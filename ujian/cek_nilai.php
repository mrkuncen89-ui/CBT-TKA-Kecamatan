<?php
// ============================================================
// ujian/cek_nilai.php — Dashboard Nilai Peserta (upgrade)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$kkm               = (int)getSetting($conn, 'kkm', '60');

$kode    = '';
$peserta = null;
$riwayat = [];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $kode = strtoupper(trim($_POST['kode_peserta'] ?? ''));
    if (!$kode) {
        $error = 'Kode peserta wajib diisi.';
    } else {
        $kd   = $conn->real_escape_string($kode);
        $pRow = $conn->query(
            "SELECT p.*, s.nama_sekolah FROM peserta p
             LEFT JOIN sekolah s ON s.id = p.sekolah_id
             WHERE p.kode_peserta = '$kd' LIMIT 1"
        );
        if (!$pRow || $pRow->num_rows === 0) {
            $error = 'Kode peserta tidak ditemukan. Pastikan kode sesuai kartu ujian.';
        } else {
            $peserta = $pRow->fetch_assoc();
            $pid     = (int)$peserta['id'];

            // Riwayat nilai lengkap
            $riwayatRes = $conn->query("
                SELECT h.nilai, h.jml_benar, h.jml_salah, h.jml_kosong,
                       h.total_soal, h.waktu_mulai, h.waktu_selesai,
                       FLOOR(h.durasi_detik / 60) AS durasi,
                       h.jadwal_id,
                       COALESCE(k.nama_kategori, 'Umum') AS nama_kategori,
                       COALESCE(k.id, 0) AS kategori_id,
                       jd.tanggal AS jadwal_tanggal, jd.keterangan AS jadwal_ket
                FROM hasil_ujian h
                LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
                LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
                WHERE h.peserta_id = $pid
                ORDER BY h.waktu_selesai DESC
            ");
            if ($riwayatRes) while ($r = $riwayatRes->fetch_assoc()) $riwayat[] = $r;
        }
    }
}

// Hitung ranking per jadwal untuk setiap riwayat
$rankingData = [];
foreach ($riwayat as $r) {
    if (!$r['jadwal_id']) continue;
    $jid = (int)$r['jadwal_id'];
    // Berapa peserta yang nilainya lebih tinggi di jadwal yang sama
    $qRank = $conn->query("SELECT COUNT(*)+1 AS peringkat FROM hasil_ujian WHERE jadwal_id=$jid AND nilai > {$r['nilai']}");
    $qTotal = $conn->query("SELECT COUNT(*) AS c FROM hasil_ujian WHERE jadwal_id=$jid");
    $rankingData[$jid] = [
        'rank'  => $qRank  ? (int)$qRank->fetch_assoc()['peringkat']  : null,
        'total' => $qTotal ? (int)$qTotal->fetch_assoc()['c'] : null,
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cek Nilai — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;padding:24px 16px 60px;min-height:100vh}
.wrap{max-width:680px;margin:0 auto}
.main-card{background:#fff;border-radius:16px;padding:28px 24px;box-shadow:0 2px 20px rgba(0,0,0,.09);margin-bottom:16px}
.card-header-top{background:linear-gradient(135deg,#1e3a8a,#1d4ed8);border-radius:12px;padding:20px;text-align:center;margin-bottom:18px}
.judul-app{font-size:20px;font-weight:900;color:#fff;letter-spacing:.5px;margin-bottom:4px;text-transform:uppercase}
.judul-sub{font-size:13px;font-weight:700;color:rgba(255,255,255,.8)}
.field-label{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:block}
.field-input{width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:15px;font-weight:700;font-family:'Courier New',monospace;letter-spacing:2px;text-transform:uppercase;text-align:center;color:#1e293b;outline:none;transition:border .15s}
.field-input:focus{border-color:#1e3a8a;background:#eff6ff;box-shadow:0 0 0 3px rgba(30,58,138,.1)}
.btn-cek{width:100%;background:#1e3a8a;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;margin-top:4px;transition:background .15s}
.btn-cek:hover{background:#1e40af}
.error-alert{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:9px 12px;font-size:13px;color:#dc2626;margin-bottom:14px;display:flex;align-items:center;gap:8px}

/* Peserta info */
.peserta-card{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:16px;margin-bottom:16px;display:flex;align-items:center;gap:14px}
.avatar{width:52px;height:52px;border-radius:50%;background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;flex-shrink:0}
.peserta-nama{font-size:17px;font-weight:800;color:#1e293b;margin-bottom:2px}
.peserta-info{font-size:12px;color:#64748b}

/* Stat cards */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.stat-box{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.stat-val{font-size:26px;font-weight:900;line-height:1;color:#1e3a8a}
.stat-lbl{font-size:10px;color:#94a3b8;margin-top:3px;font-weight:600}

/* Nilai card */
.nilai-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:box-shadow .15s}
.nilai-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1)}
.nilai-card.lulus{border-left:4px solid #16a34a}
.nilai-card.tidak-lulus{border-left:4px solid #dc2626}
.mapel-badge{display:inline-block;background:#eff6ff;color:#1e3a8a;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:8px;border:1px solid #bfdbfe}
.nilai-besar{font-size:46px;font-weight:900;line-height:1}
.stat-mini{display:flex;gap:14px;margin-top:10px;flex-wrap:wrap}
.stat-mini-item{text-align:center;min-width:40px}
.stat-mini-num{font-size:18px;font-weight:800;color:#1e293b}
.stat-mini-lbl{font-size:10px;color:#94a3b8;margin-top:1px}
.tanggal-info{font-size:11px;color:#94a3b8;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9}
.ranking-badge{background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:8px;padding:4px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px}

.footer-area{text-align:center;margin-top:16px}
.footer-area a{color:#64748b;font-size:12px;text-decoration:none}
.footer-area a:hover{color:#1e3a8a}

@media(max-width:480px){.stat-row{grid-template-columns:repeat(2,1fr)}.stat-val{font-size:22px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="main-card">

    <!-- Header -->
    <div class="card-header-top">
      <div class="judul-app"><?= e($namaAplikasi) ?></div>
      <?php if ($namaPenyelenggara): ?>
      <div class="judul-sub"><?= e($namaPenyelenggara) ?></div>
      <?php endif; ?>
      <div class="judul-sub" style="margin-top:6px;opacity:.85">🔍 Cek Nilai & Ranking Ujian</div>
    </div>

    <?php if ($error): ?>
    <div class="error-alert"><span style="font-size:16px">⚠️</span><?= $error ?></div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" autocomplete="off">
      <?= csrfField() ?>
      <div style="margin-bottom:14px">
        <label class="field-label">Kode Peserta</label>
        <input type="text" name="kode_peserta" class="field-input"
               placeholder="• • • • • • • •"
               value="<?= e($kode) ?>" maxlength="20" required autofocus>
        <div style="font-size:11px;color:#94a3b8;text-align:center;margin-top:5px">Kode ada di kartu ujian Anda</div>
      </div>
      <button type="submit" class="btn-cek">
        <i class="bi bi-search me-2"></i>Cek Nilai Saya
      </button>
    </form>

    <?php if ($peserta): ?>
    <hr style="margin:20px 0;border-color:#e2e8f0">

    <!-- Info peserta -->
    <div class="peserta-card">
      <div class="avatar"><?= mb_strtoupper(mb_substr($peserta['nama'],0,2)) ?></div>
      <div>
        <div class="peserta-nama"><?= e($peserta['nama']) ?></div>
        <div class="peserta-info">
          <i class="bi bi-building me-1"></i><?= e($peserta['nama_sekolah'] ?? '-') ?>
          &nbsp;·&nbsp; Kelas <?= e($peserta['kelas'] ?? '-') ?>
          &nbsp;·&nbsp; <code style="font-size:11px"><?= e($peserta['kode_peserta']) ?></code>
        </div>
      </div>
    </div>

    <?php if (empty($riwayat)): ?>
    <div style="text-align:center;padding:32px;color:#94a3b8">
      <i class="bi bi-inbox" style="font-size:36px;display:block;margin-bottom:10px"></i>
      Belum ada riwayat ujian.
    </div>

    <?php else:
      // Statistik ringkasan
      $nilaiArr  = array_column($riwayat, 'nilai');
      $rataRata  = round(array_sum($nilaiArr) / count($nilaiArr), 1);
      $nilaiMax  = max($nilaiArr);
      $jmlLulus  = count(array_filter($nilaiArr, fn($n) => $n >= $kkm));
      $jmlUjian  = count($riwayat);
    ?>

    <!-- Stat ringkasan -->
    <div class="stat-row">
      <div class="stat-box">
        <div class="stat-val"><?= $jmlUjian ?></div>
        <div class="stat-lbl">Sesi Ujian</div>
      </div>
      <div class="stat-box">
        <div class="stat-val text-success"><?= $nilaiMax ?></div>
        <div class="stat-lbl">Nilai Terbaik</div>
      </div>
      <div class="stat-box">
        <div class="stat-val" style="color:#1e3a8a"><?= $rataRata ?></div>
        <div class="stat-lbl">Rata-rata</div>
      </div>
    </div>

    <?php if ($jmlUjian > 1): ?>
    <!-- Grafik tren nilai -->
    <div style="margin-bottom:16px;background:#f8fafc;border-radius:10px;padding:14px">
      <div style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px">📈 Tren Nilai</div>
      <canvas id="chartTren" height="80"></canvas>
    </div>
    <?php endif; ?>

    <div style="font-size:13px;font-weight:700;color:#475569;margin-bottom:10px">
      📋 Riwayat Ujian (<?= $jmlUjian ?> sesi)
    </div>

    <?php foreach ($riwayat as $r):
        $lulus  = $r['nilai'] >= $kkm;
        [$pred, $ket, $badge] = match(true) {
            $r['nilai'] >= 90 => ['A','Istimewa','success'],
            $r['nilai'] >= 80 => ['B','Sangat Baik','success'],
            $r['nilai'] >= 70 => ['C','Baik','info'],
            $r['nilai'] >= (float)$kkm => ['D','Cukup','warning'],
            default           => ['E','Perlu Bimbingan','danger'],
        };
        $jid    = (int)($r['jadwal_id'] ?? 0);
        $rank   = $rankingData[$jid]['rank']  ?? null;
        $total  = $rankingData[$jid]['total'] ?? null;
    ?>
    <div class="nilai-card <?= $lulus ? 'lulus' : 'tidak-lulus' ?>">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-1">
        <span class="mapel-badge"><?= e($r['nama_kategori']) ?></span>
        <?php if ($rank && $total): ?>
        <span class="ranking-badge">🏅 Ranking <?= $rank ?> / <?= $total ?></span>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div>
          <div class="nilai-besar text-<?= $badge ?>"><?= number_format($r['nilai'], 0) ?></div>
          <div style="margin-top:4px">
            <span class="badge bg-<?= $badge ?>"><?= $pred ?> — <?= $ket ?></span>
            <span class="badge <?= $lulus ? 'bg-success' : 'bg-danger' ?> ms-1">
              <?= $lulus ? '✓ Lulus' : '✗ Tidak Lulus' ?>
            </span>
          </div>
        </div>
        <div class="stat-mini ms-auto">
          <div class="stat-mini-item">
            <div class="stat-mini-num text-success"><?= $r['jml_benar'] ?></div>
            <div class="stat-mini-lbl">Benar</div>
          </div>
          <div class="stat-mini-item">
            <div class="stat-mini-num text-danger"><?= $r['jml_salah'] ?></div>
            <div class="stat-mini-lbl">Salah</div>
          </div>
          <div class="stat-mini-item">
            <div class="stat-mini-num text-secondary"><?= $r['jml_kosong'] ?></div>
            <div class="stat-mini-lbl">Kosong</div>
          </div>
          <div class="stat-mini-item">
            <div class="stat-mini-num"><?= $r['total_soal'] ?></div>
            <div class="stat-mini-lbl">Total</div>
          </div>
        </div>
      </div>
      <div class="tanggal-info">
        <i class="bi bi-calendar2 me-1"></i>
        <?= $r['jadwal_tanggal'] ? date('d F Y', strtotime($r['jadwal_tanggal'])) : '-' ?>
        <?php if ($r['waktu_selesai']): ?>
        &nbsp;·&nbsp; Selesai <?= date('H:i', strtotime($r['waktu_selesai'])) ?> WIB
        <?php endif; ?>
        <?php if ($r['durasi']): ?>
        &nbsp;·&nbsp; <?= $r['durasi'] ?> menit
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

  </div>

  <div class="footer-area">
    <a href="<?= BASE_URL ?>/ujian/login_peserta.php">← Halaman Ujian</a>
    &nbsp;|&nbsp;
    <a href="<?= BASE_URL ?>/login.php">← Login Admin</a>
  </div>
</div>

<script>
document.querySelector('.field-input')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

<?php if ($peserta && count($riwayat) > 1): ?>
// Grafik tren nilai (urutan dari lama ke baru)
const trenData = <?= json_encode(array_reverse(array_map(fn($r) => [
    'label' => ($r['jadwal_tanggal'] ? date('d/m', strtotime($r['jadwal_tanggal'])) : '?') . ' ' . e($r['nama_kategori']),
    'nilai' => (float)$r['nilai'],
], $riwayat))) ?>;
new Chart(document.getElementById('chartTren'), {
    type: 'line',
    data: {
        labels: trenData.map(d => d.label),
        datasets: [{
            label: 'Nilai',
            data: trenData.map(d => d.nilai),
            borderColor: '#1e3a8a',
            backgroundColor: 'rgba(30,58,138,.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#1e3a8a',
            pointRadius: 5,
            fill: true,
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { min: 0, max: 100, grid: { color:'rgba(0,0,0,.05)' } },
            x: { ticks: { font: { size: 10 } } }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
