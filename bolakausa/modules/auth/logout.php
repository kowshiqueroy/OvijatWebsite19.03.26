<?php
/**
 * Logout Module
 */

session_start();
session_destroy();
header('Location: /bolakausa/login');
exit;
