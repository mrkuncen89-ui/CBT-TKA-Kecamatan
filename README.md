<div align="center">

<img src="assets/logo.png" width="110" alt="Logo TKA Kecamatan">

# CBT TKA Kecamatan

**Sistem Computer Based Test untuk Try Out Tingkat Kecamatan**

[![Version](https://img.shields.io/badge/version-1.1.0-2563eb?style=flat-square)](https://github.com/mrkuncen89-ui/CBT-TKA-Kecamatan/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-8.3-7c3aed?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-ea580c?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Nginx](https://img.shields.io/badge/Nginx-1.28-16a34a?style=flat-square&logo=nginx&logoColor=white)](https://nginx.org)
[![License](https://img.shields.io/badge/license-Private-dc2626?style=flat-square)](#-lisensi)
[![Platform](https://img.shields.io/badge/platform-Windows-0078d4?style=flat-square&logo=windows&logoColor=white)](https://microsoft.com/windows)

</div>

---

## 📋 Fitur

<table>
<tr>
<td>

**Manajemen Ujian**
- ✅ Manajemen soal & kategori
- ✅ Jadwal ujian per kelas (format Romawi)
- ✅ Acak soal & jawaban otomatis
- ✅ Timer ujian otomatis
- ✅ Pembahasan soal di halaman selesai & cek nilai

</td>
<td>

**Manajemen Peserta**
- ✅ Kelas multi-rombel (VI A, VI B, VII, dst)
- ✅ Import peserta via Excel / CSV
- ✅ Export daftar peserta ke Excel
- ✅ Cetak kartu ujian & sertifikat
- ✅ Kartu ujian otomatis filter jadwal per kelas

</td>
</tr>
<tr>
<td>

**Laporan & Rekap**
- ✅ Hasil & rekap nilai otomatis
- ✅ Export PDF & Excel
- ✅ Dashboard admin & sekolah
- ✅ Log aktivitas user

</td>
<td>

**Sistem & Infrastruktur**
- ✅ Aplikasi Korektor (koreksi jawaban esai/isian)
- ✅ Backup database otomatis
- ✅ Monitoring server
- ✅ Auto-restart server (watchdog)

</td>
</tr>
</table>

---

## 💻 Spesifikasi Sistem

| Komponen | Minimum | Rekomendasi |
|---|---|---|
| OS | Windows 10 64-bit | Windows 11 64-bit |
| RAM | 4 GB | 8 GB |
| Storage | 2 GB | SSD 10 GB |
| Processor | Intel Core i3 / setara | Intel Core i5 gen 10+ |

> **Catatan:** Untuk ujian serentak 500+ peserta, disarankan menggunakan spesifikasi rekomendasi atau lebih tinggi.

---

## 🚀 Instalasi

1. Download installer terbaru di **[Releases →](https://github.com/mrkuncen89-ui/CBT-TKA-Kecamatan/releases/latest)**
2. Jalankan `TKAKecamatan_Setup.exe` sebagai **Administrator**
3. Ikuti langkah instalasi hingga selesai
4. Klik shortcut **TKA Kecamatan** di Desktop
5. Akses via browser: [`http://127.0.0.1:7461/login.php`](http://127.0.0.1:7461/login.php)

---

## 🔧 Konfigurasi Default

| Setting | Nilai |
|---|---|
| Port Nginx | `7461` |
| Port PHP-CGI | `10987` |
| Port MySQL | `3307` |
| Direktori Install | `C:\TKAKecamatan` |

---

## 👤 Akun Default

| Role | Username | Password | Akses |
|---|---|---|---|
| Admin | `admin` | `cahyana` | Dashboard admin penuh |
| Korektor | `korektor` | `@korektor` | Koreksi jawaban peserta |

> ⚠️ **Ganti password** setelah instalasi pertama melalui menu pengaturan.

---

## 📁 Struktur Folder

```
TKAKecamatan/
├── www/
│   ├── admin/
│   │   ├── peserta.php          # Kelola peserta (dropdown kelas multi-rombel)
│   │   ├── jadwal.php           # Jadwal ujian per kelas (format Romawi)
│   │   ├── kartu_ujian.php      # Kartu ujian (filter jadwal per kelas peserta)
│   │   ├── import_peserta.php   # Import peserta via Excel/CSV
│   │   ├── export_peserta.php   # Export daftar peserta ke Excel
│   │   └── ...
│   ├── korektor/
│   │   ├── index.php            # Login & dashboard korektor
│   │   ├── koreksi.php          # Halaman koreksi jawaban peserta
│   │   └── ganti_password.php   # Ganti password korektor
│   ├── ujian/
│   │   ├── selesai.php          # Hasil ujian + pembahasan soal
│   │   ├── cek_nilai.php        # Cek nilai & ranking + pembahasan
│   │   └── ...
│   ├── core/
│   │   └── helper.php           # Fungsi kelas (getKelasByJenjang, renderKelasOptions)
│   ├── assets/
│   │   └── template_import_peserta.xlsx
│   ├── sekolah/                 # Halaman portal sekolah
│   ├── config/                  # Konfigurasi database
│   ├── includes/                # Header, footer, komponen bersama
│   └── index.php
├── backup/                      # Backup database otomatis
├── logs/                        # Log server & watchdog
├── bin/                         # Binary Nginx, PHP, MySQL
├── start_server.bat             # Jalankan server
└── watchdog.bat                 # Auto-restart Nginx + PHP + MySQL
```

---

## 📝 Format Kelas

Sistem mendukung kelas dengan dan tanpa sub-rombel:

| Jenjang | Format Kelas |
|---|---|
| SD / MI | `I` `II` `III` `IV` `V` `VI` `VI A` `VI B` |
| SMP / MTs | `VII` `VIII` `IX` `VII A` `VIII B` `IX C` |
| SMA / MA / SMK | `X` `XI` `XII` `X IPA` `XI IPS` `XII IPA 1` |

Format kelas disimpan sebagai teks bebas. Sekolah satu rombel cukup pilih `VI`; sekolah multi-rombel pilih `VI A`, `VI B`, dst.

---

## 📥 Format Import Peserta

Gunakan template yang tersedia di `assets/template_import_peserta.xlsx` atau buat file Excel/CSV dengan format:

| Kolom A | Kolom B |
|---|---|
| `nama` | `kelas` |
| Andi Pratama | VI A |
| Budi Santoso | VI B |
| Citra Dewi | VIII |

> Kode peserta dibuat otomatis oleh sistem setelah import.

---

## 🔄 Changelog

### v1.1.0 — 16 Mei 2026
- **Tambah:** Aplikasi Korektor (`korektor/`) untuk koreksi jawaban esai/isian peserta
- **Tambah:** Akun korektor dengan akses terbatas
- **Tambah:** Halaman ganti password untuk korektor

### v1.0.7 — 2 Mei 2026
- **Tambah:** Dukungan kelas multi-rombel (VI A, VI B, dst) untuk semua jenjang
- **Tambah:** Filter jadwal ujian per kelas di kartu ujian
- **Tambah:** Pembahasan soal di halaman `cek_nilai.php`
- **Tambah:** Export daftar peserta ke Excel (`export_peserta.php`)
- **Tambah:** Auto-restart server via `watchdog.bat`
- **Perbaiki:** Checkbox kelas di jadwal ujian dari angka Arab ke Romawi
- **Perbaiki:** Template import peserta (Excel & CSV) diperbarui ke format baru
- **Perbaiki:** Kartu ujian hanya tampilkan jadwal yang sesuai kelas peserta

### v1.0.0
- Rilis awal

---

## 👨‍💻 Developer

<div align="center">

**Cahyana Wijaya**

[![TikTok](https://img.shields.io/badge/@mrkuncen-TikTok-000000?style=flat-square&logo=tiktok&logoColor=white)](https://www.tiktok.com/@mrkuncen)

</div>

---

## 📄 Lisensi

**Private** — Hak cipta dilindungi.  
Dilarang mendistribusikan ulang, menjual, atau memodifikasi tanpa izin tertulis dari pengembang.
