/* EMS — Global JavaScript */

(() => {
  'use strict';

  // ── Sidebar toggle (mobile) ───────────────────────────────
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebar-overlay');
  const togglers = document.querySelectorAll('.sidebar-toggle');

  function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.add('show'); }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('show'); }

  togglers.forEach(t => t.addEventListener('click', () => {
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
  }));
  overlay?.addEventListener('click', closeSidebar);

  // ── Sidebar accordion: close all, then open only the active one ─
  // PHP already renders the correct show/active state server-side.
  // This block just ensures Bootstrap's collapse instances are in sync
  // and that clicking a parent link closes all siblings (accordion).
  (function initSidebarAccordion() {
    const accordion = document.getElementById('sidebarAccordion');
    if (!accordion) return;

    // On page load: collapse every submenu that PHP didn't mark as .show
    // (Bootstrap sometimes keeps them open from a previous navigation).
    accordion.querySelectorAll('.collapse.sidebar-submenu').forEach(el => {
      if (!el.classList.contains('show')) {
        // Ensure Bootstrap collapse instance is collapsed
        const bsc = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        bsc.hide();
      }
    });

    // Clicking a parent nav-link → close every OTHER submenu first
    accordion.querySelectorAll('[data-bs-toggle="collapse"]').forEach(trigger => {
      trigger.addEventListener('click', () => {
        const targetId = trigger.getAttribute('data-bs-target');
        accordion.querySelectorAll('.collapse.sidebar-submenu').forEach(el => {
          if ('#' + el.id !== targetId && el.classList.contains('show')) {
            bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
          }
        });
      });
    });
  })();

  // ── Auto-dismiss alerts ───────────────────────────────────
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    const delay = parseInt(alert.dataset.autoDismiss || '4000', 10);
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      bsAlert?.close();
    }, delay);
  });

  // ── CSRF token on AJAX ────────────────────────────────────
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (csrfMeta) {
    const originalFetch = window.fetch;
    window.fetch = (url, opts = {}) => {
      opts.headers = opts.headers || {};
      opts.headers['X-CSRF-Token'] = csrfMeta.content;
      return originalFetch(url, opts);
    };
  }

  // ── Soft-delete warning modal (data-soft-delete) ────────────
  // Usage: <button data-soft-delete="Item Name" data-soft-delete-warn="3 related records will be affected"
  //                data-form-id="myFormId">Delete</button>
  // If data-soft-delete-warn is present, show modal first; else submit immediately.
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-soft-delete]');
    if (!btn) return;
    e.preventDefault();
    const name    = btn.dataset.softDelete    || 'this record';
    const warn    = btn.dataset.softDeleteWarn || '';
    const formId  = btn.dataset.formId;
    const form    = formId ? document.getElementById(formId) : btn.closest('form');

    // If no warning context, just confirm with a simple dialog
    if (!warn) {
      if (confirm(`Soft delete "${name}"?\n\nThis will hide it from all views but can be restored from the Deleted Items page.`)) {
        form?.submit();
      }
      return;
    }

    // Show rich modal
    let modal = document.getElementById('softDeleteModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'softDeleteModal';
      modal.className = 'modal fade';
      modal.setAttribute('tabindex', '-1');
      modal.innerHTML = `
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title"><i class="bi bi-trash3 me-2"></i>Confirm Delete</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="mb-2">You are about to soft-delete: <strong id="sdModalName"></strong></p>
              <div class="alert alert-warning mb-3" id="sdModalWarn"></div>
              <p class="small text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Soft delete hides this record from all views. It can be restored from
                <strong>Setup → Deleted Items</strong>.
              </p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-danger" id="sdModalConfirm">
                <i class="bi bi-trash3 me-1"></i>Delete
              </button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modal);
    }

    document.getElementById('sdModalName').textContent = name;
    document.getElementById('sdModalWarn').innerHTML   = `<i class="bi bi-exclamation-triangle me-2"></i>${warn}`;

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    document.getElementById('sdModalConfirm').onclick = () => {
      bsModal.hide();
      form?.submit();
    };
  });

  // ── Simple confirm for non-soft-delete buttons ──────────────
  // We always preventDefault first, then manually submit with the button's name/value
  // preserved so PHP receives the correct action value.
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn || btn.dataset.softDelete) return;
    e.preventDefault();
    if (!confirm(btn.dataset.confirm || 'Are you sure?')) return;

    const form = btn.form || btn.closest('form');
    if (!form) return;

    // Preserve button name/value (lost when using form.submit())
    if (btn.name) {
      const h = document.createElement('input');
      h.type = 'hidden'; h.name = btn.name; h.value = btn.value;
      form.appendChild(h);
    }
    form.submit();
  });

  // ── Search table (client-side filter) ────────────────────
  const tableSearch = document.getElementById('table-search');
  if (tableSearch) {
    const target = document.getElementById(tableSearch.dataset.target || 'data-table');
    tableSearch.addEventListener('input', () => {
      const q = tableSearch.value.toLowerCase();
      target?.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  // ── Select2-style: searchable native selects ─────────────
  // (lightweight native implementation — replace with Select2 if needed)
  document.querySelectorAll('select[data-searchable]').forEach(sel => {
    sel.setAttribute('size', '1');
  });

  // ── Number-only inputs ────────────────────────────────────
  document.querySelectorAll('input[data-numeric]').forEach(inp => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/[^0-9.]/g, '');
    });
  });

  // ── Fee calculator (collect.php) ─────────────────────────
  function recalcFee() {
    const due    = parseFloat(document.getElementById('amount-due')?.dataset.due || 0);
    const waiver = parseFloat(document.getElementById('waiver-amount')?.value || 0);
    const paid   = parseFloat(document.getElementById('amount-paid')?.value || 0);
    const net    = Math.max(0, due - waiver);
    const balance = Math.max(0, net - paid);
    const netEl = document.getElementById('net-due');
    const balEl = document.getElementById('balance-due');
    if (netEl) netEl.textContent = net.toFixed(2);
    if (balEl) balEl.textContent = balance.toFixed(2);
  }
  document.getElementById('amount-paid')?.addEventListener('input', recalcFee);
  document.getElementById('waiver-amount')?.addEventListener('input', recalcFee);

  // ── Date range picker (attendance / reports) ─────────────
  // Basic: just ensure from <= to
  const dateFrom = document.querySelector('input[name="from_date"]');
  const dateTo   = document.querySelector('input[name="to_date"]');
  if (dateFrom && dateTo) {
    dateTo.addEventListener('change', () => {
      if (dateFrom.value && dateTo.value < dateFrom.value) dateTo.value = dateFrom.value;
    });
    dateFrom.addEventListener('change', () => {
      if (dateTo.value && dateFrom.value > dateTo.value) dateTo.value = dateFrom.value;
    });
  }

  // ── AJAX: load sections when class changes ────────────────
  const classSelects = document.querySelectorAll('select[data-load-sections]');
  classSelects.forEach(sel => {
    sel.addEventListener('change', () => {
      const target = document.getElementById(sel.dataset.loadSections);
      if (!target) return;
      target.innerHTML = '<option value="">Loading…</option>';
      const sessionId = document.querySelector('select[name="session_id"]')?.value || 0;
      fetch(`modules/academic/ajax.php?action=sections&class_id=${sel.value}&session_id=${sessionId}`)
        .then(r => r.json())
        .then(data => {
          target.innerHTML = '<option value="">— Select Section —</option>';
          data.forEach(s => {
            const o = document.createElement('option');
            o.value = s.id; o.textContent = s.section_name;
            target.appendChild(o);
          });
        })
        .catch(() => { target.innerHTML = '<option value="">Error loading</option>'; });
    });
  });

  // ── Dynamic roll number counter ───────────────────────────
  const rollCountEl = document.getElementById('roll-count-display');
  if (rollCountEl) {
    const sectionSel = document.querySelector('select[name="section_id"]');
    const sessionSel = document.querySelector('select[name="session_id"]');
    const check = () => {
      const cls = document.querySelector('select[name="class_id"]')?.value;
      const sec = sectionSel?.value;
      const ses = sessionSel?.value;
      if (!cls || !sec || !ses) return;
      fetch(`modules/students/ajax.php?action=roll_count&class_id=${cls}&section_id=${sec}&session_id=${ses}`)
        .then(r => r.json())
        .then(d => { rollCountEl.textContent = 'Next roll: ' + (d.next_roll || 1); });
    };
    sectionSel?.addEventListener('change', check);
    sessionSel?.addEventListener('change', check);
  }

  // ── Print page helper ─────────────────────────────────────
  document.querySelectorAll('[data-action="print"]').forEach(btn => {
    btn.addEventListener('click', () => window.print());
  });

  // ── Tooltip init ──────────────────────────────────────────
  document.querySelectorAll('[data-bs-toggle="tooltip"]')
    .forEach(el => new bootstrap.Tooltip(el, { trigger: 'hover' }));

})();
