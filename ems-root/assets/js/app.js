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

  // ── Mark active sidebar link ──────────────────────────────
  const currentPath = window.location.pathname;
  document.querySelectorAll('#sidebar .nav-link[href]').forEach(link => {
    const href = link.getAttribute('href');
    if (href && currentPath.endsWith(href.replace(/^.*\//, ''))) {
      link.classList.add('active');
      // Open parent collapse
      const parent = link.closest('.collapse');
      if (parent) {
        parent.classList.add('show');
        const trigger = document.querySelector(`[data-bs-target="#${parent.id}"]`);
        trigger?.setAttribute('aria-expanded', 'true');
      }
    }
  });

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

  // ── Confirm delete buttons ────────────────────────────────
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
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
