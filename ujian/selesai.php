<?php
// ujian/selesai.php — Halaman setelah ujian selesai + pembahasan dropdown
if (session_status() === PHP_SESSION_NONE) { session_name('TKA_PESERTA'); session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

$nama    = $_SESSION['peserta_nama']    ?? 'Peserta';
$kelas   = $_SESSION['peserta_kelas']   ?? '';
$sekolah = $_SESSION['peserta_sekolah'] ?? '';
$benar   = (int)($_SESSION['hasil_benar'] ?? 0);
$nilai   = (float)($_SESSION['hasil_nilai'] ?? 0);
$total   = (int)($_SESSION['hasil_total'] ?? 0);
$detail  = $_SESSION['hasil_detail'] ?? [];
$namaAplikasi     = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$tampilPembahasan = getSetting($conn, 'tampil_pembahasan', '1');

// ── Ambil peringkat sementara sebelum session dihapus ────────
$peringkat    = null;
$totalPeserta = null;
$mapelNama    = null;
if (!empty($_SESSION['ujian_id'])) {
    $ujianId = (int)$_SESSION['ujian_id'];
    // Ambil jadwal_id dan kategori dari sesi ujian ini
    $_qUjSelesai = $conn->query("SELECT jadwal_id, kategori_id FROM ujian WHERE id=$ujianId LIMIT 1");
    $ujianRow = ($_qUjSelesai && $_qUjSelesai->num_rows > 0) ? $_qUjSelesai->fetch_assoc() : null;
    $jadwalId = $ujianRow['jadwal_id'] ?? null;
    $katId    = $ujianRow['kategori_id'] ?? null;

    if ($jadwalId) {
        // Hitung peringkat: berapa peserta di jadwal yang sama dengan nilai >= nilai saya
        $nilaiSaya = (float)($_SESSION['hasil_nilai'] ?? 0);
        $q = "SELECT COUNT(*) AS c FROM hasil_ujian WHERE jadwal_id=$jadwalId AND nilai > $nilaiSaya";
        $_qRank = $conn->query($q); $rankAbove = ($_qRank && $_qRank->num_rows > 0) ? (int)$_qRank->fetch_assoc()['c'] : 0;
        $peringkat = $rankAbove + 1;

        // Hitung total peserta yang sudah selesai di jadwal ini
        $_rTp = $conn->query("SELECT COUNT(*) AS c FROM hasil_ujian WHERE jadwal_id=$jadwalId");
        $totalPeserta = $_rTp ? (int)($_rTp->fetch_assoc()['c'] ?? 0) : 0;
        if ($_rTp) $_rTp->free();

        // Nama mapel
        $_rMp = $conn->query(
            "SELECT k.nama_kategori FROM jadwal_ujian j
             LEFT JOIN kategori_soal k ON k.id = COALESCE($katId, j.kategori_id)
             WHERE j.id=$jadwalId LIMIT 1"
        );
        $mapelRow  = ($_rMp && $_rMp->num_rows > 0) ? $_rMp->fetch_assoc() : [];
        if ($_rMp) $_rMp->free();
        $mapelNama = $mapelRow['nama_kategori'] ?? null;
    }
}

[$predikat, $keterangan, $badgeClass] = match(true) {
    $nilai >= 90 => ['A','Istimewa','success'],
    $nilai >= 80 => ['B','Sangat Baik','success'],
    $nilai >= 70 => ['C','Baik','info'],
    $nilai >= 60 => ['D','Cukup','warning'],
    default      => ['E','Perlu Bimbingan','danger'],
};

// BUG FIX #6: session_destroy dipindah ke akhir file (setelah seluruh HTML selesai dirender).
// Jangan hapus session di sini — data masih dibutuhkan jika ada error saat render.

function labelJwb($soal, $kode) {
    if (!$kode) return '<span class="text-muted fst-italic">Tidak dijawab</span>';
    if ($soal['tipe_soal'] === 'bs') return $kode === 'benar' ? 'BENAR' : 'SALAH';
    $map = ['a'=>'A','b'=>'B','c'=>'C','d'=>'D'];
    return implode(', ', array_map(fn($k)=>$map[$k]??strtoupper($k), explode(',', $kode)));
}
function cekBenar($soal, $jawaban) {
    if (!$jawaban) return false;
    $a = explode(',', strtolower($jawaban)); $b = explode(',', strtolower($soal['jawaban_benar']));
    sort($a); sort($b);
    return $a === $b;
}
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ujian Selesai — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;padding:24px 16px 60px}
.wrap{max-width:860px;margin:0 auto}
.card-hasil{border:none;border-radius:18px;box-shadow:0 4px 24px rgba(0,0,0,.10);overflow:hidden;margin-bottom:24px}
.hdr{background:linear-gradient(135deg,#1a56db,#1e40af);padding:28px 24px;text-align:center;color:#fff}
.hdr h3{font-weight:800;margin:0 0 4px;font-size:22px}
.hdr p{opacity:.85;margin:2px 0;font-size:13px}
.nilai-besar{font-size:80px;font-weight:900;line-height:1}
.badge-pred{font-size:16px;padding:8px 20px;border-radius:20px}
.stat-row{display:flex;justify-content:space-around;padding:16px 0;border-bottom:1px solid #f1f5f9}
.stat-item{text-align:center}
.stat-num{font-size:28px;font-weight:800;color:#1e293b}
.stat-lbl{font-size:12px;color:#94a3b8;margin-top:2px}
.section-head{font-size:17px;font-weight:800;color:#1e293b;margin-bottom:16px;display:flex;align-items:center;gap:8px}

/* Accordion soal */
.acc-item{background:#fff;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:10px;overflow:hidden}
.acc-btn{width:100%;display:flex;align-items:center;gap:10px;padding:14px 18px;background:none;border:none;cursor:pointer;text-align:left;transition:background .15s}
.acc-btn:hover{background:#f8fafc}
.soal-no{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;font-weight:800;font-size:13px;flex-shrink:0}
.soal-no.benar{background:#dcfce7;color:#166534}
.soal-no.salah{background:#fee2e2;color:#991b1b}
.soal-no.skip{background:#f1f5f9;color:#64748b}
.acc-soal-text{flex:1;font-size:14px;color:#1e293b;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:600px}
.acc-badge{flex-shrink:0;font-size:11px;padding:3px 10px;border-radius:12px;font-weight:700}
.acc-badge.benar{background:#dcfce7;color:#166534}
.acc-badge.salah{background:#fee2e2;color:#991b1b}
.acc-badge.skip{background:#f1f5f9;color:#64748b}
.acc-arrow{flex-shrink:0;font-size:14px;color:#94a3b8;transition:transform .25s}
.acc-arrow.open{transform:rotate(180deg)}
.acc-body{display:none;padding:0 18px 18px;border-top:1px solid #f1f5f9}
.acc-body.show{display:block}
.pertanyaan{font-size:15px;color:#1e293b;line-height:1.7;margin:14px 0 12px}
.pilihan-list{list-style:none;padding:0;margin:0 0 12px;display:flex;flex-direction:column;gap:6px}
.pilihan-list li{display:flex;align-items:flex-start;gap:8px;padding:8px 12px;border-radius:8px;font-size:13.5px;border:1px solid #e2e8f0}
.pilihan-list li.kunci{background:#dcfce7;border-color:#86efac;font-weight:700;color:#166534}
.pilihan-list li.salah-pilih{background:#fee2e2;border-color:#fca5a5;color:#991b1b}
.pilihan-huruf{font-weight:800;flex-shrink:0;min-width:20px}
.jwb-row{display:flex;align-items:center;gap:10px;font-size:13px;flex-wrap:wrap;margin-bottom:10px}
.pembahasan-box{background:#eff6ff;border-left:4px solid #3b82f6;border-radius:0 8px 8px 0;padding:12px 16px;font-size:13.5px;color:#1e293b;line-height:1.7;margin-top:10px}
.pb-label{font-weight:800;color:#1a56db;margin-bottom:6px;font-size:11px;text-transform:uppercase;letter-spacing:.5px}
.btn-expand-all{font-size:13px;padding:6px 14px;border-radius:8px}
</style></head><body>
<div class="wrap">

  <!-- Kartu Hasil -->
  <div class="card-hasil">
    <div class="hdr">
      <div style="font-size:50px;margin-bottom:10px">🎉</div>
      <h3>Ujian Selesai!</h3>
      <p><?= e($nama) ?> · <?= e($kelas) ?></p>
      <p><?= e($sekolah) ?></p>
    </div>
    <div class="card-body text-center py-4">
      <div class="mb-2 text-muted" style="font-size:13px">Nilai Anda</div>
      <div class="nilai-besar text-primary mb-2"><?= number_format($nilai,0) ?></div>
      <span class="badge bg-<?= $badgeClass ?> badge-pred">Predikat <?= $predikat ?> — <?= $keterangan ?></span>
      <div class="stat-row mt-4">
        <div class="stat-item"><div class="stat-num text-success"><?= $benar ?></div><div class="stat-lbl">Jawaban Benar</div></div>
        <div class="stat-item"><div class="stat-num text-danger"><?= $total - $benar ?></div><div class="stat-lbl">Jawaban Salah</div></div>
        <div class="stat-item"><div class="stat-num"><?= $total ?></div><div class="stat-lbl">Total Soal</div></div>
      </div>
      <div class="alert alert-info mt-4 mb-2 text-start" style="font-size:13px">
        <i class="bi bi-info-circle me-2"></i>Hasil ujian Anda telah tersimpan. Silakan kembalikan komputer ke posisi semula dan tunggu instruksi pengawas.
      </div>

      <!-- Tombol aksi -->
      <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
        <?php
        $cetakPid = (int)($_SESSION['peserta_id'] ?? 0);
        $cetakJid = (int)($jadwalId ?? 0);
        ?>
        <a href="<?= BASE_URL ?>/ujian/cetak_hasil_peserta.php?peserta_id=<?= $cetakPid ?>&jadwal_id=<?= $cetakJid ?>" target="_blank"
           class="btn btn-outline-primary btn-sm px-4">
          <i class="bi bi-printer me-1"></i>Cetak Laporan
        </a>
        <?php
        // Tombol Share WA
        $waEnabled = getSetting($conn, 'wa_share_hasil', '1');
        if ($waEnabled !== '0'):
            $waText = urlencode(
                "🎓 *{$nama}* telah menyelesaikan ujian!\n" .
                "📚 Mapel: " . ($mapelNama ?? 'Umum') . "\n" .
                "📊 Nilai: *" . number_format($nilai, 0) . "* (Predikat {$predikat} — {$keterangan})\n" .
                "✅ Benar: {$benar} dari {$total} soal\n" .
                "🏫 {$sekolah} · Kelas {$kelas}"
            );
        ?>
        <a href="https://wa.me/?text=<?= $waText ?>" target="_blank"
           class="btn btn-success btn-sm px-4">
          <i class="bi bi-whatsapp me-1"></i>Bagikan ke WA
        </a>
        <?php endif; ?>
      </div>

      <?php if ($peringkat !== null && $totalPeserta !== null): ?>
      <div class="mt-3 p-3 rounded-3" style="background:#f0fdf4;border:1.5px solid #86efac">
        <div style="font-size:12px;color:#166534;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
          <i class="bi bi-trophy-fill me-1" style="color:#f59e0b"></i>
          Peringkat Sementara<?= $mapelNama ? ' — ' . htmlspecialchars($mapelNama) : '' ?>
        </div>
        <div style="font-size:36px;font-weight:900;color:#15803d;line-height:1">
          #<?= $peringkat ?>
        </div>
        <div style="font-size:13px;color:#166534;margin-top:4px">
          dari <strong><?= $totalPeserta ?></strong> peserta yang sudah selesai
        </div>
        <?php if ($peringkat === 1 && $totalPeserta >= 1): ?>
        <div style="font-size:12px;color:#854d0e;margin-top:6px">
          🥇 Nilai tertinggi saat ini!
        </div>
        <?php endif; ?>
        <div style="font-size:11px;color:#4ade80;margin-top:4px">* Peringkat dapat berubah setelah semua peserta selesai</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($tampilPembahasan === '1' && !empty($detail)): ?>
  <!-- Header Pembahasan -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="section-head mb-0">
      <i class="bi bi-journal-text text-primary" style="font-size:20px"></i> Pembahasan Soal
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-success btn-sm btn-expand-all" onclick="bukaSemuaSoal()">
        <i class="bi bi-chevron-double-down me-1"></i>Buka Semua
      </button>
      <button class="btn btn-outline-secondary btn-sm btn-expand-all" onclick="tutupSemuaSoal()">
        <i class="bi bi-chevron-double-up me-1"></i>Tutup Semua
      </button>
    </div>
  </div>

  <?php foreach ($detail as $i => $s):
    $jwbSiswa  = $s['jawaban_siswa'] ?? null;
    $benarSoal = cekBenar($s, $jwbSiswa);
    $skip      = !$jwbSiswa;
    $noKls     = $skip ? 'skip' : ($benarSoal ? 'benar' : 'salah');
    $statusLabel = $skip ? 'Tidak Dijawab' : ($benarSoal ? 'Benar' : 'Salah');
    $kunciArr  = array_map('trim', explode(',', strtolower($s['jawaban_benar'])));
    $siswaArr  = $jwbSiswa ? array_map('trim', explode(',', strtolower($jwbSiswa))) : [];
    $singkatSoal = mb_substr(strip_tags($s['pertanyaan']), 0, 80) . (mb_strlen($s['pertanyaan']) > 80 ? '...' : '');
  ?>
  <div class="acc-item" id="acc-<?= $i ?>">
    <!-- Header accordion -->
    <button class="acc-btn" onclick="toggleAcc(<?= $i ?>)">
      <span class="soal-no <?= $noKls ?>"><?= $i+1 ?></span>
      <span class="acc-soal-text"><?= e($singkatSoal) ?></span>
      <span class="acc-badge <?= $noKls ?>"><?= $statusLabel ?></span>
      <i class="bi bi-chevron-down acc-arrow" id="arrow-<?= $i ?>"></i>
    </button>

    <!-- Body accordion -->
    <div class="acc-body" id="body-<?= $i ?>">
      <p class="pertanyaan"><?= nl2br(e($s['pertanyaan'])) ?></p>

      <?php if ($s['tipe_soal'] === 'bs'): ?>
      <ul class="pilihan-list">
        <?php foreach (['benar'=>'BENAR','salah'=>'SALAH'] as $val=>$lbl):
          $isKunci = in_array($val,$kunciArr); $dipilih = in_array($val,$siswaArr);
          $cls = $isKunci ? 'kunci' : ($dipilih ? 'salah-pilih' : '');
        ?>
        <li class="<?= $cls ?>">
          <?php if ($isKunci): ?><i class="bi bi-check-circle-fill text-success"></i>
          <?php elseif ($dipilih): ?><i class="bi bi-x-circle-fill text-danger"></i>
          <?php else: ?><i class="bi bi-circle text-muted"></i><?php endif; ?>
          <?= $lbl ?>
          <?php if ($isKunci): ?><small class="text-success ms-1">(Kunci)</small><?php endif; ?>
          <?php if ($dipilih && !$isKunci): ?><small class="text-danger ms-1">(Jawaban Anda)</small><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <ul class="pilihan-list">
        <?php foreach (['a'=>$s['pilihan_a'],'b'=>$s['pilihan_b'],'c'=>$s['pilihan_c'],'d'=>$s['pilihan_d']] as $huruf=>$isi):
          if (!$isi) continue;
          $isKunci = in_array($huruf,$kunciArr); $dipilih = in_array($huruf,$siswaArr);
          $cls = $isKunci ? 'kunci' : ($dipilih ? 'salah-pilih' : '');
        ?>
        <li class="<?= $cls ?>">
          <span class="pilihan-huruf"><?= strtoupper($huruf) ?>.</span>
          <span class="flex-grow-1"><?= e($isi) ?></span>
          <?php if ($isKunci): ?><i class="bi bi-check-circle-fill text-success"></i><?php endif; ?>
          <?php if ($dipilih && !$isKunci): ?><i class="bi bi-x-circle-fill text-danger"></i><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <!-- Status jawaban -->
      <div class="jwb-row">
        <span class="fw-semibold" style="font-size:13px">Jawaban Anda:</span>
        <?php if ($skip): ?>
          <span class="badge bg-secondary">Tidak Dijawab</span>
        <?php elseif ($benarSoal): ?>
          <span class="badge bg-success"><i class="bi bi-check me-1"></i><?= labelJwb($s,$jwbSiswa) ?> — Benar</span>
        <?php else: ?>
          <span class="badge bg-danger"><i class="bi bi-x me-1"></i><?= labelJwb($s,$jwbSiswa) ?> — Salah</span>
          <span class="text-muted" style="font-size:12px">Kunci: <strong class="text-success"><?= labelJwb($s,$s['jawaban_benar']) ?></strong></span>
        <?php endif; ?>
      </div>

      <?php if (!empty(trim($s['pembahasan'] ?? ''))): ?>
      <div class="pembahasan-box">
        <div class="pb-label"><i class="bi bi-lightbulb-fill me-1"></i>Pembahasan</div>
        <?= nl2br(e($s['pembahasan'])) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<script>
function toggleAcc(i) {
    const body  = document.getElementById('body-' + i);
    const arrow = document.getElementById('arrow-' + i);
    const isOpen = body.classList.contains('show');
    body.classList.toggle('show', !isOpen);
    arrow.classList.toggle('open', !isOpen);
}
function bukaSemuaSoal() {
    document.querySelectorAll('.acc-body').forEach(b => b.classList.add('show'));
    document.querySelectorAll('.acc-arrow').forEach(a => a.classList.add('open'));
}
function tutupSemuaSoal() {
    document.querySelectorAll('.acc-body').forEach(b => b.classList.remove('show'));
    document.querySelectorAll('.acc-arrow').forEach(a => a.classList.remove('open'));
}
</script>
</body></html>
<?php
// BUG FIX #6: Hapus session setelah seluruh output HTML selesai dikirim ke browser.
session_unset(); session_destroy();
?>
