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
$__found=false;
foreach ($__candidates as $__p) {
  if (is_file($__p)) { require_once $__p; $__found=true; break; }
}
if (!$__found) { http_response_code(500); exit('db.php tidak ditemukan.'); }
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); exit('Koneksi DB tidak tersedia.'); }
$conn->set_charset('utf8mb4');

require_once __DIR__.'/review_export_helpers.php';

if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$rid = (int)($_GET['rid'] ?? 0);
if ($rid < 1) { http_response_code(400); exit('Parameter rid wajib.'); }
$lapid = (int)($_GET['lapid'] ?? 0);

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
} elseif ($roleSlug === 'kepala_ski') {
  if (!review_is_assigned($conn, $rid, 'AUDITOR') && !review_is_assigned($conn, $rid, 'AUDITEE')) {
    http_response_code(403); exit('Akses ditolak.');
  }
} else {
  http_response_code(403); exit('Akses ditolak.');
}

$revInfo = null;
if ($stmt = $conn->prepare("SELECT r.kode, r.status, r.tahun, r.updated_at,
                                   u.nama unit_nama,
                                   j.nama jenis_nama
                            FROM reviu r
                            LEFT JOIN unit_kerja u ON u.id=r.unit_id
                            LEFT JOIN jenis_reviu j ON j.id=r.jenis_id
                            WHERE r.id=? LIMIT 1")) {
  $stmt->bind_param("i", $rid);
  if ($stmt->execute()) {
    $revInfo = $stmt->get_result()->fetch_assoc();
  }
  $stmt->close();
}
if (!$revInfo) {
  $revInfo = [
    'kode'       => 'REVIU-'.$rid,
    'status'     => '',
    'tahun'      => '',
    'updated_at' => '',
    'unit_nama'  => '',
    'jenis_nama' => ''
  ];
}

$lap = null;
if ($lapid > 0) {
  if ($stmt = $conn->prepare("SELECT id, ringkasan, rekomendasi, tindak_lanjut, ttd_kepala_nama, ttd_kepala_tanggal, ttd_kepala_file, created_at, updated_at FROM reviu_laporan WHERE id=? AND reviu_id=? LIMIT 1")) {
    $stmt->bind_param("ii", $lapid, $rid);
    if ($stmt->execute()) {
      $lap = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
  }
}
if (!$lap) {
  if ($stmt = $conn->prepare("SELECT id, ringkasan, rekomendasi, tindak_lanjut, ttd_kepala_nama, ttd_kepala_tanggal, ttd_kepala_file, created_at, updated_at FROM reviu_laporan WHERE reviu_id=? ORDER BY updated_at DESC, created_at DESC LIMIT 1")) {
    $stmt->bind_param("i", $rid);
    if ($stmt->execute()) {
      $lap = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
  }
}

$filenameBase = $revInfo['kode'] ?? ('LAPORAN-'.$rid);
$filenameBase = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $filenameBase);
$filenameSuffix = $lapid > 0 ? ('ID'.$lapid) : 'Terbaru';
$filename = 'LaporanAkhir_'.$filenameBase.'_'.$filenameSuffix.'_'.date('Ymd_His').'.html';

session_release();

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

function ex_cell($value): string {
  $text = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  $text = str_replace(["\r\n","\r","\n"], '<br>', $text);
  return $text === '' ? '&ndash;' : $text;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Export Laporan Akhir</title>
  <style>
    body{font-family:Calibri,Arial,sans-serif;font-size:12px;color:#111;margin:0;padding:16px;background:#fff;}
    h1{font-size:20px;margin-bottom:10px;}
    h2{font-size:16px;margin-top:24px;margin-bottom:8px;}
    h3{font-size:14px;margin-top:16px;margin-bottom:6px;}
    table{border-collapse:collapse;width:100%;margin-bottom:12px;}
    th,td{border:1px solid #999;padding:6px;vertical-align:top;}
    th{background:#e6f4ea;font-weight:600;text-align:left;}
    .meta td{border:none;padding:3px 6px;}
    .meta td.label{font-weight:600;width:160px;}
    .section{margin-bottom:16px;}
    .sig-box{border:1px solid #999;border-radius:8px;padding:12px;margin-top:8px;min-height:120px;display:flex;align-items:center;justify-content:center;}
    .sig-box img{max-height:160px;max-width:100%;}
    .note{color:#555;font-style:italic;font-size:11px;}
  </style>
</head>
<body>
  <h1>Laporan Akhir Reviu</h1>
  <table class="meta">
    <tr>
      <td class="label">Kode Reviu</td>
      <td><?= ex_cell($revInfo['kode'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="label">Jenis Reviu</td>
      <td><?= ex_cell($revInfo['jenis_nama'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="label">Unit</td>
      <td><?= ex_cell($revInfo['unit_nama'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="label">Status</td>
      <td><?= ex_cell($revInfo['status'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="label">Terakhir Diperbarui</td>
      <td><?= ex_cell($revInfo['updated_at'] ?? '') ?></td>
    </tr>
  </table>

  <?php if (!$lap): ?>
    <div class="section">
      <h2>Laporan Akhir</h2>
      <p>Belum ada data laporan akhir yang tersimpan.</p>
    </div>
  <?php else: ?>
    <div class="section">
      <h2>Laporan Akhir</h2>
      <table>
        <tr>
          <th>Ringkasan</th>
        </tr>
        <tr>
          <td><?= ex_cell($lap['ringkasan'] ?? '') ?></td>
        </tr>
      </table>
      <table>
        <tr>
          <th>Rekomendasi</th>
        </tr>
        <tr>
          <td><?= ex_cell($lap['rekomendasi'] ?? '') ?></td>
        </tr>
      </table>
      <table>
        <tr>
          <th>Tindak Lanjut</th>
        </tr>
        <tr>
          <td><?= ex_cell($lap['tindak_lanjut'] ?? '') ?></td>
        </tr>
      </table>

      <h3>Ka SKI</h3>
      <table>
        <tr>
          <th>Nama</th>
          <td><?= ex_cell($lap['ttd_kepala_nama'] ?? '') ?></td>
        </tr>
        <tr>
          <th>Tanggal TTD</th>
          <td><?= ex_cell($lap['ttd_kepala_tanggal'] ?? '') ?></td>
        </tr>
        <tr>
          <th>Dibuat</th>
          <td><?= ex_cell($lap['created_at'] ?? '') ?></td>
        </tr>
        <tr>
          <th>Diperbarui</th>
          <td><?= ex_cell($lap['updated_at'] ?? '') ?></td>
        </tr>
      </table>

      <?php if (!empty($lap['ttd_kepala_file'])): ?>
        <?php
          $sigPath = __DIR__ . DIRECTORY_SEPARATOR . $lap['ttd_kepala_file'];
          $sigData = '';
          if (is_file($sigPath)) {
            $mimeType = mime_content_type($sigPath) ?: 'image/png';
            $sigData = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($sigPath));
          }
        ?>
        <?php if ($sigData !== ''): ?>
          <div class="sig-box">
            <img src="<?= $sigData ?>" alt="Tanda tangan Ka SKI">
          </div>
        <?php else: ?>
          <p class="note">Berkas tanda tangan tidak tersedia atau tidak dapat dibaca.</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="note">Belum ada berkas tanda tangan yang terunggah.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>



