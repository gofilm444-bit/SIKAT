<?php

return [
  'code' => 'chr_pengembangan_pegawai',
  'name' => 'CHR Pengembangan Pegawai',
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
        ['key' => 'periode', 'label' => 'Periode Reviu', 'type' => 'text', 'width' => 4],
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
      'key' => 'komponen_reviu',
      'title' => 'Komponen Reviu',
      'fields' => [
        [
          'key' => 'daftar_komponen',
          'label' => 'Komponen Reviu',
          'type' => 'repeater',
          'min_rows' => 2,
          'columns' => [
            ['key' => 'kelompok', 'label' => 'Kelompok', 'type' => 'select', 'options' => ['Kenaikan Pangkat', 'Kenaikan Jenjang Akademik', 'Pengembangan Kompetensi', 'Pendidikan dan Pelatihan', 'Lainnya']],
            ['key' => 'komponen', 'label' => 'Komponen', 'type' => 'text'],
            ['key' => 'hasil_reviu', 'label' => 'Hasil Reviu', 'type' => 'textarea'],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'hasil_temuan',
      'title' => 'Hasil Temuan',
      'fields' => [
        [
          'key' => 'temuan',
          'label' => 'Hasil Temuan',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'area', 'label' => 'Area/Komponen', 'type' => 'text'],
            ['key' => 'uraian_temuan', 'label' => 'Uraian Temuan', 'type' => 'textarea'],
            ['key' => 'kondisi', 'label' => 'Kondisi', 'type' => 'textarea'],
            ['key' => 'sebab', 'label' => 'Sebab', 'type' => 'textarea'],
            ['key' => 'akibat', 'label' => 'Akibat', 'type' => 'textarea'],
            ['key' => 'rekomendasi_awal', 'label' => 'Rekomendasi Awal', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'tindak_lanjut',
      'title' => 'Tindak Lanjut',
      'fields' => [
        ['key' => 'perlu_ditindaklanjuti', 'label' => 'Hasil yang Perlu Ditindaklanjuti/Diperbaiki', 'type' => 'textarea', 'rows' => 5],
        ['key' => 'sudah_ditindaklanjuti', 'label' => 'Hal yang Sudah Ditindaklanjuti/Diperbaiki', 'type' => 'textarea', 'rows' => 5],
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
    ['key' => 'identitas', 'title' => 'Identitas Dokumen', 'fields' => ['entitas', 'unit_yang_direviu', 'nomor_chr', 'tanggal_chr', 'periode']],
    ['key' => 'penyusun', 'title' => 'Penyusun Dokumen'],
    ['key' => 'komponen_reviu', 'title' => 'Komponen Reviu'],
    ['key' => 'hasil_temuan', 'title' => 'Hasil Temuan', 'block_repeaters' => true],
    ['key' => 'tindak_lanjut', 'title' => 'Hasil yang Perlu Ditindaklanjuti/Diperbaiki', 'only' => ['perlu_ditindaklanjuti']],
    ['key' => 'tindak_lanjut', 'title' => 'Hal yang Sudah Ditindaklanjuti/Diperbaiki', 'only' => ['sudah_ditindaklanjuti']],
    ['key' => 'rekomendasi', 'title' => 'Rekomendasi', 'only' => ['rekomendasi']],
    ['key' => 'rekomendasi', 'title' => 'Kesimpulan', 'only' => ['kesimpulan']],
    ['key' => 'pengesahan', 'title' => 'Pengesahan'],
  ],
];
