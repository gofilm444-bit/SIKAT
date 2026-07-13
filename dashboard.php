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
if (empty($_SESSION['user'])) { header('Location: ' . route_url('login', ['open' => 'login'])); exit; }

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
  header('Location: ' . route_url('review'));
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
function dashboard_table_exists(mysqli $conn, string $table): bool {
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];
  $tableEsc = $conn->real_escape_string($table);
  $ok = false;
  if ($rs = $conn->query("SHOW TABLES LIKE '{$tableEsc}'")) {
    $ok = $rs->num_rows > 0;
    $rs->free();
  }
  return $cache[$table] = $ok;
}
function dashboard_column_exists(mysqli $conn, string $table, string $column): bool {
  static $cache = [];
  $key = $table . ':' . $column;
  if (isset($cache[$key])) return $cache[$key];
  $tableEsc = $conn->real_escape_string($table);
  $columnEsc = $conn->real_escape_string($column);
  $ok = false;
  if ($rs = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'")) {
    $ok = $rs->num_rows > 0;
    $rs->free();
  }
  return $cache[$key] = $ok;
}
function dashboard_count(mysqli $conn, string $sql): int {
  if ($rs = $conn->query($sql)) {
    $row = $rs->fetch_assoc();
    $rs->free();
    return (int)($row['c'] ?? 0);
  }
  return 0;
}
function dashboard_status_badge_class(string $status): string {
  $status = strtolower(trim($status));
  if (in_array($status, ['selesai', 'arsip', 'closed', 'tuntas'], true)) return 'success';
  if (in_array($status, ['proses', 'diproses', 'sedang diproses', 'tahap berjalan'], true)) return 'warning text-dark';
  if (in_array($status, ['masuk', 'belum diproses', 'baru'], true)) return 'primary';
  if (in_array($status, ['kembali ke pelapor', 'ditolak'], true)) return 'danger';
  return 'secondary';
}
function dashboard_datetime_short(?string $value): string {
  if (!$value) return '-';
  $ts = strtotime($value);
  if (!$ts) return $value;
  return date('d M Y H:i', $ts);
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

$ski = [
  'total_review' => 0,
  'review_berjalan' => 0,
  'rekomendasi_aktif' => 0,
  'tl_terlambat' => 0,
  'risiko_tinggi' => 0,
];
$deadlineItems = [];

if (dashboard_table_exists($conn, 'reviu')) {
  $ski['total_review'] = dashboard_count($conn, "SELECT COUNT(*) c FROM reviu");
  if (dashboard_column_exists($conn, 'reviu', 'status')) {
    $ski['review_berjalan'] = dashboard_count($conn, "SELECT COUNT(*) c FROM reviu WHERE COALESCE(status,'') NOT IN ('Selesai','Arsip','Dibatalkan')");
  }
}

if (dashboard_table_exists($conn, 'reviu_chr') && dashboard_column_exists($conn, 'reviu_chr', 'status_tl')) {
  $ski['rekomendasi_aktif'] = dashboard_count($conn, "SELECT COUNT(*) c FROM reviu_chr WHERE COALESCE(status_tl,'') NOT IN ('Selesai','Sudah TL','Tuntas','Closed')");
  if (dashboard_column_exists($conn, 'reviu_chr', 'due_date')) {
    $ski['tl_terlambat'] = dashboard_count($conn, "SELECT COUNT(*) c FROM reviu_chr WHERE due_date < CURDATE() AND COALESCE(status_tl,'') NOT IN ('Selesai','Sudah TL','Tuntas','Closed')");
    if ($qd = $conn->query("SELECT id, rekomendasi, status_tl, due_date FROM reviu_chr WHERE due_date IS NOT NULL AND COALESCE(status_tl,'') NOT IN ('Selesai','Sudah TL','Tuntas','Closed') ORDER BY due_date ASC LIMIT 4")) {
      while ($row = $qd->fetch_assoc()) {
        $deadlineItems[] = [
          'title' => trim((string)($row['rekomendasi'] ?? 'Rekomendasi tindak lanjut')),
          'meta' => 'Deadline ' . dashboard_datetime_short((string)($row['due_date'] ?? '')),
          'status' => (string)($row['status_tl'] ?? 'Belum TL'),
          'href' => route_url('review'),
        ];
      }
      $qd->free();
    }
  }
}

if (empty($deadlineItems) && dashboard_table_exists($conn, 'reviu') && dashboard_column_exists($conn, 'reviu', 'tgl_deadline')) {
  if ($qd = $conn->query("SELECT kode, status, tgl_deadline FROM reviu WHERE tgl_deadline IS NOT NULL AND COALESCE(status,'') NOT IN ('Selesai','Arsip','Dibatalkan') ORDER BY tgl_deadline ASC LIMIT 4")) {
    while ($row = $qd->fetch_assoc()) {
      $deadlineItems[] = [
        'title' => 'Review ' . ((string)($row['kode'] ?? '') ?: 'Internal'),
        'meta' => 'Deadline ' . dashboard_datetime_short((string)($row['tgl_deadline'] ?? '')),
        'status' => (string)($row['status'] ?? 'Terjadwal'),
        'href' => route_url('review'),
      ];
    }
    $qd->free();
  }
}

if (dashboard_table_exists($conn, 'risiko') && dashboard_column_exists($conn, 'risiko', 'tingkat')) {
  $where = "LOWER(tingkat) IN ('tinggi','ekstrem','extreme')";
  if (dashboard_column_exists($conn, 'risiko', 'status')) {
    $where .= " AND COALESCE(status,'Aktif') = 'Aktif'";
  }
  $ski['risiko_tinggi'] = dashboard_count($conn, "SELECT COUNT(*) c FROM risiko WHERE {$where}");
}

$insights = [
  'review' => $ski['review_berjalan'] > 0 ? number_format($ski['review_berjalan']) . ' review masih berjalan' : 'Belum ada review berjalan',
  'rekomendasi' => $ski['rekomendasi_aktif'] > 0 ? 'Rekomendasi aktif perlu dipantau' : 'Tidak ada rekomendasi aktif',
  'terlambat' => $ski['tl_terlambat'] > 0 ? number_format($ski['tl_terlambat']) . ' tindak lanjut melewati deadline' : 'Tidak ada tindak lanjut terlambat',
  'risiko' => $ski['risiko_tinggi'] > 0 ? number_format($ski['risiko_tinggi']) . ' risiko prioritas tinggi' : 'Tidak ada risiko tinggi aktif',
  'pelaporan' => $sum['proses'] > 0 ? number_format($sum['proses']) . ' laporan masih tahap berjalan' : 'Tidak ada laporan tahap berjalan',
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Dashboard – Sistem SIKAT Poltekkes Ternate</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= e(asset_url('assets/css/ui_base.css')) ?>" rel="stylesheet">
  <link rel="preload" as="image" href="<?= e(asset_url('asset/logo-sikat-baru-140.png')) ?>">
  <style>
    :root{
      --brand:#218838; --brand-dark:#1b6e2c; --accent:#f0c300;
      --soft:#fbfdf8; --border:#dcefe4; --text:#0b3d2e;
    }
    body{ background:var(--soft); color:#174a3a; }
    .hero{
      text-align:center; padding:10px 12px 4px; position:relative;
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
      width:129px; height:auto; max-height:96px; object-fit:contain; aspect-ratio:auto; margin-bottom:0;
    }
    .title{
      font-weight:800; color:#0f9152; text-shadow:0 1px 0 #fff; font-size:1.42rem; letter-spacing:.2px;
    }
    .subtitle{ color:#5f6e64; max-width:720px; margin:2px auto 0; font-size:.9rem; line-height:1.35; }
    .hero-greeting{ color:#315b4d; margin-bottom:.35rem; }
    .sikat-hero{
      display:grid; grid-template-columns:140px minmax(0, 1fr);
      grid-template-areas:"logo title" "logo subtitle" "logo greeting" "actions actions";
      align-items:center; column-gap:14px; row-gap:0; max-width:1140px;
      text-align:left; padding-top:8px; padding-bottom:3px;
    }
    .sikat-hero .hero-logo{
      grid-area:logo; width:129px; height:auto; max-width:135px; max-height:96px;
      object-fit:contain; aspect-ratio:auto; margin:0; justify-self:end;
    }
    .sikat-hero .hero-title{
      grid-area:title; margin:0 0 2px !important; line-height:1.18;
    }
    .sikat-hero .hero-subtitle{
      grid-area:subtitle; margin:0; max-width:760px;
    }
    .sikat-hero .hero-greeting{
      grid-area:greeting; margin:.12rem 0 0 !important; font-size:.9rem;
    }
    .sikat-hero .sikat-quick-actions{
      grid-area:actions; justify-self:center; margin-top:6px !important; margin-bottom:0;
    }
    .menu-bar{
      background:#fff; border:1px solid var(--border); border-radius:12px; padding:8px;
      box-shadow:0 6px 16px rgba(20,92,61,.08);
    }
    .menu-bar .btn{
      background:#0f9152; border:0; color:#fff; font-weight:700; border-radius:9px;
      padding:.48rem .75rem; font-size:.88rem; display:inline-flex; align-items:center; gap:.4rem;
    }
    .menu-bar .btn:focus-visible{ outline:2px solid #f0c300; outline-offset:2px; box-shadow:0 0 0 2px rgba(33,136,56,.35); }
    .menu-bar .btn:hover{ background:#0d7b45; }
    .menu-bar .btn-outline-danger{ background:#dc3545; }
    .divider{ height:1px; background:#cfe7da; margin:5px 0 9px; }
    /* Cards */
    .card-soft{ background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,.05); }
    .section-title{ font-size:.95rem; font-weight:800; color:#124b38; margin-bottom:.75rem; }
    .kpi{ border-radius:10px; border:1px solid var(--border); padding:10px 12px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); min-height:92px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
    .kpi small{ color:#5f6e64; font-size:.76rem; letter-spacing:.2px; font-weight:700; }
    .kpi h3{ font-size:1.35rem; line-height:1.05; font-variant-numeric: tabular-nums; color:#0b3d2e; }
    .kpi .insight{ color:#69746e; font-size:.76rem; line-height:1.25; min-height:1.9em; }
    .kpi.ski{ border-top:3px solid #0f9152; }
    .kpi.report{ border-top:3px solid #d7ad00; }
    .chart-box{ min-height:300px; }
    .status-chart-box{ min-height:300px; display:flex; align-items:center; justify-content:center; }
    #statusPie{ max-width:220px !important; max-height:220px !important; margin:0 auto; }
    .category-list{ display:flex; flex-direction:column; gap:10px; }
    .category-row{ display:grid; grid-template-columns:minmax(120px,1fr) minmax(130px,2fr) 42px; align-items:center; gap:10px; font-size:.9rem; }
    .category-label{ color:#174a3a; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .category-track{ height:10px; background:#eef6f1; border-radius:999px; overflow:hidden; }
    .category-fill{ height:100%; min-width:4px; background:#0f9152; border-radius:999px; }
    .category-count{ color:#5f6e64; text-align:right; font-variant-numeric:tabular-nums; }
    .activity-list{ display:flex; flex-direction:column; gap:10px; }
    .activity-item{ border:1px solid #edf3ef; border-radius:9px; padding:10px; background:#fbfdfc; }
    .activity-title{ font-weight:700; color:#174a3a; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .activity-meta{ color:#6b7280; font-size:.82rem; margin-top:4px; }
    .latest-table th{ white-space:nowrap; font-size:.82rem; color:#315b4d; }
    .latest-table td{ vertical-align:top; font-size:.9rem; }
    .latest-table .col-code{ min-width:118px; }
    .latest-table .col-summary{ min-width:260px; }
    .summary-text{ display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; line-height:1.35; color:#4a5f55; }
    .detail-link{ font-weight:700; font-size:.82rem; text-decoration:none; }
    .empty-state{ padding:22px; text-align:center; color:#607066; background:#fbfdfc; border:1px dashed #cfe7da; border-radius:10px; }
    .empty-state .hint{ color:#7b8a82; font-size:.86rem; margin-top:2px; }
    .table thead th{ background:#f2f8f4; }
    @media (max-width: 768px){
      .sikat-hero{
        display:block; text-align:center; padding-top:9px; padding-bottom:5px;
      }
      .sikat-hero .hero-logo{
        width:114px; height:auto; max-width:123px; max-height:87px; margin:0 auto 5px;
      }
      .sikat-hero .hero-title{
        margin-bottom:3px !important;
      }
      .sikat-hero .hero-subtitle{
        margin:0 auto; max-width:640px;
      }
      .sikat-hero .hero-greeting{
        margin:.25rem 0 .25rem !important;
      }
      .sikat-hero .sikat-quick-actions{
        margin-top:5px !important;
      }
      .category-row{ grid-template-columns:1fr 36px; }
      .category-track{ grid-column:1 / -1; grid-row:2; }
    }
    @media (max-width: 576px){
      .sikat-hero .hero-logo{ width:105px; height:auto; max-width:114px; max-height:81px; }
      .sikat-hero .hero-title{ font-size:1.26rem; }
      .sikat-hero .hero-subtitle{ font-size:.84rem; line-height:1.28; }
    }
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>

<!-- ====== HEADER + MENU (sesuai screenshot) ====== -->
<section class="hero container sikat-hero">
  <img class="brand-logo hero-logo" src="<?= e(asset_url('asset/logo-sikat-baru-140.png')) ?>" alt="SIKAT">
  <h2 class="title mt-1 hero-title">Dashboard SIKAT</h2>
  <p class="subtitle hero-subtitle">Ringkasan eksekutif SKI, kepatuhan internal, risiko, tindak lanjut, dan pelaporan Poltekkes Ternate.</p>
  <p class="mt-1 fw-semibold hero-greeting">Halo, <?= e($_SESSION['user']['nama'] ?? 'Pengguna') ?>!</p>

</section>
<div class="container">
  <div class="divider hero-divider"></div>
</div>
<!-- ====== /HEADER + MENU ====== -->

<main class="container mb-5">

  <!-- KPI SKI -->
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
      <div class="kpi ski">
        <small>Total Review Internal</small>
        <h3 class="m-0"><?= number_format($ski['total_review']) ?></h3>
        <div class="insight"><?= e($insights['review']) ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="kpi ski">
        <small>Rekomendasi Aktif</small>
        <h3 class="m-0"><?= number_format($ski['rekomendasi_aktif']) ?></h3>
        <div class="insight"><?= e($insights['rekomendasi']) ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="kpi ski">
        <small>TL Terlambat</small>
        <h3 class="m-0"><?= number_format($ski['tl_terlambat']) ?></h3>
        <div class="insight"><?= e($insights['terlambat']) ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="kpi ski">
        <small>Risiko Tinggi / Ekstrem</small>
        <h3 class="m-0"><?= number_format($ski['risiko_tinggi']) ?></h3>
        <div class="insight"><?= e($insights['risiko']) ?></div>
      </div>
    </div>
  </div>

  <!-- KPI Pelaporan -->
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="kpi report"><small>Total Pengaduan</small><h3 class="m-0"><?= number_format($sum['total']) ?></h3><div class="insight"><?= e($insights['pelaporan']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi report"><small>Pengaduan Masuk</small><h3 class="m-0"><?= number_format($sum['masuk']) ?></h3><div class="insight"><?= $sum['masuk'] > 0 ? 'Perlu triase awal' : 'Tidak ada pengaduan baru' ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi report"><small>Tahap Berjalan</small><h3 class="m-0"><?= number_format($sum['proses']) ?></h3><div class="insight"><?= $sum['proses'] > 0 ? 'Pantau progres penyelesaian' : 'Tidak ada proses aktif' ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi report"><small>Arsip</small><h3 class="m-0"><?= number_format($sum['arsip']) ?></h3><div class="insight">Kembali ke pelapor: <?= number_format($sum['kembali']) ?></div></div></div>
  </div>

  <div class="row g-3">
    <!-- Tren Bulanan -->
    <div class="col-lg-8">
      <div class="card-soft p-3 chart-box">
        <h6 class="section-title">Tren Laporan 12 Bulan Terakhir</h6>
        <canvas id="trend" height="120"></canvas>
      </div>
    </div>

    <!-- Distribusi Status -->
    <div class="col-lg-4">
      <div class="card-soft p-3 status-chart-box">
        <div class="w-100">
          <h6 class="section-title">Distribusi Status</h6>
          <canvas id="statusPie"></canvas>
        </div>
      </div>
    </div>

    <!-- Top Kategori -->
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <h6 class="section-title">Top 5 Kategori Pelaporan</h6>
        <?php $maxCat = !empty($cat_counts) ? max($cat_counts) : 0; ?>
        <?php if ($maxCat <= 0): ?>
          <div class="empty-state">Belum ada data kategori.<div class="hint">Kategori akan muncul setelah laporan masuk.</div></div>
        <?php else: ?>
          <div class="category-list">
            <?php foreach ($cat_labels as $idx => $label): $count = (int)($cat_counts[$idx] ?? 0); $pct = $maxCat > 0 ? max(4, (int)round(($count / $maxCat) * 100)) : 0; ?>
              <div class="category-row">
                <div class="category-label" title="<?= e($label) ?>"><?= e($label ?: 'Tanpa kategori') ?></div>
                <div class="category-track" aria-hidden="true"><div class="category-fill" style="width:<?= $pct ?>%"></div></div>
                <div class="category-count"><?= number_format($count) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Aktivitas Penting -->
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <h6 class="section-title">Deadline Tindak Lanjut / Aktivitas Penting</h6>
        <?php if (empty($deadlineItems)): ?>
          <div class="empty-state">Belum ada deadline tindak lanjut aktif.<div class="hint">Aktivitas review dan rekomendasi akan tampil di sini.</div></div>
        <?php else: ?>
          <div class="activity-list">
            <?php foreach ($deadlineItems as $item): ?>
              <div class="activity-item">
                <div class="d-flex justify-content-between gap-2">
                  <div class="activity-title"><?= e($item['title'] ?: 'Aktivitas tindak lanjut') ?></div>
                  <span class="badge bg-<?= dashboard_status_badge_class($item['status']) ?> align-self-start"><?= e($item['status']) ?></span>
                </div>
                <div class="activity-meta"><?= e($item['meta']) ?> · <a class="detail-link" href="<?= e($item['href']) ?>">Buka Review</a></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 5 Terbaru -->
    <div class="col-12">
      <div class="card-soft p-3">
        <h6 class="section-title">5 Laporan Terbaru</h6>
        <div class="table-responsive">
          <table class="table latest-table align-middle mb-0">
            <thead><tr><th class="col-code">Kode</th><th>Kategori</th><th>Status</th><th>Waktu</th><th class="col-summary">Ringkas</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if(empty($latest)): ?>
                <tr><td colspan="6"><div class="empty-state">Belum ada laporan terbaru.<div class="hint">Laporan akan tampil setelah pelaporan diterima.</div></div></td></tr>
              <?php else: foreach($latest as $r): ?>
                <tr>
                  <td><a href="<?= e(route_url('pelaporan/' . rawurlencode((string)$r['kode']))) ?>" class="text-decoration-none"><?= e($r['kode']) ?></a></td>
                  <td><?= e($r['kategori']) ?></td>
                  <td>
                    <?php $st=$r['status']; $cls=dashboard_status_badge_class((string)$st); ?>
                    <span class="badge bg-<?= $cls ?>"><?= e($st) ?></span>
                  </td>
                  <td><?= e(dashboard_datetime_short($r['created_at'] ?? '')) ?></td>
                  <td><div class="summary-text"><?= nl2br(e($r['isi_short'] ?: 'Tidak ada ringkasan')) ?></div></td>
                  <td><a href="<?= e(route_url('pelaporan/' . rawurlencode((string)$r['kode']))) ?>" class="detail-link">Lihat Detail</a></td>
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

</script>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>
