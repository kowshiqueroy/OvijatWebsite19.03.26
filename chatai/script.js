// Configuration
// CURRENT_USER_ID and OTHER_USER_ID are now provided by index.php

let isLocked = true;
let inactivityTimer = 60;
let scrollInactivityTimer = 10;
let timerInterval;
let scrollInterval;
let statusInterval;
let pollInterval;
let isTyping = false;
let typingTimeout;
let logsInterval;
let renderedMessageIds = new Set();
let currentPIN = ''; // Handled by verify_pin on backend now

// Realistic AI Camouflage Content
const camouflageData = {
    1: { // User 1: Tech/Coding theme
        prompts: [
            "Explain Python list comprehensions.",
            "How does CSS Flexbox work?",
            "What is a REST API?",
            "Explain the difference between SQL and NoSQL.",
            "How do I optimize a React application?"
        ],
        responses: [
            "List comprehensions provide a concise way to create lists in Python using a single line of code.",
            "Flexbox is a one-dimensional layout method for arranging items in rows or columns.",
            "A REST API is an architectural style for an application program interface that uses HTTP requests.",
            "SQL databases are relational, while NoSQL databases are non-relational or distributed.",
            "To optimize React, use Memoization, lazy loading, and avoid unnecessary re-renders."
        ]
    },
    2: { // User 2: Physics/Science theme
        prompts: [
            "What is quantum entanglement?",
            "Explain the theory of general relativity.",
            "How do black holes form?",
            "What is the Higgs Boson?",
            "Explain the double-slit experiment."
        ],
        responses: [
            "Quantum entanglement is a phenomenon where particles become linked and share their state instantly.",
            "General relativity is Einstein's theory of gravity, describing space-time as a curved fabric.",
            "Black holes form when a massive star collapses under its own gravity at the end of its life cycle.",
            "The Higgs Boson is a fundamental particle that gives other particles mass via the Higgs field.",
            "The double-slit experiment demonstrates that light and matter can display characteristics of both waves and particles."
        ]
    }
};

const fakeRecentChats = [
    "Project Phoenix Refactor",
    "Quantum Mechanics Notes",
    "Neural Network Architecture",
    "Thermodynamics Basics",
    "C Library Optimization"
];

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

let currentStatusText = "Gemini is online";
let statusCycleInterval;
let inputMaskTimeout;

// Initialization
document.addEventListener('DOMContentLoaded', async () => {
    initFakeUI();
    startPolling();
    resetInactivityTimer();
    startScrollInactivityTimer();
    
    // UI Event Listeners
    if (menuToggle) menuToggle.onclick = toggleSidebar;
    if (sidebarOverlay) sidebarOverlay.onclick = closeSidebar;
    lockStatus.onclick = toggleLock;
    
    // Message Input Interceptor
    messageInput.onkeydown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
        if (!isLocked) handleTyping();
        resetScrollInactivityTimer();
    };

    messageInput.oninput = handleInputPrivacy;
    if (viewTextBtn) viewTextBtn.onclick = peekInputText;
    
    sendBtn.onclick = handleSend;
    attachBtn.onclick = () => fileInput.click();
    fileInput.onchange = handleImageUpload;

    // Security
    initSecurity();
    
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

// --- CSRF Helper ---
async function secureFetch(url, options = {}) {
    const defaultHeaders = {
        'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
    };
    
    if (options.body && !(options.body instanceof FormData)) {
        defaultHeaders['Content-Type'] = 'application/json';
    }

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };
    const resp = await fetch(url, options);
    
    if (resp.status === 401) {
        window.location.reload();
        return;
    }
    
    return resp;
}

// --- Sanitization Helper ---
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// --- Sidebar Logic ---
function toggleSidebar() {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
}

function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
}

// --- Privacy Input Logic ---
function handleInputPrivacy() {
    lastTypeTime = Date.now();
    messageInput.style.webkitTextSecurity = 'disc';
}

function peekInputText() {
    clearTimeout(inputMaskTimeout); 
    messageInput.style.webkitTextSecurity = 'none'; 
    
    inputMaskTimeout = setTimeout(() => {
        messageInput.style.webkitTextSecurity = 'disc';
    }, 2000);
}

let lastTypeTime = 0;
function handleTyping() {
    isTyping = true;
    lastTypeTime = Date.now();
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        isTyping = false;
    }, 3500);
}

// --- Auto-Scroll Logic ---
function startScrollInactivityTimer() {
    if (scrollInterval) clearInterval(scrollInterval);
    scrollInterval = setInterval(() => {
        scrollInactivityTimer--;
        if (scrollInactivityTimer <= 0) {
            scrollToBottom();
            scrollInactivityTimer = 10;
        }
    }, 1000);
}

function resetScrollInactivityTimer() {
    scrollInactivityTimer = 10;
}

function scrollToBottom() {
    const container = messagesContainer.parentElement.parentElement;
    container.scrollTop = container.scrollHeight;
}

document.onmousemove = () => { 
    if (!isLocked) resetInactivityTimer(); 
    resetScrollInactivityTimer();
};
document.onkeypress = () => { 
    if (!isLocked) resetInactivityTimer(); 
    resetScrollInactivityTimer();
};

function startStatusCycling() {
    let cycle = 0;
    if (statusCycleInterval) clearInterval(statusCycleInterval);
    statusCycleInterval = setInterval(() => {
        cycle = (cycle + 1) % 3;
        if (cycle === 0) {
            thinkingText.textContent = currentStatusText;
        } else {
            thinkingText.textContent = "Gemini is thinking...";
        }
    }, 1000);
}

// --- System Logs ---
function startSystemLogs() {
    const logs = [
        "[System_Active]",
        "[Nodes_Synchronized]",
        "[Connection_Secure]",
        "[Analyzing_Clusters]",
        "[Synthesizing_Output]",
        "[Improvising_Context]",
        "[Drafting_Insights]",
        "[Processing_Neural_Nodes]"
    ];
    
    if (logsInterval) clearInterval(logsInterval);
    logsInterval = setInterval(() => {
        systemLogsContainer.textContent = logs[Math.floor(Math.random() * logs.length)];
    }, 3000);
}

// --- Fake UI Functions ---
function initFakeUI() {
    const container = document.getElementById('fake-chats');
    if (!container) return;
    container.innerHTML = '';
    fakeRecentChats.forEach(chat => {
        const div = document.createElement('div');
        div.className = 'nav-item';
        div.innerHTML = `<span>💬</span> ${escapeHTML(chat)}`;
        container.appendChild(div);
    });
}

function addFakeAIResponse() {
    if (isLocked) return;
    setTimeout(() => {
        const pool = camouflageData[CURRENT_USER_ID];
        const randomRes = pool.responses[Math.floor(Math.random() * pool.responses.length)];
        renderMessage({
            id: Math.floor(Math.random() * 10000),
            sender_id: 0, 
            content: randomRes,
            is_image: 0,
            created_at: new Date().toISOString(),
            is_fake: true
        });
    }, 2000 + Math.random() * 2000);
}

// --- Stealth & Locking ---
function toggleLock() {
    if (!isLocked) lockApp();
}

function lockApp() {
    isLocked = true;
    lockStatus.textContent = 'L';
    location.reload(); 
}

async function unlockApp() {
    isLocked = false;
    lockStatus.textContent = 'U';
    thinkingArea.style.display = 'flex';
    renderedMessageIds.clear();
    messagesContainer.innerHTML = '';
    startSystemLogs();
    resetInactivityTimer();
    loadMessages();
}

function resetInactivityTimer() {
    inactivityTimer = 60;
    if (timerInterval) clearInterval(timerInterval);
    if (!isLocked) {
        timerInterval = setInterval(() => {
            inactivityTimer--;
            updateTimerCircle();
            if (inactivityTimer <= 0) lockApp();
        }, 1000);
    }
}

function updateTimerCircle() {
    const circumference = 88;
    const offset = circumference * (1 - inactivityTimer / 60);
    if (timerProgress) timerProgress.style.strokeDashoffset = offset;
}

// --- Message Handling ---
async function handleSend() {
    const input = messageInput.value.trim();
    if (!input) return;

    // Nuclear Command Intercept
    if (input === '0000') {
        messageInput.value = '';
        await secureFetch('api.php?action=nuclear_wipe', { method: 'POST' });
        location.reload();
        return;
    }

    // PIN Verification Intercept
    if (isLocked && input.length === 4 && /^\d+$/.test(input)) {
        const resp = await secureFetch('api.php?action=verify_pin', {
            method: 'POST',
            body: JSON.stringify({ pin: input })
        });
        const data = await resp.json();
        if (data.success) {
            messageInput.value = '';
            unlockApp();
            return;
        }
    }

    const content = input;
    messageInput.value = '';
    messageInput.style.webkitTextSecurity = 'none';
    
    await secureFetch('api.php?action=send_message', {
        method: 'POST',
        body: JSON.stringify({ content, is_image: 0 })
    });

    loadMessages();
    if (!isLocked) addFakeAIResponse();
}

async function handleImageUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (!confirm("Confirm before send image?")) return;
    const reader = new FileReader();
    reader.onload = async (event) => {
        let base64 = event.target.result;
        if (base64.length > 800000) { // Limit for Base64 overhead
            alert("Image too large");
            return;
        }
        await secureFetch('api.php?action=send_message', {
            method: 'POST',
            body: JSON.stringify({ content: base64, is_image: 1 })
        });
        loadMessages();
    };
    reader.readAsDataURL(file);
}

async function loadMessages() {
    const response = await secureFetch('api.php?action=get_messages');
    const messages = await response.json();
    messages.forEach(msg => {
        if (!renderedMessageIds.has(msg.id)) {
            renderMessage(msg, true);
            renderedMessageIds.add(msg.id);
        }
    });
    const currentIds = messages.map(m => `msg-${m.id}`);
    document.querySelectorAll('.message-row').forEach(el => {
        if (!currentIds.includes(el.id)) el.remove();
    });
    scrollToBottom();
}

function renderMessage(msg, isNew = false) {
    if (document.getElementById(`msg-${msg.id}`)) return;
    const div = document.createElement('div');
    const isSent = msg.sender_id === CURRENT_USER_ID;
    const isAI = msg.sender_id === 0;
    
    div.className = `message-row ${isSent ? 'sent' : 'received'}`;
    div.id = `msg-${msg.id}`;
    let displayContent = msg.content;
    let autoCamouflage = false;
    
    if (!msg.is_fake) {
        if (isLocked) {
            displayContent = applyCamouflage(msg);
        } else {
            if (CURRENT_USER_ID === 1) {
                displayContent = applyCamouflage(msg);
                div.style.cursor = 'pointer';
                div.onclick = () => revealMessage(msg, div);
            } else if (CURRENT_USER_ID === 2) {
                if (isNew) {
                    autoCamouflage = true;
                    displayContent = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : escapeHTML(msg.content);
                } else {
                    displayContent = applyCamouflage(msg);
                }
                div.style.cursor = 'pointer';
                div.onclick = () => revealMessage(msg, div);
            }
        }
    } else {
        displayContent = escapeHTML(displayContent);
    }
    
    const avatarClass = isSent ? 'user' : 'ai colorful-sparkle';
    const avatarIcon = isSent ? '👤' : '✦';
    div.innerHTML = `
        <div class="avatar ${avatarClass}">${avatarIcon}</div>
        <div class="msg-body">
            <div class="msg-text glass ${(!isLocked && CURRENT_USER_ID === 2 && isNew && !isAI) ? 'revealed' : ''}">${displayContent}</div>
            <div class="timestamp">${formatTimestamp(msg.created_at)}</div>
            ${msg.burn_after ? `<div class="burn-timer" id="burn-${msg.id}">🔥 <span></span></div>` : ''}
        </div>
    `;
    messagesContainer.appendChild(div);
    
    if (autoCamouflage) {
        setTimeout(() => {
            const contentDiv = div.querySelector('.msg-text');
            if (contentDiv && !contentDiv.dataset.revealedManual) {
                contentDiv.innerHTML = applyCamouflage(msg);
                contentDiv.classList.remove('revealed');
            }
        }, 2000);
    }
    if (msg.burn_after) startBurnUI(msg.id, msg.burn_after);
}

function applyCamouflage(msg) {
    if (msg.is_image) return `<div class="image-camouflage">Failed sending or generating image. Error code: 0x882</div>`;
    const isSent = msg.sender_id === CURRENT_USER_ID;
    const pool = camouflageData[CURRENT_USER_ID];
    const items = isSent ? pool.prompts : pool.responses;
    const index = msg.id % items.length;
    return escapeHTML(items[index]);
}

function formatToCCode(text) {
    const words = text.split(/\s+/).filter(w => w.length > 0);
    if (words.length === 0) return escapeHTML(text);
    const reversed = [...words].reverse();
    let code = `#include <stdio.h>\n\nint main() {\n`;
    reversed.forEach((word, i) => {
        let label = (i === 0) ? "target" : (i === reversed.length - 1 ? "subject" : `var_${i}`);
        code += `    char* ${label} = "${escapeHTML(word)}";\n`;
    });
    code += `\n    // Output Sequence\n`;
    reversed.forEach((word, i) => {
        let label = (i === 0) ? "target" : (i === reversed.length - 1 ? "subject" : `var_${i}`);
        code += `    printf("%s ", ${label});\n`;
    });
    code += `\n    return 0;\n}`;
    return code;
}

async function revealMessage(msg, element) {
    const contentDiv = element.querySelector('.msg-text');
    if (contentDiv.dataset.revealedManual) return;
    contentDiv.dataset.revealedManual = "true";
    
    const avatar = element.querySelector('.avatar.ai');
    if (avatar) {
        avatar.classList.remove('colorful-sparkle');
        avatar.classList.add('white-sparkle');
    }

    let realContent = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : escapeHTML(msg.content);
    if (!msg.is_image && CURRENT_USER_ID === 1) realContent = `<pre>${formatToCCode(msg.content)}</pre>`;
    
    contentDiv.innerHTML = realContent;
    contentDiv.classList.add('revealed');
    
    const wordCount = msg.is_image ? 50 : msg.content.split(' ').length;
    const viewTime = Math.max(3, Math.min(10, wordCount * 0.5)) * 1000;
    
    setTimeout(async () => {
        if (!msg.burn_after) {
            const resp = await secureFetch('api.php?action=mark_viewed', {
                method: 'POST',
                body: JSON.stringify({ msg_id: msg.id })
            });
            const data = await resp.json();
            if (data.success) {
                msg.burn_after = data.burn_after;
                startBurnUI(msg.id, data.burn_after);
            }
        }
        contentDiv.innerHTML = applyCamouflage(msg);
        contentDiv.classList.remove('revealed');
        delete contentDiv.dataset.revealedManual;
        if (avatar) {
            avatar.classList.add('colorful-sparkle');
            avatar.classList.remove('white-sparkle');
        }
    }, viewTime);
}

function startBurnUI(id, burnAt) {
    const burnDiv = document.getElementById(`burn-${id}`);
    if (!burnDiv) return;
    const span = burnDiv.querySelector('span');
    const interval = setInterval(() => {
        const remaining = Math.max(0, burnAt - Math.floor(Date.now() / 1000));
        if (span) span.textContent = `${remaining}s`;
        if (remaining <= 0) {
            clearInterval(interval);
            const msgEl = document.getElementById(`msg-${id}`);
            if (msgEl) msgEl.remove();
        }
    }, 1000);
}

function formatTimestamp(ts) {
    const date = new Date(ts);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function startPolling() {
    setInterval(updateMyStatus, 2000);
    pollInterval = setInterval(() => {
        getOtherStatus();
        loadMessages();
    }, 2000);
}

async function updateMyStatus() {
    await secureFetch('api.php?action=update_status', {
        method: 'POST',
        body: JSON.stringify({ is_typing: isTyping ? 1 : 0 })
    });
}

async function getOtherStatus() {
    try {
        const resp = await secureFetch('api.php?action=get_other_status');
        const data = await resp.json();
        const root = document.documentElement;
        if (data.status === 'typing') {
            root.style.setProperty('--status-color', '#ffc107');
            currentStatusText = "Other user is typing...";
        } else if (data.status === 'active') {
            root.style.setProperty('--status-color', '#28a745');
            currentStatusText = "Gemini is online";
        } else {
            root.style.setProperty('--status-color', '#f44336');
            currentStatusText = "Gemini is offline";
        }
    } catch (e) { console.error("Status check failed", e); }
}

function initSecurity() {
    let escCount = 0;
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            escCount++;
            if (escCount === 2) panic();
            setTimeout(() => escCount = 0, 500);
        }
    });
    if (panicLogo) {
        let logoTaps = 0;
        panicLogo.onclick = () => {
            logoTaps++;
            if (logoTaps === 2) panic();
            setTimeout(() => logoTaps = 0, 500);
        };
    }
    const detectDevTools = () => {
        const threshold = 160;
        if (window.outerWidth - window.innerWidth > threshold || window.outerHeight - window.innerHeight > threshold) onDevToolsDetected();
    };
    setInterval(detectDevTools, 1000);
}

function panic() {
    document.body.innerHTML = '';
    window.location.href = 'https://gemini.google.com';
}

function onDevToolsDetected() {
    console.clear();
    isLocked = true;
    document.body.innerHTML = '<div style="background:black;color:red;height:100vh;display:flex;align-items:center;justify-content:center;font-family:monospace;">FATAL ERROR: SECURITY BREACH DETECTED. SYSTEM LOCKED.</div>';
    setTimeout(() => window.location.href = 'https://gemini.google.com', 2000);
}

window.showModal = (id) => document.getElementById(id).style.display = 'flex';
window.closeModal = (id) => document.getElementById(id).style.display = 'none';

async function resetPIN() { showModal('reset-pin-modal'); }

async function confirmResetPIN() {
    const newPin = document.getElementById('reset-new-pin').value;
    if (newPin.length !== 4) return alert("PIN must be 4 digits");
    if (confirm("FINAL WARNING: This will wipe all messages and change your PIN. Proceed?")) {
        await secureFetch('api.php?action=reset_pin', {
            method: 'POST',
            body: JSON.stringify({ new_pin: newPin })
        });
        location.reload();
    }
}

async function updatePIN() {
    const oldPin = document.getElementById('old-pin').value;
    const newPin = document.getElementById('new-pin').value;
    if (newPin.length !== 4) return alert("PIN must be 4 digits");
    const resp = await secureFetch('api.php?action=update_pin', {
        method: 'POST',
        body: JSON.stringify({ old_pin: oldPin, new_pin: newPin })
    });
    const data = await resp.json();
    if (data.success) {
        alert("PIN updated successfully");
        closeModal('pin-modal');
    } else alert(data.error || "Update failed");
}

async function updatePassword() {
    const oldPass = document.getElementById('old-password').value;
    const newPass = document.getElementById('new-password').value;
    if (!newPass) return alert("Password cannot be empty");
    const resp = await secureFetch('api.php?action=update_password', {
        method: 'POST',
        body: JSON.stringify({ old_pass: oldPass, new_pass: newPass })
    });
    const data = await resp.json();
    if (data.success) {
        alert("Password updated successfully");
        closeModal('password-modal');
    } else alert(data.error || "Update failed");
}
