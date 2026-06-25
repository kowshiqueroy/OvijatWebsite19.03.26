<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Manage Feedback & Reports';
$breadcrumbs = ['Communication' => 'templates.php', 'Feedback Management' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['sms.send']); // Allow Admins/Managers (same as SMS broadcast permission)

$pdo    = db();
$status = $_GET['status'] ?? '';
$type   = $_GET['type'] ?? '';

// Handle POST: Update status and add remarks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'resolve') {
        $fid          = int_param('id', 0, $_POST);
        $new_status   = $_POST['status'] ?? 'reviewed';
        $remarks      = trim($_POST['action_taken'] ?? '');

        if ($fid) {
            $pdo->prepare('UPDATE feedback_reports SET status = ?, action_taken = ? WHERE id = ?')
                ->execute([$new_status, $remarks, $fid]);
            log_activity('resolve_feedback', 'communication', $fid, '', "Status:$new_status");
            flash('success', 'Feedback status updated.');
        }
        header("Location: feedback_admin.php?status=$status&type=$type");
        exit;
    }
}

// Build query filters
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'fr.status = :status'; $params[':status'] = $status; }
if ($type)   { $where[] = 'fr.feedback_type = :type'; $params[':type'] = $type; }
$whereStr = implode(' AND ', $where);

$reports = $pdo->prepare(
    "SELECT fr.*, u.username, u.full_name as reporter_name
     FROM feedback_reports fr
     LEFT JOIN users u ON u.id = fr.user_id
     WHERE $whereStr
     ORDER BY fr.id DESC"
);
$reports->execute($params);
$reports = $reports->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-shield-exclamation me-2 text-primary"></i>Feedback & Report Registry</h1>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-600">Status</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Statuses —</option>
          <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
          <option value="reviewed" <?= $status === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
          <option value="action_taken" <?= $status === 'action_taken' ? 'selected' : '' ?>>Action Taken</option>
          <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-600">Feedback Type</label>
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Types —</option>
          <option value="suggestion" <?= $type === 'suggestion' ? 'selected' : '' ?>>💡 Suggestions</option>
          <option value="problem" <?= $type === 'problem' ? 'selected' : '' ?>>⚠️ Problems</option>
          <option value="asking" <?= $type === 'asking' ? 'selected' : '' ?>>❓ Askings</option>
          <option value="incident" <?= $type === 'incident' ? 'selected' : '' ?>>📢 Incidents</option>
        </select>
      </div>
    </form>
  </div>
</div>

<!-- Reports List -->
<div class="row g-3">
  <div class="col-12">
    <?php if (empty($reports)): ?>
      <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-chat-left-dots"></i><p>No feedback reports matching your criteria.</p></div></div></div>
    <?php else: ?>
      <?php foreach ($reports as $row): 
        $badge = match($row['status']) {
            'submitted'    => 'bg-secondary',
            'reviewed'     => 'bg-info',
            'action_taken' => 'bg-success',
            'archived'     => 'bg-dark',
            default        => 'bg-light text-dark'
        };
        $type_icon = match($row['feedback_type']) {
            'suggestion' => '💡 Suggestion',
            'problem'    => '⚠️ Problem',
            'asking'     => '❓ Asking',
            'incident'   => '📢 Incident',
            default      => 'Feedback'
        };
      ?>
        <div class="card mb-3 shadow-sm border-start border-3 <?= $row['status'] === 'submitted' ? 'border-primary' : 'border-secondary' ?>">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
              <div>
                <span class="badge bg-light text-dark border me-2"><?= $type_icon ?></span>
                <span class="badge bg-light text-dark border me-2">Reporter: <?= ucfirst(e($row['reporter_role'])) ?></span>
                <?php if ($row['is_anonymous']): ?>
                  <span class="badge bg-danger-subtle text-danger"><i class="bi bi-eye-slash-fill me-1"></i> Anonymous</span>
                <?php else: ?>
                  <span class="badge bg-primary-subtle text-primary"><i class="bi bi-person-fill me-1"></i> <?= e($row['reporter_name']) ?> (<?= e($row['username']) ?>)</span>
                <?php endif; ?>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge <?= $badge ?> px-3 py-1"><?= ucfirst(e($row['status'])) ?></span>
                <span class="text-muted small"><?= fmt_date($row['created_at'], 'd M Y, H:i') ?></span>
              </div>
            </div>

            <h5 class="fw-bold text-dark mt-2 mb-2"><?= e($row['title']) ?></h5>
            <p class="text-sm text-muted mb-3" style="white-space: pre-line;"><?= e($row['content']) ?></p>

            <!-- Actions taken -->
            <?php if ($row['action_taken']): ?>
              <div class="p-3 bg-success-subtle text-success-durable rounded mb-3 small">
                <strong>Resolution Remarks:</strong><br>
                <?= e($row['action_taken']) ?>
              </div>
            <?php endif; ?>

            <!-- Action Form Toggle -->
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#action-form-<?= $row['id'] ?>">
              <i class="bi bi-pencil-square me-1"></i> Update & Add Remarks
            </button>

            <!-- Collapsible Remarks Form -->
            <div class="collapse mt-3" id="action-form-<?= $row['id'] ?>">
              <div class="card card-body bg-light border-0">
                <form method="POST">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="resolve">
                  <input type="hidden" name="id" value="<?= $row['id'] ?>">
                  
                  <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                      <label class="form-label small fw-600">Change Status</label>
                      <select name="status" class="form-select form-select-sm" required>
                        <option value="submitted" <?= $row['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="reviewed" <?= $row['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                        <option value="action_taken" <?= $row['status'] === 'action_taken' ? 'selected' : '' ?>>Action Taken</option>
                        <option value="archived" <?= $row['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                      </select>
                    </div>
                    <div class="col-md-7">
                      <label class="form-label small fw-600">Action Remarks / Resolution Details</label>
                      <input type="text" name="action_taken" class="form-control form-control-sm" placeholder="Details of actions taken..." value="<?= e($row['action_taken']) ?>" required>
                    </div>
                    <div class="col-md-2">
                      <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check-lg"></i> Update</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
