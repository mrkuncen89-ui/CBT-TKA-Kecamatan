<?php
// ============================================================
// ujian/soal.php — 1 soal per halaman + ragu-ragu
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

if (empty($_SESSION['peserta_id'])) {
    redirect(BASE_URL . '/ujian/login_peserta.php');
}

$pesertaId    = (int)$_SESSION['peserta_id'];
$ujianId      = (int)$_SESSION['ujian_id'];
$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$jumlahSoal   = (int)getSetting($conn, 'jumlah_soal', '0');

// Override jumlah soal dari jadwal jika ada (kolom mungkin belum ada di DB lama)
$jadwalId = (int)($_SESSION['jadwal_id'] ?? 0);
if ($jadwalId) {
    // Cek kolom ada dulu sebelum query untuk kompatibilitas DB lama
    $_colCek = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'jumlah_soal'");
    if ($_colCek && $_colCek->num_rows > 0) {
        $_qJdSoal = $conn->query("SELECT jumlah_soal FROM jadwal_ujian WHERE id=$jadwalId LIMIT 1");
        if ($_qJdSoal && $_qJdSoal->num_rows > 0) {
            $jdSoalRow = $_qJdSoal->fetch_assoc();
            if (!empty($jdSoalRow['jumlah_soal'])) {
                $jumlahSoal = (int)$jdSoalRow['jumlah_soal'];
            }
        }
    }
}

// Jika jumlahSoal masih 0 (setting belum diisi & jadwal tidak ada override)
// → auto pakai semua soal yang tersedia di bank sesuai kategori jadwal
if ($jumlahSoal <= 0) {
    $jadwalKatIdTemp = (int)($_SESSION['jadwal_kategori_id'] ?? 0);
    $katWhereTemp    = $jadwalKatIdTemp ? "WHERE kategori_id=$jadwalKatIdTemp" : '';
    $_qJmlBank = $conn->query("SELECT COUNT(*) AS c FROM soal $katWhereTemp");
    $jumlahSoal = $_qJmlBank ? max(1, (int)$_qJmlBank->fetch_assoc()['c']) : 20;
}

$_qUjian = $conn->query("SELECT id, peserta_id, waktu_mulai, waktu_selesai, nilai, token_id, jadwal_id, kategori_id, soal_order, pelanggaran, last_activity FROM ujian WHERE id=$ujianId AND peserta_id=$pesertaId AND waktu_selesai IS NULL LIMIT 1");
$ujian = ($_qUjian && $_qUjian->num_rows > 0) ? $_qUjian->fetch_assoc() : null;
if (!$ujian) { session_unset(); session_destroy(); redirect(BASE_URL . '/ujian/selesai.php'); }

updateUjianActivity($conn, $ujianId);

$jamSelesai = $_SESSION['jam_selesai'] ?? null;
$today      = date('Y-m-d');
$sisaDetik  = $jamSelesai ? max(0, strtotime("$today $jamSelesai") - time()) : 0;
if ($sisaDetik <= 0) redirect(BASE_URL . '/ujian/submit.php?auto=1');

// Urutan soal tetap selama sesi
if (empty($_SESSION['soal_order'])) {
    // Ambil soal proporsional per tipe agar semua tipe terwakili
    // Filter berdasarkan kategori jadwal jika ada
    $kategoriFilter = '';
    $jadwalKatId = $_SESSION['jadwal_kategori_id'] ?? null;
    if ($jadwalKatId) {
        $jadwalKatId = (int)$jadwalKatId;
        $kategoriFilter = "WHERE kategori_id = $jadwalKatId";
    }

    $tipeRes = $conn->query("SELECT tipe_soal, COUNT(*) as total FROM soal $kategoriFilter GROUP BY tipe_soal");
    $tipeData = [];
    if ($tipeRes) while ($t = $tipeRes->fetch_assoc()) $tipeData[$t['tipe_soal']] = (int)$t['total'];

    $jumlahTipe = count($tipeData);
    $soalList   = [];

    if ($jumlahTipe > 0) {
        // Bagi jumlah soal merata per tipe, sisa diberikan ke tipe pertama
        $perTipe = (int)floor($jumlahSoal / $jumlahTipe);
        $sisa    = $jumlahSoal - ($perTipe * $jumlahTipe);

        // Hitung berapa yang bisa diambil per tipe (bisa kurang dari target)
        $ambilPerTipe = [];
        $totalBisa    = 0;
        $i = 0;
        foreach ($tipeData as $tipe => $totalTersedia) {
            $target = $perTipe + ($i === 0 ? $sisa : 0);
            $ambil  = min($target, $totalTersedia);
            $ambilPerTipe[$tipe] = $ambil;
            $totalBisa += $ambil;
            $i++;
        }

        // Jika ada kekurangan, distribusikan sisa ke tipe yang masih punya stok
        $kurang = $jumlahSoal - $totalBisa;
        if ($kurang > 0) {
            foreach ($tipeData as $tipe => $totalTersedia) {
                if ($kurang <= 0) break;
                $sudahDiambil = $ambilPerTipe[$tipe];
                $sisaStok     = $totalTersedia - $sudahDiambil;
                if ($sisaStok > 0) {
                    $tambah = min($kurang, $sisaStok);
                    $ambilPerTipe[$tipe] += $tambah;
                    $kurang -= $tambah;
                }
            }
        }

        $i = 0;
        foreach ($tipeData as $tipe => $totalTersedia) {
            $ambil = $ambilPerTipe[$tipe];
            if ($ambil <= 0) { $i++; continue; }
            $tipeEsc = $conn->real_escape_string($tipe);
            $katWhere = $jadwalKatId ? "AND kategori_id=$jadwalKatId" : '';
            // Optimasi: hindari ORDER BY RAND() yang lambat pada data besar
            // Ambil semua ID dulu, acak di PHP, lalu ambil data soal yang dipilih
            $idRes = $conn->query(
                "SELECT id FROM soal WHERE tipe_soal='$tipeEsc' $katWhere"
            );
            $allIds = [];
            if ($idRes) { while ($row = $idRes->fetch_assoc()) $allIds[] = (int)$row['id']; $idRes->free(); }
            if (!empty($allIds)) {
                shuffle($allIds);
                $pickedIds = array_slice($allIds, 0, $ambil);
                $inStr = implode(',', $pickedIds);
                $res = $conn->query(
                    "SELECT id,tipe_soal,pertanyaan,teks_bacaan,gambar,
                            pilihan_a,pilihan_b,pilihan_c,pilihan_d,
                            gambar_pilihan_a,gambar_pilihan_b,gambar_pilihan_c,gambar_pilihan_d
                     FROM soal WHERE id IN ($inStr)"
                );
                if ($res) { while ($r = $res->fetch_assoc()) $soalList[] = $r; $res->free(); }
            }
            $i++;
        }
        // Acak urutan soal agar tidak mengelompok per tipe
        shuffle($soalList);
    }

    $_SESSION['soal_order'] = array_column($soalList, 'id');

    // Simpan soal_order ke DB agar tidak hilang jika session expired
    $soalOrderJson = $conn->real_escape_string(json_encode($_SESSION['soal_order']));
    $conn->query("UPDATE ujian SET soal_order='$soalOrderJson' WHERE id=$ujianId");

    $ids = implode(',', array_map('intval', $_SESSION['soal_order']));
} else {
    // Jika session ada tapi soal_order kosong, coba ambil dari DB
    if (empty($_SESSION['soal_order'])) {
        $_qUjRow = $conn->query("SELECT soal_order FROM ujian WHERE id=$ujianId LIMIT 1");
    $ujianRow = ($_qUjRow && $_qUjRow->num_rows > 0) ? $_qUjRow->fetch_assoc() : null;
        if (!empty($ujianRow['soal_order'])) {
            $_SESSION['soal_order'] = json_decode($ujianRow['soal_order'], true) ?: [];
        }
    }
    $ids = implode(',', array_map('intval', $_SESSION['soal_order'] ?: [0]));
    $res = $conn->query(
        "SELECT id,tipe_soal,pertanyaan,teks_bacaan,gambar,
                pilihan_a,pilihan_b,pilihan_c,pilihan_d,
                gambar_pilihan_a,gambar_pilihan_b,gambar_pilihan_c,gambar_pilihan_d
         FROM soal WHERE id IN ($ids) ORDER BY FIELD(id,$ids)"
    );
    $soalList = [];
    if ($res) while ($r = $res->fetch_assoc()) $soalList[] = $r;
}

$totalSoal = count($soalList);

// Guard: jika soal tidak berhasil dimuat (bukan karena ujian selesai)
// tampilkan halaman error — jangan redirect ke selesai agar ujian tidak terekam nilai 0
if ($totalSoal === 0) {
    ?><!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Soal Tidak Tersedia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body style="background:#f1f5f9;padding:40px 16px;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 20px rgba(0,0,0,.1);text-align:center">
  <div style="font-size:56px;margin-bottom:16px">⚠️</div>
  <h4 style="color:#dc2626;font-weight:800;margin-bottom:8px">Soal Tidak Dapat Dimuat</h4>
  <p style="color:#475569;font-size:14px;margin-bottom:20px">
    Terjadi masalah saat memuat soal ujian.<br>
    <strong>Ujian Anda belum terekam — jangan tutup browser ini.</strong>
  </p>
  <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:12px;font-size:13px;color:#dc2626;margin-bottom:20px">
    Hubungi pengawas ujian sekarang untuk mendapatkan bantuan.
  </div>
  <button onclick="location.reload()" style="background:#1e3a8a;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:700;cursor:pointer">
    🔄 Coba Muat Ulang
  </button>
</div>
</body></html><?php
    exit;
}

$noAktif   = max(1, min((int)($_GET['no'] ?? 1), $totalSoal));
$soal      = $soalList[$noAktif - 1];
$soalId    = $soal['id'];

$jawabans = [];
$jr = $conn->query("SELECT soal_id, jawaban FROM jawaban WHERE ujian_id=$ujianId AND peserta_id=$pesertaId");
if ($jr) while ($j = $jr->fetch_assoc()) $jawabans[$j['soal_id']] = $j['jawaban'];

if (!isset($_SESSION['ragu'])) $_SESSION['ragu'] = [];
$raguList = $_SESSION['ragu'];

$jwbAktif   = $jawabans[$soalId] ?? null;
$isRagu     = in_array($soalId, $raguList);
$sdhJawab   = count($jawabans);
$belumJawab = $totalSoal - $sdhJawab;
$jumlahRagu = count($raguList);

// ── Acak urutan pilihan per peserta ──────────────────────────
// Jika setting acak_pilihan aktif, acak urutan A/B/C/D per soal
// Urutan disimpan di session agar konsisten (tidak berubah tiap refresh)
$acakPilihan = getSetting($conn, 'acak_pilihan', '0') === '1';
if ($acakPilihan && $soal['tipe_soal'] === 'pg') {
    $sessionKey = "pilihan_order_{$soalId}";
    if (!isset($_SESSION[$sessionKey])) {
        $order = ['a','b','c','d'];
        // Filter hanya pilihan yang tidak kosong
        $order = array_filter($order, fn($k) => !empty($soal['pilihan_'.$k]));
        $order = array_values($order);
        shuffle($order);
        // Simpan mapping: huruf_asli => huruf_tampil dan sebaliknya
        $mapping = []; // huruf tampil => huruf asli
        $labels  = ['a','b','c','d'];
        foreach ($order as $i => $asli) {
            $mapping[$labels[$i]] = $asli; // tampil A -> asli bisa C
        }
        $_SESSION[$sessionKey] = $mapping;
    }
    $pilihanMapping = $_SESSION[$sessionKey]; // tampil => asli
} else {
    $pilihanMapping = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ujian — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{background:#eef2f7;font-family:Arial,sans-serif;margin:0;min-height:100vh}
.topbar{position:sticky;top:0;z-index:100;background:linear-gradient(90deg,#1a3faa,#1e40af);padding:0 20px;display:flex;align-items:center;height:56px;gap:14px;box-shadow:0 2px 10px rgba(0,0,0,.25)}
.topbar-name{color:#fff;font-weight:700;font-size:14px;flex:1;line-height:1.3}
.topbar-name small{display:block;font-weight:400;font-size:11px;opacity:.75}
.timer-box{background:rgba(255,255,255,.15);border-radius:8px;padding:5px 14px;color:#fff;font-size:22px;font-weight:900;font-family:monospace;letter-spacing:2px;min-width:96px;text-align:center;border:1.5px solid rgba(255,255,255,.2)}
.timer-box.warning{background:#dc2626;border-color:#dc2626;animation:blink .8s infinite}
@keyframes blink{50%{opacity:.65}}
.btn-selesai-top{background:#f59e0b;border:none;color:#1e293b;font-weight:700;font-size:13px;border-radius:7px;padding:7px 14px;cursor:pointer}
.btn-selesai-top:hover{background:#d97706}

/* ── Layout utama ── */
.main-wrap{display:flex;gap:14px;padding:14px;max-width:1200px;margin:0 auto}
.soal-area{flex:1;min-width:0}
.side-panel{width:220px;flex-shrink:0}

/* ── Soal card: 2 kolom (kiri=soal, kanan=pilihan) ── */
.soal-card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden}

/* Header soal (nomor + ragu) */
.soal-card-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 20px;
  background:#f8fafc;
  border-bottom:1px solid #e2e8f0;
}

/* Body 2 kolom */
.soal-card-body{
  display:grid;
  grid-template-columns:1fr 1px 1fr;  /* kiri | pembatas | kanan */
  min-height:340px;
}
.soal-kiri{
  padding:22px 24px;
  overflow-y:auto;
  /* Tinggi mengikuti konten — kolom kanan bisa lebih pendek */
}
.soal-divider{
  width:1px;background:#e2e8f0;
  margin:16px 0;
}
.soal-kanan{
  padding:22px 24px;
  display:flex;flex-direction:column;justify-content:flex-start;
}

/* Footer navigasi */
.soal-card-foot{
  padding:12px 20px;
  border-top:1px solid #e2e8f0;
  display:flex;align-items:center;justify-content:space-between;
  background:#f8fafc;
  gap:10px;
}

.soal-badge{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1a56db;font-size:12px;font-weight:700;border-radius:20px;padding:4px 12px;border:1px solid #bfdbfe}
.soal-text{font-size:15.5px;line-height:1.9;color:#1e293b}
.soal-img{max-width:100%;border-radius:8px;margin-bottom:14px;border:1px solid #e2e8f0;display:block}

/* Label "Pilih Jawaban" di kanan */
.pilihan-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}

.pilihan-item{display:flex;align-items:flex-start;gap:12px;padding:11px 14px;border-radius:10px;cursor:pointer;border:2px solid #e2e8f0;margin-bottom:8px;transition:all .15s;font-size:14.5px;color:#334155;background:#fafafa}
.pilihan-item:hover{border-color:#1a56db;background:#eff6ff}
.pilihan-item.selected{border-color:#1a56db;background:#eff6ff}
.huruf-box{width:30px;height:30px;border-radius:50%;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;color:#64748b;flex-shrink:0;margin-top:1px;transition:all .15s}
.pilihan-item.selected .huruf-box{background:#1a56db;border-color:#1a56db;color:#fff}
.pilihan-teks{flex:1;line-height:1.5;padding-top:3px}
.pilihan-item.mcma-selected{border-color:#7c3aed;background:#f5f3ff}
.pilihan-item.mcma-selected .huruf-box{background:#7c3aed;border-color:#7c3aed;color:#fff}
.mcma-info{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#6d28d9;font-weight:600}
.btn-ragu{border:2px solid #f59e0b;color:#b45309;background:#fffbeb;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .15s}
.btn-ragu.aktif{background:#f59e0b;color:#fff;border-color:#f59e0b}
.btn-nav{padding:9px 20px;border-radius:9px;font-weight:700;font-size:14px;border:none;cursor:pointer;display:flex;align-items:center;gap:6px}
.btn-prev{background:#e2e8f0;color:#475569}
.btn-prev:hover:not(:disabled){background:#cbd5e1}
.btn-next{background:#1a56db;color:#fff}
.btn-next:hover:not(:disabled){background:#1e40af}
.btn-nav:disabled{opacity:.4;cursor:not-allowed}
.nav-btn:disabled{opacity:.5;cursor:not-allowed}
.soal-text,.pilihan-item{transition:opacity .15s}
@keyframes spin{to{transform:rotate(360deg)}}
.btn-loading::after{content:'';display:inline-block;width:13px;height:13px;margin-left:8px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;}

/* Navigasi panel kanan */
.panel-card{background:#fff;border-radius:14px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);position:sticky;top:70px}
.panel-title{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}
.nav-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:5px;margin-bottom:14px}
.nav-btn{aspect-ratio:1;border-radius:7px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:12px;font-weight:700;cursor:pointer;color:#64748b;transition:all .15s}
.nav-btn:hover{border-color:#1a56db;color:#1a56db;background:#eff6ff}
.nav-btn.answered{background:#1a56db;border-color:#1a56db;color:#fff}
.nav-btn.ragu{background:#f59e0b;border-color:#f59e0b;color:#fff}
.nav-btn.current{box-shadow:0 0 0 2.5px #1a56db,0 0 0 4.5px #bfdbfe}
.nav-btn.answered.ragu{background:#f59e0b;border-color:#f59e0b}
.legend{display:flex;flex-wrap:wrap;gap:6px;font-size:11px;color:#64748b;margin-bottom:14px;align-items:center}
.leg-dot{width:13px;height:13px;border-radius:4px;display:inline-block;flex-shrink:0}
.progress-info{font-size:12px;color:#64748b;margin-bottom:14px;line-height:1.8}
.btn-submit-side{width:100%;border-radius:9px;padding:11px;font-weight:700;font-size:14px;border:none;cursor:pointer;background:#10b981;color:#fff}
.btn-submit-side:hover{background:#059669}
.modal-content{border:none;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.18)}
.ragu-badge{background:#fef3c7;color:#b45309;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid #fcd34d}

/* ── MOBILE: kembali 1 kolom ── */
@media(max-width:900px){
  .main-wrap{flex-direction:column;padding:10px;gap:10px}
  .side-panel{width:100%;order:2}
  .soal-area{order:1}
  .panel-card{position:static}
  /* 2-kolom soal collapse jadi 1 kolom di mobile */
  .soal-card-body{grid-template-columns:1fr;min-height:unset}
  .soal-divider{display:none}
  .soal-kiri{padding:16px 16px 8px}
  .soal-kanan{padding:8px 16px 16px;border-top:1px solid #e2e8f0}
  .soal-text{font-size:15px}
  .pilihan-item{padding:10px 12px;font-size:14px}
  .huruf-box{width:28px;height:28px;font-size:12px}
  .nav-grid{grid-template-columns:repeat(6,1fr)}
  .nav-btn{font-size:11px}
  .btn-nav{padding:9px 14px;font-size:13px}
  .btn-ragu{padding:8px 12px;font-size:12px}
  .topbar{padding:0 12px;height:52px}
  .topbar-name{font-size:13px}
  .timer-box{font-size:18px;min-width:80px;padding:4px 10px}
  .btn-selesai-top{font-size:12px;padding:6px 10px}
  .soal-badge{font-size:11px}
  .soal-card-foot{flex-wrap:wrap;gap:8px}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-name">
    <?= e($_SESSION['peserta_nama']) ?>
    <small><?= e($_SESSION['peserta_kelas']) ?> &middot; <?= e($_SESSION['peserta_sekolah']) ?></small>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <i class="bi bi-clock" style="color:rgba(255,255,255,.7);font-size:16px"></i>
    <div class="timer-box" id="timerBox">--:--</div>
  </div>
  <button class="btn-selesai-top" onclick="bukaModalSelesai()">
    <i class="bi bi-check-circle me-1"></i>Selesai
  </button>
</div>

<div class="main-wrap">

<div class="soal-area">
    <div class="soal-card">

      <!-- Header: nomor soal + badge ragu -->
      <div class="soal-card-head">
        <div class="soal-badge"><i class="bi bi-question-circle"></i> Soal <?= $noAktif ?> dari <?= $totalSoal ?></div>
        <?php if ($isRagu): ?><span class="ragu-badge">&#9888; Ragu-ragu</span><?php endif; ?>
      </div>

      <!-- Body 2 kolom -->
      <div class="soal-card-body">

        <!-- KIRI: teks bacaan + gambar + pertanyaan -->
        <div class="soal-kiri">
          <div id="teksBacaanWrap">
          <?php if (!empty($soal['teks_bacaan'])): ?>
          <div style="background:#f0f9ff;border-left:4px solid #1a56db;border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:16px;font-size:13.5px;line-height:1.9;color:#1e293b;">
            <div style="font-size:10px;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
              &#128196; Bacalah teks berikut!
            </div>
            <?= nl2br(e($soal['teks_bacaan'])) ?>
          </div>
          <?php endif; ?>

          <?php if ($soal['gambar']): ?>
          <img src="<?= BASE_URL ?>/assets/uploads/soal/<?= e($soal['gambar']) ?>" class="soal-img" alt="Gambar soal">
          <?php endif; ?>
          </div>

          <div class="soal-text"><?= nl2br(e($soal['pertanyaan'])) ?></div>
        </div>

        <!-- Garis pembatas -->
        <div class="soal-divider"></div>

        <!-- KANAN: pilihan jawaban -->
        <div class="soal-kanan">
          <div class="pilihan-label">Pilih Jawaban</div>
          <div id="pilihanWrap">
          <?php if ($soal['tipe_soal'] === 'bs'): ?>
            <?php foreach (['benar'=>'Benar','salah'=>'Salah'] as $val=>$label): ?>
            <div class="pilihan-item <?= $jwbAktif===$val?'selected':'' ?>" onclick="pilihJawaban('<?= $val ?>',this,<?= $soalId ?>)">
              <div class="huruf-box"><?= $val==='benar'?'B':'S' ?></div>
              <div class="pilihan-teks"><?= $label ?></div>
            </div>
            <?php endforeach; ?>

          <?php elseif ($soal['tipe_soal'] === 'mcma'):
            $jwbMcmaArr = $jwbAktif ? explode(',', $jwbAktif) : [];
          ?>
            <div class="mcma-info">
              <i class="bi bi-info-circle me-1"></i>Boleh pilih lebih dari satu jawaban yang benar.
            </div>
            <?php foreach (['a','b','c','d'] as $h):
              $teks = $soal['pilihan_'.$h]??'';
              if($teks==='') continue;
              $dipilih = in_array($h, $jwbMcmaArr);
            ?>
            <div class="pilihan-item <?= $dipilih?'mcma-selected':'' ?>"
                 onclick="pilihMcma('<?= $h ?>',this,<?= $soalId ?>)">
              <div class="huruf-box"><?= strtoupper($h) ?></div>
              <div class="pilihan-teks"><?= e($teks) ?></div>
              <div style="margin-left:auto;flex-shrink:0">
                <div class="mcma-check" style="width:20px;height:20px;border-radius:4px;border:2px solid <?= $dipilih?'#7c3aed':'#cbd5e1' ?>;background:<?= $dipilih?'#7c3aed':'transparent' ?>;display:flex;align-items:center;justify-content:center">
                  <?php if($dipilih): ?><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg><?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <input type="hidden" id="mcmaValue" value="<?= e($jwbAktif??'') ?>">

          <?php else: ?>
            <?php
            // Cek apakah ada kolom gambar_pilihan (DB mungkin belum di-migrate)
            $adaGambarPilihan = isset($soal['gambar_pilihan_a']);
            // Tentukan urutan pilihan — pakai mapping acak jika aktif
            $pilihanLoop = $pilihanMapping
                ? array_keys($pilihanMapping)
                : ['a','b','c','d'];
            foreach ($pilihanLoop as $hTampil):
                $hAsli    = $pilihanMapping ? $pilihanMapping[$hTampil] : $hTampil;
                $teks     = $soal['pilihan_'.$hAsli] ?? '';
                $gambarP  = $adaGambarPilihan ? ($soal['gambar_pilihan_'.$hAsli] ?? '') : '';
                if ($teks==='' && $gambarP==='') continue;
            ?>
            <div class="pilihan-item <?= $jwbAktif===$hAsli?'selected':'' ?>"
                 onclick="pilihJawaban('<?= $hAsli ?>',this,<?= $soalId ?>)">
              <div class="huruf-box"><?= strtoupper($hTampil) ?></div>
              <div class="pilihan-teks">
                <?php if ($gambarP): ?>
                <img src="<?= BASE_URL ?>/assets/uploads/soal/<?= e($gambarP) ?>"
                     style="max-width:180px;max-height:100px;border-radius:6px;display:block;margin-bottom:4px"
                     alt="Gambar pilihan <?= strtoupper($hTampil) ?>">
                <?php endif; ?>
                <?= e($teks) ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          </div>
        </div>

      </div><!-- /soal-card-body -->

      <!-- Footer navigasi -->
      <div class="soal-card-foot">
        <button class="btn-nav btn-prev" onclick="pindahSoal(<?= $noAktif-1 ?>)" <?= $noAktif<=1?'disabled':'' ?>>
          <i class="bi bi-chevron-left"></i> Sebelumnya
        </button>
        <button class="btn-ragu <?= $isRagu?'aktif':'' ?>" id="btnRagu" onclick="toggleRagu(<?= $soalId ?>)">
          <i class="bi bi-flag-fill"></i> <?= $isRagu?'Hapus Ragu':'Ragu-ragu' ?>
        </button>
        <?php if ($noAktif < $totalSoal): ?>
        <button class="btn-nav btn-next" onclick="pindahSoal(<?= $noAktif+1 ?>)">
          Selanjutnya <i class="bi bi-chevron-right"></i>
        </button>
        <?php else: ?>
        <button class="btn-nav btn-next" style="background:#10b981" onclick="bukaModalSelesai()">
          <i class="bi bi-check-circle"></i> Selesai
        </button>
        <?php endif; ?>
      </div>

    </div><!-- /soal-card -->
  </div>

  <div class="side-panel">
    <div class="panel-card">
      <div class="panel-title">Navigasi Soal</div>
      <div class="nav-grid">
      <?php foreach ($soalList as $idx => $s):
        $n = $idx+1; $sid = $s['id'];
        $cls = '';
        if ($n===$noAktif)            $cls .= ' current';
        if (isset($jawabans[$sid]))   $cls .= ' answered';
        if (in_array($sid,$raguList)) $cls .= ' ragu';
      ?>
      <button class="nav-btn<?= $cls ?>" id="navbtn-<?= $n ?>" onclick="pindahSoal(<?= $n ?>)"><?= $n ?></button>
      <?php endforeach; ?>
      </div>

      <div class="legend">
        <span class="leg-dot" style="background:#1a56db"></span>Dijawab
        <span class="leg-dot" style="background:#f59e0b;margin-left:6px"></span>Ragu
        <span class="leg-dot" style="background:#f8fafc;border:1.5px solid #e2e8f0;margin-left:6px"></span>Belum
      </div>

      <div class="progress-info">
        <span style="color:#1a56db;font-weight:700" id="countDijawab"><?= $sdhJawab ?></span> dijawab &nbsp;&middot;&nbsp;
        <span style="color:#f59e0b;font-weight:700" id="countRagu"><?= $jumlahRagu ?></span> ragu-ragu &nbsp;&middot;&nbsp;
        <span style="color:#ef4444;font-weight:700" id="countBelum"><?= $belumJawab ?></span> belum
      </div>

      <button class="btn-submit-side" onclick="bukaModalSelesai()">
        <i class="bi bi-send me-2"></i>Selesai &amp; Kirim
      </button>
    </div>
  </div>

</div>

<!-- Modal Konfirmasi -->
<div class="modal fade" id="modalSelesai" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-send-check me-2 text-success"></i>Kirim Jawaban?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-3">
        <div class="d-flex justify-content-center gap-4 mb-3">
          <div><div style="font-size:32px;font-weight:900;color:#1a56db" id="mDijawab"><?= $sdhJawab ?></div><div style="font-size:12px;color:#94a3b8">Dijawab</div></div>
          <div><div style="font-size:32px;font-weight:900;color:#f59e0b" id="mRagu"><?= $jumlahRagu ?></div><div style="font-size:12px;color:#94a3b8">Ragu-ragu</div></div>
          <div><div style="font-size:32px;font-weight:900;color:#ef4444" id="mBelum"><?= $belumJawab ?></div><div style="font-size:12px;color:#94a3b8">Belum</div></div>
        </div>
        <p class="text-muted mb-0" style="font-size:13px">Jawaban yang sudah dikirim <strong>tidak dapat diubah</strong>.</p>
      </div>
      <div class="modal-footer border-0 justify-content-center gap-2 pt-0">
        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="bi bi-arrow-left me-1"></i>Kembali Periksa</button>
        <form method="POST" action="<?= BASE_URL ?>/ujian/submit.php">
          <input type="hidden" name="confirm" value="1">
          <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-send me-1"></i>Ya, Kirim Sekarang</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Overlay Start - minta klik dulu untuk masuk fullscreen -->
<div id="fsStart" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.98);z-index:99998;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px">
  <div style="font-size:56px;margin-bottom:16px">🔒</div>
  <h2 id="fsTitleText" style="color:#fff;font-size:22px;font-weight:900;margin-bottom:8px">Mode Ujian Penuh</h2>
  <p id="fsInfoText" style="color:#94a3b8;font-size:15px;margin-bottom:6px;max-width:380px">Ujian akan berjalan dalam mode layar penuh.</p>
  <p style="color:#f59e0b;font-size:13px;font-weight:700;margin-bottom:28px">Dilarang berpindah tab atau keluar layar penuh selama ujian.</p>
  <button id="btnMulaiFs" style="background:#1a56db;color:#fff;border:none;border-radius:12px;padding:14px 40px;font-size:16px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:8px;margin:0 auto">
    🚀 Mulai Ujian
  </button>
  <p style="color:#475569;font-size:12px;margin-top:20px">Klik tombol di atas untuk memulai</p>
</div>

<!-- Overlay Peringatan Keluar Fullscreen -->
<div id="fsOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.97);z-index:99999;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px">
  <div style="font-size:60px;margin-bottom:16px">⚠️</div>
  <h2 style="color:#fff;font-size:22px;font-weight:900;margin-bottom:8px">Anda Keluar dari Mode Ujian!</h2>
  <p style="color:#94a3b8;font-size:15px;margin-bottom:8px">Dilarang berpindah tab atau keluar layar penuh saat ujian berlangsung.</p>
  <p style="color:#f59e0b;font-size:13px;font-weight:700;margin-bottom:24px" id="fsHitung">Kembali dalam 5 detik...</p>
  <button onclick="masuKembali()" style="background:#1a56db;color:#fff;border:none;border-radius:10px;padding:12px 32px;font-size:15px;font-weight:800;cursor:pointer">
    🔒 Kembali ke Ujian
  </button>
  <p style="color:#ef4444;font-size:12px;margin-top:16px" id="fsWarning"></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL  = '<?= BASE_URL ?>';
const soalId    = <?= $soalId ?>;
const noAktif   = <?= $noAktif ?>;
const totalSoal = <?= $totalSoal ?>;
let dijawab     = <?= $sdhJawab ?>;
let jumlahRagu  = <?= $jumlahRagu ?>;
let sisaDetik   = <?= $sisaDetik ?>;
let sdh         = <?= $jwbAktif ? 'true' : 'false' ?>;

const timerBox = document.getElementById('timerBox');
function updateTimer(){
  if(sisaDetik<=0){timerBox.textContent='00:00';timerBox.classList.add('warning');window.location.href=BASE_URL+'/ujian/submit.php?auto=1';return;}
  const m=Math.floor(sisaDetik/60),s=sisaDetik%60;
  timerBox.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  if(sisaDetik<=300) timerBox.classList.add('warning');
  sisaDetik--;
}
setInterval(updateTimer,1000); updateTimer();

function pilihJawaban(val,el,sid){
  document.querySelectorAll('.pilihan-item').forEach(p=>p.classList.remove('selected'));
  el.classList.add('selected');
  // sid = soalId dari parameter HTML langsung (tidak bergantung variabel global)
  const _soalId  = sid || soalId;
  const _noAktif = noAktif;
  fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`soal_id=${_soalId}&jawaban=${val}`})
  .then(r=>r.json()).then(d=>{
    if(d.expired){ window.location.href=BASE_URL+'/ujian/submit.php?auto=1'; return; }
    if(d.ok){
      const nb=document.getElementById('navbtn-'+_noAktif);
      if(nb){ nb.classList.add('answered'); nb.classList.remove('current'); }
      if(!sdh){sdh=true;dijawab++;updateStat();}
    }
  }).catch(()=>{});
}

// ── MCMA: pilih/batal beberapa jawaban ───────────────────────
let mcmaSelected = (document.getElementById('mcmaValue')?.value || '').split(',').filter(v=>v!=='');

function pilihMcma(huruf, el, sid) {
    const idx = mcmaSelected.indexOf(huruf);
    const check = el.querySelector('.mcma-check');

    if (idx === -1) {
        // Tambah pilihan
        mcmaSelected.push(huruf);
        el.classList.add('mcma-selected');
        el.querySelector('.huruf-box').style.background = '#7c3aed';
        el.querySelector('.huruf-box').style.borderColor = '#7c3aed';
        el.querySelector('.huruf-box').style.color = '#fff';
        if (check) {
            check.style.background = '#7c3aed';
            check.style.borderColor = '#7c3aed';
            check.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
        }
    } else {
        // Hapus pilihan
        mcmaSelected.splice(idx, 1);
        el.classList.remove('mcma-selected');
        el.querySelector('.huruf-box').style.background = '';
        el.querySelector('.huruf-box').style.borderColor = '';
        el.querySelector('.huruf-box').style.color = '';
        if (check) {
            check.style.background = 'transparent';
            check.style.borderColor = '#cbd5e1';
            check.innerHTML = '';
        }
    }

    // Sort dan simpan
    mcmaSelected.sort();
    const jwbStr = mcmaSelected.join(',');
    if (document.getElementById('mcmaValue')) document.getElementById('mcmaValue').value = jwbStr;

    // Kirim ke server — gunakan sid dari parameter HTML
    if (mcmaSelected.length > 0) {
        const _soalId  = sid || soalId;
        const _noAktif = noAktif;
        fetch(BASE_URL+'/ujian/ajax_jawab.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `soal_id=${sid||_soalId}&jawaban=${encodeURIComponent(jwbStr)}&tipe=mcma`
        })
        .then(r=>r.json())
        .then(d=>{
            if(d.expired){ window.location.href=BASE_URL+'/ujian/submit.php?auto=1'; return; }
            if(d.ok){
                const nb=document.getElementById('navbtn-'+_noAktif);
                if(nb){ nb.classList.add('answered'); nb.classList.remove('current'); }
                if(!sdh){sdh=true;dijawab++;updateStat();}
            }
        }).catch(()=>{});
    }
}

function updateStat(){
  const belum=totalSoal-dijawab;
  ['countBelum','mBelum'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=belum;});
  ['countDijawab','mDijawab'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=dijawab;});
  ['countRagu','mRagu'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=jumlahRagu;});
}

function toggleRagu(sid){
  fetch(BASE_URL+'/ujian/ajax_ragu.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`soal_id=${sid}`})
  .then(r=>r.json()).then(d=>{
    const btn=document.getElementById('btnRagu');
    const nb=document.getElementById('navbtn-'+noAktif);
    // Update badge ragu di header
    let badge=document.querySelector('.ragu-badge');
    if(d.ragu){
      btn.classList.add('aktif');btn.innerHTML='<i class="bi bi-flag-fill"></i> Hapus Ragu';
      if(nb)nb.classList.add('ragu');
      jumlahRagu++;
      if(!badge){badge=document.createElement('span');badge.className='ragu-badge';badge.textContent='⚠ Ragu-ragu';document.querySelector('[style*="justify-content:space-between"]').appendChild(badge);}
    }else{
      btn.classList.remove('aktif');btn.innerHTML='<i class="bi bi-flag-fill"></i> Ragu-ragu';
      if(nb)nb.classList.remove('ragu');
      jumlahRagu=Math.max(0,jumlahRagu-1);
      if(badge)badge.remove();
    }
    updateStat();
  }).catch(()=>{});
}

function pindahSoal(no) {
    if (no < 1 || no > totalSoal) return;

    // Tandai tombol nav aktif sementara
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('current'));
    const nb = document.getElementById('navbtn-' + no);
    if (nb) nb.classList.add('current');

    fetch(BASE_URL + '/ujian/ajax_soal.php?no=' + no)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { window.location.href = BASE_URL + '/ujian/soal.php?no=' + no; return; }

            // Update variabel global PERTAMA — sebelum render HTML
            // agar onclick="pilihJawaban(...)" di HTML baru langsung pakai soalId yang benar
            window.noAktif = d.no;
            window.soalId  = d.soalId;
            sdh = !!d.jwbAktif;
            mcmaSelected = (d.jwbAktif && d.tipe === 'mcma')
                ? d.jwbAktif.split(',').filter(v => v !== '')
                : [];

            // Baru render konten soal
            document.querySelector('.soal-badge').innerHTML = '<i class="bi bi-question-circle"></i> Soal ' + d.no + ' dari ' + d.total;
            document.querySelector('.soal-text').innerHTML  = d.pertanyaan;
            document.getElementById('pilihanWrap').innerHTML = d.pilihanHtml;

            // Teks bacaan & gambar
            const tbEl = document.getElementById('teksBacaanWrap');
            if (tbEl) tbEl.innerHTML = d.teksBacaan + d.gambar;

            // Badge ragu
            const raguBadge = document.querySelector('.ragu-badge');
            if (d.isRagu) {
                if (!raguBadge) {
                    const bd = document.createElement('span');
                    bd.className = 'ragu-badge';
                    bd.textContent = '⚠ Ragu-ragu';
                    document.querySelector('.soal-badge').after(bd);
                }
            } else {
                if (raguBadge) raguBadge.remove();
            }

            // Tombol ragu
            const btnRagu = document.getElementById('btnRagu');
            if (btnRagu) {
                btnRagu.className = 'btn-ragu' + (d.isRagu ? ' aktif' : '');
                btnRagu.innerHTML = '<i class="bi bi-flag-fill"></i> ' + (d.isRagu ? 'Hapus Ragu' : 'Ragu-ragu');
                btnRagu.onclick = () => toggleRagu(d.soalId);
            }

            // Tombol prev/next
            const btnPrev = document.querySelector('.btn-prev');
            const btnNext = document.querySelector('.btn-next');
            if (btnPrev) btnPrev.disabled = d.no <= 1;
            if (btnPrev) btnPrev.onclick = () => pindahSoal(d.no - 1);
            if (btnNext) {
                if (d.no < d.total) {
                    btnNext.innerHTML = 'Selanjutnya <i class="bi bi-chevron-right"></i>';
                    btnNext.style.background = '';
                    btnNext.onclick = () => pindahSoal(d.no + 1);
                } else {
                    btnNext.innerHTML = '<i class="bi bi-check-circle"></i> Selesai';
                    btnNext.style.background = '#10b981';
                    btnNext.onclick = bukaModalSelesai;
                }
            }

            // Update stats
            dijawab    = d.sdhJawab;
            jumlahRagu = d.jumlahRagu;
            updateStat();

            // Update nav buttons
            d.navBtns.forEach(btn => {
                const el = document.getElementById('navbtn-' + btn.n);
                if (el) el.className = 'nav-btn' + (btn.cls ? ' ' + btn.cls : '');
            });

            // Update URL tanpa reload
            history.replaceState(null, '', BASE_URL + '/ujian/soal.php?no=' + d.no);
        })
        .catch(() => {
            // Fallback: reload biasa jika AJAX gagal
            window.location.href = BASE_URL + '/ujian/soal.php?no=' + no;
        });
}
function bukaModalSelesai(){new bootstrap.Modal(document.getElementById('modalSelesai')).show();}
setInterval(()=>{fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'ping=1'}).catch(()=>{});},60000);

// ── Fullscreen & Anti Pindah Tab ──────────────────────────────
let pelanggaranCount = 0;
let fsHitungInterval = null;
let fsSedangReload   = true; // block event saat page baru load / pindah soal
const fsOverlay = document.getElementById('fsOverlay');
const fsWarning = document.getElementById('fsWarning');
const fsHitung  = document.getElementById('fsHitung');

// Helper: cek apakah sedang fullscreen
function isFullscreen() {
    return !!(document.fullscreenElement
        || document.webkitFullscreenElement
        || document.mozFullScreenElement
        || document.msFullscreenElement);
}

// Masuk fullscreen — tangkap error Promise
function masukFullscreen() {
    const el = document.documentElement;
    let p;
    if      (el.requestFullscreen)       p = el.requestFullscreen();
    else if (el.webkitRequestFullscreen) p = el.webkitRequestFullscreen();
    else if (el.mozRequestFullScreen)    p = el.mozRequestFullScreen();
    else if (el.msRequestFullscreen)     p = el.msRequestFullscreen();
    if (p && typeof p.catch === 'function') p.catch(() => {});
}

// Tampilkan overlay peringatan + hitung mundur
function tampilkanPeringatan() {
    if (document.getElementById('fsStart').style.display === 'flex') return;
    if (fsOverlay.style.display === 'flex') return;

    pelanggaranCount++;
    
    // Kirim ke server
    fetch(BASE_URL + '/ujian/ajax_jawab.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'violation=1'
    }).catch(() => {});

    fsOverlay.style.display = 'flex';
    fsWarning.textContent = '⚠ Pelanggaran dicatat oleh sistem!';

    let hitung = 5;
    fsHitung.textContent = 'Kembali dalam ' + hitung + ' detik...';
    if (fsHitungInterval) clearInterval(fsHitungInterval);
    fsHitungInterval = setInterval(() => {
        hitung--;
        fsHitung.textContent = 'Kembali dalam ' + hitung + ' detik...';
        if (hitung <= 0) { clearInterval(fsHitungInterval); masuKembali(); }
    }, 1000);
}

function masuKembali() {
    if (fsHitungInterval) clearInterval(fsHitungInterval);
    fsOverlay.style.display = 'none';
    masukFullscreen();
}

// Deteksi keluar fullscreen — hanya jika ujian sudah dimulai & bukan sedang reload
function cekFullscreen() {
    if (fsSedangReload) return;
    if (!sessionStorage.getItem('fs_started')) return;
    if (!isFullscreen()) tampilkanPeringatan();
}
document.addEventListener('fullscreenchange',       cekFullscreen);
document.addEventListener('webkitfullscreenchange', cekFullscreen);
document.addEventListener('mozfullscreenchange',    cekFullscreen);
document.addEventListener('MSFullscreenChange',     cekFullscreen);

// Deteksi pindah tab / minimize
document.addEventListener('visibilitychange', () => {
    if (fsSedangReload) return;
    if (!sessionStorage.getItem('fs_started')) return;
    if (document.hidden) tampilkanPeringatan();
});

// Deteksi blur window — delay lebih panjang hindari false positive
window.addEventListener('blur', () => {
    if (fsSedangReload) return;
    if (!sessionStorage.getItem('fs_started')) return;
    setTimeout(() => {
        if (!document.hasFocus() && !isFullscreen()) tampilkanPeringatan();
    }, 500);
});

// Blokir klik kanan
document.addEventListener('contextmenu', e => e.preventDefault());

// Blokir shortcut berbahaya
document.addEventListener('keydown', e => {
    const key = e.key.toLowerCase();
    if (e.altKey && (key === 'tab' || key === 'f4'))                             { e.preventDefault(); tampilkanPeringatan(); }
    if (e.ctrlKey && (key === 'w' || key === 't' || key === 'n' || key === 'r')) { e.preventDefault(); }
    if (key === 'escape') { e.preventDefault(); if (sessionStorage.getItem('fs_started') && !isFullscreen()) masukFullscreen(); }
    if (key === 'f11')    { e.preventDefault(); masukFullscreen(); }
});

// Inisialisasi saat load
window.addEventListener('load', () => {
    const sudahMulai = sessionStorage.getItem('fs_started');
    const overlay    = document.getElementById('fsStart');
    const btnMulai   = document.getElementById('btnMulaiFs');
    const judulFs    = document.getElementById('fsTitleText');
    const infoFs     = document.getElementById('fsInfoText');

    if (!sudahMulai) {
        // Belum pernah mulai — tampilkan overlay normal
        fsSedangReload = false;
        overlay.style.display = 'flex';
    } else {
        // Halaman di-refresh / buka ulang manual (bukan via AJAX pindah soal)
        // requestFullscreen() HARUS dari user gesture — tampilkan tombol klik ulang
        fsSedangReload = false;
        if (judulFs) judulFs.textContent = 'Lanjutkan Ujian';
        if (infoFs)  infoFs.textContent  = 'Klik tombol di bawah untuk kembali ke mode layar penuh.';
        if (btnMulai) btnMulai.innerHTML = '🔒 Masuk Layar Penuh';
        overlay.style.display = 'flex';
    }
});

document.getElementById('btnMulaiFs').addEventListener('click', () => {
    sessionStorage.setItem('fs_started', '1');
    document.getElementById('fsStart').style.display = 'none';
    fsSedangReload = true;
    masukFullscreen();
    setTimeout(() => { fsSedangReload = false; }, 1500);
});
</script>
</body>
</html>
