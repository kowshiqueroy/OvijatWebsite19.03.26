<?php
require_once 'config.php';
if (isLoggedIn()) {
    logActivity('logout', 'agents', agentId(), 'Agent logged out');
}
session_destroy();
header('Location: ' . APP_URL . '/login.php?msg=logged_out');
exit;
