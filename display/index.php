<?php
// ============================================================
// display/index.php — Layar Tunggu / Videotron
// Halaman publik — tidak perlu login
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

// Ambil pengaturan
$namaAplikasi  = getSetting($conn, 'nama_aplikasi', 'Sistem CBT TKA Kecamatan');
$namaKecamatan = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$displayInfo   = getSetting($conn, 'display_info', 'Selamat datang di Ujian CBT TKA');
$videoUrl      = getSetting($conn, 'display_video_url', '');
$logoFilePath  = getSetting($conn, 'logo_file_path', '');
$logoUrl       = getSetting($conn, 'logo_url', '');
$logoAktif     = $logoFilePath ? BASE_URL . '/' . $logoFilePath : $logoUrl;

// Jadwal ujian berikutnya atau yang sedang aktif
$today   = date('Y-m-d');
$nowTime = date('H:i:s');

// BUG FIX #7: Tambahkan null check agar tidak fatal error jika DB tidak merespons
$_rJa = $conn->query(
    "SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, keterangan, status, kategori_id FROM jadwal_ujian
     WHERE tanggal='$today' AND jam_mulai<='$nowTime' AND jam_selesai>='$nowTime' AND status='aktif'
     LIMIT 1"
);
$jadwalAktif = ($_rJa && $_rJa->num_rows > 0) ? $_rJa->fetch_assoc() : null;

$_rJb = $conn->query(
    "SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, keterangan, status, kategori_id FROM jadwal_ujian
     WHERE status='aktif' AND (tanggal > '$today' OR (tanggal='$today' AND jam_mulai > '$nowTime'))
     ORDER BY tanggal, jam_mulai LIMIT 1"
);
$jadwalBerikutnya = ($_rJb && $_rJb->num_rows > 0) ? $_rJb->fetch_assoc() : null;

// Statistik ringkas
$_rTp = $conn->query("SELECT COUNT(*) AS c FROM peserta");
$totalPeserta = $_rTp ? (int)$_rTp->fetch_assoc()['c'] : 0;

$_rTs = $conn->query("SELECT COUNT(*) AS c FROM sekolah");
$totalSekolah = $_rTs ? (int)$_rTs->fetch_assoc()['c'] : 0;

$_rSu = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL");
$sedangUjian = $_rSu ? (int)$_rSu->fetch_assoc()['c'] : 0;

$_rSs = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()");
$sudahSelesai = $_rSs ? (int)$_rSs->fetch_assoc()['c'] : 0;

// Hitung countdown ke jadwal berikutnya
$countdownTarget = null;
if ($jadwalBerikutnya) {
    $countdownTarget = strtotime($jadwalBerikutnya['tanggal'] . ' ' . $jadwalBerikutnya['jam_mulai']);
} elseif ($jadwalAktif) {
    $countdownTarget = strtotime($today . ' ' . $jadwalAktif['jam_selesai']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Layar Tunggu — <?= htmlspecialchars($namaAplikasi) ?></title>
<style>
/* ── Base ───────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --primary:#2563eb;--accent:#7c3aed;--green:#10b981;
    --text:#e6edf3;--muted:#8b949e;--border:#30363d;
    --card-bg:#1c2128;
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Segoe UI',system-ui,sans-serif;overflow:hidden}

/* ── Layout ─────────────────────────────────────── */
.display-wrap{display:flex;height:100vh;gap:0}
.col-main{flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative}
.col-side{width:360px;flex-shrink:0;display:flex;flex-direction:column;border-left:1px solid var(--border);background:var(--surface);overflow:hidden}

/* ── Header bar ─────────────────────────────────── */
.top-bar{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 20px;
    background:linear-gradient(90deg,#1a56db,#7c3aed);
    border-bottom:1px solid rgba(255,255,255,.1);
    flex-shrink:0;
}
.top-bar-title{font-size:18px;font-weight:800;color:#fff;letter-spacing:.3px}
.top-bar-sub{font-size:12px;color:rgba(255,255,255,.75);margin-top:2px}
.live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.3)}}

/* ── Video area ──────────────────────────────────── */
.video-area{flex:1;display:flex;align-items:center;justify-content:center;background:#000;position:relative;overflow:hidden}
.video-area iframe{width:100%;height:100%;border:none}
.video-placeholder{
    text-align:center;padding:40px;
    background:linear-gradient(135deg,#1a56db22,#7c3aed22);
    border-radius:16px;border:1px solid var(--border);
    max-width:600px;
}
.video-placeholder .big-icon{font-size:80px;margin-bottom:16px;line-height:1}
.video-placeholder h2{font-size:28px;font-weight:800;color:var(--text);margin-bottom:8px}
.video-placeholder p{color:var(--muted);font-size:15px;line-height:1.6}

/* Slide carousel jika tidak ada video */
.slide-show{width:100%;height:100%;position:relative;overflow:hidden}
.slide{
    position:absolute;inset:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;text-align:center;
    padding:40px;opacity:0;transition:opacity 1s ease;
}
.slide.active{opacity:1}
.slide-1{background:linear-gradient(135deg,#1a56db,#7c3aed)}
.slide-2{background:linear-gradient(135deg,#047857,#065f46)}
.slide-3{background:linear-gradient(135deg,#92400e,#b45309)}
.slide h1{font-size:clamp(24px,4vw,56px);font-weight:900;color:#fff;text-shadow:0 2px 20px rgba(0,0,0,.4);margin-bottom:16px}
.slide p{font-size:clamp(14px,2vw,22px);color:rgba(255,255,255,.85);max-width:600px;line-height:1.6}
.slide .slide-icon{font-size:clamp(48px,8vw,100px);margin-bottom:20px}

/* ── Countdown ───────────────────────────────────── */
.countdown-area{
    padding:20px;border-bottom:1px solid var(--border);
    background:var(--card-bg);flex-shrink:0;
}
.countdown-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px}
.countdown-blocks{display:flex;gap:8px;justify-content:center}
.cd-block{
    flex:1;text-align:center;background:var(--surface2);
    border:1px solid var(--border);border-radius:10px;padding:10px 4px;
}
.cd-num{font-size:28px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;line-height:1}
.cd-lbl{font-size:9px;color:var(--muted);text-transform:uppercase;margin-top:4px;letter-spacing:.5px}
.countdown-label{font-size:12px;color:var(--muted);text-align:center;margin-top:8px}

/* LIVE badge */
.live-badge-full{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:linear-gradient(90deg,#10b981,#059669);
    border-radius:10px;padding:14px 20px;
    font-size:18px;font-weight:800;color:#fff;
    animation:pulseBg 2s ease-in-out infinite;
}
@keyframes pulseBg{0%,100%{box-shadow:0 0 0 0 rgba(16,185,129,.4)}50%{box-shadow:0 0 20px 8px rgba(16,185,129,.15)}}

/* ── Stat cards ──────────────────────────────────── */
.stats-area{padding:16px;display:flex;flex-direction:column;gap:8px;flex-shrink:0}
.stat-row{
    display:flex;align-items:center;gap:12px;
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:10px;padding:12px;
}
.stat-icon-sm{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.ic-blue{background:rgba(37,99,235,.2);color:#60a5fa}
.ic-green{background:rgba(16,185,129,.2);color:#34d399}
.ic-orange{background:rgba(245,158,11,.2);color:#fbbf24}
.ic-purple{background:rgba(124,58,237,.2);color:#a78bfa}
.stat-num{font-size:22px;font-weight:800;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}

/* ── Info area ───────────────────────────────────── */
.info-area{flex:1;padding:16px;overflow-y:auto;border-top:1px solid var(--border)}
.info-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px}
.info-text{font-size:14px;color:var(--text);line-height:1.8;background:var(--card-bg);border-radius:10px;padding:14px;border:1px solid var(--border)}
.jadwal-card{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:10px}
.jadwal-card .lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.jadwal-card .val{font-size:15px;font-weight:700;color:var(--text);margin-top:2px}

/* ── Clock ───────────────────────────────────────── */
.clock-bar{
    padding:14px;text-align:center;border-top:1px solid var(--border);
    background:var(--card-bg);flex-shrink:0;
}
.clock{font-size:32px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;letter-spacing:2px}
.clock-date{font-size:12px;color:var(--muted);margin-top:2px}

/* ── Scrollbar ───────────────────────────────────── */
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
</style>
</head>
<body>

<div class="display-wrap">

    <!-- ══ KOLOM UTAMA (kiri) ══ -->
    <div class="col-main">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <div class="top-bar-title">
                    <span class="live-dot"></span><?= htmlspecialchars($namaAplikasi) ?>
                </div>
                <div class="top-bar-sub">📍 <?= htmlspecialchars($namaKecamatan) ?></div>
            </div>
            <div style="font-size:13px;color:rgba(255,255,255,.8)" id="topClock"></div>
        </div>

        <!-- Area video / slideshow -->
        <div class="video-area">
            <?php if (!empty($videoUrl)): ?>
            <iframe src="<?= htmlspecialchars($videoUrl) ?>&autoplay=1&mute=1&loop=1&controls=0"
                    allow="autoplay; fullscreen" allowfullscreen></iframe>
            <?php else: ?>
            <!-- Slideshow jika tidak ada video -->
            <div class="slide-show" id="slideShow">
                <div class="slide slide-1 active">
                    <div class="slide-icon">
                        <?php if ($logoAktif): ?>
                        <img src="<?= htmlspecialchars($logoAktif) ?>"
                             alt="Logo"
                             style="width:120px;height:120px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,0.3))"
                             onerror="this.outerHTML='🏫'">
                        <?php else: ?>
                        🏫
                        <?php endif; ?>
                    </div>
                    <h1><?= htmlspecialchars($namaAplikasi) ?></h1>
                    <p><?= htmlspecialchars($namaKecamatan) ?> — Ujian Berbasis Komputer</p>
                </div>
                <div class="slide slide-2">
                    <div class="slide-icon">📝</div>
                    <h1>Siapkan Dirimu</h1>
                    <p>Baca setiap soal dengan teliti.<br>Pastikan jawaban kamu sudah tersimpan.</p>
                </div>
                <div class="slide slide-3">
                    <div class="slide-icon">🎯</div>
                    <h1>Semangat!</h1>
                    <p>Kerjakan dengan jujur dan percaya diri.<br>Hasil terbaik menanti kamu!</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ KOLOM SAMPING (kanan) ══ -->
    <div class="col-side">

        <!-- Countdown / Status Ujian -->
        <div class="countdown-area">
            <div class="countdown-title">
                <?= $jadwalAktif ? 'Sesi Ujian Berlangsung' : 'Hitung Mundur' ?>
            </div>

            <?php if ($jadwalAktif): ?>
            <div class="live-badge-full">
                <span>●</span> UJIAN SEDANG BERLANGSUNG
            </div>
            <div class="countdown-label mt-2">
                <?= substr($jadwalAktif['jam_mulai'],0,5) ?> – <?= substr($jadwalAktif['jam_selesai'],0,5) ?>
                · <?= $jadwalAktif['durasi_menit'] ?> menit
            </div>
            <?php else: ?>
            <div class="countdown-blocks">
                <div class="cd-block"><div class="cd-num" id="cdJam">--</div><div class="cd-lbl">Jam</div></div>
                <div class="cd-block"><div class="cd-num" id="cdMenit">--</div><div class="cd-lbl">Menit</div></div>
                <div class="cd-block"><div class="cd-num" id="cdDetik">--</div><div class="cd-lbl">Detik</div></div>
            </div>
            <div class="countdown-label" id="cdLabel">
                <?php if ($jadwalBerikutnya): ?>
                Menuju sesi ujian: <?= formatTanggal($jadwalBerikutnya['tanggal']) ?>
                pukul <?= substr($jadwalBerikutnya['jam_mulai'],0,5) ?>
                <?php else: ?>
                Belum ada jadwal ujian terjadwal
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistik Realtime -->
        <div class="stats-area">
            <div class="countdown-title" style="padding:0">Statistik Hari Ini</div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-blue">👥</div>
                <div>
                    <div class="stat-num" id="statPeserta"><?= $totalPeserta ?></div>
                    <div class="stat-lbl">Total Peserta Terdaftar</div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-green">▶</div>
                <div>
                    <div class="stat-num" id="statUjian"><?= $sedangUjian ?></div>
                    <div class="stat-lbl">Sedang Ujian</div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-orange">✅</div>
                <div>
                    <div class="stat-num" id="statSelesai"><?= $sudahSelesai ?></div>
                    <div class="stat-lbl">Selesai Hari Ini</div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-purple">🏫</div>
                <div>
                    <div class="stat-num"><?= $totalSekolah ?></div>
                    <div class="stat-lbl">Sekolah Peserta</div>
                </div>
            </div>
        </div>

        <!-- Info ujian -->
        <div class="info-area">
            <div class="info-title">Informasi Ujian</div>
            <div class="info-text"><?= nl2br(htmlspecialchars($displayInfo)) ?></div>

            <?php if ($jadwalBerikutnya): ?>
            <div class="jadwal-card">
                <div class="lbl">Jadwal Ujian Berikutnya</div>
                <div class="val"><?= formatTanggal($jadwalBerikutnya['tanggal']) ?></div>
                <div style="color:#60a5fa;font-size:14px;margin-top:4px">
                    🕐 <?= substr($jadwalBerikutnya['jam_mulai'],0,5) ?> –
                    <?= substr($jadwalBerikutnya['jam_selesai'],0,5) ?>
                    (<?= $jadwalBerikutnya['durasi_menit'] ?> menit)
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Jam -->
        <div class="clock-bar">
            <div class="clock" id="liveClock">--:--:--</div>
            <div class="clock-date" id="liveDate"></div>
        </div>
    </div>

</div>

<script>
// ── Jam realtime ─────────────────────────────────────────────
const hariName = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const bulanName = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function pad(n){ return String(n).padStart(2,'0'); }

function updateClock(){
    const now  = new Date();
    const wkt  = pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
    const tgl  = hariName[now.getDay()]+', '+now.getDate()+' '+bulanName[now.getMonth()]+' '+now.getFullYear();
    document.getElementById('liveClock').textContent = wkt;
    document.getElementById('liveDate').textContent  = tgl;
    document.getElementById('topClock').textContent  = wkt + ' WIB';
}
setInterval(updateClock, 1000);
updateClock();

// ── Countdown ─────────────────────────────────────────────────
<?php if ($countdownTarget && !$jadwalAktif): ?>
let targetTs = <?= $countdownTarget ?> * 1000;
function updateCountdown(){
    const sisa = Math.max(0, Math.floor((targetTs - Date.now()) / 1000));
    const j = Math.floor(sisa / 3600);
    const m = Math.floor((sisa % 3600) / 60);
    const s = sisa % 60;
    document.getElementById('cdJam').textContent   = pad(j);
    document.getElementById('cdMenit').textContent = pad(m);
    document.getElementById('cdDetik').textContent = pad(s);
    if (sisa <= 0) document.getElementById('cdLabel').textContent = '⏰ Ujian segera dimulai!';
}
setInterval(updateCountdown, 1000);
updateCountdown();
<?php endif; ?>

// ── Slideshow (jika tidak ada video) ─────────────────────────
<?php if (empty($videoUrl)): ?>
const slides = document.querySelectorAll('.slide');
let current  = 0;
setInterval(() => {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
}, 6000);
<?php endif; ?>

// ── Realtime stats refresh ────────────────────────────────────
function refreshStats(){
    fetch('<?= BASE_URL ?>/admin/ajax_statistik.php')
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d) return;
            document.getElementById('statUjian').textContent   = d.peserta_ujian;
            document.getElementById('statSelesai').textContent = d.peserta_selesai;
        })
        .catch(() => {});
}
setInterval(refreshStats, 15000);
</script>
</body>
</html>
