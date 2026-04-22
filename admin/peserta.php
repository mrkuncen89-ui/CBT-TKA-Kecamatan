<?php
// ============================================================
// admin/peserta.php — Kelola Peserta (Admin Kecamatan)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// ── Helper: generate kode peserta unik ───────────────────────
function generateKode(mysqli $db): string {
    do {
        $kode = 'TKA' . strtoupper(substr(md5(uniqid()), 0, 6));
        $cek  = $db->query("SELECT id FROM peserta WHERE kode_peserta='$kode' LIMIT 1");
    } while ($cek && $cek->num_rows > 0);
    return $kode;
}

// ── HAPUS ────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $_qNama = $conn->query("SELECT nama FROM peserta WHERE id=$id LIMIT 1");
    $namaDel = ($_qNama && $_qNama->num_rows > 0) ? $_qNama->fetch_assoc()['nama'] : "ID $id";
    $conn->query("DELETE FROM jawaban WHERE ujian_id IN (SELECT id FROM ujian WHERE peserta_id=$id)");
    $conn->query("DELETE FROM ujian WHERE peserta_id=$id");
    $conn->query("DELETE FROM peserta WHERE id=$id");
    logActivity($conn, 'Hapus Peserta', "Nama: $namaDel");
    setFlash('success', 'Peserta berhasil dihapus.');
    redirect(BASE_URL . '/admin/peserta.php');
}

// ── TAMBAH ───────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'tambah') {
    $nama      = trim($_POST['nama'] ?? '');
    $kelas     = trim($_POST['kelas'] ?? '');
    $sekolahId = (int)($_POST['sekolah_id'] ?? 0);

    if (!$nama)      $errors[] = 'Nama peserta wajib diisi.';
    if (!$sekolahId) $errors[] = 'Sekolah wajib dipilih.';

    if (!$errors) {
        $kode = generateKode($conn);
        $st   = $conn->prepare("INSERT INTO peserta (nama,kelas,sekolah_id,kode_peserta) VALUES (?,?,?,?)");
        $st->bind_param('ssis', $nama, $kelas, $sekolahId, $kode);
        $st->execute(); $st->close();
        logActivity($conn, 'Tambah Peserta', "Nama: $nama | Kelas: $kelas | Sekolah ID: $sekolahId | Kode: $kode");
        setFlash('success', "Peserta <strong>$nama</strong> ditambahkan. Kode: <strong>$kode</strong>");
        redirect(BASE_URL . '/admin/peserta.php');
    }
}

// ── EDIT ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'edit') {
    $id        = (int)$_POST['id'];
    $nama      = trim($_POST['nama'] ?? '');
    $kelas     = trim($_POST['kelas'] ?? '');
    $sekolahId = (int)($_POST['sekolah_id'] ?? 0);

    if ($nama && $sekolahId) {
        $st = $conn->prepare("UPDATE peserta SET nama=?,kelas=?,sekolah_id=? WHERE id=?");
        $st->bind_param('ssii', $nama, $kelas, $sekolahId, $id);
        $st->execute(); $st->close();
        logActivity($conn, 'Edit Peserta', "ID: $id | Nama: $nama | Kelas: $kelas");
        setFlash('success', 'Data peserta berhasil diperbarui.');
    }
    redirect(BASE_URL . '/admin/peserta.php');
}

// ── RESET KODE ───────────────────────────────────────────────
if (isset($_GET['resetkode'])) {
    $id   = (int)$_GET['resetkode'];
    $kode = generateKode($conn);
    $conn->query("UPDATE peserta SET kode_peserta='$kode' WHERE id=$id");
    setFlash('success', "Kode peserta berhasil direset menjadi: <strong>$kode</strong>");
    redirect(BASE_URL . '/admin/peserta.php');
}

// ── DATA ─────────────────────────────────────────────────────
$filterSek = (int)($_GET['sekolah_id'] ?? 0);
$q         = trim($_GET['q'] ?? '');
$where     = "WHERE 1=1";
if ($filterSek) $where .= " AND p.sekolah_id=$filterSek";
if ($q)         $where .= " AND (p.nama LIKE '%" . $conn->real_escape_string($q) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($q) . "%')";

$list = $conn->query("
    SELECT p.*, s.nama_sekolah,
           (SELECT COUNT(*) FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL) AS sdh_ujian,
           (SELECT nilai FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL ORDER BY id DESC LIMIT 1) AS nilai_terakhir
    FROM peserta p
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    $where
    ORDER BY s.nama_sekolah, p.nama
");

$sekolahList = $conn->query("SELECT id, nama_sekolah, jenjang FROM sekolah ORDER BY nama_sekolah");
// Map sekolah_id => jenjang untuk dropdown kelas dinamis
$sekolahJenjang = [];
$_sq = $conn->query("SELECT id, jenjang FROM sekolah");
if ($_sq) while ($_sr = $_sq->fetch_assoc()) $sekolahJenjang[(int)$_sr['id']] = $_sr['jenjang'] ?? 'SD';
$sekolahArr  = [];
if ($sekolahList) { $sekolahList->data_seek(0); while($r=$sekolahList->fetch_assoc()) $sekolahArr[$r['id']]=$r['nama_sekolah']; }

// Edit modal data
$editData = null;
if (isset($_GET['edit'])) {
    $eid      = (int)$_GET['edit'];
    $er       = $conn->query("SELECT * FROM peserta WHERE id=$eid LIMIT 1");
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
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Peserta</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/import_peserta.php" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
        </a>
        <a href="<?= BASE_URL ?>/admin/kartu_ujian.php" class="btn btn-info text-white" target="_blank">
            <i class="bi bi-credit-card-2-front me-1"></i>Kartu Ujian
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-person-plus me-1"></i>Tambah Peserta
        </button>
    </div>
</div>

<?= renderFlash() ?>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
    <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2" method="GET">
            <select name="sekolah_id" class="form-select form-select-sm" style="width:200px">
                <option value="">Semua Sekolah</option>
                <?php foreach($sekolahArr as $sid=>$snm): ?>
                <option value="<?=$sid?>" <?=$filterSek==$sid?'selected':''?>><?=htmlspecialchars($snm)?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm" style="width:200px"
                   placeholder="Cari nama / kode…" value="<?=htmlspecialchars($q)?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Daftar Peserta</span>
        <span class="badge bg-primary"><?= $list ? $list->num_rows : 0 ?> peserta</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblPeserta" class="table table-hover datatable mb-0">
                <thead><tr>
                    <th>#</th><th>Nama Peserta</th><th>Kode Peserta</th>
                    <th>Kelas</th><th>Sekolah</th>
                    <th class="text-center">Ujian</th><th class="text-center">Nilai</th>
                    <th class="text-center" style="width:150px">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if ($list && $list->num_rows > 0): $no=1; while($p=$list->fetch_assoc()): ?>
                <tr>
                    <td><?=$no++?></td>
                    <td><strong><?=htmlspecialchars($p['nama'])?></strong></td>
                    <td>
                        <code class="text-primary fw-bold"><?=htmlspecialchars($p['kode_peserta']??'-')?></code>
                        <a href="?resetkode=<?=$p['id']?>" class="btn btn-xs btn-outline-secondary ms-1 py-0 px-1"
                           style="font-size:10px" data-confirm="Reset kode peserta ini?">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </td>
                    <td><?=htmlspecialchars($p['kelas']??'-')?></td>
                    <td><?=htmlspecialchars($p['nama_sekolah']??'-')?></td>
                    <td class="text-center">
                        <?php if($p['sdh_ujian']>0): ?>
                        <span class="badge bg-success"><i class="bi bi-check me-1"></i>Sudah</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-bold">
                        <?php if($p['nilai_terakhir']!==null): ?>
                        <span class="<?=$p['nilai_terakhir']>=60?'text-success':'text-danger'?>"><?=$p['nilai_terakhir']?></span>
                        <?php else: echo '<span class="text-muted">-</span>'; endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="<?=BASE_URL?>/admin/kartu_ujian.php?peserta_id=<?=$p['id']?>" 
                           class="btn btn-sm btn-outline-info btn-icon" title="Kartu Ujian" target="_blank">
                            <i class="bi bi-credit-card-2-front"></i></a>
                        <a href="?edit=<?=$p['id']?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                            <i class="bi bi-pencil"></i></a>
                        <a href="?hapus=<?=$p['id']?>" class="btn btn-sm btn-outline-danger btn-icon"
                           data-confirm="Hapus peserta '<?=htmlspecialchars($p['nama'])?>'?">
                            <i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data peserta
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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
                    <input type="text" name="nama" class="form-control" required
                           value="<?=htmlspecialchars($_POST['nama']??'')?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Sekolah <span class="text-danger">*</span></label>
                    <select name="sekolah_id" class="form-select" id="sekolahSelectTambah" required
                            onchange="updateKelasBySekolah(this.value, 'kelasSelectTambah')">
                        <option value="">-- Pilih Sekolah --</option>
                        <?php foreach($sekolahArr as $sid=>$snm): ?>
                        <option value="<?=$sid?>"
                            data-jenjang="<?= htmlspecialchars($sekolahJenjang[$sid] ?? 'SD') ?>"
                            <?=(($_POST['sekolah_id']??0)==$sid)?'selected':''?>>
                            <?=htmlspecialchars($snm)?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kelas</label>
                    <select name="kelas" class="form-select" id="kelasSelectTambah">
                        <?php
                        $jenjangTambah = isset($_POST['sekolah_id']) ? ($sekolahJenjang[(int)$_POST['sekolah_id']] ?? 'SD') : 'SD';
                        echo renderKelasOptions($_POST['kelas'] ?? '', $jenjangTambah);
                        ?>
                    </select>
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Kode peserta akan dibuat otomatis oleh sistem.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<?php if ($editData): ?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
<?= csrfField() ?>
            <input type="hidden" name="id"   value="<?=$editData['id']?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Peserta</h5>
                <a href="<?=BASE_URL?>/admin/peserta.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" required
                           value="<?=htmlspecialchars($editData['nama'])?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kelas</label>
                    <select name="kelas" class="form-select" id="kelasSelectEdit">
                        <?php
                        $jenjangEdit = $sekolahJenjang[$editData['sekolah_id'] ?? 0] ?? 'SD';
                        echo renderKelasOptions($editData['kelas'] ?? '', $jenjangEdit);
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Sekolah</label>
                    <select name="sekolah_id" class="form-select" id="sekolahSelectEdit"
                            onchange="updateKelasBySekolah(this.value, 'kelasSelectEdit')">
                        <?php foreach($sekolahArr as $sid=>$snm): ?>
                        <option value="<?=$sid?>"
                            data-jenjang="<?= htmlspecialchars($sekolahJenjang[$sid] ?? 'SD') ?>"
                            <?=($editData['sekolah_id']==$sid)?'selected':''?>>
                            <?=htmlspecialchars($snm)?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Kode Peserta</label>
                    <input type="text" class="form-control" value="<?=htmlspecialchars($editData['kode_peserta']??'')?>" disabled>
                    <div class="form-text">Kode tidak bisa diubah manual. Gunakan tombol reset.</div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?=BASE_URL?>/admin/peserta.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php if($errors): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('modalTambah')).show())</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
// Data kelas per jenjang
const kelasByJenjang = {
    'SD':  ['I','II','III','IV','V','VI'],
    'MI':  ['I','II','III','IV','V','VI'],
    'SMP': ['VII','VIII','IX'],
    'MTS': ['VII','VIII','IX'],
    'SMA': ['X','XI','XII'],
    'MA':  ['X','XI','XII'],
    'SMK': ['X','XI','XII'],
};

function updateKelasBySekolah(sekolahId, targetSelectId) {
    // Cari select sekolah yang memanggil — bisa tambah atau edit
    // Ambil jenjang dari option yang dipilih di elemen manapun yang punya option value=sekolahId
    let jenjang = 'SD';
    const allSekolahSelects = document.querySelectorAll('select[name="sekolah_id"]');
    for (const sel of allSekolahSelects) {
        const opt = sel.querySelector(`option[value="${sekolahId}"]`);
        if (opt && opt.dataset.jenjang) { jenjang = opt.dataset.jenjang; break; }
    }
    const kelasList = kelasByJenjang[jenjang] || kelasByJenjang['SD'];

    const kelasSelect = document.getElementById(targetSelectId);
    if (!kelasSelect) return;

    const currentVal = kelasSelect.value;
    kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
    kelasList.forEach(k => {
        const o = document.createElement('option');
        o.value = k;
        o.textContent = 'Kelas ' + k;
        if (k === currentVal) o.selected = true;
        kelasSelect.appendChild(o);
    });
}

// Trigger saat halaman load jika sudah ada sekolah terpilih
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('sekolahSelectTambah');
    if (sel && sel.value) updateKelasBySekolah(sel.value, 'kelasSelectTambah');
});
</script>
