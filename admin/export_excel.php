<?php
// ============================================================
// admin/export_excel.php  — Export Hasil Ujian ke Excel
// Sheet 1: Rekap semua mapel | Sheet berikut: per mapel
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/xlsx_builder.php';

requireLogin('admin_kecamatan');

$filterSek   = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas = trim($_GET['kelas'] ?? '');
$filterKat   = (int)($_GET['kategori_id'] ?? 0);
$filterJadwal= (int)($_GET['jadwal_id'] ?? 0);
$filterStatus= trim($_GET['status'] ?? '');
$q           = trim($_GET['q'] ?? '');
$mode        = $_GET['mode'] ?? 'hasil'; // hasil, rekap_sekolah, absensi

$namaAplikasi   = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$kkm            = (int)getSetting($conn, 'kkm', '60');
$tglExport      = date('d/m/Y H:i');

if ($mode === 'absensi') {
    // ── MODE ABSENSI ────────────────────────────────────────────
    $subSelesai = $filterJadwal
        ? "SELECT peserta_id FROM ujian WHERE jadwal_id=$filterJadwal AND waktu_selesai IS NOT NULL"
        : "SELECT peserta_id FROM hasil_ujian";
    $subUjian = $filterJadwal
        ? "SELECT peserta_id FROM ujian WHERE jadwal_id=$filterJadwal AND waktu_mulai IS NOT NULL"
        : "SELECT peserta_id FROM ujian WHERE waktu_mulai IS NOT NULL";

    $conds = ['1=1'];
    if ($filterSek)   $conds[] = "p.sekolah_id = $filterSek";
    if ($filterKelas) $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
    $wherePeserta = 'WHERE ' . implode(' AND ', $conds);

    $res = $conn->query("
        SELECT p.nama, p.kelas, p.kode_peserta,
               s.nama_sekolah,
               CASE WHEN p.id IN ($subSelesai) THEN 'Selesai'
                    WHEN p.id IN ($subUjian)   THEN 'Sedang'
                    ELSE 'Belum'
               END AS status_ujian,
               h.nilai, h.waktu_selesai,
               COALESCE(k.nama_kategori,'-') AS nama_mapel
        FROM peserta p
        LEFT JOIN sekolah s ON s.id = p.sekolah_id
        LEFT JOIN hasil_ujian h ON h.peserta_id = p.id " . ($filterJadwal
            ? "AND h.jadwal_id=$filterJadwal"
            : "AND h.id = (SELECT MAX(hx.id) FROM hasil_ujian hx WHERE hx.peserta_id = p.id)"
        ) . "
        LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
        LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
        $wherePeserta
        ORDER BY s.nama_sekolah, p.kelas, p.nama
    ");
    $absensiRows = [];
    if ($res) while ($r = $res->fetch_assoc()) {
        if ($filterStatus && strtolower($r['status_ujian']) !== strtolower($filterStatus)) continue;
        $absensiRows[] = $r;
    }

    $namaFile = 'Absensi_' . date('Ymd_His') . '.xlsx';
    $xlsx = new XLSXBuilder();
    $sheetData = [];
    $sheetData[] = [['value' => 'ABSENSI UJIAN - ' . strtoupper($namaAplikasi), 'style' => 1]];
    $sheetData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
    $sheetData[] = [];
    $sheetData[] = [
        ['value' => 'No',           'style' => 1],
        ['value' => 'Nama Peserta', 'style' => 1],
        ['value' => 'Kode',         'style' => 1],
        ['value' => 'Kelas',        'style' => 1],
        ['value' => 'Sekolah',      'style' => 1],
        ['value' => 'Mata Pelajaran','style'=> 1],
        ['value' => 'Status',       'style' => 1],
        ['value' => 'Nilai',        'style' => 1],
        ['value' => 'Waktu Selesai','style' => 1],
    ];
    $no = 1;
    foreach ($absensiRows as $r) {
        $sheetData[] = [
            $no++,
            $r['nama'],
            $r['kode_peserta'],
            $r['kelas'] ?? '-',
            $r['nama_sekolah'] ?? '-',
            $r['nama_mapel'] ?? '-',
            $r['status_ujian'],
            $r['nilai'] !== null ? (float)$r['nilai'] : '-',
            $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-',
        ];
    }
    $xlsx->addSheet('Absensi', $sheetData);
    $xlsx->download($namaFile);
    exit;
}

if ($mode === 'rekap_sekolah') {
    // ── MODE REKAP SEKOLAH ──────────────────────────────────────
    $conds = ["h.nilai IS NOT NULL"];
    if ($filterKat)   $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
    if ($filterKelas) $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
    $where = buildWhere($conds);

    $sql = "
        SELECT
            s.nama_sekolah, s.npsn,
            COUNT(h.id) AS jml_peserta,
            ROUND(AVG(h.nilai), 1) AS rata_nilai,
            MAX(h.nilai) AS nilai_max,
            MIN(h.nilai) AS nilai_min,
            SUM(CASE WHEN h.nilai >= $kkm THEN 1 ELSE 0 END) AS jml_lulus,
            SUM(CASE WHEN h.nilai < $kkm  THEN 1 ELSE 0 END) AS jml_tidak_lulus
        FROM hasil_ujian h
        JOIN peserta p ON p.id = h.peserta_id
        JOIN sekolah s ON s.id = p.sekolah_id
        LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
        $where
        GROUP BY s.id
        ORDER BY rata_nilai DESC
    ";
    $res = $conn->query($sql);
    $data = [];
    if ($res) while ($r = $res->fetch_assoc()) $data[] = $r;

    $namaFile = 'Rekap_Sekolah_' . date('Ymd_His') . '.xlsx';
    $xlsx = new XLSXBuilder();
    
    $sheetData = [];
    $sheetData[] = [['value' => 'REKAP NILAI PER SEKOLAH - ' . strtoupper($namaAplikasi), 'style' => 1]];
    $sheetData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
    $sheetData[] = [];
    $sheetData[] = [
        ['value' => 'Rank', 'style' => 1],
        ['value' => 'Nama Sekolah', 'style' => 1],
        ['value' => 'NPSN', 'style' => 1],
        ['value' => 'Peserta', 'style' => 1],
        ['value' => 'Rata-rata', 'style' => 1],
        ['value' => 'Tertinggi', 'style' => 1],
        ['value' => 'Terendah', 'style' => 1],
        ['value' => 'Lulus', 'style' => 1],
        ['value' => 'Tdk Lulus', 'style' => 1],
        ['value' => '% Lulus', 'style' => 1]
    ];

    $rank = 1;
    foreach ($data as $r) {
        $pct = $r['jml_peserta'] > 0 ? round($r['jml_lulus'] / $r['jml_peserta'] * 100, 1) : 0;
        $sheetData[] = [
            $rank++,
            $r['nama_sekolah'],
            $r['npsn'] ?? '-',
            (int)$r['jml_peserta'],
            (float)$r['rata_nilai'],
            (float)$r['nilai_max'],
            (float)$r['nilai_min'],
            (int)$r['jml_lulus'],
            (int)$r['jml_tidak_lulus'],
            $pct . '%'
        ];
    }
    $xlsx->addSheet('Rekap Sekolah', $sheetData);
    $xlsx->download($namaFile);
    exit;
}

// ── MODE HASIL / LEDGER ───────────────────────────────────────
$conds = ["h.nilai IS NOT NULL"];
if ($filterSek)   $conds[] = "p.sekolah_id = $filterSek";
if ($filterKelas) $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
if ($filterKat)   $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($q)           $conds[] = "(p.nama LIKE '%".$conn->real_escape_string($q)."%' OR p.kode_peserta LIKE '%".$conn->real_escape_string($q)."%')";
$where = buildWhere($conds);

$sql = "
    SELECT h.nilai, h.waktu_mulai, h.waktu_selesai,
           h.jml_benar, h.jml_salah, h.jml_kosong, h.total_soal,
           FLOOR(h.durasi_detik / 60) AS durasi,
           p.id AS peserta_id, p.nama, p.kelas, p.kode_peserta,
           s.nama_sekolah,
           COALESCE(k.id, 0) AS kategori_id,
           COALESCE(k.nama_kategori, 'Tanpa Mapel') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    ORDER BY k.nama_kategori ASC, h.nilai DESC
";
$res = $conn->query($sql);
$allRows = [];
$pesertaData = []; // Untuk Ledger
$mapelList   = []; // Daftar Mapel unik

if ($res) while ($r = $res->fetch_assoc()) {
    $allRows[] = $r;
    
    $pid = $r['peserta_id'];
    $mid = $r['kategori_id'];
    $mName = $r['nama_kategori'];

    if (!isset($pesertaData[$pid])) {
        $pesertaData[$pid] = [
            'nama' => $r['nama'],
            'kode' => $r['kode_peserta'],
            'sekolah' => $r['nama_sekolah'],
            'kelas' => $r['kelas'],
            'nilai' => []
        ];
    }
    $pesertaData[$pid]['nilai'][$mid] = $r['nilai'];
    if (!isset($mapelList[$mid])) $mapelList[$mid] = $mName;
}
asort($mapelList);

// Kelompokkan per mapel
$mapelGroups = [];
foreach ($allRows as $r) {
    $mapelGroups[$r['nama_kategori']][] = $r;
}

// Nama file
$namaFile = 'Nilai_Admin_' . date('Ymd_His') . '.xlsx';

// Cek ZipArchive
if (!class_exists('\ZipArchive')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $namaFile) . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Rank', 'Nama Peserta', 'Kode', 'Sekolah', 'Kelas', 'Mapel', 'Benar', 'Salah', 'Kosong', 'Nilai', 'Predikat', 'Durasi', 'Waktu Selesai']);
    $rank = 1;
    foreach ($allRows as $r) {
        [$ph, $pt] = getPredikat((int)$r['nilai']);
        fputcsv($output, [
            $rank++,
            $r['nama'],
            $r['kode_peserta'],
            $r['nama_sekolah'],
            $r['kelas'],
            $r['nama_kategori'],
            $r['jml_benar'],
            $r['jml_salah'],
            $r['jml_kosong'],
            $r['nilai'],
            $ph . ' - ' . $pt,
            $r['durasi'],
            $r['waktu_selesai']
        ]);
    }
    fclose($output);
    exit;
}

$xlsx = new XLSXBuilder();

// SHEET 1: Rekap Semua
$rekapData = [];
$rekapData[] = [['value' => 'REKAP NILAI UJIAN - ' . strtoupper($namaAplikasi), 'style' => 1]];
$rekapData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
$rekapData[] = [];
$rekapData[] = [
    ['value' => 'Rank', 'style' => 1],
    ['value' => 'Nama Peserta', 'style' => 1],
    ['value' => 'Kode', 'style' => 1],
    ['value' => 'Sekolah', 'style' => 1],
    ['value' => 'Kelas', 'style' => 1],
    ['value' => 'Mapel', 'style' => 1],
    ['value' => 'Benar', 'style' => 1],
    ['value' => 'Salah', 'style' => 1],
    ['value' => 'Kosong', 'style' => 1],
    ['value' => 'Nilai', 'style' => 1],
    ['value' => 'Predikat', 'style' => 1],
    ['value' => 'Durasi', 'style' => 1],
    ['value' => 'Waktu Selesai', 'style' => 1]
];

$rank = 1;
foreach ($allRows as $r) {
    [$ph, $pt] = getPredikat((int)$r['nilai']);
    $rekapData[] = [
        $rank++,
        $r['nama'],
        $r['kode_peserta'],
        $r['nama_sekolah'] ?? '-',
        $r['kelas'] ?? '-',
        $r['nama_kategori'],
        (int)$r['jml_benar'],
        (int)$r['jml_salah'],
        (int)$r['jml_kosong'],
        (float)$r['nilai'],
        $ph . ' - ' . $pt,
        (int)$r['durasi'],
        $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
    ];
}
$xlsx->addSheet('Rekap Semua', $rekapData);

// SHEET 2: Ledger Nilai
$ledgerData = [];
$ledgerData[] = [['value' => 'LEDGER NILAI UJIAN - ' . strtoupper($namaAplikasi), 'style' => 1]];
$ledgerData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
$ledgerData[] = [];
$ledgerHeader = [
    ['value' => 'No', 'style' => 1],
    ['value' => 'Nama Peserta', 'style' => 1],
    ['value' => 'Kode', 'style' => 1],
    ['value' => 'Sekolah', 'style' => 1],
    ['value' => 'Kelas', 'style' => 1]
];
foreach ($mapelList as $mName) {
    $ledgerHeader[] = ['value' => $mName, 'style' => 1];
}
$ledgerHeader[] = ['value' => 'Rata-rata', 'style' => 1];
$ledgerHeader[] = ['value' => 'Total', 'style' => 1];
$ledgerData[] = $ledgerHeader;

$no = 1;
foreach ($pesertaData as $pid => $p) {
    $pNilai = $p['nilai'];
    $sum = array_sum($pNilai);
    $cnt = count($pNilai);
    $avg = $cnt > 0 ? $sum / $cnt : 0;
    
    $row = [
        $no++,
        $p['nama'],
        $p['kode'],
        $p['sekolah'],
        $p['kelas'] ?? '-'
    ];
    foreach ($mapelList as $mid => $mName) {
        $row[] = isset($pNilai[$mid]) ? (float)$pNilai[$mid] : '-';
    }
    $row[] = (float)round($avg, 2);
    $row[] = (float)$sum;
    $ledgerData[] = $row;
}
$xlsx->addSheet('Ledger Nilai', $ledgerData);

// SHEET PER MAPEL
foreach ($mapelGroups as $namaMapel => $mapelRows) {
    $mapelData = [];
    $mapelData[] = [['value' => strtoupper($namaMapel) . ' - ' . strtoupper($namaAplikasi), 'style' => 1]];
    $mapelData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
    $mapelData[] = [];
    $mapelData[] = [
        ['value' => 'Rank', 'style' => 1],
        ['value' => 'Nama Peserta', 'style' => 1],
        ['value' => 'Kode', 'style' => 1],
        ['value' => 'Sekolah', 'style' => 1],
        ['value' => 'Kelas', 'style' => 1],
        ['value' => 'Benar', 'style' => 1],
        ['value' => 'Salah', 'style' => 1],
        ['value' => 'Kosong', 'style' => 1],
        ['value' => 'Nilai', 'style' => 1],
        ['value' => 'Predikat', 'style' => 1],
        ['value' => 'Durasi', 'style' => 1],
        ['value' => 'Waktu Selesai', 'style' => 1]
    ];

    $rank = 1;
    foreach ($mapelRows as $r) {
        [$ph, $pt] = getPredikat((int)$r['nilai']);
        $mapelData[] = [
            $rank++,
            $r['nama'],
            $r['kode_peserta'],
            $r['nama_sekolah'] ?? '-',
            $r['kelas'] ?? '-',
            (int)$r['jml_benar'],
            (int)$r['jml_salah'],
            (int)$r['jml_kosong'],
            (float)$r['nilai'],
            $ph . ' - ' . $pt,
            (int)$r['durasi'],
            $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
        ];
    }
    $xlsx->addSheet($namaMapel, $mapelData);
}

logActivity($conn, 'Export Excel Admin', count($allRows) . " data (XLSX)");
$xlsx->download($namaFile);
exit;
