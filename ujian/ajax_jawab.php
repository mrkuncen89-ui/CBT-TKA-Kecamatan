<?php
// ============================================================
// ujian/ajax_jawab.php — Simpan jawaban via AJAX
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['peserta_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Session habis']);
    exit;
}

$ujianId = (int)$_SESSION['ujian_id'];

// Ping (keep-alive) — tidak simpan jawaban
if (isset($_POST['ping'])) {
    updateUjianActivity($conn, $ujianId);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Record Pelanggaran (Proctoring) ───────────────────────────
if (isset($_POST['violation'])) {
    $conn->query("UPDATE ujian SET pelanggaran = pelanggaran + 1 WHERE id = $ujianId");
    updateUjianActivity($conn, $ujianId);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Validasi waktu server-side ────────────────────────────────
// Cegah jawaban masuk setelah jam sesi token berakhir
// BUG FIX #4: Gunakan tanggal_ujian dari session (bukan date('Y-m-d') hari ini)
// agar tidak salah saat ujian melewati tengah malam.
$jamSelesai = $_SESSION['jam_selesai'] ?? null;
if ($jamSelesai) {
    $tanggalUjian = $_SESSION['tanggal_ujian'] ?? date('Y-m-d');
    $batasWaktu = strtotime("$tanggalUjian $jamSelesai");
    if (time() > $batasWaktu) {
        echo json_encode(['ok' => false, 'msg' => 'Waktu ujian sudah berakhir', 'expired' => true]);
        exit;
    }
}


$soalId  = (int)($_POST['soal_id'] ?? 0);
$jawaban = trim($_POST['jawaban'] ?? '');

if (!$soalId || $jawaban === '') {
    echo json_encode(['ok' => false, 'msg' => 'Data tidak lengkap']);
    exit;
}

// Validasi: soal harus ada
$cekSoal = $conn->query("SELECT id, tipe_soal FROM soal WHERE id=$soalId LIMIT 1");
if (!$cekSoal || $cekSoal->num_rows === 0) {
    echo json_encode(['ok' => false, 'msg' => 'Soal tidak valid']);
    exit;
}

$soalRow = $cekSoal->fetch_assoc();
$tipe    = $soalRow['tipe_soal'];

// Validasi nilai jawaban sesuai tipe
if ($tipe === 'bs') {
    if (!in_array($jawaban, ['benar','salah'])) {
        echo json_encode(['ok' => false, 'msg' => 'Jawaban tidak valid']);
        exit;
    }
} elseif ($tipe === 'mcma') {
    // MCMA: format 'a,b,c' — validasi tiap huruf
    $arr = explode(',', $jawaban);
    foreach ($arr as $h) {
        if (!in_array(trim($h), ['a','b','c','d'])) {
            echo json_encode(['ok' => false, 'msg' => 'Jawaban MCMA tidak valid']);
            exit;
        }
    }
    // Sort dan simpan rapi
    sort($arr);
    $jawaban = implode(',', array_unique($arr));
} else {
    if (!in_array($jawaban, ['a','b','c','d'])) {
        echo json_encode(['ok' => false, 'msg' => 'Jawaban tidak valid']);
        exit;
    }
}

$jwb       = $conn->real_escape_string($jawaban);
$pesertaId = (int)$_SESSION['peserta_id'];

// Validasi: soal harus milik ujian ini (cek dari DB, bukan hanya session)
// Cek ujian masih aktif dan soal ada di jawaban/soal yang valid
$cekUjian = $conn->query(
    "SELECT id FROM ujian WHERE id=$ujianId AND peserta_id=$pesertaId AND waktu_selesai IS NULL LIMIT 1"
);
if (!$cekUjian || $cekUjian->num_rows === 0) {
    echo json_encode(['ok' => false, 'msg' => 'Ujian tidak aktif']);
    exit;
}

// Ambil soal_order dari session, fallback ke DB jika kosong
$soalOrder = $_SESSION['soal_order'] ?? [];
if (empty($soalOrder)) {
    $_qUjAj = $conn->query("SELECT soal_order FROM ujian WHERE id=$ujianId AND peserta_id=$pesertaId LIMIT 1");
$ujianRow = ($_qUjAj && $_qUjAj->num_rows > 0) ? $_qUjAj->fetch_assoc() : null;
    if (!empty($ujianRow['soal_order'])) {
        $soalOrder = json_decode($ujianRow['soal_order'], true) ?: [];
        $_SESSION['soal_order'] = $soalOrder; // restore ke session
    }
}

// Validasi soal ada dalam ujian ini
if (!empty($soalOrder) && !in_array((int)$soalId, array_map('intval', $soalOrder))) {
    echo json_encode(['ok' => false, 'msg' => 'Soal tidak dalam sesi ujian']);
    exit;
}

// Upsert jawaban — ON DUPLICATE KEY pakai constraint uq_ujian_peserta_soal
$result = $conn->query(
    "INSERT INTO jawaban (ujian_id, peserta_id, soal_id, jawaban)
     VALUES ($ujianId, $pesertaId, $soalId, '$jwb')
     ON DUPLICATE KEY UPDATE jawaban='$jwb', peserta_id=$pesertaId"
);
if (!$result) {
    echo json_encode(['ok' => false, 'msg' => 'DB error: ' . $conn->error]);
    exit;
}

// Update last_activity
updateUjianActivity($conn, $ujianId);

echo json_encode(['ok' => true]);
