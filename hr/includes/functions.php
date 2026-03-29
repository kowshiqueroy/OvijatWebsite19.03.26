<?php
/**
 * Core Functions File
 * Core PHP Employee Management System
 */

require_once __DIR__ . '/../config/db.php';

function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    
    return $age;
}

function generateEmployeeID($id, $officeCode, $deptCode) {
    return $officeCode . '-' . $deptCode . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

function resizeAndCompressImage($sourcePath, $targetPath, $maxWidth = 600, $maxHeight = 600, $quality = 70) {
    $imageInfo = getimagesize($sourcePath);
    
    if ($imageInfo === false) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    
    if ($ratio < 1) {
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    switch ($mimeType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    $fileSize = 0;
    $attempts = 0;
    $success = false;
    
    while (!$success && $attempts < 10) {
        switch ($mimeType) {
            case 'image/jpeg':
                $success = imagejpeg($thumb, $targetPath, $quality);
                break;
            case 'image/png':
                $success = imagepng($thumb, $targetPath, 9 - (int)($quality / 15));
                break;
            case 'image/gif':
                $success = imagegif($thumb, $targetPath);
                break;
        }
        
        if ($success) {
            $fileSize = filesize($targetPath);
            if ($fileSize > 512000 && $quality > 20) {
                $quality -= 10;
                $success = false;
            }
        }
        $attempts++;
    }
    
    imagedestroy($source);
    imagedestroy($thumb);
    
    return $success ? $targetPath : false;
}

function getOfficeList() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT DISTINCT office_name, office_code FROM employees ORDER BY office_name");
    $offices = [];
    while ($row = $result->fetch_assoc()) {
        $offices[] = $row;
    }
    return $offices;
}

function getDepartmentList($office = '') {
    $conn = getDBConnection();
    if ($office) {
        $stmt = $conn->prepare("SELECT DISTINCT department, dept_code FROM employees WHERE office_name = ? ORDER BY department");
        $stmt->bind_param("s", $office);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT DISTINCT department, dept_code FROM employees ORDER BY department");
    }
    $depts = [];
    while ($row = $result->fetch_assoc()) {
        $depts[] = $row;
    }
    return $depts;
}

function getUnitList($department = '') {
    $conn = getDBConnection();
    if ($department) {
        $stmt = $conn->prepare("SELECT DISTINCT unit FROM employees WHERE department = ? AND unit IS NOT NULL AND unit != '' ORDER BY unit");
        $stmt->bind_param("s", $department);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT DISTINCT unit FROM employees WHERE unit IS NOT NULL AND unit != '' ORDER BY unit");
    }
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row['unit'];
    }
    return $units;
}

function getPositionList($department = '') {
    $conn = getDBConnection();
    if ($department) {
        $stmt = $conn->prepare("SELECT DISTINCT position FROM employees WHERE department = ? ORDER BY position");
        $stmt->bind_param("s", $department);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT DISTINCT position FROM employees ORDER BY position");
    }
    $positions = [];
    while ($row = $result->fetch_assoc()) {
        $positions[] = $row['position'];
    }
    return $positions;
}

function getAllEmployees($filter = []) {
    $conn = getDBConnection();
    
    $sql = "SELECT * FROM employees WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($filter['office'])) {
        $sql .= " AND office_name = ?";
        $params[] = $filter['office'];
        $types .= "s";
    }
    
    if (!empty($filter['department'])) {
        $sql .= " AND department = ?";
        $params[] = $filter['department'];
        $types .= "s";
    }
    
    if (!empty($filter['unit'])) {
        $sql .= " AND unit = ?";
        $params[] = $filter['unit'];
        $types .= "s";
    }
    
    if (!empty($filter['position'])) {
        $sql .= " AND position = ?";
        $params[] = $filter['position'];
        $types .= "s";
    }
    
    if (!empty($filter['employee_type'])) {
        $sql .= " AND employee_type = ?";
        $params[] = $filter['employee_type'];
        $types .= "s";
    }
    
    if (!empty($filter['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filter['status'];
        $types .= "s";
    }
    
    $sql .= " ORDER BY id DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    
    return $employees;
}

function getEmployeeById($id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    return null;
}

function getEmployeeByPublicId($publicId) {
    $conn = getDBConnection();
    
    if (preg_match('/^([A-Za-z]+)-([A-Za-z]+)-(\d+)$/', $publicId, $matches)) {
        $officeCode = strtoupper($matches[1]);
        $deptCode = strtoupper($matches[2]);
        $id = (int)$matches[3];
        
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? AND office_code = ? AND dept_code = ?");
        $stmt->bind_param("iss", $id, $officeCode, $deptCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
    }
    
    if (is_numeric($publicId)) {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->bind_param("i", $publicId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
    }
    
    return null;
}

function calculatePFBalance($employeeId) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT SUM(pf_deduction) as total_pf 
        FROM salary_sheets 
        WHERE employee_id = ?
    ");
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total_pf'] ? $row['total_pf'] : 0;
}

function getSalarySheets($employeeId = null, $month = null, $filter = []) {
    $conn = getDBConnection();
    
    $sql = "SELECT ss.*, e.emp_name, e.office_name, e.department, e.position, e.office_code, e.dept_code 
            FROM salary_sheets ss 
            JOIN employees e ON ss.employee_id = e.id 
            WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($employeeId) {
        $sql .= " AND ss.employee_id = ?";
        $params[] = $employeeId;
        $types .= "i";
    }
    
    if ($month) {
        $sql .= " AND ss.month = ?";
        $params[] = $month;
        $types .= "s";
    }
    
    if (!empty($filter['office'])) {
        $sql .= " AND e.office_name = ?";
        $params[] = $filter['office'];
        $types .= "s";
    }
    
    if (!empty($filter['department'])) {
        $sql .= " AND e.department = ?";
        $params[] = $filter['department'];
        $types .= "s";
    }
    
    if (!empty($filter['unit'])) {
        $sql .= " AND e.unit = ?";
        $params[] = $filter['unit'];
        $types .= "s";
    }
    
    if (!empty($filter['position'])) {
        $sql .= " AND e.position = ?";
        $params[] = $filter['position'];
        $types .= "s";
    }
    
    $sql .= " ORDER BY ss.month DESC, e.emp_name ASC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $sheets = [];
    while ($row = $result->fetch_assoc()) {
        $sheets[] = $row;
    }
    
    return $sheets;
}

function calculateSalary($basicSalary, $workingDays, $presentDays, $leaveDays, $pfPercentage) {
    $dailyRate = $basicSalary / $workingDays;
    $payableDays = $presentDays + $leaveDays;
    $grossSalary = $dailyRate * $payableDays;
    $pfDeduction = ($grossSalary * $pfPercentage) / 100;
    $netPayable = $grossSalary - $pfDeduction;
    
    return [
        'daily_rate' => $dailyRate,
        'gross_salary' => $grossSalary,
        'pf_deduction' => $pfDeduction,
        'net_payable' => $netPayable
    ];
}

function generateMonthOptions($monthsBack = 24) {
    $options = [];
    $current = new DateTime();
    
    for ($i = 0; $i < $monthsBack; $i++) {
        $month = $current->format('Y-m');
        $options[] = [
            'value' => $month,
            'label' => $current->format('F Y')
        ];
        $current->modify('-1 month');
    }
    
    return $options;
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminLogin($username, $password) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_username'] = $row['username'];
            return true;
        }
    }
    return false;
}

function adminLogout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

function showAlert($message, $type = 'success') {
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}

function formatCurrency($amount) {
    return number_format($amount, 2);
}

function getEmployeeCount($status = null) {
    $conn = getDBConnection();
    
    if ($status) {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM employees WHERE status = ?");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT COUNT(*) as cnt FROM employees");
    }
    
    $row = $result->fetch_assoc();
    return $row['cnt'];
}

function getSalaryStats($month = null) {
    $conn = getDBConnection();
    
    if ($month) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_employees,
                SUM(net_payable) as total_payable,
                SUM(gross_salary) as total_gross,
                SUM(pf_deduction) as total_pf
            FROM salary_sheets 
            WHERE month = ?
        ");
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total_employees,
                SUM(net_payable) as total_payable,
                SUM(gross_salary) as total_gross,
                SUM(pf_deduction) as total_pf
            FROM salary_sheets
        ");
    }
    
    return $result->fetch_assoc();
}

function getMonthList() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT DISTINCT month FROM salary_sheets ORDER BY month DESC");
    $months = [];
    while ($row = $result->fetch_assoc()) {
        $months[] = $row['month'];
    }
    return $months;
}

function getSetting($key, $default = '') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

function getAllSettings() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function saveSetting($key, $value) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function saveSettings($settings) {
    $conn = getDBConnection();
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
        $stmt->close();
    }
    return true;
}
