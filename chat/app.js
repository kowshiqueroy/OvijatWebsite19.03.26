/**
 * Secure Ephemeral Messaging - Frontend Application
 * Vanilla JS with AJAX for real-time updates
 */

(function() {
    'use strict';

    const API = {
        auth: 'auth.php',
        contacts: 'contacts.php',
        messages: 'messages.php',
        media: 'media.php'
    };

    const State = {
        isLoggedIn: false,
        user: null,
        inboxUnlocked: false,
        isDuress: false,
        currentContactId: null,
        currentThreadUnlocked: false,
        lockTimer: null,
        inactivityTimer: null,
        messageTimers: {},
        holdTimer: null,
        holdTimeout: null
    };

    const Elements = {
        screens: {
            login: document.getElementById('login-screen'),
            inbox: document.getElementById('inbox-screen'),
            chat: document.getElementById('chat-screen')
        },
        loginForm: document.getElementById('login-form'),
        registerForm: document.getElementById('register-form'),
        inboxList: document.getElementById('inbox-list'),
        messagesList: document.getElementById('messages-list'),
        modal: {
            overlay: document.getElementById('modal-overlay'),
            title: document.getElementById('modal-title'),
            body: document.getElementById('modal-body'),
            close: document.getElementById('modal-close')
        },
        chat: {
            name: document.getElementById('chat-contact-name'),
            input: document.getElementById('message-input'),
            send: document.getElementById('send-btn'),
            compose: document.getElementById('compose-area'),
            back: document.getElementById('back-btn'),
            lock: document.getElementById('lock-thread-btn')
        },
        reveal: {
            overlay: document.getElementById('reveal-overlay'),
            content: document.getElementById('revealed-content'),
            timer: document.getElementById('reveal-timer')
        },
        lockTimer: document.getElementById('lock-timer')
    };

    function showScreen(screenName) {
        Object.values(Elements.screens).forEach(s => s.classList.add('hidden'));
        Elements.screens[screenName].classList.remove('hidden');
    }

    function ajax(url, data = {}) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            fetch(url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) resolve(res);
                    else reject(res);
                })
                .catch(reject);
        });
    }

    function handleError(err, container) {
        console.error(err);
        if (container) {
            container.textContent = err.message || 'An error occurred';
        }
    }

    // Auth Tab Switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const tab = btn.dataset.tab;
            if (tab === 'login') {
                Elements.loginForm.classList.remove('hidden');
                Elements.registerForm.classList.add('hidden');
            } else {
                Elements.loginForm.classList.add('hidden');
                Elements.registerForm.classList.remove('hidden');
            }
        });
    });

    // Login
    Elements.loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = Elements.loginForm.querySelector('button');
        const err = document.getElementById('login-error');
        btn.disabled = true;
        err.textContent = '';
        
        try {
            const res = await ajax(API.auth, {
                action: 'login',
                username: Elements.loginForm.username.value,
                password: Elements.loginForm.password.value
            });
            State.isLoggedIn = true;
            State.user = res.user;
            State.isDuress = res.user.is_duress || false;
            loadInbox();
        } catch (err) {
            err.textContent = err.message || 'Invalid credentials or user not found';
        } finally {
            btn.disabled = false;
        }
    });

    // Register
    Elements.registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = Elements.registerForm.querySelector('button');
        const err = document.getElementById('register-error');
        btn.disabled = true;
        err.textContent = '';
        
        try {
            const res = await ajax(API.auth, {
                action: 'register',
                username: Elements.registerForm.username.value,
                display_name: Elements.registerForm.display_name.value,
                password: Elements.registerForm.password.value
            });
            State.isLoggedIn = true;
            State.user = res.user;
            loadInbox();
        } catch (err) {
            err.textContent = err.message;
        } finally {
            btn.disabled = false;
        }
    });

    // Logout
    document.getElementById('logout-btn').addEventListener('click', async () => {
        await ajax(API.auth, { action: 'logout' });
        State.isLoggedIn = false;
        State.user = null;
        State.inboxUnlocked = false;
        showScreen('login');
    });

    // Inbox Loading
    async function loadInbox() {
        showScreen('inbox');
        Elements.inboxList.innerHTML = '<div class="loading">Loading configurations...</div>';
        
        try {
            const res = await ajax(API.contacts, { action: 'inbox' });
            renderInbox(res.inbox, res.is_duress);
            State.inboxUnlocked = res.inbox.length > 0;
            startInboxTimers();
        } catch (err) {
            Elements.inboxList.innerHTML = '<div class="loading">Failed to load</div>';
        }
    }

    function renderInbox(contacts, isDuress) {
        if (contacts.length === 0) {
            Elements.inboxList.innerHTML = '<div class="setting-item"><div class="setting-info"><div class="setting-title">No configurations</div></div></div>';
            return;
        }

        Elements.inboxList.innerHTML = contacts.map(c => `
            <div class="setting-item" data-contact-id="${c.id}" data-is-dummy="${c.is_dummy || false}">
                <div class="setting-info">
                    <div class="setting-title">${escapeHtml(c.display_name)}</div>
                    <div class="setting-subtitle">${escapeHtml(c.last_message || 'No messages')}</div>
                </div>
                <span class="setting-arrow">›</span>
            </div>
        `).join('');

        document.querySelectorAll('.setting-item').forEach(item => {
            item.addEventListener('click', () => handleInboxItemClick(item));
        });
    }

    async function handleInboxItemClick(item) {
        const contactId = parseInt(item.dataset.contactId);
        const isDummy = item.dataset.isDummy === 'true';

        if (State.isDuress || isDummy || !State.inboxUnlocked) {
            State.currentContactId = contactId;
            State.currentThreadUnlocked = true;
            loadMessages(contactId);
            return;
        }

        if (!State.inboxUnlocked || !State.currentThreadUnlocked || State.currentContactId !== contactId) {
            showModal('Enter PIN', `
                <div class="form-group">
                    <label>Thread PIN</label>
                    <input type="password" id="thread-pin" maxlength="10" autocomplete="off">
                </div>
                <button class="btn-primary" id="unlock-btn">Unlock</button>
            `);
            
            document.getElementById('unlock-btn').addEventListener('click', async () => {
                const pin = document.getElementById('thread-pin').value;
                try {
                    const res = await ajax(API.contacts, {
                        action: 'unlock',
                        contact_id: contactId,
                        pin: pin
                    });
                    closeModal();
                    State.currentContactId = contactId;
                    State.currentThreadUnlocked = true;
                    loadMessages(contactId);
                } catch (err) {
                    alert(err.message || 'Invalid PIN');
                }
            });
        } else {
            loadMessages(contactId);
        }
    }

    // Messages Loading
    async function loadMessages(contactId) {
        showScreen('chat');
        Elements.messagesList.innerHTML = '<div class="loading">Loading messages...</div>';
        
        try {
            const res = await ajax(API.messages, {
                action: 'get_messages',
                contact_id: contactId
            });

            Elements.chat.name.textContent = res.contact_name || 'System Config';
            Elements.chat.compose.classList.toggle('hidden', res.is_dummy);
            renderMessages(res.messages, res.is_dummy);
            
            if (State.messageRefreshInterval) clearInterval(State.messageRefreshInterval);
            State.messageRefreshInterval = setInterval(() => {
                if (State.currentContactId === contactId && showScreen === 'chat') {
                    refreshMessages(contactId);
                }
            }, 3000);
            
            startMessageExpirationCheck();
        } catch (err) {
            if (err.locked) {
                showScreen('inbox');
                State.currentContactId = null;
                State.currentThreadUnlocked = false;
            }
            Elements.messagesList.innerHTML = '<div class="loading">Failed to load</div>';
        }
    }

    async function refreshMessages(contactId) {
        try {
            const res = await ajax(API.messages, {
                action: 'get_messages',
                contact_id: contactId
            });
            renderMessages(res.messages, res.is_dummy);
        } catch (err) {
            console.error('Failed to refresh messages');
        }
    }

    function renderMessages(messages, isDummy) {
        Elements.messagesList.innerHTML = messages.map(m => `
            <div class="system-message ${m.is_me ? 'sent' : ''}" 
                 data-message-id="${m.id}" 
                 data-real-content="${escapeHtml(m.real_content || m.content)}"
                 data-view-duration="${m.view_duration || 5}"
                 data-is-dummy="${isDummy || m.is_dummy || false}">
                <span class="msg-icon"></span>
                <span class="msg-text">${isDummy || m.is_dummy ? escapeHtml(m.content) : escapeHtml(m.camouflage)}</span>
                <div class="msg-meta">${formatTime(m.created_at)}</div>
            </div>
        `).join('');

        document.querySelectorAll('.system-message').forEach(msg => {
            msg.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                handleMessagePress(msg);
            });
            
            msg.addEventListener('touchstart', (e) => {
                e.preventDefault();
                handleMessagePress(msg);
            }, { passive: false });
        });
    }

    function handleMessagePress(msgEl) {
        const isDummy = msgEl.dataset.isDummy === 'true';
        
        if (State.isDuress || isDummy) {
            State.revealTimeout = setTimeout(() => {
                showReveal(msgEl.dataset.realContent, parseInt(msgEl.dataset.viewDuration));
            }, 500);
            return;
        }

        const messageId = parseInt(msgEl.dataset.messageId);
        
        ajax(API.messages, { action: 'view_message', message_id: messageId })
            .then(() => {
                const duration = parseInt(msgEl.dataset.viewDuration) || 5;
                showReveal(msgEl.dataset.realContent, duration);
            })
            .catch(err => {
                if (err.message === 'Message expired') {
                    msgEl.remove();
                }
            });
    }

    function showReveal(content, duration) {
        Elements.reveal.content.textContent = content;
        Elements.reveal.overlay.classList.add('active');
        
        let remaining = duration;
        Elements.reveal.timer.textContent = `Visible for ${remaining}s`;
        
        const countdown = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(countdown);
                hideReveal();
            } else {
                Elements.reveal.timer.textContent = `Visible for ${remaining}s`;
            }
        }, 1000);

        Elements.reveal.overlay.dataset.timerId = countdown;
    }

    function hideReveal() {
        const timerId = Elements.reveal.overlay.dataset.timerId;
        if (timerId) clearInterval(parseInt(timerId));
        
        Elements.reveal.overlay.classList.remove('active');
        Elements.reveal.content.textContent = '';
    }

    Elements.reveal.overlay.addEventListener('click', hideReveal);

    // Send Message
    Elements.chat.send.addEventListener('click', sendMessage);
    Elements.chat.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    async function sendMessage() {
        const content = Elements.chat.input.value.trim();
        if (!content || !State.currentContactId) return;

        try {
            await ajax(API.messages, {
                action: 'send',
                contact_id: State.currentContactId,
                content: content
            });
            Elements.chat.input.value = '';
            loadMessages(State.currentContactId);
        } catch (err) {
            alert(err.message || 'Failed to send');
        }
    }

    // Back Button
    Elements.chat.back.addEventListener('click', () => {
        State.currentContactId = null;
        State.currentThreadUnlocked = false;
        showScreen('inbox');
        loadInbox();
    });

    // Lock Thread
    Elements.chat.lock.addEventListener('click', async () => {
        await ajax(API.contacts, { action: 'lock' });
        State.currentThreadUnlocked = false;
        showScreen('inbox');
        loadInbox();
    });

    // Modal Functions
    function showModal(title, bodyContent) {
        Elements.modal.title.textContent = title;
        Elements.modal.body.innerHTML = bodyContent;
        Elements.modal.overlay.classList.remove('hidden');
    }

    function closeModal() {
        Elements.modal.overlay.classList.add('hidden');
        Elements.modal.body.innerHTML = '';
    }

    Elements.modal.close.addEventListener('click', closeModal);
    Elements.modal.overlay.addEventListener('click', (e) => {
        if (e.target === Elements.modal.overlay) closeModal();
    });

    // Add Contact
    document.getElementById('add-contact-btn').addEventListener('click', () => {
        showModal('Add Contact', `
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="search-username" placeholder="Enter username">
            </div>
            <div id="search-result"></div>
            <div id="add-contact-form" class="hidden">
                <div class="form-group">
                    <label>Display Name (for this thread)</label>
                    <input type="text" id="contact-display-name">
                </div>
                <div class="form-group">
                    <label>Thread PIN (4-10 chars)</label>
                    <input type="password" id="contact-pin" maxlength="10">
                </div>
                <button class="btn-primary" id="confirm-add-btn">Add Contact</button>
            </div>
        `);

        document.getElementById('search-username').addEventListener('input', debounce(async (e) => {
            const username = e.target.value.trim();
            if (username.length < 3) return;
            
            const resultDiv = document.getElementById('search-result');
            resultDiv.textContent = 'Searching...';
            
            try {
                const res = await ajax(API.contacts, { action: 'search', username: username });
                resultDiv.innerHTML = `Found: <strong>${escapeHtml(res.user.username)}</strong>`;
                document.getElementById('add-contact-form').classList.remove('hidden');
                document.getElementById('confirm-add-btn').onclick = () => addContact(res.user.id);
            } catch (err) {
                resultDiv.textContent = err.message;
                document.getElementById('add-contact-form').classList.add('hidden');
            }
        }, 500));
    });

    async function addContact(userId) {
        const displayName = document.getElementById('contact-display-name').value.trim();
        const pin = document.getElementById('contact-pin').value;
        
        if (!displayName || !pin) {
            alert('All fields required');
            return;
        }

        try {
            await ajax(API.contacts, {
                action: 'add',
                contact_username: document.getElementById('search-username').value.trim(),
                display_name: displayName,
                thread_pin: pin
            });
            closeModal();
            loadInbox();
        } catch (err) {
            alert(err.message);
        }
    }

    // Inbox Timers
    function startInboxTimers() {
        if (State.lockTimer) clearInterval(State.lockTimer);
        
        State.lockTimer = setInterval(async () => {
            try {
                const res = await ajax(API.contacts, { action: 'status' });
                
                if (!res.inbox_unlocked && State.inboxUnlocked) {
                    State.inboxUnlocked = false;
                    State.currentThreadUnlocked = false;
                    showScreen('inbox');
                    loadInbox();
                }
            } catch (err) {
                console.error(err);
            }
        }, 5000);

        checkInactivity();
    }

    function checkInactivity() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                lockAllOnInactivity();
            }
        });
    }

    async function lockAllOnInactivity() {
        await ajax(API.contacts, { action: 'lock' });
        State.inboxUnlocked = false;
        State.currentThreadUnlocked = false;
        showScreen('inbox');
        loadInbox();
    }

    // Message Expiration Check
    function startMessageExpirationCheck() {
        if (State.messageTimers[State.currentContactId]) {
            clearInterval(State.messageTimers[State.currentContactId]);
        }

        State.messageTimers[State.currentContactId] = setInterval(async () => {
            try {
                const res = await ajax(API.messages, { action: 'delete_expired' });
                if (res.deleted > 0 && State.currentContactId) {
                    loadMessages(State.currentContactId);
                }
            } catch (err) {
                console.error(err);
            }
        }, 5000);
    }

    // Utility Functions
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // Check Session on Load
    async function checkSession() {
        try {
            const res = await ajax(API.auth, { action: 'check' });
            if (res.logged_in) {
                State.isLoggedIn = true;
                State.user = res.user;
                State.isDuress = res.is_duress;
                loadInbox();
            } else {
                showScreen('login');
            }
        } catch (err) {
            showScreen('login');
        }
    }

    checkSession();
})();