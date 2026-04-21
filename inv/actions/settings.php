<?php
/**
 * actions/settings.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'update') {
    requireRole('Admin');
    $settings = $_POST['settings'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        $pdo->commit();
        auditLog($pdo, 'Update Settings', "Updated system configuration settings");
        jsonResponse('success', 'Settings updated successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', $e->getMessage());
    }
}
?>
