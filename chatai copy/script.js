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
setTimeout(() => { isInitialLoad = false; }, 4000); // Wait 4s before allowing notifications

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

const camouflageData = {
    1: { 
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
    2: { 
        prompts: [
            "What is quantum entanglement?", "Explain the theory of general relativity.", "How do black holes form?", "What is the Higgs Boson?", "Explain the double-slit experiment.",
            "What is the Big Bang theory?", "How does a nuclear reactor work?", "What is the function of DNA?", "Explain the laws of thermodynamics.", "What is the significance of the Fibonacci sequence?",
            "How does a solar cell convert light to electricity?", "What are the main types of plate boundaries?", "Explain the process of photosynthesis.", "What is the difference between dark matter and dark energy?", "How does the immune system recognize pathogens?",
            "What is the role of ribosomes in a cell?", "Explain the concept of 'osmosis'.", "How does the Doppler effect work?", "What is the difference between 'mitosis' and 'meiosis'?", "Explain the concept of 'half-life' in radioactive decay.",
            "What are the main components of the human nervous system?", "How does an airplane wing generate lift?", "What is the significance of the 'Ozone layer'?", "Explain the process of 'cellular respiration'.", "What is the difference between 'mass' and 'weight'?",
            "How do 'enzymes' work in biological reactions?", "What is the 'Greenhouse effect'?", "Explain the concept of 'tectonic plates'.", "What are the four states of matter?", "How does the 'carbon cycle' function?"
        ], 
        responses: [
            "Quantum entanglement is a phenomenon where particles become linked and share their state instantly.", "General relativity is Einstein's theory of gravity, describing space-time as a curved fabric.", "Black holes form when a massive star collapses under its own gravity at the end of its life cycle.", "The Higgs Boson is a fundamental particle that gives other particles mass via the Higgs field.", "The double-slit experiment demonstrates that light and matter can display characteristics of both waves and particles.",
            "The Big Bang theory is the prevailing cosmological model for the observable universe from its earliest known periods.", "Nuclear reactors use controlled chain reactions to generate heat, which produces steam for electricity.", "DNA carries the genetic instructions for the development, functioning, and reproduction of all known organisms.", "The laws of thermodynamics define how fundamental physical quantities (temperature, energy, and entropy) behave.", "The Fibonacci sequence is a series of numbers where each number is the sum of the two preceding ones.",
            "Solar cells use the photovoltaic effect to generate an electric current when exposed to light.", "Plate boundaries are where tectonic plates meet, categorized as divergent, convergent, or transform.", "Photosynthesis is the process by which green plants use sunlight to synthesize nutrients from CO2 and water.", "Dark matter interacts via gravity; dark energy is a repulsive force driving the expansion of the universe.", "The immune system uses specialized cells and proteins to identify and neutralize foreign invaders.",
            "Ribosomes are the protein-synthesizing machines of the cell, translating genetic information.", "Osmosis is the spontaneous net movement of solvent molecules through a semi-permeable membrane.", "The Doppler effect is the change in frequency of a wave in relation to an observer moving relative to its source.", "Mitosis results in two identical daughter cells; meiosis results in four genetically unique gametes.", "Half-life is the time required for half of the radioactive atoms in a sample to decay.",
            "The nervous system consists of the brain, spinal cord, and a complex network of nerves.", "Lift is generated by pressure differences between the upper and lower surfaces of an airfoil.", "The ozone layer absorbs most of the sun's harmful ultraviolet radiation before it reaches Earth.", "Cellular respiration is the process by which cells break down glucose to release energy (ATP).", "Mass is the amount of matter in an object; weight is the force of gravity acting on that object.",
            "Enzymes are biological catalysts that speed up chemical reactions by lowering activation energy.", "The greenhouse effect is the process by which radiation from a planet's atmosphere warms the surface.", "Tectonic plates are massive, irregularly shaped slabs of solid rock that make up the Earth's lithosphere.", "The four states of matter are solid, liquid, gas, and plasma.", "The carbon cycle describes the process in which carbon atoms travel from the atmosphere to the Earth and back."
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

async function startApp() {
    initDOMElements();
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
function handleInputPrivacy() { if (messageInput) messageInput.style.webkitTextSecurity = 'disc'; }
function peekInputText() { if (!messageInput) return; clearTimeout(inputMaskTimeout); messageInput.style.webkitTextSecurity = 'none'; inputMaskTimeout = setTimeout(() => { messageInput.style.webkitTextSecurity = 'disc'; }, 2000); }
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

window.confirmUploadDestination = async (destination) => {
    closeModal('upload-choice-modal'); if (!pendingUploadFile) return;
    const file = pendingUploadFile; progressOverlay.style.display = 'block';
    const CHUNK_SIZE = 2 * 1024 * 1024; const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const uploadId = Date.now() + '_' + Math.floor(Math.random() * 1000000);
    let currentChunk = 0;
    const uploadNextChunk = async () => {
        const start = currentChunk * CHUNK_SIZE, end = Math.min(start + CHUNK_SIZE, file.size), chunk = file.slice(start, end);
        const formData = new FormData();
        formData.append('chunk', chunk); formData.append('chunkIndex', currentChunk); formData.append('totalChunks', totalChunks);
        formData.append('uploadId', uploadId); formData.append('filename', file.name);
        formData.append('targetDir', destination === 'vault' ? 'premium_vault' : 'uploads');
        document.getElementById('upload-percent').textContent = `${Math.round((currentChunk / totalChunks) * 100)}%`;
        try {
            const resp = await secureFetch('api.php?action=upload_chunk', { method: 'POST', body: formData });
            if (resp) {
                const data = await resp.json();
                if (data.success) {
                    if (data.status === 'partial') { currentChunk++; await uploadNextChunk(); } 
                    else {
                        const chatMsg = (destination === 'vault') ? `📎 Vault: ${file.name}||${data.path}` : data.path;
                        await secureFetch('api.php?action=send_message', { method: 'POST', body: JSON.stringify({ content: chatMsg, is_image: (!data.is_video && !data.is_voice) ? 1 : 0, is_video: data.is_video ? 1 : 0, is_voice: data.is_voice ? 1 : 0 })});
                        progressOverlay.style.display = 'none'; loadMessages();
                    }
                } else throw new Error(data.error);
            }
        } catch (err) { alert('Upload failed: ' + err.message); progressOverlay.style.display = 'none'; }
    };
    await uploadNextChunk(); pendingUploadFile = null;
};

async function loadMessages() {
    const resp = await secureFetch('api.php?action=get_messages'); if (!resp) return;
    const msgs = await resp.json();
    msgs.forEach(msg => {
        if (!renderedMessageIds.has(msg.id)) {
            renderMessage(msg); renderedMessageIds.add(msg.id); scrollToBottom();
            if (!isInitialLoad && document.hidden && msg.sender_id !== CURRENT_USER_ID) showSystemNotification("New AI Nodes", "");
        } else {
            const el = document.getElementById(`msg-${msg.id}`);
            if (el && msg.viewed_at && !el.querySelector('.viewed-badge')) {
                const meta = el.querySelector('.msg-meta'), badge = document.createElement('div');
                badge.className = 'viewed-badge'; badge.innerHTML = ' ⟳ ';
                const burn = meta.querySelector('.burn-timer'); if (burn) meta.insertBefore(badge, burn); else meta.appendChild(badge);
                if (msg.burn_after && !meta.querySelector('.burn-timer')) {
                    const bDiv = document.createElement('div'); bDiv.className = 'burn-timer'; bDiv.id = `burn-${msg.id}`; bDiv.innerHTML = ' ⚡ <span></span>';
                    meta.appendChild(bDiv); startBurnUI(msg.id, msg.burn_after);
                }
            }
        }
    });
}

function renderMessage(msg) {
    const div = document.createElement('div'), isSent = msg.sender_id === CURRENT_USER_ID;
    div.className = `message-row ${isSent ? 'sent' : 'received'}`; div.id = `msg-${msg.id}`;
    if (!isLocked) { div.style.cursor = 'pointer'; div.onclick = (e) => { e.stopPropagation(); handleMsgClick(msg, div); }; }
    const meta = `<div class="msg-meta"><div class="timestamp" data-created="${msg.created_at}">${formatTimestamp(msg.created_at)}</div>${msg.viewed_at ? '<div class="viewed-badge">⟳ </div>' : ''}${msg.burn_after ? `<div class="burn-timer" id="burn-${msg.id}">⚡ <span></span></div>` : ''}</div>`;
    div.innerHTML = isSent ? `<div class="msg-body"><div class="msg-text glass">${applyCamouflage(msg)}</div>${meta}</div><div class="avatar user">👤</div>` : `<div class="avatar ai colorful-sparkle">✦</div><div class="msg-body"><div class="msg-text glass">${applyCamouflage(msg)}</div>${meta}</div>`;
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

function camouflageMsg(entry) {
    if (!entry) return; clearTimeout(entry.timeoutId);
    entry.txt.innerHTML = applyCamouflage(entry.msg); entry.txt.classList.remove('revealed');
    delete entry.txt.dataset.revealed; if (currentRevealed === entry) currentRevealed = null;
}

function handleMsgClick(msg, el) {
    if (isLocked) return;
    const txt = el.querySelector('.msg-text'); if (!txt) return;
    if (currentRevealed && currentRevealed.txt === txt) {
        clearTimeout(currentRevealed.timeoutId);
        currentRevealed.timeoutId = setTimeout(() => camouflageMsg(currentRevealed), 10000);
        secureFetch('api.php?action=schedule_delete', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) });
        return;
    }
    if (currentRevealed) camouflageMsg(currentRevealed);
    revealMessage(msg, el, txt);
}

async function revealMessage(msg, el, txt) {
    if (txt.dataset.revealed) return; txt.dataset.revealed = "true";
    let mediaContent = msg.content, caption = '', sepIdx = msg.content.indexOf('||');
    if (sepIdx !== -1) { caption = msg.content.substring(0, sepIdx); mediaContent = msg.content.substring(sepIdx + 2); }
    
    // Use view.php proxy for all media files to ensure authorized access
    let displayUrl = mediaContent;
    if (mediaContent.startsWith('uploads/') || mediaContent.startsWith('premium_vault/')) {
        const parts = mediaContent.split('/');
        const dir = parts[0];
        const fileName = parts[1] || '';
        displayUrl = `view.php?dir=${encodeURIComponent(dir)}&file=${encodeURIComponent(fileName)}`;
    }

    let real = '';
    const safeUrl = escapeHTML(displayUrl);
    if (msg.is_image) real = `<img src="${safeUrl}" style="max-width:100%; border-radius:10px;">`;
    else if (msg.is_voice) real = `<audio controls src="${safeUrl}"></audio>`;
    else if (msg.is_video) real = `<video src="${safeUrl}" controls autoplay muted playsinline webkit-playsinline style="max-width:100%; border-radius:10px;"></video>`;
    else if (CURRENT_USER_ID === 1) real = `<pre style="white-space:pre-wrap; font-size:11px;">#include <stdio.h>\nint main() { printf("${escapeHTML(msg.content)}"); return 0; }</pre>`;
    else real = linkify(escapeHTML(msg.content));
    if (caption) real += `<div style="font-size:11px;color:#aaa;margin-top:8px;text-align:center;">${escapeHTML(caption)}</div>`;
    txt.innerHTML = real; txt.classList.add('revealed');
    if (CURRENT_USER_ID === 1 && (msg.is_image || msg.is_voice || msg.is_video) && !mediaContent.startsWith('premium_vault/')) {
        const btn = document.createElement('button'); btn.className = 'modern-btn btn-gradient-blue'; btn.style.marginTop = '12px'; btn.innerHTML = '<span>💎</span> Add to Premium';
        btn.onclick = async (e) => {
            e.stopPropagation(); btn.innerHTML = '<span>⚡</span> Adding...';
            const r = await secureFetch('api.php?action=save_image', { method:'POST', body:JSON.stringify({ media_data:mediaContent, is_voice:msg.is_voice, is_video:msg.is_video }) });
            if ((await r.json()).success) { btn.innerHTML = '<span>✅</span> Added'; btn.disabled = true; }
        };
        txt.appendChild(btn);
    }
    if (msg.receiver_id === CURRENT_USER_ID) {
        secureFetch('api.php?action=mark_viewed', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) }).then(r => {
            if (r) r.json().then(d => { if (d.success && d.burn_after) { msg.burn_after = d.burn_after; startBurnUI(msg.id, d.burn_after); } });
        });
        secureFetch('api.php?action=schedule_delete', { method: 'POST', body: JSON.stringify({ msg_id: msg.id }) });
    }
    const tid = setTimeout(() => { if (currentRevealed && currentRevealed.txt === txt) { const m = txt.querySelector('video, audio'); if (m && !m.paused && !m.ended) return; camouflageMsg(currentRevealed); } }, 10000);
    currentRevealed = { msg, el, txt, timeoutId: tid };
}

function startBurnUI(id, burnAt) {
    const span = document.querySelector(`#burn-${id} span`); if (!span) return;
    const inv = setInterval(() => {
        const rem = Math.max(0, burnAt - Math.floor(Date.now()/1000)); span.textContent = rem + 's';
        if (rem <= 0) { clearInterval(inv); const el = document.getElementById(`msg-${id}`); if (el) el.remove(); }
    }, 1000);
}

function startPolling() { 
    const poll = () => { getOtherStatus(); loadMessages(); checkTheater(); };
    poll(); setInterval(poll, 2000); setInterval(updateMyStatus, 2000); 
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
    const color = (d.status === 'typing') ? '#ffc107' : (isOnline ? '#28a745' : '#f44336');
    document.documentElement.style.setProperty('--status-color', color);
    currentStatusText = (d.status === 'typing') ? "Gemini partner is typing..." : (isOnline ? "Gemini is online" : "Gemini is offline");
    if (thinkingText) thinkingText.textContent = currentStatusText;
    debugLog(`Polling: Tab=${document.hidden?'HIDDEN':'VISIBLE'} Online=${isOnline} Prev=${lastOtherOnline}`);
    if (!isInitialLoad) {
        if (isOnline && lastOtherOnline === false) { 
            debugLog("ONLINE TRANSITION DETECTED"); 
            showSystemNotification("Gemini Online", "Partner is now online."); 
        }
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
function startStatusCycling() { setInterval(() => { if (thinkingText) thinkingText.textContent = (thinkingText.textContent.includes('thinking')) ? currentStatusText : "Gemini is thinking..."; }, 3000); }

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

