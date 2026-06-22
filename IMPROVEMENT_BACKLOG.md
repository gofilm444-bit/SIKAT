# IMPROVEMENT BACKLOG — ski_new

Legenda: Prioritas (P0/P1/P2), Estimasi (S/M/L), Dampak (High/Med/Low)

## Security & Reliability
- P0 / M / High — CSRF token di semua form admin + ubah delete GET ? POST (kebijakan/risiko/self_assessment/pengguna).
- P0 / M / High — Refactor SQL CRUD ke prepared statements (kebijakan/risiko/self_assessment/pengguna).
- P0 / S / High — Centralized RBAC guard untuk file helper (deny direct access) + pindahkan ke `/includes`.
- P1 / M / High — Tambah audit log aksi admin (create/edit/delete) di table `audit_log`.
- P1 / S / Med — Matikan `display_errors` di production (gunakan `APP_ENV`).
- P1 / M / Med — Session timeout idle + absolute expiry.
- P1 / S / Med — Password policy minimal + migrasi remove plaintext `password`.

## UX/Feature
- P2 / M / Med — Pagination & server-side search di kebijakan/risiko/self-assessment.
- P2 / M / Med — Bulk import/export CSV untuk user management.
- P2 / S / Med — Inline validation & error messaging konsisten.
- P2 / M / Low — UI polish dan empty-state feedback.

## Ops/Deploy
- P1 / L / Med — Struktur `public/` webroot + private app folder.
- P1 / M / Med — Centralized config via `.env` untuk semua service (DB + SMTP).
- P2 / S / Low — Healthcheck endpoint + basic monitoring.

