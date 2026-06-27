<?php
// AJAX endpoint for academic module
// Suppress PHP warnings/notices so they don't corrupt JSON responses
ini_set('display_errors', 0);
error_reporting(0);

define('EMS_ROOT', dirname(dirname(__DIR__)));
require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';

// Initialize session so $_SESSION is populated for checking auth status
if (session_status() === PHP_SESSION_NONE) {
    session_name(defined('EMS_SESSION_NAME') ? EMS_SESSION_NAME : 'EMS_SESS');
    session_start();
}

// Return JSON for any auth failure instead of HTML redirect
if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Session expired. Please refresh the page and log in again.']);
    exit;
}
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = db();

if ($action === 'sections') {
    $classId = int_param('class_id', 0, $_GET);
    if (!$classId) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare('SELECT id, section_name, shift, capacity FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name');
    $stmt->execute([':c' => $classId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'classes_by_session') {
    $sessionId = int_param('session_id', 0, $_GET);
    $stmt = $pdo->prepare('SELECT DISTINCT c.id, c.class_name FROM class_subjects cs JOIN classes c ON c.id=cs.class_id WHERE cs.session_id=:s ORDER BY c.display_order');
    $stmt->execute([':s' => $sessionId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'subjects_by_class_session') {
    $classId = int_param('class_id', 0, $_GET);
    $sessionId = int_param('session_id', 0, $_GET);
    $stmt = $pdo->prepare('SELECT s.id, s.subject_name, cs.full_marks_written, cs.full_marks_mcq, cs.full_marks_practical, cs.classes_per_week FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=:c AND cs.session_id=:s ORDER BY s.subject_name');
    $stmt->execute([':c' => $classId, ':s' => $sessionId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'save_period_config') {
    if (!has_permission('routine.manage')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    $periods = $_POST['periods'] ?? '[]';
    // Validate JSON
    $decoded = json_decode($periods, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        echo json_encode(['success' => false, 'error' => 'Invalid periods data structure']);
        exit;
    }
    
    $stmt = $pdo->prepare('INSERT INTO system_settings (meta_key, meta_value, meta_group) VALUES ("routine_periods", :v, "academic")
                           ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)');
    $stmt->execute([':v' => json_encode($decoded)]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_section_periods') {
    if (!has_permission('routine.manage')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    $section_id = int_param('section_id', 0, $_POST);
    $periods = $_POST['periods'] ?? '[]';
    if (!$section_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid Section ID']);
        exit;
    }
    $decoded = json_decode($periods, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        echo json_encode(['success' => false, 'error' => 'Invalid periods data structure']);
        exit;
    }
    
    $meta_key = "section_periods_" . $section_id;
    $stmt = $pdo->prepare('INSERT INTO system_settings (meta_key, meta_value, meta_group) VALUES (:k, :v, "academic")
                           ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)');
    $stmt->execute([':k' => $meta_key, ':v' => json_encode($decoded)]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get_suggestions') {
    $subId     = int_param('subject_id', 0, $_GET);
    $sessId    = int_param('session_id', 0, $_GET);
    $day       = $_GET['day'] ?? '';
    $start     = $_GET['start'] ?? '';
    $end       = $_GET['end'] ?? '';
    $sectionId = int_param('section_id', 0, $_GET);

    if (!$sessId) {
        echo json_encode(['error' => 'Missing session ID']);
        exit;
    }

    $start_time = $start ? date('H:i:s', strtotime($start)) : '';
    $end_time   = $end ? date('H:i:s', strtotime($end)) : '';

    // 1. Get Class Teacher recommendation
    $class_teacher_recommendation = null;
    if ($sectionId) {
        $sec_stmt = $pdo->prepare("
            SELECT s.class_teacher_id, s.class_teacher_first_period_days, 
                   CONCAT(sp.first_name, ' ', sp.last_name) as teacher_name
            FROM sections s
            LEFT JOIN staff_profiles sp ON sp.user_id = s.class_teacher_id
            WHERE s.id = ?
        ");
        $sec_stmt->execute([$sectionId]);
        $sec_row = $sec_stmt->fetch(PDO::FETCH_ASSOC);
        if ($sec_row && $sec_row['class_teacher_id']) {
            $teacher_id = (int)$sec_row['class_teacher_id'];
            $days_active = explode(',', $sec_row['class_teacher_first_period_days'] ?? '');
            $is_active_day = false;
            foreach ($days_active as $d) {
                if (trim(strtolower($d)) === strtolower(substr($day, 0, 3))) {
                    $is_active_day = true;
                    break;
                }
            }
            if ($is_active_day) {
                // Check workload limits and busy status for class teacher
                $limits_stmt = $pdo->prepare("SELECT max_classes_per_day, max_classes_per_week FROM staff_profiles WHERE user_id = ?");
                $limits_stmt->execute([$teacher_id]);
                $lim = $limits_stmt->fetch() ?: ['max_classes_per_day' => 4, 'max_classes_per_week' => 20];
                
                $wk_load_stmt = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id = ? AND session_id = ? AND status = 1");
                $wk_load_stmt->execute([$teacher_id, $sessId]);
                $wk_load = (int)$wk_load_stmt->fetchColumn();

                $dy_load_stmt = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id = ? AND session_id = ? AND day_of_week = ? AND status = 1");
                $dy_load_stmt->execute([$teacher_id, $sessId, $day]);
                $dy_load = (int)$dy_load_stmt->fetchColumn();

                $busy_stmt = $pdo->prepare("
                    SELECT rs.*, c.class_name, s.section_name 
                    FROM routine_slots rs 
                    JOIN classes c ON c.id=rs.class_id 
                    JOIN sections s ON s.id=rs.section_id 
                    WHERE rs.teacher_id=? AND rs.day_of_week=? AND rs.status=1 
                    AND NOT (rs.end_time <= ? OR rs.start_time >= ?)
                ");
                $busy_stmt->execute([$teacher_id, $day, $start_time, $end_time]);
                $busy = $busy_stmt->fetch();

                $class_teacher_recommendation = [
                    'id' => $teacher_id,
                    'name' => $sec_row['teacher_name'],
                    'weekly_load' => $wk_load,
                    'daily_load' => $dy_load,
                    'max_classes_per_day' => (int)$lim['max_classes_per_day'],
                    'max_classes_per_week' => (int)$lim['max_classes_per_week'],
                    'is_busy' => $busy ? 1 : 0,
                    'busy_with' => $busy ? "{$busy['class_name']} - {$busy['section_name']}" : '',
                    'is_class_teacher_rec' => 1
                ];
            }
        }
    }

    // 2. Get Expert Teachers for subject
    $experts = [];
    if ($subId) {
        $query = "SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation, sp.department,
                         sp.max_classes_per_day, sp.max_classes_per_week,
                         (SELECT COUNT(*) FROM routine_slots WHERE teacher_id = u.id AND session_id = :sess AND status = 1) as weekly_load,
                         (SELECT COUNT(*) FROM routine_slots WHERE teacher_id = u.id AND session_id = :sess AND day_of_week = :day AND status = 1) as daily_load
                  FROM teacher_subjects ts
                  JOIN staff_profiles sp ON sp.user_id = ts.teacher_id
                  JOIN users u ON u.id = ts.teacher_id
                  WHERE ts.subject_id = :sub AND sp.status = 'active'
                  ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':sub' => $subId, ':sess' => $sessId, ':day' => $day]);
        $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($experts as &$exp) {
            $exp['is_busy'] = 0;
            $exp['busy_with'] = '';
            if ($day && $start_time && $end_time) {
                $chk = $pdo->prepare("SELECT rs.*, c.class_name, s.section_name 
                                      FROM routine_slots rs 
                                      JOIN classes c ON c.id=rs.class_id 
                                      JOIN sections s ON s.id=rs.section_id 
                                      WHERE rs.teacher_id=? AND rs.day_of_week=? AND rs.status=1 
                                      AND NOT (rs.end_time <= ? OR rs.start_time >= ?)");
                $chk->execute([$exp['id'], $day, $start_time, $end_time]);
                $busy = $chk->fetch();
                if ($busy) {
                    $exp['is_busy'] = 1;
                    $exp['busy_with'] = "{$busy['class_name']} - {$busy['section_name']}";
                }
            }
        }
    }

    // 3. Get Free Rooms
    $free_rooms = [];
    if ($day && $start_time && $end_time) {
        $rooms = $pdo->query("SELECT id, room_name, capacity FROM rooms WHERE status = 1 ORDER BY room_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as $r) {
            $chk = $pdo->prepare("SELECT rs.*, c.class_name, s.section_name 
                                  FROM routine_slots rs 
                                  JOIN classes c ON c.id=rs.class_id 
                                  JOIN sections s ON s.id=rs.section_id 
                                  WHERE rs.room_id=? AND rs.day_of_week=? AND rs.status=1 
                                  AND NOT (rs.end_time <= ? OR rs.start_time >= ?)");
            $chk->execute([$r['id'], $day, $start_time, $end_time]);
            $busy = $chk->fetch();
            if (!$busy) {
                $free_rooms[] = $r;
            }
        }
    } else {
        $free_rooms = $pdo->query("SELECT id, room_name, capacity FROM rooms WHERE status = 1 ORDER BY room_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'class_teacher_rec' => $class_teacher_recommendation,
        'experts' => $experts,
        'free_rooms' => $free_rooms
    ]);
    exit;
}

if ($action === 'save_slot') {
    if (!has_permission('routine.manage')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $id         = int_param('id', 0, $_POST);
    $sess       = int_param('session_id', 0, $_POST);
    $cls        = int_param('class_id', 0, $_POST);
    $sec        = int_param('section_id', 0, $_POST);
    $subj       = int_param('subject_id', 0, $_POST);
    $teacher    = int_param('teacher_id', 0, $_POST) ?: null;
    $room       = int_param('room_id', 0, $_POST) ?: null;
    $day        = $_POST['day_of_week'] ?? '';
    $start      = $_POST['start_time'] ?? '';
    $end        = $_POST['end_time'] ?? '';
    $force      = int_param('force', 0, $_POST);

    if (!$sess || !$cls || !$sec || !$subj || !$day || !$start || !$end) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // Convert start and end times to proper TIME format (H:i:s)
    $start_time = date('H:i:s', strtotime($start));
    $end_time = date('H:i:s', strtotime($end));

    $conflicts = [];
    $exclude = $id ? "AND id != $id" : '';

    if ($teacher) {
        // Teacher double booking
        $tc = $pdo->prepare("SELECT rs.*, c.class_name, s.section_name 
                             FROM routine_slots rs 
                             JOIN classes c ON c.id=rs.class_id 
                             JOIN sections s ON s.id=rs.section_id 
                             WHERE rs.teacher_id=? AND rs.day_of_week=? AND rs.status=1 
                             AND NOT (rs.end_time <= ? OR rs.start_time >= ?) $exclude");
        $tc->execute([$teacher, $day, $start_time, $end_time]);
        $rows = $tc->fetchAll();
        foreach ($rows as $r) {
            $conflicts[] = "Teacher is already scheduled for {$r['class_name']} - {$r['section_name']} at this time.";
        }
        
        // Workload limits check
        $lim = $pdo->prepare('SELECT max_classes_per_day, max_classes_per_week FROM staff_profiles WHERE user_id = ?');
        $lim->execute([$teacher]);
        $limRow = $lim->fetch() ?: ['max_classes_per_day' => 4, 'max_classes_per_week' => 20];
        
        // Weekly load
        $wc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id=? AND session_id=? AND status=1 $exclude");
        $wc->execute([$teacher, $sess]);
        $weeklyLoad = (int)$wc->fetchColumn();
        if ($weeklyLoad >= (int)$limRow['max_classes_per_week']) {
            $conflicts[] = "Teacher has reached the weekly workload limit of {$limRow['max_classes_per_week']} classes.";
        }
        
        // Daily load
        $dc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id=? AND session_id=? AND day_of_week=? AND status=1 $exclude");
        $dc->execute([$teacher, $sess, $day]);
        $dailyLoad = (int)$dc->fetchColumn();
        if ($dailyLoad >= (int)$limRow['max_classes_per_day']) {
            $conflicts[] = "Teacher has reached the daily workload limit of {$limRow['max_classes_per_day']} classes.";
        }
    }

    if ($room) {
        // Room double booking
        $rc = $pdo->prepare("SELECT rs.*, c.class_name, s.section_name 
                             FROM routine_slots rs 
                             JOIN classes c ON c.id=rs.class_id 
                             JOIN sections s ON s.id=rs.section_id 
                             WHERE rs.room_id=? AND rs.day_of_week=? AND rs.status=1 
                             AND NOT (rs.end_time <= ? OR rs.start_time >= ?) $exclude");
        $rc->execute([$room, $day, $start_time, $end_time]);
        $rows = $rc->fetchAll();
        foreach ($rows as $r) {
            $conflicts[] = "Room is occupied by {$r['class_name']} - {$r['section_name']} at this time.";
        }
    }

    // Section double booking
    $sc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE section_id=? AND day_of_week=? AND status=1 AND NOT (end_time <= ? OR start_time >= ?) $exclude");
    $sc->execute([$sec, $day, $start_time, $end_time]);
    if ((int)$sc->fetchColumn() > 0) {
        $conflicts[] = "Section already has another class scheduled at this period.";
    }

    if ($conflicts && !$force) {
        echo json_encode(['success' => false, 'conflicts' => $conflicts]);
        exit;
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE routine_slots SET session_id=?, class_id=?, section_id=?, subject_id=?, teacher_id=?, room_id=?, day_of_week=?, start_time=?, end_time=? WHERE id=?');
        $stmt->execute([$sess, $cls, $sec, $subj, $teacher, $room, $day, $start_time, $end_time, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO routine_slots (session_id, class_id, section_id, subject_id, teacher_id, room_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$sess, $cls, $sec, $subj, $teacher, $room, $day, $start_time, $end_time]);
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_slot') {
    if (!has_permission('routine.manage')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    $id = int_param('id', 0, $_POST);
    if ($id) {
        $pdo->prepare('UPDATE routine_slots SET status=0 WHERE id=?')->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit;
}

if ($action === 'check_conflicts') {
    $teacherId = int_param('teacher_id', 0, $_GET);
    $day       = $_GET['day'] ?? '';
    $start     = $_GET['start'] ?? '';
    $end       = $_GET['end'] ?? '';
    $sessId    = int_param('session_id', (int)setting('current_session_id', 0), $_GET);

    $conflicts = [];
    $weeklyLoad = 0;
    $maxWeek    = 20;

    if ($teacherId && $day && $start && $end) {
        $start_time = date('H:i:s', strtotime($start));
        $end_time   = date('H:i:s', strtotime($end));

        // Fetch limits
        $lim = $pdo->prepare('SELECT max_classes_per_day, max_classes_per_week FROM staff_profiles WHERE user_id = ?');
        $lim->execute([$teacherId]);
        $row = $lim->fetch() ?: ['max_classes_per_day' => 4, 'max_classes_per_week' => 20];
        $maxDay  = (int)$row['max_classes_per_day'];
        $maxWeek = (int)$row['max_classes_per_week'];

        // Double booking check
        $tc = $pdo->prepare("SELECT rs.*, c.class_name, s.section_name 
                             FROM routine_slots rs 
                             JOIN classes c ON c.id=rs.class_id 
                             JOIN sections s ON s.id=rs.section_id 
                             WHERE rs.teacher_id=? AND rs.day_of_week=? AND rs.status=1 
                             AND NOT (rs.end_time <= ? OR rs.start_time >= ?)");
        $tc->execute([$teacherId, $day, $start_time, $end_time]);
        $rows = $tc->fetchAll();
        foreach ($rows as $r) {
            $conflicts[] = "Scheduled for {$r['class_name']} - {$r['section_name']} at this period.";
        }

        // Daily load check
        $dc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id=? AND session_id=? AND day_of_week=? AND status=1");
        $dc->execute([$teacherId, $sessId, $day]);
        $dayCount = (int)$dc->fetchColumn();
        if ($dayCount >= $maxDay) {
            $conflicts[] = "Daily workload ({$dayCount}/{$maxDay} classes) reached.";
        }

        // Weekly load check
        $wc = $pdo->prepare("SELECT COUNT(*) FROM routine_slots WHERE teacher_id=? AND session_id=? AND status=1");
        $wc->execute([$teacherId, $sessId]);
        $weeklyLoad = (int)$wc->fetchColumn();
        if ($weeklyLoad >= $maxWeek) {
            $conflicts[] = "Weekly workload ({$weeklyLoad}/{$maxWeek} classes) reached.";
        }
    }

    echo json_encode([
        'conflicts'   => $conflicts,
        'weekly_load' => $weeklyLoad,
        'max_week'    => $maxWeek
    ]);
    exit;
}

if ($action === 'get_experts') {
    $subId  = int_param('subject_id', 0, $_GET);
    $sessId = int_param('session_id', 0, $_GET);
    $day    = $_GET['day'] ?? '';
    $start  = $_GET['start'] ?? '';
    $end    = $_GET['end'] ?? '';

    // Load all experts
    $query = "SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.designation, sp.department,
                     sp.max_classes_per_day, sp.max_classes_per_week,
                     (SELECT COUNT(*) FROM routine_slots WHERE teacher_id = u.id AND session_id = :sess AND status = 1) as weekly_load,
                     (SELECT COUNT(*) FROM routine_slots WHERE teacher_id = u.id AND session_id = :sess AND day_of_week = :day AND status = 1) as daily_load
              FROM teacher_subjects ts
              JOIN staff_profiles sp ON sp.user_id = ts.teacher_id
              JOIN users u ON u.id = ts.teacher_id
              WHERE ts.subject_id = :sub AND sp.status = 'active'
              ORDER BY name";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':sub' => $subId, ':sess' => $sessId, ':day' => $day]);
    $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each expert, check if they are busy at this exact time
    foreach ($experts as &$exp) {
        $exp['is_busy'] = 0;
        $exp['busy_with'] = '';
        if ($day && $start && $end) {
            $start_time = date('H:i:s', strtotime($start));
            $end_time   = date('H:i:s', strtotime($end));
            
            $chk = $pdo->prepare("SELECT rs.*, c.class_name, s.section_name 
                                  FROM routine_slots rs 
                                  JOIN classes c ON c.id=rs.class_id 
                                  JOIN sections s ON s.id=rs.section_id 
                                  WHERE rs.teacher_id=? AND rs.day_of_week=? AND rs.status=1 
                                  AND NOT (rs.end_time <= ? OR rs.start_time >= ?)");
            $chk->execute([$exp['id'], $day, $start_time, $end_time]);
            $busy = $chk->fetch();
            if ($busy) {
                $exp['is_busy'] = 1;
                $exp['busy_with'] = "{$busy['class_name']} - {$busy['section_name']}";
            }
        }
    }

    echo json_encode($experts);
    exit;
}

if ($action === 'auto_generate') {
    if (!has_permission('routine.manage')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $session_id = int_param('session_id', 0, $_POST);
    if (!$session_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid Session ID']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // Clear existing slots for the session
        $pdo->prepare("UPDATE routine_slots SET status = 0 WHERE session_id = ?")->execute([$session_id]);
        
        // Load periods
        $raw = setting('routine_periods');
        $periods = [];
        if ($raw) {
            $periods = json_decode($raw, true);
        }
        if (empty($periods)) {
            $periods = [
                ['name' => 'Period 1', 'start' => '09:00', 'end' => '09:45', 'is_break' => 0],
                ['name' => 'Period 2', 'start' => '09:45', 'end' => '10:30', 'is_break' => 0],
                ['name' => 'Tiffin', 'start' => '10:30', 'end' => '11:00', 'is_break' => 1],
                ['name' => 'Period 3', 'start' => '11:00', 'end' => '11:45', 'is_break' => 0],
                ['name' => 'Period 4', 'start' => '11:45', 'end' => '12:30', 'is_break' => 0],
                ['name' => 'Prayer Break', 'start' => '12:30', 'end' => '13:30', 'is_break' => 1],
                ['name' => 'Period 5', 'start' => '13:30', 'end' => '14:15', 'is_break' => 0],
                ['name' => 'Period 6', 'start' => '14:15', 'end' => '15:00', 'is_break' => 0]
            ];
        }
        
        $class_periods = array_values(array_filter($periods, fn($p) => empty($p['is_break'])));
        
        // Normalise working_days — setting may store full names OR 3-letter codes
        $_day_norm = [
            'Sat'=>'Saturday','Sun'=>'Sunday','Mon'=>'Monday','Tue'=>'Tuesday',
            'Wed'=>'Wednesday','Thu'=>'Thursday','Fri'=>'Friday',
            'Saturday'=>'Saturday','Sunday'=>'Sunday','Monday'=>'Monday','Tuesday'=>'Tuesday',
            'Wednesday'=>'Wednesday','Thursday'=>'Thursday','Friday'=>'Friday',
        ];
        $working_days = array_values(array_filter(array_map(
            fn($d) => $_day_norm[trim($d)] ?? null,
            explode(',', setting('working_days', 'Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday'))
        )));
        if (empty($working_days)) {
            $working_days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday'];
        }
        
        // Load all active sections (excluding soft-deleted)
        $sections = $pdo->query("
            SELECT s.id as section_id, s.class_id, c.class_name, s.section_name
            FROM sections s
            JOIN classes c ON c.id = s.class_id
            WHERE s.status = 1 AND s.deleted_at IS NULL AND c.deleted_at IS NULL
            ORDER BY c.display_order, c.class_numeric, s.section_name
        ")->fetchAll();

        // Load class-subjects for this session (excluding soft-deleted subjects)
        $class_subjects = $pdo->prepare("
            SELECT cs.class_id, cs.subject_id, cs.classes_per_week, sub.subject_name
            FROM class_subjects cs
            JOIN subjects sub ON sub.id = cs.subject_id
            WHERE cs.session_id = ? AND sub.deleted_at IS NULL AND sub.status = 1
        ");
        $class_subjects->execute([$session_id]);
        $subjects_by_class = [];
        foreach ($class_subjects->fetchAll() as $row) {
            $classes_per_week = $row['classes_per_week'] ?: 4;
            $subjects_by_class[$row['class_id']][] = [
                'subject_id' => (int)$row['subject_id'],
                'subject_name' => $row['subject_name'],
                'classes_per_week' => (int)$classes_per_week
            ];
        }
        
        // Load rooms
        $rooms = $pdo->query("SELECT id, room_name FROM rooms WHERE room_type='classroom' AND status=1 ORDER BY room_name")->fetchAll(PDO::FETCH_ASSOC);
        
        // Load teacher expertise
        $expertise = [];
        $exp_rows = $pdo->query("SELECT ts.teacher_id, ts.subject_id, CONCAT(sp.first_name, ' ', sp.last_name) as name, sp.max_classes_per_day, sp.max_classes_per_week 
                                 FROM teacher_subjects ts 
                                 JOIN staff_profiles sp ON sp.user_id = ts.teacher_id 
                                 WHERE sp.status = 'active'")->fetchAll();
        foreach ($exp_rows as $row) {
            $expertise[$row['subject_id']][] = [
                'teacher_id' => (int)$row['teacher_id'],
                'name' => $row['name'],
                'max_day' => (int)($row['max_classes_per_day'] ?: 4),
                'max_week' => (int)($row['max_classes_per_week'] ?: 20)
            ];
        }
        
        // Workload tracking
        $teacher_load_day = [];  // [teacher_id][day] = count
        $teacher_load_week = []; // [teacher_id] = count
        $teacher_schedule = [];  // [teacher_id][day][period] = true
        $room_schedule = [];     // [room_id][day][period] = true
        
        $insert_stmt = $pdo->prepare("INSERT INTO routine_slots (session_id, class_id, section_id, subject_id, teacher_id, room_id, day_of_week, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        
        // Loop through each section to generate timetable
        foreach ($sections as $sec) {
            $class_id = (int)$sec['class_id'];
            $section_id = (int)$sec['section_id'];
            
            $subjects_to_schedule = $subjects_by_class[$class_id] ?? [];
            if (empty($subjects_to_schedule)) continue;
            
            // Expand subjects into individual slots to schedule
            $slots_queue = [];
            foreach ($subjects_to_schedule as $sub) {
                for ($k = 0; $k < $sub['classes_per_week']; $k++) {
                    $slots_queue[] = $sub;
                }
            }
            
            // Shuffle to randomize distribution
            shuffle($slots_queue);
            
            // Match room
            $room_id = null;
            if (!empty($rooms)) {
                foreach ($rooms as $r) {
                    if (str_contains(strtolower($r['room_name']), strtolower($sec['section_name'])) || str_contains(strtolower($r['room_name']), strtolower($sec['class_name']))) {
                        $room_id = $r['id'];
                        break;
                    }
                }
                if (!$room_id) {
                    $room_id = $rooms[array_rand($rooms)]['id'];
                }
            }
            
            $queue_index = 0;
            $total_queue = count($slots_queue);
            
            // Attempt to assign slots to periods and days
            foreach ($working_days as $day) {
                foreach ($class_periods as $period) {
                    if ($queue_index >= $total_queue) break 2; // All subjects scheduled
                    
                    $sub = $slots_queue[$queue_index];
                    $subject_id = $sub['subject_id'];
                    
                    // Find an expert teacher for this subject who is free
                    $assigned_teacher_id = null;
                    $experts = $expertise[$subject_id] ?? [];
                    
                    // Shuffle experts to distribute workload
                    shuffle($experts);
                    
                    foreach ($experts as $exp) {
                        $t_id = $exp['teacher_id'];
                        
                        // Check teacher limits and double bookings
                        $day_load = $teacher_load_day[$t_id][$day] ?? 0;
                        $week_load = $teacher_load_week[$t_id] ?? 0;
                        $has_conflict = isset($teacher_schedule[$t_id][$day][$period['name']]);
                        
                        if ($day_load < $exp['max_day'] && $week_load < $exp['max_week'] && !$has_conflict) {
                            $assigned_teacher_id = $t_id;
                            break;
                        }
                    }
                    
                    // Check if room is free
                    $room_conflict = $room_id && isset($room_schedule[$room_id][$day][$period['name']]);
                    $final_room_id = $room_conflict ? null : $room_id;
                    
                    // Execute insert
                    $insert_stmt->execute([
                        $session_id,
                        $class_id,
                        $section_id,
                        $subject_id,
                        $assigned_teacher_id,
                        $final_room_id,
                        $day,
                        $period['start'] . ':00',
                        $period['end'] . ':00'
                    ]);
                    
                    // Update workload tracking
                    if ($assigned_teacher_id) {
                        $teacher_load_day[$assigned_teacher_id][$day] = ($teacher_load_day[$assigned_teacher_id][$day] ?? 0) + 1;
                        $teacher_load_week[$assigned_teacher_id] = ($teacher_load_week[$assigned_teacher_id] ?? 0) + 1;
                        $teacher_schedule[$assigned_teacher_id][$day][$period['name']] = true;
                    }
                    if ($final_room_id) {
                        $room_schedule[$final_room_id][$day][$period['name']] = true;
                    }
                    
                    $queue_index++;
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Routine auto-generated successfully!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
