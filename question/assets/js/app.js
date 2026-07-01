// Shared helpers used across index/dashboard/setup pages.
const APP_BASE = window.APP_BASE || '';

async function api(path, options = {}) {
    const res = await fetch(APP_BASE + path, {
        method: options.method || 'GET',
        headers: { 'Content-Type': 'application/json' },
        body: options.body ? JSON.stringify(options.body) : undefined,
        credentials: 'same-origin',
    });
    let data;
    try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Invalid server response' }; }
    return data;
}

function toast(message, type = '') {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'toast-stack';
        document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = 'toast' + (type ? ' ' + type : '');
    el.textContent = message;
    stack.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 250);
    }, 2600);
}

// ---------------- Auth modal (landing page) ----------------
(function initAuthModal() {
    const overlay = document.getElementById('authOverlay');
    if (!overlay) return;

    const panels = {
        login: document.getElementById('panelLogin'),
        noUser: document.getElementById('panelNoUser'),
        register: document.getElementById('panelRegister'),
    };

    function showPanel(name) {
        Object.values(panels).forEach(p => p.hidden = true);
        panels[name].hidden = false;
    }

    function openModal() {
        showPanel('login');
        document.getElementById('loginError').textContent = '';
        overlay.classList.add('open');
    }
    function closeModal() { overlay.classList.remove('open'); }

    document.getElementById('openAuthBtn')?.addEventListener('click', openModal);
    document.getElementById('heroAuthBtn')?.addEventListener('click', openModal);
    document.getElementById('authClose').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

    let pendingUsername = '';

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value;
        const errEl = document.getElementById('loginError');
        errEl.textContent = '';

        const result = await api('/api/auth.php?action=login', { method: 'POST', body: { username, password } });
        if (result.ok) {
            window.location.href = APP_BASE + '/dashboard.php';
            return;
        }
        if (result.code === 'no_user') {
            pendingUsername = username;
            document.getElementById('noUserName').textContent = username;
            showPanel('noUser');
        } else {
            errEl.textContent = result.error || 'Wrong password, try again';
        }
    });

    document.getElementById('noUserCancel').addEventListener('click', () => showPanel('login'));
    document.getElementById('noUserCreate').addEventListener('click', () => {
        document.getElementById('regUsername').value = pendingUsername;
        document.getElementById('registerError').textContent = '';
        showPanel('register');
    });

    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('regUsername').value.trim();
        const password = document.getElementById('regPassword').value;
        const displayName = document.getElementById('regDisplayName').value.trim();
        const errEl = document.getElementById('registerError');
        errEl.textContent = '';

        const result = await api('/api/auth.php?action=register', {
            method: 'POST',
            body: { username, password, display_name: displayName },
        });
        if (result.ok) {
            window.location.href = APP_BASE + '/dashboard.php';
        } else {
            errEl.textContent = result.error || 'Could not create account';
        }
    });
})();
