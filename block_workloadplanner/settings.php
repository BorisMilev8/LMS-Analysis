<?php
defined('MOODLE_INTERNAL') || die();
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('block_workloadplanner/enableanalysis',
        get_string('enableanalysis', 'block_workloadplanner'),
        get_string('enableanalysis_desc', 'block_workloadplanner'), 0));
    $settings->add(new admin_setting_configtext('block_workloadplanner/weeklythreshold',
        get_string('weeklythreshold', 'block_workloadplanner'),
        get_string('weeklythreshold_desc', 'block_workloadplanner'), 600, PARAM_INT));
}
