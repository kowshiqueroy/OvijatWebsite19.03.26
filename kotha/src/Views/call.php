<?php
/**
 * Variables injected by CallController::callInterface() via extract($data).
 * Declared here so VS Code static analysis does not flag them as undefined.
 *
 * @var string   $chatId      Active chat room ID (e.g. "1on1_1_2")
 * @var string   $callType    "audio" or "video"
 * @var string   $callMode    "outgoing" or "incoming"
 * @var string   $partnerName Display name of the other call participant
 * @var int|null $partnerId   User ID of the other participant (null for groups)
 * @var int      $userId      Current user's ID
 * @var string   $userName    Current user's display name
 * @var string   $baseUrl     App base URL path (e.g. "/kotha")
 */

$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
function getAvatarBg($id): string {
    $palette = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1','#0ea5e9','#14b8a6'];
    if (is_numeric($id)) {
        return $palette[intval($id) % count($palette)];
    }
    return $palette[abs(crc32(strval($id))) % count($palette)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call — <?= e($partnerName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/public/img/icon.svg">
    <link rel="shortcut icon" href="<?= $baseUrl ?>/public/img/icon.svg">
    <meta name="theme-color" content="#00f2fe">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Body: flex column, fills screen ─────────────────── */
        body.call-body {
            display: flex;
            flex-direction: column;
            width: 100vw;
            height: 100dvh;
            background: #090e14;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ═══════════════════════════════════════════════════════
           TOP — partner name (small) + timer (large, blinking)
           No background, no gradient — body colour shows through.
           ═══════════════════════════════════════════════════════ */
        .call-top {
            flex-shrink: 0;
            text-align: center;
            padding: clamp(32px, 7vh, 54px) 24px 12px;
        }

        .call-name {
            font-size: 0.68rem;
            font-weight: 400;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.38);
            margin-bottom: 8px;
        }

        .call-timer {
            font-size: clamp(2.8rem, 11vw, 4rem);
            font-weight: 200;
            letter-spacing: 4px;
            font-variant-numeric: tabular-nums;
            animation: timerPulse 2.2s ease-in-out infinite alternate;
            line-height: 1;
        }

        @keyframes timerPulse {
            0%   { opacity: 0.50; }
            100% { opacity: 0.82; }
        }

        /* ═══════════════════════════════════════════════════════
           STAGE — fills all remaining space between top and footer.
           Both video feeds live here; they maintain their aspect
           ratios via object-fit:contain (letterboxed, no cropping).
           ═══════════════════════════════════════════════════════ */
        .call-stage {
            flex: 1;
            min-height: 0;          /* allows flex child to shrink below content */
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Remote (received) video ──────────────────────────── */
        .call-remote {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;    /* maintain aspect ratio — no cropping */
            background: transparent;
            display: none;          /* JS shows it when stream arrives */
        }

        /* ── Local (sent) video PIP ─────────────────────────── */
        .call-local {
            position: absolute;
            bottom: 12px;
            right: 12px;
            width: clamp(68px, 20vw, 96px);
            aspect-ratio: 3 / 4;
            object-fit: cover;      /* PIP can crop — it's a thumbnail */
            border-radius: 12px;
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.45);
            z-index: 10;
            background: #111b22;
            display: none;          /* JS shows it for video calls */
        }

        /* ── Avatar (audio calls / before video connects) ──────── */
        .call-avatar-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .call-avatar {
            width: clamp(88px, 24vw, 124px);
            height: clamp(88px, 24vw, 124px);
            border-radius: 50%;
            background: linear-gradient(135deg, #19293a 0%, #21364a 100%);
            border: 2px solid rgba(0, 212, 255, 0.13);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(2rem, 8vw, 3.2rem);
            font-weight: 700;
            color: rgba(0, 212, 255, 0.72);
            animation: avatarPulse 2.8s ease-in-out infinite;
        }

        @keyframes avatarPulse {
            0%, 100% { box-shadow: 0 0 0 0   rgba(0, 212, 255, 0.12); }
            50%       { box-shadow: 0 0 0 20px rgba(0, 212, 255, 0);   }
        }

        /* ═══════════════════════════════════════════════════════
           FOOTER — controls sit flush at the bottom.
           NO background, NO gradient on the wrapper — only the
           frosted-glass pill has a background.
           ═══════════════════════════════════════════════════════ */
        .call-footer {
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px 20px clamp(18px, 5vh, 38px);
            /* intentionally no background */
        }

        .call-pill {
            display: flex;
            gap: 12px;
            align-items: center;
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 50px;
            padding: 10px 18px;
        }

        /* ── Control buttons ─────────────────────────────────── */
        .cb {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: none;
            color: #dde4eb;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.10);
            transition: background 0.15s ease, transform 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .cb:hover  { background: rgba(255, 255, 255, 0.18); transform: scale(1.06); }
        .cb:active { transform: scale(0.93); }
        .cb.active   { background: #22c55e; color: #fff; }
        .cb.disabled { background: #ef4444; color: #fff; }

        .cb.hangup {
            width: 56px;
            height: 56px;
            font-size: 1.2rem;
            background: #e53e3e;
            box-shadow: 0 4px 16px rgba(229, 62, 62, 0.4);
        }
        .cb.hangup i    { transform: rotate(135deg); }
        .cb.hangup:hover {
            background: #c53030;
            transform: rotate(-135deg) scale(1.06);
        }
        .cb.hangup:active { transform: rotate(-135deg) scale(0.93); }

        /* ═══════════════════════════════════════════════════════
           DISCONNECT OVERLAY
           ═══════════════════════════════════════════════════════ */
        #callDisconnectOverlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 60;
            background: rgba(9, 14, 20, 0.93);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            text-align: center;
            padding: 32px;
        }
        .disc-icon { font-size: 3rem; color: #ef4444; animation: avatarPulse 2s infinite; }
        #callDisconnectOverlay h2 { font-size: 1.35rem; font-weight: 600; }
        #disconnectReason { color: rgba(255,255,255,0.4); font-size: 0.84rem; max-width: 290px; line-height: 1.6; }
        .disc-hint        { color: rgba(255,255,255,0.28); font-size: 0.74rem; max-width: 280px; line-height: 1.5; }

        .disc-btn-row {
            display: flex; gap: 10px; flex-wrap: wrap;
            justify-content: center; margin-top: 4px;
        }
        .disc-btn {
            padding: 11px 22px;
            border-radius: 12px; border: none;
            font-family: 'Outfit', sans-serif;
            font-size: 0.86rem; font-weight: 600;
            cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: opacity 0.15s;
        }
        .disc-btn:hover  { opacity: 0.85; }
        .disc-btn:active { opacity: 0.7; }
        .disc-btn-retry { background: linear-gradient(135deg, #00d4ff, #4facfe); color: #0a1520; }
        .disc-btn-back  { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); color: #dde4eb; }

        /* ── Responsive ──────────────────────────────────────── */
        @media (max-width: 480px) {
            .call-pill { gap: 8px; padding: 9px 14px; }
            .cb        { width: 46px; height: 46px; font-size: 1rem; }
            .cb.hangup { width: 52px; height: 52px; }
        }

        @media (max-height: 600px) {
            /* Short screens (landscape mobile): tighten top padding */
            .call-top  { padding-top: 16px; padding-bottom: 8px; }
            .call-timer { font-size: clamp(2rem, 8vw, 2.8rem); }
            .call-footer { padding-bottom: 12px; }
        }
    </style>
</head>
<body class="call-body">

    <!-- ── TOP: partner name + timer ─────────────────────────── -->
    <header class="call-top">
        <p class="call-name"><?= e($partnerName) ?></p>
        <div class="call-timer" id="callStatusText">CONNECTING...</div>
    </header>

    <!-- ── STAGE: video feeds + avatar ───────────────────────── -->
    <main class="call-stage">

        <!-- Received feed (remote) — contains, maintains aspect ratio -->
        <video class="call-remote" id="remoteVideoElement" autoplay playsinline></video>

        <!-- Sent feed (local) PIP — bottom-right of the stage -->
        <video class="call-local" id="localVideoElement" autoplay muted playsinline></video>

        <!-- Audio-call avatar (hidden when video connects) -->
        <div class="call-avatar-wrap" id="whatsappAvatarContainer">
            <div class="call-avatar" style="background: <?= getAvatarBg($partnerId) ?>; display: flex; align-items: center; justify-content: center; color: #fff;">
                <i class="fa-solid fa-user" style="font-size: 3rem;"></i>
            </div>
        </div>

    </main>

    <!-- ── FOOTER: controls — no background wrapper ──────────── -->
    <footer class="call-footer">
        <div class="call-pill">
            <button class="cb" id="toggleMicBtn" onclick="toggleMute()" title="Mute / Unmute">
                <i class="fa-solid fa-microphone"></i>
            </button>
            <button class="cb" id="toggleVideoBtn" onclick="toggleCamera()" title="Camera On / Off" style="display:none;">
                <i class="fa-solid fa-video"></i>
            </button>
            <button class="cb" id="switchCameraBtn" onclick="switchCamera()" title="Switch Camera" style="display:none;">
                <i class="fa-solid fa-camera-rotate"></i>
            </button>
            <button class="cb" id="shareScreenBtn" onclick="toggleScreenShare()" title="Share Screen" style="display:none;">
                <i class="fa-solid fa-desktop"></i>
            </button>
            <button class="cb hangup" onclick="terminateCall()" title="End Call">
                <i class="fa-solid fa-phone"></i>
            </button>
        </div>
    </footer>

    <!-- Legacy shim -->
    <div id="audioCallIndicator" style="display:none;"></div>

    <!-- ── Disconnect overlay ─────────────────────────────────── -->
    <div id="callDisconnectOverlay">
        <div class="disc-icon"><i class="fa-solid fa-phone-slash"></i></div>
        <h2>Call Disconnected</h2>
        <p id="disconnectReason">The call has ended.</p>

        <!-- Both sides always see Retry + Back to Chat -->
        <div class="disc-btn-row">
            <button class="disc-btn disc-btn-retry" onclick="retryCall()">
                <i class="fa-solid fa-rotate-right"></i> Retry Call
            </button>
            <button class="disc-btn disc-btn-back" onclick="backToChat()">
                <i class="fa-solid fa-arrow-left"></i> Back to Chat
            </button>
        </div>

        <!-- ── Incoming call overlay (shown ON TOP when other party retries) ── -->
        <div id="retryIncomingOverlay"
             style="display:none;position:absolute;inset:0;z-index:10;
                    background:rgba(9,14,20,.97);backdrop-filter:blur(16px);
                    flex-direction:column;align-items:center;justify-content:center;
                    gap:14px;padding:24px;text-align:center;border-radius:inherit;">

            <!-- Animated ring -->
            <div style="position:relative;margin-bottom:4px;">
                <div style="width:74px;height:74px;border-radius:50%;
                            background:rgba(34,197,94,.1);border:2px solid rgba(34,197,94,.3);
                            display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-phone" id="retryCallIcon"
                       style="font-size:1.7rem;color:#22c55e;"></i>
                </div>
                <div style="position:absolute;inset:-8px;border-radius:50%;
                            border:1.5px solid rgba(34,197,94,.2);
                            animation:avatarPulse 1.8s ease-in-out infinite;"></div>
            </div>

            <h3 id="retryCallerName"
                style="font-size:1.05rem;font-weight:700;color:#fff;margin:0;line-height:1.3;"></h3>
            <p  id="retryCallTypeLabel"
                style="font-size:0.78rem;color:rgba(255,255,255,.4);margin:0;"></p>

            <!-- Accept / Decline buttons -->
            <div style="display:flex;gap:28px;margin-top:8px;align-items:flex-end;">
                <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                    <button onclick="rejectRetryCall()"
                        style="width:58px;height:58px;border-radius:50%;
                               background:linear-gradient(135deg,#ef4444,#b91c1c);
                               border:none;color:#fff;font-size:1.15rem;cursor:pointer;
                               transform:rotate(135deg);
                               box-shadow:0 4px 18px rgba(239,68,68,.4);
                               transition:transform .15s, box-shadow .15s;">
                        <i class="fa-solid fa-phone"></i>
                    </button>
                    <span style="font-size:0.65rem;color:rgba(255,255,255,.3);letter-spacing:.4px;">Decline</span>
                </div>
                <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                    <button onclick="acceptRetryCall()"
                        style="width:58px;height:58px;border-radius:50%;
                               background:linear-gradient(135deg,#22c55e,#15803d);
                               border:none;color:#fff;font-size:1.15rem;cursor:pointer;
                               box-shadow:0 4px 18px rgba(34,197,94,.45);
                               animation:callBtnPulse 2s ease-in-out infinite;
                               transition:transform .15s, box-shadow .15s;">
                        <i class="fa-solid fa-phone"></i>
                    </button>
                    <span style="font-size:0.65rem;color:rgba(255,255,255,.3);letter-spacing:.4px;">Accept</span>
                </div>
            </div>

            <!-- Timer and dismiss -->
            <div style="margin-top:6px;display:flex;flex-direction:column;align-items:center;gap:6px;">
                <span id="retryCountdown"
                      style="font-size:0.68rem;color:rgba(255,255,255,.2);"></span>
                <button onclick="dismissRetryIncoming()"
                    style="background:none;border:none;color:rgba(255,255,255,.25);
                           font-size:0.72rem;cursor:pointer;font-family:'Outfit',sans-serif;
                           transition:color .2s;"
                    onmouseover="this.style.color='rgba(255,255,255,.5)'"
                    onmouseout="this.style.color='rgba(255,255,255,.25)'">
                    Not now
                </button>
            </div>
        </div>
    </div>

    <style>
    @keyframes callBtnPulse{0%,100%{box-shadow:0 4px 18px rgba(34,197,94,.45)}50%{box-shadow:0 4px 30px rgba(34,197,94,.75)}}
    #callDisconnectOverlay{position:relative}
    </style>

    <!-- PeerJS -->
    <script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>

    <script>
        const CURRENT_USER_ID   = <?= intval($userId) ?>;
        const CURRENT_USER_NAME = '<?= e($userName) ?>';
        const ACTIVE_CHAT_ID    = '<?= e($chatId) ?>';
        const CALL_TYPE         = '<?= e($callType) ?>';
        const CALL_MODE         = '<?= e($callMode) ?>';
        const BASE_URL          = '<?= $baseUrl ?>';

        document.addEventListener('DOMContentLoaded', function () {
            // Show video-specific buttons
            if (CALL_TYPE === 'video') {
                ['toggleVideoBtn', 'shareScreenBtn'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = 'flex';
                });
            }
            // Screen share is desktop-only — hide on mobile/iOS/Android
            const hasDisplayMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia);
            if (!hasDisplayMedia) {
                const ssBtn = document.getElementById('shareScreenBtn');
                if (ssBtn) ssBtn.style.display = 'none';
            }
        });
    </script>
    <script src="<?= $baseUrl ?>/public/js/call.js"></script>
</body>
</html>
