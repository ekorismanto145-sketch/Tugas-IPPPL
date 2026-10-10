<?php
require_once 'koneksi.php';

// 1. Bersihkan semua session yang tersangkut
session_unset();
session_destroy();
session_start();

// 2. Buat hash baru yang pasti valid untuk password 'admin123'
$password_plain = 'admin123';
$hash_baru      = password_hash($password_plain, PASSWORD_DEFAULT);

// 3. Update atau Insert user admin ke tabel users
$username = 'admin';
$cek = mysqli_query($koneksi, "SELECT * FROM users WHERE username='$username'");

if ($cek && mysqli_num_rows($cek) > 0) {
    mysqli_query($koneksi, "UPDATE users SET password='$hash_baru', role='Admin', status='Aktif' WHERE username='$username'");
    $msg = "Password admin berhasil di-reset!";
} else {
    mysqli_query($koneksi, "INSERT INTO users (username, password, nama, role, status) VALUES ('$username', '$hash_baru', 'Administrator Utama', 'Admin', 'Aktif')");
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