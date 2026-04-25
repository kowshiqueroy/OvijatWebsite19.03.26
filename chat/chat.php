<?php
session_start();
require_once 'db.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$chatWithId = (int)($_GET['user'] ?? 0);
if (!$chatWithId) { header('Location: users.php'); exit; }

$myId = $_SESSION['user_id'];
updateLastActive($myId);

$chatUser = getUserById($chatWithId);
if (!$chatUser) { header('Location: users.php'); exit; }
$nickname = getNickname($myId, $chatWithId);
$chatDisplayName = $nickname ?: $chatUser['display_name'];
$chatAvatarEmoji = $chatUser['avatar_emoji'] ?: '👤';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die("Invalid CSRF token"); }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        $msg = trim($_POST['message'] ?? '');
        $replyTo = (int)($_POST['reply_to'] ?? 0);
        if ($msg) {
            if (isPinValid($myId, $msg)) {
                unlockChat($myId, $chatWithId);
            } else {
                saveMessage($myId, $chatWithId, $msg, 'text', $replyTo);
            }
        }
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image']['tmp_name'];
            $name = basename($_FILES['image']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = 'uploads/' . $newName;
                if (move_uploaded_file($tmpName, $dest)) {
                    saveMessage($myId, $chatWithId, $dest, 'image', 0);
                }
            }
        }
        header("Location: chat.php?user=$chatWithId"); exit;
    }
    if ($action === 'lock') {
        lockChat($myId, $chatWithId);
        header("Location: chat.php?user=$chatWithId"); exit;
    }
    if ($action === 'set_nickname') {
        $newNick = sanitize($_POST['nickname'] ?? '');
        setNickname($myId, $chatWithId, $newNick);
        header("Location: chat.php?user=$chatWithId"); exit;
    }
    if ($action === 'vanish') {
        vanishChats($myId, $chatWithId);
        header("Location: chat.php?user=$chatWithId"); exit;
    }
    if ($action === 'typing') {
        setTyping($myId, $chatWithId);
        echo "OK"; exit;
    }
}

$isUnlocked = isChatUnlocked($myId, $chatWithId);
markMessagesAsRead($myId, $chatWithId);
$messages = getMessages($myId, $chatWithId);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= sanitize($chatDisplayName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f4f7fb; 
            color: #1a1a1a;
            user-select: none; /* Disable text copying */
            -webkit-user-select: none;
            overflow-x: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        
        /* Unlocked Privacy Theme */
        body.unlocked { background: #0b0c0d; color: #f0f0f0; }
        body.unlocked .header { background: #161718; border-bottom: 1px solid #2d2d2d; }
        body.unlocked .header .info h3 { color: #ffffff !important; }
        body.unlocked .header .info p { color: #8a8a8a !important; }
        body.unlocked .header .back { color: #f0f0f0; }
        body.unlocked .messages { background: #0b0c0d; }
        body.unlocked .msg.sent { background: #1a73e8; color: #ffffff; }
        body.unlocked .msg.received { background: #242526; color: #ffffff; }
        body.unlocked .msg-content { color: inherit; }
        body.unlocked .input-area { background: #161718; border-top: 1px solid #2d2d2d; }
        body.unlocked .input-area input { background: #242526; border: 1px solid #3a3b3c; color: #ffffff; }
        body.unlocked .msg-time { color: #b0b3b8; }
        body.unlocked .tick { color: #b0b3b8; }
        body.unlocked .tick.read { color: #ffffff; }

        .header { background: #fff; color: #1a1a1a; padding: 12px 16px; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid #e0e6ed; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .header .back { font-size: 20px; cursor: pointer; margin-right: 12px; display: flex; align-items: center; color: #1a1a1a; }
        .header .avatar { width: 40px; height: 40px; border-radius: 12px; background: #f0f2f5; display: flex; align-items: center; justify-content: center; font-size: 22px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header .info { flex: 1; margin-left: 12px; cursor: pointer; overflow: hidden; }
        .header .info h3 { font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #1a1a1a; }
        .header .info p { font-size: 11px; color: #6c757d; font-weight: 600; }
        
        .messages { padding: 16px; height: calc(100% - 130px); overflow-y: auto; display: flex; flex-direction: column; gap: 12px; transition: background 0.3s; scroll-behavior: smooth; }
        .msg { max-width: 85%; padding: 12px 16px; border-radius: 20px; position: relative; line-height: 1.55; font-size: 14.5px; transition: transform 0.2s; }
        .msg.sent { background: #007bff; color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; box-shadow: 0 4px 12px rgba(0,123,255,0.15); }
        .msg.received { background: #fff; color: #1a1a1a; align-self: flex-start; border-bottom-left-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); border: 1px solid #f0f2f5; }
        .msg-content img { max-width: 100%; border-radius: 14px; pointer-events: none; margin-top: 4px; }
        .img-placeholder { background: #f8f9fa; height: 140px; width: 200px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; border: 2px dashed #e0e6ed; }
        
        .msg-meta { display: flex; justify-content: flex-end; align-items: center; gap: 4px; margin-top: 6px; font-size: 10.5px; font-weight: 500; }
        .tick { font-size: 13px; opacity: 0.9; }
        .msg.sent .tick { color: rgba(255,255,255,0.7); }
        .msg.sent .tick.read { color: #fff; opacity: 1; }
        .msg.received .tick.read { color: #007bff; }

        .input-area { padding: 12px 16px; background: #fff; display: flex; align-items: center; gap: 12px; border-top: 1px solid #e0e6ed; transition: all 0.3s; }
        .input-area input[type="text"] { flex: 1; padding: 12px 18px; border: 1px solid #e0e6ed; border-radius: 24px; outline: none; font-size: 14.5px; background: #f8f9fa; transition: all 0.2s; }
        .input-area input[type="text"]:focus { background: #fff; border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
        .input-area label { font-size: 24px; cursor: pointer; color: #6c757d; transition: color 0.2s; }
        .input-area label:hover { color: #007bff; }
        .input-area button { background: #007bff; color: #fff; border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 8px rgba(0,123,255,0.2); }
        .input-area button:active { transform: scale(0.92); }

        .timer-container { position: relative; width: 34px; height: 34px; }
        .timer-container svg { transform: rotate(-90deg); width: 34px; height: 34px; }
        .timer-container circle { fill: none; stroke-width: 3; }
        .timer-container .bg { stroke: #f0f2f5; }
        body.unlocked .timer-container .bg { stroke: #2d2d2d; }
        .timer-container .progress { stroke: #007bff; stroke-dasharray: 88; stroke-dashoffset: 0; transition: stroke-dashoffset 0.3s linear; }
        .timer-btn { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: none; border: none; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px); }
        .modal.active { display: flex; }
        .modal-content { background: #fff; width: 100%; max-width: 320px; padding: 24px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        body.unlocked .modal-content { background: #2d2f31; color: #fff; }
        .modal h3 { font-size: 17px; margin-bottom: 16px; }
        .modal input { width: 100%; padding: 12px; border: 1px solid #e0e6ed; border-radius: 12px; margin-bottom: 16px; outline: none; background: #f8f9fa; }
        body.unlocked .modal input { background: #3d3f42; border-color: #444; color: #fff; }
        .modal-btns { display: flex; gap: 8px; }
        .modal-btns button { flex: 1; padding: 12px; border: none; border-radius: 12px; font-weight: 600; font-size: 14px; }
        .modal-btns .cancel { background: #f0f2f5; color: #555; }
        .modal-btns .save { background: #007bff; color: #fff; }

        /* Disable long press on images */
        img { -webkit-touch-callout: none; 
        -webkit-user-select: none; 
        -khtml-user-select: none; 
        -moz-user-select: none; 
        -ms-user-select: none; 
        user-select: none;
        }
    </style>
</head>
<body class="<?= $isUnlocked ? 'unlocked' : '' ?>" oncontextmenu="return false;">
    <div class="header">
        <span class="back" onclick="goBack()">&#10094;</span>
        <div class="avatar" onclick="showNickModal()"><?= $chatAvatarEmoji ?></div>
        <div class="info" onclick="showNickModal()">
            <h3><?= sanitize($chatDisplayName) ?></h3>
            <p id="typingStatus">Assistant ready</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <div class="timer-container">
                <svg viewBox="0 0 34 34">
                    <circle class="bg" cx="17" cy="17" r="14"/>
                    <circle class="progress" id="timerCircle" cx="17" cy="17" r="14"/>
                </svg>
                <button class="timer-btn" onclick="toggleLock()"><?= $isUnlocked ? '🔓' : '🔒' ?></button>
            </div>
            <button class="vanish-btn" style="background:none;border:none;font-size:18px;" onclick="document.getElementById('vanishModal').classList.add('active')">🗑️</button>
        </div>
    </div>

    <div class="messages" id="msgs">
        <?php foreach ($messages as $m): ?>
            <?php 
            $isSent = $m['sender_id'] == $myId;
            $replyMsg = null;
            if ($m['reply_to']) {
                $stmt = getDB()->prepare("SELECT message, original FROM messages WHERE id = ?");
                $stmt->execute([$m['reply_to']]);
                $replyMsg = $stmt->fetch();
            }
            ?>
            <div class="msg <?= $isSent ? 'sent' : 'received' ?>"
                 data-id="<?= $m['id'] ?>"
                 data-fake="<?= sanitize($m['message']) ?>"
                 data-real="<?= sanitize($m['original'] ?? $m['message']) ?>"
                 data-type="<?= $m['type'] ?>"
                 data-sent="<?= $isSent ? '1' : '0' ?>">
                <?php if ($replyMsg): ?>
                    <div class="reply-box"><div class="reply-text"><?= sanitize($replyMsg['original'] ?? $replyMsg['message']) ?></div></div>
                <?php endif; ?>
                <div class="msg-content">
                    <?= $m['type'] == 'image' ? '<div class="img-placeholder">🖼️</div>' : sanitize($m['message']) ?>
                </div>
                <div class="msg-meta">
                    <span class="msg-time"><?= date('g:i A', strtotime($m['created_at'])) ?></span>
                    <?php if ($isSent): ?>
                        <span class="tick <?= $m['is_read'] == 2 ? 'read' : '' ?>"><?= $m['is_read'] >= 1 ? '✓✓' : '✓' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="POST" class="input-area" id="chatForm">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="reply_to" id="replyToInput" value="0">
        <label for="imageInput">📷</label>
        <input type="file" name="image" id="imageInput" style="display:none">
        <input type="text" name="message" id="msgInput" placeholder="Message..." autocomplete="off">
        <button type="submit">✈</button>
    </form>

    <div class="modal" id="previewModal">
        <div class="modal-content" style="max-width: 400px;">
            <h3>Image Preview</h3>
            <div style="margin-bottom: 20px; text-align: center;">
                <img id="imgPreview" style="max-width: 100%; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            </div>
            <div class="modal-btns">
                <button type="button" class="cancel" onclick="cancelPreview()">Cancel</button>
                <button type="button" class="save" id="sendImageBtn">Send</button>
            </div>
        </div>
    </div>

    <div class="modal" id="nickModal">
        <div class="modal-content">
            <h3>Set Nickname</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="set_nickname">
                <input type="text" name="nickname" value="<?= sanitize($nickname ?: '') ?>" placeholder="Enter nickname...">
                <div class="modal-btns">
                    <button type="button" class="cancel" onclick="document.getElementById('nickModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="vanishModal">
        <div class="modal-content">
            <h3>Vanish Messages?</h3>
            <p style="margin-bottom:20px;font-size:14px;color:#666;">This will delete all messages permanently.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="vanish">
                <div class="modal-btns">
                    <button type="button" class="cancel" onclick="document.getElementById('vanishModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="save" style="background:#dc3545">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const myId = <?= $myId ?>;
    const chatWithId = <?= $chatWithId ?>;
    const isUnlocked = <?= $isUnlocked ? 'true' : 'false' ?>;
    let lastId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let timeLeft = 60;
    const totalTime = 60;
    const circumference = 2 * Math.PI * 14; 
    let timerInterval;

    function resetTimer() {
        if (!isUnlocked) return;
        timeLeft = totalTime;
        updateTimerUI();
        clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            timeLeft--;
            updateTimerUI();
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                lockChat();
            }
        }, 1000);
    }

    function updateTimerUI() {
        const circle = document.getElementById('timerCircle');
        if (circle) {
            circle.style.strokeDashoffset = circumference * (1 - timeLeft / totalTime);
        }
    }

    function lockChat() {
        const fd = new FormData();
        fd.append('action', 'lock');
        fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fetch(location.href, { method: 'POST', body: fd }).then(() => { location.reload(); });
    }

    function toggleLock() {
        if (isUnlocked) {
            lockChat();
        } else {
            const pin = prompt('Enter PIN to unlock:');
            if (!pin) return;
            const fd = new FormData();
            fd.append('user', chatWithId);
            fd.append('message', pin);
            fetch('api.php?action=send_message', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.unlocked) location.reload();
                    else alert('Wrong PIN');
                });
        }
    }

    document.getElementById('msgs').addEventListener('click', (e) => {
        if (!isUnlocked) return;
        const msg = e.target.closest('.msg');
        if (!msg) return;
        const content = msg.querySelector('.msg-content');
        const real = msg.dataset.real;
        const msgId = msg.dataset.id;
        const type = msg.dataset.type;
        
        fetch('api.php?action=view_message&id=' + msgId);
        
        if (type === 'image') {
            content.innerHTML = `<img src="${real}">`;
        } else {
            content.textContent = real;
        }
        resetTimer();
        setTimeout(() => {
            if (type === 'image') {
                content.innerHTML = '<div class="img-placeholder">🖼️</div>';
            } else {
                content.textContent = msg.dataset.fake;
            }
            fetch('api.php?action=refresh_msgs&user=' + chatWithId);
        }, 5000);
    });

    function goBack() { location.href = 'users.php'; }
    function showNickModal() { document.getElementById('nickModal').classList.add('active'); }

    document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
    setInterval(fetchMessages, 2000);
    setInterval(updateReadStatus, 3000);
    setInterval(checkTyping, 3000);
    setInterval(updateStatus, 8000);
    updateStatus();
    
    // Prevent back-forward cache issues
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            lastRenderedHash = '';
            fetchMessages();
        }
    });

    function fetchMessages() {
        fetch(`api.php?action=messages&user=${chatWithId}&last_id=${lastId}`)
            .then(r => r.json())
            .then(d => {
                if (d.messages && d.messages.length > 0) {
                    d.messages.forEach(m => {
                        if (document.querySelector(`.msg[data-id="${m.id}"]`)) return;
                        const div = document.createElement('div');
                        const isSent = m.sender_id == myId;
                        div.className = `msg ${isSent ? 'sent' : 'received'}`;
                        div.dataset.id = m.id;
                        div.dataset.fake = m.message;
                        div.dataset.real = m.original || m.message;
                        div.dataset.type = m.type;
                        let displayContent = m.type === 'image' ? '<div class="img-placeholder">🖼️</div>' : m.message;
                        let tickHtml = isSent ? `<span class="tick ${m.is_read == 2 ? 'read' : ''}">${m.is_read >= 1 ? '✓✓' : '✓'}</span>` : '';
                        div.innerHTML = `<div class="msg-content">${displayContent}</div><div class="msg-meta"><span class="msg-time">${m.time}</span>${tickHtml}</div>`;
                        document.getElementById('msgs').appendChild(div);
                        lastId = m.id;
                        
                        if (m.delete_at > 0) {
                            startDeletionTimer(m.id, m.delete_at);
                        }
                    });
                    document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
                }
            });
    }

    const deletionTimers = {};
    function startDeletionTimer(id, deleteAt) {
        if (deletionTimers[id]) return;
        const now = Math.floor(Date.now() / 1000);
        const delay = (deleteAt - now) * 1000;
        if (delay <= 0) {
            removeMessageFromDom(id);
        } else {
            deletionTimers[id] = setTimeout(() => {
                removeMessageFromDom(id);
            }, delay);
        }
    }

    function removeMessageFromDom(id) {
        const msg = document.querySelector(`.msg[data-id="${id}"]`);
        if (msg) {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }
        delete deletionTimers[id];
    }

    function updateReadStatus() {
        fetch(`api.php?action=read_status&user=${chatWithId}`)
            .then(r => r.json())
            .then(d => {
                if (d.read_messages) {
                    d.read_messages.forEach(m => {
                        const msg = document.querySelector(`.msg[data-id="${m.id}"]`);
                        if (msg) {
                            const tick = msg.querySelector('.tick');
                            if (tick) {
                                tick.textContent = m.is_read >= 1 ? '✓✓' : '✓';
                                if (m.is_read == 2) tick.className = 'tick read';
                            }
                        }
                        if (m.delete_at > 0) {
                            startDeletionTimer(m.id, m.delete_at);
                        }
                    });
                }
            });
    }

    function checkTyping() {
        fetch(`api.php?action=typing&user=${chatWithId}`)
            .then(r => r.json())
            .then(d => {
                if (d.typing) {
                    const el = document.getElementById('typingStatus');
                    el.textContent = 'Typing...';
                    el.style.color = '#28a745';
                }
            });
    }

    function updateStatus() {
        fetch(`api.php?action=status&user=${chatWithId}`)
            .then(r => r.json())
            .then(d => {
                const el = document.getElementById('typingStatus');
                if (el.textContent !== 'Typing...') {
                    el.textContent = d.status;
                    el.style.color = d.color;
                    if (isUnlocked && !d.is_online) el.style.color = '#8a8a8a';
                }
            });
    }

    document.getElementById('chatForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const input = document.getElementById('msgInput');
        const msg = input.value.trim();
        if (!msg) return;
        input.value = '';
        input.focus();
        const fd = new FormData();
        fd.append('user', chatWithId);
        fd.append('message', msg);
        fd.append('reply_to', document.getElementById('replyToInput').value);
        fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fetch('api.php?action=send_message', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.unlocked) { location.reload(); }
                else { fetchMessages(); }
            });
    });

    let selectedImageFile = null;

    document.getElementById('imageInput').addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        selectedImageFile = file;
        
        const reader = new FileReader();
        reader.onload = (ev) => {
            document.getElementById('imgPreview').src = ev.target.result;
            document.getElementById('previewModal').classList.add('active');
        };
        reader.readAsDataURL(file);
    });

    function cancelPreview() {
        selectedImageFile = null;
        document.getElementById('imageInput').value = '';
        document.getElementById('previewModal').classList.remove('active');
    }

    document.getElementById('sendImageBtn').addEventListener('click', () => {
        if (!selectedImageFile) return;
        
        const fd = new FormData();
        fd.append('user', chatWithId);
        fd.append('image', selectedImageFile);
        fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        
        document.getElementById('sendImageBtn').disabled = true;
        document.getElementById('sendImageBtn').textContent = '...';
        
        fetch('api.php?action=send_image', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                document.getElementById('sendImageBtn').disabled = false;
                document.getElementById('sendImageBtn').textContent = 'Send';
                if (d.success) {
                    cancelPreview();
                    fetchMessages();
                }
            });
    });

    document.getElementById('msgInput').addEventListener('input', () => {
        const fd = new FormData();
        fd.append('action', 'typing');
        fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fetch(location.href, { method: 'POST', body: fd });
        resetTimer();
    });

    window.addEventListener('mousemove', resetTimer);
    window.addEventListener('keypress', resetTimer);
    if (isUnlocked) resetTimer();
    </script>
</body>
</html>