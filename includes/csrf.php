<?php
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(string $token): void {
        $current = $_SESSION['csrf_token'] ?? '';
        if ($current === '' || !hash_equals($current, $token)) {
            http_response_code(400);
            die('Invalid CSRF token');
        }
    }
}
