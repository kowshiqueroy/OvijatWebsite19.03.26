<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Groups & Streams';
$breadcrumbs = ['Academic' => null, 'Groups / Streams' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_group') {
        $id   = int_param('id', 0, $_POST);
        $name = trim($_POST['group_name'] ?? '');
        $code = strtoupper(trim($_POST['group_code'] ?? ''));
        $level = $_POST['applicable_from_class_level'] ?? 'both';
        if ($name && $code) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE groups_stream SET group_name=?,group_code=?,applicable_from_class_level=? WHERE id=?')
                        ->execute([$name, $code, $level, $id]);
                    flash('success', 'Group updated.');
                } else {
                    $pdo->prepare('INSERT INTO groups_stream (group_name,group_code,applicable_from_class_level) VALUES (?,?,?)')
                        ->execute([$name, $code, $level]);
                    flash('success', "Group '$name' added.");
                }
            } catch (Exception $e) { flash('error', 'Code already exists.'); }
        }
    } elseif ($action === 'delete_group') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM groups_stream WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Group removed.');
    } elseif ($action === 'assign_group') {
        $class_id = int_param('class_id', 0, $_POST);
        $group_id = int_param('group_id', 0, $_POST);
        if ($class_id && $group_id) {
            try {
                $pdo->prepare('INSERT IGNORE INTO class_groups (class_id,group_id) VALUES (?,?)')->execute([$class_id, $group_id]);
                flash('success', 'Group assigned to class.');
            } catch (Exception $e) { flash('error', 'Already assigned.'); }
        }
    } elseif ($action === 'remove_assignment') {
        $class_id = int_param('class_id', 0, $_POST);
        $group_id = int_param('group_id', 0, $_POST);
        $pdo->prepare('DELETE FROM class_groups WHERE class_id=? AND group_id=?')->execute([$class_id, $group_id]);
        flash('success', 'Assignment removed.');
    }
    header('Location: groups.php');
    exit;
}

$groups  = $pdo->query('SELECT g.*, COUNT(cg.class_id) as class_count FROM groups_stream g LEFT JOIN class_groups cg ON cg.group_id=g.id GROUP BY g.id ORDER BY g.group_name')->fetchAll();
$classes = $pdo->query("SELECT c.*, GROUP_CONCAT(g.group_name ORDER BY g.group_name SEPARATOR ', ') as assigned_groups FROM classes c LEFT JOIN class_groups cg ON cg.class_id=c.id LEFT JOIN groups_stream g ON g.id=cg.group_id WHERE c.status=1 GROUP BY c.id ORDER BY c.display_order, c.class_numeric")->fetchAll();
$groupMap = [];
foreach ($pdo->query('SELECT cg.class_id, g.id, g.group_name FROM class_groups cg JOIN groups_stream g ON g.id=cg.group_id')->fetchAll() as $r) {
    $groupMap[$r['class_id']][$r['id']] = $r['group_name'];
}

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Groups & Streams</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal" onclick="setGroupForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Group
  </button>
</div>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Defined Groups / Streams</span></div>
      <div class="list-group list-group-flush">
        <?php if (empty($groups)): ?>
          <div class="text-center text-muted py-3 small">No groups defined yet</div>
        <?php else: foreach ($groups as $g): ?>
        <div class="list-group-item d-flex align-items-center justify-content-between">
          <div>
            <span class="fw-700"><?= e($g['group_name']) ?></span>
            <span class="badge bg-secondary ms-2"><?= e($g['group_code']) ?></span>
            <div class="small text-muted"><?= e(ucwords(str_replace('_',' ',$g['applicable_from_class_level']))) ?> · <?= $g['class_count'] ?> classes</div>
          </div>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-primary" style="padding:.15rem .4rem;font-size:.72rem;"
                    data-bs-toggle="modal" data-bs-target="#groupModal"
                    onclick="setGroupForm(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_group">
              <input type="hidden" name="id" value="<?= $g['id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:.15rem .4rem;font-size:.72rem;"
                      data-confirm="Delete group '<?= e($g['group_name']) ?>'?"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Class → Group Assignment</span></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 small">
          <thead><tr><th>Class</th><th>Assigned Groups</th><th>Add Group</th></tr></thead>
          <tbody>
            <?php foreach ($classes as $cls): ?>
            <tr>
              <td class="fw-600"><?= e($cls['class_name']) ?></td>
              <td>
                <?php foreach ($groupMap[$cls['id']] ?? [] as $gid => $gname): ?>
                <span class="badge bg-info text-white me-1">
                  <?= e($gname) ?>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_assignment">
                    <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                    <input type="hidden" name="group_id" value="<?= $gid ?>">
                    <button type="submit" class="btn-close btn-close-white" style="font-size:.5rem;" title="Remove"></button>
                  </form>
                </span>
                <?php endforeach; ?>
                <?php if (empty($groupMap[$cls['id']])): ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td>
                <form method="POST" class="d-flex gap-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="assign_group">
                  <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                  <select name="group_id" class="form-select form-select-sm" style="width:130px;">
                    <option value="">— Add —</option>
                    <?php foreach ($groups as $g):
                      if (isset($groupMap[$cls['id']][$g['id']])) continue;
                    ?>
                      <option value="<?= $g['id'] ?>"><?= e($g['group_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="groupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_group">
        <input type="hidden" name="id" id="g_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="groupModalTitle">Add Group / Stream</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Group Name <span class="text-danger">*</span></label>
            <input type="text" name="group_name" id="g_name" class="form-control" placeholder="e.g. Science, Commerce, Arts" required></div>
          <div class="mb-3"><label class="form-label">Short Code <span class="text-danger">*</span></label>
            <input type="text" name="group_code" id="g_code" class="form-control" placeholder="e.g. SCI, COM, ART" maxlength="10" required></div>
          <div><label class="form-label">Applicable Level</label>
            <select name="applicable_from_class_level" id="g_level" class="form-select">
              <option value="secondary">Secondary (SSC)</option>
              <option value="higher_secondary">Higher Secondary (HSC)</option>
              <option value="both" selected>Both</option>
            </select></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function setGroupForm(g) {
  document.getElementById('groupModalTitle').textContent = g ? 'Edit Group' : 'Add Group / Stream';
  document.getElementById('g_id').value    = g ? g.id : 0;
  document.getElementById('g_name').value  = g ? g.group_name : '';
  document.getElementById('g_code').value  = g ? g.group_code : '';
  document.getElementById('g_level').value = g ? g.applicable_from_class_level : 'both';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
