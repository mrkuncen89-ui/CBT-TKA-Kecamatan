<?php
// ============================================================
// admin/download_template_soal.php — Download Template Import Soal (.xlsx)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
requireLogin('admin_kecamatan');

$file = __DIR__ . '/../assets/template_import_soal.xlsx';

if (!file_exists($file)) {
    http_response_code(404);
    die('File template tidak ditemukan. Hubungi administrator.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_import_soal.xlsx"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache, must-revalidate');
readfile($file);
exit;
