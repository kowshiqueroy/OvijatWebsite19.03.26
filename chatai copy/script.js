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

let currentStatusText = "Gemini is offline";
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
    const sidebarClose = document.getElementById('sidebar-close');
    if (sidebarClose) sidebarClose.onclick = closeSidebar;
    lockStatus.onclick = toggleLock;
    
    // Message Input Interceptor
    messageInput.onkeydown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
        resetScrollInactivityTimer();
    };

    messageInput.oninput = () => {
        handleInputPrivacy();
        if (!isLocked) handleTyping();
    };
    messageInput.onfocus = () => {
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            setTimeout(scrollToBottom, 300);
        }
    };
    if (viewTextBtn) viewTextBtn.onclick = peekInputText;
    
    sendBtn.onclick = handleSend;
    attachBtn.onclick = () => fileInput.click();
    fileInput.onchange = handleImageUpload;
    const knockBtn = document.getElementById('knock-btn');
    if (knockBtn) knockBtn.onclick = sendKnockSMS;
    
    // Security
    initSecurity();
    
    // Always show thinking area
    thinkingArea.style.display = 'flex';
    thinkingSparkle.classList.add('active');
    
    startSystemLogs();
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

// --- Helpers ---
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function linkify(text) {
    const urlPattern = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    return text.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener noreferrer" class="chat-link">$1</a>');
}

function formatTimestamp(ts) {
    const date = new Date(ts);
    // Force Asia/Dhaka time zone for display
    return date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: true,
        timeZone: 'Asia/Dhaka' 
    });
}

function getTimeAgo(ts) {
    const created = new Date(ts).getTime();
    const diff = Math.floor((Date.now() - created) / 1000);
    if (diff < 0) return "just now";
    
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    
    let parts = [];
    if (h > 0) parts.push(`${h}h`);
    if (m > 0) parts.push(`${m}m`);
    parts.push(`${s}s`);
    return parts.join(' ') + " ago";
}

function updateAllTimestamps() {
    document.querySelectorAll('.timestamp[data-created]').forEach(el => {
        const created = el.dataset.created;
        const baseTime = formatTimestamp(created);
        const ago = getTimeAgo(created);
        el.textContent = `${baseTime} • ${ago}`;
    });
}

// Initialization
document.addEventListener('DOMContentLoaded', async () => {
    initFakeUI();
    startPolling();
    resetInactivityTimer();
    startScrollInactivityTimer();
    
    // UI Event Listeners
    if (menuToggle) menuToggle.onclick = toggleSidebar;
    if (sidebarOverlay) sidebarOverlay.onclick = closeSidebar;
    const sidebarClose = document.getElementById('sidebar-close');
    if (sidebarClose) sidebarClose.onclick = closeSidebar;
    lockStatus.onclick = toggleLock;
    
    // Message Input Interceptor
    messageInput.onkeydown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
        resetScrollInactivityTimer();
    };

    messageInput.oninput = () => {
        handleInputPrivacy();
        if (!isLocked) handleTyping();
    };
    messageInput.onfocus = () => {
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            setTimeout(scrollToBottom, 300);
        }
    };
    if (viewTextBtn) viewTextBtn.onclick = peekInputText;
    
    sendBtn.onclick = handleSend;
    attachBtn.onclick = () => fileInput.click();
    fileInput.onchange = handleImageUpload;
    const knockBtn = document.getElementById('knock-btn');
    if (knockBtn) knockBtn.onclick = sendKnockSMS;
    
    // Security
    initSecurity();
    
    // Always show thinking area
    thinkingArea.style.display = 'flex';
    thinkingSparkle.classList.add('active');
    
    startSystemLogs();
    startStatusCycling();

    // Start background time-ago updates
    setInterval(updateAllTimestamps, 1000);
});
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
    if (!isTyping) {
        isTyping = true;
        updateMyStatus();
    }
    lastTypeTime = Date.now();
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        isTyping = false;
        updateMyStatus();
    }, 3500);
}

// --- Auto-Scroll Logic ---
function startScrollInactivityTimer() {
    if (scrollInterval) clearInterval(scrollInterval);
}

function resetScrollInactivityTimer() {
    scrollInactivityTimer = 10;
}

function scrollToBottom() {
    const container = messagesContainer.parentElement.parentElement;
    const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 150;
    if (isNearBottom || isNewMessage) {
        container.scrollTop = container.scrollHeight;
    }
}

let isNewMessage = false;

document.onmousemove = () => { 
    if (!isLocked) resetInactivityTimer(); 
};
document.onkeypress = () => { 
    if (!isLocked) resetInactivityTimer(); 
};

let currentStatus = 'offline';
let lastSeenTimestamp = 0;

function startStatusCycling() {
    let cycle = 0;
    if (statusCycleInterval) clearInterval(statusCycleInterval);
    statusCycleInterval = setInterval(() => {
        cycle = (cycle + 1) % 2;
        if (cycle === 0) {
            thinkingText.textContent = "Gemini is thinking...";
        } else {
            thinkingText.textContent = currentStatusText;
        }
        
        const statusColor = getComputedStyle(document.documentElement).getPropertyValue('--status-color');
        if (timerProgress) {
            timerProgress.style.stroke = statusColor;
        }
        
        const shimmerLine = document.querySelector('.shimmer-line');
        if (shimmerLine) {
            shimmerLine.style.background = `linear-gradient(90deg, ${statusColor} 0%, ${statusColor}88 50%, ${statusColor} 100%)`;
            shimmerLine.style.backgroundSize = '200% 100%';
            shimmerLine.style.animation = 'shimmerFlow 2s linear infinite';
            shimmerLine.style.boxShadow = `0 0 10px ${statusColor}44`;
        }
    }, 3000);
}

// --- System Logs ---
function startSystemLogs() {
    const fakeTasks = [
        "Refactoring Project Phoenix...",
        "Optimizing Neural Pathways...",
        "Synthesizing Contextual Data...",
        "Analyzing User Intent...",
        "Mapping Logical Clusters...",
        "Drafting Response Insights...",
        "Securing Node Connection...",
        "Calibrating Thinking Engine..."
    ];
    
    if (logsInterval) clearInterval(logsInterval);
    logsInterval = setInterval(() => {
        if (currentStatus === 'offline' && lastSeenTimestamp > 0) {
            const diffSeconds = Math.floor(Date.now() / 1000) - lastSeenTimestamp;
            const h = Math.floor(diffSeconds / 3600);
            const m = Math.floor((diffSeconds % 3600) / 60);
            const s = diffSeconds % 60;
            const hms = `${h}h ${m}m ${s}s`;
            
            if (systemLogsContainer.textContent.includes('System_Inactive')) {
                systemLogsContainer.textContent = `[${fakeTasks[Math.floor(Math.random() * fakeTasks.length)]}]`;
            } else {
                systemLogsContainer.textContent = `[System_Inactive ${hms} ago]`;
            }
        } else {
            systemLogsContainer.textContent = `[${fakeTasks[Math.floor(Math.random() * fakeTasks.length)]}]`;
        }
    }, 1000);
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
    updateTimerCircle();
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

    if (input === '0000') {
        messageInput.value = '';
        await secureFetch('api.php?action=nuclear_wipe', { method: 'POST' });
        location.reload();
        return;
    }

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
}

async function handleImageUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (!confirm("Confirm before send image?")) return;
    
    const maxSize = 500 * 1024; // 500KB
    
    if (file.size > maxSize) {
        const img = new Image();
        const reader = new FileReader();
        
        reader.onload = (event) => {
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                const scale = Math.sqrt(maxSize / file.size);
                width = Math.floor(width * scale);
                height = Math.floor(height * scale);
                
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    const reader2 = new FileReader();
                    reader2.onload = (event2) => {
                        processImageData(event2.target.result);
                    };
                    reader2.readAsDataURL(blob);
                }, 'image/jpeg', 0.7);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        const reader = new FileReader();
        reader.onload = (event) => {
            processImageData(event.target.result);
        };
        reader.readAsDataURL(file);
    }
}

function processImageData(base64) {
    if (base64.length > 800000) {
        alert("Image too large even after resize. Please use a smaller image.");
        return;
    }
    secureFetch('api.php?action=send_message', {
        method: 'POST',
        body: JSON.stringify({ content: base64, is_image: 1 })
    });
    loadMessages();
}

async function loadMessages() {
    const response = await secureFetch('api.php?action=get_messages');
    const messages = await response.json();
    let hasNew = false;
    messages.forEach(msg => {
        if (!renderedMessageIds.has(msg.id)) {
            renderMessage(msg, true);
            renderedMessageIds.add(msg.id);
            hasNew = true;
        } else {
            // Update existing message if status changed (viewed_at)
            const el = document.getElementById(`msg-${msg.id}`);
            if (el && msg.viewed_at) {
                const meta = el.querySelector('.msg-meta');
                if (meta && !meta.querySelector('.viewed-badge')) {
                    const badge = document.createElement('div');
                    badge.className = 'viewed-badge';
                    badge.innerHTML = '👁️ Viewed';
                    // Insert before burn timer if it exists, otherwise append
                    const burn = meta.querySelector('.burn-timer');
                    if (burn) meta.insertBefore(badge, burn);
                    else meta.appendChild(badge);
                }
                
                // If burn_after just appeared, start timer
                if (msg.burn_after && !meta.querySelector('.burn-timer')) {
                    const burnDiv = document.createElement('div');
                    burnDiv.className = 'burn-timer';
                    burnDiv.id = `burn-${msg.id}`;
                    burnDiv.innerHTML = '🔥 <span></span>';
                    meta.appendChild(burnDiv);
                    startBurnUI(msg.id, msg.burn_after);
                }
            }
        }
    });
    const currentIds = messages.map(m => `msg-${m.id}`);
    document.querySelectorAll('.message-row').forEach(el => {
        if (el.id.startsWith('msg-') && !currentIds.includes(el.id)) {
            el.remove();
        }
    });
    if (hasNew) {
        isNewMessage = true;
        scrollToBottom();
        setTimeout(() => isNewMessage = false, 500);
    }
}

function renderMessage(msg, isNew = false) {
    if (document.getElementById(`msg-${msg.id}`)) return;
    const div = document.createElement('div');
    const isSent = msg.sender_id === CURRENT_USER_ID;
    const isAI = msg.sender_id === 0;
    
    div.className = `message-row ${isSent ? 'sent' : 'received'}`;
    div.id = `msg-${msg.id}`;
    let displayContent = msg.content;
    
    if (isLocked) {
        displayContent = applyCamouflage(msg);
    } else {
        if (CURRENT_USER_ID === 1) {
            displayContent = applyCamouflage(msg);
            div.style.cursor = 'pointer';
            div.onclick = () => revealMessage(msg, div);
        } else if (CURRENT_USER_ID === 2) {
            if (isNew && !msg.viewed_at) {
                displayContent = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : linkify(escapeHTML(msg.content));
                setTimeout(() => {
                    const contentDiv = div.querySelector('.msg-text');
                    if (contentDiv && !contentDiv.dataset.revealedManual) {
                        contentDiv.innerHTML = applyCamouflage(msg);
                        contentDiv.classList.remove('revealed');
                    }
                }, 2000);
            } else {
                displayContent = applyCamouflage(msg);
            }
            div.style.cursor = 'pointer';
            div.onclick = () => revealMessage(msg, div);
        }
    }
    
    const avatarClass = isSent ? 'user' : 'ai colorful-sparkle';
    const avatarIcon = isSent ? '👤' : '✦';
    
    const metaHtml = `
        <div class="msg-meta">
            <div class="timestamp">${formatTimestamp(msg.created_at)}</div>
            ${msg.viewed_at ? '<div class="viewed-badge">👁️ Viewed</div>' : ''}
            ${msg.burn_after ? `<div class="burn-timer" id="burn-${msg.id}">🔥 <span></span></div>` : ''}
        </div>
    `;
    
    if (isSent) {
        div.innerHTML = `
            <div class="msg-body">
                <div class="msg-text glass ${(!isLocked && CURRENT_USER_ID === 2 && isNew && !isAI && !msg.viewed_at) ? 'revealed' : ''}">${displayContent}</div>
                ${metaHtml}
            </div>
            <div class="avatar ${avatarClass}">${avatarIcon}</div>
        `;
    } else {
        div.innerHTML = `
            <div class="avatar ${avatarClass}">${avatarIcon}</div>
            <div class="msg-body">
                <div class="msg-text glass ${(!isLocked && CURRENT_USER_ID === 2 && isNew && !isAI && !msg.viewed_at) ? 'revealed' : ''}">${displayContent}</div>
                ${metaHtml}
            </div>
        `;
    }
    messagesContainer.appendChild(div);

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

    let realContent = msg.is_image ? `<img src="${escapeHTML(msg.content)}" class="real-img">` : linkify(escapeHTML(msg.content));
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
                
                // Add viewed badge immediately
                const meta = element.querySelector('.msg-meta');
                if (meta && !meta.querySelector('.viewed-badge')) {
                    const badge = document.createElement('div');
                    badge.className = 'viewed-badge';
                    badge.innerHTML = '👁️ Viewed';
                    // Insert before burn timer if it exists, otherwise append
                    const burn = meta.querySelector('.burn-timer');
                    if (burn) meta.insertBefore(badge, burn);
                    else meta.appendChild(badge);
                }
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

function startPolling() {
    // Run immediately on start
    updateMyStatus();
    getOtherStatus();
    loadMessages();

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
        
        currentStatus = data.status;
        lastSeenTimestamp = data.last_seen || 0;

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

async function sendKnockSMS() {
    let customText = "";
    if (CURRENT_USER_ID === 2) {
        customText = prompt("Add additional text to the knock message (optional):", "");
        if (customText === null) return; // User cancelled
    } else {
        if (!confirm("Send knock SMS to the other user?")) return;
    }
    
    const btn = document.getElementById('knock-btn');
    btn.style.opacity = '0.5';
    btn.disabled = true;
    
    const resp = await secureFetch('api.php?action=send_knock_sms', {
        method: 'POST',
        body: JSON.stringify({ custom_text: customText })
    });
    const data = await resp.json();
    
    if (data.success) {
        alert("Knock SMS sent successfully!");
    } else {
        alert(data.error || "Failed to send SMS");
    }
    
    btn.style.opacity = '1';
    btn.disabled = false;
}
