<?php
// ============================================================
// sekolah/mulai_tes.php — Halaman Mulai Tes untuk Operator
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = $user['sekolah_id'];

$kategori = $conn->query("SELECT * FROM kategori_soal ORDER BY nama_kategori");
$peserta  = $conn->query("SELECT * FROM peserta WHERE sekolah_id = $sekolahId ORDER BY nama");

$pageTitle  = 'Mulai Tes';
$activeMenu = 'mulai_tes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Mulai Tes</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Mulai Tes</li>
        </ol></nav>
    </div>
</div>

<?= renderFlash() ?>

<div class="row g-4">
    <!-- Info -->
    <div class="col-lg-4">
        <div class="card mb-3" style="border-left:4px solid var(--primary)">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-2 text-primary"></i>Petunjuk Pelaksanaan</h6>
                <ul class="mb-0 small text-muted ps-3">
                    <li>Pilih kategori tes yang akan dikerjakan</li>
                    <li>Pilih peserta yang akan mengikuti tes</li>
                    <li>Klik <strong>Buka Sesi Ujian</strong></li>
                    <li>Arahkan peserta ke halaman ujian</li>
                    <li>Peserta login menggunakan NISN dan password</li>
                </ul>
            </div>
        </div>

        <!-- Kategori yang tersedia -->
        <div class="card">
            <div class="card-header"><i class="bi bi-tag me-2"></i>Kategori Tes Tersedia</div>
            <div class="card-body p-0">
                <?php if ($kategori && $kategori->num_rows > 0):
                      while ($k = $kategori->fetch_assoc()): ?>
                <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                    <div>
                        <div class="fw-bold small"><?= htmlspecialchars($k['nama_kategori']) ?></div>
                        <div class="text-muted" style="font-size:11px">
                            <?php
                            $jmlSoal = $conn->query("SELECT COUNT(*) AS c FROM soal WHERE kategori_id=".(int)$k['id'])->fetch_assoc()['c'];
                            ?>
                            <?= $jmlSoal ?> soal
                        </div>
                    </div>
                    <span class="badge bg-light text-dark border">ID: <?= $k['id'] ?></span>
                </div>
                <?php endwhile; else: ?>
                <p class="text-muted text-center py-3 small">Belum ada kategori tes</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Form Buka Sesi -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-play-circle-fill text-success me-2"></i>Buka Sesi Ujian</div>
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>/ujian/login_peserta.php">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih Kategori Tes <span class="text-danger">*</span></label>
                        <div class="row g-2" id="kategoriGrid">
                            <?php if ($kategori): $kategori->data_seek(0); while ($k = $kategori->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <label class="w-100" style="cursor:pointer">
                                    <input type="radio" name="kategori_id" value="<?= $k['id'] ?>" class="d-none kategori-radio">
                                    <div class="card border-2 kategori-card" style="border-color:var(--border);transition:all .2s">
                                        <div class="card-body py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="stat-icon blue" style="width:42px;height:42px;font-size:18px">
                                                    <i class="bi bi-book"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($k['nama_kategori']) ?></div>
                                                    <div class="text-muted small">
                                                        <?php
                                                        $jml = $conn->query("SELECT COUNT(*) AS c FROM soal WHERE kategori_id=".(int)$k['id'])->fetch_assoc()['c'];
                                                        ?>
                                                        <?= $jml ?> soal tersedia
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endwhile; endif; ?>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center">
                        <p class="text-muted mb-4">Peserta akan diarahkan ke halaman login ujian secara mandiri.</p>
                        <a href="<?= BASE_URL ?>/ujian/login_peserta.php" class="btn btn-success btn-lg px-5">
                            <i class="bi bi-box-arrow-up-right me-2"></i>Buka Halaman Login Peserta
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Daftar Peserta Belum Ujian -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-person-exclamation me-2 text-warning"></i>Peserta Belum Mengikuti Ujian</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr><th>Nama</th><th>Kode Peserta</th><th>Kelas</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $belum = $conn->query("SELECT p.* FROM peserta p
                                WHERE p.sekolah_id = $sekolahId
                                AND p.id NOT IN (SELECT DISTINCT peserta_id FROM ujian WHERE waktu_selesai IS NOT NULL)
                                ORDER BY p.nama LIMIT 10");
                            if ($belum && $belum->num_rows > 0):
                                while ($b = $belum->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['nama']) ?></td>
                                <td><code><?= htmlspecialchars($b['kode_peserta']) ?></code></td>
                                <td><?= htmlspecialchars($b['kelas'] ?? '-') ?></td>
                                <td class="text-center"><span class="badge bg-warning text-dark">Belum Ujian</span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Semua peserta sudah mengikuti ujian 🎉</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.kategori-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.kategori-card').forEach(c => {
            c.style.borderColor = 'var(--border)';
            c.style.background  = '#fff';
        });
        const card = this.closest('label').querySelector('.kategori-card');
        card.style.borderColor = 'var(--primary)';
        card.style.background  = '#eff6ff';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
