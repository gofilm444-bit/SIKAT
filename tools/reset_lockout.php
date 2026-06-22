<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/_deny.php';

$env = strtolower((string)env('APP_ENV', ''));
$token = (string)($_GET['token'] ?? '');
$expected = (string)env('APP_RESET_TOKEN', '');
if ($env !== 'local' || $expected === '' || !hash_equals($expected, $token)) {
    forbidden_response('403 Forbidden — Akses ditolak.');
}

$dir = __DIR__ . '/../storage/login_rate';
$count = 0;
if (is_dir($dir)) {
    foreach (glob($dir . '/*.json') as $file) {
        if (@unlink($file)) { $count++; }
    }
}

header('Content-Type: text/plain; charset=UTF-8');
echo "OK cleared {$count} lockout files";
