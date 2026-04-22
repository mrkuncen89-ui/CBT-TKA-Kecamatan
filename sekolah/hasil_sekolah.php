<?php
// ============================================================
// sekolah/hasil.php — Hasil Ujian Peserta Sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

// ── Filter ────────────────────────────────────────────────────
$filterKelas = trim($_GET['kelas'] ?? '');
$filterKat   = (int)($_GET['kategori_id'] ?? 0);
$q           = trim($_GET['q'] ?? '');

// ── Data sekolah ──────────────────────────────────────────────
$stSek = $conn->prepare("SELECT id, nama_sekolah, npsn, jenjang, alamat, telepon FROM sekolah WHERE id = ? LIMIT 1");
$stSek->bind_param('i', $sekolahId); $stSek->execute();
$sekolah = $stSek->get_result()->fetch_assoc(); $stSek->close();

// ── Query hasil ───────────────────────────────────────────────
$conds = ["p.sekolah_id = $sekolahId", "h.nilai IS NOT NULL"];
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($filterKat)   $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
if ($q)           $conds[] = "(p.nama LIKE '%" . $conn->real_escape_string($q) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($q) . "%')";
$where = buildWhere($conds);

$hasilRes = $conn->query("
    SELECT h.nilai, h.waktu_mulai, h.waktu_selesai,
           h.jml_benar, h.jml_salah, h.jml_kosong, h.total_soal, h.durasi_detik,
           p.nama, p.kode_peserta, p.kelas,
           COALESCE(k.nama_kategori, '-') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    ORDER BY h.nilai DESC, h.waktu_selesai ASC
");

$rows = []; $rank = 1;
if ($hasilRes) while ($r = $hasilRes->fetch_assoc()) { $r['rank'] = $rank++; $rows[] = $r; }

// ── Statistik ─────────────────────────────────────────────────
$nilaiArr = array_column($rows, 'nilai');
$total    = count($rows);
$rata     = $total > 0 ? round(array_sum($nilaiArr) / $total, 1) : 0;
$maks     = $total > 0 ? max($nilaiArr) : 0;
$min      = $total > 0 ? min($nilaiArr) : 0;
$kkm      = (int)getSetting($conn, 'kkm', '60');
$lulus    = count(array_filter($nilaiArr, fn($n) => $n >= $kkm));

// ── Daftar kelas ──────────────────────────────────────────────
$kelasRes  = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE sekolah_id=$sekolahId AND kelas IS NOT NULL ORDER BY kelas");
$kelasList = [];
if ($kelasRes) while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];

$katRes    = $conn->query("SELECT DISTINCT k.id, k.nama_kategori FROM hasil_ujian h JOIN peserta p ON p.id=h.peserta_id LEFT JOIN jadwal_ujian jd ON jd.id=h.jadwal_id LEFT JOIN kategori_soal k ON k.id=jd.kategori_id WHERE p.sekolah_id=$sekolahId AND k.id IS NOT NULL ORDER BY k.nama_kategori");
$katList   = [];
if ($katRes) while ($kat=$katRes->fetch_assoc()) $katList[] = $kat;

// ── Pengaturan ────────────────────────────────────────────────
$namaAplikasi   = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan  = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$lgF            = getSetting($conn, 'logo_file_path', '');
$lgU            = getSetting($conn, 'logo_url', '');
$logoAktif      = $lgF ? BASE_URL.'/'.$lgF : $lgU;
$exportParams   = http_build_query(array_filter(['kelas'=>$filterKelas,'kategori_id'=>$filterKat,'q'=>$q]));

$pageTitle  = 'Hasil Ujian';
$activeMenu = 'hasil';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Layar: sembunyikan elemen print ── */
.print-area { display: none; }

/* ── Print: hanya tampilkan print-area, sembunyikan semua lainnya ── */
@media print {
    /* Sembunyikan SEMUA elemen halaman */
    body > *,
    .main-wrapper,
    .sidebar,
    .content-wrapper,
    .content-area,
    .topbar { display: none !important; }

    /* Tampilkan HANYA print-area */
    .print-area {
        display: block !important;
        position: fixed;
        top: 0; left: 0;
        width: 100%;
        background: #fff;
        font-family: Arial, sans-serif;
        font-size: 10.5px;
        color: #1e293b;
    }

    /* Reset semua margin/padding browser */
    @page {
        margin: 12mm 14mm 12mm 14mm;
        size: A4 portrait;
    }

    .print-area * { box-sizing: border-box; }

    /* Header cetak */
    .ph-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 3px solid #1a56db;
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    .ph-logo { width: 52px; height: 52px; flex-shrink: 0; object-fit: contain; }
    .ph-logo-fallback {
        width: 52px; height: 52px; flex-shrink: 0;
        background: linear-gradient(135deg,#1a56db,#7c3aed);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; color: #fff;
    }
    .ph-text h1  { font-size: 13px; font-weight: 800; color: #1a56db; margin: 0 0 2px; }
    .ph-text p   { font-size: 10px; color: #374151; margin: 1px 0 0; }
    .ph-text small { font-size: 9.5px; color: #6b7280; }

    /* Grid statistik */
    .ps-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 6px;
        margin-bottom: 12px;
    }
    .ps-box {
        text-align: center;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px 4px;
    }
    .ps-val { font-size: 16px; font-weight: 800; }
    .ps-lbl { font-size: 8.5px; color: #6b7280; margin-top: 1px; }

    /* Tabel */
    .pt-wrap { width: 100%; }
    .pt-wrap table { width: 100%; border-collapse: collapse; }
    .pt-wrap th {
        background: #1a56db;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        padding: 6px 5px;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: .3px;
        border: 1px solid #1547b8;
    }
    .pt-wrap th.c, .pt-wrap td.c { text-align: center; }
    .pt-wrap td {
        padding: 5px 5px;
        border-bottom: 1px solid #f1f5f9;
        border-left: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 10px;
    }
    .pt-wrap tr:nth-child(even) td { background: #f8fafc; }
    .pt-wrap tr.top3 td { background: #fefce8; }
    .pt-wrap tr.top3-1 td { background: #fef9c3; }

    .badge-pred {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 10px;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
    }
    .lulus-ok  { color: #15803d; font-size: 9px; }
    .lulus-no  { color: #dc2626; font-size: 9px; }

    /* Footer */
    .pf-wrap {
        margin-top: 16px;
        border-top: 1px solid #d1d5db;
        padding-top: 10px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        font-size: 9.5px;
        color: #6b7280;
    }
    .pf-ttd { text-align: center; }
    .pf-ttd-line {
        border-bottom: 1px solid #374151;
        width: 160px;
        margin: 48px auto 4px;
    }

    /* page break */
    tr { page-break-inside: avoid; }
    table { page-break-inside: auto; }
}
</style>

<!-- ════════════════════════════════════════════
     PRINT AREA — hanya tampil saat Ctrl+P
     Tabel BERSIH: tidak pakai DataTables sama sekali
     ════════════════════════════════════════════ -->
<div class="print-area">
    <!-- Header -->
    <div class="ph-wrap">
        <?php if ($logoAktif): ?>
            <img src="<?= e($logoAktif) ?>" alt="Logo" class="ph-logo"
                 onerror="this.outerHTML='<div class=ph-logo-fallback>🏫</div>'">
        <?php else: ?>
            <div class="ph-logo-fallback">🏫</div>
        <?php endif; ?>
        <div class="ph-text">
            <h1>REKAP NILAI UJIAN <?= e(strtoupper($namaAplikasi)) ?></h1>
            <p><strong><?= e($sekolah['nama_sekolah'] ?? '') ?></strong>
               <?php if (!empty($sekolah['npsn'])): ?> &nbsp;|&nbsp; NPSN: <?= e($sekolah['npsn']) ?><?php endif; ?></p>
            <small>
                <?php if (!empty($sekolah['alamat'])): ?><?= e($sekolah['alamat']) ?> &nbsp;·&nbsp; <?php endif; ?>
                Tahun Pelajaran: <strong><?= e($tahunPelajaran) ?></strong>
                &nbsp;·&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
                <?php if ($filterKelas): ?> &nbsp;·&nbsp; Kelas: <?= e($filterKelas) ?><?php endif; ?>
                <?php if ($q): ?> &nbsp;·&nbsp; Kata kunci: "<?= e($q) ?>"<?php endif; ?>
            </small>
        </div>
    </div>

    <!-- Statistik -->
    <div class="ps-grid">
        <?php foreach ([
            ['Total',        $total,        '#1a56db'],
            ['Rata-rata',    $rata,         '#0891b2'],
            ['Tertinggi',    $maks,         '#16a34a'],
            ['Terendah',     $min,          '#dc2626'],
            ['Lulus(≥'.$kkm.')', $lulus,   '#7c3aed'],
            ['Tdk Lulus',    $total-$lulus, '#b45309'],
        ] as [$lbl, $val, $clr]): ?>
        <div class="ps-box">
            <div class="ps-val" style="color:<?= $clr ?>"><?= $val ?></div>
            <div class="ps-lbl"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabel bersih (tanpa DataTables, tanpa pagination) -->
    <div class="pt-wrap">
        <table>
            <thead>
                <tr>
                    <th class="c" style="width:32px">No</th>
                    <th style="width:160px">Nama Peserta</th>
                    <th class="c" style="width:76px">Kode</th>
                    <th class="c" style="width:44px">Kelas</th>
                    <th class="c" style="width:40px">Benar</th>
                    <th class="c" style="width:38px">Salah</th>
                    <th class="c" style="width:44px">Kosong</th>
                    <th class="c" style="width:44px">Nilai</th>
                    <th class="c" style="width:96px">Predikat</th>
                    <th class="c" style="width:60px">Status</th>
                    <th class="c" style="width:48px">Durasi</th>
                    <th style="width:80px">Tgl Selesai</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows):
                  foreach ($rows as $r):
                      [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['nilai']);
                      $lulusRow = ((int)$r['nilai']) >= $kkm;
                      $durMenit = $r['durasi_detik'] ? (int)floor((int)$r['durasi_detik']/60) : null;
                      $rowClass = $r['rank'] === 1 ? 'top3-1' : ($r['rank'] <= 3 ? 'top3' : '');
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="c" style="font-weight:700">
                    <?= match((int)$r['rank']){ 1=>'🥇', 2=>'🥈', 3=>'🥉', default=>$r['rank'] } ?>
                </td>
                <td><?= e($r['nama']) ?></td>
                <td class="c" style="font-family:monospace;font-size:9px"><?= e($r['kode_peserta']) ?></td>
                <td class="c"><?= e($r['kelas'] ?? '-') ?></td>
                <td><?= e($r['nama_kategori'] ?? '-') ?></td>
                <td class="c" style="color:#15803d;font-weight:700"><?= (int)$r['jml_benar'] ?></td>
                <td class="c" style="color:#dc2626;font-weight:700"><?= (int)$r['jml_salah'] ?></td>
                <td class="c" style="color:#6b7280"><?= (int)$r['jml_kosong'] ?></td>
                <td class="c">
                    <strong style="font-size:13px;color:<?= $pcolor ?>"><?= $r['nilai'] ?></strong>
                </td>
                <td class="c">
                    <span class="badge-pred" style="background:<?= $pcolor ?>">
                        <?= $pred ?> – <?= $pteks ?>
                    </span>
                </td>
                <td class="c">
                    <?php if ($lulusRow): ?>
                        <span class="lulus-ok">✓ Lulus</span>
                    <?php else: ?>
                        <span class="lulus-no">✗ Tdk Lulus</span>
                    <?php endif; ?>
                </td>
                <td class="c"><?= $durMenit !== null ? $durMenit."'" : '-' ?></td>
                <td style="font-size:9px"><?= $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-' ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="12" style="text-align:center;padding:16px;color:#94a3b8">Belum ada data ujian</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div class="pf-wrap">
        <div>
            <p>* Nilai = (Benar / Total Soal) × 100 &nbsp;·&nbsp; KKM = <?= $kkm ?></p>
            <p>Tertinggi: <strong><?= $maks ?></strong> &nbsp;·&nbsp;
               Terendah: <strong><?= $min ?></strong> &nbsp;·&nbsp;
               Kelulusan: <strong><?= $total>0 ? round($lulus/$total*100,1) : 0 ?>%</strong></p>
        </div>
        <div class="pf-ttd">
            <p>Mengetahui, Kepala Sekolah</p>
            <div class="pf-ttd-line"></div>
            <p>(______________________)</p>
            <p style="margin-top:2px"><?= e($sekolah['nama_sekolah'] ?? '') ?></p>
        </div>
    </div>
</div>
<!-- /print-area -->


<!-- ════════════════════════════════════════════
     TAMPILAN LAYAR (normal — tidak ikut cetak)
     ════════════════════════════════════════════ -->

<!-- Page header -->
<div class="page-header">
    <div>
        <h2><i class="bi bi-trophy-fill text-warning me-2"></i>Hasil Ujian</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Hasil Ujian</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/sekolah/export_excel.php<?= $exportParams ? '?'.$exportParams : '' ?>"
           class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
        </a>
        <a href="<?= BASE_URL ?>/sekolah/cetak_hasil.php?<?= http_build_query(array_filter(['kategori_id'=>$filterKat,'kelas'=>$filterKelas,'q'=>$q])) ?>"
           class="btn btn-primary btn-sm" target="_blank">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </a>
    </div>
</div>

<?= renderFlash() ?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap align-items-center" method="GET">
            <input type="text" name="q" class="form-control form-control-sm" style="width:200px"
                   placeholder="Cari nama / kode…" value="<?= e($q) ?>">
            <?php if ($kelasList): ?>
            <select name="kelas" class="form-select form-select-sm" style="width:140px">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $kls): ?>
                <option value="<?= e($kls) ?>" <?= $filterKelas===$kls ? 'selected' : '' ?>><?= e($kls) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($katList): ?>
            <select name="kategori_id" class="form-select form-select-sm" style="width:160px">
                <option value="">Semua Mapel</option>
                <?php foreach ($katList as $kat): ?>
                <option value="<?= $kat['id'] ?>" <?= $filterKat==$kat['id'] ? 'selected' : '' ?>><?= e($kat['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-search me-1"></i>Filter
            </button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Stat cards layar -->
<?php if ($total > 0): ?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Ujian',        $total,                'blue',   'bi-people-fill'],
        ['Nilai Tertinggi',    number_format($maks,1),'green',  'bi-arrow-up-circle-fill'],
        ['Nilai Terendah',     number_format($min,1), 'orange', 'bi-arrow-down-circle-fill'],
        ['Rata-rata',          number_format($rata,1),'purple', 'bi-calculator-fill'],
        ['Lulus (≥'.$kkm.')',  $lulus,                'green',  'bi-patch-check-fill'],
        ['Tidak Lulus',        $total-$lulus,         'red',    'bi-x-circle-fill'],
    ] as [$lbl,$val,$color,$icon]): ?>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon <?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
            <div><div class="stat-label"><?= $lbl ?></div><div class="stat-value"><?= $val ?></div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tabel layar (DataTables aktif) -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ol me-2 text-warning"></i>Peringkat Nilai Peserta</span>
        <small class="text-muted"><?= $total ?> peserta</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblHasilSekolah" class="table table-hover datatable mb-0">
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px">No</th>
                        <th>Nama Peserta</th>
                        <th>Kode</th>
                        <th class="text-center">Kelas</th>
                        <th>Mapel</th>
                        <th class="text-center">Benar</th>
                        <th class="text-center">Salah</th>
                        <th class="text-center">Kosong</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-center">Predikat</th>
                        <th class="text-center">Durasi</th>
                        <th>Selesai</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows):
                      foreach ($rows as $r):
                          [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['nilai']);
                          $lulusRow = ((int)$r['nilai']) >= $kkm;
                          $durMenit = $r['durasi_detik'] ? (int)floor((int)$r['durasi_detik']/60) : null;
                ?>
                <tr>
                    <td class="text-center fw-bold">
                        <?= match((int)$r['rank']){ 1=>'🥇', 2=>'🥈', 3=>'🥉', default=>$r['rank'] } ?>
                    </td>
                    <td><strong><?= e($r['nama']) ?></strong></td>
                    <td><code style="font-size:11px"><?= e($r['kode_peserta']) ?></code></td>
                    <td class="text-center"><?= e($r['kelas'] ?? '-') ?></td>
                    <td><span class="badge bg-info text-dark" style="font-size:11px"><?= e($r['nama_kategori'] ?? '-') ?></span></td>
                    <td class="text-center text-success fw-bold"><?= (int)$r['jml_benar'] ?></td>
                    <td class="text-center text-danger fw-bold"><?= (int)$r['jml_salah'] ?></td>
                    <td class="text-center text-secondary"><?= (int)$r['jml_kosong'] ?></td>
                    <td class="text-center">
                        <strong style="font-size:15px;color:<?= $pcolor ?>"><?= $r['nilai'] ?></strong>
                    </td>
                    <td class="text-center">
                        <span class="badge" style="background:<?= $pcolor ?>;font-size:11px">
                            <?= $pred ?> – <?= $pteks ?>
                        </span>
                        <div style="font-size:10px;margin-top:2px">
                            <?php if ($lulusRow): ?>
                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> Lulus</span>
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Tdk Lulus</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center"><?= $durMenit !== null ? $durMenit.' mnt' : '-' ?></td>
                    <td><small><?= $r['waktu_selesai'] ? date('d/m/Y H:i', strtotime($r['waktu_selesai'])) : '-' ?></small></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="11" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data hasil ujian
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
