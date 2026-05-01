<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/chat.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        unlock_pin_hash TEXT NOT NULL,
        last_active INTEGER DEFAULT 0
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER NOT NULL,
        receiver_id INTEGER NOT NULL,
        original_text TEXT NOT NULL,
        timestamp INTEGER NOT NULL
    )");
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>