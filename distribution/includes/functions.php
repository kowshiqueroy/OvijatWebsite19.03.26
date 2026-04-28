<?php
require_once __DIR__ . '/../config/config.php';

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * CSRF Protection
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_csrf_token() {
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . get_csrf_token() . '">';
}

/**
 * Log user activity
 */
function log_activity($user_id, $action) {
    db_query("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)", [$user_id, $action]);
}

/**
 * Redirect with a message
 */
function redirect($url, $message = '', $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Check if user is logged in
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
    
    // Check for forced password change
    if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] == 1 && basename($_SERVER['PHP_SELF']) != 'change_password.php') {
        redirect('change_password.php', 'Please change your password first.', 'warning');
    }
}

/**
 * Check if user has required role
 */
function check_role($allowed_roles) {
    if (!in_array($_SESSION['role'], (array)$allowed_roles)) {
        redirect('index.php', 'You do not have permission to access this page.', 'danger');
    }
}

/**
 * Global SELECT filter for isDelete = 0
 * Usage: $products = fetch_all("SELECT * FROM products");
 */
function fetch_all($sql, $params = []) {
    // Check if we should skip the automatic isDelete filter
    $skip_filter = stripos($sql, 'SKIP_ISDELETE_FILTER') !== false || stripos($sql, 'isDelete') !== false;
    
    if (stripos($sql, 'SKIP_ISDELETE_FILTER') !== false) {
        $sql = str_ireplace('SKIP_ISDELETE_FILTER', '', $sql);
    }

    // Only inject if it's a SELECT and we haven't skipped it
    if (!$skip_filter && stripos($sql, 'SELECT') === 0) {
        if (stripos($sql, 'WHERE') === false) {
            // Find insertion point (before ORDER, GROUP, LIMIT)
            $insert_at = strlen($sql);
            if (preg_match('/\b(ORDER BY|GROUP BY|LIMIT)\b/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
                $insert_at = $matches[0][1];
            }
            
            $where = " WHERE isDelete = 0 ";
            $sql = substr_replace($sql, $where, $insert_at, 0);
        } else {
            // WHERE exists, inject right after it
            $sql = str_ireplace('WHERE', 'WHERE isDelete = 0 AND ', $sql);
        }
    }

    $stmt = db_query($sql, $params);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

/**
 * Fetch a single row
 */
function fetch_one($sql, $params = []) {
    $data = fetch_all($sql, $params);
    return $data ? $data[0] : null;
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Format currency
 */
function format_currency($amount) {
    return '৳ ' . number_format($amount, 2);
}

/**
 * Convert number to Taka and Paisa words
 */
function number_to_words($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'forty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        1000000             => 'million',
        1000000000          => 'billion',
        1000000000000       => 'trillion',
        1000000000000000    => 'quadrillion',
        1000000000000000000 => 'quintillion'
    );

    if (!is_numeric($number)) return false;

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        trigger_error('number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX, E_USER_WARNING);
        return false;
    }

    if ($number < 0) return $negative . number_to_words(abs($number));

    $string = $fraction = null;

    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[(int) $hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . number_to_words($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= number_to_words($remainder);
            }
            break;
    }

    if (null !== $fraction && is_numeric($fraction)) {
        $words = array();
        foreach (str_split((string) $fraction) as $number) {
            $words[] = $dictionary[$number];
        }
        // Simplified for Paisa (2 digits)
        $paisa = (int)substr($fraction, 0, 2);
        if ($paisa > 0) {
            return ucwords($string) . " Taka and " . ucwords(number_to_words($paisa)) . " Paisa Only";
        }
    }

    return ucwords($string) . " Taka Only";
}

/**
 * Get company settings
 */
function get_company_settings() {
    return fetch_one("SELECT * FROM company_settings LIMIT 1");
}
?>
