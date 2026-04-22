<?php
// ============================================================
// sekolah/peserta_edit.php — Form Edit Peserta
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = $user['sekolah_id'];

$id      = (int)($_GET['id'] ?? 0);
$_qPes = $conn->query("SELECT * FROM peserta WHERE id = $id AND sekolah_id = $sekolahId LIMIT 1");
$peserta = ($_qPes && $_qPes->num_rows > 0) ? $_qPes->fetch_assoc() : null;
if (!$peserta) { setFlash('error', 'Peserta tidak ditemukan.'); redirect(BASE_URL . '/sekolah/peserta.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');

    if (!$nama) $errors[] = 'Nama peserta wajib diisi.';

    if (!$errors) {
        $st = $conn->prepare("UPDATE peserta SET nama=?, kelas=? WHERE id=? AND sekolah_id=?");
        $st->bind_param('ssii', $nama, $kelas, $id, $sekolahId);
        $st->execute(); $st->close();
        setFlash('success', 'Data peserta berhasil diperbarui.');
        redirect(BASE_URL . '/sekolah/peserta.php');
    }
}

// Ambil jenjang sekolah untuk dropdown kelas
$_jRes2 = $conn->query("SELECT jenjang FROM sekolah WHERE id={$peserta['sekolah_id']} LIMIT 1");
$jenjangSekolah = ($_jRes2 && $_jRes2->num_rows > 0) ? ($_jRes2->fetch_assoc()['jenjang'] ?? 'SD') : 'SD';

// Ambil jenjang sekolah untuk dropdown kelas
$_jRes2 = $conn->query("SELECT jenjang FROM sekolah WHERE id={$peserta['sekolah_id']} LIMIT 1");
$jenjangSekolah = ($_jRes2 && $_jRes2->num_rows > 0) ? ($_jRes2->fetch_assoc()['jenjang'] ?? 'SD') : 'SD';

$pageTitle  = 'Edit Peserta';
$activeMenu = 'peserta';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Edit Peserta</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/peserta.php">Peserta</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/sekolah/peserta.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-gear me-2"></i>Data Peserta</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Peserta <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" required
                                   value="<?= htmlspecialchars($peserta['nama']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kelas</label>
                            <select name="kelas" class="form-select">
                                <?php echo renderKelasOptions($peserta['kelas'] ?? '', $jenjangSekolah); ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info small mb-0">
                                <i class="bi bi-key me-1"></i>
                                Kode peserta: <strong><?= htmlspecialchars($peserta['kode_peserta'] ?? '-') ?></strong>
                                — digunakan untuk login ujian.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="<?= BASE_URL ?>/sekolah/peserta.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
