<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/_deny.php';

$env = strtolower((string)env('APP_ENV', ''));
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $token = (string)($_GET['token'] ?? '');
    $expected = (string)env('APP_RESET_TOKEN', '');
    if ($env !== 'local' || $expected === '' || !hash_equals($expected, $token)) {
        forbidden_response('403 Forbidden — Akses ditolak.');
    }
}

// DB loader (same fallback as export endpoints)
$__base = dirname(__DIR__);
$__candidates = [
    $__base.'/db.php',
    $__base.'/ski_new/db.php',
    $__base.'/db/db.php',
    dirname($__base).'/db.php',
    $__base.'/includes/db.php'
];
$__found = false;
foreach ($__candidates as $__p) {
    if (is_file($__p)) { require_once $__p; $__found = true; break; }
}
if (!$__found || !isset($conn) || !($conn instanceof mysqli)) {
    if ($isCli) {
        fwrite(STDERR, "Koneksi DB tidak tersedia.\n");
        exit(1);
    }
    http_response_code(500);
    echo 'Koneksi DB tidak tersedia.';
    exit;
}
$conn->set_charset('utf8mb4');

header('Content-Type: text/plain; charset=UTF-8');

$sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN password_hash IS NOT NULL AND password_hash <> '' THEN 1 ELSE 0 END) AS hashed,
            SUM(CASE WHEN password IS NOT NULL AND password <> '' THEN 1 ELSE 0 END) AS legacy_plain
        FROM pengguna";
$row = null;
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    $res->free();
}

if (!$row) {
    echo "Gagal mengambil data.\n";
    exit;
}

$total = (int)($row['total'] ?? 0);
$hashed = (int)($row['hashed'] ?? 0);
$legacy = (int)($row['legacy_plain'] ?? 0);
$missing = max(0, $total - $hashed);

echo "Password Hash Coverage Report\n";
echo "=============================\n";
echo "Total pengguna: {$total}\n";
echo "Sudah punya password_hash: {$hashed}\n";
echo "Masih ada password plaintext terisi: {$legacy}\n";
echo "Belum punya password_hash: {$missing}\n\n";

if ($missing > 0) {
    $limit = 50;
    $list = [];
    $stmt = $conn->prepare("SELECT id, nama, username, peran, status FROM pengguna WHERE (password_hash IS NULL OR password_hash='') ORDER BY id ASC LIMIT {$limit}");
    if ($stmt && $stmt->execute()) {
        $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    echo "Contoh pengguna tanpa password_hash (maks {$limit}):\n";
    foreach ($list as $u) {
        echo "- ID {$u['id']} | {$u['username']} | {$u['nama']} | {$u['peran']} | {$u['status']}\n";
    }
    if (empty($list)) {
        echo "- (tidak ada data)\n";
    }
}
