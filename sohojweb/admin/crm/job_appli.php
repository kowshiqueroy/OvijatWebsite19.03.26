<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit   = hasPermission('hr');
$canDelete = hasPermission('hr');

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status') {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('hr')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('Security mismatch');
        
        $newStatus = sanitize($_POST['status']);
        $appId = (int)$_POST['id'];
        db()->update('job_applications', ['status' => $newStatus], 'id = :id', ['id' => $appId]);
        logAudit('application_status_updated', 'job_application', $appId, null, ['status' => $newStatus]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('hr')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('Security mismatch');

        db()->delete('job_applications', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

$applications = db()->select("
    SELECT a.*, c.job_title 
    FROM job_applications a 
    LEFT JOIN job_circulars c ON a.circular_id = c.id 
    ORDER BY a.applied_at DESC
");

$pageTitle = 'Job Applications | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Job Applications</h1>
        <p class="text-slate-500">Review and manage incoming candidate applications</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Applicant</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Job Position</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Applied Date</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="5" class="py-12 text-center text-slate-400">
                        <i class="fas fa-inbox text-4xl mb-3 block"></i>
                        No applications received yet.
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($applications as $app): ?>
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 font-bold border border-blue-100">
                                <?= strtoupper(substr($app['applicant_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900"><?= escape($app['applicant_name']) ?></p>
                                <p class="text-xs text-slate-500"><?= escape($app['applicant_email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-6">
                        <span class="text-sm font-medium text-slate-700"><?= escape($app['job_title'] ?? 'N/A') ?></span>
                    </td>
                    <td class="py-4 px-6">
                        <span class="text-sm text-slate-500"><?= date('M d, Y', strtotime($app['applied_at'])) ?></span>
                    </td>
                    <td class="py-4 px-6">
                        <?php if ($canEdit): ?>
                        <select onchange="updateAppStatus(<?= $app['id'] ?>, this.value)" class="text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full border-none ring-1 ring-inset
                            <?= $app['status'] === 'pending' ? 'bg-yellow-50 text-yellow-700 ring-yellow-600/20' :
                               ($app['status'] === 'reviewing' ? 'bg-blue-50 text-blue-700 ring-blue-600/20' :
                               ($app['status'] === 'shortlisted' ? 'bg-purple-50 text-purple-700 ring-purple-600/20' :
                               ($app['status'] === 'rejected' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-green-50 text-green-700 ring-green-600/20'))) ?>">
                            <option value="pending" <?= $app['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="reviewing" <?= $app['status'] === 'reviewing' ? 'selected' : '' ?>>Reviewing</option>
                            <option value="shortlisted" <?= $app['status'] === 'shortlisted' ? 'selected' : '' ?>>Shortlisted</option>
                            <option value="hired" <?= $app['status'] === 'hired' ? 'selected' : '' ?>>Hired</option>
                            <option value="rejected" <?= $app['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <?php else: ?>
                        <span class="text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full ring-1 ring-inset <?= $app['status'] === 'pending' ? 'bg-yellow-50 text-yellow-700 ring-yellow-600/20' : ($app['status'] === 'reviewing' ? 'bg-blue-50 text-blue-700 ring-blue-600/20' : ($app['status'] === 'shortlisted' ? 'bg-purple-50 text-purple-700 ring-purple-600/20' : ($app['status'] === 'rejected' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-green-50 text-green-700 ring-green-600/20'))) ?>"><?= ucfirst($app['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="py-4 px-6 text-right">
                        <div class="flex justify-end gap-2">
                            <button onclick="viewApplication(<?= htmlspecialchars(json_encode($app)) ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($canDelete): ?>
                            <button onclick="deleteApplication(<?= $app['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Modal -->
<div id="appModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div>
                <h2 class="text-2xl font-bold text-slate-900" id="modal_name">Applicant Details</h2>
                <p class="text-slate-500 text-sm" id="modal_job">Job Title</p>
            </div>
            <button onclick="closeModal()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white transition-colors text-slate-400 hover:text-slate-600 shadow-sm">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 space-y-8">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Email Address</p>
                    <p class="font-bold text-slate-800" id="modal_email"></p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Phone Number</p>
                    <p class="font-bold text-slate-800" id="modal_phone"></p>
                </div>
            </div>
            
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Cover Letter / Statement</p>
                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-slate-600 leading-relaxed whitespace-pre-wrap" id="modal_content"></div>
            </div>
        </div>
        <div class="px-8 py-6 border-t border-slate-100 bg-slate-50/50 flex justify-end">
            <button onclick="closeModal()" class="px-6 py-2.5 bg-white border border-slate-200 rounded-xl font-bold text-slate-600 hover:bg-slate-100 transition-all">Close Details</button>
        </div>
    </div>
</div>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
const CSRF_TOKEN = '<?= csrf_token() ?>';

function viewApplication(app) {
    document.getElementById('modal_name').innerText = app.applicant_name;
    document.getElementById('modal_job').innerText = app.job_title || 'N/A';
    document.getElementById('modal_email').innerText = app.applicant_email;
    document.getElementById('modal_phone').innerText = app.applicant_phone;
    document.getElementById('modal_content').innerText = app.cover_letter;
    
    const modal = document.getElementById('appModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('appModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function updateAppStatus(id, status) {
    const formData = new URLSearchParams();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('id', id);
    formData.append('status', status);

    fetch('job_appli.php?action=update_status', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData.toString()
    }).then(r => r.json()).then(data => {
        if(data.success) showToast('success', 'Status updated');
        else alert(data.message || 'Error updating status');
    });
}

function deleteApplication(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if (confirm('Delete this application?')) {
        const formData = new URLSearchParams();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('id', id);

        fetch('job_appli.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        }).then(r => r.json()).then(data => {
            if(data.success) location.reload();
            else alert(data.message || 'Error deleting application');
        });
    }
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
