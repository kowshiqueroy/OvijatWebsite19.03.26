let stayAliveContext = null;
let stayAliveOsc = null;

window.toggleBackgroundMode = function() {
    const btn = document.getElementById('toggle-background-mode');
    
    if (!stayAliveContext) {
        try {
            stayAliveContext = new (window.AudioContext || window.webkitAudioContext)();
            stayAliveOsc = stayAliveContext.createOscillator();
            const gain = stayAliveContext.createGain();
            
            // Slightly higher gain and a low frequency to keep iOS "interested"
            gain.gain.value = 0.01; 
            stayAliveOsc.frequency.value = 100; 
            
            stayAliveOsc.connect(gain);
            gain.connect(stayAliveContext.destination);
            stayAliveOsc.start();
            
            btn.innerHTML = '<span>🔋</span> Stay Active (Background): ON';
            btn.style.color = '#4caf50';
            debugLog("Background Mode: ACTIVE (Heartbeat Boosted)");
        } catch (e) {
            btn.innerHTML = `<span>🔋</span> Error: ${e.name}`;
            stayAliveContext = null;
        }
    } else {
        try { stayAliveOsc.stop(); stayAliveContext.close(); } catch (e) {}
        stayAliveContext = null;
        btn.innerHTML = '<span>🔋</span> Stay Active (Background): OFF';
        btn.style.color = '';
        debugLog("Background Mode: DISABLED");
    }
}

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
let currentRevealed = null; // { msg, el, txt, timeout, timeoutId }
let emptyClickCount = 0;
let emptyClickTimer = null;
let isInitialLoad = true;
let currentProtocol = localStorage.getItem('active_protocol') || 'gemini';

// Create Debug Console UI
const debugConsole = document.createElement('div');
debugConsole.id = 'debug-console';
debugConsole.style.cssText = 'position:fixed; top:10px; right:10px; width:220px; max-height:200px; background:rgba(0,0,0,0.95); color:#00ff00; font-family:monospace; font-size:10px; padding:10px; border-radius:10px; z-index:9999; overflow-y:auto; display:none; border:1px solid #00ff00; pointer-events:auto; box-shadow:0 0 20px rgba(0,255,0,0.3); line-height:1.4;';
document.body.appendChild(debugConsole);

function debugLog(msg) {
    const time = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    line.style.borderBottom = '1px solid #333';
    line.style.padding = '2px 0';
    line.textContent = `[${time}] ${msg}`;
    debugConsole.prepend(line);
    console.log(`[DEBUG] ${msg}`);
}

window.toggleDebugConsole = () => {
    debugConsole.style.display = debugConsole.style.display === 'none' ? 'block' : 'none';
    debugLog("Debug Console Toggled");
};

setTimeout(() => { isInitialLoad = false; }, 4000); // Wait 4s before allowing notifications

function setProtocol(theme) {
    currentProtocol = theme;
    localStorage.setItem('active_protocol', theme);
    document.documentElement.setAttribute('data-theme', theme);
    
    // Update Header
    const title = document.querySelector('.header-left span:not(.sparkle-logo)');
    if (title) title.textContent = theme === 'chatgpt' ? 'ChatGPT' : 'Gemini';
    
    const logo = document.getElementById('panic-logo');
    if (logo) logo.textContent = theme === 'chatgpt' ? '◎' : '✦';

    const thinkingSparkle = document.getElementById('thinking-sparkle');
    if (thinkingSparkle) thinkingSparkle.textContent = theme === 'chatgpt' ? '◎' : '✦';

    // Refresh UI
    renderedMessageIds.clear();
    messagesContainer.innerHTML = '';
    loadMessages();
    debugLog(`Protocol Switched: ${theme.toUpperCase()}`);
}

const camouflageData = {
    'gemini': {
        prompts: [
            "Explain Python list comprehensions.", "How does CSS Flexbox work?", "What is a REST API?", "Explain the difference between SQL and NoSQL.", "How do I optimize a React application?",
            "What are Docker containers used for?", "How does the Git rebase command work?", "Explain the concept of 'hoisting' in JavaScript.", "What is the difference between a process and a thread?", "How does public-key cryptography work?",
            "What is the purpose of a load balancer?", "Explain the Model-View-Controller (MVC) pattern.", "What are the benefits of using a CDN?", "How does the Domain Name System (DNS) work?", "What is a 'deadlock' in operating systems?",
            "How does the event loop in Node.js work?", "Explain the concept of 'closures' in JavaScript.", "What is the difference between synchronous and asynchronous programming?", "How do you implement a binary search tree?", "What is the purpose of the 'this' keyword in JavaScript?",
            "Explain the concept of 'dependency injection'.", "What are the advantages of using TypeScript over JavaScript?", "How does a virtual DOM work in React?", "Explain the difference between 'put' and 'patch' in HTTP.", "What is a 'memory leak' and how do you prevent it?",
            "How does the 'async/await' syntax work in JavaScript?", "What is the purpose of a 'service worker'?", "Explain the concept of 'microservices'.", "How do you implement 'rate limiting' in a web API?", "What is the difference between a 'heap' and a 'stack'?"
        ], 
        responses: [
            "List comprehensions provide a concise way to create lists in Python using a single line of code.", "Flexbox is a one-dimensional layout method for arranging items in rows or columns.", "A REST API is an architectural style for an application program interface that uses HTTP requests.", "SQL databases are relational, while NoSQL databases are non-relational or distributed.", "To optimize React, use Memoization, lazy loading, and avoid unnecessary re-renders.",
            "Docker containers package an application with all its dependencies for consistent deployment.", "Git rebase moves or combines a sequence of commits to a new base commit.", "Hoisting is a JavaScript mechanism where variables and function declarations are moved to the top.", "A process is a program in execution; a thread is the smallest unit of CPU utilization.", "Public-key cryptography uses a pair of keys: a public key for encryption and a private key for decryption.",
            "A load balancer distributes incoming network traffic across multiple servers.", "MVC is a software design pattern for developing user interfaces by separating logic into three parts.", "A CDN stores cached versions of content in multiple locations to speed up delivery.", "DNS translates human-readable domain names into IP addresses for computers to communicate.", "A deadlock is a situation where two or more processes are blocked forever, waiting for each other.",
            "The event loop allows Node.js to perform non-blocking I/O operations by offloading tasks.", "A closure is the combination of a function and the lexical environment within which it was declared.", "Synchronous is blocking, executing one at a time; asynchronous is non-blocking, allowing parallel tasks.", "A binary search tree is a node-based data structure where each node has at most two children.", "The 'this' keyword refers to the object it belongs to, depending on how the function is called.",
            "Dependency injection is a design pattern where an object receives other objects that it depends on.", "TypeScript adds optional static typing and other features to JavaScript, improving code quality.", "A virtual DOM is a lightweight copy of the real DOM, used to optimize UI updates.", "PUT replaces the entire resource; PATCH applies partial modifications to the resource.", "A memory leak is a failure to release memory that is no longer needed, leading to performance issues.",
            "Async/await provides a way to write asynchronous code that looks and behaves like synchronous code.", "A service worker is a script that runs in the background, separate from the web page.", "Microservices is an architectural style that structures an application as a collection of services.", "Rate limiting is used to control the rate of traffic sent or received by a network interface.", "A stack is used for static memory allocation; a heap is used for dynamic memory allocation."
        ] 
    },
    'chatgpt': {
        prompts: [
            "What was the impact of the Industrial Revolution?", "Summarize the key themes of 'The Great Gatsby'.", "How does photosynthesis work in simple terms?", "What are the three branches of the US government?", "Can you explain the theory of supply and demand?",
            "What is the capital of France?", "Who wrote 'To Kill a Mockingbird'?", "How do volcanoes erupt?", "What is the largest mammal on Earth?", "Explain the water cycle to a child.",
            "What are the benefits of daily meditation?", "How do I bake a chocolate cake?", "Summarize the rules of chess.", "What is the distance between the Earth and the Moon?", "Who painted the Mona Lisa?",
            "Explain the difference between weather and climate.", "What is the purpose of the United Nations?", "How does a GPS system work?", "What are the symptoms of a common cold?", "Who was the first person to walk on the moon?"
        ],
        responses: [
            "The Industrial Revolution shifted societies from agrarian to industrial, leading to massive urbanization and technological growth.", "Key themes include the American Dream, social class divide, and the corruption of idealism in the 1920s.", "Photosynthesis is how plants use sunlight, water, and CO2 to create oxygen and energy in the form of sugar.", "The US government is divided into the Legislative, Executive, and Judicial branches to ensure checks and balances.", "Supply and demand is an economic model where price is determined by the availability of a product and the desire for it.",
            "The capital of France is Paris, known for its art, fashion, and culture.", "Harper Lee wrote 'To Kill a Mockingbird', published in 1960.", "Volcanoes erupt when magma from the Earth's mantle rises to the surface due to pressure build-up.", "The blue whale is the largest mammal on Earth, reaching lengths of up to 100 feet.", "The water cycle is how water moves from the ground to the sky as clouds and falls back as rain.",
            "Meditation can reduce stress, improve focus, and promote emotional health and well-being.", "To bake a chocolate cake, you need flour, sugar, cocoa powder, eggs, milk, and butter.", "Chess is a strategy game played on an 8x8 grid where the goal is to checkmate the opponent's king.", "The average distance is about 238,855 miles (384,400 kilometers).", "Leonardo da Vinci painted the Mona Lisa in the early 16th century.",
            "Weather is the short-term state of the atmosphere, while climate is the average weather over a long period.", "The UN aims to maintain international peace, security, and develop friendly relations among nations.", "GPS uses a network of satellites that broadcast signals to receivers on Earth to determine precise location.", "Common symptoms include a runny nose, sneezing, sore throat, and a mild cough.", "Neil Armstrong was the first person to walk on the moon during the Apollo 11 mission in 1969."
        ]
    }
};

const fakeRecentChats = ["Project Phoenix Refactor", "Quantum Mechanics Notes", "Neural Network Architecture", "Thermodynamics Basics", "C Library Optimization"];

// DOM Elements (will be populated in init)
let sidebar, menuToggle, sidebarOverlay, lockStatus, timerProgress, messagesContainer, messageInput, sendBtn, attachBtn, viewTextBtn, fileInput, thinkingArea, thinkingSparkle, thinkingText, systemLogsContainer, panicLogo, notificationBtn;

function initDOMElements() {
    sidebar = document.getElementById('sidebar');
    menuToggle = document.getElementById('menu-toggle');
    sidebarOverlay = document.getElementById('sidebar-overlay');
    lockStatus = document.getElementById('lock-status');
    timerProgress = document.getElementById('timer-progress');
    messagesContainer = document.getElementById('messages-container');
    messageInput = document.getElementById('message-input');
    sendBtn = document.getElementById('send-btn');
    attachBtn = document.getElementById('attach-btn');
    viewTextBtn = document.getElementById('view-text-btn');
    fileInput = document.getElementById('file-input');
    thinkingArea = document.querySelector('.thinking-area');
    thinkingSparkle = document.getElementById('thinking-sparkle');
    thinkingText = document.getElementById('thinking-text');
    systemLogsContainer = document.getElementById('system-logs');
    panicLogo = document.getElementById('panic-logo');
    notificationBtn = document.getElementById('enable-notifications');
}

// Background Upload Progress UI
const progressOverlay = document.createElement('div');
progressOverlay.style.cssText = 'display:none; position:fixed; bottom:80px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.8); color:white; padding:10px 20px; border-radius:20px; z-index:2000; font-size:12px; font-weight:600; box-shadow:0 4px 15px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1);';
progressOverlay.id = 'upload-progress-overlay';
progressOverlay.innerHTML = 'Sending... <span id="upload-percent">0%</span>';
document.body.appendChild(progressOverlay);

let currentStatusText = "Gemini is offline";
let lastSeenTimestamp = 0;
let inputMaskTimeout;


function initFakeUI() { 
    const c = document.getElementById('fake-chats'); if (!c) return; c.innerHTML = ''; 
    fakeRecentChats.forEach(t => { const d = document.createElement('div'); d.className = 'nav-item'; d.innerHTML = `<span>💬</span> ${t}`; c.appendChild(d); }); 
    if (panicLogo) panicLogo.onclick = () => window.location.href = 'call.php?v=' + Date.now();
}

function startApp() {
    initDOMElements();
    initFakeUI();
    
    // Initialize Theme
    document.documentElement.setAttribute('data-theme', currentProtocol);
    setProtocol(currentProtocol);

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
    
    if (viewTextBtn) viewTextBtn.onclick = toggleInputMask;
    if (sendBtn) sendBtn.onclick = handleSend;
    if (attachBtn) attachBtn.onclick = () => fileInput.click();
    
    const cameraBtn = document.getElementById('camera-btn');
    const cameraInput = document.getElementById('camera-input');
    if (cameraBtn) cameraBtn.onclick = () => cameraInput.click();
    
    if (fileInput) fileInput.onchange = handleImageUpload;
    if (cameraInput) cameraInput.onchange = handleImageUpload;
    
    const micBtn = document.getElementById('mic-btn');
    if (micBtn) micBtn.onclick = toggleRecording;
    
    if (notificationBtn) notificationBtn.onclick = enableNotifications;
    if (thinkingArea) thinkingArea.style.display = 'flex';
    if (thinkingSparkle) thinkingSparkle.classList.add('active');
    
    startSystemLogs();
    startStatusCycling();
    setInterval(updateAllTimestamps, 1000);

    // Empty-space click: camouflage revealed msg; triple-tap locks
    document.addEventListener('click', (e) => {
        if (isLocked) return;
        const msgRow = e.target.closest('.message-row');
        const inputArea = e.target.closest('#message-input, #chat-input, .input-area, .input-mimic');
        if (msgRow) return;
        if (currentRevealed) camouflageMsg(currentRevealed);
        if (inputArea) return;
        emptyClickCount++;
        clearTimeout(emptyClickTimer);
        emptyClickTimer = setTimeout(() => { emptyClickCount = 0; }, 1000);
        if (emptyClickCount >= 3) { emptyClickCount = 0; toggleLock(); }
    });

    // Activity listeners to reset inactivity timer
    ['mousedown', 'mousemove', 'keypress', 'touchstart', 'scroll'].forEach(evt => {
        document.addEventListener(evt, () => { if (!isLocked) resetInactivityTimer(); }, { passive: true });
    });
    
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').then(reg => debugLog("SW Registered")).catch(err => debugLog("SW Fail: " + err));
    }
    
    debugLog("App Initialized");
    checkPendingUplinks();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startApp);
} else {
    startApp();
}

async function handleSend() {
    if (isLocked) {
        const val = messageInput.value.trim();
        if (val.length === 4 && !isNaN(val)) {
            const resp = await secureFetch('api.php?action=verify_pin', { method: 'POST', body: JSON.stringify({ pin: val }) });
            if (resp) {
                const data = await resp.json();
                if (data.success) { unlockApp(); messageInput.value = ''; return; }
            }
        }
    }
    const content = messageInput.value.trim();
    if (!content) return;
    messageInput.value = '';
    const resp = await secureFetch('api.php?action=send_message', { method: 'POST', body: JSON.stringify({ content }) });
    if (resp) loadMessages();
}

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
function handleInputPrivacy() { if (messageInput) messageInput.style.webkitTextSecurity = isInputMasked ? 'disc' : 'none'; }
let isInputMasked = true;
function toggleInputMask() {
    if (!messageInput || !viewTextBtn) return;
    isInputMasked = !isInputMasked;
    messageInput.style.webkitTextSecurity = isInputMasked ? 'disc' : 'none';
    viewTextBtn.textContent = isInputMasked ? '[•]' : '[A]';
}
function handleTyping() { if (!isTyping) { isTyping = true; updateMyStatus(); } clearTimeout(typingTimeout); typingTimeout = setTimeout(() => { isTyping = false; updateMyStatus(); }, 3500); }
function scrollToBottom() { const c = messagesContainer ? messagesContainer.parentElement.parentElement : null; if (c) c.scrollTop = c.scrollHeight; }
function toggleLock() { if (!isLocked) { isLocked = true; location.reload(); } }
async function unlockApp() { 
    isLocked = false; lockStatus.textContent = 'U'; thinkingArea.style.display = 'flex'; 
    const uploadBtn = document.getElementById('upload-media-btn');
    if (uploadBtn) uploadBtn.style.display = 'inline-block';
    const protectedLinks = document.getElementById('sidebar-protected-links');
    if (protectedLinks) protectedLinks.style.display = 'block';
    const deleteUnseenBtn = document.getElementById('delete-unseen-btn');
    if (deleteUnseenBtn) deleteUnseenBtn.style.display = 'flex';
    renderedMessageIds.clear(); messagesContainer.innerHTML = ''; 
    startSystemLogs(); resetInactivityTimer(); loadMessages(); 
}

window.deleteMyUnseen = async () => {
    if (!confirm("Delete all unseen messages?")) return;
    const r = await secureFetch('api.php?action=delete_my_unseen', { method: 'POST' });
    if (r && (await r.json()).success) location.reload();
};
window.deleteMySentMessages = async () => {
    if (!confirm("Delete ALL messages sent by you? This cannot be undone.")) return;
    const r = await secureFetch('api.php?action=delete_my_messages', { method: 'POST' });
    if (r) {
        const d = await r.json();
        if (d.success) {
            alert(`Deleted ${d.count} messages.`);
            location.reload();
        } else {
            alert("Failed: " + (d.error || "Unknown error"));
        }
    }
};
function resetInactivityTimer() { inactivityTimer = 60; clearInterval(timerInterval); updateTimerCircle(); if (!isLocked) timerInterval = setInterval(() => { inactivityTimer--; updateTimerCircle(); if (inactivityTimer <= 0) { isLocked = true; location.reload(); } }, 1000); }
function updateTimerCircle() { const offset = 88 * (1 - inactivityTimer / 60); if (timerProgress) timerProgress.style.strokeDashoffset = offset; }

let pendingUploadFile = null;
async function handleImageUpload(e) {
    const file = e.target.files[0]; if (!file) return;
    pendingUploadFile = file; showModal('upload-choice-modal');
}

// Uplink Manager for Resumable & Background Uploads (using IndexedDB for Large Files)
const UPLINK_DB = 'gemini_uplink_db', UPLINK_STORE = 'active_uplinks';
let activeUplinkTask = null;
let uplinkQueue = [];

function openUplinkDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(UPLINK_DB, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(UPLINK_STORE, { keyPath: 'uploadId' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function startUplink(file, destination) {
    const uploadId = Date.now() + '_' + Math.floor(Math.random() * 1000000);
    const CHUNK_SIZE = 2 * 1024 * 1024;
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    
    // Create Local Progress Bubble
    const tempId = 'uplink_' + uploadId;
    const div = document.createElement('div');
    div.className = 'message-row sent'; div.id = tempId;
    const meta = `<div class="msg-meta"><div class="timestamp">${formatTimestamp(new Date())}</div></div>`;
    const terminal = `
        <div class="uplink-terminal" style="width:100%; max-width:100%; padding:10px; border:none; background:transparent; font-size:11px;">
            <div class="uplink-header">[ DATA UPLINK: ${file.name.substring(0, 10)}... ]</div>
            <div class="stat-row">STATUS:   <span class="u-status">QUEUED</span></div>
            <div class="stat-row">PACKET:   <span class="u-packet">00 / ${totalChunks.toString().padStart(2, '0')}</span></div>
            <div class="stat-row">SPEED:    <span class="u-speed">0.0 MB/s</span></div>
            <div class="stat-row">PROGRESS: <span class="u-bar">[>         ]</span></div>
            <div class="stat-row">PERCENT:  [ <span class="u-percent">0.0%</span> ]</div>
        </div>`;
    div.innerHTML = `<div class="msg-body"><div class="msg-text glass" style="min-width:200px;">${terminal}</div>${meta}</div><div class="avatar user">👤</div>`;
    messagesContainer.appendChild(div);
    scrollToBottom();

    // Save state to IndexedDB for resumability
    const state = { uploadId, filename: file.name, totalChunks, currentChunk: 0, destination, fileBlob: file, tempId };
    const db = await openUplinkDB();
    const tx = db.transaction(UPLINK_STORE, 'readwrite');
    tx.objectStore(UPLINK_STORE).put(state);
    
    uplinkQueue.push(state);
    processUplinkQueue();
}

async function processUplinkQueue() {
    if (activeUplinkTask || uplinkQueue.length === 0) return;
    activeUplinkTask = uplinkQueue.shift();
    await runUplink(activeUplinkTask);
    activeUplinkTask = null;
    processUplinkQueue();
}

async function runUplink(state) {
    const bubble = document.getElementById(state.tempId);
    if (!bubble) {
        const div = document.createElement('div');
        div.className = 'message-row sent'; div.id = state.tempId;
        const meta = `<div class="msg-meta"><div class="timestamp">${formatTimestamp(new Date())}</div></div>`;
        const terminal = `
            <div class="uplink-terminal" style="width:100%; max-width:100%; padding:10px; border:none; background:transparent; font-size:11px;">
                <div class="uplink-header">[ DATA UPLINK: ${state.filename.substring(0, 10)}... ]</div>
                <div class="stat-row">STATUS:   <span class="u-status">RECONNECTING</span></div>
                <div class="stat-row">PACKET:   <span class="u-packet">00 / ${state.totalChunks.toString().padStart(2, '0')}</span></div>
                <div class="stat-row">SPEED:    <span class="u-speed">0.0 MB/s</span></div>
                <div class="stat-row">PROGRESS: <span class="u-bar">[>         ]</span></div>
                <div class="stat-row">PERCENT:  [ <span class="u-percent">0.0%</span> ]</div>
            </div>`;
        div.innerHTML = `<div class="msg-body"><div class="msg-text glass" style="min-width:200px;">${terminal}</div>${meta}</div><div class="avatar user">👤</div>`;
        messagesContainer.appendChild(div);
        scrollToBottom();
    }

    const statusEl = document.querySelector(`#${state.tempId} .u-status`);
    const packetEl = document.querySelector(`#${state.tempId} .u-packet`);
    const speedEl = document.querySelector(`#${state.tempId} .u-speed`);
    const barEl = document.querySelector(`#${state.tempId} .u-bar`);
    const percentEl = document.querySelector(`#${state.tempId} .u-percent`);
    
    const CHUNK_SIZE = 2 * 1024 * 1024;
    const statuses = ["ENCRYPTING", "BUFFERING", "COMMITTING", "SIGNAL_SYNC", "VERIFYING"];

    while (state.currentChunk < state.totalChunks) {
        const start = state.currentChunk * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, state.fileBlob.size);
        const chunkBlob = state.fileBlob.slice(start, end);
        const chunkStartTime = Date.now();
        
        const formData = new FormData();
        formData.append('chunk', chunkBlob);
        formData.append('chunkIndex', state.currentChunk);
        formData.append('totalChunks', state.totalChunks);
        formData.append('uploadId', state.uploadId);
        formData.append('filename', state.filename);
        formData.append('targetDir', state.destination === 'vault' ? 'premium_vault' : 'uploads');

        if (statusEl) statusEl.textContent = statuses[state.currentChunk % statuses.length];
        if (packetEl) packetEl.textContent = `${(state.currentChunk + 1).toString().padStart(2, '0')} / ${state.totalChunks.toString().padStart(2, '0')}`;
        const percent = ((state.currentChunk / state.totalChunks) * 100);
        if (percentEl) percentEl.textContent = percent.toFixed(1) + '%';
        
        const barSize = 10;
        const filledSize = Math.floor((state.currentChunk / state.totalChunks) * barSize);
        if (barEl) barEl.textContent = '[' + '='.repeat(filledSize) + '>' + ' '.repeat(Math.max(0, barSize - filledSize - 1)) + ']';

        try {
            const resp = await secureFetch('api.php?action=upload_chunk', { method: 'POST', body: formData });
            if (resp) {
                const data = await resp.json();
                if (data.success) {
                    const duration = (Date.now() - chunkStartTime) / 1000;
                    const speed = (chunkBlob.size / (1024 * 1024)) / duration;
                    if (speedEl) speedEl.textContent = speed.toFixed(1) + ' MB/s';

                    if (data.status === 'partial') {
                        state.currentChunk++;
                        const db = await openUplinkDB();
                        const tx = db.transaction(UPLINK_STORE, 'readwrite');
                        tx.objectStore(UPLINK_STORE).put(state);
                    } else {
                        if (percentEl) percentEl.textContent = '100.0%';
                        if (statusEl) statusEl.textContent = 'COMPLETE';
                        
                        const isVid = state.filename.match(/\.(mp4|webm|mov)$/i);
                        const isAud = state.filename.match(/\.(mp3|wav|ogg|m4a|webm)$/i);
                        const chatMsg = (state.destination === 'vault') ? `📎 Vault: ${state.filename}||${data.path}` : data.path;
                        await secureFetch('api.php?action=send_message', { method: 'POST', body: JSON.stringify({ content: chatMsg, is_image: (!isVid && !isAud) ? 1 : 0, is_video: isVid ? 1 : 0, is_voice: isAud ? 1 : 0 })});
                        
                        const db = await openUplinkDB();
                        const tx = db.transaction(UPLINK_STORE, 'readwrite');
                        tx.objectStore(UPLINK_STORE).delete(state.uploadId);
                        
                        setTimeout(() => {
                            const b = document.getElementById(state.tempId);
                            if (b) b.remove();
                            loadMessages();
                        }, 1000);
                        return;
                    }
                } else throw new Error(data.error);
            } else throw new Error("Connection Lost");
        } catch (err) {
            if (statusEl) statusEl.textContent = 'RETRYING...';
            await new Promise(r => setTimeout(r, 3000));
        }
    }
}

async function checkPendingUplinks() {
    try {
        const db = await openUplinkDB();
        const tx = db.transaction(UPLINK_STORE, 'readonly');
        const req = tx.objectStore(UPLINK_STORE).getAll();
        req.onsuccess = () => {
            if (req.result && req.result.length > 0) {
                req.result.forEach(state => {
                    if (!uplinkQueue.some(q => q.uploadId === state.uploadId)) {
                        uplinkQueue.push(state);
                    }
                });
                processUplinkQueue();
            }
        };
    } catch (e) { debugLog("Uplink Resume Fail"); }
}

window.confirmUploadDestination = async (destination) => {
    closeModal('upload-choice-modal'); if (!pendingUploadFile) return;
    startUplink(pendingUploadFile, destination);
    pendingUploadFile = null;
};

async function loadMessages() {
    const resp = await secureFetch('api.php?action=get_messages'); if (!resp) return;
    const msgs = await resp.json();
    msgs.forEach(msg => {
        if (!renderedMessageIds.has(msg.id)) {
            renderMessage(msg); renderedMessageIds.add(msg.id); scrollToBottom();
            if (!isInitialLoad && document.hidden && msg.sender_id !== CURRENT_USER_ID) {
                const themeName = currentProtocol === 'chatgpt' ? 'ChatGPT' : 'Gemini';
                showSystemNotification(`New ${themeName} Nodes`, "");
            }
        } else {
            const el = document.getElementById(`msg-${msg.id}`);
            if (el && msg.viewed_at && !el.querySelector('.fetch-pill')) {
                const meta = el.querySelector('.msg-meta'), pill = document.createElement('div');
                pill.className = 'fetch-pill'; pill.id = `fetch-${msg.id}`;
                pill.innerHTML = `<div class="fetch-icon-wrapper">⭳<div class="fetch-icon-fill">⭳</div></div><span>│ FETCH [0%]</span>`;
                meta.appendChild(pill);
                if (msg.burn_after) startBurnUI(msg.id, msg.burn_after);
            }
        }
    });
}

function renderMessage(msg) {
    const div = document.createElement('div'), isSent = msg.sender_id === CURRENT_USER_ID;
    div.className = `message-row ${isSent ? 'sent' : 'received'}`; div.id = `msg-${msg.id}`;
    if (!isLocked) { div.style.cursor = 'pointer'; div.onclick = (e) => { e.stopPropagation(); handleMsgClick(msg, div); }; }
    const meta = `<div class="msg-meta"><div class="timestamp" data-created="${msg.created_at}">${formatTimestamp(msg.created_at)}</div>${msg.viewed_at ? `<div class="fetch-pill" id="fetch-${msg.id}"><div class="fetch-icon-wrapper">⭳<div class="fetch-icon-fill">⭳</div></div><span>│ FETCH [0%]</span></div>` : ''}</div>`;
    
    // Dual-Layer structure: Real Layer defines height, Camouflage Layer overlays
    const contentHtml = `
        <div class="real-layer"></div>
        <div class="camouflage-layer">${applyCamouflage(msg)}</div>
    `;
    
    const aiAvatarIcon = (currentProtocol === 'chatgpt') ? '◎' : '✦';
    const aiAvatarClass = (currentProtocol === 'chatgpt') ? 'ai white-sparkle' : 'ai colorful-sparkle';

    div.innerHTML = isSent ? `<div class="msg-body"><div class="msg-text">${contentHtml}</div>${meta}</div><div class="avatar user">👤</div>` : `<div class="avatar ${aiAvatarClass}">${aiAvatarIcon}</div><div class="msg-body"><div class="msg-text">${contentHtml}</div>${meta}</div>`;
    messagesContainer.appendChild(div);

    // Populate the real layer in background to set the bubble height
    updateRealLayer(msg, div.querySelector('.real-layer'));

    if (msg.burn_after) startBurnUI(msg.id, msg.burn_after);
}

async function updateRealLayer(msg, layer) {
    if (!layer) return;
    let mediaContent = msg.content, caption = '', sepIdx = msg.content.indexOf('||');
    if (sepIdx !== -1) { caption = msg.content.substring(0, sepIdx); mediaContent = msg.content.substring(sepIdx + 2); }
    
    const cached = mediaCache.get(msg.id);
    let displayUrl = cached ? (cached.blobUrl || mediaContent) : mediaContent;
    if (!cached && (mediaContent.startsWith('uploads/') || mediaContent.startsWith('premium_vault/'))) {
        const parts = mediaContent.split('/');
        displayUrl = `view.php?dir=${encodeURIComponent(parts[0])}&file=${encodeURIComponent(parts[1] || '')}`;
    }

    let real = '';
    const safeUrl = escapeHTML(displayUrl);
    if (msg.is_image) real = `<img src="${safeUrl}" style="display:block;">`;
    else if (msg.is_voice) real = `<audio controls src="${safeUrl}" style="display:block; width:100%;"></audio>`;
    else if (msg.is_video) real = `<video src="${safeUrl}" controls muted playsinline style="display:block;"></video>`;
    else if (CURRENT_USER_ID === 1) real = `<pre style="white-space:pre-wrap; font-size:11px; margin:0;">#include <stdio.h>\nint main() { printf("${escapeHTML(msg.content)}"); return 0; }</pre>`;
    else real = linkify(escapeHTML(msg.content));
    if (caption) real += `<div style="font-size:11px;color:#aaa;margin-top:8px;text-align:center;">${escapeHTML(caption)}</div>`;
    layer.innerHTML = real;
}

const mediaCache = new Map();
const MEDIA_FETCH_CACHE = 'gemini-media-fetch-v1';
let preloadQueue = [];
let isProcessingPreload = false;

function applyCamouflage(msg) {
    if (msg.is_image || msg.is_video || msg.is_voice) {
        if (currentProtocol === 'gemini') {
            const type = (msg.is_image || msg.is_video) ? 'media' : 'audio';
            const pyCode = [
                "import numpy as np\ndef process_tensor(data):\n    return np.dot(data, np.random.rand(data.shape[1], 128))",
                "import torch\nclass Net(torch.nn.Module):\n    def forward(self, x): return torch.relu(self.fc(x))",
                "import cv2\nimg = cv2.imdecode(np.frombuffer(buffer, np.uint8), -1)\ngray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)",
                "import pandas as pd\ndf = pd.read_parquet('data.parquet')\ncleaned = df.dropna().apply(lambda x: x * 0.95)",
                "audio_stream = wave.open('buffer.wav', 'rb').readframes(-1)"
            ];
            const code = pyCode[msg.id % pyCode.length];
            const isReady = mediaCache.has(msg.id);
            const isSentByMe = msg.sender_id === CURRENT_USER_ID;
            let statusText = isReady ? 'PRELOADED [ <span style="color:#34a853">READY</span> ]' : 'PRELOADING [ <span class="p-percent">0%</span> ]';
            let barWidth = isReady ? '100%' : '0%';
            let barColor = isReady ? '#34a853' : '#8ab4f8';
            if (!isReady && isSentByMe) { statusText = 'UPLINK [ <span style="color:#8ab4f8">SECURED</span> ]'; barWidth = '100%'; }
            const preloadUI = `<div class="preload-container" id="preload-${msg.id}"><div class="preload-status">${statusText}</div><div class="preload-bar-bg"><div class="preload-bar-fill" style="width: ${barWidth}; background: ${barColor};"></div></div></div>`;
            if (!isReady && !isSentByMe) queuePreload(msg);
            return `<div class="code-camouflage ${type}"><pre style="font-size:10px; margin:0; line-height:1.2; color:var(--theme-dim);">${code}</pre>${preloadUI}</div>`;
        } else {
            // ChatGPT Protocol Media Mask
            const prose = [
                "The scientific analysis of oceanic current patterns reveals significant variability in thermal distribution...",
                "Recent advancements in linguistic modeling suggest that cross-cultural syntactic structures maintain...",
                "Economic indicators for the second fiscal quarter point towards a stabilization of global supply chains...",
                "Architectural history highlights the transition from traditional masonry to reinforced concrete frames...",
                "Biological research on avian migration patterns has identified several key magnetic sensory receptors..."
            ];
            return `<div style="font-size:14px; color:var(--theme-dim); font-style:italic;">${prose[msg.id % prose.length]} <br><br> <span style="font-size:11px; opacity:0.6;">[ Attachment_Ref: ${msg.id} ]</span></div>`;
        }
    }
    
    const pool = camouflageData[currentProtocol];
    const items = (msg.sender_id === CURRENT_USER_ID) ? pool.prompts : pool.responses;
    return `<div style="font-size:14px; color:rgba(255,255,255,0.8);">${escapeHTML(items[msg.id % items.length])}</div>`;
}

function queuePreload(msg) {
    if (preloadQueue.some(m => m.id === msg.id) || mediaCache.has(msg.id)) return;
    preloadQueue.push(msg);
    processPreloadQueue();
}

async function processPreloadQueue() {
    if (isProcessingPreload || preloadQueue.length === 0) return;
    isProcessingPreload = true;
    const msg = preloadQueue.shift();
    
    const container = document.getElementById(`preload-${msg.id}`);
    if (container) container.querySelector('.preload-status').textContent = 'PRELOADING [ QUEUED ]';
    
    await preloadMedia(msg);
    isProcessingPreload = false;
    processPreloadQueue();
}

async function preloadMedia(msg) {
    let mediaContent = msg.content;
    let sepIdx = msg.content.indexOf('||');
    if (sepIdx !== -1) mediaContent = msg.content.substring(sepIdx + 2);
    
    let url = mediaContent;
    if (mediaContent.startsWith('uploads/') || mediaContent.startsWith('premium_vault/')) {
        const parts = mediaContent.split('/');
        url = `view.php?dir=${encodeURIComponent(parts[0])}&file=${encodeURIComponent(parts[1] || '')}`;
    } else if (!mediaContent.startsWith('http') && !mediaContent.startsWith('data:')) {
        return;
    }

    try {
        const cache = await caches.open(MEDIA_FETCH_CACHE);
        const cachedResponse = await cache.match(url);
        
        const updateUI = (text, percent = 0, isReady = false, isManual = false) => {
            const container = document.getElementById(`preload-${msg.id}`);
            if (!container) return;
            const statusEl = container.querySelector('.preload-status');
            const fillEl = container.querySelector('.preload-bar-fill');
            if (isReady) {
                statusEl.innerHTML = 'PRELOADED [ <span style="color:#34a853">READY</span> ]';
                fillEl.style.width = '100%';
                fillEl.style.background = '#34a853';
                // Trigger real layer update since we have the data now
                const el = document.getElementById(`msg-${msg.id}`);
                if (el) updateRealLayer(msg, el.querySelector('.real-layer'));
            } else if (isManual) {
                statusEl.innerHTML = 'LARGE_DATA [ <span style="color:#f4b400">MANUAL</span> ]';
                fillEl.style.width = '100%';
                fillEl.style.background = '#f4b400';
            } else {
                statusEl.textContent = `PRELOADING [ ${text} ]`;
                fillEl.style.width = percent + '%';
            }
        };

        if (cachedResponse) {
            const blob = await cachedResponse.blob();
            mediaCache.set(msg.id, { blobUrl: URL.createObjectURL(blob), originalUrl: url });
            updateUI('', 100, true);
            return;
        }

        // Size Guard: Check size before full download
        const headResp = await fetch(url, { method: 'HEAD' });
        const size = parseInt(headResp.headers.get('Content-Length') || '0');
        if (size > 5 * 1024 * 1024) { // 5MB Limit
            updateUI('', 0, false, true);
            return;
        }

        // Proceed with download
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.responseType = 'blob';
        xhr.onprogress = (e) => { if (e.lengthComputable) updateUI(Math.floor((e.loaded / e.total) * 100) + '%', Math.floor((e.loaded / e.total) * 100)); };
        xhr.onload = async () => {
            if (xhr.status === 200) {
                const blob = xhr.response;
                await cache.put(url, new Response(blob));
                mediaCache.set(msg.id, { blobUrl: URL.createObjectURL(blob), originalUrl: url });
                updateUI('', 100, true);
            }
        };
        xhr.onerror = () => updateUI('FAILED', 0);
        xhr.send();
    } catch (e) { debugLog("Preload Error: " + e.message); }
}

function camouflageMsg(entry) {
    if (!entry) return; clearTimeout(entry.timeoutId);
    entry.txt.classList.remove('revealed');
    if (currentRevealed === entry) currentRevealed = null;
}

function handleMsgClick(msg, el) {
    if (isLocked) return;
    const txt = el.querySelector('.msg-text'); if (!txt) return;
    if (currentRevealed && currentRevealed.txt === txt) {
        clearTimeout(currentRevealed.timeoutId);
        currentRevealed.timeoutId = setTimeout(() => camouflageMsg(currentRevealed), 10000);
        if (msg.receiver_id === CURRENT_USER_ID) {
            secureFetch('api.php?action=schedule_delete', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) });
        }
        return;
    }
    if (currentRevealed) camouflageMsg(currentRevealed);
    revealMessage(msg, el, txt);
}

async function revealMessage(msg, el, txt) {
    if (txt.classList.contains('revealed')) return;
    
    // Ensure real layer is updated before showing
    await updateRealLayer(msg, txt.querySelector('.real-layer'));
    
    txt.classList.add('revealed');
    
    // If it's the receiver, mark as viewed
    if (msg.receiver_id === CURRENT_USER_ID) {
        secureFetch('api.php?action=mark_viewed', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) }).then(r => {
            if (r) r.json().then(d => { if (d.success && d.burn_after) { msg.burn_after = d.burn_after; startBurnUI(msg.id, d.burn_after); } });
        });
        secureFetch('api.php?action=schedule_delete', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) });
    }
    
    const tid = setTimeout(() => { 
        if (currentRevealed && currentRevealed.txt === txt) { 
            const m = txt.querySelector('video, audio'); 
            if (m && !m.paused && !m.ended) return; 
            camouflageMsg(currentRevealed); 
        } 
    }, 10000);
    
    currentRevealed = { msg, el, txt, timeoutId: tid };
}

function startBurnUI(id, burnAt) {
    const pill = document.querySelector(`#fetch-${id}`); if (!pill) return;
    const fill = pill.querySelector('.fetch-icon-fill'), text = pill.querySelector('span'), duration = 120;
    const inv = setInterval(() => {
        const now = Math.floor(Date.now()/1000), elapsed = duration - Math.max(0, burnAt - now);
        const percent = Math.min(100, Math.floor((elapsed / duration) * 100));
        if (fill) fill.style.height = `${percent}%`;
        if (text) text.textContent = `│ FETCH [${percent}%]`;
        if (elapsed >= duration) { 
            clearInterval(inv); 
            const el = document.getElementById(`msg-${id}`); 
            if (el) {
                // Purge Persistent Cache
                const cached = mediaCache.get(id);
                if (cached && cached.originalUrl) {
                    caches.open(MEDIA_FETCH_CACHE).then(cache => cache.delete(cached.originalUrl));
                    URL.revokeObjectURL(cached.blobUrl);
                    mediaCache.delete(id);
                }

                const textEl = el.querySelector('.msg-text');
                if (textEl) {
                    textEl.innerHTML = `<div class="skeleton-text long"></div><div class="skeleton-text medium"></div>`;
                    textEl.classList.add('purge-animation');
                }
                setTimeout(() => el.remove(), 800);
            } 
        }
    }, 1000);
}

function startPolling() { 
    const poll = () => { getOtherStatus(); loadMessages(); checkTheater(); };
    poll(); setInterval(poll, 1000); setInterval(updateMyStatus, 2000); 
}

async function checkTheater() {
    const r = await secureFetch('api.php?action=theater_status'); if (!r) return;
    const data = await r.json();
    if (data.partner_in_theater && !data.partner_in_call) showInAppNotification('Team activity', 'Team is in Theater, Join Now', '✦', 'theater', 'youtube.php');
    else hideInAppNotification('theater');
    if (data.partner_in_call) showInAppNotification('Active Meet', 'Team Meet Waiting, Join Now', '✦', 'call', isLocked ? null : 'call.php');
    else hideInAppNotification('call');
}

async function updateMyStatus() { await secureFetch('api.php?action=update_status', { method: 'POST', body: JSON.stringify({ is_typing: isTyping ? 1 : 0, in_theater: 0, in_call: 0 }) }); }

async function getOtherStatus() {
    const r = await secureFetch('api.php?action=get_other_status'); if (!r) return;
    const d = await r.json(); lastSeenTimestamp = d.last_seen;
    const isOnline = d.status === 'active' || d.status === 'typing';
    const isTypingNow = d.status === 'typing';
    
    const color = isTypingNow ? '#ffc107' : (isOnline ? '#28a745' : '#f44336');
    document.documentElement.style.setProperty('--status-color', color);
    
    if (thinkingText) {
        if (isTypingNow) {
            thinkingText.innerHTML = `
                <div class="signal-noise">
                    <span class="signal-label">[BITSTREAM_SYNC_ACTIVE]</span>
                    <div class="signal-bar"></div><div class="signal-bar"></div><div class="signal-bar"></div>
                    <div class="signal-bar"></div><div class="signal-bar"></div><div class="signal-bar"></div>
                </div>`;
        } else {
            thinkingText.textContent = isOnline ? "[NODE_ONLINE]" : "[NODE_OFFLINE]";
        }
    }
    
    if (!isInitialLoad && isOnline && lastOtherOnline === false) { 
        const themeName = currentProtocol === 'chatgpt' ? 'ChatGPT' : 'Gemini';
        showSystemNotification(`${themeName} Online`, "Node is now online."); 
    }
    lastOtherOnline = isOnline;
    if (panicLogo) { panicLogo.style.opacity = isOnline ? "1" : "0.5"; panicLogo.style.filter = isOnline ? "grayscale(0)" : "grayscale(1)"; }
}

function startSystemLogs() {
    const tasks = [
        "Nodes_Synchronized", "Cache_Warming", "TensorFlow_Loaded", "Model_Ready",
        "Context_Injected", "Attention_Heads_Active", "Tokenizer_Init", "Inference_Engine_Online",
        "GPU_Cluster_Linked", "Embedding_Sync", "Entropy_Check", "Batch_Processed",
        "Layer_Normalized", "Weights_Optimized", "Gradient_Flow_OK", "Latency_Stable"
    ];
    let taskIndex = 0;
    let rotationMode = 0; // 0: Task, 1: Relative, 2: Absolute

    setInterval(() => {
        if (!systemLogsContainer) return;
        
        const diff = lastSeenTimestamp ? Math.max(0, Math.floor(Date.now()/1000 - lastSeenTimestamp)) : 999999;
        const absTime = lastSeenTimestamp ? new Date(lastSeenTimestamp * 1000).toLocaleTimeString('en-US', { hour12: false }) : "--:--:--";
        
        if (rotationMode === 0) {
            systemLogsContainer.textContent = `[${tasks[taskIndex % tasks.length]}]`;
            taskIndex++;
            rotationMode = 1;
        } else if (rotationMode === 1) {
            if (diff < 10) {
                systemLogsContainer.textContent = "[Active just now]";
            } else if (lastSeenTimestamp) {
                const h = Math.floor(diff / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
                systemLogsContainer.textContent = `[Active ${h>0?h+'h ':''}${m>0?m+'m ':''}${s}s ago]`;
            } else {
                // Fallback to task if no timestamp
                systemLogsContainer.textContent = `[${tasks[taskIndex % tasks.length]}]`;
                taskIndex++;
            }
            rotationMode = 2;
        } else {
            if (lastSeenTimestamp) {
                systemLogsContainer.textContent = `[Active at ${absTime}]`;
            } else {
                systemLogsContainer.textContent = `[${tasks[taskIndex % tasks.length]}]`;
                taskIndex++;
            }
            rotationMode = 0;
        }
    }, 2000);
}
function startStatusCycling() { 
    setInterval(() => { 
        if (thinkingText && !thinkingText.querySelector('.signal-noise')) {
            const themeName = currentProtocol === 'chatgpt' ? 'ChatGPT' : 'Gemini';
            thinkingText.textContent = (thinkingText.textContent.includes('thinking')) ? (lastOtherOnline ? `[NODE_ONLINE]` : `[NODE_OFFLINE]`) : `${themeName} is thinking...`; 
        }
    }, 3000); 
}

async function toggleRecording() { if (isRecording) stopRecording(); else startRecording(); }
async function startRecording() {
    try {
        const s = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(s); audioChunks = [];
        mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
        mediaRecorder.onstop = () => {
            const b = new Blob(audioChunks, { type: 'audio/webm' });
            const container = document.getElementById('audio-preview-container');
            container.innerHTML = `<audio controls src="${URL.createObjectURL(b)}" style="width:100%;"></audio>`;
            showModal('audio-confirm-modal');
            document.getElementById('btn-confirm-audio').onclick = async () => {
                closeModal('audio-confirm-modal'); progressOverlay.style.display = 'block';
                const reader = new FileReader(); reader.onload = async () => {
                    await secureFetch('api.php?action=send_message', { method: 'POST', body: JSON.stringify({ content: reader.result, is_voice: 1 }) });
                    progressOverlay.style.display = 'none'; loadMessages();
                }; reader.readAsDataURL(b);
            };
            document.getElementById('btn-cancel-audio').onclick = () => closeModal('audio-confirm-modal');
            s.getTracks().forEach(t => t.stop());
        };
        mediaRecorder.start(); isRecording = true; document.getElementById('mic-btn').classList.add('recording'); startRecordingTimer();
    } catch (e) { alert("Mic denied"); }
}
function stopRecording() { if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop(); isRecording = false; document.getElementById('mic-btn').classList.remove('recording'); stopRecordingTimer(); }

function startRecordingTimer() {
    const start = Date.now(), timeEl = document.getElementById('recording-time'), status = document.getElementById('recording-status');
    if (status) status.style.display = 'inline-block';
    recordingTimerInterval = setInterval(() => {
        const sec = Math.floor((Date.now() - start) / 1000);
        if (timeEl) timeEl.textContent = `${Math.floor(sec/60)}:${(sec%60).toString().padStart(2,'0')}`;
    }, 1000);
}
function stopRecordingTimer() { clearInterval(recordingTimerInterval); if (document.getElementById('recording-status')) document.getElementById('recording-status').style.display = 'none'; }

function enableNotifications() {
    if (!("Notification" in window)) { alert("No support"); return; }
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream, isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    if (isIOS && !isStandalone) { alert("iOS: Add to Home Screen first."); return; }
    Notification.requestPermission().then(p => { if (p === "granted") { showSystemNotification("Gemini", "Enabled!"); updateNotificationButton(); } });
}
function updateNotificationButton() {
    const btn = document.getElementById('enable-notifications');
    if (btn && Notification.permission === "granted") {
        btn.innerHTML = '<span>🔔</span> Test Notification';
        btn.onclick = () => {
            showSystemNotification("Gemini", "Minimize app now...");
            if (!document.getElementById('bg-test-btn')) {
                const bgBtn = document.createElement('button'); bgBtn.id = 'bg-test-btn'; bgBtn.className = 'nav-item'; bgBtn.innerHTML = '<span>⏳</span> Background Test';
                bgBtn.onclick = () => { alert("Minimize NOW!"); setTimeout(() => showSystemNotification("Gemini Background", "Success!"), 7000); };
                btn.parentNode.insertBefore(bgBtn, btn.nextSibling);
            }
        };
    }
}
window.clearAppCache = async () => {
    if (!confirm("Are you sure you want to clear the app cache? This will reset the app and may require you to log in again.")) return;
    
    debugLog("Clearing App Cache...");
    
    // 1. Clear Local and Session Storage
    localStorage.clear();
    sessionStorage.clear();
    debugLog("Local/Session Storage cleared.");

    // 2. Unregister Service Workers
    if ('serviceWorker' in navigator) {
        try {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (let reg of registrations) {
                await reg.unregister();
                debugLog("Service Worker unregistered.");
            }
        } catch (e) {
            debugLog("Error unregistering SW: " + e.message);
        }
    }

    // 3. Clear Cache API
    if ('caches' in window) {
        try {
            const cacheNames = await caches.keys();
            for (let name of cacheNames) {
                await caches.delete(name);
                debugLog(`Cache '${name}' deleted.`);
            }
        } catch (e) {
            debugLog("Error clearing caches: " + e.message);
        }
    }

    alert("App Cache Cleared. Reloading...");
    
    // Force a hard reload from server
    window.location.href = window.location.href.split('?')[0] + '?v=' + Date.now();
};

window.showModal = (id) => { document.getElementById(id).style.display = 'flex'; };

window.closeModal = (id) => { document.getElementById(id).style.display = 'none'; };
window.burnYTComments = async () => { if (confirm("Burn YT?")) await secureFetch('api.php?action=burn_yt_comments', { method: 'POST' }); location.reload(); };
window.resetPIN = () => window.showModal('reset-pin-modal');
window.confirmResetPIN = async () => {
    const p = document.getElementById('reset-new-pin').value;
    if (p.length === 4 && (await (await secureFetch('api.php?action=reset_pin', { method: 'POST', body: JSON.stringify({ new_pin: p }) })).json()).success) location.reload();
};
window.confirmNuclearWipe = async () => {
    const p = document.getElementById('nuclear-pin').value;
    if (p.length === 4 && confirm("Wipe all?")) {
        const d = await (await secureFetch('api.php?action=nuclear_wipe', { method: 'POST', body: JSON.stringify({ pin: p }) })).json();
        if (d.success) location.reload(); else alert(d.error);
    }
};
window.updatePIN = async () => {
    const o = document.getElementById('old-pin').value, n = document.getElementById('new-pin').value;
    if (n.length === 4 && (await (await secureFetch('api.php?action=update_pin', { method: 'POST', body: JSON.stringify({ old_pin:o, new_pin:n }) })).json()).success) { alert("PIN OK"); closeModal('pin-modal'); }
};
async function updatePassword() {
    const o = document.getElementById('old-password').value, n = document.getElementById('new-password').value;
    if (n.length >= 6 && (await (await secureFetch('api.php?action=update_password', { method: 'POST', body: JSON.stringify({ old_password: o, new_password: n }) })).json()).success) { alert("Pass OK"); closeModal('password-modal'); }
};

const activeNotifications = new Map();
function showInAppNotification(title, message, icon = '✦', type = 'system', actionUrl = null) {
    if (activeNotifications.has(type)) return;
    const area = document.getElementById('notification-area'); if (!area) return;
    const card = document.createElement('div'); card.className = `notification-card ${type}`;
    card.innerHTML = `<div class="notification-icon">${icon}</div><div class="notification-content"><div class="notification-title">${title}</div><div class="notification-message">${message}</div></div>`;
    if (actionUrl) card.onclick = () => { if (!isLocked) window.location.href = actionUrl; };
    else card.onclick = () => hideInAppNotification(type);
    area.appendChild(card); activeNotifications.set(type, card);
    if (type === 'system') setTimeout(() => hideInAppNotification(type), 6000);
}

function hideInAppNotification(type) {
    const card = activeNotifications.get(type);
    if (card) { card.classList.add('fade-out'); setTimeout(() => { if (card.parentNode) card.remove(); activeNotifications.delete(type); }, 500); }
}

async function showSystemNotification(title, body) {
    debugLog(`🔔 Notification Triggered: ${title}`);
    if (Notification.permission !== "granted") {
        debugLog("❌ Aborted: Permission not granted.");
        return;
    }

    const options = { 
        body: body, 
        icon: 'https://www.gstatic.com/lamda/images/favicon_v1_150160c13ff2af13800c.png',
        tag: Date.now().toString(), // Force unique alert
        renotify: true,
        vibrate: [200, 100, 200]
    };

    // ALWAYS use Service Worker for iOS reliability
    if ('serviceWorker' in navigator) {
        try {
            const reg = await navigator.serviceWorker.ready;
            if (reg) {
                await reg.showNotification(title, options);
                debugLog("✅ SW attempt successful.");
                return;
            }
        } catch (e) {
            debugLog(`⚠️ SW failed: ${e.message}`);
        }
    }

    // Fallback to Native (standard browser)
    try {
        const n = new Notification(title, options);
        n.onclick = () => { window.focus(); n.close(); };
        debugLog("✅ Native fallback successful.");
    } catch (e) {
        debugLog(`❌ All methods failed: ${e.message}`);
    }
}

