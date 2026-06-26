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
// No time limit — BCrypt hashing + bulk inserts can take several minutes
@set_time_limit(0);
@ini_set('max_execution_time', '0');

define('EMS_ROOT', __DIR__);

$dbOk = false;
if (file_exists(EMS_ROOT . '/config/db.php') && file_exists(EMS_ROOT . '/config/.installed')) {
    require_once EMS_ROOT . '/config/constants.php';
    require_once EMS_ROOT . '/core/functions.php';
    $dbOk = true;
}

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    session_name('EMS_SEED_SESS');
    session_start();

    // PIN auth
    if (isset($_POST['pin'])) {
        if ($_POST['pin'] === '5877') $_SESSION['seed_auth'] = true;
        else { $_SESSION['seed_err'] = true; header('Location: seed.php'); exit; }
    }
    if (isset($_GET['logout'])) { session_destroy(); header('Location: seed.php'); exit; }

    $auth = $_SESSION['seed_auth'] ?? false;
} else {
    $auth = true;
    $action = null;
    foreach ($argv as $arg) {
        if (strpos($arg, '--action=') === 0) {
            $action = substr($arg, 9);
        }
    }
}

// ─── Execute action ────────────────────────────────────────────────────────
$messages = []; $errors = [];

if ($dbOk && (($auth && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) || ($isCli && $action))) {
    $pdo = db();
    if (!$isCli) {
        $action = $_POST['action'];
    }

    try {
        if ($action === 'wipe_all') {
            do_wipe_all($pdo);
            do_minimum_seed($pdo);
            $messages[] = '✅ All data wiped. Admin user (admin/Admin@1234) and base config restored.';

        } elseif ($action === 'wipe_session') {
            $sid = (int)($isCli ? ($argv[2] ?? 0) : ($_POST['wipe_session_id'] ?? 0));
            if ($sid) {
                do_wipe_session($pdo, $sid);
                $messages[] = "✅ All data for session ID $sid deleted. Other sessions untouched.";
            }

        } elseif ($action === 'seed_min') {
            do_minimum_seed($pdo);
            $messages[] = '✅ Minimum configuration seeded (roles, categories, default settings, 1 account, 1 session).';

        } elseif ($action === 'seed_2025') {
            $sid = ensure_session($pdo, '2025', '2025-01-01', '2025-12-31', 'completed', 0);
            do_seed_session($pdo, $sid, '2025', ['student_count'=>400,'class8_two_sections'=>false,'teacher_count'=>28,'max_months'=>12,'pay_rate'=>0.90]);
            $enr = $pdo->query("SELECT COUNT(*) FROM student_enrollments WHERE session_id=$sid")->fetchColumn();
            $messages[] = "✅ Session 2025 seeded — <strong>$enr</strong> enrollments (400 students, 28 staff, 3 published exams, full 12-month data).";

        } elseif ($action === 'seed_2026') {
            $sid = ensure_session($pdo, '2026', '2026-01-01', '2026-12-31', 'active', 1);
            do_seed_session($pdo, $sid, '2026', ['student_count'=>500,'class8_two_sections'=>true,'teacher_count'=>30,'max_months'=>6,'pay_rate'=>0.55]);
            $enr = $pdo->query("SELECT COUNT(*) FROM student_enrollments WHERE session_id=$sid")->fetchColumn();
            $messages[] = "✅ Session 2026 seeded — <strong>$enr</strong> enrollments (500 students, 30 staff, Jan–Jun 2026 data).";

        } elseif ($action === 'seed_full') {
            do_wipe_all($pdo);
            do_minimum_seed($pdo);
            $sid25 = ensure_session($pdo, '2025', '2025-01-01', '2025-12-31', 'completed', 0);
            do_seed_session($pdo, $sid25, '2025', ['student_count'=>400,'class8_two_sections'=>false,'teacher_count'=>28,'max_months'=>12,'pay_rate'=>0.90]);
            $sid26 = ensure_session($pdo, '2026', '2026-01-01', '2026-12-31', 'active', 1);
            do_seed_session($pdo, $sid26, '2026', ['student_count'=>500,'class8_two_sections'=>true,'teacher_count'=>30,'max_months'=>6,'pay_rate'=>0.55]);
            $pdo->exec("UPDATE academic_sessions SET is_current=0");
            $pdo->prepare("UPDATE academic_sessions SET is_current=1 WHERE id=?")->execute([$sid26]);
            $pdo->prepare("INSERT INTO system_settings (meta_key,meta_value,meta_group) VALUES ('current_session_id',?,'academic') ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")->execute([$sid26]);
            $s25 = $pdo->query("SELECT COUNT(*) FROM student_enrollments WHERE session_id=$sid25")->fetchColumn();
            $s26 = $pdo->query("SELECT COUNT(*) FROM student_enrollments WHERE session_id=$sid26")->fetchColumn();
            $messages[] = "✅ Full demo data ready! 2025: <strong>$s25</strong> enrollments &nbsp;|&nbsp; 2026: <strong>$s26</strong> enrollments &mdash; Login: <strong>admin / Admin@1234</strong> at <a href=\"index.php\">index.php</a>";
        }

        if ($isCli) {
            foreach ($messages as $msg) echo strip_tags($msg) . PHP_EOL;
            exit(0);
        }
    } catch (Exception $e) {
        $errors[] = '❌ ' . $e->getMessage();
        if ($isCli) {
            foreach ($errors as $err) echo strip_tags($err) . PHP_EOL;
            exit(1);
        }
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

function do_migrate_schema(PDO $pdo): void {
    // Add columns and tables that were added after initial install.
    // Uses IF NOT EXISTS so it's safe to run on any DB version.
    $alters = [
        // class_subjects
        "ALTER TABLE class_subjects ADD COLUMN IF NOT EXISTS periods_per_week INT DEFAULT 1",
        // sections
        "ALTER TABLE sections ADD COLUMN IF NOT EXISTS class_teacher_id INT DEFAULT NULL",
        "ALTER TABLE sections ADD COLUMN IF NOT EXISTS class_teacher_first_period_days VARCHAR(255) DEFAULT NULL",
        // student_enrollments
        "ALTER TABLE student_enrollments ADD COLUMN IF NOT EXISTS join_month INT DEFAULT NULL",
        "ALTER TABLE student_enrollments ADD COLUMN IF NOT EXISTS join_year INT DEFAULT NULL",
        "ALTER TABLE student_enrollments ADD COLUMN IF NOT EXISTS left_date DATE DEFAULT NULL",
        "ALTER TABLE student_enrollments ADD COLUMN IF NOT EXISTS left_reason VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE student_enrollments ADD COLUMN IF NOT EXISTS status_changed_by INT DEFAULT NULL",
        // fee_payments
        "ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS approval_status ENUM('approved','pending','void') DEFAULT 'approved'",
        "ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS void_reason TEXT DEFAULT NULL",
        "ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS voided_by INT DEFAULT NULL",
        "ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS voided_at TIMESTAMP NULL DEFAULT NULL",
        // waivers
        "ALTER TABLE waivers ADD COLUMN IF NOT EXISTS waiver_type ENUM('full','partial','session_bulk') DEFAULT 'partial'",
        "ALTER TABLE waivers ADD COLUMN IF NOT EXISTS voided_by INT DEFAULT NULL",
        "ALTER TABLE waivers ADD COLUMN IF NOT EXISTS void_reason TEXT DEFAULT NULL",
        "ALTER TABLE waivers ADD COLUMN IF NOT EXISTS voided_at TIMESTAMP NULL DEFAULT NULL",
        // staff_profiles
        "ALTER TABLE staff_profiles ADD COLUMN IF NOT EXISTS max_classes_per_day INT DEFAULT 6",
        "ALTER TABLE staff_profiles ADD COLUMN IF NOT EXISTS max_classes_per_week INT DEFAULT 30",
        // rooms — seat layout
        "ALTER TABLE rooms ADD COLUMN IF NOT EXISTS benches_count INT DEFAULT 10",
        "ALTER TABLE rooms ADD COLUMN IF NOT EXISTS bench_capacity INT DEFAULT 2",
        "ALTER TABLE rooms ADD COLUMN IF NOT EXISTS layout_json LONGTEXT DEFAULT NULL",
        // exam_seats — track column block for multi-column room display
        "ALTER TABLE exam_seats ADD COLUMN IF NOT EXISTS col_block INT DEFAULT 1",
        // payroll
        "ALTER TABLE payroll_runs ADD COLUMN IF NOT EXISTS run_type ENUM('regular','custom') DEFAULT 'regular'",
        "ALTER TABLE payroll_runs ADD COLUMN IF NOT EXISTS description VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE payroll_lines ADD COLUMN IF NOT EXISTS bonus_amount DECIMAL(10,2) DEFAULT 0.00",
        "ALTER TABLE payroll_lines ADD COLUMN IF NOT EXISTS bonus_desc VARCHAR(150) DEFAULT NULL",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (Exception) { /* column may already exist */ }
    }

    // New tables (CREATE TABLE IF NOT EXISTS is safe)
    $newTables = [
        "CREATE TABLE IF NOT EXISTS teacher_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            UNIQUE KEY unique_teacher_subject (teacher_id, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS teacher_class_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL, class_id INT NOT NULL, section_id INT NOT NULL,
            subject_id INT NOT NULL, teacher_id INT NOT NULL, slot_id INT DEFAULT NULL,
            log_date DATE NOT NULL, status ENUM('conducted','missed') DEFAULT 'conducted',
            notes TEXT DEFAULT NULL, marked_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
            FOREIGN KEY (class_id) REFERENCES classes(id),
            FOREIGN KEY (section_id) REFERENCES sections(id),
            FOREIGN KEY (subject_id) REFERENCES subjects(id),
            FOREIGN KEY (teacher_id) REFERENCES users(id),
            FOREIGN KEY (marked_by) REFERENCES users(id),
            UNIQUE KEY unique_class_log (slot_id, log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS section_working_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL, class_id INT NOT NULL, section_id INT DEFAULT NULL,
            working_days VARCHAR(150) DEFAULT 'Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday',
            updated_by INT DEFAULT NULL,
            FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_section_days (session_id, class_id, section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payment_void_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('payment','waiver') NOT NULL,
            entity_id INT NOT NULL, action VARCHAR(50) NOT NULL,
            old_status VARCHAR(50) DEFAULT NULL, new_status VARCHAR(50) DEFAULT NULL,
            amount_affected DECIMAL(10,2) DEFAULT 0.00,
            performed_by INT NOT NULL, reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (performed_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS session_clone_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_session_id INT NOT NULL, to_session_id INT NOT NULL, cloned_by INT NOT NULL,
            cloned_items JSON DEFAULT NULL, notes VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_session_id) REFERENCES academic_sessions(id),
            FOREIGN KEY (to_session_id) REFERENCES academic_sessions(id),
            FOREIGN KEY (cloned_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS roll_change_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL, session_id INT NOT NULL, class_id INT NOT NULL,
            section_id INT NOT NULL, old_roll INT NOT NULL, new_roll INT NOT NULL,
            changed_by INT NOT NULL, reason VARCHAR(255) DEFAULT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
            FOREIGN KEY (changed_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS staff_loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL, loan_amount DECIMAL(10,2) NOT NULL,
            interest_rate DECIMAL(5,2) DEFAULT 0.00, total_repayable DECIMAL(10,2) NOT NULL,
            monthly_installment DECIMAL(10,2) NOT NULL, amount_repaid DECIMAL(10,2) DEFAULT 0.00,
            disbursed_date DATE NOT NULL, status ENUM('active','paid','defaulted') DEFAULT 'active',
            transferred_to_loan_id INT DEFAULT NULL, transferred_to_user_id INT DEFAULT NULL,
            notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS notices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL, content TEXT NOT NULL,
            audience ENUM('all','students','staff') DEFAULT 'all',
            created_by INT NOT NULL, publish_date DATE NOT NULL,
            is_broadcast TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS feedback_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_role ENUM('student','staff','other') DEFAULT 'other',
            user_id INT DEFAULT NULL,
            feedback_type ENUM('suggestion','problem','asking','other') DEFAULT 'suggestion',
            title VARCHAR(255) NOT NULL, content TEXT NOT NULL,
            is_anonymous TINYINT(1) DEFAULT 0,
            status ENUM('submitted','reviewed','action_taken','archived') DEFAULT 'submitted',
            action_taken TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_name VARCHAR(100) NOT NULL,
            account_type ENUM('cash','bank','mobile_banking') NOT NULL,
            account_number VARCHAR(50) DEFAULT NULL, bank_name VARCHAR(100) DEFAULT NULL,
            current_balance DECIMAL(12,2) DEFAULT 0.00, notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS account_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL, amount DECIMAL(12,2) NOT NULL,
            transaction_type ENUM('deposit','withdrawal','transfer','adjustment') NOT NULL,
            description VARCHAR(255) NOT NULL,
            reference_table VARCHAR(50) DEFAULT NULL, reference_id INT DEFAULT NULL,
            created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($newTables as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) { /* already exists */ }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function do_wipe_all(PDO $pdo): void {
    // Run schema migrations first so new tables exist before truncate
    do_migrate_schema($pdo);
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

function do_seed_session(PDO $pdo, int $session_id, string $year, array $options = []): void {
    $student_count = $options['student_count'] ?? 400;
    $teacher_count = $options['teacher_count'] ?? 28;
    $class8_two_sections = $options['class8_two_sections'] ?? false;
    $max_months = $options['max_months'] ?? 12;
    $pay_rate = $options['pay_rate'] ?? 0.90;

    mt_srand(crc32("ems_seed_{$year}_{$student_count}"));

    // We wrap everything in a transaction for speed!
    $pdo->beginTransaction();

    try {
        // 1. Classes & Sections
        $classConfig = [
            ['Play Group', 0, 'playgroup'],
            ['Baby', 0, 'playgroup'],
            ['Nursery', 0, 'playgroup'],
            ['KG', 0, 'primary'],
            ['Class 1', 1, 'primary'],
            ['Class 2', 2, 'primary'],
            ['Class 3', 3, 'primary'],
            ['Class 4', 4, 'primary'],
            ['Class 5', 5, 'primary'],
            ['Class 6', 6, 'secondary'],
            ['Class 7', 7, 'secondary'],
            ['Class 8', 8, 'secondary'],
            ['Class 9', 9, 'secondary'],
            ['Class 10', 10, 'secondary'],
            ['Class 11', 11, 'higher_secondary'],
            ['Class 12', 12, 'higher_secondary'],
        ];

        $classIds = [];
        $sectionIds = [];
        $flatSections = []; // array of ['class_id' => X, 'section_id' => Y]

        foreach ($classConfig as $ord => [$cname, $cnum, $clvl]) {
            // Check if class exists
            $ex = $pdo->prepare('SELECT id FROM classes WHERE class_name=?');
            $ex->execute([$cname]);
            $cid = (int)$ex->fetchColumn();
            if (!$cid) {
                $pdo->prepare('INSERT INTO classes (class_name,class_numeric,class_level,display_order,status) VALUES (?,?,?,?,1)')
                    ->execute([$cname, $cnum, $clvl, $ord + 1]);
                $cid = (int)$pdo->lastInsertId();
            }
            $classIds[$cname] = $cid;
            $sectionIds[$cid] = [];

            // Sections list for this class
            $sects = ['A'];
            if ($cname === 'Class 8' && $class8_two_sections) {
                $sects = ['A', 'B'];
            }

            foreach ($sects as $sn) {
                $ex2 = $pdo->prepare('SELECT id FROM sections WHERE class_id=? AND section_name=?');
                $ex2->execute([$cid, $sn]);
                $secId = (int)$ex2->fetchColumn();
                if (!$secId) {
                    $pdo->prepare('INSERT INTO sections (class_id,section_name,shift,capacity,status) VALUES (?,?,?,?,1)')
                        ->execute([$cid, $sn, 'morning', 40]);
                    $secId = (int)$pdo->lastInsertId();
                }
                $sectionIds[$cid][] = $secId;
                $flatSections[] = [
                    'class_name' => $cname,
                    'class_id' => $cid,
                    'section_id' => $secId,
                ];
            }
        }

        // 2. Subjects & Class-Subject Assignments
        $subjectConfig = [
            ['Bangla','BAN-01','core'],
            ['English','ENG-01','core'],
            ['Mathematics','MAT-01','core'],
            ['General Science','SCI-01','core'],
            ['Social Studies','SST-01','core'],
            ['Islam & Moral Education','IME-01','religious'],
            ['ICT','ICT-01','core'],
            ['Physical Education','PHE-01','core'],
        ];
        $subjectIds = [];
        foreach ($subjectConfig as [$sname,$scode,$stype]) {
            $ex = $pdo->prepare('SELECT id FROM subjects WHERE subject_code=?');
            $ex->execute([$scode]);
            $sid = (int)$ex->fetchColumn();
            if (!$sid) {
                $pdo->prepare('INSERT INTO subjects (subject_name,subject_code,subject_type,status) VALUES (?,?,?,1)')
                    ->execute([$sname, $scode, $stype]);
                $sid = (int)$pdo->lastInsertId();
            }
            $subjectIds[$sname] = $sid;
        }

        $subjForClass = [
            'Play Group' => ['Bangla','English','Mathematics'],
            'Baby'       => ['Bangla','English','Mathematics'],
            'Nursery'    => ['Bangla','English','Mathematics'],
            'KG'         => ['Bangla','English','Mathematics'],
        ];

        foreach ($classConfig as [$cname,,]) {
            $cid = $classIds[$cname];
            $forThis = $subjForClass[$cname] ?? array_keys($subjectIds);
            foreach ($forThis as $sn) {
                $sid = $subjectIds[$sn] ?? null;
                if (!$sid) continue;
                $pdo->prepare('INSERT IGNORE INTO class_subjects (class_id,session_id,subject_id,full_marks_written,pass_marks_written,periods_per_week) VALUES (?,?,?,100,33,5)')
                    ->execute([$cid, $session_id, $sid]);
            }
        }

        // 3. Teachers & Staff Configuration
        $maleFirst  = ['Md. Rahim','Md. Karim','Md. Ahmed','Md. Hassan','Md. Sohel','Md. Akash','Md. Rajib','Md. Farhan','Md. Sabbir','Md. Rasel','Md. Tanvir','Md. Imran','Md. Sakib','Md. Naim','Md. Arif'];
        $femFirst   = ['Fatema','Ayesha','Sadia','Nadia','Mitu','Ritu','Nasrin','Lima','Keya','Tania','Mim','Dola','Mumu','Puja','Shiuli'];
        $surnames   = ['Khan','Ahmed','Islam','Hossain','Rahman','Ali','Mia','Bhuiyan','Chowdhury','Sarkar','Paul','Das','Mondol','Akther'];
        $guardNames = ['Md. Abdul Karim','Md. Rafiqul Islam','Md. Shafiqul Hossain','Md. Jamal Uddin','Md. Mizanur Rahman'];
        $religions  = ['Islam','Hinduism','Buddhism','Christianity'];
        $bloods     = ['A+','B+','O+','AB+','A-','B-'];

        // Core admin/staff configs
        $adminStaffConfig = [
            ['Mizanur','Rahman','male','Accountant','Finance','acc.mizan','accountant',[],30000],
            ['Sadia','Islam','female','Data Entry Operator','Admin','de.sadia','data_operator',[],22000],
            ['Delwar','Hossain','male','HR Manager','HR','hr.delwar','hr_manager',[],32000],
            ['Hasan','Jamil','male','Principal','Administration','principal.hasan','principal',[],55000],
            ['Abdur','Rashid','male','Librarian','Library','lib.rashid','librarian',[],20000],
        ];

        $staffHash = password_hash('Staff@1234', PASSWORD_BCRYPT);
        $studentHash = password_hash('Student@1234', PASSWORD_BCRYPT);

        // First insert Admin/Other staff
        $staffIds = [];
        foreach ($adminStaffConfig as [$fn,$ln,$gender,$desig,$dept,$uname,$roleSlug,$subjs,$salary]) {
            $ex = $pdo->prepare('SELECT id FROM users WHERE username=?');
            $ex->execute([$uname]);
            $uid = (int)$ex->fetchColumn();
            if (!$uid) {
                $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')
                    ->execute([$uname, $staffHash, "$fn $ln", "$uname@school.edu.bd", 'active']);
                $uid = (int)$pdo->lastInsertId();
            }
            $staffIds[$uname] = $uid;

            $roleId = $pdo->prepare('SELECT id FROM roles WHERE role_slug=?');
            $roleId->execute([$roleSlug]);
            $rid = $roleId->fetchColumn();
            if ($rid) {
                $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')
                    ->execute([$uid, $rid]);
            }

            $ex2 = $pdo->prepare('SELECT id FROM staff_profiles WHERE user_id=?');
            $ex2->execute([$uid]);
            if (!$ex2->fetchColumn()) {
                $eid = 'EMP-ADM-' . str_pad($uid, 3, '0', STR_PAD_LEFT);
                $pdo->prepare('INSERT INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,department,gender,joining_date,base_salary,status) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid, $eid, $fn, $ln, $desig, $dept, $gender, '2023-01-01', $salary, 'active']);
            }
        }

        // Generate and insert Teacher configurations
        $teacherIds = [];
        $subjects = ['Bangla','English','Mathematics','General Science','Social Studies','Islam & Moral Education','ICT','Physical Education'];

        for ($i = 1; $i <= $teacher_count; $i++) {
            $uname = "teacher.$i";
            $isMale = ($i % 2 === 1);
            $fn = $isMale ? $maleFirst[$i % count($maleFirst)] : $femFirst[$i % count($femFirst)];
            $ln = $surnames[$i % count($surnames)];
            $gender = $isMale ? 'male' : 'female';
            $desig = ($i <= 5 ? 'Senior Teacher' : ($i <= 20 ? 'Teacher' : 'Assistant Teacher'));
            $mainSubj = $subjects[$i % count($subjects)];
            $subjs = [$mainSubj];
            if ($i % 5 === 0) {
                $subjs[] = $subjects[($i + 1) % count($subjects)];
            }
            $salary = 20000 + ($i % 5) * 2000;

            $ex = $pdo->prepare('SELECT id FROM users WHERE username=?');
            $ex->execute([$uname]);
            $uid = (int)$ex->fetchColumn();
            if (!$uid) {
                $pdo->prepare('INSERT INTO users (username,password_hash,full_name,email,status) VALUES (?,?,?,?,?)')
                    ->execute([$uname, $staffHash, "$fn $ln", "$uname@school.edu.bd", 'active']);
                $uid = (int)$pdo->lastInsertId();
            }
            $teacherIds[] = $uid;
            $staffIds[$uname] = $uid;

            $roleId = $pdo->prepare('SELECT id FROM roles WHERE role_slug=?');
            $roleId->execute(['teacher']);
            $rid = $roleId->fetchColumn();
            if ($rid) {
                $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')
                    ->execute([$uid, $rid]);
            }

            $ex2 = $pdo->prepare('SELECT id FROM staff_profiles WHERE user_id=?');
            $ex2->execute([$uid]);
            if (!$ex2->fetchColumn()) {
                $eid = 'EMP-TCH-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $pdo->prepare('INSERT INTO staff_profiles (user_id,employee_id,first_name,last_name,designation,department,gender,joining_date,base_salary,status) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid, $eid, $fn, $ln, $desig, $mainSubj, $gender, '2023-01-01', $salary, 'active']);
            }

            // Map teacher subject expertise
            foreach ($subjs as $sn) {
                $sid2 = $subjectIds[$sn] ?? null;
                if ($sid2) {
                    $pdo->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id,subject_id) VALUES (?,?)')
                        ->execute([$uid, $sid2]);
                }
            }
        }

        // 4. Student Admissions & Enrollments
        // Distribute student_count across flatSections
        $sectionsCount = count($flatSections);
        $base_count = (int)floor($student_count / $sectionsCount);
        $remainder = $student_count % $sectionsCount;

        $sectionCapacities = [];
        foreach ($flatSections as $idx => $sec) {
            $cap = $base_count + ($idx < $remainder ? 1 : 0);
            $sectionCapacities[$sec['class_id']][$sec['section_id']] = $cap;
        }

        $allStudentIds = []; // class_id => section_id => array of uids
        $nameIdx = 0;
        $stuRoleId = $pdo->query("SELECT id FROM roles WHERE role_slug='student'")->fetchColumn();

        foreach ($flatSections as $sec) {
            $cid = $sec['class_id'];
            $secId = $sec['section_id'];
            $cname = $sec['class_name'];
            $capacity = $sectionCapacities[$cid][$secId];

            for ($r = 1; $r <= $capacity; $r++) {
                $isMale  = ($r % 2 === 1);
                $fname   = $isMale ? $maleFirst[$nameIdx % count($maleFirst)] : $femFirst[$nameIdx % count($femFirst)];
                $lname   = $surnames[$nameIdx % count($surnames)];
                $gender  = $isMale ? 'male' : 'female';
                $nameIdx++;

                $uname   = 'stu.' . $year . '.' . str_pad($nameIdx, 4, '0', STR_PAD_LEFT);
                $ex = $pdo->prepare('SELECT id FROM users WHERE username=?');
                $ex->execute([$uname]);
                $uid = (int)$ex->fetchColumn();
                if (!$uid) {
                    $pdo->prepare('INSERT INTO users (username,password_hash,full_name,status) VALUES (?,?,?,?)')
                        ->execute([$uname, $studentHash, "$fname $lname", 'active']);
                    $uid = (int)$pdo->lastInsertId();
                }

                $sid_str = 'STU-' . $year . '-' . str_pad($nameIdx, 5, '0', STR_PAD_LEFT);
                $ex2 = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id=?');
                $ex2->execute([$uid]);
                if (!$ex2->fetchColumn()) {
                    $dobY = (int)$year - (10 + ($r % 5));
                    $dob  = "$dobY-0" . mt_rand(1,9) . "-" . str_pad(mt_rand(1,28),2,'0',STR_PAD_LEFT);
                    $gName= $guardNames[$nameIdx % count($guardNames)];
                    $pdo->prepare(
                        'INSERT INTO student_profiles (user_id,student_id_no,first_name,last_name,dob,gender,religion,blood_group,father_name,guardian_name,guardian_phone,guardian_relation,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$uid,$sid_str,$fname,$lname,$dob,$gender,$religions[$nameIdx%4],$bloods[$nameIdx%6],"Md. {$lname}",$gName,"017" . str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT),'Father',"$year-01-" . str_pad(mt_rand(1,20),2,'0',STR_PAD_LEFT)]);
                }

                // Enrollment
                $pdo->prepare('INSERT IGNORE INTO student_enrollments (student_id,session_id,class_id,section_id,roll_number,status) VALUES (?,?,?,?,?,"active")')
                    ->execute([$uid, $session_id, $cid, $secId, $r]);

                // Role assignment
                if ($stuRoleId) {
                    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')
                        ->execute([$uid, $stuRoleId]);
                }

                $allStudentIds[$cid][$secId][] = $uid;
            }
        }

        // 5. Fee Structures & Ledgers
        $tuitionAmt = [
            'Play Group'=>300,'Baby'=>300,'Nursery'=>300,'KG'=>350,
            'Class 1'=>400,'Class 2'=>400,'Class 3'=>400,'Class 4'=>500,'Class 5'=>500,
            'Class 6'=>700,'Class 7'=>700,'Class 8'=>700,'Class 9'=>900,'Class 10'=>900,
            'Class 11'=>1200,'Class 12'=>1200
        ];

        $tuitionCatId  = (int)$pdo->query("SELECT id FROM fee_categories WHERE category_type='tuition' LIMIT 1")->fetchColumn();
        $sessionCatId  = (int)$pdo->query("SELECT id FROM fee_categories WHERE category_type='session' LIMIT 1")->fetchColumn();
        $examCatId     = (int)$pdo->query("SELECT id FROM fee_categories WHERE category_type='exam' LIMIT 1")->fetchColumn();

        foreach ($classConfig as [$cname,,]) {
            $cid = $classIds[$cname];
            $amt = $tuitionAmt[$cname] ?? 500;
            $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')
                ->execute([$session_id, $cid, $tuitionCatId, $amt, 10, 'monthly']);
            $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')
                ->execute([$session_id, $cid, $sessionCatId, 1500, 15, 'once']);
            $pdo->prepare('INSERT IGNORE INTO fee_structures (session_id,class_id,fee_category_id,amount,due_day,frequency) VALUES (?,?,?,?,?,?)')
                ->execute([$session_id, $cid, $examCatId, 250, 10, 'once']);
        }

        $accId = (int)$pdo->query("SELECT id FROM accounts LIMIT 1")->fetchColumn();
        $ledStmt = $pdo->prepare('INSERT IGNORE INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,month,year,status) VALUES (?,?,?,?,?,?,?,"unpaid")');
        $payStmt = $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,?,?,?,1,?,"approved")');
        $updLed  = $pdo->prepare('UPDATE fee_ledgers SET amount_paid=amount_due,status="paid" WHERE id=?');
        $updAcc  = $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?');

        $rcpNum = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_number,'-',-1) AS UNSIGNED)),1000) FROM fee_payments WHERE receipt_number LIKE 'RCP-%'")->fetchColumn();

        // 10% of students in 2026 have partial waivers
        // Let's seed waivers!
        $waiverStmt = $pdo->prepare('INSERT INTO waivers (student_id,ledger_id,requested_amount,waiver_reason,waiver_type,requested_by,status) VALUES (?,?,?,?,"partial",1,"approved")');
        $updWaiverLed = $pdo->prepare('UPDATE fee_ledgers SET waiver_amount=?,waiver_status="approved" WHERE id=?');

        // Session charge
        foreach ($flatSections as $sec) {
            $cid = $sec['class_id'];
            $secId = $sec['section_id'];
            foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                $dueDate = "$year-01-15";
                $ledStmt->execute([$uid, $session_id, $sessionCatId, 1500, $dueDate, null, null]);
                $lid = (int)$pdo->lastInsertId();
                if (!$lid) continue;

                $rand = mt_rand(1, 100);
                if ($rand <= ($pay_rate * 100)) {
                    // Paid
                    $rcpNum++;
                    $payStmt->execute([$lid, $uid, 1500, "$year-01-20", 'cash', "RCP-$year-$rcpNum", $accId]);
                    $updLed->execute([$lid]);
                    $updAcc->execute([1500, $accId]);
                } elseif ($rand <= (($pay_rate + 0.10) * 100)) {
                    // Waiver request & approved
                    $waiverAmt = 500.00;
                    $waiverStmt->execute([$uid, $lid, $waiverAmt, 'Financial hardship scholarship']);
                    $updWaiverLed->execute([$waiverAmt, $lid]);
                }
            }
        }

        // Monthly tuition
        for ($m = 1; $m <= $max_months; $m++) {
            $dueDate = "$year-" . str_pad($m,2,'0',STR_PAD_LEFT) . "-10";
            $payDay  = "$year-" . str_pad($m,2,'0',STR_PAD_LEFT) . "-" . str_pad(mt_rand(10,25),2,'0',STR_PAD_LEFT);
            foreach ($flatSections as $sec) {
                $cid = $sec['class_id'];
                $secId = $sec['section_id'];
                $amt = $tuitionAmt[$sec['class_name']] ?? 500;

                foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                    $ledStmt->execute([$uid, $session_id, $tuitionCatId, $amt, $dueDate, $m, $year]);
                    $lid = (int)$pdo->lastInsertId();
                    if (!$lid) continue;

                    if (mt_rand(1, 100) <= ($pay_rate * 100)) {
                        $rcpNum++;
                        $payStmt->execute([$lid, $uid, $amt, $payDay, 'cash', "RCP-$year-$rcpNum", $accId]);
                        $updLed->execute([$lid]);
                        $updAcc->execute([$amt, $accId]);
                    }
                }
            }
        }

        // 6. Routine Slots
        // Seed class routines
        $days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday'];
        $slots = [
            ['09:00:00', '09:45:00'],
            ['09:45:00', '10:30:00'],
            ['11:00:00', '11:45:00'],
            ['11:45:00', '12:30:00']
        ];

        $rooms = $pdo->query('SELECT id FROM rooms')->fetchAll(PDO::FETCH_COLUMN);
        if (empty($rooms)) {
            // Seed a few rooms first!
            for ($rNum = 101; $rNum <= 116; $rNum++) {
                $pdo->prepare('INSERT INTO rooms (room_name,room_number,floor,capacity,room_type,status) VALUES (?,?,1,40,"classroom",1)')
                    ->execute(["Room $rNum", (string)$rNum]);
            }
            $rooms = $pdo->query('SELECT id FROM rooms')->fetchAll(PDO::FETCH_COLUMN);
        }

        $routineInsert = $pdo->prepare('INSERT INTO routine_slots (session_id,class_id,section_id,subject_id,teacher_id,room_id,day_of_week,start_time,end_time,status) VALUES (?,?,?,?,?,?,?,?,?,1)');

        foreach ($flatSections as $index => $sec) {
            $cid = $sec['class_id'];
            $secId = $sec['section_id'];
            $classSubjs = $pdo->prepare('SELECT subject_id FROM class_subjects WHERE class_id=? AND session_id=?');
            $classSubjs->execute([$cid, $session_id]);
            $sids = $classSubjs->fetchAll(PDO::FETCH_COLUMN);
            if (empty($sids)) continue;

            $room_id = $rooms[$index % count($rooms)];

            foreach ($days as $day) {
                foreach ($slots as $slotIdx => [$start, $end]) {
                    $subId = $sids[($slotIdx + $index) % count($sids)];

                    // Find a teacher for this subject
                    $tchStmt = $pdo->prepare('SELECT teacher_id FROM teacher_subjects WHERE subject_id=? ORDER BY RAND() LIMIT 1');
                    $tchStmt->execute([$subId]);
                    $tId = $tchStmt->fetchColumn();
                    if (!$tId && !empty($teacherIds)) {
                        $tId = $teacherIds[array_rand($teacherIds)];
                    }

                    if ($tId) {
                        $routineInsert->execute([$session_id, $cid, $secId, $subId, $tId, $room_id, $day, $start, $end]);
                    }
                }
            }
        }

        // 7. Exams & Marks Config
        $examConfig = ($year === '2025') ? [
            ['1st Terminal Exam 2025','terminal','2025-03-01','2025-03-15','results_published'],
            ['Half-Yearly Exam 2025','midterm','2025-06-01','2025-06-15','results_published'],
            ['Annual Exam 2025','annual','2025-11-01','2025-11-30','results_published'],
        ] : [
            ['1st Terminal Exam 2026','terminal','2026-03-01','2026-03-15','results_published'],
            ['Half-Yearly Exam 2026','midterm','2026-06-01','2026-06-15','scheduled'],
        ];

        foreach ($examConfig as [$ename,$etype,$estart,$eend,$estatus]) {
            $pdo->prepare('INSERT INTO exams (session_id,exam_name,exam_type,start_date,end_date,status) VALUES (?,?,?,?,?,?)')
                ->execute([$session_id, $ename, $etype, $estart, $eend, $estatus]);
            $examId = (int)$pdo->lastInsertId();

            foreach ($flatSections as $sec) {
                $cid = $sec['class_id'];
                $secId = $sec['section_id'];

                // Map class to exam
                $pdo->prepare('INSERT IGNORE INTO exam_class_map (exam_id,class_id) VALUES (?,?)')
                    ->execute([$examId, $cid]);

                // Class subjects
                $classSubjs = $pdo->prepare('SELECT subject_id FROM class_subjects WHERE class_id=? AND session_id=?');
                $classSubjs->execute([$cid, $session_id]);
                $sids = $classSubjs->fetchAll(PDO::FETCH_COLUMN);

                foreach ($sids as $subId) {
                    $pdo->prepare('INSERT IGNORE INTO exam_subject_config (exam_id,class_id,subject_id,full_marks_written,pass_marks_written) VALUES (?,?,?,100,33)')
                        ->execute([$examId, $cid, $subId]);
                }

                // Marks for published exams
                if ($estatus === 'results_published') {
                    foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                        foreach ($sids as $subId) {
                            $isAbsent = (mt_rand(1, 100) <= 2);
                            $rawMark = $isAbsent ? 0 : mt_rand(35, 95);
                            if (mt_rand(1, 10) === 1) $rawMark = mt_rand(85, 100);
                            if (mt_rand(1, 10) === 1) $rawMark = mt_rand(20, 39);

                            $pdo->prepare('INSERT IGNORE INTO marks_entry (exam_id,student_id,class_id,section_id,subject_id,marks_written,is_absent,entered_by) VALUES (?,?,?,?,?,?,?,1)')
                                ->execute([$examId, $uid, $cid, $secId, $subId, $isAbsent ? null : $rawMark, $isAbsent ? 1 : 0]);
                        }
                    }
                }
            }
        }

        // 8. Staff Loans
        // Seed 5 loans
        $loanStmt = $pdo->prepare('INSERT INTO staff_loans (staff_id, loan_amount, interest_rate, total_repayable, monthly_installment, amount_repaid, disbursed_date, status, notes) VALUES (?,?,?,?,?,?,?,?,?)');
        $activeStaffIds = array_values($staffIds);
        if (count($activeStaffIds) >= 5) {
            $loanStmt->execute([$activeStaffIds[0], 50000, 5, 52500, 4375, 17500, "$year-02-15", 'active', 'Personal medical emergency']);
            $loanStmt->execute([$activeStaffIds[1], 30000, 0, 30000, 2500, 30000, "$year-01-10", 'paid', 'Advance salary loan for house renovation']);
            $loanStmt->execute([$activeStaffIds[2], 20000, 8, 21600, 1800, 1800, "$year-03-01", 'defaulted', 'Laptop purchase loan (delinquent)']);
            $loanStmt->execute([$activeStaffIds[3], 100000, 4, 104000, 8666.67, 34666.68, "$year-01-05", 'active', 'Home construction assistance']);
            $loanStmt->execute([$activeStaffIds[4], 15000, 0, 15000, 1500, 7500, "$year-04-10", 'active', 'Emergency advance salary']);
        }

        // 9. Expenses and Non-Fee Income
        $expCatIds = $pdo->query('SELECT id, category_name FROM expense_categories')->fetchAll(PDO::FETCH_KEY_PAIR);
        $incCatIds = $pdo->query('SELECT id, category_name FROM income_categories')->fetchAll(PDO::FETCH_KEY_PAIR);

        $expenseInsert = $pdo->prepare('INSERT INTO expenses (session_id,expense_category_id,amount,expense_date,description,vendor,invoice_no,approved_by,status,account_id) VALUES (?,?,?,?,?,?,?,1,"approved",?)');
        $incomeInsert = $pdo->prepare('INSERT INTO incomes (session_id,income_category_id,amount,income_date,description,received_by,account_id) VALUES (?,?,?,?,?,1,?)');

        // Let's seed 15 expenses
        $expensesData = [
            ['Salary', 120000.00, 'Staff monthly payroll salary disbursement', 'Bank Transfer', 'INV-SAL-01'],
            ['Utilities', 15000.00, 'DESCO electricity bill payment', 'Dhaka Electric Supply', 'INV-UT-01'],
            ['Utilities', 4500.00, 'WASA water bill payment', 'Dhaka WASA', 'INV-UT-02'],
            ['Maintenance', 8500.00, 'Classroom whiteboards maintenance and repair', 'Karim Traders', 'INV-MT-01'],
            ['Stationery', 12500.00, 'Exam papers, logs and markers procurement', 'Bengal Stationery Store', 'INV-ST-01'],
            ['Furniture', 45000.00, '10 new wooden high-benches and low-benches', 'Brothers Furniture', 'INV-FR-01'],
            ['Equipment', 35000.00, 'Projector for science laboratory classes', 'Excel Technologies', 'INV-EQ-01'],
            ['Event Expenses', 25000.00, 'Prizes and decorations for Annual Cultural Event', 'Dola Events Ltd.', 'INV-EV-01'],
            ['Stationery', 6000.00, 'A4 papers and office register files', 'Paper World', 'INV-ST-02'],
            ['Miscellaneous', 3000.00, 'Canteen refreshment costs for parent teacher meeting', 'Dhaka Sweets', 'INV-MS-01'],
            ['Maintenance', 18000.00, 'ICT Lab AC servicing and gas charge replacement', 'AC Repair Solution', 'INV-MT-02'],
            ['Furniture', 12000.00, 'Principal office wooden executive chair purchase', 'Otobi Furniture', 'INV-FR-02'],
            ['Equipment', 9500.00, '2 Laser Jet printers for exam control room', 'Star Tech', 'INV-EQ-02'],
            ['Utilities', 2500.00, 'BTCL landline telephone and internet bill', 'BTCL', 'INV-UT-03'],
            ['Event Expenses', 15000.00, 'Independence Day discussion program food distribution', 'Radhuni Catering', 'INV-EV-02']
        ];

        foreach ($expensesData as [$catName, $amount, $desc, $vendor, $invNo]) {
            $catId = array_search($catName, $expCatIds) ?: 1;
            $expDate = "$year-0" . mt_rand(1, 6) . "-" . str_pad(mt_rand(1, 28), 2, '0', STR_PAD_LEFT);
            $expenseInsert->execute([$session_id, $catId, $amount, $expDate, $desc, $vendor, $invNo, $accId]);
        }

        // Let's seed 8 incomes
        $incomesData = [
            ['Donation', 150000.00, 'Annual donation received from local education patron', 'Patron Contribution'],
            ['Venue Rental', 45000.00, 'School playground rented for community sports tournament', 'Ground Rental Revenue'],
            ['Canteen Revenue', 25000.00, 'School cafeteria monthly lease rent contribution', 'Canteen Lease Rent'],
            ['Miscellaneous Income', 12000.00, 'Sales of scrapped newspaper archives and books', 'Scrap Paper Auction'],
            ['Canteen Revenue', 25000.00, 'School cafeteria monthly lease rent contribution (month 2)', 'Canteen Lease Rent'],
            ['Donation', 80000.00, 'Ex-students alumni fund contribution for library development', 'Alumni Donation'],
            ['Venue Rental', 30000.00, 'Auditorium rented for national debate tournament venue', 'Debate Venue Revenue'],
            ['Miscellaneous Income', 8000.00, 'Library fine collections for late book returns', 'Late Fines Collection']
        ];

        foreach ($incomesData as [$catName, $amount, $desc, $source]) {
            $catId = array_search($catName, $incCatIds) ?: 1;
            $incDate = "$year-0" . mt_rand(1, 6) . "-" . str_pad(mt_rand(1, 28), 2, '0', STR_PAD_LEFT);
            $incomeInsert->execute([$session_id, $catId, $amount, $incDate, "$desc ($source)", $accId]);
        }

        // 10. Payroll Run & Lines
        $payrollRunInsert = $pdo->prepare('INSERT INTO payroll_runs (session_id, month, year, status, run_type, description, created_by) VALUES (?,?,?,?,"regular",?,1)');
        $payrollLineInsert = $pdo->prepare('INSERT INTO payroll_lines (payroll_run_id, staff_id, base_salary, bonus_amount, bonus_desc, exam_duty_allowance, tax_deduction, provident_fund, net_salary, payment_status, notes) VALUES (?,?,?,?,?,?,0.00,0.00,?,"paid",?)');

        for ($m = 1; $m <= $max_months; $m++) {
            $runStatus = ($year === '2025' || $m < $max_months) ? 'paid' : 'finalized';
            $payrollRunInsert->execute([$session_id, $m, (int)$year, $runStatus, "Regular Payroll for " . date('F', mktime(0,0,0,$m,1)) . " $year"]);
            $runId = (int)$pdo->lastInsertId();

            foreach ($staffIds as $uname => $uid) {
                // Get base salary from staff profile
                $salQuery = $pdo->prepare('SELECT base_salary, designation FROM staff_profiles WHERE user_id=?');
                $salQuery->execute([$uid]);
                $profile = $salQuery->fetch();
                $baseSal = (float)($profile['base_salary'] ?? 25000);
                $desig = $profile['designation'] ?? 'Teacher';

                $bonus = 0.00;
                $bonusDesc = null;
                // Add festival bonus in June/July (Eid bonus, etc.)
                if ($m === 5) {
                    $bonus = round($baseSal * 0.5, 2);
                    $bonusDesc = 'Festival Bonus (Eid-ul-Fitr)';
                }

                $allowance = (mt_rand(1, 10) === 1) ? 1500.00 : 0.00;
                $netSal = $baseSal + $bonus + $allowance;

                $payrollLineInsert->execute([$runId, $uid, $baseSal, $bonus, $bonusDesc, $allowance, $netSal, "Monthly salary paid to $desig via Bank Transfer"]);
            }
        }

        // 11. Holiday Calendar
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
        foreach ($holidays as [$hd,$hn,$ht]) {
            $hStmt->execute([$session_id, $hd, $hn, $ht]);
        }

        // 12. Student Attendance (last 45 school days)
        $weekends  = ['Friday'];
        $attDate   = new DateTime(($year === '2026') ? '2026-04-01' : '2025-04-01');
        $attEnd    = new DateTime(($year === '2026') ? '2026-06-25' : '2025-11-30');
        $attStmt   = $pdo->prepare('INSERT IGNORE INTO student_attendance (student_id,session_id,class_id,section_id,attendance_date,status,marked_by) VALUES (?,?,?,?,?,?,1)');

        $schoolDays = 0;
        while ($attDate <= $attEnd && $schoolDays < 45) {
            $dow = $attDate->format('l');
            if (!in_array($dow, $weekends)) {
                $ds = $attDate->format('Y-m-d');
                foreach ($flatSections as $sec) {
                    $cid = $sec['class_id'];
                    $secId = $sec['section_id'];
                    foreach (($allStudentIds[$cid][$secId] ?? []) as $uid) {
                        $roll = mt_rand(1, 100);
                        $stat = $roll <= 88 ? 'present' : ($roll <= 94 ? 'late' : 'absent');
                        $attStmt->execute([$uid, $session_id, $cid, $secId, $ds, $stat]);
                    }
                }
                $schoolDays++;
            }
            $attDate->modify('+1 day');
        }

        // 13. Notices & Broadcasts
        $noticeStmt = $pdo->prepare('INSERT INTO notices (title, content, audience, created_by, publish_date, is_broadcast) VALUES (?,?,?,1,?,?)');
        $noticeStmt->execute([
            "Commencement of Half-Yearly Examinations $year",
            "The Half-Yearly Examinations for the session $year will commence from the 1st of next month. Students are advised to collect their admit cards after clearing all dues.",
            "all",
            "$year-05-15",
            1
        ]);
        $noticeStmt->execute([
            "Staff Monthly General Meeting",
            "There will be a general academic meeting for all teachers and office staff in the conference room. Attendance is mandatory.",
            "staff",
            "$year-03-10",
            0
        ]);
        $noticeStmt->execute([
            "Inter-Class Football Tournament Registration",
            "Interested students from Class 6 to 12 can register their names with the Physical Education teacher for the upcoming Inter-Class tournament.",
            "students",
            "$year-02-18",
            0
        ]);

        // 14. Feedback Reports
        $feedbackStmt = $pdo->prepare('INSERT INTO feedback_reports (reporter_role, user_id, feedback_type, title, content, is_anonymous, status, action_taken) VALUES (?,?,?,?,?,?,?,?)');
        // Let's seed 3 feedbacks
        $stUids = [];
        foreach ($allStudentIds as $cid => $secs) {
            foreach ($secs as $secId => $uids) {
                $stUids = array_merge($stUids, $uids);
            }
        }
        if (!empty($stUids) && !empty($teacherIds)) {
            $feedbackStmt->execute([
                'student',
                $stUids[0],
                'suggestion',
                'Need more sports equipment in common room',
                'We currently have very few table tennis rackets and carrom boards. Please add more.',
                0,
                'action_taken',
                'Purchased 4 new table tennis rackets and 2 carrom boards for the student common room.'
            ]);
            $feedbackStmt->execute([
                'staff',
                $teacherIds[0],
                'problem',
                'Slow internet connection in teachers common room',
                'The wifi speed in the staff lounge is extremely slow, making it difficult to search research material during periods.',
                0,
                'reviewed',
                'IT administrator has been notified to check and upgrade the access point.'
            ]);
            $feedbackStmt->execute([
                'student',
                $stUids[1],
                'suggestion',
                'Classroom fan not working properly',
                'The corner ceiling fan in Room 104 is making loud noises and rotating very slowly.',
                1,
                'submitted',
                null
            ]);
        }

        // Commit transaction!
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
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
