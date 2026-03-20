<?php
$messages = [];

try {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'pbx_manager';

    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $messages[] = ["success", "Connected to MySQL"];

    if ($conn->query("DROP DATABASE IF EXISTS $dbname")) {
        $messages[] = ["info", "Dropped existing database"];
    }

    if (!$conn->query("CREATE DATABASE $dbname")) {
        throw new Exception("Failed to create database");
    }
    $messages[] = ["success", "Created database: $dbname"];
    $conn->select_db($dbname);

    $sql = "
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'agent', 'staff') DEFAULT 'agent',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        extension VARCHAR(20),
        phone_number VARCHAR(20),
        department VARCHAR(100),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );

    CREATE TABLE contact_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#6366f1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE contact_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#6366f1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#6366f1',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE call_tags (
        call_id INT NOT NULL,
        tag_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (call_id, tag_id)
    );

    CREATE TABLE personal_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        person_id INT DEFAULT NULL,
        content TEXT NOT NULL,
        is_pinned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    CREATE TABLE persons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) NOT NULL,
        name VARCHAR(150),
        type VARCHAR(100) DEFAULT '',
        group_id INT DEFAULT 0,
        company VARCHAR(200),
        email VARCHAR(150),
        address VARCHAR(500),
        internal_external ENUM('internal', 'external') DEFAULT 'external',
        notes TEXT,
        is_favorite TINYINT(1) DEFAULT 0,
        assigned_to INT DEFAULT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_phone (phone)
    );

    CREATE TABLE calls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pbx_id VARCHAR(100) UNIQUE,
        caller_number VARCHAR(50),
        caller_name VARCHAR(150),
        destination VARCHAR(50),
        extension VARCHAR(20),
        direction VARCHAR(20),
        status VARCHAR(30),
        duration VARCHAR(20),
        talk_time INT DEFAULT 0,
        recording_url VARCHAR(500),
        drive_link VARCHAR(500),
        start_time DATETIME,
        caller_destination VARCHAR(50),
        codecs VARCHAR(50),
        tta VARCHAR(20),
        pdd VARCHAR(20),
        call_data JSON,
        call_mark ENUM('successful', 'problem', 'need_action', 'urgent', 'failed') DEFAULT NULL,
        agent_id INT,
        person_id INT,
        is_manual TINYINT(1) DEFAULT 0,
        fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id),
        FOREIGN KEY (person_id) REFERENCES persons(id),
        INDEX idx_phone (caller_number),
        INDEX idx_start_time (start_time)
    );

    CREATE TABLE logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        call_id INT,
        person_id INT,
        agent_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        type ENUM('note', 'issue', 'followup', 'resolution', 'feedback', 'query', 'reply') DEFAULT 'note',
        log_status ENUM('open', 'closed', 'followup', 'pending') DEFAULT 'open',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'low',
        category VARCHAR(100),
        notes TEXT NOT NULL,
        drive_link VARCHAR(500),
        is_locked TINYINT(1) DEFAULT 0,
        locked_by INT,
        locked_at DATETIME,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (call_id) REFERENCES calls(id),
        FOREIGN KEY (person_id) REFERENCES persons(id),
        FOREIGN KEY (agent_id) REFERENCES agents(id)
    );

    CREATE TABLE tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        person_id INT,
        call_id INT,
        assigned_to INT,
        assigned_by INT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        due_date DATETIME,
        completed_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (person_id) REFERENCES persons(id),
        FOREIGN KEY (assigned_to) REFERENCES agents(id),
        FOREIGN KEY (assigned_by) REFERENCES agents(id)
    );

    CREATE TABLE faqs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(500) NOT NULL,
        answer TEXT NOT NULL,
        category VARCHAR(100),
        tags VARCHAR(255),
        usage_count INT DEFAULT 0,
        created_by INT,
        log_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES agents(id),
        FOREIGN KEY (log_id) REFERENCES logs(id)
    );

    CREATE TABLE edit_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_name VARCHAR(50) NOT NULL,
        record_id INT NOT NULL,
        field_name VARCHAR(100),
        old_value TEXT,
        new_value TEXT,
        edited_by INT NOT NULL,
        edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (edited_by) REFERENCES users(id)
    );

    CREATE TABLE activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        link VARCHAR(255),
        is_pinned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id)
    );

    CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255),
        message TEXT,
        type VARCHAR(50),
        link VARCHAR(255),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    ";

    if (!$conn->multi_query($sql)) {
        throw new Exception("Failed to create tables: " . $conn->error);
    }
    do { $conn->store_result(); } while ($conn->more_results() && $conn->next_result());
    $messages[] = ["success", "Created all tables"];

    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES 
        ('pbx_username', ''), 
        ('pbx_password', ''), 
        ('company_name', 'Ovijat Group'),
        ('auto_fetch', '0')");

    $adminPassword = 'admin123';
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $stmt->bind_param("ss", $adminUser, $adminPassword);
    $adminUser = 'admin';
    $stmt->execute();
    $messages[] = ["success", "Admin: admin / admin123"];

    $agentData = [
        ['name' => 'Rahim Ahmed', 'ext' => '101', 'phone' => '01712345678', 'dept' => 'Sales'],
        ['name' => 'Karim Hussain', 'ext' => '102', 'phone' => '01712345679', 'dept' => 'Support'],
        ['name' => 'Hasan Ali', 'ext' => '103', 'phone' => '01712345680', 'dept' => 'Sales'],
    ];
    foreach ($agentData as $a) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'agent')");
        $username = strtolower(str_replace(' ', '', $a['name']));
        $stmt->bind_param("ss", $username, $pass);
        $pass = 'agent123';
        $stmt->execute();
        $userId = $conn->insert_id;
        $stmt = $conn->prepare("INSERT INTO agents (user_id, name, extension, phone_number, department) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $a['name'], $a['ext'], $a['phone'], $a['dept']);
        $stmt->execute();
    }
    $messages[] = ["success", "Agents: rahimahmed, karimhussain, hasanali / agent123"];

    $conn->query("INSERT INTO contact_groups (name, color) VALUES 
        ('VIP Customers', '#10b981'),
        ('Hot Leads', '#f59e0b'),
        ('Support Queue', '#ef4444'),
        ('Sales Followup', '#6366f1'),
        ('Resolved', '#6b7280')");
    $messages[] = ["info", "Created contact groups"];

    $conn->query("INSERT INTO contact_types (name, color) VALUES 
        ('Customer', '#10b981'),
        ('Staff', '#6366f1'),
        ('Vendor', '#f59e0b'),
        ('Sales', '#8b5cf6'),
        ('Lead', '#ec4899'),
        ('Partner', '#14b8a6'),
        ('Other', '#6b7280')");
    $messages[] = ["info", "Created contact types"];

    $conn->query("INSERT INTO tags (name, color) VALUES 
        ('Billing', '#ef4444'),
        ('Sales', '#10b981'),
        ('Support', '#6366f1'),
        ('Complaint', '#f59e0b'),
        ('Inquiry', '#8b5cf6'),
        ('Follow-up', '#14b8a6')");
    $messages[] = ["info", "Created call tags"];

    $persons = [
        ['phone' => '01912345678', 'name' => 'Rahman Enterprise', 'type' => 'customer', 'company' => 'Rahman Group', 'group' => 1],
        ['phone' => '01812345678', 'name' => 'Islam Trading', 'type' => 'customer', 'company' => 'Islam Corp', 'group' => 2],
        ['phone' => '01612345678', 'name' => 'Karim Steel', 'type' => 'customer', 'company' => 'Karim Steel Ltd', 'group' => 3],
        ['phone' => '01512345678', 'name' => 'Sales Team', 'type' => 'staff', 'company' => 'Ovijat Group', 'group' => null],
        ['phone' => '01412345678', 'name' => 'Support Team', 'type' => 'staff', 'company' => 'Ovijat Group', 'group' => null],
    ];
    foreach ($persons as $p) {
        $stmt = $conn->prepare("INSERT INTO persons (phone, name, type, company, group_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $p['phone'], $p['name'], $p['type'], $p['company'], $p['group']);
        $stmt->execute();
    }
    $messages[] = ["info", "Created sample contacts"];

    $faqs = [
        ['q' => 'How to check balance?', 'a' => 'Dial *111# or use mobile app', 'cat' => 'Balance'],
        ['q' => 'Payment not received?', 'a' => 'Check gateway. Ask for transaction ID. Escalate if >24hrs.', 'cat' => 'Payment'],
        ['q' => 'Delivery delayed?', 'a' => 'Check tracking. Provide new ETA. Apologize.', 'cat' => 'Delivery'],
        ['q' => 'Product damaged?', 'a' => 'Note order ID. Initiate replacement. Escalate to QC.', 'cat' => 'Product'],
        ['q' => 'Refund process?', 'a' => 'Verify order. Check return policy. Process within 7 days.', 'cat' => 'Refund'],
    ];
    foreach ($faqs as $f) {
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, category) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $f['q'], $f['a'], $f['cat']);
        $stmt->execute();
    }
    $messages[] = ["info", "Created sample FAQs"];

    $conn->close();
    $messages[] = ["success", "Setup complete! Delete setup.php from production."];

} catch (Exception $e) {
    $messages[] = ["danger", $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PBX Manager Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/dark.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header text-white">
                        <h4 class="mb-0"><i class="fas fa-headset me-2"></i>Ovijat Call Center - Setup</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $m): ?>
                            <div class="alert alert-<?= $m[0] ?> py-2">
                                <i class="fas fa-<?= $m[0]=='success'?'check-circle text-success':($m[0]=='danger'?'times-circle text-danger':'info-circle text-info') ?> me-2"></i>
                                <?= $m[1] ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php $ok = true; foreach ($messages as $m) if ($m[0]=='danger') $ok = false; ?>
                        <?php if ($ok && count($messages)): ?>
                            <hr>
                            <div class="text-center mt-4">
                                <a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt me-2"></i>Go to Login</a>
                            </div>
                            <div class="alert alert-warning mt-4 mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Delete <code>setup.php</code> from production!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
