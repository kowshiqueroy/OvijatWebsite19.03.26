<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config/database.php';

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    ob_clean();
    jsonResponse(['success' => false, 'message' => 'Security token mismatch. Please refresh the page.'], 403);
}

$leadSource = $_POST['lead_source'] ?? 'contact_form';
$client_name = $_POST['client_name'] ?? '';
$company_name = $_POST['company_name'] ?? '';
$client_email = $_POST['client_email'] ?? '';
$client_phone = $_POST['client_phone'] ?? '';
$message = $_POST['message'] ?? '';

if (empty($client_name) || empty($client_email)) {
    jsonResponse(['success' => false, 'message' => 'Name and email are required']);
}

if ($leadSource === 'investment_estimator') {
    $module = $_POST['module'] ?? '';
    $complexity = (int)($_POST['complexity'] ?? 1);
    $integrations = $_POST['integrations'] ?? [];
    
    $basePrices = [
        'Education System' => 50000,
        'Offline Shop / POS System' => 40000,
        'Company HR/ERP Portal' => 60000,
        'Hospital Management System' => 70000,
        'Enterprise Business ERP' => 100000,
        'E-Commerce Platform' => 45000
    ];
    
    $basePrice = $basePrices[$module] ?? 50000;
    $complexityMultiplier = [1 => 1, 2 => 1.3, 3 => 1.6, 4 => 2, 5 => 2.5][$complexity] ?? 1;
    $integrationCost = count($integrations) * 8000;
    $estimatedBudget = ($basePrice * $complexityMultiplier) + $integrationCost;
    
    try {
        ob_clean();
        db()->insert('leads', [
            'lead_source' => 'investment_estimator',
            'client_name' => sanitize($client_name),
            'client_email' => sanitize($client_email),
            'client_phone' => sanitize($client_phone),
            'company_name' => sanitize($company_name),
            'selected_module' => sanitize($module),
            'complexity_scale' => $complexity,
            'technical_integrations' => sanitize(implode(', ', $integrations)),
            'estimated_budget' => $estimatedBudget,
            'message' => sanitize($message),
            'status' => 'new'
        ]);
        
        jsonResponse(['success' => true, 'estimate' => number_format($estimatedBudget, 0, '.', ',')]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    try {
        ob_clean();
        db()->insert('leads', [
            'lead_source' => $leadSource,
            'client_name' => sanitize($client_name),
            'client_email' => sanitize($client_email),
            'client_phone' => sanitize($client_phone),
            'company_name' => sanitize($company_name),
            'message' => sanitize($message),
            'status' => 'new'
        ]);
        
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}
