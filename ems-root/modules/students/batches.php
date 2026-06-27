<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Special Batches';
$breadcrumbs = ['Students' => 'index.php', 'Special Batches' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.create']);

$pdo = db();
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_batch') {
        $id   = int_param('id', 0, $_POST);
        $name = trim($_POST['batch_name'] ?? '');
        $type = $_POST['batch_type'] ?? 'other';
        $sess = int_param('session_id', $session_id, $_POST);
        $teacher = int_param('teacher_id', 0, $_POST) ?: null;
        $start = $_POST['start_date'] ?? null;
        $end   = $_POST['end_date'] ?? null;
        $desc  = trim($_POST['description'] ?? '');
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE special_batches SET batch_name=?,batch_type=?,teacher_id=?,start_date=?,end_date=?,description=? WHERE id=?')
                    ->execute([$name,$type,$teacher,$start,$end,$desc,$id]);
                flash('success','Batch updated.');
            } else {
                $pdo->prepare('INSERT INTO special_batches (batch_name,batch_type,session_id,teacher_id,start_date,end_date,description) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name,$type,$sess,$teacher,$start,$end,$desc]);
                flash('success',"Batch '$name' created.");
            }
        }
    } elseif ($action === 'enroll') {
        $batch_id   = int_param('batch_id', 0, $_POST);
        $student_id = int_param('student_id', 0, $_POST);
        if ($batch_id && $student_id) {
            try {
                $pdo->prepare('INSERT IGNORE INTO batch_enrollments (batch_id,student_id,joined_at,status) VALUES (?,?,CURDATE(),"active")')
                    ->execute([$batch_id,$student_id]);
                flash('success','Student enrolled in batch.');
            } catch (Exception $e) { flash('error','Already enrolled.'); }
        }
    } elseif ($action === 'remove_member') {
        $bid = int_param('batch_id',0,$_POST);
        $sid = int_param('student_id',0,$_POST);
        $pdo->prepare("UPDATE batch_enrollments SET status='left' WHERE batch_id=? AND student_id=?")->execute([$bid,$sid]);
        flash('success','Student removed from batch.');
    } elseif ($action === 'delete_batch') {
        $id = int_param('id',0,$_POST);
        $pdo->prepare('UPDATE special_batches SET deleted_at=NOW(), deleted_by=? WHERE id=?')
            ->execute([$_SESSION['user_id']??null, $id]);
        flash('success','Batch moved to deleted items.');

    } elseif ($action === 'save_batch_subject') {
        $batch_id   = int_param('batch_id', 0, $_POST);
        $subject_id = int_param('subject_id', 0, $_POST);
        $teacher_id = int_param('teacher_id', 0, $_POST) ?: null;
        $full_marks = int_param('full_marks', 100, $_POST);
        $pass_marks = int_param('pass_marks', 33, $_POST);
        if ($batch_id && $subject_id) {
            $pdo->prepare('INSERT INTO batch_subjects (batch_id,subject_id,teacher_id,full_marks,pass_marks) VALUES (?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id),full_marks=VALUES(full_marks),pass_marks=VALUES(pass_marks)')
                ->execute([$batch_id,$subject_id,$teacher_id,$full_marks,$pass_marks]);
            flash('success','Subject added to batch.');
        }
    } elseif ($action === 'delete_batch_subject') {
        $pdo->prepare('DELETE FROM batch_subjects WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Subject removed.');

    } elseif ($action === 'save_batch_fee') {
        $batch_id  = int_param('batch_id', 0, $_POST);
        $fee_name  = trim($_POST['fee_name'] ?? '');
        $amount    = (float)($_POST['amount'] ?? 0);
        $fee_type  = $_POST['fee_type'] ?? 'one_time';
        $frequency = $_POST['frequency'] ?? 'once';
        if ($batch_id && $fee_name && $amount > 0) {
            $pdo->prepare('INSERT INTO batch_fee_structures (batch_id,fee_name,amount,fee_type,frequency) VALUES (?,?,?,?,?)')
                ->execute([$batch_id,$fee_name,$amount,$fee_type,$frequency]);
            flash('success','Fee structure added.');
        }
    } elseif ($action === 'save_batch_exam') {
        $batch_id   = int_param('batch_id', 0, $_POST);
        $exam_name  = trim($_POST['exam_name'] ?? '');
        $exam_date  = $_POST['exam_date'] ?: null;
        if ($batch_id && $exam_name) {
            $pdo->prepare('INSERT INTO batch_exams (batch_id,exam_name,exam_date,status) VALUES (?,?,?,"scheduled")')
                ->execute([$batch_id,$exam_name,$exam_date]);
            flash('success','Batch exam created.');
        }
    } elseif ($action === 'delete_batch_fee') {
        $pdo->prepare('DELETE FROM batch_fee_structures WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Fee structure removed.');
    } elseif ($action === 'save_batch_marks') {
        $batchExamId    = int_param('batch_exam_id', 0, $_POST);
        $batchSubjectId = int_param('batch_subject_id', 0, $_POST);
        $marksData      = $_POST['marks'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO batch_marks_entry (batch_exam_id,batch_subject_id,student_id,marks,is_absent,entered_by)
                               VALUES (?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE marks=VALUES(marks),is_absent=VALUES(is_absent),entered_by=VALUES(entered_by)');
        $count = 0;
        foreach ($marksData as $sid => $md) {
            $absent = isset($md['absent']) ? 1 : 0;
            $marks  = $absent ? null : ((float)($md['marks'] ?? 0));
            $stmt->execute([$batchExamId,$batchSubjectId,(int)$sid,$marks,$absent,$_SESSION['user_id']??null]);
            $count++;
        }
        flash('success',"$count marks saved.");

    } elseif ($action === 'enroll_class_section') {
        // Enroll all or selected students from a class/section
        $batch_id  = int_param('batch_id', 0, $_POST);
        $fromClass = int_param('from_class_id', 0, $_POST);
        $fromSect  = int_param('from_section_id', 0, $_POST) ?: null;
        $stuIds    = array_map('intval', (array)($_POST['student_ids'] ?? []));
        if ($batch_id && !empty($stuIds)) {
            $ins = $pdo->prepare('INSERT IGNORE INTO batch_enrollments (batch_id,student_id,joined_at,status) VALUES (?,?,CURDATE(),"active")');
            foreach ($stuIds as $sid) if ($sid) $ins->execute([$batch_id,$sid]);
            flash('success', count($stuIds) . ' student(s) enrolled in batch.');
        }
    }
    header('Location: batches.php?session_id='.$session_id);
    exit;
}

$batches  = $pdo->prepare('SELECT sb.*, u.full_name AS teacher_name, COUNT(DISTINCT be.id) AS member_count
    FROM special_batches sb
    LEFT JOIN users u ON u.id=sb.teacher_id
    LEFT JOIN batch_enrollments be ON be.batch_id=sb.id AND be.status="active"
    WHERE sb.session_id=:sess AND sb.deleted_at IS NULL
    GROUP BY sb.id ORDER BY sb.batch_name');
$batches->execute([':sess'=>$session_id]);
$batches = $batches->fetchAll();

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();
$teachers = $pdo->query("SELECT sp.user_id AS id, CONCAT(sp.first_name,' ',sp.last_name) AS name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();
$subjects = $pdo->query('SELECT id, subject_name, subject_type FROM subjects WHERE deleted_at IS NULL AND status=1 ORDER BY subject_name')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE deleted_at IS NULL AND status=1 ORDER BY display_order')->fetchAll();

$activeBatch = int_param('batch', 0, $_GET);
$activeTab   = $_GET['btab'] ?? 'members';
$batchMembers = $batchSubjects = $batchFees = $batchExams = $availableStudents = [];
$curBatch = null;

if ($activeBatch) {
    foreach ($batches as $b) if ($b['id'] == $activeBatch) { $curBatch = $b; break; }

    $bm = $pdo->prepare('
        SELECT be.id AS enroll_id, be.student_id, be.joined_at,
               CONCAT(sp.first_name," ",sp.last_name) AS student_name,
               sp.student_id_no, c.class_name, s.section_name
        FROM batch_enrollments be
        JOIN student_profiles sp ON sp.user_id=be.student_id
        LEFT JOIN student_enrollments se ON se.student_id=be.student_id AND se.session_id=:sess AND se.status="active"
        LEFT JOIN classes c ON c.id=se.class_id
        LEFT JOIN sections s ON s.id=se.section_id
        WHERE be.batch_id=:bid AND be.status="active"
        ORDER BY sp.first_name');
    $bm->execute([':bid'=>$activeBatch,':sess'=>$session_id]);
    $batchMembers = $bm->fetchAll();

    $batchSubjects = $pdo->prepare('SELECT bs.*, sub.subject_name, CONCAT(sp.first_name," ",sp.last_name) AS teacher_name
        FROM batch_subjects bs JOIN subjects sub ON sub.id=bs.subject_id
        LEFT JOIN staff_profiles sp ON sp.user_id=bs.teacher_id
        WHERE bs.batch_id=?')->execute([$activeBatch]) ? [] : [];
    $bsQ = $pdo->prepare('SELECT bs.*, sub.subject_name, CONCAT(sp.first_name," ",sp.last_name) AS teacher_name
        FROM batch_subjects bs JOIN subjects sub ON sub.id=bs.subject_id
        LEFT JOIN staff_profiles sp ON sp.user_id=bs.teacher_id
        WHERE bs.batch_id=?');
    $bsQ->execute([$activeBatch]);
    $batchSubjects = $bsQ->fetchAll();

    $bfQ = $pdo->prepare('SELECT * FROM batch_fee_structures WHERE batch_id=? ORDER BY id');
    $bfQ->execute([$activeBatch]);
    $batchFees = $bfQ->fetchAll();

    $beQ = $pdo->prepare('SELECT * FROM batch_exams WHERE batch_id=? ORDER BY exam_date');
    $beQ->execute([$activeBatch]);
    $batchExams = $beQ->fetchAll();

    $enrolled = array_column($batchMembers,'student_id');
    $notIn    = empty($enrolled) ? '' : ' AND u.id NOT IN (' . implode(',', array_map('intval',$enrolled)) . ')';
    $av = $pdo->prepare("
        SELECT u.id, CONCAT(sp.first_name,' ',sp.last_name) AS name,
               sp.student_id_no, c.class_name, s.section_name
        FROM users u
        JOIN student_profiles sp ON sp.user_id=u.id
        LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.session_id=:sess AND se.status='active'
        LEFT JOIN classes c ON c.id=se.class_id
        LEFT JOIN sections s ON s.id=se.section_id
        WHERE u.status='active' $notIn
        ORDER BY sp.first_name LIMIT 100
    ");
    $av->execute([':sess'=>$session_id]);
    $availableStudents = $av->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Special Batches</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
      <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#batchModal" onclick="setBatchForm(null)">
      <i class="bi bi-plus-lg me-1"></i>New Batch
    </button>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Batches</span></div>
      <div class="list-group list-group-flush">
        <?php if (empty($batches)): ?>
          <div class="text-center text-muted py-3 small">No batches for this session</div>
        <?php else: foreach($batches as $b): ?>
        <a href="?session_id=<?= $session_id ?>&batch=<?= $b['id'] ?>"
           class="list-group-item list-group-item-action <?= $activeBatch==$b['id']?'active':'' ?>">
          <div class="d-flex justify-content-between">
            <span class="fw-600"><?= e($b['batch_name']) ?></span>
            <span class="badge bg-secondary"><?= $b['member_count'] ?></span>
          </div>
          <div class="small <?= $activeBatch==$b['id']?'text-white-50':'text-muted' ?>">
            <?= ucfirst(str_replace('_',' ',e($b['batch_type']))) ?> · <?= e($b['teacher_name']??'No teacher') ?>
          </div>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <?php if ($activeBatch && $curBatch): ?>
    <div class="card mb-2">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h6 class="card-title mb-0"><?= e($curBatch['batch_name']) ?></h6>
          <small class="text-muted"><?= ucfirst(str_replace('_',' ',$curBatch['batch_type'])) ?> · <?= $curBatch['member_count'] ?> members</small>
        </div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#batchModal"
                  onclick="setBatchForm(<?= htmlspecialchars(json_encode($curBatch),ENT_QUOTES) ?>)">
            <i class="bi bi-pencil me-1"></i>Edit
          </button>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_batch">
            <input type="hidden" name="id"     value="<?= $activeBatch ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-soft-delete="<?= e($curBatch['batch_name']) ?>"
                    data-soft-delete-warn="All subjects, fee configs and exams in this batch will also be deleted."
                    data-form-id="delbatch<?= $activeBatch ?>"
                    id="delbatch<?= $activeBatch ?>">
              <i class="bi bi-trash"></i>
            </button>
          </form>
        </div>
      </div>
    </div>

    <ul class="nav nav-tabs mb-2">
      <?php foreach (['members'=>'Members','subjects'=>'Subjects','fees'=>'Fees','exams'=>'Exams'] as $btk=>$btl): ?>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab===$btk?'active':'' ?>"
           href="?session_id=<?= $session_id ?>&batch=<?= $activeBatch ?>&btab=<?= $btk ?>">
          <?= $btl ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>

    <!-- ── MEMBERS TAB ────────────────────────────────────────── -->
    <?php if ($activeTab === 'members'): ?>
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="card-title mb-0">Members (<?= count($batchMembers) ?>)</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th class="ps-3">Student</th><th>ID</th><th>Class</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($batchMembers)): ?>
              <tr><td colspan="5"><div class="text-center text-muted py-3 small">No members yet. Add students below.</div></td></tr>
            <?php else: foreach($batchMembers as $m): ?>
            <tr>
              <td class="fw-600 ps-3"><?= e($m['student_name']) ?></td>
              <td><code class="small"><?= e($m['student_id_no']??'') ?></code></td>
              <td class="small"><?= e($m['class_name']??'—') ?> <?= $m['section_name'] ? '('.$m['section_name'].')' : '' ?></td>
              <td class="small text-muted"><?= fmt_date($m['joined_at']) ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action"     value="remove_member">
                  <input type="hidden" name="batch_id"   value="<?= $activeBatch ?>">
                  <input type="hidden" name="student_id" value="<?= $m['student_id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.15rem .4rem;"
                          data-confirm="Remove <?= e(addslashes($m['student_name'])) ?> from batch?">
                    <i class="bi bi-person-dash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Quick add individual student -->
      <?php if (!empty($availableStudents)): ?>
      <div class="card-footer py-2 px-3">
        <form method="POST" class="d-flex gap-2 align-items-center">
          <?= csrf_field() ?>
          <input type="hidden" name="action"   value="enroll">
          <input type="hidden" name="batch_id" value="<?= $activeBatch ?>">
          <select name="student_id" class="form-select form-select-sm">
            <option value="">— Add a student —</option>
            <?php foreach($availableStudents as $av): ?>
              <option value="<?= $av['id'] ?>"><?= e($av['name']) ?> <?= $av['class_name'] ? '('.$av['class_name'].')' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-success flex-shrink-0">
            <i class="bi bi-plus-lg me-1"></i>Add
          </button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── SUBJECTS TAB ─────────────────────────────────────── -->
    <?php elseif ($activeTab === 'subjects'): ?>
    <div class="card">
      <div class="card-header"><h6 class="card-title mb-0">Batch Subjects (<?= count($batchSubjects) ?>)</h6></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th class="ps-3">Subject</th><th>Teacher</th><th>Full Marks</th><th>Pass Marks</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($batchSubjects)): ?>
            <tr><td colspan="5"><div class="text-center text-muted py-3 small">No subjects added yet.</div></td></tr>
            <?php else: foreach($batchSubjects as $bs): ?>
            <tr>
              <td class="fw-600 ps-3"><?= e($bs['subject_name']) ?></td>
              <td class="small"><?= e($bs['teacher_name'] ?? '—') ?></td>
              <td><?= $bs['full_marks'] ?></td>
              <td><?= $bs['pass_marks'] ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_batch_subject">
                  <input type="hidden" name="id"     value="<?= $bs['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.15rem .4rem;"
                          data-confirm="Remove subject?"><i class="bi bi-x"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer py-2 px-3">
        <form method="POST" class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action"   value="save_batch_subject">
          <input type="hidden" name="batch_id" value="<?= $activeBatch ?>">
          <div class="col-md-4">
            <select name="subject_id" class="form-select form-select-sm" required>
              <option value="">— Select Subject —</option>
              <?php foreach($subjects as $sub): ?><option value="<?= $sub['id'] ?>"><?= e($sub['subject_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <select name="teacher_id" class="form-select form-select-sm">
              <option value="">— Teacher —</option>
              <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2"><input type="number" name="full_marks" class="form-control form-control-sm" placeholder="Full" value="100" min="1"></div>
          <div class="col-md-2"><input type="number" name="pass_marks" class="form-control form-control-sm" placeholder="Pass" value="33" min="1"></div>
          <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button></div>
        </form>
      </div>
    </div>

    <!-- ── FEES TAB ─────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'fees'): ?>
    <div class="card">
      <div class="card-header"><h6 class="card-title mb-0">Fee Structures (<?= count($batchFees) ?>)</h6></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th class="ps-3">Fee Name</th><th>Amount</th><th>Type</th><th>Frequency</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($batchFees)): ?>
            <tr><td colspan="5"><div class="text-center text-muted py-3 small">No fee structures yet.</div></td></tr>
            <?php else: foreach($batchFees as $bf): ?>
            <tr>
              <td class="fw-600 ps-3"><?= e($bf['fee_name']) ?></td>
              <td><?= money($bf['amount']) ?></td>
              <td><span class="badge bg-light text-dark"><?= ucfirst($bf['fee_type']) ?></span></td>
              <td><span class="badge bg-light text-dark"><?= ucfirst($bf['frequency']) ?></span></td>
              <td>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_batch_fee">
                  <input type="hidden" name="id"     value="<?= $bf['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:.15rem .4rem;"
                          data-confirm="Remove fee?"><i class="bi bi-x"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer py-2 px-3">
        <form method="POST" class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action"   value="save_batch_fee">
          <input type="hidden" name="batch_id" value="<?= $activeBatch ?>">
          <div class="col-md-3"><input type="text" name="fee_name" class="form-control form-control-sm" placeholder="Fee name *" required></div>
          <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="Amount" step="0.01" required></div>
          <div class="col-md-2">
            <select name="fee_type" class="form-select form-select-sm">
              <option value="one_time">One-Time</option>
              <option value="recurring">Recurring</option>
              <option value="extra">Extra</option>
            </select>
          </div>
          <div class="col-md-2">
            <select name="frequency" class="form-select form-select-sm">
              <option value="once">Once</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
            </select>
          </div>
          <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button></div>
        </form>
      </div>
    </div>

    <!-- ── EXAMS TAB ─────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'exams'): ?>
    <div class="card">
      <div class="card-header"><h6 class="card-title mb-0">Batch Exams (<?= count($batchExams) ?>)</h6></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th class="ps-3">Exam Name</th><th>Date</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($batchExams)): ?>
            <tr><td colspan="4"><div class="text-center text-muted py-3 small">No exams yet.</div></td></tr>
            <?php else: foreach($batchExams as $be): ?>
            <tr>
              <td class="fw-600 ps-3"><?= e($be['exam_name']) ?></td>
              <td class="small"><?= fmt_date($be['exam_date']) ?></td>
              <td><span class="badge bg-secondary"><?= ucfirst(str_replace('_',' ',$be['status'])) ?></span></td>
              <td class="small text-muted">
                <?php if (!empty($batchSubjects)): ?>
                  <a href="?session_id=<?= $session_id ?>&batch=<?= $activeBatch ?>&btab=exams&mark_exam=<?= $be['id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:.15rem .4rem;">
                    <i class="bi bi-pencil"></i> Marks
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer py-2 px-3">
        <form method="POST" class="row g-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action"   value="save_batch_exam">
          <input type="hidden" name="batch_id" value="<?= $activeBatch ?>">
          <div class="col-md-5"><input type="text" name="exam_name" class="form-control form-control-sm" placeholder="Exam name *" required></div>
          <div class="col-md-4"><input type="date" name="exam_date" class="form-control form-control-sm"></div>
          <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Exam</button></div>
        </form>
      </div>
    </div>
    <?php endif; /* end activeTab check */ ?>

    <?php else: /* no active batch selected */ ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-people"></i><p>Select a batch from the list to manage it.</p></div>
    </div></div>
    <?php endif; /* end activeBatch && curBatch */ ?>

  </div><!-- /col-md-8 -->
</div><!-- /row -->

<div class="modal fade" id="batchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_batch"><input type="hidden" name="id" id="bt_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="batchModalTitle">New Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Batch Name *</label><input type="text" name="batch_name" id="bt_name" class="form-control" required></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Type</label>
              <select name="batch_type" id="bt_type" class="form-select">
                <?php foreach(['scholarship'=>'Scholarship Prep','talent_pool'=>'Talent Pool','remedial'=>'Remedial','model_test'=>'Model Test','coaching'=>'Coaching','other'=>'Other'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Teacher / Coach</label>
              <select name="teacher_id" id="bt_teacher" class="form-select">
                <option value="">— None —</option>
                <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" id="bt_start" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" id="bt_end" class="form-control"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="bt_desc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setBatchForm(b) {
  document.getElementById('batchModalTitle').textContent = b && b.id ? 'Edit Batch' : 'New Batch';
  document.getElementById('bt_id').value      = b && b.id ? b.id : 0;
  document.getElementById('bt_name').value    = b ? b.batch_name : '';
  document.getElementById('bt_type').value    = b ? b.batch_type : 'other';
  document.getElementById('bt_teacher').value = b ? (b.teacher_id||'') : '';
  document.getElementById('bt_start').value   = b ? (b.start_date||'') : '';
  document.getElementById('bt_end').value     = b ? (b.end_date||'') : '';
  document.getElementById('bt_desc').value    = b ? (b.description||'') : '';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
