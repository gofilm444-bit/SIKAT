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
require_once __DIR__.'/chr_sop_export_common.php';
require_once __DIR__.'/chr_sop_export_render.php';

if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$rid = (int)($_GET['rid'] ?? 0);
$mode = strtolower((string)($_GET['mode'] ?? 'preview'));
$format = strtolower((string)($_GET['format'] ?? 'view'));
if ($rid < 1) { http_response_code(400); exit('Parameter rid wajib.'); }
if (!in_array($mode, ['preview', 'final'], true)) { http_response_code(400); exit('Mode ekspor tidak valid.'); }
if (!in_array($format, ['view', 'docx'], true)) { http_response_code(400); exit('Format ekspor tidak valid.'); }

$roleSlug = current_role();
if (!chr_approval_export_authorized($conn, $rid, $roleSlug)) {
  http_response_code(403); exit('Akses ditolak.');
}

$payload = chr_approval_export_load($conn, $rid);
if (!$payload) { http_response_code(404); exit('Data CHR tidak ditemukan atau belum memakai pengesahan standar.'); }
if ($mode === 'final') {
  $errors = [];
  if (!chr_approval_export_final_ready($payload, $errors)) {
    http_response_code(409); exit(chr_approval_export_escape($errors[0] ?? 'Dokumen final hanya dapat diekspor setelah seluruh pengesahan selesai.'));
  }
}

$statusPart = $mode === 'final' ? 'FINAL' : 'DRAFT';
$filename = ($payload['filename_base'] ?? ('CHR_SOP_'.$rid)).'_'.$statusPart.'_'.date('Ymd');

session_release();

if ($format === 'docx') {
  try {
    $binary = chr_approval_export_docx_binary($payload, $mode);
  } catch (Throwable $e) {
    http_response_code(500); exit('Gagal membuat dokumen Word CHR.');
  }
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="'.$filename.'.docx"');
  header('Content-Length: '.strlen($binary));
  header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
  echo $binary;
  exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="'.$filename.'.html"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
echo chr_approval_export_render_html($payload, [
  'mode' => $mode,
  'auto_print' => false,
]);
