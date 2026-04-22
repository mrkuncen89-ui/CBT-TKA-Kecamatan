<?php
// ============================================================
// sekolah/cetak_hasil.php — Cetak Peringkat Nilai Peserta
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

requireLogin('sekolah');
$user      = getCurrentUser();
$sekolahId = $user['sekolah_id'];

$_qSek = $conn->query("SELECT id, nama_sekolah, npsn, jenjang, alamat, telepon FROM sekolah WHERE id=$sekolahId LIMIT 1");
$sekolah      = ($_qSek && $_qSek->num_rows > 0) ? $_qSek->fetch_assoc() : null;
$namaSekolah  = $sekolah['nama_sekolah'] ?? 'Sekolah';
$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKec      = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$tahunPel     = getSetting($conn, 'tahun_pelajaran', date('Y') . '/' . (date('Y') + 1));
$kkm          = (int)getSetting($conn, 'kkm', '60');

$katId    = (int)($_GET['kategori_id'] ?? 0);
$filterKelas = trim($_GET['kelas'] ?? '');
$q           = trim($_GET['q'] ?? '');
$mode        = $_GET['mode'] ?? 'ranking'; // ranking, ledger

$katWhere = $katId ? "AND COALESCE(h.kategori_id, jd.kategori_id) = $katId" : '';
$kelasWhere = $filterKelas ? "AND p.kelas = '".$conn->real_escape_string($filterKelas)."'" : '';
$qWhere = $q ? "AND (p.nama LIKE '%".$conn->real_escape_string($q)."%' OR p.kode_peserta LIKE '%".$conn->real_escape_string($q)."%')" : '';

$sql = "
    SELECT h.nilai, h.waktu_selesai,
           h.jml_benar, h.jml_salah, h.jml_kosong, h.total_soal,
           FLOOR(h.durasi_detik / 60) AS durasi_menit,
           p.id AS peserta_id, p.nama, p.kode_peserta, p.kelas,
           COALESCE(k.id, 0) AS kategori_id,
           COALESCE(k.nama_kategori, '-') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    WHERE p.sekolah_id = $sekolahId AND h.nilai IS NOT NULL
    $katWhere
    $kelasWhere
    $qWhere
    ORDER BY h.nilai DESC, h.waktu_selesai ASC
";
$hasil = $conn->query($sql);
$rows  = [];
$ledgerData = [];
$mapelList  = [];

if ($hasil) while ($r = $hasil->fetch_assoc()) {
    $rows[] = $r;
    
    $pid = $r['peserta_id'];
    $mid = $r['kategori_id'];
    if (!isset($ledgerData[$pid])) {
        $ledgerData[$pid] = [
            'nama' => $r['nama'],
            'kode' => $r['kode_peserta'],
            'kelas' => $r['kelas'],
            'nilai' => []
        ];
    }
    $ledgerData[$pid]['nilai'][$mid] = $r['nilai'];
    if (!isset($mapelList[$mid])) $mapelList[$mid] = $r['nama_kategori'];
}
asort($mapelList);

$total    = count($rows);
$nilais   = array_column($rows, 'nilai');
$rata     = $total > 0 ? round(array_sum($nilais) / $total, 2) : 0;
$maks     = $total > 0 ? max($nilais) : 0;
$min      = $total > 0 ? min($nilais) : 0;
$lulus    = count(array_filter($nilais, fn($n) => $n >= $kkm));
$tLulus   = $total - $lulus;
$pctLulus = $total > 0 ? round($lulus / $total * 100) : 0;

$avgBenar = $total > 0 ? round(array_sum(array_column($rows, 'jml_benar')) / $total, 1) : 0;
$avgSalah = $total > 0 ? round(array_sum(array_column($rows, 'jml_salah')) / $total, 1) : 0;

$namaKat = 'Semua Kategori';
if ($katId) {
    $kr = $conn->query("SELECT nama_kategori FROM kategori_soal WHERE id=$katId LIMIT 1");
    if ($kr && $kr->num_rows > 0) $namaKat = $kr->fetch_assoc()['nama_kategori'];
}

$tanggalCetak = date('d/m/Y H:i');
$hariIni      = date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Peringkat Nilai — <?= e($namaSekolah) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:10pt;color:#000;background:#dde4ee}

/* toolbar layar */
.no-print{background:#1e3a8a;padding:9px 20px;display:flex;align-items:center;gap:10px}
.no-print span{color:#93c5fd;font-size:13px;font-weight:700;flex:1}
.no-print button{padding:7px 22px;border:none;border-radius:5px;font-size:12px;font-weight:700;cursor:pointer}
.btn-print{background:#fff;color:#1e3a8a}
.btn-back{background:transparent;color:#93c5fd;border:1px solid #93c5fd!important}
.btn-csv{background:#16a34a;color:#fff}
.btn-xlsx{background:#ca8a04;color:#fff}

/* halaman A4 landscape */
.page{background:#fff;width:277mm;min-height:185mm;margin:16px auto;padding:11mm 13mm 13mm;box-shadow:0 2px 18px rgba(0,0,0,.18)}

/* kop */
.kop{display:flex;align-items:flex-start;gap:14px;padding-bottom:8px;border-bottom:3px solid #1e3a8a;margin-bottom:3px}
.kop-teks{flex:1}
.kop-teks .app-name{font-size:8.5pt;color:#555;text-transform:uppercase;letter-spacing:.4px}
.kop-teks .sch-name{font-size:15pt;font-weight:900;color:#1e3a8a;line-height:1.15}
.kop-teks .sub{font-size:8.5pt;color:#444;margin-top:1px}
.kop-meta{text-align:right;font-size:8pt;color:#555;line-height:1.8}
.strip{height:2px;background:linear-gradient(to right,#1e3a8a,#ca8a04);margin-bottom:9px}

/* judul */
.judul{text-align:center;margin-bottom:9px}
.judul h2{font-size:12.5pt;font-weight:900;text-transform:uppercase;color:#1e3a8a;letter-spacing:.4px}
.judul p{font-size:8.5pt;color:#444;margin-top:2px}

/* stat bar */
.stat-bar{display:flex;border:1px solid #b0c4de;border-radius:3px;overflow:hidden;margin-bottom:9px;font-size:8pt}
.stat-bar .sc{flex:1;padding:5px 8px;background:#eef4fb;border-right:1px solid #b0c4de}
.stat-bar .sc:last-child{border-right:none}
.stat-bar .sc .lbl{font-weight:700;color:#1e3a8a;text-transform:uppercase;font-size:6.5pt;display:block;margin-bottom:1px}
.stat-bar .sc .val{font-weight:900;font-size:11pt;color:#111;line-height:1}
.stat-bar .sc.lulus .val{color:#15803d}
.stat-bar .sc.tlulus .val{color:#b91c1c}

/* tabel */
table{width:100%;border-collapse:collapse;font-size:8pt}
thead tr{background:#1e3a8a;color:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
thead th{padding:6px 5px;border:1px solid #163071;font-weight:700;font-size:7.5pt;text-align:center;white-space:nowrap}
thead th.tl{text-align:left}
tbody td{padding:4.5px 5px;border:1px solid #c8d8ee;vertical-align:middle}
tbody tr:nth-child(even){background:#f2f7ff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
tbody tr.gagal-row{background:#fff8f8!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
tbody tr.kkm-sep td{background:#fef3c7!important;border-top:2px dashed #f59e0b;border-bottom:1px dashed #f59e0b;font-size:7pt;color:#92400e;font-weight:700;padding:3px 6px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tc{text-align:center}.tl{text-align:left}
.fw{font-weight:700}.fn{font-weight:900}
.cn{font-family:'Courier New',monospace;font-size:7.5pt;color:#475569}
.grn{color:#15803d;font-weight:700}.red{color:#b91c1c;font-weight:700}
.blue{color:#1e3a8a;font-weight:900;font-size:9.5pt}
.muted{color:#64748b}
.pred{display:inline-block;padding:1px 6px;border-radius:10px;font-weight:700;font-size:7pt;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pA,.pB{background:#dcfce7;color:#15803d;border:1px solid #86efac}
.pC{background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd}
.pD{background:#fef9c3;color:#854d0e;border:1px solid #fde68a}
.pE{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5}
tfoot tr td{background:#1e3a8a!important;color:#fff;font-weight:700;font-size:7.5pt;padding:5px;border:1px solid #163071;-webkit-print-color-adjust:exact;print-color-adjust:exact;text-align:center}
tfoot tr td.tl{text-align:left;padding-left:6px}

/* ttd */
.ttd-area{display:flex;justify-content:flex-end;gap:50px;margin-top:18px}
.ttd-box{text-align:center;width:155px}
.ttd-box .ttd-loc{font-size:8pt;margin-bottom:2px}
.ttd-box .ttd-sp{height:46px;margin:3px 10px;border-bottom:1px solid #333}
.ttd-box .ttd-n{font-weight:700;font-size:8.5pt}
.ttd-box .ttd-j{font-size:7.5pt;color:#444}

/* note kaki */
.doc-foot{margin-top:12px;border-top:1px solid #c8d8ee;padding-top:6px;font-size:7pt;color:#666;display:flex;justify-content:space-between}

@media print{
  body{background:#fff}
  .no-print{display:none!important}
  .page{margin:0;padding:9mm 11mm 11mm;box-shadow:none;width:100%}
  @page{size:A4 landscape;margin:0}
  tbody tr{page-break-inside:avoid}
}
</style>
</head>
<body>

<?php
// Siapkan data untuk XLSX
$xlsxRows = [];
foreach ($rows as $i => $h) {
    [$pred, $predLabel] = getPredikat((int)$h['nilai']);
    $xlsxRows[] = [
        $i + 1,
        $i + 1,
        $h['nama'],
        $h['kode_peserta'],
        $h['kelas'] ?? '-',
        $h['nama_kategori'] ?? '-',
        (int)($h['jml_benar']  ?? 0),
        (int)($h['jml_salah']  ?? 0),
        (int)($h['jml_kosong'] ?? 0),
        (int)($h['total_soal'] ?? 0),
        round((float)$h['nilai'], 2),
        $pred . ' ' . $predLabel,
        $h['durasi_menit'] !== null ? $h['durasi_menit'].' mnt' : '-',
        $h['waktu_selesai'] ? date('d/m/Y H:i', strtotime($h['waktu_selesai'])) : '-',
        ((float)$h['nilai'] >= $kkm) ? 'LULUS' : 'TIDAK LULUS',
    ];
}
$xlsxStats = [$total, $maks, $min, $rata, $lulus, $tLulus, $pctLulus.'%'];
// Footer: 15 kolom — No, Rank, Nama, Kode, Kelas, Kategori, Benar, Salah, Kosong, Total, Nilai, Predikat, Durasi, Tgl, Status
$xlsxFoot  = [
    'Jumlah: '.$total.' peserta',   // No
    '',                              // Rank
    'Lulus: '.$lulus.' | Tidak Lulus: '.$tLulus.' | % Lulus: '.$pctLulus.'%', // Nama (colspan info)
    '',                              // Kode
    '',                              // Kelas
    '',                              // Kategori
    $avgBenar,                       // Benar (avg)
    $avgSalah,                       // Salah (avg)
    '-',                             // Kosong
    '-',                             // Total
    $rata,                           // Nilai (avg)
    '',                              // Predikat
    '',                              // Durasi
    '',                              // Tgl
    'Rata-rata: '.$rata,             // Status
];
?>
<!-- Data tersembunyi untuk ekspor XLSX -->
<script id="_xlsxData" type="application/json"><?= json_encode($xlsxRows) ?></script>
<div id="_xlsxMeta" style="display:none"
     data-kkm="<?= $kkm ?>"
     data-stats='<?= htmlspecialchars(json_encode($xlsxStats), ENT_QUOTES) ?>'
     data-foot='<?= htmlspecialchars(json_encode($xlsxFoot), ENT_QUOTES) ?>'></div>

<div class="no-print">
    <span>Peringkat Nilai Peserta &mdash; <?= e($namaSekolah) ?></span>
    <button class="btn-back" onclick="history.back()">&#8592; Kembali</button>
    <button class="btn-csv"  onclick="downloadCSV()">&#128190; &nbsp;Unduh CSV</button>
    <button class="btn-xlsx" onclick="downloadXLSX()">&#128202; &nbsp;Unduh Excel</button>
    <button class="btn-print" onclick="window.print()">&#128424; &nbsp;Cetak / Simpan PDF</button>
</div>

<div class="page">

  <!-- Kop -->
  <div class="kop">
    <div class="kop-teks">
      <div class="app-name"><?= e($namaAplikasi) ?> &nbsp;&middot;&nbsp; <?= e($namaKec) ?></div>
      <div class="sch-name"><?= e($namaSekolah) ?></div>
      <div class="sub">Tahun Pelajaran <?= e($tahunPel) ?></div>
    </div>
    <div class="kop-meta">
      Dicetak: <?= $tanggalCetak ?><br>
      KKM: <strong><?= $kkm ?></strong><br>
      Kategori: <?= e($namaKat) ?>
      <?php if($q): ?><br>Cari: "<?= e($q) ?>"<?php endif; ?>
    </div>
  </div>
  <div class="strip"></div>

  <!-- Judul -->
  <div class="judul">
    <h2><?= $mode === 'ledger' ? 'Ledger Nilai Peserta' : 'Peringkat Nilai Peserta' ?></h2>
    <p><?= e($namaKat) ?> &nbsp;&middot;&nbsp; <?= $hariIni ?></p>
  </div>

  <!-- Statistik -->
  <?php if ($mode !== 'ledger'): ?>
  <div class="stat-bar">
    <div class="sc"><span class="lbl">Total Peserta</span><span class="val"><?= $total ?></span></div>
    <div class="sc"><span class="lbl">Nilai Tertinggi</span><span class="val"><?= $maks ?></span></div>
    <div class="sc"><span class="lbl">Nilai Terendah</span><span class="val"><?= $min ?></span></div>
    <div class="sc"><span class="lbl">Rata-rata</span><span class="val"><?= $rata ?></span></div>
    <div class="sc lulus"><span class="lbl">Lulus (&ge;<?= $kkm ?>)</span><span class="val"><?= $lulus ?></span></div>
    <div class="sc tlulus"><span class="lbl">Tidak Lulus</span><span class="val"><?= $tLulus ?></span></div>
    <div class="sc"><span class="lbl">% Lulus</span><span class="val"><?= $pctLulus ?>%</span></div>
  </div>
  <?php endif; ?>

  <!-- Tabel -->
  <?php if ($mode === 'ledger'): ?>
  <table>
    <thead>
      <tr>
        <th style="width:30px">No</th>
        <th class="tl" style="width:180px">Nama Peserta</th>
        <th style="width:80px">Kode</th>
        <th style="width:40px">Kelas</th>
        <?php foreach ($mapelList as $mName): ?>
        <th style="width:60px"><?= e($mName) ?></th>
        <?php endforeach; ?>
        <th style="width:60px">Rata-rata</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($ledgerData)): ?>
      <tr><td colspan="<?= 5 + count($mapelList) ?>" class="tc muted py-4">Belum ada data nilai.</td></tr>
      <?php else: $no=1; foreach ($ledgerData as $pid => $p): 
          $pNilai = $p['nilai'];
          $sum = array_sum($pNilai);
          $cnt = count($pNilai);
          $avg = $cnt > 0 ? round($sum / $cnt, 2) : 0;
      ?>
      <tr>
        <td class="tc muted"><?= $no++ ?></td>
        <td class="tl fw"><?= e($p['nama']) ?></td>
        <td class="tc cn"><?= e($p['kode']) ?></td>
        <td class="tc"><?= e($p['kelas'] ?? '-') ?></td>
        <?php foreach ($mapelList as $mid => $mName): ?>
        <td class="tc fw">
            <?php if (isset($pNilai[$mid])): ?>
                <span class="<?= $pNilai[$mid] < $kkm ? 'red' : 'grn' ?>"><?= $pNilai[$mid] ?></span>
            <?php else: ?>
                <span class="muted">-</span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <td class="tc blue"><?= $avg ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:22px">No</th>
        <th style="width:30px">Rank</th>
        <th class="tl" style="width:145px">Nama Peserta</th>
        <th style="width:76px">Kode Peserta</th>
        <th style="width:40px">Kelas</th>
        <th class="tl">Kategori / Mapel</th>
        <th style="width:36px">Benar</th>
        <th style="width:36px">Salah</th>
        <th style="width:36px">Kosong</th>
        <th style="width:36px">Total</th>
        <th style="width:46px">Nilai</th>
        <th style="width:68px">Predikat</th>
        <th style="width:42px">Durasi</th>
        <th style="width:95px">Tgl Selesai</th>
      </tr>
    </thead>
    <tbody>
<?php if (empty($rows)): ?>
      <tr><td colspan="14" style="text-align:center;padding:18px;color:#888">Belum ada data hasil ujian.</td></tr>
<?php else:
  $kkmLine = false;
  foreach ($rows as $i => $h):
    $no        = $i + 1;
    $nilaiVal  = (float)$h['nilai'];
    $benar     = (int)($h['jml_benar']  ?? 0);
    $salah     = (int)($h['jml_salah']  ?? 0);
    $kosong    = (int)($h['jml_kosong'] ?? 0);
    $totalSoal = (int)($h['total_soal'] ?? 0);
    $durasi    = $h['durasi_menit'] !== null ? $h['durasi_menit'].' mnt' : '-';
    [$pred, $predLabel] = getPredikat((int)$nilaiVal);
    $isLulus   = $nilaiVal >= $kkm;
    $rowCls    = $isLulus ? '' : 'gagal-row';
    if (!$kkmLine && !$isLulus):
        $kkmLine = true;
?>
      <tr class="kkm-sep">
        <td colspan="14">&#9660; &nbsp;Di bawah KKM <?= $kkm ?> &mdash; <?= $tLulus ?> peserta tidak lulus</td>
      </tr>
<?php   endif; ?>
      <tr class="<?= $rowCls ?>">
        <td class="tc muted"><?= $no ?></td>
        <td class="tc fn" style="color:#1e3a8a"><?= $no ?></td>
        <td class="tl fw"><?= e($h['nama']) ?></td>
        <td class="tc cn"><?= e($h['kode_peserta']) ?></td>
        <td class="tc"><?= e($h['kelas'] ?? '-') ?></td>
        <td class="tl" style="color:#1d4ed8;font-size:7.5pt"><?= e($h['nama_kategori'] ?? '-') ?></td>
        <td class="tc grn"><?= $benar ?></td>
        <td class="tc red"><?= $salah ?></td>
        <td class="tc muted"><?= $kosong ?></td>
        <td class="tc muted"><?= $totalSoal ?></td>
        <td class="tc blue"><?= number_format($nilaiVal, 2) ?></td>
        <td class="tc"><span class="pred p<?= $pred ?>"><?= $pred ?> &nbsp;<?= $predLabel ?></span></td>
        <td class="tc muted" style="font-size:7.5pt"><?= $durasi ?></td>
        <td style="font-size:7.5pt;white-space:nowrap"><?= $h['waktu_selesai'] ? date('d/m/Y H:i', strtotime($h['waktu_selesai'])) : '-' ?></td>
      </tr>
<?php endforeach; endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" class="tl">Jumlah: <?= $total ?> peserta &nbsp;|&nbsp; Lulus: <?= $lulus ?> &nbsp;|&nbsp; Tidak Lulus: <?= $tLulus ?> &nbsp;|&nbsp; % Lulus: <?= $pctLulus ?>%</td>
        <td><?= $avgBenar ?></td>
        <td><?= $avgSalah ?></td>
        <td>&mdash;</td>
        <td>&mdash;</td>
        <td><?= $rata ?></td>
        <td colspan="3" class="tl">Rata-rata: <?= $rata ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <!-- Tanda tangan -->
  <div class="ttd-area">
    <div class="ttd-box">
      <div class="ttd-loc"><?= e($namaKec) ?>, <?= $hariIni ?></div>
      <div class="ttd-sp"></div>
      <div class="ttd-n">______________________</div>
      <div class="ttd-j">Mengetahui, Kepala Sekolah</div>
    </div>
    <div class="ttd-box">
      <div class="ttd-loc">&nbsp;</div>
      <div class="ttd-sp"></div>
      <div class="ttd-n">______________________</div>
      <div class="ttd-j">Dibuat oleh, Operator</div>
    </div>
  </div>

  <div class="doc-foot">
    <span>Dicetak dari sistem <?= e($namaAplikasi) ?> &mdash; <?= e($namaKec) ?></span>
    <span><?= $tanggalCetak ?></span>
  </div>

</div>

<script>
  if (new URLSearchParams(location.search).get('print') === '1') {
    window.onload = () => window.print();
  }

  // ── Helpers ──────────────────────────────────────────────
  const namaSekolah  = <?= json_encode($namaSekolah) ?>;
  const namaKat      = <?= json_encode($namaKat) ?>;
  const tanggalCetak = <?= json_encode($tanggalCetak) ?>;

  function getTableData() {
    const headers = [];
    document.querySelectorAll('table thead th').forEach(th => headers.push(th.innerText.trim()));

    const rows = [];
    document.querySelectorAll('table tbody tr').forEach(tr => {
      if (tr.classList.contains('kkm-sep')) return; // skip separator row
      const cells = [];
      tr.querySelectorAll('td').forEach(td => cells.push(td.innerText.trim()));
      if (cells.length > 1) rows.push(cells); // skip "belum ada data" row
    });

    // footer summary
    const footCells = [];
    document.querySelectorAll('table tfoot td').forEach(td => footCells.push(td.innerText.trim()));

    return { headers, rows, footCells };
  }

  // ── Download CSV ──────────────────────────────────────────
  function downloadCSV() {
    const { headers, rows, footCells } = getTableData();
    const escape = v => '"' + String(v).replace(/"/g, '""') + '"';

    const lines = [];
    lines.push(`"Laporan Peringkat Nilai Peserta"`);
    lines.push(`"Sekolah:","${namaSekolah}"`);
    lines.push(`"Kategori:","${namaKat}"`);
    lines.push(`"Dicetak:","${tanggalCetak}"`);
    lines.push('');
    lines.push(headers.map(escape).join(','));
    rows.forEach(r => lines.push(r.map(escape).join(',')));
    lines.push('');
    lines.push(footCells.map(escape).join(','));

    const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `Peringkat_${namaSekolah.replace(/\s+/g,'_')}_${tanggalCetak.replace(/[/:]/g,'-')}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ── Download XLSX (via xlsx-js-style — supports cell styling) ──
  function downloadXLSX() {
    // Cek apakah library sudah tersedia (XLSXStyle atau XLSX sebagai fallback)
    if (typeof window.XLSXStyle !== 'undefined' || typeof window.XLSX !== 'undefined') {
      _doXLSX();
      return;
    }
    // Coba muat xlsx-js-style dari beberapa CDN sebagai fallback
    const cdnList = [
      'https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js',
      'https://unpkg.com/xlsx-js-style@1.2.0/dist/xlsx.bundle.js',
    ];
    let idx = 0;
    function tryLoad() {
      if (idx >= cdnList.length) {
        // Semua CDN gagal, fallback ke SheetJS standar (tanpa styling)
        const s2 = document.createElement('script');
        s2.src = 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js';
        s2.onload = () => _doXLSX();
        s2.onerror = () => alert('Gagal memuat library Excel. Periksa koneksi internet Anda.');
        document.head.appendChild(s2);
        return;
      }
      const s = document.createElement('script');
      s.src = cdnList[idx++];
      s.onload = () => _doXLSX();
      s.onerror = () => tryLoad(); // coba CDN berikutnya
      document.head.appendChild(s);
    }
    tryLoad();
  }

  function _doXLSX() {
    // Dukung xlsx-js-style (XLSXStyle) maupun SheetJS standar (XLSX)
    const XL = window.XLSXStyle || window.XLSX;
    if (!XL) { alert('Library Excel tidak tersedia.'); return; }

    // ── Data dari PHP ──────────────────────────────────────
    const dataRows = JSON.parse(document.getElementById('_xlsxData').textContent);
    const KKM      = parseInt(document.getElementById('_xlsxMeta').dataset.kkm, 10);
    const statVals = JSON.parse(document.getElementById('_xlsxMeta').dataset.stats);

    const headers = [
      'No','Rank','Nama Peserta','Kode Peserta','Kelas','Kategori / Mapel',
      'Benar','Salah','Kosong','Total Soal','Nilai','Predikat','Durasi','Tgl Selesai','Status'
    ];
    const COLS = headers.length; // 15

    // ── Style helpers ──────────────────────────────────────
    const mkBorder = (style, rgb) => ({
      top:{style,color:{rgb}}, bottom:{style,color:{rgb}},
      left:{style,color:{rgb}}, right:{style,color:{rgb}}
    });
    const border     = mkBorder('thin',   'B0C4DE');
    const borderBold = mkBorder('medium', '163071');

    // cell helper: auto-detect number vs string
    const cell = (v, s) => {
      const isNum = typeof v === 'number' && isFinite(v);
      return { v: isNum ? v : String(v ?? ''), t: isNum ? 'n' : 's', s: s || {} };
    };

    // ── Worksheet ─────────────────────────────────────────
    const ws = {};
    const rowHeights = [];
    let R = 0;

    const setCell = (r, c, v, s) => { ws[XL.utils.encode_cell({r,c})] = cell(v, s); };

    // ── Baris 0: Judul ────────────────────────────────────
    rowHeights[R] = { hpt: 26 };
    setCell(R, 0, 'LAPORAN PERINGKAT NILAI PESERTA', {
      font:      { bold:true, sz:14, color:{rgb:'1E3A8A'}, name:'Arial' },
      alignment: { horizontal:'left', vertical:'center' },
    });
    R++;

    // ── Baris 1-4: Meta info ──────────────────────────────
    const metaLbl = { font:{bold:true,sz:10,name:'Arial'}, fill:{fgColor:{rgb:'EEF4FB'}}, alignment:{horizontal:'left'} };
    const metaVal = { font:{sz:10,name:'Arial'}, fill:{fgColor:{rgb:'EEF4FB'}}, alignment:{horizontal:'left'} };

    [
      ['Sekolah',  namaSekolah],
      ['Kategori', namaKat],
      ['KKM',      KKM],
      ['Dicetak',  tanggalCetak],
    ].forEach(([lbl, val]) => {
      setCell(R, 0, lbl, metaLbl);
      setCell(R, 1, ':', metaLbl);
      setCell(R, 2, val, metaVal);
      R++;
    });

    // ── Baris kosong ─────────────────────────────────────
    rowHeights[R] = { hpt: 8 };
    R++;

    // ── Statistik ─────────────────────────────────────────
    const statLabels = ['Total Peserta','Nilai Tertinggi','Nilai Terendah','Rata-rata','Lulus','Tidak Lulus','% Lulus'];
    const statColors = ['111111','1E3A8A','B91C1C','444444','15803D','B91C1C','1E3A8A'];
    rowHeights[R]   = { hpt: 20 };
    rowHeights[R+1] = { hpt: 26 };

    statLabels.forEach((lbl, c) => {
      setCell(R, c, lbl, {
        font:      { bold:true, sz:8, color:{rgb:'FFFFFF'}, name:'Arial' },
        fill:      { fgColor:{rgb:'1E3A8A'} },
        alignment: { horizontal:'center', vertical:'center' },
        border,
      });
      setCell(R+1, c, statVals[c], {
        font:      { bold:true, sz:12, color:{rgb: statColors[c]}, name:'Arial' },
        fill:      { fgColor:{rgb:'EEF4FB'} },
        alignment: { horizontal:'center', vertical:'center' },
        border,
      });
    });
    R += 2;

    // ── Baris kosong sebelum tabel ────────────────────────
    rowHeights[R] = { hpt: 6 };
    R++;

    // ── Header tabel ──────────────────────────────────────
    const HEADER_ROW = R;
    rowHeights[R] = { hpt: 32 };
    headers.forEach((h, c) => {
      setCell(R, c, h, {
        font:      { bold:true, sz:9, color:{rgb:'FFFFFF'}, name:'Arial' },
        fill:      { fgColor:{rgb:'1E3A8A'} },
        alignment: { horizontal:'center', vertical:'center', wrapText:true },
        border:    borderBold,
      });
    });
    R++;

    // ── Data rows ─────────────────────────────────────────
    const fillEven  = { fgColor:{rgb:'F2F7FF'} };
    const fillOdd   = { fgColor:{rgb:'FFFFFF'} };
    const fillGagal = { fgColor:{rgb:'FFF8F8'} };

    function tdStyle(isEven, isLulus, fontOvr={}, alignOvr={}) {
      return {
        font:      { sz:9, name:'Arial', ...fontOvr },
        fill:      isLulus ? (isEven ? fillEven : fillOdd) : fillGagal,
        alignment: { horizontal:'center', vertical:'center', ...alignOvr },
        border,
      };
    }

    dataRows.forEach((row, i) => {
      const isEven  = i % 2 === 1;
      const nilaiNum = typeof row[10] === 'number' ? row[10] : parseFloat(row[10]) || 0;
      const isLulus = nilaiNum >= KKM;
      const status  = row[14];

      row.forEach((val, c) => {
        let s;
        if (c === 2) {
          s = tdStyle(isEven, isLulus, {bold:true}, {horizontal:'left'});
        } else if (c === 5) {
          s = tdStyle(isEven, isLulus, {color:{rgb:'1D4ED8'}}, {horizontal:'left'});
        } else if (c === 6) {
          s = tdStyle(isEven, isLulus, {bold:true, color:{rgb:'15803D'}});
        } else if (c === 7) {
          s = tdStyle(isEven, isLulus, {bold:true, color:{rgb:'B91C1C'}});
        } else if (c === 10) {
          s = tdStyle(isEven, isLulus, {bold:true, sz:11, color:{rgb:'1E3A8A'}});
        } else if (c === 14) {
          const isL = (status === 'LULUS');
          s = {
            font:      { bold:true, sz:9, color:{rgb:'FFFFFF'}, name:'Arial' },
            fill:      { fgColor:{ rgb: isL ? '16A34A' : 'DC2626' } },
            alignment: { horizontal:'center', vertical:'center' },
            border,
          };
        } else {
          s = tdStyle(isEven, isLulus);
        }
        setCell(R, c, val, s);
      });
      R++;
    });

    // ── Footer ringkasan ──────────────────────────────────
    rowHeights[R] = { hpt: 22 };
    const footData = JSON.parse(document.getElementById('_xlsxMeta').dataset.foot);
    const footBase = {
      font:  { bold:true, sz:9, color:{rgb:'FFFFFF'}, name:'Arial' },
      fill:  { fgColor:{rgb:'1E3A8A'} },
      alignment: { horizontal:'center', vertical:'center' },
      border: borderBold,
    };
    const footLeft = { ...footBase, alignment:{ horizontal:'left', vertical:'center' } };
    footData.forEach((v, c) => {
      setCell(R, c, v ?? '', (c === 0 || c === 2 || c === 14) ? footLeft : footBase);
    });
    R++;

    // ── Merge cells ────────────────────────────────────────
    ws['!merges'] = [
      { s:{r:0, c:0}, e:{r:0, c:COLS-1} }, // judul
    ];

    // ── Freeze pane di bawah header tabel ─────────────────
    ws['!freeze'] = { xSplit: 0, ySplit: HEADER_ROW + 1 };

    // ── Lebar kolom ────────────────────────────────────────
    ws['!cols'] = [
      {wch:5},  // No
      {wch:5},  // Rank
      {wch:30}, // Nama
      {wch:16}, // Kode Peserta
      {wch:7},  // Kelas
      {wch:22}, // Kategori
      {wch:8},  // Benar
      {wch:8},  // Salah
      {wch:8},  // Kosong
      {wch:9},  // Total Soal
      {wch:9},  // Nilai
      {wch:14}, // Predikat
      {wch:9},  // Durasi
      {wch:17}, // Tgl Selesai
      {wch:13}, // Status
    ];

    // ── Tinggi semua baris ─────────────────────────────────
    ws['!rows'] = rowHeights;

    // ── Ref range ─────────────────────────────────────────
    ws['!ref'] = XL.utils.encode_range({r:0, c:0}, {r:R-1, c:COLS-1});

    // ── Simpan file ────────────────────────────────────────
    const wb = XL.utils.book_new();
    XL.utils.book_append_sheet(wb, ws, 'Peringkat Nilai');
    const fname = `Peringkat_${namaSekolah.replace(/\s+/g,'_')}_${tanggalCetak.replace(/[/:]/g,'-')}.xlsx`;
    XL.writeFile(wb, fname);
  }
</script>
</body>
</html>
