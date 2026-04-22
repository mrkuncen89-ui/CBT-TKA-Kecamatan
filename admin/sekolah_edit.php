<?php
// ============================================================
// admin/sekolah_edit.php — Form Edit Sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('admin_kecamatan');

$id = (int)($_GET['id'] ?? 0);
$_qSek = $conn->query("SELECT id, nama_sekolah, npsn, jenjang, alamat, kepala_sekolah, telepon, email, status FROM sekolah WHERE id = $id LIMIT 1");
$sekolah = ($_qSek && $_qSek->num_rows > 0) ? $_qSek->fetch_assoc() : null;
if (!$sekolah) { setFlash('error', 'Sekolah tidak ditemukan.'); redirect(BASE_URL . '/admin/sekolah.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $nama        = sanitize($_POST['nama_sekolah'] ?? '');
    $npsn        = sanitize($_POST['npsn'] ?? '');
    $jenjang     = sanitize($_POST['jenjang'] ?? 'SD');
    $alamat      = sanitize($_POST['alamat'] ?? '');
    $kepala      = sanitize($_POST['kepala_sekolah'] ?? '');
    $telepon     = sanitize($_POST['telepon'] ?? '');
    $email       = sanitize($_POST['email'] ?? '');
    $status      = sanitize($_POST['status'] ?? 'aktif');
    $newPassword = $_POST['new_password'] ?? '';

    if (!$nama) $errors[] = 'Nama sekolah wajib diisi.';

    if (!$errors) {
        $conn->query("UPDATE sekolah SET nama_sekolah='$nama', npsn='$npsn', jenjang='$jenjang', alamat='$alamat',
                      kepala_sekolah='$kepala', telepon='$telepon', email='$email', status='$status'
                      WHERE id = $id");
        if ($newPassword) {
            if (strlen($newPassword) < 6) { $errors[] = 'Password baru minimal 6 karakter.'; }
            else {
                $hp = password_hash($newPassword, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$hp' WHERE sekolah_id=$id AND role='sekolah'");
            }
        }
        if (!$errors) {
            logActivity($conn, 'Edit Sekolah', "ID: $id | Nama: {$data['nama_sekolah']}");
            setFlash('success', 'Data sekolah berhasil diperbarui.');
            redirect(BASE_URL . '/admin/sekolah.php');
        }
    }
}

$pageTitle  = 'Edit Sekolah';
$activeMenu = 'sekolah';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Edit Sekolah</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/sekolah.php">Sekolah</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/sekolah.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST">
            <?= csrfField() ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-building me-2"></i>Data Sekolah</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" name="nama_sekolah" class="form-control" required
                               value="<?= htmlspecialchars($sekolah['nama_sekolah']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NPSN</label>
                        <input type="text" name="npsn" class="form-control"
                               value="<?= htmlspecialchars($sekolah['npsn'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenjang <span class="text-danger">*</span></label>
                        <select name="jenjang" class="form-select" required>
                            <?php foreach (getJenjangOptions() as $kode => $label): ?>
                            <option value="<?= $kode ?>" <?= ($sekolah['jenjang']??'SD')===$kode?'selected':'' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif"    <?= ($sekolah['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($sekolah['status']??'')==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($sekolah['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kepala Sekolah</label>
                        <input type="text" name="kepala_sekolah" class="form-control"
                               value="<?= htmlspecialchars($sekolah['kepala_sekolah'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control"
                               value="<?= htmlspecialchars($sekolah['telepon'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($sekolah['email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-key me-2"></i>Reset Password Operator</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Kosongkan jika tidak ingin mengganti password.</p>
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter">
                </div>
            </div>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save me-2"></i>Simpan Perubahan
            </button>
        </div>
    </div>
</div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
