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

if (!$__found) { http_response_code(500); exit('db.php tidak ditemukan'); }

if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); exit('Koneksi DB tidak tersedia'); }

$conn->set_charset('utf8mb4');



require_once __DIR__ . '/pelaporan_helpers.php';



$fid = (int)($_GET['id'] ?? 0);

if ($fid < 1) { http_response_code(400); exit('Param id wajib.'); }



$stmt = $conn->prepare("SELECT f.id, f.kode, f.original_name, f.rel_path, f.mime, f.size_bytes, p.status

                        FROM pelaporan_files f

                        JOIN pelaporan p ON p.kode = f.kode

                        WHERE f.id=? LIMIT 1");

if (!$stmt) { http_response_code(500); exit('Query gagal.'); }

$stmt->bind_param('i', $fid);

$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$row) { http_response_code(404); exit('File tidak ditemukan.'); }



$actor = pelaporan_actor_group($_SESSION['user'] ?? []);

if (!in_array($actor, ['admin','kepala_ski','direktur'], true)) {

    http_response_code(403); exit('Akses ditolak.');

}

$statusCanonical = pelaporan_status_canonical((string)($row['status'] ?? ''));

$visibleStatuses = pelaporan_visible_statuses_for_actor($actor);

if (!in_array($statusCanonical, $visibleStatuses, true)) {

    http_response_code(403); exit('Akses ditolak.');

}



$rel = trim((string)($row['rel_path'] ?? ''));

if ($rel === '' || strpos($rel, "\0") !== false || strpos($rel, '..') !== false) {

    http_response_code(400); exit('Path tidak valid.');

}

$rel = str_replace('\\', '/', $rel);

$abs = realpath(__DIR__ . DIRECTORY_SEPARATOR . $rel);



$allowedBases = [

    realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads'),

    realpath(__DIR__ . DIRECTORY_SEPARATOR . 'upload')

];

$allowed = false;

if ($abs) {

    foreach ($allowedBases as $base) {

        if (!$base) { continue; }

        $base = rtrim($base, DIRECTORY_SEPARATOR);

        if ($abs === $base || strpos($abs, $base . DIRECTORY_SEPARATOR) === 0) {

            $allowed = true;

            break;

        }

    }

}

if (!$abs || !$allowed || !is_file($abs)) { http_response_code(404); exit('File tidak ditemukan.'); }



$mode = strtolower(trim((string)($_GET['mode'] ?? 'view')));

$disposition = in_array($mode, ['download','unduh','attachment'], true) ? 'attachment' : 'inline';

$filename = trim((string)($row['original_name'] ?? ''));

$filename = preg_replace('/[


"]+/', '', $filename);

if ($filename === '') { $filename = basename($abs); }

$mime = trim((string)($row['mime'] ?? ''));

if ($mime === '') {

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

    $mimeMap = [

        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',

        'pdf'  => 'application/pdf',

        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',

    ];

    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
}



session_release();

header('Content-Type: '.$mime);

header('Content-Length: '.filesize($abs));

header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');

header('X-Content-Type-Options: nosniff');

readfile($abs);

exit;
