<?php
// ============================================================
// ujian/login_peserta.php — Login Peserta Ujian
// Peserta memasukkan: kode_peserta + token ujian
// ============================================================

// Session HARUS distart lebih dulu sebelum akses $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');   // Nama berbeda dari admin (TKA_SID) agar tidak bentrok
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

// Jika sudah login sebagai peserta, langsung ke halaman soal
if (!empty($_SESSION['peserta_id'])) {
    redirect(BASE_URL . '/ujian/soal.php');
}

$error = '';
$today    = date('Y-m-d');
$nowTime  = date('H:i:s');

// ── Ambil jadwal aktif sekarang ───────────────────────────────
$jadwal = null;
$jr = $conn->query(
    "SELECT j.*, k.nama_kategori
     FROM jadwal_ujian j
     LEFT JOIN kategori_soal k ON k.id = j.kategori_id
     WHERE j.tanggal='$today' AND j.jam_mulai<='$nowTime' AND j.jam_selesai>='$nowTime' AND j.status='aktif'
     LIMIT 1"
);
if ($jr && $jr->num_rows > 0) $jadwal = $jr->fetch_assoc();

// ── Proses form login ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $kode  = strtoupper(trim($_POST['kode_peserta'] ?? ''));
    $token = strtoupper(trim($_POST['token'] ?? ''));

    if ($kode === '' || $token === '') {
        $error = 'Kode peserta dan token ujian wajib diisi.';
    } else {
        // 1. Cek token valid (aktif + tanggal hari ini)
        $tk      = $conn->real_escape_string($token);
        $nowTime = date('H:i:s');
        $tokenRow = $conn->query(
            "SELECT * FROM token_ujian WHERE token='$tk' AND tanggal='$today' AND status='aktif' LIMIT 1"
        );
        if (!$tokenRow || $tokenRow->num_rows === 0) {
            $error = 'Token ujian tidak valid atau belum aktif untuk hari ini.';
        } else {
            $tokenData = $tokenRow->fetch_assoc();

            // Cek jam sesi jika token punya batasan jam
            if (!empty($tokenData['jam_mulai']) && !empty($tokenData['jam_selesai'])) {
                if ($nowTime < $tokenData['jam_mulai']) {
                    $mulai = substr($tokenData['jam_mulai'], 0, 5);
                    $error = "Sesi ujian belum dimulai. Token ini aktif mulai jam <strong>$mulai</strong>.";
                    $tokenData = null;
                } elseif ($nowTime > $tokenData['jam_selesai']) {
                    $selesai = substr($tokenData['jam_selesai'], 0, 5);
                    $error = "Waktu sesi ujian sudah berakhir (batas jam <strong>$selesai</strong>).";
                    $tokenData = null;
                }
            }
        }
        if ($tokenData !== null && empty($error)) {

            // 2. Cek jadwal ujian aktif
            if (!$jadwal) {
                $error = 'Ujian belum dimulai atau sudah berakhir. Silakan hubungi pengawas.';
            } else {
                // 2b. Validasi jumlah soal di bank cukup
                $jumlahSoalGlobal = (int)getSetting($conn, 'jumlah_soal', '0');
                // Ambil jumlah_soal override dari jadwal jika ada
                $_colCekL = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'jumlah_soal'");
                if ($_colCekL && $_colCekL->num_rows > 0) {
                    $_qJdL = $conn->query("SELECT jumlah_soal FROM jadwal_ujian WHERE id=" . (int)$jadwal['id'] . " LIMIT 1");
                    if ($_qJdL && $_qJdL->num_rows > 0) {
                        $jdL = $_qJdL->fetch_assoc();
                        if (!empty($jdL['jumlah_soal'])) $jumlahSoalGlobal = (int)$jdL['jumlah_soal'];
                    }
                }
                $katFilter = $jadwal['kategori_id'] ? "WHERE kategori_id=" . (int)$jadwal['kategori_id'] : '';
                $qBank     = $conn->query("SELECT COUNT(*) AS c FROM soal $katFilter");
                $jmlBank   = $qBank ? (int)$qBank->fetch_assoc()['c'] : 0;
                // Jika jumlahSoalGlobal 0 berarti "pakai semua", tidak perlu validasi minimum
                $jadwalJmlSoal = $jumlahSoalGlobal > 0 ? $jumlahSoalGlobal : $jmlBank;
                if ($jmlBank === 0) {
                    $namaMapel = !empty($jadwal['nama_kategori']) ? $jadwal['nama_kategori'] : 'semua mapel';
                    $error = "Bank soal <strong>$namaMapel</strong> masih kosong. Hubungi pengawas.";
                } elseif ($jumlahSoalGlobal > 0 && $jmlBank < $jumlahSoalGlobal) {
                    $namaMapel = !empty($jadwal['nama_kategori']) ? $jadwal['nama_kategori'] : 'semua mapel';
                    $error = "Bank soal <strong>$namaMapel</strong> hanya punya <strong>$jmlBank</strong> soal, kurang dari target <strong>$jumlahSoalGlobal</strong> soal. Hubungi pengawas.";
                }
            }
            if (!$error && $jadwal) {
                $kd = $conn->real_escape_string($kode);
                $pRow = $conn->query(
                    "SELECT p.*, s.nama_sekolah FROM peserta p
                     LEFT JOIN sekolah s ON s.id = p.sekolah_id
                     WHERE p.kode_peserta='$kd' LIMIT 1"
                );
                if (!$pRow || $pRow->num_rows === 0) {
                    $error = 'Kode peserta tidak ditemukan. Periksa kembali kartu ujian Anda.';
                } else {
                    $peserta = $pRow->fetch_assoc();

                    // 4. Cek apakah sudah ujian pada jadwal/mapel yang SAMA (bukan semua ujian)
                    //    Fix: peserta yang sudah ujian Matematika tetap bisa ikut Bahasa Indonesia
                    $jadwalIdCek = (int)$jadwal['id'];
                    $sudahSelesai = $conn->query(
                        "SELECT id FROM ujian
                         WHERE peserta_id={$peserta['id']}
                           AND jadwal_id=$jadwalIdCek
                           AND waktu_selesai IS NOT NULL
                         LIMIT 1"
                    );
                    if ($sudahSelesai && $sudahSelesai->num_rows > 0) {
                        $namaMapel = !empty($jadwal['nama_kategori']) ? ' (' . $jadwal['nama_kategori'] . ')' : '';
                        $error = "Anda sudah menyelesaikan ujian sesi ini{$namaMapel}. Hubungi pengawas jika ada masalah.";
                    } else {
                        // 5. Cek apakah ujian pada jadwal yang SAMA sedang berlangsung (belum selesai)
                        $ujianAktif = $conn->query(
                            "SELECT id FROM ujian
                             WHERE peserta_id={$peserta['id']}
                               AND jadwal_id=$jadwalIdCek
                               AND waktu_selesai IS NULL
                             LIMIT 1"
                        );

                        $jadwalIdVal = $jadwal ? (int)$jadwal['id'] : 'NULL';
                        if ($ujianAktif && $ujianAktif->num_rows > 0) {
                            // Lanjutkan ujian yang sudah dimulai
                            $ujianId = $ujianAktif->fetch_assoc()['id'];
                            // Update jadwal_id jika belum terisi
                            if ($jadwal) {
                                $conn->query("UPDATE ujian SET jadwal_id = $jadwalIdVal WHERE id = $ujianId AND jadwal_id IS NULL");
                            }
                        } else {
                            // Buat sesi ujian baru
                            $conn->query(
                                "INSERT INTO ujian (peserta_id, token_id, jadwal_id, waktu_mulai, last_activity)
                                 VALUES ({$peserta['id']}, {$tokenData['id']}, $jadwalIdVal, NOW(), NOW())"
                            );
                            $ujianId = $conn->insert_id;
                        }

                        // Simpan ke session peserta
                        $_SESSION['peserta_id']          = $peserta['id'];
                        $_SESSION['peserta_nama']        = $peserta['nama'];
                        $_SESSION['peserta_kelas']       = $peserta['kelas'];
                        $_SESSION['peserta_sekolah']     = $peserta['nama_sekolah'] ?? '';
                        $_SESSION['kode_peserta']        = $peserta['kode_peserta'];
                        $_SESSION['ujian_id']            = $ujianId;
                        $_SESSION['jadwal_id']           = $jadwal['id'];
                        $_SESSION['jam_selesai']         = $jadwal['jam_selesai'];
                        $_SESSION['tanggal_ujian']       = $jadwal['tanggal']; // BUG FIX #4: simpan tanggal ujian untuk validasi lintas malam
                        $_SESSION['durasi_menit']        = $jadwal['durasi_menit'];
                        $_SESSION['jadwal_kategori_id']  = $jadwal['kategori_id'] ?? null;
                        $_SESSION['jadwal_mapel']        = $jadwal['nama_kategori'] ?? null;

                        // Log
                        logActivity($conn, 'Login Peserta', "Peserta {$peserta['nama']} ({$kode}) login ujian");

                        redirect(BASE_URL . '/ujian/soal.php');
                    }
                }
            }
        }
    }
}

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$jumlahSoal        = (int)getSetting($conn, 'jumlah_soal', '40');
$durasi            = getSetting($conn, 'durasi_ujian', '60');
$tahunPelajaran    = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));

// ── Cek mode maintenance ──────────────────────────────────────
$modeMaintenance = getSetting($conn, 'mode_maintenance', '0');
if ($modeMaintenance === '1') {
    $pesanMaintenance = getSetting($conn, 'pesan_maintenance', 'Sistem sedang dalam pemeliharaan. Silakan tunggu.');
    $error = '🔧 <strong>Sistem Maintenance:</strong> ' . htmlspecialchars($pesanMaintenance);
}

// Ambil mata pelajaran dari kategori soal yang ada di bank soal
$mapelList = [];
$mapelRes = $conn->query(
    "SELECT DISTINCT k.nama_kategori
     FROM kategori_soal k
     INNER JOIN soal s ON s.kategori_id = k.id
     ORDER BY k.nama_kategori"
);
if ($mapelRes) while ($m = $mapelRes->fetch_assoc()) {
    $mapelList[] = $m['nama_kategori'];
}
$mataPelajaran = implode(' • ', $mapelList);

// Ambil tipe soal yang ada
$tipeList = [];
$tipeRes = $conn->query("SELECT DISTINCT tipe_soal FROM soal WHERE tipe_soal IS NOT NULL");
if ($tipeRes) while ($t = $tipeRes->fetch_assoc()) {
    $tipeList[] = strtoupper($t['tipe_soal']);
}
$tipeStr = implode(' • ', $tipeList) ?: 'PG';

// Ambil daftar sekolah beserta jenjang
$sekolahList = [];
$sl = $conn->query("SELECT id, nama_sekolah, jenjang FROM sekolah ORDER BY jenjang, nama_sekolah");
if ($sl) while ($r = $sl->fetch_assoc()) $sekolahList[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Peserta — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
  background:#f1f5f9;
  min-height:100vh;
  font-family:'Segoe UI',Arial,sans-serif;
  padding:24px 16px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.wrap{max-width:420px;width:100%;margin:0 auto}

/* Card utama */
.main-card{background:#fff;border-radius:16px;padding:28px 24px;box-shadow:0 2px 20px rgba(0,0,0,.09)}

/* Header dalam card */
.card-header-top{background:#1e3a8a;border-radius:12px;padding:20px 20px;text-align:center;margin-bottom:18px}
.judul-app{font-size:20px;font-weight:900;color:#fff;letter-spacing:.5px;line-height:1.2;margin-bottom:4px;text-transform:uppercase}
.judul-penyelenggara{font-size:14px;font-weight:700;color:hsla(0,0%,100%,.85);text-transform:uppercase;letter-spacing:.3px;margin:4px auto 0;line-height:1.6;word-break:normal;white-space:normal;max-width:280px}
.judul-mapel{font-size:13px;font-weight:800;color:#eff6ff;margin:0}

/* Info box */
.info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:13px 16px;margin-bottom:14px}
.info-row{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e3a8a;margin-bottom:6px}
.info-row:last-child{margin-bottom:0}
.info-icon{font-size:15px;width:20px;text-align:center;flex-shrink:0}

/* Status */
.status-ujian{border-radius:8px;padding:8px 14px;font-size:12px;font-weight:600;text-align:center;margin-bottom:16px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}
.status-ujian.aktif{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.status-ujian.nonaktif{background:#fef9c3;color:#854d0e;border:1px solid #fde68a}
.live-dot{width:7px;height:7px;border-radius:50%;background:#16a34a;flex-shrink:0;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* Form */
.field-label{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:block}
.field-input{width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:14px;font-weight:700;font-family:'Courier New',monospace;letter-spacing:2px;text-transform:uppercase;text-align:center;color:#1e293b;transition:border .15s,box-shadow .15s;outline:none}
.field-input:focus{border-color:#1e3a8a;background:#eff6ff;box-shadow:0 0 0 3px rgba(30,58,138,.1)}
.field-input::placeholder{color:#cbd5e1;font-size:12px;letter-spacing:3px}
.field-hint{font-size:11px;color:#94a3b8;text-align:center;margin-top:5px}
.error-alert{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:9px 12px;font-size:12px;color:#dc2626;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.btn-mulai{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#1e3a8a;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;transition:background .15s;margin-top:4px;box-shadow:0 3px 10px rgba(30,58,138,.3)}
.btn-mulai:hover:not(:disabled){background:#1e40af}
.btn-mulai:disabled{background:#94a3b8;box-shadow:none;cursor:not-allowed}
.btn-icon{width:20px;height:20px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px}

/* Footer */
.footer-area{text-align:center;margin-top:14px}
.footer-area a{color:#64748b;font-size:12px;text-decoration:none}
.footer-area a:hover{color:#1e3a8a}
.dev-text{font-size:11px;color:#94a3b8;margin-top:6px}
.dev-text strong{color:#475569}
.dev-text a{color:#1e3a8a;text-decoration:none;font-weight:700}
.dev-text a:hover{color:#1e40af}
</style>
</head>
<body>
<div class="wrap">



  <div class="main-card">

  <!-- Header biru navy -->
  <div class="card-header-top">
    <div class="judul-app"><?= e($namaAplikasi) ?></div>
    <?php if ($namaPenyelenggara): ?>
    <div class="judul-penyelenggara"><?= e($namaPenyelenggara) ?></div>
    <?php endif; ?>
    <?php if ($mataPelajaran): ?>
    <div class="judul-mapel"><?= e($mataPelajaran) ?></div>
    <?php endif; ?>
  </div>

  <!-- Info Box -->
  <div class="info-box">
    <div class="info-row">
      <span class="info-icon">📅</span>
      <span>Tahun Pelajaran : <?= e($tahunPelajaran) ?></span>
    </div>
    <div class="info-row">
      <span class="info-icon">⏱</span>
      <span>Waktu : <?= e($durasi) ?> Menit</span>
    </div>
    <div class="info-row">
      <span class="info-icon">📋</span>
      <span>Jumlah Soal : <?= e($jumlahSoal) ?> Butir</span>
    </div>
    <div class="info-row">
      <span class="info-icon">📌</span>
      <span>Tipe : <?= e($tipeStr) ?></span>
    </div>
  </div>

  <!-- Status Ujian -->
  <?php if ($jadwal): ?>
  <div class="status-ujian aktif">
    <span class="live-dot"></span>
    Ujian berlangsung: <?= substr($jadwal['jam_mulai'],0,5) ?> – <?= substr($jadwal['jam_selesai'],0,5) ?> WIB
    <?php if (!empty($jadwal['nama_kategori'])): ?>
    &nbsp;·&nbsp; <strong><?= e($jadwal['nama_kategori']) ?></strong>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="status-ujian nonaktif">
    ⏳ Ujian belum dimulai — tunggu instruksi pengawas
  </div>
  <?php endif; ?>

  <!-- Info Sekolah per Jenjang - ringkas dengan toggle -->
  <?php if ($sekolahList): ?>
  <?php
  $grouped = [];
  foreach ($sekolahList as $sk) {
      $j = $sk['jenjang'] ?? 'SD';
      $grouped[$j][] = $sk['nama_sekolah'];
  }
  $totalSekolah = count($sekolahList);
  ?>
  <div style="margin-bottom:14px">
    <button type="button" onclick="toggleSekolah()" id="btnSekolah"
      style="width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:12px;font-weight:700;color:#475569;cursor:pointer;display:flex;align-items:center;justify-content:space-between;text-align:left">
      <span>🏫 <?= $totalSekolah ?> Sekolah Terdaftar</span>
      <span id="sekolahArrow" style="font-size:10px;transition:transform .2s">▼</span>
    </button>
    <div id="sekolahPanel" style="display:none;background:#f8fafc;border:1.5px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;padding:10px 12px;max-height:160px;overflow-y:auto">
      <?php foreach ($grouped as $jenjang => $sekolahs):
        $badgeColor = match($jenjang) {
            'SMP','MTS' => '#16a34a',
            'SMA','MA'  => '#b45309',
            'SMK'       => '#dc2626',
            default     => '#1e3a8a',
        };
      ?>
      <div style="margin-bottom:6px;display:flex;align-items:flex-start;gap:8px">
        <span style="background:<?= $badgeColor ?>;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;flex-shrink:0;margin-top:1px"><?= e($jenjang) ?></span>
        <span style="font-size:12px;color:#475569;line-height:1.5"><?= e(implode(', ', $sekolahs)) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <div class="form-section">

    <?php if ($error !== ''): ?>
    <div class="error-alert">
      <span style="font-size:16px">⚠️</span><?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
            <?= csrfField() ?>

      <div style="margin-bottom:14px">
        <label class="field-label">Kode Peserta</label>
        <input type="text" name="kode_peserta" class="field-input"
               placeholder="• • • • • • • •"
               value="<?= e(strtoupper($_POST['kode_peserta'] ?? '')) ?>"
               maxlength="20" required autofocus>
        <div class="field-hint">Lihat kode di kartu ujian Anda</div>
      </div>

      <div style="margin-bottom:16px">
        <label class="field-label">Token Ujian</label>
        <input type="text" name="token" class="field-input"
               placeholder="DARI PENGAWAS"
               value="<?= e(strtoupper($_POST['token'] ?? '')) ?>"
               maxlength="20" required>
        <div class="field-hint">Token diberikan oleh pengawas ujian</div>
      </div>

      <button type="submit" class="btn-mulai" <?= !$jadwal || $modeMaintenance === '1' ? 'disabled' : '' ?>>
        <div class="btn-icon">▶</div>
        Mulai Ujian
      </button>

    </form>
  </div>

  </div><!-- /main-card -->

  <!-- Footer -->
  <div class="footer-area">
    <a href="<?= BASE_URL ?>/login.php">← Login Admin</a>
    &nbsp;|&nbsp;
    <a href="<?= BASE_URL ?>/ujian/cek_nilai.php">🔍 Cek Nilai Saya</a>
    <div class="dev-text">
      Dikembangkan oleh <strong>Cahyana Wijaya</strong> &nbsp;
      <a href="https://www.tiktok.com/@mrkuncen?_r=1&_t=ZS-94kAVOaI36Y" target="_blank">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle">
          <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.78a4.85 4.85 0 0 1-1.01-.09z"/>
        </svg>
        @mrkuncen
      </a>
    </div>
  </div>

</div>
<script>
document.querySelectorAll('.field-input').forEach(el => {
    el.addEventListener('input', () => el.value = el.value.toUpperCase());
});
function toggleSekolah() {
    const panel = document.getElementById('sekolahPanel');
    const arrow = document.getElementById('sekolahArrow');
    const open  = panel.style.display !== 'none';
    panel.style.display = open ? 'none' : 'block';
    arrow.style.transform = open ? '' : 'rotate(180deg)';
}
</script>
</body>
</html>
