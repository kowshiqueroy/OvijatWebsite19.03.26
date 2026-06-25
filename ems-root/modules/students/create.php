<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'New Admission';
$breadcrumbs = ['Students' => 'index.php', 'New Admission' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['students.create']);

$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $dob          = $_POST['dob'] ?? '';
    $gender       = $_POST['gender'] ?? '';
    $religion     = trim($_POST['religion'] ?? '');
    $blood_group  = trim($_POST['blood_group'] ?? '');
    $father_name  = trim($_POST['father_name'] ?? '');
    $mother_name  = trim($_POST['mother_name'] ?? '');
    $guardian_name  = trim($_POST['guardian_name'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');
    $guardian_relation = trim($_POST['guardian_relation'] ?? 'Father');
    $address_present   = trim($_POST['address_present'] ?? '');
    $address_permanent = trim($_POST['address_permanent'] ?? '');
    $birth_cert   = trim($_POST['birth_certificate_no'] ?? '');
    $admission_date = $_POST['admission_date'] ?? date('Y-m-d');
    $prev_school  = trim($_POST['previous_school'] ?? '');

    // Login credentials
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Enrollment
    $session_id = int_param('session_id', 0, $_POST);
    $class_id   = int_param('class_id', 0, $_POST);
    $section_id = int_param('section_id', 0, $_POST);
    $roll       = int_param('roll_number', 0, $_POST);

    if (!$first_name)     $errors[] = 'First name is required.';
    if (!$last_name)      $errors[] = 'Last name is required.';
    if (!$username)       $errors[] = 'Username is required.';
    if (strlen($password) < 4) $errors[] = 'Password must be at least 4 characters.';
    if (!$session_id)     $errors[] = 'Academic session is required.';
    if (!$class_id)       $errors[] = 'Class is required.';
    if (!$section_id)     $errors[] = 'Section is required.';

    if (empty($errors)) {
        $ck = $pdo->prepare('SELECT id FROM users WHERE username=:u');
        $ck->execute([':u' => $username]);
        if ($ck->fetch()) $errors[] = 'Username already taken.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Create user account
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (username, password_hash, full_name, status) VALUES (?,?,?,?)')
                ->execute([$username, $hash, $first_name . ' ' . $last_name, 'active']);
            $userId = (int)$pdo->lastInsertId();

            // Auto ID number: YYYY-CLS-ROLL
            $clsCode = $pdo->prepare('SELECT class_numeric FROM classes WHERE id=:id')->execute([':id'=>$class_id]) ? '' : '';
            $studentIdNo = date('Y') . '-' . str_pad($class_id, 2, '0', STR_PAD_LEFT) . '-' . str_pad($roll ?: $userId, 4, '0', STR_PAD_LEFT);

            // Photo upload
            $photo = upload_file('photo', UPLOAD_PHOTOS, ['jpg','jpeg','png','webp'], MAX_PHOTO_SIZE) ?: null;

            // Student profile
            $pdo->prepare(
                'INSERT INTO student_profiles
                 (user_id,student_id_no,first_name,last_name,dob,gender,religion,blood_group,photo,
                  father_name,mother_name,guardian_name,guardian_phone,guardian_relation,
                  address_present,address_permanent,birth_certificate_no,previous_school,admission_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$userId,$studentIdNo,$first_name,$last_name,$dob,$gender,$religion,$blood_group,$photo,
                         $father_name,$mother_name,$guardian_name,$guardian_phone,$guardian_relation,
                         $address_present,$address_permanent,$birth_cert,$prev_school,$admission_date]);

            // Next roll number if not provided
            if (!$roll) {
                $maxRoll = $pdo->prepare('SELECT COALESCE(MAX(roll_number),0) FROM student_enrollments WHERE session_id=? AND class_id=? AND section_id=?');
                $maxRoll->execute([$session_id,$class_id,$section_id]);
                $roll = (int)$maxRoll->fetchColumn() + 1;
            }

            // Enrollment
            $pdo->prepare('INSERT INTO student_enrollments (student_id,session_id,class_id,section_id,roll_number,status) VALUES (?,?,?,?,?,?)')
                ->execute([$userId,$session_id,$class_id,$section_id,$roll,'active']);

            // Assign student role
            $studentRole = $pdo->query("SELECT id FROM roles WHERE role_slug='student' LIMIT 1")->fetchColumn();
            if ($studentRole) {
                $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$userId, $studentRole]);
            }

            $pdo->commit();
            log_activity('admit_student', 'students', $userId, '', $first_name . ' ' . $last_name);
            flash('success', "Student '{$first_name} {$last_name}' admitted. ID: {$studentIdNo}");
            header('Location: index.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes  = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order, class_numeric')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-person-plus-fill me-2 text-primary"></i>New Student Admission</h1>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="row g-3">
    <!-- Personal Info -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header py-3 px-4"><span class="card-title">Personal Information</span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?= e($_POST['dob'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select">
                <option value="">— Select —</option>
                <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-select">
                <option value="">— —</option>
                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                  <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Religion</label>
              <select name="religion" class="form-select">
                <option value="">— Select —</option>
                <?php foreach (['Islam','Hinduism','Christianity','Buddhism','Other'] as $rel): ?>
                  <option value="<?= $rel ?>" <?= ($_POST['religion'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Birth Certificate No</label>
              <input type="text" name="birth_certificate_no" class="form-control" value="<?= e($_POST['birth_certificate_no'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Father's Name</label>
              <input type="text" name="father_name" class="form-control" value="<?= e($_POST['father_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mother's Name</label>
              <input type="text" name="mother_name" class="form-control" value="<?= e($_POST['mother_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Guardian Name</label>
              <input type="text" name="guardian_name" class="form-control" value="<?= e($_POST['guardian_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Relation</label>
              <select name="guardian_relation" class="form-select">
                <?php foreach (['Father','Mother','Uncle','Aunt','Brother','Sister','Other'] as $rel): ?>
                  <option value="<?= $rel ?>" <?= ($_POST['guardian_relation'] ?? 'Father') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Guardian Phone</label>
              <input type="text" name="guardian_phone" class="form-control" value="<?= e($_POST['guardian_phone'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Present Address</label>
              <textarea name="address_present" class="form-control" rows="2"><?= e($_POST['address_present'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Permanent Address</label>
              <textarea name="address_permanent" class="form-control" rows="2"><?= e($_POST['address_permanent'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Previous School</label>
              <input type="text" name="previous_school" class="form-control" value="<?= e($_POST['previous_school'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Admission Date</label>
              <input type="date" name="admission_date" class="form-control" value="<?= e($_POST['admission_date'] ?? date('Y-m-d')) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right column -->
    <div class="col-md-4">
      <!-- Photo -->
      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Photo</span></div>
        <div class="card-body text-center">
          <div id="photoPreview" class="mx-auto mb-2 rounded" style="width:120px;height:140px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px dashed #d1d5db;">
            <i class="bi bi-person-fill text-muted" style="font-size:3rem;"></i>
          </div>
          <input type="file" name="photo" class="form-control form-control-sm" accept="image/*"
                 onchange="previewPhoto(this)">
          <small class="text-muted">JPG/PNG, max 2MB, passport size</small>
        </div>
      </div>

      <!-- Login credentials -->
      <div class="card mb-3">
        <div class="card-header py-3 px-4"><span class="card-title">Login Credentials</span></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required>
          </div>
        </div>
      </div>

      <!-- Enrollment -->
      <div class="card">
        <div class="card-header py-3 px-4"><span class="card-title">Enrollment</span></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Session <span class="text-danger">*</span></label>
            <select name="session_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php $curSess = (int)setting('current_session_id',0); foreach ($sessions as $sess): ?>
                <option value="<?= $sess['id'] ?>" <?= (int)($_POST['session_id'] ?? $curSess) === (int)$sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Class <span class="text-danger">*</span></label>
            <select name="class_id" id="class_sel" class="form-select" data-load-sections="section_sel" required onchange="loadSections(this.value)">
              <option value="">— Select —</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>" <?= (int)($_POST['class_id'] ?? 0) === (int)$cls['id'] ? 'selected' : '' ?>><?= e($cls['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Section <span class="text-danger">*</span></label>
            <select name="section_id" id="section_sel" class="form-select" required>
              <option value="">— Select class first —</option>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label">Roll Number <small class="text-muted">(leave 0 for auto)</small></label>
            <input type="number" name="roll_number" class="form-control" value="<?= e($_POST['roll_number'] ?? '0') ?>" min="0">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="bi bi-person-check me-2"></i>Complete Admission
    </button>
    <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
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

function loadSections(classId) {
  const sel = document.getElementById('section_sel');
  if (!classId) { sel.innerHTML = '<option value="">— Select class first —</option>'; return; }
  sel.innerHTML = '<option>Loading…</option>';
  fetch(`../academic/ajax.php?action=sections&class_id=${classId}`)
    .then(r => r.json())
    .then(data => {
      sel.innerHTML = '<option value="">— Select Section —</option>';
      data.forEach(s => { sel.innerHTML += `<option value="${s.id}">${s.section_name} (${s.shift})</option>`; });
    })
    .catch(() => { sel.innerHTML = '<option value="">Error</option>'; });
}

// Auto-load sections if class already selected (on error reload)
const clsSel = document.getElementById('class_sel');
if (clsSel && clsSel.value) loadSections(clsSel.value);
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
