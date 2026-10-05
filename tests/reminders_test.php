<?php
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../block_workloadplanner/classes/reminders.php');
use block_workloadplanner\reminders;
function check($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
}
$events = [1 => (object)['id' => 1], 2 => (object)['id' => 2],
    3 => (object)['id' => 3], 4 => (object)['id' => 4]];
$plans = [1 => (object)['reminderat' => 100], 2 => (object)['reminderat' => 99],
    3 => (object)['reminderat' => 101], 4 => (object)['reminderat' => 0],
    999 => (object)['reminderat' => 1]];
check(array_keys(reminders::due($events, $plans, 100)), [2, 1], 'Inclusive due boundary sorted oldest first');
check(reminders::due($events, $plans, 98), [], 'Future reminders excluded');
check(reminders::due([], $plans, 100), [], 'Unavailable events excluded');
check(reminders::due($events, [], 100), [], 'No saved reminder');
check(reminders::valid(0, 100), true, 'Disabled valid');
check(reminders::valid(100, 100), true, 'Action date inclusive');
check(reminders::valid(101, 100), false, 'After action date invalid');
check(reminders::valid(-1, 100), false, 'Negative invalid');
echo "8 reminder checks passed.\n";
