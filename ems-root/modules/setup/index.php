<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'System Settings';
$breadcrumbs = ['Setup' => null, 'System Settings' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['setup.view']);

$pdo = db();
$saved = false;

// Handle POST – save settings grouped by tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (has_permission('setup.edit')) {
        $fields = $_POST['settings'] ?? [];
        $stmt   = $pdo->prepare('INSERT INTO system_settings (meta_key, meta_value, meta_group)
                                  VALUES (:k, :v, :g)
                                  ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), meta_group=VALUES(meta_group)');
        foreach ($fields as $key => $val) {
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if (!$key) continue;
            // Determine group from key prefix
            $group = match(true) {
                str_starts_with($key, 'school_') || in_array($key, ['timezone','date_format','per_page','system_version']) => 'general',
                str_starts_with($key, 'academic_') || str_starts_with($key, 'working_') || $key === 'current_session_id' => 'academic',
                str_starts_with($key, 'currency') || str_starts_with($key, 'receipt') => 'finance',
                str_starts_with($key, 'sms_') => 'communication',
                default => 'general',
            };
            $stmt->execute([':k' => $key, ':v' => $val, ':g' => $group]);
        }
        // Handle logo upload
        if (!empty($_FILES['school_logo']['name'])) {
            $logo = upload_file('school_logo', UPLOAD_LOGOS, ['jpg','jpeg','png','webp'], MAX_PHOTO_SIZE);
            if ($logo) {
                $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ("school_logo",:v,"general")
                               ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)')->execute([':v' => $logo]);
            }
        }
        log_activity('update_settings', 'setup');
        flash('success', 'Settings saved successfully.');
        header('Location: index.php');
        exit;
    }
}

// Load all settings
$settingsRaw = $pdo->query('SELECT meta_key, meta_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$S = fn(string $k, string $d = '') => $settingsRaw[$k] ?? $d;

// Active sessions for dropdown
$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-gear-fill me-2 text-primary"></i>System Settings</h1>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <!-- Tab nav -->
  <ul class="nav nav-tabs mb-4" id="settingsTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-academic">Academic</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-finance">Finance</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-sms">SMS Gateway</a></li>
  </ul>

  <div class="tab-content">

    <!-- General -->
    <div class="tab-pane fade show active" id="tab-general">
      <div class="card">
        <div class="card-body">
          <div class="form-section-title mt-0">Institute Information</div>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">School / College Name <span class="text-danger">*</span></label>
              <input type="text" name="settings[school_name]" class="form-control" value="<?= e($S('school_name')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Institute Type</label>
              <select name="settings[school_type]" class="form-select">
                <?php foreach (['school'=>'School','college'=>'College','school_and_college'=>'School & College','madrasa'=>'Madrasa'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $S('school_type') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" name="settings[school_address]" class="form-control" value="<?= e($S('school_address')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Phone</label>
              <input type="text" name="settings[school_phone]" class="form-control" value="<?= e($S('school_phone')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="settings[school_email]" class="form-control" value="<?= e($S('school_email')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Logo <small class="text-muted">(JPG/PNG, max 2MB)</small></label>
              <input type="file" name="school_logo" class="form-control" accept="image/*">
              <?php if ($S('school_logo') && file_exists(UPLOAD_LOGOS . $S('school_logo'))): ?>
                <img src="../../uploads/logos/<?= e($S('school_logo')) ?>" height="40" class="mt-2 rounded">
              <?php endif; ?>
            </div>
          </div>

          <div class="form-section-title">System Preferences</div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Timezone</label>
              <select name="settings[timezone]" class="form-select">
                <?php foreach (['Asia/Dhaka'=>'Asia/Dhaka (BD)','Asia/Calcutta'=>'Asia/Calcutta','UTC'=>'UTC'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $S('timezone','Asia/Dhaka') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date Format</label>
              <select name="settings[date_format]" class="form-select">
                <option value="Y-m-d" <?= $S('date_format') === 'Y-m-d' ? 'selected' : '' ?>>2026-06-25</option>
                <option value="d-m-Y" <?= $S('date_format') === 'd-m-Y' ? 'selected' : '' ?>>25-06-2026</option>
                <option value="d/m/Y" <?= $S('date_format') === 'd/m/Y' ? 'selected' : '' ?>>25/06/2026</option>
                <option value="d M Y" <?= $S('date_format') === 'd M Y' ? 'selected' : '' ?>>25 Jun 2026</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Records Per Page</label>
              <select name="settings[per_page]" class="form-select">
                <?php foreach ([10,25,50,100] as $n): ?>
                  <option value="<?= $n ?>" <?= $S('per_page','25') == $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Academic -->
    <div class="tab-pane fade" id="tab-academic">
      <div class="card">
        <div class="card-body">
          <div class="form-section-title mt-0">Academic Configuration</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Current Academic Session</label>
              <select name="settings[current_session_id]" class="form-select">
                <option value="0">— Not set —</option>
                <?php foreach ($sessions as $sess): ?>
                  <option value="<?= $sess['id'] ?>" <?= $S('current_session_id','0') == $sess['id'] ? 'selected' : '' ?>>
                    <?= e($sess['session_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">
                <a href="../academic/sessions.php">Manage Sessions</a>
              </small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Education Board</label>
              <select name="settings[academic_board]" class="form-select">
                <?php foreach (EDUCATION_BOARDS as $k => $v): ?>
                  <option value="<?= $k ?>" <?= $S('academic_board') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label">Working Days</label>
              <div class="d-flex flex-wrap gap-3 mt-1">
                <?php
                $working = explode(',', $S('working_days', 'Sat,Sun,Mon,Tue,Wed'));
                $days    = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
                $short   = ['Sat','Sun','Mon','Tue','Wed','Thu','Fri'];
                foreach ($days as $i => $day):
                  $s = $short[$i];
                  $checked = in_array($s, $working);
                ?>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="wd_<?= $s ?>"
                         name="working_days_cb[]" value="<?= $s ?>" <?= $checked ? 'checked' : '' ?>>
                  <label class="form-check-label" for="wd_<?= $s ?>"><?= $day ?></label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Finance -->
    <div class="tab-pane fade" id="tab-finance">
      <div class="card">
        <div class="card-body">
          <div class="form-section-title mt-0">Finance Settings</div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Currency Symbol</label>
              <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= e($S('currency_symbol','৳')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Receipt Prefix</label>
              <input type="text" name="settings[receipt_prefix]" class="form-control" value="<?= e($S('receipt_prefix','RCP')) ?>" maxlength="10">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SMS Gateway -->
    <div class="tab-pane fade" id="tab-sms">
      <div class="card">
        <div class="card-body">
          <div class="form-section-title mt-0">SMS Gateway Configuration</div>
          <div class="alert alert-info d-flex gap-2">
            <i class="bi bi-info-circle-fill"></i>
            Supported gateways: Greenweb BD, SSLWireless, Teletalk, BulkSMS. Enter your credentials below.
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Gateway Provider</label>
              <select name="settings[sms_gateway]" class="form-select">
                <?php foreach (['greenweb'=>'Greenweb BD','ssl'=>'SSLWireless','teletalk'=>'Teletalk','custom'=>'Custom API'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $S('sms_gateway') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">API Key / Username</label>
              <input type="text" name="settings[sms_api_key]" class="form-control" value="<?= e($S('sms_api_key')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Sender ID / Password</label>
              <input type="text" name="settings[sms_sender_id]" class="form-control" value="<?= e($S('sms_sender_id')) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->

  <?php if (has_permission('setup.edit')): ?>
  <div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
    <a href="../../dashboard.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
  <?php endif; ?>
</form>

<script>
// Collect working days checkboxes into hidden field
document.querySelector('form').addEventListener('submit', function() {
  const cbs = document.querySelectorAll('[name="working_days_cb[]"]:checked');
  const vals = Array.from(cbs).map(c => c.value).join(',');
  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = 'settings[working_days]';
  hidden.value = vals;
  this.appendChild(hidden);
});
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
