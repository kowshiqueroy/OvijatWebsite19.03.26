<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'] ?? 0;
$other_user_id = ($user_id == 1) ? 2 : 1;
$other_name = ($user_id == 1) ? 'Rai' : 'Kush';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini - Secure Nodes</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
    <style>
        /* App structure */
        .app-container { 
            display: flex; 
            flex-direction: column; 
            width: 100%; 
            height: 100vh; 
            background: var(--gemini-bg);
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--gemini-bg);
            border-bottom: 1px solid #222;
            z-index: 1000;
            flex-shrink: 0;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        .menu-btn { background: none; border: none; color: #fff; font-size: 20px; }
        .header-title { font-size: 20px; font-weight: 600; color: #fff; }
        .sparkle-logo {
            font-size: 26px;
            cursor: pointer;
            padding: 8px;
            transition: all 0.5s ease;
            user-select: none;
            filter: grayscale(1);
            opacity: 0.5;
            color: #fff;
        }
        .sparkle-logo.online {
            filter: grayscale(0); opacity: 1;
            color: var(--gemini-blue);
            text-shadow: 0 0 15px rgba(138, 180, 248, 0.6);
        }

        /* Call Stream Area */
        .call-container {
            display: none;
            background: #000;
            width: 100%;
            position: relative;
            z-index: 900;
            overflow: hidden;
            border-bottom: 1px solid #333;
            transition: all 0.3s ease;
            height: 0;
            cursor: pointer;
        }
        .call-container.active { display: block; height: 60vh; }
        #remote-video { width: 100%; height: 100%; background: #000; display: block; object-fit: cover; pointer-events: none; }
        #local-video {
            position: absolute; top: 15px; right: 15px; width: 110px; height: 150px;
            border-radius: 12px; border: 1.5px solid rgba(255,255,255,0.2);
            background: #111; object-fit: cover; z-index: 910;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        #local-video.hidden { opacity: 0; pointer-events: none; transform: scale(0.8); }

        .call-mini-controls {
            position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
            padding: 10px 20px; background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
            border-radius: 30px; display: flex; justify-content: center; gap: 15px;
            z-index: 920; border: 1px solid rgba(255,255,255,0.1);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }
        .call-mini-controls.hidden {
            opacity: 0;
            pointer-events: none;
            transform: translateX(-50%) translateY(20px);
        }

        .control-btn {
            background: rgba(255,255,255,0.1); border: none; width: 44px; height: 44px;
            border-radius: 50%; color: #fff; cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 20px; transition: all 0.2s;
        }
        .control-btn.off { background: rgba(255, 77, 77, 0.4); color: #ff4d4d; }
        .control-btn.end { background: #ff4d4d; color: #fff; width: 50px; border-radius: 15px; }

        /* Overlays */
        .call-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); z-index: 10000;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            text-align: center;
        }
        .call-overlay.show { display: flex; }
        .avatar-ring {
            width: 120px; height: 120px; border-radius: 50%;
            background: #222; border: 2px solid var(--gemini-blue);
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; margin-bottom: 20px; position: relative;
            color: #fff;
        }
        .avatar-ring::after {
            content: ""; position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px;
            border-radius: 50%; border: 2px solid var(--gemini-blue);
            animation: ring-pulse 1.5s infinite;
        }
        @keyframes ring-pulse {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.3); opacity: 0; }
        }
        .overlay-name { font-size: 24px; font-weight: 600; margin-bottom: 8px; color: #fff; }
        .overlay-status { font-size: 14px; color: var(--gemini-blue); margin-bottom: 40px; text-transform: uppercase; letter-spacing: 2px; }
        .overlay-btns { display: flex; gap: 40px; }
        .overlay-btn {
            width: 64px; height: 64px; border-radius: 50%; border: none;
            display: flex; align-items: center; justify-content: center; font-size: 28px; cursor: pointer;
        }
        .btn-accept { background: #4caf50; color: #fff; }
        .btn-decline { background: #ff4d4d; color: #fff; }

        /* Fake Chat Area */
        .chat-area { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .message { max-width: 80%; padding: 12px 16px; border-radius: 18px; font-size: 14px; line-height: 1.5; background: rgba(255,255,255,0.05); color: #e0e0e0; animation: fadeIn 0.3s ease; }
        .message.ai { align-self: flex-start; background: rgba(138,180,248,0.08); border: 1px solid rgba(138,180,248,0.15); }
        .message.system { align-self: center; background: transparent; font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 1px; font-family: 'Roboto Mono', monospace; }
        .message.log { align-self: flex-start; background: rgba(255,255,255,0.03); color: #888; font-size: 12px; font-family: 'Roboto Mono', monospace; border-left: 2px solid #3ea6ff; padding-left: 12px; }
        .message h4 { margin: 0 0 6px 0; font-size: 13px; color: #8ab4f8; font-weight: 500; }
        .message p { margin: 0; }
        
        /* Thinking Animation */
        .thinking-container { display: flex; align-items: center; gap: 8px; padding: 12px 16px; align-self: flex-start; }
        .thinking-dot { width: 8px; height: 8px; background: #8ab4f8; border-radius: 50%; animation: thinkBounce 1.4s infinite ease-in-out; }
        .thinking-dot:nth-child(1) { animation-delay: 0s; }
        .thinking-dot:nth-child(2) { animation-delay: 0.2s; }
        .thinking-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes thinkBounce {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .input-mimic { padding: 16px; border-top: 1px solid #222; color: #444; font-size: 14px; display: flex; justify-content: space-between; flex-shrink: 0; }

        /* Call comments overlay */
        .call-comments {
            position: fixed; bottom: 60px; left: 0; right: 0;
            z-index: 8000; pointer-events: none;
            display: flex; flex-direction: column-reverse;
            align-items: center; gap: 6px; padding: 10px;
            max-height: 40vh; overflow: hidden;
        }
        .call-comment {
            background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
            color: #fff; padding: 10px 18px; border-radius: 20px;
            font-size: 14px; max-width: 80%; word-break: break-word;
            animation: comment-in 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
            pointer-events: auto;
        }
        .call-comment.self {
            background: rgba(138,180,248,0.2);
            border-color: rgba(138,180,248,0.3);
        }
        .call-comment.fade-out {
            animation: comment-out 0.3s ease forwards;
        }
        @keyframes comment-in {
            from { opacity: 0; transform: translateY(20px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes comment-out {
            from { opacity: 1; transform: translateY(0) scale(1); }
            to { opacity: 0; transform: translateY(-10px) scale(0.9); }
        }
        .call-input-area {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 16px; border-top: 1px solid #222;
            background: var(--gemini-bg); flex-shrink: 0;
        }
        .call-input-area input {
            flex: 1; background: #1a1a1a; border: 1px solid #333;
            border-radius: 20px; padding: 10px 16px; color: #fff;
            font-size: 14px; outline: none;
        }
        .call-input-area input:focus { border-color: var(--gemini-blue); }
        .call-send-btn {
            background: var(--gemini-blue); border: none;
            width: 38px; height: 38px; border-radius: 50%;
            color: #000; font-size: 16px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; transition: all 0.2s;
        }
        .call-send-btn:hover { transform: scale(1.1); }

        /* Record button */
        .record-btn {
            background: none; border: none; color: #fff; font-size: 18px;
            cursor: pointer; padding: 6px; transition: all 0.3s ease;
            position: relative; line-height: 1;
        }
        .record-btn.recording { color: #ff4444; animation: rec-pulse 1s infinite; }
        @keyframes rec-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
        .record-timer {
            font-size: 11px; color: #ff4444;
            font-family: 'Roboto Mono', monospace; min-width: 35px;
        }
    </style>
</head>
<body>
    <div class="app-container" id="main-app">
        <header>
            <div class="header-left" onclick="window.location.href='index.php'">
                <button class="menu-btn">☰</button>
                <span class="header-title">Gemini</span>
            </div>
            <div style="display:flex;align-items:center;gap:2px;">
                <?php if ($user_id == 1): ?>
                <button class="record-btn" id="record-btn" onclick="toggleRecording()" title="Record video call">⏺</button>
                <span class="record-timer" id="record-timer" style="display:none;">00:00</span>
                <?php endif; ?>
                <div class="sparkle-logo" id="sparkle-logo">✦</div>
            </div>
        </header>

        <div class="call-container" id="call-container">
            <video id="remote-video" autoplay playsinline></video>
            <video id="local-video" autoplay playsinline muted></video>
            <div class="call-mini-controls" id="call-mini-controls" onclick="event.stopPropagation();">
                <button class="control-btn" id="toggle-mic" onclick="toggleMic()" title="Toggle Mic">🎤</button>
                <button class="control-btn" id="toggle-video" onclick="toggleVideo()" title="Toggle Video">📹</button>
                <button class="control-btn" id="toggle-self" onclick="toggleSelfView()" title="Toggle Self View">👤</button>
                <button class="control-btn end" onclick="endCall()" title="End Call">📞</button>
            </div>
        </div>

        <div class="chat-area" id="chat-area" onclick="handleOutsideClick()">
            <div class="message system">[Node_Security_Active]</div>
            <div class="thinking-container" id="init-thinking">
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
            </div>
        </div>

        <div class="call-comments" id="call-comments"></div>

        <div class="call-input-area">
            <input type="text" id="call-input" placeholder="Enter a prompt here..." maxlength="500">
            <button class="call-send-btn" id="call-send-btn">✦</button>
        </div>
    </div>

    <!-- Incoming Call Overlay -->
    <div class="call-overlay" id="incoming-overlay">
        <div class="avatar-ring">✦</div>
        <div class="overlay-name">Gemini Team</div>
        <div class="overlay-status">Incoming Node Request...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-accept" onclick="acceptCall()">✔</button>
            <button class="overlay-btn btn-decline" onclick="declineCall()">✖</button>
        </div>
    </div>

    <!-- Outgoing Call Overlay -->
    <div class="call-overlay" id="outgoing-overlay">
        <div class="avatar-ring">✦</div>
        <div class="overlay-name">Gemini Team</div>
        <div class="overlay-status" id="outgoing-status">Connecting to Node...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-decline" onclick="endCall()">✖</button>
        </div>
    </div>

    <script>
        const USER_ID = <?php echo $user_id; ?>;
        const OTHER_ID = <?php echo $other_user_id; ?>;
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        // Unique session ID to prevent collisions on refresh
        const SESSION_ID = Math.random().toString(36).substring(2, 10);
        const HOST_HASH = btoa(window.location.hostname || 'local').replace(/[^a-z0-9]/gi, '').substring(0, 6).toLowerCase();
        const PROJECT_PREFIX = 'gm-' + HOST_HASH;
        
        let peer = null;
        let localStream;
        let currentCall;
        let incomingCall;
        let isOtherOnline = false;
        let wasOtherOnline = false;
        let micEnabled = true;
        let videoEnabled = true;
        let isCallUIHidden = false;
        let controlsTimer = null;
        let retryTimeout = null;
        let retryCount = 0;

        window.onload = () => {
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                alert('Security Notice: Camera/Mic require HTTPS. Localhost is also permitted.');
            }
            setTimeout(() => {
                const initThink = document.getElementById('init-thinking');
                if (initThink) initThink.remove();
                addChatMessage('[Node_Authorized]', 'system');
                addChatMessage('Secure node initialized. All communications are end-to-end encrypted.', 'ai');
            }, 1500);
            initPeer();
            checkPresence();
            setTimeout(() => playNextConversation(), 2000);
        };

        function showControls() {
            const controls = document.getElementById('call-mini-controls');
            controls.classList.remove('hidden');
            clearTimeout(controlsTimer);
            controlsTimer = setTimeout(() => {
                if (currentCall && !isCallUIHidden) {
                    controls.classList.add('hidden');
                }
            }, 3000);
        }

        function initPeer() {
            if (peer && !peer.destroyed) {
                try { peer.destroy(); } catch(e) {}
            }
            clearTimeout(retryTimeout);

            // Use a slightly more robust ID and standard cloud config
            const myPeerId = PROJECT_PREFIX + '-' + USER_ID + '-' + SESSION_ID;
            console.log('[Node_Init] Connecting as:', myPeerId);

            // Default config uses 0.peerjs.com with correct port/secure settings
            peer = new Peer(myPeerId, {
                debug: 2,
                config: { 
                    'iceServers': [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' },
                        { urls: 'stun:stun2.l.google.com:19302' }
                    ],
                    'sdpSemantics': 'unified-plan'
                }
            });

            peer.on('open', id => {
                console.log('[Node_Open] Active:', id);
                retryCount = 0; // Reset on success
                registerPeer(id);
                addChatMessage('[Node_Ready: Secure Signaling]');
            });

            peer.on('disconnected', () => {
                console.warn('[Node_Disconnected] Signaling connection lost');
                addChatMessage('[Node_Warning: Signal Lost. Reconnecting...]');
                if (!peer.destroyed) peer.reconnect();
            });

            peer.on('error', err => {
                console.error('[Node_Error]', err.type, err);
                if (err.type === 'unavailable-id') {
                    addChatMessage('[Error: ID Conflict. Refreshing Node...]');
                    window.location.reload();
                } else if (err.type === 'network' || err.type === 'server-error') {
                    retryCount++;
                    const delay = Math.min(1000 * Math.pow(2, retryCount), 30000);
                    addChatMessage('[Node_Error: Link Failed. Retrying in ' + (delay/1000) + 's...]');
                    retryTimeout = setTimeout(initPeer, delay);
                } else if (err.type === 'peer-unavailable') {
                    addChatMessage('[Notice: Partner node unreachable or timed out]');
                    document.getElementById('outgoing-overlay').classList.remove('show');
                } else {
                    addChatMessage('[Node_Status: ' + err.type + ']');
                }
            });

            peer.on('call', call => {
                console.log('[Node_Incoming] Request received');
                incomingCall = call;
                document.getElementById('incoming-overlay').classList.add('show');
            });
        }

        // Cleanup on close
        window.addEventListener('beforeunload', () => {
            if (peer) peer.destroy();
        });

        async function registerPeer(peerId) {
            try {
                const resp = await fetch('api.php?action=register_peer', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ peer_id: peerId })
                });
                const d = await resp.json();
                if (d.success) console.log('[Node_Sync] Peer ID registered to DB');
            } catch (e) { console.error('[Node_Sync] Failed to register ID', e); }
        }

        async function getOtherPeerId() {
            try {
                const resp = await fetch('api.php?action=get_peer');
                const data = await resp.json();
                return data.peer_id;
            } catch (e) { return null; }
        }

        async function acceptCall() {
            console.log('[Node_Call] Accepting request...');
            document.getElementById('incoming-overlay').classList.remove('show');
            if (incomingCall) {
                try {
                    const stream = await startLocalStream();
                    incomingCall.answer(stream);
                    handleCall(incomingCall);
                } catch (e) {
                    console.error('[Node_Call] Failed to start stream', e);
                    addChatMessage('[Error: Could not access media devices]');
                    incomingCall.close();
                }
            }
        }

        function declineCall() {
            console.log('[Node_Call] Declining request');
            document.getElementById('incoming-overlay').classList.remove('show');
            if (incomingCall) { incomingCall.close(); incomingCall = null; }
        }

        async function startLocalStream() {
            if (localStream) return localStream;
            console.log('[Node_Media] Requesting devices...');
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('local-video').srcObject = localStream;
                micEnabled = true; videoEnabled = true; updateControlIcons();
                return localStream;
            } catch (e) {
                console.warn('[Node_Media] Video failed, trying audio only', e);
                localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                videoEnabled = false; updateControlIcons();
                return localStream;
            }
        }

        function toggleMic() {
            if (!localStream) return;
            micEnabled = !micEnabled;
            localStream.getAudioTracks().forEach(track => track.enabled = micEnabled);
            updateControlIcons();
            showControls();
        }

        function toggleVideo() {
            if (!localStream) return;
            videoEnabled = !videoEnabled;
            localStream.getVideoTracks().forEach(track => track.enabled = videoEnabled);
            updateControlIcons();
            showControls();
        }

        function toggleSelfView() {
            const localVideo = document.getElementById('local-video');
            localVideo.classList.toggle('hidden');
            updateControlIcons();
            showControls();
        }

        function updateControlIcons() {
            const micBtn = document.getElementById('toggle-mic');
            const vidBtn = document.getElementById('toggle-video');
            const selfBtn = document.getElementById('toggle-self');
            const localVideo = document.getElementById('local-video');
            if(micBtn) {
                micBtn.textContent = micEnabled ? '🎤' : '🔇';
                micBtn.classList.toggle('off', !micEnabled);
            }
            if(vidBtn) {
                vidBtn.textContent = videoEnabled ? '📹' : '📵';
                vidBtn.classList.toggle('off', !videoEnabled);
            }
            if(selfBtn) {
                selfBtn.textContent = localVideo.classList.contains('hidden') ? '🚫' : '👤';
                selfBtn.classList.toggle('off', localVideo.classList.contains('hidden'));
            }
        }

        function handleCall(call) {
            currentCall = call;
            hasShownEndMessage = false; // Reset for new call
            isCallUIHidden = false;
            document.getElementById('outgoing-overlay').classList.remove('show');
            document.getElementById('call-container').classList.add('active');
            showThinking(() => addChatMessage('[Node_Connection_Established]', 'system'));
            showControls();
            
            call.on('stream', remoteStream => {
                console.log('[Node_Stream] Remote stream received');
                const remoteVideo = document.getElementById('remote-video');
                remoteVideo.srcObject = remoteStream;
                remoteVideo.play().then(() => {
                    addChatMessage('[Stream_Sync: Active]', 'log');
                    addChatMessage('Secure audio/video bridge is now active. Encrypted P2P connection established.', 'ai');
                }).catch(e => {
                    console.warn('[Node_Stream] Autoplay blocked', e);
                    addChatMessage('[Stream_Notice: Click to activate audio]', 'log');
                });
            });
            call.on('close', () => {
                if (!hasShownEndMessage) {
                    hasShownEndMessage = true;
                    showThinking(() => {
                        addChatMessage('[Node_Session_Ended]', 'system');
                        endCall();
                    });
                }
            });
            call.on('error', e => { 
                console.error('[Node_Stream_Error]', e);
                if (!hasShownEndMessage) {
                    hasShownEndMessage = true;
                    addChatMessage('[Call_Terminated: Node Error]', 'system'); 
                }
                endCall(); 
            });
        }

        function handleOutsideClick() {
            if (currentCall && !isCallUIHidden) hideCallUI();
        }

        async function initiateCall() {
            if (!isOtherOnline) {
                addChatMessage('[Error: Partner node unreachable]');
                return;
            }
            const otherPeerId = await getOtherPeerId();
            if (!otherPeerId) { 
                addChatMessage('[Error: Partner sync ID missing. Ask them to refresh.]'); 
                return; 
            }
            
            console.log('[Node_Call] Initiating to:', otherPeerId);
            document.getElementById('outgoing-overlay').classList.add('show');
            document.getElementById('outgoing-status').textContent = 'SYNCING NODES...';
            
            try {
                const stream = await startLocalStream();
                const call = peer.call(otherPeerId, stream);
                if (!call) throw new Error('Call object creation failed');
                handleCall(call);
            } catch (e) {
                console.error('[Node_Call] Initiation failed', e);
                addChatMessage('[Error: Call setup failed]');
                document.getElementById('outgoing-overlay').classList.remove('show');
            }
        }

        let isEnding = false;
        function endCall() {
            if (isEnding) return; // Prevent duplicate calls
            isEnding = true;
            
            console.log('[Node_Call] Ending session');
            if (currentCall) try { currentCall.close(); } catch(e){}
            if (incomingCall) try { incomingCall.close(); } catch(e){}
            if (localStream) localStream.getTracks().forEach(track => track.stop());
            
            isCallUIHidden = false;
            document.getElementById('call-container').classList.remove('active');
            document.getElementById('incoming-overlay').classList.remove('show');
            document.getElementById('outgoing-overlay').classList.remove('show');
            document.getElementById('remote-video').srcObject = null;
            document.getElementById('local-video').srcObject = null;
            
            currentCall = null; 
            incomingCall = null;
            localStream = null;
            
            clearTimeout(controlsTimer);
            if (!hasShownEndMessage) {
                hasShownEndMessage = true;
                showThinking(() => {
                    addChatMessage('[Node_Session_Ended]', 'system');
                    isEnding = false;
                });
            } else {
                isEnding = false;
            }
        }

        function hideCallUI() {
            isCallUIHidden = true;
            document.getElementById('call-container').classList.remove('active');
            clearTimeout(controlsTimer);
            addChatMessage('[Node_Minimized: Background Active]');
        }

        function showCallUI() {
            if (currentCall) {
                isCallUIHidden = false;
                document.getElementById('call-container').classList.add('active');
                showControls();
            }
        }

        function addChatMessage(text, type = 'system') {
            const area = document.getElementById('chat-area');
            const msg = document.createElement('div');
            msg.className = 'message ' + type;
            if (type === 'ai') {
                msg.innerHTML = `<h4>✦ Gemini</h4><p>${text}</p>`;
            } else {
                msg.textContent = text;
            }
            area.appendChild(msg);
            area.scrollTop = area.scrollHeight;
        }

        function showThinking(callback) {
            const area = document.getElementById('chat-area');
            const think = document.createElement('div');
            think.className = 'thinking-container';
            think.innerHTML = '<div class="thinking-dot"></div><div class="thinking-dot"></div><div class="thinking-dot"></div>';
            area.appendChild(think);
            area.scrollTop = area.scrollHeight;
            setTimeout(() => {
                think.remove();
                if (callback) callback();
            }, 1200 + Math.random() * 800);
        }

        // Simulated AI conversation flow (relative delays in ms)
        const aiConversation = [
            { delay: 2000, type: 'log', text: '[Nodes_Synchronized: Secure Channel]' },
            { delay: 3000, type: 'ai', text: 'Hello! I\'m your Gemini assistant. All communications are end-to-end encrypted and ephemeral.' },
            { delay: 3000, type: 'log', text: '[Context_Injected: Session Memory Active]' },
            { delay: 2500, type: 'ai', text: 'I\'m analyzing the secure node connection. All systems are operational.' },
            { delay: 3000, type: 'log', text: '[TensorFlow_Lite: Model Ready]' },
            { delay: 3500, type: 'ai', text: 'How can I assist you today? I can help with code, research, analysis, or just chat.' },
            { delay: 3000, type: 'log', text: '[Attention_Heads: 12/12 Active]' },
            { delay: 4000, type: 'ai', text: 'I notice your partner node is online. Would you like me to establish a secure audio/video bridge?' },
            { delay: 3000, type: 'log', text: '[WebRTC_Signaling: Standing By]' },
            { delay: 3500, type: 'ai', text: 'I\'m continuously monitoring the connection quality and will optimize in real-time.' },
            { delay: 3000, type: 'log', text: '[Inference_Engine: Latency Stable]' },
        ];

        let convIndex = 0;
        let hasShownEndMessage = false;
        function playNextConversation() {
            if (convIndex >= aiConversation.length) {
                // Repeat with system logs only
                setInterval(() => {
                    const logs = ['[Nodes_Heartbeat: OK]', '[Cache_Warming: Complete]', '[GPU_Cluster: Idle]', '[Memory_Pool: 2.3GB Free]', '[Entropy_Check: Passed]'];
                    addChatMessage(logs[Math.floor(Math.random() * logs.length)], 'log');
                }, 15000);
                return;
            }
            const item = aiConversation[convIndex];
            setTimeout(() => {
                if (item.type === 'ai') {
                    showThinking(() => {
                        addChatMessage(item.text, 'ai');
                        convIndex++;
                        playNextConversation();
                    });
                } else {
                    addChatMessage(item.text, item.type);
                    convIndex++;
                    playNextConversation();
                }
            }, item.delay);
        }

        async function checkPresence() {
            try {
                const resp = await fetch('api.php?action=get_other_status');
                const data = await resp.json();
                wasOtherOnline = isOtherOnline;
                isOtherOnline = data.status === 'active' || data.status === 'typing';
                if (isOtherOnline && !wasOtherOnline) {
                    showThinking(() => addChatMessage('[Node_Joined: Partner entered Secure Call]', 'system'));
                }
                const logo = document.getElementById('sparkle-logo');
                if (logo) {
                    logo.classList.toggle('online', isOtherOnline);
                }
            } catch (e) { console.error('[Node_Presence] Check failed', e); }
        }

        setInterval(() => {
            fetch('api.php?action=update_status', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_typing: 0, in_theater: 0 })
            });
            checkPresence();
        }, 5000);

        const logo = document.getElementById('sparkle-logo');
        if (logo) {
            logo.onclick = () => {
                if (currentCall) showCallUI();
                else if (isOtherOnline) initiateCall();
                else addChatMessage('[Node_Offline: Sync Unavailable]');
            };
        }

        const callCont = document.getElementById('call-container');
        if (callCont) {
            callCont.onclick = () => {
                if (currentCall && !isCallUIHidden) showControls();
            };
        }
    </script>

    <!-- Call Comments Module (independent – does not modify any call functions) -->
    <script>
    let knownCommentIds = new Set();
    let commentTimers = {};

    async function sendCallComment() {
        const input = document.getElementById('call-input');
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        try {
            await fetch('api.php?action=send_call_comment', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: text })
            });
        } catch(e) { console.error('[CallComment] Send failed', e); }
    }

    async function pollCallComments() {
        try {
            const r = await fetch('api.php?action=get_call_comments');
            const comments = await r.json();
            const currentIds = new Set();
            comments.forEach(c => {
                currentIds.add(c.id);
                if (!knownCommentIds.has(c.id)) {
                    knownCommentIds.add(c.id);
                    showComment(c);
                    const timer = setTimeout(() => removeComment(c.id), 10000);
                    commentTimers[c.id] = timer;
                }
            });
            knownCommentIds.forEach(id => {
                if (!currentIds.has(id)) {
                    knownCommentIds.delete(id);
                    if (commentTimers[id]) { clearTimeout(commentTimers[id]); delete commentTimers[id]; }
                }
            });
        } catch(e) { /* ignore */ }
    }

    function showComment(c) {
        const container = document.getElementById('call-comments');
        const el = document.createElement('div');
        el.className = 'call-comment' + (c.sender_id == USER_ID ? ' self' : '');
        el.id = 'cc-' + c.id;
        el.textContent = c.content;
        container.appendChild(el);
    }

    function removeComment(id) {
        const el = document.getElementById('cc-' + id);
        if (!el) return;
        el.classList.add('fade-out');
        setTimeout(() => { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
        delete commentTimers[id];
        knownCommentIds.delete(id);
        fetch('api.php?action=delete_call_comment', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        }).catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('call-input');
        const btn = document.getElementById('call-send-btn');
        if (input) {
            input.addEventListener('keydown', (e) => { if (e.key === 'Enter') sendCallComment(); });
        }
        if (btn) btn.addEventListener('click', sendCallComment);
        setInterval(pollCallComments, 2000);
    });
    </script>

    <!-- Recording Module (independent – does not modify any call functions) -->
    <script>
    <?php if ($user_id == 1): ?>
    let recMediaRecorder = null;
    let recChunks = [];
    let isRecording = false;
    let recTimerInterval = null;
    let recSeconds = 0;

    function getRecordingStream() {
        const remoteVideo = document.getElementById('remote-video');
        const remoteStream = remoteVideo ? remoteVideo.srcObject : null;
        if (!remoteStream) { console.log('[Record] No remote stream found'); return null; }
        console.log('[Record] Remote tracks:', remoteStream.getTracks().length);
        remoteStream.getTracks().forEach(t => console.log('[Record] Track:', t.kind, t.readyState));
        return new MediaStream(remoteStream.getTracks());
    }

    async function toggleRecording() {
        const btn = document.getElementById('record-btn');
        const timer = document.getElementById('record-timer');
        if (isRecording) {
            isRecording = false;
            if (recMediaRecorder && recMediaRecorder.state !== 'inactive') {
                recMediaRecorder.stop();
            }
            clearInterval(recTimerInterval);
            btn.classList.remove('recording');
            btn.textContent = '⏺';
            timer.style.display = 'none';
            return;
        }
        const stream = getRecordingStream();
        if (!stream) {
            addChatMessage('[Record_Error: No active call stream]');
            return;
        }
        recChunks = [];
        recSeconds = 0;
        try {
            recMediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp8,opus' });
        } catch(e) {
            try { recMediaRecorder = new MediaRecorder(stream); }
            catch(e2) { addChatMessage('[Record_Error: MediaRecorder not supported]'); return; }
        }
        recMediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) recChunks.push(e.data);
        };
        recMediaRecorder.onstop = async () => {
            console.log('[Record] Chunks collected:', recChunks.length, 'total bytes:', recChunks.reduce((s,c) => s + c.size, 0));
            const blob = new Blob(recChunks, { type: 'video/webm' });
            if (blob.size === 0) { addChatMessage('[Record_Error: No data captured]'); return; }
            const fd = new FormData();
            fd.append('recording', blob, 'call_rec_' + Date.now() + '.webm');
            try {
                const r = await fetch('api.php?action=save_recording', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: fd
                });
                const text = await r.text();
                console.log('[Record] Server response:', r.status, text);
                let d;
                try { d = JSON.parse(text); } catch(e) { d = { success: false, error: text }; }
                addChatMessage(d.success ? '[Record_Saved: Call recording saved to vault]' : '[Record_Error: ' + (d.error || 'Unknown') + ']');
            } catch(e) {
                console.error('[Record] Fetch error:', e);
                addChatMessage('[Record_Error: Upload failed - ' + e.message + ']');
            }
        };
        recMediaRecorder.start(1000);
        isRecording = true;
        btn.classList.add('recording');
        btn.textContent = '⏹';
        timer.style.display = 'inline';
        timer.textContent = '00:00';
        recTimerInterval = setInterval(() => {
            recSeconds++;
            timer.textContent = String(Math.floor(recSeconds / 60)).padStart(2, '0') + ':' + String(recSeconds % 60).padStart(2, '0');
        }, 1000);
        addChatMessage('[Record_Started: Call recording started]');
    }
    <?php endif; ?>
    </script>
</body>
</html>
