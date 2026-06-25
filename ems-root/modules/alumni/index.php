<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Alumni';
$breadcrumbs = ['Alumni' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id        = int_param('id',0,$_POST);
        $name      = trim($_POST['full_name']??'');
        $cls_id    = int_param('last_class_id',0,$_POST)?:null;
        $pass_year = int_param('pass_year',0,$_POST)?:null;
        $curr_inst = trim($_POST['current_institution']??'');
        $profession= trim($_POST['current_profession']??'');
        $phone     = trim($_POST['phone']??'');
        $email     = trim($_POST['email']??'');
        $notes     = trim($_POST['notes']??'');
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE alumni SET full_name=?,last_class_id=?,pass_year=?,current_institution=?,current_profession=?,phone=?,email=?,notes=? WHERE id=?')
                    ->execute([$name,$cls_id,$pass_year,$curr_inst,$profession,$phone,$email,$notes,$id]);
                flash('success','Alumni record updated.');
            } else {
                $pdo->prepare('INSERT INTO alumni (full_name,last_class_id,pass_year,current_institution,current_profession,phone,email,notes) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$name,$cls_id,$pass_year,$curr_inst,$profession,$phone,$email,$notes]);
                flash('success',"$name added to alumni.");
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM alumni WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Deleted.');
    }
    header('Location: index.php');
    exit;
}

$search = trim($_GET['q']??'');
$year_f = int_param('year',0,$_GET);
$page   = max(1,int_param('page',1,$_GET));

$where  = ['1=1'];
$params = [];
if ($search) { $where[]='(a.full_name LIKE :q OR a.current_institution LIKE :q OR a.email LIKE :q)'; $params[':q']="%$search%"; }
if ($year_f) { $where[]='a.pass_year=:yr'; $params[':yr']=$year_f; }
$whereStr = implode(' AND ',$where);

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM alumni a WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total,$page);

$alumni = $pdo->prepare("SELECT a.*,c.class_name FROM alumni a LEFT JOIN classes c ON c.id=a.last_class_id WHERE $whereStr ORDER BY a.pass_year DESC,a.full_name LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$alumni->execute($params);
$alumni = $alumni->fetchAll();

$classes  = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
$passYears= $pdo->query('SELECT DISTINCT pass_year FROM alumni WHERE pass_year IS NOT NULL ORDER BY pass_year DESC')->fetchAll(PDO::FETCH_COLUMN);

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Alumni</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#alumniModal" onclick="setAlumForm(null)"><i class="bi bi-plus-lg me-1"></i>Add Alumni</button>
</div>

<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small">Search</label><input type="text" name="q" class="form-control form-control-sm" placeholder="Name, institution, email…" value="<?= e($search) ?>"></div>
    <div class="col-md-2"><label class="form-label small">Pass Year</label>
      <select name="year" class="form-select form-select-sm">
        <option value="0">All Years</option>
        <?php foreach($passYears as $y): ?><option value="<?= $y ?>" <?= $year_f==$y?'selected':'' ?>><?= $y ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div></div>

<div class="card table-card">
  <div class="card-header py-3 px-4"><span class="card-title">Alumni <span class="badge bg-secondary"><?= $total ?></span></span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Name</th><th>Last Class</th><th>Pass Year</th><th>Current Institution</th><th>Profession</th><th>Contact</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($alumni)): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-mortarboard"></i><p>No alumni records yet.</p></div></td></tr><?php endif; ?>
        <?php foreach($alumni as $al): ?>
        <tr>
          <td class="fw-600"><?= e($al['full_name']) ?></td>
          <td><?= e($al['class_name']??'—') ?></td>
          <td class="fw-700"><?= $al['pass_year']??'—' ?></td>
          <td><?= e($al['current_institution']??'—') ?></td>
          <td><?= e($al['current_profession']??'—') ?></td>
          <td><div class="small"><?= e($al['phone']??'') ?><?php if($al['email']): ?><br><a href="mailto:<?= e($al['email']) ?>"><?= e($al['email']) ?></a><?php endif; ?></div></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#alumniModal" onclick="setAlumForm(<?= htmlspecialchars(json_encode($al),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $al['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="alumniModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="al_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="alumModalTitle">Add Alumni</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" id="al_name" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Last Class</label><select name="last_class_id" id="al_cls" class="form-select"><option value="">—</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Pass Year</label><input type="number" name="pass_year" id="al_year" class="form-control" min="1950" max="2050" value="<?= date('Y')-1 ?>"></div>
            <div class="col-md-6"><label class="form-label">Current Institution</label><input type="text" name="current_institution" id="al_inst" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Profession</label><input type="text" name="current_profession" id="al_prof" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="al_phone" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="al_email" class="form-control"></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="al_notes" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setAlumForm(a){
  document.getElementById('alumModalTitle').textContent=a?'Edit Alumni':'Add Alumni';
  document.getElementById('al_id').value=a?a.id:0;
  document.getElementById('al_name').value=a?a.full_name:'';
  document.getElementById('al_cls').value=a?(a.last_class_id||''):'';
  document.getElementById('al_year').value=a?(a.pass_year||<?= date('Y')-1 ?>):<?= date('Y')-1 ?>;
  document.getElementById('al_inst').value=a?(a.current_institution||''):'';
  document.getElementById('al_prof').value=a?(a.current_profession||''):'';
  document.getElementById('al_phone').value=a?(a.phone||''):'';
  document.getElementById('al_email').value=a?(a.email||''):'';
  document.getElementById('al_notes').value=a?(a.notes||''):'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
