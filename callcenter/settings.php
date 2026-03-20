<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Settings';
$activePage = 'settings';
$aid        = agentId();
$canEdit    = in_array(strtolower(trim($_SESSION['department'] ?? '')), ['it', 'management']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!$canEdit) { header('Location: settings.php?error=unauthorized'); exit; }
    $keys = ['company_name','company_phone','timezone','calls_per_page','missed_alert_enabled','auto_create_contact','recording_proxy'];
    foreach ($keys as $k) {
        $v    = $conn->real_escape_string(trim($_POST[$k] ?? ''));
        $old  = getSetting($k);
        $conn->query("UPDATE settings SET setting_value='$v', updated_by=$aid WHERE setting_key='$k'");
        if ($old !== $v) logEdit('settings', 0, $k, $old, $v);
    }
    logActivity('settings_updated', 'system', null, "App settings updated");
    header('Location: settings.php?saved=1');
    exit;
}

$s = [];
$r = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $r->fetch_assoc()) $s[$row['setting_key']] = $row['setting_value'];

require_once 'includes/layout.php';
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success py-2 mb-3"><i class="fas fa-check-circle me-2"></i>Settings saved.</div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-danger py-2 mb-3"><i class="fas fa-lock me-2"></i>Only IT and Management can change settings.</div>
<?php endif; ?>

<?php if (!$canEdit): ?>
<div class="alert mb-3" style="background:var(--card2);border:1px solid var(--border);color:var(--muted)">
    <i class="fas fa-lock me-2"></i>View-only — only IT and Management can modify settings.
</div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-8">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-gear me-2"></i>App Settings</span></div>
        <form method="POST">
            <div class="cc-card-body">
                <input type="hidden" name="save_settings" value="1">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?= e($s['company_name'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Company Phone</label>
                        <input type="text" name="company_phone" class="form-control" value="<?= e($s['company_phone'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select" <?= $canEdit?'':'disabled' ?>>
                            <?php foreach (['Asia/Dhaka','Asia/Kolkata','UTC','Asia/Dubai','Asia/Singapore'] as $tz): ?>
                            <option value="<?= $tz ?>" <?= ($s['timezone']??'')===$tz?'selected':'' ?>><?= $tz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Calls per Page</label>
                        <select name="calls_per_page" class="form-select" <?= $canEdit?'':'disabled' ?>>
                            <?php foreach ([25,50,100,200] as $n): ?>
                            <option value="<?= $n ?>" <?= ($s['calls_per_page']??50)==$n?'selected':'' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Missed Call Alert</label>
                        <select name="missed_alert_enabled" class="form-select" <?= $canEdit?'':'disabled' ?>>
                            <option value="1" <?= ($s['missed_alert_enabled']??'1')==='1'?'selected':'' ?>>Enabled</option>
                            <option value="0" <?= ($s['missed_alert_enabled']??'1')==='0'?'selected':'' ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Auto-create Contacts</label>
                        <select name="auto_create_contact" class="form-select" <?= $canEdit?'':'disabled' ?>>
                            <option value="1" <?= ($s['auto_create_contact']??'1')==='1'?'selected':'' ?>>Yes</option>
                            <option value="0" <?= ($s['auto_create_contact']??'1')==='0'?'selected':'' ?>>No</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Proxy Recordings</label>
                        <select name="recording_proxy" class="form-select" <?= $canEdit?'':'disabled' ?>>
                            <option value="1" <?= ($s['recording_proxy']??'1')==='1'?'selected':'' ?>>Yes (through app)</option>
                            <option value="0" <?= ($s['recording_proxy']??'1')==='0'?'selected':'' ?>>No (direct URL)</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php if ($canEdit): ?>
            <div class="cc-card-foot">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Save Settings
                    <span class="text-muted small ms-1">(by <?= e(currentAgent()['full_name']) ?>)</span>
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="col-lg-4">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-info-circle me-2"></i>System Info</span></div>
        <div class="cc-card-body">
            <div class="detail-grid">
                <div class="detail-item"><div class="detail-label">App Version</div><div class="detail-value"><?= APP_VERSION ?></div></div>
                <div class="detail-item"><div class="detail-label">PHP Version</div><div class="detail-value"><?= PHP_VERSION ?></div></div>
                <div class="detail-item"><div class="detail-label">Database</div><div class="detail-value"><?= DB_NAME ?></div></div>
                <div class="detail-item"><div class="detail-label">You</div><div class="detail-value"><?= e(currentAgent()['full_name']) ?></div></div>
            </div>
            <div class="mt-3">
                <a href="<?= APP_URL ?>/fetch.php" class="btn btn-outline-primary btn-sm w-100 mb-2">
                    <i class="fas fa-server me-1"></i>PBX Settings
                </a>
                <a href="<?= APP_URL ?>/agents.php" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-users me-1"></i>Manage Agents
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
