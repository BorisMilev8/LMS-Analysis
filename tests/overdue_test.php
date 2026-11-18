<?php
// Offline Moodle adapter contract; real module/override behavior is covered separately in PHPUnit.
define('MOODLE_INTERNAL', true);
define('DAYSECS', 86400);
define('ASSIGN_SUBMISSION_STATUS_SUBMITTED', 'submitted');
class require_login_exception extends RuntimeException {}
$USER = (object)['id' => 1];
$now = 1800000000;
$due = $now - 100;
$extension = 0;
$override = null;
$visible = true;
$enrolled = true;
$capability = true;
$status = null;
$team = false;
$group = (object)['id' => 5];
$online = true;
$loggedin = true;
$modulecount = 1;
$events = [7 => (object)['id' => 7], 8 => (object)['id' => 8]];
function isloggedin() { global $loggedin; return $loggedin; }
function isguestuser() { return false; }
function enrol_get_my_courses(...$args) { return [(object)['id' => 2, 'fullname' => 'Test course', 'visible' => 1]]; }
function is_enrolled(...$args) { global $enrolled; return $enrolled; }
function has_capability(...$args) { global $capability; return $capability; }
class context_course { public static function instance($id) { return (object)['id' => $id]; } }
class context_module { public static function instance($id) { return (object)['id' => $id]; } }
function get_fast_modinfo(...$args) {
    return new class {
        public function get_instances_of($name) {
            global $visible, $modulecount;
            return array_fill(0, $modulecount, (object)['id' => 3, 'uservisible' => $visible]);
        }
    };
}
class moodle_url {
    public function __construct(private string $url, private array $params) {}
    public function out($escape) { return $this->url . '?' . http_build_query($this->params); }
}
class assign {
    private $due;
    public function __construct(...$args) { global $due; $this->due = $due; }
    public function update_effective_access($userid) { global $override; $this->due = $override ?? $this->due; }
    public function get_instance($userid = null) {
        global $team;
        return (object)['id' => 4, 'name' => 'Report', 'duedate' => $this->due, 'teamsubmission' => $team];
    }
    public function get_user_flags($userid, $create) {
        global $extension;
        if ($create) { throw new RuntimeException('Read created user flags'); }
        return (object)['extensionduedate' => $extension];
    }
    public function is_any_submission_plugin_enabled() { global $online; return $online; }
    public function get_submission_group($userid) { global $group; return $group; }
    public function get_group_submission($userid, $groupid, $create) {
        if ($groupid !== 5) { throw new RuntimeException('Wrong submission group'); }
        return $this->get_user_submission($userid, $create);
    }
    public function get_user_submission($userid, $create) {
        global $status;
        if ($create) { throw new RuntimeException('Read created a submission'); }
        return $status === null ? false : (object)['status' => $status];
    }
}
$DB = new class {
    public function get_records(...$args) { global $events; return $events; }
};
$root = sys_get_temp_dir() . '/overdue-contract-' . bin2hex(random_bytes(8));
mkdir($root . '/mod/assign', 0700, true);
file_put_contents($root . '/mod/assign/locallib.php', '<?php');
register_shutdown_function(function() use ($root) {
    unlink($root . '/mod/assign/locallib.php');
    rmdir($root . '/mod/assign'); rmdir($root . '/mod'); rmdir($root);
});
$CFG = (object)['dirroot' => $root];
require_once(__DIR__ . '/../block_workloadplanner/classes/overdue_assignments.php');
require_once(__DIR__ . '/../block_workloadplanner/classes/planning_view.php');
use block_workloadplanner\overdue_assignments as overdue;
use block_workloadplanner\planning_view;
$checks = 0;
function check($actual, $expected, $message) {
    global $checks;
    $checks++;
    if ($actual !== $expected) { throw new RuntimeException($message); }
}
function readitems($plans = []) { global $now; return overdue::read($plans, $now)['items']; }
check(count(readitems()), 1, 'Past unsubmitted assignment appears once despite override events');
check(overdue::within_window(0, $now), false, 'No deadline is not overdue');
check(overdue::within_window($now, $now), false, 'Exact due instant is not past');
check(overdue::within_window($now - 90 * DAYSECS, $now), true, '90-day boundary included');
check(overdue::within_window($now - 90 * DAYSECS - 1, $now), false, 'Older work excluded');
$extension = $now + 500;
check(readitems(), [], 'Individual future extension prevents overdue label');
$extension = 0; $override = $now + 100;
check(readitems(), [], 'Future user/group override prevents overdue label');
$override = $now - 500;
check(readitems()[7]->timesort, $override, 'Overridden deadline is shown');
$override = null; $visible = false;
check(readitems(), [], 'Hidden/restricted modules excluded');
$visible = true; $enrolled = false;
check(readitems(), [], 'Inactive/non-enrolled course excluded');
$enrolled = true; $capability = false;
check(readitems(), [], 'View/submit capability required');
$capability = true; $status = 'submitted';
check(readitems(), [], 'Submitted assignment excluded');
$status = 'draft';
check(count(readitems()), 1, 'Draft is unfinished');
$status = 'reopened';
check(count(readitems()), 1, 'Reopened attempt is unfinished');
$team = true; $status = 'submitted';
check(readitems(), [], 'Submitted team assignment excluded');
$status = null; $group = false;
check(readitems(), [], 'Missing/ambiguous submission group excluded');
$team = false; $online = false;
check(readitems(), [], 'Offline-only assignment excluded');
$online = true;
$plans = [8 => (object)['progress' => 2, 'timemodified' => 50]];
$items = readitems($plans);
check(array_keys($items), [8], 'Existing override-event personal plan retained');
check(overdue::unfinished($items, $plans), [], 'Personal Completed hidden from overdue list');
check(count(overdue::unfinished($items, [])), 1, 'Another user without that plan sees unfinished work');
check(planning_view::filter($items, $plans, 99), [], 'Course filter applies');
check(planning_view::filter($items, $plans, 0, 3), [], 'Priority filter applies');
check(overdue::anchor($events, [7 => (object)['timemodified' => 20], 8 => (object)['timemodified' => 30]]),
    8, 'Latest personal record selected deterministically');
$modulecount = 201;
check(overdue::read([], $now)['limited'], true, 'Scan bound is explicitly reported');
$loggedin = false;
try { readitems(); throw new RuntimeException('Anonymous read accepted'); }
catch (require_login_exception $e) { check(true, true, 'Anonymous access rejected'); }
echo "$checks overdue adapter and boundary checks passed (offline doubles).\n";
