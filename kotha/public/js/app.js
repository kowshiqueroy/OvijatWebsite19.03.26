/* public/js/app.js */

// Global variables set by view scripts:
// - CURRENT_USER_ID
// - ACTIVE_CHAT_ID (optional)
// - BASE_URL

let isPinUnlocked = false;
let inactivityTimer = null;
let activeVanishTimers = {};

const FAKE_CODING_TEXTS = [
    "const database = new sqlite3.Database('./database/core.sqlite');",
    "import { useState, useEffect } from 'react';",
    "public function dispatch(string $method, string $uri): void {",
    "docker-compose up --build -d database service",
    "git commit -m \"Fix session lock on SSE stream broker\" -a",
    "npm run build --workspace=packages/client",
    "PDO::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);",
    "const Peer = require('peerjs').Peer;",
    "const AudioContext = window.AudioContext || window.webkitAudioContext;",
    "header('Content-Type: text/event-stream');",
    "RewriteEngine On && RewriteRule ^(.*)$ index.php [QSA,L]",
    "session_write_close(); // Prevent PHP thread exhaustion",
    "SELECT * FROM messages ORDER BY created_at DESC LIMIT 1;",
    "chmod -R 755 /var/www/html/uploads",
    "yarn install && yarn dev",
    "class AuthController extends BaseController {",
    "const fileUuid = 'upload_' + Math.random().toString(36);",
    "ob_flush(); flush(); // Force output buffer clean",
    "usort($activeChats, function($a, $b) { return $b <=> $a; });",
    "const socket = new WebSocket('ws://localhost:8080');"
];

/* ============================================================
   Toast Notification System (replaces all alert() calls)
   ============================================================ */

(function injectToastStyles() {
    if (document.getElementById('kotha-toast-styles')) return;
    const style = document.createElement('style');
    style.id = 'kotha-toast-styles';
    style.textContent = `
        @keyframes kToastIn  { from { opacity:0; transform: translateX(60px); } to { opacity:1; transform: translateX(0); } }
        @keyframes kToastOut { from { opacity:1; transform: translateX(0);    } to { opacity:0; transform: translateX(60px); } }
        #kToastContainer { position:fixed; bottom:20px; right:20px; z-index:99999;
            display:flex; flex-direction:column; gap:8px; max-width:340px; pointer-events:none; }
        .k-toast { background:var(--bg-panel,#1f2c34); border-radius:10px; padding:12px 16px;
            color:var(--text-primary,#e9edef); font-family:'Outfit',sans-serif; font-size:0.85rem;
            display:flex; align-items:center; gap:10px; box-shadow:0 4px 20px rgba(0,0,0,.45);
            animation:kToastIn .3s ease; pointer-events:all; cursor:pointer;
            border-left:4px solid #3b82f6; }
        .k-toast.toast-success { border-left-color:#22c55e; }
        .k-toast.toast-error   { border-left-color:#ef4444; }
        .k-toast.toast-warning { border-left-color:#f59e0b; }
        .k-toast.toast-info    { border-left-color:#3b82f6; }
        .k-toast.removing      { animation:kToastOut .25s ease forwards; }
    `;
    document.head.appendChild(style);
})();

function showToast(message, type = 'info', duration = 4000) {
    let container = document.getElementById('kToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'kToastContainer';
        document.body.appendChild(container);
    }
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
    const colors = { success:'#22c55e', error:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
    const t = type in icons ? type : 'info';

    const toast = document.createElement('div');
    toast.className = `k-toast toast-${t}`;
    toast.innerHTML = `<i class="fa-solid ${icons[t]}" style="color:${colors[t]};font-size:1rem;flex-shrink:0;"></i><span>${message}</span>`;
    toast.onclick = () => _removeToast(toast);
    container.appendChild(toast);

    setTimeout(() => _removeToast(toast), duration);
}

function _removeToast(toast) {
    if (!toast.parentNode) return;
    toast.classList.add('removing');
    setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
}

function showConfirm(message, onConfirm, onCancel = null) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99998;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = `
        <div style="background:var(--bg-panel,#1f2c34);border:1px solid rgba(255,255,255,.1);border-radius:16px;
             padding:28px 24px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.6);">
            <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;font-size:2rem;margin-bottom:14px;display:block;"></i>
            <p style="color:var(--text-primary,#e9edef);margin:0 0 22px;font-size:0.9rem;line-height:1.55;font-family:'Outfit',sans-serif;">${message}</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button id="_kConfYes" style="background:linear-gradient(135deg,#ef4444,#b91c1c);border:none;color:#fff;
                    padding:9px 22px;border-radius:8px;cursor:pointer;font-weight:600;font-family:'Outfit',sans-serif;">Confirm</button>
                <button id="_kConfNo" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);
                    color:var(--text-primary,#e9edef);padding:9px 22px;border-radius:8px;cursor:pointer;font-family:'Outfit',sans-serif;">Cancel</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    const close = (cb) => { document.body.removeChild(overlay); if (cb) cb(); };
    overlay.querySelector('#_kConfYes').onclick = () => close(onConfirm);
    overlay.querySelector('#_kConfNo').onclick  = () => close(onCancel);
    overlay.onclick = (e) => { if (e.target === overlay) close(onCancel); };
}

function showPrompt(message, defaultValue, onConfirm) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99998;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = `
        <div style="background:var(--bg-panel,#1f2c34);border:1px solid rgba(255,255,255,.1);border-radius:16px;
             padding:28px 24px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.6);">
            <p style="color:var(--text-primary,#e9edef);margin:0 0 14px;font-size:0.9rem;font-family:'Outfit',sans-serif;">${message}</p>
            <input id="_kPromptInput" type="text" value="${defaultValue || ''}"
                style="width:100%;box-sizing:border-box;padding:9px 12px;background:var(--bg-header,#202c33);
                border:1px solid rgba(255,255,255,.12);border-radius:8px;color:var(--text-primary,#e9edef);
                font-family:'Outfit',sans-serif;margin-bottom:18px;">
            <div style="display:flex;gap:10px;justify-content:center;">
                <button id="_kPromptOk" style="background:linear-gradient(135deg,#00f2fe,#4facfe);border:none;color:#0b141a;
                    padding:9px 22px;border-radius:8px;cursor:pointer;font-weight:600;font-family:'Outfit',sans-serif;">Save</button>
                <button id="_kPromptCancel" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);
                    color:var(--text-primary,#e9edef);padding:9px 22px;border-radius:8px;cursor:pointer;font-family:'Outfit',sans-serif;">Cancel</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    const input = overlay.querySelector('#_kPromptInput');
    input.focus();
    input.select();

    const close = (value) => { document.body.removeChild(overlay); if (value !== null && onConfirm) onConfirm(value); };
    overlay.querySelector('#_kPromptOk').onclick     = () => close(input.value);
    overlay.querySelector('#_kPromptCancel').onclick = () => close(null);
    overlay.onclick = (e) => { if (e.target === overlay) close(null); };
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') close(input.value); });
}

/* ============================================================
   Location Prompt System
   — Shows once per login (PHP session flag drives this).
   — On allow:  sends lat/lng to server, closes modal.
   — On deny:   sends denial flag, shows full-screen warning.
   — Try Again: re-requests permission (works when status is
     "prompt"; tells user to open settings when "denied").
   ============================================================ */

let _locDenyCount = 0;

function _showLocModal() {
    const m = document.getElementById('locPermModal');
    if (m) m.style.display = 'flex';
}
function _hideLocModal() {
    const m = document.getElementById('locPermModal');
    if (m) m.style.display = 'none';
}
function _showLocDenied() {
    _hideLocModal();
    const s = document.getElementById('locDeniedScreen');
    if (s) s.style.display = 'flex';
    const dc = document.getElementById('locDenyCount');
    if (dc) dc.textContent = _locDenyCount;
}
function _hideLocDenied() {
    const s = document.getElementById('locDeniedScreen');
    if (s) s.style.display = 'none';
}

function requestUserLocation() {
    const btn = document.getElementById('locAllowBtn');
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Requesting...';
        btn.disabled  = true;
    }

    if (!navigator.geolocation) {
        // Geolocation API not available (very old browser or secure-context missing)
        _postLocationDeny();
        _showLocDenied();
        return;
    }

    navigator.geolocation.getCurrentPosition(
        // Success
        function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy;

            const fd = new FormData();
            fd.append('lat',      lat);
            fd.append('lng',      lng);
            fd.append('accuracy', acc);

            fetch(`${BASE_URL}/api/location/save`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                _hideLocModal();
                if (d.success) {
                    showToast('Location verified. Session secured.', 'success', 4000);
                }
            })
            .catch(() => _hideLocModal());
        },
        // Error / Denied
        function(err) {
            _locDenyCount++;
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-location-dot"></i> Allow Location';
                btn.disabled  = false;
            }
            // Check if permanently blocked or just dismissed
            if (navigator.permissions) {
                navigator.permissions.query({ name: 'geolocation' })
                .then(result => {
                    if (result.state === 'denied') {
                        // Permanently blocked — show warning screen
                        _postLocationDeny();
                        _showLocDenied();
                    } else {
                        // Dismissed (not permanently blocked) — try again nudge
                        _postLocationDeny();
                        _showLocDenied();
                    }
                })
                .catch(() => {
                    _postLocationDeny();
                    _showLocDenied();
                });
            } else {
                _postLocationDeny();
                _showLocDenied();
            }
        },
        // Options: don't wait forever
        { timeout: 15000, maximumAge: 0, enableHighAccuracy: true }
    );
}

function denyLocationModal() {
    // User clicked "Skip" on the main modal
    _locDenyCount++;
    _postLocationDeny();
    _showLocDenied();
}

function retryLocation() {
    _hideLocDenied();
    // Check current permission state first
    if (navigator.permissions) {
        navigator.permissions.query({ name: 'geolocation' })
        .then(result => {
            if (result.state === 'denied') {
                // Browser has permanently blocked — can't re-prompt programmatically
                // Show the denied screen with settings instructions
                _showLocDenied();
                showToast('Location is blocked in browser settings. Please enable it manually.', 'warning', 7000);
            } else {
                // Can re-prompt — show the modal again
                _showLocModal();
            }
        })
        .catch(() => _showLocModal());
    } else {
        // Fallback: just try again
        _showLocModal();
    }
}

function continueWithoutLocation() {
    // User explicitly chose to skip — flag it and let them in
    _hideLocDenied();
    showToast('Location denied. Session flagged for admin review.', 'warning', 5000);
}

function _postLocationDeny() {
    fetch(`${BASE_URL}/api/location/deny`, { method: 'POST' }).catch(() => {});
}

// Boot: show location modal if login triggered the prompt.
// But first check whether the browser has already granted permission —
// if so, silently capture location with no popup at all.
// If permission is already denied, skip the modal and go straight
// to the warning screen.
document.addEventListener('DOMContentLoaded', function() {
    if (window.LOCATION_PROMPT !== true) return;

    setTimeout(function() {
        if (!navigator.geolocation) {
            // No Geolocation API — browser too old or insecure context
            _showLocModal();
            return;
        }

        if (!navigator.permissions) {
            // permissions API not available (older Safari / some Android)
            // Fall back: show the modal so the user can decide
            _showLocModal();
            return;
        }

        navigator.permissions.query({ name: 'geolocation' })
        .then(function(result) {
            if (result.state === 'granted') {
                // ── Already allowed: capture silently, no popup ──────────
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        const fd = new FormData();
                        fd.append('lat',      pos.coords.latitude);
                        fd.append('lng',      pos.coords.longitude);
                        fd.append('accuracy', pos.coords.accuracy);
                        fetch(`${BASE_URL}/api/location/save`, { method:'POST', body:fd })
                        .catch(() => {});
                    },
                    function() { /* position error despite granted — ignore silently */ },
                    { timeout: 10000, maximumAge: 300000 }
                );

            } else if (result.state === 'denied') {
                // ── Already blocked: skip modal, show warning immediately ─
                _locDenyCount++;
                _postLocationDeny();
                _showLocDenied();

            } else {
                // ── 'prompt': user hasn't decided yet — show the modal ───
                _showLocModal();
            }
        })
        .catch(function() {
            // permissions.query() threw (should not happen, but be safe)
            _showLocModal();
        });

    }, 1200);
});

/* ============================================================
   Presence Polling — updates sidebar last_seen every 15 s
   ============================================================ */
let _presenceInterval = null;

function startPresencePolling() {
    if (_presenceInterval) return;
    fetchAndApplyPresence();                                       // immediate first fetch
    _presenceInterval = setInterval(fetchAndApplyPresence, 8000); // every 8 s — safely under the 30 s online threshold
}

function fetchAndApplyPresence() {
    fetch(`${BASE_URL}/api/presence`)
        .then(r => r.json())
        .then(data => {
            // Update group online/offline counts in sidebar
            updateGroupOnlineCounts(data.groups || {});

            const presence = data.presence || {};

            // 1. Update sidebar data-last-seen for all direct-chat preview items
            document.querySelectorAll('.chat-preview[data-chat-type="direct"]').forEach(el => {
                const chatId = el.id.replace('preview-', '');
                const parts  = chatId.replace('1on1_', '').split('_');
                let partnerId = null;
                for (const p of parts) {
                    if (parseInt(p) !== CURRENT_USER_ID) { partnerId = p; break; }
                }
                if (!partnerId || !presence[partnerId]) return;
                el.setAttribute('data-last-seen', presence[partnerId]);
                // Also keep the presence-ring dot attribute fresh
                const dot = document.getElementById(`dot-${chatId}`);
                if (dot) dot.setAttribute('data-last-seen', presence[partnerId]);
            });

            // 2. Refresh window.partnerLastSeenTime for the open chat.
            //    This was set once at page-load and becomes stale, making both
            //    the header status and initiateCall() show the partner as offline.
            if (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID &&
                ACTIVE_CHAT_ID.startsWith('1on1_')) {
                const parts = ACTIVE_CHAT_ID.replace('1on1_', '').split('_');
                for (const p of parts) {
                    if (parseInt(p) !== CURRENT_USER_ID) {
                        if (presence[p] !== undefined) {
                            window.partnerLastSeenTime = presence[p];
                        }
                        break;
                    }
                }
            }
        })
        .catch(() => {});
}

/* ============================================================
   Camouflage helpers
   ============================================================ */
function getCamouflageText(msgId) {
    let hash = 0;
    const str = String(msgId);
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    return FAKE_CODING_TEXTS[Math.abs(hash) % FAKE_CODING_TEXTS.length];
}

function markMessageAsVanishedLocally(msgId) {
    try {
        let vanished = JSON.parse(localStorage.getItem('vanished_messages') || '[]');
        if (!vanished.includes(msgId)) {
            vanished.push(msgId);
            localStorage.setItem('vanished_messages', JSON.stringify(vanished));
        }
    } catch (e) {}
}

/* ============================================================
   Cross-browser MediaRecorder MIME detection
   Fixes Safari (no WebM) and iOS (limited codec support).
   ============================================================ */
function _getRecordingMime(kind) {
    const videoTypes = [
        'video/webm;codecs=vp8,opus', 'video/webm;codecs=vp9,opus',
        'video/webm', 'video/mp4;codecs=avc1,mp4a.40.2', 'video/mp4',
    ];
    const audioTypes = [
        'audio/webm;codecs=opus', 'audio/ogg;codecs=opus',
        'audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/webm',
    ];
    const list = kind === 'audio' ? audioTypes : videoTypes;
    return list.find(t => { try { return MediaRecorder.isTypeSupported(t); } catch(e) { return false; } }) || '';
}
function _mimeToExt(mimeType) {
    if (!mimeType) return 'webm';
    if (mimeType.includes('mp4')) return 'mp4';
    if (mimeType.includes('ogg')) return 'ogg';
    return 'webm';
}
/* Derive MIME type for <source type=""> from a file path extension */
function _pathMime(path, kind) {
    const ext = (path || '').split('.').pop().toLowerCase().split('?')[0];
    const map = {
        mp4:'video/mp4', webm:'video/webm', ogg:'video/ogg', mov:'video/mp4',
        m4a:'audio/mp4', mp3:'audio/mpeg', wav:'audio/wav',
    };
    return map[ext] || (kind === 'audio' ? 'audio/mpeg' : 'video/mp4');
}

/* ============================================================
   App Init
   ============================================================ */
document.addEventListener("DOMContentLoaded", function() {
    initApp();

    if (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID) {
        loadChatMessages();
        setupChatInput();
        setupAttachments();
    }
});

/* ============================================================
   Sidebar Search — filters chats + shows People panel
   ============================================================ */
function handleSidebarSearch(val) {
    const hidden = getHiddenChats();
    document.querySelectorAll('.chat-item').forEach(item => {
        const chatId = item.getAttribute('data-chat-id')
                    || (item.getAttribute('onclick') || '').replace(/.*'([^']+)'.*/, '$1');
        const title  = item.querySelector('.chat-title-text')?.innerText?.toLowerCase() || '';

        if (val) {
            // When searching: temporarily show hidden chats that match the query
            if (title.includes(val)) {
                item.classList.remove('chat-hidden');  // reveal for search
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
                // Don't add chat-hidden class for non-matching — this is just search filter
            }
        } else {
            // No search: re-apply the persistent hidden state, show everything else
            item.style.display = '';   // clear any search-filter inline style
            if (chatId && hidden.includes(chatId)) {
                item.classList.add('chat-hidden');
            } else {
                item.classList.remove('chat-hidden');
            }
        }
    });
    updatePeopleResults(val);
}

function updatePeopleResults(val) {
    const panel = document.getElementById('peopleSearchResults');
    if (!panel) return;
    if (!val || !window.AVAILABLE_USERS || !window.AVAILABLE_USERS.length) {
        panel.style.display = 'none'; return;
    }
    const matches = window.AVAILABLE_USERS.filter(u =>
        u.full_name.toLowerCase().includes(val) ||
        u.email.toLowerCase().includes(val) ||
        (u.institute || '').toLowerCase().includes(val)
    );
    if (!matches.length) { panel.style.display = 'none'; return; }

    panel.style.display = 'block';
    const colors = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1'];
    const rows = matches.map(u => `
        <div class="chat-item" onclick="startDirectChat(${u.id})" style="border-left:2px solid var(--accent);">
            <div class="chat-item-avatar" style="background:${colors[u.id % 8]};color:#fff;">
                <i class="fa-solid fa-user" style="font-size:.9rem;"></i>
            </div>
            <div class="chat-details">
                <div class="chat-mid-box">
                    <span class="chat-title"><span class="chat-title-text">${_esc(u.full_name)}</span></span>
                    <span style="font-size:0.72rem;color:var(--text-secondary);">${_esc(u.email)}</span>
                </div>
                <div style="font-size:0.7rem;color:var(--accent);font-weight:600;white-space:nowrap;">Start Chat →</div>
            </div>
        </div>`).join('');
    panel.innerHTML = `<div style="padding:0 12px 6px;font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;">People</div>${rows}`;
}

/* ============================================================
   Hide / Unhide Chat (localStorage)
   ============================================================ */
/* ── Hidden-chat CSS class — !important wins over any inline style ── */
(function _injectHiddenStyle() {
    const s = document.createElement('style');
    s.id    = '_kHiddenChat';
    s.textContent = '.chat-hidden { display: none !important; }';
    document.head.appendChild(s);
})();

function getHiddenChats() {
    try { return JSON.parse(localStorage.getItem('kotha_hidden_chats') || '[]'); } catch(e) { return []; }
}
function _saveHiddenChats(arr) {
    try { localStorage.setItem('kotha_hidden_chats', JSON.stringify(arr)); } catch(e) {}
}

/* Find a chat-item by chatId — prefer data-chat-id, fall back to onclick text */
function _findChatItem(chatId) {
    return document.querySelector(`.chat-item[data-chat-id="${CSS.escape(chatId)}"]`)
        || document.querySelector(`.chat-item[onclick*="${chatId}"]`);
}

/* Update the hide-button icon/title to reflect current state */
function _setHideBtnState(item, isHidden) {
    const btn  = item?.querySelector('.hide-chat-btn');
    const icon = btn?.querySelector('i');
    if (btn)  btn.title        = isHidden ? 'Show chat'         : 'Hide chat';
    if (icon) icon.className   = isHidden ? 'fa-solid fa-eye'   : 'fa-solid fa-eye-slash';
}

/* Toggle: hides if visible, shows if hidden */
function toggleChatVisibility(chatId) {
    if (getHiddenChats().includes(chatId)) {
        unhideChat(chatId);
    } else {
        hideChat(chatId);
    }
}

function hideChat(chatId) {
    const h = getHiddenChats();
    if (!h.includes(chatId)) { h.push(chatId); _saveHiddenChats(h); }

    const item = _findChatItem(chatId);
    if (item) {
        _setHideBtnState(item, true);   // switch icon to "eye" (click to show)
        item.style.transition = 'opacity .3s, transform .3s';
        item.style.opacity    = '0';
        item.style.transform  = 'translateX(-20px)';
        setTimeout(() => {
            item.classList.add('chat-hidden');
            item.style.opacity   = '1';
            item.style.transform = '';
        }, 310);
    }
    closeChatInfo();
    showToast('Chat hidden. Search by name, then click 👁 to restore.', 'info');
}

function unhideChat(chatId) {
    _saveHiddenChats(getHiddenChats().filter(id => id !== chatId));
    const item = _findChatItem(chatId);
    if (item) {
        item.classList.remove('chat-hidden');
        item.style.display = 'flex';
        _setHideBtnState(item, false);  // switch icon back to "eye-slash" (click to hide)
    }
    showToast('Chat visible again.', 'success');
}

function applyHiddenChats() {
    const h = getHiddenChats();
    document.querySelectorAll('.chat-item').forEach(item => {
        const id = item.getAttribute('data-chat-id')
                || (item.getAttribute('onclick') || '').replace(/.*'([^']+)'.*/, '$1');
        if (id && h.includes(id)) {
            item.classList.add('chat-hidden');
            _setHideBtnState(item, true);    // eye icon — "click to show"
        } else {
            item.classList.remove('chat-hidden');
            _setHideBtnState(item, false);   // eye-slash — "click to hide"
        }
    });
}
function toggleHideCurrentChat() {
    const modal = document.getElementById('chatInfoModal');
    if (!modal || !modal._chatId) return;
    const chatId = modal._chatId;
    if (getHiddenChats().includes(chatId)) { unhideChat(chatId); }
    else { hideChat(chatId); }
}

/* ============================================================
   Chat Info Modal — nickname, group members, hide
   ============================================================ */
let _ciChatId = null, _ciChatType = null, _ciInfoData = null;

function openChatInfo(chatId, chatType, displayName, partnerId) {
    _ciChatId   = chatId;
    _ciChatType = chatType;
    _ciInfoData = null;

    const modal = document.getElementById('chatInfoModal');
    if (!modal) return;
    modal._chatId    = chatId;
    modal._chatType  = chatType;
    modal._partnerId = partnerId;

    document.getElementById('ciTitle').textContent    = chatType === 'group' ? 'Group Info' : 'Chat Info';
    document.getElementById('ciSubtitle').textContent = displayName;
    document.getElementById('ciLoading').style.display  = 'block';
    document.getElementById('ciContent').style.display  = 'none';
    document.getElementById('ciMembersSection').style.display  = 'none';
    document.getElementById('ciPartnerSection').style.display  = 'none';
    document.getElementById('ciAddMemberInput').value   = '';
    document.getElementById('ciAddMemberResults').innerHTML = '';

    modal.style.display = 'flex';

    fetch(`${BASE_URL}/api/chats/${chatId}/info`)
    .then(r => r.json())
    .then(data => {
        _ciInfoData = data;
        document.getElementById('ciLoading').style.display = 'none';
        document.getElementById('ciContent').style.display = 'block';

        // Nickname input
        const nickLabel = document.getElementById('ciNickLabel');
        const nickInput = document.getElementById('ciNickInput');
        if (chatType === 'group') {
            nickLabel.textContent = 'Group nickname (your local label):';
            nickInput.value       = data.group_nickname || '';
            document.getElementById('ciMembersSection').style.display = 'block';
            renderCiMembers(data);
        } else {
            nickLabel.textContent = 'Contact nickname (your local label):';
            const partner = (data.participants || []).find(p => p.id != data.current_user_id);
            nickInput.value = partner ? (data.member_nicknames[partner.id] || '') : '';
            document.getElementById('ciPartnerSection').style.display = 'block';
            renderCiPartner(data, partner);
        }

        // Hide/show toggle
        const isHidden = getHiddenChats().includes(chatId);
        document.getElementById('ciHideLabel').textContent = isHidden ? 'Unhide Chat' : 'Hide Chat';
        const hiIcon = document.querySelector('#ciHideBtn i');
        if (hiIcon) hiIcon.className = isHidden ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
    })
    .catch(() => {
        document.getElementById('ciLoading').innerHTML = '<span style="color:#ef4444;">Failed to load chat info.</span>';
    });
}

function closeChatInfo() {
    const modal = document.getElementById('chatInfoModal');
    if (modal) modal.style.display = 'none';
    _ciChatId = null; _ciChatType = null; _ciInfoData = null;
}

function renderCiPartner(data, partner) {
    const sec = document.getElementById('ciPartnerSection');
    if (!partner) { sec.innerHTML = ''; return; }
    const isOnline = partner.is_online;
    const colors   = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1'];
    sec.innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.03);border-radius:10px;border:1px solid var(--border-color);margin-bottom:10px;">
            <div style="width:44px;height:44px;border-radius:50%;background:${colors[partner.id%8]};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;position:relative;flex-shrink:0;">
                ${_esc(partner.full_name.charAt(0).toUpperCase())}
                <span style="position:absolute;bottom:0;right:0;width:11px;height:11px;border-radius:50%;background:${isOnline ? '#22c55e' : '#667781'};border:2px solid var(--bg-panel);"></span>
            </div>
            <div>
                <div style="font-weight:600;font-size:0.88rem;">${_esc(partner.full_name)}</div>
                <div style="font-size:0.72rem;color:var(--text-secondary);">${_esc(partner.email)}</div>
                <div style="font-size:0.7rem;color:${isOnline ? '#22c55e' : '#667781'};margin-top:2px;">${isOnline ? '● Online' : '● Offline'}</div>
            </div>
        </div>`;
}

function renderCiMembers(data) {
    const list    = document.getElementById('ciMembersList');
    const countEl = document.getElementById('ciMemberCount');
    const onlEl   = document.getElementById('ciGroupOnline');
    const members = data.participants || [];
    const me      = data.current_user_id;
    const creator = data.group_creator;

    countEl.textContent = members.length;
    const onlineCount = members.filter(m => m.is_online).length;
    onlEl.textContent  = `${onlineCount}/${members.length} online`;

    const colors = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1'];

    list.innerHTML = members.map(m => {
        const isMe      = m.id == me;
        const isCreator = m.id == creator;
        const currentNick = data.member_nicknames[m.id] || '';
        return `
        <div style="display:flex;align-items:center;gap:10px;padding:9px 10px;background:rgba(255,255,255,.03);border-radius:8px;border:1px solid var(--border-color);">
            <div style="width:36px;height:36px;border-radius:50%;background:${colors[m.id%8]};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0;position:relative;">
                ${_esc(m.full_name.charAt(0).toUpperCase())}
                <span style="position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;border-radius:50%;background:${m.is_online ? '#22c55e' : '#667781'};border:2px solid var(--bg-panel);"></span>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:0.83rem;font-weight:600;">${_esc(currentNick || m.full_name)} ${isMe ? '<span style="color:var(--text-muted);font-weight:400;font-size:0.7rem;">(you)</span>' : ''} ${isCreator ? '<span style="color:var(--accent);font-size:0.65rem;font-weight:700;">CREATOR</span>' : ''}</div>
                <div style="font-size:0.7rem;color:var(--text-secondary);">${_esc(m.email)}</div>
            </div>
            <div style="display:flex;gap:4px;flex-shrink:0;">
                <button title="Set nickname" onclick="promptMemberNickname(${m.id},'${_esc(m.full_name)}',\`${_esc(currentNick)}\`)"
                    style="background:none;border:1px solid var(--border-color);border-radius:6px;padding:4px 7px;color:var(--text-secondary);cursor:pointer;font-size:0.7rem;">
                    <i class="fa-solid fa-pen"></i>
                </button>
                ${(!isMe && (me == creator || isMe)) ? `
                <button title="Remove from group" onclick="removeMemberFromGroup(${m.id},'${_esc(m.full_name)}')"
                    style="background:none;border:1px solid rgba(239,68,68,.3);border-radius:6px;padding:4px 7px;color:#ef4444;cursor:pointer;font-size:0.7rem;">
                    <i class="fa-solid fa-xmark"></i>
                </button>` : ''}
            </div>
        </div>`;
    }).join('');
}

function saveChatNickname() {
    if (!_ciChatId) return;
    const nickname = document.getElementById('ciNickInput').value.trim();
    const type     = _ciChatType === 'group' ? 'chat' : 'user';
    const targetId = _ciChatType === 'group'
        ? _ciChatId
        : (document.getElementById('chatInfoModal')._partnerId || '');

    if (!targetId) { showToast('Cannot determine target.', 'error'); return; }

    const fd = new FormData();
    fd.append('target_type', type);
    fd.append('target_id',   String(targetId));
    fd.append('nickname',    nickname);

    fetch(`${BASE_URL}/api/chats/nickname`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Nickname saved.', 'success');
            // Update sidebar title immediately
            const item = document.querySelector(`.chat-item[data-chat-id="${_ciChatId}"] .chat-title-text`);
            if (item && nickname) item.textContent = nickname;
            closeChatInfo();
        } else {
            showToast(d.error || 'Failed to save.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
}

function promptMemberNickname(userId, realName, currentNick) {
    showPrompt(`Nickname for "${realName}" (your local label):`, currentNick, (nick) => {
        if (nick === null) return;
        const fd = new FormData();
        fd.append('target_type', 'user');
        fd.append('target_id',   String(userId));
        fd.append('nickname',    nick);
        fetch(`${BASE_URL}/api/chats/nickname`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(`Nickname saved for ${realName}.`, 'success');
                if (_ciInfoData) {
                    if (!_ciInfoData.member_nicknames) _ciInfoData.member_nicknames = {};
                    _ciInfoData.member_nicknames[userId] = nick;
                    renderCiMembers(_ciInfoData);
                }
            } else {
                showToast('Failed to save.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

function removeMemberFromGroup(userId, name) {
    if (!_ciChatId) return;
    showConfirm(`Remove "${name}" from the group?`, () => {
        const fd = new FormData();
        fd.append('user_id', userId);
        fetch(`${BASE_URL}/api/group/${_ciChatId}/members/remove`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast(d.error || 'Failed.', 'error'); return; }
            showToast(`${name} removed.`, 'success');
            if (_ciInfoData) {
                _ciInfoData.participants = _ciInfoData.participants.filter(p => p.id != userId);
                renderCiMembers(_ciInfoData);
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

function filterCiAddMember(val) {
    const results = document.getElementById('ciAddMemberResults');
    if (!results) return;
    val = val.trim().toLowerCase();
    if (!val || !window.AVAILABLE_USERS) { results.innerHTML = ''; return; }

    const existingIds = (_ciInfoData?.participants || []).map(p => p.id);
    const matches = window.AVAILABLE_USERS.filter(u =>
        !existingIds.includes(u.id) &&
        (u.full_name.toLowerCase().includes(val) || u.email.toLowerCase().includes(val))
    );

    if (!matches.length) { results.innerHTML = '<div style="font-size:0.78rem;color:var(--text-muted);padding:8px 0;">No matching users.</div>'; return; }

    const colors = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1'];
    results.innerHTML = matches.map(u => `
        <div style="display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:7px;cursor:pointer;transition:background .15s;"
             onmouseover="this.style.background='rgba(255,255,255,.05)'" onmouseout="this.style.background=''"
             onclick="addMemberToGroup(${u.id},'${_esc(u.full_name)}')">
            <div style="width:28px;height:28px;border-radius:50%;background:${colors[u.id%8]};display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.75rem;font-weight:700;flex-shrink:0;">
                ${_esc(u.full_name.charAt(0).toUpperCase())}
            </div>
            <div>
                <div style="font-size:0.82rem;font-weight:500;">${_esc(u.full_name)}</div>
                <div style="font-size:0.7rem;color:var(--text-secondary);">${_esc(u.email)}</div>
            </div>
            <div style="margin-left:auto;font-size:0.7rem;color:var(--accent);font-weight:600;">Add +</div>
        </div>`).join('');
}

function addMemberToGroup(userId, name) {
    if (!_ciChatId) return;
    const fd = new FormData();
    fd.append('user_id', userId);
    fetch(`${BASE_URL}/api/group/${_ciChatId}/members/add`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast(d.error || 'Failed.', 'error'); return; }
        showToast(`${name} added to group.`, 'success');
        document.getElementById('ciAddMemberInput').value = '';
        document.getElementById('ciAddMemberResults').innerHTML = '';
        // Reload chat info to refresh member list
        fetch(`${BASE_URL}/api/chats/${_ciChatId}/info`)
        .then(r => r.json())
        .then(data => { _ciInfoData = data; renderCiMembers(data); })
        .catch(() => {});
    })
    .catch(() => showToast('Network error.', 'error'));
}

/* ============================================================
   Group online counts in sidebar (updated by presence ticker)
   ============================================================ */
function updateGroupOnlineCounts(groups) {
    if (!groups) return;
    Object.entries(groups).forEach(([chatId, g]) => {
        const pill = document.getElementById(`gcount-${chatId}`);
        if (!pill) return;
        if (g.total > 0) {
            pill.textContent  = `${g.online}/${g.total} online`;
            pill.style.display = 'block';
            pill.style.color   = g.online > 0 ? '#22c55e' : 'var(--text-muted)';
        }
    });
}

function initApp() {
    updateSendButtonColor();
    applyHiddenChats();

    const chatSearch = document.getElementById('chatSearchInput');
    if (chatSearch) {
        chatSearch.addEventListener('input', function(e) {
            handleSidebarSearch(e.target.value.toLowerCase().trim());
        });
    }

    const newChatBtn = document.getElementById('newChatBtn');
    if (newChatBtn) newChatBtn.addEventListener('click', () => openModal('newChatModal'));

    const createGroupBtn = document.getElementById('createGroupBtn');
    if (createGroupBtn) createGroupBtn.addEventListener('click', () => openModal('createGroupModal'));

    resetInactivityTimer();
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetInactivityTimer);
    });

    document.addEventListener('click', function(e) {
        if (currentlyRevealedMsgId) {
            const bubble = document.querySelector(`[data-msg-id="${currentlyRevealedMsgId}"]`);
            if (bubble && !bubble.contains(e.target)) hideRevealedMessage();
        }
    });

    // Start presence polling for sidebar
    startPresencePolling();
}

/* ============================================================
   Inactivity Timeout & PIN Verification
   ============================================================ */
function updateSendButtonColor() {
    const sendBtn = document.getElementById('sendMsgBtn');
    // Send button stays functional in locked mode — only the icon changes
    if (sendBtn) {
        sendBtn.style.color  = isPinUnlocked ? 'var(--accent-green)' : 'var(--tx-2)';
        sendBtn.style.opacity = '1';
    }

    const input = document.getElementById('chatInput');
    if (input) {
        if (isPinUnlocked) {
            input.classList.add('locked-input');
            input.placeholder = 'Write a secure message...';
        } else {
            input.classList.remove('locked-input');
            input.placeholder = 'Type a message, or enter 4-digit PIN to reveal...';
        }
    }

    // Hide call buttons when locked (calls require PIN confirmation)
    document.querySelectorAll('.call-btn').forEach(btn => {
        btn.style.display = isPinUnlocked ? 'flex' : 'none';
    });
}

function toggleInputBlur() {
    const input = document.getElementById('chatInput');
    if (input) input.classList.toggle('locked-input');
    const menu = document.getElementById('attachMenu');
    if (menu) menu.classList.remove('show');
}

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(lockSystem, 60000);
}

function lockSystem() {
    if (!isPinUnlocked) return;
    isPinUnlocked = false;

    document.querySelectorAll('.message-bubble').forEach(bubble => {
        bubble.classList.remove('clickable');
        bubble.classList.add('locked');
        const textSpan = bubble.querySelector('.message-body-text');
        if (textSpan) {
            const msgId = bubble.getAttribute('data-msg-id');
            textSpan.innerHTML = `<span class="camouflage-text">${getCamouflageText(msgId)}</span>`;
        }
    });

    const input = document.getElementById('chatInput');
    if (input) input.placeholder = "Type a message, or type 4-digit PIN to reveal...";
    updateSendButtonColor();
}

function submitPin(pin) {
    const formData = new FormData();
    formData.append('pin', pin);

    fetch(`${BASE_URL}/api/chats/verify-pin`, { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            isPinUnlocked = true;

            document.querySelectorAll('.message-bubble').forEach(bubble => {
                bubble.classList.remove('locked');
                bubble.classList.add('clickable');
            });

            const input = document.getElementById('chatInput');
            if (input) input.placeholder = "Write a secure message...";

            closePinModal();
            updateSendButtonColor();

            if (window.pendingCallData) showCallPromptModal(window.pendingCallData);
        } else {
            const err = document.getElementById('pinErrorMessage');
            if (err) { err.style.display = 'block'; setTimeout(() => err.style.display = 'none', 3000); }
            showToast('Access Denied: Invalid security PIN', 'error');
        }
    })
    .catch(() => showToast('PIN verification failed. Try again.', 'error'));
}

function showCallPromptModal(data) {
    const modal = document.getElementById('incomingCallModal');
    if (!modal) return;
    document.getElementById('incomingCallTitle').innerText = `Incoming ${data.call_type === 'video' ? 'Video' : 'Voice'} Call`;
    document.getElementById('incomingCallText').innerText = `${data.caller_name || 'Collaborator'} is calling you...`;

    const acceptBtn = document.getElementById('btnAcceptCall');
    if (acceptBtn) {
        acceptBtn.onclick = function() {
            modal.style.display = 'none';
            window.pendingCallData = null;
            window.location.href = `${BASE_URL}/call?chatId=${data.chat_id}&type=${data.call_type}&mode=incoming&peerId=${data.peer_id}&callId=${data.call_id}`;
        };
    }
    modal.style.display = 'flex';
}

/* ============================================================
   PIN Modal controls
   ============================================================ */
function openPinModal(isForCall = false) {
    document.getElementById('pinModal').style.display = 'flex';
    document.getElementById('pinDigit1').focus();

    const pinTitle = document.querySelector('#pinModal h3');
    const pinDesc  = document.querySelector('#pinModal p');
    if (isForCall) {
        if (pinTitle) pinTitle.innerText = "Incoming Call Locked";
        if (pinDesc)  pinDesc.innerText  = "Enter your 4-digit corporate PIN to unlock and answer the call.";
    } else {
        if (pinTitle) pinTitle.innerText = "Unlock Chat Content";
        if (pinDesc)  pinDesc.innerText  = "Enter your 4-digit corporate PIN to unlock camouflaged messages.";
    }
}

function closePinModal() {
    document.getElementById('pinModal').style.display = 'none';
    for (let i = 1; i <= 4; i++) document.getElementById('pinDigit' + i).value = '';
}

function movePinCursor(input, nextIndex) {
    input.value = input.value.replace(/[^0-9]/g, '');
    if (input.value.length === 1 && nextIndex <= 4) {
        const next = document.getElementById('pinDigit' + nextIndex);
        if (next) next.focus();
    }
}

function submitPinModal() {
    let pin = '';
    for (let i = 1; i <= 4; i++) pin += document.getElementById('pinDigit' + i).value;
    if (pin.length === 4) {
        submitPin(pin);
    } else {
        showToast('Please enter a complete 4-digit PIN.', 'warning');
    }
}

/* ============================================================
   Chat Messaging & Rendering
   ============================================================ */
function setupChatInput() {
    const input   = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendMsgBtn');
    if (!input || !sendBtn) return;

    const handleSend = () => {
        const text = input.value.trim();
        if (text === '') return;

        if (/^\d{4}$/.test(text)) {
            submitPin(text);
            input.value = '';
            return;
        }

        sendMessageToServer('text', text);
        input.value = '';
    };

    sendBtn.addEventListener('click', handleSend);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleSend(); });

    let lastTypingTime = 0;
    input.addEventListener('input', function() {
        const now = Date.now();
        if (now - lastTypingTime > 3000) {
            lastTypingTime = now;
            const fd = new FormData();
            fd.append('chat_id', ACTIVE_CHAT_ID);
            fd.append('typing', 'true');
            fetch(`${BASE_URL}/api/chats/typing`, { method: 'POST', body: fd })
                .catch(() => {});
        }
    });
}

function sendMessageToServer(type, content, filePath = null) {
    const fd = new FormData();
    fd.append('message_type', type);
    fd.append('content', content);
    if (filePath) fd.append('file_path', filePath);

    fetch(`${BASE_URL}/api/messages/${ACTIVE_CHAT_ID}`, { method: 'POST', body: fd })
    .then(res => res.json())
    .then(msg => {
        if (msg.limit_hit) {
            showToast(`Daily ${msg.limit_type} limit reached. Upgrade your plan to send more.`, 'error', 6000);
            showUpgradeLimitBanner(msg.limit_type, msg.summary);
        } else if (msg.error) {
            showToast(msg.error, 'error');
        } else {
            appendMessageToDom(msg);
        }
    })
    .catch(() => showToast('Failed to send message.', 'error'));
}

function loadChatMessages() {
    fetch(`${BASE_URL}/api/messages/${ACTIVE_CHAT_ID}`)
    .then(res => res.json())
    .then(data => {
        isPinUnlocked = data.pin_verified;
        updateSendButtonColor();

        if (data.partner_status) {
            window.chatPartnerStatus   = data.partner_status;
            window.partnerLastSeenTime = data.partner_last_seen;
            updatePresenceUI();
            startPresenceTicker();
            // For group chats the status contains online count — show it immediately in header
            if (!data.partner_last_seen) {
                const statusEl = document.getElementById('activeChatStatus');
                if (statusEl) { statusEl.textContent = data.partner_status; statusEl.style.color = '#22c55e'; }
            }
        } else {
            window.partnerLastSeenTime = null;
            if (window.presenceInterval) { clearInterval(window.presenceInterval); window.presenceInterval = null; }
        }

        const container = document.getElementById('messagesContainer');
        container.innerHTML = '';
        data.messages.forEach(msg => appendMessageToDom(msg));
        container.scrollTop = container.scrollHeight;
    })
    .catch(() => showToast('Failed to load messages.', 'error'));
}

function appendMessageToDom(msg) {
    try {
        const vanished = JSON.parse(localStorage.getItem('vanished_messages') || '[]');
        if (vanished.includes(msg.id)) return;
    } catch (e) {}

    const container = document.getElementById('messagesContainer');
    if (!container || document.querySelector(`[data-msg-id="${msg.id}"]`)) return;

    const isSent = (parseInt(msg.sender_id) === CURRENT_USER_ID);
    const bubble = document.createElement('div');
    bubble.className = `message-bubble ${isSent ? 'message-sent' : 'message-received'} ${isPinUnlocked ? 'clickable' : 'locked'} media-type-${msg.message_type}`;
    bubble.setAttribute('data-msg-id', msg.id);
    bubble.setAttribute('data-true-type', msg.message_type);
    bubble.setAttribute('data-true-content', msg.content);
    bubble.setAttribute('data-file-path', msg.file_path || '');

    bubble.addEventListener('click', function(e) {
        e.stopPropagation();
        if (isPinUnlocked) revealMessage(this);
    });

    const senderName   = isSent ? 'You' : _esc(msg.sender_name || 'Unknown User');
    const dateStr      = msg.created_at.replace(/-/g, '/') + ' UTC';
    const timeFormatted = new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Dhaka' });

    bubble.innerHTML = `
        ${!isSent ? `<span class="sender-name-label">${senderName}</span>` : ''}
        <span class="message-body-text">
            <span class="camouflage-text">${getCamouflageText(msg.id)}</span>
        </span>
        <span class="message-time">
            ${timeFormatted}
            ${isSent ? `<i class="fa-solid fa-check-double message-status-tick"></i>` : ''}
        </span>
        <div class="vanish-timer-bar"></div>
    `;

    container.appendChild(bubble);
    container.scrollTop = container.scrollHeight;

    if (isSent) triggerLocalVanish(msg.id);
}

/* ============================================================
   Vanish / Reveal
   ============================================================ */
let currentlyRevealedMsgId = null;
let activeVanishDetails = {};

function revealMessage(bubble) {
    const msgId      = bubble.getAttribute('data-msg-id');
    const trueType   = bubble.getAttribute('data-true-type');
    const trueContent = bubble.getAttribute('data-true-content');
    const filePath   = bubble.getAttribute('data-file-path');
    const textSpan   = bubble.querySelector('.message-body-text');

    if (currentlyRevealedMsgId === msgId) {
        if (trueType === 'image') extendOrUpdateVanishTimer(msgId, 5, true);
        return;
    }

    hideRevealedMessage();

    // Build the media URL
    const mediaUrl = `${BASE_URL}${filePath}`;
    let html = '';
    if (trueType === 'image') {
        html = `<div class="message-media-container">
            <img src="${mediaUrl}" alt="Image" loading="lazy"
                 style="max-width:100%;border-radius:8px;display:block;">
        </div>`;
    } else if (trueType === 'video') {
        // playsinline is REQUIRED for iOS Safari to play inline (not fullscreen-force)
        // <source> with explicit type helps Safari pick the right decoder faster
        html = `<div class="message-media-container">
            <video controls autoplay playsinline
                   style="max-width:100%;border-radius:8px;display:block;">
                <source src="${mediaUrl}" type="${_pathMime(filePath, 'video')}">
                <a href="${mediaUrl}" download style="color:var(--accent);">
                    <i class="fa-solid fa-download"></i> Download video
                </a>
            </video>
        </div>`;
    } else if (trueType === 'audio') {
        html = `<audio class="audio-player" controls autoplay
                       style="width:100%;min-width:200px;">
            <source src="${mediaUrl}" type="${_pathMime(filePath, 'audio')}">
            <a href="${mediaUrl}" download style="color:var(--accent);">
                <i class="fa-solid fa-download"></i> Download audio
            </a>
        </audio>`;
    } else if (trueType === 'file') {
        html = `<a href="${mediaUrl}" class="message-file-link" download>
            <i class="fa-solid fa-file-arrow-down"></i> ${_esc(trueContent || 'Attachment')}
        </a>`;
    } else {
        // Text message — linkify converts URLs to <a> tags and escapes everything else
        html = `<span style="white-space:pre-wrap;word-break:break-word;">${_linkify(trueContent)}</span>`;
    }

    textSpan.innerHTML = html;
    currentlyRevealedMsgId = msgId;

    if (activeVanishDetails[msgId]) return; // timer already running

    let duration = 5;
    if (trueType === 'image') {
        duration = 10;
    } else if (trueType === 'video' || trueType === 'audio') {
        duration = 20;
        setTimeout(() => {
            const mediaEl = textSpan.querySelector('video, audio');
            if (mediaEl) {
                const setDuration = () => {
                    const d = Math.ceil(mediaEl.duration * 2);
                    if (d > 0 && !isNaN(d)) extendOrUpdateVanishTimer(msgId, d, false);
                };
                if (mediaEl.readyState >= 1) setDuration();
                else mediaEl.addEventListener('loadedmetadata', setDuration);
            }
        }, 50);
    } else {
        const words = (trueContent || '').trim().split(/\s+/).filter(w => w.length > 0);
        duration = words.length <= 1 ? 3 : 3 + Math.floor((words.length - 1) / 2);
    }

    startVanishTimer(msgId, duration);
}

function startVanishTimer(msgId, durationSeconds) {
    if (activeVanishTimers[msgId]) clearTimeout(activeVanishTimers[msgId]);

    const bubble   = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (!bubble) return;
    const timerBar = bubble.querySelector('.vanish-timer-bar');
    if (timerBar) {
        timerBar.style.transition = 'none';
        timerBar.style.width = '0%';
        timerBar.offsetHeight;
        timerBar.style.transition = `width ${durationSeconds}s linear`;
        timerBar.style.width = '100%';
    }

    activeVanishDetails[msgId] = { totalTime: durationSeconds, startTimestamp: Date.now() };
    activeVanishTimers[msgId]  = setTimeout(() => executeVanishDelete(msgId), durationSeconds * 1000);
}

function extendOrUpdateVanishTimer(msgId, amount, isExtension = false) {
    const detail = activeVanishDetails[msgId];
    if (!detail) return;
    const bubble   = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (!bubble) return;
    const timerBar = bubble.querySelector('.vanish-timer-bar');

    const elapsed = (Date.now() - detail.startTimestamp) / 1000;
    let newRemainingTime = isExtension
        ? Math.max(0, detail.totalTime - elapsed) + amount
        : amount;

    let currentPct = 0;
    if (timerBar) {
        const bw = timerBar.getBoundingClientRect().width;
        const pw = bubble.getBoundingClientRect().width;
        currentPct = pw > 0 ? bw / pw : elapsed / detail.totalTime;
    } else {
        currentPct = elapsed / detail.totalTime;
    }
    currentPct = Math.min(0.99, Math.max(0, currentPct));

    const virtualDuration = newRemainingTime / (1 - currentPct);
    if (activeVanishTimers[msgId]) clearTimeout(activeVanishTimers[msgId]);

    detail.totalTime       = virtualDuration;
    detail.startTimestamp  = Date.now() - (currentPct * virtualDuration * 1000);

    if (timerBar) {
        timerBar.style.transition = 'none';
        timerBar.style.width = (currentPct * 100) + '%';
        timerBar.offsetHeight;
        timerBar.style.transition = `width ${virtualDuration}s linear`;
        timerBar.style.width = '100%';
    }

    activeVanishTimers[msgId] = setTimeout(() => executeVanishDelete(msgId), newRemainingTime * 1000);
}

function hideRevealedMessage() {
    if (!currentlyRevealedMsgId) return;
    const msgId = currentlyRevealedMsgId;
    currentlyRevealedMsgId = null;
    const bubble = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (bubble) {
        const textSpan = bubble.querySelector('.message-body-text');
        if (textSpan) textSpan.innerHTML = `<span class="camouflage-text">${getCamouflageText(msgId)}</span>`;
    }
}

function executeVanishDelete(msgId) {
    const targetBubble = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (targetBubble) {
        targetBubble.style.transition = 'opacity 0.5s ease';
        targetBubble.style.opacity = '0';
        setTimeout(() => targetBubble.remove(), 500);
    }

    markMessageAsVanishedLocally(msgId);

    fetch(`${BASE_URL}/api/messages/delete/${ACTIVE_CHAT_ID}/${msgId}`, { method: 'POST' })
    .then(res => res.json())
    .then(() => {})
    .catch(() => {})
    .finally(() => {
        delete activeVanishTimers[msgId];
        delete activeVanishDetails[msgId];
        if (currentlyRevealedMsgId === msgId) currentlyRevealedMsgId = null;
    });
}

function triggerLocalVanish(msgId) {
    if (activeVanishTimers[msgId]) return;

    const bubble = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (bubble) {
        const timerBar = bubble.querySelector('.vanish-timer-bar');
        if (timerBar) {
            timerBar.style.transition = 'none';
            timerBar.style.width = '0%';
            timerBar.offsetHeight;
            timerBar.style.transition = 'width 10s linear';
            timerBar.style.width = '100%';
        }
    }

    activeVanishTimers[msgId] = setTimeout(() => {
        const tb = document.querySelector(`[data-msg-id="${msgId}"]`);
        if (tb) {
            tb.style.transition = 'opacity 0.5s ease';
            tb.style.opacity = '0';
            setTimeout(() => tb.remove(), 500);
        }
        markMessageAsVanishedLocally(msgId);
        // Don't delete from server here — let the receiver's reveal-vanish timer trigger the server deletion
        delete activeVanishTimers[msgId];
    }, 10000);
}

/* ============================================================
   File Upload Handling
   ============================================================ */
function setupAttachments() {
    const toggleBtn = document.getElementById('attachToggleBtn');
    const menu      = document.getElementById('attachMenu');
    if (!toggleBtn || !menu) return;

    toggleBtn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('show'); });
    document.addEventListener('click', () => menu.classList.remove('show'));
}

let currentUploadFileType = 'file';

function triggerFileSelect(type) {
    currentUploadFileType = type;
    const fileInput = document.getElementById('fileAttachInput');
    fileInput.accept = type === 'image' ? 'image/*' : type === 'video' ? 'video/*' : '*/*';
    fileInput.click();
}

function handleFileSelected(input) {
    if (input.files.length === 0) return;
    const file = input.files[0];

    const ext       = file.name.split('.').pop().toLowerCase();
    const imgExts   = ['jpg','jpeg','png','gif','webp','bmp'];
    const vidExts   = ['mp4','webm','ogg','mov','avi','mkv'];
    const mimePrefix = (file.type || '').split('/')[0];
    let allowed = false;

    if (currentUploadFileType === 'image'  && (mimePrefix === 'image' || imgExts.includes(ext)))  allowed = true;
    if (currentUploadFileType === 'video'  && (mimePrefix === 'video' || vidExts.includes(ext)))  allowed = true;

    if (!allowed) {
        showToast('Upload blocked: Only images and videos are allowed.', 'error');
        input.value = '';
        return;
    }

    uploadFileInChunks(file);
    input.value = '';
}

let voiceRecordInterval = null;

function uploadFileInChunks(file) {
    const CHUNK_SIZE  = 1024 * 1024;
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const fileUuid    = 'upload_' + Math.random().toString(36).substring(2, 15);

    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar       = document.getElementById('uploadProgressBar');
    const progressStats     = document.getElementById('uploadProgressStats');
    const progressTime      = document.getElementById('uploadProgressTime');

    if (progressContainer) {
        progressContainer.style.display = 'block';
        if (progressBar)   progressBar.style.width = '0%';
        if (progressStats) progressStats.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" style="color:#00f2fe;"></i> 0.0 / ${(file.size/(1024*1024)).toFixed(1)} MB (0%)`;
        if (progressTime)  progressTime.innerHTML  = `<i class="fa-regular fa-clock"></i> Calculating...`;
    }

    let chunkIndex     = 0;
    const uploadStart  = Date.now();

    const uploadNextChunk = () => {
        const start     = chunkIndex * CHUNK_SIZE;
        const end       = Math.min(start + CHUNK_SIZE, file.size);
        const chunkBlob = file.slice(start, end);

        const fd = new FormData();
        fd.append('file_uuid',    fileUuid);
        fd.append('chunk_index',  chunkIndex);
        fd.append('total_chunks', totalChunks);
        fd.append('file_name',    file.name);
        fd.append('file_data',    chunkBlob, file.name);

        fetch(`${BASE_URL}/api/messages/upload-chunk`, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast('Chunk upload failed: ' + (data.error || 'Unknown error'), 'error');
                if (progressContainer) progressContainer.style.display = 'none';
                return;
            }

            if (data.completed) {
                if (progressContainer) progressContainer.style.display = 'none';
                sendMessageToServer(currentUploadFileType, file.name, data.file_path);
            } else {
                chunkIndex++;
                if (progressBar) progressBar.style.width = data.progress + '%';

                const elapsed       = (Date.now() - uploadStart) / 1000;
                const uploadedBytes = end;
                const speed         = uploadedBytes / (elapsed || 0.1);
                const remaining     = Math.ceil((file.size - uploadedBytes) / speed);
                const timeText      = remaining > 0
                    ? (remaining > 60 ? `~${Math.floor(remaining/60)}m ${remaining%60}s left` : `~${remaining}s left`)
                    : 'Finishing...';

                if (progressStats) progressStats.innerHTML = `<i class="fa-solid fa-cloud-arrow-up" style="color:#00f2fe;"></i> <span>${(uploadedBytes/(1024*1024)).toFixed(1)} / ${(file.size/(1024*1024)).toFixed(1)} MB (${Math.round(data.progress)}%)</span>`;
                if (progressTime)  progressTime.innerHTML  = `<i class="fa-regular fa-clock"></i> <span>${timeText}</span>`;
                uploadNextChunk();
            }
        })
        .catch(() => {
            if (progressContainer) progressContainer.style.display = 'none';
            showToast('Upload failed. Please try again.', 'error');
        });
    };

    uploadNextChunk();
}

/* ============================================================
   Voice Recording
   ============================================================ */
let voiceMediaRecorder = null;
let voiceChunks = [];
let voiceStream = null;

function triggerAudioRecord() {
    const menu = document.getElementById('attachMenu');
    if (menu) menu.classList.remove('show');
    const vrContainer = document.getElementById('voiceRecordContainer');
    if (vrContainer) { vrContainer.style.display = 'flex'; resetVoiceRecordingUI(); }
}

function resetVoiceRecordingUI() {
    document.getElementById('recordingStatusText').innerText = 'Voice Recorder (Ready)';
    document.getElementById('recordingTimerHMS').innerText  = '00:00:00';
    document.getElementById('recordingDot').style.display   = 'none';
    document.getElementById('audioWaveAnim').style.display  = 'none';
    document.getElementById('btnStartRecord').style.display = 'inline-flex';
    const stopBtn = document.getElementById('btnStopSendRecord');
    stopBtn.style.display = 'none';
    stopBtn.disabled = true;
}

function formatHMS(s) {
    const h = String(Math.floor(s/3600)).padStart(2,'0');
    const m = String(Math.floor((s%3600)/60)).padStart(2,'0');
    const sec = String(s%60).padStart(2,'0');
    return `${h}:${m}:${sec}`;
}

function startVoiceRecordingAction() {
    navigator.mediaDevices.getUserMedia({ audio: true })
    .then(stream => {
        voiceStream = stream;
        voiceChunks = [];
        const voiceMime = _getRecordingMime('audio');
        voiceMediaRecorder = new MediaRecorder(stream, voiceMime ? { mimeType: voiceMime } : {});

        voiceMediaRecorder.ondataavailable = e => voiceChunks.push(e.data);
        voiceMediaRecorder.onstop = function() {
            if (voiceStream) { voiceStream.getTracks().forEach(t => t.stop()); voiceStream = null; }
            if (voiceRecordInterval) { clearInterval(voiceRecordInterval); voiceRecordInterval = null; }
            document.getElementById('voiceRecordContainer').style.display = 'none';
            const usedMime = voiceMediaRecorder.mimeType || voiceMime || 'audio/webm';
            const ext      = _mimeToExt(usedMime);
            const blob = new Blob(voiceChunks, { type: usedMime });
            const file = new File([blob], `voice_record_${Date.now()}.${ext}`, { type: usedMime });
            currentUploadFileType = 'audio';
            uploadFileInChunks(file);
        };

        document.getElementById('recordingStatusText').innerText = 'Recording...';
        document.getElementById('recordingDot').style.display    = 'inline-block';
        document.getElementById('audioWaveAnim').style.display   = 'flex';
        document.getElementById('btnStartRecord').style.display  = 'none';
        const stopBtn = document.getElementById('btnStopSendRecord');
        stopBtn.style.display = 'inline-flex';
        stopBtn.disabled = false;

        let elapsed = 0;
        document.getElementById('recordingTimerHMS').innerText = formatHMS(0);
        if (voiceRecordInterval) clearInterval(voiceRecordInterval);
        voiceRecordInterval = setInterval(() => {
            elapsed++;
            document.getElementById('recordingTimerHMS').innerText = formatHMS(elapsed);
        }, 1000);

        voiceMediaRecorder.start();
    })
    .catch(err => showToast('Unable to access microphone: ' + err.message, 'error'));
}

function stopAndSendVoiceRecordingAction() {
    if (voiceMediaRecorder && voiceMediaRecorder.state === "recording") voiceMediaRecorder.stop();
}

function cancelVoiceRecordingAction() {
    if (voiceMediaRecorder && voiceMediaRecorder.state === "recording") {
        voiceMediaRecorder.onstop = null;
        voiceMediaRecorder.stop();
    }
    if (voiceStream) { voiceStream.getTracks().forEach(t => t.stop()); voiceStream = null; }
    if (voiceRecordInterval) { clearInterval(voiceRecordInterval); voiceRecordInterval = null; }
    document.getElementById('voiceRecordContainer').style.display = 'none';
    resetVoiceRecordingUI();
}

/* ============================================================
   Camera Capture
   ============================================================ */
let cameraStream = null;
let videoMediaRecorder = null;
let videoChunks = [];
let videoRecordTimerInterval = null;

function openCameraModal() {
    document.getElementById('cameraModal').style.display = 'flex';
    navigator.mediaDevices.getUserMedia({ video: true, audio: true })
    .then(stream => {
        cameraStream = stream;
        document.getElementById('cameraPreview').srcObject = stream;
    })
    .catch(err => {
        showToast('Unable to access camera: ' + err.message, 'error');
        closeCameraModal();
    });
}

function closeCameraModal() {
    if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    if (videoRecordTimerInterval) { clearInterval(videoRecordTimerInterval); videoRecordTimerInterval = null; }
    document.getElementById('cameraModal').style.display = 'none';
    document.getElementById('cameraRecordingTimer').style.display = 'none';
    const recordBtn = document.getElementById('btnRecordVideo');
    if (recordBtn) { recordBtn.innerHTML = `<i class="fa-solid fa-video"></i> Start Record`; recordBtn.className = 'btn btn-accent'; }
}

function capturePhoto() {
    const video  = document.getElementById('cameraPreview');
    const canvas = document.createElement('canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(blob => {
        const file = new File([blob], `photo_${Date.now()}.png`, { type: 'image/png' });
        currentUploadFileType = 'image';
        closeCameraModal();
        uploadFileInChunks(file);
    }, 'image/png');
}

function toggleVideoRecording() {
    const recordBtn = document.getElementById('btnRecordVideo');
    if (videoMediaRecorder && videoMediaRecorder.state === 'recording') { videoMediaRecorder.stop(); return; }

    videoChunks       = [];
    const camMime     = _getRecordingMime('video');
    videoMediaRecorder = new MediaRecorder(cameraStream, camMime ? { mimeType: camMime } : {});
    videoMediaRecorder.ondataavailable = e => videoChunks.push(e.data);

    const timerEl = document.getElementById('cameraRecordingTimer');
    timerEl.style.display = 'block';
    let elapsed = 0;
    if (videoRecordTimerInterval) clearInterval(videoRecordTimerInterval);
    videoRecordTimerInterval = setInterval(() => {
        elapsed++;
        const m = String(Math.floor(elapsed/60)).padStart(2,'0');
        const s = String(elapsed%60).padStart(2,'0');
        timerEl.innerText = `REC ${m}:${s}`;
    }, 1000);

    videoMediaRecorder.onstop = function() {
        if (videoRecordTimerInterval) { clearInterval(videoRecordTimerInterval); videoRecordTimerInterval = null; }
        timerEl.style.display = 'none';
        const usedCamMime = videoMediaRecorder.mimeType || camMime || 'video/mp4';
        const camExt      = _mimeToExt(usedCamMime);
        const blob = new Blob(videoChunks, { type: usedCamMime });
        const file = new File([blob], `video_capture_${Date.now()}.${camExt}`, { type: usedCamMime });
        currentUploadFileType = 'video';
        closeCameraModal();
        uploadFileInChunks(file);
    };

    videoMediaRecorder.start();
    recordBtn.innerHTML  = `<i class="fa-solid fa-square"></i> Stop Record`;
    recordBtn.className = 'btn btn-danger';
}

/* ============================================================
   Group / Chat helpers
   ============================================================ */
function startDirectChat(partnerId) {
    const fd = new FormData();
    fd.append('partner_id', partnerId);
    fetch(`${BASE_URL}/api/chats/create`, { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (data.success) { navigateToChat(data.chat_id); }
        else { showToast('Failed to start chat.', 'error'); }
    })
    .catch(() => showToast('Network error starting chat.', 'error'));
}

function promptNickname(type, targetId, currentName) {
    showPrompt(`Assign local UI nickname for: "${currentName}"`, currentName, (nickname) => {
        if (!nickname) return;
        const fd = new FormData();
        fd.append('target_type', type);
        fd.append('target_id',   targetId);
        fd.append('nickname',    nickname);
        fetch(`${BASE_URL}/api/chats/nickname`, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { showToast('Failed to save nickname.', 'error'); }
        })
        .catch(() => showToast('Network error saving nickname.', 'error'));
    });
}

function navigateToChat(chatId) {
    // Clear "New Message" state before navigating so the ticker can resume normally
    const previewEl = document.getElementById(`preview-${chatId}`);
    if (previewEl) previewEl.removeAttribute('data-new-msg');
    window.location.href = `${BASE_URL}/chat/${chatId}`;
}
function backToSidebar()         { window.location.href = `${BASE_URL}/dashboard`; }

function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function filterUsersList() {
    const val = document.getElementById('userSearchInput').value.toLowerCase();
    document.querySelectorAll('.user-item-select').forEach(item => {
        const name  = item.getAttribute('data-name');
        const email = item.getAttribute('data-email');
        item.style.display = (name.includes(val) || email.includes(val)) ? 'flex' : 'none';
    });
}

/* ============================================================
   WebRTC Call Initiation
   ============================================================ */
function initiateCall(type) {
    let isPartnerOnline = false;
    if (window.partnerLastSeenTime) {
        let ts = parseInt(window.partnerLastSeenTime);
        if (isNaN(ts)) ts = Math.floor(new Date(window.partnerLastSeenTime.replace(/-/g, '/')).getTime() / 1000);
        isPartnerOnline = (Math.floor(Date.now() / 1000) - ts < 30);
    }

    if (!isPartnerOnline) {
        showToast('User is currently offline. A missed-call note will be sent.', 'warning');
        sendMessageToServer('text', `Missed ${type} call`);
        return;
    }

    window.location.href = `${BASE_URL}/call?chatId=${ACTIVE_CHAT_ID}&type=${type}&mode=outgoing`;
}

/* ============================================================
   Presence Ticker (chat header "Online / Last seen X ago")
   ============================================================ */
window.presenceInterval = null;

function startPresenceTicker() {
    if (window.presenceInterval) clearInterval(window.presenceInterval);
    window.presenceInterval = setInterval(updatePresenceUI, 1000);
}

function formatCleanRelativeTime(diffSeconds) {
    const h = Math.floor(diffSeconds / 3600);
    const m = Math.floor((diffSeconds % 3600) / 60);
    const s = Math.floor(diffSeconds % 60);
    let parts = [];
    if (h > 0) parts.push(`${h}h`);
    if (m > 0) parts.push(`${m}m`);
    if (h === 0 && s > 0) parts.push(`${s}s`);
    return parts.length ? parts.join(' ') + ' ago' : 'now';
}

function updatePresenceUI() {
    // 1. Sidebar dots & preview text
    document.querySelectorAll('.chat-preview[data-chat-type="direct"]').forEach(previewEl => {
        const lastSeen = previewEl.getAttribute('data-last-seen');
        if (!lastSeen || lastSeen === 'null') return;

        // Don't overwrite a "New Message" indicator — let the user see it
        if (previewEl.getAttribute('data-new-msg') === '1') return;

        if (previewEl.getAttribute('data-typing-active') === 'true') {
            const chatId = previewEl.id.replace('preview-', '');
            const dotEl  = document.getElementById(`dot-${chatId}`);
            if (dotEl) { dotEl.className = 'presence-ring online'; }
            return;
        }

        let ts = Number(lastSeen);
        if (isNaN(ts) || String(lastSeen).includes('-') || String(lastSeen).includes(':')) {
            ts = Math.floor(new Date(String(lastSeen).replace(/-/g, '/')).getTime() / 1000);
        }

        const nowSecs     = Math.floor(Date.now() / 1000);
        const diffSeconds = Math.max(0, nowSecs - ts);
        const isOnline    = diffSeconds < 30;
        const rel         = formatCleanRelativeTime(diffSeconds);

        previewEl.innerHTML = `<span style="font-size:0.75rem;color:${isOnline ? '#22c55e' : 'var(--text-secondary)'};">${isOnline ? 'Online' : 'Offline'} (${rel})</span>`;

        const chatId = previewEl.id.replace('preview-', '');
        const dotEl  = document.getElementById(`dot-${chatId}`);
        if (dotEl) dotEl.className = `presence-ring${isOnline ? ' online' : ''}`;
    });

    // 2. Active chat header
    if (window.partnerLastSeenTime) {
        let ts = Number(window.partnerLastSeenTime);
        if (isNaN(ts) || String(window.partnerLastSeenTime).includes('-') || String(window.partnerLastSeenTime).includes(':')) {
            ts = Math.floor(new Date(String(window.partnerLastSeenTime).replace(/-/g, '/')).getTime() / 1000);
        }

        const diffSeconds = Math.max(0, Math.floor(Date.now() / 1000) - ts);
        const isOnline    = diffSeconds < 30;
        const rel         = formatCleanRelativeTime(diffSeconds);
        const statusText  = `${isOnline ? 'Online' : 'Offline'} (${rel})`;
        const statusColor = isOnline ? '#22c55e' : '#ef4444';

        window.chatPartnerStatus = statusText;
        window.chatPartnerColor  = statusColor;

        const statusEl = document.getElementById('activeChatStatus');
        if (statusEl && !statusEl.innerText.includes('Typing...')) {
            statusEl.innerText  = statusText;
            statusEl.style.color = statusColor;
        }
    }
}

// Boot presence ticker on load
startPresenceTicker();

/* ============================================================
   PLAN / USAGE SYSTEM
   ============================================================ */

let _planSummary      = null;
let _planPollInterval = null;

function startPlanPolling() {
    if (_planPollInterval) return;
    fetchPlanStatus();
    _planPollInterval = setInterval(fetchPlanStatus, 60000); // refresh every minute
}

function fetchPlanStatus() {
    fetch(`${BASE_URL}/api/plan/status`)
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        _planSummary = data.summary;
        renderUsageFooter(data.summary);
        checkWarnThresholds(data.summary);
    })
    .catch(() => {});
}

const USAGE_LABELS = {
    text:       ['Text', 'msgs'],
    image:      ['Images', ''],
    video:      ['Videos', ''],
    audio:      ['Audios', ''],
    audio_call: ['Audio Call', 'min'],
    video_call: ['Video Call', 'min'],
};

// Inject chip styles once
/* ── Plan metric definitions (icon, tooltip label, isCall) ─── */
const PLAN_METRICS = [
    { key: 'text',       icon: 'fa-message',   tip: 'Messages',   isCall: false },
    { key: 'image',      icon: 'fa-image',      tip: 'Images',     isCall: false },
    { key: 'video',      icon: 'fa-film',       tip: 'Videos',     isCall: false },
    { key: 'audio',      icon: 'fa-microphone', tip: 'Voice msgs', isCall: false },
    { key: 'audio_call', icon: 'fa-phone',      tip: 'Audio call', isCall: true  },
    { key: 'video_call', icon: 'fa-video',      tip: 'Video call', isCall: true  },
];

/* Format call minutes → { used:'45m', limit:'3h' } */
function _fmtCall(usedMin, limitMin) {
    const lh = Math.floor(limitMin / 60);
    if (usedMin < 60)  return { used: `${usedMin}m`, limit: `${lh}h` };
    const h = Math.floor(usedMin / 60), m = usedMin % 60;
    return { used: m ? `${h}h${m}m` : `${h}h`, limit: `${lh}h` };
}

/* Calculate overall % = average of all capped metric percentages */
function _overallPct(types) {
    const active = Object.values(types).filter(t => t.limit !== null);
    if (!active.length) return 0;
    return Math.round(active.reduce((s, t) => s + Math.min(100, t.pct), 0) / active.length);
}

/* ── Toggle detail rows ──────────────────────────────────── */
function togglePlanDetails() {
    const list = document.getElementById('planUsageBars');
    const btn  = document.getElementById('planOverallBar');
    if (!list) return;
    const opening = list.style.display === 'none';
    list.style.display = opening ? 'flex' : 'none';
    if (btn) btn.setAttribute('aria-expanded', opening);
}

function renderUsageFooter(summary) {
    const bar      = document.getElementById('planUsageBar');
    const badge    = document.getElementById('planBadge');
    const listEl   = document.getElementById('planUsageBars');
    const expiryEl = document.getElementById('planExpiry');
    const fillEl   = document.getElementById('planOverallFill');
    const pctEl    = document.getElementById('planOverallPct');
    if (!bar || !badge || !listEl) return;

    const plan = summary.plan;
    const PLAN_COLORS = { trial: '#f59e0b', heavy: '#3b82f6', unlimited: '#22c55e' };
    const pc = PLAN_COLORS[plan.plan_name] || '#8696a0';

    /* ── Badge pill ─────────────────────────────────────────── */
    badge.textContent = (plan.plan_label || plan.plan_name).toUpperCase();
    badge.style.cssText = `background:${pc}1a;color:${pc};border:1px solid ${pc}44;`;
    bar.style.display   = 'block';

    /* ── Expiry (stacked under badge) ───────────────────────── */
    if (plan.expires_at) {
        const d   = Math.max(0, Math.ceil((plan.expires_at - Math.floor(Date.now() / 1000)) / 86400));
        const col = d <= 3 ? '#ff4d4d' : '#f59e0b';
        expiryEl.innerHTML     = `<i class="fa-regular fa-clock"></i> ${d}d left`;
        expiryEl.style.color   = col;
        expiryEl.style.display = 'flex';
    } else {
        expiryEl.style.display = 'none';
    }

    /* ── Unlimited: ∞ overall bar + usage counts per metric (no limits) ─ */
    if (plan.plan_name === 'unlimited') {
        if (fillEl) { fillEl.style.width = '0%'; fillEl.style.background = pc; }
        if (pctEl)  { pctEl.textContent = '∞'; pctEl.style.color = pc; }

        listEl.innerHTML = '';
        const uTypes = summary.types || {};
        PLAN_METRICS.forEach(({ key, icon, tip, isCall }) => {
            const info = uTypes[key];
            if (!info) return;

            let usedStr;
            if (isCall) {
                const h = Math.floor(info.used / 60), m = info.used % 60;
                usedStr = h > 0 ? `${h}h ${m}m` : `${m}m`;
            } else {
                usedStr = info.used.toLocaleString();
            }

            const row = document.createElement('div');
            row.className = 'plan-metric';
            row.title = `${tip}: ${usedStr} used today`;
            row.innerHTML =
                `<i class="fa-solid ${icon} plan-metric-icon" style="color:${pc};"></i>
                 <div class="plan-metric-track">
                     <div class="plan-metric-fill" style="width:0%;background:${pc};opacity:.2;"></div>
                 </div>
                 <div class="plan-metric-val" style="color:${pc};">
                     ${usedStr}<span class="pmv-sep">/</span><span class="pmv-limit" style="opacity:.35;">∞</span>
                 </div>`;
            listEl.appendChild(row);
        });
        return;
    }

    /* ── Overall bar ────────────────────────────────────────── */
    const types  = summary.types || {};
    const ovPct  = _overallPct(types);
    const ovColor = ovPct <= 50 ? '#22c55e' : (ovPct <= 80 ? '#f59e0b' : '#ef4444');

    if (fillEl) {
        fillEl.style.width      = ovPct + '%';
        fillEl.style.background = ovColor;
    }
    if (pctEl) {
        pctEl.textContent  = ovPct + '%';
        pctEl.style.color  = ovColor;
    }

    /* ── Detail rows (icon + bar + value, NO label text) ───── */
    listEl.innerHTML = '';

    PLAN_METRICS.forEach(({ key, icon, tip, isCall }) => {
        const info = types[key];
        if (!info || info.limit === null) return;

        const pct      = Math.min(100, info.pct);
        const barColor = pct <= 50 ? '#22c55e' : (pct <= 80 ? '#f59e0b' : '#ef4444');

        let usedStr, limitStr;
        if (isCall) {
            const f = _fmtCall(info.used, info.limit);
            usedStr = f.used; limitStr = f.limit;
        } else {
            usedStr  = info.used.toLocaleString();
            limitStr = Number(info.limit).toLocaleString();
        }

        const row = document.createElement('div');
        row.className = 'plan-metric';
        row.title     = `${tip}: ${usedStr} / ${limitStr} today`;
        row.innerHTML =
            `<i class="fa-solid ${icon} plan-metric-icon" style="color:${barColor};"></i>
             <div class="plan-metric-track">
                 <div class="plan-metric-fill" style="width:${pct}%;background:${barColor};"></div>
             </div>
             <div class="plan-metric-val" style="color:${barColor};">
                 ${usedStr}<span class="pmv-sep">/</span><span class="pmv-limit">${limitStr}</span>
             </div>`;
        listEl.appendChild(row);
    });
}

let _warnedTypes = new Set();

function checkWarnThresholds(summary) {
    const types = summary.types || {};
    for (const [key, info] of Object.entries(types)) {
        if (info.limit === null) continue;
        if (info.blocked && !_warnedTypes.has(key + '_blocked')) {
            _warnedTypes.add(key + '_blocked');
            const [label] = USAGE_LABELS[key] || [key];
            showToast(`${label} daily limit reached. Upgrade to continue.`, 'error', 7000);
        } else if (info.warn && !info.blocked && !_warnedTypes.has(key + '_warn')) {
            _warnedTypes.add(key + '_warn');
            const [label] = USAGE_LABELS[key] || [key];
            const remaining = info.remaining;
            showToast(`⚠ ${label}: only ${remaining} remaining today.`, 'warning', 5000);
        }
    }
}

function showUpgradeLimitBanner(limitType, summary) {
    if (summary) renderUsageFooter(summary);
    openUpgradeModal();
}

/* ============================================================
   UPGRADE REQUEST MODAL
   ============================================================ */

function openUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (!modal) return;
    modal.style.display = 'flex';
    renderUpgradePlanCards();

    // Show existing request status if any
    fetch(`${BASE_URL}/api/plan/status`)
    .then(r => r.json())
    .then(data => {
        const req    = data.upgrade_request;
        const statusEl = document.getElementById('upgradeRequestStatus');
        if (!statusEl) return;
        if (req) {
            const colors = { pending:'#f59e0b', approved:'#22c55e', rejected:'#ef4444' };
            statusEl.style.display    = 'block';
            statusEl.style.background = (colors[req.status] || '#8696a0') + '18';
            statusEl.style.color      = colors[req.status] || '#8696a0';
            statusEl.style.border     = `1px solid ${colors[req.status] || '#8696a0'}40`;
            if (req.status === 'pending') {
                statusEl.innerHTML = `<i class="fa-solid fa-clock"></i> Your upgrade request to <strong>${req.requested_plan}</strong> is pending admin approval.`;
            } else if (req.status === 'approved') {
                statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> Your request was approved! Enjoy your new plan.`;
            } else {
                statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Request was rejected. ${req.admin_note ? 'Note: ' + req.admin_note : 'Please contact admin.'}`;
            }
        } else {
            if (statusEl) statusEl.style.display = 'none';
        }
    }).catch(() => {});
}

function closeUpgradeModal() {
    const modal = document.getElementById('upgradeModal');
    if (modal) modal.style.display = 'none';
}

function renderUpgradePlanCards() {
    const container = document.getElementById('upgradePlanCards');
    if (!container) return;
    container.innerHTML = '<p style="color:var(--text-muted);font-size:0.8rem;">Loading plans...</p>';

    fetch(`${BASE_URL}/api/plan/templates`)
    .then(r => r.json())
    .then(data => {
        let templates = data.templates || [];
        
        // Sort: trial first, heavy, unlimited
        const order = { trial: 1, heavy: 2, unlimited: 3 };
        templates.sort((a, b) => (order[a.plan_name] || 99) - (order[b.plan_name] || 99));

        const planColors  = { trial:'#f59e0b', heavy:'#3b82f6', unlimited:'#22c55e' };
        const planIcons   = { trial:'fa-seedling', heavy:'fa-bolt', unlimited:'fa-infinity' };
        
        // Get current active plan name
        const currentPlanName = _planSummary?.plan?.plan_name || 'trial';

        container.innerHTML = '';
        container.style.cssText = 'display:flex; gap:16px; flex-wrap:wrap; width:100%;';

        templates.forEach(tpl => {
            const pc   = planColors[tpl.plan_name]  || '#8696a0';
            const icon = planIcons[tpl.plan_name] || 'fa-layer-group';
            const isCurrent = (tpl.plan_name === currentPlanName);

            const row = (iconClass, val, unit = '') => {
                const displayVal = val !== null && val !== '' ? `${val} ${unit}` : 'Unlimited';
                return `
                    <div style="display:flex; align-items:center; gap:8px; font-size:0.78rem; color:var(--text-secondary); margin-bottom:6px;">
                        <i class="fa-solid ${iconClass}" style="width:16px; text-align:center; color:${pc};"></i>
                        <span style="font-weight:500; color:var(--text-primary);">${displayVal}</span>
                    </div>
                `;
            };

            const card = document.createElement('div');
            card.style.cssText = `
                flex: 1;
                min-width: 140px;
                background: ${pc}10;
                border: 2px solid ${isCurrent ? pc : pc + '40'};
                border-radius: 12px;
                padding: 16px;
                display: flex;
                flex-direction: column;
                position: relative;
            `;

            const currentBadge = isCurrent 
                ? `<span style="position:absolute; top:-10px; right:10px; background:${pc}; color:#fff; font-size:0.6rem; font-weight:700; padding:2px 8px; border-radius:10px; text-transform:uppercase; box-shadow:0 2px 4px rgba(0,0,0,0.15);">Current</span>`
                : '';

            let validityRow = '';
            if (isCurrent) {
                if (_planSummary?.plan?.expires_at) {
                    const expiresAt = _planSummary.plan.expires_at;
                    const d = Math.max(0, Math.ceil((expiresAt - Math.floor(Date.now() / 1000)) / 86400));
                    const dateStr = new Date(expiresAt * 1000).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' });
                    validityRow = `
                        <div style="font-size:0.72rem; color:#ef4444; font-weight:600; margin-top:8px; display:flex; align-items:center; gap:6px;">
                            <i class="fa-regular fa-calendar-xmark"></i>
                            <span>Expires: ${dateStr} (${d}d left)</span>
                        </div>
                    `;
                } else {
                    validityRow = `
                        <div style="font-size:0.72rem; color:#22c55e; font-weight:600; margin-top:8px; display:flex; align-items:center; gap:6px;">
                            <i class="fa-regular fa-calendar-check"></i>
                            <span>Validity: Lifetime</span>
                        </div>
                    `;
                }
            }

            card.innerHTML = `
                ${currentBadge}
                <div style="font-weight:700; color:${pc}; margin-bottom:12px; font-size:0.95rem; display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid ${icon}"></i>
                    <span>${tpl.label}</span>
                </div>
                <div style="display:flex; flex-direction:column; flex:1; margin-bottom:12px;">
                    ${row('fa-message', tpl.limit_text, 'msgs/day')}
                    ${row('fa-image', tpl.limit_image, 'imgs/day')}
                    ${row('fa-film', tpl.limit_video, 'vids/day')}
                    ${row('fa-microphone', tpl.limit_audio, 'voice/day')}
                    ${row('fa-phone', tpl.limit_audio_call_minutes, 'min/day')}
                    ${row('fa-video', tpl.limit_video_call_minutes, 'min/day')}
                </div>
                ${tpl.contact_number ? `<div style="font-size:0.72rem;color:#22c55e;margin-bottom:4px;font-weight:600;"><i class="fa-solid fa-phone"></i> ${tpl.contact_number}</div>` : ''}
                ${tpl.contact_text   ? `<div style="font-size:0.72rem;color:var(--text-secondary);font-style:italic;">${tpl.contact_text}</div>` : ''}
                ${validityRow}
            `;
            container.appendChild(card);
        });
    }).catch(() => {
        container.innerHTML = '<p style="color:var(--text-muted);">Failed to load plan info.</p>';
    });
}

function submitUpgradeRequest() {
    const plan    = document.getElementById('upgradeTargetPlan')?.value || 'heavy';
    const message = document.getElementById('upgradeMessage')?.value?.trim() || '';

    const fd = new FormData();
    fd.append('plan',    plan);
    fd.append('message', message);

    fetch(`${BASE_URL}/api/plan/request-upgrade`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        const statusEl = document.getElementById('upgradeRequestStatus');
        if (data.success) {
            showToast('Upgrade request sent to admin!', 'success');
            if (statusEl) {
                statusEl.style.display    = 'block';
                statusEl.style.background = '#22c55e18';
                statusEl.style.color      = '#22c55e';
                statusEl.style.border     = '1px solid #22c55e40';
                statusEl.innerHTML        = '<i class="fa-solid fa-clock"></i> Upgrade request pending admin approval.';
            }
        } else {
            showToast(data.error || 'Failed to send request.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
}

/* ============================================================
   NOTIFICATION INBOX
   ============================================================ */

function openNotifInbox() {
    const modal = document.getElementById('notifInboxModal');
    if (modal) { modal.style.display = 'flex'; loadNotifications(); }
}

function closeNotifInbox() {
    const modal = document.getElementById('notifInboxModal');
    if (modal) modal.style.display = 'none';
}

function loadNotifications() {
    const list = document.getElementById('notifInboxList');
    if (!list) return;
    list.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px;">Loading...</p>';

    fetch(`${BASE_URL}/api/notifications`)
    .then(r => r.json())
    .then(data => {
        const notifs = data.notifications || [];
        updateNotifBadge(data.unread || 0);

        if (notifs.length === 0) {
            list.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px;">No notifications yet.</p>';
            return;
        }
        list.innerHTML = '';
        notifs.forEach(n => {
            const isUnread = !n.is_read;
            const card = document.createElement('div');
            card.setAttribute('data-notif-id', n.id);
            card.style.cssText = `
                background:${isUnread ? 'rgba(0,242,254,0.06)' : 'rgba(255,255,255,0.03)'};
                border:1px solid ${isUnread ? 'rgba(0,242,254,0.2)' : 'rgba(255,255,255,0.07)'};
                border-radius:10px;padding:14px;cursor:pointer;transition:background .2s;
            `;
            card.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                    <strong style="font-size:0.88rem;${isUnread ? 'color:var(--accent)' : ''}">${_esc(n.title)}</strong>
                    ${isUnread ? '<span style="width:7px;height:7px;background:#00f2fe;border-radius:50%;flex-shrink:0;margin-top:5px;"></span>' : ''}
                </div>
                <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 6px;line-height:1.5;">${_esc(n.body)}</p>
                ${n.contact_number ? `<div style="font-size:0.75rem;color:#22c55e;margin-bottom:4px;">
                    <i class="fa-solid fa-phone"></i> <a href="tel:${_esc(n.contact_number)}" style="color:#22c55e;">${_esc(n.contact_number)}</a>
                    ${n.contact_text ? ' — ' + _esc(n.contact_text) : ''}
                </div>` : ''}
                <div style="font-size:0.7rem;color:var(--text-muted);">${_esc(n.created_at)}</div>
            `;
            card.onclick = () => markNotifRead(n.id, card);
            list.appendChild(card);
        });
    }).catch(() => {
        list.innerHTML = '<p style="color:var(--text-muted);text-align:center;">Failed to load notifications.</p>';
    });
}

function markNotifRead(id, cardEl) {
    fetch(`${BASE_URL}/api/notifications/read/${id}`, { method: 'POST' }).catch(() => {});
    if (cardEl) {
        cardEl.style.background = 'rgba(255,255,255,0.03)';
        cardEl.style.border     = '1px solid rgba(255,255,255,0.07)';
        const dot = cardEl.querySelector('span[style*="background:#00f2fe"]');
        if (dot) dot.remove();
        const title = cardEl.querySelector('strong');
        if (title) title.style.color = '';
    }
    const badge = parseInt(document.getElementById('notifBadge')?.innerText || '0');
    updateNotifBadge(Math.max(0, badge - 1));
}

function markAllNotifsRead() {
    fetch(`${BASE_URL}/api/notifications/read-all`, { method: 'POST' }).then(() => {
        updateNotifBadge(0);
        loadNotifications();
    }).catch(() => {});
}

function updateNotifBadge(count) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    if (count > 0) {
        badge.innerText      = count > 9 ? '9+' : count;
        badge.style.display  = 'block';
    } else {
        badge.style.display  = 'none';
    }
}

function refreshNotifBadge() {
    fetch(`${BASE_URL}/api/plan/unread-count`)
    .then(r => r.json())
    .then(data => updateNotifBadge(data.unread || 0))
    .catch(() => {});
}

// Helper to escape HTML in JS
function _esc(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/**
 * Converts plain text to HTML with clickable URLs.
 * Non-URL portions are HTML-escaped (prevents XSS from message content).
 *
 * URLs become: <a href="..." target="_blank" rel="noopener noreferrer">
 * onclick stops propagation so clicking a link doesn't trigger the
 * message-bubble's vanish timer.
 */
function _linkify(text) {
    if (!text) return '';

    // Split on http(s):// URLs — the capture group keeps URLs as odd elements
    const parts = String(text).split(/(https?:\/\/[^\s<>"']+)/gi);

    return parts.map((part, i) => {
        if (i % 2 === 0) {
            // Plain text — escape and preserve whitespace / newlines
            return _esc(part).replace(/\n/g, '<br>');
        }
        // URL — strip trailing punctuation that is unlikely to be part of the link
        const url  = part.replace(/[.,!?;:'"()[\]>]+$/, '');
        const tail = _esc(part.slice(url.length));
        return (
            `<a href="${_esc(url)}" target="_blank" rel="noopener noreferrer"` +
            ` style="color:var(--accent);text-decoration:underline;word-break:break-all;"` +
            ` onclick="event.stopPropagation();">${_esc(url)}</a>${tail}`
        );
    }).join('');
}

// SSE handler for live admin notifications
function handleSSENotification(payload) {
    const plan = _planSummary?.plan?.plan_name || 'trial';
    const tg   = payload.target_group || 'all';
    if (tg !== 'all' && tg !== plan) return;

    // Increment unread badge
    const badge = parseInt(document.getElementById('notifBadge')?.innerText || '0');
    updateNotifBadge(badge + 1);

    // Show toast
    showToast(`📢 ${payload.title}`, 'info', 7000);
}

// Boot plan system on page load
document.addEventListener('DOMContentLoaded', function () {
    startPlanPolling();
    refreshNotifBadge();
    // Refresh badge every 2 minutes
    setInterval(refreshNotifBadge, 120000);
});
