<?php
/**
 * includes/header.php
 */
require_once 'config.php';
require_once 'functions.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'Inventory System'; ?></title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-light">

<div class="wrapper">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-primary">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3 d-none d-md-inline text-muted">
                        <?php if (hasRole(['Admin', 'Viewer'])): ?>
                        <div class="dropdown d-inline">
                            <a class="text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-code-branch me-1"></i> <?php echo $_SESSION['branch_name'] ?? 'Main Branch'; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php
                                $isAdminOrViewer = hasRole('Admin') || hasRole('Viewer');
                                $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE is_deleted = 0" . (!$isAdminOrViewer ? " AND id = ?" : "") . " ORDER BY name");
                                if (!$isAdminOrViewer) $stmt->execute([$_SESSION['branch_id']]);
                                else $stmt->execute();
                                while ($b = $stmt->fetch()): ?>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="window.location='<?php echo BASE_URL; ?>actions/auth.php?action=switch_branch&branch_id=<?php echo $b['id']; ?>'"><?php echo htmlspecialchars($b['name']); ?></a></li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <i class="fas fa-code-branch me-1"></i> <?php echo $_SESSION['branch_name'] ?? 'Main Branch'; ?>
                        <?php endif; ?>
                    </span>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>modules/users/profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>actions/auth.php?action=logout"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid">
