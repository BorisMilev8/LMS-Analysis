<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();
/** Run inside an initialized Moodle 4.5 PHPUnit environment. */
final class planner_test extends \advanced_testcase {
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
}
