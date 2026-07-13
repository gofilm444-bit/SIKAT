<?php
declare(strict_types=1);

if (!function_exists('chr_sop_export_escape')) {
  function chr_sop_export_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('chr_sop_export_filename_part')) {
  function chr_sop_export_filename_part(string $value): string {
    $value = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $value);
    $value = trim((string)$value, '_');
    return $value !== '' ? $value : 'CHR_SOP';
  }
}

if (!function_exists('chr_sop_export_template_codes')) {
  function chr_sop_export_template_codes(): array {
    if (function_exists('chr_approval_template_codes')) {
      return chr_approval_template_codes();
    }
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

if (!function_exists('chr_sop_export_authorized')) {
  function chr_sop_export_authorized(mysqli $conn, int $rid, string $roleSlug): bool {
    if (function_exists('is_admin_like') && is_admin_like($roleSlug)) { return true; }
    if (function_exists('is_auditor') && is_auditor($roleSlug)) {
      return function_exists('review_is_assigned') && review_is_assigned($conn, $rid, 'AUDITOR');
    }
    if (function_exists('is_auditee') && is_auditee($roleSlug)) {
      return function_exists('review_is_assigned') && review_is_assigned($conn, $rid, 'AUDITEE');
    }
    if (function_exists('is_director_like') && is_director_like($roleSlug)) { return true; }
    return false;
  }
}

if (!function_exists('chr_sop_export_load')) {
  function chr_sop_export_load(mysqli $conn, int $rid): ?array {
    $revInfo = null;
    $sql = "SELECT r.id, r.kode, r.periode_mulai, r.periode_selesai, r.status, u.nama unit_nama, j.nama jenis_nama
            FROM reviu r
            JOIN unit_kerja u ON u.id=r.unit_id
            JOIN jenis_reviu j ON j.id=r.jenis_id
            WHERE r.id=?";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param('i', $rid);
      if ($stmt->execute()) { $revInfo = $stmt->get_result()->fetch_assoc(); }
      $stmt->close();
    }
    if (!$revInfo) { return null; }

    $row = function_exists('chr_form_fetch_stored_row') ? chr_form_fetch_stored_row($conn, $rid) : null;
    $templateCode = (string)($row['template_code'] ?? '');
    if (!$row || !in_array($templateCode, chr_sop_export_template_codes(), true)) { return null; }
    $decoded = json_decode((string)($row['data_json'] ?? ''), true);
    if (!is_array($decoded)) { $decoded = []; }
    $template = function_exists('chr_template_get') ? chr_template_get($templateCode) : [];
    if (!is_array($template) || (($template['renderer'] ?? '') !== 'dynamic')) { return null; }
    $data = chr_form_fetch($conn, $rid, $revInfo);
    $workflow = function_exists('chr_sop_workflow') ? chr_sop_workflow($data) : ['status' => 'draft'];
    $prefixMap = [
      'chr_sop' => 'CHR_SOP',
      'chr_rkakl' => 'CHR_RKAKL',
      'chr_manajemen_risiko' => 'CHR_MANRISK',
      'chr_pengembangan_pegawai' => 'CHR_PENGEMBANGAN_PEGAWAI',
      'chr_lhkpn_lhkasn' => 'CHR_LHKPN_LHKASN',
      'chr_iku_ikt' => 'CHR_IKU_IKT',
      'chr_lkj' => 'CHR_LKJ',
      'chr_pipk' => 'CHR_PIPK',
      'chr_rkbmn' => 'CHR_RKBMN',
    ];
    $prefix = $prefixMap[$templateCode] ?? 'CHR';
    $filenameBase = chr_sop_export_filename_part($prefix.'_'.(string)($revInfo['kode'] ?? ('REVIU_'.$rid)));

    return [
      'revInfo' => $revInfo,
      'template' => $template,
      'data' => $data,
      'dynamic' => isset($data['dynamic']) && is_array($data['dynamic']) ? $data['dynamic'] : [],
      'workflow' => $workflow,
      'filename_base' => $filenameBase,
    ];
  }
}

if (!function_exists('chr_sop_export_signers')) {
  function chr_sop_export_signers(array $data): array {
    $dynamic = isset($data['dynamic']) && is_array($data['dynamic']) ? $data['dynamic'] : [];
    $pengesahan = isset($dynamic['pengesahan']) && is_array($dynamic['pengesahan']) ? $dynamic['pengesahan'] : [];
    $signers = [];
    foreach ([
      'pejabat_menyetujui' => 'Pejabat Menyetujui',
      'ketua_tim' => 'Ketua Tim',
    ] as $key => $label) {
      $row = isset($pengesahan[$key]) && is_array($pengesahan[$key]) ? $pengesahan[$key] : [];
      $row['document_role_label'] = (string)($row['document_role_label'] ?? $label);
      $signers[] = $row;
    }
    $members = isset($pengesahan['anggota_tim']) && is_array($pengesahan['anggota_tim']) ? $pengesahan['anggota_tim'] : [];
    foreach ($members as $idx => $member) {
      if (!is_array($member)) { continue; }
      if ((int)($member['user_id'] ?? 0) < 1 && trim((string)($member['nama'] ?? '')) === '') { continue; }
      $member['document_role_label'] = 'Anggota Tim '.($idx + 1);
      $signers[] = $member;
    }
    return $signers;
  }
}

if (!function_exists('chr_sop_export_is_signed')) {
  function chr_sop_export_is_signed(array $signer): bool {
    return trim((string)($signer['signature'] ?? '')) !== '' || (string)($signer['status_signature'] ?? '') === 'signed';
  }
}

if (!function_exists('chr_sop_export_final_ready')) {
  function chr_sop_export_final_ready(array $payload, array &$errors = []): bool {
    $workflow = $payload['workflow'] ?? [];
    if (($workflow['status'] ?? 'draft') !== 'approved') {
      $errors[] = 'Dokumen final hanya dapat diekspor setelah seluruh pengesahan selesai.';
      return false;
    }
    $signers = chr_sop_export_signers($payload['data'] ?? []);
    if (!$signers) {
      $errors[] = 'Dokumen final hanya dapat diekspor setelah seluruh pengesahan selesai.';
      return false;
    }
    foreach ($signers as $signer) {
      if (!chr_sop_export_is_signed($signer)) {
        $errors[] = 'Dokumen final hanya dapat diekspor setelah seluruh pengesahan selesai.';
        return false;
      }
    }
    return true;
  }
}

if (!function_exists('chr_sop_export_date')) {
  function chr_sop_export_date($value): string {
    $value = trim((string)$value);
    if ($value === '') { return '-'; }
    $months = [
      1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
      $ts = strtotime($m[3].'-'.$m[2].'-'.$m[1]);
    } else {
      $ts = strtotime($value);
    }
    if ($ts === false) { return $value; }
    return (int)date('j', $ts).' '.$months[(int)date('n', $ts)].' '.date('Y', $ts);
  }
}

if (!function_exists('chr_sop_export_datetime')) {
  function chr_sop_export_datetime($value): string {
    $value = trim((string)$value);
    if ($value === '') { return '-'; }
    $ts = strtotime($value);
    if ($ts === false) { return $value; }
    return chr_sop_export_date(date('Y-m-d', $ts));
  }
}

if (!function_exists('chr_sop_export_status_label')) {
  function chr_sop_export_status_label(string $status): string {
    return [
      'draft' => 'Draft',
      'waiting_signatures' => 'Menunggu Tanda Tangan',
      'partially_signed' => 'Ditandatangani Sebagian',
      'approved' => 'Disahkan',
      'returned' => 'Dikembalikan untuk Perbaikan',
    ][$status] ?? $status;
  }
}

if (!function_exists('chr_sop_export_blank')) {
  function chr_sop_export_blank($value): string {
    $value = trim((string)$value);
    return $value !== '' ? $value : '-';
  }
}

if (!function_exists('chr_sop_export_signature_src')) {
  function chr_sop_export_signature_src($value): string {
    $value = trim((string)$value);
    if ($value === '') { return ''; }
    if (!preg_match('/^data:image\/(png|jpe?g);base64,[A-Za-z0-9+\/=]+$/', $value)) { return ''; }
    return $value;
  }
}

if (!function_exists('chr_sop_export_sections')) {
  function chr_sop_export_sections(array $template): array {
    $out = [];
    foreach (($template['sections'] ?? []) as $section) {
      if (!is_array($section)) { continue; }
      $key = (string)($section['key'] ?? '');
      if ($key === '') { continue; }
      $out[] = $section;
    }
    return $out;
  }
}

/*
 * Neutral export API for the shared CHR approval templates.
 * The chr_sop_* functions above are kept for backward compatibility with
 * existing SOP endpoints and older includes.
 */
if (!function_exists('chr_approval_export_escape')) {
  function chr_approval_export_escape($value): string {
    return chr_sop_export_escape($value);
  }
}

if (!function_exists('chr_approval_export_authorized')) {
  function chr_approval_export_authorized(mysqli $conn, int $rid, string $roleSlug): bool {
    return chr_sop_export_authorized($conn, $rid, $roleSlug);
  }
}

if (!function_exists('chr_approval_export_load')) {
  function chr_approval_export_load(mysqli $conn, int $rid): ?array {
    return chr_sop_export_load($conn, $rid);
  }
}

if (!function_exists('chr_approval_export_final_ready')) {
  function chr_approval_export_final_ready(array $payload, array &$errors = []): bool {
    return chr_sop_export_final_ready($payload, $errors);
  }
}

if (!function_exists('chr_approval_export_signers')) {
  function chr_approval_export_signers(array $data): array {
    return chr_sop_export_signers($data);
  }
}
