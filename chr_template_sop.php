<?php

return [
  'code' => 'chr_sop',
  'name' => 'CHR Standar Operasional Prosedur',
  'version' => 1,
  'renderer' => 'dynamic',
  'sections' => [
    [
      'key' => 'identitas',
      'title' => 'Identitas Dokumen',
      'fields' => [
        ['key' => 'header_baris_1', 'label' => 'Header Baris 1', 'type' => 'text', 'width' => 6],
        ['key' => 'header_baris_2', 'label' => 'Header Baris 2', 'type' => 'text', 'width' => 6],
        ['key' => 'judul_dokumen', 'label' => 'Judul Dokumen', 'type' => 'text', 'width' => 6],
        ['key' => 'subjudul', 'label' => 'Subjudul', 'type' => 'text', 'width' => 6],
        ['key' => 'unit_kerja', 'label' => 'Unit Kerja', 'type' => 'text', 'width' => 6],
        ['key' => 'periode', 'label' => 'Periode', 'type' => 'text', 'width' => 6],
        ['key' => 'nomor_chr', 'label' => 'Nomor CHR', 'type' => 'text', 'width' => 6],
        ['key' => 'tanggal_chr', 'label' => 'Tanggal CHR', 'type' => 'date', 'width' => 6],
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
      'key' => 'uraian_tugas',
      'title' => 'Uraian Tugas Jabatan',
      'fields' => [
        ['key' => 'uraian_tugas_jabatan', 'label' => 'Uraian Tugas Jabatan', 'type' => 'textarea', 'rows' => 5],
      ],
    ],
    [
      'key' => 'daftar_sop_section',
      'title' => 'Daftar SOP',
      'fields' => [
        [
          'key' => 'daftar_sop',
          'label' => 'Daftar SOP',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'nama_sop', 'label' => 'Nama SOP', 'type' => 'text'],
            ['key' => 'nomor_dokumen', 'label' => 'Nomor Dokumen', 'type' => 'text'],
            ['key' => 'tanggal_penetapan', 'label' => 'Tanggal Penetapan', 'type' => 'date'],
            ['key' => 'unit_pelaksana', 'label' => 'Unit Pelaksana', 'type' => 'text'],
            ['key' => 'status_ketersediaan', 'label' => 'Status', 'type' => 'select', 'options' => ['Tersedia', 'Tidak Tersedia', 'Perlu Perbaikan', 'Tidak Berlaku']],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'dokumen_sop',
      'title' => 'Dokumen SOP',
      'fields' => [
        [
          'key' => 'pemeriksaan_dokumen_sop',
          'label' => 'Pemeriksaan Dokumen SOP',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'aspek_pemeriksaan', 'label' => 'Aspek Pemeriksaan', 'type' => 'text'],
            ['key' => 'hasil_pemeriksaan', 'label' => 'Hasil Pemeriksaan', 'type' => 'textarea'],
            ['key' => 'kondisi', 'label' => 'Kondisi', 'type' => 'select', 'options' => ['Sesuai', 'Belum Sesuai', 'Tidak Tersedia', 'Tidak Relevan']],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'format_sop',
      'title' => 'Format SOP',
      'fields' => [
        [
          'key' => 'pemeriksaan_format_sop',
          'label' => 'Pemeriksaan Format SOP',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'unsur_format', 'label' => 'Unsur Format', 'type' => 'text'],
            ['key' => 'kesesuaian', 'label' => 'Kesesuaian', 'type' => 'select', 'options' => ['Sesuai', 'Belum Sesuai', 'Tidak Ada', 'Tidak Relevan']],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'pelaksanaan',
      'title' => 'Pelaksanaan SOP',
      'fields' => [
        ['key' => 'uraian_pelaksanaan', 'label' => 'Uraian Pelaksanaan', 'type' => 'textarea', 'rows' => 4],
        ['key' => 'bukti_pelaksanaan', 'label' => 'Bukti Pelaksanaan', 'type' => 'textarea', 'rows' => 4],
        ['key' => 'kendala_pelaksanaan', 'label' => 'Kendala Pelaksanaan', 'type' => 'textarea', 'rows' => 4],
        ['key' => 'kesimpulan_pelaksanaan', 'label' => 'Kesimpulan Pelaksanaan', 'type' => 'textarea', 'rows' => 4],
      ],
    ],
    [
      'key' => 'temuan',
      'title' => 'Hasil Temuan',
      'fields' => [
        [
          'key' => 'hasil_temuan',
          'label' => 'Hasil Temuan',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'uraian_temuan', 'label' => 'Uraian Temuan', 'type' => 'textarea'],
            ['key' => 'kriteria', 'label' => 'Kriteria', 'type' => 'textarea'],
            ['key' => 'kondisi', 'label' => 'Kondisi', 'type' => 'textarea'],
            ['key' => 'sebab', 'label' => 'Sebab', 'type' => 'textarea'],
            ['key' => 'akibat', 'label' => 'Akibat', 'type' => 'textarea'],
            ['key' => 'rekomendasi', 'label' => 'Rekomendasi', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'tindak_lanjut',
      'title' => 'Hasil yang Perlu Ditindaklanjuti',
      'fields' => [
        [
          'key' => 'perlu_tindak_lanjut',
          'label' => 'Perlu Ditindaklanjuti',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'uraian', 'label' => 'Uraian', 'type' => 'textarea'],
            ['key' => 'penanggung_jawab', 'label' => 'Penanggung Jawab', 'type' => 'text'],
            ['key' => 'target_waktu', 'label' => 'Target Waktu', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Belum Ditindaklanjuti', 'Dalam Proses', 'Selesai']],
          ],
        ],
        [
          'key' => 'sudah_ditindaklanjuti',
          'label' => 'Sudah Ditindaklanjuti',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'uraian', 'label' => 'Uraian', 'type' => 'textarea'],
            ['key' => 'bukti_tindak_lanjut', 'label' => 'Bukti Tindak Lanjut', 'type' => 'textarea'],
            ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
          ],
        ],
      ],
    ],
    [
      'key' => 'catatan_rekomendasi',
      'title' => 'Catatan dan Rekomendasi',
      'fields' => [
        ['key' => 'catatan_lainnya', 'label' => 'Catatan Lainnya', 'type' => 'textarea', 'rows' => 4],
        [
          'key' => 'rekomendasi_sop',
          'label' => 'Rekomendasi SOP',
          'type' => 'repeater',
          'min_rows' => 1,
          'columns' => [
            ['key' => 'nomor', 'label' => 'Nomor', 'type' => 'text', 'width' => 90],
            ['key' => 'uraian_rekomendasi', 'label' => 'Uraian Rekomendasi', 'type' => 'textarea'],
            ['key' => 'penanggung_jawab', 'label' => 'Penanggung Jawab', 'type' => 'text'],
            ['key' => 'target_waktu', 'label' => 'Target Waktu', 'type' => 'date'],
            ['key' => 'prioritas', 'label' => 'Prioritas', 'type' => 'select', 'options' => ['Tinggi', 'Sedang', 'Rendah']],
          ],
        ],
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
];
