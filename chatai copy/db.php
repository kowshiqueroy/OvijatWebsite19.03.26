<?php
date_default_timezone_set('Asia/Dhaka');
$db_file = __DIR__ . '/chat_database.sq3';

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Check if initialized
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
    $initialized = $stmt->fetch();

    if (!$initialized) {
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
            is_voice INTEGER DEFAULT 0,
            is_video INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            viewed_at DATETIME DEFAULT NULL,
            burn_after INTEGER DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS youtube_sync (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            video_id TEXT,
            state INTEGER DEFAULT 0, -- 0: paused, 1: playing
            current_time REAL DEFAULT 0,
            last_updated_by INTEGER,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ready_user1 INTEGER DEFAULT 1,
            ready_user2 INTEGER DEFAULT 1,
            comments_enabled INTEGER DEFAULT 1
        )");

        // Initialize youtube_sync
        $pdo->exec("INSERT OR IGNORE INTO youtube_sync (id, video_id, state, current_time, ready_user1, ready_user2, comments_enabled) VALUES (1, '', 0, 0, 1, 1, 1)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS video_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            video_id TEXT UNIQUE,
            title TEXT,
            watched_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_status (
            user_id INTEGER PRIMARY KEY,
            last_seen INTEGER,
            is_typing INTEGER DEFAULT 0,
            in_theater INTEGER DEFAULT 0
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sms_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            phone_number TEXT,
            message TEXT,
            status TEXT,
            error_msg TEXT,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS saved_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            image_data TEXT,
            is_voice INTEGER DEFAULT 0,
            is_video INTEGER DEFAULT 0,
            saved_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Initialize default PINs and Passwords if not set (using hashes)
        $pass1 = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $pass2 = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $pin1 = password_hash(sprintf("%04d", random_int(0, 9999)), PASSWORD_BCRYPT);
        $pin2 = password_hash(sprintf("%04d", random_int(0, 9999)), PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES 
            ('pin_1', ?), 
            ('pin_2', ?),
            ('pass_1', ?),
            ('pass_2', ?),
            ('sms_api_key', ?),
            ('sms_number_1', ?),
            ('sms_number_2', ?),
            ('sms_enabled_2', '1'),
            ('sms_default_msg', 'Since {time}, Gemini.sohojweb.com is waiting for your response.')
        ");
        $stmt->execute([
            $pin1, $pin2, $pass1, $pass2,
            defined('INITIAL_SMS_API_KEY') ? INITIAL_SMS_API_KEY : '',
            defined('INITIAL_SMS_NUMBER_1') ? INITIAL_SMS_NUMBER_1 : '',
            defined('INITIAL_SMS_NUMBER_2') ? INITIAL_SMS_NUMBER_2 : ''
        ]);
    }

    // Column maintenance (for existing databases)
    $cols = $pdo->query("PRAGMA table_info(messages)")->fetchAll(PDO::FETCH_ASSOC);
    $has_voice = false; $has_video = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'is_voice') $has_voice = true;
        if ($c['name'] === 'is_video') $has_video = true;
    }
    if (!$has_voice) $pdo->exec("ALTER TABLE messages ADD COLUMN is_voice INTEGER DEFAULT 0");
    if (!$has_video) $pdo->exec("ALTER TABLE messages ADD COLUMN is_video INTEGER DEFAULT 0");

    $cols = $pdo->query("PRAGMA table_info(saved_images)")->fetchAll(PDO::FETCH_ASSOC);
    $has_voice_saved = false; $has_video_saved = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'is_voice') $has_voice_saved = true;
        if ($c['name'] === 'is_video') $has_video_saved = true;
    }
    if (!$has_voice_saved) $pdo->exec("ALTER TABLE saved_images ADD COLUMN is_voice INTEGER DEFAULT 0");
    if (!$has_video_saved) $pdo->exec("ALTER TABLE saved_images ADD COLUMN is_video INTEGER DEFAULT 0");

    $cols = $pdo->query("PRAGMA table_info(user_status)")->fetchAll(PDO::FETCH_ASSOC);
    $hasTyping = false; $hasTheater = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'is_typing') $hasTyping = true;
        if ($c['name'] === 'in_theater') $hasTheater = true;
    }
    if (!$hasTyping) $pdo->exec("ALTER TABLE user_status ADD COLUMN is_typing INTEGER DEFAULT 0");
    if (!$hasTheater) $pdo->exec("ALTER TABLE user_status ADD COLUMN in_theater INTEGER DEFAULT 0");

    $cols = $pdo->query("PRAGMA table_info(youtube_sync)")->fetchAll(PDO::FETCH_ASSOC);
    $hasReady1 = false; $hasReady2 = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'ready_user1') $hasReady1 = true;
        if ($c['name'] === 'ready_user2') $hasReady2 = true;
    }
    if (!$hasReady1) $pdo->exec("ALTER TABLE youtube_sync ADD COLUMN ready_user1 INTEGER DEFAULT 1");
    if (!$hasReady2) $pdo->exec("ALTER TABLE youtube_sync ADD COLUMN ready_user2 INTEGER DEFAULT 1");

    $colsSync = $pdo->query("PRAGMA table_info(youtube_sync)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCommentsEnabled = false;
    foreach ($colsSync as $c) {
        if ($c['name'] === 'comments_enabled') $hasCommentsEnabled = true;
    }
    if (!$hasCommentsEnabled) $pdo->exec("ALTER TABLE youtube_sync ADD COLUMN comments_enabled INTEGER DEFAULT 1");

    $pdo->exec("CREATE TABLE IF NOT EXISTS video_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        video_id TEXT UNIQUE,
        title TEXT,
        watched_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("A database error occurred. Please try again later.");
}
