<?php
$db_file = __DIR__ . '/chat_database.sq3';

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER,
        receiver_id INTEGER,
        content TEXT,
        is_image INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        viewed_at DATETIME DEFAULT NULL,
        burn_after INTEGER DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_status (
        user_id INTEGER PRIMARY KEY,
        last_seen INTEGER,
        is_typing INTEGER DEFAULT 0
    )");

    // Initialize default PINs and Passwords if not set
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES 
        ('pin_1', '5877'), 
        ('pin_2', '5877'),
        ('pass_1', 'iloverai'),
        ('pass_2', 'ilovekush')
    ");
    $stmt->execute();

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
