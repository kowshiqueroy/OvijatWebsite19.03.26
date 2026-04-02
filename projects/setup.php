<?php
/**
 * Setup / Upgrade / Admin wizard.
 * - No config.php  → install form
 * - config.php exists → management dashboard (tables, reset, demo data, features)
 */

$configExists = file_exists(__DIR__ . '/config.php');
$section      = $_GET['section'] ?? 'tables';
$msg          = '';
$msgType      = 'success';
$pdo          = null;

if ($configExists) {
    require_once __DIR__ . '/config.php';
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        die('<p style="font-family:system-ui;color:red;padding:2rem">DB connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
    }
}

// ── Handle POST actions ──────────────────────────────────────
$tableResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'sync_tables') {
        $tableResults = runTables($pdo);
        $created = count(array_filter($tableResults, fn($v) => $v === 'created'));
        $errors  = count(array_filter($tableResults, fn($v) => str_starts_with($v, 'error:')));
        $msg     = $errors ? "$errors table(s) had errors." : ($created ? "$created table(s) created successfully." : 'All tables already up to date.');
        $msgType = $errors ? 'error' : 'success';
        $section = 'tables';

    } elseif ($action === 'reset') {
        if (($_POST['confirm_reset'] ?? '') === 'RESET') {
            doReset($pdo);
            $msg     = 'Database reset. All data has been wiped.';
            $msgType = 'warning';
        } else {
            $msg     = 'Type RESET in the confirmation box to proceed.';
            $msgType = 'error';
        }
        $section = 'reset';

    } elseif ($action === 'demo') {
        [$inserted, $skipped] = insertDemoData($pdo);
        $msg     = "Demo data added: $inserted records inserted, $skipped skipped (already present).";
        $msgType = 'success';
        $section = 'demo';
    }
}

// Auto-sync on first load of tables section
if ($configExists && $section === 'tables' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $tableResults = runTables($pdo);
}

// ── First-install (no config.php) ───────────────────────────
$installError   = '';
$installSuccess = false;
$installResults = [];
if (!$configExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost    = trim($_POST['db_host']    ?? 'localhost');
    $dbName    = trim($_POST['db_name']    ?? '');
    $dbUser    = trim($_POST['db_user']    ?? '');
    $dbPass    =      $_POST['db_pass']    ?? '';
    $appName   = trim($_POST['app_name']   ?? 'SohojWeb Projects');
    $baseUrl   = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass =      $_POST['admin_pass'] ?? '';
    $adminName = trim($_POST['admin_name'] ?? '');

    if (!$dbName || !$dbUser || !$adminUser || !$adminPass || !$adminName) {
        $installError = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            $installResults = runTables($pdo);
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, 'admin', 1)")
                ->execute([$adminUser, $hash, $adminName]);
            $cfg = "<?php\n"
                 . "define('DB_HOST', "  . var_export($dbHost,  true) . ");\n"
                 . "define('DB_NAME', "  . var_export($dbName,  true) . ");\n"
                 . "define('DB_USER', "  . var_export($dbUser,  true) . ");\n"
                 . "define('DB_PASS', "  . var_export($dbPass,  true) . ");\n"
                 . "define('APP_NAME', " . var_export($appName, true) . ");\n"
                 . "define('BASE_URL', " . var_export($baseUrl, true) . ");\n";
            file_put_contents(__DIR__ . '/config.php', $cfg);
            $installSuccess = true;
        } catch (PDOException $e) {
            $installError = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ════════════════════════════════════════════════════════════
// TABLE DEFINITIONS
// ════════════════════════════════════════════════════════════
function getTables(): array {
    return [
        'users' => "CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)  UNIQUE NOT NULL,
    email       VARCHAR(180) UNIQUE NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name   VARCHAR(120) NOT NULL,
    avatar_url  VARCHAR(500) NULL,
    role        ENUM('admin','member') NOT NULL DEFAULT 'member',
    is_active   TINYINT      NOT NULL DEFAULT 1,
    last_login  DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

        'projects' => "CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(160) NOT NULL,
    slug        VARCHAR(160) UNIQUE NOT NULL,
    description TEXT,
    status      ENUM('planning','active','on_hold','completed','archived') NOT NULL DEFAULT 'planning',
    client_name VARCHAR(160),
    start_date  DATE,
    due_date    DATE,
    tools_used  JSON,
    ai_used     JSON,
    tech_notes  TEXT,
    budget      DECIMAL(14,2) NULL,
    deleted_at  DATETIME NULL,
    created_by  INT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proj_status     (status),
    KEY idx_proj_deleted_at (deleted_at),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB",

        'project_members' => "CREATE TABLE IF NOT EXISTS project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('lead','member') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'milestones' => "CREATE TABLE IF NOT EXISTS milestones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_date    DATE,
    status      ENUM('open','completed') NOT NULL DEFAULT 'open',
    created_by  INT NOT NULL,
    created_at  DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB",

        'tasks' => "CREATE TABLE IF NOT EXISTS tasks (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    project_id        INT NOT NULL,
    parent_task_id    INT NULL,
    blocked_by_task_id INT NULL,
    milestone_id      INT NULL,
    title             VARCHAR(255) NOT NULL,
    description       TEXT,
    status            ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
    priority          ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    start_date        DATE,
    due_date          DATE,
    estimated_hours   DECIMAL(6,2) NULL CHECK (estimated_hours IS NULL OR estimated_hours > 0),
    actual_hours      DECIMAL(6,2) NULL CHECK (actual_hours IS NULL OR actual_hours > 0),
    deleted_at        DATETIME NULL,
    created_by        INT NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tasks_status     (status),
    KEY idx_tasks_due_date   (due_date),
    KEY idx_tasks_deleted_at (deleted_at),
    KEY idx_tasks_created_by (created_by),
    FOREIGN KEY (project_id)         REFERENCES projects(id)  ON DELETE CASCADE,
    FOREIGN KEY (parent_task_id)     REFERENCES tasks(id)     ON DELETE SET NULL,
    FOREIGN KEY (blocked_by_task_id) REFERENCES tasks(id)     ON DELETE SET NULL,
    FOREIGN KEY (milestone_id)       REFERENCES milestones(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)         REFERENCES users(id)
) ENGINE=InnoDB",

        'task_assignees' => "CREATE TABLE IF NOT EXISTS task_assignees (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    UNIQUE KEY uq_ta (task_id, user_id),
    KEY idx_ta_task_id (task_id),
    KEY idx_ta_user_id (user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'task_comments' => "CREATE TABLE IF NOT EXISTS task_comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT NOT NULL,
    user_id    INT NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tc_task_id (task_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'task_attachments' => "CREATE TABLE IF NOT EXISTS task_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'task_time_logs' => "CREATE TABLE IF NOT EXISTS task_time_logs (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    task_id   INT NOT NULL,
    user_id   INT NOT NULL,
    hours     DECIMAL(6,2) NOT NULL CHECK (hours > 0),
    note      VARCHAR(255),
    logged_at DATE NOT NULL,
    KEY idx_ttl_task_id  (task_id),
    KEY idx_ttl_user_id  (user_id),
    KEY idx_ttl_logged_at (logged_at),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'meetings' => "CREATE TABLE IF NOT EXISTS meetings (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NULL,
    title            VARCHAR(255) NOT NULL,
    agenda           TEXT,
    meeting_date     DATETIME NOT NULL,
    duration_minutes INT NULL CHECK (duration_minutes IS NULL OR duration_minutes > 0),
    location_or_link VARCHAR(500),
    status           ENUM('scheduled','done','cancelled') NOT NULL DEFAULT 'scheduled',
    recurrence       ENUM('none','daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'none',
    notes            TEXT,
    created_by       INT NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_meetings_date    (meeting_date),
    KEY idx_meetings_status  (status),
    KEY idx_meetings_project (project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB",

        'meeting_attendees' => "CREATE TABLE IF NOT EXISTS meeting_attendees (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    user_id    INT NOT NULL,
    rsvp       ENUM('pending','yes','no','maybe') NOT NULL DEFAULT 'pending',
    UNIQUE KEY uq_ma (meeting_id, user_id),
    KEY idx_ma_meeting_id (meeting_id),
    KEY idx_ma_user_id    (user_id),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB",

        'meeting_action_items' => "CREATE TABLE IF NOT EXISTS meeting_action_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id  INT NOT NULL,
    task_id     INT NULL,
    description TEXT NOT NULL,
    assigned_to INT NULL,
    due_date    DATE NULL,
    is_done     TINYINT NOT NULL DEFAULT 0,
    priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    created_at  DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id)  REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id)     REFERENCES tasks(id)    ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB",

        'updates' => "CREATE TABLE IF NOT EXISTS updates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NULL,
    user_id    INT NOT NULL,
    type       ENUM('task','meeting','project','general') NOT NULL DEFAULT 'general',
    message    TEXT NOT NULL,
    is_pinned  TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_updates_project_id (project_id),
    KEY idx_updates_created_at (created_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB",

        'update_reads' => "CREATE TABLE IF NOT EXISTS update_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    update_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (update_id, user_id),
    FOREIGN KEY (update_id) REFERENCES updates(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'worksheet_chat' => "CREATE TABLE IF NOT EXISTS worksheet_chat (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    project_id   INT NOT NULL,
    user_id      INT NOT NULL,
    recipient_id INT NULL,
    parent_id    INT NULL,
    body         TEXT NOT NULL,
    likes_count  INT NOT NULL DEFAULT 0,
    is_pinned    TINYINT NOT NULL DEFAULT 0,
    is_edited    TINYINT NOT NULL DEFAULT 0,
    edited_at    DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wc_project_id (project_id),
    KEY idx_wc_user_id    (user_id),
    KEY idx_wc_created_at (created_at),
    FOREIGN KEY (project_id)   REFERENCES projects(id)       ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id)          ON DELETE SET NULL,
    FOREIGN KEY (parent_id)    REFERENCES worksheet_chat(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'chat_likes' => "CREATE TABLE IF NOT EXISTS chat_likes (
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (chat_id, user_id),
    FOREIGN KEY (chat_id) REFERENCES worksheet_chat(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'chat_reactions' => "CREATE TABLE IF NOT EXISTS chat_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reaction (chat_id, user_id, emoji),
    FOREIGN KEY (chat_id) REFERENCES worksheet_chat(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    type                ENUM('mention','task_assigned','comment','meeting') NOT NULL DEFAULT 'mention',
    message             TEXT NOT NULL,
    link                VARCHAR(500),
    related_entity_type VARCHAR(40) NULL,
    related_entity_id   INT NULL,
    is_read             TINYINT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user_id (user_id),
    KEY idx_notif_is_read (is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",
    ];
}

// ════════════════════════════════════════════════════════════
// RUN TABLES — returns status per table
// ════════════════════════════════════════════════════════════
function runTables(PDO $pdo): array {
    $results = [];
    foreach (getTables() as $name => $sql) {
        try {
            $pdo->query("SELECT 1 FROM `$name` LIMIT 1");
            $existed = true;
        } catch (PDOException $e) {
            $existed = false;
        }
        try {
            $pdo->exec($sql);
            $results[$name] = $existed ? 'exists' : 'created';
        } catch (PDOException $e) {
            $results[$name] = 'error:' . $e->getMessage();
        }
    }
    return $results;
}

// ════════════════════════════════════════════════════════════
// RESET — wipe all data, keep structure
// ════════════════════════════════════════════════════════════
function doReset(PDO $pdo): void {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (array_reverse(array_keys(getTables())) as $table) {
        try { $pdo->exec("TRUNCATE TABLE `$table`"); } catch (PDOException $e) {}
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

// ════════════════════════════════════════════════════════════
// DEMO DATA
// ════════════════════════════════════════════════════════════
function insertDemoData(PDO $pdo): array {
    $inserted = 0;
    $skipped  = 0;

    // Helper: insert and return last ID, skip if unique error
    $ins = function(string $table, array $data) use ($pdo, &$inserted, &$skipped): int {
        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        try {
            $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($phs)")->execute(array_values($data));
            $inserted++;
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            $skipped++;
            // Return existing ID for unique-key conflicts on username/slug
            if (str_contains($table, 'user') && isset($data['username'])) {
                $r = $pdo->prepare("SELECT id FROM users WHERE username=?")->execute([$data['username']]);
                $row = $pdo->query("SELECT id FROM users WHERE username=" . $pdo->quote($data['username']))->fetch();
                return (int)($row['id'] ?? 0);
            }
            return 0;
        }
    };

    // ── Admin user (get existing id=1 if present) ────────────
    $adminRow = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch();
    $adminId  = $adminRow ? (int)$adminRow['id'] : 1;

    // ── Demo members ─────────────────────────────────────────
    $alice   = $ins('users', ['username'=>'alice',   'password_hash'=>password_hash('demo1234', PASSWORD_DEFAULT), 'full_name'=>'Alice Rahman',  'role'=>'member', 'is_active'=>1]);
    $bob     = $ins('users', ['username'=>'bob',     'password_hash'=>password_hash('demo1234', PASSWORD_DEFAULT), 'full_name'=>'Bob Hossain',   'role'=>'member', 'is_active'=>1]);
    $charlie = $ins('users', ['username'=>'charlie', 'password_hash'=>password_hash('demo1234', PASSWORD_DEFAULT), 'full_name'=>'Charlie Ahmed', 'role'=>'member', 'is_active'=>1]);

    // Fallback: re-fetch if insert was skipped
    $fetchUser = fn($u) => (int)($pdo->query("SELECT id FROM users WHERE username=" . $pdo->quote($u))->fetchColumn() ?: 0);
    if (!$alice)   $alice   = $fetchUser('alice');
    if (!$bob)     $bob     = $fetchUser('bob');
    if (!$charlie) $charlie = $fetchUser('charlie');

    // ── Project 1: Website Redesign ───────────────────────────
    $p1 = $ins('projects', [
        'name' => 'Website Redesign', 'slug' => 'website-redesign',
        'description' => 'Full redesign of the company website with modern UI/UX.',
        'status' => 'active', 'client_name' => 'Acme Corp',
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'due_date'   => date('Y-m-d', strtotime('+30 days')),
        'tech_notes' => 'Stack: React + Tailwind + Laravel API', 'created_by' => $adminId,
    ]);
    if (!$p1) { $r = $pdo->query("SELECT id FROM projects WHERE slug='website-redesign'")->fetch(); $p1=(int)($r['id']??0); }

    // ── Project 2: Mobile App ─────────────────────────────────
    $p2 = $ins('projects', [
        'name' => 'Mobile App', 'slug' => 'mobile-app',
        'description' => 'Cross-platform mobile application for iOS and Android.',
        'status' => 'planning', 'client_name' => 'Beta Labs',
        'start_date' => date('Y-m-d', strtotime('-10 days')),
        'due_date'   => date('Y-m-d', strtotime('+90 days')),
        'tech_notes' => 'Stack: Flutter + Firebase', 'created_by' => $adminId,
    ]);
    if (!$p2) { $r = $pdo->query("SELECT id FROM projects WHERE slug='mobile-app'")->fetch(); $p2=(int)($r['id']??0); }

    if (!$p1 || !$p2) return [$inserted, $skipped]; // bail if projects couldn't be created

    // ── Members ───────────────────────────────────────────────
    foreach ([$adminId, $alice, $bob] as $uid) {
        $ins('project_members', ['project_id'=>$p1, 'user_id'=>$uid, 'role'=> $uid===$adminId?'lead':'member']);
    }
    foreach ([$adminId, $bob, $charlie] as $uid) {
        $ins('project_members', ['project_id'=>$p2, 'user_id'=>$uid, 'role'=> $uid===$adminId?'lead':'member']);
    }

    // ── Tasks: Project 1 ─────────────────────────────────────
    $p1tasks = [
        ['Design homepage mockup',         'done',        'high',   -20, $alice],
        ['Set up project repository',       'done',        'medium', -18, $adminId],
        ['Build navigation component',      'done',        'medium', -15, $bob],
        ['Hero section implementation',     'in_progress', 'high',   -5,  $alice],
        ['Contact form with validation',    'in_progress', 'medium',  3,  $bob],
        ['SEO meta tags setup',             'todo',        'low',     7,  $adminId],
        ['Mobile responsiveness pass',      'todo',        'high',   12,  $alice],
        ['Performance audit & optimise',    'review',      'medium', 10,  $bob],
        ['Accessibility (WCAG) review',     'todo',        'medium', 20,  $adminId],
        ['Deploy to staging environment',   'todo',        'high',   28,  $bob],
    ];
    $p1tids = [];
    foreach ($p1tasks as [$title, $status, $prio, $daysOff, $assignee]) {
        $due = date('Y-m-d', strtotime("$daysOff days"));
        $tid = $ins('tasks', ['project_id'=>$p1,'title'=>$title,'status'=>$status,'priority'=>$prio,'due_date'=>$due,'created_by'=>$adminId]);
        if ($tid) { $ins('task_assignees', ['task_id'=>$tid,'user_id'=>$assignee]); $p1tids[] = $tid; }
    }

    // ── Tasks: Project 2 ─────────────────────────────────────
    $p2tasks = [
        ['Requirements gathering',     'done',        'high',   -8,  $adminId],
        ['UI/UX wireframes',            'done',        'high',   -5,  $charlie],
        ['Set up Flutter project',      'in_progress', 'medium',  5,  $bob],
        ['Authentication screens',      'in_progress', 'high',   10,  $charlie],
        ['API integration layer',       'todo',        'high',   20,  $bob],
        ['Push notifications',          'todo',        'medium', 30,  $charlie],
        ['Offline mode caching',        'todo',        'medium', 45,  $bob],
        ['App Store submission prep',   'todo',        'low',    85,  $adminId],
    ];
    $p2tids = [];
    foreach ($p2tasks as [$title, $status, $prio, $daysOff, $assignee]) {
        $due = date('Y-m-d', strtotime("$daysOff days"));
        $tid = $ins('tasks', ['project_id'=>$p2,'title'=>$title,'status'=>$status,'priority'=>$prio,'due_date'=>$due,'created_by'=>$adminId]);
        if ($tid) { $ins('task_assignees', ['task_id'=>$tid,'user_id'=>$assignee]); $p2tids[] = $tid; }
    }

    // ── Subtasks for first task of each project ───────────────
    if (!empty($p1tids)) {
        $parentId = $p1tids[3]; // Hero section
        foreach (['Write copy','Create images','Implement HTML','Apply animations'] as $sub) {
            $ins('tasks', ['project_id'=>$p1,'parent_task_id'=>$parentId,'title'=>$sub,'status'=>'todo','priority'=>'medium','created_by'=>$adminId]);
        }
    }

    // ── Task comments ─────────────────────────────────────────
    if (!empty($p1tids)) {
        $ins('task_comments', ['task_id'=>$p1tids[0],'user_id'=>$alice,  'body'=>'Mockup approved by client. Moving to dev.', 'created_at'=>date('Y-m-d H:i:s', strtotime('-19 days'))]);
        $ins('task_comments', ['task_id'=>$p1tids[0],'user_id'=>$adminId,'body'=>'Great work! Let\'s use the dark variant.',     'created_at'=>date('Y-m-d H:i:s', strtotime('-18 days'))]);
        $ins('task_comments', ['task_id'=>$p1tids[3],'user_id'=>$bob,    'body'=>'@alice need the final copy for this section.','created_at'=>date('Y-m-d H:i:s', strtotime('-3 days'))]);
    }

    // ── Time logs ─────────────────────────────────────────────
    $tlogs = [
        [$p1tids[0] ?? 0, $alice,   4.0, 'Initial mockup work',   -20],
        [$p1tids[0] ?? 0, $alice,   2.5, 'Revisions after review',-18],
        [$p1tids[1] ?? 0, $adminId, 1.5, 'Repo setup + CI',       -18],
        [$p1tids[2] ?? 0, $bob,     3.0, 'Nav component',         -15],
        [$p1tids[3] ?? 0, $alice,   5.0, 'Hero layout',           -5],
        [$p1tids[4] ?? 0, $bob,     2.0, 'Form validation',       -2],
        [$p2tids[0] ?? 0, $adminId, 3.5, 'Requirements workshop', -8],
        [$p2tids[1] ?? 0, $charlie, 6.0, 'Wireframe set',         -5],
        [$p2tids[2] ?? 0, $bob,     2.0, 'Project scaffold',      -1],
    ];
    foreach ($tlogs as [$tid, $uid, $hrs, $note, $dOff]) {
        if ($tid) $ins('task_time_logs', ['task_id'=>$tid,'user_id'=>$uid,'hours'=>$hrs,'note'=>$note,'logged_at'=>date('Y-m-d', strtotime("$dOff days"))]);
    }

    // ── Meetings ──────────────────────────────────────────────
    $m1 = $ins('meetings', [
        'project_id'=>$p1,'title'=>'Website Sprint Planning','agenda'=>'Plan next 2-week sprint and assign tasks.',
        'meeting_date'=>date('Y-m-d 10:00:00', strtotime('+2 days')),'duration_minutes'=>60,
        'location_or_link'=>'https://meet.google.com/demo-link','status'=>'scheduled','created_by'=>$adminId,
    ]);
    $m2 = $ins('meetings', [
        'project_id'=>$p1,'title'=>'Design Review','agenda'=>'Review hero section with client.',
        'meeting_date'=>date('Y-m-d 14:00:00', strtotime('-7 days')),'duration_minutes'=>45,
        'notes'=>'Client loved the dark theme. Requested larger CTA button.','status'=>'done','created_by'=>$alice,
    ]);
    $m3 = $ins('meetings', [
        'project_id'=>$p2,'title'=>'Mobile App Kickoff','agenda'=>"1. Project scope\n2. Tech stack\n3. Timeline",
        'meeting_date'=>date('Y-m-d 09:30:00', strtotime('+5 days')),'duration_minutes'=>90,
        'location_or_link'=>'Conference Room B','status'=>'scheduled','created_by'=>$adminId,
    ]);

    // Meeting attendees + RSVP
    if ($m1) { foreach ([$adminId=>'yes', $alice=>'yes', $bob=>'pending'] as $uid=>$r) $ins('meeting_attendees', ['meeting_id'=>$m1,'user_id'=>$uid,'rsvp'=>$r]); }
    if ($m2) { foreach ([$adminId=>'yes', $alice=>'yes', $bob=>'yes'] as $uid=>$r) $ins('meeting_attendees', ['meeting_id'=>$m2,'user_id'=>$uid,'rsvp'=>$r]); }
    if ($m3) { foreach ([$adminId=>'yes', $bob=>'pending', $charlie=>'yes'] as $uid=>$r) $ins('meeting_attendees', ['meeting_id'=>$m3,'user_id'=>$uid,'rsvp'=>$r]); }

    // Action items
    if ($m2) {
        $ins('meeting_action_items', ['meeting_id'=>$m2,'description'=>'Increase CTA button size to 56px','assigned_to'=>$alice,'due_date'=>date('Y-m-d', strtotime('+3 days')),'is_done'=>0]);
        $ins('meeting_action_items', ['meeting_id'=>$m2,'description'=>'Update brand colours in design system','assigned_to'=>$bob,'due_date'=>date('Y-m-d', strtotime('+5 days')),'is_done'=>0]);
    }

    // ── Worksheet chat ────────────────────────────────────────
    $chatMsgs = [
        [$p1, $adminId, 'Hey team — sprint planning is on Thursday at 10 AM.',              null, date('Y-m-d H:i:s', strtotime('-5 days'))],
        [$p1, $alice,   'Got it! I\'ll have the hero mockup ready by then.',                null, date('Y-m-d H:i:s', strtotime('-5 days +1 hour'))],
        [$p1, $bob,     '@alice can you share the Figma link when it\'s done?',             null, date('Y-m-d H:i:s', strtotime('-5 days +2 hours'))],
        [$p1, $alice,   'Sure! I\'ll drop it in the files panel.',                          null, date('Y-m-d H:i:s', strtotime('-4 days'))],
        [$p1, $adminId, 'Client confirmed — they love the dark theme option.',              null, date('Y-m-d H:i:s', strtotime('-3 days'))],
        [$p2, $adminId, 'Kickoff meeting confirmed for next Monday. See the Meetings tab.', null, date('Y-m-d H:i:s', strtotime('-2 days'))],
        [$p2, $charlie, 'Wireframes are 80% done, should be ready by end of week.',        null, date('Y-m-d H:i:s', strtotime('-1 day'))],
        [$p2, $bob,     '@charlie can we sync tomorrow on the auth flow?',                  null, date('Y-m-d H:i:s', strtotime('-12 hours'))],
    ];
    foreach ($chatMsgs as [$pid, $uid, $body, $rid, $ts]) {
        $ins('worksheet_chat', ['project_id'=>$pid,'user_id'=>$uid,'body'=>$body,'recipient_id'=>$rid,'created_at'=>$ts]);
    }

    // ── Activity feed (updates) ───────────────────────────────
    $updates = [
        [$p1, $adminId, 'task',    'Created task "Design homepage mockup".',         date('Y-m-d H:i:s', strtotime('-20 days'))],
        [$p1, $alice,   'task',    'Completed task "Design homepage mockup".',        date('Y-m-d H:i:s', strtotime('-19 days'))],
        [$p1, $adminId, 'meeting', 'Scheduled meeting "Design Review".',              date('Y-m-d H:i:s', strtotime('-8 days'))],
        [$p1, $bob,     'task',    'Started "Build navigation component".',           date('Y-m-d H:i:s', strtotime('-6 days'))],
        [$p2, $adminId, 'project', 'Project "Mobile App" created.',                  date('Y-m-d H:i:s', strtotime('-10 days'))],
        [$p2, $charlie, 'task',    'Completed "Requirements gathering".',             date('Y-m-d H:i:s', strtotime('-7 days'))],
        [$p2, $adminId, 'meeting', 'Scheduled meeting "Mobile App Kickoff".',        date('Y-m-d H:i:s', strtotime('-2 days'))],
    ];
    foreach ($updates as [$pid, $uid, $type, $msg, $ts]) {
        $ins('updates', ['project_id'=>$pid,'user_id'=>$uid,'type'=>$type,'message'=>$msg,'created_at'=>$ts]);
    }

    // ── Notifications for admin ───────────────────────────────
    $ins('notifications', ['user_id'=>$adminId,'type'=>'mention',       'message'=>'@alice mentioned you in a comment.', 'is_read'=>0,'created_at'=>date('Y-m-d H:i:s', strtotime('-3 days'))]);
    $ins('notifications', ['user_id'=>$adminId,'type'=>'task_assigned', 'message'=>'You were assigned "SEO meta tags setup".','is_read'=>0,'created_at'=>date('Y-m-d H:i:s', strtotime('-1 day'))]);
    if ($alice) $ins('notifications', ['user_id'=>$alice,'type'=>'mention','message'=>'@bob mentioned you in Team Chat.','is_read'=>0,'created_at'=>date('Y-m-d H:i:s', strtotime('-4 days'))]);

    return [$inserted, $skipped];
}

// ════════════════════════════════════════════════════════════
// HTML
// ════════════════════════════════════════════════════════════
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SohojWeb Projects — Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#F4F6F9;min-height:100vh;padding:32px 16px}
.wrap{max-width:700px;margin:0 auto}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.brand{font-size:1.25rem;font-weight:700;color:#1A1D23}
.brand span{color:#4F6BED}
.back-link{font-size:.8125rem;color:#4F6BED;text-decoration:none}
.back-link:hover{text-decoration:underline}
/* Nav tabs */
.tabs{display:flex;gap:2px;background:#E5E7EB;border-radius:8px;padding:3px;margin-bottom:24px}
.tab{flex:1;padding:7px 12px;background:none;border:none;border-radius:6px;font-size:.8125rem;font-weight:500;color:#6B7280;cursor:pointer;text-decoration:none;text-align:center;transition:.15s}
.tab:hover{color:#374151;background:rgba(255,255,255,.5)}
.tab.active{background:#fff;color:#1A1D23;box-shadow:0 1px 3px rgba(0,0,0,.1)}
/* Card */
.card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:28px;margin-bottom:20px}
.card-title{font-size:.9rem;font-weight:700;color:#1A1D23;margin-bottom:16px;display:flex;align-items:center;gap:8px}
/* Form */
label{display:block;font-size:.8rem;font-weight:500;color:#374151;margin-bottom:4px;margin-top:14px}
input[type=text],input[type=password],input[type=url]{width:100%;padding:8px 11px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;outline:none;transition:.2s}
input:focus{border-color:#4F6BED;box-shadow:0 0 0 3px rgba(79,107,237,.1)}
.section-sep{margin-top:20px;padding-top:18px;border-top:1px solid #E5E7EB}
.section-label{font-size:.72rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:6px;font-size:.875rem;font-weight:600;cursor:pointer;transition:.15s;text-decoration:none}
.btn-primary{background:#4F6BED;color:#fff} .btn-primary:hover{background:#3A56D4}
.btn-danger{background:#EF4444;color:#fff}  .btn-danger:hover{background:#DC2626}
.btn-ghost{background:#F3F4F6;color:#374151}.btn-ghost:hover{background:#E5E7EB}
.btn-full{width:100%;justify-content:center;margin-top:20px;padding:10px}
/* Alerts */
.alert{padding:12px 16px;border-radius:7px;font-size:.8125rem;margin-bottom:18px;border-left:3px solid}
.alert-success{background:#F0FDF4;color:#15803D;border-color:#22C55E}
.alert-error{background:#FEF2F2;color:#DC2626;border-color:#EF4444}
.alert-warning{background:#FFFBEB;color:#92400E;border-color:#F59E0B}
/* Table list */
.tbl-grid{display:flex;flex-direction:column;gap:5px;margin-top:4px}
.tbl-row{display:flex;align-items:center;gap:10px;padding:7px 12px;border-radius:6px;font-size:.8rem;border:1px solid #E5E7EB;background:#F9FAFB}
.tbl-row.created{border-color:#BBF7D0;background:#F0FDF4}
.tbl-row.error{border-color:#FECACA;background:#FEF2F2}
.tbl-icon{font-size:.95rem;width:20px;text-align:center;flex-shrink:0}
.tbl-name{flex:1;font-family:monospace;color:#374151}
.tbl-status{font-size:.72rem;font-weight:600;white-space:nowrap}
.tbl-row.created .tbl-status{color:#15803D}
.tbl-row.exists  .tbl-status{color:#9CA3AF}
.tbl-row.error   .tbl-status{color:#DC2626}
.summary{display:flex;gap:16px;font-size:.78rem;color:#6B7280;margin-top:10px;flex-wrap:wrap}
.summary strong{color:#374151}
/* Feature grid */
.feat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.feat-item{display:flex;align-items:flex-start;gap:10px;padding:12px;background:#F9FAFB;border-radius:8px;border:1px solid #E5E7EB}
.feat-icon{font-size:1.25rem;flex-shrink:0;line-height:1}
.feat-title{font-size:.8125rem;font-weight:600;color:#1A1D23;margin-bottom:2px}
.feat-desc{font-size:.75rem;color:#6B7280;line-height:1.4}
/* Demo user table */
.demo-table{width:100%;border-collapse:collapse;font-size:.8125rem;margin-top:10px}
.demo-table th{text-align:left;padding:6px 10px;font-size:.72rem;font-weight:700;color:#6B7280;text-transform:uppercase;border-bottom:2px solid #E5E7EB}
.demo-table td{padding:8px 10px;border-bottom:1px solid #F3F4F6}
.demo-table tr:last-child td{border-bottom:none}
/* Danger zone */
.danger-zone{border:1px solid #FECACA;border-radius:8px;padding:20px}
.danger-title{font-size:.8125rem;font-weight:700;color:#DC2626;margin-bottom:8px}
.confirm-input{width:100%;padding:8px 11px;border:2px solid #FECACA;border-radius:6px;font-size:.875rem;margin:10px 0;outline:none;transition:.2s}
.confirm-input:focus{border-color:#EF4444}
@media(max-width:540px){.feat-grid{grid-template-columns:1fr}.tabs{flex-wrap:wrap}}
</style>
</head>
<body>
<div class="wrap">

<div class="header">
    <div class="brand">SohojWeb <span>Projects</span></div>
    <?php if ($configExists && $baseUrl): ?>
    <a href="<?= $baseUrl ?>/index.php" class="back-link">← Back to app</a>
    <?php endif ?>
</div>

<?php if (!$configExists): ?>
<!-- ══════════════════ INSTALL FORM ══════════════════ -->
<?php if ($installSuccess): ?>
<div class="card">
    <div style="text-align:center;padding:10px 0">
        <div style="font-size:2.5rem;margin-bottom:10px">✅</div>
        <div style="font-size:1.1rem;font-weight:700;color:#15803D;margin-bottom:6px">Setup Complete!</div>
        <div style="font-size:.875rem;color:#6B7280;margin-bottom:20px">Your application is ready to use.</div>
        <a href="<?= htmlspecialchars($_POST['base_url'] ?? '') ?>/login.php" class="btn btn-primary">Go to Login →</a>
    </div>
    <div class="section-sep">
        <div class="section-label">Tables created</div>
        <?php renderTableGrid($installResults); ?>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-title">⚙️ Installation Wizard</div>
    <?php if ($installError): ?><div class="alert alert-error"><?= htmlspecialchars($installError) ?></div><?php endif ?>
    <form method="POST">
        <div class="section-label">Database</div>
        <label>Host</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" placeholder="localhost">
        <label>Database Name *</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="swp" required>
        <label>MySQL Username *</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" placeholder="root" required>
        <label>MySQL Password</label>
        <input type="password" name="db_pass">
        <div class="section-sep">
        <div class="section-label">Application</div>
        <label>App Name</label>
        <input type="text" name="app_name" value="<?= htmlspecialchars($_POST['app_name'] ?? 'SohojWeb Projects') ?>">
        <label>Base URL</label>
        <input type="text" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? 'http://localhost/projects') ?>">
        </div>
        <div class="section-sep">
        <div class="section-label">Admin Account</div>
        <label>Username *</label>
        <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>" required>
        <label>Password *</label>
        <input type="password" name="admin_pass" required>
        <label>Full Name *</label>
        <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Install SohojWeb Projects</button>
    </form>
</div>
<?php endif ?>

<?php else: ?>
<!-- ══════════════════ MANAGEMENT DASHBOARD ══════════════════ -->

<div class="tabs">
    <a href="?section=tables"   class="tab <?= $section==='tables'   ?'active':'' ?>">📋 Tables</a>
    <a href="?section=demo"     class="tab <?= $section==='demo'     ?'active':'' ?>">🎭 Demo Data</a>
    <a href="?section=reset"    class="tab <?= $section==='reset'    ?'active':'' ?>">🔄 Reset</a>
    <a href="?section=features" class="tab <?= $section==='features' ?'active':'' ?>">✨ Features</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>

<?php if ($section === 'tables'): ?>
<!-- ── Tables ─────────────────────────────────────────── -->
<div class="card">
    <div class="card-title">📋 Database Tables</div>
    <?php renderTableGrid($tableResults); ?>
    <form method="POST" style="margin-top:16px">
        <input type="hidden" name="action" value="sync_tables">
        <button type="submit" class="btn btn-primary">↻ Re-sync Tables</button>
    </form>
</div>

<?php elseif ($section === 'demo'): ?>
<!-- ── Demo Data ──────────────────────────────────────── -->
<div class="card">
    <div class="card-title">🎭 Demo Data</div>
    <p style="font-size:.8125rem;color:#6B7280;line-height:1.6;margin-bottom:16px">
        Inserts realistic sample data so you can explore all features immediately.
        Safe to run multiple times — duplicate records are skipped automatically.
    </p>

    <div class="section-label" style="margin-bottom:8px">What gets added</div>
    <table class="demo-table">
        <thead><tr><th>Type</th><th>Details</th></tr></thead>
        <tbody>
        <tr><td>👥 Users</td><td>3 demo members: Alice Rahman, Bob Hossain, Charlie Ahmed (password: <code>demo1234</code>)</td></tr>
        <tr><td>📁 Projects</td><td>Website Redesign (active) · Mobile App (planning)</td></tr>
        <tr><td>✅ Tasks</td><td>18 tasks across both projects — varied status &amp; priority</td></tr>
        <tr><td>🔀 Subtasks</td><td>4 subtasks on "Hero section implementation"</td></tr>
        <tr><td>💬 Comments</td><td>3 task comments with @mentions</td></tr>
        <tr><td>⏱ Time Logs</td><td>9 log entries across users and tasks</td></tr>
        <tr><td>📅 Meetings</td><td>3 meetings with attendees, RSVPs, and action items</td></tr>
        <tr><td>🗨 Chat</td><td>8 worksheet chat messages across both projects</td></tr>
        <tr><td>📰 Feed</td><td>7 activity feed entries</td></tr>
        <tr><td>🔔 Notifications</td><td>3 unread notifications for demo users</td></tr>
        </tbody>
    </table>

    <form method="POST" style="margin-top:20px">
        <input type="hidden" name="action" value="demo">
        <button type="submit" class="btn btn-primary">Insert Demo Data</button>
    </form>
</div>

<?php elseif ($section === 'reset'): ?>
<!-- ── Reset ──────────────────────────────────────────── -->
<div class="card">
    <div class="card-title">🔄 Reset Database</div>
    <p style="font-size:.8125rem;color:#6B7280;margin-bottom:20px;line-height:1.6">
        Truncates <strong>all tables</strong> and removes every record — users, projects, tasks, chat, meetings, logs, and notifications.
        The table structure is preserved. This action <strong>cannot be undone</strong>.
    </p>
    <div class="danger-zone">
        <div class="danger-title">⚠ Danger Zone</div>
        <p style="font-size:.8rem;color:#6B7280;margin-bottom:4px">Type <strong>RESET</strong> to confirm you want to wipe all data.</p>
        <form method="POST">
            <input type="hidden" name="action" value="reset">
            <input type="text" name="confirm_reset" class="confirm-input" placeholder="Type RESET here" autocomplete="off">
            <button type="submit" class="btn btn-danger">Wipe All Data</button>
        </form>
    </div>
</div>

<?php elseif ($section === 'features'): ?>
<!-- ── Features ───────────────────────────────────────── -->
<div class="card">
    <div class="card-title">✨ Feature Overview</div>
    <div class="feat-grid">
        <?php
        $features = [
            ['📁', 'Project Management',       'Create projects with status, due dates, client info, tech notes, and team members.'],
            ['✅', 'Task Kanban Board',          'Drag-free kanban with To Do / In Progress / Review / Done columns, priorities, and due dates.'],
            ['🔀', 'Subtasks',                  'Break tasks into subtasks with their own status. Progress bar on parent cards.'],
            ['💬', 'Task Comments',              'Per-task comment threads with @mention support and notification delivery.'],
            ['⏱', 'Time Logging',               'Log hours per task. Member breakdown and heatmap on reports.'],
            ['📋', 'Task Descriptions',          'Rich textarea description editable directly in the slide-over drawer.'],
            ['📅', 'Meetings & RSVP',            'Schedule meetings, set agendas, track attendees with Yes/No/Maybe RSVP.'],
            ['⚡', 'Action Items',               'Generate tasks from meeting action items with one click.'],
            ['🗨', 'Team Chat',                  'Real-time-style project chat with replies, pins, private messages, and @mentions.'],
            ['😊', 'Emoji Reactions',            '6-emoji reaction bar on chat messages. Togglable, with per-user tracking.'],
            ['🖥', 'Worksheet Dashboard',        'Live per-project dashboard: tasks, chat, meetings, feed, time log, files, and details in one view.'],
            ['🔔', 'Notifications',              'In-app notifications for @mentions, task assignments, and comments.'],
            ['⌨', 'Keyboard Shortcuts',         'R=Refresh, T=Tasks, C=Chat, Ctrl+K=Quick Create, Esc=Close drawer, ?=Help.'],
            ['➕', 'Quick Task (Ctrl+K)',         'Global modal to create a task in any project without leaving your current page.'],
            ['☀', 'My Day View',                'Daily focus page: urgent tasks, today\'s meetings, notifications, and @mentions.'],
            ['📊', 'Reports & Heatmap',          '52-week activity heatmap, status/priority charts, project health table, overdue list.'],
            ['🔍', 'Global Search',              'Full-text search across projects, tasks, meetings, and users.'],
            ['🌙', 'Dark Mode',                  'Persistent theme toggle with system-aware default.'],
            ['📱', 'Mobile Responsive',          'Bottom nav, slide-over drawers, and collapsible sidebar work on any screen size.'],
            ['📎', 'File Links',                 'Attach URLs/links to tasks — visible in the worksheet files panel.'],
            ['🗂', 'Activity Feed',              'Per-project update feed with pin, mark-read, and unread badge.'],
            ['📆', 'Calendar View',              'Monthly calendar showing tasks and meetings across all projects.'],
        ];
        foreach ($features as [$icon, $title, $desc]):
        ?>
        <div class="feat-item">
            <div class="feat-icon"><?= $icon ?></div>
            <div>
                <div class="feat-title"><?= htmlspecialchars($title) ?></div>
                <div class="feat-desc"><?= htmlspecialchars($desc) ?></div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>

<?php endif ?>
<?php endif ?>

</div><!-- /wrap -->
</body>
</html>
<?php

// ── Render table grid ────────────────────────────────────────
function renderTableGrid(array $results): void {
    if (!$results) { echo '<p style="font-size:.8rem;color:#9CA3AF">No results yet.</p>'; return; }
    $total   = count($results);
    $created = count(array_filter($results, fn($v) => $v === 'created'));
    $errors  = count(array_filter($results, fn($v) => str_starts_with($v, 'error:')));
    $exists  = $total - $created - $errors;
    echo '<div class="tbl-grid">';
    foreach ($results as $name => $status) {
        $cls   = str_starts_with($status, 'error:') ? 'error' : $status;
        $icon  = match($cls) { 'created' => '✅', 'exists' => '☑', default => '❌' };
        $label = match($cls) { 'created' => 'Created', 'exists' => 'Up to date', default => substr($status, 6) };
        echo "<div class=\"tbl-row $cls\">"
           . "<span class=\"tbl-icon\">$icon</span>"
           . "<span class=\"tbl-name\">$name</span>"
           . "<span class=\"tbl-status\">" . htmlspecialchars($label) . "</span>"
           . "</div>";
    }
    echo '</div>';
    echo "<div class=\"summary\">"
       . "<span>☑ Up to date: <strong>$exists</strong></span>"
       . "<span>✅ Created: <strong>$created</strong></span>"
       . ($errors ? "<span>❌ Errors: <strong>$errors</strong></span>" : '')
       . "</div>";
}
