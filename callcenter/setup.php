<?php
/**
 * Ovijat Call Center - Database Setup
 * Drops and recreates callcenter_db with full schema.
 * Run once. Delete after use in production.
 */

// ── SAFETY LOCK ──────────────────────────────────────────────────────────────
// CHANGE THIS TO true TO ENABLE THE SETUP SCRIPT
define('ENABLE_SETUP', false);

if (!ENABLE_SETUP) {
    die('<div style="font-family:sans-serif;padding:2rem;background:#1a1d27;color:#fca5a5;text-align:center">
         <h3>Setup Locked</h3>
         <p>For security, you must edit <code>setup.php</code> and set <code>ENABLE_SETUP</code> to <code>true</code> to run this script.</p>
         <p style="color:#94a3b8;font-size:.9rem">Warning: This script will WIPE all existing data.</p>
         </div>');
}

require_once 'config.php';
$messages = [];

try {
    $host   = DB_HOST;
    $user   = DB_USER;
    $pass   = DB_PASS;
    $dbname = DB_NAME;

    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) throw new Exception("MySQL connection failed: " . $conn->connect_error);
    $messages[] = ['success', 'Connected to MySQL server'];

    $conn->query("DROP DATABASE IF EXISTS `$dbname`");
    $messages[] = ['info', "Dropped existing database <code>$dbname</code> (if existed)"];

    if (!$conn->query("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        throw new Exception("Failed to create database: " . $conn->error);
    }
    $messages[] = ['success', "Created database: <code>$dbname</code>"];
    $conn->select_db($dbname);

    /* ================================================================
       TABLES — created one by one for clear error reporting
    ================================================================ */

    $tables = [];

    // ------------------------------------------------------------------
    // 1. AGENTS — single user type, agents are the only users
    // ------------------------------------------------------------------
    $tables['agents'] = "CREATE TABLE agents (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        username        VARCHAR(80)  UNIQUE NOT NULL,
        password        VARCHAR(255) NOT NULL,
        full_name       VARCHAR(150) NOT NULL,
        email           VARCHAR(150) DEFAULT NULL,
        avatar          VARCHAR(255) DEFAULT NULL,
        department      VARCHAR(100) DEFAULT NULL,
        status          ENUM('active','inactive') DEFAULT 'active',
        last_login      DATETIME     DEFAULT NULL,
        created_by      INT          DEFAULT NULL COMMENT 'agent_id who created this account',
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_status   (status)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 2. AGENT_NUMBERS — extensions + 11-digit official numbers per agent
    // ------------------------------------------------------------------
    $tables['agent_numbers'] = "CREATE TABLE agent_numbers (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        agent_id    INT          NOT NULL,
        number_type ENUM('extension','mobile','direct_line','official','other') DEFAULT 'extension',
        number      VARCHAR(30)  NOT NULL,
        label       VARCHAR(100) DEFAULT NULL COMMENT 'e.g. Sales DID, Office Ext',
        is_primary  TINYINT(1)   DEFAULT 0,
        created_by  INT          NOT NULL COMMENT 'agent_id who added this number',
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
        INDEX idx_number   (number),
        INDEX idx_agent_id (agent_id)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 3. PBX_SETTINGS — FreePBX MySQL credentials (any agent can configure)
    // ------------------------------------------------------------------
    $tables['pbx_settings'] = "CREATE TABLE pbx_settings (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        name                VARCHAR(100) NOT NULL COMMENT 'Friendly label e.g. Main PBX',
        pbx_host            VARCHAR(255) NOT NULL COMMENT 'FreePBX web URL for recordings',
        db_host             VARCHAR(255) DEFAULT 'localhost',
        db_port             SMALLINT     DEFAULT 3306,
        db_name             VARCHAR(100) DEFAULT 'asteriskcdrdb',
        db_username         VARCHAR(100) NOT NULL,
        db_password         VARCHAR(255) NOT NULL,
        recording_base_url  VARCHAR(500) DEFAULT NULL COMMENT 'Base URL to stream recordings',
        recording_base_path VARCHAR(500) DEFAULT NULL COMMENT 'Server path if accessible locally',
        is_active           TINYINT(1)   DEFAULT 1,
        created_by          INT NOT NULL COMMENT 'agent_id who set this up',
        updated_by          INT DEFAULT NULL COMMENT 'agent_id who last updated',
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES agents(id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 4. CONTACT_GROUPS
    // ------------------------------------------------------------------
    $tables['contact_groups'] = "CREATE TABLE contact_groups (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        color       VARCHAR(20)  DEFAULT '#6366f1',
        description VARCHAR(255) DEFAULT NULL,
        created_by  INT NOT NULL COMMENT 'agent_id',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES agents(id)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 5. CONTACT_TYPES
    // ------------------------------------------------------------------
    $tables['contact_types'] = "CREATE TABLE contact_types (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        color       VARCHAR(20)  DEFAULT '#10b981',
        description VARCHAR(255) DEFAULT NULL,
        created_by  INT NOT NULL COMMENT 'agent_id',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES agents(id)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 6. CONTACTS — unique contact base, phone as unique key
    //    Auto-populated from CDR fetch. Agents enrich the data.
    // ------------------------------------------------------------------
    $tables['contacts'] = "CREATE TABLE contacts (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        phone           VARCHAR(30)  NOT NULL COMMENT 'Canonical phone number — unique',
        name            VARCHAR(150) DEFAULT NULL,
        type_id         INT          DEFAULT NULL COMMENT 'FK contact_types',
        group_id        INT          DEFAULT NULL COMMENT 'FK contact_groups',
        company         VARCHAR(200) DEFAULT NULL,
        email           VARCHAR(150) DEFAULT NULL,
        address         VARCHAR(500) DEFAULT NULL,
        scope           ENUM('internal','external','unknown') DEFAULT 'unknown',
        office_type     ENUM('head_office','branch','field','remote','other') DEFAULT NULL,
        is_favorite     TINYINT(1)   DEFAULT 0,
        is_blocked      TINYINT(1)   DEFAULT 0,
        assigned_to     INT          DEFAULT NULL COMMENT 'agent_id responsible for this contact',
        notes           TEXT         DEFAULT NULL COMMENT 'Quick bio/note visible on call',
        status          ENUM('active','inactive') DEFAULT 'active',
        created_by      INT          NOT NULL COMMENT 'agent_id or 0 for auto-created from CDR',
        updated_by      INT          DEFAULT NULL COMMENT 'agent_id who last edited',
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_phone (phone),
        FOREIGN KEY (type_id)    REFERENCES contact_types(id)  ON DELETE SET NULL,
        FOREIGN KEY (group_id)   REFERENCES contact_groups(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES agents(id)        ON DELETE SET NULL,
        INDEX idx_name   (name),
        INDEX idx_scope  (scope),
        INDEX idx_status (status)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 7. FETCH_BATCHES — one row per manual PBX fetch run
    // ------------------------------------------------------------------
    $tables['fetch_batches'] = "CREATE TABLE fetch_batches (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        pbx_setting_id      INT     NOT NULL,
        fetched_by          INT     NOT NULL COMMENT 'agent_id who triggered the fetch',
        date_from           DATE    DEFAULT NULL,
        date_to             DATE    DEFAULT NULL,
        total_fetched       INT     DEFAULT 0 COMMENT 'Rows read from PBX CDR',
        new_records         INT     DEFAULT 0 COMMENT 'Inserted into call_logs',
        duplicates_skipped  INT     DEFAULT 0 COMMENT 'Already existed by uniqueid',
        contacts_created    INT     DEFAULT 0 COMMENT 'New contacts auto-created',
        status              ENUM('running','completed','failed') DEFAULT 'running',
        error_message       TEXT    DEFAULT NULL,
        started_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at        DATETIME  DEFAULT NULL,
        FOREIGN KEY (pbx_setting_id) REFERENCES pbx_settings(id),
        FOREIGN KEY (fetched_by)     REFERENCES agents(id),
        INDEX idx_status (status),
        INDEX idx_fetched_by (fetched_by)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 8. CALL_LOGS — ALL FreePBX CDR columns + our operational fields
    //    uniqueid is the dedup key (UNIQUE constraint)
    // ------------------------------------------------------------------
    $tables['call_logs'] = "CREATE TABLE call_logs (
        id              INT AUTO_INCREMENT PRIMARY KEY,

        -- ── FreePBX CDR columns (verbatim) ──────────────────────────
        calldate        DATETIME     DEFAULT NULL COMMENT 'Call start datetime',
        clid            VARCHAR(80)  DEFAULT NULL COMMENT 'Caller ID string: \"Name <number>\"',
        src             VARCHAR(80)  DEFAULT NULL COMMENT 'Source channel number',
        dst             VARCHAR(80)  DEFAULT NULL COMMENT 'Destination number',
        dcontext        VARCHAR(80)  DEFAULT NULL COMMENT 'Destination context',
        channel         VARCHAR(80)  DEFAULT NULL COMMENT 'Source channel name',
        dstchannel      VARCHAR(80)  DEFAULT NULL COMMENT 'Destination channel name',
        lastapp         VARCHAR(80)  DEFAULT NULL COMMENT 'Last dialplan application',
        lastdata        VARCHAR(80)  DEFAULT NULL COMMENT 'Last dialplan app data',
        duration        INT          DEFAULT 0    COMMENT 'Total call duration seconds',
        billsec         INT          DEFAULT 0    COMMENT 'Billable seconds (answered time)',
        disposition     VARCHAR(45)  DEFAULT NULL COMMENT 'ANSWERED|NO ANSWER|BUSY|FAILED|CONGESTION',
        amaflags        INT          DEFAULT 0    COMMENT 'AMA flags',
        accountcode     VARCHAR(20)  DEFAULT NULL,
        uniqueid        VARCHAR(50)  DEFAULT NULL COMMENT 'FreePBX unique call ID — dedup key',
        userfield       VARCHAR(255) DEFAULT NULL,
        recordingfile   VARCHAR(500) DEFAULT NULL COMMENT 'Recording file path/name from PBX',
        local_recording VARCHAR(500) DEFAULT NULL COMMENT 'Local path after fetching from PBX',
        cnum            VARCHAR(80)  DEFAULT NULL COMMENT 'Caller number (cleaned)',
        cnam            VARCHAR(80)  DEFAULT NULL COMMENT 'Caller name',
        outbound_cnum   VARCHAR(80)  DEFAULT NULL,
        outbound_cnam   VARCHAR(80)  DEFAULT NULL,
        dst_cnam        VARCHAR(80)  DEFAULT NULL,
        linkedid        VARCHAR(32)  DEFAULT NULL COMMENT 'Linked call chain ID',
        sequence        INT          DEFAULT NULL COMMENT 'CDR sequence number',

        -- ── Our operational fields ───────────────────────────────────
        call_direction  ENUM('inbound','outbound','internal','unknown','conflict') DEFAULT 'unknown',
        contact_id      INT          DEFAULT NULL COMMENT 'Matched contact from contacts table',
        agent_id        INT          DEFAULT NULL COMMENT 'Handling agent',
        fetch_batch_id  INT          DEFAULT NULL COMMENT 'Which fetch brought this in',
        call_mark       ENUM('normal','follow_up','callback','resolved','urgent','escalated','no_action') DEFAULT 'normal',
        is_manual       TINYINT(1)   DEFAULT 0    COMMENT '1 = manually entered by agent',
        manual_notes    TEXT         DEFAULT NULL COMMENT 'Notes for manual call entries',
        created_by      INT          DEFAULT NULL COMMENT 'agent_id — NULL if auto-fetched',
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_by      INT          DEFAULT NULL COMMENT 'agent_id who last updated this record',
        updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY uq_uniqueid   (uniqueid),
        FOREIGN KEY (contact_id)      REFERENCES contacts(id)      ON DELETE SET NULL,
        FOREIGN KEY (agent_id)        REFERENCES agents(id)        ON DELETE SET NULL,
        FOREIGN KEY (fetch_batch_id)  REFERENCES fetch_batches(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by)      REFERENCES agents(id)        ON DELETE SET NULL,
        FOREIGN KEY (updated_by)      REFERENCES agents(id)        ON DELETE SET NULL,

        INDEX idx_calldate    (calldate),
        INDEX idx_src         (src),
        INDEX idx_dst         (dst),
        INDEX idx_disposition (disposition),
        INDEX idx_direction   (call_direction),
        INDEX idx_contact     (contact_id),
        INDEX idx_agent       (agent_id),
        INDEX idx_mark        (call_mark),
        INDEX idx_is_manual   (is_manual)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 9. CALL_NOTES — threaded notes on a call
    //    parent_id = NULL means root note; parent_id set means reply
    // ------------------------------------------------------------------
    $tables['call_notes'] = "CREATE TABLE call_notes (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        call_id     INT NOT NULL,
        agent_id    INT NOT NULL COMMENT 'Who wrote this note',
        parent_id   INT DEFAULT NULL COMMENT 'NULL = root note; set = reply to parent',
        note_type   ENUM('note','issue','feedback','resolution','query','followup','internal','reply') DEFAULT 'note',
        priority    ENUM('low','medium','high','urgent') DEFAULT 'low',
        log_status  ENUM('open','closed','pending','followup') DEFAULT 'open',
        content     TEXT NOT NULL,
        is_pinned   TINYINT(1) DEFAULT 0,
        edited_by   INT DEFAULT NULL COMMENT 'agent_id who last edited',
        edited_at   DATETIME   DEFAULT NULL,
        created_at  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (call_id)   REFERENCES call_logs(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id)  REFERENCES agents(id),
        FOREIGN KEY (parent_id) REFERENCES call_notes(id) ON DELETE CASCADE,
        FOREIGN KEY (edited_by) REFERENCES agents(id),
        INDEX idx_call_id  (call_id),
        INDEX idx_agent_id (agent_id),
        INDEX idx_status   (log_status)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 10. CONTACT_NOTES — threaded notes on a contact (not tied to one call)
    // ------------------------------------------------------------------
    $tables['contact_notes'] = "CREATE TABLE contact_notes (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        contact_id  INT NOT NULL,
        agent_id    INT NOT NULL COMMENT 'Who wrote this note',
        parent_id   INT DEFAULT NULL COMMENT 'NULL = root; set = reply',
        note_type   ENUM('note','issue','feedback','resolution','query','followup','internal','reply') DEFAULT 'note',
        priority    ENUM('low','medium','high','urgent') DEFAULT 'low',
        log_status  ENUM('open','closed','pending','followup') DEFAULT 'open',
        content     TEXT NOT NULL,
        is_pinned   TINYINT(1) DEFAULT 0,
        edited_by   INT DEFAULT NULL COMMENT 'agent_id who last edited',
        edited_at   DATETIME   DEFAULT NULL,
        created_at  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id)   REFERENCES agents(id),
        FOREIGN KEY (parent_id)  REFERENCES contact_notes(id) ON DELETE CASCADE,
        FOREIGN KEY (edited_by)  REFERENCES agents(id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_agent_id   (agent_id),
        INDEX idx_status     (log_status)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 11. TODOS — tasks linked to call and/or contact
    // ------------------------------------------------------------------
    $tables['todos'] = "CREATE TABLE todos (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(255) NOT NULL,
        description  TEXT         DEFAULT NULL,
        call_id      INT          DEFAULT NULL COMMENT 'Linked call (optional)',
        contact_id   INT          DEFAULT NULL COMMENT 'Linked contact (optional)',
        created_by   INT          NOT NULL COMMENT 'agent_id who created the task',
        assigned_to  INT          DEFAULT NULL COMMENT 'agent_id responsible for doing it',
        priority     ENUM('low','medium','high','urgent') DEFAULT 'medium',
        status       ENUM('pending','in_progress','done','cancelled') DEFAULT 'pending',
        due_date     DATETIME     DEFAULT NULL,
        completed_at DATETIME     DEFAULT NULL,
        completed_by INT          DEFAULT NULL COMMENT 'agent_id who marked done',
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (call_id)      REFERENCES call_logs(id) ON DELETE SET NULL,
        FOREIGN KEY (contact_id)   REFERENCES contacts(id)  ON DELETE SET NULL,
        FOREIGN KEY (created_by)   REFERENCES agents(id),
        FOREIGN KEY (assigned_to)  REFERENCES agents(id)    ON DELETE SET NULL,
        FOREIGN KEY (completed_by) REFERENCES agents(id)    ON DELETE SET NULL,
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_status      (status),
        INDEX idx_priority    (priority),
        INDEX idx_due_date    (due_date)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 12. TODO_LOGS — full audit trail of every change on a task
    // ------------------------------------------------------------------
    $tables['todo_logs'] = "CREATE TABLE todo_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        todo_id     INT          NOT NULL,
        agent_id    INT          NOT NULL COMMENT 'Who performed the action',
        action      VARCHAR(100) NOT NULL COMMENT 'e.g. created, assigned, status_changed, edited, commented',
        old_value   VARCHAR(255) DEFAULT NULL,
        new_value   VARCHAR(255) DEFAULT NULL,
        notes       TEXT         DEFAULT NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (todo_id)  REFERENCES todos(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES agents(id),
        INDEX idx_todo_id  (todo_id),
        INDEX idx_agent_id (agent_id)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 13. TAGS — labels for calls
    // ------------------------------------------------------------------
    $tables['tags'] = "CREATE TABLE tags (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        color      VARCHAR(20)  DEFAULT '#6366f1',
        created_by INT          NOT NULL COMMENT 'agent_id',
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES agents(id),
        UNIQUE KEY uq_name (name)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 14. CALL_TAGS — many-to-many: calls ↔ tags
    // ------------------------------------------------------------------
    $tables['call_tags'] = "CREATE TABLE call_tags (
        call_id    INT NOT NULL,
        tag_id     INT NOT NULL,
        added_by   INT NOT NULL COMMENT 'agent_id who tagged this call',
        added_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (call_id, tag_id),
        FOREIGN KEY (call_id) REFERENCES call_logs(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id)  REFERENCES tags(id)      ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES agents(id)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 15. FAQS — knowledge base for quick answers during calls
    // ------------------------------------------------------------------
    $tables['faqs'] = "CREATE TABLE faqs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        question    VARCHAR(500) NOT NULL,
        answer      TEXT         NOT NULL,
        category    VARCHAR(100) DEFAULT NULL,
        keywords    VARCHAR(500) DEFAULT NULL COMMENT 'Comma-separated for search',
        usage_count INT          DEFAULT 0,
        created_by  INT          NOT NULL COMMENT 'agent_id',
        updated_by  INT          DEFAULT NULL COMMENT 'agent_id who last edited',
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES agents(id),
        FOREIGN KEY (updated_by) REFERENCES agents(id),
        FULLTEXT KEY ft_faq (question, answer, keywords)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 16. EDIT_HISTORY — field-level audit trail for contacts + calls
    //     Every edit by any agent is logged here
    // ------------------------------------------------------------------
    $tables['edit_history'] = "CREATE TABLE edit_history (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50)  NOT NULL COMMENT 'contacts|call_logs|agents|todos|faqs',
        entity_id   INT          NOT NULL,
        field_name  VARCHAR(100) NOT NULL,
        old_value   TEXT         DEFAULT NULL,
        new_value   TEXT         DEFAULT NULL,
        edited_by   INT          NOT NULL COMMENT 'agent_id who made the change',
        edited_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (edited_by) REFERENCES agents(id),
        INDEX idx_entity  (entity_type, entity_id),
        INDEX idx_agent   (edited_by),
        INDEX idx_time    (edited_at)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 17. ACTIVITY_LOG — system-wide audit: who did what, when
    // ------------------------------------------------------------------
    $tables['activity_log'] = "CREATE TABLE activity_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        agent_id    INT          NOT NULL COMMENT 'Who performed the action',
        action      VARCHAR(100) NOT NULL COMMENT 'e.g. fetch_triggered, note_added, contact_edited',
        entity_type VARCHAR(50)  DEFAULT NULL COMMENT 'call_logs|contacts|todos|agents|pbx_settings',
        entity_id   INT          DEFAULT NULL,
        details     TEXT         DEFAULT NULL COMMENT 'Human-readable description',
        ip_address  VARCHAR(45)  DEFAULT NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id),
        INDEX idx_agent      (agent_id),
        INDEX idx_action     (action),
        INDEX idx_entity     (entity_type, entity_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 18. NOTIFICATIONS — per-agent alerts (task assigned, followup due, etc.)
    // ------------------------------------------------------------------
    $tables['notifications'] = "CREATE TABLE notifications (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        agent_id    INT          NOT NULL COMMENT 'Who receives this notification',
        from_agent  INT          DEFAULT NULL COMMENT 'Who triggered it (NULL = system)',
        title       VARCHAR(255) NOT NULL,
        message     TEXT         DEFAULT NULL,
        type        ENUM('task_assigned','followup_due','note_reply','missed_calls','fetch_done','system') DEFAULT 'system',
        entity_type VARCHAR(50)  DEFAULT NULL,
        entity_id   INT          DEFAULT NULL,
        link        VARCHAR(255) DEFAULT NULL,
        is_read     TINYINT(1)   DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id)   REFERENCES agents(id) ON DELETE CASCADE,
        FOREIGN KEY (from_agent) REFERENCES agents(id) ON DELETE SET NULL,
        INDEX idx_agent_unread (agent_id, is_read),
        INDEX idx_created_at   (created_at)
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // 19. SETTINGS — key-value app configuration
    // ------------------------------------------------------------------
    $tables['settings'] = "CREATE TABLE settings (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT        DEFAULT NULL,
        updated_by  INT          DEFAULT NULL COMMENT 'agent_id who last changed this',
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES agents(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";

    // ------------------------------------------------------------------
    // Execute table creation one by one
    // ------------------------------------------------------------------
    foreach ($tables as $table => $sql) {
        if (!$conn->query($sql)) {
            throw new Exception("Failed to create table <code>$table</code>: " . $conn->error);
        }
        $messages[] = ['success', "Created table: <code>$table</code>"];
    }

    /* ================================================================
       SEED DATA
    ================================================================ */

    // -- Default agent (system account for auto-created records)
    $systemPass = password_hash('ChangeMe@2026', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO agents (id, username, password, full_name, department, status, created_by)
                  VALUES (1, 'system', '$systemPass', 'System', 'System', 'inactive', 1)");

    // -- First real agent: admin agent
    $adminPass = password_hash('admin@2026', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO agents (username, password, full_name, department, status, created_by)
                  VALUES ('admin', '$adminPass', 'Admin Agent', 'Management', 'active', 1)");
    $adminId = $conn->insert_id; // should be 2

    // -- Default PBX Host Setting
    $defaultPbx = 'https://ovijatgroup.pbx.com.bd';
    $conn->query("INSERT INTO settings (setting_key, setting_value, updated_by) VALUES ('pbx_url', '$defaultPbx', $adminId)");

    // -- Sample agents
    $sampleAgents = [
        ['jarin',  'Jarin',   'Executive',   'jarin@2026'],
        ['atoshi',  'Atoshi', 'Executive', 'atoshi@2026'],
        ['bristi',  'Bristi',   'Executive', 'bristi@2026'],
        ['nodi',  'Nodi',   'Executive',   'nodi@2026'],
    ];
    $agentIds = [$adminId];
    $stmt = $conn->prepare("INSERT INTO agents (username, password, full_name, department, status, created_by)
                             VALUES (?, ?, ?, ?, 'active', ?)");
    foreach ($sampleAgents as $a) {
        $hp = password_hash($a[3], PASSWORD_DEFAULT);
        $stmt->bind_param("ssssi", $a[0], $hp, $a[1], $a[2], $adminId);
        $stmt->execute();
        $agentIds[] = $conn->insert_id;
    }
    $messages[] = ['success', 'Created agents: <code>admin/admin@2026</code>, rahim, karim, nadia, sakib / <code>[name]@2026</code>'];

    // -- Agent numbers (extensions + official numbers)
    $agentNumbers = [
        // [agent_id_index, type, number, label, is_primary]
       
        [3, 'extension',   '102',         'Support Ext',    1],
        [3, 'official',    '01896002767', 'Robi', 1],
     [4, 'extension',   '103',         'Support Ext',    1],
        [4, 'official',    '01896002766', 'Robi', 1],
         [5, 'extension',   '104',         'Support Ext',    1],
        [5, 'official',    '01896002746', 'Robi', 1],
         [6, 'extension',   '105',         'Support Ext',    1],
        [6, 'official',    '01896002744', 'Robi', 1],
    ];
    $stmt = $conn->prepare("INSERT INTO agent_numbers (agent_id, number_type, number, label, is_primary, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($agentNumbers as $n) {
        $aid = $agentIds[$n[0] - 1] ?? $adminId;
        $stmt->bind_param("isssii", $aid, $n[1], $n[2], $n[3], $n[4], $adminId);
        $stmt->execute();
    }
    $messages[] = ['info', 'Created agent extensions and numbers'];

    // -- Contact groups
    $conn->query("INSERT INTO contact_groups (name, color, description, created_by) VALUES
        ('VIP',             '#f59e0b', 'High value clients',              $adminId),
        ('Sales',       '#ef4444', 'Prospects requiring quick action', $adminId),
        ('Staff',   '#6366f1', 'Customers needing support',       $adminId),
        ('Followup',  '#10b981', 'Pending sales follow-ups',        $adminId),
        ('Dhaka Staff',  '#8b5cf6', 'Internal company numbers',        $adminId),
        ('Vendors',         '#14b8a6', 'Suppliers and vendors',           $adminId),
        ('Resolved',        '#6b7280', 'Closed / resolved contacts',      $adminId)");
    $messages[] = ['info', 'Created contact groups'];

    // -- Contact types
    $conn->query("INSERT INTO contact_types (name, color, description, created_by) VALUES
        ('Customer',  '#10b981', 'End customers',                  $adminId),
        ('Staff',     '#6366f1', 'Internal company staff',         $adminId),
        ('Vendor',    '#f59e0b', 'Suppliers and service providers', $adminId),
        ('Lead',      '#ec4899', 'Potential customers',            $adminId),
        ('Partner',   '#14b8a6', 'Business partners',              $adminId),
        ('Other',     '#6b7280', 'Uncategorized',                  $adminId)");
    $messages[] = ['info', 'Created contact types'];

    // -- Tags for calls
    $conn->query("INSERT INTO tags (name, color, created_by) VALUES
        ('Billing',    '#ef4444', $adminId),
        ('Sales',      '#10b981', $adminId),
        ('Support',    '#6366f1', $adminId),
        ('Complaint',  '#f59e0b', $adminId),
        ('Inquiry',    '#8b5cf6', $adminId),
        ('Follow-up',  '#14b8a6', $adminId),
        ('Urgent',     '#dc2626', $adminId),
        ('Resolved',   '#6b7280', $adminId)");
    $messages[] = ['info', 'Created call tags'];

    // -- Sample contacts (will be auto-grown by CDR fetch)
    $sampleContacts = [
        ['01912345678', 'Rahman Enterprise', 1, 1, 'Rahman Group', 'external', $adminId],
        ['01812345678', 'Islam Trading Co',  1, 2, 'Islam Corp',   'external', $adminId],
        ['01612345678', 'Karim Steel Ltd',   1, 3, 'Karim Steel',  'external', $adminId],
        ['01512345678', 'Sales Team Int.',   2, 5, 'Ovijat Group', 'internal', $adminId],
        ['01412345678', 'IT Support Desk',   2, 5, 'Ovijat Group', 'internal', $adminId],
        ['01312345678', 'ABC Vendors',       3, 6, 'ABC Corp',     'external', $adminId],
    ];
    $stmt = $conn->prepare("INSERT INTO contacts (phone, name, type_id, group_id, company, scope, created_by) VALUES (?,?,?,?,?,?,?)");
    foreach ($sampleContacts as $c) {
        $stmt->bind_param("ssiissi", $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]);
        $stmt->execute();
    }
    $messages[] = ['info', 'Created sample contacts'];

    // -- FAQs / Knowledge base
    $faqs = [
        ['How to check account balance?',      'Ask customer to dial *111# or use the mobile app. For staff portal: admin.ovijat.com → Billing.',  'Billing',  'balance,account'],
        ['Payment not received by customer?',  'Verify transaction ID. Check payment gateway logs. If >24hrs, escalate to billing@ovijat.com.',      'Payment',  'payment,transaction,not received'],
        ['Delivery is delayed?',               'Check tracking portal. Provide updated ETA. Apologize and create follow-up task.',                    'Delivery', 'delivery,delayed,shipping'],
        ['Product damaged on arrival?',        'Note order ID. Photograph if possible. Initiate replacement. Escalate to QC team.',                   'Product',  'damage,return,replacement'],
        ['How to process a refund?',           'Verify order eligibility. Check return policy (7 days). Process via billing portal. Notify customer.',  'Refund',   'refund,return,money back'],
        ['How to reset a customer password?',  'Verify identity first (DOB + phone). Use admin portal → Users → Reset. Send OTP to registered number.','Account',  'password,reset,login'],
        ['Escalation process?',               'Level 1: Agent. Level 2: Team Lead (ext 200). Level 3: Manager (ext 300). Log each step.',              'Process',  'escalate,escalation,manager'],
        ['Transfer a call internally?',        'Press Transfer button → dial extension → announce → confirm → complete transfer.',                      'Process',  'transfer,extension,internal'],
    ];
    $stmt = $conn->prepare("INSERT INTO faqs (question, answer, category, keywords, created_by) VALUES (?,?,?,?,?)");
    foreach ($faqs as $f) {
        $stmt->bind_param("ssssi", $f[0], $f[1], $f[2], $f[3], $adminId);
        $stmt->execute();
    }
    $messages[] = ['info', 'Created knowledge base FAQs'];

    // -- Default app settings
    $settingsData = [
        ['company_name',         'Ovijat Group'],
        ['company_phone',        '+8809638000000'],
        ['timezone',             'Asia/Dhaka'],
        ['date_format',          'd M Y'],
        ['time_format',          'h:i A'],
        ['calls_per_page',       '50'],
        ['missed_alert_enabled', '1'],
        ['auto_create_contact',  '1'],
        ['recording_proxy',      '1'],
        ['app_version',          '1.0.0'],
    ];
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)");
    foreach ($settingsData as $s) {
        $stmt->bind_param("ss", $s[0], $s[1]);
        $stmt->execute();
    }
    $messages[] = ['info', 'Created default app settings'];

    // -- Initial activity log entry
    $conn->query("INSERT INTO activity_log (agent_id, action, entity_type, details, ip_address)
                  VALUES ($adminId, 'setup_completed', 'system', 'Database setup and seed completed', '127.0.0.1')");

    $conn->close();
    $messages[] = ['success', '<strong>Setup complete!</strong> Delete <code>setup.php</code> before going live.'];
    $success = true;

} catch (Exception $e) {
    $messages[] = ['danger', '<strong>Error:</strong> ' . $e->getMessage()];
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ovijat Call Center — Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg:      #0f1117;
            --card:    #1a1d27;
            --border:  #2a2d3a;
            --accent:  #6366f1;
            --text:    #e2e8f0;
            --muted:   #8892a4;
        }
        body         { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
        .card        { background: var(--card); border: 1px solid var(--border); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border-radius: 12px 12px 0 0 !important; padding: 1.4rem 1.6rem; }
        .setup-title { font-size: 1.25rem; font-weight: 700; letter-spacing: .3px; }
        .alert       { border-radius: 8px; font-size: .875rem; padding: .55rem 1rem; margin-bottom: .4rem; }
        .alert-success { background: #052e16; border-color: #166534; color: #bbf7d0; }
        .alert-info    { background: #0c1a3a; border-color: #1e40af; color: #bfdbfe; }
        .alert-danger  { background: #2d0f0f; border-color: #991b1b; color: #fca5a5; }
        .alert-warning { background: #2d1b00; border-color: #92400e; color: #fcd34d; }
        code           { background: rgba(255,255,255,.1); padding: 1px 5px; border-radius: 4px; font-size: .85em; }
        .step-icon     { width: 22px; display: inline-block; text-align: center; }
        .btn-go        { background: var(--accent); border: none; padding: .7rem 2rem; border-radius: 8px; font-weight: 600; font-size: 1rem; color: #fff; transition: opacity .2s; }
        .btn-go:hover  { opacity: .85; color: #fff; }
        .schema-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: .35rem; }
        @media(max-width:600px) { .schema-grid { grid-template-columns: 1fr; } }
        .schema-item   { background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.25); border-radius: 6px; padding: .35rem .7rem; font-size: .8rem; font-family: monospace; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            <div class="card shadow-lg mb-4">
                <div class="card-header text-white">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-headset fa-2x"></i>
                        <div>
                            <div class="setup-title">Ovijat Call Center</div>
                            <div class="opacity-75 small">Database Setup &amp; Initialization</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">

                    <?php foreach ($messages as $m): ?>
                        <div class="alert alert-<?= $m[0] ?>">
                            <span class="step-icon">
                                <?php if ($m[0] === 'success'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php elseif ($m[0] === 'danger'): ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php else: ?>
                                    <i class="fas fa-circle-info text-info"></i>
                                <?php endif; ?>
                            </span>
                            <?= $m[1] ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($success ?? false): ?>
                        <hr style="border-color: var(--border)">

                        <h6 class="text-muted mb-2 mt-3" style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase">
                            <i class="fas fa-table me-1"></i> Tables Created (19)
                        </h6>
                        <div class="schema-grid mb-4">
                            <?php foreach (array_keys($tables) as $t): ?>
                                <div class="schema-item"><i class="fas fa-table me-1 opacity-50"></i><?= $t ?></div>
                            <?php endforeach; ?>
                        </div>

                        <h6 class="text-muted mb-2" style="font-size:.75rem;letter-spacing:.08em;text-transform:uppercase">
                            <i class="fas fa-key me-1"></i> Default Credentials
                        </h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm" style="color:var(--text);font-size:.85rem">
                                <thead style="color:var(--muted)">
                                    <tr><th>Username</th><th>Password</th><th>Department</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td><code>admin</code></td><td><code>admin@2026</code></td><td>Management</td></tr>
                                    <tr><td><code>rahim</code></td><td><code>rahim@2026</code></td><td>Sales</td></tr>
                                    <tr><td><code>karim</code></td><td><code>karim@2026</code></td><td>Support</td></tr>
                                    <tr><td><code>nadia</code></td><td><code>nadia@2026</code></td><td>Support</td></tr>
                                    <tr><td><code>sakib</code></td><td><code>sakib@2026</code></td><td>Sales</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mb-3">
                            <a href="login.php" class="btn btn-go">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                            </a>
                        </div>

                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-triangle-exclamation me-2"></i>
                            <strong>Security:</strong> Delete or restrict access to <code>setup.php</code> immediately after setup.
                            Every agent action in this system is fully audited with identity, timestamp, and IP.
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="text-center" style="color:var(--muted);font-size:.8rem">
                callcenter_db &bull; <?= count($tables ?? []) ?> tables &bull; Core PHP &bull; <?= date('Y') ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
