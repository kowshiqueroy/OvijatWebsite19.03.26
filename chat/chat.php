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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($chatDisplayName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: #ECE5DD; }
        .header { background: #128C7E; color: #fff; padding: 10px 15px; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header .back { font-size: 24px; cursor: pointer; margin-right: 12px; }
        .header .avatar { width: 42px; height: 42px; border-radius: 50%; background: #075E54; display: flex; align-items: center; justify-content: center; font-size: 22px; cursor: pointer; }
        .header .info { flex: 1; margin-left: 12px; cursor: pointer; overflow: hidden; }
        .header .info h3 { font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header .info p { font-size: 12px; opacity: 0.9; }
        .lock-btn { font-size: 20px; background: none; border: none; cursor: pointer; padding: 8px; color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; }
        .vanish-btn { font-size: 20px; background: none; border: none; cursor: pointer; padding: 8px; color: #fff; }

        .messages { padding: 15px; background: #E5DDD3; height: calc(100vh - 160px); overflow-y: auto; padding-bottom: 80px; display: flex; flex-direction: column; }
        .msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; margin-bottom: 4px; position: relative; box-shadow: 0 1px 1px rgba(0,0,0,0.1); }
        .msg.sent { background: #DCF8C6; align-self: flex-end; border-top-right-radius: 2px; }
        .msg.received { background: #fff; align-self: flex-start; border-top-left-radius: 2px; }
        .msg-content { font-size: 15px; line-height: 1.45; word-wrap: break-word; color: #111; }
        .msg-content img { max-width: 100%; border-radius: 8px; display: block; margin-top: 5px; }
        .img-placeholder { background: #eee; height: 160px; width: 220px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #aaa; }
        .msg-meta { display: flex; justify-content: flex-end; align-items: center; gap: 5px; margin-top: 3px; }
        .msg-time { font-size: 10px; color: #888; text-transform: uppercase; }
        .tick { font-size: 14px; color: #888; }
        .tick.read { color: #128CFF; }

        .input-area { position: fixed; bottom: 0; left: 0; right: 0; background: #F0F0F0; padding: 10px; display: flex; align-items: center; gap: 10px; z-index: 100; border-top: 1px solid #ddd; }
        .input-area input[type="text"] { flex: 1; padding: 12px 18px; border: 1px solid #ddd; border-radius: 25px; outline: none; font-size: 15px; }
        .input-area label { font-size: 24px; cursor: pointer; color: #667781; }
        .input-area button { background: #128C7E; color: #fff; border: none; width: 45px; height: 45px; border-radius: 50%; font-size: 20px; cursor: pointer; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 200; align-items: center; justify-content: center; padding: 20px; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; width: 100%; max-width: 340px; padding: 25px; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal h3 { margin-bottom: 20px; color: #128C7E; font-size: 18px; }
        .modal input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; margin-bottom: 20px; font-size: 15px; outline: none; }
        .modal-btns { display: flex; gap: 12px; }
        .modal-btns button { flex: 1; padding: 12px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; }
        .modal-btns .cancel { background: #f0f0f0; color: #555; }
        .modal-btns .save { background: #128C7E; color: #fff; }

        .timer-container { position: relative; width: 36px; height: 36px; }
        .timer-container svg { transform: rotate(-90deg); }
        .timer-container circle { fill: none; stroke-width: 3; }
        .timer-container .bg { stroke: rgba(255,255,255,0.3); }
        .timer-container .progress { stroke: #25D366; stroke-dasharray: 94.2; stroke-dashoffset: 0; }
        .timer-btn { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: none; border: none; cursor: pointer; color: #fff; font-size: 18px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <div class="header">
        <span class="back" onclick="goBack()">&#8592;</span>
        <div class="avatar" onclick="showNickModal()"><?= $chatAvatarEmoji ?></div>
        <div class="info" onclick="showNickModal()">
            <h3><?= sanitize($chatDisplayName) ?></h3>
            <p id="typingStatus">online</p>
        </div>
        <div style="display:flex;gap:5px;align-items:center;">
            <div class="timer-container">
                <svg width="36" height="36" viewBox="0 0 36 36">
                    <circle class="bg" cx="18" cy="18" r="15"/>
                    <circle class="progress" id="timerCircle" cx="18" cy="18" r="15"/>
                </svg>
                <button class="timer-btn" onclick="toggleLock()"><?= $isUnlocked ? '🔓' : '🔒' ?></button>
            </div>
            <button class="vanish-btn" onclick="document.getElementById('vanishModal').classList.add('active')">🗑️</button>
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
    const circumference = 2 * Math.PI * 15;
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
        const type = msg.dataset.type;
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
                    });
                    document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
                }
            });
    }

    function updateReadStatus() {
        fetch(`api.php?action=read_status&user=${chatWithId}`)
            .then(r => r.json())
            .then(d => {
                if (d.read_ids) {
                    d.read_ids.forEach(id => {
                        const msg = document.querySelector(`.msg[data-id="${id}"]`);
                        if (msg) {
                            const tick = msg.querySelector('.tick');
                            if (tick) { tick.className = 'tick read'; tick.textContent = '✓✓'; }
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
                    document.getElementById('typingStatus').textContent = 'Typing...';
                    document.getElementById('typingStatus').style.color = '#25D366';
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
                    el.style.color = d.online ? '#25D366' : 'rgba(255,255,255,0.9)';
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

    document.getElementById('imageInput').addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('user', chatWithId);
        fd.append('image', file);
        fetch('api.php?action=send_image', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) { lastId = d.id; fetchMessages(); } });
        e.target.value = '';
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