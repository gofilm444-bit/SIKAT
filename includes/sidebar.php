<?php
require_once __DIR__ . '/url_helpers.php';

if (!function_exists('sikat_sidebar_slug')) {
    function sikat_sidebar_slug(string $value): string {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('sikat_sidebar_current_path')) {
    function sikat_sidebar_current_path(): string {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = '/' . trim((string)$path, '/');
        $base = app_base_path();
        if ($base !== '' && strpos($path, $base . '/') === 0) {
            $path = substr($path, strlen($base));
        } elseif ($base !== '' && $path === $base) {
            $path = '/';
        }
        return '/' . trim($path, '/');
    }
}

if (!function_exists('sikat_sidebar_is_active')) {
    function sikat_sidebar_is_active(array $item, string $currentPath, string $currentScript, string $currentTab): bool {
        $match = (array)($item['match'] ?? []);
        foreach ($match as $path) {
            $path = '/' . trim((string)$path, '/');
            if ($path === '/') {
                if ($currentPath === '/') { return true; }
                continue;
            }
            if ($currentPath === $path || strpos($currentPath, $path . '/') === 0) {
                return true;
            }
        }
        if (isset($item['script']) && (string)$item['script'] === $currentScript) {
            if (isset($item['tab'])) {
                return $currentTab === (string)$item['tab'];
            }
            return true;
        }
        return false;
    }
}

if (!function_exists('sikat_sidebar_icon')) {
    function sikat_sidebar_icon(string $name): string {
        $icons = [
            'dashboard' => '<path d="M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Z"/>',
            'policy' => '<path d="M7 3h8l4 4v14H7V3Zm7 1.5V8h3.5M9 12h8M9 15h8M9 18h5"/>',
            'review' => '<path d="M7 3h10v4h3v14H4V7h3V3Zm2 4h6V5H9v2Zm-1 6 2.2 2.2L15 10.4M8 18h8"/>',
            'report' => '<path d="M4 5h16v12H7l-3 3V5Zm4 4h8M8 12h6"/>',
            'risk' => '<path d="M12 3 4 6v5c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V6l-8-3Zm0 5v5M12 16h.01"/>',
            'assessment' => '<path d="M5 4h14v16H5V4Zm4 4h6M9 12h6M9 16h3M7 8h.01M7 12h.01M7 16h.01"/>',
            'media' => '<path d="M4 6h16v12H4V6Zm3 9 3.5-4 2.5 3 2-2.2L18 15M15 9h.01"/>',
            'contact' => '<path d="M4 5h16v14H4V5Zm3 4h6M7 13h4M15 9h2M15 13h2"/>',
            'users' => '<path d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3 20c.4-3.2 2.5-5 6-5s5.6 1.8 6 5H3Zm11.5-5.5c2.8.2 4.7 1.7 5.2 4.5"/>',
            'email' => '<path d="M4 6h16v12H4V6Zm1.5 1.5L12 13l6.5-5.5"/>',
            'settings' => '<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm8.5 4a7.7 7.7 0 0 0-.1-1l2-1.5-2-3.5-2.4 1a8.4 8.4 0 0 0-1.7-1L16 3h-4l-.3 3a8.4 8.4 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a7.7 7.7 0 0 0 0 2l-2 1.5 2 3.5 2.4-1a8.4 8.4 0 0 0 1.7 1l.3 3h4l.3-3a8.4 8.4 0 0 0 1.7-1l2.4 1 2-3.5-2-1.5c.1-.3.1-.7.1-1Z"/>',
        ];
        $path = $icons[$name] ?? $icons['dashboard'];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="sidebar-svg"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</g></svg>';
    }
}

$sidebarUser = $_SESSION['user'] ?? [];
$sidebarName = $displayName ?? ($sidebarUser['nama'] ?? ($sidebarUser['username'] ?? 'User'));
$sidebarRole = sikat_sidebar_slug((string)($role ?? ($sidebarUser['peran'] ?? '')));
$sidebarRoleRaw = sikat_sidebar_slug((string)($roleRaw ?? ($sidebarUser['peran_raw'] ?? $sidebarRole)));
$sidebarRoleLabel = $roleLabel ?? ($sidebarUser['peran_label'] ?? ($sidebarUser['peran_raw'] ?? ($sidebarUser['peran'] ?? 'User')));
$sidebarInitials = $initials ?? (function_exists('topbar_initials') ? topbar_initials((string)$sidebarName) : 'U');

$sidebarAdmin = in_array($sidebarRole, ['super_admin', 'superadmin', 'admin', 'moderator'], true)
    || in_array($sidebarRoleRaw, ['super_admin', 'superadmin', 'admin', 'moderator'], true);
$sidebarAuditor = in_array($sidebarRole, ['auditor', 'auditor_ka', 'auditor_staff', 'kepala_ski'], true)
    || strpos($sidebarRole, 'auditor_') === 0;
$sidebarAuditee = $sidebarRole === 'auditee' || strpos($sidebarRole, 'auditee_') === 0;
$sidebarDirector = strpos($sidebarRole, 'direktur') !== false || strpos($sidebarRoleRaw, 'direktur') !== false;

$sidebarAksesDashboard = (int)($akses_dashboard ?? ($sidebarUser['akses_dashboard'] ?? 0));
$sidebarAksesPelaporan = (int)($akses_pelaporan ?? ($sidebarUser['akses_pelaporan'] ?? 0));
$sidebarAksesReview = (int)($akses_review ?? ($sidebarUser['akses_review'] ?? 0));

$canDashboard = $sidebarAdmin || $sidebarDirector || $sidebarAksesDashboard === 1;
$canReview = $sidebarAdmin || $sidebarAuditor || $sidebarAuditee || $sidebarDirector || $sidebarAksesReview === 1;
$canPelaporan = $sidebarAdmin || $sidebarAuditor || $sidebarAksesPelaporan === 1;
$canKepatuhanMaster = $sidebarAdmin || $sidebarAuditor || $sidebarAuditee || $sidebarDirector;
$canRisk = $sidebarAdmin;
$canPublicAdmin = $sidebarAdmin;
$canUserAdmin = $sidebarAdmin;

$currentPath = sikat_sidebar_current_path();
$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$currentTab = (string)($_GET['tab'] ?? '');

$reviewItems = [];
if ($canReview) {
    $reviewItems = [
        ['label' => 'Jadwal', 'href' => review_url('jadwal'), 'match' => ['/review/jadwal'], 'script' => 'review.php', 'tab' => 'jadwal'],
        ['label' => 'Penugasan', 'href' => review_url('penugasan'), 'match' => ['/review/penugasan'], 'script' => 'review.php', 'tab' => 'asg'],
        ['label' => 'Dokumen', 'href' => review_url('dokumen'), 'match' => ['/review/dokumen'], 'script' => 'review.php', 'tab' => 'dok'],
        ['label' => 'CHR & Rekomendasi', 'href' => review_url('chr'), 'match' => ['/review/chr'], 'script' => 'review.php', 'tab' => 'chr'],
        ['label' => 'Laporan & Verifikasi', 'href' => review_url('laporan'), 'match' => ['/review/laporan', '/review/verifikasi'], 'script' => 'review.php', 'tab' => 'laporan'],
        ['label' => 'Master', 'href' => review_url('master'), 'match' => ['/review/master'], 'script' => 'review.php', 'tab' => 'master'],
    ];
}

$sections = [
    [
        'title' => 'Utama',
        'items' => array_values(array_filter([
            $canDashboard ? ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route_url('dashboard'), 'match' => ['/dashboard'], 'script' => 'dashboard.php'] : null,
        ])),
    ],
    [
        'title' => 'Kepatuhan Internal',
        'items' => array_values(array_filter([
            $canReview ? ['label' => 'Review Internal', 'icon' => 'review', 'href' => route_url('review'), 'match' => ['/review'], 'script' => 'review.php', 'children' => $reviewItems] : null,
            $canPelaporan ? ['label' => 'Pelaporan', 'icon' => 'report', 'href' => route_url('pelaporan'), 'match' => ['/pelaporan'], 'script' => 'pelaporan.php'] : null,
            $canKepatuhanMaster ? ['label' => 'Kebijakan', 'icon' => 'policy', 'href' => route_url('kebijakan'), 'match' => ['/kebijakan'], 'script' => 'kebijakan.php'] : null,
            $canRisk ? ['label' => 'Risiko', 'icon' => 'risk', 'href' => route_url('risiko'), 'match' => ['/risiko'], 'script' => 'risiko.php'] : null,
            $canRisk ? ['label' => 'Self-Assessment', 'icon' => 'assessment', 'href' => route_url('self-assessment'), 'match' => ['/self-assessment'], 'script' => 'self_assessment.php'] : null,
        ])),
    ],
    [
        'title' => 'Layanan Publik',
        'items' => $canPublicAdmin ? [
            ['label' => 'Kelola Media Publik', 'icon' => 'media', 'href' => route_url('public_media'), 'match' => ['/public-media'], 'script' => 'public_media.php'],
            ['label' => 'Kelola Kontak Publik', 'icon' => 'contact', 'href' => route_url('public_contacts'), 'match' => ['/public-contacts'], 'script' => 'public_contacts.php'],
        ] : [],
    ],
    [
        'title' => 'Administrasi',
        'items' => array_values(array_filter([
            $canUserAdmin ? ['label' => 'Pengguna', 'icon' => 'users', 'href' => route_url('pengguna'), 'match' => ['/pengguna'], 'script' => 'pengguna.php'] : null,
            $canUserAdmin ? ['label' => 'Penerima Email', 'icon' => 'email', 'href' => route_url('mail_recipients'), 'match' => ['/mail-recipients'], 'script' => 'mail_recipients.php'] : null,
            ['label' => 'Pengaturan', 'icon' => 'settings', 'href' => route_url('settings'), 'match' => ['/settings'], 'script' => 'settings.php'],
        ])),
    ],
];
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Menu utama aplikasi">
  <div class="sidebar-brand">
    <img class="sidebar-logo" src="<?= htmlspecialchars(asset_url('asset/logo_poltekkes_baru-60h.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Poltekkes Ternate">
    <div class="sidebar-brand-text">
      <strong>SIKAT</strong>
      <span>Kepatuhan Internal</span>
    </div>
    <span class="sikat-version-badge sidebar-version">V3.0</span>
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Tutup sidebar" title="Tutup sidebar">&times;</button>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($sections as $section): ?>
      <?php if (empty($section['items'])) { continue; } ?>
      <div class="sidebar-section">
        <div class="sidebar-section-title"><?= htmlspecialchars((string)$section['title'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php foreach ($section['items'] as $item): ?>
          <?php
            $active = sikat_sidebar_is_active($item, $currentPath, $currentScript, $currentTab);
            $children = (array)($item['children'] ?? []);
            $hasChildren = !empty($children);
            $subnavId = $hasChildren ? 'sidebar-subnav-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$item['label'])) : '';
            $subnavOpen = $hasChildren && ($active || $currentScript === 'review.php');
          ?>
          <div class="sidebar-item<?= $hasChildren ? ' has-children' : '' ?><?= $subnavOpen ? ' open' : '' ?>">
            <a class="sidebar-link<?= $active ? ' active' : '' ?>" href="<?= htmlspecialchars((string)$item['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>">
              <span class="sidebar-icon" aria-hidden="true"><?= sikat_sidebar_icon((string)$item['icon']) ?></span>
              <span class="sidebar-label"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <?php if ($hasChildren): ?>
              <button type="button" class="sidebar-accordion-toggle" data-sidebar-accordion aria-label="Buka/tutup submenu <?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>" aria-controls="<?= htmlspecialchars($subnavId, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="<?= $subnavOpen ? 'true' : 'false' ?>" title="Submenu <?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m8 10 4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            <?php endif; ?>
          </div>
          <?php if ($hasChildren): ?>
            <div class="sidebar-subnav<?= $subnavOpen ? ' open' : '' ?>" id="<?= htmlspecialchars($subnavId, ENT_QUOTES, 'UTF-8') ?>" aria-label="Submenu Review Internal"<?= $subnavOpen ? '' : ' hidden' ?>>
              <?php foreach ($children as $child): ?>
                <?php $childActive = sikat_sidebar_is_active($child, $currentPath, $currentScript, $currentTab); ?>
                <a class="sidebar-sublink<?= $childActive ? ' active' : '' ?>" href="<?= htmlspecialchars((string)$child['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string)$child['label'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string)$child['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-user" title="<?= htmlspecialchars((string)$sidebarName . ' - ' . (string)$sidebarRoleLabel, ENT_QUOTES, 'UTF-8') ?>">
    <span class="sikat-avatar-badge" aria-hidden="true"><?= htmlspecialchars((string)$sidebarInitials, ENT_QUOTES, 'UTF-8') ?></span>
    <div class="sidebar-user-text">
      <strong><?= htmlspecialchars((string)$sidebarName, ENT_QUOTES, 'UTF-8') ?></strong>
      <span><?= htmlspecialchars((string)$sidebarRoleLabel, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
