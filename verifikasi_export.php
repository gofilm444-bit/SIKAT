<?php
require_once __DIR__ . '/bootstrap.php';
$baseDir = __DIR__;
$dbCandidates = [
  $baseDir.'/db.php',
  $baseDir.'/ski_new/db.php',
  $baseDir.'/db/db.php',
  dirname($baseDir).'/db.php',
  $baseDir.'/includes/db.php'
];
$dbLoaded = false;
foreach ($dbCandidates as $candidatePath) {
  if (is_file($candidatePath)) {
    require_once $candidatePath;
    $dbLoaded = true;
    break;
  }
}
if (!$dbLoaded) { http_response_code(500); exit('db.php tidak ditemukan.'); }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); exit('Koneksi DB tidak tersedia.'); }
$conn->set_charset('utf8mb4');

require_once __DIR__.'/review_export_helpers.php';

if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$rid = (int)($_GET['rid'] ?? 0);
if ($rid < 1) { http_response_code(400); exit('Parameter rid wajib.'); }

$roleSlug = current_role();
$allowed = is_admin_like($roleSlug) || is_auditor($roleSlug) || is_director_like($roleSlug);
if (!$allowed) {
  http_response_code(403); exit('Akses ditolak.');
}
if (is_auditor($roleSlug) && !is_admin_like($roleSlug) && !review_is_assigned($conn, $rid, 'AUDITOR')) {
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

$verRows = [];
if ($stmt = $conn->prepare("SELECT tahap, verifikator, status, catatan, tgl_verifikasi FROM reviu_verifikasi WHERE reviu_id=? ORDER BY created_at ASC")) {
  $stmt->bind_param("i", $rid);
  if ($stmt->execute()) {
    $verRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
}

$laporanRow = null;
if ($stmt = $conn->prepare("SELECT ringkasan, rekomendasi, tindak_lanjut, ttd_kepala_nama, ttd_kepala_tanggal FROM reviu_laporan WHERE reviu_id=? LIMIT 1")) {
  $stmt->bind_param("i", $rid);
  if ($stmt->execute()) {
    $laporanRow = $stmt->get_result()->fetch_assoc();
  }
}

$filenameBase = $revInfo['kode'] ?? ('REVIU-'.$rid);
$filenameBase = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $filenameBase);
$filename = 'Verifikasi_'.$filenameBase.'_'.date('Ymd_His').'.csv';

session_release();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Kode Reviu', $revInfo['kode'] ?? '']);
fputcsv($out, ['Jenis', $revInfo['jenis_nama'] ?? '']);
fputcsv($out, ['Unit', $revInfo['unit_nama'] ?? '']);
fputcsv($out, []);
fputcsv($out, ['Tahap','Verifikator','Status','Tanggal','Catatan']);
if (empty($verRows)) {
  fputcsv($out, ['-', 'Belum ada verifikasi', '', '', '']);
} else {
  foreach ($verRows as $row) {
    fputcsv($out, [
      $row['tahap'] ?? '',
      preg_replace('/\s+/', ' ', $row['verifikator'] ?? ''),
      $row['status'] ?? '',
      $row['tgl_verifikasi'] ?? '',
      preg_replace('/\s+/', ' ', $row['catatan'] ?? ''),
    ]);
  }
}
if ($laporanRow) {
  fputcsv($out, []);
  fputcsv($out, ['Ringkasan', preg_replace('/\s+/', ' ', $laporanRow['ringkasan'] ?? '')]);
  fputcsv($out, ['Rekomendasi', preg_replace('/\s+/', ' ', $laporanRow['rekomendasi'] ?? '')]);
  fputcsv($out, ['Tindak Lanjut', preg_replace('/\s+/', ' ', $laporanRow['tindak_lanjut'] ?? '')]);
  $ttdInfo = trim(($laporanRow['ttd_kepala_nama'] ?? '').' '.($laporanRow['ttd_kepala_tanggal'] ?? ''));
  fputcsv($out, ['Ka SKI (TTD)', $ttdInfo]);
}
fclose($out);
exit;



