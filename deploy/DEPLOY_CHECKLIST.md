# SIKAT Deployment Checklist

## 1) Environment (WAJIB)
- APP_ENV=production
- APP_DEBUG_AUTH=0
- APP_RESET_TOKEN=acak kuat
- DB_HOST/DB_USER/DB_PASS/DB_NAME (user non-root, password kuat)

## 2) Web Server (WAJIB)
- DocumentRoot menunjuk ke /public
- Nginx/Apache rules sesuai hardening snippet
- Pastikan /config, /storage, /deploy, /cron, /tools, /vendor tidak bisa diakses publik

## 3) HTTPS (WAJIB)
- Redirect HTTP -> HTTPS
- Sertifikat SSL valid

## 4) PHP Runtime (DISARANKAN)
- display_errors=Off
- log_errors=On
- error_log diarahkan ke file

## 5) Backup & Monitoring (DISARANKAN)
- Backup DB terjadwal
- Log rotation untuk error/access logs

## 6) Verifikasi cepat
- Jalankan: php tools/deploy_check.php
