<?php
namespace block_workloadplanner\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
/** Personal priorities belong to the user's context, not to the dashboard block instance. */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_workloadplanner_plan', [
            'userid' => 'privacy:metadata:userid',
            'eventid' => 'privacy:metadata:eventid',
            'priority' => 'privacy:metadata:priority',
            'effortminutes' => 'privacy:metadata:effortminutes',
            'reminderat' => 'privacy:metadata:reminderat',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:block_workloadplanner_plan');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists('block_workloadplanner_plan', ['userid' => $userid])) {
            $list->add_user_context($userid);
        }
        return $list;
    }
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_user::instance($userid);
        if (!in_array($context->id, $contextlist->get_contextids())) {
            return;
        }
        $records = array_values($DB->get_records('block_workloadplanner_plan', ['userid' => $userid], '',
            'eventid, priority, effortminutes, reminderat, timemodified'));
        writer::with_context($context)->export_data([get_string('pluginname', 'block_workloadplanner')],
            (object)['priorities' => $records]);
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_USER) {
            $DB->delete_records('block_workloadplanner_plan', ['userid' => $context->instanceid]);
        }
    }
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $context = \context_user::instance($contextlist->get_user()->id);
        if (in_array($context->id, $contextlist->get_contextids())) {
            self::delete_data_for_all_users_in_context($context);
        }
    }
}
