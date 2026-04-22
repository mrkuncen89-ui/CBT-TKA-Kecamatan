<?php
// ujian/ajax_soal.php — Ambil data soal via AJAX (tanpa reload halaman)
if (session_status() === PHP_SESSION_NONE) { session_name('TKA_PESERTA'); session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['peserta_id'])) { echo json_encode(['ok'=>false,'msg'=>'Session expired']); exit; }

$no        = max(1, (int)($_GET['no'] ?? 1));
$ujianId   = (int)$_SESSION['ujian_id'];
$pesertaId = (int)$_SESSION['peserta_id'];

// Ambil soal list dari session
if (empty($_SESSION['soal_order'])) { echo json_encode(['ok'=>false,'msg'=>'Soal tidak ditemukan']); exit; }

$ids      = implode(',', array_map('intval', $_SESSION['soal_order']));
$total    = count($_SESSION['soal_order']);
$no       = max(1, min($no, $total));

// Ambil hanya 1 soal yang diminta — lebih efisien dari fetch semua
$targetId = (int)$_SESSION['soal_order'][$no - 1];
$res = $conn->query(
    "SELECT id,tipe_soal,pertanyaan,teks_bacaan,gambar,
            pilihan_a,pilihan_b,pilihan_c,pilihan_d,
            gambar_pilihan_a,gambar_pilihan_b,gambar_pilihan_c,gambar_pilihan_d
     FROM soal WHERE id=$targetId LIMIT 1"
);
$soal = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
if ($res) $res->free();
if (!$soal) { echo json_encode(['ok'=>false,'msg'=>'Soal tidak ditemukan']); exit; }
$soalId = $soal['id'];

// Ambil jawaban soal ini saja
$raguList = $_SESSION['ragu'] ?? [];
$jr1 = $conn->query("SELECT jawaban FROM jawaban WHERE ujian_id=$ujianId AND peserta_id=$pesertaId AND soal_id=$soalId LIMIT 1");
$jwbAktif = ($jr1 && $jr1->num_rows > 0) ? $jr1->fetch_assoc()['jawaban'] : null;
if ($jr1) $jr1->free();

$isRagu = in_array($soalId, $raguList);

// Hitung stats dari session (sudah tersimpan saat jawab) + DB count
$jr2 = $conn->query("SELECT COUNT(*) AS c FROM jawaban WHERE ujian_id=$ujianId AND peserta_id=$pesertaId");
$sdhJawab   = $jr2 ? (int)($jr2->fetch_assoc()['c'] ?? 0) : 0;
if ($jr2) $jr2->free();
$jumlahRagu = count($raguList);
$belumJawab = $total - $sdhJawab;

// Ambil setting acak pilihan
$acakPilihan = getSetting($conn, 'acak_pilihan', '0') === '1';

// Build pilihan HTML
$baseUrl = BASE_URL;
$pilihanHtml = '';
if ($soal['tipe_soal'] === 'bs') {
    foreach (['benar'=>'Benar','salah'=>'Salah'] as $val=>$label) {
        $sel = $jwbAktif===$val ? 'selected' : '';
        $huruf = $val==='benar' ? 'B' : 'S';
        $pilihanHtml .= "<div class=\"pilihan-item $sel\" onclick=\"pilihJawaban('$val',this,$soalId)\"><div class=\"huruf-box\">$huruf</div><div class=\"pilihan-teks\">$label</div></div>";
    }
} elseif ($soal['tipe_soal'] === 'mcma') {
    $jwbMcmaArr = $jwbAktif ? explode(',', $jwbAktif) : [];
    $pilihanHtml .= '<div class="mcma-info"><i class="bi bi-info-circle me-1"></i>Boleh pilih lebih dari satu jawaban yang benar.</div>';
    foreach (['a','b','c','d'] as $h) {
        $teks = $soal['pilihan_'.$h] ?? '';
        if (!$teks) continue;
        $dipilih = in_array($h, $jwbMcmaArr);
        $selCls  = $dipilih ? 'mcma-selected' : '';
        $chkBg   = $dipilih ? '#7c3aed' : 'transparent';
        $chkBdr  = $dipilih ? '#7c3aed' : '#cbd5e1';
        $chkMark = $dipilih ? '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>' : '';
        $teksEsc = htmlspecialchars($teks);
        $pilihanHtml .= "<div class=\"pilihan-item $selCls\" onclick=\"pilihMcma('$h',this,$soalId)\"><div class=\"huruf-box\">".strtoupper($h)."</div><div class=\"pilihan-teks\">$teksEsc</div><div style=\"margin-left:auto;flex-shrink:0\"><div class=\"mcma-check\" style=\"width:20px;height:20px;border-radius:4px;border:2px solid $chkBdr;background:$chkBg;display:flex;align-items:center;justify-content:center\">$chkMark</div></div></div>";
    }
    $pilihanHtml .= '<input type="hidden" id="mcmaValue" value="'.htmlspecialchars($jwbAktif??'').'">';
} else {
    // PG — support acak pilihan & gambar pilihan
    $sessionKey = "pilihan_order_{$soalId}";
    if ($acakPilihan) {
        if (!isset($_SESSION[$sessionKey])) {
            $order = array_filter(['a','b','c','d'], fn($k) => !empty($soal['pilihan_'.$k]));
            $order = array_values($order);
            shuffle($order);
            $labels = ['a','b','c','d'];
            $mapping = [];
            foreach ($order as $i => $asli) $mapping[$labels[$i]] = $asli;
            $_SESSION[$sessionKey] = $mapping;
        }
        $pilihanMapping = $_SESSION[$sessionKey];
        $pilihanLoop = array_keys($pilihanMapping);
    } else {
        $pilihanMapping = null;
        $pilihanLoop = ['a','b','c','d'];
    }

    foreach ($pilihanLoop as $hTampil) {
        $hAsli   = $pilihanMapping ? $pilihanMapping[$hTampil] : $hTampil;
        $teks    = $soal['pilihan_'.$hAsli] ?? '';
        $gambarP = $soal['gambar_pilihan_'.$hAsli] ?? '';
        if (!$teks && !$gambarP) continue;
        $sel      = $jwbAktif===$hAsli ? 'selected' : '';
        $teksEsc  = htmlspecialchars($teks);
        $gambarHtml = $gambarP
            ? "<img src=\"{$baseUrl}/assets/uploads/soal/".htmlspecialchars($gambarP)."\" style=\"max-width:180px;max-height:100px;border-radius:6px;display:block;margin-bottom:4px\" alt=\"\">"
            : '';
        $pilihanHtml .= "<div class=\"pilihan-item $sel\" onclick=\"pilihJawaban('$hAsli',this,$soalId)\"><div class=\"huruf-box\">".strtoupper($hTampil)."</div><div class=\"pilihan-teks\">{$gambarHtml}{$teksEsc}</div></div>";
    }
}

// Teks bacaan
$teksBacaan = '';
if (!empty($soal['teks_bacaan'])) {
    $tb = htmlspecialchars($soal['teks_bacaan']);
    $teksBacaan = "<div style=\"background:#f0f9ff;border-left:4px solid #1a56db;border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:18px;font-size:14px;line-height:1.8;color:#1e293b;\"><div style=\"font-size:11px;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;\">&#128196; Bacalah teks berikut!</div>".nl2br($tb)."</div>";
}

// Gambar
$gambarHtml = '';
if ($soal['gambar']) {
    $g = htmlspecialchars($soal['gambar']);
    // BUG FIX #5: Gunakan BASE_URL agar path gambar benar saat di subfolder
    $gambarHtml = "<img src=\"" . BASE_URL . "/assets/uploads/soal/$g\" class=\"soal-img\" alt=\"Gambar soal\">";
}

// Nav buttons state
// BUG FIX #1: $soalList dan $jawabans tidak pernah didefinisikan sebelumnya.
// Bangun $soalList dari soal_order session, dan $jawabans dari DB.
$soalList = array_map(fn($id) => ['id' => (int)$id], $_SESSION['soal_order']);

$jawabans = [];
if (!empty($ids)) {
    $jr3 = $conn->query(
        "SELECT soal_id FROM jawaban WHERE ujian_id=$ujianId AND peserta_id=$pesertaId AND soal_id IN ($ids)"
    );
    if ($jr3) {
        while ($r = $jr3->fetch_assoc()) $jawabans[(int)$r['soal_id']] = true;
        $jr3->free();
    }
}

$navBtns = [];
foreach ($soalList as $idx => $s) {
    $n   = $idx + 1;
    $sid = $s['id'];
    $cls = '';
    if ($n === $no)               $cls .= ' current';
    if (isset($jawabans[$sid]))   $cls .= ' answered';
    if (in_array($sid,$raguList)) $cls .= ' ragu';
    $navBtns[] = ['n'=>$n,'cls'=>trim($cls)];
}

echo json_encode([
    'ok'          => true,
    'no'          => $no,
    'total'       => $total,
    'soalId'      => $soalId,
    'tipe'        => $soal['tipe_soal'],
    'pertanyaan'  => nl2br(htmlspecialchars($soal['pertanyaan'])),
    'teksBacaan'  => $teksBacaan,
    'gambar'      => $gambarHtml,
    'pilihanHtml' => $pilihanHtml,
    'jwbAktif'    => $jwbAktif,
    'isRagu'      => $isRagu,
    'sdhJawab'    => $sdhJawab,
    'jumlahRagu'  => $jumlahRagu,
    'belumJawab'  => $belumJawab,
    'navBtns'     => $navBtns,
]);
