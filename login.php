<?php

// Watermark: Ded Polkester

// ================== BOOTSTRAP (PHP) ==================

require_once __DIR__ . '/includes/security_headers.php';

require_once __DIR__ . '/includes/session_hardening.php';



$__isAuth = !empty($_SESSION['auth']) || !empty($_SESSION['user']);

if ($__isAuth) {
  $r = $_SESSION['user']['peran'] ?? '';

  // Admin & Super Admin langsung dashboard
  if (in_array($r, ['super_admin','admin'], true)) {
    header('Location: dashboard.php');
    exit;
  }

  // Non-admin: biarkan tetap di login.php?logged_in=1 sebagai halaman menu
  if (!isset($_GET['logged_in'])) {
    header('Location: login.php?logged_in=1');
    exit;
  }
}

// DB loader fleksibel

$__base = __DIR__;

$__candidates = [

    $__base . '/db.php', $__base . '/ski_new/db.php', $__base . '/db/db.php',

    dirname($__base) . '/db.php', $__base . '/config/db.php', $__base . '/includes/db.php',

];

$__found = false;

foreach ($__candidates as $__p) { if (is_file($__p)) { require_once $__p; $__found = true; break; } }

if (!$__found) { http_response_code(500); die("db.php tidak ditemukan:\n - ".implode("\n - ", $__candidates)); }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); die("Koneksi database (\$conn) tidak tersedia."); }

$conn->set_charset('utf8mb4');



require_once __DIR__.'/config/env.php';

require_once __DIR__.'/pelaporan_helpers.php';



// Flash yang aman

if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) { $_SESSION['flash'] = []; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

function csrf_token(){ return $_SESSION['csrf_token']; }

function csrf_field(){ return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">'; }

function csrf_validate($t){ if(!hash_equals($_SESSION['csrf_token'], (string)$t)){ http_response_code(400); die("Invalid CSRF token"); } }

function require_post_with_csrf(){ if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); die('Method Not Allowed'); } csrf_validate($_POST['csrf'] ?? ''); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function public_form_rate_limited(string $key, int $maxAttempts, int $windowSeconds): bool {
  $now = time();
  if (!isset($_SESSION['public_form_rate']) || !is_array($_SESSION['public_form_rate'])) {
    $_SESSION['public_form_rate'] = [];
  }
  $bucket = $_SESSION['public_form_rate'][$key] ?? ['start' => $now, 'count' => 0];
  $start = (int)($bucket['start'] ?? $now);
  $count = (int)($bucket['count'] ?? 0);
  if (($now - $start) > $windowSeconds) {
    $start = $now;
    $count = 0;
  }
  $count++;
  $_SESSION['public_form_rate'][$key] = ['start' => $start, 'count' => $count];
  return $count > $maxAttempts;
}

function public_form_honeypot_filled(): bool {
  return trim((string)($_POST['website'] ?? '')) !== '';
}

function flash($k,$v=null){

  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) { $_SESSION['flash'] = []; }

  if ($v!==null){ $_SESSION['flash'][$k]=$v; return null; }

  if (!array_key_exists($k, $_SESSION['flash'])) return null;

  $x=$_SESSION['flash'][$k]; unset($_SESSION['flash'][$k]); return $x;

}



function login_set_error(string $msg, bool $locked = false, int $remaining = 0): void {

  $_SESSION['err_login'] = $msg;

  if ($locked) { $_SESSION['err_login_locked'] = 1; }

  else { unset($_SESSION['err_login_locked']); }

  if ($remaining > 0) { $_SESSION['lock_remaining'] = $remaining; }

  else { unset($_SESSION['lock_remaining']); }

}



function login_env($key, $default = '') {

  if (function_exists('env')) { return env($key, $default); }

  $val = getenv($key);

  if ($val === false || $val === null || $val === '') {

    $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;

  }

  return $val === '' ? $default : $val;

}

$ADMIN_WA_RAW = (string)login_env('APP_ADMIN_WA', '');

$ADMIN_NAME = (string)login_env('APP_ADMIN_NAME', 'Admin SKI');

$ADMIN_WA = preg_replace('/\D+/', '', $ADMIN_WA_RAW);

$ADMIN_LINK = '';

if ($ADMIN_WA !== '') {

  $adminMsg = 'Halo '.$ADMIN_NAME.', saya mengalami kendala login pada aplikasi SKI. Mohon bantuan.';

  $ADMIN_LINK = 'https://wa.me/'.$ADMIN_WA.'?text='.rawurlencode($adminMsg);

}



$LOGIN_ROLE_LABELS = [

  'super_admin'       => 'Super Admin',

  'admin'             => 'Admin SKI',

  'moderator'         => 'Moderator',

  'auditor'           => 'Auditor',

  'auditor_ka'        => 'Auditor - Kepala SKI',

  'kepala_ski'        => 'Kepala SKI',

  'direktur'          => 'Direktur',

  'auditee'           => 'Auditee',

  'auditee_tlm'       => 'Auditee - TLM',

  'auditee_direktur'  => 'Auditee - Direktur',

];

function login_resolve_role($roleValue): array {

  global $LOGIN_ROLE_LABELS;

  $raw = trim((string)$roleValue);

  if ($raw === '') { $raw = 'user'; }

  $canonical = strtolower($raw);

  if ($canonical === '') { $canonical = 'user'; }

  if (isset($LOGIN_ROLE_LABELS[$canonical])) {

    $label = $LOGIN_ROLE_LABELS[$canonical];

  } elseif (strpos($canonical, 'auditee_') === 0) {

    $label = 'Auditee - '.ucwords(str_replace('_',' ', substr($canonical, 8)));

  } else {

    $label = ucfirst(str_replace('_',' ', $canonical));

  }

  return [

    'canonical' => $canonical,

    'raw'       => $raw,

    'label'     => $label,

  ];

}



function login_public_status_label($rawStatus): string {

  $label = pelaporan_status_label($rawStatus);

  return ($label === 'Kembali ke Pelapor') ? 'Tidak sesuai' : $label;

}



// ================== HANDLERS ==================

// Rate limit login sederhana (per IP + username, berbasis file)

$WINDOW_SECONDS = 15 * 60;

$LOCK_SECONDS = 15 * 60;

$MAX_FAILS_USER = 5;



if (!function_exists('login_rate_dir')) {

  function login_rate_dir(): string {

    $dir = __DIR__ . '/storage/login_rate';

    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

    return $dir;

  }

}

if (!function_exists('login_rate_key')) {

  function login_rate_key(string $salt, string $value): string {

    $value = strtolower(trim($value));

    if (strlen($value) > 128) { $value = substr($value, 0, 128); }

    return sha1($salt.'|'.$value);

  }

}

if (!function_exists('login_rate_path')) {

  function login_rate_path(string $key): string {

    return rtrim(login_rate_dir(), '/').'/'.$key.'.json';

  }

}

if (!function_exists('login_rate_defaults')) {

  function login_rate_defaults(): array {

    return ['fails' => 0, 'first_fail_at' => 0, 'last_fail_at' => 0, 'locked_until' => 0];

  }

}

if (!function_exists('login_rate_read')) {

  function login_rate_read(string $path): array {

    if (!is_file($path)) { return login_rate_defaults(); }

    $raw = @file_get_contents($path);

    $data = json_decode((string)$raw, true);

    if (!is_array($data)) { return login_rate_defaults(); }

    return array_merge(login_rate_defaults(), $data);

  }

}

if (!function_exists('login_rate_normalize')) {

  function login_rate_normalize(array $data, int $now, int $window): array {

    $data = array_merge(login_rate_defaults(), $data);

    if (!empty($data['first_fail_at']) && ($now - (int)$data['first_fail_at']) > $window) {

      $data = login_rate_defaults();

      $data['first_fail_at'] = $now;

      return $data;

    }

    if (!empty($data['locked_until']) && $now >= (int)$data['locked_until']) {

      $data = login_rate_defaults();

      $data['first_fail_at'] = $now;

      return $data;

    }

    if (empty($data['first_fail_at'])) { $data['first_fail_at'] = $now; }

    return $data;

  }

}

if (!function_exists('login_rate_write')) {

  function login_rate_write(string $path, array $data): void {

    @file_put_contents($path, json_encode($data));

  }

}

if (!function_exists('login_rate_clear')) {

  function login_rate_clear(string $path): void {

    if (is_file($path)) { @unlink($path); }

  }

}

if (!function_exists('login_is_ajax')) {

  function login_is_ajax(): bool {

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    if ($accept && stripos((string)$accept, 'application/json') !== false) {

      return true;

    }

    return false;

  }

}





if (!function_exists('login_lock_response')) {

  function login_lock_response(string $message, int $remaining): void {

    $remaining = max(0, $remaining);

    if (login_is_ajax()) {

      header('Content-Type: application/json; charset=UTF-8');

      echo json_encode(['ok' => false, 'locked' => true, 'message' => $message, 'remaining' => $remaining], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

      exit;

    }

    login_set_error($message, true, $remaining);

    header('Location: '.$_SERVER['PHP_SELF'].'#loginModal');

    exit;

  }

}





// Login

if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='login')) {

  require_post_with_csrf();

  $now = time();

  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

  $username = trim((string)($_POST['username'] ?? ''));

  $password = (string)($_POST['password'] ?? '');

  $MSG_INVALID = 'Login gagal. Username atau password salah. Silakan periksa kembali dan coba lagi.';

  $MSG_LOCKED = 'Terlalu banyak percobaan login. Silakan coba lagi beberapa saat atau hubungi admin.';



  $userKey = login_rate_key('user', $ip.'|'.$username);

  $userPath = login_rate_path($userKey);



  $userData = login_rate_normalize(login_rate_read($userPath), $now, $WINDOW_SECONDS);



  if (!empty($userData['locked_until']) && (int)$userData['locked_until'] > $now) {

    $remaining = (int)$userData['locked_until'] - $now;

    login_lock_response($MSG_LOCKED, $remaining);

  }



  $stmt=$conn->prepare("SELECT id,nama,username,password_hash,peran,status,akses_dashboard,akses_pelaporan,akses_review FROM pengguna WHERE username=? LIMIT 1");

  if(!$stmt){ login_set_error('Login gagal. Silakan coba lagi.'); header('Location: '.$_SERVER['PHP_SELF'].'#loginModal'); exit; }

  $stmt->bind_param("s",$username); $stmt->execute(); $u=$stmt->get_result()->fetch_assoc();

    $ok=false;

  if($u){

    $hash = (string)($u['password_hash'] ?? '');

    if ($hash !== '' && password_verify($password, $hash)) {

      $ok = true;

    }


  if($ok){

    if (function_exists('session_hardening_regenerate')) { session_hardening_regenerate(); } else { session_regenerate_id(true); }

    login_rate_clear($userPath);

    unset($_SESSION['err_login'], $_SESSION['err_login_locked'], $_SESSION['lock_remaining']);

    $roleMeta = login_resolve_role($u['peran'] ?? 'user');

    $statusRaw = trim((string)($u['status'] ?? 'Aktif'));

    if ($statusRaw === '') { $statusRaw = 'Aktif'; }

             $sessionUser = [
      'id'          => $u['id'],
      'nama'        => $u['nama'],
      'username'    => $u['username'],
      'peran'       => $roleMeta['canonical'],
      'peran_raw'   => $roleMeta['raw'],
      'peran_label' => $roleMeta['label'],
      'status'      => $statusRaw,
    
      'akses_dashboard' => (int)($u['akses_dashboard'] ?? 0),
      'akses_pelaporan' => (int)($u['akses_pelaporan'] ?? 0),
      'akses_review'    => (int)($u['akses_review'] ?? 0),
    ];


    establish_login_session($sessionUser);
    $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $sessionUser);

    auth_debug_log('login_success', ['username' => $u['username'] ?? '', 'user_id' => $u['id'] ?? null]);

    flash('ok_login','Selamat datang, '.e($u['nama']).'!');

    $roleAfter = $roleMeta['canonical'];

    // Admin & Super Admin langsung dashboard
    if (in_array($roleAfter, ['super_admin','admin'], true)) {
      $redirect = 'dashboard.php';
    } else {
      // Auditee/Auditor dkk masuk ke halaman menu (login page mode logged_in)
      $redirect = 'login.php?logged_in=1';
    }
    
    if (login_is_ajax()) {

      header('Content-Type: application/json; charset=UTF-8');

      echo json_encode(['ok' => true, 'redirect' => $redirect], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

      exit;

    }

    header('Location: '.$redirect); exit;

  } else {

        $userData['fails'] = (int)($userData['fails'] ?? 0) + 1;

    $userData['last_fail_at'] = $now;

    if (empty($userData['first_fail_at'])) { $userData['first_fail_at'] = $now; }



    $lockedNow = false;

    if ($userData['fails'] >= $MAX_FAILS_USER) {

      $userData['locked_until'] = $now + $LOCK_SECONDS;

      $lockedNow = true;

    }



    login_rate_write($userPath, $userData);



    if (!$lockedNow) {

      if ((int)$userData['fails'] === 3) { session_release(); sleep(1); if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); } }

      if ((int)$userData['fails'] === 4) { session_release(); sleep(2); if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); } }

    }



    $msg = $lockedNow ? $MSG_LOCKED : $MSG_INVALID;

    $remaining = $lockedNow ? max(0, (int)($userData['locked_until'] ?? 0) - $now) : 0;

    if (login_is_ajax()) {

      header('Content-Type: application/json; charset=UTF-8');

      echo json_encode(['ok' => false, 'message' => $msg, 'locked' => $lockedNow, 'remaining' => $remaining], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

      exit;

    }

    login_set_error($msg, $lockedNow, $remaining);

    header('Location: '.$_SERVER['PHP_SELF'].'#loginModal'); exit;

  }

}


}

// Logout

if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='logout')) {

  // Do not block logout on CSRF mismatch (session may be expired)

  $token = (string)($_POST['csrf'] ?? '');

  $current = (string)($_SESSION['csrf_token'] ?? '');

  if ($token !== '' && $current !== '' && !hash_equals($current, $token)) {

    // ignore mismatch

  }

  force_logout_and_redirect('login.php?logged_out=1');

}



// ====== Konfigurasi Upload Lampiran ======

define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');

$ALLOWED_MIMES = [

  'application/pdf' => 'pdf',

  'image/jpeg'      => 'jpg',
  'image/png'       => 'png',
  'image/gif'       => 'gif',
  'image/webp'      => 'webp',

  'video/mp4'       => 'mp4',
  'video/webm'      => 'webm',
  'video/quicktime' => 'mov',

];

$MAX_SIZE = 5 * 1024 * 1024; // 5MB per file



function ensure_upload_dir($dateFolder){

  $base = UPLOAD_DIR;

  if (!is_dir($base)) { @mkdir($base, 0755, true); }

  $target = $base . DIRECTORY_SEPARATOR . $dateFolder;

  if (!is_dir($target)) { @mkdir($target, 0755, true); }

  return $target;

}



// Pelaporan (buat laporan + kode tracking + lampiran)

if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='lapor')) {

  require_post_with_csrf();
  if (public_form_honeypot_filled() || public_form_rate_limited('lapor', 5, 300)) {
    flash('err_report','Terlalu banyak percobaan submit. Silakan coba lagi beberapa saat.');
    header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit;
  }

  global $ALLOWED_MIMES, $MAX_SIZE;



  $nama=trim($_POST['nama']??''); $email=trim($_POST['email']??'');

  $kategori=trim($_POST['kategori']??''); $isi=trim($_POST['isi']??''); $anonim=isset($_POST['anonim'])?1:0;



  if($kategori===''||$isi===''){ flash('err_report','Kategori dan isi wajib diisi.'); header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit; }

  if($anonim){ $nama=null; $email=null; }



  $kode = 'SKI-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,5));

  $status='Belum diproses'; $tanggal=date('Y-m-d');

  $judul=function_exists('mb_substr')?mb_substr($isi,0,120):substr($isi,0,120);



  // Simpan laporan

  $stmt=$conn->prepare("INSERT INTO pelaporan (kode,judul,kategori,isi,anonim,status,tanggal,created_at) VALUES (?,?,?,?,?,?,?,NOW())");

  if(!$stmt){ flash('err_report','Terjadi kendala sistem saat menyimpan laporan. Silakan coba lagi.'); header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit; }

  $stmt->bind_param("ssssiss",$kode,$judul,$kategori,$isi,$anonim,$status,$tanggal);

  if(!$stmt->execute()){ flash('err_report','Terjadi kendala sistem saat menyimpan laporan. Silakan coba lagi.'); header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit; }



// simpan kontak pelapor (opsional)

if ($nama || $email) {

  $stmtC = $conn->prepare("INSERT INTO pelaporan_contact (kode,nama,email) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nama=VALUES(nama), email=VALUES(email)");

  if ($stmtC) { $stmtC->bind_param("sss", $kode, $nama, $email); $stmtC->execute(); }

}



// kirim notifikasi email ke admin

require_once __DIR__.'/mailer.php';

$admins = mailer_admin_list($conn);

if (!empty($admins)) {

  $subject = "Laporan Baru ($kategori)  $kode";

  $html = '<p>Halo Admin,</p>

           <p>Ada laporan baru:</p>

           <ul>

             <li><b>Kode:</b> '.e($kode).'</li>

             <li><b>Kategori:</b> '.e($kategori).'</li>

             <li><b>Status awal:</b> Belum diproses</li>

             <li><b>Waktu:</b> '.date('Y-m-d H:i:s').'</li>

           </ul>

           <p>Isi ringkas:</p>

           <blockquote style="border-left:4px solid #ccc;padding-left:8px">'.nl2br(e($judul)).'...</blockquote>

           <p><a href="'.e((isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/pelaporan.php?q='.urlencode($kode)).'">Buka di Admin</a></p>';

  @mailer_send($admins, $subject, $html);

}





// LOG awal status (Belum diproses)

$stmtLG = $conn->prepare("INSERT INTO pelaporan_log (kode,status_from,status_to,note,user_id,user_name) VALUES (?,?,?,?,?,?)");

if ($stmtLG) {

  $statusFrom = null; $statusTo = 'Belum diproses'; $note = 'Laporan dibuat via publik';

  $uid = null; $uname = 'Publik';

  $stmtLG->bind_param("ssssss", $kode, $statusFrom, $statusTo, $note, $uid, $uname);

  $stmtLG->execute();

}



  // Upload lampiran (opsional)

  if (!empty($_FILES['lampiran']) && is_array($_FILES['lampiran']['name'])) {

    $dateFolder = date('Ymd');

    $targetDir = ensure_upload_dir($dateFolder);



    // .htaccess untuk keamanan (blokir eksekusi PHP di folder uploads)

    $ht = $targetDir . DIRECTORY_SEPARATOR . '.htaccess';

    if (!is_file($ht)) {

      @file_put_contents($ht, "php_flag engine off\nOptions -ExecCGI -Indexes\n<FilesMatch \"\\.(php|php5|phtml)$\">\nDeny from all\n</FilesMatch>\n");

    }



    $total = count($_FILES['lampiran']['name']);

    for ($i=0; $i<$total; $i++) {

      $err = $_FILES['lampiran']['error'][$i];

      if ($err === UPLOAD_ERR_NO_FILE) { continue; }

      if ($err !== UPLOAD_ERR_OK) { continue; } // bisa ditingkatkan: tampilkan pesan per-file


    $tmp  = $_FILES['lampiran']['tmp_name'][$i];

$name = trim($_FILES['lampiran']['name'][$i]);

$size = (int)$_FILES['lampiran']['size'][$i];

$originalExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$blockedExt = ['php','phtml','php3','php4','php5','php7','php8','phar','html','htm','js','exe','bat','cmd','sh','svg'];
if ($originalExt === '' || in_array($originalExt, $blockedExt, true)) { continue; }
$type = '';
if (class_exists('finfo')) {
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $type = (string)($finfo->file($tmp) ?: '');
}
if ($type === '') {
  $type = $_FILES['lampiran']['type'][$i] ?? '';
}

if ($size <= 0 || $size > $MAX_SIZE) { continue; }

if (!isset($ALLOWED_MIMES[$type])) { continue; }

$ext = $ALLOWED_MIMES[$type];
if ($originalExt !== $ext && !($type === 'image/jpeg' && $originalExt === 'jpeg')) { continue; }

$stored = $kode . '-' . bin2hex(random_bytes(6)) . '.' . $ext;

$dest = $targetDir . DIRECTORY_SEPARATOR . $stored;

if (move_uploaded_file($tmp, $dest)) {

  $rel = 'uploads/'.$dateFolder.'/'.$stored;

  $stmtF = $conn->prepare("INSERT INTO pelaporan_files (kode, original_name, stored_name, mime, size_bytes, rel_path) VALUES (?, ?, ?, ?, ?, ?)");

  if ($stmtF) {

    // size_bytes integer
    $stmtF->bind_param("ssssis", $kode, $name, $stored, $type, $size, $rel);

    $stmtF->execute();

    $stmtF->close();

    }

    }
      

    }

  }



  // catat notifikasi sederhana (opsional)

  if ($conn->query("SHOW TABLES LIKE 'notifikasi'")->num_rows) {

    $judulNotif='Laporan baru: '.$kategori; $pesanNotif='Kode '.$kode.' status "'.$status.'".'; $linkNotif='pelaporan.php?q='.$kode;

    $stmtN=$conn->prepare("INSERT INTO notifikasi (jenis, judul, pesan, link) VALUES ('pelaporan_baru', ?, ?, ?)");

    if ($stmtN) { $stmtN->bind_param("sss",$judulNotif,$pesanNotif,$linkNotif); $stmtN->execute(); }

  }



  flash('ok_report','Laporan terkirim. Simpan kode tracking: <b>'.e($kode).'</b>');

  header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit;

}



// Lacak (POST ? GET)

if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='lacak')) {

  require_post_with_csrf();
  if (public_form_honeypot_filled() || public_form_rate_limited('lacak', 20, 300)) {
    flash('err_track','Terlalu banyak percobaan lacak. Silakan coba lagi beberapa saat.');
    header('Location: '.$_SERVER['PHP_SELF'].'#lacak-pengaduan'); exit;
  }

  $kode=trim($_POST['kode']??''); if($kode===''){ flash('err_track','Masukkan kode tracking.'); header('Location: '.$_SERVER['PHP_SELF'].'#lacak-pengaduan'); exit; }

  header('Location: '.$_SERVER['PHP_SELF'].'?action=lihat&kode='.urlencode($kode).'#lacak-pengaduan'); exit;

}



// Feedback

if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='feedback')) {

  require_post_with_csrf();
  if (public_form_honeypot_filled() || public_form_rate_limited('feedback', 5, 300)) {
    flash('err_fb','Terlalu banyak percobaan submit. Silakan coba lagi beberapa saat.');
    header('Location: '.$_SERVER['PHP_SELF'].'#saran-kritik'); exit;
  }

  $nama=trim($_POST['nama_fb']??''); $email=trim($_POST['email_fb']??''); $text=trim($_POST['isi_fb']??'');

  if($text===''){ flash('err_fb','Isi saran/kritik wajib diisi.'); header('Location: '.$_SERVER['PHP_SELF'].'#saran-kritik'); exit; }

  $stmt=$conn->prepare("INSERT INTO feedback (nama,email,isi,created_at) VALUES (?,?,?,NOW())");

  if(!$stmt){ flash('err_fb','Terjadi kendala sistem saat menyimpan saran/kritik. Silakan coba lagi.'); header('Location: '.$_SERVER['PHP_SELF'].'#saran-kritik'); exit; }

  $stmt->bind_param("sss",$nama,$email,$text);

  $stmt->execute()? flash('ok_fb','Terima kasih! Saran/kritik Anda terekam.') : flash('err_fb','Terjadi kendala sistem saat menyimpan saran/kritik. Silakan coba lagi.');

  header('Location: '.$_SERVER['PHP_SELF'].'#saran-kritik'); exit;

}



// Statistik & detail lacak

$stat=[

  'total'   => 0,

  'masuk'   => 0,

  'proses'  => 0,

  'arsip'   => 0,

  'kembali' => 0,

];

$Q=$conn->query("SELECT status, COUNT(*) c FROM pelaporan GROUP BY status");

if($Q){

  while($row=$Q->fetch_assoc()){

    $count = (int)$row['c'];

    $canonical = pelaporan_status_canonical((string)$row['status']);

    $bucket = pelaporan_status_bucket($canonical);

    if(isset($stat[$bucket])){ $stat[$bucket] += $count; }

    else{ $stat['proses'] += $count; }

    $stat['total'] += $count;

  }

}



$detailPengaduan=null; 

$lampiranDetail=[];

$logPublik=[];



if(($_GET['action']??'')==='lihat' && isset($_GET['kode'])){

    $kode=trim($_GET['kode']);



    // Detail laporan

    $stmt=$conn->prepare("SELECT kode,kategori,isi,status,created_at FROM pelaporan WHERE kode=? LIMIT 1");

    if($stmt){ 

        $stmt->bind_param("s",$kode); 

        $stmt->execute(); 

        $detailPengaduan=$stmt->get_result()->fetch_assoc(); 

    }



    // Lampiran (jika ada)

    $stmtL=$conn->prepare("SELECT id, original_name, rel_path, mime, size_bytes FROM pelaporan_files WHERE kode=? ORDER BY id ASC");

    if($stmtL){ 

        $stmtL->bind_param("s",$kode); 

        $stmtL->execute(); 

        $lampiranDetail=$stmtL->get_result()->fetch_all(MYSQLI_ASSOC); 

        if (is_array($lampiranDetail)) {

            $lampiranDetail = array_values(array_filter($lampiranDetail, function($row){

                $rel = strtolower((string)($row['rel_path'] ?? ''));

                return strpos($rel, 'uploads/rekap/') === false;

            }));

        }

    }



    // Riwayat status untuk pelacakan publik

    if ($detailPengaduan) {

        $stmtPH=$conn->prepare("SELECT status_from,status_to,note,user_name,created_at FROM pelaporan_log WHERE kode=? ORDER BY created_at ASC, id ASC");

        if($stmtPH){ 

            $stmtPH->bind_param("s",$detailPengaduan['kode']); 

            $stmtPH->execute(); 

            $logPublik=$stmtPH->get_result()->fetch_all(MYSQLI_ASSOC); 

        }

    }

}



$detailStatusLabel='';

$detailStatusBadge='bg-secondary';

$detailStatusDesc='';

if($detailPengaduan){

    $detailStatusLabel = login_public_status_label($detailPengaduan['status']);

    $detailStatusBadge = pelaporan_status_badge($detailPengaduan['status']);

    $detailStatusDesc  = pelaporan_status_description($detailPengaduan['status']);

}



foreach($logPublik as &$logItem){

    $fromRaw = trim((string)($logItem['status_from'] ?? ''));

    $toRaw   = trim((string)($logItem['status_to'] ?? ''));

    $logItem['status_from_label'] = $fromRaw !== '' ? login_public_status_label($fromRaw) : 'Pengaduan Masuk';

    $logItem['status_to_label']   = $toRaw   !== '' ? login_public_status_label($toRaw)   : 'Pengaduan Masuk';

    $logItem['status_from_badge'] = $fromRaw !== '' ? pelaporan_status_badge($fromRaw) : 'bg-secondary';

    $logItem['status_to_badge']   = $toRaw   !== '' ? pelaporan_status_badge($toRaw)   : 'bg-secondary';

    $logItem['note'] = trim((string)($logItem['note'] ?? ''));

    $logItem['user_name'] = trim((string)($logItem['user_name'] ?? '')) ?: 'Sistem/Publik';

}

unset($logItem);

function login_table_exists(mysqli $conn, string $table): bool {
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

function login_column_exists(mysqli $conn, string $table, string $column): bool {
  static $cache = [];
  $key = $table . '.' . $column;
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

$publicMediaBase = __DIR__ . '/assets/public';
$publicMediaUrl = '/ski_new/assets/public';
$publicMediaSlides = [];

if (login_table_exists($conn, 'public_media')) {
  $publicMediaColumns = ['title', 'caption', 'file_path', 'media_type'];
  foreach (['thumbnail_path', 'auto_slide', 'slide_interval'] as $optionalColumn) {
    if (login_column_exists($conn, 'public_media', $optionalColumn)) {
      $publicMediaColumns[] = $optionalColumn;
    }
  }
  $publicMediaSelect = implode(', ', array_map(static function ($column) {
    return '`' . $column . '`';
  }, $publicMediaColumns));
  if ($qm = $conn->query("SELECT {$publicMediaSelect} FROM public_media WHERE is_active = 1 ORDER BY sort_order ASC, id DESC")) {
    while ($media = $qm->fetch_assoc()) {
      $relPath = trim((string)($media['file_path'] ?? ''));
      $mediaType = strtolower(trim((string)($media['media_type'] ?? '')));
      if ($relPath === '' || !in_array($mediaType, ['image', 'video'], true)) continue;
      $fullPath = __DIR__ . '/' . ltrim(str_replace(['\\', '..'], ['/', ''], $relPath), '/');
      if (!is_file($fullPath)) continue;
      $thumbnailPath = trim((string)($media['thumbnail_path'] ?? ''));
      $thumbnailFullPath = $thumbnailPath !== '' ? __DIR__ . '/' . ltrim(str_replace(['\\', '..'], ['/', ''], $thumbnailPath), '/') : '';
      $autoSlide = isset($media['auto_slide']) ? (int)$media['auto_slide'] : 1;
      $slideInterval = isset($media['slide_interval']) ? (int)$media['slide_interval'] : 6500;
      if ($slideInterval < 3000) $slideInterval = 3000;
      if ($slideInterval > 30000) $slideInterval = 30000;
      $publicMediaSlides[] = [
        'type' => $mediaType,
        'src' => '/ski_new/' . ltrim(str_replace('\\', '/', $relPath), '/'),
        'thumbnail' => ($thumbnailPath !== '' && is_file($thumbnailFullPath)) ? '/ski_new/' . ltrim(str_replace('\\', '/', $thumbnailPath), '/') : '',
        'title' => trim((string)($media['title'] ?? '')),
        'caption' => trim((string)($media['caption'] ?? '')),
        'auto_slide' => $autoSlide === 1 ? 1 : 0,
        'interval' => $slideInterval,
      ];
    }
    $qm->free();
  }
}

if (empty($publicMediaSlides)) {
  $legacyMedia = [
    ['type' => 'video', 'path' => $publicMediaBase . '/edukasi-sikat.mp4', 'src' => $publicMediaUrl . '/edukasi-sikat.mp4'],
    ['type' => 'video', 'path' => $publicMediaBase . '/videos/edukasi-sikat.mp4', 'src' => $publicMediaUrl . '/videos/edukasi-sikat.mp4'],
    ['type' => 'image', 'path' => $publicMediaBase . '/banner-sikat.jpg', 'src' => $publicMediaUrl . '/banner-sikat.jpg'],
    ['type' => 'image', 'path' => $publicMediaBase . '/poster-kepatuhan.jpg', 'src' => $publicMediaUrl . '/poster-kepatuhan.jpg'],
  ];
  foreach ($legacyMedia as $media) {
    if (is_file($media['path'])) {
      $publicMediaSlides[] = [
        'type' => $media['type'],
        'src' => $media['src'],
        'thumbnail' => '',
        'title' => 'Media edukasi SIKAT',
        'caption' => '',
        'auto_slide' => 1,
        'interval' => 6500,
      ];
      break;
    }
  }
}

$publicContact = null;
$publicContactWaLink = '';
$publicContactMailLink = '';
$publicContactMapsLink = '';
$publicSocialLinks = [];

function login_public_whatsapp_link(string $number): string {
  $digits = preg_replace('/\D+/', '', $number) ?: '';
  if ($digits === '') return '';
  if (strpos($digits, '0') === 0) {
    $digits = '62' . substr($digits, 1);
  }
  return 'https://wa.me/' . $digits;
}

function login_public_http_url(string $url): string {
  $url = trim($url);
  if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '';
  $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
  return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

if (login_table_exists($conn, 'public_contacts')) {
  if ($qc = $conn->query("SELECT contact_name, description, whatsapp, phone, email, address, service_hours, maps_url FROM public_contacts WHERE is_active = 1 ORDER BY id ASC LIMIT 1")) {
    $row = $qc->fetch_assoc();
    if ($row) {
      $publicContact = $row;
      $publicContactWaLink = login_public_whatsapp_link((string)($row['whatsapp'] ?? ''));
      $email = trim((string)($row['email'] ?? ''));
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $publicContactMailLink = 'mailto:' . $email;
      }
      $mapsUrl = trim((string)($row['maps_url'] ?? ''));
      $publicContactMapsLink = login_public_http_url($mapsUrl);
    }
    $qc->free();
  }
}

$publicSocialIcons = [
  'website' => 'globe',
  'facebook' => 'facebook',
  'instagram' => 'instagram',
  'youtube' => 'youtube',
  'tiktok' => 'tiktok',
  'twitter' => 'twitter-x',
  'whatsapp_channel' => 'whatsapp',
  'linkedin' => 'linkedin',
];

if (login_table_exists($conn, 'public_social_links')) {
  if ($qs = $conn->query("SELECT platform, label, url, icon_key FROM public_social_links WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")) {
    while ($social = $qs->fetch_assoc()) {
      $url = login_public_http_url((string)($social['url'] ?? ''));
      $label = trim((string)($social['label'] ?? ''));
      if ($url === '' || $label === '') continue;
      $platform = strtolower(trim((string)($social['platform'] ?? '')));
      $iconKey = trim((string)($social['icon_key'] ?? ''));
      if ($iconKey === '') {
        $iconKey = $publicSocialIcons[$platform] ?? 'link-45deg';
      }
      $publicSocialLinks[] = [
        'label' => $label,
        'url' => $url,
        'icon' => preg_replace('/[^a-z0-9-]/', '', strtolower($iconKey)) ?: 'link-45deg',
      ];
    }
    $qs->free();
  }
}

?>

<!doctype html>

<html lang="id">

<head>

  <meta charset="utf-8">

  <title>SIKAT  Sistem Informasi Satuan Kepatuhan Intern</title>

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap + Icons -->

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link href="assets/css/ui_base.css" rel="stylesheet">
  <link rel="preload" as="image" href="/ski_new/asset/logo-sikat-baru-140.png">

  <style>

    :root{

      --brand-green:#218838; --brand-green-dark:#1b6e2c; --brand-accent:#f0c300;

      --bg-soft:#f4f8f5; --card-soft:#e9f5ee; --text-green:#107a3d; --border-soft:#d6e9de;

    }

    body{background:var(--bg-soft);}

    .app-header{background:var(--brand-green); border-bottom:3px solid var(--brand-accent); color:#fff;}

    .public-page .app-header{padding-top:.3rem; padding-bottom:.3rem;}

    .app-header .brand-title{font-weight:600; letter-spacing:.2px; font-size:.9rem; line-height:1.25;}

    .login-logo{height:50px !important; width:auto;}
    .public-page .app-header .sikat-logo-wrap{display:inline-flex;align-items:center;line-height:0;padding:0;border:0;background:transparent;}
    .public-page .app-header .login-logo{display:block;height:50px;width:auto;}
    .public-page .app-header .public-logo{filter:drop-shadow(0 0 1px rgba(240,195,0,.9)) drop-shadow(0 0 4px rgba(255,255,255,.9));-webkit-filter:drop-shadow(0 0 1px rgba(240,195,0,.9)) drop-shadow(0 0 4px rgba(255,255,255,.9));}

    .public-summary{background:#f7fbf8;}

    .public-focus{border-color:#bfe3cc; box-shadow:0 8px 24px rgba(16,122,61,.08);}

    .soft-card{background:#fff;border:1px solid var(--border-soft);border-radius:14px;box-shadow:0 6px 18px rgba(16,122,61,.06);}

    .public-hero{background:linear-gradient(135deg,#ffffff 0%,#f2faf5 100%);overflow:hidden;padding:24px !important;}
    .public-hero-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,3fr);gap:32px;align-items:start;}
    .hero-copy{max-width:440px;align-self:start;padding-top:24px;}
    .hero-media-column{width:100%;max-width:760px;justify-self:center;}
    .hero-copy .section-kicker{margin-bottom:6px;}
    .public-hero h1{color:#0f6e39;font-weight:800;letter-spacing:0;font-size:clamp(1.45rem,2.25vw,2.05rem);line-height:1.16;margin-bottom:14px !important;}
    .public-hero .lead{color:#4f675a;font-size:.96rem;line-height:1.52;max-width:500px;margin-bottom:0 !important;}
    .hero-services{margin-top:20px;}
    .hero-services-title{font-size:.9rem;font-weight:800;color:#164d38;margin-bottom:8px;}
    .hero-services-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
    .hero-service-link{width:100%;min-height:46px;display:flex;align-items:center;gap:8px;padding:9px 10px;border:1px solid #d7eadf;border-radius:10px;background:rgba(255,255,255,.78);color:#174a3a;text-decoration:none;font-size:.82rem;font-weight:800;cursor:pointer;transition:background .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease;}
    .hero-service-link:hover{background:#fff;border-color:#acd8bd;color:#0f6e39;box-shadow:0 6px 14px rgba(16,122,61,.08);transform:translateY(-1px);}
    .hero-service-link:focus-visible{outline:2px solid #0d7b45;outline-offset:2px;background:#fff;border-color:#91c9a7;}
    .hero-service-link i:first-child{color:#107a3d;font-size:.95rem;}
    .hero-service-link .bi-arrow-right-short{margin-left:auto;font-size:1rem;}
    .hero-social{margin-top:16px;}
    .hero-social-title{font-size:.82rem;font-weight:800;color:#164d38;margin-bottom:7px;}
    .hero-social-list{display:flex;flex-wrap:wrap;gap:7px;}
    .hero-social-link{display:inline-flex;align-items:center;gap:5px;min-height:32px;padding:6px 10px;border:1px solid #d7eadf;border-radius:999px;background:rgba(255,255,255,.82);color:#174a3a;text-decoration:none;font-size:.78rem;font-weight:800;transition:background .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease;}
    .hero-social-link:hover{background:#fff;border-color:#acd8bd;color:#0f6e39;box-shadow:0 6px 14px rgba(16,122,61,.08);transform:translateY(-1px);}
    .hero-social-link i{color:#107a3d;font-size:.9rem;}
    .public-media{background:#fff;border:1px solid #d9eadf;border-radius:12px;overflow:hidden;display:flex;align-items:stretch;justify-content:center;position:relative;}
    .media-frame{width:100%;height:370px;overflow:hidden;background:linear-gradient(135deg,#f4fbf7 0%,#e8f4ed 100%);position:relative;display:flex;align-items:center;justify-content:center;}
    .media-frame.media-landscape{background:#f4fbf7;}
    .media-frame.media-portrait{background:linear-gradient(135deg,#f7fcf9 0%,#e4f2e9 100%);}
    .media-frame.media-video{background:#0b2c20;}
    .public-media img,.public-media video{width:100%;height:100%;object-fit:contain;display:block;}
    .public-media video{object-fit:contain;background:#0b2c20;}
    .media-frame.media-portrait img{width:auto;height:100%;max-width:100%;object-fit:contain;}
    .media-frame.media-landscape img{width:100%;height:100%;object-fit:contain;}
    .media-frame.media-video video{width:100%;height:100%;object-fit:contain;}
    .public-media .carousel,.public-media .carousel-inner,.public-media .carousel-item{width:100%;}
    .public-media-caption{padding:14px 16px 16px;background:#fff;border-top:1px solid #dcefe4;color:#244d3a;text-align:left;}
    .public-media-caption h2{font-size:1rem;font-weight:800;margin:0 0 4px;color:#164d38;}
    .public-media-caption p{font-size:.88rem;margin:0;color:#607066;line-height:1.45;}
    .caption-short{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .caption-full{display:none;margin-top:8px;}
    .caption-toggle{display:inline-flex;align-items:center;gap:.25rem;margin-top:8px;padding:0;border:0;background:transparent;color:#107a3d;font-size:.84rem;font-weight:800;}
    .caption-toggle:hover{text-decoration:underline;}
    .public-media-caption.is-expanded .caption-short{display:none;}
    .public-media-caption.is-expanded .caption-full{display:block;}
    .public-media .carousel-indicators{margin-bottom:.35rem;}
    .public-media .carousel-control-prev,.public-media .carousel-control-next{width:12%;}
    .public-media-placeholder{width:100%;min-height:260px;padding:30px;text-align:center;color:#52675d;background:
      radial-gradient(circle at 18% 20%, rgba(240,195,0,.18) 0 42px, transparent 43px),
      radial-gradient(circle at 82% 78%, rgba(16,122,61,.12) 0 62px, transparent 63px),
      linear-gradient(135deg,#f9fcfa 0%,#edf7f1 100%);
      display:flex;flex-direction:column;align-items:center;justify-content:center;}
    .public-media-placeholder .media-icon{width:76px;height:76px;border-radius:20px;display:inline-flex;align-items:center;justify-content:center;background:#e7f5ee;color:#107a3d;font-size:2.4rem;margin-bottom:14px;border:1px solid #cfe7da;box-shadow:0 10px 24px rgba(16,122,61,.12);}
    .media-path-pill{display:inline-flex;align-items:center;gap:.4rem;margin-top:14px;padding:7px 10px;border-radius:999px;background:#fff;border:1px solid #dcefe4;color:#315b4d;font-size:.78rem;font-weight:700;}
    .media-file-list{display:flex;flex-wrap:wrap;justify-content:center;gap:7px;margin-top:12px;}
    .section-kicker{font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#107a3d;margin-bottom:4px;}
    .service-grid,.flow-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}
    .internal-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px;}
    .service-card,.flow-card{background:#fff;border:1px solid #e1eee6;border-radius:12px;padding:16px;height:100%;}
    .service-card{display:flex;flex-direction:column;text-decoration:none;color:inherit;transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease;}
    .service-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(16,122,61,.1);border-color:#bfe3cc;color:inherit;}
    .service-icon,.flow-step{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#e7f5ee;color:#107a3d;font-weight:800;margin-bottom:8px;}
    .service-card h3,.flow-card h3{font-size:.98rem;color:#164d38;margin-bottom:6px;font-weight:800;}
    .service-card p,.flow-card p{font-size:.86rem;color:#64746c;line-height:1.4;margin:0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
    .service-action{display:inline-flex;align-items:center;gap:.35rem;margin-top:auto;padding-top:10px;color:#107a3d;font-size:.82rem;font-weight:800;}
    .internal-card{position:relative;padding:12px;}
    .internal-card p{-webkit-line-clamp:2;font-size:.82rem;}
    .internal-card .service-icon{width:30px;height:30px;margin-bottom:7px;}
    .internal-card h3{font-size:.92rem;margin-bottom:4px;padding-right:58px;}
    .internal-section{margin-top:18px;padding-top:16px;border-top:1px solid #e6f0ea;}
    .internal-section-title{font-size:.95rem;font-weight:800;color:#164d38;margin-bottom:2px;}
    .contact-callout{margin-top:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;background:#f7fbf8;border:1px solid #dcefe4;border-radius:12px;padding:12px 14px;}
    .contact-callout strong{color:#164d38;}
    .public-contact-card{background:#fff;border:1px solid #dcefe4;border-radius:12px;padding:18px;box-shadow:0 4px 14px rgba(16,122,61,.05);}
    .public-contact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px;}
    .public-contact-item{display:flex;gap:10px;align-items:flex-start;background:#f7fbf8;border:1px solid #e2f0e8;border-radius:10px;padding:10px 12px;min-width:0;}
    .public-contact-item i{color:#107a3d;margin-top:2px;}
    .public-contact-item span{display:block;font-size:.78rem;font-weight:800;color:#607066;text-transform:uppercase;letter-spacing:.04em;}
    .public-contact-item strong{display:block;color:#164d38;font-size:.92rem;font-weight:800;overflow-wrap:anywhere;}
    .public-contact-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}
    .public-social-links{margin-top:16px;padding-top:14px;border-top:1px solid #e2f0e8;}
    .public-social-title{font-size:.86rem;font-weight:800;color:#164d38;margin-bottom:8px;}
    .public-social-list{display:flex;flex-wrap:wrap;gap:8px;}
    .public-social-link{display:inline-flex;align-items:center;gap:6px;border:1px solid #d6e9de;border-radius:999px;background:#f7fbf8;color:#174a3a;text-decoration:none;padding:7px 11px;font-size:.84rem;font-weight:800;transition:background .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease;}
    .public-social-link:hover{background:#fff;border-color:#acd8bd;color:#0f6e39;box-shadow:0 6px 14px rgba(16,122,61,.08);transform:translateY(-1px);}
    .public-social-link i{color:#107a3d;}
    .contact-empty{display:flex;align-items:center;gap:10px;background:#f7fbf8;border:1px dashed #bfe3cc;border-radius:12px;padding:14px;color:#52675d;}
    .contact-empty i{color:#107a3d;font-size:1.35rem;}
    .contact-panel{display:none;}
    .contact-panel.is-open{display:block;animation:contactFadeIn .18s ease;}
    .contact-toggle .bi{transition:transform .16s ease;}
    .contact-toggle[aria-expanded="true"] .bi{transform:rotate(180deg);}
    @keyframes contactFadeIn{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:translateY(0);}}

    .btn-primary{ background:var(--brand-green); border-color:var(--brand-green-dark); }

    .btn-primary:hover{ background:var(--brand-green-dark); border-color:var(--brand-green-dark); }

    .menu-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;}

    .menu-tile{display:flex;align-items:center;gap:.6rem;background:#fff;border:1px solid #dcefe4;color:#1b5a40;border-radius:10px;padding:10px 12px;text-decoration:none;min-width:0;font-weight:700;font-size:.92rem;box-shadow:0 2px 8px rgba(16,122,61,.04);}

    .menu-tile i{margin-right:.2rem;opacity:.85;}

    .menu-tile:hover{background:#eef8f2;color:#0f6e39;border-color:#bfe3cc;}
    .internal-badge{margin-left:auto;padding:1px 6px;border-radius:999px;background:#fff8dc;color:#78620a;border:1px solid #f3dc82;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.025em;}
    .back-top-link{display:inline-flex;align-items:center;gap:.45rem;border:1px solid #dcefe4;background:#fff;color:#1b5a40;border-radius:999px;padding:8px 12px;text-decoration:none;font-weight:700;font-size:.9rem;}
    .back-top-link:hover{background:#eef8f2;color:#0f6e39;border-color:#bfe3cc;}

    .stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;}
    .stat-card{background:#fff;border:1px solid #dcefe4;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(16,122,61,.04);}
    .stat-card .h4{color:#0f6e39;font-weight:800;}
    .public-form-card .form-label{font-weight:700;color:#244d3a;font-size:.92rem;}
    .public-form-card .form-control,.public-form-card .form-select{border-color:#d3e7dc;border-radius:10px;}
    .public-form-card .form-control:focus,.public-form-card .form-select:focus{border-color:#107a3d;box-shadow:0 0 0 .2rem rgba(16,122,61,.12);}
    .track-panel{background:#f7fbf8;border:1px solid #dcefe4;border-radius:12px;padding:14px;}
    .form-section{border:1px solid #e1eee6;border-radius:12px;padding:16px;background:#fff;margin-bottom:14px;}
    .form-section-title{display:flex;align-items:center;gap:.5rem;color:#164d38;font-weight:800;font-size:.95rem;margin-bottom:12px;}
    .form-section-title i{color:#107a3d;}
    .helper-text{font-size:.82rem;color:#6b7280;margin-top:5px;}
    .submit-panel{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;border-radius:12px;background:#f7fbf8;border:1px solid #dcefe4;padding:14px;}
    .public-action-modal .modal-content{max-height:85vh;border:1px solid #dcefe4;border-radius:14px;overflow:hidden;}
    .public-action-modal .modal-header{background:#f7fbf8;border-bottom:1px solid #dcefe4;}
    .public-action-modal .modal-body{overflow-y:auto;padding:20px;}
    .modal-backdrop.show{opacity:.45;}

    .dropdown-menu{ z-index: 2000; }

    .file-hint{font-size:.875rem;color:#6b7280}

    @media (max-width: 992px){
      .service-grid,.flow-grid,.stat-grid,.internal-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .public-hero-grid{grid-template-columns:minmax(0,45fr) minmax(0,55fr);gap:22px;}
      .media-frame{height:320px;}
      .hero-copy{max-width:none;padding-top:18px;}
    }

    @media (max-width: 767px){
      .public-hero-grid{grid-template-columns:1fr;gap:22px;}
      .hero-media-column{max-width:none;}
      .hero-copy{padding-top:0;}
    }

    @media (max-width: 576px){
      .service-grid,.flow-grid,.stat-grid,.internal-grid{grid-template-columns:1fr;}
      .hero-services-grid{grid-template-columns:1fr;}
      .hero-services{margin-top:16px;}
      .hero-service-link{min-height:44px;padding:9px 11px;}
      .contact-callout{align-items:flex-start;flex-direction:column;}
      .public-contact-grid{grid-template-columns:1fr;}
      .media-frame{height:270px;}
    }

  </style>

  <link rel="stylesheet" href="assets/css/password_toggle.css">

  <?php include __DIR__ . '/includes/head_favicon.php'; ?>

<style>
/* SIKAT_MANUAL_MEDIA_CENTER_DOTS_LIGHTBOX_CSS */
.public-media .carousel-item,
.public-media .media-frame,
.public-media .media-preview-trigger {
  display: flex;
  align-items: center;
  justify-content: center;
}

.public-media .carousel-item {
  flex-direction: column;
}

.public-media .media-frame {
  width: 100%;
  overflow: hidden;
}

.public-media .media-preview-trigger {
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: zoom-in;
  position: relative;
}

.public-media .media-frame img,
.public-media .media-frame video,
.public-media .media-preview-trigger img,
.public-media .media-preview-trigger video {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  margin-left: auto;
  margin-right: auto;
}

.public-media .media-frame.media-portrait img,
.public-media .media-frame.media-portrait video {
  width: auto !important;
  height: 100% !important;
  max-width: 100%;
  object-fit: contain;
}

.sikat-video-preview-play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.sikat-video-preview-play span {
  width: 64px;
  height: 64px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 92, 55, .86);
  color: #fff;
  box-shadow: 0 14px 34px rgba(0,0,0,.22);
  border: 2px solid rgba(255, 210, 64, .9);
  font-size: 1.65rem;
}

.sikat-media-dots {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 10px 12px 0;
  width: 100%;
}

.sikat-media-dot {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  border: 2px solid rgba(0, 105, 63, .45);
  background: rgba(221, 244, 232, .9);
  cursor: pointer;
  transition: all .25s ease;
  padding: 0;
  box-shadow: inset 0 0 0 2px rgba(255,255,255,.75);
}

.sikat-media-dot.active {
  width: 30px;
  background: linear-gradient(135deg, #006b3f, #0da85d);
  border-color: #d8a31a;
  box-shadow: 0 0 0 3px rgba(216, 163, 26, .20), 0 8px 18px rgba(0, 90, 50, .20);
}

.sikat-media-lightbox {
  position: fixed;
  inset: 0;
  z-index: 99999;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 22px;
  background: rgba(5, 32, 22, .76);
  backdrop-filter: blur(4px);
}

.sikat-media-lightbox.is-open {
  display: flex;
}

.sikat-media-lightbox-dialog {
  width: min(94vw, 1040px);
  max-height: 90vh;
  background: #fff;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 22px 70px rgba(0,0,0,.35);
  border: 1px solid rgba(201, 232, 216, .9);
}

.sikat-media-lightbox-dialog.is-portrait {
  width: min(94vw, 520px);
}

.sikat-media-lightbox-dialog.is-square {
  width: min(94vw, 720px);
}

.sikat-media-lightbox-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 18px;
  background: #f6fbf8;
  border-bottom: 1px solid #dcefe4;
}

.sikat-media-lightbox-title {
  margin: 0;
  color: #063f2b;
  font-weight: 800;
  font-size: 1.05rem;
}

.sikat-media-lightbox-close {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 1px solid #cfe7da;
  background: #fff;
  color: #063f2b;
  font-size: 1.35rem;
  line-height: 1;
  cursor: pointer;
}

.sikat-media-lightbox-body {
  background: #061f17;
  display: flex;
  align-items: center;
  justify-content: center;
  max-height: 72vh;
}

.sikat-media-lightbox-dialog.is-portrait .sikat-media-lightbox-body {
  background: #061f17;
}

.sikat-media-lightbox-body img,
.sikat-media-lightbox-body video {
  max-width: 100%;
  max-height: 72vh;
  object-fit: contain;
  display: block;
}

.sikat-media-lightbox-dialog.is-portrait .sikat-media-lightbox-body video,
.sikat-media-lightbox-dialog.is-portrait .sikat-media-lightbox-body img {
  width: auto;
  max-width: 100%;
  height: min(72vh, 760px);
}

.sikat-media-lightbox-caption {
  padding: 13px 18px 16px;
  color: #456155;
  line-height: 1.5;
  max-height: 110px;
  overflow: auto;
}

body.sikat-lightbox-open {
  overflow: hidden;
}

@media (max-width: 576px) {
  .sikat-media-lightbox {
    padding: 10px;
  }

  .sikat-media-lightbox-dialog,
  .sikat-media-lightbox-dialog.is-portrait,
  .sikat-media-lightbox-dialog.is-square {
    width: 94vw;
  }

  .sikat-media-lightbox-body,
  .sikat-media-lightbox-body img,
  .sikat-media-lightbox-body video {
    max-height: 68vh;
  }
}
</style>
<style>
/* SIKAT_FIX_DOTS_AND_CENTER_MEDIA_20260625 */
.public-media {
  display: block !important;
  position: relative;
}

.public-media .carousel {
  width: 100% !important;
  position: relative;
}

.public-media .carousel-inner {
  width: 100% !important;
}

.public-media .carousel-item {
  width: 100% !important;
}

.public-media .carousel-item.active,
.public-media .carousel-item-next,
.public-media .carousel-item-prev {
  display: block !important;
}

.public-media .media-frame {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
}

.public-media .media-preview-trigger {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
  height: 100% !important;
  margin: 0 auto !important;
}

.public-media .media-frame img,
.public-media .media-frame video,
.public-media .media-preview-trigger img,
.public-media .media-preview-trigger video {
  display: block !important;
  object-fit: contain !important;
  margin-left: auto !important;
  margin-right: auto !important;
}

.public-media .media-frame.media-portrait img,
.public-media .media-frame.media-portrait video,
.public-media .media-frame.media-portrait .media-preview-trigger img,
.public-media .media-frame.media-portrait .media-preview-trigger video {
  width: auto !important;
  height: 100% !important;
  max-width: 100% !important;
  object-fit: contain !important;
}

.public-media .media-frame.media-landscape img,
.public-media .media-frame.media-landscape video,
.public-media .media-frame.media-landscape .media-preview-trigger img,
.public-media .media-frame.media-landscape .media-preview-trigger video {
  width: 100% !important;
  height: 100% !important;
  object-fit: contain !important;
}

.public-media .sikat-media-dots {
  position: absolute !important;
  left: 50% !important;
  bottom: 14px !important;
  transform: translateX(-50%) !important;
  z-index: 20 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 9px !important;
  padding: 7px 10px !important;
  width: auto !important;
  border-radius: 999px !important;
  background: rgba(255,255,255,.72) !important;
  backdrop-filter: blur(6px);
  box-shadow: 0 8px 24px rgba(0, 60, 34, .14);
}

.public-media .sikat-media-dot {
  width: 12px !important;
  height: 12px !important;
  border-radius: 999px !important;
  border: 2px solid rgba(0, 105, 63, .55) !important;
  background: rgba(230, 247, 238, .95) !important;
  cursor: pointer !important;
  transition: all .25s ease !important;
  padding: 0 !important;
}

.public-media .sikat-media-dot.active {
  width: 32px !important;
  background: linear-gradient(135deg, #006b3f, #0da85d) !important;
  border-color: #d8a31a !important;
  box-shadow: 0 0 0 3px rgba(216,163,26,.22), 0 8px 18px rgba(0,90,50,.22) !important;
}

.public-media-caption {
  width: 100% !important;
}
</style>
</head>

<body class="public-page">

  <!-- Header -->

  <header class="app-header" id="top">

    <div class="container d-flex align-items-center justify-content-between">

      <div class="d-flex align-items-center gap-3">

        <span class="sikat-logo-wrap">
          <img src="/ski_new/asset/logo-sikat-baru-140.png" alt="SIKAT" class="login-logo public-logo">
        </span>

        <div class="brand-title">Sistem Informasi Kepatuhan Internal Poltekkes Ternate (SIKAT)</div>
        <span class="sikat-version-badge">SIKAT v3.0</span>

      </div>

      <div>

        <?php if (empty($_SESSION['user'])): ?>

          <a class="btn btn-light btn-sm" data-bs-toggle="modal" href="#loginModal"><i class="bi bi-person-lock me-1"></i>Login</a>

        <?php else: ?>

          <div class="dropdown">

            <button class="btn btn-warning btn-sm dropdown-toggle" type="button" id="profileMenuBtn" data-bs-toggle="dropdown" aria-expanded="false">

              <i class="bi bi-person-badge me-1"></i><?= e($_SESSION['user']['nama']) ?> (<?= e($_SESSION['user']['peran']) ?>)

            </button>
            
            <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="profileMenuBtn">

              <?php
              $akses_dashboard = (int)($_SESSION['user']['akses_dashboard'] ?? 0);
              $akses_pelaporan = (int)($_SESSION['user']['akses_pelaporan'] ?? 0);
              $akses_review    = (int)($_SESSION['user']['akses_review'] ?? 0);
            ?>
            
            <?php if ($akses_dashboard === 1): ?>
              <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
            <?php endif; ?>
            
            <?php if ($akses_pelaporan === 1): ?>
              <li><a class="dropdown-item" href="pelaporan.php"><i class="bi bi-list-check me-1"></i>Pelaporan</a></li>
            <?php endif; ?>
            
            <?php if ($akses_review === 1): ?>
              <li><a class="dropdown-item" href="review.php"><i class="bi bi-clipboard-check me-1"></i>Review</a></li>
            <?php endif; ?>


              <li><hr class="dropdown-divider"></li>

              <li>

                <form method="post" class="mb-0">

                  <?= csrf_field(); ?><input type="hidden" name="action" value="logout">

                  <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>

                </form>

              </li>

            </ul>

          </div>

        <?php endif; ?>

      </div>

    </div>

  </header>



  <main class="container my-4" style="max-width:1100px">

    <?php if ((isset($_GET['logged_out']) && $_GET['logged_out'] === '1')): ?>

      <div class="alert alert-info mb-3">Anda telah logout.</div>

    <?php endif; ?>



    <!-- Hero Publik -->

    <section class="soft-card public-hero mb-4">

      <div class="public-hero-grid">

        <div class="hero-copy">

          <div class="section-kicker">Portal Publik SIKAT</div>

          <h1 class="mb-3">Laporkan, Pantau, dan Dukung Kepatuhan Internal</h1>

          <p class="lead mb-4">SIKAT menjadi kanal publik untuk menyampaikan laporan, memantau pengaduan, memberikan saran dan kritik, mengakses kebijakan, serta mendukung proses e-reviu secara terdokumentasi.</p>

          <div class="hero-services">
            <div class="hero-services-title">Layanan publik dan akses cepat</div>
            <div class="hero-services-grid">
              <a class="hero-service-link" href="#pelaporan" data-modal-target="pelaporanModal"><i class="bi bi-flag"></i><span>Pelaporan</span><i class="bi bi-arrow-right-short"></i></a>
              <a class="hero-service-link" href="#lacak-pengaduan" data-modal-target="lacakModal"><i class="bi bi-search"></i><span>Lacak Pengaduan</span><i class="bi bi-arrow-right-short"></i></a>
              <a class="hero-service-link" href="#saran-kritik" data-modal-target="saranModal"><i class="bi bi-chat-dots"></i><span>Saran & Kritik</span><i class="bi bi-arrow-right-short"></i></a>
              <a class="hero-service-link" href="kebijakan.php"><i class="bi bi-journal-text"></i><span>Data Kebijakan</span><i class="bi bi-arrow-right-short"></i></a>
            </div>
          </div>

          <?php if (!empty($publicSocialLinks)): ?>
            <div class="hero-social">
              <div class="hero-social-title">Terhubung dengan kami</div>
              <div class="hero-social-list">
                <?php foreach ($publicSocialLinks as $social): ?>
                  <a class="hero-social-link" href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-<?= e($social['icon']) ?>"></i><span><?= e($social['label']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>

        <div class="hero-media-column">

          <div class="public-media">

            <?php if (!empty($publicMediaSlides)): ?>

              <?php
                $firstSlide = $publicMediaSlides[0];
                $carouselInterval = ((int)($firstSlide['auto_slide'] ?? 1) === 1) ? (string)(int)($firstSlide['interval'] ?? 6500) : 'false';
              ?>
              <div id="publicMediaCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="<?= e($carouselInterval) ?>">
                <?php if (count($publicMediaSlides) > 1): ?>
                  <div class="carousel-indicators">
                    <?php foreach ($publicMediaSlides as $idx => $_slide): ?>
                      <button type="button" data-bs-target="#publicMediaCarousel" data-bs-slide-to="<?= (int)$idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= (int)$idx + 1 ?>"></button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="carousel-inner">
                  <?php foreach ($publicMediaSlides as $idx => $slide): ?>
                    <?php $slideInterval = ((int)($slide['auto_slide'] ?? 1) === 1) ? (string)(int)($slide['interval'] ?? 6500) : 'false'; ?>
                    <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>" data-bs-interval="<?= e($slideInterval) ?>">
                      <?php if ($slide['type'] === 'video'): ?>
                        <div class="media-frame media-video">
                          <video controls preload="metadata" <?= trim((string)($slide['thumbnail'] ?? '')) !== '' ? 'poster="' . e($slide['thumbnail']) . '"' : '' ?>>
                            <source src="<?= e($slide['src']) ?>">
                            Browser Anda tidak mendukung pemutaran video.
                          </video>
                        </div>
                      <?php else: ?>
                        <div class="media-frame media-landscape">
                          <img src="<?= e($slide['src']) ?>" alt="<?= e($slide['title'] ?: 'Media edukasi SIKAT') ?>">
                        </div>
                      <?php endif; ?>
                      <?php if (($slide['title'] ?? '') !== '' || ($slide['caption'] ?? '') !== ''): ?>
                        <div class="public-media-caption">
                          <?php if (($slide['title'] ?? '') !== ''): ?><h2><?= e($slide['title']) ?></h2><?php endif; ?>
                          <?php if (($slide['caption'] ?? '') !== ''): ?>
                            <?php $caption = (string)$slide['caption']; $isLongCaption = function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') > 150 : strlen($caption) > 150; ?>
                            <p class="caption-short"><?= e($caption) ?></p>
                            <?php if ($isLongCaption): ?>
                              <p class="caption-full"><?= e($caption) ?></p>
                              <button type="button" class="caption-toggle" aria-expanded="false">Baca selengkapnya <i class="bi bi-chevron-down"></i></button>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php if (count($publicMediaSlides) > 1): ?>
                  <button class="carousel-control-prev" type="button" data-bs-target="#publicMediaCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Sebelumnya</span>
                  </button>
                  <button class="carousel-control-next" type="button" data-bs-target="#publicMediaCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Berikutnya</span>
                  </button>
                <?php endif; ?>
              </div>

            <?php else: ?>

              <div class="public-media-placeholder">
                <div class="media-icon"><i class="bi bi-shield-check"></i></div>
                <h2 class="h5 text-success mb-2">Media edukasi kepatuhan</h2>
                <p class="mb-0">Tambahkan poster atau video edukasi agar portal publik SIKAT menampilkan informasi kepatuhan yang lebih kaya.</p>
                <div class="media-file-list">
                  <span class="media-path-pill"><i class="bi bi-film"></i> assets/public/edukasi-sikat.mp4</span>
                  <span class="media-path-pill"><i class="bi bi-image"></i> assets/public/banner-sikat.jpg</span>
                  <span class="media-path-pill"><i class="bi bi-file-image"></i> assets/public/poster-kepatuhan.jpg</span>
                </div>
              </div>

            <?php endif; ?>

          </div>

        </div>

      </div>

    </section>

    <!-- Alur Pelaporan -->

    <section class="soft-card p-4 mb-4">

      <div class="section-kicker">Alur Pelaporan</div>

      <h2 class="h5 text-success mb-3">Empat langkah sederhana</h2>

      <div class="flow-grid">

        <div class="flow-card"><div class="flow-step">1</div><h3>Isi laporan</h3><p>Lengkapi kategori, uraian laporan, dan lampiran pendukung bila tersedia.</p></div>

        <div class="flow-card"><div class="flow-step">2</div><h3>Simpan kode tracking</h3><p>Kode tracking digunakan untuk memantau perkembangan laporan.</p></div>

        <div class="flow-card"><div class="flow-step">3</div><h3>Tim melakukan verifikasi</h3><p>Tim menelaah laporan dan memastikan informasi dapat diproses.</p></div>

        <div class="flow-card"><div class="flow-step">4</div><h3>Pantau tindak lanjut</h3><p>Status laporan dapat dilihat kembali melalui fitur Lacak Pengaduan.</p></div>

      </div>

    </section>



    <!-- Akses Internal -->

    <section class="soft-card p-4 mb-4">
      <div class="internal-section-title">Akses Internal</div>
      <div class="text-muted small mb-2">Fitur berikut digunakan oleh pengguna internal/petugas yang memiliki akun.</div>
      <div class="internal-grid">
        <a class="service-card internal-card" href="review.php"><span class="internal-badge position-absolute top-0 end-0 mt-2 me-2">Internal</span><div class="service-icon"><i class="bi bi-clipboard2-data"></i></div><h3>E-Reviu</h3><p>Akses modul e-reviu untuk proses reviu internal yang terdokumentasi.</p><span class="service-action">Masuk E-Reviu <i class="bi bi-arrow-right-short"></i></span></a>
        <a class="service-card internal-card" href="risiko.php"><span class="internal-badge position-absolute top-0 end-0 mt-2 me-2">Internal</span><div class="service-icon"><i class="bi bi-shield-check"></i></div><h3>Manajemen Risiko</h3><p>Pantau dan kelola risiko internal sesuai proses manajemen risiko.</p><span class="service-action">Akses Risiko <i class="bi bi-arrow-right-short"></i></span></a>
        <a class="service-card internal-card" href="self_assessment.php"><span class="internal-badge position-absolute top-0 end-0 mt-2 me-2">Internal</span><div class="service-icon"><i class="bi bi-check2-square"></i></div><h3>Self-Assessment</h3><p>Gunakan modul penilaian mandiri untuk mendukung evaluasi kepatuhan.</p><span class="service-action">Mulai Assessment <i class="bi bi-arrow-right-short"></i></span></a>
      </div>
      <div class="contact-callout">
        <div><strong>Butuh bantuan?</strong> Hubungi pengelola SIKAT atau tim terkait untuk informasi lebih lanjut.</div>
        <a href="#kontak" class="btn btn-sm btn-outline-success contact-toggle" data-target="kontak" aria-controls="kontak" aria-expanded="false">Lihat Kontak <i class="bi bi-chevron-down ms-1"></i></a>
      </div>
    </section>

    <!-- Kontak Publik -->
    <section class="soft-card p-4 mb-4 contact-panel" id="kontak" aria-hidden="true">
      <div class="section-kicker">Kontak SIKAT</div>
      <h2 class="h5 text-success mb-3">Informasi pengelola</h2>
      <?php if ($publicContact): ?>
        <div class="public-contact-card">
          <h3 class="h6 text-success mb-2"><?= e($publicContact['contact_name'] ?: 'Pengelola SIKAT') ?></h3>
          <?php if (trim((string)($publicContact['description'] ?? '')) !== ''): ?>
            <p class="text-muted mb-0"><?= nl2br(e($publicContact['description'])) ?></p>
          <?php endif; ?>
          <div class="public-contact-grid">
            <?php if (trim((string)($publicContact['whatsapp'] ?? '')) !== ''): ?>
              <div class="public-contact-item"><i class="bi bi-whatsapp"></i><div><span>WhatsApp</span><strong><?= e($publicContact['whatsapp']) ?></strong></div></div>
            <?php endif; ?>
            <?php if (trim((string)($publicContact['phone'] ?? '')) !== ''): ?>
              <div class="public-contact-item"><i class="bi bi-telephone"></i><div><span>Telepon</span><strong><?= e($publicContact['phone']) ?></strong></div></div>
            <?php endif; ?>
            <?php if (trim((string)($publicContact['email'] ?? '')) !== ''): ?>
              <div class="public-contact-item"><i class="bi bi-envelope"></i><div><span>Email</span><strong><?= e($publicContact['email']) ?></strong></div></div>
            <?php endif; ?>
            <?php if (trim((string)($publicContact['service_hours'] ?? '')) !== ''): ?>
              <div class="public-contact-item"><i class="bi bi-clock"></i><div><span>Jam Layanan</span><strong><?= e($publicContact['service_hours']) ?></strong></div></div>
            <?php endif; ?>
            <?php if (trim((string)($publicContact['address'] ?? '')) !== ''): ?>
              <div class="public-contact-item"><i class="bi bi-geo-alt"></i><div><span>Alamat</span><strong><?= nl2br(e($publicContact['address'])) ?></strong></div></div>
            <?php endif; ?>
          </div>
          <div class="public-contact-actions">
            <?php if ($publicContactWaLink !== ''): ?>
              <a class="btn btn-success btn-sm" href="<?= e($publicContactWaLink) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>Hubungi WhatsApp</a>
            <?php endif; ?>
            <?php if ($publicContactMailLink !== ''): ?>
              <a class="btn btn-outline-success btn-sm" href="<?= e($publicContactMailLink) ?>"><i class="bi bi-envelope me-1"></i>Kirim Email</a>
            <?php endif; ?>
            <?php if ($publicContactMapsLink !== ''): ?>
              <a class="btn btn-outline-success btn-sm" href="<?= e($publicContactMapsLink) ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt me-1"></i>Lihat Lokasi</a>
            <?php endif; ?>
          </div>
          <?php if (!empty($publicSocialLinks)): ?>
            <div class="public-social-links">
              <div class="public-social-title">Terhubung dengan SIKAT</div>
              <div class="public-social-list">
                <?php foreach ($publicSocialLinks as $social): ?>
                  <a class="public-social-link" href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-<?= e($social['icon']) ?>"></i><span><?= e($social['label']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="contact-empty"><i class="bi bi-info-circle"></i><div>Informasi kontak pengelola SIKAT belum tersedia.</div></div>
      <?php endif; ?>
    </section>



    <!-- Ringkasan Pengaduan -->

    <section class="soft-card p-4 mb-4 public-summary">

      <div class="section-kicker">Ringkasan Pengaduan</div>

      <h2 class="h5 text-success mb-3">Statistik laporan publik</h2>

      <div class="stat-grid">

        <div class="stat-card"><div class="small text-muted">Total Pengaduan</div><div class="h4 mb-0"><?= (int)$stat['total'] ?></div></div>

        <div class="stat-card"><div class="small text-muted">Pengaduan Masuk</div><div class="h4 mb-0"><?= (int)$stat['masuk'] ?></div></div>

        <div class="stat-card"><div class="small text-muted">Tahap Berjalan</div><div class="h4 mb-0"><?= (int)$stat['proses'] ?></div></div>

        <div class="stat-card"><div class="small text-muted">Arsip</div><div class="h4 mb-0"><?= (int)$stat['arsip'] ?></div></div>

      </div>

      <div class="small text-muted mt-2">Pengaduan yang dikembalikan ke pelapor: <?= (int)$stat['kembali'] ?> laporan.</div>

    </section>



    <!-- Pelaporan Modal -->

    <div class="modal fade" id="pelaporanModal" tabindex="-1" aria-labelledby="pelaporanModalLabel">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable public-action-modal">
        <div class="modal-content soft-card">
          <div class="modal-header">
            <h5 class="modal-title text-success" id="pelaporanModalLabel"><i class="bi bi-flag me-1"></i>Form Pelaporan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body public-form-card">

      <div class="section-kicker">Pelaporan</div>

      <h3 class="h6 text-success mb-2"><i class="bi bi-flag me-1"></i>Form Pelaporan</h3>

      <p class="text-muted small mb-3">Isi laporan dengan informasi yang jelas. Identitas dapat dikosongkan jika ingin mengirim sebagai anonim.</p>

      <?php

        $flash_messages = [];

        if ($m = flash('ok_report')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }

        if ($m = flash('err_report')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }

        include __DIR__ . '/includes/flash.php';

      ?>



      <form method="post" enctype="multipart/form-data">

        <?= csrf_field(); ?><input type="hidden" name="action" value="lapor">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="d-none" aria-hidden="true">

        <div class="form-section">

          <div class="form-section-title"><i class="bi bi-person-lines-fill"></i>Identitas Pelapor</div>

          <div class="row g-3">

            <div class="col-md-6"><label class="form-label">Nama</label><input name="nama" class="form-control" placeholder="Boleh kosong jika anonim"></div>

            <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" placeholder="Boleh kosong jika anonim"></div>

            <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="anon" name="anonim"><label class="form-check-label" for="anon">Kirim sebagai anonim</label></div>

          </div>

        </div>

        <div class="form-section">

          <div class="form-section-title"><i class="bi bi-card-text"></i>Isi Laporan</div>

          <div class="row g-3">

          <div class="col-md-6">

            <label class="form-label">Kategori</label>

            <select name="kategori" class="form-select" required>

              <option value="">-- Pilih Kategori --</option>

              <option>Gratifikasi</option><option>Korupsi</option><option>Kotak Saran</option><option>Lainnya</option>

            </select>

            <div class="helper-text">Pilih kategori yang paling sesuai dengan laporan Anda.</div>

          </div>

            <div class="col-12"><label class="form-label">Isi Laporan</label><textarea name="isi" class="form-control" rows="4" required></textarea><div class="helper-text">Jelaskan kejadian, lokasi, waktu, dan pihak terkait jika diketahui.</div></div>

          </div>

        </div>

        <div class="form-section">

          <div class="form-section-title"><i class="bi bi-paperclip"></i>Lampiran</div>

            <label class="form-label">Lampiran Bukti (opsional)</label>

            <input class="form-control" 
            type="file" 
            name="lampiran[]" 
            multiple 
            accept=".pdf,image/*,video/mp4,video/webm,video/quicktime">

            <div class="file-hint mt-1">Format: PDF/JPG/PNG/GIF/WEBP/MP4/WEBM/MOV, maks 5MB per file. Anda bisa memilih beberapa file sekaligus.</div>
            <div class="helper-text">Lampiran membantu proses verifikasi laporan.</div>

        </div>

        <div class="submit-panel">

          <div>
            <div class="form-section-title mb-1"><i class="bi bi-send-check"></i>Kirim Laporan</div>
            <div class="form-text">Simpan <b>kode tracking</b> yang tampil setelah submit.</div>
          </div>

          <div class="d-flex flex-wrap gap-2">

            <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim</button>

            <a href="#lacak-pengaduan" class="btn btn-outline-success" data-modal-target="lacakModal"><i class="bi bi-search me-1"></i>Saya sudah punya kode</a>

          </div>

        </div>

      </form>

          </div>
        </div>
      </div>
    </div>



    <!-- Lacak Modal -->

    <div class="modal fade" id="lacakModal" tabindex="-1" aria-labelledby="lacakModalLabel">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable public-action-modal">
        <div class="modal-content soft-card">
          <div class="modal-header">
            <h5 class="modal-title text-success" id="lacakModalLabel"><i class="bi bi-search me-1"></i>Lacak Pengaduan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body public-form-card">

      <div class="section-kicker">Lacak Pengaduan</div>

      <h3 class="h6 text-success mb-2"><i class="bi bi-search me-1"></i>Lacak Pengaduan</h3>

      <p class="text-muted small mb-3">Masukkan kode tracking yang diterima setelah mengirim laporan.</p>

      <?php

        $flash_messages = [];

        if ($m = flash('ok_track')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }

        if ($m = flash('err_track')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }

        include __DIR__ . '/includes/flash.php';

      ?>



      <form method="post" class="row g-2 align-items-end track-panel">

        <?= csrf_field(); ?><input type="hidden" name="action" value="lacak">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="d-none" aria-hidden="true">

        <div class="col-sm-7 col-md-6"><label class="form-label">Kode Tracking</label><input name="kode" class="form-control form-control-lg" placeholder="cth: SKI-20250930-ABCDE"></div>

        <div class="col-sm-5 col-md-3"><button class="btn btn-primary btn-lg w-100">Lacak</button></div>

      </form>



      <?php if ($detailPengaduan): ?>

        <div class="soft-card p-3 mt-3">

          <div class="d-flex justify-content-between align-items-center gap-3">

            <div><b>Kode:</b> <?= e($detailPengaduan['kode']) ?></div>

            <span class="badge <?= e($detailStatusBadge) ?>"><?= e($detailStatusLabel) ?></span>

          </div>

          <?php if($detailStatusDesc !== ''): ?>

            <div class="small text-muted mt-1"><?= e($detailStatusDesc) ?></div>

          <?php endif; ?>

          <div class="mt-2"><b>Kategori:</b> <?= e($detailPengaduan['kategori']) ?></div>

          <div class="mt-2"><b>Dibuat:</b> <?= e($detailPengaduan['created_at']) ?></div>

          <div class="mt-2"><b>Isi:</b><br><?= nl2br(e($detailPengaduan['isi'])) ?></div>



          <?php if (!empty($logPublik)): ?>

            <div class="mt-3">

              <b>Riwayat Status:</b>

              <ul class="mb-0">

                <?php foreach($logPublik as $h): ?>

                  <li>

                    <span class="badge <?= e($h['status_from_badge']) ?>"><?= e($h['status_from_label']) ?></span>

                    &rarr;

                    <span class="badge <?= e($h['status_to_badge']) ?>"><?= e($h['status_to_label']) ?></span>

                    <?php if($h['note'] !== ''): ?> - <em><?= e($h['note']) ?></em><?php endif; ?>

                    <small class="text-muted"><?= e($h['created_at']) ?> - <?= e($h['user_name']) ?></small>

                  </li>

                <?php endforeach; ?>

              </ul>

            </div>

          <?php endif; ?>

          <?php if (!empty($lampiranDetail)): ?>

            <div class="mt-3">

              <b>Lampiran:</b>

              <ul class="mb-0">

                <?php foreach ($lampiranDetail as $f): ?>

                  <li class="mb-2">

                    <div class="fw-semibold"><?= e($f['original_name']) ?></div>

                    <small class="text-muted d-block">(<?= e($f['mime']) ?>, <?= number_format($f['size_bytes']/1024,1) ?> KB)</small>

                    <div class="mt-1 d-inline-flex gap-2">

                      <a class="btn btn-sm btn-outline-primary" href="attachment_download.php?id=<?= (int)$f['id'] ?>&mode=view" target="_blank" rel="noopener">Lihat</a>

                      <a class="btn btn-sm btn-outline-success" href="attachment_download.php?id=<?= (int)$f['id'] ?>&mode=download" download>Unduh</a>

                    </div>

                  </li>

                <?php endforeach; ?>

              </ul>

            </div>

          <?php endif; ?>

        </div>

      <?php endif; ?>

          </div>
        </div>
      </div>
    </div>



    <!-- Saran & Kritik Modal -->

    <div class="modal fade" id="saranModal" tabindex="-1" aria-labelledby="saranModalLabel">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable public-action-modal">
        <div class="modal-content soft-card">
          <div class="modal-header">
            <h5 class="modal-title text-success" id="saranModalLabel"><i class="bi bi-chat-dots me-1"></i>Saran & Kritik</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body public-form-card">

      <div class="section-kicker">Saran & Kritik</div>

      <h3 class="h6 text-success mb-2"><i class="bi bi-chat-dots me-1"></i>Saran & Kritik</h3>

      <p class="text-muted small mb-3">Masukan Anda membantu meningkatkan mutu layanan dan tata kelola kepatuhan internal.</p>

      <?php

        $flash_messages = [];

        if ($m = flash('ok_fb')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }

        if ($m = flash('err_fb')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }

        include __DIR__ . '/includes/flash.php';

      ?>



      <form method="post">

        <?= csrf_field(); ?><input type="hidden" name="action" value="feedback">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="d-none" aria-hidden="true">

        <div class="row g-3">

          <div class="col-md-6"><label class="form-label">Nama</label><input name="nama_fb" class="form-control"></div>

          <div class="col-md-6"><label class="form-label">Email</label><input name="email_fb" type="email" class="form-control"></div>

          <div class="col-12"><label class="form-label">Saran/Kritik</label><textarea name="isi_fb" class="form-control" rows="3" required></textarea></div>

        </div>

        <div class="mt-3"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim</button></div>

      </form>

          </div>
        </div>
      </div>
    </div>

    <div class="text-center mb-4">

      <a href="#top" class="back-top-link"><i class="bi bi-arrow-up-short"></i>Kembali ke atas</a>

    </div>

  </main>



  <footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded </footer>



  <!-- Login Modal -->

  <div class="modal fade" id="loginModal" tabindex="-1">

    <div class="modal-dialog">

      <div class="modal-content soft-card">

        <form method="post" id="loginForm" action="login.php" data-loading="1">

          <?= csrf_field(); ?><input type="hidden" name="action" value="login">

          <div class="modal-header">

            <h5 class="modal-title"><i class="bi bi-person-lock me-1"></i>Login Pengguna</h5>

            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

          </div>

          <div class="modal-body p-0">

            <div class="row g-0">

              <div class="col-lg-5 d-none d-lg-flex login-aside flex-column justify-content-between">

                <div>

                  <div class="login-badge mb-3"><i class="bi bi-shield-check"></i></div>

                  <h6 class="fw-semibold mb-2">Keamanan data institusi terjaga.</h6>

                  <p class="text-white-50 small mb-0">Gunakan kredensial resmi SIKAT untuk mengakses pelaporan, reviu, dan monitoring tindak lanjut secara terpadu.</p>

                </div>

                <div class="text-white-50 small pt-3"> <?= date('Y') ?> SIKAT</div>

              </div>

              <div class="col-lg-7 p-4 p-lg-5">

                <?php $loginErr = (string)($_SESSION['err_login'] ?? ''); $loginLocked = !empty($_SESSION['err_login_locked']); $lockRemaining = (int)($_SESSION['lock_remaining'] ?? 0); unset($_SESSION['err_login'], $_SESSION['err_login_locked'], $_SESSION['lock_remaining']); ?>

                <div id="err_login" class="alert alert-danger shadow-sm mb-3" style="<?= $loginErr ? '' : 'display:none;' ?>"><?= e($loginErr) ?></div>

                <div id="lock_countdown" class="text-danger fw-bold mt-2" style="<?= ($loginLocked && $lockRemaining > 0) ? '' : 'display:none;' ?>"><?= ($loginLocked && $lockRemaining > 0) ? 'Coba lagi dalam ' . gmdate('i:s', $lockRemaining) : '' ?></div>

                <div id="loginLockedAction" class="mt-2" style="<?= $loginLocked ? '' : 'display:none;' ?>">

                  <?php if ($ADMIN_LINK): ?>

                    <a class="btn btn-sm btn-danger" href="<?= e($ADMIN_LINK) ?>" target="_blank" rel="noopener">Hubungi Admin</a>

                  <?php else: ?>

                    <div class="small text-muted">Hubungi admin untuk bantuan login.</div>

                  <?php endif; ?>

                </div>

                <div class="form-floating mb-3">

                  <input name="username" id="loginUsername" class="form-control" placeholder="Username" required>

                  <label for="loginUsername"><i class="bi bi-person me-1"></i>Username</label>

                </div>

                <div class="form-floating mb-3">

                  <input name="password" id="loginPassword" type="password" class="form-control" placeholder="Password" required>

                  <label for="loginPassword"><i class="bi bi-key me-1"></i>Password</label>

                </div>

                <div class="text-muted small mb-2">Hubungi administrator SKI apabila memerlukan reset akses.</div>

                <div class="small mb-4">

                  <?php if ($ADMIN_LINK): ?>

                    <a href="<?= e($ADMIN_LINK) ?>" target="_blank" rel="noopener">Hubungi Admin</a>

                  <?php else: ?>

                    <span class="text-muted">Hubungi admin untuk bantuan login.</span>

                  <?php endif; ?>

                </div>

                <div class="d-flex flex-wrap gap-2">

                  <button class="btn btn-primary btn-glow flex-grow-1 btn-loading" data-loading="1" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk Sekarang</button>

                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>

                </div>

              </div>

            </div>

          </div>

        </form>

      </div>

    </div>

  </div>



  <!-- JS -->

  <script>window.__IS_AUTH__ = <?php echo (!empty($_SESSION['auth']) || !empty($_SESSION['user'])) ? 'true' : 'false'; ?>;</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"

          integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"

          crossorigin="anonymous"></script>

  <script defer src="assets/js/password_toggle.js"></script>

  <script>

    if (typeof window.bootstrap === 'undefined') {

      var s = document.createElement('script'); s.src = 'assets/bootstrap.bundle.min.js'; document.body.appendChild(s);

    }

    document.addEventListener('DOMContentLoaded', function () {

      var modalEl = document.getElementById('loginModal');

      var errBoxInit = document.getElementById('err_login');

      var isAuth = !!window.__IS_AUTH__;

      if (modalEl && window.bootstrap) {

        modalEl.addEventListener('shown.bs.modal', function () {

          if (!isAuth && window.location.hash && window.location.hash !== '#loginModal') {

            history.replaceState(null, document.title, window.location.pathname + window.location.search);

          }

        });

      }

      if (isAuth) {

        if (window.location.hash === '#loginModal') {

          history.replaceState(null, document.title, window.location.pathname + window.location.search);

        }

        if (modalEl && window.bootstrap) {

          bootstrap.Modal.getOrCreateInstance(modalEl).hide();

        }

      } else {

        if (modalEl && (window.location.hash === '#loginModal' || (errBoxInit && errBoxInit.textContent.trim() !== ''))) {

          if (window.bootstrap) { bootstrap.Modal.getOrCreateInstance(modalEl).show(); }

        }

      }



      if (window.bootstrap) {

        document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {

          try { new bootstrap.Dropdown(el); } catch(e) {}

        });

      }

      document.querySelectorAll('.caption-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var caption = btn.closest('.public-media-caption');
          if (!caption) return;
          var expanded = caption.classList.toggle('is-expanded');
          btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          btn.innerHTML = expanded
            ? 'Tutup <i class="bi bi-chevron-up"></i>'
            : 'Baca selengkapnya <i class="bi bi-chevron-down"></i>';
        });
      });

      document.querySelectorAll('.media-frame img').forEach(function (img) {
        var setOrientation = function () {
          var frame = img.closest('.media-frame');
          if (!frame || !img.naturalWidth || !img.naturalHeight) return;
          frame.classList.remove('media-landscape', 'media-portrait');
          frame.classList.add(img.naturalHeight > img.naturalWidth ? 'media-portrait' : 'media-landscape');
        };
        if (img.complete) {
          setOrientation();
        } else {
          img.addEventListener('load', setOrientation, { once: true });
        }
      });

      var publicCarouselEl = document.getElementById('publicMediaCarousel');
      if (publicCarouselEl && window.bootstrap) {
        var publicCarousel = bootstrap.Carousel.getOrCreateInstance(publicCarouselEl);
        var carouselVideos = publicCarouselEl.querySelectorAll('video');
        var changingSlides = false;

        function activeSlideAllowsAuto() {
          var activeSlide = publicCarouselEl.querySelector('.carousel-item.active');
          if (!activeSlide) return false;
          return activeSlide.getAttribute('data-bs-interval') !== 'false';
        }

        function activeSlideHasPlayingVideo() {
          var activeSlide = publicCarouselEl.querySelector('.carousel-item.active');
          if (!activeSlide) return false;
          return Array.prototype.some.call(activeSlide.querySelectorAll('video'), function (video) {
            return !video.paused && !video.ended;
          });
        }

        function resumeCarouselIfAllowed() {
          if (activeSlideAllowsAuto() && !activeSlideHasPlayingVideo()) {
            publicCarousel.cycle();
          } else {
            publicCarousel.pause();
          }
        }

        carouselVideos.forEach(function (video) {
          video.addEventListener('play', function () {
            publicCarousel.pause();
          });
          video.addEventListener('pause', function () {
            if (!changingSlides) resumeCarouselIfAllowed();
          });
          video.addEventListener('ended', function () {
            if (!changingSlides) resumeCarouselIfAllowed();
          });
        });

        publicCarouselEl.addEventListener('slide.bs.carousel', function () {
          changingSlides = true;
          carouselVideos.forEach(function (video) {
            if (!video.paused && !video.ended) video.pause();
          });
        });
        publicCarouselEl.addEventListener('slid.bs.carousel', function () {
          changingSlides = false;
          resumeCarouselIfAllowed();
        });
        resumeCarouselIfAllowed();
      }

      var contactPanel = document.getElementById('kontak');
      var contactToggle = document.querySelector('.contact-toggle[data-target="kontak"]');

      function setContactOpen(open, shouldScroll) {
        if (!contactPanel || !contactToggle) return;
        contactPanel.classList.toggle('is-open', open);
        contactPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        contactToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        contactToggle.innerHTML = open
          ? 'Tutup Kontak <i class="bi bi-chevron-down ms-1"></i>'
          : 'Lihat Kontak <i class="bi bi-chevron-down ms-1"></i>';
        if (open && shouldScroll) {
          contactPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      if (contactToggle && contactPanel) {
        contactToggle.addEventListener('click', function (event) {
          event.preventDefault();
          var open = !contactPanel.classList.contains('is-open');
          setContactOpen(open, open);
          if (open) {
            history.replaceState(null, document.title, window.location.pathname + window.location.search + '#kontak');
          } else {
            history.replaceState(null, document.title, window.location.pathname + window.location.search);
          }
        });
        if (window.location.hash === '#kontak') {
          setContactOpen(true, true);
        }
      }

      var publicModalMap = {
        '#pelaporan': 'pelaporanModal',
        '#lacak-pengaduan': 'lacakModal',
        '#saran-kritik': 'saranModal'
      };

      function showPublicModal(modalId, hash) {
        var modalNode = document.getElementById(modalId);
        if (!modalNode || !window.bootstrap) return;
        document.querySelectorAll('.modal.show').forEach(function (openModal) {
          if (openModal.id !== modalId) {
            bootstrap.Modal.getOrCreateInstance(openModal).hide();
          }
        });
        bootstrap.Modal.getOrCreateInstance(modalNode).show();
        if (hash) {
          history.replaceState(null, document.title, window.location.pathname + window.location.search + hash);
        }
      }

      document.querySelectorAll('[data-modal-target]').forEach(function (link) {
        link.addEventListener('click', function (event) {
          var target = link.getAttribute('data-modal-target');
          var hash = link.getAttribute('href') || '';
          if (!target) return;
          event.preventDefault();
          showPublicModal(target, hash.charAt(0) === '#' ? hash : '');
        });
      });

      Object.keys(publicModalMap).forEach(function (hash) {
        var modalNode = document.getElementById(publicModalMap[hash]);
        if (!modalNode) return;
        modalNode.addEventListener('hidden.bs.modal', function () {
          if (window.location.hash === hash) {
            history.replaceState(null, document.title, window.location.pathname + window.location.search);
          }
        });
      });

      if (publicModalMap[window.location.hash]) {
        showPublicModal(publicModalMap[window.location.hash], window.location.hash);
      }



      var loginForm = document.getElementById('loginForm') || document.querySelector('#loginModal form');

      var lockTimer = null;

      var lockEndAt = 0;

      function formatLockTime(sec){

        sec = Math.max(0, parseInt(sec || 0, 10));

        var m = Math.floor(sec / 60);

        var s = sec % 60;

        return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');

      }

      function startLockCountdown(seconds){

        seconds = Math.max(0, parseInt(seconds || 0, 10));

        var countdownEl = document.getElementById('lock_countdown');

        var errBox = document.getElementById('err_login');

        var lockBox = document.getElementById('loginLockedAction');

        var submitBtn = loginForm ? loginForm.querySelector('button[type="submit"]') : null;

        var now = Date.now();

        var newEnd = now + (seconds * 1000);

        if (lockEndAt && newEnd <= lockEndAt) {

          // keep existing countdown

        } else {

          lockEndAt = newEnd;

        }

        if (lockTimer) { clearInterval(lockTimer); lockTimer = null; }

        if (seconds <= 0) {

          if (countdownEl) { countdownEl.style.display = 'none'; countdownEl.textContent = ''; }

          if (submitBtn) { submitBtn.disabled = false; }

          return;

        }

        if (countdownEl) {

          countdownEl.style.display = 'block';

        }

        if (lockBox) { lockBox.style.display = 'block'; }

        if (errBox) { errBox.style.display = 'block'; }

        if (submitBtn) { submitBtn.disabled = true; }

        lockTimer = setInterval(function(){

          var remainingMs = Math.max(0, lockEndAt - Date.now());

          var remaining = Math.ceil(remainingMs / 1000);

          if (remaining <= 0) {

            clearInterval(lockTimer); lockTimer = null; lockEndAt = 0;

            if (countdownEl) { countdownEl.style.display = 'none'; countdownEl.textContent = ''; }

            if (submitBtn) { submitBtn.disabled = false; }

          } else if (countdownEl) {

            countdownEl.textContent = 'Coba lagi dalam ' + formatLockTime(remaining);

          }

        }, 1000);

        // initial render

        if (countdownEl) { countdownEl.textContent = 'Coba lagi dalam ' + formatLockTime(seconds); }

      }



      if (loginForm && window.__LOCK_REMAINING__ && parseInt(window.__LOCK_REMAINING__, 10) > 0) {

        startLockCountdown(parseInt(window.__LOCK_REMAINING__, 10));

      }



      if (loginForm && window.fetch && loginForm.dataset.ajax === '1') {

        loginForm.addEventListener('submit', function (e) {

          e.preventDefault();

          var submitBtn = loginForm.querySelector('button[type="submit"]');

          var prevText = submitBtn ? submitBtn.innerHTML : '';

          if (submitBtn) {

            submitBtn.disabled = true;

            submitBtn.innerHTML = 'Memproses...';

          }

          var errBox = document.getElementById('err_login');

          var errText = null;

          var lockBox = document.getElementById('loginLockedAction');

          if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

          if (lockBox) { lockBox.style.display = 'none'; }

          var countdownEl = document.getElementById('lock_countdown');

          if (countdownEl && lockEndAt === 0) { countdownEl.style.display = 'none'; countdownEl.textContent = ''; }

          var fd = new FormData(loginForm);

          fetch(loginForm.action || window.location.href, {

            method: 'POST',

            body: fd,

            headers: {

              'X-Requested-With': 'XMLHttpRequest',

              'Accept': 'application/json'

            },

            credentials: 'same-origin'

          })

          .then(function (res) {

            return res.json().catch(function () { return null; }).then(function (data) {

              return { res: res, data: data };

            });

          })

          .then(function (payload) {

            if (!payload || !payload.data) {

              loginForm.dataset.noAjax = '1';

              loginForm.submit();

              return;

            }

            if (payload.data.ok) {

              window.location = payload.data.redirect || window.location.href;

              return;

            }

            var msg = payload.data.message || 'Login gagal. Silakan coba lagi.';

            if (errBox) { errBox.textContent = msg; errBox.style.display = 'block'; }

            if (lockBox) { lockBox.style.display = payload.data.locked ? 'block' : 'none'; }

            if (payload.data.locked && payload.data.remaining) {

              startLockCountdown(payload.data.remaining);

            } else {

              var countdownEl = document.getElementById('lock_countdown');

              if (countdownEl) { countdownEl.style.display = 'none'; countdownEl.textContent = ''; }

            }

            var modalEl = document.getElementById('loginModal');

            if (window.bootstrap && modalEl) {

              bootstrap.Modal.getOrCreateInstance(modalEl).show();

            }

            var focusEl = document.getElementById('loginPassword') || document.getElementById('loginUsername');

            if (focusEl) { focusEl.focus(); }

          })

          .catch(function () {

            var msg = 'Login gagal. Silakan coba lagi.';

            if (errBox) { errBox.textContent = msg; errBox.style.display = 'block'; }

            if (lockBox) { lockBox.style.display = 'none'; }

            var countdownEl = document.getElementById('lock_countdown');

            if (countdownEl) { countdownEl.style.display = 'none'; countdownEl.textContent = ''; }

            if (lockTimer) { clearInterval(lockTimer); lockTimer = null; }

            var modalEl = document.getElementById('loginModal');

            if (window.bootstrap && modalEl) {

              bootstrap.Modal.getOrCreateInstance(modalEl).show();

            }

          })

          .finally(function () {

            if (submitBtn) {

              submitBtn.disabled = false;

              submitBtn.innerHTML = prevText || submitBtn.innerHTML;

            }

          });

        });

      }

    });

  </script>

  <script>window.__LOCK_REMAINING__ = <?php echo isset($lockRemaining) ? (int)$lockRemaining : 0; ?>;</script>

  <?php if (isset($_GET['open']) && $_GET['open']==='login'){ echo "<script>document.addEventListener('DOMContentLoaded',function(){ new bootstrap.Modal(document.getElementById('loginModal')).show();});</script>"; } ?>

<script>
/* SIKAT_MANUAL_MEDIA_CENTER_DOTS_LIGHTBOX_JS */
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function classifyRatio(w, h) {
    var ratio = (w && h) ? (w / h) : 1.3;
    if (ratio < 0.85) return 'is-portrait';
    if (ratio > 1.2) return 'is-landscape';
    return 'is-square';
  }

  ready(function () {
    var carouselEl = document.getElementById('publicMediaCarousel');
    if (!carouselEl) return;

    var items = Array.prototype.slice.call(carouselEl.querySelectorAll('.carousel-item'));

    items.forEach(function (item) {
      var frame = item.querySelector('.media-frame');
      var img = item.querySelector('.media-frame img');
      var video = item.querySelector('.media-frame video');
      var media = img || video;

      if (!frame || !media) return;

      frame.style.display = 'flex';
      frame.style.alignItems = 'center';
      frame.style.justifyContent = 'center';

      if (!media.closest('.media-preview-trigger')) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'media-preview-trigger';
        btn.setAttribute('aria-label', 'Buka media');

        media.parentNode.insertBefore(btn, media);
        btn.appendChild(media);

        if (video) {
          video.controls = false;
          video.muted = true;
          video.pause();

          var play = document.createElement('div');
          play.className = 'sikat-video-preview-play';
          play.innerHTML = '<span>▶</span>';
          btn.appendChild(play);
        }
      }

      function applyMediaCenter() {
        var w = img ? img.naturalWidth : video.videoWidth;
        var h = img ? img.naturalHeight : video.videoHeight;
        frame.classList.remove('media-portrait', 'media-landscape', 'media-square');

        if (w && h && h > w) {
          frame.classList.add('media-portrait');
          media.style.width = 'auto';
          media.style.height = '100%';
        } else if (w && h && w > h) {
          frame.classList.add('media-landscape');
          media.style.width = '100%';
          media.style.height = '100%';
        } else {
          frame.classList.add('media-square');
          media.style.width = 'auto';
          media.style.height = '100%';
        }
      }

      if (img) {
        if (img.complete) applyMediaCenter();
        img.addEventListener('load', applyMediaCenter);
      }

      if (video) {
        video.addEventListener('loadedmetadata', applyMediaCenter);
      }
    });

    if (items.length > 1 && !document.querySelector('.sikat-media-dots')) {
      var dots = document.createElement('div');
      dots.className = 'sikat-media-dots';

      items.forEach(function (item, idx) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'sikat-media-dot' + (item.classList.contains('active') ? ' active' : '');
        dot.setAttribute('aria-label', 'Media ' + (idx + 1));
        dot.addEventListener('click', function () {
          if (typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
            bootstrap.Carousel.getOrCreateInstance(carouselEl).to(idx);
          }
        });
        dots.appendChild(dot);
      });

      carouselEl.appendChild(dots);

      carouselEl.addEventListener('slid.bs.carousel', function (ev) {
        Array.prototype.slice.call(dots.children).forEach(function (dot, i) {
          dot.classList.toggle('active', i === ev.to);
        });
      });
    }

    if (!document.getElementById('sikatMediaLightbox')) {
      var lb = document.createElement('div');
      lb.id = 'sikatMediaLightbox';
      lb.className = 'sikat-media-lightbox';
      lb.innerHTML =
        '<div class="sikat-media-lightbox-dialog is-landscape" role="dialog" aria-modal="true">' +
          '<div class="sikat-media-lightbox-header">' +
            '<h3 class="sikat-media-lightbox-title"></h3>' +
            '<button type="button" class="sikat-media-lightbox-close" aria-label="Tutup">×</button>' +
          '</div>' +
          '<div class="sikat-media-lightbox-body"></div>' +
          '<div class="sikat-media-lightbox-caption"></div>' +
        '</div>';
      document.body.appendChild(lb);
    }

    var lightbox = document.getElementById('sikatMediaLightbox');
    var dialog = lightbox.querySelector('.sikat-media-lightbox-dialog');
    var titleEl = lightbox.querySelector('.sikat-media-lightbox-title');
    var bodyEl = lightbox.querySelector('.sikat-media-lightbox-body');
    var captionEl = lightbox.querySelector('.sikat-media-lightbox-caption');
    var closeBtn = lightbox.querySelector('.sikat-media-lightbox-close');

    function clearLightbox() {
      var oldVideo = bodyEl.querySelector('video');
      if (oldVideo) {
        oldVideo.pause();
        oldVideo.removeAttribute('src');
        oldVideo.load();
      }
      bodyEl.innerHTML = '';
      dialog.classList.remove('is-portrait', 'is-landscape', 'is-square');
    }

    function openLightboxFromMedia(media) {
      clearLightbox();

      var item = media.closest('.carousel-item');
      var title = '';
      var caption = '';

      if (item) {
        var titleNode = item.querySelector('.public-media-caption h2');
        var captionNode = item.querySelector('.caption-full') || item.querySelector('.caption-short') || item.querySelector('.public-media-caption p');
        title = titleNode ? titleNode.textContent.trim() : '';
        caption = captionNode ? captionNode.textContent.trim() : '';
      }

      titleEl.textContent = title || 'Media Edukasi';
      captionEl.textContent = caption || '';

      if (media.tagName.toLowerCase() === 'video') {
        var source = media.querySelector('source');
        var src = source ? source.getAttribute('src') : media.getAttribute('src');
        var poster = media.getAttribute('poster') || '';

        var v = document.createElement('video');
        v.controls = true;
        v.preload = 'metadata';
        if (poster) v.poster = poster;

        var s = document.createElement('source');
        s.src = src;
        v.appendChild(s);

        v.addEventListener('loadedmetadata', function () {
          dialog.classList.add(classifyRatio(v.videoWidth, v.videoHeight));
        });

        bodyEl.appendChild(v);
      } else {
        var img = document.createElement('img');
        img.src = media.currentSrc || media.src;
        img.alt = media.alt || title || 'Media Edukasi';
        img.addEventListener('load', function () {
          dialog.classList.add(classifyRatio(img.naturalWidth, img.naturalHeight));
        });
        bodyEl.appendChild(img);
      }

      lightbox.classList.add('is-open');
      document.body.classList.add('sikat-lightbox-open');
    }

    function closeLightbox() {
      lightbox.classList.remove('is-open');
      document.body.classList.remove('sikat-lightbox-open');
      clearLightbox();
    }

    carouselEl.addEventListener('click', function (ev) {
      var trigger = ev.target.closest('.media-preview-trigger');
      if (!trigger) return;

      ev.preventDefault();

      var media = trigger.querySelector('img,video');
      if (media) openLightboxFromMedia(media);
    });

    closeBtn.addEventListener('click', closeLightbox);

    lightbox.addEventListener('click', function (ev) {
      if (ev.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && lightbox.classList.contains('is-open')) {
        closeLightbox();
      }
    });
  });
})();
</script>
<style>
/* SIKAT_FINAL_FIX_CAROUSEL_SYNC_DOTS_20260625 */
.public-media {
  display: block !important;
  position: relative !important;
}

.public-media .carousel {
  width: 100% !important;
  position: relative !important;
}

.public-media .carousel-inner {
  width: 100% !important;
  overflow: hidden !important;
}

.public-media .carousel-item {
  width: 100% !important;
  display: none !important;
}

.public-media .carousel-item.active,
.public-media .carousel-item-next,
.public-media .carousel-item-prev {
  display: block !important;
}

.public-media .media-frame {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
  overflow: hidden !important;
}

.public-media .media-preview-trigger {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
  height: 100% !important;
  margin: 0 auto !important;
  padding: 0 !important;
  border: 0 !important;
  background: transparent !important;
  cursor: zoom-in !important;
}

.public-media .media-frame img,
.public-media .media-frame video,
.public-media .media-preview-trigger img,
.public-media .media-preview-trigger video {
  display: block !important;
  object-fit: contain !important;
  margin-left: auto !important;
  margin-right: auto !important;
}

.public-media .media-frame.media-portrait img,
.public-media .media-frame.media-portrait video,
.public-media .media-frame.media-portrait .media-preview-trigger img,
.public-media .media-frame.media-portrait .media-preview-trigger video {
  width: auto !important;
  height: 100% !important;
  max-width: 100% !important;
}

.public-media .media-frame.media-landscape img,
.public-media .media-frame.media-landscape video,
.public-media .media-frame.media-landscape .media-preview-trigger img,
.public-media .media-frame.media-landscape .media-preview-trigger video {
  width: 100% !important;
  height: 100% !important;
}

/* Matikan indikator custom lama agar tidak mengganggu carousel */
.public-media .sikat-media-dots {
  display: none !important;
}

/* Gunakan indikator Bootstrap bawaan, tetapi dibuat modern */
.public-media .carousel-indicators {
  position: absolute !important;
  top: 12px !important;
  bottom: auto !important;
  left: 50% !important;
  right: auto !important;
  transform: translateX(-50%) !important;
  z-index: 30 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 9px !important;
  margin: 0 !important;
  padding: 7px 10px !important;
  width: auto !important;
  border-radius: 999px !important;
  background: rgba(255, 255, 255, .78) !important;
  backdrop-filter: blur(7px);
  box-shadow: 0 8px 24px rgba(0, 60, 34, .16);
}

.public-media .carousel-indicators [data-bs-target] {
  box-sizing: border-box !important;
  width: 12px !important;
  height: 12px !important;
  min-width: 12px !important;
  min-height: 12px !important;
  border-radius: 999px !important;
  border: 2px solid rgba(0, 105, 63, .55) !important;
  background: rgba(230, 247, 238, .95) !important;
  opacity: 1 !important;
  padding: 0 !important;
  margin: 0 !important;
  text-indent: -999px !important;
  overflow: hidden !important;
  transition: all .25s ease !important;
}

.public-media .carousel-indicators .active {
  width: 32px !important;
  min-width: 32px !important;
  background: linear-gradient(135deg, #006b3f, #0da85d) !important;
  border-color: #d8a31a !important;
  box-shadow: 0 0 0 3px rgba(216,163,26,.24), 0 8px 18px rgba(0,90,50,.24) !important;
}

.public-media-caption {
  width: 100% !important;
}
</style>

<script>
/* SIKAT_FINAL_FIX_CAROUSEL_SYNC_DOTS_JS_20260625 */
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var carouselEl = document.getElementById('publicMediaCarousel');
    if (!carouselEl) return;

    /* Hapus indikator custom lama supaya tidak mengacaukan sync slide */
    carouselEl.querySelectorAll('.sikat-media-dots').forEach(function (el) {
      el.remove();
    });

    var items = Array.prototype.slice.call(carouselEl.querySelectorAll('.carousel-inner .carousel-item'));
    if (!items.length) return;

    /* Pastikan indikator Bootstrap bawaan ada dan jumlahnya sama dengan jumlah media */
    var indicators = carouselEl.querySelector('.carousel-indicators');
    if (items.length > 1) {
      if (!indicators) {
        indicators = document.createElement('div');
        indicators.className = 'carousel-indicators';
        carouselEl.insertBefore(indicators, carouselEl.firstChild);
      }

      indicators.innerHTML = '';

      items.forEach(function (item, idx) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('data-bs-target', '#publicMediaCarousel');
        btn.setAttribute('data-bs-slide-to', String(idx));
        btn.setAttribute('aria-label', 'Slide ' + (idx + 1));

        if (idx === 0 || item.classList.contains('active')) {
          btn.className = 'active';
          btn.setAttribute('aria-current', 'true');
        }

        indicators.appendChild(btn);
      });
    } else if (indicators) {
      indicators.remove();
    }

    /* Paksa hanya satu slide aktif */
    var activeItems = items.filter(function (item) {
      return item.classList.contains('active');
    });

    if (activeItems.length === 0) {
      items[0].classList.add('active');
    } else if (activeItems.length > 1) {
      activeItems.slice(1).forEach(function (item) {
        item.classList.remove('active');
      });
    }

    /* Sync indikator setelah slide berubah */
    carouselEl.addEventListener('slide.bs.carousel', function (ev) {
      if (!indicators) return;

      Array.prototype.slice.call(indicators.children).forEach(function (dot, i) {
        var active = i === ev.to;
        dot.classList.toggle('active', active);
        if (active) {
          dot.setAttribute('aria-current', 'true');
        } else {
          dot.removeAttribute('aria-current');
        }
      });
    });

    /* Center ulang media portrait/landscape */
    function centerMedia(frame, media) {
      if (!frame || !media) return;

      var w = media.tagName.toLowerCase() === 'img' ? media.naturalWidth : media.videoWidth;
      var h = media.tagName.toLowerCase() === 'img' ? media.naturalHeight : media.videoHeight;

      frame.classList.remove('media-portrait', 'media-landscape', 'media-square');

      if (w && h && h > w) {
        frame.classList.add('media-portrait');
        media.style.width = 'auto';
        media.style.height = '100%';
      } else if (w && h && w > h) {
        frame.classList.add('media-landscape');
        media.style.width = '100%';
        media.style.height = '100%';
      } else {
        frame.classList.add('media-square');
        media.style.width = 'auto';
        media.style.height = '100%';
      }
    }

    items.forEach(function (item) {
      var frame = item.querySelector('.media-frame');
      var media = item.querySelector('.media-frame img, .media-frame video');

      if (!frame || !media) return;

      if (media.tagName.toLowerCase() === 'img') {
        if (media.complete) centerMedia(frame, media);
        media.addEventListener('load', function () {
          centerMedia(frame, media);
        });
      } else {
        media.addEventListener('loadedmetadata', function () {
          centerMedia(frame, media);
        });
      }
    });

    /* Re-init Bootstrap Carousel secara ringan agar indikator bawaan sinkron */
    if (typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
      var instance = bootstrap.Carousel.getInstance(carouselEl);
      if (instance) {
        instance.dispose();
      }

      bootstrap.Carousel.getOrCreateInstance(carouselEl, {
        ride: 'carousel',
        wrap: true,
        touch: true
      });
    }
  });
})();
</script>
</body>

</html>
