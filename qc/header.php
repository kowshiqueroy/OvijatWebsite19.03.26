<?php
include_once 'config.php'; // Include your database connection and configuration file
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-menu"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </div>
        <div class="website-name" onClick="window.location.href='index.php'">
            <h1><?php echo $company_name; ?></h1>
        </div>
        <div class="logout-button">
            <button onclick="window.location.href='logout.php'">Logout</button>
        </div>
    </header>
    <nav class="sidebar">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="damages.php">Damages</a></li>
            <li><a href="analytics.php">Analytics</a></li>
            <li><a href="shops.php">Shops</a></li>
            <li><a href="summary.php">Summary</a></li>

        </ul>
    </nav>
   