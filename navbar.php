<?php
require_once __DIR__ . '/bootstrap.php';
if(!isset($_SESSION['user'])) header('Location: login.php');
require_once __DIR__ . '/includes/topbar.php';
$user = $_SESSION['user'];
$role = strtolower($user['peran'] ?? 'user');
$roleRaw = $user['peran_raw'] ?? ($user['peran'] ?? '');
$roleLabel = $user['peran_label'] ?? '';

$menus = [];
$auditorRoles = ['auditor','auditor_ka'];
$auditeeRoles = ['auditee','auditee_tlm','auditee_direktur'];

if (in_array($role, ['super_admin','admin','moderator'], true)) {
    $menus = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
        ['label' => 'Kebijakan & Regulasi', 'href' => 'kebijakan.php'],
        ['label' => 'Review Internal', 'href' => 'review.php'],
        ['label' => 'Pelaporan', 'href' => 'pelaporan.php'],
        ['label' => 'Risiko', 'href' => 'risiko.php'],
        ['label' => 'Self-Assessment', 'href' => 'self_assessment.php'],
    ];
} elseif (in_array($role, $auditorRoles, true)) {
    $menus = [
        ['label' => 'Review Internal', 'href' => 'review.php'],
        ['label' => 'Pelaporan', 'href' => 'pelaporan.php'],
        ['label' => 'Kebijakan & Regulasi', 'href' => 'kebijakan.php'],
    ];
} elseif (in_array($role, $auditeeRoles, true)) {
    $menus = [
        ['label' => 'Tindak Lanjut Reviu', 'href' => 'review.php?tab=chr'],
        ['label' => 'Dokumen Reviu', 'href' => 'review.php?tab=dok'],
        ['label' => 'Kebijakan & Regulasi', 'href' => 'kebijakan.php'],
    ];
} else {
    $menus = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
    ];
}
?>
<nav class="navbar-main">
    <div class="nav-group">
        <?php
          $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
          $currentTab = (string)($_GET['tab'] ?? '');
        ?>
        <?php foreach ($menus as $item): ?>
            <?php $hrefBase = strtok($item['href'], '?'); ?>
            <?php
              $isActive = false;
              if ($hrefBase === $current) {
                $hrefQuery = parse_url($item['href'], PHP_URL_QUERY);
                if ($hrefQuery) {
                  parse_str($hrefQuery, $hrefParams);
                  if (isset($hrefParams['tab'])) {
                    $isActive = ($currentTab === (string)$hrefParams['tab']);
                  } else {
                    $isActive = true;
                  }
                } else {
                  $isActive = true;
                }
              }
            ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-btn<?= $isActive ? ' active' : '' ?>"><?= htmlspecialchars($item['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($roleLabel): ?>
        <span class="nav-role">Peran: <?= htmlspecialchars($roleLabel) ?></span>
    <?php endif; ?>
</nav>

