<?php
// ============================================================
// admin/kategori.php — Kelola Kategori Soal
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// HAPUS
if (isset($_GET['hapus'])) {
    $id  = (int)$_GET['hapus'];
    $cek = $conn->query("SELECT COUNT(*) AS c FROM soal WHERE kategori_id=$id")->fetch_assoc()['c'];
    if ($cek > 0) {
        setFlash('error', "Kategori tidak bisa dihapus karena masih memiliki <strong>$cek soal</strong>. Hapus soal terlebih dahulu.");
    } else {
        $conn->query("DELETE FROM kategori_soal WHERE id=$id");
        setFlash('success', 'Kategori berhasil dihapus.');
    }
    redirect(BASE_URL . '/admin/kategori.php');
}

// TAMBAH
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'tambah') {
    $nama = trim($_POST['nama_kategori'] ?? '');
    if (!$nama) $errors[] = 'Nama kategori wajib diisi.';
    if (!$errors) {
        $st = $conn->prepare("INSERT INTO kategori_soal (nama_kategori) VALUES (?)");
        $st->bind_param('s', $nama); $st->execute(); $st->close();
        setFlash('success', "Kategori <strong>$nama</strong> berhasil ditambahkan.");
        redirect(BASE_URL . '/admin/kategori.php');
    }
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'edit') {
    $id   = (int)$_POST['id'];
    $nama = trim($_POST['nama_kategori'] ?? '');
    if ($nama) {
        $st = $conn->prepare("UPDATE kategori_soal SET nama_kategori=? WHERE id=?");
        $st->bind_param('si', $nama, $id); $st->execute(); $st->close();
        setFlash('success', 'Kategori berhasil diperbarui.');
    }
    redirect(BASE_URL . '/admin/kategori.php');
}

$list = $conn->query("
    SELECT k.*, COUNT(s.id) AS jml_soal
    FROM kategori_soal k
    LEFT JOIN soal s ON s.kategori_id = k.id
    GROUP BY k.id ORDER BY k.nama_kategori
");

$editData = null;
if (isset($_GET['edit'])) {
    $eid      = (int)$_GET['edit'];
    $er       = $conn->query("SELECT * FROM kategori_soal WHERE id=$eid LIMIT 1");
    $editData = $er ? $er->fetch_assoc() : null;
}

$pageTitle  = 'Kategori Soal';
$activeMenu = 'kategori';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-tags-fill me-2 text-primary"></i>Kategori Soal</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Kategori Soal</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg me-1"></i>Tambah Kategori
    </button>
</div>

<?= renderFlash() ?>

<?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0">
    <?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif; ?>

<div class="row g-3">
<?php if($list&&$list->num_rows>0): while($k=$list->fetch_assoc()): ?>
<div class="col-md-6 col-xl-4">
    <div class="card h-100">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <h6 class="fw-bold mb-1"><?=htmlspecialchars($k['nama_kategori'])?></h6>
                    <p class="text-muted small mb-0">ID: <?=$k['id']?></p>
                </div>
                <div class="d-flex gap-1">
                    <a href="?edit=<?=$k['id']?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                        <i class="bi bi-pencil"></i></a>
                    <a href="?hapus=<?=$k['id']?>" class="btn btn-sm btn-outline-danger btn-icon"
                       data-confirm="Hapus kategori '<?=htmlspecialchars($k['nama_kategori'])?>'?">
                        <i class="bi bi-trash"></i></a>
                </div>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">Jumlah Soal</span>
                <span class="badge bg-primary fs-6"><?=$k['jml_soal']?> soal</span>
            </div>
            <div class="mt-2">
                <a href="<?=BASE_URL?>/admin/soal.php?kat=<?=$k['id']?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-eye me-1"></i>Lihat Soal
                </a>
            </div>
        </div>
    </div>
</div>
<?php endwhile; else: ?>
<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-tags fs-2 d-block mb-2"></i>Belum ada kategori soal
</div></div></div>
<?php endif; ?>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade <?=$errors?'show':''?>" id="modalTambah" tabindex="-1" <?=$errors?'style="display:block"':''?>>
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah">
<?= csrfField() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-tag me-2"></i>Tambah Kategori</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                <input type="text" name="nama_kategori" class="form-control" required
                       value="<?=htmlspecialchars($_POST['nama_kategori']??'')?>"
                       placeholder="cth: Matematika, Bahasa Indonesia…">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<?php if($editData): ?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
<?= csrfField() ?>
            <input type="hidden" name="id"   value="<?=$editData['id']?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Kategori</h5>
                <a href="<?=BASE_URL?>/admin/kategori.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Nama Kategori</label>
                <input type="text" name="nama_kategori" class="form-control" required
                       value="<?=htmlspecialchars($editData['nama_kategori'])?>">
            </div>
            <div class="modal-footer">
                <a href="<?=BASE_URL?>/admin/kategori.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
