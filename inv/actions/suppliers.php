<?php
/**
 * actions/suppliers.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    requireRole(['Admin', 'Accountant', 'Manager']);
    $name = sanitize($_POST['name']);
    $contact_person = sanitize($_POST['contact_person']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, address, branch_id) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $contact_person, $phone, $address, $branch_id])) {
        auditLog($pdo, 'Add Supplier', "Added supplier: $name");
        jsonResponse('success', 'Supplier added successfully.');
    } else {
        jsonResponse('error', 'Failed to add supplier.');
    }
}

if ($action === 'edit') {
    requireRole(['Admin', 'Accountant', 'Manager']);
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    $contact_person = sanitize($_POST['contact_person']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, address = ? WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$name, $contact_person, $phone, $address, $id, $branch_id])) {
        auditLog($pdo, 'Edit Supplier', "Updated supplier ID: $id");
        jsonResponse('success', 'Supplier updated successfully.');
    } else {
        jsonResponse('error', 'Failed to update supplier.');
    }
}

if ($action === 'delete') {
    requireRole(['Admin', 'Accountant']);
    $id = (int)$_POST['id'];
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("UPDATE suppliers SET is_deleted = 1 WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$id, $branch_id])) {
        auditLog($pdo, 'Delete Supplier', "Soft deleted supplier ID: $id");
        jsonResponse('success', 'Supplier deleted successfully.');
    } else {
        jsonResponse('error', 'Failed to delete supplier.');
    }
}
?>
