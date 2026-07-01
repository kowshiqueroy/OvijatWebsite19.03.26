<?php
declare(strict_types=1);
session_start();

define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . '/data/questionmaker2.sqlite');
define('UPLOAD_DIR', APP_ROOT . '/uploads');

$__docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$__appRoot = str_replace('\\', '/', APP_ROOT);
define('BASE_URL', strpos($__appRoot, $__docRoot) === 0 ? substr($__appRoot, strlen($__docRoot)) : '/question');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Dhaka');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
