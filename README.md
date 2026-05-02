# CBT TKA Kecamatan

<div align="center">
  <img src="assets/logo.png" width="120" alt="Logo TKA Kecamatan">
  
  <h3>Sistem Computer Based Test (CBT) TKA</h3>
  <p>Try out berbasis komputer untuk tingkat kecamatan</p>

  ![Version](https://img.shields.io/badge/version-1.1.0-blue)
  ![PHP](https://img.shields.io/badge/PHP-8.3-purple)
  ![MySQL](https://img.shields.io/badge/MySQL-8.4-orange)
  ![Nginx](https://img.shields.io/badge/Nginx-1.28-green)
  ![License](https://img.shields.io/badge/license-Private-red)
</div>

---

## 📋 Fitur

- ✅ Manajemen soal & kategori ujian
- ✅ Manajemen peserta & sekolah
- ✅ **Kelas multi-rombel** (VI A, VI B, VII, dst — SD/MI/SMP/MTs/SMA/MA/SMK)
- ✅ Ujian berbasis komputer (CBT)
- ✅ Timer ujian otomatis
- ✅ Acak soal & jawaban
- ✅ **Jadwal ujian per kelas** (kelas_diizinkan — format Romawi)
- ✅ Hasil & rekap nilai otomatis
- ✅ **Pembahasan soal** di halaman selesai & cek nilai
- ✅ Export PDF & Excel
- ✅ **Export daftar peserta ke Excel** (dengan kode peserta)
- ✅ Cetak kartu ujian & sertifikat
- ✅ **Kartu ujian otomatis filter jadwal per kelas**
- ✅ Import peserta via Excel / CSV
- ✅ Dashboard admin & sekolah
- ✅ Backup database otomatis
- ✅ Log aktivitas user
- ✅ Monitoring server
- ✅ **Auto-restart server** (watchdog)

---

## 💻 Spesifikasi Sistem

| Komponen | Minimum |
|----------|---------|
| OS | Windows 10 64-bit |
| RAM | 4 GB |
| Storage | 2 GB |
| Processor | Intel Core i3 / setara |

---

## 🚀 Instalasi

1. Download installer terbaru di [Releases](https://github.com/mrkuncen89-ui/CBT-TKA-Kecamatan/releases/latest)
2. Jalankan `TKAKecamatan_Setup.exe` sebagai **Administrator**
3. Ikuti langkah instalasi
4. Klik shortcut **TKA Kecamatan** di Desktop
5. Akses via browser: `http://127.0.0.1:7461/login.php`

---

## 🔧 Konfigurasi

| Setting | Nilai |
|---------|-------|
| Port Nginx | 7461 |
| Port PHP | 10987 |
| Port MySQL | 3307 |
| Install Dir | `C:\TKAKecamatan` |

---

## 📁 Struktur Folder

```
TKAKecamatan/
├── admin/
│   ├── peserta.php         # Kelola peserta (dropdown kelas multi-rombel)
│   ├── jadwal.php          # Jadwal ujian per kelas (format Romawi)
│   ├── kartu_ujian.php     # Kartu ujian (filter jadwal per kelas peserta)
│   ├── import_peserta.php  # Import peserta via Excel/CSV
│   ├── export_peserta.php  # Export daftar peserta ke Excel
│   └── ...
├── ujian/
│   ├── selesai.php         # Hasil ujian + pembahasan soal
│   ├── cek_nilai.php       # Cek nilai & ranking + pembahasan
│   └── ...
├── core/
│   └── helper.php          # Fungsi kelas (getKelasByJenjang, renderKelasOptions)
├── assets/
│   └── template_import_peserta.xlsx  # Template import peserta
├── sekolah/        # Halaman sekolah
├── config/         # Konfigurasi database
├── includes/       # Header, footer, dll
├── backup/         # Backup database
├── logs/           # Log server & watchdog
├── start_server.bat
└── watchdog.bat    # Auto-restart Nginx + PHP + MySQL
```

---

## 📝 Format Kelas

Sistem mendukung kelas dengan dan tanpa sub-rombel:

| Jenjang | Contoh Kelas |
|---------|-------------|
| SD / MI | `I` `II` `III` `IV` `V` `VI` `VI A` `VI B` |
| SMP / MTs | `VII` `VIII` `IX` `VII A` `VIII B` |
| SMA / MA / SMK | `X` `XI` `XII` `X IPA` `XI IPS` |

Format kelas disimpan sebagai teks bebas — sekolah 1 rombel cukup pilih `VI`, sekolah multi-rombel pilih `VI A`, `VI B`, dst.

---

## 📥 Format Import Peserta (Excel/CSV)

| Kolom A | Kolom B |
|---------|---------|
| nama | kelas |
| Andi Pratama | VI A |
| Budi Santoso | VI B |
| Citra Dewi | VIII |

> Kode peserta dibuat otomatis oleh sistem.

---

## 🔄 Changelog

### v1.1.0 (2026-05-01)
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

**Cahyana Wijaya**  
[@mrkuncen](https://www.tiktok.com/@mrkuncen)

---

## 📄 Lisensi

Private — Hak cipta dilindungi. Dilarang mendistribusikan ulang tanpa izin.
