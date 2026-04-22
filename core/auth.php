<?php
// ============================================================
// core/auth.php
// Autentikasi pengguna — TKA Kecamatan
//
// Skema tabel users:
//   id, username, password, role, sekolah_id
//
// Role yang dikenal:
//   admin_kecamatan → /admin/dashboard.php
//   sekolah         → /sekolah/dashboard.php
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

/**
 * Proses login.
 * Role TIDAK dikirim dari form — dibaca langsung dari database.
 *
 * @return array ['status'=>bool, 'role'=>string|null, 'message'=>string|null]
 */
function login(string $username, string $password): array {
    global $conn;

    // Gunakan prepared statement agar aman dari SQL injection
    $stmt = $conn->prepare(
        "SELECT id, username, nama_lengkap, foto_profil, password, role, sekolah_id
         FROM users
         WHERE username = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return ['status' => false, 'message' => 'Kesalahan sistem. Coba lagi.'];
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        return ['status' => false, 'message' => 'Username atau password salah.'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verifikasi password dengan password_verify()
    if (!password_verify($password, $user['password'])) {
        return ['status' => false, 'message' => 'Username atau password salah.'];
    }

    // Tulis session
    session_regenerate_id(true);                    // cegah session fixation
    $_SESSION['user_id']     = (int) $user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['nama']        = $user['nama_lengkap'] ?: $user['username'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['sekolah_id']  = $user['sekolah_id'] ? (int)$user['sekolah_id'] : null;
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;
    $_SESSION['logged_in']   = true;

    return ['status' => true, 'role' => $user['role']];
}

/**
 * Mengembalikan URL redirect berdasarkan role.
 */
function dashboardUrlByRole(string $role): string {
    return match ($role) {
        'admin_kecamatan' => BASE_URL . '/admin/dashboard.php',
        'sekolah'         => BASE_URL . '/sekolah/dashboard.php',
        default           => BASE_URL . '/login.php?error=role',
    };
}

/** Cek apakah user sudah login. */
function isLoggedIn(): bool {
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Wajib login; redirect ke login jika belum.
 * Opsional: paksa role tertentu (string atau array).
 *
 * @param string|array|null $role
 */
function requireLogin($role = null): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    if ($role !== null) {
        $roles = (array) $role;
        if (!in_array($_SESSION['role'], $roles, true)) {
            // User login tapi role salah → kirim ke dashboardnya sendiri
            header('Location: ' . dashboardUrlByRole($_SESSION['role']));
            exit;
        }
    }
}

/** Shortcut: izinkan admin_kecamatan ATAU sekolah. */
function requireAnyStaff(): void {
    requireLogin(['admin_kecamatan', 'sekolah']);
}

/** Mengembalikan data user saat ini dari session, atau null. */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'          => $_SESSION['user_id'],
        'username'    => $_SESSION['username'],
        'nama'        => $_SESSION['nama'],
        'role'        => $_SESSION['role'],
        'sekolah_id'  => $_SESSION['sekolah_id'] ?? null,
        'foto_profil' => $_SESSION['foto_profil'] ?? null,
    ];
}

/** Hapus session dan redirect ke halaman login. */
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?logout=1');
    exit;
}
