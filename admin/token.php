<?php
// ============================================================
// admin/token.php — Kelola Token Ujian
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

/* ── Generate token unik ─────────────────────────────────── */
function buatToken(mysqli $db): string {
    do {
        // Format: TKA + 6 huruf/angka besar
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $token = 'TKA';
        for ($i = 0; $i < 6; $i++) $token .= $chars[random_int(0, strlen($chars) - 1)];
        $cek = $db->query("SELECT id FROM token_ujian WHERE token='$token' LIMIT 1");
    } while ($cek && $cek->num_rows > 0);
    return $token;
}

/* ── HAPUS ───────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrfVerify();
    $id = (int)$_POST['id'];
    $conn->query("DELETE FROM token_ujian WHERE id=$id");
    setFlash('success', 'Token berhasil dihapus.');
    redirect(BASE_URL . '/admin/token.php');
}

/* ── TOGGLE STATUS ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle') {
    csrfVerify();
    $id    = (int)$_POST['id'];
    $_qTok = $conn->query("SELECT status FROM token_ujian WHERE id=$id LIMIT 1");
    $row   = ($_qTok && $_qTok->num_rows > 0) ? $_qTok->fetch_assoc() : null;
    if ($row) {
        $baru = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $conn->query("UPDATE token_ujian SET status='$baru' WHERE id=$id");
        setFlash('success', "Status token diubah menjadi <strong>$baru</strong>.");
    }
    redirect(BASE_URL . '/admin/token.php');
}

/* ── GENERATE / TAMBAH ───────────────────────────────────── */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    // Token bisa diisi manual atau auto-generate
    $token      = strtoupper(trim($_POST['token'] ?? ''));
    $tanggal    = trim($_POST['tanggal'] ?? '');
    $jam_mulai  = trim($_POST['jam_mulai'] ?? '') ?: null;
    $jam_selesai= trim($_POST['jam_selesai'] ?? '') ?: null;
    $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
    $status     = trim($_POST['status'] ?? 'aktif');

    if (!$token)   $errors[] = 'Token wajib diisi.';
    if (!$tanggal) $errors[] = 'Tanggal wajib diisi.';
    if (!preg_match('/^[A-Z0-9]{4,20}$/', $token))
        $errors[] = 'Token hanya boleh huruf kapital & angka, 4–20 karakter.';

    if (!$errors) {
        $cek = $conn->prepare("SELECT id FROM token_ujian WHERE token=? LIMIT 1");
        $cek->bind_param('s', $token); $cek->execute();
        if ($cek->get_result()->num_rows > 0)
            $errors[] = "Token <strong>$token</strong> sudah ada.";
        $cek->close();
    }

    if (!$errors) {
        $jmStr = $jam_mulai   ? "'$jam_mulai'"   : 'NULL';
        $jsStr = $jam_selesai ? "'$jam_selesai'" : 'NULL';
        $ktStr = $keterangan  ? "'".$conn->real_escape_string($keterangan)."'" : 'NULL';
        $conn->query("INSERT INTO token_ujian (token,tanggal,jam_mulai,jam_selesai,keterangan,status)
                      VALUES ('$token','$tanggal',$jmStr,$jsStr,$ktStr,'$status')");
        setFlash('success', "Token <strong>$token</strong> berhasil dibuat.");
        redirect(BASE_URL . '/admin/token.php');
    }
}

/* ── AUTO GENERATE (AJAX) ───────────────────────────────── */
if (isset($_GET['generate'])) {
    header('Content-Type: application/json');
    echo json_encode(['token' => buatToken($conn)]);
    exit;
}

/* ── EDIT ────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id          = (int)$_POST['id'];
    $tanggal     = trim($_POST['tanggal'] ?? '');
    $status      = trim($_POST['status'] ?? 'aktif');
    $jam_mulai   = trim($_POST['jam_mulai'] ?? '') ?: null;
    $jam_selesai = trim($_POST['jam_selesai'] ?? '') ?: null;
    $keterangan  = trim($_POST['keterangan'] ?? '') ?: null;
    if ($tanggal) {
        $jmStr = $jam_mulai   ? "'" . $conn->real_escape_string($jam_mulai)   . "'" : 'NULL';
        $jsStr = $jam_selesai ? "'" . $conn->real_escape_string($jam_selesai) . "'" : 'NULL';
        $ktStr = $keterangan  ? "'" . $conn->real_escape_string($keterangan)  . "'" : 'NULL';
        $tgl   = $conn->real_escape_string($tanggal);
        $sts   = $conn->real_escape_string($status);
        $conn->query("UPDATE token_ujian
                      SET tanggal='$tgl', status='$sts',
                          jam_mulai=$jmStr, jam_selesai=$jsStr, keterangan=$ktStr
                      WHERE id=$id");
        setFlash('success', 'Token berhasil diperbarui.');
    }
    redirect(BASE_URL . '/admin/token.php');
}

/* ── AUTO NONAKTIF: token lewat jam_selesai ─────────────── */
// Jalankan setiap kali halaman token dibuka — update token yang sudah lewat
$nowDate = date('Y-m-d');
$nowTime = date('H:i:s');
$conn->query("
    UPDATE token_ujian
    SET status = 'nonaktif'
    WHERE status = 'aktif'
      AND tanggal = '$nowDate'
      AND jam_selesai IS NOT NULL
      AND jam_selesai < '$nowTime'
");
$autoNonaktif = $conn->affected_rows;
if ($autoNonaktif > 0) {
    logActivity($conn, 'Auto Nonaktif Token', "$autoNonaktif token dinonaktifkan otomatis (jam selesai terlewat)");
}

/* ── DATA ────────────────────────────────────────────────── */
$list = $conn->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM ujian WHERE token_id=t.id) AS jml_digunakan
    FROM token_ujian t
    ORDER BY t.tanggal DESC, t.id DESC
");

$editData = null;
if (isset($_GET['edit'])) {
    $eid      = (int)$_GET['edit'];
    $er       = $conn->query("SELECT * FROM token_ujian WHERE id=$eid LIMIT 1");
    $editData = $er ? $er->fetch_assoc() : null;
}

$pageTitle  = 'Kelola Token Ujian';
$activeMenu = 'token';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-key-fill me-2 text-warning"></i>Kelola Token Ujian</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Token Ujian</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg me-1"></i>Buat Token Baru
    </button>
</div>

<?= renderFlash() ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<!-- Info box -->
<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div>
        <strong>Cara kerja Token Ujian:</strong>
        Token harus dimasukkan peserta sebelum memulai ujian. Pastikan token berstatus
        <span class="badge bg-success">Aktif</span> pada hari pelaksanaan ujian.
        Satu token dapat digunakan oleh semua peserta pada tanggal yang ditentukan.
    </div>
</div>

<!-- Tabel token -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Daftar Token Ujian</span>
        <span class="badge bg-primary"><?= $list ? $list->num_rows : 0 ?> token</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblToken" class="table table-hover datatable mb-0">
                <thead><tr>
                    <th>#</th>
                    <th>Token</th>
                    <th>Tanggal Berlaku</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Digunakan</th>
                    <th>Dibuat</th>
                    <th class="text-center" style="width:120px">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if ($list && $list->num_rows > 0):
                      $no = 1; while ($row = $list->fetch_assoc()):
                      $isHariIni = $row['tanggal'] === date('Y-m-d');
                      $isLewat   = $row['tanggal'] < date('Y-m-d');
                ?>
                <tr class="<?= $isHariIni && $row['status']==='aktif' ? 'table-success' : ($isLewat ? 'table-light' : '') ?>">
                    <td><?= $no++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <code class="fs-5 fw-bold text-primary letter-spacing-2">
                                <?= htmlspecialchars($row['token']) ?>
                            </code>
                            <button class="btn btn-xs btn-outline-secondary py-0 px-1"
                                    onclick="navigator.clipboard.writeText('<?= $row['token'] ?>');this.innerHTML='✓'"
                                    title="Salin token" style="font-size:11px">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <?= date('d F Y', strtotime($row['tanggal'])) ?>
                        <?php if ($isHariIni): ?>
                            <span class="badge bg-success ms-1">Hari Ini</span>
                        <?php elseif ($isLewat): ?>
                            <span class="badge bg-secondary ms-1">Lewat</span>
                        <?php else: ?>
                            <span class="badge bg-info ms-1">Mendatang</span>
                        <?php endif; ?>
                        <?php if (!empty($row['jam_mulai']) && !empty($row['jam_selesai'])): ?>
                            <div class="mt-1">
                                <span class="badge bg-primary" style="font-size:11px">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= substr($row['jam_mulai'],0,5) ?> – <?= substr($row['jam_selesai'],0,5) ?>
                                </span>
                                <?php if (!empty($row['keterangan'])): ?>
                                    <span class="text-muted ms-1" style="font-size:11px"><?= e($row['keterangan']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                            <button type="submit"
                                class="badge border-0 text-decoration-none bg-<?= $row['status']==='aktif'?'success':'secondary' ?>"
                                style="cursor:pointer">
                                <?= $row['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?>
                            </button>
                        </form>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-info"><?= $row['jml_digunakan'] ?> peserta</span>
                    </td>
                    <td class="small text-muted">
                        <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                    </td>
                    <td class="text-center">
                        <a href="?edit=<?= $row['id'] ?>"
                           class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                            <i class="bi bi-pencil"></i></a>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Hapus token \'<?= $row['token'] ?>\'?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-key fs-2 d-block mb-2"></i>Belum ada token ujian
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── MODAL TAMBAH ── -->
<div class="modal fade <?= $errors ? 'show' : '' ?>" id="modalTambah" tabindex="-1"
     <?= $errors ? 'style="display:block"' : '' ?>>
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah">
<?= csrfField() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>Buat Token Ujian Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Token <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="token" id="inputToken" class="form-control fw-bold"
                               style="font-family:monospace;font-size:16px;letter-spacing:3px"
                               maxlength="20" required
                               value="<?= htmlspecialchars(strtoupper($_POST['token'] ?? '')) ?>"
                               placeholder="cth: TKAABCDEF">
                        <button type="button" class="btn btn-outline-secondary" id="btnGenerate">
                            <i class="bi bi-arrow-clockwise me-1"></i>Auto
                        </button>
                    </div>
                    <div class="form-text">Huruf kapital & angka, 4–20 karakter. Klik "Auto" untuk generate otomatis.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tanggal Berlaku <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" class="form-control" required
                           value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jam Sesi <small class="text-muted fw-normal">(opsional — kosongkan jika tidak dibatasi jam)</small></label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="time" name="jam_mulai" class="form-control"
                               value="<?= htmlspecialchars($_POST['jam_mulai'] ?? '') ?>"
                               placeholder="Mulai">
                        <span class="text-muted">–</span>
                        <input type="time" name="jam_selesai" class="form-control"
                               value="<?= htmlspecialchars($_POST['jam_selesai'] ?? '') ?>"
                               placeholder="Selesai">
                    </div>
                    <div class="form-text">Contoh: 07:00 – 08:00 untuk Sesi 1, 09:00 – 10:00 untuk Sesi 2</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Keterangan Sesi</label>
                    <input type="text" name="keterangan" class="form-control"
                           placeholder="Contoh: Sesi 1 - Kelompok A"
                           value="<?= htmlspecialchars($_POST['keterangan'] ?? '') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Simpan Token
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL EDIT ── -->
<?php if ($editData): ?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
<?= csrfField() ?>
            <input type="hidden" name="id"   value="<?= $editData['id'] ?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Token: <code><?= htmlspecialchars($editData['token']) ?></code></h5>
                <a href="<?= BASE_URL ?>/admin/token.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Token</label>
                    <input type="text" class="form-control fw-bold disabled"
                           value="<?= htmlspecialchars($editData['token']) ?>" disabled
                           style="font-family:monospace;font-size:16px;letter-spacing:3px">
                    <div class="form-text">Token tidak bisa diubah.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tanggal Berlaku</label>
                    <input type="date" name="tanggal" class="form-control"
                           value="<?= htmlspecialchars($editData['tanggal']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jam Sesi <small class="text-muted fw-normal">(opsional)</small></label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="time" name="jam_mulai" class="form-control"
                               value="<?= htmlspecialchars($editData['jam_mulai'] ?? '') ?>">
                        <span class="text-muted">–</span>
                        <input type="time" name="jam_selesai" class="form-control"
                               value="<?= htmlspecialchars($editData['jam_selesai'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Keterangan Sesi</label>
                    <input type="text" name="keterangan" class="form-control"
                           placeholder="Contoh: Sesi 1 - Kelompok A"
                           value="<?= htmlspecialchars($editData['keterangan'] ?? '') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="aktif"     <?= $editData['status']==='aktif'     ?'selected':''?>>Aktif</option>
                        <option value="nonaktif"  <?= $editData['status']==='nonaktif'  ?'selected':''?>>Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?= BASE_URL ?>/admin/token.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-save me-1"></i>Simpan
                </button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script>
// Auto generate token
document.getElementById('btnGenerate')?.addEventListener('click', function () {
    fetch('?generate=1')
        .then(r => r.json())
        .then(d => {
            document.getElementById('inputToken').value = d.token;
        });
});
// Buka modal jika ada error
<?php if ($errors): ?>
document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('modalTambah')).show());
<?php endif; ?>
</script>

<style>
.letter-spacing-2 { letter-spacing: 3px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
