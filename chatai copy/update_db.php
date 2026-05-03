<?php
$pdo = new PDO("sqlite:" . __DIR__ . "/chat_database.sq3");
$stmt = $pdo->prepare("UPDATE settings SET value = 'Since {time}, Gemini.sohojweb.com is waiting for your response.' WHERE key = 'sms_default_msg'");
$stmt->execute();
echo "Updated " . $stmt->rowCount() . " rows";
