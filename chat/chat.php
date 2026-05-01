<?php
session_start();
require_once 'db.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$chatWithId = (int)($_GET['user'] ?? 0);
if (!$chatWithId) { header('Location: users.php'); exit; }

$myId = $_SESSION['user_id'];
updateLastActive($myId, "u$chatWithId");

$chatUser = getUserById($chatWithId);
if (!$chatUser) { header('Location: users.php'); exit; }
$nickname = getNickname($myId, $chatWithId);
$chatDisplayName = $nickname ?: $chatUser['display_name'];

$isUnlocked = isChatUnlocked($myId, $chatWithId);
markMessagesAsRead($myId, $chatWithId);
$messages = getMessages($myId, $chatWithId);
$lastId = !empty($messages) ? end($messages)['id'] : 0;

if (($_POST['action'] ?? '') === 'lock') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { exit; }
    lockChat($myId, $chatWithId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Chat</title>
    <style>
        :root {
            --blue-primary: #007bff;
            --blue-sent: #007bff;
            --blue-received: #f1f3f4;
            --blue-text-sent: #fff;
            --blue-text-received: #1a1b1c;
            --blue-bg: #fff;
            --header-bg: #fff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; height: -webkit-fill-available; overflow: hidden; background: var(--blue-bg); }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            display: flex; flex-direction: column; width: 100vw; position: fixed;
            background: <?= $isUnlocked ? '#eaebed' : 'var(--blue-bg)' ?>;
        }
        .header { 
            background: <?= $isUnlocked ? '#eaebed' : 'var(--header-bg)' ?>; 
            padding: calc(10px + env(safe-area-inset-top)) 15px 10px; 
            display: flex; align-items: center; justify-content: space-between; 
            border-bottom: 1px solid <?= $isUnlocked ? '#ccc' : '#eee' ?>; flex-shrink: 0; z-index: 100;
        }
        .header-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
        .back-btn { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--blue-primary); padding: 5px; }
        .header-name { font-weight: 600; font-size: 16px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-status { font-size: 11px; color: #888; }
        .messages { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 12px; -webkit-overflow-scrolling: touch; }
        .msg { max-width: 80%; padding: 10px 14px; border-radius: 20px; font-size: 15px; position: relative; line-height: 1.4; word-wrap: break-word; }
        .msg.sent { align-self: flex-end; background: var(--blue-sent); color: var(--blue-text-sent); border-bottom-right-radius: 4px; }
        .msg.received { align-self: flex-start; background: var(--blue-received); color: var(--blue-text-received); border-bottom-left-radius: 4px; }
        .msg-meta { font-size: 10px; margin-top: 4px; opacity: 0.7; display: flex; align-items: center; justify-content: flex-end; gap: 4px; }
        .tick { font-size: 12px; }
        .tick.read { color: #4fc3f7; }
        .typing-indicator { font-size: 12px; color: #28a745; padding: 5px 15px; height: 22px; flex-shrink: 0; }
        .input-area { background: #fff; padding: 8px 12px calc(8px + env(safe-area-inset-bottom)); border-top: 1px solid #eee; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .input-wrapper { flex: 1; background: #f1f3f4; border-radius: 24px; display: flex; align-items: center; padding: 0 12px; }
        .input-wrapper input { flex: 1; padding: 10px 0; background: none; border: none; outline: none; font-size: 16px; }
        .send-btn { background: none; border: none; color: var(--blue-primary); font-size: 24px; cursor: pointer; display: flex; align-items: center; }
        .action-icon { font-size: 22px; cursor: pointer; color: #5f6368; padding: 4px; }
        .timer-container { position: relative; width: 32px; height: 32px; flex-shrink: 0; }
        .timer-container svg { transform: rotate(-90deg); width: 32px; height: 32px; }
        .timer-container circle { fill: none; stroke-width: 2.5; }
        .timer-container .bg { stroke: #f1f3f4; }
        .timer-container .progress { stroke: var(--blue-primary); stroke-dasharray: 88; stroke-dashoffset: 0; transition: stroke-dashoffset 0.3s linear; }
        .timer-btn { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: none; border: none; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #5f6368; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal.active { display: flex; }
        .modal-content { background: #fff; padding: 24px; border-radius: 20px; width: 85%; max-width: 320px; text-align: center; }
        .modal-btns { display: flex; gap: 12px; margin-top: 20px; }
        .modal-btns button { flex: 1; padding: 12px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; }
        .img-placeholder { background: rgba(0,0,0,0.05); width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    </style>
</head>
<body oncontextmenu="return false;">
    <div class="header">
        <div class="header-left">
            <button class="back-btn" onclick="goBack()">←</button>
            <div class="header-info">
                <div class="header-name" onclick="showNickModal()"><?= sanitize($chatDisplayName) ?></div>
                <div class="header-status" id="onlineStatus">Connecting...</div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button class="action-icon" style="background:none;border:none;" onclick="location.href='aichat.php?user=<?= $chatWithId ?>'">✨</button>
            <div class="timer-container">
                <svg viewBox="0 0 34 34"><circle class="bg" cx="17" cy="17" r="14"/><circle class="progress" id="timerCircle" cx="17" cy="17" r="14"/></svg>
                <button class="timer-btn" onclick="toggleLock()"><?= $isUnlocked ? 'U' : 'L' ?></button>
            </div>
            <button class="action-icon" style="background:none;border:none;" onclick="document.getElementById('vanishModal').classList.add('active')">🗑️</button>
        </div>
    </div>

    <div class="messages" id="msgs">
        <?php foreach ($messages as $m): ?>
            <?php $isSent = $m['sender_id'] == $myId; $isRead = $m['is_read']; ?>
            <div class="msg <?= $isSent ? 'sent' : 'received' ?>" data-id="<?= $m['id'] ?>" data-fake="<?= sanitize($m['message']) ?>" data-real="<?= sanitize($m['original'] ?? $m['message']) ?>" data-type="<?= $m['type'] ?>">
                <div class="msg-content"><?= $m['type'] === 'image' ? '<div class="img-placeholder">🖼️</div>' : sanitize($m['message']) ?></div>
                <div class="msg-meta">
                    <span><?= date('g:i A', strtotime($m['created_at'])) ?></span>
                    <?php if ($isSent): ?><span class="tick <?= $isRead == 2 ? 'read' : '' ?>"><?= $isRead >= 1 ? '✓✓' : '✓' ?></span><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="typingStatus" class="typing-indicator"></div>
    <form class="input-area" id="chatForm">
        <label for="imageInput" class="action-icon">📷</label>
        <input type="file" id="imageInput" style="display:none" accept="image/*">
        <div class="input-wrapper"><input type="text" id="msgInput" placeholder="Message..." autocomplete="off"></div>
        <button type="submit" class="send-btn">➤</button>
    </form>

    <div id="vanishModal" class="modal"><div class="modal-content"><h3>Vanish Mode</h3><p>Clear all messages?</p><div class="modal-btns"><button style="background:#eee" onclick="this.closest('.modal').classList.remove('active')">Cancel</button><button style="background:var(--blue-primary);color:#fff" onclick="vanishMessages()">Clear All</button></div></div></div>
    <div id="nickModal" class="modal"><div class="modal-content"><h3>Set Nickname</h3><input type="text" id="nickInput" placeholder="Name..." value="<?= sanitize($nickname) ?>"><div class="modal-btns"><button style="background:#eee" onclick="this.closest('.modal').classList.remove('active')">Cancel</button><button style="background:var(--blue-primary);color:#fff" onclick="setNickname()">Save</button></div></div></div>

    <script>
    const myId = <?= $myId ?>, chatWithId = <?= $chatWithId ?>, isUnlocked = <?= $isUnlocked ? 'true' : 'false' ?>;
    let lastId = <?= $lastId ?>, timeLeft = 60, timerInterval;
    const circumference = 2 * Math.PI * 14;

    function resetTimer() {
        if (!isUnlocked) return;
        timeLeft = 60; updateTimerUI(); clearInterval(timerInterval);
        timerInterval = setInterval(() => { timeLeft--; updateTimerUI(); if (timeLeft <= 0) lockChat(); }, 1000);
    }
    function updateTimerUI() { document.getElementById('timerCircle').style.strokeDashoffset = circumference * (1 - timeLeft / 60); }
    function lockChat() { const fd = new FormData(); fd.append('action', 'lock'); fd.append('csrf_token', '<?= generateCsrfToken() ?>'); fetch(location.href, { method: 'POST', body: fd }).then(() => location.reload()); }
    function toggleLock() { if (isUnlocked) lockChat(); }

    document.getElementById('msgs').addEventListener('click', (e) => {
        if (!isUnlocked) return;
        const msg = e.target.closest('.msg'); if (!msg) return;
        const content = msg.querySelector('.msg-content'), isReceived = msg.classList.contains('received'), type = msg.dataset.type;
        if (isReceived) fetch('api.php?action=view_message&id=' + msg.dataset.id);
        content.innerHTML = type === 'image' ? `<img src="${msg.dataset.real}" onload="scrollToBottom()">` : msg.dataset.real;
        resetTimer();
        setTimeout(() => {
            content.innerHTML = type === 'image' ? '<div class="img-placeholder">🖼️</div>' : msg.dataset.fake;
            if (isReceived) fetch('api.php?action=refresh_msgs&user=' + chatWithId);
        }, 5000);
    });

    document.getElementById('chatForm').addEventListener('submit', (e) => {
        e.preventDefault(); const input = document.getElementById('msgInput'), msg = input.value.trim(); if (!msg) return;
        input.value = ''; resetTimer();
        const fd = new FormData(); fd.append('user', chatWithId); fd.append('message', msg); fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fetch('api.php?action=send_message', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.unlocked) location.reload(); else fetchMessages(); });
    });

    document.getElementById('imageInput').addEventListener('change', (e) => {
        const file = e.target.files[0]; if (!file) return;
        const fd = new FormData(); fd.append('user', chatWithId); fd.append('image', file); fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fetch('api.php?action=send_image', { method: 'POST', body: fd }).then(() => fetchMessages());
        resetTimer();
    });

    function fetchMessages() {
        fetch(`api.php?action=messages&user=${chatWithId}&last_id=${lastId}`).then(r => r.json()).then(d => {
            if (d.messages) {
                d.messages.forEach(m => {
                    if (document.querySelector(`.msg[data-id="${m.id}"]`)) return;
                    const div = document.createElement('div'), isSent = m.sender_id == myId;
                    div.className = `msg ${isSent ? 'sent' : 'received'}`;
                    div.dataset.id = m.id; div.dataset.fake = m.message; div.dataset.real = m.original || m.message; div.dataset.type = m.type;
                    div.innerHTML = `<div class="msg-content">${m.type==='image'?'<div class="img-placeholder">🖼️</div>':m.message}</div><div class="msg-meta"><span>${m.time}</span>${isSent?`<span class="tick ${m.is_read==2?'read':''}">✓${m.is_read>=1?'✓':''}</span>`:''}</div>`;
                    document.getElementById('msgs').appendChild(div); lastId = m.id;
                });
                scrollToBottom();
            }
        });
    }

    function updateReadStatus() {
        fetch(`api.php?action=read_status&user=${chatWithId}`).then(r => r.json()).then(d => {
            if (d.read_messages) d.read_messages.forEach(m => {
                const msg = document.querySelector(`.msg[data-id="${m.id}"]`);
                if (msg) { const tick = msg.querySelector('.tick'); if (tick) { tick.textContent = m.is_read>=1?'✓✓':'✓'; if(m.is_read==2) tick.className='tick read'; } }
            });
        });
    }

    function checkTyping() { fetch(`api.php?action=typing&user=${chatWithId}`).then(r=>r.json()).then(d => document.getElementById('typingStatus').textContent = d.typing ? 'Typing...' : ''); }
    function updateStatus() { fetch(`api.php?action=status&user=${chatWithId}`).then(r=>r.json()).then(d => { const el = document.getElementById('onlineStatus'); el.textContent = d.status; el.style.color = d.color; }); }
    function vanishMessages() { const fd = new FormData(); fd.append('user', chatWithId); fd.append('csrf_token', '<?= generateCsrfToken() ?>'); fetch('api.php?action=vanish', { method: 'POST', body: fd }).then(() => location.reload()); }
    function setNickname() { const nick = document.getElementById('nickInput').value.trim(); const fd = new FormData(); fd.append('user', chatWithId); fd.append('nickname', nick); fd.append('csrf_token', '<?= generateCsrfToken() ?>'); fetch('api.php?action=set_nickname', { method: 'POST', body: fd }).then(() => location.reload()); }
    function goBack() { location.href = 'users.php'; }
    function showNickModal() { document.getElementById('nickModal').classList.add('active'); }
    function scrollToBottom() { const m = document.getElementById('msgs'); m.scrollTop = m.scrollHeight; }

    setInterval(fetchMessages, 2000); setInterval(updateReadStatus, 3000); setInterval(checkTyping, 3000); setInterval(updateStatus, 5000);
    document.getElementById('msgInput').addEventListener('input', () => { fetch(`api.php?action=set_typing&user=${chatWithId}`); resetTimer(); });
    if (window.visualViewport) window.visualViewport.addEventListener('resize', () => { document.body.style.height = window.visualViewport.height + 'px'; scrollToBottom(); });
    window.addEventListener('mousemove', resetTimer); window.addEventListener('keypress', resetTimer); window.addEventListener('touchstart', resetTimer);
    scrollToBottom(); updateStatus(); if (isUnlocked) resetTimer();
    </script>
</body>
</html>