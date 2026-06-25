<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Seat Plans';
$breadcrumbs = ['Examinations' => 'index.php', 'Seat Plans' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['seats.manage']);

$pdo     = db();
$exam_id = int_param('exam_id',0,$_GET);
$room_id = int_param('room_id',0,$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $eid    = int_param('exam_id',0,$_POST);
        $rid    = int_param('room_id',0,$_POST);
        $cols   = max(2,int_param('columns',4,$_POST));

        // Get room capacity
        $cap = (int)$pdo->prepare('SELECT capacity FROM rooms WHERE id=?')->execute([$rid]) ? 40 : 40;
        $capStmt = $pdo->prepare('SELECT capacity FROM rooms WHERE id=?');
        $capStmt->execute([$rid]);
        $cap = (int)$capStmt->fetchColumn() ?: 40;

        // Get all students for this exam's classes (not yet assigned to this room)
        $classIds = $pdo->prepare('SELECT class_id FROM exam_class_map WHERE exam_id=?');
        $classIds->execute([$eid]);
        $cids = $classIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($cids)) {
            $existingStudents = $pdo->prepare('SELECT student_id FROM exam_seats WHERE exam_id=? AND room_id=?');
            $existingStudents->execute([$eid,$rid]);
            $assigned = $existingStudents->fetchAll(PDO::FETCH_COLUMN);

            // Get unassigned students, mixing classes for anti-cheat
            $inClause = implode(',',array_map('intval',$cids));
            $students = $pdo->query(
                "SELECT se.student_id, se.class_id, se.roll_number FROM student_enrollments se
                 WHERE se.class_id IN ($inClause) AND se.status='active'
                 AND se.student_id NOT IN (SELECT student_id FROM exam_seats WHERE exam_id=$eid)"
            )->fetchAll();

            // Interleave by class for anti-cheat
            $byClass = [];
            foreach ($students as $s) $byClass[$s['class_id']][] = $s;
            $interleaved = [];
            while (!empty($byClass)) {
                foreach ($byClass as $cid => &$list) {
                    if (empty($list)) { unset($byClass[$cid]); continue; }
                    $interleaved[] = array_shift($list);
                }
            }

            // Assign seats up to capacity
            $toAssign = array_slice($interleaved, 0, $cap - count($assigned));
            $stmt = $pdo->prepare('INSERT INTO exam_seats (exam_id,room_id,student_id,seat_number,row_no,col_no) VALUES (?,?,?,?,?,?)');
            $seatNum = count($assigned) + 1;
            foreach ($toAssign as $s) {
                $row = ceil($seatNum/$cols);
                $col = (($seatNum-1) % $cols) + 1;
                $stmt->execute([$eid,$rid,$s['student_id'],'S'.str_pad($seatNum,3,'0',STR_PAD_LEFT),$row,$col]);
                $seatNum++;
            }
            flash('success', count($toAssign).' seats assigned in this room.');
        }
        header("Location: seats.php?exam_id=$eid&room_id=$rid");
        exit;
    } elseif ($action === 'clear') {
        $eid = int_param('exam_id',0,$_POST);
        $rid = int_param('room_id',0,$_POST);
        $pdo->prepare('DELETE FROM exam_seats WHERE exam_id=? AND room_id=?')->execute([$eid,$rid]);
        flash('success','Seat plan cleared for this room.');
        header("Location: seats.php?exam_id=$eid");
        exit;
    }
}

$allExams = $pdo->query('SELECT id,exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();
$rooms    = $pdo->query('SELECT id,room_name,capacity FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll();

$exam = null;
if ($exam_id) {
    $s = $pdo->prepare('SELECT * FROM exams WHERE id=?');
    $s->execute([$exam_id]);
    $exam = $s->fetch();
}

$seatPlan = [];
$roomSummary = [];
if ($exam_id) {
    $summary = $pdo->query("SELECT es.room_id, r.room_name, r.capacity, COUNT(es.id) as assigned FROM exam_seats es JOIN rooms r ON r.id=es.room_id WHERE es.exam_id=$exam_id GROUP BY es.room_id")->fetchAll();
    foreach ($summary as $s) $roomSummary[$s['room_id']] = $s;
}

if ($exam_id && $room_id) {
    $sp = $pdo->query("SELECT es.*, es.seat_number, sp.first_name, sp.last_name, c.class_name, se.roll_number FROM exam_seats es JOIN student_profiles sp ON sp.user_id=es.student_id JOIN student_enrollments se ON se.student_id=es.student_id AND se.status='active' JOIN classes c ON c.id=se.class_id WHERE es.exam_id=$exam_id AND es.room_id=$room_id ORDER BY es.row_no, es.col_no")->fetchAll();
    foreach ($sp as $s) $seatPlan[$s['row_no']][$s['col_no']] = $s;
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Exam Seat Plans</h1>
  <?php if($exam_id): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#genModal"><i class="bi bi-magic me-1"></i>Generate Seats</button>
  <?php endif; ?>
</div>

<!-- Exam selector -->
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small">Exam</label>
      <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Select Exam —</option>
        <?php foreach($allExams as $e): ?><option value="<?= $e['id'] ?>" <?= $exam_id==$e['id']?'selected':'' ?>><?= e($e['exam_name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if($exam_id): ?>
    <div class="col-md-3"><label class="form-label small">Room (view plan)</label>
      <select name="room_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— All Rooms —</option>
        <?php foreach($rooms as $r): ?><option value="<?= $r['id'] ?>" <?= $room_id==$r['id']?'selected':'' ?>><?= e($r['room_name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
  </form>
</div></div>

<?php if($exam_id && !$room_id): ?>
<!-- Room summary -->
<div class="row g-3">
  <?php foreach($rooms as $r): $rs=$roomSummary[$r['id']]??null; ?>
  <div class="col-md-4">
    <div class="card <?= $rs?'border-primary':'' ?>">
      <div class="card-body">
        <div class="fw-700"><?= e($r['room_name']) ?></div>
        <div class="small text-muted">Capacity: <?= $r['capacity'] ?></div>
        <?php if($rs): ?>
        <div class="mt-2"><span class="badge bg-success"><?= $rs['assigned'] ?> / <?= $r['capacity'] ?> assigned</span></div>
        <?php else: ?>
        <div class="mt-2 text-muted small">No seats assigned</div>
        <?php endif; ?>
        <div class="mt-2 d-flex gap-1">
          <a href="?exam_id=<?= $exam_id ?>&room_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
          <?php if($rs): ?>
          <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><input type="hidden" name="exam_id" value="<?= $exam_id ?>"><input type="hidden" name="room_id" value="<?= $r['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Clear seat plan for this room?"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif($exam_id && $room_id && !empty($seatPlan)): ?>
<!-- Seat grid -->
<div class="card">
  <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
    <span class="card-title">Seat Plan — <?= e($exam['exam_name']??'') ?></span>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
  <div class="card-body">
    <div class="text-center mb-3"><div class="p-2 bg-dark text-white rounded d-inline-block">📋 INVIGILATOR DESK</div></div>
    <?php foreach($seatPlan as $rowNo=>$cols): ?>
    <div class="d-flex gap-3 justify-content-center mb-3">
      <?php ksort($cols); foreach($cols as $colNo=>$seat): ?>
      <div class="border rounded text-center p-2" style="min-width:110px;background:#f8fafc;">
        <div class="fw-700 text-primary small"><?= e($seat['seat_number']) ?></div>
        <div class="fw-600 small"><?= e($seat['first_name'].' '.$seat['last_name']) ?></div>
        <div class="text-muted small"><?= e($seat['class_name']) ?> · <?= e($seat['roll_number']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php elseif($exam_id && $room_id): ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-grid-3x3-gap"></i><p>No seats assigned to this room yet. Use "Generate Seats" to auto-assign.</p></div></div></div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-clipboard2"></i><p>Select an exam above to manage seat plans.</p></div></div></div>
<?php endif; ?>

<!-- Generate modal -->
<?php if($exam_id): ?>
<div class="modal fade" id="genModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="generate"><input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <div class="modal-header"><h5 class="modal-title">Generate Seat Plan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Students from different classes are interleaved to minimize cheating opportunities.</div>
          <div class="mb-3"><label class="form-label">Room *</label>
            <select name="room_id" class="form-select" required>
              <option value="">— Select Room —</option>
              <?php foreach($rooms as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?> (cap: <?= $r['capacity'] ?>)</option><?php endforeach; ?>
            </select></div>
          <div><label class="form-label">Columns per Row</label>
            <input type="number" name="columns" class="form-control" value="4" min="2" max="8"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-magic me-1"></i>Generate</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
