<?php
// ============================================================
// admin/monitoring.php — Monitoring Ujian Real-time
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// ── AJAX: hanya refresh angka stat cards ──────────────────────
// FIX #1: selesai_hari_ini pakai CURDATE(), bukan all-time
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $r1 = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL");
    $aktif = $r1 ? (int)($r1->fetch_assoc()['c'] ?? 0) : 0; if ($r1) $r1->free();
    $r2 = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()");
    $selesai_hari_ini = $r2 ? (int)($r2->fetch_assoc()['c'] ?? 0) : 0; if ($r2) $r2->free();
    echo json_encode([
        'aktif'            => $aktif,
        'selesai_hari_ini' => $selesai_hari_ini,
        'ts'               => date('H:i:s'),
    ]);
    exit;
}

// ── RESET UJIAN (POST + CSRF — bukan GET) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    csrfVerify();

    if ($_POST['aksi'] === 'reset_all') {
        // Konfirmasi 2 langkah: wajib ketik "RESET"
        $konfirmasi = trim($_POST['konfirmasi_teks'] ?? '');
        if ($konfirmasi !== 'RESET') {
            setFlash('error', 'Konfirmasi salah. Ketik <strong>RESET</strong> (huruf kapital) untuk melanjutkan.');
            redirect(BASE_URL . '/admin/monitoring.php');
        }
        // Backup otomatis ke hasil_ujian sebelum hapus (data nilai sudah ada di sana)
        $conn->query("DELETE FROM jawaban");
        $conn->query("DELETE FROM ujian");
        logActivity($conn, 'Reset Ujian', 'Semua data ujian dihapus oleh admin');
        setFlash('success', 'Semua data ujian berhasil direset. Peserta dapat mengerjakan ulang.');
        redirect(BASE_URL . '/admin/monitoring.php');
    }

    if ($_POST['aksi'] === 'reset_jadwal') {
        $jid  = (int)($_POST['jadwal_id'] ?? 0);
        if ($jid) {
            $conn->query("DELETE FROM jawaban WHERE ujian_id IN (SELECT id FROM ujian WHERE jadwal_id=$jid)");
            $conn->query("DELETE FROM ujian WHERE jadwal_id=$jid");
            $_qJd = $conn->query("SELECT tanggal, keterangan FROM jadwal_ujian WHERE id=$jid LIMIT 1");
        $jdRow = ($_qJd && $_qJd->num_rows > 0) ? $_qJd->fetch_assoc() : null;
            $jdLabel = $jdRow ? date('d/m/Y', strtotime($jdRow['tanggal'])) . ($jdRow['keterangan'] ? " ({$jdRow['keterangan']})" : '') : "ID $jid";
            logActivity($conn, 'Reset Ujian', "Reset ujian jadwal: $jdLabel");
            setFlash('success', "Data ujian jadwal <strong>$jdLabel</strong> berhasil direset.");
        }
        redirect(BASE_URL . '/admin/monitoring.php');
    }

    if ($_POST['aksi'] === 'reset_peserta') {
        $pid  = (int)($_POST['peserta_id'] ?? 0);
        $_qNama = $conn->query("SELECT nama FROM peserta WHERE id=$pid LIMIT 1");
        $nama = ($_qNama && $_qNama->num_rows > 0) ? ($_qNama->fetch_assoc()['nama'] ?? 'Peserta') : 'Peserta';
        // Hapus jawaban dulu, lalu ujian (hasil_ujian tetap untuk arsip nilai)
        $conn->query("DELETE FROM jawaban WHERE ujian_id IN (SELECT id FROM ujian WHERE peserta_id=$pid)");
        $conn->query("DELETE FROM ujian WHERE peserta_id=$pid");
        logActivity($conn, 'Reset Ujian', "Reset ujian peserta: $nama (ID: $pid)");
        setFlash('success', "Data ujian <strong>$nama</strong> berhasil direset.");
        redirect(BASE_URL . '/admin/monitoring.php');
    }
}

// ── Cek kolom opsional (perlu migrate_v2.php) ────────────────
$colCheck    = $conn->query("SHOW COLUMNS FROM ujian LIKE 'pelanggaran'");
$adaPelanggar = $colCheck && $colCheck->num_rows > 0;
$colCheck2   = $conn->query("SHOW COLUMNS FROM ujian LIKE 'last_activity'");
$adaLastAct  = $colCheck2 && $colCheck2->num_rows > 0;

// Ambil batas pelanggaran dari pengaturan
$maksPelanggaran = (int)getSetting($conn, 'maks_pelanggaran', '3');

// ── Data peserta SEDANG ujian ─────────────────────────────────
// FIX #3: total_soal pakai JSON_LENGTH(soal_order), bukan COUNT(*) FROM soal
$pelanggarCol  = $adaPelanggar ? "u.pelanggaran,"                                    : "0 AS pelanggaran,";
$lastActCol    = $adaLastAct   ? "IFNULL(u.last_activity, u.waktu_mulai) AS last_activity," : "u.waktu_mulai AS last_activity,";

$sedang = $conn->query("
    SELECT u.id, u.waktu_mulai,
           $pelanggarCol
           $lastActCol
           p.nama, p.kode_peserta, p.kelas, s.nama_sekolah,
           TIMESTAMPDIFF(MINUTE, u.waktu_mulai, NOW()) AS menit_berjalan,
           (SELECT COUNT(*) FROM jawaban j WHERE j.ujian_id=u.id) AS sdh_jawab,
           IFNULL(JSON_LENGTH(u.soal_order), 20) AS total_soal
    FROM ujian u
    JOIN peserta p ON p.id=u.peserta_id
    LEFT JOIN sekolah s ON s.id=p.sekolah_id
    WHERE u.waktu_selesai IS NULL AND u.waktu_mulai IS NOT NULL
    ORDER BY u.waktu_mulai DESC
");

// ── Data peserta SUDAH selesai hari ini ───────────────────────
$selesai = $conn->query("
    SELECT u.id, u.nilai, u.waktu_mulai, u.waktu_selesai,
           p.nama, p.kelas, s.nama_sekolah,
           TIMESTAMPDIFF(MINUTE, u.waktu_mulai, u.waktu_selesai) AS durasi
    FROM ujian u
    JOIN peserta p ON p.id=u.peserta_id
    LEFT JOIN sekolah s ON s.id=p.sekolah_id
    WHERE u.waktu_selesai IS NOT NULL AND DATE(u.waktu_selesai)=CURDATE()
    ORDER BY u.waktu_selesai DESC
");

// ── Statistik ─────────────────────────────────────────────────
$jmlSedang  = $sedang  ? $sedang->num_rows  : 0;
$jmlSelesai = $selesai ? $selesai->num_rows : 0;
$_rt = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL");
$totalUjian = $_rt ? (int)($_rt->fetch_assoc()['c'] ?? 0) : 0; if ($_rt) $_rt->free();
$_rr = $conn->query("SELECT ROUND(AVG(nilai),1) AS r FROM ujian WHERE waktu_selesai IS NOT NULL");
$nilaiRata  = $_rr ? ($_rr->fetch_assoc()['r'] ?? 0) : 0; if ($_rr) $_rr->free();

$pageTitle  = 'Monitoring Ujian';
$activeMenu = 'monitoring';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-display me-2 text-primary"></i>Monitoring Ujian</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Monitoring</li>
        </ol></nav>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger fs-6" id="liveIndicator" style="animation:pulse .9s infinite">
            ● LIVE
        </span>
        <span class="text-muted small">Refresh dalam <strong id="countdownNum">30</strong>s</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <button class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalResetJadwal">
            <i class="bi bi-calendar-x me-1"></i>Reset per Jadwal
        </button>
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalResetAll">
            <i class="bi bi-trash3 me-1"></i>Reset Semua Ujian
        </button>
    </div>
</div>

<!-- Modal Reset per Jadwal -->
<div class="modal fade" id="modalResetJadwal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning">
        <h5 class="modal-title fw-bold"><i class="bi bi-calendar-x me-2"></i>Reset Ujian per Jadwal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="aksi" value="reset_jadwal">
        <div class="modal-body">
          <div class="alert alert-warning mb-3">
            Hanya menghapus data ujian pada <strong>satu jadwal tertentu</strong>. Jadwal lain tidak terpengaruh.
          </div>
          <label class="form-label fw-semibold">Pilih Jadwal yang akan di-reset:</label>
          <select name="jadwal_id" class="form-select" required>
            <option value="">-- Pilih Jadwal --</option>
            <?php
            $jList = $conn->query("SELECT j.id, j.tanggal, j.keterangan, k.nama_kategori FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id=j.kategori_id ORDER BY j.tanggal DESC");
            if ($jList) while ($jRow = $jList->fetch_assoc()):
            ?>
            <option value="<?= $jRow['id'] ?>">
                <?= date('d/m/Y', strtotime($jRow['tanggal'])) ?>
                <?= $jRow['keterangan'] ? ' — '.$jRow['keterangan'] : '' ?>
                <?= $jRow['nama_kategori'] ? ' ('.$jRow['nama_kategori'].')' : '' ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning fw-bold"
                  onclick="return confirm('Yakin reset ujian jadwal ini?')">
            <i class="bi bi-calendar-x me-1"></i>Reset Jadwal Ini
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Reset Semua — 2 Langkah -->
<div class="modal fade" id="modalResetAll" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Reset Semua Ujian</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="aksi" value="reset_all">
        <div class="modal-body">
          <div class="alert alert-danger mb-3">
            <strong>PERINGATAN!</strong> Tindakan ini akan menghapus <strong>semua sesi ujian dan jawaban</strong>. Data nilai di halaman Hasil tetap aman.
          </div>
          <p class="mb-2 fw-semibold">Ketik <code class="text-danger fs-6">RESET</code> untuk melanjutkan:</p>
          <input type="text" name="konfirmasi_teks"
                 class="form-control form-control-lg text-center fw-bold"
                 placeholder="Ketik: RESET" autocomplete="off" id="inputKonfirmasiReset"
                 oninput="document.getElementById('btnResetAllSubmit').disabled = this.value.trim() !== 'RESET'">
          <div class="form-text text-center mt-1">Harus huruf kapital semua</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger fw-bold" id="btnResetAllSubmit" disabled>
            <i class="bi bi-trash3 me-1"></i>Ya, Reset Semua
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (!$adaPelanggar || !$adaLastAct): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div>
        <strong>Migrasi database belum lengkap.</strong>
        Kolom <code>pelanggaran</code> / <code>last_activity</code> belum ada.
        <a href="<?= BASE_URL ?>/install/migrate.php" class="alert-link ms-2" target="_blank">
            Jalankan migrate.php sekarang →
        </a>
        <span class="text-muted ms-1 small">(monitoring berjalan terbatas)</span>
    </div>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-activity"></i></div>
            <div>
                <div class="stat-label">Sedang Ujian</div>
                <div class="stat-value" id="cntSedang"><?=$jmlSedang?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-label">Selesai Hari Ini</div>
                <!-- FIX #1: id sesuai dengan AJAX key selesai_hari_ini -->
                <div class="stat-value" id="cntSelesaiHariIni"><?=$jmlSelesai?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-clipboard-check"></i></div>
            <div>
                <div class="stat-label">Total Ujian</div>
                <div class="stat-value"><?=$totalUjian?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-bar-chart"></i></div>
            <div>
                <div class="stat-label">Rata-rata Nilai</div>
                <div class="stat-value"><?=$nilaiRata?></div>
            </div>
        </div>
    </div>
</div>

<!-- SEDANG UJIAN -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-hourglass-split text-danger me-2"></i>Peserta Sedang Mengerjakan Ujian</span>
        <span class="badge bg-danger" id="badgeSedang"><?=$jmlSedang?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="tblSedang">
                <thead><tr>
                    <th>#</th><th>Nama</th><th>Kode</th><th>Kelas</th><th>Sekolah</th>
                    <th class="text-center">Durasi</th><th>Progress</th>
                    <th class="text-center">⚠ Pelanggaran</th>
                    <th class="text-center">Last Aktif</th>
                    <th class="text-center">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if ($sedang && $sedang->num_rows > 0): $no = 1;
                      while ($r = $sedang->fetch_assoc()):
                    $pelanggar = (int)($r['pelanggaran'] ?? 0);
                    $lastAktif = $r['last_activity'] ? date('H:i:s', strtotime($r['last_activity'])) : '-';
                    $idleDetik = $r['last_activity'] ? (time() - strtotime($r['last_activity'])) : 9999;
                    $idleClass = $idleDetik > 120 ? 'text-danger fw-bold' : ($idleDetik > 60 ? 'text-warning' : 'text-success');
                    $melebihiBatas = $pelanggar >= $maksPelanggaran && $maksPelanggaran > 0;
                    // FIX #3: gunakan total_soal dari JSON_LENGTH(soal_order)
                    $totalSoal = max(1, (int)$r['total_soal']);
                    $pct       = round($r['sdh_jawab'] / $totalSoal * 100);
                    // Prioritas highlight: pelanggaran melebih batas > idle lama
                    $rowClass  = $melebihiBatas ? 'table-danger' : ($idleDetik > 120 ? 'table-warning' : '');
                ?>
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?=$no++?></td>
                    <td>
                        <strong><?=htmlspecialchars($r['nama'])?></strong>
                        <?php if ($melebihiBatas): ?>
                        <br><span style="font-size:10px" class="text-danger fw-bold">
                            <i class="bi bi-exclamation-triangle-fill"></i> Pelanggaran melebihi batas!
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><code><?=htmlspecialchars($r['kode_peserta'])?></code></td>
                    <td><?=htmlspecialchars($r['kelas']??'-')?></td>
                    <td><?=htmlspecialchars($r['nama_sekolah']??'-')?></td>
                    <td class="text-center">
                        <span class="badge bg-warning text-dark"><?=$r['menit_berjalan']?> mnt</span>
                    </td>
                    <td style="min-width:160px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px">
                                <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : '' ?>"
                                     style="width:<?=$pct?>%"></div>
                            </div>
                            <small><?=$r['sdh_jawab']?>/<?=$totalSoal?></small>
                        </div>
                    </td>
                    <td class="text-center">
                        <?php if ($melebihiBatas): ?>
                        <span class="badge bg-danger fs-6"><?=$pelanggar?>x ⚠</span>
                        <?php elseif ($pelanggar > 0): ?>
                        <span class="badge bg-warning text-dark"><?=$pelanggar?>x</span>
                        <?php else: ?>
                        <span class="text-muted small">–</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center small <?=$idleClass?>">
                        <?=$lastAktif?>
                        <?php if ($idleDetik > 120): ?>
                        <br><span style="font-size:10px">⚠ Idle <?=round($idleDetik/60)?>mnt</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Lanjutkan?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="aksi" value="reset_peserta">
                        <input type="hidden" name="peserta_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="10" class="text-center text-muted py-4">
                    <i class="bi bi-moon-stars me-2"></i>Tidak ada peserta yang sedang ujian
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SUDAH SELESAI HARI INI -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-check-circle-fill text-success me-2"></i>Peserta Selesai Hari Ini</span>
        <span class="badge bg-success"><?=$jmlSelesai?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="tblSelesai">
                <thead><tr>
                    <th>#</th><th>Nama</th><th>Kelas</th><th>Sekolah</th>
                    <th class="text-center">Nilai</th><th class="text-center">Predikat</th>
                    <th class="text-center">Durasi</th><th>Selesai Pukul</th>
                    <th class="text-center">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if ($selesai && $selesai->num_rows > 0): $no = 1;
                      while ($r = $selesai->fetch_assoc()):
                      $st = getStatusNilai((int)$r['nilai']); ?>
                <tr>
                    <td><?=$no++?></td>
                    <td><strong><?=htmlspecialchars($r['nama'])?></strong></td>
                    <td><?=htmlspecialchars($r['kelas']??'-')?></td>
                    <td><?=htmlspecialchars($r['nama_sekolah']??'-')?></td>
                    <td class="text-center fw-bold fs-6"><?=$r['nilai']?></td>
                    <td class="text-center">
                        <span class="badge bg-<?=$st['badge']?>"><?=$st['label']?></span>
                    </td>
                    <td class="text-center"><?=$r['durasi']?> mnt</td>
                    <td><?=$r['waktu_selesai'] ? date('H:i', strtotime($r['waktu_selesai'])) : '-'?></td>
                    <td class="text-center">
                        <?php
                        $_qPid = $conn->query("SELECT peserta_id FROM ujian WHERE id={$r['id']} LIMIT 1");
                        $pidSelesai = ($_qPid && $_qPid->num_rows > 0) ? (int)$_qPid->fetch_assoc()['peserta_id'] : 0;
                        ?>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Lanjutkan?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="aksi" value="reset_peserta">
                        <input type="hidden" name="peserta_id" value="<?= $pidSelesai ?>">
                        <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    <i class="bi bi-inbox me-2"></i>Belum ada peserta yang selesai hari ini
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
@keyframes popIn { 0%{transform:scale(.5);opacity:0} 70%{transform:scale(1.1)} 100%{transform:scale(1);opacity:1} }
.notif-toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    background: #10b981; color: #fff; border-radius: 12px;
    padding: 14px 20px; box-shadow: 0 8px 24px rgba(0,0,0,.2);
    font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 10px;
    animation: popIn .4s ease; max-width: 320px;
}
.notif-toast.fade-out { opacity: 0; transition: opacity .5s; }
</style>

<div id="toastContainer"></div>

<script>
// ── Monitoring: notifikasi saat ada peserta baru selesai ──────
let prevSedang  = parseInt(document.getElementById('cntSedang')?.textContent) || 0;
let prevSelesai = parseInt(document.getElementById('cntSelesaiHariIni')?.textContent) || 0;
let countdown   = 30;

// Buat suara notifikasi via Web Audio API (tanpa file eksternal)
function bunyiNotif() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [523, 659, 784].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.12);
            gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + i * 0.12 + 0.05);
            gain.gain.linearRampToValueAtTime(0, ctx.currentTime + i * 0.12 + 0.2);
            osc.start(ctx.currentTime + i * 0.12);
            osc.stop(ctx.currentTime + i * 0.12 + 0.25);
        });
    } catch(e) {}
}

function tampilToast(pesan, warna = '#10b981') {
    const div = document.createElement('div');
    div.className = 'notif-toast';
    div.style.background = warna;
    div.innerHTML = `<span style="font-size:20px">🔔</span><span>${pesan}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => { div.classList.add('fade-out'); setTimeout(() => div.remove(), 500); }, 4000);
}

function refreshStats() {
    fetch('?ajax=1')
        .then(r => r.json())
        .then(d => {
            const newSedang  = parseInt(d.aktif) || 0;
            const newSelesai = parseInt(d.selesai_hari_ini) || 0;

            // Update angka stat cards
            document.getElementById('cntSedang').textContent         = newSedang;
            document.getElementById('cntSelesaiHariIni').textContent  = newSelesai;
            document.getElementById('badgeSedang').textContent        = newSedang;

            // Notifikasi: ada peserta baru selesai
            const tambahSelesai = newSelesai - prevSelesai;
            if (tambahSelesai > 0 && prevSelesai > 0) {
                bunyiNotif();
                tampilToast(`${tambahSelesai} peserta baru selesai ujian! 🎉`);
            }

            // Notifikasi: ada peserta baru mulai
            const tambahSedang = newSedang - prevSedang;
            if (tambahSedang > 0 && prevSedang >= 0) {
                tampilToast(`${tambahSedang} peserta baru mulai ujian`, '#3b82f6');
            }

            // Jika jumlah berubah → reload agar tabel ikut update
            if (newSedang !== prevSedang || newSelesai !== prevSelesai) {
                prevSedang  = newSedang;
                prevSelesai = newSelesai;
                setTimeout(() => location.reload(), 1500);
            }
        })
        .catch(() => {});
}

// Countdown ticker
const ticker = setInterval(() => {
    countdown--;
    const el = document.getElementById('countdownNum');
    if (el) el.textContent = countdown;
    if (countdown % 10 === 0 && countdown > 0) refreshStats();
    if (countdown <= 0) { clearInterval(ticker); location.reload(); }
}, 1000);

refreshStats();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
