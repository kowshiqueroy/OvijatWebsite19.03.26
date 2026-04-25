<?php
session_start();
require_once 'db.php';
require_once 'group_db.php';

if (!isLoggedIn()) { 
    echo json_encode(['error' => 'Not logged in']); 
    exit; 
}

$action = $_GET['action'] ?? '';
$myId = $_SESSION['user_id'];

cleanupExpiredGroupMessages();

if ($action === 'groups') {
    header('Content-Type: text/html');
    $groups = getGroupsForUser($myId);
    ?>
    <div class="section-title">My Groups</div>
    <div class="user" onclick="showCreateGroupModal()">
        <div class="avatar-box">➕</div>
        <div class="info">
            <h3>Create New Group</h3>
            <p>Start a new group chat</p>
        </div>
    </div>
    <?php if (empty($groups)): ?>
        <div class="empty">
            <div class="empty-icon">👥</div>
            <p>No groups joined yet.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($groups as $g): ?>
        <div class="user" onclick="location.href='group_chat.php?id=<?= $g['id'] ?>'">
            <div class="avatar-box">👥</div>
            <div class="info">
                <div class="info-top">
                    <h3><?= sanitize($g['name']) ?></h3>
                </div>
                <p>
                    <?php if (isset($g['last_msg'])): ?>
                        <?php if ($g['last_type'] == 'image'): ?>📷 Photo
                        <?php else: ?><?= sanitize(mb_strimwidth($g['last_msg'], 0, 35, "...")) ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="font-style:italic">No messages yet</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endforeach;
    exit;
}

if ($action === 'create_group') {
    $name = sanitize($_POST['name'] ?? '');
    if ($name) {
        $groupId = createGroup($name, $myId);
        echo json_encode(['success' => true, 'group_id' => $groupId]);
    } else {
        echo json_encode(['error' => 'Name required']);
    }
    exit;
}

if ($action === 'search_users') {
    $q = sanitize($_GET['q'] ?? '');
    $groupId = (int)($_GET['group_id'] ?? 0);
    $users = searchNewUsers($myId, $q);
    
    if ($groupId > 0) {
        $pdo = getGroupDB();
        $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $memberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($users as &$u) {
            $u['is_member'] = in_array($u['id'], $memberIds);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

if ($action === 'remove_member') {
    $groupId = (int)$_POST['group_id'];
    $userId = (int)$_POST['user_id'];
    
    // Optional: Check if requester is creator
    $group = getGroupById($groupId);
    if ($group['created_by'] == $myId || $userId == $myId) {
        if ($group['created_by'] == $userId && $userId != $myId) {
            echo json_encode(['error' => 'Cannot remove creator']);
        } else {
            removeGroupMember($groupId, $userId);
            echo json_encode(['success' => true]);
        }
    } else {
        echo json_encode(['error' => 'Permission denied']);
    }
    exit;
}

if ($action === 'add_member') {
    $groupId = (int)$_POST['group_id'];
    $userId = (int)$_POST['user_id'];
    addGroupMember($groupId, $userId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'view_message') {
    $messageId = (int)$_POST['message_id'];
    markGroupMessageViewed($messageId, $myId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get_messages') {
    $groupId = (int)$_GET['id'];
    $messages = getGroupMessages($groupId, $myId);
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

if ($action === 'get_members') {
    $groupId = (int)$_GET['id'];
    $members = getGroupMembers($groupId);
    foreach($members as &$m) {
        $m['is_online'] = (time() - $m['last_active'] < 60);
    }
    header('Content-Type: application/json');
    echo json_encode($members);
    exit;
}
