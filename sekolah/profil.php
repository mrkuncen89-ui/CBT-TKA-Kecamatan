<?php
// ============================================================
// sekolah/profil.php  — Profil Sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

/* ── Data sekolah ─────────────────────────────────────────── */
$st = $conn->prepare("SELECT id, nama_sekolah, npsn, jenjang, alamat, telepon FROM sekolah WHERE id=? LIMIT 1");
$st->bind_param('i', $sekolahId); $st->execute();
$sekolah = $st->get_result()->fetch_assoc(); $st->close();

if (!$sekolah) {
    setFlash('error', 'Data sekolah tidak ditemukan.');
    redirect(BASE_URL . '/sekolah/dashboard.php');
}

/* ── Statistik peserta & ujian ────────────────────────────── */
$_r1 = $conn->query("SELECT COUNT(*) AS c FROM peserta WHERE sekolah_id=$sekolahId");
$jmlPeserta = $_r1 ? (int)($_r1->fetch_assoc()['c'] ?? 0) : 0; if ($_r1) $_r1->free();

$_r2 = $conn->query("
    SELECT COUNT(DISTINCT p.id) AS c FROM peserta p
    JOIN ujian u ON u.peserta_id=p.id
    WHERE p.sekolah_id=$sekolahId AND u.waktu_selesai IS NOT NULL
");
$jmlSudah = $_r2 ? (int)($_r2->fetch_assoc()['c'] ?? 0) : 0; if ($_r2) $_r2->free();

$_r3 = $conn->query("
    SELECT ROUND(AVG(u.nilai),1) AS r, MAX(u.nilai) AS maks, MIN(u.nilai) AS min
    FROM ujian u JOIN peserta p ON p.id=u.peserta_id
    WHERE p.sekolah_id=$sekolahId AND u.waktu_selesai IS NOT NULL
");
$rataRes = ($_r3 && $_r3->num_rows > 0) ? $_r3->fetch_assoc() : [];
if ($_r3) $_r3->free();
$rata = $rataRes['r'] ?? 0;
$maks = $rataRes['maks'] ?? 0;
$min  = $rataRes['min'] ?? 0;

/* ── Distribusi nilai ─────────────────────────────────────── */
$dist = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0];
$distRes = $conn->query("
    SELECT u.nilai FROM ujian u JOIN peserta p ON p.id=u.peserta_id
    WHERE p.sekolah_id=$sekolahId AND u.waktu_selesai IS NOT NULL
");
if ($distRes) while ($d=$distRes->fetch_assoc()) {
    $n=(int)$d['nilai'];
    if ($n>=90) $dist['A']++;
    elseif ($n>=80) $dist['B']++;
    elseif ($n>=70) $dist['C']++;
    elseif ($n>=60) $dist['D']++;
    else $dist['E']++;
}

/* ── Top 5 peserta ────────────────────────────────────────── */
$top5 = $conn->query("
    SELECT p.nama, p.kelas, MAX(u.nilai) AS nilai
    FROM peserta p JOIN ujian u ON u.peserta_id=p.id
    WHERE p.sekolah_id=$sekolahId AND u.waktu_selesai IS NOT NULL
    GROUP BY p.id ORDER BY nilai DESC LIMIT 5
");

/* ── Username operator ────────────────────────────────────── */
$usernameOp = $conn->query(
    "SELECT username FROM users WHERE sekolah_id=$sekolahId AND role='sekolah' LIMIT 1"
)->fetch_assoc()['username'] ?? '-';

$pageTitle  = 'Profil Sekolah';
$activeMenu = 'profil';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-building-fill me-2 text-primary"></i>Profil Sekolah</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Profil Sekolah</li>
        </ol></nav>
    </div>
    <button class="btn btn-outline-secondary no-print" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Cetak Profil
    </button>
</div>

<?= renderFlash() ?>

<div class="row g-4">
    <!-- Kartu Profil -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center py-4">
                <!-- Avatar sekolah -->
                <div style="width:80px;height:80px;background:linear-gradient(135deg,#2563eb,#7c3aed);
                            border-radius:20px;display:flex;align-items:center;justify-content:center;
                            font-size:36px;margin:0 auto 16px;box-shadow:0 8px 24px rgba(37,99,235,.3)">
                    🏫
                </div>
                <h5 class="fw-800 mb-1"><?= e($sekolah['nama_sekolah']) ?></h5>
                <?php if ($sekolah['npsn']): ?>
                <p class="text-muted small mb-3">NPSN: <code><?= e($sekolah['npsn']) ?></code></p>
                <?php endif; ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                    <i class="bi bi-check-circle me-1"></i>Sekolah Aktif
                </span>
            </div>
            <hr class="my-0">
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted small" style="width:40%">
                            <i class="bi bi-geo-alt me-1"></i>Alamat
                        </td>
                        <td class="small fw-600"><?= e($sekolah['alamat'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">
                            <i class="bi bi-telephone me-1"></i>Telepon
                        </td>
                        <td class="small fw-600"><?= e($sekolah['telepon'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">
                            <i class="bi bi-person-badge me-1"></i>Username
                        </td>
                        <td><code class="small"><?= e($usernameOp) ?></code></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Statistik & Grafik -->
    <div class="col-lg-8">
        <!-- Stat cards -->
        <div class="row g-3 mb-3">
            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="stat-label">Total Peserta</div>
                        <div class="stat-value"><?= $jmlPeserta ?></div>
                        <div class="stat-sub"><?= $jmlSudah ?> sudah ujian</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="bi bi-bar-chart-fill"></i></div>
                    <div>
                        <div class="stat-label">Rata-rata Nilai</div>
                        <div class="stat-value"><?= $rata ?: '-' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="bi bi-trophy-fill"></i></div>
                    <div>
                        <div class="stat-label">Nilai Tertinggi</div>
                        <div class="stat-value"><?= $maks ?: '-' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card">
                    <?php $pct = $jmlPeserta > 0 ? round($jmlSudah/$jmlPeserta*100) : 0; ?>
                    <div class="stat-icon purple"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="stat-label">Progress Ujian</div>
                        <div class="stat-value"><?= $pct ?>%</div>
                        <div class="stat-sub"><?= $jmlSudah ?>/<?= $jmlPeserta ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress bar keikutsertaan -->
        <?php if ($jmlPeserta > 0): ?>
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between small mb-2">
                    <span class="fw-700">Progress Keikutsertaan Ujian</span>
                    <span class="fw-700 text-primary"><?= $pct ?>%</span>
                </div>
                <div class="progress" style="height:10px">
                    <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-2 text-muted text-xs">
                    <span><?= $jmlSudah ?> sudah ujian</span>
                    <span><?= $jmlPeserta - $jmlSudah ?> belum ujian</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grafik distribusi nilai -->
        <?php if (array_sum($dist) > 0): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Distribusi Predikat Nilai</div>
            <div class="card-body">
                <canvas id="chartDist" height="100"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Top 5 Peserta -->
<?php if ($top5 && $top5->num_rows > 0): ?>
<div class="card mt-3">
    <div class="card-header">
        <i class="bi bi-trophy-fill text-warning me-2"></i>Top 5 Peserta Sekolah
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th style="width:60px">Rank</th>
                <th>Nama Peserta</th>
                <th class="text-center">Kelas</th>
                <th class="text-center">Nilai</th>
                <th class="text-center">Predikat</th>
            </tr></thead>
            <tbody>
            <?php $r=1; while ($p=$top5->fetch_assoc()):
                  [$ph,$pt,$pb,$pw] = getPredikat((int)$p['nilai']); ?>
            <tr>
                <td class="text-center fw-800">
                    <?= match($r){1=>'🥇',2=>'🥈',3=>'🥉',default=>"<span class='text-muted'>$r</span>"} ?>
                <?php $r++ ?>
                </td>
                <td><strong><?= e($p['nama']) ?></strong></td>
                <td class="text-center"><?= e($p['kelas'] ?? '-') ?></td>
                <td class="text-center">
                    <strong style="font-size:16px;color:<?= $pw ?>"><?= $p['nilai'] ?></strong>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?= $pb ?>"><?= $ph ?> <?= $pt ?></span>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (array_sum($dist) > 0): ?>
<script>
new Chart(document.getElementById('chartDist'), {
    type: 'bar',
    data: {
        labels: ['A (90-100)','B (80-89)','C (70-79)','D (60-69)','E (<60)'],
        datasets: [{
            label: 'Jumlah Peserta',
            data:  <?= json_encode(array_values($dist)) ?>,
            backgroundColor: ['#0e9f6e','#22c55e','#0ea5e9','#f59e0b','#ef4444'],
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
