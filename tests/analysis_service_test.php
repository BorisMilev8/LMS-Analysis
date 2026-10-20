<?php
// Offline contract tests for the Moodle AI adapter. These doubles do not replace Moodle integration tests.
namespace core_ai\aiactions {
    class generate_text {
        public function __construct(public int $contextid, public int $userid, public string $prompttext) {}
    }
}
namespace core_ai {
    class manager {
        public static bool $available = true;
        public static bool $policy = true;
        public static function is_action_available($action): bool { return self::$available; }
        public static function get_user_policy_status($userid): bool { return self::$policy; }
    }
}
namespace core {
    class di {
        public static $manager;
        public static function get($classname) { return self::$manager; }
    }
}
namespace {
    define('MOODLE_INTERNAL', true);
    class moodle_exception extends \Exception {
        public function __construct(string $errorcode, string $module = '') { parent::__construct($errorcode); }
    }
    $config = ['enableanalysis' => 1];
    function get_config($plugin, $key) { global $config; return $config[$key] ?? false; }
    require_once(__DIR__ . '/../block_workloadplanner/classes/analysis_rules.php');
    require_once(__DIR__ . '/../block_workloadplanner/classes/assignment_analysis.php');
    use block_workloadplanner\assignment_analysis;
    $USER = (object)['id' => 42, 'firstname' => 'Should not be sent', 'email' => 'private@example.com'];
    $calls = 0;
    $success = true;
    $content = '{"summary":"Write a report","minminutes":60,"maxminutes":120,"assumptions":"No rubric supplied"}';
    \core\di::$manager = new class {
        public function process_action($action) {
            global $calls, $success, $content;
            $calls++;
            if ($action->contextid !== 12 || $action->userid !== 42
                    || strpos($action->prompttext, 'private@example.com') !== false
                    || strpos($action->prompttext, 'Should not be sent') !== false) {
                throw new \RuntimeException('Incorrect action or personal data in prompt');
            }
            return new class($success, $content) {
                public function __construct(private bool $success, private string $content) {}
                public function get_success() { return $this->success; }
                public function get_response_data() { return ['generatedcontent' => $this->content]; }
            };
        }
    };
    $checks = 0;
    function check($actual, $expected, string $message): void {
        global $checks;
        $checks++;
        if ($actual !== $expected) { throw new \RuntimeException($message); }
    }
    function attempt(string $expected): void {
        try {
            assignment_analysis::generate('Report', 'Write a report', 'some', 12);
        } catch (moodle_exception $e) {
            check($e->getMessage(), $expected, 'Safe error returned');
            return;
        }
        throw new \RuntimeException('Expected failure');
    }
    check(assignment_analysis::generate('Report', 'Write a report', 'some', 12)['effortminutes'], 90,
        'Core provider response parsed');
    check($calls, 1, 'Only one explicit generation call');
    $config['enableanalysis'] = 0;
    attempt('analysisunavailable');
    check($calls, 1, 'Disabled feature never calls AI');
    $config['enableanalysis'] = 1;
    \core_ai\manager::$available = false;
    attempt('analysisunavailable');
    check($calls, 1, 'Unconfigured provider never called');
    \core_ai\manager::$available = true;
    \core_ai\manager::$policy = false;
    attempt('analysisconsentrequired');
    check($calls, 1, 'No policy acceptance means no transfer');
    \core_ai\manager::$policy = true;
    $success = false;
    attempt('analysisfailed');
    $success = true;
    $content = 'Invalid response';
    attempt('analysisinvalid');
    check(assignment_analysis::threshold(), 600, 'Default threshold');
    $config['weeklythreshold'] = 120;
    check(assignment_analysis::threshold(), 120, 'Configured threshold');
    $config['weeklythreshold'] = -1;
    check(assignment_analysis::threshold(), 600, 'Invalid threshold fallback');
    echo "$checks AI adapter contract checks passed (offline doubles).\n";
}
