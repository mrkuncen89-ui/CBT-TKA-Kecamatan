<?php
// ============================================================
// ujian/cetak_hasil_peserta.php — Laporan PDF per Peserta
// Bisa dipanggil dari halaman selesai ujian atau cek_nilai
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

// Bisa diakses via: kode peserta, peserta_id GET, atau session
$kode     = strtoupper(trim($_GET['kode'] ?? ''));
$ujianId  = (int)($_GET['ujian_id'] ?? 0);
$jadwalId = (int)($_GET['jadwal_id'] ?? 0);
$pidGet   = (int)($_GET['peserta_id'] ?? 0);

// Helper: ambil hasil dari DB berdasarkan peserta ID
function ambilHasilDB($conn, $pid, $ujianId, $jadwalId) {
    $whereUjian  = $ujianId  ? "AND h.ujian_id=$ujianId"   : "";
    $whereJadwal = $jadwalId ? "AND h.jadwal_id=$jadwalId" : "";
    return $conn->query("
        SELECT h.*, COALESCE(k.nama_kategori,'Umum') AS nama_kategori,
               jd.tanggal AS jadwal_tanggal
        FROM hasil_ujian h
        LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
        LEFT JOIN kategori_soal k  ON k.id  = COALESCE(h.kategori_id, jd.kategori_id)
        WHERE h.peserta_id = $pid $whereUjian $whereJadwal
        ORDER BY h.id DESC LIMIT 1
    ");
}

if ($kode) {
    // --- Akses via kode peserta (dari halaman cek_nilai) ---
    $kd   = $conn->real_escape_string($kode);
    $pRow = $conn->query("SELECT p.*, s.nama_sekolah FROM peserta p
                          LEFT JOIN sekolah s ON s.id = p.sekolah_id
                          WHERE p.kode_peserta = '$kd' LIMIT 1");
    if (!$pRow || $pRow->num_rows === 0) { echo 'Kode tidak valid.'; exit; }
    $peserta = $pRow->fetch_assoc();
    $pid     = (int)$peserta['id'];

    $hRes = ambilHasilDB($conn, $pid, $ujianId, $jadwalId);
    if (!$hRes || $hRes->num_rows === 0) { echo 'Data hasil tidak ditemukan.'; exit; }
    $hasil = $hRes->fetch_assoc();

} elseif ($pidGet) {
    // --- Akses via GET peserta_id (dari halaman selesai ujian / window.open) ---
    $pRow = $conn->query("SELECT p.*, s.nama_sekolah FROM peserta p
                          LEFT JOIN sekolah s ON s.id = p.sekolah_id
                          WHERE p.id = $pidGet LIMIT 1");
    if (!$pRow || $pRow->num_rows === 0) { echo 'Peserta tidak ditemukan.'; exit; }
    $peserta = $pRow->fetch_assoc();
    $pid     = $pidGet;

    $hRes = ambilHasilDB($conn, $pid, $ujianId, $jadwalId);
    if (!$hRes || $hRes->num_rows === 0) { echo 'Data hasil tidak ditemukan.'; exit; }
    $hasil = $hRes->fetch_assoc();

} elseif (!empty($_SESSION['peserta_id'])) {
    // --- Akses via session (tab sama setelah ujian selesai) ---
    $pid  = (int)$_SESSION['peserta_id'];
    $pRow = $conn->query("SELECT p.*, s.nama_sekolah FROM peserta p
                          LEFT JOIN sekolah s ON s.id = p.sekolah_id
                          WHERE p.id = $pid LIMIT 1");
    $peserta = $pRow ? $pRow->fetch_assoc() : null;
    if (!$peserta) { echo 'Session tidak valid.'; exit; }

    // Coba ambil dari DB dulu (lebih akurat), fallback ke session vars
    $jadwalIdSess = (int)($_SESSION['jadwal_id'] ?? 0);
    $hRes = ambilHasilDB($conn, $pid, 0, $jadwalIdSess);
    if ($hRes && $hRes->num_rows > 0) {
        $hasil = $hRes->fetch_assoc();
    } else {
        // Fallback: susun dari session vars
        $totalSoal = (int)($_SESSION['hasil_total'] ?? 0);
        $jmlBenar  = (int)($_SESSION['hasil_benar'] ?? 0);
        $hasil = [
            'nilai'          => (float)($_SESSION['hasil_nilai'] ?? 0),
            'jml_benar'      => $jmlBenar,
            'total_soal'     => $totalSoal,
            'jml_salah'      => $totalSoal - $jmlBenar,
            'jml_kosong'     => 0,
            'waktu_selesai'  => date('Y-m-d H:i:s'),
            'nama_kategori'  => $_SESSION['jadwal_mapel'] ?? 'Umum',
            'jadwal_tanggal' => date('Y-m-d'),
        ];
    }
} else {
    echo 'Akses tidak valid.'; exit;
}

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan     = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$tahunPelajaran    = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$kkm               = (int)getSetting($conn, 'kkm', '60');
$logoFilePath      = getSetting($conn, 'logo_file_path', '');
$logoUrl           = getSetting($conn, 'logo_url', '');
$logoAktif         = $logoFilePath ? BASE_URL.'/'.$logoFilePath : $logoUrl;

$nilai    = (float)$hasil['nilai'];
$lulus    = $nilai >= $kkm;
[$pred, $ket, $badge] = match(true) {
    $nilai >= 90 => ['A','Istimewa','#059669'],
    $nilai >= 80 => ['B','Sangat Baik','#059669'],
    $nilai >= 70 => ['C','Baik','#0ea5e9'],
    $nilai >= $kkm => ['D','Cukup','#f59e0b'],
    default       => ['E','Perlu Bimbingan','#dc2626'],
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laporan Nilai — <?= e($peserta['nama'] ?? '') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;background:#fff;padding:20px}
.wrap{max-width:640px;margin:0 auto}
.kop{display:flex;align-items:center;gap:16px;border-bottom:3px solid #1e40af;padding-bottom:12px;margin-bottom:14px}
.kop-logo{width:60px;height:60px;object-fit:contain}
.kop-logo-placeholder{width:60px;height:60px;background:#1e40af;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:900}
.kop-teks h1{font-size:15px;font-weight:800;color:#1e40af;text-transform:uppercase;margin-bottom:2px}
.kop-teks p{font-size:10px;color:#475569;line-height:1.5}
.title-box{text-align:center;background:#1e40af;color:#fff;border-radius:8px;padding:10px;margin-bottom:14px}
.title-box h2{font-size:14px;font-weight:800;letter-spacing:.5px}
.title-box p{font-size:10px;opacity:.85;margin-top:2px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.info-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px}
.info-lbl{font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.info-val{font-size:12px;font-weight:700;color:#1e293b}
.nilai-box{text-align:center;border:2px solid <?=$badge?>;border-radius:12px;padding:16px;margin-bottom:14px;background:<?=$lulus?'#f0fdf4':'#fef2f2'?>}
.nilai-angka{font-size:56px;font-weight:900;line-height:1;color:<?=$badge?>}
.nilai-pred{font-size:13px;font-weight:700;color:<?=$badge?>;margin-top:4px}
.nilai-lulus{display:inline-block;background:<?=$lulus?'#059669':'#dc2626'?>;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:700;margin-top:6px}
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}
.stat-item{text-align:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px}
.stat-num{font-size:22px;font-weight:800}
.stat-lbl{font-size:9px;color:#94a3b8;margin-top:2px}
.footer-line{border-top:1px solid #e2e8f0;padding-top:10px;display:flex;justify-content:space-between;align-items:flex-end;margin-top:14px}
.ttd-box{text-align:center}
.ttd-line{border-bottom:1px solid #333;width:160px;height:36px;margin:0 auto 4px}
.ttd-nama{font-size:10px;color:#475569}
.footer-info{font-size:9px;color:#94a3b8}
@media print{
  body{padding:0}
  .no-print{display:none!important}
  @page{margin:.8cm}
}
</style>
</head>
<body>
<div class="wrap">

  <!-- Tombol cetak (hilang saat print) -->
  <div class="no-print" style="text-align:right;margin-bottom:12px">
    <button onclick="window.print()" style="background:#1e40af;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:12px;font-weight:700;cursor:pointer;margin-right:6px">
      🖨️ Cetak / Simpan PDF
    </button>
    <button onclick="window.close()" style="background:#e2e8f0;color:#475569;border:none;border-radius:6px;padding:8px 16px;font-size:12px;cursor:pointer">
      ✕ Tutup
    </button>
  </div>

  <!-- Kop -->
  <div class="kop">
    <?php if($logoAktif): ?>
    <img src="<?=e($logoAktif)?>" class="kop-logo" alt="Logo" onerror="this.style.display='none'">
    <?php else: ?>
    <div class="kop-logo-placeholder"><?=mb_strtoupper(mb_substr($namaAplikasi,0,1))?></div>
    <?php endif; ?>
    <div class="kop-teks">
      <h1><?=e($namaAplikasi)?></h1>
      <p><?=e($namaPenyelenggara ?: $namaKecamatan)?><br>Tahun Pelajaran <?=e($tahunPelajaran)?></p>
    </div>
  </div>

  <!-- Judul -->
  <div class="title-box">
    <h2>LAPORAN HASIL UJIAN</h2>
    <p><?=e($hasil['nama_kategori'])?> · <?=$hasil['jadwal_tanggal']?date('d F Y',strtotime($hasil['jadwal_tanggal'])):date('d F Y')?></p>
  </div>

  <!-- Info Peserta -->
  <div class="info-grid">
    <div class="info-box">
      <div class="info-lbl">Nama Peserta</div>
      <div class="info-val"><?=e($peserta['nama']??'')?></div>
    </div>
    <div class="info-box">
      <div class="info-lbl">Kode Peserta</div>
      <div class="info-val" style="font-family:monospace;letter-spacing:2px"><?=e($peserta['kode_peserta']??'')?></div>
    </div>
    <div class="info-box">
      <div class="info-lbl">Kelas</div>
      <div class="info-val">Kelas <?=e($peserta['kelas']??'-')?></div>
    </div>
    <div class="info-box">
      <div class="info-lbl">Sekolah</div>
      <div class="info-val"><?=e($peserta['nama_sekolah']??'-')?></div>
    </div>
  </div>

  <!-- Nilai -->
  <div class="nilai-box">
    <div style="font-size:10px;font-weight:700;color:<?=$badge?>;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">NILAI AKHIR</div>
    <div class="nilai-angka"><?=number_format($nilai,0)?></div>
    <div class="nilai-pred">Predikat <?=$pred?> — <?=$ket?></div>
    <div class="nilai-lulus"><?=$lulus?'✓ LULUS':'✗ TIDAK LULUS'?></div>
    <div style="font-size:9px;color:#64748b;margin-top:6px">KKM: <?=$kkm?></div>
  </div>

  <!-- Statistik -->
  <div class="stat-row">
    <div class="stat-item">
      <div class="stat-num" style="color:#059669"><?=$hasil['jml_benar']??0?></div>
      <div class="stat-lbl">Jawaban Benar</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" style="color:#dc2626"><?=$hasil['jml_salah']??0?></div>
      <div class="stat-lbl">Jawaban Salah</div>
    </div>
    <div class="stat-item">
      <div class="stat-num"><?=$hasil['total_soal']??0?></div>
      <div class="stat-lbl">Total Soal</div>
    </div>
  </div>

  <!-- Footer TTD -->
  <div class="footer-line">
    <div class="footer-info">
      Dicetak: <?=date('d/m/Y H:i')?><br>
      <?=e($namaAplikasi)?> · <?=e($namaKecamatan)?>
    </div>
    <div class="ttd-box">
      <div class="ttd-line"></div>
      <div class="ttd-nama">Pengawas Ujian</div>
    </div>
  </div>

</div>
<script>
// Auto print jika ada param ?autoprint=1
if (new URLSearchParams(location.search).get('autoprint') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>
</body>
</html>
