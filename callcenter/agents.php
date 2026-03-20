<?php
require_once 'config.php';
requireLogin();

$aid         = agentId();
$currentDept = strtolower(trim($_SESSION['department'] ?? ''));
$canEdit     = in_array($currentDept, ['it', 'management']);

$pageTitle  = 'Agents';
$activePage = 'agents';

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'agent') {
        $id = (int)($_GET['id'] ?? 0);
        $r  = $conn->query("SELECT * FROM agents WHERE id=$id LIMIT 1");
        if (!$r || !$r->num_rows) { http_response_code(404); exit; }
        $agent = $r->fetch_assoc();
        $nr    = $conn->query("SELECT * FROM agent_numbers WHERE agent_id=$id ORDER BY is_primary DESC, created_at ASC");
        echo json_encode(['ok' => true, 'agent' => $agent, 'numbers' => $nr->fetch_all(MYSQLI_ASSOC), 'canEdit' => $canEdit]);
        exit;
    }

    if ($_GET['ajax'] === 'save_agent') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'Permission denied']); exit; }
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($in['agent_id'] ?? $in['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing id']); exit; }

        $fields = [
            'full_name'  => trim($in['full_name']  ?? ''),
            'email'      => trim($in['email']      ?? ''),
            'department' => trim($in['department'] ?? ''),
            'status'     => in_array($in['status'] ?? '', ['active', 'inactive']) ? $in['status'] : 'active',
        ];
        foreach ($fields as $col => $val) {
            $ev = $conn->real_escape_string($val);
            $conn->query("UPDATE agents SET $col='$ev' WHERE id=$id");
        }
        if (!empty($in['new_password']) && strlen($in['new_password']) >= 6) {
            $hash = password_hash($in['new_password'], PASSWORD_DEFAULT);
            $conn->query("UPDATE agents SET password='$hash' WHERE id=$id");
        }
        logActivity('agent_profile_updated', 'agents', $id, "Updated by #$aid");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_GET['ajax'] === 'save_number') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'Permission denied']); exit; }
        $in      = json_decode(file_get_contents('php://input'), true) ?? [];
        $agentId = (int)($in['agent_id'] ?? 0);
        $num     = normalizePhone($in['number'] ?? '');
        $type    = $conn->real_escape_string($in['number_type'] ?? 'extension');
        $label   = $conn->real_escape_string(trim($in['label'] ?? ''));
        $primary = (int)($in['is_primary'] ?? 0);
        if ($num && $agentId) {
            if ($primary) $conn->query("UPDATE agent_numbers SET is_primary=0 WHERE agent_id=$agentId");
            $conn->query("INSERT INTO agent_numbers (agent_id,number_type,number,label,is_primary,created_by)
                          VALUES ($agentId,'$type','$num','$label',$primary,$aid)");
        }
        echo json_encode(['ok' => true, 'id' => $conn->insert_id]);
        exit;
    }

    if ($_GET['ajax'] === 'delete_number') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'Permission denied']); exit; }
        $id = (int)($_GET['id'] ?? 0);
        $conn->query("DELETE FROM agent_numbers WHERE id=$id");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_GET['ajax'] === 'delete_agent') {
        echo json_encode(['ok' => false, 'error' => 'Deleting agents is not allowed']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

// ── Standard POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        header('Location: agents.php?error=unauthorized');
        exit;
    }

    if (isset($_POST['new_agent'])) {
        $un  = $conn->real_escape_string(trim($_POST['new_username'] ?? ''));
        $fn  = $conn->real_escape_string(trim($_POST['new_fullname'] ?? ''));
        $pw  = $_POST['new_password'] ?? '';
        $dep = $conn->real_escape_string(trim($_POST['new_dept'] ?? ''));
        $em  = $conn->real_escape_string(trim($_POST['new_email'] ?? ''));
        if ($un && $fn && strlen($pw) >= 6) {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO agents (username,password,full_name,email,department,status,created_by)
                          VALUES ('$un','$hash','$fn','$em','$dep','active',$aid)");
            $newId = $conn->insert_id;
            logActivity('agent_created', 'agents', $newId, "Created: $fn by #$aid");
            header('Location: agents.php?created=1');
        } else {
            header('Location: agents.php?error=1');
        }
        exit;
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$allAgents = $conn->query(
    "SELECT id, username, full_name, email, department, status
     FROM agents
     WHERE id > 0
     ORDER BY status='active' DESC, full_name ASC"
)->fetch_all(MYSQLI_ASSOC);

$totalActive   = 0;
$totalInactive = 0;
$statsAgents   = [];
foreach ($allAgents as $ag) {
    $id2 = $ag['id'];
    if ($ag['status'] === 'active') $totalActive++; else $totalInactive++;
    $statsAgents[$id2] = [
        'calls_total'   => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE agent_id=$id2")->fetch_assoc()['c'],
        'calls_today'   => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE agent_id=$id2 AND DATE(calldate)=CURDATE()")->fetch_assoc()['c'],
        'tasks_done'    => (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$id2 AND status='done'")->fetch_assoc()['c'],
        'tasks_pending' => (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$id2 AND status IN ('pending','in_progress')")->fetch_assoc()['c'],
    ];
}

require_once 'includes/layout.php';

// Helper: generate initials and a colour from a name
function agentInitials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (!$parts) return '?';
    $i = strtoupper($parts[0][0]);
    if (count($parts) >= 2) $i .= strtoupper(end($parts)[0]);
    return $i;
}
function agentColor(string $name): string {
    $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6','#f97316'];
    return $colors[abs(crc32($name)) % count($colors)];
}
?>

<?php if (!empty($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle me-2"></i>Agent created successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif (!empty($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?= $_GET['error'] === 'unauthorized' ? 'You do not have permission to do that.' : 'Please check the form — username, full name and a password (min 6 chars) are required.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Page header ──────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-semibold"><i class="fas fa-users me-2 text-primary"></i>Agents</h5>
        <div class="text-muted small mt-1"><?= count($allAgents) ?> agents &middot; <?= $totalActive ?> active &middot; <?= $totalInactive ?> inactive</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="input-group input-group-sm" style="width:220px">
            <span class="input-group-text" style="background:var(--card2);border-color:var(--border);color:var(--muted)">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="agentSearch" class="form-control form-control-sm" placeholder="Search agents…"
                   style="background:var(--card2);border-color:var(--border);color:var(--text)">
        </div>
        <?php if ($canEdit): ?>
        <button class="btn btn-primary btn-sm" onclick="openNewAgent()">
            <i class="fas fa-user-plus me-1"></i>New Agent
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$canEdit): ?>
<div class="alert mb-3" style="background:var(--card2);border:1px solid var(--border);color:var(--muted)">
    <i class="fas fa-lock me-2"></i>View-only mode — only IT and Management can add or edit agents.
</div>
<?php endif; ?>

<!-- ── Summary stats ────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <div class="cc-card text-center py-3">
            <div style="font-size:1.6rem;font-weight:700;color:var(--accent)"><?= count($allAgents) ?></div>
            <div class="text-muted small">Total Agents</div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="cc-card text-center py-3">
            <div style="font-size:1.6rem;font-weight:700;color:var(--success)"><?= $totalActive ?></div>
            <div class="text-muted small">Active</div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="cc-card text-center py-3">
            <div style="font-size:1.6rem;font-weight:700;color:var(--muted)"><?= $totalInactive ?></div>
            <div class="text-muted small">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="cc-card text-center py-3">
            <?php $todayCalls = array_sum(array_column($statsAgents, 'calls_today')); ?>
            <div style="font-size:1.6rem;font-weight:700;color:var(--info)"><?= $todayCalls ?></div>
            <div class="text-muted small">Calls Today</div>
        </div>
    </div>
</div>

<!-- ── Agent cards grid ─────────────────────────────────────────────────────── -->
<div class="row g-3" id="agentGrid">
<?php foreach ($allAgents as $ag):
    $initials = agentInitials($ag['full_name']);
    $color    = agentColor($ag['full_name']);
    $stats    = $statsAgents[$ag['id']];
    $isActive = $ag['status'] === 'active';
    $isSelf   = $ag['id'] == $aid;
?>
<div class="col-12 col-sm-6 col-lg-4 col-xl-3 agent-card-wrap"
     data-name="<?= strtolower(e($ag['full_name'])) ?>"
     data-dept="<?= strtolower(e($ag['department'] ?? '')) ?>">
    <div class="cc-card h-100 agent-card-item" style="cursor:pointer" onclick="openAgentModal(<?= $ag['id'] ?>)">
        <!-- status bar -->
        <div style="height:3px;background:<?= $isActive ? 'var(--success)' : 'var(--border2)' ?>;border-radius:8px 8px 0 0"></div>

        <div class="p-3">
            <!-- top row: avatar + actions -->
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:44px;height:44px;border-radius:50%;background:<?= $color ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-weight:700;font-size:.95rem;color:#fff;flex-shrink:0;letter-spacing:.5px">
                        <?= $initials ?>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:.9rem;line-height:1.2">
                            <?= e($ag['full_name']) ?>
                            <?php if ($isSelf): ?>
                            <span class="badge bg-primary ms-1" style="font-size:.6rem;vertical-align:middle">You</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:.75rem">@<?= e($ag['username']) ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>" style="font-size:.65rem">
                        <?= $isActive ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>

            <!-- dept + email -->
            <div class="mb-3">
                <?php if ($ag['department']): ?>
                <div class="small text-muted mb-1">
                    <i class="fas fa-building me-1" style="width:14px"></i><?= e($ag['department']) ?>
                </div>
                <?php endif; ?>
                <?php if ($ag['email']): ?>
                <div class="small text-muted">
                    <i class="fas fa-envelope me-1" style="width:14px"></i><?= e($ag['email']) ?>
                </div>
                <?php endif; ?>
                <?php if (!$ag['department'] && !$ag['email']): ?>
                <div class="small text-muted"><em>No department / email</em></div>
                <?php endif; ?>
            </div>

            <!-- stats row -->
            <div class="d-flex gap-2" style="border-top:1px solid var(--border);padding-top:.6rem;margin-top:.5rem">
                <div class="text-center flex-fill">
                    <div class="fw-bold" style="font-size:.95rem;color:var(--info)"><?= $stats['calls_total'] ?></div>
                    <div class="text-muted" style="font-size:.65rem">Total Calls</div>
                </div>
                <div style="width:1px;background:var(--border)"></div>
                <div class="text-center flex-fill">
                    <div class="fw-bold" style="font-size:.95rem;color:var(--accent)"><?= $stats['calls_today'] ?></div>
                    <div class="text-muted" style="font-size:.65rem">Today</div>
                </div>
                <div style="width:1px;background:var(--border)"></div>
                <div class="text-center flex-fill">
                    <div class="fw-bold" style="font-size:.95rem;color:var(--warning)"><?= $stats['tasks_pending'] ?></div>
                    <div class="text-muted" style="font-size:.65rem">Tasks</div>
                </div>
                <div style="width:1px;background:var(--border)"></div>
                <div class="text-center flex-fill">
                    <div class="fw-bold" style="font-size:.95rem;color:var(--success)"><?= $stats['tasks_done'] ?></div>
                    <div class="text-muted" style="font-size:.65rem">Done</div>
                </div>
            </div>

            <!-- edit button (only for canEdit) -->
            <?php if ($canEdit): ?>
            <button class="btn btn-sm w-100 mt-2"
                    style="background:var(--card2);border:1px solid var(--border);color:var(--muted);font-size:.75rem"
                    onclick="event.stopPropagation();openAgentModal(<?= $ag['id'] ?>)">
                <i class="fas fa-pen me-1"></i>Edit Agent
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- empty state -->
<div id="agentNoResults" class="text-center py-5" style="display:none">
    <i class="fas fa-users-slash fa-2x text-muted mb-2"></i>
    <div class="text-muted">No agents match your search.</div>
</div>

<!-- ── View / Edit Agent Modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="agentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="agentModalTitle"><i class="fas fa-user me-2"></i>Agent Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="agentModalBody">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── New Agent Modal ────────────────────────────────────────────────────────── -->
<?php if ($canEdit): ?>
<div class="modal fade" id="newAgentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>New Agent</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="new_agent" value="1">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Username <span class="text-danger">*</span></label>
                            <input type="text" name="new_username" class="form-control" required
                                   pattern="[a-zA-Z0-9_]{3,30}" autocomplete="off" placeholder="e.g. john_doe">
                            <div class="form-text">3–30 chars, letters/numbers/underscore</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="new_fullname" class="form-control" required placeholder="John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Department</label>
                            <input type="text" name="new_dept" class="form-control"
                                   placeholder="IT, Management, Sales…" list="deptList">
                            <datalist id="deptList">
                                <option value="IT">
                                <option value="Management">
                                <option value="Sales">
                                <option value="Support">
                                <option value="Operations">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Email</label>
                            <input type="email" name="new_email" class="form-control" placeholder="john@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPwField" class="form-control"
                                       required minlength="6" placeholder="Minimum 6 characters" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('newPwField',this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>Create Agent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const APP_URL    = '<?= APP_URL ?>';
const CURRENT_AID = <?= $aid ?>;
const CAN_EDIT   = <?= $canEdit ? 'true' : 'false' ?>;

// ── Search filter ─────────────────────────────────────────────────────────────
document.getElementById('agentSearch').addEventListener('input', function () {
    const q   = this.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.agent-card-wrap');
    let visible = 0;
    cards.forEach(c => {
        const match = !q || c.dataset.name.includes(q) || c.dataset.dept.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('agentNoResults').style.display = visible ? 'none' : '';
});

// ── New Agent modal ───────────────────────────────────────────────────────────
function openNewAgent() {
    if (!CAN_EDIT) return;
    new bootstrap.Modal(document.getElementById('newAgentModal')).show();
}

function togglePw(fieldId, btn) {
    const f = document.getElementById(fieldId);
    const show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}

// ── Agent detail / edit modal ─────────────────────────────────────────────────
function openAgentModal(id) {
    const modal = new bootstrap.Modal(document.getElementById('agentModal'));
    document.getElementById('agentModalTitle').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading…';
    document.getElementById('agentModalBody').innerHTML =
        '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
    modal.show();

    fetch(APP_URL + '/agents.php?ajax=agent&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) {
                document.getElementById('agentModalBody').innerHTML =
                    '<div class="alert alert-danger m-3">Error loading agent.</div>';
                return;
            }
            const a       = d.agent;
            const nums    = d.numbers || [];
            const editable = d.canEdit;
            const isSelf  = a.id == CURRENT_AID;

            // initials + colour (mirrors PHP)
            const parts = (a.full_name || '?').trim().split(/\s+/);
            let ini = parts[0][0].toUpperCase();
            if (parts.length >= 2) ini += parts[parts.length - 1][0].toUpperCase();
            const palette = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6','#f97316'];
            let h = 0;
            for (let c of a.full_name) h = (Math.imul(31, h) + c.charCodeAt(0)) | 0;
            const col = palette[Math.abs(h) % palette.length];

            document.getElementById('agentModalTitle').innerHTML =
                '<i class="fas fa-' + (editable ? 'user-pen' : 'user') + ' me-2"></i>' +
                escHtml(a.full_name);

            const statusBadge = a.status === 'active'
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';

            const numRows = nums.length
                ? nums.map(n => `
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom"
                         style="border-color:var(--border)!important" id="num-row-${n.id}">
                        <div style="width:32px;height:32px;border-radius:8px;background:var(--card2);
                                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas fa-${n.number_type === 'mobile' ? 'mobile-screen' : 'phone'} text-muted" style="font-size:.8rem"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="font-monospace small">${escHtml(n.number)}</span>
                            ${n.label ? '<span class="text-muted small ms-1">· ' + escHtml(n.label) + '</span>' : ''}
                        </div>
                        <span class="badge bg-secondary badge-xs">${escHtml(n.number_type)}</span>
                        ${n.is_primary ? '<span class="badge bg-success badge-xs">Primary</span>' : ''}
                        ${editable ? `<button type="button" class="btn btn-sm text-danger p-0 px-1" onclick="deleteNumber(${n.id})" title="Remove"><i class="fas fa-trash-alt"></i></button>` : ''}
                    </div>`).join('')
                : '<div class="text-muted small py-2">No numbers added yet.</div>';

            const addNumRow = editable ? `
                <div class="row g-2 pt-2">
                    <div class="col-5">
                        <input type="text" id="newNumNumber" class="form-control form-control-sm"
                               placeholder="Number / Extension" style="background:var(--card2);border-color:var(--border);color:var(--text)">
                    </div>
                    <div class="col-3">
                        <select id="newNumType" class="form-select form-select-sm"
                                style="background:var(--card2);border-color:var(--border);color:var(--text)">
                            <option value="extension">Extension</option>
                            <option value="mobile">Mobile</option>
                            <option value="direct_line">Direct</option>
                            <option value="official">Official</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-4 d-flex gap-1">
                        <input type="text" id="newNumLabel" class="form-control form-control-sm"
                               placeholder="Label" style="background:var(--card2);border-color:var(--border);color:var(--text)">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addNumber(${a.id})">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>` : '';

            const footer = editable ? `
                <div class="d-flex justify-content-end align-items-center p-3"
                     style="border-top:1px solid var(--border);background:var(--card2)">
                    <button type="submit" form="editAgentForm" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>` : '';

            document.getElementById('agentModalBody').innerHTML = `
                <!-- profile banner -->
                <div class="p-4 d-flex align-items-center gap-3"
                     style="background:var(--card2);border-bottom:1px solid var(--border)">
                    <div style="width:60px;height:60px;border-radius:50%;background:${col};
                                display:flex;align-items:center;justify-content:center;
                                font-weight:700;font-size:1.2rem;color:#fff;flex-shrink:0">
                        ${ini}
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:1rem">${escHtml(a.full_name)}</div>
                        <div class="text-muted small">@${escHtml(a.username)}</div>
                        <div class="mt-1">${statusBadge}
                            ${a.department ? '<span class="badge ms-1" style="background:var(--border2);color:var(--muted)">' + escHtml(a.department) + '</span>' : ''}
                        </div>
                    </div>
                </div>

                <!-- form body -->
                <form id="editAgentForm" class="p-3">
                    <input type="hidden" name="agent_id" value="${a.id}">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-medium">Full Name</label>
                            <input type="text" name="full_name" class="form-control form-control-sm"
                                   value="${escHtml(a.full_name)}" required ${editable ? '' : 'disabled'}
                                   style="background:var(--card2);border-color:var(--border);color:var(--text)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-medium">Username</label>
                            <input type="text" class="form-control form-control-sm" value="${escHtml(a.username)}"
                                   disabled style="background:var(--card2);border-color:var(--border);color:var(--muted)">
                            <div class="form-text" style="color:var(--muted)">Cannot be changed</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-medium">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm"
                                   value="${escHtml(a.email || '')}" ${editable ? '' : 'disabled'}
                                   style="background:var(--card2);border-color:var(--border);color:var(--text)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-medium">Department</label>
                            <input type="text" name="department" class="form-control form-control-sm"
                                   value="${escHtml(a.department || '')}" ${editable ? '' : 'disabled'}
                                   placeholder="IT, Management…" list="deptListModal"
                                   style="background:var(--card2);border-color:var(--border);color:var(--text)">
                            <datalist id="deptListModal">
                                <option value="IT">
                                <option value="Management">
                                <option value="Sales">
                                <option value="Support">
                                <option value="Operations">
                            </datalist>
                        </div>
                        ${editable ? `
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-medium">Status</label>
                            <select name="status" class="form-select form-select-sm"
                                    style="background:var(--card2);border-color:var(--border);color:var(--text)"
                                    ${isSelf ? 'disabled' : ''}>
                                <option value="active" ${a.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${a.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                            ${isSelf ? '<input type="hidden" name="status" value="' + a.status + '">' : ''}
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-medium">New Password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="new_password" id="editPwField"
                                       class="form-control form-control-sm" placeholder="Leave blank to keep"
                                       minlength="6"
                                       style="background:var(--card2);border-color:var(--border);color:var(--text)">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="togglePw('editPwField',this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>` : ''}
                    </div>

                    <!-- numbers section -->
                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <div class="small fw-medium" style="color:var(--muted)">
                            <i class="fas fa-phone me-1"></i>Extensions &amp; Numbers (${nums.length})
                        </div>
                    </div>
                    <div id="agentNumbersList">${numRows}</div>
                    ${addNumRow}
                </form>
                ${footer}`;

            if (editable) {
                document.getElementById('editAgentForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    const data = Object.fromEntries(new FormData(this));
                    const btn  = document.querySelector('[form="editAgentForm"]');
                    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…'; }
                    fetch(APP_URL + '/agents.php?ajax=save_agent', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    }).then(r => r.json()).then(d2 => {
                        if (d2.ok) {
                            showToast('Agent updated successfully', 'success');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            showToast(d2.error || 'Save failed', 'danger');
                            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Changes'; }
                        }
                    });
                });
            }
        }).catch(() => {
            document.getElementById('agentModalBody').innerHTML =
                '<div class="alert alert-danger m-3">Failed to load agent data.</div>';
        });
}

function addNumber(agentId) {
    const num = document.getElementById('newNumNumber').value.trim();
    if (!num) { showToast('Enter a number or extension', 'warning'); return; }
    fetch(APP_URL + '/agents.php?ajax=save_number', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            agent_id: agentId,
            number: num,
            number_type: document.getElementById('newNumType').value,
            label: document.getElementById('newNumLabel').value.trim(),
            is_primary: 0
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) { showToast('Number added', 'success'); openAgentModal(agentId); }
        else showToast(d.error || 'Error', 'danger');
    });
}

function deleteNumber(numId) {
    if (!confirm('Remove this number?')) return;
    fetch(APP_URL + '/agents.php?ajax=delete_number&id=' + numId)
        .then(r => r.json()).then(d => {
            if (d.ok) { document.getElementById('num-row-' + numId)?.remove(); showToast('Number removed', 'success'); }
            else showToast(d.error || 'Error', 'danger');
        });
}


function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once 'includes/footer.php'; ?>
