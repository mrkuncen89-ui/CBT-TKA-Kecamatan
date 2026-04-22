<?php
// ============================================================
// logout.php — Proses logout dan hapus session
// ============================================================
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';

// Log sebelum session dihapus
logActivity($conn, 'Logout', 'Keluar dari sistem');

logout(); // redirect ke login.php?logout=1 sudah ada di dalam fungsi
