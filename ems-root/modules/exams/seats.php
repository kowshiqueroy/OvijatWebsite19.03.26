<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Seat Plans';
$breadcrumbs = ['Examinations' => 'index.php', 'Seat Plans' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['seats.manage']);

$pdo     = db();
$exam_id = int_param('exam_id', 0, $_GET);
$room_id = int_param('room_id', 0, $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Generate seats for selected rooms
    if ($action === 'generate') {
        $eid       = int_param('exam_id', 0, $_POST);
        $room_ids  = $_POST['room_ids'] ?? [];
        $overrides = $_POST['overrides'] ?? []; // contains custom benches/bench_capacity per room

        if ($eid && !empty($room_ids)) {
            // Get all students for this exam's classes (not yet assigned to ANY room for this exam)
            $classIds = $pdo->prepare('SELECT class_id FROM exam_class_map WHERE exam_id=?');
            $classIds->execute([$eid]);
            $cids = $classIds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($cids)) {
                // Get unassigned students, mixing classes for anti-cheat
                $inClause = implode(',', array_map('intval', $cids));
                $students = $pdo->query(
                    "SELECT se.student_id, se.class_id, se.roll_number 
                     FROM student_enrollments se
                     WHERE se.class_id IN ($inClause) AND se.status='active'
                     AND se.student_id NOT IN (SELECT student_id FROM exam_seats WHERE exam_id=$eid)
                     ORDER BY se.roll_number"
                )->fetchAll();

                // Interleave by class for anti-cheat
                $byClass = [];
                foreach ($students as $s) {
                    $byClass[$s['class_id']][] = $s;
                }
                $interleaved = [];
                while (!empty($byClass)) {
                    foreach ($byClass as $cid => &$list) {
                        if (empty($list)) {
                            unset($byClass[$cid]);
                            continue;
                        }
                        $interleaved[] = array_shift($list);
                    }
                }

                $assignedCount = 0;
                $stmt = $pdo->prepare('INSERT INTO exam_seats (exam_id, room_id, student_id, seat_number, row_no, col_no) VALUES (?, ?, ?, ?, ?, ?)');

                // Assign students to chosen rooms
                foreach ($room_ids as $rid) {
                    $rid = (int)$rid;
                    if (empty($interleaved)) break;

                    // Get room defaults
                    $roomStmt = $pdo->prepare('SELECT benches_count, bench_capacity FROM rooms WHERE id=?');
                    $roomStmt->execute([$rid]);
                    $roomData = $roomStmt->fetch() ?: ['benches_count' => 10, 'bench_capacity' => 2];

                    $benches = max(1, int_param('benches_' . $rid, (int)$roomData['benches_count'], $_POST));
                    $capacityPerBench = max(1, int_param('bench_capacity_' . $rid, (int)$roomData['bench_capacity'], $_POST));
                    $totalCapacity = $benches * $capacityPerBench;

                    // Find how many are already assigned to this room
                    $exCountStmt = $pdo->prepare('SELECT COUNT(*) FROM exam_seats WHERE exam_id=? AND room_id=?');
                    $exCountStmt->execute([$eid, $rid]);
                    $alreadyAssigned = (int)$exCountStmt->fetchColumn();

                    $availableSeats = $totalCapacity - $alreadyAssigned;
                    if ($availableSeats <= 0) continue;

                    // Slice students to fit this room
                    $toAssign = array_slice($interleaved, 0, $availableSeats);
                    $interleaved = array_slice($interleaved, $availableSeats); // remove assigned from pool

                    $seatNum = $alreadyAssigned + 1;
                    foreach ($toAssign as $s) {
                        $row = ceil($seatNum / $capacityPerBench);
                        $col = (($seatNum - 1) % $capacityPerBench) + 1;
                        $seatLabel = 'R' . str_pad($rid, 2, '0', STR_PAD_LEFT) . '-B' . str_pad($row, 2, '0', STR_PAD_LEFT) . '-S' . $col;
                        $stmt->execute([$eid, $rid, $s['student_id'], $seatLabel, $row, $col]);
                        $seatNum++;
                        $assignedCount++;
                    }
                }
                flash('success', $assignedCount . ' students assigned to selected rooms.');
            } else {
                flash('error', 'No classes mapped to this exam.');
            }
        } else {
            flash('error', 'Please select at least one room.');
        }
        header("Location: seats.php?exam_id=$eid");
        exit;
    }

    // Clear seat plan for a specific room or whole exam
    if ($action === 'clear') {
        $eid = int_param('exam_id', 0, $_POST);
        $rid = int_param('room_id', 0, $_POST);
        if ($rid) {
            $pdo->prepare('DELETE FROM exam_seats WHERE exam_id=? AND room_id=?')->execute([$eid, $rid]);
            flash('success', 'Seat plan cleared for this room.');
        } else {
            $pdo->prepare('DELETE FROM exam_seats WHERE exam_id=?')->execute([$eid]);
            flash('success', 'Seat plan cleared for all rooms.');
        }
        header("Location: seats.php?exam_id=$eid");
        exit;
    }
}

// Load dropdowns
$allExams = $pdo->query('SELECT id, exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();
$rooms    = $pdo->query('SELECT id, room_name, capacity, benches_count, bench_capacity FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll();

$exam = null;
if ($exam_id) {
    $s = $pdo->prepare('SELECT * FROM exams WHERE id=?');
    $s->execute([$exam_id]);
    $exam = $s->fetch();
}

$seatPlan = [];
$roomSummary = [];
if ($exam_id) {
    $summary = $pdo->query("SELECT es.room_id, r.room_name, r.capacity, COUNT(es.id) as assigned 
                            FROM exam_seats es 
                            JOIN rooms r ON r.id=es.room_id 
                            WHERE es.exam_id=$exam_id 
                            GROUP BY es.room_id")->fetchAll();
    foreach ($summary as $s) {
        $roomSummary[$s['room_id']] = $s;
    }
}

if ($exam_id && $room_id) {
    $sp = $pdo->query(
        "SELECT es.*, es.seat_number, sp.first_name, sp.last_name, c.class_name, se.roll_number 
         FROM exam_seats es 
         JOIN student_profiles sp ON sp.user_id=es.student_id 
         JOIN student_enrollments se ON se.student_id=es.student_id AND se.status='active' 
         JOIN classes c ON c.id=se.class_id 
         WHERE es.exam_id=$exam_id AND es.room_id=$room_id 
         ORDER BY es.row_no, es.col_no"
    )->fetchAll();
    foreach ($sp as $s) {
        $seatPlan[$s['row_no']][$s['col_no']] = $s;
    }
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Exam Seat Plans</h1>
  <div class="d-flex gap-2">
    <?php if ($exam_id): ?>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#genModal"><i class="bi bi-magic me-1"></i>Generate Seat Plan</button>
      <?php if (!empty($roomSummary)): ?>
        <a href="seat_tokens.php?exam_id=<?= $exam_id ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-ticket-perforated me-1"></i>Print Seat Tokens</a>
        <form method="POST" class="d-inline" onsubmit="return confirm('Clear entire seat plan for this exam?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear">
          <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
          <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3 me-1"></i>Clear All</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Exam Selector -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-600">Select Exam</label>
        <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Exam —</option>
          <?php foreach ($allExams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $exam_id == $e['id'] ? 'selected' : '' ?>><?= e($e['exam_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($exam_id): ?>
        <div class="col-md-3">
          <label class="form-label small fw-600">Select Room to View Layout</label>
          <select name="room_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— Overview (All Rooms) —</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" <?= $room_id == $r['id'] ? 'selected' : '' ?>><?= e($r['room_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($exam_id && !$room_id): ?>
  <!-- Overview of Rooms -->
  <div class="row g-3">
    <?php foreach ($rooms as $r):
      $rs = $roomSummary[$r['id']] ?? null;
      $cap = $r['benches_count'] * $r['bench_capacity'];
    ?>
      <div class="col-md-4">
        <div class="card h-100 <?= $rs ? 'border-primary' : '' ?>">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="fw-700 fs-5"><?= e($r['room_name']) ?></div>
              <div class="small text-muted mb-2">Benches: <?= $r['benches_count'] ?> (<?= $r['bench_capacity'] ?> seats/bench) &bull; Capacity: <?= $cap ?></div>
              <?php if ($rs): ?>
                <div class="progress mb-2" style="height: 6px;">
                  <div class="progress-bar" role="progressbar" style="width: <?= ($rs['assigned']/$cap)*100 ?>%;"></div>
                </div>
                <span class="badge bg-success"><?= $rs['assigned'] ?> / <?= $cap ?> Seats Assigned</span>
              <?php else: ?>
                <span class="badge bg-light text-dark border">No seats assigned</span>
              <?php endif; ?>
            </div>
            <div class="mt-3 d-flex gap-2">
              <a href="?exam_id=<?= $exam_id ?>&room_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-eye me-1"></i>View Layout</a>
              <?php if ($rs): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Clear seat plan for this room?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="clear">
                  <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                  <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($exam_id && $room_id && !empty($seatPlan)): ?>
  <!-- View Specific Room Bench Grid Layout -->
  <div class="card">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between bg-light">
      <span class="card-title mb-0"><i class="bi bi-border-all me-2"></i>Bench Layout: <?= e($exam['exam_name']) ?> — <?= e($rooms[array_search($room_id, array_column($rooms, 'id'))]['room_name']) ?></span>
      <div class="d-flex gap-2">
        <a href="seat_tokens.php?exam_id=<?= $exam_id ?>&room_id=<?= $room_id ?>" class="btn btn-sm btn-success"><i class="bi bi-ticket-perforated me-1"></i>Print Tokens</a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print Layout</button>
      </div>
    </div>
    <div class="card-body">
      <div class="text-center mb-4"><div class="px-4 py-2 bg-dark text-white rounded d-inline-block fw-bold shadow-sm">📋 BOARD / TEACHER DESK</div></div>
      
      <?php 
      ksort($seatPlan);
      foreach ($seatPlan as $rowNo => $cols): 
      ?>
        <div class="d-flex justify-content-center align-items-center gap-3 mb-4 flex-wrap">
          <div class="fw-700 text-muted me-2 border-end pe-3" style="min-width: 80px;">Bench <?= $rowNo ?></div>
          <div class="d-flex gap-2">
            <?php 
            ksort($cols);
            foreach ($cols as $colNo => $seat): 
            ?>
              <div class="border rounded text-center p-3 shadow-sm" style="min-width:160px; background:#fff; border-top: 3px solid var(--ems-primary) !important;">
                <div class="fw-bold text-primary small mb-1"><?= e($seat['seat_number']) ?></div>
                <div class="fw-bold"><?= e($seat['first_name'] . ' ' . $seat['last_name']) ?></div>
                <div class="text-muted small">Roll: <?= e($seat['roll_number']) ?> &bull; <?= e($seat['class_name']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<?php elseif ($exam_id && $room_id): ?>
  <div class="card"><div class="card-body"><div class="empty-state py-5 text-center"><i class="bi bi-grid-3x3-gap fs-1 text-muted"></i><p class="mt-3">No seats assigned in this room yet. Use "Generate Seat Plan" to configure.</p></div></div></div>
<?php else: ?>
  <div class="card"><div class="card-body"><div class="empty-state py-5 text-center"><i class="bi bi-clipboard2 fs-1 text-muted"></i><p class="mt-3">Please select an exam above to view or plan seating arrangements.</p></div></div></div>
<?php endif; ?>

<!-- Generate Seats Multi-Room Modal -->
<?php if ($exam_id): ?>
<div class="modal fade" id="genModal" tabindex="-1" aria-labelledby="genModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-600" id="genModalLabel">Generate Seat Plan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>Select which rooms to use. You can customize the number of benches and capacity per bench for each room dynamically. Students will be interleaved by class for anti-cheating protocols.
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered align-middle small">
              <thead class="table-light">
                <tr>
                  <th style="width: 50px;" class="text-center">Use</th>
                  <th>Room Name</th>
                  <th style="width: 140px;">Benches Count</th>
                  <th style="width: 140px;">Bench Capacity</th>
                  <th>Calculated Seats</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rooms as $r): 
                  $cap = $r['benches_count'] * $r['bench_capacity'];
                ?>
                <tr>
                  <td class="text-center">
                    <input type="checkbox" name="room_ids[]" value="<?= $r['id'] ?>" class="form-check-input room-checkbox" id="chk-rm-<?= $r['id'] ?>">
                  </td>
                  <td>
                    <label for="chk-rm-<?= $r['id'] ?>" class="fw-bold d-block pointer"><?= e($r['room_name']) ?></label>
                    <span class="text-muted text-xs">Default capacity: <?= $cap ?></span>
                  </td>
                  <td>
                    <input type="number" name="benches_<?= $r['id'] ?>" value="<?= $r['benches_count'] ?: 10 ?>" class="form-control form-control-sm bench-input" min="1" max="50" oninput="recalcCapacity(<?= $r['id'] ?>)">
                  </td>
                  <td>
                    <input type="number" name="bench_capacity_<?= $r['id'] ?>" value="<?= $r['bench_capacity'] ?: 2 ?>" class="form-control form-control-sm cap-input" min="1" max="10" oninput="recalcCapacity(<?= $r['id'] ?>)">
                  </td>
                  <td>
                    <span class="fw-bold text-success" id="calc-cap-<?= $r['id'] ?>"><?= $cap ?></span> seats
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-magic me-1"></i>Generate</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function recalcCapacity(id) {
  const benches = parseInt(document.getElementsByName('benches_' + id)[0].value) || 0;
  const capacity = parseInt(document.getElementsByName('bench_capacity_' + id)[0].value) || 0;
  document.getElementById('calc-cap-' + id).innerText = benches * capacity;
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
