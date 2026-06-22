<?php
require_once __DIR__ . '/bootstrap.php';
/* ===== DB Loader Fleksibel ===== */
$__base = __DIR__;
$__candidates = [
  $__base.'/db.php', $__base.'/ski_new/db.php', $__base.'/db/db.php',
  dirname($__base).'/db.php', $__base.'/includes/db.php'
];
$__found=false; foreach($__candidates as $__p){ if(is_file($__p)){ require_once $__p; $__found=true; break; } }
if(!$__found){ http_response_code(500); die("db.php tidak ditemukan:\n - ".implode("\n - ", $__candidates)); }
if(!isset($conn) || !($conn instanceof mysqli)){ http_response_code(500); die("Koneksi \$conn tidak tersedia."); }
$conn->set_charset('utf8mb4');

/* ===== AuthZ (admin/moderator) ===== */
if (empty($_SESSION['user'])) { header('Location: login.php?open=login'); exit; }
$role = strtolower($_SESSION['user']['peran'] ?? 'user');
if (!in_array($role, ['admin','super_admin','superadmin','moderator'])) { http_response_code(403); die('Akses ditolak'); }

/* ===== Helpers ===== */
if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf_token']).'">'; }
function csrf_check($t){ if(!hash_equals($_SESSION['csrf_token'], (string)$t)){ http_response_code(400); die('Invalid CSRF token'); } }
function flash($k,$v=null){ if($v!==null){ $_SESSION['flash'][$k]=$v; return; } $x=$_SESSION['flash'][$k]??null; unset($_SESSION['flash'][$k]); return $x; }

function ensure_mail_recipients_schema(mysqli $conn): void {
  static $done = false;
  if ($done) return;
  $done = true;

  $res = @$conn->query("SELECT COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mail_recipients' AND COLUMN_NAME='id' LIMIT 1");
  if (!$res) return;
  $info = $res->fetch_assoc() ?: [];
  $needPrimary = (($info['COLUMN_KEY'] ?? '') !== 'PRI');
  $needAuto = (strpos(strtolower($info['EXTRA'] ?? ''), 'auto_increment') === false);
  if (!$needPrimary && !$needAuto) return;

  @$conn->query("SET @row:=0");
  @$conn->query("UPDATE mail_recipients SET id = (@row:=@row+1) ORDER BY created_at, email");
  if ($needPrimary) {
    @$conn->query("ALTER TABLE mail_recipients ADD PRIMARY KEY (id)");
  }
  if ($needAuto) {
    @$conn->query("ALTER TABLE mail_recipients MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
  }
}

/* ===== Actions ===== */
$method = $_SERVER['REQUEST_METHOD'];

if ($method==='POST') {
  csrf_check($_POST['csrf'] ?? '');
  $act = $_POST['action'] ?? '';

  if ($act==='create' || $act==='update') {
    ensure_mail_recipients_schema($conn);
    $id    = (int)($_POST['id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $nama  = trim($_POST['nama'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash('err','Email tidak valid.'); header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
    if (mb_strlen($nama)>100) $nama = mb_substr($nama,0,100);

    if ($act==='create') {
      $stmt = $conn->prepare("INSERT INTO mail_recipients (email,nama,aktif) VALUES (?,?,?)");
      $stmt->bind_param("ssi",$email,$nama,$aktif);
      $ok = $stmt->execute();
      flash($ok?'ok':'err', $ok?'Penerima ditambahkan.':'Gagal menambah (mungkin email sudah ada).');
    } else {
      $stmt = $conn->prepare("UPDATE mail_recipients SET email=?, nama=?, aktif=? WHERE id=?");
      $stmt->bind_param("ssii",$email,$nama,$aktif,$id);
      $ok = $stmt->execute();
      flash($ok?'ok':'err', $ok?'Penerima diperbarui.':'Tidak ada perubahan.');
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
  }

  if ($act==='toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $to = (int)($_POST['to'] ?? 0);
    $stmt = $conn->prepare("UPDATE mail_recipients SET aktif=? WHERE id=?");
    $stmt->bind_param("ii",$to,$id);
    $ok = $stmt->execute();
    flash($ok?'ok':'err', $ok?'Status diperbarui.':'Gagal memperbarui status.');
    header('Location: '.$_SERVER['PHP_SELF']); exit;
  }

  if ($act==='delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM mail_recipients WHERE id=?");
    $stmt->bind_param("i",$id);
    $ok = $stmt->execute();
    flash($ok?'ok':'err', $ok?'Penerima dihapus.':'Gagal menghapus.');
    header('Location: '.$_SERVER['PHP_SELF']); exit;
  }
}

/* ===== List & search ===== */
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per = 10; $off = ($page-1)*$per;

$where = "WHERE 1=1"; $types=""; $params=[];
if ($q!=='') { $where .= " AND (email LIKE CONCAT('%',?,'%') OR nama LIKE CONCAT('%',?,'%'))"; $types.="ss"; $params[]=$q; $params[]=$q; }

$stmtC = $conn->prepare("SELECT COUNT(*) c FROM mail_recipients $where");
if($types) $stmtC->bind_param($types, ...$params);
$stmtC->execute(); $total = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);

$sql = "SELECT id,email,nama,aktif,created_at FROM mail_recipients $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
if($types){ $types2=$types.'ii'; $params2=array_merge($params,[$per,$off]); $stmt=$conn->prepare($sql); $stmt->bind_param($types2, ...$params2); }
else { $stmt=$conn->prepare($sql); $stmt->bind_param("ii",$per,$off); }
$stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pages = max(1, (int)ceil($total/$per));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Penerima Notifikasi – SIKAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/ui_base.css" rel="stylesheet">
  <style>
    :root{ --brand:#218838; --accent:#f0c300; --soft:#fbfdf8; --border:#dcefe4; }
    body{ background:var(--soft); }
    .appbar{background:var(--brand); border-bottom:4px solid var(--accent); color:#fff;}
    .card-soft{background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 6px 18px rgba(0,0,0,.06);}
    .badge-soft{background:#e9f5ee; color:#107a3d; border:1px solid var(--border);}
  </style>
  <?php include __DIR__ . '/includes/head_favicon.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>


<main class="container my-4">
  <?php
    $flash_messages = [];
    if ($m = flash('ok')) { $flash_messages[] = ['type' => 'success', 'message' => $m]; }
    if ($m = flash('err')) { $flash_messages[] = ['type' => 'danger', 'message' => $m]; }
    if ($m = flash('info')) { $flash_messages[] = ['type' => 'info', 'message' => $m]; }
    if ($m = flash('warn')) { $flash_messages[] = ['type' => 'warning', 'message' => $m]; }
    include __DIR__ . '/includes/flash.php';
  ?>


  <div class="row g-3">
    <!-- Form tambah / edit -->
    <div class="col-lg-4">
      <div class="card-soft p-3">
        <h6 class="mb-3">Tambah / Edit Penerima</h6>
        <form method="post">
          <?= csrf_field(); ?>
          <input type="hidden" name="action" value="create" id="form-action">
          <input type="hidden" name="id" value="" id="form-id">
          <div class="mb-2">
            <label class="form-label mb-1">Email</label>
            <input name="email" id="form-email" class="form-control" placeholder="nama@domain.ac.id" required>
          </div>
          <div class="mb-2">
            <label class="form-label mb-1">Nama (opsional)</label>
            <input name="nama" id="form-nama" class="form-control" placeholder="Nama penerima">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="aktif" id="form-aktif" checked>
            <label for="form-aktif" class="form-check-label">Aktif</label>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-success" type="submit">Simpan</button>
            <button class="btn btn-secondary" type="button" onclick="resetForm()">Reset</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel daftar -->
    <div class="col-lg-8">
      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Daftar Penerima</h6>
          <form class="d-flex" method="get">
            <input class="form-control form-control-sm me-2" name="q" value="<?= e($q) ?>" placeholder="Cari email / nama">
            <button class="btn btn-sm btn-primary">Cari</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr>
              <th>Email</th><th>Nama</th><th>Status</th><th>Dibuat</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody>
              <?php if(empty($rows)): ?>
                <tr><td colspan="5"><div class="empty-state">Tidak ada data ditemukan.<div class="hint">Coba ubah filter/pencarian.</div></div></td></tr>
              <?php else: foreach($rows as $r): ?>
                <tr>
                  <td><?= e($r['email']) ?></td>
                  <td><?= e($r['nama']) ?></td>
                  <td>
                    <?php if($r['aktif']): ?>
                      <span class="badge bg-success">Aktif</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td><?= e($r['created_at']) ?></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editRow(<?= (int)$r['id'] ?>,'<?= e($r['email']) ?>','<?= e($r['nama']) ?>',<?= (int)$r['aktif'] ?>)">Edit</button>

                    <form method="post" class="d-inline">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="to" value="<?= $r['aktif']?0:1 ?>">
                      <button class="btn btn-sm btn-outline-warning me-1" onclick="return confirm('Ubah status penerima ini?')">
                        <?= $r['aktif']?'Nonaktifkan':'Aktifkan' ?>
                      </button>
                    </form>

                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus penerima ini?')">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <nav>
          <ul class="pagination justify-content-end mb-0">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetForm(){
  document.getElementById('form-action').value='create';
  document.getElementById('form-id').value='';
  document.getElementById('form-email').value='';
  document.getElementById('form-nama').value='';
  document.getElementById('form-aktif').checked=true;
}

function editRow(id,email,nama,aktif){
  document.getElementById('form-action').value='update';
  document.getElementById('form-id').value=id;
  document.getElementById('form-email').value=email;
  document.getElementById('form-nama').value=nama;
  document.getElementById('form-aktif').checked=!!aktif;
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<footer class="text-center py-3 small text-muted">&copy; <?= date('Y') ?> SIKAT &ndash; Team IT Poltekkes Ternate | Ded</footer>
</body>
</html>



