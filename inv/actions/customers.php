<?php
/**
 * actions/customers.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    requireRole(['Admin', 'Accountant', 'Manager']);
    $name = sanitize($_POST['name']);
    $type = sanitize($_POST['type']);
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("INSERT INTO customers (name, type, balance, branch_id) VALUES (?, ?, 0, ?)");
    if ($stmt->execute([$name, $type, $branch_id])) {
        auditLog($pdo, 'Add Customer', "Added customer: $name");
        jsonResponse('success', 'Customer added successfully.');
    } else {
        jsonResponse('error', 'Failed to add customer.');
    }
}

if ($action === 'edit') {
    requireRole(['Admin', 'Accountant']);
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    $type = sanitize($_POST['type']);
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("UPDATE customers SET name = ?, type = ? WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$name, $type, $id, $branch_id])) {
        auditLog($pdo, 'Edit Customer', "Updated customer ID: $id");
        jsonResponse('success', 'Customer updated successfully.');
    } else {
        jsonResponse('error', 'Failed to update customer.');
    }
}

if ($action === 'delete') {
    requireRole(['Admin', 'Accountant']);
    $id = (int)$_POST['id'];
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("UPDATE customers SET is_deleted = 1 WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$id, $branch_id])) {
        auditLog($pdo, 'Delete Customer', "Soft deleted customer ID: $id");
        jsonResponse('success', 'Customer deleted successfully.');
    } else {
        jsonResponse('error', 'Failed to delete customer.');
    }
}
?>
