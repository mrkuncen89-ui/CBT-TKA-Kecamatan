<?php
// ============================================================
// core/helper.php  — Global helper functions
// ============================================================

/* ── Session timeout (30 menit) ─────────────────────────────── */
define('SESSION_TIMEOUT', 1800); // 30 menit

function checkSessionTimeout(): void {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) return;
    $lastActivity = $_SESSION['last_activity'] ?? time();
    if ((time() - $lastActivity) > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/* ── Sanitize input ─────────────────────────────────────────── */
function sanitize(string $data): string {
    global $conn;
    return $conn->real_escape_string(strip_tags(trim($data)));
}

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeInt(mixed $val): int {
    return (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}

/* ── Redirect ───────────────────────────────────────────────── */
function redirect(string $url): never {
    header("Location: $url");
    exit;
}

/* ── Format tanggal Indonesia ──────────────────────────────── */
function formatTanggal(string $tanggal): string {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $t = explode('-', $tanggal);
    if (count($t) < 3) return $tanggal;
    return "{$t[2]} {$bulan[(int)$t[1]]} {$t[0]}";
}

function formatTanggalPendek(string $tanggal): string {
    return date('d/m/Y', strtotime($tanggal));
}

/* ── Nilai helpers ──────────────────────────────────────────── */
function hitungNilai(int $benar, int $total): float {
    if ($total === 0) return 0;
    return round(($benar / $total) * 100, 2);
}

function getStatusNilai(int $nilai): array {
    if ($nilai >= 90) return ['label' => 'Istimewa',    'badge' => 'success'];
    if ($nilai >= 80) return ['label' => 'Sangat Baik', 'badge' => 'success'];
    if ($nilai >= 70) return ['label' => 'Baik',        'badge' => 'info'];
    if ($nilai >= 60) return ['label' => 'Cukup',       'badge' => 'warning'];
    return                   ['label' => 'Kurang',      'badge' => 'danger'];
}

function getPredikat(int $nilai, int $kkm = 0): array {
    if ($kkm <= 0) {
        // Auto-ambil dari DB jika tidak disuplai
        global $conn;
        $kkm = isset($conn) ? (int)getSetting($conn, 'kkm', '60') : 60;
    }
    if ($nilai >= 90) return ['A', 'Istimewa',    'success', '#0e9f6e'];
    if ($nilai >= 80) return ['B', 'Sangat Baik', 'success', '#16a34a'];
    if ($nilai >= 70) return ['C', 'Baik',        'info',    '#0ea5e9'];
    if ($nilai >= $kkm) return ['D', 'Cukup',     'warning', '#f59e0b'];
    return                   ['E', 'Kurang',      'danger',  '#ef4444'];
}

/* ── Upload gambar soal ─────────────────────────────────────── */
function uploadGambarSoal(array $file, string $dir): array {
    if ($file['error'] !== UPLOAD_ERR_OK)
        return ['ok' => false, 'msg' => 'Upload gagal (kode: '.$file['error'].')'];

    // Validasi MIME type nyata (bukan hanya ekstensi)
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime))
        return ['ok' => false, 'msg' => 'Format gambar tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.'];

    // Validasi ekstensi konsisten dengan MIME
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeToExt = ['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/gif'=>['gif'],'image/webp'=>['webp']];
    if (!in_array($ext, $mimeToExt[$mime] ?? []))
        return ['ok' => false, 'msg' => 'Ekstensi file tidak sesuai dengan isi file.'];

    if ($file['size'] > 3 * 1024 * 1024)
        return ['ok' => false, 'msg' => 'Ukuran gambar maksimal 3MB.'];

    $nama = 'soal_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $nama))
        return ['ok' => false, 'msg' => 'Gagal menyimpan file gambar.'];
    return ['ok' => true, 'nama' => $nama];
}

/**
 * Validasi upload file gambar profil/logo.
 * Memeriksa MIME type nyata, ekstensi, dan ukuran.
 *
 * @param array  $file      Entry dari $_FILES
 * @param int    $maxBytes  Ukuran maksimal (default 2MB)
 * @return array ['ok'=>bool, 'ext'=>string, 'msg'=>string]
 */
function validasiUploadGambar(array $file, int $maxBytes = 2097152): array {
    if ($file['error'] !== UPLOAD_ERR_OK)
        return ['ok' => false, 'msg' => 'Upload gagal (kode: '.$file['error'].')'];

    $allowedMime = ['image/jpeg' => ['jpg','jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime]))
        return ['ok' => false, 'msg' => 'Format file tidak didukung. Gunakan JPG, PNG, atau WEBP.'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedMime[$mime]))
        return ['ok' => false, 'msg' => 'Ekstensi file tidak sesuai dengan isi file.'];

    if ($file['size'] > $maxBytes)
        return ['ok' => false, 'msg' => 'Ukuran file terlalu besar. Maksimal ' . round($maxBytes/1048576) . 'MB.'];

    return ['ok' => true, 'ext' => $ext];
}

/* ── Generate kode peserta unik ─────────────────────────────── */
function generateKodePeserta(mysqli $db): string {
    do {
        $kode = 'TKA' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $cek  = $db->query("SELECT id FROM peserta WHERE kode_peserta='$kode' LIMIT 1");
    } while ($cek && $cek->num_rows > 0);
    return $kode;
}

/* ── JSON response ──────────────────────────────────────────── */
function jsonResponse(array $data): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Escape untuk Excel XML ─────────────────────────────────── */
function xlEsc(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/* ── Durasi ujian human-readable ─────────────────────────────── */
function formatDurasi(string $mulai, string $selesai): string {
    $det   = max(0, strtotime($selesai) - strtotime($mulai));
    $menit = floor($det / 60);
    $sisa  = $det % 60;
    return $menit > 0 ? "{$menit} mnt {$sisa} dtk" : "{$sisa} dtk";
}

/* ── Build WHERE clause helper ──────────────────────────────── */
function buildWhere(array $conditions): string {
    $conditions = array_filter($conditions);
    return $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
}

/* ── Log Aktivitas ──────────────────────────────────────────── */
function logActivity(mysqli $db, string $aktivitas, string $detail = ''): void {
    $userId   = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'guest';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip       = $db->real_escape_string($ip);
    $aktivitas = $db->real_escape_string($aktivitas);
    $detail   = $db->real_escape_string($detail);
    $username = $db->real_escape_string($username);
    if ($userId) {
        $db->query("INSERT INTO log_aktivitas (user_id, username, aktivitas, detail, ip_address)
                    VALUES ($userId, '$username', '$aktivitas', '$detail', '$ip')");
    } else {
        $db->query("INSERT INTO log_aktivitas (user_id, username, aktivitas, detail, ip_address)
                    VALUES (NULL, '$username', '$aktivitas', '$detail', '$ip')");
    }
}

/* ── Pengaturan Sistem ──────────────────────────────────────── */
function getSetting(mysqli $db, string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $k   = $db->real_escape_string($key);
        $res = $db->query("SELECT setting_val FROM pengaturan WHERE setting_key='$k' LIMIT 1");
        $cache[$key] = ($res && $row = $res->fetch_assoc()) ? ($row['setting_val'] ?? $default) : $default;
    }
    return $cache[$key];
}

function setSetting(mysqli $db, string $key, string $value): void {
    $k = $db->real_escape_string($key);
    $v = $db->real_escape_string($value);
    $db->query("INSERT INTO pengaturan (setting_key, setting_val)
                VALUES ('$k','$v')
                ON DUPLICATE KEY UPDATE setting_val='$v'");
}

/* ── Update last activity ujian (anti-kick) ─────────────────── */
function updateUjianActivity(mysqli $db, int $ujianId): void {
    // Throttle: hanya UPDATE ke DB setiap 30 detik per sesi
    // Mencegah ratusan UPDATE bersamaan saat banyak siswa aktif
    $key = 'act_' . $ujianId;
    if (isset($_SESSION[$key]) && (time() - $_SESSION[$key]) < 30) return;
    $db->query("UPDATE ujian SET last_activity=NOW() WHERE id=$ujianId");
    $_SESSION[$key] = time();
}

/* ── CSRF Protection ─────────────────────────────────────────── */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function csrfVerify(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;color:#c00"><h2>403 — Permintaan tidak valid.</h2><p><a href="javascript:history.back()">Kembali</a></p></div>');
    }
}

/* ── Security Headers ────────────────────────────────────────── */
function sendSecurityHeaders(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/* ── Rate Limiting Login (berbasis DB per IP) ────────────────── */
/**
 * Cek apakah IP ini masih boleh mencoba login.
 * Menyimpan hitungan di tabel rate_limit (dibuat otomatis jika belum ada).
 * Fallback ke session jika tabel belum ada (misal sebelum migrasi).
 *
 * @return bool  true = boleh lanjut, false = terkunci
 */
function cekRateLimit(string $key, int $maxPercobaan = 5, int $jendela = 300): bool {
    global $conn;
    $now = time();

    // Pastikan tabel ada (buat sekali jika belum)
    $conn->query("CREATE TABLE IF NOT EXISTS `rate_limit` (
        `rl_key`       VARCHAR(200) NOT NULL,
        `attempts`     INT          NOT NULL DEFAULT 0,
        `first_attempt` INT         NOT NULL DEFAULT 0,
        `locked_until` INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (`rl_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $k   = $conn->real_escape_string($key);
    $res = $conn->query("SELECT * FROM rate_limit WHERE rl_key='$k' LIMIT 1");
    $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    // Jika terkunci
    if ($row && $row['locked_until'] > $now) return false;

    // Reset jika jendela waktu sudah lewat
    if (!$row || ($now - $row['first_attempt']) > $jendela) {
        $conn->query("INSERT INTO rate_limit (rl_key, attempts, first_attempt, locked_until)
                      VALUES ('$k', 1, $now, 0)
                      ON DUPLICATE KEY UPDATE attempts=1, first_attempt=$now, locked_until=0");
        return true;
    }

    $attempts = (int)$row['attempts'] + 1;
    if ($attempts > $maxPercobaan) {
        $lockUntil = $now + $jendela;
        $conn->query("UPDATE rate_limit SET attempts=$attempts, locked_until=$lockUntil WHERE rl_key='$k'");
        return false;
    }

    $conn->query("UPDATE rate_limit SET attempts=$attempts WHERE rl_key='$k'");
    return true;
}

function resetRateLimit(string $key): void {
    global $conn;
    $k = $conn->real_escape_string($key);
    $conn->query("DELETE FROM rate_limit WHERE rl_key='$k'");
}

function sisaWaktuKunci(string $key): int {
    global $conn;
    $k   = $conn->real_escape_string($key);
    $res = $conn->query("SELECT locked_until FROM rate_limit WHERE rl_key='$k' LIMIT 1");
    if (!$res || $res->num_rows === 0) return 0;
    $row = $res->fetch_assoc();
    return max(0, (int)$row['locked_until'] - time());
}

// ── Jenjang & Kelas ──────────────────────────────────────────

/**
 * Daftar kelas per jenjang pendidikan.
 * Nilai kelas disimpan sebagai string agar fleksibel (1, VII, X, dll).
 */
function getKelasByJenjang(string $jenjang): array {
    return match(strtoupper($jenjang)) {
        'SD'  => ['I','II','III','IV','V','VI'],
        'MI'  => ['I','II','III','IV','V','VI'],
        'SMP' => ['VII','VIII','IX'],
        'MTS' => ['VII','VIII','IX'],
        'SMA' => ['X','XI','XII'],
        'MA'  => ['X','XI','XII'],
        'SMK' => ['X','XI','XII'],
        default => ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'],
    };
}

/**
 * Daftar jenjang yang tersedia.
 */
function getJenjangOptions(): array {
    return [
        'SD'  => 'SD (Sekolah Dasar)',
        'MI'  => 'MI (Madrasah Ibtidaiyah)',
        'SMP' => 'SMP (Sekolah Menengah Pertama)',
        'MTS' => 'MTs (Madrasah Tsanawiyah)',
        'SMA' => 'SMA (Sekolah Menengah Atas)',
        'MA'  => 'MA (Madrasah Aliyah)',
        'SMK' => 'SMK (Sekolah Menengah Kejuruan)',
    ];
}

/**
 * Render dropdown kelas berdasarkan jenjang sekolah.
 * Ambil jenjang dari DB jika $jenjang kosong.
 */
function renderKelasOptions(string $selected = '', string $jenjang = ''): string {
    $kelasList = $jenjang ? getKelasByJenjang($jenjang) : array_merge(
        ['I','II','III','IV','V','VI'],
        ['VII','VIII','IX'],
        ['X','XI','XII']
    );
    $html = '<option value="">-- Pilih Kelas --</option>';
    foreach ($kelasList as $k) {
        $sel   = $selected === $k ? ' selected' : '';
        $html .= "<option value=\"$k\"$sel>Kelas $k</option>";
    }
    return $html;
}

