<?php
// debug_baseurl.php — DELETE after use!
// Visit http://localhost/ovijat/debug_baseurl.php to verify BASE_URL
require_once 'config.php';
echo '<pre>';
echo 'BASE_URL      : ' . BASE_URL . "\n";
echo 'DOCUMENT_ROOT : ' . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo '__DIR__        : ' . __DIR__ . "\n";
echo 'HTTP_HOST     : ' . $_SERVER['HTTP_HOST'] . "\n";
echo '</pre>';
echo '<p>If BASE_URL looks correct, delete this file.</p>';
