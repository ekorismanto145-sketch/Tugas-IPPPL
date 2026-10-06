<?php
require_once 'app_layout.php';
require_user(); // Guard khusus Pelanggan / Member

$user_name = $_SESSION['nama'] ?? 'Pelanggan';
$message = '';
$error = '';

// Ambil ID Pelanggan dari akun session
$user_id = (int)($_SESSION['user_id'] ?? 0);
$pelanggan_stmt = mysqli_prepare($koneksi, "SELECT * FROM pelanggan WHERE id_pelanggan = ? LIMIT 1");
mysqli_stmt_bind_param($pelanggan_stmt, 'i', $user_id);
mysqli_stmt_execute($pelanggan_stmt);
$pelanggan_data = mysqli_fetch_assoc(mysqli_stmt_get_result($pelanggan_stmt)) ?: [];
$id_pelanggan = (int) ($pelanggan_data['id_pelanggan'] ?? 0);
$poin_bonus = (int) ($pelanggan_data['poin_bonus'] ?? 0);

// Handling Form Ulasan & Rating Pelanggan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int) ($_POST['rating'] ?? 0);
    $komentar = trim($_POST['komentar'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Silakan pilih bintang rating (1 - 5).';
    } elseif ($komentar === '') {
        $error = 'Tuliskan ulasan atau ulasan Anda.';
    } else {
        $stmt_rating = mysqli_prepare($koneksi, "INSERT INTO rating_laundry (id_pelanggan, nama_reviewer, rating, komentar) VALUES (?, ?, ?, ?)");
        $pelanggan_val = $id_pelanggan > 0 ? $id_pelanggan : null;
        mysqli_stmt_bind_param($stmt_rating, 'isis', $pelanggan_val, $user_name, $rating, $komentar);
        if (mysqli_stmt_execute($stmt_rating)) {
            $message = 'Terima kasih! Ulasan dan rating Anda berhasil terkirim dan akan ditampilkan di Halaman Utama.';
        } else {
            $error = 'Gagal mengirimkan ulasan. Silakan coba lagi.';
        }
    }
}

// Stats & Cucian Aktif Pelanggan Ini
$stats = ['menunggu' => 0, 'proses' => 0, 'selesai' => 0, 'diambil' => 0, 'total' => 0];
$transaksi_list = [];

if ($id_pelanggan > 0) {
    $q_stats = mysqli_query($koneksi, "SELECT status_cucian, COUNT(*) AS total FROM transaksi WHERE id_pelanggan = {$id_pelanggan} GROUP BY status_cucian");
    while ($row = mysqli_fetch_assoc($q_stats)) {
        $count = (int)$row['total'];
        $stats['total'] += $count;
        if (in_array($row['status_cucian'], ['Booking Masuk', 'Penjemputan Kurir', 'Menunggu Timbang'])) $stats['menunggu'] += $count;
        if ($row['status_cucian'] === 'Dalam Proses') $stats['proses'] += $count;
        if ($row['status_cucian'] === 'Selesai') $stats['selesai'] += $count;
        if (in_array($row['status_cucian'], ['Siap Diambil/Diantar', 'Sudah Selesai & Diambil'])) $stats['diambil'] += $count;
    }

    $q_trans = mysqli_query($koneksi, "SELECT id_transaksi, kode_transaksi, berat_cucian, total_harga, status_pembayaran, status_cucian, tanggal_transaksi, tanggal_selesai FROM transaksi WHERE id_pelanggan = {$id_pelanggan} ORDER BY tanggal_transaksi DESC LIMIT 5");
    while ($t = mysqli_fetch_assoc($q_trans)) {
        $transaksi_list[] = $t;
    }
}

// Ulasan Terbaru Pelanggan
$u_query = mysqli_query($koneksi, "SELECT nama_reviewer, rating, komentar, created_at FROM rating_laundry ORDER BY created_at DESC LIMIT 4");

app_start('Dashboard Cucian Saya', 'customer');
?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-xs font-black uppercase tracking-widest text-cyan-600">Portal Pelanggan</p>
        <h1 class="mt-1 text-3xl font-black text-slate-900 dark:text-white">Selamat Datang, <?= e($user_name); ?>!</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pantau progres cucian dan kumpulkan poin bonus Anda.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="transaksi_baru.php" class="rounded-2xl bg-cyan-500 px-5 py-3 text-slate-950 font-black shadow-lg hover:bg-cyan-400 transition">
            ➕ Booking Layanan Baru
        </a>
        <a href="history_transaksi.php" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-slate-700 font-bold shadow-sm hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 transition">
            📋 Riwayat Cucian
        </a>
        <div class="rounded-2xl bg-indigo-600 px-5 py-3 text-white font-black shadow-lg">
            ⭐ <?= $poin_bonus; ?> Poin Member
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="mt-5 rounded-xl border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
        ✅ <?= e($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mt-5 rounded-xl border-l-4 border-rose-500 bg-rose-50 p-4 text-sm font-bold text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
        ⚠️ <?= e($error); ?>
    </div>
<?php endif; ?>

<!-- RINGKASAN STATUS CUCIAN -->
<section class="mt-6 grid gap-4 sm:grid-cols-4">
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Menunggu Timbang</p>
        <strong class="mt-2 block text-3xl font-black text-amber-600 dark:text-amber-400"><?= $stats['menunggu']; ?></strong>
    </article>
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Dalam Proses Cuci</p>
        <strong class="mt-2 block text-3xl font-black text-cyan-600 dark:text-cyan-400"><?= $stats['proses']; ?></strong>
    </article>
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Selesai (Siap Ambil)</p>
        <strong class="mt-2 block text-3xl font-black text-emerald-600 dark:text-emerald-400"><?= $stats['selesai']; ?></strong>
    </article>
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Total Pesanan Saya</p>
        <strong class="mt-2 block text-3xl font-black text-slate-900 dark:text-white"><?= $stats['total']; ?></strong>
    </article>
</section>

<!-- SECTION LIST PESANAN TERBARU & FORM ULASAN -->
<div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
    
    <!-- DAFTAR PESANAN SAYA -->
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
        <div class="flex items-center justify-between border-b pb-4 dark:border-slate-700">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">🧺 Ringkasan Pesanan Cucian Anda</h2>
            <a href="history_transaksi.php" class="text-xs font-bold text-cyan-600 hover:underline">Lihat Semua &rarr;</a>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wider text-white dark:bg-slate-950">
                    <tr>
                        <th class="px-4 py-3">Kode Nota</th>
                        <th class="px-4 py-3">Berat</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status Cucian</th>
                        <th class="px-4 py-3 text-center">Nota</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (!$transaksi_list): ?>
                        <tr><td colspan="5" class="p-5 text-center text-slate-500">Belum ada riwayat transaksi cucian.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($transaksi_list as $t): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-3 font-mono font-bold text-cyan-600 dark:text-cyan-400"><?= e($t['kode_transaksi']); ?></td>
                            <td class="px-4 py-3 font-semibold"><?= (float)$t['berat_cucian']; ?> Kg</td>
                            <td class="px-4 py-3 font-bold">Rp <?= number_format($t['total_harga'], 0, ',', '.'); ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-3 py-1 text-xs font-bold 
                                    <?= $t['status_cucian'] === 'Selesai' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 
                                       ($t['status_cucian'] === 'Dalam Proses' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'); ?>">
                                    <?= e($t['status_cucian']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="nota.php?id=<?= (int)$t['id_transaksi']; ?>" target="_blank" class="rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-indigo-500">
                                    📄 Lihat
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FORM INPUT ULASAN & RATING -->
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
        <h2 class="text-lg font-black text-slate-900 dark:text-white">⭐ Beri Rating & Ulasan</h2>
        <p class="mt-1 text-xs text-slate-500">Ulasan Anda membantu kami meningkatkan kualitas layanan RAVF Laundry.</p>
        
        <form method="POST" class="mt-4 space-y-4">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Pilih Rating Bintang</label>
                <select name="rating" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold dark:bg-slate-900">
                    <option value="5">⭐⭐⭐⭐⭐ 5 - Sangat Puas</option>
                    <option value="4">⭐⭐⭐⭐ 4 - Puas & Rapi</option>
                    <option value="3">⭐⭐⭐ 3 - Cukup Baik</option>
                    <option value="2">⭐⭐ 2 - Kurang Puas</option>
                    <option value="1">⭐ 1 - Buruk</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Ulasan / Masukan Anda</label>
                <textarea name="komentar" rows="4" required maxlength="500" class="w-full rounded-xl border border-slate-200 p-3 text-sm dark:bg-slate-900" placeholder="Tuliskan pengalaman Anda mencuci di RAVF Laundry..."></textarea>
            </div>
            <button type="submit" class="w-full rounded-xl bg-cyan-500 py-3 font-black text-slate-950 shadow-md hover:bg-cyan-400 transition">
                🚀 Kirim Ulasan Layanan
            </button>
        </form>
    </div>

</div>

<?php app_end(); ?>
