<?php
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../block_workloadplanner/classes/planning_view.php');
use block_workloadplanner\planning_view;
function check($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
}
function activity(int $id, string $date): object {
    return (object)['id' => $id, 'timesort' => strtotime($date), 'course' => (object)['id' => 2]];
}
$tz = new DateTimeZone('America/New_York');
$events = [
    activity(1, '2026-11-02 05:00:00 UTC'), // Monday midnight after DST ends.
    activity(2, '2026-11-02 04:59:59 UTC'), // Sunday in New York, Monday in UTC.
    activity(3, '2026-11-01 06:30:00 UTC'), // Repeated DST hour.
];
$plans = [1 => (object)['effortminutes' => 90, 'priority' => 3],
    2 => (object)['effortminutes' => 30, 'priority' => 1],
    99 => (object)['effortminutes' => 500, 'priority' => 3]];
$weeks = planning_view::weekly($events, $plans, $tz);
check(count($weeks), 2, 'Timezone creates two weeks');
check((new DateTimeImmutable('@' . $weeks[0]['start']))->setTimezone($tz)->format('Y-m-d'), '2026-10-26', 'Oldest week first');
check($weeks[0]['activities'], 2, 'Sunday and repeated-hour dates bucket together');
check($weeks[0]['minutes'], 30, 'Unknown estimates and stale records do not inflate total');
check($weeks[0]['unestimated'], 1, 'Unknown effort counted');
check($weeks[1]['highpriority'], 1, 'High priorities counted');
check($weeks[1]['minutes'], 90, 'Monday begins next bucket');
$boundary = planning_view::weekly([activity(4, '2027-01-01 12:00:00 UTC')], [], $tz);
check((new DateTimeImmutable('@' . $boundary[0]['start']))->setTimezone($tz)->format('Y-m-d'),
    '2026-12-28', 'Week crosses year boundary');
check(planning_view::weekly([], $plans, $tz), [], 'Empty input');
$filtered = planning_view::filter($events, $plans, 2, 3);
check(array_sum(array_column(planning_view::weekly($filtered, $plans, $tz), 'minutes')), 90,
    'Weekly results respect filters');
echo "10 weekly workload checks passed.\n";
