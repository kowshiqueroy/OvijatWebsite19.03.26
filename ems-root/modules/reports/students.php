<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Student Register';
$breadcrumbs = ['Reports' => 'index.php', 'Student Register' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['reports.view']);

$pdo        = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);
$class_id   = int_param('class_id',0,$_GET);
$section_id = int_param('section_id',0,$_GET);
$print_mode = isset($_GET['print']);

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_numeric')->fetchAll();
$sections = $class_id ? $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name') : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections=$sections->fetchAll(); } else $sections=[];

$students = [];
if ($class_id && $session_id) {
    $where = 'se.session_id=:sess AND se.class_id=:cls AND se.status="active"';
    $params = [':sess'=>$session_id,':cls'=>$class_id];
    if ($section_id) { $where.=' AND se.section_id=:sec'; $params[':sec']=$section_id; }
    $stu = $pdo->prepare("SELECT se.roll_number, sp.first_name, sp.last_name, sp.student_id_no, sp.dob, sp.gender, sp.father_name, sp.guardian_phone, sp.blood_group, sp.religion, c.class_name, sec.section_name FROM student_enrollments se JOIN student_profiles sp ON sp.user_id=se.student_id JOIN classes c ON c.id=se.class_id JOIN sections sec ON sec.id=se.section_id WHERE $where ORDER BY c.display_order, sec.section_name, se.roll_number");
    $stu->execute($params);
    $students = $stu->fetchAll();
}

$school_name = setting('school_name','EMS');
$curSession  = '';
foreach($sessions as $s) if($s['id']==$session_id) { $curSession=$s['session_name']; break; }

if ($print_mode && !empty($students)) { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Student Register</title>
<style>body{font-family:Arial,sans-serif;font-size:10px;}h2,h3{text-align:center;margin:4px 0;}table{width:100%;border-collapse:collapse;margin-top:8px;}th,td{border:1px solid #333;padding:3px 5px;}th{background:#eee;font-weight:bold;text-align:center;}td{text-align:left;}@page{margin:10mm;}@media print{.no-print{display:none;}}</style></head><body>
<div class="no-print" style="margin-bottom:10px"><button onclick="window.print()">🖨 Print</button> <a href="students.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>">← Back</a></div>
<h2><?= e($school_name) ?></h2>
<h3>Student Register — <?= e($curSession) ?></h3>
<p style="text-align:center;margin:0;"><?php
$parts=[];
foreach($classes as $c) if($c['id']==$class_id) { $parts[]=$c['class_name']; break; }
if($section_id) foreach($sections as $s) if($s['id']==$section_id) { $parts[]='Section '.$s['section_name']; break; }
echo implode(' · ',$parts);
?></p>
<table>
<thead><tr><th>Roll</th><th>Student Name</th><th>ID No</th><th>DOB</th><th>Gender</th><th>Religion</th><th>Father</th><th>Phone</th><th>Blood</th><th>Section</th></tr></thead>
<tbody>
<?php foreach($students as $i=>$s): ?>
<tr><td style="text-align:center;"><?= $s['roll_number'] ?></td><td><?= e($s['first_name'].' '.$s['last_name']) ?></td><td><?= e($s['student_id_no']??'') ?></td><td><?= fmt_date($s['dob'],'d/m/Y') ?></td><td><?= ucfirst(e($s['gender']??'')) ?></td><td><?= e($s['religion']??'') ?></td><td><?= e($s['father_name']??'') ?></td><td><?= e($s['guardian_phone']??'') ?></td><td><?= e($s['blood_group']??'') ?></td><td><?= e($s['section_name']) ?></td></tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="10" style="text-align:right;">Total: <?= count($students) ?> students</td></tr></tfoot>
</table>
<div style="display:flex;justify-content:space-between;margin-top:30px;">
  <div style="text-align:center;"><div style="border-top:1px solid #000;min-width:150px;padding-top:3px;">Class Teacher</div></div>
  <div style="text-align:center;"><div style="border-top:1px solid #000;min-width:150px;padding-top:3px;">Principal</div></div>
</div>
</body></html>
<?php exit; }

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Student Register</h1>
  <?php if(!empty($students)): ?>
  <a href="?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&print=1" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print Register</a>
  <?php endif; ?>
</div>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label small">Session</label><select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">Class</label><select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="0">—</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option><?php endforeach; ?></select></div>
    <?php if(!empty($sections)): ?><div class="col-md-2"><label class="form-label small">Section</label><select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="0">All</option><?php foreach($sections as $sec): ?><option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></div>
  </form>
</div></div>
<?php if(!empty($students)): ?>
<div class="card table-card">
  <div class="card-header py-3 px-4"><span class="card-title">Students <span class="badge bg-secondary"><?= count($students) ?></span></span></div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0 small" id="data-table">
      <thead><tr><th>Roll</th><th>Name</th><th>ID No</th><th>DOB</th><th>Gender</th><th>Father</th><th>Phone</th><th>Section</th></tr></thead>
      <tbody>
        <?php foreach($students as $s): ?>
        <tr>
          <td class="fw-700"><?= $s['roll_number'] ?></td>
          <td class="fw-600"><?= e($s['first_name'].' '.$s['last_name']) ?></td>
          <td><code><?= e($s['student_id_no']??'') ?></code></td>
          <td><?= fmt_date($s['dob']) ?></td>
          <td class="text-capitalize"><?= e($s['gender']??'—') ?></td>
          <td><?= e($s['father_name']??'—') ?></td>
          <td><?= e($s['guardian_phone']??'—') ?></td>
          <td><?= e($s['section_name']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?><div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-person-lines-fill"></i><p>Select session and class to generate register.</p></div></div></div><?php endif; ?>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
