<?php
// admin/profil.php — Profil & Upload Foto Admin
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$user   = getCurrentUser();
$userId = $user['id'];
$errors = [];
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    // Update nama lengkap
    if (isset($_POST['nama_lengkap'])) {
        $namaLengkap = trim($_POST['nama_lengkap']);
        $nl = $conn->real_escape_string($namaLengkap);
        $conn->query("UPDATE users SET nama_lengkap='$nl' WHERE id=$userId");
        $_SESSION['nama'] = $namaLengkap ?: $user['username'];
    }

    // Upload foto
    if (!empty($_FILES['foto']['name'])) {
        $uploadDir = __DIR__ . '/../assets/uploads/profil/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $validasi = validasiUploadGambar($_FILES['foto'], 2 * 1024 * 1024);
        if (!$validasi['ok']) {
            $errors[] = $validasi['msg'];
        } else {
            $ext = $validasi['ext'];
            // Hapus foto lama
            $_qFoto = $conn->query("SELECT foto_profil FROM users WHERE id=$userId LIMIT 1"); $fotoLama = ($_qFoto && $_qFoto->num_rows>0) ? ($_qFoto->fetch_assoc()['foto_profil'] ?? '') : '';
            if ($fotoLama && file_exists(__DIR__ . '/../' . $fotoLama)) @unlink(__DIR__ . '/../' . $fotoLama);

            $namaFile = 'profil_' . $userId . '_' . time() . '.' . $ext;
            $dest     = $uploadDir . $namaFile;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                $path = 'assets/uploads/profil/' . $namaFile;
                $conn->query("UPDATE users SET foto_profil='$path' WHERE id=$userId");
                $_SESSION['foto_profil'] = $path;
                $sukses = 'Foto profil berhasil diperbarui.';
            }
        }
    }

    // Hapus foto
    if (isset($_POST['hapus_foto'])) {
        $_qFoto = $conn->query("SELECT foto_profil FROM users WHERE id=$userId LIMIT 1"); $fotoLama = ($_qFoto && $_qFoto->num_rows>0) ? ($_qFoto->fetch_assoc()['foto_profil'] ?? '') : '';
        if ($fotoLama && file_exists(__DIR__ . '/../' . $fotoLama)) @unlink(__DIR__ . '/../' . $fotoLama);
        $conn->query("UPDATE users SET foto_profil=NULL WHERE id=$userId");
        $_SESSION['foto_profil'] = null;
        $sukses = 'Foto profil berhasil dihapus.';
    }

    // Ganti password
    if (!empty($_POST['password_baru'])) {
        $passBaru  = $_POST['password_baru'];
        $passKonf  = $_POST['password_konfirmasi'] ?? '';
        $passLama  = $_POST['password_lama'] ?? '';

        $_qPw = $conn->query("SELECT password FROM users WHERE id=$userId LIMIT 1"); $userRow = ($_qPw && $_qPw->num_rows>0) ? $_qPw->fetch_assoc() : null;
        if (!password_verify($passLama, $userRow['password'])) {
            $errors[] = 'Password lama tidak sesuai.';
        } elseif (strlen($passBaru) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        } elseif ($passBaru !== $passKonf) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($passBaru, PASSWORD_BCRYPT);
            $conn->query("UPDATE users SET password='$hash' WHERE id=$userId");
            $sukses = 'Password berhasil diubah.';
        }
    }

    if (!$errors && !$sukses) $sukses = 'Profil berhasil disimpan.';
    if (!$errors) {
        setFlash('success', $sukses);
        redirect(BASE_URL . '/admin/profil.php');
    }
}

// Ambil data terbaru
$_qUser = $conn->query("SELECT id, username, nama_lengkap, foto_profil, role, sekolah_id FROM users WHERE id=$userId LIMIT 1"); $userRow = ($_qUser && $_qUser->num_rows>0) ? $_qUser->fetch_assoc() : null;
$namaLengkap  = $userRow['nama_lengkap'] ?? '';
$fotoProfil   = $userRow['foto_profil']  ?? '';
$fotoUrl      = $fotoProfil ? BASE_URL . '/' . $fotoProfil : '';

$pageTitle  = 'Profil Saya';
$activeMenu = 'profil';
require_once __DIR__ . '/../includes/header.php';
?>

<?= renderFlash() ?>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4" style="max-width:800px">

    <!-- Kartu Foto Profil -->
    <div class="col-md-4">
        <div class="card shadow-sm text-center">
            <div class="card-body py-4">
                <!-- Avatar besar -->
                <div style="width:120px;height:120px;border-radius:50%;margin:0 auto 16px;overflow:hidden;background:var(--primary);display:flex;align-items:center;justify-content:center;border:4px solid var(--primary-light)">
                    <?php if ($fotoUrl): ?>
                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto"
                         style="width:100%;height:100%;object-fit:cover" id="fotoPreviewBesar">
                    <?php else: ?>
                    <span style="font-size:48px;font-weight:900;color:#fff" id="inisialBesar">
                        <?= strtoupper(substr($userRow['username'], 0, 1)) ?>
                    </span>
                    <img src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover" id="fotoPreviewBesar">
                    <?php endif; ?>
                </div>

                <div class="fw-800 mb-1" style="font-size:16px"><?= e($namaLengkap ?: $userRow['username']) ?></div>
                <div class="text-muted mb-3" style="font-size:12px">@<?= e($userRow['username']) ?> · <?= e($userRow['role']) ?></div>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="file" name="foto" id="inputFoto" accept=".jpg,.jpeg,.png,.webp"
                           class="d-none" onchange="previewFoto(this)">
                    <button type="button" class="btn btn-primary btn-sm w-100 mb-2"
                            onclick="document.getElementById('inputFoto').click()">
                        <i class="bi bi-camera me-1"></i>Ganti Foto
                    </button>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100 mb-2" id="btnSimpanFoto" style="display:none">
                        <i class="bi bi-check me-1"></i>Simpan Foto
                    </button>
                    <?php if ($fotoUrl): ?>
                    <button type="submit" name="hapus_foto" value="1"
                            class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('Hapus foto profil?')">
                        <i class="bi bi-trash me-1"></i>Hapus Foto
                    </button>
                    <?php endif; ?>
                    <div class="form-text mt-2">JPG, PNG, WEBP · Maks 2MB</div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kartu Edit Profil -->
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-700">
                <i class="bi bi-person-fill text-primary me-2"></i>Data Profil
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-600">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control"
                               value="<?= e($namaLengkap) ?>"
                               placeholder="Masukkan nama lengkap Anda">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Username</label>
                        <input type="text" class="form-control" value="<?= e($userRow['username']) ?>" disabled>
                        <div class="form-text">Username tidak dapat diubah.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-600">Role</label>
                        <input type="text" class="form-control" value="<?= e($userRow['role']) ?>" disabled>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Simpan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ganti Password -->
        <div class="card shadow-sm">
            <div class="card-header fw-700">
                <i class="bi bi-shield-lock-fill text-warning me-2"></i>Ganti Password
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-600">Password Lama</label>
                        <input type="password" name="password_lama" class="form-control"
                               placeholder="Masukkan password lama">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Password Baru</label>
                        <input type="password" name="password_baru" class="form-control"
                               placeholder="Minimal 6 karakter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Konfirmasi Password Baru</label>
                        <input type="password" name="password_konfirmasi" class="form-control"
                               placeholder="Ulangi password baru">
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key me-1"></i>Ganti Password
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function previewFoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const besar = document.getElementById('fotoPreviewBesar');
            const inisial = document.getElementById('inisialBesar');
            besar.src = e.target.result;
            besar.style.display = 'block';
            if (inisial) inisial.style.display = 'none';
            document.getElementById('btnSimpanFoto').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
