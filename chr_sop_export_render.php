<?php
declare(strict_types=1);

if (!function_exists('chr_sop_doc_value')) {
  function chr_sop_doc_value(array $dynamic, string $sectionKey, string $fieldKey): string {
    $value = $dynamic[$sectionKey][$fieldKey] ?? '';
    if (is_array($value)) { return ''; }
    return trim((string)$value);
  }
}

if (!function_exists('chr_sop_doc_rows')) {
  function chr_sop_doc_rows(array $dynamic, string $sectionKey, string $fieldKey): array {
    $rows = $dynamic[$sectionKey][$fieldKey] ?? [];
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
  }
}

if (!function_exists('chr_sop_doc_field_html')) {
  function chr_sop_doc_field_html(string $label, string $value): string {
    $value = trim($value);
    return '<tr><th>'.chr_sop_export_escape($label).'</th><td>'.($value !== '' ? nl2br(chr_sop_export_escape($value)) : '-').'</td></tr>';
  }
}

if (!function_exists('chr_sop_doc_table_html')) {
  function chr_sop_doc_table_html(array $columns, array $rows): string {
    $html = '<table class="doc-table"><thead><tr><th style="width:38px;">No</th>';
    foreach ($columns as $column) {
      $html .= '<th>'.chr_sop_export_escape((string)($column['label'] ?? $column['key'] ?? '')).'</th>';
    }
    $html .= '</tr></thead><tbody>';
    $hasRows = false;
    foreach ($rows as $idx => $row) {
      $nonEmpty = false;
      foreach ($columns as $column) {
        if (trim((string)($row[(string)($column['key'] ?? '')] ?? '')) !== '') { $nonEmpty = true; break; }
      }
      if (!$nonEmpty) { continue; }
      $hasRows = true;
      $html .= '<tr><td class="center">'.($idx + 1).'</td>';
      foreach ($columns as $column) {
        $key = (string)($column['key'] ?? '');
        $type = (string)($column['type'] ?? 'text');
        $value = (string)($row[$key] ?? '');
        if ($type === 'date') { $value = chr_sop_export_date($value); }
        $html .= '<td>'.($value !== '' ? nl2br(chr_sop_export_escape($value)) : '&nbsp;').'</td>';
      }
      $html .= '</tr>';
    }
    if (!$hasRows) {
      $html .= '<tr><td colspan="'.(count($columns) + 1).'" class="muted">Tidak terdapat data</td></tr>';
    }
    return $html.'</tbody></table>';
  }
}

if (!function_exists('chr_sop_doc_blocks_html')) {
  function chr_sop_doc_blocks_html(array $columns, array $rows, string $itemTitle): string {
    $html = '<div class="block-list">';
    $hasRows = false;
    foreach ($rows as $idx => $row) {
      $nonEmpty = false;
      foreach ($columns as $column) {
        if (trim((string)($row[(string)($column['key'] ?? '')] ?? '')) !== '') { $nonEmpty = true; break; }
      }
      if (!$nonEmpty) { continue; }
      $hasRows = true;
      $html .= '<div class="doc-block"><div class="doc-block-title">'.chr_sop_export_escape($itemTitle).' '.($idx + 1).'</div><table class="doc-meta">';
      foreach ($columns as $column) {
        $key = (string)($column['key'] ?? '');
        $label = (string)($column['label'] ?? $key);
        $value = (string)($row[$key] ?? '');
        if (($column['type'] ?? '') === 'date') { $value = chr_sop_export_date($value); }
        $html .= chr_sop_doc_field_html($label, $value);
      }
      $html .= '</table></div>';
    }
    if (!$hasRows) { $html .= '<div class="muted empty-box">Tidak terdapat data</div>'; }
    return $html.'</div>';
  }
}

if (!function_exists('chr_sop_doc_required_sections')) {
  function chr_sop_doc_required_sections(array $template): array {
    if (isset($template['export_sections']) && is_array($template['export_sections']) && $template['export_sections']) {
      return $template['export_sections'];
    }
    $map = [];
    foreach (chr_sop_export_sections($template) as $section) {
      $map[(string)($section['key'] ?? '')] = $section;
    }
    return [
      ['key' => 'identitas', 'title' => 'Identitas Dokumen', 'fields' => ['nomor_chr', 'tanggal_chr', 'unit_kerja', 'periode', 'nomor_surat_tugas', 'tanggal_surat_tugas']],
      ['key' => 'penyusun', 'title' => 'Penyusun Dokumen'],
      ['key' => 'uraian_tugas', 'title' => 'Uraian Tugas Jabatan'],
      ['key' => 'daftar_sop_section', 'title' => 'Daftar SOP'],
      ['key' => 'dokumen_sop', 'title' => 'Pemeriksaan Dokumen SOP'],
      ['key' => 'format_sop', 'title' => 'Pemeriksaan Format SOP'],
      ['key' => 'pelaksanaan', 'title' => 'Pelaksanaan SOP'],
      ['key' => 'temuan', 'title' => 'Hasil Temuan', 'block_repeaters' => true],
      ['key' => 'tindak_lanjut', 'title' => 'Hasil yang Perlu Ditindaklanjuti', 'only' => ['perlu_tindak_lanjut']],
      ['key' => 'tindak_lanjut', 'title' => 'Hal yang Sudah Ditindaklanjuti', 'only' => ['sudah_ditindaklanjuti']],
      ['key' => 'catatan_rekomendasi', 'title' => 'Catatan Lainnya', 'only' => ['catatan_lainnya']],
      ['key' => 'catatan_rekomendasi', 'title' => 'Rekomendasi', 'only' => ['rekomendasi_sop']],
      ['key' => 'catatan_rekomendasi', 'title' => 'Kesimpulan', 'only' => ['kesimpulan']],
      ['key' => 'pengesahan', 'title' => 'Pengesahan'],
    ];
  }
}

if (!function_exists('chr_sop_doc_signature_html')) {
  function chr_sop_doc_official_title(array $signer): string {
    $jabatan = trim((string)($signer['jabatan'] ?? ''));
    $unit = trim((string)($signer['unit'] ?? ''));
    $blocked = ['auditee', 'auditor', 'kepala_ski', 'admin', 'super_admin', 'superadmin', 'ski'];
    if ($jabatan === '' || strcasecmp($jabatan, $unit) === 0 || in_array(strtolower($jabatan), $blocked, true)) {
      return '-';
    }
    return $jabatan;
  }

  function chr_sop_doc_signature_html(array $signer): string {
    $role = trim((string)($signer['document_role_label'] ?? 'Penanda Tangan'));
    $nama = trim((string)($signer['nama'] ?? ''));
    $nip = trim((string)($signer['nip'] ?? ''));
    $jabatan = chr_sop_doc_official_title($signer);
    $unit = trim((string)($signer['unit'] ?? ''));
    $signed = chr_sop_export_is_signed($signer);
    $sig = chr_sop_export_signature_src($signer['signature'] ?? '');
    $signedAt = chr_sop_export_datetime($signer['signed_at'] ?? '');
    $html = '<div class="signature-official">';
    $html .= '<div class="sign-role">'.chr_sop_export_escape($role).'</div>';
    $html .= '<div class="sign-title">'.chr_sop_export_escape($jabatan !== '' ? $jabatan : '-').'</div>';
    if ($sig !== '' && $signed) {
      $html .= '<div class="sign-box"><img src="'.chr_sop_export_escape($sig).'" alt="Tanda tangan '.chr_sop_export_escape($nama).'"></div>';
    } else {
      $html .= '<div class="sign-box waiting">&nbsp;</div>';
    }
    $html .= '<div class="sign-name">'.chr_sop_export_escape($nama !== '' ? $nama : '-').'</div>';
    $html .= '<div>NIP. '.chr_sop_export_escape($nip !== '' ? $nip : '-').'</div>';
    if ($unit !== '') { $html .= '<div class="sign-unit">'.chr_sop_export_escape($unit).'</div>'; }
    if ($signed && $signedAt !== '-' && $signedAt !== '') {
      $html .= '<div class="sign-date">Ditandatangani pada '.chr_sop_export_escape($signedAt).'</div>';
    }
    return $html.'</div>';
  }
}

if (!function_exists('chr_sop_export_render_html')) {
  function chr_sop_export_render_html(array $payload, array $options = []): string {
    $mode = (string)($options['mode'] ?? 'preview');
    $autoPrint = !empty($options['auto_print']);
    $isFinal = $mode === 'final';
    $revInfo = $payload['revInfo'] ?? [];
    $template = $payload['template'] ?? [];
    $dynamic = $payload['dynamic'] ?? [];
    $workflow = $payload['workflow'] ?? ['status' => 'draft'];
    $signers = chr_sop_export_signers($payload['data'] ?? []);
    $identitas = $dynamic['identitas'] ?? [];
    if (!is_array($identitas)) { $identitas = []; }
    $title = chr_sop_export_blank($identitas['judul_dokumen'] ?? 'CATATAN HASIL REVIU');
    $subtitle = chr_sop_export_blank($identitas['subjudul'] ?? 'REVIU STANDAR OPERASIONAL PROSEDUR');
    $header1 = chr_sop_export_blank($identitas['header_baris_1'] ?? 'KEMENTERIAN KESEHATAN REPUBLIK INDONESIA');
    $header2 = chr_sop_export_blank($identitas['header_baris_2'] ?? 'POLTEKKES KEMENKES TERNATE');
    $unitKerja = chr_sop_export_blank($identitas['unit_kerja'] ?? ($revInfo['unit_nama'] ?? ''));
    $watermark = !$isFinal ? 'DRAFT - BELUM DISAHKAN' : '';

    ob_start();
    ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?= chr_sop_export_escape($title) ?></title>
  <style>
    @page{size:A4;margin:18mm 16mm;}
    body{font-family:Arial,Calibri,sans-serif;color:#172018;font-size:10.5pt;line-height:1.42;margin:0;background:#eef3ef;}
    .page{max-width:210mm;margin:0 auto 18px;background:#fff;padding:18mm 16mm;box-shadow:0 8px 26px rgba(0,0,0,.12);box-sizing:border-box;}
    .official-head{text-align:center;border-bottom:2px solid #1f5130;padding-bottom:10px;margin-bottom:12px;}
    .official-head .ministry{font-weight:700;font-size:12pt;text-transform:uppercase;}
    .official-head .agency{font-weight:700;font-size:12pt;text-transform:uppercase;}
    .official-head .unit{font-size:10pt;text-transform:uppercase;margin-top:2px;}
    h1{font-size:17pt;text-align:center;margin:14px 0 3px;text-transform:uppercase;}
    .doc-subtitle{text-align:center;font-weight:700;font-size:12pt;margin:0 0 12px;text-transform:uppercase;}
    h2{font-size:12.5pt;margin:20px 0 8px;color:#145c32;border-bottom:1px solid #cfe3d4;padding-bottom:4px;page-break-after:avoid;}
    h3{font-size:11.5pt;margin:14px 0 6px;color:#233;}
    .doc-meta,.doc-table{width:100%;border-collapse:collapse;margin:8px 0 12px;table-layout:fixed;}
    .doc-meta th{width:32%;text-align:left;background:#f4fbf6;}
    .doc-meta th,.doc-meta td,.doc-table th,.doc-table td{border:1px solid #b8cabb;padding:6px;vertical-align:top;word-wrap:break-word;}
    .doc-table th{background:#eaf5ee;text-align:center;}
    .center{text-align:center}.muted{color:#778277}.watermark{border:1px solid #c46b6b;color:#9f3131;background:#fff7f7;text-align:center;font-weight:700;padding:6px;margin:0 0 10px;letter-spacing:.8px;font-size:10pt;}
    .doc-summary{width:100%;border-collapse:collapse;margin:8px 0 14px;table-layout:fixed}.doc-summary td{padding:3px 6px;border:0}.doc-summary .label{width:28%;font-weight:700;color:#314b37}
    .small{font-size:9pt;color:#667}.empty-box{border:1px solid #b8cabb;padding:8px;margin:6px 0 12px}
    .text-block{border:1px solid #b8cabb;padding:8px;margin:6px 0 12px;min-height:24px;white-space:pre-wrap;}
    .doc-block{border:1px solid #b8cabb;border-radius:4px;padding:8px;margin:8px 0 12px;break-inside:avoid}.doc-block-title{font-weight:700;color:#145c32;margin-bottom:4px}
    .signature-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:10px;}
    .signature-members{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:12px;}
    .signature-official{text-align:center;break-inside:avoid;padding:10px 8px;}
    .sign-role{font-weight:700;color:#145c32;margin-bottom:4px}.sign-title{min-height:20px;margin-bottom:8px}
    .sign-box{height:76px;display:flex;align-items:center;justify-content:center;margin:8px 0 6px;background:#fff;color:#8a8a8a;font-size:9pt;}
    .sign-box.waiting{border-bottom:1px dotted #999}.sign-box img{max-width:170px;max-height:70px;object-fit:contain}.sign-name{font-weight:700;text-decoration:underline}.sign-unit,.sign-date{font-size:9pt;color:#667;margin-top:2px}.section{break-inside:auto}.page-break{page-break-before:always;}
    @media print{body{background:#fff}.page{box-shadow:none;margin:0;padding:0;max-width:none}.watermark{position:fixed;top:0;left:0;right:0}}
  </style>
  <?php if($autoPrint): ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print();},200);});</script><?php endif; ?>
</head>
<body>
  <div class="page">
    <?php if($watermark !== ''): ?><div class="watermark"><?= chr_sop_export_escape($watermark) ?></div><?php endif; ?>
    <div class="official-head">
      <div class="ministry"><?= chr_sop_export_escape($header1) ?></div>
      <div class="agency"><?= chr_sop_export_escape($header2) ?></div>
      <div class="unit"><?= chr_sop_export_escape($unitKerja) ?></div>
    </div>
    <h1><?= chr_sop_export_escape($title) ?></h1>
    <div class="doc-subtitle"><?= chr_sop_export_escape($subtitle) ?></div>
    <table class="doc-summary">
      <tr><td class="label">Nomor CHR</td><td>: <?= chr_sop_export_escape(chr_sop_export_blank($identitas['nomor_chr'] ?? '')) ?></td></tr>
      <tr><td class="label">Nomor Surat Tugas</td><td>: <?= chr_sop_export_escape(chr_sop_export_blank($identitas['nomor_surat_tugas'] ?? '')) ?></td></tr>
      <tr><td class="label">Tanggal Surat Tugas</td><td>: <?= chr_sop_export_escape(chr_sop_export_date($identitas['tanggal_surat_tugas'] ?? '')) ?></td></tr>
      <tr><td class="label">Periode</td><td>: <?= chr_sop_export_escape(chr_sop_export_blank($identitas['periode'] ?? '')) ?></td></tr>
    </table>

    <?php
    $sectionNo = 1;
    foreach (chr_sop_doc_required_sections($template) as $sectionSpec):
      $sectionKey = (string)($sectionSpec['key'] ?? '');
      if ($sectionKey === 'pengesahan') { break; }
      $section = null;
      foreach (chr_sop_export_sections($template) as $candidate) {
        if ((string)($candidate['key'] ?? '') === $sectionKey) { $section = $candidate; break; }
      }
      if (!$section) { continue; }
      $sectionKey = (string)($section['key'] ?? '');
      $only = isset($sectionSpec['only']) && is_array($sectionSpec['only']) ? $sectionSpec['only'] : null;
      $fieldLimit = isset($sectionSpec['fields']) && is_array($sectionSpec['fields']) ? $sectionSpec['fields'] : null;
    ?>
      <div class="section">
        <h2><?= $sectionNo++ ?>. <?= chr_sop_export_escape((string)($sectionSpec['title'] ?? $section['title'] ?? $sectionKey)) ?></h2>
        <?php
        $simpleRows = '';
        foreach (($section['fields'] ?? []) as $field) {
          if (!is_array($field)) { continue; }
          $fieldKey = (string)($field['key'] ?? '');
          if ($only !== null && !in_array($fieldKey, $only, true)) { continue; }
          if ($fieldLimit !== null && !in_array($fieldKey, $fieldLimit, true)) { continue; }
          $type = (string)($field['type'] ?? 'text');
          $label = (string)($field['label'] ?? $fieldKey);
          if ($type === 'repeater') {
            if ($simpleRows !== '') { echo '<table class="doc-meta">'.$simpleRows.'</table>'; $simpleRows = ''; }
            $columns = is_array($field['columns'] ?? null) ? $field['columns'] : [];
            $rows = chr_sop_doc_rows($dynamic, $sectionKey, $fieldKey);
            if (!empty($sectionSpec['block_repeaters']) || count($columns) > 5) {
              echo chr_sop_doc_blocks_html($columns, $rows, $label === 'Hasil Temuan' ? 'Temuan' : $label);
            } else {
              echo chr_sop_doc_table_html($columns, $rows);
            }
          } elseif ($type === 'textarea') {
            if ($simpleRows !== '') { echo '<table class="doc-meta">'.$simpleRows.'</table>'; $simpleRows = ''; }
            $value = chr_sop_doc_value($dynamic, $sectionKey, $fieldKey);
            echo '<div class="text-block">'.($value !== '' ? nl2br(chr_sop_export_escape($value)) : '-').'</div>';
          } else {
            $value = chr_sop_doc_value($dynamic, $sectionKey, $fieldKey);
            if ($type === 'date') { $value = chr_sop_export_date($value); }
            $simpleRows .= chr_sop_doc_field_html($label, $value);
          }
        }
        if ($simpleRows !== '') { echo '<table class="doc-meta">'.$simpleRows.'</table>'; }
        ?>
      </div>
    <?php endforeach; ?>

    <div class="section page-break">
      <?php if($watermark !== ''): ?><div class="watermark"><?= chr_sop_export_escape($watermark) ?></div><?php endif; ?>
      <h2><?= $sectionNo ?>. Pengesahan</h2>
      <div class="signature-row">
        <?php foreach (array_slice($signers, 0, 2) as $signer): ?>
          <?= chr_sop_doc_signature_html(is_array($signer) ? $signer : []) ?>
        <?php endforeach; ?>
      </div>
      <?php if (count($signers) > 2): ?>
      <div class="signature-members">
        <?php foreach (array_slice($signers, 2) as $signer): ?>
          <?= chr_sop_doc_signature_html(is_array($signer) ? $signer : []) ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php
    return ob_get_clean();
  }
}

if (!function_exists('chr_sop_docx_xml')) {
  function chr_sop_docx_xml(string $text): string {
    return htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
  }
}

if (!function_exists('chr_sop_docx_paragraph')) {
  function chr_sop_docx_paragraph(string $text, bool $bold = false, string $size = '22'): string {
    $runs = '';
    foreach (preg_split('/\R/', $text) ?: [''] as $line) {
      $runs .= '<w:r><w:rPr>'.($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/></w:rPr><w:t xml:space="preserve">'.chr_sop_docx_xml($line).'</w:t></w:r>';
      $runs .= '<w:r><w:br/></w:r>';
    }
    return '<w:p>'.$runs.'</w:p>';
  }
}

if (!function_exists('chr_sop_docx_table')) {
  function chr_sop_docx_table(array $headers, array $rows): string {
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr>';
    $xml .= '<w:tr>';
    foreach ($headers as $header) { $xml .= '<w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>'.chr_sop_docx_xml((string)$header).'</w:t></w:r></w:p></w:tc>'; }
    $xml .= '</w:tr>';
    if (!$rows) { $rows = [['Tidak terdapat data']]; }
    foreach ($rows as $row) {
      $xml .= '<w:tr>';
      foreach ($row as $cell) { $xml .= '<w:tc>'.chr_sop_docx_paragraph((string)$cell).'</w:tc>'; }
      $xml .= '</w:tr>';
    }
    return $xml.'</w:tbl>';
  }
}

if (!function_exists('chr_sop_docx_image_paragraph')) {
  function chr_sop_docx_image_paragraph(string $relId): string {
    return '<w:p><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0"><wp:extent cx="1828800" cy="731520"/><wp:docPr id="1" name="Tanda Tangan"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="signature.png"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="'.$relId.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1828800" cy="731520"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
  }
}

if (!function_exists('chr_sop_export_docx_binary')) {
  function chr_sop_export_docx_binary(array $payload, string $mode = 'preview'): string {
    if (!class_exists('ZipArchive')) { throw new RuntimeException('ZipArchive tidak tersedia.'); }
    $isFinal = $mode === 'final';
    $template = $payload['template'] ?? [];
    $dynamic = $payload['dynamic'] ?? [];
    $revInfo = $payload['revInfo'] ?? [];
    $workflow = $payload['workflow'] ?? ['status' => 'draft'];
    $identitas = $dynamic['identitas'] ?? [];
    if (!is_array($identitas)) { $identitas = []; }
    $title = chr_sop_export_blank($identitas['judul_dokumen'] ?? 'CATATAN HASIL REVIU');
    $subtitle = chr_sop_export_blank($identitas['subjudul'] ?? 'REVIU STANDAR OPERASIONAL PROSEDUR');
    $header1 = chr_sop_export_blank($identitas['header_baris_1'] ?? 'KEMENTERIAN KESEHATAN REPUBLIK INDONESIA');
    $header2 = chr_sop_export_blank($identitas['header_baris_2'] ?? 'POLTEKKES KEMENKES TERNATE');
    $unitKerja = chr_sop_export_blank($identitas['unit_kerja'] ?? ($revInfo['unit_nama'] ?? ''));
    $body = '';
    $media = [];
    $mediaRels = [];
    $nextRel = 1;
    $addSignature = static function (string $dataUrl) use (&$media, &$mediaRels, &$nextRel): string {
      $src = chr_sop_export_signature_src($dataUrl);
      if ($src === '') { return ''; }
      $parts = explode(',', $src, 2);
      if (count($parts) !== 2) { return ''; }
      $bin = base64_decode($parts[1], true);
      if ($bin === false || $bin === '' || strlen($bin) > 750000) { return ''; }
      $name = 'signature'.$nextRel.'.png';
      $relId = 'rId'.$nextRel;
      $nextRel++;
      $media[$name] = $bin;
      $mediaRels[] = '<Relationship Id="'.$relId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$name.'"/>';
      return $relId;
    };
    if (!$isFinal) { $body .= chr_sop_docx_paragraph('DRAFT - BELUM DISAHKAN', true, '24'); }
    $body .= chr_sop_docx_paragraph($header1, true, '24');
    $body .= chr_sop_docx_paragraph($header2, true, '24');
    $body .= chr_sop_docx_paragraph($unitKerja, false, '20');
    $body .= chr_sop_docx_paragraph($title, true, '32');
    $body .= chr_sop_docx_paragraph($subtitle, true, '24');
    $body .= chr_sop_docx_paragraph('Nomor CHR: '.chr_sop_export_blank($identitas['nomor_chr'] ?? ''));
    $body .= chr_sop_docx_paragraph('Nomor Surat Tugas: '.chr_sop_export_blank($identitas['nomor_surat_tugas'] ?? ''));
    $body .= chr_sop_docx_paragraph('Tanggal Surat Tugas: '.chr_sop_export_date($identitas['tanggal_surat_tugas'] ?? ''));
    $body .= chr_sop_docx_paragraph('Periode: '.chr_sop_export_blank($identitas['periode'] ?? ''));
    $sectionNo = 1;
    foreach (chr_sop_doc_required_sections($template) as $sectionSpec) {
      $sectionKey = (string)($sectionSpec['key'] ?? '');
      if ($sectionKey === 'pengesahan') { break; }
      $section = null;
      foreach (chr_sop_export_sections($template) as $candidate) {
        if ((string)($candidate['key'] ?? '') === $sectionKey) { $section = $candidate; break; }
      }
      if (!$section) { continue; }
      $only = isset($sectionSpec['only']) && is_array($sectionSpec['only']) ? $sectionSpec['only'] : null;
      $fieldLimit = isset($sectionSpec['fields']) && is_array($sectionSpec['fields']) ? $sectionSpec['fields'] : null;
      $body .= chr_sop_docx_paragraph($sectionNo++.'. '.(string)($sectionSpec['title'] ?? $section['title'] ?? $sectionKey), true, '26');
      foreach (($section['fields'] ?? []) as $field) {
        if (!is_array($field)) { continue; }
        $fieldKey = (string)($field['key'] ?? '');
        if ($only !== null && !in_array($fieldKey, $only, true)) { continue; }
        if ($fieldLimit !== null && !in_array($fieldKey, $fieldLimit, true)) { continue; }
        $type = (string)($field['type'] ?? 'text');
        $label = (string)($field['label'] ?? $fieldKey);
        if ($type === 'repeater') {
          $columns = is_array($field['columns'] ?? null) ? $field['columns'] : [];
          $dataRows = chr_sop_doc_rows($dynamic, $sectionKey, $fieldKey);
          if (!empty($sectionSpec['block_repeaters']) || count($columns) > 5) {
            $hasRows = false;
            foreach ($dataRows as $idx => $row) {
              $nonEmpty = false;
              foreach ($columns as $column) {
                if (trim((string)($row[(string)($column['key'] ?? '')] ?? '')) !== '') { $nonEmpty = true; break; }
              }
              if (!$nonEmpty) { continue; }
              $hasRows = true;
              $body .= chr_sop_docx_paragraph(($label === 'Hasil Temuan' ? 'Temuan' : $label).' '.($idx + 1), true);
              foreach ($columns as $column) {
                $key = (string)($column['key'] ?? '');
                $value = trim((string)($row[$key] ?? ''));
                if (($column['type'] ?? '') === 'date') { $value = chr_sop_export_date($value); }
                $body .= chr_sop_docx_paragraph((string)($column['label'] ?? $key).': '.($value !== '' ? $value : '-'));
              }
            }
            if (!$hasRows) { $body .= chr_sop_docx_paragraph('Tidak terdapat data'); }
          } else {
            $headers = ['No'];
            foreach ($columns as $column) { $headers[] = (string)($column['label'] ?? $column['key'] ?? ''); }
            $rows = [];
            foreach ($dataRows as $idx => $row) {
              $line = [(string)($idx + 1)];
              $nonEmpty = false;
              foreach ($columns as $column) {
                $key = (string)($column['key'] ?? '');
                $value = trim((string)($row[$key] ?? ''));
                if (($column['type'] ?? '') === 'date') { $value = chr_sop_export_date($value); }
                if ($value !== '') { $nonEmpty = true; }
                $line[] = $value !== '' ? $value : '-';
              }
              if ($nonEmpty) { $rows[] = $line; }
            }
            $body .= chr_sop_docx_table($headers, $rows);
          }
        } else {
          $value = chr_sop_doc_value($dynamic, $sectionKey, $fieldKey);
          if ($type === 'date') { $value = chr_sop_export_date($value); }
          $body .= chr_sop_docx_paragraph($label.': '.($value !== '' ? $value : '-'));
        }
      }
    }
    $body .= chr_sop_docx_paragraph($sectionNo.'. Pengesahan', true, '26');
    foreach (chr_sop_export_signers($payload['data'] ?? []) as $signer) {
      $signed = chr_sop_export_is_signed($signer);
      $body .= chr_sop_docx_paragraph((string)($signer['document_role_label'] ?? 'Penanda Tangan'), true);
      $body .= chr_sop_docx_paragraph(chr_sop_doc_official_title(is_array($signer) ? $signer : []));
      if ($signed) {
        $relId = $addSignature((string)($signer['signature'] ?? ''));
        if ($relId !== '') { $body .= chr_sop_docx_image_paragraph($relId); }
      }
      $body .= chr_sop_docx_paragraph(chr_sop_export_blank($signer['nama'] ?? '-'), true);
      $body .= chr_sop_docx_paragraph('NIP. '.chr_sop_export_blank($signer['nip'] ?? '-'));
      if (trim((string)($signer['unit'] ?? '')) !== '') { $body .= chr_sop_docx_paragraph((string)$signer['unit']); }
      if ($signed) {
        $body .= chr_sop_docx_paragraph('Ditandatangani pada '.chr_sop_export_datetime($signer['signed_at'] ?? ''));
      } else {
        $body .= chr_sop_docx_paragraph('');
      }
    }
    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1021" w:right="907" w:bottom="1021" w:left="907"/></w:sectPr></w:body></w:document>';
    $tmp = tempnam(sys_get_temp_dir(), 'chr_sop_docx_');
    if ($tmp === false) { throw new RuntimeException('Gagal membuat file sementara DOCX.'); }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('Gagal membuka arsip DOCX.'); }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('', $mediaRels).'</Relationships>');
    foreach ($media as $name => $bin) {
      $zip->addFromString('word/media/'.$name, $bin);
    }
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();
    $binary = file_get_contents($tmp);
    @unlink($tmp);
    if ($binary === false) { throw new RuntimeException('Gagal membaca DOCX.'); }
    return $binary;
  }
}

if (!function_exists('chr_approval_export_render_html')) {
  function chr_approval_export_render_html(array $payload, array $options = []): string {
    return chr_sop_export_render_html($payload, $options);
  }
}

if (!function_exists('chr_approval_export_docx_binary')) {
  function chr_approval_export_docx_binary(array $payload, string $mode = 'preview'): string {
    return chr_sop_export_docx_binary($payload, $mode);
  }
}
