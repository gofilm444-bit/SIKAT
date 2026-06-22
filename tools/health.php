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
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin','superadmin','super_admin']);

$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

function hc_env(string $key, $default = null) {
    $val = getenv($key);
    if ($val === false || $val === null || $val === '') {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($val === null || $val === '') ? $default : $val;
}

$checks = [];
$add = function (string $name, string $status, string $detail) use (&$checks) {
    $checks[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
};

// DB connectivity
$dbOk = false;
$dbDetail = 'db.php tidak ditemukan';
$__candidates = [
    $root.'/db.php',
    $root.'/ski_new/db.php',
    $root.'/db/db.php',
    dirname($root).'/db.php',
    $root.'/includes/db.php'
];
$__found = false;
foreach ($__candidates as $__p) {
    if (is_file($__p)) { require_once $__p; $__found = true; break; }
}
if ($__found && isset($conn) && ($conn instanceof mysqli)) {
    $conn->set_charset('utf8mb4');
    $res = $conn->query('SELECT 1 AS ok');
    if ($res) {
        $dbOk = true;
        $dbDetail = 'Koneksi OK';
    } else {
        $dbDetail = 'Query test gagal';
    }
} else {
    $dbDetail = 'Koneksi DB tidak tersedia';
}
$add('DB Connectivity', $dbOk ? 'OK' : 'FAIL', $dbDetail);

// PHP info
$add('PHP Version', 'OK', PHP_VERSION);
$add('display_errors', ini_get('display_errors') ? 'WARN' : 'OK', ini_get('display_errors') ? 'ON' : 'OFF');
$add('ext: mysqli', extension_loaded('mysqli') ? 'OK' : 'FAIL', extension_loaded('mysqli') ? 'loaded' : 'missing');
$add('ext: pdo_mysql', extension_loaded('pdo_mysql') ? 'OK' : 'WARN', extension_loaded('pdo_mysql') ? 'loaded' : 'missing');

// Storage perms
$storageDir = $root . '/storage';
$loginRateDir = $storageDir . '/login_rate';
if (!is_dir($storageDir)) {
    $add('storage dir', 'WARN', 'Folder belum ada (akan dibuat saat runtime)');
} else {
    $add('storage dir', is_writable($storageDir) ? 'OK' : 'FAIL', is_writable($storageDir) ? 'writable' : 'not writable');
}
if (!is_dir($loginRateDir)) {
    $add('storage/login_rate', 'WARN', 'Folder belum ada (akan dibuat saat runtime)');
} else {
    $add('storage/login_rate', is_writable($loginRateDir) ? 'OK' : 'FAIL', is_writable($loginRateDir) ? 'writable' : 'not writable');
}

// Env config (safe)
$appEnv = hc_env('APP_ENV', 'production (default)');
$sessionIdle = hc_env('APP_SESSION_IDLE', '1800 (default)');
$appTz = hc_env('APP_TIMEZONE', 'not set');
$add('APP_ENV', 'OK', (string)$appEnv);
$add('APP_SESSION_IDLE', 'OK', (string)$sessionIdle);
$add('APP_TIMEZONE', 'OK', (string)$appTz);
$add('DB_PASS set', hc_env('DB_PASS') ? 'OK' : 'WARN', hc_env('DB_PASS') ? 'set' : 'not set');
$add('APP_SMTP_PASS set', hc_env('APP_SMTP_PASS') ? 'OK' : 'WARN', hc_env('APP_SMTP_PASS') ? 'set' : 'not set');

// Path/debug
$add('App root', 'OK', (string)$root);
$add('DOCUMENT_ROOT', 'OK', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')); 
$add('SCRIPT_NAME', 'OK', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
if ($format === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'timestamp' => date('c'),
        'checks' => $checks
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Health Check - SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:Arial, sans-serif; background:#f7f7f7; margin:0; padding:20px; color:#222;}
    h1{font-size:20px;margin-bottom:10px;}
    table{width:100%; border-collapse:collapse; background:#fff;}
    th,td{border:1px solid #ddd; padding:8px; text-align:left; font-size:13px;}
    th{background:#f0f0f0;}
    .status-ok{color:#0a7a2f; font-weight:600;}
    .status-warn{color:#c77800; font-weight:600;}
    .status-fail{color:#b00020; font-weight:600;}
    .meta{font-size:12px;color:#666;margin-bottom:12px;}
  </style>
</head>
<body>
  <h1>Application Health Check</h1>
  <div class="meta">Timestamp: <?= h(date('Y-m-d H:i:s')) ?> | Format: HTML (use <code>?format=json</code>)</div>
  <table>
    <thead>
      <tr>
        <th>Check</th>
        <th>Status</th>
        <th>Detail</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($checks as $c):
        $cls = $c['status']==='OK' ? 'status-ok' : ($c['status']==='WARN' ? 'status-warn' : 'status-fail');
      ?>
        <tr>
          <td><?= h($c['name']) ?></td>
          <td class="<?= h($cls) ?>"><?= h($c['status']) ?></td>
          <td><?= h($c['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
