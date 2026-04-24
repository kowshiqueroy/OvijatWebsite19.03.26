<?php
session_start();
require_once 'db.php';

$adminPass = '5877';
$isAuth = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Access</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f4f7fb; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; width: 90%; max-width: 320px; }
        input { width: 100%; padding: 12px; border: 1px solid #e0e6ed; border-radius: 12px; margin-bottom: 15px; text-align: center; font-size: 18px; outline: none; }
        button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="margin-bottom:20px; font-size: 18px;">Admin Monitor</h2>
        <form method="POST">
            <input type="password" name="pass" placeholder="Enter Access Key" autofocus>
            <button type="submit">Access System</button>
        </form>
        <?php if(isset($error)) echo "<p style='color:red;font-size:12px;margin-top:10px;'>$error</p>"; ?>
    </div>
</body>
</html>
<?php exit; endif;

// --- Admin API Logic ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $pdo = getDB();
    
    if ($_GET['action'] === 'get_users') {
        $stmt = $pdo->query("SELECT id, username, display_name, last_active, viewing_target, avatar_emoji FROM users ORDER BY last_active DESC");
        $users = $stmt->fetchAll();
        foreach($users as &$u) {
            $u['is_online'] = (time() - $u['last_active'] < 60);
            $u['status_text'] = getStatusText($u['last_active']);
        }
        echo json_encode($users);
        exit;
    }

    if ($_GET['action'] === 'get_chats') {
        $stmt = $pdo->query("
            SELECT DISTINCT 
                CASE WHEN sender_id < receiver_id THEN sender_id ELSE receiver_id END as u1,
                CASE WHEN sender_id < receiver_id THEN receiver_id ELSE sender_id END as u2,
                MAX(created_at) as last_msg
            FROM messages 
            GROUP BY u1, u2 
            ORDER BY last_msg DESC
        ");
        $chats = $stmt->fetchAll();
        foreach($chats as &$c) {
            $u1 = getUserById($c['u1']);
            $u2 = getUserById($c['u2']);
            $c['u1_name'] = $u1['display_name'];
            $c['u2_name'] = $u2['display_name'];
        }
        echo json_encode($chats);
        exit;
    }

    if ($_GET['action'] === 'view_chat') {
        $u1 = (int)$_GET['u1'];
        $u2 = (int)$_GET['u2'];
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY id ASC LIMIT 100");
        $stmt->execute([$u1, $u2, $u2, $u1]);
        $msgs = $stmt->fetchAll();
        foreach($msgs as &$m) {
            $m['time'] = date('H:i', strtotime($m['created_at']));
            $m['real_msg'] = $m['original'] ?: $m['message'];
        }
        echo json_encode($msgs);
        exit;
    }

    if ($_GET['action'] === 'get_live_feed') {
        $stmt = $pdo->query("SELECT m.*, uS.display_name as sender_name, uR.display_name as receiver_name FROM messages m JOIN users uS ON m.sender_id = uS.id JOIN users uR ON m.receiver_id = uR.id ORDER BY m.id DESC LIMIT 100");
        $msgs = $stmt->fetchAll();
        foreach($msgs as &$m) {
            $m['full_time'] = date('M j, H:i:s', strtotime($m['created_at']));
            $m['real_msg'] = $m['original'] ?: $m['message'];
            $m['del_time'] = $m['delete_at'] > 0 ? date('H:i:s', $m['delete_at']) : 'Never';
        }
        echo json_encode($msgs);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Monitor</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, sans-serif; background: #f8f9fa; color: #333; }
        .nav { background: #fff; padding: 15px; display: flex; gap: 8px; border-bottom: 1px solid #ddd; position: sticky; top: 0; z-index: 100; overflow-x: auto; }
        .nav-btn { flex: 1; padding: 10px; border: none; background: #eee; border-radius: 8px; font-weight: bold; font-size: 12px; cursor: pointer; white-space: nowrap; }
        .nav-btn.active { background: #007bff; color: #fff; }
        
        .container { padding: 15px; }
        .section { display: none; }
        .section.active { display: block; }
        
        .filters { display: flex; gap: 8px; margin-bottom: 15px; }
        .filters input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; outline: none; }

        .card { background: #fff; padding: 12px; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 12px; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; }
        .online { background: #28a745; }
        .offline { background: #adb5bd; }

        .feed-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 11px; }
        .feed-table th, .feed-table td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; }
        .feed-table th { background: #f0f2f5; font-weight: bold; }
        .feed-table tr:last-child td { border-bottom: none; }
        .feed-msg { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #555; }
        
        .chat-view { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #fff; z-index: 200; display: none; flex-direction: column; }
        .chat-header { padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; justify-content: space-between; background: #f8f9fa; }
        .chat-content { flex: 1; overflow-y: auto; padding: 15px; background: #f0f2f5; display: flex; flex-direction: column; gap: 8px; }
        
        .m-msg { max-width: 80%; padding: 8px 12px; border-radius: 12px; font-size: 14px; position: relative; }
        .m-sent { align-self: flex-end; background: #007bff; color: #fff; }
        .m-recv { align-self: flex-start; background: #fff; color: #333; }
        .m-meta { font-size: 9px; opacity: 0.7; margin-top: 4px; display: block; }
    </style>
</head>
<body>

<div class="nav">
    <button class="nav-btn active" onclick="showSec('users', this)">Users</button>
    <button class="nav-btn" onclick="showSec('chats', this)">Chats</button>
    <button class="nav-btn" onclick="showSec('feed', this)">Live Feed</button>
    <button class="nav-btn" onclick="location.href='logout.php'" style="flex:0; background:#fff0f0; color:red">🚪</button>
</div>

<div class="container">
    <div id="sec-users" class="section active">
        <div id="user-list"></div>
    </div>
    
    <div id="sec-chats" class="section">
        <div id="chat-list"></div>
    </div>

    <div id="sec-feed" class="section">
        <div class="filters">
            <input type="text" id="filter-user" placeholder="Filter by user..." oninput="loadFeed()">
            <input type="text" id="filter-text" placeholder="Filter by message..." oninput="loadFeed()">
        </div>
        <table class="feed-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Users</th>
                    <th>Msg</th>
                    <th>Expires</th>
                </tr>
            </thead>
            <tbody id="feed-list"></tbody>
        </table>
    </div>
</div>

<div id="chat-overlay" class="chat-view">
    <div class="chat-header">
        <button onclick="closeChat()" style="border:none; background:none; font-size: 20px;">&#10094;</button>
        <div id="chat-title" style="font-weight:bold; font-size: 14px;">Monitor</div>
        <div style="width:20px"></div>
    </div>
    <div id="chat-body" class="chat-content"></div>
</div>

<script>
let currentU1 = null, currentU2 = null;
let pollInterval = null;
let currentTab = 'users';
let allFeedMsgs = [];

function showSec(id, btn) {
    currentTab = id;
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('sec-'+id).classList.add('active');
    if (btn) btn.classList.add('active');
    
    if(id === 'users') loadUsers();
    if(id === 'chats') loadChats();
    if(id === 'feed') loadFeed();
}

function loadUsers() {
    if(currentTab !== 'users') return;
    fetch('monitor.php?action=get_users').then(r => r.json()).then(data => {
        let html = '';
        data.forEach(u => {
            html += `<div class="card">
                <div class="status-dot ${u.is_online ? 'online' : 'offline'}"></div>
                <div style="flex:1">
                    <div style="font-weight:bold">${u.display_name} (${u.username})</div>
                    <div style="font-size:11px; color:#666">${u.is_online ? 'Active' : 'Last seen: ' + u.status_text}</div>
                    <div style="font-size:10px; color:#007bff">${u.viewing_target ? 'Viewing: ' + u.viewing_target : 'Idle'}</div>
                </div>
                <div style="font-size:20px">${u.avatar_emoji}</div>
            </div>`;
        });
        document.getElementById('user-list').innerHTML = html;
    });
}

function loadChats() {
    if(currentTab !== 'chats') return;
    fetch('monitor.php?action=get_chats').then(r => r.json()).then(data => {
        let html = '';
        data.forEach(c => {
            html += `<div class="card" onclick="openChat(${c.u1}, ${c.u2}, '${c.u1_name} & ${c.u2_name}')">
                <div style="flex:1">
                    <div style="font-weight:bold">${c.u1_name} & ${c.u2_name}</div>
                    <div style="font-size:11px; color:#666">Last Activity: ${c.last_msg}</div>
                </div>
                <div style="font-size:18px; opacity:0.5">&#10095;</div>
            </div>`;
        });
        document.getElementById('chat-list').innerHTML = html;
    });
}

function loadFeed() {
    if(currentTab !== 'feed') return;
    fetch('monitor.php?action=get_live_feed').then(r => r.json()).then(data => {
        allFeedMsgs = data;
        renderFeed();
    });
}

function renderFeed() {
    const userFilter = document.getElementById('filter-user').value.toLowerCase();
    const textFilter = document.getElementById('filter-text').value.toLowerCase();
    
    let html = '';
    allFeedMsgs.forEach(m => {
        const u1 = m.sender_id < m.receiver_id ? m.sender_id : m.receiver_id;
        const u2 = m.sender_id < m.receiver_id ? m.receiver_id : m.sender_id;
        const title = m.sender_id < m.receiver_id ? `${m.sender_name} & ${m.receiver_name}` : `${m.receiver_name} & ${m.sender_name}`;
        
        const senderMatch = m.sender_name.toLowerCase().includes(userFilter) || m.receiver_name.toLowerCase().includes(userFilter);
        const textMatch = m.real_msg.toLowerCase().includes(textFilter);
        
        if (senderMatch && textMatch) {
            html += `<tr onclick="openChat(${u1}, ${u2}, '${title}')" style="cursor:pointer">
                <td style="color:#888">${m.full_time}</td>
                <td>
                    <div style="font-weight:bold; color:#007bff">${m.sender_name}</div>
                    <div style="font-size:9px; color:#aaa">to ${m.receiver_name}</div>
                </td>
                <td><div class="feed-msg">${m.type === 'image' ? '📷 Image' : m.real_msg}</div></td>
                <td style="color:#dc3545">${m.del_time}</td>
            </tr>`;
        }
    });
    document.getElementById('feed-list').innerHTML = html;
}

function openChat(u1, u2, title) {
    currentU1 = u1; currentU2 = u2;
    document.getElementById('chat-title').textContent = title;
    document.getElementById('chat-overlay').style.display = 'flex';
    updateChat();
    pollInterval = setInterval(updateChat, 3000);
}

function closeChat() {
    document.getElementById('chat-overlay').style.display = 'none';
    clearInterval(pollInterval);
}

function updateChat() {
    if(!currentU1) return;
    fetch(`monitor.php?action=view_chat&u1=${currentU1}&u2=${currentU2}`)
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(m => {
                const isU1 = m.sender_id == currentU1;
                html += `<div class="m-msg ${isU1 ? 'm-sent' : 'm-recv'}">
                    <div style="font-size:10px; opacity:0.6; margin-bottom:2px;">Sender ID: ${m.sender_id}</div>
                    ${m.type === 'image' ? `<img src="${m.message}" style="max-width:100%; border-radius:8px">` : m.real_msg}
                    <span class="m-meta">${m.time} | Deletes at: ${m.delete_at > 0 ? new Date(m.delete_at*1000).toLocaleTimeString() : 'Never'}</span>
                </div>`;
            });
            const body = document.getElementById('chat-body');
            body.innerHTML = html;
            body.scrollTop = body.scrollHeight;
        });
}

loadUsers();
setInterval(() => {
    if(currentTab === 'users') loadUsers();
    if(currentTab === 'chats') loadChats();
    if(currentTab === 'feed') loadFeed();
}, 3000);
</script>
</body>
</html>