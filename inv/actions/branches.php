<?php
/**
 * actions/branches.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    requireRole('Admin');
    $name = sanitize($_POST['name']);
    $location = sanitize($_POST['location']);
    $contact = sanitize($_POST['contact']);

    if (empty($name)) {
        jsonResponse('error', 'Branch name is required.');
    }

    $stmt = $pdo->prepare("INSERT INTO branches (name, location, contact) VALUES (?, ?, ?)");
    if ($stmt->execute([$name, $location, $contact])) {
        auditLog($pdo, 'Add Branch', "Added branch: $name");
        jsonResponse('success', 'Branch added successfully.');
    } else {
        jsonResponse('error', 'Failed to add branch.');
    }
}

if ($action === 'edit') {
    requireRole('Admin');
    $id = (int)$_POST['id'];
    $name = sanitize($_POST['name']);
    $location = sanitize($_POST['location']);
    $contact = sanitize($_POST['contact']);

    $stmt = $pdo->prepare("UPDATE branches SET name = ?, location = ?, contact = ? WHERE id = ?");
    if ($stmt->execute([$name, $location, $contact, $id])) {
        auditLog($pdo, 'Edit Branch', "Updated branch ID: $id");
        jsonResponse('success', 'Branch updated successfully.');
    } else {
        jsonResponse('error', 'Failed to update branch.');
    }
}