<?php
require_once 'config.php';
header('Location: ' . APP_URL . (isLoggedIn() ? '/dashboard.php' : '/login.php'));
exit;
