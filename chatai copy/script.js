// Configuration
let isLocked = true;
let inactivityTimer = 60;
let timerInterval;
let isTyping = false;
let typingTimeout;
let renderedMessageIds = new Set();
let mediaRecorder;
let audioChunks = [];
let isRecording = false;
let recordingTimerInterval;
let lastOtherOnline = false;

const camouflageData = {
    1: { prompts: ["Explain Python list comprehensions.", "How does CSS Flexbox work?", "What is a REST API?", "Explain the difference between SQL and NoSQL.", "How do I optimize a React application?"], responses: ["List comprehensions provide a concise way to create lists in Python using a single line of code.", "Flexbox is a one-dimensional layout method for arranging items in rows or columns.", "A REST API is an architectural style for an application program interface that uses HTTP requests.", "SQL databases are relational, while NoSQL databases are non-relational or distributed.", "To optimize React, use Memoization, lazy loading, and avoid unnecessary re-renders."] },
    2: { prompts: ["What is quantum entanglement?", "Explain the theory of general relativity.", "How do black holes form?", "What is the Higgs Boson?", "Explain the double-slit experiment."], responses: ["Quantum entanglement is a phenomenon where particles become linked and share their state instantly.", "General relativity is Einstein's theory of gravity, describing space-time as a curved fabric.", "Black holes form when a massive star collapses under its own gravity at the end of its life cycle.", "The Higgs Boson is a fundamental particle that gives other particles mass via the Higgs field.", "The double-slit experiment demonstrates that light and matter can display characteristics of both waves and particles."] }
};

const fakeRecentChats = ["Project Phoenix Refactor", "Quantum Mechanics Notes", "Neural Network Architecture", "Thermodynamics Basics", "C Library Optimization"];

// DOM Elements
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menu-toggle');
const sidebarOverlay = document.getElementById('sidebar-overlay');
const lockStatus = document.getElementById('lock-status');
const timerProgress = document.getElementById('timer-progress');
const messagesContainer = document.getElementById('messages-container');
const messageInput = document.getElementById('message-input');
const sendBtn = document.getElementById('send-btn');
const attachBtn = document.getElementById('attach-btn');
const viewTextBtn = document.getElementById('view-text-btn');
const fileInput = document.getElementById('file-input');
const thinkingArea = document.querySelector('.thinking-area');
const thinkingSparkle = document.getElementById('thinking-sparkle');
const thinkingText = document.getElementById('thinking-text');
const systemLogsContainer = document.getElementById('system-logs');
const panicLogo = document.getElementById('panic-logo');
const notificationBtn = document.getElementById('enable-notifications');

// Background Upload Progress UI
const progressOverlay = document.createElement('div');
progressOverlay.style.cssText = 'display:none; position:fixed; bottom:80px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.8); color:white; padding:10px 20px; border-radius:20px; z-index:2000; font-size:12px; font-weight:600; box-shadow:0 4px 15px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1);';
progressOverlay.id = 'upload-progress-overlay';
progressOverlay.innerHTML = 'Sending... <span id="upload-percent">0%</span>';
document.body.appendChild(progressOverlay);

let currentStatusText = "Gemini is offline";
let previousStatus = 'offline';
let lastSeenTimestamp = 0;
let statusCycleInterval;
let inputMaskTimeout;

document.addEventListener('DOMContentLoaded', async () => {
    initFakeUI();
    startPolling();
    resetInactivityTimer();
    if (menuToggle) menuToggle.onclick = toggleSidebar;
    if (sidebarOverlay) sidebarOverlay.onclick = closeSidebar;
    const sidebarClose = document.getElementById('sidebar-close');
    if (sidebarClose) sidebarClose.onclick = closeSidebar;
    if (lockStatus) lockStatus.onclick = toggleLock;
    if (messageInput) {
        messageInput.onkeydown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); } };
        messageInput.oninput = () => { handleInputPrivacy(); if (!isLocked) handleTyping(); };
        messageInput.onfocus = () => { if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) setTimeout(scrollToBottom, 300); };
    }
    if (viewTextBtn) viewTextBtn.onclick = peekInputText;
    if (sendBtn) sendBtn.onclick = handleSend;
    if (attachBtn) attachBtn.onclick = () => fileInput.click();
    if (fileInput) fileInput.onchange = handleImageUpload;
    if (document.getElementById('mic-btn')) document.getElementById('mic-btn').onclick = toggleRecording;
    if (document.getElementById('knock-btn')) document.getElementById('knock-btn').onclick = sendKnockSMS;
    if (notificationBtn) notificationBtn.onclick = enableNotifications;
    if (thinkingArea) thinkingArea.style.display = 'flex';
    if (thinkingSparkle) thinkingSparkle.classList.add('active');
    startSystemLogs();
    startStatusCycling();
    setInterval(updateAllTimestamps, 1000);

    // Activity listeners to reset inactivity timer
    ['mousedown', 'mousemove', 'keypress', 'touchstart', 'scroll'].forEach(evt => {
        document.addEventListener(evt, () => { if (!isLocked) resetInactivityTimer(); }, { passive: true });
    });
});

async function secureFetch(url, options = {}) {
    const h = { 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' };
    if (options.body && !(options.body instanceof FormData)) h['Content-Type'] = 'application/json';
    options.headers = { ...h, ...(options.headers || {}) };
    try {
        const resp = await fetch(url, options);
        if (resp.status === 401) { window.location.href = 'index.php'; return null; }
        if (!resp.ok) return null;
        return resp;
    } catch (e) { return null; }
}

function escapeHTML(str) { if (!str) return ''; const div = document.createElement('div'); div.textContent = str; return div.innerHTML; }
function linkify(text) { if (!text) return ''; const urlPattern = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig; return text.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener noreferrer" class="chat-link">$1</a>'); }
function formatTimestamp(ts) { return new Date(ts).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Dhaka' }); }
function getTimeAgo(ts) {
    const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (diff < 10) return "just now";
    const h = Math.floor(diff / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
    return (h > 0 ? h + "h " : "") + (m > 0 ? m + "m " : "") + s + "s ago";
}
function updateAllTimestamps() { document.querySelectorAll('.timestamp[data-created]').forEach(el => { el.textContent = `${formatTimestamp(el.dataset.created)} • ${getTimeAgo(el.dataset.created)}`; }); }
function toggleSidebar() { sidebar.classList.toggle('active'); sidebarOverlay.classList.toggle('active'); }
function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
function handleInputPrivacy() { if (messageInput) messageInput.style.webkitTextSecurity = 'disc'; }
function peekInputText() { if (!messageInput) return; clearTimeout(inputMaskTimeout); messageInput.style.webkitTextSecurity = 'none'; inputMaskTimeout = setTimeout(() => { messageInput.style.webkitTextSecurity = 'disc'; }, 2000); }
function handleTyping() { if (!isTyping) { isTyping = true; updateMyStatus(); } clearTimeout(typingTimeout); typingTimeout = setTimeout(() => { isTyping = false; updateMyStatus(); }, 3500); }
function scrollToBottom() { const c = messagesContainer ? messagesContainer.parentElement.parentElement : null; if (c) c.scrollTop = c.scrollHeight; }
function toggleLock() { if (!isLocked) { isLocked = true; location.reload(); } }
async function unlockApp() { 
    isLocked = false; 
    lockStatus.textContent = 'U'; 
    thinkingArea.style.display = 'flex'; 
    const uploadBtn = document.getElementById('upload-media-btn');
    if (uploadBtn) uploadBtn.style.display = 'inline-block';
    renderedMessageIds.clear(); 
    messagesContainer.innerHTML = ''; 
    startSystemLogs(); 
    resetInactivityTimer(); 
    loadMessages(); 
}
function resetInactivityTimer() { inactivityTimer = 60; clearInterval(timerInterval); updateTimerCircle(); if (!isLocked) timerInterval = setInterval(() => { inactivityTimer--; updateTimerCircle(); if (inactivityTimer <= 0) { isLocked = true; location.reload(); } }, 1000); }
function updateTimerCircle() { const offset = 88 * (1 - inactivityTimer / 60); if (timerProgress) timerProgress.style.strokeDashoffset = offset; }

async function handleSend() {
    const input = messageInput.value.trim(); if (!input) return;
    if (input === '0000') { messageInput.value = ''; await secureFetch('api.php?action=nuclear_wipe', { method: 'POST' }); location.reload(); return; }
    if (isLocked && input.length === 4 && /^\d+$/.test(input)) {
        const resp = await secureFetch('api.php?action=verify_pin', { method: 'POST', body: JSON.stringify({ pin: input }) });
        if (resp) {
            try {
                const data = await resp.json();
                if (data.success) { messageInput.value = ''; unlockApp(); return; }
            } catch(e) {}
        }
    }
    messageInput.value = ''; messageInput.style.webkitTextSecurity = 'none';
    await secureFetch('api.php?action=send_message', { method: 'POST', body: JSON.stringify({ content: input, is_image: 0 }) });
    loadMessages();
}

async function handleImageUpload(e) {
    const file = e.target.files[0]; if (!file) return;
    const isVideo = file.type.startsWith('video');
    const isImage = file.type.startsWith('image');
    if (!confirm(`Confirm send ${file.type.split('/')[0]}?`)) return;

    progressOverlay.style.display = 'block';
    document.getElementById('upload-percent').textContent = '0%';

    const sendData = async (dataUrl) => {
        if (dataUrl.length > 140 * 1024 * 1024) { // ~140MB base64
            alert('File too large! Please use a smaller file.');
            progressOverlay.style.display = 'none';
            return;
        }
        await secureFetch('api.php?action=send_message', { 
            method: 'POST', 
            body: JSON.stringify({ content: dataUrl, is_image: isImage ? 1 : 0, is_video: isVideo ? 1 : 0 }) 
        });
        progressOverlay.style.display = 'none'; loadMessages();
    };

    if (isImage && file.size > 1024 * 1024) {
        const img = new Image();
        img.src = URL.createObjectURL(file);
        img.onload = () => {
            const canvas = document.createElement('canvas');
            let w = img.width, h = img.height;
            const max = 1600;
            if (w > max || h > max) { if (w > h) { h *= max / w; w = max; } else { w *= max / h; h = max; } }
            canvas.width = w; canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            sendData(canvas.toDataURL('image/jpeg',0.7));
            URL.revokeObjectURL(img.src);
        };
    } else if (isVideo && file.size > 10 * 1024 * 1024) {
        alert('Video too large! Max 10MB for chat. Please upload via Premium Vault instead.');
        progressOverlay.style.display = 'none';
        return;
    } else {
        const reader = new FileReader();
        reader.onload = (event) => sendData(event.target.result);
        reader.onprogress = (d) => { if (d.lengthComputable) document.getElementById('upload-percent').textContent = Math.round((d.loaded/d.total)*100)+'%'; };
        reader.readAsDataURL(file);
    }
}

async function loadMessages() {
    const resp = await secureFetch('api.php?action=get_messages'); if (!resp) return;
    const msgs = await resp.json();
    msgs.forEach(msg => {
        if (!renderedMessageIds.has(msg.id)) { renderMessage(msg); renderedMessageIds.add(msg.id); scrollToBottom(); }
        else {
            const el = document.getElementById(`msg-${msg.id}`);
            if (el && msg.viewed_at && !el.querySelector('.viewed-badge')) {
                const meta = el.querySelector('.msg-meta'), badge = document.createElement('div');
                badge.className = 'viewed-badge'; badge.innerHTML = '👁️ Viewed';
                const burn = meta.querySelector('.burn-timer'); if (burn) meta.insertBefore(badge, burn); else meta.appendChild(badge);
                if (msg.burn_after && !meta.querySelector('.burn-timer')) {
                    const bDiv = document.createElement('div'); bDiv.className = 'burn-timer'; bDiv.id = `burn-${msg.id}`; bDiv.innerHTML = '🔥 <span></span>';
                    meta.appendChild(bDiv); startBurnUI(msg.id, msg.burn_after);
                }
            }
        }
    });
}

function renderMessage(msg) {
    const div = document.createElement('div'), isSent = msg.sender_id === CURRENT_USER_ID, isAI = msg.sender_id === 0;
    div.className = `message-row ${isSent ? 'sent' : 'received'}`; div.id = `msg-${msg.id}`;
    let display = applyCamouflage(msg);
    if (!isLocked) {
        div.style.cursor = 'pointer'; div.onclick = () => revealMessage(msg, div);
    }
    const meta = `<div class="msg-meta"><div class="timestamp" data-created="${msg.created_at}">${formatTimestamp(msg.created_at)}</div>${msg.viewed_at ? '<div class="viewed-badge">👁️ Viewed</div>' : ''}${msg.burn_after ? `<div class="burn-timer" id="burn-${msg.id}">🔥 <span></span></div>` : ''}</div>`;
    div.innerHTML = isSent ? `<div class="msg-body"><div class="msg-text glass">${display}</div>${meta}</div><div class="avatar user">👤</div>` : `<div class="avatar ai colorful-sparkle">✦</div><div class="msg-body"><div class="msg-text glass">${display}</div>${meta}</div>`;
    messagesContainer.appendChild(div);
    if (msg.burn_after) startBurnUI(msg.id, msg.burn_after);
}

function applyCamouflage(msg) {
    if (msg.is_image) return `<div class="image-camouflage">Image Error 0x882</div>`;
    if (msg.is_voice) return `<div class="voice-camouflage">Audio Error</div>`;
    if (msg.is_video) return `<div class="video-camouflage">Video Error 502</div>`;
    const pool = camouflageData[CURRENT_USER_ID], items = (msg.sender_id === CURRENT_USER_ID) ? pool.prompts : pool.responses;
    return escapeHTML(items[msg.id % items.length]);
}

async function revealMessage(msg, el) {
    const txt = el.querySelector('.msg-text'); if (!txt || txt.dataset.revealed) return;
    txt.dataset.revealed = "true";
    let real = '';
    if (msg.is_image) real = `<img src="${msg.content}" style="max-width:100%; height:auto; border-radius:10px; display:block;">`;
    else if (msg.is_voice) real = `<audio controls src="${msg.content}"></audio>`;
    else if (msg.is_video) real = `<video src="${msg.content}" controls autoplay style="max-width:100%; height:auto; border-radius:10px; display:block;"></video>`;
    else if (CURRENT_USER_ID === 1) {
        const escaped = escapeHTML(msg.content);
        real = `<pre style="white-space:pre-wrap; font-size:11px;">#include <stdio.h>\nint main() { printf("${escaped}"); return 0; }</pre>`;
    }
    else real = linkify(escapeHTML(msg.content));
    
    txt.innerHTML = real; txt.classList.add('revealed');
    if (CURRENT_USER_ID === 1 && (msg.is_image || msg.is_voice || msg.is_video)) {
        const btn = document.createElement('button');
        btn.className = 'modern-btn';
        btn.style.cssText = 'margin-top:8px; font-size:11px; padding:6px 14px; border-radius:20px; background:linear-gradient(135deg,#4285f4,#9b72cb); color:#fff; border:none; cursor:pointer; font-weight:600; transition:all 0.3s ease; box-shadow:0 2px 10px rgba(66,133,244,0.3);';
        btn.textContent = '💾 Add to Premium';
        btn.onmouseenter = () => { btn.style.transform = 'scale(1.05)'; btn.style.boxShadow = '0 4px 15px rgba(66,133,244,0.5)'; };
        btn.onmouseleave = () => { btn.style.transform = 'scale(1)'; btn.style.boxShadow = '0 2px 10px rgba(66,133,244,0.3)'; };
        btn.onclick = async (e) => {
            e.stopPropagation();
            btn.textContent = '⏳ Adding...'; btn.disabled = true; btn.style.opacity = '0.7';
            const r = await secureFetch('api.php?action=save_image', { method:'POST', body:JSON.stringify({ media_data:msg.content, is_voice:msg.is_voice, is_video:msg.is_video }) });
            const d = await r.json();
            if (d.success) {
                btn.textContent = '✅ Added to Premium';
                btn.style.background = 'linear-gradient(135deg,#34a853,#28a745)';
                btn.style.boxShadow = '0 2px 10px rgba(40,167,69,0.3)';
                btn.style.cursor = 'default';
                btn.style.opacity = '1';
                btn.onmouseenter = null; btn.onmouseleave = null;
            } else {
                btn.textContent = '❌ Failed'; btn.disabled = false; btn.style.opacity = '1';
                setTimeout(() => { btn.textContent = '💾 Add to Premium'; }, 2000);
            }
        };
        txt.appendChild(btn);
    }
    setTimeout(async () => {
        if (!msg.burn_after) {
            const r = await secureFetch('api.php?action=mark_viewed', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) });
            if (r) { const d = await r.json(); if (d.success) { msg.burn_after = d.burn_after; startBurnUI(msg.id, d.burn_after); } }
        }
        txt.innerHTML = applyCamouflage(msg); txt.classList.remove('revealed'); delete txt.dataset.revealed;
    }, 10000);
}

function startBurnUI(id, burnAt) {
    const span = document.querySelector(`#burn-${id} span`); if (!span) return;
    const inv = setInterval(() => {
        const rem = Math.max(0, burnAt - Math.floor(Date.now()/1000));
        span.textContent = rem + 's';
        if (rem <= 0) { clearInterval(inv); const el = document.getElementById(`msg-${id}`); if (el) el.remove(); }
    }, 1000);
}

function startPolling() { updateMyStatus(); getOtherStatus(); loadMessages(); setInterval(() => { getOtherStatus(); loadMessages(); }, 2000); setInterval(updateMyStatus, 2000); }
async function updateMyStatus() { await secureFetch('api.php?action=update_status', { method: 'POST', body: JSON.stringify({ is_typing: isTyping ? 1 : 0, in_theater: 0 }) }); }
async function getOtherStatus() {
    const r = await secureFetch('api.php?action=get_other_status'); if (!r) return;
    const d = await r.json(); lastSeenTimestamp = d.last_seen;
    const isOnline = d.status === 'active' || d.status === 'typing';
    const color = (d.status === 'typing') ? '#ffc107' : (isOnline ? '#28a745' : '#f44336');
    document.documentElement.style.setProperty('--status-color', color);
    currentStatusText = (d.status === 'typing') ? "Gemini partner is typing..." : (isOnline ? "Gemini is online" : "Gemini is offline");
    if (thinkingText) thinkingText.textContent = currentStatusText;
    if (panicLogo) {
        panicLogo.style.opacity = isOnline ? "1" : "0.5";
        panicLogo.style.color = isOnline ? "var(--gemini-blue)" : "inherit";
        panicLogo.style.filter = isOnline ? "grayscale(0)" : "grayscale(1)";
    }
    // Send notification when other user comes online
    if (isOnline && !lastOtherOnline && Notification.permission === 'granted') {
        new Notification('Gemini is Online', { body: 'Your Gemini partner is now online.', icon: 'https://www.gstatic.com/lamda/images/favicon_v1_150160c13ff2af13800c.png' });
    }
    lastOtherOnline = isOnline;
    // Update system log with active time
    if (systemLogsContainer && lastSeenTimestamp) {
        const diff = Math.max(0, Math.floor(Date.now()/1000 - lastSeenTimestamp));
        if (diff < 10) {
            systemLogsContainer.textContent = "[Active just now]";
        } else {
            const d = Math.floor(diff / 86400);
            const h = Math.floor((diff % 86400) / 3600);
            const m = Math.floor((diff % 3600) / 60);
            const s = diff % 60;
            let parts = [];
            if (d) parts.push(d + 'd');
            if (h) parts.push(h + 'h');
            if (m) parts.push(m + 'm');
            if (s) parts.push(s + 's');
            systemLogsContainer.textContent = `[Active ${parts.join(' ')} ago]`;
        }
    }
}

function initFakeUI() { 
    const c = document.getElementById('fake-chats'); if (!c) return; c.innerHTML = ''; 
    fakeRecentChats.forEach(t => { const d = document.createElement('div'); d.className = 'nav-item'; d.innerHTML = `<span>💬</span> ${t}`; c.appendChild(d); }); 
    if (panicLogo) panicLogo.onclick = () => window.location.href = 'call.php';
}
function startSystemLogs() {
    const tasks = ["Refactoring Project Phoenix...", "Optimizing Neural Pathways...", "Synthesizing Contextual Data...", "Analyzing User Intent..."];
    let taskIndex = 0;
    setInterval(() => {
        if (!systemLogsContainer) return;
        if (lastSeenTimestamp && systemLogsContainer.textContent.includes('Active')) {
            // Keep showing active time, don't override
            return;
        }
        systemLogsContainer.textContent = `[${tasks[taskIndex % tasks.length]}]`;
        taskIndex++;
    }, 2000);
}
function startStatusCycling() { setInterval(() => { if (thinkingText) thinkingText.textContent = (thinkingText.textContent.includes('thinking')) ? currentStatusText : "Gemini is thinking..."; }, 3000); }

async function toggleRecording() { if (isRecording) stopRecording(); else startRecording(); }
async function startRecording() {
    try {
        const s = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(s); audioChunks = [];
        mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
        mediaRecorder.onstop = async () => {
            const b = new Blob(audioChunks, { type: 'audio/webm' });
            const reader = new FileReader();
            reader.onload = async () => { await secureFetch('api.php?action=send_message', { method: 'POST', body: JSON.stringify({ content: reader.result, is_voice: 1 }) }); loadMessages(); };
            reader.readAsDataURL(b); s.getTracks().forEach(t => t.stop());
        };
        mediaRecorder.start();
        isRecording = true;
        document.getElementById('mic-btn').classList.add('recording');
        startRecordingTimer();
    } catch (e) {
        alert("Microphone access denied or not available.");
    }
}
function stopRecording() { 
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop(); 
    isRecording = false; 
    document.getElementById('mic-btn').classList.remove('recording'); 
    stopRecordingTimer();
}

let recordingStartTime;
function startRecordingTimer() {
    recordingStartTime = Date.now();
    const status = document.getElementById('recording-status');
    const timeEl = document.getElementById('recording-time');
    if (status) status.style.display = 'inline-block';
    recordingTimerInterval = setInterval(() => {
        const seconds = Math.floor((Date.now() - recordingStartTime) / 1000);
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        if (timeEl) timeEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    }, 1000);
}

function stopRecordingTimer() {
    clearInterval(recordingTimerInterval);
    const status = document.getElementById('recording-status');
    if (status) status.style.display = 'none';
}

function enableNotifications() {
    if (!("Notification" in window)) {
        alert("This browser does not support desktop notification");
    } else if (Notification.permission === "granted") {
        alert("Notifications already enabled!");
    } else {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                new Notification("Notifications enabled!", {
                    body: "You will now receive alerts for new messages.",
                    icon: "https://www.gstatic.com/lamda/images/favicon_v1_150160c13ff2af13800c.png"
                });
            }
        });
    }
}

window.showModal = (id) => { document.getElementById(id).style.display = 'flex'; };
window.closeModal = (id) => { document.getElementById(id).style.display = 'none'; };
window.burnYTComments = async () => {
    if (!confirm("Burn all messages containing YT_COMMENT: text?")) return;
    const r = await secureFetch('api.php?action=burn_yt_comments', { method: 'POST' });
    if (r && (await r.json()).success) { alert("YT_COMMENT messages burned"); location.reload(); }
};
window.resetPIN = () => { window.showModal('reset-pin-modal'); };
window.confirmResetPIN = async () => {
    const p = document.getElementById('reset-new-pin').value;
    if (p.length === 4) { 
        const r = await secureFetch('api.php?action=reset_pin', { method: 'POST', body: JSON.stringify({ new_pin: p }) }); 
        if (r && (await r.json()).success) { alert("PIN Reset & Messages Wiped"); location.reload(); }
        else { alert("Failed to reset PIN"); }
    } else { alert("PIN must be 4 digits"); }
};
window.updatePIN = async () => {
    const o = document.getElementById('old-pin').value, n = document.getElementById('new-pin').value;
    if (n.length !== 4) { alert("New PIN must be 4 digits"); return; }
    const r = await secureFetch('api.php?action=update_pin', { method: 'POST', body: JSON.stringify({ old_pin:o, new_pin:n }) });
    if (r && (await r.json()).success) { alert("PIN Updated"); window.closeModal('pin-modal'); }
    else { alert("Failed: " + ((await r.json()).error || "Unknown error")); }
};
window.updatePassword = async () => {
    const o = document.getElementById('old-password').value, n = document.getElementById('new-password').value;
    if (n.length < 6) { alert("New password too short"); return; }
    const r = await secureFetch('api.php?action=update_password', { method: 'POST', body: JSON.stringify({ old_password: o, new_password: n }) });
    const data = r ? await r.json() : null;
    if (data && data.success) { alert("Password updated"); window.closeModal('password-modal'); }
    else { alert("Failed: " + (data ? data.error : "Unknown error")); }
};
window.sendKnockSMS = async () => {
    const txt = prompt("Message:"); if (txt === null) return;
    await secureFetch('api.php?action=send_knock_sms', { method: 'POST', body: JSON.stringify({ custom_text: txt }) });
};
