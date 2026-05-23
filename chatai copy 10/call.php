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
    <title id="page-title">Gemini AI</title>
    <link rel="icon" id="page-favicon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <script src="https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js"></script>
    <script>
        // Global App Config & Protocol Sync
        var USER_ID = <?php echo $user_id; ?>;
        var OTHER_ID = <?php echo $other_user_id; ?>;
        var CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        var SESSION_ID = Math.random().toString(36).substring(2, 10);
        var PROJECT_PREFIX = 'gm-v1';

        var protocol = localStorage.getItem('active_protocol') || (USER_ID === 2 ? 'chatgpt' : 'gemini');
        var isGPT = protocol === 'chatgpt';
        var themeName = isGPT ? "ChatGPT" : "Gemini";
        var themeIcon = isGPT ? "◎" : "✦";

        // Apply theme immediately to prevent flicker
        document.documentElement.setAttribute('data-theme', protocol);
        document.title = themeName;
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: var(--theme-bg); color: var(--theme-text); }
        
        .app-container { 
            display: flex; flex-direction: column; width: 100%;
            height: 100dvh; height: -webkit-fill-available;
            background: var(--theme-bg); overflow: hidden; position: relative;
        }
        header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 16px; padding-top: max(10px, env(safe-area-inset-top, 0px));
            background: var(--theme-bg); border-bottom: 1px solid var(--header-border);
            z-index: 1000; flex-shrink: 0; position: sticky; top: 0;
            min-height: 52px;
        }
        header::after {
            content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--theme-accent), #c58af9, var(--theme-accent), transparent);
            background-size: 200% 100%; animation: shimmer 4s linear infinite;
        }
        [data-theme="chatgpt"] header::after { display: none; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        @keyframes restore-pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.2); opacity: 0.7; } 100% { transform: scale(1); opacity: 1; } }
        .sparkle-logo.pulse-restore { animation: restore-pulse 1.5s infinite ease-in-out; filter: drop-shadow(0 0 8px var(--theme-accent)); }

        .header-left { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .menu-btn { background: none; border: none; color: #fff; font-size: 20px; padding: 0; line-height: 1; cursor: pointer; }
        .header-title { 
            font-size: 18px; font-weight: 600; 
            background: linear-gradient(135deg, var(--theme-accent), #c58af9); 
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; 
        }
        [data-theme="chatgpt"] .header-title { background: none; -webkit-text-fill-color: #fff; color: #fff; }
        .sparkle-logo {
            font-size: 22px; cursor: pointer; padding: 6px; transition: all 0.5s ease;
            user-select: none; color: #fff; line-height: 1;
            display: inline-block;
            background: linear-gradient(135deg, var(--theme-accent), #c58af9);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sparkle-logo.online {
            filter: grayscale(0); opacity: 1;
            text-shadow: 0 0 12px rgba(138,180,248,0.5);
        }
        [data-theme="chatgpt"] .sparkle-logo {
            background: none;
            -webkit-text-fill-color: #fff;
            color: #fff;
            opacity: 0.8;
        }

        /* Stream Area */
        .call-container {
            display: none; background: #000; width: 100%; position: relative;
            z-index: 900; overflow: hidden; border-bottom: 1px solid rgba(255,255,255,0.06); cursor: pointer;
        }
        .call-container.active { display: block; width: 100%; height: auto; max-height: 70vh; flex-shrink: 0; background: #000; }
        #remote-video { width: 100%; height: 100%; max-height: 70vh; background: #000; display: block; object-fit: cover; pointer-events: none; }
        #local-video {
            position: absolute; top: 12px; right: 12px; width: 110px; height: 160px;
            border-radius: 12px; z-index: 910;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5); 
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: scaleX(-1);
            object-fit: cover;
            background: transparent;
        }
        #local-video.hidden { opacity: 0; pointer-events: none; transform: scaleX(-1) scale(0.8); }
        #local-video.no-mirror { transform: scaleX(1); }

        .call-mini-controls {
            position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
            padding: 8px 16px; background: rgba(0,0,0,0.7); backdrop-filter: blur(12px);
            border-radius: 24px; display: flex; justify-content: center; gap: 12px;
            z-index: 920; border: 1px solid rgba(255,255,255,0.08);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }
        [data-theme="chatgpt"] .call-mini-controls { border-radius: 8px; backdrop-filter: none; background: #202123; }
        .call-mini-controls.hidden { opacity: 0; pointer-events: none; transform: translateX(-50%) translateY(16px); }
        
        .control-btn {
            background: rgba(255,255,255,0.08); border: none; width: 40px; height: 40px;
            border-radius: 50%; color: #fff; cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 18px; transition: all 0.2s;
        }
        [data-theme="chatgpt"] .control-btn { border-radius: 4px; }
        .control-btn:active { transform: scale(0.9); }
        .control-btn.off { background: rgba(255,77,77,0.35); color: #ff4d4d; }
        .control-btn.end { background: #ff4d4d; color: #fff; width: 46px; border-radius: 12px; }
        [data-theme="chatgpt"] .control-btn.end { border-radius: 4px; }

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
        [data-theme="chatgpt"] .avatar-ring { border-radius: 8px; background: var(--theme-accent); border: none; }
        .avatar-ring::after {
            content: ""; position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px;
            border-radius: 50%; border: 1.5px solid rgba(138,180,248,0.3);
            animation: ring-pulse 1.5s infinite;
        }
        [data-theme="chatgpt"] .avatar-ring::after { border-radius: 10px; border-color: var(--theme-accent); }
        @keyframes ring-pulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.3); opacity: 0; } }
        .overlay-name { font-size: 22px; font-weight: 600; margin-bottom: 6px; color: #fff; }
        .overlay-status { font-size: 13px; color: var(--theme-accent); margin-bottom: 32px; text-transform: uppercase; letter-spacing: 2px; }
        .overlay-btns { display: flex; gap: 36px; }
        .overlay-btn {
            width: 60px; height: 60px; border-radius: 50%; border: none;
            display: flex; align-items: center; justify-content: center; font-size: 26px; cursor: pointer;
            transition: transform 0.2s;
        }
        [data-theme="chatgpt"] .overlay-btn { border-radius: 8px; }
        .overlay-btn:active { transform: scale(0.9); }
        .btn-accept { background: #4caf50; color: #fff; }
        .btn-decline { background: #ff4d4d; color: #fff; }

        /* Fake Chat Area */
        .chat-area {
            flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column;
            gap: 10px; min-height: 0; -webkit-overflow-scrolling: touch;
            background: radial-gradient(ellipse at 50% 0%, rgba(138,180,248,0.03) 0%, transparent 60%);
        }
        [data-theme="chatgpt"] .chat-area { background: none; }
        .message {
            max-width: 82%; padding: 10px 16px; border-radius: 16px; font-size: 14px;
            line-height: 1.5; color: #e0e0e0; animation: fadeIn 0.35s ease;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        [data-theme="chatgpt"] .message { border-radius: 0; background: transparent !important; backdrop-filter: none; padding: 12px 0; border: none !important; }
        .message.ai {
            align-self: flex-start;
            background: linear-gradient(135deg, rgba(138,180,248,0.12), rgba(197,138,249,0.08));
            border: 1px solid rgba(138,180,248,0.15);
            border-bottom-left-radius: 4px;
        }
        .message.system {
            align-self: center; background: transparent; font-size: 10px; color: var(--theme-accent);
            text-transform: uppercase; letter-spacing: 1.5px; font-family: 'Roboto Mono', monospace;
            padding: 4px 0; opacity: 0.6;
        }
        .message.log {
            align-self: flex-start; background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.25);
            font-size: 11px; font-family: 'Roboto Mono', monospace;
            border-left: 2px solid var(--theme-accent); padding: 6px 12px; border-radius: 4px; opacity: 0.7;
        }
        .message h4 {
            margin: 0 0 4px 0; font-size: 12px;
            background: linear-gradient(135deg, var(--theme-accent), #c58af9);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            font-weight: 600; letter-spacing: 0.3px;
        }
        [data-theme="chatgpt"] .message h4 { background: none; -webkit-text-fill-color: var(--theme-accent); color: var(--theme-accent); }
        .message p { margin: 0; color: rgba(255,255,255,0.85); }

        .thinking-container { display: flex; align-items: center; gap: 6px; padding: 10px 16px; align-self: flex-start; }
        .thinking-dot { width: 7px; height: 7px; border-radius: 50%; animation: thinkBounce 1.4s infinite ease-in-out; }
        .thinking-dot:nth-child(1) { background: var(--theme-accent); animation-delay: 0s; }
        .thinking-dot:nth-child(2) { background: var(--theme-accent); opacity: 0.7; animation-delay: 0.2s; }
        .thinking-dot:nth-child(3) { background: var(--theme-accent); opacity: 0.4; animation-delay: 0.4s; }
        @keyframes thinkBounce { 0%,80%,100% { transform: scale(0.6); opacity: 0.3; } 40% { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* Input wrap */
        .input-wrap {
            position: sticky; bottom: 0; flex-shrink: 0; z-index: 1001;
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: var(--theme-bg);
        }
        .input-wrap::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--theme-accent), transparent);
            opacity: 0.3;
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
            pointer-events: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        [data-theme="chatgpt"] .chat-comment { border-radius: 6px; background: #202123; border-color: rgba(255,255,255,0.1); }
        .chat-comment.self {
            background: rgba(66, 133, 244, 0.2); border-color: rgba(66, 133, 244, 0.3);
        }
        [data-theme="chatgpt"] .chat-comment.self { background: var(--theme-accent); border: none; }
        .chat-comment.fade-out { animation: comment-out 0.3s ease forwards; }

        @keyframes comment-in { from { opacity: 0; transform: translateY(10px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes comment-out { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.9); } }

        .chat-input-area {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 16px; background: var(--theme-bg);
            padding-bottom: max(10px, env(safe-area-inset-bottom, 0px));
        }
        .chat-input-area input {
            flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px; padding: 10px 16px; color: #fff;
            font-size: 14px; outline: none; transition: border-color 0.2s;
        }
        [data-theme="chatgpt"] .chat-input-area input { border-radius: 8px; background: var(--theme-input); border-color: rgba(255,255,255,0.1); }
        .chat-input-area input:focus { border-color: var(--theme-accent); }
        .chat-input-area input::placeholder { color: rgba(255,255,255,0.25); }
        .chat-send-btn {
            background: linear-gradient(135deg, var(--theme-accent), #c58af9); border: none;
            width: 38px; height: 38px; border-radius: 50%;
            color: #fff; font-size: 15px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; transition: all 0.2s; flex-shrink: 0;
        }
        [data-theme="chatgpt"] .chat-send-btn { border-radius: 8px; background: var(--theme-accent); }
        .chat-send-btn:active { transform: scale(0.9); }

        .dl-timer { font-size: 10px; color: #ff4444; font-family: 'Roboto Mono', monospace; min-width: 32px; font-weight: 500; }

        @media (max-width: 768px) {
            #local-video { width: 76px; height: 120px; top: 8px; right: 8px; border-radius: 8px; }
            .chat-area .message { max-width: 90%; }
        }
    </style>
</head>
<body>
    <div class="app-container" id="main-app">
        <header>
            <div class="header-left" onclick="window.location.href='index.php'">
                <button class="menu-btn">☰</button>
                <span class="header-title" id="header-title">Gemini</span>
            </div>
            <div style="display:flex;align-items:center;gap:1px;">
                <div class="sparkle-logo" id="sparkle-logo" onclick="initiateCall()">✦</div>
            </div>
        </header>

        <div class="call-container" id="call-container" onclick="showControls()">
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
            <div class="message system" id="sys-msg">[NODE_ACTIVE]</div>
            <div class="thinking-container" id="init-thinking">
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
            </div>
        </div>

        <div class="input-wrap">
            <div class="chat-comments" id="chat-comments"></div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="Ask AI anything..." maxlength="500">
                <button class="chat-send-btn" id="chat-send-btn">✦</button>
            </div>
        </div>
    </div>

    <div class="call-overlay" id="incoming-overlay">
        <div class="avatar-ring" id="incoming-icon">✦</div>
        <div class="overlay-name" id="incoming-name">AI Node</div>
        <div class="overlay-status" id="incoming-status">Incoming Stream Request...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-accept" onclick="acceptCall()">✔</button>
            <button class="overlay-btn btn-decline" onclick="declineCall()">✖</button>
        </div>
    </div>

    <div class="call-overlay" id="outgoing-overlay">
        <div class="avatar-ring" id="outgoing-icon">✦</div>
        <div class="overlay-name" id="outgoing-name">AI Node</div>
        <div class="overlay-status" id="outgoing-status">Connecting to Peer...</div>
        <div class="overlay-btns">
            <button class="overlay-btn btn-decline" onclick="endCall()">✖</button>
        </div>
    </div>

    <div class="call-overlay" id="premium-modal">
        <div style="background:var(--theme-sidebar);border:1px solid rgba(255,255,255,0.1);border-radius:28px;padding:36px 28px;max-width:340px;width:90%;text-align:center;box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <div style="font-size:44px;margin-bottom:10px;" id="premium-emoji">✨</div>
            <h2 style="font-size:20px;font-weight:700;margin:0 0 6px 0;color:#fff;" id="premium-title">Upgrade to Gemini Advanced</h2>
            <p style="color:var(--theme-dim);font-size:13px;margin-bottom:20px;line-height:1.5;" id="premium-desc">Priority access to Gemini Ultra 2.0, 2M token context, and advanced tools.</p>
            <div style="margin-bottom:20px;">
                <span style="font-size:32px;font-weight:700;color:#fff;">$19.99</span>
                <span style="color:var(--theme-dim);font-size:13px;">/month</span>
            </div>
            <button id="premium-buy-btn" style="background:var(--theme-accent);color:#fff;border:none;padding:13px 0;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;width:100%;margin-bottom:8px;box-shadow:0 4px 15px rgba(0,0,0,0.2);">Buy Now</button>
            <button id="premium-cancel-btn" style="background:transparent;color:var(--theme-dim);border:1px solid rgba(255,255,255,0.1);padding:13px 0;border-radius:12px;font-size:14px;cursor:pointer;width:100%;">Not Now</button>
        </div>
    </div>

    <script>
        console.log('[App] Startup sequence initiated...');
        window.onerror = function(msg, url, line) {
            console.error('GLOBAL ERROR:', msg, 'at', line);
            if (typeof addChatMessage === 'function') addChatMessage('[System_Error: ' + msg + ']', 'log');
        };

        // Signaling node connection
        var peer = null;
        var localStream;
        let currentCall;
        let incomingCall;
        let dataConn;
        let isOtherOnline = false;
        let wasOtherOnline = false;
        let micEnabled = true;
        let videoEnabled = true;
        let localVideoBlack = false;
        let isCallUIHidden = false;
        let controlsTimer = null;
        let retryTimeout = null;
        let retryCount = 0;
        let currentFacingMode = 'user';
        let hasShownEndMessage = false;

        // Broadcast Loop System with Gemini Glitch
        let bcCanvas = null, bcCanvasCtx = null, bcAnimationId = null, bcAudioCtx = null, bcAudioSource = null, bcAudioDest = null, isGlitching = false, lastBCPos = 0;

        function triggerGlitch() {
            if (isGlitching) return;
            isGlitching = true;
            addChatMessage('[Signal_Interference: Re-aligning nodes...]', 'log');
            setTimeout(() => { isGlitching = false; }, 250);
        }

        async function getBroadcastStream(videoEl) {
            console.log('[Broadcast] Initializing Gemini Glitch Stream');
            if (!bcCanvas) bcCanvas = document.createElement('canvas');
            if (videoEl.readyState < 2) await new Promise(r => videoEl.onloadedmetadata = r);
            bcCanvas.width = videoEl.videoWidth || 640;
            bcCanvas.height = videoEl.videoHeight || 480;
            bcCanvasCtx = bcCanvas.getContext('2d', { alpha: false });

            if (bcAnimationId) cancelAnimationFrame(bcAnimationId);
            const drawFrame = () => {
                if (!videoEl.paused && !videoEl.ended) {
                    // Detect loop reset for freeze effect
                    if (videoEl.currentTime < lastBCPos && lastBCPos > videoEl.duration - 0.5) {
                        isGlitching = true; // reusing variable as "isFrozen"
                        setTimeout(() => { isGlitching = false; }, 300); // 300ms freeze on loop
                    }
                    lastBCPos = videoEl.currentTime;
                    
                    if (!isGlitching) {
                        bcCanvasCtx.drawImage(videoEl, 0, 0, bcCanvas.width, bcCanvas.height);
                    }
                    // If isGlitching (isFrozen) is true, we simply don't draw, leaving the last frame on canvas
                }
                bcAnimationId = requestAnimationFrame(drawFrame);
            };
            drawFrame();
            
            const canvasStream = bcCanvas.captureStream(30);
            try {
                if (!bcAudioCtx) bcAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (bcAudioCtx.state === 'suspended') await bcAudioCtx.resume();
                if (!bcAudioSource) {
                    bcAudioSource = bcAudioCtx.createMediaElementSource(videoEl);
                    bcAudioDest = bcAudioCtx.createMediaStreamDestination();
                    bcAudioSource.connect(bcAudioDest);
                }
                return new MediaStream([...canvasStream.getVideoTracks(), ...bcAudioDest.stream.getAudioTracks()]);
            } catch (e) { return canvasStream; }
        }

        function addChatMessage(text, type = 'system') {
            const area = document.getElementById('chat-area');
            if (!area) return;
            const msg = document.createElement('div');
            msg.className = 'message ' + type;
            if (type === 'ai') {
                const now = new Date();
                const t = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                msg.innerHTML = `<h4>${themeIcon} ${themeName}</h4><p>${text}</p><div style="font-size:10px;color:rgba(255,255,255,0.2);margin-top:6px;">${t}</div>`;
            } else msg.textContent = text;
            area.appendChild(msg); area.scrollTop = area.scrollHeight;
        }

        function showThinking(callback) {
            const area = document.getElementById('chat-area');
            if (!area) return;
            const think = document.createElement('div');
            think.className = 'thinking-container';
            think.innerHTML = '<div class="thinking-dot"></div><div class="thinking-dot"></div><div class="thinking-dot"></div>';
            area.appendChild(think); area.scrollTop = area.scrollHeight;
            setTimeout(() => { think.remove(); if (callback) callback(); }, 1200 + Math.random() * 800);
        }

        function showControls() {
            const controls = document.getElementById('call-mini-controls');
            const bcMenu = document.getElementById('bc-menu');
            if (!controls) return;
            controls.classList.remove('hidden');
            if (bcMenu) bcMenu.style.display = 'flex';
            clearTimeout(controlsTimer);
            controlsTimer = setTimeout(() => {
                if (currentCall && !isCallUIHidden) {
                    controls.classList.add('hidden');
                    if (bcMenu) bcMenu.style.display = 'none';
                }
            }, 3000);
        }

        function initPeer() {
            console.log('[Peer] Initializing Node...');
            if (peer && !peer.destroyed) try { peer.destroy(); } catch(e) {}
            clearTimeout(retryTimeout);
            const myPeerId = PROJECT_PREFIX + '-' + USER_ID + '-' + SESSION_ID;
            console.log('[Peer] ID:', myPeerId);
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
                console.log('[Peer] Node Opened:', id);
                registerPeer(id); addChatMessage('[Signal_Ready]'); 
            });
            peer.on('disconnected', () => { 
                console.warn('[Peer] Node Disconnected');
                if (!peer.destroyed) { try { peer.reconnect(); } catch(e) { if (!currentCall) initPeer(); } } 
            });
            peer.on('error', err => {
                console.error('[Peer] Error:', err.type);
                if (err.type === 'unavailable-id') window.location.reload();
                else if (err.type === 'network' || err.type === 'server-error') {
                    if (currentCall) return;
                    retryCount++;
                    retryTimeout = setTimeout(initPeer, Math.min(1000 * Math.pow(2, retryCount), 30000));
                } else if (err.type !== 'peer-disconnected') addChatMessage('[Error: ' + err.type + ']');
            });
            peer.on('call', call => {
                console.log('[Peer] Incoming Call');
                if (currentCall && !currentCall._streamReceived) currentCall.close();
                if (currentCall) { call.close(); return; }
                incomingCall = call;
                if (USER_ID === 1) showPremiumModal(() => acceptCall(), () => declineCall());
                else {
                    const overlay = document.getElementById('incoming-overlay');
                    if (overlay) overlay.classList.add('show');
                    const status = document.getElementById('incoming-status');
                    if (status) status.textContent = 'Incoming Stream Request...';
                }
            });
            peer.on('connection', conn => { 
                console.log('[Peer] Incoming Data Connection');
                dataConn = conn; setupDataConn(conn); 
            });
        }

        function setupDataConn(conn) {
            conn.on('open', () => console.log('[DataChan] Open'));
            conn.on('data', data => {
                if (data.type === 'mic') showCallNotification(data.enabled ? 'Partner unmuted' : 'Partner muted');
                else if (data.type === 'video') showCallNotification(data.enabled ? 'Partner camera on' : 'Partner camera off');
                else if (data.type === 'visibility') handleConnectionState(data.visible ? 'connected' : 'disconnected');
                else if (data.type === 'camera_switched') showCallNotification('Partner switched camera');
                else if (data.type === 'camera_freeze') {
                    const video = document.getElementById('remote-video');
                    if (video) {
                        if (data.frozen) video.pause();
                        else video.play().catch(()=>{});
                    }
                }
            });
        }

        function handleConnectionState(state) {
            const video = document.getElementById('remote-video');
            if (state === 'disconnected' || state === 'failed') {
                if (video) video.pause(); // Freeze the frame
            } else if (state === 'connected' || state === 'completed') {
                if (video) video.play().catch(() => {});
            }
        }

        document.addEventListener('visibilitychange', () => { if (dataConn && dataConn.open) dataConn.send({ type: 'visibility', visible: !document.hidden }); });

        function showPremiumModal(onBuy, onCancel) {
            const modal = document.getElementById('premium-modal');
            if (!modal) return;
            const buyBtn = document.getElementById('premium-buy-btn');
            const cancelBtn = document.getElementById('premium-cancel-btn');
            if (buyBtn) buyBtn.onclick = () => { modal.classList.remove('show'); onBuy(); };
            if (cancelBtn) cancelBtn.onclick = () => { modal.classList.remove('show'); onCancel(); };
            modal.classList.add('show');
        }

        async function startLocalStream() {
            console.log('[Media] Requesting Local Stream...');
            if (localStream) return localStream;
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: currentFacingMode }, audio: true });
                console.log('[Media] Stream Granted');
                document.getElementById('call-container').classList.add('active');
                const lv = document.getElementById('local-video');
                if (lv) lv.srcObject = localStream;
                micEnabled = true; videoEnabled = true; updateControlIcons();
                return localStream;
            } catch (e) {
                console.error('[Media] Request Failed:', e.name);
                if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') { showPermissionModal(); throw e; }
                localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                videoEnabled = false; updateControlIcons(); return localStream;
            }
        }

        function showPermissionModal() {
            const modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;padding:20px;';
            modal.innerHTML = `<div style="background:#1a1a1c;border:1px solid rgba(255,255,255,0.1);border-radius:24px;padding:32px;max-width:380px;width:100%;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">🔒</div>
                <h2 style="font-size:20px;font-weight:600;margin-bottom:8px;color:#fff;">Permissions Required</h2>
                <p style="color:#999;font-size:14px;margin-bottom:24px;line-height:1.6;">Camera and microphone access is needed.</p>
                <button onclick="window.location.reload()" style="background:#4285f4;color:#fff;border:none;padding:12px 24px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;width:100%;">Retry</button>
            </div>`;
            document.body.appendChild(modal);
        }

        async function acceptCall() {
            console.log('[Call] Accepting...');
            if (incomingCall) {
                try {
                    const stream = await startLocalStream();
                    let streamToSend = stream;
                    if (typeof isBCBroadcasting !== 'undefined' && isBCBroadcasting) {
                        const v = document.getElementById('bc-video'); if (v) await v.play();
                        streamToSend = await getBroadcastStream(v);
                    }
                    incomingCall.answer(streamToSend);
                    isCallUIHidden = false; handleCall(incomingCall);
                } catch (e) { console.error('[Call] Acceptance Failed', e); incomingCall.close(); }
            }
        }

        function declineCall() { console.log('[Call] Declining'); if (incomingCall) incomingCall.close(); incomingCall = null; const overlay = document.getElementById('incoming-overlay'); if (overlay) overlay.classList.remove('show'); }

        function handleCall(call) {
            currentCall = call; isCallUIHidden = false; showControls();
            const noAnswerTimer = setTimeout(() => { if (!call._streamReceived) { console.warn('[Call] Timeout reached'); addChatMessage('[Timeout: No response]'); endCall(); } }, 15000);
            call.on('stream', stream => {
                const remote = document.getElementById('remote-video');
                const container = document.getElementById('call-container');
                if (!remote || remote.srcObject === stream) return;
                console.log('[Call] Remote Stream Received');
                clearTimeout(noAnswerTimer); call._streamReceived = true;
                const inOver = document.getElementById('incoming-overlay'); if (inOver) inOver.classList.remove('show');
                const outOver = document.getElementById('outgoing-overlay'); if (outOver) outOver.classList.remove('show');
                if (container) container.classList.add('active');
                
                remote.onloadedmetadata = () => {
                    console.log('[Call] Metadata Loaded:', remote.videoWidth, 'x', remote.videoHeight);
                    if (remote.videoWidth && remote.videoHeight && container) {
                        container.style.aspectRatio = (remote.videoWidth > remote.videoHeight) ? '16/9' : '9/16';
                    }
                };
                remote.srcObject = stream;
                remote.play().catch(e => { if (e.name === 'NotAllowedError') { remote.muted = true; remote.play(); } });
                addChatMessage('[Stream_Established]');
                
                if (typeof isBCBroadcasting !== 'undefined' && isBCBroadcasting && USER_ID === 1) {
                    setTimeout(async () => {
                        const v = document.getElementById('bc-video');
                        if (!v) return;
                        const bcStream = await getBroadcastStream(v);
                        if (call.peerConnection) {
                            const pc = call.peerConnection;
                            const vSender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                            const aSender = pc.getSenders().find(s => s.track && s.track.kind === 'audio');
                            if (vSender) await vSender.replaceTrack(bcStream.getVideoTracks()[0]);
                            if (aSender) await aSender.replaceTrack(bcStream.getAudioTracks()[0]);
                        }
                    }, 1000);
                }
                if (call.peerConnection) call.peerConnection.oniceconnectionstatechange = () => handleConnectionState(call.peerConnection.iceConnectionState);
            });
            call.on('close', () => { console.log('[Call] Peer closed connection'); endCall(); });
        }

        async function initiateCall() {
            if (currentCall && isCallUIHidden) {
                const container = document.getElementById('call-container'); if (container) container.classList.add('active');
                const controls = document.getElementById('call-mini-controls'); if (controls) controls.classList.remove('hidden');
                const bcMenu = document.getElementById('bc-menu'); if (bcMenu) bcMenu.style.display = 'flex';
                isCallUIHidden = false;
                const logo = document.getElementById('sparkle-logo'); if (logo) logo.classList.remove('pulse-restore');
                showControls();
                return;
            }
            if (currentCall) return;
            const otherPeerId = await getOtherPeerId();
            if (!otherPeerId) { console.error('[Call] Other peer ID not found'); return addChatMessage('[Error: Peer Offline]'); }
            console.log('[Call] Initiating to:', otherPeerId);
            const outOver = document.getElementById('outgoing-overlay'); if (outOver) outOver.classList.add('show');
            try {
                const stream = await startLocalStream();
                let streamToSend = stream;
                if (typeof isBCBroadcasting !== 'undefined' && isBCBroadcasting) {
                    const v = document.getElementById('bc-video'); if (v) { await v.play(); streamToSend = await getBroadcastStream(v); }
                }
                const call = peer.call(otherPeerId, streamToSend);
                dataConn = peer.connect(otherPeerId, { reliable: true });
                setupDataConn(dataConn); handleCall(call);
            } catch (e) { console.error('[Call] Initiation Failed', e); const outOver = document.getElementById('outgoing-overlay'); if (outOver) outOver.classList.remove('show'); }
        }

        async function registerPeer(id) { 
            console.log('[Sync] Registering ID:', id);
            await fetch('api.php?action=register_peer', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF_TOKEN,'Content-Type':'application/json'}, body:JSON.stringify({peer_id:id}) }); 
        }
        async function getOtherPeerId() { const r = await fetch('api.php?action=get_peer'); const d = await r.json(); return d.peer_id; }
        function showInAppNotification(title, message, icon = '✦', type = 'system', actionUrl = null) {
            if (activeNotifications.has(type)) return;
            const area = document.getElementById('chat-comments'); // Use chat area for internal notifications
            if (!area) return;
            const card = document.createElement('div'); card.className = `chat-comment`;
            card.innerHTML = `<div style="display:flex;align-items:center;gap:10px;"><span>${icon}</span><div><div style="font-weight:700;font-size:11px;">${title}</div><div>${message}</div></div></div>`;
            
            if (actionUrl) {
                card.style.cursor = 'pointer';
                card.onclick = () => {
                    if (actionUrl.startsWith('javascript:')) {
                        eval(actionUrl.replace('javascript:', ''));
                        hideInAppNotification(type, card);
                    } else {
                        window.location.href = actionUrl;
                    }
                };
            } else {
                card.onclick = () => hideInAppNotification(type, card);
            }
            
            area.appendChild(card); 
            activeNotifications.set(type, card);
            if (type === 'system') setTimeout(() => hideInAppNotification(type, card), 8000);
        }

        const activeNotifications = new Map();
        function hideInAppNotification(type, card) {
            if (card) { card.classList.add('fade-out'); setTimeout(() => { if (card.parentNode) card.remove(); activeNotifications.delete(type); }, 500); }
        }
        function hideCallUI() {
            const container = document.getElementById('call-container'); 
            if (container) container.classList.remove('active');
            isCallUIHidden = true;
            const logo = document.getElementById('sparkle-logo'); 
            if (logo) logo.classList.add('pulse-restore');

            // Prompt User 1 to start broadcast if recording exists
            if (USER_ID === 1 && bcBlobUrl && !isBCBroadcasting) {
                showInAppNotification('Presence Mode', 'Start Broadcast Loop?', '📡', 'system', 'javascript:startBCBroadcast()');
            }
        }        function handleOutsideClick() { if (currentCall && !isCallUIHidden) hideCallUI(); }

        async function checkPresence() {
            try {
                const resp = await fetch('api.php?action=get_other_status');
                const data = await resp.json();
                isOtherOnline = data.status === 'active' || data.status === 'typing';
                const logo = document.getElementById('sparkle-logo'); if (logo) logo.classList.toggle('online', isOtherOnline);
            } catch (e) {}
        }

        function toggleMic() {
            if (!localStream) return; micEnabled = !micEnabled;
            if (USER_ID === 1) localStream.getAudioTracks().forEach(t => t.enabled = micEnabled);
            updateControlIcons(); if (dataConn) dataConn.send({ type: 'mic', enabled: micEnabled });
        }

        function toggleVideo() {
            if (!localStream) return; videoEnabled = !videoEnabled;
            const vid = document.getElementById('local-video');
            
            if (USER_ID === 1) {
                if (!videoEnabled) {
                    // Signal User 2 to "freeze" before turning off
                    if (dataConn) dataConn.send({ type: 'camera_freeze', frozen: true });
                    setTimeout(() => {
                        localStream.getVideoTracks().forEach(t => t.enabled = false);
                    }, 500); // 500ms freeze before track kill
                } else {
                    localStream.getVideoTracks().forEach(t => t.enabled = true);
                    if (dataConn) dataConn.send({ type: 'camera_freeze', frozen: false });
                }
            } else { 
                localVideoBlack = !videoEnabled; 
                if (vid) vid.style.filter = localVideoBlack ? 'brightness(0)' : ''; 
            }
            
            updateControlIcons(); 
            if (dataConn && USER_ID !== 1) dataConn.send({ type: 'video', enabled: videoEnabled });
        }

        function toggleSelfView() { const vid = document.getElementById('local-video'); if (vid) vid.classList.toggle('hidden'); updateControlIcons(); }

        function updateControlIcons() {
            const micBtn = document.getElementById('toggle-mic'); if (micBtn) { micBtn.textContent = micEnabled ? '🎤' : '🔇'; micBtn.classList.toggle('off', !micEnabled); }
            const vidBtn = document.getElementById('toggle-video'); if (vidBtn) { vidBtn.textContent = videoEnabled ? '📹' : '📵'; vidBtn.classList.toggle('off', !videoEnabled); }
            const lv = document.getElementById('local-video'); const selfBtn = document.getElementById('toggle-self');
            if (lv && selfBtn) { selfBtn.textContent = lv.classList.contains('hidden') ? '🚫' : '👤'; selfBtn.classList.toggle('off', lv.classList.contains('hidden')); }
        }

        async function swapTracks() {
            if (!localStream) return;
            currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
            const lv = document.getElementById('local-video'); if (lv) lv.classList.toggle('no-mirror', currentFacingMode === 'environment');
            try {
                localStream.getTracks().forEach(t => t.stop()); localStream = null;
                const newStream = await startLocalStream();
                if (currentCall && currentCall.peerConnection) {
                    const pc = currentCall.peerConnection;
                    const vSender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    const aSender = pc.getSenders().find(s => s.track && s.track.kind === 'audio');
                    if (vSender) await vSender.replaceTrack(newStream.getVideoTracks()[0]);
                    if (aSender) await aSender.replaceTrack(newStream.getAudioTracks()[0]);
                }
                if (dataConn) dataConn.send({ type: 'camera_switched', mode: currentFacingMode });
                addChatMessage(`[Camera_Switched: ${currentFacingMode === 'user' ? 'Front' : 'Back'}]`, 'log');
            } catch(e) { addChatMessage('[Error: Camera switch failed]'); }
        }

        let isRecording = false, recSeconds = 0, recTimerInterval = null, recChunkIndex = 0, recSessionId = null, recMediaRecorder = null;
        function getRecordingStream() {
            const remoteVideo = document.getElementById('remote-video');
            const remoteStream = remoteVideo ? remoteVideo.srcObject : null;
            if (!remoteStream) return null;
            return new MediaStream(remoteStream.getTracks().filter(t => t.readyState === 'live'));
        }

        async function uploadChunk(blob, index, sessionId) {
            const fd = new FormData(); fd.append('recording', blob); fd.append('chunk_index', index); fd.append('rec_session_id', sessionId);
            try { await fetch('api.php?action=save_recording', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }, body: fd }); } catch(e) {}
        }

        async function toggleRecording() {
            const btn = document.getElementById('record-btn'); const timer = document.getElementById('record-timer');
            if (isRecording) {
                isRecording = false; clearInterval(recTimerInterval);
                if (btn) { btn.classList.remove('off'); btn.textContent = '🔴'; } if (timer) timer.style.display = 'none';
                if (recMediaRecorder) recMediaRecorder.stop(); return;
            }
            const stream = getRecordingStream(); if (!stream) return addChatMessage('[Sync_Error: No session]');
            isRecording = true; recChunkIndex = 0; recSeconds = 0; recSessionId = Date.now();
            if (btn) { btn.classList.add('off'); btn.textContent = '⚪'; } if (timer) timer.style.display = 'inline';
            recMediaRecorder = new MediaRecorder(stream);
            recMediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) uploadChunk(e.data, recChunkIndex++, recSessionId); };
            recMediaRecorder.start(10000);
            recTimerInterval = setInterval(() => { recSeconds++; if (timer) timer.textContent = String(Math.floor(recSeconds / 60)).padStart(2, '0') + ':' + String(recSeconds % 60).padStart(2, '0'); }, 1000);
            addChatMessage('[Backup: Active]');
        }

        let bcRecorder = null, bcChunks = [], bcBlobUrl = null, isBCBroadcasting = false;
        function toggleBCMenu() { const menu = document.getElementById('bc-menu'); if (!menu) return; menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'flex' : 'none'; }
        async function startBCRecording() {
            if (!localStream) await startLocalStream();
            bcChunks = []; bcRecorder = new MediaRecorder(localStream);
            bcRecorder.ondataavailable = (e) => { if (e.data.size > 0) bcChunks.push(e.data); };
            bcRecorder.onstop = () => {
                bcBlobUrl = URL.createObjectURL(new Blob(bcChunks, { type: 'video/webm' }));
                const bv = document.getElementById('bc-video'); if (bv) bv.src = bcBlobUrl;
                const bs = document.getElementById('bc-start'); if (bs) bs.style.display = 'inline-block';
                const br = document.getElementById('bc-restart'); if (br) br.style.display = 'inline-block';
                addChatMessage('[Broadcast: Segment ready]');
            };
            bcRecorder.start(); const brc = document.getElementById('bc-rec'); if (brc) brc.style.display = 'none'; const bsc = document.getElementById('bc-stop-rec'); if (bsc) bsc.style.display = 'inline-block';
        }
        function stopBCRecording() { if (bcRecorder) bcRecorder.stop(); const brc = document.getElementById('bc-rec'); if (brc) brc.style.display = 'inline-block'; const bsc = document.getElementById('bc-stop-rec'); if (bsc) bsc.style.display = 'none'; }
        async function startBCBroadcast() {
            if (!bcBlobUrl) return; isBCBroadcasting = true;
            addChatMessage('[Broadcast: Syncing sequence...]', 'log');
            const v = document.getElementById('bc-video'); if (v) await v.play();
            const bcStream = await getBroadcastStream(v);
            if (currentCall && currentCall.peerConnection) {
                const pc = currentCall.peerConnection;
                const vSender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                const aSender = pc.getSenders().find(s => s.track && s.track.kind === 'audio');
                if (vSender) await vSender.replaceTrack(bcStream.getVideoTracks()[0]);
                if (aSender) await aSender.replaceTrack(bcStream.getAudioTracks()[0]);
            }
            const lv = document.getElementById('local-video'); if (lv) lv.srcObject = bcStream;
            const bs = document.getElementById('bc-start'); if (bs) bs.style.display = 'none';
            const be = document.getElementById('bc-end'); if (be) be.style.display = 'inline-block';
            const br = document.getElementById('bc-restart'); if (br) br.style.display = 'none';
            addChatMessage('[Broadcast: Looping active]');
        }
        async function stopBCBroadcast() {
            isBCBroadcasting = false; addChatMessage('[Broadcast: Restoring live feed...]', 'log');
            setTimeout(async () => {
                const v = document.getElementById('bc-video'); if (v) v.pause();
                if (currentCall && currentCall.peerConnection) {
                    const pc = currentCall.peerConnection;
                    const vSender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    const aSender = pc.getSenders().find(s => s.track && s.track.kind === 'audio');
                    if (vSender) await vSender.replaceTrack(localStream.getVideoTracks()[0]);
                    if (aSender) await aSender.replaceTrack(localStream.getAudioTracks()[0]);
                }
                const lv = document.getElementById('local-video'); if (lv) lv.srcObject = localStream;
                const bs = document.getElementById('bc-start'); if (bs) bs.style.display = 'inline-block';
                const be = document.getElementById('bc-end'); if (be) be.style.display = 'none';
                const br = document.getElementById('bc-restart'); if (br) br.style.display = 'inline-block';
                addChatMessage('[Broadcast: Live restored]');
            }, 800);
        }
        function restartBC() { if (isBCBroadcasting) stopBCBroadcast(); bcBlobUrl = null; const bv = document.getElementById('bc-video'); if (bv) bv.src = ''; const bs = document.getElementById('bc-start'); if (bs) bs.style.display = 'none'; const br = document.getElementById('bc-restart'); if (br) br.style.display = 'none'; const brc = document.getElementById('bc-rec'); if (brc) brc.style.display = 'inline-block'; }

        let knownCommentIds = new Set();
        async function pollCallComments() {
            try {
                const r = await fetch('api.php?action=get_call_comments');
                const data = await r.json();
                data.forEach(c => {
                    if (!knownCommentIds.has(c.id)) {
                        knownCommentIds.add(c.id);
                        const isMe = c.sender_id == USER_ID;
                        const el = document.createElement('div');
                        el.className = 'chat-comment' + (isMe ? ' self' : '');
                        el.textContent = c.content; const cc = document.getElementById('chat-comments'); if (cc) cc.appendChild(el);
                        setTimeout(() => { el.classList.add('fade-out'); setTimeout(() => el.remove(), 300); }, 5000);
                    }
                });
            } catch(e) {}
        }

        async function sendCallComment() {
            const input = document.getElementById('chat-input'); const text = input ? input.value.trim() : ''; if (!text) return;
            try { await fetch('api.php?action=send_call_comment', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' }, body: JSON.stringify({ content: text }) }); if (input) input.value = ''; } catch(e) {}
        }

        window.onload = () => {
            console.log('[App] Handshake completed. Initializing UI...');
            const ht = document.getElementById('header-title'); if (ht) ht.textContent = themeName;
            const sl = document.getElementById('sparkle-logo'); if (sl) sl.textContent = themeIcon;
            const sm = document.getElementById('sys-msg'); if (sm) sm.textContent = `[${themeName.toUpperCase()}_ACTIVE]`;
            const iname = document.getElementById('incoming-name'); if (iname) iname.textContent = '<?php echo $other_name; ?>';
            const oname = document.getElementById('outgoing-name'); if (oname) oname.textContent = '<?php echo $other_name; ?>';
            if (isGPT) { const ph = document.querySelector('#premium-modal h2'); if (ph) ph.textContent = "Upgrade to ChatGPT Plus"; }
            initPeer(); setInterval(checkPresence, 5000); setInterval(pollCallComments, 2000);
            const csb = document.getElementById('chat-send-btn'); if (csb) csb.onclick = sendCallComment;
            const ci = document.getElementById('chat-input'); if (ci) ci.onkeydown = (e) => { if (e.key === 'Enter') sendCallComment(); };
            setInterval(() => { const blob = new Blob([JSON.stringify({ is_typing: 0, in_theater: 0, in_call: 1 })], { type: 'application/json' }); navigator.sendBeacon(`api.php?action=update_status&_csrf=${CSRF_TOKEN}`, blob); }, 5000);
            setInterval(() => { if (isCallUIHidden) { const logs = ['[Security: Handshake verified]', '[System: Kernel optimized]', '[Model: Context updated]']; addChatMessage(logs[Math.floor(Math.random()*logs.length)], 'log'); } }, 20000);
            const think = document.getElementById('init-thinking'); if (think) setTimeout(() => think.remove(), 2000);
        };

        function endCall() { location.reload(); }
    </script>
</body>
</html>