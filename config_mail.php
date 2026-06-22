<?php
// ===== Konfigurasi Email/SMTP =====
require_once __DIR__ . '/config/env.php';

// Mode: 'smtp' atau 'mail' (fallback, gunakan kalau SMTP belum siap)
define('MAIL_MODE', env('APP_MAIL_MODE', 'smtp')); // 'smtp' | 'mail'

// Identitas pengirim (From)
define('MAIL_FROM', env('APP_MAIL_FROM', 'no-reply@contoh.ac.id'));
define('MAIL_FROM_NAME', env('APP_MAIL_FROM_NAME', 'SIKAT Notifier'));

// SMTP settings (abaikan jika mode 'mail')
define('SMTP_HOST', env('APP_SMTP_HOST', ''));
define('SMTP_PORT', (int)env('APP_SMTP_PORT', 587));
define('SMTP_SECURE', env('APP_SMTP_SECURE', 'tls')); // 'tls' | 'ssl' | ''
define('SMTP_USER', env('APP_SMTP_USER', ''));
define('SMTP_PASS', env('APP_SMTP_PASS', ''));

// Judul default
define('MAIL_SUBJECT_PREFIX', env('APP_MAIL_SUBJECT_PREFIX', '[SIKAT] '));
