<?php
// ============================================================
// admin/download_template_peserta_csv.php — Download Template Import Peserta (.csv)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
requireLogin('admin_kecamatan');

$filename = "template_import_peserta.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM untuk Excel agar deteksi UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header sesuai format import
$header = ['nama', 'kelas'];
fputcsv($output, $header);

// Contoh baris
fputcsv($output, ['Budi Santoso', '7A']);
fputcsv($output, ['Ani Wijaya', '7B']);
fputcsv($output, ['Citra Lestari', '8C']);

fclose($output);
exit;
