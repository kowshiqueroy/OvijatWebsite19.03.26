<?php
// Safety guard — prevent accidental execution in production.
// To run setup, pass ?confirm=SETUP_SOHOJWEB in the URL.
if (($_GET['confirm'] ?? '') !== 'SETUP_SOHOJWEB') {
    die('Access denied. Pass ?confirm=SETUP_SOHOJWEB to run setup intentionally.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sohojweb');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //drop database if exists for testing
    $pdo->exec("DROP DATABASE IF EXISTS " . DB_NAME);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    echo "Database connected<br>";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('super_admin', 'editor', 'sales', 'hr') DEFAULT 'editor',
        avatar VARCHAR(255) DEFAULT NULL,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        last_login DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_settings" => "CREATE TABLE IF NOT EXISTS site_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'textarea', 'number', 'boolean', 'json') DEFAULT 'text',
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "leads" => "CREATE TABLE IF NOT EXISTS leads (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lead_source ENUM('contact_form', 'investment_estimator', 'whatsapp', 'phone', 'referral', 'other') DEFAULT 'contact_form',
        client_name VARCHAR(150) NOT NULL,
        client_email VARCHAR(150) DEFAULT NULL,
        client_phone VARCHAR(20) DEFAULT NULL,
        company_name VARCHAR(150) DEFAULT NULL,
        selected_module VARCHAR(100) DEFAULT NULL,
        complexity_scale INT DEFAULT 1,
        technical_integrations TEXT DEFAULT NULL,
        estimated_budget DECIMAL(15,2) DEFAULT NULL,
        message TEXT DEFAULT NULL,
        status ENUM('new', 'contacted', 'qualified', 'proposal_sent', 'negotiating', 'won', 'lost') DEFAULT 'new',
        assigned_to INT DEFAULT NULL,
        follow_up_date DATE DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "projects" => "CREATE TABLE IF NOT EXISTS projects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        project_code VARCHAR(50) UNIQUE NOT NULL,
        project_name VARCHAR(255) NOT NULL,
        client_name VARCHAR(150) DEFAULT NULL,
        client_email VARCHAR(150) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('planning', 'in_progress', 'review', 'testing', 'completed', 'on_hold', 'cancelled') DEFAULT 'planning',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        start_date DATE DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        completed_date DATE DEFAULT NULL,
        budget DECIMAL(15,2) DEFAULT NULL,
        show_in_portfolio TINYINT(1) DEFAULT 0,
        assigned_to INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "tasks" => "CREATE TABLE IF NOT EXISTS tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_title VARCHAR(255) NOT NULL,
        task_description TEXT DEFAULT NULL,
        task_date DATE NOT NULL,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        assigned_to INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        due_time TIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "project_tasks" => "CREATE TABLE IF NOT EXISTS project_tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT NOT NULL,
        task_title VARCHAR(255) NOT NULL,
        task_description TEXT DEFAULT NULL,
        status ENUM('todo', 'in_progress', 'review', 'done') DEFAULT 'todo',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        assigned_to INT DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        completed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "invoices" => "CREATE TABLE IF NOT EXISTS invoices (
        id INT PRIMARY KEY AUTO_INCREMENT,
        invoice_number VARCHAR(50) UNIQUE NOT NULL,
        invoice_prefix VARCHAR(10) DEFAULT 'INV',
        invoice_date DATE NOT NULL,
        due_date DATE DEFAULT NULL,
        client_name VARCHAR(150) NOT NULL,
        client_email VARCHAR(150) DEFAULT NULL,
        client_address TEXT DEFAULT NULL,
        client_phone VARCHAR(20) DEFAULT NULL,
        client_company VARCHAR(150) DEFAULT NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        tax_rate DECIMAL(5,2) DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
        discount_value DECIMAL(15,2) DEFAULT 0,
        discount_amount DECIMAL(15,2) DEFAULT 0,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        notes TEXT DEFAULT NULL,
        terms TEXT DEFAULT NULL,
        status ENUM('draft', 'sent', 'viewed', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
        project_id INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        share_token VARCHAR(64) UNIQUE DEFAULT NULL,
        viewed_at DATETIME DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "invoice_items" => "CREATE TABLE IF NOT EXISTS invoice_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        invoice_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        item_description TEXT DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "quotations" => "CREATE TABLE IF NOT EXISTS quotations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quote_number VARCHAR(50) UNIQUE NOT NULL,
        quote_prefix VARCHAR(10) DEFAULT 'QUO',
        quote_date DATE NOT NULL,
        valid_until DATE DEFAULT NULL,
        client_name VARCHAR(150) NOT NULL,
        client_email VARCHAR(150) DEFAULT NULL,
        client_address TEXT DEFAULT NULL,
        client_phone VARCHAR(20) DEFAULT NULL,
        client_company VARCHAR(150) DEFAULT NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        tax_rate DECIMAL(5,2) DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
        discount_value DECIMAL(15,2) DEFAULT 0,
        discount_amount DECIMAL(15,2) DEFAULT 0,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        notes TEXT DEFAULT NULL,
        terms TEXT DEFAULT NULL,
        status ENUM('draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired') DEFAULT 'draft',
        project_id INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        share_token VARCHAR(64) UNIQUE DEFAULT NULL,
        viewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "quotation_items" => "CREATE TABLE IF NOT EXISTS quotation_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quotation_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        item_description TEXT DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "job_circulars" => "CREATE TABLE IF NOT EXISTS job_circulars (
        id INT PRIMARY KEY AUTO_INCREMENT,
        job_title VARCHAR(255) NOT NULL,
        company_name VARCHAR(150) DEFAULT NULL,
        company_logo VARCHAR(255) DEFAULT NULL,
        location VARCHAR(150) DEFAULT NULL,
        employment_type VARCHAR(50) DEFAULT NULL,
        salary_range VARCHAR(100) DEFAULT NULL,
        experience_required VARCHAR(100) DEFAULT NULL,
        education_requirement VARCHAR(255) DEFAULT NULL,
        job_description TEXT DEFAULT NULL,
        responsibilities TEXT DEFAULT NULL,
        requirements TEXT DEFAULT NULL,
        benefits TEXT DEFAULT NULL,
        apply_deadline DATE DEFAULT NULL,
        contact_email VARCHAR(150) DEFAULT NULL,
        status ENUM('draft', 'published', 'closed') DEFAULT 'draft',
        image_path VARCHAR(255) DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "job_applications" => "CREATE TABLE IF NOT EXISTS job_applications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        circular_id INT DEFAULT NULL,
        applicant_name VARCHAR(150) NOT NULL,
        applicant_email VARCHAR(150) NOT NULL,
        applicant_phone VARCHAR(20) DEFAULT NULL,
        resume_path VARCHAR(255) DEFAULT NULL,
        cover_letter TEXT DEFAULT NULL,
        status ENUM('pending', 'reviewing', 'shortlisted', 'rejected', 'hired') DEFAULT 'pending',
        notes TEXT DEFAULT NULL,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "custom_documents" => "CREATE TABLE IF NOT EXISTS custom_documents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        doc_type VARCHAR(100) DEFAULT 'Custom Document',
        doc_title VARCHAR(255) NOT NULL,
        recipient_name VARCHAR(150) DEFAULT NULL,
        recipient_email VARCHAR(150) DEFAULT NULL,
        recipient_phone VARCHAR(20) DEFAULT NULL,
        recipient_company VARCHAR(150) DEFAULT NULL,
        recipient_address TEXT DEFAULT NULL,
        content TEXT DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "job_offer_letters" => "CREATE TABLE IF NOT EXISTS job_offer_letters (
        id INT PRIMARY KEY AUTO_INCREMENT,
        offer_number VARCHAR(50) UNIQUE NOT NULL,
        candidate_name VARCHAR(150) NOT NULL,
        candidate_email VARCHAR(150) NOT NULL,
        candidate_phone VARCHAR(20) DEFAULT NULL,
        position VARCHAR(150) NOT NULL,
        department VARCHAR(100) DEFAULT NULL,
        joining_date DATE NOT NULL,
        salary DECIMAL(15,2) DEFAULT NULL,
        salary_currency VARCHAR(10) DEFAULT 'BDT',
        employment_type VARCHAR(50) DEFAULT 'Full-time',
        working_hours VARCHAR(100) DEFAULT NULL,
        probation_period VARCHAR(100) DEFAULT NULL,
        benefits TEXT DEFAULT NULL,
        report_to VARCHAR(150) DEFAULT NULL,
        terms TEXT DEFAULT NULL,
        status ENUM('draft', 'sent', 'accepted', 'rejected') DEFAULT 'draft',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "application_forms" => "CREATE TABLE IF NOT EXISTS application_forms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        form_title VARCHAR(255) NOT NULL,
        form_type ENUM('experience', 'leave', 'recommendation', 'general') DEFAULT 'general',
        applicant_name VARCHAR(150) NOT NULL,
        applicant_email VARCHAR(150) DEFAULT NULL,
        applicant_phone VARCHAR(20) DEFAULT NULL,
        department VARCHAR(100) DEFAULT NULL,
        form_data JSON DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        approver_notes TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        old_data JSON DEFAULT NULL,
        new_data JSON DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_features" => "CREATE TABLE IF NOT EXISTS site_features (
        id INT PRIMARY KEY AUTO_INCREMENT,
        feature_key VARCHAR(50) UNIQUE NOT NULL,
        feature_value TEXT,
        feature_type ENUM('text', 'textarea', 'number', 'json') DEFAULT 'text',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_services" => "CREATE TABLE IF NOT EXISTS site_services (
        id INT PRIMARY KEY AUTO_INCREMENT,
        service_title VARCHAR(255) NOT NULL,
        service_icon VARCHAR(50) DEFAULT NULL,
        service_description TEXT,
        service_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_stats" => "CREATE TABLE IF NOT EXISTS site_stats (
        id INT PRIMARY KEY AUTO_INCREMENT,
        stat_label VARCHAR(100) NOT NULL,
        stat_value VARCHAR(50) NOT NULL,
        stat_icon VARCHAR(50) DEFAULT NULL,
        stat_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_testimonials" => "CREATE TABLE IF NOT EXISTS site_testimonials (
        id INT PRIMARY KEY AUTO_INCREMENT,
        client_name VARCHAR(150) NOT NULL,
        client_designation VARCHAR(150) DEFAULT NULL,
        client_company VARCHAR(150) DEFAULT NULL,
        client_image VARCHAR(255) DEFAULT NULL,
        testimonial_text TEXT NOT NULL,
        rating INT DEFAULT 5,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_team" => "CREATE TABLE IF NOT EXISTS site_team (
        id INT PRIMARY KEY AUTO_INCREMENT,
        member_name VARCHAR(150) NOT NULL,
        designation VARCHAR(150) NOT NULL,
        member_image VARCHAR(255) DEFAULT NULL,
        bio TEXT,
        member_email VARCHAR(150) DEFAULT NULL,
        member_facebook VARCHAR(255) DEFAULT NULL,
        member_linkedin VARCHAR(255) DEFAULT NULL,
        member_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_why_choose" => "CREATE TABLE IF NOT EXISTS site_why_choose (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        icon VARCHAR(50) DEFAULT NULL,
        item_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_contact_info" => "CREATE TABLE IF NOT EXISTS site_contact_info (
        id INT PRIMARY KEY AUTO_INCREMENT,
        info_type VARCHAR(50) NOT NULL,
        info_value TEXT NOT NULL,
        info_icon VARCHAR(50) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "site_social_links" => "CREATE TABLE IF NOT EXISTS site_social_links (
        id INT PRIMARY KEY AUTO_INCREMENT,
        platform VARCHAR(50) NOT NULL,
        profile_url VARCHAR(255) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Created table: $name<br>";
    } catch (PDOException $e) {
        echo "✗ Error creating $name: " . $e->getMessage() . "<br>";
    }
}

// Migrations for existing tables
echo "<br><h3>Checking for schema updates...</h3>";
try {
    // Fix site_team missing designation
    $cols = $pdo->query("SHOW COLUMNS FROM site_team LIKE 'designation'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE site_team ADD COLUMN designation VARCHAR(150) NOT NULL AFTER member_name");
        echo "✓ Added 'designation' to site_team<br>";
    }
    
    // Fix site_testimonials missing client_designation
    $cols = $pdo->query("SHOW COLUMNS FROM site_testimonials LIKE 'client_designation'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE site_testimonials ADD COLUMN client_designation VARCHAR(150) AFTER client_name");
        echo "✓ Added 'client_designation' to site_testimonials<br>";
    }
    // Create custom_documents if missing
    $customDocsTable = $pdo->query("SHOW TABLES LIKE 'custom_documents'")->fetchAll();
    if (empty($customDocsTable)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_documents (
            id INT PRIMARY KEY AUTO_INCREMENT,
            doc_type VARCHAR(100) DEFAULT 'Custom Document',
            doc_title VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(150) DEFAULT NULL,
            recipient_email VARCHAR(150) DEFAULT NULL,
            recipient_phone VARCHAR(20) DEFAULT NULL,
            recipient_company VARCHAR(150) DEFAULT NULL,
            recipient_address TEXT DEFAULT NULL,
            content TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created custom_documents table<br>";
    }

    // Rename job_applicants to job_applications if old name exists
    $oldTable = $pdo->query("SHOW TABLES LIKE 'job_applicants'")->fetchAll();
    if (!empty($oldTable)) {
        $newTable = $pdo->query("SHOW TABLES LIKE 'job_applications'")->fetchAll();
        if (empty($newTable)) {
            $pdo->exec("RENAME TABLE job_applicants TO job_applications");
            echo "✓ Renamed job_applicants → job_applications<br>";
        }
    }

    // Add show_in_portfolio column if missing
    $cols = $pdo->query("SHOW COLUMNS FROM projects LIKE 'show_in_portfolio'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN show_in_portfolio TINYINT(1) DEFAULT 0 AFTER budget");
        echo "✓ Added show_in_portfolio column to projects<br>";
    }

    // Add performance indexes if missing
    $indexes = [
        ['leads',        'idx_leads_status',           "ALTER TABLE leads ADD INDEX idx_leads_status (status)"],
        ['leads',        'idx_leads_created_at',       "ALTER TABLE leads ADD INDEX idx_leads_created_at (created_at)"],
        ['projects',     'idx_projects_status',        "ALTER TABLE projects ADD INDEX idx_projects_status (status)"],
        ['projects',     'idx_projects_portfolio',     "ALTER TABLE projects ADD INDEX idx_projects_portfolio (show_in_portfolio)"],
        ['project_tasks','idx_project_tasks_project',  "ALTER TABLE project_tasks ADD INDEX idx_project_tasks_project (project_id)"],
        ['audit_logs',   'idx_audit_created_at',       "ALTER TABLE audit_logs ADD INDEX idx_audit_created_at (created_at)"],
        ['audit_logs',   'idx_audit_entity_type',      "ALTER TABLE audit_logs ADD INDEX idx_audit_entity_type (entity_type)"],
    ];
    foreach ($indexes as [$tbl, $idxName, $ddl]) {
        $exists = $pdo->query("SHOW INDEX FROM `$tbl` WHERE Key_name = '$idxName'")->fetchAll();
        if (empty($exists)) {
            $pdo->exec($ddl);
            echo "✓ Added index $idxName on $tbl<br>";
        }
    }
} catch (PDOException $e) {
    echo "ℹ Migration info: " . $e->getMessage() . "<br>";
}

echo "<br><h3>Inserting settings...</h3>";
$defaultSettings = [
    ['company_name', 'SOHOJWEB', 'text', 'Company Name'],
    ['company_email', 'sohojweb.com@gmail.com', 'text', 'Company Email'],
    ['company_phone', '01632950179', 'text', 'Company Phone'],
    ['company_address', 'Dhaka, Bangladesh', 'textarea', 'Company Address'],
    ['company_logo', '', 'text', 'Company Logo URL'],
    ['company_logo_large', '', 'text', 'Large Logo URL'],
    ['company_favicon', '', 'text', 'Favicon URL'],
    ['tax_rate', '0', 'number', 'Default Tax Rate (%)'],
    ['currency', 'BDT', 'text', 'Currency Code'],
    ['currency_symbol', '৳', 'text', 'Currency Symbol'],
    ['invoice_prefix', 'INV', 'text', 'Invoice Prefix'],
    ['quotation_prefix', 'QUO', 'text', 'Quotation Prefix'],
    ['invoice_terms', 'Payment due within 30 days.', 'textarea', 'Default Invoice Terms'],
    ['maintenance_mode', '0', 'boolean', 'Maintenance Mode'],
    ['timezone', 'Asia/Dhaka', 'text', 'Timezone'],
    ['seo_home_title', 'SohojWeb - Building Smart Ecosystems', 'text', 'SEO Home Title'],
    ['seo_home_description', 'We develop highly modular, colorful, and responsive management software.', 'text', 'SEO Home Description'],
    ['seo_keywords', 'ERP, POS, School Management, Hospital Management, Software Development', 'text', 'SEO Keywords']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
foreach ($defaultSettings as $setting) {
    $stmt->execute($setting);
}
echo "✓ Inserted settings<br>";

echo "<br><h3>Creating users...</h3>";
$passwordHash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute(['admin', 'admin@sohojweb.com', $passwordHash, 'System Administrator', 'super_admin', 'active']);
echo "✓ Created admin user (admin@sohojweb.com / admin123)<br>";

$passwordHash2 = password_hash('editor123', PASSWORD_BCRYPT);
$stmt->execute(['editor', 'editor@sohojweb.com', $passwordHash2, 'Content Editor', 'editor', 'active']);
echo "✓ Created editor user (editor@sohojweb.com / editor123)<br>";

$passwordHash3 = password_hash('sales123', PASSWORD_BCRYPT);
$stmt->execute(['sales', 'sales@sohojweb.com', $passwordHash3, 'Sales Manager', 'sales', 'active']);
echo "✓ Created sales user (sales@sohojweb.com / sales123)<br>";

echo "<br><h3>Seeding demo leads...</h3>";
$leads = [
    ['contact_form', 'John Doe', 'john@example.com', '01512345678', 'Tech Solutions Ltd', 'Education System', 2, 'Mobile App', 65000, 'Interested in school management system', 'new'],
    ['investment_estimator', 'Sarah Ahmed', 'sarah@hospital.com', '01712345678', 'City Hospital', 'Hospital Management System', 3, 'SMS Notifications, API', 120000, 'Need complete hospital ERP', 'contacted'],
    ['contact_form', 'Mr. Rahman', 'rahman@retail.com', '01812345678', 'Fashion House', 'Offline Shop / POS System', 1, 'Barcode Scanner', 45000, 'Looking for POS with inventory', 'qualified'],
    ['whatsapp', 'Dr. Khan', 'khan@clinic.com', '01912345678', 'HealthCare Plus', 'Hospital Management System', 2, 'Offline Sync', 85000, 'Want to try demo', 'new'],
    ['referral', 'Emily Wang', 'emily@enterprise.com', '01612345678', 'Global Corp', 'Enterprise Business ERP', 4, 'API, Mobile App', 250000, 'Multi-branch requirement', 'proposal_sent']
];

$stmt = $pdo->prepare("INSERT INTO leads (lead_source, client_name, client_email, client_phone, company_name, selected_module, complexity_scale, technical_integrations, estimated_budget, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($leads as $lead) {
    $stmt->execute($lead);
}
echo "✓ Created " . count($leads) . " demo leads<br>";

echo "<br><h3>Seeding demo projects...</h3>";
$projects = [
    ['PRJ-001', 'School Management System', 'Dream Valley School', 'contact@dvschool.edu', 'Complete ERP with admission, accounts, library', 'in_progress', 'high', '2026-01-15', '2026-03-30', 150000],
    ['PRJ-002', 'Hospital Management', 'City Medical Center', 'admin@citymedicare.org', 'Patient records, billing, appointment', 'planning', 'medium', '2026-02-01', '2026-05-15', 200000],
    ['PRJ-003', 'POS System', 'Fashion House', 'owner@fashionhouse.bd', 'Retail POS with inventory', 'testing', 'high', '2026-01-01', '2026-02-28', 80000],
    ['PRJ-004', 'E-Commerce Platform', 'Bangladeshi Crafts', 'hello@bangladeshicrafts.com', 'Online store with payment gateway', 'completed', 'medium', '2025-11-01', '2026-01-15', 120000]
];

$stmt = $pdo->prepare("INSERT IGNORE INTO projects (project_code, project_name, client_name, client_email, description, status, priority, start_date, due_date, budget) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($projects as $proj) {
    $stmt->execute($proj);
}
echo "✓ Created " . count($projects) . " demo projects<br>";

echo "<br><h3>Seeding demo tasks...</h3>";
$tasks = [
    ['Review project proposal for hospital', 'Discuss requirements with client', '2026-03-24', 'pending', 'high', '09:00:00'],
    ['Prepare invoice for Fashion House', 'Finalize amounts and send', '2026-03-24', 'in_progress', 'medium', '11:00:00'],
    ['Team meeting - Sprint planning', 'Weekly team sync', '2026-03-25', 'pending', 'medium', '10:00:00'],
    ['Update documentation', 'User manual for school ERP', '2026-03-26', 'pending', 'low', null],
    ['Client call - Tech Solutions', 'Demo session', '2026-03-27', 'pending', 'high', '14:00:00']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO tasks (task_title, task_description, task_date, status, priority, due_time) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($tasks as $task) {
    $stmt->execute($task);
}
echo "✓ Created " . count($tasks) . " demo tasks<br>";

echo "<br><h3>Seeding demo invoices...</h3>";
$invoices = [
    ['INV-0001', '2026-03-01', '2026-03-31', 'Tech Solutions Ltd', 'contact@techsolutions.com', ' Dhaka, Bangladesh', '01512345678', 'School ERP - Phase 1', 100000, 0, 0, 0, 100000, 'paid'],
    ['INV-0002', '2026-03-10', '2026-03-25', 'City Medical Center', 'admin@citymedicare.org', 'Chittagong, Bangladesh', '01712345678', 'Hospital system deposit', 50000, 0, 5000, 0, 45000, 'sent'],
    ['INV-0003', '2026-03-15', '2026-04-15', 'Fashion House', 'owner@fashionhouse.bd', ' Dhaka, Bangladesh', '01812345678', 'POS System - Setup', 80000, 0, 8000, 0, 72000, 'draft']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO invoices (invoice_number, invoice_date, due_date, client_name, client_email, client_address, client_phone, notes, subtotal, tax_rate, tax_amount, discount_amount, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($invoices as $inv) {
    $stmt->execute($inv);
}
$invoiceId = $pdo->lastInsertId() - count($invoices) + 1;

$invoiceItems = [
    ['Software Development', 'Phase 1 Development', 1, 60000],
    ['System Setup', 'Server and database setup', 1, 25000],
    ['Training', 'Staff training session', 1, 15000]
];
$stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_name, item_description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($invoiceItems as $item) {
    $stmt->execute([$invoiceId, $item[0], $item[1], $item[2], $item[3], $item[2] * $item[3]]);
}
echo "✓ Created " . count($invoices) . " demo invoices<br>";

echo "<br><h3>Seeding demo quotations...</h3>";
$quotations = [
    ['QUO-0001', '2026-03-20', '2026-04-20', 'Global Corp', 'emily@enterprise.com', 'Complete ERP Solution', 250000, 'sent'],
    ['QUO-0002', '2026-03-22', '2026-04-22', 'HealthCare Plus', 'drkhan@clinic.com', 'Hospital Management', 85000, 'draft']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO quotations (quote_number, quote_date, valid_until, client_name, client_email, notes, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($quotations as $quo) {
    $stmt->execute($quo);
}
echo "✓ Created " . count($quotations) . " demo quotations<br>";

echo "<br><h3>Seeding demo job circulars...</h3>";
$jobs = [
    ['Senior PHP Developer', 'SohojWeb', 'Dhaka, Bangladesh', 'Full-time', '60000 - 80000 BDT', '3-5 years', 'BSc in CSE', 'We are looking for an experienced PHP developer...', 'Develop web applications', '3+ years PHP experience, MySQL, JavaScript', 'Competitive salary, Remote work, Health insurance', '2026-04-15', 'sohojweb.com@gmail.com', 'published'],
    ['Junior Web Designer', 'SohojWeb', 'Dhaka, Bangladesh', 'Full-time', '25000 - 40000 BDT', '1-2 years', 'Diploma in Design', 'Looking for a creative web designer...', 'Design websites', 'HTML, CSS, JavaScript, Figma', 'Friendly environment, Learning opportunities', '2026-04-30', 'sohojweb.com@gmail.com', 'published']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO job_circulars (job_title, company_name, location, employment_type, salary_range, experience_required, education_requirement, job_description, responsibilities, requirements, benefits, apply_deadline, contact_email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($jobs as $job) {
    $stmt->execute($job);
}
echo "✓ Created " . count($jobs) . " demo job circulars<br>";

echo "<br><h3>Seeding demo application forms...</h3>";
$applications = [
    ['Experience Certificate Request', 'experience', 'Rahim Ahmed', 'rahim@email.com', '01812345678', 'Development', '{"experience":"5 years in web development","education":"BSc in CSE","skills":"PHP, MySQL, JavaScript"}', 'pending'],
    ['Leave Application', 'leave', 'Fatema Begum', 'fatema@email.com', '01712345678', 'HR', '{"reason":"Medical emergency - need 5 days leave"}', 'approved']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO application_forms (form_title, form_type, applicant_name, applicant_email, applicant_phone, department, form_data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($applications as $app) {
    $stmt->execute($app);
}
echo "✓ Created " . count($applications) . " demo application forms<br>";

echo "<br><h3>Seeding site features...</h3>";
$features = [
    ['hero_title', 'Transform Your Business with Intelligent Software', 'text'],
    ['hero_subtitle', 'We develop highly modular, colorful, and responsive management software. Specializing in offline-capable applications tailored precisely for your organizational structure.', 'text'],
    ['cta_title', 'Ready to Transform Your Business?', 'text'],
    ['cta_subtitle', 'Get a free estimate for your project. Our team will analyze your requirements and provide a customized quote.', 'text'],
    ['about_title', 'About SohojWeb', 'text'],
    ['about_subtitle', 'We are a leading software development company specializing in custom web and mobile applications.', 'text'],
    ['about_mission', 'To empower businesses with innovative, efficient, and user-friendly software solutions that drive growth and streamline operations.', 'text'],
    ['about_vision', 'To become the most trusted technology partner for businesses seeking digital transformation across Bangladesh and beyond.', 'text']
];
$stmt = $pdo->prepare("INSERT IGNORE INTO site_features (feature_key, feature_value, feature_type) VALUES (?, ?, ?)");
foreach ($features as $f) {
    $stmt->execute($f);
}
echo "✓ Inserted site features<br>";

echo "<br><h3>Seeding site services...</h3>";
$services = [
    ['Education System', 'fas fa-graduation-cap', 'Tailored architecture focusing on institutional setup and daily workflow with Advanced Core Settings, Dynamic Routing & Routine, Admission Processing, Staff HR & Leave Tracking', 1, 1],
    ['Company HR/ERP', 'fas fa-users', 'Centralized control for corporate human resources and project assignments with Employee Dashboard, Automated Payroll, Task Allocation, Internal Communication', 2, 1],
    ['Shop / POS Node', 'fas fa-shopping-cart', 'Lightning-fast offline billing systems built for retail and wholesale with Offline Billing, Live Inventory Sync, Barcode Integration, Supplier Ledger', 3, 1],
    ['Hospital System', 'fas fa-hospital', 'Secure patient data handling and facility management for clinics with Digital Patient (EMR), Appointment Scheduling, Ward & Bed Allotment, Pharmacy POS', 4, 1],
    ['Business ERP', 'fas fa-building', 'Complete financial and operational overview for large enterprises with General Ledger, Supply Chain, CRM Module, Multi-Branch Sync', 5, 1],
    ['E-Commerce Platform', 'fas fa-store', 'A comprehensive e-commerce solution for online retail businesses with Product Catalog, Shopping Cart, Payment Gateway, Order Management', 6, 1]
];
$stmt = $pdo->prepare("INSERT IGNORE INTO site_services (service_title, service_icon, service_description, service_order, is_active) VALUES (?, ?, ?, ?, ?)");
foreach ($services as $s) {
    $stmt->execute($s);
}
echo "✓ Inserted " . count($services) . " site services<br>";

echo "<br><h3>Seeding site stats...</h3>";
$stats = [
    ['Years Experience', '5+', 'fas fa-clock', 1, 1],
    ['Projects Done', '100+', 'fas fa-project-diagram', 2, 1],
    ['Happy Clients', '50+', 'fas fa-smile', 3, 1]
];
$stmt = $pdo->prepare("INSERT IGNORE INTO site_stats (stat_label, stat_value, stat_icon, stat_order, is_active) VALUES (?, ?, ?, ?, ?)");
foreach ($stats as $s) {
    $stmt->execute($s);
}
echo "✓ Inserted " . count($stats) . " site stats<br>";

echo "<br><h3>Seeding why choose us items...</h3>";
$whyChoose = [
    ['Fast Delivery', 'We deliver projects on time without compromising quality.', 'fas fa-rocket', 1, 1],
    ['Secure Solutions', 'Enterprise-grade security for your business data.', 'fas fa-shield-alt', 2, 1],
    ['24/7 Support', 'Round-the-clock technical support for all clients.', 'fas fa-headset', 3, 1]
];
$stmt = $pdo->prepare("INSERT IGNORE INTO site_why_choose (title, description, icon, item_order, is_active) VALUES (?, ?, ?, ?, ?)");
foreach ($whyChoose as $w) {
    $stmt->execute($w);
}
echo "✓ Inserted " . count($whyChoose) . " why choose items<br>";

echo "<br><h3>Seeding contact info...</h3>";
$contactInfo = [
    ['address', 'Dhaka, Bangladesh', 'fas fa-map-marker-alt', 1],
    ['email', 'sohojweb.com@gmail.com', 'fas fa-envelope', 1],
    ['phone', '01632950179', 'fas fa-phone', 1],
    ['whatsapp', '8801632950179', 'fab fa-whatsapp', 1]
];
$stmt = $pdo->prepare("INSERT IGNORE INTO site_contact_info (info_type, info_value, info_icon, is_active) VALUES (?, ?, ?, ?)");
foreach ($contactInfo as $c) {
    $stmt->execute($c);
}
echo "✓ Inserted " . count($contactInfo) . " contact info items<br>";

echo "<br><h3>Seeding social links...</h3>";
$socialLinks = [
    ['facebook', 'https://www.facebook.com/share/1QGY2QMvkH/', 1],
    ['twitter', 'https://twitter.com/sohojweb', 0],
    ['linkedin', 'https://linkedin.com/company/sohojweb', 0],
    ['instagram', 'https://instagram.com/sohojweb', 0]
];
$stmt = $pdo->prepare("INSERT IGNORE INTO site_social_links (platform, profile_url, is_active) VALUES (?, ?, ?)");
foreach ($socialLinks as $s) {
    $stmt->execute($s);
}
echo "✓ Inserted " . count($socialLinks) . " social links<br>";

echo "<br><h3>Seeding refined Testimonials...</h3>";
$testimonials = [
    ['Arifur Rahman', 'CEO', 'TechFlow Solutions', 'SohojWeb transformed our manual processes into a seamless digital ecosystem. Their attention to detail and offline sync capabilities are unmatched.', 5],
    ['Sarah Jenkins', 'HR Director', 'Global Retail', 'The customized HR portal developed by SohojWeb has reduced our administrative workload by 40%. Highly recommended for corporate solutions.', 5],
    ['Dr. Mahbubul Alam', 'Managing Director', 'City Medicare', 'Reliable, secure, and intuitive. Their hospital management system is exactly what we needed for our multi-branch operations.', 4],
    ['Jasmine Akter', 'Proprietor', 'Fashionista BD', 'The POS system is lightning fast and works perfectly even during internet outages. Their support team is always available.', 5]
];
$stmt = $pdo->prepare("INSERT INTO site_testimonials (client_name, client_designation, client_company, testimonial_text, rating) VALUES (?, ?, ?, ?, ?)");
foreach ($testimonials as $t) { $stmt->execute($t); }
echo "✓ Created " . count($testimonials) . " testimonials<br>";

echo "<br><h3>Seeding refined Team Members...</h3>";
$team = [
    ['Tanvir Hasan', 'Founder & Lead Architect', '10+ years of experience in building scalable ERP systems and smart business ecosystems.', 'tanvir@sohojweb.com', 1],
    ['Nusrat Jahan', 'Senior Full-Stack Developer', 'Expert in PHP, Laravel, and React. Passionate about creating clean, modular code architectures.', 'nusrat@sohojweb.com', 2],
    ['Rahul Sharma', 'UI/UX Designer', 'Crafting beautiful, intuitive interfaces that enhance user experience and business conversion.', 'rahul@sohojweb.com', 3],
    ['Mehedi Hasan', 'Systems Engineer', 'Specializes in offline-first applications and robust database management for enterprise clients.', 'mehedi@sohojweb.com', 4]
];
$stmt = $pdo->prepare("INSERT INTO site_team (member_name, designation, bio, member_email, member_order) VALUES (?, ?, ?, ?, ?)");
foreach ($team as $m) { $stmt->execute($m); }
echo "✓ Created " . count($team) . " team members<br>";

echo "<br><h3>Seeding Job Applications & Offers...</h3>";
$applications = [
    [1, 'Kamal Hossain', 'kamal@example.com', '01700000001', 'resumes/demo_resume.pdf', 'I have 4 years of experience in PHP...', 'reviewing'],
    [2, 'Sultana Razia', 'sultana@example.com', '01800000002', 'resumes/demo_resume_2.pdf', 'Highly interested in the design role...', 'pending']
];
$stmt = $pdo->prepare("INSERT INTO job_applications (circular_id, applicant_name, applicant_email, applicant_phone, resume_path, cover_letter, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($applications as $app) { $stmt->execute($app); }

$offers = [
    ['OFF-0001', 'Kamal Hossain', 'kamal@example.com', '01700000001', 'Senior PHP Developer', 'Engineering', '2026-05-01', 75000, 'Full-time', 'sent']
];
$stmt = $pdo->prepare("INSERT INTO job_offer_letters (offer_number, candidate_name, candidate_email, candidate_phone, position, department, joining_date, salary, employment_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($offers as $off) { $stmt->execute($off); } 
echo "✓ Created demo applications and offers<br>";

echo "<br><h3>Seeding Project Tasks...</h3>";
$projTasks = [
    [1, 'Database Schema Design', 'Finalize tables for HR module', 'done', 'high', '2026-01-20'],
    [1, 'API Development', 'Build endpoints for mobile app sync', 'in_progress', 'medium', '2026-03-15'],
    [2, 'Hardware Procurement', 'Setup servers for hospital local node', 'todo', 'high', '2026-04-01']
];
$stmt = $pdo->prepare("INSERT INTO project_tasks (project_id, task_title, task_description, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($projTasks as $pt) { $stmt->execute($pt); }
echo "✓ Created project-specific tasks<br>";

echo "<br><br><div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
echo "<h2>✓ Database setup completed successfully!</h2>";
echo "<p><strong>Admin Login:</strong> admin@sohojweb.com / admin123</p>";
echo "<p><strong>Public Site:</strong> <a href='../'>http://localhost/sohojweb/</a></p>";
echo "<p><strong>Admin Panel:</strong> <a href='../admin/'>http://localhost/sohojweb/admin/</a></p>";
echo "</div>";
?>
