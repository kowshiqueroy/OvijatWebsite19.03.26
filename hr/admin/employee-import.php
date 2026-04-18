<?php
/**
 * CSV Import Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Import Employees';
$currentPage = 'employees';

$message = '';
$messageType = 'success';
$importResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $conn = getDBConnection();
    
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error uploading file';
        $messageType = 'danger';
    } else {
        $filePath = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            $message = 'Cannot read file';
            $messageType = 'danger';
        } else {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                $message = 'Empty file or invalid format';
                $messageType = 'danger';
            } else {
                $headers = array_map('trim', $headers);
                $headerMap = [
                    'office_name' => -1, 'office_code' => -1, 'department' => -1, 'dept_code' => -1,
                    'unit' => -1, 'position' => -1, 'emp_name' => -1, 'nid' => -1,
                    'dob' => -1, 'blood_group' => -1, 'sex' => -1, 'bank_name' => -1,
                    'bank_account' => -1, 'basic_salary' => -1, 'pf_percentage' => -1,
                    'joining_date' => -1, 'status' => -1
                ];
                
                foreach ($headers as $index => $header) {
                    $headerLower = strtolower(trim($header));
                    if (isset($headerMap[$headerLower])) {
                        $headerMap[$headerLower] = $index;
                    }
                }
                
                $requiredFields = ['office_name', 'department', 'position', 'emp_name'];
                $missingFields = [];
                foreach ($requiredFields as $field) {
                    if ($headerMap[$field] === -1) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    $message = 'Missing required columns: ' . implode(', ', $missingFields);
                    $messageType = 'danger';
                } else {
                    $rowNumber = 1;
                    $successCount = 0;
                    $errorCount = 0;
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNumber++;
                        
                        if (count($row) < count($headers)) {
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'error',
                                'message' => 'Insufficient columns'
                            ];
                            $errorCount++;
                            continue;
                        }
                        
                        $data = [];
                        foreach ($headerMap as $field => $index) {
                            if ($index !== -1 && isset($row[$index])) {
                                $data[$field] = trim($row[$index]);
                            } else {
                                $data[$field] = '';
                            }
                        }
                        
                        if (empty($data['emp_name']) || empty($data['department']) || empty($data['position'])) {
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'error',
                                'message' => 'Missing required fields'
                            ];
                            $errorCount++;
                            continue;
                        }
                        
                        $basicSalary = !empty($data['basic_salary']) ? (float)$data['basic_salary'] : 0;
                        $pfPercentage = !empty($data['pf_percentage']) ? (float)$data['pf_percentage'] : 5;
                        
                        $status = 'Active';
                        $statusList = ['Active', 'Inactive', 'Resigned', 'Terminated'];
                        if (!empty($data['status']) && in_array(ucfirst($data['status']), $statusList)) {
                            $status = ucfirst($data['status']);
                        }
                        
                        $dob = null;
                        if (!empty($data['dob'])) {
                            $ts = strtotime($data['dob']);
                            if ($ts !== false && $ts > 0) {
                                [$y, $m, $d] = explode('-', date('Y-m-d', $ts));
                                if (checkdate((int)$m, (int)$d, (int)$y)) {
                                    $dob = "$y-$m-$d";
                                }
                            }
                        }
                        $joiningDate = date('Y-m-d');
                        if (!empty($data['joining_date'])) {
                            $ts = strtotime($data['joining_date']);
                            if ($ts !== false && $ts > 0) {
                                [$y, $m, $d] = explode('-', date('Y-m-d', $ts));
                                if (checkdate((int)$m, (int)$d, (int)$y)) {
                                    $joiningDate = "$y-$m-$d";
                                }
                            }
                        }
                        
                        $sql = "INSERT INTO employees (
                            office_name, office_code, department, dept_code,
                            unit, position, emp_name, nid, dob,
                            blood_group, sex, bank_name, bank_account,
                            basic_salary, pf_percentage,
                            joining_date, status, photo
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $photo = null;
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssssssssssssddsss", 
                            $data['office_name'], $data['office_code'], $data['department'], $data['dept_code'],
                            $data['unit'], $data['position'], $data['emp_name'], $data['nid'], $dob,
                            $data['blood_group'], $data['sex'], $data['bank_name'], $data['bank_account'],
                            $basicSalary, $pfPercentage,
                            $joiningDate, $status, $photo
                        );
                        
                        try {
                            if ($stmt->execute()) {
                                $successCount++;
                                $importResults[] = [
                                    'row' => $rowNumber,
                                    'status' => 'success',
                                    'message' => 'Imported: ' . htmlspecialchars($data['emp_name'])
                                ];
                            }
                        } catch (Exception $e) {
                            $errorCount++;
                            $errMsg = $e->getMessage();
                            if (strpos($errMsg, 'Duplicate entry') !== false) {
                                $errMsg = 'Duplicate NID: ' . htmlspecialchars($data['nid']);
                            }
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'error',
                                'message' => $errMsg
                            ];
                        }
                        $stmt->close();
                    }
                    
                    $message = "Import completed: $successCount successful, $errorCount errors";
                    $messageType = $errorCount > 0 ? 'warning' : 'success';
                }
            }
            fclose($handle);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Import Employees</h4>
        <small class="text-muted">Bulk upload employees from CSV file</small>
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

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Upload CSV</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload me-1"></i> Import
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>CSV Format</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">The CSV file should have the following columns (headers are case-insensitive):</p>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Column</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>office_name</td><td>Yes</td><td>Office/Branch name</td></tr>
                            <tr><td>office_code</td><td>No</td><td>Short code (e.g. HQ)</td></tr>
                            <tr><td>department</td><td>Yes</td><td>Department name</td></tr>
                            <tr><td>dept_code</td><td>No</td><td>Department short code</td></tr>
                            <tr><td>unit</td><td>No</td><td>Unit/Section name</td></tr>
                            <tr><td>position</td><td>Yes</td><td>Job title</td></tr>
                            <tr><td>emp_name</td><td>Yes</td><td>Full name</td></tr>
                            <tr><td>nid</td><td>No</td><td>National ID</td></tr>
                            <tr><td>dob</td><td>No</td><td>Date of birth (YYYY-MM-DD)</td></tr>
                            <tr><td>blood_group</td><td>No</td><td>A+, A-, B+, B-, O+, O-, AB+, AB-</td></tr>
                            <tr><td>sex</td><td>No</td><td>Male, Female, Other</td></tr>
                            <tr><td>bank_name</td><td>No</td><td>Bank name</td></tr>
                            <tr><td>bank_account</td><td>No</td><td>Account number</td></tr>
                            <tr><td>basic_salary</td><td>No</td><td>Numeric value</td></tr>
                            <tr><td>pf_percentage</td><td>No</td><td>PF % (default: 5)</td></tr>
                            <tr><td>joining_date</td><td>No</td><td>Date (YYYY-MM-DD)</td></tr>
                            <tr><td>status</td><td>No</td><td>Active, Inactive, Resigned, Terminated</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="#" onclick="downloadSampleCSV(); return false;" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i> Download Sample CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Import Results</h5>
            </div>
            <div class="card-body">
                <?php if (empty($importResults)): ?>
                    <p class="text-muted text-center py-4">No imports yet. Upload a CSV file to see results.</p>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Row</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($importResults as $result): ?>
                                    <tr>
                                        <td><?php echo $result['row']; ?></td>
                                        <td>
                                            <?php if ($result['status'] === 'success'): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function downloadSampleCSV() {
    const csvContent = `office_name,office_code,department,dept_code,unit,position,emp_name,nid,dob,blood_group,sex,bank_name,bank_account,basic_salary,pf_percentage,joining_date,status
Head Office,HQ,Information Technology,IT,Development,Software Engineer,John Doe,123456789,1990-05-15,A+,Male,City Bank,1234567890,50000,5,Staff,2020-01-15,Active
Head Office,HQ,Human Resources,HR,Recruitment,HR Manager,Jane Smith,987654321,1985-08-20,B+,Female,Standard Bank,0987654321,60000,5,Staff,2019-03-10,Active
Factory,FAC,Production,PROD,Assembly,Production Worker,Bob Wilson,456123789,1995-03-10,O+,Male,Workers Bank,5555555555,25000,5,Worker,2021-06-01,Active
Head Office,HQ,Marketing,MKT,Digital,Marketing Intern,Alice Brown,789123456,2000-12-01,A-,Female,,,,15000,0,Intern,2023-01-15,Active`;
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'employee_sample.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
