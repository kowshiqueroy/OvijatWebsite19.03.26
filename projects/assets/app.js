/* SohojWeb Projects — app.js */
// Restore sidebar collapsed state before first paint
if (localStorage.getItem('swSidebarCollapsed') === '1') {
  document.body.classList.add('sidebar-collapsed');
}

(function () {
  'use strict';

  /* ── Dark mode toggle ─────────────────────────────────── */
  const themeToggle = document.getElementById('themeToggle');
  themeToggle?.addEventListener('click', () => {
    const html    = document.documentElement;
    const isDark  = html.getAttribute('data-theme') === 'dark';
    const next    = isDark ? '' : 'dark';
    html.setAttribute('data-theme', next);
    if (next) localStorage.setItem('swTheme', next);
    else localStorage.removeItem('swTheme');
  });

  /* ── Sidebar (mobile overlay / desktop collapse) ──────── */
  const sidebar      = document.getElementById('sidebar');
  const overlay      = document.getElementById('sidebarOverlay');
  const toggleBtn    = document.getElementById('sidebarToggle');
  const collapseBtn  = document.getElementById('sidebarCollapseBtn');

  function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.add('open'); }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('open'); }

  toggleBtn?.addEventListener('click', () => {
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);

  // Desktop collapse
  collapseBtn?.addEventListener('click', () => {
    const collapsed = document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('swSidebarCollapsed', collapsed ? '1' : '');
  });

  /* ── Toast Notifications ──────────────────────────────── */
  const TOAST_ICONS = {
    success: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
    error:   '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };

  window.showToast = function (message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML =
      `<span class="toast-icon">${TOAST_ICONS[type] ?? TOAST_ICONS.info}</span>` +
      `<span class="toast-body">${message}</span>` +
      `<button class="toast-close" aria-label="Close">&times;</button>`;
    container.appendChild(toast);
    // Trigger show animation
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
    const dismiss = () => {
      toast.classList.add('hiding');
      toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    };
    toast.querySelector('.toast-close').addEventListener('click', dismiss);
    if (duration > 0) setTimeout(dismiss, duration);
    return toast;
  };

  /* ── Mobile More sheet ────────────────────────────────── */
  const moreBtn     = document.getElementById('moreBtn');
  const moreSheet   = document.getElementById('moreSheet');
  const moreOverlay = document.getElementById('moreOverlay');

  function openMore()  { moreSheet?.classList.add('open'); moreOverlay?.classList.add('open'); }
  function closeMore() { moreSheet?.classList.remove('open'); moreOverlay?.classList.remove('open'); }

  moreBtn?.addEventListener('click', openMore);
  moreOverlay?.addEventListener('click', closeMore);

  /* ── Active bottom nav ───────────────────────────────── */
  const path = location.pathname;
  document.querySelectorAll('.bottom-nav-item[href]').forEach(a => {
    if (a.getAttribute('href') && path.endsWith(a.getAttribute('href').split('/').pop())) {
      a.classList.add('active');
    }
  });

  /* ── Modal helpers ────────────────────────────────────── */
  window.openModal = function (id) {
    document.getElementById(id)?.classList.add('open');
  };
  window.closeModal = function (id) {
    document.getElementById(id)?.classList.remove('open');
  };
  // Close modal on overlay click
  document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => {
      if (e.target === el) el.classList.remove('open');
    });
  });

  /* ── Drawer helpers ───────────────────────────────────── */
  window.openDrawer = function (id) {
    document.getElementById(id + '-overlay')?.classList.add('open');
    document.getElementById(id)?.classList.add('open');
  };
  window.closeDrawer = function (id) {
    document.getElementById(id + '-overlay')?.classList.remove('open');
    document.getElementById(id)?.classList.remove('open');
  };
  document.querySelectorAll('.drawer-overlay').forEach(el => {
    el.addEventListener('click', () => {
      const drawerId = el.id.replace('-overlay', '');
      closeDrawer(drawerId);
    });
  });

  /* ── Confirm delete ───────────────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  /* ── Mention autocomplete ─────────────────────────────── */
  window.initMentionInput = function (textarea, members) {
    if (!textarea || !members?.length) return;
    let dropdown    = null;
    let mentionStart = -1;   // index of the '@' character
    let selectedIdx  = 0;
    let blurTimer    = null;

    function removeDrop() {
      dropdown?.remove(); dropdown = null; mentionStart = -1;
    }

    function positionDrop() {
      if (!dropdown) return;
      const rect      = textarea.getBoundingClientRect();
      const spaceBelow = window.innerHeight - rect.bottom;
      const dropH      = Math.min(dropdown.scrollHeight, 200);
      const top = (spaceBelow < dropH + 8 && rect.top > dropH + 8)
        ? rect.top  - dropH - 2 + window.scrollY
        : rect.bottom + 2        + window.scrollY;
      dropdown.style.top   = top + 'px';
      dropdown.style.left  = (rect.left + window.scrollX) + 'px';
      dropdown.style.width = Math.max(200, rect.width) + 'px';
    }

    function buildDrop(filtered) {
      // Remove old dropdown WITHOUT calling removeDrop() — that resets mentionStart
      dropdown?.remove();
      dropdown = null;
      selectedIdx = 0;
      if (!filtered.length) return;
      dropdown = document.createElement('div');
      dropdown.className = 'mention-dropdown';
      dropdown.style.cssText = 'position:absolute;z-index:9999';
      filtered.forEach((m, i) => {
        const opt = document.createElement('div');
        opt.className = 'mention-option' + (i === 0 ? ' selected' : '');
        opt.dataset.username = m.username;
        opt.innerHTML =
          `<span class="avatar avatar-sm">${m.initials}</span>` +
          `<span>${m.name}</span>` +
          `<span style="font-size:.75rem;color:var(--text-muted);margin-left:6px">@${m.username}</span>`;
        opt.addEventListener('mousedown', e => {
          e.preventDefault();   // keep textarea focus so blur doesn't fire
          applyMention(m.username);
        });
        dropdown.appendChild(opt);
      });
      document.body.appendChild(dropdown);
      positionDrop();
    }

    function applyMention(username) {
      const atPos = mentionStart;
      if (atPos < 0 || !username) return;
      const v      = textarea.value;
      const before = v.slice(0, atPos);           // everything before '@'
      const after  = v.slice(textarea.selectionStart);
      textarea.value = before + '@' + username + ' ' + after;
      const newPos = atPos + username.length + 2; // '@' + username + ' '
      textarea.setSelectionRange(newPos, newPos);
      removeDrop();
      textarea.focus();
    }

    textarea.addEventListener('input', () => {
      const v   = textarea.value;
      const pos = textarea.selectionStart;
      // Walk backwards from cursor to find an unspaced '@'
      let atIdx = -1;
      for (let i = pos - 1; i >= 0; i--) {
        if (v[i] === '@') { atIdx = i; break; }
        if (/\s/.test(v[i])) break;
      }
      if (atIdx === -1) { removeDrop(); return; }
      const query = v.slice(atIdx + 1, pos);
      if (/\s/.test(query)) { removeDrop(); return; }
      mentionStart = atIdx;
      const q = query.toLowerCase();
      const filtered = members.filter(m =>
        m.username.toLowerCase().includes(q) ||
        m.name.toLowerCase().includes(q)
      );
      filtered.length ? buildDrop(filtered) : removeDrop();
    });

    // Use capture phase so this fires BEFORE other keydown listeners (e.g. chat send-on-Enter)
    textarea.addEventListener('keydown', e => {
      if (!dropdown) return;
      const opts = dropdown.querySelectorAll('.mention-option');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        e.stopImmediatePropagation();
        selectedIdx = (selectedIdx + 1) % opts.length;
        opts.forEach((o, i) => o.classList.toggle('selected', i === selectedIdx));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        e.stopImmediatePropagation();
        selectedIdx = (selectedIdx - 1 + opts.length) % opts.length;
        opts.forEach((o, i) => o.classList.toggle('selected', i === selectedIdx));
      } else if (e.key === 'Enter' || e.key === 'Tab') {
        const sel = opts[selectedIdx];
        if (sel) {
          e.preventDefault();
          e.stopImmediatePropagation(); // prevent chat's Enter-to-send from firing
          applyMention(sel.dataset.username);
        }
      } else if (e.key === 'Escape') {
        e.stopImmediatePropagation();
        removeDrop();
      }
    }, true /* capture phase */);

    textarea.addEventListener('blur', () => {
      blurTimer = setTimeout(removeDrop, 200);
    });
    window.addEventListener('scroll', positionDrop, true);
    window.addEventListener('resize', positionDrop);
  };

  /* ── Render mentions ──────────────────────────────────── */
  window.renderMentions = function (text) {
    return text
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/@(\w+)/g, '<span class="mention">@$1</span>');
  };

  /* ── AJAX helper ──────────────────────────────────────── */
  window.apiPost = async function (url, data) {
    const form = new FormData();
    Object.entries(data).forEach(([k, v]) => form.append(k, v));
    const res = await fetch(url, { method: 'POST', body: form });
    return res.json();
  };

  /* ── Tag input (comma-separated → visual tags) ────────── */
  window.initTagInput = function (inputEl, hiddenEl) {
    if (!inputEl || !hiddenEl) return;
    function renderTags() {
      const vals = hiddenEl.value ? JSON.parse(hiddenEl.value) : [];
      let wrap = inputEl.previousElementSibling;
      if (!wrap || !wrap.classList.contains('tag-wrap')) {
        wrap = document.createElement('div');
        wrap.className = 'tag-wrap';
        wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:4px;margin-bottom:6px';
        inputEl.parentElement.insertBefore(wrap, inputEl);
      }
      wrap.innerHTML = vals.map(v => `<span class="badge badge-active" style="gap:4px">${v}<button type="button" data-tag="${v}" style="background:none;border:none;color:inherit;cursor:pointer;font-size:.9rem;padding:0 0 0 4px">&times;</button></span>`).join('');
      wrap.querySelectorAll('[data-tag]').forEach(btn => {
        btn.addEventListener('click', () => {
          const updated = vals.filter(x => x !== btn.dataset.tag);
          hiddenEl.value = JSON.stringify(updated);
          renderTags();
        });
      });
    }
    renderTags();
    inputEl.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = inputEl.value.trim().replace(/,/g,'');
        if (!val) return;
        const vals = hiddenEl.value ? JSON.parse(hiddenEl.value) : [];
        if (!vals.includes(val)) { vals.push(val); hiddenEl.value = JSON.stringify(vals); }
        inputEl.value = '';
        renderTags();
      }
    });
  };

  /* ── Quick Create (Ctrl+K) ───────────────────────────── */
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      const modal = document.getElementById('quickCreateModal');
      if (!modal) return;
      // Populate project dropdown
      const sel = document.getElementById('qcProject');
      if (sel && window.MY_PROJECTS) {
        sel.innerHTML = '<option value="">— Project —</option>' +
          (window.MY_PROJECTS || []).map(p => `<option value="${p.id}">${p.name}</option>`).join('');
      }
      openModal('quickCreateModal');
      setTimeout(() => document.getElementById('qcTitle')?.focus(), 100);
    }
  });

  window.quickCreateTask = async function() {
    const title    = document.getElementById('qcTitle')?.value?.trim();
    const projId   = document.getElementById('qcProject')?.value;
    const priority = document.getElementById('qcPriority')?.value || 'medium';
    const due      = document.getElementById('qcDue')?.value || '';
    if (!title) { window.showToast('Task title required', 'error'); return; }
    if (!projId) { window.showToast('Select a project', 'error'); return; }
    const btn = document.getElementById('qcSubmitBtn');
    if (btn) btn.disabled = true;
    try {
      const form = new FormData();
      form.append('project_id', projId);
      form.append('title', title);
      form.append('priority', priority);
      form.append('due_date', due);
      form.append('csrf_token', window.CSRF_TOKEN || '');
      const res = await fetch((window.BASE_URL || '') + '/quick_task.php', {method:'POST', body:form});
      const d = await res.json();
      if (d.success) {
        window.showToast('Task created!', 'success');
        closeModal('quickCreateModal');
        document.getElementById('qcTitle').value = '';
        document.getElementById('qcDue').value = '';
      } else {
        window.showToast(d.message || 'Failed to create task', 'error');
      }
    } catch(err) {
      window.showToast('Network error', 'error');
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  // Also allow Enter in qcTitle to submit
  document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('qcTitle')?.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); window.quickCreateTask(); }
    });
  });

})();
