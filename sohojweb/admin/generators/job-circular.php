<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit   = hasPermission('hr');
$canDelete = hasPermission('super_admin');

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'create')) {
    header('Content-Type: application/json');
    if (!hasPermission('hr')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            throw new Exception('CSRF token mismatch');
        }
        $data = [
            'job_title' => sanitize($_POST['job_title']),
            'company_name' => sanitize($_POST['company_name']) ?: getSetting('company_name'),
            'location' => sanitize($_POST['location']) ?: null,
            'employment_type' => sanitize($_POST['employment_type']) ?: null,
            'salary_range' => sanitize($_POST['salary_range']) ?: null,
            'experience_required' => sanitize($_POST['experience_required']) ?: null,
            'education_requirement' => sanitize($_POST['education_requirement']) ?: null,
            'job_description' => sanitize($_POST['job_description']) ?: null,
            'responsibilities' => sanitize($_POST['responsibilities']) ?: null,
            'requirements' => sanitize($_POST['requirements']) ?: null,
            'benefits' => sanitize($_POST['benefits']) ?: null,
            'apply_deadline' => sanitize($_POST['apply_deadline']) ?: null,
            'contact_email' => sanitize($_POST['contact_email']) ?: getSetting('company_email'),
            'status' => sanitize($_POST['status'] ?? 'draft'),
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        if ($id) {
            db()->update('job_circulars', $data, 'id = :id', ['id' => $id]);
            jsonResponse(['success' => true, 'message' => 'Job circular updated', 'id' => $id]);
        } else {
            $newId = db()->insert('job_circulars', $data);
            jsonResponse(['success' => true, 'message' => 'Job circular created', 'id' => $newId]);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    header('Content-Type: application/json');
    if (!hasPermission('super_admin')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        db()->delete('job_circulars', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'view' && $id) {
    $job = db()->selectOne("SELECT * FROM job_circulars WHERE id = ?", [$id]);
}

$jobs = db()->select("SELECT * FROM job_circulars ORDER BY created_at DESC");

$pageTitle = 'Job Circulars | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Job Circulars</h1>
        <?php if ($canEdit): ?>
        <button onclick="openModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> New Job Post
        </button>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'view' && $job): ?>
    <div class="mb-4"><a href="job-circular.php" class="text-blue-600 hover:underline">&larr; Back to Jobs</a></div>
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl mx-auto">
        <div class="text-center border-b pb-6 mb-6">
            <h1 class="text-2xl font-bold"><?= escape($job['job_title']) ?></h1>
            <p class="text-xl text-gray-600"><?= escape($job['company_name']) ?></p>
            <div class="flex justify-center gap-4 mt-4 text-sm text-gray-500">
                <span><i class="fas fa-map-marker mr-1"></i> <?= escape($job['location']) ?></span>
                <span><i class="fas fa-briefcase mr-1"></i> <?= escape($job['employment_type']) ?></span>
                <span><i class="fas fa-money-bill mr-1"></i> <?= escape($job['salary_range']) ?></span>
            </div>
        </div>
        
        <div class="space-y-6">
            <?php if ($job['job_description']): ?>
            <div><h3 class="font-bold text-gray-700 mb-2">Job Description</h3><p class="text-gray-600"><?= nl2br(escape($job['job_description'])) ?></p></div>
            <?php endif; ?>
            <?php if ($job['responsibilities']): ?>
            <div><h3 class="font-bold text-gray-700 mb-2">Responsibilities</h3><p class="text-gray-600"><?= nl2br(escape($job['responsibilities'])) ?></p></div>
            <?php endif; ?>
            <?php if ($job['requirements']): ?>
            <div><h3 class="font-bold text-gray-700 mb-2">Requirements</h3><p class="text-gray-600"><?= nl2br(escape($job['requirements'])) ?></p></div>
            <?php endif; ?>
            <?php if ($job['benefits']): ?>
            <div><h3 class="font-bold text-gray-700 mb-2">Benefits</h3><p class="text-gray-600"><?= nl2br(escape($job['benefits'])) ?></p></div>
            <?php endif; ?>
        </div>
        
        <div class="mt-8 pt-6 border-t text-center">
            <p class="text-gray-600 mb-2">Apply before: <strong><?= $job['apply_deadline'] ? date('F d, Y', strtotime($job['apply_deadline'])) : 'Open' ?></strong></p>
            <p class="text-gray-600">Send resume to: <strong><?= escape($job['contact_email']) ?></strong></p>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($jobs as $job): ?>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex justify-between items-start mb-2">
                <span class="px-2 py-1 text-xs rounded-full <?= $job['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' ?>"><?= ucfirst($job['status']) ?></span>
            </div>
            <h3 class="font-bold text-lg mb-1"><?= escape($job['job_title']) ?></h3>
            <p class="text-sm text-gray-600 mb-2"><?= escape($job['company_name']) ?></p>
            <div class="text-xs text-gray-500 mb-4">
                <p><i class="fas fa-map-marker mr-1"></i> <?= escape($job['location']) ?></p>
                <p><i class="fas fa-clock mr-1"></i> Deadline: <?= $job['apply_deadline'] ? date('M d, Y', strtotime($job['apply_deadline'])) : 'Open' ?></p>
            </div>
            <div class="flex gap-2">
                <a href="?action=view&id=<?= $job['id'] ?>" class="flex-1 text-center px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">View</a>
                <?php if ($canDelete): ?>
                <button onclick="deleteJob(<?= $job['id'] ?>)" class="px-3 py-2 text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Modal -->
    <div id="jobModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b px-6 py-4 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold">Create Job Circular</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <form id="jobForm" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-1">Job Title *</label>
                        <input type="text" name="job_title" required class="w-full px-3 py-2 border rounded-lg" placeholder="e.g. Senior PHP Developer">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Company Name</label>
                        <input type="text" name="company_name" value="<?= getSetting('company_name') ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Location</label>
                        <input type="text" name="location" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g. Dhaka, Bangladesh">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Employment Type</label>
                        <select name="employment_type" class="w-full px-3 py-2 border rounded-lg">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Internship">Internship</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Salary Range</label>
                        <input type="text" name="salary_range" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g. 50,000 - 80,000 BDT">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Experience Required</label>
                        <input type="text" name="experience_required" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g. 2-4 years">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Education</label>
                        <input type="text" name="education_requirement" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g. BSc in CSE">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-1">Apply Deadline</label>
                        <input type="date" name="apply_deadline" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Job Description</label>
                    <textarea name="job_description" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Responsibilities</label>
                    <textarea name="responsibilities" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Requirements</label>
                    <textarea name="requirements" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Benefits</label>
                    <textarea name="benefits" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Contact Email</label>
                    <input type="email" name="contact_email" value="<?= getSetting('company_email') ?>" class="w-full px-3 py-2 border rounded-lg">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border rounded-lg">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save Job Post</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;

function openModal() { document.getElementById('jobModal').classList.remove('hidden'); document.getElementById('jobModal').classList.add('flex'); }
function closeModal() { document.getElementById('jobModal').classList.add('hidden'); document.getElementById('jobModal').classList.remove('flex'); }
document.getElementById('jobForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    const formData = new FormData(this);
    const jobId = '<?= $id ?? '' ?>';
    const actionUrl = 'job-circular.php?action=save' + (jobId ? '&id=' + jobId : '');

    fetch(actionUrl, {method: 'POST', body: formData}).then(res => res.json()).then(data => { 
        if(data.success) { 
            closeModal(); 
            location.reload(); 
        } else {
            alert(data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        }
    }).catch(err => {
        console.error(err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
});
function deleteJob(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete this job post?')) fetch('job-circular.php?action=delete', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'id='+id}).then(() => location.reload());
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
