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
        .chat-area { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 16px; opacity: 0.8; }
        .message { max-width: 80%; padding: 12px 16px; border-radius: 18px; font-size: 14px; line-height: 1.5; background: rgba(255,255,255,0.05); color: #aaa; }
        .msg-system { align-self: center; background: transparent; font-size: 11px; color: #444; text-transform: uppercase; letter-spacing: 1px; }
        .input-mimic { padding: 16px; border-top: 1px solid #222; color: #444; font-size: 14px; display: flex; justify-content: space-between; flex-shrink: 0; }
    </style>
</head>
<body>
    <div class="app-container" id="main-app">
        <header>
            <div class="header-left" onclick="window.location.href='index.php'">
                <button class="menu-btn">☰</button>
                <span class="header-title">Gemini</span>
            </div>
            <div class="sparkle-logo" id="sparkle-logo">✦</div>
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
            <div class="message msg-system">[Node_Security_Active]</div>
            <div class="message">Gemini is a private, encrypted environment. All interactions are ephemeral.</div>
            <div class="message">System logs are synchronized across both nodes.</div>
            <div class="message msg-system" id="last-sync-log">Last sync: <?php echo date('H:i:s'); ?></div>
        </div>

        <div class="input-mimic" onclick="handleOutsideClick()">
            <span>Enter a prompt here...</span>
            <span>✦</span>
        </div>
    </div>

    <!-- Incoming Call Overlay -->
    <div class="call-overlay" id="incoming-overlay">
        <div class="avatar-ring"><?php echo $other_name[0]; ?></div>
        <div class="overlay-name"><?php echo $other_name; ?></div>
        <div class="overlay-status">Incoming Node Request...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-accept" onclick="acceptCall()">✔</button>
            <button class="overlay-btn btn-decline" onclick="declineCall()">✖</button>
        </div>
    </div>

    <!-- Outgoing Call Overlay -->
    <div class="call-overlay" id="outgoing-overlay">
        <div class="avatar-ring"><?php echo $other_name[0]; ?></div>
        <div class="overlay-name"><?php echo $other_name; ?></div>
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
            addChatMessage('[Node_Authorized]');
            initPeer();
            checkPresence();
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
            isCallUIHidden = false;
            document.getElementById('outgoing-overlay').classList.remove('show');
            document.getElementById('call-container').classList.add('active');
            addChatMessage('[Node_Connection_Established]');
            showControls();
            
            call.on('stream', remoteStream => {
                console.log('[Node_Stream] Remote stream received');
                const remoteVideo = document.getElementById('remote-video');
                remoteVideo.srcObject = remoteStream;
                remoteVideo.play().then(() => {
                    addChatMessage('[Stream_Sync: Active]');
                }).catch(e => {
                    console.warn('[Node_Stream] Autoplay blocked', e);
                    addChatMessage('[Stream_Notice: Click to activate audio]');
                });
            });
            call.on('close', endCall);
            call.on('error', e => { 
                console.error('[Node_Stream_Error]', e);
                addChatMessage('[Call_Terminated: Node Error]'); 
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

        function endCall() {
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
            addChatMessage('[Node_Session_Ended]');
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

        function addChatMessage(text) {
            const area = document.getElementById('chat-area');
            const msg = document.createElement('div');
            msg.className = 'message msg-system';
            msg.textContent = text;
            area.appendChild(msg);
            area.scrollTop = area.scrollHeight;
        }

        async function checkPresence() {
            try {
                const resp = await fetch('api.php?action=get_other_status');
                const data = await resp.json();
                wasOtherOnline = isOtherOnline;
                isOtherOnline = data.status === 'active' || data.status === 'typing';
                if (isOtherOnline && !wasOtherOnline) {
                    addChatMessage('[Node_Joined: Partner entered Secure Call]');
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
</body>
</html>
