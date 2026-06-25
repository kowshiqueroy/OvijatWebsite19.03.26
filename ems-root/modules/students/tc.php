<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Transfer Certificate';
$breadcrumbs = ['Students' => 'index.php', 'Transfer (TC)' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.tc']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'issue_tc') {
        $student_id  = int_param('student_id', 0, $_POST);
        $reason      = trim($_POST['reason'] ?? '');
        $destination = trim($_POST['destination_school'] ?? '');
        $new_status  = $_POST['new_status'] ?? 'transferred';

        if ($student_id) {
            $count = (int)$pdo->query('SELECT COUNT(*) FROM tc_records')->fetchColumn() + 1;
            $file_no = 'TC-' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

            $pdo->prepare('INSERT INTO tc_records (student_id,file_number,issued_date,reason,destination_school,approved_by,status) VALUES (?,?,CURDATE(),?,?,?,?)')
                ->execute([$student_id,$file_no,$reason,$destination,current_user_id(),'issued']);

            // Change enrollment status
            $pdo->prepare("UPDATE student_enrollments SET status=? WHERE student_id=? AND status='active'")
                ->execute([$new_status, $student_id]);

            // Archive user
            $pdo->prepare("UPDATE users SET status='archived' WHERE id=?")->execute([$student_id]);

            log_activity('issue_tc','students',$student_id,'active',$new_status);
            flash('success', "TC issued. File No: $file_no");
            header('Location: tc.php?print_tc=' . $student_id . '&file_no=' . urlencode($file_no));
            exit;
        }
    }
    header('Location: tc.php');
    exit;
}

// Print mode
$print_tc = int_param('print_tc', 0, $_GET);
$file_no  = $_GET['file_no'] ?? '';

// Past TC records
$page   = max(1, int_param('page', 1, $_GET));
$total  = (int)$pdo->query('SELECT COUNT(*) FROM tc_records')->fetchColumn();
$pg     = paginate($total, $page);
$tcList = $pdo->query("SELECT tcr.*, sp.first_name, sp.last_name, sp.student_id_no, u2.full_name as approved_by_name FROM tc_records tcr JOIN student_profiles sp ON sp.user_id=tcr.student_id JOIN users u2 ON u2.id=tcr.approved_by ORDER BY tcr.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}")->fetchAll();

// Student search for new TC
$searchQ = trim($_GET['q'] ?? '');
$searchResults = [];
if ($searchQ) {
    $sr = $pdo->prepare("SELECT u.id, sp.first_name, sp.last_name, sp.student_id_no, c.class_name, sec.section_name FROM users u JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.status='active' LEFT JOIN classes c ON c.id=se.class_id LEFT JOIN sections sec ON sec.id=se.section_id WHERE (sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q) AND u.status='active' LIMIT 15");
    $sr->execute([':q' => "%$searchQ%"]);
    $searchResults = $sr->fetchAll();
}

$school_name = setting('school_name','EMS');

// Print TC view
if ($print_tc && $file_no) {
    $tc = $pdo->prepare('SELECT tcr.*, sp.*, u.username FROM tc_records tcr JOIN student_profiles sp ON sp.user_id=tcr.student_id JOIN users u ON u.id=tcr.student_id WHERE tcr.student_id=:id AND tcr.file_number=:fn LIMIT 1');
    $tc->execute([':id'=>$print_tc,':fn'=>$file_no]);
    $tcData = $tc->fetch();

    if ($tcData) {
        require_once EMS_ROOT . '/includes/header.php';
        ?>
        <div class="d-flex gap-2 mb-3 d-print-none">
          <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print TC</button>
          <a href="tc.php" class="btn btn-outline-secondary">Back</a>
        </div>
        <div id="tc-document" style="max-width:700px;margin:0 auto;border:2px solid #000;padding:2rem;font-family:serif;">
          <div style="text-align:center;border-bottom:2px solid #000;padding-bottom:1rem;margin-bottom:1rem;">
            <h2 style="margin:0;font-size:1.4rem;"><?= e($school_name) ?></h2>
            <h3 style="margin:.5rem 0 0;font-size:1.1rem;">TRANSFER CERTIFICATE</h3>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:1rem;">
            <div><strong>File No:</strong> <?= e($tcData['file_number']) ?></div>
            <div><strong>Date:</strong> <?= fmt_date($tcData['issued_date']) ?></div>
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:.95rem;">
            <?php
            $rows = [
              ['Name of Student',   $tcData['first_name'].' '.$tcData['last_name']],
              ["Father's Name",     $tcData['father_name']],
              ["Mother's Name",     $tcData['mother_name']],
              ['Date of Birth',     fmt_date($tcData['dob'])],
              ['Admission Date',    fmt_date($tcData['admission_date'])],
              ['Student ID',        $tcData['student_id_no']],
              ['Religion',          $tcData['religion']],
              ['Blood Group',       $tcData['blood_group']],
              ['Reason for Leaving',$tcData['reason']],
              ['Destination School',$tcData['destination_school']],
              ['Date of Leaving',   fmt_date($tcData['issued_date'])],
              ['Character',         'Good'],
            ];
            foreach ($rows as [$label, $val]):
            ?>
            <tr>
              <td style="padding:.4rem .6rem;border:1px solid #999;width:40%;font-weight:bold;"><?= e($label) ?></td>
              <td style="padding:.4rem .6rem;border:1px solid #999;"><?= e($val ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <div style="display:flex;justify-content:space-between;margin-top:3rem;">
            <div style="text-align:center;">
              <div style="border-top:1px solid #000;padding-top:.3rem;min-width:150px;">Class Teacher</div>
            </div>
            <div style="text-align:center;">
              <div style="border-top:1px solid #000;padding-top:.3rem;min-width:150px;">Principal / Headmaster</div>
            </div>
          </div>
          <div style="margin-top:1.5rem;font-size:.8rem;text-align:center;color:#555;">
            Issued by <?= e($school_name) ?> on <?= fmt_date($tcData['issued_date']) ?>
          </div>
        </div>
        <?php
        require_once EMS_ROOT . '/includes/footer.php';
        exit;
    }
}

require_once EMS_ROOT . '/includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-file-earmark-arrow-up-fill me-2 text-primary"></i>Transfer Certificate (TC)</h1>

<div class="row g-3">
  <!-- Issue new TC -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Issue New TC</span></div>
      <div class="card-body">
        <form method="GET" class="mb-3">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search student by name or ID…" value="<?= e($searchQ) ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
          </div>
        </form>
        <?php if (!empty($searchResults)): ?>
        <div class="list-group list-group-flush mb-3">
          <?php foreach ($searchResults as $sr): ?>
          <a href="#tc-form" class="list-group-item list-group-item-action py-2"
             onclick="setTCForm(<?= $sr['id'] ?>, '<?= e($sr['first_name'].' '.$sr['last_name']) ?>', '<?= e($sr['student_id_no'] ?? '') ?>', '<?= e(($sr['class_name']??'').' - '.($sr['section_name']??'')) ?>')">
            <div class="fw-600"><?= e($sr['first_name'].' '.$sr['last_name']) ?></div>
            <small class="text-muted"><?= e($sr['student_id_no']??'') ?> | <?= e($sr['class_name']??'?') ?> - <?= e($sr['section_name']??'?') ?></small>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div id="tc-form" style="display:none;">
          <div class="alert alert-warning small mb-3 d-flex gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>Issuing a TC will <strong>archive the student account</strong> and mark enrollment as transferred. This cannot be undone.</div>
          </div>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="issue_tc">
            <input type="hidden" name="student_id" id="tc_student_id" value="">
            <div class="p-3 bg-light rounded mb-3">
              <div class="fw-700" id="tc_student_name">—</div>
              <small class="text-muted" id="tc_student_info">—</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Reason for Leaving</label>
              <textarea name="reason" class="form-control" rows="2" placeholder="Family relocation, better institution, etc."></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Destination School</label>
              <input type="text" name="destination_school" class="form-control" placeholder="Name of new school">
            </div>
            <div class="mb-3">
              <label class="form-label">Status After TC</label>
              <select name="new_status" class="form-select">
                <option value="transferred">Transferred</option>
                <option value="opt_out">Opted Out / Dropped</option>
              </select>
            </div>
            <button type="submit" class="btn btn-danger w-100"
                    data-confirm="Issue TC and archive this student? This cannot be undone.">
              <i class="bi bi-file-earmark-check me-1"></i>Issue Transfer Certificate
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- TC History -->
  <div class="col-md-7">
    <div class="card table-card">
      <div class="card-header py-3 px-4"><span class="card-title">TC History</span></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 small">
          <thead><tr><th>File No</th><th>Student</th><th>ID</th><th>Issued</th><th>Destination</th><th>By</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($tcList)): ?>
              <tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-earmark-x"></i><p>No TCs issued yet</p></div></td></tr>
            <?php else: foreach ($tcList as $tc): ?>
            <tr>
              <td><span class="badge bg-secondary"><?= e($tc['file_number']) ?></span></td>
              <td class="fw-600"><?= e($tc['first_name'].' '.$tc['last_name']) ?></td>
              <td><code><?= e($tc['student_id_no']??'') ?></code></td>
              <td><?= fmt_date($tc['issued_date']) ?></td>
              <td><?= e($tc['destination_school']??'—') ?></td>
              <td><?= e($tc['approved_by_name']) ?></td>
              <td>
                <a href="tc.php?print_tc=<?= $tc['student_id'] ?>&file_no=<?= urlencode($tc['file_number']) ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
function setTCForm(id, name, studentId, classInfo) {
  document.getElementById('tc_student_id').value = id;
  document.getElementById('tc_student_name').textContent = name;
  document.getElementById('tc_student_info').textContent = studentId + ' | ' + classInfo;
  document.getElementById('tc-form').style.display = 'block';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
