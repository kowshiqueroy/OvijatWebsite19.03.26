/**
 * EMS Utilities v1.1
 * Global: error popup · duplicate prevention · CSV export · copy-as-text · print toolbar
 */
(function () {
  'use strict';

  const EMS = window.EMS = window.EMS || {};

  // ─── Meta helpers ────────────────────────────────────────────────────────
  const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

  // ─── Error Popup ─────────────────────────────────────────────────────────
  EMS.showError = function (message, technical, autoClose) {
    document.getElementById('ems-error-popup')?.remove();
    const url = location.href;
    const ts  = new Date().toLocaleString();
    const devText = ['URL: ' + url, 'Time: ' + ts, 'Error: ' + message, technical ? 'Details: ' + technical : ''].filter(Boolean).join('\n');

    const overlay = document.createElement('div');
    overlay.id = 'ems-error-popup';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;animation:ems-fadein .18s ease';
    overlay.innerHTML = `
<div style="background:#fff;border-radius:12px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden;font-family:'Segoe UI',sans-serif;">
  <div style="background:#dc2626;color:#fff;padding:12px 18px;display:flex;align-items:center;gap:10px;">
    <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
    <strong style="flex:1">${technical ? 'Error' : 'Notice'}</strong>
    <button onclick="document.getElementById('ems-error-popup').remove()" style="background:none;border:none;color:#fff;font-size:1.4rem;cursor:pointer;line-height:1;padding:0 2px">&times;</button>
  </div>
  <div style="padding:18px 20px;">
    <p style="margin:0 0 14px;color:#1e293b;font-size:.9rem;line-height:1.5">${message.replace(/</g,'&lt;').replace(/\n/g,'<br>')}</p>
    ${technical ? `
    <details style="margin-bottom:12px">
      <summary style="cursor:pointer;color:#64748b;font-size:.82rem;user-select:none">⚙ Technical Details (click to expand)</summary>
      <pre style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;font-size:.72rem;overflow:auto;max-height:140px;margin-top:8px;white-space:pre-wrap;color:#334155">${devText.replace(/</g,'&lt;')}</pre>
    </details>
    <button id="ems-copy-dev-btn" onclick="EMS._copyDev('${btoa(unescape(encodeURIComponent(devText)))}')"
      style="background:#1e293b;color:#fff;border:none;border-radius:6px;padding:8px 14px;font-size:.82rem;cursor:pointer;width:100%;display:flex;align-items:center;justify-content:center;gap:6px">
      <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/></svg>
      Copy Error Details for Developer
    </button>
    <p id="ems-copy-ok" style="display:none;text-align:center;color:#10b981;font-size:.78rem;margin:6px 0 0">✅ Copied to clipboard!</p>` : ''}
  </div>
</div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
    if (autoClose > 0) setTimeout(() => overlay.remove(), autoClose);
  };

  EMS._copyDev = function (b64) {
    try {
      const text = decodeURIComponent(escape(atob(b64)));
      navigator.clipboard?.writeText(text).catch(() => { EMS._clipFallback(text); });
      const ok = document.getElementById('ems-copy-ok');
      if (ok) { ok.style.display = 'block'; setTimeout(() => ok.style.display = 'none', 2500); }
    } catch (e) { alert('Copy failed. Please copy the details manually.'); }
  };
  EMS._clipFallback = function (text) {
    const ta = Object.assign(document.createElement('textarea'), { value: text, style: 'position:fixed;opacity:0' });
    document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
  };

  // ─── Toast ───────────────────────────────────────────────────────────────
  EMS.toast = function (msg, type) {
    const bg = { success:'#10b981', error:'#ef4444', warning:'#f59e0b', info:'#3b82f6' }[type] || '#3b82f6';
    const t = Object.assign(document.createElement('div'), {
      textContent: msg,
      style: `position:fixed;bottom:20px;right:20px;z-index:99998;background:${bg};color:#fff;padding:10px 18px;border-radius:8px;font-size:.85rem;box-shadow:0 4px 20px rgba(0,0,0,.2);transition:opacity .3s;font-family:'Segoe UI',sans-serif`
    });
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 320); }, 2400);
  };

  // ─── Duplicate Submission Prevention ─────────────────────────────────────
  EMS._guardForms = function () {
    document.querySelectorAll('form:not([data-no-protect]):not([data-guarded])').forEach(form => {
      form.dataset.guarded = '1';
      let submitting = false;
      form.addEventListener('submit', function (e) {
        if (submitting) {
          e.preventDefault();
          EMS.showError('This form is already being submitted. Please wait.', null, 3500);
          return;
        }
        submitting = true;
        // Disable submit buttons with spinner
        form.querySelectorAll('[type=submit]:not([data-keep-enabled])').forEach(btn => {
          btn._ems_orig = btn.innerHTML;
          btn.disabled  = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing…';
        });
        // Safety reset after 12 s (in case redirect fails)
        setTimeout(() => {
          submitting = false;
          form.querySelectorAll('[type=submit][disabled]').forEach(btn => {
            btn.disabled = false;
            if (btn._ems_orig) { btn.innerHTML = btn._ems_orig; delete btn._ems_orig; }
          });
        }, 12000);
      });
    });
    // Re-enable buttons on back-navigation
    window.addEventListener('pageshow', (e) => {
      if (e.persisted) {
        document.querySelectorAll('[type=submit][disabled]').forEach(btn => {
          btn.disabled = false;
          if (btn._ems_orig) { btn.innerHTML = btn._ems_orig; delete btn._ems_orig; }
        });
      }
    });
  };

  // ─── CSV Export ──────────────────────────────────────────────────────────
  EMS.exportCSV = function (tableId, filename) {
    const tbl = document.getElementById(tableId);
    if (!tbl) { EMS.showError('Table not found for CSV export.'); return; }
    const rows = [...tbl.querySelectorAll('tr')].map(tr =>
      [...tr.querySelectorAll('th,td')].map(td => {
        let t = td.innerText.replace(/\s+/g,' ').trim();
        return (t.includes(',') || t.includes('"') || t.includes('\n')) ? `"${t.replace(/"/g,'""')}"` : t;
      }).join(',')
    );
    const csv = '﻿' + rows.join('\r\n');
    const fn  = (filename || document.title || 'export').replace(/[^a-zA-Z0-9_-]/g,'_')
              + '_' + new Date().toISOString().slice(0,10) + '.csv';
    const a = Object.assign(document.createElement('a'), {
      href: URL.createObjectURL(new Blob([csv], { type:'text/csv;charset=utf-8;' })),
      download: fn
    });
    a.click(); URL.revokeObjectURL(a.href);
    EMS.toast('CSV exported: ' + fn, 'success');
  };

  // ─── Copy as Tab-Separated Text ──────────────────────────────────────────
  EMS.copyTableText = function (tableId) {
    const tbl = document.getElementById(tableId);
    if (!tbl) { EMS.showError('Table not found.'); return; }
    const rows = [...tbl.querySelectorAll('tr')].map(tr =>
      [...tr.querySelectorAll('th,td')].map(td => td.innerText.replace(/\s+/g,' ').trim()).join('\t')
    );
    const text = rows.join('\n');
    (navigator.clipboard?.writeText(text) ?? Promise.reject()).then(() => {
      EMS.toast('Table copied! Paste into Excel or Google Sheets.', 'success');
    }).catch(() => { EMS._clipFallback(text); EMS.toast('Copied!', 'success'); });
  };

  // ─── Print (opens clean A4 window, compact, minimal colour) ────────────
  EMS.printTable = function (tableId, opts) {
    opts = opts || {};
    const tbl    = document.getElementById(tableId);
    const school = meta('school-name') || document.title;
    const title  = opts.title || document.title;
    const ts     = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });

    // Collect active filter values from the nearest form
    const filterEl = tbl ? tbl.closest('.card')?.parentElement?.querySelector('form') : null;
    const filters  = filterEl
      ? [...filterEl.querySelectorAll('select, input[type=date], input[type=text]')].map(el => {
          const lbl = filterEl.querySelector(`label[for="${el.id}"]`)?.textContent?.trim()
                   || el.closest('[class*=col-]')?.querySelector('label')?.textContent?.trim();
          const val = el.tagName === 'SELECT' ? el.options[el.selectedIndex]?.text : el.value;
          return (lbl && val && val !== '—' && !val.includes('All')) ? `<b>${lbl.replace(':','')}:</b> ${val}` : null;
        }).filter(Boolean).join(' &nbsp;&bull;&nbsp; ')
      : '';

    const isWide = tbl ? tbl.offsetWidth > 700 : false;
    const orient = isWide ? 'A4 landscape' : 'A4 portrait';
    const tableHTML = tbl ? tbl.outerHTML : '';

    const w = window.open('', '_blank', 'width=900,height=680');
    w.document.write(`<!DOCTYPE html><html><head>
<meta charset="UTF-8">
<title>${title}</title>
<style>
/* A4 compact print — white bg, minimal colour, tight spacing */
*    { box-sizing:border-box; margin:0; padding:0; }
@page{ margin:8mm 10mm; size:${orient}; }
body { font-family:'Segoe UI',Arial,sans-serif; font-size:9.5pt; color:#000; background:#fff; }

/* School header */
.ph   { text-align:center; border-bottom:2pt double #000; padding-bottom:5pt; margin-bottom:8pt; }
.ph h1{ font-size:13pt; text-transform:uppercase; letter-spacing:.04em; font-weight:800; margin-bottom:2pt; }
.ph p { font-size:8pt; color:#444; }
.ph .doc-title { font-size:11pt; font-weight:700; margin-top:4pt; }

/* Filter bar — condensed italic */
.filters{ font-size:8pt; color:#555; background:#f0f0f0; padding:3pt 6pt;
          border-radius:3pt; margin-bottom:6pt; font-style:italic; }

/* Tables */
table  { width:100%; border-collapse:collapse; font-size:8.5pt; table-layout:auto; }
th     { background:#e0e0e0; color:#000; border:0.75pt solid #888;
         padding:3pt 5pt; font-size:8pt; font-weight:700; text-align:left;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
td     { border:0.5pt solid #ccc; padding:2.5pt 5pt; vertical-align:top; }
tr:nth-child(even) td{ background:#f7f7f7;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
tfoot td, tr.table-dark td{ background:#222!important; color:#fff!important;
         font-weight:700; border-color:#555;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
tr     { break-inside:avoid; }

/* Strip Bootstrap colours from badges */
.badge, [class*=badge-status]{ background:#fff!important; color:#000!important;
         border:0.75pt solid #666; font-size:7.5pt; padding:1pt 3pt; border-radius:2pt; }

/* Links */
a { color:#000; text-decoration:none; }

/* Utility */
.text-success,.text-danger,.text-warning,.text-primary,.text-muted,.text-info
       { color:#000!important; }
.fw-bold,.fw-600,.fw-700,.fw-800{ font-weight:700!important; }
.small, small{ font-size:7.5pt; }

/* Print controls */
.print-btn-bar { background:#e8e8e8; padding:5pt 8pt; margin-bottom:10pt;
                 border-radius:4pt; display:flex; gap:6pt; }
.print-btn-bar button { padding:4pt 10pt; font-size:8.5pt; cursor:pointer;
                        border:1pt solid #999; border-radius:3pt; background:#fff; }
@media print { .print-btn-bar { display:none!important; } }
</style></head><body>

<div class="print-btn-bar">
  <button onclick="window.print()">🖨 Print</button>
  <button onclick="window.close()">✕ Close</button>
  <span style="font-size:7.5pt;color:#666;margin:auto 0;">A4 ${isWide ? 'Landscape' : 'Portrait'} &nbsp;|&nbsp; Printed: ${ts}</span>
</div>

<div class="ph">
  <h1>${school}</h1>
  <div class="doc-title">${title}</div>
  <p>Printed: ${ts}</p>
</div>

${filters ? `<div class="filters">${filters}</div>` : ''}

${tableHTML}

<script>window.onload=()=>{window.print();window.onafterprint=()=>window.close();}<\/script>
</body></html>`);
    w.document.close();
  };

  // ─── Auto-inject Export Toolbar on all card tables ────────────────────────
  EMS.initExportToolbars = function () {
    document.querySelectorAll('.card .table-responsive:not([data-export-done])').forEach((wrapper, i) => {
      const tbl = wrapper.querySelector('table');
      if (!tbl) return;
      wrapper.dataset.exportDone = '1';
      const tid = tbl.id || ('ems-t-' + (++EMS._tblIdx));
      tbl.id = tid;

      const btns = `
<div class="ems-export-bar d-flex gap-1 no-print" data-export-bar>
  <button class="btn btn-xs btn-outline-secondary" onclick="EMS.copyTableText('${tid}')" title="Copy as text (for Excel)"><i class="bi bi-clipboard-data"></i></button>
  <button class="btn btn-xs btn-outline-success"   onclick="EMS.exportCSV('${tid}')"    title="Download CSV"><i class="bi bi-filetype-csv"></i></button>
  <button class="btn btn-xs btn-outline-primary"   onclick="EMS.printTable('${tid}')"   title="Print clean"><i class="bi bi-printer"></i></button>
</div>`;

      const hdr = wrapper.closest('.card')?.querySelector('.card-header');
      if (hdr) {
        hdr.style.cssText += ';display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap';
        hdr.insertAdjacentHTML('beforeend', btns);
      } else {
        wrapper.insertAdjacentHTML('beforebegin',
          `<div class="px-3 pt-2 d-flex justify-content-end no-print">${btns}</div>`);
      }
    });
  };
  EMS._tblIdx = 0;

  // ─── Global fetch error interceptor ──────────────────────────────────────
  const _fetch0 = window.fetch;
  window.fetch = async function (...args) {
    try {
      const res = await _fetch0(...args);
      if (res.status >= 500) {
        EMS.showError(`Server error ${res.status}. Please try again or contact support.`,
          `URL: ${typeof args[0]==='string'?args[0]:args[0].url}\nStatus: ${res.status} ${res.statusText}`);
      }
      return res;
    } catch (err) {
      EMS.showError('Network error — server unreachable.', `URL: ${typeof args[0]==='string'?args[0]:args[0].url}\n${err.message}`);
      throw err;
    }
  };

  window.addEventListener('unhandledrejection', (e) => {
    if (e.reason && !(e.reason instanceof TypeError)) { // avoid noise from benign breaks
      EMS.showError('An unexpected script error occurred.', String(e.reason?.stack ?? e.reason));
    }
  });

  // ─── DOMContentLoaded init ────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    // Style for animation
    if (!document.getElementById('ems-style')) {
      const s = document.createElement('style');
      s.id = 'ems-style';
      s.textContent = `
        @keyframes ems-fadein{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
        .btn-xs{padding:.2rem .45rem;font-size:.72rem;border-radius:.3rem}
        [data-export-bar] button{line-height:1.2}
        @media print{.no-print,[data-export-bar],.ems-export-bar{display:none!important}}
      `;
      document.head.appendChild(s);
    }

    EMS.initExportToolbars();
    EMS._guardForms();

    // Re-run toolbars when modals open (they may contain tables)
    document.addEventListener('shown.bs.modal', () => EMS.initExportToolbars());
    document.addEventListener('shown.bs.tab',   () => EMS.initExportToolbars());
  });

})();
