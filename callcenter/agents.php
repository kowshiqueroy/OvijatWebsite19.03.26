<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Agents';
$activePage = 'agents';
$aid        = agentId();
$viewId     = (int)($_GET['view'] ?? 0) ?: $aid;

// ── Handle save ───────────────────────────────────────────────────────────────
$saveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_profile'])) {
        $targetId  = (int)$_POST['target_id'];
        $fullName  = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
        $email     = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $dept      = $conn->real_escape_string(trim($_POST['department'] ?? ''));

        $old = $conn->query("SELECT * FROM agents WHERE id=$targetId LIMIT 1")->fetch_assoc();
        $conn->query("UPDATE agents SET full_name='$fullName',email='$email',department='$dept' WHERE id=$targetId");
        foreach (['full_name','email','department'] as $f) {
            if ($old[$f] !== $$f) logEdit('agents', $targetId, $f, $old[$f], $$f ?? '');
        }
        logActivity('agent_profile_updated', 'agents', $targetId, "Profile updated for #$targetId by agent #$aid");

        // Password change
        if (!empty($_POST['new_password'])) {
            $np = $_POST['new_password'];
            if (strlen($np) >= 6) {
                $hash = password_hash($np, PASSWORD_DEFAULT);
                $conn->query("UPDATE agents SET password='$hash' WHERE id=$targetId");
                logActivity('agent_password_changed', 'agents', $targetId, "Password changed by #$aid");
            }
        }
        $saveMsg = 'Profile updated.';
    }

    if (isset($_POST['save_number'])) {
        $agentIdNum  = (int)$_POST['agent_id_num'];
        $numType     = $conn->real_escape_string($_POST['num_type'] ?? 'extension');
        $num         = $conn->real_escape_string(normalizePhone($_POST['number'] ?? ''));
        $label       = $conn->real_escape_string(trim($_POST['label'] ?? ''));
        $isPrimary   = (int)($_POST['is_primary'] ?? 0);

        if ($num) {
            if ($isPrimary) $conn->query("UPDATE agent_numbers SET is_primary=0 WHERE agent_id=$agentIdNum");
            $conn->query("INSERT INTO agent_numbers (agent_id,number_type,number,label,is_primary,created_by)
                          VALUES ($agentIdNum,'$numType','$num','$label',$isPrimary,$aid)");
            logActivity('agent_number_added', 'agents', $agentIdNum, "Number: $num ($numType)");
        }
        $saveMsg = 'Number added.';
    }

    if (isset($_POST['delete_number'])) {
        $numId = (int)$_POST['num_id'];
        $conn->query("DELETE FROM agent_numbers WHERE id=$numId");
        logActivity('agent_number_deleted', 'agents', $viewId, "Number #$numId deleted by #$aid");
        $saveMsg = 'Number removed.';
    }

    if (isset($_POST['new_agent'])) {
        $un  = $conn->real_escape_string(trim($_POST['new_username'] ?? ''));
        $fn  = $conn->real_escape_string(trim($_POST['new_fullname'] ?? ''));
        $pw  = $_POST['new_password'] ?? '';
        $dep = $conn->real_escape_string(trim($_POST['new_dept'] ?? ''));
        if ($un && $fn && strlen($pw) >= 6) {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO agents (username,password,full_name,department,status,created_by)
                          VALUES ('$un','$hash','$fn','$dep','active',$aid)");
            $newId = $conn->insert_id;
            logActivity('agent_created', 'agents', $newId, "Agent created: $fn by #$aid");
            $saveMsg = "Agent $fn created.";
        } else {
            $saveMsg = 'Error: Username, full name, and 6+ char password required.';
        }
    }

    header("Location: agents.php?view=$viewId&saved=1");
    exit;
}

$agent = $conn->query("SELECT * FROM agents WHERE id=$viewId LIMIT 1")->fetch_assoc();
$numbers = $conn->query("SELECT * FROM agent_numbers WHERE agent_id=$viewId ORDER BY is_primary DESC, created_at ASC");

$agentStats = [
    'calls_total'   => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE agent_id=$viewId")->fetch_assoc()['c'],
    'calls_today'   => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE agent_id=$viewId AND DATE(calldate)=CURDATE()")->fetch_assoc()['c'],
    'tasks_done'    => (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$viewId AND status='done'")->fetch_assoc()['c'],
    'tasks_pending' => (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$viewId AND status IN ('pending','in_progress')")->fetch_assoc()['c'],
    'notes_written' => (int)$conn->query("SELECT COUNT(*) AS c FROM call_notes WHERE agent_id=$viewId")->fetch_assoc()['c'],
];

$allAgents   = getAllAgents();
$recentActs  = $conn->query("SELECT * FROM activity_log WHERE agent_id=$viewId ORDER BY created_at DESC LIMIT 20");

require_once 'includes/layout.php';
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success py-2 mb-3"><i class="fas fa-check-circle me-2"></i>Saved.</div>
<?php endif; ?>

<div class="row g-4">

<!-- Agent list sidebar -->
<div class="col-lg-3">
    <div class="cc-card mb-3">
        <div class="cc-card-head"><span>All Agents</span></div>
        <div class="cc-card-body p-0">
            <?php foreach ($allAgents as $ag): ?>
            <a href="?view=<?= $ag['id'] ?>" class="quick-item <?= $ag['id']==$viewId?'todo-active':'' ?>" style="text-decoration:none">
                <div class="agent-avatar-sm"><i class="fas fa-user"></i></div>
                <div class="qi-body">
                    <div class="qi-title"><?= e($ag['full_name']) ?></div>
                    <div class="qi-sub"><?= e($ag['department'] ?: $ag['username']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="cc-card-foot">
            <button class="btn btn-primary btn-sm w-100" onclick="openNewAgent()">
                <i class="fas fa-user-plus me-1"></i>New Agent
            </button>
        </div>
    </div>
</div>

<!-- Agent profile -->
<div class="col-lg-9">
    <?php if ($agent): ?>

    <!-- Stats -->
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr))">
        <div class="stat-card"><div class="stat-icon bg-primary"><i class="fas fa-phone-volume"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $agentStats['calls_total'] ?></div><div class="stat-label">Total Calls</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-info"><i class="fas fa-phone"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $agentStats['calls_today'] ?></div><div class="stat-label">Today</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-success"><i class="fas fa-check"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $agentStats['tasks_done'] ?></div><div class="stat-label">Tasks Done</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-warning"><i class="fas fa-list-check"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $agentStats['tasks_pending'] ?></div><div class="stat-label">Pending Tasks</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-secondary"><i class="fas fa-comment"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $agentStats['notes_written'] ?></div><div class="stat-label">Notes Written</div></div></div>
    </div>

    <div class="row g-4">
    <div class="col-md-6">
        <!-- Profile form -->
        <div class="cc-card mb-4">
            <div class="cc-card-head"><span><i class="fas fa-user me-2"></i>Profile</span></div>
            <form method="POST" action="agents.php?view=<?= $viewId ?>">
                <div class="cc-card-body">
                    <input type="hidden" name="save_profile" value="1">
                    <input type="hidden" name="target_id" value="<?= $viewId ?>">
                    <div class="text-center mb-3">
                        <div class="contact-avatar-xl mx-auto"><i class="fas fa-user-tie fa-lg"></i></div>
                        <div class="fw-bold mt-2"><?= e($agent['username']) ?></div>
                        <span class="badge bg-<?= ($agent['status']??'active')==='active'?'success':'secondary' ?>"><?= $agent['status'] ?? 'active' ?></span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Full Name</label>
                        <input type="text" name="full_name" class="form-control form-control-sm" value="<?= e($agent['full_name']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="<?= e($agent['email'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Department</label>
                        <input type="text" name="department" class="form-control form-control-sm" value="<?= e($agent['department'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">New Password (leave blank to keep)</label>
                        <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Min 6 characters">
                    </div>
                    <div class="text-muted small mb-2">
                        Last login: <?= !empty($agent['last_login']) ? formatDt($agent['last_login']) : 'Never' ?>
                    </div>
                </div>
                <div class="cc-card-foot">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i>Save Profile
                        <span class="text-muted small ms-1">(by <?= e(currentAgent()['full_name']) ?>)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <!-- Extensions & numbers -->
        <div class="cc-card mb-4">
            <div class="cc-card-head"><span><i class="fas fa-hashtag me-2"></i>Extensions &amp; Numbers</span></div>
            <div class="cc-card-body p-0">
                <?php if ($numbers->num_rows): while ($n = $numbers->fetch_assoc()): ?>
                <div class="quick-item">
                    <div class="qi-icon <?= $n['is_primary']?'text-success':'text-muted' ?>">
                        <i class="fas fa-<?= $n['number_type']==='extension'?'phone-office':($n['number_type']==='mobile'?'mobile-screen':'phone') ?>"></i>
                    </div>
                    <div class="qi-body">
                        <div class="qi-title font-monospace"><?= e($n['number']) ?>
                            <?php if ($n['is_primary']): ?><span class="badge bg-success ms-1">Primary</span><?php endif; ?>
                        </div>
                        <div class="qi-sub"><?= ucwords(str_replace('_',' ',$n['number_type'])) ?>
                            <?php if ($n['label']): ?> &bull; <?= e($n['label']) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="empty-state py-3">No numbers added</div>
                <?php endif; ?>
            </div>
            <div class="cc-card-foot">
                <form method="POST" class="row g-2">
                    <input type="hidden" name="save_number" value="1">
                    <input type="hidden" name="agent_id_num" value="<?= $viewId ?>">
                    <div class="col-5">
                        <input type="text" name="number" class="form-control form-control-sm" placeholder="Number / Ext">
                    </div>
                    <div class="col-4">
                        <select name="num_type" class="form-select form-select-sm">
                            <option value="extension">Extension</option>
                            <option value="mobile">Mobile</option>
                            <option value="direct_line">Direct Line</option>
                            <option value="official">Official</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="col-8">
                        <input type="text" name="label" class="form-control form-control-sm" placeholder="Label (e.g. Sales DID)">
                    </div>
                    <div class="col-4 d-flex align-items-center">
                        <div class="form-check">
                            <input type="checkbox" name="is_primary" class="form-check-input" value="1">
                            <label class="form-check-label small">Primary</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Recent activity -->
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-clock-rotate-left me-2"></i>Activity Log</span></div>
        <div class="cc-card-body p-0">
            <?php while ($a = $recentActs->fetch_assoc()): ?>
            <div class="quick-item">
                <div class="qi-icon text-muted" style="font-size:.4rem"><i class="fas fa-circle"></i></div>
                <div class="qi-body">
                    <div class="qi-title"><?= e(ucwords(str_replace('_',' ',$a['action']))) ?></div>
                    <div class="qi-sub"><?= e(mb_strimwidth($a['details']??'',0,80,'…')) ?> &bull; <?= timeAgo($a['created_at']) ?> &bull; IP: <?= e($a['ip_address']??'') ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="empty-state py-5"><i class="fas fa-user-slash fa-2x mb-2 d-block"></i>Agent not found</div>
    <?php endif; ?>
</div>
</div>

<!-- New Agent Modal -->
<div class="modal fade" id="newAgentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>New Agent</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="new_agent" value="1">
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Username</label>
                        <input type="text" name="new_username" class="form-control" required pattern="[a-zA-Z0-9_]{3,30}"></div>
                    <div class="mb-2"><label class="form-label small">Full Name</label>
                        <input type="text" name="new_fullname" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label small">Department</label>
                        <input type="text" name="new_dept" class="form-control" placeholder="Sales, Support…"></div>
                    <div class="mb-2"><label class="form-label small">Password (min 6)</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6"></div>
                    <div class="text-muted small">Created by: <strong><?= e(currentAgent()['full_name']) ?></strong></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Create Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewAgent() {
    new bootstrap.Modal(document.getElementById('newAgentModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
