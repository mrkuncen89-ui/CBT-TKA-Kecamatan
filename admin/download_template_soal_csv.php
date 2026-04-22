<?php
// ============================================================
// admin/download_template_soal_csv.php — Download Template Import Soal (.csv)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
requireLogin('admin_kecamatan');

$filename = "template_import_soal.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM untuk Excel agar deteksi UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header sesuai format import
$header = ['kategori', 'teks_bacaan', 'pertanyaan', 'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d', 'jawaban_benar', 'tipe_soal'];
fputcsv($output, $header);

// Contoh baris
fputcsv($output, ['Matematika', '', 'Berapakah 1 + 1?', '1', '2', '3', '4', 'b', 'pg']);
fputcsv($output, ['IPA', 'Teks bacaan tentang mamalia...', 'Hewan yang menyusui disebut...', 'Reptilia', 'Mamalia', 'Aves', 'Amfibi', 'b', 'pg']);
fputcsv($output, ['Umum', '', 'Ibu kota Indonesia adalah Jakarta.', 'Benar', 'Salah', '', '', 'benar', 'bs']);

fclose($output);
exit;
