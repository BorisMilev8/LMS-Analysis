<?php
defined('MOODLE_INTERNAL') || die();
/** Preserve existing priorities while adding optional effort estimates. */
function xmldb_block_workloadplanner_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2026100300) {
        $table = new xmldb_table('block_workloadplanner_plan');
        $field = new xmldb_field('effortminutes', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'priority');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026100300, 'workloadplanner');
    }
    if ($oldversion < 2026100500) {
        $table = new xmldb_table('block_workloadplanner_plan');
        $field = new xmldb_field('reminderat', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'effortminutes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026100500, 'workloadplanner');
    }
    return true;
}
