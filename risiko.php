<?php

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once 'db/koneksi.php';

if(!isset($_SESSION['user'])) header('Location: ' . route_url('login'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate($_POST['csrf'] ?? ''); }



function e($value): string {

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}



// --- Modul Risiko Kepatuhan ---

$risiko = query("SELECT * FROM risiko ORDER BY id DESC");

$risiko_search = isset($_GET['risiko_search']) ? strtolower($_GET['risiko_search']) : '';

$risiko_tingkat = isset($_GET['risiko_tingkat']) ? $_GET['risiko_tingkat'] : '';

$risiko_status = isset($_GET['risiko_status']) ? $_GET['risiko_status'] : '';

$risiko_filtered = array_filter($risiko, function($item) use ($risiko_search, $risiko_tingkat, $risiko_status) {

    $match = true;

    if ($risiko_search) {

        $match = strpos(strtolower($item['nama']), $risiko_search) !== false;

    }

    if ($risiko_tingkat && $risiko_tingkat !== 'all') {

        $match = $match && ($item['tingkat'] === $risiko_tingkat);

    }

    if ($risiko_status && $risiko_status !== 'all') {

        $match = $match && ($item['status'] === $risiko_status);

    }

    return $match;

});

$tingkat_list = ['Tinggi', 'Sedang', 'Rendah'];

$status_risiko_list = ['Aktif', 'Tidak Aktif'];

$risiko_edit_mode = false;

$risiko_edit_data = ['id'=>'','nama'=>'','tingkat'=>'','status'=>''];

if(isset($_GET['edit_risiko'])) {

    $risiko_edit_mode = true;

    $id = intval($_GET['edit_risiko']);

    if ($stmt = $conn->prepare("SELECT * FROM risiko WHERE id=?")) {

        $stmt->bind_param("i", $id);

        $ok = $stmt->execute();
        $res = $stmt->get_result();

        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $stmt->close();

        if ($data) $risiko_edit_data = $data[0];

    }

}

if(isset($_POST['tambah_risiko'])) {

    $nama = mysqli_real_escape_string($conn, $_POST['nama_risiko']);

    $tingkat = mysqli_real_escape_string($conn, $_POST['tingkat_risiko']);

    $status = mysqli_real_escape_string($conn, $_POST['status_risiko']);
    $ok = false;
    if ($stmt = $conn->prepare("INSERT INTO risiko (nama, tingkat, status) VALUES (?, ?, ?)")) {

        $stmt->bind_param("sss", $nama, $tingkat, $status);

        $ok = $stmt->execute();
        $stmt->close();

    }

    if ($ok) {
        $newId = (int)$conn->insert_id;
        audit_log($conn, 'create', 'risiko', $newId, [
            'nama' => $nama,
            'tingkat' => $tingkat,
            'status' => $status,
        ]);
    }
    header('Location: ' . route_url('risiko')); exit;
}


if(isset($_POST['edit_risiko'])) {

    $id = intval($_POST['id_risiko']);

    $nama = mysqli_real_escape_string($conn, $_POST['nama_risiko']);

    $tingkat = mysqli_real_escape_string($conn, $_POST['tingkat_risiko']);

    $status = mysqli_real_escape_string($conn, $_POST['status_risiko']);
    $ok = false;

    if ($stmt = $conn->prepare("UPDATE risiko SET nama=?, tingkat=?, status=? WHERE id=?")) {

        $stmt->bind_param("sssi", $nama, $tingkat, $status, $id);

        $ok = $stmt->execute();

        $stmt->close();

    }

    if ($ok) {
        audit_log($conn, 'update', 'risiko', $id, [
            'nama' => $nama,
            'tingkat' => $tingkat,
            'status' => $status,
        ]);
    }
    header('Location: ' . route_url('risiko')); exit;

}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_risiko'])) {

    $id = intval($_POST['delete_risiko']);
    $ok = false;
    if ($stmt = $conn->prepare("DELETE FROM risiko WHERE id=?")) {

        $stmt->bind_param("i", $id);

        $ok = $stmt->execute();

        $stmt->close();

    }

    if ($ok) {
        audit_log($conn, 'delete', 'risiko', $id);
    }
    header('Location: ' . route_url('risiko')); exit;

}

// Export Excel (HTML table)

if(isset($_GET['export_excel'])) {

    header('Content-Type: application/vnd.ms-excel');

    header('Content-Disposition: attachment; filename=risiko.xls');

    echo "<table border='1' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'>";

    echo "<tr style='background:#e6f7d4;font-weight:bold;'><th>ID</th><th>Nama</th><th>Tingkat</th><th>Status</th></tr>";

    foreach($risiko_filtered as $row) {

        echo "<tr><td>".e($row['id'])."</td><td>".e($row['nama'])."</td><td>".e($row['tingkat'])."</td><td>".e($row['status'])."</td></tr>";

    }

    echo "</table>";

    exit;

}

// Export Word

if(isset($_GET['export_word'])) {

    header('Content-Type: application/msword');

    header('Content-Disposition: attachment; filename=risiko.doc');

    echo "<html><body><h2 style='font-family:sans-serif;'>Data Risiko Kepatuhan</h2><table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'><tr style='background:#e6f7d4;font-weight:bold;'><th>ID</th><th>Nama</th><th>Tingkat</th><th>Status</th></tr>";

    foreach($risiko_filtered as $row) {

        echo "<tr><td>".e($row['id'])."</td><td>".e($row['nama'])."</td><td>".e($row['tingkat'])."</td><td>".e($row['status'])."</td></tr>";

    }

    echo '</table><footer class="text-center py-3 small text-muted">&copy; ' . date('Y') . ' SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer></body></html>';

    exit;

}

// Export PDF (HTML to PDF via print dialog)

if(isset($_GET['export_pdf'])) {

    echo "<html><head><title>Export PDF</title><style>body{font-family:sans-serif;}table{border-collapse:collapse;font-size:14px;}th,td{border:1px solid #333;padding:6px;}th{background:#e6f7d4;}</style>

</head><body onload='window.print()'>";

    echo "<h2>Data Risiko Kepatuhan</h2><table><tr><th>ID</th><th>Nama</th><th>Tingkat</th><th>Status</th></tr>";

    foreach($risiko_filtered as $row) {

        echo "<tr><td>".e($row['id'])."</td><td>".e($row['nama'])."</td><td>".e($row['tingkat'])."</td><td>".e($row['status'])."</td></tr>";

    }

    echo '</table><footer class="text-center py-3 small text-muted">&copy; ' . date('Y') . ' SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer></body></html>';

    exit;

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Manajemen Risiko Kepatuhan</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/ui_base.css'), ENT_QUOTES, 'UTF-8') ?>">

  <?php include __DIR__ . '/includes/head_favicon.php'; ?>

</head>

<body>

<?php include 'navbar.php'; ?>

<h2>Manajemen Risiko Kepatuhan</h2>

<div style="margin-bottom:24px;">

    <form method="post" data-loading="1" style="background:#e6f7d4; padding:12px 16px; border-radius:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">

        <?= csrf_field() ?>

        <input type="hidden" name="id_risiko" value="<?= htmlspecialchars($risiko_edit_data['id']) ?>">

        <label class="sr-only" for="nama_risiko">Nama Risiko</label>

        <input id="nama_risiko" type="text" name="nama_risiko" placeholder="Nama Risiko" value="<?= htmlspecialchars($risiko_edit_data['nama']) ?>" required>

        <label class="sr-only" for="tingkat_risiko">Tingkat Risiko</label>

        <select id="tingkat_risiko" name="tingkat_risiko" required>

            <option value="">Pilih Tingkat</option>

            <?php foreach($tingkat_list as $tingkat): ?>

                <option value="<?= e($tingkat) ?>" <?= $risiko_edit_data['tingkat']===$tingkat?'selected':'' ?>><?= e($tingkat) ?></option>

            <?php endforeach; ?>

        </select>

        <label class="sr-only" for="status_risiko">Status Risiko</label>

        <select id="status_risiko" name="status_risiko" required>

            <option value="">Pilih Status</option>

            <?php foreach($status_risiko_list as $stat): ?>

                <option value="<?= e($stat) ?>" <?= $risiko_edit_data['status']===$stat?'selected':'' ?>><?= e($stat) ?></option>

            <?php endforeach; ?>

        </select>

        <?php if($risiko_edit_mode): ?>

            <button type="submit" name="edit_risiko" class="btn-loading" data-loading="1">Simpan Perubahan</button>

            <a href="risiko.php" style="color:#008a43; margin-left:8px;">Batal</a>

        <?php else: ?>

            <button type="submit" name="tambah_risiko" class="btn-loading" data-loading="1">Tambah Risiko</button>

        <?php endif; ?>

    </form>

</div>

<hr>

<form method="get" style="margin-bottom:16px;">

    <label class="sr-only" for="risiko_search">Cari nama risiko</label>

    <input id="risiko_search" type="text" name="risiko_search" placeholder="Cari nama risiko..." value="<?= htmlspecialchars($risiko_search) ?>">

    <label class="sr-only" for="risiko_tingkat">Filter Tingkat</label>

    <select id="risiko_tingkat" name="risiko_tingkat">

        <option value="all">Semua Tingkat</option>

        <?php foreach($tingkat_list as $tingkat): ?>

            <option value="<?= e($tingkat) ?>" <?= $risiko_tingkat===$tingkat?'selected':'' ?>><?= e($tingkat) ?></option>

        <?php endforeach; ?>

    </select>

    <label class="sr-only" for="risiko_status">Filter Status</label>

    <select id="risiko_status" name="risiko_status">

        <option value="all">Semua Status</option>

        <?php foreach($status_risiko_list as $stat): ?>

            <option value="<?= e($stat) ?>" <?= $risiko_status===$stat?'selected':'' ?>><?= e($stat) ?></option>

        <?php endforeach; ?>

    </select>

    <button type="submit">Cari</button>

</form>

<div style="margin-bottom:16px;">

    <a href="?export_excel=1" class="export-btn nav-btn btn-loading" data-loading="1" target="_blank">Export Excel</a>

    <a href="?export_word=1" class="export-btn nav-btn btn-loading" data-loading="1" target="_blank">Export Word</a>

    <a href="?export_pdf=1" class="export-btn nav-btn btn-loading" data-loading="1" target="_blank">Export PDF</a>

</div>

<div class="table-wrap">

<table border="1" cellpadding="8" cellspacing="0">

    <tr style="background:#f0f0f0;">

        <th>ID</th>

        <th>Nama Risiko</th>

        <th>Tingkat</th>

        <th>Status</th>

        <th>Aksi</th>

    </tr>

    <?php if(count($risiko_filtered) === 0): ?>

        <tr><td colspan="5"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>

    <?php else: ?>

        <?php foreach($risiko_filtered as $row): ?>

            <tr>

                <td><?= e($row['id']) ?></td>

                <td><?= e($row['nama']) ?></td>

                <td><?= e($row['tingkat']) ?></td>

                <td><?= e($row['status']) ?></td>

                <td>

                    <button class="action-btn edit" onclick="window.location.href='?edit_risiko=<?= (int)$row['id'] ?>'">Edit</button>

                    <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">

                        <?= csrf_field() ?>

                        <input type="hidden" name="delete_risiko" value="<?= (int)$row['id'] ?>">

                        <button class="action-btn delete" type="submit">Hapus</button>

                    </form>

                </td>

            </tr>

        <?php endforeach; ?>

    <?php endif; ?>

</table>

</div>

<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>

</body>

</html>
