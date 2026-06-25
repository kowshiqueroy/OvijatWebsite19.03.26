<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Subjects';
$breadcrumbs = ['Academic' => null, 'Subjects' => null];

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
        $data = [
            trim($_POST['subject_name'] ?? ''),
            trim($_POST['subject_code'] ?? '') ?: null,
            $_POST['subject_type'] ?? 'core',
            isset($_POST['is_group_subject'])  ? 1 : 0,
            isset($_POST['is_religious_alt'])   ? 1 : 0,
            isset($_POST['can_be_4th'])         ? 1 : 0,
            isset($_POST['has_practical'])      ? 1 : 0,
            isset($_POST['has_mcq'])            ? 1 : 0,
        ];
        if ($data[0]) {
            if ($id) {
                $pdo->prepare('UPDATE subjects SET subject_name=?,subject_code=?,subject_type=?,is_group_subject=?,is_religious_alt=?,can_be_4th=?,has_practical=?,has_mcq=? WHERE id=?')
                    ->execute(array_merge($data, [$id]));
                flash('success', 'Subject updated.');
            } else {
                $pdo->prepare('INSERT INTO subjects (subject_name,subject_code,subject_type,is_group_subject,is_religious_alt,can_be_4th,has_practical,has_mcq) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute($data);
                flash('success', 'Subject added.');
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('UPDATE subjects SET status=0 WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Subject removed.');
    }
    header('Location: subjects.php');
    exit;
}

$subjects = $pdo->query('SELECT * FROM subjects WHERE status=1 ORDER BY subject_type, subject_name')->fetchAll();
require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-journal-bookmark-fill me-2 text-primary"></i>Subjects</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="setForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Subject
  </button>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="data-table">
      <thead>
        <tr><th>#</th><th>Name</th><th>Code</th><th>Type</th><th>Flags</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($subjects)): ?>
          <tr><td colspan="6"><div class="empty-state"><i class="bi bi-book"></i><p>No subjects added yet</p></div></td></tr>
        <?php else: foreach ($subjects as $i => $sub): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td class="fw-600"><?= e($sub['subject_name']) ?></td>
          <td><code><?= e($sub['subject_code'] ?? '—') ?></code></td>
          <td><span class="badge bg-light text-dark text-capitalize"><?= e(str_replace('_',' ',$sub['subject_type'])) ?></span></td>
          <td>
            <?php
            $flags = [];
            if ($sub['is_group_subject']) $flags[] = '<span class="badge bg-info text-white">Group</span>';
            if ($sub['is_religious_alt']) $flags[] = '<span class="badge bg-warning text-dark">Religious</span>';
            if ($sub['can_be_4th'])       $flags[] = '<span class="badge bg-secondary">4th Sub</span>';
            if ($sub['has_practical'])    $flags[] = '<span class="badge bg-success text-white">Practical</span>';
            if ($sub['has_mcq'])          $flags[] = '<span class="badge bg-primary text-white">MCQ</span>';
            echo implode(' ', $flags) ?: '—';
            ?>
          </td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#subjectModal"
                      onclick="setForm(<?= htmlspecialchars(json_encode($sub), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        data-confirm="Remove subject '<?= e($sub['subject_name']) ?>'?">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="sub_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="subjectModalTitle">Add Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Subject Name <span class="text-danger">*</span></label>
              <input type="text" name="subject_name" id="sub_name" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Code</label>
              <input type="text" name="subject_code" id="sub_code" class="form-control" maxlength="30">
            </div>
            <div class="col-md-3">
              <label class="form-label">Type</label>
              <select name="subject_type" id="sub_type" class="form-select">
                <option value="core">Core</option>
                <option value="religious">Religious</option>
                <option value="optional">Optional</option>
                <option value="4th_subject">4th Subject</option>
                <option value="practical">Practical Only</option>
              </select>
            </div>
          </div>
          <div class="form-section-title">Subject Flags</div>
          <div class="row g-3">
            <?php $checkboxes = [
              'is_group_subject' => ['Group/Stream Subject','Only for specific stream (Science/Commerce/Arts)'],
              'is_religious_alt' => ['Religious Alternative','Islam/Hinduism etc. — conditionally assigned'],
              'can_be_4th'       => ['Can be 4th Subject','Students can opt as their 4th elective'],
              'has_practical'    => ['Has Practical','Includes practical/lab component with separate marks'],
              'has_mcq'          => ['Has MCQ','Includes multiple-choice section with separate marks'],
            ];
            foreach ($checkboxes as $key => [$label, $desc]): ?>
            <div class="col-md-6">
              <div class="form-check border rounded p-3">
                <input type="checkbox" class="form-check-input" name="<?= $key ?>" id="sub_<?= $key ?>" value="1">
                <label class="form-check-label" for="sub_<?= $key ?>">
                  <span class="fw-600"><?= $label ?></span>
                  <small class="text-muted d-block"><?= $desc ?></small>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Subject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setForm(sub) {
  document.getElementById('subjectModalTitle').textContent = sub ? 'Edit Subject' : 'Add Subject';
  document.getElementById('sub_id').value   = sub ? sub.id : 0;
  document.getElementById('sub_name').value = sub ? sub.subject_name : '';
  document.getElementById('sub_code').value = sub ? (sub.subject_code || '') : '';
  document.getElementById('sub_type').value = sub ? sub.subject_type : 'core';
  ['is_group_subject','is_religious_alt','can_be_4th','has_practical','has_mcq'].forEach(k => {
    document.getElementById('sub_' + k).checked = sub ? sub[k] == 1 : false;
  });
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
