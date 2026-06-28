<?php
// src/Database.php

class Database {
    private static ?PDO  $corePdo  = null;
    private static array $chatPdos = [];   // shard connection cache: chatIdClean → PDO

    // Bump this number whenever the schema changes.
    // The first request after a bump re-runs initializeCoreTables() once, then
    // all subsequent requests skip it entirely via the flag file check.
    private const SCHEMA_VERSION = 9;

    /* ================================================================
       CORE DATABASE
       ================================================================ */

    public static function getCoreConnection(): PDO {
        if (self::$corePdo !== null) return self::$corePdo;

        $dbPath = CORE_DB_PATH;
        $dbDir  = dirname($dbPath);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
            file_put_contents($dbDir . '/.htaccess', "Deny from all\n");
        }

        try {
            self::$corePdo = new PDO("sqlite:" . $dbPath);
            self::$corePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$corePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Performance PRAGMAs — each is a single statement, safe to call every boot
            self::$corePdo->exec("PRAGMA foreign_keys = ON");
            self::$corePdo->exec("PRAGMA journal_mode = WAL");      // non-blocking concurrent reads
            self::$corePdo->exec("PRAGMA synchronous   = NORMAL");  // safe + faster than FULL
            self::$corePdo->exec("PRAGMA cache_size    = -8000");   // 8 MB page cache
            self::$corePdo->exec("PRAGMA temp_store    = MEMORY");  // temp tables in RAM
            self::$corePdo->exec("PRAGMA busy_timeout  = 5000");   // wait up to 5s if locked

            // Schema init is expensive (~20 SQL statements).
            // The flag file makes it a one-time cost per schema version;
            // every subsequent request skips the entire block.
            $versionFile = $dbDir . '/.schema_v' . self::SCHEMA_VERSION;
            if (!file_exists($versionFile)) {
                self::initializeCoreTables(self::$corePdo);
                file_put_contents($versionFile, date('Y-m-d H:i:s'));
                // Remove any older version flag files
                foreach (glob($dbDir . '/.schema_v*') as $f) {
                    if ($f !== $versionFile) @unlink($f);
                }
            }

        } catch (PDOException $e) {
            die("Core Database Connection failed: " . $e->getMessage());
        }

        return self::$corePdo;
    }

    /* ================================================================
       SHARDED CHAT DATABASE
       ================================================================ */

    public static function getChatConnection(string $chatId): PDO {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $chatId);

        // Return cached connection — avoids re-opening the file and re-running
        // CREATE TABLE IF NOT EXISTS on every call within the same request.
        if (isset(self::$chatPdos[$clean])) return self::$chatPdos[$clean];

        $dbPath = CHAT_DB_DIR . '/chat_' . $clean . '.sqlite';
        $isNew  = !file_exists($dbPath) || filesize($dbPath) === 0;

        if (!is_dir(CHAT_DB_DIR)) mkdir(CHAT_DB_DIR, 0755, true);

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA journal_mode = WAL");
            $pdo->exec("PRAGMA synchronous  = NORMAL");
            $pdo->exec("PRAGMA cache_size   = -4000");   // 4 MB per shard
            $pdo->exec("PRAGMA busy_timeout  = 5000");   // wait up to 5s if locked

            // Only initialise tables when the shard file is brand-new.
            // Existing shards already have their schema; skipping init on them
            // is the biggest single saving in getChatConnection().
            if ($isNew) self::initializeChatTables($pdo);

            self::$chatPdos[$clean] = $pdo;
            return $pdo;

        } catch (PDOException $e) {
            die("Chat Shard Connection failed: " . $e->getMessage());
        }
    }

    /* ================================================================
       SCHEMA — runs once per SCHEMA_VERSION bump, never again
       IMPORTANT: each exec() call must contain ONE statement only.
       PHP PDO SQLite only executes the first statement in a multi-
       statement string; subsequent ones are silently dropped.
       ================================================================ */

    private static function initializeCoreTables(PDO $pdo): void {

        // ── Tables (one exec per statement) ──────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            email         TEXT    UNIQUE NOT NULL,
            password_hash TEXT    NOT NULL,
            pin_hash      TEXT    NOT NULL,
            full_name     TEXT    NOT NULL,
            address       TEXT    NOT NULL,
            dob           TEXT    NOT NULL,
            institute     TEXT    NOT NULL,
            phone         TEXT    NOT NULL,
            is_approved   INTEGER DEFAULT 0,
            is_admin      INTEGER DEFAULT 0,
            last_seen     INTEGER DEFAULT 0,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS chats_index (
            chat_id    TEXT PRIMARY KEY,
            chat_type  TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_participants (
            chat_id   TEXT    NOT NULL,
            user_id   INTEGER NOT NULL,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (chat_id, user_id),
            FOREIGN KEY (chat_id) REFERENCES chats_index(chat_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)            ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS nicknames (
            user_id     INTEGER NOT NULL,
            target_type TEXT    NOT NULL,
            target_id   TEXT    NOT NULL,
            nickname    TEXT    NOT NULL,
            PRIMARY KEY (user_id, target_type, target_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS call_records (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            caller_id        INTEGER,
            chat_id          TEXT    NOT NULL,
            call_type        TEXT    DEFAULT 'audio',
            recording_file   TEXT,
            ended_at         DATETIME,
            duration_minutes INTEGER DEFAULT 0,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (caller_id) REFERENCES users(id)            ON DELETE SET NULL,
            FOREIGN KEY (chat_id)   REFERENCES chats_index(chat_id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
            group_id   TEXT    PRIMARY KEY,
            name       TEXT    NOT NULL,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id)   REFERENCES chats_index(chat_id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id)             ON DELETE SET NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sse_events (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id    TEXT    NOT NULL,
            sender_id  INTEGER NOT NULL,
            event_type TEXT    NOT NULL,
            payload    TEXT    NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS plan_templates (
            plan_name                TEXT PRIMARY KEY,
            label                    TEXT NOT NULL,
            limit_text               INTEGER,
            limit_image              INTEGER,
            limit_video              INTEGER,
            limit_audio              INTEGER,
            limit_audio_call_minutes INTEGER,
            limit_video_call_minutes INTEGER,
            contact_number           TEXT DEFAULT '',
            contact_text             TEXT DEFAULT '',
            updated_at               DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_plans (
            user_id                  INTEGER PRIMARY KEY,
            plan_name                TEXT    NOT NULL DEFAULT 'trial',
            limit_text               INTEGER,
            limit_image              INTEGER,
            limit_video              INTEGER,
            limit_audio              INTEGER,
            limit_audio_call_minutes INTEGER,
            limit_video_call_minutes INTEGER,
            expires_at               INTEGER,
            assigned_by              INTEGER,
            assigned_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS daily_usage (
            user_id            INTEGER NOT NULL,
            usage_date         TEXT    NOT NULL,
            text_count         INTEGER DEFAULT 0,
            image_count        INTEGER DEFAULT 0,
            video_count        INTEGER DEFAULT 0,
            audio_count        INTEGER DEFAULT 0,
            audio_call_minutes INTEGER DEFAULT 0,
            video_call_minutes INTEGER DEFAULT 0,
            PRIMARY KEY (user_id, usage_date)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS upgrade_requests (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id        INTEGER NOT NULL,
            requested_plan TEXT    NOT NULL,
            message        TEXT,
            status         TEXT    DEFAULT 'pending',
            admin_note     TEXT,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            title          TEXT NOT NULL,
            body           TEXT NOT NULL,
            target_group   TEXT DEFAULT 'all',
            contact_number TEXT DEFAULT '',
            contact_text   TEXT DEFAULT '',
            sent_by        INTEGER,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
            notification_id INTEGER NOT NULL,
            user_id         INTEGER NOT NULL,
            read_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (notification_id, user_id)
        )");

        // ── App-wide key/value settings ──────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            key        TEXT PRIMARY KEY,
            value      TEXT NOT NULL DEFAULT '',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("INSERT OR IGNORE INTO app_settings (key, value) VALUES
            ('auto_approve_registration', '0')");

        // ── User locations table ─────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_locations (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            latitude     REAL    NOT NULL,
            longitude    REAL    NOT NULL,
            accuracy     REAL,
            ip_address   TEXT,
            user_agent   TEXT,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_loc_user ON user_locations(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_loc_time ON user_locations(created_at DESC)");

        // ── ALTER TABLE migrations (safe on existing DBs) ─────────────
        foreach ([
            "ALTER TABLE users        ADD COLUMN last_seen          DATETIME",
            "ALTER TABLE users        ADD COLUMN location_denied    INTEGER DEFAULT 0",
            "ALTER TABLE call_records ADD COLUMN call_type          TEXT DEFAULT 'audio'",
            "ALTER TABLE call_records ADD COLUMN ended_at           DATETIME",
            "ALTER TABLE call_records ADD COLUMN duration_minutes   INTEGER DEFAULT 0",
        ] as $sql) {
            try { $pdo->exec($sql); } catch (\PDOException $e) { /* column already exists */ }
        }

        // ── Indexes (one per exec) ────────────────────────────────────
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sse_created  ON sse_events(created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sse_chat     ON sse_events(chat_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cp_user      ON chat_participants(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cp_chat      ON chat_participants(chat_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_seen   ON users(last_seen)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_du_user_date ON daily_usage(user_id, usage_date)");

        // ── Seeds ─────────────────────────────────────────────────────
        $pdo->exec("INSERT OR IGNORE INTO plan_templates
            (plan_name, label, limit_text, limit_image, limit_video, limit_audio,
             limit_audio_call_minutes, limit_video_call_minutes, contact_number, contact_text)
            VALUES
            ('trial',     'Trial',      500,  50,  20,  30,  180, 120, '', 'Contact us to upgrade.'),
            ('heavy',     'Heavy',     5000, 200, 100, 100,  300, 300, '', 'Contact us to upgrade to Unlimited.'),
            ('unlimited', 'Unlimited', NULL, NULL, NULL, NULL, NULL, NULL, '', 'You are on the Unlimited plan.')");

        $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
        if ($adminCount == 0) {
            $pdo->prepare("INSERT INTO users
                (email, password_hash, pin_hash, full_name, address, dob, institute, phone, is_approved, is_admin)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
            )->execute([
                'admin@kotha.sohojweb.com',
                password_hash('AdminPassword123', PASSWORD_BCRYPT),
                password_hash('1234',             PASSWORD_BCRYPT),
                'Corporate Admin', 'Kotha Corporate HQ',
                '1990-01-01', 'sohojweb.com', '+8801700000000',
            ]);
        }
    }

    private static function initializeChatTables(PDO $pdo): void {
        // One statement per exec() — PDO SQLite only runs the first in a multi-statement string
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id           TEXT    PRIMARY KEY,
            sender_id    INTEGER NOT NULL,
            message_type TEXT    NOT NULL,
            content      TEXT    NOT NULL,
            file_path    TEXT,
            is_read      INTEGER DEFAULT 0,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_vanished_messages (
            message_id TEXT    NOT NULL,
            user_id    INTEGER NOT NULL,
            PRIMARY KEY (message_id, user_id)
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_created ON messages(created_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_read    ON messages(sender_id, is_read)");
    }
}
