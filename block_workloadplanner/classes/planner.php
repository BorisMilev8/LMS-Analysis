<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();
/** Current-user calendar access and private priority persistence. */
class planner {
    const LIMIT = 200;
    /** Read actionable events through Moodle's permission-aware external calendar API. */
    public static function activities(): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/externallib.php');
        $from = time();
        $to = $from + 90 * DAYSECS;
        $events = [];
        $after = 0;
        // Core accepts up to 50 events per page. No unbounded course/event query.
        for ($page = 0; $page < 4; $page++) {
            $result = \core_calendar_external::get_calendar_action_events_by_timesort(
                $from, $to, $after, 50, true);
            $batch = $result->events;
            foreach ($batch as $event) {
                if (!empty($event->course->id) && $event->course->id != SITEID
                        && !empty($event->action->url)) {
                    $events[(int)$event->id] = $event;
                }
            }
            if (count($batch) < 50) {
                break;
            }
            $last = end($batch);
            $next = (int)$last->id;
            if ($next === $after) {
                break;
            }
            $after = $next;
        }
        return $events;
    }
    /** User IDs always come from the authenticated session, never from submitted data. */
    public static function priorities(): array {
        global $DB, $USER;
        return $DB->get_records('block_workloadplanner_plan', ['userid' => $USER->id], '',
            'eventid, priority, effortminutes, reminderat, timemodified');
    }
    public static function save(int $eventid, int $priority, ?int $effortminutes = null, ?int $reminderat = null): void {
        global $DB, $USER;
        if ($priority < 0 || $priority > 3) {
            throw new \invalid_parameter_exception('Invalid priority');
        }
        if ($effortminutes !== null && ($effortminutes < 0 || $effortminutes > 10080)) {
            throw new \invalid_parameter_exception('Effort must be between 0 and 10080 minutes');
        }
        $events = self::activities();
        if (!isset($events[$eventid])) {
            throw new \moodle_exception('unavailable', 'block_workloadplanner');
        }
        $conditions = ['userid' => $USER->id, 'eventid' => $eventid];
        $record = $DB->get_record('block_workloadplanner_plan', $conditions);
        $effortminutes = $effortminutes ?? (int)($record->effortminutes ?? 0);
        $reminderat = $reminderat ?? (int)($record->reminderat ?? 0);
        if (!reminders::valid($reminderat, (int)$events[$eventid]->timesort)) {
            throw new \invalid_parameter_exception('Reminder must be on or before the activity action date');
        }
        if ($priority === 0 && $effortminutes === 0 && $reminderat === 0) {
            $DB->delete_records('block_workloadplanner_plan', $conditions);
            return;
        }
        if (!$record) {
            $record = (object)$conditions;
            $record->priority = $priority;
            $record->effortminutes = $effortminutes;
            $record->reminderat = $reminderat;
            $record->timemodified = time();
            $DB->insert_record('block_workloadplanner_plan', $record);
        } else {
            $record->priority = $priority;
            $record->effortminutes = $effortminutes;
            $record->reminderat = $reminderat;
            $record->timemodified = time();
            $DB->update_record('block_workloadplanner_plan', $record);
        }
    }
}
