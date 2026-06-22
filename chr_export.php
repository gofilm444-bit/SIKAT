<?php
require_once __DIR__ . '/bootstrap.php';
$__base = __DIR__;
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
if (!$__found) { http_response_code(500); exit('db.php tidak ditemukan.'); }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); exit('Koneksi DB tidak tersedia.'); }
$conn->set_charset('utf8mb4');

require_once __DIR__.'/review_export_helpers.php';
require_once __DIR__.'/chr_helpers.php';
require_once __DIR__.'/chr_export_common.php';
require_once __DIR__.'/chr_export_view.php';

if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$rid = (int)($_GET['rid'] ?? 0);
if ($rid < 1) { http_response_code(400); exit('Parameter rid wajib.'); }

$roleSlug = current_role();
if (is_admin_like($roleSlug)) {
  // allowed
} elseif (is_auditor($roleSlug)) {
  if (!review_is_assigned($conn, $rid, 'AUDITOR')) {
    http_response_code(403); exit('Akses ditolak.');
  }
} elseif (is_auditee($roleSlug)) {
  if (!review_is_assigned($conn, $rid, 'AUDITEE')) {
    http_response_code(403); exit('Akses ditolak.');
  }
} else {
  http_response_code(403); exit('Akses ditolak.');
}

$data = chr_export_load($conn, $rid);
if (!$data) { http_response_code(404); exit('Data reviu tidak ditemukan.'); }

session_release();

header('Content-Type: application/vnd.ms-word; charset=UTF-8');
header('Content-Disposition: attachment; filename="Catatan_Hasil_Reviu.doc"');
echo "\xEF\xBB\xBF";

echo chr_export_render($data, [
  'mode' => 'word',
  'title' => 'Catatan Hasil Reviu',
]);




