<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['routine.view']);

$pdo = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$class_id   = int_param('class_id', 0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$selected_date = $_GET['date'] ?? '';
$selected_day  = $_GET['day'] ?? 'all';

$session_name = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
$session_name->execute([$session_id]);
$session_label = $session_name->fetchColumn() ?: '';

$is_class_print = ($class_id && $section_id);

$days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
$working_days_raw = setting('working_days', 'Sat,Sun,Mon,Tue,Wed');
$working_days = explode(',', $working_days_raw);
$day_full_names = [
    'Sat' => 'Saturday', 'Sun' => 'Sunday', 'Mon' => 'Monday',
    'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday'
];

if ($is_class_print) {
    // ──────── CLASS ROUTINE MODE ────────
    $class_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $class_stmt->execute([$class_id]);
    $class_label = $class_stmt->fetchColumn();

    $sec_stmt = $pdo->prepare("
        SELECT s.section_name, s.class_teacher_id, 
               CONCAT(sp.first_name, ' ', sp.last_name) as teacher_name
        FROM sections s
        LEFT JOIN staff_profiles sp ON sp.user_id = s.class_teacher_id
        WHERE s.id = ?
    ");
    $sec_stmt->execute([$section_id]);
    $sec_info = $sec_stmt->fetch(PDO::FETCH_ASSOC);
    $sec_label = $sec_info ? $sec_info['section_name'] : '';
    $class_teacher = $sec_info ? $sec_info['teacher_name'] : '';

    // Load custom periods or global
    $sec_periods_key = "section_periods_" . $section_id;
    $sec_periods_raw = setting($sec_periods_key);
    $sec_periods = $sec_periods_raw ? json_decode($sec_periods_raw, true) : null;
    if (empty($sec_periods)) {
        $sec_periods_raw = setting('routine_periods');
        $sec_periods = $sec_periods_raw ? json_decode($sec_periods_raw, true) : [
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

    $slots = [];
    if ($selected_date) {
        $weekday_filter = date('l', strtotime($selected_date));
        $sl = $pdo->prepare("
            SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name
            FROM routine_slots rs
            JOIN subjects s ON s.id=rs.subject_id
            LEFT JOIN users u ON u.id=rs.teacher_id
            LEFT JOIN rooms r ON r.id=rs.room_id
            WHERE rs.session_id=:sess AND rs.class_id=:cls AND rs.section_id=:sec AND rs.status=1
              AND (
                  (rs.is_substitute = 1 AND rs.substitute_date = :date_val)
                  OR
                  (rs.is_substitute = 0 AND rs.substitute_date IS NULL AND NOT EXISTS (
                      SELECT 1 FROM routine_slots sub 
                      WHERE sub.class_id = rs.class_id AND sub.section_id = rs.section_id 
                        AND sub.day_of_week = rs.day_of_week AND sub.start_time = rs.start_time 
                        AND sub.is_substitute = 1 AND sub.substitute_date = :date_val AND sub.status = 1
                  ))
              )
            ORDER BY rs.start_time
        ");
        $sl->execute([
            ':sess' => $session_id,
            ':cls' => $class_id,
            ':sec' => $section_id,
            ':date_val' => $selected_date
        ]);
        foreach ($sl->fetchAll() as $row) {
            $slots[$row['day_of_week']][] = $row;
        }
    } else {
        $sl = $pdo->prepare("
            SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name
            FROM routine_slots rs
            JOIN subjects s ON s.id=rs.subject_id
            LEFT JOIN users u ON u.id=rs.teacher_id
            LEFT JOIN rooms r ON r.id=rs.room_id
            WHERE rs.session_id=:sess AND rs.class_id=:cls AND rs.section_id=:sec AND rs.status=1 AND rs.is_substitute=0
            ORDER BY rs.start_time
        ");
        $sl->execute([':sess'=>$session_id,':cls'=>$class_id,':sec'=>$section_id]);
        foreach ($sl->fetchAll() as $row) {
            $slots[$row['day_of_week']][] = $row;
        }
    }
} else {
    // ──────── MASTER ROUTINE MODE ────────
    $default_periods = get_routine_periods();
    function get_routine_periods() {
        $raw = setting('routine_periods');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return [
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

    $sections_list = $pdo->query("
        SELECT s.id as section_id, s.class_id, c.class_name, s.section_name,
               CONCAT(sp.first_name, ' ', sp.last_name) as class_teacher_name
        FROM sections s 
        JOIN classes c ON c.id = s.class_id 
        LEFT JOIN staff_profiles sp ON sp.user_id = s.class_teacher_id
        WHERE s.status = 1 
        ORDER BY c.display_order, s.section_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $slots_raw = $pdo->prepare("
        SELECT rs.*, s.subject_name, u.full_name as teacher_name, r.room_name 
        FROM routine_slots rs 
        JOIN subjects s ON s.id = rs.subject_id 
        LEFT JOIN users u ON u.id = rs.teacher_id 
        LEFT JOIN rooms r ON r.id = rs.room_id 
        WHERE rs.session_id = ? AND rs.status = 1 AND rs.is_substitute = 0
    ");
    $slots_raw->execute([$session_id]);
    $all_slots = $slots_raw->fetchAll(PDO::FETCH_ASSOC);

    $indexed_slots = [];
    foreach ($all_slots as $slot) {
        $start_time = date('H:i', strtotime($slot['start_time']));
        $indexed_slots[$slot['section_id']][$start_time][$slot['day_of_week']][] = $slot;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Timetable - <?= e($page_title) ?></title>
    <!-- Bootstrap CSS -->
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            color: #333333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .print-header {
            border-bottom: 3px double #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .institute-name {
            font-size: 1.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #111;
        }
        .routine-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #444;
        }
        table {
            width: 100%;
            border-collapse: collapse !important;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #444 !important;
            padding: 6px !important;
            text-align: center !important;
            vertical-align: middle !important;
        }
        th {
            background-color: #f1f1f1 !important;
            color: #000000 !important;
            font-weight: 700 !important;
        }
        .slot-box {
            background: #fdfdfd;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px;
            text-align: left;
            font-size: 0.75rem;
            margin-bottom: 2px;
        }
        .slot-subject {
            font-weight: 700;
            color: #111;
        }
        .slot-teacher, .slot-room {
            font-size: 0.7rem;
            color: #555;
        }
        .break-cell {
            background-color: #fafafa !important;
            font-weight: bold;
            font-size: 0.75rem;
            letter-spacing: 2px;
            color: #777;
        }
        .float-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            z-index: 9999;
            border: 1px solid #ddd;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <!-- Print Action Bar -->
    <div class="float-bar no-print d-flex gap-2">
        <button class="btn btn-primary btn-sm fw-bold" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <button class="btn btn-secondary btn-sm" onclick="window.close()"><i class="bi bi-x-lg"></i> Close</button>
    </div>

    <!-- Header info -->
    <div class="print-header text-center">
        <div class="institute-name"><?= e(setting('institute_name', 'EMS Bangladesh')) ?></div>
        <div class="routine-title">
            <?php if ($is_class_print): ?>
                Class Routine &bull; Class: <strong><?= e($class_label) ?></strong> &bull; Section: <strong><?= e($sec_label) ?></strong>
                <?php if ($class_teacher): ?>
                    &bull; Class Teacher: <strong><?= e($class_teacher) ?></strong>
                <?php endif; ?>
                <?php if ($selected_date): ?>
                    <br><small class="text-danger font-monospace">Effective Date: <?= e($selected_date) ?> (<?= $weekday ?>) [Daily Substitutions Included]</small>
                <?php endif; ?>
            <?php else: ?>
                Master Timetable &bull; Academic Session: <strong><?= e($session_label) ?></strong>
                <?php if ($selected_day !== 'all'): ?>
                    &bull; Weekday: <strong><?= e($selected_day) ?></strong>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_class_print): ?>
        <!-- ──────── CLASS ROUTINE TABLE ──────── -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width: 140px;">Day</th>
                    <?php foreach ($sec_periods as $p): ?>
                        <th>
                            <div><?= e($p['name']) ?></div>
                            <small class="fw-normal opacity-75"><?= e($p['start']) ?> - <?= e($p['end']) ?></small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day):
                    $isWorking = !in_array($day, WEEKENDS);
                    $isToday = $selected_date && ($weekday_filter === $day);
                    $bgStyle = !$isWorking ? 'background-color:#f5f5f5; color:#999;' : ($isToday ? 'background-color:#e8f4fd;' : '');
                ?>
                <tr style="<?= $bgStyle ?>">
                    <td style="font-weight: 700; background-color: #fafafa;">
                        <?= $day ?>
                        <?php if ($isToday): ?><br><span class="badge bg-primary text-white" style="font-size:0.55rem;">SELECTED</span><?php endif; ?>
                    </td>
                    <?php foreach ($sec_periods as $p):
                        if (!empty($p['is_break'])): ?>
                            <td class="break-cell"><?= strtoupper(e($p['name'])) ?></td>
                        <?php else:
                            $cell_slots = [];
                            $daySlots = $slots[$day] ?? [];
                            foreach ($daySlots as $slot) {
                                if (date('H:i', strtotime($slot['start_time'])) === date('H:i', strtotime($p['start']))) {
                                    $cell_slots[] = $slot;
                                }
                            }
                        ?>
                            <td>
                                <?php foreach ($cell_slots as $slot): 
                                    $isSub = !empty($slot['is_substitute']);
                                    $boxStyle = $isSub ? 'border-color: #ffc107; background-color: #fffbeb;' : '';
                                ?>
                                    <div class="slot-box" style="<?= $boxStyle ?>">
                                        <div class="slot-subject"><?= e($slot['subject_name']) ?></div>
                                        <div class="slot-teacher"><i class="bi bi-person me-1"></i><?= e($slot['teacher_name'] ?? 'No teacher') ?></div>
                                        <?php if ($slot['room_name']): ?>
                                            <div class="slot-room"><i class="bi bi-geo-alt me-1"></i>Room: <?= e($slot['room_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($isSub): ?>
                                            <span class="badge bg-warning text-dark p-0 px-1 mt-1" style="font-size:0.55rem;">SUBSTITUTE</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <!-- ──────── MASTER TIMETABLE TABLE ──────── -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width: 160px; text-align: left !important;">Class & Section</th>
                    <?php foreach ($default_periods as $p): ?>
                        <th>
                            <div><?= e($p['name']) ?></div>
                            <small class="fw-normal opacity-75"><?= e($p['start']) ?> - <?= e($p['end']) ?></small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections_list as $sec): 
                    $sec_periods_key = "section_periods_" . $sec['section_id'];
                    $sec_periods_raw = setting($sec_periods_key);
                    $sec_periods = $sec_periods_raw ? json_decode($sec_periods_raw, true) : $default_periods;
                ?>
                <tr>
                    <td style="text-align: left !important; font-weight:700; background-color: #fafafa;">
                        <?= e($sec['class_name']) ?> - <?= e($sec['section_name']) ?>
                        <?php if ($sec['class_teacher_name']): ?>
                            <div class="text-muted fw-normal" style="font-size: 0.65rem;">CT: <?= e($sec['class_teacher_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($sec_periods as $p):
                        if (!empty($p['is_break'])): ?>
                            <td class="break-cell"><?= strtoupper(e($p['name'])) ?></td>
                        <?php else:
                            $start_key = $p['start'];
                            $day_slots = $indexed_slots[$sec['section_id']][$start_key] ?? [];
                            
                            $cell_slots = [];
                            foreach ($working_days as $day_code) {
                                $day_name = $day_full_names[trim($day_code)] ?? '';
                                if ($selected_day !== 'all' && $selected_day !== $day_name) {
                                    continue;
                                }
                                $day_slots_list = $day_slots[$day_name] ?? [];
                                foreach ($day_slots_list as $slot) {
                                    $slot['day_code'] = $day_code;
                                    $cell_slots[] = $slot;
                                }
                            }
                        ?>
                            <td>
                                <?php foreach ($cell_slots as $slot): ?>
                                    <div class="slot-box">
                                        <?php if ($selected_day === 'all'): ?>
                                            <span class="badge bg-secondary p-0 px-1" style="font-size: 0.55rem;"><?= e($slot['day_code']) ?></span>
                                        <?php endif; ?>
                                        <div class="slot-subject d-inline-block"><?= e($slot['subject_name']) ?></div>
                                        <div class="slot-teacher"><i class="bi bi-person me-1"></i><?= e($slot['teacher_name'] ?? 'No teacher') ?></div>
                                        <?php if ($slot['room_name']): ?>
                                            <div class="slot-room"><i class="bi bi-geo-alt me-1"></i>Room: <?= e($slot['room_name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="text-center mt-4 small text-muted no-print">
        Timetable print generated automatically on <?= date('Y-m-d H:i:s') ?>.
    </div>

</body>
</html>
