<?php
// ============================================================
// includes/bootstrap.php — Loaded first on every request
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

startSession();
