<?php
require_once 'config.php';
requireLogin();

$aid        = agentId();
$pageTitle  = 'Contact Groups';
$activePage = 'groups';

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6366f1');
    $desc  = trim($_POST['description'] ?? '');
    if ($name) {
        $eName  = $conn->real_escape_string($name);
        $eColor = $conn->real_escape_string($color);
        $eDesc  = $conn->real_escape_string($desc);
        if ($id > 0) {
            $conn->query("UPDATE contact_groups SET name='$eName', color='$eColor', description='$eDesc' WHERE id=$id");
            logActivity('group_updated', 'contact_groups', $id, "Updated group: $name");
        } else {
            $conn->query("INSERT INTO contact_groups (name, color, description, created_by)
                          VALUES ('$eName','$eColor','$eDesc',$aid)");
            logActivity('group_created', 'contact_groups', $conn->insert_id, "Created group: $name");
        }
    }
    header('Location: groups.php?saved=1');
    exit;
}

$groups = $conn->query(
    "SELECT g.*, a.full_name AS creator_name
     FROM contact_groups g
     LEFT JOIN agents a ON a.id = g.created_by
     ORDER BY g.name"
);

// Usage counts from contacts
$usageCounts = [];
$ur = $conn->query("SELECT group_id, COUNT(*) AS cnt FROM contacts WHERE group_id IS NOT NULL GROUP BY group_id");
while ($r = $ur->fetch_assoc()) $usageCounts[$r['group_id']] = $r['cnt'];

require_once 'includes/layout.php';
?>
<style>
.group-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .2rem .65rem; border-radius: 999px;
    font-size: .78rem; font-weight: 600;
}
.group-swatch { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0"><i class="fas fa-object-group me-2"></i>Contact Groups</h5>
        <span class="text-muted small">Organise contacts into groups — VIP, Hot Leads, Support Queue…</span>
    </div>
    <button class="btn btn-sm btn-primary" onclick="openGroupForm()">
        <i class="fas fa-plus me-1"></i>New Group
    </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success small py-2 mb-3">
    <i class="fas fa-check-circle me-1"></i>Contact group saved successfully.
</div>
<?php endif; ?>

<div class="cc-card">
    <?php if (!$groups || !$groups->num_rows): ?>
    <div class="cc-card-body">
        <div class="empty-state py-5">
            <i class="fas fa-object-group"></i><br>No contact groups yet
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table cc-table mb-0">
            <thead>
                <tr>
                    <th style="width:200px">Group</th>
                    <th>Description</th>
                    <th style="width:120px">Contacts</th>
                    <th style="width:140px">Created By</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($g = $groups->fetch_assoc()):
                    $count = $usageCounts[$g['id']] ?? 0;
                ?>
                <tr>
                    <td>
                        <div class="group-chip"
                             style="background:<?= e($g['color']) ?>20;color:<?= e($g['color']) ?>;border:1px solid <?= e($g['color']) ?>40">
                            <span class="group-swatch" style="background:<?= e($g['color']) ?>"></span>
                            <?= e($g['name']) ?>
                        </div>
                    </td>
                    <td class="small text-muted"><?= e($g['description'] ?: '—') ?></td>
                    <td>
                        <span class="badge" style="background:var(--card2);color:var(--muted);border:1px solid var(--border)">
                            <i class="fas fa-address-card me-1" style="font-size:.65rem"></i><?= $count ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= e($g['creator_name'] ?: '—') ?></td>
                    <td>
                        <button class="btn-sm-icon text-primary" onclick='editGroup(<?= json_encode($g) ?>)' title="Edit">
                            <i class="fas fa-pen"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Group Form Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-object-group me-2"></i><span id="groupModalTitle">New Group</span>
                </h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="groupId" value="0">

                    <div class="mb-3">
                        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="groupName" class="form-control" required
                               placeholder="e.g. VIP, Hot Leads, Support Queue…">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <input type="text" name="description" id="groupDesc" class="form-control"
                               placeholder="Short description (optional)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Color</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="groupColorPicker" value="#6366f1"
                                   class="form-control form-control-color"
                                   style="width:44px;padding:2px;cursor:pointer"
                                   oninput="document.getElementById('groupColor').value=this.value;updateGroupPreview()">
                            <input type="text" name="color" id="groupColor" value="#6366f1"
                                   class="form-control" style="max-width:120px" placeholder="#6366f1"
                                   oninput="document.getElementById('groupColorPicker').value=this.value;updateGroupPreview()">
                            <div id="groupColorPreview" class="group-chip"
                                 style="background:rgba(99,102,241,.15);color:#6366f1;border:1px solid rgba(99,102,241,.3)">
                                <span class="group-swatch" style="background:#6366f1"></span>
                                <span id="groupPreviewName">Preview</span>
                            </div>
                        </div>

                        <!-- Quick palette -->
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <?php foreach (['#6366f1','#f59e0b','#ef4444','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#6b7280'] as $pc): ?>
                            <div onclick="pickGroupColor('<?= $pc ?>')"
                                 style="width:22px;height:22px;border-radius:50%;background:<?= $pc ?>;cursor:pointer;
                                        border:2px solid transparent;transition:.15s"
                                 class="gpalette-dot" title="<?= $pc ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openGroupForm() {
    document.getElementById('groupModalTitle').textContent = 'New Group';
    document.getElementById('groupId').value    = '0';
    document.getElementById('groupName').value  = '';
    document.getElementById('groupDesc').value  = '';
    setGroupColor('#6366f1');
    new bootstrap.Modal(document.getElementById('groupModal')).show();
    setTimeout(() => document.getElementById('groupName').focus(), 300);
}

function editGroup(g) {
    document.getElementById('groupModalTitle').textContent = 'Edit Group';
    document.getElementById('groupId').value    = g.id;
    document.getElementById('groupName').value  = g.name;
    document.getElementById('groupDesc').value  = g.description || '';
    setGroupColor(g.color || '#6366f1');
    new bootstrap.Modal(document.getElementById('groupModal')).show();
}

function setGroupColor(c) {
    document.getElementById('groupColor').value       = c;
    document.getElementById('groupColorPicker').value = c;
    updateGroupPreview();
}

function pickGroupColor(c) {
    setGroupColor(c);
    document.querySelectorAll('.gpalette-dot').forEach(d => {
        d.style.borderColor = d.title === c ? '#fff' : 'transparent';
    });
}

function updateGroupPreview() {
    const c = document.getElementById('groupColor').value;
    const n = document.getElementById('groupName').value || 'Preview';
    const p = document.getElementById('groupColorPreview');
    p.style.background = c + '20';
    p.style.color      = c;
    p.style.border     = '1px solid ' + c + '60';
    p.querySelector('.group-swatch').style.background = c;
    document.getElementById('groupPreviewName').textContent = n;
}

document.getElementById('groupName').addEventListener('input', updateGroupPreview);
</script>

<?php require_once 'includes/footer.php'; ?>
