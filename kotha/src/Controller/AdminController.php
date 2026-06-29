<?php
// src/Controller/AdminController.php

namespace Controller;

use Models\User;
use Models\Chat;
use Models\Call;
use Models\Plan;

class AdminController extends AuthController {

    /**
     * Check if the user is a logged-in admin.
     */
    private function checkAdmin() {
        if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1 || \Models\User::getById($_SESSION['user_id']) === null) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            $this->redirect('/login');
        }
    }

    /**
     * Show admin dashboard.
     */
    public function dashboard(): void {
        $this->checkAdmin();

        $users      = User::getAllUsers();
        // getAllRecordings() auto-finalizes any stale temp files (>1h old),
        // so the live counts are always accurate on page load.
        $recordings = Call::getAllRecordings();

        // Get all active chats to sniff
        $db = \Database::getCoreConnection();
        $stmt = $db->query("SELECT * FROM chats_index ORDER BY created_at DESC");
        $chats = $stmt->fetchAll();

        $activeChats = [];
        foreach ($chats as $c) {
            $chatId = $c['chat_id'];
            $participants = Chat::getParticipants($chatId);
            $names = array_column($participants, 'full_name');
            
            // Resolve display title
            if ($c['chat_type'] === 'direct') {
                $title = implode(' <-> ', $names);
            } else {
                $stmtGrp = $db->prepare("SELECT name FROM groups WHERE group_id = ? LIMIT 1");
                $stmtGrp->execute([$chatId]);
                $group = $stmtGrp->fetch();
                $title = "[Group] " . ($group ? $group['name'] : 'Unknown Group') . " (" . count($names) . " members)";
            }

            $activeChats[] = [
                'chat_id' => $chatId,
                'chat_type' => $c['chat_type'],
                'title' => $title,
                'participants' => $names,
                'created_at' => $c['created_at']
            ];
        }

        // Fetch app-wide settings (try-catch guards against schema migration lag)
        $autoApprove = false;
        try {
            $settingRow = $db->prepare("SELECT value FROM app_settings WHERE key='auto_approve_registration'");
            $settingRow->execute();
            $autoApprove = ($settingRow->fetchColumn() === '1');
        } catch (\PDOException $e) { /* table not yet created — default to false */ }

        $this->render('admin', [
            'users'            => $users,
            'recordings'       => $recordings,
            'chats'            => $activeChats,
            'userName'         => $_SESSION['user_name'],
            'usersWithPlans'   => Plan::getAllUsersWithPlans(),
            'planTemplates'    => Plan::getAllTemplates(),
            'upgradeRequests'  => Plan::getAllRequests(),
            'allNotifications' => Plan::getAllNotifications(),
            'autoApprove'      => $autoApprove,
        ]);
    }

    /* ── Overview dashboard data ─────────────────────────────── */

    /** GET /admin/overview — aggregated stats + chart data */
    public function overview(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $db         = \Database::getCoreConnection();
        $todayStart = date('Y-m-d 00:00:00');
        $weekStart  = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $monStart   = date('Y-m-d 00:00:00', strtotime('-29 days'));

        // ── Users ──────────────────────────────────────────────
        $uTotal   = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0")->fetchColumn();
        $uApproved= (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND is_approved=1")->fetchColumn();
        $uPending = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND is_approved=0")->fetchColumn();
        $uBlocked = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND is_approved=2")->fetchColumn();
        $uOnline  = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND last_seen > " . (time()-300))->fetchColumn();
        $uToday   = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND created_at>='{$todayStart}'")->fetchColumn();
        $uWeek    = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND created_at>='{$weekStart}'")->fetchColumn();
        $uMon     = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND created_at>='{$monStart}'")->fetchColumn();

        // Daily registrations (last 30 days)
        $uGrowth  = $db->query("SELECT date(created_at) day, COUNT(*) cnt
                                 FROM users WHERE is_admin=0 AND created_at>='{$monStart}'
                                 GROUP BY day ORDER BY day ASC")->fetchAll();

        // ── Calls ──────────────────────────────────────────────
        $cTotal = (int)$db->query("SELECT COUNT(*) FROM call_records")->fetchColumn();
        $cToday = (int)$db->query("SELECT COUNT(*) FROM call_records WHERE created_at>='{$todayStart}'")->fetchColumn();
        $cWeek  = (int)$db->query("SELECT COUNT(*) FROM call_records WHERE created_at>='{$weekStart}'")->fetchColumn();
        $cMon   = (int)$db->query("SELECT COUNT(*) FROM call_records WHERE created_at>='{$monStart}'")->fetchColumn();
        $cAudio = (int)$db->query("SELECT COUNT(*) FROM call_records WHERE call_type='audio'")->fetchColumn();
        $cVideo = (int)$db->query("SELECT COUNT(*) FROM call_records WHERE call_type='video'")->fetchColumn();

        // Call trend (last 30 days)
        $cTrendRaw = $db->query("SELECT date(created_at) day, call_type, COUNT(*) cnt
                                  FROM call_records WHERE created_at>='{$monStart}'
                                  GROUP BY day, call_type ORDER BY day ASC")->fetchAll();

        // ── Chats ──────────────────────────────────────────────
        $chTotal  = (int)$db->query("SELECT COUNT(*) FROM chats_index")->fetchColumn();
        $chDirect = (int)$db->query("SELECT COUNT(*) FROM chats_index WHERE chat_type='direct'")->fetchColumn();
        $chGroup  = (int)$db->query("SELECT COUNT(*) FROM chats_index WHERE chat_type='group'")->fetchColumn();

        // ── Messages + Media (scan shards) ─────────────────────
        $mTotal=0; $mToday=0; $mWeek=0; $mMon=0;
        $types  = ['text'=>0,'image'=>0,'video'=>0,'audio'=>0,'file'=>0];
        $daily  = [];   // date → count (last 7 days)

        foreach (glob(CHAT_DB_DIR . '/chat_*.sqlite') ?: [] as $shard) {
            $cid = substr(basename($shard, '.sqlite'), 5);
            try {
                $p = \Database::getChatConnection($cid);
                $mTotal += (int)$p->query("SELECT COUNT(*) FROM messages")->fetchColumn();
                $mToday += (int)$p->query("SELECT COUNT(*) FROM messages WHERE created_at>='{$todayStart}'")->fetchColumn();
                $mWeek  += (int)$p->query("SELECT COUNT(*) FROM messages WHERE created_at>='{$weekStart}'")->fetchColumn();
                $mMon   += (int)$p->query("SELECT COUNT(*) FROM messages WHERE created_at>='{$monStart}'")->fetchColumn();
                foreach (array_keys($types) as $t) {
                    $types[$t] += (int)$p->query("SELECT COUNT(*) FROM messages WHERE message_type='$t'")->fetchColumn();
                }
                foreach ($p->query("SELECT date(created_at) d, COUNT(*) c FROM messages
                                     WHERE created_at>='{$weekStart}' GROUP BY d")->fetchAll() as $r) {
                    $daily[$r['d']] = ($daily[$r['d']] ?? 0) + $r['c'];
                }
            } catch (\Exception $e) {}
        }

        // Build 7-day chart arrays
        $dLabels = []; $dCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $dLabels[] = date('D d', strtotime($d));
            $dCounts[] = $daily[$d] ?? 0;
        }

        // Build 30-day user growth arrays
        $ugLabels = []; $ugCounts = [];
        $ugMap = array_column($uGrowth, 'cnt', 'day');
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $ugLabels[] = date('d M', strtotime($d));
            $ugCounts[] = $ugMap[$d] ?? 0;
        }

        // Build call trend arrays (30 days)
        $ctLabels = []; $ctAudio = []; $ctVideo = [];
        $ctMap = [];
        foreach ($cTrendRaw as $r) { $ctMap[$r['day']][$r['call_type']] = (int)$r['cnt']; }
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $ctLabels[] = date('d M', strtotime($d));
            $ctAudio[]  = $ctMap[$d]['audio'] ?? 0;
            $ctVideo[]  = $ctMap[$d]['video'] ?? 0;
        }

        echo json_encode([
            'users'    => ['total'=>$uTotal,'approved'=>$uApproved,'pending'=>$uPending,'blocked'=>$uBlocked,
                           'online'=>$uOnline,'today'=>$uToday,'week'=>$uWeek,'month'=>$uMon,
                           'growth_labels'=>$ugLabels,'growth_counts'=>$ugCounts],
            'messages' => ['total'=>$mTotal,'today'=>$mToday,'week'=>$mWeek,'month'=>$mMon,
                           'types'=>$types,'day_labels'=>$dLabels,'day_counts'=>$dCounts],
            'media'    => ['total'=>$types['image']+$types['video']+$types['audio'],
                           'images'=>$types['image'],'videos'=>$types['video'],'audio'=>$types['audio']],
            'calls'    => ['total'=>$cTotal,'today'=>$cToday,'week'=>$cWeek,'month'=>$cMon,
                           'audio'=>$cAudio,'video'=>$cVideo,'labels'=>$ctLabels,'a_data'=>$ctAudio,'v_data'=>$ctVideo],
            'chats'    => ['total'=>$chTotal,'direct'=>$chDirect,'group'=>$chGroup],
        ]);
        exit;
    }

    /** POST /admin/users/toggle-admin/{userId} — promote or demote to admin */
    public function toggleAdmin(int $userId): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        if ($userId === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot change your own admin status']);
            exit;
        }

        $db   = \Database::getCoreConnection();
        $stmt = $db->prepare("SELECT is_admin, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $newFlag = $user['is_admin'] == 1 ? 0 : 1;
        $db->prepare("UPDATE users SET is_admin = ? WHERE id = ?")->execute([$newFlag, $userId]);

        echo json_encode(['success' => true, 'is_admin' => (bool)$newFlag, 'name' => $user['full_name']]);
        exit;
    }

    /** POST /admin/settings/auto-approve — toggle the auto-approve flag */
    public function toggleAutoApprove(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $db  = \Database::getCoreConnection();
        $row = $db->query("SELECT value FROM app_settings WHERE key='auto_approve_registration'")->fetch();
        $new = ($row && $row['value'] === '1') ? '0' : '1';

        $db->prepare("
            INSERT INTO app_settings (key, value, updated_at)
            VALUES ('auto_approve_registration', ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at
        ")->execute([$new]);

        echo json_encode(['success' => true, 'auto_approve' => ($new === '1')]);
        exit;
    }

    /* ----------------------------------------------------------------
       PLAN MANAGEMENT
       ---------------------------------------------------------------- */

    /** POST /admin/plans/set/{userId} */
    public function setPlan(int $userId): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $planName              = $_POST['plan_name']                ?? 'trial';
        $expiryDays            = isset($_POST['expiry_days']) && $_POST['expiry_days'] !== ''
                                    ? (int)$_POST['expiry_days'] : null;
        $expiresAt             = $expiryDays !== null ? time() + ($expiryDays * 86400) : null;

        $parseLimit = fn(string $k): ?int =>
            (isset($_POST[$k]) && $_POST[$k] !== '') ? (int)$_POST[$k] : null;

        $ok = Plan::assignPlan(
            $userId,
            $planName,
            $_SESSION['user_id'],
            $expiresAt,
            $parseLimit('limit_text'),
            $parseLimit('limit_image'),
            $parseLimit('limit_video'),
            $parseLimit('limit_audio'),
            $parseLimit('limit_audio_call_minutes'),
            $parseLimit('limit_video_call_minutes')
        );

        echo json_encode(['success' => $ok]);
        exit;
    }

    /** POST /admin/plans/templates/update */
    public function updatePlanTemplate(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $planName = $_POST['plan_name'] ?? '';
        if (!in_array($planName, ['trial', 'heavy', 'unlimited'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid plan name']);
            exit;
        }

        $parseLimit = fn(string $k): ?int =>
            (isset($_POST[$k]) && $_POST[$k] !== '' && $_POST[$k] !== 'null')
                ? (int)$_POST[$k] : null;

        $ok = Plan::updateTemplate(
            $planName,
            $parseLimit('limit_text'),
            $parseLimit('limit_image'),
            $parseLimit('limit_video'),
            $parseLimit('limit_audio'),
            $parseLimit('limit_audio_call_minutes'),
            $parseLimit('limit_video_call_minutes'),
            trim($_POST['contact_number'] ?? ''),
            trim($_POST['contact_text']   ?? '')
        );

        echo json_encode(['success' => $ok]);
        exit;
    }

    /** GET /admin/plans/users — JSON list of users + plans + today's usage */
    public function listUsersWithPlans(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        echo json_encode(['users' => Plan::getAllUsersWithPlans()]);
        exit;
    }

    /* ----------------------------------------------------------------
       UPGRADE REQUESTS
       ---------------------------------------------------------------- */

    /** POST /admin/upgrade-requests/handle/{id} */
    public function handleUpgradeRequest(int $requestId): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
        $note   = trim($_POST['note'] ?? '');

        if (!in_array($action, ['approve', 'reject'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }

        $ok = Plan::handleUpgradeRequest($requestId, $action, $note, $_SESSION['user_id']);

        // If approved, also actually assign the plan
        if ($ok && $action === 'approve') {
            $db = \Database::getCoreConnection();
            $stmt = $db->prepare("SELECT user_id, requested_plan FROM upgrade_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();
            if ($req) {
                Plan::assignPlan(
                    (int)$req['user_id'],
                    $req['requested_plan'],
                    $_SESSION['user_id'],
                    null, null, null, null, null, null, null
                );
            }
        }

        echo json_encode(['success' => $ok]);
        exit;
    }

    /* ----------------------------------------------------------------
       ADMIN NOTIFICATIONS
       ---------------------------------------------------------------- */

    /** POST /admin/notifications/send */
    public function sendNotification(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');

        $title         = trim($_POST['title']          ?? '');
        $body          = trim($_POST['body']           ?? '');
        $targetGroup   = trim($_POST['target_group']   ?? 'all');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $contactText   = trim($_POST['contact_text']   ?? '');

        if (empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Title and body are required.']);
            exit;
        }

        $notifId = Plan::sendNotification(
            $title, $body, $targetGroup, $contactNumber, $contactText, $_SESSION['user_id']
        );

        // Push via SSE so online users see it immediately
        // We publish to a special "system" chat_id that all users receive
        $db = \Database::getCoreConnection();
        $db->prepare("
            INSERT INTO sse_events (chat_id, sender_id, event_type, payload)
            VALUES ('_system', ?, 'notification', ?)
        ")->execute([
            $_SESSION['user_id'],
            json_encode([
                'id'             => $notifId,
                'title'          => $title,
                'body'           => $body,
                'target_group'   => $targetGroup,
                'contact_number' => $contactNumber,
                'contact_text'   => $contactText,
            ])
        ]);

        echo json_encode(['success' => true, 'notification_id' => $notifId]);
        exit;
    }

    /**
     * Approve user registration.
     */
    public function approveUser(int $userId): void {
        $this->checkAdmin();
        
        $success = User::updateStatus($userId, 1); // 1 = Approved
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * Block user account.
     */
    public function blockUser(int $userId): void {
        $this->checkAdmin();
        
        $success = User::updateStatus($userId, 2); // 2 = Blocked
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * Hard delete user account.
     */
    public function deleteUser(int $userId): void {
        $this->checkAdmin();
        
        $success = User::delete($userId);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * Sniff/Read active chats from shard DB before they vanish.
     */
    public function viewChatHistory(string $chatId): void {
        $this->checkAdmin();
        
        $messages = Chat::getMessages($chatId);
        
        // Add sender details
        $participants = Chat::getParticipants($chatId);
        $namesMap = [];
        foreach ($participants as $p) {
            $namesMap[$p['id']] = $p['full_name'];
        }

        foreach ($messages as &$msg) {
            $msg['sender_name'] = $namesMap[$msg['sender_id']] ?? 'Unknown User';
        }

        header('Content-Type: application/json');
        echo json_encode(['messages' => $messages]);
        exit;
    }

    /**
     * Delete specific message inside a sniffed chat.
     */
    public function deleteChatMessage(string $chatId, string $messageId): void {
        $this->checkAdmin();
        
        // forceDelete=true: admin bypasses vanish-count logic, deletes immediately
        $success = Chat::hardDeleteMessage($chatId, $messageId, $_SESSION['user_id'], true);

        if ($success) {
            Chat::publishEvent($chatId, $_SESSION['user_id'], 'vanish', [
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * JSON API: returns all users (for dynamic admin refresh).
     */
    public function listUsers(): void {
        $this->checkAdmin();
        $users = User::getAllUsers();
        header('Content-Type: application/json');
        echo json_encode(['users' => $users]);
        exit;
    }

    /**
     * JSON API: returns all recordings split into live/final groups.
     */
    public function listRecordings(): void {
        $this->checkAdmin();
        $recordings = Call::getAllRecordings();

        $liveAudio = []; $liveVideo = []; $finalAudio = []; $finalVideo = [];
        foreach ($recordings as $rec) {
            $isLive  = empty($rec['recording_file']);
            $isAudio = (($rec['call_type'] ?? 'audio') === 'audio');
            if ($isLive) {
                if ($isAudio) $liveAudio[]  = $rec; else $liveVideo[]  = $rec;
            } else {
                if ($isAudio) $finalAudio[] = $rec; else $finalVideo[] = $rec;
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'live_audio'  => $liveAudio,
            'live_video'  => $liveVideo,
            'final_audio' => $finalAudio,
            'final_video' => $finalVideo,
        ]);
        exit;
    }

    /** POST /admin/recording/delete/{id} — delete one recording file + DB row */
    public function deleteRecording(int $id): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        echo json_encode(['success' => Call::deleteRecording($id)]);
        exit;
    }

    /** POST /admin/recordings/delete-all — wipe every .webm + clear call_records */
    public function deleteAllRecordings(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        $deleted = Call::deleteAllRecordings();
        echo json_encode(['success' => true, 'deleted' => $deleted]);
        exit;
    }

    /** POST /admin/chats/purge-vanished — remove orphaned vanish rows from all shards */
    public function purgeVanishedMessages(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'result' => Chat::purgeVanishedMessages()]);
        exit;
    }

    /** POST /admin/chats/purge-messages/{chatId} — delete all messages from one shard */
    public function purgeChatMessages(string $chatId): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        echo json_encode(['success' => Chat::purgeAllMessages($chatId)]);
        exit;
    }

    /** POST /admin/chats/purge-all — delete every message from every shard */
    public function purgeAllChatsMessages(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'result' => Chat::purgeAllChatsMessages()]);
        exit;
    }

    /* ── Location tracking ───────────────────────────────────── */

    /** GET /admin/locations — all users with their most recent location */
    public function listLocations(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        $db = \Database::getCoreConnection();

        // One row per user: last location (subquery), deny count, total location count
        $stmt = $db->query("
            SELECT u.id, u.full_name, u.email, u.is_approved,
                   COALESCE(u.location_denied, 0) AS location_denied,
                   l.latitude, l.longitude, l.accuracy, l.ip_address,
                   l.user_agent, l.created_at AS last_location_at,
                   (SELECT COUNT(*) FROM user_locations WHERE user_id = u.id) AS location_count
            FROM   users u
            LEFT JOIN user_locations l ON l.id = (
                SELECT id FROM user_locations
                WHERE  user_id = u.id
                ORDER  BY created_at DESC
                LIMIT  1
            )
            WHERE  u.is_admin = 0
            ORDER  BY
                CASE WHEN l.created_at IS NULL THEN 1 ELSE 0 END,
                l.created_at DESC
        ");
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
        exit;
    }

    /** GET /admin/user/{userId}/locations — full location history for one user */
    public function getUserLocations(int $userId): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        $db = \Database::getCoreConnection();
        $stmt = $db->prepare("
            SELECT id, latitude, longitude, accuracy, ip_address, user_agent, created_at
            FROM   user_locations
            WHERE  user_id = ?
            ORDER  BY created_at DESC
        ");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'locations' => $stmt->fetchAll()]);
        exit;
    }

    /* ── Storage: orphaned recordings ───────────────────────── */

    public function listOrphanedRecordings(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        try {
            echo json_encode(['success' => true, 'files' => Call::getOrphanedFiles()]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteOrphanedRecording(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'No filename provided']);
            exit;
        }
        try {
            $ok = Call::deleteOrphanedFile($filename);
            echo json_encode(['success' => $ok, 'error' => $ok ? null : 'File not found or invalid']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteAllOrphanedRecordings(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        try {
            $deleted = Call::deleteAllOrphanedFiles();
            echo json_encode(['success' => true, 'deleted' => $deleted]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /* ── Storage: chat media files ───────────────────────────── */

    public function listChatMedia(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        try {
            $media = Chat::getAllChatMedia(600);
            // Also include orphaned uploads
            $orphanedUploads = Chat::getOrphanedUploads();
            echo json_encode([
                'success'          => true,
                'media'            => $media,
                'orphaned_uploads' => $orphanedUploads,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteMediaFile(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        $chatId    = $_POST['chat_id']    ?? '';
        $messageId = $_POST['message_id'] ?? '';
        if (empty($chatId) || empty($messageId)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            exit;
        }
        try {
            $result = Chat::deleteMediaFile($chatId, $messageId);
            echo json_encode(['success' => $result === 'deleted', 'status' => $result]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteAllChatMedia(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        try {
            $result = Chat::deleteAllChatMedia();
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteOrphanedChatMedia(): void {
        $this->checkAdmin();
        header('Content-Type: application/json');
        try {
            // Delete vanishing media (messages users revealed/vanished)
            $vanishing = Chat::deleteVanishingMedia();
            // Delete orphaned upload files (not referenced by any message)
            $orphaned  = Chat::getOrphanedUploads();
            $deleted   = 0;
            foreach ($orphaned as $f) {
                if (file_exists($f['path']) && @unlink($f['path'])) $deleted++;
            }
            echo json_encode([
                'success'  => true,
                'vanished' => $vanishing,
                'orphaned_files_deleted' => $deleted,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function playRecording(int $id): void {
        $this->checkAdmin();

        $record = Call::getById($id);
        if (!$record) {
            header("HTTP/1.1 404 Not Found");
            echo "Recording not found.";
            exit;
        }

        if (!empty($record['recording_file'])) {
            $filePath = RECORDINGS_DIR . '/' . basename($record['recording_file']);
        } else {
            $filePath = RECORDINGS_DIR . '/temp_' . $id . '.webm';
        }

        if (!file_exists($filePath)) {
            header("HTTP/1.1 404 Not Found");
            echo "Recording file does not exist on disk.";
            exit;
        }

        $fileSize = filesize($filePath);
        $start    = 0;
        $end      = $fileSize - 1;
        $length   = $fileSize;

        header('Content-Type: video/webm');
        header('Accept-Ranges: bytes');

        // Support HTTP Range requests so the browser can seek without re-downloading
        if (!empty($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
                $start  = $m[1] !== '' ? (int)$m[1] : 0;
                $end    = $m[2] !== '' ? min((int)$m[2], $fileSize - 1) : $fileSize - 1;
                $length = $end - $start + 1;
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
            }
        } else {
            header('HTTP/1.1 200 OK');
        }

        header('Content-Length: ' . $length);

        $fp        = fopen($filePath, 'rb');
        fseek($fp, $start);
        $remaining = $length;
        while (!feof($fp) && $remaining > 0) {
            $chunk      = fread($fp, min(65536, $remaining));
            $remaining -= strlen($chunk);
            echo $chunk;
            flush();
        }
        fclose($fp);
        exit;
    }

    /**
     * Chunk-based streaming API for ongoing/live calls.
     * Serves new bytes starting from $_GET['offset'] to support MSE real-time growth.
     */
    public function liveStream(int $id): void {
        $this->checkAdmin();

        $record = Call::getById($id);
        if (!$record) {
            header("HTTP/1.1 404 Not Found");
            echo "Recording not found.";
            exit;
        }

        if (!empty($record['recording_file'])) {
            $filePath = RECORDINGS_DIR . '/' . basename($record['recording_file']);
            $isLive = false;
        } else {
            $filePath = RECORDINGS_DIR . '/temp_' . $id . '.webm';
            $isLive = file_exists($filePath);
        }

        if (!file_exists($filePath)) {
            header("HTTP/1.1 404 Not Found");
            echo "Recording file does not exist on disk.";
            exit;
        }

        $offset = intval($_GET['offset'] ?? 0);
        $fileSize = filesize($filePath);

        // Set live streaming headers
        header('Content-Type: video/webm');
        header('Cache-Control: no-cache');
        header('X-Live-Size: ' . $fileSize);
        header('X-Call-Active: ' . ($isLive ? 'true' : 'false'));

        if ($offset < $fileSize) {
            $fp = fopen($filePath, 'rb');
            if ($fp) {
                fseek($fp, $offset);
                header('Content-Length: ' . ($fileSize - $offset));
                fpassthru($fp);
                fclose($fp);
            }
        } else {
            header('Content-Length: 0');
        }
        exit;
    }
}
