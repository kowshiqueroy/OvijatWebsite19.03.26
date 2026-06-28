<?php
// src/Models/Plan.php

namespace Models;

use Database;
use PDO;

class Plan {

    /* ----------------------------------------------------------------
       PLAN RESOLUTION — returns the effective limits for a user today.
       Also handles expiry (reverts to trial when expires_at has passed).
       ---------------------------------------------------------------- */
    public static function getEffectivePlan(int $userId): array {
        $db = Database::getCoreConnection();

        // Fetch user's current plan row (if any)
        $stmt = $db->prepare("SELECT * FROM user_plans WHERE user_id = ?");
        $stmt->execute([$userId]);
        $up = $stmt->fetch();

        // Auto-expire: if the plan has passed its expiry date, revert to trial
        if ($up && !empty($up['expires_at']) && $up['expires_at'] < time()) {
            $db->prepare("UPDATE user_plans SET plan_name = 'trial',
                limit_text = NULL, limit_image = NULL, limit_video = NULL,
                limit_audio = NULL, limit_audio_call_minutes = NULL, limit_video_call_minutes = NULL,
                expires_at = NULL WHERE user_id = ?")
               ->execute([$userId]);
            $up['plan_name'] = 'trial';
            $up['expires_at'] = null;
        }

        $planName = $up['plan_name'] ?? 'trial';

        // Fetch the template for this plan name
        $stmt2 = $db->prepare("SELECT * FROM plan_templates WHERE plan_name = ?");
        $stmt2->execute([$planName]);
        $tpl = $stmt2->fetch() ?: [];

        // Merge: user-specific overrides take precedence over template defaults
        $resolve = function (string $col) use ($up, $tpl): ?int {
            if ($up && isset($up[$col]) && $up[$col] !== null) return (int)$up[$col];
            if (isset($tpl[$col]) && $tpl[$col] !== null) return (int)$tpl[$col];
            return null; // null = unlimited
        };

        return [
            'plan_name'                 => $planName,
            'plan_label'               => $tpl['label'] ?? ucfirst($planName),
            'expires_at'               => $up['expires_at'] ?? null,
            'limit_text'               => $resolve('limit_text'),
            'limit_image'              => $resolve('limit_image'),
            'limit_video'              => $resolve('limit_video'),
            'limit_audio'              => $resolve('limit_audio'),
            'limit_audio_call_minutes' => $resolve('limit_audio_call_minutes'),
            'limit_video_call_minutes' => $resolve('limit_video_call_minutes'),
        ];
    }

    /* ----------------------------------------------------------------
       DAILY USAGE — get today's counts for a user.
       ---------------------------------------------------------------- */
    public static function getTodayUsage(int $userId): array {
        $db   = Database::getCoreConnection();
        $date = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM daily_usage WHERE user_id = ? AND usage_date = ?");
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch();
        return $row ?: [
            'text_count'          => 0,
            'image_count'         => 0,
            'video_count'         => 0,
            'audio_count'         => 0,
            'audio_call_minutes'  => 0,
            'video_call_minutes'  => 0,
        ];
    }

    /* ----------------------------------------------------------------
       CHECK LIMIT — returns true if allowed, false if limit hit.
       $type: 'text' | 'image' | 'video' | 'audio'
       ---------------------------------------------------------------- */
    public static function checkLimit(int $userId, string $type): bool {
        $plan  = self::getEffectivePlan($userId);
        $limitKey = "limit_{$type}";
        if (!isset($plan[$limitKey]) || $plan[$limitKey] === null) return true; // unlimited

        $usage    = self::getTodayUsage($userId);
        $countKey = "{$type}_count";
        $current  = (int)($usage[$countKey] ?? 0);
        return $current < $plan[$limitKey];
    }

    /* ----------------------------------------------------------------
       CHECK CALL LIMIT — returns remaining call minutes for a type.
       $callType: 'audio' | 'video'
       ---------------------------------------------------------------- */
    public static function checkCallLimit(int $userId, string $callType): ?int {
        $plan     = self::getEffectivePlan($userId);
        $limitKey = "limit_{$callType}_call_minutes";
        if (!isset($plan[$limitKey]) || $plan[$limitKey] === null) return null; // unlimited (null)

        $usage    = self::getTodayUsage($userId);
        $countKey = "{$callType}_call_minutes";
        $used     = (int)($usage[$countKey] ?? 0);
        return max(0, $plan[$limitKey] - $used);
    }

    /* ----------------------------------------------------------------
       INCREMENT USAGE — call after a successful message/upload/call-end.
       ---------------------------------------------------------------- */
    public static function incrementUsage(int $userId, string $type, int $amount = 1): void {
        $db   = Database::getCoreConnection();
        $date = date('Y-m-d');
        $col  = match ($type) {
            'text'              => 'text_count',
            'image'             => 'image_count',
            'video'             => 'video_count',
            'audio'             => 'audio_count',
            'audio_call'        => 'audio_call_minutes',
            'video_call'        => 'video_call_minutes',
            default             => null,
        };
        if (!$col) return;

        $db->prepare("
            INSERT INTO daily_usage (user_id, usage_date, {$col})
            VALUES (?, ?, ?)
            ON CONFLICT(user_id, usage_date) DO UPDATE SET {$col} = {$col} + excluded.{$col}
        ")->execute([$userId, $date, $amount]);
    }

    /* ----------------------------------------------------------------
       USAGE SUMMARY — plan + today's usage + remaining + warning flags
       ---------------------------------------------------------------- */
    public static function getUsageSummary(int $userId): array {
        $plan  = self::getEffectivePlan($userId);
        $usage = self::getTodayUsage($userId);

        $types = [
            'text'  => ['limit' => $plan['limit_text'],  'used' => (int)$usage['text_count']],
            'image' => ['limit' => $plan['limit_image'], 'used' => (int)$usage['image_count']],
            'video' => ['limit' => $plan['limit_video'], 'used' => (int)$usage['video_count']],
            'audio' => ['limit' => $plan['limit_audio'], 'used' => (int)$usage['audio_count']],
            'audio_call' => ['limit' => $plan['limit_audio_call_minutes'], 'used' => (int)$usage['audio_call_minutes']],
            'video_call' => ['limit' => $plan['limit_video_call_minutes'], 'used' => (int)$usage['video_call_minutes']],
        ];

        foreach ($types as $k => &$v) {
            if ($v['limit'] === null) {
                $v['remaining'] = null;
                $v['pct']       = 0;
                $v['warn']      = false;
                $v['blocked']   = false;
            } else {
                $v['remaining'] = max(0, $v['limit'] - $v['used']);
                $v['pct']       = $v['limit'] > 0 ? min(100, round($v['used'] / $v['limit'] * 100)) : 100;
                $v['warn']      = $v['pct'] >= 80;
                $v['blocked']   = $v['pct'] >= 100;
            }
        }

        return [
            'plan'       => $plan,
            'usage'      => $usage,
            'types'      => $types,
            'expires_at' => $plan['expires_at'],
        ];
    }

    /* ----------------------------------------------------------------
       ASSIGN PLAN — admin sets a user's plan
       ---------------------------------------------------------------- */
    public static function assignPlan(
        int    $userId,
        string $planName,
        int    $adminId,
        ?int   $expiresAt,
        ?int   $limitText,
        ?int   $limitImage,
        ?int   $limitVideo,
        ?int   $limitAudio,
        ?int   $limitAudioCallMinutes,
        ?int   $limitVideoCallMinutes
    ): bool {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            INSERT INTO user_plans
                (user_id, plan_name, expires_at, assigned_by, assigned_at,
                 limit_text, limit_image, limit_video, limit_audio,
                 limit_audio_call_minutes, limit_video_call_minutes)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(user_id) DO UPDATE SET
                plan_name                = excluded.plan_name,
                expires_at               = excluded.expires_at,
                assigned_by              = excluded.assigned_by,
                assigned_at              = excluded.assigned_at,
                limit_text               = excluded.limit_text,
                limit_image              = excluded.limit_image,
                limit_video              = excluded.limit_video,
                limit_audio              = excluded.limit_audio,
                limit_audio_call_minutes = excluded.limit_audio_call_minutes,
                limit_video_call_minutes = excluded.limit_video_call_minutes
        ");
        return $stmt->execute([
            $userId, $planName, $expiresAt, $adminId,
            $limitText, $limitImage, $limitVideo, $limitAudio,
            $limitAudioCallMinutes, $limitVideoCallMinutes
        ]);
    }

    /* ----------------------------------------------------------------
       PLAN TEMPLATES
       ---------------------------------------------------------------- */
    public static function getAllTemplates(): array {
        $db   = Database::getCoreConnection();
        $stmt = $db->query("SELECT * FROM plan_templates ORDER BY plan_name");
        return $stmt->fetchAll();
    }

    public static function updateTemplate(
        string $planName,
        ?int   $limitText,
        ?int   $limitImage,
        ?int   $limitVideo,
        ?int   $limitAudio,
        ?int   $limitAudioCallMinutes,
        ?int   $limitVideoCallMinutes,
        string $contactNumber,
        string $contactText
    ): bool {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            UPDATE plan_templates SET
                limit_text               = ?,
                limit_image              = ?,
                limit_video              = ?,
                limit_audio              = ?,
                limit_audio_call_minutes = ?,
                limit_video_call_minutes = ?,
                contact_number           = ?,
                contact_text             = ?,
                updated_at               = CURRENT_TIMESTAMP
            WHERE plan_name = ?
        ");
        return $stmt->execute([
            $limitText, $limitImage, $limitVideo, $limitAudio,
            $limitAudioCallMinutes, $limitVideoCallMinutes,
            $contactNumber, $contactText, $planName
        ]);
    }

    /* ----------------------------------------------------------------
       UPGRADE REQUESTS
       ---------------------------------------------------------------- */
    public static function submitUpgradeRequest(int $userId, string $plan, string $message): bool {
        $db = Database::getCoreConnection();
        // Only allow one pending request at a time
        $check = $db->prepare("SELECT id FROM upgrade_requests WHERE user_id = ? AND status = 'pending'");
        $check->execute([$userId]);
        if ($check->fetch()) return false; // already pending

        $stmt = $db->prepare("
            INSERT INTO upgrade_requests (user_id, requested_plan, message, status)
            VALUES (?, ?, ?, 'pending')
        ");
        return $stmt->execute([$userId, $plan, $message]);
    }

    public static function getPendingRequests(): array {
        $db   = Database::getCoreConnection();
        $stmt = $db->query("
            SELECT ur.*, u.full_name, u.email, u.phone,
                   COALESCE(up.plan_name, 'trial') AS current_plan
            FROM upgrade_requests ur
            JOIN users u ON u.id = ur.user_id
            LEFT JOIN user_plans up ON up.user_id = ur.user_id
            ORDER BY ur.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public static function getAllRequests(): array {
        $db   = Database::getCoreConnection();
        $stmt = $db->query("
            SELECT ur.*, u.full_name, u.email,
                   COALESCE(up.plan_name, 'trial') AS current_plan
            FROM upgrade_requests ur
            JOIN users u ON u.id = ur.user_id
            LEFT JOIN user_plans up ON up.user_id = ur.user_id
            ORDER BY ur.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public static function handleUpgradeRequest(int $requestId, string $action, string $note, int $adminId): bool {
        $db   = Database::getCoreConnection();
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        $stmt = $db->prepare("
            UPDATE upgrade_requests
            SET status = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$status, $note, $requestId]);
    }

    public static function getUserRequest(int $userId): ?array {
        $db   = Database::getCoreConnection();
        $stmt = $db->prepare("
            SELECT * FROM upgrade_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ----------------------------------------------------------------
       ADMIN NOTIFICATIONS
       ---------------------------------------------------------------- */
    public static function sendNotification(
        string $title,
        string $body,
        string $targetGroup,
        string $contactNumber,
        string $contactText,
        int    $sentBy
    ): int {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            INSERT INTO admin_notifications
                (title, body, target_group, contact_number, contact_text, sent_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $body, $targetGroup, $contactNumber, $contactText, $sentBy]);
        return (int)$db->lastInsertId();
    }

    public static function getNotificationsForUser(int $userId): array {
        $db = Database::getCoreConnection();

        // Determine user's plan group
        $planStmt = $db->prepare("SELECT plan_name FROM user_plans WHERE user_id = ?");
        $planStmt->execute([$userId]);
        $planRow  = $planStmt->fetch();
        $planName = $planRow ? $planRow['plan_name'] : 'trial';

        $stmt = $db->prepare("
            SELECT n.*,
                   CASE WHEN nr.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM admin_notifications n
            LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
            WHERE n.target_group = 'all' OR n.target_group = ?
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId, $planName]);
        return $stmt->fetchAll();
    }

    public static function countUnread(int $userId): int {
        $db = Database::getCoreConnection();
        $planStmt = $db->prepare("SELECT plan_name FROM user_plans WHERE user_id = ?");
        $planStmt->execute([$userId]);
        $planRow  = $planStmt->fetch();
        $planName = $planRow ? $planRow['plan_name'] : 'trial';

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM admin_notifications n
            WHERE (n.target_group = 'all' OR n.target_group = ?)
              AND NOT EXISTS (
                  SELECT 1 FROM notification_reads nr
                  WHERE nr.notification_id = n.id AND nr.user_id = ?
              )
        ");
        $stmt->execute([$planName, $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markNotificationRead(int $userId, int $notificationId): void {
        $db = Database::getCoreConnection();
        $db->prepare("
            INSERT OR IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)
        ")->execute([$notificationId, $userId]);
    }

    public static function markAllRead(int $userId): void {
        $db = Database::getCoreConnection();
        $notifs = self::getNotificationsForUser($userId);
        foreach ($notifs as $n) {
            $db->prepare("INSERT OR IGNORE INTO notification_reads (notification_id, user_id) VALUES (?,?)")
               ->execute([$n['id'], $userId]);
        }
    }

    public static function getAllNotifications(): array {
        $db   = Database::getCoreConnection();
        $stmt = $db->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    /* ----------------------------------------------------------------
       ADMIN — all users with their current plan + today's usage
       ---------------------------------------------------------------- */
    public static function getAllUsersWithPlans(): array {
        $db   = Database::getCoreConnection();
        $today = date('Y-m-d');
        $stmt  = $db->prepare("
            SELECT u.id, u.full_name, u.email, u.phone, u.is_approved,
                   COALESCE(up.plan_name, 'trial') AS plan_name,
                   up.expires_at,
                   COALESCE(du.text_count, 0)         AS text_count,
                   COALESCE(du.image_count, 0)        AS image_count,
                   COALESCE(du.video_count, 0)        AS video_count,
                   COALESCE(du.audio_count, 0)        AS audio_count,
                   COALESCE(du.audio_call_minutes, 0) AS audio_call_minutes,
                   COALESCE(du.video_call_minutes, 0) AS video_call_minutes
            FROM users u
            LEFT JOIN user_plans up ON up.user_id = u.id
            LEFT JOIN daily_usage du ON du.user_id = u.id AND du.usage_date = ?
            WHERE u.is_admin = 0
            ORDER BY u.full_name
        ");
        $stmt->execute([$today]);
        return $stmt->fetchAll();
    }
}
