<?php
// ============================================================
// admin/analisis_soal.php — Analisis Butir Soal
// Soal mana yang paling banyak salah, tingkat kesulitan, dll.
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterKat    = (int)($_GET['kategori_id'] ?? 0);
$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$filterTipe   = trim($_GET['tipe_soal'] ?? '');
$sortBy       = trim($_GET['sort'] ?? 'pct_salah'); // pct_salah | pct_benar | jml_jawab

// ── Filter kondisi jawaban ─────────────────────────────────────
$joinJadwal = '';
$whereJawab = '1=1';
if ($filterJadwal) {
    $joinJadwal  = "JOIN ujian u ON u.id = j.ujian_id AND u.jadwal_id = $filterJadwal";
    $whereJawab .= " AND u.jadwal_id = $filterJadwal";
}

$whereSoal = '1=1';
if ($filterKat)  $whereSoal .= " AND s.kategori_id = $filterKat";
if ($filterTipe) $whereSoal .= " AND s.tipe_soal = '".$conn->real_escape_string($filterTipe)."'";

$orderBy = match($sortBy) {
    'pct_benar' => 'pct_benar ASC',
    'jml_jawab' => 'jml_jawab DESC',
    default      => 'pct_salah DESC',
};

// ── Query analisis per soal ────────────────────────────────────
$sql = "
    SELECT
        s.id,
        s.pertanyaan,
        s.tipe_soal,
        s.jawaban_benar,
        k.nama_kategori,
        COUNT(j.id)                                          AS jml_jawab,
        GROUP_CONCAT(j.jawaban ORDER BY j.jawaban SEPARATOR '|') AS semua_jawaban_raw
    FROM soal s
    JOIN kategori_soal k ON k.id = s.kategori_id
    LEFT JOIN jawaban j ON j.soal_id = s.id $joinJadwal
    WHERE $whereSoal
    GROUP BY s.id, s.pertanyaan, s.tipe_soal, s.jawaban_benar, k.nama_kategori
    HAVING jml_jawab > 0
    LIMIT 200
";
$res  = $conn->query($sql);
$soalList = [];
if ($res) while ($r = $res->fetch_assoc()) {
    $jmlJawab = (int)$r['jml_jawab'];
    // Hitung benar/salah di PHP agar MCMA "a,b" == "b,a"
    $benarCount = 0;
    $semua_jawaban = '';
    if ($jmlJawab > 0 && $r['semua_jawaban_raw']) {
        $kunci = strtolower($r['jawaban_benar'] ?? '');
        $kunciArr = array_unique(array_map('trim', explode(',', $kunci)));
        sort($kunciArr);
        $jawabanArr = explode('|', $r['semua_jawaban_raw']);
        $freqMap = [];
        foreach ($jawabanArr as $jwb) {
            $jwb = trim($jwb);
            if (!$jwb) continue;
            $freqMap[$jwb] = ($freqMap[$jwb] ?? 0) + 1;
            if ($r['tipe_soal'] === 'mcma') {
                $siswaArr = array_unique(array_map('trim', explode(',', strtolower($jwb))));
                sort($siswaArr);
                if ($siswaArr === $kunciArr) $benarCount++;
            } else {
                if (strtolower($jwb) === $kunci) $benarCount++;
            }
        }
        // Rebuild semua_jawaban sebagai comma-joined untuk display
        $parts = [];
        foreach ($freqMap as $v => $c) $parts[] = $v . ($c > 1 ? "($c)" : '');
        $semua_jawaban = implode(',', $parts);
    }
    $r['jml_benar_raw'] = $benarCount;
    $r['pct_benar'] = $jmlJawab > 0 ? round($benarCount / $jmlJawab * 100, 1) : 0;
    $r['pct_salah'] = $jmlJawab > 0 ? round(($jmlJawab - $benarCount) / $jmlJawab * 100, 1) : 0;
    $r['semua_jawaban'] = $semua_jawaban;
    $soalList[] = $r;
}

// Re-sort setelah hitung ulang di PHP (karena ORDER BY di SQL memakai kolom alias yang kini berbeda)
usort($soalList, function($a, $b) use ($sortBy) {
    return match($sortBy) {
        'pct_benar' => $a['pct_benar'] <=> $b['pct_benar'],
        'jml_jawab' => $b['jml_jawab'] <=> $a['jml_jawab'],
        default      => $b['pct_salah'] <=> $a['pct_salah'],
    };
});

// ── Hitung distribusi jawaban per soal (untuk chart pilihan) ──
// Hanya untuk 10 soal teratas (paling sering salah)
$distribusiData = [];
foreach (array_slice($soalList, 0, 10) as $s) {
    $sid = (int)$s['id'];
    $distRes = $conn->query(
        "SELECT j.jawaban, COUNT(*) AS jml
         FROM jawaban j
         WHERE j.soal_id = $sid " . ($filterJadwal ? "AND j.ujian_id IN (SELECT id FROM ujian WHERE jadwal_id=$filterJadwal)" : "") . "
         GROUP BY j.jawaban ORDER BY jml DESC"
    );
    $dist = [];
    if ($distRes) while ($d = $distRes->fetch_assoc()) $dist[] = $d;
    $distribusiData[$sid] = $dist;
}

// ── Statistik global ───────────────────────────────────────────
$totalSoalAnalisis = count($soalList);
$pctBenarArr = array_column($soalList, 'pct_benar');
$rataBenar   = $totalSoalAnalisis > 0 ? round(array_sum($pctBenarArr) / $totalSoalAnalisis, 1) : 0;
$soalSulit   = count(array_filter($pctBenarArr, fn($p) => $p < 40));
$soalMudah   = count(array_filter($pctBenarArr, fn($p) => $p >= 80));

// ── Filter lists ───────────────────────────────────────────────
$katList    = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$jadwalList = $conn->query("SELECT j.id, j.tanggal, j.keterangan, k.nama_kategori FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id=j.kategori_id ORDER BY j.tanggal DESC");

$pageTitle  = 'Analisis Butir Soal';
$activeMenu = 'analisis_soal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-graph-up me-2 text-primary"></i>Analisis Butir Soal</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Analisis Butir Soal</li>
        </ol></nav>
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
                    <?php if ($katList) while ($k=$katList->fetch_assoc()): ?>
                    <option value="<?=$k['id']?>" <?=$filterKat==$k['id']?'selected':''?>>
                        <?= e($k['nama_kategori']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Jadwal Ujian</label>
                <select name="jadwal_id" class="form-select form-select-sm">
                    <option value="">Semua Jadwal</option>
                    <?php if ($jadwalList) while ($jd=$jadwalList->fetch_assoc()): ?>
                    <option value="<?=$jd['id']?>" <?=$filterJadwal==$jd['id']?'selected':''?>>
                        <?= date('d/m/Y', strtotime($jd['tanggal'])) ?>
                        <?= $jd['keterangan'] ? ' — '.$jd['keterangan'] : '' ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold mb-1">Tipe Soal</label>
                <select name="tipe_soal" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <option value="pg"   <?=$filterTipe==='pg'  ?'selected':''?>>PG</option>
                    <option value="bs"   <?=$filterTipe==='bs'  ?'selected':''?>>BS</option>
                    <option value="mcma" <?=$filterTipe==='mcma'?'selected':''?>>MCMA</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold mb-1">Urutkan</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="pct_salah" <?=$sortBy==='pct_salah'?'selected':''?>>% Salah Terbanyak</option>
                    <option value="pct_benar" <?=$sortBy==='pct_benar'?'selected':''?>>% Benar Terendah</option>
                    <option value="jml_jawab" <?=$sortBy==='jml_jawab'?'selected':''?>>Paling Banyak Dijawab</option>
                </select>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel me-1"></i>Analisis
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-question-circle-fill"></i></div>
            <div><div class="stat-label">Soal Dianalisis</div><div class="stat-value"><?= $totalSoalAnalisis ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-bar-chart-fill"></i></div>
            <div><div class="stat-label">Rata-rata % Benar</div><div class="stat-value"><?= $rataBenar ?>%</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-emoji-frown-fill"></i></div>
            <div><div class="stat-label">Soal Sulit (<40%)</div><div class="stat-value"><?= $soalSulit ?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-emoji-smile-fill"></i></div>
            <div><div class="stat-label">Soal Mudah (≥80%)</div><div class="stat-value"><?= $soalMudah ?></div></div>
        </div>
    </div>
</div>

<!-- Tabel Analisis -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2 text-primary"></i>Tingkat Kesulitan per Soal</span>
        <small class="text-muted">Hijau = mudah · Kuning = sedang · Merah = sulit</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-primary">
                <tr>
                    <th class="text-center" style="width:40px">#</th>
                    <th>Pertanyaan</th>
                    <th class="text-center">Mapel</th>
                    <th class="text-center">Tipe</th>
                    <th class="text-center">Dijawab</th>
                    <th class="text-center">% Benar</th>
                    <th class="text-center">% Salah</th>
                    <th style="min-width:150px">Tingkat Kesulitan</th>
                    <th class="text-center">Distribusi Jawaban</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($soalList): $no=1; foreach ($soalList as $s):
                $pctB = (float)$s['pct_benar'];
                $diff = $pctB >= 80 ? 'Mudah' : ($pctB >= 40 ? 'Sedang' : 'Sulit');
                $diffColor = $pctB >= 80 ? 'success' : ($pctB >= 40 ? 'warning' : 'danger');
                $singkat = mb_substr(strip_tags($s['pertanyaan']), 0, 80) . (mb_strlen($s['pertanyaan']) > 80 ? '...' : '');
                $tipeLabel = match($s['tipe_soal']) {
                    'bs'   => '<span class="badge bg-warning text-dark">BS</span>',
                    'mcma' => '<span class="badge" style="background:#7c3aed;color:#fff">MCMA</span>',
                    default=> '<span class="badge bg-primary">PG</span>',
                };
                $dist = $distribusiData[$s['id']] ?? [];
            ?>
            <tr>
                <td class="text-center text-muted small"><?= $no++ ?></td>
                <td style="max-width:280px">
                    <span style="font-size:12px"><?= e($singkat) ?></span>
                    <br><small class="text-muted">Kunci: <strong class="text-success"><?= e(strtoupper($s['jawaban_benar'])) ?></strong></small>
                </td>
                <td class="text-center" style="font-size:11px"><?= e($s['nama_kategori']) ?></td>
                <td class="text-center"><?= $tipeLabel ?></td>
                <td class="text-center fw-bold"><?= $s['jml_jawab'] ?></td>
                <td class="text-center">
                    <span class="fw-bold text-<?= $diffColor ?>"><?= $pctB ?>%</span>
                </td>
                <td class="text-center">
                    <span class="fw-bold text-<?= $pctB < 40 ? 'danger' : 'muted' ?>"><?= $s['pct_salah'] ?>%</span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:10px">
                            <div class="progress-bar bg-<?= $diffColor ?>"
                                 style="width:<?= $pctB ?>%"></div>
                        </div>
                        <span class="badge bg-<?= $diffColor ?>" style="font-size:10px;white-space:nowrap"><?= $diff ?></span>
                    </div>
                </td>
                <td class="text-center" style="font-size:10px">
                    <?php if ($dist): foreach ($dist as $d): ?>
                    <span class="badge <?= strtolower($d['jawaban']) === strtolower($s['jawaban_benar']) ? 'bg-success' : 'bg-secondary' ?> me-1" style="font-size:9px">
                        <?= e(strtoupper($d['jawaban'])) ?>:<?= $d['jml'] ?>
                    </span>
                    <?php endforeach; else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
                <i class="bi bi-inbox me-2"></i>Belum ada data jawaban untuk dianalisis
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
