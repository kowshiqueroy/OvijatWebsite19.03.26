<?php
/**
 * index.php
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('modules/dashboard.php');
} else {
    redirect('login.php');
}
?>
