<?php $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($chatTitle) ?> - Kotha Secure Messenger</title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/public/img/icon.svg">
    <link rel="shortcut icon" href="<?= $baseUrl ?>/public/img/icon.svg">
    <meta name="theme-color" content="#00f2fe">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/style.css?v=4">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/logo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- App Container with .chat-open class for responsive layout on mobile -->
    <div class="app-container chat-open">
        
        <!-- Sidebar Layout -->
        <?php include BASE_DIR . '/src/Views/layout/sidebar.php'; ?>

        <!-- Active Chat viewport -->
        <div class="main-viewport" id="activeChatViewport">
            
            <?php
                $hPalette = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1','#0ea5e9','#14b8a6'];
                $partnerId = 0;
                if ($chatType === 'direct') {
                    $userId = $_SESSION['user_id'] ?? 0;
                    foreach ($participants as $p) {
                        if ($p['id'] != $userId) {
                            $partnerId = intval($p['id']);
                            break;
                        }
                    }
                    if ($partnerId === 0 && !empty($participants)) {
                        $partnerId = intval($participants[0]['id']);
                    }
                }
                $hBg = $chatType === 'group'
                    ? 'linear-gradient(135deg,#7c3aed,#a78bfa)'
                    : $hPalette[$partnerId % count($hPalette)];
            ?>
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-header-info">
                    <button class="header-btn back-btn" onclick="backToSidebar()" style="margin-right:2px;"><i class="fa-solid fa-arrow-left"></i></button>
                    <div class="chat-header-avatar" style="background:<?= e($hBg) ?>;">
                        <?= $chatType === 'group'
                            ? '<i class="fa-solid fa-users" style="font-size:.75rem;"></i>'
                            : '<i class="fa-solid fa-user" style="font-size:.75rem;"></i>' ?>
                    </div>
                    <div style="min-width:0;">
                        <div class="chat-header-title" id="activeChatTitle"><?= e($chatTitle) ?></div>
                        <div class="chat-header-status" id="activeChatStatus"><?= ucfirst(e($chatType)) ?> Chat</div>
                    </div>
                </div>
                
                <div class="header-actions">
                    <!-- WebRTC Call triggers -->
                    <?php if ($chatType === 'direct'): ?>
                        <button class="header-btn call-btn" onclick="initiateCall('audio')" title="Voice Call"><i class="fa-solid fa-phone"></i></button>
                        <button class="header-btn call-btn" onclick="initiateCall('video')" title="Video Call"><i class="fa-solid fa-video"></i></button>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?>/logout" class="header-btn" title="Sign Out"><i class="fa-solid fa-right-from-bracket"></i></a>
                </div>
            </div>

            <!-- Chat Messages Log Container -->
            <div class="messages-container" id="messagesContainer">
                <!-- Messages will load dynamically via JS -->
            </div>
            <!-- Voice Recording Control Panel (HMS timing & visual bouncing audio wave) -->
            <div class="voice-record-container" id="voiceRecordContainer" style="display: none; background: var(--bg-header); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 12px 16px; z-index: 10; align-items: center; justify-content: space-between; gap: 15px; animation: slideUp 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span class="recording-indicator" id="recordingIndicator" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-circle" id="recordingDot" style="color: #ef4444; font-size: 0.65rem; animation: blink 1s infinite; display: none;"></i>
                        <span id="recordingStatusText" style="font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">Voice Recorder (Ready)</span>
                    </span>
                    <span id="recordingTimerHMS" style="font-family: monospace; font-size: 0.95rem; font-weight: 700; color: var(--accent); background: rgba(0, 242, 254, 0.1); padding: 3px 10px; border-radius: 6px; border: 1px solid rgba(0, 242, 254, 0.15);">00:00:00</span>
                    
                    <!-- Bouncing Audio Wave Animation -->
                    <div class="audio-wave-anim" id="audioWaveAnim" style="display: none;">
                        <div class="wave-bar" style="animation: bounceWave 0.5s ease-in-out infinite alternate;"></div>
                        <div class="wave-bar" style="animation: bounceWave 0.5s ease-in-out infinite alternate 0.15s;"></div>
                        <div class="wave-bar" style="animation: bounceWave 0.5s ease-in-out infinite alternate 0.3s;"></div>
                        <div class="wave-bar" style="animation: bounceWave 0.5s ease-in-out infinite alternate 0.08s;"></div>
                        <div class="wave-bar" style="animation: bounceWave 0.5s ease-in-out infinite alternate 0.22s;"></div>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button class="btn btn-primary btn-sm" id="btnStartRecord" onclick="startVoiceRecordingAction()" style="padding: 6px 14px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; border-radius: 8px; cursor: pointer; transition: var(--transition); background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); border: none; color: #0b141a; font-weight: 600;"><i class="fa-solid fa-microphone"></i> Start</button>
                    <button class="btn btn-danger btn-sm" id="btnStopSendRecord" onclick="stopAndSendVoiceRecordingAction()" style="padding: 6px 14px; font-size: 0.8rem; display: none; align-items: center; gap: 6px; border-radius: 8px; cursor: pointer; transition: var(--transition); background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); border: none; color: white; font-weight: 600;"><i class="fa-solid fa-paper-plane"></i> Stop & Send</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelRecord" onclick="cancelVoiceRecordingAction()" style="padding: 6px 14px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; border-radius: 8px; cursor: pointer; transition: var(--transition); background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-primary);"><i class="fa-solid fa-xmark"></i> Close</button>
                </div>
            </div>

            <!-- Detailed Progress Stats Panel (Shows on top of the text input) -->
            <div class="upload-progress-container" id="uploadProgressContainer" style="display: none; background: var(--bg-panel); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); padding: 10px 16px; z-index: 10; animation: slideUp 0.25s ease;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 0.75rem; margin-bottom: 6px; font-weight: 600; font-family: 'Outfit', sans-serif;">
                    <span id="uploadProgressStats" style="color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-cloud-arrow-up" style="color: #00f2fe;"></i>
                        <span>Preparing...</span>
                    </span>
                    <span id="uploadProgressTime" style="color: var(--text-secondary); display: flex; align-items: center; gap: 5px;">
                        <i class="fa-regular fa-clock"></i>
                        <span>Calculating...</span>
                    </span>
                </div>
                <div style="background: rgba(255,255,255,0.03); border-radius: 6px; height: 6px; width: 100%; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); position: relative;">
                    <div class="upload-progress-bar" id="uploadProgressBar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #00f2fe 0%, #4facfe 100%); box-shadow: 0 0 8px rgba(0, 242, 254, 0.4); transition: width 0.1s linear; border-radius: 6px;"></div>
                </div>
            </div>

            <!-- Chat Input Area (Wrapper containing Media Status on top of Input Box) -->
            <div class="chat-input-area" style="position: relative; display: flex; flex-direction: column; background: var(--bg-panel); border-top: 1px solid var(--border-color);">
                
                <!-- Chat Input Bar -->
                <div class="chat-input-bar" style="border-top: none;">
                    <button class="input-action-btn" id="attachToggleBtn" title="Attach file"><i class="fa-solid fa-paperclip"></i></button>
                    
                    <!-- Attachment Popover menu -->
                    <div class="attach-menu" id="attachMenu">
                        <div class="attach-menu-item" onclick="triggerFileSelect('image')">
                            <i class="fa-solid fa-image"></i> Image
                        </div>
                        <div class="attach-menu-item" onclick="triggerFileSelect('video')">
                            <i class="fa-solid fa-video"></i> Video
                        </div>
                        <div class="attach-menu-item" onclick="triggerAudioRecord()">
                            <i class="fa-solid fa-microphone"></i> Voice Record
                        </div>
                        <div class="attach-menu-item" onclick="openCameraModal()">
                            <i class="fa-solid fa-camera"></i> Camera Capture
                        </div>
                        <div class="attach-menu-item" onclick="toggleInputBlur()">
                            <i class="fa-solid fa-eye-slash"></i> Toggle Blur
                        </div>
                    </div>

                    <div class="chat-input-container">
                        <input type="text" class="chat-input-box" id="chatInput" placeholder="Type a message, or type 4-digit PIN to reveal..." autocomplete="off">
                    </div>
                    
                    <button class="input-action-btn send-btn" id="sendMsgBtn"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>

            <!-- Hidden input files -->
            <input type="file" id="fileAttachInput" style="display:none" onchange="handleFileSelected(this)">

        </div>

    </div>

    <!-- PIN Unlock Prompt Modal -->
    <div class="pin-modal" id="pinModal" style="display:none;">
        <div class="pin-card">
            <i class="fa-solid fa-lock-open" style="font-size: 2.5rem; color: var(--accent); margin-bottom:15px;"></i>
            <h3>Unlock Chat Content</h3>
            <p>Enter your 4-digit corporate PIN to unlock camouflaged messages.</p>
            <div class="pin-inputs">
                <input type="password" class="pin-field" maxlength="1" oninput="movePinCursor(this, 2)" id="pinDigit1">
                <input type="password" class="pin-field" maxlength="1" oninput="movePinCursor(this, 3)" id="pinDigit2">
                <input type="password" class="pin-field" maxlength="1" oninput="movePinCursor(this, 4)" id="pinDigit3">
                <input type="password" class="pin-field" maxlength="1" oninput="movePinCursor(this, 5)" id="pinDigit4">
            </div>
            <div id="pinErrorMessage" style="color:#ef4444; font-size:0.8rem; margin-bottom:15px; display:none;">Invalid PIN. Please try again.</div>
            <button class="btn btn-primary" onclick="submitPinModal()">Unlock Messages</button>
            <button class="btn btn-secondary" onclick="closePinModal()" style="margin-top: 10px;">Cancel</button>
        </div>
    </div>

    <!-- Camera Capture Modal -->
    <div class="pin-modal" id="cameraModal" style="display:none; z-index: 2000;">
        <div class="pin-card" style="max-width: 640px; width: 90%;">
            <h3 style="margin-bottom: 15px;">Camera Capture</h3>
            
            <div style="background: #000; border-radius: 12px; overflow: hidden; width: 100%; aspect-ratio: 4/3; margin-bottom: 15px; position: relative;">
                <video id="cameraPreview" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
                <div id="cameraRecordingTimer" style="display: none; position: absolute; top: 15px; left: 15px; background: rgba(239, 68, 68, 0.85); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-family: monospace;">REC 00:00</div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; width: 100%; flex-wrap: wrap;">
                <button class="btn btn-primary" id="btnCapturePhoto" onclick="capturePhoto()"><i class="fa-solid fa-camera"></i> Snap Photo</button>
                <button class="btn btn-accent" id="btnRecordVideo" onclick="toggleVideoRecording()"><i class="fa-solid fa-video"></i> Start Record</button>
                <button class="btn btn-secondary" onclick="closeCameraModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Client-Side Configuration Script -->
    <script>
        const CURRENT_USER_ID = <?= intval($_SESSION['user_id']) ?>;
        const ACTIVE_CHAT_ID = '<?= e($chatId) ?>';
        const BASE_URL = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") ?>';
        window.defaultChatStatus = '<?= e($chatType === 'group' ? 'Group Chat (' . count($participants) . ' members)' : 'Offline') ?>';
    </script>
    <script src="<?= $baseUrl ?>/public/js/app.js?v=6"></script>
    <script src="<?= $baseUrl ?>/public/js/sse.js?v=2"></script>
</body>
</html>
