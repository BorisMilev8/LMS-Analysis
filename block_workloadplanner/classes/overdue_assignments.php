<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();

/** Read overdue online assignments using current-user module access and effective dates. */
class overdue_assignments {
    const LIMIT = 200;
    const LOOKBACK_DAYS = 90;

    /** Completed personal plans remain available for editing, but not in unfinished(). */
    public static function read(array $plans, ?int $now = null): array {
        global $CFG, $USER, $DB;
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception();
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $now = $now ?? time();
        $items = [];
        $checked = 0;
        $courses = enrol_get_my_courses('id,fullname', 'id ASC');
        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            if (!$course->visible || !is_enrolled($coursecontext, $USER, '', true)) {
                continue;
            }
            $modinfo = get_fast_modinfo($course, $USER->id);
            foreach ($modinfo->get_instances_of('assign') as $cm) {
                $context = \context_module::instance($cm->id);
                if (!$cm->uservisible || !has_capability('mod/assign:view', $context)
                        || !has_capability('mod/assign:submit', $context)) {
                    continue;
                }
                if ($checked >= self::LIMIT) {
                    return ['items' => $items, 'limited' => true];
                }
                $checked++;
                $assignment = new \assign($context, $cm, $course);
                $assignment->update_effective_access($USER->id);
                $instance = $assignment->get_instance($USER->id);
                $flags = $assignment->get_user_flags($USER->id, false);
                $due = max((int)$instance->duedate, (int)($flags->extensionduedate ?? 0));
                if (!self::within_window($due, $now) || !$assignment->is_any_submission_plugin_enabled()) {
                    continue;
                }
                if ($instance->teamsubmission) {
                    // Ambiguous/no submission group must not read another team's work.
                    $group = $assignment->get_submission_group($USER->id);
                    if (!$group) {
                        continue;
                    }
                    $submission = $assignment->get_group_submission($USER->id, $group->id, false);
                } else {
                    $submission = $assignment->get_user_submission($USER->id, false);
                }
                if ($submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                    continue;
                }
                // Reuse the student's existing event-keyed plan across assignment overrides.
                // Event rows supply identity only; permissions and dates come from the module above.
                $anchors = $DB->get_records('event', ['modulename' => 'assign',
                    'instance' => $instance->id, 'courseid' => $course->id, 'eventtype' => 'due'], 'id ASC');
                if (!$anchors) {
                    continue;
                }
                $anchor = self::anchor($anchors, $plans);
                $items[$anchor] = (object)[
                    'id' => $anchor, 'name' => $instance->name, 'timesort' => $due,
                    'course' => (object)['id' => $course->id, 'fullname' => $course->fullname],
                    'action' => (object)['url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false)],
                ];
            }
        }
        return ['items' => $items, 'limited' => false];
    }

    public static function within_window(int $due, int $now): bool {
        return $due > 0 && $due < $now && $due >= $now - self::LOOKBACK_DAYS * DAYSECS;
    }

    /** Prefer the most recently saved private plan, then the smallest event ID. */
    public static function anchor(array $events, array $plans): int {
        $ids = array_keys($events);
        usort($ids, function($a, $b) use ($plans) {
            $saved = isset($plans[$b]) <=> isset($plans[$a]);
            return $saved ?: ((int)($plans[$b]->timemodified ?? 0) <=> (int)($plans[$a]->timemodified ?? 0))
                ?: ((int)$a <=> (int)$b);
        });
        return (int)reset($ids);
    }

    public static function unfinished(array $items, array $plans): array {
        return array_filter($items, function($item) use ($plans) {
            return (int)($plans[$item->id]->progress ?? 0) !== 2;
        });
    }
}
