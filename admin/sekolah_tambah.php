<?php
// ============================================================
// admin/sekolah_tambah.php — Form Tambah Sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('admin_kecamatan');

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['nama_sekolah']   = sanitize($_POST['nama_sekolah'] ?? '');
    $data['npsn']           = sanitize($_POST['npsn'] ?? '');
    $data['jenjang']        = sanitize($_POST['jenjang'] ?? 'SD');
    $data['alamat']         = sanitize($_POST['alamat'] ?? '');
    $data['kepala_sekolah'] = sanitize($_POST['kepala_sekolah'] ?? '');
    $data['telepon']        = sanitize($_POST['telepon'] ?? '');
    $data['email']          = sanitize($_POST['email'] ?? '');
    $data['status']         = sanitize($_POST['status'] ?? 'aktif');

    // Username & password untuk akun sekolah
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$data['nama_sekolah']) $errors[] = 'Nama sekolah wajib diisi.';
    if (!$username)             $errors[] = 'Username akun wajib diisi.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';

    // Cek duplikat username
    $cek = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($cek && $cek->num_rows > 0) $errors[] = 'Username sudah digunakan.';

    if (!$errors) {
        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO sekolah (nama_sekolah, npsn, jenjang, alamat, kepala_sekolah, telepon, email, status)
                          VALUES ('{$data['nama_sekolah']}','{$data['npsn']}','{$data['jenjang']}','{$data['alamat']}',
                                  '{$data['kepala_sekolah']}','{$data['telepon']}','{$data['email']}','{$data['status']}')");
            $sekolahId  = $conn->insert_id;
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);
            $nama       = $data['nama_sekolah'];
            $conn->query("INSERT INTO users (username, password, nama, role, sekolah_id, status)
                          VALUES ('$username', '$hashedPass', '$nama', 'sekolah', $sekolahId, 'aktif')");
            $conn->commit();
            logActivity($conn, 'Tambah Sekolah', "ID: $sekolahId | Nama: {$data['nama_sekolah']} | User: $username");
            setFlash('success', 'Sekolah dan akun operator berhasil ditambahkan.');
            redirect(BASE_URL . '/admin/sekolah.php');
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

$pageTitle  = 'Tambah Sekolah';
$activeMenu = 'sekolah';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Tambah Sekolah</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/sekolah.php">Sekolah</a></li>
            <li class="breadcrumb-item active">Tambah</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/sekolah.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <strong>Terdapat kesalahan:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="">
            <?= csrfField() ?>
<div class="row g-4">
    <!-- Data Sekolah -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-building me-2"></i>Data Sekolah</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" name="nama_sekolah" class="form-control" required
                               value="<?= htmlspecialchars($data['nama_sekolah'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NPSN</label>
                        <input type="text" name="npsn" class="form-control" maxlength="8"
                               value="<?= htmlspecialchars($data['npsn'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenjang <span class="text-danger">*</span></label>
                        <select name="jenjang" class="form-select" required>
                            <?php foreach (getJenjangOptions() as $kode => $label): ?>
                            <option value="<?= $kode ?>" <?= ($data['jenjang']??'SD')===$kode?'selected':'' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif" <?= ($data['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($data['status']??'')==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($data['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kepala Sekolah</label>
                        <input type="text" name="kepala_sekolah" class="form-control"
                               value="<?= htmlspecialchars($data['kepala_sekolah'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" class="form-control"
                               value="<?= htmlspecialchars($data['telepon'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($data['email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Akun Operator -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-lock me-2"></i>Akun Operator Sekolah</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Akun ini digunakan oleh operator sekolah untuk login ke sistem.</p>
                <div class="mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                    <div class="form-text">Minimal 6 karakter.</div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" class="form-control">
                </div>
            </div>
        </div>
        <div class="mt-3 d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save me-2"></i>Simpan Sekolah
            </button>
        </div>
    </div>
</div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
