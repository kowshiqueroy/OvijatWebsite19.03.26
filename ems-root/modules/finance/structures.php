<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Fee Structures';
$breadcrumbs = ['Finance' => null, 'Fee Structures' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['finance.view']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('setup.edit')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = int_param('id', 0, $_POST);
        $sessId  = int_param('session_id', 0, $_POST);
        $clsId   = int_param('class_id', 0, $_POST);
        $catId   = int_param('fee_category_id', 0, $_POST);
        $amount  = (float)($_POST['amount'] ?? 0);
        $dueDay  = int_param('due_day', 10, $_POST);
        $freq    = $_POST['frequency'] ?? 'monthly';

        if ($sessId && $clsId && $catId && $amount > 0) {
            if ($id) {
                $pdo->prepare('UPDATE fee_structures SET session_id=?,class_id=?,fee_category_id=?,amount=?,due_day=?,frequency=? WHERE id=?')
                    ->execute([$sessId,$clsId,$catId,$amount,$dueDay,$freq,$id]);
                flash('success', 'Fee structure updated.');
            } else {
                try {
                    $pdo->prepare('INSERT INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')
                        ->execute([$sessId,$clsId,$catId,$amount,$dueDay,$freq]);
                    flash('success', 'Fee structure added.');
                } catch (Exception $e) {
                    flash('error', 'Duplicate entry — this fee already exists for that class/session.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM fee_structures WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Fee structure removed.');
    } elseif ($action === 'generate_ledgers') {
        // Generate monthly fee ledger entries for all active enrollments
        $sessId = int_param('session_id', 0, $_POST);
        $month  = int_param('month', date('n'), $_POST);
        $year   = int_param('year', date('Y'), $_POST);
        $dueDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-10";

        if ($sessId) {
            $enrollments = $pdo->prepare('SELECT student_id, class_id FROM student_enrollments WHERE session_id=:s AND status="active"');
            $enrollments->execute([':s' => $sessId]);
            $enrollments = $enrollments->fetchAll();

            $structures = $pdo->prepare("SELECT * FROM fee_structures WHERE session_id=:s AND frequency IN ('monthly','quarterly','yearly')");
            $structures->execute([':s' => $sessId]);
            $structures = $structures->fetchAll();

            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status)
                 VALUES (?,?,?,?,?,?,?,"unpaid")'
            );

            $count = 0;
            foreach ($enrollments as $en) {
                foreach ($structures as $st) {
                    if ($st['class_id'] != $en['class_id']) continue;
                    $stmt->execute([$en['student_id'],$sessId,$st['fee_category_id'],$st['amount'],$dueDate,$month,$year]);
                    $count++;
                }
            }
            flash('success', "Generated $count fee ledger entries for " . date('F Y', mktime(0,0,0,$month,1,$year)) . ".");
        }
    }
    header('Location: structures.php');
    exit;
}

$sessions   = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$classes    = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order, class_name')->fetchAll();
$feeCategories = $pdo->query('SELECT id, category_name, category_type FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();

$structures = $pdo->prepare(
    'SELECT fs.*, c.class_name, fc.category_name, ass.session_name
     FROM fee_structures fs
     JOIN classes c ON c.id=fs.class_id
     JOIN fee_categories fc ON fc.id=fs.fee_category_id
     JOIN academic_sessions ass ON ass.id=fs.session_id
     WHERE fs.session_id=:sess
     ORDER BY c.display_order, fc.category_name'
);
$structures->execute([':sess' => $session_id]);
$structures = $structures->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-table me-2 text-primary"></i>Fee Structures</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto;">
      <?php foreach ($sessions as $sess): ?>
        <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (has_permission('setup.edit')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#structModal" onclick="setStructForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Fee
    </button>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#genModal">
      <i class="bi bi-lightning-fill me-1"></i>Generate Ledgers
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Class</th><th>Fee Category</th><th>Amount</th><th>Frequency</th><th>Due Day</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($structures)): ?>
          <tr><td colspan="6"><div class="empty-state"><i class="bi bi-table"></i><p>No fee structures defined for this session.</p></div></td></tr>
        <?php else: foreach ($structures as $fs): ?>
        <tr>
          <td class="fw-600"><?= e($fs['class_name']) ?></td>
          <td><?= e($fs['category_name']) ?></td>
          <td class="fw-700"><?= money($fs['amount']) ?></td>
          <td class="text-capitalize"><?= e($fs['frequency']) ?></td>
          <td><?= $fs['due_day'] ?>th of month</td>
          <td>
            <div class="table-actions">
              <?php if (has_permission('setup.edit')): ?>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#structModal"
                      onclick="setStructForm(<?= htmlspecialchars(json_encode($fs), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $fs['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        data-confirm="Remove this fee structure?">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Structure Modal -->
<div class="modal fade" id="structModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="fs_id" value="0">
        <div class="modal-header"><h5 class="modal-title" id="structModalTitle">Add Fee Structure</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Session <span class="text-danger">*</span></label>
            <select name="session_id" id="fs_sess" class="form-select">
              <?php foreach ($sessions as $sess): ?>
                <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Class <span class="text-danger">*</span></label>
            <select name="class_id" id="fs_cls" class="form-select" required>
              <option value="">— All Classes / Select —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>"><?= e($cls['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Fee Category <span class="text-danger">*</span></label>
            <select name="fee_category_id" id="fs_cat" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($feeCategories as $fc): ?>
                <option value="<?= $fc['id'] ?>"><?= e($fc['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-5">
              <label class="form-label">Amount <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><?= e(setting('currency_symbol','৳')) ?></span>
                <input type="number" name="amount" id="fs_amount" class="form-control" step="0.01" min="1" required>
              </div>
            </div>
            <div class="col-4">
              <label class="form-label">Frequency</label>
              <select name="frequency" id="fs_freq" class="form-select">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="yearly">Yearly</option>
                <option value="once">Once</option>
              </select>
            </div>
            <div class="col-3">
              <label class="form-label">Due Day</label>
              <input type="number" name="due_day" id="fs_due" class="form-control" min="1" max="28" value="10">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Generate Ledgers Modal -->
<div class="modal fade" id="genModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate_ledgers">
        <div class="modal-header"><h5 class="modal-title">Generate Monthly Fee Ledgers</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="alert alert-info d-flex gap-2">
            <i class="bi bi-info-circle-fill"></i>
            This creates individual fee ledger entries for all active students based on the fee structures above. Duplicates are automatically skipped.
          </div>
          <input type="hidden" name="session_id" value="<?= $session_id ?>">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Month</label>
              <select name="month" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                  <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Year</label>
              <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2035">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-lightning-fill me-1"></i>Generate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setStructForm(fs) {
  document.getElementById('structModalTitle').textContent = fs ? 'Edit Fee Structure' : 'Add Fee Structure';
  document.getElementById('fs_id').value     = fs ? fs.id : 0;
  document.getElementById('fs_cls').value    = fs ? fs.class_id : '';
  document.getElementById('fs_cat').value    = fs ? fs.fee_category_id : '';
  document.getElementById('fs_amount').value = fs ? fs.amount : '';
  document.getElementById('fs_freq').value   = fs ? fs.frequency : 'monthly';
  document.getElementById('fs_due').value    = fs ? fs.due_day : 10;
  if (fs) document.getElementById('fs_sess').value = fs.session_id;
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
