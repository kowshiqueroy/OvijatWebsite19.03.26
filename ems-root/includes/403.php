<?php
if (!defined('EMS_ROOT')) define('EMS_ROOT', dirname(__DIR__));
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access Denied — EMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center">
  <i class="bi bi-shield-lock-fill text-danger" style="font-size:5rem;"></i>
  <h2 class="mt-3 fw-bold">Access Denied</h2>
  <p class="text-muted">You don't have permission to view this page.</p>
  <a href="javascript:history.back()" class="btn btn-primary me-2">Go Back</a>
  <a href="<?= isset($MOD) ? $MOD : '' ?>dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
</div>
</body>
</html>
