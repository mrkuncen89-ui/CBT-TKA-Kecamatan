<?php
// ============================================================
// admin/rekap_kelas.php — Rekap Nilai per Kelas
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterKat    = (int)($_GET['kategori_id'] ?? 0);
$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$filterSek    = (int)($_GET['sekolah_id'] ?? 0);
$kkm          = (int)getSetting($conn, 'kkm', '60');

$conds = ["h.nilai IS NOT NULL"];
if ($filterKat)    $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($filterJadwal) $conds[] = "h.jadwal_id = $filterJadwal";
if ($filterSek)    $conds[] = "p.sekolah_id = $filterSek";
$where = buildWhere($conds);

// Rekap per kelas
$sqlRekap = "
    SELECT
        p.kelas,
        COUNT(h.id)                          AS jml_peserta,
        ROUND(AVG(h.nilai), 1)               AS rata_nilai,
        MAX(h.nilai)                         AS nilai_max,
        MIN(h.nilai)                         AS nilai_min,
        SUM(CASE WHEN h.nilai >= $kkm THEN 1 ELSE 0 END) AS jml_lulus,
        SUM(CASE WHEN h.nilai < $kkm  THEN 1 ELSE 0 END) AS jml_tidak_lulus
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    $where
    GROUP BY p.kelas
    ORDER BY p.kelas
";
$rekapRes = $conn->query($sqlRekap);
$rekap = [];
if ($rekapRes) while ($r = $rekapRes->fetch_assoc()) $rekap[] = $r;

$totalPeserta = array_sum(array_column($rekap, 'jml_peserta'));
$totalLulus   = array_sum(array_column($rekap, 'jml_lulus'));
$rataGlobal   = $totalPeserta > 0
    ? round(array_sum(array_map(fn($r) => $r['rata_nilai'] * $r['jml_peserta'], $rekap)) / $totalPeserta, 1)
    : 0;

// Filter lists
$katList    = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$jadwalList = $conn->query("SELECT j.id, j.tanggal, j.keterangan, k.nama_kategori FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id=j.kategori_id ORDER BY j.tanggal DESC");
$sekolahList= $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");

$namaAplikasi  = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$tahunPelajaran= getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));

$pageTitle  = 'Rekap Per Kelas';
$activeMenu = 'rekap_kelas';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h2><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Rekap Nilai per Kelas</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Rekap Per Kelas</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/export_excel.php?mode=rekap_kelas&<?= http_build_query(array_filter(['kategori_id'=>$filterKat,'jadwal_id'=>$filterJadwal,'sekolah_id'=>$filterSek])) ?>"
           class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        <a href="<?= BASE_URL ?>/admin/export_pdf.php?mode=rekap_kelas&<?= http_build_query(array_filter(['kategori_id'=>$filterKat,'jadwal_id'=>$filterJadwal,'sekolah_id'=>$filterSek])) ?>"
           class="btn btn-danger" target="_blank"><i class="bi bi-printer me-1"></i>Cetak PDF</a>
    </div>
</div>
<?= renderFlash() ?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Mata Pelajaran</label>
                <select name="kategori_id" class="form-select form-select-sm">
                    <option value="">Semua Mapel</option>
                    <?php if($katList) while($k=$katList->fetch_assoc()): ?>
                    <option value="<?=$k['id']?>" <?=$filterKat==$k['id']?'selected':''?>><?=e($k['nama_kategori'])?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Jadwal Ujian</label>
                <select name="jadwal_id" class="form-select form-select-sm">
                    <option value="">Semua Jadwal</option>
                    <?php if($jadwalList) while($jd=$jadwalList->fetch_assoc()): ?>
                    <option value="<?=$jd['id']?>" <?=$filterJadwal==$jd['id']?'selected':''?>>
                        <?=date('d/m/Y',strtotime($jd['tanggal']))?><?=$jd['keterangan']?' — '.$jd['keterangan']:''?><?=$jd['nama_kategori']?' ('.$jd['nama_kategori'].')':''?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Sekolah</label>
                <select name="sekolah_id" class="form-select form-select-sm">
                    <option value="">Semua Sekolah</option>
                    <?php if($sekolahList) while($sk=$sekolahList->fetch_assoc()): ?>
                    <option value="<?=$sk['id']?>" <?=$filterSek==$sk['id']?'selected':''?>><?=e($sk['nama_sekolah'])?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
        <div><div class="stat-label">Total Peserta</div><div class="stat-value"><?=$totalPeserta?></div></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-bar-chart-fill"></i></div>
        <div><div class="stat-label">Rata-rata</div><div class="stat-value"><?=$rataGlobal?></div></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="stat-label">Total Lulus</div><div class="stat-value"><?=$totalLulus?></div></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-percent"></i></div>
        <div><div class="stat-label">% Lulus</div><div class="stat-value"><?=$totalPeserta>0?round($totalLulus/$totalPeserta*100,1):0?>%</div></div>
    </div></div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2 text-primary"></i>Ringkasan per Kelas</span>
        <span class="badge bg-primary"><?=count($rekap)?> kelas</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-primary">
                <tr>
                    <th>Kelas</th>
                    <th class="text-center">Peserta</th>
                    <th class="text-center">Rata-rata</th>
                    <th class="text-center">Tertinggi</th>
                    <th class="text-center">Terendah</th>
                    <th class="text-center">Lulus<br><small>(≥<?=$kkm?>)</small></th>
                    <th class="text-center">Tdk Lulus</th>
                    <th class="text-center">% Lulus</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rekap): foreach ($rekap as $r):
                $pct = $r['jml_peserta'] > 0 ? round($r['jml_lulus']/$r['jml_peserta']*100,1) : 0;
                [$pred] = getPredikat((int)$r['rata_nilai']);
            ?>
            <tr>
                <td><strong>Kelas <?=e($r['kelas']??'-')?></strong></td>
                <td class="text-center"><span class="badge bg-primary"><?=$r['jml_peserta']?></span></td>
                <td class="text-center">
                    <span class="fw-bold fs-6 text-<?=$r['rata_nilai']>=$kkm?'success':'danger'?>"><?=$r['rata_nilai']?></span>
                    <br><small class="badge bg-secondary"><?=$pred?></small>
                </td>
                <td class="text-center text-success fw-bold"><?=$r['nilai_max']?></td>
                <td class="text-center text-danger fw-bold"><?=$r['nilai_min']?></td>
                <td class="text-center"><span class="badge bg-success"><?=$r['jml_lulus']?></span></td>
                <td class="text-center">
                    <?php if($r['jml_tidak_lulus']>0): ?>
                    <span class="badge bg-danger"><?=$r['jml_tidak_lulus']?></span>
                    <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="d-flex align-items-center gap-1 justify-content-center">
                        <div class="progress" style="width:50px;height:8px">
                            <div class="progress-bar bg-<?=$pct>=80?'success':($pct>=60?'warning':'danger')?>"
                                 style="width:<?=$pct?>%"></div>
                        </div>
                        <small class="fw-bold"><?=$pct?>%</small>
                    </div>
                </td>
                <td class="text-center">
                    <a href="<?=BASE_URL?>/admin/nilai.php?kelas=<?=urlencode($r['kelas']??'')?><?=$filterKat?'&kategori_id='.$filterKat:''?>"
                       class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">
                        <i class="bi bi-eye"></i> Detail
                    </a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
                <i class="bi bi-inbox me-2"></i>Belum ada data
            </td></tr>
            <?php endif; ?>
            </tbody>
            <?php if($rekap): ?>
            <tfoot class="table-secondary fw-bold">
                <tr>
                    <td>Total / Rata-rata</td>
                    <td class="text-center"><?=$totalPeserta?></td>
                    <td class="text-center text-primary fs-6"><?=$rataGlobal?></td>
                    <td class="text-center text-success"><?=$rekap?max(array_column($rekap,'nilai_max')):'-'?></td>
                    <td class="text-center text-danger"><?=$rekap?min(array_column($rekap,'nilai_min')):'-'?></td>
                    <td class="text-center"><?=$totalLulus?></td>
                    <td class="text-center"><?=$totalPeserta-$totalLulus?></td>
                    <td class="text-center"><?=$totalPeserta>0?round($totalLulus/$totalPeserta*100,1):0?>%</td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if(count($rekap)>1): ?>
<div class="card mt-4">
    <div class="card-header"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Perbandingan Rata-rata per Kelas</div>
    <div class="card-body">
        <canvas id="chartKelas" height="<?=min(60,count($rekap)*10)?>"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartKelas'), {
    type: 'bar',
    data: {
        labels: <?=json_encode(array_map(fn($r)=>'Kelas '.($r['kelas']??'-'),$rekap))?>,
        datasets: [{
            label: 'Rata-rata Nilai',
            data: <?=json_encode(array_column($rekap,'rata_nilai'))?>,
            backgroundColor: <?=json_encode(array_map(fn($r)=>$r['rata_nilai']>=$kkm?'rgba(22,163,74,.7)':'rgba(220,38,38,.7)',$rekap))?>,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
