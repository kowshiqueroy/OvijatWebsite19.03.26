<?php
date_default_timezone_set('Asia/Dhaka');
// db.php - Database helpers using PDO with auto-creation

function initChatDb() {
    $pdo = new PDO('sqlite:' . __DIR__ . '/chat.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER NOT NULL,
        receiver_id INTEGER NOT NULL,
        message TEXT,
        is_image BOOLEAN DEFAULT 0,
        image_blob BLOB,
        word_count INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        viewed BOOLEAN DEFAULT 0,
        viewed_at DATETIME,
        deleted_at DATETIME
    )");
    return $pdo;
}

function initUserDb($user) {
    $file = __DIR__ . '/' . $user . '.db';
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        username TEXT NOT NULL,
        pin TEXT NOT NULL,
        is_unlocked BOOLEAN DEFAULT 0,
        last_active DATETIME,
        is_typing BOOLEAN DEFAULT 0
    )");
    // Insert default if not exists
    $pin = $user === 'user1' ? '7785' : '5877';
    $username = $user === 'user1' ? 'user1' : 'user2';
    $pdo->exec("INSERT OR IGNORE INTO users (id, username, pin, is_unlocked, last_active, is_typing)
                VALUES (1, '$username', '$pin', 0, CURRENT_TIMESTAMP, 0)");
    return $pdo;
}

function getChatDb() {
    $file = __DIR__ . '/chat.db';
    if (!file_exists($file)) {
        return initChatDb();
    }
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ensure columns exist (migration)
    try {
        $pdo->query("SELECT viewed FROM messages LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN viewed BOOLEAN DEFAULT 0");
        $pdo->exec("ALTER TABLE messages ADD COLUMN viewed_at DATETIME");
        $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_at DATETIME");
    }
    return $pdo;
}

function getUser1Db() {
    $file = __DIR__ . '/user1.db';
    if (!file_exists($file)) {
        return initUserDb('user1');
    }
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function getUser2Db() {
    $file = __DIR__ . '/user2.db';
    if (!file_exists($file)) {
        return initUserDb('user2');
    }
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function getUserDb($user) {
    return $user === 'user1' ? getUser1Db() : getUser2Db();
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getOtherUser($user) {
    return [
        'user' => $user === 'user1' ? 'user2' : 'user1',
        'id' => $user === 'user1' ? 2 : 1
    ];
}
?>
