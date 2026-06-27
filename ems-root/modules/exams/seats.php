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
                $examSessionId = (int)$pdo->query("SELECT session_id FROM exams WHERE id = $eid")->fetchColumn();
                $students = $pdo->query(
                    "SELECT se.student_id, se.class_id, se.roll_number 
                     FROM student_enrollments se
                     WHERE se.class_id IN ($inClause) AND se.status='active' AND se.session_id = $examSessionId
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

                // Assign students to chosen rooms
                foreach ($room_ids as $rid) {
                    $rid = (int)$rid;
                    if (empty($interleaved)) break;

                    // Get room layout (supports custom JSON or simple benches×bench_capacity)
                    $roomStmt = $pdo->prepare('SELECT benches_count, bench_capacity, layout_json FROM rooms WHERE id=?');
                    $roomStmt->execute([$rid]);
                    $roomData = $roomStmt->fetch() ?: ['benches_count' => 10, 'bench_capacity' => 2, 'layout_json' => null];

                    // Build ordered seat list from layout
                    $seatPositions = [];
                    if (!empty($roomData['layout_json'])) {
                        $lj = json_decode($roomData['layout_json'], true);
                        if (isset($lj['cols']) && is_array($lj['cols'])) {
                            // Interleave across columns for anti-cheat:
                            // seat order = col1-b1-s1, col2-b1-s1, col3-b1-s1, col1-b1-s2, col2-b1-s2...
                            // Actually: fill column by column (natural order), anti-cheat already done by class mixing
                            foreach ($lj['cols'] as $colIdx => $benches) {
                                foreach ($benches as $benchIdx => $seatsInBench) {
                                    for ($si = 0; $si < $seatsInBench; $si++) {
                                        $seatPositions[] = [
                                            'col_block' => $colIdx + 1,
                                            'row_no'    => $benchIdx + 1,
                                            'col_no'    => $si + 1,
                                            'label'     => 'C'.($colIdx+1).'-B'.($benchIdx+1).'-S'.($si+1),
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    if (empty($seatPositions)) {
                        // Simple mode fallback
                        $benches = max(1, int_param('benches_' . $rid, (int)($roomData['benches_count'] ?? 10), $_POST));
                        $benchCap = max(1, int_param('bench_capacity_' . $rid, (int)($roomData['bench_capacity'] ?? 2), $_POST));
                        for ($b = 0; $b < $benches; $b++) {
                            for ($s = 0; $s < $benchCap; $s++) {
                                $seatPositions[] = [
                                    'col_block' => 1,
                                    'row_no'    => $b + 1,
                                    'col_no'    => $s + 1,
                                    'label'     => 'B'.str_pad($b+1,2,'0',STR_PAD_LEFT).'-S'.($s+1),
                                ];
                            }
                        }
                    }

                    $totalCapacity = count($seatPositions);

                    // Find how many are already assigned to this room
                    $exCountStmt = $pdo->prepare('SELECT COUNT(*) FROM exam_seats WHERE exam_id=? AND room_id=?');
                    $exCountStmt->execute([$eid, $rid]);
                    $alreadyAssigned = (int)$exCountStmt->fetchColumn();

                    // Skip already-used positions
                    $availablePositions = array_slice($seatPositions, $alreadyAssigned);
                    if (empty($availablePositions)) continue;

                    // Slice students to fit available seats
                    $toAssign = array_slice($interleaved, 0, count($availablePositions));
                    $interleaved = array_slice($interleaved, count($toAssign));

                    $stmt = $pdo->prepare('INSERT INTO exam_seats (exam_id, room_id, student_id, seat_number, row_no, col_no, col_block) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    foreach ($toAssign as $i => $s) {
                        $pos = $availablePositions[$i];
                        $stmt->execute([$eid, $rid, $s['student_id'], $pos['label'], $pos['row_no'], $pos['col_no'], $pos['col_block']]);
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
$rooms    = $pdo->query('SELECT id, room_name, capacity, benches_count, bench_capacity, layout_json FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll();

// Helper: compute total capacity and seat list from a room
function room_seat_capacity(array $r): int {
    if (!empty($r['layout_json'])) {
        $lj = json_decode($r['layout_json'], true);
        if (isset($lj['cols'])) {
            $total = 0;
            foreach ($lj['cols'] as $col) $total += array_sum($col);
            return $total;
        }
    }
    return max(1, (int)$r['benches_count']) * max(1, (int)$r['bench_capacity']);
}
function room_layout_label(array $r): string {
    if (!empty($r['layout_json'])) {
        $lj = json_decode($r['layout_json'], true);
        if (isset($lj['cols'])) {
            $numCols  = count($lj['cols']);
            $colSizes = array_map(fn($c) => count($c) . 'B', $lj['cols']);
            return "$numCols cols (" . implode(', ', $colSizes) . ")";
        }
    }
    return ($r['benches_count'] ?? 10) . ' rows × ' . ($r['bench_capacity'] ?? 2) . ' seats';
}

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
      $rs  = $roomSummary[$r['id']] ?? null;
      $cap = room_seat_capacity($r);
      $layoutLabel = room_layout_label($r);
    ?>
      <div class="col-md-4">
        <div class="card h-100 <?= $rs ? 'border-primary' : '' ?>">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <div class="fw-700 fs-5"><?= e($r['room_name']) ?></div>
              <div class="small text-muted mb-2">
                Layout: <?= e($layoutLabel) ?> &bull; <strong><?= $cap ?> seats</strong>
              </div>
              <?php if ($rs): ?>
                <div class="progress mb-2" style="height: 6px;">
                  <div class="progress-bar" role="progressbar" style="width: <?= $cap > 0 ? round(($rs['assigned']/$cap)*100) : 0 ?>%;"></div>
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
  <?php
    // Detect current room data for layout-aware display
    $currentRoomIdx = array_search($room_id, array_column($rooms, 'id'));
    $currentRoom    = $currentRoomIdx !== false ? $rooms[$currentRoomIdx] : null;
    $hasCustomLayout = $currentRoom && !empty($currentRoom['layout_json']);
    $currentRoomName = $currentRoom['room_name'] ?? 'Room';

    // Rebuild seatPlan indexed by col_block, then row_no, then col_no
    $seatPlanByBlock = [];
    if ($exam_id && $room_id) {
        $sp2 = $pdo->query(
            "SELECT es.*, es.seat_number, es.row_no, es.col_no,
                    COALESCE(es.col_block, 1) as col_block,
                    sp.first_name, sp.last_name, c.class_name, se.roll_number
             FROM exam_seats es
             JOIN student_profiles sp ON sp.user_id=es.student_id
             JOIN student_enrollments se ON se.student_id=es.student_id AND se.status='active'
             JOIN classes c ON c.id=se.class_id
             WHERE es.exam_id=$exam_id AND es.room_id=$room_id
             ORDER BY es.col_block, es.row_no, es.col_no"
        )->fetchAll();
        foreach ($sp2 as $s) {
            $seatPlanByBlock[$s['col_block']][$s['row_no']][$s['col_no']] = $s;
        }
    }
    $numCols = count($seatPlanByBlock);
  ?>
  <!-- Seat Layout Display -->
  <div class="card">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between bg-light no-print">
      <span class="card-title mb-0">
        <i class="bi bi-border-all me-2"></i>
        <?= e($exam['exam_name']) ?> — <?= e($currentRoomName) ?>
        <?php if ($hasCustomLayout): ?>
          <span class="badge bg-primary ms-2" style="font-size:.7rem;">Custom Layout</span>
        <?php endif; ?>
      </span>
      <div class="d-flex gap-2">
        <a href="seat_tokens.php?exam_id=<?= $exam_id ?>&room_id=<?= $room_id ?>" class="btn btn-sm btn-success">
          <i class="bi bi-ticket-perforated me-1"></i>Print Tokens
        </a>
        <button onclick="EMS.printTable('seat-layout-tbl')" class="btn btn-sm btn-outline-secondary no-print">
          <i class="bi bi-printer me-1"></i>Print Layout
        </button>
      </div>
    </div>
    <div class="card-body overflow-auto">
      <!-- Board indicator -->
      <div class="text-center mb-4">
        <div class="px-4 py-2 bg-dark text-white rounded d-inline-block fw-bold shadow-sm small">
          📋 BOARD / INVIGILATOR DESK
        </div>
      </div>

      <?php if ($numCols > 1): ?>
        <!-- Multi-column layout: show columns side by side -->
        <div class="d-flex gap-4 justify-content-center flex-wrap" id="seat-layout-tbl">
          <?php foreach ($seatPlanByBlock as $colBlock => $benches): ?>
          <div style="flex:1;min-width:160px;max-width:220px;">
            <div class="text-center fw-700 text-primary border-bottom pb-1 mb-2 small">
              Column <?= $colBlock ?>
            </div>
            <?php ksort($benches); foreach ($benches as $benchNo => $seats): ?>
            <div class="mb-2">
              <div class="text-center text-muted mb-1" style="font-size:.7rem;">Bench <?= $benchNo ?></div>
              <div class="d-flex gap-1 justify-content-center flex-wrap">
                <?php ksort($seats); foreach ($seats as $s): ?>
                <div class="border rounded text-center p-1 shadow-sm" style="min-width:100px;border-top:3px solid #1a56db !important;background:#fff;">
                  <div class="fw-700 text-primary" style="font-size:.65rem;"><?= e($s['seat_number']) ?></div>
                  <div class="fw-700 small"><?= e($s['first_name'][0].'. '.$s['last_name']) ?></div>
                  <div class="text-muted" style="font-size:.65rem;">R:<?= $s['roll_number'] ?> · <?= e($s['class_name']) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <!-- Simple single-column layout: rows of benches -->
        <div id="seat-layout-tbl">
          <?php foreach (($seatPlanByBlock[1] ?? []) as $benchNo => $seats): ?>
          <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <div class="fw-700 text-muted border-end pe-3 text-end" style="min-width:80px;font-size:.85rem;">
              Bench <?= $benchNo ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <?php ksort($seats); foreach ($seats as $s): ?>
              <div class="border rounded text-center p-2 shadow-sm" style="min-width:150px;border-top:3px solid #1a56db !important;background:#fff;">
                <div class="fw-700 text-primary small mb-1"><?= e($s['seat_number']) ?></div>
                <div class="fw-700"><?= e($s['first_name'].' '.$s['last_name']) ?></div>
                <div class="text-muted small">Roll: <?= e($s['roll_number']) ?> &bull; <?= e($s['class_name']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

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
          <div class="alert alert-info small mb-3 d-flex gap-2">
            <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
            <div>
              Select rooms to use. Seat capacity is taken from each room's configured layout
              (set in <strong>Academic → Rooms → Edit Layout</strong>). Students from different
              classes are interleaved for anti-cheat seating.
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered align-middle small">
              <thead class="table-dark">
                <tr>
                  <th class="text-center" style="width:50px;">Use</th>
                  <th>Room</th>
                  <th>Layout</th>
                  <th class="text-center">Exam Capacity</th>
                  <th class="text-center">Already Assigned</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rooms as $r):
                  $cap = room_seat_capacity($r);
                  $aStmt = $pdo->prepare('SELECT COUNT(*) FROM exam_seats WHERE exam_id=? AND room_id=?');
                  $aStmt->execute([$exam_id, $r['id']]);
                  $alreadyInRoom = (int)$aStmt->fetchColumn();
                  $available     = $cap - $alreadyInRoom;
                  $isCustom      = !empty($r['layout_json']);
                ?>
                <tr <?= $alreadyInRoom > 0 ? 'class="table-warning-subtle"' : '' ?>>
                  <td class="text-center">
                    <input type="checkbox" name="room_ids[]" value="<?= $r['id'] ?>"
                           class="form-check-input" id="chk-<?= $r['id'] ?>"
                           <?= $available <= 0 ? 'disabled' : '' ?>>
                  </td>
                  <td>
                    <label for="chk-<?= $r['id'] ?>" class="fw-700 mb-0 d-block"><?= e($r['room_name']) ?></label>
                    <small class="text-muted">Floor <?= $r['floor'] ?></small>
                  </td>
                  <td>
                    <span class="badge bg-<?= $isCustom ? 'primary' : 'secondary' ?> me-1">
                      <?= $isCustom ? 'Custom' : 'Simple' ?>
                    </span>
                    <span class="text-muted small"><?= e(room_layout_label($r)) ?></span>
                    <?php if (!$isCustom): ?>
                      <a href="<?= $MOD ?? '../' ?>modules/academic/rooms.php" class="ms-1 small text-warning"
                         title="Set custom layout in Rooms" target="_blank">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                    <?php endif; ?>
                  </td>
                  <td class="text-center fw-700 text-success"><?= $cap ?></td>
                  <td class="text-center">
                    <?php if ($alreadyInRoom > 0): ?>
                      <span class="badge bg-warning text-dark"><?= $alreadyInRoom ?> assigned</span>
                      <span class="text-muted small d-block"><?= $available ?> free</span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
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
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
