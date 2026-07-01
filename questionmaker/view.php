<?php
require_once 'includes/db.php';
$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(404); die('Invalid link'); }

$db   = getDB();
$stmt = $db->prepare('SELECT sp.*, qp.paper_json, qp.title FROM shared_papers sp
    JOIN question_papers qp ON qp.id=sp.paper_id
    WHERE sp.share_token=?');
$stmt->execute([$token]);
$shared = $stmt->fetch();

if (!$shared) { http_response_code(404); die('Link not found or expired.'); }
if ($shared['expires_at'] && strtotime($shared['expires_at']) < time()) { http_response_code(410); die('This link has expired.'); }

$paper      = json_decode($shared['paper_json'], true) ?? [];
$showAns    = (bool)$shared['show_answers'];
$title      = $shared['title'];


?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — QuestionMaker Pro</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/print.css">
<style>
body{background:#444;padding:30px 20px;}
.view-toolbar{background:var(--bg-1);border:1px solid var(--border);border-radius:12px;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;max-width:850px;margin-left:auto;margin-right:auto;}
.view-toolbar h2{margin:0;font-size:16px;color:var(--text-0);}
</style>
</head>
<body>
<div class="view-toolbar" style="background:var(--bg-1);">
  <h2>📄 <?= htmlspecialchars($title) ?></h2>
  <div style="display:flex;gap:8px;">
    <?php if ($showAns): ?><span style="font-size:12px;color:var(--success);border:1px solid var(--success);border-radius:6px;padding:3px 8px;">Answer Key Included</span><?php endif; ?>
    <button class="btn btn-primary btn-sm" onclick="window.print()">🖨️ Print</button>
  </div>
</div>
<div id="printWrap"></div>
<script>
const paper    = <?= json_encode($paper) ?>;
const showAns  = <?= $showAns ? 'true' : 'false' ?>;
</script>
<script src="assets/js/bangla.js?v=<?= time() ?>"></script>
<script src="assets/js/render.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('printWrap').innerHTML = Render.buildPrintHtml(paper, showAns);
});
</script>
</body>
</html>
