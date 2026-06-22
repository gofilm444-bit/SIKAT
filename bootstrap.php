<?php
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_hardening.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
$accessMap = require __DIR__ . '/config/access_map.php';

$public = $accessMap['public'] ?? [];
$adminOnly = $accessMap['admin_only'] ?? [];

$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptPath = ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');

// Global logout handler (works on any page with logout form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'logout')) {
    // Do not block logout on CSRF mismatch; session could already be expired.
    $token = (string)($_POST['csrf'] ?? '');
    $current = (string)($_SESSION['csrf_token'] ?? '');
    if ($token !== '' && $current !== '' && !hash_equals($current, $token)) {
        // Ignore mismatch to avoid trapping users on logout.
    }
    $loginUrl = function_exists('auth_login_url') ? auth_login_url() : '/login.php';
    $sep = (strpos($loginUrl, '?') === false) ? '?' : '&';
    force_logout_and_redirect($loginUrl . $sep . 'logged_out=1');
}

if (!function_exists('access_map_match')) {
    function access_map_match(string $name, string $path, array $patterns): bool {
        foreach ($patterns as $pattern) {
            $pattern = (string)$pattern;
            if ($pattern === '') {
                continue;
            }
            if (strpos($pattern, '*') !== false) {
                $pathMatch = fnmatch($pattern, $path);
                $nameMatch = fnmatch($pattern, $name);
                if ($pathMatch || $nameMatch) {
                    return true;
                }
                continue;
            }
            if (substr($pattern, -2) === '/*') {
                $prefix = rtrim(substr($pattern, 0, -2), '/');
                if (strpos($path, $prefix . '/') === 0) {
                    return true;
                }
                continue;
            }
            if ($pattern === $name || $pattern === $path) {
                return true;
            }
        }
        return false;
    }
}

if (!access_map_match($scriptName, $scriptPath, $public)) {
    require_login();
}

if (access_map_match($scriptName, $scriptPath, $adminOnly)) {
    require_role(['admin', 'superadmin', 'super_admin']);
}
