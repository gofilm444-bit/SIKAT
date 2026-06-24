<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';

$env = strtolower((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''))));
if (in_array($env, ['local','dev','development'], true)) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}

if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }

$role = strtolower((string)($_SESSION['user']['peran'] ?? ''));
$roleRaw = strtolower((string)($_SESSION['user']['peran_raw'] ?? $role));
if (!in_array($role, ['super_admin', 'admin'], true) && !in_array($roleRaw, ['super_admin', 'admin'], true)) {
    http_response_code(403);
    die('Akses hanya untuk Admin/Super Admin.');
}

$__base = __DIR__;
$__candidates = [
    $__base . '/db.php',
    $__base . '/db/db.php',
    dirname($__base) . '/db.php',
    $__base . '/includes/db.php',
];
$__found = false;
foreach ($__candidates as $__p) {
    if (is_file($__p)) { require_once $__p; $__found = true; break; }
}
if (!$__found || !isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    die('Koneksi database tidak tersedia.');
}
$conn->set_charset('utf8mb4');

if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function media_table_exists(mysqli $conn): bool {
    if ($rs = $conn->query("SHOW TABLES LIKE 'public_media'")) {
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $ok;
    }
    return false;
}

function media_flash(string $type, string $message): void {
    $_SESSION['public_media_flash'] = ['type' => $type, 'message' => $message];
}

function media_safe_unlink(string $relPath): void {
    $base = realpath(__DIR__ . '/assets/public/media');
    $file = realpath(__DIR__ . '/' . ltrim(str_replace('\\', '/', $relPath), '/'));
    if ($base && $file && strpos($file, $base) === 0 && is_file($file)) {
        @unlink($file);
    }
}

function media_type_from_extension(string $ext): string {
    $ext = strtolower($ext);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return 'image';
    if (in_array($ext, ['mp4', 'webm', 'mov'], true)) return 'video';
    return '';
}

$tableReady = media_table_exists($conn);
$mediaDir = __DIR__ . '/assets/public/media';
if (!is_dir($mediaDir)) { @mkdir($mediaDir, 0755, true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf'] ?? '');
    if (!$tableReady) {
        media_flash('danger', 'Tabel public_media belum tersedia. Jalankan migration terlebih dahulu.');
        header('Location: public_media.php'); exit;
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        $title = trim((string)($_POST['title'] ?? ''));
        $caption = trim((string)($_POST['caption'] ?? ''));
        $requestedType = strtolower(trim((string)($_POST['media_type'] ?? '')));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            media_flash('danger', 'Judul media wajib diisi.');
            header('Location: public_media.php'); exit;
        }
        if (empty($_FILES['media_file']) || (int)($_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            media_flash('danger', 'File media wajib dipilih.');
            header('Location: public_media.php'); exit;
        }

        $file = $_FILES['media_file'];
        $original = (string)($file['name'] ?? '');
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $mediaType = media_type_from_extension($ext);

        $imageExt = ['jpg', 'jpeg', 'png', 'webp'];
        $videoExt = ['mp4', 'webm', 'mov'];
        $blocked = ['php','phtml','php3','php4','php5','php7','php8','phar','html','htm','js','exe','bat','cmd','sh'];
        if ($mediaType === '' || in_array($ext, $blocked, true)) {
            media_flash('danger', 'Format file tidak diizinkan.');
            header('Location: public_media.php'); exit;
        }
        if (!in_array($requestedType, ['image', 'video'], true) || $requestedType !== $mediaType) {
            media_flash('danger', 'Tipe media harus sesuai dengan file yang diupload.');
            header('Location: public_media.php'); exit;
        }

        $maxSize = $mediaType === 'image' ? 5 * 1024 * 1024 : 30 * 1024 * 1024;
        if ($size <= 0 || $size > $maxSize) {
            media_flash('danger', $mediaType === 'image' ? 'Ukuran gambar maksimal 5 MB.' : 'Ukuran video maksimal 30 MB.');
            header('Location: public_media.php'); exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowedMime = [
            'image/jpeg', 'image/png', 'image/webp',
            'video/mp4', 'video/webm', 'video/quicktime',
        ];
        if (!in_array($mime, $allowedMime, true)) {
            media_flash('danger', 'Tipe MIME file tidak valid.');
            header('Location: public_media.php'); exit;
        }
        if ($mediaType === 'image' && !in_array($ext, $imageExt, true)) {
            media_flash('danger', 'Ekstensi gambar tidak valid.');
            header('Location: public_media.php'); exit;
        }
        if ($mediaType === 'video' && !in_array($ext, $videoExt, true)) {
            media_flash('danger', 'Ekstensi video tidak valid.');
            header('Location: public_media.php'); exit;
        }

        $storedName = 'public_media_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $mediaDir . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($tmp, $dest)) {
            media_flash('danger', 'Gagal menyimpan file media.');
            header('Location: public_media.php'); exit;
        }

        $relPath = 'assets/public/media/' . $storedName;
        if ($stmt = $conn->prepare("INSERT INTO public_media (title, caption, file_path, media_type, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)")) {
            $stmt->bind_param('ssssii', $title, $caption, $relPath, $mediaType, $sortOrder, $isActive);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) {
                media_flash('success', 'Media publik berhasil diupload.');
            } else {
                media_safe_unlink($relPath);
                media_flash('danger', 'Gagal menyimpan metadata media.');
            }
        }
        header('Location: public_media.php'); exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $caption = trim((string)($_POST['caption'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0 && $title !== '' && ($stmt = $conn->prepare("UPDATE public_media SET title=?, caption=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?"))) {
            $stmt->bind_param('ssiii', $title, $caption, $sortOrder, $isActive, $id);
            $stmt->execute();
            $stmt->close();
            media_flash('success', 'Media publik diperbarui.');
        } else {
            media_flash('danger', 'Data media tidak valid.');
        }
        header('Location: public_media.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $relPath = '';
        if ($id > 0 && ($stmt = $conn->prepare("SELECT file_path FROM public_media WHERE id=? LIMIT 1"))) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $row = $stmt->get_result()->fetch_assoc();
                $relPath = (string)($row['file_path'] ?? '');
            }
            $stmt->close();
        }
        if ($id > 0 && ($stmt = $conn->prepare("DELETE FROM public_media WHERE id=?"))) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            if ($relPath !== '') media_safe_unlink($relPath);
            media_flash('success', 'Media publik dihapus.');
        }
        header('Location: public_media.php'); exit;
    }
}

$flash = $_SESSION['public_media_flash'] ?? null;
unset($_SESSION['public_media_flash']);
$items = [];
if ($tableReady && ($rs = $conn->query("SELECT * FROM public_media ORDER BY sort_order ASC, id DESC"))) {
    $items = $rs->fetch_all(MYSQLI_ASSOC);
    $rs->free();
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Kelola Media Publik - SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/ui_base.css" rel="stylesheet">
  <style>
    body{background:#f4faf6;color:#0b3d2e;}
    .wrap{max-width:1120px;margin:24px auto 48px;padding:0 14px;}
    .panel{background:#fff;border:1px solid #dcefe4;border-radius:12px;box-shadow:0 6px 18px rgba(16,122,61,.06);}
    .media-thumb{width:120px;aspect-ratio:16/9;border-radius:8px;object-fit:cover;background:#0b2c20;}
    .form-label{font-weight:700;color:#244d3a;}
    .hint{font-size:.86rem;color:#6b7280;}
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>
<main class="wrap">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="h4 text-success mb-1">Kelola Media Publik</h1>
      <div class="text-muted">Atur gambar dan video edukasi yang tampil di carousel halaman publik SIKAT.</div>
    </div>
    <a href="dashboard.php" class="btn btn-outline-success"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <?php if (!$tableReady): ?>
    <div class="alert alert-warning">
      Tabel <b>public_media</b> belum tersedia. Jalankan migration:
      <code>deploy/migrations/20260624_101500_create_public_media.sql</code>
    </div>
  <?php endif; ?>

  <section class="panel p-4 mb-4">
    <h2 class="h6 text-success mb-3"><i class="bi bi-upload me-1"></i>Upload Media</h2>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <?= csrf_field(); ?><input type="hidden" name="action" value="upload">
      <div class="col-md-4">
        <label class="form-label">Judul</label>
        <input name="title" class="form-control" maxlength="150" required>
      </div>
      <div class="col-md-5">
        <label class="form-label">Caption</label>
        <input name="caption" class="form-control" maxlength="255">
      </div>
      <div class="col-md-3">
        <label class="form-label">Urutan</label>
        <input name="sort_order" type="number" class="form-control" value="0">
      </div>
      <div class="col-md-3">
        <label class="form-label">Tipe Media</label>
        <select name="media_type" class="form-select" required>
          <option value="image">Image</option>
          <option value="video">Video</option>
        </select>
      </div>
      <div class="col-md-9">
        <label class="form-label">File Media</label>
        <input name="media_file" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov" required>
        <div class="hint mt-1">Gambar: JPG/JPEG/PNG/WEBP maks 5 MB. Video: MP4/WEBM/MOV maks 30 MB.</div>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active_upload" checked>
          <label class="form-check-label" for="is_active_upload">Aktif</label>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" <?= !$tableReady ? 'disabled' : '' ?>><i class="bi bi-cloud-arrow-up me-1"></i>Upload Media</button>
      </div>
    </form>
  </section>

  <section class="panel p-4">
    <h2 class="h6 text-success mb-3"><i class="bi bi-images me-1"></i>Daftar Media</h2>
    <?php if (empty($items)): ?>
      <div class="text-muted">Belum ada media publik yang diupload.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Preview</th><th>Metadata</th><th style="width:120px">Urutan</th><th style="width:100px">Aktif</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <?php $src = '/ski_new/' . ltrim(str_replace('\\', '/', (string)$item['file_path']), '/'); ?>
              <tr>
                <td>
                  <?php if (($item['media_type'] ?? '') === 'video'): ?>
                    <video class="media-thumb" controls preload="metadata"><source src="<?= e($src) ?>"></video>
                  <?php else: ?>
                    <img class="media-thumb" src="<?= e($src) ?>" alt="<?= e($item['title']) ?>">
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" id="media-form-<?= (int)$item['id'] ?>" class="row g-2">
                    <?= csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <div class="col-md-6"><input name="title" class="form-control" value="<?= e($item['title']) ?>" maxlength="150" required></div>
                    <div class="col-md-6"><input name="caption" class="form-control" value="<?= e($item['caption']) ?>" maxlength="255" placeholder="Caption"></div>
                    <div class="col-12 hint"><?= e($item['media_type']) ?> - <?= e($item['file_path']) ?></div>
                  </form>
                </td>
                <td><input form="media-form-<?= (int)$item['id'] ?>" name="sort_order" type="number" class="form-control" value="<?= (int)$item['sort_order'] ?>"></td>
                <td class="text-center"><input form="media-form-<?= (int)$item['id'] ?>" class="form-check-input" type="checkbox" name="is_active" <?= ((int)$item['is_active'] === 1) ? 'checked' : '' ?>></td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <button form="media-form-<?= (int)$item['id'] ?>" class="btn btn-sm btn-success">Simpan</button>
                    <form method="post" onsubmit="return confirm('Hapus media ini?');">
                      <?= csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>
