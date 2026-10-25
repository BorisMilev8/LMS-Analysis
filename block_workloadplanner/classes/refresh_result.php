<?php
namespace block_workloadplanner;
defined('MOODLE_INTERNAL') || die();

/** Fetch the complete visible planner state or return an explicit failure, never partial totals. */
class refresh_result {
    public static function load(callable $activities, callable $priorities): array {
        try {
            $events = $activities();
            $plans = $priorities();
            return ['success' => true, 'refreshedat' => time(), 'events' => $events, 'plans' => $plans];
        } catch (\Throwable $e) {
            // Never expose database details, credentials or exception text in a student-facing message.
            // No stale data or successful timestamp is returned after either read fails.
            return ['success' => false, 'refreshedat' => null, 'events' => [], 'plans' => []];
        }
    }
}
