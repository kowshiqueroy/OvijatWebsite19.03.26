<?php
// Bootstrap constants (safe to call multiple times — constants.php guards with defined())
require_once dirname(__DIR__) . '/config/constants.php';

// Global PDO instance
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        require_once dirname(__DIR__) . '/config/db.php';
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,  // allows reusing named params (:q, :v) in same query
        ]);
    }
    return $pdo;
}

// XSS-safe output — accepts null safely
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Redirect helper
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// Get a single system setting
function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $stmt = db()->prepare('SELECT meta_value FROM system_settings WHERE meta_key = :k');
            $stmt->execute([':k' => $key]);
            $cache[$key] = $stmt->fetchColumn() ?: $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

// Flash message (store then consume)
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// CSRF token generate & verify
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_check(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

// Safe integer from request
function int_param(string $key, int $default = 0, array $source = []): int {
    $src = $source ?: $_REQUEST;
    return isset($src[$key]) ? (int)$src[$key] : $default;
}

// Pagination helper
function paginate(int $total, int $page, int $perPage = PER_PAGE): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
    ];
}

// Generate a collision-safe sequential receipt number
function next_receipt_number(): string {
    $prefix  = setting('receipt_prefix', 'RCP');
    $year    = date('Y');
    $pattern = $prefix . '-' . $year . '-%';
    // Use MAX of the numeric suffix to avoid collisions with seeded/existing data
    $stmt = db()->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)), 0)
         FROM fee_payments WHERE receipt_number LIKE :p"
    );
    $stmt->execute([':p' => $pattern]);
    $maxNum = (int)$stmt->fetchColumn();
    return $prefix . '-' . $year . '-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
}

// Calculate GPA from total marks percentage
function calculate_grade(float $percentage): array {
    foreach (GRADE_SCALE as $g) {
        if ($percentage >= $g['min'] && $percentage <= $g['max']) {
            return $g;
        }
    }
    return ['grade' => 'F', 'gpa' => 0.00, 'label' => 'Fail'];
}

// Log activity
function log_activity(string $action, string $module = '', int $recordId = 0, string $old = '', string $new = ''): void {
    try {
        db()->prepare(
            'INSERT INTO activity_logs (user_id, action, module, record_id, old_value, new_value, ip_address)
             VALUES (:uid, :act, :mod, :rid, :old, :new, :ip)'
        )->execute([
            ':uid' => $_SESSION['user_id'] ?? null,
            ':act' => $action,
            ':mod' => $module,
            ':rid' => $recordId ?: null,
            ':old' => $old ?: null,
            ':new' => $new ?: null,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);
    } catch (Exception $e) {
        // Non-fatal — don't interrupt the main flow
    }
}

// Format currency
function money(float $amount): string {
    return setting('currency_symbol', '৳') . ' ' . number_format($amount, 2);
}

// Date formatting
function fmt_date(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '—';
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

// Safe file upload — returns saved filename or false
function upload_file(string $file_key, string $dest_dir, array $allowed_ext = ['jpg','jpeg','png','pdf'], int $max_size = 2097152): string|false {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) return false;
    $tmp  = $_FILES[$file_key]['tmp_name'];
    $orig = $_FILES[$file_key]['name'];
    $size = $_FILES[$file_key]['size'];
    if ($size > $max_size) return false;
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) return false;

    // MIME check — use finfo if the fileinfo extension is loaded, else rely on extension alone
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp);
        $safe_mimes = ['image/jpeg','image/png','image/gif','image/webp',
                       'application/pdf','application/msword',
                       'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($mime, $safe_mimes, true) && !str_starts_with($mime, 'image/')) return false;
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp);
        $safe_mimes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
        if (!in_array($mime, $safe_mimes, true) && !str_starts_with($mime, 'image/')) return false;
    }
    // If neither is available, extension whitelist above is the only guard

    $new_name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    if (!move_uploaded_file($tmp, $dest_dir . $new_name)) return false;
    return $new_name;
}

// Render a Bootstrap alert from flash
function render_flash(): void {
    $f = get_flash();
    if (!$f) return;
    $map = ['success'=>'success','error'=>'danger','warning'=>'warning','info'=>'info'];
    $cls = $map[$f['type']] ?? 'info';
    $icon = ['success'=>'check-circle-fill','danger'=>'x-circle-fill','warning'=>'exclamation-triangle-fill','info'=>'info-circle-fill'][$cls] ?? 'info-circle-fill';
    echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-' . $icon . '"></i>
            <div>' . e($f['msg']) . '</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
          </div>';
}

// Current user's enrolled session/class etc (students)
function current_enrollment(int $studentId): ?array {
    $sid = (int)setting('current_session_id', 0);
    if (!$sid) return null;
    $stmt = db()->prepare(
        'SELECT se.*, c.class_name, s.section_name
         FROM student_enrollments se
         JOIN classes c ON c.id = se.class_id
         JOIN sections s ON s.id = se.section_id
         WHERE se.student_id = :sid AND se.session_id = :sess AND se.status = "active"
         LIMIT 1'
    );
    $stmt->execute([':sid' => $studentId, ':sess' => $sid]);
    return $stmt->fetch() ?: null;
}

// Check if a student has outstanding fee dues in the current session
function student_has_dues(int $studentId): bool {
    try {
        $sid = (int)setting('current_session_id', 0);
        if (!$sid) return false;
        $stmt = db()->prepare(
            'SELECT SUM(amount_due - amount_paid - waiver_amount)
             FROM fee_ledgers
             WHERE student_id = :sid AND session_id = :sess AND status != "paid"'
        );
        $stmt->execute([':sid' => $studentId, ':sess' => $sid]);
        $dues = (float)$stmt->fetchColumn();
        return $dues > 0.05;
    } catch (Exception $e) {
        return false;
    }
}

