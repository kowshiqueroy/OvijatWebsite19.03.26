<?php
/**
 * Add/Edit Employee Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Add Employee';
$currentPage = 'employees';

$employee = [
    'id' => '',
    'office_name' => '',
    'office_code' => '',
    'department' => '',
    'dept_code' => '',
    'unit' => '',
    'position' => '',
    'emp_name' => '',
    'official_phone' => '',
    'personal_phone' => '',
    'nid' => '',
    'dob' => '',
    'blood_group' => '',
    'sex' => '',
    'bank_name' => '',
    'bank_account' => '',
    'basic_salary' => '',
    'pf_percentage' => getSetting('default_pf_percentage', '5.00'),
    
    'joining_date' => date('Y-m-d'),
    'status' => 'Active',
    'photo' => ''
];

$isEdit = false;

$uniqueOffices = [];
$uniqueDepts = [];
$uniqueUnits = [];
$uniquePositions = [];
$uniqueBanks = [];

$conn = getDBConnection();
$result = $conn->query("SELECT DISTINCT office_name, office_code FROM employees WHERE office_name != '' ORDER BY office_name");
while ($row = $result->fetch_assoc()) {
    $uniqueOffices[] = $row;
}

$result = $conn->query("SELECT DISTINCT department, dept_code FROM employees WHERE department != '' ORDER BY department");
while ($row = $result->fetch_assoc()) {
    $uniqueDepts[] = $row;
}

$result = $conn->query("SELECT DISTINCT unit FROM employees WHERE unit IS NOT NULL AND unit != '' ORDER BY unit");
while ($row = $result->fetch_assoc()) {
    $uniqueUnits[] = $row['unit'];
}

$result = $conn->query("SELECT DISTINCT position FROM employees WHERE position != '' ORDER BY position");
while ($row = $result->fetch_assoc()) {
    $uniquePositions[] = $row['position'];
}

$result = $conn->query("SELECT DISTINCT bank_name FROM employees WHERE bank_name IS NOT NULL AND bank_name != '' ORDER BY bank_name");
while ($row = $result->fetch_assoc()) {
    $uniqueBanks[] = $row['bank_name'];
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $existing = getEmployeeById($id);
    if ($existing) {
        $employee = $existing;
        $isEdit = true;
        $pageTitle = 'Edit Employee';
    }
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }
    $conn = getDBConnection();

    $office_name = sanitize($_POST['office_name'] ?? '');
    $office_code = sanitize($_POST['office_code'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $dept_code = sanitize($_POST['dept_code'] ?? '');
    $unit = sanitize($_POST['unit'] ?? '');
    $position = sanitize($_POST['position'] ?? '');
    $emp_name = sanitize($_POST['emp_name'] ?? '');
    $official_phone = sanitize($_POST['official_phone'] ?? '');
    $personal_phone = sanitize($_POST['personal_phone'] ?? '');
    $nid = !empty($_POST['nid']) ? sanitize($_POST['nid']) : '';
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : '';
    $blood_group = sanitize($_POST['blood_group'] ?? '');
    $sex = sanitize($_POST['sex'] ?? '');
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $bank_account = sanitize($_POST['bank_account'] ?? '');
    $basic_salary = (float)($_POST['basic_salary'] ?? 0);
    $pf_percentage = (float)($_POST['pf_percentage'] ?? 5);
    $joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : '';
    $status = sanitize($_POST['status'] ?? 'Active');
    
    $currentPhoto = $employee['photo'] ?? '';
    $selectedPhoto = sanitize($_POST['photo_select'] ?? '');
    
    if (!empty($selectedPhoto)) {
        $photo = $selectedPhoto;
    } else {
        $photo = $currentPhoto;
    }
    
if ($isEdit) {
        $sql = "UPDATE employees SET 
            office_name = ?, office_code = ?, department = ?, dept_code = ?,
            unit = ?, position = ?, emp_name = ?, official_phone = ?,
            personal_phone = ?, nid = ?, dob = ?,
            blood_group = ?, sex = ?, bank_name = ?, bank_account = ?,
            basic_salary = ?, pf_percentage = ?,
            joining_date = ?, status = ?, photo = ?
            WHERE id = ?";
        
        $empId = (int)$employee['id'];
        
$stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssssssssssddi", 
            $office_name, $office_code, $department, $dept_code,
            $unit, $position, $emp_name, $official_phone,
            $personal_phone, $nid, $dob,
            $blood_group, $sex, $bank_name, $bank_account,
            $basic_salary, $pf_percentage,
            $joining_date, $status, $photo, $empId
        );
    } else {
        $sql = "INSERT INTO employees (
            office_name, office_code, department, dept_code,
            unit, position, emp_name, official_phone, personal_phone,
            nid, dob, blood_group, sex, bank_name, bank_account,
            basic_salary, pf_percentage,
            joining_date, status, photo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssssssssssdds",
            $office_name, $office_code, $department, $dept_code,
            $unit, $position, $emp_name, $official_phone,
            $personal_phone, $nid, $dob,
            $blood_group, $sex, $bank_name, $bank_account,
            $basic_salary, $pf_percentage,
            $joining_date, $status, $photo
        );
    }
    
    if ($stmt->execute()) {
        $empId = $isEdit ? (int)$_GET['edit'] : $conn->insert_id;
        $action = $isEdit ? 'update' : 'create';
        
        if ($isEdit) {
            // Check if status changed
            if ($employee['status'] !== $status) {
                logEmploymentHistory($empId, $status, date('Y-m-d'), "Status changed from {$employee['status']} to $status");
            }
            
            $getStmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
            $getStmt->bind_param("i", $empId);
            $getStmt->execute();
            $result = $getStmt->get_result();
            $empData = $result->fetch_assoc();
            $getStmt->close();
            logActivity($action, 'employee', $empId, "Updated: " . sanitize($_POST['emp_name']) . " | Data: " . json_encode($empData));
        } else {
            // Log joining history
            logEmploymentHistory($empId, 'Joined', $joining_date, "Initial joining");
            logActivity($action, 'employee', $empId, "Created: " . sanitize($_POST['emp_name']) . " | ID: $empId");
        }
        
        $message = $isEdit ? 'Employee updated successfully!' : 'Employee added successfully!';
        if (!$isEdit) {
            $newId = $conn->insert_id;
            $stmt->close();
            header('Location: employee-add.php?edit=' . $newId . '&msg=added');
            exit;
        }
    } else {
        $message = 'Error: ' . $conn->error;
        $messageType = 'danger';
    }
    $stmt->close();
}

if (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $message = 'Employee added successfully!';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1"><?php echo $isEdit ? 'Edit Employee' : 'Add New Employee'; ?></h4>
        <small class="text-muted"><?php echo $isEdit ? 'Update employee information' : 'Enter new employee details'; ?></small>
    </div>
    <div>
        <a href="employees.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Basic Information</h5>
        </div>
        <div class="card-body">
        <div class="row g-3">
            <?php if ($isEdit): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <strong>Employee ID:</strong> <?php echo generateEmployeeID($employee['id'], $employee['office_code'], $employee['dept_code']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-4">
                <label class="form-label">Office Name *</label>
                <input type="text" name="office_name" class="form-control" list="officeList" 
                       value="<?php echo htmlspecialchars($employee['office_name']); ?>" required>
                <datalist id="officeList">
                    <?php foreach (getOfficeList() as $off): ?>
                        <option value="<?php echo htmlspecialchars($off['office_name']); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Office Code *</label>
                <input type="text" name="office_code" class="form-control" 
                       value="<?php echo htmlspecialchars($employee['office_code']); ?>" 
                       placeholder="e.g. HQ" required maxlength="10">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Department *</label>
                <input type="text" name="department" class="form-control" list="deptList"
                       value="<?php echo htmlspecialchars($employee['department']); ?>" required>
                <datalist id="deptList">
                    <?php foreach (getDepartmentList() as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Dept Code *</label>
                <input type="text" name="dept_code" class="form-control" 
                       value="<?php echo htmlspecialchars($employee['dept_code']); ?>" 
                       placeholder="e.g. IT" required maxlength="10">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" class="form-control" list="unitList"
                       value="<?php echo htmlspecialchars($employee['unit'] ?? ''); ?>">
                <datalist id="unitList">
                    <?php foreach (getUnitList() as $unit): ?>
                        <option value="<?php echo htmlspecialchars($unit); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Position *</label>
                <input type="text" name="position" class="form-control" list="posList"
                       value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                <datalist id="posList">
                    <?php foreach (getPositionList() as $pos): ?>
                        <option value="<?php echo htmlspecialchars($pos); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            
            
            <div class="col-md-4">
                <label class="form-label">Status *</label>
                <select name="status" class="form-select" required>
                    <option value="Active" <?php echo $employee['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $employee['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Resigned" <?php echo $employee['status'] === 'Resigned' ? 'selected' : ''; ?>>Resigned</option>
                    <option value="Terminated" <?php echo $employee['status'] === 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person me-2"></i>Personal Details</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="emp_name" class="form-control" 
                           value="<?php echo htmlspecialchars($employee['emp_name']); ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Official Phone</label>
                    <input type="tel" name="official_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($employee['official_phone'] ?? ''); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Personal Phone</label>
                    <input type="tel" name="personal_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($employee['personal_phone'] ?? ''); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Photo</label>
                    <select name="photo_select" id="photoSelect" class="form-select" onchange="updatePhotoPreview()">
                        <option value="">-- Select Photo --</option>
                        <?php
                        $uploadDir = __DIR__ . '/../uploads/photos/';
                        $existingPhotos = glob($uploadDir . '*.*') ?: [];
                        rsort($existingPhotos);
                        // Load all employee-photo mappings in one query
                        $photoEmployeeMap = [];
                        if (!empty($existingPhotos)) {
                            $allPhotoNames = array_map('basename', $existingPhotos);
                            $placeholders = implode(',', array_fill(0, count($allPhotoNames), '?'));
                            $mapStmt = $conn->prepare("SELECT id, emp_name, office_code, dept_code, photo FROM employees WHERE photo IN ($placeholders)");
                            $mapTypes = str_repeat('s', count($allPhotoNames));
                            $mapStmt->bind_param($mapTypes, ...$allPhotoNames);
                            $mapStmt->execute();
                            $mapResult = $mapStmt->get_result();
                            while ($mapRow = $mapResult->fetch_assoc()) {
                                $photoEmployeeMap[$mapRow['photo']] = $mapRow;
                            }
                            $mapStmt->close();
                        }
                        foreach ($existingPhotos as $photo):
                            $photoName = basename($photo);
                            $empInfo = $photoEmployeeMap[$photoName] ?? null;
                            $displayLabel = $photoName;
                            if ($empInfo) {
                                $empID = generateEmployeeID($empInfo['id'], $empInfo['office_code'], $empInfo['dept_code']);
                                $displayLabel = $empID . ' - ' . $empInfo['emp_name'];
                            }
                        ?>
                            <option value="<?php echo htmlspecialchars($photoName); ?>"
                                <?php echo ($employee['photo'] ?? '') === $photoName ? 'selected' : ''; ?>
                                data-preview="<?php echo htmlspecialchars($photoName); ?>">
                                <?php echo htmlspecialchars($displayLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        <a href="photo-upload.php" target="_blank">Upload new photos here</a>
                    </small>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <?php if (!empty($employee['photo']) && file_exists($uploadDir . $employee['photo'])): ?>
                            <img src="../uploads/photos/<?php echo htmlspecialchars($employee['photo']); ?>" 
                                 id="photoPreview" class="profile-img" style="width: 80px; height: 80px;">
                        <?php else: ?>
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' fill='%23ccc'%3E%3Crect width='100%25' height='100%25'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23aaa'%3ENo Photo%3C/text%3E%3C/svg%3E" 
                                 id="photoPreview" class="profile-img" style="width: 80px; height: 80px;">
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">NID Number</label>
                    <input type="text" name="nid" class="form-control" 
                           value="<?php echo htmlspecialchars($employee['nid'] ?? ''); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" id="dob" class="form-control" 
                           value="<?php echo $employee['dob']; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input type="text" id="age" class="form-control" 
                           value="<?php echo calculateAge($employee['dob']); ?>" readonly>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sex</label>
                    <select name="sex" class="form-select">
                        <option value="">Select</option>
                        <option value="Male" <?php echo ($employee['sex'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($employee['sex'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($employee['sex'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">Select</option>
                        <option value="A+" <?php echo ($employee['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo ($employee['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo ($employee['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo ($employee['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                        <option value="O+" <?php echo ($employee['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo ($employee['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                        <option value="AB+" <?php echo ($employee['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo ($employee['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" 
                           value="<?php echo $employee['joining_date']; ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-bank me-2"></i>Bank & Salary Details</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" list="bankList"
                           value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>">
                    <datalist id="bankList">
                        <?php foreach ($uniqueBanks as $bank): ?>
                            <option value="<?php echo htmlspecialchars($bank); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Bank Account Number</label>
                    <input type="text" name="bank_account" class="form-control" 
                           value="<?php echo htmlspecialchars($employee['bank_account'] ?? ''); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Basic Salary *</label>
                    <input type="number" name="basic_salary" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($employee['basic_salary']); ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">PF Percentage (%)</label>
                    <input type="number" name="pf_percentage" class="form-control" step="0.01" min="0" max="100"
                           value="<?php echo htmlspecialchars($employee['pf_percentage']); ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mt-4 border-primary">
        <div class="card-body">
            <div class="d-flex gap-2 justify-content-end">
                <a href="employees.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> <?php echo $isEdit ? 'Update Employee' : 'Add Employee'; ?>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const officeNames = <?php echo json_encode($uniqueOffices); ?>;
    const deptNames = <?php echo json_encode($uniqueDepts); ?>;
    
    const officeNameInput = document.querySelector('input[name="office_name"]');
    const officeCodeInput = document.querySelector('input[name="office_code"]');
    const deptInput = document.querySelector('input[name="department"]');
    const deptCodeInput = document.querySelector('input[name="dept_code"]');
    
    if (officeNameInput && officeCodeInput) {
        officeNameInput.addEventListener('change', function() {
            const selected = officeNames.find(o => o.office_name === this.value);
            if (selected && !officeCodeInput.value) {
                officeCodeInput.value = selected.office_code;
            }
        });
    }
    
    if (deptInput && deptCodeInput) {
        deptInput.addEventListener('change', function() {
            const selected = deptNames.find(d => d.department === this.value);
            if (selected && !deptCodeInput.value) {
                deptCodeInput.value = selected.dept_code;
            }
        });
    }
    
    updatePhotoPreview();
});

function updatePhotoPreview() {
    const select = document.getElementById('photoSelect');
    const preview = document.getElementById('photoPreview');
    const selectedValue = select.value;
    
    if (selectedValue) {
        preview.src = '../uploads/photos/' + selectedValue;
        preview.style.display = 'block';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
