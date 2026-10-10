<?php
require_once 'koneksi.php';

$username_baru = 'admin';
$password_baru = 'admin123';
$hash_baru     = password_hash($password_baru, PASSWORD_DEFAULT);

// Update hash password di database tabel users
$update = mysqli_query($koneksi, "UPDATE users SET password='$hash_baru' WHERE username='$username_baru'");

if ($update && mysqli_affected_rows($koneksi) > 0) {
    echo "<h3 style='color:green;'>Password Admin Berhasil Diperbarui!</h3>";
    echo "<b>Username:</b> $username_baru<br>";
    echo "<b>Password:</b> $password_baru<br>";
    echo "<b>Hash Baru:</b> $hash_baru<br><br>";
    echo "<a href='login.php'>Klik di sini untuk Login</a>";
} else {
    // Jika user admin belum ada sama sekali, buatkan baru
    $insert = mysqli_query($koneksi, "INSERT INTO users (username, password, nama, role) VALUES ('$username_baru', '$hash_baru', 'Administrator Utama', 'Admin')");
    if ($insert) {
        echo "<h3 style='color:green;'>Akun Admin Berhasil Dibuat!</h3>";
        echo "<a href='login.php'>Klik di sini untuk Login</a>";
    } else {
        echo "<h3 style='color:red;'>Gagal mengupdate database: " . mysqli_error($koneksi) . "</h3>";
    }
}
?>