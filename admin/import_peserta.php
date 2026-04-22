<?php
// ============================================================
// admin/import_peserta.php — Import Peserta dari Excel
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../vendor/simplexlsx/SimpleXLSX.php';
requireLogin('admin_kecamatan');

function genKode3(mysqli $db): string {
    do {
        $k = 'TKA' . strtoupper(substr(md5(uniqid()),0,6));
        $c = $db->query("SELECT id FROM peserta WHERE kode_peserta='$k' LIMIT 1");
    } while($c && $c->num_rows > 0);
    return $k;
}

$sekolahList = $conn->query("SELECT id,nama_sekolah FROM sekolah ORDER BY nama_sekolah");
$sekolahArr  = [];
if ($sekolahList) while($r=$sekolahList->fetch_assoc()) $sekolahArr[$r['id']]=$r['nama_sekolah'];

$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $sekolahId = (int)($_POST['sekolah_id'] ?? 0);
    if (!$sekolahId) { setFlash('error','Pilih sekolah.'); redirect(BASE_URL.'/admin/import_peserta.php'); }
    if (empty($_FILES['file_excel']['name'])) { setFlash('error','Pilih file.'); redirect(BASE_URL.'/admin/import_peserta.php'); }

    $ext = strtolower(pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'])) { setFlash('error','Format harus .xlsx atau .csv'); redirect(BASE_URL.'/admin/import_peserta.php'); }

    $tmpFile = $_FILES['file_excel']['tmp_name'];
    $rows = [];

    if ($ext === 'csv') {
        if (($handle = fopen($tmpFile, "r")) !== FALSE) {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) $rows[] = $data;
            if (count($rows) > 0 && count($rows[0]) === 1 && str_contains($rows[0][0], ';')) {
                rewind($handle);
                if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
                $rows = [];
                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) $rows[] = $data;
            }
            fclose($handle);
        }
    } else {
        if (!class_exists('\ZipArchive') && !extension_loaded('zip')) {
            setFlash('error','Ekstensi zip belum aktif. Silakan gunakan format .csv sebagai alternatif.');
            redirect(BASE_URL.'/admin/import_peserta.php');
        }
        $xlsx = SimpleXLSX::parse($tmpFile);
        if (!$xlsx) { setFlash('error','File tidak bisa dibaca.'); redirect(BASE_URL.'/admin/import_peserta.php'); }
        $rows = $xlsx->rows(0);
    }
    $berhasil = $gagal = $diupdate = 0;
    $log = [];
    $startRow = 0;
    if (!empty($rows[0]) && strtolower(trim($rows[0][0]??'')) === 'nama') $startRow = 1;

    $modeUpdate = ($_POST['mode_update'] ?? '0') === '1';

    for ($i = $startRow; $i < count($rows); $i++) {
        $row   = $rows[$i];
        while(count($row)<2) $row[]='';
        $nama  = trim($row[0]??'');
        $kelas = trim($row[1]??'');
        if (!$nama) continue;

        if ($modeUpdate) {
            // Cek apakah nama+sekolah sudah ada
            $namaEsc  = $conn->real_escape_string($nama);
            $cekExist = $conn->query("SELECT id FROM peserta WHERE nama='$namaEsc' AND sekolah_id=$sekolahId LIMIT 1");
            if ($cekExist && $cekExist->num_rows > 0) {
                // Update data yang sudah ada
                $existId = (int)$cekExist->fetch_assoc()['id'];
                $kelasEsc = $conn->real_escape_string($kelas);
                if ($conn->query("UPDATE peserta SET kelas='$kelasEsc' WHERE id=$existId")) {
                    $diupdate++;
                    $log[] = ['no'=>$i+1,'status'=>'update','nama'=>$nama,'kode'=>'(diperbarui)'];
                } else {
                    $gagal++;
                    $log[] = ['no'=>$i+1,'status'=>'gagal','nama'=>$nama,'pesan'=>$conn->error];
                }
                continue;
            }
        }
        // Insert baru
        $kode = genKode3($conn);
        $st   = $conn->prepare("INSERT IGNORE INTO peserta (nama,kelas,sekolah_id,kode_peserta) VALUES (?,?,?,?)");
        $st->bind_param('ssis', $nama, $kelas, $sekolahId, $kode);
        if ($st->execute() && $conn->affected_rows > 0) {
            $berhasil++;
            $log[] = ['no'=>$i+1,'status'=>'ok','nama'=>$nama,'kode'=>$kode];
        } elseif ($conn->affected_rows === 0) {
            $log[] = ['no'=>$i+1,'status'=>'skip','nama'=>$nama,'kode'=>'(sudah ada)'];
        } else {
            $gagal++;
            $log[] = ['no'=>$i+1,'status'=>'gagal','nama'=>$nama,'pesan'=>$conn->error];
        }
        $st->close();
    }
    $results = compact('berhasil','gagal','diupdate','log','sekolahId');
}

$pageTitle  = 'Import Peserta';
$activeMenu = 'peserta';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h2><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Peserta dari Excel</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/peserta.php">Peserta</a></li>
        <li class="breadcrumb-item active">Import</li>
    </ol></nav></div>
    <a href="<?=BASE_URL?>/admin/peserta.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?= renderFlash() ?>
<div class="row g-4">
<div class="col-lg-5">
    <div class="card">
        <div class="card-header"><i class="bi bi-upload me-2"></i>Upload File</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Sekolah <span class="text-danger">*</span></label>
                    <select name="sekolah_id" class="form-select" required>
                        <option value="">-- Pilih Sekolah --</option>
                        <?php foreach($sekolahArr as $sid=>$snm): ?>
                        <option value="<?=$sid?>"><?=htmlspecialchars($snm)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">File Excel (.xlsx) atau CSV (.csv)</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx,.csv" required>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="mode_update" value="1" id="modeUpdate">
                        <label class="form-check-label fw-semibold" for="modeUpdate">
                            Update data jika nama sudah ada
                        </label>
                    </div>
                    <div class="form-text">Jika dicentang: peserta dengan nama sama di sekolah yang sama akan diupdate kelasnya. Jika tidak: data lama dibiarkan.</div>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold">
                    <i class="bi bi-upload me-2"></i>Proses Import
                </button>
            </form>
        </div>
    </div>
    <div class="card mt-3">
        <div class="card-header"><i class="bi bi-download me-2"></i>Template</div>
        <div class="card-body">
            <a href="<?=BASE_URL?>/assets/template_import_peserta.xlsx" class="btn btn-outline-success w-100 mb-2">
                <i class="bi bi-file-earmark-excel me-2"></i>Download XLSX
            </a>
            <a href="<?=BASE_URL?>/admin/download_template_peserta_csv.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-file-earmark-text me-2"></i>Download CSV
            </a>
            <hr>
            <div class="small">
                <p class="fw-bold mb-1">Format Kolom:</p>
                <table class="table table-bordered table-sm mb-2">
                    <thead class="table-primary"><tr><th>Kolom A</th><th>Kolom B</th></tr></thead>
                    <tbody>
                        <tr class="table-light"><td><em>nama</em></td><td><em>kelas</em></td></tr>
                        <tr><td>Andi Pratama</td><td>VI</td></tr>
                        <tr><td>Budi Santoso</td><td>VIII</td></tr>
                    </tbody>
                </table>
                <p class="text-muted mb-1">Kode peserta dibuat otomatis oleh sistem.</p>
                <p class="text-muted mb-0"><strong>Format kelas:</strong> SD/MI = I–VI | SMP/MTs = VII–IX | SMA/MA/SMK = X–XII</p>
            </div>
        </div>
    </div>
</div>
<div class="col-lg-7">
    <?php if($results): ?>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-1 align-items-center">
            Hasil Import
            <span class="badge bg-success ms-2"><?=$results['berhasil']?> ditambah</span>
            <?php if(($results['diupdate']??0)>0): ?><span class="badge bg-primary ms-1"><?=$results['diupdate']?> diupdate</span><?php endif; ?>
            <?php if($results['gagal']>0): ?><span class="badge bg-danger ms-1"><?=$results['gagal']?> gagal</span><?php endif; ?>
        </div>
        <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
            <table class="table table-sm mb-0">
                <thead><tr><th>No</th><th>Nama</th><th>Kode</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach($results['log'] as $l):
                    $rowClass = match($l['status']) {
                        'ok'     => 'table-success',
                        'update' => 'table-primary',
                        'skip'   => 'table-light',
                        default  => 'table-danger',
                    };
                    $badge = match($l['status']) {
                        'ok'     => '<span class="badge bg-success">Ditambah</span>',
                        'update' => '<span class="badge bg-primary">Diupdate</span>',
                        'skip'   => '<span class="badge bg-secondary">Sudah Ada</span>',
                        default  => '<span class="badge bg-danger">Gagal: '.htmlspecialchars($l['pesan']??'').'</span>',
                    };
                ?>
                <tr class="<?=$rowClass?>">
                    <td><?=$l['no']?></td>
                    <td><?=htmlspecialchars($l['nama'])?></td>
                    <td><code><?=htmlspecialchars($l['kode']??'-')?></code></td>
                    <td><?=$badge?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if($results['berhasil']>0 || ($results['diupdate']??0)>0): ?>
        <div class="card-footer">
            <a href="<?=BASE_URL?>/admin/peserta.php?sekolah_id=<?=$results['sekolahId']?>" class="btn btn-primary btn-sm">
                Lihat Peserta
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
