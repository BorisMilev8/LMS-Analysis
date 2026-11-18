<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();
/** Run inside an initialized Moodle 4.5 PHPUnit environment. */
final class planner_test extends \advanced_testcase {
    private function overdue_fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_user();
        $second = $generator->create_user();
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');
        $assignment = $generator->create_module('assign', [
            'course' => $course->id, 'duedate' => time() - 3600,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 0,
        ]);
        $eventid = $DB->get_field('event', 'id', ['modulename' => 'assign',
            'instance' => $assignment->id, 'eventtype' => 'due', 'userid' => 0, 'groupid' => 0], MUST_EXIST);
        $this->setUser($first);
        return [$first, $second, $assignment, (int)$eventid, $course];
    }

    public function test_overdue_edit_and_private_completed_state(): void {
        global $DB;
        [$first, $second, $assignment, $eventid] = $this->overdue_fixture();
        $past = overdue_assignments::read([]);
        $this->assertArrayHasKey($eventid, $past['items']);
        $this->assertEquals($assignment->duedate, $past['items'][$eventid]->timesort);
        planner::save($eventid, 3, 90, 0, 2);
        $plans = planner::priorities();
        $this->assertEmpty(overdue_assignments::unfinished(overdue_assignments::read($plans)['items'], $plans));
        // A completed personal plan can still be resolved and reset, without submitting work.
        planner::save($eventid, 3, null, null, 1);
        $this->assertEquals(90, planner::priorities()[$eventid]->effortminutes);
        $this->setUser($second);
        $past = overdue_assignments::read(planner::priorities());
        $this->assertArrayHasKey($eventid, overdue_assignments::unfinished($past['items'], planner::priorities()));
        $this->assertFalse($DB->record_exists('assign_submission', ['assignment' => $assignment->id]));
    }

    public function test_overdue_honours_extensions_and_user_overrides(): void {
        global $DB;
        [$first, $second, $assignment, $eventid] = $this->overdue_fixture();
        $DB->insert_record('assign_user_flags', (object)['assignment' => $assignment->id,
            'userid' => $first->id, 'extensionduedate' => time() + DAYSECS]);
        $this->assertEmpty(overdue_assignments::read([])['items']);
        $this->setUser($second);
        $this->assertArrayHasKey($eventid, overdue_assignments::read([])['items']);
        $overrideid = $DB->insert_record('assign_overrides', (object)['assignid' => $assignment->id,
            'userid' => $second->id, 'duedate' => time() + 2 * DAYSECS]);
        $this->assertEmpty(overdue_assignments::read([])['items']);
        $past = time() - 1800;
        $DB->set_field('assign_overrides', 'duedate', $past, ['id' => $overrideid]);
        $this->assertEquals($past, overdue_assignments::read([])['items'][$eventid]->timesort);
    }

    public function test_overdue_honours_group_override_and_user_precedence(): void {
        global $DB;
        [$first, $second, $assignment, $eventid, $course] = $this->overdue_fixture();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $first->id);
        $DB->insert_record('assign_overrides', (object)['assignid' => $assignment->id,
            'groupid' => $group->id, 'sortorder' => 1, 'duedate' => time() + DAYSECS]);
        $this->assertEmpty(overdue_assignments::read([])['items']);
        $past = time() - 1800;
        $DB->insert_record('assign_overrides', (object)['assignid' => $assignment->id,
            'userid' => $first->id, 'duedate' => $past]);
        $this->assertEquals($past, overdue_assignments::read([])['items'][$eventid]->timesort);
    }

    public function test_overdue_excludes_submitted_but_keeps_draft_and_reopened(): void {
        global $DB;
        [$first, $second, $assignment, $eventid] = $this->overdue_fixture();
        $submissionid = $DB->insert_record('assign_submission', (object)[
            'assignment' => $assignment->id, 'userid' => $first->id, 'groupid' => 0,
            'timecreated' => time(), 'timemodified' => time(), 'status' => 'submitted',
            'attemptnumber' => 0, 'latest' => 1,
        ]);
        $this->assertEmpty(overdue_assignments::read([])['items']);
        foreach (['draft', 'reopened'] as $status) {
            $DB->set_field('assign_submission', 'status', $status, ['id' => $submissionid]);
            $this->assertArrayHasKey($eventid, overdue_assignments::read([])['items']);
        }
    }

    public function test_overdue_protects_hidden_restricted_and_unenrolled_access(): void {
        global $DB, $CFG;
        [$first, $second, $assignment, $eventid] = $this->overdue_fixture();
        $CFG->enableavailability = 1;
        set_coursemodule_visible($assignment->cmid, 0);
        $this->assertEmpty(overdue_assignments::read([])['items']);
        set_coursemodule_visible($assignment->cmid, 1);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]],
            'showc' => [false],
        ]), ['id' => $assignment->cmid]);
        rebuild_course_cache($assignment->course, true);
        $this->assertEmpty(overdue_assignments::read([])['items']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertEmpty(overdue_assignments::read([])['items']);
        $this->expectException(\moodle_exception::class);
        planner::save($eventid, 0, 0, 0, 2);
    }

    public function test_overdue_keeps_visible_closed_assignments_and_excludes_undated_work(): void {
        global $DB;
        [$first, $second, $assignment, $eventid] = $this->overdue_fixture();
        $DB->set_field('assign', 'cutoffdate', time() - 60, ['id' => $assignment->id]);
        $this->assertArrayHasKey($eventid, overdue_assignments::read([])['items']);
        $DB->set_field('assign', 'duedate', 0, ['id' => $assignment->id]);
        $this->assertEmpty(overdue_assignments::read([])['items']);
    }

    public function test_overdue_excludes_group_submission_for_own_team(): void {
        global $DB;
        [$first, $second, $assignment, $eventid, $course] = $this->overdue_fixture();
        $DB->set_field('assign', 'teamsubmission', 1, ['id' => $assignment->id]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $first->id);
        $this->assertArrayHasKey($eventid, overdue_assignments::read([])['items']);
        $DB->insert_record('assign_submission', (object)[
            'assignment' => $assignment->id, 'userid' => 0, 'groupid' => $group->id,
            'timecreated' => time(), 'timemodified' => time(), 'status' => 'submitted',
            'attemptnumber' => 0, 'latest' => 1,
        ]);
        $this->assertEmpty(overdue_assignments::read([])['items']);
    }

    public function test_progress_only_record_is_private_and_can_be_reset(): void {
        [$first, $second, $eventid] = $this->fixture();
        planner::save($eventid, 0, 0, 0, 1);
        $this->assertEquals(1, planner::priorities()[$eventid]->progress);
        $this->setUser($second);
        $this->assertEmpty(planner::priorities());
        planner::save($eventid, 0, 0, 0, 2);
        $this->setUser($first);
        $this->assertEquals(1, planner::priorities()[$eventid]->progress);
        planner::save($eventid, 0, 0, 0, 0);
        $this->assertEmpty(planner::priorities());
        $this->setUser($second);
        $this->assertEquals(2, planner::priorities()[$eventid]->progress);
    }

    public function test_progress_preserves_other_fields_and_does_not_submit_work(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $assignment = $DB->get_record('assign', ['id' => $event->instance], '*', MUST_EXIST);
        $reminder = time() + 3600;
        planner::save($eventid, 3, 120, $reminder, 1);
        planner::save($eventid, 3, null, null, 2);
        $plan = planner::priorities()[$eventid];
        $this->assertEquals(120, $plan->effortminutes);
        $this->assertEquals($reminder, $plan->reminderat);
        $this->assertEquals(2, $plan->progress);
        // AI application and older callers omit progress and must preserve it.
        planner::save($eventid, 1, 90);
        $this->assertEquals(2, planner::priorities()[$eventid]->progress);
        $this->assertEquals($reminder, planner::priorities()[$eventid]->reminderat);
        $this->assertArrayHasKey($eventid, planner::activities());
        $this->assertEquals($assignment, $DB->get_record('assign', ['id' => $event->instance]));
        $this->assertFalse($DB->record_exists('assign_submission', ['assignment' => $event->instance]));
    }

    public function test_progress_rejects_invalid_values_without_changing_plan(): void {
        [$first, $second, $eventid] = $this->fixture();
        planner::save($eventid, 2, 60, 0, 1);
        foreach ([-1, 3] as $progress) {
            try {
                planner::save($eventid, 0, 0, 0, $progress);
                $this->fail('Invalid progress accepted');
            } catch (\invalid_parameter_exception $e) {
                $this->assertEquals(1, planner::priorities()[$eventid]->progress);
                $this->assertEquals(60, planner::priorities()[$eventid]->effortminutes);
            }
        }
    }

    public function test_unenrolled_user_cannot_save_progress(): void {
        [$first, $second, $eventid] = $this->fixture();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        planner::save($eventid, 0, 0, 0, 2);
    }

    private function fixture(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($first->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($second->id, $course->id, 'student');
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id, 'duedate' => time() + DAYSECS,
        ]);
        $this->setUser($first);
        $events = planner::activities();
        $this->assertNotEmpty($events);
        return [$first, $second, (int)array_key_first($events)];
    }
    public function test_priority_persistence_and_user_isolation(): void {
        [$first, $second, $eventid] = $this->fixture();
        planner::save($eventid, 3);
        $this->assertEquals(3, planner::priorities()[$eventid]->priority);
        $this->setUser($second);
        $this->assertEmpty(planner::priorities());
        planner::save($eventid, 1);
        $this->setUser($first);
        $this->assertEquals(3, planner::priorities()[$eventid]->priority);
        planner::save($eventid, 0);
        $this->assertEmpty(planner::priorities());
        $this->setUser($second);
        $this->assertEquals(1, planner::priorities()[$eventid]->priority);
    }
    public function test_unenrolled_user_cannot_save_priority(): void {
        [$first, $second, $eventid] = $this->fixture();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->expectException(\moodle_exception::class);
        planner::save($eventid, 3);
    }
    public function test_rejects_invalid_priority(): void {
        [$first, $second, $eventid] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        planner::save($eventid, 4);
    }
    public function test_privacy_deletion_preserves_other_user(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        planner::save($eventid, 3);
        $this->setUser($second);
        planner::save($eventid, 2);
        \block_workloadplanner\privacy\provider::delete_data_for_all_users_in_context(
            \context_user::instance($first->id));
        $this->assertFalse($DB->record_exists('block_workloadplanner_plan', ['userid' => $first->id]));
        $this->assertTrue($DB->record_exists('block_workloadplanner_plan', ['userid' => $second->id]));
    }
    public function test_effort_survives_priority_only_update_and_can_be_cleared(): void {
        [$first, $second, $eventid] = $this->fixture();
        planner::save($eventid, 3, 120);
        planner::save($eventid, 1);
        $this->assertEquals(120, planner::priorities()[$eventid]->effortminutes);
        planner::save($eventid, 0, 120);
        $this->assertEquals(0, planner::priorities()[$eventid]->priority);
        $this->assertEquals(120, planner::priorities()[$eventid]->effortminutes);
        planner::save($eventid, 0, 0);
        $this->assertEmpty(planner::priorities());
    }
    public function test_effort_rejects_out_of_range_input(): void {
        [$first, $second, $eventid] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        planner::save($eventid, 3, 10081);
    }
    public function test_reminder_only_record_and_isolation(): void {
        [$first, $second, $eventid] = $this->fixture();
        $at = time() + 3600;
        planner::save($eventid, 0, 0, $at);
        $this->assertEquals($at, planner::priorities()[$eventid]->reminderat);
        $this->setUser($second);
        $this->assertEmpty(planner::priorities());
        $this->setUser($first);
        planner::save($eventid, 0, 0, 0);
        $this->assertEmpty(planner::priorities());
    }
    public function test_reminder_after_action_date_rejected(): void {
        [$first, $second, $eventid] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        planner::save($eventid, 0, 0, time() + 2 * DAYSECS);
    }

    public function test_assignment_analysis_source_and_access(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $DB->set_field('assign', 'intro', '<p>Write a 1000-word report.</p>', ['id' => $event->instance]);
        $source = assignment_analysis::source($eventid);
        $this->assertSame('Write a 1000-word report.', $source['instructions']);
        $this->assertSame(CONTEXT_MODULE, $source['context']->contextlevel);
        $this->assertArrayHasKey($eventid, $source['events']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        assignment_analysis::source($eventid);
    }

    public function test_hidden_assignment_instructions_cannot_be_analyzed(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $DB->set_field('assign', 'alwaysshowdescription', 0, ['id' => $event->instance]);
        $DB->set_field('assign', 'allowsubmissionsfromdate', time() + 3600, ['id' => $event->instance]);
        $this->expectException(\moodle_exception::class);
        assignment_analysis::source($eventid);
    }

    public function test_analysis_disabled_by_default_and_threshold_fallback(): void {
        $this->resetAfterTest();
        $this->assertFalse(assignment_analysis::available());
        $this->assertSame(600, assignment_analysis::threshold());
        set_config('weeklythreshold', 120, 'block_workloadplanner');
        $this->assertSame(120, assignment_analysis::threshold());
        set_config('weeklythreshold', -5, 'block_workloadplanner');
        $this->assertSame(600, assignment_analysis::threshold());
    }

    public function test_analysis_rejected_without_changing_existing_plan(): void {
        [$first, $second, $eventid] = $this->fixture();
        planner::save($eventid, 3, 120);
        try {
            assignment_analysis::generate('Report', 'Write a report.', 'some', \context_user::instance($first->id)->id);
            $this->fail('Disabled analysis must not call a provider');
        } catch (\moodle_exception $e) {
            $this->assertSame('analysisunavailable', $e->errorcode);
        }
        $this->assertEquals(120, planner::priorities()[$eventid]->effortminutes);
        $this->assertEquals(3, planner::priorities()[$eventid]->priority);
    }

    public function test_assignment_details_do_not_require_ai_access(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $DB->set_field('assign', 'intro', '<p>Read the rubric.</p>', ['id' => $event->instance]);
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('block/workloadplanner:analyze', CAP_PROHIBIT, $studentrole,
            \context_course::instance($event->courseid)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $details = assignment_details::load($eventid);
        $this->assertFalse(has_capability('block/workloadplanner:analyze', $details['context']));
        $this->assertSame((int)$event->courseid, (int)$details['course']->id);
        $this->assertSame((int)$event->instance, (int)$details['assignment']->get_instance()->id);
        $this->assertSame('<p>Read the rubric.</p>', $details['assignment']->get_instance()->intro);
        $this->expectException(\required_capability_exception::class);
        assignment_analysis::source($eventid);
    }

    public function test_assignment_details_preserve_instruction_release_rules(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $DB->set_field('assign', 'alwaysshowdescription', 0, ['id' => $event->instance]);
        $DB->set_field('assign', 'allowsubmissionsfromdate', time() + 3600, ['id' => $event->instance]);
        $details = assignment_details::load($eventid);
        $this->assertFalse($details['assignment']->show_intro());
    }

    public function test_assignment_details_reject_unenrolled_user(): void {
        [$first, $second, $eventid] = $this->fixture();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        assignment_details::load($eventid);
    }

    public function test_assignment_details_reject_hidden_module(): void {
        global $DB;
        [$first, $second, $eventid] = $this->fixture();
        $event = $DB->get_record('event', ['id' => $eventid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assign', $event->instance);
        set_coursemodule_visible($cm->id, 0);
        $this->expectException(\moodle_exception::class);
        assignment_details::load($eventid);
    }

}
