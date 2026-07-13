<?php
$__isCli = (PHP_SAPI === 'cli');

if (!function_exists('session_hardening_is_secure')) {
    function session_hardening_is_secure(): bool {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }
}

if (!function_exists('session_hardening_env')) {
    function session_hardening_env(string $key, $default = null) {
        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        return ($val === null || $val === '') ? $default : $val;
    }
}

if (!function_exists('auth_debug_enabled')) {
    function auth_debug_enabled(): bool {
        $env = strtolower((string)session_hardening_env('APP_ENV', ''));
        $debug = (string)session_hardening_env('APP_DEBUG_AUTH', '0');
        return in_array($env, ['local', 'dev', 'development'], true) && $debug === '1';
    }
}

if (!function_exists('auth_debug_log')) {
    function auth_debug_log(string $event, array $extra = []): void {
        if (!auth_debug_enabled()) {
            return;
        }
        $baseDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }
        $payload = [
            'ts' => date('c'),
            'event' => $event,
            'script' => $_SERVER['SCRIPT_NAME'] ?? '',
            'session_id' => session_id(),
            'cookie_params' => session_get_cookie_params(),
            'session_keys' => array_keys($_SESSION ?? []),
            'has_auth' => !empty($_SESSION['auth']),
            'has_user' => !empty($_SESSION['user']),
            'auth_user_id' => $_SESSION['auth']['user_id'] ?? null,
        ];
        if (!empty($extra)) {
            $payload['extra'] = $extra;
        }
        @file_put_contents($baseDir . '/auth_debug.log', json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('session_release')) {
    function session_release(): void {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

if (!function_exists('session_hardening_login_url')) {
    function session_hardening_login_url(): string {
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

if (!function_exists('force_logout')) {
    function force_logout(string $reason = 'manual', string $redirect = ''): void {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if ($redirect === '') {
            if (function_exists('auth_login_url')) {
                $redirect = auth_login_url();
            } else {
                $redirect = session_hardening_login_url();
            }
        }

        if ($reason !== '') {
            $sep = (strpos($redirect, '?') === false) ? '?' : '&';
            $redirect .= $sep . 'reason=' . rawurlencode($reason);
        }

        force_logout_and_redirect($redirect);
    }
}

if (!function_exists('force_logout_and_redirect')) {
    function force_logout_and_redirect(string $redirectUrl): void {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        auth_debug_log('logout_start', ['redirect' => $redirectUrl]);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $path = $params['path'] ?: '/';
            $domain = $params['domain'] ?? '';
            $secure = session_hardening_is_secure();
            $httpOnly = true;

            setcookie(session_name(), '', time() - 42000, $path, $domain, $secure, $httpOnly);
            if ($path !== '/') {
                setcookie(session_name(), '', time() - 42000, '/', $domain, $secure, $httpOnly);
            }
            $dirPath = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            if ($dirPath === '') { $dirPath = '/'; }
            if ($dirPath !== $path && $dirPath !== '/') {
                setcookie(session_name(), '', time() - 42000, $dirPath, $domain, $secure, $httpOnly);
            }
        }
        session_destroy();
        auth_debug_log('logout_cleared', ['redirect' => $redirectUrl]);
        if (!headers_sent()) {
            header('Location: ' . $redirectUrl);
        }
        exit;
    }
}

if (!function_exists('establish_login_session')) {
    function establish_login_session(array $user): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (function_exists('session_hardening_regenerate')) {
            session_hardening_regenerate();
        } else {
            @session_regenerate_id(true);
        }
        unset($_SESSION['admin'], $_SESSION['pegawai']);
        $now = time();
        $role = (string)($user['peran'] ?? $user['role'] ?? '');
        $_SESSION['auth'] = [
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? '',
            'role' => $role,
            'login_at' => $now,
            'last_activity' => $now,
        ];
        $_SESSION['user'] = [
            'id' => $user['id'] ?? null,
            'nama' => $user['nama'] ?? '',
            'username' => $user['username'] ?? '',
            'peran' => $role,
            'peran_raw' => $user['peran_raw'] ?? $role,
            'peran_label' => $user['peran_label'] ?? $role,
            'status' => $user['status'] ?? '',
        ];
        $_SESSION['login_at'] = $now;
        $_SESSION['last_activity'] = $now;
    }
}

if (!$__isCli && session_status() === PHP_SESSION_NONE) {
    $params = session_get_cookie_params();
    $secure = session_hardening_is_secure();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        $path = ($params['path'] ?: '/') . '; samesite=Lax';
        session_set_cookie_params($params['lifetime'], $path, $params['domain'] ?? '', $secure, true);
    }
    session_start();
}

// Session timeout policy (idle + absolute)
if (!$__isCli && session_status() === PHP_SESSION_ACTIVE) {
    $now = time();
    $idleTimeout = (int)session_hardening_env('APP_SESSION_IDLE', 1800);
    $absTimeout  = (int)session_hardening_env('APP_SESSION_ABSOLUTE', 21600);
    if ($idleTimeout < 300) { $idleTimeout = 300; }
    if ($absTimeout < 900) { $absTimeout = 900; }

    // Normalize auth session if legacy user exists
    if (!isset($_SESSION['auth']) && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $user = $_SESSION['user'];
        $_SESSION['auth'] = [
            'user_id'       => $user['id'] ?? null,
            'username'      => $user['username'] ?? '',
            'role'          => $user['peran'] ?? ($user['role'] ?? ''),
            'login_at'      => $_SESSION['login_at'] ?? $now,
            'last_activity' => $_SESSION['last_activity'] ?? $now,
        ];
    }

    $auth = (isset($_SESSION['auth']) && is_array($_SESSION['auth'])) ? $_SESSION['auth'] : null;
    if ($auth) {
        $loginAt = (int)($auth['login_at'] ?? ($_SESSION['login_at'] ?? $now));
        $lastAct = (int)($auth['last_activity'] ?? ($_SESSION['last_activity'] ?? $now));

        if ($loginAt <= 0) { $loginAt = $now; }
        if ($lastAct <= 0) { $lastAct = $now; }

        if (($now - $loginAt) > $absTimeout) {
            force_logout('absolute');
        }
        if (($now - $lastAct) > $idleTimeout) {
            force_logout('idle');
        }

        $_SESSION['auth']['login_at'] = $loginAt;
        $_SESSION['auth']['last_activity'] = $now;
        $_SESSION['login_at'] = $loginAt;
        $_SESSION['last_activity'] = $now;
    }
}

if (!function_exists('session_hardening_regenerate')) {
    function session_hardening_regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
