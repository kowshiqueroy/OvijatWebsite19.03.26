<?php
/**
 * Index Page - Entry Point
 * Core PHP Employee Management System
 */

session_start();

if (is_dir('admin') && file_exists('admin/login.php')) {
    header('Location: admin/login.php');
    exit;
} else {
    header('Location: public/profile.php');
    exit;
}
