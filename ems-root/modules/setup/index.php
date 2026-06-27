<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'System Settings';
$breadcrumbs = ['Setup' => null, 'System Settings' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['setup.view']);

$pdo   = db();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_auth(['setup.edit']);

    $fields = $_POST['settings'] ?? [];
    $stmt   = $pdo->prepare('INSERT INTO system_settings (meta_key, meta_value, meta_group)
                              VALUES (:k, :v, :g)
                              ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), meta_group=VALUES(meta_group)');
    foreach ($fields as $key => $val) {
        $key = preg_replace('/[^a-z0-9_]/', '', $key);
        if (!$key) continue;
        $group = match(true) {
            str_starts_with($key,'school_') || in_array($key,['timezone','date_format','per_page','system_version','principal_designation']) => 'general',
            str_starts_with($key,'academic_') || str_starts_with($key,'working_') || $key === 'current_session_id' => 'academic',
            str_starts_with($key,'currency') || str_starts_with($key,'receipt')
                || in_array($key,['allow_partial_payment','partial_min_percent','partial_requires_approval',
                                  'admit_card_dues_allow','result_card_dues_allow','certificate_dues_allow']) => 'finance',
            str_starts_with($key,'sms_') => 'communication',
            default => 'general',
        };
        $stmt->execute([':k' => $key, ':v' => is_array($val) ? implode(',', $val) : $val, ':g' => $group]);
    }

    // ── Logo upload ──────────────────────────────────────────
    if (!empty($_FILES['school_logo']['name'])) {
        require_once EMS_ROOT . '/config/constants.php';
        $logo = upload_file('school_logo', UPLOAD_LOGOS, ['jpg','jpeg','png','webp'], MAX_PHOTO_SIZE);
        if ($logo) {
            $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ("school_logo",:v,"general")
                           ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)')->execute([':v' => $logo]);
        }
    }

    // ── Favicon upload ───────────────────────────────────────
    if (!empty($_FILES['school_favicon']['name'])) {
        require_once EMS_ROOT . '/config/constants.php';
        $fav = upload_file('school_favicon', UPLOAD_LOGOS, ['ico','png','jpg','jpeg'], MAX_PHOTO_SIZE);
        if ($fav) {
            $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ("school_favicon",:v,"general")
                           ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)')->execute([':v' => $fav]);
        }
    }

    // ── SMS management numbers (JSON array) ──────────────────
    if (isset($_POST['sms_mgmt_names'])) {
        $names  = $_POST['sms_mgmt_names']  ?? [];
        $phones = $_POST['sms_mgmt_phones'] ?? [];
        $pdo->exec('DELETE FROM sms_management_numbers');
        $mgmtStmt = $pdo->prepare('INSERT INTO sms_management_numbers (name, phone) VALUES (?,?)');
        foreach ($names as $i => $name) {
            $phone = trim($phones[$i] ?? '');
            $name  = trim($name);
            if ($name && $phone) $mgmtStmt->execute([$name, $phone]);
        }
    }

    // ── SMS feature settings ──────────────────────────────────
    if (isset($_POST['sms_features'])) {
        $sfStmt = $pdo->prepare('UPDATE sms_feature_settings SET is_enabled=:e, send_to=:st WHERE feature_key=:k');
        foreach ($_POST['sms_features'] as $key => $data) {
            $sfStmt->execute([
                ':k'  => $key,
                ':e'  => isset($data['enabled']) ? 1 : 0,
                ':st' => $data['send_to'] ?? 'both',
            ]);
        }
    }

    log_activity('update_settings', 'setup');
    flash('success', 'Settings saved successfully.');
    header('Location: index.php');
    exit;
}

// ── Load data ─────────────────────────────────────────────────────────────────
$S = fn(string $k, string $d = '') => $pdo->query("SELECT meta_value FROM system_settings WHERE meta_key='" . addslashes($k) . "'")->fetchColumn() ?: $d;
$sessions        = $pdo->query('SELECT id, session_name FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();
$managementNums  = $pdo->query('SELECT * FROM sms_management_numbers ORDER BY id')->fetchAll();
$smsFeatures     = $pdo->query('SELECT * FROM sms_feature_settings ORDER BY id')->fetchAll();
$designationTypes= $pdo->query('SELECT designation_name FROM designation_types ORDER BY display_order')->fetchAll(PDO::FETCH_COLUMN);
$working_days_db = $S('working_days', 'Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday');
$working_arr     = array_map('trim', explode(',', $working_days_db));

require_once EMS_ROOT . '/includes/header.php';
require_once EMS_ROOT . '/config/constants.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-gear-fill me-2 text-primary"></i>System Settings</h1>
</div>

<form method="POST" enctype="multipart/form-data" id="settingsForm">
  <?= csrf_field() ?>

  <ul class="nav nav-tabs mb-4" id="settingsTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-general"><i class="bi bi-building me-1"></i>Institute</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-academic"><i class="bi bi-book me-1"></i>Academic</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-finance"><i class="bi bi-cash-coin me-1"></i>Finance</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-sms"><i class="bi bi-chat-dots me-1"></i>SMS</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-desig"><i class="bi bi-person-badge me-1"></i>Designations</a></li>
  </ul>

  <div class="tab-content">

    <!-- ══ GENERAL ═══════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade show active" id="tab-general">
      <div class="card mb-3">
        <div class="card-header"><h6 class="card-title">Institute Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">School / College Name <span class="text-danger">*</span></label>
              <input type="text" name="settings[school_name]" class="form-control" value="<?= e($S('school_name')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">EIIN Number</label>
              <input type="text" name="settings[school_eiin]" class="form-control" value="<?= e($S('school_eiin')) ?>" placeholder="e.g. 123456">
            </div>
            <div class="col-md-6">
              <label class="form-label">Institute Type</label>
              <select name="settings[school_type]" class="form-select">
                <?php foreach (['school'=>'School (Playgroup–SSC)','college'=>'College (HSC)','school_and_college'=>'School & College (Combined)','madrasa'=>'Madrasa','technical'=>'Technical / Vocational'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $S('school_type') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Head of Institution Designation</label>
              <input type="text" name="settings[principal_designation]" class="form-control"
                     value="<?= e($S('principal_designation','Principal')) ?>"
                     list="desig-list" placeholder="Principal / Headmaster / Headmistress…">
              <datalist id="desig-list">
                <?php foreach ($designationTypes as $d): ?>
                  <option value="<?= e($d) ?>">
                <?php endforeach; ?>
              </datalist>
              <small class="text-muted">This title appears on certificates and documents.</small>
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" name="settings[school_address]" class="form-control" value="<?= e($S('school_address')) ?>" placeholder="Street, Thana, District, Bangladesh">
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
              <label class="form-label">Website</label>
              <input type="text" name="settings[school_website]" class="form-control" value="<?= e($S('school_website')) ?>" placeholder="https://…">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h6 class="card-title">Logo & Favicon</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-center">
            <div class="col-md-6">
              <label class="form-label">Institute Logo <small class="text-muted">(JPG/PNG/WebP, max 2MB)</small></label>
              <input type="file" name="school_logo" class="form-control" accept="image/*">
              <?php $logo = $S('school_logo'); if ($logo && file_exists(EMS_ROOT . '/uploads/logos/' . $logo)): ?>
                <div class="mt-2">
                  <img src="../../uploads/logos/<?= e($logo) ?>" height="50" class="rounded border">
                  <small class="text-muted ms-2">Current logo</small>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">Favicon <small class="text-muted">(ICO/PNG, max 2MB) — shown in browser tab</small></label>
              <input type="file" name="school_favicon" class="form-control" accept="image/x-icon,image/png,image/jpeg">
              <?php $fav = $S('school_favicon'); if ($fav && file_exists(EMS_ROOT . '/uploads/logos/' . $fav)): ?>
                <div class="mt-2">
                  <img src="../../uploads/logos/<?= e($fav) ?>" height="32" class="rounded border">
                  <small class="text-muted ms-2">Current favicon</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h6 class="card-title">System Preferences</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Timezone</label>
              <select name="settings[timezone]" class="form-select">
                <?php foreach (['Asia/Dhaka'=>'Asia/Dhaka (BD)','Asia/Calcutta'=>'Asia/Calcutta (IN)','UTC'=>'UTC'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $S('timezone','Asia/Dhaka') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date Format</label>
              <select name="settings[date_format]" class="form-select">
                <?php foreach (['d-m-Y'=>'25-06-2026','d/m/Y'=>'25/06/2026','Y-m-d'=>'2026-06-25','d M Y'=>'25 Jun 2026'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $S('date_format','d-m-Y') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
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

    <!-- ══ ACADEMIC ══════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="tab-academic">
      <div class="card">
        <div class="card-header"><h6 class="card-title">Academic Configuration</h6></div>
        <div class="card-body">
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
              <small class="text-muted"><a href="../academic/sessions.php">Manage Sessions</a></small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Education Board</label>
              <select name="settings[academic_board]" class="form-select">
                <?php foreach (EDUCATION_BOARDS as $k => $v): ?>
                  <option value="<?= $k ?>" <?= $S('academic_board') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Marks Entry Permission</label>
              <select name="settings[academic_marks_entry_level]" class="form-select">
                <option value="all"      <?= $S('academic_marks_entry_level','all') === 'all'      ? 'selected':'' ?>>All teachers can enter marks for any subject</option>
                <option value="assigned" <?= $S('academic_marks_entry_level','all') === 'assigned' ? 'selected':'' ?>>Only teachers assigned in class routine</option>
                <option value="expertise"<?= $S('academic_marks_entry_level','all') === 'expertise'? 'selected':'' ?>>Only teachers with registered subject expertise</option>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label">Working Days
                <small class="text-muted ms-2">Friday is the national weekend in Bangladesh</small>
              </label>
              <div class="d-flex flex-wrap gap-3 mt-1">
                <?php
                $days  = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
                // Default: Sat-Thu (all except Friday)
                $defaultDays = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday'];
                foreach ($days as $day):
                  $checked = in_array($day, $working_arr);
                  $isFriday = ($day === 'Friday');
                ?>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="wd_<?= $day ?>"
                         name="working_days_cb[]" value="<?= $day ?>"
                         <?= $checked ? 'checked' : '' ?>>
                  <label class="form-check-label<?= $isFriday ? ' text-muted' : '' ?>" for="wd_<?= $day ?>">
                    <?= $day ?><?= $isFriday ? ' <small>(Weekend)</small>' : '' ?>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ FINANCE ═══════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="tab-finance">
      <div class="card mb-3">
        <div class="card-header"><h6 class="card-title">Currency & Receipt</h6></div>
        <div class="card-body">
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
      <div class="card mb-3">
        <div class="card-header"><h6 class="card-title">Partial Payment Policy</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Allow Partial Payment</label>
              <select name="settings[allow_partial_payment]" class="form-select" onchange="togglePartialSettings(this.value)">
                <option value="no"  <?= $S('allow_partial_payment','no') === 'no'  ? 'selected':'' ?>>No — Full amount required</option>
                <option value="yes" <?= $S('allow_partial_payment','no') === 'yes' ? 'selected':'' ?>>Yes — Allow partial payments</option>
              </select>
            </div>
            <div class="col-md-4" id="partial-min-wrap" <?= $S('allow_partial_payment','no')==='no'?'style="display:none"':'' ?>>
              <label class="form-label">Minimum Payment %</label>
              <div class="input-group">
                <input type="number" name="settings[partial_min_percent]" class="form-control"
                       value="<?= e($S('partial_min_percent','100')) ?>" min="1" max="100">
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-md-4" id="partial-approval-wrap" <?= $S('allow_partial_payment','no')==='no'?'style="display:none"':'' ?>>
              <label class="form-label">Require Approval for Partial</label>
              <select name="settings[partial_requires_approval]" class="form-select">
                <option value="yes" <?= $S('partial_requires_approval','yes') === 'yes' ? 'selected':'' ?>>Yes — Hold pending approval</option>
                <option value="no"  <?= $S('partial_requires_approval','yes') === 'no'  ? 'selected':'' ?>>No — Apply immediately</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h6 class="card-title">Dues Controls</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Admit Cards with Dues</label>
              <select name="settings[admit_card_dues_allow]" class="form-select">
                <option value="1" <?= $S('admit_card_dues_allow','1')==='1'?'selected':'' ?>>Allow Generation</option>
                <option value="0" <?= $S('admit_card_dues_allow','1')==='0'?'selected':'' ?>>Block Generation</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Result Cards with Dues</label>
              <select name="settings[result_card_dues_allow]" class="form-select">
                <option value="1" <?= $S('result_card_dues_allow','1')==='1'?'selected':'' ?>>Allow Generation</option>
                <option value="0" <?= $S('result_card_dues_allow','1')==='0'?'selected':'' ?>>Block Generation</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Certificates with Dues</label>
              <select name="settings[certificate_dues_allow]" class="form-select">
                <option value="1" <?= $S('certificate_dues_allow','1')==='1'?'selected':'' ?>>Allow Issue</option>
                <option value="0" <?= $S('certificate_dues_allow','1')==='0'?'selected':'' ?>>Block / Prevent Issue</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ SMS ═══════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="tab-sms">

      <!-- Gateway config -->
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h6 class="card-title mb-0">SMS Gateway</h6>
          <span class="badge bg-success">sms.net.bd (Default)</span>
        </div>
        <div class="card-body">
          <div class="alert alert-info d-flex gap-2 align-items-start">
            <i class="bi bi-info-circle-fill mt-1"></i>
            <div>
              SMS is sent via <strong>sms.net.bd</strong> even when the toggle is OFF — logs are always saved
              and you can resend from the SMS Delivery Log page.
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">SMS Enabled</label>
              <select name="settings[sms_enabled]" class="form-select">
                <option value="1" <?= $S('sms_enabled','1') === '1' ? 'selected' : '' ?>>Yes — Send SMS actively</option>
                <option value="0" <?= $S('sms_enabled','1') === '0' ? 'selected' : '' ?>>No — Log only (no actual sending)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Gateway Provider</label>
              <select name="settings[sms_gateway]" class="form-select">
                <option value="smsnetbd" <?= $S('sms_gateway','smsnetbd') === 'smsnetbd' ? 'selected' : '' ?>>sms.net.bd (Recommended)</option>
                <option value="greenweb" <?= $S('sms_gateway','smsnetbd') === 'greenweb' ? 'selected' : '' ?>>Greenweb BD</option>
                <option value="ssl"      <?= $S('sms_gateway','smsnetbd') === 'ssl'      ? 'selected' : '' ?>>SSLWireless</option>
                <option value="teletalk" <?= $S('sms_gateway','smsnetbd') === 'teletalk' ? 'selected' : '' ?>>Teletalk</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">API Key</label>
              <input type="text" name="settings[sms_api_key]" class="form-control font-monospace"
                     value="<?= e($S('sms_api_key','64v0VK2aq7AddQlE40Oh4T4oXpkgL3VBXxlc4W6l')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Sender ID <small class="text-muted">(optional, if approved)</small></label>
              <input type="text" name="settings[sms_sender_id]" class="form-control" value="<?= e($S('sms_sender_id')) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Management copy numbers -->
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h6 class="card-title mb-0">Management Copy Numbers</h6>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addMgmtRow()">
            <i class="bi bi-plus-lg me-1"></i>Add Number
          </button>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">
            These numbers always receive a copy of every SMS sent, regardless of the send_to setting.
          </p>
          <div id="mgmt-numbers-list">
            <?php if (empty($managementNums)): ?>
            <div class="mgmt-row row g-2 mb-2" id="mgmt-0">
              <div class="col-md-5"><input type="text" name="sms_mgmt_names[]" class="form-control" placeholder="Name (e.g. Principal)"></div>
              <div class="col-md-5"><input type="text" name="sms_mgmt_phones[]" class="form-control" placeholder="01XXXXXXXXX"></div>
              <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.mgmt-row').remove()"><i class="bi bi-trash"></i></button></div>
            </div>
            <?php else: ?>
            <?php foreach ($managementNums as $i => $mn): ?>
            <div class="mgmt-row row g-2 mb-2">
              <div class="col-md-5"><input type="text" name="sms_mgmt_names[]" class="form-control" value="<?= e($mn['name']) ?>" placeholder="Name"></div>
              <div class="col-md-5"><input type="text" name="sms_mgmt_phones[]" class="form-control" value="<?= e($mn['phone']) ?>" placeholder="01XXXXXXXXX"></div>
              <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.mgmt-row').remove()"><i class="bi bi-trash"></i></button></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- SMS feature toggles -->
      <div class="card">
        <div class="card-header"><h6 class="card-title mb-0">SMS Feature Settings</h6></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>Feature</th>
                  <th class="text-center" style="width:90px;">Enabled</th>
                  <th style="width:220px;">Send To</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($smsFeatures as $sf): ?>
                <tr>
                  <td>
                    <div class="fw-600"><?= e($sf['feature_label']) ?></div>
                    <small class="text-muted font-monospace"><?= e($sf['feature_key']) ?></small>
                  </td>
                  <td class="text-center">
                    <div class="form-check d-flex justify-content-center">
                      <input type="checkbox" class="form-check-input"
                             name="sms_features[<?= e($sf['feature_key']) ?>][enabled]"
                             value="1" <?= $sf['is_enabled'] ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td>
                    <select name="sms_features[<?= e($sf['feature_key']) ?>][send_to]" class="form-select form-select-sm">
                      <?php foreach (['affected_only'=>'Affected Person Only','management_only'=>'Management Only','both'=>'Both','none'=>'None (log only)'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $sf['send_to'] === $k ? 'selected':'' ?>><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ DESIGNATIONS ══════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="tab-desig">
      <div class="card">
        <div class="card-header"><h6 class="card-title">Designation Types</h6></div>
        <div class="card-body">
          <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            Designations defined here appear in staff profiles and on official documents.
            Add, edit or remove from <strong>Dropdown Options</strong> in the Setup menu.
          </div>
          <div class="row g-2">
            <?php
            $allDesigs = $pdo->query('SELECT id, designation_name, designation_role, display_order, status FROM designation_types ORDER BY display_order')->fetchAll();
            $roleColors = ['head'=>'danger','academic'=>'primary','admin'=>'warning','support'=>'secondary','other'=>'light text-dark'];
            foreach ($allDesigs as $d):
              $rc = $roleColors[$d['designation_role']] ?? 'light text-dark';
            ?>
            <div class="col-md-4 col-6">
              <div class="d-flex align-items-center gap-2 border rounded p-2">
                <span class="badge bg-<?= $rc ?>"><?= e(ucfirst($d['designation_role'])) ?></span>
                <span class="fw-600 small"><?= e($d['designation_name']) ?></span>
                <?php if (!$d['status']): ?><span class="badge bg-secondary ms-auto">Off</span><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-3">
            <a href="categories.php#tab-designation" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-pencil me-1"></i>Manage Designations
            </a>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->

  <?php if (has_permission('setup.edit')): ?>
  <div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary px-4">
      <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
    <a href="../../dashboard.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
  <?php endif; ?>
</form>

<script>
// Collect working day checkboxes into hidden input
document.getElementById('settingsForm').addEventListener('submit', function() {
  const cbs  = document.querySelectorAll('[name="working_days_cb[]"]:checked');
  const vals = Array.from(cbs).map(c => c.value).join(',');
  const h    = document.createElement('input');
  h.type = 'hidden'; h.name = 'settings[working_days]'; h.value = vals;
  this.appendChild(h);
});

function togglePartialSettings(val) {
  ['partial-min-wrap','partial-approval-wrap'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = val === 'yes' ? '' : 'none';
  });
}

let mgmtIdx = <?= count($managementNums) ?: 1 ?>;
function addMgmtRow() {
  const div = document.createElement('div');
  div.className = 'mgmt-row row g-2 mb-2';
  div.innerHTML = `
    <div class="col-md-5"><input type="text" name="sms_mgmt_names[]" class="form-control" placeholder="Name"></div>
    <div class="col-md-5"><input type="text" name="sms_mgmt_phones[]" class="form-control" placeholder="01XXXXXXXXX"></div>
    <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.mgmt-row').remove()"><i class="bi bi-trash"></i></button></div>
  `;
  document.getElementById('mgmt-numbers-list').appendChild(div);
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
