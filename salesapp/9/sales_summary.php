<?php
/* Redirect to detailed_report.php preserving all query params */
$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: detailed_report.php$qs");
exit;
