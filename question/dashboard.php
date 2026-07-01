<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Question Paper Maker</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>

<div class="topbar">
    <div class="topbar-title">Hi, <?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></div>
    <button class="btn btn-ghost btn-sm" id="logoutBtn">Logout</button>
</div>

<div class="container">
    <div class="search-row">
        <input type="text" id="searchInput" placeholder="Search your question papers...">
    </div>

    <div id="paperList" class="paper-list"></div>
    <div id="emptyState" class="empty-state" hidden>
        <p>No question papers yet. Tap the + button to create your first one.</p>
    </div>
</div>

<button class="fab" id="createBtn" title="Create New Question">+</button>

<script>window.APP_BASE = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard.js"></script>
</body>
</html>
