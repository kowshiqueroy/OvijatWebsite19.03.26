<?php
$db_file = __DIR__ . '/chat_database.sq3';

// Force drop and recreation if we need to apply structural changes (last_seen as INTEGER)
// Note: In a real live scenario, you would use migrations.
if (file_exists($db_file)) {
    // Check if user_status table has last_seen as string or integer by trying a numeric comparison
    try {
        $check_pdo = new PDO("sqlite:$db_file");
        $res = $check_pdo->query("SELECT typeof(last_seen) FROM user_status LIMIT 1")->fetchColumn();
        if ($res && $res !== 'integer') {
            unlink($db_file); // Nuclear structural update
        }
    } catch (Exception $e) {}
}

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

    // Initialize default PINs and Passwords if not set (using hashes)
    $pass1 = password_hash('iloverai', PASSWORD_BCRYPT);
    $pass2 = password_hash('ilovekush', PASSWORD_BCRYPT);
    $pin1 = password_hash('5877', PASSWORD_BCRYPT);
    $pin2 = password_hash('5877', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES 
        ('pin_1', ?), 
        ('pin_2', ?),
        ('pass_1', ?),
        ('pass_2', ?)
    ");
    $stmt->execute([$pin1, $pin2, $pass1, $pass2]);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
