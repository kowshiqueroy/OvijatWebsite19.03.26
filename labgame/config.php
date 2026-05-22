<?php
session_start();

define('DB_PATH', __DIR__ . '/database.sqlite');
define('SITE_NAME', 'LabGame');
define('ZONES', ['candyland', 'dinosaur', 'space']);
define('QUESTION_TYPES', ['math', 'word', 'pattern', 'random']);
define('RACE_LENGTH', 100);
define('BASE_SPEED', 2);
define('BOOST_SPEED', 5);
define('SLIME_SPEED', 1.2);
define('FIRE_SPEED', 0.8);
define('BOOST_DURATION', 5);
define('SLIME_DURATION', 3);
define('FIRE_DURATION', 4);
define('POWERUP_STREAK', 3);
define('QUESTION_TIME', 30);
define('MIN_PLAYERS', 2);

if (!file_exists(DB_PATH)) {
    initDB();
}

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA busy_timeout=5000');
    }
    return $db;
}

function initDB() {
    $db = getDB();
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            pin TEXT NOT NULL,
            avatar_seed TEXT NOT NULL,
            points INTEGER DEFAULT 0,
            races_won INTEGER DEFAULT 0,
            races_played INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            zone TEXT NOT NULL DEFAULT 'candyland',
            host_id INTEGER NOT NULL,
            question_type TEXT NOT NULL DEFAULT 'random',
            duration_minutes INTEGER NOT NULL DEFAULT 3,
            status TEXT DEFAULT 'waiting',
            max_players INTEGER DEFAULT 8,
            current_q_index INTEGER DEFAULT 0,
            started_at DATETIME,
            finished_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (host_id) REFERENCES students(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS race_participants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            position REAL DEFAULT 0,
            speed REAL DEFAULT 2,
            streak INTEGER DEFAULT 0,
            has_fire INTEGER DEFAULT 0,
            status TEXT DEFAULT 'racing',
            bubble_until DATETIME,
            boost_until DATETIME,
            slime_until DATETIME,
            fire_until DATETIME,
            total_correct INTEGER DEFAULT 0,
            total_wrong INTEGER DEFAULT 0,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES rooms(id),
            FOREIGN KEY (student_id) REFERENCES students(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            zone TEXT NOT NULL,
            difficulty TEXT NOT NULL,
            type TEXT NOT NULL,
            question_text TEXT NOT NULL,
            answers_json TEXT NOT NULL,
            correct_index INTEGER NOT NULL
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS room_questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            q_index INTEGER NOT NULL,
            FOREIGN KEY (room_id) REFERENCES rooms(id),
            FOREIGN KEY (question_id) REFERENCES questions(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS race_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            answer_index INTEGER,
            is_correct INTEGER DEFAULT 0,
            answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES rooms(id),
            FOREIGN KEY (student_id) REFERENCES students(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS powerups_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            from_student_id INTEGER NOT NULL,
            to_student_id INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT 'fire',
            landed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES rooms(id),
            FOREIGN KEY (from_student_id) REFERENCES students(id),
            FOREIGN KEY (to_student_id) REFERENCES students(id)
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            pin TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    seedQuestions($db);
    
    $adminHash = password_hash('1234', PASSWORD_DEFAULT);
    $db->prepare("INSERT OR IGNORE INTO admins (username, pin) VALUES (?, ?)")->execute(['teacher', $adminHash]);
}

function seedQuestions($db) {
    $count = $db->query("SELECT COUNT(*) as c FROM questions")->fetch()['c'];
    if ($count > 0) return;

    $questions = [];

    // ===== MATH QUESTIONS =====
    // Easy addition
    for ($i = 1; $i <= 8; $i++) {
        $a = rand(1, 5); $b = rand(1, 5); $c = $a + $b;
        $opts = [$c, $c+1, max(1,$c-1), $c+2];
        shuffle($opts);
        $ci = array_search($c, $opts);
        $questions[] = ['candyland', 'easy', 'math', "$a + $b = ?", json_encode($opts), $ci];
    }
    // Medium subtraction
    for ($i = 1; $i <= 8; $i++) {
        $a = rand(5, 15); $b = rand(1, 10); $c = $a - $b;
        $opts = [$c, $c+1, $c-1, $c+2];
        shuffle($opts);
        $ci = array_search($c, $opts);
        $questions[] = ['dinosaur', 'medium', 'math', "$a - $b = ?", json_encode($opts), $ci];
    }
    // Hard multiplication
    for ($i = 1; $i <= 8; $i++) {
        $a = rand(2, 9); $b = rand(2, 9); $c = $a * $b;
        $opts = [$c, $c+$a, $c+$b, $c+1];
        shuffle($opts);
        $ci = array_search($c, $opts);
        $questions[] = ['space', 'hard', 'math', "$a × $b = ?", json_encode($opts), $ci];
    }

    // ===== WORD QUESTIONS =====
    $all_words = [
        ['CAT', '🐱'], ['DOG', '🐶'], ['SUN', '☀️'], ['CAR', '🚗'],
        ['BALL', '⚽'], ['FISH', '🐟'], ['BIRD', '🐦'], ['TREE', '🌳'],
        ['BOOK', '📖'], ['STAR', '⭐'], ['MOON', '🌙'], ['FIRE', '🔥'],
        ['WATER', '💧'], ['HOUSE', '🏠'], ['APPLE', '🍎'], ['HEART', '❤️']
    ];
    $distractors = ['🐱','🐶','☀️','🚗','⚽','🐟','🐦','🌳','📖','⭐','🌙','🔥','💧','🏠','🍎','❤️','🌈','🦋','🍕','🎈'];
    foreach ($all_words as $w) {
        $pool = array_filter($distractors, fn($d) => $d !== $w[1]);
        $pool = array_values($pool);
        shuffle($pool);
        $opts = [$w[1], $pool[0], $pool[1], $pool[2]];
        shuffle($opts);
        $ci = array_search($w[1], $opts);
        $zone = ['candyland','dinosaur','space'][array_rand(['candyland','dinosaur','space'])];
        $questions[] = [$zone, 'easy', 'word', $w[0], json_encode($opts), $ci];
    }

    // ===== PATTERN / IQ QUESTIONS =====
    $patterns = [
        ['🔴 🔵 🔴 ?', ['🔴','🟢','🔵','🟡'], 0],
        ['🍎 🍊 🍎 ?', ['🍎','🍋','🍊','🍇'], 0],
        ['⬆️ ➡️ ⬆️ ?', ['⬆️','⬇️','➡️','⬅️'], 0],
        ['1 2 1 2 ?', ['1','3','2','4'], 2],
        ['🔵 🔵 🔴 🔵 🔵 ?', ['🔴','🔵','🟢','🟡'], 1],
        ['⭐ 🌙 ⭐ 🌙 ?', ['⭐','🌟','🌙','☀️'], 2],
        ['🟢 🟡 🟢 🟡 ?', ['🟢','🔴','🟡','🔵'], 2],
        ['🐱 🐶 🐱 🐶 ?', ['🐱','🐭','🐶','🐹'], 2],
        ['1 1 2 1 1 2 ?', ['1','2','3','4'], 1],
        ['🍕 🍔 🍕 🍔 ?', ['🍕','🍟','🍔','🌭'], 2],
        ['A B A B ?', ['A','C','B','D'], 2],
        ['🔴🔴🔵 🔴🔴🔵 ?', ['🔴🔴🔵','🔵🔵🔴','🔴🔵🔴','🔵🔴🔵'], 0],
    ];
    foreach ($patterns as $p) {
        $zone = ['candyland','dinosaur','space'][array_rand(['candyland','dinosaur','space'])];
        $questions[] = [$zone, 'medium', 'pattern', $p[0], json_encode($p[1]), $p[2]];
    }

    $stmt = $db->prepare("INSERT INTO questions (zone, difficulty, type, question_text, answers_json, correct_index) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questions as $q) {
        $stmt->execute($q);
    }
}
