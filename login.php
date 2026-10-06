<?php
require_once 'auth.php';

// Jika sudah login, redirect sesuai role
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'User') {
        header("Location: customer_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error   = '';
$success = '';
$reviewers = [];
$rating_average = 0;

if (isset($_SESSION['login_flash'])) {
    $flash = $_SESSION['login_flash'];
    unset($_SESSION['login_flash']);
    $error = $flash['error'] ?? '';
    $success = $flash['success'] ?? '';
}

// Ulasan untuk Landing Page
$review_query = mysqli_query($koneksi, 'SELECT nama_reviewer, rating, komentar, created_at FROM rating_laundry ORDER BY created_at DESC LIMIT 4');
if ($review_query) {
    while ($review = mysqli_fetch_assoc($review_query)) {
        $reviewers[] = $review;
    }
}
$rating_summary = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT COALESCE(AVG(rating), 0) AS average_rating FROM rating_laundry'));
$rating_average = round((float) ($rating_summary['average_rating'] ?? 0), 1);

// Handling Form Submission Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // 1. Cek ke tabel users (Admin & Staff)
        $stmt_users = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username = ? AND status = 'Aktif' LIMIT 1");
        $d_user = false;
        if ($stmt_users) {
            mysqli_stmt_bind_param($stmt_users, 's', $username);
            mysqli_stmt_execute($stmt_users);
            $d_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_users));
            mysqli_stmt_close($stmt_users);
        }

        if ($d_user && verify_login_password($password, $d_user['password'])) {
            $_SESSION['user_id'] = $d_user['id_user'];
            $_SESSION['nama']    = $d_user['nama'];
            $_SESSION['role']    = $d_user['role']; // 'Admin' atau 'Staff'
            header("Location: dashboard.php");
            exit;
        }

        // 2. Cek ke tabel pelanggan (User / Member)
        $stmt_pelanggan = mysqli_prepare($koneksi, "SELECT * FROM pelanggan WHERE username = ? AND password IS NOT NULL LIMIT 1");
        $d_pelanggan = false;
        if ($stmt_pelanggan) {
            mysqli_stmt_bind_param($stmt_pelanggan, 's', $username);
            mysqli_stmt_execute($stmt_pelanggan);
            $d_pelanggan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pelanggan));
            mysqli_stmt_close($stmt_pelanggan);
        }

        if ($d_pelanggan && verify_login_password($password, $d_pelanggan['password'])) {
            $_SESSION['user_id'] = $d_pelanggan['id_pelanggan'];
            $_SESSION['nama']    = $d_pelanggan['nama'];
            $_SESSION['role']    = 'User';
            header("Location: customer_dashboard.php");
            exit;
        }

        $_SESSION['login_flash'] = ['error' => 'Username atau Password salah / Akun nonaktif!'];
        header('Location: login.php#beranda');
        exit;
    }
}

function verify_login_password(string $password, string $stored_password): bool
{
    if (password_verify($password, $stored_password)) {
        return true;
    }
    return !password_get_info($stored_password)['algo'] && hash_equals($stored_password, $password);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAVF Laundry - Kelola Laundry Lebih Mudah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Trebuchet MS', sans-serif; }
        .hero-grid { background-image: linear-gradient(rgba(30, 64, 175, .08) 1px, transparent 1px), linear-gradient(90deg, rgba(30, 64, 175, .08) 1px, transparent 1px); background-size: 34px 34px; }
        .modal-backdrop { background: rgba(15, 23, 42, .72); backdrop-filter: blur(5px); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">
    <header class="absolute inset-x-0 top-0 z-20 border-b border-indigo-800/60 bg-indigo-950/95 shadow-lg shadow-indigo-950/20">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 lg:px-8">
            <a href="#beranda" class="flex items-center gap-3 text-white">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-400 text-xl text-slate-950 shadow-lg shadow-cyan-950/20"><i class="fa-solid fa-shirt"></i></span>
                <span class="text-xl font-black tracking-[.18em]">RAVF</span>
            </a>
            <div class="hidden items-center gap-8 text-sm font-semibold text-slate-200 md:flex">
                <a href="#beranda" class="transition hover:text-cyan-300">Beranda</a>
                <a href="#layanan" class="transition hover:text-cyan-300">Layanan</a>
                <a href="#rating" class="transition hover:text-cyan-300">Rating</a>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="openAuth('login')" class="rounded-lg px-4 py-2 text-sm font-bold text-white bg-indigo-800 hover:bg-indigo-700 transition"><i class="fa-solid fa-right-to-bracket mr-1.5"></i>Masuk Sistem</button>
                <a href="register.php" class="rounded-lg bg-cyan-400 px-4 py-2 text-sm font-bold text-slate-950 shadow-lg shadow-cyan-950/20 transition hover:bg-cyan-300"><i class="fa-solid fa-user-plus mr-1.5"></i>Daftar Member</a>
            </div>
        </nav>
    </header>

    <main>
        <section id="beranda" class="hero-grid overflow-hidden bg-[#edf6ff] px-5 pb-20 pt-32 text-slate-950 lg:px-8 lg:pb-28 lg:pt-40">
            <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.05fr_.95fr]">
                <div>
                    <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-300/30 bg-cyan-300/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-cyan-600"><span class="h-2 w-2 rounded-full bg-cyan-500"></span>Layanan Laundry Terpercaya & Antar-Jemput</p>
                    <h1 class="max-w-2xl text-4xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">Laundry Rapi, Poin Melimpah.</h1>
                    <p class="mt-6 max-w-xl text-base leading-7 text-slate-600">Pesan laundry online dari rumah dengan layanan Antar-Jemput atau Drop-Point. Kumpulkan 10 poin untuk Dapatkan Gratis 1 Kg!</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <button type="button" onclick="openAuth('login')" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-blue-900"><i class="fa-solid fa-arrow-right-to-bracket mr-2"></i>Masuk / Booking Sekarang</button>
                        <a href="#layanan" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-800 transition hover:border-blue-600 hover:text-blue-700">Lihat Tarif Paket</a>
                    </div>
                </div>
                <div class="relative">
                    <div class="relative rounded-2xl border border-white/10 bg-white p-6 text-slate-800 shadow-2xl space-y-4">
                        <h3 class="text-lg font-black border-b pb-3">💡 Keuntungan Member RAVF Laundry</h3>
                        <div class="flex items-start gap-3">
                            <span class="text-cyan-600 font-bold text-lg">💰</span>
                            <p class="text-xs text-slate-600 font-semibold"><strong class="text-slate-900">Dapatkan Poin:</strong> Setiap transaksi kelipatan Rp 5.000 mendapatkan +1 Poin Member.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-emerald-600 font-bold text-lg">🎁</span>
                            <p class="text-xs text-slate-600 font-semibold"><strong class="text-slate-900">Free 1 Kg Cucian:</strong> Setiap per 10 Poin, Anda berhak klaim diskon Gratis 1 Kg saat checkout.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-indigo-600 font-bold text-lg">🚗</span>
                            <p class="text-xs text-slate-600 font-semibold"><strong class="text-slate-900">Antar-Jemput Kurir:</strong> Bebas pilih jadwal pickup tanpa ribet keluar rumah.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="layanan" class="bg-white px-5 py-20 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <div class="max-w-xl"><p class="text-xs font-black uppercase tracking-[.2em] text-cyan-600">Tarif Transparan</p><h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Pilihan Layanan Laundry Kami</h2></div>
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="font-black text-lg text-slate-900">Cuci Basah</h3>
                        <p class="text-2xl font-black text-cyan-600 mt-2">Rp 4.000 <span class="text-xs text-slate-500 font-normal">/ Kg</span></p>
                        <p class="mt-3 text-xs text-slate-500">Cuci bersih basah tanpa pengeringan & setrika.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="font-black text-lg text-slate-900">Cuci Kering</h3>
                        <p class="text-2xl font-black text-cyan-600 mt-2">Rp 5.000 <span class="text-xs text-slate-500 font-normal">/ Kg</span></p>
                        <p class="mt-3 text-xs text-slate-500">Cuci bersih dan pengeringan mesin higienis.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="font-black text-lg text-slate-900">Cuci Kering Lipat</h3>
                        <p class="text-2xl font-black text-cyan-600 mt-2">Rp 7.000 <span class="text-xs text-slate-500 font-normal">/ Kg</span></p>
                        <p class="mt-3 text-xs text-slate-500">Cuci bersih, kering, dan dilipat rapi.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="font-black text-lg text-slate-900">Cuci Setrika</h3>
                        <p class="text-2xl font-black text-cyan-600 mt-2">Rp 10.000 <span class="text-xs text-slate-500 font-normal">/ Kg</span></p>
                        <p class="mt-3 text-xs text-slate-500">Paket komplit cuci bersih, kering, dan disetrika rapi.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- REVIEW RATINGS -->
        <section id="rating" class="bg-cyan-50 px-5 py-16 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div><p class="text-xs font-black uppercase tracking-[.2em] text-cyan-700">Ulasan Aktual</p><h2 class="mt-2 text-3xl font-black text-slate-900">Apa Kata Pelanggan Kami?</h2></div>
                    <div class="text-xl font-black text-amber-500">⭐ <?= number_format($rating_average, 1); ?> / 5.0</div>
                </div>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <?php if (!$reviewers): ?>
                        <p class="col-span-full rounded-xl bg-white p-6 text-sm text-slate-500">Belum ada ulasan. Jadilah yang pertama memberi rating!</p>
                    <?php else: ?>
                        <?php foreach ($reviewers as $review): ?>
                            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="text-amber-500">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <i class="fa-solid fa-star<?= $star <= (int) $review['rating'] ? '' : ' text-slate-200'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-slate-600">&ldquo;<?= e($review['komentar']); ?>&rdquo;</p>
                                <p class="mt-4 text-xs font-black text-slate-900"><?= e($review['nama_reviewer']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-slate-950 px-5 py-7 text-center text-xs text-slate-400">&copy; <?= date('Y'); ?> SISTEM LAUNDRY RAVF. Smart Laundry System.</footer>

    <!-- MODAL AUTH LOGIN -->
    <div id="auth-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <button type="button" onclick="closeAuth()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-xl"></i></button>
            <div class="mb-6 Pr-8">
                <p class="text-xs font-black uppercase tracking-[.18em] text-cyan-600">RAVF Laundry System</p>
                <h2 class="mt-2 text-2xl font-black text-slate-900">Selamat Datang Kembali</h2>
                <p class="mt-1 text-xs text-slate-500">Masuk sebagai Staf Internal atau Login Member Pelanggan.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="mb-4 rounded-lg bg-red-50 p-3 text-xs font-bold text-red-700"><?= e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Username</label>
                    <input type="text" name="username" id="username_input" value="" required placeholder="masukan username" autocomplete="off" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm outline-none focus:border-cyan-500 placeholder:text-slate-400 placeholder:font-normal">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Password</label>
                    <input type="password" name="password" id="password_input" value="" required placeholder="masukan password" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm outline-none focus:border-cyan-500 placeholder:text-slate-400 placeholder:font-normal">
                </div>
                <button type="submit" class="w-full rounded-xl bg-slate-900 py-3 text-sm font-black text-white hover:bg-cyan-600 transition">Masuk ke Sistem</button>
            </form>
            <p class="mt-5 text-center text-xs text-slate-500">Pelanggan baru? <a href="register.php" class="font-bold text-cyan-600 hover:underline">Daftar Akun Member</a></p>
        </div>
    </div>

    <script>
    const modal = document.getElementById('auth-modal');
    function openAuth() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function closeAuth() { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    <?php if ($error): ?>openAuth();<?php endif; ?>
    </script>
</body>
</html>