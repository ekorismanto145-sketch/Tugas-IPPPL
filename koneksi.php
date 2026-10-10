<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "laundry_ecosmart";

// Coba koneksi ke server MySQL
$koneksi = @mysqli_connect($host, $user, $pass);
if (!$koneksi) {
    $koneksi = @mysqli_connect("127.0.0.1", $user, $pass);
}

if (!$koneksi) {
    die("<div style='font-family:sans-serif;padding:30px;background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;border-radius:12px;margin:40px auto;max-w:600px;'>
        <h3 style='margin:0 0 10px 0;'>⚠️ Koneksi Database Gagal</h3>
        <p style='margin:0;'>Tidak dapat terhubung ke MySQL Server (Laragon/XAMPP). Pastikan service MySQL sudah <strong>START</strong>.</p>
        <small style='display:block;margin-top:10px;color:#be123c;'>" . htmlspecialchars(mysqli_connect_error() ?: 'Connection Refused') . "</small>
    </div>");
}

// Auto-create database jika belum ada
@mysqli_query($koneksi, "CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
@mysqli_select_db($koneksi, $db);

// Auto-seed tabel dari database.sql jika database masih kosong
$chk_tb = @mysqli_query($koneksi, "SHOW TABLES LIKE 'users'");
if ($chk_tb && mysqli_num_rows($chk_tb) == 0) {
    $sql_file = __DIR__ . '/database.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        @mysqli_multi_query($koneksi, $sql_content);
        while (@mysqli_next_result($koneksi)) {;} // clear extra result sets
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>