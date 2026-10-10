<?php
require_once 'auth.php';

function app_start(string $title, string $active = ''): void
{
    global $koneksi;
    require_login();
    
    // Keamanan URL Guard (Mengizinkan Pelanggan mengakses dashboard, booking baru, riwayat cucian, & profil)
    if (is_user() && !in_array($active, ['customer', 'new', 'history', 'profile'], true)) {
        header('Location: customer_dashboard.php');
        exit;
    }

    $role = current_role();
    $home = is_user() ? 'customer_dashboard.php' : 'dashboard.php';
    $display_name = $_SESSION['nama'] ?? 'Pengguna';
    
    $profile_photo = '';
    $user_id = (int) ($_SESSION['user_id'] ?? 0);

    if (is_user()) {
        // Safe check foto profil dari tabel pelanggan
        $res = @mysqli_query($koneksi, "SELECT foto_profil FROM pelanggan WHERE id_pelanggan = {$user_id} LIMIT 1");
        if ($res && $data = mysqli_fetch_assoc($res)) {
            $profile_photo = $data['foto_profil'] ?? '';
        }
    } else {
        // Safe check foto profil dari tabel users
        $res = @mysqli_query($koneksi, "SELECT foto_profil FROM users WHERE id_user = {$user_id} LIMIT 1");
        if ($res && $data = mysqli_fetch_assoc($res)) {
            $profile_photo = $data['foto_profil'] ?? '';
        }
    }
    
    $show_back = !in_array($active, ['dashboard', 'customer'], true);
    ?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?> - RAVF Laundry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet.js CSS & JS untuk Pin Location Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        [data-theme="dark"] body { background: #0f172a; color: #f1f5f9; }
        [data-theme="dark"] .surface { background: #1e293b; color: #f1f5f9; border-color: #334155; }
        [data-theme="dark"] .muted { color: #94a3b8; }
        [data-theme="dark"] input, [data-theme="dark"] select, [data-theme="dark"] textarea { background: #0f172a !important; color: #f1f5f9 !important; border-color: #475569 !important; }
        [data-theme="dark"] input::placeholder, [data-theme="dark"] textarea::placeholder { color: #64748b; }
        [data-theme="dark"] .bg-white { background: #1e293b !important; color: #f1f5f9; }
        [data-theme="dark"] .bg-slate-50 { background: #1e293b !important; border-color: #334155 !important; }
        [data-theme="dark"] .bg-slate-100 { background: #0f172a !important; }
        [data-theme="dark"] .bg-slate-200 { background: #334155 !important; color: #f1f5f9 !important; }
        [data-theme="dark"] .bg-emerald-100 { background: #064e3b !important; color: #a7f3d0 !important; }
        [data-theme="dark"] .bg-emerald-50 { background: #064e3b !important; color: #a7f3d0 !important; }
        [data-theme="dark"] .bg-amber-50 { background: #451a03 !important; color: #fde68a !important; }
        [data-theme="dark"] .bg-cyan-50 { background: #083344 !important; color: #cffaff !important; }
        [data-theme="dark"] .bg-red-50 { background: #450a0a !important; color: #fecaca !important; }
        [data-theme="dark"] .bg-indigo-100 { background: #312e81 !important; color: #c7d2fe !important; }
        [data-theme="dark"] .text-slate-900, [data-theme="dark"] .text-slate-800, [data-theme="dark"] .text-slate-700 { color: #f1f5f9 !important; }
        [data-theme="dark"] .text-slate-600, [data-theme="dark"] .text-slate-500 { color: #cbd5e1 !important; }
        [data-theme="dark"] .text-cyan-700, [data-theme="dark"] .text-cyan-800 { color: #38bdf8 !important; }
        [data-theme="dark"] .text-indigo-700, [data-theme="dark"] .text-indigo-800 { color: #a5b4fc !important; }
        [data-theme="dark"] .border-slate-100, [data-theme="dark"] .border-slate-200, [data-theme="dark"] .border-slate-300 { border-color: #334155 !important; }
        [data-theme="dark"] th { background: #0f172a !important; color: #cbd5e1 !important; border-color: #334155 !important; }
        [data-theme="dark"] td { border-color: #334155 !important; }
        [data-theme="dark"] .leaflet-popup-content-wrapper, [data-theme="dark"] .leaflet-popup-tip { background: #1e293b !important; color: #f1f5f9 !important; }
        .sidebar { transform: translateX(-105%); transition: transform .25s ease; }
        .sidebar.open { transform: translateX(0); }
        .sidebar-overlay { opacity: 0; pointer-events: none; transition: opacity .25s ease; }
        .sidebar-overlay.open { opacity: 1; pointer-events: auto; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 transition-colors">
<header class="sticky top-0 z-30 bg-indigo-950 text-white shadow-lg">
    <div class="flex min-h-16 items-center justify-between gap-3 px-4 lg:px-8">
        <div class="flex items-center gap-3">
            <button id="sidebar-toggle" type="button" class="rounded-lg border border-indigo-700 px-3 py-2 text-lg hover:bg-indigo-800" aria-label="Buka menu">☰</button>
            <a href="<?= $home; ?>" class="text-xl font-black tracking-widest text-cyan-300">RAVF</a>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <?php if ($profile_photo): ?>
                <img src="<?= e($profile_photo); ?>" alt="Foto profil" class="h-9 w-9 rounded-full object-cover ring-2 ring-cyan-300">
            <?php else: ?>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-cyan-400 font-black text-slate-950"><?= e(strtoupper(substr($display_name, 0, 1))); ?></span>
            <?php endif; ?>
            <span class="hidden text-indigo-200 sm:inline"><?= e($display_name); ?> · <strong class="text-cyan-300"><?= e($role); ?></strong></span>
            <button id="theme-toggle" type="button" class="rounded-lg border border-indigo-700 px-3 py-2" aria-label="Ganti mode warna">☾</button>
        </div>
    </div>
</header>
<div id="sidebar-overlay" class="sidebar-overlay fixed inset-0 z-40 bg-slate-950/50"></div>
<aside id="app-sidebar" class="sidebar fixed inset-y-0 left-0 z-50 w-72 bg-indigo-950 p-5 text-white shadow-2xl">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[.2em] text-cyan-300">RAVF Laundry</p>
                <p class="mt-1 text-sm text-indigo-200"><?= e($display_name); ?></p>
            </div>
        </div>
        <button id="sidebar-close" type="button" class="rounded-lg px-3 py-2 text-xl text-indigo-200 hover:bg-indigo-800" aria-label="Tutup menu">×</button>
    </div>
    <div class="mt-6 rounded-xl bg-indigo-900 p-4">
        <p class="text-xs text-indigo-300">Login sebagai</p>
        <p class="mt-1 font-black text-white"><?= e($role); ?></p>
        <a href="profile.php" class="mt-3 inline-flex text-sm font-bold text-cyan-300 hover:text-cyan-200">Kelola profil &rarr;</a>
    </div>
    
    <!-- NAVIGATION MENU ROLE BASED -->
    <nav class="mt-6 space-y-2 text-sm font-semibold">
        <?php if (is_user()): ?>
            <a class="block rounded-lg px-3 py-3 <?= $active === 'customer' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="customer_dashboard.php">Dashboard Cucian Saya</a>
            <a class="block rounded-lg px-3 py-3 <?= $active === 'new' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="transaksi_baru.php">➕ Booking Layanan</a>
            <a class="block rounded-lg px-3 py-3 <?= $active === 'history' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="history_transaksi.php">Riwayat Cucian</a>
        <?php else: ?>
            <a class="block rounded-lg px-3 py-3 <?= $active === 'dashboard' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="dashboard.php">Dashboard Operasional</a>
            <a class="block rounded-lg px-3 py-3 <?= $active === 'new' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="transaksi_baru.php">Kasir POS & Booking</a>
            <a class="block rounded-lg px-3 py-3 <?= $active === 'history' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="history_transaksi.php">History Transaksi</a>
            
            <?php if (is_admin()): ?>
                <div class="my-4 border-t border-indigo-900 pt-3 text-xs uppercase tracking-widest text-cyan-400 font-bold">Menu Admin</div>
                <a class="block rounded-lg px-3 py-3 <?= $active === 'vouchers' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="kelola_voucher.php">🎟️ Voucher & Promo</a>
                <a class="block rounded-lg px-3 py-3 <?= $active === 'report' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="laporan.php">📊 Laporan Keuangan Rekap</a>
                <a class="block rounded-lg px-3 py-3 <?= $active === 'employees' ? 'bg-cyan-500 text-slate-950 font-black' : 'hover:bg-indigo-800'; ?>" href="kelola_karyawan.php">👥 Kelola Staf & Pelanggan</a>
            <?php endif; ?>
        <?php endif; ?>
        
        <a class="mt-6 block rounded-lg bg-rose-600 px-3 py-3 hover:bg-rose-500" href="logout.php">Keluar</a>
    </nav>
</aside>
<main class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
    <?php if ($show_back): ?>
        <div class="mb-5">
            <a href="javascript:history.back()" class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-bold text-indigo-700 shadow-sm transition hover:border-cyan-400 hover:bg-cyan-50 hover:text-cyan-700">Kembali</a>
        </div>
    <?php endif; ?>
<?php
}

function app_end(): void
{
    ?>
</main>
<script>
const root=document.documentElement, sidebar=document.getElementById('app-sidebar'), overlay=document.getElementById('sidebar-overlay');
function closeSidebar(){sidebar.classList.remove('open');overlay.classList.remove('open');document.body.classList.remove('overflow-hidden')}
document.getElementById('sidebar-toggle').addEventListener('click',()=>{sidebar.classList.add('open');overlay.classList.add('open');document.body.classList.add('overflow-hidden')});document.getElementById('sidebar-close').addEventListener('click',closeSidebar);overlay.addEventListener('click',closeSidebar);document.addEventListener('keydown',event=>{if(event.key==='Escape')closeSidebar()});
document.getElementById('theme-toggle').addEventListener('click',()=>{const dark=root.dataset.theme!=='dark';root.dataset.theme=dark?'dark':'light';localStorage.setItem('ravf-theme',root.dataset.theme)});if(localStorage.getItem('ravf-theme')==='dark')root.dataset.theme='dark';
</script>
</body>
</html>
<?php
}
