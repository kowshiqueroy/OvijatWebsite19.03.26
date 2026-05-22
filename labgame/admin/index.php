<?php
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? 'login';
$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId && $action !== 'login') {
    $action = 'login';
}

$db = getDB();

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($pin, $admin['pin'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            header('Location: ?action=dashboard');
            exit;
        }
        $error = 'Wrong username or PIN.';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Teacher Admin - <?= SITE_NAME ?></title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body class="login-page">
        <div class="login-container">
            <div class="logo">
                <span class="logo-icon">👩‍🏫</span>
                <h1>Teacher Panel</h1>
            </div>
            <form method="POST" class="login-form">
                <div class="input-group">
                    <label>👤 Username</label>
                    <input type="text" name="username" required autocomplete="off" value="teacher">
                </div>
                <div class="input-group">
                    <label>🔢 PIN</label>
                    <input type="password" name="pin" required pattern="[0-9]{4}" maxlength="4" inputmode="numeric">
                </div>
                <button type="submit" class="btn btn-primary btn-large">🔓 Login</button>
                <?php if (isset($error)): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            </form>
            <div class="login-footer"><a href="../index.php" class="link-small">⬅ Back to Student Login</a></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['admin_id'], $_SESSION['admin_user']);
    header('Location: ?action=login');
    exit;
}

if ($action === 'dashboard' || $action === 'students') {
    $students = $db->query("SELECT * FROM students ORDER BY points DESC")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pin'])) {
        $sid = (int)$_POST['student_id'];
        $newPin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $hash = password_hash($newPin, PASSWORD_DEFAULT);
        $db->prepare("UPDATE students SET pin = ? WHERE id = ?")->execute([$hash, $sid]);
        $resetMsg = "PIN reset to $newPin for student #$sid";
        $students = $db->query("SELECT * FROM students ORDER BY points DESC")->fetchAll();
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Teacher Admin - <?= SITE_NAME ?></title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body class="admin-page">
        <nav class="admin-sidebar">
            <h2>👩‍🏫 Admin</h2>
            <a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
            <a href="?action=students" class="<?= $action === 'students' ? 'active' : '' ?>">👥 Students</a>
            <a href="?action=questions">❓ Questions</a>
            <a href="?action=export">📤 Export</a>
            <a href="?action=logout" style="margin-top:auto;color:#f44336;">🚪 Logout</a>
        </nav>
        <main class="admin-main">
            <h1><?= $action === 'dashboard' ? '📊 Dashboard' : '👥 Students' ?></h1>

            <?php if (isset($resetMsg)): ?>
                <div style="background:#4caf50;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:600;"><?= htmlspecialchars($resetMsg) ?></div>
            <?php endif; ?>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Avatar</th>
                        <th>Points</th>
                        <th>Wins</th>
                        <th>Played</th>
                        <th>Created</th>
                        <?php if ($action === 'students'): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><?= htmlspecialchars($s['username']) ?></td>
                        <td><img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= urlencode($s['avatar_seed']) ?>" style="width:32px;height:32px;border-radius:50%;background:#333;"></td>
                        <td>⭐ <?= (int)$s['points'] ?></td>
                        <td>🏆 <?= (int)$s['races_won'] ?></td>
                        <td>🎮 <?= (int)$s['races_played'] ?></td>
                        <td><?= $s['created_at'] ?></td>
                        <?php if ($action === 'students'): ?>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reset PIN for <?= htmlspecialchars($s['username']) ?>?')">
                                <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                <button type="submit" name="reset_pin" class="btn-reset-pin">🔄 Reset PIN</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'questions') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_question'])) {
            $zone = $_POST['zone'];
            $difficulty = $_POST['difficulty'];
            $type = $_POST['type'];
            $text = $_POST['question_text'];
            $answers = json_encode([$_POST['answer_0'], $_POST['answer_1'], $_POST['answer_2'], $_POST['answer_3']]);
            $correct = (int)$_POST['correct_index'];
            $stmt = $db->prepare("INSERT INTO questions (zone, difficulty, type, question_text, answers_json, correct_index) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$zone, $difficulty, $type, $text, $answers, $correct]);
        } elseif (isset($_POST['delete_question'])) {
            $db->prepare("DELETE FROM questions WHERE id = ?")->execute([(int)$_POST['question_id']]);
        }
    }

    $zoneFilter = $_GET['zone'] ?? '';
    $questions = $zoneFilter ? $db->prepare("SELECT * FROM questions WHERE zone = ? ORDER BY difficulty, type") : $db->query("SELECT * FROM questions ORDER BY zone, difficulty, type");
    if ($zoneFilter) $questions->execute([$zoneFilter]);
    $allQ = $questions->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Questions - <?= SITE_NAME ?></title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body class="admin-page">
        <nav class="admin-sidebar">
            <h2>👩‍🏫 Admin</h2>
            <a href="?action=dashboard">📊 Dashboard</a>
            <a href="?action=students">👥 Students</a>
            <a href="?action=questions" class="active">❓ Questions</a>
            <a href="?action=export">📤 Export</a>
            <a href="?action=logout" style="margin-top:auto;color:#f44336;">🚪 Logout</a>
        </nav>
        <main class="admin-main">
            <h1>❓ Question Bank</h1>

            <form method="GET" style="margin-bottom:20px;display:flex;gap:8px;align-items:center;">
                <label>Filter by Zone:</label>
                <select name="zone" onchange="this.form.submit()" style="padding:8px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                    <option value="">All</option>
                    <?php foreach (ZONES as $z): ?>
                        <option value="<?= $z ?>" <?= $zoneFilter === $z ? 'selected' : '' ?>><?= ucfirst($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <details style="margin-bottom:24px;background:rgba(255,255,255,0.05);border-radius:var(--radius);padding:16px;">
                <summary style="font-weight:700;cursor:pointer;font-size:16px;">➕ Add New Question</summary>
                <form method="POST" style="margin-top:16px;display:grid;gap:12px;grid-template-columns:1fr 1fr;">
                    <div class="input-group">
                        <label>Zone</label>
                        <select name="zone" required style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                            <?php foreach (ZONES as $z): ?>
                                <option value="<?= $z ?>"><?= ucfirst($z) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Difficulty</label>
                        <select name="difficulty" style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Type</label>
                        <select name="type" style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                            <option value="math">Math</option>
                            <option value="pattern">Pattern</option>
                            <option value="word">Word</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Correct Answer Index (0-3)</label>
                        <input type="number" name="correct_index" min="0" max="3" required style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                    </div>
                    <div class="input-group" style="grid-column:1/-1;">
                        <label>Question Text</label>
                        <input type="text" name="question_text" required style="width:100%;padding:10px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                    </div>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="input-group">
                        <label>Answer <?= chr(65 + $i) ?></label>
                        <input type="text" name="answer_<?= $i ?>" required style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                    </div>
                    <?php endfor; ?>
                    <button type="submit" name="add_question" class="btn btn-primary" style="grid-column:1/-1;">➕ Add Question</button>
                </form>
            </details>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zone</th>
                        <th>Difficulty</th>
                        <th>Type</th>
                        <th>Question</th>
                        <th>Answers</th>
                        <th>Correct</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allQ as $q): 
                        $answers = json_decode($q['answers_json'], true);
                    ?>
                    <tr>
                        <td><?= $q['id'] ?></td>
                        <td><span class="room-zone zone-<?= $q['zone'] ?>"><?= ucfirst($q['zone']) ?></span></td>
                        <td><?= ucfirst($q['difficulty']) ?></td>
                        <td><?= $q['type'] ?></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($q['question_text']) ?></td>
                        <td><?= implode(', ', $answers ?? ['?','?','?','?']) ?></td>
                        <td><?= $answers[$q['correct_index']] ?? '?' ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete question #<?= $q['id'] ?>?')">
                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                <button type="submit" name="delete_question" class="btn btn-small btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'export') {
    $students = $db->query("SELECT * FROM students ORDER BY points DESC")->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=labgame_students_' . date('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Username', 'Points', 'Races Won', 'Races Played', 'Created']);
    foreach ($students as $s) {
        fputcsv($out, [$s['id'], $s['username'], $s['points'], $s['races_won'], $s['races_played'], $s['created_at']]);
    }
    fclose($out);
    exit;
}

header('Location: ?action=dashboard');
