<?php
require_once(__DIR__ . '/../deploy/render/environment.php');
foreach (['MOODLE_DB_HOST=database', 'MOODLE_DB_NAME=moodle', 'MOODLE_DB_USER=moodle',
        'MOODLE_DB_PASSWORD=not-a-real-secret', 'MOODLE_WWWROOT=https://example.onrender.com'] as $value) {
    putenv($value);
}
putenv('RENDER_EXTERNAL_URL');
putenv('MOODLE_ALLOW_HTTP');
putenv('MOODLE_DB_PORT');
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function rejects(string $setting, string $value): void {
    $old = getenv($setting);
    putenv($setting . '=' . $value);
    $rejected = false;
    try { moodle_render_environment(); } catch (RuntimeException $e) { $rejected = true; }
    $old === false ? putenv($setting) : putenv($setting . '=' . $old);
    check($rejected, 'Expected rejection: ' . $setting);
}
$env = moodle_render_environment();
check($env['port'] === 5432 && $env['sslproxy'], 'HTTPS and default database port');
rejects('MOODLE_DB_PASSWORD', '');
rejects('MOODLE_DB_HOST', 'host;dbname=other');
rejects('MOODLE_DB_PORT', '5432bad');
rejects('MOODLE_DB_PORT', '70000');
rejects('MOODLE_WWWROOT', 'http://example.com');
rejects('MOODLE_WWWROOT', 'https://user:pass@example.com');
rejects('MOODLE_WWWROOT', 'https://example.com/subpath');
putenv('MOODLE_ALLOW_HTTP=true');
putenv('MOODLE_WWWROOT=http://localhost:8080');
check(!moodle_render_environment()['sslproxy'], 'Explicit local HTTP override');
putenv('MOODLE_ALLOW_HTTP');
putenv('MOODLE_WWWROOT');
putenv('RENDER_EXTERNAL_URL=https://render-fallback.onrender.com');
check(moodle_render_environment()['wwwroot'] === 'https://render-fallback.onrender.com', 'Render URL fallback');
echo "10 deployment configuration checks passed.\n";
