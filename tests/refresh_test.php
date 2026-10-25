<?php
// Standalone tests for atomic data loading and safe failure states.
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../block_workloadplanner/classes/refresh_result.php');
use block_workloadplanner\refresh_result;
$checks = 0;
function check($actual, $expected, string $description): void {
    global $checks;
    $checks++;
    if ($actual !== $expected) {
        throw new RuntimeException($description);
    }
}
$events = [12 => (object)['id' => 12]];
$plans = [12 => (object)['priority' => 3, 'effortminutes' => 120, 'reminderat' => 100]];
$before = serialize([$events, $plans]);
$start = time();
$result = refresh_result::load(fn() => $events, fn() => $plans);
check($result['success'], true, 'Successful reads are marked successful');
check($result['refreshedat'] >= $start && $result['refreshedat'] <= time(), true, 'Timestamp describes this completed refresh');
check($result['events'], $events, 'Current events retained');
check($result['plans'], $plans, 'Current saved plans retained');
$empty = refresh_result::load(fn() => [], fn() => []);
check($empty['success'], true, 'An empty calendar is a success, not an error');
check(is_int($empty['refreshedat']), true, 'Empty calendar still has a successful refresh time');
$plancalls = 0;
$failure = refresh_result::load(function() { throw new RuntimeException('private database credential'); },
    function() use (&$plancalls) { $plancalls++; return []; });
check($plancalls, 0, 'Do not continue after calendar failure');
check($failure, ['success' => false, 'refreshedat' => null, 'events' => [], 'plans' => []],
    'Failure is distinct from empty success and has no exception text or success timestamp');
$partial = refresh_result::load(fn() => $events, function() { throw new RuntimeException('query failed'); });
check($partial['events'], [], 'Discard partial calendar results when personal plans cannot load');
check($partial['success'], false, 'Personal-plan failure is reported');
$retry = refresh_result::load(fn() => $events, fn() => $plans);
check($retry['success'], true, 'A later successful retry recovers without cached failure');
check(serialize([$events, $plans]), $before, 'Reads never mutate stored planning values');
echo "$checks refresh behavior checks passed.\n";
