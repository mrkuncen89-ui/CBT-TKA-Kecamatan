tka_kecamatan-- =============================================
-- DATABASE: db_sekolah
-- Website SD Negeri Harapan Bangsa
-- =============================================

CREATE DATABASE IF NOT EXISTS db_sekolah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_sekolah;

-- Tabel Admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Berita
CREATE TABLE IF NOT EXISTS berita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    isi TEXT NOT NULL,
    gambar VARCHAR(255),
    tanggal DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Galeri
CREATE TABLE IF NOT EXISTS galeri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    gambar VARCHAR(255) NOT NULL,
    kategori VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Guru/Tenaga Pendidik
CREATE TABLE IF NOT EXISTS guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    jabatan VARCHAR(100),
    foto VARCHAR(255),
    jenis_kelamin ENUM('Laki-laki','Perempuan') NOT NULL,
    status_kepegawaian ENUM('PNS','Honorer','GTT') DEFAULT 'PNS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Siswa
CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nis VARCHAR(20) UNIQUE,
    kelas VARCHAR(10),
    jenis_kelamin ENUM('Laki-laki','Perempuan') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Profil Sekolah
CREATE TABLE IF NOT EXISTS profil_sekolah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_sekolah VARCHAR(255),
    npsn VARCHAR(20),
    alamat TEXT,
    telepon VARCHAR(20),
    email VARCHAR(100),
    kepala_sekolah VARCHAR(100),
    visi TEXT,
    misi TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- DATA AWAL (Seed)
-- =============================================

-- Admin default (password: admin123)
INSERT INTO admin (username, password, nama) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'AdminSekolah');

-- Profil Sekolah
INSERT INTO profil_sekolah (nama_sekolah, npsn, alamat, telepon, email, kepala_sekolah, visi, misi) VALUES (
    'SD Negeri Harapan Bangsa',
    '20214567',
    'Jl. Pendidikan No. 1, Tasikmalaya, Jawa Barat',
    '(0265) 123456',
    'sdnharapanbangsa@gmail.com',
    'Drs. Ahmad Fauzi, M.Pd.',
    'Membentuk Generasi Unggul dan Berakhlak',
    'Memberikan pendidikan dasar berkualitas dengan penekanan pada karakter, literasi, dan numerasi untuk membentuk siswa yang kompetitif dan berintegritas.'
);

-- Guru
INSERT INTO guru (nama, jabatan, jenis_kelamin, status_kepegawaian) VALUES 
('Drs. Ahmad Fauzi, M.Pd.', 'Kepala Sekolah', 'Laki-laki', 'PNS'),
('Siti Rahayu, S.Pd.', 'Guru Kelas 1', 'Perempuan', 'PNS'),
('Budi Santoso, S.Pd.', 'Guru Kelas 2', 'Laki-laki', 'PNS'),
('Dewi Lestari, S.Pd.', 'Guru Kelas 3', 'Perempuan', 'Honorer'),
('Eko Prasetyo, S.Pd.', 'Guru Kelas 4', 'Laki-laki', 'GTT');

-- Berita
INSERT INTO berita (judul, isi, tanggal) VALUES 
('Penerimaan Siswa Baru Tahun Ajaran 2026/2027', 'SD Negeri Harapan Bangsa membuka penerimaan siswa baru untuk tahun ajaran 2026/2027. Pendaftaran dibuka mulai 1 Februari hingga 30 Juni 2026.', '2026-01-15'),
('Juara 1 Lomba Olimpiade Matematika Tingkat Kota', 'Siswa kelas 5 kami berhasil meraih juara 1 pada lomba olimpiade matematika tingkat kota Tasikmalaya yang berlangsung pada 10 Januari 2026.', '2026-01-10'),
('Kegiatan Pramuka Tingkat Penggalang', 'Pelantikan anggota pramuka penggalang baru berlangsung meriah di lapangan sekolah pada Sabtu, 8 Januari 2026.', '2026-01-08');

-- Siswa
INSERT INTO siswa (nama, nis, kelas, jenis_kelamin) VALUES
('Andi Pratama', '2026001', '6A', 'Laki-laki'),
('Budi Santoso', '2026002', '6A', 'Laki-laki'),
('Citra Dewi', '2026003', '5B', 'Perempuan'),
('Dian Permata', '2026004', '4A', 'Perempuan'),
('Eko Wijaya', '2026005', '3B', 'Laki-laki');
