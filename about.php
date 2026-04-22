<?php
// ============================================================
// about.php — Halaman Biodata Pengembang (Dynamic Version)
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

// ── Ambil data pengembang dari settings ──────────────────────
$dev = [
    'nama'   => getSetting($conn, 'dev_nama',   'Cahyana Wijaya'),
    'role'   => getSetting($conn, 'dev_role',   'Fullstack Developer'),
    'bio'    => getSetting($conn, 'dev_bio',    'Berfokus pada pengembangan sistem informasi yang efisien dan solusi digital berbasis web untuk mendukung kemajuan teknologi di sektor pendidikan.'),
    'email'  => getSetting($conn, 'dev_email',  'mrkuncen89@gmail.com'),
    'wa'     => getSetting($conn, 'dev_wa',     '6287781743048'),
    'tiktok' => getSetting($conn, 'dev_tiktok', '@mrkuncen'),
    'foto'   => getSetting($conn, 'dev_foto',   ''),
    'skills' => getSetting($conn, 'dev_skills', 'PHP 8,MySQL,Bootstrap,CBT System,Data Export'),
];

// Inisial nama untuk fallback avatar
$inisial = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', trim($dev['nama'])), 0, 2)));

// Skills jadi array
$skillList = array_filter(array_map('trim', explode(',', $dev['skills'])));

// Foto: cek dari settings dulu, fallback ke folder profil lama
$fotoSrc = '';
if (!empty($dev['foto'])) {
    $fotoSrc = BASE_URL . '/assets/uploads/profil/' . $dev['foto'];
} else {
    // cari file profil_1_*.png sebagai fallback otomatis
    $profDir = __DIR__ . '/assets/uploads/profil/';
    if (is_dir($profDir)) {
        $files = glob($profDir . 'profil_1_*.png');
        if ($files) {
            $fotoSrc = BASE_URL . '/assets/uploads/profil/' . basename($files[0]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
:root {
    --bg:      #f5f5f0;
    --card-bg: #ffffff;
    --primary: #4f46e5;
    --primary-light: #eef2ff;
    --text:    #111827;
    --muted:   #6b7280;
    --border:  #e5e7eb;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    -webkit-font-smoothing: antialiased;
}

/* ── Back link ── */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    transition: color .2s;
    align-self: flex-start;
    max-width: 440px;
    width: 100%;
}
.back-link:hover { color: var(--primary); }

/* ── Card ── */
.profile-card {
    background: var(--card-bg);
    width: 100%;
    max-width: 440px;
    border-radius: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
    overflow: hidden;
    animation: slideUp .4s ease both;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Header banner ── */
.card-banner {
    height: 80px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    position: relative;
}

/* ── Avatar ── */
.avatar-wrap {
    position: absolute;
    bottom: -44px;
    left: 50%;
    transform: translateX(-50%);
}

.avatar {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    border: 4px solid #fff;
    object-fit: cover;
    display: block;
    background: #e5e7eb;
}

.avatar-fallback {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    border: 4px solid #fff;
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--primary);
}

.online-dot {
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 14px;
    height: 14px;
    background: #10b981;
    border: 3px solid #fff;
    border-radius: 50%;
}

/* ── Body ── */
.card-body {
    padding: 3.5rem 2rem 2rem;
    text-align: center;
}

.dev-name {
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: -.03em;
    margin-bottom: 4px;
}

.dev-role {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--primary);
    background: var(--primary-light);
    display: inline-block;
    padding: 3px 12px;
    border-radius: 99px;
    margin-bottom: 1.25rem;
}

.dev-bio {
    font-size: 0.875rem;
    line-height: 1.65;
    color: var(--muted);
    margin-bottom: 1.75rem;
}

/* ── Contact links ── */
.contact-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 1.75rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: .8rem 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    font-size: .875rem;
    font-weight: 500;
    transition: all .2s;
}

.contact-item:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
    transform: translateX(4px);
}

.contact-item i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.contact-item .contact-value {
    flex: 1;
    text-align: left;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Skills ── */
.skills-wrap {
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.skills-label {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: .75rem;
}

.skills-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
}

.skill-tag {
    font-size: .75rem;
    font-weight: 600;
    color: var(--muted);
    background: #f3f4f6;
    padding: 4px 12px;
    border-radius: 99px;
    border: 1px solid var(--border);
    transition: all .2s;
}

.skill-tag:hover {
    background: var(--primary-light);
    color: var(--primary);
    border-color: var(--primary);
}

/* ── Footer ── */
.card-footer-custom {
    text-align: center;
    padding: 1rem 2rem;
    border-top: 1px solid var(--border);
    font-size: .75rem;
    color: #9ca3af;
    background: #fafafa;
}

@media (max-width: 480px) {
    .card-body { padding: 3.5rem 1.25rem 1.5rem; }
}
</style>
</head>
<body>

<a href="javascript:history.back()" class="back-link">
    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
</a>

<div class="profile-card">

    <!-- Banner + Avatar -->
    <div class="card-banner">
        <div class="avatar-wrap">
            <?php if ($fotoSrc): ?>
                <img src="<?= e($fotoSrc) ?>"
                     class="avatar"
                     alt="<?= e($dev['nama']) ?>"
                     onerror="this.replaceWith(document.getElementById('av-fallback'));document.getElementById('av-fallback').style.display='flex';">
                <div id="av-fallback" class="avatar-fallback" style="display:none">
                    <?= e($inisial) ?>
                </div>
            <?php else: ?>
                <div class="avatar-fallback"><?= e($inisial) ?></div>
            <?php endif; ?>
            <div class="online-dot"></div>
        </div>
    </div>

    <!-- Body -->
    <div class="card-body">
        <div class="dev-name"><?= e($dev['nama']) ?></div>
        <div class="dev-role"><?= e($dev['role']) ?></div>
        <p class="dev-bio"><?= e($dev['bio']) ?></p>

        <div class="contact-list">
            <?php if ($dev['email']): ?>
            <a href="mailto:<?= e($dev['email']) ?>" class="contact-item">
                <i class="bi bi-envelope-fill"></i>
                <span class="contact-value"><?= e($dev['email']) ?></span>
                <i class="bi bi-arrow-up-right text-muted" style="font-size:.75rem"></i>
            </a>
            <?php endif; ?>

            <?php if ($dev['wa']): ?>
            <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $dev['wa'])) ?>" target="_blank" rel="noopener" class="contact-item">
                <i class="bi bi-whatsapp" style="color:#25d366"></i>
                <span class="contact-value">+<?= e($dev['wa']) ?></span>
                <i class="bi bi-arrow-up-right text-muted" style="font-size:.75rem"></i>
            </a>
            <?php endif; ?>

            <?php if ($dev['tiktok']): ?>
            <a href="https://www.tiktok.com/<?= e(ltrim($dev['tiktok'], '@')) ?>" target="_blank" rel="noopener" class="contact-item">
                <i class="bi bi-tiktok"></i>
                <span class="contact-value"><?= e($dev['tiktok']) ?></span>
                <i class="bi bi-arrow-up-right text-muted" style="font-size:.75rem"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Skills -->
        <?php if ($skillList): ?>
        <div class="skills-wrap">
            <div class="skills-label">Tech Stack</div>
            <div class="skills-tags">
                <?php foreach ($skillList as $skill): ?>
                    <span class="skill-tag"><?= e($skill) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="card-footer-custom">
        &copy; <?= date('Y') ?> &bull; <?= e($namaAplikasi) ?>
    </div>

</div>

</body>
</html>
