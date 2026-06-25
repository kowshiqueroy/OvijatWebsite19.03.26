<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Student Profile';
$breadcrumbs = ['Students' => 'index.php', 'Profile' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.view']);

$pdo = db();
$id  = int_param('id', 0, $_GET);
if (!$id) { flash('error', 'Invalid student ID.'); redirect('index.php'); }

$student = $pdo->prepare(
    'SELECT u.id, u.username, u.status, u.created_at,
            sp.*
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.id = :id'
);
$student->execute([':id' => $id]);
$s = $student->fetch();
if (!$s) { flash('error', 'Student not found.'); redirect('index.php'); }

// All enrollments
$enrollments = $pdo->prepare(
    'SELECT se.*, c.class_name, sec.section_name, ass.session_name,
            gs.group_name
     FROM student_enrollments se
     JOIN classes c ON c.id=se.class_id
     JOIN sections sec ON sec.id=se.section_id
     JOIN academic_sessions ass ON ass.id=se.session_id
     LEFT JOIN groups_stream gs ON gs.id=se.group_id
     WHERE se.student_id=:sid
     ORDER BY ass.start_date DESC'
);
$enrollments->execute([':sid' => $id]);
$enrollments = $enrollments->fetchAll();

// Fee summary for current session
$session_id = (int)setting('current_session_id', 0);
$feeSummary = [];
if ($session_id) {
    $fs = $pdo->prepare(
        'SELECT fc.category_name, fl.amount_due, fl.amount_paid, fl.waiver_amount, fl.status, fl.due_date
         FROM fee_ledgers fl JOIN fee_categories fc ON fc.id=fl.fee_category_id
         WHERE fl.student_id=:sid AND fl.session_id=:sess
         ORDER BY fl.due_date'
    );
    $fs->execute([':sid' => $id, ':sess' => $session_id]);
    $feeSummary = $fs->fetchAll();
}

// Recent marks
$recentMarks = $pdo->prepare(
    'SELECT me.*, s.subject_name, e.exam_name,
            (COALESCE(me.marks_written,0)+COALESCE(me.marks_mcq,0)+COALESCE(me.marks_practical,0)) as total,
            (esc.full_marks_written+esc.full_marks_mcq+esc.full_marks_practical) as full_marks
     FROM marks_entry me
     JOIN subjects s ON s.id=me.subject_id
     JOIN exams e ON e.id=me.exam_id
     JOIN exam_subject_config esc ON esc.exam_id=me.exam_id AND esc.subject_id=me.subject_id AND esc.class_id=me.class_id
     WHERE me.student_id=:sid
     ORDER BY me.entered_at DESC LIMIT 15'
);
$recentMarks->execute([':sid' => $id]);
$recentMarks = $recentMarks->fetchAll();

// Attendance (last 30 days)
$attStats = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
$attRows = $pdo->prepare('SELECT status, COUNT(*) as cnt FROM student_attendance WHERE student_id=:sid AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status');
$attRows->execute([':sid' => $id]);
foreach ($attRows->fetchAll() as $r) $attStats[$r['status']] = (int)$r['cnt'];
$attTotal = array_sum($attStats);
$attPct   = $attTotal > 0 ? round($attStats['present']/$attTotal*100) : 0;

$page_title = e($s['first_name'] . ' ' . $s['last_name']);
require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Student Profile</h1>
  <div class="d-flex gap-2">
    <?php if (has_permission('students.edit')): ?>
      <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
    <?php endif; ?>
    <?php if (has_permission('fees.collect')): ?>
      <a href="../finance/collect.php?student_id=<?= $id ?>&session_id=<?= $session_id ?>" class="btn btn-success"><i class="bi bi-cash me-1"></i>Collect Fee</a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <!-- Left: Photo + Quick Info -->
  <div class="col-md-3">
    <div class="card text-center mb-3">
      <div class="card-body py-4">
        <div class="mx-auto mb-3 rounded-circle overflow-hidden border" style="width:100px;height:100px;background:#e2e8f0;">
          <?php if ($s['photo'] && file_exists(UPLOAD_PHOTOS . $s['photo'])): ?>
            <img src="../../uploads/photos/<?= e($s['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;color:#94a3b8;">
              <?= strtoupper(substr($s['first_name'],0,1)) ?>
            </div>
          <?php endif; ?>
        </div>
        <h5 class="fw-700 mb-0"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h5>
        <p class="text-muted small mb-2"><code><?= e($s['student_id_no'] ?? 'No ID') ?></code></p>
        <span class="badge-status badge-<?= $s['status'] === 'active' ? 'active' : 'draft' ?>"><?= ucfirst(e($s['status'])) ?></span>
      </div>
    </div>

    <!-- Attendance card -->
    <div class="card">
      <div class="card-header py-3 px-3"><span class="card-title small">Attendance (30 days)</span></div>
      <div class="card-body py-3">
        <div class="text-center mb-2">
          <span class="fw-700" style="font-size:1.75rem;"><?= $attPct ?>%</span>
        </div>
        <div class="progress mb-2" style="height:6px;">
          <div class="progress-bar bg-<?= $attPct >= 75 ? 'success' : ($attPct >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $attPct ?>%"></div>
        </div>
        <div class="row g-1 text-center small">
          <div class="col-6"><span class="text-success fw-600"><?= $attStats['present'] ?></span><br><span class="text-muted">Present</span></div>
          <div class="col-6"><span class="text-danger fw-600"><?= $attStats['absent'] ?></span><br><span class="text-muted">Absent</span></div>
          <div class="col-6"><span class="text-warning fw-600"><?= $attStats['late'] ?></span><br><span class="text-muted">Late</span></div>
          <div class="col-6"><span class="text-info fw-600"><?= $attStats['excused'] ?></span><br><span class="text-muted">Excused</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Details -->
  <div class="col-md-9">
    <!-- Personal Info -->
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">Personal Information</span></div>
      <div class="card-body">
        <div class="row g-2 small">
          <?php
          $fields = [
            'Date of Birth'     => fmt_date($s['dob']),
            'Gender'            => ucfirst($s['gender'] ?? '—'),
            'Religion'          => $s['religion'] ?? '—',
            'Blood Group'       => $s['blood_group'] ?? '—',
            'Father\'s Name'    => $s['father_name'] ?? '—',
            'Mother\'s Name'    => $s['mother_name'] ?? '—',
            'Guardian'          => ($s['guardian_name'] ?? '—') . ($s['guardian_relation'] ? ' (' . $s['guardian_relation'] . ')' : ''),
            'Guardian Phone'    => $s['guardian_phone'] ?? '—',
            'Birth Cert No'     => $s['birth_certificate_no'] ?? '—',
            'Present Address'   => $s['address_present'] ?? '—',
            'Previous School'   => $s['previous_school'] ?? '—',
            'Admission Date'    => fmt_date($s['admission_date']),
          ];
          foreach ($fields as $label => $value):
          ?>
          <div class="col-md-6">
            <div class="d-flex gap-2">
              <span class="text-muted" style="min-width:120px;"><?= e($label) ?>:</span>
              <span class="fw-600"><?= e($value) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Enrollments -->
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">Enrollment History</span></div>
      <?php if (empty($enrollments)): ?>
        <div class="card-body"><div class="empty-state"><i class="bi bi-mortarboard"></i><p>Not enrolled yet</p></div></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 small">
          <thead><tr><th>Session</th><th>Class</th><th>Section</th><th>Group</th><th>Roll</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($enrollments as $en): ?>
            <tr>
              <td class="fw-600"><?= e($en['session_name']) ?></td>
              <td><?= e($en['class_name']) ?></td>
              <td><?= e($en['section_name']) ?></td>
              <td><?= e($en['group_name'] ?? '—') ?></td>
              <td class="fw-700"><?= $en['roll_number'] ?></td>
              <td><span class="badge-status badge-<?= $en['status'] === 'active' ? 'active' : 'draft' ?>"><?= ucfirst(e($en['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Fee Summary -->
    <?php if (!empty($feeSummary) && has_permission('finance.view')): ?>
    <div class="card mb-3">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Fee Status (Current Session)</span>
        <a href="../finance/collect.php?student_id=<?= $id ?>&session_id=<?= $session_id ?>" class="btn btn-sm btn-outline-success">Collect</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Category</th><th>Due</th><th>Paid</th><th>Waiver</th><th>Balance</th><th>Status</th></tr></thead>
          <tbody>
            <?php
            $totDue=$totPaid=$totWaiver=0;
            foreach ($feeSummary as $fee):
              $bal = max(0, $fee['amount_due'] - $fee['amount_paid'] - $fee['waiver_amount']);
              $totDue+=$fee['amount_due']; $totPaid+=$fee['amount_paid']; $totWaiver+=$fee['waiver_amount'];
            ?>
            <tr>
              <td class="fw-600"><?= e($fee['category_name']) ?></td>
              <td><?= money($fee['amount_due']) ?></td>
              <td class="text-success"><?= money($fee['amount_paid']) ?></td>
              <td class="text-warning"><?= money($fee['waiver_amount']) ?></td>
              <td class="<?= $bal>0?'text-danger fw-600':'' ?>"><?= money($bal) ?></td>
              <td><span class="badge-status badge-<?= e($fee['status']) ?>"><?= ucfirst(e($fee['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light fw-700">
              <td>Total</td>
              <td><?= money($totDue) ?></td>
              <td class="text-success"><?= money($totPaid) ?></td>
              <td class="text-warning"><?= money($totWaiver) ?></td>
              <td class="<?= ($totDue-$totPaid-$totWaiver)>0?'text-danger':'' ?>"><?= money(max(0,$totDue-$totPaid-$totWaiver)) ?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Marks -->
    <?php if (!empty($recentMarks) && has_permission('exams.view')): ?>
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Recent Exam Results</span></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Exam</th><th>Subject</th><th>Written</th><th>MCQ</th><th>Practical</th><th>Total</th><th>Grade</th></tr></thead>
          <tbody>
            <?php foreach ($recentMarks as $mk):
              $pct = $mk['full_marks'] > 0 ? ($mk['total']/$mk['full_marks'])*100 : 0;
              $grade = calculate_grade($pct);
            ?>
            <tr>
              <td><?= e($mk['exam_name']) ?></td>
              <td class="fw-600"><?= e($mk['subject_name']) ?></td>
              <td><?= $mk['is_absent'] ? '<span class="text-danger">AB</span>' : ($mk['marks_written'] ?? '—') ?></td>
              <td><?= $mk['is_absent'] ? '' : ($mk['marks_mcq'] ?? '—') ?></td>
              <td><?= $mk['is_absent'] ? '' : ($mk['marks_practical'] ?? '—') ?></td>
              <td class="fw-700"><?= $mk['is_absent'] ? 'AB' : $mk['total'] ?></td>
              <td><span class="badge bg-<?= $grade['gpa'] >= 4 ? 'success' : ($grade['gpa'] >= 2 ? 'warning' : 'danger') ?> text-white"><?= $grade['grade'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
