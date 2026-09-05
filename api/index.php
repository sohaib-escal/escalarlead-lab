<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure all needed Laravel storage directories exist in /tmp for serverless environment
$storageDirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/testing',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/storage/bootstrap/cache',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Redirect all Laravel writable cache paths to /tmp
putenv('APP_STORAGE=/tmp/storage');
putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
putenv('APP_SERVICES_CACHE=/tmp/storage/bootstrap/cache/services.php');
putenv('APP_PACKAGES_CACHE=/tmp/storage/bootstrap/cache/packages.php');
putenv('APP_CONFIG_CACHE=/tmp/storage/bootstrap/cache/config.php');
putenv('APP_ROUTES_CACHE=/tmp/storage/bootstrap/cache/routes-v7.php');
putenv('APP_EVENTS_CACHE=/tmp/storage/bootstrap/cache/events.php');
putenv('LOG_CHANNEL=stderr');

$_ENV['APP_STORAGE'] = '/tmp/storage';
$_ENV['VIEW_COMPILED_PATH'] = '/tmp/storage/framework/views';
$_ENV['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';
$_ENV['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
$_ENV['APP_CONFIG_CACHE'] = '/tmp/storage/bootstrap/cache/config.php';
$_ENV['APP_ROUTES_CACHE'] = '/tmp/storage/bootstrap/cache/routes-v7.php';
$_ENV['APP_EVENTS_CACHE'] = '/tmp/storage/bootstrap/cache/events.php';
$_ENV['LOG_CHANNEL'] = 'stderr';

// Ensure driver defaults if not set or empty
if (empty($_ENV['SESSION_DRIVER']) || $_ENV['SESSION_DRIVER'] === '') {
    putenv('SESSION_DRIVER=database');
    $_ENV['SESSION_DRIVER'] = 'database';
}
if (empty($_ENV['CACHE_STORE']) || $_ENV['CACHE_STORE'] === '') {
    putenv('CACHE_STORE=database');
    $_ENV['CACHE_STORE'] = 'database';
}
if (empty($_ENV['QUEUE_CONNECTION']) || $_ENV['QUEUE_CONNECTION'] === '') {
    putenv('QUEUE_CONNECTION=database');
    $_ENV['QUEUE_CONNECTION'] = 'database';
}
if (empty($_ENV['DB_CONNECTION']) || in_array($_ENV['DB_CONNECTION'], ['psql', 'postgres', 'postgresql', ''])) {
    putenv('DB_CONNECTION=pgsql');
    $_ENV['DB_CONNECTION'] = 'pgsql';
}
if (empty($_ENV['APP_MAINTENANCE_DRIVER']) || $_ENV['APP_MAINTENANCE_DRIVER'] === '') {
    putenv('APP_MAINTENANCE_DRIVER=file');
    $_ENV['APP_MAINTENANCE_DRIVER'] = 'file';
}

try {
    // Forward the request to Laravel's public entry point
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Application Error</title><style>body{font-family:sans-serif;padding:2rem;background:#f8fafc;color:#1e293b}pre{background:#0f172a;color:#f8fafc;padding:1rem;border-radius:8px;overflow:auto}</style></head><body>';
    echo '<h1>Application Error</h1>';
    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong> in ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</body></html>';
}
