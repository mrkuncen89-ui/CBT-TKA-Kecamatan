<?php
// ============================================================
// admin/soal.php — Bank Soal (PG / BS / MCMA)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$uploadDir = __DIR__ . '/../assets/uploads/soal/';

// ── Auto-migrate: tambah kolom gambar_pilihan jika belum ada ──
foreach (['a','b','c','d'] as $_k) {
    $_col = $conn->query("SHOW COLUMNS FROM soal LIKE 'gambar_pilihan_{$_k}'");
    if (!$_col || $_col->num_rows === 0) {
        $conn->query("ALTER TABLE soal ADD COLUMN `gambar_pilihan_{$_k}` VARCHAR(255) NULL DEFAULT NULL AFTER `pilihan_{$_k}`");
    }
}

// Helper: upload gambar pilihan
function uploadGambarPilihan(array $file, string $dir): array {
    return uploadGambarSoal($file, $dir); // pakai fungsi yang sama
}

// Helper: label tipe soal
function tipeSoalLabel(string $tipe): string {
    return match($tipe) {
        'bs'   => '<span class="badge bg-warning text-dark">BS</span>',
        'mcma' => '<span class="badge bg-purple" style="background:#7c3aed">MCMA</span>',
        default=> '<span class="badge bg-primary">PG</span>',
    };
}

// Helper: normalisasi jawaban_benar MCMA → sorted string 'a,b,c'
function normalizeJawaban(string $tipe, $raw): string {
    if ($tipe === 'mcma') {
        $arr = is_array($raw) ? $raw : explode(',', $raw);
        $arr = array_filter(array_map('trim', $arr), fn($x) => in_array($x, ['a','b','c','d']));
        sort($arr);
        return implode(',', array_unique($arr));
    }
    if ($tipe === 'bs') {
        $v = strtolower(trim(is_array($raw) ? ($raw[0] ?? '') : $raw));
        return in_array($v, ['benar','salah']) ? $v : 'benar';
    }
    // PG
    $v = strtolower(trim(is_array($raw) ? ($raw[0] ?? '') : $raw));
    return in_array($v, ['a','b','c','d']) ? $v : 'a';
}

// ── HAPUS SATUAN ─────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id  = (int)$_GET['hapus'];
    $_qSoal = $conn->query("SELECT gambar FROM soal WHERE id=$id LIMIT 1");
    $row = ($_qSoal && $_qSoal->num_rows > 0) ? $_qSoal->fetch_assoc() : null;
    if ($row && $row['gambar'] && file_exists($uploadDir . $row['gambar']))
        unlink($uploadDir . $row['gambar']);
    $conn->query("DELETE FROM soal WHERE id=$id");
    setFlash('success', 'Soal berhasil dihapus.');
    redirect(BASE_URL . '/admin/soal.php');
}

// ── HAPUS MASSAL ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus_massal') {
    csrfVerify();
    $ids = $_POST['soal_ids'] ?? [];
    $ids = array_filter(array_map('intval', $ids));
    // Ambil $filterKat dari GET (dikirim bersama form via query string) atau POST tersembunyi
    $_katRedirect = (int)($_GET['kat'] ?? $_POST['kat_filter'] ?? 0);
    if (!empty($ids)) {
        $idStr = implode(',', $ids);
        // Hapus gambar dulu
        $res = $conn->query("SELECT gambar FROM soal WHERE id IN ($idStr) AND gambar IS NOT NULL");
        if ($res) while ($r = $res->fetch_assoc()) {
            if ($r['gambar'] && file_exists($uploadDir . $r['gambar']))
                @unlink($uploadDir . $r['gambar']);
        }
        $conn->query("DELETE FROM soal WHERE id IN ($idStr)");
        $jumlah = $conn->affected_rows;
        setFlash('success', "$jumlah soal berhasil dihapus.");
    }
    redirect(BASE_URL . '/admin/soal.php' . ($_katRedirect ? "?kat=$_katRedirect" : ''));
}

// ── HAPUS SEMUA (per kategori/filter) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus_semua') {
    csrfVerify();
    $katFilter = (int)($_POST['kat_filter'] ?? 0);
    $whereBase = $katFilter ? "WHERE kategori_id = $katFilter" : '';
    $whereGambar = $katFilter ? "WHERE kategori_id = $katFilter AND gambar IS NOT NULL" : "WHERE gambar IS NOT NULL";
    // Hapus gambar
    $res = $conn->query("SELECT gambar FROM soal $whereGambar");
    if ($res) while ($r = $res->fetch_assoc()) {
        if ($r['gambar'] && file_exists($uploadDir . $r['gambar']))
            @unlink($uploadDir . $r['gambar']);
    }
    $conn->query("DELETE FROM soal $whereBase");
    $jumlah = $conn->affected_rows;
    setFlash('success', "$jumlah soal berhasil dihapus semua.");
    redirect(BASE_URL . '/admin/soal.php');
}

// ── TAMBAH ───────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'tambah') {
    csrfVerify();
    $katId  = (int)$_POST['kategori_id'];
    $tipe   = in_array($_POST['tipe_soal']??'pg', ['pg','bs','mcma']) ? $_POST['tipe_soal'] : 'pg';
    $pert   = trim($_POST['pertanyaan'] ?? '');
    $gambar = '';

    $teks_bacaan = trim($_POST['teks_bacaan'] ?? '');
    if (!$katId) $errors[] = 'Kategori wajib dipilih.';
    if (!$pert)  $errors[] = 'Pertanyaan wajib diisi.';

    // Pilihan berdasarkan tipe
    if ($tipe === 'bs') {
        $pA = 'Benar'; $pB = 'Salah'; $pC = ''; $pD = '';
        $jwb = normalizeJawaban('bs', $_POST['jawaban_benar'] ?? 'benar');
    } elseif ($tipe === 'mcma') {
        $pA = trim($_POST['pilihan_a'] ?? '');
        $pB = trim($_POST['pilihan_b'] ?? '');
        $pC = trim($_POST['pilihan_c'] ?? '');
        $pD = trim($_POST['pilihan_d'] ?? '');
        if (!$pA || !$pB) $errors[] = 'Minimal pilihan A dan B wajib diisi.';
        $jwb = normalizeJawaban('mcma', $_POST['jawaban_mcma'] ?? []);
        if (!$jwb) $errors[] = 'Pilih minimal satu jawaban benar untuk MCMA.';
        $jwbArr = explode(',', $jwb);
        if (count($jwbArr) < 2) $errors[] = 'MCMA harus memiliki minimal 2 jawaban benar.';
    } else {
        $pA = trim($_POST['pilihan_a'] ?? '');
        $pB = trim($_POST['pilihan_b'] ?? '');
        $pC = trim($_POST['pilihan_c'] ?? '');
        $pD = trim($_POST['pilihan_d'] ?? '');
        if (!$pA || !$pB || !$pC || !$pD) $errors[] = 'Semua pilihan A–D wajib diisi untuk PG.';
        $jwb = normalizeJawaban('pg', $_POST['jawaban_benar'] ?? 'a');
    }

    // Upload gambar (opsional)
    if (!$errors && !empty($_FILES['gambar']['name'])) {
        $up = uploadGambarSoal($_FILES['gambar'], $uploadDir);
        if (!$up['ok']) $errors[] = $up['msg'];
        else $gambar = $up['nama'];
    }

    if (!$errors) {
        // Upload gambar per pilihan (opsional, untuk PG dan MCMA)
        $gpA = $gpB = $gpC = $gpD = '';
        if ($tipe === 'pg' || $tipe === 'mcma') {
            foreach (['a','b','c','d'] as $k) {
                $fileKey = "gambar_pilihan_{$k}";
                if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $up = uploadGambarSoal($_FILES[$fileKey], $uploadDir);
                    if ($up['ok']) {
                        // Simpan ke variabel yang benar: $gpA, $gpB, $gpC, $gpD
                        switch ($k) {
                            case 'a': $gpA = $up['nama']; break;
                            case 'b': $gpB = $up['nama']; break;
                            case 'c': $gpC = $up['nama']; break;
                            case 'd': $gpD = $up['nama']; break;
                        }
                    } else {
                        $errors[] = "Gambar pilihan " . strtoupper($k) . ": " . $up['msg'];
                    }
                }
            }
        }

        if (!$errors) {
            $pembahasan = trim($_POST['pembahasan'] ?? '');
            $st = $conn->prepare(
                "INSERT INTO soal (kategori_id, tipe_soal, pertanyaan, teks_bacaan, gambar,
                 pilihan_a, pilihan_b, pilihan_c, pilihan_d,
                 gambar_pilihan_a, gambar_pilihan_b, gambar_pilihan_c, gambar_pilihan_d,
                 jawaban_benar, pembahasan)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $st->bind_param('issssssssssssss', $katId, $tipe, $pert, $teks_bacaan, $gambar,
                $pA, $pB, $pC, $pD, $gpA, $gpB, $gpC, $gpD, $jwb, $pembahasan);
            $st->execute();
            $newId = $conn->insert_id;
            $st->close();
            logActivity($conn, 'Tambah Soal', "ID $newId | Kategori ID $katId | Tipe $tipe");
            setFlash('success', 'Soal berhasil ditambahkan.');
            redirect(BASE_URL . '/admin/soal.php' . ($katId ? "?kat=$katId" : ''));
        }
    }
} // end if aksi === tambah

// ── EDIT ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi']??'') === 'edit') {
    csrfVerify();
    $id    = (int)$_POST['id'];
    $katId = (int)$_POST['kategori_id'];
    $tipe  = in_array($_POST['tipe_soal']??'pg', ['pg','bs','mcma']) ? $_POST['tipe_soal'] : 'pg';
    $pert  = trim($_POST['pertanyaan'] ?? '');
    $teks_bacaan = trim($_POST['teks_bacaan'] ?? '');
    $gambarLama = trim($_POST['gambar_lama'] ?? '');
    $gambar     = $gambarLama;

    if (!empty($_FILES['gambar_baru']['name'])) {
        $up = uploadGambarSoal($_FILES['gambar_baru'], $uploadDir);
        if (!$up['ok']) $errors[] = $up['msg'];
        else {
            if ($gambarLama && file_exists($uploadDir.$gambarLama)) @unlink($uploadDir.$gambarLama);
            $gambar = $up['nama'];
        }
    } elseif (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar']) {
        if ($gambarLama && file_exists($uploadDir.$gambarLama)) @unlink($uploadDir.$gambarLama);
        $gambar = '';
    }

    if ($tipe === 'bs') {
        $pA = 'Benar'; $pB = 'Salah'; $pC = ''; $pD = '';
        $jwb = normalizeJawaban('bs', $_POST['jawaban_benar'] ?? 'benar');
    } elseif ($tipe === 'mcma') {
        $pA = trim($_POST['pilihan_a'] ?? '');
        $pB = trim($_POST['pilihan_b'] ?? '');
        $pC = trim($_POST['pilihan_c'] ?? '');
        $pD = trim($_POST['pilihan_d'] ?? '');
        $jwb = normalizeJawaban('mcma', $_POST['jawaban_mcma'] ?? []);
    } else {
        $pA = trim($_POST['pilihan_a'] ?? '');
        $pB = trim($_POST['pilihan_b'] ?? '');
        $pC = trim($_POST['pilihan_c'] ?? '');
        $pD = trim($_POST['pilihan_d'] ?? '');
        $jwb = normalizeJawaban('pg', $_POST['jawaban_benar'] ?? 'a');
    }

    if (!$katId) $errors[] = 'Kategori wajib dipilih.';
    if (!$pert)  $errors[] = 'Pertanyaan wajib diisi.';

    if (!$errors) {
        // Upload / hapus gambar pilihan (untuk PG dan MCMA)
        $gpA = trim($_POST['gambar_pilihan_a_lama'] ?? '');
        $gpB = trim($_POST['gambar_pilihan_b_lama'] ?? '');
        $gpC = trim($_POST['gambar_pilihan_c_lama'] ?? '');
        $gpD = trim($_POST['gambar_pilihan_d_lama'] ?? '');
        if ($tipe === 'pg' || $tipe === 'mcma') {
            foreach (['a','b','c','d'] as $k) {
                $fileKey  = "gambar_pilihan_{$k}_baru";
                $lamaKey  = "gambar_pilihan_{$k}_lama";
                $hapusKey = "hapus_gambar_pilihan_{$k}";
                $lamaVal  = trim($_POST[$lamaKey] ?? '');
                $curVar   = 'gp' . strtoupper($k);
                $$curVar  = $lamaVal; // mulai dari nilai lama

                // Upload gambar pilihan baru (prioritas utama)
                if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $up = uploadGambarSoal($_FILES[$fileKey], $uploadDir);
                    if ($up['ok']) {
                        if ($lamaVal && file_exists($uploadDir.$lamaVal)) @unlink($uploadDir.$lamaVal);
                        $$curVar = $up['nama'];
                    } else {
                        $errors[] = "Gambar pilihan " . strtoupper($k) . ": " . $up['msg'];
                    }
                } elseif (!empty($_POST[$hapusKey])) {
                    // Hapus gambar pilihan (hanya jika tidak ada upload baru)
                    if ($lamaVal && file_exists($uploadDir.$lamaVal)) @unlink($uploadDir.$lamaVal);
                    $$curVar = '';
                }
            }
        } else {
            // Tipe bs: bersihkan gambar pilihan yang ada
            $gpA = $gpB = $gpC = $gpD = '';
        }

        if (!$errors) {
            $pembahasan_edit = trim($_POST['pembahasan'] ?? '');
            $st = $conn->prepare(
                "UPDATE soal SET kategori_id=?, tipe_soal=?, pertanyaan=?, teks_bacaan=?, gambar=?,
                 pilihan_a=?, pilihan_b=?, pilihan_c=?, pilihan_d=?,
                 gambar_pilihan_a=?, gambar_pilihan_b=?, gambar_pilihan_c=?, gambar_pilihan_d=?,
                 jawaban_benar=?, pembahasan=? WHERE id=?"
            );
            $st->bind_param('issssssssssssssi',
                $katId, $tipe, $pert, $teks_bacaan, $gambar,
                $pA, $pB, $pC, $pD,
                $gpA, $gpB, $gpC, $gpD,
                $jwb, $pembahasan_edit, $id);
            $st->execute(); $st->close();
            logActivity($conn, 'Edit Soal', "ID $id | Kategori ID $katId | Tipe $tipe");
            setFlash('success', 'Soal berhasil diperbarui.');
            redirect(BASE_URL . '/admin/soal.php');
        }
    }
}

// ── DATA ─────────────────────────────────────────────────────
$filterKat  = (int)($_GET['kat'] ?? 0);
$filterTipe = trim($_GET['tipe'] ?? '');
$q          = trim($_GET['q'] ?? '');
$where      = "WHERE 1=1";
if ($filterKat) $where .= " AND s.kategori_id=$filterKat";
if ($q)         $where .= " AND s.pertanyaan LIKE '%" . $conn->real_escape_string($q) . "%'";

// Cek apakah kolom tipe_soal sudah ada (migrate_v3.php sudah dijalankan)
$koloms      = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_soal'");
$adaTipeSoal = $koloms && $koloms->num_rows > 0;

if ($adaTipeSoal && $filterTipe)
    $where .= " AND s.tipe_soal='" . $conn->real_escape_string($filterTipe) . "'";

$orderBy  = $adaTipeSoal
    ? "k.nama_kategori, s.tipe_soal, s.id DESC"
    : "k.nama_kategori, s.id DESC";

$soalList = $conn->query("
    SELECT s.*, k.nama_kategori
    FROM soal s LEFT JOIN kategori_soal k ON k.id=s.kategori_id
    $where ORDER BY $orderBy
");

$katList = $conn->query("SELECT id,nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$katArr  = [];
if ($katList) { $katList->data_seek(0); while($r=$katList->fetch_assoc()) $katArr[$r['id']]=$r['nama_kategori']; }

$editSoal = null;
if (isset($_GET['edit'])) {
    $eid      = (int)$_GET['edit'];
    $er       = $conn->query("SELECT * FROM soal WHERE id=$eid LIMIT 1");
    $editSoal = $er ? $er->fetch_assoc() : null;
}

// Hitung per tipe (hanya jika kolom sudah ada)
$statTipe = ['pg'=>0,'bs'=>0,'mcma'=>0];
if ($adaTipeSoal) {
    $stRes = $conn->query("SELECT tipe_soal, COUNT(*) AS c FROM soal GROUP BY tipe_soal");
    if ($stRes) while ($r=$stRes->fetch_assoc()) $statTipe[$r['tipe_soal']] = (int)$r['c'];
} else {
    // Sebelum migrasi: semua dianggap PG
    $stRes = $conn->query("SELECT COUNT(*) AS c FROM soal");
    if ($stRes) $statTipe['pg'] = (int)$stRes->fetch_assoc()['c'];
}

$pageTitle  = 'Bank Soal';
$activeMenu = 'soal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-question-circle-fill me-2 text-primary"></i>Bank Soal</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Bank Soal</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <?php if ($filterKat): ?>
        <a href="<?=BASE_URL?>/admin/preview_soal.php?kat=<?=$filterKat?>" target="_blank"
           class="btn btn-outline-info">
            <i class="bi bi-eye me-1"></i>Preview Semua
        </a>
        <?php endif; ?>
        <a href="<?=BASE_URL?>/admin/export_soal.php<?= $filterKat ? '?kategori_id='.$filterKat : '' ?>"
           class="btn btn-outline-secondary" title="Export soal ke XLSX — siap re-import">
            <i class="bi bi-download me-1"></i>Export XLSX
        </a>
        <a href="<?=BASE_URL?>/admin/import_soal.php" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-1"></i>Tambah Soal
        </button>
    </div>
</div>

<?= renderFlash() ?>

<?php if (!$adaTipeSoal): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div>
        <strong>Migrasi database belum dijalankan.</strong>
        Fitur tipe soal BS & MCMA belum aktif.
        <a href="<?= BASE_URL ?>/install/migrate_v3.php" class="alert-link ms-2" target="_blank">
            Jalankan migrate_v3.php sekarang →
        </a>
        <span class="text-muted ms-2 small">(halaman ini akan berfungsi penuh setelah migrasi)</span>
    </div>
</div>
<?php endif; ?>

<!-- Stat tipe soal -->
<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="fw-bold fs-4 text-primary"><?= $statTipe['pg'] ?></div>
                <div class="small text-muted">PG (Pilihan Ganda)</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="fw-bold fs-4 text-warning"><?= $statTipe['bs'] ?></div>
                <div class="small text-muted">BS (Benar/Salah)</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="fw-bold fs-4" style="color:#7c3aed"><?= $statTipe['mcma'] ?></div>
                <div class="small text-muted">MCMA (Multi Jawaban)</div>
            </div>
        </div>
    </div>
</div>

<?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0">
    <?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?>
</ul></div><?php endif; ?>

<!-- Filter -->
<div class="card mb-3"><div class="card-body py-2">
    <form class="d-flex flex-wrap gap-2" method="GET">
        <select name="kat" class="form-select form-select-sm" style="width:200px">
            <option value="">Semua Kategori</option>
            <?php foreach($katArr as $kid=>$knm): ?>
            <option value="<?=$kid?>" <?=$filterKat==$kid?'selected':''?>><?=htmlspecialchars($knm)?></option>
            <?php endforeach; ?>
        </select>
        <select name="tipe" class="form-select form-select-sm" style="width:160px">
            <option value="">Semua Tipe</option>
            <option value="pg"   <?=$filterTipe==='pg'?'selected':''?>>PG – Pilihan Ganda</option>
            <option value="bs"   <?=$filterTipe==='bs'?'selected':''?>>BS – Benar/Salah</option>
            <option value="mcma" <?=$filterTipe==='mcma'?'selected':''?>>MCMA – Multi Jawaban</option>
        </select>
        <input type="text" name="q" class="form-control form-control-sm" style="width:220px"
               placeholder="Cari pertanyaan…" value="<?=htmlspecialchars($q)?>">
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Filter</button>
        <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
</div></div>

<?php if ($filterKat): ?>
<!-- Form terpisah untuk hapus semua (menghindari nested form) -->
<form method="POST" id="formHapusSemua" style="display:none">
<?= csrfField() ?>
<input type="hidden" name="aksi" value="hapus_semua">
<input type="hidden" name="kat_filter" value="<?= $filterKat ?>">
</form>
<?php endif; ?>

<!-- Tabel Soal -->
<form method="POST" id="formMassal">
<?= csrfField() ?>
<input type="hidden" name="aksi" value="hapus_massal">
<input type="hidden" name="kat_filter" value="<?= $filterKat ?>">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-list-check me-2"></i>Daftar Soal
            <span class="badge bg-primary ms-1"><?=$soalList?$soalList->num_rows:0?> soal</span>
        </span>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span id="infoTerpilih" class="text-muted small" style="display:none">
                <span id="jmlTerpilih">0</span> soal dipilih
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="pilihSemua(this)">
                <i class="bi bi-check-all me-1"></i>Pilih Semua
            </button>
            <button type="submit" class="btn btn-sm btn-danger" id="btnHapusTerpilih" style="display:none"
                    onclick="return confirm('Hapus soal yang dipilih?')">
                <i class="bi bi-trash me-1"></i>Hapus Terpilih
            </button>
            <!-- Hapus semua per kategori -->
            <?php if ($filterKat): ?>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="if(confirm('Hapus SEMUA soal kategori ini? Tindakan ini tidak bisa dibatalkan.')) document.getElementById('formHapusSemua').submit();">
                <i class="bi bi-trash-fill me-1"></i>Hapus Semua Kategori Ini
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0"><div class="table-responsive">
        <table id="tblSoal" class="table table-hover datatable mb-0">
            <thead><tr>
                <th style="width:40px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)" class="form-check-input"></th>
                <th style="width:50px">#</th>
                <th>Pertanyaan</th>
                <th>Kategori</th>
                <th class="text-center">Tipe</th>
                <th class="text-center">Gambar</th>
                <th class="text-center">Jawaban Benar</th>
                <th class="text-center" style="width:100px">Aksi</th>
            </tr></thead>
            <tbody>
            <?php if($soalList&&$soalList->num_rows>0): $no=1; while($s=$soalList->fetch_assoc()): ?>
            <tr>
                <td><input type="checkbox" name="soal_ids[]" value="<?=$s['id']?>" class="form-check-input soal-check" onchange="updateTerpilih()"></td>
                <td><?=$no++?></td>
                <td style="max-width:320px">
                    <p class="mb-0 text-truncate" style="max-width:320px"
                       title="<?=htmlspecialchars($s['pertanyaan'])?>">
                        <?= htmlspecialchars(mb_strlen($s['pertanyaan']) > 75 ? mb_substr($s['pertanyaan'],0,75).'…' : $s['pertanyaan']) ?>
                    </p>
                </td>
                <td><span class="badge bg-info text-dark"><?=htmlspecialchars($s['nama_kategori']??'-')?></span></td>
                <td class="text-center"><?= tipeSoalLabel($s['tipe_soal'] ?? 'pg') ?></td>
                <td class="text-center">
                    <?php if($s['gambar']&&file_exists($uploadDir.$s['gambar'])): ?>
                    <img src="<?=BASE_URL?>/assets/uploads/soal/<?=htmlspecialchars($s['gambar'])?>"
                         style="height:36px;border-radius:4px">
                    <?php else: echo '<span class="text-muted small">-</span>'; endif; ?>
                </td>
                <td class="text-center">
                    <?php
                    $tipe = $s['tipe_soal'] ?? 'pg';
                    if ($tipe === 'mcma') {
                        // Tampilkan semua jawaban benar
                        $arr = explode(',', $s['jawaban_benar']);
                        foreach ($arr as $h) {
                            echo '<span class="badge bg-success me-1">'.strtoupper(trim($h)).'</span>';
                        }
                    } elseif ($tipe === 'bs') {
                        $label = $s['jawaban_benar'] === 'benar' ? 'BENAR' : 'SALAH';
                        $bg    = $s['jawaban_benar'] === 'benar' ? 'success' : 'danger';
                        echo "<span class='badge bg-$bg'>$label</span>";
                    } else {
                        echo "<span class='badge bg-primary fs-6'>".strtoupper($s['jawaban_benar'])."</span>";
                    }
                    ?>
                </td>
                <td class="text-center">
                    <a href="<?=BASE_URL?>/admin/preview_soal.php?id=<?=$s['id']?>" target="_blank"
                       class="btn btn-sm btn-outline-info btn-icon" title="Preview"><i class="bi bi-eye"></i></a>
                    <a href="?edit=<?=$s['id']?>" class="btn btn-sm btn-outline-warning btn-icon"><i class="bi bi-pencil"></i></a>
                    <a href="?hapus=<?=$s['id']?>" class="btn btn-sm btn-outline-danger btn-icon"
                       data-confirm="Hapus soal ini?"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada soal
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<!-- ══════════ MODAL TAMBAH ══════════ -->
<div class="modal fade <?=$errors&&($_POST['aksi']??'')==='tambah'?'show':''?>"
     id="modalTambah" tabindex="-1"
     <?=$errors&&($_POST['aksi']??'')==='tambah'?'style="display:block"':''?>>
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
        <form method="POST" enctype="multipart/form-data" id="formTambahSoal"
              style="display:contents">
<?= csrfField() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Soal Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="overflow-y:auto;max-height:calc(100vh - 200px)">

                <!-- ── Baris 1: Kategori + Tipe + Gambar ── -->
                <div class="row g-3 mb-3 pb-3 border-bottom">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold mb-1">Kategori <span class="text-danger">*</span></label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach($katArr as $kid=>$knm): ?>
                            <option value="<?=$kid?>" <?=(($_POST['kategori_id']??0)==$kid)?'selected':''?>><?=htmlspecialchars($knm)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Tipe Soal <span class="text-danger">*</span></label>
                        <select name="tipe_soal" id="tipeTambah" class="form-select" onchange="ubahTipe(this,'tambah')">
                            <option value="pg"   <?=(($_POST['tipe_soal']??'pg')==='pg')?'selected':''?>>PG – Pilihan Ganda</option>
                            <option value="bs"   <?=(($_POST['tipe_soal']??'')==='bs')?'selected':''?>>BS – Benar / Salah</option>
                            <option value="mcma" <?=(($_POST['tipe_soal']??'')==='mcma')?'selected':''?>>MCMA – Multi Jawaban</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Gambar <small class="text-muted fw-normal">(opsional)</small></label>
                        <input type="file" name="gambar" class="form-control" accept="image/*">
                    </div>
                </div>

                <!-- ── Teks Bacaan ── -->
                <div class="mb-3">
                    <label class="form-label fw-semibold mb-1">
                        Teks Bacaan / Wacana
                        <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                    </label>
                    <textarea name="teks_bacaan" class="form-control" rows="3"
                              placeholder="Isi teks bacaan/wacana di sini. Kosongkan jika tidak ada."
                              style="font-size:13px;resize:vertical"><?=htmlspecialchars($_POST['teks_bacaan']??'')?></textarea>
                    <div class="form-text">Teks ini akan tampil di atas pertanyaan saat ujian.</div>
                </div>

                <!-- ── Pertanyaan ── -->
                <div class="mb-3">
                    <label class="form-label fw-semibold mb-1">Pertanyaan <span class="text-danger">*</span></label>
                    <textarea name="pertanyaan" class="form-control" rows="3" required
                              placeholder="Tulis pertanyaan di sini..."
                              style="resize:vertical"><?=htmlspecialchars($_POST['pertanyaan']??'')?></textarea>
                </div>

                <!-- ── Pilihan Jawaban ── -->
                <div id="blokPilihanTambah" class="mb-3">
                    <label class="form-label fw-semibold mb-2">Pilihan Jawaban <span class="text-danger">*</span></label>
                    <div class="row g-2">
                        <?php foreach(['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$label): ?>
                        <div class="col-md-6">
                            <div class="input-group mb-1">
                                <span class="input-group-text fw-bold bg-primary text-white" style="width:40px;justify-content:center"><?=$label?></span>
                                <input type="text" name="pilihan_<?=$k?>" class="form-control"
                                       placeholder="Teks pilihan <?=$label?>"
                                       value="<?=htmlspecialchars($_POST['pilihan_'.$k]??'')?>">
                            </div>
                            <div class="ms-1">
                                <input type="file" name="gambar_pilihan_<?=$k?>" class="form-control form-control-sm" accept="image/*"
                                       style="font-size:11px">
                                <div class="form-text" style="font-size:10px">Gambar opsional untuk pilihan <?=$label?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Jawaban Benar ── -->
                <div class="mb-3 p-3 bg-light rounded" id="blokJawabanTambah">
                    <label class="form-label fw-semibold mb-2 text-success"><i class="bi bi-check-circle me-1"></i>Jawaban Benar <span class="text-danger">*</span></label>
                    <!-- PG -->
                    <div id="jwbPgTambah">
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach(['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $j=>$lbl): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jawaban_benar"
                                       id="jwb_t_<?=$j?>" value="<?=$j?>"
                                       <?=(($_POST['jawaban_benar']??'a')===$j)?'checked':''?>>
                                <label class="form-check-label fw-bold" for="jwb_t_<?=$j?>"><?=$lbl?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- BS -->
                    <div id="jwbBsTambah" style="display:none">
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jawaban_benar_bs"
                                       id="bs_t_benar" value="benar"
                                       <?=(($_POST['jawaban_benar_bs']??'benar')==='benar')?'checked':''?>>
                                <label class="form-check-label fw-bold text-success" for="bs_t_benar">BENAR</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="jawaban_benar_bs"
                                       id="bs_t_salah" value="salah"
                                       <?=(($_POST['jawaban_benar_bs']??'')==='salah')?'checked':''?>>
                                <label class="form-check-label fw-bold text-danger" for="bs_t_salah">SALAH</label>
                            </div>
                        </div>
                    </div>
                    <!-- MCMA -->
                    <div id="jwbMcmaTambah" style="display:none">
                        <div class="form-text mb-2">Centang semua pilihan yang merupakan jawaban benar (minimal 2).</div>
                        <div class="d-flex gap-3 flex-wrap">
                            <?php foreach(['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$lbl): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="jawaban_mcma[]" value="<?=$k?>"
                                       id="mcma_<?=$k?>_t"
                                       <?= in_array($k, (array)($_POST['jawaban_mcma']??[])) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="mcma_<?=$k?>_t"><?=$lbl?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Pembahasan ── -->
                <div class="mb-1">
                    <label class="form-label fw-semibold mb-1">
                        Pembahasan
                        <small class="text-muted fw-normal">(opsional — ditampilkan setelah ujian selesai)</small>
                    </label>
                    <textarea name="pembahasan" class="form-control" rows="2"
                              placeholder="Jelaskan alasan jawaban yang benar..."
                              style="font-size:13px;resize:vertical"><?=htmlspecialchars($_POST['pembahasan']??'')?></textarea>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Batal
                </button>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i>Simpan Soal
                </button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- ══════════ MODAL EDIT ══════════ -->
<?php if($editSoal): ?>
<?php
$eTipe = $editSoal['tipe_soal'] ?? 'pg';
$eJwb  = $editSoal['jawaban_benar'] ?? '';
$eJwbArr = $eTipe === 'mcma' ? explode(',', $eJwb) : [];
?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
<?= csrfField() ?>
            <input type="hidden" name="aksi"        value="edit">
            <input type="hidden" name="id"          value="<?=$editSoal['id']?>">
            <input type="hidden" name="gambar_lama" value="<?=htmlspecialchars($editSoal['gambar']??'')?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Soal</h5>
                <a href="<?=BASE_URL?>/admin/soal.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Kategori</label>
                        <select name="kategori_id" class="form-select" required>
                            <?php foreach($katArr as $kid=>$knm): ?>
                            <option value="<?=$kid?>" <?=($editSoal['kategori_id']==$kid)?'selected':''?>><?=htmlspecialchars($knm)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tipe Soal</label>
                        <select name="tipe_soal" id="tipeEdit" class="form-select" onchange="ubahTipe(this,'edit')">
                            <option value="pg"   <?=$eTipe==='pg'?'selected':''?>>PG – Pilihan Ganda</option>
                            <option value="bs"   <?=$eTipe==='bs'?'selected':''?>>BS – Benar / Salah</option>
                            <option value="mcma" <?=$eTipe==='mcma'?'selected':''?>>MCMA – Multi Jawaban</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Gambar Baru</label>
                        <input type="file" name="gambar_baru" class="form-control" accept="image/*">
                        <?php if($editSoal['gambar']&&file_exists($uploadDir.$editSoal['gambar'])): ?>
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <img src="<?=BASE_URL?>/assets/uploads/soal/<?=htmlspecialchars($editSoal['gambar'])?>"
                                 style="height:40px;border-radius:4px">
                            <label class="form-check-label small">
                                <input type="checkbox" name="hapus_gambar" value="1" class="form-check-input me-1">
                                Hapus
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Teks Bacaan -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Teks Bacaan / Wacana
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <textarea name="teks_bacaan" class="form-control" rows="4"
                                  placeholder="Kosongkan jika tidak ada teks bacaan."
                                  style="font-size:13px"><?=htmlspecialchars($editSoal['teks_bacaan']??'')?></textarea>
                        <div class="form-text">Teks ini akan tampil di atas pertanyaan saat ujian.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Pertanyaan</label>
                        <textarea name="pertanyaan" class="form-control" rows="3" required><?=htmlspecialchars($editSoal['pertanyaan'])?></textarea>
                    </div>
                    <!-- Pilihan -->
                    <div id="blokPilihanEdit">
                        <div class="row g-2">
                        <?php foreach(['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$label):
                            $gambarPilihanLama = $editSoal['gambar_pilihan_'.$k] ?? '';
                        ?>
                        <div class="col-md-6 mb-2" id="rowPilihan_<?=$k?>_e">
                            <label class="form-label fw-semibold">Pilihan <?=$label?></label>
                            <input type="text" name="pilihan_<?=$k?>" class="form-control mb-1"
                                   value="<?=htmlspecialchars($editSoal['pilihan_'.$k]??'')?>">
                            <!-- Gambar pilihan -->
                            <input type="hidden" name="gambar_pilihan_<?=$k?>_lama"
                                   value="<?=htmlspecialchars($gambarPilihanLama)?>">
                            <input type="file" name="gambar_pilihan_<?=$k?>_baru"
                                   class="form-control form-control-sm" accept="image/*"
                                   style="font-size:11px">
                            <?php if ($gambarPilihanLama): ?>
                            <div class="mt-1 d-flex align-items-center gap-2">
                                <img src="<?=BASE_URL?>/assets/uploads/soal/<?=htmlspecialchars($gambarPilihanLama)?>"
                                     style="height:36px;border-radius:4px" alt="Gambar pilihan <?=$label?>">
                                <label class="form-check-label small" style="font-size:11px">
                                    <input type="checkbox" name="hapus_gambar_pilihan_<?=$k?>" value="1"
                                           class="form-check-input me-1">
                                    Hapus
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Jawaban Benar -->
                    <div class="col-12">
                        <div id="jwbPgEdit" <?=$eTipe!=='pg'?'style="display:none"':''?>>
                            <label class="form-label fw-semibold">Jawaban Benar</label>
                            <select name="jawaban_benar" class="form-select">
                                <?php foreach(['a','b','c','d'] as $j): ?>
                                <option value="<?=$j?>" <?=($eTipe==='pg'&&$eJwb===$j)?'selected':''?>><?=strtoupper($j)?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="jwbBsEdit" <?=$eTipe!=='bs'?'style="display:none"':''?>>
                            <label class="form-label fw-semibold">Jawaban Benar</label>
                            <select name="jawaban_benar_bs" class="form-select">
                                <option value="benar" <?=($eTipe==='bs'&&$eJwb==='benar')?'selected':''?>>BENAR</option>
                                <option value="salah" <?=($eTipe==='bs'&&$eJwb==='salah')?'selected':''?>>SALAH</option>
                            </select>
                        </div>
                        <div id="jwbMcmaEdit" <?=$eTipe!=='mcma'?'style="display:none"':''?>>
                            <label class="form-label fw-semibold">Jawaban Benar
                                <small class="text-muted">(pilih 2 atau lebih)</small>
                            </label>
                            <div class="d-flex gap-3 mt-1">
                                <?php foreach(['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="jawaban_mcma[]" value="<?=$k?>"
                                           id="mcma_<?=$k?>_e"
                                           <?= in_array($k, $eJwbArr) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="mcma_<?=$k?>_e"><?=$label?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Pembahasan edit -->
                <div class="row mt-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Pembahasan <small class="text-muted fw-normal">(opsional — ditampilkan setelah ujian)</small></label>
                        <textarea name="pembahasan" class="form-control" rows="3"
                                  placeholder="Jelaskan alasan jawaban yang benar..."
                                  style="font-size:13px"><?=htmlspecialchars($editSoal['pembahasan']??'')?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?=BASE_URL?>/admin/soal.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php if($errors&&($_POST['aksi']??'')==='tambah'): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('modalTambah')).show())</script>
<?php endif; ?>

<script>
// ── Hapus Massal ─────────────────────────────────────────────
function updateTerpilih() {
    const checks  = document.querySelectorAll('.soal-check:checked');
    const allCheck = document.getElementById('checkAll');
    const allBoxes = document.querySelectorAll('.soal-check');
    const btn     = document.getElementById('btnHapusTerpilih');
    const info    = document.getElementById('infoTerpilih');
    const jml     = document.getElementById('jmlTerpilih');

    const jumlah = checks.length;
    btn.style.display  = jumlah > 0 ? 'inline-flex' : 'none';
    info.style.display = jumlah > 0 ? 'inline' : 'none';
    if (jml) jml.textContent = jumlah;
    if (allCheck) allCheck.indeterminate = jumlah > 0 && jumlah < allBoxes.length;
    if (allCheck) allCheck.checked = jumlah === allBoxes.length && allBoxes.length > 0;
}

function toggleAll(cb) {
    document.querySelectorAll('.soal-check').forEach(c => c.checked = cb.checked);
    updateTerpilih();
}

function pilihSemua(btn) {
    const checks = document.querySelectorAll('.soal-check');
    const semuaTerpilih = [...checks].every(c => c.checked);
    checks.forEach(c => c.checked = !semuaTerpilih);
    const allCheck = document.getElementById('checkAll');
    if (allCheck) allCheck.checked = !semuaTerpilih;
    updateTerpilih();
}

// ── Ubah tampilan form sesuai tipe soal ───────────────────────
function ubahTipe(sel, mode) {
    const tipe = sel.value;
    const s = mode; // 'tambah' atau 'edit'

    // Blok pilihan
    const blokPilihan = document.getElementById('blokPilihan' + (s==='tambah'?'Tambah':'Edit'));

    if (tipe === 'bs') {
        // BS: sembunyikan semua pilihan (auto-filled)
        if (blokPilihan) blokPilihan.style.display = 'none';
    } else {
        if (blokPilihan) blokPilihan.style.display = '';
        // Untuk mode edit: tampilkan/sembunyikan baris pilihan C dan D
        if (s === 'edit') {
            ['c','d'].forEach(k => {
                const row = document.getElementById('rowPilihan_'+k+'_e');
                if (row) row.style.display = '';
            });
        }
    }

    // Blok jawaban benar
    const suffix = s === 'tambah' ? 'Tambah' : 'Edit';
    document.getElementById('jwbPg'   + suffix).style.display = tipe === 'pg'   ? '' : 'none';
    document.getElementById('jwbBs'   + suffix).style.display = tipe === 'bs'   ? '' : 'none';
    document.getElementById('jwbMcma' + suffix).style.display = tipe === 'mcma' ? '' : 'none';
}

// Jalankan saat load untuk modal edit yang langsung terbuka
document.addEventListener('DOMContentLoaded', () => {
    const tipeEditEl = document.getElementById('tipeEdit');
    if (tipeEditEl) ubahTipe(tipeEditEl, 'edit');
    const tipeTambahEl = document.getElementById('tipeTambah');
    if (tipeTambahEl) ubahTipe(tipeTambahEl, 'tambah');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
