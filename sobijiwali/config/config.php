<?php
/**
 * Global Configuration for sobjiwali.com
 * Handles DB credentials and core site constants.
 */

// Site Identity
define('SITE_NAME', 'Sobjiwali');
define('SITE_URL', 'http://localhost/sobijiwali'); // Update this for production
define('ADMIN_PATH', 'admin_stealth_zone');

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'sobjiwali_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security
define('AUTH_SALT', 'c48d2f7e8a9b1c2d3e4f5g6h7i8j9k0l'); // Change this!

// Session Security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
session_start();

// Stripe Configuration (Test Mode)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_placeholder');
define('STRIPE_SECRET_KEY', 'sk_test_placeholder');
define('STRIPE_CURRENCY', 'usd');

// Bank Details for Manual Payment
define('BANK_NAME', 'Global Fresh Bank');
define('BANK_ACCOUNT_NAME', 'Sobjiwali PVT LTD');
define('BANK_ACCOUNT_NUMBER', '1234-5678-9012');
define('BANK_ROUTING', '987654321');
define('BANK_INSTRUCTIONS', 'Please include your Order ID as the reference.');
