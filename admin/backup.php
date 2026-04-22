<?php
// ============================================================
// admin/backup.php — Backup & Restore Database
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$backupDir  = __DIR__ . '/../backup/';
$backupUrl  = BASE_URL . '/backup/';

// Pastikan folder backup ada dan terlindungi
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
// Buat .htaccess agar tidak bisa diakses langsung lewat browser (Apache)
$htaFile = $backupDir . '.htaccess';
if (!file_exists($htaFile)) {
    file_put_contents($htaFile, "Options -Indexes\n<FilesMatch \"\\.sql$\">\n    Require all denied\n</FilesMatch>\n");
}

// ── Fungsi generate backup SQL ────────────────────────────────
function generateBackupSQL(mysqli $db): string {
    $sql  = "-- TKA Kecamatan Database Backup\n";
    $sql .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Server:  " . DB_HOST . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $db->query("SHOW TABLES");
    while ($t = $tables->fetch_array()) {
        $tbl = $t[0];

        // DROP + CREATE TABLE
        $create = $db->query("SHOW CREATE TABLE `$tbl`")->fetch_assoc();
        $sql .= "-- -----------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `$tbl`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        // INSERT DATA
        $rows = $db->query("SELECT * FROM `$tbl`");
        if ($rows && $rows->num_rows > 0) {
            $sql .= "INSERT INTO `$tbl` VALUES\n";
            $inserts = [];
            while ($row = $rows->fetch_row()) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = "'" . $db->real_escape_string($v) . "'";
                    }
                }
                $inserts[] = '(' . implode(',', $vals) . ')';
            }
            $sql .= implode(",\n", $inserts) . ";\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// ── Handle: Download file backup ──────────────────────────────
if (isset($_GET['download']) && $_GET['download']) {
    $filename = basename($_GET['download']);
    $filepath = $backupDir . $filename;
    if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    setFlash('error', 'File backup tidak ditemukan.');
    redirect(BASE_URL . '/admin/backup.php');
}

// ── Handle: Hapus file backup ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $filename = basename($_POST['hapus']);
    $filepath = $backupDir . $filename;
    if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'sql') {
        unlink($filepath);
        // Hapus dari tabel history
        $fn = $conn->real_escape_string($filename);
        $conn->query("DELETE FROM backup_history WHERE filename='$fn'");
        logActivity($conn, 'Hapus backup', $filename);
        setFlash('success', 'File backup berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/backup.php');
}

// ── Handle: Backup manual ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_manual'])) {
    $filename = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . $filename;

    $sqlContent = generateBackupSQL($conn);
    $written    = file_put_contents($filepath, $sqlContent);

    if ($written !== false) {
        $ukuran   = filesize($filepath);
        $user     = $_SESSION['username'];
        $fn       = $conn->real_escape_string($filename);
        $userEsc  = $conn->real_escape_string($user);
        $conn->query("INSERT INTO backup_history (filename, ukuran, dibuat_oleh) VALUES ('$fn', $ukuran, '$userEsc')");
        logActivity($conn, 'Backup database manual', $filename);
        setFlash('success', "Backup berhasil dibuat: <strong>$filename</strong> (" . number_format($ukuran/1024, 1) . " KB)");
    } else {
        setFlash('error', 'Gagal membuat file backup. Periksa permission folder /backup/.');
    }
    redirect(BASE_URL . '/admin/backup.php');
}

// ── Auto backup harian (cek jika hari ini belum ada backup) ───
$_qBak = $conn->query("SELECT id FROM backup_history WHERE DATE(created_at)=CURDATE() LIMIT 1");
$todayBackup = ($_qBak && $_qBak->num_rows > 0) ? $_qBak->fetch_assoc() : null;
if ($_qBak) $_qBak->free();

$autoBackupRan = false;
if (!$todayBackup && isset($_GET['autobackup'])) {
    $filename = 'autobackup_' . DB_NAME . '_' . date('Ymd') . '.sql';
    $filepath = $backupDir . $filename;
    if (!file_exists($filepath)) {
        $sqlContent = generateBackupSQL($conn);
        $written    = file_put_contents($filepath, $sqlContent);
        if ($written !== false) {
            $ukuran  = filesize($filepath);
            $fn      = $conn->real_escape_string($filename);
            $conn->query("INSERT INTO backup_history (filename, ukuran, dibuat_oleh) VALUES ('$fn', $ukuran, 'auto')");
            logActivity($conn, 'Backup otomatis harian', $filename);
            $autoBackupRan = true;
        }
    }
}

// ── Ambil daftar backup ───────────────────────────────────────
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    if ($files) {
        rsort($files); // terbaru dulu
        foreach ($files as $f) {
            $fn   = basename($f);
            $size = filesize($f);
            $time = filemtime($f);
            // Ambil info dari DB
            $fnEsc  = $conn->real_escape_string($fn);
            $_qHist = $conn->query("SELECT * FROM backup_history WHERE filename='$fnEsc' LIMIT 1"); $histRow = ($_qHist && $_qHist->num_rows>0) ? $_qHist->fetch_assoc() : null;
            $backupFiles[] = [
                'filename'  => $fn,
                'filepath'  => $f,
                'size'      => $size,
                'time'      => $time,
                'dibuat'    => $histRow['dibuat_oleh'] ?? 'manual',
            ];
        }
    }
}

$pageTitle  = 'Backup Database';
$activeMenu = 'backup';
require_once __DIR__ . '/../includes/header.php';
?>

<?= renderFlash() ?>

<?php if (!$todayBackup): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>
        <strong>Backup hari ini belum ada!</strong>
        Disarankan melakukan backup setiap hari sebelum sesi ujian dimulai.
        <a href="?autobackup=1" class="alert-link ms-2">Jalankan Auto Backup Sekarang →</a>
    </div>
</div>
<?php endif; ?>

<?php if ($autoBackupRan): ?>
<div class="alert alert-success">✅ Auto backup harian berhasil dijalankan.</div>
<?php endif; ?>

<!-- Action Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon blue" style="width:48px;height:48px">
                        <i class="bi bi-database-down fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-700">Backup Manual</h6>
                        <small class="text-muted">Buat snapshot database sekarang</small>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Menghasilkan file <code>.sql</code> berisi seluruh struktur dan data database
                    <strong><?= DB_NAME ?></strong>. File dapat didownload dan disimpan sebagai cadangan.
                </p>
                <form method="POST">
<?= csrfField() ?>
                    <button type="submit" name="backup_manual" value="1"
                            class="btn btn-primary w-100"
                            onclick="return confirm('Buat backup database sekarang?')">
                        <i class="bi bi-database-down me-2"></i>Backup Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon green" style="width:48px;height:48px">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-700">Auto Backup Harian</h6>
                        <small class="text-muted">Otomatis setiap hari saat halaman ini dibuka</small>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Sistem akan otomatis membuat backup jika hari ini belum ada backup.
                    Disarankan untuk mengunjungi halaman ini setiap pagi sebelum ujian dimulai.
                </p>
                <div class="d-flex gap-2">
                    <a href="?autobackup=1" class="btn btn-outline-success flex-grow-1">
                        <i class="bi bi-play-circle me-1"></i>Jalankan Auto Backup
                    </a>
                    <span class="badge <?= $todayBackup ? 'bg-success' : 'bg-warning text-dark' ?> d-flex align-items-center px-3">
                        <?= $todayBackup ? '✓ Sudah' : '⚠ Belum' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Info -->
<div class="alert alert-info d-flex gap-2 mb-4">
    <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>Lokasi file backup:</strong> <code><?= htmlspecialchars($backupDir) ?></code><br>
        File backup tidak bisa diakses langsung via browser (dilindungi .htaccess).
        Gunakan tombol <strong>Download</strong> di bawah untuk mengambil file.
    </div>
</div>

<!-- Daftar Backup -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-archive-fill text-primary me-2"></i>Riwayat Backup (<?= count($backupFiles) ?> file)</span>
        <span class="text-muted small">Total ruang: <?= number_format(array_sum(array_column($backupFiles, 'size')) / 1024 / 1024, 2) ?> MB</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($backupFiles)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-archive fs-1 d-block mb-2 opacity-25"></i>
            Belum ada file backup. Klik "Backup Sekarang" untuk membuat backup pertama.
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama File</th>
                    <th>Ukuran</th>
                    <th>Jenis</th>
                    <th>Waktu Dibuat</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backupFiles as $i => $bf): ?>
            <tr>
                <td class="text-muted small"><?= $i + 1 ?></td>
                <td>
                    <i class="bi bi-file-earmark-zip text-primary me-1"></i>
                    <code class="small"><?= htmlspecialchars($bf['filename']) ?></code>
                </td>
                <td class="small"><?= number_format($bf['size'] / 1024, 1) ?> KB</td>
                <td>
                    <?php if (str_starts_with($bf['filename'], 'auto')): ?>
                    <span class="badge bg-info text-dark">🤖 Auto</span>
                    <?php else: ?>
                    <span class="badge bg-primary">👤 Manual</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?= date('d/m/Y H:i', $bf['time']) ?></td>
                <td class="text-center">
                    <a href="?download=<?= urlencode($bf['filename']) ?>"
                       class="btn btn-sm btn-outline-primary me-1">
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                    <form method="POST" class="d-inline"
                          onsubmit="return confirm('Hapus file backup ini?')">
<?= csrfField() ?>
                        <input type="hidden" name="hapus" value="<?= htmlspecialchars($bf['filename']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 p-3 bg-light rounded small text-muted">
    <i class="bi bi-lightbulb-fill text-warning me-1"></i>
    <strong>Tips Auto Backup Otomatis (Windows Task Scheduler):</strong><br>
    Buat task baru di Task Scheduler yang membuka URL berikut setiap hari jam 06:00:<br>
    <code><?= BASE_URL ?>/admin/backup.php?autobackup=1&key=<?= md5(DB_NAME . 'autobackup') ?></code>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
