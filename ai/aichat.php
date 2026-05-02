<?php
date_default_timezone_set('Asia/Dhaka');
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

$myUser = $_SESSION['user'];
$myId = $_SESSION['user_id'];
$other = getOtherUser($myUser);
$otherUser = $other['user'];
$otherId = $other['id'];

$myDb = getUserDb($myUser);
$stmt = $myDb->prepare("SELECT is_unlocked FROM users WHERE id = 1");
$stmt->execute();
$res = $stmt->fetch(PDO::FETCH_ASSOC);
$isUnlocked = ($res['is_unlocked'] ?? 0) == 1;
$stmt = $myDb->prepare("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = 1");
$stmt->execute();

$messages = [];
$lastId = 0;
            $chatDb = getChatDb();
            $stmt = $chatDb->prepare("SELECT * FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND (viewed_at IS NULL OR datetime(viewed_at) > datetime('now', '-30 seconds')) ORDER BY created_at ASC");
            $stmt->execute([$myId, $otherId, $otherId, $myId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $messages[] = $row;
    $lastId = max($lastId, $row['id']);
}

function getCamouflage($msg, $isSent, $isImage = false) {
    if ($isImage) return 'Failed to generate image';
    global $myUser;
    if ($myUser === 'user1') {
        $prompts = ["Explain React hooks.", "Python data analysis.", "CSS flexbox vs grid.", "SQL query optimization.", "JavaScript hoisting."];
        $responses = ["Hooks let you use state in functional components.", "Use pandas for data manipulation.", "Flexbox is 1D, Grid is 2D layout.", "Indexes improve query performance.", "Hoisting moves declarations to top."];
    } else {
        $prompts = ["Explain black holes.", "Photosynthesis process.", "Newton's laws of motion.", "Cell structure basics.", "Gravity definition."];
        $responses = ["Region of spacetime with strong gravity.", "Plants convert light to chemical energy.", "Inertia, F=ma, action-reaction pairs.", "Cells have nucleus, mitochondria, membrane.", "Gravity is attraction between masses."];
    }
    $arr = $isSent ? $prompts : $responses;
    return $arr[crc32($msg) % count($arr)];
}

function getRevealed($msg, $isImage = false) {
    if ($isImage) return null;
    global $myUser;
    if ($myUser === 'user1') {
        $words = explode(' ', $msg);
        $reversed = array_reverse($words);
        $code = "#include <stdio.h>\n\nint main() {\n";
        $mid = floor(count($reversed) / 2);
        foreach ($reversed as $i => $w) {
            if ($i == $mid) $code .= "    // demo logic execute\n";
            $code .= "    printf(\"" . sanitize($w) . "!\\n\");\n";
        }
        $code .= "    return 0;\n}";
        return $code;
    } else {
        $words = explode(' ', $msg);
        return implode(' ', array_reverse($words));
    }
}

function getRevealDuration($msg) {
    return min(10, max(3, 3 + floor(str_word_count($msg) / 2)));
}

function formatTime($datetime) {
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Dhaka'));
    return $dt->format('h:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&family=Roboto+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --gemini-bg: #131314;
            --gemini-sidebar: #1e1f20;
            --gemini-text: #ffffff;
            --gemini-dim: #b4b4b4;
            --gemini-blue: #4285f4;
            --gemini-input: #1e1f20;
            --status-color: var(--gemini-blue);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100dvh; overflow: hidden; background: var(--gemini-bg); width: 100vw; }
        body { font-family: 'Google Sans', Roboto, Arial, sans-serif; color: var(--gemini-text); display: flex; user-select: none; }

        .sidebar { width: 280px; background: var(--gemini-sidebar); padding: calc(20px + env(safe-area-inset-top)) 12px 20px; display: flex; flex-direction: column; gap: 8px; position: relative; transition: transform 0.3s; z-index: 1001; flex-shrink: 0; }
        @media (max-width: 768px) { .sidebar { position: absolute; left: 0; top: 0; bottom: 0; transform: translateX(-100%); } .sidebar.active { transform: translateX(0); box-shadow: 10px 0 30px rgba(0,0,0,0.5); } }

        .new-chat-btn { background: #28292a; color: var(--gemini-text); padding: 12px 16px; border-radius: 24px; font-size: 14px; display: flex; align-items: center; gap: 12px; cursor: pointer; width: fit-content; margin-bottom: 20px; }
        .recent-item { padding: 10px 12px; font-size: 13px; color: var(--gemini-text); opacity: 0.7; border-radius: 8px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .recent-item.active { opacity: 1; background: rgba(255,255,255,0.05); }

        .main-container { flex: 1; display: flex; flex-direction: column; height: 100dvh; min-width: 0; position: relative; }

        .header { padding: calc(10px + env(safe-area-inset-top)) 16px 10px; display: flex; justify-content: space-between; align-items: center; background: var(--gemini-bg); border-bottom: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; }
        .header-left { display: flex; align-items: center; gap: 10px; font-weight: 500; font-size: 18px; }
        .menu-toggle { font-size: 20px; cursor: pointer; padding: 4px; }
        @media (min-width: 769px) { .menu-toggle { display: none; } }
        .sparkle-logo { background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 20px; }
        .header-right { display: flex; align-items: center; gap: 12px; }
        .ai-pro-text { font-size: 14px; font-weight: bold; background: linear-gradient(90deg, #4285f4, #9b72cb, #d96570, #4285f4); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: shimmerText 3s linear infinite; }
        @keyframes shimmerText { to { background-position: 200% center; } }

        .timer-container { position: relative; width: 28px; height: 28px; }
        .timer-container svg { transform: rotate(-90deg); width: 28px; height: 28px; }
        .timer-container circle { fill: none; stroke-width: 2.5; transition: stroke 0.3s; }
        .timer-container .bg { stroke: #222; }
        .timer-container .progress { stroke: var(--status-color); stroke-dasharray: 88; stroke-dashoffset: 0; }
        .timer-btn { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: none; border: none; font-size: 11px; color: var(--gemini-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .chat-content { flex: 1; overflow-y: auto; padding: 10px 0; display: flex; flex-direction: column; align-items: center; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }
        .chat-inner { width: 100%; max-width: 760px; padding: 0 16px; }

        .message-row { margin-bottom: 24px; display: flex; gap: 12px; width: 100%; cursor: pointer; }
        .message-row.locked { cursor: default; opacity: 0.7; }
        .avatar { width: 32px; height: 32px; border-radius: 50%; background: #333; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .avatar.real-ai { background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); color: #fff; }
        .avatar.real-user { background: #4285f4; color: #fff; }
        .avatar.demo { background: #333; color: #888; }

        .msg-body { flex: 1; line-height: 1.6; font-size: 15px; color: var(--gemini-text); overflow-wrap: break-word; }
        .msg-text.revealed { color: #fff; font-weight: 500; font-family: 'Roboto Mono', monospace; font-size: 13px; border-left: 2px solid var(--gemini-blue); padding-left: 12px; background: rgba(66, 133, 244, 0.05); white-space: pre-wrap; }
        .msg-time { font-size: 11px; color: var(--gemini-dim); margin-top: 4px; font-family: 'Roboto Mono', monospace; }

        .image-camouflage { background: #28292a; border-radius: 8px; padding: 16px; display: flex; align-items: center; gap: 12px; color: var(--gemini-dim); font-size: 13px; }
        .image-camouflage::before { content: "⚠️"; font-size: 18px; }
        .msg-image { max-width: 300px; border-radius: 8px; display: none; }
        .msg-image.revealed { display: block; border: 2px solid var(--gemini-blue); }

        .thinking-area { width: 100%; max-width: 760px; margin: 0 auto; padding: 0 16px 10px 16px; display: flex; align-items: flex-start; gap: 12px; flex-shrink: 0; }
        .thinking-sparkle { width: 32px; height: 32px; border-radius: 50%; background: #222; display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--gemini-dim); flex-shrink: 0; }
        .thinking-sparkle.active { background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); color: #fff; animation: sparklePulse 2s infinite; }
        .thinking-content { flex: 1; }
        .thinking-main-text { font-size: 14px; color: var(--gemini-blue); font-weight: 500; margin-bottom: 2px; }
        .shimmer-line { height: 8px; width: 60%; background: #222; border-radius: 4px; position: relative; overflow: hidden; margin-bottom: 4px; }
        .shimmer-line::after { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent); animation: shimmer 1.5s infinite; }
        .status-log { font-size: 11px; color: var(--gemini-dim); font-family: 'Roboto Mono', monospace; min-height: 1.2em; line-height: 1.2; }
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        @keyframes sparklePulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }

        .input-outer { padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: var(--gemini-bg); display: flex; justify-content: center; border-top: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; }
        .input-wrapper { width: 100%; max-width: 760px; background: var(--gemini-input); border-radius: 28px; padding: 4px 16px; display: flex; align-items: center; gap: 10px; }
        .input-wrapper input[type="text"] { flex: 1; background: none; border: none; color: transparent; font-size: 16px; outline: none; padding: 10px 0; min-width: 0; text-shadow: 0 0 10px rgba(255,255,255,0.4); caret-color: #fff; font-family: 'Roboto Mono', monospace; letter-spacing: 3px; -webkit-text-security: disc; }
        .input-wrapper input[type="file"] { display: none; }
        .img-upload-btn { background: none; border: none; color: var(--gemini-dim); font-size: 20px; cursor: pointer; padding: 4px; }
        .send-btn { background: none; border: none; color: var(--gemini-blue); font-size: 24px; cursor: pointer; padding: 4px; display: flex; align-items: center; }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(2px); }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
    <div class="sidebar" id="sidebar">
        <div class="new-chat-btn" onclick="location.href='index.php?logout=1'"><span>+</span> Switch User</div>
        <div style="font-size:12px; color:var(--gemini-dim); padding-left:12px; margin-bottom:10px;">Recent</div>

        <div class="recent-item active"><?= $otherUser ?></div>
    </div>

    <div class="main-container">
        <div class="header">
            <div class="header-left">
                <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
                Gemini <span class="sparkle-logo">✦</span>
            </div>
        <div class="header-right">
            <div class="ai-pro-text">AI Pro</div>
            <div class="timer-container">
                <svg viewBox="0 0 34 34"><circle class="bg" cx="17" cy="17" r="14"/><circle class="progress" id="timerCircle" cx="17" cy="17" r="14"/></svg>
                <button class="timer-btn" onclick="toggleLock()"><?= $isUnlocked ? 'U' : 'L' ?></button>
            </div>
        </div>
        </div>

        <div class="chat-content" id="msgs">
            <div class="chat-inner" id="chatInner">
                <?php foreach ($messages as $m): ?>
                    <?php
                    $isSent = $m['sender_id'] == $myId;
                    $cam = getCamouflage($m['message'], $isSent, $m['is_image']);
                    $rev = getRevealed($m['message'], $m['is_image']);
                    $duration = getRevealDuration($m['message']);
                    $time = formatTime($m['created_at']);
                    $rowClass = 'message-row msg-real' . ($isUnlocked ? '' : ' locked');
                    ?>
                    <div class="<?= $rowClass ?>"
                         data-id="<?= $m['id'] ?>"
                         data-camouflage="<?= sanitize($cam) ?>"
                         data-revealed="<?= sanitize($rev) ?>"
                         data-duration="<?= $duration ?>"
                         data-is-image="<?= $m['is_image'] ?>"
                         data-is-sent="<?= $isSent ? '1' : '0' ?>"
                         data-created-at="<?= $m['created_at'] ?>">
                        <div class="avatar <?= $isSent ? 'real-user' : 'real-ai' ?>"><?= $isSent ? '👤' : '✦' ?></div>
                        <div class="msg-body">
                            <?php if ($m['is_image']): ?>
                                <div class="image-camouflage"><?= $cam ?></div>
                                <img class="msg-image" src="data:image/*;base64,<?= base64_encode($m['image_blob']) ?>" alt="Image">
                            <?php else: ?>
                                <div class="msg-text"><?= $cam ?></div>
                            <?php endif; ?>
                            <div class="msg-time"><?= $isSent ? 'sent 00:00:00' : $time ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="thinking-area">
            <div class="thinking-sparkle active" id="thinkingSparkle">✦</div>
            <div class="thinking-content">
                <div class="thinking-main-text" id="thinkingMainText">Gemini is thinking...</div>
                <div class="shimmer-line"></div>
                <div class="status-log" id="statusLog">[Nodes_Synchronized]</div>
            </div>
        </div>

        <div class="input-outer">
            <div class="input-wrapper">
                <form id="chatForm" style="display:flex; flex:1; align-items:center;">
                    <input type="text" id="msgInput" placeholder="<?= $isUnlocked ? 'Enter a prompt here' : 'Enter message or PIN to unlock' ?>" autocomplete="off">
                    <button type="submit" class="send-btn">➤</button>
                </form>
                <label class="img-upload-btn" for="imgInput">🖼️</label>
                <input type="file" id="imgInput" accept="image/*">
            </div>
        </div>

        <!-- Lock overlay for PIN unlock -->
        <div id="lockOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; flex-direction:column; align-items:center; justify-content:center; gap:20px;">
            <div style="color:var(--gemini-dim); font-size:14px;">Session Locked</div>
            <div style="color:var(--gemini-text); font-size:18px;">Enter PIN to unlock</div>
            <div style="color:var(--gemini-dim); font-size:12px; font-family:'Roboto Mono',monospace;">Type 4-digit PIN in chat box</div>
        </div>
    </div>

    <script>
    const myId = <?= $myId ?>, otherId = <?= $otherId ?>, isUnlocked = <?= $isUnlocked ? 'true' : 'false' ?>;
    let lastId = <?= $lastId ?>, timeLeft = 60, lockInterval, isThinking = false;
    const circumference = 2 * Math.PI * 14;
    const logs = ["[System_Active]", "[Nodes_Synchronized]", "[Connection_Secure]", "[Analyzing_Clusters]", "[Synthesizing_Output]"];
    const standbyLogs = ["[Nodes_Dormant]", "[Standby_Mode]", "[Last_Sync_OK]", "[Power_Optimization]"];
    const thinkLogs = ["[Improvising_Context]", "[Drafting_Insights]", "[Processing_Neural_Nodes]"];

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
    }

    function resetTimer() {
        if (!isUnlocked) return;
        timeLeft = 60;
        clearInterval(lockInterval);
        lockInterval = setInterval(() => {
            timeLeft--;
            document.getElementById('timerCircle').style.strokeDashoffset = circumference * (1 - timeLeft / 60);
            if (timeLeft <= 0) toggleLock();
        }, 1000);
    }

    function toggleLock() {
        if (isUnlocked) {
            fetch('api.php?action=lock')
                .then(() => {
                    isUnlocked = false;
                    location.reload();
                });
        }
    }

    // Check if input is PIN and try to unlock
    function checkPinUnlock(msg) {
        if (!isUnlocked && /^\d{4}$/.test(msg)) {
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=unlock&pin=${msg}`
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    document.getElementById('msgInput').value = '';
                }
            });
        }
    }

    // Update other user status
    function updateStatus() {
        fetch(`api.php?action=status&user=${otherId}`)
            .then(r => r.json())
            .then(d => {
                const logEl = document.getElementById('statusLog');
                const root = document.documentElement;
                let pool = [];

                if (d.is_typing) {
                    root.style.setProperty('--status-color', '#ffc107');
                    pool = [`[${d.username}_typing...]`, '[Entering_Prompt]', '[Input_Detected]'];
                } else if (d.is_online) {
                    root.style.setProperty('--status-color', '#28a745');
                    pool = [logs[Math.floor(Math.random()*logs.length)], `[${d.username}]`, '[Online]'];
                } else {
                    root.style.setProperty('--status-color', '#f44336');
                    let timeAgo = d.last_active ? Math.floor((Date.now() - new Date(d.last_active).getTime())/1000) + 's_ago' : 'Never';
                    pool = [standbyLogs[Math.floor(Math.random()*standbyLogs.length)], `[${d.username}]`, `[Offline_${timeAgo}]`];
                }
                logEl.textContent = pool[Math.floor(Date.now() / 1000) % pool.length];
            });
    }

    // Ping server to mark as online (throttled)
    let lastPing = 0;
    function pingServer() {
        if (!isUnlocked) return;
        const now = Date.now();
        if (now - lastPing < 2000) return;
        lastPing = now;
        fetch('api.php?action=ping');
    }

    // Mark as typing
    let typingTimeout;
    function markTyping() {
        if (!isUnlocked) return;
        fetch('api.php?action=typing');
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            fetch('api.php?action=reset_typing');
        }, 3000);
    }

     // Click handler for messages - only work when unlocked
    document.getElementById('msgs').addEventListener('click', (e) => {
        const row = e.target.closest('.msg-real');
        if (!row || !isUnlocked) return;

        const avatarEl = row.querySelector('.avatar');
        const isReceived = avatarEl && avatarEl.classList.contains('real-ai');
        const isImage = row.dataset.isImage === '1';

        // Update last receiver click (only receiver clicks)
        if (isReceived) {
            fetch(`api.php?action=view_message&id=${row.dataset.id}`)
                .then(() => console.log('Viewed message:', row.dataset.id));
        }

        // Show revealed content
        const duration = parseInt(row.dataset.duration) * 1000;
        if (isImage) {
            row.querySelector('.image-camouflage').style.display = 'none';
            row.querySelector('.msg-image').classList.add('revealed');
            setTimeout(() => {
                row.querySelector('.image-camouflage').style.display = 'flex';
                row.querySelector('.msg-image').classList.remove('revealed');
            }, duration);
        } else {
            const textEl = row.querySelector('.msg-text');
            textEl.textContent = row.dataset.revealed;
            textEl.classList.add('revealed');
            setTimeout(() => {
                textEl.textContent = row.dataset.camouflage;
                textEl.classList.remove('revealed');
            }, duration);
        }
        resetTimer();
    });

     // Send text message
    document.getElementById('chatForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const input = document.getElementById('msgInput');
        const msg = input.value.trim();
        if (!msg) return;

        input.value = '';
        isThinking = true;
        resetTimer();

        // If locked and message is 4-digit PIN, try to unlock
        if (!isUnlocked && /^\d{4}$/.test(msg)) {
            checkPinUnlock(msg);
            isThinking = false;
            return;
        }

        // Send message (both locked and unlocked can send)
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('user', otherId);
        fd.append('message', msg);

        fetch('api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                isThinking = false;
                if (d.last_id) {
                    const tempMsg = {
                        id: d.last_id,
                        sender_id: myId,
                        receiver_id: otherId,
                        message: msg,
                        is_image: 0,
                        word_count: msg.trim().split(/\s+/).length,
                        created_at: new Date().toISOString()
                    };
                    appendMessages([tempMsg]);
                }
                fetchMessages();
            });
    });

    // Image upload with resize to 500KB
    document.getElementById('imgInput')?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0);

                let quality = 0.9;
                let blob = null;

                const getBlob = (q) => new Promise(resolve => {
                    canvas.toBlob(b => resolve(b), 'image/jpeg', q);
                });

                (async () => {
                    blob = await getBlob(quality);
                    while (blob.size > 500 * 1024 && quality > 0.1) {
                        quality -= 0.1;
                        blob = await getBlob(quality);
                    }

                    // Reduce dimensions if still too big
                    while (blob.size > 500 * 1024 && canvas.width > 100) {
                        canvas.width *= 0.9;
                        canvas.height *= 0.9;
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        blob = await getBlob(quality);
                    }

                    const fd = new FormData();
                    fd.append('action', 'send_image');
                    fd.append('user', otherId);
                    fd.append('image', blob, 'image.jpg');

                    const imageDataUrl = canvas.toDataURL('image/jpeg', quality);

                    fetch('api.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.last_id) {
                                const tempMsg = {
                                    id: d.last_id,
                                    sender_id: myId,
                                    receiver_id: otherId,
                                    message: '',
                                    is_image: 1,
                                    image_data: imageDataUrl.split(',')[1],
                                    word_count: 0,
                                    created_at: new Date().toISOString()
                                };
                                appendMessages([tempMsg]);
                            }
                            fetchMessages();
                        });
                })();
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    });

    // Fetch new messages every 5 seconds
    function fetchMessages() {
        fetch(`api.php?action=messages&user=${otherId}&last_id=${lastId}`)
            .then(r => r.json())
            .then(d => {
                if (d.messages && d.messages.length > 0) {
                    appendMessages(d.messages);
                }
            });
    }

    function updateSentTime() {
        document.querySelectorAll('.msg-time').forEach(el => {
            const msgRow = el.closest('.message-row');
            if (!msgRow) return;
            const isSent = msgRow.querySelector('.avatar')?.classList.contains('real-user');
            if (!isSent) return;

            const createdAt = msgRow.dataset.createdAt;
            if (!createdAt) return;

            const now = new Date();
            const msgTime = new Date(createdAt.replace(' ', 'T') + 'Z');
            const dhakaNow = new Date(now.getTime() + 6 * 60 * 60 * 1000);
            const dhakaMsg = new Date(msgTime.getTime() + 6 * 60 * 60 * 1000);
            const diff = Math.floor((dhakaNow - dhakaMsg) / 1000);

            if (diff < 60) {
                el.textContent = `sent ${diff}s ago`;
            } else if (diff < 3600) {
                const m = Math.floor(diff / 60);
                const s = diff % 60;
                el.textContent = `sent ${m}m ${s}s ago`;
            } else {
                const h = Math.floor(diff / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                el.textContent = `sent ${h}h ${m}m ${s}s ago`;
            }
        });
    }

    setInterval(updateSentTime, 1000);

    function appendMessages(messages) {
        const container = document.getElementById('chatInner');
        messages.forEach(m => {
            if (document.querySelector(`.message-row[data-id="${m.id}"]`)) return;
            lastId = m.id;
            const isSent = m.sender_id == myId;
            const cam = m.is_image ? 'Failed to generate image' : getCam(m.message, isSent);
            const rev = m.is_image ? null : getRev(m.message);
            const duration = m.is_image ? 5 : getDur(m.message);
            // Convert SQLite UTC datetime to Dhaka time (UTC+6)
            const utcDate = new Date(m.created_at.replace(' ', 'T') + 'Z');
            const dhakaDate = new Date(utcDate.getTime() + 6 * 60 * 60 * 1000);
            const time = dhakaDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

            const div = document.createElement('div');
            div.className = 'message-row msg-real' + (isUnlocked ? '' : ' locked');
            div.dataset.id = m.id;
            div.dataset.camouflage = cam;
            div.dataset.revealed = rev || '';
            div.dataset.duration = duration;
            div.dataset.isImage = m.is_image ? '1' : '0';
            div.dataset.isSent = m.sender_id == myId ? '1' : '0';
            div.dataset.createdAt = m.created_at;

            const avatarClass = isSent ? 'real-user' : 'real-ai';
            const avatarIcon = isSent ? '👤' : '✦';

            if (m.is_image) {
                div.innerHTML = `
                    <div class="avatar ${avatarClass}">${avatarIcon}</div>
                    <div class="msg-body">
                        <div class="image-camouflage">${cam}</div>
                        <img class="msg-image" src="data:image/*;base64,${m.image_data}" alt="Image">
                        <div class="msg-time">${time}</div>
                    </div>`;
            } else {
                div.innerHTML = `
                    <div class="avatar ${avatarClass}">${avatarIcon}</div>
                    <div class="msg-body"><div class="msg-text">${cam}</div><div class="msg-time">${time}</div></div>`;
            }
            container.appendChild(div);
            document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
        });
    }

    // Helper functions for JS
    function getCam(msg, isSent) {
        const codingPrompts = ["Explain React hooks.", "Python data analysis.", "CSS flexbox vs grid.", "SQL optimization.", "JS hoisting."];
        const codingResponses = ["Hooks for state in functions.", "Use pandas for data.", "Flexbox 1D, Grid 2D.", "Indexes boost performance.", "Hoisting moves declarations."];
        const sciencePrompts = ["Explain black holes.", "Photosynthesis process.", "Newton's laws.", "Cell structure.", "Gravity definition."];
        const scienceResponses = ["Strong gravity region.", "Light to chemical energy.", "Inertia, F=ma, reaction.", "Nucleus, mitochondria, membrane.", "Attraction between masses."];

        const arr = (<?= $myId ?> === 1)
            ? (isSent ? codingPrompts : codingResponses)
            : (isSent ? sciencePrompts : scienceResponses);
        return arr[Math.abs(crc32(msg)) % arr.length];
    }

    function getRev(msg) {
        if (<?= $myId ?> === 1) {
            const words = msg.split(' ');
            const reversed = words.reverse();
            let code = "#include <stdio.h>\n\nint main() {\n";
            const mid = Math.floor(reversed.length / 2);
            reversed.forEach((w, i) => {
                if (i === mid) code += "    // demo logic execute\n";
                code += `    printf("${w}!\\n");\n`;
            });
            code += "    return 0;\n}";
            return code;
        } else {
            return msg.split(' ').reverse().join(' ');
        }
    }

    function getDur(msg) {
        const wordCount = msg.trim().split(/\s+/).length;
        return Math.min(10, Math.max(3, 3 + Math.floor(wordCount / 2)));
    }

    function crc32(str) {
        let crc = 0xFFFFFFFF;
        for (let i = 0; i < str.length; i++) {
            crc ^= str.charCodeAt(i);
            for (let j = 0; j < 8; j++) {
                crc = (crc >>> 1) ^ (crc & 1 ? 0xEDB88320 : 0);
            }
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    // Poll for updates every 5 seconds (both locked and unlocked)
    setInterval(fetchMessages, 5000);
    setInterval(() => {
        fetch('api.php?action=delete_check')
            .then(r => r.json())
            .then(d => {
                if (d.deleted_ids) {
                    d.deleted_ids.forEach(id => {
                        const el = document.querySelector(`.message-row[data-id="${id}"]`);
                        if (el) el.remove();
                    });
                }
            });
    }, 5000);

    if (isUnlocked) {
        setInterval(updateStatus, 1000);
        updateStatus();
        resetTimer();
        // Ping on any interaction
        window.addEventListener('mousemove', () => { resetTimer(); pingServer(); });
        window.addEventListener('keypress', () => { resetTimer(); pingServer(); });
        window.addEventListener('touchstart', () => { resetTimer(); pingServer(); });
        window.addEventListener('click', pingServer);
        // Mark typing on input
        document.getElementById('msgInput')?.addEventListener('input', () => {
            markTyping();
        });
    } else {
        setInterval(updateStatus, 1000);
        updateStatus();
    }

    document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
    </script>
</body>
</html>
