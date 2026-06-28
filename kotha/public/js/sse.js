/* public/js/sse.js */

let sseConnection = null;
let typingTimers  = {};

const SSE_RESUME_KEY = 'kotha_sse_last_id';

// Clear resume ID on a fresh page load — avoids stale IDs after hard reload
sessionStorage.removeItem(SSE_RESUME_KEY);

function _sseTrackId(e) {
    if (e.lastEventId) {
        try { sessionStorage.setItem(SSE_RESUME_KEY, e.lastEventId); } catch (_) {}
    }
}

function connectSSE() {
    if (sseConnection) sseConnection.close();

    let resumeFrom = '';
    try { resumeFrom = sessionStorage.getItem(SSE_RESUME_KEY) || ''; } catch (_) {}

    const sseUrl = `${BASE_URL}/api/sse?t=${Date.now()}${resumeFrom ? '&from=' + encodeURIComponent(resumeFrom) : ''}`;
    sseConnection = new EventSource(sseUrl);

    // 1. Message Event — use addEventListener only (NOT onmessage) to avoid double-firing
    sseConnection.addEventListener('message', function(e) {
        _sseTrackId(e);
        try {
            const msg = JSON.parse(e.data);
            if (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID === msg.chat_id) {
                appendMessageToDom(msg);
            }
            updateSidebarPreview(msg);
        } catch (err) {
            console.error('SSE message parse error:', err);
        }
    });

    // 2. Vanish Event
    sseConnection.addEventListener('vanish', function(e) {
        _sseTrackId(e);
        try {
            const data      = JSON.parse(e.data);
            const messageId = data.message_id;
            const bubble    = document.querySelector(`[data-msg-id="${messageId}"]`);
            if (bubble) {
                bubble.style.transition = 'opacity 0.4s ease';
                bubble.style.opacity    = '0';
                setTimeout(() => bubble.remove(), 400);
            }
            if (typeof markMessageAsVanishedLocally === 'function') {
                markMessageAsVanishedLocally(messageId);
            }
        } catch (err) {
            console.error('SSE vanish parse error:', err);
        }
    });

    // 3. Call Signal — triggers incoming-call screen
    sseConnection.addEventListener('call', function(e) {
        _sseTrackId(e);
        try {
            const data = JSON.parse(e.data);
            if (parseInt(data.caller_id) !== CURRENT_USER_ID) {
                window.pendingCallData = data;
                if (typeof isPinUnlocked !== 'undefined' && isPinUnlocked) {
                    if (typeof showCallPromptModal === 'function') showCallPromptModal(data);
                } else {
                    if (typeof openPinModal === 'function') openPinModal(true);
                }
            }
        } catch (err) {
            console.error('SSE call parse error:', err);
        }
    });

    // 4. Call-end — remote party hung up; dismiss any incoming-call modal on this page
    sseConnection.addEventListener('call-end', function(e) {
        _sseTrackId(e);
        try {
            const data = JSON.parse(e.data);
            // If we were showing an incoming-call prompt for this chat, dismiss it
            if (window.pendingCallData && window.pendingCallData.chat_id === data.chat_id) {
                window.pendingCallData = null;
                const modal = document.getElementById('incomingCallModal');
                if (modal) modal.style.display = 'none';
            }
        } catch (err) {
            console.error('SSE call-end parse error:', err);
        }
    });

    // 5. Admin Notification
    sseConnection.addEventListener('notification', function(e) {
        _sseTrackId(e);
        try {
            const data = JSON.parse(e.data);
            if (typeof handleSSENotification === 'function') handleSSENotification(data);
        } catch (err) {
            console.error('SSE notification parse error:', err);
        }
    });

    // 6. Typing indicator
    sseConnection.addEventListener('typing', function(e) {
        _sseTrackId(e);
        try {
            const data = JSON.parse(e.data);
            if (parseInt(data.user_id) === CURRENT_USER_ID) return;

            const previewEl = document.getElementById(`preview-${data.chat_id}`);
            if (previewEl) {
                if (!previewEl.hasAttribute('data-typing-active')) {
                    previewEl.setAttribute('data-original-text', previewEl.innerHTML);
                }
                previewEl.setAttribute('data-typing-active', 'true');
                previewEl.removeAttribute('data-new-msg');  // typing clears new-msg state
                previewEl.innerHTML = `<span style="color:#3b82f6;font-weight:600;">Typing...</span>`;
            }

            if (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID === data.chat_id) {
                const statusEl = document.getElementById('activeChatStatus');
                if (statusEl) { statusEl.innerText = 'Typing...'; statusEl.style.color = '#3b82f6'; }
            }

            clearTimeout(typingTimers[data.chat_id]);
            typingTimers[data.chat_id] = setTimeout(() => {
                if (previewEl) {
                    previewEl.removeAttribute('data-typing-active');
                    const orig = previewEl.getAttribute('data-original-text');
                    if (orig !== null) {
                        previewEl.innerHTML = orig;
                        previewEl.removeAttribute('data-original-text');
                    }
                }
                if (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID === data.chat_id) {
                    const statusEl = document.getElementById('activeChatStatus');
                    if (statusEl) {
                        if (typeof updatePresenceUI === 'function') updatePresenceUI();
                        if (statusEl.innerText === 'Typing...') {
                            statusEl.innerText   = window.defaultChatStatus || 'Offline';
                            statusEl.style.color = '';
                        }
                    }
                }
            }, 3500);

        } catch (err) {
            console.error('SSE typing parse error:', err);
        }
    });

    // 7. Group created — reload sidebar so the new group appears
    sseConnection.addEventListener('group_created', function(e) {
        _sseTrackId(e);
        // A new group was created that includes us — reload to show it in the sidebar
        if (typeof ACTIVE_CHAT_ID === 'undefined' || !ACTIVE_CHAT_ID) {
            window.location.reload();
        } else {
            // Don't interrupt an active chat; just reload the page when idle
            setTimeout(() => window.location.reload(), 500);
        }
    });

    sseConnection.onerror = function() {
        sseConnection.close();
        setTimeout(connectSSE, 3000);
    };
}

/**
 * Updates sidebar preview when a new message event arrives via SSE.
 * Hidden chats are intentionally skipped — a new message must NOT resurface them.
 */
function updateSidebarPreview(msg) {
    const chatItem = document.querySelector(`.chat-item[onclick*="${msg.chat_id}"]`);
    if (!chatItem) return;

    // Never un-hide a chat that the user deliberately hid
    if (chatItem.classList.contains('chat-hidden')) return;

    clearTimeout(typingTimers[msg.chat_id]);

    // Restore chat-header presence if this is the active chat
    if (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID === msg.chat_id) {
        const statusEl = document.getElementById('activeChatStatus');
        if (statusEl) {
            if (typeof updatePresenceUI === 'function') updatePresenceUI();
            if (statusEl.innerText === 'Typing...') {
                statusEl.innerText   = window.defaultChatStatus || 'Offline';
                statusEl.style.color = '';
            }
        }
    }

    // Float this chat to the top
    const chatsList = document.getElementById('chatsListContainer');
    if (chatsList && chatItem) chatsList.insertBefore(chatItem, chatsList.firstChild);

    // Mark preview with data-new-msg so the presence ticker won't overwrite it
    const previewEl = document.getElementById(`preview-${msg.chat_id}`);
    if (previewEl) {
        previewEl.removeAttribute('data-typing-active');
        previewEl.removeAttribute('data-original-text');
        previewEl.setAttribute('data-new-msg', '1');
        previewEl.innerHTML = `<span class="camouflage-text" style="font-size:0.75rem;color:var(--accent);">New Message</span>`;
    }

    // Update timestamp
    const timeEl = document.getElementById(`time-${msg.chat_id}`);
    if (timeEl) {
        const dateStr       = msg.created_at.replace(/-/g, '/') + ' UTC';
        const timeFormatted = new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Dhaka' });
        timeEl.innerText    = timeFormatted;
    }

    // Increment unread badge for non-active chats
    const isCurrentlyActive = (typeof ACTIVE_CHAT_ID !== 'undefined' && ACTIVE_CHAT_ID === msg.chat_id);
    if (!isCurrentlyActive && parseInt(msg.sender_id) !== CURRENT_USER_ID) {
        const unreadEl = document.getElementById(`unread-${msg.chat_id}`);
        if (unreadEl) {
            unreadEl.innerText      = (parseInt(unreadEl.innerText) || 0) + 1;
            unreadEl.style.display  = 'flex';
        } else {
            const rightBoxEl = chatItem.querySelector('.chat-right-box');
            if (rightBoxEl) {
                const spacer = rightBoxEl.querySelector('.unread-badge-spacer');
                if (spacer) spacer.remove();
                const badge       = document.createElement('span');
                badge.className   = 'unread-badge';
                badge.id          = `unread-${msg.chat_id}`;
                badge.innerText   = '1';
                rightBoxEl.appendChild(badge);
            }
        }
    }
}

// Establish the connection on page load
connectSSE();
