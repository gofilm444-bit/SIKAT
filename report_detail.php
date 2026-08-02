<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pelaporan_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}
$conn->set_charset('utf8mb4');

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi login tidak ditemukan.']);
    exit;
}

if (!function_exists('report_detail_table_exists')) {
    function report_detail_table_exists(mysqli $conn, string $table): bool {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('report_detail_columns')) {
    function report_detail_columns(mysqli $conn, string $table): array {
        $cols = [];
        $safe = str_replace('`', '', $table);
        $res = $conn->query("SHOW COLUMNS FROM `{$safe}`");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $cols[(string)$row['Field']] = true;
            }
        }
        return $cols;
    }
}

if (!function_exists('report_detail_json_error')) {
    function report_detail_json_error(int $code, string $message): void {
        http_response_code($code);
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('report_detail_format_bytes')) {
    function report_detail_format_bytes($bytes): string {
        $bytes = max(0, (int)$bytes);
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

if (!function_exists('report_detail_datetime')) {
    function report_detail_datetime(?string $value): string {
        $value = trim((string)$value);
        if ($value === '') {
            return '-';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        return date('d/m/Y H:i', $ts);
    }
}

$user = $_SESSION['user'];
$actor = pelaporan_actor_group($user);
$role = strtolower((string)($user['peran'] ?? ''));
$rawRole = strtolower((string)($user['peran_raw'] ?? $role));
$aksesPelaporan = (int)($user['akses_pelaporan'] ?? 0);
$canAccess = in_array($actor, ['admin', 'kepala_ski', 'direktur'], true)
    || in_array($role, ['auditor'], true)
    || in_array($rawRole, ['auditor'], true)
    || $aksesPelaporan === 1;

if (!$canAccess) {
    report_detail_json_error(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
}

$kode = trim((string)($_GET['kode'] ?? ''));
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($kode === '' && $id <= 0) {
    report_detail_json_error(400, 'Kode atau ID laporan wajib diisi.');
}
if ($kode !== '' && !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $kode)) {
    report_detail_json_error(400, 'Kode laporan tidak valid.');
}

if (!report_detail_table_exists($conn, 'pelaporan')) {
    report_detail_json_error(500, 'Tabel pelaporan tidak tersedia.');
}

$pelaporanCols = report_detail_columns($conn, 'pelaporan');
$baseFields = ['kode', 'kategori', 'isi', 'status', 'created_at'];
$optionalFields = ['id', 'judul', 'tanggal', 'anonim', 'tl_status', 'tl_due_date', 'tl_catatan', 'tl_updated_at', 'tl_updated_name'];
$selectFields = [];
foreach (array_merge($baseFields, $optionalFields) as $field) {
    if (isset($pelaporanCols[$field])) {
        $selectFields[] = "`{$field}`";
    }
}
if (!isset($pelaporanCols['kode'])) {
    report_detail_json_error(500, 'Kolom kode laporan tidak tersedia.');
}

$sql = 'SELECT ' . implode(',', array_unique($selectFields)) . ' FROM pelaporan WHERE ';
if ($id > 0 && isset($pelaporanCols['id'])) {
    $sql .= 'id=? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        report_detail_json_error(500, 'Gagal menyiapkan detail laporan.');
    }
    $stmt->bind_param('i', $id);
} else {
    $sql .= 'kode=? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        report_detail_json_error(500, 'Gagal menyiapkan detail laporan.');
    }
    $stmt->bind_param('s', $kode);
}
$stmt->execute();
$lap = $stmt->get_result()->fetch_assoc();
if (!$lap) {
    report_detail_json_error(404, 'Laporan tidak ditemukan.');
}

$statusCanonical = pelaporan_status_canonical((string)($lap['status'] ?? ''));
$visibleStatuses = pelaporan_visible_statuses_for_actor($actor);
if (!in_array($statusCanonical, $visibleStatuses, true)) {
    report_detail_json_error(403, 'Anda tidak memiliki akses untuk melihat laporan ini.');
}

$reportKode = (string)($lap['kode'] ?? '');
$files = [];
if ($reportKode !== '' && report_detail_table_exists($conn, 'pelaporan_files')) {
    $fileCols = report_detail_columns($conn, 'pelaporan_files');
    if (isset($fileCols['kode'], $fileCols['id'])) {
        $fileFields = ['id', 'original_name', 'mime', 'size_bytes'];
        $selectFileFields = [];
        foreach ($fileFields as $field) {
            if (isset($fileCols[$field])) {
                $selectFileFields[] = "`{$field}`";
            }
        }
        $stmtFiles = $conn->prepare('SELECT ' . implode(',', $selectFileFields) . ' FROM pelaporan_files WHERE kode=? ORDER BY id ASC');
        if ($stmtFiles) {
            $stmtFiles->bind_param('s', $reportKode);
            $stmtFiles->execute();
            $resFiles = $stmtFiles->get_result();
            while ($file = $resFiles->fetch_assoc()) {
                $fileId = (int)($file['id'] ?? 0);
                if ($fileId <= 0) {
                    continue;
                }
                $files[] = [
                    'id' => $fileId,
                    'name' => (string)($file['original_name'] ?? 'Lampiran'),
                    'mime' => (string)($file['mime'] ?? ''),
                    'size' => report_detail_format_bytes($file['size_bytes'] ?? 0),
                    'view_url' => endpoint_url('attachment_download.php', ['id' => $fileId, 'mode' => 'view']),
                    'download_url' => endpoint_url('attachment_download.php', ['id' => $fileId, 'mode' => 'download']),
                ];
            }
        }
    }
}

$history = [];
if ($reportKode !== '' && report_detail_table_exists($conn, 'pelaporan_log')) {
    $logCols = report_detail_columns($conn, 'pelaporan_log');
    if (isset($logCols['kode'])) {
        $logFields = ['status_from', 'status_to', 'note', 'user_name', 'created_at'];
        $selectLogFields = [];
        foreach ($logFields as $field) {
            if (isset($logCols[$field])) {
                $selectLogFields[] = "`{$field}`";
            }
        }
        if (!empty($selectLogFields)) {
            $stmtLogs = $conn->prepare('SELECT ' . implode(',', $selectLogFields) . ' FROM pelaporan_log WHERE kode=? ORDER BY created_at ASC, id ASC');
            if ($stmtLogs) {
                $stmtLogs->bind_param('s', $reportKode);
                $stmtLogs->execute();
                $resLogs = $stmtLogs->get_result();
                while ($log = $resLogs->fetch_assoc()) {
                    $fromRaw = trim((string)($log['status_from'] ?? ''));
                    $toRaw = trim((string)($log['status_to'] ?? ''));
                    $history[] = [
                        'from' => $fromRaw !== '' ? pelaporan_status_label($fromRaw) : 'Pengaduan Masuk',
                        'to' => $toRaw !== '' ? pelaporan_status_label($toRaw) : 'Pengaduan Masuk',
                        'note' => trim((string)($log['note'] ?? '')),
                        'user' => trim((string)($log['user_name'] ?? '')) ?: 'Sistem/Publik',
                        'created_at' => report_detail_datetime($log['created_at'] ?? null),
                    ];
                }
            }
        }
    }
}

$anonim = isset($lap['anonim']) ? (int)$lap['anonim'] === 1 : false;
$tlStatus = trim((string)($lap['tl_status'] ?? ''));
$tlNote = trim((string)($lap['tl_catatan'] ?? ''));
$tlUpdatedAt = report_detail_datetime($lap['tl_updated_at'] ?? null);
$tlUpdatedName = trim((string)($lap['tl_updated_name'] ?? ''));

echo json_encode([
    'ok' => true,
    'report' => [
        'id' => (int)($lap['id'] ?? 0),
        'kode' => $reportKode,
        'kategori' => (string)($lap['kategori'] ?? '-'),
        'judul' => trim((string)($lap['judul'] ?? '')),
        'isi' => (string)($lap['isi'] ?? ''),
        'created_at' => report_detail_datetime($lap['created_at'] ?? ($lap['tanggal'] ?? null)),
        'status' => [
            'raw' => (string)($lap['status'] ?? ''),
            'label' => pelaporan_status_label((string)($lap['status'] ?? '')),
            'badge' => pelaporan_status_badge((string)($lap['status'] ?? '')),
            'description' => pelaporan_status_description((string)($lap['status'] ?? '')),
        ],
        'pelapor' => $anonim ? 'Anonim' : 'Identitas pelapor tidak ditampilkan di modal ini.',
        'tl' => [
            'status' => $tlStatus !== '' ? $tlStatus : 'Belum TL',
            'due_date' => report_detail_datetime($lap['tl_due_date'] ?? null),
            'note' => $tlNote,
            'updated_at' => $tlUpdatedAt,
            'updated_by' => $tlUpdatedName,
        ],
        'lampiran' => $files,
        'riwayat' => $history,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
