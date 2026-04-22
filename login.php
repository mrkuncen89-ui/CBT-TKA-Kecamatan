<?php
// ============================================================
// login.php — Halaman Login Admin TKA Kecamatan
// ============================================================
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';

if (isLoggedIn()) {
    redirect(dashboardUrlByRole($_SESSION['role']));
}

$error = '';
$lockSisa = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi CSRF token
    csrfVerify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipKey    = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // Cek rate limit — maks 5 percobaan per 5 menit per IP
    if (!cekRateLimit($ipKey, 5, 300)) {
        $lockSisa = sisaWaktuKunci($ipKey);
        $menit    = ceil($lockSisa / 60);
        $error    = "Terlalu banyak percobaan login. Coba lagi dalam <strong>{$menit} menit</strong>.";
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $result = login($username, $password);
        if ($result['status']) {
            resetRateLimit($ipKey); // reset counter jika berhasil
            logActivity($conn, 'Login', 'Berhasil login sebagai ' . $result['role']);
            redirect(dashboardUrlByRole($result['role']));
        } else {
            $error = $result['message'];
        }
    }
}

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — <?= e($namaAplikasi) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
  background:#f1f5f9;
  min-height:100vh;
  font-family:'Segoe UI',Arial,sans-serif;
  padding:24px 16px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.wrap{max-width:420px;width:100%;margin:0 auto}

/* Card utama */
.main-card{background:#fff;border-radius:16px;padding:28px 24px;box-shadow:0 2px 20px rgba(0,0,0,.09)}

/* Header biru navy (sama dengan login_peserta) */
.card-header-top{background:#1e3a8a;border-radius:12px;padding:20px;text-align:center;margin-bottom:18px}
.judul-app{font-size:20px;font-weight:900;color:#fff;letter-spacing:.5px;line-height:1.2;margin-bottom:4px;text-transform:uppercase}
.judul-penyelenggara{font-size:14px;font-weight:700;color:hsla(0,0%,100%,.85);text-transform:uppercase;letter-spacing:.3px;margin:4px auto 0;line-height:1.6;word-break:normal;white-space:normal;max-width:280px}
.admin-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);color:#eff6ff;font-size:12px;font-weight:700;border-radius:20px;padding:5px 14px;margin-top:8px;border:1px solid rgba(255,255,255,.25)}

/* Alert */
.error-alert{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:9px 12px;font-size:12px;color:#dc2626;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.info-alert{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;padding:9px 12px;font-size:12px;color:#1e3a8a;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.success-alert{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;padding:9px 12px;font-size:12px;color:#15803d;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.warn-alert{background:#fefce8;border:1.5px solid #fde68a;border-radius:8px;padding:9px 12px;font-size:12px;color:#854d0e;margin-bottom:14px;display:flex;align-items:center;gap:8px}

/* Form fields */
.field-label{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:block}
.field-wrap{position:relative;display:flex;align-items:center}
.field-icon{position:absolute;left:12px;color:#94a3b8;font-size:15px;pointer-events:none;z-index:1}
.field-input{width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px 10px 38px;font-size:14px;color:#1e293b;transition:border .15s,box-shadow .15s;outline:none}
.field-input:focus{border-color:#1e3a8a;background:#eff6ff;box-shadow:0 0 0 3px rgba(30,58,138,.1)}
.field-input::placeholder{color:#cbd5e1;font-size:13px;font-weight:400}
.toggle-pw{position:absolute;right:12px;background:none;border:none;padding:0;cursor:pointer;color:#94a3b8;font-size:16px;line-height:1;z-index:1}
.toggle-pw:hover{color:#1e3a8a}
.field-hint{font-size:11px;color:#94a3b8;margin-top:5px}

/* Tombol */
.btn-masuk{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#1e3a8a;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;transition:background .15s;margin-top:4px;box-shadow:0 3px 10px rgba(30,58,138,.3)}
.btn-masuk:hover{background:#1e40af}
.btn-icon{width:20px;height:20px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px}

.divider{display:flex;align-items:center;gap:10px;margin:16px 0;color:#94a3b8;font-size:12px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0}

.btn-peserta{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:11px;font-size:14px;font-weight:700;color:#475569;text-decoration:none;transition:all .15s}
.btn-peserta:hover{border-color:#1e3a8a;color:#1e3a8a;background:#eff6ff}

/* Footer */
.footer-area{text-align:center;margin-top:14px}
.footer-area a{color:#64748b;font-size:12px;text-decoration:none}
.footer-area a:hover{color:#1e3a8a}
.dev-text{font-size:11px;color:#94a3b8;margin-top:6px}
.dev-text strong{color:#475569}
.dev-text a{color:#1e3a8a;text-decoration:none;font-weight:700}
.dev-text a:hover{color:#1e40af}
</style>
</head>
<body>
<div class="wrap">

  <div class="main-card">

    <!-- Header biru navy -->
    <div class="card-header-top">
      <div class="judul-app"><?= e($namaAplikasi) ?></div>
      <?php if ($namaPenyelenggara): ?>
      <div class="judul-penyelenggara"><?= e($namaPenyelenggara) ?></div>
      <?php endif; ?>
      <div class="admin-badge">
        <i class="bi bi-shield-lock-fill"></i> Login Administrator
      </div>
    </div>

    <!-- Alert: timeout sesi -->
    <?php if (isset($_GET['timeout'])): ?>
    <div class="warn-alert">
      <span style="font-size:16px">⏱</span>
      Sesi berakhir karena tidak aktif. Silakan login kembali.
    </div>
    <?php endif; ?>

    <!-- Alert: berhasil logout -->
    <?php if (isset($_GET['logout'])): ?>
    <div class="success-alert">
      <span style="font-size:16px">✅</span>
      Anda berhasil keluar dari sistem.
    </div>
    <?php endif; ?>

    <!-- Alert: role tidak dikenal -->
    <?php if (isset($_GET['error']) && $_GET['error'] === 'role'): ?>
    <div class="warn-alert">
      <span style="font-size:16px">⚠️</span>
      Role akun tidak dikenali. Hubungi administrator.
    </div>
    <?php endif; ?>

    <!-- Alert: error login -->
    <?php if ($error !== ''): ?>
    <div class="error-alert">
      <span style="font-size:16px">⚠️</span><?= e($error) ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" autocomplete="off" novalidate>
      <?= csrfField() ?>

      <div style="margin-bottom:14px">
        <label class="field-label">Username</label>
        <div class="field-wrap">
          <i class="bi bi-person field-icon"></i>
          <input type="text" name="username" class="field-input"
                 placeholder="Masukkan username"
                 value="<?= e($_POST['username'] ?? '') ?>"
                 autocomplete="username" required autofocus>
        </div>
      </div>

      <div style="margin-bottom:16px">
        <label class="field-label">Password</label>
        <div class="field-wrap">
          <i class="bi bi-lock field-icon"></i>
          <input type="password" id="passwordInput" name="password" class="field-input"
                 placeholder="Masukkan password"
                 autocomplete="current-password" required>
          <button type="button" class="toggle-pw" id="togglePassword" title="Tampilkan/sembunyikan">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
        <div class="field-hint">Role terdeteksi otomatis setelah login.</div>
      </div>

      <button type="submit" class="btn-masuk">
        <div class="btn-icon">▶</div>
        Masuk
      </button>

    </form>

    <div class="divider">atau</div>

    <a href="<?= BASE_URL ?>/ujian/login_peserta.php" class="btn-peserta">
      <i class="bi bi-pencil-square"></i> Login Sebagai Peserta Ujian
    </a>

  </div><!-- /main-card -->

  <!-- Footer -->
  <div class="footer-area">
    <div class="dev-text">
      &copy; <?= date('Y') ?> <?= e($namaAplikasi) ?>. Dikembangkan oleh <strong>Cahyana Wijaya</strong> &nbsp;
      <a href="https://www.tiktok.com/@mrkuncen?_r=1&_t=ZS-94kAVOaI36Y" target="_blank">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle">
          <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.78a4.85 4.85 0 0 1-1.01-.09z"/>
        </svg>
        @mrkuncen
      </a>
    </div>
  </div>

</div>
<script>
document.getElementById('togglePassword').addEventListener('click', function(){
    const pw  = document.getElementById('passwordInput');
    const eye = document.getElementById('eyeIcon');
    if(pw.type==='password'){
        pw.type='text';
        eye.className='bi bi-eye-slash';
    } else {
        pw.type='password';
        eye.className='bi bi-eye';
    }
});
</script>
</body>
</html>