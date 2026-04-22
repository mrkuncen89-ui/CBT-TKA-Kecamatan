<?php
// ============================================================
// admin/rekap_sekolah.php — Rekap Nilai per Sekolah
// Ringkasan: rata-rata, jumlah peserta, lulus, ranking sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterKat    = (int)($_GET['kategori_id'] ?? 0);
$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$filterKelas  = trim($_GET['kelas'] ?? '');

$kkm = (int)getSetting($conn, 'kkm', '60');

// ── Filter kondisi ─────────────────────────────────────────────
$conds = ["h.nilai IS NOT NULL"];
if ($filterKat)    $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($filterJadwal) $conds[] = "h.jadwal_id = $filterJadwal";
if ($filterKelas)  $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
$where = buildWhere($conds);

// ── Rekap per sekolah ──────────────────────────────────────────
$sqlRekap = "
    SELECT
        s.id AS sekolah_id,
        s.nama_sekolah,
        s.npsn,
        COUNT(h.id)                          AS jml_peserta,
        ROUND(AVG(h.nilai), 1)               AS rata_nilai,
        MAX(h.nilai)                         AS nilai_max,
        MIN(h.nilai)                         AS nilai_min,
        SUM(CASE WHEN h.nilai >= $kkm THEN 1 ELSE 0 END) AS jml_lulus,
        SUM(CASE WHEN h.nilai < $kkm  THEN 1 ELSE 0 END) AS jml_tidak_lulus,
        ROUND(AVG(h.jml_benar), 1)           AS rata_benar,
        ROUND(AVG(h.total_soal), 0)          AS rata_total,
        ROUND(AVG(h.durasi_detik / 60), 1)   AS rata_durasi
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    $where
    GROUP BY s.id, s.nama_sekolah, s.npsn
    ORDER BY rata_nilai DESC
";
$rekapRes = $conn->query($sqlRekap);
$rekap    = [];
$rankSek  = 1;
if ($rekapRes) while ($r = $rekapRes->fetch_assoc()) {
    $r['rank'] = $rankSek++;
    $rekap[]   = $r;
}

// ── Statistik global ───────────────────────────────────────────
$totalSekolah  = count($rekap);
$totalPeserta  = array_sum(array_column($rekap, 'jml_peserta'));
$rataGlobal    = $totalPeserta > 0
    ? round(array_sum(array_map(fn($r) => $r['rata_nilai'] * $r['jml_peserta'], $rekap)) / $totalPeserta, 1)
    : 0;
$totalLulus    = array_sum(array_column($rekap, 'jml_lulus'));
$pctLulus      = $totalPeserta > 0 ? round($totalLulus / $totalPeserta * 100, 1) : 0;

// ── Jumlah peserta yang belum ujian per sekolah ───────────────
$belumRes = $conn->query("
    SELECT p.sekolah_id, COUNT(*) AS jml_belum
    FROM peserta p
    WHERE p.id NOT IN (
        SELECT DISTINCT h2.peserta_id FROM hasil_ujian h2
        " . ($filterJadwal ? "WHERE h2.jadwal_id = $filterJadwal" : "") . "
    )
    " . ($filterKelas ? "AND p.kelas = '".$conn->real_escape_string($filterKelas)."'" : "") . "
    GROUP BY p.sekolah_id
");
$belumMap = [];
if ($belumRes) while ($b = $belumRes->fetch_assoc())
    $belumMap[$b['sekolah_id']] = (int)$b['jml_belum'];

// ── Filter lists ───────────────────────────────────────────────
$katList    = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$jadwalList = $conn->query("SELECT j.id, j.tanggal, j.keterangan, k.nama_kategori FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id=j.kategori_id ORDER BY j.tanggal DESC, j.jam_mulai DESC");
$kelasList  = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE kelas IS NOT NULL ORDER BY kelas");

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan     = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$tahunPelajaran    = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));

$pageTitle  = 'Rekap Per Sekolah';
$activeMenu = 'rekap_sekolah';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-building me-2 text-primary"></i>Rekap Nilai per Sekolah</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Rekap Per Sekolah</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/export_excel.php?mode=rekap_sekolah&<?= http_build_query(array_filter(['kategori_id'=>$filterKat,'jadwal_id'=>$filterJadwal,'kelas'=>$filterKelas])) ?>"
           class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
        </a>
        <a href="<?= BASE_URL ?>/admin/export_pdf.php?mode=rekap_sekolah&<?= http_build_query(array_filter(['kategori_id'=>$filterKat,'jadwal_id'=>$filterJadwal,'kelas'=>$filterKelas])) ?>"
           class="btn btn-danger" target="_blank">
            <i class="bi bi-printer me-1"></i>Cetak PDF
        </a>
    </div>
</div>

<?= renderFlash() ?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label small fw-semibold mb-1">Mata Pelajaran</label>
                <select name="kategori_id" class="form-select form-select-sm">
                    <option value="">Semua Mapel</option>
                    <?php if ($katList) while ($k=$katList->fetch_assoc()): ?>
                    <option value="<?=$k['id']?>" <?=$filterKat==$k['id']?'selected':''?>>
                        <?= e($k['nama_kategori']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label small fw-semibold mb-1">Jadwal Ujian</label>
                <select name="jadwal_id" class="form-select form-select-sm">
                    <option value="">Semua Jadwal</option>
                    <?php if ($jadwalList) while ($jd=$jadwalList->fetch_assoc()): ?>
                    <option value="<?=$jd['id']?>" <?=$filterJadwal==$jd['id']?'selected':''?>>
                        <?= date('d/m/Y', strtotime($jd['tanggal'])) ?>
                        <?= $jd['keterangan'] ? ' — '.$jd['keterangan'] : '' ?>
                        <?= $jd['nama_kategori'] ? ' ('.$jd['nama_kategori'].')' : '' ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold mb-1">Kelas</label>
                <select name="kelas" class="form-select form-select-sm">
                    <option value="">Semua Kelas</option>
                    <?php if ($kelasList) while ($kl=$kelasList->fetch_assoc()): ?>
                    <option value="<?=$kl['kelas']?>" <?=$filterKelas===$kl['kelas']?'selected':''?>>
                        <?= e($kl['kelas']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-building"></i></div>
            <div>
                <div class="stat-label">Total Sekolah</div>
                <div class="stat-value"><?= $totalSekolah ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-label">Total Peserta Ujian</div>
                <div class="stat-value"><?= $totalPeserta ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-bar-chart-fill"></i></div>
            <div>
                <div class="stat-label">Rata-rata Kecamatan</div>
                <div class="stat-value"><?= $rataGlobal ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-label">Tingkat Kelulusan</div>
                <div class="stat-value"><?= $pctLulus ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Rekap -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2 text-primary"></i>Ringkasan per Sekolah</span>
        <span class="badge bg-primary"><?= $totalSekolah ?> sekolah</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tblRekap">
            <thead class="table-primary">
                <tr>
                    <th class="text-center" style="width:40px">Rank</th>
                    <th>Nama Sekolah</th>
                    <th class="text-center">Peserta<br><small>Ujian</small></th>
                    <th class="text-center">Belum<br><small>Ujian</small></th>
                    <th class="text-center">Rata-rata<br><small>Nilai</small></th>
                    <th class="text-center">Tertinggi</th>
                    <th class="text-center">Terendah</th>
                    <th class="text-center">Lulus<br><small>(≥<?= $kkm ?>)</small></th>
                    <th class="text-center">Tidak<br><small>Lulus</small></th>
                    <th class="text-center">%<br><small>Lulus</small></th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rekap): foreach ($rekap as $r):
                $pctLulusRow = $r['jml_peserta'] > 0 ? round($r['jml_lulus'] / $r['jml_peserta'] * 100, 1) : 0;
                $belum = $belumMap[$r['sekolah_id']] ?? 0;
                $rowClass = $r['rank'] === 1 ? 'table-warning' : '';
                [$pred] = getPredikat((int)$r['rata_nilai']);
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="text-center fw-bold">
                    <?= match($r['rank']){1=>'🥇',2=>'🥈',3=>'🥉',default=>'#'.$r['rank']} ?>
                </td>
                <td>
                    <strong><?= e($r['nama_sekolah']) ?></strong>
                    <?php if ($r['npsn']): ?>
                    <br><small class="text-muted">NPSN: <?= e($r['npsn']) ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary"><?= $r['jml_peserta'] ?></span>
                </td>
                <td class="text-center">
                    <?php if ($belum > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $belum ?></span>
                    <?php else: ?>
                    <span class="text-success small">✓ Semua</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="fw-bold fs-6 text-<?= $r['rata_nilai'] >= $kkm ? 'success' : 'danger' ?>">
                        <?= $r['rata_nilai'] ?>
                    </span>
                    <br><small class="badge bg-secondary"><?= $pred ?></small>
                </td>
                <td class="text-center text-success fw-bold"><?= $r['nilai_max'] ?></td>
                <td class="text-center text-danger fw-bold"><?= $r['nilai_min'] ?></td>
                <td class="text-center">
                    <span class="badge bg-success"><?= $r['jml_lulus'] ?></span>
                </td>
                <td class="text-center">
                    <?php if ($r['jml_tidak_lulus'] > 0): ?>
                    <span class="badge bg-danger"><?= $r['jml_tidak_lulus'] ?></span>
                    <?php else: ?>
                    <span class="text-muted">0</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="d-flex align-items-center gap-1 justify-content-center">
                        <div class="progress" style="width:50px;height:8px">
                            <div class="progress-bar bg-<?= $pctLulusRow >= 80 ? 'success' : ($pctLulusRow >= 60 ? 'warning' : 'danger') ?>"
                                 style="width:<?= $pctLulusRow ?>%"></div>
                        </div>
                        <small class="fw-bold"><?= $pctLulusRow ?>%</small>
                    </div>
                </td>
                <td class="text-center">
                    <a href="<?= BASE_URL ?>/admin/hasil.php?sekolah_id=<?= $r['sekolah_id'] ?><?= $filterKat ? '&kategori_id='.$filterKat : '' ?><?= $filterJadwal ? '&jadwal_id='.$filterJadwal : '' ?>"
                       class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="11" class="text-center text-muted py-4">
                <i class="bi bi-inbox me-2"></i>Belum ada data ujian
            </td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($rekap): ?>
            <tfoot class="table-secondary fw-bold">
                <tr>
                    <td colspan="2" class="text-end">Total / Rata-rata Kecamatan</td>
                    <td class="text-center"><?= $totalPeserta ?></td>
                    <td class="text-center"><?= array_sum(array_values($belumMap)) ?></td>
                    <td class="text-center text-primary fs-6"><?= $rataGlobal ?></td>
                    <td class="text-center text-success"><?= $rekap ? max(array_column($rekap,'nilai_max')) : '-' ?></td>
                    <td class="text-center text-danger"><?= $rekap ? min(array_column($rekap,'nilai_min')) : '-' ?></td>
                    <td class="text-center"><?= $totalLulus ?></td>
                    <td class="text-center"><?= $totalPeserta - $totalLulus ?></td>
                    <td class="text-center"><?= $pctLulus ?>%</td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Grafik Perbandingan -->
<?php if (count($rekap) > 1): ?>
<div class="card mt-4">
    <div class="card-header"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Perbandingan Rata-rata Nilai per Sekolah</div>
    <div class="card-body">
        <canvas id="chartRekap" height="<?= min(60, count($rekap) * 8) ?>"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode(array_column($rekap,'nama_sekolah')) ?>;
const nilaiData = <?= json_encode(array_column($rekap,'rata_nilai')) ?>;
const kkm = <?= $kkm ?>;
new Chart(document.getElementById('chartRekap'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Rata-rata Nilai',
            data: nilaiData,
            backgroundColor: nilaiData.map(v => v >= kkm ? 'rgba(22,163,74,.7)' : 'rgba(220,38,38,.7)'),
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            annotation: { annotations: { kkm: { type:'line', yMin:kkm, yMax:kkm, borderColor:'orange', borderWidth:2, label:{ content:'KKM '+kkm, display:true } }}}
        },
        scales: {
            y: { beginAtZero: true, max: 100, grid: { color:'rgba(0,0,0,.05)' } },
            x: { ticks: { font: { size: 11 } } }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
