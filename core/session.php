<?php
// ============================================================
// core/session.php
// Inisialisasi dan utilitas sesi — TKA Kecamatan
// ============================================================

// Kirim security headers di setiap request
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_SID');
    session_set_cookie_params([
        'lifetime' => 0,          // tutup browser = session habis
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // auto-detect HTTPS
        'httponly' => true,       // tidak bisa diakses JavaScript
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Check session timeout (auto-logout setelah 30 menit idle)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    if (!defined('BASE_URL')) require_once __DIR__ . '/../config/database.php';
    $lastAct = $_SESSION['last_activity'] ?? time();
    if ((time() - $lastAct) > 1800) {
        session_unset(); session_destroy();
        // Hanya redirect jika bukan AJAX
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Location: ' . BASE_URL . '/login.php?timeout=1');
            exit;
        }
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// ── Flash message ────────────────────────────────────────────

/** Simpan flash message ke session. $type: 'success' | 'error' | 'info' | 'warning' */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Ambil flash message (sekali pakai) dan hapus dari session. */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message sebagai Bootstrap alert HTML.
 * Panggil di dalam <body> halaman.
 */
function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';

    $map = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];
    $bsType = $map[$flash['type']] ?? 'secondary';

    return "<div class='alert alert-{$bsType} alert-dismissible fade show' role='alert'>"
         . $flash['message']
         . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>"
         . "</div>";
}
