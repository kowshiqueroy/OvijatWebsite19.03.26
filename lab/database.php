<?php
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'lab.db';
        $db = new PDO("sqlite:{$dbPath}");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec("PRAGMA journal_mode = WAL;");
        $db->exec("PRAGMA foreign_keys = ON;");
        _initTables($db);
    }
    return $db;
}

function _initTables($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            pin TEXT NOT NULL,
            is_online INTEGER DEFAULT 0,
            is_teacher INTEGER DEFAULT 0,
            is_blocked INTEGER DEFAULT 0,
            total_academic_points INTEGER DEFAULT 0,
            total_arcade_points INTEGER DEFAULT 0,
            current_screen TEXT DEFAULT 'dashboard',
            created_at DATETIME DEFAULT (datetime('now','localtime')),
            last_active DATETIME DEFAULT (datetime('now','localtime'))
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS lab_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL CHECK(type IN ('mcq','typing')),
            content_json TEXT NOT NULL,
            time_limit INTEGER DEFAULT 0,
            created_by INTEGER,
            created_at DATETIME DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(created_by) REFERENCES students(id)
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS mcq_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            task_id INTEGER NOT NULL,
            score INTEGER DEFAULT 0,
            total INTEGER DEFAULT 0,
            completed_at DATETIME DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(student_id) REFERENCES students(id),
            FOREIGN KEY(task_id) REFERENCES lab_tasks(id)
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS typing_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            task_id INTEGER,
            wpm REAL DEFAULT 0,
            accuracy REAL DEFAULT 0,
            points_earned INTEGER DEFAULT 0,
            completed_at DATETIME DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(student_id) REFERENCES students(id)
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_type TEXT NOT NULL CHECK(game_type IN ('sprint','tictactoe')),
            player1_id INTEGER NOT NULL,
            player2_id INTEGER NOT NULL,
            state_json TEXT DEFAULT '{}',
            winner_id INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(player1_id) REFERENCES students(id),
            FOREIGN KEY(player2_id) REFERENCES students(id)
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS student_warnings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            warning_type TEXT NOT NULL,
            details TEXT DEFAULT '',
            created_at DATETIME DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(student_id) REFERENCES students(id)
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS pushed_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            task_id INTEGER NOT NULL,
            is_active INTEGER DEFAULT 1,
            pushed_at DATETIME DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(student_id) REFERENCES students(id),
            FOREIGN KEY(task_id) REFERENCES lab_tasks(id)
        )
    ");
    $stmt = $db->prepare("SELECT id FROM students WHERE username = ?");
    $stmt->execute(['teacher']);
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO students (username, pin, is_teacher) VALUES (?, ?, 1)")
           ->execute(['teacher', '1234']);
    }
}
