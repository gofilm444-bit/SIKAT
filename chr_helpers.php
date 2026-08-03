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
    if (strlen($value) > 1500000) { return ''; }
    if (!preg_match('/^data:image\/(png|jpe?g);base64,[A-Za-z0-9+\/=]+$/i', $value)) {
      return '';
    }
    return $value;
  }
}

if (!function_exists('chr_employee_profile_schema_ready')) {
  function chr_employee_profile_schema_ready(mysqli $conn): bool {
    foreach (['nip', 'jabatan', 'unit_id'] as $column) {
      $safe = $conn->real_escape_string($column);
      $res = @$conn->query("SHOW COLUMNS FROM pengguna LIKE '{$safe}'");
      if (!$res || $res->num_rows < 1) { return false; }
    }
    return true;
  }
}

if (!function_exists('chr_employee_picker_options')) {
  function chr_employee_picker_options(mysqli $conn): array {
    if (!chr_employee_profile_schema_ready($conn)) { return []; }
    $sql = "SELECT p.id, p.nama, p.nip, p.jabatan, p.unit_id, p.peran, p.status, u.nama AS unit_nama
            FROM pengguna p
            LEFT JOIN unit_kerja u ON u.id = p.unit_id
            WHERE p.status = 'aktif'
            ORDER BY
              CASE
                WHEN p.peran IN ('auditee_direktur','auditee_wadir1','auditee_wadir2','auditee_wadir3') THEN 0
                WHEN p.peran IN ('auditor_ka','auditor_staff','auditor') THEN 1
                ELSE 2
              END,
              p.nama ASC";
    $result = $conn->query($sql);
    if (!$result) { return []; }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
      $complete = trim((string)($row['nama'] ?? '')) !== ''
        && trim((string)($row['nip'] ?? '')) !== ''
        && trim((string)($row['jabatan'] ?? '')) !== ''
        && (int)($row['unit_id'] ?? 0) > 0
        && trim((string)($row['unit_nama'] ?? '')) !== '';
      $row['profile_complete'] = $complete ? 1 : 0;
      $rows[] = $row;
    }
    return $rows;
  }
}

if (!function_exists('chr_employee_profile_fetch')) {
  function chr_employee_profile_fetch(mysqli $conn, int $userId): ?array {
    if ($userId < 1 || !chr_employee_profile_schema_ready($conn)) { return null; }
    $sql = "SELECT p.id, p.nama, p.nip, p.jabatan, p.unit_id, p.status, u.nama AS unit_nama
            FROM pengguna p
            LEFT JOIN unit_kerja u ON u.id = p.unit_id
            WHERE p.id = ? AND p.status = 'aktif'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return null; }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
      $stmt->close();
      return null;
    }
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) { return null; }
    if (trim((string)($row['nama'] ?? '')) === ''
      || trim((string)($row['nip'] ?? '')) === ''
      || trim((string)($row['jabatan'] ?? '')) === ''
      || (int)($row['unit_id'] ?? 0) < 1
      || trim((string)($row['unit_nama'] ?? '')) === '') {
      return null;
    }
    return [
      'user_id' => (int)$row['id'],
      'nama' => (string)$row['nama'],
      'nip' => (string)$row['nip'],
      'jabatan' => (string)$row['jabatan'],
      'unit_id' => (int)$row['unit_id'],
      'unit' => (string)$row['unit_nama'],
    ];
  }
}

if (!function_exists('chr_template_registry')) {
  function chr_template_registry(): array {
    static $registry = null;
    if ($registry !== null) { return $registry; }

    $fallback = [
      'chr_legacy_laporan_keuangan' => [
        'code' => 'chr_legacy_laporan_keuangan',
        'name' => 'CHR Laporan Keuangan Legacy',
        'version' => 1,
        'aliases' => ['Reviu Laporan Keuangan', 'Laporan Keuangan'],
        'status' => 'active',
        'renderer' => 'legacy',
      ],
    ];

    $path = __DIR__ . '/chr_templates.php';
    if (!is_file($path)) {
      $registry = $fallback;
      return $registry;
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
      $registry = $fallback;
      return $registry;
    }

    foreach ([
      'chr_sop' => 'chr_template_sop.php',
      'chr_rkakl' => 'chr_template_rkakl.php',
      'chr_manajemen_risiko' => 'chr_template_manajemen_risiko.php',
      'chr_pengembangan_pegawai' => 'chr_template_pengembangan_pegawai.php',
      'chr_lhkpn_lhkasn' => 'chr_template_lhkpn_lhkasn.php',
      'chr_iku_ikt' => 'chr_template_iku_ikt.php',
      'chr_lkj' => 'chr_template_lkj.php',
      'chr_pipk' => 'chr_template_pipk.php',
      'chr_rkbmn' => 'chr_template_rkbmn.php',
    ] as $expectedCode => $fileName) {
      $templatePath = __DIR__ . '/' . $fileName;
      if (!is_file($templatePath)) { continue; }
      $templateFile = require $templatePath;
      if (is_array($templateFile) && (($templateFile['code'] ?? '') === $expectedCode)) {
        $loaded[$expectedCode] = array_replace_recursive($loaded[$expectedCode] ?? [], $templateFile);
      }
    }

    $registry = [];
    foreach ($loaded as $code => $template) {
      if (!is_array($template)) { continue; }
      $templateCode = trim((string)($template['code'] ?? $code));
      if ($templateCode === '') { continue; }
      $template['code'] = $templateCode;
      $template['version'] = max(1, (int)($template['version'] ?? 1));
      $template['aliases'] = isset($template['aliases']) && is_array($template['aliases']) ? $template['aliases'] : [];
      $template['renderer'] = trim((string)($template['renderer'] ?? 'legacy')) ?: 'legacy';
      $registry[$templateCode] = $template;
    }

    if (!isset($registry['chr_legacy_laporan_keuangan'])) {
      $registry['chr_legacy_laporan_keuangan'] = $fallback['chr_legacy_laporan_keuangan'];
    }

    return $registry;
  }
}

if (!function_exists('chr_template_get')) {
  function chr_template_get(string $code): ?array {
    $code = trim($code);
    if ($code === '') { return null; }
    $registry = chr_template_registry();
    return $registry[$code] ?? null;
  }
}

if (!function_exists('chr_template_normalize_name')) {
  function chr_template_normalize_name(string $name): string {
    $name = trim($name);
    if ($name === '') { return ''; }
    $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $name = str_replace('&', ' dan ', $name);
    $name = preg_replace('/\bdan\b/u', ' dan ', $name);
    $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);
    $name = preg_replace('/\s+/u', ' ', (string)$name);
    return trim((string)$name);
  }
}

if (!function_exists('chr_template_resolve_by_review')) {
  function chr_template_resolve_by_review(?array $rev): string {
    $reviewTemplateCode = trim((string)($rev['template_code'] ?? ($rev['review_template_code'] ?? '')));
    if ($reviewTemplateCode !== '' && chr_template_get($reviewTemplateCode)) {
      return $reviewTemplateCode;
    }
    $jenisTemplateCode = trim((string)($rev['jenis_template_code'] ?? ''));
    if ($jenisTemplateCode !== '' && chr_template_get($jenisTemplateCode)) {
      return $jenisTemplateCode;
    }
    $jenis = trim((string)($rev['jenis_nama'] ?? ''));
    if ($jenis === '') { return ''; }
    $target = chr_template_normalize_name($jenis);
    if ($target === '') { return ''; }

    foreach (chr_template_registry() as $code => $template) {
      $aliases = $template['aliases'] ?? [];
      $aliases[] = $template['name'] ?? '';
      foreach ($aliases as $alias) {
        if (chr_template_normalize_name((string)$alias) === $target) {
          return (string)$code;
        }
      }
    }

    return '';
  }
}

if (!function_exists('chr_template_resolve_for_form')) {
  function chr_template_resolve_for_form(?array $rev, ?array $storedRow): string {
    $storedCode = trim((string)($storedRow['template_code'] ?? ''));
    $resolved = chr_template_resolve_by_review($rev);
    if ($storedCode !== '' && chr_template_get($storedCode)) {
      if ($resolved === 'chr_rkakl' && in_array($storedCode, ['chr_rkakl', 'chr_legacy_laporan_keuangan'], true)) {
        return 'chr_rkakl';
      }
      return $storedCode;
    }

    $storedJson = trim((string)($storedRow['data_json'] ?? ''));
    if ($storedRow && $storedJson !== '') {
      if ($resolved === 'chr_rkakl') {
        return 'chr_rkakl';
      }
      return 'chr_legacy_laporan_keuangan';
    }

    return chr_template_get($resolved) ? $resolved : 'chr_legacy_laporan_keuangan';
  }
}

if (!function_exists('chr_template_version')) {
  function chr_template_version(string $code): int {
    $template = chr_template_get($code);
    return $template ? max(1, (int)($template['version'] ?? 1)) : 1;
  }
}

if (!function_exists('chr_template_uses_standard_approval')) {
  function chr_template_uses_standard_approval(string $code): bool {
    return in_array($code, chr_approval_template_codes(), true);
  }
}

if (!function_exists('chr_approval_template_codes')) {
  function chr_approval_template_codes(): array {
    return [
      'chr_sop',
      'chr_rkakl',
      'chr_manajemen_risiko',
      'chr_pengembangan_pegawai',
      'chr_lhkpn_lhkasn',
      'chr_iku_ikt',
      'chr_lkj',
      'chr_pipk',
      'chr_rkbmn',
    ];
  }
}

if (!function_exists('chr_dynamic_template_subtitle')) {
  function chr_dynamic_template_subtitle(string $templateCode): string {
    return [
      'chr_rkakl' => 'REVIU RKA/RKAKL',
      'chr_manajemen_risiko' => 'MANAJEMEN RISIKO',
      'chr_pengembangan_pegawai' => 'PENGEMBANGAN PEGAWAI',
      'chr_lhkpn_lhkasn' => 'LHKPN DAN LHKASN',
      'chr_iku_ikt' => 'IKU-IKT',
      'chr_lkj' => 'LAPORAN KINERJA',
      'chr_pipk' => 'PIPK TINGKAT SATKER',
      'chr_rkbmn' => 'RKBMN',
    ][$templateCode] ?? 'REVIU STANDAR OPERASIONAL PROSEDUR';
  }
}

if (!function_exists('chr_template_display_name')) {
  function chr_template_display_name(string $templateCode): string {
    $template = chr_template_get($templateCode);
    if ($template && trim((string)($template['name'] ?? '')) !== '') {
      return (string)$template['name'];
    }
    return 'CHR';
  }
}

if (!function_exists('chr_form_defaults')) {
  function chr_form_defaults(?array $rev = null, ?string $templateCode = null): array {
    // Tahap 1: semua template masih memakai struktur legacy. Renderer dinamis dibuat pada tahap berikutnya.
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

if (!function_exists('chr_form_strip_runtime_metadata')) {
  function chr_form_strip_runtime_metadata(array $data): array {
    unset($data['template_code'], $data['template_version'], $data['template']);
    return $data;
  }
}

if (!function_exists('chr_form_is_list_array')) {
  function chr_form_is_list_array(array $value): bool {
    if ($value === []) { return true; }
    return array_keys($value) === range(0, count($value) - 1);
  }
}

if (!function_exists('chr_form_merge_preserve_field')) {
  function chr_form_merge_preserve_field($stored, $incoming, $default) {
    if (is_array($incoming)) {
      if (chr_form_is_list_array($incoming)) {
        // Repeater/list fields from the form are treated as a complete replacement.
        return $incoming;
      }

      $storedArr = is_array($stored) ? $stored : [];
      $defaultArr = is_array($default) ? $default : [];
      $result = $storedArr;
      foreach ($incoming as $key => $value) {
        if (!array_key_exists($key, $defaultArr) && !array_key_exists($key, $storedArr)) {
          continue;
        }
        $result[$key] = chr_form_merge_preserve_field(
          $storedArr[$key] ?? null,
          $value,
          $defaultArr[$key] ?? null
        );
      }
      return $result;
    }

    return $incoming;
  }
}

if (!function_exists('chr_form_merge_preserve_legacy')) {
  function chr_form_merge_preserve_legacy(array $stored, array $incoming, array $defaults): array {
    $stored = chr_form_strip_runtime_metadata($stored);
    $incoming = chr_form_strip_runtime_metadata($incoming);
    $defaults = chr_form_strip_runtime_metadata($defaults);

    $result = $stored;
    foreach ($incoming as $key => $value) {
      if (!array_key_exists($key, $defaults) && !array_key_exists($key, $stored)) {
        continue;
      }
      $result[$key] = chr_form_merge_preserve_field(
        $stored[$key] ?? null,
        $value,
        $defaults[$key] ?? null
      );
    }

    foreach ($defaults as $key => $value) {
      if (!array_key_exists($key, $result)) {
        $result[$key] = $value;
      }
    }

    return chr_form_sync_signatures($result);
  }
}

if (!function_exists('chr_form_has_legacy_signatures')) {
  function chr_form_has_legacy_signatures(array $data): bool {
    if (trim((string)($data['direktur_signature'] ?? '')) !== '') { return true; }
    if (trim((string)($data['ketua_signature'] ?? '')) !== '') { return true; }
    if (isset($data['anggota_signatures']) && is_array($data['anggota_signatures'])) {
      foreach ($data['anggota_signatures'] as $signature) {
        if (trim((string)$signature) !== '') { return true; }
      }
    }
    foreach (['direktur', 'ketua'] as $key) {
      if (!isset($data[$key]) || !is_array($data[$key])) { continue; }
      foreach (['nama', 'nip'] as $field) {
        if (trim((string)($data[$key][$field] ?? '')) !== '') { return true; }
      }
    }
    $ketua = isset($data['ketua']) && is_array($data['ketua']) ? $data['ketua'] : [];
    $ketuaJabatan = trim((string)($ketua['jabatan'] ?? ''));
    if ($ketuaJabatan !== '' && strcasecmp($ketuaJabatan, 'Ketua Tim') !== 0) { return true; }
    if (isset($data['anggota_list']) && is_array($data['anggota_list'])) {
      foreach ($data['anggota_list'] as $row) {
        if (!is_array($row)) { continue; }
        foreach (['nama', 'nip'] as $field) {
          if (trim((string)($row[$field] ?? '')) !== '') { return true; }
        }
      }
    }
    return false;
  }
}

if (!function_exists('chr_form_has_legacy_signature_structure')) {
  function chr_form_has_legacy_signature_structure(array $data): bool {
    foreach (['direktur', 'ketua', 'anggota_list', 'direktur_signature', 'ketua_signature', 'anggota_signatures'] as $key) {
      if (array_key_exists($key, $data)) { return true; }
    }
    return false;
  }
}

if (!function_exists('chr_rkakl_approval_mode')) {
  function chr_rkakl_approval_mode(array $data, ?array $rev = null): string {
    $templateCode = (string)($data['template_code'] ?? '');
    $resolvedByReview = $rev ? chr_template_resolve_by_review($rev) : '';
    if ($templateCode !== 'chr_rkakl' && $resolvedByReview !== 'chr_rkakl') { return 'not_rkakl'; }
    $dynamic = isset($data['dynamic']) && is_array($data['dynamic']) ? $data['dynamic'] : [];
    $pengesahan = isset($dynamic['pengesahan']) && is_array($dynamic['pengesahan']) ? $dynamic['pengesahan'] : [];
    if ($pengesahan) { return 'standard'; }
    return 'legacy';
  }
}

if (!function_exists('chr_dynamic_first_nonempty')) {
  function chr_dynamic_first_nonempty(?array $source, array $keys): string {
    foreach ($keys as $key) {
      $value = trim((string)($source[$key] ?? ''));
      if ($value !== '') { return $value; }
    }
    return '';
  }
}

if (!function_exists('chr_dynamic_field_default')) {
  function chr_dynamic_field_default(array $field, ?array $rev = null) {
    $type = (string)($field['type'] ?? 'text');
    if ($type === 'repeater') {
      $rows = [];
      $minRows = max(0, (int)($field['min_rows'] ?? 0));
      for ($i = 0; $i < $minRows; $i++) {
        $row = [];
        foreach (($field['columns'] ?? []) as $column) {
          if (!is_array($column)) { continue; }
          $row[(string)($column['key'] ?? '')] = '';
        }
        $rows[] = $row;
      }
      return $rows;
    }
    if ($type === 'signature') {
      return [
        'user_id' => '',
        'document_role' => '',
        'document_role_label' => '',
        'nama' => '',
        'nip' => '',
        'jabatan' => '',
        'unit_id' => '',
        'unit' => '',
        'tanggal' => '',
        'lokasi' => '',
        'signature' => '',
        'status_signature' => 'waiting',
        'signed_at' => '',
        'signed_ip' => '',
        'signed_user_agent' => '',
      ];
    }
    return (string)($field['default'] ?? '');
  }
}

if (!function_exists('chr_dynamic_defaults')) {
  function chr_dynamic_defaults(array $template, ?array $rev = null): array {
    $dynamic = [];
    foreach (($template['sections'] ?? []) as $section) {
      if (!is_array($section)) { continue; }
      $sectionKey = (string)($section['key'] ?? '');
      if ($sectionKey === '') { continue; }
      $dynamic[$sectionKey] = [];
      foreach (($section['fields'] ?? []) as $field) {
        if (!is_array($field)) { continue; }
        $fieldKey = (string)($field['key'] ?? '');
        if ($fieldKey === '') { continue; }
        $dynamic[$sectionKey][$fieldKey] = chr_dynamic_field_default($field, $rev);
      }
    }

    if (isset($dynamic['identitas'])) {
      $unitName = chr_dynamic_first_nonempty($rev, ['unit_nama']);
      $kode = chr_dynamic_first_nonempty($rev, ['kode']);
      $periode = chr_dynamic_first_nonempty($rev, ['periode', 'periode_label', 'periode_selesai', 'tanggal_selesai']);
      $tanggal = chr_dynamic_first_nonempty($rev, ['tanggal', 'tanggal_mulai', 'created_at']);
      $dynamic['identitas']['header_baris_1'] = 'Kementerian Kesehatan RI';
      $dynamic['identitas']['header_baris_2'] = $unitName;
      $templateCode = (string)($template['code'] ?? '');
      $dynamic['identitas']['judul_dokumen'] = 'CATATAN HASIL REVIU';
      $dynamic['identitas']['subjudul'] = chr_dynamic_template_subtitle($templateCode);
      if ($templateCode === 'chr_rkakl') {
        $dynamic['identitas']['kementerian_lembaga'] = 'Kementerian Kesehatan RI';
        $dynamic['identitas']['apip'] = 'Satuan Kepatuhan Intern';
        $dynamic['identitas']['eselon_unit'] = $unitName;
        $dynamic['identitas']['tahun_anggaran'] = $periode !== '' && preg_match('/\b(20\d{2})\b/', $periode, $m) ? $m[1] : '';
      }
      if ($templateCode === 'chr_manajemen_risiko') {
        $dynamic['identitas']['judul_dokumen'] = 'CATATAN HASIL REVIU';
        $dynamic['identitas']['subjudul'] = 'MANAJEMEN RISIKO';
        $dynamic['identitas']['entitas'] = 'Poltekkes Kemenkes Ternate';
        $dynamic['identitas']['unit_yang_direviu'] = $unitName;
      }
      if (in_array($templateCode, ['chr_pengembangan_pegawai', 'chr_lhkpn_lhkasn', 'chr_iku_ikt', 'chr_lkj', 'chr_pipk', 'chr_rkbmn'], true)) {
        $dynamic['identitas']['entitas'] = 'Poltekkes Kemenkes Ternate';
        $dynamic['identitas']['unit_yang_direviu'] = $unitName !== '' ? $unitName : 'Seluruh Unit Kerja';
      }
      if (in_array($templateCode, ['chr_lkj', 'chr_pipk', 'chr_rkbmn'], true)) {
        $dynamic['identitas']['tahun_anggaran'] = $periode !== '' && preg_match('/\b(20\d{2})\b/', $periode, $m) ? $m[1] : '';
      }
      $dynamic['identitas']['unit_kerja'] = $unitName;
      $dynamic['identitas']['periode'] = $periode;
      $dynamic['identitas']['nomor_chr'] = $kode;
      $dynamic['identitas']['tanggal_chr'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ? $tanggal : '';
      $dynamic['identitas']['nomor_surat_tugas'] = chr_dynamic_first_nonempty($rev, ['nomor_surat_tugas', 'no_surat_tugas']);
      $stDate = chr_dynamic_first_nonempty($rev, ['tanggal_surat_tugas', 'tgl_surat_tugas']);
      $dynamic['identitas']['tanggal_surat_tugas'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $stDate) ? $stDate : '';
    }

    if (($template['code'] ?? '') === 'chr_rkakl' && isset($dynamic['pemeriksaan']['aspek_reviu']) && is_array($dynamic['pemeriksaan']['aspek_reviu'])) {
      $aspects = [
        'Kesesuaian dengan RKP dan Renja-K/L',
        'Kesesuaian dengan Pagu Anggaran',
        'Kesesuaian dengan Alokasi Anggaran',
        'Kelengkapan Dokumen Pendukung',
        'Kesesuaian Biaya Pemeliharaan',
        'Kesesuaian Biaya Pengadaan',
      ];
      foreach ($aspects as $idx => $aspect) {
        if (!isset($dynamic['pemeriksaan']['aspek_reviu'][$idx]) || !is_array($dynamic['pemeriksaan']['aspek_reviu'][$idx])) {
          $dynamic['pemeriksaan']['aspek_reviu'][$idx] = [];
        }
        if (trim((string)($dynamic['pemeriksaan']['aspek_reviu'][$idx]['aspek'] ?? '')) === '') {
          $dynamic['pemeriksaan']['aspek_reviu'][$idx]['aspek'] = $aspect;
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_manajemen_risiko' && isset($dynamic['komponen_reviu']['daftar_komponen']) && is_array($dynamic['komponen_reviu']['daftar_komponen'])) {
      $components = ['Profil Risiko Unit Kerja', 'Laporan Pelaksanaan/Pemantauan Unit Kerja'];
      foreach ($components as $idx => $component) {
        if (!isset($dynamic['komponen_reviu']['daftar_komponen'][$idx]) || !is_array($dynamic['komponen_reviu']['daftar_komponen'][$idx])) {
          $dynamic['komponen_reviu']['daftar_komponen'][$idx] = [];
        }
        if (trim((string)($dynamic['komponen_reviu']['daftar_komponen'][$idx]['komponen'] ?? '')) === '') {
          $dynamic['komponen_reviu']['daftar_komponen'][$idx]['komponen'] = $component;
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_pengembangan_pegawai' && isset($dynamic['komponen_reviu']['daftar_komponen']) && is_array($dynamic['komponen_reviu']['daftar_komponen'])) {
      $rows = [
        ['kelompok' => 'Kenaikan Pangkat', 'komponen' => 'Jabatan Fungsional'],
        ['kelompok' => 'Kenaikan Pangkat', 'komponen' => 'Jabatan Pelaksana'],
        ['kelompok' => 'Kenaikan Jenjang Akademik', 'komponen' => 'Jabatan Fungsional'],
      ];
      foreach ($rows as $idx => $row) {
        if (!isset($dynamic['komponen_reviu']['daftar_komponen'][$idx]) || !is_array($dynamic['komponen_reviu']['daftar_komponen'][$idx])) {
          $dynamic['komponen_reviu']['daftar_komponen'][$idx] = [];
        }
        foreach ($row as $key => $value) {
          if (trim((string)($dynamic['komponen_reviu']['daftar_komponen'][$idx][$key] ?? '')) === '') {
            $dynamic['komponen_reviu']['daftar_komponen'][$idx][$key] = $value;
          }
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_lhkpn_lhkasn') {
      if (isset($dynamic['komponen_reviu']['daftar_komponen']) && is_array($dynamic['komponen_reviu']['daftar_komponen'])) {
        foreach (['Kepatuhan Pelaporan', 'Keakuratan dan Kelengkapan Data', 'Kepatuhan pada Regulasi'] as $idx => $component) {
          if (!isset($dynamic['komponen_reviu']['daftar_komponen'][$idx]) || !is_array($dynamic['komponen_reviu']['daftar_komponen'][$idx])) {
            $dynamic['komponen_reviu']['daftar_komponen'][$idx] = [];
          }
          if (trim((string)($dynamic['komponen_reviu']['daftar_komponen'][$idx]['komponen'] ?? '')) === '') {
            $dynamic['komponen_reviu']['daftar_komponen'][$idx]['komponen'] = $component;
          }
        }
      }
      if (isset($dynamic['rekap_pelaporan']['rekap']) && is_array($dynamic['rekap_pelaporan']['rekap'])) {
        foreach (['LHKPN', 'LHKASN'] as $idx => $kind) {
          if (!isset($dynamic['rekap_pelaporan']['rekap'][$idx]) || !is_array($dynamic['rekap_pelaporan']['rekap'][$idx])) {
            $dynamic['rekap_pelaporan']['rekap'][$idx] = [];
          }
          if (trim((string)($dynamic['rekap_pelaporan']['rekap'][$idx]['jenis_laporan'] ?? '')) === '') {
            $dynamic['rekap_pelaporan']['rekap'][$idx]['jenis_laporan'] = $kind;
          }
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_iku_ikt' && isset($dynamic['komponen_reviu']['indikator']) && is_array($dynamic['komponen_reviu']['indikator'])) {
      $rows = [
        ['jenis_indikator' => 'Indikator Kinerja Utama', 'area' => 'Tata Kelola'],
        ['jenis_indikator' => 'Indikator Kinerja Utama', 'area' => 'Pendidikan'],
        ['jenis_indikator' => 'Indikator Kinerja Utama', 'area' => 'Penelitian dan Pengabdian kepada Masyarakat'],
        ['jenis_indikator' => 'Indikator Kinerja Tambahan', 'area' => 'Tata Kelola'],
        ['jenis_indikator' => 'Indikator Kinerja Tambahan', 'area' => 'Pendidikan'],
        ['jenis_indikator' => 'Indikator Kinerja Tambahan', 'area' => 'Penelitian dan Pengabdian kepada Masyarakat'],
      ];
      foreach ($rows as $idx => $row) {
        if (!isset($dynamic['komponen_reviu']['indikator'][$idx]) || !is_array($dynamic['komponen_reviu']['indikator'][$idx])) {
          $dynamic['komponen_reviu']['indikator'][$idx] = [];
        }
        foreach ($row as $key => $value) {
          if (trim((string)($dynamic['komponen_reviu']['indikator'][$idx][$key] ?? '')) === '') {
            $dynamic['komponen_reviu']['indikator'][$idx][$key] = $value;
          }
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_lkj' && isset($dynamic['komponen_reviu']['daftar_komponen']) && is_array($dynamic['komponen_reviu']['daftar_komponen'])) {
      foreach (['Format Laporan Kinerja', 'Mekanisme Penyusunan Laporan Kinerja', 'Substansi Laporan Kinerja', 'Catatan Permasalahan Lainnya'] as $idx => $component) {
        if (!isset($dynamic['komponen_reviu']['daftar_komponen'][$idx]) || !is_array($dynamic['komponen_reviu']['daftar_komponen'][$idx])) {
          $dynamic['komponen_reviu']['daftar_komponen'][$idx] = [];
        }
        if (trim((string)($dynamic['komponen_reviu']['daftar_komponen'][$idx]['komponen'] ?? '')) === '') {
          $dynamic['komponen_reviu']['daftar_komponen'][$idx]['komponen'] = $component;
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_pipk') {
      if (isset($dynamic['tingkat_unit']['unit_akuntansi']) && is_array($dynamic['tingkat_unit']['unit_akuntansi'])) {
        foreach (['UPPA', 'UAPPA-E1', 'UAPPA-W', 'UAKPA'] as $idx => $level) {
          if (!isset($dynamic['tingkat_unit']['unit_akuntansi'][$idx]) || !is_array($dynamic['tingkat_unit']['unit_akuntansi'][$idx])) {
            $dynamic['tingkat_unit']['unit_akuntansi'][$idx] = [];
          }
          if (trim((string)($dynamic['tingkat_unit']['unit_akuntansi'][$idx]['tingkat'] ?? '')) === '') {
            $dynamic['tingkat_unit']['unit_akuntansi'][$idx]['tingkat'] = $level;
          }
        }
      }
      if (isset($dynamic['komponen_pipk']['daftar_komponen']) && is_array($dynamic['komponen_pipk']['daftar_komponen'])) {
        $components = ['Identifikasi Risiko dan Kecukupan Rancangan Pengendalian', 'Perbaikan Identifikasi Risiko dan Pengendalian', 'Pengujian Pengendalian Intern Tingkat Entitas', 'Pengujian PUTIK', 'Pengujian Atribut Pengendalian', 'Pengujian Pengendalian Aplikasi', 'Penilaian Efektivitas Implementasi Pengendalian'];
        foreach ($components as $idx => $component) {
          if (!isset($dynamic['komponen_pipk']['daftar_komponen'][$idx]) || !is_array($dynamic['komponen_pipk']['daftar_komponen'][$idx])) {
            $dynamic['komponen_pipk']['daftar_komponen'][$idx] = [];
          }
          if (trim((string)($dynamic['komponen_pipk']['daftar_komponen'][$idx]['komponen'] ?? '')) === '') {
            $dynamic['komponen_pipk']['daftar_komponen'][$idx]['komponen'] = $component;
          }
        }
      }
    }

    if (($template['code'] ?? '') === 'chr_rkbmn' && isset($dynamic['kelengkapan_dokumen']['checklist']) && is_array($dynamic['kelengkapan_dokumen']['checklist'])) {
      $docs = [
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Surat Pernyataan Tanggung Jawab Mutlak (SPTJM)'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Hasil Penelitian RKBMN oleh Pengguna Barang'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Data tanah dan/atau bangunan existing yang terindikasi idle'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Dokumen rencana BMN untuk dihapuskan/dihentikan/dipindahtangankan/dimanfaatkan/dimusnahkan'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Dokumen hasil pembahasan dengan bidang Pekerjaan Umum jika relevan'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Renstra K/L atau dokumen yang disetarakan'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Hasil Pengusulan Penyediaan Anggaran pada RKBMN tahun sebelumnya'],
        ['kategori' => 'Kelengkapan Dokumen', 'dokumen' => 'Dokumen pendukung terkait lainnya'],
      ];
      foreach ($docs as $idx => $row) {
        if (!isset($dynamic['kelengkapan_dokumen']['checklist'][$idx]) || !is_array($dynamic['kelengkapan_dokumen']['checklist'][$idx])) {
          $dynamic['kelengkapan_dokumen']['checklist'][$idx] = [];
        }
        foreach ($row as $key => $value) {
          if (trim((string)($dynamic['kelengkapan_dokumen']['checklist'][$idx][$key] ?? '')) === '') {
            $dynamic['kelengkapan_dokumen']['checklist'][$idx][$key] = $value;
          }
        }
      }
    }

    return ['dynamic' => $dynamic];
  }
}

if (!function_exists('chr_dynamic_clean_scalar')) {
  function chr_dynamic_clean_scalar($value, string $type, array $options = []): string {
    $value = trim((string)$value);
    if ($type === 'signature_data') {
      return chr_sanitize_signature($value);
    }
    $limit = $type === 'textarea' ? 20000 : 1000;
    if (function_exists('mb_substr')) {
      $value = mb_substr($value, 0, $limit, 'UTF-8');
    } else {
      $value = substr($value, 0, $limit);
    }
    $value = strip_tags($value);
    if ($type === 'date') {
      return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
    if ($type === 'number') {
      $normalized = str_replace(',', '.', preg_replace('/[^0-9,\.\-]+/', '', $value));
      return is_numeric($normalized) ? $normalized : '';
    }
    if ($type === 'select') {
      return in_array($value, $options, true) ? $value : '';
    }
    return $value;
  }
}

if (!function_exists('chr_dynamic_row_empty')) {
  function chr_dynamic_row_empty(array $row): bool {
    foreach ($row as $value) {
      if (trim((string)$value) !== '') { return false; }
    }
    return true;
  }
}

if (!function_exists('chr_dynamic_normalize_field')) {
  function chr_dynamic_normalize_field(array $field, $input) {
    $type = (string)($field['type'] ?? 'text');
    if ($type === 'repeater') {
      $rows = is_array($input) ? array_values($input) : [];
      $cleanRows = [];
      $columns = isset($field['columns']) && is_array($field['columns']) ? $field['columns'] : [];
      foreach (array_slice($rows, 0, 200) as $row) {
        if (!is_array($row)) { continue; }
        $cleanRow = [];
        foreach ($columns as $column) {
          if (!is_array($column)) { continue; }
          $key = (string)($column['key'] ?? '');
          if ($key === '') { continue; }
          $cleanRow[$key] = chr_dynamic_clean_scalar($row[$key] ?? '', (string)($column['type'] ?? 'text'), $column['options'] ?? []);
        }
        if (!chr_dynamic_row_empty($cleanRow)) {
          $cleanRows[] = $cleanRow;
        }
      }
      $minRows = max(0, (int)($field['min_rows'] ?? 0));
      while (count($cleanRows) < $minRows) {
        $empty = [];
        foreach ($columns as $column) {
          if (is_array($column) && ($column['key'] ?? '') !== '') {
            $empty[(string)$column['key']] = '';
          }
        }
        $cleanRows[] = $empty;
      }
      return $cleanRows;
    }
    if ($type === 'signature') {
      $source = is_array($input) ? $input : [];
      return [
        'nama' => chr_dynamic_clean_scalar($source['nama'] ?? '', 'text'),
        'nip' => chr_dynamic_clean_scalar($source['nip'] ?? '', 'text'),
        'jabatan' => chr_dynamic_clean_scalar($source['jabatan'] ?? '', 'text'),
        'user_id' => chr_dynamic_clean_scalar($source['user_id'] ?? '', 'text'),
        'document_role' => chr_dynamic_clean_scalar($source['document_role'] ?? '', 'text'),
        'document_role_label' => chr_dynamic_clean_scalar($source['document_role_label'] ?? '', 'text'),
        'unit_id' => chr_dynamic_clean_scalar($source['unit_id'] ?? '', 'text'),
        'unit' => chr_dynamic_clean_scalar($source['unit'] ?? '', 'text'),
        'tanggal' => chr_dynamic_clean_scalar($source['tanggal'] ?? '', 'date'),
        'lokasi' => chr_dynamic_clean_scalar($source['lokasi'] ?? '', 'text'),
        'signature' => chr_dynamic_clean_scalar($source['signature'] ?? '', 'signature_data'),
        'status_signature' => chr_dynamic_clean_scalar($source['status_signature'] ?? '', 'text'),
        'signed_at' => chr_dynamic_clean_scalar($source['signed_at'] ?? '', 'text'),
        'signed_ip' => chr_dynamic_clean_scalar($source['signed_ip'] ?? '', 'text'),
        'signed_user_agent' => chr_dynamic_clean_scalar($source['signed_user_agent'] ?? '', 'text'),
      ];
    }
    return chr_dynamic_clean_scalar($input ?? '', $type, $field['options'] ?? []);
  }
}

if (!function_exists('chr_sop_signer_has_snapshot')) {
  function chr_sop_signer_has_snapshot($signer): bool {
    if (!is_array($signer)) { return false; }
    foreach (['nama', 'nip', 'jabatan', 'unit', 'signature'] as $key) {
      if (trim((string)($signer[$key] ?? '')) !== '') { return true; }
    }
    return false;
  }
}

if (!function_exists('chr_sop_apply_signer_profile')) {
  function chr_sop_apply_signer_profile(mysqli $conn, array $input, array $stored, string $documentRole, string $documentRoleLabel, array &$seen, array &$errors, int $currentUserId = 0, array $requestMeta = [], bool $locked = false): array {
    $userId = $locked && (int)($stored['user_id'] ?? 0) > 0
      ? (int)$stored['user_id']
      : (int)($input['user_id'] ?? 0);
    if ($userId < 1) {
      if (chr_sop_signer_has_snapshot($stored)) {
        return $stored;
      }
      return [
        'user_id' => '',
        'document_role' => $documentRole,
        'document_role_label' => $documentRoleLabel,
        'nama' => '',
        'nip' => '',
        'jabatan' => '',
        'unit_id' => '',
        'unit' => '',
        'tanggal' => '',
        'lokasi' => '',
        'signature' => '',
        'status_signature' => 'waiting',
        'signed_at' => '',
        'signed_ip' => '',
        'signed_user_agent' => '',
      ];
    }

    $seenKey = $documentRole.'|'.$userId;
    if (isset($seen[$seenKey])) {
      $errors[] = 'Pegawai yang sama tidak boleh dipilih lebih dari sekali pada posisi '.$documentRoleLabel.'.';
    } else {
      $seen[$seenKey] = $documentRoleLabel;
    }

    $profile = chr_employee_profile_fetch($conn, $userId);
    if (!$profile) {
      $errors[] = $documentRoleLabel.' harus memilih pegawai aktif dengan profil lengkap.';
      $profile = [
        'user_id' => $userId,
        'nama' => '',
        'nip' => '',
        'jabatan' => '',
        'unit_id' => '',
        'unit' => '',
      ];
    }

    $oldUserId = (int)($stored['user_id'] ?? 0);
    $storedSignature = chr_dynamic_clean_scalar($stored['signature'] ?? '', 'signature_data');
    $inputSignature = chr_dynamic_clean_scalar($input['signature'] ?? '', 'signature_data');
    $signature = ($oldUserId === $userId && $storedSignature !== '') ? $storedSignature : '';
    $signedAt = ($oldUserId === $userId) ? chr_dynamic_clean_scalar($stored['signed_at'] ?? '', 'text') : '';
    $signedIp = ($oldUserId === $userId) ? chr_dynamic_clean_scalar($stored['signed_ip'] ?? '', 'text') : '';
    $signedUserAgent = ($oldUserId === $userId) ? chr_dynamic_clean_scalar($stored['signed_user_agent'] ?? '', 'text') : '';

    if ($inputSignature !== '' && $inputSignature !== $storedSignature) {
      if ($currentUserId !== $userId) {
        $errors[] = 'Anda tidak memiliki hak untuk menandatangani bagian ini.';
      } else {
        $signature = $inputSignature;
        $signedAt = date('Y-m-d H:i:s');
        $signedIp = chr_dynamic_clean_scalar($requestMeta['ip'] ?? '', 'text');
        $signedUserAgent = chr_dynamic_clean_scalar($requestMeta['user_agent'] ?? '', 'text');
      }
    } elseif ($inputSignature === '' && $storedSignature !== '' && $currentUserId === $userId && (int)($input['clear_signature'] ?? 0) === 1) {
      $signature = '';
      $signedAt = '';
      $signedIp = '';
      $signedUserAgent = '';
    }

    $statusSignature = $signature !== '' ? 'signed' : 'waiting';

    return [
      'user_id' => (string)$profile['user_id'],
      'document_role' => $documentRole,
      'document_role_label' => $documentRoleLabel,
      'nama' => (string)$profile['nama'],
      'nip' => (string)$profile['nip'],
      'jabatan' => (string)$profile['jabatan'],
      'unit_id' => (string)$profile['unit_id'],
      'unit' => (string)$profile['unit'],
      'tanggal' => chr_dynamic_clean_scalar($input['tanggal'] ?? '', 'date'),
      'lokasi' => chr_dynamic_clean_scalar($input['lokasi'] ?? '', 'text'),
      'signature' => $signature,
      'status_signature' => $statusSignature,
      'signed_at' => $signedAt,
      'signed_ip' => $signedIp,
      'signed_user_agent' => $signedUserAgent,
    ];
  }
}

if (!function_exists('chr_sop_apply_employee_profiles')) {
  function chr_sop_apply_employee_profiles(mysqli $conn, array $dynamic, array $storedDynamic, array &$errors, int $currentUserId = 0, array $requestMeta = [], bool $locked = false): array {
    if (!isset($dynamic['pengesahan']) || !is_array($dynamic['pengesahan'])) { return $dynamic; }
    $seen = [];
    $storedSigners = isset($storedDynamic['pengesahan']) && is_array($storedDynamic['pengesahan']) ? $storedDynamic['pengesahan'] : [];

    $roleMap = [
      'pejabat_menyetujui' => ['approving_official', 'Pejabat Menyetujui'],
      'ketua_tim' => ['team_leader', 'Ketua Tim'],
    ];
    foreach ($roleMap as $key => $roleInfo) {
      $input = isset($dynamic['pengesahan'][$key]) && is_array($dynamic['pengesahan'][$key]) ? $dynamic['pengesahan'][$key] : [];
      $stored = isset($storedSigners[$key]) && is_array($storedSigners[$key]) ? $storedSigners[$key] : [];
      $dynamic['pengesahan'][$key] = chr_sop_apply_signer_profile($conn, $input, $stored, $roleInfo[0], $roleInfo[1], $seen, $errors, $currentUserId, $requestMeta, $locked);
    }

    $rows = isset($dynamic['pengesahan']['anggota_tim']) && is_array($dynamic['pengesahan']['anggota_tim'])
      ? array_values($dynamic['pengesahan']['anggota_tim'])
      : [];
    $storedRows = isset($storedSigners['anggota_tim']) && is_array($storedSigners['anggota_tim'])
      ? array_values($storedSigners['anggota_tim'])
      : [];
    $cleanRows = [];
    foreach ($rows as $idx => $row) {
      $input = is_array($row) ? $row : [];
      $stored = isset($storedRows[$idx]) && is_array($storedRows[$idx]) ? $storedRows[$idx] : [];
      if ((int)($input['user_id'] ?? 0) < 1 && !chr_sop_signer_has_snapshot($stored)) {
        $hasNewData = false;
        foreach (['nama', 'nip', 'jabatan', 'unit', 'signature'] as $key) {
          if (trim((string)($input[$key] ?? '')) !== '') { $hasNewData = true; break; }
        }
        if (!$hasNewData) { continue; }
      }
      $cleanRows[] = chr_sop_apply_signer_profile($conn, $input, $stored, 'team_member', 'Anggota Tim', $seen, $errors, $currentUserId, $requestMeta, $locked);
    }
    if (!$cleanRows) {
      $cleanRows[] = [
        'user_id' => '',
        'document_role' => 'team_member',
        'document_role_label' => 'Anggota Tim',
        'nama' => '',
        'nip' => '',
        'jabatan' => '',
        'unit_id' => '',
        'unit' => '',
        'tanggal' => '',
        'lokasi' => '',
        'signature' => '',
        'status_signature' => 'waiting',
        'signed_at' => '',
        'signed_ip' => '',
        'signed_user_agent' => '',
      ];
    }
    $dynamic['pengesahan']['anggota_tim'] = $cleanRows;
    return $dynamic;
  }
}

if (!function_exists('chr_sop_collect_signers')) {
  function chr_sop_collect_signers(array $data): array {
    $dynamic = isset($data['dynamic']) && is_array($data['dynamic']) ? $data['dynamic'] : [];
    $pengesahan = isset($dynamic['pengesahan']) && is_array($dynamic['pengesahan']) ? $dynamic['pengesahan'] : [];
    $out = [];
    foreach (['pejabat_menyetujui', 'ketua_tim'] as $key) {
      if (isset($pengesahan[$key]) && is_array($pengesahan[$key])) {
        $out[] = $pengesahan[$key];
      }
    }
    if (isset($pengesahan['anggota_tim']) && is_array($pengesahan['anggota_tim'])) {
      foreach ($pengesahan['anggota_tim'] as $row) {
        if (is_array($row)) { $out[] = $row; }
      }
    }
    return $out;
  }
}

if (!function_exists('chr_sop_signature_anchor')) {
  function chr_sop_signature_anchor(string $documentRole, int $memberIndex = -1): string {
    if ($documentRole === 'approving_official') {
      return 'approval-approving-official';
    }
    if ($documentRole === 'team_leader') {
      return 'approval-team-leader';
    }
    if ($documentRole === 'team_member') {
      return 'approval-team-member-' . max(0, $memberIndex);
    }
    return 'approval-section';
  }
}

if (!function_exists('chr_sop_workflow_default')) {
  function chr_sop_workflow_default(): array {
    return [
      'status' => 'draft',
      'submitted_at' => '',
      'submitted_by' => '',
      'returned_at' => '',
      'returned_by' => '',
      'return_note' => '',
      'reopened_at' => '',
      'reopened_by' => '',
    ];
  }
}

if (!function_exists('chr_sop_workflow')) {
  function chr_sop_workflow(array $data): array {
    $workflow = isset($data['workflow']) && is_array($data['workflow']) ? $data['workflow'] : [];
    return array_merge(chr_sop_workflow_default(), $workflow);
  }
}

if (!function_exists('chr_sop_required_signers_ready')) {
  function chr_sop_required_signers_ready(array $data, array &$errors = []): bool {
    $dynamic = isset($data['dynamic']) && is_array($data['dynamic']) ? $data['dynamic'] : [];
    $pengesahan = isset($dynamic['pengesahan']) && is_array($dynamic['pengesahan']) ? $dynamic['pengesahan'] : [];
    foreach (['pejabat_menyetujui' => 'Pejabat Menyetujui', 'ketua_tim' => 'Ketua Tim'] as $key => $label) {
      $signer = isset($pengesahan[$key]) && is_array($pengesahan[$key]) ? $pengesahan[$key] : [];
      if ((int)($signer['user_id'] ?? 0) < 1) {
        $errors[] = $label.' wajib dipilih sebelum pengesahan diajukan.';
      } elseif (trim((string)($signer['nama'] ?? '')) === ''
        || trim((string)($signer['nip'] ?? '')) === ''
        || trim((string)($signer['jabatan'] ?? '')) === ''
        || (int)($signer['unit_id'] ?? 0) < 1
        || trim((string)($signer['unit'] ?? '')) === '') {
        $errors[] = $label.' wajib memiliki profil pegawai aktif yang lengkap.';
      }
    }
    $members = isset($pengesahan['anggota_tim']) && is_array($pengesahan['anggota_tim']) ? $pengesahan['anggota_tim'] : [];
    $memberReady = false;
    $memberSeen = [];
    foreach ($members as $idx => $member) {
      if (!is_array($member)) { continue; }
      $userId = (int)($member['user_id'] ?? 0);
      if ($userId < 1) { continue; }
      $memberReady = true;
      if (trim((string)($member['nama'] ?? '')) === ''
        || trim((string)($member['nip'] ?? '')) === ''
        || trim((string)($member['jabatan'] ?? '')) === ''
        || (int)($member['unit_id'] ?? 0) < 1
        || trim((string)($member['unit'] ?? '')) === '') {
        $errors[] = 'Anggota Tim wajib memiliki profil pegawai aktif yang lengkap.';
      }
      if (isset($memberSeen[$userId])) {
        $errors[] = 'Anggota Tim tidak boleh berisi pegawai yang sama lebih dari satu kali.';
      }
      $memberSeen[$userId] = true;
    }
    if (!$memberReady) {
      $errors[] = 'Minimal satu Anggota Tim wajib dipilih sebelum pengesahan diajukan.';
    }
    return !$errors;
  }
}

if (!function_exists('chr_sop_clear_signer_signature')) {
  function chr_sop_clear_signer_signature(array $signer): array {
    $signer['signature'] = '';
    $signer['status_signature'] = 'waiting';
    $signer['signed_at'] = '';
    $signer['signed_ip'] = '';
    $signer['signed_user_agent'] = '';
    return $signer;
  }
}

if (!function_exists('chr_sop_map_signers')) {
  function chr_sop_map_signers(array $data, callable $callback): array {
    if (!isset($data['dynamic']['pengesahan']) || !is_array($data['dynamic']['pengesahan'])) { return $data; }
    foreach (['pejabat_menyetujui', 'ketua_tim'] as $key) {
      if (isset($data['dynamic']['pengesahan'][$key]) && is_array($data['dynamic']['pengesahan'][$key])) {
        $data['dynamic']['pengesahan'][$key] = $callback($data['dynamic']['pengesahan'][$key], $key);
      }
    }
    if (isset($data['dynamic']['pengesahan']['anggota_tim']) && is_array($data['dynamic']['pengesahan']['anggota_tim'])) {
      foreach ($data['dynamic']['pengesahan']['anggota_tim'] as $idx => $signer) {
        if (is_array($signer)) {
          $data['dynamic']['pengesahan']['anggota_tim'][$idx] = $callback($signer, 'anggota_tim');
        }
      }
    }
    return $data;
  }
}

if (!function_exists('chr_sop_recalculate_workflow')) {
  function chr_sop_recalculate_workflow(array $data): array {
    $workflow = chr_sop_workflow($data);
    if (!in_array($workflow['status'], ['waiting_signatures', 'partially_signed', 'approved'], true)) {
      $data['workflow'] = $workflow;
      return $data;
    }
    $total = 0;
    $signed = 0;
    foreach (chr_sop_collect_signers($data) as $signer) {
      if ((int)($signer['user_id'] ?? 0) < 1) { continue; }
      $total++;
      if (trim((string)($signer['signature'] ?? '')) !== '' || ($signer['status_signature'] ?? '') === 'signed') {
        $signed++;
      }
    }
    if ($total > 0 && $signed >= $total) {
      $workflow['status'] = 'approved';
    } elseif ($signed > 0) {
      $workflow['status'] = 'partially_signed';
    } else {
      $workflow['status'] = 'waiting_signatures';
    }
    $data['workflow'] = $workflow;
    return $data;
  }
}

if (!function_exists('chr_sop_submit_for_signatures')) {
  function chr_sop_submit_for_signatures(array $data, int $submittedBy, array &$errors = []): array {
    if (!chr_sop_required_signers_ready($data, $errors)) { return $data; }
    $data = chr_sop_map_signers($data, function (array $signer): array {
      return chr_sop_clear_signer_signature($signer);
    });
    $workflow = chr_sop_workflow($data);
    $workflow['status'] = 'waiting_signatures';
    $workflow['submitted_at'] = date('Y-m-d H:i:s');
    $workflow['submitted_by'] = (string)$submittedBy;
    $workflow['returned_at'] = '';
    $workflow['returned_by'] = '';
    $workflow['return_note'] = '';
    $data['workflow'] = $workflow;
    return $data;
  }
}

if (!function_exists('chr_sop_return_for_revision')) {
  function chr_sop_return_for_revision(array $data, int $returnedBy, string $note): array {
    $workflow = chr_sop_workflow($data);
    $workflow['status'] = 'returned';
    $workflow['returned_at'] = date('Y-m-d H:i:s');
    $workflow['returned_by'] = (string)$returnedBy;
    $workflow['return_note'] = chr_dynamic_clean_scalar($note, 'textarea');
    $data['workflow'] = $workflow;
    return $data;
  }
}

if (!function_exists('chr_sop_reopen_draft')) {
  function chr_sop_reopen_draft(array $data, int $reopenedBy): array {
    $data = chr_sop_map_signers($data, function (array $signer): array {
      return chr_sop_clear_signer_signature($signer);
    });
    $workflow = chr_sop_workflow($data);
    $workflow['status'] = 'draft';
    $workflow['reopened_at'] = date('Y-m-d H:i:s');
    $workflow['reopened_by'] = (string)$reopenedBy;
    $data['workflow'] = $workflow;
    return $data;
  }
}

if (!function_exists('chr_sop_user_has_waiting_signature')) {
  function chr_sop_user_has_waiting_signature(array $data, int $userId): bool {
    if ($userId < 1) { return false; }
    $workflow = chr_sop_workflow($data);
    if (!in_array($workflow['status'], ['waiting_signatures', 'partially_signed'], true)) { return false; }
    foreach (chr_sop_collect_signers($data) as $signer) {
      if ((int)($signer['user_id'] ?? 0) !== $userId) { continue; }
      $status = (string)($signer['status_signature'] ?? '');
      $signature = trim((string)($signer['signature'] ?? ''));
      if ($status === '') { $status = $signature !== '' ? 'signed' : 'waiting'; }
      if ($status === 'waiting') { return true; }
    }
    return false;
  }
}

if (!function_exists('chr_sop_pending_signature_tasks')) {
  function chr_sop_pending_signature_tasks(mysqli $conn, int $userId): array {
    if ($userId < 1 || !ensure_chr_form_schema($conn)) { return []; }
    if (!chr_form_column_exists($conn, 'template_code')) { return []; }
    $sql = "SELECT f.reviu_id, f.template_code, f.data_json, f.updated_at, r.kode, j.nama AS jenis_nama
            FROM reviu_chr_form f
            LEFT JOIN reviu r ON r.id = f.reviu_id
            LEFT JOIN jenis_reviu j ON j.id = r.jenis_id
            WHERE f.template_code IN ('".implode("','", array_map([$conn, 'real_escape_string'], chr_approval_template_codes()))."')
            ORDER BY f.updated_at DESC, f.reviu_id DESC
            LIMIT 200";
    $res = $conn->query($sql);
    if (!$res) { return []; }
    $tasks = [];
    while ($row = $res->fetch_assoc()) {
      $decoded = json_decode((string)($row['data_json'] ?? ''), true);
      if (!is_array($decoded)) { continue; }
      $workflow = chr_sop_workflow($decoded);
      if (!in_array($workflow['status'], ['waiting_signatures', 'partially_signed'], true)) { continue; }
      $dynamic = isset($decoded['dynamic']) && is_array($decoded['dynamic']) ? $decoded['dynamic'] : [];
      $pengesahan = isset($dynamic['pengesahan']) && is_array($dynamic['pengesahan']) ? $dynamic['pengesahan'] : [];
      $signerItems = [];
      if (isset($pengesahan['pejabat_menyetujui']) && is_array($pengesahan['pejabat_menyetujui'])) {
        $signerItems[] = ['signer' => $pengesahan['pejabat_menyetujui'], 'role' => 'approving_official', 'index' => -1];
      }
      if (isset($pengesahan['ketua_tim']) && is_array($pengesahan['ketua_tim'])) {
        $signerItems[] = ['signer' => $pengesahan['ketua_tim'], 'role' => 'team_leader', 'index' => -1];
      }
      if (isset($pengesahan['anggota_tim']) && is_array($pengesahan['anggota_tim'])) {
        foreach (array_values($pengesahan['anggota_tim']) as $memberIndex => $memberSigner) {
          if (is_array($memberSigner)) {
            $signerItems[] = ['signer' => $memberSigner, 'role' => 'team_member', 'index' => (int)$memberIndex];
          }
        }
      }
      foreach ($signerItems as $signerItem) {
        $signer = $signerItem['signer'];
        if ((int)($signer['user_id'] ?? 0) !== $userId) { continue; }
        $status = (string)($signer['status_signature'] ?? '');
        $signature = trim((string)($signer['signature'] ?? ''));
        if ($status === '') { $status = $signature !== '' ? 'signed' : 'waiting'; }
        if ($status === 'signed') { continue; }
        $documentRole = (string)($signer['document_role'] ?? $signerItem['role']);
        $tasks[] = [
          'reviu_id' => (int)($row['reviu_id'] ?? 0),
          'kode' => (string)($row['kode'] ?? ''),
          'jenis_nama' => (string)($row['jenis_nama'] ?? chr_template_display_name((string)($row['template_code'] ?? ''))),
          'document_role' => $documentRole,
          'document_role_label' => (string)($signer['document_role_label'] ?? 'Penanda Tangan'),
          'jabatan' => (string)($signer['jabatan'] ?? ''),
          'approval_anchor' => chr_sop_signature_anchor($documentRole, (int)$signerItem['index']),
          'updated_at' => (string)($row['updated_at'] ?? ''),
          'submitted_at' => (string)($workflow['submitted_at'] ?? ''),
          'status' => $status,
        ];
      }
    }
    return $tasks;
  }
}

if (!function_exists('chr_dynamic_normalize_input')) {
  function chr_dynamic_normalize_input(array $template, array $input, array $storedData = [], ?array $rev = null, ?mysqli $conn = null, array &$errors = [], int $currentUserId = 0, array $requestMeta = [], bool $locked = false): array {
    $defaults = chr_dynamic_defaults($template, $rev);
    $storedDynamic = isset($storedData['dynamic']) && is_array($storedData['dynamic']) ? $storedData['dynamic'] : [];
    $dynamic = $storedDynamic;

    foreach (($template['sections'] ?? []) as $section) {
      if (!is_array($section)) { continue; }
      $sectionKey = (string)($section['key'] ?? '');
      if ($sectionKey === '') { continue; }
      $dynamic[$sectionKey] = isset($dynamic[$sectionKey]) && is_array($dynamic[$sectionKey])
        ? $dynamic[$sectionKey]
        : ($defaults['dynamic'][$sectionKey] ?? []);
      $sectionInput = isset($input[$sectionKey]) && is_array($input[$sectionKey]) ? $input[$sectionKey] : [];
      foreach (($section['fields'] ?? []) as $field) {
        if (!is_array($field)) { continue; }
        $fieldKey = (string)($field['key'] ?? '');
        if ($fieldKey === '') { continue; }
        if (array_key_exists($fieldKey, $sectionInput)) {
          $dynamic[$sectionKey][$fieldKey] = chr_dynamic_normalize_field($field, $sectionInput[$fieldKey]);
        } elseif (!array_key_exists($fieldKey, $dynamic[$sectionKey])) {
          $dynamic[$sectionKey][$fieldKey] = $defaults['dynamic'][$sectionKey][$fieldKey] ?? chr_dynamic_field_default($field, $rev);
        }
      }
    }

    $templateCode = (string)($template['code'] ?? '');
    if (chr_template_uses_standard_approval($templateCode) && $conn instanceof mysqli) {
      $storedDynamicForSop = isset($storedData['dynamic']) && is_array($storedData['dynamic']) ? $storedData['dynamic'] : [];
      $dynamic = chr_sop_apply_employee_profiles($conn, $dynamic, $storedDynamicForSop, $errors, $currentUserId, $requestMeta, $locked);
    }

    $payload = ['dynamic' => $dynamic];
    if (chr_template_uses_standard_approval($templateCode)) {
      $payload['workflow'] = chr_sop_workflow($storedData);
      $payload = chr_sop_recalculate_workflow($payload);
    }
    return $payload;
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

if (!function_exists('chr_form_column_exists')) {
  function chr_form_column_exists(mysqli $conn, string $column): bool {
    static $cache = [];
    $column = trim($column);
    if ($column === '') { return false; }
    if (array_key_exists($column, $cache)) { return $cache[$column]; }
    $columnEsc = $conn->real_escape_string($column);
    $ok = false;
    if ($rs = $conn->query("SHOW COLUMNS FROM `reviu_chr_form` LIKE '{$columnEsc}'")) {
      $ok = $rs->num_rows > 0;
      $rs->free();
    }
    $cache[$column] = $ok;
    return $ok;
  }
}

if (!function_exists('chr_form_fetch_stored_row')) {
  function chr_form_fetch_stored_row(mysqli $conn, int $reviuId): ?array {
    if ($reviuId < 1 || !ensure_chr_form_schema($conn)) { return null; }
    $hasTemplateCode = chr_form_column_exists($conn, 'template_code');
    $hasTemplateVersion = chr_form_column_exists($conn, 'template_version');
    $columns = ['data_json'];
    if (chr_form_column_exists($conn, 'updated_at')) { $columns[] = 'updated_at'; }
    if ($hasTemplateCode) { $columns[] = 'template_code'; }
    if ($hasTemplateVersion) { $columns[] = 'template_version'; }
    $sql = "SELECT " . implode(', ', $columns) . " FROM reviu_chr_form WHERE reviu_id=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return null; }
    $stmt->bind_param("i", $reviuId);
    $row = null;
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      if ($res) { $res->free(); }
    }
    $stmt->close();
    return $row ?: null;
  }
}

if (!function_exists('chr_form_review_meta')) {
  function chr_form_review_meta(mysqli $conn, int $reviuId): ?array {
    if ($reviuId < 1) { return null; }
    $reviewColumns = ['r.id', 'r.jenis_id', 'j.nama AS jenis_nama'];
    if (function_exists('review_table_column_exists')) {
      if (review_table_column_exists($conn, 'reviu', 'template_code')) {
        $reviewColumns[] = 'r.template_code';
      }
      if (review_table_column_exists($conn, 'jenis_reviu', 'template_code')) {
        $reviewColumns[] = 'j.template_code AS jenis_template_code';
      }
    } else {
      if ($rs = $conn->query("SHOW COLUMNS FROM `reviu` LIKE 'template_code'")) {
        if ($rs->num_rows > 0) { $reviewColumns[] = 'r.template_code'; }
        $rs->free();
      }
      if ($rs = $conn->query("SHOW COLUMNS FROM `jenis_reviu` LIKE 'template_code'")) {
        if ($rs->num_rows > 0) { $reviewColumns[] = 'j.template_code AS jenis_template_code'; }
        $rs->free();
      }
    }
    $stmt = $conn->prepare(
      "SELECT ".implode(', ', $reviewColumns)."
       FROM reviu r
       LEFT JOIN jenis_reviu j ON j.id = r.jenis_id
       WHERE r.id=? LIMIT 1"
    );
    if (!$stmt) { return null; }
    $stmt->bind_param("i", $reviuId);
    $row = null;
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      if ($res) { $res->free(); }
    }
    $stmt->close();
    return $row ?: null;
  }
}

if (!function_exists('chr_form_fetch')) {
  function chr_form_fetch(mysqli $conn, int $reviuId, ?array $rev = null): array {
    $storedRow = $reviuId > 0 ? chr_form_fetch_stored_row($conn, $reviuId) : null;
    $templateCode = chr_template_resolve_for_form($rev, $storedRow);
    $template = chr_template_get($templateCode) ?: chr_template_get('chr_legacy_laporan_keuangan');
    $templateVersion = chr_template_version($templateCode);
    if ($storedRow && isset($storedRow['template_version'])) {
      $storedVersion = (int)$storedRow['template_version'];
      if ($storedVersion > 0) { $templateVersion = $storedVersion; }
    }

    $data = chr_form_defaults($rev, $templateCode);
    if (($template['renderer'] ?? '') === 'dynamic') {
      $data = chr_form_merge($data, chr_dynamic_defaults($template ?: [], $rev));
      if (chr_template_uses_standard_approval((string)($template['code'] ?? '')) && !isset($data['workflow'])) {
        $data['workflow'] = chr_sop_workflow_default();
      }
    }
    if ($storedRow) {
      $json = json_decode((string)($storedRow['data_json'] ?? ''), true);
      if (is_array($json)) {
        $data = chr_form_merge($data, $json);
      }
    }

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
    $data['template_code'] = $templateCode;
    $data['template_version'] = $templateVersion;
    $data['template'] = $template ?: [];
    return $data;
  }
}

if (!function_exists('chr_form_save')) {
  function chr_form_save(mysqli $conn, int $reviuId, array $data, ?array $rev = null): bool {
    if ($reviuId < 1) { return false; }
    if (!ensure_chr_form_schema($conn)) { return false; }

    $hasTemplateCode = chr_form_column_exists($conn, 'template_code');
    $hasTemplateVersion = chr_form_column_exists($conn, 'template_version');
    $hasUpdatedAt = chr_form_column_exists($conn, 'updated_at');
    $storedRow = chr_form_fetch_stored_row($conn, $reviuId);
    if ($rev === null) {
      $rev = chr_form_review_meta($conn, $reviuId);
    }
    $templateCode = chr_template_resolve_for_form($rev, $storedRow);
    $defaults = chr_form_defaults($rev, $templateCode);
    $template = chr_template_get($templateCode);
    if (($template['renderer'] ?? '') === 'dynamic') {
      $defaults = chr_form_merge($defaults, chr_dynamic_defaults($template, $rev));
      if (chr_template_uses_standard_approval((string)($template['code'] ?? '')) && !isset($defaults['workflow'])) {
        $defaults['workflow'] = chr_sop_workflow_default();
      }
    }
    $storedData = [];
    if ($storedRow) {
      $decoded = json_decode((string)($storedRow['data_json'] ?? ''), true);
      if (is_array($decoded)) {
        $storedData = $decoded;
      }
    }
    $data = chr_form_merge_preserve_legacy($storedData, $data, $defaults);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; }

    if ($hasTemplateCode && $hasTemplateVersion) {
      $templateVersion = chr_template_version($templateCode);
      $sql = $hasUpdatedAt
        ? "INSERT INTO reviu_chr_form (reviu_id, template_code, template_version, data_json, updated_at)
           VALUES (?, ?, ?, ?, NOW())
           ON DUPLICATE KEY UPDATE
             template_code = CASE WHEN reviu_chr_form.template_code IS NULL OR reviu_chr_form.template_code = '' THEN VALUES(template_code) ELSE reviu_chr_form.template_code END,
             template_version = CASE WHEN reviu_chr_form.template_code IS NULL OR reviu_chr_form.template_code = '' THEN VALUES(template_version) ELSE reviu_chr_form.template_version END,
             data_json = VALUES(data_json),
             updated_at = NOW()"
        : "INSERT INTO reviu_chr_form (reviu_id, template_code, template_version, data_json)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE
             template_code = CASE WHEN reviu_chr_form.template_code IS NULL OR reviu_chr_form.template_code = '' THEN VALUES(template_code) ELSE reviu_chr_form.template_code END,
             template_version = CASE WHEN reviu_chr_form.template_code IS NULL OR reviu_chr_form.template_code = '' THEN VALUES(template_version) ELSE reviu_chr_form.template_version END,
             data_json = VALUES(data_json)";
      $stmt = $conn->prepare($sql);
      if (!$stmt) { return false; }
      $stmt->bind_param("isis", $reviuId, $templateCode, $templateVersion, $json);
      $ok = $stmt->execute();
      $stmt->close();
      return $ok;
    }

    $sql = $hasUpdatedAt
      ? "INSERT INTO reviu_chr_form (reviu_id, data_json, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE data_json=VALUES(data_json), updated_at=NOW()"
      : "INSERT INTO reviu_chr_form (reviu_id, data_json)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE data_json=VALUES(data_json)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return false; }
    $stmt->bind_param("is", $reviuId, $json);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}
