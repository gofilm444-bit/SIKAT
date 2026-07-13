<?php

return [
  'code' => 'chr_pipk',
  'name' => 'CHR PIPK Tingkat Satker',
  'version' => 1,
  'renderer' => 'dynamic',
  'sections' => [
    [
      'key' => 'identitas',
      'title' => 'Identitas Dokumen',
      'fields' => [
        ['key' => 'judul_dokumen', 'label' => 'Judul Dokumen', 'type' => 'text', 'width' => 6],
        ['key' => 'subjudul', 'label' => 'Subjudul', 'type' => 'text', 'width' => 6],
        ['key' => 'entitas', 'label' => 'Entitas', 'type' => 'text', 'width' => 6],
        ['key' => 'unit_yang_direviu', 'label' => 'Unit yang Direviu', 'type' => 'text', 'width' => 6],
        ['key' => 'nomor_chr', 'label' => 'Nomor CHR', 'type' => 'text', 'width' => 4],
        ['key' => 'tanggal_chr', 'label' => 'Tanggal CHR', 'type' => 'date', 'width' => 4],
        ['key' => 'tahun_anggaran', 'label' => 'Tahun Anggaran', 'type' => 'text', 'width' => 4],
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
      'key' => 'tingkat_unit',
      'title' => 'Tingkat Unit Akuntansi',
      'fields' => [
        [
          'key' => 'unit_akuntansi',
          'label' => 'Unit Akuntansi',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'tingkat', 'label' => 'Tingkat', 'type' => 'select', 'options' => ['UPPA', 'UAPPA-E1', 'UAPPA-W', 'UAKPA']],
            ['key' => 'dipilih', 'label' => 'Dipilih', 'type' => 'select', 'options' => ['Ya', 'Tidak']],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'uraian_reviu',
      'title' => 'Uraian Catatan Hasil Reviu PIPK',
      'fields' => [
        ['key' => 'dasar_reviu', 'label' => 'Dasar Pelaksanaan Reviu', 'type' => 'textarea', 'rows' => 4],
        ['key' => 'teknik_reviu', 'label' => 'Teknik Reviu', 'type' => 'textarea', 'rows' => 4],
      ],
    ],
    [
      'key' => 'komponen_pipk',
      'title' => 'Komponen Reviu PIPK',
      'fields' => [
        [
          'key' => 'daftar_komponen',
          'label' => 'Komponen PIPK',
          'type' => 'repeater',
          'min_rows' => 7,
          'columns' => [
            ['key' => 'komponen', 'label' => 'Komponen', 'type' => 'select', 'options' => ['Identifikasi Risiko dan Kecukupan Rancangan Pengendalian', 'Perbaikan Identifikasi Risiko dan Pengendalian', 'Pengujian Pengendalian Intern Tingkat Entitas', 'Pengujian PUTIK', 'Pengujian Atribut Pengendalian', 'Pengujian Pengendalian Aplikasi', 'Penilaian Efektivitas Implementasi Pengendalian', 'Lainnya']],
            ['key' => 'indeks_kkr', 'label' => 'Indeks KKR', 'type' => 'text'],
            ['key' => 'uraian', 'label' => 'Uraian Catatan Hasil Reviu', 'type' => 'textarea'],
            ['key' => 'hasil', 'label' => 'Hasil/Simpulan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'hasil_temuan',
      'title' => 'Temuan dan Koreksi',
      'fields' => [
        ['key' => 'simpulan_pipk', 'label' => 'Simpulan PIPK', 'type' => 'textarea', 'rows' => 4],
        ['key' => 'koreksi_belum_dilakukan', 'label' => 'Koreksi/Perbaikan yang Belum Dilakukan/Tidak Disetujui', 'type' => 'textarea', 'rows' => 5],
        ['key' => 'rekomendasi', 'label' => 'Rekomendasi', 'type' => 'textarea', 'rows' => 5],
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
    ['key' => 'identitas', 'title' => 'Identitas Dokumen', 'fields' => ['entitas', 'unit_yang_direviu', 'nomor_chr', 'tanggal_chr', 'tahun_anggaran', 'nomor_surat_tugas', 'tanggal_surat_tugas']],
    ['key' => 'penyusun', 'title' => 'Penyusun Dokumen'],
    ['key' => 'tingkat_unit', 'title' => 'Tingkat Unit Akuntansi'],
    ['key' => 'uraian_reviu', 'title' => 'Uraian Catatan Hasil Reviu PIPK'],
    ['key' => 'komponen_pipk', 'title' => 'Komponen Reviu PIPK', 'block_repeaters' => true],
    ['key' => 'hasil_temuan', 'title' => 'Simpulan PIPK', 'only' => ['simpulan_pipk']],
    ['key' => 'hasil_temuan', 'title' => 'Koreksi/Perbaikan yang Belum Dilakukan/Tidak Disetujui', 'only' => ['koreksi_belum_dilakukan']],
    ['key' => 'hasil_temuan', 'title' => 'Rekomendasi', 'only' => ['rekomendasi']],
    ['key' => 'pengesahan', 'title' => 'Pengesahan'],
  ],
];
