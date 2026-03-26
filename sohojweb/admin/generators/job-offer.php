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

function generateOfferNumber() {
    $row = db()->selectOne("SELECT MAX(CAST(SUBSTRING(offer_number, 5) AS UNSIGNED)) as max_num FROM job_offer_letters");
    $nextNum = ($row['max_num'] ?? 0) + 1;
    return 'OFF-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'create')) {
    ob_clean(); // Clear any previous output (warnings, whitespace)
    header('Content-Type: application/json');
    if (!hasPermission('hr')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            throw new Exception('Security token mismatch. Please refresh and try again.');
        }
        $data = [
            'candidate_name' => sanitize($_POST['candidate_name']),
            'candidate_email' => sanitize($_POST['candidate_email']),
            'candidate_phone' => sanitize($_POST['candidate_phone'] ?? '') ?: null,
            'position' => sanitize($_POST['position']),
            'department' => sanitize($_POST['department'] ?? '') ?: null,
            'joining_date' => sanitize($_POST['joining_date']),
            'salary' => (float)($_POST['salary'] ?? 0),
            'salary_currency' => sanitize($_POST['salary_currency'] ?? 'BDT'),
            'employment_type' => sanitize($_POST['employment_type'] ?? 'Full-time'),
            'working_hours' => sanitize($_POST['working_hours'] ?? '') ?: null,
            'probation_period' => sanitize($_POST['probation_period'] ?? '') ?: null,
            'benefits' => sanitize($_POST['benefits'] ?? '') ?: null,
            'report_to' => sanitize($_POST['report_to'] ?? '') ?: null,
            'terms' => sanitize($_POST['terms'] ?? '') ?: null,
            'status' => sanitize($_POST['status'] ?? 'draft'),
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        if ($id) {
            db()->update('job_offer_letters', $data, 'id = :id', ['id' => $id]);
            jsonResponse(['success' => true, 'message' => 'Offer letter updated', 'id' => $id]);
        } else {
            $data['offer_number'] = generateOfferNumber();
            $newId = db()->insert('job_offer_letters', $data);
            jsonResponse(['success' => true, 'message' => 'Offer letter created', 'id' => $newId]);
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
        db()->delete('job_offer_letters', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'view' && $id) {
    $offer = db()->selectOne("SELECT * FROM job_offer_letters WHERE id = ?", [$id]);
}

$offers = db()->select("SELECT * FROM job_offer_letters ORDER BY created_at DESC");

$pageTitle = 'Job Offers | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Job Offer Letters</h1>
        <?php if ($canEdit): ?>
        <button onclick="openModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> New Offer Letter
        </button>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'view' && $offer): ?>
    <div class="mb-4"><a href="job-offer.php" class="text-blue-600 hover:underline">&larr; Back to Offers</a></div>
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl mx-auto">
        <div class="text-center border-b pb-6 mb-6">
            <h1 class="text-2xl font-bold">Job Offer Letter</h1>
            <p class="text-gray-600"><?= getSetting('company_name') ?></p>
        </div>
        
        <p class="mb-4">Date: <?= date('F d, Y', strtotime($offer['created_at'])) ?></p>
        
        <p class="mb-4">To,<br><strong><?= escape($offer['candidate_name']) ?></strong><br><?= escape($offer['candidate_email']) ?></p>
        
        <p class="mb-4"><strong>Subject: Offer Letter for the position of <?= escape($offer['position']) ?></strong></p>
        
        <p class="mb-4">Dear <?= escape($offer['candidate_name']) ?>,</p>
        
        <p class="mb-4">We are pleased to offer you the position of <strong><?= escape($offer['position']) ?></strong> at <?= getSetting('company_name') ?>. We believe your skills and experience will be a great addition to our team.</p>
        
        <div class="mb-4">
            <h3 class="font-bold">Employment Details:</h3>
            <ul class="list-disc pl-5">
                <li><strong>Position:</strong> <?= escape($offer['position']) ?></li>
                <li><strong>Department:</strong> <?= escape($offer['department']) ?></li>
                <li><strong>Joining Date:</strong> <?= date('F d, Y', strtotime($offer['joining_date'])) ?></li>
                <li><strong>Salary:</strong> <?= $offer['salary_currency'] ?> <?= number_format($offer['salary']) ?> <?= $offer['employment_type'] ? '/month' : '' ?></li>
                <li><strong>Employment Type:</strong> <?= escape($offer['employment_type']) ?></li>
                <?php if($offer['probation_period']): ?><li><strong>Probation Period:</strong> <?= escape($offer['probation_period']) ?></li><?php endif; ?>
                <?php if($offer['report_to']): ?><li><strong>Reports To:</strong> <?= escape($offer['report_to']) ?></li><?php endif; ?>
            </ul>
        </div>
        
        <?php if($offer['benefits']): ?>
        <div class="mb-4">
            <h3 class="font-bold">Benefits:</h3>
            <p><?= nl2br(escape($offer['benefits'])) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if($offer['terms']): ?>
        <div class="mb-4">
            <h3 class="font-bold">Terms & Conditions:</h3>
            <p><?= nl2br(escape($offer['terms'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="mt-8 pt-6 border-t">
            <p>To accept this offer, please sign and return a copy of this letter.</p>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($offers as $offer): ?>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex justify-between items-start mb-2">
                <span class="px-2 py-1 text-xs rounded-full <?= $offer['status'] === 'accepted' ? 'bg-green-100 text-green-700' : ($offer['status'] === 'sent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>"><?= ucfirst($offer['status']) ?></span>
            </div>
            <h3 class="font-bold text-lg mb-1"><?= escape($offer['candidate_name']) ?></h3>
            <p class="text-sm text-gray-600 mb-2"><?= escape($offer['position']) ?></p>
            <div class="text-xs text-gray-500 mb-4">
                <p><i class="fas fa-envelope mr-1"></i> <?= escape($offer['candidate_email']) ?></p>
                <p><i class="fas fa-calendar mr-1"></i> Join: <?= date('M d, Y', strtotime($offer['joining_date'])) ?></p>
            </div>
            <div class="flex gap-2">
                <?php 
                $printData = [
                    'type' => 'offer_letter',
                    'doc_id' => $offer['offer_number'],
                    'doc_date' => $offer['created_at'],
                    'client_name' => $offer['candidate_name'],
                    'client_designation' => $offer['position'],
                    'client_phone' => $offer['candidate_phone'],
                    'client_address' => '',
                    'subject' => 'Job Offer: ' . $offer['position'],
                    'body' => "Dear {$offer['candidate_name']},\n\nWe are pleased to offer you the position of {$offer['position']} at SohojWeb IT Solutions.\n\nPosition: {$offer['position']}\nDepartment: {$offer['department']}\nJoining Date: " . date('F d, Y', strtotime($offer['joining_date'])) . "\nSalary: {$offer['salary']} BDT/month\n\nPlease sign and return this letter to confirm your acceptance.",
                    'opts' => ['sign' => true, 'note' => true]
                ];
                ?>
                <a href="/sohojweb/print/?type=job_offer&id=<?= $offer['id'] ?>" target="_blank" class="px-3 py-2 text-green-600 hover:bg-green-50 rounded-lg" title="Print"><i class="fas fa-print"></i></a>
                <a href="?action=view&id=<?= $offer['id'] ?>" class="flex-1 text-center px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">View</a>
                <?php if ($canDelete): ?>
                <button onclick="deleteOffer(<?= $offer['id'] ?>)" class="px-3 py-2 text-gray-500 hover:text-red-600"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Modal -->
    <div id="offerModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="border-b px-6 py-4 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold">Create Job Offer Letter</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <form id="offerForm" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Candidate Name *</label>
                        <input type="text" name="candidate_name" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Position *</label>
                        <input type="text" name="position" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email *</label>
                        <input type="email" name="candidate_email" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone</label>
                        <input type="text" name="candidate_phone" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Department</label>
                        <input type="text" name="department" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Joining Date *</label>
                        <input type="date" name="joining_date" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Salary</label>
                        <input type="number" name="salary" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Currency</label>
                        <select name="salary_currency" class="w-full px-3 py-2 border rounded-lg">
                            <option value="BDT">BDT</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Employment Type</label>
                        <select name="employment_type" class="w-full px-3 py-2 border rounded-lg">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Reports To</label>
                        <input type="text" name="report_to" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Working Hours</label>
                        <input type="text" name="working_hours" placeholder="e.g. 9 AM - 6 PM" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Probation Period</label>
                    <input type="text" name="probation_period" placeholder="e.g. 3 months" class="w-full px-3 py-2 border rounded-lg">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Benefits</label>
                    <textarea name="benefits" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Terms & Conditions</label>
                    <textarea name="terms" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border rounded-lg">
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save Offer Letter</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;

function openModal() { document.getElementById('offerModal').classList.remove('hidden'); document.getElementById('offerModal').classList.add('flex'); }
function closeModal() { document.getElementById('offerModal').classList.add('hidden'); document.getElementById('offerModal').classList.remove('flex'); }
document.getElementById('offerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    const formData = new FormData(this);
    const offerId = '<?= $id ?? '' ?>';
    const actionUrl = 'job-offer.php?action=save' + (offerId ? '&id=' + offerId : '');

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
function deleteOffer(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete this offer letter?')) fetch('job-offer.php?action=delete', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'id='+id}).then(() => location.reload());
}
</script>
<?php include __DIR__ . '/../footer.php'; ?>
