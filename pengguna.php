<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit_log.php';
$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
$env = strtolower((string)$env);
if (in_array($env, ['local','dev','development'], true)) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}
require_once 'db/koneksi.php';
if(!isset($_SESSION['user'])) header('Location: login.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('[pengguna.php POST] ' . json_encode($_POST));
    csrf_validate($_POST['csrf'] ?? '');
}
$user = $_SESSION['user'];
if(!in_array($user['peran'], ['super_admin','admin'], true)) {
    echo '<div class="notif-flash" style="background:#ffe6e6;color:#d13438;">Akses hanya untuk Super Admin atau Admin SKI.</div>';
    exit;
}

$ROLE_OPTIONS = [
    'super_admin'          => 'Super Admin (IT)',
    'admin'                => 'Admin SKI',
    'auditor_ka'           => 'Auditor - Kepala SKI',
    'auditor_staff'        => 'Auditor - Staf SKI',
    'auditor'              => 'Auditor',
    'auditee_direktur'     => 'Auditee - Direktur',
    'auditee_wadir1'       => 'Auditee - Wadir I',
    'auditee_wadir2'       => 'Auditee - Wadir II',
    'auditee_wadir3'       => 'Auditee - Wadir III',
    'auditee_adav'         => 'Auditee - ADAV',
    'auditee_adum'         => 'Auditee - ADUM',
    'auditee_pmpp'         => 'Auditee - PMPP',
    'auditee_pppm'         => 'Auditee - PPPM',
    'auditee_itp'          => 'Auditee - ITP',
    'auditee_labterpadu'   => 'Auditee - Lab Terpadu',
    'auditee_perpustakaan' => 'Auditee - Perpustakaan',
    'auditee_keperawatan'  => 'Auditee - Keperawatan',
    'auditee_kebidanan'    => 'Auditee - Kebidanan',
    'auditee_gizi'         => 'Auditee - Gizi',
    'auditee_kesling'      => 'Auditee - Kesling',
    'auditee_tlm'          => 'Auditee - TLM'
];

function role_label(string $role, array $map): string {
    return $map[$role] ?? ucfirst(str_replace('_',' ', $role));
}
function first_array_key(array $arr) {
    foreach ($arr as $key => $_) { return $key; }
    return null;
}


$can_manage_super = ($user['peran'] === 'super_admin');
$available_roles = $ROLE_OPTIONS;
if(!$can_manage_super) { unset($available_roles['super_admin']); }

$pengguna_error = '';
$pengguna = query("SELECT * FROM pengguna ORDER BY id DESC");
if (!is_array($pengguna)) {
    $pengguna_error = mysqli_error($conn) ?: 'Data pengguna tidak dapat dimuat.';
    $pengguna = [];
}

$peran_list = array_keys($available_roles);
$status_user_list = ['aktif','nonaktif'];
foreach ($pengguna as $row) {
    if (!isset($available_roles[$row['peran']])) {
        $available_roles[$row['peran']] = role_label($row['peran'], $ROLE_OPTIONS);
        $peran_list[] = $row['peran'];
    }
    $status = $row['status'] ?? '';
    if ($status !== '' && !in_array($status, $status_user_list, true)) {
        $status_user_list[] = $status;
    }
}
$peran_list = array_values(array_unique($peran_list));
if (!in_array($user['peran'], ['super_admin'], true)) {
    $peran_list = array_values(array_filter($peran_list, fn($r) => $r !== 'super_admin'));
}
$status_user_list = array_values(array_unique($status_user_list));

if (!isset($_SESSION['flash'])) $_SESSION['flash'] = '';
$flash = $_SESSION['flash'];
if ($pengguna_error !== '' && $flash === '') { $flash = $pengguna_error; }
$_SESSION['flash'] = '';
if ($pengguna_error !== '') { $_SESSION['flash'] = $pengguna_error; }

$user_edit_mode = false;
$user_edit_data = [
    'id' => '',
    'nama' => '',
    'username' => '',
    'peran' => $peran_list[0] ?? first_array_key($available_roles),
    'status' => $status_user_list[0] ?? 'aktif',
    'akses_dashboard' => 0,
    'akses_pelaporan' => 0,
    'akses_review' => 0,
];

if(isset($_GET['edit_user'])) {
    $user_edit_mode = true;
    $id = intval($_GET['edit_user']);
    if ($stmt = $conn->prepare("SELECT * FROM pengguna WHERE id=?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        if ($data) {
            $user_edit_data = array_merge($user_edit_data, $data[0]);
        }
    }
    if (!$can_manage_super && !empty($user_edit_data['id']) && ($user_edit_data['peran'] ?? '') === 'super_admin') {
        $_SESSION['flash'] = 'Anda tidak berwenang mengedit akun Super Admin.';
        header('Location: pengguna.php'); exit;
    }
    if (!empty($user_edit_data)) {
        if (!isset($available_roles[$user_edit_data['peran']])) {
            $available_roles[$user_edit_data['peran']] = role_label($user_edit_data['peran'], $ROLE_OPTIONS);
            $peran_list[] = $user_edit_data['peran'];
            $peran_list = array_values(array_unique($peran_list));
        }
        if (!in_array($user_edit_data['status'], $status_user_list, true)) {
            $status_user_list[] = $user_edit_data['status'];
        }
    }
}

$user_search = isset($_GET['user_search']) ? strtolower(trim($_GET['user_search'])) : '';
$user_peran = $_GET['user_peran'] ?? 'all';
$user_status = $_GET['user_status'] ?? 'all';
$user_filtered = array_filter($pengguna, function($item) use ($user_search, $user_peran, $user_status) {
    if (!is_array($item)) { return false; }
    $match = true;
    if ($user_search) {
        $nama = strtolower((string)($item['nama'] ?? ''));
        $match = $match && (strpos($nama, $user_search) !== false);
    }
    if ($user_peran !== 'all') {
        $match = $match && (($item['peran'] ?? null) === $user_peran);
    }
    if ($user_status !== 'all') {
        $match = $match && (($item['status'] ?? null) === $user_status);
    }
    return $match;
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawId = $_POST['id_user'] ?? '';
    $rawId = is_string($rawId) ? trim($rawId) : '';
    $hasId = ($rawId !== '' && ctype_digit($rawId) && (int)$rawId > 0);
    $isAdd = isset($_POST['tambah_user'])
        || (!$hasId && !isset($_POST['edit_user']) && isset($_POST['nama_user'], $_POST['username_user'], $_POST['peran_user'], $_POST['status_user']));
    $isEdit = isset($_POST['edit_user']) || $hasId;

    if ($isAdd) {
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama_user']));
        $username = mysqli_real_escape_string($conn, trim($_POST['username_user']));
        $password = mysqli_real_escape_string($conn, trim($_POST['password_user']));
        $peran = mysqli_real_escape_string($conn, trim($_POST['peran_user']));
        $status = mysqli_real_escape_string($conn, trim($_POST['status_user']));
        $akses_dashboard = isset($_POST['akses_dashboard']) ? 1 : 0;
        $akses_pelaporan = isset($_POST['akses_pelaporan']) ? 1 : 0;
        $akses_review    = isset($_POST['akses_review']) ? 1 : 0;
        if($nama==='' || $username==='' || $password==='' || $peran==='' || $status==='') {
            $_SESSION['flash'] = 'Data tidak boleh kosong!';
        } elseif (strlen($password) < 8) {
            $_SESSION['flash'] = 'Password minimal 8 karakter.';
        } elseif(!isset($available_roles[$peran])) {
            $_SESSION['flash'] = 'Peran tidak dikenali.';
        } elseif(!$can_manage_super && $peran==='super_admin') {
            $_SESSION['flash'] = 'Anda tidak berwenang menambah Super Admin.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ok = false;
                if($stmt = $conn->prepare("INSERT INTO pengguna (nama, username, password, password_hash, peran, status, akses_dashboard, akses_pelaporan, akses_review) VALUES (?,?,?,?,?,?,?,?,?)")){
                $empty = '';
                $stmt->bind_param("ssssiiisi", $nama, $username, $peran, $status, $akses_dashboard, $akses_pelaporan, $akses_review, $hash, $id);
                $ok = $stmt->execute();
                if ($ok) {
                    $_SESSION['flash'] = 'Pengguna berhasil ditambah.';
                } else {
                    $_SESSION['flash'] = 'Gagal menambah pengguna: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['flash'] = 'Gagal menambah pengguna: ' . $conn->error;
            }
            if ($ok) {
                $newId = (int)$conn->insert_id;
                audit_log($conn, 'create', 'pengguna', $newId, [
                    'nama' => $nama,
                    'username' => $username,
                    'peran' => $peran,
                    'status' => $status,
                ]);
            }
        }
        header('Location: pengguna.php'); exit;
    }

    if ($isEdit) {
        $id = intval($_POST['id_user']);
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama_user']));
        $username = mysqli_real_escape_string($conn, trim($_POST['username_user']));
        $password = mysqli_real_escape_string($conn, trim($_POST['password_user']));
        $peran = mysqli_real_escape_string($conn, trim($_POST['peran_user']));
        $status = mysqli_real_escape_string($conn, trim($_POST['status_user']));
        $akses_dashboard = isset($_POST['akses_dashboard']) ? 1 : 0;
        $akses_pelaporan = isset($_POST['akses_pelaporan']) ? 1 : 0;
        $akses_review    = isset($_POST['akses_review']) ? 1 : 0;
        $passwordChanged = ($password !== '');
        $target_peran = null;
        if ($stmt = $conn->prepare("SELECT peran FROM pengguna WHERE id=?")) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $target_peran = $row['peran'] ?? null;
        }
        if($nama==='' || $username==='' || $peran==='' || $status==='') {
            $_SESSION['flash'] = 'Data tidak boleh kosong!';
        } elseif ($passwordChanged && strlen($password) < 8) {
            $_SESSION['flash'] = 'Password minimal 8 karakter.';
        } elseif(!$can_manage_super && ($target_peran==='super_admin' || $peran==='super_admin')) {
            $_SESSION['flash'] = 'Anda tidak berwenang mengubah data Super Admin.';
        } elseif(!isset($available_roles[$peran])) {
            $_SESSION['flash'] = 'Peran tidak dikenali.';
        } else {
            $ok = false;
        if ($passwordChanged) {
         $hash = password_hash($password, PASSWORD_DEFAULT);
         $stmt = $conn->prepare(
        "UPDATE pengguna 
         SET nama=?, username=?, peran=?, status=?, akses_dashboard=?, akses_pelaporan=?, akses_review=?, password='', password_hash=? 
         WHERE id=?"
    );
    if ($stmt) {
        $stmt->bind_param(
            "ssssiiisi",
            $nama,
            $username,
            $peran,
            $status,
            $akses_dashboard,
            $akses_pelaporan,
            $akses_review,
            $hash,
            $id
        );
    

        $ok = $stmt->execute();
        if ($ok) {
            $_SESSION['flash'] = 'Pengguna berhasil diubah.';
        } else {
            $_SESSION['flash'] = 'Gagal mengubah pengguna: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['flash'] = 'Gagal mengubah pengguna: ' . $conn->error;
    }
} else {
// UPDATE TANPA GANTI PASSWORD  (± baris 271)
$stmt = $conn->prepare(
    "UPDATE pengguna 
     SET nama=?, username=?, peran=?, status=?, 
         akses_dashboard=?, akses_pelaporan=?, akses_review=? 
     WHERE id=?"
);

if ($stmt) {
    $stmt->bind_param(
        "sssssiii",
        $nama,
        $username,
        $peran,
        $status,
        $akses_dashboard,
        $akses_pelaporan,
        $akses_review,
        $id
    );
        $ok = $stmt->execute();
        if ($ok) {
            $_SESSION['flash'] = 'Pengguna berhasil diubah.';
        } else {
            $_SESSION['flash'] = 'Gagal mengubah pengguna: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['flash'] = 'Gagal mengubah pengguna: ' . $conn->error;
    }
}

            if ($ok) {
                audit_log($conn, 'update', 'pengguna', $id, [
                    'nama' => $nama,
                    'username' => $username,
                    'peran' => $peran,
                    'status' => $status,
                    'password_changed' => $passwordChanged,
                ]);
            }
        }
        header('Location: pengguna.php'); exit;
    }

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $id = intval($_POST['delete_user']);
    $target_peran = null;
    if ($stmt = $conn->prepare("SELECT peran FROM pengguna WHERE id=?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $target_peran = $row['peran'] ?? null;
    }
    if(!$can_manage_super) {
        $_SESSION['flash'] = 'Anda tidak berwenang menghapus Super Admin.';
    } elseif($target_peran === 'super_admin' && (int)($user['id'] ?? 0) === $id) {
        $_SESSION['flash'] = 'Tidak dapat menghapus akun Super Admin sendiri.';
    } else {
        $ok = false;
        if($stmt = $conn->prepare("DELETE FROM pengguna WHERE id=?")) {
            $stmt->bind_param("i", $id);
            $ok = $stmt->execute();
            $_SESSION['flash'] = $ok ? 'Pengguna berhasil dihapus.' : 'Gagal menghapus pengguna!';
            $stmt->close();
        }
        if ($ok) {
            audit_log($conn, 'delete', 'pengguna', $id, [
                'target_peran' => $target_peran,
            ]);
        }
    }
    header('Location: pengguna.php'); exit;
}

}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manajemen Pengguna</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/ui_base.css">
    <link rel="stylesheet" href="assets/css/password_toggle.css">
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include 'navbar.php'; ?>
<h2>Manajemen Pengguna</h2>
<?php
  $flash_messages = [];
  if ($flash) {
    $flash_messages[] = ['type' => 'info', 'message' => is_array($flash) ? implode(', ', array_map('strval', $flash)) : (string)$flash];
  }
  include __DIR__ . '/includes/flash.php';
?>
<div style="margin-bottom:24px;">
    <form method="post" data-loading="1" style="background:#e6f7d4; padding:12px 16px; border-radius:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="id_user" value="<?= htmlspecialchars((string)($user_edit_data['id'] ?? '')) ?>">
        <label class="sr-only" for="nama_user">Nama Pengguna</label>
        <input id="nama_user" type="text" name="nama_user" placeholder="Nama Pengguna" value="<?= htmlspecialchars((string)($user_edit_data['nama'] ?? '')) ?>" required>
        <label class="sr-only" for="username_user">Username</label>
        <input id="username_user" type="text" name="username_user" placeholder="Username" value="<?= htmlspecialchars((string)($user_edit_data['username'] ?? '')) ?>" required>
        <label class="sr-only" for="password_user">Password</label>
        <input id="password_user" type="password" name="password_user" placeholder="Password <?= $user_edit_mode ? '(kosongkan jika tidak diubah)' : '' ?>">
        <label class="sr-only" for="peran_user">Peran</label>
        <select id="peran_user" name="peran_user" required>
            <option value="">Pilih Peran</option>
        <?php foreach($peran_list as $peran): ?>
            <option value="<?= htmlspecialchars($peran) ?>" <?= ($user_edit_data['peran'] ?? '') === $peran?'selected':'' ?>><?= htmlspecialchars($available_roles[$peran] ?? role_label($peran,$ROLE_OPTIONS)) ?></option>
        <?php endforeach; ?>
        </select>
        <label class="sr-only" for="status_user">Status</label>
        <select id="status_user" name="status_user" required>
            <option value="">Pilih Status</option>
            <?php foreach($status_user_list as $stat): ?>
                <option value="<?= htmlspecialchars($stat) ?>" <?= ($user_edit_data['status'] ?? '') === $stat?'selected':'' ?>><?= htmlspecialchars($stat) ?></option>
            <?php endforeach; ?>
        </select>
        <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap; padding:6px 0;">
          <label style="display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" name="akses_dashboard" value="1" <?= !empty($user_edit_data['akses_dashboard']) ? 'checked' : '' ?>>
            Dashboard
          </label>
        
          <label style="display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" name="akses_pelaporan" value="1" <?= !empty($user_edit_data['akses_pelaporan']) ? 'checked' : '' ?>>
            Pelaporan
          </label>
        
          <label style="display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" name="akses_review" value="1" <?= !empty($user_edit_data['akses_review']) ? 'checked' : '' ?>>
            Review
          </label>
        </div>

        <?php if($user_edit_mode): ?>
            <button type="submit" name="edit_user" class="btn-loading" data-loading="1">Simpan Perubahan</button>
            <a href="pengguna.php" style="color:#008a43; margin-left:8px;">Batal</a>
        <?php else: ?>
            <button type="submit" name="tambah_user" class="btn-loading" data-loading="1">Tambah Pengguna</button>
        <?php endif; ?>
    </form>
</div>
<hr>
<form method="get" style="margin-bottom:16px;">
    <label class="sr-only" for="user_search">Cari nama pengguna</label>
    <input id="user_search" type="text" name="user_search" placeholder="Cari nama pengguna..." value="<?= htmlspecialchars($user_search) ?>">
    <label class="sr-only" for="user_peran">Filter Peran</label>
    <select id="user_peran" name="user_peran">
        <option value="all">Semua Peran</option>
        <?php foreach($peran_list as $peran): ?>
            <option value="<?= htmlspecialchars($peran) ?>" <?= $user_peran===$peran?'selected':'' ?>><?= htmlspecialchars($available_roles[$peran] ?? role_label($peran,$ROLE_OPTIONS)) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="user_status">Filter Status</label>
    <select id="user_status" name="user_status">
        <option value="all">Semua Status</option>
        <?php foreach($status_user_list as $stat): ?>
            <option value="<?= htmlspecialchars($stat) ?>" <?= $user_status===$stat?'selected':'' ?>><?= htmlspecialchars($stat) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Cari</button>
</form>
<div class="table-wrap">
<table border="1" cellpadding="8" cellspacing="0">
    <tr style="background:#f0f0f0;">
        <th>ID</th>
        <th>Nama Pengguna</th>
        <th>Username</th>
        <th>Peran</th>
        <th>Status</th>
        <th>Aksi</th>
    </tr>
    <?php if(count($user_filtered) === 0): ?>
        <tr><td colspan="6"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
    <?php else: ?>
        <?php foreach($user_filtered as $row): ?>
            <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars((string)$row['nama']) ?></td>
                <td><?= htmlspecialchars((string)$row['username']) ?></td>
                <td><?= htmlspecialchars($available_roles[$row['peran']] ?? role_label($row['peran'],$ROLE_OPTIONS)) ?></td>
                <td><?= htmlspecialchars((string)$row['status']) ?></td>
                <td>
                    <?php if(($row['peran'] ?? '') === 'super_admin' && !$can_manage_super): ?>
                        <span class="text-muted small">Tidak bisa edit</span>
                    <?php else: ?>
                        <button class="action-btn edit" onclick="window.location.href='?edit_user=<?= (int)$row['id'] ?>'">Edit</button>
                    <?php endif; ?>
                    <?php if($user['peran']==='super_admin' && $row['id'] != $user['id']): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_user" value="<?= (int)$row['id'] ?>">
                            <button class="action-btn delete" type="submit">Hapus</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
</div>
  <script defer src="assets/js/password_toggle.js"></script>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>




