<?php
if (!function_exists('topbar_initials')) {
    function topbar_initials(string $name): string {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') { return 'U'; }
        $parts = preg_split('/\s+/', $name) ?: [];
        $parts = array_values(array_filter($parts, static function ($part) {
            return trim($part) !== '';
        }));
        $count = count($parts);
        $first = $parts[0] ?? '';
        $last = $parts[$count - 1] ?? '';
        $clean = static function (string $value): string {
            return preg_replace('/[^A-Za-z]/', '', $value);
        };
        $grab = static function (string $value, int $length): string {
            $value = preg_replace('/[^A-Za-z]/', '', $value);
            if ($value === '') { return ''; }
            return substr($value, 0, $length);
        };
        if ($count >= 2) {
            $initials = $grab($first, 1) . $grab($last, 1);
        } else {
            $initials = $grab($first, 2);
        }
        $initials = strtoupper($initials);
        $initials = preg_replace('/[^A-Z]/', '', $initials);
        if ($initials === '') {
            $fallback = strtoupper($grab($clean($name), 2));
            $initials = preg_replace('/[^A-Z]/', '', $fallback);
        }
        if ($initials === '') { return 'U'; }
        return substr($initials, 0, 2);
    }
}

/**
 * Cek apakah kolom ada pada tabel (supaya aman kalau kolom akses belum dibuat).
 */
if (!function_exists('__topbar_col_exists')) {
    function __topbar_col_exists(mysqli $conn, string $table, string $col): bool {
        static $cache = [];
        $key = $table . ':' . $col;
        if (isset($cache[$key])) return $cache[$key];

        $tableEsc = $conn->real_escape_string($table);
        $colEsc   = $conn->real_escape_string($col);
        $ok = false;

        if ($rs = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'")) {
            $ok = ($rs->num_rows > 0);
            $rs->free();
        }

        return $cache[$key] = $ok;
    }
}

$user = $_SESSION['user'] ?? [];
$displayName = $user['nama'] ?? ($user['username'] ?? 'User');
$role = strtolower($user['peran'] ?? '');
$roleRaw = strtolower($user['peran_raw'] ?? $role);
$roleLabel = $user['peran_raw'] ?? ($user['peran'] ?? 'User');
$showAdminItems = in_array($role, ['super_admin', 'admin'], true) || in_array($roleRaw, ['super_admin', 'admin'], true);
$initials = topbar_initials((string)$displayName);

/**
 * ====== MENU ACCESS (UI) ======
 * Sumber akses:
 * 1) prioritas: $_SESSION['user'][akses_*] (kalau sudah di-set saat login)
 * 2) fallback: query DB pengguna (kalau koneksi $conn tersedia)
 *
 * Kunci yang dipakai (konsisten):
 * - akses_dashboard
 * - akses_pelaporan
 * - akses_review
 */
$akses_dashboard = (int)($user['akses_dashboard'] ?? 0);
$akses_pelaporan = (int)($user['akses_pelaporan'] ?? 0);
$akses_review    = (int)($user['akses_review'] ?? 0);

$uid = (int)($user['id'] ?? ($user['user_id'] ?? 0));

/**
 * Jika session belum memuat akses (semuanya 0) tapi user login,
 * coba ambil dari DB agar menu tetap muncul sesuai setting.
 */
if ($uid > 0 && ($akses_dashboard + $akses_pelaporan + $akses_review) === 0) {
    if (isset($conn) && ($conn instanceof mysqli)) {
        $fields = [];
        if (__topbar_col_exists($conn, 'pengguna', 'akses_dashboard')) $fields[] = 'akses_dashboard';
        if (__topbar_col_exists($conn, 'pengguna', 'akses_pelaporan')) $fields[] = 'akses_pelaporan';
        if (__topbar_col_exists($conn, 'pengguna', 'akses_review'))    $fields[] = 'akses_review';

        if (!empty($fields)) {
            $sql = "SELECT " . implode(',', $fields) . " FROM pengguna WHERE id=? LIMIT 1";
            if ($st = $conn->prepare($sql)) {
                $st->bind_param("i", $uid);
                if ($st->execute()) {
                    $res = $st->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    if ($row) {
                        $akses_dashboard = (int)($row['akses_dashboard'] ?? $akses_dashboard);
                        $akses_pelaporan = (int)($row['akses_pelaporan'] ?? $akses_pelaporan);
                        $akses_review    = (int)($row['akses_review'] ?? $akses_review);

                        // simpan balik ke session biar request berikutnya tidak query lagi
                        $_SESSION['user']['akses_dashboard'] = $akses_dashboard;
                        $_SESSION['user']['akses_pelaporan'] = $akses_pelaporan;
                        $_SESSION['user']['akses_review']    = $akses_review;
                    }
                }
                $st->close();
            }
        }
    }
}

// tampilkan divider dinamis kalau ada menu akses tambahan
$hasQuickMenu = ($akses_dashboard === 1) || ($akses_pelaporan === 1) || ($akses_review === 1);
?>
<header class="topbar">
  <div class="topbar-brand">
    <img class="topbar-logo" src="/ski_new/asset/logo_poltekkes_baru-60h.png" alt="Poltekkes Ternate">
    <span class="sikat-version-badge">SIKAT v2.0</span>
    <span class="sr-only">Poltekkes Ternate</span>
  </div>
  <div class="topbar-actions">
    <div class="topbar-profile" id="topbarMenuWrap">
      <button type="button" class="profile-btn" id="topbarMenuButton" aria-label="Menu Profil" aria-haspopup="true" aria-expanded="false" aria-controls="topbarMenu">
        <span class="sikat-avatar-badge" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
      </button>
      <div class="topbar-menu" id="topbarMenu" role="menu" aria-labelledby="topbarMenuButton">
        <div class="topbar-user-card" aria-label="Profil pengguna">
          <div class="topbar-user-name"><?= htmlspecialchars((string)$displayName, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="topbar-user-role"><?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="topbar-divider"></div>

        <?php if ($akses_dashboard === 1): ?>
          <a href="dashboard.php" role="menuitem">Dashboard</a>
        <?php endif; ?>

        <?php if ($akses_review === 1): ?>
          <a href="review.php" role="menuitem">Review</a>
        <?php endif; ?>

        <?php if ($akses_pelaporan === 1): ?>
          <a href="pelaporan.php" role="menuitem">Pelaporan</a>
        <?php endif; ?>

        <?php if ($hasQuickMenu): ?>
          <div class="topbar-divider" style="height:1px;background:#e6efe9;margin:6px 4px;"></div>
        <?php endif; ?>

        <a href="settings.php" role="menuitem">Pengaturan</a>
        <a href="settings.php#ubah-password" role="menuitem">Ubah Password</a>
        <?php if ($showAdminItems): ?>
          <a href="public_media.php" role="menuitem">Kelola Media Publik</a>
          <a href="public_contacts.php" role="menuitem">Kelola Kontak Publik</a>
          <a href="pengguna.php" role="menuitem">Pengguna</a>
          <a href="mail_recipients.php" role="menuitem">Penerima Email</a>
        <?php endif; ?>
        <div class="topbar-divider"></div>
        <a href="logout.php" class="danger" role="menuitem">Logout</a>
      </div>
    </div>
  </div>
</header>
<script>
(function() {
  var wrap = document.getElementById('topbarMenuWrap');
  var btn = document.getElementById('topbarMenuButton');
  var menu = document.getElementById('topbarMenu');
  if (!wrap || !btn) return;
  var lastFocus = null;
  var closeMenu = function() {
    wrap.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
    if (lastFocus) {
      lastFocus.focus();
      lastFocus = null;
    }
  };
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    lastFocus = document.activeElement === btn ? btn : document.activeElement;
    var isOpen = wrap.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen && menu) {
      var firstLink = menu.querySelector('a');
      if (firstLink) { firstLink.focus(); }
    }
  });
  document.addEventListener('click', function(e) {
    if (!wrap.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMenu();
  });
})();

(function() {
  var setLoading = function(btn, autoReset) {
    if (!btn || btn.dataset.loadingApplied === '1') return;
    btn.dataset.loadingApplied = '1';
    btn.setAttribute('aria-busy', 'true');
    if (!btn.dataset.originalText) {
      btn.dataset.originalText = btn.innerText;
    }
    btn.classList.add('is-loading');
    if (btn.tagName === 'BUTTON') {
      btn.disabled = true;
    } else {
      btn.setAttribute('aria-disabled', 'true');
    }
    btn.innerText = 'Memproses...';
    if (autoReset) {
      setTimeout(function() {
        btn.classList.remove('is-loading');
        btn.removeAttribute('aria-busy');
        if (btn.tagName === 'BUTTON') { btn.disabled = false; }
        btn.removeAttribute('aria-disabled');
        if (btn.dataset.originalText) {
          btn.innerText = btn.dataset.originalText;
        }
        delete btn.dataset.loadingApplied;
      }, 2500);
    }
  };

  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form || form.getAttribute('data-loading') !== '1') return;
    var btns = form.querySelectorAll('button[type="submit"], button:not([type])');
    btns.forEach(function(btn) { setLoading(btn, false); });
  });

  document.addEventListener('click', function(e) {
    var target = e.target.closest('a.btn-loading[data-loading="1"]');
    if (!target) return;
    setLoading(target, true);
  });
})();
</script>
