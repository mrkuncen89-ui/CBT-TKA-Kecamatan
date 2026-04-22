<?php
// ============================================================
// admin/import_soal.php  — Import Soal dari Excel (.xls/.xlsx)
// Kolom: kategori | pertanyaan | pilihan_a | pilihan_b |
//         pilihan_c | pilihan_d | jawaban_benar | tipe_soal (opsional: pg/bs/mcma)
// Untuk MCMA: jawaban_benar = "a,b" atau "ab" (huruf digabung)
// Untuk BS  : jawaban_benar = "benar" atau "salah", pilihan_c/d kosong
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../vendor/simplexlsx/SimpleXLSX.php';
requireLogin('admin_kecamatan');

/* ── Semua kategori untuk lookup nama → id ─────────────────── */
$katRes = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$katArr = [];   // nama lowercase → id
$katById = [];  // id → nama
if ($katRes) while ($k = $katRes->fetch_assoc()) {
    $katArr[strtolower(trim($k['nama_kategori']))] = (int)$k['id'];
    $katById[(int)$k['id']] = $k['nama_kategori'];
}

/* ── Kategori untuk dropdown filter ────────────────────────── */
$results   = null;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerify();
    /* ── Validasi file ───────────────────────────────────────── */
    if (empty($_FILES['file_excel']['name']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File Excel wajib dipilih dan berhasil diupload.';
    } else {
        $origName = $_FILES['file_excel']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $maxSize  = 5 * 1024 * 1024; // 5 MB

        if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
            $errors[] = 'Format file harus <strong>.xlsx</strong>, <strong>.xls</strong>, atau <strong>.csv</strong>.';
        } elseif ($_FILES['file_excel']['size'] > $maxSize) {
            $errors[] = 'Ukuran file maksimal 5 MB.';
        }
    }

    /* ── Pilihan kategori default (opsional) ─────────────────── */
    $defaultKatId = (int)($_POST['default_kategori_id'] ?? 0);

    if (!$errors) {
        $tmpFile = $_FILES['file_excel']['tmp_name'];
        $rows    = [];

        if ($ext === 'csv') {
            // Handle CSV
            if (($handle = fopen($tmpFile, "r")) !== FALSE) {
                // Deteksi BOM UTF-8
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $rows[] = $data;
                }
                // Jika hanya 1 kolom, mungkin pemisahnya titik koma (;)
                if (count($rows) > 0 && count($rows[0]) === 1 && str_contains($rows[0][0], ';')) {
                    rewind($handle);
                    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
                    $rows = [];
                    while (($data = fgetcsv($handle, 10000, ";")) !== FALSE) {
                        $rows[] = $data;
                    }
                }
                fclose($handle);
            }
            if (empty($rows)) {
                $errors[] = 'File CSV kosong atau tidak bisa dibaca.';
            }
        } else {
            // Handle XLSX
            if ($ext === 'xls') {
                $errors[] = 'Format <strong>.xls</strong> (Excel 97-2003) tidak didukung secara langsung. '
                          . 'Silakan <strong>Save As</strong> file Anda ke format <strong>.xlsx</strong> (Excel Workbook) atau <strong>.csv</strong>.';
            } else {
                if (!class_exists('\ZipArchive') && !extension_loaded('zip')) {
                    $errors[] = 'Ekstensi <strong>zip</strong> (ZipArchive) belum aktif di server Anda. '
                              . 'Silakan gunakan format <strong>.csv</strong> sebagai alternatif, atau aktifkan ekstensi zip di konfigurasi PHP server Anda.';
                } else {
                    $xlsx = SimpleXLSX::parse($tmpFile);
                    if (!$xlsx) {
                        $errors[] = 'File tidak bisa dibaca. Pastikan format <strong>.xlsx</strong> (bukan .xls yang direname) dan tidak terproteksi password.';
                    } else {
                        $rows = $xlsx->rows(0);
                    }
                }
            }
        }

        if (!$errors && !empty($rows)) {
            $berhasil = 0;
            $gagal    = 0;
            $log      = [];

            /* Deteksi baris header */
            $startRow = 0;
            if (!empty($rows[0])) {
                $h0 = strtolower(trim($rows[0][0] ?? ''));
                $h1 = strtolower(trim($rows[0][1] ?? ''));
                if (in_array($h0, ['kategori','no','#','soal']) || in_array($h1, ['pertanyaan','soal'])) {
                    $startRow = 1;
                }
            }

            /* Prepare statement */
            $stmt = $conn->prepare(
                "INSERT INTO soal (kategori_id, tipe_soal, pertanyaan, teks_bacaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // Helper: cocokkan teks jawaban ke huruf pilihan
            function cocokkanJawaban(string $jwbRaw, string $pA, string $pB, string $pC, string $pD): string {
                if (in_array($jwbRaw, ['a','b','c','d'])) return $jwbRaw;
                $pilihanMap = [
                    'a' => strtolower(trim($pA)),
                    'b' => strtolower(trim($pB)),
                    'c' => strtolower(trim($pC)),
                    'd' => strtolower(trim($pD)),
                ];
                $cari = strtolower(trim($jwbRaw));
                foreach ($pilihanMap as $huruf => $teks) {
                    if ($teks !== '' && $teks === $cari) return $huruf;
                }
                foreach ($pilihanMap as $huruf => $teks) {
                    if ($teks !== '' && (str_contains($cari, $teks) || str_contains($teks, $cari))) return $huruf;
                }
                return '';
            }

            for ($i = $startRow; $i < count($rows); $i++) {
                $row = $rows[$i];
                /* ── Parsing 9 kolom ─────────────────────────────── */
                // Kolom A: kategori (nama atau ID)
                // Kolom B: teks_bacaan (opsional, kosongkan jika tidak ada)
                // Kolom C: pertanyaan
                // Kolom D: pilihan_a
                // Kolom E: pilihan_b
                // Kolom F: pilihan_c
                // Kolom G: pilihan_d
                // Kolom H: jawaban_benar (a/b/c/d | benar/salah | a,b untuk MCMA)
                // Kolom I: tipe_soal (pg/bs/mcma) — opsional, default pg
                // Kompatibel mundur: jika hanya 8 kolom, dianggap format lama (tanpa teks_bacaan)

                while (count($row) < 9) $row[] = '';

                $kolomKat = trim($row[0] ?? '');

                // Deteksi format: lihat kolom I (index 8)
                // Format BARU (9 kolom): kolom I berisi tipe soal (pg/bs/mcma)
                // Format LAMA (8 kolom): kolom I kosong, kolom H berisi tipe soal
                $kol9 = strtolower(trim($row[8] ?? ''));
                $isFormatBaru = in_array($kol9, ['pg','bs','mcma','PG','BS','MCMA'])
                                || strlen(trim($row[1] ?? '')) > 5; // kolom B panjang = teks bacaan

                if ($isFormatBaru) {
                    // Format baru: A=kat, B=teks_bacaan, C=pert, D=pA, E=pB, F=pC, G=pD, H=jwb, I=tipe
                    $teksBacaan = trim($row[1] ?? '');
                    $pert       = trim($row[2] ?? '');
                    $pA         = trim($row[3] ?? '');
                    $pB         = trim($row[4] ?? '');
                    $pC         = trim($row[5] ?? '');
                    $pD         = trim($row[6] ?? '');
                    $jwbRaw     = strtolower(trim($row[7] ?? ''));
                    $tipe       = strtolower(trim($row[8] ?? 'pg'));
                } else {
                    // Format lama: A=kat, B=pert, C=pA, D=pB, E=pC, F=pD, G=jwb, H=tipe
                    $teksBacaan = '';
                    $pert       = trim($row[1] ?? '');
                    $pA         = trim($row[2] ?? '');
                    $pB         = trim($row[3] ?? '');
                    $pC         = trim($row[4] ?? '');
                    $pD         = trim($row[5] ?? '');
                    $jwbRaw     = strtolower(trim($row[6] ?? ''));
                    $tipe       = strtolower(trim($row[7] ?? 'pg'));
                }
                $tipe = strtolower(trim($tipe));
                if (!in_array($tipe, ['pg','bs','mcma'])) $tipe = 'pg';

                // Normalisasi jawaban berdasarkan tipe
                if ($tipe === 'bs') {
                    // BS: jawaban bisa huruf (a/b/c/d) atau teks (benar/salah)
                    if (in_array($jwbRaw, ['a','b','c','d'])) {
                        $jwb = $jwbRaw; // sudah huruf, langsung pakai
                    } elseif (in_array($jwbRaw, ['benar','salah'])) {
                        $jwb = $jwbRaw;
                    } elseif (in_array($jwbRaw, ['true','ya','iya','betul','correct'])) {
                        $jwb = 'benar';
                    } elseif (in_array($jwbRaw, ['false','tidak','salah','wrong','tidak benar'])) {
                        $jwb = 'salah';
                    } else {
                        // Coba cocokkan dengan teks pilihan
                        $cocok = cocokkanJawaban($jwbRaw, $pA, $pB, $pC, $pD);
                        $jwb = $cocok ?: 'benar';
                    }
                    // Konversi huruf ke benar/salah jika pA=Benar, pB=Salah
                    if (in_array($jwb, ['a','b','c','d'])) {
                        // Cari teks pilihan yang dipilih
                        $pilihanArr = ['a'=>$pA,'b'=>$pB,'c'=>$pC,'d'=>$pD];
                        $teksTerpilih = strtolower(trim($pilihanArr[$jwb] ?? ''));
                        if ($teksTerpilih === 'benar' || $teksTerpilih === 'true') $jwb = 'benar';
                        elseif ($teksTerpilih === 'salah' || $teksTerpilih === 'false') $jwb = 'salah';
                        // Jika bukan benar/salah, tetap pakai huruf (sistem simpan sebagai huruf)
                    }
                    if (!$pA) $pA = 'Benar';
                    if (!$pB) $pB = 'Salah';
                } elseif ($tipe === 'mcma') {
                    // MCMA: bisa huruf (a,b) atau teks dipisah koma
                    $jwbParts = array_map('trim', explode(',', $jwbRaw));
                    $jwbHuruf = [];
                    foreach ($jwbParts as $part) {
                        $part = strtolower($part);
                        if (in_array($part, ['a','b','c','d'])) {
                            $jwbHuruf[] = $part;
                        } elseif (strlen($part) === 1 && in_array($part, ['a','b','c','d'])) {
                            $jwbHuruf[] = $part;
                        } else {
                            // Coba cocokkan teks ke huruf
                            $h = cocokkanJawaban($part, $pA, $pB, $pC, $pD);
                            if ($h) $jwbHuruf[] = $h;
                        }
                    }
                    // Kalau tidak ada koma, coba split per karakter
                    if (empty($jwbHuruf) && !str_contains($jwbRaw, ',')) {
                        $chars = str_split($jwbRaw);
                        $chars = array_filter($chars, fn($c) => in_array($c, ['a','b','c','d']));
                        $jwbHuruf = array_values($chars);
                    }
                    sort($jwbHuruf);
                    $jwb = implode(',', array_unique($jwbHuruf));
                } else {
                    // PG: huruf langsung atau cocokkan teks
                    if (in_array($jwbRaw, ['a','b','c','d'])) {
                        $jwb = $jwbRaw;
                    } else {
                        $jwb = cocokkanJawaban($jwbRaw, $pA, $pB, $pC, $pD);
                    }
                }

                // Skip baris kosong
                if (!$pert && !$pA) continue;

                // Resolusi kategori
                $katId = 0;
                if (is_numeric($kolomKat)) {
                    $katId = (int)$kolomKat;
                } elseif ($kolomKat !== '') {
                    $katId = $katArr[strtolower($kolomKat)] ?? 0;
                }
                if (!$katId) $katId = $defaultKatId;

                // Validasi
                $rowErr = [];
                if (!$pert) $rowErr[] = 'pertanyaan kosong';
                if (!$katId) $rowErr[] = 'kategori tidak ditemukan';
                if ($tipe === 'pg') {
                    if (!$pA || !$pB || !$pC || !$pD) $rowErr[] = 'pilihan A-D tidak lengkap';
                    if (!$jwb) $rowErr[] = "Jawaban '{$jwbRaw}' tidak cocok dengan pilihan A/B/C/D manapun";
                } elseif ($tipe === 'bs') {
                    if (!$jwb) $rowErr[] = "Jawaban BS tidak valid (isi: a/b/c/d atau 'benar'/'salah')";
                } elseif ($tipe === 'mcma') {
                    if (!$pA || !$pB) $rowErr[] = 'minimal pilihan A dan B wajib diisi';
                    if (!$jwb || count(explode(',', $jwb)) < 2)
                        $rowErr[] = "MCMA harus ≥2 jawaban benar (contoh: a,b)";
                }

                if ($rowErr) {
                    $gagal++;
                    $log[] = ['no'=>$i+1, 'status'=>'gagal',
                              'pesan'=>implode('; ', $rowErr),
                              'preview'=>mb_substr($pert ?: $kolomKat, 0, 60)];
                    continue;
                }

                $stmt->bind_param('issssssss', $katId, $tipe, $pert, $teksBacaan, $pA, $pB, $pC, $pD, $jwb);
                if ($stmt->execute()) {
                    $berhasil++;
                    $log[] = ['no'=>$i+1, 'status'=>'ok',
                              'preview'=>mb_substr($pert, 0, 60),
                              'kat'=>$katById[$katId] ?? "ID $katId",
                              'tipe'=>strtoupper($tipe)];
                } else {
                    $gagal++;
                    $log[] = ['no'=>$i+1, 'status'=>'gagal',
                              'pesan'=>$conn->error,
                              'preview'=>mb_substr($pert, 0, 60)];
                }
            }
            $stmt->close();
            $results = compact('berhasil', 'gagal', 'log');

            if ($berhasil > 0) {
                logActivity($conn, 'Import soal', "$berhasil soal berhasil diimport");
                setFlash('success', "<strong>$berhasil soal</strong> berhasil diimport." .
                                    ($gagal ? " <strong>$gagal baris</strong> gagal." : ''));
            } elseif ($gagal > 0) {
                setFlash('error', "Semua <strong>$gagal baris</strong> gagal diimport. Periksa format file.");
            } else {
                setFlash('info', 'Tidak ada data yang diproses. Pastikan file tidak kosong.');
            }
        }
    }
}

$pageTitle  = 'Import Soal Excel';
$activeMenu = 'importsoal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Soal dari Excel</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/soal.php">Bank Soal</a></li>
            <li class="breadcrumb-item active">Import Excel</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/soal.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Bank Soal
    </a>
</div>

<?= renderFlash() ?>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Terdapat Kesalahan</h6>
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Form Upload ── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-upload me-2 text-primary"></i>Upload File Excel
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="formImport">
            <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Kategori Default <span class="text-muted small">(opsional)</span></label>
                        <select name="default_kategori_id" class="form-select">
                            <option value="">— Gunakan kolom Kategori di Excel —</option>
                            <?php foreach ($katById as $kid => $knm): ?>
                            <option value="<?= $kid ?>"><?= e($knm) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Digunakan jika kolom kategori di Excel kosong atau tidak dikenali.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            File Excel <span class="text-danger">*</span>
                        </label>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="file_excel" id="fileInput"
                                   accept=".xls,.xlsx,.csv" required class="d-none">
                            <div id="uploadPrompt" onclick="document.getElementById('fileInput').click()"
                                 style="cursor:pointer">
                                <i class="bi bi-cloud-upload fs-2 text-primary d-block mb-2"></i>
                                <p class="fw-semibold mb-1">Klik untuk pilih file</p>
                                <p class="text-muted small mb-0">atau drag & drop di sini</p>
                                <p class="text-muted small">Format: <strong>.xlsx</strong>, <strong>.xls</strong>, atau <strong>.csv</strong> — Maks. 5 MB</p>
                            </div>
                            <div id="fileSelected" class="d-none text-center">
                                <i class="bi bi-file-earmark-excel fs-2 text-success d-block mb-2"></i>
                                <p class="fw-semibold mb-1" id="fileName">-</p>
                                <p class="text-muted small" id="fileSize">-</p>
                                <button type="button" class="btn btn-xs btn-outline-danger mt-1"
                                        onclick="resetFile()">
                                    <i class="bi bi-x me-1"></i>Ganti File
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold py-2" id="btnImport" disabled>
                        <i class="bi bi-upload me-2"></i>Proses Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Download Template -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-download me-2"></i>Template Excel</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Download template resmi agar format file sesuai:
                </p>
                <a href="<?= BASE_URL ?>/admin/download_template_soal.php"
                   class="btn btn-outline-success w-100 mb-2">
                    <i class="bi bi-file-earmark-excel me-2"></i>Download Template (.xlsx)
                </a>
                <a href="<?= BASE_URL ?>/admin/download_template_soal_csv.php"
                   class="btn btn-outline-secondary w-100">
                    <i class="bi bi-file-earmark-text me-2"></i>Download Template (.csv)
                </a>
            </div>
        </div>
    </div>

    <!-- ── Panduan + Hasil ── -->
    <div class="col-lg-7">

        <!-- Hasil Import -->
        <?php if ($results): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-clipboard-check me-2"></i>Hasil Import
                </span>
                <div class="d-flex gap-2">
                    <span class="badge bg-success"><?= $results['berhasil'] ?> berhasil</span>
                    <?php if ($results['gagal'] > 0): ?>
                    <span class="badge bg-danger"><?= $results['gagal'] ?> gagal</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress bar -->
            <?php $totalRows = $results['berhasil'] + $results['gagal']; ?>
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= $results['berhasil'] ?> baris berhasil</span>
                    <span><?= $totalRows ?> total diproses</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-success"
                         style="width:<?= $totalRows > 0 ? round($results['berhasil']/$totalRows*100) : 0 ?>%"></div>
                    <div class="progress-bar bg-danger"
                         style="width:<?= $totalRows > 0 ? round($results['gagal']/$totalRows*100) : 0 ?>%"></div>
                </div>
            </div>

            <div style="max-height:360px; overflow-y:auto">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">Baris</th>
                            <th>Pertanyaan</th>
                            <th style="width:90px">Kategori</th>
                            <th style="width:70px" class="text-center">Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results['log'] as $lg): ?>
                    <tr class="<?= $lg['status']==='ok' ? 'table-success' : 'table-danger' ?>">
                        <td><?= $lg['no'] ?></td>
                        <td class="small"><?= e(mb_substr($lg['preview'], 0, 55)) ?><?= mb_strlen($lg['preview']) > 55 ? '…' : '' ?></td>
                        <td class="small"><?= e($lg['kat'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php if ($lg['status']==='ok'): ?>
                            <span class="badge bg-success">✓ OK</span>
                            <?php else: ?>
                            <span class="badge bg-danger">✗ Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($lg['pesan'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($results['berhasil'] > 0): ?>
            <div class="card-footer">
                <a href="<?= BASE_URL ?>/admin/soal.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>Lihat Semua Soal
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Format panduan -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2 text-info"></i>Format File Excel
            </div>
            <div class="card-body">
                <p class="fw-semibold mb-3">Urutan kolom (7 kolom, baris pertama bisa header):</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm small">
                        <thead class="table-primary">
                            <tr>
                                <th>A: kategori</th>
                                <th>B: pertanyaan</th>
                                <th>C: pilihan_a</th>
                                <th>D: pilihan_b</th>
                                <th>E: pilihan_c</th>
                                <th>F: pilihan_d</th>
                                <th>G: jawaban_benar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td><em>Matematika</em></td>
                                <td><em>Ibu kota Indonesia adalah…</em></td>
                                <td><em>Jakarta</em></td>
                                <td><em>Bandung</em></td>
                                <td><em>Surabaya</em></td>
                                <td><em>Medan</em></td>
                                <td class="fw-bold text-success"><em>a</em></td>
                            </tr>
                            <tr>
                                <td>IPA</td>
                                <td>Hewan yang menyusui disebut…</td>
                                <td>Reptilia</td>
                                <td>Mamalia</td>
                                <td>Aves</td>
                                <td>Amfibi</td>
                                <td class="fw-bold text-success">b</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <div class="alert alert-success py-2 mb-0 small">
                            <strong>✓ Kolom A (Kategori)</strong> bisa berisi:<br>
                            • Nama kategori (cth: <code>Matematika</code>)<br>
                            • ID kategori (cth: <code>1</code>)<br>
                            • Kosong → pakai kategori default di atas
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning py-2 mb-0 small">
                            <strong>⚠ Kolom G (Jawaban)</strong> harus huruf kecil:<br>
                            <code>a</code>, <code>b</code>, <code>c</code>, atau <code>d</code><br>
                            Huruf kapital (A/B/C/D) akan otomatis dikonversi.
                        </div>
                    </div>
                </div>
                <div class="alert alert-info py-2 mt-2 mb-0 small">
                    <strong>ℹ Format yang didukung:</strong>
                    <code>.xlsx</code> (direkomendasikan), <code>.xls</code>, dan <code>.csv</code>.<br>
                    Jika <code>.xlsx</code> gagal karena masalah server (ZipArchive), gunakan format <strong>.csv</strong>.<br>
                    Gambar soal tidak bisa diimport via Excel/CSV (tambahkan manual di Bank Soal).
                </div>
            </div>
        </div>

        <!-- Daftar kategori yang ada -->
        <?php if (!empty($katById)): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-tags me-2"></i>Kategori Tersedia</div>
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($katById as $kid => $knm): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <?= $kid ?> — <?= e($knm) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($katById)): ?>
                <p class="text-muted small mb-0">
                    Belum ada kategori. <a href="<?= BASE_URL ?>/admin/kategori.php">Buat kategori dulu</a>.
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.upload-zone {
    border: 2px dashed var(--border); border-radius: var(--radius);
    padding: 28px 20px; text-align: center;
    background: #f8fafc; transition: var(--transition);
}
.upload-zone.drag-over { border-color: var(--primary); background: #eff6ff; }
</style>
<script>
const fileInput   = document.getElementById('fileInput');
const uploadZone  = document.getElementById('uploadZone');
const uploadPrompt= document.getElementById('uploadPrompt');
const fileSelected= document.getElementById('fileSelected');
const btnImport   = document.getElementById('btnImport');

fileInput.addEventListener('change', showFile);

function showFile() {
    const f = fileInput.files[0];
    if (!f) return;
    document.getElementById('fileName').textContent = f.name;
    document.getElementById('fileSize').textContent = (f.size/1024/1024).toFixed(2) + ' MB';
    uploadPrompt.classList.add('d-none');
    fileSelected.classList.remove('d-none');
    btnImport.disabled = false;
}
function resetFile() {
    fileInput.value = '';
    uploadPrompt.classList.remove('d-none');
    fileSelected.classList.add('d-none');
    btnImport.disabled = true;
}

// Drag & Drop
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault(); uploadZone.classList.remove('drag-over');
    const dt = e.dataTransfer;
    if (dt.files[0]) { fileInput.files = dt.files; showFile(); }
});

// Progress saat submit
document.getElementById('formImport').addEventListener('submit', () => {
    btnImport.disabled = true;
    btnImport.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses…';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
