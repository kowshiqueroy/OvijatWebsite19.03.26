<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Add Staff';
$breadcrumbs = ['HR & Payroll' => 'staff.php', 'Add Staff' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['hr.manage']);

$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $designation  = trim($_POST['designation'] ?? '');
    $department   = trim($_POST['department'] ?? '');
    $dob          = $_POST['dob'] ?? '';
    $gender       = $_POST['gender'] ?? '';
    $religion     = trim($_POST['religion'] ?? '');
    $blood_group  = trim($_POST['blood_group'] ?? '');
    $nid_no       = trim($_POST['nid_no'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
    $contract     = $_POST['contract_type'] ?? 'permanent';
    $salary_type  = $_POST['salary_type'] ?? 'fixed';
    $base_salary  = (float)($_POST['base_salary'] ?? 0);
    $bank_account = trim($_POST['bank_account'] ?? '');
    $bank_name    = trim($_POST['bank_name'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role_ids     = array_map('intval', (array)($_POST['role_ids'] ?? []));

    if (!$first_name)  $errors[] = 'First name is required.';
    if (!$last_name)   $errors[] = 'Last name is required.';
    if (!$username)    $errors[] = 'Username is required.';
    if (strlen($password) < 4) $errors[] = 'Password must be at least 4 characters.';

    if (empty($errors)) {
        $ck = $pdo->prepare('SELECT id FROM users WHERE username=:u');
        $ck->execute([':u' => $username]);
        if ($ck->fetch()) $errors[] = 'Username already taken.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, phone, status) VALUES (?,?,?,?,?,?)')
                ->execute([$username, $hash, $first_name.' '.$last_name, $email, $phone, 'active']);
            $userId = (int)$pdo->lastInsertId();

            // Auto employee ID
            $empCount = (int)$pdo->query('SELECT COUNT(*) FROM staff_profiles')->fetchColumn() + 1;
            $empId    = 'EMP-' . date('Y') . '-' . str_pad($empCount, 4, '0', STR_PAD_LEFT);

            $photo = upload_file('photo', UPLOAD_AVATARS, ['jpg','jpeg','png','webp'], MAX_PHOTO_SIZE) ?: null;

            $pdo->prepare(
                'INSERT INTO staff_profiles
                 (user_id,employee_id,first_name,last_name,designation,department,dob,gender,religion,blood_group,photo,
                  phone,email,address,nid_no,joining_date,contract_type,salary_type,base_salary,bank_account,bank_name)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$userId,$empId,$first_name,$last_name,$designation,$department,$dob,$gender,$religion,$blood_group,$photo,
                         $phone,$email,$address,$nid_no,$joining_date,$contract,$salary_type,$base_salary,$bank_account,$bank_name]);

            $rpStmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)');
            foreach ($role_ids as $rid) { if ($rid) $rpStmt->execute([$userId, $rid]); }

            $pdo->commit();
            log_activity('create_staff', 'hr', $userId, '', "$first_name $last_name");
            flash('success', "Staff '$first_name $last_name' added. Employee ID: $empId");
            header('Location: staff.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

$allRoles = $pdo->query("SELECT id, role_name FROM roles WHERE role_slug NOT IN ('student','guardian') ORDER BY role_name")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-workspace me-2 text-primary"></i>Add Staff Member</h1>
  <a href="staff.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="row g-3">
    <!-- Personal Info -->
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Personal Information</span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? '') ?>" required></div>
            <div class="col-md-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? '') ?>" required></div>
            <div class="col-md-6"><label class="form-label">Designation</label><input type="text" name="designation" class="form-control" value="<?= e($_POST['designation'] ?? '') ?>" placeholder="e.g. Assistant Teacher, Lecturer"></div>
            <div class="col-md-6"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= e($_POST['department'] ?? '') ?>" placeholder="e.g. Science, Administration"></div>
            <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= e($_POST['dob'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Gender</label>
              <select name="gender" class="form-select"><option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
            <div class="col-md-4"><label class="form-label">Religion</label>
              <select name="religion" class="form-select"><option value="">—</option><?php foreach(['Islam','Hinduism','Christianity','Buddhism','Other'] as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-select"><option value="">—</option><?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option value="<?= $bg ?>"><?= $bg ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">NID No</label><input type="text" name="nid_no" class="form-control" value="<?= e($_POST['nid_no'] ?? '') ?>"></div>
            <div class="col-md-5"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= e($_POST['address'] ?? '') ?></textarea></div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Employment Details</span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Joining Date</label><input type="date" name="joining_date" class="form-control" value="<?= e($_POST['joining_date'] ?? date('Y-m-d')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Contract Type</label>
              <select name="contract_type" class="form-select">
                <?php foreach(['permanent'=>'Permanent','contractual'=>'Contractual','part_time'=>'Part Time','guest_lecturer'=>'Guest Lecturer'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= ($_POST['contract_type'] ?? 'permanent') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label">Salary Type</label>
              <select name="salary_type" class="form-select">
                <?php foreach(['fixed'=>'Fixed Monthly','hourly'=>'Per Hour','class_wise'=>'Per Class','attendance_based'=>'Attendance Based'] as $k=>$v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label">Base Salary (৳)</label><input type="number" name="base_salary" class="form-control" step="0.01" value="<?= e($_POST['base_salary'] ?? '0') ?>"></div>
            <div class="col-md-4"><label class="form-label">Bank Account</label><input type="text" name="bank_account" class="form-control" value="<?= e($_POST['bank_account'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($_POST['bank_name'] ?? '') ?>"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right column -->
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Photo</span></div>
        <div class="card-body text-center">
          <div id="photoPreview" class="mx-auto mb-2 rounded" style="width:100px;height:120px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px dashed #d1d5db;">
            <i class="bi bi-person-fill text-muted" style="font-size:2.5rem;"></i>
          </div>
          <input type="file" name="photo" class="form-control form-control-sm" accept="image/*"
                 onchange="previewPhoto(this)">
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">System Account</span></div>
        <div class="card-body">
          <div class="mb-3"><label class="form-label">Username <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required></div>
          <div class="mb-0"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header py-3 px-4"><span class="card-title">Assign Roles</span></div>
        <div class="card-body">
          <?php foreach ($allRoles as $role): ?>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="role_ids[]" id="r_<?= $role['id'] ?>" value="<?= $role['id'] ?>">
            <label class="form-check-label" for="r_<?= $role['id'] ?>"><?= e($role['role_name']) ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Save Staff</button>
    <a href="staff.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
  </div>
</form>

<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const prev = document.getElementById('photoPreview');
      prev.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
