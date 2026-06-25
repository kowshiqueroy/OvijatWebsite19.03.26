<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Notice Board';
$breadcrumbs = ['Communication' => 'templates.php', 'Notice Board' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(); // All authenticated users can view notices

$pdo = db();
$print_id = int_param('print_id', 0, $_GET);

// Handle print view
if ($print_id) {
    $stmt = $pdo->prepare('SELECT n.*, u.full_name as author_name FROM notices n JOIN users u ON u.id=n.created_by WHERE n.id = ?');
    $stmt->execute([$print_id]);
    $notice = $stmt->fetch();
    if (!$notice) {
        die("Notice not found.");
    }
    $school_name = setting('school_name', 'EMS');
    $school_addr = setting('school_address', 'Bangladesh');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notice — <?= e($notice['title']) ?></title>
<style>
  @page {
    size: A4;
    margin: 20mm;
  }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    line-height: 1.6;
    color: #334155;
    margin: 0;
    padding: 0;
  }
  .print-header {
    text-align: center;
    border-bottom: 3px double #0f172a;
    padding-bottom: 15px;
    margin-bottom: 30px;
  }
  .print-header h1 {
    margin: 0;
    font-size: 26px;
    color: #0f172a;
    text-transform: uppercase;
  }
  .print-header p {
    margin: 5px 0 0;
    font-size: 13px;
    color: #475569;
  }
  .notice-meta {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    font-size: 13px;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 8px;
    margin-bottom: 25px;
  }
  .notice-title {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    color: #0f172a;
    margin-bottom: 25px;
    text-decoration: underline;
  }
  .notice-body {
    font-size: 15px;
    text-align: justify;
    white-space: pre-line;
    min-height: 350px;
  }
  .notice-footer {
    margin-top: 60px;
    display: flex;
    justify-content: flex-end;
  }
  .signature-block {
    text-align: center;
    border-top: 1px solid #000;
    padding-top: 5px;
    min-width: 200px;
    font-size: 13px;
    font-weight: bold;
  }
  .btn-bar {
    background: #f1f5f9;
    padding: 10px;
    text-align: right;
    border-bottom: 1px solid #cbd5e1;
  }
  .print-btn {
    background: #1a56db;
    color: #fff;
    border: 0;
    padding: 6px 14px;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
  }
  @media print {
    .btn-bar { display: none !important; }
  }
</style>
</head>
<body>
<div class="btn-bar">
  <button class="print-btn" onclick="window.print()">Print A4 Notice</button>
</div>
<div style="padding: 10px;">
  <div class="print-header">
    <h1><?= e($school_name) ?></h1>
    <p><?= e($school_addr) ?></p>
  </div>
  
  <div class="notice-meta">
    <span>Ref: EMS/NOTICE/<?= $notice['id'] ?></span>
    <span>Date: <?= fmt_date($notice['publish_date'], 'd F Y') ?></span>
  </div>

  <div class="notice-title">
    NOTICE
  </div>

  <div class="notice-body">
    <h4>Subject: <?= e($notice['title']) ?></h4>
    <?= e($notice['content']) ?>
  </div>

  <div class="notice-footer">
    <div class="signature-block">
      <br><br>
      Authority / Principal<br>
      <?= e($school_name) ?>
    </div>
  </div>
</div>
</body>
</html>
    <?php
    exit;
}

// Handle POST actions: Save Notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('sms.send')) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_notice') {
        $title     = trim($_POST['title'] ?? '');
        $content   = trim($_POST['content'] ?? '');
        $audience  = $_POST['audience'] ?? 'all';
        $pub_date  = $_POST['publish_date'] ?: date('Y-m-d');
        $broadcast = isset($_POST['is_broadcast']) ? 1 : 0;
        
        if ($title && $content) {
            $pdo->prepare(
                'INSERT INTO notices (title, content, audience, created_by, publish_date, is_broadcast)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$title, $content, $audience, current_user_id(), $pub_date, $broadcast]);
            
            $id = $pdo->lastInsertId();
            log_activity('create_notice', 'communication', $id);
            flash('success', 'Notice published successfully.');
        }
        header('Location: notices.php');
        exit;
    }
}

// Fetch all active notices
$user_roles = $_SESSION['roles'] ?? [];
$isStaff = !in_array('student', $user_roles) && !in_array('guardian', $user_roles);
$audienceFilter = $isStaff ? "audience IN ('all', 'staff')" : "audience IN ('all', 'students')";

$allNotices = $pdo->query(
    "SELECT n.*, u.full_name as author_name 
     FROM notices n 
     JOIN users u ON u.id = n.created_by 
     WHERE $audienceFilter
     ORDER BY n.publish_date DESC, n.id DESC"
)->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-megaphone-fill me-2 text-primary"></i>Notice Board</h1>
  <?php if (has_permission('sms.send')): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#noticeModal"><i class="bi bi-plus-lg me-1"></i>Publish Notice</button>
  <?php endif; ?>
</div>

<div class="row g-3">
  <!-- Notices List -->
  <div class="col-12">
    <?php if (empty($allNotices)): ?>
      <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-megaphone"></i><p>No active notices published yet.</p></div></div></div>
    <?php else: ?>
      <?php foreach ($allNotices as $notice): ?>
        <div class="card mb-3 shadow-sm border-start border-3 border-primary">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <div>
                <span class="badge bg-light text-dark border me-2"><i class="bi bi-people-fill me-1"></i> Audience: <?= ucfirst(e($notice['audience'])) ?></span>
                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-person-circle me-1"></i> <?= e($notice['author_name']) ?></span>
              </div>
              <div class="text-muted small fw-600"><i class="bi bi-calendar-event me-1"></i> Published: <?= fmt_date($notice['publish_date']) ?></div>
            </div>
            
            <h4 class="fw-bold text-dark mb-3 mt-2"><?= e($notice['title']) ?></h4>
            <p class="text-dark small mb-3" style="white-space: pre-line; line-height: 1.6;"><?= e($notice['content']) ?></p>
            
            <div class="d-flex gap-2">
              <a href="?print_id=<?= $notice['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print A4 Notice
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Publish Notice Modal -->
<?php if (has_permission('sms.send')): ?>
<div class="modal fade" id="noticeModal" tabindex="-1" aria-labelledby="noticeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_notice">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-600" id="noticeModalLabel">Create & Publish Notice</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-600">Notice Title *</label>
            <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Eid Holidays Announcement" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Audience *</label>
              <select name="audience" class="form-select form-select-sm" required>
                <option value="all">Everyone (All Students & Staff)</option>
                <option value="students">Students / Guardians Only</option>
                <option value="staff">Staff / Faculty Only</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Publish Date *</label>
              <input type="date" name="publish_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Notice Description / Content *</label>
            <textarea name="content" rows="8" class="form-control form-control-sm" placeholder="Write full details of the notice here..." required></textarea>
          </div>
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input" name="is_broadcast" id="is_broadcast" value="1">
            <label class="form-check-label small fw-bold" for="is_broadcast">Broadcast via Dashboard Banner</label>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Publish Notice</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
