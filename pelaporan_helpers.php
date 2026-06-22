<?php
/**
 * Helper utilitas untuk manajemen status pelaporan.
 * Dipakai oleh pelaporan.php, pelaporan_detail.php, dashboard.php, dan login.php.
 */

if (!function_exists('pelaporan_status_catalog')) {
    function pelaporan_status_catalog(): array {
        return [
            'Masuk' => [
                'label'       => 'Pengaduan Masuk',
                'badge_class' => 'bg-secondary',
                'description' => 'Laporan baru diterima oleh admin dan menunggu diteruskan ke Kepala SKI.'
            ],
            'Verifikasi SKI' => [
                'label'       => 'Verifikasi Kepala SKI',
                'badge_class' => 'bg-info text-dark',
                'description' => 'Kepala SKI menilai kesesuaian laporan sebelum diproses lebih lanjut.'
            ],
            'Perlu Rekap Admin' => [
                'label'       => 'Perlu Rekap Admin',
                'badge_class' => 'bg-primary',
                'description' => 'Admin menyiapkan rekap dan dokumen untuk diteruskan ke Direktur.'
            ],
            'Verifikasi Direktur' => [
                'label'       => 'Verifikasi Direktur',
                'badge_class' => 'bg-warning text-dark',
                'description' => 'Direktur memverifikasi hasil rekap sebelum diberikan ke unit tindak lanjut.'
            ],
            'Perlu Perbaikan SKI' => [
                'label'       => 'Perlu Perbaikan SKI',
                'badge_class' => 'bg-warning text-dark',
                'description' => 'Direktur mengembalikan laporan ke SKI untuk perbaikan atau klarifikasi.'
            ],
            'Diteruskan ke Unit TL' => [
                'label'       => 'Diteruskan ke Unit TL',
                'badge_class' => 'bg-primary',
                'description' => 'Laporan sudah diteruskan ke unit terkait untuk tindak lanjut.'
            ],
            'Monitoring TL' => [
                'label'       => 'Monitoring TL',
                'badge_class' => 'bg-info text-dark',
                'description' => 'SKI/Admin memantau progres tindak lanjut dari unit terkait.'
            ],
            'Kembali ke Pelapor' => [
                'label'       => 'Kembali ke Pelapor',
                'badge_class' => 'bg-danger',
                'description' => 'Laporan dinyatakan tidak sesuai dan dikembalikan kepada pelapor untuk tindak lanjut.',
                'final'       => true
            ],
            'Arsip' => [
                'label'       => 'Arsip Laporan',
                'badge_class' => 'bg-success',
                'description' => 'Seluruh proses selesai, laporan diarsipkan.',
                'final'       => true
            ],
        ];
    }

    function pelaporan_status_alias_map(): array {
        return [
            'Belum diproses' => 'Masuk',
            'Diproses'       => 'Verifikasi SKI',
            'Proses'         => 'Verifikasi SKI',
            'Selesai'        => 'Arsip',
            'Ditolak'        => 'Kembali ke Pelapor',
            'Ditolak SKI'    => 'Kembali ke Pelapor'
        ];
    }

    function pelaporan_status_db_map(): array {
        return [
            'Masuk' => ['Masuk', 'Belum diproses'],
            'Verifikasi SKI' => ['Verifikasi SKI', 'Proses', 'Diproses'],
            'Perlu Rekap Admin' => ['Perlu Rekap Admin'],
            'Verifikasi Direktur' => ['Verifikasi Direktur'],
            'Perlu Perbaikan SKI' => ['Perlu Perbaikan SKI'],
            'Diteruskan ke Unit TL' => ['Diteruskan ke Unit TL'],
            'Monitoring TL' => ['Monitoring TL'],
            'Kembali ke Pelapor' => ['Kembali ke Pelapor', 'Ditolak', 'Ditolak SKI'],
            'Arsip' => ['Arsip', 'Selesai']
        ];
    }

    function pelaporan_status_canonical(string $raw): string {
        $raw = trim($raw);
        $aliases = pelaporan_status_alias_map();
        return $aliases[$raw] ?? $raw;
    }

    function pelaporan_status_label(string $raw): string {
        $canonical = pelaporan_status_canonical($raw);
        $catalog = pelaporan_status_catalog();
        return $catalog[$canonical]['label'] ?? $canonical;
    }

    function pelaporan_status_badge(string $raw): string {
        $canonical = pelaporan_status_canonical($raw);
        $catalog = pelaporan_status_catalog();
        return $catalog[$canonical]['badge_class'] ?? 'bg-secondary';
    }

    function pelaporan_status_description(string $raw): string {
        $canonical = pelaporan_status_canonical($raw);
        $catalog = pelaporan_status_catalog();
        return $catalog[$canonical]['description'] ?? '';
    }

    function pelaporan_status_db_values(string $canonical): array {
        $map = pelaporan_status_db_map();
        return $map[$canonical] ?? [$canonical];
    }

    function pelaporan_status_options(): array {
        $catalog = pelaporan_status_catalog();
        $order = ['Masuk','Verifikasi SKI','Perlu Rekap Admin','Verifikasi Direktur','Perlu Perbaikan SKI','Diteruskan ke Unit TL','Monitoring TL','Kembali ke Pelapor','Arsip'];
        $opts = [];
        foreach ($order as $key) {
            if (isset($catalog[$key])) {
                $opts[$key] = $catalog[$key]['label'];
            }
        }
        return $opts;
    }

    function pelaporan_is_final_status(string $raw): bool {
        $canonical = pelaporan_status_canonical($raw);
        $catalog = pelaporan_status_catalog();
        return !empty($catalog[$canonical]['final']);
    }

    function pelaporan_actor_group(array $user): string {
        $canonical = strtolower($user['peran'] ?? '');
        $raw = strtolower($user['peran_raw'] ?? $canonical);

        if (in_array($canonical, ['super_admin','admin','moderator'], true) || in_array($raw, ['super_admin','admin','moderator'], true)) {
            return 'admin';
        }
        if (in_array($canonical, ['kepala_ski'], true) || in_array($raw, ['kepala_ski','auditor_ka'], true)) {
            return 'kepala_ski';
        }
        if (in_array($canonical, ['direktur'], true) || in_array($raw, ['direktur','auditee_direktur'], true)) {
            return 'direktur';
        }
        return $canonical ?: 'user';
    }

    function pelaporan_transition_matrix(): array {
        return [
            'admin' => [
                'Masuk' => [
                    ['to' => 'Verifikasi SKI', 'label' => 'Teruskan ke Kepala SKI', 'note_required' => false, 'note_placeholder' => 'Catatan untuk Kepala SKI (opsional)'],
                ],
                'Perlu Rekap Admin' => [
                    [
                        'to' => 'Verifikasi Direktur',
                        'label' => 'Kirim ke Direktur',
                        'note_required' => true,
                        'note_placeholder' => 'Ringkasan rekap untuk Direktur (wajib diisi)',
                        'allow_attachment' => true,
                    ],
                ],
                'Diteruskan ke Unit TL' => [
                    ['to' => 'Monitoring TL', 'label' => 'Mulai monitoring TL', 'note_required' => false, 'note_placeholder' => 'Catatan awal monitoring (opsional)'],
                ],
                'Monitoring TL' => [
                    ['to' => 'Arsip', 'label' => 'Arsipkan laporan', 'note_required' => false, 'note_placeholder' => 'Catatan penutupan (opsional)'],
                ],
            ],
            'kepala_ski' => [
                'Verifikasi SKI' => [
                    ['to' => 'Kembali ke Pelapor', 'label' => 'Kembalikan ke pelapor', 'note_required' => true, 'note_placeholder' => 'Alasan pengembalian ke pelapor (wajib)'],
                    ['to' => 'Perlu Rekap Admin', 'label' => 'Setujui (lanjut ke admin)', 'note_required' => false, 'note_placeholder' => 'Catatan untuk admin (opsional)'],
                ],
                'Perlu Perbaikan SKI' => [
                    ['to' => 'Perlu Rekap Admin', 'label' => 'Perbaikan selesai, kirim ke admin', 'note_required' => false, 'note_placeholder' => 'Catatan tambahan (opsional)'],
                    ['to' => 'Kembali ke Pelapor', 'label' => 'Kembalikan ke pelapor', 'note_required' => true, 'note_placeholder' => 'Alasan pengembalian ke pelapor (wajib)'],
                ],
                'Monitoring TL' => [
                    ['to' => 'Arsip', 'label' => 'Arsipkan laporan', 'note_required' => false, 'note_placeholder' => 'Catatan penutupan (opsional)'],
                ],
            ],
            'direktur' => [
                'Verifikasi Direktur' => [
                    ['to' => 'Perlu Perbaikan SKI', 'label' => 'Kembalikan ke SKI', 'note_required' => true, 'note_placeholder' => 'Alasan pengembalian ke SKI (wajib)'],
                    ['to' => 'Diteruskan ke Unit TL', 'label' => 'Setujui & Teruskan ke Unit TL', 'note_required' => false, 'note_placeholder' => 'Instruksi ke unit TL (opsional)'],
                ],
                'Diteruskan ke Unit TL' => [
                    ['to' => 'Monitoring TL', 'label' => 'Mulai tindak lanjut (Monitoring TL)', 'note_required' => false, 'note_placeholder' => 'Pengantar monitoring (opsional)'],
                ],
                'Monitoring TL' => [
                    ['to' => 'Arsip', 'label' => 'Tindak lanjut selesai (Arsipkan)', 'note_required' => false, 'note_placeholder' => 'Catatan penutupan (opsional)'],
                ],
            ],
        ];
    }

    function pelaporan_available_transitions(string $actor, string $status): array {
        $matrix = pelaporan_transition_matrix();
        return $matrix[$actor][$status] ?? [];
    }

    function pelaporan_visible_statuses_for_actor(string $actor): array {
        $all = array_keys(pelaporan_status_catalog());
        switch ($actor) {
            case 'direktur':
                return [
                    'Verifikasi Direktur',
                    'Perlu Perbaikan SKI',
                    'Diteruskan ke Unit TL',
                    'Monitoring TL',
                    'Arsip',
                ];
            default:
                return $all;
        }
    }

    function pelaporan_status_bucket(string $canonical): string {
        switch ($canonical) {
            case 'Masuk':
                return 'masuk';
            case 'Arsip':
                return 'arsip';
            case 'Kembali ke Pelapor':
                return 'kembali';
            default:
                return 'proses';
        }
    }
}
