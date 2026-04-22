<?php
// ============================================================
// admin/export_pdf.php  — Cetak Laporan Nilai (Print → PDF)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterSek    = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas  = trim($_GET['kelas'] ?? '');
$filterKat    = (int)($_GET['kategori_id'] ?? 0);
$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');
$q            = trim($_GET['q'] ?? '');
$mode         = $_GET['mode'] ?? 'ranking'; // ranking, ledger, rekap_sekolah, absensi

// ── MODE ABSENSI ─────────────────────────────────────────────
if ($mode === 'absensi') {
    $namaAplikasi   = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
    $namaKecamatan  = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
    $tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
    $kkm            = (int)getSetting($conn, 'kkm', '60');

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
        SELECT p.nama, p.kelas, p.kode_peserta, s.nama_sekolah,
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
    $totalAbs    = count($absensiRows);
    $selesaiAbs  = count(array_filter($absensiRows, fn($r) => $r['status_ujian'] === 'Selesai'));
    $belumAbs    = count(array_filter($absensiRows, fn($r) => $r['status_ujian'] === 'Belum'));

    // Cek info jadwal jika difilter
    $jadwalInfo = '';
    if ($filterJadwal) {
        $jr = $conn->query("SELECT j.tanggal, j.keterangan, k.nama_kategori FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id=j.kategori_id WHERE j.id=$filterJadwal LIMIT 1");
        if ($jr && $jr->num_rows > 0) {
            $jd = $jr->fetch_assoc();
            $jadwalInfo = date('d/m/Y', strtotime($jd['tanggal'])) . ($jd['keterangan'] ? ' — '.$jd['keterangan'] : '') . ($jd['nama_kategori'] ? ' ('.$jd['nama_kategori'].')' : '');
        }
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Absensi Ujian — <?= e($namaAplikasi) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:10px;color:#1e293b;background:#fff}
.wrap{max-width:900px;margin:0 auto;padding:16px}
.kop{text-align:center;border-bottom:2.5px solid #1e40af;padding-bottom:10px;margin-bottom:12px}
.kop h1{font-size:14px;font-weight:800;text-transform:uppercase;color:#1e40af}
.kop p{font-size:10px;color:#475569;margin-top:2px}
.meta{display:flex;justify-content:space-between;margin-bottom:10px;font-size:10px}
.stat-row{display:flex;gap:20px;margin-bottom:12px}
.stat-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:6px 14px;text-align:center}
.stat-box .val{font-size:18px;font-weight:800;color:#1e40af}
.stat-box .lbl{font-size:9px;color:#64748b}
table{width:100%;border-collapse:collapse;font-size:9.5px}
th{background:#1e40af;color:#fff;padding:5px 8px;text-align:left;font-weight:700}
td{padding:4px 8px;border-bottom:1px solid #e2e8f0}
tr:nth-child(even) td{background:#f8fafc}
.s-selesai{color:#059669;font-weight:700}
.s-sedang{color:#d97706;font-weight:700}
.s-belum{color:#dc2626;font-weight:700}
.ttd-area{display:flex;justify-content:flex-end;margin-top:24px}
.ttd-box{text-align:center;width:200px}
.ttd-line{border-bottom:1px solid #333;margin:30px 0 4px}
@media print{@page{margin:1cm}}
</style>
</head>
<body>
<div class="wrap">
<div class="kop">
    <h1>Daftar Hadir Ujian</h1>
    <p><?= e($namaAplikasi) ?> — <?= e($namaKecamatan) ?> — Tahun Pelajaran <?= e($tahunPelajaran) ?></p>
    <?php if ($jadwalInfo): ?><p style="color:#1e40af;font-weight:700">Jadwal: <?= e($jadwalInfo) ?></p><?php endif; ?>
</div>
<div class="meta">
    <span>Dicetak: <?= date('d/m/Y H:i') ?></span>
    <span>Total: <?= $totalAbs ?> peserta</span>
</div>
<div class="stat-row">
    <div class="stat-box"><div class="val"><?= $selesaiAbs ?></div><div class="lbl">Sudah Selesai</div></div>
    <div class="stat-box"><div class="val" style="color:#d97706"><?= $totalAbs - $selesaiAbs - $belumAbs ?></div><div class="lbl">Sedang Ujian</div></div>
    <div class="stat-box"><div class="val" style="color:#dc2626"><?= $belumAbs ?></div><div class="lbl">Belum Ujian</div></div>
    <div class="stat-box"><div class="val" style="color:#059669"><?= $totalAbs > 0 ? round($selesaiAbs/$totalAbs*100,1) : 0 ?>%</div><div class="lbl">Kehadiran</div></div>
</div>
<table>
    <thead><tr>
        <th style="width:30px">No</th>
        <th>Nama Peserta</th>
        <th>Kode</th>
        <th>Kelas</th>
        <th>Sekolah</th>
        <th>Mapel</th>
        <th>Status</th>
        <th>Nilai</th>
        <th>Waktu Selesai</th>
        <th style="width:40px">TTD</th>
    </tr></thead>
    <tbody>
    <?php if (!$absensiRows): ?>
    <tr><td colspan="10" style="text-align:center;padding:16px;color:#94a3b8">Belum ada data</td></tr>
    <?php else: $no=1; foreach($absensiRows as $r):
        $sc = match($r['status_ujian']) { 'Selesai'=>'s-selesai', 'Sedang'=>'s-sedang', default=>'s-belum' };
    ?>
    <tr>
        <td><?= $no++ ?></td>
        <td><?= htmlspecialchars($r['nama']) ?></td>
        <td><code><?= htmlspecialchars($r['kode_peserta']) ?></code></td>
        <td><?= htmlspecialchars($r['kelas'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['nama_sekolah'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['nama_mapel'] ?? '-') ?></td>
        <td class="<?= $sc ?>"><?= $r['status_ujian'] ?></td>
        <td><?= $r['nilai'] !== null ? $r['nilai'] : '-' ?></td>
        <td><?= $r['waktu_selesai'] ? date('H:i', strtotime($r['waktu_selesai'])) : '-' ?></td>
        <td></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
<div class="ttd-area">
    <div class="ttd-box">
        <p>Mengetahui, Admin Kecamatan</p>
        <div class="ttd-line"></div>
        <p>( ______________________ )</p>
    </div>
</div>
</div>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),500))</script>
</body>
</html>
    <?php exit;
}

// ── Pakai hasil_ujian (konsisten dengan halaman Hasil) ────────
$conds = ["h.nilai IS NOT NULL"];
if ($filterSek)    $conds[] = "p.sekolah_id = $filterSek";
if ($filterKelas)  $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
if ($filterKat)    $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($filterJadwal) $conds[] = "h.jadwal_id = $filterJadwal";
if ($q)            $conds[] = "(p.nama LIKE '%".$conn->real_escape_string($q)."%' OR p.kode_peserta LIKE '%".$conn->real_escape_string($q)."%')";
$where = buildWhere($conds);

$sql = "
    SELECT h.nilai, h.waktu_mulai, h.waktu_selesai,
           h.jml_benar, h.total_soal, h.jml_salah, h.jml_kosong,
           FLOOR(h.durasi_detik / 60) AS durasi,
           p.id AS peserta_id, p.nama, p.kelas, p.kode_peserta,
           s.nama_sekolah,
           COALESCE(k.id, 0) AS kategori_id,
           COALESCE(k.nama_kategori, '-') AS nama_kategori,
           jd.tanggal AS jadwal_tanggal
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    ORDER BY h.nilai DESC, h.waktu_selesai ASC
";
$res  = $conn->query($sql);
$rows = []; $rank = 1;
$ledgerData = [];
$mapelList  = [];

if ($res) while ($r = $res->fetch_assoc()) { 
    $r['rank'] = $rank++; 
    $rows[] = $r; 

    $pid = $r['peserta_id'];
    $mid = $r['kategori_id'];
    if (!isset($ledgerData[$pid])) {
        $ledgerData[$pid] = [
            'nama' => $r['nama'],
            'kode' => $r['kode_peserta'],
            'sekolah' => $r['nama_sekolah'],
            'kelas' => $r['kelas'],
            'nilai' => []
        ];
    }
    $ledgerData[$pid]['nilai'][$mid] = $r['nilai'];
    if (!isset($mapelList[$mid])) $mapelList[$mid] = $r['nama_kategori'];
}
asort($mapelList);

// ── Rekap per sekolah (untuk mode rekap_sekolah) ───────────────
$rekapSekolah = [];
if ($mode === 'rekap_sekolah') {
    $sqlRekap = "
        SELECT
            s.nama_sekolah,
            s.npsn,
            COUNT(h.id)                          AS jml_peserta,
            ROUND(AVG(h.nilai), 1)               AS rata_nilai,
            MAX(h.nilai)                         AS nilai_max,
            MIN(h.nilai)                         AS nilai_min,
            SUM(CASE WHEN h.nilai >= $kkm THEN 1 ELSE 0 END) AS jml_lulus,
            SUM(CASE WHEN h.nilai < $kkm  THEN 1 ELSE 0 END) AS jml_tidak_lulus
        FROM hasil_ujian h
        JOIN peserta p ON p.id = h.peserta_id
        JOIN sekolah s ON s.id = p.sekolah_id
        LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
        $where
        GROUP BY s.id, s.nama_sekolah, s.npsn
        ORDER BY rata_nilai DESC
    ";
    $resRekap = $conn->query($sqlRekap);
    if ($resRekap) while ($r = $resRekap->fetch_assoc()) $rekapSekolah[] = $r;
}

$total  = count($rows);
$nilaiA = array_column($rows, 'nilai');
$rata   = $total > 0 ? round(array_sum($nilaiA) / $total, 1) : 0;
$maks   = $total > 0 ? max($nilaiA) : 0;
$min    = $total > 0 ? min($nilaiA) : 0;

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan     = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$tahunPelajaran    = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$kkm               = (int)getSetting($conn, 'kkm', '60');
$lulus             = count(array_filter($nilaiA, fn($n) => $n >= $kkm));

// Label filter untuk judul
$judulFilter = '';
if ($filterSek) {
    $_sr = $conn->query("SELECT nama_sekolah FROM sekolah WHERE id=$filterSek LIMIT 1"); $sr = ($_sr && $_sr->num_rows>0) ? $_sr->fetch_assoc() : null;
    if ($sr) $judulFilter .= ' — ' . $sr['nama_sekolah'];
}
if ($filterKat) {
    $_kr = $conn->query("SELECT nama_kategori FROM kategori_soal WHERE id=$filterKat LIMIT 1"); $kr = ($_kr && $_kr->num_rows>0) ? $_kr->fetch_assoc() : null;
    if ($kr) $judulFilter .= ' — ' . $kr['nama_kategori'];
}
if ($filterJadwal) {
    $_jr = $conn->query("SELECT tanggal, keterangan FROM jadwal_ujian WHERE id=$filterJadwal LIMIT 1"); $jr = ($_jr && $_jr->num_rows>0) ? $_jr->fetch_assoc() : null;
    if ($jr) $judulFilter .= ' — ' . date('d/m/Y', strtotime($jr['tanggal'])) . ($jr['keterangan'] ? " ({$jr['keterangan']})" : '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Nilai TKA</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;background:#fff}
@media screen{
  body{background:#e2e8f0;padding:20px}
  .page{background:#fff;max-width:1050px;margin:0 auto;padding:28px 32px;box-shadow:0 4px 20px rgba(0,0,0,.15)}
  .no-print{display:flex;gap:10px;justify-content:flex-end;max-width:1050px;margin:0 auto 14px}
  .no-print button,.no-print a{padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;border:none}
  .btn-print{background:#1a56db;color:#fff}
  .btn-close2{background:#64748b;color:#fff}
}
@media print{
  body{background:#fff;padding:0}
  .page{padding:10mm 14mm}
  .no-print{display:none!important}
  table{page-break-inside:auto}
  tr{page-break-inside:avoid}
}
.doc-header{display:flex;align-items:flex-start;gap:16px;border-bottom:3px solid #1a56db;padding-bottom:14px;margin-bottom:18px}
.logo-box{width:54px;height:54px;background:linear-gradient(135deg,#1a56db,#7c3aed);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0}
.doc-title h1{font-size:15px;font-weight:800;color:#1a56db;margin:0 0 3px}
.doc-title p{font-size:10.5px;color:#64748b;margin:0}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.stat-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;text-align:center}
.stat-box .n{font-size:20px;font-weight:800;color:#1a56db}
.stat-box .l{font-size:10px;color:#64748b;margin-top:2px}
table{width:100%;border-collapse:collapse;font-size:10px}
thead th{background:#1a56db;color:#fff;padding:7px 6px;font-weight:700;font-size:9.5px;text-transform:uppercase;letter-spacing:.3px;text-align:left}
th.c,td.c{text-align:center}
tbody tr:nth-child(even){background:#f8fafc}
tbody tr.rank-1{background:#fffbeb}
tbody tr.rank-2{background:#f9fafb}
tbody tr.rank-3{background:#fef3c7}
td{padding:6px 6px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.badge-pred{display:inline-block;padding:2px 7px;border-radius:10px;color:#fff;font-size:9px;font-weight:700}
.doc-footer{margin-top:22px;border-top:1px solid #e2e8f0;padding-top:12px;display:flex;justify-content:space-between;align-items:flex-end}
.footer-note{font-size:9.5px;color:#64748b}
.ttd-box{text-align:center}
.ttd-line{width:160px;border-bottom:1px solid #1e293b;margin:50px auto 4px}
.ttd-name{font-size:9.5px;color:#64748b}
</style>
</head>
<body>
<div class="no-print">
    <button class="btn-close2" onclick="window.close()">✕ Tutup</button>
    <button class="btn-print" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
</div>

<div class="page">
    <!-- Header -->
    <div class="doc-header">
        <?php
        $lgF = getSetting($conn,'logo_file_path','');
        $lgU = getSetting($conn,'logo_url','');
        $lgA = $lgF ? BASE_URL.'/'.$lgF : $lgU;
        ?>
        <?php if ($lgA): ?>
        <div class="logo-box">
            <img src="<?= htmlspecialchars($lgA) ?>" alt="Logo"
                 style="width:100%;height:100%;object-fit:contain"
                 onerror="this.outerHTML='🏫'">
        </div>
        <?php else: ?>
        <div class="logo-box">🏫</div>
        <?php endif; ?>
        <div class="doc-title">
            <h1>LAPORAN REKAP NILAI UJIAN <?= e(strtoupper($namaAplikasi)) ?><?= e($judulFilter) ?></h1>
            <p><?= e($namaPenyelenggara ?: $namaKecamatan) ?></p>
            <p>
                Tahun Pelajaran: <strong><?= e($tahunPelajaran) ?></strong>
                &nbsp;·&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
                <?php if ($filterKelas): ?> &nbsp;·&nbsp; Kelas: <?= e($filterKelas) ?><?php endif; ?>
                <?php if ($q): ?> &nbsp;·&nbsp; Filter: "<?= e($q) ?>"<?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Statistik -->
    <?php if ($mode !== 'ledger'): ?>
    <div class="stat-row">
        <div class="stat-box"><div class="n"><?= $total ?></div><div class="l">Total Peserta</div></div>
        <div class="stat-box"><div class="n"><?= $rata ?></div><div class="l">Rata-rata Nilai</div></div>
        <div class="stat-box"><div class="n" style="color:#16a34a"><?= $lulus ?></div><div class="l">Lulus (≥60)</div></div>
        <div class="stat-box"><div class="n" style="color:#dc2626"><?= $total-$lulus ?></div><div class="l">Tidak Lulus</div></div>
    </div>
    <?php endif; ?>

    <!-- Tabel -->
    <?php if ($mode === 'rekap_sekolah'): ?>
    <table>
        <thead>
            <tr>
                <th class="c" style="width:30px">No</th>
                <th>Nama Sekolah</th>
                <th class="c">NPSN</th>
                <th class="c">Peserta</th>
                <th class="c">Rata-rata</th>
                <th class="c">Tertinggi</th>
                <th class="c">Terendah</th>
                <th class="c">Lulus</th>
                <th class="c">Tdk Lulus</th>
                <th class="c">% Lulus</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rekapSekolah): $no=1; foreach ($rekapSekolah as $r): 
                $pct = $r['jml_peserta'] > 0 ? round($r['jml_lulus'] / $r['jml_peserta'] * 100, 1) : 0;
            ?>
            <tr>
                <td class="c"><?= $no++ ?></td>
                <td><strong><?= e($r['nama_sekolah']) ?></strong></td>
                <td class="c"><?= e($r['npsn']) ?></td>
                <td class="c"><?= $r['jml_peserta'] ?></td>
                <td class="c fw" style="background:#f1f5f9"><?= $r['rata_nilai'] ?></td>
                <td class="c"><?= $r['nilai_max'] ?></td>
                <td class="c"><?= $r['nilai_min'] ?></td>
                <td class="c" style="color:#16a34a"><?= $r['jml_lulus'] ?></td>
                <td class="c" style="color:#dc2626"><?= $r['jml_tidak_lulus'] ?></td>
                <td class="c fw"><?= $pct ?>%</td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" class="c" style="padding:20px;color:#94a3b8">Belum ada data rekap sekolah</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php elseif ($mode === 'ledger'): ?>
    <table>
        <thead>
            <tr>
                <th class="c" style="width:30px">No</th>
                <th>Nama Peserta</th>
                <th>Sekolah</th>
                <th class="c" style="width:40px">Kls</th>
                <?php foreach ($mapelList as $mName): ?>
                <th class="c"><?= e($mName) ?></th>
                <?php endforeach; ?>
                <th class="c">Rata-rata</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($ledgerData): $no=1; foreach ($ledgerData as $pid => $p): 
                $pNilai = $p['nilai'];
                $sum = array_sum($pNilai);
                $cnt = count($pNilai);
                $avg = $cnt > 0 ? round($sum / $cnt, 1) : 0;
            ?>
            <tr>
                <td class="c"><?= $no++ ?></td>
                <td>
                    <strong><?= e($p['nama']) ?></strong><br>
                    <small style="font-size:8px"><?= e($p['kode']) ?></small>
                </td>
                <td style="font-size:9px"><?= e($p['sekolah'] ?? '-') ?></td>
                <td class="c"><?= e($p['kelas'] ?? '-') ?></td>
                <?php foreach ($mapelList as $mid => $mName): ?>
                <td class="c fw">
                    <?php if (isset($pNilai[$mid])): ?>
                        <span style="color:<?= $pNilai[$mid] < $kkm ? '#dc2626' : '#16a34a' ?>"><?= $pNilai[$mid] ?></span>
                    <?php else: ?>
                        <span style="color:#94a3b8">-</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="c" style="background:#f1f5f9;font-weight:800"><?= $avg ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="<?= 5 + count($mapelList) ?>" class="c" style="padding:20px;color:#94a3b8">Belum ada data nilai</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th class="c" style="width:30px">No</th>
                <th>Nama Peserta</th>
                <th class="c" style="width:58px">Kode</th>
                <th>Sekolah</th>
                <th class="c" style="width:30px">Kls</th>
                <th>Mapel</th>
                <th class="c" style="width:34px">Benar</th>
                <th class="c" style="width:34px">Total</th>
                <th class="c" style="width:40px">Nilai</th>
                <th class="c" style="width:66px">Predikat</th>
                <th class="c" style="width:38px">Durasi</th>
                <th style="width:76px">Tgl Selesai</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
              [$ph,$pt,$pb,$pw] = getPredikat((int)$r['nilai']);
              $rc = $r['rank'] <= 3 ? 'rank-'.$r['rank'] : '';
        ?>
        <tr class="<?= $rc ?>">
            <td class="c">
                <?= match((int)$r['rank']){1=>'🥇',2=>'🥈',3=>'🥉',default=>$r['rank']} ?>
            </td>
            <td><?= e($r['nama']) ?></td>
            <td class="c" style="font-family:monospace;font-size:9px"><?= e($r['kode_peserta']) ?></td>
            <td><?= e($r['nama_sekolah']??'-') ?></td>
            <td class="c"><?= e($r['kelas']??'-') ?></td>
            <td style="font-size:9px"><?= e($r['nama_kategori']??'-') ?></td>
            <td class="c" style="color:#16a34a;font-weight:700"><?= (int)$r['jml_benar'] ?></td>
            <td class="c"><?= (int)$r['total_soal'] ?></td>
            <td class="c"><strong style="color:<?= $pw ?>;font-size:13px"><?= $r['nilai'] ?></strong></td>
            <td class="c">
                <span class="badge-pred" style="background:<?= $pw ?>"><?= $ph ?></span>
                <span style="font-size:9px;color:#64748b;display:block;margin-top:1px"><?= $pt ?></span>
            </td>
            <td class="c"><?= $r['durasi']!==null ? $r['durasi']."'" : '-' ?></td>
            <td style="font-size:9.5px"><?= $r['waktu_selesai'] ? date('d/m/Y H:i',strtotime($r['waktu_selesai'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="12" style="text-align:center;padding:20px;color:#94a3b8">Belum ada data ujian</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Footer -->
    <div class="doc-footer">
        <div class="footer-note">
            <p>* Nilai = (Benar / Total Soal) × 100 &nbsp;·&nbsp; KKM = 60</p>
            <p>Tertinggi: <strong><?= $maks ?></strong> &nbsp;·&nbsp; Terendah: <strong><?= $min ?></strong>
               &nbsp;·&nbsp; Kelulusan: <strong><?= $total>0?round($lulus/$total*100,1):0 ?>%</strong></p>
        </div>
        <div class="ttd-box">
            <p style="font-size:9.5px">Mengetahui, Admin Kecamatan</p>
            <div class="ttd-line"></div>
            <p class="ttd-name">( ______________________ )</p>
        </div>
    </div>
</div>

<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),500))</script>
</body>
</html>
