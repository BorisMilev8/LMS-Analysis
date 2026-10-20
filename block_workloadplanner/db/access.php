<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'block/workloadplanner:analyze' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
    'block/workloadplanner:myaddinstance' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['user' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
    'block/workloadplanner:addinstance' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => ['manager' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],
];
