<?php
date_default_timezone_set('Asia/Dhaka');
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pin = $_POST['pin'] ?? '';
    
    if ($user === 'user1' || $user === 'user2') {
        $db = getUserDb($user);
        $stmt = $db->prepare("SELECT pin FROM users WHERE id = 1");
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($res && $res['pin'] === $pin) {
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user === 'user1' ? 1 : 2;
            $db->exec("UPDATE users SET is_unlocked = 1, last_active = CURRENT_TIMESTAMP WHERE id = 1");
            header('Location: aichat.php');
            exit;
        }
    }
    $error = "Invalid PIN or user selection.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gemini - Select User</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: #131314; 
            color: #fff; 
            font-family: 'Google Sans', sans-serif; 
            height: 100dvh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        .container { 
            width: 320px; 
            padding: 32px 24px; 
            background: #1e1f20; 
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4);
        }
        h2 { 
            text-align: center; 
            margin-bottom: 24px; 
            font-size: 28px;
            background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        select, input, button { 
            width: 100%; 
            padding: 14px 16px; 
            margin-bottom: 16px; 
            border-radius: 12px; 
            border: none; 
            font-size: 15px; 
            font-family: 'Google Sans', sans-serif;
        }
        select, input { 
            background: #28292a; 
            color: #fff; 
            outline: none;
        }
        select option { background: #28292a; }
        input::placeholder { color: #888; }
        button { 
            background: #4285f4; 
            color: #fff; 
            cursor: pointer; 
            font-weight: 500; 
            transition: background 0.2s;
        }
        button:hover { background: #3367d6; }
        .error { 
            color: #f44336; 
            font-size: 13px; 
            text-align: center; 
            margin-bottom: 16px; 
            padding: 8px;
            background: rgba(244,67,54,0.1);
            border-radius: 8px;
        }
        .hint {
            color: var(--gemini-dim);
            font-size: 12px;
            text-align: center;
            margin-top: 16px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Gemini</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <select name="user" required>
                <option value="">Select User</option>
                <option value="user1">User1 (Coding Topic)</option>
                <option value="user2">User2 (Science Topic)</option>
            </select>
            <input type="password" name="pin" placeholder="Enter 4-digit PIN" required maxlength="4" pattern="[0-9]{4}">
            <button type="submit">Unlock Chat</button>
        </form>
        <div class="hint">Databases auto-create. User1: 7785 | User2: 5877</div>
    </div>
</body>
</html>
