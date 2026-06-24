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

function contact_table_exists(mysqli $conn): bool {
    if ($rs = $conn->query("SHOW TABLES LIKE 'public_contacts'")) {
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $ok;
    }
    return false;
}

function contact_social_table_exists(mysqli $conn): bool {
    if ($rs = $conn->query("SHOW TABLES LIKE 'public_social_links'")) {
        $ok = $rs->num_rows > 0;
        $rs->free();
        return $ok;
    }
    return false;
}

function contact_flash(string $type, string $message): void {
    $_SESSION['public_contact_flash'] = ['type' => $type, 'message' => $message];
}

function contact_clean_number(string $value): string {
    return preg_replace('/[^\d+]/', '', trim($value)) ?: '';
}

function contact_valid_http_url(string $value): bool {
    if (!filter_var($value, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)(parse_url($value, PHP_URL_SCHEME) ?: ''));
    return in_array($scheme, ['http', 'https'], true);
}

$tableReady = contact_table_exists($conn);
$socialTableReady = contact_social_table_exists($conn);
$platformOptions = [
    'website' => ['label' => 'Website Resmi', 'icon' => 'globe'],
    'facebook' => ['label' => 'Facebook', 'icon' => 'facebook'],
    'instagram' => ['label' => 'Instagram', 'icon' => 'instagram'],
    'youtube' => ['label' => 'YouTube', 'icon' => 'youtube'],
    'tiktok' => ['label' => 'TikTok', 'icon' => 'tiktok'],
    'twitter' => ['label' => 'X/Twitter', 'icon' => 'twitter-x'],
    'whatsapp_channel' => ['label' => 'WhatsApp Channel', 'icon' => 'whatsapp'],
    'linkedin' => ['label' => 'LinkedIn', 'icon' => 'linkedin'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf'] ?? '');
    $action = (string)($_POST['action'] ?? 'save_contact');

    if ($action === 'save_contact' && !$tableReady) {
        contact_flash('danger', 'Tabel public_contacts belum tersedia. Jalankan migration terlebih dahulu.');
        header('Location: public_contacts.php'); exit;
    }

    if (in_array($action, ['add_social', 'update_social', 'delete_social'], true) && !$socialTableReady) {
        contact_flash('danger', 'Tabel public_social_links belum tersedia. Jalankan migration terlebih dahulu.');
        header('Location: public_contacts.php'); exit;
    }

    if ($action === 'add_social' || $action === 'update_social') {
        $id = (int)($_POST['id'] ?? 0);
        $platform = strtolower(trim((string)($_POST['platform'] ?? '')));
        $url = trim((string)($_POST['url'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!isset($platformOptions[$platform])) {
            contact_flash('danger', 'Platform media sosial tidak valid.');
            header('Location: public_contacts.php'); exit;
        }
        if ($url === '' || !contact_valid_http_url($url)) {
            contact_flash('danger', 'URL media sosial wajib valid dan memakai http/https.');
            header('Location: public_contacts.php'); exit;
        }

        $label = $platformOptions[$platform]['label'];
        $iconKey = $platformOptions[$platform]['icon'];
        if ($action === 'add_social') {
            if ($stmt = $conn->prepare("INSERT INTO public_social_links (platform, label, url, icon_key, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)")) {
                $stmt->bind_param('ssssii', $platform, $label, $url, $iconKey, $sortOrder, $isActive);
                $ok = $stmt->execute();
                $stmt->close();
                contact_flash($ok ? 'success' : 'danger', $ok ? 'Link media sosial berhasil ditambahkan.' : 'Gagal menambahkan link media sosial.');
            }
        } else {
            if ($id > 0 && ($stmt = $conn->prepare("UPDATE public_social_links SET platform=?, label=?, url=?, icon_key=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?"))) {
                $stmt->bind_param('ssssiii', $platform, $label, $url, $iconKey, $sortOrder, $isActive, $id);
                $ok = $stmt->execute();
                $stmt->close();
                contact_flash($ok ? 'success' : 'danger', $ok ? 'Link media sosial diperbarui.' : 'Gagal memperbarui link media sosial.');
            }
        }
        header('Location: public_contacts.php'); exit;
    }

    if ($action === 'delete_social') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && ($stmt = $conn->prepare("DELETE FROM public_social_links WHERE id=?"))) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            contact_flash('success', 'Link media sosial dihapus.');
        }
        header('Location: public_contacts.php'); exit;
    }

    $contactName = trim((string)($_POST['contact_name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $whatsapp = contact_clean_number((string)($_POST['whatsapp'] ?? ''));
    $phone = contact_clean_number((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $serviceHours = trim((string)($_POST['service_hours'] ?? ''));
    $mapsUrl = trim((string)($_POST['maps_url'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($contactName === '' && $description === '') {
        contact_flash('danger', 'Isi minimal nama pengelola atau deskripsi kontak.');
        header('Location: public_contacts.php'); exit;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        contact_flash('danger', 'Format email tidak valid.');
        header('Location: public_contacts.php'); exit;
    }
    if ($mapsUrl !== '' && !contact_valid_http_url($mapsUrl)) {
        contact_flash('danger', 'Format link Google Maps tidak valid.');
        header('Location: public_contacts.php'); exit;
    }

    $existingId = 0;
    if ($rs = $conn->query("SELECT id FROM public_contacts ORDER BY id ASC LIMIT 1")) {
        $row = $rs->fetch_assoc();
        $existingId = (int)($row['id'] ?? 0);
        $rs->free();
    }

    if ($existingId > 0) {
        $stmt = $conn->prepare("UPDATE public_contacts SET contact_name=?, description=?, whatsapp=?, phone=?, email=?, address=?, service_hours=?, maps_url=?, is_active=?, updated_at=NOW() WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssssssssii', $contactName, $description, $whatsapp, $phone, $email, $address, $serviceHours, $mapsUrl, $isActive, $existingId);
            $ok = $stmt->execute();
            $stmt->close();
            contact_flash($ok ? 'success' : 'danger', $ok ? 'Kontak publik berhasil diperbarui.' : 'Gagal memperbarui kontak publik.');
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO public_contacts (contact_name, description, whatsapp, phone, email, address, service_hours, maps_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssssssi', $contactName, $description, $whatsapp, $phone, $email, $address, $serviceHours, $mapsUrl, $isActive);
            $ok = $stmt->execute();
            $stmt->close();
            contact_flash($ok ? 'success' : 'danger', $ok ? 'Kontak publik berhasil disimpan.' : 'Gagal menyimpan kontak publik.');
        }
    }
    header('Location: public_contacts.php'); exit;
}

$flash = $_SESSION['public_contact_flash'] ?? null;
unset($_SESSION['public_contact_flash']);

$contact = [];
if ($tableReady && ($rs = $conn->query("SELECT * FROM public_contacts ORDER BY id ASC LIMIT 1"))) {
    $contact = $rs->fetch_assoc() ?: [];
    $rs->free();
}
$socialLinks = [];
if ($socialTableReady && ($rs = $conn->query("SELECT * FROM public_social_links ORDER BY sort_order ASC, id ASC"))) {
    $socialLinks = $rs->fetch_all(MYSQLI_ASSOC);
    $rs->free();
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Kelola Kontak Publik - SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/ui_base.css" rel="stylesheet">
  <style>
    body{background:#f4faf6;color:#0b3d2e;}
    .wrap{max-width:980px;margin:24px auto 48px;padding:0 14px;}
    .panel{background:#fff;border:1px solid #dcefe4;border-radius:12px;box-shadow:0 6px 18px rgba(16,122,61,.06);}
    .form-label{font-weight:700;color:#244d3a;}
    .hint{font-size:.86rem;color:#6b7280;}
    .social-table td{vertical-align:middle;}
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>
<main class="wrap">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="h4 text-success mb-1">Kelola Kontak Publik</h1>
      <div class="text-muted">Atur informasi pengelola yang tampil di halaman publik SIKAT.</div>
    </div>
    <a href="dashboard.php" class="btn btn-outline-success"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <?php if (!$tableReady): ?>
    <div class="alert alert-warning">
      Tabel <b>public_contacts</b> belum tersedia. Jalankan migration:
      <code>deploy/migrations/20260624_131408_create_public_contacts.sql</code>
    </div>
  <?php endif; ?>
  <?php if (!$socialTableReady): ?>
    <div class="alert alert-warning">
      Tabel <b>public_social_links</b> belum tersedia. Jalankan migration:
      <code>deploy/migrations/20260624_132219_create_public_social_links.sql</code>
    </div>
  <?php endif; ?>

  <section class="panel p-4">
    <h2 class="h6 text-success mb-3"><i class="bi bi-person-lines-fill me-1"></i>Informasi Kontak</h2>
    <form method="post" class="row g-3">
      <?= csrf_field(); ?>
      <input type="hidden" name="action" value="save_contact">
      <div class="col-md-6">
        <label class="form-label">Nama unit/pengelola</label>
        <input name="contact_name" class="form-control" maxlength="150" value="<?= e($contact['contact_name'] ?? '') ?>" placeholder="Tim Pengelola SIKAT">
      </div>
      <div class="col-md-3">
        <label class="form-label">WhatsApp</label>
        <input name="whatsapp" class="form-control" maxlength="40" value="<?= e($contact['whatsapp'] ?? '') ?>" placeholder="62812...">
      </div>
      <div class="col-md-3">
        <label class="form-label">Telepon</label>
        <input name="phone" class="form-control" maxlength="40" value="<?= e($contact['phone'] ?? '') ?>" placeholder="0921...">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input name="email" type="email" class="form-control" maxlength="150" value="<?= e($contact['email'] ?? '') ?>" placeholder="sikat@example.ac.id">
      </div>
      <div class="col-md-6">
        <label class="form-label">Jam layanan</label>
        <input name="service_hours" class="form-control" maxlength="150" value="<?= e($contact['service_hours'] ?? '') ?>" placeholder="Senin-Jumat, 08.00-16.00 WIT">
      </div>
      <div class="col-12">
        <label class="form-label">Deskripsi singkat</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Informasi singkat tentang pengelola atau kanal bantuan SIKAT."><?= e($contact['description'] ?? '') ?></textarea>
        <div class="hint mt-1">Isi minimal nama pengelola atau deskripsi.</div>
      </div>
      <div class="col-12">
        <label class="form-label">Alamat kantor/unit</label>
        <textarea name="address" class="form-control" rows="2" placeholder="Alamat kantor/unit pengelola."><?= e($contact['address'] ?? '') ?></textarea>
      </div>
      <div class="col-12">
        <label class="form-label">Link Google Maps</label>
        <input name="maps_url" type="url" class="form-control" maxlength="255" value="<?= e($contact['maps_url'] ?? '') ?>" placeholder="https://maps.google.com/...">
      </div>
      <div class="col-md-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active_contact" <?= ((int)($contact['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active_contact">Kontak aktif ditampilkan di publik</label>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" <?= !$tableReady ? 'disabled' : '' ?>><i class="bi bi-save me-1"></i>Simpan Kontak</button>
      </div>
    </form>
  </section>

  <section class="panel p-4 mt-4">
    <h2 class="h6 text-success mb-3"><i class="bi bi-share me-1"></i>Media Sosial Resmi</h2>
    <form method="post" class="row g-3 mb-4">
      <?= csrf_field(); ?>
      <input type="hidden" name="action" value="add_social">
      <div class="col-md-3">
        <label class="form-label">Platform</label>
        <select name="platform" class="form-select" required>
          <?php foreach ($platformOptions as $key => $meta): ?>
            <option value="<?= e($key) ?>"><?= e($meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label">URL/Link</label>
        <input name="url" type="url" class="form-control" maxlength="255" placeholder="https://..." required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Urutan</label>
        <input name="sort_order" type="number" class="form-control" value="0">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="social_active_new" checked>
          <label class="form-check-label" for="social_active_new">Aktif</label>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" <?= !$socialTableReady ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-1"></i>Tambah Link</button>
        <span class="hint ms-2">URL harus valid dan memakai http:// atau https://.</span>
      </div>
    </form>

    <?php if (empty($socialLinks)): ?>
      <div class="text-muted">Belum ada link media sosial resmi.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table social-table align-middle">
          <thead><tr><th>Platform</th><th>URL</th><th style="width:110px">Urutan</th><th style="width:90px">Aktif</th><th style="width:150px">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($socialLinks as $item): ?>
              <?php $formId = 'social-form-' . (int)$item['id']; ?>
              <tr>
                <td>
                  <form method="post" id="<?= e($formId) ?>" class="d-grid gap-2">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="update_social">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <select name="platform" class="form-select form-select-sm">
                      <?php foreach ($platformOptions as $key => $meta): ?>
                        <option value="<?= e($key) ?>" <?= (($item['platform'] ?? '') === $key) ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td><input form="<?= e($formId) ?>" name="url" type="url" class="form-control form-control-sm" value="<?= e($item['url'] ?? '') ?>" maxlength="255" required></td>
                <td><input form="<?= e($formId) ?>" name="sort_order" type="number" class="form-control form-control-sm" value="<?= (int)($item['sort_order'] ?? 0) ?>"></td>
                <td class="text-center"><input form="<?= e($formId) ?>" class="form-check-input" type="checkbox" name="is_active" <?= ((int)($item['is_active'] ?? 0) === 1) ? 'checked' : '' ?>></td>
                <td>
                  <div class="d-flex gap-2">
                    <button form="<?= e($formId) ?>" class="btn btn-sm btn-success">Simpan</button>
                    <form method="post" onsubmit="return confirm('Hapus link media sosial ini?');">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_social">
                      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
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
