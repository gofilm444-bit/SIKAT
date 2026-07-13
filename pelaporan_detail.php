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
    if (is_file($__p)) {
        require_once $__p;
        $__found = true;
        break;
    }
}
if (!$__found) {
    http_response_code(500);
    die("db.php tidak ditemukan");
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    die("Koneksi $conn tidak tersedia.");
}
$conn->set_charset('utf8mb4');

require_once __DIR__.'/pelaporan_helpers.php';

if (empty($_SESSION['user'])) { header('Location: ' . route_url('login', ['open' => 'login'])); exit; }
$actor = pelaporan_actor_group($_SESSION['user']);
if (!in_array($actor, ['admin','kepala_ski','direktur'], true)) {
    http_response_code(403);
    die('Akses ditolak');
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf_token']).'">'; }
function csrf_validate($t){ if(!hash_equals($_SESSION['csrf_token'], (string)$t)){ http_response_code(400); die('Invalid CSRF token'); } }
function require_post(){ if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); die('Method Not Allowed'); } csrf_validate($_POST['csrf'] ?? ''); }

$kode = trim($_GET['kode'] ?? '');
if ($kode === ''){ http_response_code(400); die('Kode kosong'); }

$stmt = $conn->prepare("SELECT kode,kategori,isi,status,created_at FROM pelaporan WHERE kode=? LIMIT 1");
$stmt->bind_param("s", $kode);
$stmt->execute();
$lap = $stmt->get_result()->fetch_assoc();
if (!$lap){ http_response_code(404); die('Data tidak ditemukan'); }

$statusCanonical = pelaporan_status_canonical($lap['status']);
$statusLabel = pelaporan_status_label($lap['status']);
$statusDesc = pelaporan_status_description($lap['status']);
$statusBadge = pelaporan_status_badge($lap['status']);
$visibleStatuses = pelaporan_visible_statuses_for_actor($actor);
if (!in_array($statusCanonical, $visibleStatuses, true)) {
    http_response_code(403);
    die('Akses ditolak');
}

$stmtL = $conn->prepare("SELECT id, original_name, rel_path, mime, size_bytes FROM pelaporan_files WHERE kode=? ORDER BY id ASC");
$stmtL->bind_param("s", $kode);
$stmtL->execute();
$files = $stmtL->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtH = $conn->prepare("SELECT status_from,status_to,note,user_name,created_at FROM pelaporan_log WHERE kode=? ORDER BY created_at ASC, id ASC");
$stmtH->bind_param("s", $kode);
$stmtH->execute();
$logs = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($logs as &$log) {
    $fromRaw = trim((string)($log['status_from'] ?? ''));
    $toRaw   = trim((string)($log['status_to'] ?? ''));
    $log['status_from_label'] = $fromRaw !== '' ? pelaporan_status_label($fromRaw) : 'Pengaduan Masuk';
    $log['status_to_label']   = $toRaw   !== '' ? pelaporan_status_label($toRaw)   : 'Pengaduan Masuk';
    $log['note'] = trim((string)($log['note'] ?? ''));
    $log['user_name'] = trim((string)($log['user_name'] ?? '')) ?: 'Sistem/Publik';
}
unset($log);

$rekapNote = '';
if ($actor === 'direktur') {
    foreach (array_reverse($logs) as $logItem) {
        if (pelaporan_status_canonical($logItem['status_to']) === 'Verifikasi Direktur') {
            $noteRaw = trim((string)$logItem['note']);
            if ($noteRaw !== '') {
                $parts = explode('| Lampiran:', $noteRaw, 2);
                $noteClean = trim($parts[0]);
                $rekapNote = $noteClean !== '' ? $noteClean : $noteRaw;
            }
            break;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Detail Laporan <?= e($kode) ?> - SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= htmlspecialchars(asset_url('assets/css/ui_base.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <style>
    :root{ --brand:#218838; --accent:#f0c300; --soft:#f4f8f5; --border:#d6e9de; }
    body{background:var(--soft);}
    .appbar{background:var(--brand); border-bottom:4px solid var(--accent); color:#fff;}
    .card-soft{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.06);}
    .timeline{ border-left:3px solid #dbece2; margin-left:10px; padding-left:15px;}
    .dot{ width:10px;height:10px;background:#218838;border-radius:50%;display:inline-block;margin-right:6px; }
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>


<main class="container my-4">
  <div class="d-flex justify-content-end mb-3"><a class="btn btn-outline-secondary btn-sm" href="pelaporan.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card-soft p-3 mb-3">
        <div class="mb-2"><b>Kategori:</b> <?= e($lap['kategori']) ?></div>
        <div class="mb-2"><b>Status:</b> <span class="badge <?= e($statusBadge) ?>"><?= e($statusLabel) ?></span><?php if($statusDesc !== ''): ?> <span class="text-muted ms-1">- <?= e($statusDesc) ?></span><?php endif; ?></div>
        <div class="mb-2"><b>Dibuat:</b> <?= e($lap['created_at']) ?></div>
        <?php if ($actor === 'direktur'): ?>
          <div><b>Ringkasan Admin SKI:</b><br>
            <?php if ($rekapNote !== ''): ?>
              <?= nl2br(e($rekapNote)) ?>
            <?php else: ?>
              <span class="text-muted">Belum ada ringkasan dari Admin SKI.</span>
            <?php endif; ?>
          </div>
          <details class="mt-3">
            <summary class="text-muted">Lihat laporan asli dari pelapor</summary>
            <div class="mt-2"><?= nl2br(e($lap['isi'])) ?></div>
          </details>
        <?php else: ?>
          <div><b>Isi:</b><br><?= nl2br(e($lap['isi'])) ?></div>
        <?php endif; ?>
      </div>

      <div class="card-soft p-3">
        <h6 class="mb-2">Riwayat Status</h6>
        <?php if(empty($logs)): ?>
          <div class="text-muted">Belum ada riwayat.</div>
        <?php else: ?>
          <div class="timeline">
            <?php foreach($logs as $h): ?>
              <div class="mb-2">
                <span class="dot"></span>
                <b><?= e($h['status_from_label']) ?></b>
                <i class="bi bi-arrow-right-short"></i>
                <b><?= e($h['status_to_label']) ?></b>
                <?php if($h['note'] !== ''): ?> - <em><?= e($h['note']) ?></em><?php endif; ?>
                <div class="small text-muted"><?= e($h['created_at']) ?> oleh <?= e($h['user_name']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card-soft p-3">
        <h6 class="mb-2">Lampiran</h6>
        <?php if(empty($files)): ?>
          <div class="text-muted">Tidak ada lampiran.</div>
        <?php else: ?>
          <ul class="mb-0 list-unstyled">
            <?php foreach($files as $f): ?>
              <li class="mb-3">
                <div class="fw-semibold"><?= e($f['original_name']) ?></div>
                <small class="text-muted d-block">(<?= e($f['mime']) ?>, <?= number_format($f['size_bytes']/1024,1) ?> KB)</small>
                <div class="mt-1 d-inline-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('attachment_download.php', ['id' => (int)$f['id'], 'mode' => 'view'])) ?>" target="_blank" rel="noopener">Lihat</a>
                  <a class="btn btn-sm btn-outline-success" href="<?= e(endpoint_url('attachment_download.php', ['id' => (int)$f['id'], 'mode' => 'download'])) ?>" download>Unduh</a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>



