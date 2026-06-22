<?php
declare(strict_types=1);

if (!function_exists('chr_mb_upper')) {
  function chr_mb_upper(string $value): string {
    if (function_exists('mb_strtoupper')) {
      return mb_strtoupper($value, 'UTF-8');
    }
    return strtoupper($value);
  }
}

if (!function_exists('chr_format_tanggal_indo')) {
  function chr_format_tanggal_indo(?string $date, bool $uppercase = false): string {
    if (!$date) { return ''; }
    try {
      $dt = new DateTime($date);
    } catch (Throwable $e) {
      $ts = strtotime((string)$date);
      if ($ts === false) { return (string)$date; }
      $dt = new DateTime('@'.$ts);
      $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
    $bulan = [
      1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
      5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
      9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $day = $dt->format('j');
    $monthIndex = (int)$dt->format('n');
    $month = $bulan[$monthIndex] ?? $dt->format('F');
    $year = $dt->format('Y');
    $text = $day.' '.$month.' '.$year;
    return $uppercase ? chr_mb_upper($text) : $text;
  }
}

if (!function_exists('chr_format_bulan_tahun')) {
  function chr_format_bulan_tahun(?string $date, bool $uppercase = false): string {
    if (!$date) { return ''; }
    try {
      $dt = new DateTime($date);
    } catch (Throwable $e) {
      $ts = strtotime((string)$date);
      if ($ts === false) { return (string)$date; }
      $dt = new DateTime('@'.$ts);
      $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
    $bulan = [
      1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
      5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
      9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $monthIndex = (int)$dt->format('n');
    $month = $bulan[$monthIndex] ?? $dt->format('F');
    $year = $dt->format('Y');
    $text = $month.' '.$year;
    return $uppercase ? chr_mb_upper($text) : $text;
  }
}

if (!function_exists('chr_sanitize_signature')) {
  function chr_sanitize_signature(?string $value): string {
    if ($value === null) { return ''; }
    $value = trim((string)$value);
    if ($value === '') { return ''; }
    if (!preg_match('/^data:image\/(png|jpe?g);base64,[A-Za-z0-9+\/=]+$/i', $value)) {
      return '';
    }
    return $value;
  }
}

if (!function_exists('chr_form_defaults')) {
  function chr_form_defaults(?array $rev = null): array {
    $unitName = trim((string)($rev['unit_nama'] ?? 'Poltekkes Kemenkes Ternate'));
    if ($unitName === '') { $unitName = 'Poltekkes Kemenkes Ternate'; }
    $kode = trim((string)($rev['kode'] ?? ''));
    $jenis = trim((string)($rev['jenis_nama'] ?? 'Laporan Keuangan'));
    $periodeSelesai = $rev['periode_selesai'] ?? null;

    $coverSubtitle = chr_mb_upper($jenis);
    if ($unitName !== '') {
      $coverSubtitle .= ' '.chr_mb_upper($unitName);
    }
    if ($kode !== '') {
      $coverSubtitle .= ' ('.$kode.')';
    }

    $uakpaLabel = $unitName;
    if ($kode !== '') {
      $uakpaLabel .= ' ('.$kode.')';
    }

    $defaults = [
      'header_line1' => 'Kementerian Kesehatan RI',
      'header_line2' => $unitName,
      'cover_title' => 'CATATAN HASIL REVIU',
      'cover_subtitle1' => $coverSubtitle,
      'cover_period_prefix' => 'UNTUK PERIODE YANG BERAKHIR PADA TANGGAL',
      'cover_period_date' => chr_format_tanggal_indo($periodeSelesai, true),
      'drafter' => [
        ['nama' => '', 'tanggal' => '', 'label' => 'Disusun oleh/Tanggal'],
        ['nama' => '', 'tanggal' => '', 'label' => 'Disusun oleh/Tanggal'],
        ['nama' => '', 'tanggal' => '', 'label' => 'Disusun oleh/Tanggal'],
      ],
      'uapa_opts' => [
        ['key' => 'uapa', 'label' => 'Kementerian Kesehatan RI', 'checked' => false],
        ['key' => 'uappae1', 'label' => 'Direktorat Jenderal Sumber Daya Manusia Kesehatan', 'checked' => false],
        ['key' => 'uappaw', 'label' => '', 'checked' => false],
        ['key' => 'uakpa', 'label' => $uakpaLabel, 'checked' => true],
      ],
      'lk_items' => [
        ['label' => 'A. LRA', 'uraian' => '', 'indeks' => ''],
        ['label' => 'B. LO', 'uraian' => '', 'indeks' => ''],
        ['label' => 'C. LPE', 'uraian' => '', 'indeks' => ''],
        ['label' => 'D. Neraca', 'uraian' => '', 'indeks' => ''],
        ['label' => 'E. CaLK', 'uraian' => '', 'indeks' => ''],
      ],
      'perbaikan_list' => ['LRA', 'LO', 'LPE', 'Neraca', 'CaLK'],
      'hal_lain' => '',
      'rekomendasi' => '',
      'direktur' => [
        'label' => 'Direktur',
        'nama' => '',
        'nip' => '',
      ],
      'ketua' => [
        'lokasi' => 'Ternate',
        'waktu' => chr_format_bulan_tahun($periodeSelesai, false),
        'jabatan' => 'Ketua Tim',
        'nama' => '',
        'nip' => '',
      ],
      'anggota_list' => [
        ['label' => 'Anggota', 'nama' => '', 'nip' => ''],
        ['label' => '', 'nama' => '', 'nip' => ''],
      ],
      'direktur_signature' => '',
      'ketua_signature' => '',
      'anggota_signatures' => array_fill(0, 2, ''),
    ];

    return chr_form_sync_signatures($defaults);
  }
}

if (!function_exists('chr_form_sync_signatures')) {
  function chr_form_sync_signatures(array $data): array {
    $anggota = isset($data['anggota_list']) && is_array($data['anggota_list']) ? $data['anggota_list'] : [];
    $count = count($anggota);
    $signatures = isset($data['anggota_signatures']) && is_array($data['anggota_signatures'])
      ? array_values($data['anggota_signatures'])
      : [];
    while (count($signatures) < $count) {
      $signatures[] = '';
    }
    if ($count > 0 && count($signatures) > $count) {
      $signatures = array_slice($signatures, 0, $count);
    }
    $data['anggota_signatures'] = $signatures;
    return $data;
  }
}

if (!function_exists('chr_form_merge')) {
  function chr_form_merge(array $base, array $override): array {
    foreach ($override as $key => $value) {
      if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
        $base[$key] = chr_form_merge($base[$key], $value);
      } else {
        $base[$key] = $value;
      }
    }
    return $base;
  }
}

if (!function_exists('chr_form_normalize_input')) {
  function chr_form_normalize_input(array $input, array $base): array {
    $clean = $base;
    $scalarKeys = [
      'header_line1','header_line2','cover_title',
      'cover_subtitle1','cover_period_prefix','cover_period_date'
    ];
    foreach ($scalarKeys as $key) {
      if (array_key_exists($key, $input)) {
        $clean[$key] = trim((string)$input[$key]);
      }
    }

    if (isset($input['drafter']) && is_array($input['drafter'])) {
      $clean['drafter'] = [];
      foreach ($input['drafter'] as $idx => $row) {
        if (!is_array($row)) { $row = []; }
        $clean['drafter'][$idx] = [
          'label' => isset($row['label']) ? trim((string)$row['label']) : ($base['drafter'][$idx]['label'] ?? 'Disusun oleh/Tanggal'),
          'nama' => trim((string)($row['nama'] ?? '')),
          'tanggal' => trim((string)($row['tanggal'] ?? '')),
        ];
      }
      if (!count($clean['drafter'])) {
        $clean['drafter'] = $base['drafter'];
      }
    }

    if (isset($input['uapa_opts']) && is_array($input['uapa_opts'])) {
      $clean['uapa_opts'] = [];
      foreach ($input['uapa_opts'] as $idx => $row) {
        if (!is_array($row)) { $row = []; }
        $baseRow = $base['uapa_opts'][$idx] ?? ['key' => '', 'label' => '', 'checked' => false];
        $clean['uapa_opts'][$idx] = [
          'key' => (string)($baseRow['key'] ?? ($row['key'] ?? '')),
          'label' => trim((string)($row['label'] ?? $baseRow['label'] ?? '')),
          'checked' => !empty($row['checked']),
        ];
      }
      if (!count($clean['uapa_opts'])) {
        $clean['uapa_opts'] = $base['uapa_opts'];
      }
    }

    if (isset($input['lk_items']) && is_array($input['lk_items'])) {
      $clean['lk_items'] = [];
      foreach ($input['lk_items'] as $idx => $row) {
        if (!is_array($row)) { $row = []; }
        $baseRow = $base['lk_items'][$idx] ?? ['label' => '', 'uraian' => '', 'indeks' => ''];
        $clean['lk_items'][$idx] = [
          'label' => trim((string)($row['label'] ?? $baseRow['label'] ?? '')),
          'uraian' => trim((string)($row['uraian'] ?? $baseRow['uraian'] ?? '')),
          'indeks' => trim((string)($row['indeks'] ?? $baseRow['indeks'] ?? '')),
        ];
      }
      if (!count($clean['lk_items'])) {
        $clean['lk_items'] = $base['lk_items'];
      }
    }

    if (isset($input['perbaikan_list']) && is_array($input['perbaikan_list'])) {
      $clean['perbaikan_list'] = [];
      foreach ($input['perbaikan_list'] as $idx => $value) {
        $text = trim((string)$value);
        if ($text !== '') {
          $clean['perbaikan_list'][] = $text;
        }
      }
      if (!count($clean['perbaikan_list'])) {
        $clean['perbaikan_list'] = [];
      }
    }

    foreach (['hal_lain','rekomendasi'] as $textKey) {
      if (array_key_exists($textKey, $input)) {
        $clean[$textKey] = trim((string)$input[$textKey]);
      }
    }

    if (isset($input['direktur']) && is_array($input['direktur'])) {
      $clean['direktur'] = [
        'label' => trim((string)($input['direktur']['label'] ?? ($base['direktur']['label'] ?? 'Direktur'))),
        'nama' => trim((string)($input['direktur']['nama'] ?? '')),
        'nip' => trim((string)($input['direktur']['nip'] ?? '')),
      ];
    }

    if (isset($input['ketua']) && is_array($input['ketua'])) {
      $clean['ketua'] = [
        'lokasi' => trim((string)($input['ketua']['lokasi'] ?? ($base['ketua']['lokasi'] ?? ''))),
        'waktu' => trim((string)($input['ketua']['waktu'] ?? ($base['ketua']['waktu'] ?? ''))),
        'jabatan' => trim((string)($input['ketua']['jabatan'] ?? ($base['ketua']['jabatan'] ?? ''))),
        'nama' => trim((string)($input['ketua']['nama'] ?? '')),
        'nip' => trim((string)($input['ketua']['nip'] ?? '')),
      ];
    }

    if (isset($input['anggota_list']) && is_array($input['anggota_list'])) {
      $clean['anggota_list'] = [];
      foreach ($input['anggota_list'] as $idx => $row) {
        if (!is_array($row)) { $row = []; }
        $baseRow = $base['anggota_list'][$idx] ?? ['label' => 'Anggota', 'nama' => '', 'nip' => ''];
        $clean['anggota_list'][$idx] = [
          'label' => trim((string)($row['label'] ?? $baseRow['label'] ?? 'Anggota')),
          'nama' => trim((string)($row['nama'] ?? '')),
          'nip' => trim((string)($row['nip'] ?? '')),
        ];
      }
      if (!count($clean['anggota_list'])) {
        $clean['anggota_list'] = $base['anggota_list'];
      }
    }

    if (array_key_exists('direktur_signature', $input)) {
      $clean['direktur_signature'] = chr_sanitize_signature($input['direktur_signature']);
    }
    if (array_key_exists('ketua_signature', $input)) {
      $clean['ketua_signature'] = chr_sanitize_signature($input['ketua_signature']);
    }
    if (isset($input['anggota_signatures']) && is_array($input['anggota_signatures'])) {
      $clean['anggota_signatures'] = [];
      $expected = isset($clean['anggota_list']) ? count($clean['anggota_list']) : 0;
      for ($i = 0; $i < $expected; $i++) {
        $clean['anggota_signatures'][$i] = chr_sanitize_signature($input['anggota_signatures'][$i] ?? '');
      }
    }

    $clean = chr_form_sync_signatures($clean);

    return $clean;
  }
}

if (!function_exists('ensure_chr_form_schema')) {
  function ensure_chr_form_schema(mysqli $conn): bool {
    static $checked = false;
    static $result = true;
    if ($checked) { return $result; }
    $checked = true;
    $sql = "CREATE TABLE IF NOT EXISTS reviu_chr_form (
      id INT AUTO_INCREMENT PRIMARY KEY,
      reviu_id INT NOT NULL,
      data_json LONGTEXT NOT NULL,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_reviu (reviu_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
      error_log('ensure_chr_form_schema failed: '.$conn->error);
      $result = false;
    }
    return $result;
  }
}

if (!function_exists('chr_form_fetch')) {
  function chr_form_fetch(mysqli $conn, int $reviuId, ?array $rev = null): array {
    $data = chr_form_defaults($rev);
    if ($reviuId < 1) { return $data; }
    if (!ensure_chr_form_schema($conn)) { return $data; }
    $stmt = $conn->prepare("SELECT data_json FROM reviu_chr_form WHERE reviu_id=? LIMIT 1");
    if (!$stmt) { return $data; }
    $stmt->bind_param("i", $reviuId);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $json = json_decode($row['data_json'], true);
        if (is_array($json)) {
          $data = chr_form_merge($data, $json);
        }
      }
      $res->free();
    }
    $stmt->close();
    $data = chr_form_sync_signatures($data);
    $data['direktur_signature'] = chr_sanitize_signature($data['direktur_signature'] ?? '');
    $data['ketua_signature'] = chr_sanitize_signature($data['ketua_signature'] ?? '');
    if (!isset($data['anggota_signatures']) || !is_array($data['anggota_signatures'])) {
      $data['anggota_signatures'] = [];
    }
    foreach ($data['anggota_signatures'] as $idx => $sig) {
      $data['anggota_signatures'][$idx] = chr_sanitize_signature($sig);
    }
    $data = chr_form_sync_signatures($data);
    return $data;
  }
}

if (!function_exists('chr_form_save')) {
  function chr_form_save(mysqli $conn, int $reviuId, array $data): bool {
    if ($reviuId < 1) { return false; }
    if (!ensure_chr_form_schema($conn)) { return false; }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; }
    $stmt = $conn->prepare(
      "INSERT INTO reviu_chr_form (reviu_id, data_json, updated_at)
       VALUES (?, ?, NOW())
       ON DUPLICATE KEY UPDATE data_json=VALUES(data_json), updated_at=NOW()"
    );
    if (!$stmt) { return false; }
    $stmt->bind_param("is", $reviuId, $json);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}
