<?php
declare(strict_types=1);

if (!function_exists('doc_escape')) {
  function doc_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('doc_nl2br')) {
  function doc_nl2br($value): string {
    return nl2br(doc_escape($value));
  }
}
if (!function_exists('doc_checkbox')) {
  function doc_checkbox(bool $checked): string {
    return $checked ? '&#9745;' : '&#9744;';
  }
}
if (!function_exists('doc_format_datetime')) {
  function doc_format_datetime(?string $value): string {
    if (!$value) { return ''; }
    $value = trim((string)$value);
    if ($value === '') { return ''; }
    $ts = strtotime($value);
    if ($ts === false) { return doc_escape($value); }
    return date('d/m/Y H:i', $ts);
  }
}
if (!function_exists('doc_format_due')) {
  function doc_format_due(?string $value): string {
    if (!$value) { return ''; }
    $value = trim((string)$value);
    if ($value === '') { return ''; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && function_exists('chr_format_tanggal_indo')) {
      return doc_escape(chr_format_tanggal_indo($value, false));
    }
    return doc_escape($value);
  }
}
if (!function_exists('doc_signature_normalize')) {
  function doc_signature_normalize(string $dataUrl): string {
    if ($dataUrl === '' || !function_exists('imagecreatefromstring')) { return $dataUrl; }
    if (!preg_match('/^data:image\/(png|jpe?g);base64,/', $dataUrl)) { return $dataUrl; }
    $parts = explode(',', $dataUrl, 2);
    if (count($parts) !== 2) { return $dataUrl; }
    $bin = base64_decode($parts[1], true);
    if ($bin === false || $bin === '') { return $dataUrl; }
    $img = @imagecreatefromstring($bin);
    if (!$img) { return $dataUrl; }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0 || ($w * $h) > 5000000) {
      imagedestroy($img);
      return $dataUrl;
    }
    $sample = [
      [0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1],
      [intdiv($w, 2), intdiv($h, 2)],
    ];
    $sum = 0; $count = 0;
    foreach ($sample as $p) {
      $rgb = imagecolorat($img, $p[0], $p[1]);
      $r = ($rgb >> 16) & 0xFF;
      $g = ($rgb >> 8) & 0xFF;
      $b = $rgb & 0xFF;
      $sum += ($r + $g + $b) / 3;
      $count++;
    }
    $avg = $count ? ($sum / $count) : 255;
    if ($avg > 60) {
      imagedestroy($img);
      return $dataUrl;
    }
    $out = imagecreatetruecolor($w, $h);
    if (!$out) { imagedestroy($img); return $dataUrl; }
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $white);
    $threshold = 35;
    for ($y = 0; $y < $h; $y++) {
      for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if ($r < $threshold && $g < $threshold && $b < $threshold) {
          continue;
        }
        $color = imagecolorallocate($out, $r, $g, $b);
        imagesetpixel($out, $x, $y, $color);
      }
    }
    ob_start();
    imagepng($out);
    $png = ob_get_clean();
    imagedestroy($img);
    imagedestroy($out);
    if ($png === false || $png === '') { return $dataUrl; }
    return 'data:image/png;base64,' . base64_encode($png);
  }
}
if (!function_exists('doc_signature_html')) {
  function doc_signature_html(?string $value, string $alt = 'Tanda tangan'): string {
    $value = trim((string)$value);
    $altEsc = doc_escape($alt);
    if ($value !== '' && preg_match('/^data:image\/(png|jpe?g);base64,[A-Za-z0-9+\/=]+$/', $value)) {
      $normalized = doc_signature_normalize($value);
      $src = doc_escape($normalized);
      return '<div class="sig-wrap"><img src="'.$src.'" alt="'.$altEsc.'" class="sig-img" style="width:180px;height:70px;max-width:180px;max-height:70px;display:inline-block;"></div>';
    }
    return '<div class="sig-wrap"><span class="sig-empty" aria-label="'.$altEsc.'"></span></div>';
  }
}

/**
 * Render HTML dokumen CHR untuk Word/PDF.
 *
 * @param array{
 *   revInfo: array<mixed>,
 *   chrSheet: array<mixed>,
 *   chrRows: array<int, array<mixed>>
 * } $payload
 */
function chr_export_render(array $payload, array $options = []): string {
  $mode = strtolower((string)($options['mode'] ?? 'word'));
  $autoPrint = !empty($options['auto_print']);
  $title = (string)($options['title'] ?? 'Catatan Hasil Reviu');

  $revInfo = $payload['revInfo'] ?? [];
  $chrSheet = $payload['chrSheet'] ?? [];
  $chrRows = $payload['chrRows'] ?? [];

  $drafterList = $chrSheet['drafter'] ?? [];
  $uapaOpts = $chrSheet['uapa_opts'] ?? [];
  $lkItems = $chrSheet['lk_items'] ?? [];
  $perbaikanList = $chrSheet['perbaikan_list'] ?? [];
  $perbaikanList = array_values(array_filter($perbaikanList, static function ($item) {
    return trim((string)$item) !== '';
  }));
  $halLain = $chrSheet['hal_lain'] ?? '';
  $rekomendasi = $chrSheet['rekomendasi'] ?? '';
  $direktur = $chrSheet['direktur'] ?? ['label' => 'Direktur', 'nama' => '', 'nip' => ''];
  $ketua = $chrSheet['ketua'] ?? ['lokasi' => '', 'waktu' => '', 'jabatan' => '', 'nama' => '', 'nip' => ''];
  $anggotaList = $chrSheet['anggota_list'] ?? [];

  $direkturSignature = trim((string)($chrSheet['direktur_signature'] ?? ''));
  $ketuaSignature = trim((string)($chrSheet['ketua_signature'] ?? ''));
  $anggotaSignatures = $chrSheet['anggota_signatures'] ?? [];
  if (!is_array($anggotaSignatures)) { $anggotaSignatures = []; }
  $anggotaSignatures = array_values($anggotaSignatures);
  $anggotaCount = count($anggotaList);
  while (count($anggotaSignatures) < $anggotaCount) { $anggotaSignatures[] = ''; }
  if ($anggotaCount > 0 && count($anggotaSignatures) > $anggotaCount) {
    $anggotaSignatures = array_slice($anggotaSignatures, 0, $anggotaCount);
  }
  $anggotaRendered = [];
  foreach ($anggotaList as $idx => $anggotaRow) {
    $aLabel = trim((string)($anggotaRow['label'] ?? ''));
    $aNama = trim((string)($anggotaRow['nama'] ?? ''));
    $aNip = trim((string)($anggotaRow['nip'] ?? ''));
    $sig = trim((string)($anggotaSignatures[$idx] ?? ''));
    if ($aLabel === '' && $aNama === '' && $aNip === '' && $sig === '') { continue; }
    $anggotaRendered[] = [
      'label' => $aLabel,
      'nama' => $aNama,
      'nip' => $aNip,
      'signature' => $sig,
    ];
  }
  $anggotaRenderedCount = count($anggotaRendered);

  ob_start();
  ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?= doc_escape($title) ?></title>
  <style>
    @page { size: A4; margin: 20mm; }
    body{font-family:"Calibri","Arial",sans-serif;color:#111;font-size:11pt;line-height:1.3;margin:0;padding:0;}
    table{width:100%;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;}
    th,td{vertical-align:top;}
    .page{border:1px solid #000;padding:18mm 18mm;min-height:24.5cm;box-sizing:border-box;page-break-after:always;position:relative;}
    .page.cover{min-height:29.7cm;}
    .sheet-detail{page-break-before:always;}
    .page:last-child{page-break-after:auto;}
    .cover-header{font-size:11pt;line-height:1.4;}
    .cover-header .line1{font-weight:600;}
    .cover-header .line2{color:#b71c1c;font-weight:600;}
    .cover-title{margin-top:6.5cm;text-align:center;}
    .cover-title h1{font-size:18pt;letter-spacing:1.5px;margin:0 0 10pt;font-weight:700;}
    .cover-title h2{font-size:12pt;margin:0 0 6pt;font-weight:500;text-transform:uppercase;}
    .cover-title p{margin:0;font-size:11pt;}
    .highlight{background-color:#fff176;}
    .page-content{font-size:10.5pt;}
    .header-grid{width:100%;table-layout:fixed;margin-bottom:10pt;}
    .header-grid .left{width:58%;padding-right:10pt;}
    .header-grid .right{width:42%;}
    .header-line{display:block;margin-bottom:2pt;}
    .approval-table{table-layout:fixed;font-size:10pt;}
    .approval-table td{border:1px solid #000;padding:4px 6px;}
    .approval-label{font-weight:600;display:block;margin-bottom:2pt;}
    .approval-value{display:block;word-wrap:break-word;word-break:break-word;line-height:1.25;}
    .uapa-table{font-size:10pt;table-layout:fixed;margin-bottom:10pt;}
    .uapa-table th,.uapa-table td{border:1px solid #000;padding:4px 6px;}
    .uapa-table .check-cell{text-align:center;width:28px;}
    .chr-table{font-size:10pt;table-layout:fixed;margin-bottom:10pt;}
    .chr-table th,.chr-table td{border:1px solid #000;padding:5px;word-wrap:break-word;word-break:break-word;}
    .chr-table th{background:#f2f2f2;text-align:center;font-weight:600;}
    .section-title{font-weight:700;margin:10pt 0 4pt;text-transform:uppercase;letter-spacing:.4pt;font-size:10.5pt;}
    .perbaikan-list{margin:0 0 8pt 18pt;padding:0;}
    .perbaikan-list li{margin:0 0 2pt 0;}
    .text-table{margin:0 0 8pt 0;}
    .text-table td{border:1px solid #000;padding:6px;min-height:1.6cm;height:1.6cm;word-wrap:break-word;word-break:break-word;}
    .signature-table{margin-top:8pt;font-size:10pt;table-layout:fixed;border-collapse:collapse;border:0 none;}
    .signature-table.leaders{border:0 none;}
    .signature-table.leaders td{border:0 none;padding:6px 8px;width:50%;}
    .signature-table.anggota-grid{border:0 none;margin-top:6pt;}
    .signature-table.anggota-grid td{border:0 none;padding:6px 8px;width:50%;}
    .signature-inner{width:100%;border-collapse:collapse;border:0 none;}
    .signature-inner td{padding:1px 0;border:0 none;}
    .signature-table, .signature-table td, .signature-inner, .signature-inner td{border:0 none !important;}
    .signature-label{display:block;font-weight:600;margin-bottom:2pt;}
    .signature-name{font-weight:600;display:block;margin-top:2pt;}
    .signature-name.highlighted{background:#fff176;padding:0 2px;display:inline-block;}
    .signature-nip{display:block;margin-top:1pt;}
    .sig-wrap{height:70px;line-height:70px;text-align:center;}
    .sig-img{width:180px;height:70px;max-width:180px;max-height:70px;display:inline-block;}
    .sig-empty{display:inline-block;width:180px;height:70px;}
    .detail-section{page-break-before:always;}
    .detail-table{font-size:9.5pt;table-layout:fixed;margin-top:6pt;}
    .detail-table th,.detail-table td{border:1px solid #777;padding:4px;word-wrap:break-word;word-break:break-word;}
    .detail-table th{background:#e8f2ff;text-align:center;}
    .meta-info{margin-bottom:8pt;font-size:10pt;}
    .meta-info span{display:inline-block;margin-right:12pt;}
    .header-grid,
    .uapa-table,
    .chr-table,
    .text-table,
    .signature-table,
    .detail-table{page-break-inside:avoid;break-inside:avoid;}
    <?php if ($mode === 'word'): ?>
    @page WordSection1 { size: 21cm 29.7cm; margin: 20mm; }
    div.word-section{ page: WordSection1; }
    .page{border:none;padding:0;min-height:auto;}
    .page.cover{min-height:auto;}
    .cover-title{margin-top:3.2cm;}
    .cover-header{margin-bottom:6pt;}
    .section-title{letter-spacing:.2pt;}
    .highlight{background-color:#fff2cc;}
    <?php endif; ?>
    <?php if ($mode === 'pdf'): ?>
    body{background:#f4f5f7;margin:0;padding:24px 0;}
    .page{max-width:210mm;margin:0 auto 24px auto;border:1px solid #ced4da;box-shadow:0 12px 30px rgba(0,0,0,.12);padding:18mm 18mm;min-height:auto;}
    .page.cover{min-height:auto;}
    .page:last-child{margin-bottom:0;}
    @media print {
      body{background:#fff;padding:0;}
      .page{box-shadow:none;border:none;margin:0;padding:20mm;max-width:none;page-break-after:always;min-height:auto;}
      .page.cover{min-height:auto;}
      .page:last-child{page-break-after:auto;}
    }
    <?php endif; ?>
  </style>
  <?php if ($autoPrint): ?>
  <script>
    window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 150); });
  </script>
  <?php endif; ?>
</head>
<body>
<?php if ($mode === 'word'): ?>
  <div class="word-section">
<?php endif; ?>
  <div class="page cover"<?= $mode === 'word' ? ' style="page-break-after:always; mso-break-type:page;"' : '' ?>>
    <div class="cover-header">
      <div class="line1"><?= doc_escape($chrSheet['header_line1'] ?? '') ?></div>
      <div class="line2"><?= doc_escape($chrSheet['header_line2'] ?? '') ?></div>
    </div>
    <div class="cover-title">
      <h1><?= doc_escape($chrSheet['cover_title'] ?? 'CATATAN HASIL REVIU') ?></h1>
      <?php if(!empty($chrSheet['cover_subtitle1'])){ ?>
        <h2><?= doc_escape($chrSheet['cover_subtitle1']) ?></h2>
      <?php } ?>
      <p>
        <?= doc_escape($chrSheet['cover_period_prefix'] ?? '') ?>
        <?php if(!empty($chrSheet['cover_period_date'])){ ?>
          <span class="highlight"><?= doc_escape($chrSheet['cover_period_date']) ?></span>
        <?php } ?>
      </p>
    </div>
  </div>

  <div class="page sheet-main"<?= $mode === 'word' ? ' style="page-break-after:always; mso-break-type:page;"' : '' ?>>
    <div class="page-content">
      <table class="header-grid">
        <tr>
          <td class="left">
            <span class="header-line"><?= doc_escape($chrSheet['header_line1'] ?? '') ?></span>
            <span class="header-line"><?= doc_escape($chrSheet['header_line2'] ?? '') ?></span>
          </td>
          <td class="right">
            <table class="approval-table">
              <?php foreach ($drafterList as $drafter){
                $label = trim((string)($drafter['label'] ?? ''));
                $name = trim((string)($drafter['nama'] ?? ''));
                $date = trim((string)($drafter['tanggal'] ?? ''));
                $valueParts = [];
                if ($name !== '') { $valueParts[] = doc_escape($name); }
                if ($date !== '') { $valueParts[] = doc_escape($date); }
                $valueText = implode(' ', $valueParts);
              ?>
              <tr>
                <td>
                  <span class="approval-label"><?= $label !== '' ? doc_escape($label) : '&nbsp;' ?></span>
                  <span class="approval-value"><?= $valueText !== '' ? $valueText : '&nbsp;' ?></span>
                </td>
              </tr>
              <?php } ?>
            </table>
          </td>
        </tr>
      </table>

      <table class="uapa-table">
        <colgroup>
          <col style="width:20%;">
          <col style="width:6%;">
          <col style="width:74%;">
        </colgroup>
        <?php
          $uapaTitleMap = [
            'uapa' => 'UAPA',
            'uappae1' => 'UAPPA-E1',
            'uappaw' => 'UAPPA-W',
            'uakpa' => 'UAKPA',
          ];
          foreach ($uapaOpts as $opt){
            $key = (string)($opt['key'] ?? '');
            $label = trim((string)($opt['label'] ?? ''));
            $checked = !empty($opt['checked']);
            if ($key === '' && $label === '' && !$checked) { continue; }
            $titleCell = $uapaTitleMap[$key] ?? strtoupper($key ?: 'UNIT');
        ?>
          <tr>
            <td><?= doc_escape($titleCell) ?></td>
            <td class="check-cell"><?= doc_checkbox($checked) ?></td>
            <td><?= doc_escape($label) ?></td>
          </tr>
        <?php } ?>
      </table>

      <table class="chr-table">
        <colgroup>
          <col style="width:12%;">
          <col style="width:68%;">
          <col style="width:20%;">
        </colgroup>
        <thead>
          <tr>
            <th colspan="2">Uraian Catatan Hasil Reviu</th>
            <th>Indeks KKR</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lkItems as $item){
            $label = trim((string)($item['label'] ?? ''));
            $uraian = trim((string)($item['uraian'] ?? ''));
            $indeks = trim((string)($item['indeks'] ?? ''));
            if ($label === '' && $uraian === '' && $indeks === '') { continue; }
          ?>
          <tr>
            <td class="lk-label"><?= doc_escape($label) ?></td>
            <td><?= $uraian !== '' ? doc_nl2br($uraian) : '&nbsp;' ?></td>
            <td><?= $indeks !== '' ? doc_escape($indeks) : '&nbsp;' ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>

      <div class="section-title">Koreksi / Perbaikan yang Belum Dilakukan</div>
      <?php if(!empty($perbaikanList)){ ?>
        <ol class="perbaikan-list">
          <?php foreach ($perbaikanList as $item){ ?>
            <li><?= doc_escape($item) ?></li>
          <?php } ?>
        </ol>
      <?php } else { ?>
        <table class="text-table">
          <tr><td>&nbsp;</td></tr>
        </table>
      <?php } ?>

      <div class="section-title">Hal-hal Lain yang Perlu Diungkapkan</div>
      <table class="text-table">
        <tr>
          <td><?= $halLain !== '' ? doc_nl2br($halLain) : '&nbsp;' ?></td>
        </tr>
      </table>

      <div class="section-title">Rekomendasi</div>
      <table class="text-table">
        <tr>
          <td><?= $rekomendasi !== '' ? doc_nl2br($rekomendasi) : '&nbsp;' ?></td>
        </tr>
      </table>

      <table class="signature-table leaders" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td>
            <table class="signature-inner" border="0" cellspacing="0" cellpadding="0">
              <?php
                $direkturAltName = trim((string)($direktur['nama'] ?? ''));
                if ($direkturAltName === '') {
                  $direkturAltName = trim((string)($direktur['label'] ?? 'Direktur'));
                }
              ?>
              <?php if(!empty($direktur['label'])){ ?><tr><td><span class="signature-label"><?= doc_escape($direktur['label']) ?></span></td></tr><?php } ?>
              <tr><td><?= doc_signature_html($direkturSignature, 'Tanda tangan '.$direkturAltName) ?></td></tr>
              <?php
                $direkturName = trim((string)($direktur['nama'] ?? ''));
                $direkturNameClass = $direkturName !== '' ? 'signature-name' : 'signature-name';
              ?>
              <tr><td><span class="<?= doc_escape($direkturNameClass) ?>"><?= doc_escape($direkturName) ?></span></td></tr>
              <?php if(!empty($direktur['nip'])){ ?><tr><td class="signature-nip">NIP. <?= doc_escape($direktur['nip']) ?></td></tr><?php } ?>
            </table>
          </td>
          <td>
            <table class="signature-inner" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <span class="signature-label">
                    <?= doc_escape($ketua['lokasi'] ?? '') ?><?php if(!empty($ketua['lokasi']) && !empty($ketua['waktu'])){ ?>, <?php } ?>
                    <?php if(!empty($ketua['waktu'])){ ?><span class="highlight"><?= doc_escape($ketua['waktu']) ?></span><?php } ?>
                  </span>
                </td>
              </tr>
              <?php if(!empty($ketua['jabatan'])){ ?><tr><td><span class="signature-label"><?= doc_escape($ketua['jabatan']) ?></span></td></tr><?php } ?>
              <?php
                $ketuaAltName = trim((string)($ketua['nama'] ?? ''));
                if ($ketuaAltName === '') {
                  $ketuaAltName = 'Ketua Tim';
                }
              ?>
              <tr><td><?= doc_signature_html($ketuaSignature, 'Tanda tangan '.$ketuaAltName) ?></td></tr>
              <?php
                $ketuaName = trim((string)($ketua['nama'] ?? ''));
                $ketuaNameClass = $ketuaName !== '' ? 'signature-name highlighted' : 'signature-name';
              ?>
              <tr><td><span class="<?= doc_escape($ketuaNameClass) ?>"><?= doc_escape($ketuaName) ?></span></td></tr>
              <?php if(!empty($ketua['nip'])){ ?><tr><td class="signature-nip">NIP. <?= doc_escape($ketua['nip']) ?></td></tr><?php } ?>
            </table>
          </td>
        </tr>
      </table>
      <?php if ($anggotaRenderedCount > 0){ ?>
      <div class="section-title" style="margin-top:6pt;">Anggota Tim</div>
      <?php
        $anggotaCols = $anggotaRenderedCount > 1 ? 2 : 1;
        $anggotaRows = array_chunk($anggotaRendered, $anggotaCols);
      ?>
      <table class="signature-table anggota-grid" border="0" cellspacing="0" cellpadding="0">
        <?php foreach ($anggotaRows as $row){ ?>
        <tr>
          <?php for ($col = 0; $col < $anggotaCols; $col++){
            $rowData = $row[$col] ?? null;
          ?>
            <td>
              <?php if ($rowData){ ?>
                <table class="signature-inner" border="0" cellspacing="0" cellpadding="0">
                  <?php
                    $aLabel = $rowData['label'];
                    $aNama = $rowData['nama'];
                    $aNip = $rowData['nip'];
                    $signatureValue = $rowData['signature'];
                    $anggotaAlt = $aNama !== '' ? $aNama : ($aLabel !== '' ? $aLabel : 'Anggota');
                    $anggotaNameClass = $aNama !== '' ? 'signature-name highlighted' : 'signature-name';
                  ?>
                  <?php if($aLabel !== ''){ ?><tr><td><span class="signature-label"><?= doc_escape($aLabel) ?></span></td></tr><?php } ?>
                  <tr><td><?= doc_signature_html($signatureValue, 'Tanda tangan '.$anggotaAlt) ?></td></tr>
                  <tr><td><span class="<?= doc_escape($anggotaNameClass) ?>"><?= doc_escape($aNama) ?></span></td></tr>
                  <?php if($aNip !== ''){ ?><tr><td class="signature-nip">NIP. <?= doc_escape($aNip) ?></td></tr><?php } ?>
                </table>
              <?php } else { ?>
                &nbsp;
              <?php } ?>
            </td>
          <?php } ?>
        </tr>
        <?php } ?>
      </table>
      <?php } ?>
    </div>
  </div>

  <?php if(!empty($chrRows)){ ?>
  <div class="page detail-section sheet-detail"<?= $mode === 'word' ? ' style="page-break-before:always; mso-break-type:page;"' : '' ?>>
    <div class="meta-info">
      <span><strong>Kode:</strong> <?= doc_escape($revInfo['kode'] ?? '') ?></span>
      <span><strong>Unit:</strong> <?= doc_escape($revInfo['unit_nama'] ?? '') ?></span>
      <span><strong>Jenis:</strong> <?= doc_escape($revInfo['jenis_nama'] ?? '') ?></span>
    </div>
    <table class="detail-table">
      <colgroup>
        <col style="width:5%;">
        <col style="width:22%;">
        <col style="width:20%;">
        <col style="width:10%;">
        <col style="width:9%;">
        <col style="width:18%;">
        <col style="width:8%;">
        <col style="width:8%;">
      </colgroup>
      <thead>
        <tr>
          <th>No</th>
          <th>Deskripsi CHR</th>
          <th>Rekomendasi</th>
          <th>Tenggat</th>
          <th>Status TL</th>
          <th>Catatan TL</th>
          <th>Dibuat</th>
          <th>Diperbarui</th>
        </tr>
      </thead>
      <tbody>
        <?php $idx = 1; foreach ($chrRows as $row){ ?>
        <tr>
          <td><?= $idx++ ?></td>
          <td><?= doc_nl2br($row['deskripsi'] ?? '') ?></td>
          <td><?= doc_nl2br($row['rekomendasi'] ?? '') ?></td>
          <td><?= doc_format_due($row['due_date'] ?? '') ?></td>
          <td><?= doc_escape($row['status_tl'] ?? '') ?></td>
          <td><?= doc_nl2br($row['tl_catatan'] ?? '') ?></td>
          <td><?= doc_format_datetime($row['created_at'] ?? '') ?></td>
          <td><?= doc_format_datetime($row['updated_at'] ?? '') ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <?php } ?>
<?php if ($mode === 'word'): ?>
  </div>
<?php endif; ?>
</body>
</html>
<?php
  return ob_get_clean();
}
