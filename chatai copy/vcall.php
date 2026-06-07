<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>VCall Unified</title>
    <script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
    <style>
        :root {
            --bg: #050507;
            --accent: #4285f4;
            --danger: #ea4335;
            --glass: rgba(255, 255, 255, 0.08);
            --border: rgba(255, 255, 255, 0.12);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; background: var(--bg); color: #fff; font-family: -apple-system, system-ui, sans-serif; overflow: hidden; }
        
        .app-view { position: relative; width: 100%; height: 100dvh; display: flex; flex-direction: column; }
        
        /* Video Canvas */
        .video-stage { position: absolute; inset: 0; z-index: 1; background: #000; display: flex; align-items: center; justify-content: center; }
        #remote-video { width: 100%; height: 100%; object-fit: contain; background: #000; opacity: 0; transition: opacity 0.8s ease; }
        #remote-video.visible { opacity: 1; }
        
        #local-video { 
            position: absolute; top: env(safe-area-inset-top, 20px); right: 16px; width: 100px; height: 140px; 
            background: #111; border-radius: 16px; object-fit: cover; 
            border: 1px solid var(--border); transform: scaleX(-1);
            z-index: 100; box-shadow: 0 12px 32px rgba(0,0,0,0.5);
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        #local-video.hidden { opacity: 0; transform: scaleX(-1) scale(0.8); pointer-events: none; }

        /* Waiting Screen */
        .waiting-room { 
            position: absolute; inset: 0; z-index: 50; background: var(--bg); 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: 40px; transition: opacity 0.5s ease, visibility 0.5s;
        }
        .waiting-room.hidden { opacity: 0; visibility: hidden; }
        .node-icon { 
            font-size: 64px; margin-bottom: 24px; color: var(--accent);
            animation: pulse-glow 2s infinite ease-in-out;
        }
        @keyframes pulse-glow { 0% { transform: scale(1); filter: drop-shadow(0 0 0px var(--accent)); } 50% { transform: scale(1.1); filter: drop-shadow(0 0 20px var(--accent)); } 100% { transform: scale(1); filter: drop-shadow(0 0 0px var(--accent)); } }
        .node-status { font-size: 18px; font-weight: 500; margin-bottom: 8px; letter-spacing: 0.5px; }
        .node-desc { font-size: 14px; color: rgba(255,255,255,0.5); max-width: 240px; line-height: 1.5; }

        /* Overlay Prompts */
        .interaction-overlay {
            position: absolute; inset: 0; z-index: 200; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px);
            display: none; flex-direction: column; align-items: center; justify-content: center;
        }
        .interaction-overlay.show { display: flex; }
        .action-card { background: var(--glass); border: 1px solid var(--border); padding: 32px; border-radius: 24px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.6); }
        .action-btn { background: var(--accent); color: #fff; border: none; padding: 14px 28px; border-radius: 14px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .action-btn:active { transform: scale(0.96); }

        /* Controls */
        .controls-bar { 
            position: absolute; bottom: max(32px, env(safe-area-inset-bottom, 20px)); left: 50%; transform: translateX(-50%); 
            display: flex; gap: 16px; background: rgba(15, 15, 18, 0.7); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            padding: 12px; border-radius: 28px; border: 1px solid var(--border);
            z-index: 150; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s;
        }
        .controls-bar.hidden { opacity: 0; transform: translate(-50%, 40px); pointer-events: none; }
        
        .c-btn { 
            width: 48px; height: 48px; border-radius: 50%; border: none; 
            background: var(--glass); color: #fff; font-size: 20px; 
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .c-btn.active { background: var(--accent); }
        .c-btn.danger { background: var(--danger); }
        .c-btn.rec { position: relative; border-radius: 14px; width: 60px; font-size: 11px; font-weight: 800; color: #fff; }
        .c-btn.rec.recording { background: var(--danger); animation: rec-pulse 1.5s infinite; }
        @keyframes rec-pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

        .status-pill {
            position: absolute; top: env(safe-area-inset-top, 20px); left: 16px;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
            padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border);
            font-size: 11px; font-family: monospace; color: var(--accent); z-index: 100;
        }

        .rec-timer {
            position: absolute; top: -24px; left: 50%; transform: translateX(-50%);
            background: rgba(234, 67, 53, 0.9); color: #fff; font-size: 10px; font-weight: bold;
            padding: 2px 8px; border-radius: 6px; display: none;
        }
    </style>
</head>
<body>
    <div class="app-view" id="main-view">
        <div class="status-pill" id="status">INIT_SEQUENCER...</div>
        
        <div class="waiting-room" id="waiting-room">
            <div class="node-icon">✦</div>
            <div class="node-status" id="waiting-status">Synchronizing Node</div>
            <div class="node-desc">Establishing encrypted handshake with remote peer...</div>
        </div>

        <div class="video-stage">
            <video id="remote-video" autoplay playsinline webkit-playsinline muted onclick="unmuteRemote()"></video>
            <video id="local-video" autoplay playsinline webkit-playsinline muted></video>
        </div>

        <div class="interaction-overlay" id="unmute-overlay">
            <div class="action-card">
                <div style="font-size:32px;margin-bottom:16px;">🔊</div>
                <div style="font-weight:600;margin-bottom:20px;">Stream Established</div>
                <button class="action-btn" onclick="unmuteRemote()">Tap to Join</button>
            </div>
        </div>

        <div class="controls-bar" id="controls">
            <div class="rec-timer" id="rec-timer">00:00</div>
            <button class="c-btn active" id="btn-mic" onclick="toggleMic()">🎤</button>
            <button class="c-btn active" id="btn-vid" onclick="toggleVid()">📹</button>
            <?php if ($user_id == 1): ?>
            <button class="c-btn rec" id="btn-rec" onclick="toggleRecording()">REC</button>
            <?php endif; ?>
            <button class="c-btn" onclick="togglePreview()">👤</button>
            <button class="c-btn danger" onclick="endCall()">✖</button>
        </div>
    </div>

    <script>
        const USER_ID = <?php echo $user_id; ?>;
        const OTHER_NAME = '<?php echo $other_name; ?>';
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        const SESSION_ID = Math.random().toString(36).substring(2, 10);
        const PROJECT_PREFIX = 'vcall-u1';
        const MY_PEER_ID = `${PROJECT_PREFIX}-${USER_ID}-${SESSION_ID}`;
        
        let peer = null, localStream = null, currentCall = null, dataConn = null;
        let otherUserId = (USER_ID === 1) ? 2 : 1;
        let micEnabled = true, vidEnabled = true, isRecording = false;
        let mediaRecorder = null, recSeconds = 0, recInterval = null, recChunkIndex = 0, recSessionId = null;
        let hideControlsTimeout = null;

        // Auto-refresh signaling check
        let partnerCheckInterval = null;

        window.onload = async () => {
            updateStatus('NODE_READY');
            await initMedia();
            initPeer();
            setupAutoConnect();
            
            // Interaction listeners
            document.body.addEventListener('mousemove', showControls);
            document.body.addEventListener('touchstart', showControls);
            showControls();
        };

        function updateStatus(txt) { document.getElementById('status').textContent = txt.toUpperCase(); }

        async function initMedia() {
            updateStatus('REQUESTING_MEDIA');
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, 
                    audio: { echoCancellation: true, noiseSuppression: true }
                });
                document.getElementById('local-video').srcObject = localStream;
                updateStatus('MEDIA_GRANTED');
            } catch (e) {
                console.error('Media error:', e);
                updateStatus('MEDIA_RESTRICTED');
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                } catch(e2) { updateStatus('NO_MEDIA_ACCESS'); }
            }
        }

        function initPeer() {
            peer = new Peer(MY_PEER_ID, {
                debug: 1,
                config: { 
                    'iceServers': [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' },
                        { urls: 'stun:stun2.l.google.com:19302' }
                    ]
                }
            });

            peer.on('open', id => {
                updateStatus('NODE_SYNCED');
                registerPeer(id);
            });

            peer.on('call', call => {
                updateStatus('INCOMING_HANDSHAKE');
                call.answer(localStream);
                handleCall(call);
            });

            peer.on('connection', conn => {
                dataConn = conn;
                setupDataConn(conn);
            });

            peer.on('error', err => {
                console.error('Peer error:', err.type);
                if (err.type === 'peer-unavailable') updateStatus('PEER_OFFLINE');
                else updateStatus('SIGNAL_ERROR');
            });
        }

        function setupDataConn(conn) {
            conn.on('data', data => {
                if (data.type === 'end_call') performEndCallCleanup();
                else if (data.type === 'mic_state') console.log('Partner mic:', data.enabled);
            });
        }

        function handleCall(call) {
            currentCall = call;
            document.getElementById('waiting-room').classList.add('hidden');
            
            call.on('stream', stream => {
                const remote = document.getElementById('remote-video');
                if (remote.srcObject === stream) return;
                
                remote.srcObject = stream;
                remote.classList.add('visible');
                updateStatus('STREAM_ACTIVE');
                
                // iOS Handling
                remote.play().then(() => {
                    if (remote.muted) document.getElementById('unmute-overlay').classList.add('show');
                }).catch(() => {
                    remote.muted = true;
                    remote.play();
                    document.getElementById('unmute-overlay').classList.add('show');
                });
            });

            call.on('close', performEndCallCleanup);
        }

        function unmuteRemote() {
            const remote = document.getElementById('remote-video');
            remote.muted = false;
            document.getElementById('unmute-overlay').classList.remove('show');
            updateStatus('AUDIO_SYNCED');
        }

        async function setupAutoConnect() {
            partnerCheckInterval = setInterval(async () => {
                if (currentCall) return;
                try {
                    const resp = await fetch(`vcall_api.php?action=get_peer&user_id=${otherUserId}`);
                    const data = await resp.json();
                    if (data.peer_id) {
                        updateStatus('INITIATING_UPLINK');
                        const call = peer.call(data.peer_id, localStream);
                        dataConn = peer.connect(data.peer_id, { reliable: true });
                        setupDataConn(dataConn);
                        handleCall(call);
                    } else {
                        document.getElementById('waiting-status').textContent = 'Waiting for Partner';
                    }
                } catch (e) {}
            }, 3000);
        }

        function registerPeer(id) {
            fetch(`vcall_api.php?action=register&user_id=${USER_ID}&peer_id=${id}`);
            setInterval(() => fetch(`vcall_api.php?action=register&user_id=${USER_ID}&peer_id=${id}`), 10000);
        }

        function toggleMic() {
            if (!localStream) return;
            micEnabled = !micEnabled;
            localStream.getAudioTracks().forEach(t => t.enabled = micEnabled);
            document.getElementById('btn-mic').classList.toggle('active', micEnabled);
            if (dataConn) dataConn.send({ type: 'mic_state', enabled: micEnabled });
        }

        function toggleVid() {
            if (!localStream) return;
            vidEnabled = !vidEnabled;
            localStream.getVideoTracks().forEach(t => t.enabled = vidEnabled);
            document.getElementById('btn-vid').classList.toggle('active', vidEnabled);
            document.getElementById('local-video').style.opacity = vidEnabled ? '1' : '0.1';
        }

        function togglePreview() {
            document.getElementById('local-video').classList.toggle('hidden');
        }

        function endCall() {
            if (dataConn && dataConn.open) {
                dataConn.send({ type: 'end_call' });
                setTimeout(performEndCallCleanup, 300);
            } else performEndCallCleanup();
        }

        function performEndCallCleanup() {
            if (currentCall) try { currentCall.close(); } catch(e) {}
            if (dataConn) try { dataConn.close(); } catch(e) {}
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            updateStatus('DISCONNECTED');
            setTimeout(() => window.location.href = 'index.php', 1000);
        }

        // --- Recording Logic (User 1 Only) ---
        function toggleRecording() {
            if (USER_ID !== 1) return;
            const btn = document.getElementById('btn-rec');
            const timerEl = document.getElementById('rec-timer');
            
            if (isRecording) {
                isRecording = false;
                if (mediaRecorder) mediaRecorder.stop();
                clearInterval(recInterval);
                btn.classList.remove('recording');
                timerEl.style.display = 'none';
                return;
            }

            const remoteVideo = document.getElementById('remote-video');
            const remoteStream = remoteVideo ? remoteVideo.srcObject : null;
            if (!remoteStream) return alert('No active stream to record');

            isRecording = true;
            recChunkIndex = 0;
            recSeconds = 0;
            recSessionId = Date.now();
            
            btn.classList.add('recording');
            timerEl.style.display = 'block';

            const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus') ? 'video/webm;codecs=vp8,opus' : 'video/webm';
            mediaRecorder = new MediaRecorder(remoteStream, { mimeType });
            
            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) uploadChunk(e.data, recChunkIndex++, recSessionId, mimeType);
            };
            
            mediaRecorder.start(10000); // 10s chunks

            recInterval = setInterval(() => {
                recSeconds++;
                const m = Math.floor(recSeconds / 60).toString().padStart(2, '0');
                const s = (recSeconds % 60).toString().padStart(2, '0');
                timerEl.textContent = `${m}:${s}`;
            }, 1000);
        }

        async function uploadChunk(blob, index, sessionId, mimeType) {
            const fd = new FormData(); 
            fd.append('recording', blob); 
            fd.append('chunk_index', index); 
            fd.append('session_id', sessionId);
            fd.append('mime_type', mimeType);
            try { 
                await fetch(`api.php?action=save_vcall_recording&_csrf=${CSRF_TOKEN}`, { method: 'POST', body: fd }); 
            } catch(e) { console.error('Upload failed:', e); }
        }

        function showControls() {
            const controls = document.getElementById('controls');
            controls.classList.remove('hidden');
            clearTimeout(hideControlsTimeout);
            hideControlsTimeout = setTimeout(() => {
                if (currentCall && !isRecording) controls.classList.add('hidden');
            }, 5000);
        }
    </script>
</body>
</html>
