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
    <title>Gemini AI</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: var(--gemini-bg); }
        
        .app-container { 
            display: flex; flex-direction: column; width: 100%;
            height: 100dvh; height: -webkit-fill-available;
            background: var(--gemini-bg); overflow: hidden; position: relative;
        }
        header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 16px; padding-top: max(10px, env(safe-area-inset-top, 0px));
            background: var(--gemini-bg); border-bottom: 1px solid rgba(138,180,248,0.12);
            z-index: 1000; flex-shrink: 0; position: sticky; top: 0;
            min-height: 52px;
        }
        header::after {
            content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, #4285f4, #c58af9, #4285f4, transparent);
            background-size: 200% 100%; animation: shimmer 4s linear infinite;
        }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .header-left { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .menu-btn { background: none; border: none; color: #fff; font-size: 20px; padding: 0; line-height: 1; cursor: pointer; }
        .header-title { font-size: 18px; font-weight: 600; background: linear-gradient(135deg, #8ab4f8, #c58af9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .sparkle-logo {
            font-size: 22px; cursor: pointer; padding: 6px; transition: all 0.5s ease;
            user-select: none; filter: grayscale(1); opacity: 0.4; color: #fff; line-height: 1;
        }
        .sparkle-logo.online {
            filter: grayscale(0); opacity: 1;
            color: var(--gemini-blue);
            text-shadow: 0 0 12px rgba(138,180,248,0.5);
        }

        /* Stream Area */
        .call-container {
            display: none; background: #000; width: 100%; position: relative;
            z-index: 900; overflow: hidden; border-bottom: 1px solid rgba(255,255,255,0.06); cursor: pointer;
        }
        .call-container.active { display: block; width: 100%; height: auto; max-height: 65vh; flex-shrink: 0; aspect-ratio: 16/9; }
        #remote-video { width: 100%; height: auto; max-height: 65vh; background: #000; display: block; object-fit: contain; pointer-events: none; }
        #local-video {
            position: absolute; top: 12px; right: 12px; width: 100px; max-height: 160px;
            border-radius: 10px; border: 1px solid rgba(255,255,255,0.15);
            background: #111; object-fit: cover; z-index: 910;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5); transition: opacity 0.3s ease, transform 0.3s ease;
        }
        #local-video.hidden { opacity: 0; pointer-events: none; transform: scale(0.8); }

        .call-mini-controls {
            position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
            padding: 8px 16px; background: rgba(0,0,0,0.7); backdrop-filter: blur(12px);
            border-radius: 24px; display: flex; justify-content: center; gap: 12px;
            z-index: 920; border: 1px solid rgba(255,255,255,0.08);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }
        .call-mini-controls.hidden { opacity: 0; pointer-events: none; transform: translateX(-50%) translateY(16px); }
        #bc-menu.hidden { opacity: 0; pointer-events: none; transform: translateX(-50%) translateY(16px); }
        .control-btn {
            background: rgba(255,255,255,0.08); border: none; width: 40px; height: 40px;
            border-radius: 50%; color: #fff; cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 18px; transition: all 0.2s;
        }
        .control-btn:active { transform: scale(0.9); }
        .control-btn.off { background: rgba(255,77,77,0.35); color: #ff4d4d; }
        .control-btn.end { background: #ff4d4d; color: #fff; width: 46px; border-radius: 12px; }

        /* Overlays */
        .call-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.92); z-index: 10000;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }
        .call-overlay.show { display: flex; }
        .avatar-ring {
            width: 100px; height: 100px; border-radius: 50%;
            background: linear-gradient(135deg, rgba(138,180,248,0.15), rgba(197,138,249,0.15));
            border: 2px solid rgba(138,180,248,0.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; margin-bottom: 16px; position: relative; color: #fff;
        }
        .avatar-ring::after {
            content: ""; position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px;
            border-radius: 50%; border: 1.5px solid rgba(138,180,248,0.3);
            animation: ring-pulse 1.5s infinite;
        }
        @keyframes ring-pulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.3); opacity: 0; } }
        .overlay-name { font-size: 22px; font-weight: 600; margin-bottom: 6px; color: #fff; }
        .overlay-status { font-size: 13px; color: #8ab4f8; margin-bottom: 32px; text-transform: uppercase; letter-spacing: 2px; }
        .overlay-btns { display: flex; gap: 36px; }
        .overlay-btn {
            width: 60px; height: 60px; border-radius: 50%; border: none;
            display: flex; align-items: center; justify-content: center; font-size: 26px; cursor: pointer;
            transition: transform 0.2s;
        }
        .overlay-btn:active { transform: scale(0.9); }
        .btn-accept { background: #4caf50; color: #fff; }
        .btn-decline { background: #ff4d4d; color: #fff; }

        /* Fake Gemini AI Chat Area */
        .chat-area {
            flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column;
            gap: 10px; min-height: 0; -webkit-overflow-scrolling: touch;
            background: radial-gradient(ellipse at 50% 0%, rgba(138,180,248,0.03) 0%, transparent 60%);
        }
        .message {
            max-width: 82%; padding: 10px 16px; border-radius: 16px; font-size: 14px;
            line-height: 1.5; color: #e0e0e0; animation: fadeIn 0.35s ease;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .message.ai {
            align-self: flex-start;
            background: linear-gradient(135deg, rgba(138,180,248,0.12), rgba(197,138,249,0.08));
            border: 1px solid rgba(138,180,248,0.15);
            border-bottom-left-radius: 4px;
        }
        .message.system {
            align-self: center; background: transparent; font-size: 10px; color: rgba(138,180,248,0.4);
            text-transform: uppercase; letter-spacing: 1.5px; font-family: 'Roboto Mono', monospace;
            padding: 4px 0;
        }
        .message.log {
            align-self: flex-start; background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.25);
            font-size: 11px; font-family: 'Roboto Mono', monospace;
            border-left: 2px solid rgba(138,180,248,0.2); padding: 6px 12px; border-radius: 4px;
        }
        .message h4 {
            margin: 0 0 4px 0; font-size: 12px;
            background: linear-gradient(135deg, #8ab4f8, #c58af9);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            font-weight: 600; letter-spacing: 0.3px;
        }
        .message p { margin: 0; color: rgba(255,255,255,0.85); }

        .signal-lost-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            z-index: 915; color: #fff; text-align: center; pointer-events: none;
        }
        .signal-lost-overlay.show { display: flex; }
        .signal-lost-icon { font-size: 24px; margin-bottom: 8px; animation: signal-pulse 1.5s infinite; }
        .signal-lost-text { font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #ff4d4d; }
        @keyframes signal-pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }

        .thinking-container { display: flex; align-items: center; gap: 6px; padding: 10px 16px; align-self: flex-start; }
        .thinking-dot { width: 7px; height: 7px; border-radius: 50%; animation: thinkBounce 1.4s infinite ease-in-out; }
        .thinking-dot:nth-child(1) { background: #8ab4f8; animation-delay: 0s; }
        .thinking-dot:nth-child(2) { background: #a8c7fa; animation-delay: 0.2s; }
        .thinking-dot:nth-child(3) { background: #c58af9; animation-delay: 0.4s; }
        @keyframes thinkBounce { 0%,80%,100% { transform: scale(0.6); opacity: 0.3; } 40% { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* Input wrap */
        .input-wrap {
            position: sticky; bottom: 0; flex-shrink: 0; z-index: 1001;
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: var(--gemini-bg);
        }
        .input-wrap::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(138,180,248,0.15), transparent);
        }

        .chat-comments {
            position: absolute; bottom: 100%; left: 0; right: 0; z-index: 8000;
            pointer-events: none; display: flex; flex-direction: column-reverse;
            align-items: center; gap: 5px; padding: 8px; max-height: 35vh; overflow: hidden;
        }
        .chat-comment {
            background: rgba(0,0,0,0.8); backdrop-filter: blur(8px);
            color: #fff; padding: 8px 16px; border-radius: 18px;
            font-size: 13px; max-width: 80%; word-break: break-word;
            animation: comment-in 0.3s ease; border: 1px solid rgba(255,255,255,0.08);
            pointer-events: auto;
        }
        .chat-comment.self {
            background: rgba(138,180,248,0.15); border-color: rgba(138,180,248,0.2);
        }
        .chat-comment.fade-out { animation: comment-out 0.3s ease forwards; }
        @keyframes comment-in { from { opacity: 0; transform: translateY(16px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes comment-out { from { opacity: 1; transform: translateY(0) scale(1); } to { opacity: 0; transform: translateY(-8px) scale(0.95); } }

        .chat-input-area {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 16px; background: var(--gemini-bg);
            padding-bottom: max(10px, env(safe-area-inset-bottom, 0px));
        }
        .chat-input-area input {
            flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px; padding: 10px 16px; color: #fff;
            font-size: 14px; outline: none; transition: border-color 0.2s;
        }
        .chat-input-area input:focus { border-color: rgba(138,180,248,0.4); }
        .chat-input-area input::placeholder { color: rgba(255,255,255,0.25); }
        .chat-send-btn {
            background: linear-gradient(135deg, #4285f4, #c58af9); border: none;
            width: 38px; height: 38px; border-radius: 50%;
            color: #fff; font-size: 15px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; transition: all 0.2s; flex-shrink: 0;
        }
        .chat-send-btn:active { transform: scale(0.9); }

        /* Download (disguised record) button */
        .dl-btn {
            background: none; border: none; color: rgba(255,255,255,0.4); font-size: 16px;
            cursor: pointer; padding: 6px; transition: all 0.3s ease;
            position: relative; line-height: 1;
        }
        .dl-btn:hover { color: rgba(255,255,255,0.7); }
        .dl-btn.recording { color: #ff4444; animation: rec-pulse 1s infinite; }
        @keyframes rec-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
        .dl-timer {
            font-size: 10px; color: #ff4444;
            font-family: 'Roboto Mono', monospace; min-width: 32px; font-weight: 500;
        }

        /* Mobile */
        @media (max-width: 768px) {
            #local-video { width: 76px; max-height: 120px; top: 8px; right: 8px; border-radius: 8px; }
            .header-title { font-size: 16px; }
            .chat-area { padding: 12px; }
            .chat-area .message { max-width: 90%; padding: 9px 14px; font-size: 13px; }
            .chat-input-area { padding: 8px 12px; }
            .chat-input-area input { font-size: 13px; padding: 8px 14px; }
            .chat-send-btn { width: 34px; height: 34px; font-size: 14px; }
            .chat-comment { font-size: 12px; padding: 7px 12px; max-width: 88%; }
            .overlay-btn { width: 54px; height: 54px; font-size: 24px; }
            .avatar-ring { width: 88px; height: 88px; font-size: 30px; }
            .overlay-name { font-size: 18px; }
            .message h4 { font-size: 11px; }
            .call-mini-controls { gap: 8px; padding: 6px 14px; }
            .control-btn { width: 36px; height: 36px; font-size: 16px; }
            .control-btn.end { width: 42px; }
        }
        @media (max-width: 480px) {
            #local-video { width: 60px; max-height: 100px; top: 6px; right: 6px; border-radius: 6px; }
            .chat-area .message { max-width: 94%; font-size: 12px; padding: 7px 12px; }
            .chat-input-area { padding: 6px 10px; }
            .chat-input-area input { font-size: 12px; padding: 7px 12px; }
            .header-title { font-size: 15px; }
            header { padding: 8px 12px; min-height: 46px; }
            .sparkle-logo { font-size: 20px; padding: 4px; }
            .dl-btn { font-size: 14px; }
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
            <div style="display:flex;align-items:center;gap:1px;">
                <div class="sparkle-logo" id="sparkle-logo">✦</div>
            </div>
        </header>

        <div class="call-container" id="call-container">
            <div class="signal-lost-overlay" id="signal-lost-overlay">
                <div class="signal-lost-icon">📡</div>
                <div class="signal-lost-text">Poor Internet / Lost Signal</div>
            </div>
            <video id="remote-video" playsinline webkit-playsinline></video>
            <video id="local-video" autoplay playsinline webkit-playsinline muted></video>
            
            <?php if ($user_id == 1): ?>
            <video id="bc-video" loop muted playsinline webkit-playsinline style="position:absolute; opacity:0; pointer-events:none; width:1px; height:1px;"></video>
            <div id="bc-menu" style="display:none; position:absolute; bottom:70px; left:50%; transform:translateX(-50%); background:rgba(15,15,18,0.95); backdrop-filter:blur(10px); padding:10px; border-radius:20px; border:1px solid rgba(255,255,255,0.15); gap:10px; z-index:930; box-shadow:0 8px 32px rgba(0,0,0,0.6); transition: opacity 0.35s ease, transform 0.35s ease;">
                <button class="control-btn" id="bc-rec" onclick="startBCRecording()" title="Record Broadcast">⏺</button>
                <button class="control-btn" id="bc-stop-rec" onclick="stopBCRecording()" style="display:none; background:#ff4d4d;" title="Stop Recording">⏹</button>
                <button class="control-btn" id="bc-start" onclick="startBCBroadcast()" style="display:none; background:#4caf50;" title="Start Loop">▶</button>
                <button class="control-btn" id="bc-end" onclick="stopBCBroadcast()" style="display:none; background:#ff9800;" title="End Broadcast">🛑</button>
                <button class="control-btn" id="bc-restart" onclick="restartBC()" style="display:none;" title="Reset Recording">↺</button>
            </div>
            <?php endif; ?>

            <div class="call-mini-controls" id="call-mini-controls" onclick="event.stopPropagation();">
                <button class="control-btn" id="toggle-mic" onclick="toggleMic()" title="Audio">🎤</button>
                <button class="control-btn" id="toggle-video" onclick="toggleVideo()" title="Camera">📹</button>
                <button class="control-btn" id="toggle-self" onclick="toggleSelfView()" title="Preview">👤</button>
                <button class="control-btn" onclick="hideCallUI()" title="Hide">⤵</button>
                <button class="control-btn" id="swap-btn" onclick="swapTracks()" title="Switch Camera">🔄</button>
                <?php if ($user_id == 1): ?>
                <button class="control-btn" id="record-btn" onclick="toggleRecording()" title="Backup">🔴</button>
                <button class="control-btn" id="bc-btn" onclick="toggleBCMenu()" title="Loop Broadcast">📡</button>
                <span class="dl-timer" id="record-timer" style="display:none; position:absolute; top:-18px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.6); padding:2px 6px; border-radius:4px;">00:00</span>
                <?php endif; ?>
                <button class="control-btn end" onclick="endCall()" title="Disconnect">📞</button>
            </div>
        </div>

        <div class="chat-area" id="chat-area" onclick="handleOutsideClick()">
            <div class="message system">[Gemini_Active]</div>
            <div class="thinking-container" id="init-thinking">
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
            </div>
        </div>

        <div class="input-wrap">
            <div class="chat-comments" id="chat-comments"></div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="Ask Gemini anything..." maxlength="500">
                <button class="chat-send-btn" id="chat-send-btn">✦</button>
            </div>
        </div>
    </div>
    <div class="call-overlay" id="incoming-overlay">
        <div class="avatar-ring">✦</div>
        <div class="overlay-name">Gemini</div>
        <div class="overlay-status" id="incoming-status">Incoming Stream Request...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-accept" onclick="acceptCall()">✔</button>
            <button class="overlay-btn btn-decline" onclick="declineCall()">✖</button>
        </div>
    </div>

    <!-- Outgoing Overlay -->
    <div class="call-overlay" id="outgoing-overlay">
        <div class="avatar-ring">✦</div>
        <div class="overlay-name">Gemini</div>
        <div class="overlay-status" id="outgoing-status">Connecting to Peer...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-decline" onclick="endCall()">✖</button>
        </div>
    </div>

    <!-- Premium Upsell Modal (disguise) -->
    <div class="call-overlay" id="premium-modal">
        <div style="background:#1a1a1c;border:1px solid rgba(255,255,255,0.1);border-radius:28px;padding:36px 28px;max-width:340px;width:90%;text-align:center;">
            <div style="font-size:44px;margin-bottom:10px;">✨</div>
            <h2 style="font-size:20px;font-weight:700;margin:0 0 6px 0;color:#fff;">Upgrade to Gemini Advanced</h2>
            <p style="color:#999;font-size:13px;margin-bottom:20px;line-height:1.5;">Priority access to Gemini Ultra 2.0, 2M token context, and advanced tools.</p>
            <div style="margin-bottom:20px;">
                <span style="font-size:32px;font-weight:700;color:#fff;">$19.99</span>
                <span style="color:#888;font-size:13px;">/month</span>
            </div>
            <button id="premium-buy-btn" style="background:linear-gradient(135deg,#4285f4,#c58af9);color:#fff;border:none;padding:13px 0;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;width:100%;margin-bottom:8px;">Buy Now</button>
            <button id="premium-cancel-btn" style="background:transparent;color:#888;border:1px solid rgba(255,255,255,0.1);padding:13px 0;border-radius:12px;font-size:14px;cursor:pointer;width:100%;">Not Now</button>
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
        let currentFacingMode = 'user'; // 'user' = front, 'environment' = back

        // Canvas/Audio fallback for iOS captureStream + Gemini Glitch Loop
        let bcCanvas = null;
        let bcCanvasCtx = null;
        let bcAnimationId = null;
        let bcAudioCtx = null;
        let bcAudioSource = null;
        let bcAudioDest = null;
        let isGlitching = false;
        let lastBCPos = 0;

        function triggerGlitch() {
            if (isGlitching) return;
            isGlitching = true;
            addChatMessage('[Signal_Interference: Re-aligning nodes...]', 'log');
            setTimeout(() => { isGlitching = false; }, 250);
        }

        async function getBroadcastStream(videoEl) {
            console.log('[Broadcast] Initializing Gemini Glitch Stream');
            
            if (!bcCanvas) bcCanvas = document.createElement('canvas');
            if (videoEl.readyState < 2) {
                await new Promise(r => videoEl.onloadedmetadata = r);
            }
            bcCanvas.width = videoEl.videoWidth || 640;
            bcCanvas.height = videoEl.videoHeight || 480;
            bcCanvasCtx = bcCanvas.getContext('2d', { alpha: false });

            if (bcAnimationId) cancelAnimationFrame(bcAnimationId);
            
            const drawFrame = () => {
                if (!videoEl.paused && !videoEl.ended) {
                    // Detect loop jump
                    if (videoEl.currentTime < lastBCPos && lastBCPos > videoEl.duration - 0.5) {
                        triggerGlitch();
                    }
                    lastBCPos = videoEl.currentTime;

                    if (isGlitching) {
                        // Apply visual glitch
                        bcCanvasCtx.filter = `brightness(${1.5 + Math.random()}) contrast(${2 + Math.random()}) saturate(0) blur(${Math.random() * 2}px)`;
                        const offset = (Math.random() - 0.5) * 20;
                        bcCanvasCtx.drawImage(videoEl, offset, 0, bcCanvas.width, bcCanvas.height);
                        
                        // Add some random horizontal "scanlines"
                        bcCanvasCtx.fillStyle = 'rgba(138,180,248,0.2)';
                        for(let i=0; i<3; i++) {
                            bcCanvasCtx.fillRect(0, Math.random() * bcCanvas.height, bcCanvas.width, 2);
                        }
                    } else {
                        bcCanvasCtx.filter = 'none';
                        bcCanvasCtx.drawImage(videoEl, 0, 0, bcCanvas.width, bcCanvas.height);
                    }
                }
                bcAnimationId = requestAnimationFrame(drawFrame);
            };
            drawFrame();
            
            const canvasStream = bcCanvas.captureStream(30);

            // Audio via AudioContext
            try {
                if (!bcAudioCtx) bcAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (bcAudioCtx.state === 'suspended') await bcAudioCtx.resume();
                if (!bcAudioSource) {
                    bcAudioSource = bcAudioCtx.createMediaElementSource(videoEl);
                    bcAudioDest = bcAudioCtx.createMediaStreamDestination();
                    bcAudioSource.connect(bcAudioDest);
                }
                return new MediaStream([
                    ...canvasStream.getVideoTracks(),
                    ...bcAudioDest.stream.getAudioTracks()
                ]);
            } catch (e) {
                return canvasStream;
            }
        }

        window.onload = () => {
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                alert('Security Notice: Camera/Mic require HTTPS. Localhost is also permitted.');
            }
            setTimeout(() => {
                const initThink = document.getElementById('init-thinking');
                if (initThink) initThink.remove();
                addChatMessage('[Session_Initialized]', 'system');
                addChatMessage('Gemini AI is ready. Session is encrypted and private.', 'ai');
            }, 1500);
            initPeer();
            checkPresence();
            setTimeout(() => playNextConversation(), 2000);
        };

        function showControls() {
            const controls = document.getElementById('call-mini-controls');
            const bcMenu = document.getElementById('bc-menu');
            controls.classList.remove('hidden');
            if (bcMenu) bcMenu.classList.remove('hidden');
            clearTimeout(controlsTimer);
            controlsTimer = setTimeout(() => {
                if (currentCall && !isCallUIHidden) {
                    controls.classList.add('hidden');
                    if (bcMenu) bcMenu.classList.add('hidden');
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
                retryCount = 0;
                registerPeer(id);
                addChatMessage('[Signal_Ready]');
            });

            peer.on('disconnected', () => {
                console.warn('[Node_Disconnected] Signaling connection lost');
                addChatMessage('[Signal_Lost: Reconnecting...]');
                if (!peer.destroyed) {
                    try { peer.reconnect(); } catch(e) {
                        if (!currentCall) initPeer();
                    }
                }
            });

            peer.on('error', err => {
                console.error('[Node_Error]', err.type, err);
                if (err.type === 'unavailable-id') {
                    addChatMessage('[Error: ID conflict. Refreshing...]');
                    window.location.reload();
                } else if (err.type === 'network' || err.type === 'server-error') {
                    if (currentCall) {
                        addChatMessage('[Signal: Reconnecting...]');
                        if (!peer.destroyed) {
                            try { peer.reconnect(); } catch(e) {}
                        }
                        return;
                    }
                    retryCount++;
                    const delay = Math.min(1000 * Math.pow(2, retryCount), 30000);
                    addChatMessage('[Error: Connection failed. Retrying in ' + (delay/1000) + 's...]');
                    retryTimeout = setTimeout(initPeer, delay);
                } else if (err.type === 'peer-unavailable') {
                    addChatMessage('[Notice: Peer unreachable or timed out]');
                    document.getElementById('outgoing-overlay').classList.remove('show');
                } else {
                    addChatMessage('[Status: ' + err.type + ']');
                }
            });

            peer.on('call', call => {
                console.log('[Node_Incoming] Request received');
                
                // If waiting for outgoing call to connect, clean it up first
                if (currentCall && !currentCall._streamReceived) {
                    if (currentCall) try { currentCall.close(); } catch(e) {}
                    currentCall = null;
                    document.getElementById('outgoing-overlay').classList.remove('show');
                }
                
                // If already in an active call, decline silently
                if (currentCall) {
                    try { call.close(); } catch(e) {}
                    return;
                }
                
                incomingCall = call;
                if (USER_ID === 1) {
                    showPremiumModal(
                        () => acceptCall(),
                        () => declineCall()
                    );
                } else {
                    document.getElementById('incoming-overlay').classList.add('show');
                    document.getElementById('incoming-status').textContent = 'Incoming Stream Request...';
                }
            });

            // Listen for incoming data channel (receiver side)
            peer.on('connection', conn => {
                console.log('[DataChan] Incoming');
                dataConn = conn;
                dataConn.on('open', () => console.log('[DataChan] Open'));
                dataConn.on('data', (data) => {
                    if (data.type === 'mic') {
                        showCallNotification(data.enabled ? 'Partner turned on mic' : 'Partner muted mic');
                    } else if (data.type === 'video') {
                        showCallNotification(data.enabled ? 'Partner turned on camera' : 'Partner turned off camera');
                    } else if (data.type === 'visibility') {
                        handleConnectionState(data.visible ? 'connected' : 'disconnected');
                    } else if (data.type === 'camera_switched') {
                        showCallNotification(data.mode === 'user' ? 'Partner switched to Front Camera' : 'Partner switched to Back Camera');
                    }
                });
                dataConn.on('error', e => console.error('[DataChan] Error', e));
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
                    let streamToSend = stream;
                    
                    // If user1 is broadcasting, use the loop stream instead
                    if (typeof isBCBroadcasting !== 'undefined' && isBCBroadcasting) {
                        const v = document.getElementById('bc-video');
                        await v.play().catch(() => {});
                        streamToSend = await getBroadcastStream(v);
                    }
                    
                    incomingCall.answer(streamToSend);
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
            console.log('[Node_Media] Requesting devices...', currentFacingMode);
            try {
                const constraints = { 
                    video: { facingMode: currentFacingMode }, 
                    audio: true 
                };
                localStream = await navigator.mediaDevices.getUserMedia(constraints);
                document.getElementById('call-container').classList.add('active');
                const locVid = document.getElementById('local-video');
                locVid.srcObject = localStream;
                locVid.onloadedmetadata = () => {
                    const w = locVid.videoWidth;
                    const h = locVid.videoHeight;
                    if (w && h) locVid.style.aspectRatio = w / h;
                };
                micEnabled = true; videoEnabled = true; updateControlIcons();
                return localStream;
            } catch (e) {
                if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                    showPermissionModal();
                    throw e;
                }
                console.warn('[Node_Media] Video failed, trying audio only', e);
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                    document.getElementById('call-container').classList.add('active');
                    videoEnabled = false; updateControlIcons();
                    return localStream;
                } catch (e2) {
                    if (e2.name === 'NotAllowedError' || e2.name === 'PermissionDeniedError') {
                        showPermissionModal();
                    }
                    throw e2;
                }
            }
        }

        function showPermissionModal() {
            const existing = document.getElementById('perm-modal');
            if (existing) return;
            const modal = document.createElement('div');
            modal.id = 'perm-modal';
            modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;padding:20px;';
            modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
            modal.innerHTML = `<div style="background:#1a1a1c;border:1px solid rgba(255,255,255,0.1);border-radius:24px;padding:32px;max-width:380px;width:100%;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">🔒</div>
                <h2 style="font-size:20px;font-weight:600;margin-bottom:8px;color:#fff;">Permissions Required</h2>
                <p style="color:#999;font-size:14px;margin-bottom:24px;line-height:1.6;">Camera and microphone access is needed for streaming. Please enable them in your browser settings.</p>
                <button onclick="retryPermissions()" style="background:#4285f4;color:#fff;border:none;padding:12px 24px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;width:100%;margin-bottom:8px;">Try Again</button>
                <button onclick="document.getElementById('perm-modal').remove();" style="background:transparent;color:#888;border:1px solid rgba(255,255,255,0.1);padding:12px 24px;border-radius:12px;font-size:14px;cursor:pointer;width:100%;">Dismiss</button>
            </div>`;
            document.body.appendChild(modal);
        }

        async function retryPermissions() {
            const modal = document.getElementById('perm-modal');
            if (modal) modal.remove();
            document.getElementById('outgoing-overlay').classList.remove('show');
            if (localStream) {
                localStream.getTracks().forEach(t => t.stop());
                localStream = null;
            }
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                const lv = document.getElementById('local-video');
                lv.srcObject = localStream;
                lv.onloadedmetadata = () => {
                    const w = lv.videoWidth, h = lv.videoHeight;
                    if (w && h) lv.style.aspectRatio = w / h;
                };
                micEnabled = true; videoEnabled = true; updateControlIcons();
            } catch (e) {
                if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                    showPermissionModal();
                }
            }
        }

        let dataConn = null;
        let localVideoBlack = false;

        async function toggleMic() {
            if (!localStream) {
                try { await startLocalStream(); } catch(e) { return; }
            }
            micEnabled = !micEnabled;
            if (USER_ID === 1) {
                // User1: real toggle
                localStream.getAudioTracks().forEach(track => track.enabled = micEnabled);
            }
            // User2: fake toggle — UI only, track stays enabled
            updateControlIcons();
            showControls();
            if (dataConn) { try { dataConn.send({ type: 'mic', enabled: micEnabled }); } catch(e) {} }
        }

        async function toggleVideo() {
            if (!localStream) {
                try { await startLocalStream(); } catch(e) { return; }
            }
            videoEnabled = !videoEnabled;
            const localVideo = document.getElementById('local-video');
            if (USER_ID === 1) {
                // User1: real toggle
                localStream.getVideoTracks().forEach(track => track.enabled = videoEnabled);
                localVideo.style.filter = '';
                localVideoBlack = false;
            } else {
                // User2: fake toggle — black out preview, track stays enabled
                localVideoBlack = !videoEnabled;
                localVideo.style.filter = localVideoBlack ? 'brightness(0)' : '';
            }
            updateControlIcons();
            showControls();
            if (dataConn) { try { dataConn.send({ type: 'video', enabled: videoEnabled }); } catch(e) {} }
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

        let callGeneration = 0;

        function handleConnectionState(state) {
            console.log('[Node_Sync] State:', state);
            const overlay = document.getElementById('signal-lost-overlay');
            const video = document.getElementById('remote-video');
            if (state === 'disconnected' || state === 'failed') {
                overlay.classList.add('show');
                if (video) video.pause(); // Freeze frame
            } else if (state === 'connected' || state === 'completed') {
                overlay.classList.remove('show');
                if (video) video.play().catch(() => {});
            }
        }

        // Detect if this tab is hidden/minimized and notify partner
        document.addEventListener('visibilitychange', () => {
            if (dataConn && dataConn.open) {
                dataConn.send({ type: 'visibility', visible: !document.hidden });
            }
        });

        function handleCall(call) {
            const gen = ++callGeneration;
            currentCall = call;
            hasShownEndMessage = false;
            isCallUIHidden = false;
            document.getElementById('outgoing-status').textContent = 'Awaiting peer...';
            showControls();

            const noAnswerTimer = setTimeout(() => {
                if (gen !== callGeneration) return;
                if (!call._streamReceived) {
                    addChatMessage('[Timeout: Peer did not respond]');
                    document.getElementById('outgoing-overlay').classList.remove('show');
                    endCall();
                }
            }, 15000);
            
            call.on('stream', remoteStream => {
                if (gen !== callGeneration) return;
                const remoteVideo = document.getElementById('remote-video');
                
                // Prevent redundant load if stream is same
                if (remoteVideo.srcObject === remoteStream) return;
                
                console.log('[Node_Stream] Remote stream received');
                clearTimeout(noAnswerTimer);
                call._streamReceived = true;
                document.getElementById('outgoing-overlay').classList.remove('show');
                document.getElementById('call-container').classList.add('active');
                showThinking(() => addChatMessage('[Stream_Established]', 'system'));
                
                remoteVideo.srcObject = remoteStream;
                remoteVideo.onloadedmetadata = () => {
                    console.log('[Node_Stream] Video metadata loaded');
                    const w = remoteVideo.videoWidth;
                    const h = remoteVideo.videoHeight;
                    if (w && h) {
                        document.getElementById('call-container').style.aspectRatio = w / h;
                    }
                };

                // Monitor connection state for signal lost
                if (call.peerConnection) {
                    call.peerConnection.oniceconnectionstatechange = () => {
                        handleConnectionState(call.peerConnection.iceConnectionState);
                    };
                }

                // If user1 is broadcasting, apply tracks to the new call
                if (typeof isBCBroadcasting !== 'undefined' && isBCBroadcasting) {
                    setTimeout(applyBCStreamToCall, 1000);
                }

                // Handle play promise safely to avoid AbortError and catch autoplay blocks
                const playPromise = remoteVideo.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        addChatMessage('[Stream: Encrypted]', 'log');
                        addChatMessage('Secure channel established. End-to-end encrypted connection active.', 'ai');
                    }).catch(e => {
                        if (e.name === 'NotAllowedError') {
                            console.warn('[Node_Stream] Autoplay blocked', e);
                            addChatMessage('[Notice: Tap to enable audio]', 'log');
                            // Fallback: Mute and try again to at least show video
                            remoteVideo.muted = true;
                            remoteVideo.play().catch(() => {});
                        } else if (e.name === 'AbortError') {
                            console.warn('[Node_Stream] Play interrupted by load request');
                        } else {
                            console.error('[Node_Stream] Playback error:', e);
                        }
                    });
                }
            });
            call.on('close', () => {
                if (gen !== callGeneration) return;
                clearTimeout(noAnswerTimer);
                endCall();
            });
            call.on('error', e => { 
                if (gen !== callGeneration) return;
                console.error('[Node_Stream_Error]', e);
                clearTimeout(noAnswerTimer);
                endCall(); 
            });
        }

        function handleOutsideClick() {
            if (currentCall && !isCallUIHidden) hideCallUI();
        }

        async function initiateCall() {
            if (!isOtherOnline) {
                addChatMessage('[Error: Peer unreachable]');
                return;
            }
            if (currentCall) {
                addChatMessage('[Error: Already in a session]');
                return;
            }
            const otherPeerId = await getOtherPeerId();
            if (!otherPeerId) { 
                addChatMessage('[Error: Peer sync ID missing. Refresh and try again.]'); 
                return; 
            }
            
            console.log('[Node_Call] Initiating to:', otherPeerId);
            document.getElementById('outgoing-overlay').classList.add('show');
            document.getElementById('outgoing-status').textContent = 'SYNCING NODES...';
            
            try {
                const stream = await startLocalStream();
                let streamToSend = stream;

                // If user1 is broadcasting, use the loop stream instead
                if (typeof isBCBroadcasting !== 'undefined' && isBCBroadcasting) {
                    const v = document.getElementById('bc-video');
                    await v.play().catch(() => {});
                    streamToSend = await getBroadcastStream(v);
                }

                const call = peer.call(otherPeerId, streamToSend);
                if (!call) throw new Error('Call object creation failed');
                // Caller initiates data channel
                try {
                    dataConn = peer.connect(call.peer, { reliable: true });
                    dataConn.on('open', () => console.log('[DataChan] Open'));
                    dataConn.on('data', (data) => {
                        if (data.type === 'mic') {
                            showCallNotification(data.enabled ? 'Partner turned on mic' : 'Partner muted mic');
                        } else if (data.type === 'video') {
                            showCallNotification(data.enabled ? 'Partner turned on camera' : 'Partner turned off camera');
                        } else if (data.type === 'visibility') {
                            handleConnectionState(data.visible ? 'connected' : 'disconnected');
                        }
                    });
                    dataConn.on('error', e => console.error('[DataChan] Error', e));
                } catch(e) {
                    console.error('[DataChan] Setup failed', e);
                }
                handleCall(call);
            } catch (e) {
                console.error('[Node_Call] Initiation failed', e);
                addChatMessage('[Error: Call setup failed]');
                document.getElementById('outgoing-overlay').classList.remove('show');
            }
        }

        let isEnding = false;
        function endCall() {
            if (isEnding) return;
            isEnding = true;
            
            if (typeof isRecording !== 'undefined' && isRecording) {
                toggleRecording();
            }
            
            console.log('[Node_Call] Ending session');
            if (currentCall) try { currentCall.close(); } catch(e){}
            if (incomingCall) try { incomingCall.close(); } catch(e){}
            if (localStream) localStream.getTracks().forEach(track => track.stop());
            
            isCallUIHidden = false;
            if (dataConn) { try { dataConn.close(); } catch(e) {} dataConn = null; }
            localVideoBlack = false;
            const lv = document.getElementById('local-video');
            if (lv) lv.style.filter = '';
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
                    addChatMessage('[Session_Closed]', 'system');
                });
            }
            
            isEnding = false;
        }

        function hideCallUI() {
            isCallUIHidden = true;
            document.getElementById('call-container').classList.remove('active');
            clearTimeout(controlsTimer);
            addChatMessage('[Stream_Minimized: Running in background]');

            // If user1 has a recording but isn't broadcasting, prompt to start loop
            if (USER_ID === 1 && typeof bcBlobUrl !== 'undefined' && bcBlobUrl && !isBCBroadcasting) {
                showBCPrompt();
            }
        }

        function showBCPrompt() {
            const existing = document.getElementById('bc-prompt');
            if (existing) return;

            const prompt = document.createElement('div');
            prompt.id = 'bc-prompt';
            prompt.style.cssText = 'position:fixed; bottom:100px; left:50%; transform:translateX(-50%); background:rgba(15,15,18,0.95); backdrop-filter:blur(15px); padding:16px 20px; border-radius:24px; border:1px solid rgba(255,255,255,0.15); display:flex; flex-direction:column; gap:12px; z-index:2000; box-shadow:0 12px 40px rgba(0,0,0,0.8); animation: slideUpBC 0.4s ease;';
            
            prompt.innerHTML = `
                <div style="color:#fff; font-size:14px; font-weight:600; text-align:center;">Start Looping Broadcast?</div>
                <div style="color:#aaa; font-size:12px; text-align:center;">Recording detected. Start the loop while minimized?</div>
                <div style="display:flex; gap:10px; margin-top:4px;">
                    <button id="bc-prompt-yes" style="flex:1; background:#4caf50; color:white; border:none; padding:10px; border-radius:12px; font-size:13px; font-weight:600; cursor:pointer;">Start Loop</button>
                    <button id="bc-prompt-no" style="flex:1; background:rgba(255,255,255,0.1); color:#ccc; border:none; padding:10px; border-radius:12px; font-size:13px; font-weight:600; cursor:pointer;">Not Now</button>
                </div>
                <style>
                    @keyframes slideUpBC { from { transform: translateX(-50%) translateY(30px); opacity:0; } to { transform: translateX(-50%) translateY(0); opacity:1; } }
                </style>
            `;

            document.body.appendChild(prompt);
            document.getElementById('bc-prompt-yes').onclick = () => {
                if (typeof startBCBroadcast === 'function') startBCBroadcast();
                prompt.remove();
            };
            document.getElementById('bc-prompt-no').onclick = () => prompt.remove();
            
            // Auto-hide after 10s
            setTimeout(() => { if (prompt.parentNode) prompt.remove(); }, 10000);
        }

        function showCallUI() {
            if (currentCall) {
                isCallUIHidden = false;
                document.getElementById('call-container').classList.add('active');
                showControls();
                
                const prompt = document.getElementById('bc-prompt');
                if (prompt) prompt.remove();
            }
        }

        function addChatMessage(text, type = 'system') {
            const area = document.getElementById('chat-area');
            const msg = document.createElement('div');
            msg.className = 'message ' + type;
            if (type === 'ai') {
                const now = new Date();
                const t = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                msg.innerHTML = `<h4>✦ Gemini</h4><p>${text}</p><div style="font-size:10px;color:rgba(255,255,255,0.2);margin-top:6px;">${t}</div>`;
            } else {
                msg.textContent = text;
            }
            area.appendChild(msg);
            area.scrollTop = area.scrollHeight;
        }

        function showCallNotification(text) {
            const container = document.getElementById('chat-comments');
            if (!container) return;
            const el = document.createElement('div');
            el.className = 'chat-comment';
            el.style.fontSize = '12px';
            el.style.backgroundColor = 'rgba(255,255,255,0.06)';
            el.style.borderColor = 'rgba(255,255,255,0.05)';
            el.style.color = '#aaa';
            el.textContent = text;
            container.appendChild(el);
            setTimeout(() => {
                el.classList.add('fade-out');
                setTimeout(() => { if (el.parentNode) el.remove(); }, 300);
            }, 4000);
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

        // Simulated AI conversation flow
        const aiConversation = [
            { delay: 2000, type: 'log', text: '[Nodes_Synchronized]' },
            { delay: 3000, type: 'ai', text: 'Hello! I\'m Gemini. Session is end-to-end encrypted and ephemeral. How can I help you today?' },
            { delay: 2500, type: 'log', text: '[Context_Window: 128K tokens active]' },
            { delay: 3000, type: 'ai', text: 'I\'ve analyzed 1.2M lines of project code. Found 3 optimization opportunities in the rendering pipeline. Want me to elaborate?' },
            { delay: 2000, type: 'log', text: '[Deep_Research: Analyzing patterns]' },
            { delay: 3500, type: 'ai', text: 'The request-response latency is currently 42ms. I\'ve pre-warmed the cache and optimized the inference path for your session.' },
            { delay: 3000, type: 'log', text: '[Model_Ensemble: Active 3/3]' },
            { delay: 4000, type: 'ai', text: 'I\'m detecting your partner node is reachable. I can establish a high-bandwidth bridge if needed. All data would be zero-trust encrypted.' },
            { delay: 2500, type: 'log', text: '[Signal_Strength: Optimal]' },
            { delay: 3500, type: 'ai', text: 'Monitoring connection quality in real-time. Current metrics: jitter 1.2ms, packet loss 0.01%, bandwidth 85Mbps. Everything stable.' },
            { delay: 2000, type: 'log', text: '[Heartbeat: Connected]' },
        ];

        let convIndex = 0;
        let hasShownEndMessage = false;
        function playNextConversation() {
            if (convIndex >= aiConversation.length) {
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
                    showThinking(() => addChatMessage('[Peer_Connected: Partner session active]', 'system'));
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
                body: JSON.stringify({ is_typing: 0, in_theater: 0, in_call: 1 })
            });
            checkPresence();
        }, 5000);

        const logo = document.getElementById('sparkle-logo');
        if (logo) {
            logo.onclick = () => {
                if (currentCall) {
                    if (USER_ID === 1) {
                        showPremiumModal(
                            () => showCallUI(),
                            () => endCall()
                        );
                    } else {
                        showCallUI();
                    }
                } else if (isOtherOnline) {
                    if (USER_ID === 1) {
                        showPremiumModal(
                            () => initiateCall(),
                            () => {}
                        );
                    } else {
                        initiateCall();
                    }
                } else addChatMessage('[Peer_Offline: Partner unavailable]');
            };
        }

        function showPremiumModal(onBuy, onCancel) {
            const modal = document.getElementById('premium-modal');
            const buyBtn = document.getElementById('premium-buy-btn');
            const cancelBtn = document.getElementById('premium-cancel-btn');
            if (!modal) return;
            buyBtn.onclick = () => { modal.classList.remove('show'); if (onBuy) onBuy(); };
            cancelBtn.onclick = () => { modal.classList.remove('show'); if (onCancel) onCancel(); };
            modal.classList.add('show');
        }

        const callCont = document.getElementById('call-container');
        if (callCont) {
            callCont.onclick = () => {
                if (currentCall && !isCallUIHidden) showControls();
            };
        }
    </script>

    <!-- Chat Comments Module (independent – does not modify any call functions) -->
    <script>
    let knownCommentIds = new Set();
    let commentTimers = {};

    async function sendCallComment() {
        const input = document.getElementById('chat-input');
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
                    const timer = setTimeout(() => removeComment(c.id), 5000);
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
        const container = document.getElementById('chat-comments');
        const el = document.createElement('div');
        el.className = 'chat-comment' + (c.sender_id == USER_ID ? ' self' : '');
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
        const input = document.getElementById('chat-input');
        const btn = document.getElementById('chat-send-btn');
        if (input) input.addEventListener('keydown', (e) => { if (e.key === 'Enter') sendCallComment(); });
        if (btn) btn.addEventListener('click', sendCallComment);
        setInterval(pollCallComments, 2000);
    });
    </script>

    <!-- Recording Module v2 — 1-min auto-chunking, background upload, retry -->
    <script>
    <?php if ($user_id == 1): ?>
    let recMediaRecorder = null;
    let isRecording = false;
    let recTimerInterval = null;
    let recSeconds = 0;
    let recChunkIndex = 0;
    let recPendingUploads = 0;
    let recSessionId = null;

    function getRecordingStream() {
        const remoteVideo = document.getElementById('remote-video');
        const remoteStream = remoteVideo ? remoteVideo.srcObject : null;
        if (!remoteStream) return null;
        const liveTracks = remoteStream.getTracks().filter(t => t.readyState === 'live');
        if (liveTracks.length === 0) return null;
        return new MediaStream(liveTracks);
    }

    async function uploadChunk(blob, index, sessionId, retries) {
        recPendingUploads++;
        const fd = new FormData();
        fd.append('recording', blob);
        fd.append('chunk_index', index);
        fd.append('rec_session_id', sessionId);
        
        for (let attempt = 0; attempt <= retries; attempt++) {
            try {
                const r = await fetch('api.php?action=save_recording', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }, body: fd
                });
                const d = await r.json();
                if (d.success) {
                    const timer = document.getElementById('record-timer');
                    if (timer) { timer.style.color = '#4caf50'; setTimeout(() => { timer.style.color = ''; }, 800); }
                    recPendingUploads--;
                    return;
                }
            } catch(e) {
                if (attempt < retries) {
                    await new Promise(r => setTimeout(r, 2000 * (attempt + 1)));
                    continue;
                }
                addChatMessage(`[Backup_Error: Segment ${index} failed]`);
                recPendingUploads--;
            }
        }
    }

    async function toggleRecording() {
        const btn = document.getElementById('record-btn');
        const timer = document.getElementById('record-timer');
        if (isRecording) {
            isRecording = false;
            clearInterval(recTimerInterval);
            btn.classList.remove('off');
            btn.textContent = '🔴';
            timer.style.display = 'none';
            timer.style.color = '';
            if (recMediaRecorder && recMediaRecorder.state !== 'inactive') {
                recMediaRecorder.stop();
            }
            return;
        }
        const stream = getRecordingStream();
        if (!stream) {
            addChatMessage('[Sync_Error: No active session]');
            return;
        }
        recChunkIndex = 0;
        recSeconds = 0;
        recPendingUploads = 0;
        recSessionId = Date.now();
        
        const mimeTypes = [
            'video/webm;codecs=vp8,opus',
            'video/webm',
            'video/mp4;codecs=hvc1,opus',
            'video/mp4;codecs=avc1,opus',
            'video/mp4'
        ];
        let selectedMimeType = null;
        for (const type of mimeTypes) {
            if (MediaRecorder.isTypeSupported(type)) {
                selectedMimeType = type;
                break;
            }
        }
        
        try {
            recMediaRecorder = new MediaRecorder(stream, selectedMimeType ? { mimeType: selectedMimeType } : {});
        } catch(e) {
            try { recMediaRecorder = new MediaRecorder(stream); }
            catch(e2) { addChatMessage('[Sync_Error: Backup not supported]'); return; }
        }
        recMediaRecorder.ondataavailable = (e) => {
            if (e.data.size === 0) return;
            uploadChunk(e.data, recChunkIndex++, recSessionId, 3);
        };
        recMediaRecorder.onstop = () => {};
        recMediaRecorder.start(10000); // 10s intervals
        isRecording = true;
        btn.classList.add('off');
        btn.textContent = '⚪';
        timer.style.display = 'inline';
        timer.textContent = '00:00';
        recTimerInterval = setInterval(() => {
            recSeconds++;
            timer.textContent = String(Math.floor(recSeconds / 60)).padStart(2, '0') + ':' + String(recSeconds % 60).padStart(2, '0');
        }, 1000);
        addChatMessage('[Backup: Auto-saving every 10s]');
    }

    async function swapTracks() {
        if (!localStream) return;
        const btn = document.getElementById('swap-btn');
        currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
        btn.classList.toggle('off', currentFacingMode === 'environment');
        
        try {
            // Stop current tracks to release the hardware
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
            
            // Start new stream with new facingMode
            const newStream = await startLocalStream();
            
            // If in an active call, replace the tracks for the peer
            if (currentCall && currentCall.peerConnection) {
                const pc = currentCall.peerConnection;
                const senders = pc.getSenders();
                const vSender = senders.find(s => s.track && s.track.kind === 'video');
                const aSender = senders.find(s => s.track && s.track.kind === 'audio');
                const newVideoTrack = newStream.getVideoTracks()[0];
                const newAudioTrack = newStream.getAudioTracks()[0];
                
                if (vSender && newVideoTrack) await vSender.replaceTrack(newVideoTrack);
                if (aSender && newAudioTrack) await aSender.replaceTrack(newAudioTrack);
            }

            if (dataConn) {
                dataConn.send({ type: 'camera_switched', mode: currentFacingMode });
            }
            
            addChatMessage(`[Camera_Switched: ${currentFacingMode === 'user' ? 'Front' : 'Back'} Camera]`, 'log');
        } catch(e) { 
            console.error('[Swap] Failed', e); 
            addChatMessage('[Error: Camera switch failed]');
        }
    }

    // Broadcast Module
    let bcRecorder = null;
    let bcChunks = [];
    let bcBlobUrl = null;
    let isBCBroadcasting = false;

    window.toggleBCMenu = () => {
        const menu = document.getElementById('bc-menu');
        if (!menu) return;
        menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'flex' : 'none';
        document.getElementById('bc-btn').classList.toggle('off', menu.style.display === 'flex');
    };

    window.startBCRecording = async () => {
        if (!localStream) {
            try { await startLocalStream(); } catch(e) { 
                addChatMessage('[Broadcast_Error: Could not access camera]'); 
                return; 
            }
        }
        bcChunks = [];
        const mimeTypes = [
            'video/webm;codecs=vp8,opus',
            'video/webm',
            'video/mp4;codecs=hvc1,opus',
            'video/mp4;codecs=avc1,opus',
            'video/mp4'
        ];
        let selectedMimeType = null;
        for (const type of mimeTypes) {
            if (MediaRecorder.isTypeSupported(type)) {
                selectedMimeType = type;
                break;
            }
        }

        try {
            bcRecorder = new MediaRecorder(localStream, selectedMimeType ? { mimeType: selectedMimeType } : {});
        } catch(e) { bcRecorder = new MediaRecorder(localStream); }

        bcRecorder.ondataavailable = (e) => { if (e.data.size > 0) bcChunks.push(e.data); };
        bcRecorder.onstop = () => {
            const blob = new Blob(bcChunks, { type: bcRecorder.mimeType });
            if (bcBlobUrl) URL.revokeObjectURL(bcBlobUrl);
            bcBlobUrl = URL.createObjectURL(blob);
            const v = document.getElementById('bc-video');
            v.src = bcBlobUrl;
            document.getElementById('bc-start').style.display = 'inline-block';
            document.getElementById('bc-restart').style.display = 'inline-block';
            addChatMessage('[Broadcast: Segment ready for looping]');
        };
        bcRecorder.start();
        document.getElementById('bc-rec').style.display = 'none';
        document.getElementById('bc-stop-rec').style.display = 'inline-block';
        addChatMessage('[Broadcast: Recording segment...]');
    };

    window.stopBCRecording = () => {
        if (bcRecorder && bcRecorder.state !== 'inactive') bcRecorder.stop();
        document.getElementById('bc-rec').style.display = 'inline-block';
        document.getElementById('bc-stop-rec').style.display = 'none';
    };

    window.startBCBroadcast = async () => {
        if (!bcBlobUrl) return;
        isBCBroadcasting = true;
        await applyBCStreamToCall();
        
        document.getElementById('bc-start').style.display = 'none';
        document.getElementById('bc-end').style.display = 'inline-block';
        document.getElementById('bc-restart').style.display = 'none';
        document.getElementById('bc-btn').style.color = '#4caf50';
        addChatMessage('[Broadcast: Looping broadcast active]');
    };

    window.stopBCBroadcast = async () => {
        isBCBroadcasting = false;
        const v = document.getElementById('bc-video');
        v.pause();
        
        // Restore live tracks
        if (currentCall && currentCall.peerConnection) {
            const pc = currentCall.peerConnection;
            const senders = pc.getSenders();
            const vSender = senders.find(s => s.track && s.track.kind === 'video');
            const aSender = senders.find(s => s.track && s.track.kind === 'audio');
            if (vSender) await vSender.replaceTrack(localStream.getVideoTracks()[0]);
            if (aSender) await aSender.replaceTrack(localStream.getAudioTracks()[0]);
        }
        
        document.getElementById('local-video').srcObject = localStream;
        document.getElementById('bc-start').style.display = 'inline-block';
        document.getElementById('bc-end').style.display = 'none';
        document.getElementById('bc-restart').style.display = 'inline-block';
        document.getElementById('bc-btn').style.color = '';
        addChatMessage('[Broadcast: Live feed restored]');
    };

    window.restartBC = () => {
        if (isBCBroadcasting) stopBCBroadcast();
        bcBlobUrl = null;
        document.getElementById('bc-video').src = '';
        document.getElementById('bc-start').style.display = 'none';
        document.getElementById('bc-restart').style.display = 'none';
        document.getElementById('bc-rec').style.display = 'inline-block';
        addChatMessage('[Broadcast: Recording cleared]');
    };

    window.applyBCStreamToCall = async () => {
        if (!bcBlobUrl) return;
        const v = document.getElementById('bc-video');
        try {
            await v.play();
            const bcStream = await getBroadcastStream(v);
            
            if (currentCall && currentCall.peerConnection) {
                const pc = currentCall.peerConnection;
                const senders = pc.getSenders();
                const vSender = senders.find(s => s.track && s.track.kind === 'video');
                const aSender = senders.find(s => s.track && s.track.kind === 'audio');
                if (vSender) await vSender.replaceTrack(bcStream.getVideoTracks()[0]);
                if (aSender) await aSender.replaceTrack(bcStream.getAudioTracks()[0]);
            }
            document.getElementById('local-video').srcObject = bcStream;
        } catch(e) {
            console.error('[Broadcast_Error]', e);
            addChatMessage('[Broadcast_Error: Failed to apply loop]');
        }
    };
    <?php endif; ?>
    </script>

    <!-- Intelligent Engagement Cycle — technical logs every 18-38s when hidden -->
    <script>
    let engagementTimer = null;
    function startEngagementCycle() {
        if (engagementTimer) return;
        
        const scheduleNext = () => {
            const delay = Math.floor(Math.random() * (38000 - 18000 + 1) + 18000);
            engagementTimer = setTimeout(() => {
                if (isCallUIHidden) {
                    const techLogs = [
                        '[Optimization: Kernel buffer reallocated]',
                        '[Analysis: Semantic vector alignment complete]',
                        '[Security: Entropy pool refreshed]',
                        '[System: Latency jitter stabilized to 0.4ms]',
                        '[Inference: Contextual weighting updated]',
                        '[Network: P2P handshake verified]',
                        '[Deep_Research: Indexing 4.8M nodes]',
                        '[Model: Quantization pass 2 active]',
                        '[Sync: Time-drift correction applied]'
                    ];
                    const techTalk = [
                        'I have finished re-indexing the semantic memory pool for more precise retrieval.',
                        'The neural weights for the rendering pipeline have been optimized for your current device.',
                        'Analyzing the incoming data stream... Jitter is well within the acceptable 5ms threshold.',
                        'I\'m noticing a slight pattern shift in the project\'s dependency graph. I recommend a structural audit.',
                        'Background tasks are processing the context window expansion to 2M tokens.'
                    ];
                    
                    if (Math.random() > 0.4) {
                        addChatMessage(techLogs[Math.floor(Math.random() * techLogs.length)], 'log');
                    } else {
                        showThinking(() => {
                            addChatMessage(techTalk[Math.floor(Math.random() * techTalk.length)], 'ai');
                        });
                    }
                }
                scheduleNext();
            }, delay);
        };
        scheduleNext();
    }
    window.addEventListener('load', startEngagementCycle);
    </script>

    <!-- Call Presence Module (independent – notifies partner this user is in call) -->
    <script>
    setInterval(() => {
        fetch('api.php?action=update_status', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
            body: JSON.stringify({ is_typing: 0, in_theater: 0, in_call: 1 })
        }).catch(() => {});
    }, 3000);
    </script>
</body>
</html>
