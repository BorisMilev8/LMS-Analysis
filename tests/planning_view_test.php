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
$now = 1792800000;
$weekevents = [];
foreach ([20 => -1, 21 => 0, 22 => 1, 23 => 604800, 24 => 604801] as $id => $offset) {
    $weekevents[$id] = (object)['id' => $id, 'timesort' => $now + $offset,
        'course' => (object)['id' => $id === 22 ? 3 : 2]];
}
$weekplans = [21 => (object)['priority' => 3, 'effortminutes' => 60],
    22 => (object)['priority' => 1, 'effortminutes' => 30]];
$before = serialize([$weekevents, $weekplans]);
$week = planning_view::next_seven_days($weekevents, $now);
check(array_keys($week), [21, 22, 23], 'Include now and exact seven-day boundary, exclude past and later dates');
check(array_keys(planning_view::next_seven_days(
    planning_view::filter($weekevents, $weekplans, 2, 3), $now)), [21], 'Compose course, priority and date filters');
check(planning_view::summary($week, $weekplans),
    ['activities' => 3, 'minutes' => 90, 'unestimated' => 1], 'Visible totals exclude out-of-window events');
check(array_keys(planning_view::sort($week, $weekplans, 'priority')), [21, 22, 23], 'Sorting retains date-filtered events');
check(planning_view::next_seven_days([], $now), [], 'Empty date range');
check(serialize([$weekevents, $weekplans]), $before, 'Filtering never mutates events or saved plans');
// The range is elapsed time even across daylight-saving transitions.
$dstnow = (new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('America/New_York')))->getTimestamp();
$dst = [30 => (object)['timesort' => $dstnow + 604800],
    31 => (object)['timesort' => $dstnow + 604801]];
check(array_keys(planning_view::next_seven_days($dst, $dstnow)), [30], 'DST does not extend the 168-hour window');
echo "15 planning behavior checks passed.\n";
