// Scaled Gemini Chat Logic
let isLocked = true;
let inactivityTimer = 60;
let timerInterval;
let pollInterval;
let statusInterval;
let isTyping = false;
let typingTimeout;
let renderedMessageIds = new Set();
let currentRoomId = null;
let currentUser = null; // Will hold profile and privacy settings
let camouflageLibrary = { prompts: [], responses: [] };
let renderedRoomIds = new Set();

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
const activeRoomName = document.getElementById('active-room-name');
const roomListContainer = document.getElementById('room-list');

let currentStatusText = "Gemini is online";
let statusCycleInterval;
let inputMaskTimeout;

// --- Initialization ---
document.addEventListener('DOMContentLoaded', async () => {
    await initUser();
    initEventListeners();
    startPolling();
    
    // Always show thinking area
    thinkingArea.style.display = 'flex';
    thinkingSparkle.classList.add('active');
    
    if (!isLocked) {
        startSystemLogs();
    } else {
        systemLogsContainer.textContent = "[System_Standby]";
    }
    
    startStatusCycling();
});

async function initUser() {
    const resp = await secureFetch('api.php?action=get_user_info');
    currentUser = await resp.json();
    
    // Set privacy defaults from user settings
    inactivityTimer = currentUser.auto_lock_timer || 60;
    
    // Fetch camouflage for their theme
    await fetchCamouflage(currentUser.camouflage_theme);
    
    resetInactivityTimer();
}

async function fetchCamouflage(theme) {
    const resp = await secureFetch(`api.php?action=get_camouflage&theme=${theme}`);
    const data = await resp.json();
    camouflageLibrary.prompts = data.filter(i => i.type === 'prompt').map(i => i.content);
    camouflageLibrary.responses = data.filter(i => i.type === 'response').map(i => i.content);
    
    // Fallback if empty
    if (camouflageLibrary.prompts.length === 0) camouflageLibrary.prompts = ["Explain Python logic."];
    if (camouflageLibrary.responses.length === 0) camouflageLibrary.responses = ["Python logic involves using clear syntax."];
}

function initEventListeners() {
    if (menuToggle) menuToggle.onclick = toggleSidebar;
    if (sidebarOverlay) sidebarOverlay.onclick = closeSidebar;
    const sidebarClose = document.getElementById('sidebar-close');
    if (sidebarClose) sidebarClose.onclick = closeSidebar;
    lockStatus.onclick = toggleLock;
    
    messageInput.onkeydown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
        if (!isLocked) handleTyping();
    };

    messageInput.oninput = () => {
        messageInput.style.webkitTextSecurity = 'disc';
    };
    
    if (viewTextBtn) viewTextBtn.onclick = peekInputText;
    
    sendBtn.onclick = handleSend;
    attachBtn.onclick = () => fileInput.click();
    fileInput.onchange = handleImageUpload;
    
    document.onmousemove = () => { if (!isLocked) resetInactivityTimer(); };
    document.onkeypress = () => { if (!isLocked) resetInactivityTimer(); };
}

// --- CSRF & Fetch ---
async function secureFetch(url, options = {}) {
    const defaultHeaders = { 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' };
    if (options.body && !(options.body instanceof FormData)) {
        defaultHeaders['Content-Type'] = 'application/json';
    }
    options.headers = { ...defaultHeaders, ...(options.headers || {}) };
    const resp = await fetch(url, options);
    if (resp.status === 401) { window.location.reload(); return; }
    return resp;
}

// --- Navigation ---
async function loadRooms() {
    const resp = await secureFetch('api.php?action=get_rooms');
    const rooms = await resp.json();
    
    rooms.forEach(room => {
        if (renderedRoomIds.has(room.id)) {
            // Update existing room name if needed
            return;
        }
        const div = document.createElement('div');
        div.className = `nav-item ${currentRoomId === room.id ? 'active' : ''}`;
        div.id = `room-${room.id}`;
        const displayName = room.name || room.member_names || 'Conversation ' + room.id;
        div.innerHTML = `<span>💬</span> ${escapeHTML(displayName)}`;
        div.onclick = () => switchRoom(room.id, displayName);
        roomListContainer.appendChild(div);
        renderedRoomIds.add(room.id);
    });

    if (!currentRoomId && rooms.length > 0) {
        const first = rooms[0];
        switchRoom(first.id, first.name || first.member_names || 'Conversation ' + first.id);
    }
}

function switchRoom(id, name) {
    if (currentRoomId === id) return;
    currentRoomId = id;
    activeRoomName.textContent = name || 'Conversation ' + id;
    renderedMessageIds.clear();
    messagesContainer.innerHTML = '';
    
    document.querySelectorAll('#room-list .nav-item').forEach(el => {
        el.classList.toggle('active', el.id === `room-${id}`);
    });
    
    loadMessages();
    closeSidebar();
}

let selectedUsers = [];

async function searchUsers(query) {
    const resultsContainer = document.getElementById('user-search-results');
    if (query.length < 2) {
        resultsContainer.innerHTML = '';
        return;
    }

    const resp = await secureFetch(`api.php?action=search_users&query=${encodeURIComponent(query)}`);
    const users = await resp.json();

    resultsContainer.innerHTML = '';
    users.forEach(u => {
        if (selectedUsers.some(su => su.username === u.username)) return;
        const div = document.createElement('div');
        div.style = 'padding: 10px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;';
        div.innerHTML = `
            <div onclick="selectUser(${JSON.stringify(u).replace(/"/g, '&quot;')})">
                <strong>${escapeHTML(u.username)}</strong> <small>(${escapeHTML(u.display_name)})</small>
            </div>
            <button onclick="promptNickname(${u.id}, '${escapeHTML(u.display_name)}')" style="background:none; border:1px solid var(--gemini-dim); color:var(--gemini-dim); padding:2px 8px; border-radius:4px; font-size:10px; cursor:pointer;">Nick</button>
        `;
        resultsContainer.appendChild(div);
    });
}

async function promptNickname(id, name) {
    const nick = prompt(`Set a local nickname for ${name} (leave empty to reset):`);
    if (nick === null) return;
    
    await secureFetch('api.php?action=set_nickname', {
        method: 'POST',
        body: JSON.stringify({ target_user_id: id, nickname: nick })
    });
    alert("Nickname updated!");
    loadRooms(); // Refresh names
}

function selectUser(user) {
    if (selectedUsers.some(u => u.username === user.username)) return;
    selectedUsers.push(user);
    renderSelectedUsers();
    document.getElementById('new-room-user').value = '';
    document.getElementById('user-search-results').innerHTML = '';
}

function removeUser(username) {
    selectedUsers = selectedUsers.filter(u => u.username !== username);
    renderSelectedUsers();
}

function renderSelectedUsers() {
    const container = document.getElementById('selected-users');
    container.innerHTML = '';
    selectedUsers.forEach(u => {
        const span = document.createElement('span');
        span.style = 'background: var(--gemini-blue); color: #fff; padding: 4px 10px; border-radius: 16px; font-size: 12px; display: flex; align-items: center; gap: 5px;';
        span.innerHTML = `${escapeHTML(u.username)} <b onclick="removeUser('${u.username}')" style="cursor:pointer; font-size:14px;">&times;</b>`;
        container.appendChild(span);
    });
}

async function createRoom() {
    if (selectedUsers.length === 0) return;
    
    const usernames = selectedUsers.map(u => u.username);
    const resp = await secureFetch('api.php?action=create_room', {
        method: 'POST',
        body: JSON.stringify({ 
            type: usernames.length > 1 ? 'group' : '1to1', 
            name: usernames.length > 1 ? usernames.join(', ') : null,
            members: usernames 
        })
    });
    const data = await resp.json();
    if (data.success) {
        selectedUsers = [];
        renderSelectedUsers();
        closeModal('create-room-modal');
        loadRooms();
        if (data.room_id) switchRoom(data.room_id, null);
    } else {
        alert(data.error || "Failed to start conversation");
    }
}

// --- Admin Logic ---
window.showModal = (id) => {
    document.getElementById(id).style.display = 'flex';
    if (id === 'admin-modal') loadAdminData();
    if (id === 'settings-modal') loadSettingsUI();
};
window.closeModal = (id) => document.getElementById(id).style.display = 'none';

async function loadSettingsUI() {
    document.getElementById('setting-lock-timer').value = currentUser.auto_lock_timer;
    document.getElementById('setting-arrival-dur').value = currentUser.reveal_on_arrival_duration;
    document.getElementById('setting-theme').value = currentUser.camouflage_theme;
    document.getElementById('setting-style').value = currentUser.camouflage_style || 'none';
    
    // Profile Fields
    document.getElementById('prof-display-name').value = currentUser.display_name;
}

async function updateProfile() {
    const displayName = document.getElementById('prof-display-name').value.trim();
    if (!displayName) return;
    
    const resp = await secureFetch('api.php?action=update_profile', {
        method: 'POST',
        body: JSON.stringify({ display_name: displayName })
    });
    const data = await resp.json();
    if (data.success) {
        currentUser.display_name = displayName;
        const sidebarName = document.getElementById('sidebar-display-name');
        if (sidebarName) sidebarName.textContent = displayName;
        alert("Display name updated!");
    } else {
        alert(data.error || "Update failed");
    }
}

async function updatePassword() {
    const oldPass = document.getElementById('prof-old-pass').value;
    const newPass = document.getElementById('prof-new-pass').value;
    if (!oldPass || !newPass) { alert("Please fill all fields"); return; }
    
    const resp = await secureFetch('api.php?action=update_password', {
        method: 'POST',
        body: JSON.stringify({ old_pass: oldPass, new_pass: newPass })
    });
    const data = await resp.json();
    if (data.success) {
        document.getElementById('prof-old-pass').value = '';
        document.getElementById('prof-new-pass').value = '';
        alert("Password updated successfully!");
    } else {
        alert(data.error || "Update failed");
    }
}

async function updatePIN() {
    const oldPin = document.getElementById('prof-old-pin').value;
    const newPin = document.getElementById('prof-new-pin').value;
    if (!oldPin || !newPin) { alert("Please fill all fields"); return; }
    
    const resp = await secureFetch('api.php?action=update_pin', {
        method: 'POST',
        body: JSON.stringify({ old_pin: oldPin, new_pin: newPin })
    });
    const data = await resp.json();
    if (data.success) {
        document.getElementById('prof-old-pin').value = '';
        document.getElementById('prof-new-pin').value = '';
        alert("PIN updated successfully!");
    } else {
        alert(data.error || "Update failed");
    }
}

async function loadAdminData() {
    const userResp = await secureFetch('api.php?action=admin_get_users');
    const users = await userResp.json();

    const listEl = document.getElementById('admin-user-list');
    listEl.innerHTML = '<h3>Manage Users</h3>';
    users.forEach(u => {
        const div = document.createElement('div');
        div.style = 'display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid rgba(255,255,255,0.05);';
        div.innerHTML = `
            <div>
                <strong>${escapeHTML(u.username)}</strong> (${escapeHTML(u.display_name)})<br>
                <small style="color:var(--gemini-dim)">Status: ${u.status} | Role: ${u.role}</small>
            </div>
            <div>
                ${u.status === 'pending' ? `<button onclick="updateUserStatus(${u.id}, 'active')" style="color:#28a745; background:none; border:1px solid #28a745; padding:4px 8px; border-radius:4px; cursor:pointer;">Approve</button>` : ''}
                ${u.status === 'active' && u.username !== 'kush' ? `<button onclick="updateUserStatus(${u.id}, 'blocked')" style="color:#ff4d4d; background:none; border:1px solid #ff4d4d; padding:4px 8px; border-radius:4px; cursor:pointer;">Block</button>` : ''}
                ${u.status === 'blocked' ? `<button onclick="updateUserStatus(${u.id}, 'active')" style="color:#28a745; background:none; border:1px solid #28a745; padding:4px 8px; border-radius:4px; cursor:pointer;">Unblock</button>` : ''}
            </div>
        `;
        listEl.appendChild(div);
    });

    // Camouflage Editor (Simplified)
    const camList = document.getElementById('admin-cam-list');
    camList.innerHTML = `
        <div style="margin-bottom:15px; display:flex; gap:10px;">
            <select id="new-cam-theme" style="padding:5px; background:#222; color:#fff; border:1px solid #444;"><option value="coding">Coding</option><option value="physics">Physics</option><option value="games">Games</option><option value="beauty">Beauty</option></select>
            <select id="new-cam-type" style="padding:5px; background:#222; color:#fff; border:1px solid #444;"><option value="prompt">Prompt</option><option value="response">Response</option></select>
            <input type="text" id="new-cam-content" placeholder="Content text" style="flex:1; padding:5px; background:#222; color:#fff; border:1px solid #444;">
            <button onclick="addCamouflage()" style="padding:5px 15px; background:var(--gemini-blue); color:#fff; border:none; border-radius:4px; cursor:pointer;">Add</button>
        </div>
    `;
}

async function updateUserStatus(id, status) {
    await secureFetch('api.php?action=admin_update_user', {
        method: 'POST',
        body: JSON.stringify({ id, status, role: 'user' })
    });
    loadAdminData();
}

async function addCamouflage() {
    const theme = document.getElementById('new-cam-theme').value;
    const type = document.getElementById('new-cam-type').value;
    const content = document.getElementById('new-cam-content').value;
    if (!content) return;

    await secureFetch('api.php?action=admin_manage_camouflage', {
        method: 'POST',
        body: JSON.stringify({ sub_action: 'add', theme, type, content })
    });
    document.getElementById('new-cam-content').value = '';
    alert("Camouflage added!");
}
async function handleSend() {
    const content = messageInput.value.trim();
    if (!content || !currentRoomId) return;

    // PIN Verification Intercept (if locked)
    if (isLocked && content.length === 4 && /^\d+$/.test(content)) {
        const resp = await secureFetch('api.php?action=verify_pin', {
            method: 'POST',
            body: JSON.stringify({ pin: content })
        });
        const data = await resp.json();
        if (data.success) {
            messageInput.value = '';
            unlockApp();
            return;
        }
    }

    messageInput.value = '';
    messageInput.style.webkitTextSecurity = 'none';
    
    await secureFetch('api.php?action=send_message', {
        method: 'POST',
        body: JSON.stringify({ room_id: currentRoomId, content, is_image: 0 })
    });

    // Refresh messages immediately to show yours
    await loadMessages();
    addFakeGeneratingResponse();
}

async function loadMessages() {
    if (!currentRoomId || isLocked) return;
    const response = await secureFetch(`api.php?action=get_messages&room_id=${currentRoomId}`);
    const messages = await response.json();
    
    let hasNew = false;
    messages.forEach(msg => {
        if (!renderedMessageIds.has(msg.id)) {
            renderMessage(msg, true);
            renderedMessageIds.add(msg.id);
            hasNew = true;
        }
    });

    if (hasNew) scrollToBottom();
}

function renderMessage(msg, isNew = false) {
    if (document.getElementById(`msg-${msg.id}`)) return;
    const div = document.createElement('div');
    const isSent = msg.sender_id === currentUser.id;
    
    div.className = `message-row ${isSent ? 'sent' : 'received'}`;
    div.id = `msg-${msg.id}`;
    
    let displayContent = applyCamouflage(msg);
    let shouldAutoReveal = false;

    // Check privacy settings for auto-reveal
    if (!isLocked && isNew && !isSent) {
        if (currentUser.reveal_on_arrival_duration > 0) {
            shouldAutoReveal = true;
        } else if (currentUser.auto_reveal_unlocked) {
            displayContent = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : escapeHTML(msg.content);
        }
    }

    const avatarClass = isSent ? 'user' : 'ai colorful-sparkle';
    const avatarIcon = isSent ? '👤' : '✦';
    
    const bodyHtml = `
        <div class="msg-body">
            <div class="msg-text glass" onclick="revealMessage(${JSON.stringify(msg).replace(/"/g, '&quot;')}, this)">${displayContent}</div>
            <div class="timestamp">${formatTimestamp(msg.created_at)} ${isSent ? '' : '• ' + escapeHTML(msg.sender_name)}</div>
        </div>
    `;

    if (isSent) {
        div.innerHTML = `${bodyHtml}<div class="avatar ${avatarClass}">${avatarIcon}</div>`;
    } else {
        div.innerHTML = `<div class="avatar ${avatarClass}">${avatarIcon}</div>${bodyHtml}`;
    }
    
    messagesContainer.appendChild(div);

    if (shouldAutoReveal) {
        const textEl = div.querySelector('.msg-text');
        textEl.innerHTML = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : escapeHTML(msg.content);
        textEl.classList.add('revealed');
        setTimeout(() => {
            textEl.innerHTML = applyCamouflage(msg);
            textEl.classList.remove('revealed');
        }, currentUser.reveal_on_arrival_duration * 1000);
    }
}

async function revealMessage(msg, element) {
    if (isLocked || element.dataset.revealing === "true") return;
    element.dataset.revealing = "true";
    
    const originalContent = element.innerHTML;
    let realContent = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : escapeHTML(msg.content);
    
    // Apply camouflage style if configured
    if (currentUser.camouflage_style === 'c_code' && !msg.is_image) {
        realContent = `<pre style="font-family:monospace; font-size:12px; color:#4285f4;">${formatToCCode(msg.content)}</pre>`;
    }

    element.innerHTML = realContent;
    element.classList.add('revealed');
    
    const duration = currentUser.reveal_on_click_duration || 5;
    setTimeout(() => {
        element.innerHTML = applyCamouflage(msg);
        element.classList.remove('revealed');
        delete element.dataset.revealing;
    }, duration * 1000);
}

function applyCamouflage(msg) {
    const pool = msg.sender_id === currentUser.id ? camouflageLibrary.prompts : camouflageLibrary.responses;
    const index = msg.id % pool.length;
    return escapeHTML(pool[index]);
}

function addFakeGeneratingResponse() {
    const fakeDiv = document.createElement('div');
    fakeDiv.className = 'message-row received fake-response';
    fakeDiv.innerHTML = `
        <div class="avatar ai white-sparkle">✦</div>
        <div class="msg-body">
            <div class="msg-text glass fake-msg">The response is generating... wait few moments, typing...</div>
            <div class="timestamp">${formatTimestamp(new Date().toISOString())} <span class="fake-badge">simulated</span></div>
        </div>
    `;
    messagesContainer.appendChild(fakeDiv);
    scrollToBottom();
    setTimeout(() => {
        fakeDiv.style.opacity = '0';
        fakeDiv.style.transition = 'opacity 0.6s ease';
        setTimeout(() => fakeDiv.remove(), 600);
    }, 5000);
}

// --- Stealth & Locking ---
function toggleLock() { if (!isLocked) lockApp(); }
function lockApp() { isLocked = true; location.reload(); }
function unlockApp() {
    isLocked = false;
    lockStatus.textContent = 'U';
    messagesContainer.innerHTML = '';
    renderedMessageIds.clear();
    startSystemLogs();
    resetInactivityTimer();
    loadMessages();
}

function resetInactivityTimer() {
    if (timerInterval) clearInterval(timerInterval);
    let time = inactivityTimer;
    if (!isLocked) {
        timerInterval = setInterval(() => {
            time--;
            const offset = 88 * (1 - time / inactivityTimer);
            if (timerProgress) timerProgress.style.strokeDashoffset = offset;
            if (time <= 0) lockApp();
        }, 1000);
    }
}

// --- Utils ---
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatTimestamp(ts) {
    const date = new Date(ts);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function scrollToBottom() {
    const container = chatWindow; // Define if not globally accessible
    const cw = document.getElementById('chat-window');
    cw.scrollTop = cw.scrollHeight;
}

function toggleSidebar() {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
}

function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
}

function peekInputText() {
    clearTimeout(inputMaskTimeout); 
    messageInput.style.webkitTextSecurity = 'none'; 
    inputMaskTimeout = setTimeout(() => {
        messageInput.style.webkitTextSecurity = 'disc';
    }, 2000);
}

function handleTyping() {
    isTyping = true;
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => { isTyping = false; }, 3500);
}

function startPolling() {
    loadRooms(); // Initial load
    setInterval(loadRooms, 5000);
    setInterval(loadMessages, 3000);
}

function startStatusCycling() {
    let cycle = 0;
    statusCycleInterval = setInterval(() => {
        cycle = (cycle + 1) % 3;
        thinkingText.textContent = (cycle === 0) ? currentStatusText : "Gemini is thinking...";
    }, 1500);
}

function startSystemLogs() {
    const logs = ["[System_Active]", "[Nodes_Synchronized]", "[Connection_Secure]", "[Analyzing_Clusters]", "[Processing_Neural_Nodes]"];
    setInterval(() => {
        systemLogsContainer.textContent = logs[Math.floor(Math.random() * logs.length)];
    }, 4000);
}

async function saveSettings() {
    const settings = {
        auto_lock_timer: parseInt(document.getElementById('setting-lock-timer').value),
        reveal_on_arrival_duration: parseInt(document.getElementById('setting-arrival-dur').value),
        camouflage_theme: document.getElementById('setting-theme').value,
        camouflage_style: document.getElementById('setting-style').value
    };
    
    await secureFetch('api.php?action=update_privacy_settings', {
        method: 'POST',
        body: JSON.stringify(settings)
    });
    
    await secureFetch('api.php?action=update_theme', {
        method: 'POST',
        body: JSON.stringify({ theme: settings.camouflage_theme })
    });

    location.reload();
}

function formatToCCode(text) {
    const words = text.split(/\s+/).filter(w => w.length > 0);
    if (words.length === 0) return text;
    let code = `#include <stdio.h>\n\nint main() {\n`;
    words.forEach((word, i) => {
        code += `    char* var_${i} = "${word}";\n`;
    });
    code += `\n    // Sequence Output\n`;
    words.forEach((word, i) => {
        code += `    printf("%s ", var_${i});\n`;
    });
    return code + `\n    return 0;\n}`;
}

async function handleImageUpload(e) {
    const file = e.target.files[0];
    if (!file || !currentRoomId) return;
    const reader = new FileReader();
    reader.onload = async (event) => {
        await secureFetch('api.php?action=send_message', {
            method: 'POST',
            body: JSON.stringify({ room_id: currentRoomId, content: event.target.result, is_image: 1 })
        });
        loadMessages();
    };
    reader.readAsDataURL(file);
}
