/**
 * Ovijat Call Center — Global JS
 * Sidebar, global search, notifications, autofill,
 * quick call modal, toast, hourly sparkline.
 */

/* ── Sidebar ──────────────────────────────────────────────────── */
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    if (sb) sb.classList.toggle('open');
    if (ov) ov.classList.toggle('show');
}

/* ── Dropdowns (notif + agent menu) ──────────────────────────── */
function toggleNotif(e) {
    if (e) e.stopPropagation();
    const d = document.getElementById('notifDropdown');
    if (d) d.classList.toggle('show');
    document.getElementById('agentDropdown')?.classList.remove('show');
}
function toggleAgentMenu(e) {
    if (e) e.stopPropagation();
    const d = document.getElementById('agentDropdown');
    if (d) d.classList.toggle('show');
    document.getElementById('notifDropdown')?.classList.remove('show');
}
document.addEventListener('click', e => {
    document.getElementById('notifDropdown')?.classList.remove('show');
    document.getElementById('agentDropdown')?.classList.remove('show');
    const sr = document.getElementById('searchResults');
    if (sr && !e.target.closest('#searchResults') && !e.target.closest('#globalSearch'))
        sr.classList.remove('show');
});

/* ── Mark all notifications read ──────────────────────────────── */
function markAllRead() {
    fetch(APP_URL + '/api/notify.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_all_read' })
    }).then(() => {
        document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        document.querySelectorAll('.badge-dot').forEach(el => el.remove());
    });
}

/* ── Global search ────────────────────────────────────────────── */
let searchTimer;
const icons = { call: 'fa-phone', contact: 'fa-user', note: 'fa-comment', task: 'fa-list-check', faq: 'fa-circle-question' };

function initGlobalSearch() {
    const input = document.getElementById('globalSearch');
    const box   = document.getElementById('searchResults');
    if (!input || !box) return;

    input.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = input.value.trim();
        if (q.length < 2) { box.classList.remove('show'); return; }
        searchTimer = setTimeout(() => {
            fetch(APP_URL + '/api/search.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(d => {
                    if (!d.results?.length) { box.classList.remove('show'); return; }
                    box.innerHTML = d.results.map(r => {
                        if (r.type === 'contact') {
                            const fav  = r.is_favorite ? '★ ' : '';
                            const co   = r.company ? ' · ' + escHtml(r.company) : '';
                            const last = r.last_call ? 'Last: ' + escHtml(r.last_call) : 'No calls yet';
                            return `
                            <div class="search-contact-card">
                                <a href="${r.url_profile}" class="search-result-item type-contact">
                                    <div class="search-result-icon"><i class="fas fa-user"></i></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="search-result-label">${fav}${escHtml(r.label)} &nbsp;<small class="text-muted">${escHtml(r.phone)}${co}</small></div>
                                        <div class="search-result-sub">${escHtml(r.scope)} &nbsp;·&nbsp; ${r.call_count} calls &nbsp;·&nbsp; ${last}</div>
                                    </div>
                                </a>
                                <div class="search-contact-links">
                                    <a href="${r.url_calls}" class="search-link"><i class="fas fa-phone me-1"></i>All Calls (${r.call_count})</a>
                                    <a href="${r.url_answered}" class="search-link sl-success"><i class="fas fa-check me-1"></i>Answered (${r.answered_count})</a>
                                    <a href="${r.url_missed}" class="search-link sl-danger"><i class="fas fa-phone-slash me-1"></i>Missed (${r.missed_count})</a>
                                    <a href="${r.url_profile}" class="search-link"><i class="fas fa-id-card me-1"></i>Profile</a>
                                </div>
                            </div>`;
                        }
                        return `
                        <a href="${r.url || '#'}" class="search-result-item type-${escHtml(r.type)}">
                            <div class="search-result-icon"><i class="fas ${icons[r.type] || 'fa-search'}"></i></div>
                            <div>
                                <div class="search-result-label">${escHtml(r.label || r.question || '')}</div>
                                <div class="search-result-sub">${escHtml(r.sub || r.category || r.type || '')}</div>
                            </div>
                        </a>`;
                    }).join('');
                    box.classList.add('show');
                });
        }, 280);
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { box.classList.remove('show'); input.blur(); }
    });
}

/* ── Contact autofill (for quick call modal + manual entry) ────── */
let contactHintTimer;
function contactAutofill(phone) {
    clearTimeout(contactHintTimer);
    const hint = document.getElementById('qcContactHint');
    if (!hint || phone.length < 5) { if (hint) hint.innerHTML = ''; return; }
    contactHintTimer = setTimeout(() => {
        fetch(APP_URL + '/api/contacts.php?action=lookup&phone=' + encodeURIComponent(phone))
            .then(r => r.json()).then(d => {
                if (d.ok && d.contact) {
                    const c = d.contact;
                    hint.innerHTML = `
                        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2)">
                            <div class="contact-avatar-sm">${escHtml((c.name||c.phone)[0].toUpperCase())}</div>
                            <div>
                                <div class="fw-medium small">${escHtml(c.name || c.phone)}</div>
                                <div class="text-muted" style="font-size:.72rem">${escHtml(c.type_name||'')} ${c.group_name ? '· '+escHtml(c.group_name) : ''} · ${escHtml(c.scope)}</div>
                                ${c.notes ? `<div class="text-muted" style="font-size:.72rem;margin-top:2px">${escHtml(c.notes.slice(0,80))}</div>` : ''}
                            </div>
                        </div>`;
                } else {
                    hint.innerHTML = '<div class="text-muted small mt-1"><i class="fas fa-user-plus me-1"></i>New contact will be created</div>';
                }
            });
    }, 400);
}

/* ── Quick call modal submit ──────────────────────────────────── */
function submitQuickCall() {
    const form = document.getElementById('quickCallForm');
    if (!form) return;
    const data = {
        action:        'manual',
        src:           form.src.value,
        dst:           form.dst?.value || '',
        call_direction:form.call_direction.value,
        disposition:   form.disposition.value,
        duration:      form.duration?.value || 0,
        calldate:      form.calldate?.value?.replace('T', ' ') || '',
        call_mark:     form.call_mark?.value || 'normal',
        manual_notes:  form.manual_notes?.value || '',
    };
    if (!data.src) { showToast('Phone number required', 'warning'); return; }

    fetch(APP_URL + '/api/calls.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('quickCallModal'))?.hide();
            showToast('Call logged successfully', 'success');
            setTimeout(() => {
                if (d.id) window.location = APP_URL + '/call_detail.php?id=' + d.id;
                else location.reload();
            }, 700);
        } else showToast(d.error || 'Error saving call', 'danger');
    });
}

/* ── Toast notification ────────────────────────────────────────── */
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const id   = 'toast-' + Date.now();
    const icons = { success: 'fa-check-circle text-success', danger: 'fa-times-circle text-danger',
                    warning: 'fa-exclamation-circle text-warning', info: 'fa-info-circle text-info' };
    const html = `
        <div id="${id}" class="toast cc-toast align-items-center" role="alert">
            <div class="d-flex align-items-center p-2 gap-2">
                <i class="fas ${icons[type] || icons.info}"></i>
                <div class="toast-body p-0 flex-grow-1">${escHtml(message)}</div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    const el = document.getElementById(id);
    const t  = new bootstrap.Toast(el, { delay: 3500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

/* ── Hourly sparkline chart ────────────────────────────────────── */
function renderSparkline() {
    const el = document.getElementById('hourlyChart');
    if (!el) return;
    const values = JSON.parse(el.dataset.values || '[]');
    const max    = Math.max(...values, 1);
    el.innerHTML = values.map((v, i) => {
        const h    = Math.round((v / max) * 100);
        const hour = i < 12 ? `${i || 12}${i === 0 ? 'am' : (i < 12 ? 'am' : 'pm')}` : `${i - 12 || 12}pm`;
        return `<div class="spark-bar" style="height:${Math.max(h, 2)}%" title="${hour}: ${v} calls"></div>`;
    }).join('');
}

/* ── Escape HTML ───────────────────────────────────────────────── */
function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Auto-suggest inputs (used on contacts search, etc.) ─────── */
function initAutoSuggest() {
    document.querySelectorAll('[data-suggest]').forEach(input => {
        const url   = input.dataset.suggest;
        const target= input.dataset.suggestTarget;
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) return;
            timer = setTimeout(() => {
                fetch(url + encodeURIComponent(q)).then(r => r.json()).then(d => {
                    const list = document.getElementById(target);
                    if (!list || !d.results) return;
                    list.innerHTML = d.results.map(r =>
                        `<option value="${escHtml(r.phone || r.label || r.name)}">${escHtml(r.label || r.name)} — ${escHtml(r.phone || '')}</option>`
                    ).join('');
                });
            }, 300);
        });
    });
}

/* ── Keyboard shortcuts ────────────────────────────────────────── */
document.addEventListener('keydown', e => {
    // Ctrl+K → focus global search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('globalSearch')?.focus();
    }
    // Ctrl+N → open quick call modal
    if ((e.ctrlKey || e.metaKey) && e.key === 'n' && !e.target.matches('input,textarea')) {
        e.preventDefault();
        const modal = document.getElementById('quickCallModal');
        if (modal) new bootstrap.Modal(modal).show();
    }
});

/* ── Contact field suggestions (type / group / company) ────────── */
function initContactSuggest(inputEl, field) {
    if (!inputEl) return;
    const dropId = '__sug_' + inputEl.id;
    let drop = document.getElementById(dropId);
    if (!drop) {
        drop = document.createElement('div');
        drop.id = dropId;
        drop.className = 'cc-suggest-drop';
        drop.style.cssText = 'position:absolute;z-index:9999;background:var(--card2);border:1px solid var(--border);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:100%;max-height:200px;overflow-y:auto;display:none';
        inputEl.parentElement.style.position = 'relative';
        inputEl.parentElement.appendChild(drop);
    }
    let timer;
    inputEl.addEventListener('input', () => {
        clearTimeout(timer);
        const q = inputEl.value.trim();
        if (q.length < 1) { drop.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch(APP_URL + '/api/contacts.php?action=suggest&field=' + field + '&q=' + encodeURIComponent(q))
                .then(r => r.json()).then(d => {
                    if (!d.results?.length) { drop.style.display = 'none'; return; }
                    drop.innerHTML = d.results.map(v =>
                        `<div class="cc-sug-item" style="padding:.35rem .75rem;cursor:pointer;font-size:.85rem" onmousedown="event.preventDefault()" onclick="this.closest('[style*=relative]').querySelector('input').value=${JSON.stringify(v)};document.getElementById('${dropId}').style.display='none'">${escHtml(v)}</div>`
                    ).join('');
                    drop.style.display = 'block';
                });
        }, 200);
    });
    inputEl.addEventListener('blur', () => setTimeout(() => { drop.style.display = 'none'; }, 150));
    inputEl.addEventListener('focus', () => { if (inputEl.value.trim()) inputEl.dispatchEvent(new Event('input')); });
}

/* ── Init ──────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initGlobalSearch();
    renderSparkline();
    initAutoSuggest();
});
