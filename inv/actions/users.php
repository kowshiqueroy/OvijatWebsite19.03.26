<?php
/**
 * actions/users.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    requireRole('Admin');
    $username = sanitize($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $branch_id = (int)$_POST['branch_id'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, branch_id) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$username, $password, $role, $branch_id])) {
        auditLog($pdo, 'Add User', "Added user: $username with role: $role");
        jsonResponse('success', 'User added successfully.');
    } else {
        jsonResponse('error', 'Failed to add user. Username might already exist.');
    }
}

if ($action === 'toggle_status') {
    requireRole('Admin');
    $id = (int)$_POST['id'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    if ($stmt->execute([$status, $id])) {
        auditLog($pdo, 'Toggle User Status', "Changed user ID: $id status to $status");
        jsonResponse('success', 'User status updated.');
    } else {
        jsonResponse('error', 'Failed to update status.');
    }
}

if ($action === 'edit') {
    requireRole('Admin');
    $id = (int)$_POST['id'];
    $role = $_POST['role'];
    $branch_id = (int)$_POST['branch_id'];
    $status = $_POST['status'];
    $password = $_POST['password'];

    if (!empty($password)) {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET role = ?, branch_id = ?, status = ?, password = ? WHERE id = ?");
        $stmt->execute([$role, $branch_id, $status, $password, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, branch_id = ?, status = ? WHERE id = ?");
        $stmt->execute([$role, $branch_id, $status, $id]);
    }

    auditLog($pdo, 'Edit User', "Updated user ID: $id");
    jsonResponse('success', 'User updated successfully.');
}
?>
