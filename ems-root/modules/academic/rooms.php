<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Rooms & Labs';
$breadcrumbs = ['Academic' => null, 'Rooms' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id   = int_param('id', 0, $_POST);
        $name = trim($_POST['room_name'] ?? '');
        $num  = trim($_POST['room_number'] ?? '');
        $floor  = int_param('floor', 1, $_POST);
        $cap    = int_param('capacity', 30, $_POST);
        $type   = $_POST['room_type'] ?? 'classroom';
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE rooms SET room_name=?,room_number=?,floor=?,capacity=?,room_type=? WHERE id=?')
                    ->execute([$name,$num,$floor,$cap,$type,$id]);
                flash('success', 'Room updated.');
            } else {
                $pdo->prepare('INSERT INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)')
                    ->execute([$name,$num,$floor,$cap,$type]);
                flash('success', "Room '$name' added.");
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('UPDATE rooms SET status=0 WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Room removed.');
    }
    header('Location: rooms.php');
    exit;
}

$rooms = $pdo->query("SELECT r.*, (SELECT COUNT(*) FROM exam_seats es WHERE es.room_id=r.id) as exam_count FROM rooms r WHERE r.status=1 ORDER BY r.floor, r.room_name")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-door-open-fill me-2 text-primary"></i>Rooms & Labs</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="setRoomForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Room
  </button>
</div>
<div class="row g-3">
  <?php if (empty($rooms)): ?>
    <div class="col-12"><div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-building"></i><p>No rooms added yet.</p></div></div></div></div>
  <?php else: foreach ($rooms as $rm): ?>
  <div class="col-md-4 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <h6 class="fw-700 mb-0"><?= e($rm['room_name']) ?></h6>
            <div class="text-muted small">Room <?= e($rm['room_number'] ?? '?') ?> · Floor <?= $rm['floor'] ?></div>
          </div>
          <span class="badge bg-<?= $rm['room_type'] === 'exam_hall' ? 'danger' : ($rm['room_type'] === 'lab' ? 'warning text-dark' : 'primary') ?>">
            <?= ucfirst(str_replace('_',' ',e($rm['room_type']))) ?>
          </span>
        </div>
        <div class="mt-2 d-flex gap-3 small text-muted">
          <span><i class="bi bi-people me-1"></i>Capacity: <strong><?= $rm['capacity'] ?></strong></span>
        </div>
      </div>
      <div class="card-footer d-flex gap-2 py-2">
        <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#roomModal"
                onclick="setRoomForm(<?= htmlspecialchars(json_encode($rm), ENT_QUOTES) ?>)">
          <i class="bi bi-pencil me-1"></i>Edit
        </button>
        <form method="POST" class="d-inline flex-fill">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $rm['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100" data-confirm="Remove '<?= e($rm['room_name']) ?>'?">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<div class="modal fade" id="roomModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="rm_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="roomModalTitle">Add Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Room Name <span class="text-danger">*</span></label>
              <input type="text" name="room_name" id="rm_name" class="form-control" placeholder="e.g. Science Lab 1" required></div>
            <div class="col-md-4"><label class="form-label">Room No.</label>
              <input type="text" name="room_number" id="rm_num" class="form-control" placeholder="101"></div>
            <div class="col-md-4"><label class="form-label">Floor</label>
              <input type="number" name="floor" id="rm_floor" class="form-control" value="1" min="0"></div>
            <div class="col-md-4"><label class="form-label">Capacity</label>
              <input type="number" name="capacity" id="rm_cap" class="form-control" value="30" min="1"></div>
            <div class="col-md-4"><label class="form-label">Type</label>
              <select name="room_type" id="rm_type" class="form-select">
                <?php foreach(['classroom'=>'Classroom','lab'=>'Lab','office'=>'Office','exam_hall'=>'Exam Hall','library'=>'Library','other'=>'Other'] as $k=>$v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Room</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function setRoomForm(r) {
  document.getElementById('roomModalTitle').textContent = r ? 'Edit Room' : 'Add Room';
  document.getElementById('rm_id').value    = r ? r.id : 0;
  document.getElementById('rm_name').value  = r ? r.room_name : '';
  document.getElementById('rm_num').value   = r ? (r.room_number||'') : '';
  document.getElementById('rm_floor').value = r ? r.floor : 1;
  document.getElementById('rm_cap').value   = r ? r.capacity : 30;
  document.getElementById('rm_type').value  = r ? r.room_type : 'classroom';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
