<?php
// ============================================================
// admin/maintenance.php — Alat Pemeliharaan Sistem
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$pageTitle  = 'Pemeliharaan Sistem';
$activeMenu = 'maintenance';

$msg = '';
$type = 'success';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'clear_logs') {
        if ($conn->query("TRUNCATE TABLE log_aktivitas")) {
            $msg = 'Log aktivitas berhasil dibersihkan.';
        } else {
            $msg = 'Gagal membersihkan log: ' . $conn->error;
            $type = 'danger';
        }
    } elseif ($action === 'clear_temp_ujian') {
        // Hapus ujian yang tidak selesai dan sudah lewat 24 jam
        $sql = "DELETE FROM ujian WHERE waktu_selesai IS NULL AND last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        if ($conn->query($sql)) {
            $affected = $conn->affected_rows;
            $msg = "Berhasil menghapus $affected data ujian menggantung.";
        } else {
            $msg = 'Gagal membersihkan data ujian: ' . $conn->error;
            $type = 'danger';
        }
    } elseif ($action === 'reset_all_ujian') {
        // Hapus SEMUA data ujian dan jawaban (Hati-hati!)
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $q1 = $conn->query("TRUNCATE TABLE jawaban");
        $q2 = $conn->query("TRUNCATE TABLE ujian");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        if ($q1 && $q2) {
            $msg = 'SEMUA data hasil ujian dan jawaban telah dihapus (Reset Total).';
        } else {
            $msg = 'Gagal melakukan reset: ' . $conn->error;
            $type = 'danger';
        }
    }
    
    if ($msg) setFlash($msg, $type);
    redirect(BASE_URL . '/admin/maintenance.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-tools me-2"></i>Pemeliharaan Sistem</h2>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &nbsp;&raquo;&nbsp; Pemeliharaan
        </div>
    </div>
</div>

<?= renderFlash() ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-journal-text me-2"></i>Pembersihan Log</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Log aktivitas mencatat setiap aksi yang dilakukan pengguna. Jika database terasa berat, Anda bisa membersihkan tabel ini.</p>
                <?php
                $countLog = (int)$conn->query("SELECT COUNT(*) as c FROM log_aktivitas")->fetch_assoc()['c'];
                ?>
                <div class="alert alert-info py-2 px-3 mb-4">
                    <i class="bi bi-info-circle me-2"></i>Jumlah log saat ini: <strong><?= $countLog ?></strong> baris.
                </div>
                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus semua log aktivitas?')">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="btn btn-outline-danger w-100 py-2">
                        <i class="bi bi-trash me-2"></i>Bersihkan Log Aktivitas
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-warning"><i class="bi bi-clock-history me-2"></i>Data Ujian Menggantung</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Menghapus data ujian peserta yang tidak menekan tombol "Selesai" dan sudah tidak aktif lebih dari 24 jam.</p>
                <?php
                $countTemp = (int)$conn->query("SELECT COUNT(*) as c FROM ujian WHERE waktu_selesai IS NULL AND last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetch_assoc()['c'];
                ?>
                <div class="alert alert-warning py-2 px-3 mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>Data menggantung: <strong><?= $countTemp ?></strong> sesi.
                </div>
                <form method="POST" onsubmit="return confirm('Hapus data ujian yang menggantung?')">
                    <input type="hidden" name="action" value="clear_temp_ujian">
                    <button type="submit" class="btn btn-outline-warning w-100 py-2">
                        <i class="bi bi-eraser me-2"></i>Bersihkan Data Menggantung
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card shadow-sm border-0 border-start border-danger border-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i>Zona Bahaya: Reset Total</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <p class="mb-lg-0">Fitur ini akan menghapus **SELURUH** data hasil ujian, nilai, dan jawaban peserta dari database. Gunakan hanya jika Anda ingin memulai periode ujian baru dari nol.</p>
                    </div>
                    <div class="col-lg-4">
                        <form method="POST" onsubmit="return confirm('PERINGATAN KRITIS!\n\nTindakan ini akan menghapus SEMUA HASIL UJIAN secara permanen.\nData tidak bisa dikembalikan.\n\nApakah Anda benar-benar yakin?')">
                            <input type="hidden" name="action" value="reset_all_ujian">
                            <button type="submit" class="btn btn-danger w-100 py-2 fw-bold">
                                <i class="bi bi-shield-exclamation me-2"></i>RESET SEMUA HASIL UJIAN
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
