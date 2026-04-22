<?php
// ============================================================
// admin/panduan.php — Panduan Penggunaan Sistem
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$pageTitle  = 'Panduan Pengguna';
$activeMenu = 'panduan';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-book-half me-2 text-primary"></i>Panduan Pengguna</h2>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &nbsp;&raquo;&nbsp; Panduan
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
            <div class="card-body p-0">
                <div class="list-group list-group-flush rounded-3">
                    <a href="#persiapan" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-gear-wide-connected me-2"></i> 1. Persiapan Awal
                    </a>
                    <a href="#banksoal" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-journal-check me-2"></i> 2. Mengelola Bank Soal
                    </a>
                    <a href="#pelaksanaan" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-play-circle me-2"></i> 3. Pelaksanaan Ujian
                    </a>
                    <a href="#laporan" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-file-earmark-bar-graph me-2"></i> 4. Laporan & Hasil
                    </a>
                    <a href="#troubleshoot" class="list-group-item list-group-item-action py-3">
                        <i class="bi bi-exclamation-triangle me-2"></i> 5. Troubleshooting
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Persiapan -->
        <div id="persiapan" class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4 text-primary">1. Persiapan Awal</h4>
                <div class="mb-4">
                    <h6 class="fw-bold">A. Kelola Sekolah</h6>
                    <p class="text-muted small">Daftarkan sekolah-sekolah yang akan mengikuti ujian di menu <strong>Master Data > Kelola Sekolah</strong>. Setiap sekolah akan mendapatkan akun login sendiri.</p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">B. Kelola Peserta</h6>
                    <p class="text-muted small">Data peserta bisa diinput satu per satu oleh Admin Kecamatan atau diimport secara massal oleh operator sekolah masing-masing.</p>
                </div>
            </div>
        </div>

        <!-- Bank Soal -->
        <div id="banksoal" class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4 text-primary">2. Mengelola Bank Soal</h4>
                <div class="mb-4">
                    <h6 class="fw-bold">A. Kategori Soal</h6>
                    <p class="text-muted small">Buat kategori ujian (misal: Matematika, Bahasa Indonesia) sebelum menginput soal.</p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">B. Import Soal Excel</h6>
                    <p class="text-muted small">Gunakan template Excel yang disediakan untuk mengunggah soal dalam jumlah banyak sekaligus. Pastikan format kolom sesuai dengan template agar tidak terjadi error.</p>
                </div>
            </div>
        </div>

        <!-- Pelaksanaan -->
        <div id="pelaksanaan" class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4 text-primary">3. Pelaksanaan Ujian</h4>
                <div class="mb-4">
                    <h6 class="fw-bold">A. Jadwal Ujian</h6>
                    <p class="text-muted small">Tentukan tanggal dan jam pelaksanaan ujian. Ujian hanya bisa diakses oleh peserta pada waktu yang telah ditentukan.</p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">B. Token Ujian</h6>
                    <p class="text-muted small">Generate token ujian setiap hari pelaksanaan. Token ini harus dibagikan kepada peserta untuk bisa masuk ke ruang ujian.</p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">C. Monitoring Realtime</h6>
                    <p class="text-muted small">Gunakan menu <strong>Monitoring Ujian</strong> untuk melihat siapa saja yang sedang mengerjakan, siapa yang sudah selesai, dan mendeteksi jika ada peserta yang melakukan pelanggaran (keluar dari layar penuh).</p>
                </div>
            </div>
        </div>

        <!-- Laporan -->
        <div id="laporan" class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4 text-primary">4. Laporan & Hasil</h4>
                <div class="mb-4">
                    <h6 class="fw-bold">A. Export Excel & PDF</h6>
                    <p class="text-muted small">Hasil ujian dapat diexport ke Excel (multi-sheet) atau PDF untuk keperluan arsip fisik.</p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">B. Cetak Sertifikat</h6>
                    <p class="text-muted small">Anda dapat mencetak sertifikat penghargaan bagi peserta yang telah menyelesaikan ujian langsung dari sistem.</p>
                </div>
            </div>
        </div>

        <!-- Troubleshoot -->
        <div id="troubleshoot" class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4 text-danger">5. Troubleshooting</h4>
                <div class="mb-4">
                    <h6 class="fw-bold">Peserta Terkeluar/Logout Sendiri?</h6>
                    <p class="text-muted small">Pastikan koneksi internet stabil. Jika peserta terkeluar, mereka bisa login kembali menggunakan nomor peserta dan token yang sama, jawaban sebelumnya akan otomatis tersimpan.</p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">Token Tidak Valid?</h6>
                    <p class="text-muted small">Pastikan tanggal pada server dan komputer peserta sudah sinkron. Token hanya berlaku pada tanggal yang ditentukan saat generate.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
