<?php
$db_file = __DIR__ . '/chat_database.sq3';

// Force drop and recreation if we need to apply structural changes
// Major overhaul to support multi-user, rooms, and granular privacy
if (file_exists($db_file)) {
    try {
        $check_pdo = new PDO("sqlite:$db_file");
        // Check if 'users' table exists. If not, it's the old schema.
        $hasUsers = $check_pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        if (!$hasUsers) {
            unlink($db_file); // Nuclear structural update
        }
    } catch (Exception $e) {}
}

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        display_name TEXT,
        password_hash TEXT,
        pin_hash TEXT,
        role TEXT DEFAULT 'user', -- 'admin' or 'user'
        status TEXT DEFAULT 'pending', -- 'pending', 'active', 'blocked'
        camouflage_theme TEXT DEFAULT 'coding',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. User Privacy Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_privacy_settings (
        user_id INTEGER PRIMARY KEY,
        auto_lock_timer INTEGER DEFAULT 60, -- seconds
        reveal_on_arrival_duration INTEGER DEFAULT 0, -- seconds
        reveal_on_click_duration INTEGER DEFAULT 5, -- seconds
        camouflage_style TEXT DEFAULT 'c_code', -- 'c_code', 'dummy', 'none'
        auto_reveal_unlocked INTEGER DEFAULT 0, -- boolean (0 or 1)
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Rooms Table (for 1:1 and Group chats)
    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        type TEXT DEFAULT '1to1', -- '1to1' or 'group'
        creator_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )");

    // 4. Room Members Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS room_members (
        room_id INTEGER,
        user_id INTEGER,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (room_id, user_id),
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 5. Nicknames Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS nicknames (
        set_by_user_id INTEGER,
        target_user_id INTEGER,
        nickname TEXT,
        PRIMARY KEY (set_by_user_id, target_user_id),
        FOREIGN KEY (set_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 6. Camouflage Library Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS camouflage_library (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        theme TEXT, -- 'coding', 'physics', 'games', 'beauty'
        type TEXT, -- 'prompt' or 'response'
        content TEXT
    )");

    // 7. Messages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_id INTEGER,
        sender_id INTEGER,
        content TEXT,
        is_image INTEGER DEFAULT 0,
        is_fake INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        viewed_at DATETIME DEFAULT NULL,
        burn_after INTEGER DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 8. User Status Table (for active/typing)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_status (
        user_id INTEGER PRIMARY KEY,
        last_seen INTEGER,
        is_typing INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 9. SMS History Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        phone_number TEXT,
        message TEXT,
        status TEXT,
        error_msg TEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 10. Global Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    // --- Initialization ---

    // Default Admin (User 1: Kush)
    $adminUser = $pdo->query("SELECT * FROM users WHERE username = 'kush'")->fetch();
    if (!$adminUser) {
        $passHash = password_hash('5877', PASSWORD_BCRYPT);
        $pinHash = password_hash('5877', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, display_name, password_hash, pin_hash, role, status, camouflage_theme) VALUES (?, ?, ?, ?, 'admin', 'active', 'coding')");
        $stmt->execute(['kush', 'admin', $passHash, $pinHash]);
        $adminId = $pdo->lastInsertId();

        // Default Privacy Settings for Admin
        $stmtPriv = $pdo->prepare("INSERT INTO user_privacy_settings (user_id) VALUES (?)");
        $stmtPriv->execute([$adminId]);
    }

    // Initialize Camouflage Library if empty
    $countCam = $pdo->query("SELECT COUNT(*) FROM camouflage_library")->fetchColumn();
    if ($countCam == 0) {
        $camData = [
            // Coding
            ['coding', 'prompt', 'Explain Python list comprehensions.'],
            ['coding', 'prompt', 'How does CSS Flexbox work?'],
            ['coding', 'response', 'List comprehensions provide a concise way to create lists in Python.'],
            ['coding', 'response', 'Flexbox is a one-dimensional layout method for arranging items.'],
            // Physics
            ['physics', 'prompt', 'What is quantum entanglement?'],
            ['physics', 'prompt', 'Explain the theory of general relativity.'],
            ['physics', 'response', 'Quantum entanglement is a phenomenon where particles become linked.'],
            ['physics', 'response', 'General relativity describes space-time as a curved fabric.'],
            // Games
            ['games', 'prompt', 'How to beat the first boss in Elden Ring?'],
            ['games', 'prompt', 'What is the best build for a mage in Skyrim?'],
            ['games', 'response', 'To beat Margit, use the spirit summons and stay aggressive.'],
            ['games', 'response', 'For a mage build, focus on Destruction and Restoration magic.'],
            // Beauty
            ['beauty', 'prompt', 'Best skincare routine for oily skin?'],
            ['beauty', 'prompt', 'How to apply winged eyeliner for beginners?'],
            ['beauty', 'response', 'Use a salicylic acid cleanser and a lightweight moisturizer.'],
            ['beauty', 'response', 'Start with a small line at the outer corner and connect it to your lash line.']
        ];
        $stmtCam = $pdo->prepare("INSERT INTO camouflage_library (theme, type, content) VALUES (?, ?, ?)");
        foreach ($camData as $row) {
            $stmtCam->execute($row);
        }
    }

    // Default Global Settings
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES 
        ('sms_api_key', '64v0VK2aq7AddQlE40Oh4T4oXpkgL3VBXxlc4W6l'),
        ('sms_default_msg', 'Since {time}, Gemini.sohojweb.com is waiting for your response.')
    ");

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
