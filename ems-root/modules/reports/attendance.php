<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Attendance Report';
$breadcrumbs = ['Reports' => 'index.php', 'Attendance Report' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['reports.view']);

$pdo        = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);
$class_id   = int_param('class_id',0,$_GET);
$section_id = int_param('section_id',0,$_GET);
$month      = int_param('month',date('n'),$_GET);
$year       = int_param('year',date('Y'),$_GET);

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
$sections = $class_id ? $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=:c ORDER BY section_name') : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections=$sections->fetchAll(); } else $sections=[];

$report = [];
$days   = [];
if ($class_id && $section_id) {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN,$month,$year);
    $dateFrom    = sprintf('%04d-%02d-01',$year,$month);
    $dateTo      = sprintf('%04d-%02d-%02d',$year,$month,$daysInMonth);

    // Students
    $stuStmt = $pdo->prepare('SELECT se.student_id, se.roll_number, sp.first_name, sp.last_name FROM student_enrollments se JOIN student_profiles sp ON sp.user_id=se.student_id WHERE se.class_id=:c AND se.section_id=:s AND se.session_id=:sess AND se.status="active" ORDER BY se.roll_number');
    $stuStmt->execute([':c'=>$class_id,':s'=>$section_id,':sess'=>$session_id]);
    $students = $stuStmt->fetchAll();

    // Attendance records
    $attStmt = $pdo->prepare('SELECT student_id, attendance_date, status FROM student_attendance WHERE class_id=:c AND section_id=:s AND attendance_date BETWEEN :f AND :t');
    $attStmt->execute([':c'=>$class_id,':s'=>$section_id,':f'=>$dateFrom,':t'=>$dateTo]);
    $attData = [];
    foreach ($attStmt->fetchAll() as $a) $attData[$a['student_id']][$a['attendance_date']] = $a['status'];

    // Working days
    $workingDays = explode(',',setting('working_days','Sat,Sun,Mon,Tue,Wed'));
    $dayShort    = ['Saturday'=>'Sat','Sunday'=>'Sun','Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri'];
    for ($d=1;$d<=$daysInMonth;$d++) {
        $ds   = sprintf('%04d-%02d-%02d',$year,$month,$d);
        $dow  = date('l',strtotime($ds));
        $short= $dayShort[$dow];
        if (in_array($short,$workingDays)) $days[] = ['date'=>$ds,'day'=>$d,'dow'=>substr($dow,0,2)];
    }

    foreach ($students as $stu) {
        $row = ['stu'=>$stu,'days'=>[],'present'=>0,'absent'=>0,'late'=>0];
        foreach ($days as $day) {
            $s = $attData[$stu['student_id']][$day['date']] ?? null;
            $row['days'][$day['date']] = $s;
            if ($s==='present') $row['present']++;
            elseif ($s==='absent') $row['absent']++;
            elseif ($s==='late') { $row['late']++; $row['present']++; }
        }
        $row['pct'] = count($days)>0 ? round($row['present']/count($days)*100) : 0;
        $report[] = $row;
    }
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Monthly Attendance Report</h1>
  <?php if(!empty($report)): ?>
  <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print</button>
  <?php endif; ?>
</div>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label small">Session</label><select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">Class</label><select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="0">—</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option><?php endforeach; ?></select></div>
    <?php if(!empty($sections)): ?><div class="col-md-2"><label class="form-label small">Section</label><select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="0">—</option><?php foreach($sections as $sec): ?><option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="col-md-1"><label class="form-label small">Month</label><select name="month" class="form-select form-select-sm" onchange="this.form.submit()"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= date('M',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?></select></div>
    <div class="col-md-1"><label class="form-label small">Year</label><input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" min="2020" max="2040" onchange="this.form.submit()"></div>
  </form>
</div></div>

<?php if(!empty($report)): ?>
<div class="card table-card" style="overflow-x:auto;">
  <div class="table-responsive">
    <table class="table table-bordered table-sm mb-0" style="font-size:.78rem;">
      <thead class="table-dark">
        <tr>
          <th>Roll</th><th>Name</th>
          <?php foreach($days as $day): ?><th class="text-center" style="min-width:26px;"><?= $day['day'] ?><br><span style="font-size:.65rem;"><?= $day['dow'] ?></span></th><?php endforeach; ?>
          <th>P</th><th>A</th><th>L</th><th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($report as $row): ?>
        <tr>
          <td class="fw-700 text-center"><?= $row['stu']['roll_number'] ?></td>
          <td class="fw-600"><?= e($row['stu']['first_name'].' '.$row['stu']['last_name']) ?></td>
          <?php foreach($days as $day):
            $s=$row['days'][$day['date']]??null;
            $bg=$s==='present'?'#dcfce7':($s==='absent'?'#fee2e2':($s==='late'?'#fef9c3':'#f8fafc'));
            $lbl=$s==='present'?'P':($s==='absent'?'A':($s==='late'?'L':''));
          ?>
          <td class="text-center" style="background:<?= $bg ?>;font-size:.72rem;"><?= $lbl ?></td>
          <?php endforeach; ?>
          <td class="text-success fw-700 text-center"><?= $row['present'] ?></td>
          <td class="text-danger text-center"><?= $row['absent'] ?></td>
          <td class="text-warning text-center"><?= $row['late'] ?></td>
          <td class="fw-700 text-center <?= $row['pct']<75?'text-danger':'' ?>"><?= $row['pct'] ?>%</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="mt-2 small text-muted"><span class="me-3">P = Present</span><span class="me-3">A = Absent</span><span class="me-3">L = Late</span><span>Blank = Not marked</span></div>
<?php else: ?><div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-calendar-check"></i><p>Select session, class, section, and month.</p></div></div></div><?php endif; ?>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
