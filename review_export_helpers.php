<?php
if (!function_exists('role_slug')) {
  function role_slug(string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
  }
}
if (!function_exists('current_role')) {
  function current_role(): string {
    return role_slug($_SESSION['user']['peran'] ?? 'user');
  }
}
if (!function_exists('user_id')) {
  function user_id(){
    return $_SESSION['user']['id'] ?? null;
  }
}
if (!function_exists('user_email')) {
  function user_email(){
    return $_SESSION['user']['email'] ?? null;
  }
}
if (!function_exists('is_admin_like')) {
  function is_admin_like(string $role = null): bool {
    $slug = $role ? role_slug($role) : current_role();
    return in_array($slug, ['admin','super_admin','superadmin','moderator'], true);
  }
}
if (!function_exists('is_auditor')) {
  function is_auditor(string $role = null): bool {
    $slug = $role ? role_slug($role) : current_role();
    return $slug === 'auditor' || strpos($slug, 'auditor_') === 0;
  }
}
if (!function_exists('is_director_like')) {
  function is_director_like(string $role = null): bool {
    $slug = $role ? role_slug($role) : current_role();
    return in_array($slug, ['direktur','auditee_direktur'], true);
  }
}
if (!function_exists('is_auditee')) {
  function is_auditee(string $role = null): bool {
    $slug = $role ? role_slug($role) : current_role();
    return $slug === 'auditee' || strpos($slug, 'auditee') === 0;
  }
}
if (!function_exists('review_is_assigned')) {
  function review_is_assigned(mysqli $conn, int $reviuId, ?string $roleFilter = null): bool {
    if ($reviuId < 1) { return false; }
    $uid = user_id();
    $email = user_email();
    $sql = "SELECT 1 FROM reviu_penugasan WHERE reviu_id=? AND (user_id=? OR (email<>'' AND email=?))";
    if ($roleFilter) { $sql .= " AND role=?"; }
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return false; }
    if ($roleFilter) { $stmt->bind_param("iiss", $reviuId, $uid, $email, $roleFilter); }
    else { $stmt->bind_param("iis", $reviuId, $uid, $email); }
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
  }
}
if (!function_exists('review_require_access')) {
  function review_require_access(mysqli $conn, int $reviuId, ?string $roleFilter = null): void {
    if ($reviuId < 1) {
      http_response_code(400); exit('Parameter rid wajib.');
    }
    if (is_admin_like()) { return; }
    if ($roleFilter) {
      if (!review_is_assigned($conn, $reviuId, $roleFilter)) { http_response_code(403); exit('Akses ditolak.'); }
    } else {
      if (!review_is_assigned($conn, $reviuId, null)) { http_response_code(403); exit('Akses ditolak.'); }
    }
  }
}
