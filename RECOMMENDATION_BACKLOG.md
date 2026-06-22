# Recommendation Backlog

## Tabel Backlog
| Prioritas | Area | Usaha | Dampak | Risiko | Catatan |
|---|---|---|---|---|---|
| P0 | Security | S | Sangat tinggi | Account takeover | Hapus `LOCAL_ADMIN_CREDENTIALS.md` dari webroot, rotasi kredensial superadmin. |
| P1 | Security | M | Tinggi | Privilege escalation | Blokir `/tools/*` di production atau pindahkan ke CLI‑only. |
| P1 | Security | M | Tinggi | Data leakage | Lindungi `/uploads` dan `/upload` dari akses langsung; gunakan endpoint terotentikasi. |
| P1 | UX | M | Tinggi | Kebingungan navigasi | Konsolidasikan navbar + dropdown profil ke satu layout include. |
| P2 | Security | M | Sedang | XSS | Audit output template dan wajibkan escaping (`e()`/`htmlspecialchars`). |
| P2 | UX | S | Sedang | Inkonistensi tampilan | Standarisasi tombol, alert, dan form (hilangkan campur style). |
| P2 | UX | S | Sedang | Responsif buruk | Tambah `.table-responsive` / overflow pada tabel panjang. |
| P2 | Security | M | Sedang | Misconfig | Perketat CSP, tambah HSTS untuk production. |
| P2 | UX | M | Sedang | Aksesibilitas | Tambahkan label input, focus state, perbaiki kontras. |
| P3 | Observability | S | Rendah | Audit trail | Pastikan logging tanpa data sensitif dan rotasi log. |

## Rekomendasi Urutan Eksekusi (Top 10)
1) Hapus `LOCAL_ADMIN_CREDENTIALS.md` dari webroot + rotasi password admin.
2) Blokir akses publik ke `/tools/*` (atau pindahkan ke CLI).
3) Proteksi folder `uploads/` dan `upload/` dari akses langsung.
4) Standarkan navigasi utama (navbar + dropdown profil) di seluruh halaman.
5) Audit & perbaiki escaping output untuk menutup potensi XSS.
6) Standardisasi komponen UI (tombol, alert, form) agar profesional.
7) Perbaiki responsif tabel dengan wrapper & overflow.
8) Perketat CSP + tambahkan HSTS di produksi.
9) Tambahkan label input dan fokus aksesibilitas dasar.
10) Validasi logging & redaksi data sensitif di log.
