<?php
// ============================================================
// ujian/ajax_ragu.php — Toggle status ragu-ragu soal
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['peserta_id'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$soalId = (int)($_POST['soal_id'] ?? 0);
if (!$soalId) {
    echo json_encode(['ok' => false]);
    exit;
}

if (!isset($_SESSION['ragu'])) $_SESSION['ragu'] = [];

$idx = array_search($soalId, $_SESSION['ragu']);
if ($idx !== false) {
    // Hapus dari ragu
    array_splice($_SESSION['ragu'], $idx, 1);
    echo json_encode(['ok' => true, 'ragu' => false]);
} else {
    // Tambah ke ragu
    $_SESSION['ragu'][] = $soalId;
    echo json_encode(['ok' => true, 'ragu' => true]);
}
