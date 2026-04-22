<?php
// ============================================================
// admin/pengaturan.php — Pengaturan Sistem
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

// Handle simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    // Handle upload logo
    if (!empty($_FILES['logo_file']['name'])) {
        $uploadDir = __DIR__ . '/../assets/uploads/logo/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Validasi MIME type nyata (SVG diizinkan tapi cek tersendiri karena bisa berisi JS)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['logo_file']['tmp_name']);
        finfo_close($finfo);

        $allowedMime = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        $ext         = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($mime, $allowedMime)) {
            setFlash('danger', 'Format file tidak didukung. Gunakan JPG, PNG, GIF, SVG, atau WEBP.');
            redirect(BASE_URL . '/admin/pengaturan.php');
        }
        if ($_FILES['logo_file']['size'] > 2 * 1024 * 1024) {
            setFlash('danger', 'Ukuran file terlalu besar. Maksimal 2MB.');
            redirect(BASE_URL . '/admin/pengaturan.php');
        }

        // Hapus logo lama jika ada
        $logoLama = getSetting($conn, 'logo_file_path', '');
        if ($logoLama && file_exists(__DIR__ . '/../' . $logoLama)) {
            @unlink(__DIR__ . '/../' . $logoLama);
        }

        $namaFile = 'logo_' . time() . '.' . $ext;
        $dest     = $uploadDir . $namaFile;
        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
            $path = 'assets/uploads/logo/' . $namaFile;
            setSetting($conn, 'logo_file_path', $path);
            setSetting($conn, 'logo_url', ''); // kosongkan URL jika pakai file
        }
    }

    // Hapus logo
    if (isset($_POST['hapus_logo'])) {
        $logoLama = getSetting($conn, 'logo_file_path', '');
        if ($logoLama && file_exists(__DIR__ . '/../' . $logoLama)) {
            @unlink(__DIR__ . '/../' . $logoLama);
        }
        setSetting($conn, 'logo_file_path', '');
        setSetting($conn, 'logo_url', '');
        setFlash('success', 'Logo berhasil dihapus.');
        redirect(BASE_URL . '/admin/pengaturan.php');
    }

    $keys = [
        'nama_aplikasi', 'nama_kecamatan', 'durasi_ujian', 'jumlah_soal',
        'nama_penyelenggara', 'mata_pelajaran',
        'maks_pelanggaran', 'display_info', 'display_video_url', 'logo_url',
        'kkm', 'tampil_pembahasan', 'ujian_ulang', 'tahun_pelajaran',
        'wa_api_key', 'wa_sender', 'wa_auto_send',
        'mode_maintenance', 'pesan_maintenance',
        'wa_share_hasil', 'acak_pilihan',
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            setSetting($conn, $key, trim($_POST[$key]));
        }
    }
    // Checkbox yang tidak ada di POST saat tidak dicentang
    foreach (['mode_maintenance', 'acak_pilihan', 'wa_share_hasil'] as $chk) {
        if (!isset($_POST[$chk])) setSetting($conn, $chk, '0');
    }
    logActivity($conn, 'Update Pengaturan', implode(', ', array_filter(array_map(
        fn($k) => isset($_POST[$k]) ? "$k=" . substr(trim($_POST[$k]), 0, 30) : null,
        ['nama_aplikasi', 'kkm', 'jumlah_soal', 'mode_maintenance', 'ujian_ulang']
    ))));
    setFlash('success', 'Pengaturan berhasil disimpan.');
    redirect(BASE_URL . '/admin/pengaturan.php');
}

// Ambil semua setting
$settings = [];
$res = $conn->query("SELECT setting_key, setting_val FROM pengaturan");
if ($res) while ($r = $res->fetch_assoc()) {
    $settings[$r['setting_key']] = $r['setting_val'];
}
$logoFilePath = $settings['logo_file_path'] ?? '';
$logoUrl      = $settings['logo_url'] ?? '';
$logoAktif    = $logoFilePath ? BASE_URL . '/' . $logoFilePath : $logoUrl;

function s(array $s, string $k, string $d = ''): string {
    return htmlspecialchars($s[$k] ?? $d, ENT_QUOTES);
}

$pageTitle  = 'Pengaturan Sistem';
$activeMenu = 'pengaturan';
require_once __DIR__ . '/../includes/header.php';
?>

<?= renderFlash() ?>

<?php if (getSetting($conn, 'mode_maintenance', '0') === '1'): ?>
<div class="alert alert-danger d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-tools fs-4 flex-shrink-0"></i>
    <div>
        <strong>Mode Maintenance Aktif!</strong>
        Halaman ujian peserta sedang ditutup sementara. Peserta tidak bisa masuk ujian.
        <a href="#maintenance-card" class="alert-link ms-2">Matikan sekarang →</a>
    </div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<?= csrfField() ?>
<div class="row g-4">

    <!-- ── Mode Maintenance ── -->
    <div class="col-12" id="maintenance-card">
        <div class="card shadow-sm border-warning">
            <div class="card-header fw-700 bg-warning text-dark">
                <i class="bi bi-tools me-2"></i>Mode Maintenance
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="mode_maintenance" value="1" id="toggleMaintenance"
                                   <?= (($settings['mode_maintenance'] ?? '0') === '1') ? 'checked' : '' ?>
                                   style="width:3em;height:1.5em">
                            <label class="form-check-label fw-bold ms-2" for="toggleMaintenance">
                                <?= (($settings['mode_maintenance'] ?? '0') === '1') ? '<span class="text-danger">● Aktif</span>' : '<span class="text-success">○ Nonaktif</span>' ?>
                            </label>
                        </div>
                        <div class="form-text mt-1">Jika aktif, peserta tidak bisa mengakses halaman ujian.</div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-600">Pesan untuk Peserta</label>
                        <input type="text" name="pesan_maintenance" class="form-control"
                               value="<?= s($settings, 'pesan_maintenance', 'Sistem sedang dalam pemeliharaan. Silakan tunggu.') ?>"
                               placeholder="Pesan yang ditampilkan ke peserta...">
                        <div class="form-text">Ditampilkan di halaman login peserta saat maintenance aktif.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Identitas Aplikasi ── -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-700">
                <i class="bi bi-gear-fill text-primary me-2"></i>Identitas Aplikasi
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-600">Nama Aplikasi</label>
                    <input type="text" name="nama_aplikasi" class="form-control"
                           value="<?= s($settings,'nama_aplikasi','Sistem CBT TKA Kecamatan') ?>"
                           placeholder="Sistem CBT TKA Kecamatan">
                    <div class="form-text">Tampil di browser title & header aplikasi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Nama Kecamatan</label>
                    <input type="text" name="nama_kecamatan" class="form-control"
                           value="<?= s($settings,'nama_kecamatan','Kecamatan Contoh') ?>"
                           placeholder="Kecamatan ...">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Nama Penyelenggara</label>
                    <input type="text" name="nama_penyelenggara" class="form-control"
                           value="<?= s($settings,'nama_penyelenggara','') ?>"
                           placeholder="cth: Suku Dinas Pendidikan Wilayah II Jakarta Pusat">
                    <div class="form-text">Tampil di halaman login siswa, di bawah nama aplikasi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Mata Pelajaran / Jenis Ujian</label>
                    <input type="text" name="mata_pelajaran" class="form-control"
                           value="<?= s($settings,'mata_pelajaran','') ?>"
                           placeholder="cth: Matematika · CBT Online">
                    <div class="form-text">Tampil di halaman login siswa.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Tahun Pelajaran</label>
                    <input type="text" name="tahun_pelajaran" class="form-control"
                           value="<?= s($settings,'tahun_pelajaran', date('Y').'/'.(date('Y')+1)) ?>"
                           placeholder="cth: 2025/2026">
                    <div class="form-text">Tampil di kartu ujian, laporan, dan halaman login.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Logo Aplikasi <span class="text-muted fw-400">(opsional)</span></label>

                    <?php if ($logoAktif): ?>
                    <div class="d-flex align-items-center gap-3 mb-2 p-3 bg-light rounded">
                        <img src="<?= htmlspecialchars($logoAktif) ?>" alt="Logo"
                             style="height:60px;max-width:120px;object-fit:contain;border-radius:6px;background:#fff;padding:4px;border:1px solid #e2e8f0">
                        <div>
                            <div class="text-success fw-700" style="font-size:13px"><i class="bi bi-check-circle me-1"></i>Logo terpasang</div>
                            <button type="submit" name="hapus_logo" value="1"
                                    class="btn btn-sm btn-outline-danger mt-1"
                                    onclick="return confirm('Hapus logo?')">
                                <i class="bi bi-trash me-1"></i>Hapus Logo
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label fw-600" style="font-size:13px">Upload File Logo</label>
                        <input type="file" name="logo_file" class="form-control"
                               accept=".jpg,.jpeg,.png,.gif,.svg,.webp"
                               onchange="previewLogo(this)">
                        <div class="form-text">Format: JPG, PNG, GIF, SVG, WEBP. Maksimal 2MB.</div>
                        <img id="logoPreview" src="" alt="Preview"
                             style="display:none;height:60px;margin-top:8px;border-radius:6px;border:1px solid #e2e8f0;padding:4px;background:#fff">
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-600" style="font-size:13px">Atau pakai URL Logo</label>
                        <input type="url" name="logo_url" class="form-control"
                               value="<?= s($settings,'logo_url') ?>"
                               placeholder="https://...">
                        <div class="form-text">Kosongkan jika pakai upload file di atas.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Konfigurasi CBT ── -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-700">
                <i class="bi bi-clock-fill text-success me-2"></i>Konfigurasi CBT
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-600">Durasi Ujian (menit)</label>
                    <div class="input-group">
                        <input type="number" name="durasi_ujian" class="form-control"
                               value="<?= s($settings,'durasi_ujian','60') ?>"
                               min="10" max="300">
                        <span class="input-group-text">menit</span>
                    </div>
                    <div class="form-text">Durasi default jika jadwal tidak menentukan durasi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Jumlah Soal per Ujian</label>
                    <?php $totalSoalBank = (int)$conn->query("SELECT COUNT(*) AS c FROM soal")->fetch_assoc()['c']; ?>
                    <div class="input-group">
                        <input type="number" name="jumlah_soal" class="form-control"
                               value="<?= s($settings,'jumlah_soal','20') ?>"
                               min="5" max="<?= $totalSoalBank ?>">
                        <span class="input-group-text">soal</span>
                    </div>
                    <div class="form-text">
                        Jumlah soal yang diambil secara acak per sesi ujian.
                        <span class="text-primary fw-600">Bank soal tersedia: <?= $totalSoalBank ?> soal.</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Nilai KKM <small class="text-muted fw-normal">(Kriteria Ketuntasan Minimal)</small></label>
                    <div class="input-group">
                        <input type="number" name="kkm" class="form-control"
                               value="<?= s($settings,'kkm','60') ?>"
                               min="0" max="100">
                        <span class="input-group-text">/ 100</span>
                    </div>
                    <div class="form-text">Nilai minimum yang dianggap lulus. Tampil di laporan hasil.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">
                        Maks Perpindahan Tab
                        <span class="badge bg-danger ms-1">Anti-Kecurangan</span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="maks_pelanggaran" class="form-control"
                               value="<?= s($settings,'maks_pelanggaran','3') ?>"
                               min="1" max="10">
                        <span class="input-group-text">kali</span>
                    </div>
                    <div class="form-text">Ujian otomatis diakhiri jika peserta berpindah tab melebihi batas ini.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Tampilkan Pembahasan</label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tampil_pembahasan" value="1" id="pb_ya"
                                   <?= ($settings['tampil_pembahasan']??'1')==='1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pb_ya">
                                <span class="text-success fw-600">Ya</span> — tampilkan pembahasan setelah ujian selesai
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tampil_pembahasan" value="0" id="pb_tidak"
                                   <?= ($settings['tampil_pembahasan']??'1')==='0' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pb_tidak">
                                <span class="text-danger fw-600">Tidak</span> — sembunyikan pembahasan
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-600">Izinkan Ujian Ulang</label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ujian_ulang" value="1" id="ul_ya"
                                   <?= ($settings['ujian_ulang']??'0')==='1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ul_ya">
                                <span class="text-success fw-600">Ya</span> — peserta boleh ujian lebih dari 1x
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ujian_ulang" value="0" id="ul_tidak"
                                   <?= ($settings['ujian_ulang']??'0')==='0' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ul_tidak">
                                <span class="text-danger fw-600">Tidak</span> — satu kali submit, tidak bisa diulang
                            </label>
                        </div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="acak_pilihan" value="1" id="acakPilihan"
                               <?= (($settings['acak_pilihan'] ?? '0') === '1') ? 'checked' : '' ?>
                               style="width:2.5em;height:1.4em">
                        <label class="form-check-label fw-600 ms-2" for="acakPilihan">
                            Acak Urutan Pilihan Jawaban
                        </label>
                    </div>
                    <div class="form-text">Jika aktif, urutan pilihan A/B/C/D diacak per peserta sehingga tidak bisa menyontek.</div>
                </div>
                <div class="mb-0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="wa_share_hasil" value="1" id="waShareHasil"
                               <?= (($settings['wa_share_hasil'] ?? '1') === '1') ? 'checked' : '' ?>
                               style="width:2.5em;height:1.4em">
                        <label class="form-check-label fw-600 ms-2" for="waShareHasil">
                            Tampilkan Tombol Bagikan ke WhatsApp
                        </label>
                    </div>
                    <div class="form-text">Tampilkan tombol berbagi hasil ujian ke WA di halaman selesai ujian.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Layar Tunggu / Videotron ── -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header fw-700">
                <i class="bi bi-display text-info me-2"></i>Layar Tunggu / Videotron
                <a href="<?= BASE_URL ?>/display/" target="_blank"
                   class="btn btn-sm btn-outline-info ms-2">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Preview Layar
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-600">Teks Informasi Ujian</label>
                        <textarea name="display_info" class="form-control" rows="3"
                                  placeholder="Selamat datang di Ujian CBT TKA..."><?= s($settings,'display_info') ?></textarea>
                        <div class="form-text">Teks yang tampil di layar tunggu sebelum ujian dimulai.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-600">URL Video / YouTube Embed</label>
                        <input type="url" name="display_video_url" class="form-control"
                               value="<?= s($settings,'display_video_url') ?>"
                               placeholder="https://www.youtube.com/embed/...">
                        <div class="form-text">
                            Gunakan URL embed YouTube: <code>https://www.youtube.com/embed/VIDEO_ID?autoplay=1&loop=1&mute=1</code>
                        </div>
                        <?php if (!empty($settings['display_video_url'])): ?>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small class="text-success">✅ Video sudah dikonfigurasi</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── WhatsApp Gateway ── -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header fw-700">
                <i class="bi bi-whatsapp text-success me-2"></i>WhatsApp Gateway (Opsional)
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-600">API Key / Token WA</label>
                        <input type="text" name="wa_api_key" class="form-control"
                               value="<?= s($settings,'wa_api_key') ?>"
                               placeholder="Masukkan API Key...">
                        <div class="form-text">Mendukung layanan seperti Fonnte / Wablas.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-600">Nomor Pengirim</label>
                        <input type="text" name="wa_sender" class="form-control"
                               value="<?= s($settings,'wa_sender') ?>"
                               placeholder="6287781743048">
                        <div class="form-text">Nomor WA yang terhubung ke gateway.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-600">Kirim Notifikasi Otomatis</label>
                        <select name="wa_auto_send" class="form-select">
                            <option value="0" <?= ($settings['wa_auto_send']??'0')==='0'?'selected':'' ?>>Tidak Aktif</option>
                            <option value="1" <?= ($settings['wa_auto_send']??'0')==='1'?'selected':'' ?>>Aktif (Kirim Nilai Setelah Ujian)</option>
                        </select>
                        <div class="form-text">Kirim nilai ke nomor peserta/orang tua setelah ujian selesai.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tombol Simpan -->
    <div class="col-12">
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-x me-1"></i>Batal
            </a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check2 me-1"></i>Simpan Pengaturan
            </button>
        </div>
    </div>

</div>
</form>

<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
