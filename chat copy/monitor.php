<?php
session_start();
require_once 'db.php';
require_once 'group_db.php';

$adminPass = '5877';
$isAuth = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;

// Handle API actions BEFORE login check
$action = $_GET['action'] ?? '';
if ($action === 'ticker' || $action === 'live_feed') {
    header('Content-Type: application/json');
    
    $pdo = getDB();
    $pdoG = getGroupDB();
    
    // Private messages
    $stmt = $pdo->query("SELECT m.id, m.sender_id, m.receiver_id, m.message, m.original, m.type, m.created_at, u.display_name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id ORDER BY m.id DESC LIMIT 50");
    $msgs = $stmt->fetchAll();
    
    // Group messages
    $stmtG = $pdoG->query("SELECT gm.id, gm.group_id, gm.sender_id, gm.message, gm.original, gm.type, gm.created_at, u.display_name as sender_name, g.name as group_name FROM group_messages gm JOIN users u ON gm.sender_id = u.id JOIN groups g ON gm.group_id = g.id ORDER BY gm.id DESC LIMIT 50");
    $gMsgs = $stmtG->fetchAll();
    
    // Combine and sort
    $all = array_merge($msgs, $gMsgs);
    usort($all, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Get recent users (newest first)
    $stmt = $pdo->query("SELECT id, username, display_name, avatar_emoji FROM users ORDER BY id DESC LIMIT 10");
    $recentRegs = $stmt->fetchAll();
    
    // Get recent logins (last_active in last 10 min)
    $tenMinAgo = time() - 600;
    $stmt = $pdo->query("SELECT id, username, display_name, avatar_emoji, last_active FROM users WHERE last_active > $tenMinAgo ORDER BY last_active DESC LIMIT 10");
    $recentLogins = $stmt->fetchAll();
    
    $badWords = ['sex', 'nude', 'fuck', 'anal', 'boobs', 'pussy', 'dick', 'cock', 'bitch', 'ass', 'shit', 'damn', 'hell', 'cunt', 'whore', 'slut', 'xxx', '18+', 'adult', 'milf', 'orgasm'];
    
$badWords = ['sex', 'nude', 'fuck', 'anal', 'boobs', 'pussy', 'dick', 'cock', 'bitch', 'ass', 'shit', 'damn', 'hell', 'cunt', 'whore', 'slut', 'xxx', '18+', 'adult', 'milf', 'orgasm'];
    
    $hasBad = function($text) use ($badWords) {
        $lower = strtolower($text);
        foreach ($badWords as $w) {
            if (strpos($lower, $w) !== false) return true;
        }
        return false;
    };
    
    // Build ticker events
    $ticker = [];
    $nowTime = date('H:i');
    foreach($recentRegs as $r) {
        $ticker[] = ['time' => $nowTime, 'type' => 'reg', 'user' => $r['display_name'] ?: $r['username'], 'msg' => 'Registered', 'to' => '-'];
    }
    foreach($recentLogins as $l) {
        $ticker[] = ['time' => date('H:i', $l['last_active']), 'type' => 'login', 'user' => $l['display_name'] ?: $l['username'], 'msg' => 'Logged in', 'to' => '-'];
    }
    foreach(array_slice($all, 0, 15) as $m) {
        $msgType = $m['type'] === 'image' ? 'img' : 'msg';
        $msgText = $m['type'] === 'image' ? '📷 Image' : ($m['original'] ?: $m['message']);
        if (strlen($msgText) > 25) $msgText = substr($msgText, 0, 22) . '...';
        
        $to = '';
        $toId = 0;
        if (isset($m['group_id'])) {
            $to = $m['group_name'] ?: 'Group';
            $toId = $m['group_id'];
        } else {
            $receiver = getUserById($m['receiver_id']);
            $to = $receiver ? ($receiver['display_name'] ?: $receiver['username']) : 'User';
            $toId = $m['receiver_id'];
        }
        $ticker[] = ['time' => date('H:i', strtotime($m['created_at'])), 'type' => $msgType, 'user' => $m['sender_name'], 'msg' => $msgText, 'to' => $to, 'fromId' => $m['sender_id'], 'toId' => $toId, 'isGroup' => isset($m['group_id']) ? 1 : 0, 'bad' => $hasBad($msgText)];
    }
    
    // If empty
    if (empty($ticker)) {
        $ticker = [
            ['time' => $nowTime, 'type' => 'msg', 'user' => 'System', 'msg' => 'Waiting for activity...', 'to' => '-']
        ];
    }
    
    if ($action === 'ticker') {
        echo json_encode($ticker);
        exit;
    }
    
    $final = array_slice($all, 0, 50);
    foreach($final as &$m) {
        $m['time'] = date('H:i:s', strtotime($m['created_at']));
        $m['msg'] = $m['type'] === 'image' ? '📷 Image' : ($m['original'] ?: $m['message']);
        if ($m['type'] === 'image' && isset($m['original']) && $m['original']) {
            $m['real_url'] = $m['original'];
        } elseif ($m['type'] === 'image' && isset($m['message']) && strpos($m['message'], 'uploads/') === 0) {
            $m['real_url'] = $m['message'];
        }
        $m['is_group'] = isset($m['group_id']) ? 1 : 0;
    }
    
    echo json_encode(['messages' => $final, 'ticker' => $ticker]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    if ($_POST['pass'] === $adminPass) {
        $_SESSION['admin_auth'] = true;
        header('Location: monitor.php');
        exit;
    } else {
        $error = "Access Denied";
    }
}

if (!$isAuth): ?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Monitor</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f4f7fb; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; width: 90%; max-width: 300px; }
        input { width: 100%; padding: 12px; border: 1px solid #e0e6ed; border-radius: 12px; margin-bottom: 15px; text-align: center; font-size: 18px; outline: none; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="margin-bottom:20px;">Admin Monitor</h2>
        <form method="POST">
            <input type="password" name="pass" placeholder="Enter Access Key" autofocus>
            <button type="submit">Access System</button>
        </form>
        <?php if(isset($error)) echo "<p style='color:red;font-size:12px;margin-top:10px;'>$error</p>"; ?>
    </div>
</body>
</html>
<?php exit; endif;

// API Actions
$action = $_GET['action'] ?? '';

if ($action) {
    header('Content-Type: application/json');
    $pdo = getDB();
    
    if ($action === 'users') {
        $stmt = $pdo->query("SELECT id, username, display_name, avatar_emoji, last_active, viewing_target FROM users ORDER BY last_active DESC");
        $users = $stmt->fetchAll();
        $now = time();
        foreach($users as &$u) {
            $u['is_online'] = ($now - $u['last_active']) < 60;
            $diff = $now - $u['last_active'];
            if ($diff < 60) $u['status'] = 'Active now';
            elseif ($diff < 3600) $u['status'] = floor($diff/60).'m ago';
            elseif ($diff < 86400) $u['status'] = floor($diff/3600).'h ago';
            else $u['status'] = date('M j', $u['last_active']);
        }
        echo json_encode($users);
        exit;
    }
    
    if ($action === 'private_chats') {
        $stmt = $pdo->query("
            SELECT DISTINCT 
                CASE WHEN sender_id < receiver_id THEN sender_id ELSE receiver_id END as u1,
                CASE WHEN sender_id < receiver_id THEN receiver_id ELSE sender_id END as u2,
                MAX(id) as last_id
            FROM messages 
            GROUP BY u1, u2 
            ORDER BY last_id DESC
            LIMIT 50
        ");
        $chats = $stmt->fetchAll();
        foreach($chats as &$c) {
            $u1 = getUserById($c['u1']);
            $u2 = getUserById($c['u2']);
            $c['user1'] = $u1 ? $u1['display_name'] : 'Unknown';
            $c['user2'] = $u2 ? $u2['display_name'] : 'Unknown';
            
            $stmtMsg = $pdo->prepare("SELECT message, type, created_at FROM messages WHERE id = ?");
            $stmtMsg->execute([$c['last_id']]);
            $msg = $stmtMsg->fetch();
            $c['last_msg'] = $msg['type'] === 'image' ? '📷 Image' : $msg['message'];
            $c['last_time'] = $msg['created_at'];
        }
        echo json_encode($chats);
        exit;
    }
    
    if ($action === 'groups') {
        $pdoG = getGroupDB();
        
        $stmt = $pdoG->query("SELECT id, name, created_by, created_at FROM groups ORDER BY id DESC");
        $groups = $stmt->fetchAll();
        foreach($groups as &$g) {
            $creator = getUserById($g['created_by']);
            $g['creator'] = $creator ? $creator['display_name'] : 'Unknown';
            
            $stmt = $pdoG->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ?");
            $stmt->execute([$g['id']]);
            $g['members'] = $stmt->fetchColumn();
            
            $stmt = $pdoG->prepare("SELECT message, created_at FROM group_messages WHERE group_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$g['id']]);
            $msg = $stmt->fetch();
            $g['last_msg'] = $msg ? ($msg['message'] === '[Image]' ? '📷 Image' : $msg['message']) : 'No messages';
            $g['last_time'] = $msg ? $msg['created_at'] : null;
        }
        echo json_encode($groups);
        exit;
    }
    
    if ($action === 'group_detail') {
        $pdoG = getGroupDB();
        $groupId = (int)$_GET['id'];
        
        $stmt = $pdoG->query("SELECT id, name, created_by, created_at FROM groups WHERE id = $groupId");
        $group = $stmt->fetch();
        
        $stmt = $pdoG->query("SELECT id, username, display_name, avatar_emoji FROM users u JOIN group_members gm ON u.id = gm.user_id WHERE gm.group_id = $groupId");
        $members = $stmt->fetchAll();
        
        $stmt = $pdoG->query("SELECT id, message, original, type, sender_id, created_at FROM group_messages WHERE group_id = $groupId ORDER BY id DESC LIMIT 30");
        $msgs = array_reverse($stmt->fetchAll());
        foreach($msgs as &$m) {
            $sender = getUserById($m['sender_id']);
            $m['sender'] = $sender ? $sender['display_name'] : 'Unknown';
            // Show original content
            if ($m['original']) {
                $m['real_msg'] = $m['original'];
            }
        }
        
        echo json_encode(['group' => $group, 'members' => $members, 'messages' => $msgs]);
        exit;
    }
    
    if ($action === 'chat_detail') {
        $u1 = (int)$_GET['u1'];
        $u2 = (int)$_GET['u2'];
        
        $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE id IN (?, ?)");
        $stmt->execute([$u1, $u2]);
        $users = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY id DESC LIMIT 30");
        $stmt->execute([$u1, $u2, $u2, $u1]);
        $msgs = array_reverse($stmt->fetchAll());
        
        echo json_encode(['users' => array_column($users, 'display_name', 'id'), 'messages' => $msgs]);
        exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Monitor</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #1a1a2e; color: #fff; }
        
        .header { background: #16213e; padding: 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #0f3460; }
        .header h1 { font-size: 18px; color: #e94560; }
        .logout { background: #e94560; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; }
        
        .nav { background: #16213e; display: flex; gap: 5px; padding: 10px; overflow-x: auto; }
        .nav-btn { flex: 1; padding: 12px 8px; border: none; background: #0f3460; color: #aaa; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer; white-space: nowrap; }
        .nav-btn.active { background: #e94560; color: #fff; }
        
        .content { padding: 15px; }
        .section { display: none; }
        .section.active { display: block; }
        
        .card { background: #16213e; padding: 15px; border-radius: 12px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; }
        .card:hover { background: #1f2b4d; }
        
        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #6c757d; }
        .status-dot.online { background: #28a745; box-shadow: 0 0 8px #28a745; }
        
        .user-info { flex: 1; }
        .user-info .name { font-weight: bold; font-size: 15px; }
        .user-info .detail { font-size: 12px; color: #aaa; margin-top: 4px; }
        .user-info .viewing { color: #e94560; font-size: 11px; }
        
        .emoji { font-size: 24px; }
        
        .search { background: #16213e; border: none; padding: 12px; border-radius: 12px; width: 100%; color: #fff; font-size: 14px; margin-bottom: 15px; }
        .search:focus { outline: 2px solid #e94560; }
        
        .feed-table { width: 100%; border-collapse: collapse; background: #16213e; border-radius: 12px; overflow: hidden; }
        .feed-table th { background: #0f3460; padding: 12px 8px; text-align: left; font-size: 11px; color: #aaa; }
        .feed-table td { padding: 10px 8px; border-bottom: 1px solid #0f3460; font-size: 12px; }
        .feed-table tr:hover { background: #1f2b4d; }
        
        .badge { display: inline-block; background: #e94560; padding: 2px 8px; border-radius: 10px; font-size: 10px; margin-left: 5px; }
        .badge-group { background: #0f3460; }
        
        .chat-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #1a1a2e; z-index: 100; flex-direction: column; }
        .chat-modal.active { display: flex; }
        .chat-header { background: #16213e; padding: 15px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #0f3460; }
        .chat-header button { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
        .chat-header h2 { flex: 1; font-size: 16px; }
        .chat-body { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
        
        .msg-box { max-width: 80%; padding: 10px 14px; border-radius: 16px; font-size: 14px; }
        .msg-box.sent { align-self: flex-end; background: #e94560; }
        .msg-box.recv { align-self: flex-start; background: #16213e; }
        .msg-meta { font-size: 10px; opacity: 0.6; margin-top: 4px; }
        
        .empty { text-align: center; padding: 40px; color: #aaa; }
        
        /* News Ticker */
        .ticker-wrap { position: fixed; top: 60px; left: 0; right: 0; background: linear-gradient(90deg, #1a1a2e 0%, #16213e 50%, #1a1a2e 100%); overflow: hidden; height: 44px; z-index: 90; border-bottom: 1px solid #0f3460; }
        .ticker { display: flex; animation: ticker 30s linear infinite; white-space: nowrap; padding-top: 10px; }
        .ticker:hover { animation-play-state: paused; }
        @keyframes ticker { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
.ticker-item { display: inline-flex; align-items: center; gap: 8px; padding: 0 20px; border-right: 1px solid #333; cursor: pointer; }
        .ticker-item:hover { background: rgba(255,255,255,0.1); }
        .ticker-item:active { background: rgba(233,69,96,0.3); }
        .ticker-item.has-bad { background: rgba(255,0,0,0.3); border-left: 3px solid #ff0000; }
        .ticker-item.has-bad .ticker-msg { color: #ff6b6b; font-weight: bold; }
        
        .header { position: fixed; top: 44px; left: 0; right: 0; height: 60px; z-index: 100; }
        .nav { position: fixed; top: 104px; left: 0; right: 0; z-index: 95; }
        .ticker-wrap { position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(90deg, #1a1a2e 0%, #16213e 50%, #1a1a2e 100%); overflow: hidden; height: 44px; z-index: 90; border-bottom: 1px solid #0f3460; }
        .content { padding: 15px; padding-top: 170px; }
    </style>
</head>
<body>

<div class="header">
    <h1>🔴 Monitor</h1>
    <button class="logout" onclick="location.href='logout.php'">Logout</button>
</div>

<div class="nav">
    <button class="nav-btn active" onclick="showTab('users', this)">Users <span id="user-count" class="badge">0</span></button>
    <button class="nav-btn" onclick="showTab('chats', this)">Chats <span id="chat-count" class="badge">0</span></button>
    <button class="nav-btn" onclick="showTab('groups', this)">Groups <span id="group-count" class="badge badge-group">0</span></button>
    <button class="nav-btn" onclick="showTab('feed', this)">Live Feed</button>
</div>

<div class="ticker-wrap">
    <div class="ticker" id="ticker"></div>
</div>

<div class="content">
    <!-- Users Tab -->
    <div id="tab-users" class="section active">
        <input type="text" class="search" id="user-search" placeholder="Search users..." oninput="filterUsers()">
        <div id="user-list"></div>
    </div>
    
    <!-- Chats Tab -->
    <div id="tab-chats" class="section">
        <div id="chat-list"></div>
    </div>
    
    <!-- Groups Tab -->
    <div id="tab-groups" class="section">
        <div id="group-list"></div>
    </div>
    
    <!-- Live Feed Tab -->
    <div id="tab-feed" class="section">
        <table class="feed-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody id="feed-list"></tbody>
        </table>
    </div>
</div>

<!-- Chat Detail Modal -->
<div id="chat-modal" class="chat-modal">
    <div class="chat-header">
        <button onclick="closeModal()">&#8674;</button>
        <h2 id="modal-title">Chat</h2>
        <button onclick="closeModal()" style="background:none;border:none;color:#fff;font-size:18px;">&#10005;</button>
    </div>
    <div id="modal-body" class="chat-body"></div>
</div>

<!-- Image Viewer -->
<div id="img-viewer" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.95);z-index:200;align-items:center;justify-content:center;" onclick="this.style.display='none'">
    <img id="viewer-img" src="" style="max-width:90%;max-height:90%;border-radius:8px;" onclick="event.stopPropagation()">
</div>

<script>
let currentTab = 'users';
let allUsers = [];
let allChats = [];
let allGroups = [];
let allFeed = [];

function showTab(tab, btn) {
    currentTab = tab;
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    if (btn) btn.classList.add('active');
    
    if (tab === 'users') loadUsers();
    else if (tab === 'chats') loadChats();
    else if (tab === 'groups') loadGroups();
    else if (tab === 'feed') loadFeed();
}

function loadUsers() {
    fetch('?action=users').then(r => r.json()).then(data => {
        allUsers = data;
        document.getElementById('user-count').textContent = data.length;
        renderUsers();
    });
}

function renderUsers() {
    const search = document.getElementById('user-search').value.toLowerCase();
    const list = document.getElementById('user-list');
    
    let html = '';
    let count = 0;
    allUsers.forEach(u => {
        const name = (u.display_name + ' ' + u.username).toLowerCase();
        if (search && name.indexOf(search) === -1) return;
        count++;
        html += `<div class="card">
            <div class="status-dot ${u.is_online ? 'online' : ''}"></div>
            <div class="user-info">
                <div class="name">${u.avatar_emoji} ${u.display_name}</div>
                <div class="detail">@${u.username} · ${u.status}</div>
                <div class="viewing">${u.viewing_target ? 'Viewing: ' + u.viewing_target : ''}</div>
            </div>
        </div>`;
    });
    list.innerHTML = count ? html : '<div class="empty">No users found</div>';
}

function filterUsers() { renderUsers(); }

function loadChats() {
    fetch('?action=private_chats').then(r => r.json()).then(data => {
        allChats = data;
        document.getElementById('chat-count').textContent = data.length;
        
        let html = '';
        data.forEach(c => {
            html += `<div class="card" onclick="viewChat(${c.u1}, ${c.u2}, '${c.user1} & ${c.user2}')">
                <div class="emoji">💬</div>
                <div class="user-info">
                    <div class="name">${c.user1} & ${c.user2}</div>
                    <div class="detail">${c.last_msg}</div>
                </div>
            </div>`;
        });
        document.getElementById('chat-list').innerHTML = html || '<div class="empty">No chats yet</div>';
    });
}

function loadGroups() {
    fetch('?action=groups').then(r => r.json()).then(data => {
        allGroups = data;
        document.getElementById('group-count').textContent = data.length;
        
        let html = '';
        data.forEach(g => {
            html += `<div class="card" onclick="viewGroup(${g.id}, '${g.name}')">
                <div class="emoji">👥</div>
                <div class="user-info">
                    <div class="name">${g.name}</div>
                    <div class="detail">${g.members} members · Created by ${g.creator}</div>
                    <div class="viewing">${g.last_msg}</div>
                </div>
            </div>`;
        });
        document.getElementById('group-list').innerHTML = html || '<div class="empty">No groups yet</div>';
    });
}

function loadFeed() {
    fetch('?action=live_feed').then(r => r.json()).then(data => {
        const msgs = data.messages || [];
        allFeed = msgs;
        
        let html = '';
        msgs.forEach(m => {
            const to = m.is_group ? m.group_name : m.receiver_id;
            const onclick = m.is_group ? `viewGroup(${m.group_id}, '${m.group_name}')` : `viewChat(${m.sender_id}, ${m.receiver_id}, '${m.sender_name} to ${m.receiver_id}')`;
            
            // Only treat as image if type is 'image' and we have a valid URL
            let msgContent = m.msg;
            if (m.type === 'image') {
                let imgUrl = m.real_url || m.original || m.message;
                // Check if it's actually a valid image URL (starts with uploads/)
                if (imgUrl && imgUrl.indexOf('uploads/') === 0) {
                    msgContent = `<span style="cursor:pointer;color:#e94560;font-weight:bold" onclick="event.stopPropagation();viewImage('${imgUrl}')">📷 Image</span>`;
                } else {
                    msgContent = '📷 Image';
                }
            }
            
            html += `<tr onclick="${onclick}" style="cursor:pointer">
                <td style="color:#aaa">${m.time}</td>
                <td><b>${m.sender_name}</b></td>
                <td>${to}</td>
                <td>${msgContent}</td>
            </tr>`;
        });
        document.getElementById('feed-list').innerHTML = html || '<tr><td colspan="4" class="empty">No messages</td></tr>';
    });
}

let modalType = '';

function viewChat(u1, u2, title) {
    modalType = 'chat';
    document.getElementById('modal-title').textContent = title;
    document.getElementById('chat-modal').classList.add('active');
    
    fetch('?action=chat_detail&u1=' + u1 + '&u2=' + u2).then(r => r.json()).then(data => {
        const userNames = data.users;
        let html = '';
        data.messages.forEach(m => {
            const senderName = userNames[m.sender_id] || 'Unknown';
            let content = m.message;
            if (m.type === 'image' && m.message) {
                content = `<img src="${m.message}" style="max-width:100%;border-radius:8px;cursor:pointer" onclick="viewImage('${m.message}')">`;
            }
            html += `<div class="msg-box ${m.sender_id == u1 ? 'sent' : 'recv'}">
                <div style="font-size:10px;opacity:0.7;margin-bottom:4px;">${senderName}</div>
                ${content}
                <div class="msg-meta">${new Date(m.created_at).toLocaleString()}</div>
            </div>`;
        });
        document.getElementById('modal-body').innerHTML = html || '<div class="empty">No messages</div>';
    });
    
    loadModalUpdates('chat', u1, u2, 0, title);
}

function viewGroup(groupId, title) {
    modalType = 'group';
    document.getElementById('modal-title').textContent = '[Group] ' + title;
    document.getElementById('chat-modal').classList.add('active');
    
    fetch('?action=group_detail&id=' + groupId).then(r => r.json()).then(data => {
        let html = '<div style="background:#0f3460;padding:10px;border-radius:8px;margin-bottom:10px;">';
        html += '<b>Members:</b><br>';
        data.members.forEach(m => {
            html += `<span style="display:inline-block;background:#16213e;padding:4px 8px;border-radius:12px;margin:2px;font-size:11px;">${m.avatar_emoji} ${m.display_name}</span>`;
        });
        html += '</div>';
        
        data.messages.forEach(m => {
            let content = m.original || m.message;
            if (m.type === 'image' && content) {
                content = `<img src="${content}" style="max-width:100%;border-radius:8px;cursor:pointer" onclick="viewImage('${content}')">`;
            }
            html += `<div class="msg-box recv">
                <div style="font-size:10px;opacity:0.7;margin-bottom:4px;">${m.sender}</div>
                ${content}
                <div class="msg-meta">${new Date(m.created_at).toLocaleString()}</div>
            </div>`;
        });
        document.getElementById('modal-body').innerHTML = html || '<div class="empty">No messages</div>';
    });
    
    loadModalUpdates('group', 0, 0, groupId, title);
}

function viewImage(url) {
    console.log('viewImage called with:', url);
    if (!url || url === 'undefined' || url === 'null' || url.length < 5) {
        alert('Image not available. URL: ' + url);
        return;
    }
    var img = document.getElementById('viewer-img');
    if (img) {
        img.src = url;
        var viewer = document.getElementById('img-viewer');
        if (viewer) viewer.style.display = 'flex';
    }
}

function closeModal() {
    document.getElementById('chat-modal').classList.remove('active');
}

let modalInterval = null;

function loadModalUpdates(type, u1, u2, groupId, title) {
    if (modalInterval) clearInterval(modalInterval);
    
    function update() {
        if (type === 'chat') viewChat(u1, u2, title);
        else viewGroup(groupId, title);
    }
    
    modalInterval = setInterval(update, 3000);
}

// Initial load
loadUsers();
setInterval(() => {
    if (currentTab === 'users') loadUsers();
    else if (currentTab === 'chats') loadChats();
    else if (currentTab === 'groups') loadGroups();
    else if (currentTab === 'feed') loadFeed();
}, 5000);

// Ticker real-time update
function loadTicker() {
    fetch('monitor.php?action=ticker&t=' + Date.now(), { cache: 'no-store' }).then(r => r.ok ? r.json() : []).then(data => {
        const el = document.getElementById('ticker');
        if (!data || data.length === 0) {
            el.innerHTML = '<div class="ticker-item" style="border:none;"><span class="ticker-msg" style="color:#aaa;">Waiting for activity...</span></div>';
            return;
        }
        const html = data.map(t => 
            `<div class="ticker-item ${t.bad ? 'has-bad' : ''}" data-from="${t.fromId || 0}" data-to="${t.toId || 0}" data-isgrp="${t.isGroup || 0}" data-user="${t.user || ''}" data-to-name="${t.to || ''}">
                <span class="ticker-time">${t.time}</span>
                <span class="ticker-type ${t.type}">${t.type.toUpperCase()}</span>
                <span class="ticker-user">${t.user}</span>
                <span class="ticker-arrow">→</span>
                <span class="ticker-to">${t.to || '-'}</span>
                <span class="ticker-msg">${t.msg}</span>
            </div>`
        ).join('');
        el.innerHTML = html + html;
        
        // Add click handlers
        el.querySelectorAll('.ticker-item').forEach(item => {
            item.onclick = () => {
                const fromId = parseInt(item.dataset.from) || 0;
                const toId = parseInt(item.dataset.to) || 0;
                const isGroup = parseInt(item.dataset.isgrp) || 0;
                const user = item.dataset.user || '';
                const toName = item.dataset.toName || '';
                
                if (isGroup) {
                    viewGroup(toId, toName);
                } else if (toId && fromId) {
                    viewChat(fromId, toId, user + ' → ' + toName);
                } else {
                    alert('From: ' + user + '\nTo: ' + toName);
                }
            };
        });
        el.innerHTML = html + html;
    }).catch(() => {
        document.getElementById('ticker').innerHTML = '<div class="ticker-item" style="border:none;"><span class="ticker-msg" style="color:#aaa;">Loading ticker...</span></div>';
    });
}
loadTicker();
setInterval(() => loadTicker(), 8000);

// Refresh on visibility change (tab switching, minimize, close- reopen)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        loadUsers(); loadChats(); loadGroups(); loadFeed(); loadTicker();
    }
});

// Prevent back-forward cache issues
window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
        if (currentTab === 'users') loadUsers();
        else if (currentTab === 'chats') loadChats();
        else if (currentTab === 'groups') loadGroups();
        else if (currentTab === 'feed') loadFeed();
    }
});
</script>

</body>
</html>