<?php
require_once __DIR__ . '/session_hardening.php';

if (!function_exists('auth_login_url')) {
    function auth_login_url(): string {
        if (function_exists('route_url')) {
            return route_url('login');
        }
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $script = trim($script, '/');
        if ($script === '') {
            return '/login.php';
        }
        $parts = explode('/', $script);
        $first = $parts[0] ?? '';
        if ($first !== '' && strpos($first, '.php') === false) {
            return '/' . $first . '/login.php';
        }
        return '/login.php';
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array {
        if (isset($_SESSION['auth']) && is_array($_SESSION['auth'])) {
            return $_SESSION['auth'];
        }
        if (isset($_SESSION['user'])) {
            return is_array($_SESSION['user']) ? $_SESSION['user'] : ['role' => (string)$_SESSION['user']];
        }
        if (isset($_SESSION['admin'])) {
            return is_array($_SESSION['admin']) ? ($_SESSION['admin'] + ['role' => 'admin']) : ['role' => 'admin'];
        }
        if (isset($_SESSION['pegawai'])) {
            return is_array($_SESSION['pegawai']) ? ($_SESSION['pegawai'] + ['role' => 'pegawai']) : ['role' => 'pegawai'];
        }
        return null;
    }
}

if (!function_exists('current_role')) {
    function current_role(): string {
        $user = current_user();
        if (!$user) {
            return '';
        }
        $role = '';
        if (is_array($user)) {
            $role = (string)($user['role'] ?? $user['peran'] ?? $user['level'] ?? $user['tipe'] ?? $user['user_role'] ?? '');
            if ($role === '' && !empty($user['is_admin'])) {
                $role = 'admin';
            }
        } else {
            $role = (string)$user;
        }
        return $role;
    }
}

if (!function_exists('auth_normalize_role')) {
    function auth_normalize_role(string $role): string {
        $role = strtolower(trim($role));
        $role = str_replace([' ', '-'], '_', $role);
        $role = preg_replace('/[^a-z0-9_]+/', '', $role);
        return $role;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!current_user()) {
            header('Location: ' . auth_login_url());
            exit;
        }
    }
}

if (!function_exists('require_role')) {
    function require_role($roles): void {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $roles = is_array($roles) ? $roles : [$roles];
        $roles = array_map(function ($r) { return auth_normalize_role((string)$r); }, $roles);
        $role = auth_normalize_role(current_role());
        if ($role === '' || !in_array($role, $roles, true)) {
            http_response_code(403);
            echo 'Akses ditolak.';
            exit;
        }
    }
}
