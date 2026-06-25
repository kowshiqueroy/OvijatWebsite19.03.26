<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Edit Student';
$breadcrumbs = ['Students' => 'index.php', 'Edit' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.edit']);

$pdo    = db();
$id     = int_param('id', 0, $_GET);
$errors = [];

if (!$id) { flash('error','Invalid ID.'); redirect('index.php'); }

$stmt = $pdo->prepare('SELECT u.*, sp.* FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=:id');
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();
if (!$student) { flash('error','Student not found.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');

    if (!$first_name || !$last_name) { $errors[] = 'Name is required.'; }

    if (empty($errors)) {
        $pdo->prepare('UPDATE users SET full_name=? WHERE id=?')
            ->execute([$first_name . ' ' . $last_name, $id]);

        $photo = upload_file('photo', UPLOAD_PHOTOS, ['jpg','jpeg','png','webp'], MAX_PHOTO_SIZE);
        $photoSql = $photo ? ', photo=?' : '';
        $params   = [
            $_POST['dob'] ?? null,
            $_POST['gender'] ?? null,
            $_POST['religion'] ?? null,
            $_POST['blood_group'] ?? null,
            trim($_POST['father_name'] ?? ''),
            trim($_POST['mother_name'] ?? ''),
            trim($_POST['guardian_name'] ?? ''),
            trim($_POST['guardian_phone'] ?? ''),
            trim($_POST['guardian_relation'] ?? 'Father'),
            trim($_POST['address_present'] ?? ''),
            trim($_POST['address_permanent'] ?? ''),
            trim($_POST['birth_certificate_no'] ?? ''),
            trim($_POST['previous_school'] ?? ''),
            $first_name, $last_name, $id,
        ];
        if ($photo) array_splice($params, count($params) - 1, 0, [$photo]);

        $pdo->prepare("UPDATE student_profiles SET dob=?,gender=?,religion=?,blood_group=?,father_name=?,mother_name=?,guardian_name=?,guardian_phone=?,guardian_relation=?,address_present=?,address_permanent=?,birth_certificate_no=?,previous_school=?,first_name=?,last_name=?$photoSql WHERE user_id=?")->execute($params);

        log_activity('edit_student', 'students', $id);
        flash('success', 'Student updated.');
        header("Location: view.php?id=$id");
        exit;
    }
}
$S = fn($k,$d='') => $_POST[$k] ?? $student[$k] ?? $d;

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Student</h1>
  <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Personal Information</span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?= e($S('first_name')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= e($S('last_name')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">DOB</label><input type="date" name="dob" class="form-control" value="<?= e($S('dob')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Gender</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                <?php foreach(['male','female','other'] as $g): ?><option value="<?= $g ?>" <?= $S('gender')===$g?'selected':'' ?>><?= ucfirst($g) ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-select">
                <option value="">—</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option value="<?= $bg ?>" <?= $S('blood_group')===$bg?'selected':'' ?>><?= $bg ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Religion</label>
              <select name="religion" class="form-select">
                <option value="">—</option>
                <?php foreach(['Islam','Hinduism','Christianity','Buddhism','Other'] as $r): ?><option value="<?= $r ?>" <?= $S('religion')===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">Birth Certificate No</label><input type="text" name="birth_certificate_no" class="form-control" value="<?= e($S('birth_certificate_no')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Father's Name</label><input type="text" name="father_name" class="form-control" value="<?= e($S('father_name')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Mother's Name</label><input type="text" name="mother_name" class="form-control" value="<?= e($S('mother_name')) ?>"></div>
            <div class="col-md-5"><label class="form-label">Guardian Name</label><input type="text" name="guardian_name" class="form-control" value="<?= e($S('guardian_name')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Relation</label>
              <select name="guardian_relation" class="form-select">
                <?php foreach(['Father','Mother','Uncle','Aunt','Brother','Sister','Other'] as $r): ?><option value="<?= $r ?>" <?= $S('guardian_relation')===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label">Guardian Phone</label><input type="text" name="guardian_phone" class="form-control" value="<?= e($S('guardian_phone')) ?>"></div>
            <div class="col-12"><label class="form-label">Present Address</label><textarea name="address_present" class="form-control" rows="2"><?= e($S('address_present')) ?></textarea></div>
            <div class="col-12"><label class="form-label">Permanent Address</label><textarea name="address_permanent" class="form-control" rows="2"><?= e($S('address_permanent')) ?></textarea></div>
            <div class="col-12"><label class="form-label">Previous School</label><input type="text" name="previous_school" class="form-control" value="<?= e($S('previous_school')) ?>"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Photo</span></div>
        <div class="card-body text-center">
          <div class="mx-auto mb-2 rounded" style="width:110px;height:130px;background:#f1f5f9;overflow:hidden;border:2px dashed #d1d5db;">
            <?php if ($student['photo'] && file_exists(UPLOAD_PHOTOS . $student['photo'])): ?>
              <img src="../../uploads/photos/<?= e($student['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#94a3b8;">
                <?= strtoupper(substr($student['first_name'],0,1)) ?>
              </div>
            <?php endif; ?>
          </div>
          <input type="file" name="photo" class="form-control form-control-sm" accept="image/*">
          <small class="text-muted">JPG/PNG, max 2MB</small>
        </div>
      </div>
      <div class="card">
        <div class="card-header py-3 px-4"><span class="card-title">Student ID</span></div>
        <div class="card-body">
          <div class="mb-2"><label class="form-label small text-muted">ID Number</label>
            <input class="form-control bg-light" value="<?= e($student['student_id_no'] ?? '') ?>" readonly></div>
          <div class="mb-0"><label class="form-label small text-muted">Username</label>
            <input class="form-control bg-light" value="<?= e($student['username'] ?? '') ?>" readonly></div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
    <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
