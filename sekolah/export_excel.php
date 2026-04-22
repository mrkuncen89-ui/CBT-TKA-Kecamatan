<?php
// ============================================================
// sekolah/export_excel.php — Export Hasil Ujian Sekolah ke Excel
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/xlsx_builder.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

// ── Filter ────────────────────────────────────────────────────
$filterKelas = trim($_GET['kelas'] ?? '');
$q           = trim($_GET['q'] ?? '');

// ── Data sekolah ──────────────────────────────────────────────
$stSek = $conn->prepare("SELECT id, nama_sekolah, npsn, jenjang, alamat, telepon FROM sekolah WHERE id = ? LIMIT 1");
$stSek->bind_param('i', $sekolahId); $stSek->execute();
$sekolah = $stSek->get_result()->fetch_assoc(); $stSek->close();

$namaSekolah    = $sekolah['nama_sekolah'] ?? 'Sekolah';
$npsn           = $sekolah['npsn'] ?? '';
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$kkm            = (int)getSetting($conn, 'kkm', '60');
$tglExport      = date('d/m/Y H:i');

// ── Query hasil ───────────────────────────────────────────────
$conds = ["p.sekolah_id = $sekolahId", "h.nilai IS NOT NULL"];
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($q)           $conds[] = "(p.nama LIKE '%" . $conn->real_escape_string($q) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($q) . "%')";
$where = buildWhere($conds);

$sql = "
    SELECT h.nilai, h.waktu_mulai, h.waktu_selesai,
           h.jml_benar, h.jml_salah, h.jml_kosong, h.total_soal, h.durasi_detik,
           p.id AS peserta_id, p.nama, p.kode_peserta, p.kelas,
           COALESCE(k.id, 0) AS kategori_id,
           COALESCE(k.nama_kategori, 'Tanpa Mapel') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN kategori_soal k ON k.id = h.kategori_id
    $where
    ORDER BY p.nama ASC, k.nama_kategori ASC
";
$res = $conn->query($sql);
$allRows = [];
if ($res) while ($r = $res->fetch_assoc()) $allRows[] = $r;

// ── Pengelompokan Data ────────────────────────────────────────
$pesertaData = []; // Untuk Ledger
$mapelList   = []; // Daftar Mapel unik
$mapelGroups = []; // Data per Mapel

foreach ($allRows as $r) {
    $pid = $r['peserta_id'];
    $mid = $r['kategori_id'];
    $mName = $r['nama_kategori'];

    // Ledger data
    if (!isset($pesertaData[$pid])) {
        $pesertaData[$pid] = [
            'nama' => $r['nama'],
            'kode' => $r['kode_peserta'],
            'kelas' => $r['kelas'],
            'nilai' => []
        ];
    }
    $pesertaData[$pid]['nilai'][$mid] = $r['nilai'];

    // Mapel list
    if (!isset($mapelList[$mid])) {
        $mapelList[$mid] = $mName;
    }

    // Mapel groups
    $mapelGroups[$mName][] = $r;
}

// Urutkan mapel berdasarkan nama
asort($mapelList);

// ── Nama file ─────────────────────────────────────────────────
$slugSekolah = preg_replace('/[^a-zA-Z0-9]+/', '_', $namaSekolah);
$namaFile    = 'Hasil_Ujian_' . $slugSekolah . '_' . date('Ymd_His') . '.xlsx';

// ── Cek ZipArchive ────────────────────────────────────────────
if (!class_exists('\ZipArchive')) {
    // Fallback ke CSV jika ZipArchive tidak ada
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $namaFile) . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['PERHATIAN: Server tidak mendukung format XLSX (ZipArchive tidak aktif). File dialihkan ke CSV.']);
    fputcsv($no, []);
    fputcsv($output, ['No', 'Nama Peserta', 'Kode', 'Kelas', 'Mapel', 'Benar', 'Salah', 'Kosong', 'Nilai', 'Status', 'Waktu Selesai']);
    $no = 1;
    foreach ($allRows as $r) {
        fputcsv($output, [
            $no++,
            $r['nama'],
            $r['kode_peserta'],
            $r['kelas'] ?? '-',
            $r['nama_kategori'],
            $r['jml_benar'],
            $r['jml_salah'],
            $r['jml_kosong'],
            $r['nilai'],
            $r['nilai'] >= $kkm ? 'Lulus' : 'Tdk Lulus',
            $r['waktu_selesai']
        ]);
    }
    fclose($output);
    exit;
}

$xlsx = new XLSXBuilder();

// ── SHEET 1: REKAP SEMUA ──
$rekapData = [];
$rekapData[] = [['value' => 'REKAP HASIL UJIAN - ' . strtoupper($namaSekolah), 'style' => 1]];
$rekapData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
$rekapData[] = [];
$rekapData[] = [
    ['value' => 'No', 'style' => 1],
    ['value' => 'Nama Peserta', 'style' => 1],
    ['value' => 'Kode', 'style' => 1],
    ['value' => 'Kelas', 'style' => 1],
    ['value' => 'Mapel', 'style' => 1],
    ['value' => 'B', 'style' => 1],
    ['value' => 'S', 'style' => 1],
    ['value' => 'K', 'style' => 1],
    ['value' => 'Nilai', 'style' => 1],
    ['value' => 'Status', 'style' => 1],
    ['value' => 'Waktu Selesai', 'style' => 1]
];

$no = 1;
$rekapRows = $allRows;
usort($rekapRows, fn($a, $b) => $b['nilai'] <=> $a['nilai']);
foreach ($rekapRows as $r) {
    $rekapData[] = [
        $no++,
        $r['nama'],
        $r['kode_peserta'],
        $r['kelas'] ?? '-',
        $r['nama_kategori'],
        (int)$r['jml_benar'],
        (int)$r['jml_salah'],
        (int)$r['jml_kosong'],
        (float)$r['nilai'],
        $r['nilai'] >= $kkm ? 'Lulus' : 'Tdk Lulus',
        $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
    ];
}
$xlsx->addSheet('Rekap Semua', $rekapData);

// ── SHEET 2: LEDGER NILAI ──
$ledgerData = [];
$ledgerData[] = [['value' => 'LEDGER NILAI UJIAN - ' . strtoupper($namaSekolah), 'style' => 1]];
$ledgerData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Dicetak: ' . $tglExport, 'style' => 0]];
$ledgerData[] = [];
$ledgerHeader = [
    ['value' => 'No', 'style' => 1],
    ['value' => 'Nama Peserta', 'style' => 1],
    ['value' => 'Kode', 'style' => 1],
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
        $p['kelas'] ?? '-'
    ];
    foreach ($mapelList as $mid => $mName) {
        $row[] = isset($pNilai[$mid]) ? (float)$pNilai[$mid] : '-';
    }
    $row[] = (float)$avg;
    $row[] = (float)$sum;
    $ledgerData[] = $row;
}
$xlsx->addSheet('Ledger Nilai', $ledgerData);

// ── SHEET PER MAPEL ──
foreach ($mapelGroups as $mName => $mRows) {
    $mapelData = [];
    $mapelData[] = [['value' => 'HASIL UJIAN: ' . strtoupper($mName), 'style' => 1]];
    $mapelData[] = [['value' => $namaSekolah . ' | TP: ' . $tahunPelajaran, 'style' => 0]];
    $mapelData[] = [];
    $mapelData[] = [
        ['value' => 'No', 'style' => 1],
        ['value' => 'Nama Peserta', 'style' => 1],
        ['value' => 'Kode', 'style' => 1],
        ['value' => 'Kelas', 'style' => 1],
        ['value' => 'B', 'style' => 1],
        ['value' => 'S', 'style' => 1],
        ['value' => 'K', 'style' => 1],
        ['value' => 'Nilai', 'style' => 1],
        ['value' => 'Status', 'style' => 1],
        ['value' => 'Waktu Selesai', 'style' => 1]
    ];
    
    $no = 1;
    usort($mRows, fn($a, $b) => $b['nilai'] <=> $a['nilai']);
    foreach ($mRows as $r) {
        $mapelData[] = [
            $no++,
            $r['nama'],
            $r['kode_peserta'],
            $r['kelas'] ?? '-',
            (int)$r['jml_benar'],
            (int)$r['jml_salah'],
            (int)$r['jml_kosong'],
            (float)$r['nilai'],
            $r['nilai'] >= $kkm ? 'Lulus' : 'Tdk Lulus',
            $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
        ];
    }
    $xlsx->addSheet($mName, $mapelData);
}

logActivity($conn, 'Export Excel Sekolah', "Sekolah ID $sekolahId, " . count($allRows) . " data (XLSX)");
$xlsx->download($namaFile);
exit;
