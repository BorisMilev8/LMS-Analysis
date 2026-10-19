<?php
header('Content-Type: text/plain');
header('Cache-Control: no-store');
try {
    require_once('/opt/moodle-render/environment.php');
    $db = moodle_render_database(moodle_render_environment());
    $version = $db->query("SELECT value FROM mdl_config_plugins
        WHERE plugin='block_workloadplanner' AND name='version'")->fetchColumn();
    if (!$version) {
        throw new RuntimeException('Plugin unavailable');
    }
    echo "ready\n";
} catch (Throwable $e) {
    http_response_code(503);
    echo "not ready\n";
}
