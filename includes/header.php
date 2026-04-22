<?php
// includes/header.php
// Dipanggil di setiap halaman dengan $pageTitle dan $activeMenu sudah di-set
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'TKA Kecamatan') ?> — TKA Kecamatan</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <script>
        (function() {
            const theme = localStorage.getItem('darkMode');
            if (theme === 'enabled') {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', () => {
                    document.body.classList.add('dark-mode');
                });
            }
        })();
    </script>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <?php
    $namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
    $logoFilePath = getSetting($conn, 'logo_file_path', '');
    $logoUrl      = getSetting($conn, 'logo_url', '');
    $logoAktif    = $logoFilePath ? BASE_URL . '/' . $logoFilePath : $logoUrl;
    ?>
    <div class="sidebar-brand">
        <div class="brand-icon" style="<?= $logoAktif ? 'background:transparent;box-shadow:none;width:52px;height:52px;' : '' ?>">
            <?php if ($logoAktif): ?>
                <img src="<?= htmlspecialchars($logoAktif) ?>"
                     alt="Logo"
                     style="width:50px;height:50px;object-fit:contain"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <i class="bi bi-mortarboard-fill" style="font-size:22px;color:#fff;display:none"></i>
            <?php else: ?>
                <i class="bi bi-mortarboard-fill" style="font-size:22px;color:#fff"></i>
            <?php endif; ?>
        </div>
        <div class="brand-text">
            <div class="brand-name"><?= htmlspecialchars($namaAplikasi) ?></div>
            <div class="brand-sub">Sistem Ujian Online</div>
        </div>
    </div>

    <?php if ($user['role'] === 'admin_kecamatan'): ?>
    <!-- ADMIN MENU -->
    <ul class="sidebar-menu mt-3">
        <li><a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= ($activeMenu??'')==='dashboard'?'active':'' ?>">
            <span class="menu-icon"><i class="bi bi-grid-1x2-fill"></i></span> Dashboard
        </a></li>

        <!-- Master Data -->
        <?php $isMaster = in_array($activeMenu, ['sekolah', 'peserta']); ?>
        <li>
            <a href="#menuMaster" class="has-dropdown <?= $isMaster?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isMaster?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-database-fill"></i></span> Master Data
            </a>
            <ul class="sidebar-submenu collapse <?= $isMaster?'show':'' ?>" id="menuMaster">
                <li><a href="<?= BASE_URL ?>/admin/sekolah.php" class="<?= ($activeMenu??'')==='sekolah'?'active':'' ?>">Kelola Sekolah</a></li>
                <li><a href="<?= BASE_URL ?>/admin/peserta.php" class="<?= ($activeMenu??'')==='peserta'?'active':'' ?>">Kelola Peserta</a></li>
            </ul>
        </li>

        <!-- Bank Soal -->
        <?php $isSoal = in_array($activeMenu, ['kategori', 'soal', 'importsoal']); ?>
        <li>
            <a href="#menuSoal" class="has-dropdown <?= $isSoal?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isSoal?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-journal-check"></i></span> Bank Soal
            </a>
            <ul class="sidebar-submenu collapse <?= $isSoal?'show':'' ?>" id="menuSoal">
                <li><a href="<?= BASE_URL ?>/admin/kategori.php" class="<?= ($activeMenu??'')==='kategori'?'active':'' ?>">Kategori Soal</a></li>
                <li><a href="<?= BASE_URL ?>/admin/soal.php" class="<?= ($activeMenu??'')==='soal'?'active':'' ?>">Bank Soal</a></li>
                <li><a href="<?= BASE_URL ?>/admin/import_soal.php" class="<?= ($activeMenu??'')==='importsoal'?'active':'' ?>">Import Soal Excel</a></li>
            </ul>
        </li>

        <!-- Manajemen Ujian -->
        <?php $isUjian = in_array($activeMenu, ['token', 'jadwal', 'monitoring', 'absensi']); ?>
        <li>
            <a href="#menuUjian" class="has-dropdown <?= $isUjian?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isUjian?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-play-btn-fill"></i></span> Manajemen Ujian
            </a>
            <ul class="sidebar-submenu collapse <?= $isUjian?'show':'' ?>" id="menuUjian">
                <li><a href="<?= BASE_URL ?>/admin/token.php" class="<?= ($activeMenu??'')==='token'?'active':'' ?>">Token Ujian</a></li>
                <li><a href="<?= BASE_URL ?>/admin/jadwal.php" class="<?= ($activeMenu??'')==='jadwal'?'active':'' ?>">Jadwal Ujian</a></li>
                <li><a href="<?= BASE_URL ?>/admin/monitoring.php" class="<?= ($activeMenu??'')==='monitoring'?'active':'' ?>">Monitoring Ujian</a></li>
                <li><a href="<?= BASE_URL ?>/admin/absensi.php" class="<?= ($activeMenu??'')==='absensi'?'active':'' ?>">Absensi Ujian</a></li>
            </ul>
        </li>

        <!-- Laporan & Hasil -->
        <?php $isLaporan = in_array($activeMenu, ['nilai', 'hasil', 'rekap_sekolah', 'rekap_kelas', 'export', 'exportpdf', 'analisis_soal', 'kartuujian', 'sertifikat']); ?>
        <li>
            <a href="#menuLaporan" class="has-dropdown <?= $isLaporan?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isLaporan?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-file-earmark-bar-graph-fill"></i></span> Laporan & Hasil
            </a>
            <ul class="sidebar-submenu collapse <?= $isLaporan?'show':'' ?>" id="menuLaporan">
                <li><a href="<?= BASE_URL ?>/admin/nilai.php" class="<?= ($activeMenu??'')==='nilai'?'active':'' ?>">Nilai & Ranking</a></li>
                <li><a href="<?= BASE_URL ?>/admin/hasil.php" class="<?= ($activeMenu??'')==='hasil'?'active':'' ?>">Hasil Tes & Ranking</a></li>
                <li><a href="<?= BASE_URL ?>/admin/rekap_sekolah.php" class="<?= ($activeMenu??'')==='rekap_sekolah'?'active':'' ?>">Rekap Per Sekolah</a></li>
                <li><a href="<?= BASE_URL ?>/admin/rekap_kelas.php" class="<?= ($activeMenu??'')==='rekap_kelas'?'active':'' ?>">Rekap Per Kelas</a></li>
                <li><a href="<?= BASE_URL ?>/admin/sertifikat.php" class="<?= ($activeMenu??'')==='sertifikat'?'active':'' ?>">Cetak Sertifikat</a></li>
                <li><a href="<?= BASE_URL ?>/admin/export_excel.php" class="<?= ($activeMenu??'')==='export'?'active':'' ?>">Export Excel</a></li>
                <li><a href="<?= BASE_URL ?>/admin/export_pdf.php" class="<?= ($activeMenu??'')==='exportpdf'?'active':'' ?>" target="_blank">Export PDF</a></li>
                <li><a href="<?= BASE_URL ?>/admin/analisis_soal.php" class="<?= ($activeMenu??'')==='analisis_soal'?'active':'' ?>">Analisis Butir Soal</a></li>
                <li><a href="<?= BASE_URL ?>/admin/kartu_ujian.php" class="<?= ($activeMenu??'')==='kartuujian'?'active':'' ?>" target="_blank">Kartu Ujian</a></li>
            </ul>
        </li>

        <!-- Pengaturan Sistem -->
        <?php $isSistem = in_array($activeMenu, ['display', 'backup', 'log', 'pengaturan', 'about', 'maintenance']); ?>
        <li>
            <a href="#menuSistem" class="has-dropdown <?= $isSistem?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isSistem?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-gear-fill"></i></span> Pengaturan Sistem
            </a>
            <ul class="sidebar-submenu collapse <?= $isSistem?'show':'' ?>" id="menuSistem">
                <li><a href="<?= BASE_URL ?>/display/" target="_blank">Layar Tunggu</a></li>
                <li><a href="<?= BASE_URL ?>/admin/backup.php" class="<?= ($activeMenu??'')==='backup'?'active':'' ?>">Backup Database</a></li>
                <li><a href="<?= BASE_URL ?>/admin/log.php" class="<?= ($activeMenu??'')==='log'?'active':'' ?>">Log Aktivitas</a></li>
                <li><a href="<?= BASE_URL ?>/admin/maintenance.php" class="<?= ($activeMenu??'')==='maintenance'?'active':'' ?>">Pemeliharaan Sistem</a></li>
                <li><a href="<?= BASE_URL ?>/admin/pengaturan.php" class="<?= ($activeMenu??'')==='pengaturan'?'active':'' ?>">Pengaturan Sistem</a></li>
                <li><a href="<?= BASE_URL ?>/about.php" class="<?= ($activeMenu??'')==='about'?'active':'' ?>">Tentang Pengembang</a></li>
            </ul>
        </li>

        <!-- Bantuan -->
        <li>
            <a href="<?= BASE_URL ?>/admin/panduan.php" class="<?= ($activeMenu??'')==='panduan'?'active':'' ?>">
                <span class="menu-icon"><i class="bi bi-question-circle-fill"></i></span> Panduan Pengguna
            </a>
        </li>
    </ul>

    <?php elseif ($user['role'] === 'sekolah'): ?>
    <!-- SEKOLAH MENU -->
    <ul class="sidebar-menu mt-3">
        <li><a href="<?= BASE_URL ?>/sekolah/dashboard.php" class="<?= ($activeMenu??'')==='dashboard'?'active':'' ?>">
            <span class="menu-icon"><i class="bi bi-grid-1x2-fill"></i></span> Dashboard
        </a></li>

        <!-- Data Peserta -->
        <?php $isPeserta = in_array($activeMenu, ['peserta', 'importpeserta']); ?>
        <li>
            <a href="#menuPeserta" class="has-dropdown <?= $isPeserta?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isPeserta?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-people-fill"></i></span> Data Peserta
            </a>
            <ul class="sidebar-submenu collapse <?= $isPeserta?'show':'' ?>" id="menuPeserta">
                <li><a href="<?= BASE_URL ?>/sekolah/peserta.php" class="<?= ($activeMenu??'')==='peserta'?'active':'' ?>">Kelola Peserta</a></li>
                <li><a href="<?= BASE_URL ?>/sekolah/import_peserta.php" class="<?= ($activeMenu??'')==='importpeserta'?'active':'' ?>">Import Peserta</a></li>
            </ul>
        </li>

        <!-- Pelaksanaan Ujian -->
        <?php $isUjian = in_array($activeMenu, ['mulai_tes', 'hasil', 'kartu_ujian']); ?>
        <li>
            <a href="#menuUjianSekolah" class="has-dropdown <?= $isUjian?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isUjian?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-play-circle-fill"></i></span> Pelaksanaan Ujian
            </a>
            <ul class="sidebar-submenu collapse <?= $isUjian?'show':'' ?>" id="menuUjianSekolah">
                <li><a href="<?= BASE_URL ?>/sekolah/mulai_tes.php" class="<?= ($activeMenu??'')==='mulai_tes'?'active':'' ?>">Mulai Tes</a></li>
                <li><a href="<?= BASE_URL ?>/sekolah/hasil.php" class="<?= ($activeMenu??'')==='hasil'?'active':'' ?>">Hasil Ujian</a></li>
                <li><a href="<?= BASE_URL ?>/sekolah/kartu_ujian.php" class="<?= ($activeMenu??'')==='kartu_ujian'?'active':'' ?>">Kartu Ujian</a></li>
            </ul>
        </li>

        <!-- Pengaturan -->
        <?php $isSetting = in_array($activeMenu, ['profil', 'about']); ?>
        <li>
            <a href="#menuSetting" class="has-dropdown <?= $isSetting?'active':'' ?>" data-bs-toggle="collapse" aria-expanded="<?= $isSetting?'true':'false' ?>">
                <span class="menu-icon"><i class="bi bi-sliders"></i></span> Pengaturan
            </a>
            <ul class="sidebar-submenu collapse <?= $isSetting?'show':'' ?>" id="menuSetting">
                <li><a href="<?= BASE_URL ?>/sekolah/profil.php" class="<?= ($activeMenu??'')==='profil'?'active':'' ?>">Profil Sekolah</a></li>
                <li><a href="<?= BASE_URL ?>/about.php" class="<?= ($activeMenu??'')==='about'?'active':'' ?>">Tentang Pengembang</a></li>
            </ul>
        </li>
    </ul>
    <?php endif; ?>

    <div class="sidebar-footer">
        <div class="user-info">
            <?php if (!empty($user['foto_profil'])): ?>
            <div class="avatar" style="padding:0;overflow:hidden">
                <img src="<?= BASE_URL.'/'.htmlspecialchars($user['foto_profil']) ?>"
                     alt="<?= e($user['nama']) ?>"
                     style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
            </div>
            <?php else: ?>
            <div class="avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
            <?php endif; ?>
            <div>
                <div class="user-name"><?= htmlspecialchars($user['nama']) ?></div>
                <div class="user-role"><?= ucfirst($user['role']) ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <!-- TOPBAR -->
    <header class="topbar">
        <button class="topbar-btn" id="sidebarToggle" title="Toggle Sidebar">
            <i class="bi bi-list"></i>
        </button>
        <span class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></span>

        <div class="topbar-divider"></div>

        <button class="topbar-btn" id="fullscreenBtn" title="Fullscreen">
            <i class="bi bi-fullscreen" id="fullscreenIcon"></i>
        </button>

        <button class="topbar-btn" id="darkModeToggle" title="Ganti Tema">
            <i class="bi bi-moon-stars-fill" id="darkModeIcon"></i>
        </button>

        <div class="dropdown">
            <?php
            // ── Notifikasi dinamis ─────────────────────────────
            $notifs = [];
            $maksPel = (int)getSetting($conn, 'maks_pelanggaran', '3');

            // Peserta sedang ujian
            $sedangUjian = (int)$conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL")->fetch_assoc()['c'];
            if ($sedangUjian > 0)
                $notifs[] = ['icon'=>'bi-activity','color'=>'text-primary','teks'=>"$sedangUjian peserta sedang mengerjakan ujian",'url'=>BASE_URL.'/admin/monitoring.php'];

            // Peserta idle > 5 menit
            $idle = (int)$conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL AND last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetch_assoc()['c'];
            if ($idle > 0)
                $notifs[] = ['icon'=>'bi-clock-history','color'=>'text-warning','teks'=>"$idle peserta tidak aktif >5 menit",'url'=>BASE_URL.'/admin/monitoring.php'];

            // Pelanggaran melebihi batas
            if ($maksPel > 0) {
                $pelRes = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND pelanggaran >= $maksPel");
                $pel = $pelRes ? (int)$pelRes->fetch_assoc()['c'] : 0;
                if ($pel > 0)
                    $notifs[] = ['icon'=>'bi-exclamation-triangle-fill','color'=>'text-danger','teks'=>"$pel peserta melebihi batas pelanggaran",'url'=>BASE_URL.'/admin/monitoring.php'];
            }

            // Peserta belum ujian hari ini (jadwal aktif)
            $today = date('Y-m-d');
            $jadwalAktif = $conn->query("SELECT COUNT(*) AS c FROM jadwal_ujian WHERE tanggal='$today' AND status='aktif'")->fetch_assoc()['c'];
            if ($jadwalAktif > 0) {
                $belumUjian = (int)$conn->query("SELECT COUNT(*) AS c FROM peserta WHERE id NOT IN (SELECT DISTINCT peserta_id FROM ujian WHERE DATE(waktu_mulai)='$today')")->fetch_assoc()['c'];
                if ($belumUjian > 0)
                    $notifs[] = ['icon'=>'bi-person-x-fill','color'=>'text-secondary','teks'=>"$belumUjian peserta belum mulai ujian hari ini",'url'=>BASE_URL.'/admin/monitoring.php'];
            }

            // Selesai hari ini
            $selesaiHariIni = (int)$conn->query("SELECT COUNT(*) AS c FROM ujian WHERE DATE(waktu_selesai)='$today'")->fetch_assoc()['c'];
            if ($selesaiHariIni > 0)
                $notifs[] = ['icon'=>'bi-check-circle-fill','color'=>'text-success','teks'=>"$selesaiHariIni peserta selesai ujian hari ini",'url'=>BASE_URL.'/admin/hasil.php'];

            $jmlNotif = count($notifs);
            ?>
            <button class="topbar-btn dropdown-toggle border-0 position-relative" data-bs-toggle="dropdown" id="bellBtn">
                <i class="bi bi-bell"></i>
                <?php if ($jmlNotif > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;padding:2px 5px">
                    <?= $jmlNotif ?>
                </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:300px;max-height:360px;overflow-y:auto">
                <li class="dropdown-header fw-bold px-3 py-2 d-flex justify-content-between align-items-center">
                    <span>🔔 Notifikasi</span>
                    <small class="text-muted fw-normal"><?= date('H:i') ?> WIB</small>
                </li>
                <li><hr class="dropdown-divider my-0"></li>
                <?php if ($notifs): foreach ($notifs as $n): ?>
                <li>
                    <a class="dropdown-item py-2 px-3" href="<?= $n['url'] ?>" style="white-space:normal;font-size:13px">
                        <i class="bi <?= $n['icon'] ?> me-2 <?= $n['color'] ?>"></i><?= htmlspecialchars($n['teks']) ?>
                    </a>
                </li>
                <?php endforeach; else: ?>
                <li><p class="text-muted text-center py-3 mb-0" style="font-size:12px">
                    <i class="bi bi-check2-circle me-1"></i>Semua aman, tidak ada notifikasi
                </p></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider my-0"></li>
                <li><a class="dropdown-item text-center small py-2 text-primary" href="<?= BASE_URL ?>/admin/monitoring.php">
                    Buka Monitoring →
                </a></li>
            </ul>
        </div>

        <div class="dropdown">
            <button class="d-flex align-items-center gap-2 btn btn-sm btn-light" data-bs-toggle="dropdown">
                <div class="avatar" style="width:30px;height:30px;background:var(--primary);color:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;overflow:hidden;padding:0">
                    <?php if (!empty($user['foto_profil'])): ?>
                    <img src="<?= BASE_URL.'/'.htmlspecialchars($user['foto_profil']) ?>"
                         alt="foto"
                         style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                    <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <span style="font-size:13px;font-weight:600"><?= htmlspecialchars($user['nama']) ?></span>
                <i class="bi bi-chevron-down" style="font-size:11px"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/<?= $user['role']==='admin_kecamatan'?'admin':'sekolah' ?>/profil.php"><i class="bi bi-person-circle me-2"></i>Profil Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($user['role'] === 'admin_kecamatan'): ?>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/pengaturan.php"><i class="bi bi-gear me-2"></i>Pengaturan Sistem</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/backup.php"><i class="bi bi-database me-2"></i>Backup Database</a></li>
                <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
            </ul>
        </div>
    </header>

    <!-- CONTENT -->
    <div class="content-area">
