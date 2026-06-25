<?php
/**
 * EMS Demo Data Manager
 * Seed or wipe 3 years of realistic Bangladesh school data.
 * SECURITY: Delete this file before going live.
 */
define('EMS_ROOT', __DIR__);
require_once EMS_ROOT . '/config/constants.php';
require_once EMS_ROOT . '/core/functions.php';

session_name('EMS_SESS');
session_start();

$action = $_POST['action'] ?? '';
$messages = [];
$errors   = [];

// ── Helpers ──────────────────────────────────────────────────────────────────
function rnd_int(int $min, int $max): int { return rand($min, $max); }
function rnd_float(float $min, float $max, int $dp = 1): float {
    return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $dp);
}
function rnd_item(array $arr) { return $arr[array_rand($arr)]; }
function bd_phone(): string { return '01'.rnd_item(['3','4','5','6','7','8','9']).str_pad(rand(10000000,99999999),8,'0',STR_PAD_LEFT); }

// Bangladeshi name pools
$MALE_FIRST   = ['Abdullah','Ahmed','Amin','Anwar','Arafat','Arif','Ashraf','Atik','Aziz','Borhan',
                  'Dalim','Farhan','Faruk','Forhad','Golam','Hafiz','Harun','Hasan','Ibrahim','Imran',
                  'Jahangir','Jamal','Kabir','Kamal','Khaled','Liton','Mahbub','Masum','Minhaj',
                  'Mizanur','Nazmul','Nasir','Nabil','Nahid','Palash','Pervez','Raihan','Rahim',
                  'Rakib','Rifat','Ripon','Sagor','Salim','Shahed','Shamim','Sharif','Shohel',
                  'Sohag','Sumon','Tanvir','Tariq','Wahid','Wasim','Yasin','Zahir','Zakir'];
$FEMALE_FIRST = ['Afsana','Aisha','Akhi','Alia','Amena','Arifa','Asma','Ayesha','Bilkis',
                  'Champa','Dipa','Dilruba','Farhana','Fariha','Fatema','Gulnaz','Hasina',
                  'Israt','Jasmine','Jesmin','Khaleda','Lima','Lubna','Marium','Mita','Mitu',
                  'Mohua','Monira','Munni','Nasrin','Nazmun','Nilufar','Nipa','Parveen',
                  'Roksana','Runa','Sabina','Sadia','Saima','Salma','Sharmin','Shilpi',
                  'Sumaiya','Sumona','Sumi','Tania','Urmi','Yasmin','Zannat'];
$LAST_NAMES   = ['Ahmed','Akhter','Alam','Ali','Bhuiyan','Choudhury','Das','Gazi','Hossain',
                  'Huda','Islam','Jahan','Kabir','Karim','Khan','Khatun','Mahmud','Majumdar',
                  'Mia','Molla','Mondal','Mridha','Paul','Rahman','Roy','Sarkar','Siddique',
                  'Talukder','Uddin','Ullah'];
$RELIGIONS    = ['Islam','Islam','Islam','Islam','Islam','Islam','Islam','Islam','Hinduism','Hinduism','Christianity','Buddhism'];
$BLOOD_GROUPS = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$DEPARTMENTS  = ['Science','Commerce','Arts & Humanities','Administration'];

function gen_name(array $male, array $female, array $last, &$gender = null): array {
    $isMale = rand(0,1);
    $gender = $isMale ? 'male' : 'female';
    $first  = $isMale ? $male[array_rand($male)] : $female[array_rand($female)];
    $lname  = $last[array_rand($last)];
    return [$first, $lname];
}

// ── SEED ─────────────────────────────────────────────────────────────────────
if ($action === 'seed') {
    set_time_limit(300);
    ini_set('memory_limit', '256M');
    $pdo = db();
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    global $MALE_FIRST, $FEMALE_FIRST, $LAST_NAMES, $RELIGIONS, $BLOOD_GROUPS, $DEPARTMENTS;

    // ── 1. School Settings ───────────────────────────────────────────────────
    $settings = [
        ['school_name',       'Dhaka Model High School & College', 'general'],
        ['school_address',    'House 14, Road 5, Dhanmondi, Dhaka-1205', 'general'],
        ['school_phone',      '02-9876543', 'general'],
        ['school_email',      'info@dmhsc.edu.bd', 'general'],
        ['school_type',       'school_and_college', 'general'],
        ['academic_board',    'DEB', 'academic'],
        ['date_format',       'd M Y', 'general'],
        ['timezone',          'Asia/Dhaka', 'general'],
        ['currency_symbol',   '৳', 'finance'],
        ['receipt_prefix',    'DMHSC', 'finance'],
        ['working_days',      'Sat,Sun,Mon,Tue,Wed', 'academic'],
        ['per_page',          '25', 'general'],
    ];
    $sStmt = $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)');
    foreach ($settings as $s) $sStmt->execute($s);
    $messages[] = '✅ School settings updated.';

    // ── 2. Academic Sessions ─────────────────────────────────────────────────
    $pdo->exec("UPDATE academic_sessions SET is_current=0");
    $sessionDef = [
        ['2023', '2023-01-01', '2023-12-31', 0, 'completed'],
        ['2024', '2024-01-01', '2024-12-31', 0, 'completed'],
        ['2025', '2025-01-01', '2025-12-31', 1, 'active'],
    ];
    $ssStmt = $pdo->prepare('INSERT IGNORE INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)');
    foreach ($sessionDef as $sd) $ssStmt->execute($sd);
    $sessions = $pdo->query("SELECT id,session_name FROM academic_sessions ORDER BY start_date")->fetchAll(PDO::FETCH_KEY_PAIR);
    $sess2023 = array_search('2023',$sessions); $sess2024=array_search('2024',$sessions); $sess2025=array_search('2025',$sessions);
    $pdo->prepare('UPDATE system_settings SET meta_value=? WHERE meta_key="current_session_id"')->execute([$sess2025]);
    $messages[] = '✅ 3 academic sessions created (2023, 2024, 2025).';

    // ── 3. Classes & Sections ────────────────────────────────────────────────
    $classDef = [
        ['Playgroup',        0,  'playgroup',        1],
        ['KG (Nursery)',     1,  'playgroup',        2],
        ['Class 1',          1,  'primary',          3],
        ['Class 2',          2,  'primary',          4],
        ['Class 3',          3,  'primary',          5],
        ['Class 4',          4,  'primary',          6],
        ['Class 5',          5,  'primary',          7],
        ['Class 6',          6,  'secondary',        8],
        ['Class 7',          7,  'secondary',        9],
        ['Class 8',          8,  'secondary',        10],
        ['Class 9 (SSC-I)',  9,  'secondary',        11],
        ['Class 10 (SSC-II)',10,  'secondary',        12],
        ['HSC 1st Year',    11,  'higher_secondary', 13],
        ['HSC 2nd Year',    12,  'higher_secondary', 14],
    ];
    $clsStmt = $pdo->prepare('INSERT IGNORE INTO classes (class_name,class_numeric,class_level,display_order) VALUES (?,?,?,?)');
    foreach ($classDef as $c) $clsStmt->execute($c);
    $classMap = $pdo->query("SELECT class_name,id FROM classes")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Sections per class
    $sectionDef = [
        'Playgroup'         => [['Flower','morning',35]],
        'KG (Nursery)'      => [['Blossom','morning',35]],
        'Class 1'           => [['A','morning',45],['B','day',45]],
        'Class 2'           => [['A','morning',45],['B','day',45]],
        'Class 3'           => [['A','morning',45],['B','day',45]],
        'Class 4'           => [['A','morning',45],['B','day',45]],
        'Class 5'           => [['A','morning',45],['B','day',45]],
        'Class 6'           => [['A','morning',50],['B','day',50],['C','evening',50]],
        'Class 7'           => [['A','morning',50],['B','day',50],['C','evening',50]],
        'Class 8'           => [['A','morning',50],['B','day',50],['C','evening',50]],
        'Class 9 (SSC-I)'   => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
        'Class 10 (SSC-II)' => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
        'HSC 1st Year'      => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
        'HSC 2nd Year'      => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
    ];
    $secStmt = $pdo->prepare('INSERT IGNORE INTO sections (class_id,section_name,shift,capacity) VALUES (?,?,?,?)');
    foreach ($sectionDef as $className => $sections) {
        $cid = $classMap[$className] ?? null;
        if (!$cid) continue;
        foreach ($sections as [$sname,$shift,$cap]) $secStmt->execute([$cid,$sname,$shift,$cap]);
    }
    // Build section map: class_name => section_name => id
    $secRows = $pdo->query("SELECT s.id,s.section_name,c.class_name FROM sections s JOIN classes c ON c.id=s.class_id")->fetchAll();
    $sectionMap = [];
    foreach ($secRows as $r) $sectionMap[$r['class_name']][$r['section_name']] = $r['id'];
    $messages[] = '✅ 14 classes and '.array_sum(array_map('count',$sectionDef)).' sections created.';

    // ── 4. Groups ────────────────────────────────────────────────────────────
    $groupDef = [['Science','SCI','both'],['Commerce','COM','both'],['Arts','ART','both']];
    $grpStmt  = $pdo->prepare('INSERT IGNORE INTO groups_stream (group_name,group_code,applicable_from_class_level) VALUES (?,?,?)');
    foreach ($groupDef as $g) $grpStmt->execute($g);
    $groupMap = $pdo->query("SELECT group_name,id FROM groups_stream")->fetchAll(PDO::FETCH_KEY_PAIR);
    $cgStmt   = $pdo->prepare('INSERT IGNORE INTO class_groups (class_id,group_id) VALUES (?,?)');
    foreach (['Class 9 (SSC-I)','Class 10 (SSC-II)','HSC 1st Year','HSC 2nd Year'] as $cn) {
        $cid = $classMap[$cn] ?? null;
        if (!$cid) continue;
        foreach ($groupMap as $gid) $cgStmt->execute([$cid,$gid]);
    }
    $messages[] = '✅ Science, Commerce, Arts groups assigned.';

    // ── 5. Rooms ─────────────────────────────────────────────────────────────
    $roomDef = [
        ['Room 101','101',1,45,'classroom'],['Room 102','102',1,45,'classroom'],
        ['Room 103','103',1,45,'classroom'],['Room 104','104',1,45,'classroom'],
        ['Room 201','201',2,50,'classroom'],['Room 202','202',2,50,'classroom'],
        ['Room 203','203',2,50,'classroom'],['Room 204','204',2,50,'classroom'],
        ['Room 301','301',3,50,'classroom'],['Room 302','302',3,50,'classroom'],
        ['Science Lab','SL-1',1,32,'lab'],['Chemistry Lab','CL-1',2,30,'lab'],
        ['Computer Lab','CMP-1',2,25,'lab'],['Library','LIB',1,60,'library'],
        ['Principal Office','PRIN',1,5,'office'],['Vice Principal Office','VP',1,5,'office'],
        ['Exam Hall 1','EH-1',3,80,'exam_hall'],['Exam Hall 2','EH-2',3,80,'exam_hall'],
    ];
    $rStmt = $pdo->prepare('INSERT IGNORE INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)');
    foreach ($roomDef as $r) $rStmt->execute($r);
    $roomIds = $pdo->query("SELECT id FROM rooms WHERE room_type='classroom' ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    $messages[] = '✅ '.count($roomDef).' rooms created.';

    // ── 6. Subjects ──────────────────────────────────────────────────────────
    $subjectDef = [
        // [name, code, type, is_group, is_rel, can_4th, has_prac, has_mcq]
        ['Bangla 1st Paper',     'BAN1','core',   0,0,0,0,1],
        ['Bangla 2nd Paper',     'BAN2','core',   0,0,0,0,0],
        ['English 1st Paper',    'ENG1','core',   0,0,0,0,1],
        ['English 2nd Paper',    'ENG2','core',   0,0,0,0,0],
        ['Mathematics',          'MATH','core',   0,0,0,0,1],
        ['Higher Mathematics',   'HMAT','4th_subject',1,0,1,0,1],
        ['General Science',      'GSCI','core',   0,0,0,1,1],
        ['Physics',              'PHY', 'core',   1,0,0,1,1],
        ['Chemistry',            'CHE', 'core',   1,0,0,1,1],
        ['Biology',              'BIO', 'core',   1,0,0,1,1],
        ['Accounting',           'ACC', 'core',   1,0,0,0,1],
        ['Finance & Banking',    'FIN', 'core',   1,0,0,0,1],
        ['Business Entrepreneur','BEP', 'core',   1,0,0,0,1],
        ['Geography',            'GEO', 'core',   1,0,0,0,1],
        ['History of Bangladesh','HIS', 'core',   1,0,0,0,1],
        ['Civics & Governance',  'CIV', 'core',   1,0,0,0,1],
        ['Bangladesh & World',   'BWI', 'core',   0,0,0,0,1],
        ['ICT',                  'ICT', 'core',   0,0,0,1,1],
        ['Islam Studies',        'ISL', 'religious',0,1,0,0,1],
        ['Hindu Studies',        'HIN', 'religious',0,1,0,0,1],
        ['Arts & Crafts',        'ART', 'optional',0,0,0,1,0],
        ['Physical Education',   'PE',  'optional',0,0,0,1,0],
        ['Career Education',     'CAR', 'optional',0,0,0,0,0],
        ['Social Studies',       'SOC', 'core',   0,0,0,0,1],
        ['Science (Primary)',     'SCI', 'core',   0,0,0,0,1],
    ];
    $subjStmt = $pdo->prepare('INSERT IGNORE INTO subjects (subject_name,subject_code,subject_type,is_group_subject,is_religious_alt,can_be_4th,has_practical,has_mcq) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($subjectDef as $s) $subjStmt->execute($s);
    $subjMap = $pdo->query("SELECT subject_code,id FROM subjects")->fetchAll(PDO::FETCH_KEY_PAIR);
    $messages[] = '✅ '.count($subjectDef).' subjects created.';

    // ── 7. Class-Subject Mappings ─────────────────────────────────────────────
    // Primary classes: Bangla1, English1, Math, SciencePrim, SocialStudies, Islam, Arts
    // Secondary 6-8: Bangla1, Bangla2, English1, English2, Math, GenSci, BangladeshWorld, ICT, Islam
    // Secondary 9-10: Bangla1, Bangla2, English1, English2, Math, ICT, Islam (common), then group subjects
    $csStmt = $pdo->prepare('INSERT IGNORE INTO class_subjects (class_id,session_id,subject_id,group_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical) VALUES (?,?,?,?,?,?,?,?,?,?)');

    $primarySubjs  = ['BAN1','ENG1','MATH','SCI','SOC','ISL','ART'];
    $juniorSubjs   = ['BAN1','BAN2','ENG1','ENG2','MATH','GSCI','BWI','ICT','ISL'];
    $seniorCommon  = ['BAN1','BAN2','ENG1','ENG2','MATH','ICT','ISL'];
    $sciSubjs      = ['PHY','CHE','BIO'];
    $comSubjs      = ['ACC','FIN','BEP'];
    $artSubjs      = ['GEO','HIS','CIV'];
    $sciGrpId      = $groupMap['Science'] ?? null;
    $comGrpId      = $groupMap['Commerce'] ?? null;
    $artGrpId      = $groupMap['Arts'] ?? null;

    foreach ([$sess2023,$sess2024,$sess2025] as $sessId) {
        foreach (['Class 1','Class 2','Class 3','Class 4','Class 5'] as $cn) {
            $cid = $classMap[$cn]??null; if (!$cid) continue;
            foreach ($primarySubjs as $sc) {
                $sid = $subjMap[$sc]??null; if (!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0]);
            }
        }
        foreach (['Class 6','Class 7','Class 8'] as $cn) {
            $cid = $classMap[$cn]??null; if (!$cid) continue;
            foreach ($juniorSubjs as $sc) {
                $sid = $subjMap[$sc]??null; if (!$sid) continue;
                $fm = in_array($sc,['BAN2','ENG2'])?100:($sc==='ICT'?75:100);
                $csStmt->execute([$cid,$sessId,$sid,null,$fm,0,0,33,0,0]);
            }
        }
        foreach (['Class 9 (SSC-I)','Class 10 (SSC-II)'] as $cn) {
            $cid = $classMap[$cn]??null; if (!$cid) continue;
            foreach ($seniorCommon as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0]);
            }
            foreach ($sciSubjs as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,$sciGrpId,75,25,25,25,10,10]);
            }
            foreach ($comSubjs as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,$comGrpId,100,0,0,33,0,0]);
            }
            foreach ($artSubjs as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,$artGrpId,100,0,0,33,0,0]);
            }
        }
        foreach (['HSC 1st Year','HSC 2nd Year'] as $cn) {
            $cid = $classMap[$cn]??null; if (!$cid) continue;
            foreach ($seniorCommon as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0]);
            }
            foreach ($sciSubjs as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,$sciGrpId,75,25,30,25,10,12]);
            }
            foreach ($comSubjs as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,$comGrpId,100,0,0,33,0,0]);
            }
            foreach ($artSubjs as $sc) {
                $sid=$subjMap[$sc]??null; if(!$sid) continue;
                $csStmt->execute([$cid,$sessId,$sid,$artGrpId,100,0,0,33,0,0]);
            }
        }
    }
    $messages[] = '✅ Class-subject mappings created for all 3 sessions.';

    // ── 8. Staff ─────────────────────────────────────────────────────────────
    $staffDef = [
        ['principal',   'A. B. M. Mizanur',  'Rahman',     'Principal',             'Administration', 'permanent','fixed',85000, '2015-01-01'],
        ['vp_shahida',  'Shahida',            'Begum',      'Vice Principal',         'Administration','permanent','fixed',72000, '2016-03-15'],
        ['kamal_vp',    'Kamal',              'Hossain',    'Vice Principal (Acad.)', 'Administration','permanent','fixed',70000, '2017-06-01'],
        ['aminul_sci',  'Dr. Aminul',         'Islam',      'Head of Science Dept.',  'Science',       'permanent','fixed',65000, '2014-08-01'],
        ['rashida_com', 'Rashidun',           'Naher',      'Head of Commerce Dept.', 'Commerce',      'permanent','fixed',62000, '2015-04-01'],
        ['kabir_arts',  'Prof. Kabir',        'Ahmed',      'Head of Arts Dept.',     'Arts & Humanities','permanent','fixed',62000,'2013-09-01'],
        ['t_physics',   'Md. Shahadat',       'Hossain',    'Assistant Teacher',      'Science',       'permanent','fixed',45000, '2018-01-15'],
        ['t_chemistry', 'Mina',               'Akter',      'Assistant Teacher',      'Science',       'permanent','fixed',44000, '2019-03-01'],
        ['t_biology',   'Md. Delwar',         'Hossain',    'Assistant Teacher',      'Science',       'permanent','fixed',44000, '2018-07-01'],
        ['t_math',      'Gias Uddin',         'Ahmed',      'Senior Teacher (Math)',  'Science',       'permanent','fixed',48000, '2016-01-01'],
        ['t_bangla',    'Razia',              'Sultana',    'Senior Teacher (Bangla)','Arts & Humanities','permanent','fixed',46000,'2015-09-01'],
        ['t_english',   'Mosharraf',          'Hossain',    'Senior Teacher (English)','Arts & Humanities','permanent','fixed',47000,'2015-03-01'],
        ['t_ict',       'Sazzad',             'Islam',      'Teacher (ICT)',          'Science',       'permanent','fixed',40000, '2020-01-01'],
        ['t_acc',       'Rokshana',           'Parveen',    'Teacher (Accounting)',   'Commerce',      'permanent','fixed',43000, '2017-08-01'],
        ['t_fin',       'Md. Jakir',          'Hossain',    'Teacher (Finance)',      'Commerce',      'permanent','fixed',42000, '2018-02-01'],
        ['t_geo',       'Sultana',            'Begum',      'Teacher (Geography)',    'Arts & Humanities','permanent','fixed',41000,'2019-01-01'],
        ['t_pe',        'Aminur',             'Rahman',     'Teacher (Physical Ed.)', 'Administration','permanent','fixed',38000, '2020-06-01'],
        ['t_junior1',   'Ferdousi',           'Akter',      'Junior Teacher',         'Science',       'contractual','fixed',32000,'2021-01-01'],
        ['t_junior2',   'Monir',              'Hossain',    'Junior Teacher',         'Arts & Humanities','contractual','fixed',30000,'2022-03-01'],
        ['t_guest1',    'Dr. Shamsul',        'Islam',      'Guest Lecturer',         'Science',       'guest_lecturer','class_wise',8000,'2023-01-01'],
        ['clerk',       'Shahidul',           'Islam',      'Office Clerk',           'Administration','permanent','fixed',22000, '2016-05-01'],
        ['accountant',  'Jasmine',            'Akter',      'Accountant',             'Administration','permanent','fixed',35000, '2017-01-01'],
        ['librarian',   'Shahed',             'Ali',        'Librarian',              'Administration','permanent','fixed',28000, '2018-04-01'],
        ['store_mgr',   'Golam',              'Mustafa',    'Store Manager',          'Administration','permanent','fixed',25000, '2019-07-01'],
        ['lab_asst1',   'Sumon',              'Kumar Das',  'Lab Assistant',          'Science',       'permanent','fixed',20000, '2020-01-01'],
    ];

    $staffRoles = [
        'principal'  => 'principal',
        'vp_shahida' => 'vice_principal',
        'kamal_vp'   => 'vice_principal',
        'accountant' => 'accountant',
        'librarian'  => 'librarian',
        'store_mgr'  => 'store_manager',
    ];
    $allRoles = $pdo->query("SELECT role_slug,id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
    $teacherRoleId = $allRoles['teacher'] ?? null;

    $staffIds = [];
    foreach ($staffDef as $sd) {
        [$uname,$fname,$lname,$desig,$dept,$contract,$salary_type,$salary,$joining] = $sd;
        $fullName = "$fname $lname";
        $email    = strtolower(preg_replace('/[^a-z]/','',strtolower($fname))).'@dmhsc.edu.bd';
        // Create user if not exists
        $ck = $pdo->prepare('SELECT id FROM users WHERE username=?'); $ck->execute([$uname]); $uid=$ck->fetchColumn();
        if (!$uid) {
            $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')->execute([$uname,password_hash('Staff@123',PASSWORD_BCRYPT),$fullName,$email,'active']);
            $uid = (int)$pdo->lastInsertId();
        }
        $empId = 'EMP-'.(int)date('Y',strtotime($joining)).'-'.str_pad(array_search($sd,$staffDef)+1,3,'0',STR_PAD_LEFT);
        $gender = ['Shahida','Mina','Razia','Rokshana','Sultana','Ferdousi','Jasmine','Sumon Kumar Das'] ;
        $isFemale = in_array($fname,['Shahida','Mina','Razia','Rokshana','Sultana','Ferdousi','Jasmine']);
        $pdo->prepare('INSERT IGNORE INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,department,dob,gender,religion,blood_group,joining_date,contract_type,salary_type,base_salary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$uid,$empId,$fname,$lname,$desig,$dept,date('Y-m-d',strtotime('-'.rnd_int(25,55).' years')), $isFemale?'female':'male', rnd_item(['Islam','Islam','Islam','Hinduism']),rnd_item($BLOOD_GROUPS),$joining,$contract,$salary_type,$salary]);
        // Assign role
        $roleSlug = $staffRoles[$uname] ?? 'teacher';
        $roleId   = $allRoles[$roleSlug] ?? $teacherRoleId;
        if ($roleId) $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid,$roleId]);
        $staffIds[$uname] = $uid;
    }
    $allStaffIds = array_values($staffIds);
    $messages[] = '✅ '.count($staffDef).' staff members created.';

    // ── 9. Students ──────────────────────────────────────────────────────────
    // Create 20-25 students per section for 2025 session, promote back for 2023/2024
    $studentRoleId = $allRoles['student'] ?? null;
    $stuInsUser  = $pdo->prepare('INSERT IGNORE INTO users (username,password_hash,full_name,status) VALUES (?,?,?,?)');
    $stuInsProf  = $pdo->prepare('INSERT IGNORE INTO student_profiles (user_id,student_id_no,first_name,last_name,dob,gender,religion,blood_group,father_name,mother_name,guardian_phone,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $stuInsEnr   = $pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id,session_id,class_id,section_id,group_id,roll_number,status) VALUES (?,?,?,?,?,?,?)');
    $stuInsRole  = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)');

    $studentIds = []; // class_name => section_name => [user_id, ...]
    $counter = 1;

    // For each section in current 2025 session, generate students
    // Then enroll them in prior sessions in the class 2 levels below
    $classPromotionMap = [
        'Class 3'=>'Class 1','Class 4'=>'Class 2','Class 5'=>'Class 3',
        'Class 6'=>'Class 4','Class 7'=>'Class 5','Class 8'=>'Class 6',
        'Class 9 (SSC-I)'=>'Class 7','Class 10 (SSC-II)'=>'Class 8',
        'HSC 1st Year'=>'Class 9 (SSC-I)','HSC 2nd Year'=>'Class 10 (SSC-II)',
    ];
    $classPrior2Map = [
        'Class 3'=>'Playgroup','Class 4'=>'Class 1','Class 5'=>'Class 2',
        'Class 6'=>'Class 3','Class 7'=>'Class 4','Class 8'=>'Class 5',
        'Class 9 (SSC-I)'=>'Class 6','Class 10 (SSC-II)'=>'Class 7',
        'HSC 1st Year'=>'Class 8','HSC 2nd Year'=>'Class 9 (SSC-I)',
    ];

    foreach ($sectionDef as $className => $sections) {
        $cid2025 = $classMap[$className] ?? null;
        if (!$cid2025) continue;
        $numStudents = in_array($className,['Playgroup','KG (Nursery)']) ? 12 : (str_contains($className,'HSC') ? 15 : 20);

        foreach ($sections as [$sname,,]) {
            $secId2025 = $sectionMap[$className][$sname] ?? null;
            if (!$secId2025) continue;

            // Group ID for SSC/HSC classes
            $grpId2025 = null;
            if (in_array($sname,['Science','Commerce','Arts'])) {
                $grpId2025 = $groupMap[$sname] ?? null;
            }

            $sectionStudents = [];
            for ($n = 1; $n <= $numStudents; $n++) {
                $g = null; [$first,$last] = gen_name($MALE_FIRST,$FEMALE_FIRST,$LAST_NAMES,$g);
                $uname  = strtolower($first).($counter).substr($last,0,3);
                $year   = date('Y');
                $stuId  = 'DMHSC-'.$year.'-'.str_pad($counter,5,'0',STR_PAD_LEFT);
                $dob    = date('Y-m-d',strtotime('-'.rnd_int(5,20).' years -'.rnd_int(0,365).' days'));
                $rel    = rnd_item($RELIGIONS);
                $phone  = bd_phone();
                $fatherFirst = $MALE_FIRST[array_rand($MALE_FIRST)];
                $fatherName  = $fatherFirst.' '.$last;
                $motherFirst = $FEMALE_FIRST[array_rand($FEMALE_FIRST)];
                $motherName  = $motherFirst.' '.rnd_item($LAST_NAMES);
                $admDate= date('Y-m-d',strtotime('2023-01-01 +'.rnd_int(0,365).' days'));

                $stuInsUser->execute([$uname,password_hash('Student@123',PASSWORD_BCRYPT),"$first $last",'active']);
                $uid = (int)$pdo->lastInsertId();
                if (!$uid) {
                    $ck2=$pdo->prepare('SELECT id FROM users WHERE username=?'); $ck2->execute([$uname]); $uid=(int)$ck2->fetchColumn();
                }
                if (!$uid) { $counter++; continue; }

                $stuInsProf->execute([$uid,$stuId,$first,$last,$dob,$g,$rel,rnd_item($BLOOD_GROUPS),$fatherName,$motherName,$phone,$admDate]);
                if ($studentRoleId) $stuInsRole->execute([$uid,$studentRoleId]);

                // 2025 enrollment
                $stuInsEnr->execute([$uid,$sess2025,$cid2025,$secId2025,$grpId2025,$n,'active']);

                // 2024 enrollment — previous class
                $cn2024 = $classPromotionMap[$className] ?? null;
                if ($cn2024 && isset($classMap[$cn2024])) {
                    $cid2024 = $classMap[$cn2024];
                    $secName2024 = $sname;
                    if (!isset($sectionMap[$cn2024][$sname])) $secName2024 = array_key_first($sectionMap[$cn2024]??[]);
                    $secId2024 = $sectionMap[$cn2024][$secName2024] ?? null;
                    $grpId2024 = in_array($secName2024,['Science','Commerce','Arts']) ? ($groupMap[$secName2024]??null) : null;
                    if ($secId2024) $stuInsEnr->execute([$uid,$sess2024,$cid2024,$secId2024,$grpId2024,$n,'active']);
                }

                // 2023 enrollment — 2 classes below
                $cn2023 = $classPrior2Map[$className] ?? null;
                if ($cn2023 && isset($classMap[$cn2023])) {
                    $cid2023 = $classMap[$cn2023];
                    $secName2023 = $sname;
                    if (!isset($sectionMap[$cn2023][$sname])) $secName2023 = array_key_first($sectionMap[$cn2023]??[]);
                    $secId2023 = $sectionMap[$cn2023][$secName2023] ?? null;
                    $grpId2023 = in_array($secName2023,['Science','Commerce','Arts']) ? ($groupMap[$secName2023]??null) : null;
                    if ($secId2023) $stuInsEnr->execute([$uid,$sess2023,$cid2023,$secId2023,$grpId2023,$n,'active']);
                }

                $sectionStudents[] = $uid;
                $counter++;
            }
            $studentIds[$className][$sname] = $sectionStudents;
        }
    }
    $totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM student_profiles')->fetchColumn();
    $messages[] = "✅ $totalStudents students created with 3-year enrollment history.";

    // ── 10. Exams ────────────────────────────────────────────────────────────
    $examDef = [
        [$sess2023,'Half-Yearly Exam 2023','terminal','2023-06-01','2023-06-20','results_published'],
        [$sess2023,'Annual Exam 2023',     'annual',  '2023-11-01','2023-11-25','results_published'],
        [$sess2024,'Half-Yearly Exam 2024','terminal','2024-06-01','2024-06-20','results_published'],
        [$sess2024,'Annual Exam 2024',     'annual',  '2024-11-01','2024-11-25','results_published'],
        [$sess2025,'Half-Yearly Exam 2025','terminal','2025-06-01','2025-06-20','results_published'],
        [$sess2025,'Monthly Test - Sep 2025','monthly','2025-09-10','2025-09-15','scheduled'],
    ];
    $examIds = [];
    $eStmt = $pdo->prepare('INSERT IGNORE INTO exams (session_id,exam_name,exam_type,start_date,end_date,status) VALUES (?,?,?,?,?,?)');
    foreach ($examDef as $ed) {
        $eStmt->execute($ed);
        $eid = (int)$pdo->lastInsertId();
        if (!$eid) { $ck=$pdo->prepare('SELECT id FROM exams WHERE exam_name=?'); $ck->execute([$ed[1]]); $eid=(int)$ck->fetchColumn(); }
        $examIds[] = $eid;
        // Map all classes to exam
        foreach ($classMap as $cname => $cid) {
            $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)')->execute([$eid,$cid]);
        }
    }

    // Exam subject config for each exam × class combination
    $escStmt = $pdo->prepare('INSERT IGNORE INTO exam_subject_config (exam_id,class_id,subject_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($examIds as $eid) {
        $exam = $pdo->prepare('SELECT session_id FROM exams WHERE id=?'); $exam->execute([$eid]); $sessId=(int)$exam->fetchColumn();
        $csRows=$pdo->prepare('SELECT class_id,subject_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical FROM class_subjects WHERE session_id=?');
        $csRows->execute([$sessId]);
        foreach ($csRows->fetchAll() as $cs) {
            $escStmt->execute([$eid,$cs['class_id'],$cs['subject_id'],$cs['full_marks_written'],$cs['full_marks_mcq'],$cs['full_marks_practical'],$cs['pass_marks_written'],$cs['pass_marks_mcq'],$cs['pass_marks_practical']]);
        }
    }
    $messages[] = '✅ '.count($examIds).' exams created with subject configurations.';

    // ── 11. Marks Entry (for published exams) ────────────────────────────────
    $meStmt = $pdo->prepare('INSERT IGNORE INTO marks_entry (exam_id,student_id,class_id,section_id,subject_id,marks_written,marks_mcq,marks_practical,is_absent,entered_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $adminId = 1;

    foreach ($examIds as $idx => $eid) {
        $examRow = $pdo->prepare('SELECT status,session_id FROM exams WHERE id=?'); $examRow->execute([$eid]); $examRow=$examRow->fetch();
        if (!in_array($examRow['status'],['results_published'])) continue;

        $escRows = $pdo->prepare('SELECT esc.class_id,esc.subject_id,esc.full_marks_written,esc.full_marks_mcq,esc.full_marks_practical,esc.pass_marks_written FROM exam_subject_config esc WHERE esc.exam_id=?');
        $escRows->execute([$eid]);
        $escByClass = [];
        foreach ($escRows->fetchAll() as $esc) $escByClass[$esc['class_id']][] = $esc;

        foreach ($studentIds as $className => $sectionData) {
            $cid = $classMap[$className] ?? null;
            if (!$cid || empty($escByClass[$cid])) continue;
            foreach ($sectionData as $sname => $stuArr) {
                $secId = $sectionMap[$className][$sname] ?? null;
                if (!$secId) continue;
                foreach ($stuArr as $uid) {
                    foreach ($escByClass[$cid] as $esc) {
                        $absent = rand(1,100) <= 3; // 3% absent
                        if ($absent) { $meStmt->execute([$eid,$uid,$cid,$secId,$esc['subject_id'],null,null,null,1,$adminId]); continue; }
                        $passM = $esc['pass_marks_written'] ?: 33;
                        $totalFull = $esc['full_marks_written']+$esc['full_marks_mcq']+$esc['full_marks_practical'];
                        // Bell curve: avg 65%, std dev ~15%
                        $rawPct = max(0, min(100, (int)(65 + (rand(-30,30) + rand(-15,15) + rand(-10,10))/3)));
                        $total = (int)($totalFull * $rawPct / 100);
                        $mw = $esc['full_marks_written'] > 0 ? min($esc['full_marks_written'], (int)($total * $esc['full_marks_written'] / $totalFull)) : null;
                        $mm = $esc['full_marks_mcq'] > 0 ? min($esc['full_marks_mcq'], $total - ($mw??0)) : null;
                        $mp = $esc['full_marks_practical'] > 0 ? min($esc['full_marks_practical'], (int)($esc['full_marks_practical'] * ($rawPct/100 + 0.05))) : null;
                        $meStmt->execute([$eid,$uid,$cid,$secId,$esc['subject_id'],$mw,$mm,$mp,0,$adminId]);
                    }
                }
            }
        }
    }
    $marksCount = (int)$pdo->query('SELECT COUNT(*) FROM marks_entry')->fetchColumn();
    $messages[] = "✅ $marksCount marks entries created.";

    // ── 12. Fee Structures + Ledgers + Payments ──────────────────────────────
    $feeCats = $pdo->query("SELECT id,category_name,category_type FROM fee_categories WHERE status=1")->fetchAll();
    $fcMap = []; foreach($feeCats as $fc) $fcMap[$fc['category_type']] = $fc['id'];

    // Monthly tuition per class
    $tuitionFees = ['Playgroup'=>800,'KG (Nursery)'=>900,'Class 1'=>1000,'Class 2'=>1000,
                    'Class 3'=>1100,'Class 4'=>1100,'Class 5'=>1200,'Class 6'=>1400,'Class 7'=>1400,
                    'Class 8'=>1500,'Class 9 (SSC-I)'=>1800,'Class 10 (SSC-II)'=>1800,
                    'HSC 1st Year'=>2200,'HSC 2nd Year'=>2200];
    $fsStmt = $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)');
    $tutCatId = $fcMap['tuition'] ?? 1;
    $examCatId= $fcMap['exam'] ?? null;
    $admCatId = $fcMap['admission'] ?? null;

    foreach ([$sess2023,$sess2024,$sess2025] as $sessId) {
        foreach ($tuitionFees as $cn => $amt) {
            $cid = $classMap[$cn] ?? null; if (!$cid) continue;
            $fsStmt->execute([$sessId,$cid,$tutCatId,$amt,10,'monthly']);
            if ($examCatId) $fsStmt->execute([$sessId,$cid,$examCatId,(int)($amt*0.5),15,'quarterly']);
        }
    }

    // Generate ledgers for 2023-2025 (12 months each) and payments
    $flStmt  = $pdo->prepare('INSERT IGNORE INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,?)');
    $fpStmt  = $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by) VALUES (?,?,?,?,?,?,?)');
    $rcpN = (int)$pdo->query('SELECT COUNT(*) FROM fee_payments')->fetchColumn();
    $prefix = setting('receipt_prefix','DMHSC');

    foreach ([$sess2023=>2023,$sess2024=>2024,$sess2025=>2025] as $sessId=>$yr) {
        $fsRows = $pdo->prepare('SELECT fs.class_id,fs.fee_category_id,fs.amount,fs.frequency FROM fee_structures fs WHERE fs.session_id=?');
        $fsRows->execute([$sessId]);
        $fsAll = $fsRows->fetchAll();

        $enrRows = $pdo->prepare('SELECT se.student_id,se.class_id,se.section_id FROM student_enrollments se WHERE se.session_id=? AND se.status="active"');
        $enrRows->execute([$sessId]);
        $enrollments = $enrRows->fetchAll();

        $maxMonth = ($yr < 2025) ? 12 : 9; // 2025 up to September

        foreach ($enrollments as $en) {
            foreach ($fsAll as $fs) {
                if ($fs['class_id'] != $en['class_id']) continue;
                $months = $fs['frequency']==='monthly' ? range(1,$maxMonth) : [1,4,7,10];
                foreach ($months as $m) {
                    if ($m > $maxMonth) continue;
                    $dueDate = sprintf('%04d-%02d-10',$yr,$m);
                    $flStmt->execute([$en['student_id'],$sessId,$fs['fee_category_id'],$fs['amount'],$dueDate,$m,$yr,'unpaid']);
                    $ledgerId = (int)$pdo->lastInsertId();
                    if (!$ledgerId) continue;

                    // Payment probability: 95% for past years, 85% for 2025
                    $payProb = ($yr < 2025) ? 95 : 85;
                    if (rand(1,100) <= $payProb) {
                        $payDate  = date('Y-m-d',strtotime("$dueDate +".rand(0,20)." days"));
                        $method   = rnd_item(['cash','cash','cash','mobile_banking','bank']);
                        $rcpN++;
                        $rcp = $prefix.'-'.$yr.'-'.str_pad($rcpN,6,'0',STR_PAD_LEFT);
                        $fpStmt->execute([$ledgerId,$en['student_id'],$fs['amount'],$payDate,$method,$rcp,$adminId]);
                        $pdo->prepare("UPDATE fee_ledgers SET amount_paid=?,status='paid' WHERE id=?")->execute([$fs['amount'],$ledgerId]);
                    }
                }
            }
        }
    }
    $feeCount = (int)$pdo->query('SELECT COUNT(*) FROM fee_payments')->fetchColumn();
    $messages[] = "✅ $feeCount fee payments seeded across 3 years.";

    // ── 13. Staff Attendance (last 3 months) ─────────────────────────────────
    $saStmt = $pdo->prepare('INSERT IGNORE INTO staff_attendance (staff_id,attendance_date,status,marked_by) VALUES (?,?,?,?)');
    $workDays = ['Saturday','Sunday','Monday','Tuesday','Wednesday'];
    for ($d = 90; $d >= 1; $d--) {
        $date   = date('Y-m-d', strtotime("-$d days"));
        $dow    = date('l',$_=strtotime($date));
        if (!in_array($dow,$workDays)) continue;
        foreach ($allStaffIds as $sid) {
            $st = rand(1,100) <= 90 ? 'present' : (rand(1,100)<=50?'absent':'late');
            $saStmt->execute([$sid,$date,$st,$adminId]);
        }
    }
    $messages[] = '✅ Staff attendance for last 3 months seeded.';

    // ── 14. Student Attendance (last 2 months) ────────────────────────────────
    $attStmt = $pdo->prepare('INSERT IGNORE INTO student_attendance (student_id,session_id,class_id,section_id,attendance_date,status,marked_by) VALUES (?,?,?,?,?,?,?)');
    for ($d = 60; $d >= 1; $d--) {
        $date = date('Y-m-d', strtotime("-$d days"));
        $dow  = date('l', strtotime($date));
        if (!in_array($dow,$workDays)) continue;
        foreach ($studentIds as $className => $sectionData) {
            $cid = $classMap[$className] ?? null;
            if (!$cid) continue;
            foreach ($sectionData as $sname => $stuArr) {
                $secId = $sectionMap[$className][$sname] ?? null;
                if (!$secId) continue;
                foreach ($stuArr as $uid) {
                    $st = rand(1,100) <= 87 ? 'present' : (rand(1,100)<=60?'absent':'late');
                    $attStmt->execute([$uid,$sess2025,$cid,$secId,$date,$st,$adminId]);
                }
            }
        }
    }
    $messages[] = '✅ Student attendance for last 2 months seeded.';

    // ── 15. Leave Applications ────────────────────────────────────────────────
    $laStmt = $pdo->prepare('INSERT INTO leave_applications (staff_id,leave_type_id,from_date,to_date,total_days,reason,status,approved_by,applied_at) VALUES (?,?,?,?,?,?,?,?,?)');
    $ltId = (int)$pdo->query('SELECT id FROM leave_types LIMIT 1')->fetchColumn();
    $reasons = ['Personal work','Sick - Fever','Family function','Medical treatment','Child illness','Emergency travel'];
    $statusOpts = ['approved','approved','approved','pending','rejected'];
    foreach (array_slice($allStaffIds,2,20) as $sid) {
        for ($i=0;$i<rand(1,3);$i++) {
            $daysAgo = rand(10,300);
            $fd = date('Y-m-d',strtotime("-$daysAgo days"));
            $days = rand(1,5);
            $td = date('Y-m-d',strtotime("$fd +$days days"));
            $st = rnd_item($statusOpts);
            $apvBy = $st!=='pending' ? $allStaffIds['principal'] : null;
            $laStmt->execute([$sid,$ltId,$fd,$td,$days,rnd_item($reasons),$st,$apvBy,date('Y-m-d H:i:s',strtotime("-$daysAgo days"))]);
        }
    }
    $messages[] = '✅ Leave applications seeded.';

    // ── 16. Payroll (last 6 months) ──────────────────────────────────────────
    $prStmt = $pdo->prepare('INSERT IGNORE INTO payroll_runs (session_id,month,year,status,created_by) VALUES (?,?,?,"finalized",?)');
    $plStmt = $pdo->prepare('INSERT IGNORE INTO payroll_lines (payroll_run_id,staff_id,base_salary,exam_duty_allowance,net_salary,payment_status) VALUES (?,?,?,?,?,"paid")');
    for ($i=5; $i>=1; $i--) {
        $mon = (int)date('n',strtotime("-$i months"));
        $yr  = (int)date('Y',strtotime("-$i months"));
        $prStmt->execute([$sess2025,$mon,$yr,$adminId]);
        $runId = (int)$pdo->lastInsertId();
        if (!$runId) continue;
        foreach ($allStaffIds as $sid) {
            $sp = $pdo->prepare('SELECT base_salary FROM staff_profiles WHERE user_id=?'); $sp->execute([$sid]); $base=(float)$sp->fetchColumn();
            if (!$base) continue;
            $duty = rand(0,3)*500;
            $plStmt->execute([$runId,$sid,$base,$duty,$base+$duty]);
        }
    }
    $messages[] = '✅ 5 months of payroll runs seeded.';

    // ── 17. Expenses (3 years) ────────────────────────────────────────────────
    $exStmt = $pdo->prepare('INSERT INTO expenses (session_id,expense_category_id,amount,expense_date,description,vendor,status,approved_by) VALUES (?,?,?,?,?,?,"approved",?)');
    $ecId = (int)$pdo->query('SELECT id FROM expense_categories LIMIT 1')->fetchColumn();
    $expCats = $pdo->query('SELECT id,category_name FROM expense_categories')->fetchAll();
    $expDescs = ['Electricity bill','Water bill','Internet charges','Office supplies purchase','Maintenance repair','Lab chemicals','Sports equipment','Library books','Printing & stationery','Furniture repair','Security service','Cleaning supplies','Staff refreshments','Exam printing'];
    $vendors  = ['DESCO','WASA','Grameenphone','Reza Traders','Fix-It Services','Lab Supplies BD','Sports Corner','Books & More','Print House','Furniture Fix','Guard Services','Clean BD','Refreshment House','Print Pro'];

    foreach ([$sess2023=>2023,$sess2024=>2024,$sess2025=>2025] as $sessId=>$yr) {
        $maxM = ($yr<2025)?12:9;
        for ($m=1;$m<=$maxM;$m++) {
            // 3-6 expense entries per month
            for ($e=0;$e<rand(3,6);$e++) {
                $day = rand(1,28);
                $cat = rnd_item($expCats);
                $i   = rand(0,count($expDescs)-1);
                $amt = rnd_int(2000,50000);
                $exStmt->execute([$sessId,$cat['id'],$amt,sprintf('%04d-%02d-%02d',$yr,$m,$day),$expDescs[$i],$vendors[$i]??'',$adminId]);
            }
        }
    }
    $messages[] = '✅ Expense records for 3 years seeded.';

    // ── 18. Non-fee Income ────────────────────────────────────────────────────
    $inStmt = $pdo->prepare('INSERT INTO incomes (session_id,income_category_id,amount,income_date,description,received_by) VALUES (?,?,?,?,?,?)');
    $icRows = $pdo->query('SELECT id,category_name FROM income_categories')->fetchAll();
    $incDescs = ['Annual alumni donation','Venue rental — community hall','Canteen monthly income','Book sale revenue','Uniform sale','Stationery sale revenue','Sports day sponsorship'];
    foreach ([$sess2023=>2023,$sess2024=>2024,$sess2025=>2025] as $sessId=>$yr) {
        $maxM = ($yr<2025)?12:9;
        for ($m=1;$m<=$maxM;$m+=2) {
            $ic = rnd_item($icRows);
            $i  = rand(0,count($incDescs)-1);
            $inStmt->execute([$sessId,$ic['id'],rnd_int(5000,80000),sprintf('%04d-%02d-15',$yr,$m),$incDescs[$i],$adminId]);
        }
    }
    $messages[] = '✅ Income records seeded.';

    // ── 19. Assets & Consumables ─────────────────────────────────────────────
    $assetCatId = (int)$pdo->query("SELECT id FROM asset_categories WHERE category_name='Electronics'")->fetchColumn();
    $furnCatId  = (int)$pdo->query("SELECT id FROM asset_categories WHERE category_name='Furniture'")->fetchColumn();
    $labCatId   = (int)$pdo->query("SELECT id FROM asset_categories WHERE category_name='Lab Equipment'")->fetchColumn();
    $asStmt = $pdo->prepare('INSERT IGNORE INTO assets (asset_category_id,asset_name,serial_number,purchase_date,purchase_price,vendor,status) VALUES (?,?,?,?,?,?,?)');
    $assetDef = [
        [$assetCatId,'HP Laptop','HP-2023-001','2023-01-15',65000,'Computer City','assigned'],
        [$assetCatId,'HP Laptop','HP-2023-002','2023-01-15',65000,'Computer City','assigned'],
        [$assetCatId,'Dell Desktop PC','DELL-001','2022-06-01',55000,'Tech Zone','available'],
        [$assetCatId,'Epson Projector','PROJ-2023-01','2023-03-10',45000,'AV World','assigned'],
        [$assetCatId,'Canon Printer','PRNT-001','2022-01-01',18000,'Office World','available'],
        [$assetCatId,'Photocopier','COPY-001','2021-05-01',120000,'Ricoh BD','available'],
        [$furnCatId,'Principal Chair Set','FURN-PC-01','2020-01-01',25000,'Furniture House','available'],
        [$furnCatId,'Student Bench-Desk (x10)','FURN-STU-01','2021-06-01',80000,'Wood Works','available'],
        [$labCatId,'Microscope Set (x5)','MICRO-001','2022-08-01',75000,'Lab Supplies BD','available'],
        [$labCatId,'Chemistry Balance (x3)','BAL-001','2023-02-01',45000,'Science Tools','available'],
        [$assetCatId,'Smart TV 55"','TV-001','2023-09-01',60000,'Sony BD','available'],
        [$assetCatId,'CCTV System (8 cam)','CCTV-001','2022-12-01',85000,'Security Pro','available'],
    ];
    foreach ($assetDef as $a) $asStmt->execute($a);
    // Assign 2 laptops to principal and VP
    $lapIds = $pdo->query("SELECT id FROM assets WHERE serial_number IN ('HP-2023-001','HP-2023-002')")->fetchAll(PDO::FETCH_COLUMN);
    $principalId = $staffIds['principal'] ?? null;
    $vpId = $staffIds['vp_shahida'] ?? null;
    if ($principalId && isset($lapIds[0])) $pdo->prepare('INSERT IGNORE INTO asset_assignments (asset_id,assigned_to,assigned_by,assigned_date,condition_out,status) VALUES (?,?,?,?,?,?)')->execute([$lapIds[0],$principalId,$adminId,'2023-01-20','good','active']);
    if ($vpId && isset($lapIds[1])) $pdo->prepare('INSERT IGNORE INTO asset_assignments (asset_id,assigned_to,assigned_by,assigned_date,condition_out,status) VALUES (?,?,?,?,?,?)')->execute([$lapIds[1],$vpId,$adminId,'2023-01-20','good','active']);

    // Consumables
    $bookCatId = (int)$pdo->query("SELECT id FROM consumable_categories WHERE category_name='Books'")->fetchColumn();
    $khataCatId= (int)$pdo->query("SELECT id FROM consumable_categories WHERE category_name LIKE '%Khata%'")->fetchColumn();
    $statCatId = (int)$pdo->query("SELECT id FROM consumable_categories WHERE category_name='Stationery'")->fetchColumn();
    $conDef = [
        [$bookCatId,'Bangla Textbook Cl-6','BK-BAN6','piece',150,50,120],
        [$bookCatId,'Math Textbook Cl-9','BK-MTH9','piece',80,30,180],
        [$bookCatId,'Physics Textbook Cl-9','BK-PHY9','piece',60,25,220],
        [$khataCatId??$bookCatId,'School Notebook (Khata)','KH-BLUE','piece',250,100,55],
        [$khataCatId??$bookCatId,'Exercise Copy','KH-EX','piece',180,80,45],
        [$statCatId??$bookCatId,'Ball Pen (Box)','STAT-PEN','box',30,15,95],
        [$statCatId??$bookCatId,'Whiteboard Marker','STAT-MRK','pack',20,10,120],
        [$statCatId??$bookCatId,'A4 Paper (Ream)','STAT-A4','ream',50,20,350],
        [$statCatId??$bookCatId,'Chalk Box','STAT-CHK','box',100,40,25],
    ];
    $conStmt = $pdo->prepare('INSERT IGNORE INTO consumables (consumable_category_id,item_name,item_code,unit,current_stock,min_threshold,unit_cost) VALUES (?,?,?,?,?,?,?)');
    $conTxStmt= $pdo->prepare('INSERT INTO consumable_transactions (consumable_id,transaction_type,quantity,unit_price,total_price,transaction_date,created_by) VALUES (?,?,?,?,?,?,?)');
    foreach ($conDef as $cd) {
        [$catId,$name,$code,$unit,$stock,$min,$cost] = $cd;
        $conStmt->execute([$catId,$name,$code,$unit,$stock,$min,$cost]);
        $cid = (int)$pdo->lastInsertId();
        if (!$cid) { $ck=$pdo->prepare('SELECT id FROM consumables WHERE item_code=?'); $ck->execute([$code]); $cid=(int)$ck->fetchColumn(); }
        if ($cid) {
            $conTxStmt->execute([$cid,'purchase',$stock+50,$cost,($stock+50)*$cost,date('Y-m-d',strtotime('-6 months')),$adminId]);
            $conTxStmt->execute([$cid,'issue',50,$cost,$cost*50,date('Y-m-d',strtotime('-1 month')),$adminId]);
        }
    }
    $messages[] = '✅ '.count($assetDef).' assets and '.count($conDef).' consumables seeded.';

    // ── 20. Clubs & Events ────────────────────────────────────────────────────
    $tPhy = $staffIds['t_physics']??$adminId; $tEng=$staffIds['t_english']??$adminId;
    $tPe  = $staffIds['t_pe']??$adminId; $tBan=$staffIds['t_bangla']??$adminId;
    $clubDef = [
        ['Science Club','science',$tPhy,'Explore science through experiments and innovation.','2020-01-15'],
        ['Debate Club', 'debate', $tEng,'Develop public speaking and critical thinking.',  '2019-06-01'],
        ['Sports Club', 'sports', $tPe, 'Annual sports and physical activities.',           '2018-03-01'],
        ['Cultural Club','cultural',$tBan,'Bangla cultural programs and literary events.',  '2021-01-01'],
    ];
    $clbStmt = $pdo->prepare('INSERT IGNORE INTO clubs (club_name,club_type,moderator_id,description,founded_date) VALUES (?,?,?,?,?)');
    $clubIds = [];
    foreach ($clubDef as $cd) {
        $clbStmt->execute($cd); $cid=(int)$pdo->lastInsertId();
        if (!$cid) { $ck=$pdo->prepare('SELECT id FROM clubs WHERE club_name=?'); $ck->execute([$cd[0]]); $cid=(int)$ck->fetchColumn(); }
        $clubIds[] = $cid;
    }
    // Add some students as members
    $cmStmt = $pdo->prepare('INSERT IGNORE INTO club_members (club_id,student_id,role_in_club,joined_date,status) VALUES (?,?,?,?,?)');
    $allStuIds = [];
    foreach ($studentIds as $sdata) foreach ($sdata as $sArr) $allStuIds = array_merge($allStuIds,$sArr);
    $allStuIds = array_unique($allStuIds);
    shuffle($allStuIds);
    foreach ($clubIds as $i => $cid) {
        $members = array_slice($allStuIds, $i*20, 20);
        foreach ($members as $j => $sid) $cmStmt->execute([$cid,$sid,$j===0?'President':'member',date('Y-m-d',strtotime('-'.rand(30,365).' days')),'active']);
    }

    // Events
    $evStmt = $pdo->prepare('INSERT IGNORE INTO events (session_id,event_name,event_type,start_date,end_date,budget_allocated,description,status) VALUES (?,?,?,?,?,?,?,?)');
    $evDef = [
        [$sess2023,'Annual Sports Day 2023','annual_sports','2023-02-15','2023-02-15',50000,'Annual inter-class sports competition','completed'],
        [$sess2023,'Bangla Cultural Fest 2023','cultural','2023-02-21','2023-02-21',30000,'Language Martyrs Day celebration','completed'],
        [$sess2023,'Science Fair 2023','seminar','2023-10-10','2023-10-11',40000,'Student science project exhibition','completed'],
        [$sess2024,'Annual Sports Day 2024','annual_sports','2024-02-14','2024-02-14',55000,'Inter-section sports competition','completed'],
        [$sess2024,'Debate Competition 2024','debate','2024-03-20','2024-03-20',20000,'Inter-class debate competition','completed'],
        [$sess2024,'Annual Prize Giving 2024','cultural','2024-12-10','2024-12-10',60000,'Annual prize giving ceremony','completed'],
        [$sess2025,'Annual Sports Day 2025','annual_sports','2025-02-15','2025-02-15',60000,'Annual inter-class sports event','completed'],
        [$sess2025,'Science & Technology Fair','seminar','2025-11-01','2025-11-02',45000,'Upcoming science fair - planning','planning'],
    ];
    foreach ($evDef as $ev) $evStmt->execute($ev);
    // Add expenses to completed events
    $evIds = $pdo->query("SELECT id FROM events WHERE status='completed' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $eeStmt = $pdo->prepare('INSERT INTO event_expenses (event_id,description,amount,expense_date,approved_by) VALUES (?,?,?,?,?)');
    foreach ($evIds as $evId) {
        $eeStmt->execute([$evId,'Trophy & prizes',rnd_int(5000,15000),date('Y-m-d',strtotime('-'.rand(30,400).' days')),$adminId]);
        $eeStmt->execute([$evId,'Refreshments & snacks',rnd_int(3000,8000),date('Y-m-d',strtotime('-'.rand(30,400).' days')),$adminId]);
        $eeStmt->execute([$evId,'Decoration & setup',rnd_int(2000,10000),date('Y-m-d',strtotime('-'.rand(30,400).' days')),$adminId]);
    }
    $messages[] = '✅ 4 clubs, '.count($evDef).' events, and event expenses seeded.';

    // ── 21. Holidays (3 years) ────────────────────────────────────────────────
    $hStmt = $pdo->prepare('INSERT IGNORE INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)');
    $bdHolidays = [
        ['02-21','Language Martyrs Day (Shaheed Dibosh)','govt'],
        ['03-17','Birthday of Bangabandhu Sheikh Mujibur Rahman','govt'],
        ['03-26','Independence Day (Shadhinota Dibosh)','govt'],
        ['04-14','Bengali New Year (Pohela Boishakh)','govt'],
        ['05-01','International Labour Day','govt'],
        ['08-15','National Mourning Day','govt'],
        ['12-16','Victory Day (Bijoy Dibosh)','govt'],
        ['12-25','Christmas Day','govt'],
    ];
    $instHolidays = [
        ['01-01','New Year Holiday','institutional'],
        ['06-15','Summer Vacation Begins','institutional'],
        ['07-15','Summer Vacation Ends','institutional'],
        ['10-20','Half-Yearly Exam Prep Day','institutional'],
        ['11-30','Annual Exam Prep Day','institutional'],
    ];
    foreach ([$sess2023=>2023,$sess2024=>2024,$sess2025=>2025] as $sessId=>$yr) {
        foreach (array_merge($bdHolidays,$instHolidays) as [$md,$name,$type]) {
            $hStmt->execute([$sessId,"$yr-$md",$name,$type]);
        }
    }
    $messages[] = '✅ Bangladesh national + institutional holidays for 3 years seeded.';

    // ── 22. Alumni ────────────────────────────────────────────────────────────
    $alStmt = $pdo->prepare('INSERT INTO alumni (full_name,last_class_id,pass_year,current_institution,current_profession,phone,email) VALUES (?,?,?,?,?)');
    // Manually insert more meaningful alumni
    $alumniDef = [
        ['Md. Ahsan Habib',    'Class 10 (SSC-II)', 2022,'University of Dhaka','Medical Student (MBBS)','01711200001','ahsan@alumni.dmhsc.edu.bd'],
        ['Shaila Parveen',     'Class 10 (SSC-II)', 2022,'BUET','Engineering Student','01912200002','shaila@alumni.dmhsc.edu.bd'],
        ['Karim Uddin Ahmed',  'HSC 2nd Year',      2023,'Dhaka College','BBA Student','01611200003',''],
        ['Nusrat Jahan',       'HSC 2nd Year',      2023,'Eden College','Admission Pending','01811200004',''],
        ['Tariqul Islam',      'Class 10 (SSC-II)', 2021,'Dhaka College','HSC Student','01511200005',''],
        ['Farzana Akter',      'HSC 2nd Year',      2022,'Dhaka University','LLB Student','01311200006','farzana@alumni.dmhsc.edu.bd'],
        ['Rashed Mia',         'HSC 2nd Year',      2021,'RUET','Engineering','01211200007',''],
        ['Rina Begum',         'Class 10 (SSC-II)', 2023,'Vocational Institute','Diploma Student','01911200008',''],
    ];
    $alStmt = $pdo->prepare('INSERT IGNORE INTO alumni (full_name,last_class_id,pass_year,current_institution,current_profession,phone,email) VALUES (?,?,?,?,?,?,?)');
    foreach ($alumniDef as [$name,$cn,$yr,$inst,$prof,$ph,$em]) {
        $cid = $classMap[$cn] ?? null;
        $alStmt->execute([$name,$cid,$yr,$inst,$prof,$ph,$em]);
    }
    $messages[] = '✅ Alumni records seeded.';

    // ── 23. TC Records ───────────────────────────────────────────────────────
    // Issue TC to 3 random students
    $tcStudents = array_slice($allStuIds, -5, 3);
    $tcStmt = $pdo->prepare('INSERT IGNORE INTO tc_records (student_id,file_number,issued_date,reason,destination_school,approved_by,status) VALUES (?,?,?,?,?,?,?)');
    foreach ($tcStudents as $i => $sid) {
        $tcStmt->execute([$sid,'TC-2024-'.str_pad($i+1,5,'0',STR_PAD_LEFT),'2024-12-15','Family relocation','New School, Chittagong',$adminId,'issued']);
        $pdo->prepare("UPDATE student_enrollments SET status='transferred' WHERE student_id=? AND status='active'")->execute([$sid]);
        $pdo->prepare("UPDATE users SET status='archived' WHERE id=?")->execute([$sid]);
    }
    $messages[] = '✅ 3 TC records created.';

    // ── 24. Activity Logs ─────────────────────────────────────────────────────
    $alLogStmt = $pdo->prepare('INSERT INTO activity_logs (user_id,action,module,record_id,ip_address,created_at) VALUES (?,?,?,?,?,?)');
    $logSamples = [
        ['admit_student','students'],['collect_fee','finance'],['create_class','academic'],
        ['generate_payroll','hr'],['login','auth'],['update_settings','setup'],
        ['mark_attendance','students'],['approve_leave','hr'],['create_exam','exams'],
    ];
    for ($i=0;$i<50;$i++) {
        [$act,$mod] = rnd_item($logSamples);
        $uid = rnd_item($allStaffIds);
        $daysAgo = rand(0,90);
        $alLogStmt->execute([$uid,$act,$mod,rand(1,100),'192.168.1.'.rand(1,50),date('Y-m-d H:i:s',strtotime("-$daysAgo days -".rand(0,86400)." seconds"))]);
    }
    $messages[] = '✅ Activity log entries seeded.';

    // ── 25. Fee Waivers ────────────────────────────────────────────────────────
    $wvLedgers = $pdo->query("SELECT id,student_id FROM fee_ledgers WHERE status='unpaid' ORDER BY RAND() LIMIT 8")->fetchAll();
    $wvStmt = $pdo->prepare('INSERT IGNORE INTO waivers (student_id,ledger_id,requested_amount,waiver_reason,requested_by,status) VALUES (?,?,?,?,?,?)');
    $reasons2 = ['Financial hardship — single parent','Scholarship recipient','Outstanding academic performance','Covid-19 impact on family income'];
    foreach ($wvLedgers as $i => $wl) {
        $amt = rand(200,500);
        $st  = $i<3?'approved':($i<6?'pending':'rejected');
        $wvStmt->execute([$wl['student_id'],$wl['id'],$amt,rnd_item($reasons2),$adminId,$st]);
        if ($st==='approved') $pdo->prepare("UPDATE fee_ledgers SET waiver_status='approved',waiver_amount=? WHERE id=?")->execute([$amt,$wl['id']]);
    }
    $messages[] = '✅ Fee waiver requests seeded.';

    // Finalise: update current_session_id
    $pdo->prepare('UPDATE system_settings SET meta_value=? WHERE meta_key="current_session_id"')->execute([$sess2025]);
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    // Summary
    $msgs[] = '';
    $msgs[] = '═══════════════════════════════════════';
    $msgs[] = '  SEED COMPLETE — Database Summary';
    $msgs[] = '═══════════════════════════════════════';
    foreach ([
        'Sessions'         => 'SELECT COUNT(*) FROM academic_sessions',
        'Classes'          => 'SELECT COUNT(*) FROM classes',
        'Sections'         => 'SELECT COUNT(*) FROM sections',
        'Subjects'         => 'SELECT COUNT(*) FROM subjects',
        'Staff Members'    => 'SELECT COUNT(*) FROM staff_profiles',
        'Students'         => 'SELECT COUNT(*) FROM student_profiles',
        'Enrollments'      => 'SELECT COUNT(*) FROM student_enrollments',
        'Exams'            => 'SELECT COUNT(*) FROM exams',
        'Marks Entries'    => 'SELECT COUNT(*) FROM marks_entry',
        'Fee Payments'     => 'SELECT COUNT(*) FROM fee_payments',
        'Expense Records'  => 'SELECT COUNT(*) FROM expenses',
        'Payroll Lines'    => 'SELECT COUNT(*) FROM payroll_lines',
        'Activity Logs'    => 'SELECT COUNT(*) FROM activity_logs',
        'Clubs'            => 'SELECT COUNT(*) FROM clubs',
        'Alumni'           => 'SELECT COUNT(*) FROM alumni',
    ] as $label => $sql) {
        $messages[] = "  $label: ".(int)$pdo->query($sql)->fetchColumn();
    }
}

// ── WIPE ──────────────────────────────────────────────────────────────────────
if ($action === 'wipe') {
    $pdo = db();
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $tables = ['activity_logs','alumni','asset_assignments','assets','batch_enrollments',
               'class_groups','class_subjects','club_members','clubs','consumable_transactions',
               'consumables','document_issues','event_expenses','events','exam_class_map',
               'exam_invigilators','exam_seats','exam_subject_config','exams','expenses',
               'fee_ledgers','fee_payments','fee_structures','holiday_calendar','incidents',
               'incomes','leave_applications','marks_entry','payroll_lines','payroll_runs',
               'performance_logs','question_vault','routine_slots','sms_logs','special_batches',
               'staff_attendance','staff_profiles','student_attendance','student_documents',
               'student_enrollments','student_profiles','tc_records','waivers'];
    foreach ($tables as $t) $pdo->exec("TRUNCATE TABLE $t");

    // Keep admin user only
    $pdo->exec("DELETE FROM user_roles WHERE user_id != 1");
    $pdo->exec("DELETE FROM users WHERE id != 1");

    // Reset sessions and classes
    $pdo->exec("TRUNCATE TABLE academic_sessions");
    $pdo->exec("TRUNCATE TABLE sections");
    $pdo->exec("TRUNCATE TABLE classes");
    $pdo->exec("TRUNCATE TABLE groups_stream");
    $pdo->exec("TRUNCATE TABLE rooms");
    $pdo->exec("TRUNCATE TABLE subjects");
    $pdo->exec("UPDATE system_settings SET meta_value='0' WHERE meta_key='current_session_id'");

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $messages[] = '🗑️ All demo data wiped. Admin account (admin/Admin@1234) preserved. Ready for fresh seed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EMS Demo Data Manager</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;}
.card{border:none;border-radius:16px;box-shadow:0 25px 60px rgba(0,0,0,.4);max-width:680px;width:100%;}
.header{background:linear-gradient(135deg,#1a56db,#0e9f6e);padding:2rem;border-radius:16px 16px 0 0;color:#fff;}
pre{background:#1e293b;color:#94a3b8;border-radius:10px;padding:1.25rem;font-size:.8rem;max-height:350px;overflow-y:auto;}
pre .ok{color:#4ade80;} pre .warn{color:#fbbf24;} pre .sep{color:#64748b;}
.btn-seed{background:linear-gradient(135deg,#059669,#10b981);border:none;color:#fff;font-weight:700;}
.btn-wipe{background:linear-gradient(135deg,#dc2626,#ef4444);border:none;color:#fff;font-weight:700;}
.btn-login{background:linear-gradient(135deg,#1a56db,#2563eb);border:none;color:#fff;font-weight:700;}
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-database-fill-gear" style="font-size:2.5rem;"></i>
      <div>
        <h4 class="mb-0 fw-800">EMS Demo Data Manager</h4>
        <p class="mb-0 opacity-75">Dhaka Model High School & College — 3-year dataset</p>
      </div>
    </div>
  </div>
  <div class="p-4">

    <?php if (!empty($messages)): ?>
    <pre><?php foreach ($messages as $m): ?>
<?php if(str_starts_with($m,'✅')): ?><span class="ok"><?= htmlspecialchars($m) ?></span><?php
   elseif(str_starts_with($m,'🗑️')): ?><span class="warn"><?= htmlspecialchars($m) ?></span><?php
   elseif(str_starts_with($m,'═')): ?><span class="sep"><?= htmlspecialchars($m) ?></span><?php
   else: ?><?= htmlspecialchars($m) ?><?php endif; ?>
<?php endforeach; ?></pre>
      <a href="index.php" class="btn btn-login w-100 py-2 mb-3">
        <i class="bi bi-box-arrow-in-right me-2"></i>Go to EMS Login
      </a>
    <?php else: ?>

    <!-- What will be seeded -->
    <div class="mb-4">
      <h6 class="fw-700 mb-3">What the seed creates:</h6>
      <div class="row g-2 small">
        <?php $items = [
          ['📅','3 Academic Sessions','2023 (completed) · 2024 (completed) · 2025 (current)'],
          ['🏫','14 Classes','Playgroup → KG → Class 1–10 → HSC 1st & 2nd Year'],
          ['🗂️','35 Sections','A/B/C · Science/Commerce/Arts streams'],
          ['📚','25 Subjects','Full NCTB curriculum with MCQ/Practical flags'],
          ['👨‍🏫','25 Staff','Principal, VPs, Dept Heads, Teachers, Admin, Accounts'],
          ['🎓','~300 Students','Realistic Bangladeshi names, enrolled in all 3 years'],
          ['📝','6 Exams','Half-Yearly + Annual for each session with full marks'],
          ['💰','~10,000 Fee Records','Structures → Ledgers → Payments (95% paid)'],
          ['📊','3-Year Expenses','Monthly utility, maintenance, supply records'],
          ['💼','5-Month Payroll','Finalized payroll runs for all staff'],
          ['🏆','4 Clubs','Science, Debate, Sports, Cultural with members'],
          ['🎪','8 Events','Annual Sports, Fests, Fairs with budgets'],
          ['🏛️','8 Alumni Records','HSC & SSC graduates from 2021–2023'],
          ['📦','Assets & Stock','12 assets, 9 consumable items with transactions'],
          ['🗓️','3-Year Holidays','Bangladesh national + institutional holidays'],
        ]; foreach ($items as [$icon,$title,$desc]): ?>
        <div class="col-12">
          <div class="d-flex gap-2 p-2 bg-light rounded">
            <span style="font-size:1.1rem;"><?= $icon ?></span>
            <div><span class="fw-600"><?= $title ?></span><br>
              <span class="text-muted"><?= $desc ?></span></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="alert alert-warning d-flex gap-2 py-2 small">
      <i class="bi bi-exclamation-triangle-fill mt-1"></i>
      <div><strong>Note:</strong> Seeding takes 30–120 seconds. Do not close the page. If existing demo data is present, use Wipe first to avoid duplicates.</div>
    </div>
    <?php endif; ?>

    <?php if (!$action): ?>
    <div class="d-flex gap-3">
      <form method="POST" class="flex-fill">
        <button type="submit" name="action" value="seed" class="btn btn-seed w-100 py-2"
                onclick="this.innerHTML='⏳ Seeding — please wait...';this.disabled=true;this.form.submit();">
          <i class="bi bi-database-fill-add me-2"></i>Seed 3-Year Demo Data
        </button>
      </form>
      <form method="POST" class="flex-fill">
        <button type="submit" name="action" value="wipe" class="btn btn-wipe w-100 py-2"
                onclick="return confirm('Wipe ALL data? Admin account will be preserved.')">
          <i class="bi bi-trash3-fill me-2"></i>Wipe All Data
        </button>
      </form>
    </div>
    <div class="mt-3 text-center">
      <a href="index.php" class="btn btn-outline-secondary w-100">Back to EMS Login</a>
    </div>
    <?php endif; ?>

    <hr class="my-3">
    <p class="text-center text-muted small mb-0">
      <i class="bi bi-shield-exclamation text-warning me-1"></i>
      <strong>Security:</strong> Delete <code>demo_data.php</code> before going live.
    </p>
  </div>
</div>
</body>
</html>
