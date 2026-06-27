<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['exams.view']);

$pdo     = db();
$exam_id = int_param('exam_id', 0, $_GET);
$room_id = int_param('room_id', 0, $_GET);

if (!$exam_id) {
    // Render selection page using the standard admin wrapper
    $page_title = 'Print Seat Tokens';
    $breadcrumbs = ['Examinations' => 'seats.php', 'Print Seat Tokens' => null];
    
    // Fetch all active/recent exams
    $allExams = $pdo->query('SELECT id, exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();
    
    require_once EMS_ROOT . '/includes/header.php';
    ?>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm border-0">
          <div class="card-header py-3 px-4 bg-light">
            <h5 class="card-title mb-0"><i class="bi bi-ticket-perforated me-2 text-primary"></i>Print Seat Tokens</h5>
          </div>
          <div class="card-body p-4">
            <form method="GET" action="seat_tokens.php" target="_blank">
              <div class="mb-3">
                <label class="form-label fw-600">Select Exam <span class="text-danger">*</span></label>
                <select name="exam_id" class="form-select" required>
                  <option value="">— Select Exam —</option>
                  <?php foreach ($allExams as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= e($e['exam_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-600">Select Room <small class="text-muted">(Optional)</small></label>
                <select name="room_id" class="form-select">
                  <option value="0">All Rooms</option>
                  <?php 
                  $rooms = $pdo->query('SELECT id, room_name FROM rooms ORDER BY room_name')->fetchAll();
                  foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-printer me-2"></i>Generate Seat Tokens
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php
    require_once EMS_ROOT . '/includes/footer.php';
    exit;
}

// Fetch exam
$exStmt = $pdo->prepare('SELECT exam_name FROM exams WHERE id = ?');
$exStmt->execute([$exam_id]);
$examName = $exStmt->fetchColumn();

if (!$examName) {
    die("Exam not found.");
}

// Fetch seat tokens
$where = "es.exam_id = :eid";
$params = [':eid' => $exam_id];
if ($room_id) {
    $where .= " AND es.room_id = :rid";
    $params[':rid'] = $room_id;
}

$stmt = $pdo->prepare(
    "SELECT es.*, sp.first_name, sp.last_name, sp.student_id_no, c.class_name, sec.section_name, r.room_name, se.roll_number
     FROM exam_seats es
     JOIN student_profiles sp ON sp.user_id = es.student_id
     JOIN student_enrollments se ON se.student_id = es.student_id AND se.status = 'active' AND se.session_id = (SELECT session_id FROM exams WHERE id = es.exam_id)
     JOIN classes c ON c.id = se.class_id
     JOIN sections sec ON sec.id = se.section_id
     JOIN rooms r ON r.id = es.room_id
     WHERE $where
     ORDER BY r.room_name, es.row_no, es.col_no"
);
$stmt->execute($params);
$tokens = $stmt->fetchAll();

if (empty($tokens)) {
    // If no tokens found, show a clean message using the admin layout
    $page_title = 'Print Seat Tokens';
    $breadcrumbs = ['Examinations' => 'seats.php', 'Print Seat Tokens' => null];
    require_once EMS_ROOT . '/includes/header.php';
    ?>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm border-0">
          <div class="card-body text-center p-5">
            <div class="mb-3 text-warning"><i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i></div>
            <h4>No Seat Assignments Found</h4>
            <p class="text-muted">No students have been assigned to rooms for the exam <strong><?= e($examName) ?></strong> yet.</p>
            <div class="d-flex gap-2 justify-content-center mt-4">
              <a href="seats.php?exam_id=<?= $exam_id ?>" class="btn btn-primary"><i class="bi bi-gear me-2"></i>Generate Seat Plan</a>
              <a href="seat_tokens.php" class="btn btn-outline-secondary">Select Another Exam</a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    require_once EMS_ROOT . '/includes/footer.php';
    exit;
}

$school_name = setting('school_name', 'EMS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seat Tokens — <?= e($examName) ?></title>
<style>
  @page {
    size: A4;
    margin: 8mm 6mm;
  }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #fff;
    color: #000;
  }
  .print-btn-bar {
    background: #f1f5f9;
    padding: 10px;
    text-align: right;
    border-bottom: 1px solid #cbd5e1;
  }
  .print-btn {
    background: #2563eb;
    color: #fff;
    border: 0;
    padding: 6px 14px;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
  }
  .tokens-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 8px;
    row-gap: 8px;
  }
  .token-card {
    border: 2px dashed #000;
    padding: 12px;
    box-sizing: border-box;
    height: 52mm; /* ~ 5 rows per A4 page */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    page-break-inside: avoid;
    background: #fff;
  }
  .token-header {
    text-align: center;
    border-bottom: 1px solid #000;
    padding-bottom: 4px;
    margin-bottom: 6px;
  }
  .token-header h3 {
    margin: 0;
    font-size: 13px;
    text-transform: uppercase;
    font-weight: bold;
  }
  .token-header p {
    margin: 1px 0 0;
    font-size: 10px;
    color: #334155;
  }
  .token-details {
    width: 100%;
    font-size: 11px;
    border-collapse: collapse;
  }
  .token-details td {
    padding: 2px 0;
    vertical-align: top;
  }
  .token-details .lbl {
    font-weight: bold;
    width: 75px;
  }
  .seat-badge-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #ccc;
    padding-top: 4px;
    margin-top: 4px;
  }
  .seat-badge {
    font-size: 12px;
    font-weight: bold;
    background: #000;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
  }
  .room-badge {
    font-size: 11px;
    font-weight: bold;
  }
  @media print {
    .no-print {
      display: none !important;
    }
    .token-card {
      border: 2px dashed #000;
    }
  }
</style>
</head>
<body>

<div class="print-btn-bar no-print">
  <button class="print-btn" onclick="window.print()"><i class="bi bi-printer"></i> Print Tokens</button>
</div>

<div style="padding: 5px;">
  <div class="tokens-container">
    <?php foreach ($tokens as $t): ?>
      <div class="token-card">
        <div class="token-header">
          <h3><?= e($school_name) ?></h3>
          <p><?= e($examName) ?> — Seat Token</p>
        </div>
        
        <table class="token-details">
          <tr>
            <td class="lbl">Student Name:</td>
            <td class="fw-bold"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></td>
          </tr>
          <tr>
            <td class="lbl">Student ID:</td>
            <td><?= e($t['student_id_no']) ?></td>
          </tr>
          <tr>
            <td class="lbl">Class / Sec:</td>
            <td><?= e($t['class_name']) ?> — <?= e($t['section_name']) ?> (Roll: <?= e($t['roll_number'] ?? $t['seat_number']) ?>)</td>
          </tr>
        </table>
        
        <div class="seat-badge-box">
          <span class="room-badge">Room: <?= e($t['room_name']) ?></span>
          <span class="seat-badge">Seat: <?= e($t['seat_number']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

</body>
</html>
