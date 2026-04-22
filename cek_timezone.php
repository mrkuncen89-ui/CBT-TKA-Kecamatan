<?php
echo "<h3>Cek Timezone Server</h3>";
echo "Timezone PHP: <b>" . date_default_timezone_get() . "</b><br>";
echo "Waktu PHP sekarang: <b>" . date('Y-m-d H:i:s') . "</b><br>";
echo "Waktu MySQL sekarang: ";

// Ganti sesuai config database kamu
$conn = new mysqli('localhost', 'root', '', 'tka_kecamatan');
if ($conn->connect_error) {
    echo "<b style='color:red'>Gagal koneksi: " . $conn->connect_error . "</b>";
} else {
    $r = $conn->query("SELECT NOW() as waktu, @@global.time_zone as tz_global, @@session.time_zone as tz_session");
    $d = $r->fetch_assoc();
    echo "<b>" . $d['waktu'] . "</b><br>";
    echo "Timezone MySQL Global: <b>" . $d['tz_global'] . "</b><br>";
    echo "Timezone MySQL Session: <b>" . $d['tz_session'] . "</b><br>";
}
?>
