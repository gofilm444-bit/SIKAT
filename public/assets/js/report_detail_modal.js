(function () {
  'use strict';

  const modal = document.getElementById('reportDetailModal');
  if (!modal) return;

  const dialog = modal.querySelector('[data-report-modal-dialog]');
  const loading = modal.querySelector('[data-report-modal-loading]');
  const errorBox = modal.querySelector('[data-report-modal-error]');
  const content = modal.querySelector('[data-report-modal-content]');
  const body = modal.querySelector('[data-report-modal-body]');
  const endpoint = window.SIKAT_REPORT_DETAIL_ENDPOINT || 'report_detail.php';
  let lastTrigger = null;
  let activeController = null;

  const text = (selector, value) => {
    const el = modal.querySelector(selector);
    if (el) el.textContent = value && String(value).trim() !== '' ? String(value) : '-';
  };

  const setHidden = (el, hidden) => {
    if (el) el.hidden = hidden;
  };

  const openModal = () => {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('report-modal-open');
    window.setTimeout(() => {
      if (dialog) dialog.focus();
    }, 20);
  };

  const closeModal = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('report-modal-open');
    if (activeController) {
      activeController.abort();
      activeController = null;
    }
    if (lastTrigger && typeof lastTrigger.focus === 'function') {
      lastTrigger.focus();
    }
  };

  const setLoading = () => {
    text('#reportDetailModalTitle', 'Memuat laporan...');
    text('#reportDetailCode', '-');
    const status = modal.querySelector('#reportDetailStatus');
    if (status) {
      status.className = 'badge bg-secondary';
      status.textContent = '-';
    }
    setHidden(loading, false);
    setHidden(errorBox, true);
    setHidden(content, true);
    if (errorBox) errorBox.textContent = '';
  };

  const showError = (message) => {
    setHidden(loading, true);
    setHidden(content, true);
    if (errorBox) {
      errorBox.textContent = message || 'Detail laporan tidak dapat dimuat.';
      setHidden(errorBox, false);
    }
  };

  const makeUrl = (trigger) => {
    const url = new URL(endpoint, window.location.origin);
    const id = trigger.getAttribute('data-report-id') || '';
    const code = trigger.getAttribute('data-report-code') || '';
    if (id) {
      url.searchParams.set('id', id);
    }
    if (code) {
      url.searchParams.set('kode', code);
    }
    return url.toString();
  };

  const attachmentKind = (item) => {
    const mime = String(item && item.mime ? item.mime : '').toLowerCase();
    const name = String(item && item.name ? item.name : '').toLowerCase();
    if (mime.startsWith('image/') || /\.(jpe?g|png|webp|gif)$/.test(name)) return 'image';
    if (mime === 'application/pdf' || /\.pdf$/.test(name)) return 'pdf';
    return 'other';
  };

  const renderAttachmentPreview = (item) => {
    const preview = modal.querySelector('[data-report-attachment-preview]');
    if (!preview) return;
    preview.textContent = '';
    preview.hidden = false;

    const title = document.createElement('div');
    title.className = 'report-attachment-preview__title';
    title.textContent = item.name || 'Lampiran';
    preview.appendChild(title);

    const media = document.createElement('div');
    media.className = 'report-attachment-preview__media';
    const kind = attachmentKind(item);
    if (kind === 'image' && item.view_url) {
      const img = document.createElement('img');
      img.src = item.view_url;
      img.alt = item.name || 'Pratinjau lampiran';
      img.loading = 'lazy';
      media.appendChild(img);
    } else if (kind === 'pdf' && item.view_url) {
      const frame = document.createElement('iframe');
      frame.src = item.view_url;
      frame.title = item.name || 'Pratinjau PDF';
      frame.loading = 'lazy';
      media.appendChild(frame);
    } else {
      const empty = document.createElement('div');
      empty.className = 'report-attachment-preview__empty';
      empty.textContent = 'Pratinjau tidak tersedia untuk tipe file ini.';
      media.appendChild(empty);
    }
    preview.appendChild(media);

    const actions = document.createElement('div');
    actions.className = 'report-attachment__actions';
    if (item.view_url) {
      const open = document.createElement('a');
      open.className = 'btn btn-sm btn-outline-primary';
      open.href = item.view_url;
      open.target = '_blank';
      open.rel = 'noopener';
      open.textContent = 'Buka';
      actions.appendChild(open);
    }
    if (item.download_url) {
      const download = document.createElement('a');
      download.className = 'btn btn-sm btn-outline-success';
      download.href = item.download_url;
      download.textContent = 'Unduh';
      actions.appendChild(download);
    }
    preview.appendChild(actions);
  };

  const renderAttachments = (items) => {
    const wrap = modal.querySelector('[data-report-attachments]');
    const preview = modal.querySelector('[data-report-attachment-preview]');
    if (!wrap) return;
    wrap.textContent = '';
    if (preview) {
      preview.textContent = '';
      preview.hidden = true;
    }
    if (!Array.isArray(items) || items.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'report-detail-empty';
      empty.textContent = 'Tidak ada lampiran.';
      wrap.appendChild(empty);
      return;
    }
    const list = document.createElement('div');
    list.className = 'report-attachments';
    items.forEach((item) => {
      const card = document.createElement('div');
      card.className = 'report-attachment';

      const name = document.createElement('div');
      name.className = 'report-attachment__name';
      name.textContent = item.name || 'Lampiran';
      card.appendChild(name);

      const meta = document.createElement('div');
      meta.className = 'report-attachment__meta';
      meta.textContent = [item.mime, item.size].filter(Boolean).join(' - ') || '-';
      card.appendChild(meta);

      const actions = document.createElement('div');
      actions.className = 'report-attachment__actions';
      if (item.view_url) {
        const view = document.createElement('button');
        view.type = 'button';
        view.className = 'btn btn-sm btn-outline-primary';
        view.textContent = 'Preview';
        view.addEventListener('click', () => {
          list.querySelectorAll('.report-attachment.is-active').forEach((el) => el.classList.remove('is-active'));
          card.classList.add('is-active');
          renderAttachmentPreview(item);
          const previewPanel = modal.querySelector('[data-report-attachment-preview]');
          if (previewPanel && body) {
            previewPanel.scrollIntoView({ block: 'start', behavior: 'smooth' });
          }
        });
        actions.appendChild(view);
      }
      if (item.download_url) {
        const download = document.createElement('a');
        download.className = 'btn btn-sm btn-outline-success';
        download.href = item.download_url;
        download.textContent = 'Unduh';
        actions.appendChild(download);
      }
      card.appendChild(actions);
      list.appendChild(card);
    });
    wrap.appendChild(list);
    if (items.length === 1 && items[0] && items[0].view_url) {
      const firstCard = list.querySelector('.report-attachment');
      if (firstCard) firstCard.classList.add('is-active');
      renderAttachmentPreview(items[0]);
    }
  };

  const renderHistory = (items) => {
    const wrap = modal.querySelector('[data-report-history]');
    if (!wrap) return;
    wrap.textContent = '';
    if (!Array.isArray(items) || items.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'report-detail-empty';
      empty.textContent = 'Belum ada riwayat proses untuk laporan ini.';
      wrap.appendChild(empty);
      return;
    }
    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'report-timeline__item';

      const title = document.createElement('div');
      title.className = 'report-timeline__title';
      title.textContent = `${item.from || 'Pengaduan Masuk'} -> ${item.to || 'Pengaduan Masuk'}`;
      row.appendChild(title);

      if (item.note) {
        const note = document.createElement('div');
        note.className = 'report-timeline__note';
        note.textContent = item.note;
        row.appendChild(note);
      }

      const meta = document.createElement('div');
      meta.className = 'report-timeline__meta';
      meta.textContent = [item.created_at, item.user ? `oleh ${item.user}` : ''].filter(Boolean).join(' ');
      row.appendChild(meta);

      wrap.appendChild(row);
    });
  };

  const renderReport = (payload, focus) => {
    const report = payload && payload.report ? payload.report : {};
    const status = report.status || {};
    const tl = report.tl || {};

    text('#reportDetailModalTitle', 'Detail Laporan');
    text('#reportDetailCode', report.kode || '-');
    const statusEl = modal.querySelector('#reportDetailStatus');
    if (statusEl) {
      statusEl.className = `badge ${status.badge || 'bg-secondary'}`;
      statusEl.textContent = status.label || status.raw || '-';
    }
    text('[data-report-field="kode"]', report.kode);
    text('[data-report-field="kategori"]', report.kategori);
    text('[data-report-field="dibuat"]', report.created_at);
    text('[data-report-field="status"]', status.description ? `${status.label} - ${status.description}` : status.label);
    text('[data-report-field="tl_status"]', tl.status);
    text('[data-report-field="pelapor"]', report.pelapor);
    text('[data-report-field="isi"]', report.isi || 'Tidak ada isi laporan.');

    const titleEl = modal.querySelector('[data-report-field="judul"]');
    if (titleEl) {
      titleEl.textContent = report.judul || '';
      titleEl.hidden = !report.judul;
    }

    const tlPanel = modal.querySelector('[data-report-tl-panel]');
    const hasTl = Boolean((tl.status && tl.status !== 'Belum TL') || tl.note || tl.updated_by || (tl.updated_at && tl.updated_at !== '-'));
    setHidden(tlPanel, !hasTl);
    text('[data-report-field="tl_latest_status"]', tl.status);
    text('[data-report-field="tl_latest_note"]', tl.note || '-');
    text('[data-report-field="tl_latest_updated"]', tl.updated_at || '-');
    text('[data-report-field="tl_latest_user"]', tl.updated_by || '-');

    renderAttachments(report.lampiran || []);
    renderHistory(report.riwayat || []);

    setHidden(loading, true);
    setHidden(errorBox, true);
    setHidden(content, false);

    if (focus === 'history' || focus === 'content' || focus === 'attachments') {
      window.setTimeout(() => {
        const target = document.getElementById(focus === 'history' ? 'reportDetailHistorySection' : (focus === 'attachments' ? 'reportDetailAttachmentsSection' : 'reportDetailContentSection'));
        if (target && body) {
          target.scrollIntoView({ block: 'start', behavior: 'smooth' });
          target.classList.add('report-detail-panel--highlight');
          window.setTimeout(() => target.classList.remove('report-detail-panel--highlight'), 2200);
        }
      }, 80);
    }
  };

  const loadReport = async (trigger) => {
    lastTrigger = trigger;
    openModal();
    setLoading();
    const focus = trigger.getAttribute('data-report-focus') || 'detail';

    if (activeController) activeController.abort();
    activeController = new AbortController();

    try {
      const response = await fetch(makeUrl(trigger), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: activeController.signal
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        showError(payload.message || 'Detail laporan tidak dapat dimuat.');
        return;
      }
      renderReport(payload, focus);
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      showError('Terjadi kendala saat memuat detail laporan.');
    }
  };

  document.addEventListener('click', function (event) {
    const close = event.target.closest('[data-report-modal-close]');
    if (close) {
      event.preventDefault();
      closeModal();
      return;
    }

    const trigger = event.target.closest('.js-report-detail, .js-report-history');
    if (!trigger) return;
    event.preventDefault();
    loadReport(trigger);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
  });
})();
