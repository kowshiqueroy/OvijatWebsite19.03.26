<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!hasPermission('super_admin')) {
    die('Access denied');
}

$tables = ['users', 'site_settings', 'leads', 'projects', 'tasks', 'project_tasks', 'invoices', 'invoice_items', 'quotations', 'quotation_items', 'job_circulars', 'job_applications', 'job_offer_letters', 'application_forms', 'custom_documents', 'audit_logs', 'site_features', 'site_services', 'site_stats', 'site_testimonials', 'site_team', 'site_why_choose', 'site_contact_info', 'site_social_links'];

$sql = "-- Database Backup - SohojWeb\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
$pdo = db()->getConnection();

foreach ($tables as $table) {
    $rows = db()->select("SELECT * FROM $table");
    if (!empty($rows)) {
        $sql .= "\n\n-- Table: $table\n";
        $sql .= "TRUNCATE TABLE $table;\n\n";

        foreach ($rows as $row) {
            $columns = array_keys($row);
            $values = array_map(function($v) use ($pdo) {
                if (is_null($v)) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            
            $sql .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
    }
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="sohojweb_backup_' . date('Y-m-d') . '.sql"');
header('Content-Length: ' . strlen($sql));
echo $sql;
exit;
