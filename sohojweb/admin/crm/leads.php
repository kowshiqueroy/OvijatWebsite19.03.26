<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit   = hasPermission('sales');
$canDelete = hasPermission('super_admin');

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status') {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('sales')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('CSRF token mismatch');
        
        $leadId = (int)$_POST['id'];
        $oldLead = db()->selectOne("SELECT status FROM leads WHERE id = ?", [$leadId]);
        db()->update('leads', ['status' => $_POST['status']], 'id = :id', ['id' => $leadId]);
        logAudit('lead_status_updated', 'lead', $leadId, ['status' => $oldLead['status']], ['status' => $_POST['status']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('super_admin')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('CSRF token mismatch');

        $delId = (int)$_POST['id'];
        logAudit('lead_deleted', 'lead', $delId);
        db()->delete('leads', 'id = :id', ['id' => $delId]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_field') {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('sales')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('CSRF token mismatch');
        $allowedFields = ['assigned_to', 'follow_up_date', 'notes'];
        $field = $_POST['field'] ?? '';
        if (!in_array($field, $allowedFields)) throw new Exception('Invalid field');
        $value = $_POST['value'] === '' || $_POST['value'] === 'null' ? null : sanitize($_POST['value']);
        $leadId = (int)$_POST['id'];
        db()->update('leads', [$field => $value], 'id = :id', ['id' => $leadId]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'view' && $id) {
    $lead = db()->selectOne(
        "SELECT l.*, u.full_name AS assigned_user FROM leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.id = ?",
        [$id]
    );
}

$users = db()->select("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
$leads = db()->select("SELECT l.*, u.full_name AS assigned_user FROM leads l LEFT JOIN users u ON l.assigned_to = u.id ORDER BY l.created_at DESC");
$rawCounts = db()->select("SELECT status, COUNT(*) AS cnt FROM leads GROUP BY status");
$statusCounts = ['new' => 0, 'contacted' => 0, 'qualified' => 0, 'won' => 0];
foreach ($rawCounts as $row) {
    if (array_key_exists($row['status'], $statusCounts)) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
    }
}

$pageTitle = 'Leads / CRM | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Leads / CRM</h1>
    <p class="text-slate-500">Manage incoming inquiries and customer relationships</p>
</div>
    
    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-xl border border-blue-100">
            <p class="text-2xl font-bold text-blue-600"><?= $statusCounts['new'] ?></p>
            <p class="text-sm text-gray-600">New Leads</p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-xl border border-yellow-100">
            <p class="text-2xl font-bold text-yellow-600"><?= $statusCounts['contacted'] ?></p>
            <p class="text-sm text-gray-600">Contacted</p>
        </div>
        <div class="bg-purple-50 p-4 rounded-xl border border-purple-100">
            <p class="text-2xl font-bold text-purple-600"><?= $statusCounts['qualified'] ?></p>
            <p class="text-sm text-gray-600">Qualified</p>
        </div>
        <div class="bg-green-50 p-4 rounded-xl border border-green-100">
            <p class="text-2xl font-bold text-green-600"><?= $statusCounts['won'] ?></p>
            <p class="text-sm text-gray-600">Won</p>
        </div>
    </div>
    
    <?php if ($action === 'view' && $lead): ?>
    <div class="mb-4"><a href="leads.php" class="text-blue-600 hover:underline">&larr; Back to Leads</a></div>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-xl font-bold"><?= escape($lead['client_name']) ?></h2>
                <p class="text-gray-600"><?= escape($lead['company_name']) ?: 'No company' ?></p>
            </div>
            <?php if ($canEdit): ?>
            <select onchange="updateStatus(<?= $lead['id'] ?>, this.value)" class="px-3 py-2 border rounded-lg">
                <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>New</option>
                <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="qualified" <?= $lead['status'] === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                <option value="proposal_sent" <?= $lead['status'] === 'proposal_sent' ? 'selected' : '' ?>>Proposal Sent</option>
                <option value="negotiating" <?= $lead['status'] === 'negotiating' ? 'selected' : '' ?>>Negotiating</option>
                <option value="won" <?= $lead['status'] === 'won' ? 'selected' : '' ?>>Won</option>
                <option value="lost" <?= $lead['status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
            </select>
            <?php else: ?>
            <span class="px-3 py-2 bg-gray-100 rounded-lg text-sm font-medium"><?= ucfirst(str_replace('_', ' ', $lead['status'])) ?></span>
            <?php endif; ?>
        </div>
        
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-3">CONTACT INFORMATION</h3>
                <p class="mb-2"><i class="fas fa-envelope w-6 text-gray-400"></i> <?= escape($lead['client_email']) ?></p>
                <p class="mb-2"><i class="fas fa-phone w-6 text-gray-400"></i> <?= escape($lead['client_phone']) ?></p>
                <p><i class="fas fa-building w-6 text-gray-400"></i> <?= escape($lead['company_name']) ?: '—' ?></p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-3">PROJECT DETAILS</h3>
                <p class="mb-2"><strong>Module:</strong> <?= escape($lead['selected_module']) ?: '—' ?></p>
                <p class="mb-2"><strong>Complexity:</strong> <?= $lead['complexity_scale'] ?> / 5</p>
                <p class="mb-2"><strong>Integrations:</strong> <?= escape($lead['technical_integrations']) ?: 'None' ?></p>
                <p class="mb-2"><strong>Estimated Budget:</strong> <?= $lead['estimated_budget'] ? '৳ ' . number_format($lead['estimated_budget']) : '—' ?></p>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-6 mt-6 pt-6 border-t">
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-2">ASSIGNED TO</h3>
                <?php if ($canEdit): ?>
                <select onchange="updateAssigned(<?= $lead['id'] ?>, this.value)" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $lead['assigned_to'] == $u['id'] ? 'selected' : '' ?>><?= escape($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <p class="text-gray-700"><?= escape($lead['assigned_user']) ?: '—' ?></p>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-2">FOLLOW-UP DATE</h3>
                <?php if ($canEdit): ?>
                <input type="date" value="<?= escape($lead['follow_up_date'] ?? '') ?>" onchange="updateFollowUp(<?= $lead['id'] ?>, this.value)" class="w-full px-3 py-2 border rounded-lg text-sm">
                <?php else: ?>
                <p class="text-gray-700"><?= $lead['follow_up_date'] ? date('F d, Y', strtotime($lead['follow_up_date'])) : '—' ?></p>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-2">INTERNAL NOTES</h3>
                <?php if ($canEdit): ?>
                <textarea rows="2" onblur="updateNotes(<?= $lead['id'] ?>, this.value)" class="w-full px-3 py-2 border rounded-lg text-sm resize-none"><?= escape($lead['notes'] ?? '') ?></textarea>
                <?php else: ?>
                <p class="text-gray-700"><?= nl2br(escape($lead['notes'] ?? '')) ?: '—' ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($lead['message']): ?>
        <div class="mt-6">
            <h3 class="font-semibold text-gray-500 text-sm mb-2">MESSAGE</h3>
            <p class="text-gray-700"><?= nl2br(escape($lead['message'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-6 pt-6 border-t text-sm text-gray-500">
            <p>Source: <?= ucfirst(str_replace('_', ' ', $lead['lead_source'])) ?> | Created: <?= date('F d, Y g:i A', strtotime($lead['created_at'])) ?></p>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Lead</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Module</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Budget</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Assigned</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Source</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Status</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Date</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <a href="?action=view&id=<?= $lead['id'] ?>" class="font-medium text-gray-800 hover:text-blue-600"><?= escape($lead['client_name']) ?></a>
                        <p class="text-xs text-gray-500"><?= escape($lead['client_phone']) ?></p>
                    </td>
                    <td class="py-3 px-4 text-sm"><?= escape($lead['selected_module']) ?></td>
                    <td class="py-3 px-4 text-sm font-medium"><?= $lead['estimated_budget'] ? '৳ ' . number_format($lead['estimated_budget']) : '—' ?></td>
                    <td class="py-3 px-4 text-sm text-gray-600"><?= escape($lead['assigned_user']) ?: '<span class="text-gray-400">—</span>' ?></td>
                    <td class="py-3 px-4 text-sm"><span class="px-2 py-1 bg-gray-100 rounded text-xs"><?= ucfirst(str_replace('_', ' ', $lead['lead_source'])) ?></span></td>
                    <td class="py-3 px-4">
                        <?php if ($canEdit): ?>
                        <select onchange="updateStatus(<?= $lead['id'] ?>, this.value)" class="text-xs px-2 py-1 rounded-full border-0 cursor-pointer <?= $lead['status'] === 'new' ? 'bg-blue-100 text-blue-700' : ($lead['status'] === 'won' ? 'bg-green-100 text-green-700' : ($lead['status'] === 'lost' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) ?>">
                            <option value="new" <?= $lead['status'] === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="contacted" <?= $lead['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                            <option value="qualified" <?= $lead['status'] === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                            <option value="proposal_sent" <?= $lead['status'] === 'proposal_sent' ? 'selected' : '' ?>>Proposal</option>
                            <option value="negotiating" <?= $lead['status'] === 'negotiating' ? 'selected' : '' ?>>Negotiating</option>
                            <option value="won" <?= $lead['status'] === 'won' ? 'selected' : '' ?>>Won</option>
                            <option value="lost" <?= $lead['status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                        </select>
                        <?php else: ?>
                        <span class="text-xs px-2 py-1 rounded-full <?= $lead['status'] === 'new' ? 'bg-blue-100 text-blue-700' : ($lead['status'] === 'won' ? 'bg-green-100 text-green-700' : ($lead['status'] === 'lost' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) ?>"><?= ucfirst(str_replace('_', ' ', $lead['status'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-sm text-gray-500"><?= date('M d', strtotime($lead['created_at'])) ?></td>
                    <td class="py-3 px-4">
                        <a href="?action=view&id=<?= $lead['id'] ?>" class="p-2 text-gray-500 hover:text-blue-600"><i class="fas fa-eye"></i></a>
                        <?php if ($canDelete): ?>
                        <button onclick="deleteLead(<?= $lead['id'] ?>)" class="p-2 text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
const CSRF_TOKEN = '<?= csrf_token() ?>';

function updateStatus(id, status) {
    const formData = new URLSearchParams();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('id', id);
    formData.append('status', status);

    fetch('leads.php?action=update_status', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData.toString()
    }).then(r => r.json()).then(data => {
        if(data.success) showToast('success', 'Status updated');
        else alert(data.message || 'Error updating status');
    });
}

function updateField(id, field, value) {
    const formData = new URLSearchParams();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('id', id);
    formData.append('field', field);
    formData.append('value', value);
    fetch('leads.php?action=update_field', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData.toString()
    }).then(r => r.json()).then(data => {
        if(data.success) showToast('success', 'Saved');
        else alert(data.message || 'Error saving');
    });
}
function updateAssigned(id, val)  { updateField(id, 'assigned_to', val || null); }
function updateFollowUp(id, val)  { updateField(id, 'follow_up_date', val || null); }
function updateNotes(id, val)     { updateField(id, 'notes', val); }

function deleteLead(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if (confirm('Are you sure you want to delete this lead? This action cannot be undone.')) {
        const formData = new URLSearchParams();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('id', id);

        fetch('leads.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        }).then(r => r.json()).then(data => {
            if(data.success) location.reload();
            else alert(data.message || 'Error deleting lead');
        });
    }
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
