<?php
/**
 * EMS v2.0 — Comprehensive Demo Seed
 * Seeds: 2024 (completed) + 2025 (active/current)
 * Run: php seed_demo.php
 */
define('EMS_ROOT', __DIR__);
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Error: This script can only be run from the command line.');
}
require_once EMS_ROOT . '/core/functions.php';


$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "\n=== EMS Demo Seed v2.0 ===\n\n";

// ── helpers ──────────────────────────────────────────────────────────────────
function x(PDO $pdo, string $sql, array $p = []): int {
    $pdo->prepare($sql)->execute($p);
    return (int)$pdo->lastInsertId();
}
function q(PDO $pdo, string $sql, array $p = []): array {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll();
}
function rnd(int $min, int $max): int { return rand($min, $max); }

// ── 1. CLEAR DATA TABLES ─────────────────────────────────────────────────────
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$clears = [
    'holiday_calendar','teacher_class_logs','batch_marks_entry','batch_exams',
    'batch_fee_structures','batch_subjects','batch_enrollments','special_batches',
    'student_subject_choices','student_subject_history','marks_entry','exam_seats',
    'exam_invigilators','exam_subject_config','exam_routine','exam_class_map','exams',
    'fee_payments','payment_void_logs','waivers','fee_ledgers','fee_structures',
    'tc_records','student_documents','student_enrollments','student_profiles',
    'teacher_level_assignments','teacher_subjects','staff_attendance','leave_applications',
    'payroll_lines','payroll_runs','performance_logs','staff_loans','staff_profiles',
    'routine_slots','section_working_days','class_subjects','class_groups',
    'sections','classes','rooms','session_clone_logs','roll_change_logs','academic_sessions',
];
foreach ($clears as $t) {
    try { $pdo->exec("DELETE FROM `$t`"); } catch (Exception $e) {}
}
$pdo->exec("DELETE FROM user_roles WHERE user_id != 1");
$pdo->exec("DELETE FROM users WHERE id != 1");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "Tables cleared.\n";

// ── 2. ACADEMIC SESSIONS ─────────────────────────────────────────────────────
$sess2024 = x($pdo,'INSERT INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)',
    ['Academic Year 2024','2024-01-01','2024-12-31',0,'completed']);
$sess2025 = x($pdo,'INSERT INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)',
    ['Academic Year 2025','2025-01-01','2025-12-31',1,'active']);
$pdo->prepare("UPDATE system_settings SET meta_value=? WHERE meta_key='current_session_id'")->execute([$sess2025]);
echo "Sessions: 2024 (completed), 2025 (current)\n";

// ── 3. ROOMS ─────────────────────────────────────────────────────────────────
$rooms = [];
for ($r = 101; $r <= 115; $r++) {
    $rooms[$r] = x($pdo,'INSERT INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)',
        ["Room $r", (string)$r, (int)floor(($r-101)/5)+1, 40, 'classroom']);
}
$labIds = [];
$labIds['science'] = x($pdo,'INSERT INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)',
    ['Science Lab','SL-01',2,30,'lab']);
$labIds['computer'] = x($pdo,'INSERT INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)',
    ['Computer Lab','CL-01',2,30,'lab']);
x($pdo,'INSERT INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)',
    ['Library','LIB',1,50,'library']);
echo "Rooms: 15 classrooms + 2 labs + library\n";

// ── 4. INSTITUTE LEVELS (already seeded, just fetch IDs) ─────────────────────
$lvls = [];
foreach (q($pdo,'SELECT level_code,id FROM institute_levels') as $r) $lvls[$r['level_code']] = $r['id'];

// ── 5. CLASSES ───────────────────────────────────────────────────────────────
$classIds = [];
$classData = [
    // [name, numeric, level_enum, level_code, order]
    ['Play Group',   0, 'pre_primary',      'pre_primary', 1],
    ['Nursery / KG', 0, 'pre_primary',      'pre_primary', 2],
    ['Class 1',      1, 'primary',          'primary',     3],
    ['Class 2',      2, 'primary',          'primary',     4],
    ['Class 3',      3, 'primary',          'primary',     5],
    ['Class 4',      4, 'primary',          'primary',     6],
    ['Class 5',      5, 'primary',          'primary',     7],
    ['Class 6',      6, 'secondary',        'secondary',   8],
    ['Class 7',      7, 'secondary',        'secondary',   9],
    ['Class 8',      8, 'secondary',        'secondary',   10],
    ['Class 9',      9, 'secondary',        'secondary',   11],
    ['Class 10',    10, 'secondary',        'secondary',   12],
    ['Class 11 (HSC 1st Year)', 11, 'higher_secondary', 'higher_secondary', 13],
    ['Class 12 (HSC 2nd Year)', 12, 'higher_secondary', 'higher_secondary', 14],
];
$roomArr = array_values($rooms);
$rIdx = 0;
foreach ($classData as $cd) {
    $lvlId = $lvls[$cd[3]] ?? null;
    $cid   = x($pdo,'INSERT INTO classes (class_name,class_numeric,class_level,level_id,display_order,status) VALUES (?,?,?,?,?,1)',
        [$cd[0],$cd[1],$cd[2],$lvlId,$cd[4]]);
    $classIds[$cd[0]] = $cid;
}
echo "Classes: " . count($classData) . "\n";

// ── 6. SECTIONS ──────────────────────────────────────────────────────────────
// Pre-primary: 1 section; Class 1-8: A,B; Class 9-12: Science A, Commerce A, Humanities A
$sectionMap = [];
$secRoomIdx = 0;
$secList = [
    'Play Group'          => [['Section A', 'morning']],
    'Nursery / KG'        => [['Section A', 'morning']],
    'Class 1'             => [['Section A','day'],['Section B','day']],
    'Class 2'             => [['Section A','day'],['Section B','day']],
    'Class 3'             => [['Section A','day'],['Section B','day']],
    'Class 4'             => [['Section A','day'],['Section B','day']],
    'Class 5'             => [['Section A','day'],['Section B','day']],
    'Class 6'             => [['Section A','day'],['Section B','day']],
    'Class 7'             => [['Section A','day'],['Section B','day']],
    'Class 8'             => [['Section A','day'],['Section B','day']],
    'Class 9'             => [['Science','day'],['Commerce','day'],['Humanities','day']],
    'Class 10'            => [['Science','day'],['Commerce','day'],['Humanities','day']],
    'Class 11 (HSC 1st Year)' => [['Science','day'],['Commerce','day'],['Humanities','day']],
    'Class 12 (HSC 2nd Year)' => [['Science','day'],['Commerce','day'],['Humanities','day']],
];
foreach ($secList as $className => $sects) {
    $cid = $classIds[$className];
    foreach ($sects as $s) {
        $rId = $roomArr[$secRoomIdx % count($roomArr)];
        $sid = x($pdo,'INSERT INTO sections (class_id,section_name,shift,capacity,room_id,status) VALUES (?,?,?,?,?,1)',
            [$cid,$s[0],$s[1],40,$rId]);
        $sectionMap[$cid][] = ['id'=>$sid,'name'=>$s[0]];
        $secRoomIdx++;
    }
}
$totalSections = array_sum(array_map('count', $sectionMap));
echo "Sections: $totalSections\n";

// ── 7. SUBJECTS ──────────────────────────────────────────────────────────────
$subIds = [];
$subjects = [
    // Pre-primary
    ['Play Activities',        'PLY', 'core',     'pre_primary', 0,0,0,0,0,0],
    ['Bengali Basics',         'BNB', 'core',     'pre_primary', 0,0,0,0,0,0],
    ['Math Basics',            'MTB', 'core',     'pre_primary', 0,0,0,0,0,0],
    ['Drawing & Craft',        'DRW', 'core',     'pre_primary', 0,0,0,0,0,0],
    // Primary (1-5)
    ['Bengali',                'BEN', 'core',     'primary',     0,0,0,0,0,0],
    ['English',                'ENG', 'core',     'primary,secondary,higher_secondary', 0,0,0,0,0,0],
    ['Mathematics',            'MAT', 'core',     'primary,secondary', 0,0,1,0,0,0],
    ['Science',                'SCI', 'core',     'primary,secondary', 0,0,0,0,0,0],
    ['Bangladesh & Global Studies','BGS','core',  'primary,secondary', 0,0,0,0,0,0],
    ['Religious Studies',      'REL', 'religious','primary,secondary,higher_secondary',0,1,0,0,0,0],
    // Secondary (6-8 extra)
    ['General Science',        'GSC', 'core',     'secondary',   0,0,0,0,0,0],
    ['History & World Civilization','HWC','core', 'secondary',   0,0,0,0,0,0],
    ['Geography & Environment','GEO', 'core',     'secondary',   0,0,0,0,0,0],
    ['ICT',                    'ICT', 'core',     'secondary,higher_secondary',0,0,0,0,0,0],
    ['Agriculture / Home Science','AGR','optional','secondary',  0,0,0,0,0,0],
    // Class 9-10 Science
    ['Physics',                'PHY', 'core',     'secondary,higher_secondary',1,0,1,0,1,0],
    ['Chemistry',              'CHM', 'core',     'secondary,higher_secondary',1,0,1,0,1,0],
    ['Biology',                'BIO', 'core',     'secondary,higher_secondary',1,0,1,0,1,0],
    ['Higher Mathematics',     'HMT', 'core',     'secondary,higher_secondary',1,0,0,0,1,0],
    // Class 9-10 Commerce
    ['Accounting',             'ACC', 'core',     'secondary,higher_secondary',1,0,0,0,0,0],
    ['Business Studies',       'BUS', 'core',     'secondary',   1,0,0,0,0,0],
    ['Finance & Banking',      'FNB', 'core',     'secondary,higher_secondary',1,0,0,0,0,0],
    ['Economics',              'ECO', 'core',     'secondary,higher_secondary',1,0,0,0,0,0],
    // Class 9-10 Humanities
    ['Civics',                 'CIV', 'core',     'secondary,higher_secondary',1,0,0,0,0,0],
    ['History',                'HST', 'core',     'secondary,higher_secondary',1,0,0,0,0,0],
    ['Social Work',            'SWK', 'core',     'secondary,higher_secondary',1,0,0,0,0,0],
    // HSC extra
    ['Statistics',             'STA', 'core',     'higher_secondary',1,0,0,0,0,0],
    ['Business Org & Management','BOM','core',    'higher_secondary',1,0,0,0,0,0],
    ['Islamic History & Culture','IHC','core',    'higher_secondary',1,0,0,0,0,0],
    // Alt religious
    ['Hindu Religion',         'HNR', 'religious','secondary,higher_secondary',0,1,0,0,0,1],
    ['Christian Religion',     'CHR', 'religious','secondary,higher_secondary',0,1,0,0,0,1],
    ['Buddhist Religion',      'BDR', 'religious','secondary,higher_secondary',0,1,0,0,0,1],
];
// col order: name,code,type,level_codes, is_group,is_religious,has_practical,is_religious_alt,has_mcq,can_be_3rd
foreach ($subjects as [$nm,$cd,$tp,$lc,$ig,$ir,$hp,$ira,$hmcq,$c3]) {
    $sid = x($pdo,'INSERT INTO subjects (subject_name,subject_code,subject_type,level_codes,is_group_subject,is_religious,has_practical,is_religious_alt,has_mcq,can_be_3rd,can_be_4th,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)',
        [$nm,$cd,$tp,$lc,$ig,$ir,$hp,$ira,$hmcq,$c3,0]);
    $subIds[$cd] = $sid;
}
echo "Subjects: " . count($subIds) . "\n";

// ── 8. CLASS–SUBJECT MAPPING (both sessions) ─────────────────────────────────
$classSubjectSets = [
    'Play Group'          => ['PLY','BNB','MTB','DRW'],
    'Nursery / KG'        => ['PLY','BNB','MTB','DRW'],
    'Class 1'             => ['BEN','ENG','MAT','SCI','BGS','REL','DRW'],
    'Class 2'             => ['BEN','ENG','MAT','SCI','BGS','REL','DRW'],
    'Class 3'             => ['BEN','ENG','MAT','SCI','BGS','REL'],
    'Class 4'             => ['BEN','ENG','MAT','SCI','BGS','REL'],
    'Class 5'             => ['BEN','ENG','MAT','SCI','BGS','REL'],
    'Class 6'             => ['BEN','ENG','MAT','GSC','BGS','HWC','GEO','REL','ICT','AGR'],
    'Class 7'             => ['BEN','ENG','MAT','GSC','BGS','HWC','GEO','REL','ICT','AGR'],
    'Class 8'             => ['BEN','ENG','MAT','GSC','BGS','HWC','GEO','REL','ICT','AGR'],
    'Class 9'             => ['BEN','ENG','MAT','PHY','CHM','BIO','HMT','REL','ICT','ACC','BUS','FNB','ECO','CIV','HST','SWK'],
    'Class 10'            => ['BEN','ENG','MAT','PHY','CHM','BIO','HMT','REL','ICT','ACC','BUS','FNB','ECO','CIV','HST','SWK'],
    'Class 11 (HSC 1st Year)' => ['BEN','ENG','PHY','CHM','BIO','HMT','STA','REL','ICT','ACC','FNB','ECO','BOM','CIV','HST','SWK','IHC'],
    'Class 12 (HSC 2nd Year)' => ['BEN','ENG','PHY','CHM','BIO','HMT','STA','REL','ICT','ACC','FNB','ECO','BOM','CIV','HST','SWK','IHC'],
];
$marks_by_sub = [
    'PLY'=>[50,25],'BNB'=>[50,25],'MTB'=>[50,25],'DRW'=>[50,25],
    'BEN'=>[100,33],'ENG'=>[100,33],'MAT'=>[100,33],'SCI'=>[100,33],'BGS'=>[100,33],
    'REL'=>[100,33],'DRW'=>[100,33],'GSC'=>[100,33],'HWC'=>[100,33],'GEO'=>[100,33],
    'ICT'=>[100,33],'AGR'=>[100,33],'PHY'=>[100,33],'CHM'=>[100,33],'BIO'=>[100,33],
    'HMT'=>[100,33],'ACC'=>[100,33],'BUS'=>[100,33],'FNB'=>[100,33],'ECO'=>[100,33],
    'CIV'=>[100,33],'HST'=>[100,33],'SWK'=>[100,33],'STA'=>[100,33],'BOM'=>[100,33],
    'IHC'=>[100,33],'HNR'=>[100,33],'CHR'=>[100,33],'BDR'=>[100,33],
];
$csInsert = $pdo->prepare('INSERT IGNORE INTO class_subjects (class_id,session_id,subject_id,full_marks_written,pass_marks_written,classes_per_week) VALUES (?,?,?,?,?,?)');
foreach ([$sess2024, $sess2025] as $sessId) {
    foreach ($classSubjectSets as $className => $subCodes) {
        $cid = $classIds[$className];
        foreach ($subCodes as $code) {
            if (!isset($subIds[$code])) continue;
            $mk = $marks_by_sub[$code] ?? [100,33];
            $csInsert->execute([$cid, $sessId, $subIds[$code], $mk[0], $mk[1], rnd(2,5)]);
        }
    }
}
echo "Class-subject mappings created.\n";

// ── 9. GROUPS assigned to Class 9-12 ─────────────────────────────────────────
$groupIds = [];
foreach (q($pdo,'SELECT id,group_code FROM groups_stream') as $r) $groupIds[$r['group_code']] = $r['id'];
// SCI=Science, COM=Commerce, ART=Humanities/Arts
$gcInsert = $pdo->prepare('INSERT IGNORE INTO class_groups (class_id,group_id) VALUES (?,?)');
foreach (['Class 9','Class 10','Class 11 (HSC 1st Year)','Class 12 (HSC 2nd Year)'] as $cn) {
    $cid = $classIds[$cn];
    foreach (['SCI','COM','ART'] as $gc) {
        if (isset($groupIds[$gc])) $gcInsert->execute([$cid, $groupIds[$gc]]);
    }
}
echo "Groups assigned to Class 9-12.\n";

// ── 10. STAFF ────────────────────────────────────────────────────────────────
$staffData = [
    // [username, full_name, desig, desig_role, dept, salary]
    ['principal',   'Md. Abdur Rahman',        'Principal',         'head',     'Administration',  45000],
    ['vice_prin',   'Mrs. Nasrin Sultana',      'Vice Principal',    'academic', 'Administration',  38000],
    ['teacher01',   'Md. Rafiqul Islam',        'Senior Teacher',    'academic', 'Bengali',         28000],
    ['teacher02',   'Md. Aminul Haque',         'Senior Teacher',    'academic', 'English',         28000],
    ['teacher03',   'Mrs. Sharmin Akter',       'Assistant Teacher', 'academic', 'Mathematics',     24000],
    ['teacher04',   'Md. Kamal Uddin',          'Assistant Teacher', 'academic', 'Science',         24000],
    ['teacher05',   'Mrs. Farzana Begum',       'Assistant Teacher', 'academic', 'Social Studies',  22000],
    ['teacher06',   'Md. Shahidul Islam',       'Senior Teacher',    'academic', 'Physics',         30000],
    ['teacher07',   'Mrs. Roksana Khatun',      'Assistant Teacher', 'academic', 'Chemistry',       26000],
    ['teacher08',   'Md. Mahbubur Rahman',      'Senior Teacher',    'academic', 'Mathematics',     30000],
    ['teacher09',   'Mrs. Nasima Akhter',       'Assistant Teacher', 'academic', 'Biology',         26000],
    ['teacher10',   'Md. Sirajul Islam',        'Assistant Teacher', 'academic', 'ICT',             24000],
    ['teacher11',   'Mrs. Lovely Begum',        'Assistant Teacher', 'academic', 'Commerce',        24000],
    ['teacher12',   'Md. Tariqul Hassan',       'Assistant Teacher', 'academic', 'Accounting',      24000],
    ['teacher13',   'Mrs. Shahanara Parvin',    'Junior Teacher',    'academic', 'English',         20000],
    ['teacher14',   'Md. Nurul Islam',          'Junior Teacher',    'academic', 'Bengali',         20000],
    ['teacher15',   'Mrs. Dilruba Yasmin',      'Junior Teacher',    'academic', 'History',         20000],
    ['accountant1', 'Md. Golam Mostafa',        'Accountant',        'admin',    'Finance',         25000],
    ['librarian1',  'Mrs. Hasina Begum',        'Librarian',         'support',  'Library',         18000],
];
$hash = password_hash('teacher123', PASSWORD_BCRYPT);
$desigTypes = [];
foreach (q($pdo,'SELECT id,designation_name FROM designation_types') as $d) $desigTypes[$d['designation_name']] = $d['id'];
$teacherRole = q($pdo,"SELECT id FROM roles WHERE role_slug='teacher'")->fetchColumn ?? null;
$teacherRoleId = q($pdo,"SELECT id FROM roles WHERE role_slug='teacher'")[0]['id'] ?? null;
$principalRoleId = q($pdo,"SELECT id FROM roles WHERE role_slug='principal'")[0]['id'] ?? null;

$staffIds = [];
$empNum = 1001;
foreach ($staffData as $sd) {
    $uid = x($pdo,'INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)',
        [$sd[0],$hash,$sd[1],$sd[0].'@school.edu.bd','active']);
    // Assign teacher role
    $roleSlug = in_array($sd[0],['principal','vice_prin']) ? 'principal' : (in_array($sd[0],['accountant1']) ? 'accountant' : (in_array($sd[0],['librarian1']) ? 'librarian' : 'teacher'));
    $rId = q($pdo,"SELECT id FROM roles WHERE role_slug=?",[strtolower($roleSlug)]);
    if ($rId) x($pdo,'INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)',[$uid,$rId[0]['id']]);
    $dtId = $desigTypes[$sd[2]] ?? null;
    $spid = x($pdo,'INSERT INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,designation_type_id,department,joining_date,contract_type,salary_type,base_salary,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$uid,'EMP-'.(string)$empNum++,
         explode(' ',$sd[1])[0].' '.explode(' ',$sd[1])[1] ?? '',
         implode(' ',array_slice(explode(' ',$sd[1]),2)) ?: 'N/A',
         $sd[2],$dtId,$sd[3],'2020-01-01','permanent','fixed',$sd[5],'active']);
    $staffIds[$sd[0]] = ['uid'=>$uid,'spid'=>$spid];
    $staffIds[$sd[0]]['first'] = explode(' ',$sd[1])[0];
}
echo "Staff: " . count($staffData) . " profiles\n";

// Teacher subject expertise
$expertMap = [
    'teacher01'=>['BEN'],'teacher02'=>['ENG'],'teacher03'=>['MAT'],
    'teacher04'=>['SCI','GSC'],'teacher05'=>['BGS','HWC','GEO'],
    'teacher06'=>['PHY'],'teacher07'=>['CHM'],'teacher08'=>['HMT','MAT'],
    'teacher09'=>['BIO'],'teacher10'=>['ICT'],'teacher11'=>['BUS','BOM','FNB'],
    'teacher12'=>['ACC','ECO','STA'],'teacher13'=>['ENG'],'teacher14'=>['BEN'],
    'teacher15'=>['HST','CIV','SWK','IHC'],
    'principal'=>['BEN'],'vice_prin'=>['ENG'],
];
$tsInsert = $pdo->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id,subject_id) VALUES (?,?)');
foreach ($expertMap as $uname => $codes) {
    if (!isset($staffIds[$uname])) continue;
    $uid = $staffIds[$uname]['uid'];
    foreach ($codes as $code) {
        if (isset($subIds[$code])) $tsInsert->execute([$uid,$subIds[$code]]);
    }
}
echo "Teacher subject expertise assigned.\n";

// Teacher level assignments (all teachers → all levels for now)
$lvlIdsAll = array_values($lvls);
$tlaInsert = $pdo->prepare('INSERT IGNORE INTO teacher_level_assignments (teacher_id,level_id) VALUES (?,?)');
foreach ($staffIds as $skey => $sd) {
    foreach ($lvlIdsAll as $lid) $tlaInsert->execute([$sd['uid'],$lid]);
}

// ── 11. STUDENTS ─────────────────────────────────────────────────────────────
$maleFirst  = ['Rahim','Karim','Arif','Farhan','Rakib','Sabbir','Imran','Mehedi','Tamim','Nayeem','Zahid','Asif','Touhid','Mainul','Riyad','Shihab','Shahadat','Nasir','Monirul','Siam','Toha','Rasel','Nabil','Shahriar','Taufiq','Babul','Saiful','Belal','Habib','Jalal'];
$femFirst   = ['Fatema','Ayasha','Nusrat','Lamia','Sadia','Tania','Shirin','Roksana','Nazma','Mitu','Mahbuba','Sonia','Keya','Asha','Rima','Moni','Puja','Suma','Shapna','Bina','Sumi','Rekha','Meher','Shampa','Jhumur','Shiuli','Tulip','Lovely','Meem','Suraia'];
$lastNames  = ['Ahmed','Islam','Hossain','Uddin','Alam','Khan','Miah','Sheikh','Mondal','Talukder','Sarkar','Biswas','Chowdhury','Das','Dey','Roy','Paul','Jahan','Khatun','Begum','Hasan','Bhuiyan','Chandra','Saha','Nath','Dhar','Kabir','Aziz','Molla','Pramanik'];
$guardFirst = ['Md. Abdul','Mohammad','Md. Rafiqul','Md. Nurul','Md. Sirajul','Md. Kamal','Md. Jahangir','Md. Shafiqul','Md. Mizanur','A.K.M.'];
$religions  = array_merge(array_fill(0,16,'Islam'),['Hindu','Hindu','Christian','Buddhist']);
$sHash      = password_hash('student123', PASSWORD_BCRYPT);
$studentRoleId = q($pdo,"SELECT id FROM roles WHERE role_slug='student'")[0]['id'] ?? null;

$studentIdCounter = 1;
$allStudents = []; // [uid => [class_id,section_id,roll,gender,religion]]
$stdPerSection = 6; // 6 students per section

foreach ($sectionMap as $cid => $sections) {
    $classNumericRow = q($pdo,'SELECT class_numeric FROM classes WHERE id=?',[$cid]);
    $classNum = (int)($classNumericRow[0]['class_numeric'] ?? 1);
    foreach ($sections as $sec) {
        $secId = $sec['id'];
        $secName = $sec['name'];
        // Stream for class 9-12
        $stream = in_array($secName,['Science','Commerce','Humanities']) ? $secName : null;
        for ($i = 1; $i <= $stdPerSection; $i++) {
            $gender   = ($i % 2 === 0) ? 'female' : 'male';
            $fn       = $gender === 'male' ? $maleFirst[array_rand($maleFirst)] : $femFirst[array_rand($femFirst)];
            $ln       = $lastNames[array_rand($lastNames)];
            $religion = $religions[array_rand($religions)];
            $dob      = date('Y-m-d', mktime(0,0,0,rnd(1,12),rnd(1,28),rnd(2000+max(0,$classNum-6),2014-max(0,$classNum-6))));
            $guName   = $guardFirst[array_rand($guardFirst)] . ' ' . $ln;
            $guPhone  = '017' . rnd(10000000,99999999);
            $studIdNo = 'STU-' . str_pad($studentIdCounter++, 5, '0', STR_PAD_LEFT);
            $uname    = strtolower($studIdNo);
            $uid      = x($pdo,'INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)',
                [$uname,$sHash,"$fn $ln",$uname.'@student.edu.bd','active']);
            if ($studentRoleId) x($pdo,'INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)',[$uid,$studentRoleId]);
            x($pdo,'INSERT INTO student_profiles (user_id,student_id_no,first_name,last_name,dob,gender,religion,photo,father_name,mother_name,guardian_name,guardian_phone,guardian_relation,address_present,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$uid,$studIdNo,$fn,$ln,$dob,$gender,$religion,'',
                 'Md. '.$ln.' Sr.','Mrs. '.$fn.' Begum',$guName,$guPhone,'Father',
                 rnd(1,99).' Moholla, Dhaka','2020-01-15']);
            $allStudents[$uid] = ['class_id'=>$cid,'sec_id'=>$secId,'roll'=>$i,'stream'=>$stream];
        }
    }
}
echo "Students created: " . count($allStudents) . "\n";

// ── 12. ENROLLMENTS (2024 & 2025) ────────────────────────────────────────────
$enrInsert = $pdo->prepare('INSERT INTO student_enrollments (student_id,session_id,class_id,section_id,group_id,roll_number,status) VALUES (?,?,?,?,?,?,?)');
// For Class 9-12, determine group based on section name
$sectionGroupMap = [];
foreach ($sectionMap as $cid => $sects) {
    foreach ($sects as $sec) {
        $g = null;
        if ($sec['name'] === 'Science')    $g = $groupIds['SCI'] ?? null;
        if ($sec['name'] === 'Commerce')   $g = $groupIds['COM'] ?? null;
        if ($sec['name'] === 'Humanities') $g = $groupIds['ART'] ?? null;
        $sectionGroupMap[$sec['id']] = $g;
    }
}
$rollBySec = [];
foreach ($allStudents as $uid => $sd) {
    $gid = $sectionGroupMap[$sd['sec_id']] ?? null;
    $roll = ($rollBySec[$sd['sec_id']] ?? 0) + 1;
    $rollBySec[$sd['sec_id']] = $roll;
    // 2024 enrollment (same class — historical data)
    $enrInsert->execute([$uid,$sess2024,$sd['class_id'],$sd['sec_id'],$gid,$roll,'active']);
    // 2025 enrollment (same class — user will promote to 2026 later)
    $enrInsert->execute([$uid,$sess2025,$sd['class_id'],$sd['sec_id'],$gid,$roll,'active']);
}
echo "Enrollments: " . (count($allStudents)*2) . " (2024 + 2025)\n";

// ── 13. FEE STRUCTURES ───────────────────────────────────────────────────────
$catIds = [];
foreach (q($pdo,'SELECT id,category_type FROM fee_categories WHERE status=1') as $r) $catIds[$r['category_type']] = $r['id'];
// Tuition by class level
$tuitionRate = [
    'Play Group'=>400,'Nursery / KG'=>400,
    'Class 1'=>500,'Class 2'=>500,'Class 3'=>500,'Class 4'=>500,'Class 5'=>500,
    'Class 6'=>700,'Class 7'=>700,'Class 8'=>700,
    'Class 9'=>900,'Class 10'=>900,
    'Class 11 (HSC 1st Year)'=>1200,'Class 12 (HSC 2nd Year)'=>1200,
];
$fsInsert = $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)');
foreach ([$sess2024,$sess2025] as $sessId) {
    foreach ($classIds as $className => $cid) {
        $rate = $tuitionRate[$className] ?? 600;
        // Tuition: monthly
        if (isset($catIds['tuition'])) $fsInsert->execute([$sessId,$cid,$catIds['tuition'],$rate,10,'monthly']);
        // Session charge: yearly
        if (isset($catIds['session'])) $fsInsert->execute([$sessId,$cid,$catIds['session'],2000,31,'yearly']);
        // Exam fee: yearly
        if (isset($catIds['exam'])) $fsInsert->execute([$sessId,$cid,$catIds['exam'],500,1,'yearly']);
    }
}
echo "Fee structures created.\n";

// ── 14. FEE LEDGERS & PAYMENTS ───────────────────────────────────────────────
$fsCache = [];
foreach (q($pdo,'SELECT session_id,class_id,fee_category_id,amount,frequency FROM fee_structures') as $r)
    $fsCache[$r['session_id'].'-'.$r['class_id'].'-'.$r['fee_category_id']] = $r;

$ledInsert = $pdo->prepare('INSERT INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,?)');
$payInsert = $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,?,?,?,?,?)');
$acctId = q($pdo,'SELECT id FROM accounts LIMIT 1')[0]['id'] ?? null;
$collectorId = $staffIds['accountant1']['uid'] ?? 1;
$receiptNum = 1000;

foreach ($allStudents as $uid => $sd) {
    foreach ([$sess2024, $sess2025] as $sessId) {
        $year = ($sessId === $sess2024) ? 2024 : 2025;
        // Monthly tuition (Jan–Dec 2024 fully paid; Jan–Jun 2025 paid, Jul–Dec 2025 due)
        if (isset($catIds['tuition'])) {
            $fsKey = $sessId.'-'.$sd['class_id'].'-'.$catIds['tuition'];
            $amt   = $fsCache[$fsKey]['amount'] ?? 600;
            $paidUpTo = ($sessId === $sess2024) ? 12 : 6; // 2024 all paid; 2025 first 6
            for ($m = 1; $m <= 12; $m++) {
                $dueDate = "$year-" . str_pad($m,2,'0',STR_PAD_LEFT) . "-10";
                $status  = ($m <= $paidUpTo) ? 'paid' : 'unpaid';
                $lid = x($pdo,'INSERT INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,amount_paid,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$uid,$sessId,$catIds['tuition'],$amt,($m <= $paidUpTo)?$amt:0,$dueDate,$m,$year,$status]);
                if ($m <= $paidUpTo && $acctId) {
                    $pdate = "$year-" . str_pad($m,2,'0',STR_PAD_LEFT) . "-" . rnd(5,12);
                    x($pdo,'INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,?,?,?,?,?,?)',
                        [$lid,$uid,$amt,$pdate,'cash','RCP-'.(string)$receiptNum++,$collectorId,$acctId,'approved']);
                }
            }
        }
        // Session fee (once)
        if (isset($catIds['session'])) {
            $sfKey = $sessId.'-'.$sd['class_id'].'-'.$catIds['session'];
            $sfAmt = $fsCache[$sfKey]['amount'] ?? 2000;
            $sfPaid = ($sessId === $sess2024) ? $sfAmt : ($sfAmt * 0); // 2024 paid; 2025 unpaid
            $sfStatus = ($sessId === $sess2024) ? 'paid' : 'unpaid';
            $lid2 = x($pdo,'INSERT INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,amount_paid,due_date,year,status) VALUES (?,?,?,?,?,?,?,?)',
                [$uid,$sessId,$catIds['session'],$sfAmt,$sfPaid,"$year-01-31",$year,$sfStatus]);
            if ($sessId === $sess2024 && $acctId)
                x($pdo,'INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$lid2,$uid,$sfAmt,"$year-02-05",'cash','RCP-'.(string)$receiptNum++,$collectorId,$acctId,'approved']);
        }
    }
}
echo "Fee ledgers & payments created.\n";

// ── 15. EXAMS ────────────────────────────────────────────────────────────────
$examConfigs = [
    $sess2024 => [
        ['Half-Yearly Exam 2024','midterm','2024-06-01','2024-06-20','results_published'],
        ['Annual Exam 2024',     'annual', '2024-11-01','2024-11-25','results_published'],
    ],
    $sess2025 => [
        ['Half-Yearly Exam 2025','midterm','2025-06-01','2025-06-20','results_published'],
        ['Annual Exam 2025',     'annual', '2025-11-01','2025-11-25','scheduled'],
    ],
];
$examIds = [];
$escInsert = $pdo->prepare('INSERT IGNORE INTO exam_subject_config (exam_id,class_id,subject_id,full_marks_written,pass_marks_written,exam_date,exam_start_time,exam_end_time) VALUES (?,?,?,?,?,?,?,?)');
$ecmInsert = $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)');
$marksInsert = $pdo->prepare('INSERT IGNORE INTO marks_entry (exam_id,student_id,class_id,section_id,subject_id,marks_written,is_absent,entered_by) VALUES (?,?,?,?,?,?,?,?)');

foreach ($examConfigs as $sessId => $exList) {
    $year = ($sessId === $sess2024) ? 2024 : 2025;
    foreach ($exList as [$eName,$eType,$eStart,$eEnd,$eStatus]) {
        $eid = x($pdo,'INSERT INTO exams (session_id,exam_name,exam_type,scope,start_date,end_date,status) VALUES (?,?,?,?,?,?,?)',
            [$sessId,$eName,$eType,'all_classes',$eStart,$eEnd,$eStatus]);
        $examIds[] = $eid;

        // Map all classes
        foreach ($classIds as $cid) $ecmInsert->execute([$eid,$cid]);

        // Subject config per class
        $subjectDate = new DateTime($eStart);
        foreach ($classIds as $className => $cid) {
            $classSubs = $classSubjectSets[$className] ?? [];
            foreach ($classSubs as $code) {
                if (!isset($subIds[$code])) continue;
                $escInsert->execute([$eid,$cid,$subIds[$code],100,33,$subjectDate->format('Y-m-d'),'10:00','13:00']);
                $subjectDate->modify('+1 day');
            }
        }

        // Enter marks (only for published exams)
        if ($eStatus === 'results_published') {
            foreach ($allStudents as $uid => $sd) {
                $classSubs = $classSubjectSets[
                    array_search($sd['class_id'], $classIds)
                ] ?? [];
                // Determine student grade level (weak/average/good) based on uid mod
                $level = ($uid % 10) < 2 ? 'weak' : (($uid % 10) < 7 ? 'avg' : 'good');
                foreach ($classSubs as $code) {
                    if (!isset($subIds[$code])) continue;
                    $absent = (rnd(1,20) === 1) ? 1 : 0;
                    $marks = 0;
                    if (!$absent) {
                        if ($level === 'good')   $marks = rnd(75,95);
                        elseif ($level === 'avg') $marks = rnd(45,74);
                        else                      $marks = rnd(25,44);
                    }
                    $marksInsert->execute([$eid,$uid,$sd['class_id'],$sd['sec_id'],$subIds[$code],$absent?null:$marks,$absent,1]);
                }
            }
        }
    }
}
echo "Exams: " . count($examIds) . " (with marks for published ones)\n";

// ── 16. STUDENT ATTENDANCE (sample — 2025 last 30 school days) ───────────────
$attInsert = $pdo->prepare('INSERT IGNORE INTO student_attendance (student_id,session_id,class_id,section_id,attendance_date,status,marked_by) VALUES (?,?,?,?,?,?,?)');
$schoolDays = [];
$dt = new DateTime('2025-06-01');
$end = new DateTime('2025-06-30');
while ($dt <= $end) {
    if (!in_array($dt->format('N'),[5,6])) $schoolDays[] = $dt->format('Y-m-d');
    $dt->modify('+1 day');
}
foreach ($allStudents as $uid => $sd) {
    foreach ($schoolDays as $aDate) {
        $status = (rnd(1,10) > 1) ? 'present' : 'absent'; // 90% present
        $attInsert->execute([$uid,$sess2025,$sd['class_id'],$sd['sec_id'],$aDate,$status,1]);
    }
}
echo "Attendance: " . count($allStudents) . " students × " . count($schoolDays) . " days\n";

// ── 17. STAFF ATTENDANCE (2025 June sample) ───────────────────────────────────
$sAttInsert = $pdo->prepare('INSERT IGNORE INTO staff_attendance (staff_id,attendance_date,status,in_time,out_time,marked_by) VALUES (?,?,?,?,?,?)');
foreach ($staffIds as $sdata) {
    foreach ($schoolDays as $aDate) {
        $present = rnd(1,10) > 0;  // 95%+ present
        $status  = $present ? 'present' : 'absent';
        $sAttInsert->execute([$sdata['uid'],$aDate,$status,$present?'08:30':null,$present?'16:00':null,1]);
    }
}
echo "Staff attendance: " . count($staffIds) . " staff × " . count($schoolDays) . " days\n";

// ── 18. HOLIDAYS (2024 & 2025) ───────────────────────────────────────────────
$hInsert = $pdo->prepare('INSERT INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)');
$bdHolidays = [
    '01-01'=>"New Year's Day",'02-21'=>'Language Martyrs Day','03-17'=>'Birthday of Father of Nation',
    '03-25'=>'Genocide Remembrance Day','03-26'=>'Independence Day','04-14'=>'Bengali New Year',
    '04-17'=>'Mujibnagar Day','05-01'=>'International Workers Day',
    '08-15'=>'National Mourning Day','12-16'=>'Victory Day',
];
foreach ([$sess2024=>2024,$sess2025=>2025] as $sessId=>$yr) {
    // Fridays
    $d = new DateTime("$yr-01-01"); $e = new DateTime("$yr-12-31");
    while ((int)$d->format('N') !== 5) $d->modify('+1 day');
    while ($d <= $e) {
        $hInsert->execute([$sessId,$d->format('Y-m-d'),'Friday (Weekly Holiday)','weekend']);
        $d->modify('+7 days');
    }
    // National holidays
    foreach ($bdHolidays as $mmdd=>$name)
        $hInsert->execute([$sessId,"$yr-$mmdd",$name,'govt']);
}
echo "Holidays seeded for 2024 & 2025 (Fridays + national).\n";

// ── 19. NOTICES (a few) ───────────────────────────────────────────────────────
$notices = [
    ['Annual Exam 2024 Results Published','Results for Annual Exam 2024 are now available. Students can collect their mark sheets from the office.','all','2024-12-30'],
    ['Half-Yearly Exam 2025 Schedule','Half-Yearly Examination 2025 will commence from 1st June 2025. Routine attached below.','all','2025-05-15'],
    ['Eid Holiday Notice','The institute will remain closed from Eid-ul-Adha. Classes will resume after the holiday.','all','2025-06-05'],
    ['Fee Collection Notice','Monthly tuition fees for June 2025 are now due. Please pay by 10th June to avoid late fines.','students','2025-06-01'],
    ['Parent-Teacher Meeting','A Parent-Teacher Meeting is scheduled for 25 June 2025. All guardians are requested to attend.','all','2025-06-20'],
];
foreach ($notices as [$title,$content,$audience,$pdate])
    x($pdo,'INSERT INTO notices (title,content,audience,created_by,publish_date) VALUES (?,?,?,?,?)',[$title,$content,$audience,1,$pdate]);
echo "Notices: " . count($notices) . "\n";

// ── 20. SECTION CLASS TEACHERS (assign first staff to sections) ───────────────
$staffUids = array_column($staffIds,'uid');
$tIdx = 0;
$pdo->beginTransaction();
foreach ($sectionMap as $cid => $sects) {
    foreach ($sects as $sec) {
        $teacherUid = $staffUids[($tIdx++ % count($staffUids))];
        $pdo->prepare('UPDATE sections SET class_teacher_id=? WHERE id=?')->execute([$teacherUid,$sec['id']]);
    }
}
$pdo->commit();
echo "Class teachers assigned.\n";

// ── UPDATE ACCOUNT BALANCE ────────────────────────────────────────────────────
$totalPaid = q($pdo,'SELECT SUM(amount) FROM fee_payments WHERE approval_status="approved"')[0]['SUM(amount)'] ?? 0;
$pdo->prepare('UPDATE accounts SET current_balance=? WHERE id=?')->execute([$totalPaid,$acctId]);
echo "Account balance updated: ৳" . number_format((float)$totalPaid,2) . "\n";

// ── DONE ──────────────────────────────────────────────────────────────────────
echo "\n✓ Demo seed complete!\n";
echo "  Sessions : 2 (2024 completed, 2025 active)\n";
echo "  Classes  : " . count($classIds) . "\n";
echo "  Sections : $totalSections\n";
echo "  Students : " . count($allStudents) . "\n";
echo "  Staff    : " . count($staffData) . "\n";
echo "  Exams    : " . count($examIds) . "\n";
echo "  Holidays : 2 years (Fridays + BD national)\n";
echo "\nLogin → admin / admin123\n";
echo "All teachers → teacher123\n";
echo "All students → student123\n\n";
