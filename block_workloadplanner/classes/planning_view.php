<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();
/** Filtering does not alter the user's saved planning details. */
class planning_view {
    public static function filter(array $events, array $plans, int $courseid = 0, int $priority = -1): array {
        return array_filter($events, function($event) use ($plans, $courseid, $priority) {
            $savedpriority = (int)($plans[$event->id]->priority ?? 0);
            return ($courseid === 0 || (int)$event->course->id === $courseid)
                && ($priority === -1 || $savedpriority === $priority);
        });
    }
    /** Summarize only the visible result set; an unestimated activity is not zero work. */
    public static function summary(array $events, array $plans): array {
        $minutes = 0;
        $unestimated = 0;
        foreach ($events as $event) {
            $effort = (int)($plans[$event->id]->effortminutes ?? 0);
            $minutes += $effort;
            if ($effort === 0) {
                $unestimated++;
            }
        }
        return ['activities' => count($events), 'minutes' => $minutes, 'unestimated' => $unestimated];
    }
    /** Bucket visible action dates into Monday-starting weeks in the user's timezone.
     * These totals group estimated effort by deadline week; they do not schedule study time.
     */
    public static function weekly(array $events, array $plans, \DateTimeZone $timezone): array {
        $weeks = [];
        foreach ($events as $event) {
            $date = (new \DateTimeImmutable('@' . (int)$event->timesort))->setTimezone($timezone);
            $monday = $date->setTime(0, 0)->modify('-' . ((int)$date->format('N') - 1) . ' days');
            $key = $monday->format('Y-m-d');
            if (!isset($weeks[$key])) {
                $weeks[$key] = [
                    'start' => $monday->getTimestamp(),
                    'end' => $monday->modify('+6 days')->getTimestamp(),
                    'activities' => 0, 'minutes' => 0, 'unestimated' => 0, 'highpriority' => 0,
                ];
            }
            $effort = (int)($plans[$event->id]->effortminutes ?? 0);
            $weeks[$key]['activities']++;
            $weeks[$key]['minutes'] += $effort;
            $weeks[$key]['unestimated'] += ($effort === 0 ? 1 : 0);
            $weeks[$key]['highpriority'] += ((int)($plans[$event->id]->priority ?? 0) === 3 ? 1 : 0);
        }
        ksort($weeks);
        return array_values($weeks);
    }
}
