<?php
// ============================================================
// sekolah/peserta.php — Kelola Peserta (Operator Sekolah)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];
// Ambil jenjang sekolah untuk dropdown kelas
$_jRes = $conn->query("SELECT jenjang FROM sekolah WHERE id=$sekolahId LIMIT 1");
$jenjangSekolah = ($_jRes && $_jRes->num_rows > 0) ? ($_jRes->fetch_assoc()['jenjang'] ?? 'SD') : 'SD';

function generateKode2(mysqli $db): string {
    do {
        $kode = 'TKA' . strtoupper(substr(md5(uniqid()), 0, 6));
        $cek  = $db->query("SELECT id FROM peserta WHERE kode_peserta='$kode' LIMIT 1");
    } while ($cek && $cek->num_rows > 0);
    return $kode;
}

// HAPUS
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Pastikan peserta milik sekolah ini
    $cek = $conn->query("SELECT id FROM peserta WHERE id=$id AND sekolah_id=$sekolahId LIMIT 1");
    if ($cek && $cek->num_rows > 0) {
        $conn->query("DELETE FROM jawaban WHERE ujian_id IN (SELECT id FROM ujian WHERE peserta_id=$id)");
        $conn->query("DELETE FROM ujian WHERE peserta_id=$id");
        $conn->query("DELETE FROM peserta WHERE id=$id");
        setFlash('success', 'Peserta berhasil dihapus.');
    }
    redirect(BASE_URL . '/sekolah/peserta.php');
}

// TAMBAH
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'tambah') {
    $nama  = trim($_POST['nama'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    if (!$nama) $errors[] = 'Nama peserta wajib diisi.';
    if (!$errors) {
        $kode = generateKode2($conn);
        $st   = $conn->prepare("INSERT INTO peserta (nama,kelas,sekolah_id,kode_peserta) VALUES (?,?,?,?)");
        $st->bind_param('ssis', $nama, $kelas, $sekolahId, $kode);
        $st->execute(); $st->close();
        setFlash('success', "Peserta <strong>$nama</strong> ditambahkan. Kode: <strong>$kode</strong>");
        redirect(BASE_URL . '/sekolah/peserta.php');
    }
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'edit') {
    $id    = (int)$_POST['id'];
    $nama  = trim($_POST['nama'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    if ($nama) {
        $st = $conn->prepare("UPDATE peserta SET nama=?,kelas=? WHERE id=? AND sekolah_id=?");
        $st->bind_param('ssii', $nama, $kelas, $id, $sekolahId);
        $st->execute(); $st->close();
        setFlash('success', 'Data peserta diperbarui.');
    }
    redirect(BASE_URL . '/sekolah/peserta.php');
}

// RESET KODE
if (isset($_GET['resetkode'])) {
    $id   = (int)$_GET['resetkode'];
    $kode = generateKode2($conn);
    $conn->query("UPDATE peserta SET kode_peserta='$kode' WHERE id=$id AND sekolah_id=$sekolahId");
    setFlash('success', "Kode berhasil direset: <strong>$kode</strong>");
    redirect(BASE_URL . '/sekolah/peserta.php');
}

$q    = trim($_GET['q'] ?? '');
$cond = "WHERE p.sekolah_id=$sekolahId";
if ($q) $cond .= " AND (p.nama LIKE '%" . $conn->real_escape_string($q) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($q) . "%')";

$list = $conn->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL) AS sdh_ujian,
           (SELECT nilai FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL ORDER BY id DESC LIMIT 1) AS nilai_terakhir
    FROM peserta p $cond ORDER BY p.nama
");

$editData = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $er  = $conn->query("SELECT * FROM peserta WHERE id=$eid AND sekolah_id=$sekolahId LIMIT 1");
    $editData = $er ? $er->fetch_assoc() : null;
}

$pageTitle  = 'Kelola Peserta';
$activeMenu = 'peserta';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-people-fill me-2 text-primary"></i>Kelola Peserta</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Peserta</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?=BASE_URL?>/sekolah/import_peserta.php" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-person-plus me-1"></i>Tambah Peserta
        </button>
    </div>
</div>

<?= renderFlash() ?>

<?php if($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
    <?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" style="max-width:260px"
               placeholder="Cari nama / kode…" value="<?=htmlspecialchars($q)?>">
        <button class="btn btn-sm btn-outline-primary">Cari</button>
        <?php if($q): ?><a href="?" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
    </form>
</div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Daftar Peserta Sekolah Anda</span>
        <span class="badge bg-primary"><?=$list?$list->num_rows:0?> peserta</span>
    </div>
    <div class="card-body p-0"><div class="table-responsive">
        <table id="tblPesertaSekolah" class="table table-hover datatable mb-0">
            <thead><tr>
                <th>#</th><th>Nama</th><th>Kode Peserta</th><th>Kelas</th>
                <th class="text-center">Status Ujian</th><th class="text-center">Nilai</th>
                <th class="text-center">Aksi</th>
            </tr></thead>
            <tbody>
            <?php if($list&&$list->num_rows>0): $no=1; while($p=$list->fetch_assoc()): ?>
            <tr>
                <td><?=$no++?></td>
                <td><strong><?=htmlspecialchars($p['nama'])?></strong></td>
                <td>
                    <code class="text-primary fw-bold fs-6"><?=htmlspecialchars($p['kode_peserta']??'-')?></code>
                    <a href="?resetkode=<?=$p['id']?>" class="btn btn-xs btn-outline-secondary ms-1 py-0 px-1"
                       style="font-size:10px" data-confirm="Reset kode?">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </td>
                <td><?=htmlspecialchars($p['kelas']??'-')?></td>
                <td class="text-center">
                    <?php if($p['sdh_ujian']>0): ?>
                    <span class="badge bg-success">Sudah Ujian</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Belum Ujian</span>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold">
                    <?php if($p['nilai_terakhir']!==null):?>
                    <span class="<?=$p['nilai_terakhir']>=60?'text-success':'text-danger'?>"><?=$p['nilai_terakhir']?></span>
                    <?php else: echo '<span class="text-muted">-</span>'; endif;?>
                </td>
                <td class="text-center">
                    <a href="?edit=<?=$p['id']?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                        <i class="bi bi-pencil"></i></a>
                    <a href="?hapus=<?=$p['id']?>" class="btn btn-sm btn-outline-danger btn-icon"
                       data-confirm="Hapus peserta ini?"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada peserta terdaftar
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade <?=$errors?'show':''?>" id="modalTambah" tabindex="-1" <?=$errors?'style="display:block"':''?>>
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah">
            <?= csrfField() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Tambah Peserta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" required value="<?=htmlspecialchars($_POST['nama']??'')?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kelas</label>
                    <select name="kelas" class="form-select">
                        <?php echo renderKelasOptions('', $jenjangSekolah); ?>
                    </select>
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-key me-1"></i>Kode peserta dibuat otomatis oleh sistem.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php if($editData): ?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
            <?= csrfField() ?>
            <input type="hidden" name="id"   value="<?=$editData['id']?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Peserta</h5>
                <a href="<?=BASE_URL?>/sekolah/peserta.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama</label>
                    <input type="text" name="nama" class="form-control" required value="<?=htmlspecialchars($editData['nama'])?>">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Kelas</label>
                    <select name="kelas" class="form-select">
                        <?php echo renderKelasOptions($editData['kelas'] ?? '', $jenjangSekolah); ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?=BASE_URL?>/sekolah/peserta.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
