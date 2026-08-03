<?php

// --- MIME fallback helper (server-safe) ---
if (!function_exists("sikat_guess_mime")) {
    function sikat_guess_mime(string $path): string {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            "pdf"  => "application/pdf",
            "doc"  => "application/msword",
            "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "xls"  => "application/vnd.ms-excel",
            "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "ppt"  => "application/vnd.ms-powerpoint",
            "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "txt"  => "text/plain",
            "csv"  => "text/csv",
            "jpg"  => "image/jpeg",
            "jpeg" => "image/jpeg",
            "png"  => "image/png",
            "gif"  => "image/gif",
            "webp" => "image/webp",
            "zip"  => "application/zip",
            "rar"  => "application/vnd.rar"
        ];
        return $map[$ext] ?? "application/octet-stream";
    }
}
// --- end helper ---
require_once __DIR__ . '/bootstrap.php';
// Watermark: Ded Polkester

/* ====== BOOTSTRAP DB ====== */
$__base = __DIR__;
$__candidates = [
  $__base.'/db.php', $__base.'/ski_new/db.php', $__base.'/db/db.php',
  dirname($__base).'/db.php', $__base.'/includes/db.php'
];
$__found=false; foreach($__candidates as $__p){ if(is_file($__p)){ require_once $__p; $__found=true; break; } }
if(!$__found){ http_response_code(500); die("db.php tidak ditemukan. Coba salah satu path: \n - ".implode("\n - ", $__candidates)); }
if(!isset($conn) || !($conn instanceof mysqli)){ http_response_code(500); die("Koneksi DB \$conn tidak tersedia."); }
$conn->set_charset('utf8mb4');
date_default_timezone_set('Asia/Jayapura'); // WIT
require_once __DIR__.'/pelaporan_helpers.php';
require_once __DIR__.'/chr_helpers.php';
require_once __DIR__.'/chr_form_renderer.php';
require_once __DIR__.'/early_warning_helpers.php';

/* ====== AUTHZ ====== */
if (empty($_SESSION['user'])) { header('Location: ' . route_url('login', ['open' => 'login'])); exit; }

if (!function_exists('role_slug')) {
  function role_slug(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
  }
}

$roleRaw = $_SESSION['user']['peran'] ?? 'user';
$roleLower = strtolower((string)$roleRaw);
$role = role_slug($roleLower);
$actor = function_exists('pelaporan_actor_group') ? pelaporan_actor_group($_SESSION['user']) : $role;

// ambil akses_review per-user (cache ke session)
$akses_review = (int)($_SESSION['user']['akses_review'] ?? 0);
$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) { $uid = (int)($_SESSION['user']['user_id'] ?? 0); }

if ($akses_review === 0 && $uid > 0) {
  if ($st = $conn->prepare("SELECT akses_review FROM pengguna WHERE id=? LIMIT 1")) {
    $st->bind_param("i", $uid);
    if ($st->execute()) {
      $res = $st->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $akses_review = (int)($row['akses_review'] ?? 0);
      $_SESSION['user']['akses_review'] = $akses_review;
    }
    $st->close();
  }
}

// aturan: admin/auditor/kepala_ski/direktur selalu boleh, selain itu wajib akses_review=1
$can_access_review =
  in_array($actor, ['admin','super_admin','superadmin','moderator','kepala_ski','direktur','auditor','auditor_ka','auditor_staff'], true)
  || ($akses_review === 1);

if (!$can_access_review) { http_response_code(403); die('Akses ditolak'); }

/* ====== HELPERS ====== */
if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash']=[];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf_token']).'">'; }
function csrf_ok($t){ return hash_equals($_SESSION['csrf_token'], (string)$t ?? ''); }
function flash($k,$v=null){ if($v!==null){ $_SESSION['flash'][$k]=$v; return; } $x=$_SESSION['flash'][$k]??null; unset($_SESSION['flash'][$k]); return $x; }
function ew_color(DateTime $due, int $warnThresholdDays = 2, int $progressBoost = 0): array {
  $today = new DateTime('today');
  $diff = (int)$today->diff($due)->format('%r%a');
  [$baseCode, $levelDesc] = early_warning_base_level($diff, $warnThresholdDays);
  $finalCode = early_warning_adjust_code($baseCode, $progressBoost);
  $levelName = early_warning_label($finalCode, $warnThresholdDays);
  $levelColor = early_warning_color($finalCode);
  return [$levelName, $levelColor, $levelDesc, $diff];
}
function ew_color_from_diff(int $diff, int $warnThresholdDays = 2, int $progressBoost = 0): array {
  [$baseCode, $levelDesc] = early_warning_base_level($diff, $warnThresholdDays);
  $finalCode = early_warning_adjust_code($baseCode, $progressBoost);
  $levelName = early_warning_label($finalCode, $warnThresholdDays);
  $levelColor = early_warning_color($finalCode);
  return [$levelName, $levelColor, $levelDesc, $diff];
}
if (!function_exists('unit_slug')) {
  function unit_slug(string $value): string {
    $slug = strtolower($value);
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    return trim($slug, '_');
  }
}
if (!function_exists('unit_slug_compact')) {
  function unit_slug_compact(string $value): string {
    return str_replace('_', '', unit_slug($value));
  }
}
if (!function_exists('review_table_column_exists')) {
  function review_table_column_exists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table.'.'.$column;
    if (array_key_exists($key, $cache)) { return $cache[$key]; }
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $ok = false;
    if ($rs = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'")) {
      $ok = $rs->num_rows > 0;
      $rs->free();
    }
    $cache[$key] = $ok;
    return $ok;
  }
}
if (!function_exists('review_template_options')) {
  function review_template_options(): array {
    $options = [];
    if (!function_exists('chr_template_registry')) { return $options; }
    foreach (chr_template_registry() as $code => $template) {
      if (($template['status'] ?? 'active') === 'inactive') { continue; }
      $options[(string)$code] = [
        'code' => (string)$code,
        'name' => function_exists('chr_template_display_name') ? chr_template_display_name((string)$code) : (string)($template['name'] ?? $code),
        'version' => function_exists('chr_template_version') ? chr_template_version((string)$code) : max(1, (int)($template['version'] ?? 1)),
      ];
    }
    return $options;
  }
}
if (!function_exists('review_template_code_from_name')) {
  function review_template_code_from_name(string $name): string {
    $name = trim($name);
    if ($name === '' || !function_exists('chr_template_registry') || !function_exists('chr_template_normalize_name')) {
      return '';
    }
    $target = chr_template_normalize_name($name);
    foreach (chr_template_registry() as $code => $template) {
      $aliases = $template['aliases'] ?? [];
      $aliases[] = $template['name'] ?? '';
      foreach ($aliases as $alias) {
        if (chr_template_normalize_name((string)$alias) === $target) {
          return (string)$code;
        }
      }
    }
    return '';
  }
}
if (!function_exists('review_resolve_template_for_jenis')) {
  function review_resolve_template_for_jenis(mysqli $conn, int $jenisId): array {
    if ($jenisId < 1) { return ['', 1, '']; }
    $hasTemplateCode = review_table_column_exists($conn, 'jenis_reviu', 'template_code');
    $hasTemplateVersion = review_table_column_exists($conn, 'jenis_reviu', 'template_version');
    $cols = ['nama'];
    if ($hasTemplateCode) { $cols[] = 'template_code'; }
    if ($hasTemplateVersion) { $cols[] = 'template_version'; }
    $stmt = $conn->prepare("SELECT ".implode(',', $cols)." FROM jenis_reviu WHERE id=? AND aktif=1 LIMIT 1");
    if (!$stmt) { return ['', 1, '']; }
    $stmt->bind_param("i", $jenisId);
    $row = null;
    if ($stmt->execute()) {
      $row = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
    if (!$row) { return ['', 1, '']; }
    $code = trim((string)($row['template_code'] ?? ''));
    if ($code === '' || !chr_template_get($code)) {
      $code = review_template_code_from_name((string)($row['nama'] ?? ''));
    }
    $version = max(1, (int)($row['template_version'] ?? (function_exists('chr_template_version') && $code !== '' ? chr_template_version($code) : 1)));
    if ($code !== '' && function_exists('chr_template_version')) {
      $version = chr_template_version($code);
    }
    return [$code, $version, (string)($row['nama'] ?? '')];
  }
}

/* ====== RBAC HELPERS ====== */
if (!function_exists('user_id')) {
  function user_id(){ return $_SESSION['user']['id'] ?? null; }
}
if (!function_exists('user_email')) {
  function user_email(){ return $_SESSION['user']['email'] ?? null; }
}
if (!function_exists('user_role')) {
  function user_role(){
    return role_slug($_SESSION['user']['peran'] ?? 'user');
  }
}
if (!function_exists('is_admin_like')) {
  function is_admin_like(string $r=null){
    $r = $r ? role_slug($r) : user_role();
    return in_array($r, ['admin','super_admin','superadmin','moderator'], true);
  }
}
if (!function_exists('is_ski_admin')) {
  function is_ski_admin(string $r=null){
    $r = $r ? role_slug($r) : user_role();
    return in_array($r, ['admin','super_admin','superadmin'], true);
  }
}
if (!function_exists('is_auditor')) {
  function is_auditor(string $role = null){
    $r = $role ? role_slug($role) : user_role();
    return $r === 'auditor' || strpos($r, 'auditor_') === 0;
  }
}
if (!function_exists('is_auditee')) {
  function is_auditee(){
    $role = user_role();
    return $role === 'auditee' || strpos($role, 'auditee') === 0;
  }
}
if (!function_exists('is_director_like')) {
  function is_director_like(string $role = null){
    $r = $role ? role_slug($role) : user_role();
    if ($r === 'direktur' || $r === 'auditee_direktur') { return true; }
    return strpos($r, 'direktur') !== false;
  }
}
if (!function_exists('ensure_laporan_signature_schema')) {
  function ensure_laporan_signature_schema(mysqli $conn): bool {
    static $checked = false;
    static $resultCache = true;
    if ($checked) { return $resultCache; }
    $checked = true;
    $resultCache = true;
    $resTable = $conn->query("SHOW TABLES LIKE 'reviu_laporan'");
    if (!$resTable || $resTable->num_rows < 1) { $resultCache = false; return false; }
    $resTable->free();
    $columns = [
      'ttd_kepala_nama' => "ADD COLUMN ttd_kepala_nama VARCHAR(150) NULL AFTER lampiran",
      'ttd_kepala_tanggal' => "ADD COLUMN ttd_kepala_tanggal DATE NULL AFTER ttd_kepala_nama",
      'ttd_kepala_file' => "ADD COLUMN ttd_kepala_file VARCHAR(255) NULL AFTER ttd_kepala_tanggal",
    ];
    foreach ($columns as $col => $alter) {
      $check = $conn->query("SHOW COLUMNS FROM reviu_laporan LIKE '".$conn->real_escape_string($col)."'");
      $exists = $check && $check->num_rows > 0;
      if ($check) { $check->free(); }
      if ($exists) { continue; }
      if (!$conn->query("ALTER TABLE reviu_laporan ".$alter)) {
        error_log('ensure_laporan_signature_schema failed: '.$conn->error);
        $resultCache = false;
        return false;
      }
    }
    return true;
  }
}
if (!function_exists('ensure_review_comments_schema')) {
  function ensure_review_comments_schema(mysqli $conn): bool {
    static $checked = false;
    if ($checked) { return true; }
    $checked = true;
    $sql = "CREATE TABLE IF NOT EXISTS review_comments (
      id INT AUTO_INCREMENT PRIMARY KEY,
      review_id INT NOT NULL,
      user_id INT NULL,
      username VARCHAR(80) NULL,
      user_name VARCHAR(150) NULL,
      parent_id INT NULL,
      body TEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      INDEX idx_review_id (review_id),
      INDEX idx_parent_id (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return (bool)$conn->query($sql);
  }
}
if (!function_exists('is_assigned')) {
  /**
   * Cek apakah user saat ini termasuk tim reviu_id (opsional filter role AUDITOR/AUDITEE).
   * Perlu $GLOBALS['conn'].
   */
  function is_assigned(int $reviu_id, ?string $roleFilter=null): bool {
    global $conn;
    $uid = (int)user_id();
    $em  = trim((string)user_email());
    $uname = trim((string)($_SESSION['user']['nama'] ?? ''));
    if (!$reviu_id) return false;

    $conds = [];
    $types = 'i';
    $params = [$reviu_id];
    if ($uid > 0) { $conds[] = 'user_id=?'; $types .= 'i'; $params[] = $uid; }
    if ($em !== '') { $conds[] = "(email<>'' AND LOWER(email)=LOWER(?))"; $types .= 's'; $params[] = $em; }
    if ($uname !== '') { $conds[] = 'LOWER(nama)=LOWER(?)'; $types .= 's'; $params[] = $uname; }
    if (!$conds) { return false; }

    $sql = "SELECT 1 FROM reviu_penugasan WHERE reviu_id=? AND (".implode(' OR ', $conds).")";
    if ($roleFilter) { $sql .= " AND role=?"; $types .= 's'; $params[] = $roleFilter; }
    $st = $conn->prepare($sql);
    if (!$st) { return false; }
    $st->bind_param($types, ...$params);
    $st->execute();
    return (bool)$st->get_result()->fetch_row();
  }
}
if (!function_exists('require_assigned_or_admin')) {
  /** Guard: hentikan request jika bukan admin & bukan tim reviu */
  function require_assigned_or_admin(int $reviu_id, ?string $roleFilter=null){
    if (is_ski_admin()) { return; }
    if (!is_assigned($reviu_id, $roleFilter)) {
      http_response_code(403); die('Akses ditolak: tidak termasuk tim pada reviu ini.');
    }
  }
}

/* ====== KONFIG UPLOAD ====== */
$ALLOWED_MIME = [
  'application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
  'application/msword'=>'doc',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
  'application/vnd.ms-excel'=>'xls',
];
$MAX_UPLOAD = 8*1024*1024; // 8MB

/* ====== DATA USER (AUDITOR/AUDITEE) ====== */
$userSourceTable = null;
$userSourceHasEmail = false;
if ($tmpRes = $conn->query("SHOW TABLES LIKE 'pengguna'")) {
  if ($tmpRes->num_rows > 0) { $userSourceTable = 'pengguna'; }
  $tmpRes->free();
}
if (!$userSourceTable) {
  if ($tmpRes = $conn->query("SHOW TABLES LIKE 'users'")) {
    if ($tmpRes->num_rows > 0) { $userSourceTable = 'users'; }
    $tmpRes->free();
  }
}
if ($userSourceTable === 'pengguna') {
  if ($tmpRes = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'email'")) {
    $userSourceHasEmail = $tmpRes->num_rows > 0;
    $tmpRes->free();
  }
}

/* ====== KOMENTAR DOKUMEN ====== */
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '') === 'comment_add') && csrf_ok($_POST['csrf'] ?? '')) {
  $rid = (int)($_POST['reviu_id'] ?? 0);
  $parentId = (int)($_POST['parent_id'] ?? 0);
  $body = trim((string)($_POST['body'] ?? ''));
  $redirect = $_SERVER['PHP_SELF'].'?tab=dok'.($rid ? '&rid='.$rid : '').'#komentar';
  if ($rid < 1) { flash('err','Data reviu tidak valid.'); header('Location: '.$redirect); exit; }
  if ($body === '') { flash('err','Komentar tidak boleh kosong.'); header('Location: '.$redirect); exit; }
  $bodyLen = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
  if ($bodyLen > 2000) { flash('err','Komentar maksimal 2000 karakter.'); header('Location: '.$redirect); exit; }
  if (!is_assigned($rid)) {
    flash('err','Anda tidak termasuk tim penugasan, sehingga tidak dapat berkomentar.');
    header('Location: '.$redirect); exit;
  }
  if (!ensure_review_comments_schema($conn)) {
    flash('err','Gagal menyiapkan tabel komentar.');
    header('Location: '.$redirect); exit;
  }
  if ($parentId > 0) {
    if ($pst = $conn->prepare("SELECT review_id FROM review_comments WHERE id=?")) {
      $pst->bind_param("i", $parentId);
      $pst->execute();
      $row = $pst->get_result()->fetch_assoc();
      $pst->close();
      if (!$row || (int)$row['review_id'] !== $rid) {
        flash('err','Reply tidak valid.');
        header('Location: '.$redirect); exit;
      }
    }
  }
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  $username = trim((string)($_SESSION['user']['username'] ?? ''));
  $userName = trim((string)($_SESSION['user']['nama'] ?? ''));
  $parentParam = $parentId > 0 ? $parentId : null;
  $stmt = $conn->prepare("INSERT INTO review_comments (review_id, user_id, username, user_name, parent_id, body, created_at) VALUES (?,?,?,?,?, ?, NOW())");
  if (!$stmt) {
    flash('err','Gagal menyimpan komentar.');
    header('Location: '.$redirect); exit;
  }
  $stmt->bind_param("iissis", $rid, $uid, $username, $userName, $parentParam, $body);
  $ok = $stmt->execute();
  $stmt->close();
  flash($ok ? 'ok' : 'err', $ok ? 'Komentar ditambahkan.' : 'Gagal menambahkan komentar.');
  header('Location: '.$redirect); exit;
}

/* =========================================================
   ================ ACTIONS (POST HANDLERS) ================
   ========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && csrf_ok($_POST['csrf'] ?? '')) {
  $act = $_POST['action'];

  /* ---- MASTER: UNIT ---- */
  if ($act==='unit_create' && in_array($role,['admin','super_admin','superadmin'])) {
    $nama=trim($_POST['nama']??'');
    if($nama===''){ flash('err','Nama unit wajib diisi'); }
    else{
      $st=$conn->prepare("INSERT INTO unit_kerja (nama,aktif) VALUES (?,1)");
      $st->bind_param("s",$nama);
      $ok=$st->execute();
      flash($ok?'ok':'err',$ok?'Unit ditambahkan.':'Gagal menambah unit.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=master'); exit;
  }

  /* ---- MASTER: JENIS ---- */
  if ($act==='jenis_create' && in_array($role,['admin','super_admin','superadmin'])) {
    $nama=trim($_POST['nama']??''); $desk=trim($_POST['deskripsi']??'');
    $templateCode = trim((string)($_POST['template_code'] ?? ''));
    $templateVersion = ($templateCode !== '' && chr_template_get($templateCode)) ? chr_template_version($templateCode) : 1;
    if($nama===''){ flash('err','Nama jenis wajib diisi'); }
    elseif($templateCode !== '' && !chr_template_get($templateCode)){ flash('err','Template CHR tidak valid.'); }
    else{
      $hasTemplateCols = review_table_column_exists($conn, 'jenis_reviu', 'template_code') && review_table_column_exists($conn, 'jenis_reviu', 'template_version');
      if ($hasTemplateCols) {
        $st=$conn->prepare("INSERT INTO jenis_reviu (nama,deskripsi,template_code,template_version,aktif) VALUES (?,?,?,?,1)");
        $st->bind_param("sssi",$nama,$desk,$templateCode,$templateVersion);
      } else {
        $st=$conn->prepare("INSERT INTO jenis_reviu (nama,deskripsi,aktif) VALUES (?,?,1)");
        $st->bind_param("ss",$nama,$desk);
      }
      $ok=$st->execute();
      flash($ok?'ok':'err',$ok?'Jenis ditambahkan.':'Gagal menambah jenis.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=master'); exit;
  }

  if ($act==='jenis_template_update' && in_array($role,['admin','super_admin','superadmin'])) {
    $jenisId = (int)($_POST['jenis_id'] ?? 0);
    $templateCode = trim((string)($_POST['template_code'] ?? ''));
    if (!review_table_column_exists($conn, 'jenis_reviu', 'template_code') || !review_table_column_exists($conn, 'jenis_reviu', 'template_version')) {
      flash('err','Kolom pemetaan template belum tersedia. Jalankan migration review template mapping.');
    } elseif ($jenisId < 1) {
      flash('err','Jenis reviu tidak valid.');
    } elseif ($templateCode === '' || !chr_template_get($templateCode)) {
      flash('err','Pilih template CHR yang valid.');
    } else {
      $templateVersion = chr_template_version($templateCode);
      $st = $conn->prepare("UPDATE jenis_reviu SET template_code=?, template_version=? WHERE id=? LIMIT 1");
      $st->bind_param("sii", $templateCode, $templateVersion, $jenisId);
      $ok = $st->execute();
      $st->close();
      flash($ok ? 'ok' : 'err', $ok ? 'Pemetaan template jenis reviu diperbarui.' : 'Gagal memperbarui pemetaan template.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=master'); exit;
  }

  /* ---- JADWAL: BUAT ---- */
  if ($act==='reviu_create' && in_array($role,['admin','super_admin','superadmin','moderator'])) {
    $namaKegiatan=trim($_POST['nama_kegiatan']??'');
    $jenis=(int)($_POST['jenis_id']??0);
    $unit=(int)($_POST['unit_id']??0);
    $mulai=$_POST['mulai']??''; $selesai=$_POST['selesai']??''; $deadline=$_POST['deadline']??'';
    [$templateCode, $templateVersion] = review_resolve_template_for_jenis($conn, $jenis);
    $hasReviewTemplateCols = review_table_column_exists($conn, 'reviu', 'nama_kegiatan')
      && review_table_column_exists($conn, 'reviu', 'template_code')
      && review_table_column_exists($conn, 'reviu', 'template_version');
    if($namaKegiatan===''||!$jenis||!$unit||!$mulai||!$selesai||!$deadline){
      flash('err','Lengkapi nama kegiatan, jenis reviu, unit, periode, dan deadline.');
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
      flash('err','Format tanggal tidak valid.');
    } elseif (strtotime($mulai) > strtotime($selesai)) {
      flash('err','Tanggal mulai tidak boleh melebihi tanggal selesai.');
    } elseif (!$hasReviewTemplateCols) {
      flash('err','Kolom nama kegiatan/template belum tersedia. Jalankan migration review template mapping terlebih dahulu.');
    } elseif ($templateCode === '' || !chr_template_get($templateCode)) {
      flash('err','Jenis reviu belum memiliki template CHR. Silakan hubungi administrator atau perbaiki pemetaan jenis reviu.');
    } else {
      $ym=date('Ym');
      $prefix = sprintf('RV-%s-', $ym);
      $seq = 1;
      if ($lastStmt = $conn->prepare("SELECT kode FROM reviu WHERE kode LIKE ? ORDER BY kode DESC LIMIT 1")) {
        $pattern = $prefix.'%';
        $lastStmt->bind_param("s", $pattern);
        if ($lastStmt->execute()) {
          $lastRes = $lastStmt->get_result()->fetch_assoc();
          if ($lastRes && isset($lastRes['kode'])) {
            $lastParts = explode('-', $lastRes['kode']);
            $lastNum = (int)array_pop($lastParts);
            if ($lastNum > 0) { $seq = $lastNum + 1; }
          }
        }
        $lastStmt->close();
      }

      $created_by = $_SESSION['user']['id'] ?? null;
      $ok = false;
      $errMsg = '';
      for ($tries=0; $tries<5; $tries++) {
        $kode = sprintf('%s%03d', $prefix, $seq);
        $st=$conn->prepare("INSERT INTO reviu (kode,nama_kegiatan,jenis_id,unit_id,periode_mulai,periode_selesai,tgl_deadline,template_code,template_version,status,created_by) VALUES (?,?,?,?,?,?,?,?,?, 'Terjadwal', ?)");
        $st->bind_param("ssiissssii",$kode,$namaKegiatan,$jenis,$unit,$mulai,$selesai,$deadline,$templateCode,$templateVersion,$created_by);
        $ok=$st->execute();
        if ($ok) { $st->close(); break; }
        $errMsg = (string)$st->error;
        $st->close();
        if (strpos($errMsg, 'Duplicate entry') !== false) {
          $seq++;
          continue;
        }
        break;
      }

      if ($ok) {
        flash('ok','Jadwal dibuat: '.$kode);
      } else {
        flash('err','Gagal membuat jadwal: '.e($errMsg ?: 'Unknown error'));
      }
      // (Opsional) kirim notifikasi via mailer.php di sini
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
  }

  /* ---- JADWAL: MAJUKAN STATUS ---- */
  if ($act==='reviu_step' && (in_array($role,['admin','super_admin','superadmin','moderator'], true) || is_auditor($role))) {
    $id=(int)($_POST['id']??0); $to=$_POST['to']??'';
    $allowed=['Terjadwal','Pelaksanaan','CHR','Rekomendasi','Laporan','Verifikasi','Selesai','Dibatalkan'];
    if(!$id || !in_array($to,$allowed)){ flash('err','Data tidak valid'); }
    else{
      $st=$conn->prepare("UPDATE reviu SET status=? WHERE id=?");
      $st->bind_param("si",$to,$id);
      $ok=$st->execute();
      flash($ok?'ok':'err',$ok?'Status diperbarui menjadi '.$to:'Gagal memperbarui status');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
  }

  if ($act==='reviu_deadline_update' && (in_array($role,['admin','super_admin','superadmin','moderator'], true) || is_auditor($role))) {
    $id = (int)($_POST['id'] ?? 0);
    $deadline = trim((string)($_POST['deadline'] ?? ''));
    if (!$id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
      flash('err','Deadline baru tidak valid.');
    } else {
      $st = $conn->prepare("UPDATE reviu SET tgl_deadline=? WHERE id=?");
      $st->bind_param("si", $deadline, $id);
      $ok = $st->execute();
      flash($ok ? 'ok' : 'err', $ok ? 'Deadline berhasil diperbarui menjadi '.$deadline : 'Gagal memperbarui deadline.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
  }

  if ($act==='reviu_note_update' && (in_array($role,['admin','super_admin','superadmin','moderator'], true) || is_auditor($role))) {
    $id = (int)($_POST['id'] ?? 0);
    $catatan = trim((string)($_POST['catatan'] ?? ''));
    if (function_exists('mb_substr')) {
      $catatan = mb_substr($catatan, 0, 5000);
    } else {
      $catatan = substr($catatan, 0, 5000);
    }
    if (!$id) {
      flash('err','Data reviu tidak valid.');
    } else {
      $st = $conn->prepare("UPDATE reviu SET catatan=? WHERE id=?");
      $st->bind_param("si", $catatan, $id);
      $ok = $st->execute();
      flash($ok ? 'ok' : 'err', $ok ? 'Catatan status berhasil diperbarui.' : 'Gagal memperbarui catatan status.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
  }

  if ($act==='reviu_status_update' && (in_array($role,['admin','super_admin','superadmin','moderator'], true) || is_auditor($role))) {
    $id = (int)($_POST['id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $catatan = trim((string)($_POST['catatan'] ?? ''));
    $allowed = ['Selesai', 'Tidak Selesai'];
    if (!$id || !in_array($status, $allowed, true)) {
      flash('err','Status review tidak valid.');
    } else {
      $st = $conn->prepare("UPDATE reviu SET status=?, catatan=? WHERE id=?");
      $st->bind_param("ssi", $status, $catatan, $id);
      $ok = $st->execute();
      flash($ok ? 'ok' : 'err', $ok ? 'Status review diperbarui menjadi '.$status : 'Gagal memperbarui status review.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
  }

  if ($act==='reviu_delete' && in_array($role,['admin','super_admin','superadmin','moderator'])) {
    $rid = (int)($_POST['reviu_id'] ?? 0);
    if (!$rid) {
      flash('err','Data reviu tidak valid.');
      header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
    }

    $docFiles = [];
    if ($st = $conn->prepare("SELECT file_path FROM reviu_dokumen WHERE reviu_id=?")) {
      $st->bind_param("i", $rid);
      $st->execute();
      $res = $st->get_result();
      while ($row = $res->fetch_assoc()) {
        if (!empty($row['file_path'])) { $docFiles[] = $row['file_path']; }
      }
    }
    if ($st = $conn->prepare("SELECT lampiran FROM reviu_laporan WHERE reviu_id=? AND lampiran<>''")) {
      $st->bind_param("i", $rid);
      $st->execute();
      $res = $st->get_result();
      while ($row = $res->fetch_assoc()) {
        if (!empty($row['lampiran'])) { $docFiles[] = $row['lampiran']; }
      }
    }

    $conn->begin_transaction();
    $okDelete = true;
    try {
      foreach (['reviu_penugasan','reviu_dokumen','reviu_chr','reviu_verifikasi','reviu_monitoring','reviu_laporan','reviu_log','reviu_early_warning'] as $tbl) {
        if ($stmt = $conn->prepare("DELETE FROM $tbl WHERE reviu_id=?")) {
          $stmt->bind_param("i", $rid);
          $stmt->execute();
        }
      }
      if ($stmt = $conn->prepare("DELETE FROM reviu WHERE id=? LIMIT 1")) {
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
          throw new RuntimeException('Data reviu tidak ditemukan.');
        }
      } else {
        throw new RuntimeException('Gagal menyiapkan penghapusan jadwal.');
      }
      $conn->commit();
    } catch (Throwable $e) {
      $conn->rollback();
      $okDelete = false;
      flash('err','Gagal menghapus jadwal: '.$e->getMessage());
    }

    if ($okDelete) {
      $baseUploads = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reviu');
      foreach ($docFiles as $rel) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        if (is_file($path)) {
          $real = realpath($path);
          if ($real && $baseUploads && strpos($real, $baseUploads) === 0) {
            @unlink($real);
          }
        }
      }
      if ($baseUploads) {
        $targetDir = $baseUploads . DIRECTORY_SEPARATOR . $rid;
        if (is_dir($targetDir)) {
          $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($targetDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
          );
          foreach ($iterator as $entry) {
            if ($entry->isDir()) { @rmdir($entry->getPathname()); }
            else { @unlink($entry->getPathname()); }
          }
          @rmdir($targetDir);
        }
      }
      flash('ok','Jadwal reviu beserta lampiran telah dihapus.');
    }

    header('Location: '.$_SERVER['PHP_SELF'].'?tab=jadwal'); exit;
  }

  /* ---- PENUGASAN: TAMBAH ---- */
  if ($act==='assign_add' && (in_array($role, ['admin','super_admin','superadmin','moderator'], true) || is_auditor($role))) {
    $rid = (int)($_POST['reviu_id'] ?? 0);
    $rrole = $_POST['rrole'] ?? 'AUDITOR'; // AUDITOR / AUDITEE
    $user_id = (int)($_POST['user_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if (!$rid || !in_array($rrole, ['AUDITOR','AUDITEE'], true)) {
      flash('err','Data penugasan tidak valid.');
      header('Location: '.$_SERVER['PHP_SELF'].'?tab=asg&rid='.$rid); exit;
    }
    // Isi otomatis nama/email jika user dipilih dari tabel pengguna/users
    if ($user_id && $userSourceTable) {
      if ($userSourceTable === 'pengguna') {
        $columns = "nama, username, peran".($userSourceHasEmail ? ", email" : "");
        if ($uq = $conn->prepare("SELECT $columns FROM pengguna WHERE id=? LIMIT 1")) {
          $uq->bind_param("i", $user_id);
          $uq->execute();
          if ($urow = $uq->get_result()->fetch_assoc()) {
            if ($nama === '') {
              $nama = trim((string)($urow['nama'] ?? ''));
              if ($nama === '') { $nama = trim((string)($urow['username'] ?? '')); }
            }
            if ($email === '' && $userSourceHasEmail && isset($urow['email'])) {
              $email = trim((string)$urow['email']);
            }
          }
        }
      } elseif ($uq = $conn->prepare("SELECT nama,email,peran FROM users WHERE id=? LIMIT 1")) {
        $uq->bind_param("i", $user_id);
        $uq->execute();
        if ($urow = $uq->get_result()->fetch_assoc()) {
          if ($nama === '') {
            $nama = trim((string)($urow['nama'] ?? ''));
          }
          if ($email === '' && isset($urow['email'])) {
            $email = trim((string)$urow['email']);
          }
        }
      }
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash('err','Email tidak valid.');
      header('Location: '.$_SERVER['PHP_SELF'].'?tab=asg&rid='.$rid); exit;
    }
    $st = $conn->prepare("INSERT INTO reviu_penugasan (reviu_id,role,user_id,nama,email) VALUES (?,?,?,?,?)");
    $st->bind_param("isiss",$rid,$rrole,$user_id,$nama,$email);
    $ok = $st->execute();
    flash($ok?'ok':'err',$ok?'Penugasan ditambahkan.':'Gagal menambahkan penugasan.');
    // (Opsional) kirim notif email ke $email
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=asg&rid='.$rid); exit;
  }

  /* ---- PENUGASAN: HAPUS ---- */
  if ($act==='assign_del' && in_array($role, ['admin','super_admin','superadmin','moderator'])) {
    $rid = (int)($_POST['reviu_id'] ?? 0);
    $id  = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $st = $conn->prepare("DELETE FROM reviu_penugasan WHERE id=?");
      $st->bind_param("i",$id);
      $ok = $st->execute();
      flash($ok?'ok':'err',$ok?'Penugasan dihapus.':'Gagal menghapus penugasan.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=asg&rid='.$rid); exit;
  }

  /* ---- CHR FORM: SIMPAN / WORKFLOW CHR SOP ---- */
  if (in_array($act, ['chr_sheet_save', 'chr_sop_submit', 'chr_sop_return', 'chr_sop_reopen'], true) && (in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role) || is_director_like($role))) {
    $rid = (int)($_POST['reviu_id'] ?? 0);
    $redirectUrl = $_SERVER['PHP_SELF'].'?tab=chr';
    if ($rid > 0) { $redirectUrl .= '&rid='.$rid; }

    if ($rid < 1) {
      flash('err','Data reviu tidak valid.');
      header('Location: '.$redirectUrl); exit;
    }

    $revRow = null;
    if ($stmt = $conn->prepare("SELECT r.id, r.kode, r.periode_selesai, u.nama unit_nama, j.nama jenis_nama FROM reviu r JOIN unit_kerja u ON u.id=r.unit_id JOIN jenis_reviu j ON j.id=r.jenis_id WHERE r.id=? LIMIT 1")) {
      $stmt->bind_param("i", $rid);
      if ($stmt->execute()) {
        $res = $stmt->get_result();
        $revRow = $res->fetch_assoc() ?: null;
        $res->free();
      }
      $stmt->close();
    }

    $currentSheet = chr_form_fetch($conn, $rid, $revRow);
    $templateCode = (string)($currentSheet['template_code'] ?? 'chr_legacy_laporan_keuangan');
    $template = chr_template_get($templateCode) ?: chr_template_get('chr_legacy_laporan_keuangan');
    $approvalDocName = function_exists('chr_template_display_name') ? chr_template_display_name($templateCode) : ($templateCode === 'chr_rkakl' ? 'CHR RKAKL' : ($templateCode === 'chr_manajemen_risiko' ? 'CHR Manajemen Risiko' : 'CHR SOP'));
    $useDynamicSave = function_exists('chr_template_uses_standard_approval')
      && chr_template_uses_standard_approval($templateCode)
      && (($template['renderer'] ?? 'legacy') === 'dynamic');
    if ($useDynamicSave) {
      $storedRow = chr_form_fetch_stored_row($conn, $rid);
      $storedData = [];
      if ($storedRow) {
        $decoded = json_decode((string)($storedRow['data_json'] ?? ''), true);
        if (is_array($decoded)) { $storedData = $decoded; }
      }
      $workflow = chr_sop_workflow($storedData);
      $workflowStatus = (string)($workflow['status'] ?? 'draft');
      $currentUserId = (int)($_SESSION['user']['id'] ?? 0);

      if ($act === 'chr_sop_return') {
        $note = trim((string)($_POST['return_note'] ?? ''));
        if ($note === '') {
          flash('err','Catatan pengembalian wajib diisi.');
          header('Location: '.$redirectUrl); exit;
        }
        if (!chr_sop_user_has_waiting_signature($storedData, $currentUserId)) {
          flash('err','Anda tidak memiliki hak untuk mengembalikan bagian ini.');
          header('Location: '.$redirectUrl); exit;
        }
        $payload = chr_sop_return_for_revision($storedData, $currentUserId, $note);
        $saved = chr_form_save($conn, $rid, $payload, $revRow);
        flash($saved ? 'ok' : 'err', $saved ? $approvalDocName.' dikembalikan untuk perbaikan.' : 'Gagal mengembalikan '.$approvalDocName.'.');
        header('Location: '.$redirectUrl); exit;
      }

      if ($act === 'chr_sop_reopen') {
        if ($workflowStatus !== 'returned') {
          flash('err',$approvalDocName.' hanya dapat dibuka untuk perbaikan setelah dikembalikan.');
          header('Location: '.$redirectUrl); exit;
        }
        $payload = chr_sop_reopen_draft($storedData, $currentUserId);
        $saved = chr_form_save($conn, $rid, $payload, $revRow);
        flash($saved ? 'ok' : 'err', $saved ? $approvalDocName.' dibuka kembali sebagai draft. Tanda tangan lama telah direset.' : 'Gagal membuka '.$approvalDocName.' untuk perbaikan.');
        header('Location: '.$redirectUrl); exit;
      }

      $rawInput = $_POST['chr_dynamic'] ?? [];
      if (!is_array($rawInput)) { $rawInput = []; }
      $lockedWorkflow = !in_array($workflowStatus, ['draft'], true);
      if ($lockedWorkflow) {
        if (!in_array($workflowStatus, ['waiting_signatures', 'partially_signed'], true)) {
          flash('err',$approvalDocName.' tidak dapat diubah pada status saat ini.');
          header('Location: '.$redirectUrl); exit;
        }
        $rawInput = ['pengesahan' => is_array($rawInput['pengesahan'] ?? null) ? $rawInput['pengesahan'] : []];
      }
      $chrSignerErrors = [];
      $requestMeta = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
      ];
      $payload = chr_dynamic_normalize_input($template ?: [], $rawInput, $storedData, $revRow, $conn, $chrSignerErrors, $currentUserId, $requestMeta, $lockedWorkflow);
      if ($chrSignerErrors) {
        flash('err', implode(' ', array_values(array_unique($chrSignerErrors))));
        header('Location: '.$redirectUrl); exit;
      }
      if ($act === 'chr_sop_submit') {
        if ($workflowStatus !== 'draft') {
          flash('err','Hanya draft '.$approvalDocName.' yang dapat diajukan untuk pengesahan.');
          header('Location: '.$redirectUrl); exit;
        }
        $submitErrors = [];
        $payload = chr_sop_submit_for_signatures($payload, $currentUserId, $submitErrors);
        if ($submitErrors) {
          flash('err', implode(' ', array_values(array_unique($submitErrors))));
          header('Location: '.$redirectUrl); exit;
        }
      }
    } else {
      if ($act !== 'chr_sheet_save') {
        flash('err','Aksi workflow hanya tersedia untuk template CHR dengan pengesahan standar.');
        header('Location: '.$redirectUrl); exit;
      }
      $base = chr_form_defaults($revRow);
      $rawInput = $_POST['chr_sheet'] ?? [];
      if (!is_array($rawInput)) { $rawInput = []; }
      $payload = chr_form_normalize_input($rawInput, $base);
    }
    $saved = chr_form_save($conn, $rid, $payload, $revRow);
    $successMessage = $act === 'chr_sop_submit' ? $approvalDocName.' diajukan untuk pengesahan.' : 'Template CHR tersimpan.';
    flash($saved ? 'ok' : 'err', $saved ? $successMessage : 'Gagal menyimpan template CHR.');
    header('Location: '.$redirectUrl); exit;
  }

  /* ---- CHR & REKOMENDASI: BUAT ---- */
  if ($act==='chr_create' && (in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role))) {
    $rid=(int)($_POST['reviu_id']??0);
    $des=trim($_POST['deskripsi']??'');
    $rek=trim($_POST['rekomendasi']??'');
    $due=$_POST['due_date']??'';
    if(!$rid||$des===''||$rek===''||$due===''){ flash('err','Lengkapi CHR'); }
    else{
      $st=$conn->prepare("INSERT INTO reviu_chr (reviu_id,deskripsi,rekomendasi,due_date) VALUES (?,?,?,?)");
      $st->bind_param("isss",$rid,$des,$rek,$due);
      $ok=$st->execute();
      flash($ok?'ok':'err',$ok?'CHR/rek ditambah.':'Gagal menambah CHR.');
      if($ok){
        if ($stmt = $conn->prepare("UPDATE reviu SET status='CHR' WHERE id=? AND status IN ('Terjadwal','Pelaksanaan')")) {
          $stmt->bind_param("i", $rid);
          $stmt->execute();
          $stmt->close();
        }
      }
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=chr&rid='.$rid); exit;
  }

  /* ---- CHR & REKOMENDASI: UBAH ---- */
  if ($act==='chr_update' && (in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role))) {
    $cid=(int)($_POST['chr_id']??0);
    $rid=(int)($_POST['reviu_id']??0);
    $des=trim($_POST['deskripsi']??'');
    $rek=trim($_POST['rekomendasi']??'');
    $due=$_POST['due_date']??'';
    if(!$cid||!$rid||$des===''||$rek===''||$due===''){ flash('err','Lengkapi CHR'); }
    else{
      $chk=$conn->prepare("SELECT id FROM reviu_chr WHERE id=? AND reviu_id=?");
      $chk->bind_param("ii",$cid,$rid);
      $chk->execute();
      if(!$chk->get_result()->fetch_row()){
        flash('err','CHR tidak ditemukan.');
      } else {
        $st=$conn->prepare("UPDATE reviu_chr SET deskripsi=?, rekomendasi=?, due_date=? WHERE id=? AND reviu_id=?");
        $st->bind_param("sssii",$des,$rek,$due,$cid,$rid);
        $ok=$st->execute();
        flash($ok?'ok':'err',$ok?'CHR diperbarui.':'Gagal memperbarui CHR.');
      }
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=chr&rid='.$rid); exit;
  }

  /* ---- CHR & REKOMENDASI: HAPUS ---- */
  if ($act==='chr_delete' && (in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role))) {
    $cid = (int)($_POST['chr_id'] ?? 0);
    $rid = (int)($_POST['reviu_id'] ?? 0);
    if ($cid && $rid) {
      if ($stmt = $conn->prepare("DELETE FROM reviu_chr WHERE id=? AND reviu_id=?")) {
        $stmt->bind_param("ii", $cid, $rid);
        $ok = $stmt->execute();
        flash($ok ? 'ok' : 'err', $ok ? 'CHR dihapus.' : 'Gagal menghapus CHR.');
        if ($ok) {
          $rowCount = 0;
          if ($countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM reviu_chr WHERE reviu_id=?")) {
            $countStmt->bind_param("i", $rid);
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $rowCount = $countRes ? (int)($countRes->fetch_assoc()['c'] ?? 0) : 0;
            $countStmt->close();
          }
          if ($rowCount < 1) {
            if ($stmt = $conn->prepare("UPDATE reviu SET status='Pelaksanaan' WHERE id=? AND status='CHR'")) {
              $stmt->bind_param("i", $rid);
              $stmt->execute();
              $stmt->close();
            }
          }
        }
      }
    } else {
      flash('err', 'Data CHR tidak lengkap.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=chr&rid='.$rid); exit;
  }

  /* ---- TL (AUDITEE): UPDATE ---- */
  if ($act==='tl_update' && (in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role) || is_auditee() || $role === 'direktur')) {
    $cid=(int)($_POST['chr_id']??0);
    $status=$_POST['status_tl']??'Belum TL';
    $cat=trim($_POST['tl_catatan']??'');
    $rid=(int)($_POST['reviu_id']??0);
    $allow=['Belum TL','Proses','Selesai'];
    if(!$cid || !in_array($status,$allow)){ flash('err','Data TL tidak valid'); }
    else{
      $st=$conn->prepare("UPDATE reviu_chr SET status_tl=?, tl_catatan=?, updated_at=NOW() WHERE id=?");
      $st->bind_param("ssi",$status,$cat,$cid);
      $ok=$st->execute();
      flash($ok?'ok':'err',$ok?'Tindak lanjut tersimpan.':'Gagal menyimpan TL.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=chr&rid='.$rid); exit;
  }

  /* ---- VERIFIKASI/TTD ---- */
  if ($act==='verifikasi' && in_array($role,['admin','super_admin','superadmin','moderator'])) {
    $rid=(int)($_POST['reviu_id']??0);
    $tahap=$_POST['tahap']??'CHR';
    $ver=$_POST['verifikator']??'';
    $status=$_POST['v_status']??'Menunggu';
    $cat=trim($_POST['v_catatan']??'');
    $st=$conn->prepare("INSERT INTO reviu_verifikasi (reviu_id,tahap,verifikator,status,catatan,tgl_verifikasi) VALUES (?,?,?,?,?,NOW())");
    $st->bind_param("issss",$rid,$tahap,$ver,$status,$cat);
    $ok=$st->execute();
    flash($ok?'ok':'err',$ok?'Verifikasi tercatat.':'Gagal mencatat verifikasi.');
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
  }

  /* ---- DOKUMEN: UPLOAD ---- */
  if ($act==='doc_upload' && (in_array($role,['admin','super_admin','superadmin','moderator'], true) || is_auditor($role) || is_auditee())) {
    $rid=(int)($_POST['reviu_id']??0);
    $kat=$_POST['kategori']??'Pelaksanaan';
    $judul=trim($_POST['judul']??'');
    $allowedCategories = ['Standar','KertasKerja','Pelaksanaan','Laporan','DukunganTL'];
    if (is_auditee()) {
      $allowedCategories = ['Pelaksanaan','DukunganTL'];
    }
    if (!in_array($kat, $allowedCategories, true)) {
      flash('err','Kategori dokumen tidak diizinkan untuk peran Anda.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit;
    }
    if(!$rid || $judul==='' || empty($_FILES['file']['name'])){
      flash('err','Lengkapi unggah dokumen'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit;
    }
    $tmp = $_FILES['file']['tmp_name'];
    $name= $_FILES['file']['name'];
    $size= (int)$_FILES['file']['size'];
    $type = sikat_guess_mime($name) ?: ($_FILES['file']['type'] ?? 'application/octet-stream');
    global $ALLOWED_MIME,$MAX_UPLOAD;
    if (!isset($ALLOWED_MIME[$type])) {
      flash('err','Tipe file tidak diizinkan: '.$type); header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit;
    }
    if ($size > $MAX_UPLOAD) {
      flash('err','Ukuran file melebihi batas (max '.number_format($MAX_UPLOAD/1024/1024,1).'MB)'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit;
    }
    $dir = __DIR__ . '/uploads/reviu/'.$rid;
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $ext = $ALLOWED_MIME[$type];
    $safeBase = preg_replace('/[^A-Za-z0-9_\-]+/','_', pathinfo($name, PATHINFO_FILENAME));
    $fname = date('Ymd_His').'_'.$safeBase.'.'.$ext;
    $dest = $dir.'/'.$fname;
    if (!move_uploaded_file($tmp, $dest)) { flash('err','Gagal menyimpan file'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit; }
    $rel = 'uploads/reviu/'.$rid.'/'.$fname;
    $st=$conn->prepare("INSERT INTO reviu_dokumen (reviu_id,kategori,judul,file_path,uploaded_by) VALUES (?,?,?,?,?)");
    $uid = $_SESSION['user']['id'] ?? null;
    $st->bind_param("isssi",$rid,$kat,$judul,$rel,$uid);
    $ok=$st->execute();
    flash($ok?'ok':'err',$ok?'Dokumen diunggah.':'Gagal mencatat dokumen.');
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit;
  }

  /* ---- DOKUMEN: HAPUS ---- */
  if ($act==='doc_delete' && in_array($role,['admin','super_admin','superadmin','moderator'])) {
    $id=(int)($_POST['id']??0); $rid=(int)($_POST['reviu_id']??0);
    $q = $conn->prepare("SELECT file_path FROM reviu_dokumen WHERE id=?");
    $q->bind_param("i",$id); $q->execute(); $row=$q->get_result()->fetch_assoc();
    if($row){
      $abs = realpath(__DIR__ . DIRECTORY_SEPARATOR . $row['file_path']);
      $base= realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reviu');
      if($abs && $base && strpos($abs,$base)===0 && is_file($abs)){ @unlink($abs); }
      $del=$conn->prepare("DELETE FROM reviu_dokumen WHERE id=?"); $del->bind_param("i",$id); $del->execute();
      flash('ok','Dokumen dihapus.');
    } else {
      flash('err','Dokumen tidak ditemukan.');
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=dok&rid='.$rid); exit;
  }

  /* ---- LAPORAN AKHIR: SIMPAN ---- */
  if ($act==='laporan_save' && (is_admin_like($role) || is_auditor($role) || $role === 'kepala_ski' || is_director_like($role))) {
    $rid = (int)($_POST['reviu_id'] ?? 0);
    $ringkasan = trim($_POST['ringkasan'] ?? '');
    $rekomendasi = trim($_POST['rekomendasi'] ?? '');
    $tindakLanjut = trim($_POST['tindak_lanjut'] ?? '');
    $ttdNama = trim($_POST['ttd_kepala_nama'] ?? '');
    $ttdTanggal = trim($_POST['ttd_kepala_tanggal'] ?? '');
    $ttdMode = $_POST['ttd_mode'] ?? 'canvas';
    $ttdMode = $ttdMode === 'upload' ? 'upload' : 'canvas';
    if ($ttdTanggal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ttdTanggal)) {
      $ttdTanggal = '';
    }
    if (!$rid) {
      flash('err','Data reviu tidak valid.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan'); exit;
    }
    $existingFile = null;
    if ($st = $conn->prepare("SELECT ttd_kepala_file FROM reviu_laporan WHERE reviu_id=?")) {
      $st->bind_param("i", $rid);
      if ($st->execute()) {
        $existing = $st->get_result()->fetch_assoc();
        if ($existing && !empty($existing['ttd_kepala_file'])) {
          $existingFile = $existing['ttd_kepala_file'];
        }
      }
    }
    $ttdPath = $existingFile;
    $canvasDataRaw = $ttdMode === 'canvas' ? trim($_POST['ttd_canvas'] ?? '') : '';
    $allowedSig = ['image/png'=>'png','image/jpeg'=>'jpg'];
    $maxSig = 2 * 1024 * 1024;
    $dir = __DIR__ . '/uploads/reviu/'.$rid;
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $hasCanvasSignature = false;
    if ($ttdMode === 'canvas' && $canvasDataRaw !== '') {
      if (strpos($canvasDataRaw, 'data:image/png;base64,') !== 0) {
        flash('err','Format tanda tangan digital tidak dikenali.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      $base64 = substr($canvasDataRaw, strlen('data:image/png;base64,'));
      $binary = base64_decode($base64, true);
      if ($binary === false) {
        flash('err','Gagal membaca data tanda tangan digital.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      if (strlen($binary) > $maxSig) {
        flash('err','Ukuran tanda tangan digital maksimal 2MB.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      if (strlen(trim($base64)) < 40) {
        // Kemungkinan kosong; abaikan
      } else {
        $fname = date('Ymd_His').'_ttd_canvas.png';
        $dest = $dir.'/'.$fname;
        if (file_put_contents($dest, $binary) === false) {
          flash('err','Gagal menyimpan tanda tangan digital.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
        }
        $hasCanvasSignature = true;
        $ttdPath = 'uploads/reviu/'.$rid.'/'.$fname;
        if ($existingFile) {
          $absOld = realpath(__DIR__ . DIRECTORY_SEPARATOR . $existingFile);
          $baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reviu' . DIRECTORY_SEPARATOR . $rid);
          if ($absOld && $baseDir && strpos($absOld, $baseDir) === 0 && is_file($absOld)) {
            @unlink($absOld);
          }
        }
      }
    }
    $hasNewSignature = ($ttdMode === 'upload') && !$hasCanvasSignature && !empty($_FILES['ttd_file']['name']);
    if ($hasNewSignature) {
      $tmp = $_FILES['ttd_file']['tmp_name'];
      $size = (int)($_FILES['ttd_file']['size'] ?? 0);
      $mime = $tmp && is_file($tmp) ? (sikat_guess_mime($tmp) ?: ($_FILES['ttd_file']['type'] ?? '')) : '';
      if (!$tmp || !is_uploaded_file($tmp)) {
        flash('err','Unggahan tanda tangan tidak valid.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      if (!isset($allowedSig[$mime])) {
        flash('err','Tanda tangan harus berupa gambar PNG atau JPG.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      if ($size > $maxSig) {
        flash('err','Ukuran tanda tangan maksimal 2MB.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      $safeBase = preg_replace('/[^A-Za-z0-9_\-]+/','_', pathinfo($_FILES['ttd_file']['name'], PATHINFO_FILENAME));
      if ($safeBase === '') { $safeBase = 'ttd_kepala_ski'; }
      $fname = date('Ymd_His').'_ttd_'.$safeBase.'.'.$allowedSig[$mime];
      $dest = $dir.'/'.$fname;
      if (!move_uploaded_file($tmp, $dest)) {
        flash('err','Gagal menyimpan berkas tanda tangan.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
      }
      $ttdPath = 'uploads/reviu/'.$rid.'/'.$fname;
      if ($existingFile) {
        $absOld = realpath(__DIR__ . DIRECTORY_SEPARATOR . $existingFile);
        $baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reviu' . DIRECTORY_SEPARATOR . $rid);
        if ($absOld && $baseDir && strpos($absOld, $baseDir) === 0 && is_file($absOld)) {
          @unlink($absOld);
        }
      }
    }
    $ttdTanggalVal = $ttdTanggal !== '' ? $ttdTanggal : null;
    if (!ensure_laporan_signature_schema($conn)) {
      flash('err','Struktur tabel revisi laporan belum lengkap. Hubungi admin.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
    }
    $sql = "INSERT INTO reviu_laporan (reviu_id, ringkasan, rekomendasi, tindak_lanjut, ttd_kepala_nama, ttd_kepala_tanggal, ttd_kepala_file, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE
              ringkasan=VALUES(ringkasan),
              rekomendasi=VALUES(rekomendasi),
              tindak_lanjut=VALUES(tindak_lanjut),
              ttd_kepala_nama=VALUES(ttd_kepala_nama),
              ttd_kepala_tanggal=VALUES(ttd_kepala_tanggal),
              ttd_kepala_file=VALUES(ttd_kepala_file),
              updated_at=NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      error_log('laporan_save prepare failed: '.$conn->error);
      flash('err','Query laporan akhir gagal disiapkan.'); header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
    }
    $stmt->bind_param(
      "issssss",
      $rid,
      $ringkasan,
      $rekomendasi,
      $tindakLanjut,
      $ttdNama,
      $ttdTanggalVal,
      $ttdPath
    );
    $ok = $stmt->execute();
    flash($ok ? 'ok' : 'err', $ok ? 'Laporan akhir tersimpan.' : 'Gagal menyimpan laporan akhir.');
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=laporan&rid='.$rid); exit;
  }
}

/* =========================================================
   ================== DATA UNTUK TAMPILAN ==================
   ========================================================= */
$tab=$_GET['tab']??'jadwal';
if ($tab === 'laporan' && is_auditee() && !is_director_like($role)) {
  $tab = 'chr';
}

$units = $conn->query("SELECT id,nama FROM unit_kerja WHERE aktif=1 ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$hasJenisTemplateCode = review_table_column_exists($conn, 'jenis_reviu', 'template_code');
$hasJenisTemplateVersion = review_table_column_exists($conn, 'jenis_reviu', 'template_version');
$jenisSelect = "id,nama,deskripsi,aktif";
if ($hasJenisTemplateCode) { $jenisSelect .= ",template_code"; }
if ($hasJenisTemplateVersion) { $jenisSelect .= ",template_version"; }
$jenis = $conn->query("SELECT {$jenisSelect} FROM jenis_reviu WHERE aktif=1 ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$templateOptions = review_template_options();
$hasReviewNamaKegiatan = review_table_column_exists($conn, 'reviu', 'nama_kegiatan');
$hasReviewTemplateCode = review_table_column_exists($conn, 'reviu', 'template_code');
$hasReviewTemplateVersion = review_table_column_exists($conn, 'reviu', 'template_version');
$jenisUsage = [];
if ($usageRes = $conn->query("SELECT jenis_id, COUNT(*) c FROM reviu GROUP BY jenis_id")) {
  while ($usageRow = $usageRes->fetch_assoc()) {
    $jenisUsage[(int)$usageRow['jenis_id']] = (int)$usageRow['c'];
  }
  $usageRes->free();
}
foreach ($jenis as &$jenisRow) {
  $mappedCode = trim((string)($jenisRow['template_code'] ?? ''));
  if ($mappedCode === '') {
    $mappedCode = review_template_code_from_name((string)($jenisRow['nama'] ?? ''));
  }
  $jenisRow['resolved_template_code'] = $mappedCode;
  $jenisRow['resolved_template_name'] = $mappedCode !== '' && isset($templateOptions[$mappedCode]) ? $templateOptions[$mappedCode]['name'] : 'Belum Dipetakan';
  $jenisRow['mapping_status'] = $mappedCode === '' ? 'Belum Dipetakan' : ($mappedCode === 'chr_legacy_laporan_keuangan' ? 'Legacy' : 'Terpetakan');
  $jenisRow['usage_count'] = $jenisUsage[(int)$jenisRow['id']] ?? 0;
}
unset($jenisRow);
$unitOptionLabels = [];
$unitSlugMap = [];
$unitCompactMap = [];
foreach ($units as $u) {
  $uid = (int)$u['id'];
  $unitOptionLabels[$uid] = $u['nama'];
  $slug = unit_slug((string)$u['nama']);
  $compact = unit_slug_compact((string)$u['nama']);
  if ($slug !== '') { $unitSlugMap[$slug] = $u; }
  if ($compact !== '') { $unitCompactMap[$compact] = $u; }
}
$auditeeNamesByUnit = [];
if ($resAuditee = $conn->query("SELECT nama, username, peran FROM pengguna WHERE LOWER(peran) LIKE 'auditee%'")) {
  while ($row = $resAuditee->fetch_assoc()) {
    $peran = strtolower(trim((string)($row['peran'] ?? '')));
    if (strpos($peran, 'auditee') !== 0) { continue; }
    $raw = substr($peran, strlen('auditee'));
    $raw = ltrim($raw, '_');
    if ($raw === '') { continue; }
    $slug = unit_slug($raw);
    $compact = unit_slug_compact($raw);
    $unit = $unitSlugMap[$slug] ?? $unitCompactMap[$slug] ?? null;
    if (!$unit && $compact !== $slug) {
      $unit = $unitSlugMap[$compact] ?? $unitCompactMap[$compact] ?? null;
    }
    if (!$unit) { continue; }
    $name = trim((string)($row['nama'] ?: $row['username'] ?: ''));
    if ($name === '') {
      $name = strtoupper(str_replace('_',' ', $raw));
    }
    $auditeeNamesByUnit[(int)$unit['id']][] = $name;
  }
  $resAuditee->free();
}
foreach ($auditeeNamesByUnit as $unitId => $names) {
  $uniqueNames = array_values(array_unique($names));
  if (!empty($uniqueNames)) {
    $unitOptionLabels[$unitId] = implode(', ', $uniqueNames).'   '.$unitOptionLabels[$unitId];
  }
}
/* Jadwal (list + filter) */
$qRaw = trim($_GET['q'] ?? '');
$q = $qRaw;
$where="WHERE 1=1"; $types=""; $params=[];
if($qRaw!==''){
  $tokens = preg_split('/\s*,\s*/', $qRaw, -1, PREG_SPLIT_NO_EMPTY);
  $statusAliasMap = pelaporan_status_alias_map();
  $statusAliasMapLower = [];
  foreach ($statusAliasMap as $alias => $canonAlias) {
    $statusAliasMapLower[strtolower($alias)] = $canonAlias;
  }
  $knownStatusLabels = [];
  foreach (pelaporan_status_catalog() as $canonKey => $meta) {
    $label = strtolower($meta['label'] ?? $canonKey);
    $knownStatusLabels[$label] = $canonKey;
  }
  $deadlineDiffExpr = "DATEDIFF(r.tgl_deadline, CURDATE())";
  $colorClauses = [
    'merah' => "r.tgl_deadline IS NOT NULL AND $deadlineDiffExpr BETWEEN -5 AND -1",
    'hitam' => "r.tgl_deadline IS NOT NULL AND $deadlineDiffExpr < -5",
    'kuning'=> "r.tgl_deadline IS NOT NULL AND $deadlineDiffExpr BETWEEN 0 AND 2",
    'hijau' => "r.tgl_deadline IS NOT NULL AND $deadlineDiffExpr > 2",
  ];
  $filterApplied = false;
  $generalTokens = [];
  foreach ($tokens as $token) {
    $value = trim($token);
    if ($value === '') { continue; }
    $parts = explode(':', $value, 2);
    if (count($parts) === 2) {
      [$field, $valRaw] = $parts;
      $field = strtolower(trim($field));
      $val = trim($valRaw);
      if ($val === '') { continue; }
      switch ($field) {
        case 'kode':
          $where .= " AND r.kode LIKE CONCAT('%',?,'%')";
          $params[] = $val; $types .= 's'; $filterApplied = true;
          continue 2;
        case 'jenis':
          $where .= " AND j.nama LIKE CONCAT('%',?,'%')";
          $params[] = $val; $types .= 's'; $filterApplied = true;
          continue 2;
        case 'unit':
          $where .= " AND u.nama LIKE CONCAT('%',?,'%')";
          $params[] = $val; $types .= 's'; $filterApplied = true;
          continue 2;
        case 'periode':
        case 'periode_mulai':
        case 'mulai':
          if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $where .= " AND r.periode_mulai = ?";
            $params[] = $val; $types .= 's'; $filterApplied = true;
            continue 2;
          }
          break;
        case 'periode_selesai':
        case 'selesai':
          if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $where .= " AND r.periode_selesai = ?";
            $params[] = $val; $types .= 's'; $filterApplied = true;
            continue 2;
          }
          break;
        case 'deadline':
          if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $where .= " AND r.tgl_deadline = ?";
            $params[] = $val; $types .= 's'; $filterApplied = true;
            continue 2;
          }
          break;
        case 'status':
          $valLower = strtolower($val);
          if (isset($statusAliasMapLower[$valLower])) {
            $canon = $statusAliasMapLower[$valLower];
          } elseif (isset($knownStatusLabels[$valLower])) {
            $canon = $knownStatusLabels[$valLower];
          } else {
            $canon = pelaporan_status_canonical($val);
          }
          $where .= " AND r.status = ?";
          $params[] = $canon; $types .= 's'; $filterApplied = true;
          continue 2;
        case 'warna':
        case 'badge':
          $valLower = strtolower($val);
          if (isset($colorClauses[$valLower])) {
            $where .= " AND ".$colorClauses[$valLower];
            $filterApplied = true;
            continue 2;
          }
          break;
      }
    }
    $valLower = strtolower($value);
    if (isset($colorClauses[$valLower])) {
      $where .= " AND ".$colorClauses[$valLower];
      $filterApplied = true;
      continue;
    }
    if (isset($statusAliasMapLower[$valLower])) {
      $where .= " AND r.status = ?";
      $params[] = $statusAliasMapLower[$valLower]; $types .= 's'; $filterApplied = true;
      continue;
    }
    if (isset($knownStatusLabels[$valLower])) {
      $where .= " AND r.status = ?";
      $params[] = $knownStatusLabels[$valLower]; $types .= 's'; $filterApplied = true;
      continue;
    }
    $generalTokens[] = $value;
  }
  if (!empty($generalTokens)) {
    foreach ($generalTokens as $gToken) {
      $where.=" AND (r.kode LIKE CONCAT('%',?,'%') OR u.nama LIKE CONCAT('%',?,'%') OR j.nama LIKE CONCAT('%',?,'%') OR r.status LIKE CONCAT('%',?,'%'))";
      $types.="ssss"; $params[]=$gToken; $params[]=$gToken; $params[]=$gToken; $params[]=$gToken;
    }
  } elseif (!$filterApplied) {
    $where.=" AND (r.kode LIKE CONCAT('%',?,'%') OR u.nama LIKE CONCAT('%',?,'%') OR j.nama LIKE CONCAT('%',?,'%') OR r.status LIKE CONCAT('%',?,'%'))";
    $types.="ssss"; $params[]=$qRaw; $params[]=$qRaw; $params[]=$qRaw; $params[]=$qRaw;
  }
}
if (!is_ski_admin()) {
  $assignConds = [];
  $assignTypes = '';
  $assignParams = [];
  $uid = (int)user_id();
  if ($uid > 0) {
    $assignConds[] = 'rp.user_id=?';
    $assignTypes .= 'i';
    $assignParams[] = $uid;
  }
  $uemail = trim((string)user_email());
  if ($uemail !== '') {
    $assignConds[] = "(rp.email<>'' AND LOWER(rp.email)=LOWER(?))";
    $assignTypes .= 's';
    $assignParams[] = $uemail;
  }
  $uname = trim((string)($_SESSION['user']['nama'] ?? ''));
  if ($uname !== '') {
    $assignConds[] = 'LOWER(rp.nama)=LOWER(?)';
    $assignTypes .= 's';
    $assignParams[] = $uname;
  }
  if ($assignConds) {
    $where .= ' AND EXISTS (SELECT 1 FROM reviu_penugasan rp WHERE rp.reviu_id=r.id AND ('.implode(' OR ', $assignConds).'))';
    $types .= $assignTypes;
    $params = array_merge($params, $assignParams);
  } else {
    $where .= ' AND 1=0';
  }
}
$sortableMap = [
  'kode'     => 'r.kode',
  'kegiatan' => $hasReviewNamaKegiatan ? 'r.nama_kegiatan' : 'r.kode',
  'jenis'    => 'j.nama',
  'unit'     => 'u.nama',
  'periode'  => 'r.periode_mulai',
  'deadline' => 'r.tgl_deadline',
  'status'   => 'r.status',
  'created'  => 'r.created_at',
];
$sortCurrent = 'created';
$sortDir = 'desc';
$sortCurrentReq = strtolower(trim($_GET['sort'] ?? 'created'));
if (isset($sortableMap[$sortCurrentReq])) {
  $sortCurrent = $sortCurrentReq;
}
$sortDirReq = strtolower(trim($_GET['dir'] ?? ''));
if (in_array($sortDirReq, ['asc','desc'], true)) {
  $sortDir = $sortDirReq;
} else {
  $sortDir = ($sortCurrent === 'created') ? 'desc' : 'asc';
}
$orderExpr = $sortableMap[$sortCurrent] . ' ' . strtoupper($sortDir);
if ($sortCurrent !== 'created') {
  $orderExpr .= ', r.created_at DESC';
}
$sortQueryBase = ['tab' => 'jadwal'];
if ($q !== '') { $sortQueryBase['q'] = $q; }
$templateSelect = $hasJenisTemplateCode ? ", j.template_code AS jenis_template_code" : ", NULL AS jenis_template_code";
$templateSelect .= $hasReviewTemplateCode ? ", r.template_code AS review_template_code" : ", NULL AS review_template_code";
$sql="SELECT r.*, u.nama unit_nama, j.nama jenis_nama {$templateSelect}
      FROM reviu r 
      JOIN unit_kerja u ON u.id=r.unit_id
      JOIN jenis_reviu j ON j.id=r.jenis_id
      $where
      ORDER BY $orderExpr
      LIMIT 50";
$stmt=$conn->prepare($sql);
if (!$stmt) {
  flash('err', 'Gagal memuat data review: '.$conn->error);
  $reviu = [];
} else {
  if($types){ $stmt->bind_param($types, ...$params); }
  if (!$stmt->execute()) {
    flash('err', 'Gagal memuat data review: '.$stmt->error);
    $reviu = [];
  } else {
    $reviu=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();
}

$ewResponseMeta = [];
if (!empty($reviu)) {
  $ids = array_column($reviu, 'id');
  foreach ($ids as $rawRid) {
    $ridKey = (int)$rawRid;
    if ($ridKey > 0) {
      $ewResponseMeta[$ridKey] = ['responded' => false, 'responded_at' => null, 'source' => null];
    }
  }
  $idList = array_map('intval', array_keys($ewResponseMeta));
  $idList = array_values(array_filter($idList, fn($v) => $v > 0));
  if (!empty($idList)) {
    $placeholders = implode(',', array_fill(0, count($idList), '?'));
    $types = str_repeat('i', count($idList));

    if ($docStmt = $conn->prepare("SELECT d.reviu_id, MIN(d.created_at) AS responded_at
      FROM reviu_dokumen d
      LEFT JOIN pengguna p ON p.id = d.uploaded_by
      WHERE d.reviu_id IN ($placeholders)
        AND (
          LOWER(COALESCE(p.peran, '')) LIKE 'auditee%'
          OR d.kategori = 'DukunganTL'
        )
      GROUP BY d.reviu_id")) {
      $docStmt->bind_param($types, ...$idList);
      $docStmt->execute();
      $docRes = $docStmt->get_result();
      while ($docRes && ($docRow = $docRes->fetch_assoc())) {
        $rid = (int)$docRow['reviu_id'];
        if (isset($ewResponseMeta[$rid])) {
          $respondedAt = trim((string)($docRow['responded_at'] ?? ''));
          if ($respondedAt !== '') {
            $ewResponseMeta[$rid] = ['responded' => true, 'responded_at' => $respondedAt, 'source' => 'dokumen'];
          }
        }
      }
      $docStmt->close();
    }

    if ($tlStmt = $conn->prepare("SELECT reviu_id, MIN(COALESCE(updated_at, created_at)) AS responded_at
      FROM reviu_chr
      WHERE reviu_id IN ($placeholders)
        AND (
          status_tl IN ('Proses', 'Selesai')
          OR TRIM(COALESCE(tl_catatan, '')) <> ''
        )
      GROUP BY reviu_id")) {
      $tlStmt->bind_param($types, ...$idList);
      $tlStmt->execute();
      $tlRes = $tlStmt->get_result();
      while ($tlRes && ($tlRow = $tlRes->fetch_assoc())) {
        $rid = (int)$tlRow['reviu_id'];
        if (isset($ewResponseMeta[$rid])) {
          $respondedAt = trim((string)($tlRow['responded_at'] ?? ''));
          $currentAt = (string)($ewResponseMeta[$rid]['responded_at'] ?? '');
          if ($respondedAt !== '' && ($currentAt === '' || strtotime($respondedAt) < strtotime($currentAt))) {
            $ewResponseMeta[$rid] = ['responded' => true, 'responded_at' => $respondedAt, 'source' => 'tl'];
          }
        }
      }
      $tlStmt->close();
    }
  }
}

/* Detail jika rid diberikan */
$rid=(int)($_GET['rid']??0);
$rid = $rid > 0 ? $rid : 0;
if ($rid) { require_assigned_or_admin($rid); }
$rev=null; $chr=[]; $ver=[]; $laporanRows=[];
$chrEditId=0; $chrEdit=null;
$docs = ['Standar'=>[], 'KertasKerja'=>[], 'Pelaksanaan'=>[], 'Laporan'=>[], 'DukunganTL'=>[]];
$hasDocs = false;
$assign = []; $users=[];
$chrSheet = chr_form_defaults(null);
$chrTemplateCode = 'chr_legacy_laporan_keuangan';
$chrTemplate = chr_template_get($chrTemplateCode) ?: [];
$useDynamicChr = false;
$chrDocName = 'CHR';
$chrEmployeeOptions = [];
$chrWorkflow = chr_sop_workflow_default();
$chrWorkflowStatus = 'draft';
$chrWorkflowLocked = false;
$chrCanReturnCurrent = false;
$chrSheetUpdatedAt = null;
if($rid){
  $stmt=$conn->prepare("SELECT r.*, u.nama unit_nama, j.nama jenis_nama FROM reviu r JOIN unit_kerja u ON u.id=r.unit_id JOIN jenis_reviu j ON j.id=r.jenis_id WHERE r.id=?");
  $stmt->bind_param("i",$rid); $stmt->execute(); $rev=$stmt->get_result()->fetch_assoc();
  $chrSheet = chr_form_fetch($conn, $rid, $rev ?: null);
  $chrTemplateCode = (string)($chrSheet['template_code'] ?? 'chr_legacy_laporan_keuangan');
  $chrTemplate = chr_template_get($chrTemplateCode) ?: chr_template_get('chr_legacy_laporan_keuangan') ?: [];
  $chrDocName = function_exists('chr_template_display_name') ? chr_template_display_name($chrTemplateCode) : ($chrTemplateCode === 'chr_rkakl' ? 'CHR RKAKL' : ($chrTemplateCode === 'chr_manajemen_risiko' ? 'CHR Manajemen Risiko' : ($chrTemplateCode === 'chr_sop' ? 'CHR SOP' : 'CHR')));
  $useDynamicChr = function_exists('chr_template_uses_standard_approval')
    && chr_template_uses_standard_approval($chrTemplateCode)
    && (($chrTemplate['renderer'] ?? 'legacy') === 'dynamic');
  if ($useDynamicChr) {
    $chrEmployeeOptions = chr_employee_picker_options($conn);
    $chrWorkflow = chr_sop_workflow($chrSheet);
    $chrWorkflowStatus = (string)($chrWorkflow['status'] ?? 'draft');
    $chrWorkflowLocked = $chrWorkflowStatus !== 'draft';
    $chrCanReturnCurrent = chr_sop_user_has_waiting_signature($chrSheet, (int)($_SESSION['user']['id'] ?? 0));
  }
  if ($metaStmt = $conn->prepare("SELECT updated_at FROM reviu_chr_form WHERE reviu_id=? LIMIT 1")) {
    $metaStmt->bind_param("i", $rid);
    if ($metaStmt->execute()) {
      $metaRes = $metaStmt->get_result();
      if ($metaRow = $metaRes->fetch_assoc()) {
        $chrSheetUpdatedAt = $metaRow['updated_at'] ?? null;
      }
      $metaRes->free();
    }
    $metaStmt->close();
  }
  $chr = [];
  if ($chrStmt = $conn->prepare("SELECT * FROM reviu_chr WHERE reviu_id=? ORDER BY created_at DESC")) {
    $chrStmt->bind_param("i", $rid);
    $chrStmt->execute();
    $chrRes = $chrStmt->get_result();
    $chr = $chrRes ? $chrRes->fetch_all(MYSQLI_ASSOC) : [];
    $chrStmt->close();
  }
  $chrEditId = (int)($_GET['edit_chr']??0);
  if ($chrEditId && (in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role))) {
    foreach ($chr as $item) {
      if ((int)$item['id'] === $chrEditId) { $chrEdit = $item; break; }
    }
    if (!$chrEdit) { $chrEditId = 0; }
  } else {
    $chrEditId = 0;
  }
  $ver = [];
  if ($verStmt = $conn->prepare("SELECT * FROM reviu_verifikasi WHERE reviu_id=? ORDER BY created_at DESC")) {
    $verStmt->bind_param("i", $rid);
    $verStmt->execute();
    $verRes = $verStmt->get_result();
    $ver = $verRes ? $verRes->fetch_all(MYSQLI_ASSOC) : [];
    $verStmt->close();
  }
  $qd = $conn->prepare("SELECT id,kategori,judul,file_path,created_at FROM reviu_dokumen WHERE reviu_id=? ORDER BY created_at DESC");
  $qd->bind_param("i",$rid); $qd->execute(); $res=$qd->get_result();
  while($row=$res->fetch_assoc()){ if(isset($docs[$row['kategori']])) $docs[$row['kategori']][]=$row; }
  foreach ($docs as $items) { if (!empty($items)) { $hasDocs = true; break; } }
  if ($qa = $conn->prepare("SELECT id,role,user_id,nama,email,created_at FROM reviu_penugasan WHERE reviu_id=? ORDER BY created_at DESC")){
    $qa->bind_param("i",$rid); $qa->execute(); $assign=$qa->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  if ($qlap = $conn->prepare("SELECT id, ringkasan, rekomendasi, tindak_lanjut, lampiran, ttd_kepala_nama, ttd_kepala_tanggal, ttd_kepala_file, created_at, updated_at FROM reviu_laporan WHERE reviu_id=? ORDER BY created_at DESC")) {
    $qlap->bind_param("i", $rid);
    $qlap->execute();
    $laporanRows = $qlap->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  // Ambil daftar auditor/auditee dari tabel pengguna/users
  if ($userSourceTable === 'pengguna') {
    $colList = "id, nama, username, peran".($userSourceHasEmail ? ", email" : "");
    if ($rs = $conn->query("SELECT $colList FROM pengguna ORDER BY nama ASC, username ASC")) {
      while ($row = $rs->fetch_assoc()) {
        $roleRaw = strtolower(trim((string)($row['peran'] ?? '')));
        $type = '';
        if (strpos($roleRaw, 'auditor') === 0) { $type = 'AUDITOR'; }
        elseif (strpos($roleRaw, 'auditee') === 0) { $type = 'AUDITEE'; }
        if ($type === '') { continue; }
        $name = trim((string)($row['nama'] ?? ''));
        if ($name === '') { $name = trim((string)($row['username'] ?? '')); }
        if ($name === '') { $name = 'User #'.(int)$row['id']; }
        $email = '';
        if ($userSourceHasEmail && isset($row['email'])) {
          $email = trim((string)$row['email']);
        }
        $users[] = [
          'id' => (int)$row['id'],
          'nama' => $name,
          'email' => $email,
          'peran' => (string)($row['peran'] ?? ''),
          'type' => $type,
        ];
      }
      $rs->free();
    }
  } elseif ($userSourceTable === 'users') {
    if ($rs = $conn->query("SELECT id, COALESCE(nama,username) nama, email, COALESCE(peran,'') peran FROM users ORDER BY nama ASC")) {
      while ($row = $rs->fetch_assoc()) {
        $roleRaw = strtolower(trim((string)($row['peran'] ?? '')));
        $type = '';
        if (strpos($roleRaw, 'auditor') === 0) { $type = 'AUDITOR'; }
        elseif (strpos($roleRaw, 'auditee') === 0) { $type = 'AUDITEE'; }
        if ($type === '') { continue; }
        $name = trim((string)($row['nama'] ?? ''));
        if ($name === '') { $name = 'User #'.(int)$row['id']; }
        $email = trim((string)($row['email'] ?? ''));
        $users[] = [
          'id' => (int)$row['id'],
          'nama' => $name,
          'email' => $email,
          'peran' => (string)($row['peran'] ?? ''),
          'type' => $type,
        ];
      }
      $rs->free();
    }
  }
}

$chrPendingSignatureTasks = chr_sop_pending_signature_tasks($conn, (int)($_SESSION['user']['id'] ?? 0));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>E-Reviu &ndash; SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= e(asset_url('assets/css/ui_base.css')) ?>" rel="stylesheet">
  <style>
    :root{ --brand:#218838; --accent:#f0c300; --soft:#fbfdf8; --border:#dcefe4; }
    body{ background:var(--soft); }
    .appbar{background:var(--brand); border-bottom:4px solid var(--accent); color:#fff;}
    .card-soft{background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 6px 18px rgba(0,0,0,.06);}
    .badge-ew{border:1px solid #ccc}
    .signature-pad-wrapper{
      position:relative;
      border:1.5px dashed rgba(51,107,80,.35);
      border-radius:12px;
      background:linear-gradient(180deg,#fff,#fbfdf8);
      min-height:118px;
      touch-action:none;
      overflow:hidden;
      max-width:520px;
      width:100%;
      margin:0 auto;
      text-align:center;
    }
    .signature-pad-wrapper canvas{
      width:100%;
      height:118px;
      display:block;
      cursor:crosshair;
      touch-action:none;
    }
    .signature-pad-wrapper .sig-overlay{
      position:absolute;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:.78rem;
      padding:0 18px;
      pointer-events:none;
      transition:opacity .2s ease;
    }
    .signature-pad-wrapper.is-drawing .sig-overlay{opacity:0;}
    .signature-pad-wrapper.is-disabled{
      opacity:.4;
    }
    .chr-signature-pad{
      min-height:118px;
      max-width:100%;
    }
    .chr-signature-preview{
      border-top:1px solid rgba(51,107,80,.12);
      padding-top:10px;
    }
    .chr-signature-preview img{
      max-width:100%;
      width:100%;
      height:82px;
      object-fit:contain;
      border:1px solid rgba(51,107,80,.16);
      border-radius:8px;
      background:#fff;
      padding:4px;
    }
    .chr-signature-actions .btn{min-width:0; flex:1 1 0; font-weight:600;}
    .chr-signature-section .card-body{background:#fff;}
    .chr-signature-grid>[class*="col-"]{display:flex;}
    .chr-signature-panel{
      width:100%;
      border:1px solid rgba(51,107,80,.16);
      border-radius:14px;
      background:#fbfdf8;
      padding:16px;
      box-shadow:0 4px 14px rgba(33,136,56,.06);
    }
    .chr-signature-panel-head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
      margin-bottom:12px;
      padding-bottom:10px;
      border-bottom:1px solid rgba(51,107,80,.14);
    }
    .chr-signature-panel-title{
      font-weight:700;
      color:#1f6b3a;
      line-height:1.25;
    }
    .chr-signature-fields{
      display:grid;
      gap:10px;
    }
    .chr-employee-picker .form-control{
      border-color:rgba(51,107,80,.18);
    }
    .chr-employee-search{
      border:1px solid rgba(51,107,80,.16);
      border-radius:12px;
      background:#fff;
      padding:8px;
    }
    .chr-employee-help,
    .chr-employee-empty{
      margin-top:8px;
      border:1px dashed rgba(51,107,80,.18);
      border-radius:10px;
      background:#f8fbf9;
      padding:8px 10px;
    }
    .chr-employee-results{
      display:grid;
      gap:6px;
      max-height:210px;
      overflow:auto;
      margin-top:8px;
      padding-right:2px;
    }
    .chr-employee-option{
      width:100%;
      border:1px solid rgba(51,107,80,.12);
      border-radius:10px;
      background:#fff;
      color:#1f5130;
      display:grid;
      gap:2px;
      padding:8px 10px;
      text-align:left;
      transition:background .15s ease, border-color .15s ease, box-shadow .15s ease;
    }
    .chr-employee-option:hover,
    .chr-employee-option.is-selected{
      background:#eef8f0;
      border-color:rgba(33,136,56,.32);
      box-shadow:0 4px 12px rgba(33,136,56,.08);
    }
    .chr-employee-option.is-empty{
      color:#6c757d;
      font-size:.85rem;
    }
    .chr-employee-option.is-disabled{
      opacity:.58;
      cursor:not-allowed;
    }
    .chr-employee-option-name{
      font-weight:700;
      font-size:.9rem;
    }
    .chr-employee-option-meta,
    .chr-employee-option-nip{
      color:#6c757d;
      font-size:.78rem;
    }
    .chr-signer-profile{
      display:grid;
      gap:3px;
      border:1px solid rgba(51,107,80,.14);
      border-radius:12px;
      background:#fff;
      padding:10px 12px;
      min-height:88px;
    }
    .chr-signer-profile.is-empty{
      color:#6c757d;
      background:#f8fbf9;
    }
    .chr-signer-name{
      font-weight:700;
      color:#1f5130;
    }
    .chr-signer-title{
      color:#2d6d43;
      font-size:.9rem;
    }
    .chr-signer-meta,
    .chr-signer-unit{
      color:#6c757d;
      font-size:.8rem;
    }
    .chr-signature-waiting{
      border:1px dashed rgba(51,107,80,.25);
      border-radius:10px;
      background:#f8fbf9;
      padding:12px;
    }
    .chr-member-signature-list{
      display:grid;
      gap:12px;
      grid-template-columns:repeat(2, minmax(0, 1fr));
    }
    .chr-member-signature-card{
      border:1px solid rgba(51,107,80,.14);
      border-radius:12px;
      background:#fff;
      padding:12px;
    }
    .chr-submit-readiness{
      border:1px solid rgba(51,107,80,.14);
      border-radius:12px;
      background:#f8fbf9;
      padding:10px 12px;
    }
    .chr-preview-modal .modal-dialog{
      max-width:min(1120px, 96vw);
    }
    .chr-preview-frame{
      width:100%;
      height:78vh;
      border:0;
      background:#eef3ef;
      border-radius:0 0 10px 10px;
    }
    .approval-anchor-highlight{
      outline:3px solid rgba(25,135,84,.38);
      box-shadow:0 0 0 6px rgba(25,135,84,.12), 0 10px 24px rgba(25,135,84,.12);
      transition:box-shadow .25s ease, outline-color .25s ease;
    }
    .review-create-modal .modal-content{border:1px solid #d6e9de;border-radius:16px;box-shadow:0 22px 60px rgba(5,42,28,.24);}
    .review-create-modal .modal-header{background:#f7fbf8;border-bottom:1px solid #d6e9de;}
    .review-create-modal .modal-title{color:#124b38;font-weight:800;}
    .review-template-help{font-size:.78rem;color:#6b7280;margin-top:.25rem;}
    .jenis-template-row{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(180px,.8fr) 110px minmax(180px,.9fr);gap:10px;align-items:center;padding:10px;border:1px solid #edf3ef;border-radius:10px;background:#fbfdfc;}
    .review-action-cell{min-width:260px;}
    .review-action-group{display:flex;align-items:center;justify-content:flex-end;gap:8px;white-space:nowrap;}
    .review-action-group .btn{min-height:32px;display:inline-flex;align-items:center;justify-content:center;gap:6px;}
    .review-action-menu{min-width:210px;border:1px solid #dce9e1;border-radius:12px;box-shadow:0 16px 36px rgba(5,42,28,.16);padding:6px;}
    .review-action-menu .dropdown-item{border-radius:8px;padding:8px 10px;font-size:.9rem;}
    .review-action-menu .dropdown-item i{width:18px;text-align:center;}
    .review-action-modal .modal-content{border:1px solid #d6e9de;border-radius:16px;box-shadow:0 22px 60px rgba(5,42,28,.24);}
    .review-action-modal .modal-header{background:#f7fbf8;border-bottom:1px solid #d6e9de;}
    .review-action-modal .modal-title{color:#124b38;font-weight:800;}
    .review-status-note{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;max-width:260px;}
    @media (max-width: 992px){.jenis-template-row{grid-template-columns:1fr;}}
    @media (max-width: 575.98px){
      .chr-signature-panel{padding:12px;}
      .chr-signature-actions{flex-direction:column;}
      .chr-signature-actions .btn{width:100%;}
      .chr-member-signature-list{grid-template-columns:1fr;}
      .chr-preview-frame{height:74vh;}
      .review-action-cell{min-width:210px;}
      .review-action-group{justify-content:flex-start;gap:6px;}
      .review-action-group .btn:not(.dropdown-toggle){padding-left:8px;padding-right:8px;}
    }
    @media (max-width: 991.98px){
      .chr-member-signature-list{grid-template-columns:1fr;}
    }
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>


<main class="container my-4">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="if(window.history.length>1){window.history.back();}else{window.location.href='<?= e(route_url('dashboard')) ?>';}">
      <i class="bi bi-arrow-left"></i> Kembali
    </button>
    <?php if (!is_auditee() || is_director_like($role)): ?>
    <a class="btn btn-success btn-sm" href="<?= e(route_url('dashboard')) ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <?php endif; ?>
  </div>
  <?php
    $flash_messages = [];
    if ($m = flash('ok')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }
    if ($m = flash('err')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }
    if ($m = flash('info')) { $flash_messages[] = ['type' => 'info', 'message' => $m]; }
    if ($m = flash('warn')) { $flash_messages[] = ['type' => 'warning', 'message' => $m]; }
    include __DIR__ . '/includes/flash.php';
  ?>

  <?php if(!empty($chrPendingSignatureTasks)): ?>
    <div class="card-soft p-3 mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div>
          <div class="fw-semibold text-success">Dokumen Menunggu Tanda Tangan</div>
          <div class="small text-muted">Daftar dokumen CHR yang menugaskan akun Anda sebagai penanda tangan.</div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Dokumen</th>
              <th>Posisi Anda</th>
              <th>Jabatan Resmi</th>
              <th>Tanggal Pengajuan</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($chrPendingSignatureTasks as $task): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($task['kode'] ?: 'Reviu #'.(int)$task['reviu_id']) ?></div>
                  <div class="small text-muted"><?= e($task['jenis_nama'] ?: 'CHR SOP') ?></div>
                </td>
                <td><?= e($task['document_role_label']) ?></td>
                <td><?= e($task['jabatan']) ?></td>
                <td><?= e($task['submitted_at'] ?: $task['updated_at']) ?></td>
                <td><span class="badge bg-warning text-dark">Menunggu Tanda Tangan</span></td>
                <td><a class="btn btn-sm btn-outline-success" href="<?= e(review_url('chr', ['rid' => (int)$task['reviu_id']], (string)($task['approval_anchor'] ?? 'approval-section'))) ?>">Lihat Dokumen</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>


  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='jadwal'?'active':'' ?>" href="<?= e(review_url('jadwal')) ?>">Jadwal</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='asg'?'active':'' ?>" href="<?= e(review_url('asg', $rid ? ['rid' => $rid] : [])) ?>">Penugasan</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='dok'?'active':'' ?>" href="<?= e(review_url('dok', $rid ? ['rid' => $rid] : [])) ?>">Dokumen</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='chr'?'active':'' ?>" href="<?= e(review_url('chr', $rid ? ['rid' => $rid] : [])) ?>">CHR & Rekomendasi</a></li>
    <?php if(!is_auditee() || is_director_like($role)){ ?>
      <li class="nav-item"><a class="nav-link <?= $tab==='laporan'?'active':'' ?>" href="<?= e(review_url('laporan', $rid ? ['rid' => $rid] : [])) ?>">Laporan &amp; Verifikasi</a></li>
    <?php } ?>
    <?php if (!is_auditee()): ?>
      <li class="nav-item ms-auto"><a class="nav-link <?= $tab==='master'?'active':'' ?>" href="<?= e(review_url('master')) ?>">Master</a></li>
    <?php endif; ?>
  </ul>

  <?php if($tab==='master'){ ?>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card-soft p-3">
          <h6 class="mb-2">Unit Kerja</h6>
          <form method="post" class="row g-2 mb-2">
            <?= csrf_field(); ?><input type="hidden" name="action" value="unit_create">
            <div class="col-8"><input name="nama" class="form-control" placeholder="Nama unit"></div>
            <div class="col-4"><button class="btn btn-success w-100">Tambah</button></div>
          </form>
          <ul class="list-group">
            <?php foreach($units as $u){ ?>
              <li class="list-group-item d-flex justify-content-between align-items-center"><?= e($u['nama']) ?><span class="badge bg-success">Aktif</span></li>
            <?php } ?>
          </ul>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card-soft p-3">
          <h6 class="mb-2">Jenis Reviu</h6>
          <form method="post" class="row g-2 mb-2">
            <?= csrf_field(); ?><input type="hidden" name="action" value="jenis_create">
            <div class="col-md-4"><input name="nama" class="form-control" placeholder="Nama jenis"></div>
            <div class="col-md-4"><input name="deskripsi" class="form-control" placeholder="Deskripsi (opsional)"></div>
            <div class="col-md-3">
              <select name="template_code" class="form-select">
                <option value="">Template opsional</option>
                <?php foreach($templateOptions as $tpl){ ?>
                  <option value="<?= e($tpl['code']) ?>"><?= e($tpl['name']) ?> (<?= e($tpl['code']) ?>)</option>
                <?php } ?>
              </select>
            </div>
            <div class="col-md-1"><button class="btn btn-success w-100">Tambah</button></div>
          </form>
          <div class="d-grid gap-2">
            <?php foreach($jenis as $j){
              $mappedCode = (string)($j['resolved_template_code'] ?? '');
              $status = (string)($j['mapping_status'] ?? 'Belum Dipetakan');
              $badgeClass = $status === 'Terpetakan' ? 'bg-success' : ($status === 'Legacy' ? 'bg-secondary' : 'bg-warning text-dark');
            ?>
              <div class="jenis-template-row">
                <div>
                  <div class="fw-semibold"><?= e($j['nama']) ?></div>
                  <?php if(trim((string)($j['deskripsi'] ?? '')) !== ''): ?><div class="small text-muted"><?= e($j['deskripsi']) ?></div><?php endif; ?>
                  <div class="small text-muted"><?= number_format((int)($j['usage_count'] ?? 0)) ?> kegiatan memakai jenis ini</div>
                </div>
                <div>
                  <div class="small text-muted">Template</div>
                  <div class="fw-semibold"><?= $mappedCode !== '' ? e($j['resolved_template_name']) : 'Belum Dipetakan' ?></div>
                  <code class="small"><?= $mappedCode !== '' ? e($mappedCode) : '-' ?></code>
                </div>
                <div><span class="badge <?= e($badgeClass) ?>"><?= e($status) ?></span></div>
                <form method="post" class="d-flex gap-2 align-items-center">
                  <?= csrf_field(); ?><input type="hidden" name="action" value="jenis_template_update">
                  <input type="hidden" name="jenis_id" value="<?= (int)$j['id'] ?>">
                  <select name="template_code" class="form-select form-select-sm" <?= (!$hasJenisTemplateCode || !$hasJenisTemplateVersion) ? 'disabled' : '' ?>>
                    <option value="">Atur Template</option>
                    <?php foreach($templateOptions as $tpl){ ?>
                      <option value="<?= e($tpl['code']) ?>" <?= $mappedCode === $tpl['code'] ? 'selected' : '' ?>><?= e($tpl['code']) ?></option>
                    <?php } ?>
                  </select>
                  <button class="btn btn-sm btn-outline-success" <?= (!$hasJenisTemplateCode || !$hasJenisTemplateVersion) ? 'disabled' : '' ?>>Simpan</button>
                </form>
              </div>
            <?php } ?>
          </div>
          <?php if(!$hasJenisTemplateCode || !$hasJenisTemplateVersion): ?>
            <div class="alert alert-warning mt-3 mb-0">Kolom pemetaan template belum tersedia. Jalankan migration <code>20260803_090111_review_template_mapping.sql</code> untuk mengaktifkan pemetaan jenis reviu.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  <?php } ?>

  <?php if($tab==='jadwal'){ ?>
    <div class="card-soft p-3 mb-3">
      <form method="get" class="row g-2">
        <input type="hidden" name="tab" value="jadwal">
        <input type="hidden" name="sort" value="<?= e($sortCurrent) ?>">
        <input type="hidden" name="dir" value="<?= e($sortDir) ?>">
        <div class="col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari kode/unit (mis. warna:merah, status:Monitoring TL)"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= e(review_url('jadwal')) ?>">Reset</a></div>
      </form>
    </div>

    <?php if(in_array($role,['admin','super_admin','superadmin','moderator'])){ ?>
    <div class="card-soft p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h6 class="mb-1">Buat Jadwal Reviu</h6>
        <div class="text-muted small">Nama kegiatan dan jenis/template CHR dipisahkan agar dokumen CHR memakai format yang tepat.</div>
      </div>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#reviewCreateModal">
        <i class="bi bi-plus-lg me-1"></i>Buat Jadwal Reviu
      </button>
    </div>
    <?php } ?>

    <div class="modal fade review-create-modal" id="reviewCreateModal" tabindex="-1" aria-labelledby="reviewCreateModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <form method="post" id="reviewCreateForm" data-dirty-confirm="Data jadwal belum disimpan. Tutup form?">
            <?= csrf_field(); ?><input type="hidden" name="action" value="reviu_create">
            <div class="modal-header">
              <div>
                <h5 class="modal-title" id="reviewCreateModalLabel">Buat Jadwal Reviu</h5>
                <div class="text-muted small">Pisahkan judul kegiatan dari jenis reviu/template CHR.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <?php if(!$hasReviewNamaKegiatan || !$hasReviewTemplateCode || !$hasReviewTemplateVersion): ?>
                <div class="alert alert-warning">Kolom nama kegiatan/template belum tersedia. Jalankan migration <code>20260803_090111_review_template_mapping.sql</code> sebelum membuat jadwal baru.</div>
              <?php endif; ?>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Nama/Judul Kegiatan Reviu <span class="text-danger">*</span></label>
                  <input type="text" name="nama_kegiatan" class="form-control" placeholder="Contoh: Reviu Laporan Kinerja Semester I Tahun 2026" required data-review-create-focus>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Jenis Reviu / Template CHR <span class="text-danger">*</span></label>
                  <select name="jenis_id" class="form-select" required id="reviewJenisSelect">
                    <option value="">Pilih Jenis Reviu / Template</option>
                    <?php foreach($jenis as $j){
                      $resolvedCode = (string)($j['resolved_template_code'] ?? '');
                      $templateName = (string)($j['resolved_template_name'] ?? 'Belum Dipetakan');
                    ?>
                      <option value="<?= (int)$j['id'] ?>" data-template-code="<?= e($resolvedCode) ?>" data-template-name="<?= e($templateName) ?>">
                        <?= e($j['nama']) ?><?= $resolvedCode !== '' ? ' - '.e($resolvedCode) : ' - Belum Dipetakan' ?>
                      </option>
                    <?php } ?>
                  </select>
                  <div class="review-template-help" id="reviewTemplateHelp">Pilih jenis yang sudah terhubung ke template CHR.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Unit Kerja <span class="text-danger">*</span></label>
                  <select name="unit_id" class="form-select" id="unit-select" required>
                    <option value="">Pilih Unit</option>
                    <?php foreach($units as $u){ ?>
                      <option value="<?= (int)$u['id'] ?>"><?= e($u['nama']) ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tanggal Mulai Reviu <span class="text-danger">*</span></label>
                  <input type="date" name="mulai" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tanggal Selesai Reviu <span class="text-danger">*</span></label>
                  <input type="date" name="selesai" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Deadline <span class="text-danger">*</span></label>
                  <input type="date" name="deadline" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success" <?= (!$hasReviewNamaKegiatan || !$hasReviewTemplateCode || !$hasReviewTemplateVersion) ? 'disabled' : '' ?>>Simpan Jadwal</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="card-soft p-3">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr>
            <?php
              $sortColumns = [
                'kode'     => 'Kode',
                'jenis'    => 'Kegiatan / Jenis',
                'unit'     => 'Unit',
                'periode'  => 'Periode',
                'deadline' => 'Deadline',
                'status'   => 'Status',
              ];
              foreach ($sortColumns as $colKey => $colLabel):
                $isCurrent = ($sortCurrent === $colKey);
                $nextDir = ($isCurrent && $sortDir === 'asc') ? 'desc' : 'asc';
                $params = $sortQueryBase;
                $params['sort'] = $colKey;
                $params['dir'] = $nextDir;
                $icon = '';
                if ($isCurrent) {
                  $icon = $sortDir === 'asc' ? ' &uarr;' : ' &darr;';
                }
            ?>
              <th>
                <a href="<?= e(review_url('jadwal', $params)) ?>" class="text-decoration-none <?= $isCurrent ? 'fw-semibold' : '' ?>">
                  <?= e($colLabel) ?><?= $icon ?>
                </a>
              </th>
            <?php endforeach; ?>
            <th>Aksi</th>
          </tr></thead>
          <tbody>
            <?php if(empty($reviu)){ ?>
              <tr><td colspan="7"><div class="empty-state">Anda Belum di Tugaskan</div></td></tr>
            <?php } else { foreach($reviu as $r){
              try {
                $deadlineDate = new DateTime($r['tgl_deadline']);
              } catch (Throwable $e) {
                $deadlineDate = new DateTime('today');
              }
              $responseMeta = $ewResponseMeta[(int)$r['id']] ?? ['responded' => false, 'responded_at' => null, 'source' => null];
              [$ewName,$ewColor,$ewDesc,$ewDiff] = ew_color($deadlineDate, 2, 0);
              if (!empty($responseMeta['responded']) && !empty($responseMeta['responded_at'])) {
                try {
                  $responseDate = new DateTime((string)$responseMeta['responded_at']);
                  $frozenDiff = (int)$responseDate->diff($deadlineDate)->format('%r%a');
                  [$ewName,$ewColor,, $ewDiff] = ew_color_from_diff($frozenDiff, 2, 0);
                  $ewName = 'Hijau';
                  $ewColor = early_warning_color('green');
                  if ($frozenDiff < 0) {
                    $ewDesc = 'Auditee sudah merespons. Keterlambatan dibekukan pada '.abs($frozenDiff).' hari.';
                  } elseif ($frozenDiff === 0) {
                    $ewDesc = 'Auditee sudah merespons tepat pada hari deadline.';
                  } else {
                    $ewDesc = 'Auditee sudah merespons '.($frozenDiff === 1 ? '1 hari sebelum deadline.' : $frozenDiff.' hari sebelum deadline.');
                  }
                } catch (Throwable $e) {
                  $ewName = 'Hijau';
                  $ewColor = early_warning_color('green');
                  $ewDesc = 'Auditee sudah merespons.';
                }
              }
              $cls = $r['status']==='Selesai'
                ? 'success'
                : ($r['status']==='Tidak Selesai'
                  ? 'danger'
                  : ($r['status']==='Verifikasi' ? 'warning text-dark' : 'secondary'));
              $canAdvanceStatus = in_array($r['status'], ['Terjadwal','Pelaksanaan','CHR'], true);
            ?>
              <tr>
                <td>
                  <div class="d-flex flex-column">
                    <a href="<?= e(review_url('asg', ['rid' => (int)$r['id']])) ?>" class="fw-bold"><?= e($r['kode']) ?></a>
                    <small class="text-muted">
                    <a href="<?= e(review_url('asg', ['rid' => (int)$r['id']])) ?>">Penugasan</a> &middot;
                    <a href="<?= e(review_url('dok', ['rid' => (int)$r['id']])) ?>">Dokumen</a> &middot;
                    <a href="<?= e(review_url('chr', ['rid' => (int)$r['id']])) ?>">CHR</a>
                    <?php if(!is_auditee()){ ?> &middot;
                      <a href="<?= e(review_url('laporan', ['rid' => (int)$r['id']])) ?>">Verifikasi</a>
                    <?php } ?>
                    </small>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold"><?= e($hasReviewNamaKegiatan && trim((string)($r['nama_kegiatan'] ?? '')) !== '' ? $r['nama_kegiatan'] : $r['jenis_nama']) ?></div>
                  <div class="small text-muted">Jenis: <?= e($r['jenis_nama']) ?></div>
                  <?php
                    $rowTemplateCode = trim((string)($r['review_template_code'] ?? ''));
                    if ($rowTemplateCode === '') { $rowTemplateCode = trim((string)($r['jenis_template_code'] ?? '')); }
                    if ($rowTemplateCode === '') { $rowTemplateCode = review_template_code_from_name((string)$r['jenis_nama']); }
                  ?>
                  <div class="small text-muted">Template: <?= $rowTemplateCode !== '' ? e($rowTemplateCode) : 'Belum dipetakan' ?></div>
                </td>
                <td><?= e($r['unit_nama']) ?></td>
                <td><?= e($r['periode_mulai']) ?> &rarr; <?= e($r['periode_selesai']) ?></td>
                <td>
                  <span class="badge badge-ew" style="background: <?= e($ewColor) ?>; color:#fff"><?= e($r['tgl_deadline']).' &middot; '.$ewName ?></span>
                  <?php if($ewDesc !== ''): ?><div class="small text-muted"><?= e($ewDesc) ?></div><?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-<?= $cls ?>"><?= e($r['status']) ?></span>
                  <?php if(trim((string)($r['catatan'] ?? '')) !== ''): ?>
                    <div class="small text-muted mt-1 review-status-note" title="<?= e($r['catatan']) ?>"><?= e($r['catatan']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="review-action-cell">
                  <div class="review-action-group">
                    <a class="btn btn-sm btn-success" href="<?= e(review_url('asg', ['rid' => (int)$r['id']])) ?>">
                      <i class="bi bi-folder2-open"></i><span>Buka</span>
                    </a>
                    <?php if((in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role)) && $canAdvanceStatus){ ?>
                      <form method="post" class="d-inline">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="reviu_step">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="to" value="<?= $r['status']==='Terjadwal'?'Pelaksanaan':($r['status']==='Pelaksanaan'?'CHR':'Rekomendasi') ?>">
                        <button class="btn btn-sm btn-outline-primary" title="Melanjutkan kegiatan ke tahap berikutnya">
                          <i class="bi bi-arrow-right-circle"></i><span>Majukan Tahap</span>
                        </button>
                      </form>
                    <?php } ?>
                    <?php
                      $canManageSchedule = in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role);
                      $canDeleteSchedule = in_array($role,['admin','super_admin','superadmin','moderator'], true);
                      $rowTitle = $hasReviewNamaKegiatan && trim((string)($r['nama_kegiatan'] ?? '')) !== '' ? (string)$r['nama_kegiatan'] : (string)$r['jenis_nama'];
                    ?>
                    <?php if($canManageSchedule || $canDeleteSchedule){ ?>
                      <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Menu aksi lainnya">
                          <i class="bi bi-three-dots-vertical"></i><span class="visually-hidden">Lainnya</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end review-action-menu">
                          <?php if($canManageSchedule){ ?>
                            <li>
                              <button type="button" class="dropdown-item" data-review-deadline-open
                                data-id="<?= (int)$r['id'] ?>"
                                data-code="<?= e($r['kode']) ?>"
                                data-name="<?= e($rowTitle) ?>"
                                data-deadline="<?= e($r['tgl_deadline']) ?>"
                                data-deadline-info="<?= e($ewDesc !== '' ? $ewDesc : ($r['tgl_deadline'].' - '.$ewName)) ?>">
                                <i class="bi bi-calendar-event"></i>Atur Deadline
                              </button>
                            </li>
                            <li>
                              <button type="button" class="dropdown-item" data-review-note-open
                                data-id="<?= (int)$r['id'] ?>"
                                data-code="<?= e($r['kode']) ?>"
                                data-name="<?= e($rowTitle) ?>"
                                data-status="<?= e($r['status']) ?>"
                                data-note="<?= e($r['catatan'] ?? '') ?>">
                                <i class="bi bi-chat-left-text"></i>Atur Catatan Status
                              </button>
                            </li>
                          <?php } ?>
                          <?php if($canDeleteSchedule){ ?>
                            <?php if($canManageSchedule){ ?><li><hr class="dropdown-divider"></li><?php } ?>
                            <li>
                              <button type="button" class="dropdown-item text-danger" data-review-delete-open
                                data-id="<?= (int)$r['id'] ?>"
                                data-code="<?= e($r['kode']) ?>"
                                data-title="<?= e($rowTitle) ?>">
                                <i class="bi bi-trash3"></i>Hapus
                              </button>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                    <?php } ?>
                  </div>
                </td>
              </tr>
            <?php } } ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade review-action-modal" id="reviewDeadlineModal" tabindex="-1" aria-labelledby="reviewDeadlineModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="post" data-review-action-form>
            <?= csrf_field(); ?><input type="hidden" name="action" value="reviu_deadline_update">
            <input type="hidden" name="id" value="">
            <div class="modal-header">
              <div>
                <h5 class="modal-title" id="reviewDeadlineModalLabel">Atur Deadline</h5>
                <div class="text-muted small" data-review-deadline-code></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small mb-3">Ubah batas waktu penyelesaian kegiatan reviu.</p>
              <div class="border rounded-3 p-3 bg-light mb-3">
                <div class="fw-semibold" data-review-deadline-title>-</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Deadline saat ini</label>
                <input type="date" name="deadline" class="form-control" required>
              </div>
              <div class="alert alert-light border mb-0 small" data-review-deadline-info></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success">Simpan Deadline</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade review-action-modal" id="reviewNoteModal" tabindex="-1" aria-labelledby="reviewNoteModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="post" data-review-action-form>
            <?= csrf_field(); ?><input type="hidden" name="action" value="reviu_note_update">
            <input type="hidden" name="id" value="">
            <div class="modal-header">
              <div>
                <h5 class="modal-title" id="reviewNoteModalLabel">Atur Catatan Status</h5>
                <div class="text-muted small" data-review-note-code></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small mb-3">Catatan ini tidak mengubah status atau tahap kegiatan.</p>
              <div class="border rounded-3 p-3 bg-light mb-3">
                <div class="fw-semibold" data-review-note-title>-</div>
                <div class="small text-muted mt-1">Status/tahap saat ini: <span class="fw-semibold" data-review-note-status>-</span></div>
              </div>
              <div class="mb-0">
                <label class="form-label">Catatan kondisi kegiatan</label>
                <textarea name="catatan" class="form-control" rows="4" maxlength="5000" placeholder="Contoh: Menunggu dokumen tambahan dari unit kerja."></textarea>
                <div class="form-text">Kosongkan lalu simpan untuk menghapus catatan dari tabel.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success">Simpan Catatan</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade review-action-modal" id="reviewDeleteModal" tabindex="-1" aria-labelledby="reviewDeleteModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="post" data-review-action-form>
            <?= csrf_field(); ?><input type="hidden" name="action" value="reviu_delete">
            <input type="hidden" name="reviu_id" value="">
            <div class="modal-header">
              <div>
                <h5 class="modal-title text-danger" id="reviewDeleteModalLabel">Hapus Jadwal Reviu</h5>
                <div class="text-muted small" data-review-delete-code></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small mb-3">Hapus jadwal reviu sesuai hak akses dan aturan sistem.</p>
              <p class="mb-2">Jadwal berikut akan dihapus:</p>
              <div class="border rounded-3 p-3 bg-light">
                <div class="fw-semibold" data-review-delete-title>-</div>
              </div>
              <p class="text-muted small mt-3 mb-0">Tindakan ini mengikuti aturan hapus yang sudah ada dan akan menghapus data jadwal beserta lampiran terkait.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger">Ya, Hapus</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php } ?>

  <?php if($tab==='asg'){ ?>
    <?php if($rev){ ?>
      <div class="card-soft p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold"><?= e($rev['kode']) ?> &mdash; <?= e($rev['jenis_nama']) ?> / <?= e($rev['unit_nama']) ?></div>
            <div class="text-muted">Periode: <?= e($rev['periode_mulai']) ?> &rarr; <?= e($rev['periode_selesai']) ?> &middot; Status: <b><?= e($rev['status']) ?></b></div>
          </div>
          <a class="btn btn-outline-secondary" href="<?= e(review_url('jadwal')) ?>">Kembali</a>
        </div>
      </div>

      <?php if(in_array($role,['admin','super_admin','superadmin','moderator'], true) || is_auditor($role)){ ?>
      <div class="card-soft p-3 mb-3">
        <h6 class="mb-2">Tambah Penugasan</h6>
        <form method="post" class="row g-2">
          <?= csrf_field(); ?><input type="hidden" name="action" value="assign_add">
          <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
          <div class="col-md-2">
            <select name="rrole" class="form-select" required>
              <option value="AUDITOR">AUDITOR</option>
              <option value="AUDITEE">AUDITEE</option>
            </select>
          </div>
          <?php if(!empty($users)){ ?>
          <div class="col-md-3">
            <select name="user_id" class="form-select">
              <option value="">Pilih user sesuai peran&hellip;</option>
              <?php foreach($users as $us){ ?>
                <option value="<?= (int)$us['id'] ?>"
                  data-role="<?= e($us['type']) ?>"
                  data-name="<?= e($us['nama']) ?>"
                  data-email="<?= e($us['email']) ?>"
                  data-peran="<?= e($us['peran']) ?>">
                  <?= e($us['nama'] . ($us['peran'] !== '' ? ' - '.$us['peran'] : '')) ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <?php } else { ?><input type="hidden" name="user_id" value="0"><?php } ?>
          <div class="col-md-3"><input name="nama" class="form-control" placeholder="Nama (opsional jika pilih user)"></div>
          <div class="col-md-3"><input name="email" class="form-control" placeholder="Email (opsional jika pilih user)"></div>
          <div class="col-md-1"><button class="btn btn-success w-100">Tambah</button></div>
        </form>
        <small class="text-muted d-block mt-1">Bisa pilih dari user yang ada, atau isi manual Nama/Email.</small>
        <?php if(!empty($users)){ ?>
        <script>
        (function(){
          const roleSelect = document.querySelector('select[name="rrole"]');
          const userSelect = document.querySelector('select[name="user_id"]');
          if (!roleSelect || !userSelect) { return; }
          const namaInput = document.querySelector('input[name="nama"]');
          const emailInput = document.querySelector('input[name="email"]');
          const filterOptions = () => {
            const selectedRole = roleSelect.value || '';
            let shouldReset = false;
            Array.from(userSelect.options).forEach((opt) => {
              const optRole = opt.dataset.role || '';
              if (!optRole) {
                opt.hidden = false;
                return;
              }
              const visible = selectedRole === '' ? true : optRole === selectedRole;
              opt.hidden = !visible;
              if (!visible && opt.selected) { shouldReset = true; }
            });
            if (shouldReset) {
              userSelect.value = '';
              if (namaInput) { namaInput.value = ''; }
              if (emailInput) { emailInput.value = ''; }
            }
          };
          const dispatchChange = () => {
            let ev;
            if (typeof Event === 'function') {
              ev = new Event('change');
            } else {
              ev = document.createEvent('Event');
              ev.initEvent('change', true, false);
            }
            userSelect.dispatchEvent(ev);
          };
          roleSelect.addEventListener('change', () => {
            filterOptions();
            dispatchChange();
          });
          userSelect.addEventListener('change', () => {
            const opt = userSelect.selectedOptions[0];
            if (!opt || !opt.dataset.role) {
              if (namaInput) { namaInput.value = ''; }
              if (emailInput) { emailInput.value = ''; }
              return;
            }
            if (namaInput) { namaInput.value = opt.dataset.name || ''; }
            if (emailInput) { emailInput.value = opt.dataset.email || ''; }
          });
          filterOptions();
          dispatchChange();
        })();
        </script>
        <?php } ?>
      </div>
      <?php } ?>

      <div class="card-soft p-3">
        <h6 class="mb-2">Daftar Penugasan</h6>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Peran</th><th>Nama</th><th>Email</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <?php if(empty($assign)){ ?>
                <tr><td colspan="5"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
              <?php } else { foreach($assign as $a){ ?>
                <tr>
                  <td><span class="badge <?= $a['role']==='AUDITOR'?'bg-primary':'bg-info text-dark' ?>"><?= e($a['role']) ?></span></td>
                  <td><?= e($a['nama'] ?: ('User#'.(int)$a['user_id'])) ?></td>
                  <td><?= e($a['email']) ?></td>
                  <td><?= e($a['created_at']) ?></td>
                  <td class="text-end">
                    <?php if(in_array($role,['admin','super_admin','superadmin','moderator'])){ ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Hapus penugasan ini?')">
                        <?= csrf_field(); ?><input type="hidden" name="action" value="assign_del">
                        <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                      </form>
                    <?php } ?>
                  </td>
                </tr>
              <?php } } ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php } else { ?>
      <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>Pilih jadwal dari tab <b>Jadwal</b> lalu klik kode reviu terlebih dahulu untuk mengelola <b>Penugasan</b>.</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(review_url('jadwal')) ?>">Buka Daftar Jadwal</a>
      </div>
    <?php } ?>
  <?php } ?>

  <?php if($tab==='dok'){ ?>
    <?php if($rev){ ?>
      <div class="card-soft p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold"><?= e($rev['kode']) ?> &mdash; <?= e($rev['jenis_nama']) ?> / <?= e($rev['unit_nama']) ?></div>
            <div class="text-muted">Periode: <?= e($rev['periode_mulai']) ?> &rarr; <?= e($rev['periode_selesai']) ?> &middot; Status: <b><?= e($rev['status']) ?></b></div>
          </div>
          <a class="btn btn-outline-secondary" href="<?= e(review_url('jadwal')) ?>">Kembali</a>
        </div>
      </div>

      <div class="card-soft p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Unggah Dokumen</h6>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('dokumen_export.php', ['rid' => (int)$rev['id']])) ?>">Export Dokumen</a>
        </div>
        <form method="post" enctype="multipart/form-data" class="row g-2">
          <?= csrf_field(); ?><input type="hidden" name="action" value="doc_upload">
          <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
          <div class="col-md-3">
            <?php $docUploadOptions = is_auditee() ? ['Pelaksanaan','DukunganTL'] : ['Standar','KertasKerja','Pelaksanaan','Laporan','DukunganTL']; ?>
            <select name="kategori" class="form-select" required>
              <?php foreach($docUploadOptions as $k){ ?>
                <option value="<?= e($k) ?>"><?= e($k) ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-5"><input name="judul" class="form-control" placeholder="Judul dokumen" required></div>
          <div class="col-md-3"><input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required></div>
          <div class="col-md-1"><button class="btn btn-success w-100">Upload</button></div>
        </form>
        <small class="text-muted d-block mt-1">Tipe diizinkan: PDF/JPG/PNG/DOC/DOCX/XLS/XLSX · Maks 8MB.</small>
      </div>

      <div class="row g-3">
        <?php $__kategori_list = ['Standar','KertasKerja','Pelaksanaan','Laporan','DukunganTL'];
        foreach ($__kategori_list as $k) { ?>
          <div class="col-lg-6">
            <div class="card-soft p-3">
              <h6 class="mb-2"><?= e($k) ?></h6>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead><tr><th>Judul</th><th>Waktu</th><th class="text-end">Aksi</th></tr></thead>
                  <tbody>
                    <?php if (empty($docs[$k])) { ?>
                      <tr><td colspan="3"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
                    <?php } else { foreach ($docs[$k] as $d) { ?>
                      <tr>
                        <td><?= e($d['judul']) ?></td>
                        <td><?= e($d['created_at']) ?></td>
                        <td class="text-end">
                          <span class="d-inline-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('download.php', ['id' => (int)$d['id'], 'mode' => 'view'])) ?>" target="_blank" rel="noopener">Lihat</a>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(endpoint_url('download.php', ['id' => (int)$d['id'], 'mode' => 'download'])) ?>" target="_blank" rel="noopener">Unduh</a>
                          </span>
                          <?php if(in_array($role,['admin','super_admin','superadmin','moderator'])){ ?>
                          <form method="post" class="d-inline" onsubmit="return confirm('Hapus dokumen ini?')">
                            <?= csrf_field(); ?><input type="hidden" name="action" value="doc_delete">
                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                          </form>
                          <?php } ?>
                        </td>
                      </tr>
                    <?php } } ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>

      <?php
        $canComment = is_assigned((int)$rev['id']);
        $docComments = [];
        $docCommentTree = [];
        $replyMap = [];
        if ($canComment && ensure_review_comments_schema($conn)) {
          if ($cstmt = $conn->prepare("SELECT id, review_id, user_id, username, user_name, parent_id, body, created_at FROM review_comments WHERE review_id=? ORDER BY created_at ASC")) {
            $cstmt->bind_param("i", $rev['id']);
            if ($cstmt->execute()) {
              $cres = $cstmt->get_result();
              $docComments = $cres ? $cres->fetch_all(MYSQLI_ASSOC) : [];
            }
            $cstmt->close();
          }
          foreach ($docComments as $c) {
            $pid = (int)($c['parent_id'] ?? 0);
            if ($pid > 0) {
              $replyMap[$pid][] = $c;
            } else {
              $docCommentTree[] = $c;
            }
          }
        }
      ?>
      <div class="card-soft p-3 mb-3" id="komentar">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Komentar Dokumen</h6>
        </div>
        <?php if(!$canComment): ?>
          <div class="alert alert-warning mb-0">Anda tidak termasuk tim penugasan, sehingga tidak dapat berkomentar.</div>
        <?php else: ?>
          <form method="post" class="mb-3">
            <?= csrf_field(); ?><input type="hidden" name="action" value="comment_add">
            <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
            <input type="hidden" name="parent_id" value="">
            <textarea name="body" class="form-control" rows="3" maxlength="2000" placeholder="Tulis komentar..." required></textarea>
            <button class="btn btn-sm btn-success mt-2">Kirim</button>
          </form>
          <?php if(empty($docCommentTree)): ?>
            <div class="empty-state">Belum ada komentar.</div>
          <?php else: foreach($docCommentTree as $c): ?>
            <?php
              $cName = trim((string)($c['user_name'] ?? $c['username'] ?? 'User'));
              $cName = $cName !== '' ? $cName : 'User';
              $cTime = $c['created_at'] ?? '';
            ?>
            <div class="border rounded-3 p-2 mb-2">
              <div class="small text-muted"><?= e($cName) ?><?= $cTime ? ' · '.e($cTime) : '' ?></div>
              <div><?= nl2br(e($c['body'] ?? '')) ?></div>
              <button type="button" class="btn btn-sm btn-link p-0 comment-reply-toggle" data-reply="reply-<?= (int)$c['id'] ?>">Balas</button>
              <form method="post" class="mt-2 d-none" id="reply-<?= (int)$c['id'] ?>">
                <?= csrf_field(); ?><input type="hidden" name="action" value="comment_add">
                <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
                <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>">
                <textarea name="body" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Tulis balasan..." required></textarea>
                <button class="btn btn-sm btn-outline-success mt-2">Kirim Balasan</button>
              </form>
              <?php if(!empty($replyMap[$c['id']])): ?>
                <div class="mt-2 ms-3">
                  <?php foreach($replyMap[$c['id']] as $r): ?>
                    <?php
                      $rName = trim((string)($r['user_name'] ?? $r['username'] ?? 'User'));
                      $rName = $rName !== '' ? $rName : 'User';
                      $rTime = $r['created_at'] ?? '';
                    ?>
                    <div class="border rounded-3 p-2 mb-2 bg-light">
                      <div class="small text-muted"><?= e($rName) ?><?= $rTime ? ' · '.e($rTime) : '' ?></div>
                      <div><?= nl2br(e($r['body'] ?? '')) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        <?php endif; ?>
      </div>

    <?php } else { ?>
      <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>Pilih jadwal dari tab <b>Jadwal</b> lalu klik kode reviu terlebih dahulu untuk mengelola <b>Dokumen</b>.</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(review_url('jadwal')) ?>">Buka Daftar Jadwal</a>
      </div>
    <?php } ?>
  <?php } ?>

  <?php if($tab==='chr'){ ?>
    <?php if($rev){ ?>
      <div class="card-soft p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold"><?= e($rev['kode']) ?> &mdash; <?= e($rev['jenis_nama']) ?> / <?= e($rev['unit_nama']) ?></div>
            <div class="text-muted">Periode: <?= e($rev['periode_mulai']) ?> &rarr; <?= e($rev['periode_selesai']) ?> &middot; Status: <b><?= e($rev['status']) ?></b></div>
      </div>
      <a class="btn btn-outline-secondary" href="<?= e(review_url('jadwal')) ?>">Kembali</a>
    </div>
  </div>

      <?php
        $canManageChrSheet = in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role) || is_director_like($role);
        $uapaTitles = [
          'uapa' => 'UAPA',
          'uappae1' => 'UAPPA-E1',
          'uappaw' => 'UAPPA-W',
          'uakpa' => 'UAKPA',
        ];
        $perbaikanItems = $chrSheet['perbaikan_list'] ?? [];
        $perbaikanCount = max(5, count($perbaikanItems));
        while (count($perbaikanItems) < $perbaikanCount) { $perbaikanItems[] = ''; }
        $anggotaItems = $chrSheet['anggota_list'] ?? [];
        $anggotaCount = max(3, count($anggotaItems));
        while (count($anggotaItems) < $anggotaCount) { $anggotaItems[] = ['label' => 'Anggota', 'nama' => '', 'nip' => '']; }
        $direkturSignature = $chrSheet['direktur_signature'] ?? '';
        $ketuaSignature = $chrSheet['ketua_signature'] ?? '';
        $anggotaSignatures = $chrSheet['anggota_signatures'] ?? [];
        $updatedAtLabel = '';
        if ($chrSheetUpdatedAt) {
          try {
            $dtChrUpdated = new DateTime($chrSheetUpdatedAt);
            $updatedAtLabel = $dtChrUpdated->format('d/m/Y H:i');
          } catch (Throwable $e) {
            $updatedAtLabel = (string)$chrSheetUpdatedAt;
          }
        }
        $chrSopSubmitReady = true;
        $chrSopSubmitErrors = [];
        $chrSopSignerSelected = 0;
        $chrSopProfileComplete = 0;
        $chrSopRequiredCount = 0;
        if ($useDynamicChr && function_exists('chr_sop_collect_signers')) {
          $chrSopSigners = chr_sop_collect_signers($chrSheet);
          $chrSopRequiredCount = max(3, count($chrSopSigners));
          $seenSignerIds = [];
          foreach ($chrSopSigners as $signer) {
            if (!is_array($signer)) { continue; }
            $signerUserId = (int)($signer['user_id'] ?? 0);
            if ($signerUserId < 1) { continue; }
            $chrSopSignerSelected++;
            if (isset($seenSignerIds[$signerUserId])) {
              $chrSopSubmitErrors[] = 'Pegawai penanda tangan tidak boleh dobel.';
            }
            $seenSignerIds[$signerUserId] = true;
            $profileComplete = trim((string)($signer['nama'] ?? '')) !== ''
              && trim((string)($signer['nip'] ?? '')) !== ''
              && trim((string)($signer['jabatan'] ?? '')) !== ''
              && (int)($signer['unit_id'] ?? 0) > 0
              && trim((string)($signer['unit'] ?? '')) !== '';
            if ($profileComplete) { $chrSopProfileComplete++; }
          }
          if (function_exists('chr_sop_required_signers_ready')) {
            $chrSopSubmitReady = chr_sop_required_signers_ready($chrSheet, $chrSopSubmitErrors);
          }
        }
      ?>
      <div class="card-soft p-3 mb-3">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
          <div>
            <h6 class="mb-0">Form Catatan Hasil Reviu</h6>
            <?php if($updatedAtLabel){ ?><div class="small text-muted">Terakhir diperbarui: <?= e($updatedAtLabel) ?></div><?php } ?>
          </div>
          <?php if($rev && (is_admin_like($role) || is_auditor($role) || is_auditee())){ ?>
            <div class="d-flex gap-2">
              <?php if($useDynamicChr){ ?>
                <?php $chrFinalExportReady = $chrWorkflowStatus === 'approved' && $chrSopSubmitReady; ?>
                <button type="button" class="btn btn-sm btn-outline-success" data-chr-preview-url="<?= e(endpoint_url('chr_sop_export.php', ['rid' => (int)$rev['id'], 'mode' => 'preview', 'format' => 'view'])) ?>">Pratinjau Dokumen</button>
                <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= e(endpoint_url('chr_sop_export.php', ['rid' => (int)$rev['id'], 'mode' => 'preview', 'format' => 'docx'])) ?>">Unduh Word Pratinjau</a>
                <a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="<?= e(endpoint_url('chr_sop_export_pdf.php', ['rid' => (int)$rev['id'], 'mode' => 'preview'])) ?>">Unduh PDF Pratinjau</a>
                <?php if($chrFinalExportReady){ ?>
                  <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?= e(endpoint_url('chr_sop_export.php', ['rid' => (int)$rev['id'], 'mode' => 'final', 'format' => 'docx'])) ?>">Unduh Word Final</a>
                  <a class="btn btn-sm btn-danger" target="_blank" rel="noopener" href="<?= e(endpoint_url('chr_sop_export_pdf.php', ['rid' => (int)$rev['id'], 'mode' => 'final'])) ?>">Unduh PDF Final</a>
                <?php } else { ?>
                  <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true" title="Dokumen final hanya dapat diekspor setelah seluruh pengesahan selesai.">Final belum tersedia</span>
                <?php } ?>
              <?php } else { ?>
                <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= e(endpoint_url('chr_export.php', ['rid' => (int)$rev['id']])) ?>">Export CHR (Word)</a>
                <a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="<?= e(endpoint_url('chr_export_pdf.php', ['rid' => (int)$rev['id']])) ?>">Export CHR (PDF)</a>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
        <?php if($canManageChrSheet){ ?>
        <form method="post" class="row g-3" id="chrTemplateForm">
          <?= csrf_field(); ?>
          <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
          <?php if($useDynamicChr){ ?>
            <div class="col-12">
              <?= chr_render_dynamic_form($chrTemplate, $chrSheet, ['rev' => $rev, 'employees' => $chrEmployeeOptions, 'current_user_id' => (int)($_SESSION['user']['id'] ?? 0), 'locked' => $chrWorkflowLocked, 'workflow_status' => $chrWorkflowStatus]) ?>
            </div>
            <?php if($chrCanReturnCurrent && in_array($chrWorkflowStatus, ['waiting_signatures','partially_signed'], true)): ?>
              <div class="col-12">
                <label class="form-label fw-semibold">Catatan Pengembalian</label>
                <textarea class="form-control" name="return_note" rows="2" placeholder="Wajib diisi jika mengembalikan untuk perbaikan"></textarea>
              </div>
            <?php endif; ?>
            <?php if($chrWorkflowStatus === 'draft'): ?>
              <div class="col-12">
                <div class="chr-submit-readiness d-flex flex-wrap align-items-center justify-content-between gap-2" data-chr-submit-readiness data-min-required="3">
                  <div>
                    <div class="fw-semibold text-success">Kesiapan Pengajuan</div>
                    <div class="small text-muted">
                      Penanda tangan dipilih: <span data-role="signer-count"><?= (int)$chrSopSignerSelected ?></span><span data-role="required-count"><?= $chrSopRequiredCount ? ' dari minimal '.(int)$chrSopRequiredCount : '' ?></span>.
                      Profil lengkap: <span data-role="profile-count"><?= (int)$chrSopProfileComplete ?></span><span data-role="profile-total"><?= $chrSopSignerSelected ? ' dari '.(int)$chrSopSignerSelected : '' ?></span>.
                    </div>
                  </div>
                  <span class="badge <?= $chrSopSubmitReady ? 'bg-success' : 'bg-warning text-dark' ?>" data-role="readiness-badge"><?= $chrSopSubmitReady ? 'Siap diajukan' : 'Lengkapi penanda tangan' ?></span>
                </div>
              </div>
            <?php endif; ?>
            <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
              <?php if($chrWorkflowStatus === 'draft'): ?>
                <button type="submit" name="action" value="chr_sheet_save" class="btn btn-outline-success">Simpan Draft</button>
                <button type="submit" name="action" value="chr_sop_submit" class="btn btn-success" data-chr-submit-button<?= $chrSopSubmitReady ? '' : ' disabled' ?> onclick="return confirm('Ajukan <?= e($chrDocName) ?> untuk pengesahan? Setelah diajukan, isi dokumen akan dikunci sampai dikembalikan untuk perbaikan.');">Ajukan Pengesahan</button>
              <?php elseif($chrWorkflowStatus === 'returned'): ?>
                <button type="submit" name="action" value="chr_sop_reopen" class="btn btn-warning" onclick="return confirm('Buka <?= e($chrDocName) ?> untuk perbaikan? Tanda tangan yang sudah ada akan direset.');">Buka untuk Perbaikan</button>
              <?php elseif(in_array($chrWorkflowStatus, ['waiting_signatures','partially_signed'], true)): ?>
                <?php if($chrCanReturnCurrent): ?>
                  <button type="submit" name="action" value="chr_sop_return" class="btn btn-outline-danger">Kembalikan untuk Perbaikan</button>
                <?php endif; ?>
                <button type="submit" name="action" value="chr_sheet_save" class="btn btn-success">Simpan Tanda Tangan</button>
              <?php else: ?>
                <span class="btn btn-outline-secondary disabled" aria-disabled="true"><?= e($chrDocName) ?> sudah disahkan</span>
              <?php endif; ?>
            </div>
          <?php } else { ?>
          <?php if(function_exists('chr_rkakl_approval_mode') && chr_rkakl_approval_mode($chrSheet, $rev ?: null) === 'legacy'): ?>
          <div class="col-12">
            <div class="alert alert-warning border mb-0">
              <strong>Mode Legacy RKAKL.</strong>
              Dokumen ini masih memakai blok tanda tangan manual lama. Data nama, NIP, lokasi, bulan/tahun, dan tanda tangan lama tetap dipertahankan serta tidak dikonversi otomatis ke workflow pengesahan baru.
            </div>
          </div>
          <?php endif; ?>
          <div class="col-12">
            <div class="border rounded-3 p-3 bg-white">
              <h6 class="fw-semibold mb-3">Halaman Sampul</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-uppercase text-muted">Header Baris 1</label>
                  <input type="text" class="form-control" name="chr_sheet[header_line1]" value="<?= e($chrSheet['header_line1'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase text-muted">Header Baris 2</label>
                  <input type="text" class="form-control" name="chr_sheet[header_line2]" value="<?= e($chrSheet['header_line2'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase text-muted">Judul Dokumen</label>
                  <input type="text" class="form-control" name="chr_sheet[cover_title]" value="<?= e($chrSheet['cover_title'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase text-muted">Subjudul (Unit / Jenis)</label>
                  <input type="text" class="form-control" name="chr_sheet[cover_subtitle1]" value="<?= e($chrSheet['cover_subtitle1'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                  <label class="form-label small text-uppercase text-muted">Kalimat Periode</label>
                  <input type="text" class="form-control" name="chr_sheet[cover_period_prefix]" value="<?= e($chrSheet['cover_period_prefix'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-uppercase text-muted">Tanggal (highlight)</label>
                  <input type="text" class="form-control" name="chr_sheet[cover_period_date]" value="<?= e($chrSheet['cover_period_date'] ?? '') ?>" placeholder="30 JUNI 2025">
                </div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded-3 p-3 bg-white">
              <h6 class="fw-semibold mb-3">Penyusun Dokumen</h6>
              <?php foreach ($chrSheet['drafter'] as $idx => $drafter){ ?>
              <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4">
                  <label class="form-label small text-muted">Label Baris <?= $idx+1 ?></label>
                  <input type="text" class="form-control form-control-sm" name="chr_sheet[drafter][<?= $idx ?>][label]" value="<?= e($drafter['label'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted">Nama / Jabatan</label>
                  <input type="text" class="form-control form-control-sm" name="chr_sheet[drafter][<?= $idx ?>][nama]" value="<?= e($drafter['nama'] ?? '') ?>" placeholder="Nama lengkap">
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted">Tanggal</label>
                  <input type="text" class="form-control form-control-sm" name="chr_sheet[drafter][<?= $idx ?>][tanggal]" value="<?= e($drafter['tanggal'] ?? '') ?>" placeholder="15/07/2025">
                </div>
              </div>
              <?php } ?>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded-3 p-3 bg-white">
              <h6 class="fw-semibold mb-3">Identitas Entitas</h6>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width:140px;">Level</th>
                      <th>Nama Unit</th>
                      <th style="width:80px;" class="text-center">Centang</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($chrSheet['uapa_opts'] as $idx => $opt){
                      $key = (string)($opt['key'] ?? ('opt'.$idx));
                      $title = $uapaTitles[$key] ?? strtoupper($key);
                    ?>
                    <tr>
                      <td class="fw-semibold"><?= e($title) ?></td>
                      <td>
                        <input type="hidden" name="chr_sheet[uapa_opts][<?= $idx ?>][key]" value="<?= e($key) ?>">
                        <input type="text" class="form-control form-control-sm" name="chr_sheet[uapa_opts][<?= $idx ?>][label]" value="<?= e($opt['label'] ?? '') ?>">
                      </td>
                      <td class="text-center">
                        <input type="hidden" name="chr_sheet[uapa_opts][<?= $idx ?>][checked]" value="0">
                        <input class="form-check-input" type="checkbox" name="chr_sheet[uapa_opts][<?= $idx ?>][checked]" value="1" <?= !empty($opt['checked'])?'checked':'' ?>>
                      </td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded-3 p-3 bg-white">
              <h6 class="fw-semibold mb-3">Uraian Catatan Hasil Reviu</h6>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width:140px;">Bagian LK</th>
                      <th>Uraian</th>
                      <th style="width:160px;">Indeks KKR</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($chrSheet['lk_items'] as $idx => $item){ ?>
                    <tr>
                      <td class="fw-semibold">
                        <?= e($item['label'] ?? '') ?>
                        <input type="hidden" name="chr_sheet[lk_items][<?= $idx ?>][label]" value="<?= e($item['label'] ?? '') ?>">
                      </td>
                      <td>
                        <textarea class="form-control form-control-sm" rows="2" name="chr_sheet[lk_items][<?= $idx ?>][uraian]"><?= e($item['uraian'] ?? '') ?></textarea>
                      </td>
                      <td>
                        <input type="text" class="form-control form-control-sm" name="chr_sheet[lk_items][<?= $idx ?>][indeks]" value="<?= e($item['indeks'] ?? '') ?>">
                      </td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="border rounded-3 p-3 bg-white h-100">
              <h6 class="fw-semibold mb-3">Koreksi / Perbaikan yang Belum Dilakukan</h6>
              <?php foreach ($perbaikanItems as $idx => $value){ ?>
              <div class="input-group input-group-sm mb-2">
                <span class="input-group-text"><?= $idx+1 ?>.</span>
                <input type="text" class="form-control" name="chr_sheet[perbaikan_list][<?= $idx ?>]" value="<?= e($value) ?>">
              </div>
              <?php } ?>
              <div class="form-text">Kosongkan baris jika tidak diperlukan.</div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="border rounded-3 p-3 bg-white h-100">
              <label class="form-label fw-semibold">Hal-hal Lain yang Perlu Diungkapkan</label>
              <textarea class="form-control mb-3" rows="4" name="chr_sheet[hal_lain]"><?= e($chrSheet['hal_lain'] ?? '') ?></textarea>
              <label class="form-label fw-semibold">Rekomendasi</label>
              <textarea class="form-control" rows="4" name="chr_sheet[rekomendasi]"><?= e($chrSheet['rekomendasi'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded-3 p-3 bg-white">
              <h6 class="fw-semibold mb-3">Blok Tanda Tangan</h6>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small text-muted">Label Direktur</label>
                  <input type="text" class="form-control form-control-sm mb-2" name="chr_sheet[direktur][label]" value="<?= e($chrSheet['direktur']['label'] ?? 'Direktur') ?>">
                  <label class="form-label small text-muted mb-0">Nama Direktur</label>
                  <input type="text" class="form-control form-control-sm mb-2" name="chr_sheet[direktur][nama]" value="<?= e($chrSheet['direktur']['nama'] ?? '') ?>">
                  <label class="form-label small text-muted mb-0">NIP Direktur</label>
                  <input type="text" class="form-control form-control-sm" name="chr_sheet[direktur][nip]" value="<?= e($chrSheet['direktur']['nip'] ?? '') ?>">
                  <div class="chr-signature-field mt-3" data-chr-signature>
                    <label class="form-label small text-muted d-block mb-1">Tanda Tangan Direktur (Drag)</label>
                    <div class="signature-pad-wrapper chr-signature-pad">
                      <canvas data-role="canvas"></canvas>
                      <div class="sig-overlay text-muted">Tarik pointer atau sentuh untuk menggambar tanda tangan.</div>
                    </div>
                    <div class="chr-signature-actions d-flex flex-wrap gap-2 mt-2">
                      <button type="button" class="btn btn-sm btn-outline-secondary" data-action="sig-clear">Bersihkan</button>
                      <button type="button" class="btn btn-sm btn-primary" data-action="sig-save" disabled>Simpan Tanda Tangan</button>
                    </div>
                    <div class="chr-signature-preview mt-2" data-role="preview" <?= $direkturSignature ? '' : 'hidden' ?>>
                      <div class="small text-muted mb-1">Pratinjau tersimpan</div>
                      <img data-role="preview-img" alt="Tanda tangan Direktur" <?= $direkturSignature ? 'src="'.e($direkturSignature).'"' : '' ?> <?= $direkturSignature ? '' : 'hidden' ?>>
                    </div>
                    <input type="hidden" data-role="input" name="chr_sheet[direktur_signature]" value="<?= e($direkturSignature) ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted">Lokasi</label>
                  <input type="text" class="form-control form-control-sm mb-2" name="chr_sheet[ketua][lokasi]" value="<?= e($chrSheet['ketua']['lokasi'] ?? '') ?>">
                  <label class="form-label small text-muted">Bulan / Tahun</label>
                  <input type="text" class="form-control form-control-sm mb-2" name="chr_sheet[ketua][waktu]" value="<?= e($chrSheet['ketua']['waktu'] ?? '') ?>" placeholder="Juli 2025">
                  <label class="form-label small text-muted">Jabatan</label>
                  <input type="text" class="form-control form-control-sm mb-2" name="chr_sheet[ketua][jabatan]" value="<?= e($chrSheet['ketua']['jabatan'] ?? '') ?>">
                  <label class="form-label small text-muted mb-0">Nama Ketua Tim</label>
                  <input type="text" class="form-control form-control-sm mb-2" name="chr_sheet[ketua][nama]" value="<?= e($chrSheet['ketua']['nama'] ?? '') ?>">
                  <label class="form-label small text-muted mb-0">NIP Ketua Tim</label>
                  <input type="text" class="form-control form-control-sm" name="chr_sheet[ketua][nip]" value="<?= e($chrSheet['ketua']['nip'] ?? '') ?>">
                  <div class="chr-signature-field mt-3" data-chr-signature>
                    <label class="form-label small text-muted d-block mb-1">Tanda Tangan Ketua Tim (Drag)</label>
                    <div class="signature-pad-wrapper chr-signature-pad">
                      <canvas data-role="canvas"></canvas>
                      <div class="sig-overlay text-muted">Tarik pointer atau sentuh untuk menggambar tanda tangan.</div>
                    </div>
                    <div class="chr-signature-actions d-flex flex-wrap gap-2 mt-2">
                      <button type="button" class="btn btn-sm btn-outline-secondary" data-action="sig-clear">Bersihkan</button>
                      <button type="button" class="btn btn-sm btn-primary" data-action="sig-save" disabled>Simpan Tanda Tangan</button>
                    </div>
                    <div class="chr-signature-preview mt-2" data-role="preview" <?= $ketuaSignature ? '' : 'hidden' ?>>
                      <div class="small text-muted mb-1">Pratinjau tersimpan</div>
                      <img data-role="preview-img" alt="Tanda tangan Ketua Tim" <?= $ketuaSignature ? 'src="'.e($ketuaSignature).'"' : '' ?> <?= $ketuaSignature ? '' : 'hidden' ?>>
                    </div>
                    <input type="hidden" data-role="input" name="chr_sheet[ketua_signature]" value="<?= e($ketuaSignature) ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted">Anggota Tim</label>
                  <?php foreach ($anggotaItems as $idx => $anggota){
                    $anggotaLabel = trim((string)($anggota['label'] ?? 'Anggota'));
                    if ($anggotaLabel === '') { $anggotaLabel = 'Anggota'; }
                    $anggotaSignatureValue = $anggotaSignatures[$idx] ?? '';
                  ?>
                  <div class="border rounded-3 p-2 mb-2 bg-body-tertiary">
                    <label class="form-label small text-muted mb-0">Label</label>
                    <input type="text" class="form-control form-control-sm mb-1" name="chr_sheet[anggota_list][<?= $idx ?>][label]" value="<?= e($anggota['label'] ?? '') ?>">
                    <label class="form-label small text-muted mb-0">Nama</label>
                    <input type="text" class="form-control form-control-sm mb-1" name="chr_sheet[anggota_list][<?= $idx ?>][nama]" value="<?= e($anggota['nama'] ?? '') ?>">
                    <label class="form-label small text-muted mb-0">NIP</label>
                    <input type="text" class="form-control form-control-sm" name="chr_sheet[anggota_list][<?= $idx ?>][nip]" value="<?= e($anggota['nip'] ?? '') ?>">
                    <div class="chr-signature-field mt-2" data-chr-signature>
                      <label class="form-label small text-muted d-block mb-1">Tanda Tangan <?= e($anggotaLabel) ?> (Drag)</label>
                      <div class="signature-pad-wrapper chr-signature-pad">
                        <canvas data-role="canvas"></canvas>
                        <div class="sig-overlay text-muted">Tarik pointer atau sentuh untuk menggambar tanda tangan.</div>
                      </div>
                      <div class="chr-signature-actions d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="sig-clear">Bersihkan</button>
                        <button type="button" class="btn btn-sm btn-primary" data-action="sig-save" disabled>Simpan Tanda Tangan</button>
                      </div>
                      <div class="chr-signature-preview mt-2" data-role="preview" <?= $anggotaSignatureValue ? '' : 'hidden' ?>>
                        <div class="small text-muted mb-1">Pratinjau tersimpan</div>
                        <img data-role="preview-img" alt="<?= e('Tanda tangan '.$anggotaLabel) ?>" <?= $anggotaSignatureValue ? 'src="'.e($anggotaSignatureValue).'"' : '' ?> <?= $anggotaSignatureValue ? '' : 'hidden' ?>>
                      </div>
                      <input type="hidden" data-role="input" name="chr_sheet[anggota_signatures][<?= $idx ?>]" value="<?= e($anggotaSignatureValue) ?>">
                    </div>
                  </div>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 text-end">
            <button type="submit" name="action" value="chr_sheet_save" class="btn btn-success">Simpan Form CHR</button>
          </div>
          <?php } ?>
        </form>
        <?php } else { ?>
          <div class="alert alert-info mb-0">Hubungi auditor untuk memperbarui form CHR<?= $useDynamicChr ? '.' : '. Anda tetap dapat mengunduh versi Word.' ?></div>
        <?php } ?>
      </div>

      <?php if(in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role)){ ?>
      <div class="card-soft p-3 mb-3">
        <h6 class="mb-2">Tambah CHR & Rekomendasi</h6>
        <form method="post" class="row g-2">
          <?= csrf_field(); ?><input type="hidden" name="action" value="chr_create">
          <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
          <div class="col-12"><textarea class="form-control" name="deskripsi" placeholder="Deskripsi temuan/CHR" required></textarea></div>
          <div class="col-12"><textarea class="form-control" name="rekomendasi" placeholder="Rekomendasi" required></textarea></div>
          <div class="col-md-3"><label class="form-label">Tenggat (due)</label><input type="date" name="due_date" class="form-control" required></div>
          <div class="col-md-2"><button class="btn btn-success w-100">Simpan</button></div>
        </form>
      </div>
      <?php } ?>

      <?php if((in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role)) && $chrEdit){ ?>
      <div class="card-soft p-3 mb-3 border border-warning-subtle">
        <h6 class="mb-2 text-warning">Edit CHR & Rekomendasi</h6>
        <form method="post" class="row g-2">
          <?= csrf_field(); ?><input type="hidden" name="action" value="chr_update">
          <input type="hidden" name="chr_id" value="<?= (int)$chrEdit['id'] ?>">
          <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
          <div class="col-12"><textarea class="form-control" name="deskripsi" placeholder="Deskripsi temuan/CHR" required><?= e($chrEdit['deskripsi']) ?></textarea></div>
          <div class="col-12"><textarea class="form-control" name="rekomendasi" placeholder="Rekomendasi" required><?= e($chrEdit['rekomendasi']) ?></textarea></div>
          <div class="col-md-3"><label class="form-label">Tenggat (due)</label><input type="date" name="due_date" class="form-control" required value="<?= e($chrEdit['due_date']) ?>"></div>
          <div class="col-md-2"><button class="btn btn-warning w-100 text-dark">Perbarui</button></div>
          <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= e(review_url('chr', ['rid' => (int)$rev['id']])) ?>">Batal</a></div>
        </form>
      </div>
      <?php } ?>

      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Daftar CHR &amp; Tindak Lanjut</h6>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>#</th><th>CHR</th><th>Rekomendasi</th><th>Tenggat</th><th>Early-Warning</th><th>Status TL</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if(empty($chr)){ ?>
                <tr><td colspan="7"><div class="empty-state">Anda Belum di Tugaskan</div></td></tr>
              <?php } else { 
                $warnDays = is_director_like($role) ? 15 : 2;
                foreach ($chr as $i => $c) {
                  try {
                    $dueDateObj = new DateTime($c['due_date']);
                  } catch (Throwable $e) {
                    $dueDateObj = new DateTime('today');
                  }
                  [$ewName,$ewColor,$ewDesc,$ewDiff] = ew_color($dueDateObj, $warnDays);
                  if ($warnDays >= 10 && $ewDiff >= 0 && $ewDiff <= $warnDays) {
                    $ewName .= ' (<= '.$warnDays.' hari)';
                  }
                  $st = $c['status_tl']; 
                  $cls = $st==='Selesai' ? 'success' : ($st==='Proses' ? 'warning text-dark' : 'secondary');
                  $rowEditing = $chrEdit && (int)$chrEdit['id'] === (int)$c['id'];
              ?>
                <tr<?= $rowEditing ? ' class="table-warning"' : '' ?>>
                  <td><?= $i+1 ?></td>
                  <td><?= nl2br(e($c['deskripsi'])) ?></td>
                  <td><?= nl2br(e($c['rekomendasi'])) ?></td>
                  <td><?= e($c['due_date']) ?></td>
                  <td>
                    <span class="badge" style="background: <?= e($ewColor) ?>; color:#fff"><?= e($ewName) ?></span>
                    <?php if($ewDesc !== ''): ?><div class="small text-muted"><?= e($ewDesc) ?></div><?php endif; ?>
                  </td>
                  <td><span class="badge bg-<?= $cls ?>"><?= e($st) ?></span></td>
                  <td>
                    <?php if(in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role)){ ?>
                    <div class="mb-2">
                      <a class="btn btn-sm btn-outline-secondary" href="<?= e(review_url('chr', ['rid' => (int)$rev['id'], 'edit_chr' => (int)$c['id']])) ?>">Edit</a>
                    </div>
                    <?php } ?>
                    <form method="post" class="d-flex gap-2">
                      <?= csrf_field(); ?><input type="hidden" name="action" value="tl_update">
                      <input type="hidden" name="chr_id" value="<?= (int)$c['id'] ?>">
                      <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
                      <select name="status_tl" class="form-select form-select-sm" style="width:auto">
                        <?php foreach(['Belum TL','Proses','Selesai'] as $o){ ?>
                          <option value="<?= e($o) ?>" <?= $o===$c['status_tl']?'selected':'' ?>><?= e($o) ?></option>
                        <?php } ?>
                      </select>
                      <input name="tl_catatan" class="form-control form-control-sm" style="width:220px" placeholder="Catatan TL" value="<?= e($c['tl_catatan']) ?>">
                      <button class="btn btn-sm btn-primary">Simpan</button>
                    </form>
                    <?php if(in_array($role,['admin','super_admin','superadmin'], true) || is_auditor($role)){ ?>
                    <form method="post" class="mt-2" onsubmit="return confirm('Hapus CHR ini?');">
                      <?= csrf_field(); ?><input type="hidden" name="action" value="chr_delete">
                      <input type="hidden" name="chr_id" value="<?= (int)$c['id'] ?>">
                      <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                    <?php } ?>
                  </td>
                </tr>
              <?php } } ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php
      $chrDocList = [];
      if (!empty($docs['KertasKerja'])) { $chrDocList = array_merge($chrDocList, $docs['KertasKerja']); }
      if (!empty($docs['Pelaksanaan'])) { $chrDocList = array_merge($chrDocList, $docs['Pelaksanaan']); }
      if (!empty($chrDocList)):
      ?>
      <div class="card-soft p-3 mt-3">
        <h6 class="mb-2">Lampiran CHR</h6>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Judul</th><th>Kategori</th><th>Diunggah</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <?php foreach($chrDocList as $d){ ?>
                <tr>
                  <td><?= e($d['judul']) ?></td>
                  <td><span class="badge bg-secondary"><?= e($d['kategori']) ?></span></td>
                  <td><?= e($d['created_at']) ?></td>
                  <td class="text-end">
                    <span class="d-inline-flex gap-2">
                      <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('download.php', ['id' => (int)$d['id'], 'mode' => 'view'])) ?>" target="_blank" rel="noopener">Lihat</a>
                      <a class="btn btn-sm btn-outline-success" href="<?= e(endpoint_url('download.php', ['id' => (int)$d['id'], 'mode' => 'download'])) ?>" target="_blank" rel="noopener">Unduh</a>
                    </span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    <?php } else { ?>
      <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>Pilih jadwal dari tab <b>Jadwal</b> lalu klik kode reviu terlebih dahulu untuk mengelola <b>CHR</b>.</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(review_url('jadwal')) ?>">Buka Daftar Jadwal</a>
      </div>
    <?php } ?>
  <?php } ?>

  <?php if($tab==='laporan'){ ?>
    <?php if($rev){ ?>
      <div class="card-soft p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold"><?= e($rev['kode']) ?> &mdash; <?= e($rev['jenis_nama']) ?> / <?= e($rev['unit_nama']) ?></div>
            <div class="text-muted">Status: <b><?= e($rev['status']) ?></b></div>
          </div>
          <a class="btn btn-outline-secondary" href="<?= e(review_url('jadwal')) ?>">Kembali</a>
        </div>
      </div>

      <?php $laporanData = $laporanRows[0] ?? null; $canManageLaporan = is_admin_like($role) || is_auditor($role) || $role === 'kepala_ski' || is_director_like($role); ?>
      <div class="card-soft p-3 mb-3">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Laporan Akhir</h6>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if($laporanData && !empty($laporanData['updated_at'])){ ?>
              <small class="text-muted me-2">Diperbarui: <?= e($laporanData['updated_at']) ?></small>
            <?php } elseif($laporanData && !empty($laporanData['created_at'])) { ?>
              <small class="text-muted me-2">Dibuat: <?= e($laporanData['created_at']) ?></small>
            <?php } ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('laporan_export.php', ['rid' => (int)$rev['id']])) ?>">Export Laporan</a>
          </div>
        </div>
        <?php if($canManageLaporan){ ?>
          <form method="post" enctype="multipart/form-data" class="row g-3" id="laporanAkhirForm">
            <?= csrf_field(); ?><input type="hidden" name="action" value="laporan_save">
            <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
            <div class="col-12">
              <label class="form-label">Ringkasan</label>
              <textarea class="form-control" name="ringkasan" rows="3" placeholder="Ringkasan laporan akhir"><?= e($laporanData['ringkasan'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Rekomendasi</label>
              <textarea class="form-control" name="rekomendasi" rows="3" placeholder="Rekomendasi akhir"><?= e($laporanData['rekomendasi'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Tindak Lanjut</label>
              <textarea class="form-control" name="tindak_lanjut" rows="3" placeholder="Status tindak lanjut"><?= e($laporanData['tindak_lanjut'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama Ka SKI</label>
              <input class="form-control" name="ttd_kepala_nama" placeholder="Nama lengkap Ka SKI" value="<?= e($laporanData['ttd_kepala_nama'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Tanggal TTD</label>
              <input type="date" class="form-control" name="ttd_kepala_tanggal" value="<?= e($laporanData['ttd_kepala_tanggal'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Metode Tanda Tangan</label>
              <?php $ttdModeDefault = 'canvas'; ?>
              <div class="d-flex flex-column gap-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="ttd_mode" id="ttdModeCanvas" value="canvas" <?= $ttdModeDefault === 'canvas' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="ttdModeCanvas">Digital (Canvas)</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="ttd_mode" id="ttdModeUpload" value="upload" <?= $ttdModeDefault === 'upload' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="ttdModeUpload">Upload Gambar</label>
                </div>
              </div>
            </div>
            <div class="col-12" id="ttdUploadSection" hidden>
              <label class="form-label">Berkas Tanda Tangan</label>
              <input type="file" class="form-control" name="ttd_file" accept=".png,.jpg,.jpeg">
              <small class="text-muted">PNG/JPG maks 2MB. Biarkan kosong jika tidak berubah.</small>
            </div>
            <div class="col-12" id="ttdCanvasSection">
              <label class="form-label d-flex align-items-center gap-2">
                <span>Tanda Tangan Digital (Drag/Draw)</span>
                <span class="badge bg-secondary text-uppercase">Beta</span>
              </label>
              <div class="signature-pad-wrapper">
                <canvas id="ttdSignaturePad"></canvas>
                <div class="sig-overlay text-muted">Tarik pointer atau gunakan layar sentuh untuk menggambar tanda tangan.</div>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2 justify-content-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sigClearBtn">Bersihkan</button>
                <button type="button" class="btn btn-sm btn-primary" id="sigSaveBtn" disabled>Simpan Tanda Tangan</button>
              </div>
              <input type="hidden" name="ttd_canvas" id="ttdCanvasInput">
              <small class="text-muted d-block mt-1">Canvas akan disimpan dalam bentuk gambar PNG saat formulir dikirim.</small>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary">Simpan Laporan Akhir</button>
            </div>
          </form>
        <?php } else { ?>
          <?php if($laporanData){ ?>
            <dl class="row mb-0">
              <?php if(!empty($laporanData['ringkasan'])){ ?>
                <dt class="col-sm-3">Ringkasan</dt>
                <dd class="col-sm-9"><?= nl2br(e($laporanData['ringkasan'])) ?></dd>
              <?php } ?>
              <?php if(!empty($laporanData['rekomendasi'])){ ?>
                <dt class="col-sm-3">Rekomendasi</dt>
                <dd class="col-sm-9"><?= nl2br(e($laporanData['rekomendasi'])) ?></dd>
              <?php } ?>
              <?php if(!empty($laporanData['tindak_lanjut'])){ ?>
                <dt class="col-sm-3">Tindak Lanjut</dt>
                <dd class="col-sm-9"><?= nl2br(e($laporanData['tindak_lanjut'])) ?></dd>
              <?php } ?>
              <?php if(!empty($laporanData['ttd_kepala_nama']) || !empty($laporanData['ttd_kepala_tanggal'])){ ?>
                <dt class="col-sm-3">Ka SKI</dt>
                <dd class="col-sm-9">
                  <?= e($laporanData['ttd_kepala_nama'] ?? '-') ?><br>
                  <?php if(!empty($laporanData['ttd_kepala_tanggal'])){ ?><small class="text-muted">TTD: <?= e($laporanData['ttd_kepala_tanggal']) ?></small><?php } ?>
                </dd>
              <?php } ?>
            </dl>
          <?php } else { ?>
            <div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div>
          <?php } ?>
        <?php } ?>
      </div>

      <?php if(!empty($laporanRows)){ ?>
        <div class="card-soft p-3 mb-3">
          <h6 class="mb-2">Riwayat Laporan Akhir</h6>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>#</th><th>Ringkasan</th><th>Ka SKI</th><th>Dibuat</th><th>Diperbarui</th><th class="text-end">Aksi</th></tr></thead>
              <tbody>
                <?php foreach($laporanRows as $i=>$row){ ?>
                  <?php
                    $ringkasanShort = (string)($row['ringkasan'] ?? '');
                    if (function_exists('mb_strlen') && mb_strlen($ringkasanShort) > 110) {
                      $ringkasanShort = mb_substr($ringkasanShort, 0, 110) . ' ';
                    } elseif (strlen($ringkasanShort) > 110) {
                      $ringkasanShort = substr($ringkasanShort, 0, 110) . ' ';
                    }
                  ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= nl2br(e($ringkasanShort)) ?></td>
                    <td><?= e($row['ttd_kepala_nama'] ?? '-') ?></td>
                    <td><?= e($row['created_at'] ?? '-') ?></td>
                    <td><?= e($row['updated_at'] ?? '-') ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('laporan_export.php', ['rid' => (int)$rev['id'], 'lapid' => (int)$row['id']])) ?>">Export</a>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php } ?>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card-soft p-3">
            <h6 class="mb-2">Verifikasi & TTD</h6>
            <form method="post" class="row g-2">
              <?= csrf_field(); ?><input type="hidden" name="action" value="verifikasi">
              <input type="hidden" name="reviu_id" value="<?= (int)$rev['id'] ?>">
              <div class="col-md-4">
                <select name="tahap" class="form-select" required>
                  <?php foreach(['CHR','Laporan','TL'] as $t){ ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php } ?>
                </select>
              </div>
              <div class="col-md-4"><input name="verifikator" class="form-control" placeholder="Direktur/Wadir/Ka. Unit SKI" required></div>
              <div class="col-md-4">
                <select name="v_status" class="form-select" required>
                  <?php foreach(['Menunggu','Disetujui','Ditolak'] as $t){ ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php } ?>
                </select>
              </div>
              <div class="col-12"><input name="v_catatan" class="form-control" placeholder="Catatan (opsional)"></div>
              <div class="col-12"><button class="btn btn-success">Simpan</button></div>
            </form>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Riwayat Verifikasi</h6>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(endpoint_url('verifikasi_export.php', ['rid' => (int)$rev['id']])) ?>">Export Verifikasi</a>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Tahap</th><th>Verifikator</th><th>Status</th><th>Waktu</th><th>Catatan</th></tr></thead>
                <tbody>
                  <?php if(empty($ver)){ ?>
                    <tr><td colspan="5"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
                  <?php } else { foreach($ver as $v){ ?>
                    <tr>
                      <td><?= e($v['tahap']) ?></td>
                      <td><?= e($v['verifikator']) ?></td>
                      <td><?= e($v['status']) ?></td>
                      <td><?= e($v['tgl_verifikasi']) ?></td>
                      <td><?= e($v['catatan']) ?></td>
                    </tr>
                  <?php } } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <?php
      $laporanDownloadRows = [];
      if (!empty($docs['Laporan'])) {
        foreach ($docs['Laporan'] as $docItem) {
          $laporanDownloadRows[] = [
            'label' => 'Dokumen',
            'title' => $docItem['judul'],
            'time'  => $docItem['created_at'],
            'link'  => endpoint_url('download.php', ['id' => (int)$docItem['id']]),
            'is_direct' => false,
          ];
        }
      }
      if (!empty($laporanRows)) {
        foreach ($laporanRows as $lapRow) {
          if (!empty($lapRow['lampiran'])) {
            $laporanDownloadRows[] = [
              'label' => 'Lampiran Laporan',
              'title' => $lapRow['ringkasan'] ?: ('Lampiran #' . (int)$lapRow['id']),
              'time'  => $lapRow['created_at'],
              'link'  => $lapRow['lampiran'],
              'is_direct' => true,
            ];
          }
        }
      }
      if (!empty($laporanDownloadRows)):
      ?>
      <div class="card-soft p-3 mt-3">
        <h6 class="mb-2">Lampiran Laporan</h6>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Jenis</th><th>Judul</th><th>Waktu</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <?php foreach($laporanDownloadRows as $row){ ?>
                <tr>
                  <td><span class="badge bg-secondary"><?= e($row['label']) ?></span></td>
                  <td><?= e($row['title']) ?></td>
                  <td><?= e($row['time']) ?></td>
                  <td class="text-end">
                    <?php
                      $baseLink = $row['link'];
                      $hasQuery = strpos($baseLink, '?') !== false;
                      $viewLink = $row['is_direct'] ? $baseLink : $baseLink . ($hasQuery ? '&' : '?') . 'mode=view';
                      $downloadLink = $row['is_direct'] ? $baseLink : $baseLink . ($hasQuery ? '&' : '?') . 'mode=download';
                    ?>
                    <span class="d-inline-flex gap-2">
                      <a class="btn btn-sm btn-outline-primary" href="<?= e($viewLink) ?>" target="_blank" rel="noopener">Lihat</a>
                      <?php if($row['is_direct']){ ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= e($downloadLink) ?>" download>Unduh</a>
                      <?php } else { ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= e($downloadLink) ?>" target="_blank" rel="noopener">Unduh</a>
                      <?php } ?>
                    </span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    <?php } else { ?>
      <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>Pilih jadwal dari tab <b>Jadwal</b> lalu klik kode reviu terlebih dahulu untuk mengelola <b>Verifikasi/TTD</b>.</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(review_url('jadwal')) ?>">Buka Daftar Jadwal</a>
      </div>
    <?php } ?>
  <?php } ?>

</main>

<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>

<script>
(function(){
  const form = document.getElementById('laporanAkhirForm');
  const canvas = document.getElementById('ttdSignaturePad');
  const hiddenInput = document.getElementById('ttdCanvasInput');
  const clearBtn = document.getElementById('sigClearBtn');
  const saveBtn = document.getElementById('sigSaveBtn');
  if (!form || !canvas || !hiddenInput) { return; }

  const wrapper = canvas.parentElement;
  const ctx = canvas.getContext('2d');
  canvas.style.touchAction = 'none';
  const modeRadios = form.querySelectorAll('input[name="ttd_mode"]');
  const uploadSection = document.getElementById('ttdUploadSection');
  const canvasSection = document.getElementById('ttdCanvasSection');

  const strokes = [];
  let currentStroke = null;
  let drawing = false;

  const getMode = () => {
    const checked = form.querySelector('input[name="ttd_mode"]:checked');
    return checked ? checked.value : 'canvas';
  };
  const shouldUseCanvas = () => getMode() === 'canvas';

  const setCanvasSize = () => {
    const rect = canvas.getBoundingClientRect();
    const width = rect.width || 600;
    const height = rect.height || 140;
    const needResize = canvas.width !== width || canvas.height !== height;
    if (needResize) {
      canvas.width = width;
      canvas.height = height;
      redraw();
    }
  };

  const hasSignature = () => strokes.some((s) => s.length > 1);

  const updateOverlayState = () => {
    if (!wrapper) { return; }
    const hasContent = hasSignature();
    const useCanvas = shouldUseCanvas();
    wrapper.classList.toggle('is-drawing', useCanvas && (hasContent || drawing));
    wrapper.classList.toggle('is-disabled', !useCanvas);
    const overlay = wrapper.querySelector('.sig-overlay');
    if (overlay) {
      overlay.style.opacity = useCanvas && (hasContent || drawing) ? '0' : '';
    }
    if (saveBtn) {
      saveBtn.disabled = !(useCanvas && hasContent);
    }
  };

  const getNormPos = (evt) => {
    const rect = canvas.getBoundingClientRect();
    let clientX = evt.clientX;
    let clientY = evt.clientY;
    if (evt.touches && evt.touches.length > 0) {
      clientX = evt.touches[0].clientX;
      clientY = evt.touches[0].clientY;
    }
    const x = (clientX - rect.left) / rect.width;
    const y = (clientY - rect.top) / rect.height;
    return {
      x: Math.min(Math.max(x, 0), 1),
      y: Math.min(Math.max(y, 0), 1)
    };
  };

  const redraw = () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.lineWidth = 2;
    ctx.strokeStyle = '#1b6e2c';
    strokes.forEach((stroke) => {
      if (!stroke || stroke.length < 2) { return; }
      ctx.beginPath();
      stroke.forEach((pt, idx) => {
        const x = pt.x * canvas.width;
        const y = pt.y * canvas.height;
        if (idx === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();
    });
    updateOverlayState();
  };

  const pointerDown = (evt) => {
    if (!shouldUseCanvas()) { return; }
    evt.preventDefault();
    setCanvasSize();
    drawing = true;
    currentStroke = [];
    strokes.push(currentStroke);
    currentStroke.push(getNormPos(evt));
    updateOverlayState();
  };

  const pointerMove = (evt) => {
    if (!drawing || !currentStroke) { return; }
    evt.preventDefault();
    currentStroke.push(getNormPos(evt));
    redraw();
  };

  const pointerUp = (evt) => {
    if (!drawing) { return; }
    evt.preventDefault();
    drawing = false;
    if (currentStroke && currentStroke.length < 2) {
      strokes.pop();
    }
    currentStroke = null;
    redraw();
  };

  const clearSignature = () => {
    strokes.length = 0;
    currentStroke = null;
    drawing = false;
    redraw();
    hiddenInput.value = '';
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.classList.add('btn-primary');
      saveBtn.classList.remove('btn-success');
      saveBtn.textContent = 'Simpan Tanda Tangan';
    }
  };

  const exportSignature = () => {
    const out = document.createElement('canvas');
    out.width = canvas.width;
    out.height = canvas.height;
    const octx = out.getContext('2d');
    octx.fillStyle = '#fff';
    octx.fillRect(0, 0, out.width, out.height);
    octx.lineJoin = 'round';
    octx.lineCap = 'round';
    octx.lineWidth = 2;
    octx.strokeStyle = '#1b6e2c';
    strokes.forEach((stroke) => {
      if (!stroke || stroke.length < 2) { return; }
      octx.beginPath();
      stroke.forEach((pt, idx) => {
        const x = pt.x * out.width;
        const y = pt.y * out.height;
        if (idx === 0) {
          octx.moveTo(x, y);
        } else {
          octx.lineTo(x, y);
        }
      });
      octx.stroke();
    });
    return out.toDataURL('image/png');
  };

  const applyMode = () => {
    const mode = getMode();
    if (uploadSection) { uploadSection.hidden = mode !== 'upload'; }
    if (canvasSection) { canvasSection.hidden = mode !== 'canvas'; }
    if (mode !== 'canvas') {
      hiddenInput.value = '';
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.classList.add('btn-primary');
        saveBtn.classList.remove('btn-success');
        saveBtn.textContent = 'Simpan Tanda Tangan';
      }
    } else {
      setCanvasSize();
    }
    updateOverlayState();
  };

  canvas.addEventListener('pointerdown', pointerDown);
  canvas.addEventListener('pointermove', pointerMove);
  canvas.addEventListener('pointerup', pointerUp);
  canvas.addEventListener('pointerleave', pointerUp);
  canvas.addEventListener('pointercancel', pointerUp);

  const saveSignature = () => {
    if (!shouldUseCanvas() || !hasSignature()) { return; }
    try {
      hiddenInput.value = exportSignature();
      if (saveBtn) {
        saveBtn.classList.add('btn-success');
        saveBtn.classList.remove('btn-primary');
        saveBtn.textContent = 'Tersimpan';
        setTimeout(() => {
          saveBtn.classList.add('btn-primary');
          saveBtn.classList.remove('btn-success');
          saveBtn.textContent = 'Simpan Tanda Tangan';
        }, 1800);
      }
    } catch (error) {
      console.error('Gagal menyimpan tanda tangan canvas', error);
    }
  };

  if (clearBtn) { clearBtn.addEventListener('click', clearSignature); }
  if (saveBtn) { saveBtn.addEventListener('click', saveSignature); }

  if (modeRadios.length) {
    modeRadios.forEach((radio) => radio.addEventListener('change', applyMode));
  }

  window.addEventListener('resize', () => {
    setCanvasSize();
  });

  setCanvasSize();
  redraw();
  applyMode();

  form.addEventListener('submit', () => {
    if (shouldUseCanvas() && hasSignature()) {
      hiddenInput.value = exportSignature();
    } else {
      hiddenInput.value = '';
    }
  });
})();

(function(){
  const form = document.getElementById('chrTemplateForm');
  if (!form) { return; }
  const signatureWrappers = form.querySelectorAll('[data-chr-signature]');

  const controllers = [];

  const initSignatureWrapper = (wrapper) => {
    if (!wrapper || wrapper.dataset.chrSignatureReady === '1') { return; }
    const canvas = wrapper.querySelector('canvas');
    const hiddenInput = wrapper.querySelector('[data-role="input"]');
    if (!canvas || !hiddenInput) { return; }
    wrapper.dataset.chrSignatureReady = '1';

    const ctx = canvas.getContext('2d');
    const overlay = wrapper.querySelector('.sig-overlay');
    const previewContainer = wrapper.querySelector('[data-role="preview"]');
    const previewImg = wrapper.querySelector('[data-role="preview-img"]');
    const clearBtn = wrapper.querySelector('[data-action="sig-clear"]');
    const saveBtn = wrapper.querySelector('[data-action="sig-save"]');
    const clearInput = wrapper.querySelector('[data-role="clear-input"]');
    const isDisabled = () => wrapper.getAttribute('data-signature-disabled') === '1';
    canvas.style.touchAction = 'none';

    const strokes = [];
    let currentStroke = null;
    let drawing = false;
    let dirty = false;
    let savedState = false;

    const hasSignature = () => strokes.some((stroke) => stroke && stroke.length > 1);

    const updateOverlay = () => {
      const hasContent = hasSignature();
      wrapper.classList.toggle('is-drawing', hasContent || drawing);
      wrapper.classList.toggle('is-disabled', isDisabled());
      if (overlay) {
        overlay.style.opacity = hasContent || drawing ? '0' : '';
      }
      if (saveBtn) {
        if (isDisabled()) {
          saveBtn.disabled = true;
          return;
        }
        if (hasContent) {
          if (!savedState) {
            saveBtn.textContent = 'Simpan Tanda Tangan';
            saveBtn.classList.add('btn-primary');
            saveBtn.classList.remove('btn-success');
          }
          saveBtn.disabled = false;
        } else {
          savedState = false;
          saveBtn.disabled = true;
          saveBtn.textContent = 'Simpan Tanda Tangan';
          saveBtn.classList.add('btn-primary');
          saveBtn.classList.remove('btn-success');
        }
      }
    };

    const redraw = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#1b6e2c';
      strokes.forEach((stroke) => {
        if (!stroke || stroke.length < 2) { return; }
        ctx.beginPath();
        stroke.forEach((pt, idx) => {
          const x = pt.x * canvas.width;
          const y = pt.y * canvas.height;
          if (idx === 0) {
            ctx.moveTo(x, y);
          } else {
            ctx.lineTo(x, y);
          }
        });
        ctx.stroke();
      });
      updateOverlay();
    };

    const exportSignature = () => {
      const out = document.createElement('canvas');
      out.width = canvas.width;
      out.height = canvas.height;
      const octx = out.getContext('2d');
      octx.fillStyle = '#fff';
      octx.fillRect(0, 0, out.width, out.height);
      octx.lineJoin = 'round';
      octx.lineCap = 'round';
      octx.lineWidth = 2;
      octx.strokeStyle = '#1b6e2c';
      strokes.forEach((stroke) => {
        if (!stroke || stroke.length < 2) { return; }
        octx.beginPath();
        stroke.forEach((pt, idx) => {
          const x = pt.x * out.width;
          const y = pt.y * out.height;
          if (idx === 0) {
            octx.moveTo(x, y);
          } else {
            octx.lineTo(x, y);
          }
        });
        octx.stroke();
      });
      return out.toDataURL('image/png');
    };

    const setCanvasSize = () => {
      const rect = wrapper.getBoundingClientRect();
      const width = Math.max(Math.round(rect.width), 260);
      const height = Math.max(Math.round(rect.height), 140);
      if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
      }
      redraw();
    };

    const getNormPos = (evt) => {
      const rect = canvas.getBoundingClientRect();
      let clientX = evt.clientX;
      let clientY = evt.clientY;
      if (evt.touches && evt.touches.length > 0) {
        clientX = evt.touches[0].clientX;
        clientY = evt.touches[0].clientY;
      }
      const x = (clientX - rect.left) / rect.width;
      const y = (clientY - rect.top) / rect.height;
      return {
        x: Math.min(Math.max(x, 0), 1),
        y: Math.min(Math.max(y, 0), 1)
      };
    };

    const pointerDown = (evt) => {
      if (isDisabled()) { return; }
      evt.preventDefault();
      setCanvasSize();
      drawing = true;
      dirty = true;
      savedState = false;
      currentStroke = [];
      strokes.push(currentStroke);
      currentStroke.push(getNormPos(evt));
      updateOverlay();
    };

    const pointerMove = (evt) => {
      if (!drawing || !currentStroke) { return; }
      evt.preventDefault();
      currentStroke.push(getNormPos(evt));
      redraw();
    };

    const pointerUp = (evt) => {
      if (!drawing) { return; }
      evt.preventDefault();
      drawing = false;
      if (currentStroke && currentStroke.length < 2) {
        strokes.pop();
      }
      currentStroke = null;
      savedState = false;
      redraw();
    };

    const clearSignature = () => {
      if (isDisabled()) { return; }
      strokes.length = 0;
      currentStroke = null;
      drawing = false;
      dirty = true;
      savedState = false;
      redraw();
      hiddenInput.value = '';
      if (clearInput) {
        clearInput.value = '1';
      }
      if (previewImg) {
        previewImg.hidden = true;
        previewImg.removeAttribute('src');
      }
      if (previewContainer) {
        previewContainer.hidden = true;
      }
    };

    const saveSignature = () => {
      if (isDisabled()) { return; }
      if (!hasSignature()) { return; }
      try {
        const dataUrl = exportSignature();
        hiddenInput.value = dataUrl;
        if (clearInput) {
          clearInput.value = '0';
        }
        if (previewImg) {
          previewImg.src = dataUrl;
          previewImg.hidden = false;
        }
        if (previewContainer) {
          previewContainer.hidden = false;
        }
        savedState = true;
        if (saveBtn) {
          saveBtn.disabled = true;
          saveBtn.classList.remove('btn-primary');
          saveBtn.classList.add('btn-success');
          saveBtn.textContent = 'Tanda Tangan Tersimpan';
        }
      } catch (error) {
        console.error('Gagal menyimpan tanda tangan CHR', error);
      }
    };

    canvas.addEventListener('pointerdown', pointerDown);
    canvas.addEventListener('pointermove', pointerMove);
    canvas.addEventListener('pointerup', pointerUp);
    canvas.addEventListener('pointerleave', pointerUp);
    canvas.addEventListener('pointercancel', pointerUp);

    if (clearBtn) { clearBtn.addEventListener('click', clearSignature); }
    if (saveBtn) { saveBtn.addEventListener('click', saveSignature); }

    controllers.push({
      resize: setCanvasSize,
      submit() {
        if (!dirty) { return; }
        if (hasSignature()) {
          try {
            const dataUrl = exportSignature();
            hiddenInput.value = dataUrl;
            if (previewImg) {
              previewImg.src = dataUrl;
              previewImg.hidden = false;
            }
            if (previewContainer) {
              previewContainer.hidden = false;
            }
          } catch (error) {
            console.error('Gagal menyimpan tanda tangan CHR', error);
          }
        } else {
          hiddenInput.value = '';
          if (previewImg) {
            previewImg.hidden = true;
            previewImg.removeAttribute('src');
          }
          if (previewContainer) {
            previewContainer.hidden = true;
          }
        }
      }
    });

    setCanvasSize();
    updateOverlay();

    if (previewImg && hiddenInput.value) {
      previewImg.hidden = false;
      if (previewContainer) {
        previewContainer.hidden = false;
      }
    }
  };

  signatureWrappers.forEach(initSignatureWrapper);

  document.addEventListener('chr:dynamic-row-added', (event) => {
    const root = event.detail && event.detail.root ? event.detail.root : null;
    if (!root) { return; }
    if (root.matches && root.matches('[data-chr-signature]')) {
      initSignatureWrapper(root);
    }
    root.querySelectorAll('[data-chr-signature]').forEach(initSignatureWrapper);
  });

  if (!controllers.length) { return; }

  form.addEventListener('submit', () => {
    controllers.forEach((ctrl) => ctrl.submit());
  });

  window.addEventListener('resize', () => {
    controllers.forEach((ctrl) => {
      if (typeof ctrl.resize === 'function') {
        ctrl.resize();
      }
    });
  });
})();
</script>

<div class="modal fade chr-preview-modal" id="chrPreviewModal" tabindex="-1" aria-labelledby="chrPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="chrPreviewModalLabel">Pratinjau Dokumen CHR</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-0">
        <div class="alert alert-danger m-3 d-none" id="chrPreviewError">Pratinjau gagal dimuat. Silakan coba unduh dokumen atau buka kembali halaman ini.</div>
        <iframe class="chr-preview-frame" id="chrPreviewFrame" title="Pratinjau Dokumen CHR" loading="lazy"></iframe>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  if (!window.bootstrap) { return; }
  function setText(selector, value) {
    var el = document.querySelector(selector);
    if (el) { el.textContent = value || '-'; }
  }
  function showModal(id) {
    var el = document.getElementById(id);
    if (!el) { return null; }
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
    return el;
  }
  function setField(form, selector, value) {
    var field = form ? form.querySelector(selector) : null;
    if (field) { field.value = value || ''; }
  }
  document.addEventListener('click', function(event) {
    var deadlineBtn = event.target.closest('[data-review-deadline-open]');
    if (deadlineBtn) {
      var deadlineModalEl = showModal('reviewDeadlineModal');
      if (!deadlineModalEl) { return; }
      var deadlineForm = deadlineModalEl.querySelector('form');
      setField(deadlineForm, 'input[name="id"]', deadlineBtn.getAttribute('data-id') || '');
      setField(deadlineForm, 'input[name="deadline"]', deadlineBtn.getAttribute('data-deadline') || '');
      setText('#reviewDeadlineModal [data-review-deadline-code]', deadlineBtn.getAttribute('data-code') || '');
      setText('#reviewDeadlineModal [data-review-deadline-title]', deadlineBtn.getAttribute('data-name') || '-');
      setText('#reviewDeadlineModal [data-review-deadline-info]', deadlineBtn.getAttribute('data-deadline-info') || 'Informasi deadline belum tersedia.');
      return;
    }
    var noteBtn = event.target.closest('[data-review-note-open]');
    if (noteBtn) {
      var noteModalEl = showModal('reviewNoteModal');
      if (!noteModalEl) { return; }
      var noteForm = noteModalEl.querySelector('form');
      setField(noteForm, 'input[name="id"]', noteBtn.getAttribute('data-id') || '');
      setField(noteForm, 'textarea[name="catatan"]', noteBtn.getAttribute('data-note') || '');
      setText('#reviewNoteModal [data-review-note-code]', noteBtn.getAttribute('data-code') || '');
      setText('#reviewNoteModal [data-review-note-title]', noteBtn.getAttribute('data-name') || '-');
      setText('#reviewNoteModal [data-review-note-status]', noteBtn.getAttribute('data-status') || '-');
      return;
    }
    var deleteBtn = event.target.closest('[data-review-delete-open]');
    if (deleteBtn) {
      var deleteModalEl = showModal('reviewDeleteModal');
      if (!deleteModalEl) { return; }
      var deleteForm = deleteModalEl.querySelector('form');
      setField(deleteForm, 'input[name="reviu_id"]', deleteBtn.getAttribute('data-id') || '');
      setText('#reviewDeleteModal [data-review-delete-code]', deleteBtn.getAttribute('data-code') || '');
      setText('#reviewDeleteModal [data-review-delete-title]', deleteBtn.getAttribute('data-title') || '-');
    }
  });
  document.querySelectorAll('[data-review-action-form]').forEach(function(form) {
    form.addEventListener('submit', function() {
      var submit = form.querySelector('button[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.dataset.originalText = submit.textContent;
        submit.textContent = 'Menyimpan...';
      }
    });
  });
})();
(function(){
  var modalEl = document.getElementById('reviewCreateModal');
  var form = document.getElementById('reviewCreateForm');
  if (!modalEl || !form || !window.bootstrap) { return; }
  var opener = document.querySelector('[data-bs-target="#reviewCreateModal"]');
  var dirty = false;
  var allowClose = false;
  var jenisSelect = document.getElementById('reviewJenisSelect');
  var help = document.getElementById('reviewTemplateHelp');
  var mulai = form.querySelector('[name="mulai"]');
  var selesai = form.querySelector('[name="selesai"]');

  form.addEventListener('input', function(){ dirty = true; });
  form.addEventListener('change', function(){ dirty = true; updateTemplateHelp(); });
  form.addEventListener('submit', function(event){
    if (mulai && selesai && mulai.value && selesai.value && mulai.value > selesai.value) {
      event.preventDefault();
      alert('Tanggal mulai tidak boleh melebihi tanggal selesai.');
      mulai.focus();
      return;
    }
    allowClose = true;
  });

  function updateTemplateHelp() {
    if (!jenisSelect || !help) { return; }
    var opt = jenisSelect.options[jenisSelect.selectedIndex];
    var code = opt ? (opt.getAttribute('data-template-code') || '') : '';
    var name = opt ? (opt.getAttribute('data-template-name') || '') : '';
    if (!jenisSelect.value) {
      help.textContent = 'Pilih jenis yang sudah terhubung ke template CHR.';
    } else if (code) {
      help.textContent = 'Template aktif: ' + code + (name ? ' - ' + name : '');
    } else {
      help.textContent = 'Jenis ini belum dipetakan ke template CHR dan tidak bisa disimpan.';
    }
  }

  modalEl.addEventListener('show.bs.modal', function(){ allowClose = false; });
  modalEl.addEventListener('shown.bs.modal', function(){
    var focusEl = form.querySelector('[data-review-create-focus]');
    if (focusEl) { focusEl.focus(); }
    updateTemplateHelp();
  });
  modalEl.addEventListener('hide.bs.modal', function(event){
    if (!allowClose && dirty && !confirm(form.getAttribute('data-dirty-confirm') || 'Tutup form tanpa menyimpan?')) {
      event.preventDefault();
    }
  });
  modalEl.addEventListener('hidden.bs.modal', function(){
    dirty = false;
    allowClose = false;
    form.reset();
    updateTemplateHelp();
    if (opener) { opener.focus(); }
  });
})();
</script>
<script>
(function(){
  function targetFromHash() {
    var raw = window.location.hash ? window.location.hash.substring(1) : '';
    if (!raw) { return null; }
    var id = '';
    try { id = decodeURIComponent(raw); } catch (e) { id = raw; }
    var target = document.getElementById(id);
    if (!target && id.indexOf('approval-') === 0) {
      target = document.getElementById('approval-section');
    }
    return target;
  }
  function focusSignerAction(target) {
    var focusable = target.querySelector('[data-action="sig-save"]:not([disabled]), [data-action="sig-clear"]:not([disabled]), canvas, button:not([disabled]), input:not([type="hidden"]), select, textarea');
    if (focusable && typeof focusable.focus === 'function') {
      focusable.focus({ preventScroll: true });
    } else if (typeof target.focus === 'function') {
      target.focus({ preventScroll: true });
    }
  }
  function activateApprovalAnchor() {
    var target = targetFromHash();
    if (!target) { return; }
    window.setTimeout(function() {
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      target.classList.add('approval-anchor-highlight');
      focusSignerAction(target);
      window.setTimeout(function() {
        target.classList.remove('approval-anchor-highlight');
      }, 2800);
    }, 120);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activateApprovalAnchor);
  } else {
    activateApprovalAnchor();
  }
  window.addEventListener('hashchange', activateApprovalAnchor);
})();
</script>
<script>
(function(){
  var modalEl = document.getElementById('chrPreviewModal');
  var frame = document.getElementById('chrPreviewFrame');
  var errorBox = document.getElementById('chrPreviewError');
  if (!modalEl || !frame || !window.bootstrap) { return; }
  var modal = new bootstrap.Modal(modalEl);
  var previewTimer = null;
  document.addEventListener('click', function(event) {
    var btn = event.target.closest('[data-chr-preview-url]');
    if (!btn) { return; }
    event.preventDefault();
    if (errorBox) { errorBox.classList.add('d-none'); }
    frame.src = btn.getAttribute('data-chr-preview-url') || 'about:blank';
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(function() {
      if (errorBox) { errorBox.classList.remove('d-none'); }
    }, 12000);
    modal.show();
  });
  frame.addEventListener('load', function() {
    window.clearTimeout(previewTimer);
    if (errorBox) { errorBox.classList.add('d-none'); }
  });
  frame.addEventListener('error', function() {
    window.clearTimeout(previewTimer);
    if (errorBox) { errorBox.classList.remove('d-none'); }
  });
  modalEl.addEventListener('hidden.bs.modal', function() {
    window.clearTimeout(previewTimer);
    if (errorBox) { errorBox.classList.add('d-none'); }
    frame.removeAttribute('src');
  });
})();
</script>
<script>
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.comment-reply-toggle');
  if (!btn) return;
  var targetId = btn.getAttribute('data-reply');
  if (!targetId) return;
  var form = document.getElementById(targetId);
  if (form) {
    form.classList.toggle('d-none');
  }
});
</script>
</body>
</html>
