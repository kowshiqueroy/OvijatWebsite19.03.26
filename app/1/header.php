<?php
require_once '../config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
if(isset($_SESSION['role']) && ($_SESSION['role'] != 3 && $_SESSION['role'] != 1)) {
    header("Location: ".$_SESSION['role']);
    exit;
 
}
// --- MENU DATA ---
$menuItems = [
    ['icon' => 'fa-house', 'label' => 'Home', 'link' => 'index.php'],
    ['icon' => 'fa-clipboard', 'label' => 'Orders', 'link' => 'orders.php'],
    ['icon' => 'fa-truck', 'label' => 'Serial', 'link' => 'serials.php'],
    ['icon' => 'fa-store', 'label' => 'Shops', 'link' => 'shops.php'],
    ['icon' => 'fa-money-bill', 'label' => 'Cash', 'link' => 'cash.php'],
    ['icon' => 'fa-map', 'label' => 'Routes', 'link' => 'routes.php'],
    ['icon' => 'fa-box', 'label' => 'Items', 'link' => 'items.php'],
    ['icon' => 'fa-question', 'label' => 'Return', 'link' => 'return_orders.php'],
         ['icon' => 'fa-box', 'label' => 'Net Sale', 'link' => 'sales_summary.php'],
    ['icon' => 'fa-gift', 'label' => 'Others', 'link' => 'others.php'],
    ['icon' => 'fa-users', 'label' => 'Users', 'link' => 'users.php'],
    ['icon' => 'fa-right-from-bracket', 'label' => 'Logout', 'link' => '../logout.php'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?=APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
 <div class="loading-logo">
        <img src="https://www.ovijatfood.com/images/logo.png" alt="Loading Logo">
    </div>
    
    <style>
       .loading-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            width: 100%;
            height: 100vh;
            top: 0;
            z-index: 99999999;
            opacity: 0.5;
        }
        .loading-logo img {
            width: 300px;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
    </style>

   <script>
        var showTime = 500;
        var startTime = new Date().getTime();
        $(window).on('load', function() {
            var endTime = new Date().getTime();
            var timeElapsed = endTime - startTime;
            if(timeElapsed < showTime) {
                var timeLeft = showTime - timeElapsed;
                setTimeout(function() {
                    $(".loading-logo").fadeOut(200);
                }, timeLeft);
            } else {
                $(".loading-logo").fadeOut(200);
            }
        });
    </script>
    <header>
        <div class="app-name"><i class="fa-solid fa-building" style="color:var(--warning)"></i> <?php echo APP_NAME; ?> <span style="color:var(--primary)"> App</span></div>
        <div class="user-info">
            <span class="user-company"><i class="fa-regular fa-building"></i> <?php echo APP_NAME; ?></span>
            <span class="user-role"> <i class="fa-solid fa-user-tag"></i> <?php echo $_SESSION['role']; ?></span>
            <span class="user-handle"><i class="fa-regular fa-user"></i><?php echo $_SESSION['user_id']; ?>@<?php echo $_SESSION['username']; ?></span>
        </div>
    </header>