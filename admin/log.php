<?php
// ============================================================
// admin/log.php — Log Aktivitas Sistem
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// Handle hapus log lama (lebih dari 30 hari)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_lama'])) {
    $conn->query("DELETE FROM log_aktivitas WHERE waktu < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $hapus = $conn->affected_rows;
    logActivity($conn, 'Hapus log lama', "Dihapus $hapus baris");
    setFlash('success', "$hapus entri log lama berhasil dihapus.");
    redirect(BASE_URL . '/admin/log.php');
}

// Filter
$filterUser     = trim($_GET['user']     ?? '');
$filterAktivitas = trim($_GET['aktivitas'] ?? '');
$filterTgl      = trim($_GET['tgl']      ?? '');

$where = [];
if ($filterUser)      $where[] = "username LIKE '%" . $conn->real_escape_string($filterUser) . "%'";
if ($filterAktivitas) $where[] = "aktivitas LIKE '%" . $conn->real_escape_string($filterAktivitas) . "%'";
if ($filterTgl)       $where[] = "DATE(waktu)='" . $conn->real_escape_string($filterTgl) . "'";

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

function qs_log($conn, $sql, $col = 'c') {
    $r = $conn->query($sql); if (!$r) return 0;
    $row = $r->fetch_assoc(); $r->free(); return $row[$col] ?? 0;
}
$totalLog    = (int) qs_log($conn, "SELECT COUNT(*) AS c FROM log_aktivitas $whereStr");
$logs        = $conn->query("SELECT * FROM log_aktivitas $whereStr ORDER BY waktu DESC LIMIT 500");

// Statistik ringkas
$logHariIni  = (int) qs_log($conn, "SELECT COUNT(*) AS c FROM log_aktivitas WHERE DATE(waktu)=CURDATE()");
$logMingguIni= (int) qs_log($conn, "SELECT COUNT(*) AS c FROM log_aktivitas WHERE waktu >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$userAktif   = (int) qs_log($conn, "SELECT COUNT(DISTINCT user_id) AS c FROM log_aktivitas WHERE waktu >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

$pageTitle  = 'Log Aktivitas';
$activeMenu = 'log';
require_once __DIR__ . '/../includes/header.php';

// Ikono per aktivitas
function getAktiIcon(string $a): string {
    $map = [
        'login'         => '<span class="badge bg-success">🔐 Login</span>',
        'logout'        => '<span class="badge bg-secondary">🚪 Logout</span>',
        'mulai ujian'   => '<span class="badge bg-primary">▶ Mulai Ujian</span>',
        'selesai ujian' => '<span class="badge bg-info text-dark">✅ Selesai Ujian</span>',
        'import soal'   => '<span class="badge bg-warning text-dark">📥 Import Soal</span>',
        'import peserta'=> '<span class="badge bg-warning text-dark">📥 Import Peserta</span>',
        'backup'        => '<span class="badge bg-purple" style="background:#7c3aed">💾 Backup</span>',
        'pelanggaran'   => '<span class="badge bg-danger">⚠ Pelanggaran</span>',
    ];
    $a_lower = strtolower($a);
    foreach ($map as $key => $val) {
        if (str_contains($a_lower, $key)) return $val;
    }
    return '<span class="badge bg-light text-dark border">' . htmlspecialchars($a) . '</span>';
}
?>

<?= renderFlash() ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-list-check"></i></div>
            <div><div class="stat-label">Total Log</div><div class="stat-value"><?= number_format($totalLog) ?></div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-calendar-check"></i></div>
            <div><div class="stat-label">Hari Ini</div><div class="stat-value"><?= $logHariIni ?></div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-graph-up"></i></div>
            <div><div class="stat-label">7 Hari Terakhir</div><div class="stat-value"><?= $logMingguIni ?></div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-person-check"></i></div>
            <div><div class="stat-label">User Aktif (24j)</div><div class="stat-value"><?= $userAktif ?></div></div>
        </div>
    </div>
</div>

<!-- Filter & Actions -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-600">Username</label>
                <input type="text" name="user" class="form-control form-control-sm"
                       placeholder="Cari username..." value="<?= e($filterUser) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-600">Aktivitas</label>
                <input type="text" name="aktivitas" class="form-control form-control-sm"
                       placeholder="Cari aktivitas..." value="<?= e($filterAktivitas) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-600">Tanggal</label>
                <input type="date" name="tgl" class="form-control form-control-sm"
                       value="<?= e($filterTgl) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="log.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Log -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-journal-text text-primary me-2"></i>Log Aktivitas (<?= number_format($totalLog) ?> entri)</span>
        <form method="POST" onsubmit="return confirm('Hapus semua log lebih dari 30 hari?')">
            <button type="submit" name="hapus_lama" value="1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i>Hapus Log &gt;30 Hari
            </button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" id="tblLog">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Waktu</th>
                    <th>Username</th>
                    <th>Aktivitas</th>
                    <th>Detail</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs && $logs->num_rows > 0):
                  $no = 1;
                  while ($r = $logs->fetch_assoc()): ?>
            <tr>
                <td class="text-muted small"><?= $no++ ?></td>
                <td class="small text-nowrap"><?= date('d/m/Y H:i:s', strtotime($r['waktu'])) ?></td>
                <td>
                    <span class="badge bg-light text-dark border small">
                        <i class="bi bi-person me-1"></i><?= e($r['username'] ?? '-') ?>
                    </span>
                </td>
                <td><?= getAktiIcon($r['aktivitas']) ?></td>
                <td class="small text-muted"><?= e($r['detail'] ?? '') ?></td>
                <td><code class="small"><?= e($r['ip_address'] ?? '') ?></code></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center text-muted py-5">
                <i class="bi bi-journal-x fs-2 d-block mb-2 opacity-25"></i>
                Tidak ada log ditemukan
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
$(function () {
    if ($.fn.DataTable) {
        $('#tblLog').DataTable({
            pageLength: 25,
            order: [[1, 'desc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
