<?php
// ============================================================
// admin/nilai.php
// Rekap nilai semua peserta + ranking + grafik + export
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('admin_kecamatan');

// ── Filter ────────────────────────────────────────────────────
$filterSekolah = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas   = trim($_GET['kelas'] ?? '');
$search        = trim($_GET['q'] ?? '');

$where = "WHERE h.nilai IS NOT NULL";
if ($filterSekolah) $where .= " AND p.sekolah_id = $filterSekolah";
if ($filterKelas)   $where .= " AND p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($search)        $where .= " AND (p.nama LIKE '%" . $conn->real_escape_string($search) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($search) . "%')";

// ── Query utama: semua nilai dengan ranking ───────────────────
// Query optimasi: pakai hasil_ujian + VIEW v_ranking
$sql = "
    SELECT
        h.ujian_id,
        h.peserta_id,
        h.nilai,
        h.waktu_mulai,
        h.waktu_selesai,
        h.jml_benar,
        h.total_soal         AS jml_dijawab,
        h.jml_salah,
        h.jml_kosong,
        FLOOR(h.durasi_detik / 60) AS durasi_menit,
        p.nama,
        p.kelas,
        p.kode_peserta,
        s.nama_sekolah,
        COALESCE(k.id, 0) AS kategori_id,
        COALESCE(k.nama_kategori, 'Tanpa Mapel') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    ORDER BY h.nilai DESC, h.waktu_selesai ASC
";
$hasilRes = $conn->query($sql);
$semua    = []; $noUrut = 1;
$ledgerData = [];
$mapelList  = [];

if ($hasilRes) while ($row = $hasilRes->fetch_assoc()) { 
    $row['no_urut'] = $noUrut++; 
    $semua[] = $row; 

    $pid = $row['peserta_id'];
    $mid = $row['kategori_id'];
    if (!isset($ledgerData[$pid])) {
        $ledgerData[$pid] = [
            'nama' => $row['nama'],
            'kode' => $row['kode_peserta'],
            'sekolah' => $row['nama_sekolah'],
            'kelas' => $row['kelas'],
            'nilai' => []
        ];
    }
    $ledgerData[$pid]['nilai'][$mid] = $row['nilai'];
    if (!isset($mapelList[$mid])) $mapelList[$mid] = $row['nama_kategori'];
}
asort($mapelList);

// ── Statistik agregat ─────────────────────────────────────────
$totalPeserta = count($semua);
$nilaiArr     = array_column($semua, 'nilai');
$rataRata     = $totalPeserta > 0 ? round(array_sum($nilaiArr) / $totalPeserta, 1) : 0;
$nilaiMax     = $totalPeserta > 0 ? max($nilaiArr) : 0;
$nilaiMin     = $totalPeserta > 0 ? min($nilaiArr) : 0;
$kkm          = (int)getSetting($conn, 'kkm', '60');
$lulus        = count(array_filter($nilaiArr, fn($n) => $n >= $kkm));

// ── Data untuk grafik distribusi nilai ───────────────────────
$distribusi = [
    'A (90-100)' => 0, 'B (80-89)' => 0, 'C (70-79)' => 0,
    'D (60-69)'  => 0, 'E (<60)'   => 0,
];
foreach ($nilaiArr as $n) {
    if      ($n >= 90) $distribusi['A (90-100)']++;
    elseif  ($n >= 80) $distribusi['B (80-89)']++;
    elseif  ($n >= 70) $distribusi['C (70-79)']++;
    elseif  ($n >= 60) $distribusi['D (60-69)']++;
    else               $distribusi['E (<60)']++;
}

// ── Rata-rata per sekolah (untuk grafik batang) ───────────────
$rataSekolahRes = $conn->query(
    "SELECT s.nama_sekolah, ROUND(AVG(h.nilai),1) as rata, COUNT(*) as jml
     FROM hasil_ujian h
     JOIN peserta p ON h.peserta_id = p.id
     JOIN sekolah s ON p.sekolah_id = s.id
     WHERE h.nilai IS NOT NULL
     GROUP BY s.id ORDER BY rata DESC"
);
$rataSekolah = [];
if ($rataSekolahRes) while ($r = $rataSekolahRes->fetch_assoc()) $rataSekolah[] = $r;

// ── Daftar sekolah & kelas untuk filter ──────────────────────
$sekolahList = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");
$kelasList   = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE kelas IS NOT NULL ORDER BY kelas");

$pageTitle  = 'Nilai & Ranking';
$activeMenu = 'nilai';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Nilai & Ranking Peserta</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Nilai & Ranking</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/export_excel.php?<?= http_build_query($_GET) ?>"
           class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <div class="btn-group">
            <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-printer me-1"></i>Cetak / PDF
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/export_pdf.php?<?= http_build_query($_GET) ?>" target="_blank">Cetak Ranking</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/export_pdf.php?mode=ledger&<?= http_build_query($_GET) ?>" target="_blank">Cetak Ledger</a></li>
            </ul>
        </div>
    </div>
</div>

<?= renderFlash() ?>

<!-- ── STAT CARDS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-label">Total Peserta Ujian</div>
                 <div class="stat-value"><?= $totalPeserta ?></div></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-bar-chart-fill"></i></div>
            <div><div class="stat-label">Rata-rata Nilai</div>
                 <div class="stat-value"><?= $rataRata ?></div></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-trophy-fill"></i></div>
            <div><div class="stat-label">Nilai Tertinggi</div>
                 <div class="stat-value"><?= $nilaiMax ?></div></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-patch-check-fill"></i></div>
            <div><div class="stat-label">Peserta Lulus</div>
                 <div class="stat-value"><?= $lulus ?></div>
                 <div class="stat-sub">KKM ≥ <?= $kkm ?> &nbsp;·&nbsp; <?= $totalPeserta > 0 ? round($lulus/$totalPeserta*100) : 0 ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- ── GRAFIK ── -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i>Rata-rata Nilai per Sekolah
            </div>
            <div class="card-body">
                <canvas id="chartSekolah" height="120"></canvas>
                <?php if (!$rataSekolah): ?>
                <p class="text-muted text-center mt-3 small">Belum ada data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart-fill text-success me-2"></i>Distribusi Predikat
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartDistribusi" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── FILTER ── -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
            <select name="sekolah_id" class="form-select form-select-sm" style="width:180px">
                <option value="">Semua Sekolah</option>
                <?php if ($sekolahList): while ($s = $sekolahList->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSekolah == $s['id'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($s['nama_sekolah']) ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <select name="kelas" class="form-select form-select-sm" style="width:120px">
                <option value="">Semua Kelas</option>
                <?php if ($kelasList): while ($k = $kelasList->fetch_assoc()): ?>
                <option value="<?= $k['kelas'] ?>" <?= $filterKelas === $k['kelas'] ? 'selected':'' ?>>
                    Kelas <?= htmlspecialchars($k['kelas']) ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Cari nama / kode…" style="width:160px"
                   value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-sm btn-primary">Filter</button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- ── TABEL RANKING ── -->
<div class="card">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="nilaiTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 px-4 fw-bold" id="ranking-tab" data-bs-toggle="tab" data-bs-target="#ranking" type="button" role="tab">
                    <i class="bi bi-trophy-fill text-warning me-2"></i>Ranking Peserta
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 px-4 fw-bold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger" type="button" role="tab">
                    <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Ledger Nilai
                </button>
            </li>
        </ul>
    </div>
    <div class="tab-content" id="nilaiTabContent">
        <!-- TAB RANKING -->
        <div class="tab-pane fade show active" id="ranking" role="tabpanel">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover datatable mb-0" id="tabelNilai">
                        <thead>
                            <tr>
                                <th style="width:60px">Rank</th>
                                <th>Nama Peserta</th>
                                <th>Kode</th>
                                <th>Sekolah</th>
                                <th>Kelas</th>
                                <th class="text-center">Benar</th>
                                <th class="text-center">Dijawab</th>
                                <th class="text-center">Nilai</th>
                                <th class="text-center">Predikat</th>
                                <th>Durasi</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($semua):
                                  foreach ($semua as $i => $h):
                                  $rank = $i + 1;
                                  [$ph, $pt, $pb, $pw] = predikat_arr($h['nilai']); ?>
                            <tr>
                                <td class="text-center fw-bold">
                                    <?php if ($rank === 1): ?><span style="font-size:20px">🥇</span>
                                    <?php elseif ($rank === 2): ?><span style="font-size:20px">🥈</span>
                                    <?php elseif ($rank === 3): ?><span style="font-size:20px">🥉</span>
                                    <?php else: ?><span class="text-muted"><?= $rank ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($h['nama']) ?></strong></td>
                                <td><code><?= htmlspecialchars($h['kode_peserta']) ?></code></td>
                                <td><?= htmlspecialchars($h['nama_sekolah'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($h['kelas'] ?? '-') ?></td>
                                <td class="text-center text-success fw-bold"><?= $h['jml_benar'] ?></td>
                                <td class="text-center"><?= $h['jml_dijawab'] ?> / <?= $h['jml_benar'] + ($h['jml_dijawab'] - $h['jml_benar']) ?></td>
                                <td class="text-center">
                                    <span class="fw-bold fs-6" style="color:<?= $pw ?>"><?= $h['nilai'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $pb ?>"><?= $ph ?> &nbsp; <?= $pt ?></span>
                                </td>
                                <td><?= $h['durasi_menit'] !== null ? $h['durasi_menit'] . ' mnt' : '-' ?></td>
                                <td style="font-size:12px">
                                    <?= $h['waktu_selesai'] ? date('d/m/Y H:i', strtotime($h['waktu_selesai'])) : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="11" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data ujian
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB LEDGER -->
        <div class="tab-pane fade" id="ledger" role="tabpanel">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover datatable mb-0">
                        <thead>
                            <tr class="table-light">
                                <th style="width:50px">No</th>
                                <th>Nama Peserta</th>
                                <th>Sekolah</th>
                                <th class="text-center">Kelas</th>
                                <?php foreach ($mapelList as $mName): ?>
                                <th class="text-center"><?= htmlspecialchars($mName) ?></th>
                                <?php endforeach; ?>
                                <th class="text-center table-primary">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ledgerData): $no=1; foreach ($ledgerData as $pid => $p): 
                                $pNilai = $p['nilai'];
                                $sum = array_sum($pNilai);
                                $cnt = count($pNilai);
                                $avg = $cnt > 0 ? round($sum / $cnt, 1) : 0;
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nama']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($p['kode']) ?></small>
                                </td>
                                <td class="small"><?= htmlspecialchars($p['sekolah'] ?? '-') ?></td>
                                <td class="text-center"><?= htmlspecialchars($p['kelas'] ?? '-') ?></td>
                                <?php foreach ($mapelList as $mid => $mName): ?>
                                <td class="text-center fw-bold">
                                    <?php if (isset($pNilai[$mid])): ?>
                                        <span class="<?= $pNilai[$mid] < $kkm ? 'text-danger' : 'text-success' ?>">
                                            <?= $pNilai[$mid] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <td class="text-center table-primary fw-bold fs-6">
                                    <?= $avg ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="<?= 5 + count($mapelList) ?>" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data nilai
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function predikat_arr(float|int $nilai): array {
    $nilai = (int) round($nilai);
    if ($nilai >= 90) return ['A', 'Istimewa',    'success', '#0e9f6e'];
    if ($nilai >= 80) return ['B', 'Sangat Baik', 'success', '#0e9f6e'];
    if ($nilai >= 70) return ['C', 'Baik',        'info',    '#0ea5e9'];
    global $conn;
    $kkm = ($conn instanceof mysqli) ? (int)getSetting($conn, 'kkm', '60') : 60;
    if ($nilai >= $kkm) return ['D', 'Cukup', 'warning', '#f59e0b'];
    return                   ['E', 'Kurang',      'danger',  '#ef4444'];
}
?>

<script>
// ── Chart: Rata-rata nilai per sekolah ──
const labelsSekolah = <?= json_encode(array_column($rataSekolah, 'nama_sekolah')) ?>;
const dataSekolah   = <?= json_encode(array_column($rataSekolah, 'rata')) ?>;

new Chart(document.getElementById('chartSekolah'), {
    type: 'bar',
    data: {
        labels: labelsSekolah.length ? labelsSekolah : ['Belum ada data'],
        datasets: [{
            label: 'Rata-rata Nilai',
            data: dataSekolah.length ? dataSekolah : [0],
            backgroundColor: dataSekolah.map(v =>
                v >= 80 ? 'rgba(14,159,110,.85)' :
                v >= <?= $kkm ?> ? 'rgba(26,86,219,.85)'  : 'rgba(239,68,68,.85)'
            ),
            borderRadius: 8, borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, max: 100,
                 ticks: { callback: v => v + '' } },
            x: { ticks: { maxRotation: 30 } }
        }
    }
});

// ── Chart: Distribusi predikat ──
new Chart(document.getElementById('chartDistribusi'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($distribusi)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($distribusi)) ?>,
            backgroundColor: ['#0e9f6e','#22c55e','#0ea5e9','#f59e0b','#ef4444'],
            borderWidth: 2, borderColor: '#fff',
        }]
    },
    options: {
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
