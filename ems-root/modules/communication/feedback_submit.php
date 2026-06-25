<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Submit Feedback / Report';
$breadcrumbs = ['Communication' => 'templates.php', 'Feedback Submit' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(); // Any logged-in user can submit

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $type    = $_POST['feedback_type'] ?? 'suggestion';
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $anon    = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Determine reporter role
    $user_roles = $_SESSION['roles'] ?? [];
    $role = 'other';
    if (in_array('student', $user_roles)) {
        $role = 'student';
    } elseif (in_array('teacher', $user_roles) || in_array('accountant', $user_roles) || in_array('principal', $user_roles)) {
        $role = 'staff';
    }

    if ($title && $content) {
        $stmt = $pdo->prepare(
            'INSERT INTO feedback_reports (reporter_role, user_id, feedback_type, title, content, is_anonymous, status)
             VALUES (?, ?, ?, ?, ?, ?, "submitted")'
        );
        $stmt->execute([
            $role,
            $anon ? null : current_user_id(),
            $type,
            $title,
            $content,
            $anon
        ]);
        
        flash('success', 'Your feedback/report has been submitted successfully.');
        header('Location: feedback_submit.php');
        exit;
    } else {
        flash('error', 'Please fill in both the title and details of your request.');
    }
}

// Load past submissions by this user
$stmt = $pdo->prepare('SELECT * FROM feedback_reports WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$stmt->execute([current_user_id()]);
$mySubmissions = $stmt->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-chat-square-text-fill me-2 text-primary"></i>Feedback & Problem Reporting</h1>

<div class="row g-3">
  <!-- Submission Form -->
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">New Feedback / Inquiry to Authority</span></div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          
          <div class="mb-3">
            <label class="form-label fw-600 small">Select Type</label>
            <select name="feedback_type" class="form-select form-select-sm" required>
              <option value="suggestion">💡 General Suggestion</option>
              <option value="problem">⚠️ Hidden Problem / Complaint</option>
              <option value="asking">❓ Asking / Request to Authority</option>
              <option value="incident">📢 Disciplinary / Incident Report</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-600 small">Subject Title</label>
            <input type="text" name="title" class="form-control form-control-sm" placeholder="Summarize your issue..." max="150" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-600 small">Details & Explanations</label>
            <textarea name="content" rows="6" class="form-control form-control-sm" placeholder="Provide full details here..." required></textarea>
          </div>

          <div class="mb-3 form-check form-switch">
            <input type="checkbox" class="form-check-input" name="is_anonymous" id="is_anonymous">
            <label class="form-check-label fw-bold small text-danger" for="is_anonymous">
              <i class="bi bi-eye-slash-fill me-1"></i> Submit Anonymously (Hide Identity)
            </label>
            <div class="form-text text-xs">If checked, the system will hide your username and profile from administrators.</div>
          </div>

          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Submit to Authority</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Past Logged Submissions -->
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-header py-3 px-4 bg-light"><span class="card-title">My Recent Logged Submissions</span></div>
      <div class="card-body p-0">
        <?php if (empty($mySubmissions)): ?>
          <div class="text-muted text-center py-4 small">You have no logged feedback reports. Anonymous feedback is not listed here for privacy.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($mySubmissions as $row): 
              $badge = match($row['status']) {
                  'submitted'    => 'bg-secondary',
                  'reviewed'     => 'bg-info',
                  'action_taken' => 'bg-success',
                  default        => 'bg-light text-dark'
              };
            ?>
              <li class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="fw-bold small"><?= e($row['title']) ?></span>
                  <span class="badge <?= $badge ?> small"><?= ucfirst(e($row['status'])) ?></span>
                </div>
                <div class="text-xs text-muted mb-2"><?= fmt_date($row['created_at'], 'd M Y, H:i') ?> &bull; Type: <?= ucfirst(e($row['feedback_type'])) ?></div>
                <p class="text-sm text-dark mb-1" style="white-space: pre-line;"><?= e($row['content']) ?></p>
                <?php if ($row['action_taken']): ?>
                  <div class="mt-2 p-2 bg-success-subtle text-success-durable rounded small">
                    <strong>Authority Remarks:</strong><br>
                    <?= e($row['action_taken']) ?>
                  </div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
