<?php
// Auto-generated. Modify as needed.
define('EMS_VERSION', '1.0.0');
if (!defined('EMS_ROOT')) define('EMS_ROOT', dirname(__DIR__));

// Calculate EMS_URL robustly, handling Windows drive letter casing differences
$doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$ems_root = str_replace('\\', '/', EMS_ROOT);

if (preg_match('/^[a-zA-Z]:/', $doc_root) && preg_match('/^[a-zA-Z]:/', $ems_root)) {
    if (strtolower(substr($doc_root, 0, 2)) === strtolower(substr($ems_root, 0, 2))) {
        $doc_root = ucfirst($doc_root);
        $ems_root = ucfirst($ems_root);
    }
}

$sub_dir = rtrim(str_replace($doc_root, '', $ems_root), '/');
define('EMS_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                  . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                  . $sub_dir);

// Upload directories
define('UPLOAD_PHOTOS',    EMS_ROOT . '/uploads/photos/');
define('UPLOAD_DOCS',      EMS_ROOT . '/uploads/documents/');
define('UPLOAD_QUESTIONS', EMS_ROOT . '/uploads/questions/');
define('UPLOAD_LOGOS',     EMS_ROOT . '/uploads/logos/');
define('UPLOAD_AVATARS',   EMS_ROOT . '/uploads/avatars/');

// Max upload sizes (bytes)
define('MAX_PHOTO_SIZE',  2 * 1024 * 1024);  // 2MB
define('MAX_DOC_SIZE',    5 * 1024 * 1024);  // 5MB

// Session name
define('EMS_SESSION_NAME', 'EMS_SESS');

// Pagination default
define('PER_PAGE', 25);

// Bangladesh-specific
define('WEEKENDS', ['Friday', 'Saturday']);
define('EDUCATION_BOARDS', [
    'DEB' => 'Dhaka Board',
    'RAJ' => 'Rajshahi Board',
    'CHI' => 'Chittagong Board',
    'CUM' => 'Comilla Board',
    'BAR' => 'Barisal Board',
    'JES' => 'Jessore Board',
    'SYL' => 'Sylhet Board',
    'MYM' => 'Mymensingh Board',
    'DIN' => 'Dinajpur Board',
]);

define('GRADE_SCALE', [
    ['min' => 80, 'max' => 100, 'grade' => 'A+', 'gpa' => 5.00, 'label' => 'Outstanding'],
    ['min' => 70, 'max' =>  79, 'grade' => 'A',  'gpa' => 4.00, 'label' => 'Excellent'],
    ['min' => 60, 'max' =>  69, 'grade' => 'A-', 'gpa' => 3.50, 'label' => 'Very Good'],
    ['min' => 50, 'max' =>  59, 'grade' => 'B',  'gpa' => 3.00, 'label' => 'Good'],
    ['min' => 40, 'max' =>  49, 'grade' => 'C',  'gpa' => 2.00, 'label' => 'Satisfactory'],
    ['min' => 33, 'max' =>  39, 'grade' => 'D',  'gpa' => 1.00, 'label' => 'Pass'],
    ['min' =>  0, 'max' =>  32, 'grade' => 'F',  'gpa' => 0.00, 'label' => 'Fail'],
]);
