let isUnlocked = false, currentUser = null, currentChatUserId = 1;
let lastActivityTime = Date.now(), lastMessageId = 0, isThinking = false;
let autoLockTimer, messageFetchInterval;
let authStep = null, tempUsername = null;
const INACTIVITY_TIMEOUT = 120000, DELETE_DELAY = 10000, circumference = 2 * Math.PI * 14;

const logs = ["[System_Active]", "[Nodes_Synchronized]", "[Connection_Secure]", "[Analyzing_Clusters]", "[Synthesizing_Output]"];
const thinkLogs = ["[Improvising_Context]", "[Drafting_Insights]", "[Processing_Neural_Nodes]"];

document.addEventListener('DOMContentLoaded', () => {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(e => console.error('SW registration failed:', e));
    }
    
    const resetBtn = document.getElementById('resetCacheBtn');
    if (resetBtn) resetBtn.addEventListener('click', resetCache);
    
    checkAuth();
    
    const chatForm = document.getElementById('chatForm');
    if (chatForm) {
        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            sendMessage();
        });
    }
    
    const msgInput = document.getElementById('msgInput');
    if (msgInput) msgInput.addEventListener('input', resetAutoLock);
    
    const timerBtn = document.getElementById('timerBtn');
    if (timerBtn) timerBtn.addEventListener('click', () => { if (isUnlocked) panicLock(); });
    
    ['mousemove', 'touchstart', 'keydown', 'touchmove'].forEach(e => {
        document.addEventListener(e, resetAutoLock, { passive: true });
    });
    
    document.addEventListener('visibilitychange', () => { if (document.hidden) panicLock(); });
    setInterval(updateStatus, 1000);
    setInterval(updateTimerUI, 1000);
});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
}

function checkAuth() {
    fetch('api.php?action=check_auth')
        .then(r => r.json())
        .then(data => {
            if (data.loggedIn) {
                currentUser = data.user;
                loadMessages();
                startAutoLock();
                loadSidebarChats();
                const logoutBtn = document.getElementById('logoutBtn');
                if (logoutBtn) {
                    logoutBtn.style.display = 'block';
                    logoutBtn.addEventListener('click', logout);
                }
                startMessageFetch();
                fetchOnlineStatus();
                updateTimerUI();
            } else {
                startAuthFlow();
                const logoutBtn = document.getElementById('logoutBtn');
                if (logoutBtn) logoutBtn.style.display = 'none';
                stopMessageFetch();
            }
        })
        .catch(e => console.error('Auth check failed:', e));
}

function startAuthFlow() {
    const inner = document.getElementById('chatInner');
    if (inner) inner.innerHTML = '';
    authStep = 'username';
    addMessage('received', 'Hello! I\'m Gemini. What\'s your username?', false);
    const input = document.getElementById('msgInput');
    if (input) {
        input.placeholder = 'Enter username...';
        input.type = 'text';
    }
}

function sendMessage() {
    const input = document.getElementById('msgInput');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    
    if (!currentUser) { handleAuth(text); } 
    else if (!isUnlocked) { verifyPin(text); } 
    else { sendRealMessage(text); }
}

function handleAuth(text) {
    if (authStep === 'username') {
        tempUsername = text;
        authStep = 'check_user';
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=search_users&query=${encodeURIComponent(text)}`
        })
        .then(r => r.json())
        .then(data => {
            const userExists = data.users.some(u => u.username === tempUsername);
            if (userExists) {
                authStep = 'login_password';
                addMessage('sent', text, true);
                addMessage('received', 'User found! Enter your password:', false);
                const input = document.getElementById('msgInput');
                if (input) input.type = 'password';
            } else {
                authStep = 'register_password';
                addMessage('sent', text, true);
                addMessage('received', 'New user! Create a password:', false);
                const input = document.getElementById('msgInput');
                if (input) input.type = 'password';
            }
        })
        .catch(e => console.error('Auth check failed:', e));
    } else if (authStep === 'login_password') {
        addMessage('sent', '••••••••', true);
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=login&username=${encodeURIComponent(tempUsername)}&password=${encodeURIComponent(text)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const input = document.getElementById('msgInput');
                if (input) {
                    input.type = 'text';
                    input.placeholder = 'Enter a prompt here';
                }
                location.reload();
            } else {
                addMessage('received', 'Invalid password. Try again:', false);
            }
        })
        .catch(e => console.error('Login failed:', e));
    } else if (authStep === 'register_password') {
        window.tempPassword = text;
        authStep = 'register_pin';
        addMessage('sent', '••••••••', true);
        addMessage('received', 'Set a 4-digit Chat Unlock PIN:', false);
        const input = document.getElementById('msgInput');
        if (input) input.placeholder = '4-digit PIN...';
    } else if (authStep === 'register_pin') {
        addMessage('sent', '••••', true);
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=register&username=${encodeURIComponent(tempUsername)}&password=${encodeURIComponent(window.tempPassword)}&pin=${encodeURIComponent(text)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const input = document.getElementById('msgInput');
                if (input) {
                    input.type = 'text';
                    input.placeholder = 'Enter a prompt here';
                }
                location.reload();
            } else {
                addMessage('received', 'Username already exists! Try another:', false);
                authStep = 'username';
            }
        })
        .catch(e => console.error('Register failed:', e));
    }
}

function verifyPin(pin) {
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=verify_pin&pin=${encodeURIComponent(pin)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            isUnlocked = true;
            loadMessages();
            addMessage('received', 'Unlocked! Click messages to reveal real content.', false);
            updateTimerUI();
            startMessageFetch();
        } else {
            addMessage('received', 'I\'m a large language model, how can I help you today?', false);
        }
    })
    .catch(e => console.error('PIN verify failed:', e));
}

function sendRealMessage(text) {
    isThinking = true;
    const sparkle = document.getElementById('thinkingSparkle');
    const mainText = document.getElementById('thinkingMainText');
    if (sparkle) sparkle.classList.add('active');
    if (mainText) mainText.textContent = 'Gemini is thinking...';
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=send_message&receiver_id=${currentChatUserId}&text=${encodeURIComponent(text)}`
    })
    .then(r => r.json())
    .then(() => {
        setTimeout(() => {
            isThinking = false;
            loadMessages();
            loadSidebarChats();
            const sparkle = document.getElementById('thinkingSparkle');
            const mainText = document.getElementById('thinkingMainText');
            if (sparkle) sparkle.classList.remove('active');
            if (mainText) mainText.textContent = 'Gemini is ready';
        }, 1500);
    })
    .catch(e => {
        console.error('Send message failed:', e);
        isThinking = false;
    });
}

function loadMessages() {
    fetch(`api.php?action=get_messages&receiver_id=${currentChatUserId}&unlocked=${isUnlocked}`)
        .then(r => r.json())
        .then(data => {
            const inner = document.getElementById('chatInner');
            if (!inner) return;
            inner.innerHTML = '';
            
            if (!isUnlocked) {
                addMessage('received', 'I\'m Gemini, your AI assistant. Ask me anything!', false);
            }
            
            if (data.messages) {
                data.messages.forEach(msg => {
                    const isSent = msg.sender_id === currentUser.id;
                    const div = document.createElement('div');
                    div.className = 'message-row msg-real';
                    div.dataset.id = msg.id;
                    div.dataset.originalText = msg.original_text;
                    
                    const avatarClass = isSent ? 'real-user' : 'real-ai';
                    const avatarIcon = isSent ? '👤' : '✦';
                    
                    div.innerHTML = `
                        <div class="avatar ${avatarClass}">${avatarIcon}</div>
                        <div class="msg-body">
                            <div class="msg-text ${!isUnlocked ? 'camouflage' : ''}" data-camouflage="${msg.camouflage_text}">${msg.camouflage_text}</div>
                        </div>
                    `;
                    
                    if (isUnlocked && !isSent) {
                        const msgText = div.querySelector('.msg-text');
                        if (msgText) msgText.addEventListener('click', () => revealMessage(div));
                    }
                    
                    inner.appendChild(div);
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });
            }
            
            const msgs = document.getElementById('msgs');
            if (msgs) msgs.scrollTop = msgs.scrollHeight;
        })
        .catch(e => console.error('Load messages failed:', e));
}

function revealMessage(row) {
    const msgText = row.querySelector('.msg-text');
    if (!msgText || msgText.classList.contains('revealed')) return;
    
    const originalText = row.dataset.originalText;
    const words = originalText.split(' ').reverse().join(' ');
    const synthesized = `GEMINI Says, "${words}" IS IT Correct or You need more?`;
    
    msgText.textContent = synthesized;
    msgText.classList.add('revealed');
    msgText.classList.remove('camouflage');
    
    const revealTime = Math.min(10000, Math.max(3000, originalText.length * 100));
    setTimeout(() => {
        msgText.textContent = msgText.dataset.camouflage;
        msgText.classList.remove('revealed');
        msgText.classList.add('camouflage');
    }, revealTime);
    
    setTimeout(() => {
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_message&message_id=${row.dataset.id}`
        })
        .then(() => loadMessages())
        .catch(e => console.error('Delete failed:', e));
    }, DELETE_DELAY);
}

function loadSidebarChats() {
    const container = document.getElementById('sidebarChats');
    if (!container) return;
    container.innerHTML = '';
    const chatItem = document.createElement('div');
    chatItem.className = 'recent-item';
    chatItem.innerHTML = `<b>Chat with User ${currentChatUserId}</b>`;
    chatItem.addEventListener('click', () => toggleSidebar());
    container.appendChild(chatItem);
    fetchOnlineStatus();
}

function fetchOnlineStatus() {
    if (!currentUser) return;
    fetch(`api.php?action=get_online_status&user_id=${currentChatUserId}`)
        .then(r => r.json())
        .then(data => {
            const indicator = document.getElementById('onlineIndicator');
            if (indicator) indicator.classList.toggle('online', data.online);
        })
        .catch(e => console.error('Online status failed:', e));
}

function startMessageFetch() {
    stopMessageFetch();
    messageFetchInterval = setInterval(() => { if (isUnlocked) loadMessages(); }, 3000);
}

function stopMessageFetch() {
    if (messageFetchInterval) clearInterval(messageFetchInterval);
}

function updateTimerUI() {
    if (!currentUser) return;
    const timerBtn = document.getElementById('timerBtn');
    const timerCircle = document.getElementById('timerCircle');
    const root = document.documentElement;
    
    if (!timerBtn || !timerCircle) return;
    
    if (isUnlocked) {
        timerBtn.textContent = 'U';
        const remaining = (lastActivityTime + INACTIVITY_TIMEOUT) - Date.now();
        if (remaining <= 0) { panicLock(); return; }
        const progress = remaining / INACTIVITY_TIMEOUT;
        timerCircle.style.strokeDashoffset = circumference * (1 - progress);
        root.style.setProperty('--status-color', '#28a745');
    } else {
        timerBtn.textContent = 'L';
        timerCircle.style.strokeDashoffset = circumference;
        root.style.setProperty('--status-color', '#f44336');
    }
}

function updateStatus() {
    if (!currentUser) return;
    const logEl = document.getElementById('statusLog');
    const root = document.documentElement;
    
    if (!logEl) return;
    
    if (isThinking) {
        root.style.setProperty('--status-color', '#4285f4');
        logEl.textContent = thinkLogs[Math.floor(Date.now() / 1000) % thinkLogs.length];
    } else if (isUnlocked) {
        root.style.setProperty('--status-color', '#28a745');
        logEl.textContent = logs[Math.floor(Date.now() / 1000) % logs.length];
    } else {
        root.style.setProperty('--status-color', '#f44336');
        logEl.textContent = '[Locked_Mode]';
    }
}

function panicLock() {
    isUnlocked = false;
    loadMessages();
    addMessage('received', 'Chat locked. Enter your unlock PIN to continue.', false);
    clearTimeout(autoLockTimer);
    updateTimerUI();
    stopMessageFetch();
}

function resetAutoLock() {
    lastActivityTime = Date.now();
    if (isUnlocked) {
        clearTimeout(autoLockTimer);
        autoLockTimer = setTimeout(panicLock, INACTIVITY_TIMEOUT);
        updateTimerUI();
    }
}

function startAutoLock() {
    autoLockTimer = setTimeout(panicLock, INACTIVITY_TIMEOUT);
}

function logout() {
    fetch('api.php?action=logout')
        .then(() => {
            stopMessageFetch();
            currentUser = null;
            isUnlocked = false;
            location.reload();
        })
        .catch(e => console.error('Logout failed:', e));
}

function searchUser() {
    const username = prompt('Enter username to chat with:');
    if (username) {
        fetch(`api.php?action=search_users&query=${encodeURIComponent(username)}`)
            .then(r => r.json())
            .then(data => {
                if (data.users && data.users.length > 0) {
                    currentChatUserId = data.users[0].id;
                    loadMessages();
                    loadSidebarChats();
                    toggleSidebar();
                }
            })
            .catch(e => console.error('Search failed:', e));
    }
}

function addMessage(type, text, isSent) {
    const inner = document.getElementById('chatInner');
    if (!inner) return;
    const div = document.createElement('div');
    div.className = 'message-row';
    const avatarClass = isSent ? 'real-user' : 'demo';
    const avatarIcon = isSent ? '👤' : '✦';
    div.innerHTML = `
        <div class="avatar ${avatarClass}">${avatarIcon}</div>
        <div class="msg-body"><div class="msg-text">${text}</div></div>
    `;
    inner.appendChild(div);
    const msgs = document.getElementById('msgs');
    if (msgs) msgs.scrollTop = msgs.scrollHeight;
}

function resetCache() {
    if (confirm('Clear all cache, unregister service worker, and reload?')) {
        if ('caches' in window) {
            caches.keys().then(cacheNames => {
                return Promise.all(cacheNames.map(cacheName => caches.delete(cacheName)));
            });
        }
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(registrations => {
                registrations.forEach(reg => reg.unregister());
            });
        }
        localStorage.clear();
        sessionStorage.clear();
        setTimeout(() => location.reload(true), 500);
    }
}

if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', () => {
        document.body.style.height = window.visualViewport.height + 'px';
        window.scrollTo(0, 0);
        const msgs = document.getElementById('msgs');
        if (msgs) msgs.scrollTop = msgs.scrollHeight;
    });
}