<?php
/**
 * EMS Seed & Reset Tool — Developer Only
 * PIN: 5877
 *
 * Actions:
 *   seed_full    — wipe + minimum config + full 2025 + 2026 demo data
 *   seed_2025    — add 2025 session data (if session doesn't exist)
 *   seed_2026    — add 2026 session data (if session doesn't exist)
 *   seed_min     — minimum config only (roles, categories, no students)
 *   wipe_all     — truncate ALL data, restore admin + base config
 *   wipe_session — DELETE all records for one session (non-destructive to others)
 */
define('EMS_ROOT', __DIR__);

$dbOk = false;
if (file_exists(EMS_ROOT . '/config/db.php') && file_exists(EMS_ROOT . '/config/.installed')) {
    require_once EMS_ROOT . '/config/constants.php';
    require_once EMS_ROOT . '/core/functions.php';
    $dbOk = true;
}

session_name('EMS_SEED_SESS');
session_start();

// PIN auth
if (isset($_POST['pin'])) {
    if ($_POST['pin'] === '5877') $_SESSION['seed_auth'] = true;
    else { $_SESSION['seed_err'] = true; header('Location: seed.php'); exit; }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: seed.php'); exit; }

$auth = $_SESSION['seed_auth'] ?? false;

// ─── Execute action ────────────────────────────────────────────────────────
$messages = []; $errors = [];

if ($auth && $dbOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = db();
    $action = $_POST['action'];

    try {
        if ($action === 'wipe_all') {
            do_wipe_all($pdo);
            do_minimum_seed($pdo);
            $messages[] = '✅ All data wiped. Admin user (admin/Admin@1234) and base config restored.';

        } elseif ($action === 'wipe_session') {
            $sid = (int)($_POST['wipe_session_id'] ?? 0);
            if ($sid) {
                do_wipe_session($pdo, $sid);
                $messages[] = "✅ All data for session ID $sid deleted. Other sessions untouched.";
            }

        } elseif ($action === 'seed_min') {
            do_minimum_seed($pdo);
            $messages[] = '✅ Minimum configuration seeded (roles, categories, default settings, 1 account, 1 session).';

        } elseif ($action === 'seed_2025') {
            $sid = ensure_session($pdo, '2025', '2025-01-01', '2025-12-31', 'completed', 0);
            do_seed_session($pdo, $sid, '2025');
            $messages[] = '✅ Session 2025 fully seeded (students, staff, exams, marks, fees, attendance).';

        } elseif ($action === 'seed_2026') {
            $sid = ensure_session($pdo, '2026', '2026-01-01', '2026-12-31', 'active', 1);
            do_seed_session($pdo, $sid, '2026');
            $messages[] = '✅ Session 2026 seeded (current session, 6-month data Jan–Jun 2026).';

        } elseif ($action === 'seed_full') {
            do_wipe_all($pdo);
            do_minimum_seed($pdo);
            $sid25 = ensure_session($pdo, '2025', '2025-01-01', '2025-12-31', 'completed', 0);
            do_seed_session($pdo, $sid25, '2025');
            $sid26 = ensure_session($pdo, '2026', '2026-01-01', '2026-12-31', 'active', 1);
            do_seed_session($pdo, $sid26, '2026');
            $messages[] = '✅ Full demo data ready! Login: <strong>admin / Admin@1234</strong> at <a href="index.php">index.php</a>';
        }
    } catch (Exception $e) {
        $errors[] = '❌ ' . $e->getMessage();
    }
}

// ─── DB stats ─────────────────────────────────────────────────────────────
$stats = [];
if ($auth && $dbOk) {
    $pdo = db();
    $stats['sessions']  = (int)$pdo->query('SELECT COUNT(*) FROM academic_sessions')->fetchColumn();
    $stats['students']  = (int)$pdo->query('SELECT COUNT(*) FROM student_profiles')->fetchColumn();
    $stats['staff']     = (int)$pdo->query('SELECT COUNT(*) FROM staff_profiles')->fetchColumn();
    $stats['exams']     = (int)$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn();
    $stats['payments']  = (int)$pdo->query('SELECT COUNT(*) FROM fee_payments')->fetchColumn();
    $stats['marks']     = (int)$pdo->query('SELECT COUNT(*) FROM marks_entry')->fetchColumn();
    $sessRows           = $pdo->query('SELECT id, session_name, status, is_current FROM academic_sessions ORDER BY id')->fetchAll();
}

// ════════════════════════════════════════════════════════════════════════════
//  DATA FUNCTIONS
// ════════════════════════════════════════════════════════════════════════════

function do_wipe_all(PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) $pdo->exec("TRUNCATE TABLE `$t`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function do_wipe_session(PDO $pdo, int $sid): void {
    // Delete in FK-safe reverse order, scoped to this session
    $tables = [
        'payment_void_logs'    => null,
        'marks_entry'          => 'exam_id IN (SELECT id FROM exams WHERE session_id=?)',
        'student_attendance'   => 'session_id=?',
        'fee_payments'         => 'ledger_id IN (SELECT id FROM fee_ledgers WHERE session_id=?)',
        'waivers'              => 'ledger_id IN (SELECT id FROM fee_ledgers WHERE session_id=?)',
        'fee_ledgers'          => 'session_id=?',
        'fee_structures'       => 'session_id=?',
        'exam_invigilators'    => 'exam_id IN (SELECT id FROM exams WHERE session_id=?)',
        'exam_seats'           => 'exam_id IN (SELECT id FROM exams WHERE session_id=?)',
        'exam_subject_config'  => 'exam_id IN (SELECT id FROM exams WHERE session_id=?)',
        'exam_class_map'       => 'exam_id IN (SELECT id FROM exams WHERE session_id=?)',
        'exams'                => 'session_id=?',
        'student_enrollments'  => 'session_id=?',
        'payroll_lines'        => 'payroll_run_id IN (SELECT id FROM payroll_runs WHERE session_id=?)',
        'payroll_runs'         => 'session_id=?',
        'routine_slots'        => 'session_id=?',
        'section_working_days' => 'session_id=?',
        'class_subjects'       => 'session_id=?',
        'incomes'              => 'session_id=?',
        'expenses'             => 'session_id=?',
        'events'               => 'session_id=?',
        'session_clone_logs'   => 'from_session_id=? OR to_session_id=?',
    ];
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $tbl => $where) {
        if (!$where) continue;
        $params = []; $cnt = substr_count($where, '?');
        for ($i=0;$i<$cnt;$i++) $params[] = $sid;
        $pdo->prepare("DELETE FROM `$tbl` WHERE $where")->execute($params);
    }
    $pdo->prepare('DELETE FROM academic_sessions WHERE id=?')->execute([$sid]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function do_minimum_seed(PDO $pdo): void {
    // ── Roles ────────────────────────────────────────────────────────────
    $roles = [
        ['Super Admin','super_admin','Full system access'],
        ['Principal','principal','Head of institution'],
        ['Vice Principal','vice_principal','Academic oversight'],
        ['Accountant','accountant','Fee collection and payroll'],
        ['Teacher','teacher','Academic staff'],
        ['Data Entry Operator','data_operator','Student data entry'],
        ['Student','student','Student portal'],
        ['Guardian','guardian','Parent portal'],
        ['Librarian','librarian','Library management'],
        ['Store Manager','store_manager','Inventory management'],
        ['HR Manager','hr_manager','Staff HR and payroll'],
        ['Exam Controller','exam_controller','Exam logistics'],
    ];
    $rs = $pdo->prepare('INSERT IGNORE INTO roles (role_name,role_slug,description) VALUES (?,?,?)');
    foreach ($roles as $r) $rs->execute($r);

    // ── Permissions ──────────────────────────────────────────────────────
    $perms = [
        ['setup.view','setup','View Setup',''],['setup.edit','setup','Edit Setup',''],
        ['users.view','users','View Users',''],['users.create','users','Create Users',''],
        ['users.edit','users','Edit Users',''],['users.delete','users','Delete Users',''],
        ['roles.manage','users','Manage Roles',''],
        ['academic.view','academic','View Academic',''],['academic.manage','academic','Manage Academic',''],
        ['routine.view','academic','View Routine',''],['routine.manage','academic','Manage Routine',''],
        ['students.view','students','View Students',''],['students.create','students','Admit Students',''],
        ['students.edit','students','Edit Students',''],['students.promote','students','Promote Students',''],
        ['students.tc','students','Issue TC',''],
        ['exams.view','exams','View Exams',''],['exams.manage','exams','Manage Exams',''],
        ['marks.enter','exams','Enter Marks',''],['marks.approve','exams','Approve Marks',''],
        ['seats.manage','exams','Manage Seat Plans',''],
        ['finance.view','finance','View Finance',''],['fees.collect','finance','Collect Fees',''],
        ['waivers.request','finance','Request Waiver',''],['waivers.approve','finance','Approve Waiver',''],
        ['expenses.manage','finance','Manage Expenses',''],
        ['payroll.view','finance','View Payroll',''],['payroll.manage','finance','Manage Payroll',''],
        ['hr.view','hr','View HR',''],['hr.manage','hr','Manage HR',''],
        ['leave.approve','hr','Approve Leave',''],['attendance.mark','hr','Mark Attendance',''],
        ['inventory.view','inventory','View Inventory',''],['inventory.manage','inventory','Manage Inventory',''],
        ['eca.view','eca','View ECA',''],['eca.manage','eca','Manage ECA',''],
        ['sms.send','communication','Send SMS',''],['documents.issue','communication','Issue Documents',''],
        ['incidents.manage','communication','Manage Incidents',''],['reports.view','reports','View Reports',''],
    ];
    $ps = $pdo->prepare('INSERT IGNORE INTO permissions (permission_key,module,label,description) VALUES (?,?,?,?)');
    foreach ($perms as $p) $ps->execute($p);

    // Grant super_admin all permissions
    $saId = $pdo->query("SELECT id FROM roles WHERE role_slug='super_admin'")->fetchColumn();
    $allPids = $pdo->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
    $rp = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id) VALUES (?,?)');
    foreach ($allPids as $pid) $rp->execute([$saId, $pid]);

    // Grant teacher common permissions
    $teacherPerms = ['academic.view','routine.view','students.view','exams.view','marks.enter','attendance.mark','reports.view'];
    $tRoleId = $pdo->query("SELECT id FROM roles WHERE role_slug='teacher'")->fetchColumn();
    foreach ($teacherPerms as $pk) {
        $pid = $pdo->prepare('SELECT id FROM permissions WHERE permission_key=?');
        $pid->execute([$pk]);
        $pidVal = $pid->fetchColumn();
        if ($pidVal) $rp->execute([$tRoleId, $pidVal]);
    }

    // Grant accountant finance permissions
    $accountantPerms = ['finance.view','fees.collect','waivers.request','waivers.approve','expenses.manage','payroll.view','reports.view','students.view'];
    $aRoleId = $pdo->query("SELECT id FROM roles WHERE role_slug='accountant'")->fetchColumn();
    foreach ($accountantPerms as $pk) {
        $pid2 = $pdo->prepare('SELECT id FROM permissions WHERE permission_key=?');
        $pid2->execute([$pk]);
        $pv = $pid2->fetchColumn();
        if ($pv) $rp->execute([$aRoleId, $pv]);
    }

    // ── Admin user ───────────────────────────────────────────────────────
    $exists = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
    if (!$exists) {
        $hash = password_hash('Admin@1234', PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')
            ->execute(['admin', $hash, 'System Administrator', 'admin@school.edu.bd', 'active']);
        $adminId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$adminId, $saId]);
    }

    // ── System Settings ──────────────────────────────────────────────────
    $settings = [
        ['school_name','Dhaka Model Academy','general'],
        ['school_address','Farmgate, Dhaka-1215','general'],
        ['school_phone','01700-000000','general'],
        ['school_email','info@dhakamodel.edu.bd','general'],
        ['school_logo','','general'],
        ['school_type','school','general'],
        ['academic_board','DEB','academic'],
        ['current_session_id','0','academic'],
        ['currency_symbol','৳','finance'],
        ['receipt_prefix','RCP','finance'],
        ['date_format','d M Y','general'],
        ['timezone','Asia/Dhaka','general'],
        ['sms_gateway','greenweb','communication'],
        ['sms_api_key','','communication'],
        ['sms_sender_id','','communication'],
        ['working_days','Sat,Sun,Mon,Tue,Wed','academic'],
        ['per_page','25','general'],
        ['system_version','1.0.0','general'],
        ['allow_partial_payment','no','finance'],
        ['partial_requires_approval','yes','finance'],
        ['partial_min_percent','100','finance'],
        ['admit_card_dues_allow','1','finance'],
        ['result_card_dues_allow','1','finance'],
        ['certificate_dues_allow','1','finance'],
    ];
    $ss = $pdo->prepare('INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)');
    foreach ($settings as $s) $ss->execute($s);

    // ── Default categories ────────────────────────────────────────────────
    $feeCats = [
        ['Tuition Fee','tuition','Monthly tuition'],['Admission Fee','admission','One-time admission'],
        ['Session Charge','session','Annual session fee'],['Exam Fee','exam','Per-exam fee'],
        ['Library Fee','library','Annual library fee'],['Transport Fee','transport','Monthly transport'],
        ['Late Fine','fine','Late payment fine'],
    ];
    $fc = $pdo->prepare('INSERT IGNORE INTO fee_categories (category_name,category_type,description) VALUES (?,?,?)');
    foreach ($feeCats as $f) $fc->execute($f);

    foreach (['Salary','Utilities','Maintenance','Stationery','Furniture','Equipment','Event Expenses','Miscellaneous'] as $ec)
        $pdo->prepare('INSERT IGNORE INTO expense_categories (category_name) VALUES (?)')->execute([$ec]);

    foreach (['Donation','Venue Rental','Canteen Revenue','Miscellaneous Income'] as $ic)
        $pdo->prepare('INSERT IGNORE INTO income_categories (category_name) VALUES (?)')->execute([$ic]);

    foreach (['Electronics','Furniture','Lab Equipment','Sports Equipment','Musical Instruments','Vehicles'] as $ac)
        $pdo->prepare('INSERT IGNORE INTO asset_categories (category_name) VALUES (?)')->execute([$ac]);

    foreach (['Books','Notebooks (Khata)','Stationery','Uniforms','Cleaning Supplies','Lab Consumables'] as $cc)
        $pdo->prepare('INSERT IGNORE INTO consumable_categories (category_name) VALUES (?)')->execute([$cc]);

    foreach ([['Casual Leave',10,1,'all'],['Sick Leave',14,1,'all'],['Maternity Leave',90,1,'all'],['Earned Leave',15,1,'all']] as $lt)
        $pdo->prepare('INSERT IGNORE INTO leave_types (leave_name,annual_quota,is_paid,applicable_to) VALUES (?,?,?,?)')->execute($lt);

    foreach ([['Science','SCI'],['Commerce','COM'],['Arts','ART'],['Business Studies','BUS']] as $g)
        $pdo->prepare('INSERT IGNORE INTO groups_stream (group_name,group_code) VALUES (?,?)')->execute($g);

    // Default account
    $pdo->prepare('INSERT IGNORE INTO accounts (account_name,account_type,current_balance,notes) VALUES (?,?,?,?)')->execute(['Main Cash','cash',0.00,'Primary cash register']);

    // A default session
    $hasSess = $pdo->query('SELECT COUNT(*) FROM academic_sessions')->fetchColumn();
    if (!$hasSess) {
        $pdo->prepare('INSERT INTO academic_sessions (session_name,start_date,end_date,is_current,status) VALUES (?,?,?,1,"active")')
            ->execute(['2026','2026-01-01','2026-12-31']);
        $newSid = $pdo->lastInsertId();
        $pdo->prepare("UPDATE system_settings SET meta_value=? WHERE meta_key='current_session_id'")->execute([$newSid]);
    }
}

function ensure_session(PDO $pdo, string $name, string $start, string $end, string $status, int $isCurrent): int {
    $existing = $pdo->prepare('SELECT id FROM academic_sessions WHERE session_name=?');
    $existing->execute([$name]);
    $id = $existing->fetchColumn();
    if ($id) return (int)$id;
    if ($isCurrent) $pdo->exec("UPDATE academic_sessions SET is_current=0");
    $pdo->prepare('INSERT INTO academic_sessions (session_name,start_date,end_date,status,is_current) VALUES (?,?,?,?,?)')
        ->execute([$name, $start, $end, $status, $isCurrent]);
    $sid = (int)$pdo->lastInsertId();
    if ($isCurrent)
        $pdo->prepare("INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ('current_session_id',?,'academic') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")->execute([$sid]);
    return $sid;
}

function do_seed_session(PDO $pdo, int $session_id, string $year): void {
    mt_srand(crc32("ems_seed_$year"));

    // ── Classes & Sections ───────────────────────────────────────────────
    $classConfig = [
        ['Play Group',0,'playgroup',['A']],
        ['Nursery',0,'playgroup',['A']],
        ['KG',0,'primary',['A']],
        ['Class 1',1,'primary',['A','B']],
        ['Class 2',2,'primary',['A','B']],
        ['Class 3',3,'primary',['A','B']],
        ['Class 4',4,'primary',['A','B']],
        ['Class 5',5,'primary',['A','B']],
        ['Class 6',6,'secondary',['A','B']],
        ['Class 7',7,'secondary',['A','B']],
        ['Class 8',8,'secondary',['A','B']],
    ];

    $classIds = []; $sectionIds = [];
    foreach ($classConfig as $ord => [$cname, $cnum, $clvl, $sects]) {
        $ex = $pdo->prepare('SELECT id FROM classes WHERE class_name=?'); $ex->execute([$cname]);
        $cid = (int)($ex->fetchColumn() ?: 0);
        if (!$cid) {
            $pdo->prepare('INSERT INTO classes (class_name,class_numeric,class_level,display_order,status) VALUES (?,?,?,?,1)')
                ->execute([$cname,$cnum,$clvl,$ord+1]);
            $cid = (int)$pdo->lastInsertId();
        }
        $classIds[$cname] = $cid;
        $sectionIds[$cid] = [];
        foreach ($sects as $sn) {
            $ex2 = $pdo->prepare('SELECT id FROM sections WHERE class_id=? AND section_name=?'); $ex2->execute([$cid,$sn]);
            $secId = (int)($ex2->fetchColumn() ?: 0);
            if (!$secId) {
                $pdo->prepare('INSERT INTO sections (class_id,section_name,shift,capacity,status) VALUES (?,?,?,?,1)')
                    ->execute([$cid,$sn,'morning',40]);
                $secId = (int)$pdo->lastInsertId();
            }
            $sectionIds[$cid][] = $secId;
        }
    }

    // ── Subjects ─────────────────────────────────────────────────────────
    $subjectConfig = [
        ['Bangla','BAN-01','core'],['English','ENG-01','core'],
        ['Mathematics','MAT-01','core'],['General Science','SCI-01','core'],
        ['Social Studies','SST-01','core'],['Islam & Moral Education','IME-01','religious'],
        ['ICT','ICT-01','core'],['Physical Education','PHE-01','core'],
    ];
    $subjectIds = [];
    foreach ($subjectConfig as [$sname,$scode,$stype]) {
        $ex = $pdo->prepare('SELECT id FROM subjects WHERE subject_code=?'); $ex->execute([$scode]);
        $sid = (int)($ex->fetchColumn() ?: 0);
        if (!$sid) {
            $pdo->prepare('INSERT INTO subjects (subject_name,subject_code,subject_type,status) VALUES (?,?,?,1)')->execute([$sname,$scode,$stype]);
            $sid = (int)$pdo->lastInsertId();
        }
        $subjectIds[$sname] = $sid;
    }

    // ── Class-Subject Assignments ─────────────────────────────────────────
    $subjForClass = [
        'Play Group' => ['Bangla','English','Mathematics'],
        'Nursery'    => ['Bangla','English','Mathematics'],
        'KG'         => ['Bangla','English','Mathematics'],
    ];
    foreach ($classConfig as [$cname,,,$sects]) {
        $cid = $classIds[$cname];
        $forThis = $subjForClass[$cname] ?? array_keys($subjectIds);
        foreach ($forThis as $sn) {
            $sid = $subjectIds[$sn] ?? null; if (!$sid) continue;
            $fm = in_array($sn, ['Mathematics','General Science','ICT']) ? 100 : 100;
            $pm = 33;
            $pdo->prepare('INSERT IGNORE INTO class_subjects (class_id,session_id,subject_id,full_marks_written,pass_marks_written,periods_per_week) VALUES (?,?,?,?,?,?)')
                ->execute([$cid,$session_id,$sid,$fm,$pm,5]);
        }
    }

    // ── Staff (Teachers + Admin) ─────────────────────────────────────────
    $staffConfig = [
        ['Abdul','Karim','male','Senior Teacher','Bangla','t.karim','teacher',['Bangla']],
        ['Nasrin','Akter','female','Senior Teacher','English','t.akter','teacher',['English']],
        ['Shahidul','Islam','male','Senior Teacher','Mathematics','t.shahid','teacher',['Mathematics']],
        ['Fatema','Begum','female','Teacher','Science','t.fatema','teacher',['General Science']],
        ['Rafiqul','Islam','male','Teacher','Social Studies','t.rafiq','teacher',['Social Studies']],
        ['Abdul','Aziz','male','Teacher','Religion','t.aziz','teacher',['Islam & Moral Education']],
        ['Shahin','Alam','male','Teacher','ICT','t.shahin','teacher',['ICT']],
        ['Ruma','Akter','female','Teacher','PE','t.ruma','teacher',['Physical Education']],
        ['Jamal','Uddin','male','Assistant Teacher','Mathematics','t.jamal','teacher',['Mathematics','General Science']],
        ['Salma','Khatun','female','Assistant Teacher','English','t.salma','teacher',['English','Bangla']],
        ['Mizanur','Rahman','male','Accountant','Finance','acc.mizan','accountant',[]],
        ['Sadia','Islam','female','Data Entry Operator','Admin','de.sadia','data_operator',[]],
        ['Delwar','Hossain','male','HR Manager','HR','hr.delwar','hr_manager',[]],
    ];

    $staffIds = []; $teacherSubjectMap = [];
    foreach ($staffConfig as [$fn,$ln,$gender,$desig,$dept,$uname,$roleSlug,$subjs]) {
        // User account
        $ex = $pdo->prepare('SELECT id FROM users WHERE username=?'); $ex->execute([$uname]);
        $uid = (int)($ex->fetchColumn() ?: 0);
        if (!$uid) {
            $hash = password_hash('Staff@1234', PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')
                ->execute([$uname, $hash, "$fn $ln", "$uname@school.edu.bd", 'active']);
            $uid = (int)$pdo->lastInsertId();
        }
        // Role
        $roleId = $pdo->prepare('SELECT id FROM roles WHERE role_slug=?');
        $roleId->execute([$roleSlug]);
        $rid = $roleId->fetchColumn();
        if ($rid) $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid,$rid]);

        // Staff profile
        $ex2 = $pdo->prepare('SELECT id FROM staff_profiles WHERE user_id=?'); $ex2->execute([$uid]);
        if (!$ex2->fetchColumn()) {
            $eid = 'EMP-' . str_pad(count($staffIds)+1,3,'0',STR_PAD_LEFT);
            $pdo->prepare('INSERT INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,department,gender,joining_date,base_salary,status) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$uid,$eid,$fn,$ln,$desig,$dept,$gender,'2023-01-01', ($roleSlug==='teacher'?25000:30000),'active']);
        }
        $staffIds[$uname] = $uid;
        if ($subjs) {
            $teacherSubjectMap[$uid] = $subjs;
            foreach ($subjs as $sn) {
                $sid2 = $subjectIds[$sn] ?? null;
                if ($sid2) $pdo->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id,subject_id) VALUES (?,?)')->execute([$uid,$sid2]);
            }
        }
    }

    // ── Students ─────────────────────────────────────────────────────────
    $maleFirst  = ['Md. Rahim','Md. Karim','Md. Ahmed','Md. Hassan','Md. Sohel','Md. Akash','Md. Rajib','Md. Farhan','Md. Sabbir','Md. Rasel','Md. Tanvir','Md. Imran','Md. Sakib','Md. Naim','Md. Arif'];
    $femFirst   = ['Fatema','Ayesha','Sadia','Nadia','Mitu','Ritu','Nasrin','Lima','Keya','Tania','Mim','Dola','Mumu','Puja','Shiuli'];
    $surnames   = ['Khan','Ahmed','Islam','Hossain','Rahman','Ali','Mia','Bhuiyan','Chowdhury','Sarkar','Paul','Das','Mondol','Akther'];
    $guardNames = ['Md. Abdul Karim','Md. Rafiqul Islam','Md. Shafiqul Hossain','Md. Jamal Uddin','Md. Mizanur Rahman'];
    $religions  = ['Islam','Hinduism','Buddhism','Christianity'];
    $bloods     = ['A+','B+','O+','AB+','A-','B-'];

    $nameIdx = 0; $allStudentIds = [];
    foreach ($classConfig as [$cname,,,$sects]) {
        $cid = $classIds[$cname];
        $studentsPerSection = (str_contains($cname,'Play')||str_contains($cname,'Nursery')||str_contains($cname,'KG')) ? 8 : 12;
        foreach ($sectionIds[$cid] as $secId) {
            for ($r = 1; $r <= $studentsPerSection; $r++) {
                $isMale  = ($r % 2 === 1);
                $fname   = $isMale ? $maleFirst[$nameIdx % count($maleFirst)] : $femFirst[$nameIdx % count($femFirst)];
                $lname   = $surnames[$nameIdx % count($surnames)];
                $gender  = $isMale ? 'male' : 'female';
                $nameIdx++;

                $uname   = 'stu.' . $year . '.' . str_pad($nameIdx, 4, '0', STR_PAD_LEFT);
                $ex = $pdo->prepare('SELECT id FROM users WHERE username=?'); $ex->execute([$uname]);
                $uid = (int)($ex->fetchColumn() ?: 0);
                if (!$uid) {
                    $hash = password_hash('Student@1234', PASSWORD_BCRYPT);
                    $pdo->prepare('INSERT INTO users (username,password_hash,full_name,status) VALUES (?,?,?,?)')->execute([$uname,$hash,"$fname $lname",'active']);
                    $uid = (int)$pdo->lastInsertId();
                }

                $sid_str = 'STU-' . $year . '-' . str_pad($nameIdx, 5, '0', STR_PAD_LEFT);
                $ex2 = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id=?'); $ex2->execute([$uid]);
                if (!$ex2->fetchColumn()) {
                    $dobY = (int)$year - (10 + ($r % 5));
                    $dob  = "$dobY-0" . mt_rand(1,9) . "-" . str_pad(mt_rand(1,28),2,'0',STR_PAD_LEFT);
                    $gName= $guardNames[$nameIdx % count($guardNames)];
                    $pdo->prepare(
                        'INSERT INTO student_profiles (user_id,student_id_no,first_name,last_name,dob,gender,religion,blood_group,father_name,guardian_name,guardian_phone,guardian_relation,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$uid,$sid_str,$fname,$lname,$dob,$gender,$religions[$nameIdx%4],$bloods[$nameIdx%6],"Md. {$lname}",$gName,"017" . str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT),'Father',"$year-01-" . str_pad(mt_rand(1,20),2,'0',STR_PAD_LEFT)]);
                }

                // Enrollment
                $pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id,session_id,class_id,section_id,roll_number,status) VALUES (?,?,?,?,?,"active")')->execute([$uid,$session_id,$cid,$secId,$r]);

                // Role assignment
                $stuRoleId = $pdo->query("SELECT id FROM roles WHERE role_slug='student'")->fetchColumn();
                if ($stuRoleId) $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid,$stuRoleId]);

                $allStudentIds[$cid][$secId][] = $uid;
            }
        }
    }

    // ── Fee Structures & Ledgers ─────────────────────────────────────────
    $tuitionAmt = ['Play Group'=>300,'Nursery'=>300,'KG'=>350,'Class 1'=>400,'Class 2'=>400,'Class 3'=>400,'Class 4'=>500,'Class 5'=>500,'Class 6'=>700,'Class 7'=>700,'Class 8'=>700];
    $tuitionCatId  = (int)$pdo->query("SELECT id FROM fee_categories WHERE category_type='tuition' LIMIT 1")->fetchColumn();
    $sessionCatId  = (int)$pdo->query("SELECT id FROM fee_categories WHERE category_type='session' LIMIT 1")->fetchColumn();
    $examCatId     = (int)$pdo->query("SELECT id FROM fee_categories WHERE category_type='exam' LIMIT 1")->fetchColumn();

    foreach ($classConfig as [$cname,,, ]) {
        $cid = $classIds[$cname];
        $amt = $tuitionAmt[$cname] ?? 500;
        $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')->execute([$session_id,$cid,$tuitionCatId,$amt,10,'monthly']);
        $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')->execute([$session_id,$cid,$sessionCatId,1500,15,'once']);
        $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')->execute([$session_id,$cid,$examCatId,250,10,'once']);
    }

    // Generate monthly tuition + session charge ledgers
    $maxMonth = ($year === '2025') ? 12 : 6; // 2025=full year, 2026=Jan-Jun
    $accId = (int)$pdo->query("SELECT id FROM accounts LIMIT 1")->fetchColumn();

    $ledStmt = $pdo->prepare('INSERT IGNORE INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,"unpaid")');
    $payStmt = $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,?,?,?,1,?,"approved")');
    $updLed  = $pdo->prepare('UPDATE fee_ledgers SET amount_paid=amount_due,status="paid" WHERE id=?');
    $updAcc  = $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?');

    $rcpNum = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_number,'-',-1) AS UNSIGNED)),1000) FROM fee_payments WHERE receipt_number LIKE 'RCP-%'")->fetchColumn();

    $payRate = ($year === '2025') ? 0.90 : 0.55;

    // Session charge (once per student)
    foreach ($classConfig as [$cname,,,$sects]) {
        $cid = $classIds[$cname];
        foreach ($sectionIds[$cid] as $secId) {
            foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                $dueDate = "$year-01-15";
                $ledStmt->execute([$uid,$session_id,$sessionCatId,1500,$dueDate,null,null]);
                $lid = (int)$pdo->lastInsertId(); if (!$lid) continue;
                if (mt_rand(1,100) <= ($payRate * 100)) {
                    $rcpNum++;
                    $payStmt->execute([$lid,$uid,1500,"$year-01-20",'cash',"RCP-$year-$rcpNum",$accId]);
                    $updLed->execute([$lid]);
                    $updAcc->execute([1500,$accId]);
                }
            }
        }
    }

    // Monthly tuition
    for ($m = 1; $m <= $maxMonth; $m++) {
        $dueDate = "$year-" . str_pad($m,2,'0',STR_PAD_LEFT) . "-10";
        $payDay  = "$year-" . str_pad($m,2,'0',STR_PAD_LEFT) . "-" . str_pad(mt_rand(10,25),2,'0',STR_PAD_LEFT);
        foreach ($classConfig as [$cname,,,$sects]) {
            $cid = $classIds[$cname];
            $amt = $tuitionAmt[$cname] ?? 500;
            foreach ($sectionIds[$cid] as $secId) {
                foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                    $ledStmt->execute([$uid,$session_id,$tuitionCatId,$amt,$dueDate,$m,$year]);
                    $lid = (int)$pdo->lastInsertId(); if (!$lid) continue;
                    if (mt_rand(1,100) <= ($payRate * 100)) {
                        $rcpNum++;
                        $payStmt->execute([$lid,$uid,$amt,$payDay,'cash',"RCP-$year-$rcpNum",$accId]);
                        $updLed->execute([$lid]);
                        $updAcc->execute([$amt,$accId]);
                    }
                }
            }
        }
    }

    // ── Exams + Marks ─────────────────────────────────────────────────────
    $examConfig = ($year === '2025') ? [
        ['1st Terminal Exam 2025','terminal','2025-03-01','2025-03-15','results_published'],
        ['Half-Yearly Exam 2025','midterm','2025-06-01','2025-06-15','results_published'],
        ['Annual Exam 2025','annual','2025-11-01','2025-11-30','results_published'],
    ] : [
        ['1st Terminal Exam 2026','terminal','2026-03-01','2026-03-15','results_published'],
        ['Half-Yearly Exam 2026','midterm','2026-06-01','2026-06-15','scheduled'],
    ];

    foreach ($examConfig as [$ename,$etype,$estart,$eend,$estatus]) {
        $pdo->prepare('INSERT INTO exams (session_id,exam_name,exam_type,start_date,end_date,status) VALUES (?,?,?,?,?,?)')->execute([$session_id,$ename,$etype,$estart,$eend,$estatus]);
        $examId = (int)$pdo->lastInsertId();

        foreach ($classConfig as [$cname,,,$sects]) {
            $cid = $classIds[$cname];
            // Map class to exam
            $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)')->execute([$examId,$cid]);

            // Subject config for this exam
            $subjList = ($subjForClass[$cname] ?? array_keys($subjectIds));
            foreach ($subjList as $sn) {
                $sid2 = $subjectIds[$sn] ?? null; if (!$sid2) continue;
                $pdo->prepare('INSERT INTO exam_subject_config (exam_id,class_id,subject_id,full_marks_written,pass_marks_written) VALUES (?,?,?,?,?)')->execute([$examId,$cid,$sid2,100,33]);
            }

            // Marks for published exams only
            if ($estatus !== 'results_published') continue;

            foreach ($sectionIds[$cid] as $secId) {
                foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                    foreach ($subjList as $sn) {
                        $sid2 = $subjectIds[$sn] ?? null; if (!$sid2) continue;
                        // Realistic mark distribution
                        $isAbsent = (mt_rand(1,100) <= 3); // 3% absent
                        $rawMark = $isAbsent ? 0 : mt_rand(33,95);
                        // Some students do very well, some struggle
                        if (mt_rand(1,10) === 1) $rawMark = mt_rand(85,100); // toppers
                        if (mt_rand(1,8) === 1)  $rawMark = mt_rand(25,40);  // struggling
                        $pdo->prepare('INSERT IGNORE INTO marks_entry (exam_id,student_id,class_id,section_id,subject_id,marks_written,is_absent,entered_by) VALUES (?,?,?,?,?,?,?,1)')
                            ->execute([$examId,$uid,$cid,$secId,$sid2,$isAbsent?null:$rawMark,$isAbsent?1:0]);
                    }
                }
            }
        }
    }

    // ── Attendance (last 60 school days) ─────────────────────────────────
    $weekends  = ['Friday','Saturday'];
    $attDate   = new DateTime(($year === '2026') ? '2026-04-01' : '2025-04-01');
    $attEnd    = new DateTime(($year === '2026') ? '2026-06-25' : '2025-11-30');
    $attStmt   = $pdo->prepare('INSERT IGNORE INTO student_attendance (student_id,session_id,class_id,section_id,attendance_date,status,marked_by) VALUES (?,?,?,?,?,?,1)');
    $schoolDays = 0;
    while ($attDate <= $attEnd && $schoolDays < 50) {
        $dow = $attDate->format('l');
        if (!in_array($dow, $weekends)) {
            $ds = $attDate->format('Y-m-d');
            foreach ($classConfig as [$cname,,,$sects]) {
                $cid = $classIds[$cname];
                foreach ($sectionIds[$cid] as $secId) {
                    foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                        $roll = mt_rand(1,100);
                        $stat = $roll <= 85 ? 'present' : ($roll <= 92 ? 'late' : 'absent');
                        $attStmt->execute([$uid,$session_id,$cid,$secId,$ds,$stat]);
                    }
                }
            }
            $schoolDays++;
        }
        $attDate->modify('+1 day');
    }

    // ── Holiday Calendar ──────────────────────────────────────────────────
    $holidays = [
        ["$year-02-21","Language Martyrs Day","govt"],
        ["$year-03-17","Birth of Sheikh Mujibur Rahman","govt"],
        ["$year-03-26","Independence Day","govt"],
        ["$year-04-14","Bengali New Year (Pahela Baishakh)","govt"],
        ["$year-05-01","International Labour Day","govt"],
        ["$year-08-15","National Mourning Day","govt"],
        ["$year-12-16","Victory Day","govt"],
        ["$year-01-01","New Year's Day","institutional"],
    ];
    $hStmt = $pdo->prepare('INSERT IGNORE INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)');
    foreach ($holidays as [$hd,$hn,$ht]) $hStmt->execute([$session_id,$hd,$hn,$ht]);
}

// ════════════════════════════════════════════════════════════════════════════
//  HTML OUTPUT
// ════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EMS Seed Tool — Developer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{background:#0f172a;color:#e2e8f0;min-height:100vh;font-family:'Segoe UI',sans-serif;}
.seed-card{background:#1e293b;border-radius:12px;border:1px solid #334155;padding:1.5rem;}
.action-btn{width:100%;text-align:left;border-radius:8px;padding:.75rem 1rem;font-weight:600;font-size:.9rem;border:2px solid transparent;transition:.2s;}
.action-btn:hover{transform:translateX(3px);}
.stat-chip{display:inline-flex;align-items:center;gap:.4rem;background:#0f172a;border-radius:6px;padding:.35rem .75rem;font-size:.8rem;font-weight:600;}
.badge-pill{display:inline-block;padding:.25rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700;}
hr.dark{border-color:#334155;}
</style>
</head>
<body>
<div class="container py-5" style="max-width:900px;">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h2 class="mb-0 fw-800 text-white"><i class="bi bi-database-gear me-2 text-warning"></i>EMS Seed Tool</h2>
      <p class="text-slate-400 mb-0 small">Developer utility — PIN protected</p>
    </div>
    <div class="d-flex gap-2">
      <?php if($dbOk): ?>
        <a href="../" class="btn btn-sm btn-outline-light">← App Home</a>
      <?php endif; ?>
      <?php if($auth): ?>
        <a href="?logout" class="btn btn-sm btn-outline-danger">Logout</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if(!$dbOk): ?>
  <!-- DB not configured -->
  <div class="seed-card text-center py-5">
    <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
    <h5 class="mt-3 text-white">Database Not Configured</h5>
    <p class="text-slate-300">Run <a href="install.php" class="text-warning">install.php</a> first to set up the database.</p>
  </div>

  <?php elseif(!$auth): ?>
  <!-- PIN Login -->
  <div class="seed-card" style="max-width:400px;margin:auto;">
    <h5 class="text-white mb-4 text-center"><i class="bi bi-lock-fill me-2 text-warning"></i>Developer Access</h5>
    <?php if($_SESSION['seed_err'] ?? false): unset($_SESSION['seed_err']); ?>
      <div class="alert alert-danger py-2 small">Incorrect PIN. Access denied.</div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label text-slate-300">PIN Code</label>
        <input type="password" name="pin" class="form-control bg-dark text-white border-secondary" placeholder="Enter developer PIN" autofocus>
      </div>
      <button type="submit" class="btn btn-warning w-100 fw-600">Unlock</button>
    </form>
  </div>

  <?php else: ?>
  <!-- Authenticated — Main Dashboard -->

  <!-- Messages -->
  <?php foreach($messages as $msg): ?>
    <div class="alert alert-success alert-dismissible mb-3" style="background:#064e3b;border-color:#10b981;color:#a7f3d0;">
      <i class="bi bi-check-circle-fill me-2"></i><?= $msg ?>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
  <?php foreach($errors as $err): ?>
    <div class="alert alert-danger alert-dismissible mb-3">
      <?= e($err) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>

  <!-- DB Stats -->
  <div class="seed-card mb-4">
    <h6 class="text-slate-300 mb-3"><i class="bi bi-bar-chart-fill me-2 text-info"></i>Current Database State</h6>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php foreach([
        ['sessions','Sessions','calendar3','info'],
        ['students','Students','person-badge-fill','success'],
        ['staff','Staff','person-workspace','warning'],
        ['exams','Exams','clipboard2-check-fill','primary'],
        ['payments','Payments','cash-coin','success'],
        ['marks','Marks','pencil-fill','secondary'],
      ] as [$key,$label,$icon,$color]): ?>
        <div class="stat-chip text-<?= $color ?>">
          <i class="bi bi-<?= $icon ?>"></i>
          <span><?= number_format($stats[$key] ?? 0) ?></span>
          <span class="text-slate-400 fw-400"><?= $label ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if(!empty($sessRows)): ?>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach($sessRows as $sr): ?>
        <span class="badge-pill <?= $sr['is_current'] ? 'bg-success text-white' : 'bg-slate-700' ?>" style="<?= $sr['is_current'] ? '' : 'background:#334155;color:#94a3b8;' ?>">
          <?= e($sr['session_name']) ?>
          <?= $sr['is_current'] ? ' ★ Current' : '' ?>
          — <?= ucfirst($sr['status']) ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="text-slate-400 small mb-0">No sessions found. Run a seed to get started.</p>
    <?php endif; ?>
  </div>

  <div class="row g-3">

    <!-- Left: Seed Actions -->
    <div class="col-md-7">
      <div class="seed-card h-100">
        <h6 class="text-white mb-3"><i class="bi bi-play-fill me-2 text-success"></i>Seed Actions</h6>

        <!-- Full seed -->
        <form method="POST" onsubmit="return confirm('This will WIPE ALL DATA and rebuild from scratch. Continue?')">
          <input type="hidden" name="action" value="seed_full">
          <button type="submit" class="action-btn btn btn-success mb-2">
            <i class="bi bi-stars me-2"></i>Full Demo Seed (2025 + 2026)
            <small class="d-block fw-400 opacity-75 mt-1">Wipe → Min Config → Complete 2-year realistic data</small>
          </button>
        </form>

        <hr class="dark my-3">

        <!-- Individual year seeds -->
        <div class="row g-2">
          <div class="col-6">
            <form method="POST" onsubmit="return confirm('Seed 2025 session data?')">
              <input type="hidden" name="action" value="seed_2025">
              <button type="submit" class="action-btn btn btn-outline-info">
                <i class="bi bi-calendar-check me-2"></i>Seed Session 2025
                <small class="d-block fw-400 opacity-75">Full year, all completed</small>
              </button>
            </form>
          </div>
          <div class="col-6">
            <form method="POST" onsubmit="return confirm('Seed 2026 session data?')">
              <input type="hidden" name="action" value="seed_2026">
              <button type="submit" class="action-btn btn btn-outline-primary">
                <i class="bi bi-calendar-week me-2"></i>Seed Session 2026
                <small class="d-block fw-400 opacity-75">Jan–Jun, current session</small>
              </button>
            </form>
          </div>
        </div>

        <hr class="dark my-3">

        <!-- Minimum seed -->
        <form method="POST" onsubmit="return confirm('Apply minimum configuration seed?')">
          <input type="hidden" name="action" value="seed_min">
          <button type="submit" class="action-btn btn btn-outline-warning mb-2">
            <i class="bi bi-gear-fill me-2"></i>Minimum Seed (No Students/Staff)
            <small class="d-block fw-400 opacity-75">Roles, categories, 1 account, 1 session — ready to use fresh</small>
          </button>
        </form>
      </div>
    </div>

    <!-- Right: Danger Zone -->
    <div class="col-md-5">
      <div class="seed-card h-100" style="border-color:#7f1d1d;">
        <h6 style="color:#fca5a5;" class="mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i>Danger Zone</h6>
        <p class="small" style="color:#94a3b8;">These actions delete data permanently. All historical session data is <strong>always preserved per-session</strong> unless explicitly wiped.</p>

        <!-- Wipe All -->
        <form method="POST" onsubmit="return confirm('⚠️ WIPE ALL DATA?\n\nThis deletes EVERY record from every table and restores only admin user and base config.\n\nType YES to confirm.') && prompt('Type YES to confirm:') === 'YES'">
          <input type="hidden" name="action" value="wipe_all">
          <button type="submit" class="action-btn btn btn-danger mb-3">
            <i class="bi bi-trash3-fill me-2"></i>Wipe ALL Data
            <small class="d-block fw-400 opacity-75">Truncates every table. Re-seeds admin + base config only.</small>
          </button>
        </form>

        <hr class="dark">

        <!-- Wipe specific session -->
        <form method="POST" onsubmit="return confirm('Delete ALL records for the selected session? Other sessions will NOT be affected.')">
          <input type="hidden" name="action" value="wipe_session">
          <div class="mb-2">
            <select name="wipe_session_id" class="form-select form-select-sm bg-dark text-white border-secondary" required>
              <option value="">— Select session to wipe —</option>
              <?php foreach($sessRows as $sr): ?>
                <option value="<?= $sr['id'] ?>"><?= e($sr['session_name']) ?> (<?= ucfirst($sr['status']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="action-btn btn btn-outline-danger">
            <i class="bi bi-calendar-x me-2"></i>Wipe Selected Session
            <small class="d-block fw-400 opacity-75">All enrollments, marks, fees, exams for that session.</small>
          </button>
        </form>
      </div>
    </div>

  </div><!-- /row -->

  <!-- What gets seeded info -->
  <div class="seed-card mt-4">
    <h6 class="text-slate-300 mb-3"><i class="bi bi-info-circle me-2 text-info"></i>What the Full Seed Creates</h6>
    <div class="row g-3 small" style="color:#94a3b8;">
      <div class="col-md-4">
        <strong class="text-white d-block mb-1">Academic Structure</strong>
        11 classes (Play Group → Class 8)<br>
        20 sections (A+B per class)<br>
        8 core subjects<br>
        4 stream groups (Sci/Com/Art/Bus)
      </div>
      <div class="col-md-4">
        <strong class="text-white d-block mb-1">People</strong>
        ~220 students (10-12 per section)<br>
        13 staff (teachers + admin)<br>
        Teacher-subject expertise mapped<br>
        Admin: <code>admin / Admin@1234</code><br>
        Staff: <code>[username] / Staff@1234</code><br>
        Students: <code>[username] / Student@1234</code>
      </div>
      <div class="col-md-4">
        <strong class="text-white d-block mb-1">Session Data (per year)</strong>
        Fee structures + ledgers + payments<br>
        2025: 3 exams (all published, ~90% fees paid)<br>
        2026: 1 published exam, 1 upcoming (~55% fees paid)<br>
        50 days attendance records<br>
        National holiday calendar
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
