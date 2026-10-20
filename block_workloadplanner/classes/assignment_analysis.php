<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();

/** Moodle access checks and the configured core AI provider, with no private API keys here. */
class assignment_analysis {
    public static function available(): bool {
        return (bool)get_config('block_workloadplanner', 'enableanalysis')
            && \core_ai\manager::is_action_available(\core_ai\aiactions\generate_text::class);
    }

    public static function threshold(): int {
        $value = (int)get_config('block_workloadplanner', 'weeklythreshold');
        return $value >= 60 && $value <= 10080 ? $value : 600;
    }

    /** Resolve server-side calendar ownership and module visibility; never trust a submitted assignment ID. */
    public static function source(int $eventid): array {
        global $DB, $CFG, $USER;
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
        require_login($course, false, $cm);
        require_capability('mod/assign:view', $context);
        require_capability('block/workloadplanner:analyze', $context);
        $info = get_fast_modinfo($course, $USER->id)->get_cm($cm->id);
        if (!$info->uservisible) {
            throw new \moodle_exception('unavailable', 'block_workloadplanner');
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assignment = new \assign($context, $cm, $course);
        if (!$assignment->show_intro()) {
            throw new \moodle_exception('instructionsunavailable', 'block_workloadplanner');
        }
        $instance = $assignment->get_instance();
        // No attachments, submissions, names, grades or other students' data are sent.
        $instructions = trim(html_to_text($instance->intro, 0, false));
        $title = trim(html_to_text($instance->name, 0, false));
        return ['title' => $title, 'instructions' => $instructions, 'context' => $context,
            'event' => $events[$eventid], 'events' => $events,
            'hash' => hash('sha256', $title . "\n" . $instructions)];
    }

    public static function generate(string $title, string $text, string $experience, int $contextid): array {
        global $USER;
        if (!self::available()) {
            throw new \moodle_exception('analysisunavailable', 'block_workloadplanner');
        }
        if (!\core_ai\manager::get_user_policy_status($USER->id)) {
            throw new \moodle_exception('analysisconsentrequired', 'block_workloadplanner');
        }
        $action = new \core_ai\aiactions\generate_text($contextid, $USER->id,
            analysis_rules::prompt($title, $text, $experience));
        $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        if (!$response->get_success()) {
            throw new \moodle_exception('analysisfailed', 'block_workloadplanner');
        }
        try {
            return analysis_rules::parse((string)($response->get_response_data()['generatedcontent'] ?? ''));
        } catch (\Throwable $e) {
            throw new \moodle_exception('analysisinvalid', 'block_workloadplanner');
        }
    }
}
