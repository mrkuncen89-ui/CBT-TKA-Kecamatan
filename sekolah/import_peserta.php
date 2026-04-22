<?php
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../vendor/simplexlsx/SimpleXLSX.php';
requireLogin('sekolah');
$user = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

function genKodeSek(mysqli $db): string {
    do { $k='TKA'.strtoupper(substr(md5(uniqid()),0,6));
         $c=$db->query("SELECT id FROM peserta WHERE kode_peserta='$k' LIMIT 1");
    } while($c&&$c->num_rows>0); return $k;
}

$results = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfVerify();
    if (empty($_FILES['file_excel']['name'])) { setFlash('error','Pilih file.'); redirect(BASE_URL.'/sekolah/import_peserta.php'); }
    $ext = strtolower(pathinfo($_FILES['file_excel']['name'],PATHINFO_EXTENSION));
    if ($ext!=='xlsx') { setFlash('error','Format harus .xlsx'); redirect(BASE_URL.'/sekolah/import_peserta.php'); }
    $xlsx = SimpleXLSX::parse($_FILES['file_excel']['tmp_name']);
    if (!$xlsx) { setFlash('error','File tidak bisa dibaca.'); redirect(BASE_URL.'/sekolah/import_peserta.php'); }
    $rows=$xlsx->rows(0); $berhasil=$gagal=0; $log=[];
    $startRow=0; if(!empty($rows[0])&&strtolower(trim($rows[0][0]??''))==='nama')$startRow=1;
    $stmt=$conn->prepare("INSERT IGNORE INTO peserta (nama,kelas,sekolah_id,kode_peserta) VALUES (?,?,?,?)");
    for($i=$startRow;$i<count($rows);$i++){
        $row=$rows[$i]; while(count($row)<2)$row[]='';
        $nama=trim($row[0]??''); $kelas=trim($row[1]??'');
        if(!$nama)continue;
        $kode=genKodeSek($conn);
        $stmt->bind_param('ssis',$nama,$kelas,$sekolahId,$kode);
        if($stmt->execute()){$berhasil++;$log[]=['no'=>$i+1,'status'=>'ok','nama'=>$nama,'kode'=>$kode];}
        else{$gagal++;$log[]=['no'=>$i+1,'status'=>'gagal','nama'=>$nama,'pesan'=>$conn->error];}
    }
    $stmt->close();
    $results=compact('berhasil','gagal','log');
}
$pageTitle='Import Peserta'; $activeMenu='importpeserta';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h2><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Peserta</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/peserta.php">Peserta</a></li>
        <li class="breadcrumb-item active">Import</li>
    </ol></nav></div>
    <a href="<?=BASE_URL?>/sekolah/peserta.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?= renderFlash() ?>
<div class="row g-4">
<div class="col-lg-5">
    <div class="card"><div class="card-header">Upload File Excel</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="mb-4"><label class="form-label fw-semibold">File Excel (.xlsx)</label>
                <input type="file" name="file_excel" class="form-control" accept=".xlsx" required></div>
            <button type="submit" class="btn btn-success w-100"><i class="bi bi-upload me-2"></i>Proses Import</button>
        </form>
    </div></div>
    <div class="card mt-3"><div class="card-header">Format</div>
    <div class="card-body small">
        <table class="table table-bordered table-sm">
            <thead class="table-primary"><tr><th>Kolom A (nama)</th><th>Kolom B (kelas)</th></tr></thead>
            <tbody>
                <tr><td>Andi Pratama</td><td>VI</td></tr>
                <tr><td>Budi Santoso</td><td>VIII</td></tr>
                <tr><td>Citra Dewi</td><td>XI</td></tr>
            </tbody>
        </table>
        <p class="text-muted mb-0 small">SD/MI: I–VI &nbsp;|&nbsp; SMP/MTs: VII–IX &nbsp;|&nbsp; SMA/MA/SMK: X–XII</p>
    </div></div>
</div>
<div class="col-lg-7">
<?php if($results): ?>
<div class="card"><div class="card-header">
    Hasil <span class="badge bg-success ms-2"><?=$results['berhasil']?> OK</span>
    <?php if($results['gagal']>0):?><span class="badge bg-danger ms-1"><?=$results['gagal']?> Gagal</span><?php endif;?>
</div>
<div class="card-body p-0" style="max-height:400px;overflow-y:auto">
<table class="table table-sm mb-0"><thead><tr><th>#</th><th>Nama</th><th>Kode</th><th>Status</th></tr></thead>
<tbody>
<?php foreach($results['log'] as $l):?>
<tr class="<?=$l['status']==='ok'?'table-success':'table-danger'?>">
    <td><?=$l['no']?></td><td><?=htmlspecialchars($l['nama'])?></td>
    <td><code><?=htmlspecialchars($l['kode']??'-')?></code></td>
    <td><?=$l['status']==='ok'?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">Gagal</span>'?></td>
</tr>
<?php endforeach;?>
</tbody></table>
</div></div>
<?php endif;?>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
