<?php

return [
  'code' => 'chr_lkj',
  'name' => 'CHR Laporan Kinerja',
  'version' => 1,
  'renderer' => 'dynamic',
  'sections' => [
    [
      'key' => 'identitas',
      'title' => 'Identitas Dokumen',
      'fields' => [
        ['key' => 'judul_dokumen', 'label' => 'Judul Dokumen', 'type' => 'text', 'width' => 6],
        ['key' => 'subjudul', 'label' => 'Subjudul', 'type' => 'text', 'width' => 6],
        ['key' => 'entitas', 'label' => 'Entitas Akuntabilitas', 'type' => 'text', 'width' => 6],
        ['key' => 'unit_yang_direviu', 'label' => 'Unit yang Direviu', 'type' => 'text', 'width' => 6],
        ['key' => 'nomor_chr', 'label' => 'Nomor CHR', 'type' => 'text', 'width' => 4],
        ['key' => 'tanggal_chr', 'label' => 'Tanggal CHR', 'type' => 'date', 'width' => 4],
        ['key' => 'periode', 'label' => 'Tahun/Periode Laporan Kinerja', 'type' => 'text', 'width' => 4],
        ['key' => 'nomor_surat_tugas', 'label' => 'Nomor Surat Tugas', 'type' => 'text', 'width' => 6],
        ['key' => 'tanggal_surat_tugas', 'label' => 'Tanggal Surat Tugas', 'type' => 'date', 'width' => 6],
      ],
    ],
    [
      'key' => 'penyusun',
      'title' => 'Penyusun Dokumen',
      'fields' => [
        ['key' => 'disusun_oleh', 'label' => 'Disusun oleh', 'type' => 'text', 'width' => 4],
        ['key' => 'tanggal_disusun', 'label' => 'Tanggal Disusun', 'type' => 'date', 'width' => 4],
        ['key' => 'direviu_oleh', 'label' => 'Direviu oleh', 'type' => 'text', 'width' => 4],
        ['key' => 'tanggal_direviu', 'label' => 'Tanggal Direviu', 'type' => 'date', 'width' => 4],
        ['key' => 'disetujui_oleh', 'label' => 'Disetujui oleh', 'type' => 'text', 'width' => 4],
        ['key' => 'tanggal_disetujui', 'label' => 'Tanggal Disetujui', 'type' => 'date', 'width' => 4],
      ],
    ],
    [
      'key' => 'uraian_reviu',
      'title' => 'Uraian Catatan Hasil Reviu',
      'fields' => [
        ['key' => 'dasar_reviu', 'label' => 'Dasar Pelaksanaan Reviu', 'type' => 'textarea', 'rows' => 4],
        ['key' => 'batas_tanggung_jawab', 'label' => 'Batas Tanggung Jawab Reviu', 'type' => 'textarea', 'rows' => 4],
      ],
    ],
    [
      'key' => 'komponen_reviu',
      'title' => 'Komponen Reviu Laporan Kinerja',
      'fields' => [
        [
          'key' => 'daftar_komponen',
          'label' => 'Komponen Reviu',
          'type' => 'repeater',
          'min_rows' => 4,
          'columns' => [
            ['key' => 'komponen', 'label' => 'Komponen', 'type' => 'select', 'options' => ['Format Laporan Kinerja', 'Mekanisme Penyusunan Laporan Kinerja', 'Substansi Laporan Kinerja', 'Catatan Permasalahan Lainnya', 'Lainnya']],
            ['key' => 'indeks_kkr', 'label' => 'Indeks KKR', 'type' => 'text'],
            ['key' => 'uraian', 'label' => 'Uraian Hasil Reviu', 'type' => 'textarea'],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'koreksi_perbaikan',
      'title' => 'Koreksi/Perbaikan',
      'fields' => [
        ['key' => 'sudah_dilakukan', 'label' => 'Koreksi/Perbaikan yang Sudah Dilakukan', 'type' => 'textarea', 'rows' => 5],
        ['key' => 'belum_dilakukan', 'label' => 'Koreksi/Perbaikan yang Belum Dilakukan', 'type' => 'textarea', 'rows' => 5],
      ],
    ],
    [
      'key' => 'rekomendasi',
      'title' => 'Rekomendasi dan Kesimpulan',
      'fields' => [
        ['key' => 'rekomendasi', 'label' => 'Rekomendasi', 'type' => 'textarea', 'rows' => 5],
        ['key' => 'kesimpulan', 'label' => 'Kesimpulan', 'type' => 'textarea', 'rows' => 5],
      ],
    ],
    [
      'key' => 'pengesahan',
      'title' => 'Pengesahan',
      'fields' => [
        ['key' => 'pejabat_menyetujui', 'label' => 'Pejabat Menyetujui', 'type' => 'signature'],
        ['key' => 'ketua_tim', 'label' => 'Ketua Tim', 'type' => 'signature'],
        ['key' => 'anggota_tim', 'label' => 'Anggota Tim', 'type' => 'repeater', 'min_rows' => 1, 'columns' => [
          ['key' => 'user_id', 'label' => 'Pegawai', 'type' => 'text'],
          ['key' => 'nama', 'label' => 'Nama', 'type' => 'text'],
          ['key' => 'nip', 'label' => 'NIP', 'type' => 'text'],
          ['key' => 'jabatan', 'label' => 'Jabatan', 'type' => 'text'],
          ['key' => 'unit_id', 'label' => 'Unit ID', 'type' => 'text'],
          ['key' => 'unit', 'label' => 'Unit Kerja', 'type' => 'text'],
          ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
          ['key' => 'signature', 'label' => 'Tanda Tangan', 'type' => 'signature_data'],
        ]],
      ],
    ],
  ],
  'export_sections' => [
    ['key' => 'identitas', 'title' => 'Identitas Dokumen', 'fields' => ['entitas', 'unit_yang_direviu', 'nomor_chr', 'tanggal_chr', 'periode', 'nomor_surat_tugas', 'tanggal_surat_tugas']],
    ['key' => 'penyusun', 'title' => 'Penyusun Dokumen'],
    ['key' => 'uraian_reviu', 'title' => 'Uraian Catatan Hasil Reviu'],
    ['key' => 'komponen_reviu', 'title' => 'Komponen Reviu Laporan Kinerja', 'block_repeaters' => true],
    ['key' => 'koreksi_perbaikan', 'title' => 'Koreksi/Perbaikan yang Sudah Dilakukan', 'only' => ['sudah_dilakukan']],
    ['key' => 'koreksi_perbaikan', 'title' => 'Koreksi/Perbaikan yang Belum Dilakukan', 'only' => ['belum_dilakukan']],
    ['key' => 'rekomendasi', 'title' => 'Rekomendasi', 'only' => ['rekomendasi']],
    ['key' => 'rekomendasi', 'title' => 'Kesimpulan', 'only' => ['kesimpulan']],
    ['key' => 'pengesahan', 'title' => 'Pengesahan'],
  ],
];
