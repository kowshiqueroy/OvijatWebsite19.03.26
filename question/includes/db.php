<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        display_name  TEXT    DEFAULT '',
        settings_json TEXT    DEFAULT '{}',
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Papers: header/meta fields are real columns (validated, searchable);
    // the builder's element tree + print settings live in JSON blobs since
    // their shape is driven entirely by the dynamic JS builder.
    $db->exec("CREATE TABLE IF NOT EXISTS papers (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id           INTEGER NOT NULL,
        name              TEXT    NOT NULL DEFAULT 'Untitled Question',
        language          TEXT    NOT NULL DEFAULT 'bn',
        primary_font      TEXT    NOT NULL DEFAULT 'Kalpurush',
        secondary_font    TEXT    NOT NULL DEFAULT 'Times New Roman',
        school_name       TEXT    DEFAULT '',
        exam_name         TEXT    DEFAULT '',
        class_name        TEXT    DEFAULT '',
        subject_name      TEXT    DEFAULT '',
        time_text         TEXT    DEFAULT '',
        full_marks        TEXT    DEFAULT '',
        show_subject_code INTEGER NOT NULL DEFAULT 0,
        subject_code      TEXT    DEFAULT '',
        show_set_code     INTEGER NOT NULL DEFAULT 0,
        set_code          TEXT    DEFAULT '',
        page_size         TEXT    NOT NULL DEFAULT 'A4',
        print_mode        TEXT    NOT NULL DEFAULT 'full-1col',
        print_settings_json TEXT NOT NULL DEFAULT '{}',
        elements_json     TEXT    NOT NULL DEFAULT '[]',
        is_answer_key     INTEGER NOT NULL DEFAULT 0,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_papers_user ON papers(user_id)");

    $db->exec("CREATE TABLE IF NOT EXISTS shared_papers (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        paper_id     INTEGER NOT NULL,
        share_token  TEXT    UNIQUE NOT NULL,
        show_answers INTEGER DEFAULT 0,
        expires_at   DATETIME,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
    )");
}

function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
