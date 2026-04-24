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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>kotha.SohojWeb.com</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: #ECE5DD; }
        .header { background: #128C7E; color: #fff; padding: 15px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { font-size: 20px; font-weight: bold; }
        .header .user-info { display: flex; align-items: center; gap: 10px; }
        .header .avatar { font-size: 24px; background: rgba(255,255,255,0.2); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }

        .search-container { padding: 12px; background: #128C7E; position: sticky; top: 56px; z-index: 99; }
        .search-container form { display: flex; gap: 8px; }
        .search-container input { flex: 1; padding: 12px 18px; border: none; border-radius: 25px; font-size: 15px; outline: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .search-container button { background: #25D366; color: #fff; border: none; padding: 0 20px; border-radius: 25px; cursor: pointer; font-weight: bold; font-size: 14px; }

        .user-list { padding: 10px; padding-bottom: 90px; max-width: 600px; margin: 0 auto; }
        .section-title { font-size: 12px; color: #667781; margin: 15px 10px 10px; text-transform: uppercase; letter-spacing: 1.2px; font-weight: 700; }
        
        .user { display: flex; align-items: center; padding: 15px; background: #fff; border-radius: 15px; margin-bottom: 10px; cursor: pointer; transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: relative; }
        .user:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.12); }
        .user .avatar-box { width: 55px; height: 55px; border-radius: 50%; background: #f0f2f5; display: flex; align-items: center; justify-content: center; font-size: 30px; flex-shrink: 0; position: relative; }
        .status-dot { width: 14px; height: 14px; background: #25D366; border: 2.5px solid #fff; border-radius: 50%; position: absolute; bottom: 2px; right: 2px; }
        .status-dot.offline { background: #bbb; }

        .info { margin-left: 15px; flex: 1; overflow: hidden; }
        .info-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .info h3 { font-size: 17px; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
        .info p { font-size: 14px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }

        .status-tag { font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
        .status-tag.online { color: #25D366; }
        .status-tag.offline { color: #888; }
        .status-tag.in-chat { color: #128C7E; background: #e7f3f1; }
        .status-tag.other-chat { color: #f0ad4e; background: #fff8e6; }
        
        .badge { background: #25D366; color: #fff; font-size: 12px; padding: 3px 8px; border-radius: 15px; min-width: 22px; text-align: center; font-weight: bold; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .tick { font-size: 16px; margin-right: 2px; }
        .tick.read { color: #128CFF; }
        
        .tab-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; display: flex; border-top: 1px solid #ddd; z-index: 100; padding: 10px 0; padding-bottom: calc(10px + env(safe-area-inset-bottom)); box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
.tab-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; color: #667781; cursor: pointer; text-decoration: none; border: none; background: none; transition: 0.2s; }
        .tab-item.active { color: #128C7E; }
        .tab-icon { font-size: 24px; }
        .tab-label { font-size: 11px; font-weight: 600; }

        .empty { text-align: center; padding: 60px 20px; color: #667781; }
        .empty-icon { font-size: 50px; margin-bottom: 15px; opacity: 0.5; }
        .in-this-chat { display: inline-flex; align-items: center; gap: 4px; }
        .in-this-chat .dot { width: 6px; height: 6px; background: #25D366; border-radius: 50%; }
    </style>
</head>
<body>
    <script>
    const myId = <?= $_SESSION['user_id'] ?>;
    function updateView(t) {
        fetch('api.php?action=update_viewing&target=' + (t || ''));
    }
    function refreshList() {
        fetch('api.php?action=conversations')
            .then(r => r.text())
            .then(html => {
                const wrapper = document.getElementById('user-list-wrapper');
                if (wrapper) wrapper.innerHTML = html;
            });
    }
    updateView('');
    setInterval(() => updateView(''), 30000);
    setInterval(refreshList, 5000);
    window.addEventListener('focus', () => updateView(''));
    </script>
    <div class="header">
        <h1>kotha.SohojWeb.com</h1>
        <div class="user-info">
            <div class="avatar"><?= $user['avatar_emoji'] ?></div>
            <a href="logout.php" style="color:#fff;text-decoration:none;margin-left:8px;font-size:20px;" title="Logout">🚪</a>
        </div>
    </div>
    
    <div class="search-container">
        <form method="GET">
            <input type="text" name="q" value="<?= $searchQuery ?>" placeholder="Search people..." autocomplete="off">
            <?php if ($isSearching): ?>
                <button type="button" onclick="location.href='users.php'" style="background:#667781">✕</button>
            <?php endif; ?>
            <button type="submit">Search</button>
        </form>
    </div>
    
    <div class="user-list" id="user-list-wrapper">
        <?php if ($isSearching): ?>
            <div class="section-title">Search Results</div>
            <?php if (empty($users)): ?>
                <div class="empty">
                    <div class="empty-icon">🔍</div>
                    <p>No results for "<?= $searchQuery ?>"</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="section-title">Recent Conversations</div>
            <?php if (empty($users)): ?>
                <div class="empty">
                    <div class="empty-icon">💬</div>
                    <p>No chats yet. Start searching!</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($users as $u): ?>
            <?php 
            $lastActive = $u['last_active'] ?? 0;
            $isOnline = (time() - $lastActive < 60);
            $viewTarget = "u" . $_SESSION['user_id'];
            $statusClass = 'offline';
            $statusText = getStatusText($lastActive);
            if ($isOnline) {
                if (($u['viewing_target'] ?? '') === $viewTarget) {
                    $statusClass = 'in-chat';
                    $statusText = 'In this chat';
                } else if (!empty($u['viewing_target'] ?? '')) {
                    $statusClass = 'other-chat';
                    $statusText = 'In other chat';
                } else {
                    $statusClass = 'online';
                    $statusText = 'Online';
                }
            }
            ?>
            <div class="user" onclick="location.href='chat.php?user=<?= $u['id'] ?>'">
                <div class="avatar-box">
                    <?= !empty($u['avatar_emoji']) ? $u['avatar_emoji'] : '👤' ?>
                    <?php if ($isOnline): ?><div class="status-dot"></div><?php else: ?><div class="status-dot offline"></div><?php endif; ?>
                </div>
                <div class="info">
                    <div class="info-top">
                        <h3><?= sanitize($u['display_name']) ?></h3>
                        <span class="status-tag <?= $statusClass ?>">
                            <?= $statusText ?>
                        </span>
                    </div>
                    <p>
                        <?php if (isset($u['last_msg'])): ?>
                            <?php if ($u['last_sender'] == $_SESSION['user_id']): ?>
                                <span class="tick <?= $u['last_read'] == 2 ? 'read' : '' ?>">
                                    <?= $u['last_read'] >= 1 ? '✓✓' : '✓' ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($u['last_type'] == 'image'): ?>📷 Photo
                            <?php else: ?><?= sanitize(mb_strimwidth($u['last_msg'], 0, 35, "...")) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="font-style:italic">No messages yet</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (isset($u['unread_count']) && $u['unread_count'] > 0): ?>
                    <span class="badge"><?= $u['unread_count'] ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="tab-bar">
        <a href="users.php" class="tab-item active">
            <span class="tab-icon">💬</span>
            <span class="tab-label">Chats</span>
        </a>
        <a href="settings.php" class="tab-item">
            <span class="tab-icon">⚙️</span>
            <span class="tab-label">Settings</span>
        </a>
    </div>
</body>
</html>
