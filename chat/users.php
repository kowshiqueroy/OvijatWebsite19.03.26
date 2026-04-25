<?php
session_start();
require_once 'db.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$user = getCurrentUser();
if (!$user) { header('Location: index.php'); exit; }

updateLastActive($_SESSION['user_id'], '');

if (empty($user['pin'])) { header('Location: settings.php?setup=1'); exit; }

// Update viewing target on page load
$csrfToken = generateCsrfToken();

$searchQuery = sanitize($_GET['q'] ?? '');
$isSearching = !empty($searchQuery);

if ($isSearching) {
    $users = searchNewUsers($_SESSION['user_id'], $searchQuery);
} else {
    $users = getConnectedUsers($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>kotha.sohojweb.com - Messages</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f4f7fb; 
            color: #1a1a1a;
            user-select: none;
            -webkit-user-select: none;
            overflow-x: hidden;
        }

        .header { 
            background: #fff; 
            color: #1a1a1a; 
            padding: 16px 20px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            border-bottom: 1px solid #e0e6ed;
        }
        .header h1 { font-size: 18px; font-weight: 800; color: #007bff; letter-spacing: -0.5px; }
        .header .user-info { display: flex; align-items: center; gap: 12px; }
        .header .avatar { 
            font-size: 20px; 
            background: #f0f2f5; 
            width: 38px; 
            height: 38px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 10px; 
        }

        .search-container { padding: 12px 16px; background: #fff; border-bottom: 1px solid #e0e6ed; }
        .search-container form { display: flex; gap: 8px; }
        .search-container input { 
            flex: 1; 
            padding: 10px 16px; 
            border: 1px solid #e0e6ed; 
            border-radius: 12px; 
            font-size: 14px; 
            outline: none; 
            background: #f8f9fa;
        }
        .search-container button { 
            background: #007bff; 
            color: #fff; 
            border: none; 
            padding: 0 16px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 13px; 
        }

        .user-list { padding: 12px; padding-bottom: 90px; max-width: 600px; margin: 0 auto; }
        .section-title { font-size: 11px; color: #6c757d; margin: 16px 8px 8px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        
        .user { 
            display: flex; 
            align-items: center; 
            padding: 14px; 
            background: #fff; 
            border-radius: 16px; 
            margin-bottom: 10px; 
            cursor: pointer; 
            transition: 0.2s; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            position: relative;
        }
        .user:active { background: #f8f9fa; transform: scale(0.98); }
        .user .avatar-box { 
            width: 50px; 
            height: 50px; 
            border-radius: 14px; 
            background: #f0f2f5; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 24px; 
            flex-shrink: 0; 
            position: relative; 
        }
        .status-dot { 
            width: 12px; 
            height: 12px; 
            background: #28a745; 
            border: 2px solid #fff; 
            border-radius: 50%; 
            position: absolute; 
            bottom: -2px; 
            right: -2px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .status-dot.offline { background: #adb5bd; }

        .info { margin-left: 14px; flex: 1; overflow: hidden; }
        .info-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; }
        .info h3 { font-size: 15px; color: #1a1a1a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
        .info p { font-size: 13px; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }

        .status-tag { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 6px; }
        .status-tag.online { color: #28a745; background: #e8f5e9; }
        .status-tag.offline { color: #6c757d; background: #f8f9fa; }
        .status-tag.in-chat { color: #007bff; background: #e7f3ff; }
        .status-tag.other-chat { color: #fd7e14; background: #fff4e6; }
        
        .badge { 
            background: #007bff; 
            color: #fff; 
            font-size: 11px; 
            padding: 2px 6px; 
            border-radius: 8px; 
            min-width: 20px; 
            text-align: center; 
            font-weight: 700; 
        }
        .tick { font-size: 14px; margin-right: 2px; }
        .tick.read { color: #007bff; }
        
        .tab-bar { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            background: #fff; 
            display: flex; 
            border-top: 1px solid #e0e6ed; 
            z-index: 100; 
            padding: 8px 0; 
            padding-bottom: calc(8px + env(safe-area-inset-bottom)); 
        }
        .tab-item { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 4px; 
            color: #6c757d; 
            cursor: pointer; 
            text-decoration: none; 
            transition: 0.2s; 
        }
        .tab-item.active { color: #007bff; }
        .tab-icon { font-size: 20px; }
        .tab-label { font-size: 10px; font-weight: 600; }

        .empty { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-icon { font-size: 40px; margin-bottom: 12px; opacity: 0.3; }
    </style>
</head>
<body>
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <h3>Create New Group</h3>
            <input type="text" id="groupNameInput" placeholder="Enter Group Name..." style="margin-top:15px">
            <div style="display:flex; gap:10px; margin-top:10px">
                <button onclick="submitCreateGroup()" style="flex:1; background:#007bff; color:#fff; border:none; padding:10px; border-radius:8px">Create</button>
                <button onclick="closeCreateGroupModal()" style="flex:1; background:#eee; border:none; padding:10px; border-radius:8px">Cancel</button>
            </div>
        </div>
    </div>

    <style>
        .modal { display: none; position: fixed; z-index: 200; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: #fff; margin: 20% auto; padding: 20px; border-radius: 20px; width: 85%; max-width: 350px; }
        .modal-content h3 { font-size: 18px; margin-bottom: 10px; }
        .modal-content input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 12px; outline: none; }
    </style>

    <script>
    const myId = <?= $_SESSION['user_id'] ?>;
    const searchQuery = '<?= addslashes($searchQuery) ?>';
    let currentTab = 'messages';

    function updateView(t) {
        fetch('api.php?action=update_viewing&target=' + (t || ''));
    }
    function refreshList() {
        let url;
        if (currentTab === 'messages') {
            url = 'api.php?action=conversations&_=' + Date.now();
            if (searchQuery) url += '&q=' + encodeURIComponent(searchQuery);
        } else {
            url = 'group_api.php?action=groups&_=' + Date.now();
        }
        
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const wrapper = document.getElementById('user-list-wrapper');
                if (wrapper) wrapper.innerHTML = html;
            });
    }

    function switchTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        refreshList();
    }

    function showCreateGroupModal() {
        document.getElementById('createGroupModal').style.display = 'block';
        document.getElementById('groupNameInput').focus();
    }

    function closeCreateGroupModal() {
        document.getElementById('createGroupModal').style.display = 'none';
    }

    function submitCreateGroup() {
        const name = document.getElementById('groupNameInput').value.trim();
        if (name) {
            let formData = new FormData();
            formData.append('name', name);
            fetch('group_api.php?action=create_group', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.href = 'group_chat.php?id=' + data.group_id;
                }
            });
        }
    }

    updateView('');
    setInterval(() => updateView(''), 30000);
    setInterval(refreshList, 5000);
    window.addEventListener('focus', () => { updateView(''); refreshList(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshList(); });
    
    // Check if should open groups tab
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('openGroups') === '1') {
        currentTab = 'groups';
        try { switchTab('groups'); } catch(e) {}
    }
    
    refreshList();
    </script>
    <div class="header">
        <h1>kotha.sohojweb.com</h1>
        <div class="user-info">
            <div class="avatar"><?= $user['avatar_emoji'] ?></div>
            <a href="logout.php" style="color:#6c757d;text-decoration:none;margin-left:8px;font-size:18px;" title="Logout">🚪</a>
        </div>
    </div>
    
    <div class="search-container">
        <form method="GET">
            <input type="text" name="q" value="<?= $searchQuery ?>" placeholder="Search users..." autocomplete="off">
            <?php if ($isSearching): ?>
                <button type="button" onclick="location.href='users.php'" style="background:#adb5bd">✕</button>
            <?php endif; ?>
            <button type="submit">Search</button>
        </form>
    </div>
    
    <div class="user-list" id="user-list-wrapper">
        <div class="empty" style="padding: 40px 0; opacity: 0.5;">
            <p>Loading...</p>
        </div>
    </div>
    
    <div class="tab-bar">
        <a href="javascript:void(0)" id="tab-messages" class="tab-item active" onclick="switchTab('messages')">
            <span class="tab-icon">💬</span>
            <span class="tab-label">Messages</span>
        </a>
        <a href="javascript:void(0)" id="tab-groups" class="tab-item" onclick="switchTab('groups')">
            <span class="tab-icon">👥</span>
            <span class="tab-label">Groups</span>
        </a>
        <a href="settings.php" class="tab-item">
            <span class="tab-icon">⚙️</span>
            <span class="tab-label">Settings</span>
        </a>
    </div>
</body>
</html>