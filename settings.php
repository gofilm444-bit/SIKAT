<?php
require_once __DIR__ . '/bootstrap.php';
if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$nama = $_SESSION['user']['nama'] ?? ($_SESSION['user']['username'] ?? 'Pengguna');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pengaturan - SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --brand:#218838; --soft:#f4faf6; --border:#dcefe4; --text:#0b3d2e; }
    body{ margin:0; font-family:system-ui, -apple-system, "Segoe UI", Arial, sans-serif; background:var(--soft); color:var(--text); }
    .wrap{ max-width:720px; margin:48px auto; background:#fff; border:1px solid var(--border); border-radius:16px; padding:24px; box-shadow:0 10px 24px rgba(0,0,0,.08); }
    h1{ margin:0 0 8px; color:#0f9152; }
    .muted{ color:#5f6e64; }
    .btn{ display:inline-block; margin-top:16px; background:var(--brand); color:#fff; padding:10px 14px; border-radius:10px; text-decoration:none; font-weight:600; }
    .btn:hover{ background:#1b6e2c; }
  </style>
  <link href="assets/css/ui_base.css" rel="stylesheet">
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>
  <div class="wrap">
    <h1>Pengaturan</h1>
    <p class="muted">Halo, <?= e($nama) ?>. Halaman pengaturan sedang disiapkan.</p>
    <p>Coming soon.</p>
    <a href="dashboard.php" class="btn">Kembali ke Dashboard</a>
  </div>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>
