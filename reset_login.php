<?php
require_once 'koneksi.php';

// 1. Bersihkan semua session yang tersangkut
session_unset();
session_destroy();
session_start();

// 2. Buat hash baru yang pasti valid untuk password 'admin123'
$password_plain = 'admin123';
$hash_baru      = password_hash($password_plain, PASSWORD_DEFAULT);

// 3. Update atau Insert user admin
$username = 'admin';
$cek = mysqli_query($koneksi, "SELECT * FROM pemilik WHERE username='$username'");

if (mysqli_num_rows($cek) > 0) {
    mysqli_query($koneksi, "UPDATE pemilik SET password='$hash_baru', role='Pemilik' WHERE username='$username'");
    $msg = "Password admin berhasil di-reset!";
} else {
    mysqli_query($koneksi, "INSERT INTO pemilik (username, password, nama, role) VALUES ('$username', '$hash_baru', 'Pemilik RAVF', 'Pemilik')");
    $msg = "Akun admin baru berhasil dibuat!";
}
?>
<!DOCTYPE html>
<html>
<head><title>Reset Akses - RAVF</title></head>
<body style="font-family: sans-serif; padding: 40px; text-align: center;">
    <h2 style="color: green;"><?= $msg; ?></h2>
    <p>Session lama telah dibersihkan sepenuhnya.</p>
    <p><b>Username:</b> admin<br><b>Password:</b> admin123</p>
    <br>
    <a href="login.php" style="background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Kembali ke Halaman Login
    </a>
</body>
</html>