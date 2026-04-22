<?php
// Proteksi: blokir akses jika aplikasi sudah terinstall
if (file_exists(__DIR__ . '/../config/database.php')) {
    @include_once __DIR__ . '/../config/database.php';
    if (defined('DB_HOST')) {
        $testConn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$testConn->connect_error) {
            $testConn->close();
            http_response_code(403);
            die('<div style="font-family:sans-serif;padding:40px;color:#c00"><h2>403 — Akses ditolak.</h2><p>Aplikasi sudah terinstall. Hapus folder <code>install/</code> dari server untuk keamanan.</p></div>');
        }
    }
}
?>
<?php
// ============================================================
// install/install.php — Wizard Instalasi Database TKA Kecamatan
//
// Skema tabel sesuai spesifikasi:
//   users, sekolah, peserta, kategori_soal, soal, ujian, jawaban
// ============================================================

define('INSTALL_DB_HOST', 'localhost');
define('INSTALL_DB_USER', 'root');
define('INSTALL_DB_PASS', '');
define('INSTALL_DB_NAME', 'tka_kecamatan');
define('INSTALL_BASE_URL', 'http://localhost/tka-kecamatan');

$step    = (int)($_GET['step'] ?? 1);
$logs    = [];
$success = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {

    $conn = new mysqli(INSTALL_DB_HOST, INSTALL_DB_USER, INSTALL_DB_PASS);

    if ($conn->connect_error) {
        $success = false;
        $logs[]  = '❌ Koneksi MySQL gagal: ' . $conn->connect_error;
    } else {
        $conn->set_charset('utf8mb4');

        $ddl = [
            "CREATE DATABASE IF NOT EXISTS `tka_kecamatan` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            "USE `tka_kecamatan`",

            // sekolah — harus lebih dulu karena direferensikan FK
            "CREATE TABLE IF NOT EXISTS `sekolah` (
                `id`           INT          NOT NULL AUTO_INCREMENT,
                `nama_sekolah` VARCHAR(150) NOT NULL,
                `npsn`         VARCHAR(20)  DEFAULT NULL,
                `alamat`       TEXT         DEFAULT NULL,
                `telepon`      VARCHAR(20)  DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // users
            "CREATE TABLE IF NOT EXISTS `users` (
                `id`         INT          NOT NULL AUTO_INCREMENT,
                `username`   VARCHAR(50)  NOT NULL,
                `password`   VARCHAR(255) NOT NULL,
                `role`       VARCHAR(20)  NOT NULL DEFAULT 'sekolah',
                `sekolah_id` INT          DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_username` (`username`),
                CONSTRAINT `fk_users_sekolah`
                    FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah`(`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // peserta
            "CREATE TABLE IF NOT EXISTS `peserta` (
                `id`           INT          NOT NULL AUTO_INCREMENT,
                `nama`         VARCHAR(100) NOT NULL,
                `kelas`        VARCHAR(10)  DEFAULT NULL,
                `sekolah_id`   INT          NOT NULL,
                `kode_peserta` VARCHAR(20)  DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_kode_peserta` (`kode_peserta`),
                CONSTRAINT `fk_peserta_sekolah`
                    FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah`(`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // kategori_soal
            "CREATE TABLE IF NOT EXISTS `kategori_soal` (
                `id`            INT          NOT NULL AUTO_INCREMENT,
                `nama_kategori` VARCHAR(100) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // soal
            "CREATE TABLE IF NOT EXISTS `soal` (
                `id`            INT          NOT NULL AUTO_INCREMENT,
                `kategori_id`   INT          NOT NULL,
                `pertanyaan`    TEXT         NOT NULL,
                `gambar`        VARCHAR(255) DEFAULT NULL,
                `pilihan_a`     TEXT         DEFAULT NULL,
                `pilihan_b`     TEXT         DEFAULT NULL,
                `pilihan_c`     TEXT         DEFAULT NULL,
                `pilihan_d`     TEXT         DEFAULT NULL,
                `jawaban_benar` CHAR(1)      NOT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_soal_kategori`
                    FOREIGN KEY (`kategori_id`) REFERENCES `kategori_soal`(`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ujian
            "CREATE TABLE IF NOT EXISTS `ujian` (
                `id`            INT      NOT NULL AUTO_INCREMENT,
                `peserta_id`    INT      NOT NULL,
                `waktu_mulai`   DATETIME DEFAULT NULL,
                `waktu_selesai` DATETIME DEFAULT NULL,
                `nilai`         INT      DEFAULT NULL,
                `token_id`      INT      DEFAULT NULL,
                `jadwal_id`     INT      DEFAULT NULL,
                `soal_order`    TEXT     DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_ujian_peserta`
                    FOREIGN KEY (`peserta_id`) REFERENCES `peserta`(`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // jawaban — UNIQUE KEY (ujian_id, soal_id) untuk UPSERT
            "CREATE TABLE IF NOT EXISTS `jawaban` (
                `id`       INT    NOT NULL AUTO_INCREMENT,
                `ujian_id` INT    NOT NULL,
                `soal_id`  INT    NOT NULL,
                `jawaban`  CHAR(1) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ujian_soal` (`ujian_id`, `soal_id`),
                CONSTRAINT `fk_jawaban_ujian`
                    FOREIGN KEY (`ujian_id`) REFERENCES `ujian`(`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_jawaban_soal`
                    FOREIGN KEY (`soal_id`) REFERENCES `soal`(`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // token_ujian
            "CREATE TABLE IF NOT EXISTS `token_ujian` (
                `id`         INT          NOT NULL AUTO_INCREMENT,
                `token`      VARCHAR(20)  NOT NULL,
                `tanggal`    DATE         NOT NULL,
                `status`     ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
                `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // jadwal_ujian
            "CREATE TABLE IF NOT EXISTS `jadwal_ujian` (
                `id`           INT      NOT NULL AUTO_INCREMENT,
                `tanggal`      DATE     NOT NULL,
                `jam_mulai`    TIME     NOT NULL,
                `jam_selesai`  TIME     NOT NULL,
                `durasi_menit` INT      NOT NULL DEFAULT 60,
                `keterangan`   VARCHAR(200) DEFAULT NULL,
                `status`       ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
                `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($ddl as $sql) {
            $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $sql)), 0, 70);
            if ($conn->query($sql)) {
                $logs[] = "✅ " . htmlspecialchars($preview) . "…";
            } else {
                $logs[] = "❌ GAGAL: " . htmlspecialchars($conn->error)
                        . "<br><small><code>" . htmlspecialchars($preview) . "…</code></small>";
                $success = false;
                break;
            }
        }

        if ($success) {
            // Seed: admin_kecamatan
            $passAdmin = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO `users` (username, password, role, sekolah_id) VALUES (?, ?, 'admin_kecamatan', NULL)"
            );
            $uAdmin = 'admin';
            $stmt->bind_param('ss', $uAdmin, $passAdmin);
            $stmt->execute();
            $stmt->close();
            $logs[] = "✅ Akun <strong>admin</strong> / <strong>admin123</strong> (role: admin_kecamatan) dibuat.";

            // Seed: sekolah contoh
            $conn->query("INSERT IGNORE INTO `sekolah` (id, nama_sekolah, npsn, alamat, telepon) VALUES
                (1,'SDN 01 Contoh','12345678','Jl. Merdeka No.1','021-1234'),
                (2,'SDN 02 Contoh','87654321','Jl. Pahlawan No.2','021-5678')");
            $logs[] = "✅ 2 sekolah contoh ditambahkan.";

            // Seed: operator sekolah
            $passSkl = password_hash('sekolah123', PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare(
                "INSERT IGNORE INTO `users` (username, password, role, sekolah_id) VALUES (?, ?, 'sekolah', 1)"
            );
            $uSkl = 'sekolah1';
            $stmt2->bind_param('ss', $uSkl, $passSkl);
            $stmt2->execute();
            $stmt2->close();
            $logs[] = "✅ Akun <strong>sekolah1</strong> / <strong>sekolah123</strong> (role: sekolah) dibuat.";

            // Seed: kategori soal
            $conn->query("INSERT IGNORE INTO `kategori_soal` (id, nama_kategori) VALUES
                (1,'Matematika'),(2,'Bahasa Indonesia'),(3,'IPA'),(4,'IPS')");
            $logs[] = "✅ 4 kategori soal ditambahkan.";

            // Seed: token ujian contoh (hari ini)
            $tokenContoh = 'TKA' . strtoupper(substr(md5('demo'), 0, 6));
            $tanggalHariIni = date('Y-m-d');
            $conn->query("INSERT IGNORE INTO `token_ujian` (token, tanggal, status)
                          VALUES ('$tokenContoh', '$tanggalHariIni', 'aktif')");
            $logs[] = "✅ Token ujian contoh: <strong>$tokenContoh</strong> (hari ini, aktif).";

            // Seed: jadwal ujian contoh (hari ini, 07:00-17:00, 60 menit)
            $conn->query("INSERT IGNORE INTO `jadwal_ujian` (tanggal, jam_mulai, jam_selesai, durasi_menit, keterangan, status)
                          VALUES ('$tanggalHariIni', '07:00:00', '17:00:00', 60, 'Jadwal Ujian Contoh', 'aktif')");
            $logs[] = "✅ Jadwal ujian contoh hari ini 07:00–17:00 (60 menit) dibuat.";
            $logs[] = "✅ 4 kategori soal ditambahkan.";

            $logs[] = "<hr><strong class='text-success'>🎉 Instalasi selesai!</strong> Redirect dalam 2 detik…";
            header('refresh:2;url=?step=2');
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer — TKA Kecamatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:linear-gradient(135deg,#0f172a,#1e3a5f); min-height:100vh;
               display:flex; align-items:center; justify-content:center; padding:24px;
               font-family:'Segoe UI',sans-serif; }
        .card-install { max-width:680px; width:100%; background:#fff; border-radius:20px;
                        padding:40px; box-shadow:0 25px 60px rgba(0,0,0,.35); }
        .step-badge { display:inline-flex; align-items:center; justify-content:center;
                      width:32px; height:32px; border-radius:50%; font-weight:700; font-size:14px; }
        .log-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
                   padding:16px; max-height:300px; overflow-y:auto; font-size:13px; line-height:1.9; }
        code { background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12px; }
    </style>
</head>
<body>
<div class="card-install">
    <div class="text-center mb-4">
        <div style="font-size:52px">🏫</div>
        <h3 class="fw-bold mt-2 mb-0">TKA Kecamatan</h3>
        <p class="text-muted">Wizard Instalasi Database</p>
    </div>

    <div class="d-flex align-items-center gap-2 justify-content-center mb-4">
        <span class="step-badge <?= $step>=1?'bg-primary text-white':'bg-light border text-muted' ?>">1</span>
        <small class="text-muted">Install DB</small>
        <div style="width:50px;border-top:1px solid #e2e8f0"></div>
        <span class="step-badge <?= $step>=2?'bg-success text-white':'bg-light border text-muted' ?>">2</span>
        <small class="text-muted">Selesai</small>
    </div>

    <?php if ($step === 1): ?>

    <h5 class="fw-bold mb-3">
        <i class="bi bi-database-fill-gear me-2 text-primary"></i>Instalasi Database
    </h5>

    <?php if ($logs): ?>
    <div class="log-box mb-4"><?php foreach($logs as $l) echo "<div>$l</div>"; ?></div>
    <?php if (!$success): ?>
    <div class="alert alert-danger mb-3">
        <i class="bi bi-x-circle me-2"></i>Instalasi gagal. Periksa konfigurasi MySQL dan coba lagi.
    </div>
    <form method="POST" action="?step=1">
        <button class="btn btn-danger w-100">Coba Lagi</button>
    </form>
    <?php endif; ?>

    <?php else: ?>
    <div class="alert alert-info small mb-3">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Konfigurasi:</strong>
        Host: <code>localhost</code> &nbsp;|&nbsp;
        User: <code>root</code> &nbsp;|&nbsp;
        DB: <code>tka_kecamatan</code><br>
        Edit <code>config/database.php</code> dan <code>install/install.php</code> jika berbeda.
    </div>
    <p class="text-muted small mb-2">Akan dibuat:</p>
    <ul class="small text-muted mb-4">
        <li>Database <code>tka_kecamatan</code></li>
        <li>Tabel: <code>users</code>, <code>sekolah</code>, <code>peserta</code>, <code>kategori_soal</code>, <code>soal</code>, <code>ujian</code>, <code>jawaban</code></li>
        <li>Akun admin: <code>admin</code> / <code>admin123</code></li>
        <li>Akun operator: <code>sekolah1</code> / <code>sekolah123</code></li>
    </ul>
    <form method="POST" action="?step=1">
        <button type="submit" class="btn btn-primary w-100 btn-lg fw-bold">
            <i class="bi bi-play-circle me-2"></i>Mulai Instalasi
        </button>
    </form>
    <?php endif; ?>

    <?php elseif ($step === 2): ?>

    <div class="text-center">
        <div style="font-size:72px">🎉</div>
        <h4 class="fw-bold mt-3">Instalasi Berhasil!</h4>
        <p class="text-muted mb-4">Semua tabel berhasil dibuat. Sistem siap digunakan.</p>
        <div class="card bg-light text-start mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-key-fill text-warning me-2"></i>Akun Default</h6>
                <table class="table table-sm small mb-0">
                    <thead><tr><th>Role</th><th>Username</th><th>Password</th><th>Dashboard</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-danger">admin_kecamatan</span></td>
                            <td><code>admin</code></td><td><code>admin123</code></td>
                            <td><code>/admin/dashboard.php</code></td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">sekolah</span></td>
                            <td><code>sekolah1</code></td><td><code>sekolah123</code></td>
                            <td><code>/sekolah/dashboard.php</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-warning small text-start mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Segera ganti password default setelah login. Hapus folder <code>/install/</code> setelah selesai.
        </div>
        <a href="<?= INSTALL_BASE_URL ?>/login.php" class="btn btn-success w-100 btn-lg fw-bold">
            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Aplikasi
        </a>
    </div>

    <?php endif; ?>
</div>
</body>
</html>
