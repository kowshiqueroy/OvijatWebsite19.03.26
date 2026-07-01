const listEl = document.getElementById('paperList');
const emptyEl = document.getElementById('emptyState');
const searchInput = document.getElementById('searchInput');

function fmtDate(s) {
    const d = new Date(s.replace(' ', 'T') + 'Z');
    if (isNaN(d)) return s;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function renderPapers(papers) {
    listEl.innerHTML = '';
    emptyEl.hidden = papers.length > 0;
    papers.forEach(p => {
        const item = document.createElement('div');
        item.className = 'paper-item';
        item.innerHTML = `
            <div class="paper-item-main">
                <h4>${escapeHtml(p.name)}</h4>
                <span>${escapeHtml(p.subject_name || p.class_name || '')} · ${fmtDate(p.updated_at)}</span>
            </div>
            <div class="paper-item-actions">
                <button class="btn btn-ghost btn-sm" data-act="open" data-id="${p.id}">Open</button>
                <button class="btn btn-ghost btn-sm" data-act="dup" data-id="${p.id}">Duplicate</button>
                <button class="btn btn-danger btn-sm" data-act="del" data-id="${p.id}">Delete</button>
            </div>
        `;
        listEl.appendChild(item);
    });
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

async function loadPapers(q = '') {
    const res = await api('/api/paper.php?action=list' + (q ? '&q=' + encodeURIComponent(q) : ''));
    if (res.ok) renderPapers(res.papers);
}

listEl.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.dataset.id;
    const act = btn.dataset.act;

    if (act === 'open') {
        window.location.href = APP_BASE + '/editor.php?id=' + id;
    } else if (act === 'dup') {
        const res = await api('/api/paper.php?action=duplicate', { method: 'POST', body: { id: Number(id) } });
        if (res.ok) { toast('Duplicated'); loadPapers(searchInput.value.trim()); }
        else toast(res.error || 'Failed to duplicate', 'error');
    } else if (act === 'del') {
        if (!confirm('Delete this question paper? This cannot be undone.')) return;
        const res = await api('/api/paper.php?action=delete', { method: 'POST', body: { id: Number(id) } });
        if (res.ok) { toast('Deleted'); loadPapers(searchInput.value.trim()); }
        else toast(res.error || 'Failed to delete', 'error');
    }
});

let searchTimer;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadPapers(searchInput.value.trim()), 250);
});

document.getElementById('createBtn').addEventListener('click', () => {
    window.location.href = APP_BASE + '/setup.php';
});

document.getElementById('logoutBtn').addEventListener('click', async () => {
    await api('/api/auth.php?action=logout', { method: 'POST' });
    window.location.href = APP_BASE + '/index.php';
});

loadPapers();
