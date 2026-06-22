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
$__base = __DIR__;
$__candidates = [$__base.'/db.php', $__base.'/ski_new/db.php', $__base.'/db/db.php', dirname($__base).'/db.php', $__base.'/includes/db.php'];
$__found=false; foreach($__candidates as $__p){ if(is_file($__p)){ require_once $__p; $__found=true; break; } }
if(!$__found){ http_response_code(500); exit('db.php tidak ditemukan'); }
if(!isset($conn) || !($conn instanceof mysqli)){ http_response_code(500); exit('Koneksi DB tidak tersedia'); }
$conn->set_charset('utf8mb4');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function deny(){ http_response_code(403); exit('Akses ditolak'); }

if (empty($_GET['id'])) { http_response_code(400); exit('Param hilang'); }
$id = (int)$_GET['id'];

require_once __DIR__ . '/review_export_helpers.php';
$q = $conn->prepare("SELECT d.file_path, d.judul, d.reviu_id, r.kode FROM reviu_dokumen d JOIN reviu r ON r.id=d.reviu_id WHERE d.id=?");
$q->bind_param("i",$id);
$q->execute();
$doc = $q->get_result()->fetch_assoc();
if(!$doc){ http_response_code(404); exit('File tidak ditemukan'); }

// Otorisasi: yang boleh minimal user login (dan sebaiknya role terkait reviu). Untuk aman:
if (empty($_SESSION['user'])) deny();
review_require_access($conn, (int)$doc['reviu_id']);

$abs = realpath(__DIR__ . DIRECTORY_SEPARATOR . $doc['file_path']);
$base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reviu');
if(!$abs || strpos($abs, $base)!==0 || !is_file($abs)) { http_response_code(404); exit('File tidak ada'); }

$fname = basename($abs);
$mime  = sikat_guess_mime($abs) ?: 'application/octet-stream';
$mode = strtolower(trim($_GET['mode'] ?? 'view'));

$inlineTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];

if ($mode === 'download' || $mode === 'unduh') {
    $disposition = 'attachment';
} else {
    // lihat di browser untuk tipe yang didukung
    $disposition = in_array($mime, $inlineTypes, true) ? 'inline' : 'attachment';
}


session_release();

header('Content-Type: '.$mime);
header('Content-Length: '.filesize($abs));
header('Content-Disposition: '.$disposition.'; filename="'.str_replace('"','',$fname).'"');
readfile($abs);



