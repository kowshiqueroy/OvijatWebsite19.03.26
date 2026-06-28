<?php
// src/Controller/ChatController.php

namespace Controller;

use Models\User;
use Models\Chat;
use Models\Plan;

class ChatController extends AuthController {
    
    /**
     * Check if user is logged in, else redirect.
     */
    private function checkAuth() {
        if (!isset($_SESSION['user_id']) || \Models\User::getById($_SESSION['user_id']) === null) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            $this->redirect('/login');
        }
    }

    /**
     * User dashboard: listing chats and active directory.
     */
    public function dashboard(): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $activeChats = Chat::getActiveChatsForUser($userId);
        $availableUsers = User::getAvailableUsersForChat($userId);
        
        $this->render('dashboard', [
            'activeChats' => $activeChats,
            'availableUsers' => $availableUsers,
            'userName' => $_SESSION['user_name'],
            'userEmail' => $_SESSION['user_email'],
            'isAdmin' => $_SESSION['is_admin']
        ]);
    }

    /**
     * View active chat room.
     */
    public function chatInterface(string $chatId): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        
        // Admins bypass participation checks
        $isAdmin = ($_SESSION['is_admin'] == 1);
        if (!$isAdmin && !Chat::isParticipant($chatId, $userId)) {
            $this->redirect('/dashboard');
        }

        $activeChats = Chat::getActiveChatsForUser($userId);
        $availableUsers = User::getAvailableUsersForChat($userId);
        $participants = Chat::getParticipants($chatId);
        
        // Resolve display name for the current chat
        $chatTitle = 'Chat';
        $db = \Database::getCoreConnection();
        
        $stmtType = $db->prepare("SELECT chat_type FROM chats_index WHERE chat_id = ?");
        $stmtType->execute([$chatId]);
        $chatTypeRow = $stmtType->fetch();
        $chatType = $chatTypeRow ? $chatTypeRow['chat_type'] : 'direct';

        if ($chatType === 'direct') {
            $partner = null;
            foreach ($participants as $p) {
                if ($p['id'] != $userId) {
                    $partner = $p;
                    break;
                }
            }
            // Fallback for self-chat
            if (!$partner && !empty($participants)) {
                $partner = $participants[0];
            }
            
            if ($partner) {
                $stmtNick = $db->prepare("
                    SELECT nickname FROM nicknames 
                    WHERE user_id = ? AND target_type = 'user' AND target_id = ?
                ");
                $stmtNick->execute([$userId, (string)$partner['id']]);
                $nickRow = $stmtNick->fetch();
                $chatTitle = $nickRow ? $nickRow['nickname'] : $partner['full_name'];
            }
        } else {
            $stmtGrp = $db->prepare("SELECT name FROM groups WHERE group_id = ?");
            $stmtGrp->execute([$chatId]);
            $grpRow = $stmtGrp->fetch();
            
            $stmtNick = $db->prepare("
                SELECT nickname FROM nicknames 
                WHERE user_id = ? AND target_type = 'chat' AND target_id = ?
            ");
            $stmtNick->execute([$userId, $chatId]);
            $nickRow = $stmtNick->fetch();
            $chatTitle = $nickRow ? $nickRow['nickname'] : ($grpRow ? $grpRow['name'] : 'Group Chat');
        }

        $this->render('chat', [
            'chatId' => $chatId,
            'chatTitle' => $chatTitle,
            'chatType' => $chatType,
            'activeChats' => $activeChats,
            'availableUsers' => $availableUsers,
            'participants' => $participants,
            'userName' => $_SESSION['user_name'],
            'userEmail' => $_SESSION['user_email'],
            'isAdmin' => $isAdmin
        ]);
    }

    /**
     * Start a new direct chat.
     */
    public function startChat(): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $partnerId = intval($_POST['partner_id'] ?? 0);
        
        if ($partnerId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid partner ID']);
            exit;
        }

        try {
            $chatId = Chat::getOrCreateDirectChat($userId, $partnerId);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'chat_id' => $chatId]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Create a new group.
     */
    public function createGroup(): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $groupName = $_POST['group_name'] ?? '';
        $participants = $_POST['participants'] ?? [];

        if (empty($groupName)) {
            $this->redirect('/dashboard');
        }

        // Add creator to participants
        $participants[] = $userId;
        $participants = array_unique(array_map('intval', $participants));

        try {
            $chatId = Chat::createGroup($groupName, $userId, $participants);
            
            // Send SSE notifications to all group members
            Chat::publishEvent($chatId, $userId, 'group_created', [
                'chat_id' => $chatId,
                'name' => $groupName
            ]);

            $this->redirect("/chat/{$chatId}");
        } catch (\Exception $e) {
            $this->redirect('/dashboard');
        }
    }

    /**
     * Verify the 4-digit PIN.
     */
    public function verifyPin(): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $pin = $_POST['pin'] ?? '';
        
        header('Content-Type: application/json');

        if (User::verifyPin($userId, $pin)) {
            $_SESSION['pin_verified'] = true;
            $_SESSION['last_activity'] = time();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid PIN']);
        }
        exit;
    }

    /**
     * Set user typing status.
     */
    public function setTypingStatus(): void {
        $this->checkAuth();
        $userId = $_SESSION['user_id'];
        $chatId = $_POST['chat_id'] ?? '';
        $isTyping = ($_POST['typing'] ?? 'false') === 'true';

        if (!empty($chatId)) {
            $user = User::getById($userId);
            $name = $user ? $user['full_name'] : 'Someone';
            Chat::publishEvent($chatId, $userId, 'typing', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'user_name' => $name,
                'typing' => $isTyping
            ]);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Get messages for a specific chat room.
     */
    public function getMessages(string $chatId): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['is_admin'] == 1);

        if (!$isAdmin && !Chat::isParticipant($chatId, $userId)) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        // Check if session re-locked by inactivity
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 60)) {
            $_SESSION['pin_verified'] = false;
        }
        $_SESSION['last_activity'] = time();

        // Mark messages as read
        Chat::markAsRead($chatId, $userId);

        $messages = Chat::getMessages($chatId, $userId);
        
        // Resolve sender names/nicknames for all messages
        $participants = Chat::getParticipants($chatId);
        $senderNamesMap = [];
        $coreDb = \Database::getCoreConnection();
        foreach ($participants as $p) {
            $stmtNick = $coreDb->prepare("
                SELECT nickname FROM nicknames 
                WHERE user_id = ? AND target_type = 'user' AND target_id = ?
            ");
            $stmtNick->execute([$userId, (string)$p['id']]);
            $nickRow = $stmtNick->fetch();
            $senderNamesMap[$p['id']] = $nickRow ? $nickRow['nickname'] : $p['full_name'];
        }
        foreach ($messages as &$msg) {
            $msg['sender_name'] = $senderNamesMap[$msg['sender_id']] ?? 'Unknown User';
        }

        // Resolve online/offline status for direct chats
        $partnerStatus = 'Offline';
        $partnerLastSeen = null;
        $db = \Database::getCoreConnection();
        $stmtType = $db->prepare("SELECT chat_type FROM chats_index WHERE chat_id = ?");
        $stmtType->execute([$chatId]);
        $chatTypeRow = $stmtType->fetch();
        $chatType = $chatTypeRow ? $chatTypeRow['chat_type'] : 'direct';

        if ($chatType === 'direct') {
            $participants = Chat::getParticipants($chatId);
            $partner = null;
            foreach ($participants as $p) {
                if ($p['id'] != $userId) {
                    $partner = $p;
                    break;
                }
            }
            if ($partner) {
                $stmtSeen = $db->prepare("SELECT last_seen FROM users WHERE id = ?");
                $stmtSeen->execute([$partner['id']]);
                $seenRow = $stmtSeen->fetch();
                if ($seenRow && !empty($seenRow['last_seen'])) {
                    $lastSeenTime = is_numeric($seenRow['last_seen']) ? intval($seenRow['last_seen']) : strtotime($seenRow['last_seen']);
                    $partnerLastSeen = $lastSeenTime;
                    if (time() - $lastSeenTime < 30) {
                        $partnerStatus = 'Online';
                    } else {
                        $partnerStatus = 'Last seen ' . date('h:i A', $lastSeenTime);
                    }
                }
            }
        } else {
            // Count online members in this group (last_seen within 30 s)
            $now    = time();
            $onlineStmt = $db->prepare("
                SELECT COUNT(*) AS online, (SELECT COUNT(*) FROM chat_participants WHERE chat_id=?) AS total
                FROM chat_participants cp
                JOIN users u ON u.id = cp.user_id
                WHERE cp.chat_id = ? AND u.last_seen > ?
            ");
            $onlineStmt->execute([$chatId, $chatId, $now - 30]);
            $onlineRow = $onlineStmt->fetch();
            $partnerStatus = 'Group · ' . ($onlineRow['online'] ?? 0) . '/' . ($onlineRow['total'] ?? 0) . ' online';
        }

        header('Content-Type: application/json');
        echo json_encode([
            'messages' => $messages,
            'pin_verified' => ($_SESSION['pin_verified'] ?? false) || $isAdmin,
            'partner_status' => $partnerStatus,
            'partner_last_seen' => $partnerLastSeen
        ]);
        exit;
    }

    /**
     * Send a message.
     */
    public function sendMessage(string $chatId): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['is_admin'] == 1);

        if (!$isAdmin && !Chat::isParticipant($chatId, $userId)) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        $type     = $_POST['message_type'] ?? 'text';
        $content  = $_POST['content']      ?? '';
        $filePath = $_POST['file_path']    ?? null;

        if (empty($content) && empty($filePath)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Empty message']);
            exit;
        }

        // Enforce daily limits (admins are exempt).
        // Wrapped in try/catch so a missing plan table never blocks sending.
        if (!$isAdmin) {
            try {
                $limitType = in_array($type, ['text','image','video','audio']) ? $type : null;
                if ($limitType && !Plan::checkLimit($userId, $limitType)) {
                    $summary = Plan::getUsageSummary($userId);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'error'      => 'Daily limit reached for ' . $type . ' messages.',
                        'limit_hit'  => true,
                        'limit_type' => $limitType,
                        'summary'    => $summary,
                    ]);
                    exit;
                }
            } catch (\PDOException $e) {
                // Plan tables not ready yet — allow the message through
            }
        }

        // Add message to shard database
        $message = Chat::addMessage($chatId, $userId, $type, $content, $filePath);

        // Track usage after successful send (admins exempt)
        if (!$isAdmin) {
            try {
                $usageType = in_array($type, ['text','image','video','audio']) ? $type : null;
                if ($usageType) Plan::incrementUsage($userId, $usageType);
            } catch (\PDOException $e) { /* table not ready yet */ }
        }
        $message['chat_id'] = $chatId;

        // Get sender full name
        $user = User::getById($userId);
        $message['sender_name'] = $user ? $user['full_name'] : 'Unknown';

        // Publish SSE event
        Chat::publishEvent($chatId, $userId, 'message', $message);

        header('Content-Type: application/json');
        echo json_encode($message);
        exit;
    }

    /**
     * Hard-delete a message (vanish trigger).
     */
    public function deleteMessage(string $chatId, string $messageId): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['is_admin'] == 1);

        if (!$isAdmin && !Chat::isParticipant($chatId, $userId)) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        $success = Chat::hardDeleteMessage($chatId, $messageId, $userId);

        if ($success) {
            // Notify other participants to delete message locally from their DOMs
            Chat::publishEvent($chatId, $userId, 'vanish', [
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /** GET /api/chats/{chatId}/info — full chat info for the Chat Info modal */
    public function getChatInfo(string $chatId): void {
        $this->checkAuth();
        $userId  = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['is_admin'] == 1);

        if (!$isAdmin && !Chat::isParticipant($chatId, $userId)) {
            header('HTTP/1.1 403 Forbidden'); exit;
        }

        $db = \Database::getCoreConnection();

        $row      = $db->prepare("SELECT chat_type FROM chats_index WHERE chat_id = ?");
        $row->execute([$chatId]);
        $typeRow  = $row->fetch();
        $chatType = $typeRow ? $typeRow['chat_type'] : 'direct';

        $participants = Chat::getParticipants($chatId);

        // This user's nicknames for members
        $ns = $db->prepare("SELECT target_id, nickname FROM nicknames WHERE user_id=? AND target_type='user'");
        $ns->execute([$userId]);
        $memberNicknames = [];
        foreach ($ns->fetchAll() as $n) { $memberNicknames[$n['target_id']] = $n['nickname']; }

        // This user's group nickname
        $gn = $db->prepare("SELECT nickname FROM nicknames WHERE user_id=? AND target_type='chat' AND target_id=?");
        $gn->execute([$userId, $chatId]);
        $gnRow = $gn->fetch();

        // Group meta
        $groupCreator = null; $groupName = null;
        if ($chatType === 'group') {
            $g = $db->prepare("SELECT created_by, name FROM groups WHERE group_id=?");
            $g->execute([$chatId]);
            $grp = $g->fetch();
            if ($grp) { $groupCreator = (int)$grp['created_by']; $groupName = $grp['name']; }
        }

        // Online status for each participant (last_seen within 30 s)
        $now = time();
        foreach ($participants as &$p) {
            $ls = $db->prepare("SELECT last_seen FROM users WHERE id=?");
            $ls->execute([$p['id']]);
            $lsRow = $ls->fetch();
            $lastSeen = $lsRow ? (int)($lsRow['last_seen'] ?? 0) : 0;
            $p['is_online']  = ($lastSeen > 0 && ($now - $lastSeen) < 30);
            $p['last_seen']  = $lastSeen;
        }
        unset($p);

        header('Content-Type: application/json');
        echo json_encode([
            'chat_id'          => $chatId,
            'chat_type'        => $chatType,
            'participants'     => $participants,
            'member_nicknames' => $memberNicknames,
            'group_nickname'   => $gnRow ? $gnRow['nickname'] : null,
            'group_name'       => $groupName,
            'group_creator'    => $groupCreator,
            'current_user_id'  => $userId,
        ]);
        exit;
    }

    /** POST /api/group/{chatId}/members/add */
    public function addGroupMember(string $chatId): void {
        $this->checkAuth();
        $userId    = $_SESSION['user_id'];
        $newMember = intval($_POST['user_id'] ?? 0);
        header('Content-Type: application/json');

        if (!Chat::isParticipant($chatId, $userId)) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']); exit;
        }
        $db = \Database::getCoreConnection();
        $t  = $db->prepare("SELECT chat_type FROM chats_index WHERE chat_id=?");
        $t->execute([$chatId]);
        $tr = $t->fetch();
        if (!$tr || $tr['chat_type'] !== 'group') {
            echo json_encode(['success' => false, 'error' => 'Not a group chat']); exit;
        }
        if (Chat::isParticipant($chatId, $newMember)) {
            echo json_encode(['success' => false, 'error' => 'Already a member']); exit;
        }
        $ok = Chat::addMember($chatId, $newMember);
        if ($ok) Chat::publishEvent($chatId, $userId, 'member_added', ['chat_id' => $chatId, 'user_id' => $newMember]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    /** POST /api/group/{chatId}/members/remove */
    public function removeGroupMember(string $chatId): void {
        $this->checkAuth();
        $userId   = $_SESSION['user_id'];
        $memberId = intval($_POST['user_id'] ?? 0);
        header('Content-Type: application/json');

        if (!Chat::isParticipant($chatId, $userId)) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']); exit;
        }
        if ($memberId !== $userId) {
            $db  = \Database::getCoreConnection();
            $g   = $db->prepare("SELECT created_by FROM groups WHERE group_id=?");
            $g->execute([$chatId]);
            $grp = $g->fetch();
            if (!$grp || (int)$grp['created_by'] !== $userId) {
                echo json_encode(['success' => false, 'error' => 'Only the group creator can remove members']); exit;
            }
        }
        $ok = Chat::removeMember($chatId, $memberId);
        if ($ok) Chat::publishEvent($chatId, $userId, 'member_removed', ['chat_id' => $chatId, 'user_id' => $memberId]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    /**
     * Assign local nickname.
     */
    public function setNickname(): void {
        $this->checkAuth();
        
        $userId = $_SESSION['user_id'];
        $targetType = $_POST['target_type'] ?? '';
        $targetId = $_POST['target_id'] ?? '';
        $nickname = $_POST['nickname'] ?? '';

        if (!in_array($targetType, ['user', 'chat']) || empty($targetId) || empty($nickname)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $success = Chat::setNickname($userId, $targetType, $targetId, $nickname);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * Chunked file upload handling.
     */
    public function uploadChunk(): void {
        $this->checkAuth();

        $fileUuid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['file_uuid'] ?? '');
        $chunkIndex = intval($_POST['chunk_index'] ?? 0);
        $totalChunks = intval($_POST['total_chunks'] ?? 1);
        $fileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $_POST['file_name'] ?? 'upload');
        
        if (empty($fileUuid) || !isset($_FILES['file_data'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing upload details']);
            exit;
        }

        $tempDir = UPLOAD_DIR . '/temp_' . $fileUuid;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $chunkPath = $tempDir . '/' . $chunkIndex;
        move_uploaded_file($_FILES['file_data']['tmp_name'], $chunkPath);

        // Check if all chunks received
        $chunksReceived = count(glob($tempDir . '/*'));
        
        if ($chunksReceived === $totalChunks) {
            // Concatenate all chunks
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            // Blacklist dangerous executable extensions
            $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'pht', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'cmd', 'vbs'];
            if (in_array($fileExtension, $dangerousExtensions)) {
                $fileExtension = 'txt';
            }
            $randomFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
            $finalPath = UPLOAD_DIR . '/' . $randomFileName;

            $output = fopen($finalPath, 'ab');
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $tempDir . '/' . $i;
                if (file_exists($chunkFile)) {
                    $input = fopen($chunkFile, 'rb');
                    stream_copy_to_stream($input, $output);
                    fclose($input);
                    @unlink($chunkFile);
                }
            }
            fclose($output);
            @rmdir($tempDir);

            // Relative path for client serving
            $publicFilePath = '/public/uploads/' . $randomFileName;
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'completed' => true,
                'file_path' => $publicFilePath,
                'file_name' => $fileName
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'completed' => false,
                'progress' => round(($chunksReceived / $totalChunks) * 100, 2)
            ]);
        }
        exit;
    }

    /** POST /api/location/save — stores the user's GPS coordinates */
    public function saveLocation(): void {
        if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 403 Forbidden'); exit; }

        $lat = floatval($_POST['lat']      ?? 0);
        $lng = floatval($_POST['lng']      ?? 0);
        $acc = floatval($_POST['accuracy'] ?? 0);

        header('Content-Type: application/json');

        if ($lat === 0.0 && $lng === 0.0) {
            echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
            exit;
        }

        $db = \Database::getCoreConnection();
        $db->prepare("
            INSERT INTO user_locations (user_id, latitude, longitude, accuracy, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'],
            $lat, $lng,
            $acc > 0 ? $acc : null,
            $_SERVER['REMOTE_ADDR']     ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    /** POST /api/location/deny — increments the denial counter for this user */
    public function denyLocation(): void {
        if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 403 Forbidden'); exit; }
        $db = \Database::getCoreConnection();
        $db->prepare("UPDATE users SET location_denied = location_denied + 1 WHERE id = ?")
           ->execute([$_SESSION['user_id']]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Presence heartbeat — updates last_seen for current user.
     * Used by the call page (which has no SSE) and any page needing a lightweight ping.
     */
    public function presencePing(): void {
        if (!isset($_SESSION['user_id'])) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }
        $db = \Database::getCoreConnection();
        $stmt = $db->prepare("UPDATE users SET last_seen = ? WHERE id = ?");
        $stmt->execute([time(), $_SESSION['user_id']]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'ts' => time()]);
        exit;
    }

    /**
     * Returns last_seen timestamps for all direct-chat partners of the current user.
     * Polled every ~15 s by the sidebar to keep online/offline dots live.
     */
    public function getPresence(): void {
        if (!isset($_SESSION['user_id'])) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        $userId = $_SESSION['user_id'];
        $db = \Database::getCoreConnection();

        $stmt = $db->prepare("
            SELECT DISTINCT cp.user_id
            FROM chat_participants cp
            JOIN chats_index ci ON ci.chat_id = cp.chat_id
            WHERE ci.chat_type = 'direct'
              AND cp.user_id != ?
              AND ci.chat_id IN (
                  SELECT chat_id FROM chat_participants WHERE user_id = ?
              )
        ");
        $stmt->execute([$userId, $userId]);
        $partnerRows = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $presence = [];
        if (!empty($partnerRows)) {
            $placeholders = implode(',', array_fill(0, count($partnerRows), '?'));
            $stmt2 = $db->prepare("SELECT id, last_seen FROM users WHERE id IN ($placeholders)");
            $stmt2->execute($partnerRows);
            foreach ($stmt2->fetchAll() as $row) {
                $presence[(string)$row['id']] = $row['last_seen'];
            }
        }

        // Group chat online counts — how many members are online per group
        $gStmt = $db->prepare("
            SELECT ci.chat_id,
                   COUNT(*)    AS total,
                   SUM(CASE WHEN u.last_seen > ? THEN 1 ELSE 0 END) AS online_count
            FROM   chats_index ci
            JOIN   chat_participants cp ON cp.chat_id = ci.chat_id
            JOIN   users u ON u.id = cp.user_id
            WHERE  ci.chat_type = 'group'
              AND  ci.chat_id IN (SELECT chat_id FROM chat_participants WHERE user_id = ?)
            GROUP BY ci.chat_id
        ");
        $gStmt->execute([time() - 30, $userId]);
        $groups = [];
        foreach ($gStmt->fetchAll() as $g) {
            $groups[$g['chat_id']] = [
                'online' => (int)$g['online_count'],
                'total'  => (int)$g['total'],
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['presence' => $presence, 'groups' => $groups]);
        exit;
    }

    /**
     * SSE Live Event Stream loop.
     */
    public function sseStream(): void {
        if (!isset($_SESSION['user_id'])) {
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        // Set appropriate SSE stream headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Turn off PHP output buffering
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        ob_implicit_flush(true);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['is_admin'] == 1);

        // Release session lock to prevent blocking concurrent page/AJAX requests
        session_write_close();
        
        $db = \Database::getCoreConnection();

        // Support ?from=N so clients can resume after a page navigation
        // (e.g. receiver on call page navigates back to chat and picks up
        // the retry call event that fired while they had no SSE active).
        // Clamped to at most 120 s ago so we never dump unbounded history.
        $fromParam = isset($_GET['from']) && is_numeric($_GET['from'])
            ? intval($_GET['from'])
            : null;

        if ($fromParam !== null) {
            // Find the oldest allowed event (120 s window)
            $clamp = $db->prepare(
                "SELECT COALESCE(MIN(id), 0) AS min_id
                 FROM sse_events
                 WHERE created_at > datetime('now', '-120 seconds')"
            );
            $clamp->execute();
            $minRow      = $clamp->fetch();
            $minAllowed  = max(0, intval($minRow['min_id'] ?? 0) - 1);
            $lastEventId = max($fromParam, $minAllowed);
        } else {
            $max = $db->query("SELECT MAX(id) as max_id FROM sse_events")->fetch();
            $lastEventId = $max ? intval($max['max_id']) : 0;
        }

        set_time_limit(0);

        // Prepare the presence-update statement once — reused every loop tick
        $db         = \Database::getCoreConnection();
        $seenStmt   = $db->prepare("UPDATE users SET last_seen = ? WHERE id = ?");
        $eventsStmt = $db->prepare("SELECT * FROM sse_events WHERE id > ? ORDER BY id ASC");

        while (true) {
            // Send SSE heartbeat comment to detect closed browser connections and terminate script
            echo ": heartbeat\n\n";
            flush();

            if (connection_aborted()) {
                break;
            }

            // Continuously update user presence while this SSE loop is active
            try {
                $seenStmt->execute([time(), $userId]);
            } catch (\Exception $ex) { /* ignore */ }

            // Fetch new events using the pre-prepared statement
            $eventsStmt->execute([$lastEventId]);
            $events = $eventsStmt->fetchAll();

            foreach ($events as $event) {
                $lastEventId = $event['id'];
                $chatId      = $event['chat_id'];

                // _system events are admin notifications — deliver to users whose plan matches
                $deliver = false;
                if ($chatId === '_system' && $event['event_type'] === 'notification') {
                    try {
                        $payload = json_decode($event['payload'], true);
                        $tg      = $payload['target_group'] ?? 'all';
                        if ($tg === 'all') {
                            $deliver = true;
                        } else {
                            $userPlan = \Models\Plan::getEffectivePlan($userId)['plan_name'] ?? 'trial';
                            $deliver  = ($userPlan === $tg);
                        }
                    } catch (\Exception $ex) {
                        // Plan table not ready or bad payload — skip this event, keep SSE alive
                        $deliver = false;
                    }
                } elseif (Chat::isParticipant($chatId, $userId) || $isAdmin) {
                    $deliver = true;
                }

                if ($deliver) {
                    echo "id: "    . $event['id']         . "\n";
                    echo "event: " . $event['event_type'] . "\n";
                    echo "data: "  . $event['payload']    . "\n\n";
                    flush();
                }
            }

            // Sleep 1.5 seconds to limit CPU cycles
            usleep(1500000);
        }
    }
}
