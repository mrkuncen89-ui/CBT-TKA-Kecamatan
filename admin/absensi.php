<?php
// ============================================================
// admin/absensi.php — Laporan Absensi / Kehadiran Ujian
// Menampilkan peserta yang sudah & belum ujian per sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterSek    = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas  = trim($_GET['kelas'] ?? '');
$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? ''); // 'sudah' | 'belum' | ''

// ── Semua jadwal untuk dropdown ───────────────────────────────
$jadwalList = $conn->query(
    "SELECT j.id, j.tanggal, j.jam_mulai, j.jam_selesai, j.keterangan, k.nama_kategori
     FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id=j.kategori_id
     ORDER BY j.tanggal DESC, j.jam_mulai DESC"
);

// ── Kondisi jadwal untuk filter ujian ─────────────────────────
$jadwalCond = '';
if ($filterJadwal) {
    $jadwalCond = "AND u.jadwal_id = $filterJadwal";
} else {
    // Default: semua ujian yang pernah ada
    $jadwalCond = '';
}

// ── Query: semua peserta + status ujian ───────────────────────
$conds = ['1=1'];
if ($filterSek)   $conds[] = "p.sekolah_id = $filterSek";
if ($filterKelas) $conds[] = "p.kelas = '".$conn->real_escape_string($filterKelas)."'";
$wherePeserta = 'WHERE ' . implode(' AND ', $conds);

// Subquery cek sudah ujian
$subUjian = $filterJadwal
    ? "SELECT peserta_id FROM ujian WHERE jadwal_id=$filterJadwal AND waktu_mulai IS NOT NULL"
    : "SELECT peserta_id FROM ujian WHERE waktu_mulai IS NOT NULL";
$subSelesai = $filterJadwal
    ? "SELECT peserta_id FROM ujian WHERE jadwal_id=$filterJadwal AND waktu_selesai IS NOT NULL"
    : "SELECT peserta_id FROM hasil_ujian";

$sql = "
    SELECT p.id, p.nama, p.kelas, p.kode_peserta,
           s.nama_sekolah,
           CASE WHEN p.id IN ($subSelesai) THEN 'selesai'
                WHEN p.id IN ($subUjian)   THEN 'sedang'
                ELSE 'belum'
           END AS status_ujian,
           h.nilai, h.waktu_selesai,
           COALESCE(k.nama_kategori,'-') AS nama_mapel
    FROM peserta p
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN hasil_ujian h ON h.peserta_id = p.id " . ($filterJadwal
        ? "AND h.jadwal_id=$filterJadwal"
        : "AND h.id = (SELECT MAX(hx.id) FROM hasil_ujian hx WHERE hx.peserta_id = p.id)"
    ) . "
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $wherePeserta
    ORDER BY s.nama_sekolah, p.kelas, p.nama
";
$res  = $conn->query($sql);
$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

// Filter status
if ($filterStatus) {
    $rows = array_filter($rows, fn($r) => $r['status_ujian'] === $filterStatus);
    $rows = array_values($rows);
}

// ── Statistik ──────────────────────────────────────────────────
$total   = count($rows);
$selesai = count(array_filter($rows, fn($r) => $r['status_ujian'] === 'selesai'));
$sedang  = count(array_filter($rows, fn($r) => $r['status_ujian'] === 'sedang'));
$belum   = count(array_filter($rows, fn($r) => $r['status_ujian'] === 'belum'));
$pctHadir = $total > 0 ? round(($selesai + $sedang) / $total * 100, 1) : 0;

// ── Rekap per sekolah ──────────────────────────────────────────
$rekapSek = [];
foreach ($rows as $r) {
    $sid = $r['nama_sekolah'] ?? '-';
    if (!isset($rekapSek[$sid])) $rekapSek[$sid] = ['total'=>0,'selesai'=>0,'sedang'=>0,'belum'=>0];
    $rekapSek[$sid]['total']++;
    $rekapSek[$sid][$r['status_ujian']]++;
}

// ── Filter lists ───────────────────────────────────────────────
$sekolahList = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");
$kelasList   = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE kelas IS NOT NULL ORDER BY kelas");

$pageTitle  = 'Absensi Ujian';
$activeMenu = 'absensi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-person-check-fill me-2 text-primary"></i>Absensi Ujian</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Absensi Ujian</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/export_excel.php?mode=absensi&<?= http_build_query(array_filter(['jadwal_id'=>$filterJadwal,'sekolah_id'=>$filterSek,'kelas'=>$filterKelas,'status'=>$filterStatus])) ?>"
           class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <a href="<?= BASE_URL ?>/admin/export_pdf.php?mode=absensi&<?= http_build_query(array_filter(['jadwal_id'=>$filterJadwal,'sekolah_id'=>$filterSek,'kelas'=>$filterKelas,'status'=>$filterStatus])) ?>"
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
            <div class="col-sm-3">
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
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Sekolah</label>
                <select name="sekolah_id" class="form-select form-select-sm">
                    <option value="">Semua Sekolah</option>
                    <?php if ($sekolahList) while ($sk=$sekolahList->fetch_assoc()): ?>
                    <option value="<?=$sk['id']?>" <?=$filterSek==$sk['id']?'selected':''?>>
                        <?= e($sk['nama_sekolah']) ?>
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
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="selesai" <?=$filterStatus==='selesai'?'selected':''?>>✅ Sudah Selesai</option>
                    <option value="sedang"  <?=$filterStatus==='sedang' ?'selected':''?>>⏳ Sedang Ujian</option>
                    <option value="belum"   <?=$filterStatus==='belum'  ?'selected':''?>>❌ Belum Ujian</option>
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
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-label">Total Peserta</div><div class="stat-value"><?= $total ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="stat-label">Sudah Selesai</div><div class="stat-value"><?= $selesai ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="stat-label">Sedang Ujian</div><div class="stat-value"><?= $sedang ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-person-x-fill"></i></div>
            <div><div class="stat-label">Belum Ujian</div><div class="stat-value"><?= $belum ?></div></div>
        </div>
    </div>
</div>

<!-- Rekap per Sekolah -->
<?php if ($rekapSek): ?>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-building me-2"></i>Rekap per Sekolah</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Sekolah</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">Selesai</th>
                    <th class="text-center">Sedang</th>
                    <th class="text-center">Belum</th>
                    <th>Kehadiran</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rekapSek as $sNama => $rk):
                $pct = $rk['total'] > 0 ? round(($rk['selesai']+$rk['sedang'])/$rk['total']*100) : 0;
            ?>
            <tr>
                <td class="fw-semibold"><?= e($sNama) ?></td>
                <td class="text-center"><?= $rk['total'] ?></td>
                <td class="text-center"><span class="badge bg-success"><?= $rk['selesai'] ?></span></td>
                <td class="text-center"><span class="badge bg-warning text-dark"><?= $rk['sedang'] ?></span></td>
                <td class="text-center"><span class="badge bg-danger"><?= $rk['belum'] ?></span></td>
                <td style="min-width:150px">
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:8px">
                            <div class="progress-bar bg-<?= $pct>=80?'success':($pct>=50?'warning':'danger') ?>"
                                 style="width:<?= $pct ?>%"></div>
                        </div>
                        <small class="fw-bold"><?= $pct ?>%</small>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Tabel Detail -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-check me-2"></i>Detail Peserta</span>
        <span class="badge bg-primary"><?= $total ?> peserta</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm" id="tblAbsensi">
            <thead class="table-primary">
                <tr>
                    <th class="text-center" style="width:40px">#</th>
                    <th>Nama Peserta</th>
                    <th class="text-center">Kode</th>
                    <th>Sekolah</th>
                    <th class="text-center">Kelas</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Nilai</th>
                    <th>Mapel</th>
                    <th>Selesai Pukul</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): $no=1; foreach ($rows as $r): ?>
            <tr class="<?= $r['status_ujian']==='belum' ? 'table-danger' : ($r['status_ujian']==='sedang' ? 'table-warning' : '') ?>">
                <td class="text-center text-muted small"><?= $no++ ?></td>
                <td><strong><?= e($r['nama']) ?></strong></td>
                <td class="text-center"><code style="font-size:11px"><?= e($r['kode_peserta']) ?></code></td>
                <td><?= e($r['nama_sekolah']??'-') ?></td>
                <td class="text-center"><?= e($r['kelas']??'-') ?></td>
                <td class="text-center">
                    <?php if ($r['status_ujian']==='selesai'): ?>
                        <span class="badge bg-success">✅ Selesai</span>
                    <?php elseif ($r['status_ujian']==='sedang'): ?>
                        <span class="badge bg-warning text-dark">⏳ Sedang</span>
                    <?php else: ?>
                        <span class="badge bg-danger">❌ Belum</span>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold">
                    <?= $r['nilai'] !== null ? $r['nilai'] : '<span class="text-muted">—</span>' ?>
                </td>
                <td style="font-size:12px"><?= e($r['nama_mapel']??'-') ?></td>
                <td style="font-size:12px">
                    <?= $r['waktu_selesai'] ? date('H:i', strtotime($r['waktu_selesai'])) : '—' ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
                <i class="bi bi-inbox me-2"></i>Tidak ada data
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .page-header, .card:first-of-type, .topbar, .sidebar { display: none !important; }
    .main-wrapper { margin: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
