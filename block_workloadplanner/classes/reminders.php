<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();
/** In-planner reminders, evaluated on page load; no external delivery. */
class reminders {
    public static function valid(int $reminderat, int $actiondate): bool {
        return $reminderat >= 0 && ($reminderat === 0 || $reminderat <= $actiondate);
    }
    public static function due(array $events, array $plans, int $now): array {
        $due = array_filter($events, function($event) use ($plans, $now) {
            $at = (int)($plans[$event->id]->reminderat ?? 0);
            return $at > 0 && $at <= $now;
        });
        uasort($due, function($a, $b) use ($plans) {
            return (int)$plans[$a->id]->reminderat <=> (int)$plans[$b->id]->reminderat;
        });
        return $due;
    }
}
