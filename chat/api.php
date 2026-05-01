<?php
session_start();
require_once 'db.php';
if (!isLoggedIn()) { 
    echo json_encode(['error' => 'Not logged in']); 
    exit; 
}

$action = $_GET['action'] ?? '';
$myId = $_SESSION['user_id'];
error_log("API called: action=$action, myId=$myId");

try {

if ($action === 'conversations') {
    header('Content-Type: text/html');
    $myId = (int)$myId;
    $q = sanitize($_GET['q'] ?? '');
    $isSearching = !empty($q);
    
    if ($isSearching) {
        $users = searchNewUsers($myId, $q);
    } else {
        $users = getConnectedUsers($myId);
    }
    
    if ($isSearching): ?>
        <div class="section-title">Search Results</div>
        <?php if (empty($users)): ?>
            <div class="empty">
                <div class="empty-icon">🔍</div>
                <p>No results for "<?= sanitize($q) ?>"</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="section-title">Recent Chats</div>
        <?php if (empty($users)): ?>
            <div class="empty">
                <div class="empty-icon">💬</div>
                <p>No chats yet. Start a conversation!</p>
            </div>
        <?php endif; ?>
    <?php endif;

    foreach ($users as $u): 
        $lastActive = $u['last_active'] ?? 0;
        $isOnline = (time() - $lastActive < 60);
        $viewTarget = "u" . $myId;
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
                    <span class="status-tag <?= $statusClass ?>"><?= $statusText ?></span>
                </div>
                <p>
                    <?php if (isset($u['last_msg'])): ?>
                        <?php if ($u['last_sender'] == $myId): ?>
                            <span class="tick <?= $u['last_read'] == 2 ? 'read' : '' ?>"><?= $u['last_read'] >= 1 ? '✓✓' : '✓' ?></span>
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
        <?php 
    endforeach;
    exit;
}

if ($action === 'update_viewing') {
    $target = $_GET['target'] ?? ''; // e.g. 'u1', 'g5', or empty
    updateLastActive($myId, $target);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    $userId = (int)($_GET['user'] ?? 0);
    if ($userId) {
        $u = getUserById($userId);
        if ($u) {
            $isOnline = (time() - $u['last_active'] < 60);
            $status = getDetailedStatus($u, "u$myId");
            $color = '#6c757d'; // Default offline color
            
            if ($isOnline) {
                if ($status === 'In this chat') $color = '#007bff';
                else if ($status === 'In other chat') $color = '#fd7e14';
                else $color = '#28a745';
            }
            
            echo json_encode([
                'status' => $status,
                'is_online' => $isOnline,
                'color' => $color
            ]);
        }
    }
    exit;
}

if ($action === 'messages') {
    cleanupExpiredMessages();
    $chatWithId = (int)($_GET['user'] ?? 0);
    $lastId = (int)($_GET['last_id'] ?? 0);
    $pdo = getDB();
    $now = time();

    if ($chatWithId) {
        markMessagesAsRead($myId, $chatWithId);
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND id > ? AND (delete_at = 0 OR delete_at > ?) ORDER BY id ASC");
        $stmt->execute([$myId, $chatWithId, $chatWithId, $myId, $lastId, $now]);
    } else {
        echo json_encode(['error' => 'Invalid target']); exit;
    }

    $msgs = $stmt->fetchAll();
    $result = [];
    foreach ($msgs as $m) {
        $replyContent = '';
        if ($m['reply_to']) {
            $stmtR = $pdo->prepare("SELECT message, original FROM messages WHERE id = ?");
            $stmtR->execute([$m['reply_to']]);
            $replyMsg = $stmtR->fetch();
            if ($replyMsg) $replyContent = $replyMsg['original'] ?? $replyMsg['message'];
        }
        $result[] = [
            'id' => $m['id'],
            'sender_id' => $m['sender_id'],
            'message' => $m['message'],
            'original' => $m['original'],
            'type' => $m['type'],
            'is_read' => $m['is_read'],
            'delete_at' => $m['delete_at'],
            'reply_to' => $m['reply_to'],
            'reply_content' => $replyContent,
            'time' => date('g:i A', strtotime($m['created_at']))
        ];
    }
    echo json_encode(['messages' => $result]);
    exit;
}

if ($action === 'markread') {
    $msgId = (int)($_GET['id'] ?? 0);
    if ($msgId) {
        getDB()->prepare("UPDATE messages SET is_read = 2 WHERE id = ? AND receiver_id = ?")->execute([$msgId, $myId]);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($action === 'view_message') {
    $msgId = (int)($_GET['id'] ?? 0);
    if ($msgId) {
        // Only the receiver can trigger the deletion timer and mark as blue tick (is_read=2)
        getDB()->prepare("UPDATE messages SET 
            is_read = CASE WHEN receiver_id = ? THEN 2 ELSE is_read END,
            delete_at = CASE WHEN receiver_id = ? AND delete_at = 0 THEN ? ELSE delete_at END 
            WHERE id = ?")->execute([$myId, $myId, time() + 30, $msgId]);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($action === 'refresh_msgs') {
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'typing') {
    $chatWithId = (int)($_GET['user'] ?? 0);
    if ($chatWithId) echo json_encode(['typing' => getTypingStatus($myId, $chatWithId)]);
    exit;
}

if ($action === 'read_status') {
    $chatWithId = (int)($_GET['user'] ?? 0);
    if (!$chatWithId) { echo json_encode([]); exit; }
    $pdo = getDB();
    
    // Just mark as received (is_read=1) if it was unread (is_read=0)
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")->execute([$chatWithId, $myId]);
    
    $stmt = $pdo->prepare("SELECT id, delete_at, is_read FROM messages WHERE sender_id = ? AND receiver_id = ? ORDER BY id ASC");
    $stmt->execute([$chatWithId, $myId]);
    $rows = $stmt->fetchAll();
    
    // Also get messages I sent
    $stmt2 = $pdo->prepare("SELECT id, delete_at, is_read FROM messages WHERE sender_id = ? AND receiver_id = ? ORDER BY id ASC");
    $stmt2->execute([$myId, $chatWithId]);
    $rows2 = $stmt2->fetchAll();

    $read_data = [];
    foreach(array_merge($rows, $rows2) as $r) {
        if ($r['is_read'] >= 1) {
            $read_data[] = ['id' => $r['id'], 'delete_at' => $r['delete_at'], 'is_read' => $r['is_read']];
        }
    }

    echo json_encode(['read_messages' => $read_data]);
    exit;
}

if ($action === 'search_users') {
    $q = sanitize($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, display_name, avatar_emoji FROM users WHERE (display_name LIKE ? OR username LIKE ?) AND id != ? LIMIT 10");
    $stmt->execute(["%$q%", "%$q%", $myId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'send_message') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['error' => 'Invalid token']); exit; }
    $msg = trim($_POST['message'] ?? '');
    $replyTo = (int)($_POST['reply_to'] ?? 0);
    $chatWithId = (int)($_POST['user'] ?? 0);
    if ($msg && $chatWithId) {
        if (isPinValid($myId, $msg)) {
            unlockChat($myId, $chatWithId);
            echo json_encode(['success' => true, 'unlocked' => true]);
        } else {
            $msgId = saveMessage($myId, $chatWithId, $msg, 'text', $replyTo);
            echo json_encode(['success' => true, 'id' => $msgId]);
        }
    } else {
        echo json_encode(['error' => 'Empty message or invalid target']);
    }
    exit;
}

if ($action === 'send_image') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['error' => 'Invalid token']); exit; }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['image']['tmp_name'];
        $name = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $newName = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = 'uploads/' . $newName;
            if (move_uploaded_file($tmpName, $dest)) {
                $chatWithId = (int)($_POST['user'] ?? 0);
                $msgId = saveMessage($myId, $chatWithId, $dest, 'image', 0);
                echo json_encode(['success' => true, 'id' => $msgId]);
                exit;
            }
        }
    }
    echo json_encode(['error' => 'Upload failed']);
    exit;
}
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

echo json_encode(['error' => 'Unknown action']);
