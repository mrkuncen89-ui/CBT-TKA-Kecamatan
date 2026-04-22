<?php
// ============================================================
// admin/sertifikat.php — Cetak Sertifikat Peserta
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$pageTitle  = 'Cetak Sertifikat';
$activeMenu = 'sertifikat';

// Filter
$sekolahId = (int)($_GET['sekolah_id'] ?? 0);
$kategoriId = (int)($_GET['kategori_id'] ?? 0);

$where = "u.waktu_selesai IS NOT NULL";
if ($sekolahId) $where .= " AND p.sekolah_id = $sekolahId";
if ($kategoriId) $where .= " AND u.kategori_id = $kategoriId";

$sql = "SELECT u.id as ujian_id, u.nilai, u.waktu_selesai, 
               p.nama, p.kode_peserta, s.nama_sekolah, k.nama_kategori
        FROM ujian u
        JOIN peserta p ON p.id = u.peserta_id
        JOIN sekolah s ON s.id = p.sekolah_id
        JOIN kategori_soal k ON k.id = u.kategori_id
        WHERE $where
        ORDER BY u.nilai DESC, p.nama ASC";

$res = $conn->query($sql);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-patch-check-fill me-2 text-primary"></i>Cetak Sertifikat</h2>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &nbsp;&raquo;&nbsp; Sertifikat
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Filter Sekolah</label>
                <select name="sekolah_id" class="form-select">
                    <option value="">Semua Sekolah</option>
                    <?php
                    $qSek = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");
                    while($s = $qSek->fetch_assoc()):
                    ?>
                    <option value="<?= $s['id'] ?>" <?= $sekolahId==$s['id']?'selected':'' ?>><?= e($s['nama_sekolah']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Kategori Ujian</label>
                <select name="kategori_id" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php
                    $qKat = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
                    while($k = $qKat->fetch_assoc()):
                    ?>
                    <option value="<?= $k['id'] ?>" <?= $kategoriId==$k['id']?'selected':'' ?>><?= e($k['nama_kategori']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter me-2"></i>Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Nama Peserta</th>
                        <th>Sekolah</th>
                        <th>Kategori</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-center">Predikat</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while($r = $res->fetch_assoc()): 
                            [$ph, $pt, $pb] = getPredikat((int)$r['nilai']);
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= e($r['nama']) ?></div>
                                <div class="text-muted text-xs"><?= e($r['kode_peserta']) ?></div>
                            </td>
                            <td><?= e($r['nama_sekolah']) ?></td>
                            <td><?= e($r['nama_kategori']) ?></td>
                            <td class="text-center fw-bold"><?= $r['nilai'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $pb ?>"><?= $ph ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="cetak_sertifikat.php?id=<?= $r['ujian_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-printer me-1"></i> Cetak
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                Belum ada data ujian yang selesai untuk filter ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
