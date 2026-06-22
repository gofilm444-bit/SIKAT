<?php
require_once __DIR__ . '/bootstrap.php';
$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
$env = strtolower((string)$env);
if (in_array($env, ['local','dev','development'], true)) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}


/* ====== DB Loader Fleksibel ====== */
$__base = __DIR__;
$__candidates = [
  $__base.'/db.php',
  $__base.'/ski_new/db.php',
  $__base.'/db/db.php',
  dirname($__base).'/db.php',
  $__base.'/includes/db.php'
];
$__found=false; foreach($__candidates as $__p){ if(is_file($__p)){ require_once $__p; $__found=true; break; } }
if(!$__found){ http_response_code(500); die("db.php tidak ditemukan:\n - ".implode("\n - ", $__candidates)); }
if(!isset($conn) || !($conn instanceof mysqli)){ http_response_code(500); die("Koneksi \$conn tidak tersedia."); }
$conn->set_charset('utf8mb4');

require_once __DIR__.'/pelaporan_helpers.php';

/* ====== AuthZ (admin/moderator) ====== */
if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$role = strtolower($_SESSION['user']['peran'] ?? '');
$roleRaw = strtolower($_SESSION['user']['peran_raw'] ?? $role);

$auditeeRoles = [
  'auditee','auditee_direktur','auditee_wadir1','auditee_wadir2','auditee_wadir3',
  'auditee_adav','auditee_adum','auditee_pmpp','auditee_pppm','auditee_itp',
  'auditee_labterpadu','auditee_perpustakaan','auditee_keperawatan','auditee_kebidanan',
  'auditee_gizi','auditee_kesling','auditee_tlm'
];

$reviewRedirectRoles = array_merge($auditeeRoles, ['auditor','auditor_staff','auditor_ka']);
$actor = pelaporan_actor_group($_SESSION['user']);

/**
 * ====== NEW: Per-user flag akses_dashboard dari DB ======
 * - default 0 (tidak boleh)
 * - kalau 1: boleh dashboard walaupun role termasuk yang biasanya redirect ke review
 */
$akses_dashboard = 0;
$uid = (int)($_SESSION['user']['id'] ?? 0);

// fallback kalau key session id beda (jaga-jaga)
if ($uid <= 0) {
  $uid = (int)($_SESSION['user']['user_id'] ?? 0);
}

if ($uid > 0) {
  if ($st = $conn->prepare("SELECT akses_dashboard FROM pengguna WHERE id = ? LIMIT 1")) {
    $st->bind_param("i", $uid);
    if ($st->execute()) {
      $res = $st->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $akses_dashboard = (int)($row['akses_dashboard'] ?? 0);
    }
    $st->close();
  }
}

/**
 * ====== Dashboard allowlist lama + flag baru ======
 * - Tetap izinkan admin/kepala_ski/direktur via actor group
 * - Auditee roles masih boleh dashboard
 * - Tambahkan bypass bila akses_dashboard = 1
 */
$can_view_dashboard =
  in_array($actor, ['admin','kepala_ski','direktur'], true)
  || in_array($role, $auditeeRoles, true)
  || in_array($roleRaw, $auditeeRoles, true)
  || ($akses_dashboard === 1);

/**
 * ====== Redirect ke review (kecuali punya akses_dashboard=1) ======
 */
if (
  $akses_dashboard !== 1
  && (in_array($role, $reviewRedirectRoles, true) || in_array($roleRaw, $reviewRedirectRoles, true))
) {
  header('Location: review.php');
  exit;
}

if (!$can_view_dashboard) { http_response_code(403); die('Akses ditolak'); }

/* ====== Helpers ====== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8').'">'; }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function user_initials(string $name): string {
  $name = trim(preg_replace('/\s+/', ' ', $name));
  if ($name === '') return 'U';
  $parts = explode(' ', $name);
  $first = $parts[0] ?? '';
  $second = $parts[1] ?? '';
  $get_char = function(string $s): string {
    if (function_exists('mb_substr')) {
      return mb_substr($s, 0, 1, 'UTF-8');
    }
    return substr($s, 0, 1);
  };
  $upper = function(string $s): string {
    if (function_exists('mb_strtoupper')) {
      return mb_strtoupper($s, 'UTF-8');
    }
    return strtoupper($s);
  };
  $initials = $upper($get_char($first));
  if ($second !== '') {
    $initials .= $upper($get_char($second));
  }
  return $initials;
}
$role = $role ?: strtolower($_SESSION['user']['peran'] ?? '');
$roleRaw = $roleRaw ?: strtolower($_SESSION['user']['peran_raw'] ?? '');
$can_manage_users = in_array($role, ['super_admin','admin'], true) || in_array($roleRaw, ['super_admin','admin'], true);
$can_manage_recipients = in_array($role, ['admin','super_admin','superadmin','moderator'], true) || in_array($roleRaw, ['admin','super_admin','superadmin','moderator'], true);
$display_name = $_SESSION['user']['nama'] ?? ($_SESSION['user']['username'] ?? 'Pengguna');
$profile_initials = user_initials((string)$display_name);

/* ====== DATA untuk widget ====== */
$sum  = ['total'=>0,'masuk'=>0,'proses'=>0,'arsip'=>0,'kembali'=>0];
$dist = [];
if ($q = $conn->query("SELECT status, COUNT(*) c FROM pelaporan GROUP BY status")) {
  while ($row = $q->fetch_assoc()) {
    $canonical = pelaporan_status_canonical($row['status']);
    $label     = pelaporan_status_label($canonical);
    $count     = (int)$row['c'];

    $dist[$label] = ($dist[$label] ?? 0) + $count;

    switch ($canonical) {
      case 'Masuk':
        $sum['masuk'] += $count;
        break;
      case 'Arsip':
        $sum['arsip'] += $count;
        break;
      case 'Kembali ke Pelapor':
        $sum['kembali'] += $count;
        break;
      default:
        $sum['proses'] += $count;
        break;
    }
    $sum['total'] += $count;
  }
}

$trend_labels=[]; $trend_counts=[];
$trendWindow = [];
$baseMonth = new DateTime('first day of this month');
for ($i = 11; $i >= 0; $i--) {
  $label = (clone $baseMonth)->modify('-'.$i.' month')->format('Y-m');
  $trendWindow[$label] = 0;
}
$cutoffDate = (clone $baseMonth)->modify('-11 month')->format('Y-m-01');
if ($stmtTrend = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c FROM pelaporan WHERE created_at >= ? GROUP BY ym")) {
  $stmtTrend->bind_param("s", $cutoffDate);
  if ($stmtTrend->execute()) {
    $resTrend = $stmtTrend->get_result();
    while ($row = $resTrend->fetch_assoc()) {
      $ym = $row['ym'];
      if (isset($trendWindow[$ym])) {
        $trendWindow[$ym] = (int)$row['c'];
      }
    }
  }
}
foreach ($trendWindow as $label => $count) {
  $trend_labels[] = $label;
  $trend_counts[] = $count;
}

$cat_labels=[]; $cat_counts=[];
if ($qc=$conn->query("SELECT kategori, COUNT(*) c FROM pelaporan GROUP BY kategori ORDER BY c DESC LIMIT 5")) {
  while($r=$qc->fetch_assoc()){ $cat_labels[]=$r['kategori']; $cat_counts[]=(int)$r['c']; }
}

$latest=[];
if ($ql=$conn->query("SELECT kode,kategori,status,LEFT(isi,120) isi_short, created_at FROM pelaporan ORDER BY created_at DESC LIMIT 5")) {
  $latest=$ql->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Dashboard – Sistem SIKAT Poltekkes Ternate</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/ui_base.css" rel="stylesheet">
  <link rel="preload" as="image" href="/ski_new/asset/logo-sikat-baru-140.png">
  <style>
    :root{
      --brand:#218838; --brand-dark:#1b6e2c; --accent:#f0c300;
      --soft:#fbfdf8; --border:#dcefe4; --text:#0b3d2e;
    }
    body{ background:var(--soft); color:#174a3a; }
    .hero{
      text-align:center; padding:24px 12px 12px; position:relative;
    }
    .profile-area{
      position:absolute; top:12px; left:12px; z-index:60;
    }
    .profile-btn{
      width:38px; height:38px; border-radius:50%;
      background:#0f9152; color:#fff; border:2px solid #e3f3ea;
      display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:.85rem; letter-spacing:.02em;
      box-shadow:0 4px 12px rgba(0,0,0,.12);
    }
    .profile-btn:focus{ outline:2px solid #0d7b45; outline-offset:2px; }
    .profile-dropdown{
      position:absolute; top:46px; left:0; min-width:190px; max-width:80vw;
      background:#fff; border:1px solid var(--border); border-radius:12px;
      box-shadow:0 10px 24px rgba(0,0,0,.12);
      padding:6px; display:none;
    }
    .profile-area.open .profile-dropdown{ display:block; }
    .profile-dropdown a{
      display:block; padding:8px 10px; border-radius:8px;
      color:#124b38; text-decoration:none; font-weight:600;
    }
    .profile-dropdown a:hover{ background:#eef7f1; }
    .profile-dropdown .danger{ color:#b02a37; }
    .profile-divider{ height:1px; background:#e6efe9; margin:6px 4px; }
    @media (max-width: 576px){
      .profile-area{ top:8px; left:8px; }
      .profile-btn{ width:34px; height:34px; }
    }
    .brand-logo{
      height:70px; width:auto; margin-bottom:2px;
    }
    .title{
      font-weight:800; color:#0f9152; text-shadow:0 1px 0 #fff; font-size:1.6rem; letter-spacing:.2px;
    }
    .subtitle{ color:#5f6e64; max-width:720px; margin:4px auto 0; font-size:.95rem; line-height:1.4; }
    .menu-bar{
      background:#e8f5ec; border:1px solid var(--border); border-radius:10px; padding:10px;
    }
    .menu-bar .btn{
      background:#0f9152; border:0; color:#fff; font-weight:600; border-radius:10px;
      padding:.45rem .85rem; font-size:.92rem;
    }
    .menu-bar .btn:focus-visible{ outline:2px solid #f0c300; outline-offset:2px; box-shadow:0 0 0 2px rgba(33,136,56,.35); }
    .menu-bar .btn:hover{ background:#0d7b45; }
    .menu-bar .btn-outline-danger{ background:#dc3545; }
    .divider{ height:2px; background:#cfe7da; margin:14px 0 12px; }
    /* Cards */
    .card-soft{ background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.05); }
    .kpi{ border-radius:10px; border:1px solid var(--border); padding:6px 8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.04); min-height:64px; display:flex; flex-direction:column; justify-content:center; gap:2px; }
    .kpi small{ color:#6b7280; font-size:.76rem; letter-spacing:.2px; }
    .kpi h3{ font-size:1.05rem; line-height:1.2; font-variant-numeric: tabular-nums; }
    .table thead th{ background:#f2f8f4; }
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>

<!-- ====== HEADER + MENU (sesuai screenshot) ====== -->
<section class="hero container sikat-hero">
  <img class="brand-logo hero-logo" src="/ski_new/asset/logo-sikat-baru-140.png" alt="SIKAT">
  <h2 class="title mt-2 hero-title">Dashboard SIKAT</h2>
  <p class="subtitle hero-subtitle">Sistem Informasi Kepatuhan Internal Poltekkes Ternate (SIKAT).</p>
  <p class="mt-2 fw-semibold hero-greeting">Halo, <?= e($_SESSION['user']['nama'] ?? 'Pengguna') ?>!</p>

  <div class="menu-bar d-inline-block mt-2 sikat-quick-actions">
    <div class="d-flex flex-wrap gap-2 justify-content-center quick-actions-row">
      <a href="kebijakan.php" class="btn sikat-quick-btn">Kebijakan &amp; Regulasi</a>
      <a href="review.php" class="btn sikat-quick-btn">Review Internal</a>
      <a href="pelaporan.php" class="btn sikat-quick-btn">Pelaporan</a>
      <a href="risiko.php" class="btn sikat-quick-btn">Risiko</a>
      <a href="self_assessment.php" class="btn sikat-quick-btn">Self-Assessment</a>
    </div>
  </div>
</section>
<div class="container">
  <div class="divider hero-divider"></div>
</div>
<!-- ====== /HEADER + MENU ====== -->

<main class="container mb-5">

  <!-- KPI Ringkas -->
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="kpi"><small>Total Pengaduan</small><h3 class="m-0"><?= number_format($sum['total']) ?></h3></div></div>
    <div class="col-md-3"><div class="kpi"><small>Pengaduan Masuk</small><h3 class="m-0"><?= number_format($sum['masuk']) ?></h3></div></div>
    <div class="col-md-3"><div class="kpi"><small>Tahap Berjalan</small><h3 class="m-0"><?= number_format($sum['proses']) ?></h3></div></div>
    <div class="col-md-3"><div class="kpi"><small>Arsip</small><h3 class="m-0"><?= number_format($sum['arsip']) ?></h3></div></div>
  </div>
  <p class="text-muted small">Kembali ke pelapor: <?= number_format($sum['kembali']) ?> laporan.</p>

  <div class="row g-3">
    <!-- Tren Bulanan -->
    <div class="col-lg-8">
      <div class="card-soft p-3">
        <h6 class="mb-3">Tren Laporan 12 Bulan Terakhir</h6>
        <canvas id="trend"></canvas>
      </div>
    </div>

    <!-- Distribusi Status -->
    <div class="col-lg-4">
      <div class="card-soft p-3">
        <h6 class="mb-3">Distribusi Status</h6>
        <canvas id="statusPie"></canvas>
      </div>
    </div>

    <!-- Top Kategori -->
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <h6 class="mb-3">Top 5 Kategori</h6>
        <canvas id="kategoriBar"></canvas>
      </div>
    </div>

    <!-- 5 Terbaru -->
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <h6 class="mb-3">5 Laporan Terbaru</h6>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Kode</th><th>Kategori</th><th>Status</th><th>Waktu</th><th>Ringkas</th></tr></thead>
            <tbody>
              <?php if(empty($latest)): ?>
                <tr><td colspan="5"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
              <?php else: foreach($latest as $r): ?>
                <tr>
                  <td><a href="pelaporan_detail.php?kode=<?= urlencode($r['kode']) ?>" class="text-decoration-none"><?= e($r['kode']) ?></a></td>
                  <td><?= e($r['kategori']) ?></td>
                  <td>
                    <?php $st=$r['status']; $cls=$st==='Selesai'?'success':($st==='Proses'?'warning text-dark':'secondary'); ?>
                    <span class="badge bg-<?= $cls ?>"><?= e($st) ?></span>
                  </td>
                  <td><?= e($r['created_at']) ?></td>
                  <td><?= nl2br(e($r['isi_short'])) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
  const wrap = document.getElementById('profileMenuWrap');
  const btn = document.getElementById('profileMenuButton');
  if (!wrap || !btn) return;
  const closeMenu = () => {
    wrap.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  };
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const isOpen = wrap.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });
})();
const trendLabels = <?= json_encode($trend_labels, JSON_UNESCAPED_UNICODE) ?>;
const trendCounts = <?= json_encode($trend_counts, JSON_UNESCAPED_UNICODE) ?>;
const statusLabels = <?= json_encode(array_keys($dist), JSON_UNESCAPED_UNICODE) ?>;
const statusCounts = <?= json_encode(array_values($dist), JSON_UNESCAPED_UNICODE) ?>;
const catLabels = <?= json_encode($cat_labels, JSON_UNESCAPED_UNICODE) ?>;
const catCounts = <?= json_encode($cat_counts, JSON_UNESCAPED_UNICODE) ?>;

// Line: Tren bulanan
if (document.getElementById('trend')) {
  new Chart(document.getElementById('trend'), {
    type: 'line',
    data: { labels: trendLabels, datasets: [{ label:'Jumlah laporan', data: trendCounts, tension:.3, fill:false }] },
    options: { responsive:true, plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:true, precision:0 } } }
  });
}

// Pie: Status
if (document.getElementById('statusPie')) {
  new Chart(document.getElementById('statusPie'), {
    type: 'doughnut',
    data: { labels: statusLabels, datasets: [{ data: statusCounts }] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
  });
}

// Bar: Top kategori
if (document.getElementById('kategoriBar')) {
  new Chart(document.getElementById('kategoriBar'), {
    type: 'bar',
    data: { labels: catLabels, datasets: [{ label:'Jumlah', data: catCounts }] },
    options: { responsive:true, plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:true, precision:0 } } }
  });
}
</script>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>
