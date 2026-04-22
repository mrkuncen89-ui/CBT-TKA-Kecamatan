<?php
// ============================================================
// ujian/submit.php — Submit ujian: batasi 1x, simpan ke hasil_ujian
// ============================================================
if (session_status() === PHP_SESSION_NONE) { session_name('TKA_PESERTA'); session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

if (empty($_SESSION['peserta_id'])) redirect(BASE_URL . '/ujian/login_peserta.php');

$ujianId   = (int)$_SESSION['ujian_id'];
$pesertaId = (int)$_SESSION['peserta_id'];

$isSubmit = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1')
         || ($_GET['auto'] ?? '') === '1';

if (!$isSubmit) redirect(BASE_URL . '/ujian/soal.php');

// ── Batasi submit: cek setting ujian_ulang ───────────────────
// BUG FIX #3: $ujianUlang sebelumnya diambil tapi tidak pernah digunakan.
$ujianUlang = getSetting($conn, 'ujian_ulang', '0');
$cekHasil   = $conn->query(
    "SELECT h.id, h.nilai, h.jml_benar, h.total_soal
     FROM hasil_ujian h
     WHERE h.ujian_id = $ujianId AND h.peserta_id = $pesertaId
     LIMIT 1"
);
if ($cekHasil && $cekHasil->num_rows > 0 && $ujianUlang !== '1') {
    // Ujian ulang tidak diizinkan — tampilkan hasil lama
    $existing = $cekHasil->fetch_assoc();
    $_SESSION['hasil_benar'] = $existing['jml_benar'];
    $_SESSION['hasil_nilai'] = $existing['nilai'];
    $_SESSION['hasil_total'] = $existing['total_soal'];
    $_SESSION['hasil_detail'] = [];
    session_unset(); session_destroy();
    redirect(BASE_URL . '/ujian/selesai.php');
}

// ── Cek ujian milik peserta ini & belum selesai ───────────────
// BUG FIX #2: Gunakan UPDATE atomic sebagai "klaim" submit untuk mencegah race condition.
// Jika dua request masuk bersamaan, hanya satu yang akan affected_rows=1.
$conn->query(
    "UPDATE ujian SET waktu_selesai = NOW()
     WHERE id = $ujianId AND peserta_id = $pesertaId AND waktu_selesai IS NULL"
);
if ($conn->affected_rows === 0) {
    // Submit sudah diproses oleh request lain, atau ujian sudah selesai sebelumnya.
    redirect(BASE_URL . '/ujian/selesai.php');
}

// Ambil data ujian yang sudah di-klaim
$_qUjian = $conn->query(
    "SELECT id, peserta_id, waktu_mulai, waktu_selesai, nilai, token_id, jadwal_id, kategori_id, soal_order, pelanggaran, last_activity FROM ujian
     WHERE id = $ujianId AND peserta_id = $pesertaId
     LIMIT 1"
);
$ujian = ($_qUjian && $_qUjian->num_rows > 0) ? $_qUjian->fetch_assoc() : null;
if ($_qUjian) $_qUjian->free();

if (!is_array($ujian)) redirect(BASE_URL . '/ujian/selesai.php');

// ── Hitung nilai dari jawaban ─────────────────────────────────
$soalOrder = $_SESSION['soal_order'] ?? [];

// Jika soal_order session kosong (misal session expired saat submit)
// ambil dari kolom soal_order di tabel ujian sebagai fallback
if (empty($soalOrder) && !empty($ujian['soal_order'])) {
    $soalOrder = json_decode($ujian['soal_order'], true) ?: [];
}

// Total soal dari jumlah soal_order, fallback ke pengaturan
$totalSoal = count($soalOrder) > 0
    ? count($soalOrder)
    : (int)getSetting($conn, 'jumlah_soal', '20');

$ids = !empty($soalOrder)
    ? implode(',', array_map('intval', $soalOrder))
    : '0';

// Hitung benar & dijawab — filter hanya soal dalam ujian ini
// Scoring MCMA dilakukan di PHP agar kompatibel dengan MySQL 5.7+
// (MCMA: "a,b" == "b,a" → dianggap benar setelah dinormalisasi)
$statRes = $conn->query(
    "SELECT j.jawaban AS jwb_siswa, s.jawaban_benar AS jwb_kunci, s.tipe_soal, s.kategori_id
     FROM jawaban j
     JOIN soal s ON s.id = j.soal_id
     WHERE j.ujian_id = $ujianId
       AND j.peserta_id = $pesertaId
       AND j.soal_id IN ($ids)"
);

$benar      = 0;
$dijawab    = 0;
$kategoriId = null;
if ($statRes) {
    while ($row = $statRes->fetch_assoc()) {
        $dijawab++;
        if (!$kategoriId && !empty($row['kategori_id'])) {
            $kategoriId = (int)$row['kategori_id'];
        }
        if ($row['tipe_soal'] === 'mcma') {
            // Normalisasi kedua sisi: pecah koma, trim, sort, deduplikasi
            $siswa = explode(',', strtolower($row['jwb_siswa']));
            $kunci = explode(',', strtolower($row['jwb_kunci']));
            $siswa = array_unique(array_map('trim', $siswa)); sort($siswa);
            $kunci = array_unique(array_map('trim', $kunci));  sort($kunci);
            if ($siswa === $kunci) $benar++;
        } else {
            if ($row['jwb_siswa'] === $row['jwb_kunci']) $benar++;
        }
    }
}
$salah   = $dijawab - $benar;
$kosong  = max(0, $totalSoal - $dijawab); // soal yang tidak dijawab sama sekali
$nilai   = $totalSoal > 0 ? round(($benar / $totalSoal) * 100, 2) : 0;
$durasiDetik = $ujian['waktu_mulai']
    ? (int)(time() - strtotime($ujian['waktu_mulai']))
    : null;

// ── Simpan nilai ke ujian ─────────────────────────────────────
// (waktu_selesai sudah di-set secara atomic di atas untuk mencegah race condition)
$conn->query(
    "UPDATE ujian SET nilai = $nilai, kategori_id = " . ($kategoriId ?: 'NULL') . " WHERE id = $ujianId"
);

// ── Simpan ke hasil_ujian (insert 1x, tidak bisa double) ─────
// kategori_id diambil dari soal (sudah tersedia di $kategoriId)
// Fallback ke jadwal jika $kategoriId tidak tersedia
$katFinal = $kategoriId ?: ($ujian['jadwal_id']
    ? (function() use ($conn, $ujian) { $_q = $conn->query("SELECT kategori_id FROM jadwal_ujian WHERE id={$ujian['jadwal_id']} LIMIT 1"); return ($_q && $_q->num_rows > 0) ? (int)($_q->fetch_assoc()['kategori_id'] ?? 0) : 0; })()
    : 0);
$katStr  = $katFinal ? $katFinal : 'NULL';
$jadwalId = $ujian['jadwal_id'] ? (int)$ujian['jadwal_id'] : 'NULL';
$duStr    = $durasiDetik !== null ? $durasiDetik : 'NULL';
$conn->query(
    "INSERT IGNORE INTO hasil_ujian
        (ujian_id, peserta_id, jadwal_id, kategori_id, total_soal, jml_benar, jml_salah, jml_kosong,
         nilai, waktu_mulai, waktu_selesai, durasi_detik)
     VALUES
        ($ujianId, $pesertaId, $jadwalId, $katStr, $totalSoal, $benar, $salah, $kosong,
         $nilai, '{$ujian['waktu_mulai']}', NOW(), $duStr)"
);

// ── Ambil detail soal untuk pembahasan ───────────────────────
// Batasi hanya soal yang ada di soal_order session (keamanan data)
$detailSoal = [];
if (!empty($soalOrder)) {
    $detailRes = $conn->query(
        "SELECT s.id, s.pertanyaan, s.tipe_soal,
                s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d,
                s.jawaban_benar, s.pembahasan,
                j.jawaban AS jawaban_siswa
         FROM soal s
         LEFT JOIN jawaban j ON j.soal_id = s.id
             AND j.ujian_id = $ujianId
             AND j.peserta_id = $pesertaId
         WHERE s.id IN ($ids)
         ORDER BY FIELD(s.id, $ids)"
    );
    if ($detailRes) while ($d = $detailRes->fetch_assoc()) $detailSoal[] = $d;
}

// ── Simpan ke session untuk halaman selesai ───────────────────
$_SESSION['hasil_benar']  = $benar;
$_SESSION['hasil_nilai']  = $nilai;
$_SESSION['hasil_total']  = $totalSoal;
$_SESSION['hasil_detail'] = $detailSoal;
unset($_SESSION['soal_order']);

logActivity($conn, 'Submit Ujian', "Peserta ID $pesertaId, benar: $benar/$totalSoal, nilai: $nilai");

redirect(BASE_URL . '/ujian/selesai.php');
