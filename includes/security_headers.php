<?php
if (PHP_SAPI === 'cli' || headers_sent()) {
    return;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$host = preg_replace('/:\\d+$/', '', $host);
$serverAddr = (string)($_SERVER['SERVER_ADDR'] ?? '');
$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? ''));
$env = strtolower((string)$env);
$isLocalEnv = in_array($env, ['local', 'dev', 'development'], true);
$isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || in_array($serverAddr, ['127.0.0.1', '::1'], true);

$csp = "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; "
    . "style-src 'self' 'unsafe-inline' https: http:; "
    . "img-src 'self' data: blob: https: http:; "
    . "font-src 'self' data: https: http:; "
    . "connect-src 'self' https: http:; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "frame-ancestors 'self';";

if ($isLocalEnv || $isLocalHost) {
    header('Content-Security-Policy-Report-Only: ' . $csp);
} else {
    header('Content-Security-Policy: ' . $csp);
}
