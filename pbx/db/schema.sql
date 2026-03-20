-- PBX Call Manager Database Schema

CREATE DATABASE IF NOT EXISTS pbx_manager;
USE pbx_manager;

-- Users table (admin and agents)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'agent') NOT NULL DEFAULT 'agent',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Agents table (links to users, stores extension and phone)
CREATE TABLE agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    extension VARCHAR(20),
    phone_number VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Persons table (contacts - staff/customer/other)
CREATE TABLE persons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) UNIQUE NOT NULL,
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Contact groups table
CREATE TABLE contact_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#6366f1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact types table
CREATE TABLE contact_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#6366f1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tags table (admin managed call tags)
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#6366f1',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Call tags (many-to-many)
CREATE TABLE call_tags (
    call_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (call_id, tag_id),
    FOREIGN KEY (call_id) REFERENCES calls(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
);

-- Personal notes (agent private notes)
CREATE TABLE personal_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    person_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (person_id) REFERENCES persons(id)
);

-- Calls table (PBX data)
CREATE TABLE calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pbx_id VARCHAR(100) UNIQUE NOT NULL,
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
    INDEX idx_extension (extension),
    INDEX idx_start_time (start_time)
);

-- Logs table (agent notes per call/person)
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    call_id INT,
    person_id INT,
    agent_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    type ENUM('note', 'issue', 'followup', 'resolution', 'feedback', 'query', 'reply') DEFAULT 'note',
    log_status ENUM('open', 'closed', 'followup', 'pending') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'low',
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

-- Edit history (tracks all changes)
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

-- Activity log (tracks agent activities)
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

-- Settings table (stores PBX credentials, etc)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES ('pbx_username', '');
INSERT INTO settings (setting_key, setting_value) VALUES ('pbx_password', '');

-- Insert default admin
INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin');

-- Insert sample agents
INSERT INTO agents (user_id, name, extension, phone_number) VALUES 
(1, 'Agent 1', '101', '01712345678'),
(1, 'Agent 2', '102', '01712345679');
