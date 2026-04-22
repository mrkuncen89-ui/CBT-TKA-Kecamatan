<?php
// ============================================================
// about.php — Halaman Biodata Pengembang
// URL: /about.php (hanya bisa diakses jika sudah login)
// ============================================================
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';

requireLogin(); // wajib login, semua role boleh akses

$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$pageTitle    = 'Tentang Pengembang';
$activeMenu   = 'about';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
:root {
    --bg-color: #fcfcfd;
    --card-bg: #ffffff;
    --primary: #4f46e5;
    --text-dark: #111827;
    --text-muted: #4b5563;
    --border-color: #e5e7eb;
}

body {
    margin: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: var(--bg-color);
    color: var(--text-dark);
    -webkit-font-smoothing: antialiased;
}

.about-container {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.profile-card {
    background: var(--card-bg);
    width: 100%;
    max-width: 440px;
    border-radius: 24px;
    border: 1px solid var(--border-color);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    padding: 2.5rem;
    text-align: center;
}

.avatar-container {
    position: relative;
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
}

.avatar-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-color);
    padding: 4px;
}

.status-indicator {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 14px;
    height: 14px;
    background-color: #10b981;
    border: 3px solid #fff;
    border-radius: 50%;
}

.name-heading {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.25rem;
    color: var(--text-dark);
    letter-spacing: -0.02em;
}

.role-subheading {
    font-size: 0.95rem;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 2rem;
}

.bio-text {
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--text-muted);
    margin-bottom: 2rem;
}

.contact-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.contact-link {
    display: flex;
    align-items: center;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-dark);
    font-size: 0.9rem;
    font-weight: 500;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.contact-link:hover {
    background-color: #f9fafb;
    border-color: var(--primary);
    color: var(--primary);
}

.contact-link i {
    margin-right: 12px;
    font-size: 1.1rem;
}

.skills-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.skill-tag {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    background: #f3f4f6;
    padding: 4px 12px;
    border-radius: 99px;
}

.back-home {
    margin-bottom: 1.5rem;
    text-decoration: none;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
}

.back-home:hover {
    color: var(--primary);
}

.footer-copyright {
    margin-top: 2rem;
    font-size: 0.75rem;
    color: #9ca3af;
}

@media (max-width: 480px) {
    .profile-card {
        padding: 1.5rem;
        border-radius: 16px;
    }
    .name-heading {
        font-size: 1.25rem;
    }
}
</style>
</head>
<body>

<div class="about-container">
    <a href="javascript:history.back()" class="back-home">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>

    <div class="profile-card">
        <div class="avatar-container">
            <img src="<?= BASE_URL ?>/assets/uploads/profil/profil_1_1774016977.png" 
                 class="avatar-image" 
                 alt="Cahyana Wijaya"
                 onerror="this.style.display='none';this.parentElement.innerHTML='<div style=\'width:100%;height:100%;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#9ca3af\'>CW</div>'">
            <div class="status-indicator"></div>
        </div>

        <h1 class="name-heading">Cahyana Wijaya</h1>
        <div class="role-subheading">Fullstack Developer</div>

        <p class="bio-text">
            Berfokus pada pengembangan sistem informasi yang efisien dan solusi digital berbasis web untuk mendukung kemajuan teknologi di sektor pendidikan.
        </p>

        <div class="contact-section">
            <a href="mailto:mrkuncen89@gmail.com" class="contact-link">
                <i class="bi bi-envelope"></i> mrkuncen89@gmail.com
            </a>
            <a href="https://wa.me/6287781743048" target="_blank" class="contact-link">
                <i class="bi bi-whatsapp"></i> +62 877-8174-3048
            </a>
            <a href="https://www.tiktok.com/@mrkuncen" target="_blank" class="contact-link">
                <i class="bi bi-tiktok"></i> @mrkuncen
            </a>
        </div>

        <div class="skills-tags">
            <span class="skill-tag">PHP 8</span>
            <span class="skill-tag">MySQL</span>
            <span class="skill-tag">Bootstrap</span>
            <span class="skill-tag">CBT System</span>
            <span class="skill-tag">Data Export</span>
        </div>

        <div class="footer-copyright">
            &copy; <?= date('Y') ?> &bull; <?= e($namaAplikasi) ?>
        </div>
    </div>
</div>

</body>
</html>
