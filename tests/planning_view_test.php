<?php
// Standalone behavioral tests for filtering and visible-workload summaries.
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../block_workloadplanner/classes/planning_view.php');
use block_workloadplanner\planning_view;
function check($actual, $expected, string $description): void {
    if ($actual !== $expected) {
        throw new RuntimeException($description . ': ' . var_export($actual, true));
    }
}
$events = [
    10 => (object)['id' => 10, 'course' => (object)['id' => 2]],
    11 => (object)['id' => 11, 'course' => (object)['id' => 3]],
    12 => (object)['id' => 12, 'course' => (object)['id' => 2]],
];
$plans = [
    10 => (object)['priority' => 3, 'effortminutes' => 120],
    11 => (object)['priority' => 1, 'effortminutes' => 45],
    999 => (object)['priority' => 3, 'effortminutes' => 500],
];
check(array_keys(planning_view::filter($events, $plans)), [10, 11, 12], 'Default preserves all visible events');
check(array_keys(planning_view::filter($events, $plans, 2)), [10, 12], 'Course filter');
check(array_keys(planning_view::filter($events, $plans, 0, 0)), [12], 'Unset priority includes activities without plans');
check(array_keys(planning_view::filter($events, $plans, 2, 3)), [10], 'Combined filters');
check(planning_view::filter($events, $plans, 99), [], 'Unknown course reveals nothing');
check(planning_view::summary($events, $plans), ['activities' => 3, 'minutes' => 165, 'unestimated' => 1],
    'Ignore stale plans and distinguish unknown effort');
check(planning_view::summary(planning_view::filter($events, $plans, 2), $plans),
    ['activities' => 2, 'minutes' => 120, 'unestimated' => 1], 'Summary reflects filtered set');
check(planning_view::summary([], $plans), ['activities' => 0, 'minutes' => 0, 'unestimated' => 0], 'Empty summary');
echo "8 planning behavior checks passed.\n";
