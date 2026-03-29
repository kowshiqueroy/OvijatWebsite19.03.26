<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
