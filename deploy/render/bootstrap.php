<?php
require_once(__DIR__ . '/environment.php');
try {
    $env = moodle_render_environment();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
$database = null;
for ($attempt = 0; $attempt < 30; $attempt++) {
    try {
        $database = moodle_render_database($env);
        break;
    } catch (Throwable $e) {
        // Never print a DSN or credentials to deployment logs.
        fwrite(STDERR, "Waiting for database connection...\n");
        sleep(2);
    }
}
if (!$database) {
    fwrite(STDERR, "Database unavailable; refusing to start web service.\n");
    exit(1);
}
$tables = (int)$database->query("SELECT count(*) FROM information_schema.tables
    WHERE table_schema='public' AND table_type='BASE TABLE'")->fetchColumn();
function run_moodle_command(array $arguments): void {
    $command = array_merge([PHP_BINARY], $arguments);
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, '/var/www/html');
    if (!is_resource($process) || proc_close($process) !== 0) {
        fwrite(STDERR, "Moodle initialization failed; web service remains closed.\n");
        exit(1);
    }
}
if ($tables === 0) {
    $password = getenv('MOODLE_ADMIN_PASSWORD') ?: '';
    $email = getenv('MOODLE_ADMIN_EMAIL') ?: '';
    if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Set a valid admin email and a password of 12+ characters with uppercase, lowercase, number and symbol.\n");
        exit(1);
    }
    run_moodle_command(['admin/cli/install_database.php', '--agree-license',
        '--fullname=' . (getenv('MOODLE_SITE_NAME') ?: 'LMS Analysis Test Site'), '--shortname=LMS-Test',
        '--adminuser=' . (getenv('MOODLE_ADMIN_USER') ?: 'admin'), '--adminemail=' . $email,
        '--adminpass=' . $password]);
} else {
    $exists = $database->query("SELECT to_regclass('public.mdl_config')")->fetchColumn();
    $version = $exists ? $database->query("SELECT value FROM mdl_config WHERE name='version'")->fetchColumn() : false;
    if (!$version) {
        fwrite(STDERR, "Database is not an installed Moodle site. Restore a backup or inspect a failed initial install; no data was deleted.\n");
        exit(1);
    }
    // Startup runs with the mounted disk; Render pre-deploy jobs cannot access it.
    // Take database + moodledata backups before manually deploying code changes.
    run_moodle_command(['admin/cli/upgrade.php', '--non-interactive']);
}
echo "Moodle initialization complete.\n";
