<?php
/** Validate environment before using it in Moodle or a database connection. */
function moodle_render_environment(): array {
    $required = ['MOODLE_DB_HOST', 'MOODLE_DB_NAME', 'MOODLE_DB_USER', 'MOODLE_DB_PASSWORD'];
    $result = [];
    foreach ($required as $key) {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new RuntimeException('Missing environment setting: ' . $key);
        }
        $result[$key] = $value;
    }
    // These values are used in a PostgreSQL DSN. Reject delimiters/control characters.
    foreach (['MOODLE_DB_HOST', 'MOODLE_DB_NAME', 'MOODLE_DB_USER'] as $key) {
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $result[$key])) {
            throw new RuntimeException('Invalid environment setting: ' . $key);
        }
    }
    $port = getenv('MOODLE_DB_PORT') ?: '5432';
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        throw new RuntimeException('Invalid database port');
    }
    $result['port'] = (int)$port;
    $url = rtrim(getenv('MOODLE_WWWROOT') ?: (getenv('RENDER_EXTERNAL_URL') ?: ''), '/');
    $parts = parse_url($url);
    $http = getenv('MOODLE_ALLOW_HTTP') === 'true';
    if (!$parts || empty($parts['host']) || !filter_var($url, FILTER_VALIDATE_URL)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !empty($parts['path']) || !in_array($parts['scheme'] ?? '', $http ? ['http', 'https'] : ['https'], true)) {
        throw new RuntimeException('Provide an HTTPS root URL in MOODLE_WWWROOT or RENDER_EXTERNAL_URL');
    }
    $result['wwwroot'] = $url;
    $result['sslproxy'] = ($parts['scheme'] === 'https');
    return $result;
}
function moodle_render_database(array $env): PDO {
    return new PDO('pgsql:host=' . $env['MOODLE_DB_HOST'] . ';port=' . $env['port'] .
        ';dbname=' . $env['MOODLE_DB_NAME'] . ';connect_timeout=10',
        $env['MOODLE_DB_USER'], $env['MOODLE_DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
