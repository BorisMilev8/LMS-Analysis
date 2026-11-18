<?php
// Offline persistence contract checks. Moodle integration remains a separate CI stage.
define('MOODLE_INTERNAL', true);
define('DAYSECS', 86400);
define('SITEID', 1);
class invalid_parameter_exception extends RuntimeException {}
class moodle_exception extends RuntimeException {
    public function __construct($message, $component = '') { parent::__construct($message); }
}
class core_calendar_external {
    public static function get_calendar_action_events_by_timesort(...$args) {
        global $USER;
        return (object)['events' => $USER->id === 99 ? [] : [(object)[
            'id' => 7, 'timesort' => time() + DAYSECS,
            'course' => (object)['id' => 2], 'action' => (object)['url' => '/mod/assign/view.php?id=3'],
        ]]];
    }
}
$root = sys_get_temp_dir() . '/planner-progress-' . bin2hex(random_bytes(8));
mkdir($root . '/calendar', 0700, true);
file_put_contents($root . '/calendar/externallib.php', '<?php');
register_shutdown_function(function() use ($root) {
    unlink($root . '/calendar/externallib.php');
    rmdir($root . '/calendar');
    rmdir($root);
});
$CFG = (object)['dirroot' => $root];
$USER = (object)['id' => 10];
$DB = new class {
    private array $rows = [];
    private int $nextid = 1;
    public function get_records($table, $conditions, $sort = '', $fields = '*') {
        $result = [];
        foreach ($this->rows as $record) {
            foreach ($conditions as $key => $value) {
                if ($record->$key != $value) { continue 2; }
            }
            $result[$record->eventid] = clone $record;
        }
        return $result;
    }
    public function get_record($table, $conditions) {
        return array_values($this->get_records($table, $conditions))[0] ?? false;
    }
    public function insert_record($table, $record) {
        $record = clone $record;
        $record->id = $this->nextid++;
        $this->rows[$record->id] = $record;
    }
    public function update_record($table, $record) { $this->rows[$record->id] = clone $record; }
    public function delete_records($table, $conditions) {
        foreach ($this->get_records($table, $conditions) as $record) { unset($this->rows[$record->id]); }
    }
};
require_once(__DIR__ . '/../block_workloadplanner/classes/reminders.php');
require_once(__DIR__ . '/../block_workloadplanner/classes/planner.php');
use block_workloadplanner\planner;
$checks = 0;
function check($actual, $expected, $message) {
    global $checks;
    $checks++;
    if ($actual !== $expected) { throw new RuntimeException($message); }
}
planner::save(7, 0, 0, 0, 1);
check(planner::priorities()[7]->progress, 1, 'Progress-only record must persist');
$USER->id = 20;
check(planner::priorities(), [], 'Another student must not see this plan');
planner::save(7, 0, 0, 0, 2);
$USER->id = 10;
check(planner::priorities()[7]->progress, 1, 'Other student must not change this progress');
$reminder = time() + 100;
planner::save(7, 3, 120, $reminder, 2);
planner::save(7, 1, 90); // Same signature used by AI apply.
check(planner::priorities()[7]->progress, 2, 'AI/priority update must preserve progress');
check(planner::priorities()[7]->reminderat, $reminder, 'AI update must preserve reminder');
planner::save(7, 1, null, null, 0);
check(planner::priorities()[7]->effortminutes, 90, 'Progress reset must preserve estimate');
check(planner::priorities()[7]->priority, 1, 'Progress reset must preserve priority');
check(planner::priorities()[7]->reminderat, $reminder, 'Progress reset must preserve reminder');
foreach ([-1, 3] as $invalid) {
    try { planner::save(7, 0, 0, 0, $invalid); throw new RuntimeException('Invalid progress accepted'); }
    catch (invalid_parameter_exception $e) {
        check(planner::priorities()[7]->effortminutes, 90, 'Rejected request must not change plan');
    }
}
$USER->id = 99;
try { planner::save(7, 0, 0, 0, 2); throw new RuntimeException('Unenrolled user accepted'); }
catch (moodle_exception $e) { check(planner::priorities(), [], 'Unavailable event must not be saved'); }
$USER->id = 10;
planner::save(7, 0, 0, 0, 0);
check(planner::priorities(), [], 'Reset all personal fields must remove record');
$USER->id = 20;
check(planner::priorities()[7]->progress, 2, 'Reset must preserve another student');
echo "$checks progress persistence checks passed (offline doubles).\n";
