<?php
// ============================================================
// admin/hasil.php  — Hasil Tes, Ranking, Filter, DataTables
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

/* ── Filter ──────────────────────────────────────────────── */
$filterSek   = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas = trim($_GET['kelas'] ?? '');
$filterKat   = (int)($_GET['kategori_id'] ?? 0);
$filterJadwal= (int)($_GET['jadwal_id'] ?? 0);
$q           = trim($_GET['q'] ?? '');

$conds = ["h.nilai IS NOT NULL"];
if ($filterSek)    $conds[] = "p.sekolah_id = $filterSek";
if ($filterKelas)  $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
if ($filterKat)    $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($filterJadwal) $conds[] = "h.jadwal_id = $filterJadwal";
if ($q)            $conds[] = "(p.nama LIKE '%".$conn->real_escape_string($q)."%' OR p.kode_peserta LIKE '%".$conn->real_escape_string($q)."%')";
$where = buildWhere($conds);

/* ── Data ─────────────────────────────────────────────────── */
// Query optimasi: pakai hasil_ujian, tidak ada subquery berganda
$sql = "
    SELECT h.ujian_id AS id, h.nilai, h.waktu_mulai, h.waktu_selesai,
           h.jml_benar AS benar, h.total_soal AS dijawab,
           h.jml_salah, h.jml_kosong,
           FLOOR(h.durasi_detik / 60) AS durasi,
           p.id AS peserta_id, p.nama, p.kelas, p.kode_peserta,
           s.nama_sekolah,
           COALESCE(k.id, 0) AS kategori_id,
           k.nama_kategori,
           jd.keterangan AS jadwal_nama,
           jd.tanggal AS jadwal_tanggal
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    ORDER BY h.nilai DESC, h.waktu_selesai ASC
";
$res  = $conn->query($sql);
$rows = []; $rank = 1;
$ledgerData = [];
$mapelList  = [];

if ($res) while ($r = $res->fetch_assoc()) { 
    $r['rank'] = $rank++; 
    $rows[] = $r; 

    $pid = $r['peserta_id'];
    $mid = $r['kategori_id'];
    if (!isset($ledgerData[$pid])) {
        $ledgerData[$pid] = [
            'nama' => $r['nama'],
            'kode' => $r['kode_peserta'],
            'sekolah' => $r['nama_sekolah'],
            'kelas' => $r['kelas'],
            'nilai' => []
        ];
    }
    $ledgerData[$pid]['nilai'][$mid] = $r['nilai'];
    if (!isset($mapelList[$mid])) $mapelList[$mid] = $r['nama_kategori'] ?? 'Tanpa Mapel';
}
asort($mapelList);

/* ── Statistik ────────────────────────────────────────────── */
$total   = count($rows);
$nilaiA  = array_column($rows, 'nilai');
$rata    = $total > 0 ? round(array_sum($nilaiA)/$total, 1) : 0;
$maks    = $total > 0 ? max($nilaiA) : 0;
$min     = $total > 0 ? min($nilaiA) : 0;
$kkm     = (int)getSetting($conn, 'kkm', '60');
$lulus   = count(array_filter($nilaiA, fn($n) => $n >= $kkm));

/* ── Filter lists ─────────────────────────────────────────── */
$sekolahList  = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");
$kelasList    = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE kelas IS NOT NULL AND kelas!='' ORDER BY kelas");
$kategoriList = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$jadwalList   = $conn->query("SELECT j.id, j.tanggal, j.keterangan, k.nama_kategori
                               FROM jadwal_ujian j
                               LEFT JOIN kategori_soal k ON k.id = j.kategori_id
                               ORDER BY j.tanggal DESC, j.id DESC");

$pageTitle  = 'Hasil Tes & Ranking';
$activeMenu = 'hasil';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-trophy-fill me-2 text-warning"></i>Hasil Tes & Ranking</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Hasil Tes</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
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

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-label">Peserta Ujian</div><div class="stat-value"><?= $total ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon green"><i class="bi bi-bar-chart-fill"></i></div>
            <div><div class="stat-label">Rata-rata</div><div class="stat-value"><?= $rata ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon orange"><i class="bi bi-trophy-fill"></i></div>
            <div><div class="stat-label">Tertinggi</div><div class="stat-value"><?= $maks ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon purple"><i class="bi bi-patch-check-fill"></i></div>
            <div>
                <div class="stat-label">Lulus (≥60)</div>
                <div class="stat-value"><?= $lulus ?></div>
                <div class="stat-sub"><?= $total > 0 ? round($lulus/$total*100) : 0 ?>% dari total</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search -->
<div class="card mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-sm-auto">
                <label class="form-label mb-0 fw-600 small">Filter:</label>
            </div>
            <div class="col-12 col-sm-auto">
                <select name="sekolah_id" class="form-select form-select-sm" style="min-width:180px"
                        onchange="this.form.submit()">
                    <option value="">Semua Sekolah</option>
                    <?php if ($sekolahList): while ($s=$sekolahList->fetch_assoc()): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSek==$s['id']?'selected':''?>>
                        <?= e($s['nama_sekolah']) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="col-12 col-sm-auto">
                <select name="kelas" class="form-select form-select-sm" style="min-width:120px"
                        onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    <?php if ($kelasList): while ($k=$kelasList->fetch_assoc()): ?>
                    <option value="<?= $k['kelas'] ?>" <?= $filterKelas===$k['kelas']?'selected':''?>>
                        Kelas <?= e($k['kelas']) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="col-12 col-sm-auto">
                <select name="kategori_id" class="form-select form-select-sm" style="min-width:160px"
                        onchange="this.form.submit()">
                    <option value="">Semua Mapel</option>
                    <?php if ($kategoriList): while ($kat=$kategoriList->fetch_assoc()): ?>
                    <option value="<?= $kat['id'] ?>" <?= $filterKat==$kat['id']?'selected':''?>>
                        <?= e($kat['nama_kategori']) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="col-12 col-sm-auto">
                <select name="jadwal_id" class="form-select form-select-sm" style="min-width:180px"
                        onchange="this.form.submit()">
                    <option value="">Semua Jadwal</option>
                    <?php if ($jadwalList): while ($jd=$jadwalList->fetch_assoc()): ?>
                    <option value="<?= $jd['id'] ?>" <?= $filterJadwal==$jd['id']?'selected':''?>>
                        <?= date('d/m/Y', strtotime($jd['tanggal'])) ?>
                        <?= $jd['nama_kategori'] ? ' — '.$jd['nama_kategori'] : '' ?>
                        <?= $jd['keterangan'] ? ' ('.$jd['keterangan'].')' : '' ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="col-12 col-sm-auto flex-grow-1">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Cari nama peserta atau kode…"
                           value="<?= e($q) ?>">
                    <button class="btn btn-primary btn-sm">Cari</button>
                </div>
            </div>
            <?php if ($filterSek || $filterKelas || $filterKat || $filterJadwal || $q): ?>
            <div class="col-auto">
                <a href="?" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Reset
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Filter active badge -->
<?php if ($filterSek || $filterKelas || $q): ?>
<div class="d-flex gap-2 flex-wrap mb-3">
    <span class="text-muted small">Menampilkan:</span>
    <?php if ($filterSek): ?>
    <span class="badge bg-primary-subtle text-primary border">
        Sekolah: <?= e(array_column(iterator_to_array((function() use($conn,$filterSek){
            $r=$conn->query("SELECT nama_sekolah FROM sekolah WHERE id=$filterSek LIMIT 1");
            return $r?$r:new ArrayIterator([]);
        })()), 'nama_sekolah')[0] ?? '') ?>
    </span>
    <?php endif; ?>
    <?php if ($filterKelas): ?>
    <span class="badge bg-info-subtle text-info border">Kelas: <?= e($filterKelas) ?></span>
    <?php endif; ?>
    <?php if ($q): ?>
    <span class="badge bg-secondary-subtle text-secondary border">Kata kunci: "<?= e($q) ?>"</span>
    <?php endif; ?>
    <span class="badge bg-dark-subtle text-dark border"><?= $total ?> hasil ditemukan</span>
</div>
<?php endif; ?>

<!-- Tabel Ranking + DataTables -->
<div class="card">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="hasilTab" role="tablist">
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
    <div class="tab-content" id="hasilTabContent">
        <!-- TAB RANKING -->
        <div class="tab-pane fade show active" id="ranking" role="tabpanel">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tabelHasil">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:60px">Rank</th>
                                <th>Nama Peserta</th>
                                <th>Kode</th>
                                <th>Sekolah</th>
                                <th class="text-center">Kelas</th>
                                <th>Mapel</th>
                                <th class="text-center">Benar</th>
                                <th class="text-center">Nilai</th>
                                <th class="text-center">Predikat</th>
                                <th class="text-center">Durasi</th>
                                <th>Tgl Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows):
                            foreach ($rows as $h):
                                $rank = $h['rank'];
                                [$ph,$pt,$pb,$pw] = getPredikat((int)$h['nilai']);
                        ?>
                        <tr>
                            <td class="text-center fw-800">
                                <?= match((int)$rank){
                                    1 => '<span style="font-size:20px">🥇</span>',
                                    2 => '<span style="font-size:20px">🥈</span>',
                                    3 => '<span style="font-size:20px">🥉</span>',
                                    default => "<span class='text-muted'>$rank</span>",
                                } ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar" style="width:28px;height:28px;font-size:11px;flex-shrink:0">
                                        <?= strtoupper(substr($h['nama'],0,1)) ?>
                                    </div>
                                    <strong><?= e($h['nama']) ?></strong>
                                </div>
                            </td>
                            <td><code class="text-primary"><?= e($h['kode_peserta']) ?></code></td>
                            <td class="text-sm"><?= e($h['nama_sekolah'] ?? '-') ?></td>
                            <td class="text-center"><?= e($h['kelas'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($h['nama_kategori'])): ?>
                                <span class="badge bg-info text-dark"><?= e($h['nama_kategori']) ?></span>
                                <?php else: ?>
                                <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-success fw-700"><?= (int)$h['benar'] ?>/<?= (int)$h['dijawab'] ?></td>
                            <td class="text-center">
                                <strong style="font-size:17px;color:<?= $pw ?>"><?= $h['nilai'] ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $pb ?>"><?= $ph ?> <?= $pt ?></span>
                            </td>
                            <td class="text-center text-muted text-sm">
                                <?= $h['durasi'] !== null ? $h['durasi'].' mnt' : '-' ?>
                            </td>
                            <td class="text-sm text-muted">
                                <?= $h['waktu_selesai'] ? date('d/m/Y H:i', strtotime($h['waktu_selesai'])) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Belum ada data ujian
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
                    <table class="table table-hover mb-0 datatable">
                        <thead>
                            <tr class="table-light">
                                <th style="width:50px">No</th>
                                <th>Nama Peserta</th>
                                <th>Sekolah</th>
                                <th class="text-center">Kelas</th>
                                <?php foreach ($mapelList as $mName): ?>
                                <th class="text-center"><?= e($mName) ?></th>
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
                                    <strong><?= e($p['nama']) ?></strong><br>
                                    <small class="text-muted"><?= e($p['kode']) ?></small>
                                </td>
                                <td class="small"><?= e($p['sekolah'] ?? '-') ?></td>
                                <td class="text-center"><?= e($p['kelas'] ?? '-') ?></td>
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

<script>
$(document).ready(function() {
    $('#tabelHasil').DataTable({
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
            paginate: { previous: "«", next: "»" },
            zeroRecords: "Tidak ada data yang cocok",
            emptyTable: "Belum ada data"
        },
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0] }, // Rank column tidak di-sort ulang
        ],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
