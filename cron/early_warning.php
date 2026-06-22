<?php
require_once __DIR__ . '/../bootstrap.php';
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only"; exit(1);
}

date_default_timezone_set('Asia/Jayapura');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../early_warning_helpers.php';

function ew_level(DateTime $today, DateTime $deadline, int $warnThresholdDays = 2, int $progressBoost = 0): array {
    $diff = (int)$today->diff($deadline)->format('%r%a');
    [$baseCode, $levelDesc] = early_warning_base_level($diff, $warnThresholdDays);
    $levelCode = early_warning_adjust_code($baseCode, $progressBoost);
    $levelName = early_warning_label($levelCode, $warnThresholdDays);
    return [$levelCode, $levelName, $levelDesc, $diff];
}

function build_recipient_list(mysqli $conn, int $reviuId): array {
    $emails = [];
    if ($stmt = $conn->prepare("SELECT nama, email FROM reviu_penugasan WHERE reviu_id=? AND email <> ''")) {
        $stmt->bind_param("i", $reviuId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $email = trim($row['email']);
            if ($email === '') continue;
            $emails[strtolower($email)] = ['email' => $email, 'nama' => $row['nama'] ?? ''];
        }
        $stmt->close();
    }
    foreach (mailer_admin_list($conn) as $admin) {
        $email = trim($admin['email']);
        if ($email === '') continue;
        if (!isset($emails[strtolower($email)])) {
            $emails[strtolower($email)] = $admin;
        }
    }
    return array_values($emails);
}

function format_days_label(int $diff): string {
    if ($diff > 0) return 'H-'.$diff;
    if ($diff === 0) return 'H-0';
    return 'H+'.abs($diff);
}

$today = new DateTime('today');

$sql = "SELECT r.id, r.kode, r.status, r.tgl_deadline, u.nama AS unit_nama, j.nama AS jenis_nama
        FROM reviu r
        JOIN unit_kerja u ON u.id = r.unit_id
        JOIN jenis_reviu j ON j.id = r.jenis_id
        WHERE r.status NOT IN ('Selesai','Dibatalkan') AND r.tgl_deadline IS NOT NULL";

$res = $conn->query($sql);
if (!$res) {
    fwrite(STDERR, "Query error: {$conn->error}\n");
    exit(1);
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

$progressBoostByReviu = [];
if (!empty($rows)) {
    foreach ($rows as $row) {
        $progressBoostByReviu[(int)$row['id']] = 0;
    }
    $ids = array_values(array_filter(array_map('intval', array_keys($progressBoostByReviu)), static function ($v) {
        return $v > 0;
    }));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($docStmt = $conn->prepare("SELECT DISTINCT reviu_id FROM reviu_dokumen WHERE reviu_id IN ($placeholders)")) {
            $docStmt->bind_param($types, ...$ids);
            $docStmt->execute();
            $docRes = $docStmt->get_result();
            while ($docRes && ($docRow = $docRes->fetch_assoc())) {
                $rid = (int)$docRow['reviu_id'];
                if (isset($progressBoostByReviu[$rid])) {
                    $progressBoostByReviu[$rid]++;
                }
            }
            $docStmt->close();
        }

        if ($followStmt = $conn->prepare("SELECT DISTINCT reviu_id FROM reviu_laporan WHERE reviu_id IN ($placeholders) AND TRIM(tindak_lanjut) <> ''")) {
            $followStmt->bind_param($types, ...$ids);
            $followStmt->execute();
            $followRes = $followStmt->get_result();
            while ($followRes && ($followRow = $followRes->fetch_assoc())) {
                $rid = (int)$followRow['reviu_id'];
                if (isset($progressBoostByReviu[$rid])) {
                    $progressBoostByReviu[$rid]++;
                }
            }
            $followStmt->close();
        }

        foreach ($progressBoostByReviu as $rid => $current) {
            $progressBoostByReviu[$rid] = min(2, max(0, $current));
        }
    }
}

$totalChecked = 0;
$totalSent = 0;

foreach ($rows as $row) {
    $totalChecked++;
    try {
        $deadline = new DateTime($row['tgl_deadline']);
    } catch (Exception $e) {
        continue;
    }
    $reviuId = (int)$row['id'];
    $progressBoost = $progressBoostByReviu[$reviuId] ?? 0;
    [$levelCode, $levelName, $levelDesc, $diff] = ew_level($today, $deadline, 2, $progressBoost);
    if ($levelCode === 'green') {
        continue; // aman, tidak kirim
    }

    $ewRow = null;
    if ($stmt = $conn->prepare("SELECT last_level FROM reviu_early_warning WHERE reviu_id=? LIMIT 1")) {
        $stmt->bind_param("i", $reviuId);
        $stmt->execute();
        $ewRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if ($ewRow && $ewRow['last_level'] === $levelCode) {
        continue; // sudah dikirim untuk level ini
    }

    $recipients = build_recipient_list($conn, $reviuId);
    if (empty($recipients)) {
        fwrite(STDERR, "[WARN] Reviu {$row['kode']} tidak punya email penugasan.\n");
        continue;
    }

    $daysLabel = format_days_label($diff);
    $subject = "Early Warning ($levelName $daysLabel) - {$row['kode']}";
    $htmlBody = '<p>Halo rekan,<br>Berikut status early warning untuk jadwal reviu:</p>'
        .'<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:sans-serif;font-size:13px;">'
        .'<tr><th align="left">Kode Reviu</th><td>'.htmlspecialchars($row['kode']).'</td></tr>'
        .'<tr><th align="left">Unit</th><td>'.htmlspecialchars($row['unit_nama']).'</td></tr>'
        .'<tr><th align="left">Jenis</th><td>'.htmlspecialchars($row['jenis_nama']).'</td></tr>'
        .'<tr><th align="left">Status Saat Ini</th><td>'.htmlspecialchars($row['status']).'</td></tr>'
        .'<tr><th align="left">Tanggal Tenggat</th><td>'.htmlspecialchars($row['tgl_deadline']).' ('.$daysLabel.')</td></tr>'
        .'<tr><th align="left">Level Early Warning</th><td>'.htmlspecialchars($levelName.' - '.$levelDesc).'</td></tr>'
        .'</table>'
        .'<p>Mohon tindak lanjut agar reviu berjalan sesuai jadwal. Email ini dikirim otomatis oleh sistem SIKAT.</p>';

    $sent = mailer_send($recipients, $subject, $htmlBody);
    if ($sent) {
        $totalSent++;
        if ($stmt = $conn->prepare("INSERT INTO reviu_early_warning (reviu_id,last_level,notified_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE last_level=VALUES(last_level), notified_at=NOW()")) {
            $stmt->bind_param("is", $reviuId, $levelCode);
            $stmt->execute();
            $stmt->close();
        }
        echo "[OK] {$row['kode']} -> {$levelCode} ({$daysLabel})\n";
    } else {
        fwrite(STDERR, "[ERR] Gagal mengirim email untuk {$row['kode']}\n");
    }
}

printf("Selesai. Dicek: %d, notifikasi terkirim: %d\n", $totalChecked, $totalSent);

