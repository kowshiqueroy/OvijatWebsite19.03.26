<?php
session_name('EMS_SESS');
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;
