<?php
/**
 * actions/finance.php
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? '';

if ($action === 'add_expense') {
    requireRole(['Admin', 'Accountant']);
    
    $expense_date = sanitize($_POST['expense_date']);
    $category = sanitize($_POST['category']);
    $amount = (float)$_POST['amount'];
    $description = sanitize($_POST['description']);
    $branch_id = $_SESSION['branch_id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO expenses (branch_id, category, amount, description, expense_date, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$branch_id, $category, $amount, $description, $expense_date, $user_id])) {
        auditLog($pdo, 'Add Expense', "Recorded expense: $category ($amount)");
        jsonResponse('success', 'Expense recorded successfully.');
    } else {
        jsonResponse('error', 'Failed to record expense.');
    }
}

if ($action === 'delete_expense') {
    requireRole(['Admin', 'Accountant']);
    $id = (int)$_POST['id'];
    $branch_id = $_SESSION['branch_id'];

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND branch_id = ?");
    if ($stmt->execute([$id, $branch_id])) {
        auditLog($pdo, 'Delete Expense', "Deleted expense record ID: $id");
        jsonResponse('success', 'Expense record deleted successfully.');
    } else {
        jsonResponse('error', 'Failed to delete expense record.');
    }
}
?>
