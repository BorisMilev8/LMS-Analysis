<?php
require_once('/opt/moodle-render/environment.php');
$env = moodle_render_environment();
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost = $env['MOODLE_DB_HOST'];
$CFG->dbname = $env['MOODLE_DB_NAME'];
$CFG->dbuser = $env['MOODLE_DB_USER'];
$CFG->dbpass = $env['MOODLE_DB_PASSWORD'];
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbpersist' => false, 'dbport' => $env['port'], 'dbsocket' => ''];
$CFG->wwwroot = $env['wwwroot'];
$CFG->dataroot = '/var/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
$CFG->sslproxy = $env['sslproxy'];
$CFG->debug = 0;
$CFG->debugdisplay = false;
$CFG->perfdebug = 0;
$CFG->cachejs = true;
$CFG->themedesignermode = false;
$CFG->disableupdateautodeploy = true;
// Use the application cron only; never expose a web cron endpoint.
$CFG->cronclionly = true;
if (getenv('MOODLE_PHPUNIT') === 'true') {
    $CFG->phpunit_prefix = 'phpu_';
    $CFG->phpunit_dataroot = '/var/moodledata/phpunit';
}
require_once(__DIR__ . '/lib/setup.php');
