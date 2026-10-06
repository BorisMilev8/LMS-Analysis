<?php
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../block_workloadplanner/classes/planning_view.php');
use block_workloadplanner\planning_view;
function check($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
}
$events = [
    4 => (object)['id' => 4, 'timesort' => 300, 'course' => (object)['id' => 2]],
    2 => (object)['id' => 2, 'timesort' => 100, 'course' => (object)['id' => 3]],
    3 => (object)['id' => 3, 'timesort' => 100, 'course' => (object)['id' => 2]],
    1 => (object)['id' => 1, 'timesort' => 200, 'course' => (object)['id' => 2]],
];
$plans = [4 => (object)['priority' => 3, 'effortminutes' => 60],
    1 => (object)['priority' => 3, 'effortminutes' => 120],
    2 => (object)['priority' => 1, 'effortminutes' => 120]];
check(array_keys(planning_view::sort($events, $plans)), [2, 3, 1, 4], 'Deadline and ID tie break');
check(array_keys(planning_view::sort($events, $plans, 'priority')), [1, 4, 2, 3], 'Priority with date tie break');
check(array_keys(planning_view::sort($events, $plans, 'effort')), [2, 1, 4, 3], 'Largest effort with unknown last');
check(array_keys(planning_view::sort($events, [], 'priority')), [2, 3, 1, 4], 'Missing plans fall back to dates');
check(planning_view::sort([], $plans, 'effort'), [], 'Empty set');
check(array_keys($events), [4, 2, 3, 1], 'Input not mutated');
$filtered = planning_view::filter($events, $plans, 2);
check(array_keys(planning_view::sort($filtered, $plans, 'priority')), [1, 4, 3], 'Sort filtered activities');
check(planning_view::summary(planning_view::sort($events, $plans, 'effort'), $plans),
    planning_view::summary($events, $plans), 'Totals unchanged');
try {
    planning_view::sort($events, $plans, 'invalid');
    throw new RuntimeException('Invalid sort accepted');
} catch (InvalidArgumentException $e) {
    // Expected.
}
echo "9 sorting checks passed.\n";
