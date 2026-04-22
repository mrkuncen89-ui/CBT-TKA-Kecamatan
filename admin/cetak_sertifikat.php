<?php
// ============================================================
// admin/cetak_sertifikat.php — Template Sertifikat
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$ujianId = (int)($_GET['id'] ?? 0);

$sql = "SELECT u.nilai, u.waktu_selesai, 
               p.nama, p.kode_peserta, s.nama_sekolah, k.nama_kategori
        FROM ujian u
        JOIN peserta p ON p.id = u.peserta_id
        JOIN sekolah s ON s.id = p.sekolah_id
        JOIN kategori_soal k ON k.id = u.kategori_id
        WHERE u.id = $ujianId LIMIT 1";

$res = $conn->query($sql);
$data = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

if (!$data) die("Data tidak ditemukan.");

[$ph, $pt, $pb] = getPredikat((int)$data['nilai']);
$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat - <?= e($data['nama']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Great+Vibes&family=Montserrat:wght@400;600;700&display=swap');
        
        body {
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            font-family: 'Montserrat', sans-serif;
        }
        
        .certificate-container {
            width: 297mm;
            height: 210mm;
            padding: 20mm;
            margin: 10mm auto;
            background: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
            border: 15px solid #1a365d;
            overflow: hidden;
        }
        
        .certificate-container::before {
            content: '';
            position: absolute;
            top: 5px; left: 5px; right: 5px; bottom: 5px;
            border: 2px solid #c5a059;
            pointer-events: none;
        }

        .inner-border {
            position: absolute;
            top: 20px; left: 20px; right: 20px; bottom: 20px;
            border: 1px solid #c5a059;
            pointer-events: none;
        }

        .content {
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .header {
            margin-top: 10mm;
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            font-size: 48px;
            color: #1a365d;
            margin: 0;
            letter-spacing: 5px;
        }

        .header h2 {
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #c5a059;
            margin-top: 5px;
        }

        .sub-header {
            margin-top: 10mm;
            font-size: 16px;
            color: #4a5568;
        }

        .student-name {
            font-family: 'Great Vibes', cursive;
            font-size: 64px;
            color: #1a365d;
            margin: 10mm 0;
            border-bottom: 2px solid #c5a059;
            display: inline-block;
            padding: 0 40px;
        }

        .details {
            font-size: 18px;
            line-height: 1.6;
            color: #2d3748;
            max-width: 80%;
            margin: 0 auto;
        }

        .details strong {
            color: #1a365d;
        }

        .score-box {
            margin-top: 10mm;
            display: inline-block;
            padding: 15px 30px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .score-box .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 5px;
        }

        .score-box .value {
            font-size: 32px;
            font-weight: 800;
            color: #1a365d;
        }

        .footer {
            margin-top: 20mm;
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
        }

        .signature {
            width: 60mm;
            text-align: center;
        }

        .signature .line {
            border-top: 1px solid #2d3748;
            margin-bottom: 5px;
        }

        .signature .name {
            font-weight: 700;
            font-size: 14px;
        }

        .signature .title {
            font-size: 12px;
            color: #718096;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 150px;
            opacity: 0.03;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }

        @media print {
            body { background: none; }
            .certificate-container { margin: 0; box-shadow: none; }
            .no-print { display: none; }
        }

        .no-print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1a365d;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            z-index: 100;
        }
    </style>
</head>
<body>

<button class="no-print-btn no-print" onclick="window.print()">
    🖨️ Cetak Sertifikat
</button>

<div class="certificate-container">
    <div class="inner-border"></div>
    <div class="watermark">TKA KECAMATAN</div>
    
    <div class="content">
        <div class="header">
            <h1>SERTIFIKAT</h1>
            <h2>PENGHARGAAN</h2>
        </div>
        
        <div class="sub-header">
            Diberikan kepada:
        </div>
        
        <div class="student-name">
            <?= e($data['nama']) ?>
        </div>
        
        <div class="details">
            Atas keberhasilannya dalam mengikuti <strong>Ujian <?= e($data['nama_kategori']) ?></strong><br>
            yang diselenggarakan oleh <strong><?= e($namaAplikasi) ?></strong><br>
            pada tanggal <?= date('d F Y', strtotime($data['waktu_selesai'])) ?>.
        </div>
        
        <div class="score-box">
            <div class="label">Nilai Akhir</div>
            <div class="value"><?= $data['nilai'] ?></div>
            <div style="font-size:14px; color:#c5a059; font-weight:700; margin-top:5px;"><?= $ph ?></div>
        </div>
        
        <div class="footer">
            <div class="signature">
                <div style="height: 20mm;"></div>
                <div class="line"></div>
                <div class="name">Kepala Sekolah</div>
                <div class="title"><?= e($data['nama_sekolah']) ?></div>
            </div>
            
            <div style="text-align: center;">
                <div style="width: 30mm; height: 30mm; border: 2px solid #c5a059; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #c5a059; font-weight: 700;">
                    STEMPEL<br>RESMI
                </div>
            </div>

            <div class="signature">
                <div style="height: 20mm;"></div>
                <div class="line"></div>
                <div class="name">Panitia Pelaksana</div>
                <div class="title">Kecamatan TKA</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
