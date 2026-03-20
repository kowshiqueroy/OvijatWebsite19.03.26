<?php
// admin/logout.php
require_once dirname(__DIR__) . '/config.php';
$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
