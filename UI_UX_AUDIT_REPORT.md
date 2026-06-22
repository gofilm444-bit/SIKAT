# UI/UX Audit Report (SIKAT)

## Ringkasan Eksekutif
Aplikasi sudah memiliki alur fungsional dasar, namun tampilan dan interaksi masih terasa “campuran” (Bootstrap + CSS kustom + inline). Hal ini membuat pengalaman pengguna kurang konsisten antar halaman, terutama pada navigasi, komponen tombol/form, dan responsif mobile. Perlu standar UI yang lebih seragam, perbaikan aksesibilitas dasar, dan konsolidasi pola feedback agar tampak lebih profesional.

## Pemetaan Struktur & Layout
- Entry points: `index.php`, `login.php`, `dashboard.php`, `review.php`, `pelaporan.php`, `pengguna.php`, `kebijakan.php`, `risiko.php`, `self_assessment.php`, `mail_recipients.php`.
- Layout include: `navbar.php` (menu utama), `style.css` (tema hijau-kuning), Bootstrap dipakai pada sebagian halaman (dashboard/review/pelaporan_detail, dsb).
- Pola routing: query params untuk tab/filter/export (mis. `review.php?tab=...`, `pelaporan.php?action=...`, `kebijakan.php?export_...`).

## Temuan Per Halaman (Ringkas + Rekomendasi)

### 1) Login (`login.php`)
- **Kuat:** ada feedback error/lockout dan countdown; modal login jelas.
- **Isu:** campuran Bootstrap + elemen custom; tombol/alert tidak konsisten dengan halaman lain.
- **Rekomendasi:** standarkan ukuran tombol, spacing pada form, dan konsistensi warna alert; perjelas hierarki heading; tambah focus state yang jelas.

### 2) Dashboard (`dashboard.php`)
- **Kuat:** KPI dan chart rapi; layout hero jelas.
- **Isu:** style berbeda dari halaman CRUD (menggunakan Bootstrap + inline CSS); menu utama di dalam hero; dropdown profil hanya di dashboard.
- **Rekomendasi:** konsistensi navbar global; seragamkan gaya komponen (button, card, table). Pastikan menu profil tersedia secara konsisten di halaman lain atau gunakan `navbar.php` dengan slot profil.

### 3) Review (`review.php`)
- **Kuat:** tata letak komponen cukup modern, tab jelas.
- **Isu:** banyak kontrol padat; tabel besar tanpa pembungkus responsif; dropdown user tidak konsisten dengan halaman lain.
- **Rekomendasi:** gunakan `.table-responsive`/overflow untuk mobile; konsistenkan dropdown profil + logout di semua modul.

### 4) Pelaporan (`pelaporan.php`)
- **Isu utama:** campuran dropdown login & menu dari style berbeda; formulir panjang tanpa section header yang jelas.
- **Rekomendasi:** pisahkan area filter, aksi, dan list data dengan section header; gunakan spacing konsisten; tambah empty-state yang lebih informatif.

### 5) Pelaporan Detail (`pelaporan_detail.php`)
- **Kuat:** informasi status, timeline, lampiran jelas.
- **Isu:** breadcrumb minim; tombol aksi kecil di mobile; tidak ada sticky action.
- **Rekomendasi:** tambahkan “kembali” yang konsisten dan jarak yang lebih lega pada mobile.

### 6) Pengguna (`pengguna.php`)
- **Isu utama:** memakai style lama (`style.css`); form dan tabel padat, label input sebagian hanya placeholder.
- **Rekomendasi:** gunakan label eksplisit dan hint teks; buat layout form bertahap/accordion agar tidak padat.

### 7) Kebijakan (`kebijakan.php`)
- **Isu:** export buttons terlalu menonjol dibanding aksi utama; filter & form tersusun satu blok besar.
- **Rekomendasi:** bedakan aksi “Export” vs “CRUD” secara visual (secondary button); grouping yang jelas.

### 8) Risiko (`risiko.php`) & 9) Self-Assessment (`self_assessment.php`)
- **Isu:** tabel dan form mengandalkan style lama; konsistensi tombol kurang; empty state sederhana.
- **Rekomendasi:** konsolidasi ke gaya komponen yang sama dengan dashboard/pelaporan.

### 10) Mail Recipients (`mail_recipients.php`)
- **Isu:** gaya lebih modern dibanding modul lain; tetapi masih berbeda dari dashboard/pelaporan.
- **Rekomendasi:** satukan gaya tombol, heading, dan spacing agar terasa satu aplikasi.

### 11) Settings (`settings.php`)
- **Isu:** tampilan minimalis berbeda total dari halaman lain.
- **Rekomendasi:** gunakan layout & warna yang sama dengan modul utama.

## Temuan Lintas-Halaman (Konsistensi UI)
1) **Campuran framework** (Bootstrap, inline CSS, dan `style.css`) → tampilan tidak seragam.
2) **Navigasi terfragmentasi**: `navbar.php` dipakai di sebagian halaman, sedangkan `review.php` dan `dashboard.php` menggunakan header sendiri.
3) **Feedback pengguna** tidak konsisten: flash message/alert style berbeda-beda.
4) **Responsif mobile**: tabel panjang tanpa scroll; tombol kecil dan rapat.
5) **Aksesibilitas**: banyak input hanya placeholder; kontras warna kuning-hijau pada teks kecil; fokus keyboard tidak konsisten.

## Quick Wins (1–2 hari)
- Standarkan komponen tombol, alert, dan input (pilih satu style dasar).
- Tambahkan `.table-responsive` untuk tabel utama di semua modul.
- Tambahkan label `<label>` untuk input form penting (Pengguna, Kebijakan, Risiko, Self-Assessment).
- Samakan header/nav dengan satu include yang bisa dipakai seluruh halaman.

## Improvements (1–2 minggu)
- Buat design system mini (warna, typography, spacing, komponen) dan refactor bertahap.
- Konsolidasikan menu utama + dropdown profil agar semua halaman konsisten.
- Tingkatkan UX untuk empty state dan error state (pesan, CTA, guidance).

## Checklist Konsistensi Komponen UI
- [ ] Satu sistem warna utama + aksen konsisten
- [ ] Satu gaya tombol (primary/secondary/danger) di semua halaman
- [ ] Form input selalu punya label + hint
- [ ] Tabel punya header yang konsisten dan wrapper responsif
- [ ] Alert/flash message konsisten
- [ ] Footer konsisten dan ringan
- [ ] Fokus keyboard terlihat jelas
- [ ] Menu navigasi konsisten (lokasi & hierarki)
