# PRODUCTION_READINESS

Tanggal: 2026-01-26

## Checklist Environment Variables
- `APP_ENV=production`
- `DB_HOST=...`
- `DB_USER=...`
- `DB_PASS=...`
- `DB_NAME=...`
- `APP_SESSION_IDLE=1800` (detik, opsional)
- `APP_MAIL_MODE=smtp|mail`
- `APP_SMTP_HOST=...`
- `APP_SMTP_PORT=587`
- `APP_SMTP_SECURE=tls|ssl|""`
- `APP_SMTP_USER=...`
- `APP_SMTP_PASS=...`
- `APP_MAIL_FROM=...`
- `APP_MAIL_FROM_NAME=...`

## Files/Tools to Remove or Restrict
- `tools/security_selfcheck.php` (hapus setelah testing lokal)
- `hash.php` (jangan di production)
- Pastikan `.env` tidak dapat diakses publik
- Pastikan `/tools/*` diblokir di production (HTTP 403)

## Permissions & Server Notes
- Upload directory harus writable oleh web server:
  - `uploads/` dan subfoldernya
  - `upload/` (jika digunakan)
- Pastikan eksekusi PHP dinonaktifkan di folder upload (sudah ada .htaccess).
- Pastikan direct access ke `/uploads/` dan `/upload/` diblokir (Apache/Nginx snippet).
- Pastikan direct access ke `/storage/` diblokir (digunakan untuk rate limit).
- Aktifkan HTTPS dan pastikan `X-Forwarded-Proto` diset benar jika di balik reverse proxy.
- Pastikan error display nonaktif di production (gunakan `APP_ENV=production`).

## Security & Access Control
- Semua halaman sensitif harus lewat `bootstrap.php` (auth guard).
- Download lampiran wajib melalui gateway:
  - `attachment_download.php` untuk pelaporan
  - `download.php` untuk dokumen reviu
- Non-admin hanya dapat mengakses data yang ditugaskan.

## Post-Deploy Smoke Tests
1) Login/logout normal, pastikan redirect benar.
2) Coba akses `/uploads/...` langsung -> 403.
3) Coba akses `attachment_download.php?id=...` tanpa login -> redirect/403.
4) Coba akses `/tools/health.php` di production -> 403.
5) Jalankan cron early warning (CLI) dan pastikan tidak error.
6) Akses halaman admin dengan akun non-admin -> 403.


## Health Check Endpoint
- URL: `/tools/health.php?token=APP_RESET_TOKEN`
- **Local only** (`APP_ENV=local`), selain itu wajib 403
- JSON output: `/tools/health.php?format=json&token=APP_RESET_TOKEN`


## Session Policy
- Idle timeout: 30 menit (APP_SESSION_IDLE=1800)
- Absolute lifetime: 6 jam (APP_SESSION_ABSOLUTE=21600)
- Login akan logout otomatis ketika salah satu batas tercapai.

## Login Anti-Bruteforce
- Progressive delay per (IP+username): gagal ke-3 delay 2s, gagal ke-4 delay 5s.
- Lock user setelah 5 gagal selama 15 menit.
- IP-wide cap (dinonaktifkan): 30 gagal dalam 15 menit => lock IP 15 menit.
- Pesan error selalu generik (anti-enumeration).

## Admin Contact
- Set `APP_ADMIN_WA` dan `APP_ADMIN_NAME` agar tombol "Hubungi Admin" muncul di modal login.

## Local-only tools (remove before production)
- tools/create_superadmin.php
- tools/reset_lockout.php
- tools/health.php
- tools/security_selfcheck.php

## Debug (Local Only)
- APP_DEBUG_AUTH=1 akan menulis log auth ke storage/logs/auth_debug.log (hapus/disable di production).
