<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$paperId = (int)($_GET['id'] ?? 0);
if (!$paperId) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Print Preview — Question Paper Maker</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
<style id="dynamicPageStyle"></style>
</head>
<body class="view-body">

<div class="topbar no-print">
    <a href="<?= BASE_URL ?>/editor.php?id=<?= $paperId ?>" class="btn btn-ghost btn-sm">&larr; Back to Editor</a>
    <div class="topbar-title">Print Preview</div>
    <button class="btn btn-primary btn-sm" id="doPrintBtn">Print</button>
</div>

<div class="preview-scale-wrap">
    <div id="printRoot"></div>
</div>

<script>window.APP_BASE = <?= json_encode(BASE_URL) ?>; window.PAPER_ID = <?= (int)$paperId ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bangla.js"></script>
<script src="<?= BASE_URL ?>/assets/js/render.js"></script>
<script src="<?= BASE_URL ?>/assets/js/view.js"></script>
</body>
</html>
