<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Question Vault';
$breadcrumbs = ['Examinations' => 'index.php', 'Question Vault' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['exams.view']);

$pdo     = db();
$exam_id = int_param('exam_id', 0, $_GET);
$tab     = $_GET['tab'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Teacher submits a question paper
    if ($action === 'submit' && has_permission('marks.enter')) {
        $eid    = int_param('exam_id', 0, $_POST);
        $cls_id = int_param('class_id', 0, $_POST);
        $subj_id= int_param('subject_id', 0, $_POST);
        $notes  = trim($_POST['notes'] ?? '');

        if ($eid && $cls_id && $subj_id) {
            $file = upload_file('question_file', UPLOAD_QUESTIONS,
                ['pdf','doc','docx','jpg','jpeg','png'], MAX_DOC_SIZE);

            if ($file) {
                $pdo->prepare(
                    'INSERT INTO question_vault
                     (exam_id,class_id,subject_id,submitted_by,file_path,notes,status)
                     VALUES (?,?,?,?,?,"draft")'
                )->execute([$eid, $cls_id, $subj_id, current_user_id(), $file, $notes]);
                // Set to pending review
                $id = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE question_vault SET status='pending_review' WHERE id=?")->execute([$id]);
                flash('success', 'Question paper submitted for review.');
            } else {
                flash('error', 'File upload failed. Allowed: PDF, DOC, DOCX, JPG, PNG. Max 5MB.');
            }
        }
        header("Location: questions.php?exam_id=$eid&tab=pending");
        exit;
    }

    // HOD/Admin reviews
    if (in_array($action, ['approve', 'reject']) && has_permission('exams.manage')) {
        $qid    = int_param('id', 0, $_POST);
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare(
            'UPDATE question_vault SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?'
        )->execute([$status, current_user_id(), $qid]);
        log_activity($action.'_question', 'exams', $qid);
        flash('success', 'Question paper ' . $status . '.');
        header("Location: questions.php?exam_id=$exam_id&tab=$tab");
        exit;
    }

    // Delete (own submissions only, or admin)
    if ($action === 'delete') {
        $qid = int_param('id', 0, $_POST);
        $q   = $pdo->prepare('SELECT submitted_by, file_path FROM question_vault WHERE id=?');
        $q->execute([$qid]);
        $qrow = $q->fetch();
        if ($qrow && ($qrow['submitted_by'] == current_user_id() || has_permission('exams.manage'))) {
            // Remove file
            if ($qrow['file_path'] && file_exists(UPLOAD_QUESTIONS . $qrow['file_path'])) {
                @unlink(UPLOAD_QUESTIONS . $qrow['file_path']);
            }
            $pdo->prepare('DELETE FROM question_vault WHERE id=?')->execute([$qid]);
            flash('success', 'Entry removed.');
        }
        header("Location: questions.php?exam_id=$exam_id&tab=$tab");
        exit;
    }
}

// Load data
$allExams = $pdo->query('SELECT id, exam_name FROM exams ORDER BY id DESC LIMIT 30')->fetchAll();

$where  = $exam_id ? "qv.exam_id=$exam_id" : '1=1';
if ($tab === 'pending')  $where .= " AND qv.status='pending_review'";
if ($tab === 'approved') $where .= " AND qv.status='approved'";
if ($tab === 'rejected') $where .= " AND qv.status='rejected'";

$questions = $pdo->query(
    "SELECT qv.*, e.exam_name, c.class_name, s.subject_name,
            u.full_name as submitted_by_name,
            rv.full_name as reviewed_by_name
     FROM question_vault qv
     JOIN exams e ON e.id=qv.exam_id
     JOIN classes c ON c.id=qv.class_id
     JOIN subjects s ON s.id=qv.subject_id
     JOIN users u ON u.id=qv.submitted_by
     LEFT JOIN users rv ON rv.id=qv.reviewed_by
     WHERE $where
     ORDER BY qv.id DESC"
)->fetchAll();

$counts = [];
foreach (['all'=>'1=1', 'pending'=>"status='pending_review'",
          'approved'=>"status='approved'", 'rejected'=>"status='rejected'"] as $k => $w) {
    $extra = $exam_id ? " AND exam_id=$exam_id" : '';
    $counts[$k] = (int)$pdo->query("SELECT COUNT(*) FROM question_vault WHERE $w$extra")->fetchColumn();
}

// For submit form
$examClasses = $exam_id
    ? $pdo->query("SELECT c.id,c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=$exam_id ORDER BY c.display_order")->fetchAll()
    : [];
$subjects = $pdo->query('SELECT id, subject_name FROM subjects WHERE status=1 ORDER BY subject_name')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0">
    <i class="bi bi-archive-fill me-2 text-primary"></i>Question Vault
  </h1>
  <?php if (has_permission('marks.enter') && $exam_id): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitModal">
    <i class="bi bi-cloud-upload me-1"></i>Submit Question Paper
  </button>
  <?php endif; ?>
</div>

<!-- Exam filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small">Exam</label>
        <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— All Exams —</option>
          <?php foreach ($allExams as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $exam_id == $ex['id'] ? 'selected' : '' ?>>
              <?= e($ex['exam_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<!-- Status tabs -->
<ul class="nav nav-tabs mb-3">
  <?php foreach (['all' => 'All', 'pending' => 'Pending Review',
                  'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $v): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab === $k ? 'active' : '' ?>"
       href="?exam_id=<?= $exam_id ?>&tab=<?= $k ?>">
      <?= $v ?>
      <span class="badge ms-1 bg-<?= $k === 'pending' ? 'warning text-dark' : ($k === 'approved' ? 'success' : ($k === 'rejected' ? 'danger' : 'secondary')) ?>">
        <?= $counts[$k] ?>
      </span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Question list -->
<?php if (empty($questions)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <i class="bi bi-archive"></i>
    <p>No question papers <?= $tab !== 'all' ? "in '$tab' status" : '' ?> yet.</p>
    <?php if (has_permission('marks.enter') && $exam_id): ?>
    <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#submitModal">
      Submit the first one
    </button>
    <?php endif; ?>
  </div>
</div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($questions as $q):
    $statusColor = ['draft'=>'secondary','pending_review'=>'warning','approved'=>'success','rejected'=>'danger'][$q['status']] ?? 'secondary';
    $statusLabel = ['draft'=>'Draft','pending_review'=>'Pending Review','approved'=>'Approved for Print','rejected'=>'Rejected'][$q['status']] ?? $q['status'];
    $ext = strtolower(pathinfo($q['file_path'] ?? '', PATHINFO_EXTENSION));
    $icon = in_array($ext, ['jpg','jpeg','png']) ? 'file-image' : ($ext === 'pdf' ? 'file-pdf' : 'file-earmark-word');
  ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100 <?= $q['status'] === 'approved' ? 'border-success' : ($q['status'] === 'rejected' ? 'border-danger' : '') ?>">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div>
            <div class="fw-700"><?= e($q['subject_name']) ?></div>
            <div class="text-muted small"><?= e($q['class_name']) ?> · <?= e($q['exam_name']) ?></div>
          </div>
          <span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span>
        </div>

        <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-light rounded">
          <i class="bi bi-<?= $icon ?> fs-4 text-primary"></i>
          <div>
            <div class="small fw-600"><?= e($q['file_path'] ?? 'No file') ?></div>
            <div class="text-muted small">
              Submitted by <?= e($q['submitted_by_name']) ?>
            </div>
          </div>
        </div>

        <?php if ($q['notes']): ?>
        <div class="small text-muted border-start border-2 ps-2 mb-2"><?= e($q['notes']) ?></div>
        <?php endif; ?>

        <?php if ($q['reviewed_by_name']): ?>
        <div class="small text-muted">
          Reviewed by <?= e($q['reviewed_by_name']) ?> on <?= fmt_date($q['reviewed_at'], 'd M Y') ?>
        </div>
        <?php endif; ?>

        <div class="mt-3 d-flex gap-2 flex-wrap">
          <!-- Download link if file exists -->
          <?php if ($q['file_path'] && file_exists(UPLOAD_QUESTIONS . $q['file_path'])): ?>
          <a href="../../uploads/questions/<?= e($q['file_path']) ?>" target="_blank"
             class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i>View File
          </a>
          <?php endif; ?>

          <!-- HOD approve/reject -->
          <?php if ($q['status'] === 'pending_review' && has_permission('exams.manage')): ?>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn btn-sm btn-success">
              <i class="bi bi-check-lg me-1"></i>Approve
            </button>
          </form>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-confirm="Reject this submission?">
              <i class="bi bi-x-lg me-1"></i>Reject
            </button>
          </form>
          <?php endif; ?>

          <!-- Delete -->
          <?php if ($q['submitted_by'] == current_user_id() || has_permission('exams.manage')): ?>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-confirm="Delete this submission?">
              <i class="bi bi-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Submit Modal -->
<?php if (has_permission('marks.enter')): ?>
<div class="modal fade" id="submitModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit">
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <div class="modal-header">
          <h5 class="modal-title">Submit Question Paper</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small d-flex gap-2">
            <i class="bi bi-info-circle-fill"></i>
            After submission the paper enters <strong>Pending Review</strong>. The HOD/Admin
            must approve before it is cleared for printing.
          </div>
          <div class="mb-3">
            <label class="form-label">Class <span class="text-danger">*</span></label>
            <select name="class_id" class="form-select" required>
              <option value="">— Select Class —</option>
              <?php foreach ($examClasses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
              <?php endforeach; ?>
              <?php if (empty($examClasses)): ?>
                <option disabled>No classes mapped to this exam yet</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Subject <span class="text-danger">*</span></label>
            <select name="subject_id" class="form-select" required>
              <option value="">— Select Subject —</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['subject_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Question Paper File <span class="text-danger">*</span>
              <small class="text-muted">(PDF, DOC, DOCX, JPG — max 5MB)</small>
            </label>
            <input type="file" name="question_file" class="form-control"
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="2"
                      placeholder="e.g. Final version — do not share"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-cloud-upload me-1"></i>Submit for Review
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
