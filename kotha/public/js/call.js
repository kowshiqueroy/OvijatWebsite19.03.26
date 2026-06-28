/* public/js/call.js */

/* ============================================================
   ICE server config — edit TURN block for corporate NAT support.
   Without a TURN server, calls behind symmetric NAT may fail.
   Self-host: https://github.com/coturn/coturn
   ============================================================ */
const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302'  },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    { urls: 'stun:stun.cloudflare.com:3478' },
    // Uncomment + fill to enable TURN relay (required for strict corporate NAT):
    // { urls: 'turn:YOUR_COTURN_HOST:3478', username: 'kotha', credential: 'CHANGE_ME' },
    // { urls: 'turns:YOUR_COTURN_HOST:5349', username: 'kotha', credential: 'CHANGE_ME' },
];

/* ── Browser-safe MediaRecorder MIME detection ─────────────────
   Returns the first MIME type the current browser can encode.
   Fixes Safari/iOS crash caused by hardcoded video/webm.
   ──────────────────────────────────────────────────────────── */
function _getRecordingMime(kind) {
    const videoTypes = [
        'video/webm;codecs=vp8,opus',
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=h264,opus',
        'video/webm',
        'video/mp4;codecs=avc1,mp4a.40.2',
        'video/mp4',
    ];
    const audioTypes = [
        'audio/webm;codecs=opus',
        'audio/ogg;codecs=opus',
        'audio/mp4;codecs=mp4a.40.2',
        'audio/mp4',
        'audio/webm',
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

let localStream   = null;
let remoteStream  = null;
let currentCall   = null;
let peer          = null;
let mediaRecorder = null;
let callId        = null;
let audioCtx      = null;
let audioDest     = null;
let callTimeout   = null;
let callDurationInterval = null;
let videoDevices  = [];
let currentDeviceIndex = 0;
let isCallConnected    = false;
let isRecordingActive  = false;
let isTerminating      = false;
let retryInterval      = null;
let heartbeatInterval  = null;
let _iceDisconnectTimer = null;
let _wasReconnecting    = false;
let _callEndSSE         = null;   // SSE connection for call-end cross-tab signaling

/* ============================================================
   Inline Toast — call page doesn't load app.js
   ============================================================ */
(function injectCallToastStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes cToastIn  { from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)} }
        @keyframes cToastOut { from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(60px)} }
        #cToastBox { position:fixed;bottom:20px;right:20px;z-index:99999;display:flex;flex-direction:column;
            gap:8px;max-width:320px;pointer-events:none; }
        .c-toast { background:#1f2c34;border-radius:10px;padding:12px 16px;color:#e9edef;
            font-family:'Outfit',sans-serif;font-size:0.85rem;display:flex;align-items:center;gap:10px;
            box-shadow:0 4px 20px rgba(0,0,0,.5);animation:cToastIn .3s ease;pointer-events:all;
            cursor:pointer;border-left:4px solid #3b82f6; }
        .c-toast.t-success{border-left-color:#22c55e} .c-toast.t-error{border-left-color:#ef4444}
        .c-toast.t-warning{border-left-color:#f59e0b} .c-toast.removing{animation:cToastOut .25s ease forwards}
    `;
    document.head.appendChild(style);
})();

function showCallToast(msg, type = 'info', duration = 5000) {
    let box = document.getElementById('cToastBox');
    if (!box) { box = document.createElement('div'); box.id = 'cToastBox'; document.body.appendChild(box); }
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
    const colors = { success:'#22c55e', error:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
    const t = type in icons ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `c-toast t-${t}`;
    toast.innerHTML = `<i class="fa-solid ${icons[t]}" style="color:${colors[t]};flex-shrink:0;"></i><span>${msg}</span>`;
    toast.onclick = () => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 260); };
    box.appendChild(toast);
    setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 260); }, duration);
}

/* ============================================================
   Heartbeat — keeps last_seen updated while in a call
   (SSE is not loaded on the call page)
   ============================================================ */
function startHeartbeat() {
    if (heartbeatInterval) return;
    heartbeatInterval = setInterval(() => {
        fetch(`${BASE_URL}/api/presence/ping`, { method: 'POST' }).catch(() => {});
    }, 10000);
    // First ping immediately
    fetch(`${BASE_URL}/api/presence/ping`, { method: 'POST' }).catch(() => {});
}

function stopHeartbeat() {
    if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null; }
}

/* ============================================================
   Disconnect Overlay helpers
   ============================================================ */
function showDisconnectOverlay(reason) {
    const overlay = document.getElementById('callDisconnectOverlay');
    if (!overlay) return;
    const reasonEl = document.getElementById('disconnectReason');
    if (reasonEl) reasonEl.innerText = reason || 'The call has ended.';
    overlay.style.display = 'flex';
}

function hideDisconnectOverlay() {
    const overlay = document.getElementById('callDisconnectOverlay');
    if (overlay) overlay.style.display = 'none';
}

/* ============================================================
   Peer ID helpers
   ============================================================ */

/**
 * Receiver's canonical peer ID — unique per call, derived from call ID.
 * Both sides compute it independently:
 *   caller  → _recvPeerId(partnerUserId, callId)  — to dial
 *   receiver → _recvPeerId(CURRENT_USER_ID, callId) — to register
 *
 * Using callId makes this collision-proof: no stale peer from a previous
 * session can block registration, so `unavailable-id` never fires for
 * the receiver, and the caller always dials the right peer.
 */
function _recvPeerId(userId, cId) {
    return `krecv_${userId}_${cId}`;
}

/** Extract the numeric partner user-ID from a 1on1_X_Y chat ID. */
function _partnerUserId() {
    if (!ACTIVE_CHAT_ID || !ACTIVE_CHAT_ID.startsWith('1on1_')) return null;
    const parts = ACTIVE_CHAT_ID.split('_');
    const a = parseInt(parts[1]), b = parseInt(parts[2]);
    return a === CURRENT_USER_ID ? b : a;
}

/* ============================================================
   Call-end SSE — lightweight listener so the OTHER party's
   call page is notified the moment this side hangs up.
   Works even when the browser tab is navigated away (beforeunload
   fires sendBeacon which still POST-es the end action).
   ============================================================ */
function startCallEndSSE() {
    if (_callEndSSE) _callEndSSE.close();
    _callEndSSE = new EventSource(`${BASE_URL}/api/sse`);

    _callEndSSE.addEventListener('call-end', function(e) {
        try {
            const data = JSON.parse(e.data);
            if (data.chat_id === ACTIVE_CHAT_ID && !isTerminating) {
                terminateCall('The other party ended the call.');
            }
        } catch(ex) {}
    });

    _callEndSSE.onerror = function() {
        _callEndSSE.close();
        if (!isTerminating) setTimeout(startCallEndSSE, 3000);
    };
}

function _publishCallEnd() {
    if (!ACTIVE_CHAT_ID) return;
    const fd = new FormData();
    fd.append('action',   'end');
    fd.append('chat_id',  ACTIVE_CHAT_ID);
    // sendBeacon is reliable on page unload; fetch for normal hangs
    if (navigator.sendBeacon) {
        navigator.sendBeacon(`${BASE_URL}/api/call/save-record`, fd);
    } else {
        fetch(`${BASE_URL}/api/call/save-record`, { method: 'POST', body: fd }).catch(() => {});
    }
}

// Ensure remote party is notified even when tab is closed / navigated away
window.addEventListener('beforeunload', function() {
    if (_callEndSSE) { _callEndSSE.close(); _callEndSSE = null; }
    _publishCallEnd();
    // Also finalise recording on page close if it hasn't been done yet
    if (callId && CALL_MODE === 'outgoing' && !isTerminating) {
        const fd = new FormData();
        fd.append('action',           'finish');
        fd.append('call_id',          callId);
        fd.append('duration_minutes', Math.max(0, Math.floor(_callElapsedSeconds / 60)));
        navigator.sendBeacon(`${BASE_URL}/api/call/save-record`, fd);
    }
});

/* ============================================================
   Entry point
   ============================================================ */
document.addEventListener("DOMContentLoaded", function() {
    initCall();
    startCallEndSSE();   // Start listening for remote hang-up signals
    _listenForRetrySignal();

    document.body.addEventListener('click', function() {
        const rv = document.getElementById('remoteVideoElement');
        if (rv && rv.srcObject) rv.play().catch(() => {});
        const lv = document.getElementById('localVideoElement');
        if (lv && lv.srcObject) lv.play().catch(() => {});
    }, { once: true });
});

/**
 * Cross-tab localStorage signal so the OTHER party's call page (which has no
 * SSE) knows to go back to chat when a retry is happening.
 *
 * Why this now works end-to-end:
 *   1. Caller clicks "Retry" → writes signal → reloads their page.
 *   2. Receiver's call page sees the storage event → navigates to chat.
 *   3. Receiver's chat page connects SSE with ?from=<last-known-id>
 *      (stored in sessionStorage before they navigated to the call page).
 *   4. The SSE stream replays missed events (120 s window) — the new call
 *      event the caller just sent is in that window → receiver sees it.
 */
function _listenForRetrySignal() {
    window.addEventListener('storage', function(e) {
        if (e.key !== 'kotha_call_retry') return;
        try {
            const data = JSON.parse(e.newValue || '{}');
            if (data.chatId === ACTIVE_CHAT_ID && Date.now() - data.ts < 20000) {
                window.location.href = `${BASE_URL}/chat/${ACTIVE_CHAT_ID}`;
            }
        } catch (ex) {}
    });
}

function initCall() {
    const statusText = document.getElementById('callStatusText');
    startHeartbeat();

    // ── Incoming receiver: register with a deterministic, call-scoped peer ID ──
    // The URL contains ?callId=N.  We register as krecv_{myUserId}_{callId}.
    // The caller independently computes the same string and dials it — no
    // stale-peer race condition, no random suffix mismatch.
    const urlParams      = new URLSearchParams(window.location.search);
    const urlCallId      = urlParams.get('callId');
    const myPeerId       = (CALL_MODE === 'incoming' && urlCallId)
        ? _recvPeerId(CURRENT_USER_ID, urlCallId)
        : 'kotha_user_' + CURRENT_USER_ID;   // outgoing: canonical (doesn't need dialing)

    if (CALL_MODE === 'incoming' && urlCallId) {
        callId = parseInt(urlCallId);         // store early so terminateCall can use it
    }

    peer = new Peer(myPeerId, {
        debug: 1,
        config: { iceServers: ICE_SERVERS }
    });

    peer.on('open', function() {
        if (statusText) statusText.innerText = 'INITIALIZING MEDIA...';
        setupMediaAndCall();
    });

    peer.on('error', function(err) {
        if (err.type === 'unavailable-id') {
            // Outgoing callers can hit this if a previous session's peer is
            // still alive on the signaling server.  Fall back to a timestamped ID.
            peer.destroy();
            const fallback = myPeerId + '_' + Date.now().toString(36);
            peer = new Peer(fallback, {
                debug: 1,
                config: { iceServers: ICE_SERVERS }
            });
            peer.on('open', () => {
                if (statusText) statusText.innerText = 'INITIALIZING MEDIA...';
                setupMediaAndCall();
            });
            peer.on('error', (e2) => {
                if (e2.type !== 'peer-unavailable') {           // suppress during ringing
                    showCallToast('Signaling error: ' + e2.type, 'error');
                    if (statusText) statusText.innerText = 'P2P ERROR';
                }
            });
            return;
        }

        // peer-unavailable fires every dial attempt while the receiver's page
        // is still loading — it is EXPECTED noise during ringing, not an error.
        if (err.type === 'peer-unavailable') return;

        showCallToast('P2P Error: ' + err.type, 'error');
        if (statusText) statusText.innerText = 'P2P ERROR: ' + err.type;
    });
}

function setupMediaAndCall() {
    const statusText = document.getElementById('callStatusText');

    const constraints = {
        audio: true,
        video: (CALL_TYPE === 'video') ? { width: 320, height: 240, frameRate: 15 } : false
    };

    navigator.mediaDevices.getUserMedia(constraints)
    .then(stream => {
        localStream = stream;

        if (CALL_TYPE === 'video') {
            const lv = document.getElementById('localVideoElement');
            lv.srcObject = stream;
            lv.style.display = 'block';
            lv.play().catch(() => {});
            document.getElementById('audioCallIndicator').style.display = 'none';

            navigator.mediaDevices.enumerateDevices()
            .then(devices => {
                videoDevices = devices.filter(d => d.kind === 'videoinput');
                if (videoDevices.length > 1) {
                    const sb = document.getElementById('switchCameraBtn');
                    if (sb) sb.style.display = 'flex';
                }
            }).catch(() => {});
        }

        if (CALL_MODE === 'outgoing') {
            startCallLogging();
        } else {
            if (statusText) statusText.innerText = "WAITING FOR CONNECTION...";

            peer.on('call', function(incomingCall) {
                // Only answer the first incoming call
                if (isCallConnected) return;
                if (statusText) statusText.innerText = "CONNECTING...";
                incomingCall.answer(localStream);
                handleCallStream(incomingCall);
            });

            // Extract callId from URL for the receiver
            const urlParams     = new URLSearchParams(window.location.search);
            const incomingCallId = urlParams.get('callId');
            if (incomingCallId) callId = parseInt(incomingCallId);
        }
    })
    .catch(err => {
        showCallToast('Camera/Mic access required: ' + err.message, 'error');
        if (statusText) statusText.innerText = "MEDIA ERROR";
        showDisconnectOverlay('Media access was denied. Please allow camera/microphone and retry.');
    });
}

/* ============================================================
   Outgoing call logging & dialing
   ============================================================ */
function startCallLogging() {
    const statusText = document.getElementById('callStatusText');
    const partnerUid = _partnerUserId();

    if (!partnerUid) {
        if (statusText) statusText.innerText = 'CALL ERROR';
        showCallToast('Cannot resolve partner from chat ID.', 'error');
        return;
    }

    if (statusText) statusText.innerText = 'DIALING...';

    const fd = new FormData();
    fd.append('action',    'start');
    fd.append('chat_id',   ACTIVE_CHAT_ID);
    fd.append('call_type', CALL_TYPE);

    fetch(`${BASE_URL}/api/call/save-record`, { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showCallToast('Failed to initialise call on server.', 'error');
            if (statusText) statusText.innerText = 'SERVER ERROR';
            return;
        }

        callId = data.call_id;

        // Compute the receiver's peer ID the same way they registered it:
        // krecv_{receiverUserId}_{callId} — unique per call, no stale-ID collisions.
        const targetPeerId = _recvPeerId(partnerUid, callId);
        dialPartner(targetPeerId);
    })
    .catch(() => {
        showCallToast('Server connection error. Check network.', 'error');
        if (statusText) statusText.innerText = 'SERVER ERROR';
    });
}

function dialPartner(partnerPeerId) {
    const statusText = document.getElementById('callStatusText');

    // Place first call attempt immediately
    _placeSingleCall(partnerPeerId);

    // Retry every 3 s until connected or timed out
    retryInterval = setInterval(() => {
        if (isCallConnected || isTerminating) { clearInterval(retryInterval); retryInterval = null; return; }
        if (statusText) statusText.innerText = "RINGING...";
        _placeSingleCall(partnerPeerId);
    }, 3000);

    // 30-second ring timeout → missed call
    callTimeout = setTimeout(() => {
        if (isCallConnected || isTerminating) return;
        if (retryInterval) { clearInterval(retryInterval); retryInterval = null; }

        if (statusText) statusText.innerText = "NO ANSWER";
        showCallToast('No answer from partner.', 'warning');

        const mfd = new FormData();
        mfd.append('message_type', 'text');
        mfd.append('content', `Missed ${CALL_TYPE} call`);
        fetch(`${BASE_URL}/api/messages/${ACTIVE_CHAT_ID}`, { method: 'POST', body: mfd })
        .finally(() => showDisconnectOverlay('No answer. The partner did not pick up.'));
    }, 30000);
}

function _placeSingleCall(partnerPeerId) {
    if (isCallConnected || isTerminating) return;
    try {
        const c = peer.call(partnerPeerId, localStream);
        if (c) handleCallStream(c);
    } catch (e) {
        // peer may be in bad state; ignore individual dial errors
    }
}

/* ============================================================
   ICE / Connection-health monitoring
   ============================================================
   call.on('close') only fires on a *graceful* peer close.
   When the remote user loses network or their tab crashes, the
   RTCPeerConnection ICE state changes instead.  Without this
   monitor the local user is stuck on a frozen frame for 30 s+
   until the browser's own ICE timeout kills the connection.
   ============================================================ */
function _monitorConnectionHealth(call) {
    const pc = call.peerConnection;
    if (!pc) return;

    const check = () => {
        if (isTerminating) return;

        const ice  = pc.iceConnectionState;
        // connectionState is more reliable but not available in all browsers
        const conn = pc.connectionState || ice;
        const statusText = document.getElementById('callStatusText');

        // ── Permanent failure ──────────────────────────────────
        if (ice === 'failed' || conn === 'failed') {
            if (_iceDisconnectTimer) { clearTimeout(_iceDisconnectTimer); _iceDisconnectTimer = null; }
            terminateCall('Connection failed. Check your network.');
            return;
        }

        if (ice === 'closed' || conn === 'closed') {
            terminateCall('Connection closed.');
            return;
        }

        // ── Transient disconnection — might recover ────────────
        if (ice === 'disconnected' || conn === 'disconnected') {
            _wasReconnecting = true;
            if (statusText) statusText.innerText = 'RECONNECTING...';

            // If still disconnected after 8 s, give up
            if (!_iceDisconnectTimer) {
                _iceDisconnectTimer = setTimeout(() => {
                    _iceDisconnectTimer = null;
                    const curIce  = pc.iceConnectionState;
                    const curConn = pc.connectionState || curIce;
                    if (!isTerminating &&
                        (curIce === 'disconnected' || curIce === 'failed' ||
                         curConn === 'disconnected' || curConn === 'failed')) {
                        terminateCall('Connection lost — the other party may have gone offline.');
                    }
                }, 8000);
            }
            return;
        }

        // ── Recovered ─────────────────────────────────────────
        if (ice === 'connected' || ice === 'completed' || conn === 'connected') {
            if (_iceDisconnectTimer) { clearTimeout(_iceDisconnectTimer); _iceDisconnectTimer = null; }
            if (_wasReconnecting) {
                _wasReconnecting = false;
                showCallToast('Connection restored.', 'success', 3000);
                // Restore the timer display (JS timer is still running)
                if (statusText && _callElapsedSeconds > 0) {
                    const m = String(Math.floor(_callElapsedSeconds / 60)).padStart(2, '0');
                    const s = String(_callElapsedSeconds % 60).padStart(2, '0');
                    statusText.innerText = `${m}:${s}`;
                }
            }
        }
    };

    pc.oniceconnectionstatechange = check;
    pc.onconnectionstatechange    = check;
}

/* ============================================================
   Handle a PeerJS call object (works for both sides)
   ============================================================ */
function handleCallStream(call) {
    const statusText = document.getElementById('callStatusText');

    call.on('stream', function(stream) {
        // Guard: only process the very first successful stream
        if (isCallConnected) return;
        isCallConnected = true;

        // Cancel dial retry & ring timeout
        if (retryInterval) { clearInterval(retryInterval); retryInterval = null; }
        if (callTimeout)   { clearTimeout(callTimeout);    callTimeout   = null; }

        remoteStream = stream;
        currentCall  = call;

        const rv = document.getElementById('remoteVideoElement');
        if (rv) {
            rv.srcObject = stream;
            rv.play().then(() => {}).catch(() => {
                if (statusText) statusText.innerText = 'TAP TO HEAR/SEE';
            });
            if (CALL_TYPE === 'video') {
                rv.style.display = 'block';
                const av = document.getElementById('whatsappAvatarContainer');
                if (av) av.style.display = 'none';
            }
        }

        startCallTimer();
        _monitorConnectionHealth(call);   // ← start ICE health monitoring

        if (CALL_MODE === 'outgoing') setupInitiatorRecording();
    });

    call.on('close', function() {
        // Graceful close from the remote peer
        if (!isTerminating) terminateCall('Remote party ended the call.');
    });

    call.on('error', function(err) {
        if (!isCallConnected && !isTerminating) {
            console.warn('Call attempt error:', err);
        }
    });
}

/* ============================================================
   Call termination — shows overlay instead of hard-redirecting
   ============================================================ */
function terminateCall(reason) {
    if (isTerminating) return;
    isTerminating    = true;
    isCallConnected  = false;
    isRecordingActive = false;

    // Notify the other party via SSE immediately (before any async cleanup)
    _publishCallEnd();

    // Close our own SSE listener — no longer needed
    if (_callEndSSE) { _callEndSSE.close(); _callEndSSE = null; }

    stopHeartbeat();

    if (callTimeout)        { clearTimeout(callTimeout);           callTimeout         = null; }
    if (retryInterval)      { clearInterval(retryInterval);        retryInterval       = null; }
    if (callDurationInterval){ clearInterval(callDurationInterval); callDurationInterval = null; }
    if (_iceDisconnectTimer){ clearTimeout(_iceDisconnectTimer);   _iceDisconnectTimer  = null; }
    _wasReconnecting = false;

    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (currentCall) { try { currentCall.close(); } catch(e) {} }

    // Finalise recording then show overlay
    if (CALL_MODE === 'outgoing' && callId) {
        const fd = new FormData();
        fd.append('action',           'finish');
        fd.append('call_id',          callId);
        fd.append('duration_minutes', Math.max(0, Math.floor(_callElapsedSeconds / 60)));
        fetch(`${BASE_URL}/api/call/save-record`, { method: 'POST', body: fd })
        .catch(() => {})
        .finally(() => {
            if (peer) { try { peer.destroy(); } catch(e) {} }
            showDisconnectOverlay(reason || 'The call has ended.');
        });
    } else {
        if (peer) { try { peer.destroy(); } catch(e) {} }
        showDisconnectOverlay(reason || 'The call has ended.');
    }
}

/**
 * Retry flow (now works end-to-end):
 *
 * OUTGOING (caller):
 *   - Signals the receiver's call page (localStorage) → receiver goes to chat.
 *   - Reloads own page → new call record → new SSE call event sent.
 *   - Receiver's chat page resumes SSE from ?from=<sessionStorage id> →
 *     replays the last 120 s of events → sees the new call event. ✓
 *
 * INCOMING (receiver):
 *   - Just go back to chat; if the caller also retried, the resumed SSE
 *     will deliver their new call notification. ✓
 */
function retryCall() {
    if (CALL_MODE === 'incoming') {
        backToChat();
        return;
    }

    // Signal the receiver's call page to navigate back to chat
    try {
        localStorage.setItem('kotha_call_retry', JSON.stringify({
            chatId: ACTIVE_CHAT_ID,
            ts:     Date.now()
        }));
        setTimeout(() => { try { localStorage.removeItem('kotha_call_retry'); } catch(_){} }, 8000);
    } catch (e) {}

    // Reload → new call record → SSE call event → receiver's resumed SSE delivers it
    window.location.reload();
}

function backToChat() {
    window.location.href = `${BASE_URL}/chat/${ACTIVE_CHAT_ID}`;
}

/* ============================================================
   Side-by-side canvas recording (caller only)
   ============================================================ */
function setupInitiatorRecording() {
    try {
        isRecordingActive = true;
        audioCtx  = new (window.AudioContext || window.webkitAudioContext)();
        audioDest = audioCtx.createMediaStreamDestination();

        if (localStream && localStream.getAudioTracks().length > 0) {
            audioCtx.createMediaStreamSource(localStream).connect(audioDest);
        }
        if (remoteStream && remoteStream.getAudioTracks().length > 0) {
            audioCtx.createMediaStreamSource(remoteStream).connect(audioDest);
        }

        const canvas  = document.createElement('canvas');
        canvas.width  = 640;
        canvas.height = 240;
        const ctx     = canvas.getContext('2d');
        const lv      = document.getElementById('localVideoElement');
        const rv      = document.getElementById('remoteVideoElement');
        let partnerName = 'Partner';
        const hEl = document.querySelector('.whatsapp-call-header h2');
        if (hEl) partnerName = hEl.innerText.trim();

        function drawFrame() {
            if (!isRecordingActive) return;
            ctx.fillStyle = '#0b141a';
            ctx.fillRect(0, 0, 640, 240);

            if (CALL_TYPE === 'video' && lv && lv.readyState >= 2) {
                ctx.drawImage(lv, 0, 0, 320, 240);
            } else {
                ctx.fillStyle = '#1f2c34'; ctx.fillRect(10, 10, 300, 220);
                ctx.fillStyle = '#fff'; ctx.font = 'bold 16px Outfit,sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(CURRENT_USER_NAME || 'You', 160, 110);
                ctx.fillStyle = '#8696a0'; ctx.font = '12px Outfit,sans-serif';
                ctx.fillText(CALL_TYPE === 'video' ? 'Camera Off' : 'Voice Active', 160, 140);
            }

            if (CALL_TYPE === 'video' && rv && rv.readyState >= 2) {
                ctx.drawImage(rv, 320, 0, 320, 240);
            } else {
                ctx.fillStyle = '#1f2c34'; ctx.fillRect(330, 10, 300, 220);
                ctx.fillStyle = '#fff'; ctx.font = 'bold 16px Outfit,sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(partnerName, 480, 110);
                ctx.fillStyle = '#8696a0'; ctx.font = '12px Outfit,sans-serif';
                ctx.fillText(CALL_TYPE === 'video' ? 'Connecting Video...' : 'Voice Active', 480, 140);
            }
            requestAnimationFrame(drawFrame);
        }
        drawFrame();

        const tracks = [];
        const cs = canvas.captureStream(15);
        if (cs.getVideoTracks().length > 0) tracks.push(cs.getVideoTracks()[0]);
        if (audioDest.stream.getAudioTracks().length > 0) tracks.push(audioDest.stream.getAudioTracks()[0]);

        const recMime = _getRecordingMime('video');
        const recOpts = recMime ? { mimeType: recMime } : {};
        mediaRecorder = new MediaRecorder(new MediaStream(tracks), recOpts);
        mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) uploadRecordingChunk(e.data); };
        mediaRecorder.start(5000);
    } catch (err) {
        console.error("Recording setup error:", err);
    }
}

function uploadRecordingChunk(blob) {
    if (!callId) return;
    const fd = new FormData();
    fd.append('action',      'append');
    fd.append('call_id',     callId);
    fd.append('video_chunk', blob);
    fetch(`${BASE_URL}/api/call/save-record`, { method: 'POST', body: fd }).catch(() => {});
}

/* ============================================================
   Local UI controls
   ============================================================ */
let isMuted = false;
function toggleMute() {
    isMuted = !isMuted;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = !isMuted);
    const btn = document.getElementById('toggleMicBtn');
    btn.classList.toggle('disabled', isMuted);
    btn.querySelector('i').className = isMuted ? 'fa-solid fa-microphone-slash' : 'fa-solid fa-microphone';
}

let isCamOff = false;
function toggleCamera() {
    if (CALL_TYPE !== 'video') return;
    isCamOff = !isCamOff;
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = !isCamOff);
    const btn = document.getElementById('toggleVideoBtn');
    btn.classList.toggle('disabled', isCamOff);
    btn.querySelector('i').className = isCamOff ? 'fa-solid fa-video-slash' : 'fa-solid fa-video';
}

let isSharingScreen = false;
let screenStream    = null;

function toggleScreenShare() {
    if (isSharingScreen) { stopScreenShare(); return; }
    // Screen capture API is desktop-only; unavailable on iOS and Android Chrome
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
        showCallToast('Screen sharing is not supported on this device or browser.', 'warning');
        return;
    }

    navigator.mediaDevices.getDisplayMedia({ video: true })
    .then(stream => {
        isSharingScreen = true;
        screenStream    = stream;
        const btn       = document.getElementById('shareScreenBtn');
        if (btn) btn.classList.add('active');

        const vt = stream.getVideoTracks()[0];
        if (currentCall && currentCall.peerConnection) {
            const vs = currentCall.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
            if (vs) vs.replaceTrack(vt);
        }
        document.getElementById('localVideoElement').srcObject = stream;
        vt.onended = stopScreenShare;
    })
    .catch(err => showCallToast('Screen sharing error: ' + err.message, 'error'));
}

function stopScreenShare() {
    if (!isSharingScreen) return;
    isSharingScreen = false;
    if (screenStream) screenStream.getTracks().forEach(t => t.stop());
    if (localStream && localStream.getVideoTracks().length > 0) {
        const vt = localStream.getVideoTracks()[0];
        if (currentCall && currentCall.peerConnection) {
            const vs = currentCall.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
            if (vs) vs.replaceTrack(vt);
        }
        document.getElementById('localVideoElement').srcObject = localStream;
    }
    const btn = document.getElementById('shareScreenBtn');
    if (btn) btn.classList.remove('active');
}

function switchCamera() {
    if (videoDevices.length <= 1) {
        showCallToast('No other camera devices detected.', 'warning');
        return;
    }
    currentDeviceIndex = (currentDeviceIndex + 1) % videoDevices.length;
    const dev = videoDevices[currentDeviceIndex];
    navigator.mediaDevices.getUserMedia({
        audio: true,
        video: { deviceId: { exact: dev.deviceId }, width: 320, height: 240, frameRate: 15 }
    })
    .then(newStream => {
        if (localStream) localStream.getVideoTracks().forEach(t => { localStream.removeTrack(t); t.stop(); });
        const vt = newStream.getVideoTracks()[0];
        if (localStream) localStream.addTrack(vt); else localStream = newStream;

        const lv = document.getElementById('localVideoElement');
        if (lv) lv.srcObject = localStream;

        if (currentCall && currentCall.peerConnection) {
            const vs = currentCall.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
            if (vs) vs.replaceTrack(vt);
        }
    })
    .catch(err => showCallToast('Failed to switch camera: ' + err.message, 'error'));
}

/* ============================================================
   Helpers
   ============================================================ */
function getPartnerPeerId() {
    const uid = _partnerUserId();
    return uid ? 'kotha_user_' + uid : null;  // informational only — dialing now uses _recvPeerId()
}

let _callElapsedSeconds = 0;

function startCallTimer() {
    const el = document.getElementById('callStatusText');
    if (!el) return;
    _callElapsedSeconds = 0;
    el.innerText = '00:00';
    // Keep the timerPulse animation — do NOT set animation:'none'.
    // The large blinking timer is the main visual during a connected call.
    if (callDurationInterval) clearInterval(callDurationInterval);
    callDurationInterval = setInterval(() => {
        _callElapsedSeconds++;
        const m = String(Math.floor(_callElapsedSeconds / 60)).padStart(2, '0');
        const s = String(_callElapsedSeconds % 60).padStart(2, '0');
        el.innerText = `${m}:${s}`;
    }, 1000);
}
