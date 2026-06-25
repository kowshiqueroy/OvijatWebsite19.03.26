<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Clubs & Activities';
$breadcrumbs = ['ECA & Events' => null, 'Clubs' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['eca.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('eca.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_club') {
        $id      = int_param('id',0,$_POST);
        $name    = trim($_POST['club_name']??'');
        $type    = $_POST['club_type']??'other';
        $mod     = int_param('moderator_id',0,$_POST)?:null;
        $desc    = trim($_POST['description']??'');
        $founded = $_POST['founded_date']??null;
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE clubs SET club_name=?,club_type=?,moderator_id=?,description=?,founded_date=? WHERE id=?')
                    ->execute([$name,$type,$mod,$desc,$founded,$id]);
                flash('success','Club updated.');
            } else {
                $pdo->prepare('INSERT INTO clubs (club_name,club_type,moderator_id,description,founded_date) VALUES (?,?,?,?,?)')
                    ->execute([$name,$type,$mod,$desc,$founded]);
                flash('success',"Club '$name' created.");
            }
        }
    } elseif ($action === 'enroll') {
        $club_id    = int_param('club_id',0,$_POST);
        $student_id = int_param('student_id',0,$_POST);
        $role_in    = trim($_POST['role_in_club']??'member');
        if ($club_id && $student_id) {
            try {
                $pdo->prepare('INSERT IGNORE INTO club_members (club_id,student_id,role_in_club,joined_date,status) VALUES (?,?,?,CURDATE(),"active")')
                    ->execute([$club_id,$student_id,$role_in]);
                flash('success','Member added.');
            } catch (Exception $e) { flash('error','Already a member.'); }
        }
    } elseif ($action === 'remove') {
        $club_id    = int_param('club_id',0,$_POST);
        $student_id = int_param('student_id',0,$_POST);
        $pdo->prepare("UPDATE club_members SET status='left' WHERE club_id=? AND student_id=?")->execute([$club_id,$student_id]);
        flash('success','Member removed.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM clubs WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Club deleted.');
    }
    header('Location: clubs.php?club='.($_POST['club_id']??''));
    exit;
}

$clubs   = $pdo->query('SELECT c.*,u.full_name as moderator_name,COUNT(cm.id) as member_count FROM clubs c LEFT JOIN users u ON u.id=c.moderator_id LEFT JOIN club_members cm ON cm.club_id=c.id AND cm.status="active" WHERE c.status=1 GROUP BY c.id ORDER BY c.club_name')->fetchAll();
$staff   = $pdo->query("SELECT sp.user_id as id,CONCAT(sp.first_name,' ',sp.last_name) as name FROM staff_profiles sp WHERE sp.status='active' ORDER BY name")->fetchAll();

$activeClub = int_param('club',0,$_GET);
$members = [];
if ($activeClub) {
    $m = $pdo->query("SELECT cm.*,sp.first_name,sp.last_name,sp.student_id_no FROM club_members cm JOIN student_profiles sp ON sp.user_id=cm.student_id WHERE cm.club_id=$activeClub AND cm.status='active' ORDER BY cm.role_in_club DESC, sp.first_name");
    $members = $m->fetchAll();
}

$students = $pdo->query("SELECT u.id,sp.first_name,sp.last_name,sp.student_id_no FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE u.status='active' ORDER BY sp.first_name LIMIT 100")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-trophy-fill me-2 text-primary"></i>Clubs & Activities</h1>
  <?php if(has_permission('eca.manage')): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clubModal" onclick="setClubForm(null)"><i class="bi bi-plus-lg me-1"></i>New Club</button>
  <?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="row g-2">
      <?php foreach($clubs as $club): ?>
      <div class="col-12">
        <div class="card <?= $activeClub==$club['id']?'border-primary':'' ?> h-100">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <a href="?club=<?= $club['id'] ?>" class="fw-700 text-decoration-none"><?= e($club['club_name']) ?></a>
                <div class="small text-muted"><?= ucfirst(str_replace('_',' ',e($club['club_type']))) ?></div>
                <div class="small text-muted">Moderator: <?= e($club['moderator_name']??'—') ?></div>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-primary"><?= $club['member_count'] ?> members</span>
                <?php if(has_permission('eca.manage')): ?>
                <div class="d-flex gap-1 mt-1">
                  <button class="btn btn-xs btn-outline-primary" style="font-size:.65rem;padding:.1rem .3rem;" data-bs-toggle="modal" data-bs-target="#clubModal" onclick="setClubForm(<?= htmlspecialchars(json_encode($club),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                  <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $club['id'] ?>"><button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.65rem;padding:.1rem .3rem;" data-confirm="Delete '<?= e($club['club_name']) ?>'?"><i class="bi bi-trash"></i></button></form>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($clubs)): ?><div class="col-12"><div class="card"><div class="card-body text-center text-muted small py-3">No clubs yet</div></div></div><?php endif; ?>
    </div>
  </div>

  <div class="col-md-8">
    <?php if($activeClub): ?>
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Members (<?= count($members) ?>)</span></div>
      <div class="table-responsive" style="max-height:300px;overflow-y:auto;">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Name</th><th>ID</th><th>Role</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php if(empty($members)): ?><tr><td colspan="5" class="text-center text-muted small py-2">No members</td></tr><?php endif; ?>
            <?php foreach($members as $m): ?>
            <tr>
              <td class="fw-600"><?= e($m['first_name'].' '.$m['last_name']) ?></td>
              <td><code><?= e($m['student_id_no']??'') ?></code></td>
              <td><?= e($m['role_in_club']) ?></td>
              <td><?= fmt_date($m['joined_date']) ?></td>
              <td><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="club_id" value="<?= $activeClub ?>"><input type="hidden" name="student_id" value="<?= $m['student_id'] ?>"><button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.65rem;padding:.1rem .3rem;" data-confirm="Remove?"><i class="bi bi-x"></i></button></form></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if(has_permission('eca.manage')): ?>
      <div class="card-footer p-3">
        <form method="POST" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="club_id" value="<?= $activeClub ?>">
          <div class="col-md-5"><select name="student_id" class="form-select form-select-sm" required><option value="">— Add Student —</option><?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['first_name'].' '.$s['last_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><input type="text" name="role_in_club" class="form-control form-control-sm" placeholder="Role (e.g. Captain)" value="member"></div>
          <div class="col-auto"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-trophy"></i><p>Select a club to manage its members.</p></div></div></div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="clubModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save_club"><input type="hidden" name="id" id="cb_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="clubModalTitle">New Club</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Club Name *</label><input type="text" name="club_name" id="cb_name" class="form-control" required></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Type</label><select name="club_type" id="cb_type" class="form-select"><?php foreach(['academic'=>'Academic','sports'=>'Sports','cultural'=>'Cultural','debate'=>'Debate','science'=>'Science','other'=>'Other'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Moderator</label><select name="moderator_id" id="cb_mod" class="form-select"><option value="">— None —</option><?php foreach($staff as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Founded Date</label><input type="date" name="founded_date" id="cb_date" class="form-control"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="cb_desc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setClubForm(c){
  document.getElementById('clubModalTitle').textContent=c?'Edit Club':'New Club';
  document.getElementById('cb_id').value=c?c.id:0;
  document.getElementById('cb_name').value=c?c.club_name:'';
  document.getElementById('cb_type').value=c?c.club_type:'other';
  document.getElementById('cb_mod').value=c?(c.moderator_id||''):'';
  document.getElementById('cb_date').value=c?(c.founded_date||''):'';
  document.getElementById('cb_desc').value=c?(c.description||''):'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
