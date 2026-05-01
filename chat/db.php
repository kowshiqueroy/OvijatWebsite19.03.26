<?php
date_default_timezone_set('Asia/Dhaka');
define('DB_FILE', __DIR__ . '/chat.db');

const DEFAULT_EMOJIS = ['😎', '🚀', '🐱', '🐶', '🦊', '🦁', '🐯', '🐼', '🐨', '🐸', '🦄', '🍎', '🍕', '🎮', '🎸', '⚽', '💎', '🔥', '🌈', '👻'];

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT, display_name TEXT, pin TEXT DEFAULT '', theme TEXT DEFAULT 'blue', last_active INTEGER DEFAULT 0, avatar_emoji TEXT DEFAULT '', viewing_target TEXT DEFAULT '')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY, sender_id INTEGER, receiver_id INTEGER, message TEXT, original TEXT, type TEXT DEFAULT 'text', reply_to INTEGER DEFAULT 0, is_read INTEGER DEFAULT 0, delete_at INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS unlock (id INTEGER PRIMARY KEY, user_id INTEGER, chat_with INTEGER, expires_at INTEGER)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS typing (user_id INTEGER, chat_with INTEGER, expires_at INTEGER, PRIMARY KEY(user_id, chat_with))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS nicknames (user_id INTEGER, contact_id INTEGER, nickname TEXT, PRIMARY KEY(user_id, contact_id))");
        
        // Migrate schema
        try { $pdo->exec("ALTER TABLE users ADD COLUMN last_active INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar_emoji TEXT DEFAULT ''"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN viewing_target TEXT DEFAULT ''"); } catch (Exception $e) {}
    }
    return $pdo;
}

function updateLastActive($userId, $target = null) {
    $pdo = getDB();
    if ($target !== null) {
        $pdo->prepare("UPDATE users SET last_active = ?, viewing_target = ? WHERE id = ?")->execute([time(), $target, $userId]);
    } else {
        $pdo->prepare("UPDATE users SET last_active = ? WHERE id = ?")->execute([time(), $userId]);
    }
}

function getDetailedStatus($u, $meViewing = null) {
    $isOnline = (time() - $u['last_active'] < 60);
    if (!$isOnline) return "Offline (Last seen " . getStatusText($u['last_active']) . ")";
    
    if ($meViewing && $u['viewing_target'] === $meViewing) return "In this chat";
    if (!empty($u['viewing_target'])) return "In other chat";
    return "Online";
}

function getStatusText($lastActive) {
    if ($lastActive == 0) return "Never";
    $diff = time() - $lastActive;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    return date('M j', $lastActive);
}

function getNickname($userId, $contactId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT nickname FROM nicknames WHERE user_id = ? AND contact_id = ?");
    $stmt->execute([$userId, $contactId]);
    $r = $stmt->fetch();
    return $r ? $r['nickname'] : null;
}

function setNickname($userId, $contactId, $nickname) {
    $pdo = getDB();
    $pdo->prepare("INSERT OR REPLACE INTO nicknames (user_id, contact_id, nickname) VALUES (?, ?, ?)")->execute([$userId, $contactId, $nickname]);
}

function getConnectedUsers($userId) {
    $pdo = getDB();
    $userId = (int)$userId;
    
    // Get all users who have exchanged messages with current user (either direction)
    $stmt = $pdo->prepare("
        SELECT DISTINCT other_id FROM (
            SELECT receiver_id as other_id FROM messages WHERE sender_id = ?
            UNION
            SELECT sender_id as other_id FROM messages WHERE receiver_id = ?
        )
    ");
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll();
    
    if (empty($rows)) {
        return [];
    }
    
    $userIds = array_column($rows, 'other_id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    
    // Get users with their last message info
    $sql = "SELECT id, username, display_name, avatar_emoji, last_active, viewing_target FROM users WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);
    $users = $stmt->fetchAll();
    
    foreach ($users as &$u) {
        $uid = $u['id'];
        
        // Get last message between these two users
        $msgStmt = $pdo->prepare("
            SELECT message, type, sender_id, is_read, created_at 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY id DESC LIMIT 1
        ");
        $msgStmt->execute([$userId, $uid, $uid, $userId]);
        $msg = $msgStmt->fetch();
        
        if ($msg) {
            $u['last_msg'] = $msg['message'];
            $u['last_type'] = $msg['type'];
            $u['last_sender'] = $msg['sender_id'];
            $u['last_read'] = $msg['is_read'];
            $u['last_msg_time'] = $msg['created_at'];
        }
        
        // Get unread count
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $unreadStmt->execute([$uid, $userId]);
        $u['unread_count'] = $unreadStmt->fetchColumn();
        
        $u['display_name'] = getNickname($userId, $uid) ?: $u['display_name'];
    }
    
    // Sort by last_msg_time descending
    usort($users, function($a, $b) {
        return strcmp($b['last_msg_time'] ?? '', $a['last_msg_time'] ?? '');
    });
    
    return $users;
}

function searchNewUsers($userId, $query) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, display_name, avatar_emoji FROM users WHERE (username LIKE ? OR display_name LIKE ?) AND id != ? LIMIT 10");
    $stmt->execute(["%$query%", "%$query%", $userId]);
    return $stmt->fetchAll();
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($s) { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
function isLoggedIn() { return isset($_SESSION['user_id']); }

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function getUserById($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAllUsers($excludeId = null) {
    $pdo = getDB();
    $sql = $excludeId ? "SELECT id, username, display_name, avatar_emoji FROM users WHERE id != ?" : "SELECT id, username, display_name, avatar_emoji FROM users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($excludeId ? [$excludeId] : []);
    return $stmt->fetchAll();
}

function getUserByUsername($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function createUser($username, $password, $displayName) {
    $pdo = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $emoji = DEFAULT_EMOJIS[array_rand(DEFAULT_EMOJIS)];
    $stmt = $pdo->prepare("INSERT INTO users (username, password, display_name, avatar_emoji) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hash, $displayName, $emoji]);
    return $pdo->lastInsertId();
}

function verifyPassword($user, $password) { return password_verify($password, $user['password']); }

function updateUserPin($userId, $pin) {
    $pdo = getDB();
    $hash = empty($pin) ? '' : password_hash($pin, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET pin = ? WHERE id = ?");
    $stmt->execute([$hash, $userId]);
}

function getPin($userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT pin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    return $r ? $r['pin'] : '';
}

function getMessages($userId, $chatWithId) {
    $pdo = getDB();
    $now = time();
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND (delete_at = 0 OR delete_at > ?) ORDER BY id DESC LIMIT 200");
    $stmt->execute([$userId, $chatWithId, $chatWithId, $userId, $now]);
    return array_reverse($stmt->fetchAll());
}

function markMessagesAsRead($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")->execute([$chatWithId, $userId]);
}

function markMessageViewed($msgId, $viewerId) {
    $pdo = getDB();
    $pdo->prepare("UPDATE messages SET delete_at = ? WHERE id = ? AND delete_at = 0")->execute([time() + 30, $msgId]);
}

function cleanupExpiredMessages() {
    $pdo = getDB();
    $now = time();
    $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE delete_at > 0 AND delete_at <= ?");
    $stmt->execute([$now]);
    $toDelete = $stmt->fetchAll();
    foreach ($toDelete as $m) {
        if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
    }
    $pdo->prepare("DELETE FROM messages WHERE delete_at > 0 AND delete_at <= ?")->execute([$now]);
}

function saveMessage($senderId, $receiverId, $message, $type = 'text', $replyTo = 0) {
    $pdo = getDB();
    $fake = ($type === 'text') ? camouflage($message) : $message;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, original, type, reply_to, delete_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$senderId, $receiverId, $fake, $message, $type, $replyTo, $now]);
    $lastId = $pdo->lastInsertId();
    cleanupOldMessages($senderId, $receiverId);
    return $lastId;
}

function cleanupOldMessages($u1, $u2) {
    $pdo = getDB();
    // Only cleanup messages that have been viewed (delete_at > 0)
    $stmt = $pdo->prepare("SELECT id, message, type FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND delete_at > 0 ORDER BY id DESC LIMIT 1000 OFFSET 200");
    $stmt->execute([$u1, $u2, $u2, $u1]);
    $toDelete = $stmt->fetchAll();
    if ($toDelete) {
        $ids = [];
        foreach ($toDelete as $m) {
            $ids[] = $m['id'];
            if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)")->execute($ids);
    }
}

function deleteMyMessages($userId, $imagesOnly = false) {
    $pdo = getDB();
    if ($imagesOnly) {
        $stmt = $pdo->prepare("SELECT message FROM messages WHERE sender_id = ? AND type = 'image'");
        $stmt->execute([$userId]);
        $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($imgs as $img) if (file_exists($img)) @unlink($img);
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? AND type = 'image'")->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT message FROM messages WHERE sender_id = ? AND type = 'image'");
        $stmt->execute([$userId]);
        $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($imgs as $img) if (file_exists($img)) @unlink($img);
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$userId]);
    }
}

function cleanupGlobalOldMessages() {
    $pdo = getDB();
    $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    // Only delete messages that were viewed AND are older than 7 days
    $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE type = 'image' AND created_at < ? AND delete_at > 0");
    $stmt->execute([$sevenDaysAgo]);
    $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($imgs as $img) if (file_exists($img)) @unlink($img);
    
    $pdo->prepare("DELETE FROM messages WHERE created_at < ? AND delete_at > 0")->execute([$sevenDaysAgo]);
}

function camouflage($msg) {
    $phrases = [
        // Short casual
        "Hi!",
        "Hello there!",
        "Hey!",
        "Kemon acho?",
        "Ki khubi?",
        "Holar moin",
        "Oki",
        "Nai",
        "Haan",
        "Aar?",
        "OK",
        "Sure",
        "Done",
        "Rikh",
        
        // Friendly casual
        "Koi na, all good!",
        "Apnar kache aktu wait korte hobe",
        "Onek din por dekha holo!",
        "valo toh",
        "kichu noi",
        "Sotti pawar moddhe chotur baat",
        "Apni kemon feel koren?",
        "Ekhono kintu valo",
        "Ato bolte paren na",
        
        // Misspelled/casual
        "Vaat ki bollam",
        "Ki korcho",
        "Koi re vai",
        "Apnakar k城里",
        "Bhabish",
        "Chai na",
        "Kheyal ache",
        "Onno kothin bolte paren",
        
        // Banglish mixed
        "Bhai, ki news?",
        "Apni kothay?",
        "Dekhi next week e",
        "Call koren",
        "Msg korechen",
        "Wait kore",
        "Cholo niye jai",
        "Kome jayga lage",
        "Ekta kaaj baaki ache",
        
        // Bangla
        "কেমন আছেন?",
        "আপনি কোথায়?",
        "চলুন দেখি",
        "একটু অপেক্ষা করুন",
        "আছে সব ঠিক আছে",
        "কোনো কথা নেই",
        "ভালো আছি",
        "এখনো ব্যস্ত আছি",
        
        // Longer sentences
        "Apnar message ta eka pore",
        "Onek din e kono update paaya nai",
        "Apni onek days pore react koren nai",
        "Kichu specific jante cai",
        "Eki related question",
        "Apnake onek thank you",
        "Taile alada alada",
        "Jodi kokhono lage bolun",
        
        // Random chatty
        "Aaj keki monday!",
        "Keno eta koro",
        "Kichu to bujhi nai",
        "Eta ki onnano vabe implement kora jai",
        "Ta ei time e difficult lagbe",
        "Onek easy na eta",
        "Ta ki abar kora jabe",
        "Sotti bolle ki hoy",
        
        // Tech/chat related
        "Typing error hoy geche",
        "Network slow",
        "Internet nai",
        "Server busy",
        "Loading hocche",
        "Refresh kore dekho",
        "Cache clear koro",
        "App restart koro",
        
        // Generic
        "Ok understood",
        "Let me think",
        "Will get back",
        "Sounds good",
        "Noted",
        "Thanks",
        "OK done",
        "Let me check",
        
        // OFFICIAL / PROFESSIONAL TALK
        "Please refer to the attached document for details.",
        "This is to inform you that your request has been received.",
        "Kindly adhere to the deadline mentioned above.",
        "We request you to submit the required documents ASAP.",
        "Your application is under review.",
        "Please confirm your availability for the meeting.",
        "Further correspondence will be done via official email.",
        "This is an automated response from the system.",
        "Please contact the admin for further assistance.",
        "Your complaint has been registered.",
        "We appreciate your patience in this matter.",
        "Please note that this is a system-generated message.",
        "Your request has been forwarded to the concerned department.",
        "Further updates will be shared via email.",
        "Please adhere to the terms and conditions.",
        
        // TEACHER / CLASSROOM TALK
        "Homework is due next class.",
        "Please complete the reading before next session.",
        "Any questions so far?",
        "Who can answer this?",
        "Let me explain again.",
        "Open your books to page",
        "This is in your syllabus.",
        "Test exam is next week.",
        "Submit your assignment on time.",
        "Pay attention in class.",
        "Did you understand?",
        "Let me give you an example.",
        "Note this down - it will be in the exam.",
        "You should practice more.",
        "Let me check your homework.",
        "Good progress! Keep it up!",
        "Need more practice with this topic.",
        "Read the chapter before class.",
        "Who didn't submit the assignment?",
        "Let's move to the next topic.",
        "Can you explain this in your own words?",
        "I want everyone to try this.",
        "Raise your hand if you have questions.",
        "Test results will be shared tomorrow.",
        
        // IT / TECH SUPPORT TALK
        "Have you tried restarting the device?",
        "Please clear your browser cache.",
        "Which browser are you using?",
        "Try using Chrome or Firefox.",
        "Your internet connection seems slow.",
        "Is the website loading for you?",
        "Please login again with correct credentials.",
        "Your session has expired.",
        "Password should be at least 8 characters.",
        "Two-factor authentication is enabled.",
        "Please verify your email address.",
        "Account locked due to multiple failed attempts.",
        "Contact IT support for password reset.",
        "System maintenance scheduled tonight.",
        "Please update your software.",
        "Antivirus scan is recommended.",
        "Your firewall might be blocking.",
        "Check your network settings.",
        "DNS server not responding.",
        "Port 443 is blocked.",
        "API endpoint returning 404.",
        "Database connection timeout.",
        "Server not found - DNS issue.",
        "Please check your proxy settings.",
        "SSL certificate expired.",
        "JWT token validation failed.",
        "Memory usage at 100%.",
        "Disk space running low.",
        
        // OFFICE / WORKPLACE
        "Can you join the meeting now?",
        "Please send the report by EOD.",
        "Let's schedule a call.",
        "CC the manager in the email.",
        "Deadline is end of this week.",
        "Please confirm your attendance.",
        "Following up on my previous email.",
        "Need your approval for this.",
        "Please review and revert.",
        "As discussed in the meeting,",
        "Action items are attached.",
        "Please prioritize this task.",
        "Will update you after the call.",
        "Let's sync up tomorrow.",
        "Please block your calendar.",
        
        // COLLEGE / STUDENT TALK
        "Assignment eta koto?",
        "Test er date te ki?",
        "Lecture e attendance check hoe?",
        "Marks eta ki公布 hisheb?",
        "Group project e ki korte hobe?",
        "Library e book pai?",
        "Cafeteria e khabar dibe?",
        "Bus eta ki somoy?",
        "Semester final koto?",
        "Result ki release hoyeche?",
        "Roll number bhul geche.",
        "Certificate collection time.",
        "Hostel einojentication baki.",
        "Practical exam kothay?",
        "Tution fee eta ki?",
        "Seminar e attend korbo?",
        "Club election koto?",
        "Fest registration open.",
        "Library book return deadline.",
        "GPA calculate koro.",
        "Course registration korte hobe.",
        "Hall ticket download koro.",
        "Exam routine dekho.",
        "Class routine change hoyeche.",
        "Attendance 75% lagbe.",
        
        // FRIENDS BANGLA CASUAL - 50 MORE
        "Dekhbi kothay?",
        "Khelbo ajke?",
        "Cricket khelbo?",
        "Cinema dekhte jabi?",
        "Coffee shop e jai?",
        "Lunch khete jai?",
        "Shopping korbo?",
        "Party kothay?",
        "Birthday party e ashben?",
        "Tui ki korbe?",
        "Vaat ki bolte paren?",
        "Onek din pore dekhlam",
        "Ki khana dibe?",
        "Bazar e jete paren?",
        "Gari ekhane ashche?",
        "Phone e call koro",
        "WhatsApp e msg dao",
        "Photo dekho ei",
        "Video ekhane dekho",
        "Location pathao",
        "Koi ajke free?",
        "Cholo bar-e",
        "Cafe-e jacchi",
        "Food order korbo?",
        "Pizza chai?",
        "Burger khabo?",
        "Singara khete jabi?",
        "Cinema ticket ki?",
        "Movie er time ki?",
        "Dekho ei new song",
        "Ei video viral hoe geche!",
        "Tui ei game khela?",
        "Football match dekhte jabo?",
        "Cricket world cup dekho",
        "Tennis khelte jabi?",
        "Badminton khelte chai",
        "Swimming pool jabo?",
        "Gym e jete paren?",
        "Morning walk korbo?",
        "Jogging e ascho?",
        "Cycling korbo?",
        "Turi kothay gia?",
        "Bajar e jete jabo?",
        "Shop-e jai?",
        "Mall-e jacchi?",
        "Market-e ki khobor?",
        "Haat e ki ki kinbo?",
        "Mobile ki kinbo?",
        "Laptop change korbo?",
        "Headphone lagbe",
        "Charger dao",
        "Cable ki ase?",
        "Cover lagbe",
        "Case dao",
        "Screen protector dao",
        "Earbuds chai",
        "Speaker lagbe",
        "Charger change hbe",
        "Powerbank dao",
"Cable dao",
        
        // MORE OFFICE BANGlish 50
        "Boss, onek urgent",
        "Sir, ki ki?",
        "Meeting e attend korben?",
        "Report pathao please",
        "EOD te deliver hobe",
        "Client ke inform koro",
        "Team ke update dao",
        "Presentation ready?",
        "Slide prepare koro",
        "Project timeline ki?",
        "Deadline miss hoe geche",
        "Client meeting cancel",
        "Budget approval lagbe",
        "Expense claim submit korbo",
        "Leave apply korchi",
        "WFH apply korchi",
        "Office Chowr",
        "HR te info dao",
        "Manager ke cc kor",
        "Email draft kori",
        "Follow up email pathao",
        "Meeting minutes share kor",
        "Action plan propose kor",
        "Task assign koro",
        "Progress update chai",
        "Deadline extend chai",
        "Resource allocate koro",
        "Team meeting call kor",
        "Zoom link pathao",
        "Google meet e jao",
        "Laptop issue hoeche",
        "Wifi connect hoi nai",
        "VPN connect hoi nai",
        "Printer not working",
        "Desktop slow",
        "Software install lagbe",
        "Access denied hoeche",
        "Password reset koro",
        "Account unlock koro",
        "Server down",
        "Backup restore koro",
        "File share kor",
        "Document review koro",
        "Proposal submit kor",
        "Tender submit kor",
        "Contract sign koro",
        "Payment release chai",
        "Invoice send kor",
        "Receipt issue koro",
        "Tax deduction confuse",
        "PF processing",
        "Salary credited?",
        "Appraisal time",
        "Promotion process",
        
        // FRIENDS BANGlish CONVO 50
        "Bhai, kothay ghora bhai?",
        "Ajke ki korbo?",
        "Cinema dekhteo lagbe?",
        "Tui ki ajke free?",
        "Phone e call korechi",
        "Msg korchi, reply dao",
        "Photo pathao",
        "Video call e dekho",
        "Voice note e sun",
        "Location share kor",
        "Audio call koro",
        "Profile photo change hoyeche",
        "Story share kor",
        "Post e like dao",
        "Comment e reply dao",
        "Share kor",
        "Tag kor",
        "Mention kor",
        "DM e bol",
        "Story view kor",
        "Reels share kor",
        "Live e ascho?",
        "Emoji react dao",
        "Heart react dao",
        "Fire react dao",
        "Haha reaction",
        "Sad reaction",
        "Wow reaction",
        "Profile view kor",
        "Last seen ki?",
        "Online ki?",
        "Typing dekhai",
        "Delivered hoyeche",
        "Seen hoyeche",
        "Online ascho",
        "Active",
        "Away",
        "Busy",
        
        // FAMILY BANGLA CONVO 50
        "Ma kemon ache?",
        "Baba kemon ache?",
        "Bhai ki kore?",
        "Bon ke phone koro",
        "Chele ki koren?",
        "Meye ki koren?",
        "Bou er kache jai",
        "Naker phone kor",
        "Maw er kache geche",
        "Baba er office e phone hbe",
        "Papa ke phone kor",
        "Mama ke phone kor",
        "Khalar phone number dao",
        "Address ta pathao",
        "Ghar er kotha bol",
        "Family program e ashben?",
        "Biyer tari ki?",
        "Bou pakaite lagbe",
        "Ghori er biye ki?",
        "Baba ma ke anbo",
        "S Bari jacchi",
        "Eid e dekha hobe",
        "Pujo e Kolkata jacchi",
        "Homecoming e aschi",
        "Holiday ki?",
        "Picnic planning",
        "Trip ki rakhbo?",
        "Rail ticket book",
        "Bus eta available?",
        "Flight price ki?",
        "Hotel book korbo?",
        "Package include ki?",
 
    
    // CRICKET TALK 50
    "Match dekhtese?",
    "Ki score?",
    "Winner ki?",
    "Captain ke?",
    "Opponent team ki?",
    "Over ki chara?",
    "Run rate ki?",
    "Target ki?",
    "Wicket geselo",
    "Boundary marse",
    "Sixer marse",
    "Four marse",
    "Catch out geselo",
    "Run out",
    "LBW appeal",
    "No ball marse",
    "Wide ball",
    "Dead ball",
    "Powerplay over",
    "Death over",
    "Middle overs",
    "Spin bowling",
    "Fast bowling",
    "pace lagbe",
    "Bouncer throw",
    "Yorker throw",
    "Full toss",
    "Googly ball",
    "Doosra ball",
    "Leg spin",
    "Off spin",
    "Straight drive",
    "Cover drive",
    "On drive",
    "Square cut",
    "Pull shot",
    "Hook shot",
    "Sweep shot",
    "Leaves shot",
    "Defensive shots",
    "Attacking shots",
    "T20 match",
    "One day match",
    "Test match",
    "IPL match dekhtese",
    "BPL match",
    "World cup final",
    "Asia cup" ,
    "Tournament winner",
    "Runner up",
    "Man of the match",
    "Player of the series",
    "Sixer record",
    "Century marse",
    "Half century",
    "Double century",
    "Triple century",
    "Maiden over",
    "Hat trick",
    "Golden duck",
    "Mankaded",

    // BD CRICKET TEAM 50
    "Bangladesh team ki khelbe?",
    " Tigers jegeche?",
    "B Tigers score ki?",
    "Captain Shakib",
    "Tamim Iqbal",
    "Mahmudullah Riyad",
    "Mushfiqur Rahim",
    "Liton Das",
    "Afif Hossain",
    "Mehidy Hasan",
    "Mustafizur Rahman",
    "Taskin Ahmed",
    " Shoriful Islam",
    "Nasum Ahmed",
    "Mahfuz Islam",
    "Pacer bowling",
    "Spiner bowling",
    "Batting order",
    "Opener fast?",
    "Middle order strong?",
    "Allrounder ase?",
    "Match win",
    "Series win",
    "Test jeeteche?",
    "ODI jeeteche?",
    "T20 jeeteche?",
    "World Cup preparation",
    "Asia Cup playing",
    "Champions Trophy",
    "T20 World Cup",
    "Home series",
    "Away tour",
    "Zimbabwe tour",
    "West Indies tour",
    "New Zealand tour",
    "Australia tour",
    "England tour",
    "India tour",
    "Sri Lanka tour",
    "Afghanistan match",
    "Ireland match",
    "Netherlands match",
    "Scotland match",
    "Practice match",
    "Warm up match",
    "Fitness condition",
    "Injury update",
    "Form ki kemon?",
    "Confidence high?",
    "Tigers pride",
    "Jai Bangla",

    // NEW JOB TALK 50
    "New job e join korchi",
    " Joining date ki?",
    "Office e first day",
    "Team e introduced",
    "HR meeting scheduled",
    "ID card banse",
    "Laptop diyeche",
    "Email setup hoeche",
    "System access den",
    "Login credentials pai nai",
    "Orientation program",
    "Training session",
    "Induction complete",
    "Buddy assigned",
    "Manager introduced",
    "Team lead introduced",
    "Colleagues meet korchi",
    "Workspace assigned",
    "Desk clean korchi",
    "Cabin e boschi",
    "Office tour neye",
    "Canteen e khao",
    "Break room e gia",
    "Parking facility",
    "Transport available?",
    "Shift timing ki?",
    "Working hours",
    "Hybrid model?",
    "WFH allowed?",
    "Office e report korte hobe?",
    "Dress code ki?",
    "Casual Friday?",
    "Jersey day?",
    "Team lunch plan",
    "Welcome lunch",
    "Farewell lunch",
    "Appraisal cycle",
    "Promotion policy",
    "Increment timeline",
    "Bonus structure",
    "Leave policy",
    "Sick leave?",
    "Casual leave?",
    "Annual leave?",
    "Parental leave?",
    "Working from home",
    "Office days",
    "Remote days",
    "Flexible hours",
    "Probation period",
    "Confirmation date?",
    "Contract signed"
    ];
    return $phrases[array_rand($phrases)];
}

function isChatUnlocked($userId, $chatWithId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT expires_at FROM unlock WHERE user_id = ? AND chat_with = ?");
    $stmt->execute([$userId, $chatWithId]);
    $r = $stmt->fetch();
    return $r && $r['expires_at'] > time();
}

function unlockChat($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM unlock WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
    $pdo->prepare("INSERT INTO unlock (user_id, chat_with, expires_at) VALUES (?, ?, ?)")->execute([$userId, $chatWithId, time() + 300]);
}

function lockChat($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM unlock WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
}

function setTyping($userId, $chatWithId) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM typing WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
    $pdo->prepare("INSERT INTO typing (user_id, chat_with, expires_at) VALUES (?, ?, ?)")->execute([$userId, $chatWithId, time() + 3]);
}

function getTypingStatus($userId, $chatWithId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT expires_at FROM typing WHERE user_id = ? AND chat_with = ?");
    $stmt->execute([$chatWithId, $userId]);
    $r = $stmt->fetch();
    return $r && $r['expires_at'] > time();
}

function isPinValid($userId, $enteredPin) {
    $storedPin = getPin($userId);
    if (empty($storedPin)) return empty($enteredPin);
    return password_verify($enteredPin, $storedPin);
}

function vanishChats($userId, $chatWithId) {
    $pdo = getDB();
    if ($chatWithId > 0) {
        $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $stmt->execute([$userId, $chatWithId, $chatWithId, $userId]);
        $msgs = $stmt->fetchAll();
        foreach ($msgs as $m) if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
        $pdo->prepare("DELETE FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))")->execute([$userId, $chatWithId, $chatWithId, $userId]);
        $pdo->prepare("DELETE FROM unlock WHERE user_id = ? AND chat_with = ?")->execute([$userId, $chatWithId]);
    } else {
        $stmt = $pdo->prepare("SELECT message, type FROM messages WHERE sender_id = ? OR receiver_id = ?");
        $stmt->execute([$userId, $userId]);
        $msgs = $stmt->fetchAll();
        foreach ($msgs as $m) if ($m['type'] === 'image' && file_exists($m['message'])) @unlink($m['message']);
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]);
        $pdo->prepare("DELETE FROM unlock WHERE user_id = ?")->execute([$userId]);
    }
}
