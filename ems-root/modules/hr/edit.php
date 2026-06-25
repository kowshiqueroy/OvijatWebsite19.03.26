<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Edit Staff';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Edit Staff' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['hr.manage']);

$pdo    = db();
$id     = int_param('id', 0, $_GET);
$errors = [];
if (!$id) { flash('error','Invalid ID.'); redirect('staff.php'); }

$stmt = $pdo->prepare('SELECT sp.*, u.username, u.status as u_status FROM staff_profiles sp JOIN users u ON u.id=sp.user_id WHERE sp.user_id=:id');
$stmt->execute([':id' => $id]);
$staff = $stmt->fetch();
if (!$staff) { flash('error','Staff not found.'); redirect('staff.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $first_name  = trim($_POST['first_name']??'');
    $last_name   = trim($_POST['last_name']??'');
    if (!$first_name || !$last_name) { $errors[] = 'Name is required.'; }

    if (empty($errors)) {
        $photo = upload_file('photo', UPLOAD_AVATARS, ['jpg','jpeg','png','webp'], MAX_PHOTO_SIZE);
        $photoSql = $photo ? ', photo=?' : '';

        $params = [
            $first_name, $last_name,
            trim($_POST['designation']??''),
            trim($_POST['department']??''),
            $_POST['dob']??null,
            $_POST['gender']??null,
            trim($_POST['religion']??''),
            trim($_POST['blood_group']??''),
            trim($_POST['nid_no']??''),
            trim($_POST['phone']??''),
            trim($_POST['email']??''),
            trim($_POST['address']??''),
            $_POST['joining_date']??null,
            $_POST['contract_type']??'permanent',
            $_POST['salary_type']??'fixed',
            (float)($_POST['base_salary']??0),
            trim($_POST['bank_account']??''),
            trim($_POST['bank_name']??''),
            $_POST['status']??'active',
        ];
        if ($photo) $params[] = $photo;
        $params[] = $id;

        $pdo->prepare("UPDATE staff_profiles SET first_name=?,last_name=?,designation=?,department=?,dob=?,gender=?,religion=?,blood_group=?,nid_no=?,phone=?,email=?,address=?,joining_date=?,contract_type=?,salary_type=?,base_salary=?,bank_account=?,bank_name=?$photoSql WHERE user_id=?")->execute($params);
        $pdo->prepare('UPDATE users SET full_name=?,status=? WHERE id=?')->execute([$first_name.' '.$last_name,$_POST['status']??'active',$id]);

        log_activity('edit_staff','hr',$id);
        flash('success','Staff profile updated.');
        header("Location: view.php?id=$id");
        exit;
    }
}
$S = fn($k,$d='') => $_POST[$k] ?? $staff[$k] ?? $d;

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Staff</h1>
  <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<?php if(!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
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
            <div class="col-md-6"><label class="form-label">Designation</label><input type="text" name="designation" class="form-control" value="<?= e($S('designation')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= e($S('department')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= e($S('dob')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">—</option><?php foreach(['male','female','other'] as $g): ?><option value="<?= $g ?>" <?= $S('gender')===$g?'selected':'' ?>><?= ucfirst($g) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Blood Group</label><select name="blood_group" class="form-select"><option value="">—</option><?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option value="<?= $bg ?>" <?= $S('blood_group')===$bg?'selected':'' ?>><?= $bg ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Religion</label><select name="religion" class="form-select"><option value="">—</option><?php foreach(['Islam','Hinduism','Christianity','Buddhism','Other'] as $r): ?><option value="<?= $r ?>" <?= $S('religion')===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">NID No</label><input type="text" name="nid_no" class="form-control" value="<?= e($S('nid_no')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($S('phone')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($S('email')) ?>"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($S('address')) ?></textarea></div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header py-3 px-4"><span class="card-title">Employment Details</span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Joining Date</label><input type="date" name="joining_date" class="form-control" value="<?= e($S('joining_date')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Contract Type</label><select name="contract_type" class="form-select"><?php foreach(['permanent'=>'Permanent','contractual'=>'Contractual','part_time'=>'Part Time','guest_lecturer'=>'Guest Lecturer'] as $k=>$v): ?><option value="<?= $k ?>" <?= $S('contract_type')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Salary Type</label><select name="salary_type" class="form-select"><?php foreach(['fixed'=>'Fixed Monthly','hourly'=>'Per Hour','class_wise'=>'Per Class','attendance_based'=>'Attendance Based'] as $k=>$v): ?><option value="<?= $k ?>" <?= $S('salary_type')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Base Salary (৳)</label><input type="number" name="base_salary" class="form-control" step="0.01" value="<?= e($S('base_salary','0')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Bank Account</label><input type="text" name="bank_account" class="form-control" value="<?= e($S('bank_account')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($S('bank_name')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Account Status</label><select name="status" class="form-select"><?php foreach(['active'=>'Active','resigned'=>'Resigned','terminated'=>'Terminated','retired'=>'Retired'] as $k=>$v): ?><option value="<?= $k ?>" <?= $S('status')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header py-3 px-4"><span class="card-title">Photo</span></div>
        <div class="card-body text-center">
          <div class="mx-auto mb-2 rounded-circle overflow-hidden" style="width:90px;height:90px;background:#f1f5f9;border:2px dashed #d1d5db;">
            <?php if($staff['photo'] && file_exists(UPLOAD_AVATARS.$staff['photo'])): ?><img src="../../uploads/avatars/<?= e($staff['photo']) ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#94a3b8;"><?= strtoupper(substr($staff['first_name'],0,1)) ?></div><?php endif; ?>
          </div>
          <input type="file" name="photo" class="form-control form-control-sm" accept="image/*">
          <small class="text-muted">JPG/PNG, max 2MB</small>
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
