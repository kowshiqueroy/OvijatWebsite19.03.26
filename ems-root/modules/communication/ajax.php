<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_auth();
header('Content-Type: application/json');

$type       = $_GET['type'] ?? '';
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$pdo        = db();
$phones     = [];

if ($type === 'all_guardian') {
    $stmt = $pdo->prepare("SELECT DISTINCT sp.guardian_phone FROM student_profiles sp JOIN users u ON u.id=sp.user_id WHERE u.status='active' AND sp.guardian_phone IS NOT NULL AND sp.guardian_phone != ''");
    $stmt->execute();
    $phones = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN));
} elseif ($type === 'all_staff') {
    $stmt = $pdo->prepare("SELECT DISTINCT sp.phone FROM staff_profiles sp WHERE sp.status='active' AND sp.phone IS NOT NULL AND sp.phone != ''");
    $stmt->execute();
    $phones = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN));
} elseif (str_starts_with($type, 'class_')) {
    $class_id = (int)substr($type, 6);
    $stmt = $pdo->prepare("SELECT DISTINCT sp.guardian_phone FROM student_profiles sp JOIN users u ON u.id=sp.user_id JOIN student_enrollments se ON se.student_id=u.id WHERE se.class_id=:cls AND se.session_id=:sess AND se.status='active' AND sp.guardian_phone IS NOT NULL AND sp.guardian_phone != ''");
    $stmt->execute([':cls'=>$class_id,':sess'=>$session_id]);
    $phones = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN));
}

echo json_encode(array_values($phones));
