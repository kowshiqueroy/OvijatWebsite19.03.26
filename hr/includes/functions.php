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

function buildEmployeeFilterSQL($filter, &$params, &$types) {
    $sql = '';
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
    return $sql;
}

function countAllEmployees($filter = []) {
    $conn = getDBConnection();
    $params = [];
    $types = "";
    $where = buildEmployeeFilterSQL($filter, $params, $types);
    $sql = "SELECT COUNT(*) as cnt FROM employees WHERE 1=1" . $where;
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    $row = $result->fetch_assoc();
    return (int)$row['cnt'];
}

function getAllEmployees($filter = [], $limit = null, $offset = 0) {
    $conn = getDBConnection();
    $params = [];
    $types = "";
    $where = buildEmployeeFilterSQL($filter, $params, $types);
    $sql = "SELECT * FROM employees WHERE 1=1" . $where . " ORDER BY id DESC";

    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    }

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

function countSalarySheets($employeeId = null, $month = null, $filter = []) {
    $conn = getDBConnection();
    $sql = "SELECT COUNT(*) as cnt FROM salary_sheets ss JOIN employees e ON ss.employee_id = e.id WHERE 1=1";
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

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    $row = $result->fetch_assoc();
    return (int)$row['cnt'];
}

function getSalarySheets($employeeId = null, $month = null, $filter = [], $limit = null, $offset = 0) {
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

    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    }

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
    if ($workingDays <= 0) {
        return ['daily_rate' => 0, 'gross_salary' => 0, 'pf_deduction' => 0, 'net_payable' => 0];
    }
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

function generateMonthOptions($monthsBack = 2, $monthsForward = 2) {
    $options = [];
    $current = new DateTime();
    $current->modify('first day of this month');
    $current->modify("-{$monthsBack} months");

    $totalMonths = $monthsBack + 1 + $monthsForward;
    for ($i = 0; $i < $totalMonths; $i++) {
        $month = $current->format('Y-m');
        $options[] = [
            'value' => $month,
            'label' => $current->format('F Y')
        ];
        $current->modify('+1 month');
    }

    return $options;
}

function getSalaryMonths() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT DISTINCT month FROM salary_sheets ORDER BY month DESC");
    $months = [];
    while ($row = $result->fetch_assoc()) {
        $months[] = $row['month'];
    }
    return $months;
}

function getBonusMonths() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT DISTINCT month FROM bonus_sheets ORDER BY month DESC");
    if (!$result) return [];
    $months = [];
    while ($row = $result->fetch_assoc()) {
        $months[] = $row['month'];
    }
    return $months;
}

function tableExists($tableName) {
    $conn = getDBConnection();
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    return $result && $result->num_rows > 0;
}

// ─── Authentication ───────────────────────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    // 60-minute inactivity timeout
    $timeout = 3600;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
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
            $_SESSION['last_activity'] = time();
            return true;
        }
    }
    return false;
}

function adminLogout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ─── CSRF Protection ──────────────────────────────────────────────────────────

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// ─── UI Helpers ───────────────────────────────────────────────────────────────

function showAlert($message, $type = 'success') {
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}

function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return;
    echo '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    echo '<li class="page-item ' . ($currentPage <= 1 ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">&laquo;</a></li>';
    $start = max(1, $currentPage - 2);
    $end   = min($totalPages, $currentPage + 2);
    for ($i = $start; $i <= $end; $i++) {
        echo '<li class="page-item ' . ($i === $currentPage ? 'active' : '') . '">';
        echo '<a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }
    echo '<li class="page-item ' . ($currentPage >= $totalPages ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">&raquo;</a></li>';
    echo '</ul></nav>';
}

function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol', '৳');
    return $symbol . ' ' . number_format($amount, 2);
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

// ─── Loan / PF / Bonus ───────────────────────────────────────────────────────

function getEmployeeLoanBalance($employeeId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type='debit' THEN amount ELSE -amount END), 0) as balance
         FROM loan_transactions WHERE employee_id = ?"
    );
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)$row['balance'];
}

function getEmployeePFBalance($employeeId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(pf_deduction), 0) as pf_total
         FROM salary_sheets WHERE employee_id = ? AND confirmed = 1"
    );
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)$row['pf_total'];
}

function getBatchEmployeeBalances(array $employeeIds) {
    if (empty($employeeIds)) return [];
    $conn = getDBConnection();
    $pl = implode(',', array_fill(0, count($employeeIds), '?'));
    $t  = str_repeat('i', count($employeeIds));

    $stmt = $conn->prepare(
        "SELECT employee_id,
                COALESCE(SUM(CASE WHEN type='debit' THEN amount ELSE -amount END), 0) as loan_balance
         FROM loan_transactions WHERE employee_id IN ($pl) GROUP BY employee_id"
    );
    $stmt->bind_param($t, ...$employeeIds);
    $stmt->execute();
    $loanBalances = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $loanBalances[$row['employee_id']] = (float)$row['loan_balance'];
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT employee_id, COALESCE(SUM(pf_deduction), 0) as pf_balance
         FROM salary_sheets WHERE employee_id IN ($pl) AND confirmed = 1
         GROUP BY employee_id"
    );
    $stmt->bind_param($t, ...$employeeIds);
    $stmt->execute();
    $pfBalances = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $pfBalances[$row['employee_id']] = (float)$row['pf_balance'];
    }
    $stmt->close();

    $balances = [];
    foreach ($employeeIds as $id) {
        $balances[$id] = [
            'loan_balance' => $loanBalances[$id] ?? 0.0,
            'pf_balance'   => $pfBalances[$id]   ?? 0.0,
        ];
    }
    return $balances;
}

function getBatchBonusesForMonth(array $employeeIds, $month) {
    if (empty($employeeIds)) return [];
    $conn = getDBConnection();
    $pl = implode(',', array_fill(0, count($employeeIds), '?'));
    $t  = str_repeat('i', count($employeeIds)) . 's';
    $stmt = $conn->prepare(
        "SELECT employee_id, COALESCE(SUM(bonus_amount), 0) as total
         FROM bonus_sheets WHERE employee_id IN ($pl) AND month = ?
         GROUP BY employee_id"
    );
    $params = array_merge($employeeIds, [$month]);
    $stmt->bind_param($t, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $bonuses = [];
    while ($row = $res->fetch_assoc()) {
        $bonuses[$row['employee_id']] = (float)$row['total'];
    }
    $stmt->close();
    return $bonuses;
}
