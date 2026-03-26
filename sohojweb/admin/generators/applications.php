<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit   = hasPermission('editor');
$canDelete = hasPermission('super_admin');

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'create')) {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('editor')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            throw new Exception('Security token mismatch. Please refresh and try again.');
        }
        $type = sanitize($_POST['form_type'] ?? 'formal_letter');
        // Map frontend template types to DB ENUM types (actual type stored in form_data)
        if ($type === 'experience_certificate') $dbType = 'experience';
        elseif ($type === 'recommendation') $dbType = 'recommendation';
        else $dbType = 'general';
        
        $formData = [
            'form_title' => sanitize($_POST['form_title']),
            'form_type' => $dbType,
            'applicant_name' => sanitize($_POST['recipient_name']),
            'applicant_email' => sanitize($_POST['recipient_email']) ?: null,
            'applicant_phone' => sanitize($_POST['recipient_phone']) ?: null,
            'department' => sanitize($_POST['recipient_designation']) ?: null,
            'form_data' => json_encode([
                'content' => $_POST['letter_content'] ?? '', // Don't sanitize raw HTML/newlines too aggressively here
                'recipient_company' => sanitize($_POST['recipient_company'] ?? ''),
                'additional_info' => sanitize($_POST['additional_info'] ?? ''),
                'template_type' => $type // Store original template type
            ]),
            'status' => 'pending',
            'approver_notes' => sanitize($_POST['notes'] ?? '') ?: null,
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        if ($id) {
            db()->update('application_forms', $formData, 'id = :id', ['id' => $id]);
            jsonResponse(['success' => true, 'message' => 'Document updated', 'id' => $id]);
        } else {
            $newId = db()->insert('application_forms', $formData);
            jsonResponse(['success' => true, 'message' => 'Document created', 'id' => $newId]);
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
        db()->delete('application_forms', 'id = :id', ['id' => (int)$_POST['id']]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'view' && $id) {
    $app = db()->selectOne("SELECT * FROM application_forms WHERE id = ?", [$id]);
}

$applications = db()->select("SELECT * FROM application_forms ORDER BY created_at DESC");

$letterTypeLabels = [
    'offer_letter'           => 'Offer Letter',
    'experience_certificate' => 'Experience Certificate',
    'recommendation'         => 'Recommendation Letter',
    'termination'            => 'Termination Letter',
    'promotion'              => 'Promotion Letter',
    'greeting'               => 'Greeting Letter',
    'formal_letter'          => 'Formal Letter',
    'notice'                 => 'Notice',
    'memo'                   => 'Memo',
    'other'                  => 'Other',
    // DB enum fallbacks
    'experience'             => 'Experience Certificate',
    'leave'                  => 'Leave Letter',
    'general'                => 'General Letter',
];

$pageTitle = 'Outgoing Letters | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Outgoing Letters</h1>
            <p class="text-gray-500 text-sm">Create & issue letters from the company</p>
        </div>
        <?php if ($canEdit): ?>
        <button onclick="openModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> New Letter
        </button>
        <?php endif; ?>
    </div>

    <?php if ($action === 'view' && $app): ?>
    <div class="mb-4"><a href="applications.php" class="text-blue-600 hover:underline">&larr; Back to Letters</a></div>
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl mx-auto">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-xl font-bold"><?= escape($app['form_title']) ?></h2>
                <p class="text-gray-600"><?= escape($app['applicant_name']) ?></p>
            </div>
            <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-700">Issued</span>
        </div>
        
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-2">RECIPIENT INFO</h3>
                <p><strong>Name:</strong> <?= escape($app['applicant_name']) ?></p>
                <p><strong>Email:</strong> <?= escape($app['applicant_email']) ?></p>
                <p><strong>Phone:</strong> <?= escape($app['applicant_phone']) ?></p>
                <p><strong>Designation:</strong> <?= escape($app['department']) ?></p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-500 text-sm mb-2">LETTER DETAILS</h3>
                <?php $viewFormData = json_decode($app['form_data'] ?? '{}', true);
                      $viewType = $letterTypeLabels[$viewFormData['template_type'] ?? ''] ?? $letterTypeLabels[$app['form_type']] ?? ucwords(str_replace('_', ' ', $app['form_type'])); ?>
                <p><strong>Type:</strong> <?= escape($viewType) ?></p>
                <p><strong>Date:</strong> <?= date('F d, Y', strtotime($app['created_at'])) ?></p>
            </div>
        </div>
        
        <?php $formData = json_decode($app['form_data'], true); ?>
        <?php if(!empty($formData['content'])): ?>
        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <h3 class="font-semibold text-gray-500 text-sm mb-2">LETTER CONTENT</h3>
            <p class="whitespace-pre-wrap"><?= nl2br(escape($formData['content'])) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($formData['additional_info'])): ?>
        <div class="mb-4">
            <h3 class="font-semibold text-gray-500 text-sm mb-2">Additional Info</h3>
            <p><?= nl2br(escape($formData['additional_info'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="mt-6 pt-4 border-t">
            <a href="/sohojweb/print/?type=letter&id=<?= $app['id'] ?>" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-print mr-2"></i> Print Letter
            </a>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Title</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Recipient</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Type</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Date</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <?php $formData = json_decode($app['form_data'] ?? '{}', true);
                      $displayType = $letterTypeLabels[$formData['template_type'] ?? ''] ?? $letterTypeLabels[$app['form_type']] ?? ucwords(str_replace('_', ' ', $app['form_type'])); ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4 font-medium"><?= escape($app['form_title']) ?></td>
                    <td class="py-3 px-4 text-gray-600"><?= escape($app['applicant_name']) ?></td>
                    <td class="py-3 px-4 text-gray-600"><?= escape($displayType) ?></td>
                    <td class="py-3 px-4 text-gray-500 text-sm"><?= date('M d, Y', strtotime($app['created_at'])) ?></td>
                    <td class="py-3 px-4">
                        <a href="?action=view&id=<?= $app['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-3" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/sohojweb/print/?type=letter&id=<?= $app['id'] ?>" target="_blank" class="text-green-600 hover:text-green-800 mr-3" title="Print">
                            <i class="fas fa-print"></i>
                        </a>
                        <?php if ($canDelete): ?>
                        <a href="#" onclick="deleteDoc(<?= $app['id'] ?>)" class="text-red-500 hover:text-red-700" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="5" class="py-8 text-center text-gray-500">No letters created yet. Click "New Letter" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <!-- Create/Edit Modal -->
    <div id="letterModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold">Create New Letter</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="letterForm" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" id="docId">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Letter Title *</label>
                        <input type="text" name="form_title" id="formTitle" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="e.g., Job Offer for John Doe">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Letter Type</label>
                        <select name="form_type" id="formType" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="offer_letter">Offer Letter</option>
                            <option value="experience_certificate">Experience Certificate</option>
                            <option value="recommendation">Recommendation Letter</option>
                            <option value="termination">Termination Letter</option>
                            <option value="promotion">Promotion Letter</option>
                            <option value="greeting">Greeting Letter</option>
                            <option value="formal_letter">Formal Letter</option>
                            <option value="notice">Notice</option>
                            <option value="memo">Memo</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Name *</label>
                        <input type="text" name="recipient_name" id="recipientName" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Full Name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                        <input type="text" name="recipient_designation" id="recipientDesignation" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Job Title">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="recipient_email" id="recipientEmail" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="email@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="recipient_phone" id="recipientPhone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="01XXXXXXXXX">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company/Organization</label>
                    <input type="text" name="recipient_company" id="recipientCompany" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Company Name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Letter Content *</label>
                    <textarea name="letter_content" id="letterContent" required rows="8" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm" placeholder="Write the letter content here..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                    <textarea name="additional_info" id="additionalInfo" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Internal notes (not printed)"></textarea>
                </div>
                
                <div class="flex justify-between pt-4">
                    <button type="button" onclick="loadDemo()" class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-magic mr-1"></i> Load Demo
                    </button>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create Letter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;

    const demoTemplates = {
        offer_letter: {
            form_title: "Job Offer Letter for [Name]",
            recipient_name: "John Doe",
            recipient_designation: "Software Engineer",
            recipient_email: "john.doe@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Tech Solutions Ltd.",
            letter_content: `Dear [Name],

We are pleased to offer you the position of [Designation] at [Company Name].

Job Details:
- Position: [Designation]
- Department: [Department]
- Salary: [Amount] BDT/month
- Joining Date: [Date]
- Work Schedule: [Full-time/Part-time]

Benefits:
- Weekly holidays
- Festival bonus
- Annual leave
- Medical allowance

Please sign and return this letter within 7 days to confirm your acceptance.

We look forward to working with you!

Best regards,
[Your Name]
[Your Position]
[Company Name]`
        },
        experience_certificate: {
            form_title: "Experience Certificate for [Name]",
            recipient_name: "John Doe",
            recipient_designation: "Software Developer",
            recipient_email: "john.doe@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Previous Company Ltd.",
            letter_content: `TO WHOM IT MAY CONCERN

This is to certify that [Name] has been employed at [Company Name] from [Start Date] to [End Date].

During their employment, [Name] served as [Designation] and was responsible for:
- [Duty 1]
- [Duty 2]
- [Duty 3]

[Name] has demonstrated professionalism, dedication, and strong technical skills throughout their tenure. They have been a valuable team member and maintained excellent relationships with colleagues and clients.

We wish them all the best in their future endeavors.

Sincerely,
[Your Name]
[Your Position]
[Company Name]`
        },
        recommendation: {
            form_title: "Recommendation Letter for [Name]",
            recipient_name: "John Doe",
            recipient_designation: "Former Employee",
            recipient_email: "john.doe@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Previous Company Ltd.",
            letter_content: `TO WHOM IT MAY CONCERN

I am writing to recommend [Name] for any future employment opportunities.

I had the pleasure of working with [Name] at [Company Name] for [Duration]. During this time, [Name] consistently demonstrated:

- Strong technical expertise in [Skills]
- Excellent problem-solving abilities
- Great communication and teamwork
- Professional attitude and reliability

[Name] would be a valuable asset to any organization. I highly recommend them without any hesitation.

Please feel free to contact me if you need any further information.

Best regards,
[Your Name]
[Your Position]
[Company Name]`
        },
        termination: {
            form_title: "Termination Letter for [Name]",
            recipient_name: "Employee Name",
            recipient_designation: "Position",
            recipient_email: "employee@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Company Name",
            letter_content: `Dear [Name],

This letter formally confirms the termination of your employment at [Company Name], effective [Date].

Reason for Termination:
[Brief reason - performance / conduct / redundancy / etc.]

You are required to:
1. Return all company property
2. Complete exit formalities
3. Clear any outstanding dues

Your final salary and benefits will be processed according to company policy within [Time Period].

We appreciate your contributions during your tenure and wish you success in your future endeavors.

Regards,
[Your Name]
[Your Position]
[Company Name]`
        },
        promotion: {
            form_title: "Promotion Letter for [Name]",
            recipient_name: "Employee Name",
            recipient_designation: "Senior Developer",
            recipient_email: "employee@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Company Name",
            letter_content: `Dear [Name],

Congratulations! We are pleased to inform you that you have been promoted to [New Position] at [Company Name], effective [Date].

Your new details:
- New Position: [New Designation]
- New Salary: [Amount] BDT/month
- Reporting To: [Manager Name]

This promotion recognizes your outstanding performance, dedication, and contributions to the company. Your skills and leadership have been instrumental in [Achievements].

We believe you will continue to excel in your new role and contribute to the company's growth.

Congratulations once again!

Best regards,
[Your Name]
[Your Position]
[Company Name]`
        },
        greeting: {
            form_title: "Greeting Letter - [Occasion]",
            recipient_name: "Recipient Name",
            recipient_designation: "Designation",
            recipient_email: "recipient@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Company Name",
            letter_content: `Dear [Name],

On behalf of [Company Name], I would like to extend our warmest greetings to you on the occasion of [Occasion - e.g., New Year, Eid, Christmas, Anniversary].

[Company Name] values your partnership and support. We look forward to continuing our successful relationship in the coming year.

May this [occasion] bring you joy, prosperity, and success.

Warm regards,
[Your Name]
[Your Position]
[Company Name]`
        },
        formal_letter: {
            form_title: "Formal Letter - [Subject]",
            recipient_name: "Recipient Name",
            recipient_designation: "Designation",
            recipient_email: "recipient@email.com",
            recipient_phone: "017XXXXXXXX",
            recipient_company: "Company Name",
            letter_content: `Dear [Name],

I am writing to inform you regarding [Subject].

[Describe the matter in detail]

Please feel free to contact us if you require any further information or clarification.

Thank you for your attention to this matter.

Yours sincerely,
[Your Name]
[Your Position]
[Company Name]`
        },
        notice: {
            form_title: "Notice - [Subject]",
            recipient_name: "All Employees",
            recipient_designation: "",
            recipient_email: "",
            recipient_phone: "",
            recipient_company: "",
            letter_content: `NOTICE

Subject: [Notice Title]

Date: [Date]

This is to notify all employees that [Notice Content].

Please take necessary action accordingly.

For any queries, please contact [Contact Person/Department].

Regards,
[Your Name]
[Your Position]
[Company Name]`
        },
        memo: {
            form_title: "Internal Memo - [Subject]",
            recipient_name: "Team/Department",
            recipient_designation: "",
            recipient_email: "",
            recipient_phone: "",
            recipient_company: "",
            letter_content: `MEMORANDUM

To: [Recipient/Team]
From: [Your Name]
Date: [Date]
Subject: [Subject]

[Main content of the memo]

Please [action required].

Thank you.`
        }
    };

    function openModal() {
        document.getElementById('letterModal').classList.remove('hidden');
        document.getElementById('letterForm').reset();
        document.getElementById('docId').value = '';
    }
    
    function closeModal() {
        document.getElementById('letterModal').classList.add('hidden');
    }
    
    function loadDemo() {
        const type = document.getElementById('formType').value;
        const template = demoTemplates[type] || demoTemplates.formal_letter;
        
        document.getElementById('formTitle').value = template.form_title;
        document.getElementById('recipientName').value = template.recipient_name;
        document.getElementById('recipientDesignation').value = template.recipient_designation;
        document.getElementById('recipientEmail').value = template.recipient_email;
        document.getElementById('recipientPhone').value = template.recipient_phone;
        document.getElementById('recipientCompany').value = template.recipient_company;
        document.getElementById('letterContent').value = template.letter_content;
    }
    
    document.getElementById('formType').addEventListener('change', function() {
        if(document.getElementById('letterContent').value === '') {
            loadDemo();
        }
    });
    
    document.getElementById('letterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

        const formData = new FormData(this);
        fetch('applications.php?action=save' + (document.getElementById('docId').value ? '&id=' + document.getElementById('docId').value : ''), {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
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
    
    function deleteDoc(id) {
        if (!CAN_DELETE) { alert('Permission denied'); return; }
        if (confirm('Are you sure you want to delete this letter?')) {
            fetch('?action=delete', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
            });
        }
    }
    </script>
</body>
</html>
