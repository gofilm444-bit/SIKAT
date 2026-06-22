<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/_deny.php';
$env = strtolower((string)env('APP_ENV', ''));
$token = (string)($_GET['token'] ?? '');
$expected = (string)env('APP_RESET_TOKEN', '');
if ($env !== 'local' || $expected === '' || !hash_equals($expected, $token)) {
    forbidden_response('403 Forbidden — Akses ditolak.');
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_hardening.php';

$allowedRoles = ['super_admin','admin','kepala_ski','direktur','auditor_ka'];
$user = $_SESSION['user'] ?? null;
$role = is_array($user) ? ($user['peran'] ?? '') : '';

if (PHP_SAPI !== 'cli' && (!$user || !in_array($role, $allowedRoles, true))) {
    forbidden_response('403 Forbidden — Akses ditolak.');
}

$headers = headers_list();

$uploadDirs = [
    'upload' => realpath(__DIR__ . '/../upload'),
    'uploads' => realpath(__DIR__ . '/../uploads'),
];

$rootHtaccess = is_file(__DIR__ . '/../.htaccess');

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Security Self-Check ===\n";

if ($headers) {
    echo "Headers aktif:\n";
    foreach ($headers as $h) {
        echo "- {$h}\n";
    }
} else {
    echo "Headers aktif: (tidak ada)\n";
}

echo "\nRoot .htaccess: " . ($rootHtaccess ? 'OK' : 'MISSING') . "\n";

echo "\nUpload .htaccess:\n";
foreach ($uploadDirs as $name => $path) {
    if ($path && is_dir($path)) {
        $ht = $path . DIRECTORY_SEPARATOR . '.htaccess';
        echo "- {$name}/.htaccess: " . (is_file($ht) ? 'OK' : 'MISSING') . "\n";
    } else {
        echo "- {$name}: not found\n";
    }
}

echo "\nManual tests yang disarankan:\n";
echo "- Akses /db/ harus 403/404\n";
echo "- Akses /db/ski_db.sql harus 403/404\n";
echo "- Akses /uploads/ dan /upload/ tidak boleh listing\n";
echo "- Akses /.env harus 403/404\n";
echo "- Upload file .php ke uploads tidak boleh dieksekusi\n";

