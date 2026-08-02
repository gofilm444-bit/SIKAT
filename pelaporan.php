<?php

require_once __DIR__ . '/bootstrap.php';

// Watermark: Ded Polkester



/* ===== Bootstrap koneksi DB ===== */

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

if (!$__found) {

    http_response_code(500);

    die("db.php tidak ditemukan:\n - ".implode("\n - ", $__candidates));

}

if (!isset($conn) || !($conn instanceof mysqli)) {

    http_response_code(500);

    die('Koneksi $conn tidak tersedia.');

}

$conn->set_charset('utf8mb4');



require_once __DIR__.'/pelaporan_helpers.php';

/* ====== AUTHZ PELAPORAN (PER-USER) ====== */
$role = strtolower($_SESSION['user']['peran'] ?? '');
$roleRaw = strtolower($_SESSION['user']['peran_raw'] ?? $role);

$actor = function_exists('pelaporan_actor_group')
  ? pelaporan_actor_group($_SESSION['user'])
  : $role;

// ambil akses_pelaporan per-user
$akses_pelaporan = 0;
$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
  $uid = (int)($_SESSION['user']['user_id'] ?? 0);
}

if ($uid > 0) {
  if ($st = $conn->prepare("SELECT akses_pelaporan FROM pengguna WHERE id=? LIMIT 1")) {
    $st->bind_param("i", $uid);
    if ($st->execute()) {
      $res = $st->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $akses_pelaporan = (int)($row['akses_pelaporan'] ?? 0);
    }
    $st->close();
  }
}

// aturan:
// - admin / auditor selalu boleh
// - auditee hanya boleh jika akses_pelaporan = 1
$can_access_pelaporan =
  in_array($actor, [
    'admin','super_admin','kepala_ski','direktur',
    'auditor','auditor_staff','auditor_ka'
  ], true)
  || ($akses_pelaporan === 1);

if (!$can_access_pelaporan) {
  http_response_code(403);
  die('Akses ditolak');
}
/* ====== /AUTHZ PELAPORAN ====== */


/* ===== Utilitas umum ===== */

if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) { $_SESSION['flash'] = []; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

function csrf_field(): string {

    return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8').'">';

}

function csrf_validate(string $token): void {

    if (!hash_equals($_SESSION['csrf_token'], $token)) {

        http_response_code(400);

        die('Invalid CSRF token');

    }

}

function require_post(): void {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        http_response_code(405);

        die('Method Not Allowed');

    }

    csrf_validate($_POST['csrf'] ?? '');

}

function e($value): string {

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}

function flash(string $key, ?string $value = null) {

    if ($value !== null) {

        $_SESSION['flash'][$key] = $value;

        return;

    }

    if (!array_key_exists($key, $_SESSION['flash'])) {

        return null;

    }

    $msg = $_SESSION['flash'][$key];

    unset($_SESSION['flash'][$key]);

    return $msg;

}



if (!function_exists('is_auditee')) {

    function is_auditee(): bool {

        $role = strtolower($_SESSION['user']['peran'] ?? '');

        return $role === 'auditee' || strpos($role, 'auditee_') === 0;

    }

}



/* ===== Lampiran rekap untuk Direktur ===== */

if (!function_exists('pelaporan_rekap_allowed_mimes')) {

    function pelaporan_rekap_allowed_mimes(): array {

        return [

            'application/pdf' => 'pdf',

            'application/msword' => 'doc',

            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',

            'application/vnd.ms-excel' => 'xls',

            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',

            'image/jpeg' => 'jpg',

            'image/png' => 'png',

        ];

    }

}



if (!function_exists('pelaporan_rekap_accept_attr')) {

    function pelaporan_rekap_accept_attr(): string {

        return '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png';

    }

}



if (!function_exists('pelaporan_rekap_max_size')) {

    function pelaporan_rekap_max_size(): int {

        return 8 * 1024 * 1024; // 8MB

    }

}



if (!function_exists('pelaporan_rekap_storage_slug')) {

    function pelaporan_rekap_storage_slug(string $kode): string {

        $slug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $kode);

        return $slug !== '' ? $slug : 'kode';

    }

}



if (!function_exists('pelaporan_rekap_upload_dir')) {

    function pelaporan_rekap_upload_dir(string $kode): string {

        $base = __DIR__ . '/uploads/rekap';

        if (!is_dir($base)) {

            @mkdir($base, 0755, true);

        }



        $htBase = $base . DIRECTORY_SEPARATOR . '.htaccess';

        if (!is_file($htBase)) {

            @file_put_contents($htBase, "php_flag engine off\nOptions -ExecCGI -Indexes\n<FilesMatch \"\\.(php|php5|phtml)$\">\nDeny from all\n</FilesMatch>\n");

        }



        $slug = pelaporan_rekap_storage_slug($kode);

        $target = $base . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($target)) {

            @mkdir($target, 0755, true);

        }



        $htTarget = $target . DIRECTORY_SEPARATOR . '.htaccess';

        if (!is_file($htTarget)) {

            @file_put_contents($htTarget, "php_flag engine off\nOptions -ExecCGI -Indexes\n<FilesMatch \"\\.(php|php5|phtml)$\">\nDeny from all\n</FilesMatch>\n");

        }



        return $target;

    }

}



if (!function_exists('pelaporan_ensure_tl_schema')) {

    function pelaporan_ensure_tl_schema(mysqli $conn): bool {

        static $checked = false;

        static $result = true;

        if ($checked) { return $result; }

        $checked = true;

        $columns = [

            'tl_status'      => "ADD COLUMN tl_status VARCHAR(20) NOT NULL DEFAULT 'Belum TL' AFTER updated_at",

            'tl_due_date'    => "ADD COLUMN tl_due_date DATE NULL AFTER tl_status",

            'tl_catatan'     => "ADD COLUMN tl_catatan VARCHAR(255) NULL AFTER tl_due_date",

            'tl_updated_at'  => "ADD COLUMN tl_updated_at DATETIME NULL AFTER tl_catatan",

            'tl_updated_by'  => "ADD COLUMN tl_updated_by INT NULL AFTER tl_updated_at",

            'tl_updated_name'=> "ADD COLUMN tl_updated_name VARCHAR(150) NULL AFTER tl_updated_by",

        ];

        foreach ($columns as $col => $alter) {

            $check = $conn->query("SHOW COLUMNS FROM pelaporan LIKE '".$conn->real_escape_string($col)."'");

            $exists = $check && $check->num_rows > 0;

            if ($check) { $check->free(); }

            if ($exists) { continue; }

            if (!$conn->query("ALTER TABLE pelaporan ".$alter)) {

                error_log('pelaporan_ensure_tl_schema failed for '.$col.': '.$conn->error);

                $result = false;

                return false;

            }

        }

        return $result;

    }

}



if (!function_exists('pelaporan_tl_allowed_statuses')) {

    function pelaporan_tl_allowed_statuses(): array {

        return ['Belum TL','Proses','Selesai'];

    }

}



if (!function_exists('pelaporan_tl_badge_class')) {

    function pelaporan_tl_badge_class(string $status): string {

        switch ($status) {

            case 'Selesai': return 'bg-success';

            case 'Proses': return 'bg-warning text-dark';

            case 'Belum TL':

            default: return 'bg-secondary';

        }

    }

}



if (!function_exists('pelaporan_tl_warning_meta')) {

    function pelaporan_tl_warning_meta(?string $dueDate, int $warnThreshold = 15): array {

        if ($dueDate === null || $dueDate === '') {

            return [null,null,null,null];

        }

        try {

            $due = new DateTime($dueDate);

        } catch (Throwable $e) {

            return [null,null,null,null];

        }

        $today = new DateTime('today');

        $diff = (int)$today->diff($due)->format('%r%a');

        if ($diff > $warnThreshold) {

            $desc = $diff === 1 ? 'Jatuh tempo 1 hari lagi' : 'Jatuh tempo '.$diff.' hari lagi';

            return ['Hijau','#16a34a','Aman - '.$desc,$diff];

        }

        if ($diff >= 0 && $diff <= $warnThreshold) {

            $desc = $diff === 0 ? 'Jatuh tempo hari ini' : ($diff === 1 ? 'Jatuh tempo 1 hari lagi' : 'Jatuh tempo '.$diff.' hari lagi');

            return ['Kuning','#f59e0b','Perlu perhatian (<= '.$warnThreshold.' hari) · '.$desc,$diff];

        }

        $over = abs($diff);

        if ($diff >= -5) {

            $desc = $over === 1 ? 'Lewat 1 hari' : 'Lewat '.$over.' hari';

            return ['Merah','#dc2626',$desc,$diff];

        }

        return ['Hitam','#111827','Lewat '.$over.' hari',$diff];

    }

}



pelaporan_ensure_tl_schema($conn);



if (!function_exists('pelaporan_process_admin_attachment')) {

    function pelaporan_process_admin_attachment(mysqli $conn, string $kode): array {

        if (!isset($_FILES['attachment'])) {

            return ['ok' => true, 'file' => null];

        }



        $file = $_FILES['attachment'];

        if (!is_array($file)) {

            return ['ok' => false, 'error' => 'Lampiran tidak valid.'];

        }



        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        $originalName = (string)($file['name'] ?? '');

        if ($error === UPLOAD_ERR_NO_FILE || $originalName === '') {

            return ['ok' => true, 'file' => null]; // opsional

        }

        if ($error !== UPLOAD_ERR_OK) {

            return ['ok' => false, 'error' => 'Gagal mengunggah lampiran (kode: ' . $error . ').'];

        }



        $size = (int)($file['size'] ?? 0);

        if ($size <= 0) {

            return ['ok' => false, 'error' => 'Lampiran kosong atau rusak.'];

        }

        if ($size > pelaporan_rekap_max_size()) {

            $maxMb = number_format(pelaporan_rekap_max_size() / (1024 * 1024), 1);

            return ['ok' => false, 'error' => 'Lampiran melebihi batas ' . $maxMb . ' MB.'];

        }



        $tmp = $file['tmp_name'] ?? '';

        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {

            return ['ok' => false, 'error' => 'Lampiran tidak ditemukan di server.'];

        }



        $mime = mime_content_type($tmp);

        if ($mime === false || $mime === '') {

            $mime = (string)($file['type'] ?? '');

        }

        $allowed = pelaporan_rekap_allowed_mimes();

        if (!isset($allowed[$mime])) {

            return ['ok' => false, 'error' => 'Tipe file lampiran belum didukung. Gunakan PDF, DOC/DOCX, XLS/XLSX, atau gambar (JPG/PNG).'];

        }

        $ext = $allowed[$mime];



        $cleanOriginal = basename($originalName);

        $cleanOriginal = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanOriginal);

        if ($cleanOriginal === '') {

            $cleanOriginal = 'Lampiran.' . $ext;

        }

        if (function_exists('mb_strlen') && mb_strlen($cleanOriginal) > 190) {

            $cleanOriginal = mb_substr($cleanOriginal, 0, 190);

        } elseif (strlen($cleanOriginal) > 190) {

            $cleanOriginal = substr($cleanOriginal, 0, 190);

        }



        $slug = pelaporan_rekap_storage_slug($kode);

        $dir = pelaporan_rekap_upload_dir($kode);

        try {

            $uniq = bin2hex(random_bytes(6));

        } catch (Throwable $e) {

            if (function_exists('openssl_random_pseudo_bytes')) {

                $uniq = bin2hex(openssl_random_pseudo_bytes(6));

            } else {

                $uniq = substr(md5(uniqid((string)mt_rand(), true)), 0, 12);

            }

        }

        $stored = $slug . '-rekap-' . $uniq . '.' . $ext;

        $dest = $dir . DIRECTORY_SEPARATOR . $stored;



        if (!@move_uploaded_file($tmp, $dest)) {

            return ['ok' => false, 'error' => 'Lampiran gagal disimpan.'];

        }



        $rel = 'uploads/rekap/' . $slug . '/' . $stored;

        $stmt = $conn->prepare("INSERT INTO pelaporan_files (kode, original_name, stored_name, mime, size_bytes, rel_path) VALUES (?, ?, ?, ?, ?, ?)");

        if (!$stmt) {

            @unlink($dest);

            return ['ok' => false, 'error' => 'Gagal menyiapkan penyimpanan lampiran: ' . $conn->error];

        }



        $sizeStr = (string)$size;

        $stmt->bind_param("ssssss", $kode, $cleanOriginal, $stored, $mime, $sizeStr, $rel);

        if (!$stmt->execute()) {

            @unlink($dest);

            return ['ok' => false, 'error' => 'Gagal menyimpan metadata lampiran: ' . $stmt->error];

        }



        return ['ok' => true, 'file' => ['original' => $cleanOriginal, 'rel' => $rel]];

    }

}



if (!function_exists('pelaporan_redirect_with_filters')) {

    function pelaporan_redirect_with_filters(): void {

        $params = [

            'q' => $_GET['q'] ?? '',

            's' => $_GET['s'] ?? '',

            'from' => $_GET['from'] ?? '',

            'to' => $_GET['to'] ?? '',

        ];

        $query = http_build_query($params);

        header('Location: ' . $_SERVER['PHP_SELF'] . ($query !== '' ? '?'.$query : ''));

        exit;

    }

}



/* ===== Akses ===== */

if (empty($_SESSION['user'])) { header('Location: ' . route_url('login', ['open' => 'login'])); exit; }

$actor = pelaporan_actor_group($_SESSION['user']);

if (!in_array($actor, ['admin','kepala_ski','direktur'], true)) {

    http_response_code(403);

    die('Akses ditolak');

}

$roleLabels = [

    'admin' => 'Admin SKI',

    'kepala_ski' => 'Kepala SKI',

    'direktur' => 'Direktur'

];

$roleLabel = $roleLabels[$actor] ?? ucfirst($actor);

$statusOptionsAll = pelaporan_status_options();

$visibleStatuses = pelaporan_visible_statuses_for_actor($actor);

$allStatusKeys = array_keys(pelaporan_status_catalog());

$restrictStatuses = array_diff($allStatusKeys, $visibleStatuses);

if (!empty($restrictStatuses)) {

    $statusOptionsAll = array_intersect_key($statusOptionsAll, array_flip($visibleStatuses));

    if (empty($statusOptionsAll)) {

        $statusOptionsAll = array_intersect_key(pelaporan_status_options(), array_flip($visibleStatuses));

    }

}

$transitionMatrix = pelaporan_transition_matrix();

$actorHandleStatuses = array_keys($transitionMatrix[$actor] ?? []);

$actorHandleLabels = array_values(array_unique(array_map('pelaporan_status_label', $actorHandleStatuses)));



/* =======================================================================

   EXPORT CSV / XLS

   ======================================================================= */

if (isset($_GET['export']) && in_array($_GET['export'], ['csv','xls'], true)) {

    while (ob_get_level()) { ob_end_clean(); }



    $qExp   = trim($_GET['q']   ?? '');

    $sExp   = trim($_GET['s']   ?? '');

    $from   = trim($_GET['from'] ?? '');

    $to     = trim($_GET['to']   ?? '');



    $statusCanonicalExp = $sExp !== '' ? pelaporan_status_canonical($sExp) : '';

    if ($statusCanonicalExp !== '' && !isset($statusOptionsAll[$statusCanonicalExp])) {

        $statusCanonicalExp = '';

    }



    $whereParts = ['1=1'];

    $paramsExp = [];

    $typesExp  = '';



    if ($qExp !== '') {

        $whereParts[] = "(kode LIKE CONCAT('%',?,'%') OR kategori LIKE CONCAT('%',?,'%') OR isi LIKE CONCAT('%',?,'%'))";

        $paramsExp[] = $qExp; $paramsExp[] = $qExp; $paramsExp[] = $qExp;

        $typesExp .= 'sss';

    }

    if ($statusCanonicalExp !== '') {

        $values = pelaporan_status_db_values($statusCanonicalExp);

        $placeholders = implode(',', array_fill(0, count($values), '?'));

        $whereParts[] = "status IN ($placeholders)";

        foreach ($values as $val) { $paramsExp[] = $val; $typesExp .= 's'; }

    }

    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {

        $whereParts[] = "DATE(created_at) >= ?";

        $paramsExp[] = $from; $typesExp .= 's';

    }

    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {

        $whereParts[] = "DATE(created_at) <= ?";

        $paramsExp[] = $to; $typesExp .= 's';

    }



    $whereExp = 'WHERE '.implode(' AND ', $whereParts);

    $sqlExp = "SELECT kode,kategori,isi,status,tanggal,created_at FROM pelaporan $whereExp ORDER BY created_at DESC";

    $stmtExp = $conn->prepare($sqlExp);

    if ($typesExp !== '') { $stmtExp->bind_param($typesExp, ...$paramsExp); }

    $stmtExp->execute();

    $re = $stmtExp->get_result();



    if ($_GET['export'] === 'csv') {

        header('Content-Type: text/csv; charset=UTF-8');

        header('Content-Disposition: attachment; filename="pelaporan_'.date('Ymd_His').'.csv"');

        header('Pragma: no-cache'); header('Expires: 0');

        $out = fopen('php://output','w');

        fprintf($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Kode','Kategori','Isi','Status','Tanggal','Dibuat']);

        while ($row = $re->fetch_assoc()) {

            $isi = preg_replace('/\s+/', ' ', (string)$row['isi']);

            if (function_exists('mb_strlen') && mb_strlen($isi) > 500) { $isi = mb_substr($isi,0,500).'...'; }

            elseif (strlen($isi) > 500) { $isi = substr($isi,0,500).'...'; }

            $statusLabel = pelaporan_status_label($row['status']);

            fputcsv($out, [$row['kode'],$row['kategori'],$isi,$statusLabel,$row['tanggal'],$row['created_at']]);

        }

        fclose($out);

        exit;

    }



    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');

    header('Content-Disposition: attachment; filename="pelaporan_'.date('Ymd_His').'.xls"');

    header('Pragma: no-cache'); header('Expires: 0');

    echo '<html><head><meta charset="utf-8"><style>';

    echo 'table{border-collapse:collapse;font-family:Calibri,Arial,sans-serif;font-size:11pt}'

        .'th,td{border:1px solid #ccc;padding:6px 8px;vertical-align:top}'

        .'th{background:#e9f5ee;color:#107a3d}';

    echo '</style>

</head><body><table>';

    echo '<tr><th>Kode</th><th>Kategori</th><th>Isi</th><th>Status</th><th>Tanggal</th><th>Dibuat</th></tr>';

    while ($row = $re->fetch_assoc()) {

        $isi = preg_replace('/\s+/', ' ', (string)$row['isi']);

        if (function_exists('mb_strlen') && mb_strlen($isi) > 500) { $isi = mb_substr($isi,0,500).'...'; }

        elseif (strlen($isi) > 500) { $isi = substr($isi,0,500).'...'; }

        $statusLabel = pelaporan_status_label($row['status']);

        echo '<tr>'

            .'<td>'.e($row['kode']).'</td>'

            .'<td>'.e($row['kategori']).'</td>'

            .'<td>'.e($isi).'</td>'

            .'<td>'.e($statusLabel).'</td>'

            .'<td>'.e($row['tanggal']).'</td>'

            .'<td>'.e($row['created_at']).'</td>'

            .'</tr>';

    }

    echo '</table></body></html>';

    exit;

}



/* =======================================================================

   HAPUS LAPORAN (ADMIN)

   ======================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_report') {

    require_post();



    if ($actor !== 'admin') {

        flash('err', 'Akses hapus laporan hanya untuk admin.');

        pelaporan_redirect_with_filters();

    }



    $kode = trim($_POST['kode'] ?? '');

    if ($kode === '') {

        flash('err', 'Kode laporan tidak valid.');

        pelaporan_redirect_with_filters();

    }



    $filePaths = [];

    if ($stmtFiles = $conn->prepare("SELECT rel_path FROM pelaporan_files WHERE kode=?")) {

        $stmtFiles->bind_param('s', $kode);

        if ($stmtFiles->execute()) {

            $resFiles = $stmtFiles->get_result();

            while ($rowFile = $resFiles->fetch_assoc()) {

                $rel = trim((string)($rowFile['rel_path'] ?? ''));

                if ($rel !== '') { $filePaths[] = $rel; }

            }

        }

        $stmtFiles->close();

    }



    $conn->begin_transaction();

    try {

        if ($stmtDelFiles = $conn->prepare("DELETE FROM pelaporan_files WHERE kode=?")) {

            $stmtDelFiles->bind_param('s', $kode);

            $stmtDelFiles->execute();

            $stmtDelFiles->close();

        } else {

            throw new RuntimeException('Gagal menyiapkan hapus lampiran.');

        }



        if ($stmtDelLog = $conn->prepare("DELETE FROM pelaporan_log WHERE kode=?")) {

            $stmtDelLog->bind_param('s', $kode);

            $stmtDelLog->execute();

            $stmtDelLog->close();

        } else {

            throw new RuntimeException('Gagal menyiapkan hapus log.');

        }



        if ($stmtDel = $conn->prepare("DELETE FROM pelaporan WHERE kode=? LIMIT 1")) {

            $stmtDel->bind_param('s', $kode);

            $stmtDel->execute();

            if ($stmtDel->affected_rows < 1) {

                $stmtDel->close();

                throw new RuntimeException('Data laporan tidak ditemukan.');

            }

            $stmtDel->close();

        } else {

            throw new RuntimeException('Gagal menyiapkan hapus laporan.');

        }



        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        flash('err', 'Gagal menghapus laporan: ' . $e->getMessage());

        pelaporan_redirect_with_filters();

    }



    foreach ($filePaths as $rel) {

        $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);

        if (is_file($path)) {

            @unlink($path);

        }

        $dir = dirname($path);

        if (is_dir($dir)) {

            @rmdir($dir);

        }

    }



    flash('ok', 'Laporan ' . e($kode) . ' berhasil dihapus.');

    pelaporan_redirect_with_filters();

}



/* =======================================================================

   UPDATE TL (DIREKTUR)

   ======================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_tl') {

    require_post();



    if (!in_array($actor, ['direktur','admin'], true)) {

        flash('err', 'Aksi tindak lanjut hanya dapat dilakukan oleh Direktur atau Admin.');

        pelaporan_redirect_with_filters();

    }



    $kode = trim($_POST['kode'] ?? '');

    if ($kode === '') {

        flash('err', 'Kode laporan tidak valid.');

        pelaporan_redirect_with_filters();

    }



    $statusTl = trim($_POST['tl_status'] ?? '');

    $allowedTl = pelaporan_tl_allowed_statuses();

    if (!in_array($statusTl, $allowedTl, true)) {

        flash('err', 'Status tindak lanjut tidak dikenal.');

        pelaporan_redirect_with_filters();

    }



    $dueRaw = trim($_POST['tl_due'] ?? '');

    if ($dueRaw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueRaw)) {

        flash('err', 'Format tanggal TL tidak valid (gunakan YYYY-MM-DD).');

        pelaporan_redirect_with_filters();

    }

    if ($dueRaw === '' && $statusTl !== 'Selesai') {

        $dueDateObj = new DateTime('today');

        $dueDateObj->modify('+15 days');

        $dueRaw = $dueDateObj->format('Y-m-d');

    }

    $dueParam = $dueRaw !== '' ? $dueRaw : null;



    $noteRaw = trim($_POST['tl_note'] ?? '');

    if (function_exists('mb_strlen')) {

        $noteRaw = mb_substr($noteRaw, 0, 250);

    } else {

        $noteRaw = substr($noteRaw, 0, 250);

    }

    $noteParam = $noteRaw === '' ? null : $noteRaw;



    $uidParam = $_SESSION['user']['id'] ?? null;

    if ($uidParam !== null) { $uidParam = (int)$uidParam; }

    $unameParam = trim((string)($_SESSION['user']['nama'] ?? ''));

    if ($unameParam === '') { $unameParam = $actor === 'direktur' ? 'Direktur' : 'Admin SKI'; }



    $stmt = $conn->prepare("UPDATE pelaporan SET tl_status=?, tl_due_date=?, tl_catatan=?, tl_updated_at=NOW(), tl_updated_by=?, tl_updated_name=? WHERE kode=? LIMIT 1");

    if (!$stmt) {

        flash('err', 'Query TL gagal: '.e($conn->error));

        pelaporan_redirect_with_filters();

    }

    $stmt->bind_param("sssiss", $statusTl, $dueParam, $noteParam, $uidParam, $unameParam, $kode);

    $ok = $stmt->execute();

    if ($ok && $stmt->affected_rows >= 0) {

        flash('ok', 'Status tindak lanjut laporan '.e($kode).' diperbarui menjadi <b>'.e($statusTl).'</b>.');

    } else {

        flash('err', 'Tidak ada perubahan atau gagal memperbarui status TL.');

    }

    pelaporan_redirect_with_filters();

}



/* =======================================================================

   UPDATE STATUS

   ======================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {

    require_post();



    $kode   = trim($_POST['kode']   ?? '');

    $targetRaw = trim($_POST['status'] ?? '');

    $note   = trim($_POST['note']   ?? '');



    if ($kode === '' || $targetRaw === '') {

        flash('err','Data tidak lengkap');

        header('Location: '.$_SERVER['PHP_SELF']); exit;

    }



    $target = pelaporan_status_canonical($targetRaw);



    $stmtCurrent = $conn->prepare("SELECT status, tl_status, tl_due_date FROM pelaporan WHERE kode=? LIMIT 1");

    if (!$stmtCurrent) {

        flash('err','Query error: '.e($conn->error));

        header('Location: '.$_SERVER['PHP_SELF']); exit;

    }

    $stmtCurrent->bind_param('s', $kode);

    $stmtCurrent->execute();

    $currentRow = $stmtCurrent->get_result()->fetch_assoc();

    if (!$currentRow) {

        flash('err','Kode tidak ditemukan');

        header('Location: '.$_SERVER['PHP_SELF']); exit;

    }



    $currentCanonical = pelaporan_status_canonical($currentRow['status']);

    $currentTlStatus = trim((string)($currentRow['tl_status'] ?? ''));

    $currentTlDue = $currentRow['tl_due_date'] ?? null;

    $available = pelaporan_available_transitions($actor, $currentCanonical);

    $transitionMeta = null;

    foreach ($available as $opt) {

        if ($opt['to'] === $target) { $transitionMeta = $opt; break; }

    }

    if (!$transitionMeta) {

        flash('err','Aksi tidak diperbolehkan pada status ini.');

        header('Location: '.$_SERVER['PHP_SELF']); exit;

    }



    if (!empty($transitionMeta['note_required']) && $note === '') {

        flash('err','Catatan wajib diisi.');

        header('Location: '.$_SERVER['PHP_SELF']); exit;

    }



    if (function_exists('mb_strlen')) {

        $note = mb_substr($note, 0, 250);

    } else {

        $note = substr($note, 0, 250);

    }



    $attachmentInfo = null;

    if (!empty($transitionMeta['allow_attachment'])) {

        $upload = pelaporan_process_admin_attachment($conn, $kode);

        if (!$upload['ok']) {

            flash('err', $upload['error']);

            pelaporan_redirect_with_filters();

        }

        $attachmentInfo = $upload['file'];

    }



    $stmt = $conn->prepare("UPDATE pelaporan SET status=? WHERE kode=? LIMIT 1");

    if (!$stmt) {

        flash('err','Query error: '.e($conn->error));

        header('Location: '.$_SERVER['PHP_SELF']); exit;

    }

    $stmt->bind_param('ss', $target, $kode);

    $ok = $stmt->execute();



    if ($ok) {

        $uid   = $_SESSION['user']['id']   ?? null;

        $uname = $_SESSION['user']['nama'] ?? null;

        if ($stmtL = $conn->prepare("INSERT INTO pelaporan_log (kode,status_from,status_to,note,user_id,user_name) VALUES (?,?,?,?,?,?)")) {

            $fromLog = $currentCanonical;

            $toLog   = $target;

            $noteForLog = $note;

            if ($attachmentInfo) {

                $tag = 'Lampiran: '.$attachmentInfo['original'];

                $noteForLog = $noteForLog !== '' ? ($noteForLog.' | '.$tag) : $tag;

                if (function_exists('mb_strlen')) {

                    $noteForLog = mb_substr($noteForLog, 0, 250);

                } else {

                    $noteForLog = substr($noteForLog, 0, 250);

                }

            }

            $stmtL->bind_param('ssssss', $kode, $fromLog, $toLog, $noteForLog, $uid, $uname);

            $stmtL->execute();

        }



        $uidForTl = $uid !== null ? (int)$uid : null;

        $unameForTl = trim((string)($uname ?? ''));

        if ($unameForTl === '') { $unameForTl = 'Sistem'; }

        if ($target === 'Verifikasi Direktur') {

            $dueAuto = $currentTlDue;

            if ($dueAuto === null || $dueAuto === '') {

                $dueObj = new DateTime('today');

                $dueObj->modify('+15 days');

                $dueAuto = $dueObj->format('Y-m-d');

            }

            if ($stmtTl = $conn->prepare("UPDATE pelaporan SET tl_status='Belum TL', tl_due_date=?, tl_catatan=NULL, tl_updated_at=NOW(), tl_updated_by=?, tl_updated_name=? WHERE kode=? LIMIT 1")) {

                $stmtTl->bind_param("siss", $dueAuto, $uidForTl, $unameForTl, $kode);

                $stmtTl->execute();

            }

        } elseif ($target === 'Diteruskan ke Unit TL') {

            $dueAuto = $currentTlDue;

            if ($dueAuto === null || $dueAuto === '') {

                $dueObj = new DateTime('today');

                $dueObj->modify('+15 days');

                $dueAuto = $dueObj->format('Y-m-d');

            }

            if ($stmtTl = $conn->prepare("UPDATE pelaporan SET tl_status='Belum TL', tl_due_date=?, tl_catatan=NULL, tl_updated_at=NOW(), tl_updated_by=?, tl_updated_name=? WHERE kode=? LIMIT 1")) {

                $stmtTl->bind_param("siss", $dueAuto, $uidForTl, $unameForTl, $kode);

                $stmtTl->execute();

            }

        } elseif ($target === 'Monitoring TL') {

            if ($currentTlStatus === '' || $currentTlStatus === 'Belum TL') {

                if ($stmtTl = $conn->prepare("UPDATE pelaporan SET tl_status='Proses', tl_updated_at=NOW(), tl_updated_by=?, tl_updated_name=? WHERE kode=? LIMIT 1")) {

                    $stmtTl->bind_param("iss", $uidForTl, $unameForTl, $kode);

                    $stmtTl->execute();

                }

            }

        } elseif ($target === 'Arsip') {

            if ($currentTlStatus !== 'Selesai') {

                if ($stmtTl = $conn->prepare("UPDATE pelaporan SET tl_status='Selesai', tl_updated_at=NOW(), tl_updated_by=?, tl_updated_name=? WHERE kode=? LIMIT 1")) {

                    $stmtTl->bind_param("iss", $uidForTl, $unameForTl, $kode);

                    $stmtTl->execute();

                }

            }

        }



        $msg = 'Status '.e($kode).' diperbarui menjadi <b>'.e(pelaporan_status_label($target)).'</b>';

        if ($note !== '') { $msg .= ' ('.e($note).')'; }

        if ($attachmentInfo) { $msg .= ' - Lampiran tersimpan: '.e($attachmentInfo['original']); }

        flash('ok', $msg);



        $mailer_path = __DIR__.'/mailer.php';

        if (is_file($mailer_path)) {

            require_once $mailer_path;

            if (function_exists('mailer_admin_list') && function_exists('mailer_send')) {

                $admins = mailer_admin_list($conn);

                if (!empty($admins) && in_array($target, ['Verifikasi SKI','Verifikasi Direktur','Diteruskan ke Unit TL'], true)) {

                    $subject = 'Notifikasi Pelaporan: '.pelaporan_status_label($target).' ('.$kode.')';

                    $linkAdmin = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http')

                                .'://'.$_SERVER['HTTP_HOST']

                                .dirname($_SERVER['PHP_SELF'])

                                .'/pelaporan_detail.php?kode='.urlencode($kode);

                    $html = '<p>Status laporan <b>'.e($kode).'</b> berubah menjadi <b>'.e(pelaporan_status_label($target)).'</b>.</p>'

                          . '<p>Catatan: '.($note !== '' ? e($note) : '<em>(tidak ada)</em>').'</p>'

                          . '<p><a href="'.e($linkAdmin).'">Lihat detail</a></p>';

                    @mailer_send($admins, $subject, $html);

                }

            }

        }

    } else {

        flash('err','Tidak ada perubahan atau terjadi kesalahan.');

    }



    $q=$_GET['q']??''; $s=$_GET['s']??''; $from=$_GET['from']??''; $to=$_GET['to']??'';

    header('Location: '.$_SERVER['PHP_SELF'].'?q='.urlencode($q).'&s='.urlencode($s).'&from='.urlencode($from).'&to='.urlencode($to));

    exit;

}



/* =======================================================================

   FILTER & DATA LIST

   ======================================================================= */

$q    = trim($_GET['q'] ?? '');

$sIn  = trim($_GET['s'] ?? '');

$s    = $sIn === '' ? '' : pelaporan_status_canonical($sIn);

if ($s !== '' && !isset($statusOptionsAll[$s])) { $s = ''; }

$from = trim($_GET['from'] ?? '');

$to   = trim($_GET['to']   ?? '');

$page = max(1,(int)($_GET['page'] ?? 1));

$per  = 10;

$off  = ($page-1)*$per;



$whereParts = ['1=1'];

$params = [];

$types  = '';



if ($q !== '') {

    $whereParts[] = "(kode LIKE CONCAT('%',?,'%') OR kategori LIKE CONCAT('%',?,'%') OR isi LIKE CONCAT('%',?,'%'))";

    $params[]=$q; $params[]=$q; $params[]=$q; $types.='sss';

}

if ($s !== '') {

    $values = pelaporan_status_db_values($s);

    $placeholders = implode(',', array_fill(0, count($values), '?'));

    $whereParts[] = "status IN ($placeholders)";

    foreach ($values as $val) { $params[]=$val; $types.='s'; }

}

if (!empty($restrictStatuses)) {

    $allowedDb = [];

    foreach ($visibleStatuses as $canonStatus) {

        $allowedDb = array_merge($allowedDb, pelaporan_status_db_values($canonStatus));

    }

    $allowedDb = array_values(array_unique($allowedDb));

    if (!empty($allowedDb)) {

        $placeholders = implode(',', array_fill(0, count($allowedDb), '?'));

        $whereParts[] = "status IN ($placeholders)";

        foreach ($allowedDb as $val) { $params[] = $val; $types .= 's'; }

    }

}

if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {

    $whereParts[] = "DATE(created_at) >= ?";

    $params[]=$from; $types.='s';

}

if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {

    $whereParts[] = "DATE(created_at) <= ?";

    $params[]=$to; $types.='s';

}



$where = 'WHERE '.implode(' AND ', $whereParts);



$sqlCount = "SELECT COUNT(*) c FROM pelaporan $where";

$stmtC = $conn->prepare($sqlCount);

if ($types !== '') { $stmtC->bind_param($types, ...$params); }

$stmtC->execute();

$total = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);



$sql = "SELECT kode,kategori,LEFT(isi,150) isi_short,status,created_at,

               tl_status, tl_due_date, tl_catatan, tl_updated_at, tl_updated_name

        FROM pelaporan

        $where

        ORDER BY created_at DESC

        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

$paramsList = $params;

$typesList  = $types.'ii';

$paramsList[] = $per;

$paramsList[] = $off;

$stmt->bind_param($typesList, ...$paramsList);

$stmt->execute();

$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pages = max(1,(int)ceil($total/$per));



$rekapNotes = [];

if ($actor === 'direktur' && !empty($rows)) {

    $codes = array_values(array_unique(array_map(fn($r) => $r['kode'], $rows)));

    $placeholders = implode(',', array_fill(0, count($codes), '?'));

    $sqlRekap = "SELECT kode, note FROM pelaporan_log WHERE kode IN ($placeholders) AND status_to='Verifikasi Direktur' ORDER BY created_at DESC, id DESC";

    $stmtRekap = $conn->prepare($sqlRekap);

    if ($stmtRekap) {

        $typesRekap = str_repeat('s', count($codes));

        $stmtRekap->bind_param($typesRekap, ...$codes);

        $stmtRekap->execute();

        $resRekap = $stmtRekap->get_result();

        while ($rowRekap = $resRekap->fetch_assoc()) {

            $kodeKey = $rowRekap['kode'];

            if (!isset($rekapNotes[$kodeKey])) {

                $noteRaw = trim((string)$rowRekap['note']);

                if ($noteRaw !== '') {

                    $parts = explode('| Lampiran:', $noteRaw, 2);

                    $noteClean = trim($parts[0]);

                    $rekapNotes[$kodeKey] = $noteClean !== '' ? $noteClean : $noteRaw;

                } else {

                    $rekapNotes[$kodeKey] = '';

                }

            }

        }

    }

}



foreach ($rows as &$r) {

    $r['status_canonical'] = pelaporan_status_canonical($r['status']);

    $r['status_label'] = pelaporan_status_label($r['status']);

    $r['status_badge'] = pelaporan_status_badge($r['status']);

    $r['status_desc'] = pelaporan_status_description($r['status']);

    $r['actions'] = pelaporan_available_transitions($actor, $r['status_canonical']);

    $tlStatusRaw = trim((string)($r['tl_status'] ?? ''));

    if ($tlStatusRaw === '') { $tlStatusRaw = 'Belum TL'; }

    if (!in_array($tlStatusRaw, pelaporan_tl_allowed_statuses(), true)) {

        $tlStatusRaw = 'Belum TL';

    }

    $r['tl_status'] = $tlStatusRaw;

    $r['tl_badge'] = pelaporan_tl_badge_class($tlStatusRaw);

    $warningMeta = pelaporan_tl_warning_meta($r['tl_due_date'] ?? null, 15);

    $r['tl_warning_label'] = $warningMeta[0];

    $r['tl_warning_color'] = $warningMeta[1];

    $r['tl_warning_desc'] = $warningMeta[2];

    $r['tl_warning_diff'] = $warningMeta[3];

    $r['tl_catatan'] = trim((string)($r['tl_catatan'] ?? ''));

    $r['tl_updated_name'] = trim((string)($r['tl_updated_name'] ?? ''));

    $r['tl_updated_at'] = $r['tl_updated_at'] ?? null;

    $r['tl_due_date'] = $r['tl_due_date'] ?? null;

    $r['tl_can_update'] = ($actor === 'direktur');

    if ($actor === 'direktur') {

        $kode = $r['kode'];

        $note = $rekapNotes[$kode] ?? '';

        $r['display_text'] = $note !== '' ? $note : $r['isi_short'];

        $r['display_source'] = $note !== '' ? 'admin' : 'pelapor';

        $r['rekap_note'] = $note;

    } else {

        $r['display_text'] = $r['isi_short'];

        $r['display_source'] = 'pelapor';

        $r['rekap_note'] = '';

    }

}

unset($r);



/* Lampiran per kode */

$lampCount = [];

if (!empty($rows)) {

    $codes = array_map(fn($r) => $r['kode'], $rows);

    $in = str_repeat('?,', count($codes)-1) . '?';

    $sqlLC = "SELECT kode, COUNT(*) c FROM pelaporan_files WHERE kode IN ($in) GROUP BY kode";

    $stmtLC = $conn->prepare($sqlLC);

    $typesLC = str_repeat('s', count($codes));

    $stmtLC->bind_param($typesLC, ...$codes);

    $stmtLC->execute();

    $rLC = $stmtLC->get_result();

    while ($x = $rLC->fetch_assoc()) { $lampCount[$x['kode']] = (int)$x['c']; }

}



$statusBuckets = ['masuk'=>0,'proses'=>0,'kembali'=>0,'arsip'=>0];

if ($resBuckets = $conn->query("SELECT status, COUNT(*) c FROM pelaporan GROUP BY status")) {

    while ($b = $resBuckets->fetch_assoc()) {

        $canonical = pelaporan_status_canonical($b['status']);

        $bucket = pelaporan_status_bucket($canonical);

        if (!isset($statusBuckets[$bucket])) { $statusBuckets[$bucket] = 0; }

        $statusBuckets[$bucket] += (int)$b['c'];

    }

}

$totalAll = array_sum($statusBuckets);

$showTlColumns = in_array($actor, ['direktur','admin'], true);

?>

<!doctype html>

<html lang="id">

<head>

  <meta charset="utf-8">

  <title>Kelola Pelaporan - SIKAT</title>

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link href="<?= htmlspecialchars(asset_url('assets/css/ui_base.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

  <style>

    :root{ --brand:#218838; --brand-dark:#1b6e2c; --accent:#f0c300; --soft:#f4f8f5; --card:#fff; --border:#d6e9de; --text-green:#107a3d; }

    body{background:var(--soft);}

    .appbar{background:var(--brand); border-bottom:4px solid var(--accent); color:#fff;}

    .card-soft{background:var(--card); border:1px solid var(--border); border-radius:16px; box-shadow:0 6px 18px rgba(0,0,0,.06);}

    .badge-soft{background:#e9f5ee; color:var(--text-green); border:1px solid var(--border);}

    .btn-primary{ background:var(--brand); border-color:var(--brand-dark); }

    .btn-primary:hover{ background:var(--brand-dark); border-color:var(--brand-dark); }

    .table thead th{ background:#f2f8f4; }

    .form-label{ font-size:.82rem; color:#4b5563; margin-bottom:.25rem; }

    .mini-stat{background:#fff;border:1px solid var(--border);border-radius:14px;padding:10px 16px;box-shadow:0 4px 12px rgba(0,0,0,.04);}

    .mini-stat small{display:block;color:#6b7280;font-size:.78rem;}

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

    <?php if (!is_auditee()): ?>

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





  <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center">

    <div>

      <strong>Peran:</strong> <?= e($roleLabel) ?>.

      <?php if(!empty($actorHandleLabels)): ?>

        <span class="ms-1">Tahap yang dapat Anda tindak lanjuti: <?= e(implode(', ', $actorHandleLabels)) ?>.</span>

      <?php else: ?>

        <span class="ms-1">Anda hanya dapat memonitor progres.</span>

      <?php endif; ?>

    </div>

  </div>



  <div class="row g-2 mb-3">

    <div class="col-6 col-lg-3"><div class="mini-stat"><small>Total Laporan</small><h5 class="mb-0"><?= number_format($totalAll) ?></h5></div></div>

    <div class="col-6 col-lg-3"><div class="mini-stat"><small>Pengaduan Masuk</small><h5 class="mb-0"><?= number_format($statusBuckets['masuk'] ?? 0) ?></h5></div></div>

    <div class="col-6 col-lg-3"><div class="mini-stat"><small>Tahap Berjalan</small><h5 class="mb-0"><?= number_format($statusBuckets['proses'] ?? 0) ?></h5></div></div>

    <div class="col-6 col-lg-3"><div class="mini-stat"><small>Arsip</small><h5 class="mb-0"><?= number_format($statusBuckets['arsip'] ?? 0) ?></h5></div></div>

  </div>

  <p class="text-muted small">Kembali ke pelapor: <?= number_format($statusBuckets['kembali'] ?? 0) ?> laporan.</p>



  <div class="card-soft p-3 mb-3">

    <form class="row g-2">

      <div class="col-md-4">

        <label class="form-label">Kata Kunci</label>

        <input name="q" value="<?= e($q) ?>" class="form-control" placeholder="kode / kategori / isi">

      </div>

      <div class="col-md-3">

        <label class="form-label">Status</label>

        <select name="s" class="form-select">

          <option value="">(semua)</option>

          <?php foreach($statusOptionsAll as $key=>$label): ?>

            <option value="<?= e($key) ?>" <?= $s===$key?'selected':'' ?>><?= e($label) ?></option>

          <?php endforeach; ?>

        </select>

      </div>



      <div class="w-100 d-block d-md-none"></div>



      <div class="col-md-3">

        <label class="form-label">Dari</label>

        <input type="date" name="from" value="<?= e($from) ?>" class="form-control">

      </div>

      <div class="col-md-3">

        <label class="form-label">Sampai</label>

        <input type="date" name="to" value="<?= e($to) ?>" class="form-control">

      </div>



      <div class="col-md-3 d-flex align-items-end">

        <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>

      </div>

      <div class="col-md-2 d-flex align-items-end">

        <a class="btn btn-outline-secondary w-100" href="<?= e($_SERVER['PHP_SELF']) ?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>

      </div>



      <div class="w-100"></div>



      <div class="col-md-2 mt-2">

        <a class="btn btn-success w-100 btn-loading" data-loading="1" href="?q=<?= urlencode($q) ?>&s=<?= urlencode($s) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>

      </div>

      <div class="col-md-2 mt-2">

        <a class="btn btn-outline-success w-100 btn-loading" data-loading="1" href="?q=<?= urlencode($q) ?>&s=<?= urlencode($s) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=xls"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel</a>

      </div>

    </form>

  </div>



  <div class="card-soft p-3">

    <div class="table-responsive">

      <table class="table align-middle">

        <thead>

          <tr>

            <th style="width:140px">Kode</th>

            <th style="width:120px">Kategori</th>

            <th>Isi (ringkas)</th>

            <th style="width:120px">Lampiran</th>

            <th style="width:160px">Dibuat</th>

            <th style="width:160px">Status</th>

            <?php if($showTlColumns): ?>

              <th style="width:190px">Early-Warning Direktur/TL</th>

              <th style="width:240px">Status TL</th>

            <?php endif; ?>

            <th style="width:340px">Aksi</th>

          </tr>

        </thead>

        <tbody>

          <?php if(empty($rows)): ?>

            <tr><td colspan="7"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>

          <?php else: ?>

            <?php foreach($rows as $r): ?>

              <tr>

                <td><span class="badge badge-soft"><?= e($r['kode']) ?></span></td>

                <td><?= e($r['kategori']) ?></td>

                <td>

                  <?php if($actor === 'direktur' && !empty($r['rekap_note'])): ?>

                    <div class="small text-muted">Ringkasan Admin SKI</div>

                    <?= nl2br(e($r['display_text'])) ?>

                  <?php else: ?>

                    <?= nl2br(e($r['display_text'])) ?>

                  <?php endif; ?>

                  <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-link p-0 fw-semibold js-report-detail" data-report-code="<?= e((string)$r['kode']) ?>" data-report-focus="content" aria-label="Lihat isi lengkap laporan <?= e((string)$r['kode']) ?>">Lihat Isi Lengkap</button>
                  </div>

                </td>

                <td>

                  <?php $cnt = $lampCount[$r['kode']] ?? 0; ?>

                  <?php if($cnt>0): ?>

                    <button type="button" class="btn btn-sm btn-outline-success js-report-detail" data-report-code="<?= e((string)$r['kode']) ?>">Lihat (<?= $cnt ?>)</button>

                  <?php else: ?>

                    <span class="text-muted">-</span>

                  <?php endif; ?>

                </td>

                <td><?= e($r['created_at']) ?></td>

                <td>

                  <span class="badge <?= e($r['status_badge']) ?>"><?= e($r['status_label']) ?></span>

                  <?php if(!empty($r['status_desc'])): ?>

                    <div class="small text-muted mt-1"><?= e($r['status_desc']) ?></div>

                  <?php endif; ?>

                </td>

                <?php if($showTlColumns): ?>

                  <td>

                    <?php if($r['tl_warning_label']): ?>

                      <?php if($r['status_canonical'] === 'Verifikasi Direktur'): ?>

                        <div class="small text-muted text-uppercase fw-semibold mb-1">Respon Direktur</div>

                      <?php endif; ?>

                      <span class="badge" style="background: <?= e($r['tl_warning_color']) ?>; color:#fff"><?= e($r['tl_warning_label']) ?></span>

                      <?php if($r['tl_warning_desc']): ?><div class="small text-muted mt-1"><?= e($r['tl_warning_desc']) ?></div><?php endif; ?>

                    <?php else: ?>

                      <span class="text-muted">-</span>

                    <?php endif; ?>

                    <?php if(!empty($r['tl_due_date'])): ?>

                      <div class="small text-muted">Jatuh tempo: <?= e($r['tl_due_date']) ?></div>

                    <?php endif; ?>

                  </td>

                  <td>

                    <div class="d-flex flex-column gap-2">

                      <span class="badge <?= e($r['tl_badge']) ?>"><?= e($r['tl_status']) ?></span>

                      <?php if($actor === 'direktur'): ?>

                        <form method="post" class="d-flex flex-wrap gap-2 align-items-center">

                          <?= csrf_field(); ?>

                          <input type="hidden" name="action" value="update_tl">

                          <input type="hidden" name="kode" value="<?= e($r['kode']) ?>">

                          <select name="tl_status" class="form-select form-select-sm" style="width:auto">

                            <?php foreach(pelaporan_tl_allowed_statuses() as $opt): ?>

                              <option value="<?= e($opt) ?>" <?= $opt===$r['tl_status']?'selected':'' ?>><?= e($opt) ?></option>

                            <?php endforeach; ?>

                          </select>

                          <input type="date" name="tl_due" value="<?= e((string)$r['tl_due_date']) ?>" class="form-control form-control-sm" style="width:150px" title="Jatuh tempo tindak lanjut">

                          <input type="text" name="tl_note" value="<?= e($r['tl_catatan']) ?>" class="form-control form-control-sm" style="width:200px" placeholder="Catatan (opsional)">

                          <button class="btn btn-sm btn-success">Simpan TL</button>

                        </form>

                        <?php if(!empty($r['tl_updated_name'])): ?>

                          <div class="small text-muted">Terakhir diperbarui oleh <?= e($r['tl_updated_name']) ?><?php if(!empty($r['tl_updated_at'])): ?> · <?= e($r['tl_updated_at']) ?><?php endif; ?></div>

                        <?php endif; ?>

                      <?php else: ?>

                        <?php if($r['tl_catatan'] !== ''): ?><div class="small text-muted">Catatan: <?= e($r['tl_catatan']) ?></div><?php endif; ?>

                        <?php if(!empty($r['tl_updated_name'])): ?>

                          <div class="small text-muted">Terakhir diperbarui <?= e($r['tl_updated_name']) ?><?php if(!empty($r['tl_updated_at'])): ?> · <?= e($r['tl_updated_at']) ?><?php endif; ?></div>

                        <?php endif; ?>

                      <?php endif; ?>

                    </div>

                  </td>

                <?php endif; ?>

                <td>

                  <div class="d-flex flex-column gap-2">

                    <button type="button" class="btn btn-sm btn-outline-secondary js-report-history" data-report-code="<?= e((string)$r['kode']) ?>" data-report-focus="history"><i class="bi bi-clock-history me-1"></i>Riwayat</button>

                    <?php if(!empty($r['actions'])): ?>

                      <?php if($actor === 'direktur'): ?>

                        <?php if($r['status_canonical'] === 'Verifikasi Direktur'): ?>

                          <div class="small text-muted text-uppercase fw-semibold">Aksi Verifikasi Direktur</div>

                        <?php elseif(in_array($r['status_canonical'], ['Diteruskan ke Unit TL','Monitoring TL'], true)): ?>

                          <div class="small text-muted text-uppercase fw-semibold">Aksi Tindak Lanjut</div>

                        <?php endif; ?>

                      <?php endif; ?>

                      <?php foreach($r['actions'] as $act): ?>

                        <form method="post" class="d-flex flex-wrap gap-2 align-items-center" <?= !empty($act['allow_attachment']) ? 'enctype="multipart/form-data"' : '' ?>>

                          <?= csrf_field(); ?>

                          <input type="hidden" name="action" value="update_status">

                          <input type="hidden" name="kode" value="<?= e($r['kode']) ?>">

                          <input type="hidden" name="status" value="<?= e($act['to']) ?>">

                          <?php if (!empty($act['note_placeholder']) || !empty($act['note_required'])): ?>

                            <input name="note" class="form-control form-control-sm flex-grow-1" placeholder="<?= e($act['note_placeholder'] ?: 'Catatan (opsional)') ?>" <?= !empty($act['note_required']) ? 'required' : '' ?>>

                          <?php else: ?>

                            <input type="hidden" name="note" value="">

                          <?php endif; ?>

                          <?php if (!empty($act['allow_attachment'])): ?>

                            <input type="file" name="attachment" class="form-control form-control-sm" style="max-width:240px" accept="<?= e(pelaporan_rekap_accept_attr()) ?>" title="Lampiran (opsional)">

                          <?php endif; ?>

                          <button class="btn btn-sm btn-primary"><?= e($act['label']) ?><?= !empty($act['note_required']) ? ' *' : '' ?></button>

                        </form>

                      <?php endforeach; ?>

                    <?php else: ?>

                      <span class="text-muted small">Tidak ada aksi untuk peran ini.</span>

                    <?php endif; ?>

                    <?php if ($actor === 'admin'): ?>

                      <form method="post" class="d-flex gap-2 align-items-center" onsubmit="return confirm('Hapus laporan <?= e($r['kode']) ?>? Tindakan ini tidak dapat dibatalkan.');">

                        <?= csrf_field(); ?>

                        <input type="hidden" name="action" value="delete_report">

                        <input type="hidden" name="kode" value="<?= e($r['kode']) ?>">

                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Hapus Laporan</button>

                      </form>

                    <?php endif; ?>

                  </div>

                </td>

              </tr>

            <?php endforeach; ?>

          <?php endif; ?>

        </tbody>

      </table>

    </div>

    <div class="small text-muted mt-2">Aksi bertanda * membutuhkan catatan.</div>

    <div class="mt-2 text-muted">

      Menampilkan <b><?= count($rows) ?></b> dari <b><?= (int)$total ?></b> data

      <?php if($from||$to): ?> untuk rentang <b><?= e($from ?: 'awal') ?></b> s.d. <b><?= e($to ?: 'akhir') ?></b><?php endif; ?>.

    </div>

    <nav class="mt-2">

      <ul class="pagination justify-content-end mb-0">

        <?php for($i=1;$i<=$pages;$i++): $active=($i===$page)?'active':''; ?>

          <li class="page-item <?= $active ?>">

            <a class="page-link" href="?q=<?= urlencode($q) ?>&s=<?= urlencode($s) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&page=<?= $i ?>"><?= $i ?></a>

          </li>

        <?php endfor; ?>

      </ul>

    </nav>

  </div>

</main>


<?php include __DIR__ . '/includes/report_detail_modal.php'; ?>

<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('assets/js/report_detail_modal.js')) ?>"></script>

</body>

</html>
