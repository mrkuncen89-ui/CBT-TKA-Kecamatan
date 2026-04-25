<?php
// ============================================================
// admin/dashboard.php  — Dashboard Admin Kecamatan
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// Cek update dari GitHub
require_once __DIR__ . '/../config/version.php';
$adaUpdate = false;
$infoUpdate = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, VERSION_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'TKAKecamatan/1.0.1');
$jsonUpdate = curl_exec($ch);
curl_close($ch);
if ($jsonUpdate) {
    $infoUpdate = json_decode($jsonUpdate, true);
    if ($infoUpdate && version_compare($infoUpdate['version'], APP_VERSION, '>')) {
        $adaUpdate = true;
    }
}

/* ── Auto Backup Harian ──────────────────────────────────────
   Backup otomatis berjalan sekali sehari saat dashboard dibuka.
────────────────────────────────────────────────────────────── */
(function() use ($conn) {
    $backupDir = __DIR__ . '/../backup/';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

    $today    = date('Ymd');
    $namaFile = "autobackup_{$today}.sql";
    $filePath = $backupDir . $namaFile;
    if (file_exists($filePath)) return;

    $cekDb = $conn->query("SELECT id FROM backup_history WHERE filename='$namaFile' LIMIT 1");
    if ($cekDb && $cekDb->num_rows > 0) return;

    // Buat backup SQL sederhana (struktur + data tabel utama)
    $sql  = "-- TKA Kecamatan Auto Backup\n-- Tanggal: " . date('Y-m-d H:i:s') . "\n-- Database: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = ['peserta','sekolah','soal','kategori_soal','jadwal_ujian','token_ujian','ujian','jawaban','hasil_ujian','pengaturan','users'];
    foreach ($tables as $tbl) {
        $res = $conn->query("SELECT * FROM `$tbl`");
        if (!$res) continue;
        $sql .= "-- Tabel: $tbl\nTRUNCATE TABLE `$tbl`;\n";
        while ($row = $res->fetch_assoc()) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'", array_values($row));
            $sql .= "INSERT INTO `$tbl` VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
        $res->free();
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $written = @file_put_contents($filePath, $sql);
    if ($written !== false) {
        $ukuran = filesize($filePath);
        $fn     = $conn->real_escape_string($namaFile);
        $conn->query("INSERT INTO backup_history (filename, ukuran, dibuat_oleh) VALUES ('$fn', $ukuran, 'auto-daily')");
        logActivity($conn, 'Backup Otomatis', "Backup harian: $namaFile");
    }
})();

/* ── Helper: jalankan query scalar dengan aman ───────────── */
function queryScalar($conn, $sql, $col) {
    $r = $conn->query($sql);
    if (!$r) return null;
    $row = $r->fetch_assoc();
    $r->free();
    return $row[$col] ?? null;
}

/* ── Statistik ringkasan ──────────────────────────────────── */
$totalSekolah      = (int) queryScalar($conn, "SELECT COUNT(*) AS c FROM sekolah", 'c');
$totalPeserta      = (int) queryScalar($conn, "SELECT COUNT(*) AS c FROM peserta", 'c');
$totalSoal         = (int) queryScalar($conn, "SELECT COUNT(*) AS c FROM soal", 'c');
$totalKategori     = (int) queryScalar($conn, "SELECT COUNT(*) AS c FROM kategori_soal", 'c');
$totalUjianSelesai = (int) queryScalar($conn, "SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL", 'c');
$sedangUjian       = (int) queryScalar($conn, "SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL", 'c');
$nilaiRata         = (float)(queryScalar($conn, "SELECT ROUND(AVG(nilai),1) AS r FROM ujian WHERE waktu_selesai IS NOT NULL", 'r') ?? 0);
$pesertaSudah      = (int) queryScalar($conn, "SELECT COUNT(DISTINCT peserta_id) AS c FROM ujian WHERE waktu_selesai IS NOT NULL", 'c');

/* ── Token & Jadwal hari ini ─────────────────────────────── */
$today    = date('Y-m-d');
$nowTime  = date('H:i:s');
$_qTok = $conn->query(
    "SELECT token FROM token_ujian WHERE tanggal='$today' AND status='aktif' ORDER BY id DESC LIMIT 1"
);
$tokenHariIni = null;
if ($_qTok && $_qTok->num_rows > 0) { $tokenHariIni = $_qTok->fetch_assoc(); }
if ($_qTok) { $_qTok->free(); }

// Helper: query jadwal dengan IFNULL keterangan, fallback jika kolom belum ada
function queryJadwal($conn, $where) {
    $r = $conn->query("SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, status, kategori_id,
            IFNULL(keterangan,'') AS keterangan FROM jadwal_ujian WHERE $where LIMIT 1");
    if (!$r) {
        $conn->query("SELECT 1"); // flush error state
        $r = $conn->query("SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, status, kategori_id,
            '' AS keterangan FROM jadwal_ujian WHERE $where LIMIT 1");
    }
    $row = null;
    if ($r && $r->num_rows > 0) { $row = $r->fetch_assoc(); }
    if ($r) { $r->free(); }
    return is_array($row) ? $row : null;
}

$jadwalAktif     = queryJadwal($conn, "tanggal='$today' AND jam_mulai<='$nowTime' AND jam_selesai>='$nowTime' AND status='aktif'");
$jadwalBerikutnya = queryJadwal($conn, "tanggal>='$today' AND status='aktif' ORDER BY tanggal,jam_mulai");

/* ── Grafik 1: Rata-rata nilai per sekolah ───────────────── */
$gSek = $conn->query("
    SELECT s.nama_sekolah,
           ROUND(AVG(u.nilai),1) AS rata,
           COUNT(u.id) AS jml_ujian,
           MAX(u.nilai) AS maks,
           MIN(u.nilai) AS min
    FROM ujian u
    JOIN peserta p ON p.id=u.peserta_id
    JOIN sekolah s ON s.id=p.sekolah_id
    WHERE u.waktu_selesai IS NOT NULL
    GROUP BY s.id ORDER BY rata DESC LIMIT 10
");
$g1Labels=[]; $g1Rata=[]; $g1Maks=[]; $g1Min=[];
if ($gSek) { while ($g=$gSek->fetch_assoc()) {
    $g1Labels[]=$g['nama_sekolah']; $g1Rata[]=$g['rata']; $g1Maks[]=$g['maks']; $g1Min[]=$g['min'];
} $gSek->free(); }

/* ── Grafik 2: Jumlah peserta per sekolah ────────────────── */
$gPes = $conn->query("
    SELECT s.nama_sekolah, COUNT(p.id) AS jml,
           SUM(CASE WHEN u.waktu_selesai IS NOT NULL THEN 1 ELSE 0 END) AS sdh_ujian
    FROM sekolah s
    LEFT JOIN peserta p ON p.sekolah_id=s.id
    LEFT JOIN ujian u ON u.peserta_id=p.id AND u.waktu_selesai IS NOT NULL
    GROUP BY s.id ORDER BY jml DESC LIMIT 10
");
$g2Labels=[]; $g2Jml=[]; $g2Ujian=[];
if ($gPes) { while ($g=$gPes->fetch_assoc()) {
    $g2Labels[]=$g['nama_sekolah']; $g2Jml[]=(int)$g['jml']; $g2Ujian[]=(int)$g['sdh_ujian'];
} $gPes->free(); }

/* ── Grafik 3: Tren ujian 7 hari ─────────────────────────── */
$gTren = $conn->query("
    SELECT DATE(waktu_selesai) AS tgl,
           COUNT(*) AS jml,
           ROUND(AVG(nilai),1) AS rata
    FROM ujian WHERE waktu_selesai IS NOT NULL
      AND waktu_selesai >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY tgl ORDER BY tgl
");
$g3Labels=[]; $g3Jml=[]; $g3Rata=[];
if ($gTren) { while ($g=$gTren->fetch_assoc()) {
    $g3Labels[]=date('d/m',strtotime($g['tgl'])); $g3Jml[]=$g['jml']; $g3Rata[]=$g['rata'];
} $gTren->free(); }

/* ── Grafik 4: Distribusi nilai ──────────────────────────── */
$dist = ['A (90-100)'=>0,'B (80-89)'=>0,'C (70-79)'=>0,'D (60-69)'=>0,'E (<60)'=>0];
$distRes = $conn->query("SELECT nilai FROM ujian WHERE waktu_selesai IS NOT NULL AND nilai IS NOT NULL");
if ($distRes) { while ($d=$distRes->fetch_assoc()) {
    $n=(int)$d['nilai'];
    if ($n>=90) $dist['A (90-100)']++;
    elseif ($n>=80) $dist['B (80-89)']++;
    elseif ($n>=70) $dist['C (70-79)']++;
    elseif ($n>=60) $dist['D (60-69)']++;
    else $dist['E (<60)']++;
} $distRes->free(); }

/* ── Nilai terbaru ────────────────────────────────────────── */
$nilaiTerbaru = $conn->query("
    SELECT u.nilai, u.waktu_selesai,
           p.nama, p.kelas, s.nama_sekolah
    FROM ujian u
    JOIN peserta p ON p.id=u.peserta_id
    LEFT JOIN sekolah s ON s.id=p.sekolah_id
    WHERE u.waktu_selesai IS NOT NULL
    ORDER BY u.waktu_selesai DESC LIMIT 6
");

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>


<!-- Welcome Banner -->
<div class="card mb-4 border-0" style="background:linear-gradient(135deg,#1a56db 0%,#7c3aed 60%,#0ea5e9 100%);color:#fff;overflow:hidden;position:relative">
    <div class="card-body py-4 px-4">
        <div class="d-flex align-items-center gap-3">
            <?php
            $lgF = getSetting($conn,'logo_file_path','');
            $lgU = getSetting($conn,'logo_url','');
            $lgA = $lgF ? BASE_URL.'/'.$lgF : $lgU;
            ?>
            <?php if ($lgA): ?>
            <img src="<?= htmlspecialchars($lgA) ?>" alt="Logo"
                 style="width:44px;height:44px;object-fit:contain"
                 onerror="this.outerHTML='<div style=\'font-size:44px;line-height:1\'>🏫</div>'">
            <?php else: ?>
            <div style="font-size:44px;line-height:1">🏫</div>
            <?php endif; ?>
            <div>
                <h4 class="fw-800 mb-1" style="color:#fff">
                    Selamat Datang, <?= e($_SESSION['nama'] ?? 'Admin') ?>!
                </h4>
                <p class="mb-0 small" style="color:rgba(255,255,255,.8)">
                    <?= date('l, d F Y') ?> &nbsp;·&nbsp; Panel Admin Kecamatan TKA
                </p>
            </div>
        </div>
    </div>
    <!-- Decorative circles -->
    <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.06)"></div>
    <div style="position:absolute;bottom:-20px;right:60px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.04)"></div>
</div>

<!-- Realtime Stats Panel -->
<div class="card mb-4 border-0 shadow-sm" id="realtimePanel">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <span class="live-badge me-2">● LIVE</span>
            <strong>Statistik Realtime</strong>
            <small class="text-muted ms-2">diperbarui otomatis setiap 10 detik</small>
        </span>
        <small class="text-muted" id="rtLastUpdate">–</small>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3" style="background:#eff6ff;border:1.5px solid #bfdbfe">
                    <div class="fw-900 text-primary" style="font-size:36px" id="rtOnline"><?= $sedangUjian ?></div>
                    <div class="text-muted small mt-1">🟢 Sedang Ujian</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3" style="background:#f0fdf4;border:1.5px solid #bbf7d0">
                    <div class="fw-900 text-success" style="font-size:36px" id="rtSelesai"><?= $totalUjianSelesai ?></div>
                    <div class="text-muted small mt-1">✅ Selesai Hari Ini</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3" style="background:#fefce8;border:1.5px solid #fde68a">
                    <div class="fw-900 text-warning" style="font-size:36px" id="rtTotal"><?= $totalPeserta ?></div>
                    <div class="text-muted small mt-1">👥 Total Peserta</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3" style="background:#fdf4ff;border:1.5px solid #e9d5ff">
                    <div class="fw-900 text-purple" style="font-size:36px;color:#7c3aed" id="rtNilai"><?= $nilaiRata ?></div>
                    <div class="text-muted small mt-1">📊 Rata-rata Nilai</div>
                </div>
            </div>
        </div>
        <!-- Baru selesai -->
        <div id="rtBaruSelesai" class="mt-3" style="display:none">
            <div class="small fw-600 text-muted mb-2">🕐 Baru selesai (5 menit terakhir):</div>
            <div id="rtBaruList" class="d-flex flex-wrap gap-2"></div>
        </div>
    </div>
</div>

<?= renderFlash() ?>

<!-- CBT Status Bar -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100 border-0" style="border-left:4px solid <?= is_array($jadwalAktif)?'#10b981':'#e2e8f0' ?> !important;border-left-width:4px !important">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon <?= is_array($jadwalAktif) ? 'green' : 'teal' ?>">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <?php if (is_array($jadwalAktif)): ?>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="live-badge">● LIVE</span>
                            <span class="fw-700 small">Ujian Sedang Berlangsung</span>
                        </div>
                        <div class="text-muted text-sm">
                            <?= substr($jadwalAktif['jam_mulai'],0,5) ?> – <?= substr($jadwalAktif['jam_selesai'],0,5) ?>
                            &nbsp;·&nbsp; <?= $jadwalAktif['durasi_menit'] ?> menit
                        </div>
                        <?php elseif (is_array($jadwalBerikutnya)): ?>
                        <div class="fw-700 small mb-1">Jadwal Berikutnya</div>
                        <div class="text-muted text-sm">
                            <?= formatTanggal($jadwalBerikutnya['tanggal']) ?>
                            &nbsp;·&nbsp; <?= substr($jadwalBerikutnya['jam_mulai'],0,5) ?>
                        </div>
                        <?php else: ?>
                        <div class="text-muted small">Tidak ada jadwal aktif</div>
                        <?php endif; ?>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/jadwal.php" class="btn btn-sm btn-outline-secondary">
                        Kelola
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 border-0" style="border-left:4px solid <?= $tokenHariIni?'#f59e0b':'#e2e8f0' ?> !important;border-left-width:4px !important">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon orange"><i class="bi bi-key-fill"></i></div>
                    <div class="flex-grow-1">
                        <?php if ($tokenHariIni): ?>
                        <div class="text-muted text-sm mb-1">Token Ujian Hari Ini</div>
                        <div>
                            <code class="fw-800 text-primary" style="font-size:18px;letter-spacing:3px">
                                <?= e($tokenHariIni['token']) ?>
                            </code>
                            <button class="btn btn-xs btn-outline-secondary ms-2"
                                    onclick="navigator.clipboard.writeText('<?= e($tokenHariIni['token']) ?>');this.innerHTML='✓ Disalin'"
                                    style="font-size:10px">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="text-muted small">Belum ada token untuk hari ini</div>
                        <a href="<?= BASE_URL ?>/admin/token.php" class="btn btn-xs btn-outline-primary mt-1">Buat Token</a>
                        <?php endif; ?>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/token.php" class="btn btn-sm btn-outline-secondary">
                        Kelola
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($sedangUjian > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
    <div class="live-badge">● LIVE</div>
    <div>
        <strong><?= $sedangUjian ?> peserta sedang mengerjakan ujian!</strong>
    </div>
    <a href="<?= BASE_URL ?>/admin/monitoring.php" class="btn btn-sm btn-warning ms-auto">
        <i class="bi bi-display me-1"></i>Monitoring
    </a>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-4 col-xl-2">
        <a href="<?= BASE_URL ?>/admin/sekolah.php" class="stat-card d-flex">
            <div class="stat-icon blue"><i class="bi bi-building"></i></div>
            <div><div class="stat-label">Sekolah</div><div class="stat-value"><?= $totalSekolah ?></div></div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <a href="<?= BASE_URL ?>/admin/peserta.php" class="stat-card d-flex">
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-label">Peserta</div><div class="stat-value"><?= $totalPeserta ?></div></div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <a href="<?= BASE_URL ?>/admin/soal.php" class="stat-card d-flex">
            <div class="stat-icon orange"><i class="bi bi-question-circle-fill"></i></div>
            <div>
                <div class="stat-label">Bank Soal</div>
                <div class="stat-value"><?= $totalSoal ?></div>
                <div class="stat-sub"><?= $totalKategori ?> kategori</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <a href="<?= BASE_URL ?>/admin/hasil.php" class="stat-card d-flex">
            <div class="stat-icon purple"><i class="bi bi-clipboard-check-fill"></i></div>
            <div>
                <div class="stat-label">Ujian Selesai</div>
                <div class="stat-value"><?= $totalUjianSelesai ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-bar-chart-fill"></i></div>
            <div>
                <div class="stat-label">Rata-rata</div>
                <div class="stat-value"><?= $nilaiRata ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-activity"></i></div>
            <div>
                <div class="stat-label">Sedang Ujian</div>
                <div class="stat-value"><?= $sedangUjian ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Row 1 -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                Nilai Tertinggi, Rata-rata & Terendah per Sekolah
            </div>
            <div class="card-body">
                <canvas id="chartSekolah" height="120"></canvas>
                <?php if (!$g1Labels): ?>
                <p class="text-muted text-center small mt-3">Belum ada data ujian</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart-fill text-success me-2"></i>Distribusi Predikat
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="chartDist" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Row 2 -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-people-fill text-info me-2"></i>Peserta per Sekolah
                <span class="text-muted small ms-2">(Total vs Sudah Ujian)</span>
            </div>
            <div class="card-body">
                <canvas id="chartPeserta" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-graph-up text-warning me-2"></i>Tren Ujian 7 Hari Terakhir
            </div>
            <div class="card-body">
                <canvas id="chartTren" height="140"></canvas>
                <?php if (!$g3Labels): ?>
                <p class="text-muted text-center small mt-3">Belum ada data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="row g-3">
    <!-- Quick links -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightning-fill text-warning me-2"></i>Akses Cepat</div>
            <div class="card-body d-flex flex-column gap-2 p-3">
                <?php
                $links = [
                    ['url'=>'admin/sekolah.php','icon'=>'bi-building','color'=>'primary','label'=>'Tambah Sekolah'],
                    ['url'=>'admin/peserta.php','icon'=>'bi-person-plus','color'=>'success','label'=>'Tambah Peserta'],
                    ['url'=>'admin/soal.php','icon'=>'bi-plus-circle','color'=>'warning','label'=>'Tambah Soal Baru'],
                    ['url'=>'admin/import_soal.php','icon'=>'bi-file-earmark-excel','color'=>'info','label'=>'Import Soal Excel'],
                    ['url'=>'admin/token.php','icon'=>'bi-key-fill','color'=>'orange','label'=>'Buat Token Ujian'],
                    ['url'=>'admin/hasil.php','icon'=>'bi-trophy-fill','color'=>'danger','label'=>'Lihat Ranking'],
                ];
                foreach ($links as $l): ?>
                <a href="<?= BASE_URL ?>/<?= $l['url'] ?>"
                   class="btn btn-outline-<?= $l['color'] ?> text-start d-flex align-items-center gap-2 py-2">
                    <i class="<?= $l['icon'] ?>"></i><?= $l['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Nilai terbaru -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock-history me-2"></i>Nilai Ujian Terbaru</span>
                <a href="<?= BASE_URL ?>/admin/hasil.php" class="btn btn-sm btn-outline-primary">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Nama Peserta</th><th>Sekolah</th>
                        <th class="text-center">Nilai</th><th class="text-center">Predikat</th>
                        <th>Waktu</th>
                    </tr></thead>
                    <tbody>
                    <?php if ($nilaiTerbaru && $nilaiTerbaru->num_rows > 0):
                          while ($r = $nilaiTerbaru->fetch_assoc()):
                          [$ph,$pt,$pb] = getPredikat((int)$r['nilai']); ?>
                    <tr>
                        <td>
                            <strong><?= e($r['nama']) ?></strong><br>
                            <span class="text-muted text-xs"><?= e($r['kelas'] ?? '') ?></span>
                        </td>
                        <td class="text-sm"><?= e($r['nama_sekolah'] ?? '-') ?></td>
                        <td class="text-center">
                            <strong class="fs-6"><?= $r['nilai'] ?></strong>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $pb ?>"><?= $ph ?> <?= $pt ?></span>
                        </td>
                        <td class="text-muted text-xs">
                            <?= $r['waktu_selesai'] ? date('d/m H:i', strtotime($r['waktu_selesai'])) : '-' ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada ujian selesai</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// ── Chart 1: Nilai tertinggi, rata, terendah per sekolah ──
new Chart(document.getElementById('chartSekolah'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($g1Labels) ?>,
        datasets: [
            {
                label: 'Tertinggi',
                data:  <?= json_encode($g1Maks) ?>,
                backgroundColor: 'rgba(16,185,129,.7)',
                borderRadius: 4,
            },
            {
                label: 'Rata-rata',
                data:  <?= json_encode($g1Rata) ?>,
                backgroundColor: 'rgba(37,99,235,.8)',
                borderRadius: 4,
            },
            {
                label: 'Terendah',
                data:  <?= json_encode($g1Min) ?>,
                backgroundColor: 'rgba(239,68,68,.65)',
                borderRadius: 4,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
            y: { beginAtZero: true, max: 100 },
            x: { ticks: { font: { size: 10 }, maxRotation: 30 } }
        }
    }
});

// ── Chart 2: Distribusi predikat ──────────────────────────
new Chart(document.getElementById('chartDist'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($dist)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($dist)) ?>,
            backgroundColor: ['#0e9f6e','#22c55e','#0ea5e9','#f59e0b','#ef4444'],
            borderWidth: 2, borderColor: '#fff',
        }]
    },
    options: {
        cutout: '58%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8 } }
        }
    }
});

// ── Chart 3: Peserta per sekolah (total vs sudah ujian) ───
new Chart(document.getElementById('chartPeserta'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($g2Labels) ?>,
        datasets: [
            { label: 'Total Peserta', data: <?= json_encode($g2Jml) ?>,
              backgroundColor: 'rgba(37,99,235,.25)', borderColor: '#2563eb', borderWidth: 1.5, borderRadius: 4 },
            { label: 'Sudah Ujian', data: <?= json_encode($g2Ujian) ?>,
              backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: { y: { beginAtZero: true }, x: { ticks: { font: { size: 10 }, maxRotation: 30 } } }
    }
});

// ── Realtime Stats AJAX ───────────────────────────────────────
function refreshRealtimeStats() {
    fetch('<?= BASE_URL ?>/admin/ajax_statistik.php')
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d) return;
            document.getElementById('rtOnline').textContent  = d.peserta_ujian;
            document.getElementById('rtSelesai').textContent = d.peserta_selesai;
            document.getElementById('rtTotal').textContent   = d.total_peserta;
            document.getElementById('rtNilai').textContent   = d.nilai_rata || '–';
            document.getElementById('rtLastUpdate').textContent = 'Update: ' + d.timestamp;

            // Baru selesai
            const divBaru  = document.getElementById('rtBaruSelesai');
            const listBaru = document.getElementById('rtBaruList');
            if (d.baru_selesai && d.baru_selesai.length > 0) {
                divBaru.style.display = '';
                listBaru.innerHTML = d.baru_selesai.map(p =>
                    `<span class="badge bg-success px-2 py-1">
                        ${p.nama} <small class="opacity-75">(${p.sekolah})</small>
                        <strong class="ms-1">${p.nilai}</strong>
                    </span>`
                ).join('');
            } else {
                divBaru.style.display = 'none';
            }
        })
        .catch(() => {});
}
setInterval(refreshRealtimeStats, 10000);

// ── Chart 4: Tren ujian 7 hari ────────────────────────────
new Chart(document.getElementById('chartTren'), {
    type: 'line',
    data: {
        labels: <?= json_encode($g3Labels) ?>,
        datasets: [
            {
                label: 'Jumlah Ujian',
                data:  <?= json_encode($g3Jml) ?>,
                borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.08)',
                fill: true, tension: .4, yAxisID: 'y',
                pointBackgroundColor: '#2563eb', pointRadius: 4,
            },
            {
                label: 'Rata-rata Nilai',
                data:  <?= json_encode($g3Rata) ?>,
                borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.06)',
                fill: true, tension: .4, yAxisID: 'y1',
                pointBackgroundColor: '#10b981', pointRadius: 4,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
            y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'Ujian' } },
            y1: { beginAtZero: true, max: 100, position: 'right', grid: { drawOnChartArea: false },
                  title: { display: true, text: 'Nilai' } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
