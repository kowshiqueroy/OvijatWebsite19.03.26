<?php
require_once 'config.php';
requireLogin();

$aid        = agentId();
$pageTitle  = 'Contact Types';
$activePage = 'types';

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#10b981');
    $desc  = trim($_POST['description'] ?? '');
    if ($name) {
        $eName  = $conn->real_escape_string($name);
        $eColor = $conn->real_escape_string($color);
        $eDesc  = $conn->real_escape_string($desc);
        if ($id > 0) {
            $conn->query("UPDATE contact_types SET name='$eName', color='$eColor', description='$eDesc' WHERE id=$id");
            logActivity('type_updated', 'contact_types', $id, "Updated type: $name");
        } else {
            $conn->query("INSERT INTO contact_types (name, color, description, created_by)
                          VALUES ('$eName','$eColor','$eDesc',$aid)");
            logActivity('type_created', 'contact_types', $conn->insert_id, "Created type: $name");
        }
    }
    header('Location: types.php?saved=1');
    exit;
}

$types = $conn->query(
    "SELECT t.*, a.full_name AS creator_name
     FROM contact_types t
     LEFT JOIN agents a ON a.id = t.created_by
     ORDER BY t.name"
);

// Usage counts from contacts
$usageCounts = [];
$ur = $conn->query("SELECT type_id, COUNT(*) AS cnt FROM contacts WHERE type_id IS NOT NULL GROUP BY type_id");
while ($r = $ur->fetch_assoc()) $usageCounts[$r['type_id']] = $r['cnt'];

require_once 'includes/layout.php';
?>
<style>
.type-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .2rem .65rem; border-radius: 999px;
    font-size: .78rem; font-weight: 600;
}
.type-swatch { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Contact Types</h5>
        <span class="text-muted small">Classify contacts by type — Customer, Lead, Vendor…</span>
    </div>
    <button class="btn btn-sm btn-primary" onclick="openTypeForm()">
        <i class="fas fa-plus me-1"></i>New Type
    </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success small py-2 mb-3">
    <i class="fas fa-check-circle me-1"></i>Contact type saved successfully.
</div>
<?php endif; ?>

<div class="cc-card">
    <?php if (!$types || !$types->num_rows): ?>
    <div class="cc-card-body">
        <div class="empty-state py-5">
            <i class="fas fa-layer-group"></i><br>No contact types yet
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table cc-table mb-0">
            <thead>
                <tr>
                    <th style="width:200px">Type</th>
                    <th>Description</th>
                    <th style="width:120px">Contacts</th>
                    <th style="width:140px">Created By</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($t = $types->fetch_assoc()):
                    $count = $usageCounts[$t['id']] ?? 0;
                ?>
                <tr>
                    <td>
                        <div class="type-chip"
                             style="background:<?= e($t['color']) ?>20;color:<?= e($t['color']) ?>;border:1px solid <?= e($t['color']) ?>40">
                            <span class="type-swatch" style="background:<?= e($t['color']) ?>"></span>
                            <?= e($t['name']) ?>
                        </div>
                    </td>
                    <td class="small text-muted"><?= e($t['description'] ?: '—') ?></td>
                    <td>
                        <span class="badge" style="background:var(--card2);color:var(--muted);border:1px solid var(--border)">
                            <i class="fas fa-address-card me-1" style="font-size:.65rem"></i><?= $count ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= e($t['creator_name'] ?: '—') ?></td>
                    <td>
                        <button class="btn-sm-icon text-primary" onclick='editType(<?= json_encode($t) ?>)' title="Edit">
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

<!-- Type Form Modal -->
<div class="modal fade" id="typeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-layer-group me-2"></i><span id="typeModalTitle">New Type</span>
                </h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="typeId" value="0">

                    <div class="mb-3">
                        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="typeName" class="form-control" required
                               placeholder="e.g. Customer, Lead, Vendor…">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <input type="text" name="description" id="typeDesc" class="form-control"
                               placeholder="Short description (optional)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Color</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="typeColorPicker" value="#10b981"
                                   class="form-control form-control-color"
                                   style="width:44px;padding:2px;cursor:pointer"
                                   oninput="document.getElementById('typeColor').value=this.value;updateTypePreview()">
                            <input type="text" name="color" id="typeColor" value="#10b981"
                                   class="form-control" style="max-width:120px" placeholder="#10b981"
                                   oninput="document.getElementById('typeColorPicker').value=this.value;updateTypePreview()">
                            <div id="typeColorPreview" class="type-chip"
                                 style="background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3)">
                                <span class="type-swatch" style="background:#10b981"></span>
                                <span id="typePreviewName">Preview</span>
                            </div>
                        </div>

                        <!-- Quick palette -->
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <?php foreach (['#10b981','#6366f1','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f97316','#6b7280'] as $pc): ?>
                            <div onclick="pickTypeColor('<?= $pc ?>')"
                                 style="width:22px;height:22px;border-radius:50%;background:<?= $pc ?>;cursor:pointer;
                                        border:2px solid transparent;transition:.15s"
                                 class="palette-dot" title="<?= $pc ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTypeForm() {
    document.getElementById('typeModalTitle').textContent = 'New Type';
    document.getElementById('typeId').value    = '0';
    document.getElementById('typeName').value  = '';
    document.getElementById('typeDesc').value  = '';
    setTypeColor('#10b981');
    new bootstrap.Modal(document.getElementById('typeModal')).show();
    setTimeout(() => document.getElementById('typeName').focus(), 300);
}

function editType(t) {
    document.getElementById('typeModalTitle').textContent = 'Edit Type';
    document.getElementById('typeId').value    = t.id;
    document.getElementById('typeName').value  = t.name;
    document.getElementById('typeDesc').value  = t.description || '';
    setTypeColor(t.color || '#10b981');
    new bootstrap.Modal(document.getElementById('typeModal')).show();
}

function setTypeColor(c) {
    document.getElementById('typeColor').value       = c;
    document.getElementById('typeColorPicker').value = c;
    updateTypePreview();
}

function pickTypeColor(c) {
    setTypeColor(c);
    document.querySelectorAll('.palette-dot').forEach(d => {
        d.style.borderColor = d.title === c ? '#fff' : 'transparent';
    });
}

function updateTypePreview() {
    const c = document.getElementById('typeColor').value;
    const n = document.getElementById('typeName').value || 'Preview';
    const p = document.getElementById('typeColorPreview');
    p.style.background = c + '20';
    p.style.color      = c;
    p.style.border     = '1px solid ' + c + '60';
    p.querySelector('.type-swatch').style.background = c;
    document.getElementById('typePreviewName').textContent = n;
}

document.getElementById('typeName').addEventListener('input', updateTypePreview);
</script>

<?php require_once 'includes/footer.php'; ?>
