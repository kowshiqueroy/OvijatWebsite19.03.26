-- ============================================================
-- SWP Project Manager — Migration
-- Run once against the `swp` database
-- Safe: uses IF NOT EXISTS / MODIFY only where needed
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── 1. users: add email, last_login, avatar_url ─────────────
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `email`      VARCHAR(180)  NULL UNIQUE   AFTER `username`,
  ADD COLUMN IF NOT EXISTS `avatar_url` VARCHAR(500)  NULL          AFTER `full_name`,
  ADD COLUMN IF NOT EXISTS `last_login` DATETIME      NULL          AFTER `is_active`;

-- ── 2. projects: add budget, deleted_at ─────────────────────
ALTER TABLE `projects`
  ADD COLUMN IF NOT EXISTS `budget`     DECIMAL(14,2) NULL          AFTER `tech_notes`,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME      NULL          AFTER `updated_at`;

-- ── 3. tasks: add actual_hours, blocked_by_task_id, deleted_at
ALTER TABLE `tasks`
  ADD COLUMN IF NOT EXISTS `actual_hours`       DECIMAL(6,2) NULL   AFTER `estimated_hours`,
  ADD COLUMN IF NOT EXISTS `blocked_by_task_id` INT          NULL   AFTER `parent_task_id`,
  ADD COLUMN IF NOT EXISTS `deleted_at`         DATETIME     NULL   AFTER `updated_at`,
  ADD CONSTRAINT IF NOT EXISTS `fk_task_blocked_by`
    FOREIGN KEY (`blocked_by_task_id`) REFERENCES `tasks`(`id`) ON DELETE SET NULL;

-- ── 4. worksheet_chat: add edit tracking ────────────────────
ALTER TABLE `worksheet_chat`
  ADD COLUMN IF NOT EXISTS `is_edited`  TINYINT(1)   NOT NULL DEFAULT 0  AFTER `is_pinned`,
  ADD COLUMN IF NOT EXISTS `edited_at`  DATETIME     NULL                 AFTER `is_edited`;

-- ── 5. meeting_action_items: add priority, updated_at ───────
ALTER TABLE `meeting_action_items`
  ADD COLUMN IF NOT EXISTS `priority`   ENUM('low','medium','high') NOT NULL DEFAULT 'medium' AFTER `is_done`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP            AFTER `created_at`;

-- ── 6. milestones: add description, updated_at ──────────────
ALTER TABLE `milestones`
  ADD COLUMN IF NOT EXISTS `description` TEXT NULL          AFTER `title`,
  ADD COLUMN IF NOT EXISTS `updated_at`  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ── 7. notifications: add related entity fields ─────────────
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `related_entity_type` VARCHAR(40) NULL AFTER `link`,
  ADD COLUMN IF NOT EXISTS `related_entity_id`   INT         NULL AFTER `related_entity_type`;

-- ── 8. Fix RSVP enum: standardize to pending/yes/no/maybe ───
-- First set any old 'confirmed' → 'yes', 'declined' → 'no'
UPDATE `meeting_attendees` SET `rsvp` = 'yes'   WHERE `rsvp` = 'confirmed';
UPDATE `meeting_attendees` SET `rsvp` = 'no'    WHERE `rsvp` = 'declined';
-- Now alter the column to the clean enum
ALTER TABLE `meeting_attendees`
  MODIFY COLUMN `rsvp` ENUM('pending','yes','no','maybe') NOT NULL DEFAULT 'pending';

-- ── 9. CHECK constraints (MySQL 8.0.16+) ────────────────────
ALTER TABLE `task_time_logs`
  ADD CONSTRAINT IF NOT EXISTS `chk_hours_positive`
    CHECK (`hours` > 0);

ALTER TABLE `tasks`
  ADD CONSTRAINT IF NOT EXISTS `chk_est_hours_positive`
    CHECK (`estimated_hours` IS NULL OR `estimated_hours` > 0),
  ADD CONSTRAINT IF NOT EXISTS `chk_actual_hours_positive`
    CHECK (`actual_hours` IS NULL OR `actual_hours` > 0);

ALTER TABLE `meetings`
  ADD CONSTRAINT IF NOT EXISTS `chk_duration_positive`
    CHECK (`duration_minutes` IS NULL OR `duration_minutes` > 0);

-- ── 10. Performance indexes ──────────────────────────────────
-- tasks
CREATE INDEX IF NOT EXISTS `idx_tasks_project_id`  ON `tasks`(`project_id`);
CREATE INDEX IF NOT EXISTS `idx_tasks_status`       ON `tasks`(`status`);
CREATE INDEX IF NOT EXISTS `idx_tasks_due_date`     ON `tasks`(`due_date`);
CREATE INDEX IF NOT EXISTS `idx_tasks_deleted_at`   ON `tasks`(`deleted_at`);
CREATE INDEX IF NOT EXISTS `idx_tasks_created_by`   ON `tasks`(`created_by`);

-- task_assignees
CREATE INDEX IF NOT EXISTS `idx_ta_task_id`         ON `task_assignees`(`task_id`);
CREATE INDEX IF NOT EXISTS `idx_ta_user_id`         ON `task_assignees`(`user_id`);

-- task_comments
CREATE INDEX IF NOT EXISTS `idx_tc_task_id`         ON `task_comments`(`task_id`);

-- worksheet_chat
CREATE INDEX IF NOT EXISTS `idx_wc_project_id`      ON `worksheet_chat`(`project_id`);
CREATE INDEX IF NOT EXISTS `idx_wc_user_id`         ON `worksheet_chat`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_wc_created_at`      ON `worksheet_chat`(`created_at`);

-- updates
CREATE INDEX IF NOT EXISTS `idx_updates_project_id` ON `updates`(`project_id`);
CREATE INDEX IF NOT EXISTS `idx_updates_created_at` ON `updates`(`created_at`);

-- meetings
CREATE INDEX IF NOT EXISTS `idx_meetings_date`      ON `meetings`(`meeting_date`);
CREATE INDEX IF NOT EXISTS `idx_meetings_status`    ON `meetings`(`status`);
CREATE INDEX IF NOT EXISTS `idx_meetings_project`   ON `meetings`(`project_id`);

-- meeting_attendees
CREATE INDEX IF NOT EXISTS `idx_ma_meeting_id`      ON `meeting_attendees`(`meeting_id`);
CREATE INDEX IF NOT EXISTS `idx_ma_user_id`         ON `meeting_attendees`(`user_id`);

-- projects
CREATE INDEX IF NOT EXISTS `idx_proj_status`        ON `projects`(`status`);
CREATE INDEX IF NOT EXISTS `idx_proj_deleted_at`    ON `projects`(`deleted_at`);

-- notifications
CREATE INDEX IF NOT EXISTS `idx_notif_user_id`      ON `notifications`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_notif_is_read`      ON `notifications`(`is_read`);

-- task_time_logs
CREATE INDEX IF NOT EXISTS `idx_ttl_task_id`        ON `task_time_logs`(`task_id`);
CREATE INDEX IF NOT EXISTS `idx_ttl_user_id`        ON `task_time_logs`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_ttl_logged_at`      ON `task_time_logs`(`logged_at`);

SET foreign_key_checks = 1;
