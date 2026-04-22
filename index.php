<?php
// ============================================================
// index.php — Entry point, redirect sesuai role dari session
// ============================================================
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

// Redirect ke dashboard yang sesuai role
redirect(dashboardUrlByRole($_SESSION['role']));
