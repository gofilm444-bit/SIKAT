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

  if(!$stmt){ flash('err_report','Query error: '.e($conn->error)); header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit; }

  $stmt->bind_param("ssssiss",$kode,$judul,$kategori,$isi,$anonim,$status,$tanggal);

  if(!$stmt->execute()){ flash('err_report','Gagal menyimpan: '.e($conn->error)); header('Location: '.$_SERVER['PHP_SELF'].'#pelaporan'); exit; }



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

// Aman tanpa mime_content_type()
$type = $_FILES['lampiran']['type'][$i] ?? '';

if ($size <= 0 || $size > $MAX_SIZE) { continue; }

if (!isset($ALLOWED_MIMES[$type])) { continue; }

$ext = $ALLOWED_MIMES[$type];

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

  $kode=trim($_POST['kode']??''); if($kode===''){ flash('err_track','Masukkan kode tracking.'); header('Location: '.$_SERVER['PHP_SELF'].'#lacak'); exit; }

  header('Location: '.$_SERVER['PHP_SELF'].'?action=lihat&kode='.urlencode($kode).'#lacak'); exit;

}



// Feedback

if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='feedback')) {

  require_post_with_csrf();

  $nama=trim($_POST['nama_fb']??''); $email=trim($_POST['email_fb']??''); $text=trim($_POST['isi_fb']??'');

  if($text===''){ flash('err_fb','Isi saran/kritik wajib diisi.'); header('Location: '.$_SERVER['PHP_SELF'].'#saran'); exit; }

  $stmt=$conn->prepare("INSERT INTO feedback (nama,email,isi,created_at) VALUES (?,?,?,NOW())");

  if(!$stmt){ flash('err_fb','Query error: '.e($conn->error)); header('Location: '.$_SERVER['PHP_SELF'].'#saran'); exit; }

  $stmt->bind_param("sss",$nama,$email,$text);

  $stmt->execute()? flash('ok_fb','Terima kasih! Saran/kritik Anda terekam.') : flash('err_fb','Gagal menyimpan: '.e($conn->error));

  header('Location: '.$_SERVER['PHP_SELF'].'#saran'); exit;

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

    .public-page .app-header{padding-top:.5rem; padding-bottom:.5rem;}

    .app-header .brand-title{font-weight:600; letter-spacing:.2px; font-size:.95rem; line-height:1.3;}

    .login-logo{height:56px !important; width:auto;}
    .public-page .app-header .sikat-logo-wrap{display:inline-flex;align-items:center;line-height:0;padding:0;border:0;background:transparent;}
    .public-page .app-header .login-logo{display:block;height:56px;width:auto;}
    .public-page .app-header .public-logo{filter:drop-shadow(0 0 1px rgba(240,195,0,.9)) drop-shadow(0 0 4px rgba(255,255,255,.9));-webkit-filter:drop-shadow(0 0 1px rgba(240,195,0,.9)) drop-shadow(0 0 4px rgba(255,255,255,.9));}

    .public-summary{background:#f7fbf8;}

    .public-focus{border-color:#bfe3cc; box-shadow:0 8px 24px rgba(16,122,61,.08);}

    .soft-card{background:#fff;border:1px solid var(--border-soft);border-radius:14px;box-shadow:0 6px 18px rgba(16,122,61,.06);}

    .btn-primary{ background:var(--brand-green); border-color:var(--brand-green-dark); }

    .btn-primary:hover{ background:var(--brand-green-dark); border-color:var(--brand-green-dark); }

    .menu-row{display:flex;flex-wrap:wrap;gap:10px;}

    .menu-tile{display:flex;align-items:center;gap:.6rem;background:#f1f6f2;border:1px solid #e2eee7;color:#1b5a40;border-radius:10px;padding:8px 12px;text-decoration:none;white-space:nowrap;flex:0 0 auto;min-width:210px;font-weight:600;font-size:.95rem;}

    .menu-tile i{margin-right:.2rem;opacity:.85;}

    @media (min-width: 992px){ .menu-row{flex-wrap:nowrap; overflow-x:auto;} }

    .dropdown-menu{ z-index: 2000; }

    .file-hint{font-size:.875rem;color:#6b7280}

  </style>

  <link rel="stylesheet" href="assets/css/password_toggle.css">

  <?php include __DIR__ . '/includes/head_favicon.php'; ?>

</head>

<body class="public-page">

  <!-- Header -->

  <header class="app-header py-3">

    <div class="container d-flex align-items-center justify-content-between">

      <div class="d-flex align-items-center gap-3">

        <span class="sikat-logo-wrap">
          <img src="/ski_new/asset/logo-sikat-baru-140.png" alt="SIKAT" class="login-logo public-logo">
        </span>

        <div class="brand-title">Sistem Informasi Kepatuhan Internal Poltekkes Ternate (SIKAT)</div>
        <span class="sikat-version-badge">SIKAT v2.0</span>

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



    <!-- Menu Umum -->

    <div class="soft-card p-4 mb-4 public-summary">

      <h2 class="h5 text-success mb-3">Menu Umum</h2>

      <div class="menu-row">

        <a class="menu-tile" href="#pelaporan"><i class="bi bi-flag"></i>Pelaporan</a>

        <a class="menu-tile" href="#lacak"><i class="bi bi-search"></i>Lacak Pengaduan</a>

        <a class="menu-tile" href="#saran"><i class="bi bi-chat-dots"></i>Saran & Kritik</a>

        <a class="menu-tile" href="kebijakan.php"><i class="bi bi-journal-text"></i>Data Kebijakan</a>

        <a class="menu-tile" href="review.php"><i class="bi bi-clipboard2-data"></i>E-Reviu</a>

        <a class="menu-tile" href="risiko.php"><i class="bi bi-shield-check"></i>Manajemen Risiko</a>

        <a class="menu-tile" href="self_assessment.php"><i class="bi bi-check2-square"></i>Self-Assessment</a>

        <a class="menu-tile" href="kontak.php"><i class="bi bi-telephone"></i>Kontak</a>

      </div>



      <div class="d-flex flex-wrap gap-3 mt-4">

        <div class="soft-card px-4 py-3"><div class="small text-muted">Total Pengaduan</div><div class="h4 mb-0"><?= (int)$stat['total'] ?></div></div>

        <div class="soft-card px-4 py-3"><div class="small text-muted">Pengaduan Masuk</div><div class="h4 mb-0"><?= (int)$stat['masuk'] ?></div></div>

        <div class="soft-card px-4 py-3"><div class="small text-muted">Tahap Berjalan</div><div class="h4 mb-0"><?= (int)$stat['proses'] ?></div></div>

        <div class="soft-card px-4 py-3"><div class="small text-muted">Arsip</div><div class="h4 mb-0"><?= (int)$stat['arsip'] ?></div></div>

      </div>

      <div class="small text-muted mt-2">Pengaduan yang dikembalikan ke pelapor: <?= (int)$stat['kembali'] ?> laporan.</div>

    </div>



    <!-- Pelaporan -->

    <section id="pelaporan" class="soft-card p-4 mb-4 public-focus">

      <h3 class="h6 text-success mb-3"><i class="bi bi-flag me-1"></i>Form Pelaporan</h3>

      <?php

        $flash_messages = [];

        if ($m = flash('ok_report')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }

        if ($m = flash('err_report')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }

        include __DIR__ . '/includes/flash.php';

      ?>



      <form method="post" enctype="multipart/form-data">

        <?= csrf_field(); ?><input type="hidden" name="action" value="lapor">

        <div class="row g-3">

          <div class="col-md-6"><label class="form-label">Nama</label><input name="nama" class="form-control" placeholder="Boleh kosong jika anonim"></div>

          <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" placeholder="Boleh kosong jika anonim"></div>

          <div class="col-md-6">

            <label class="form-label">Kategori</label>

            <select name="kategori" class="form-select" required>

              <option value="">-- Pilih Kategori --</option>

              <option>Gratifikasi</option><option>Korupsi</option><option>Kotak Saran</option><option>Lainnya</option>

            </select>

          </div>

          <div class="col-12"><label class="form-label">Isi Laporan</label><textarea name="isi" class="form-control" rows="4" required></textarea></div>

          <div class="col-12 form-check"><input class="form-check-input" type="checkbox" id="anon" name="anonim"><label class="form-check-label" for="anon">Kirim sebagai anonim</label></div>



          <div class="col-12">

            <label class="form-label">Lampiran Bukti (opsional)</label>

            <input class="form-control" 
            type="file" 
            name="lampiran[]" 
            multiple 
            accept=".pdf,image/*,video/mp4,video/webm,video/quicktime">

            <div class="file-hint mt-1">Format: PDF/JPG/PNG/GIF/WEBP/MP4/WEBM/MOV, maks 5MB per file. Anda bisa memilih beberapa file sekaligus.</div>

          </div>

        </div>

        <div class="mt-3 d-flex gap-2">

          <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim</button>

          <a href="#lacak" class="btn btn-outline-success"><i class="bi bi-search me-1"></i>Saya sudah punya kode</a>

        </div>

        <div class="form-text mt-2">Simpan <b>kode tracking</b> yang tampil setelah submit.</div>

      </form>

    </section>



    <!-- Lacak -->

    <section id="lacak" class="soft-card p-4 mb-4">

      <h3 class="h6 text-success mb-3"><i class="bi bi-search me-1"></i>Lacak Pengaduan</h3>

      <?php

        $flash_messages = [];

        if ($m = flash('ok_track')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }

        if ($m = flash('err_track')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }

        include __DIR__ . '/includes/flash.php';

      ?>



      <form method="post" class="row g-2 align-items-end">

        <?= csrf_field(); ?><input type="hidden" name="action" value="lacak">

        <div class="col-sm-7 col-md-6"><label class="form-label">Kode Tracking</label><input name="kode" class="form-control" placeholder="cth: SKI-20250930-ABCDE"></div>

        <div class="col-sm-5 col-md-3"><button class="btn btn-primary w-100">Lacak</button></div>

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

    </section>



    <!-- Saran & Kritik -->

    <section id="saran" class="soft-card p-4 mb-4">

      <h3 class="h6 text-success mb-3"><i class="bi bi-chat-dots me-1"></i>Saran & Kritik</h3>

      <?php

        $flash_messages = [];

        if ($m = flash('ok_fb')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }

        if ($m = flash('err_fb')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }

        include __DIR__ . '/includes/flash.php';

      ?>



      <form method="post">

        <?= csrf_field(); ?><input type="hidden" name="action" value="feedback">

        <div class="row g-3">

          <div class="col-md-6"><label class="form-label">Nama</label><input name="nama_fb" class="form-control"></div>

          <div class="col-md-6"><label class="form-label">Email</label><input name="email_fb" type="email" class="form-control"></div>

          <div class="col-12"><label class="form-label">Saran/Kritik</label><textarea name="isi_fb" class="form-control" rows="3" required></textarea></div>

        </div>

        <div class="mt-3"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim</button></div>

      </form>

    </section>

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

</body>

</html>


