<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Fetch PBX';
$activePage = 'fetch';
$aid        = agentId();

// ── Save credentials ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_creds'])) {
    $u = $conn->real_escape_string(trim($_POST['pbx_username'] ?? ''));
    $p = $conn->real_escape_string(trim($_POST['pbx_password'] ?? ''));
    if ($u && $p) {
        $conn->query("INSERT INTO settings (setting_key,setting_value,updated_by) VALUES
            ('pbx_username','$u',$aid),('pbx_password','$p',$aid)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=$aid");
        // Upsert pbx_settings row (needed for fetch_batches FK)
        $h = 'https://ovijatgroup.pbx.com.bd';
        $r = $conn->query("SELECT id FROM pbx_settings WHERE pbx_host='$h' LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) {
            $conn->query("UPDATE pbx_settings SET db_username='$u',db_password='$p',updated_by=$aid WHERE id={$row['id']}");
        } else {
            $conn->query("INSERT INTO pbx_settings (name,pbx_host,db_host,db_username,db_password,created_by)
                          VALUES ('Ovijat PBX','$h','localhost','$u','$p',$aid)");
        }
        logActivity('pbx_credentials_saved', 'pbx_settings', 1, 'PBX credentials updated');
    }
    header('Location: fetch.php?saved=1');
    exit;
}

$pbxUsername = getSetting('pbx_username');
$pbxPassword = getSetting('pbx_password');
$hasCreds    = !empty($pbxUsername) && !empty($pbxPassword);

$fetchHistory = $conn->query(
    "SELECT fb.*, a.full_name
     FROM fetch_batches fb
     JOIN agents a ON a.id = fb.fetched_by
     ORDER BY fb.started_at DESC LIMIT 20"
);

require_once 'includes/layout.php';
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success mb-3 py-2"><i class="fas fa-check-circle me-2"></i>PBX credentials saved.</div>
<?php endif; ?>

<div class="row g-4">

<!-- ── Left: Fetch panel ───────────────────────────────────────────────────── -->
<div class="col-lg-7">

    <!-- Credentials card -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-key me-2"></i>PBX Credentials</span>
            <?php if ($hasCreds): ?>
            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Configured</span>
            <?php endif; ?>
        </div>
        <form method="POST">
            <div class="cc-card-body">
                <input type="hidden" name="save_creds" value="1">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="pbx_username" class="form-control"
                               value="<?= e($pbxUsername) ?>" placeholder="PBX login username" autocomplete="off" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="pbx_password" class="form-control"
                               value="<?= $pbxPassword ? str_repeat('•', 10) : '' ?>"
                               placeholder="PBX login password" autocomplete="off"  required>
                        <div class="form-text text-muted small">Leave unchanged to keep current password.</div>
                    </div>
                </div>
                <div class="mt-2 text-muted small">
                    <i class="fas fa-server me-1"></i>Connects to <strong>ovijatgroup.pbx.com.bd</strong> &nbsp;·&nbsp;
                    Saved by: <strong><?= e(currentAgent()['full_name']) ?></strong>
                </div>
            </div>
            <div class="cc-card-foot d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save me-1"></i>Save Credentials
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="testConnection()">
                    <i class="fas fa-plug me-1"></i>Test Connection
                </button>
            </div>
        </form>
    </div>

    <!-- Fetch panel -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-cloud-arrow-down me-2"></i>Fetch CDR from PBX</span>
        </div>
        <div class="cc-card-body">
            <?php if (!$hasCreds): ?>
            <div class="alert alert-warning mb-0">
                <i class="fas fa-triangle-exclamation me-2"></i>
                Save PBX credentials above before fetching.
            </div>
            <?php else: ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-5">
                    <label class="form-label">From Date</label>
                    <input type="date" id="fetchFrom" class="form-control"
                           value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                </div>
                <div class="col-sm-5">
                    <label class="form-label">To Date</label>
                    <input type="date" id="fetchTo" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-sm-2">
                    <label class="form-label">Limit</label>
                    <select id="fetchLimit" class="form-select">
                        <option value="1000">1 K</option>
                        <option value="5000" selected>5 K</option>
                        <option value="10000">10 K</option>
                        <option value="20000">20 K</option>
                    </select>
                </div>
            </div>

            <div id="fetchProgress" style="display:none" class="mb-3">
                <div class="progress mb-2" style="height:8px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:100%"></div>
                </div>
                <div id="fetchStatus" class="text-muted small text-center">Connecting to PBX…</div>
            </div>
            <div id="fetchResult" style="display:none" class="mb-3"></div>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-lg" id="fetchBtn" onclick="startFetch()">
                    <i class="fas fa-cloud-arrow-down me-1"></i>Fetch Now
                </button>
                <button class="btn btn-outline-secondary" onclick="redetectDirections()" title="Re-guess direction for all existing unknown-direction calls">
                    <i class="fas fa-compass me-1"></i>Fix Unknown Directions
                </button>
            </div>
            <div class="mt-2 text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                Duplicate calls are automatically skipped. Contacts auto-created from new numbers.
                Every fetch is logged with your identity.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fetch history -->
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-history me-2"></i>Fetch History</span></div>
        <div class="table-responsive">
            <table class="table cc-table mb-0">
                <thead>
                    <tr><th>When</th><th>By</th><th>Range</th><th>Total</th><th>New</th><th>Dupes</th><th>Contacts</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if (!$fetchHistory || !$fetchHistory->num_rows): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No fetch history yet</td></tr>
                <?php endif; ?>
                <?php while ($fh = $fetchHistory->fetch_assoc()): ?>
                <tr>
                    <td class="small"><?= timeAgo($fh['started_at']) ?></td>
                    <td class="small"><?= e($fh['full_name']) ?></td>
                    <td class="small"><?= e($fh['date_from']) ?> – <?= e($fh['date_to']) ?></td>
                    <td><?= $fh['total_fetched'] ?></td>
                    <td class="text-success fw-bold"><?= $fh['new_records'] ?></td>
                    <td class="text-warning"><?= $fh['duplicates_skipped'] ?></td>
                    <td class="text-info"><?= $fh['contacts_created'] ?></td>
                    <td>
                        <span class="badge bg-<?= $fh['status']==='completed'?'success':($fh['status']==='failed'?'danger':'warning') ?>">
                            <?= $fh['status'] ?>
                        </span>
                        <?php if ($fh['error_message']): ?>
                        <i class="fas fa-circle-exclamation text-danger ms-1"
                           title="<?= e($fh['error_message']) ?>"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Right: Info panel ───────────────────────────────────────────────────── -->
<div class="col-lg-5">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-circle-info me-2"></i>About PBX Fetch</span></div>
        <div class="cc-card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">PBX Host</div>
                    <div class="detail-value small">ovijatgroup.pbx.com.bd</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">CDR Source</div>
                    <div class="detail-value small">FreePBX Web / XML CDR</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Deduplication</div>
                    <div class="detail-value small">By call hash (caller + dest + time)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Recordings</div>
                    <div class="detail-value small">Direct URL from PBX (streamed via app)</div>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <i class="fas fa-shield-halved me-1"></i>
                Credentials are stored securely. All fetch operations are logged with agent identity and timestamp.
            </div>
        </div>
    </div>
</div>

</div>

<script>
const APP_URL = '<?= APP_URL ?>';

async function redetectDirections() {
    showToast('Re-detecting directions…', 'info');
    try {
        const r = await fetch(APP_URL + '/api/fetch.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'redetect'})
        }).then(r => r.json());
        showToast(r.ok ? `Fixed ${r.fixed} records` : (r.error || 'Error'), r.ok ? 'success' : 'danger');
    } catch(e) { showToast('Error: ' + e.message, 'danger'); }
}

async function testConnection() {
    showToast('Testing connection…', 'info');
    try {
        const r = await fetch(APP_URL + '/api/fetch.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'test'})
        }).then(r => r.json());
        showToast(r.message || r.error, r.ok ? 'success' : 'danger');
    } catch(e) {
        showToast('Network error: ' + e.message, 'danger');
    }
}

async function startFetch() {
    const from  = document.getElementById('fetchFrom').value;
    const to    = document.getElementById('fetchTo').value;
    const limit = parseInt(document.getElementById('fetchLimit').value);
    if (!from || !to) { showToast('Select date range', 'warning'); return; }

    document.getElementById('fetchBtn').disabled = true;
    document.getElementById('fetchProgress').style.display = '';
    document.getElementById('fetchResult').style.display = 'none';
    document.getElementById('fetchStatus').textContent = 'Connecting to PBX…';

    try {
        const r = await fetch(APP_URL + '/api/fetch.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'run', date_from: from, date_to: to, limit})
        }).then(r => r.json());

        document.getElementById('fetchProgress').style.display = 'none';
        const el = document.getElementById('fetchResult');
        el.style.display = '';
        if (r.ok) {
            el.innerHTML = `
                <div class="alert alert-success">
                    <strong><i class="fas fa-check-circle me-2"></i>Fetch Complete!</strong>
                    <div class="row mt-2 text-center">
                        <div class="col"><div class="fw-bold fs-4">${r.total_fetched}</div><div class="small">Total Read</div></div>
                        <div class="col"><div class="fw-bold fs-4 text-success">${r.new_records}</div><div class="small">New</div></div>
                        <div class="col"><div class="fw-bold fs-4 text-warning">${r.duplicates_skipped}</div><div class="small">Dupes</div></div>
                        <div class="col"><div class="fw-bold fs-4 text-info">${r.contacts_created}</div><div class="small">Contacts</div></div>
                    </div>
                    <div class="mt-2">
                        <a href="${APP_URL}/calls.php" class="btn btn-sm btn-outline-light me-2">View Calls</a>
                        <a href="${APP_URL}/contacts.php" class="btn btn-sm btn-outline-light">View Contacts</a>
                    </div>
                </div>`;
            showToast('Fetch complete: ' + r.new_records + ' new records', 'success');
            setTimeout(() => location.reload(), 4000);
        } else {
            el.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${escHtml(r.error || 'Fetch failed')}</div>`;
            showToast('Fetch failed: ' + r.error, 'danger');
        }
    } catch(e) {
        document.getElementById('fetchProgress').style.display = 'none';
        showToast('Network error: ' + e.message, 'danger');
    }

    document.getElementById('fetchBtn').disabled = false;
}
</script>

<?php require_once 'includes/footer.php'; ?>
