<?php
require_once 'config.php';
requireLogin();

$aid       = agentId();
$pageTitle = 'Tags';
$activePage = 'tags';

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6366f1');
    if ($name) {
        if ($id > 0) {
            $conn->query("UPDATE tags SET name='{$conn->real_escape_string($name)}', color='{$conn->real_escape_string($color)}' WHERE id=$id");
            logActivity('tag_updated', 'tags', $id, "Updated tag: $name");
        } else {
            $conn->query("INSERT INTO tags (name, color, created_by) VALUES ('{$conn->real_escape_string($name)}','{$conn->real_escape_string($color)}',$aid)");
            logActivity('tag_created', 'tags', $conn->insert_id, "Created tag: $name");
        }
    }
    header('Location: tags.php?saved=1');
    exit;
}

$tags = $conn->query("SELECT t.*, a.full_name AS creator_name FROM tags t LEFT JOIN agents a ON a.id=t.created_by ORDER BY t.name");

$usageCounts = [];
$ur = $conn->query("SELECT tag_id, COUNT(*) AS cnt FROM call_tags GROUP BY tag_id");
while ($r = $ur->fetch_assoc()) $usageCounts[$r['tag_id']] = $r['cnt'];

require_once 'includes/layout.php';
?>
<style>
.tag-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 600; }
.tag-swatch { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Call Tags</h5>
        <span class="text-muted small">Label and categorize calls for filtering and reporting</span>
    </div>
    <button class="btn btn-sm btn-primary" onclick="openTagForm()">
        <i class="fas fa-plus me-1"></i>New Tag
    </button>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success small py-2"><i class="fas fa-check-circle me-1"></i>Tag saved successfully.</div>
<?php endif; ?>

<div class="cc-card">
    <?php if (!$tags || !$tags->num_rows): ?>
    <div class="cc-card-body">
        <div class="empty-state py-5">
            <i class="fas fa-tags"></i><br>No tags yet
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table cc-table mb-0">
            <thead>
                <tr>
                    <th>Color</th>
                    <th>Name</th>
                    <th>Calls Tagged</th>
                    <th>Created By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($t = $tags->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="tag-chip" style="background:<?= e($t['color']) ?>20; color:<?= e($t['color']) ?>; border:1px solid <?= e($t['color']) ?>40">
                            <span class="tag-swatch" style="background:<?= e($t['color']) ?>"></span>
                            <?= e($t['name']) ?>
                        </div>
                    </td>
                    <td><?= e($t['name']) ?></td>
                    <td><span class="badge bg-secondary"><?= $usageCounts[$t['id']] ?? 0 ?></span></td>
                    <td class="small text-muted"><?= e($t['creator_name'] ?: '—') ?></td>
                    <td class="text-nowrap">
                        <button class="btn-sm-icon text-primary" onclick='editTag(<?= json_encode($t) ?>)' title="Edit">
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

<!-- Tag Form Modal -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-tag me-2"></i><span id="tagModalTitle">New Tag</span></h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="tagId" value="0">
                    <div class="mb-3">
                        <label class="form-label">Tag Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="tagName" class="form-control" required placeholder="e.g. Billing, Sales, Urgent…">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="tagColorPicker" value="#6366f1" class="form-control form-control-color"
                                   style="width:44px;padding:2px;cursor:pointer" oninput="document.getElementById('tagColor').value=this.value;updateColorPreview()">
                            <input type="text" name="color" id="tagColor" value="#6366f1" class="form-control"
                                   style="max-width:120px" placeholder="#6366f1" oninput="document.getElementById('tagColorPicker').value=this.value;updateColorPreview()">
                            <div id="tagColorPreview" class="tag-chip" style="background:rgba(99,102,241,0.15);color:#6366f1;border:1px solid rgba(99,102,241,0.3)">
                                <span class="tag-swatch" style="background:#6366f1"></span>
                                <span id="tagPreviewName">Preview</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small">Tags are used to label calls. A call can have multiple tags.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Tag</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
function openTagForm() {
    document.getElementById('tagModalTitle').textContent = 'New Tag';
    document.getElementById('tagId').value = '0';
    document.getElementById('tagName').value = '';
    document.getElementById('tagColor').value = '#6366f1';
    document.getElementById('tagColorPicker').value = '#6366f1';
    updateColorPreview();
    new bootstrap.Modal(document.getElementById('tagModal')).show();
    setTimeout(() => document.getElementById('tagName').focus(), 300);
}
function editTag(t) {
    document.getElementById('tagModalTitle').textContent = 'Edit Tag';
    document.getElementById('tagId').value = t.id;
    document.getElementById('tagName').value = t.name;
    document.getElementById('tagColor').value = t.color;
    document.getElementById('tagColorPicker').value = t.color;
    updateColorPreview();
    new bootstrap.Modal(document.getElementById('tagModal')).show();
}
function updateColorPreview() {
    const c = document.getElementById('tagColor').value;
    const n = document.getElementById('tagName').value || 'Preview';
    const p = document.getElementById('tagColorPreview');
    p.style.background = c + '20';
    p.style.color = c;
    p.style.border = '1px solid ' + c + '60';
    p.querySelector('.tag-swatch').style.background = c;
    document.getElementById('tagPreviewName').textContent = n;
}
</script>

<?php require_once 'includes/footer.php'; ?>
