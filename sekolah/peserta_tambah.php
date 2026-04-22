<?php
// ============================================================
// sekolah/peserta_tambah.php — Form Tambah Peserta
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = $user['sekolah_id'];

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['nama']  = trim($_POST['nama'] ?? '');
    $data['kelas'] = trim($_POST['kelas'] ?? '');

    if (!$data['nama']) $errors[] = 'Nama peserta wajib diisi.';

    if (!$errors) {
        $kode = generateKodePeserta($conn);
        $st   = $conn->prepare("INSERT INTO peserta (nama, kelas, sekolah_id, kode_peserta) VALUES (?,?,?,?)");
        $st->bind_param('ssis', $data['nama'], $data['kelas'], $sekolahId, $kode);
        $st->execute(); $st->close();
        setFlash('success', "Peserta <strong>" . htmlspecialchars($data['nama']) . "</strong> ditambahkan. Kode: <strong>$kode</strong>");
        redirect(BASE_URL . '/sekolah/peserta.php');
    }
}

// Ambil jenjang sekolah untuk dropdown kelas
$_jRes = $conn->query("SELECT jenjang FROM sekolah WHERE id=$sekolahId LIMIT 1");
$jenjangSekolah = ($_jRes && $_jRes->num_rows > 0) ? ($_jRes->fetch_assoc()['jenjang'] ?? 'SD') : 'SD';

$pageTitle  = 'Tambah Peserta';
$activeMenu = 'peserta';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Tambah Peserta</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/peserta.php">Peserta</a></li>
            <li class="breadcrumb-item active">Tambah</li>
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
            <div class="card">
                <div class="card-header"><i class="bi bi-person-plus me-2"></i>Data Peserta Baru</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Peserta <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" required
                                   value="<?= htmlspecialchars($data['nama'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kelas</label>
                            <select name="kelas" class="form-select">
                                                  <?php echo renderKelasOptions($data['kelas'] ?? '', $jenjangSekolah); ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Kode peserta akan digenerate otomatis. Peserta menggunakan kode tersebut untuk login ujian.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="<?= BASE_URL ?>/sekolah/peserta.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan Peserta
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
