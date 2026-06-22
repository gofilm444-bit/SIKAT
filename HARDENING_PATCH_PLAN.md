# HARDENING PATCH PLAN (1–2 Minggu)

## Target
- Stabilkan keamanan, akses, dan maintainability untuk production.

## Phase 1 — Refactor Struktur & Access
1) **Pisahkan webroot**
   - Buat `/public` sebagai webroot.
   - Pindahkan file entrypoint ke `/public`.
   - Pastikan `/app` atau `/includes` tidak dapat diakses langsung.

2) **Middleware Guard**
   - Terapkan `bootstrap.php` (auth + headers + env) sebagai front controller.
   - Pastikan semua route melewati guard.

3) **Access Map + RBAC**
   - Definisikan permission per role dan mapping route.
   - Tambahkan check per aksi (create/edit/delete).

## Phase 2 — Data & Security Hardening
4) **Prepared Statements Full**
   - Refactor semua CRUD query menjadi prepared statement.

5) **CSRF & Input Validation**
   - Standardisasi CSRF di seluruh form.
   - Tambah sanitasi & validation helper terpusat.

6) **Audit Log**
   - Table `audit_log` untuk action admin.
   - Log user, IP, action, payload ringkas.

7) **Secret Management**
   - Pindahkan `config_mail.php` ke env.
   - Tambahkan `.env` loader dan config cache.

## Phase 3 — Observability & Ops
8) **Error Handling**
   - Prod: `display_errors=0`, log to file.
   - Tambah error pages (403/404/500).

9) **Security Headers & CSP**
   - Finalize CSP allowlist untuk CDN resmi.
   - Pertimbangkan nonce untuk script.

10) **Testing**
   - Tambah smoke test untuk auth/role.
   - Minimal unit test untuk CSRF + permission.

## Migrasi Bertahap
- Minggu 1: CSRF + prepared statements + audit log.
- Minggu 2: refactor struktur `/public`, env secrets, test automation.

## File yang Akan Diubah
- `bootstrap.php`, `includes/*`, `config/*`
- `kebijakan.php`, `pengguna.php`, `risiko.php`, `self_assessment.php`
- `.htaccess`, `deploy/nginx_hardening_snippet.conf`
- (baru) `/public/index.php` + router minimal

