<?php
// ============================================================
// admin/export_soal.php — Export Bank Soal ke XLSX
// Format output = format import (9 kolom):
//   kategori | teks_bacaan | pertanyaan | pilihan_a | pilihan_b
//   pilihan_c | pilihan_d | jawaban_benar | tipe_soal
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterKat = (int)($_GET['kategori_id'] ?? 0);
$where = $filterKat ? "WHERE s.kategori_id = $filterKat" : '';

$res = $conn->query("
    SELECT k.nama_kategori,
           s.teks_bacaan, s.pertanyaan,
           s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d,
           s.jawaban_benar, s.tipe_soal
    FROM soal s
    JOIN kategori_soal k ON k.id = s.kategori_id
    $where
    ORDER BY k.nama_kategori, s.id
");

if (!$res || $res->num_rows === 0) {
    setFlash('warning', 'Tidak ada soal untuk diekspor.');
    redirect(BASE_URL . '/admin/soal.php');
}

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

// ── Bangun XLSX manual pakai ZipArchive ──────────────────────
// Format: Office Open XML SpreadsheetML (tidak butuh library)

if (!class_exists('\ZipArchive') && !extension_loaded('zip')) {
    // Fallback ke CSV jika ZipArchive benar-benar tidak terdeteksi
    $filename = 'soal_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // BOM untuk Excel agar deteksi UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // PESAN DIAGNOSTIK (Akan muncul di baris pertama Excel)
    fputcsv($output, ["--- PERINGATAN SISTEM ---"]);
    fputcsv($output, ["Website mendeteksi ekstensi 'zip' BELUM AKTIF di PHP versi: " . phpversion()]);
    fputcsv($output, ["Sistem terpaksa menggunakan format CSV. Untuk XLSX, aktifkan ekstensi zip dan RESTART Laragon."]);
    fputcsv($output, ["-------------------------"]);
    fputcsv($output, []); // Baris kosong
    
    $header = ['kategori', 'teks_bacaan', 'pertanyaan', 'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d', 'jawaban_benar', 'tipe_soal'];
    fputcsv($output, $header);
    
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['nama_kategori'],
            $r['teks_bacaan']  ?? '',
            $r['pertanyaan'],
            $r['pilihan_a']    ?? '',
            $r['pilihan_b']    ?? '',
            $r['pilihan_c']    ?? '',
            $r['pilihan_d']    ?? '',
            $r['jawaban_benar'],
            $r['tipe_soal'],
        ]);
    }
    fclose($output);
    exit;
}

function xlsxEsc(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function buildXlsx(array $header, array $data): string {
    // [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

    // xl/_rels/workbook.xml.rels
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

    // xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Soal" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

    // xl/styles.xml — style 0=normal, style 1=header (bold)
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts>
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills>
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>
  </fills>
  <borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1">
      <alignment wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0">
      <alignment wrapText="1"/>
    </xf>
  </cellXfs>
</styleSheet>';

    // xl/worksheets/sheet1.xml
    $cols = count($header);
    $colLetters = ['A','B','C','D','E','F','G','H','I','J'];

    $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    // Lebar kolom: A=20, B=30, C=50, D-G=30, H=15, I=10
    $sheetXml .= '<cols>';
    $colWidths  = [20, 30, 50, 30, 30, 30, 30, 15, 10];
    foreach ($colWidths as $ci => $w) {
        $n = $ci + 1;
        $sheetXml .= "<col min=\"$n\" max=\"$n\" width=\"$w\" customWidth=\"1\"/>";
    }
    $sheetXml .= '</cols>';
    $sheetXml .= '<sheetData>';

    // Baris header (style 1 = bold biru)
    $sheetXml .= '<row r="1">';
    foreach ($header as $ci => $h) {
        $cell = $colLetters[$ci] . '1';
        $sheetXml .= "<c r=\"$cell\" t=\"inlineStr\" s=\"1\"><is><t>" . xlsxEsc($h) . "</t></is></c>";
    }
    $sheetXml .= '</row>';

    // Baris data (style 2 = wrap text)
    foreach ($data as $ri => $rowData) {
        $rowNum = $ri + 2;
        $sheetXml .= "<row r=\"$rowNum\">";
        foreach ($rowData as $ci => $val) {
            $cell = $colLetters[$ci] . $rowNum;
            $val  = (string)($val ?? '');
            $sheetXml .= "<c r=\"$cell\" t=\"inlineStr\" s=\"2\"><is><t>" . xlsxEsc($val) . "</t></is></c>";
        }
        $sheetXml .= '</row>';
    }

    $sheetXml .= '</sheetData>';
    $sheetXml .= '<pageSetup orientation="landscape"/>';
    $sheetXml .= '</worksheet>';

    // Buat file ZIP di memory
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new \ZipArchive();
    $zip->open($tmpFile, \ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',          $contentTypes);
    $zip->addFromString('_rels/.rels',                  $rels);
    $zip->addFromString('xl/workbook.xml',              $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels',   $wbRels);
    $zip->addFromString('xl/styles.xml',                $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
    $zip->close();

    $content = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $content;
}

// ── Siapkan data untuk ditulis ────────────────────────────────
$header = [
    'kategori', 'teks_bacaan', 'pertanyaan',
    'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d',
    'jawaban_benar', 'tipe_soal'
];

$data = [];
foreach ($rows as $r) {
    $data[] = [
        $r['nama_kategori'],
        $r['teks_bacaan']  ?? '',
        $r['pertanyaan'],
        $r['pilihan_a']    ?? '',
        $r['pilihan_b']    ?? '',
        $r['pilihan_c']    ?? '',
        $r['pilihan_d']    ?? '',
        $r['jawaban_benar'],
        $r['tipe_soal'],
    ];
}

$xlsxContent = buildXlsx($header, $data);
$namaFile    = 'soal_export_' . date('Ymd_His') . '.xlsx';

logActivity($conn, 'Export Soal', 'Export ' . count($rows) . ' soal ke XLSX' . ($filterKat ? " (kat ID: $filterKat)" : ''));

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Content-Length: ' . strlen($xlsxContent));
header('Pragma: no-cache');
header('Expires: 0');
echo $xlsxContent;
exit;
