<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../config/env.php';

$checks = [];
$env = strtolower((string)(getenv('APP_ENV') ?: ''));
$checks[] = ['APP_ENV=production', $env === 'production', $env ?: '(empty)'];

$debugAuth = (string)(getenv('APP_DEBUG_AUTH') ?: '');
$checks[] = ['APP_DEBUG_AUTH=0', $debugAuth === '' || $debugAuth === '0', $debugAuth ?: '(empty)'];

$resetToken = (string)(getenv('APP_RESET_TOKEN') ?: '');
$checks[] = ['APP_RESET_TOKEN set', $resetToken !== '' && $resetToken !== 'local_reset_123' && $resetToken !== 'CHANGE_ME', $resetToken !== '' ? 'set' : '(empty)'];

$dbUser = (string)(getenv('DB_USER') ?: '');
$dbPass = (string)(getenv('DB_PASS') ?: '');
$checks[] = ['DB_USER non-root', $dbUser !== '' && strtolower($dbUser) !== 'root', $dbUser ?: '(empty)'];
$checks[] = ['DB_PASS non-empty', $dbPass !== '' && $dbPass !== 'CHANGE_ME', $dbPass !== '' ? 'set' : '(empty)'];

$allOk = true;
foreach ($checks as [$label, $ok, $value]) {
    $status = $ok ? 'OK' : 'WARN';
    if (!$ok) { $allOk = false; }
    echo sprintf("[%s] %s (%s)\n", $status, $label, $value);
}

if (!$allOk) {
    echo "\nPerbaiki item WARN sebelum deploy.\n";
    exit(2);
}

echo "\nSiap deploy (environment).\n";
exit(0);
?>
