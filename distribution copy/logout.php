<?php
require_once 'config/config.php';
session_start();
session_destroy();
header("Location: login.php");
exit;
?>
