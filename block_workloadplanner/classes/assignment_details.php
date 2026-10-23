<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();

/** Resolve only assignments visible in the current user's planner, independently of AI access. */
class assignment_details {
    public static function load(int $eventid): array {
        global $DB, $CFG, $USER;
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception();
        }
        $events = planner::activities();
        if (!isset($events[$eventid])) {
            throw new \moodle_exception('unavailable', 'block_workloadplanner');
        }
        $event = $DB->get_record('event', ['id' => $eventid, 'modulename' => 'assign'], '*', MUST_EXIST);
        if ((int)$event->courseid !== (int)$events[$eventid]->course->id) {
            throw new \moodle_exception('unavailable', 'block_workloadplanner');
        }
        $cm = get_coursemodule_from_instance('assign', $event->instance, $event->courseid, false, MUST_EXIST);
        $course = get_course($event->courseid);
        $context = \context_module::instance($cm->id);
        // Calendar access already checks enrollment. This personal Dashboard page must not
        // switch its course/theme after the calendar API has initialized the page renderer.
        require_capability('mod/assign:view', $context);
        $info = get_fast_modinfo($course, $USER->id)->get_cm($cm->id);
        if (!$info->uservisible) {
            throw new \moodle_exception('unavailable', 'block_workloadplanner');
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assignment = new \assign($context, $cm, $course);
        return ['assignment' => $assignment, 'context' => $context, 'cm' => $cm,
            'course' => $course, 'event' => $events[$eventid], 'events' => $events];
    }
}
