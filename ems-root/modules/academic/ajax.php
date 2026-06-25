<?php
// AJAX endpoint for academic module
define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_auth();

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

if ($action === 'sections') {
    $classId = int_param('class_id', 0, $_GET);
    if (!$classId) { echo json_encode([]); exit; }
    $stmt = db()->prepare('SELECT id, section_name, shift, capacity FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name');
    $stmt->execute([':c' => $classId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'classes_by_session') {
    // Returns classes that have subjects mapped to a session
    $sessionId = int_param('session_id', 0, $_GET);
    $stmt = db()->prepare('SELECT DISTINCT c.id, c.class_name FROM class_subjects cs JOIN classes c ON c.id=cs.class_id WHERE cs.session_id=:s ORDER BY c.display_order');
    $stmt->execute([':s' => $sessionId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'subjects_by_class_session') {
    $classId = int_param('class_id', 0, $_GET);
    $sessionId = int_param('session_id', 0, $_GET);
    $stmt = db()->prepare('SELECT s.id, s.subject_name, cs.full_marks_written, cs.full_marks_mcq, cs.full_marks_practical FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=:c AND cs.session_id=:s ORDER BY s.subject_name');
    $stmt->execute([':c' => $classId, ':s' => $sessionId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

echo json_encode(['error' => 'Invalid action']);
