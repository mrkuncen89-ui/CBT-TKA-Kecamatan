<?php
// ============================================================
// sekolah/export_excel.php — Export Hasil Ujian Sekolah
// Sheet 1: Rekap Semua Mapel | Sheet berikut: per mapel
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../core/xlsx_builder.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

$filterKelas = trim($_GET['kelas'] ?? '');
$filterKat   = (int)($_GET['kategori_id'] ?? 0);
$q           = trim($_GET['q'] ?? '');

$stSek = $conn->prepare("SELECT id, nama_sekolah, npsn, jenjang, alamat, telepon FROM sekolah WHERE id = ? LIMIT 1");
$stSek->bind_param('i', $sekolahId); $stSek->execute();
$sekolah = $stSek->get_result()->fetch_assoc(); $stSek->close();

$namaSekolah    = $sekolah['nama_sekolah'] ?? 'Sekolah';
$npsn           = $sekolah['npsn'] ?? '';
$namaAplikasi   = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan  = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$kkm            = (int)getSetting($conn, 'kkm', '60');
$tglExport      = date('d/m/Y H:i');

$conds = ["p.sekolah_id = $sekolahId", "h.nilai IS NOT NULL"];
if ($filterKelas) $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
if ($filterKat)   $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($q)           $conds[] = "(p.nama LIKE '%".$conn->real_escape_string($q)."%' OR p.kode_peserta LIKE '%".$conn->real_escape_string($q)."%')";
$where = buildWhere($conds);

$res = $conn->query("
    SELECT h.nilai, h.waktu_mulai, h.waktu_selesai,
           h.jml_benar, h.jml_salah, h.jml_kosong, h.total_soal, h.durasi_detik,
           p.nama, p.kode_peserta, p.kelas,
           COALESCE(k.nama_kategori, 'Tanpa Mapel') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    ORDER BY k.nama_kategori ASC, h.nilai DESC
");

$allRows = []; $rank = 1;
if ($res) while ($r = $res->fetch_assoc()) { $r['rank'] = $rank++; $allRows[] = $r; }

// Kelompokkan per mapel
$mapelGroups = [];
foreach ($allRows as $r) $mapelGroups[$r['nama_kategori']][] = $r;

$slugSekolah = preg_replace('/[^a-zA-Z0-9]+/', '_', $namaSekolah);
$namaFile    = 'hasil_'.$slugSekolah.'_'.date('Ymd_His').'.xlsx';

logActivity($conn, 'Export Excel Sekolah', "Sekolah ID $sekolahId, ".count($allRows)." data (XLSX)");

// ── Cek ZipArchive ────────────────────────────────────────────
if (!class_exists('\ZipArchive')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $namaFile) . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['PERHATIAN: Server tidak mendukung format XLSX (ZipArchive tidak aktif). File dialihkan ke CSV.']);
    fputcsv($output, []);
    fputcsv($output, ['No', 'Nama Peserta', 'Kode', 'Kelas', 'Mapel', 'Benar', 'Salah', 'Kosong', 'Total', 'Nilai', 'Predikat', 'Status', 'Durasi (mnt)', 'Tgl Selesai']);
    foreach ($allRows as $r) {
        [$ph, $pt] = getPredikat((int)$r['nilai']);
        fputcsv($output, [
            $r['rank'],
            $r['nama'],
            $r['kode_peserta'],
            $r['kelas'] ?? '-',
            $r['nama_kategori'],
            $r['jml_benar'],
            $r['jml_salah'],
            $r['jml_kosong'],
            $r['total_soal'],
            $r['nilai'],
            $ph.' - '.$pt,
            $r['nilai'] >= $kkm ? 'Lulus' : 'Tidak Lulus',
            $r['durasi_detik'] ? floor($r['durasi_detik']/60) : 0,
            $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
        ]);
    }
    fclose($output);
    exit;
}

$xlsx = new XLSXBuilder();

// ── SHEET 1: Rekap Semua Mapel ────────────────────────────────
$rekapData = [];
$rekapData[] = [['value' => 'REKAP NILAI UJIAN ' . strtoupper($namaAplikasi), 'style' => 1]];
$rekapData[] = [['value' => $namaSekolah . ($npsn ? ' | NPSN: '.$npsn : ''), 'style' => 0]];
$rekapData[] = [['value' => 'Tahun Pelajaran: ' . $tahunPelajaran . ' | Diekspor: ' . $tglExport, 'style' => 0]];
$rekapData[] = [];

// Statistik Ringkas
$nilaiArr = array_column($allRows, 'nilai');
$total    = count($allRows);
$rata     = $total > 0 ? round(array_sum($nilaiArr) / $total, 1) : 0;
$maks     = $total > 0 ? max($nilaiArr) : 0;
$min      = $total > 0 ? min($nilaiArr) : 0;
$lulus    = count(array_filter($nilaiArr, fn($n) => $n >= $kkm));

$rekapData[] = [
    ['value' => 'Total', 'style' => 1], ['value' => $total],
    ['value' => 'Rata-rata', 'style' => 1], ['value' => $rata],
    ['value' => 'Tertinggi', 'style' => 1], ['value' => $maks],
    ['value' => 'Terendah', 'style' => 1], ['value' => $min],
    ['value' => 'Lulus', 'style' => 1], ['value' => $lulus],
    ['value' => 'Tdk Lulus', 'style' => 1], ['value' => ($total - $lulus)]
];
$rekapData[] = [];

$rekapData[] = [
    ['value' => 'No', 'style' => 1],
    ['value' => 'Nama Peserta', 'style' => 1],
    ['value' => 'Kode', 'style' => 1],
    ['value' => 'Kelas', 'style' => 1],
    ['value' => 'Mapel', 'style' => 1],
    ['value' => 'Benar', 'style' => 1],
    ['value' => 'Salah', 'style' => 1],
    ['value' => 'Kosong', 'style' => 1],
    ['value' => 'Total', 'style' => 1],
    ['value' => 'Nilai', 'style' => 1],
    ['value' => 'Predikat', 'style' => 1],
    ['value' => 'Status', 'style' => 1],
    ['value' => 'Durasi (mnt)', 'style' => 1],
    ['value' => 'Tgl Selesai', 'style' => 1]
];

foreach ($allRows as $r) {
    [$ph, $pt] = getPredikat((int)$r['nilai']);
    $rekapData[] = [
        $r['rank'],
        $r['nama'],
        $r['kode_peserta'],
        $r['kelas'] ?? '-',
        $r['nama_kategori'],
        (int)$r['jml_benar'],
        (int)$r['jml_salah'],
        (int)$r['jml_kosong'],
        (int)$r['total_soal'],
        (float)$r['nilai'],
        $ph.' - '.$pt,
        $r['nilai'] >= $kkm ? 'Lulus' : 'Tidak Lulus',
        $r['durasi_detik'] ? (int)floor($r['durasi_detik']/60) : 0,
        $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
    ];
}
$xlsx->addSheet('Rekap Semua', $rekapData);

// ── SHEET PER MAPEL ───────────────────────────────────────────
foreach ($mapelGroups as $namaMapel => $mapelRows) {
    $mapelData = [];
    $mapelData[] = [['value' => strtoupper($namaMapel) . ' — ' . strtoupper($namaAplikasi), 'style' => 1]];
    $mapelData[] = [['value' => $namaSekolah . ' | TP: ' . $tahunPelajaran, 'style' => 0]];
    $mapelData[] = [];
    
    // Statistik per mapel
    $mNilai = array_column($mapelRows, 'nilai');
    $mTot   = count($mapelRows);
    $mRata  = $mTot > 0 ? round(array_sum($mNilai)/$mTot, 1) : 0;
    $mLulus = count(array_filter($mNilai, fn($n) => $n >= $kkm));
    
    $mapelData[] = [
        ['value' => 'Total', 'style' => 1], ['value' => $mTot],
        ['value' => 'Rata-rata', 'style' => 1], ['value' => $mRata],
        ['value' => 'Lulus', 'style' => 1], ['value' => $mLulus],
        ['value' => 'Tdk Lulus', 'style' => 1], ['value' => ($mTot - $mLulus)]
    ];
    $mapelData[] = [];

    $mapelData[] = [
        ['value' => 'No', 'style' => 1],
        ['value' => 'Nama Peserta', 'style' => 1],
        ['value' => 'Kode', 'style' => 1],
        ['value' => 'Kelas', 'style' => 1],
        ['value' => 'Benar', 'style' => 1],
        ['value' => 'Salah', 'style' => 1],
        ['value' => 'Kosong', 'style' => 1],
        ['value' => 'Total', 'style' => 1],
        ['value' => 'Nilai', 'style' => 1],
        ['value' => 'Predikat', 'style' => 1],
        ['value' => 'Status', 'style' => 1],
        ['value' => 'Durasi (mnt)', 'style' => 1],
        ['value' => 'Tgl Selesai', 'style' => 1]
    ];

    $mRank = 1;
    foreach ($mapelRows as $r) {
        [$ph, $pt] = getPredikat((int)$r['nilai']);
        $mapelData[] = [
            $mRank++,
            $r['nama'],
            $r['kode_peserta'],
            $r['kelas'] ?? '-',
            (int)$r['jml_benar'],
            (int)$r['jml_salah'],
            (int)$r['jml_kosong'],
            (int)$r['total_soal'],
            (float)$r['nilai'],
            $ph.' - '.$pt,
            $r['nilai'] >= $kkm ? 'Lulus' : 'Tidak Lulus',
            $r['durasi_detik'] ? (int)floor($r['durasi_detik']/60) : 0,
            $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-'
        ];
    }
    $xlsx->addSheet($namaMapel, $mapelData);
}

$xlsx->download($namaFile);
exit;

