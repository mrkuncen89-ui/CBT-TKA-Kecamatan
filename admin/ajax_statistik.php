<?php
// ============================================================
// admin/ajax_statistik.php — Statistik Realtime (AJAX)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';

if (!isLoggedIn()) {
    http_response_code(401);
    jsonResponse(['error' => 'Unauthorized']);
}

function qs($conn, $sql, $col) {
    $r = $conn->query($sql);
    if (!$r) return null;
    $row = $r->fetch_assoc(); $r->free();
    return $row[$col] ?? null;
}

$pesertaUjian   = (int) qs($conn, "SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL", 'c');
$pesertaSelesai = (int) qs($conn, "SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()", 'c');
$pesertaOnline  = (int) qs($conn, "SELECT COUNT(*) AS c FROM ujian WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)", 'c');
$totalPeserta   = (int) qs($conn, "SELECT COUNT(*) AS c FROM peserta", 'c');
$nilaiRata      = (float)(qs($conn, "SELECT ROUND(AVG(nilai),1) AS r FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()", 'r') ?? 0);

// Peserta yang baru selesai (5 menit terakhir)
$baruSelesai = [];
$res = $conn->query(
    "SELECT p.nama, s.nama_sekolah, u.nilai
     FROM ujian u
     JOIN peserta p ON p.id=u.peserta_id
     LEFT JOIN sekolah s ON s.id=p.sekolah_id
     WHERE u.waktu_selesai >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY u.waktu_selesai DESC LIMIT 5"
);
if ($res) while ($r = $res->fetch_assoc()) {
    $baruSelesai[] = [
        'nama'     => $r['nama'],
        'sekolah'  => $r['nama_sekolah'] ?? '-',
        'nilai'    => $r['nilai'],
    ];
}

jsonResponse([
    'peserta_online'  => $pesertaOnline,
    'peserta_ujian'   => $pesertaUjian,
    'peserta_selesai' => $pesertaSelesai,
    'total_peserta'   => $totalPeserta,
    'nilai_rata'      => $nilaiRata,
    'baru_selesai'    => $baruSelesai,
    'timestamp'       => date('H:i:s'),
]);
