<?php
require_once __DIR__ . '/bootstrap.php';
$__base = __DIR__;
$__candidates = [
  $__base.'/db.php',
  $__base.'/ski_new/db.php',
  $__base.'/db/db.php',
  dirname($__base).'/db.php',
  $__base__.'/includes/db.php'
];
$__found = false;
foreach ($__candidates as $__p) {
  if (is_file($__p)) { require_once $__p; $__found = true; break; }
}
if (!$__found) { http_response_code(500); exit('db.php tidak ditemukan.'); }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); exit('Koneksi DB tidak tersedia.'); }
$conn->set_charset('utf8mb4');

require_once __DIR__.'/review_export_helpers.php';

if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$rid = (int)($_GET['rid'] ?? 0);
if ($rid < 1) { http_response_code(400); exit('Parameter rid wajib.'); }

if (!is_admin_like() && !review_is_assigned($conn, $rid, null)) {
  http_response_code(403); exit('Akses ditolak.');
}

$revInfo = null;
if ($stmt = $conn->prepare("SELECT r.kode, u.nama unit_nama, j.nama jenis_nama FROM reviu r JOIN unit_kerja u ON u.id=r.unit_id JOIN jenis_reviu j ON j.id=r.jenis_id WHERE r.id=?")) {
  $stmt->bind_param("i", $rid);
  if ($stmt->execute()) {
    $revInfo = $stmt->get_result()->fetch_assoc();
  }
}
if (!$revInfo) { http_response_code(404); exit('Data reviu tidak ditemukan.'); }

$docs = [];
if ($stmt = $conn->prepare("SELECT kategori, judul, file_path, uploaded_by, created_at FROM reviu_dokumen WHERE reviu_id=? ORDER BY created_at ASC")) {
  $stmt->bind_param("i", $rid);
  if ($stmt->execute()) {
    $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
}

$filenameBase = $revInfo['kode'] ?? ('REVIU-'.$rid);
$filenameBase = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $filenameBase);
$filename = 'Dokumen_'.$filenameBase.'_'.date('Ymd_His').'.csv';

session_release();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Kode Reviu', $revInfo['kode'] ?? '']);
fputcsv($out, ['Jenis', $revInfo['jenis_nama'] ?? '']);
fputcsv($out, ['Unit', $revInfo['unit_nama'] ?? '']);
fputcsv($out, []);
fputcsv($out, ['No','Kategori','Judul','Path','Uploaded By','Created At']);
$idx = 1;
foreach ($docs as $row) {
  fputcsv($out, [
    $idx++,
    $row['kategori'] ?? '',
    preg_replace('/\s+/', ' ', $row['judul'] ?? ''),
    $row['file_path'] ?? '',
    (string)($row['uploaded_by'] ?? ''),
    $row['created_at'] ?? '',
  ]);
}
if ($idx === 1) {
  fputcsv($out, ['-', 'Belum ada dokumen', '', '', '', '']);
}
fclose($out);
exit;



