<?php
session_start();
require_once 'db.php';
require_once 'group_db.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: users.php'); exit; }

$myId = $_SESSION['user_id'];
updateLastActive($myId, "g$groupId");

$group = getGroupById($groupId);
if (!$group) { header('Location: users.php'); exit; }

// Check if user is a member
$members = getGroupMembers($groupId);
$isMember = false;
foreach ($members as $m) if ($m['id'] == $myId) $isMember = true;
if (!$isMember) { header('Location: users.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die("Invalid CSRF token"); }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        $msg = trim($_POST['message'] ?? '');
        $replyTo = (int)($_POST['reply_to'] ?? 0);
        if ($msg) {
            if (isPinValid($myId, $msg)) {
                unlockGroup($myId, $groupId);
                header("Location: group_chat.php?id=$groupId"); exit;
            } else {
                saveGroupMessage($groupId, $myId, $msg, 'text', $replyTo);
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
                    saveGroupMessage($groupId, $myId, $dest, 'image', 0);
                }
            }
        }
        if (isset($_GET['ajax'])) { echo json_encode(['success' => true]); exit; }
        header("Location: group_chat.php?id=$groupId"); exit;
    }
    if ($action === 'lock') {
        lockGroup($myId, $groupId);
        header("Location: group_chat.php?id=$groupId"); exit;
    }
}

$isUnlocked = isGroupUnlocked($myId, $groupId);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= sanitize($group['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f4f7fb; 
            color: #1a1a1a;
            user-select: none;
            -webkit-user-select: none;
            overflow-x: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        body.unlocked { background: #0b0c0d; color: #f0f0f0; }
        .header { height: 60px; background: #fff; display: flex; align-items: center; padding: 0 15px; border-bottom: 1px solid #e0e0e0; position: fixed; top: 0; width: 100%; z-index: 100; }
        body.unlocked .header { background: #161718; border-bottom: 1px solid #2d2d2d; }
        .back { text-decoration: none; color: #1a1a1a; font-size: 20px; margin-right: 15px; }
        body.unlocked .back { color: #f0f0f0; }
        .header .info { flex: 1; min-width: 0; }
        .header .info h3 { font-size: 15px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        body.unlocked .header .info h3 { color: #fff; }
        .header .info p { font-size: 10px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        body.unlocked .header .info p { color: #888; }
        
        .messages { position: absolute; top: 60px; bottom: 70px; width: 100%; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; scroll-behavior: smooth; }
        .msg { max-width: 80%; padding: 10px 14px; border-radius: 18px; position: relative; font-size: 15px; line-height: 1.4; cursor: pointer; }
        
        .msg.sent { align-self: flex-end; background: #0084ff; color: #fff; border-bottom-right-radius: 4px; }
        .msg.received { align-self: flex-start; background: #e4e6eb; color: #1c1e21; border-bottom-left-radius: 4px; }
        body.unlocked .msg.sent { background: #1a73e8; }
        body.unlocked .msg.received { background: #242526; color: #f0f0f0; }
        .sender-name { font-size: 11px; margin-bottom: 2px; opacity: 0.7; font-weight: bold; }
        
        .input-area { position: fixed; bottom: 0; width: 100%; background: #fff; padding: 10px; border-top: 1px solid #e0e0e0; display: flex; align-items: center; gap: 10px; z-index: 100; }
        body.unlocked .input-area { background: #161718; border-top: 1px solid #2d2d2d; }
        .input-area input[type="text"] { flex: 1; border: none; background: #f0f2f5; padding: 12px 18px; border-radius: 24px; outline: none; font-size: 15px; }
        body.unlocked .input-area input[type="text"] { background: #242526; color: #fff; }
        .btn-send { background: none; border: none; color: #0084ff; font-weight: 800; cursor: pointer; padding: 5px; font-size: 16px; }
        
        .btn-add { background: #f0f2f5; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; }
        body.unlocked .btn-add { background: #242526; color: #fff; }

        .modal { display: none; position: fixed; z-index: 300; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
        .modal-content { background: #fff; margin: 15% auto; padding: 25px; border-radius: 24px; width: 90%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        body.unlocked .modal-content { background: #1c1d1e; color: #fff; }
        .modal-content h3 { margin-bottom: 20px; font-size: 20px; }
        .modal-content input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 12px; outline: none; }
        body.unlocked .modal-content input { background: #242526; border-color: #333; color: #fff; }
        .search-results { max-height: 250px; overflow-y: auto; margin-bottom: 15px; }
        .search-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f0f2f5; }
        body.unlocked .search-item { border-bottom-color: #2d2d2d; }
        .btn-action { background: #007bff; color: #fff; border: none; padding: 8px 16px; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-action.remove { background: #ff4444; }
        
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
        .status-dot.online { background: #28a745; box-shadow: 0 0 5px #28a745; }
        .status-dot.offline { background: #adb5bd; }

        #preview-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); display: none; z-index: 200; flex-direction: column; align-items: center; justify-content: center; }
        #preview-overlay img { max-width: 90%; max-height: 70vh; border-radius: 16px; object-fit: contain; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .preview-header { position: absolute; top: 20px; left: 20px; right: 20px; display: flex; justify-content: space-between; align-items: center; }
        .preview-header span { color: #fff; font-size: 16px; font-weight: 600; }
        .preview-actions { display: flex; gap: 15px; margin-top: 20px; }
        .preview-actions button { padding: 14px 28px; border: none; border-radius: 12px; font-weight: bold; font-size: 15px; cursor: pointer; }
        .preview-cancel { background: #333; color: #fff; }
        .preview-send { background: #0084ff; color: #fff; }
        
        body.unlocked #preview-overlay { background: rgba(0,0,0,0.98); }
        
        #img-viewer { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); display: none; z-index: 300; align-items: center; justify-content: center; cursor: pointer; }
        #img-viewer img { max-width: 95%; max-height: 95%; border-radius: 12px; cursor: default; }
        
        .countdown { font-size: 9px; opacity: 0.6; margin-top: 4px; display: block; font-weight: bold; }
        
        .msg-image { max-width: 200px; border-radius: 12px; cursor: pointer; }
    </style>
</head>
<body class="<?= $isUnlocked ? 'unlocked' : '' ?>">
    <div class="header">
        <a href="users.php" class="back">←</a>
        <div class="info">
            <h3 onclick="showMembersList()"><?= sanitize($group['name']) ?></h3>
            <p id="member-status">Loading members...</p>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px">
            <button class="btn-add" onclick="showAddMemberModal()" title="Add Member">👤+</button>
            <?php if ($isUnlocked): ?>
                <form method="POST" id="lockForm" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn-add" style="background:#ff4444; color:#fff">🔒</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="messages" id="messages"></div>

    <div id="preview-overlay">
        <div class="preview-header">
            <span>📷 Image Preview</span>
            <button onclick="cancelUpload()" style="background:none;border:none;color:#fff;font-size:24px;">✕</button>
        </div>
        <img id="preview-img" src="" onclick="viewFullImage(this.src)">
        <div class="preview-actions">
            <button class="preview-cancel" onclick="cancelUpload()">Cancel</button>
            <button class="preview-send" onclick="confirmUpload()">Send Image</button>
        </div>
    </div>
    
    <div id="img-viewer" onclick="this.style.display='none'">
        <img id="full-img" src="">
    </div>
    
    <form class="input-area" id="msgForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="send">
        <label for="img-upload" style="cursor:pointer; font-size: 20px;">📷</label>
        <input type="file" id="img-upload" name="image" style="display:none" onchange="previewImage(this)">
        <input type="text" id="msgInput" name="message" placeholder="Message or PIN..." autocomplete="off">
        <button type="submit" class="btn-send">➤</button>
    </form>

    <div id="addMemberModal" class="modal">
        <div class="modal-content">
            <h3>Group Members</h3>
            <div id="currentMembersList" class="search-results" style="border-bottom: 2px solid #eee; margin-bottom: 15px; padding-bottom: 10px;"></div>
            <h3>Add New Member</h3>
            <input type="text" id="userSearch" placeholder="Search users..." onkeyup="searchUsers(this.value)">
            <div id="searchResults" class="search-results"></div>
            <button onclick="closeAddMemberModal()" style="width:100%; padding:12px; border:none; background:#eee; border-radius:12px; font-weight:bold">Close</button>
        </div>
    </div>

    <script>
        const groupId = <?= $groupId ?>;
        const myId = <?= $myId ?>;
        let lastMsgId = 0;
        let lastActivity = Date.now();
        const isUnlocked = <?= $isUnlocked ? 'true' : 'false' ?>;
        let tempRevealed = {}; // msgId -> timestamp
        let currentMsgs = [];
        let lastRenderedHash = "";

        if (isUnlocked) {
            setInterval(() => {
                if (Date.now() - lastActivity > 60000) document.getElementById('lockForm').submit();
            }, 5000);
        }

        function updateActivity() { lastActivity = Date.now(); }
        document.addEventListener('touchstart', updateActivity);
        document.addEventListener('mousemove', updateActivity);
        document.getElementById('msgInput').addEventListener('input', updateActivity);

        function scrollBottom() {
            const m = document.getElementById('messages');
            m.scrollTop = m.scrollHeight;
        }

        function viewMessage(msgId) {
            if (!isUnlocked) return;
            updateActivity();
            
            // Check if already viewed in localStorage
            const viewedKey = 'viewed_' + msgId;
            if (localStorage.getItem(viewedKey)) {
                // Already viewed, just reveal temporarily
                tempRevealed[msgId] = Date.now();
                renderMessages(true);
                return;
            }
            
            // First time viewing - mark as viewed
            localStorage.setItem(viewedKey, '1');
            tempRevealed[msgId] = Date.now();

            let msg = currentMsgs.find(m => m.id == msgId);
            if (msg && !msg.viewed_at) {
                let formData = new FormData();
                formData.append('message_id', msgId);
                formData.append('csrf_token', '<?= generateCsrfToken() ?>');
                fetch('group_api.php?action=view_message', { method: 'POST', body: formData });
            }
            renderMessages(true);
        }

        function loadMessages() {
            fetch(`group_api.php?action=get_messages&id=${groupId}`)
                .then(r => r.json())
                .then(msgs => {
                    // Patch in viewed state from localStorage
                    msgs.forEach(m => {
                        if (localStorage.getItem('viewed_' + m.id)) {
                            m.viewed_at = localStorage.getItem('viewed_' + m.id);
                        }
                        if (localStorage.getItem('allviewed_' + m.id)) {
                            m.all_viewed = true;
                            m.delete_at = parseInt(localStorage.getItem('allviewed_' + m.id));
                        }
                    });
                    currentMsgs = msgs;
                    renderMessages();
                    if (msgs.length > 0 && msgs[msgs.length - 1].id > lastMsgId) {
                        lastMsgId = msgs[msgs.length - 1].id;
                        scrollBottom();
                    }
                });
        }

        function renderMessages(force = false) {
            const container = document.getElementById('messages');
            const now = Math.floor(Date.now() / 1000);
            
            // Generate hash based only on server message data, not localStorage
            let contentHash = currentMsgs.map(m => {
                return `${m.id}-${m.viewed_at ? 'V' : 'C'}-${m.user_delete_at || 0}-${m.all_viewed ? 'A' : 'N'}-${m.delete_at || 0}`;
            }).join('|');

            if (contentHash === lastRenderedHash && !force) {
                // Only update countdowns surgically without full re-render
                currentMsgs.forEach(m => {
                    const el = document.getElementById(`countdown-${m.id}`);
                    const globalEl = document.getElementById(`global-countdown-${m.id}`);
                    
                    // Personal countdown
                    if (el && m.user_delete_at) {
                        const rem = m.user_delete_at - now;
                        el.textContent = rem > 0 ? `${rem}s` : '';
                        if (rem <= 0) loadMessages();
                    }
                    
                    // Global countdown (all viewed)
                    if (globalEl && m.all_viewed && m.delete_at) {
                        const globalRem = m.delete_at - now;
                        if (globalRem <= 0) loadMessages();
                    }
                    
                    // Temp reveal
                    const isTemp = tempRevealed[m.id] && (Date.now() - tempRevealed[m.id] < 5000);
                    const contentEl = document.querySelector(`#msg-${m.id} .msg-content`);
                    if (contentEl) {
                        if (isTemp && m.viewed_at && !contentEl.dataset.permanent) {
                            contentEl.dataset.permanent = contentEl.innerHTML;
                            contentEl.innerHTML = (m.type === 'image') ? `<img src="${m.original}" style="max-width:100%; border-radius:12px; margin-top:5px">` : m.original;
                        } else if (!isTemp && contentEl.dataset.permanent) {
                            contentEl.innerHTML = contentEl.dataset.permanent;
                            delete contentEl.dataset.permanent;
                        }
                    }
});
                return;
            }

            lastRenderedHash = contentHash;
            let html = '';
            currentMsgs.forEach(m => {
                const isMe = m.sender_id == myId;
                const isTemp = tempRevealed[m.id] && (Date.now() - tempRevealed[m.id] < 5000);
                const viewedKey = 'viewed_' + m.id;
                const hasViewed = m.viewed_at || localStorage.getItem(viewedKey);
                
                let content = m.message;
                let countdownText = '';
                let globalCountdown = '';
                let viewStatus = '';

                // Handle image messages - ALL see placeholder until they click to view
                if (m.type === 'image') {
                    const isTemp = tempRevealed[m.id] && (Date.now() - tempRevealed[m.id] < 5000);
                    if (isTemp) {
                        content = `<img src="${m.original}" class="msg-image" onclick="viewImage('${m.original}')">`;
                    } else {
                        content = `<div class="img-placeholder" onclick="viewMessage(${m.id})" style="background:#eee; padding:20px; border-radius:12px; text-align:center; cursor:pointer">📷 Image</div>`;
                    }
                }

                // Show countdown for viewed messages (regardless of sender)
                if (hasViewed) {
                    const rem = m.user_delete_at - now;
                    if (rem <= 0) return; // Message expired
                    countdownText = `${rem}s`;
                }

                // Show who viewed (for all members)
                if (m.viewers && m.viewers.length > 0) {
                    const viewerNames = m.viewers.map(v => v.avatar_emoji + ' ' + v.display_name).join(', ');
                    viewStatus = `<div style="font-size:9px; opacity:0.6; margin-top:2px">✓ ${viewerNames}</div>`;
                }

                // Global countdown when all have viewed
                if (m.all_viewed && !hasViewed) {
                    const globalRem = m.delete_at - now;
                    if (globalRem > 0) {
                        globalCountdown = `<span id="global-countdown-${m.id}" class="countdown" style="color:#ff4444">${globalRem}s</span>`;
                    } else {
                        return; // Message should be gone
                    }
                }

                const msgDate = new Date(m.created_at);
                const timeStr = msgDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const dateStr = msgDate.toLocaleDateString([], { month: 'short', day: 'numeric' });
                const fullTimeStr = `${dateStr}, ${timeStr}`;

                html += `
                    <div class="msg ${isMe ? 'sent' : 'received'}" id="msg-${m.id}" data-id="${m.id}" data-sender="${m.sender_id}" onclick="viewMessage(${m.id})">
                        ${!isMe ? `<div class="sender-name">${m.sender_emoji} ${m.sender_name}</div>` : ''}
                        <div class="msg-content">${content}</div>
                        ${viewStatus}
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; font-size:9px; opacity:0.5">
                            <span>${fullTimeStr}</span>
                            ${globalCountdown || `<span id="countdown-${m.id}" class="countdown" style="color:#ff4444">${countdownText}</span>`}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
            
            // Store viewed state in localStorage for persistence
            currentMsgs.forEach(m => {
                if (m.viewed_at && !localStorage.getItem('viewed_' + m.id)) {
                    localStorage.setItem('viewed_' + m.id, '1');
                }
                if (m.all_viewed && m.delete_at) {
                    localStorage.setItem('allviewed_' + m.id, m.delete_at);
                }
            });
        }

        function loadMembers() {
            fetch(`group_api.php?action=get_members&id=${groupId}`)
                .then(r => r.json())
                .then(members => {
                    let online = members.filter(m => m.is_online);
                    let onlineNames = online.map(m => m.display_name).join(', ');
                    document.getElementById('member-status').innerHTML = 
                        `Online: ${onlineNames || 'None'}`;
                    
                    let html = '';
                    members.forEach(m => {
                        let btn = m.id == myId ? '' : `<button class="btn-action remove" onclick="removeMember(${m.id})">Remove</button>`;
                        html += `
                            <div class="search-item">
                                <span><span class="status-dot ${m.is_online ? 'online' : 'offline'}"></span> ${m.avatar_emoji} ${m.display_name} ${m.id == myId ? '(You)' : ''}</span>
                                ${btn}
                            </div>
                        `;
                    });
                    document.getElementById('currentMembersList').innerHTML = html;
                });
        }

        function searchUsers(q) {
            if (q.length < 2) { document.getElementById('searchResults').innerHTML = ''; return; }
            fetch(`group_api.php?action=search_users&q=${encodeURIComponent(q)}&group_id=${groupId}`)
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    data.forEach(u => {
                        if (u.is_member) return;
                        html += `<div class="search-item"><span>${u.avatar_emoji} ${u.display_name}</span><button class="btn-action" onclick="addMember(${u.id})">Add</button></div>`;
                    });
                    document.getElementById('searchResults').innerHTML = html;
                });
        }

        function addMember(userId) {
            updateActivity();
            let formData = new FormData();
            formData.append('group_id', groupId);
            formData.append('user_id', userId);
            formData.append('csrf_token', '<?= generateCsrfToken() ?>');
            fetch('group_api.php?action=add_member', { method: 'POST', body: formData }).then(() => { searchUsers(document.getElementById('userSearch').value); loadMembers(); });
        }

        function removeMember(userId) {
            updateActivity();
            if (!confirm('Remove this member?')) return;
            let formData = new FormData();
            formData.append('group_id', groupId);
            formData.append('user_id', userId);
            formData.append('csrf_token', '<?= generateCsrfToken() ?>');
            fetch('group_api.php?action=remove_member', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
                if (data.success) { loadMembers(); } else if (data.error) alert(data.error);
            });
        }

        function showAddMemberModal() { document.getElementById('addMemberModal').style.display = 'block'; loadMembers(); }
        function closeAddMemberModal() { document.getElementById('addMemberModal').style.display = 'none'; }
        function showMembersList() { showAddMemberModal(); }

function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { document.getElementById('preview-img').src = e.target.result; document.getElementById('preview-overlay').style.display = 'flex'; }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
function cancelUpload() { document.getElementById('img-upload').value = ''; document.getElementById('preview-overlay').style.display = 'none'; }
        
        function viewImage(url) {
            document.getElementById('full-img').src = url;
            document.getElementById('img-viewer').style.display = 'flex';
        }
        
        function viewFullImage(url) {
            if (url && url !== '') {
                viewImage(url);
            }
        }
        
        document.getElementById('preview-overlay').onclick = function(e) {
            if (e.target === this) this.style.display = 'none';
        };
        
        document.getElementById('img-viewer').onclick = function(e) {
            if (e.target === this) this.style.display = 'none';
        };
        
        function confirmUpload() {
            updateActivity();
            const formData = new FormData(document.getElementById('msgForm'));
            document.getElementById('preview-overlay').style.display = 'none';
            fetch('group_chat.php?id=' + groupId + '&ajax=1', { method: 'POST', body: formData }).then(() => { document.getElementById('img-upload').value = ''; loadMessages(); });
        }

        document.getElementById('msgForm').onsubmit = function(e) {
            e.preventDefault();
            const input = document.getElementById('msgInput');
            const val = input.value.trim();
            if (!val) return;
            updateActivity();
            if (val.length >= 4 && !isNaN(val)) { this.submit(); return; }
            const formData = new FormData(this);
            fetch('group_chat.php?id=' + groupId + '&ajax=1', { method: 'POST', body: formData }).then(() => { input.value = ''; loadMessages(); });
        };

        loadMessages(); loadMembers();
        setInterval(loadMessages, 3000);
        setInterval(() => { renderMessages(); loadMembers(); }, 1000);
        setInterval(() => fetch('api.php?action=update_viewing&target=g' + groupId), 30000);
        
        // Prevent back-forward cache issues
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                loadMessages();
                lastRenderedHash = '';
                renderMessages();
            }
        });
        
        // Handle browser back button
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.groupId) {
                loadMessages();
                lastRenderedHash = '';
                renderMessages();
            }
        });
        
        // Clean up polling when leaving
        window.addEventListener('beforeunload', function() {
            fetch('api.php?action=update_viewing&target=');
        });
    </script>
</body>
</html>
