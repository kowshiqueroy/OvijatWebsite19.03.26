<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_auth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'roll_count') {
    $classId   = int_param('class_id', 0, $_GET);
    $sectionId = int_param('section_id', 0, $_GET);
    $sessionId = int_param('session_id', 0, $_GET);
    $q = db()->prepare('SELECT COALESCE(MAX(roll_number),0)+1 FROM student_enrollments WHERE class_id=:c AND section_id=:s AND session_id=:sess');
    $q->execute([':c'=>$classId,':s'=>$sectionId,':sess'=>$sessionId]);
    echo json_encode(['next_roll' => (int)$q->fetchColumn()]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
