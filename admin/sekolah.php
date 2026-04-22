<?php
// ============================================================
// admin/sekolah.php — Kelola Data Sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// ── HAPUS ────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Hapus user terkait dulu
    $conn->query("DELETE FROM users WHERE sekolah_id = $id AND role = 'sekolah'");
    $conn->query("DELETE FROM sekolah WHERE id = $id");
    setFlash('success', 'Sekolah berhasil dihapus.');
    redirect(BASE_URL . '/admin/sekolah.php');
}

// ── TAMBAH ───────────────────────────────────────────────────
$errTambah = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nama     = trim($_POST['nama_sekolah'] ?? '');
    $npsn     = trim($_POST['npsn'] ?? '');
    $jenjang  = trim($_POST['jenjang'] ?? 'SD');
    $alamat   = trim($_POST['alamat'] ?? '');
    $telepon  = trim($_POST['telepon'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$nama)     $errTambah[] = 'Nama sekolah wajib diisi.';
    if (!$username) $errTambah[] = 'Username login wajib diisi.';
    if (strlen($password) < 6) $errTambah[] = 'Password minimal 6 karakter.';

    // Cek username unik
    if (!$errTambah) {
        $cek = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $cek->bind_param('s', $username); $cek->execute();
        if ($cek->get_result()->num_rows > 0) $errTambah[] = 'Username sudah digunakan.';
        $cek->close();
    }

    if (!$errTambah) {
        $conn->begin_transaction();
        try {
            // 1. Simpan sekolah
            $s1 = $conn->prepare("INSERT INTO sekolah (nama_sekolah,npsn,jenjang,alamat,telepon) VALUES (?,?,?,?,?)");
            $s1->bind_param('sssss', $nama, $npsn, $jenjang, $alamat, $telepon);
            $s1->execute();
            $sekolahId = $conn->insert_id;
            $s1->close();

            // 2. Buat akun login (role = sekolah, password_hash)
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $s2 = $conn->prepare("INSERT INTO users (username,password,role,sekolah_id) VALUES (?,?,'sekolah',?)");
            $s2->bind_param('ssi', $username, $hashed, $sekolahId);
            $s2->execute();
            $s2->close();

            $conn->commit();
            setFlash('success', "Sekolah <strong>$nama</strong> dan akun login berhasil ditambahkan.");
            redirect(BASE_URL . '/admin/sekolah.php');
        } catch (Exception $e) {
            $conn->rollback();
            $errTambah[] = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

// ── EDIT ─────────────────────────────────────────────────────
$errEdit = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id      = (int)$_POST['id'];
    $nama    = trim($_POST['nama_sekolah'] ?? '');
    $npsn    = trim($_POST['npsn'] ?? '');
    $jenjang = trim($_POST['jenjang'] ?? 'SD');
    $alamat  = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $newPass = $_POST['new_password'] ?? '';

    if (!$nama) $errEdit[] = 'Nama sekolah wajib diisi.';

    if (!$errEdit) {
        $s = $conn->prepare("UPDATE sekolah SET nama_sekolah=?,npsn=?,jenjang=?,alamat=?,telepon=? WHERE id=?");
        $s->bind_param('sssssi', $nama, $npsn, $jenjang, $alamat, $telepon, $id);
        $s->execute(); $s->close();

        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                $errEdit[] = 'Password baru minimal 6 karakter.';
            } else {
                $hp = password_hash($newPass, PASSWORD_DEFAULT);
                $p = $conn->prepare("UPDATE users SET password=? WHERE sekolah_id=? AND role='sekolah'");
                $p->bind_param('si', $hp, $id); $p->execute(); $p->close();
            }
        }
        if (!$errEdit) {
            setFlash('success', 'Data sekolah berhasil diperbarui.');
            redirect(BASE_URL . '/admin/sekolah.php');
        }
    }
}

// ── DATA ─────────────────────────────────────────────────────
$q     = trim($_GET['q'] ?? '');
$where = $q ? "WHERE s.nama_sekolah LIKE '%" . $conn->real_escape_string($q) . "%' OR s.npsn LIKE '%" . $conn->real_escape_string($q) . "%'" : '';
$list  = $conn->query("
    SELECT s.*,
           u.username,
           (SELECT COUNT(*) FROM peserta WHERE sekolah_id = s.id) AS jml_peserta
    FROM sekolah s
    LEFT JOIN users u ON u.sekolah_id = s.id AND u.role = 'sekolah'
    $where
    ORDER BY s.nama_sekolah
");

// Data untuk modal edit
$editData = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $er  = $conn->query("SELECT s.*,u.username FROM sekolah s LEFT JOIN users u ON u.sekolah_id=s.id AND u.role='sekolah' WHERE s.id=$eid LIMIT 1");
    $editData = $er ? $er->fetch_assoc() : null;
}

$pageTitle  = 'Kelola Sekolah';
$activeMenu = 'sekolah';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-building me-2 text-primary"></i>Kelola Sekolah</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Sekolah</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg me-1"></i>Tambah Sekolah
    </button>
</div>

<?= renderFlash() ?>

<!-- Error tambah inline -->
<?php if ($errTambah): ?>
<div class="alert alert-danger"><ul class="mb-0">
    <?php foreach($errTambah as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:280px"
                   placeholder="Cari nama sekolah / NPSN…" value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Cari</button>
            <?php if($q): ?><a href="?" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Daftar Sekolah</span>
        <span class="badge bg-primary"><?= $list ? $list->num_rows : 0 ?> sekolah</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblSekolah" class="table table-hover datatable mb-0">
                <thead><tr>
                    <th>#</th><th>Nama Sekolah</th><th>Jenjang</th><th>NPSN</th><th>Alamat</th>
                    <th>Telepon</th><th>Username</th><th class="text-center">Peserta</th>
                    <th class="text-center" style="width:110px">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if ($list && $list->num_rows > 0): $no=1; while($row=$list->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars($row['nama_sekolah']) ?></strong></td>
                    <td>
                        <?php
                        $jBadge = match($row['jenjang']??'SD') {
                            'SMP','MTS' => 'bg-success',
                            'SMA','MA'  => 'bg-warning text-dark',
                            'SMK'       => 'bg-danger',
                            default     => 'bg-primary',
                        };
                        ?>
                        <span class="badge <?= $jBadge ?>"><?= htmlspecialchars($row['jenjang']??'SD') ?></span>
                    </td>
                    <td><code><?= htmlspecialchars($row['npsn']??'-') ?></code></td>
                    <td class="text-muted small" style="max-width:180px"><?= htmlspecialchars($row['alamat']??'-') ?></td>
                    <td><?= htmlspecialchars($row['telepon']??'-') ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['username']??'-') ?></span></td>
                    <td class="text-center"><span class="badge bg-info"><?= $row['jml_peserta'] ?></span></td>
                    <td class="text-center">
                        <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon"
                           data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger btn-icon"
                           data-confirm="Hapus sekolah '<?= htmlspecialchars($row['nama_sekolah']) ?>'? Akun login juga akan dihapus.">
                            <i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data sekolah
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── MODAL TAMBAH ── -->
<div class="modal fade <?= $errTambah ? 'show' : '' ?>" id="modalTambah" tabindex="-1" <?= $errTambah ? 'style="display:block"' : '' ?>>
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah">
<?= csrfField() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-building me-2"></i>Tambah Sekolah Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" name="nama_sekolah" class="form-control" required
                               value="<?= htmlspecialchars($_POST['nama_sekolah']??'') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">NPSN</label>
                        <input type="text" name="npsn" class="form-control" maxlength="10"
                               value="<?= htmlspecialchars($_POST['npsn']??'') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jenjang <span class="text-danger">*</span></label>
                        <select name="jenjang" class="form-select" required>
                            <?php foreach (getJenjangOptions() as $kode => $label): ?>
                            <option value="<?= $kode ?>" <?= ($_POST['jenjang']??'SD')===$kode?'selected':'' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Telepon</label>
                        <input type="text" name="telepon" class="form-control"
                               value="<?= htmlspecialchars($_POST['telepon']??'') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($_POST['alamat']??'') ?></textarea>
                    </div>
                </div>
                <hr class="my-3">
                <p class="fw-semibold mb-2"><i class="bi bi-person-lock me-1 text-primary"></i>Akun Login Operator Sekolah</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required
                               value="<?= htmlspecialchars($_POST['username']??'') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                        <div class="form-text">Minimal 6 karakter.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Sekolah</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL EDIT ── -->
<?php if ($editData): ?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
<?= csrfField() ?>
            <input type="hidden" name="id"   value="<?= $editData['id'] ?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Sekolah</h5>
                <a href="<?= BASE_URL ?>/admin/sekolah.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" name="nama_sekolah" class="form-control" required
                               value="<?= htmlspecialchars($editData['nama_sekolah']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">NPSN</label>
                        <input type="text" name="npsn" class="form-control"
                               value="<?= htmlspecialchars($editData['npsn']??'') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jenjang <span class="text-danger">*</span></label>
                        <select name="jenjang" class="form-select" required>
                            <?php foreach (getJenjangOptions() as $kode => $label): ?>
                            <option value="<?= $kode ?>" <?= ($editData['jenjang']??'SD')===$kode?'selected':'' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Telepon</label>
                        <input type="text" name="telepon" class="form-control"
                               value="<?= htmlspecialchars($editData['telepon']??'') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($editData['alamat']??'') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Username Saat Ini</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($editData['username']??'') ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password Baru <span class="text-muted">(kosongkan jika tidak diubah)</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min. 6 karakter">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?= BASE_URL ?>/admin/sekolah.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php if ($errTambah): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('modalTambah')).show())</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
