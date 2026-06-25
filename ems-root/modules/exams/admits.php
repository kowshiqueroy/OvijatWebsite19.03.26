<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Admit Cards';
$breadcrumbs = ['Examinations' => 'index.php', 'Admit Cards' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['exams.view']);

$pdo     = db();
$exam_id = int_param('exam_id', 0, $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$single_id  = int_param('student_id', 0, $_GET);

$exam = null;
if ($exam_id) {
    $s = $pdo->prepare('SELECT e.*, ass.session_name FROM exams e JOIN academic_sessions ass ON ass.id=e.session_id WHERE e.id=:id');
    $s->execute([':id' => $exam_id]);
    $exam = $s->fetch();
}

$examClasses = $exam_id ? $pdo->query("SELECT c.id,c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=$exam_id ORDER BY c.display_order")->fetchAll() : [];

$sections = $class_id
    ? $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections=$sections->fetchAll(); } else $sections=[];

// Load students for admit cards
$students = [];
if ($exam_id && $class_id && $section_id) {
    $stu = $pdo->prepare(
        'SELECT se.student_id, se.roll_number, se.section_id,
                sp.first_name, sp.last_name, sp.student_id_no, sp.photo, sp.dob,
                c.class_name, sec.section_name,
                es.seat_number, r.room_name
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id=se.student_id
         JOIN classes c ON c.id=se.class_id
         JOIN sections sec ON sec.id=se.section_id
         JOIN academic_sessions ass ON ass.id=se.session_id
         JOIN exams ex ON ex.session_id=ass.id AND ex.id=:eid
         LEFT JOIN exam_seats es ON es.student_id=se.student_id AND es.exam_id=:eid
         LEFT JOIN rooms r ON r.id=es.room_id
         WHERE se.class_id=:cls AND se.section_id=:sec AND se.status="active"
         ORDER BY se.roll_number'
    );
    $stu->execute([':eid'=>$exam_id,':cls'=>$class_id,':sec'=>$section_id]);
    $students = $stu->fetchAll();
}

// Subject schedule for the exam + class
$schedule = [];
if ($exam_id && $class_id) {
    $sch = $pdo->prepare('SELECT s.subject_name, esc.exam_date, esc.exam_time, esc.full_marks_written+esc.full_marks_mcq+esc.full_marks_practical as full_marks FROM exam_subject_config esc JOIN subjects s ON s.id=esc.subject_id WHERE esc.exam_id=:eid AND esc.class_id=:cls AND esc.exam_date IS NOT NULL ORDER BY esc.exam_date');
    $sch->execute([':eid'=>$exam_id,':cls'=>$class_id]);
    $schedule = $sch->fetchAll();
}

$school_name = setting('school_name','EMS');
$school_addr = setting('school_address','');

$allExams = $pdo->query('SELECT id,exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();

// Print mode
$printMode = !empty($students) && isset($_GET['print']);

if ($printMode) {
    // Pure print page — no header/footer shell
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admit Cards — <?= e($exam['exam_name']??'') ?></title>
<style>
  body{font-family:Arial,sans-serif;margin:0;padding:10px;font-size:11px;}
  .admit-card{border:2px solid #000;padding:12px;margin-bottom:15px;page-break-inside:avoid;max-width:380px;display:inline-block;vertical-align:top;margin-right:10px;}
  .admit-header{text-align:center;border-bottom:1px solid #000;padding-bottom:6px;margin-bottom:8px;}
  .admit-header h3{margin:0;font-size:13px;}
  .admit-header h4{margin:2px 0 0;font-size:11px;font-weight:normal;}
  .admit-body{display:flex;gap:8px;}
  .admit-photo{width:60px;height:70px;border:1px solid #ccc;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f5f5f5;}
  .admit-photo img{width:100%;height:100%;object-fit:cover;}
  .admit-info table{width:100%;border-collapse:collapse;font-size:10px;}
  .admit-info td{padding:1.5px 3px;vertical-align:top;}
  .admit-info .label{font-weight:bold;white-space:nowrap;width:90px;}
  .schedule{margin-top:8px;border-top:1px solid #ccc;padding-top:6px;}
  .schedule table{width:100%;border-collapse:collapse;font-size:9px;}
  .schedule th,.schedule td{border:1px solid #ccc;padding:2px 4px;}
  .schedule th{background:#eee;font-weight:bold;}
  .admit-footer{display:flex;justify-content:space-between;margin-top:8px;font-size:9px;border-top:1px solid #000;padding-top:6px;}
  .sig-line{border-top:1px solid #000;padding-top:2px;text-align:center;min-width:100px;}
  @media print{body{padding:5px;}@page{margin:10mm;}}
</style>
</head>
<body>
<div style="text-align:right;margin-bottom:10px;" class="no-print" onclick="window.print()" style="cursor:pointer;">
  <button onclick="window.print()" style="padding:6px 16px;background:#1a56db;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;">🖨 Print All Cards</button>
  <a href="admits.php?exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>" style="margin-left:8px;padding:6px 16px;background:#6c757d;color:#fff;border-radius:6px;text-decoration:none;font-size:12px;">← Back</a>
</div>
<?php foreach($students as $stu): ?>
<div class="admit-card">
  <div class="admit-header">
    <h3><?= e($school_name) ?></h3>
    <h4><?= e($exam['exam_name']??'') ?> — <?= e($exam['session_name']??'') ?></h4>
    <div style="font-size:9px;margin-top:2px;"><strong>ADMIT CARD</strong></div>
  </div>
  <div class="admit-body">
    <div class="admit-photo">
      <?php if($stu['photo'] && file_exists(UPLOAD_PHOTOS.$stu['photo'])): ?>
        <img src="../../uploads/photos/<?= e($stu['photo']) ?>" alt="">
      <?php else: ?>
        <span style="font-size:20px;color:#aaa;">👤</span>
      <?php endif; ?>
    </div>
    <div class="admit-info">
      <table>
        <tr><td class="label">Student Name</td><td><?= e($stu['first_name'].' '.$stu['last_name']) ?></td></tr>
        <tr><td class="label">Student ID</td><td><?= e($stu['student_id_no']??'—') ?></td></tr>
        <tr><td class="label">Class</td><td><?= e($stu['class_name']) ?> — <?= e($stu['section_name']) ?></td></tr>
        <tr><td class="label">Roll No.</td><td><strong><?= $stu['roll_number'] ?></strong></td></tr>
        <tr><td class="label">Seat No.</td><td><strong><?= e($stu['seat_number']??'—') ?></strong></td></tr>
        <tr><td class="label">Room</td><td><?= e($stu['room_name']??'—') ?></td></tr>
        <tr><td class="label">D.O.B.</td><td><?= fmt_date($stu['dob']) ?></td></tr>
      </table>
    </div>
  </div>
  <?php if(!empty($schedule)): ?>
  <div class="schedule">
    <table>
      <thead><tr><th>Date</th><th>Time</th><th>Subject</th><th>Marks</th></tr></thead>
      <tbody>
        <?php foreach($schedule as $sch): ?>
        <tr>
          <td><?= fmt_date($sch['exam_date'],'d M') ?></td>
          <td><?= $sch['exam_time'] ? substr($sch['exam_time'],0,5) : '—' ?></td>
          <td><?= e($sch['subject_name']) ?></td>
          <td><?= $sch['full_marks'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <div class="admit-footer">
    <span>Issued: <?= date('d M Y') ?></span>
    <div class="sig-line">Controller of Examinations</div>
  </div>
</div>
<?php endforeach; ?>
</body>
</html>
<?php
    exit;
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-card-text me-2 text-primary"></i>Admit Cards</h1>
  <?php if(!empty($students)): ?>
  <a href="?exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&print=1" target="_blank" class="btn btn-primary">
    <i class="bi bi-printer me-1"></i>Print All Cards (<?= count($students) ?>)
  </a>
  <?php endif; ?>
</div>

<!-- Selector -->
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Exam</label>
      <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Select Exam —</option>
        <?php foreach($allExams as $e): ?><option value="<?= $e['id'] ?>" <?= $exam_id==$e['id']?'selected':'' ?>><?= e($e['exam_name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if($exam_id && !empty($examClasses)): ?>
    <div class="col-md-2"><label class="form-label small">Class</label>
      <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Select —</option>
        <?php foreach($examClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <?php if($class_id && !empty($sections)): ?>
    <div class="col-md-2"><label class="form-label small">Section</label>
      <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Select —</option>
        <?php foreach($sections as $sec): ?><option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
  </form>
</div></div>

<?php if(!empty($students)): ?>
<!-- Preview grid -->
<div class="row g-3">
  <?php foreach($students as $stu): ?>
  <div class="col-md-4 col-lg-3">
    <div class="card h-100">
      <div class="card-body text-center py-3">
        <div class="mx-auto mb-2 rounded" style="width:60px;height:70px;background:#f1f5f9;overflow:hidden;border:1px solid #ddd;">
          <?php if($stu['photo'] && file_exists(UPLOAD_PHOTOS.$stu['photo'])): ?>
            <img src="../../uploads/photos/<?= e($stu['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#94a3b8;"><?= strtoupper(substr($stu['first_name'],0,1)) ?></div>
          <?php endif; ?>
        </div>
        <div class="fw-700 small"><?= e($stu['first_name'].' '.$stu['last_name']) ?></div>
        <div class="text-muted small">Roll: <strong><?= $stu['roll_number'] ?></strong></div>
        <div class="text-muted small">Seat: <strong><?= e($stu['seat_number']??'—') ?></strong> <?= e($stu['room_name']??'') ?></div>
        <a href="?exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&student_id=<?= $stu['student_id'] ?>&print=1" target="_blank" class="btn btn-xs btn-outline-primary mt-2" style="font-size:.7rem;padding:.15rem .45rem;">
          <i class="bi bi-printer"></i> Single
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php elseif($exam_id): ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-card-text"></i><p>Select class and section to preview and print admit cards.</p></div></div></div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-card-text"></i><p>Select an exam to generate admit cards.</p></div></div></div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
