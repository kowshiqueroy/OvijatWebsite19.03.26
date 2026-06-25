<?php
/* Viewer role does not have user management — redirect to dashboard */
require_once '../config.php';
header("Location: index.php");
exit;
