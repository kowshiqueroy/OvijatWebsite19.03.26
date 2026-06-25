<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Invigilator Duty Roster';
$breadcrumbs = ['Examinations' => 'index.php', 'Invigilators' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['exams.manage']);

$pdo     = db();
$exam_id = int_param('exam_id',0,$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action']??'';
    if ($action === 'assign') {
        $eid      = int_param('exam_id',0,$_POST);
        $rid      = int_param('room_id',0,$_POST);
        $tid      = int_param('teacher_id',0,$_POST);
        $date     = $_POST['duty_date']??date('Y-m-d');
        $type     = $_POST['duty_type']??'invigilator';
        $allow    = (float)($_POST['allowance_amount']??0);
        if ($eid && $rid && $tid) {
            $pdo->prepare('INSERT INTO exam_invigilators (exam_id,room_id,teacher_id,duty_date,duty_type,allowance_amount) VALUES (?,?,?,?,?,?)')->execute([$eid,$rid,$tid,$date,$type,$allow]);
            flash('success','Duty assigned.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM exam_invigilators WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Duty removed.');
    }
    header("Location: invigilators.php?exam_id=$exam_id");
    exit;
}

$allExams = $pdo->query('SELECT id,exam_name FROM exams ORDER BY id DESC LIMIT 20')->fetchAll();
$rooms    = $pdo->query('SELECT id,room_name FROM rooms WHERE status=1 ORDER BY room_name')->fetchAll();
$teachers = $pdo->query("SELECT sp.user_id as id,CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();

$duties = [];
if ($exam_id) {
    $d = $pdo->query("SELECT ei.*,r.room_name,CONCAT(sp.first_name,' ',sp.last_name) as teacher_name FROM exam_invigilators ei JOIN rooms r ON r.id=ei.room_id JOIN staff_profiles sp ON sp.user_id=ei.teacher_id WHERE ei.exam_id=$exam_id ORDER BY ei.duty_date, r.room_name");
    $duties = $d->fetchAll();
}
$totalAllow = array_sum(array_column($duties,'allowance_amount'));

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Invigilator Duty Roster</h1>
  <?php if($exam_id): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dutyModal"><i class="bi bi-plus-lg me-1"></i>Assign Duty</button>
  <?php endif; ?>
</div>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small">Exam</label>
      <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Select Exam —</option>
        <?php foreach($allExams as $e): ?><option value="<?= $e['id'] ?>" <?= $exam_id==$e['id']?'selected':'' ?>><?= e($e['exam_name']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if($exam_id && !empty($duties)): ?>
    <div class="col-auto"><button onclick="window.print()" type="button" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print Roster</button></div>
    <?php endif; ?>
  </form>
</div></div>

<?php if($exam_id): ?>
<?php if(!empty($duties)): ?>
<div class="row g-3 mb-3">
  <div class="col-sm-4"><div class="stat-card info"><div class="stat-value"><?= count($duties) ?></div><div class="stat-label">Total Duties</div><i class="bi bi-person-badge stat-icon"></i></div></div>
  <div class="col-sm-4"><div class="stat-card warning"><div class="stat-value"><?= money($totalAllow) ?></div><div class="stat-label">Total Allowance</div><i class="bi bi-cash stat-icon"></i></div></div>
</div>
<?php endif; ?>
<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Date</th><th>Room</th><th>Invigilator</th><th>Duty Type</th><th>Allowance</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($duties)): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-badge"></i><p>No duties assigned yet.</p></div></td></tr><?php endif; ?>
        <?php foreach($duties as $d): ?>
        <tr>
          <td><?= fmt_date($d['duty_date']) ?></td>
          <td class="fw-600"><?= e($d['room_name']) ?></td>
          <td><?= e($d['teacher_name']) ?></td>
          <td><span class="badge bg-<?= $d['duty_type']==='chief_invigilator'?'danger':($d['duty_type']==='invigilator'?'primary':'secondary') ?>"><?= ucwords(str_replace('_',' ',e($d['duty_type']))) ?></span></td>
          <td class="fw-600"><?= money($d['allowance_amount']) ?></td>
          <td><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove duty?"><i class="bi bi-trash"></i></button></form></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?><div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-clipboard-check"></i><p>Select an exam to manage duty roster.</p></div></div></div><?php endif; ?>

<div class="modal fade" id="dutyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <div class="modal-header"><h5 class="modal-title">Assign Invigilator Duty</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Room *</label><select name="room_id" class="form-select" required><option value="">—</option><?php foreach($rooms as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['room_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Invigilator *</label><select name="teacher_id" class="form-select" required><option value="">—</option><?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Duty Date</label><input type="date" name="duty_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-6"><label class="form-label">Duty Type</label><select name="duty_type" class="form-select"><option value="chief_invigilator">Chief Invigilator</option><option value="invigilator" selected>Invigilator</option><option value="assistant">Assistant</option></select></div>
            <div class="col-md-6"><label class="form-label">Allowance (৳)</label><input type="number" name="allowance_amount" class="form-control" step="0.01" value="0"></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Assign</button></div>
      </form>
    </div>
  </div>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
