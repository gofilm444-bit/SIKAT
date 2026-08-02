<?php
$reportDetailEndpoint = function_exists('endpoint_url') ? endpoint_url('report_detail.php') : 'report_detail.php';
?>
<div class="report-modal" id="reportDetailModal" aria-hidden="true">
  <div class="report-modal__backdrop" data-report-modal-close></div>
  <section class="report-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="reportDetailModalTitle" tabindex="-1" data-report-modal-dialog>
    <header class="report-modal__header">
      <div>
        <p class="report-modal__eyebrow">Detail Laporan</p>
        <h2 id="reportDetailModalTitle" class="report-modal__title">Memuat laporan...</h2>
        <div class="report-modal__meta">
          <span id="reportDetailCode">-</span>
          <span id="reportDetailStatus" class="badge bg-secondary">-</span>
        </div>
      </div>
      <button type="button" class="report-modal__close" data-report-modal-close aria-label="Tutup detail laporan">&times;</button>
    </header>
    <div class="report-modal__body" data-report-modal-body>
      <div class="report-modal__loading" data-report-modal-loading>Memuat detail laporan...</div>
      <div class="report-modal__error" data-report-modal-error hidden></div>
      <div class="report-modal__content" data-report-modal-content hidden>
        <div class="report-detail-grid">
          <article class="report-detail-panel report-detail-panel--wide">
            <h3>Informasi Laporan</h3>
            <dl class="report-detail-list">
              <div><dt>Kode</dt><dd data-report-field="kode">-</dd></div>
              <div><dt>Kategori</dt><dd data-report-field="kategori">-</dd></div>
              <div><dt>Dibuat</dt><dd data-report-field="dibuat">-</dd></div>
              <div><dt>Status Saat Ini</dt><dd data-report-field="status">-</dd></div>
              <div><dt>Status Tindak Lanjut</dt><dd data-report-field="tl_status">-</dd></div>
              <div><dt>Identitas Pelapor</dt><dd data-report-field="pelapor">-</dd></div>
            </dl>
          </article>
          <article class="report-detail-panel report-detail-panel--wide" id="reportDetailContentSection">
            <h3>Isi Laporan</h3>
            <div class="report-detail-title" data-report-field="judul" hidden></div>
            <div class="report-detail-text" data-report-field="isi">-</div>
          </article>
          <article class="report-detail-panel">
            <h3>Lampiran</h3>
            <div data-report-attachments></div>
          </article>
          <article class="report-detail-panel" id="reportDetailHistorySection">
            <h3>Riwayat Proses</h3>
            <div class="report-timeline" data-report-history></div>
          </article>
          <article class="report-detail-panel report-detail-panel--wide" data-report-tl-panel hidden>
            <h3>Tindak Lanjut Terbaru</h3>
            <dl class="report-detail-list">
              <div><dt>Status TL</dt><dd data-report-field="tl_latest_status">-</dd></div>
              <div><dt>Catatan</dt><dd data-report-field="tl_latest_note">-</dd></div>
              <div><dt>Diperbarui</dt><dd data-report-field="tl_latest_updated">-</dd></div>
              <div><dt>Petugas</dt><dd data-report-field="tl_latest_user">-</dd></div>
            </dl>
          </article>
        </div>
      </div>
    </div>
    <footer class="report-modal__footer">
      <button type="button" class="btn btn-outline-secondary" data-report-modal-close>Tutup</button>
    </footer>
  </section>
</div>
<script>
window.SIKAT_REPORT_DETAIL_ENDPOINT = <?= json_encode($reportDetailEndpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
