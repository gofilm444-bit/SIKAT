<?php
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

$__legacy = [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'ski_db',
];

$DB_HOST = env('DB_HOST', $__legacy['DB_HOST']);
$DB_USER = env('DB_USER', $__legacy['DB_USER']);
$DB_PASS = env('DB_PASS', $__legacy['DB_PASS']);
$DB_NAME = env('DB_NAME', $__legacy['DB_NAME']);

// Disarankan pakai user non-root di production.

return [
    'DB_HOST' => $DB_HOST,
    'DB_USER' => $DB_USER,
    'DB_PASS' => $DB_PASS,
    'DB_NAME' => $DB_NAME,
];
