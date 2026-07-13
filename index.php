<?php
require_once __DIR__ . '/bootstrap.php';

$user = current_user();
if (!$user) {
    require_login();
    exit;
}

$role = current_role();
$roleNorm = auth_normalize_role($role);

if (in_array($roleNorm, ['admin','superadmin','super_admin','kepala_ski','direktur'], true)) {
    header('Location: ' . route_url('dashboard'));
    exit;
}
if (strpos($roleNorm, 'auditor') === 0 || strpos($roleNorm, 'auditee') === 0) {
    header('Location: ' . review_url('jadwal'));
    exit;
}

header('Location: ' . route_url('review'));
exit;
