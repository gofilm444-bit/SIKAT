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

// --- Modul: Manajemen Kebijakan & Regulasi ---
$kebijakan = query("SELECT * FROM kebijakan ORDER BY id ASC");
if (!is_array($kebijakan)) $kebijakan = [];
$search = isset($_GET['search']) ? strtolower($_GET['search']) : '';
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filtered = array_filter($kebijakan, function($item) use ($search, $filter_kategori) {
    $match = true;
    if ($search) {
        $match = strpos(strtolower($item['judul']), $search) !== false;
    }
    if ($filter_kategori && $filter_kategori !== 'all') {
        $match = $match && ($item['kategori'] === $filter_kategori);
    }
    return $match;
});
$kategori_list = array_unique(array_column($kebijakan, 'kategori'));
// Notifikasi flash message
if (!isset($_SESSION['flash'])) $_SESSION['flash'] = '';
$flash = $_SESSION['flash'];
if (is_array($flash)) {
    $flash = implode(' ', array_map(function ($v) {
        if (is_scalar($v) || $v === null) {
            return (string)$v;
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }, $flash));
}
$_SESSION['flash'] = '';
// Export CSV
if(isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="kebijakan.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Judul', 'Kategori', 'Tahun', 'Status']);
    foreach($filtered as $row) {
        fputcsv($output, [$row['id'], $row['judul'], $row['kategori'], $row['tahun'], $row['status']]);
    }
    fclose($output);
    exit;
}
// Export Excel (HTML table)
if(isset($_GET['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="kebijakan.xls"');
    echo "<table border='1' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'>";
    echo "<tr style='background:#e6f7d4;font-weight:bold;'><th>ID</th><th>Judul</th><th>Kategori</th><th>Tahun</th><th>Status</th></tr>";
    foreach($filtered as $row) {
        echo "<tr><td>".e($row['id'])."</td><td>".e($row['judul'])."</td><td>".e($row['kategori'])."</td><td>".e($row['tahun'])."</td><td>".e($row['status'])."</td></tr>";
    }
    echo "</table>";
    exit;
}
// Export Word
if(isset($_GET['export_word'])) {
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="kebijakan.doc"');
    echo "<html><body><h2 style='font-family:sans-serif;'>Data Kebijakan & Regulasi</h2><table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;font-size:14px;'><tr style='background:#e6f7d4;font-weight:bold;'><th>ID</th><th>Judul</th><th>Kategori</th><th>Tahun</th><th>Status</th></tr>";
    foreach($filtered as $row) {
        echo "<tr><td>".e($row['id'])."</td><td>".e($row['judul'])."</td><td>".e($row['kategori'])."</td><td>".e($row['tahun'])."</td><td>".e($row['status'])."</td></tr>";
    }
    echo '</table><footer class="text-center py-3 small text-muted">&copy; ' . date('Y') . ' SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer></body></html>';
    exit;
}
// Export PDF (HTML to PDF via print dialog)
if(isset($_GET['export_pdf'])) {
    echo "<html><head><title>Export PDF</title><style>body{font-family:sans-serif;}table{border-collapse:collapse;font-size:14px;}th,td{border:1px solid #333;padding:6px;}th{background:#e6f7d4;}</style>
</head><body onload='window.print()'>";
    echo "<h2>Data Kebijakan & Regulasi</h2><table><tr><th>ID</th><th>Judul</th><th>Kategori</th><th>Tahun</th><th>Status</th></tr>";
    foreach($filtered as $row) {
        echo "<tr><td>".e($row['id'])."</td><td>".e($row['judul'])."</td><td>".e($row['kategori'])."</td><td>".e($row['tahun'])."</td><td>".e($row['status'])."</td></tr>";
    }
    echo '</table><footer class="text-center py-3 small text-muted">&copy; ' . date('Y') . ' SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer></body></html>';
    exit;
}
// CRUD
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_kebijakan'])) {
    $id = intval($_POST['delete_kebijakan']);
    $res = false;
    if ($stmt = $conn->prepare("DELETE FROM kebijakan WHERE id=?")) {
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
    }
    if ($res) {
        audit_log($conn, 'delete', 'kebijakan', $id);
    }
    $_SESSION['flash'] = $res ? 'Data berhasil dihapus.' : 'Gagal menghapus data!';
    header('Location: kebijakan.php'); exit;
}
$status_list = ['Aktif', 'Tidak Aktif'];
if(isset($_POST['tambah_kebijakan'])) {
    $judul = trim($_POST['judul_kebijakan']);
    $kategori = trim($_POST['kategori_kebijakan']);
    $tahun = intval($_POST['tahun_kebijakan']);
    $status = trim($_POST['status_kebijakan']);
    if($judul==='' || $kategori==='' || $tahun<2000 || $tahun>2100 || $status==='') {
        $_SESSION['flash'] = 'Data tidak valid!';
    } else {
        $res = false;
        if ($stmt = $conn->prepare("INSERT INTO kebijakan (judul, kategori, tahun, status) VALUES (?, ?, ?, ?)")) {
            $stmt->bind_param("ssis", $judul, $kategori, $tahun, $status);
            $res = $stmt->execute();
            $stmt->close();
        }
        if ($res) {
            $newId = (int)$conn->insert_id;
            audit_log($conn, 'create', 'kebijakan', $newId, [
                'judul' => $judul,
                'kategori' => $kategori,
                'tahun' => $tahun,
                'status' => $status,
            ]);
        }
        $_SESSION['flash'] = $res ? 'Data berhasil ditambah.' : 'Gagal menambah data!';
    }
    header('Location: kebijakan.php'); exit;
}
if(isset($_POST['edit_kebijakan'])) {
    $id = intval($_POST['id_kebijakan']);
    $judul = trim($_POST['judul_kebijakan']);
    $kategori = trim($_POST['kategori_kebijakan']);
    $tahun = intval($_POST['tahun_kebijakan']);
    $status = trim($_POST['status_kebijakan']);
    if($judul==='' || $kategori==='' || $tahun<2000 || $tahun>2100 || $status==='') {
        $_SESSION['flash'] = 'Data tidak valid!';
    } else {
        $res = false;
        if ($stmt = $conn->prepare("UPDATE kebijakan SET judul=?, kategori=?, tahun=?, status=? WHERE id=?")) {
            $stmt->bind_param("ssisi", $judul, $kategori, $tahun, $status, $id);
            $res = $stmt->execute();
            $stmt->close();
        }
        if ($res) {
            audit_log($conn, 'update', 'kebijakan', $id, [
                'judul' => $judul,
                'kategori' => $kategori,
                'tahun' => $tahun,
                'status' => $status,
            ]);
        }
        $_SESSION['flash'] = $res ? 'Data berhasil diubah.' : 'Gagal mengubah data!';
    }
    header('Location: kebijakan.php'); exit;
}
$edit_mode = false;
$edit_data = ['id'=>'','judul'=>'','kategori'=>'','tahun'=>'', 'status'=>''];
if(isset($_GET['edit_kebijakan'])) {
    $edit_mode = true;
    $id = intval($_GET['edit_kebijakan']);
    if ($stmt = $conn->prepare("SELECT * FROM kebijakan WHERE id=?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        if ($row && count($row)) $edit_data = $row[0];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kebijakan & Regulasi</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/ui_base.css">
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include 'navbar.php'; ?>
<h2>Manajemen Kebijakan & Regulasi</h2>
<?php
  $flash_messages = [];
  if ($flash) {
    $flash_messages[] = ['type' => 'info', 'message' => (string)$flash];
  }
  include __DIR__ . '/includes/flash.php';
?>
<div style="margin-bottom:16px;">
    <a href="?export_excel=1" class="export-btn nav-btn btn-loading" data-loading="1" target="_blank">Export Excel</a>
    <a href="?export_word=1" class="export-btn nav-btn btn-loading" data-loading="1" target="_blank">Export Word</a>
    <a href="?export_pdf=1" class="export-btn nav-btn btn-loading" data-loading="1" target="_blank">Export PDF</a>
</div>
<div style="margin-bottom:24px;">
    <form method="post" data-loading="1" style="background:#e6f7d4; padding:8px 16px; border-radius:6px; display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="id_kebijakan" value="<?= htmlspecialchars($edit_data['id']) ?>">
        <label class="sr-only" for="judul_kebijakan">Judul Kebijakan</label>
        <input id="judul_kebijakan" type="text" name="judul_kebijakan" placeholder="Judul Kebijakan" value="<?= htmlspecialchars($edit_data['judul']) ?>" required>
        <label class="sr-only" for="kategori_kebijakan">Kategori</label>
        <input id="kategori_kebijakan" type="text" name="kategori_kebijakan" placeholder="Kategori" value="<?= htmlspecialchars($edit_data['kategori']) ?>" required>
        <label class="sr-only" for="tahun_kebijakan">Tahun</label>
        <input id="tahun_kebijakan" type="number" name="tahun_kebijakan" placeholder="Tahun" value="<?= htmlspecialchars($edit_data['tahun']) ?>" min="2000" max="2100" required>
        <label class="sr-only" for="status_kebijakan">Status</label>
        <select id="status_kebijakan" name="status_kebijakan" required>
            <option value="">Pilih Status</option>
            <?php foreach($status_list as $stat): ?>
                <option value="<?= e($stat) ?>" <?= (isset($edit_data['status']) && $edit_data['status']===$stat)?'selected':'' ?>><?= e($stat) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if($edit_mode): ?>
            <button type="submit" name="edit_kebijakan" class="btn-loading" data-loading="1">Simpan Perubahan</button>
            <a href="kebijakan.php" style="color:#008a43; margin-left:8px;">Batal</a>
        <?php else: ?>
            <button type="submit" name="tambah_kebijakan" class="btn-loading" data-loading="1">Tambah Kebijakan</button>
        <?php endif; ?>
    </form>
</div>
<hr>
<form method="get" style="margin-bottom:16px;">
    <label class="sr-only" for="search">Cari judul/kategori</label>
    <input id="search" type="text" name="search" placeholder="Cari judul/kategori..." value="<?= htmlspecialchars($search) ?>">
    <label class="sr-only" for="kategori">Filter Kategori</label>
    <select id="kategori" name="kategori">
        <option value="all">Semua Kategori</option>
        <?php foreach($kategori_list as $kat): ?>
            <option value="<?= htmlspecialchars($kat) ?>" <?= $filter_kategori===$kat?'selected':'' ?>><?= htmlspecialchars($kat) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Cari</button>
</form>
<div class="table-wrap">
<table border="1" cellpadding="8" cellspacing="0">
    <tr style="background:#f0f0f0;">
        <th>ID</th>
        <th>Judul</th>
        <th>Kategori</th>
        <th>Tahun</th>
        <th>Status</th>
        <th>Aksi</th>
    </tr>
    <?php if(count($filtered) === 0): ?>
        <tr><td colspan="6"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
    <?php else: ?>
        <?php foreach($filtered as $row): ?>
            <tr>
                <td><?= e($row['id']) ?></td>
                <td><?= e($row['judul']) ?></td>
                <td><?= e($row['kategori']) ?></td>
                <td><?= e($row['tahun']) ?></td>
                <td><?= e($row['status']) ?></td>
                <td>
                    <button class="action-btn edit" onclick="window.location.href='?edit_kebijakan=<?= (int)$row['id'] ?>'">Edit</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_kebijakan" value="<?= (int)$row['id'] ?>">
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
