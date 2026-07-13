<?php
declare(strict_types=1);

if (!function_exists('chr_escape')) {
  function chr_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('chr_render_width_class')) {
  function chr_render_width_class($width): string {
    $width = (int)($width ?: 12);
    if ($width < 1 || $width > 12) { $width = 12; }
    return 'col-md-' . $width;
  }
}

if (!function_exists('chr_dynamic_value')) {
  function chr_dynamic_value(array $data, string $sectionKey, string $fieldKey) {
    return $data['dynamic'][$sectionKey][$fieldKey] ?? '';
  }
}

if (!function_exists('chr_render_dynamic_input')) {
  function chr_render_dynamic_input(string $name, array $field, $value, bool $readonly = false): string {
    $type = (string)($field['type'] ?? 'text');
    $label = (string)($field['label'] ?? $field['key'] ?? '');
    $required = !empty($field['required']);
    $requiredAttr = $required ? ' required' : '';
    $requiredMark = $required ? ' <span class="text-danger">*</span>' : '';
    $rawValue = $value;

    if ($type === 'hidden') {
      $value = is_scalar($rawValue) ? (string)$rawValue : '';
      return '<input type="hidden" name="'.chr_escape($name).'" value="'.chr_escape($value).'">';
    }

    if ($type === 'signature') {
      $items = is_array($rawValue) ? $rawValue : [];
      return chr_render_signature_panel($name, (string)($field['key'] ?? ''), $label, $items);
    }

    $html = '<label class="form-label fw-semibold">'.chr_escape($label).$requiredMark.'</label>';
    $value = is_scalar($rawValue) ? (string)$rawValue : '';
    if ($type === 'textarea') {
      $rows = max(2, (int)($field['rows'] ?? 3));
      return $html.'<textarea class="form-control" rows="'.$rows.'" name="'.chr_escape($name).'"'.$requiredAttr.($readonly ? ' readonly' : '').'>'.chr_escape($value).'</textarea>';
    }
    if ($type === 'date') {
      return $html.'<input type="date" class="form-control" name="'.chr_escape($name).'" value="'.chr_escape($value).'"'.$requiredAttr.($readonly ? ' readonly' : '').'>';
    }
    if ($type === 'number') {
      return $html.'<input type="number" step="any" class="form-control" name="'.chr_escape($name).'" value="'.chr_escape($value).'"'.$requiredAttr.($readonly ? ' readonly' : '').'>';
    }
    if ($type === 'select') {
      $out = $html.'<select class="form-select" name="'.chr_escape($name).'"'.$requiredAttr.($readonly ? ' disabled' : '').'><option value="">-- Pilih --</option>';
      foreach (($field['options'] ?? []) as $option) {
        $option = (string)$option;
        $selected = ($option === $value) ? ' selected' : '';
        $out .= '<option value="'.chr_escape($option).'"'.$selected.'>'.chr_escape($option).'</option>';
      }
      return $out.'</select>'.($readonly ? '<input type="hidden" name="'.chr_escape($name).'" value="'.chr_escape($value).'">' : '');
    }
    return $html.'<input type="text" class="form-control" name="'.chr_escape($name).'" value="'.chr_escape($value).'" maxlength="1000"'.$requiredAttr.($readonly ? ' readonly' : '').'>';
  }
}

if (!function_exists('chr_render_signature_field')) {
  function chr_render_signature_field(string $name, array $items, string $title, string $alt, bool $canSign = false, string $workflowStatus = 'draft'): string {
    $signature = (string)($items['signature'] ?? '');
    $status = (string)($items['status_signature'] ?? ($signature !== '' ? 'signed' : 'waiting'));
    $signedAt = trim((string)($items['signed_at'] ?? ''));
    $html = '<div class="chr-signature-field mt-3" data-chr-signature data-signature-disabled="'.($canSign ? '0' : '1').'">';
    if ($signature !== '') {
      $html .= '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">';
      $html .= '<span class="badge bg-success">Sudah Ditandatangani</span>';
      if ($signedAt !== '') { $html .= '<span class="small text-muted">'.chr_escape($signedAt).'</span>'; }
      $html .= '</div>';
    } elseif ($canSign) {
      $html .= '<label class="form-label small text-muted d-block mb-1">'.chr_escape($title).'</label>';
      $html .= '<div class="signature-pad-wrapper chr-signature-pad">';
      $html .= '<canvas data-role="canvas"></canvas>';
      $html .= '<div class="sig-overlay text-muted">Tarik pointer atau sentuh untuk menggambar tanda tangan.</div>';
      $html .= '</div>';
      $html .= '<div class="chr-signature-actions d-flex gap-2 mt-2">';
      $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="sig-clear">Bersihkan</button>';
      $html .= '<button type="button" class="btn btn-sm btn-primary" data-action="sig-save" disabled>Tandatangani</button>';
      $html .= '</div>';
    } else {
      $nama = trim((string)($items['nama'] ?? 'penanda tangan'));
      $jabatan = trim((string)($items['jabatan'] ?? ''));
      if ($workflowStatus === 'draft') {
        $message = 'Tanda tangan tersedia setelah dokumen diajukan.';
      } elseif ($workflowStatus === 'returned') {
        $message = 'Dokumen dikembalikan untuk perbaikan. Tanda tangan akan tersedia setelah diajukan kembali.';
      } else {
        $message = 'Menunggu tanda tangan '.$nama.($jabatan !== '' ? ' / '.$jabatan : '').'.';
      }
      $html .= '<div class="chr-signature-waiting small text-muted">'.chr_escape($message).'</div>';
    }
    $html .= '<div class="chr-signature-preview mt-2" data-role="preview"'.($signature !== '' ? '' : ' hidden').'>';
    $html .= '<img data-role="preview-img" alt="'.chr_escape($alt).'"'.($signature !== '' ? ' src="'.chr_escape($signature).'"' : '').($signature !== '' ? '' : ' hidden').'>';
    $html .= '</div>';
    $html .= '<input type="hidden" data-role="input" name="'.chr_escape($name.'[signature]').'" value="'.chr_escape($signature).'">';
    $html .= '<input type="hidden" data-role="clear-input" name="'.chr_escape($name.'[clear_signature]').'" value="0">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[status_signature]').'" value="'.chr_escape($status).'">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[signed_at]').'" value="'.chr_escape((string)($items['signed_at'] ?? '')).'">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[signed_ip]').'" value="'.chr_escape((string)($items['signed_ip'] ?? '')).'">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[signed_user_agent]').'" value="'.chr_escape((string)($items['signed_user_agent'] ?? '')).'">';
    return $html.'</div>';
  }
}

if (!function_exists('chr_render_employee_picker')) {
  function chr_render_employee_picker(string $name, array $items, array $employees, string $label, bool $locked = false): string {
    $selectedId = (int)($items['user_id'] ?? 0);
    $legacyManual = $selectedId < 1 && (
      trim((string)($items['nama'] ?? '')) !== ''
      || trim((string)($items['nip'] ?? '')) !== ''
      || trim((string)($items['jabatan'] ?? '')) !== ''
      || trim((string)($items['unit'] ?? '')) !== ''
    );
    $hasProfile = trim((string)($items['nama'] ?? '')) !== '' || trim((string)($items['nip'] ?? '')) !== '';

    $html = '<div class="chr-employee-picker" data-chr-employee-picker>';
    $html .= '<div class="d-flex align-items-center justify-content-between gap-2 mb-2">';
    $html .= '<label class="form-label small text-muted mb-0">'.chr_escape($label).'</label>';
    if (!$locked) {
      $html .= '<button type="button" class="btn btn-sm btn-outline-secondary'.($hasProfile ? '' : ' d-none').'" data-action="employee-change">Ganti Pegawai</button>';
    }
    $html .= '</div>';
    if ($legacyManual) {
      $html .= '<div class="alert alert-warning small py-2 mb-2">Data lama/manual. Untuk pembaruan berikutnya, pilih pegawai aktif dari daftar.</div>';
    }
    $html .= '<div class="chr-employee-search'.(($locked || $hasProfile) ? ' d-none' : '').'" data-role="employee-search-wrap" data-min-chars="2" data-max-results="10">';
    $html .= '<input type="search" class="form-control form-control-sm" data-role="employee-search" placeholder="Cari nama, NIP, jabatan, atau unit kerja..."'.($locked ? ' disabled' : '').'>';
    $html .= '<div class="chr-employee-help small text-muted" data-role="employee-help">Ketik minimal 2 karakter untuk mencari pegawai.</div>';
    $html .= '<div class="chr-employee-empty small text-muted d-none" data-role="employee-empty">Tidak ada pegawai yang cocok.</div>';
    $html .= '<div class="chr-employee-results" data-role="employee-results" hidden>';
    $selectedKnown = false;
    foreach ($employees as $employee) {
      if (!is_array($employee)) { continue; }
      $id = (int)($employee['id'] ?? 0);
      if ($id < 1) { continue; }
      $complete = !empty($employee['profile_complete']);
      $selected = $id === $selectedId;
      if ($selected) { $selectedKnown = true; }
      $text = trim((string)($employee['nama'] ?? ''));
      $detail = [];
      if (trim((string)($employee['jabatan'] ?? '')) !== '') { $detail[] = trim((string)$employee['jabatan']); }
      if (trim((string)($employee['unit_nama'] ?? '')) !== '') { $detail[] = trim((string)$employee['unit_nama']); }
      $searchText = trim($text.' '.($employee['nip'] ?? '').' '.($employee['jabatan'] ?? '').' '.($employee['unit_nama'] ?? ''));
      $html .= '<button type="button" class="chr-employee-option'.($selected ? ' is-selected' : '').(!$complete ? ' is-disabled' : '').'"'
        .' data-value="'.chr_escape((string)$id).'"'
        .' data-search="'.chr_escape($searchText).'"'
        .' data-nama="'.chr_escape((string)($employee['nama'] ?? '')).'"'
        .' data-nip="'.chr_escape((string)($employee['nip'] ?? '')).'"'
        .' data-jabatan="'.chr_escape((string)($employee['jabatan'] ?? '')).'"'
        .' data-unit-id="'.chr_escape((string)($employee['unit_id'] ?? '')).'"'
        .' data-unit="'.chr_escape((string)($employee['unit_nama'] ?? '')).'"'
        .' data-incomplete="'.($complete ? '0' : '1').'"'
        .(!$complete ? ' disabled' : '').'>';
      $html .= '<span class="chr-employee-option-name">'.chr_escape($text).'</span>';
      $optionMeta = chr_escape(trim((string)($employee['jabatan'] ?? '')));
      if (trim((string)($employee['unit_nama'] ?? '')) !== '') {
        $optionMeta .= ($optionMeta !== '' ? ' &bull; ' : '').chr_escape((string)$employee['unit_nama']);
      }
      $html .= '<span class="chr-employee-option-meta">'.$optionMeta.'</span>';
      $nipText = trim((string)($employee['nip'] ?? '')) !== '' ? 'NIP. '.(string)$employee['nip'] : '';
      if (!$complete) {
        $nipText = trim($nipText.' - Data profil belum lengkap', ' -');
      }
      $html .= '<span class="chr-employee-option-nip">'.chr_escape($nipText).'</span>';
      $html .= '</button>';
    }
    $html .= '</div></div>';
    if ($selectedId > 0 && !$selectedKnown && !$hasProfile) {
      $html .= '<div class="alert alert-warning small py-2 mb-2">Profil pegawai lama tidak aktif atau tidak lengkap.</div>';
    }
    $html .= '<input type="hidden" data-role="employee-select" name="'.chr_escape($name.'[user_id]').'" value="'.chr_escape((string)$selectedId).'">';
    $html .= '<div class="chr-signer-profile'.($hasProfile ? '' : ' is-empty').'" data-role="employee-profile">';
    $html .= '<div class="chr-signer-name" data-role="employee-nama-text">'.chr_escape((string)($items['nama'] ?? 'Belum memilih pegawai')).'</div>';
    $html .= '<div class="chr-signer-title" data-role="employee-jabatan-text">'.chr_escape((string)($items['jabatan'] ?? '')).'</div>';
    $html .= '<div class="chr-signer-meta" data-role="employee-nip-text">'.(trim((string)($items['nip'] ?? '')) !== '' ? 'NIP. '.chr_escape((string)$items['nip']) : '').'</div>';
    $html .= '<div class="chr-signer-unit" data-role="employee-unit-text">'.chr_escape((string)($items['unit'] ?? '')).'</div>';
    $html .= '<input type="hidden" data-role="employee-nama" name="'.chr_escape($name.'[nama]').'" value="'.chr_escape((string)($items['nama'] ?? '')).'">';
    $html .= '<input type="hidden" data-role="employee-nip" name="'.chr_escape($name.'[nip]').'" value="'.chr_escape((string)($items['nip'] ?? '')).'">';
    $html .= '<input type="hidden" data-role="employee-jabatan" name="'.chr_escape($name.'[jabatan]').'" value="'.chr_escape((string)($items['jabatan'] ?? '')).'">';
    $html .= '<input type="hidden" data-role="employee-unit" name="'.chr_escape($name.'[unit]').'" value="'.chr_escape((string)($items['unit'] ?? '')).'">';
    $html .= '<input type="hidden" data-role="employee-unit-id" name="'.chr_escape($name.'[unit_id]').'" value="'.chr_escape((string)($items['unit_id'] ?? '')).'">';
    $html .= '</div></div>';
    return $html;
  }
}

if (!function_exists('chr_signer_can_sign')) {
  function chr_signer_can_sign(array $items, int $currentUserId): bool {
    $userId = (int)($items['user_id'] ?? 0);
    $signature = trim((string)($items['signature'] ?? ''));
    $status = trim((string)($items['status_signature'] ?? ''));
    if ($status === '') { $status = $signature !== '' ? 'signed' : 'waiting'; }
    return $currentUserId > 0 && $userId > 0 && $currentUserId === $userId && $status !== 'signed';
  }
}

if (!function_exists('chr_signer_status_badge')) {
  function chr_signer_status_badge(array $items, string $workflowStatus): string {
    $signature = trim((string)($items['signature'] ?? ''));
    $status = trim((string)($items['status_signature'] ?? ''));
    if ($workflowStatus === 'returned') {
      return '<span class="badge bg-warning text-dark">Dikembalikan</span>';
    }
    if ($workflowStatus === 'approved') {
      return '<span class="badge bg-success">Disahkan</span>';
    }
    if ($signature !== '' || $status === 'signed') {
      return '<span class="badge bg-success">Ditandatangani</span>';
    }
    if (in_array($workflowStatus, ['waiting_signatures', 'partially_signed'], true)) {
      return '<span class="badge bg-warning text-dark">Menunggu Tanda Tangan</span>';
    }
    return '<span class="badge bg-secondary">Draft</span>';
  }
}

if (!function_exists('chr_render_signature_panel')) {
  function chr_render_signature_panel(string $name, string $fieldKey, string $label, array $items, array $employees = [], int $currentUserId = 0, bool $locked = false, bool $canSignWorkflow = false, string $workflowStatus = 'draft'): string {
    $isDirector = $fieldKey === 'pejabat_menyetujui';
    $isLead = $fieldKey === 'ketua_tim';
    $title = $isDirector ? 'Direktur / Pejabat Menyetujui' : ($isLead ? 'Ketua Tim' : $label);
    $signatureTitle = $isDirector ? 'Area Tanda Tangan Direktur' : ($isLead ? 'Area Tanda Tangan Ketua Tim' : 'Area Tanda Tangan');

    $anchorId = $isDirector ? 'approval-approving-official' : ($isLead ? 'approval-team-leader' : '');
    $html = '<div class="chr-signature-panel h-100"'.($anchorId !== '' ? ' id="'.chr_escape($anchorId).'"' : '').' tabindex="-1">';
    $html .= '<div class="chr-signature-panel-head"><div class="chr-signature-panel-title">'.chr_escape($title).'</div>'.chr_signer_status_badge($items, $workflowStatus).'</div>';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[document_role]').'" value="'.chr_escape($isDirector ? 'approving_official' : 'team_leader').'">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[document_role_label]').'" value="'.chr_escape($isDirector ? 'Pejabat Menyetujui' : 'Ketua Tim').'">';
    $html .= chr_render_employee_picker($name, $items, $employees, 'Pilih Pegawai', $locked);
    $html .= chr_render_signature_field($name, $items, $signatureTitle, 'Tanda tangan '.$title, $canSignWorkflow && chr_signer_can_sign($items, $currentUserId), $workflowStatus);
    $html .= '<input type="hidden" name="'.chr_escape($name.'[lokasi]').'" value="'.chr_escape((string)($items['lokasi'] ?? '')).'">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[tanggal]').'" value="'.chr_escape((string)($items['tanggal'] ?? '')).'">';
    return $html.'</div>';
  }
}

if (!function_exists('chr_render_repeater_cell')) {
  function chr_render_repeater_cell(string $name, array $column, $value, bool $readonly = false): string {
    $type = (string)($column['type'] ?? 'text');
    $value = is_scalar($value) ? (string)$value : '';
    if ($type === 'textarea') {
      return '<textarea class="form-control form-control-sm" rows="2" name="'.chr_escape($name).'"'.($readonly ? ' readonly' : '').'>'.chr_escape($value).'</textarea>';
    }
    if ($type === 'date') {
      return '<input type="date" class="form-control form-control-sm" name="'.chr_escape($name).'" value="'.chr_escape($value).'"'.($readonly ? ' readonly' : '').'>';
    }
    if ($type === 'number') {
      return '<input type="number" step="any" class="form-control form-control-sm" name="'.chr_escape($name).'" value="'.chr_escape($value).'"'.($readonly ? ' readonly' : '').'>';
    }
    if ($type === 'select') {
      $out = '<select class="form-select form-select-sm" name="'.chr_escape($name).'"'.($readonly ? ' disabled' : '').'><option value="">-- Pilih --</option>';
      foreach (($column['options'] ?? []) as $option) {
        $option = (string)$option;
        $selected = ($option === $value) ? ' selected' : '';
        $out .= '<option value="'.chr_escape($option).'"'.$selected.'>'.chr_escape($option).'</option>';
      }
      return $out.'</select>'.($readonly ? '<input type="hidden" name="'.chr_escape($name).'" value="'.chr_escape($value).'">' : '');
    }
    return '<input type="text" class="form-control form-control-sm" name="'.chr_escape($name).'" value="'.chr_escape($value).'" maxlength="1000"'.($readonly ? ' readonly' : '').'>';
  }
}

if (!function_exists('chr_render_repeater')) {
  function chr_render_repeater(array $field, array $rows, string $sectionKey, bool $readonly = false): string {
    $fieldKey = (string)($field['key'] ?? '');
    $label = (string)($field['label'] ?? $fieldKey);
    $columns = isset($field['columns']) && is_array($field['columns']) ? $field['columns'] : [];
    $minRows = max(0, (int)($field['min_rows'] ?? 0));
    while (count($rows) < $minRows) { $rows[] = []; }
    if (!$rows && $minRows > 0) { $rows[] = []; }
    $baseName = 'chr_dynamic['.$sectionKey.']['.$fieldKey.']';
    $uid = preg_replace('/[^a-z0-9_]+/i', '_', $sectionKey.'_'.$fieldKey);

    $html = '<div class="chr-dynamic-repeater" data-chr-repeater data-next-index="'.count($rows).'" data-repeater="'.$uid.'">';
    $html .= '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div class="fw-semibold">'.chr_escape($label).'</div>'.($readonly ? '' : '<button type="button" class="btn btn-sm btn-outline-success" data-action="add-row"><i class="bi bi-plus-circle"></i> Tambah Baris</button>').'</div>';
    $html .= '<div class="table-responsive"><table class="table table-sm align-middle chr-dynamic-table"><thead class="table-light"><tr>';
    foreach ($columns as $column) {
      $style = isset($column['width']) ? ' style="width:'.(int)$column['width'].'px"' : '';
      $html .= '<th'.$style.'>'.chr_escape($column['label'] ?? $column['key'] ?? '').'</th>';
    }
    $html .= '<th style="width:80px;">Aksi</th></tr></thead><tbody data-role="rows">';

    $renderRow = function ($idx, array $row) use ($columns, $baseName, $readonly): string {
      $out = '<tr data-row-index="'.$idx.'">';
      foreach ($columns as $column) {
        $key = (string)($column['key'] ?? '');
        $name = $baseName.'['.$idx.']['.$key.']';
        $out .= '<td>'.chr_render_repeater_cell($name, $column, $row[$key] ?? '', $readonly).'</td>';
      }
      return $out.'<td>'.($readonly ? '' : '<button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-row">Hapus</button>').'</td></tr>';
    };

    foreach (array_values($rows) as $idx => $row) {
      $html .= $renderRow($idx, is_array($row) ? $row : []);
    }
    $html .= '</tbody></table></div>';

    $html .= '<template data-role="template">'.$renderRow('__INDEX__', []).'</template>';
    return $html.'</div>';
  }
}

if (!function_exists('chr_render_signature_member_card')) {
  function chr_render_signature_member_card(string $baseName, $idx, array $row, array $employees = [], int $currentUserId = 0, bool $locked = false, bool $canSignWorkflow = false, string $workflowStatus = 'draft'): string {
    $name = $baseName.'['.$idx.']';
    $label = trim((string)($row['nama'] ?? ''));
    if ($label === '') { $label = is_numeric($idx) ? 'Anggota Tim '.((int)$idx + 1) : 'Anggota Tim'; }
    $memberAnchor = is_numeric($idx) ? 'approval-team-member-'.(int)$idx : '';
    $html = '<div class="chr-member-signature-card"'.($memberAnchor !== '' ? ' id="'.chr_escape($memberAnchor).'"' : '').' data-row-index="'.chr_escape((string)$idx).'" tabindex="-1">';
    $html .= '<div class="d-flex justify-content-between align-items-start gap-2 mb-2">';
    $html .= '<div><div class="fw-semibold text-success">Anggota Tim '.(is_numeric($idx) ? ((int)$idx + 1) : '').'</div>'.chr_signer_status_badge($row, $workflowStatus).'</div>';
    $html .= $locked ? '' : '<button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-row">Hapus</button>';
    $html .= '</div>';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[document_role]').'" value="team_member">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[document_role_label]').'" value="Anggota Tim">';
    $html .= chr_render_employee_picker($name, $row, $employees, 'Pilih Pegawai', $locked);
    $html .= chr_render_signature_field($name, $row, 'Area Tanda Tangan', 'Tanda tangan '.$label, $canSignWorkflow && chr_signer_can_sign($row, $currentUserId), $workflowStatus);
    $html .= '<input type="hidden" name="'.chr_escape($name.'[lokasi]').'" value="'.chr_escape((string)($row['lokasi'] ?? '')).'">';
    $html .= '<input type="hidden" name="'.chr_escape($name.'[tanggal]').'" value="'.chr_escape((string)($row['tanggal'] ?? '')).'">';
    return $html.'</div>';
  }
}

if (!function_exists('chr_render_signature_members')) {
  function chr_render_signature_members(array $field, array $rows, string $sectionKey, array $employees = [], int $currentUserId = 0, bool $locked = false, bool $canSignWorkflow = false, string $workflowStatus = 'draft'): string {
    $fieldKey = (string)($field['key'] ?? 'anggota_tim');
    $label = (string)($field['label'] ?? 'Anggota Tim');
    $minRows = max(0, (int)($field['min_rows'] ?? 0));
    while (count($rows) < $minRows) { $rows[] = []; }
    if (!$rows && $minRows > 0) { $rows[] = []; }
    $baseName = 'chr_dynamic['.$sectionKey.']['.$fieldKey.']';
    $uid = preg_replace('/[^a-z0-9_]+/i', '_', $sectionKey.'_'.$fieldKey);

    $html = '<div class="chr-signature-panel h-100" id="approval-team-members" data-chr-repeater data-next-index="'.count($rows).'" data-repeater="'.$uid.'" tabindex="-1">';
    $html .= '<div class="d-flex justify-content-between align-items-center gap-2 mb-3">';
    $html .= '<div class="chr-signature-panel-title mb-0">'.chr_escape($label).'</div>';
    $html .= $locked ? '' : '<button type="button" class="btn btn-sm btn-outline-success" data-action="add-row"><i class="bi bi-plus-circle"></i> Tambah</button>';
    $html .= '</div>';
    $html .= '<div class="chr-member-signature-list" data-role="rows">';
    foreach (array_values($rows) as $idx => $row) {
      $html .= chr_render_signature_member_card($baseName, $idx, is_array($row) ? $row : [], $employees, $currentUserId, $locked, $canSignWorkflow, $workflowStatus);
    }
    $html .= '</div>';
    if (!$locked) {
      $html .= '<template data-role="template">'.chr_render_signature_member_card($baseName, '__INDEX__', [], $employees, $currentUserId, $locked, $canSignWorkflow, $workflowStatus).'</template>';
    }
    return $html.'</div>';
  }
}

if (!function_exists('chr_render_signature_section')) {
  function chr_render_signature_section(array $section, array $data, array $context = []): string {
    $sectionKey = (string)($section['key'] ?? '');
    $title = (string)($section['title'] ?? 'Pengesahan');
    $fields = [];
    foreach (($section['fields'] ?? []) as $field) {
      if (is_array($field) && ($field['key'] ?? '') !== '') {
        $fields[(string)$field['key']] = $field;
      }
    }

    $director = chr_dynamic_value($data, $sectionKey, 'pejabat_menyetujui');
    $lead = chr_dynamic_value($data, $sectionKey, 'ketua_tim');
    $members = chr_dynamic_value($data, $sectionKey, 'anggota_tim');
    $employees = isset($context['employees']) && is_array($context['employees']) ? $context['employees'] : [];
    $currentUserId = (int)($context['current_user_id'] ?? 0);
    $locked = !empty($context['locked']);
    $workflowStatus = (string)($context['workflow_status'] ?? 'draft');
    $canSignWorkflow = in_array($workflowStatus, ['waiting_signatures', 'partially_signed'], true);

    $html = '<div class="card border-success-subtle mb-3 chr-dynamic-section chr-signature-section" id="approval-section" tabindex="-1">';
    $html .= '<div class="card-header bg-light"><h6 class="mb-0 text-success">'.chr_escape($title).'</h6></div>';
    $html .= '<div class="card-body">';
    $html .= '<div class="row g-3 chr-signature-grid">';
    $html .= '<div class="col-12 col-lg-6">'.chr_render_signature_panel('chr_dynamic['.$sectionKey.'][pejabat_menyetujui]', 'pejabat_menyetujui', 'Pejabat Menyetujui', is_array($director) ? $director : [], $employees, $currentUserId, $locked, $canSignWorkflow, $workflowStatus).'</div>';
    $html .= '<div class="col-12 col-lg-6">'.chr_render_signature_panel('chr_dynamic['.$sectionKey.'][ketua_tim]', 'ketua_tim', 'Ketua Tim', is_array($lead) ? $lead : [], $employees, $currentUserId, $locked, $canSignWorkflow, $workflowStatus).'</div>';
    $html .= '<div class="col-12">'.chr_render_signature_members($fields['anggota_tim'] ?? ['key' => 'anggota_tim', 'label' => 'Anggota Tim', 'min_rows' => 1], is_array($members) ? $members : [], $sectionKey, $employees, $currentUserId, $locked, $canSignWorkflow, $workflowStatus).'</div>';
    $html .= '</div></div></div>';
    return $html;
  }
}

if (!function_exists('chr_render_section')) {
  function chr_render_section(array $section, array $data, array $context = []): string {
    $sectionKey = (string)($section['key'] ?? '');
    $title = (string)($section['title'] ?? $sectionKey);
    if ($sectionKey === 'pengesahan') {
      return chr_render_signature_section($section, $data, $context);
    }
    $readonly = !empty($context['locked']);
    $html = '<div class="card border-success-subtle mb-3 chr-dynamic-section"><div class="card-header bg-light"><h6 class="mb-0 text-success">'.chr_escape($title).'</h6></div><div class="card-body"><div class="row g-3">';
    foreach (($section['fields'] ?? []) as $field) {
      if (!is_array($field)) { continue; }
      $type = (string)($field['type'] ?? 'text');
      $fieldKey = (string)($field['key'] ?? '');
      if ($fieldKey === '') { continue; }
      $value = chr_dynamic_value($data, $sectionKey, $fieldKey);
      if ($type === 'heading') {
        $html .= '<div class="col-12"><h6 class="mb-0">'.chr_escape($field['label'] ?? '').'</h6></div>';
      } elseif ($type === 'information') {
        $html .= '<div class="col-12"><div class="alert alert-info mb-0">'.chr_escape($field['label'] ?? '').'</div></div>';
      } elseif ($type === 'repeater') {
        $rows = is_array($value) ? $value : [];
        $html .= '<div class="col-12">'.chr_render_repeater($field, $rows, $sectionKey, $readonly).'</div>';
      } else {
        $html .= '<div class="'.chr_render_width_class($field['width'] ?? 12).'">'.chr_render_dynamic_input('chr_dynamic['.$sectionKey.']['.$fieldKey.']', $field, $value, $readonly).'</div>';
      }
    }
    return $html.'</div></div></div>';
  }
}

if (!function_exists('chr_render_dynamic_form')) {
  function chr_render_dynamic_form(array $template, array $data, array $context = []): string {
    $name = (string)($template['name'] ?? 'Template CHR');
    $version = (int)($template['version'] ?? 1);
    $workflow = function_exists('chr_sop_workflow') ? chr_sop_workflow($data) : ['status' => 'draft'];
    $statusLabel = [
      'draft' => 'Draft',
      'waiting_signatures' => 'Menunggu Tanda Tangan',
      'partially_signed' => 'Ditandatangani Sebagian',
      'approved' => 'Disahkan',
      'returned' => 'Dikembalikan',
    ];
    $status = (string)($workflow['status'] ?? 'draft');
    $statusClass = [
      'draft' => 'bg-secondary',
      'waiting_signatures' => 'bg-warning text-dark',
      'partially_signed' => 'bg-info text-dark',
      'approved' => 'bg-success',
      'returned' => 'bg-warning text-dark',
    ][$status] ?? 'bg-secondary';
    $html = '<div class="alert alert-success-subtle border border-success-subtle"><strong>Template CHR:</strong> '.chr_escape($name).' <span class="ms-2"><strong>Versi:</strong> '.chr_escape((string)$version).'</span></div>';
    $html .= '<div class="alert alert-light border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2"><span><strong>Status Pengesahan:</strong> '.chr_escape($statusLabel[$status] ?? $status).'</span><span class="badge '.$statusClass.'">'.chr_escape($statusLabel[$status] ?? $status).'</span></div>';
    if (($workflow['status'] ?? '') === 'returned' && trim((string)($workflow['return_note'] ?? '')) !== '') {
      $html .= '<div class="alert alert-warning border mb-3"><strong>Catatan Pengembalian:</strong> '.chr_escape((string)$workflow['return_note']).'</div>';
    }
    $html .= '<div class="chr-dynamic-form" data-chr-dynamic-form>';
    foreach (($template['sections'] ?? []) as $section) {
      if (is_array($section)) { $html .= chr_render_section($section, $data, $context); }
    }
    $html .= '</div>';
    $html .= <<<'HTML'
<script>
document.addEventListener('click', function (event) {
  var addBtn = event.target.closest('[data-chr-repeater] [data-action="add-row"]');
  if (addBtn) {
    var repeater = addBtn.closest('[data-chr-repeater]');
    var tbody = repeater.querySelector('[data-role="rows"]');
    var tpl = repeater.querySelector('template[data-role="template"]');
    if (!tbody || !tpl) return;
    var idx = parseInt(repeater.getAttribute('data-next-index') || '0', 10);
    var html = tpl.innerHTML.replace(/__INDEX__/g, String(idx));
    tbody.insertAdjacentHTML('beforeend', html);
    repeater.setAttribute('data-next-index', String(idx + 1));
    var added = tbody.querySelector('[data-row-index="' + idx + '"]');
    if (added) {
      document.dispatchEvent(new CustomEvent('chr:dynamic-row-added', { detail: { root: added } }));
    }
    if (window.updateSubmissionReadiness) window.updateSubmissionReadiness();
    return;
  }
  var removeBtn = event.target.closest('[data-chr-repeater] [data-action="remove-row"]');
  if (removeBtn) {
    var row = removeBtn.closest('[data-row-index]');
    if (!row) return;
    var hasValue = Array.prototype.some.call(row.querySelectorAll('input, textarea, select'), function (el) {
      return String(el.value || '').trim() !== '';
    });
    if (hasValue && !window.confirm('Hapus baris ini?')) return;
    row.remove();
    if (window.updateSubmissionReadiness) window.updateSubmissionReadiness();
  }
});

(function(){
  function setInput(root, role, value) {
    var el = root.querySelector('[data-role="' + role + '"]');
    if (el) el.value = value || '';
  }

  function setText(root, role, value) {
    var el = root.querySelector('[data-role="' + role + '"]');
    if (el) el.textContent = value || '';
  }

  function populateFromOption(root, option) {
    setInput(root, 'employee-select', option ? option.getAttribute('data-value') : '');
    setInput(root, 'employee-nama', option ? option.getAttribute('data-nama') : '');
    setInput(root, 'employee-nip', option ? option.getAttribute('data-nip') : '');
    setInput(root, 'employee-jabatan', option ? option.getAttribute('data-jabatan') : '');
    setInput(root, 'employee-unit', option ? option.getAttribute('data-unit') : '');
    setInput(root, 'employee-unit-id', option ? option.getAttribute('data-unit-id') : '');
    setText(root, 'employee-nama-text', option ? option.getAttribute('data-nama') : 'Belum memilih pegawai');
    setText(root, 'employee-jabatan-text', option ? option.getAttribute('data-jabatan') : '');
    var nip = option ? option.getAttribute('data-nip') : '';
    setText(root, 'employee-nip-text', nip ? 'NIP. ' + nip : '');
    setText(root, 'employee-unit-text', option ? option.getAttribute('data-unit') : '');
    var profile = root.querySelector('[data-role="employee-profile"]');
    if (profile) {
      profile.classList.toggle('is-empty', !option);
    }
    var changeBtn = root.querySelector('[data-action="employee-change"]');
    if (changeBtn) {
      changeBtn.classList.toggle('d-none', !option);
    }
    updateSubmissionReadiness();
  }

  function clearSignatureForPicker(root) {
    var panel = root.closest('.chr-signature-panel, .chr-member-signature-card');
    if (!panel) return true;
    var input = panel.querySelector('[data-chr-signature] [data-role="input"]');
    if (!input || !input.value) return true;
    if (!window.confirm('Penanda tangan diganti. Tanda tangan lama harus dibuat ulang. Bersihkan tanda tangan lama?')) {
      return false;
    }
    var clearBtn = panel.querySelector('[data-chr-signature] [data-action="sig-clear"]');
    if (clearBtn) clearBtn.click();
    return true;
  }

  function signerSnapshot(picker) {
    function val(role) {
      var el = picker.querySelector('[data-role="' + role + '"]');
      return el ? String(el.value || '').trim() : '';
    }
    var panel = picker.closest('.chr-signature-panel, .chr-member-signature-card');
    var roleInput = panel ? panel.querySelector('input[name$="[document_role]"]') : null;
    var userId = parseInt(val('employee-select') || '0', 10);
    return {
      role: roleInput ? String(roleInput.value || '').trim() : '',
      userId: userId,
      nama: val('employee-nama'),
      nip: val('employee-nip'),
      jabatan: val('employee-jabatan'),
      unitId: parseInt(val('employee-unit-id') || '0', 10),
      unit: val('employee-unit')
    };
  }

  function updateSubmissionReadiness() {
    var form = document.getElementById('chrTemplateForm');
    if (!form) return;
    var box = form.querySelector('[data-chr-submit-readiness]');
    var submit = form.querySelector('[data-chr-submit-button]');
    if (!box || !submit) return;

    var minRequired = parseInt(box.getAttribute('data-min-required') || '3', 10);
    var selected = 0;
    var complete = 0;
    var hasApprover = false;
    var hasLead = false;
    var memberCount = 0;
    var seen = {};
    var hasDuplicate = false;

    form.querySelectorAll('.chr-signature-section [data-chr-employee-picker]').forEach(function (picker) {
      var signer = signerSnapshot(picker);
      if (signer.userId < 1) return;
      selected += 1;
      if (seen[signer.userId]) hasDuplicate = true;
      seen[signer.userId] = true;
      if (signer.role === 'approving_official') hasApprover = true;
      if (signer.role === 'team_leader') hasLead = true;
      if (signer.role === 'team_member') memberCount += 1;
      if (signer.nama && signer.nip && signer.jabatan && signer.unitId > 0 && signer.unit) {
        complete += 1;
      }
    });

    var ready = selected >= minRequired && complete === selected && hasApprover && hasLead && memberCount >= 1 && !hasDuplicate;
    var signerCount = box.querySelector('[data-role="signer-count"]');
    var requiredCount = box.querySelector('[data-role="required-count"]');
    var profileCount = box.querySelector('[data-role="profile-count"]');
    var profileTotal = box.querySelector('[data-role="profile-total"]');
    var badge = box.querySelector('[data-role="readiness-badge"]');
    if (signerCount) signerCount.textContent = String(selected);
    if (requiredCount) requiredCount.textContent = ' dari minimal ' + minRequired;
    if (profileCount) profileCount.textContent = String(complete);
    if (profileTotal) profileTotal.textContent = selected > 0 ? ' dari ' + selected : '';
    if (badge) {
      badge.textContent = ready ? 'Siap diajukan' : (hasDuplicate ? 'Penanda tangan dobel' : 'Lengkapi penanda tangan');
      badge.classList.toggle('bg-success', ready);
      badge.classList.toggle('bg-warning', !ready);
      badge.classList.toggle('text-dark', !ready);
    }
    submit.disabled = !ready;
  }

  window.updateSubmissionReadiness = updateSubmissionReadiness;
  window.refreshApprovalReadiness = updateSubmissionReadiness;

  function updateResults(picker) {
    var search = picker.querySelector('[data-role="employee-search"]');
    var searchWrap = picker.querySelector('[data-role="employee-search-wrap"]');
    var results = picker.querySelector('[data-role="employee-results"]');
    var help = picker.querySelector('[data-role="employee-help"]');
    var empty = picker.querySelector('[data-role="employee-empty"]');
    var options = picker.querySelectorAll('.chr-employee-option[data-value]');
    var minChars = parseInt(searchWrap ? (searchWrap.getAttribute('data-min-chars') || '2') : '2', 10);
    var maxResults = parseInt(searchWrap ? (searchWrap.getAttribute('data-max-results') || '10') : '10', 10);
    var q = String(search ? search.value || '' : '').trim().toLowerCase();
    var visibleCount = 0;
    var canSearch = q.length >= minChars;

    Array.prototype.forEach.call(options, function (option) {
      option.hidden = true;
      if (!canSearch) return;
      var haystack = String(option.getAttribute('data-search') || option.textContent || '').toLowerCase();
      var match = haystack.indexOf(q) !== -1;
      if (match && visibleCount < maxResults) {
        option.hidden = false;
        visibleCount += 1;
      }
    });

    if (results) results.hidden = !canSearch || visibleCount === 0;
    if (help) help.classList.toggle('d-none', canSearch);
    if (empty) empty.classList.toggle('d-none', !canSearch || visibleCount > 0);
  }

  function initEmployeePicker(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-chr-employee-picker]').forEach(function (picker) {
      if (picker.dataset.employeePickerReady === '1') return;
      picker.dataset.employeePickerReady = '1';
      var search = picker.querySelector('[data-role="employee-search"]');
      var selectedInput = picker.querySelector('[data-role="employee-select"]');
      var searchWrap = picker.querySelector('[data-role="employee-search-wrap"]');
      var options = picker.querySelectorAll('.chr-employee-option[data-value]');
      if (!selectedInput) return;
      picker.dataset.previousValue = selectedInput.value || '';

      if (search) {
        search.addEventListener('input', function () {
          updateResults(picker);
        });
        updateResults(picker);
      }

      picker.addEventListener('click', function (event) {
        var changeBtn = event.target.closest('[data-action="employee-change"]');
        if (changeBtn) {
      if (searchWrap) searchWrap.classList.remove('d-none');
      if (search) {
        search.value = '';
        search.focus();
      }
      updateResults(picker);
      return;
    }
        var option = event.target.closest('.chr-employee-option[data-value]');
        if (!option || option.disabled || option.classList.contains('is-disabled')) return;
        var previous = picker.dataset.previousValue || '';
        var nextValue = option.getAttribute('data-value') || '';
        if (previous !== nextValue && !clearSignatureForPicker(picker)) {
          return;
        }
        populateFromOption(picker, nextValue ? option : null);
        picker.dataset.previousValue = nextValue;
        Array.prototype.forEach.call(options, function (item) {
          item.classList.toggle('is-selected', item === option && nextValue !== '');
          item.hidden = true;
        });
        if (searchWrap && nextValue !== '') searchWrap.classList.add('d-none');
      });
    });
  }

  initEmployeePicker(document);
  updateSubmissionReadiness();
  document.addEventListener('chr:dynamic-row-added', function (event) {
    if (event.detail && event.detail.root) {
      initEmployeePicker(event.detail.root);
      updateSubmissionReadiness();
    }
  });
})();
</script>
HTML;
    return $html;
  }
}
