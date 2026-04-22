<?php
// ============================================================
// admin/preview_soal.php — Preview soal seperti tampilan siswa
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$soalId  = (int)($_GET['id'] ?? 0);
$katId   = (int)($_GET['kat'] ?? 0);

// Ambil satu soal spesifik atau daftar per kategori
if ($soalId) {
    $res = $conn->query("SELECT s.*, k.nama_kategori FROM soal s LEFT JOIN kategori_soal k ON k.id=s.kategori_id WHERE s.id=$soalId LIMIT 1");
    $soal = $res ? $res->fetch_assoc() : null;
    if (!$soal) { setFlash('error','Soal tidak ditemukan.'); redirect(BASE_URL.'/admin/soal.php'); }
    $soalList = [$soal];
    $judulPreview = 'Preview Soal #' . $soalId;
} elseif ($katId) {
    $res = $conn->query("SELECT s.*, k.nama_kategori FROM soal s LEFT JOIN kategori_soal k ON k.id=s.kategori_id WHERE s.kategori_id=$katId ORDER BY s.tipe_soal, s.id");
    $soalList = [];
    if ($res) while ($r=$res->fetch_assoc()) $soalList[] = $r;
    $katNama = $soalList ? $soalList[0]['nama_kategori'] : 'Kategori';
    $judulPreview = 'Preview Soal — ' . $katNama . ' (' . count($soalList) . ' soal)';
} else {
    redirect(BASE_URL.'/admin/soal.php');
}

$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$uploadDir    = BASE_URL . '/assets/uploads/soal/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($judulPreview) ?> — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#eef2f7;font-family:'Segoe UI',Arial,sans-serif;padding:20px}
.wrap{max-width:860px;margin:0 auto}
.toolbar{background:#fff;border-radius:12px;padding:12px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 1px 6px rgba(0,0,0,.07);flex-wrap:wrap}
.toolbar-title{font-weight:800;color:#1e3a8a;font-size:15px}
.soal-card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px}
.soal-card-head{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.soal-card-body{display:grid;grid-template-columns:1fr 1px 1fr;min-height:200px}
.soal-kiri{padding:20px 22px}
.soal-divider{width:1px;background:#e2e8f0;margin:16px 0}
.soal-kanan{padding:20px 22px}
.soal-badge{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1a56db;font-size:12px;font-weight:700;border-radius:20px;padding:4px 12px;border:1px solid #bfdbfe}
.tipe-badge{font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px}
.soal-text{font-size:15px;line-height:1.8;color:#1e293b}
.soal-img{max-width:100%;border-radius:8px;margin-bottom:12px;border:1px solid #e2e8f0;display:block}
.pilihan-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}
.pilihan-item{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-radius:10px;border:2px solid #e2e8f0;margin-bottom:8px;font-size:14px;color:#334155;background:#fafafa}
.pilihan-item.benar{border-color:#16a34a;background:#f0fdf4}
.huruf-box{width:28px;height:28px;border-radius:50%;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#64748b;flex-shrink:0}
.pilihan-item.benar .huruf-box{background:#16a34a;border-color:#16a34a;color:#fff}
.pilihan-teks{flex:1;line-height:1.5;padding-top:2px}
.kunci-badge{background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;margin-top:10px}
.teks-bacaan-box{background:#f0f9ff;border-left:4px solid #1a56db;border-radius:0 8px 8px 0;padding:12px 14px;margin-bottom:14px;font-size:13px;line-height:1.8;color:#1e293b}
.pembahasan-box{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:12px;color:#92400e}
.soal-num{font-size:11px;font-weight:700;color:#64748b}
@media(max-width:700px){.soal-card-body{grid-template-columns:1fr}.soal-divider{display:none}.soal-kiri{padding:14px}.soal-kanan{padding:14px;border-top:1px solid #e2e8f0}}
</style>
</head>
<body>
<div class="wrap">

<!-- Toolbar -->
<div class="toolbar">
  <div class="toolbar-title">
    <i class="bi bi-eye-fill me-2 text-primary"></i><?= e($judulPreview) ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Cetak
    </button>
    <?php if ($katId): ?>
    <a href="<?=BASE_URL?>/admin/soal.php?kat=<?=$katId?>" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Soal
    </a>
    <?php else: ?>
    <a href="<?=BASE_URL?>/admin/soal.php?edit=<?=$soalId?>" class="btn btn-sm btn-outline-warning">
      <i class="bi bi-pencil me-1"></i>Edit Soal Ini
    </a>
    <a href="<?=BASE_URL?>/admin/soal.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($soalList)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-inbox fs-2 d-block mb-2"></i>Tidak ada soal
</div>
<?php else: ?>

<?php foreach ($soalList as $i => $s):
  $no = $i + 1;
  $tipe = $s['tipe_soal'] ?? 'pg';
  $jwbBenar = $s['jawaban_benar'] ?? '';
  $tipeBadge = match($tipe) {
    'bs'   => '<span class="tipe-badge" style="background:#fef3c7;color:#92400e">BS</span>',
    'mcma' => '<span class="tipe-badge" style="background:#ede9fe;color:#5b21b6">MCMA</span>',
    default=> '<span class="tipe-badge" style="background:#eff6ff;color:#1d4ed8">PG</span>',
  };
  $jwbBenarArr = $tipe === 'mcma' ? explode(',', $jwbBenar) : [$jwbBenar];
?>
<div class="soal-card">
  <div class="soal-card-head">
    <div class="d-flex align-items-center gap-2">
      <span class="soal-badge"><i class="bi bi-question-circle"></i> Soal <?=$no?></span>
      <?= $tipeBadge ?>
      <span class="soal-num text-muted"><?= e($s['nama_kategori'] ?? '-') ?> · ID #<?=$s['id']?></span>
    </div>
    <?php if (!$soalId): ?>
    <div class="d-flex gap-1">
      <a href="?id=<?=$s['id']?>" target="_blank" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px" title="Preview terpisah">
        <i class="bi bi-eye"></i>
      </a>
      <a href="<?=BASE_URL?>/admin/soal.php?edit=<?=$s['id']?>" class="btn btn-xs btn-outline-warning" style="font-size:11px;padding:2px 8px" title="Edit">
        <i class="bi bi-pencil"></i>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <div class="soal-card-body">
    <!-- Kiri: teks bacaan + gambar + pertanyaan -->
    <div class="soal-kiri">
      <?php if (!empty($s['teks_bacaan'])): ?>
      <div class="teks-bacaan-box">
        <div style="font-size:10px;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">📄 Bacalah teks berikut!</div>
        <?= nl2br(e($s['teks_bacaan'])) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($s['gambar'])): ?>
      <img src="<?= $uploadDir . e($s['gambar']) ?>" class="soal-img" alt="Gambar soal"
           onerror="this.style.display='none'">
      <?php endif; ?>

      <div class="soal-text"><?= nl2br(e($s['pertanyaan'])) ?></div>

      <?php if (!empty($s['pembahasan'])): ?>
      <div class="pembahasan-box">
        <strong>💡 Pembahasan:</strong> <?= e($s['pembahasan']) ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="soal-divider"></div>

    <!-- Kanan: pilihan jawaban -->
    <div class="soal-kanan">
      <div class="pilihan-label">Pilihan Jawaban</div>

      <?php if ($tipe === 'bs'): ?>
        <?php foreach (['benar'=>'Benar','salah'=>'Salah'] as $val=>$lbl):
          $isBenar = $val === $jwbBenar;
        ?>
        <div class="pilihan-item <?= $isBenar?'benar':'' ?>">
          <div class="huruf-box"><?= $val==='benar'?'B':'S' ?></div>
          <div class="pilihan-teks"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>

      <?php elseif ($tipe === 'mcma'): ?>
        <?php foreach (['a','b','c','d'] as $h):
          $teks     = $s['pilihan_'.$h] ?? '';
          $gambarPilihan = $s['gambar_pilihan_'.$h] ?? '';
          if (!$teks && !$gambarPilihan) continue;
          $isBenar = in_array($h, $jwbBenarArr);
        ?>
        <div class="pilihan-item <?= $isBenar?'benar':'' ?>">
          <div class="huruf-box"><?= strtoupper($h) ?></div>
          <div class="pilihan-teks">
            <?php if ($gambarPilihan): ?>
              <img src="<?= $uploadDir . e($gambarPilihan) ?>"
                   style="max-width:100%;max-height:120px;border-radius:6px;display:block;margin-bottom:<?= $teks?'6px':'0' ?>"
                   alt="Gambar pilihan <?= strtoupper($h) ?>"
                   onerror="this.style.display='none'">
            <?php endif; ?>
            <?php if ($teks): ?><?= e($teks) ?><?php endif; ?>
          </div>
          <?php if ($isBenar): ?><i class="bi bi-check-circle-fill text-success ms-auto mt-1"></i><?php endif; ?>
        </div>
        <?php endforeach; ?>

      <?php else: ?>
        <?php foreach (['a','b','c','d'] as $h):
          $teks     = $s['pilihan_'.$h] ?? '';
          $gambarPilihan = $s['gambar_pilihan_'.$h] ?? '';
          if (!$teks && !$gambarPilihan) continue;
          $isBenar = $h === $jwbBenar;
        ?>
        <div class="pilihan-item <?= $isBenar?'benar':'' ?>">
          <div class="huruf-box"><?= strtoupper($h) ?></div>
          <div class="pilihan-teks">
            <?php if ($gambarPilihan): ?>
              <img src="<?= $uploadDir . e($gambarPilihan) ?>"
                   style="max-width:100%;max-height:120px;border-radius:6px;display:block;margin-bottom:<?= $teks?'6px':'0' ?>"
                   alt="Gambar pilihan <?= strtoupper($h) ?>"
                   onerror="this.style.display='none'">
            <?php endif; ?>
            <?php if ($teks): ?><?= e($teks) ?><?php endif; ?>
          </div>
          <?php if ($isBenar): ?><i class="bi bi-check-circle-fill text-success ms-auto mt-1"></i><?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Kunci jawaban -->
      <div class="kunci-badge">
        <i class="bi bi-key-fill"></i>
        Kunci:
        <?php if ($tipe === 'bs'): ?>
          <?= strtoupper($jwbBenar) ?>
        <?php elseif ($tipe === 'mcma'): ?>
          <?= implode(', ', array_map('strtoupper', $jwbBenarArr)) ?>
        <?php else: ?>
          <?= strtoupper($jwbBenar) ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /soal-card-body -->
</div>
<?php endforeach; ?>
<?php endif; ?>

</div><!-- /wrap -->

<style>@media print{.toolbar{display:none!important}.soal-card{break-inside:avoid;margin-bottom:14px}}</style>
</body>
</html>
