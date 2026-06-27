<?php
header('Content-Type: text/plain');

require_once __DIR__ . '/core/functions.php';

echo "EMS Database Integrity & Orphans Check\n";
echo "=====================================\n\n";

$pdo = db();

function check_orphans($pdo, $table, $col, $refTable, $refCol) {
    echo "Checking orphans in `$table` on `$col` referencing `$refTable($refCol)`...\n";
    $sql = "SELECT COUNT(*) FROM `$table` t LEFT JOIN `$refTable` r ON r.`$refCol` = t.`$col` WHERE t.`$col` IS NOT NULL AND r.`$refCol` IS NULL";
    try {
        $count = $pdo->query($sql)->fetchColumn();
        if ($count > 0) {
            echo "🚨 FOUND $count ORPHANED RECORDS!\n";
            // Print a sample of orphans
            $sampleSql = "SELECT t.id, t.`$col` FROM `$table` t LEFT JOIN `$refTable` r ON r.`$refCol` = t.`$col` WHERE t.`$col` IS NOT NULL AND r.`$refCol` IS NULL LIMIT 5";
            $samples = $pdo->query($sampleSql)->fetchAll();
            foreach ($samples as $s) {
                echo "   - Row ID: {$s['id']}, Value: {$s[$col]}\n";
            }
        } else {
            echo "   ✅ Clean (0 orphans)\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Query error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

check_orphans($pdo, 'student_enrollments', 'student_id', 'users', 'id');
check_orphans($pdo, 'staff_profiles', 'user_id', 'users', 'id');
check_orphans($pdo, 'user_roles', 'user_id', 'users', 'id');
check_orphans($pdo, 'user_roles', 'role_id', 'roles', 'id');
check_orphans($pdo, 'routine_slots', 'class_id', 'classes', 'id');
check_orphans($pdo, 'routine_slots', 'section_id', 'sections', 'id');
check_orphans($pdo, 'routine_slots', 'subject_id', 'subjects', 'id');
check_orphans($pdo, 'routine_slots', 'teacher_id', 'users', 'id');
check_orphans($pdo, 'exam_seats', 'student_id', 'users', 'id');
check_orphans($pdo, 'exam_seats', 'room_id', 'rooms', 'id');
check_orphans($pdo, 'marks_entry', 'student_id', 'users', 'id');
check_orphans($pdo, 'marks_entry', 'subject_id', 'subjects', 'id');
check_orphans($pdo, 'fee_payments', 'student_id', 'users', 'id');
check_orphans($pdo, 'fee_ledgers', 'student_id', 'users', 'id');

echo "Database check finished.\n";
