<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE,          PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    _initSchema($db);
    return $db;
}

function _initSchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT    UNIQUE NOT NULL,
        password_hash TEXT    NOT NULL,
        claude_api_key TEXT   DEFAULT '',
        ai_keys_json  TEXT    DEFAULT '{}',
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: add ai_keys_json to existing installs
    try { $db->exec("ALTER TABLE users ADD COLUMN ai_keys_json TEXT DEFAULT '{}'"); } catch (\Exception $e) {}
    // Migrate legacy claude_api_key into ai_keys_json where not yet migrated
    $db->exec("UPDATE users SET ai_keys_json = json_object('claude', claude_api_key)
               WHERE claude_api_key != '' AND (ai_keys_json IS NULL OR ai_keys_json = '{}' OR ai_keys_json = '')");

    $db->exec("CREATE TABLE IF NOT EXISTS question_papers (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        title      TEXT    NOT NULL DEFAULT 'Untitled Paper',
        paper_json TEXT    NOT NULL DEFAULT '{}',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS question_bank (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL,
        question_type TEXT    NOT NULL,
        subject       TEXT    DEFAULT '',
        topic         TEXT    DEFAULT '',
        content_json  TEXT    NOT NULL DEFAULT '{}',
        tags          TEXT    DEFAULT '',
        use_count     INTEGER DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS shared_papers (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        paper_id     INTEGER NOT NULL,
        share_token  TEXT    UNIQUE NOT NULL,
        show_answers INTEGER DEFAULT 0,
        expires_at   DATETIME,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (paper_id) REFERENCES question_papers(id) ON DELETE CASCADE
    )");
}
