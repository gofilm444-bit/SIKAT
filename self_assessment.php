<?php

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once 'db/koneksi.php';

if(!isset($_SESSION['user'])) header('Location: login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate($_POST['csrf'] ?? ''); }



function e($value): string {

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}



// --- Modul Self-Assessment ---

$self_assessment = query("SELECT * FROM self_assessment ORDER BY id DESC");

$self_search = isset($_GET['self_search']) ? strtolower($_GET['self_search']) : '';

$self_status = isset($_GET['self_status']) ? $_GET['self_status'] : '';

$self_filtered = array_filter($self_assessment, function($item) use ($self_search, $self_status) {

    $match = true;

    if ($self_search) {

        $match = strpos(strtolower($item['nama']), $self_search) !== false;

    }

    if ($self_status && $self_status !== 'all') {

        $match = $match && ($item['status'] === $self_status);

    }

    return $match;

});

$self_status_list = ['Selesai', 'Proses', 'Belum Mulai'];

$self_edit_mode = false;

$self_edit_data = ['id'=>'','nama'=>'','status'=>'','tanggal'=>''];

if(isset($_GET['edit_self'])) {

    $self_edit_mode = true;

    $id = intval($_GET['edit_self']);

    if ($stmt = $conn->prepare("SELECT * FROM self_assessment WHERE id=?")) {

        $stmt->bind_param("i", $id);

        $stmt->execute();

        $res = $stmt->get_result();

        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        $stmt->close();

        if ($data) $self_edit_data = $data[0];

    }

}

if(isset($_POST['tambah_self'])) {

    $nama = mysqli_real_escape_string($conn, $_POST['nama_self']);

    $status = mysqli_real_escape_string($conn, $_POST['status_self']);

    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal_self']);
    $ok = false;

    if ($stmt = $conn->prepare("INSERT INTO self_assessment (nama, status, tanggal) VALUES (?, ?, ?)")) {

        $stmt->bind_param("sss", $nama, $status, $tanggal);

        $ok = $stmt->execute();

        $stmt->close();

    }

    if ($ok) {
        $newId = (int)$conn->insert_id;
        audit_log($conn, 'create', 'self_assessment', $newId, [
            'nama' => $nama,
            'status' => $status,
            'tanggal' => $tanggal,
        ]);
    }
    header('Location: self_assessment.php'); exit;
}


if(isset($_POST['edit_self'])) {

    $id = intval($_POST['id_self']);

    $nama = mysqli_real_escape_string($conn, $_POST['nama_self']);

    $status = mysqli_real_escape_string($conn, $_POST['status_self']);

    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal_self']);
    $ok = false;

    if ($stmt = $conn->prepare("UPDATE self_assessment SET nama=?, status=?, tanggal=? WHERE id=?")) {

        $stmt->bind_param("sssi", $nama, $status, $tanggal, $id);

        $ok = $stmt->execute();

        $stmt->close();

    }

    if ($ok) {
        audit_log($conn, 'update', 'self_assessment', $id, [
            'nama' => $nama,
            'status' => $status,
            'tanggal' => $tanggal,
        ]);
    }
    header('Location: self_assessment.php'); exit;
}


if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_self'])) {

    $id = intval($_POST['delete_self']);
    $ok = false;

    if ($stmt = $conn->prepare("DELETE FROM self_assessment WHERE id=?")) {

        $stmt->bind_param("i", $id);

        $ok = $stmt->execute();

        $stmt->close();

    }

    if ($ok) {
        audit_log($conn, 'delete', 'self_assessment', $id);
    }
    header('Location: self_assessment.php'); exit;
}


// Export Word

if(isset($_GET['export_word'])) {

    header('Content-Type: application/msword');

    header('Content-Disposition: attachment; filename=self_assessment.doc');

    echo "<html><body><h2 style='font-family:sans-serif;'>Data Self-Assessment</h2><table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'><tr style='background:#e6f7d4;font-weight:bold;'><th>ID</th><th>Nama</th><th>Status</th><th>Tanggal</th></tr>";

    foreach($self_filtered as $row) {

        echo "<tr><td>".e($row['id'])."</td><td>".e($row['nama'])."</td><td>".e($row['status'])."</td><td>".e($row['tanggal'])."</td></tr>";

    }

    echo '</table><footer class="text-center py-3 small text-muted">&copy; ' . date('Y') . ' SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer></body></html>';

    exit;

}

// Export PDF (HTML to PDF via print dialog)

if(isset($_GET['export_pdf'])) {

    echo "<html><head><title>Export PDF</title><style>body{font-family:sans-serif;}table{border-collapse:collapse;font-size:14px;}th,td{border:1px solid #333;padding:6px;}th{background:#e6f7d4;}</style>

</head><body onload='window.print()'>";

    echo "<h2>Data Self-Assessment</h2><table><tr><th>ID</th><th>Nama</th><th>Status</th><th>Tanggal</th></tr>";

    foreach($self_filtered as $row) {

        echo "<tr><td>".e($row['id'])."</td><td>".e($row['nama'])."</td><td>".e($row['status'])."</td><td>".e($row['tanggal'])."</td></tr>";

    }

    echo '</table><footer class="text-center py-3 small text-muted">&copy; ' . date('Y') . ' SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer></body></html>';

    exit;

}

// Export Excel (HTML table)

if(isset($_GET['export_excel'])) {

    header('Content-Type: application/vnd.ms-excel');

    header('Content-Disposition: attachment; filename=self_assessment.xls');

    echo "<table border='1' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'>";

    echo "<tr style='background:#e6f7d4;font-weight:bold;'><th>ID</th><th>Nama</th><th>Status</th><th>Tanggal</th></tr>";

    foreach($self_filtered as $row) {

        echo "<tr><td>".e($row['id'])."</td><td>".e($row['nama'])."</td><td>".e($row['status'])."</td><td>".e($row['tanggal'])."</td></tr>";

    }

    echo "</table>";

    exit;

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Self-Assessment</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="assets/css/ui_base.css">

  <?php include __DIR__ . '/includes/head_favicon.php'; ?>

</head>

<body>

<?php include 'navbar.php'; ?>

<h2>Penilaian Self-Assessment</h2>

<div style="margin-bottom:24px;">

    <form method="post" data-loading="1" style="background:#e6f7d4; padding:12px 16px; border-radius:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">

        <?= csrf_field() ?>

        <input type="hidden" name="id_self" value="<?= htmlspecialchars($self_edit_data['id']) ?>">

        <label class="sr-only" for="nama_self">Nama Assessment</label>

        <input id="nama_self" type="text" name="nama_self" placeholder="Nama Assessment" value="<?= htmlspecialchars($self_edit_data['nama']) ?>" required>

        <label class="sr-only" for="status_self">Status Assessment</label>

        <select id="status_self" name="status_self" required>

            <option value="">Pilih Status</option>

            <?php foreach($self_status_list as $stat): ?>

                <option value="<?= e($stat) ?>" <?= $self_edit_data['status']===$stat?'selected':'' ?>><?= e($stat) ?></option>

            <?php endforeach; ?>

        </select>

        <label class="sr-only" for="tanggal_self">Tanggal Assessment</label>

        <input id="tanggal_self" type="date" name="tanggal_self" value="<?= htmlspecialchars($self_edit_data['tanggal']) ?>" required>

        <?php if($self_edit_mode): ?>

            <button type="submit" name="edit_self" class="btn-loading" data-loading="1">Simpan Perubahan</button>

            <a href="self_assessment.php" style="color:#008a43; margin-left:8px;">Batal</a>

        <?php else: ?>

            <button type="submit" name="tambah_self" class="btn-loading" data-loading="1">Tambah Assessment</button>

        <?php endif; ?>

    </form>

</div>

<hr>

<form method="get" style="margin-bottom:16px;">

    <label class="sr-only" for="self_search">Cari nama assessment</label>

    <input id="self_search" type="text" name="self_search" placeholder="Cari nama assessment..." value="<?= htmlspecialchars($self_search) ?>">

    <label class="sr-only" for="self_status">Filter Status</label>

    <select id="self_status" name="self_status">

        <option value="all">Semua Status</option>

        <?php foreach($self_status_list as $stat): ?>

            <option value="<?= e($stat) ?>" <?= $self_status===$stat?'selected':'' ?>><?= e($stat) ?></option>

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

        <th>Nama Assessment</th>

        <th>Status</th>

        <th>Tanggal</th>

        <th>Aksi</th>

    </tr>

    <?php if(count($self_filtered) === 0): ?>

        <tr><td colspan="5"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>

    <?php else: ?>

        <?php foreach($self_filtered as $row): ?>

            <tr>

                <td><?= e($row['id']) ?></td>

                <td><?= e($row['nama']) ?></td>

                <td><?= e($row['status']) ?></td>

                <td><?= e($row['tanggal']) ?></td>

                <td>

                    <button class="action-btn edit" onclick="window.location.href='?edit_self=<?= (int)$row['id'] ?>'">Edit</button>

                    <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">

                        <?= csrf_field() ?>

                        <input type="hidden" name="delete_self" value="<?= (int)$row['id'] ?>">

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
