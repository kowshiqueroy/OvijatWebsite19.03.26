<?php
/**
 * EMS Database Manager
 * Backup, Reset, Import SQL, and Seed 2024–2026 Academic Data
 * Protected by hardcoded PIN: 5877
 */
define('EMS_ROOT', __DIR__);
require_once EMS_ROOT . '/config/constants.php';
require_once EMS_ROOT . '/core/functions.php';

session_name('EMS_SESS');
session_start();

$pin_error = false;
$pin_success = false;

// Handle PIN Verification
if (isset($_POST['pin_submit'])) {
    $entered_pin = $_POST['pin'] ?? '';
    if ($entered_pin === '5877') {
        $_SESSION['db_manager_auth'] = true;
        $pin_success = true;
    } else {
        $pin_error = true;
    }
}

// Log out of manager
if (isset($_GET['logout'])) {
    unset($_SESSION['db_manager_auth']);
    header('Location: db_manager.php');
    exit;
}

$authenticated = isset($_SESSION['db_manager_auth']) && $_SESSION['db_manager_auth'] === true;

$messages = [];
$errors = [];
$action = $_POST['action'] ?? '';

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    $pdo = db();
    
    // ── NATIVE SQL EXPORT ───────────────────────────────────────────────────────
    if ($action === 'export') {
        try {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $sql = "-- EMS Bangladesh Database Export\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create[1] . ";\n\n";
                
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $vals = array_map(function($val) use ($pdo) {
                        return $val === null ? 'NULL' : $pdo->quote($val);
                    }, array_values($row));
                    $sql .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                }
                $sql .= "\n";
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            header('Content-Type: text/sql');
            header('Content-Disposition: attachment; filename="ems_backup_' . date('Y_m_d_His') . '.sql"');
            echo $sql;
            exit;
        } catch (Exception $e) {
            $errors[] = 'Export failed: ' . $e->getMessage();
        }
    }
    
    // ── NATIVE SQL IMPORT ───────────────────────────────────────────────────────
    if ($action === 'import') {
        if (!empty($_FILES['sql_file']['tmp_name'])) {
            try {
                $content = file_get_contents($_FILES['sql_file']['tmp_name']);
                
                // Strip comments
                $content = preg_replace('/--.*$/m', '', $content);
                $content = preg_replace('/\/\*.*?\*\//s', '', $content);
                
                $queries = preg_split('/;\s*$/m', $content);
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                $count = 0;
                foreach ($queries as $q) {
                    $q = trim($q);
                    if ($q) {
                        $pdo->exec($q);
                        $count++;
                    }
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                $messages[] = "✅ SQL imported successfully. Executed $count queries.";
            } catch (Exception $e) {
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Please choose a valid SQL backup file.';
        }
    }
    
    // ── RESET DATABASE ──────────────────────────────────────────────────────────
    if ($action === 'reset') {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $tables = ['activity_logs','alumni','asset_assignments','assets','batch_enrollments',
                       'class_groups','class_subjects','club_members','clubs','consumable_transactions',
                       'consumables','document_issues','event_expenses','events','exam_class_map',
                       'exam_invigilators','exam_seats','exam_subject_config','exams','expenses',
                       'fee_ledgers','fee_payments','fee_structures','holiday_calendar','incidents',
                       'incomes','leave_applications','marks_entry','payroll_lines','payroll_runs',
                       'performance_logs','question_vault','routine_slots','sms_logs','special_batches',
                       'staff_attendance','staff_profiles','student_attendance','student_documents',
                       'student_enrollments','student_profiles','tc_records','waivers',
                       'accounts','account_transactions','custom_payments'];
            foreach ($tables as $t) {
                $pdo->exec("TRUNCATE TABLE `$t`");
            }
            
            // Keep admin user only
            $pdo->exec("DELETE FROM user_roles WHERE user_id != 1");
            $pdo->exec("DELETE FROM users WHERE id != 1");
            
            // Truncate structures
            $pdo->exec("TRUNCATE TABLE academic_sessions");
            $pdo->exec("TRUNCATE TABLE sections");
            $pdo->exec("TRUNCATE TABLE classes");
            $pdo->exec("TRUNCATE TABLE groups_stream");
            $pdo->exec("TRUNCATE TABLE rooms");
            $pdo->exec("TRUNCATE TABLE subjects");
            
            $pdo->exec("UPDATE system_settings SET meta_value='0' WHERE meta_key='current_session_id'");
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            
            $messages[] = '🗑️ Database reset completed. Essential metadata, default settings, and admin account preserved.';
        } catch (Exception $e) {
            $errors[] = 'Reset failed: ' . $e->getMessage();
        }
    }
    
    // ── SEED 2024–2026 DEMO DATA ────────────────────────────────────────────────
    if ($action === 'seed') {
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        try {
            // Name pools (Bangladeshi)
            $MALE_FIRST   = ['Abdullah','Ahmed','Amin','Anwar','Arafat','Arif','Ashraf','Atik','Aziz','Borhan','Farhan','Faruk','Forhad','Golam','Hafiz','Harun','Hasan','Ibrahim','Imran','Jahangir','Jamal','Kabir','Kamal','Khaled','Liton','Mahbub','Masum','Minhaj','Mizanur','Nazmul','Nasir','Nabil','Nahid','Palash','Pervez','Raihan','Rahim','Rakib','Rifat','Ripon','Sagor','Salim','Shahed','Shamim','Sharif','Shohel','Sohag','Sumon','Tanvir','Tariq','Wahid','Wasim','Yasin','Zahir','Zakir'];
            $FEMALE_FIRST = ['Afsana','Aisha','Akhi','Alia','Amena','Arifa','Asma','Ayesha','Bilkis','Champa','Dipa','Dilruba','Farhana','Fariha','Fatema','Gulnaz','Hasina','Israt','Jasmine','Jesmin','Khaleda','Lima','Lubna','Marium','Mita','Mitu','Mohua','Monira','Munni','Nasrin','Nazmun','Nilufar','Nipa','Parveen','Roksana','Runa','Sabina','Sadia','Saima','Salma','Sharmin','Shilpi','Sumaiya','Sumona','Sumi','Tania','Urmi','Yasmin','Zannat'];
            $LAST_NAMES   = ['Ahmed','Akhter','Alam','Ali','Bhuiyan','Choudhury','Das','Gazi','Hossain','Huda','Islam','Jahan','Kabir','Karim','Khan','Khatun','Mahmud','Majumdar','Mia','Molla','Mondal','Mridha','Paul','Rahman','Roy','Sarkar','Siddique','Talukder','Uddin','Ullah'];

            // ── 1. School Settings ─────────────────────────────────────────────
            $settings = [
                ['school_name',      'Dhaka Model High School & College', 'general'],
                ['school_address',   'House 14, Road 5, Dhanmondi, Dhaka-1205', 'general'],
                ['school_phone',     '02-9876543', 'general'],
                ['school_email',     'info@dmhsc.edu.bd', 'general'],
                ['school_type',      'school_and_college', 'general'],
                ['academic_board',   'DEB', 'academic'],
                ['date_format',      'd M Y', 'general'],
                ['timezone',         'Asia/Dhaka', 'general'],
                ['currency_symbol',  '৳', 'finance'],
                ['receipt_prefix',   'DMHSC', 'finance'],
                ['working_days',     'Sat,Sun,Mon,Tue,Wed', 'academic'],
                ['per_page',         '25', 'general'],
            ];
            $sStmt = $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)');
            foreach ($settings as $s) $sStmt->execute($s);

            // ── 2. Academic Sessions ───────────────────────────────────────────
            $pdo->exec("UPDATE academic_sessions SET is_current=0");
            $sessionDef = [
                ['2024', '2024-01-01', '2024-12-31', 0, 'completed'],
                ['2025', '2025-01-01', '2025-12-31', 0, 'completed'],
                ['2026', '2026-01-01', '2026-12-31', 1, 'active'],
            ];
            $ssStmt = $pdo->prepare('INSERT IGNORE INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)');
            foreach ($sessionDef as $sd) $ssStmt->execute($sd);
            $sessions = $pdo->query("SELECT id,session_name FROM academic_sessions ORDER BY start_date")->fetchAll(PDO::FETCH_KEY_PAIR);
            $sess2024 = array_search('2024',$sessions); $sess2025=array_search('2025',$sessions); $sess2026=array_search('2026',$sessions);
            $pdo->prepare('UPDATE system_settings SET meta_value=? WHERE meta_key="current_session_id"')->execute([$sess2026]);

            // ── 3. Classes & Sections ──────────────────────────────────────────
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

            $sectionDef = [
                'Playgroup'         => [['Flower','morning',35]],
                'KG (Nursery)'      => [['Blossom','morning',35]],
                'Class 1'           => [['A','morning',45],['B','day',45]],
                'Class 2'           => [['A','morning',45],['B','day',45]],
                'Class 3'           => [['A','morning',45],['B','day',45]],
                'Class 4'           => [['A','morning',45],['B','day',45]],
                'Class 5'           => [['A','morning',45],['B','day',45]],
                'Class 6'           => [['A','morning',50],['B','day',50]],
                'Class 7'           => [['A','morning',50],['B','day',50]],
                'Class 8'           => [['A','morning',50],['B','day',50]],
                'Class 9 (SSC-I)'   => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
                'Class 10 (SSC-II)' => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
                'HSC 1st Year'      => [['Science','morning',40],['Commerce','day',40]],
                'HSC 2nd Year'      => [['Science','morning',40],['Commerce','day',40]],
            ];
            $secStmt = $pdo->prepare('INSERT IGNORE INTO sections (class_id,section_name,shift,capacity) VALUES (?,?,?,?)');
            foreach ($sectionDef as $className => $sections) {
                $cid = $classMap[$className] ?? null;
                if (!$cid) continue;
                foreach ($sections as [$sname,$shift,$cap]) $secStmt->execute([$cid,$sname,$shift,$cap]);
            }
            $secRows = $pdo->query("SELECT s.id,s.section_name,c.class_name FROM sections s JOIN classes c ON c.id=s.class_id")->fetchAll();
            $sectionMap = [];
            foreach ($secRows as $r) $sectionMap[$r['class_name']][$r['section_name']] = $r['id'];

            // ── 4. Groups & Streams ────────────────────────────────────────────
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

            // ── 5. Rooms ──────────────────────────────────────────────────────
            $roomDef = [
                ['Room 101','101',1,45,'classroom'],['Room 102','102',1,45,'classroom'],
                ['Room 103','103',1,45,'classroom'],['Room 104','104',1,45,'classroom'],
                ['Room 201','201',2,50,'classroom'],['Room 202','202',2,50,'classroom'],
                ['Room 203','203',2,50,'classroom'],['Room 204','204',2,50,'classroom'],
                ['Science Lab','SL-1',1,32,'lab'],['Computer Lab','CMP-1',2,25,'lab'],
                ['Library','LIB',1,60,'library'],['Principal Office','PRIN',1,5,'office'],
                ['Exam Hall 1','EH-1',3,80,'exam_hall'],
            ];
            $rStmt = $pdo->prepare('INSERT IGNORE INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)');
            foreach ($roomDef as $r) $rStmt->execute($r);
            $roomRows = $pdo->query("SELECT id FROM rooms WHERE room_type='classroom' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            $labRoom  = $pdo->query("SELECT id FROM rooms WHERE room_name='Science Lab' LIMIT 1")->fetchColumn();
            $compRoom = $pdo->query("SELECT id FROM rooms WHERE room_name='Computer Lab' LIMIT 1")->fetchColumn();

            // ── 6. Subjects ────────────────────────────────────────────────────
            $subjectDef = [
                ['Bangla 1st Paper','BAN1','core',0,0,0,0,1],
                ['Bangla 2nd Paper','BAN2','core',0,0,0,0,0],
                ['English 1st Paper','ENG1','core',0,0,0,0,1],
                ['English 2nd Paper','ENG2','core',0,0,0,0,0],
                ['Mathematics','MATH','core',0,0,0,0,1],
                ['General Science','GSCI','core',0,0,0,1,1],
                ['Physics','PHY','core',1,0,0,1,1],
                ['Chemistry','CHE','core',1,0,0,1,1],
                ['Biology','BIO','core',1,0,0,1,1],
                ['Accounting','ACC','core',1,0,0,0,1],
                ['Finance & Banking','FIN','core',1,0,0,0,1],
                ['Business Studies','BUS','core',1,0,0,0,1],
                ['ICT','ICT','core',0,0,0,1,1],
                ['Islam Studies','ISL','religious',0,1,0,0,1],
                ['Hindu Studies','HIN','religious',0,1,0,0,1],
                ['Bangladesh Studies','BGS','core',0,0,0,0,1],
                ['Social Science','SOC','core',0,0,0,0,1],
                ['Agriculture','AGR','core',0,0,0,1,1],
                ['Physical Education','PE','core',0,0,0,0,0],
            ];
            $subjStmt = $pdo->prepare('INSERT IGNORE INTO subjects (subject_name,subject_code,subject_type,is_group_subject,is_religious_alt,can_be_4th,has_practical,has_mcq) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($subjectDef as $s) $subjStmt->execute($s);
            $subjMap = $pdo->query("SELECT subject_code,id FROM subjects")->fetchAll(PDO::FETCH_KEY_PAIR);

            // ── 7. Class-Subject Mapping ───────────────────────────────────────
            $csStmt = $pdo->prepare('INSERT IGNORE INTO class_subjects (class_id,session_id,subject_id,group_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical,classes_per_week) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $primarySubjs  = ['BAN1','ENG1','MATH','ISL','BGS'];
            $juniorSubjs   = ['BAN1','BAN2','ENG1','ENG2','MATH','GSCI','ICT','ISL','SOC'];
            $seniorCommon  = ['BAN1','BAN2','ENG1','ENG2','MATH','ICT','ISL','BGS'];
            $sciSubjs      = ['PHY','CHE','BIO'];
            $comSubjs      = ['ACC','FIN','BUS'];
            $sciGrpId      = $groupMap['Science'] ?? null;
            $comGrpId      = $groupMap['Commerce'] ?? null;

            foreach ([$sess2024,$sess2025,$sess2026] as $sessId) {
                foreach (['Playgroup','KG (Nursery)'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach (['BAN1','ENG1','MATH','ISL'] as $sc) {
                        $sid = $subjMap[$sc]??null; if (!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0,5]);
                    }
                }
                foreach (['Class 1','Class 2','Class 3','Class 4','Class 5'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach ($primarySubjs as $sc) {
                        $sid = $subjMap[$sc]??null; if (!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0,5]);
                    }
                }
                foreach (['Class 6','Class 7','Class 8'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach ($juniorSubjs as $sc) {
                        $sid = $subjMap[$sc]??null; if (!$sid) continue;
                        $fm = in_array($sc,['BAN2','ENG2'])?100:($sc==='ICT'?75:100);
                        $csStmt->execute([$cid,$sessId,$sid,null,$fm,0,0,33,0,0,4]);
                    }
                }
                foreach (['Class 9 (SSC-I)','Class 10 (SSC-II)','HSC 1st Year','HSC 2nd Year'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach ($seniorCommon as $sc) {
                        $sid=$subjMap[$sc]??null; if(!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0,4]);
                    }
                    foreach ($sciSubjs as $sc) {
                        $sid=$subjMap[$sc]??null; if(!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,$sciGrpId,75,25,25,25,10,10,3]);
                    }
                    foreach ($comSubjs as $sc) {
                        $sid=$subjMap[$sc]??null; if(!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,$comGrpId,100,0,0,33,0,0,3]);
                    }
                }
            }

            // ── 8. Staff — 15 teachers + admin staff ──────────────────────────
            // [username, first_name, last_name, designation, department, joining, gender, base_salary]
            $staffDef = [
                ['principal',   'A.B.M. Mizanur', 'Rahman',    'Principal',                 'Administration',    '2015-01-01','male',   85000],
                ['vp_shahida',  'Shahida',         'Begum',     'Vice Principal',             'Administration',    '2016-03-15','female', 72000],
                ['t_bangla1',   'Razia',           'Sultana',   'Senior Teacher (Bangla)',    'Arts & Humanities', '2015-09-01','female', 46000],
                ['t_bangla2',   'Mostak',          'Ahmed',     'Assistant Teacher (Bangla)', 'Arts & Humanities', '2019-06-01','male',   38000],
                ['t_english1',  'Mosharraf',       'Hossain',   'Senior Teacher (English)',   'Arts & Humanities', '2015-03-01','male',   47000],
                ['t_english2',  'Shirin',          'Akter',     'Assistant Teacher (English)','Arts & Humanities', '2020-01-10','female', 36000],
                ['t_math1',     'Gias Uddin',      'Ahmed',     'Senior Teacher (Math)',      'Science',           '2016-01-01','male',   48000],
                ['t_math2',     'Nargis',          'Parvin',    'Assistant Teacher (Math)',   'Science',           '2021-03-20','female', 35000],
                ['t_physics',   'Md. Shahadat',    'Hossain',   'Senior Teacher (Physics)',   'Science',           '2018-01-15','male',   45000],
                ['t_chemistry', 'Taslima',         'Khatun',    'Assistant Teacher (Chemistry)','Science',         '2019-09-01','female', 40000],
                ['t_biology',   'Nurul',           'Islam',     'Senior Teacher (Biology)',   'Science',           '2017-07-01','male',   42000],
                ['t_ict',       'Raihan',          'Uddin',     'ICT Teacher',               'Science',           '2020-11-15','male',   37000],
                ['t_islam',     'Hafizur',         'Rahman',    'Islamic Studies Teacher',   'Arts & Humanities', '2016-05-01','male',   34000],
                ['t_social',    'Dilruba',         'Yasmin',    'Social Science Teacher',    'Arts & Humanities', '2018-09-01','female', 36000],
                ['t_commerce',  'Kamal',           'Hossain',   'Commerce Teacher',          'Commerce',          '2017-03-01','male',   41000],
                ['accountant',  'Jasmine',         'Akter',     'Accountant',                'Administration',    '2017-01-01','female', 35000],
                ['clerk1',      'Ariful',          'Islam',     'Office Clerk',              'Administration',    '2019-04-01','male',   22000],
            ];

            $allRoles = $pdo->query("SELECT role_slug,id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
            $teacherRoleId = $allRoles['teacher'] ?? null;
            $staffIds = [];

            foreach ($staffDef as $idx => $sd) {
                [$uname,$fname,$lname,$desig,$dept,$joining,$gender,$salary] = $sd;
                $fullName = "$fname $lname";
                $email = strtolower(preg_replace('/[^a-zA-Z]/','',$fname)).'@dmhsc.edu.bd';

                $ck = $pdo->prepare('SELECT id FROM users WHERE username=?');
                $ck->execute([$uname]); $uid = $ck->fetchColumn();
                if (!$uid) {
                    $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')
                        ->execute([$uname,password_hash('Staff@123',PASSWORD_BCRYPT),$fullName,$email,'active']);
                    $uid = (int)$pdo->lastInsertId();
                }

                $empId = 'EMP-' . str_pad($idx+1, 3, '0', STR_PAD_LEFT);
                $pdo->prepare('INSERT IGNORE INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,department,dob,gender,religion,blood_group,joining_date,contract_type,salary_type,base_salary,max_classes_per_day,max_classes_per_week) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid,$empId,$fname,$lname,$desig,$dept,'1985-05-15',$gender,'Islam','O+',$joining,'permanent','fixed',$salary,5,20]);

                $roleSlug = ($uname === 'accountant' || $uname === 'clerk1') ? 'accountant' : (($uname === 'principal' || $uname === 'vp_shahida') ? 'principal' : 'teacher');
                $roleId = $allRoles[$roleSlug] ?? $teacherRoleId;
                if ($roleId) $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid,$roleId]);
                $staffIds[$uname] = $uid;
            }

            // ── 9. Teacher Subject Expertise ──────────────────────────────────
            $expertiseMap = [
                't_bangla1'   => ['BAN1','BAN2'],
                't_bangla2'   => ['BAN1','BAN2'],
                't_english1'  => ['ENG1','ENG2'],
                't_english2'  => ['ENG1','ENG2'],
                't_math1'     => ['MATH'],
                't_math2'     => ['MATH'],
                't_physics'   => ['PHY','GSCI'],
                't_chemistry' => ['CHE','GSCI'],
                't_biology'   => ['BIO','GSCI','AGR'],
                't_ict'       => ['ICT'],
                't_islam'     => ['ISL'],
                't_social'    => ['SOC','BGS','HIN'],
                't_commerce'  => ['ACC','FIN','BUS'],
            ];
            $tsStmt = $pdo->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id,subject_id) VALUES (?,?)');
            foreach ($expertiseMap as $uname => $codes) {
                $tid = $staffIds[$uname] ?? null; if (!$tid) continue;
                foreach ($codes as $code) {
                    $sid = $subjMap[$code] ?? null; if (!$sid) continue;
                    $tsStmt->execute([$tid, $sid]);
                }
            }

            // ── 10. Class Teacher Assignments ─────────────────────────────────
            // Format: [class_name][section_name] => teacher_username
            $classTeacherMap = [
                'Class 1'           => ['A' => 't_bangla1', 'B' => 't_english2'],
                'Class 2'           => ['A' => 't_math2',   'B' => 't_bangla2'],
                'Class 3'           => ['A' => 't_bangla1', 'B' => 't_english1'],
                'Class 4'           => ['A' => 't_math1',   'B' => 't_social'],
                'Class 5'           => ['A' => 't_english1','B' => 't_math2'],
                'Class 6'           => ['A' => 't_bangla1', 'B' => 't_english2'],
                'Class 7'           => ['A' => 't_math1',   'B' => 't_social'],
                'Class 8'           => ['A' => 't_english1','B' => 't_biology'],
                'Class 9 (SSC-I)'   => ['Science' => 't_physics', 'Commerce' => 't_commerce', 'Arts' => 't_bangla2'],
                'Class 10 (SSC-II)' => ['Science' => 't_chemistry','Commerce' => 't_commerce','Arts' => 't_social'],
                'HSC 1st Year'      => ['Science' => 't_biology',  'Commerce' => 't_commerce'],
                'HSC 2nd Year'      => ['Science' => 't_physics',  'Commerce' => 't_commerce'],
            ];
            $updateSecStmt = $pdo->prepare("UPDATE sections SET class_teacher_id = ?, class_teacher_first_period_days = 'Sat,Sun,Mon,Tue,Wed' WHERE id = ?");
            foreach ($classTeacherMap as $cname => $secs) {
                foreach ($secs as $sname => $teacherKey) {
                    $secId = $sectionMap[$cname][$sname] ?? null;
                    $tid = $staffIds[$teacherKey] ?? null;
                    if ($secId && $tid) {
                        $updateSecStmt->execute([$tid, $secId]);
                    }
                }
            }

            // ── 11. Period Configuration ───────────────────────────────────────
            $periods_config = [
                ['name' => 'Period 1', 'start' => '08:00', 'end' => '08:45', 'is_break' => 0],
                ['name' => 'Period 2', 'start' => '08:45', 'end' => '09:30', 'is_break' => 0],
                ['name' => 'Period 3', 'start' => '09:30', 'end' => '10:15', 'is_break' => 0],
                ['name' => 'Tiffin Break', 'start' => '10:15', 'end' => '10:45', 'is_break' => 1],
                ['name' => 'Period 4', 'start' => '10:45', 'end' => '11:30', 'is_break' => 0],
                ['name' => 'Period 5', 'start' => '11:30', 'end' => '12:15', 'is_break' => 0],
                ['name' => 'Dhuhr Prayer', 'start' => '12:15', 'end' => '12:45', 'is_break' => 1],
                ['name' => 'Period 6', 'start' => '12:45', 'end' => '13:30', 'is_break' => 0],
            ];
            $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ("routine_periods",?,"academic") ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)')->execute([json_encode($periods_config)]);

            // ── 12. Master Routine Slots ───────────────────────────────────────
            // Delete existing master routine for 2026
            $pdo->prepare("DELETE FROM routine_slots WHERE session_id = ? AND is_substitute = 0")->execute([$sess2026]);

            $insSlot = $pdo->prepare("INSERT INTO routine_slots (session_id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time,status) VALUES (?,?,?,?,?,?,?,?,?,1)");

            $days = ['Saturday','Sunday','Monday','Tuesday','Wednesday'];

            // Helper: insert slot
            $slot = function($classN, $sectionN, $subCode, $teacherKey, $roomIdx, $day, $start, $end) use ($pdo, $sess2026, $classMap, $sectionMap, $subjMap, $staffIds, $roomRows, $insSlot, $labRoom, $compRoom) {
                $cid = $classMap[$classN] ?? null;
                $sid = $sectionMap[$classN][$sectionN] ?? null;
                $sub = $subjMap[$subCode] ?? null;
                $tid = $staffIds[$teacherKey] ?? null;
                $rid = ($roomIdx === 'lab') ? $labRoom : (($roomIdx === 'comp') ? $compRoom : ($roomRows[$roomIdx % count($roomRows)] ?? null));
                if ($cid && $sid && $sub) {
                    $insSlot->execute([$sess2026,$cid,$sid,$sub,$tid,$rid,$day,$start.':00',$end.':00']);
                }
            };

            // ── Primary Classes (Class 1 A & B) — Mon-Sat same slot, alternating subjects ──
            $primarySchedule = [
                // [class, section, day, p1_sub, p1_teacher, p2_sub, p2_teacher, p3_sub, p3_teacher, p4_sub, p4_teacher]
                ['Class 1','A','Saturday',  'BAN1','t_bangla1', 'ENG1','t_english1','MATH','t_math1',   'ISL','t_islam'],
                ['Class 1','A','Sunday',    'ENG1','t_english1','MATH','t_math1',   'BAN1','t_bangla1', 'BGS','t_social'],
                ['Class 1','A','Monday',    'MATH','t_math1',   'BAN1','t_bangla1', 'ISL','t_islam',    'ENG1','t_english1'],
                ['Class 1','A','Tuesday',   'ISL','t_islam',    'ENG1','t_english1','BGS','t_social',   'MATH','t_math1'],
                ['Class 1','A','Wednesday', 'BAN1','t_bangla1', 'MATH','t_math1',   'ENG1','t_english1','BGS','t_social'],
                ['Class 1','B','Saturday',  'ENG1','t_english2','BAN1','t_bangla2', 'ISL','t_islam',    'MATH','t_math2'],
                ['Class 1','B','Sunday',    'MATH','t_math2',   'ENG1','t_english2','BAN1','t_bangla2', 'BGS','t_social'],
                ['Class 1','B','Monday',    'BAN1','t_bangla2', 'ISL','t_islam',    'MATH','t_math2',   'ENG1','t_english2'],
                ['Class 1','B','Tuesday',   'BGS','t_social',   'MATH','t_math2',   'ENG1','t_english2','ISL','t_islam'],
                ['Class 1','B','Wednesday', 'MATH','t_math2',   'BAN1','t_bangla2', 'ISL','t_islam',    'ENG1','t_english2'],
            ];
            $pPeriods = [['08:00','08:45'],['08:45','09:30'],['09:30','10:15'],['10:45','11:30']];
            foreach ($primarySchedule as $row) {
                [$cn,$sn,$day,$s1,$t1,$s2,$t2,$s3,$t3,$s4,$t4] = $row;
                $roomI = (strpos($sn,'A')!==false) ? 0 : 1;
                $slot($cn,$sn,$s1,$t1,$roomI,$day,'08:00','08:45');
                $slot($cn,$sn,$s2,$t2,$roomI,$day,'08:45','09:30');
                $slot($cn,$sn,$s3,$t3,$roomI,$day,'09:30','10:15');
                $slot($cn,$sn,$s4,$t4,$roomI,$day,'10:45','11:30');
            }

            // ── Class 6, 7, 8 — 5 periods + period 6 for extra classes ──
            $juniorClasses = [
                ['Class 6','A',0], ['Class 6','B',1],
                ['Class 7','A',2], ['Class 7','B',3],
                ['Class 8','A',4], ['Class 8','B',5],
            ];
            $juniorSubjRotation = [
                'Saturday'  => ['BAN1','ENG1','MATH','ICT','GSCI','SOC'],
                'Sunday'    => ['ENG1','BAN1','SOC','MATH','ISL','GSCI'],
                'Monday'    => ['MATH','ENG2','BAN2','GSCI','ICT','BAN1'],
                'Tuesday'   => ['GSCI','MATH','ENG1','BAN1','SOC','ICT'],
                'Wednesday' => ['ICT','SOC','MATH','ENG1','BAN2','MATH'],
            ];
            $juniorTeacherMap = ['BAN1'=>'t_bangla1','BAN2'=>'t_bangla2','ENG1'=>'t_english1','ENG2'=>'t_english2','MATH'=>'t_math1','GSCI'=>'t_physics','ICT'=>'t_ict','ISL'=>'t_islam','SOC'=>'t_social'];
            $juniorPeriods = [['08:00','08:45'],['08:45','09:30'],['09:30','10:15'],['10:45','11:30'],['11:30','12:15'],['12:45','13:30']];
            foreach ($juniorClasses as [$cn,$sn,$roomI]) {
                foreach ($days as $day) {
                    $subjects = $juniorSubjRotation[$day];
                    $isASection = ($sn === 'A' || $sn === 'Science');
                    // Section B uses slightly shifted rotation
                    if (!$isASection) $subjects = array_merge(array_slice($subjects,1), [array_shift($subjects)]);
                    foreach ($juniorPeriods as $pi => [$s,$e]) {
                        $sc = $subjects[$pi] ?? 'BAN1';
                        // Class 8 gets extra period 6 (extra English or Math)
                        if ($pi === 5 && $cn !== 'Class 8') continue;
                        $tk = $juniorTeacherMap[$sc] ?? 't_bangla1';
                        // Alternate math teacher for B sections
                        if ($sc === 'MATH' && !$isASection) $tk = 't_math2';
                        if ($sc === 'BAN1' && !$isASection) $tk = 't_bangla2';
                        $slot($cn,$sn,$sc,$tk,$roomI,$day,$s,$e);
                    }
                }
            }

            // ── SSC & HSC classes — science/commerce splits, 6 periods ──
            $seniorClasses = [
                ['Class 9 (SSC-I)','Science',  't_physics',   $sciGrpId ?? null, 6],
                ['Class 9 (SSC-I)','Commerce', 't_commerce',  $comGrpId ?? null, 7],
                ['Class 10 (SSC-II)','Science','t_chemistry', $sciGrpId ?? null, 0],
                ['Class 10 (SSC-II)','Commerce','t_commerce', $comGrpId ?? null, 1],
                ['HSC 1st Year','Science',     't_biology',   $sciGrpId ?? null, 2],
                ['HSC 1st Year','Commerce',    't_commerce',  $comGrpId ?? null, 3],
                ['HSC 2nd Year','Science',     't_physics',   $sciGrpId ?? null, 4],
                ['HSC 2nd Year','Commerce',    't_commerce',  $comGrpId ?? null, 5],
            ];
            $sciRotation = ['Saturday'=>['BAN1','ENG1','MATH','PHY','CHE','BIO'],'Sunday'=>['ENG1','BAN1','PHY','MATH','CHE','BIO'],'Monday'=>['MATH','PHY','BAN2','ENG2','BIO','ICT'],'Tuesday'=>['BIO','CHE','MATH','ENG1','PHY','ISL'],'Wednesday'=>['PHY','MATH','BIO','CHE','ENG1','BAN1']];
            $comRotation = ['Saturday'=>['BAN1','ENG1','MATH','ACC','FIN','BUS'],'Sunday'=>['ENG1','BAN1','ACC','MATH','FIN','BUS'],'Monday'=>['MATH','ACC','BAN2','ENG2','BUS','ICT'],'Tuesday'=>['FIN','BUS','MATH','ENG1','ACC','ISL'],'Wednesday'=>['ACC','MATH','FIN','BUS','ENG1','BAN1']];
            $senTeacherMap = ['BAN1'=>'t_bangla1','BAN2'=>'t_bangla2','ENG1'=>'t_english1','ENG2'=>'t_english2','MATH'=>'t_math1','PHY'=>'t_physics','CHE'=>'t_chemistry','BIO'=>'t_biology','ACC'=>'t_commerce','FIN'=>'t_commerce','BUS'=>'t_commerce','ICT'=>'t_ict','ISL'=>'t_islam'];
            $senPeriods = [['08:00','08:45'],['08:45','09:30'],['09:30','10:15'],['10:45','11:30'],['11:30','12:15'],['12:45','13:30']];
            foreach ($seniorClasses as [$cn,$sn,$classTeacher,$grpId,$roomI]) {
                $isSci = ($sn === 'Science');
                foreach ($days as $day) {
                    $subjs = $isSci ? ($sciRotation[$day] ?? []) : ($comRotation[$day] ?? []);
                    foreach ($senPeriods as $pi => [$s,$e]) {
                        $sc = $subjs[$pi] ?? 'BAN1';
                        $tk = $senTeacherMap[$sc] ?? 't_bangla1';
                        if ($sc === 'MATH') $tk = ($sn === 'Science') ? 't_math1' : 't_math2';
                        $slot($cn,$sn,$sc,$tk,$roomI,$day,$s,$e);
                    }
                }
            }

            // ── 13. Students ───────────────────────────────────────────────────
            $studentRoleId = $allRoles['student'] ?? null;
            $stuInsUser  = $pdo->prepare('INSERT IGNORE INTO users (username,password_hash,full_name,status) VALUES (?,?,?,?)');
            $stuInsProf  = $pdo->prepare('INSERT IGNORE INTO student_profiles (user_id,student_id_no,first_name,last_name,dob,gender,religion,blood_group,father_name,mother_name,guardian_phone,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stuInsEnr   = $pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id,session_id,class_id,section_id,group_id,roll_number,status) VALUES (?,?,?,?,?,?,?)');
            $stuInsRole  = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)');

            $studentIds = [];
            $counter = 1;
            foreach ($sectionDef as $className => $sections) {
                $cid2026 = $classMap[$className] ?? null;
                if (!$cid2026) continue;
                foreach ($sections as [$sname,,]) {
                    $secId2026 = $sectionMap[$className][$sname] ?? null;
                    if (!$secId2026) continue;
                    $grpId2026 = in_array($sname,['Science','Commerce']) ? ($groupMap[$sname]??null) : null;
                    $numStudents = in_array($className,['Class 9 (SSC-I)','Class 10 (SSC-II)','HSC 1st Year','HSC 2nd Year']) ? 5 : 4;
                    $sectionStudents = [];
                    for ($n = 1; $n <= $numStudents; $n++) {
                        $isMale = rand(0,1);
                        $first = $isMale ? $MALE_FIRST[array_rand($MALE_FIRST)] : $FEMALE_FIRST[array_rand($FEMALE_FIRST)];
                        $last  = $LAST_NAMES[array_rand($LAST_NAMES)];
                        $uname = strtolower($first).$counter;
                        $stuId = 'DMHSC-2026-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
                        $stuInsUser->execute([$uname,password_hash('Student@123',PASSWORD_BCRYPT),"$first $last",'active']);
                        $uid = (int)$pdo->lastInsertId();
                        if (!$uid) { $ck=$pdo->prepare("SELECT id FROM users WHERE username=?"); $ck->execute([$uname]); $uid=(int)$ck->fetchColumn(); }
                        if (!$uid) continue;
                        $stuInsProf->execute([$uid,$stuId,$first,$last,'2012-08-10',$isMale?'male':'female','Islam','O+','Father Name','Mother Name','01711000000','2024-01-05']);
                        if ($studentRoleId) $stuInsRole->execute([$uid,$studentRoleId]);
                        $stuInsEnr->execute([$uid,$sess2026,$cid2026,$secId2026,$grpId2026,$n,'active']);
                        $stuInsEnr->execute([$uid,$sess2025,$cid2026,$secId2026,$grpId2026,$n,'active']);
                        $stuInsEnr->execute([$uid,$sess2024,$cid2026,$secId2026,$grpId2026,$n,'active']);
                        $sectionStudents[] = $uid;
                        $counter++;
                    }
                    $studentIds[$className][$sname] = $sectionStudents;
                }
            }

            // ── 14. Liquid Accounts ────────────────────────────────────────────
            $pdo->exec("TRUNCATE TABLE accounts"); $pdo->exec("TRUNCATE TABLE account_transactions");
            $insAccount = $pdo->prepare("INSERT INTO accounts (account_name,account_type,account_number,bank_name,current_balance,notes) VALUES (?,?,?,?,?,?)");
            $insAccount->execute(['Cash Register','cash',null,null,150000.00,'Main cash chest inside office']);
            $cash_acc_id = $pdo->lastInsertId();
            $insAccount->execute(['Main Bank Account','bank','122-344-555-6','Prime Bank Ltd.',450000.00,'Primary corporate banking']);
            $bank_acc_id = $pdo->lastInsertId();
            $insAccount->execute(['bKash Merchant Wallet','mobile_banking','01711223344','bKash',25000.00,'Mobile collections']);
            $bkash_acc_id = $pdo->lastInsertId();
            $insTx = $pdo->prepare("INSERT INTO account_transactions (account_id,amount,transaction_type,description,created_by) VALUES (?,?,?,?,1)");
            $insTx->execute([$cash_acc_id, 150000.00,'adjustment','Opening balance']);
            $insTx->execute([$bank_acc_id, 450000.00,'adjustment','Opening balance']);
            $insTx->execute([$bkash_acc_id, 25000.00,'adjustment','Opening balance']);

            // ── 15. Fee structures & Payments ─────────────────────────────────
            $pdo->exec("DELETE FROM fee_categories");
            $insFeeCat = $pdo->prepare("INSERT INTO fee_categories (category_name,category_type,status) VALUES (?,?,1)");
            $insFeeCat->execute(['Tuition Fee','tuition']); $tutCatId = $pdo->lastInsertId();
            $insFeeCat->execute(['Exam Fee','exam']); $examCatId = $pdo->lastInsertId();
            $fsStmt = $pdo->prepare('INSERT INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)');
            foreach (['Class 1','Class 5','Class 8','Class 10 (SSC-II)','HSC 2nd Year'] as $cn) {
                $cid = $classMap[$cn] ?? null; if (!$cid) continue;
                $fsStmt->execute([$sess2026,$cid,$tutCatId,1200,10,'monthly']);
                $fsStmt->execute([$sess2025,$cid,$tutCatId,1100,10,'monthly']);
                $fsStmt->execute([$sess2024,$cid,$tutCatId,1000,10,'monthly']);
            }
            $flStmt = $pdo->prepare('INSERT INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,?)');
            $fpStmt = $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id) VALUES (?,?,?,?,?,?,?,?)');
            $rcpN = 1;
            foreach ($studentIds as $cname => $secData) {
                foreach ($secData as $sname => $stuArr) {
                    foreach ($stuArr as $uid) {
                        $flStmt->execute([$uid,$sess2024,$tutCatId,1000,'2024-03-10',3,2024,'paid']);
                        $ledId=$pdo->lastInsertId();
                        $fpStmt->execute([$ledId,$uid,1000,'2024-03-05','cash','DMHSC-2024-'.str_pad($rcpN++,6,'0',STR_PAD_LEFT),1,$cash_acc_id]);
                        $flStmt->execute([$uid,$sess2025,$tutCatId,1100,'2025-05-10',5,2025,'paid']);
                        $ledId=$pdo->lastInsertId();
                        $fpStmt->execute([$ledId,$uid,1100,'2025-05-06','bank','DMHSC-2025-'.str_pad($rcpN++,6,'0',STR_PAD_LEFT),1,$bank_acc_id]);
                        $flStmt->execute([$uid,$sess2026,$tutCatId,1200,'2026-06-10',6,2026,'paid']);
                        $ledId=$pdo->lastInsertId();
                        $fpStmt->execute([$ledId,$uid,1200,'2026-06-08','mobile_banking','DMHSC-2026-'.str_pad($rcpN++,6,'0',STR_PAD_LEFT),1,$bkash_acc_id]);
                    }
                }
            }
            $sumCash=(float)$pdo->query("SELECT SUM(amount) FROM fee_payments WHERE account_id=$cash_acc_id")->fetchColumn();
            $sumBank=(float)$pdo->query("SELECT SUM(amount) FROM fee_payments WHERE account_id=$bank_acc_id")->fetchColumn();
            $sumBkash=(float)$pdo->query("SELECT SUM(amount) FROM fee_payments WHERE account_id=$bkash_acc_id")->fetchColumn();
            $pdo->prepare("UPDATE accounts SET current_balance=current_balance+? WHERE id=?")->execute([$sumCash,$cash_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance=current_balance+? WHERE id=?")->execute([$sumBank,$bank_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance=current_balance+? WHERE id=?")->execute([$sumBkash,$bkash_acc_id]);
            $insTx->execute([$cash_acc_id,$sumCash,'deposit','Fee collections (Cash)']);
            $insTx->execute([$bank_acc_id,$sumBank,'deposit','Fee collections (Bank)']);
            $insTx->execute([$bkash_acc_id,$sumBkash,'deposit','Fee collections (bKash)']);

            // ── 16. Expenses & Income ──────────────────────────────────────────
            $pdo->exec("DELETE FROM expense_categories");
            $insExpCat=$pdo->prepare("INSERT INTO expense_categories (category_name,status) VALUES (?,1)");
            $insExpCat->execute(['Utilities & Bills']); $utCatId=$pdo->lastInsertId();
            $insExpCat->execute(['Stationery & Printing']); $prCatId=$pdo->lastInsertId();
            $insExp=$pdo->prepare("INSERT INTO expenses (session_id,expense_category_id,amount,expense_date,description,vendor,status,approved_by,account_id) VALUES (?,?,?,?,?,?,'approved',1,?)");
            $insExp->execute([$sess2024,$utCatId,12500.00,'2024-04-15','Electricity bill (April 2024)','DESCO',$bank_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance=current_balance-12500 WHERE id=?")->execute([$bank_acc_id]);
            $insTx->execute([$bank_acc_id,-12500,'withdrawal','DESCO electricity bill']);
            $insExp->execute([$sess2025,$prCatId,8500.00,'2025-09-20','Bulk stationery supplies','Reza Traders',$cash_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance=current_balance-8500 WHERE id=?")->execute([$cash_acc_id]);
            $insTx->execute([$cash_acc_id,-8500,'withdrawal','Reza Traders stationery']);
            $pdo->exec("DELETE FROM income_categories");
            $insIncCat=$pdo->prepare("INSERT INTO income_categories (category_name,status) VALUES (?,1)");
            $insIncCat->execute(['Donations & Sponsors']); $donCatId=$pdo->lastInsertId();
            $insInc=$pdo->prepare("INSERT INTO incomes (session_id,income_category_id,amount,income_date,description,received_by,account_id) VALUES (?,?,?,?,?,1,?)");
            $insInc->execute([$sess2025,$donCatId,25000.00,'2025-10-12','Alumni community donation',$bank_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance=current_balance+25000 WHERE id=?")->execute([$bank_acc_id]);
            $insTx->execute([$bank_acc_id,25000,'deposit','Alumni donation']);

            // ── 17. Staff Attendance — today + a few past dates ───────────────
            // Mark 2 teachers absent today for demo (substitution planner)
            $today = date('Y-m-d');
            $todayWeekday = date('l'); // e.g. 'Saturday'
            $insAtt = $pdo->prepare("INSERT INTO staff_attendance (staff_id,attendance_date,status) VALUES (?,?,'absent') ON DUPLICATE KEY UPDATE status='absent'");
            // Always mark these two absent today for demo
            $insAtt->execute([$staffIds['t_english1'], $today]);
            $insAtt->execute([$staffIds['t_math1'], $today]);

            // Past attendance records (some absences in last 30 days)
            $pastDates = [];
            for ($d=1; $d<=30; $d++) {
                $pastDates[] = date('Y-m-d', strtotime("-$d days"));
            }
            $insAttPast = $pdo->prepare("INSERT IGNORE INTO staff_attendance (staff_id,attendance_date,status) VALUES (?,?,?)");
            $allTeacherIds = array_filter(array_map(fn($k) => $staffIds[$k] ?? null, array_keys($expertiseMap)));
            foreach ($allTeacherIds as $tid) {
                foreach ($pastDates as $pd) {
                    // 90% present, 10% absent
                    $st = rand(1,10) === 1 ? 'absent' : 'present';
                    $insAttPast->execute([$tid, $pd, $st]);
                }
            }

            // ── 18. Today's substitute slots demo ─────────────────────────────
            // For the two absent teachers today (t_english1, t_math1), create
            // substitute slot records so the Substitution Planner shows real data
            if ($todayWeekday !== 'Thursday' && $todayWeekday !== 'Friday') { // only on working days
                $affStmt = $pdo->prepare("
                    SELECT id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time
                    FROM routine_slots
                    WHERE session_id = ? AND teacher_id IN (?,?) AND day_of_week = ? AND status=1 AND is_substitute=0
                    LIMIT 4
                ");
                $affStmt->execute([$sess2026, $staffIds['t_english1'], $staffIds['t_math1'], $todayWeekday]);
                $affSlots = $affStmt->fetchAll(PDO::FETCH_ASSOC);
                // Assign substitute: english slots → t_english2, math slots → t_math2
                $insSub = $pdo->prepare("INSERT IGNORE INTO routine_slots (session_id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time,is_substitute,substitute_date,status) VALUES (?,?,?,?,?,?,?,?,?,1,?,1)");
                foreach ($affSlots as $asl) {
                    $subTeacher = ($asl['teacher_id'] === $staffIds['t_english1']) ? $staffIds['t_english2'] : $staffIds['t_math2'];
                    $insSub->execute([$sess2026,$asl['class_id'],$asl['section_id'],$asl['subject_id'],$subTeacher,$asl['room_id'],$asl['day_of_week'],$asl['start_time'],$asl['end_time'],$today]);
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $messages[] = '🎉 <strong>Demo Institute Seeded!</strong> Created: 3 academic sessions, 14 classes, 25+ sections, 19 subjects, 15 teachers with subject expertise, class teacher assignments for all sections, full 5-day master routine for all classes, today\'s absent teacher records with substitute assignments, 3-year student enrolments, fee records, and account transactions.';
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $errors[] = 'Seeding failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
,'Arafat','Arif','Ashraf','Atik','Aziz','Borhan','Farhan','Faruk','Forhad','Golam','Hafiz','Harun','Hasan','Ibrahim','Imran','Jahangir','Jamal','Kabir','Kamal','Khaled','Liton','Mahbub','Masum','Minhaj','Mizanur','Nazmul','Nasir','Nabil','Nahid','Palash','Pervez','Raihan','Rahim','Rakib','Rifat','Ripon','Sagor','Salim','Shahed','Shamim','Sharif','Shohel','Sohag','Sumon','Tanvir','Tariq','Wahid','Wasim','Yasin','Zahir','Zakir'];
            $FEMALE_FIRST = ['Afsana','Aisha','Akhi','Alia','Amena','Arifa','Asma','Ayesha','Bilkis','Champa','Dipa','Dilruba','Farhana','Fariha','Fatema','Gulnaz','Hasina','Israt','Jasmine','Jesmin','Khaleda','Lima','Lubna','Marium','Mita','Mitu','Mohua','Monira','Munni','Nasrin','Nazmun','Nilufar','Nipa','Parveen','Roksana','Runa','Sabina','Sadia','Saima','Salma','Sharmin','Shilpi','Sumaiya','Sumona','Sumi','Tania','Urmi','Yasmin','Zannat'];
            $LAST_NAMES   = ['Ahmed','Akhter','Alam','Ali','Bhuiyan','Choudhury','Das','Gazi','Hossain','Huda','Islam','Jahan','Kabir','Karim','Khan','Khatun','Mahmud','Majumdar','Mia','Molla','Mondal','Mridha','Paul','Rahman','Roy','Sarkar','Siddique','Talukder','Uddin','Ullah'];
            $BLOOD_GROUPS = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
            $RELIGIONS    = ['Islam','Islam','Islam','Islam','Islam','Islam','Islam','Islam','Hinduism','Hinduism','Christianity','Buddhism'];
            
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

            // ── 2. Academic Sessions ─────────────────────────────────────────────────
            $pdo->exec("UPDATE academic_sessions SET is_current=0");
            $sessionDef = [
                ['2024', '2024-01-01', '2024-12-31', 0, 'completed'],
                ['2025', '2025-01-01', '2025-12-31', 0, 'completed'],
                ['2026', '2026-01-01', '2026-12-31', 1, 'active'],
            ];
            $ssStmt = $pdo->prepare('INSERT IGNORE INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,?,?)');
            foreach ($sessionDef as $sd) $ssStmt->execute($sd);
            $sessions = $pdo->query("SELECT id,session_name FROM academic_sessions ORDER BY start_date")->fetchAll(PDO::FETCH_KEY_PAIR);
            $sess2024 = array_search('2024',$sessions); $sess2025=array_search('2025',$sessions); $sess2026=array_search('2026',$sessions);
            $pdo->prepare('UPDATE system_settings SET meta_value=? WHERE meta_key="current_session_id"')->execute([$sess2026]);

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

            $sectionDef = [
                'Playgroup'         => [['Flower','morning',35]],
                'KG (Nursery)'      => [['Blossom','morning',35]],
                'Class 1'           => [['A','morning',45],['B','day',45]],
                'Class 2'           => [['A','morning',45],['B','day',45]],
                'Class 3'           => [['A','morning',45],['B','day',45]],
                'Class 4'           => [['A','morning',45],['B','day',45]],
                'Class 5'           => [['A','morning',45],['B','day',45]],
                'Class 6'           => [['A','morning',50],['B','day',50]],
                'Class 7'           => [['A','morning',50],['B','day',50]],
                'Class 8'           => [['A','morning',50],['B','day',50]],
                'Class 9 (SSC-I)'   => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
                'Class 10 (SSC-II)' => [['Science','morning',40],['Commerce','day',40],['Arts','day',40]],
                'HSC 1st Year'      => [['Science','morning',40],['Commerce','day',40]],
                'HSC 2nd Year'      => [['Science','morning',40],['Commerce','day',40]],
            ];
            $secStmt = $pdo->prepare('INSERT IGNORE INTO sections (class_id,section_name,shift,capacity) VALUES (?,?,?,?)');
            foreach ($sectionDef as $className => $sections) {
                $cid = $classMap[$className] ?? null;
                if (!$cid) continue;
                foreach ($sections as [$sname,$shift,$cap]) $secStmt->execute([$cid,$sname,$shift,$cap]);
            }
            $secRows = $pdo->query("SELECT s.id,s.section_name,c.class_name FROM sections s JOIN classes c ON c.id=s.class_id")->fetchAll();
            $sectionMap = [];
            foreach ($secRows as $r) $sectionMap[$r['class_name']][$r['section_name']] = $r['id'];

            // ── 4. Groups & Streams ──────────────────────────────────────────────────
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

            // ── 5. Rooms ─────────────────────────────────────────────────────────────
            $roomDef = [
                ['Room 101','101',1,45,'classroom'],['Room 102','102',1,45,'classroom'],
                ['Room 103','103',1,45,'classroom'],['Room 104','104',1,45,'classroom'],
                ['Room 201','201',2,50,'classroom'],['Room 202','202',2,50,'classroom'],
                ['Room 203','203',2,50,'classroom'],['Room 204','204',2,50,'classroom'],
                ['Science Lab','SL-1',1,32,'lab'],['Computer Lab','CMP-1',2,25,'lab'],
                ['Library','LIB',1,60,'library'],['Principal Office','PRIN',1,5,'office'],
                ['Exam Hall 1','EH-1',3,80,'exam_hall'],
            ];
            $rStmt = $pdo->prepare('INSERT IGNORE INTO rooms (room_name,room_number,floor,capacity,room_type) VALUES (?,?,?,?,?)');
            foreach ($roomDef as $r) $rStmt->execute($r);

            // ── 6. Subjects ──────────────────────────────────────────────────────────
            $subjectDef = [
                ['Bangla 1st Paper',     'BAN1','core',   0,0,0,0,1],
                ['Bangla 2nd Paper',     'BAN2','core',   0,0,0,0,0],
                ['English 1st Paper',    'ENG1','core',   0,0,0,0,1],
                ['English 2nd Paper',    'ENG2','core',   0,0,0,0,0],
                ['Mathematics',          'MATH','core',   0,0,0,0,1],
                ['General Science',      'GSCI','core',   0,0,0,1,1],
                ['Physics',              'PHY', 'core',   1,0,0,1,1],
                ['Chemistry',            'CHE', 'core',   1,0,0,1,1],
                ['Biology',              'BIO', 'core',   1,0,0,1,1],
                ['Accounting',           'ACC', 'core',   1,0,0,0,1],
                ['Finance & Banking',    'FIN', 'core',   1,0,0,0,1],
                ['ICT',                  'ICT', 'core',   0,0,0,1,1],
                ['Islam Studies',        'ISL', 'religious',0,1,0,0,1],
                ['Hindu Studies',        'HIN', 'religious',0,1,0,0,1],
            ];
            $subjStmt = $pdo->prepare('INSERT IGNORE INTO subjects (subject_name,subject_code,subject_type,is_group_subject,is_religious_alt,can_be_4th,has_practical,has_mcq) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($subjectDef as $s) $subjStmt->execute($s);
            $subjMap = $pdo->query("SELECT subject_code,id FROM subjects")->fetchAll(PDO::FETCH_KEY_PAIR);

            // ── 7. Class-Subject Mapping ─────────────────────────────────────────────
            $csStmt = $pdo->prepare('INSERT IGNORE INTO class_subjects (class_id,session_id,subject_id,group_id,full_marks_written,full_marks_mcq,full_marks_practical,pass_marks_written,pass_marks_mcq,pass_marks_practical,classes_per_week) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $primarySubjs  = ['BAN1','ENG1','MATH','ISL'];
            $juniorSubjs   = ['BAN1','BAN2','ENG1','ENG2','MATH','GSCI','ICT','ISL'];
            $seniorCommon  = ['BAN1','BAN2','ENG1','ENG2','MATH','ICT','ISL'];
            $sciSubjs      = ['PHY','CHE','BIO'];
            $comSubjs      = ['ACC','FIN'];
            $sciGrpId      = $groupMap['Science'] ?? null;
            $comGrpId      = $groupMap['Commerce'] ?? null;

            foreach ([$sess2024,$sess2025,$sess2026] as $sessId) {
                foreach (['Class 1','Class 2','Class 3','Class 4','Class 5'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach ($primarySubjs as $sc) {
                        $sid = $subjMap[$sc]??null; if (!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0,4]);
                    }
                }
                foreach (['Class 6','Class 7','Class 8'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach ($juniorSubjs as $sc) {
                        $sid = $subjMap[$sc]??null; if (!$sid) continue;
                        $fm = in_array($sc,['BAN2','ENG2'])?100:($sc==='ICT'?75:100);
                        $csStmt->execute([$cid,$sessId,$sid,null,$fm,0,0,33,0,0,4]);
                    }
                }
                foreach (['Class 9 (SSC-I)','Class 10 (SSC-II)','HSC 1st Year','HSC 2nd Year'] as $cn) {
                    $cid = $classMap[$cn]??null; if (!$cid) continue;
                    foreach ($seniorCommon as $sc) {
                        $sid=$subjMap[$sc]??null; if(!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,null,100,0,0,33,0,0,4]);
                    }
                    foreach ($sciSubjs as $sc) {
                        $sid=$subjMap[$sc]??null; if(!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,$sciGrpId,75,25,25,25,10,10,3]);
                    }
                    foreach ($comSubjs as $sc) {
                        $sid=$subjMap[$sc]??null; if(!$sid) continue;
                        $csStmt->execute([$cid,$sessId,$sid,$comGrpId,100,0,0,33,0,0,3]);
                    }
                }
            }

            // ── 8. Staff profiles ────────────────────────────────────────────────────
            $staffDef = [
                ['principal',   'A. B. M. Mizanur',  'Rahman',     'Principal',             'Administration', 'permanent','fixed',85000, '2015-01-01'],
                ['vp_shahida',  'Shahida',            'Begum',      'Vice Principal',         'Administration','permanent','fixed',72000, '2016-03-15'],
                ['t_physics',   'Md. Shahadat',       'Hossain',    'Assistant Teacher',      'Science',       'permanent','fixed',45000, '2018-01-15'],
                ['t_math',      'Gias Uddin',         'Ahmed',      'Senior Teacher (Math)',  'Science',       'permanent','fixed',48000, '2016-01-01'],
                ['t_bangla',    'Razia',              'Sultana',    'Senior Teacher (Bangla)','Arts & Humanities','permanent','fixed',46000,'2015-09-01'],
                ['t_english',   'Mosharraf',          'Hossain',    'Senior Teacher (English)','Arts & Humanities','permanent','fixed',47000,'2015-03-01'],
                ['accountant',  'Jasmine',            'Akter',      'Accountant',             'Administration','permanent','fixed',35000, '2017-01-01'],
            ];
            $allRoles = $pdo->query("SELECT role_slug,id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
            $teacherRoleId = $allRoles['teacher'] ?? null;
            $staffIds = [];

            foreach ($staffDef as $idx => $sd) {
                [$uname,$fname,$lname,$desig,$dept,$contract,$salary_type,$salary,$joining] = $sd;
                $fullName = "$fname $lname";
                $email = strtolower($fname).'@dmhsc.edu.bd';
                
                $ck = $pdo->prepare('SELECT id FROM users WHERE username=?'); 
                $ck->execute([$uname]); 
                $uid = $ck->fetchColumn();
                if (!$uid) {
                    $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')
                        ->execute([$uname,password_hash('Staff@123',PASSWORD_BCRYPT),$fullName,$email,'active']);
                    $uid = (int)$pdo->lastInsertId();
                }
                
                $empId = 'EMP-2024-' . str_pad($idx+1, 3, '0', STR_PAD_LEFT);
                $isFemale = in_array($fname,['Shahida','Jasmine','Razia']);
                
                $pdo->prepare('INSERT IGNORE INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,department,dob,gender,religion,blood_group,joining_date,contract_type,salary_type,base_salary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid,$empId,$fname,$lname,$desig,$dept,'1985-05-15', $isFemale?'female':'male', 'Islam','O+',$joining,$contract,$salary_type,$salary]);
                
                $roleSlug = ($uname === 'accountant') ? 'accountant' : (($uname === 'principal') ? 'principal' : 'teacher');
                $roleId = $allRoles[$roleSlug] ?? $teacherRoleId;
                if ($roleId) {
                    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid,$roleId]);
                }
                $staffIds[$uname] = $uid;
                
                // Add default expertise mappings for routine generator
                if ($roleSlug === 'teacher') {
                    $sub_code = ($uname === 't_physics') ? 'PHY' : (($uname === 't_math') ? 'MATH' : (($uname === 't_bangla') ? 'BAN1' : 'ENG1'));
                    $sub_id = $subjMap[$sub_code] ?? null;
                    if ($sub_id) {
                        $pdo->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)")->execute([$uid, $sub_id]);
                    }
                }
            }
            $allStaffIds = array_values($staffIds);

            // ── 9. Students ──────────────────────────────────────────────────────────
            $studentRoleId = $allRoles['student'] ?? null;
            $stuInsUser  = $pdo->prepare('INSERT IGNORE INTO users (username,password_hash,full_name,status) VALUES (?,?,?,?)');
            $stuInsProf  = $pdo->prepare('INSERT IGNORE INTO student_profiles (user_id,student_id_no,first_name,last_name,dob,gender,religion,blood_group,father_name,mother_name,guardian_phone,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stuInsEnr   = $pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id,session_id,class_id,section_id,group_id,roll_number,status) VALUES (?,?,?,?,?,?,?)');
            $stuInsRole  = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)');

            $studentIds = [];
            $counter = 1;
            
            // Seed ~3 students per section for fast seeding
            foreach ($sectionDef as $className => $sections) {
                $cid2026 = $classMap[$className] ?? null;
                if (!$cid2026) continue;
                
                foreach ($sections as [$sname,,]) {
                    $secId2026 = $sectionMap[$className][$sname] ?? null;
                    if (!$secId2026) continue;
                    
                    $grpId2026 = in_array($sname,['Science','Commerce']) ? ($groupMap[$sname]??null) : null;
                    $sectionStudents = [];
                    
                    for ($n = 1; $n <= 3; $n++) {
                        $isMale = rand(0,1);
                        $first = $isMale ? $MALE_FIRST[array_rand($MALE_FIRST)] : $FEMALE_FIRST[array_rand($FEMALE_FIRST)];
                        $last  = $LAST_NAMES[array_rand($LAST_NAMES)];
                        
                        $uname = strtolower($first).$counter;
                        $stuId = 'DMHSC-2026-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
                        
                        $stuInsUser->execute([$uname, password_hash('Student@123', PASSWORD_BCRYPT), "$first $last", 'active']);
                        $uid = (int)$pdo->lastInsertId();
                        if (!$uid) {
                            $ck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                            $ck->execute([$uname]);
                            $uid = (int)$ck->fetchColumn();
                        }
                        if (!$uid) continue;
                        
                        $stuInsProf->execute([$uid, $stuId, $first, $last, '2012-08-10', $isMale?'male':'female', 'Islam', 'O+', 'Father Name', 'Mother Name', '01711000000', '2024-01-05']);
                        if ($studentRoleId) $stuInsRole->execute([$uid, $studentRoleId]);
                        
                        // Enroll in 2026
                        $stuInsEnr->execute([$uid, $sess2026, $cid2026, $secId2026, $grpId2026, $n, 'active']);
                        // Enroll in 2025 (completed)
                        $stuInsEnr->execute([$uid, $sess2025, $cid2026, $secId2026, $grpId2026, $n, 'active']);
                        // Enroll in 2024 (completed)
                        $stuInsEnr->execute([$uid, $sess2024, $cid2026, $secId2026, $grpId2026, $n, 'active']);
                        
                        $sectionStudents[] = $uid;
                        $counter++;
                    }
                    $studentIds[$className][$sname] = $sectionStudents;
                }
            }

            // ── 10. LIQUID ACCOUNTS REGISTRY (PHASE 2) ──────────────────────────────
            $pdo->exec("TRUNCATE TABLE accounts");
            $pdo->exec("TRUNCATE TABLE account_transactions");
            
            $insAccount = $pdo->prepare("INSERT INTO accounts (account_name, account_type, account_number, bank_name, current_balance, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $insAccount->execute(['Cash Register', 'cash', null, null, 150000.00, 'Main cash chest inside office']);
            $cash_acc_id = $pdo->lastInsertId();
            
            $insAccount->execute(['Main Bank Account', 'bank', '122-344-555-6', 'Prime Bank Ltd.', 450000.00, 'Primary corporate banking portal']);
            $bank_acc_id = $pdo->lastInsertId();
            
            $insAccount->execute(['bKash Merchant Wallet', 'mobile_banking', '01711223344', 'bKash Merchant Portal', 25000.00, 'Mobile checkout collections']);
            $bkash_acc_id = $pdo->lastInsertId();
            
            // Log initial balances transactions
            $insTx = $pdo->prepare("INSERT INTO account_transactions (account_id, amount, transaction_type, description, created_by) VALUES (?, ?, ?, ?, 1)");
            $insTx->execute([$cash_acc_id, 150000.00, 'adjustment', 'Opening balance initialization']);
            $insTx->execute([$bank_acc_id, 450000.00, 'adjustment', 'Opening balance initialization']);
            $insTx->execute([$bkash_acc_id, 25000.00, 'adjustment', 'Opening balance initialization']);

            // ── 11. Fee Categories & Payments ────────────────────────────────────────
            $pdo->exec("DELETE FROM fee_categories");
            $insFeeCat = $pdo->prepare("INSERT INTO fee_categories (category_name, category_type, status) VALUES (?, ?, 1)");
            $insFeeCat->execute(['Tuition Fee', 'tuition']);
            $tutCatId = $pdo->lastInsertId();
            $insFeeCat->execute(['Exam Fee', 'exam']);
            $examCatId = $pdo->lastInsertId();
            
            // Set fee structures
            $fsStmt = $pdo->prepare('INSERT INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)');
            foreach (['Class 1','Class 5','Class 10 (SSC-II)'] as $cn) {
                $cid = $classMap[$cn] ?? null;
                if ($cid) {
                    $fsStmt->execute([$sess2026, $cid, $tutCatId, 1200, 10, 'monthly']);
                    $fsStmt->execute([$sess2025, $cid, $tutCatId, 1100, 10, 'monthly']);
                    $fsStmt->execute([$sess2024, $cid, $tutCatId, 1000, 10, 'monthly']);
                }
            }

            // Seed fee ledger and payments
            $flStmt = $pdo->prepare('INSERT INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,?)');
            $fpStmt = $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id) VALUES (?,?,?,?,?,?,?,?)');
            
            $rcpN = 1;
            foreach ($studentIds as $cname => $secData) {
                $cid = $classMap[$cname] ?? null;
                if (!$cid) continue;
                
                foreach ($secData as $sname => $stuArr) {
                    foreach ($stuArr as $uid) {
                        // 2024 tuition
                        $flStmt->execute([$uid, $sess2024, $tutCatId, 1000, '2024-03-10', 3, 2024, 'paid']);
                        $ledId = $pdo->lastInsertId();
                        $rcp = 'DMHSC-2024-' . str_pad($rcpN++, 6, '0', STR_PAD_LEFT);
                        $fpStmt->execute([$ledId, $uid, 1000, '2024-03-05', 'cash', $rcp, 1, $cash_acc_id]);
                        
                        // 2025 tuition
                        $flStmt->execute([$uid, $sess2025, $tutCatId, 1100, '2025-05-10', 5, 2025, 'paid']);
                        $ledId = $pdo->lastInsertId();
                        $rcp = 'DMHSC-2025-' . str_pad($rcpN++, 6, '0', STR_PAD_LEFT);
                        $fpStmt->execute([$ledId, $uid, 1100, '2025-05-06', 'bank', $rcp, 1, $bank_acc_id]);

                        // 2026 tuition (current)
                        $flStmt->execute([$uid, $sess2026, $tutCatId, 1200, '2026-06-10', 6, 2026, 'paid']);
                        $ledId = $pdo->lastInsertId();
                        $rcp = 'DMHSC-2026-' . str_pad($rcpN++, 6, '0', STR_PAD_LEFT);
                        $fpStmt->execute([$ledId, $uid, 1200, '2026-06-08', 'mobile_banking', $rcp, 1, $bkash_acc_id]);
                    }
                }
            }
            
            // Adjust liquid accounts balances based on fee payments
            $sumCash = (float)$pdo->query("SELECT SUM(amount) FROM fee_payments WHERE account_id = $cash_acc_id")->fetchColumn();
            $sumBank = (float)$pdo->query("SELECT SUM(amount) FROM fee_payments WHERE account_id = $bank_acc_id")->fetchColumn();
            $sumBkash = (float)$pdo->query("SELECT SUM(amount) FROM fee_payments WHERE account_id = $bkash_acc_id")->fetchColumn();
            
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$sumCash, $cash_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$sumBank, $bank_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$sumBkash, $bkash_acc_id]);
            
            // Log fee transactions
            $insTx->execute([$cash_acc_id, $sumCash, 'deposit', 'Consolidated student fee collections (Cash)']);
            $insTx->execute([$bank_acc_id, $sumBank, 'deposit', 'Consolidated student fee collections (Bank)']);
            $insTx->execute([$bkash_acc_id, $sumBkash, 'deposit', 'Consolidated student fee collections (bKash)']);

            // ── 12. Expenses ─────────────────────────────────────────────────────────
            $pdo->exec("DELETE FROM expense_categories");
            $insExpCat = $pdo->prepare("INSERT INTO expense_categories (category_name, status) VALUES (?, 1)");
            $insExpCat->execute(['Utilities & Bills']);
            $utCatId = $pdo->lastInsertId();
            $insExpCat->execute(['Stationery & Printing']);
            $prCatId = $pdo->lastInsertId();
            
            $insExp = $pdo->prepare("INSERT INTO expenses (session_id, expense_category_id, amount, expense_date, description, vendor, status, approved_by, account_id) VALUES (?, ?, ?, ?, ?, ?, 'approved', 1, ?)");
            
            // 2024 utility
            $insExp->execute([$sess2024, $utCatId, 12500.00, '2024-04-15', 'Electricity monthly bill payment', 'DESCO', $bank_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance - 12500.00 WHERE id = ?")->execute([$bank_acc_id]);
            $insTx->execute([$bank_acc_id, -12500.00, 'withdrawal', 'DESCO electricity bill expense']);

            // 2025 stationery
            $insExp->execute([$sess2025, $prCatId, 8500.00, '2025-09-20', 'Bulk papers and chalk printing box', 'Reza Traders', $cash_acc_id]);
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance - 8500.00 WHERE id = ?")->execute([$cash_acc_id]);
            $insTx->execute([$cash_acc_id, -8500.00, 'withdrawal', 'Reza Traders office stationery supply expense']);

            // ── 13. Non-fee Income ───────────────────────────────────────────────────
            $pdo->exec("DELETE FROM income_categories");
            $insIncCat = $pdo->prepare("INSERT INTO income_categories (category_name, status) VALUES (?, 1)");
            $insIncCat->execute(['Donations & Sponsors']);
            $donCatId = $pdo->lastInsertId();
            
            $insInc = $pdo->prepare("INSERT INTO incomes (session_id, income_category_id, amount, income_date, description, received_by, account_id) VALUES (?, ?, ?, ?, ?, 1, ?)");
            $insInc->execute([$sess2025, $donCatId, 25000.00, '2025-10-12', 'Alumni community donation fund', $bank_acc_id]);
            
            $pdo->prepare("UPDATE accounts SET current_balance = current_balance + 25000.00 WHERE id = ?")->execute([$bank_acc_id]);
            $insTx->execute([$bank_acc_id, 25000.00, 'deposit', 'Alumni community donation fund donation']);

            // ── 14. Routine slots generation ─────────────────────────────────────────
            // Run a quick default routine generation for 2026 to ensure the system is populated
            $periods_config = [
                ['name' => 'Period 1', 'start' => '09:00', 'end' => '09:45', 'is_break' => 0],
                ['name' => 'Period 2', 'start' => '09:45', 'end' => '10:30', 'is_break' => 0],
                ['name' => 'Tiffin', 'start' => '10:30', 'end' => '11:00', 'is_break' => 1],
                ['name' => 'Period 3', 'start' => '11:00', 'end' => '11:45', 'is_break' => 0],
            ];
            $pdo->prepare('INSERT INTO system_settings (meta_key, meta_value, meta_group) VALUES ("routine_periods", ?, "academic")
                           ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)')->execute([json_encode($periods_config)]);
            
            $insSlot = $pdo->prepare("INSERT INTO routine_slots (session_id, class_id, section_id, subject_id, teacher_id, room_id, day_of_week, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            
            $secId = reset($sectionMap)['Flower'] ?? 1;
            $insSlot->execute([$sess2026, $classMap['Playgroup'], $secId, $subjMap['BAN1'], $staffIds['t_bangla'], 1, 'Saturday', '09:00:00', '09:45:00']);
            $insSlot->execute([$sess2026, $classMap['Playgroup'], $secId, $subjMap['ENG1'], $staffIds['t_english'], 1, 'Sunday', '09:00:00', '09:45:00']);
            $insSlot->execute([$sess2026, $classMap['Playgroup'], $secId, $subjMap['MATH'], $staffIds['t_math'], 1, 'Monday', '09:00:00', '09:45:00']);

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $messages[] = '🎉 Seeding successful! Created 3 Academic Sessions (2024, 2025, 2026), default Classes, Sections, Subjects, Staff, Students, Liquid Accounts registries, and Transaction Records.';
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $errors[] = 'Seeding failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EMS Database Manager</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; color: #f8fafc; }
.card { border: none; border-radius: 16px; background: #1e293b; box-shadow: 0 25px 60px rgba(0,0,0,.45); max-width: 650px; width: 100%; }
.header { background: linear-gradient(135deg, #1e3a8a, #0d9488); padding: 2rem; border-radius: 16px 16px 0 0; }
pre { background: #0f172a; color: #38bdf8; border-radius: 10px; padding: 1.25rem; font-size: .8rem; max-height: 300px; overflow-y: auto; }
.btn-primary { background: #2563eb; border: none; }
.btn-primary:hover { background: #1d4ed8; }
.btn-success { background: #10b981; border: none; }
.btn-success:hover { background: #059669; }
.btn-danger { background: #ef4444; border: none; }
.btn-danger:hover { background: #dc2626; }
</style>
</head>
<body>
<div class="card my-5">
  <div class="header text-center">
    <i class="bi bi-database-fill-gear text-white" style="font-size: 3rem;"></i>
    <h3 class="mb-0 fw-bold mt-2">EMS Database Manager</h3>
    <p class="mb-0 text-white-50 small">Backup, Reset, and Seed system tables safely</p>
  </div>
  <div class="p-4">

    <!-- Auth PIN Form -->
    <?php if (!$authenticated): ?>
      <form method="POST" class="py-3">
        <?php if ($pin_error): ?>
          <div class="alert alert-danger py-2 small"><i class="bi bi-shield-slash me-2"></i>Access Denied: Incorrect PIN code.</div>
        <?php endif; ?>
        <div class="mb-3 text-center">
          <label class="form-label text-white-50">Enter Hardcoded PIN to Unlock</label>
          <input type="password" name="pin" class="form-control form-control-lg text-center" maxlength="4" placeholder="••••" style="font-size: 1.5rem; letter-spacing: 0.5rem;" required autofocus>
        </div>
        <button type="submit" name="pin_submit" class="btn btn-primary w-100 py-2.5 fw-bold"><i class="bi bi-unlock me-2"></i>Authenticate</button>
      </form>
    <?php else: ?>
      
      <!-- Operations Panel -->
      <div class="d-flex justify-content-between align-items-center mb-3 text-white-50 border-bottom pb-2">
        <span>Session: Authenticated</span>
        <a href="db_manager.php?logout=1" class="btn btn-link btn-sm text-danger p-0">Lock Manager</a>
      </div>

      <?php if (!empty($messages)): ?>
        <div class="alert alert-success py-2 small">
          <?php foreach ($messages as $m): echo e($m) . '<br>'; endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 small">
          <?php foreach ($errors as $e): echo e($e) . '<br>'; endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- 1. Seed Demo Data -->
      <div class="mb-4 p-3 rounded bg-slate-800 border border-secondary border-opacity-25">
        <h5 class="fw-bold text-success mb-1"><i class="bi bi-database-fill-add me-2"></i>Seed 2024–2026 Academic Data</h5>
        <p class="text-white-50 small mb-3">Fills the database with 3 complete years of realistic school data (students, staff, attendance, marks, fees, liquid cash/bank accounts registers, and transactional history).</p>
        <form method="POST">
          <button type="submit" name="action" value="seed" class="btn btn-success w-100 py-2 fw-bold" onclick="this.innerHTML='⏳ Seeding data, please wait...';">
            <i class="bi bi-magic me-2"></i>Populate 3-Year Seed
          </button>
        </form>
      </div>

      <!-- 2. Export & Import SQL -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="p-3 h-100 rounded bg-slate-800 border border-secondary border-opacity-25 d-flex flex-column">
            <h5 class="fw-bold text-primary mb-1"><i class="bi bi-download me-2"></i>SQL Export</h5>
            <p class="text-white-50 small mb-auto">Downloads a complete native database SQL dump file including all tables schema and data values.</p>
            <form method="POST" class="mt-3">
              <button type="submit" name="action" value="export" class="btn btn-primary w-100 py-2 fw-bold">
                <i class="bi bi-file-earmark-arrow-down me-2"></i>Download SQL
              </button>
            </form>
          </div>
        </div>
        <div class="col-md-6">
          <div class="p-3 h-100 rounded bg-slate-800 border border-secondary border-opacity-25">
            <h5 class="fw-bold text-info mb-1"><i class="bi bi-upload me-2"></i>SQL Import</h5>
            <p class="text-white-50 small mb-2">Upload and execute an SQL schema or backup restoration script directly via PDO.</p>
            <form method="POST" enctype="multipart/form-data" class="mt-2">
              <input type="hidden" name="action" value="import">
              <input type="file" name="sql_file" class="form-control form-control-sm mb-2" accept=".sql" required>
              <button type="submit" class="btn btn-info btn-sm w-100 text-white fw-bold">
                <i class="bi bi-file-earmark-arrow-up me-2"></i>Restore Backup
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- 3. Reset Database -->
      <div class="p-3 rounded bg-slate-800 border border-danger border-opacity-25">
        <h5 class="fw-bold text-danger mb-1"><i class="bi bi-trash3 me-2"></i>Wipe / Reset Database</h5>
        <p class="text-white-50 small mb-3">Deletes all student registrations, staff profiles, transactions, financial logs, and dynamic routine slots. Essential setup configurations, system definitions, and <strong>super_admin account (admin)</strong> are preserved.</p>
        <form method="POST" onsubmit="return confirm('Wipe all transaction tables? Admin account and system settings will be kept.')">
          <button type="submit" name="action" value="reset" class="btn btn-danger w-100 py-2 fw-bold">
            <i class="bi bi-exclamation-triangle me-2"></i>Wipe Operational Data
          </button>
        </form>
      </div>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
