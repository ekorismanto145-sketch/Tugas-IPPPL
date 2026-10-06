<?php
require_once 'auth.php';

$error = '';
$old = ['nama' => '', 'no_hp' => '', 'alamat' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['nama'] = trim($_POST['nama'] ?? '');
    $old['no_hp'] = trim($_POST['no_hp'] ?? '');
    $old['alamat'] = trim($_POST['alamat'] ?? '');
    $old['username'] = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($old['nama'] === '' || $old['username'] === '' || $password === '') {
        $error = 'Nama, username, dan password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // Cek keunikan username
        $check_stmt = mysqli_prepare($koneksi, "SELECT username FROM users WHERE username = ? UNION SELECT username FROM pelanggan WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 'ss', $old['username'], $old['username']);
        mysqli_stmt_execute($check_stmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
            $error = 'Username sudah digunakan, pilih username lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($koneksi, "INSERT INTO pelanggan (nama, username, password, no_hp, alamat) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssss', $old['nama'], $old['username'], $hash, $old['no_hp'], $old['alamat']);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['login_flash'] = ['success' => 'Registrasi Member berhasil. Silakan masuk dengan akun Anda.'];
                header('Location: login.php');
                exit;
            }
            $error = 'Registrasi gagal. Periksa koneksi database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Member Pelanggan - RAVF Laundry</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 flex min-h-screen items-center justify-center p-4">
    <main class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
        <a href="login.php" class="text-xs font-bold text-cyan-700 hover:underline">&larr; Kembali ke Beranda</a>
        <div class="mt-4">
            <p class="text-xs font-black uppercase tracking-widest text-cyan-600">RAVF Laundry</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900">Daftar Akun Member</h1>
            <p class="mt-1 text-xs text-slate-500">Dapatkan keuntungan kumpul poin dan promo gratis cucian!</p>
        </div>

        <?php if ($error): ?>
            <div class="mt-4 rounded-xl bg-red-50 p-3 text-xs font-bold text-red-700"><?= e($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-5 space-y-4">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Nama Lengkap</label>
                <input name="nama" value="<?= e($old['nama']); ?>" required placeholder="Contoh: Budi Santoso" class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-cyan-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600">No. HP / WhatsApp</label>
                <input name="no_hp" value="<?= e($old['no_hp']); ?>" placeholder="08xxxxxxxx" class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-cyan-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Alamat Lengkap</label>
                <textarea name="alamat" rows="2" placeholder="Alamat penjemputan" class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-cyan-500"><?= e($old['alamat']); ?></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Username Login</label>
                <input name="username" value="<?= e($old['username']); ?>" required placeholder="Username Anda" class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-cyan-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Password</label>
                <input type="password" name="password" minlength="6" required placeholder="Minimal 6 karakter" class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-cyan-500">
            </div>
            <button class="w-full rounded-xl bg-cyan-500 py-3 text-sm font-black text-slate-950 transition hover:bg-cyan-400">Daftar Akun Member</button>
        </form>
    </main>
</body>
</html>
