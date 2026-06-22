<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/_deny.php';

$env = strtolower((string)env('APP_ENV', ''));
$token = (string)($_GET['token'] ?? '');
$expected = (string)env('APP_RESET_TOKEN', '');
if ($env !== 'local' || $expected === '' || !hash_equals($expected, $token)) {
    forbidden_response('403 Forbidden — Akses ditolak.');
}

require_once __DIR__ . '/../db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    deny('DB connection not available', 500);
}

function table_exists(mysqli $conn, string $table): bool {
    $esc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$esc'");
    return $res && $res->num_rows > 0;
}

$tables = ['pengguna', 'users', 'admin'];
$table = '';
foreach ($tables as $t) {
    if (table_exists($conn, $t)) { $table = $t; break; }
}
if ($table === '') {
    deny('No user table found', 500);
}

$cols = [];
$types = [];
$res = $conn->query("SHOW COLUMNS FROM `{$table}`");
while ($res && ($row = $res->fetch_assoc())) {
    $cols[$row['Field']] = true;
    $types[$row['Field']] = strtolower((string)($row['Type'] ?? ''));
}

$colUsername = '';
foreach (['username','user','email','nip','userid'] as $cand) {
    if (isset($cols[$cand])) { $colUsername = $cand; break; }
}
if ($colUsername === '') {
    deny('No username column found', 500);
}

$colPassHash = isset($cols['password_hash']) ? 'password_hash' : '';
$colPass = isset($cols['password']) ? 'password' : '';
$colRole = '';
foreach (['peran','role','level','tipe','user_role'] as $cand) {
    if (isset($cols[$cand])) { $colRole = $cand; break; }
}
$colStatus = '';
foreach (['status','aktif','is_active','active'] as $cand) {
    if (isset($cols[$cand])) { $colStatus = $cand; break; }
}
$colAksesDashboard = isset($cols['akses_dashboard']) ? 'akses_dashboard' : '';
$colAksesPelaporan = isset($cols['akses_pelaporan']) ? 'akses_pelaporan' : '';
$colAksesReview = isset($cols['akses_review']) ? 'akses_review' : '';

$username = 'superadmin';
$plainPassword = 'SuperAdmin!12345';
$roleValue = 'super_admin';
$newHash = password_hash($plainPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id FROM `{$table}` WHERE `{$colUsername}`=? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$setParts = [];
$params = [];
$bindTypes = '';

if ($colPassHash !== '') {
    $setParts[] = "`{$colPassHash}`=?";
    $params[] = $newHash;
    $bindTypes .= 's';
}
if ($colPass !== '') {
    $setParts[] = "`{$colPass}`=?";
    $params[] = ($colPassHash !== '') ? '' : $plainPassword;
    $bindTypes .= 's';
}
if ($colRole !== '') {
    $setParts[] = "`{$colRole}`=?";
    $params[] = $roleValue;
    $bindTypes .= 's';
}
if ($colStatus !== '') {
    $type = $types[$colStatus] ?? '';
    $statusValue = (preg_match('/int|tinyint|bit|bool|decimal|float|double/', $type)) ? 1 : 'Aktif';
    $setParts[] = "`{$colStatus}`=?";
    $params[] = $statusValue;
    $bindTypes .= is_int($statusValue) ? 'i' : 's';
}
if ($colAksesDashboard !== '') {
    $setParts[] = "`{$colAksesDashboard}`=?";
    $params[] = 1;
    $bindTypes .= 'i';
}
if ($colAksesPelaporan !== '') {
    $setParts[] = "`{$colAksesPelaporan}`=?";
    $params[] = 1;
    $bindTypes .= 'i';
}
if ($colAksesReview !== '') {
    $setParts[] = "`{$colAksesReview}`=?";
    $params[] = 1;
    $bindTypes .= 'i';
}

if ($row && isset($row['id'])) {
    $uid = (int)$row['id'];
    $params[] = $uid;
    $bindTypes .= 'i';
    $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE id=?";
    $stmtU = $conn->prepare($sql);
    $stmtU->bind_param($bindTypes, ...$params);
    $stmtU->execute();
    header('Content-Type: text/plain; charset=UTF-8');
    echo "OK updated ({$table})";
    exit;
}

$colsInsert = ["`{$colUsername}`"]; $vals = ['?']; $params = [$username]; $bindTypes = 's';
if ($colPassHash !== '') { $colsInsert[] = "`{$colPassHash}`"; $vals[] = '?'; $params[] = $newHash; $bindTypes .= 's'; }
if ($colPass !== '') { $colsInsert[] = "`{$colPass}`"; $vals[] = '?'; $params[] = ($colPassHash !== '') ? '' : $plainPassword; $bindTypes .= 's'; }
if ($colRole !== '') { $colsInsert[] = "`{$colRole}`"; $vals[] = '?'; $params[] = $roleValue; $bindTypes .= 's'; }
if ($colStatus !== '') {
    $type = $types[$colStatus] ?? '';
    $statusValue = (preg_match('/int|tinyint|bit|bool|decimal|float|double/', $type)) ? 1 : 'Aktif';
    $colsInsert[] = "`{$colStatus}`"; $vals[] = '?'; $params[] = $statusValue; $bindTypes .= is_int($statusValue) ? 'i' : 's';
}
if ($colAksesDashboard !== '') { $colsInsert[] = "`{$colAksesDashboard}`"; $vals[] = '?'; $params[] = 1; $bindTypes .= 'i'; }
if ($colAksesPelaporan !== '') { $colsInsert[] = "`{$colAksesPelaporan}`"; $vals[] = '?'; $params[] = 1; $bindTypes .= 'i'; }
if ($colAksesReview !== '') { $colsInsert[] = "`{$colAksesReview}`"; $vals[] = '?'; $params[] = 1; $bindTypes .= 'i'; }

$sql = "INSERT INTO `{$table}` (" . implode(', ', $colsInsert) . ") VALUES (" . implode(', ', $vals) . ")";
$stmtI = $conn->prepare($sql);
$stmtI->bind_param($bindTypes, ...$params);
$stmtI->execute();

header('Content-Type: text/plain; charset=UTF-8');
echo "OK created ({$table})";
