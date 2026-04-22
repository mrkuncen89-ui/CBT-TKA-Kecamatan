<?php
// ============================================================
// admin/kartu_ujian.php — Cetak Kartu Ujian Peserta
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// ── Filter ────────────────────────────────────────────────────
$filterSek   = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas = trim($_GET['kelas'] ?? '');
$filterId    = (int)($_GET['peserta_id'] ?? 0);

// ── Query Peserta ─────────────────────────────────────────────
$conds = ['1=1'];
if ($filterSek)   $conds[] = "p.sekolah_id = $filterSek";
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($filterId)    $conds[] = "p.id = $filterId";
$where = 'WHERE ' . implode(' AND ', $conds);

$res = $conn->query("
    SELECT p.id, p.nama, p.kelas, p.kode_peserta,
           s.nama_sekolah, s.alamat AS alamat_sekolah
    FROM peserta p LEFT JOIN sekolah s ON s.id = p.sekolah_id
    $where ORDER BY s.nama_sekolah, p.kelas, p.nama
");
$peserta = [];
if ($res) while ($r = $res->fetch_assoc()) $peserta[] = $r;

// ── Pengaturan Sistem ─────────────────────────────────────────
$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan     = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$jumlahSoal        = (int)getSetting($conn, 'jumlah_soal', '40');
$logoFilePath      = getSetting($conn, 'logo_file_path', '');
$logoUrl           = getSetting($conn, 'logo_url', '');
$logoAktif         = $logoFilePath ? BASE_URL . '/' . $logoFilePath : $logoUrl;
$tahunAjaran       = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));

// ── Jadwal aktif ──────────────────────────────────────────────
$jadwalList = [];
$jr = $conn->query("
    SELECT j.id, j.tanggal, j.jam_mulai, j.jam_selesai, j.durasi_menit,
           j.keterangan, j.kategori_id, k.nama_kategori
    FROM jadwal_ujian j
    LEFT JOIN kategori_soal k ON k.id = j.kategori_id
    WHERE j.status = 'aktif'
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
");
if ($jr) {
    while ($r = $jr->fetch_assoc()) {
        $jml = 0;
        if ($r['kategori_id']) {
            $qs = $conn->query("SELECT COUNT(*) as c FROM soal WHERE kategori_id = " . (int)$r['kategori_id']);
            if ($qs) $jml = (int)$qs->fetch_assoc()['c'];
        }
        $r['jml_soal'] = $jml ?: $jumlahSoal;
        $jadwalList[] = $r;
    }
}

// ── Sekolah list untuk filter ─────────────────────────────────
$sekolahList = [];
$sl = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");
if ($sl) while ($r = $sl->fetch_assoc()) $sekolahList[$r['id']] = $r['nama_sekolah'];

// ── Helper tanggal ────────────────────────────────────────────
function tgl_indo($tanggal) {
    $bln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    $t   = explode('-', $tanggal);
    return $t[2] . ' ' . ($bln[(int)$t[1]] ?? '') . ' ' . $t[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kartu Ujian — <?= e($namaAplikasi) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════
   RESET & BASE
══════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',Arial,sans-serif;background:#edf2f7;color:#1e293b;font-size:12px}

/* ══════════════════════════════════════
   TOOLBAR (layar saja)
══════════════════════════════════════ */
@media screen {
  .toolbar{
    background:#fff;padding:10px 24px;
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    position:sticky;top:0;z-index:100;
    border-bottom:3px solid #1d4ed8;
    box-shadow:0 2px 8px rgba(0,0,0,.07);
  }
  .toolbar-title{
    font-size:15px;font-weight:800;color:#1d4ed8;flex:1;
    display:flex;align-items:center;gap:8px;
  }
  .toolbar select{
    padding:7px 12px;border-radius:7px;border:1.5px solid #cbd5e1;
    font-size:12px;font-family:inherit;color:#334155;background:#f8fafc;
    cursor:pointer;
  }
  .toolbar select:focus{outline:none;border-color:#1d4ed8}
  .btn{
    padding:7px 16px;border-radius:7px;font-size:12px;font-weight:700;
    cursor:pointer;border:none;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;
    transition:all .15s;white-space:nowrap;
  }
  .btn-primary{background:#1d4ed8;color:#fff}
  .btn-primary:hover{background:#1e40af}
  .btn-secondary{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
  .btn-secondary:hover{background:#e2e8f0;color:#1e293b}
  .cnt-badge{
    background:#eff6ff;color:#1d4ed8;
    font-size:12px;font-weight:700;
    padding:4px 10px;border-radius:20px;
    border:1.5px solid #bfdbfe;
  }

  /* Sesi info bar */
  .jadwal-bar{
    background:#fff;border-bottom:1px solid #e2e8f0;
    padding:8px 24px;display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;
  }
  .jadwal-bar-label{font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
  .jadwal-chips{display:flex;flex-wrap:wrap;gap:6px}
  .jchip{
    background:#f0f9ff;border:1px solid #bae6fd;
    border-radius:6px;padding:4px 10px;
    font-size:11px;color:#0369a1;font-weight:600;
    display:flex;align-items:center;gap:5px;
  }
  .jchip-mapel{
    background:#eff6ff;border-color:#bfdbfe;
    color:#1d4ed8;font-weight:800;
  }
  .no-jadwal-info{font-size:12px;color:#f59e0b;font-weight:600}

  .page-wrap{padding:28px;max-width:860px;margin:0 auto}
  .grid-wrap{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}

  /* ── Mobile: 1 kolom, kartu landscape ── */
  @media(max-width:600px){
    .toolbar{padding:8px 12px;gap:6px}
    .toolbar-title{font-size:13px}
    .toolbar select{font-size:11px;padding:6px 8px}
    .btn{padding:6px 12px;font-size:11px}
    .cnt-badge{font-size:11px;padding:3px 8px}
    .jadwal-bar{padding:8px 12px}
    .page-wrap{padding:12px}
    .grid-wrap{
      grid-template-columns:1fr;
      gap:14px;
    }
    /* Kartu landscape di mobile */
    .kartu{
      max-width:100%;
      /* Rasio kartu fisik: 85.6mm x 53.98mm ~ 1.586:1 */
      min-height:0;
    }
    .k-header{padding:8px 12px}
    .logo-box{width:32px;height:32px}
    .head-title{font-size:11px}
    .head-sub{font-size:8.5px}
    .head-badge{font-size:7px;padding:2px 6px}
    .k-sesi{padding:4px 12px}
    .k-body{padding:10px 12px 6px;gap:12px}
    .foto-init{width:52px;height:64px;font-size:22px}
    .info-nama{font-size:14px}
    .info-lbl{font-size:8px;width:52px}
    .info-val{font-size:10px}
    .k-jadwal-list{margin-top:6px;padding-top:5px}
    .k-jadwal-mapel{font-size:8.5px}
    .k-jadwal-detail{font-size:8px}
    .k-kode{margin:0 12px 10px}
    .kode-lbl{font-size:8px}
    .kode-val{font-size:20px;letter-spacing:4px}
    .ttd-box{min-width:90px}
    .ttd-line{width:90px}
    .foot-txt{font-size:8px}
  }
}

/* ══════════════════════════════════════
   PRINT
══════════════════════════════════════ */
@media print {
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body{background:#fff;font-family:'Nunito',Arial,sans-serif}
  .toolbar,.jadwal-bar{display:none!important}
  .page-wrap{padding:0;max-width:100%}
  .grid-wrap{
    display:grid;grid-template-columns:repeat(2,1fr);
    gap:5mm;padding:7mm;
  }
  @page{size:A4 portrait;margin:0}
  .kartu{break-inside:avoid;page-break-inside:avoid;box-shadow:none!important}
  .kartu:nth-child(6n){page-break-after:always;break-after:page}
  /* Paksa warna */
  .k-header{background:linear-gradient(135deg,#1d4ed8,#2563eb)!important}
  .k-sesi{background:#eff6ff!important}
  .k-foot{background:#f8fafc!important}
  .kode-inner{background:#eff6ff!important;border-color:#bfdbfe!important}
  .foto-init{background:linear-gradient(135deg,#dbeafe,#eff6ff)!important}
}

/* ══════════════════════════════════════
   KARTU
══════════════════════════════════════ */
.kartu{
  width:100%;
  background:#fff;
  border-radius:10px;
  overflow:hidden;
  border:1.5px solid #1d4ed8;
  box-shadow:0 2px 10px rgba(29,78,216,.10);
  display:flex;flex-direction:column;
  font-family:'Nunito',Arial,sans-serif;
}

/* ── Header ── */
.k-header{
  background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);
  padding:9px 12px;
  display:flex;align-items:center;gap:10px;
  position:relative;overflow:hidden;
}
/* Lingkaran dekoratif di kanan header */
.k-header::before{
  content:'';position:absolute;right:-18px;top:-18px;
  width:60px;height:60px;border-radius:50%;
  background:rgba(255,255,255,.08);
}
.k-header::after{
  content:'';position:absolute;right:10px;bottom:-22px;
  width:48px;height:48px;border-radius:50%;
  background:rgba(255,255,255,.06);
}
.logo-box{
  width:36px;height:36px;flex-shrink:0;
  border-radius:7px;background:#fff;
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;padding:3px;
  box-shadow:0 1px 4px rgba(0,0,0,.15);
}
.logo-box img{width:100%;height:100%;object-fit:contain}
.logo-box .logo-txt{font-size:17px;font-weight:900;color:#1d4ed8;line-height:1}
.head-text{flex:1;color:#fff;z-index:1}
.head-title{font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;line-height:1.2}
.head-sub{font-size:9px;opacity:.85;margin-top:1px;font-weight:600}
.head-badge{
  flex-shrink:0;z-index:1;
  background:rgba(255,255,255,.2);
  border:1px solid rgba(255,255,255,.35);
  border-radius:5px;padding:3px 7px;
  font-size:7.5px;font-weight:800;color:#fff;
  text-transform:uppercase;letter-spacing:.5px;
  white-space:nowrap;
  backdrop-filter:blur(2px);
}

/* ── Sesi strip ── */
.k-sesi{
  background:#eff6ff;
  border-bottom:1px solid #bfdbfe;
  padding:5px 12px;
  display:flex;align-items:center;gap:6px;flex-wrap:wrap;
}
.sesi-mapel-tag{
  background:#1d4ed8;color:#fff;
  font-size:8px;font-weight:800;
  padding:2px 8px;border-radius:20px;
  letter-spacing:.3px;white-space:nowrap;
}
.sesi-mapel-tag.all{background:#64748b}
.sesi-info-txt{font-size:8px;color:#1d4ed8;font-weight:700;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.sesi-info-txt .sep{color:#93c5fd;margin:0 1px}
.no-sesi-txt{font-size:8px;color:#94a3b8;font-style:italic}

/* ── Body ── */
.k-body{
  padding:10px 12px 8px;
  display:flex;gap:10px;
  flex:1;
}
.foto-init{
  width:46px;height:56px;flex-shrink:0;
  border-radius:7px;
  background:linear-gradient(135deg,#dbeafe,#eff6ff);
  border:1.5px solid #bfdbfe;
  display:flex;align-items:center;justify-content:center;
  font-size:20px;font-weight:900;color:#1d4ed8;
  letter-spacing:-1px;
  font-family:'Nunito',sans-serif;
}
.info-col{flex:1;min-width:0}
.info-nama{
  font-size:13px;font-weight:900;color:#0f172a;
  line-height:1.2;margin-bottom:5px;
  text-transform:uppercase;
}
.info-row{display:flex;align-items:baseline;gap:0;margin-bottom:3px}
.info-lbl{
  font-size:7.5px;font-weight:700;color:#94a3b8;
  text-transform:uppercase;letter-spacing:.4px;
  width:48px;flex-shrink:0;
}
.info-sep{font-size:8.5px;color:#cbd5e1;margin-right:4px;flex-shrink:0}
.info-val{font-size:9.5px;font-weight:700;color:#334155;line-height:1.3}

/* Jadwal dalam kartu */
.k-jadwal-list{
  margin-top:6px;
  padding-top:5px;
  border-top:1px solid #f1f5f9;
}
.k-jadwal-row{
  display:flex;align-items:baseline;gap:6px;
  margin-bottom:2px;
}
.k-jadwal-mapel{
  font-size:8px;font-weight:800;color:#1d4ed8;
  white-space:nowrap;flex-shrink:0;
}
.k-jadwal-detail{font-size:7.5px;color:#64748b;font-weight:600;line-height:1.2}
.k-jadwal-detail b{color:#334155}
.no-jadwal-row{font-size:8px;color:#94a3b8;font-style:italic}

/* ── Kode area ── */
.k-kode{
  margin:0 12px 10px;
  display:flex;align-items:stretch;gap:8px;
}
.kode-inner{
  flex:1;
  background:#eff6ff;
  border:1.5px solid #bfdbfe;
  border-radius:8px;
  padding:6px 10px;
}
.kode-lbl{
  font-size:7px;font-weight:800;color:#3b82f6;
  text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px;
}
.kode-val{
  font-family:'Courier New',monospace;
  font-size:18px;font-weight:900;color:#1d4ed8;
  letter-spacing:3px;line-height:1;
}
.ttd-box{
  display:flex;flex-direction:column;align-items:center;
  justify-content:flex-end;padding-bottom:2px;
  min-width:80px;
}
.ttd-line{
  width:80px;height:22px;
  border-bottom:1.5px solid #94a3b8;
  margin-bottom:3px;
}
.ttd-lbl{font-size:7px;color:#94a3b8;font-weight:600;white-space:nowrap}

/* ── Footer ── */
.k-foot{
  background:#f8fafc;
  border-top:1px solid #e8eef6;
  padding:4px 12px;
  display:flex;align-items:center;justify-content:space-between;
}
.foot-txt{font-size:7.5px;color:#94a3b8;font-weight:600}
.foot-txt strong{color:#64748b}

/* ══════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════ */
.empty-wrap{
  grid-column:1/-1;
  background:#fff;border-radius:12px;
  border:2px dashed #cbd5e1;
  text-align:center;padding:60px 20px;
}
.empty-ic{font-size:36px;margin-bottom:10px}
.empty-ttl{font-size:15px;font-weight:800;color:#475569}
.empty-sub{font-size:12px;color:#94a3b8;margin-top:4px}
</style>
</head>
<body>

<!-- ══ TOOLBAR ══ -->
<div class="toolbar">
  <div class="toolbar-title">
    &#128219; Kartu Ujian Peserta
  </div>

  <form method="GET" style="display:contents">
    <select name="sekolah_id" onchange="this.form.submit()">
      <option value="">&#8212; Semua Sekolah</option>
      <?php foreach($sekolahList as $sid=>$snm): ?>
      <option value="<?=$sid?>" <?=$filterSek==$sid?'selected':''?>><?=htmlspecialchars($snm)?></option>
      <?php endforeach; ?>
    </select>

    <select name="kelas" onchange="this.form.submit()">
      <option value="">&#8212; Semua Kelas</option>
      <?php
      $semuaKelas = array_merge(getKelasByJenjang('SD'), getKelasByJenjang('SMP'), getKelasByJenjang('SMA'));
      foreach($semuaKelas as $k): ?>
      <option value="<?=$k?>" <?=$filterKelas===$k?'selected':''?>>Kelas <?=$k?></option>
      <?php endforeach; ?>
    </select>

    <?php if ($filterId): ?><input type="hidden" name="peserta_id" value="<?=$filterId?>"><?php endif; ?>
  </form>

  <span class="cnt-badge"><?= count($peserta) ?> kartu</span>
  <a href="<?=BASE_URL?>/admin/peserta.php" class="btn btn-secondary">&#8592; Kembali</a>
  <button class="btn btn-primary" onclick="window.print()">&#128424; Cetak Semua</button>
</div>

<!-- ══ JADWAL BAR ══ -->
<div class="jadwal-bar">
  <span class="jadwal-bar-label">Jadwal Aktif:</span>
  <?php if (empty($jadwalList)): ?>
    <span class="no-jadwal-info">&#9888; Belum ada jadwal aktif &mdash; buat jadwal terlebih dahulu agar info sesi muncul di kartu.</span>
  <?php else: ?>
  <div class="jadwal-chips">
    <?php foreach($jadwalList as $j): ?>
    <span class="jchip">
      <?php if ($j['nama_kategori']): ?>
      <span class="jchip-mapel"><?= e($j['nama_kategori']) ?></span>
      <?php endif; ?>
      <?= tgl_indo($j['tanggal']) ?> &nbsp;
      <?= substr($j['jam_mulai'],0,5) ?>&ndash;<?= substr($j['jam_selesai'],0,5) ?> WIB
      &nbsp;&middot; <?= $j['durasi_menit'] ?> mnt
    </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══ GRID ══ -->
<div class="page-wrap">
<div class="grid-wrap">

<?php if (empty($peserta)): ?>
<div class="empty-wrap">
  <div class="empty-ic">&#128219;</div>
  <div class="empty-ttl">Belum ada peserta</div>
  <div class="empty-sub">Pilih filter atau tambah peserta terlebih dahulu.</div>
</div>

<?php else:
$mapelSingle = count($jadwalList) === 1 ? ($jadwalList[0]['nama_kategori'] ?? null) : null;
foreach($peserta as $p):
  $inisial2     = mb_strtoupper(mb_substr($p['nama'], 0, 2));
  $kelasStr     = $p['kelas'] ? 'Kelas ' . $p['kelas'] : '-';
  $sekolah      = $p['nama_sekolah'] ?? '-';
  $sekolahShort = mb_strlen($sekolah) > 34 ? mb_substr($sekolah, 0, 32) . '…' : $sekolah;
  $kode         = $p['kode_peserta'] ?? '------';
?>

<div class="kartu">

  <!-- Header -->
  <div class="k-header">
    <div class="logo-box">
      <?php if ($logoAktif): ?>
        <img src="<?= htmlspecialchars($logoAktif) ?>" alt="Logo"
             onerror="this.style.display='none'">
      <?php else: ?>
        <span class="logo-txt"><?= e(mb_strtoupper(mb_substr($namaAplikasi,0,1))) ?></span>
      <?php endif; ?>
    </div>
    <div class="head-text">
      <div class="head-title"><?= e($namaAplikasi) ?></div>
      <div class="head-sub"><?= e($namaPenyelenggara ?: $namaKecamatan) ?></div>
    </div>
    <div class="head-badge">KARTU UJIAN</div>
  </div>

  <!-- Sesi Strip -->
  <?php if (!empty($jadwalList)): ?>
  <div class="k-sesi">
    <?php if ($mapelSingle): ?>
      <span class="sesi-mapel-tag">&#128218; <?= e($mapelSingle) ?></span>
    <?php elseif (count($jadwalList) > 1): ?>
      <span class="sesi-mapel-tag all">Multi Sesi</span>
    <?php else: ?>
      <span class="sesi-mapel-tag all">Semua Mapel</span>
    <?php endif; ?>
    <div class="sesi-info-txt">
      <?php $j0 = $jadwalList[0]; ?>
      <span>&#128197; <?= tgl_indo($j0['tanggal']) ?></span>
      <span class="sep">&middot;</span>
      <span>&#9200; <?= substr($j0['jam_mulai'],0,5) ?>&ndash;<?= substr($j0['jam_selesai'],0,5) ?></span>
      <span class="sep">&middot;</span>
      <span>&#9203; <?= $j0['durasi_menit'] ?> mnt</span>
    </div>
  </div>
  <?php else: ?>
  <div class="k-sesi"><span class="no-sesi-txt">Jadwal belum ditetapkan</span></div>
  <?php endif; ?>

  <!-- Body -->
  <div class="k-body">
    <div class="foto-init"><?= e($inisial2) ?></div>
    <div class="info-col">
      <div class="info-nama"><?= e($p['nama']) ?></div>
      <div class="info-row">
        <span class="info-lbl">Kelas</span>
        <span class="info-sep">:</span>
        <span class="info-val"><?= e($kelasStr) ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Sekolah</span>
        <span class="info-sep">:</span>
        <span class="info-val"><?= e($sekolahShort) ?></span>
      </div>

      <!-- Daftar sesi/jadwal -->
      <div class="k-jadwal-list">
        <?php if (empty($jadwalList)): ?>
          <span class="no-jadwal-row">Jadwal belum tersedia</span>
        <?php else: foreach($jadwalList as $j): ?>
        <div class="k-jadwal-row">
          <?php if ($j['nama_kategori']): ?>
          <span class="k-jadwal-mapel"><?= e($j['nama_kategori']) ?></span>
          <?php endif; ?>
          <span class="k-jadwal-detail">
            <b><?= tgl_indo($j['tanggal']) ?></b>
            &nbsp;<?= substr($j['jam_mulai'],0,5) ?>&ndash;<?= substr($j['jam_selesai'],0,5) ?>
            &middot; <?= $j['jml_soal'] ?> soal &middot; <?= $j['durasi_menit'] ?> mnt
          </span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Kode + TTD -->
  <div class="k-kode">
    <div class="kode-inner">
      <div class="kode-lbl">Kode Login Peserta</div>
      <div class="kode-val"><?= e($kode) ?></div>
    </div>
    <div class="ttd-box">
      <div class="ttd-line"></div>
      <div class="ttd-lbl">Tanda Tangan Peserta</div>
    </div>
  </div>

  <!-- Footer -->
  <div class="k-foot">
    <span class="foot-txt">No. <strong>#<?= $p['id'] ?></strong></span>
    <span class="foot-txt">Wajib dibawa saat ujian</span>
    <span class="foot-txt">TA <strong><?= e($tahunAjaran) ?></strong></span>
  </div>

</div><!-- .kartu -->

<?php endforeach; endif; ?>
</div><!-- .grid-wrap -->
</div><!-- .page-wrap -->

<script>
const params = new URLSearchParams(window.location.search);
if (params.get('print')==='1') window.addEventListener('load',()=>setTimeout(()=>window.print(),500));
</script>
</body>
</html>