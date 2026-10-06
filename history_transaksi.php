<?php
require_once 'app_layout.php';

$role = current_role();
$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['nama'] ?? 'Pelanggan';
$message = '';
$statuses = ['Booking Masuk', 'Penjemputan Kurir', 'Menunggu Timbang', 'Dalam Proses', 'Selesai', 'Siap Diambil/Diantar', 'Sudah Selesai & Diambil'];

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Action Update & Hapus Transaksi (Staff/Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_staff()) {
    $id = (int) ($_POST['id_transaksi'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && $action === 'update') {
        $status_cucian = $_POST['status_cucian'] ?? '';
        $status_pembayaran = $_POST['status_pembayaran'] ?? 'Belum Lunas';

        if (in_array($status_cucian, $statuses, true)) {
            // Ambil data transaksi lama
            $q_old = mysqli_query($koneksi, "SELECT id_pelanggan, status_pembayaran, total_harga FROM transaksi WHERE id_transaksi = {$id} LIMIT 1");
            $data_old = mysqli_fetch_assoc($q_old);

            $stmt_upd = mysqli_prepare($koneksi, "UPDATE transaksi SET status_cucian = ?, status_pembayaran = ? WHERE id_transaksi = ?");
            mysqli_stmt_bind_param($stmt_upd, 'ssi', $status_cucian, $status_pembayaran, $id);
            mysqli_stmt_execute($stmt_upd);

            // JIKA BARU BERUBAH MENJADI LUNAS -> TAMBAHKAN POIN PELANGGAN (Kelipatan Rp 5.000 = +1 Poin)
            if ($data_old && $data_old['status_pembayaran'] !== 'Lunas' && $status_pembayaran === 'Lunas') {
                $id_pelanggan = (int) $data_old['id_pelanggan'];
                $total_harga = (float) $data_old['total_harga'];
                $poin_didapat = (int) floor($total_harga / 5000);

                if ($poin_didapat > 0 && $id_pelanggan > 0) {
                    mysqli_query($koneksi, "UPDATE pelanggan SET poin_bonus = poin_bonus + {$poin_didapat} WHERE id_pelanggan = {$id_pelanggan}");
                    $log_p = mysqli_prepare($koneksi, "INSERT INTO riwayat_poin (id_pelanggan, id_transaksi, tipe, jumlah_poin, keterangan) VALUES (?, ?, 'Masuk', ?, 'Bonus Poin Transaksi Lunas')");
                    mysqli_stmt_bind_param($log_p, 'iii', $id_pelanggan, $id, $poin_didapat);
                    mysqli_stmt_execute($log_p);
                }
            }

            $message = 'Status transaksi & pembayaran berhasil diperbarui.';
        }
    } elseif ($id > 0 && $action === 'delete') {
        mysqli_begin_transaction($koneksi);
        $dt = mysqli_prepare($koneksi, "DELETE FROM detail_transaksi WHERE id_transaksi = ?");
        mysqli_stmt_bind_param($dt, 'i', $id);
        mysqli_stmt_execute($dt);

        $tr = mysqli_prepare($koneksi, "DELETE FROM transaksi WHERE id_transaksi = ?");
        mysqli_stmt_bind_param($tr, 'i', $id);
        $ok = mysqli_stmt_execute($tr);

        if ($ok) {
            mysqli_commit($koneksi);
            $message = 'Transaksi berhasil dihapus.';
        } else {
            mysqli_rollback($koneksi);
            $message = 'Gagal menghapus transaksi.';
        }
    }
}

// Fetch Query berdasarkan Role Session
$conditions = [];
$params = [];
$types = '';

if (is_user()) {
    $conditions[] = 't.id_pelanggan = ?';
    $params[] = $user_id;
    $types .= 'i';
}

if ($from !== '') {
    $conditions[] = 'DATE(t.tanggal_transaksi) >= ?';
    $params[] = $from;
    $types .= 's';
}
if ($to !== '') {
    $conditions[] = 'DATE(t.tanggal_transaksi) <= ?';
    $params[] = $to;
    $types .= 's';
}

$sql = 'SELECT t.*, p.nama AS nama_pelanggan, p.no_hp FROM transaksi t LEFT JOIN pelanggan p ON p.id_pelanggan = t.id_pelanggan';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY t.tanggal_transaksi DESC';

$stmt = mysqli_prepare($koneksi, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

app_start('Riwayat Transaksi', 'history');
?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-xs font-black uppercase tracking-widest text-cyan-600">Akses <?= e($role); ?></p>
        <h1 class="mt-1 text-3xl font-black text-slate-900 dark:text-white">Riwayat Transaksi Cucian</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Daftar transaksi, jadwal antar-jemput, dan status pengerjaan.</p>
    </div>
    <a href="transaksi_baru.php" class="rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 shadow-md hover:bg-cyan-400 transition">
        ➕ <?= is_user() ? 'Booking Baru' : 'Transaksi Kasir POS'; ?>
    </a>
</div>

<form method="GET" class="mt-6 grid gap-3 rounded-2xl bg-white p-5 shadow-sm sm:grid-cols-[1fr_1fr_auto_auto] dark:bg-slate-800">
    <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Dari Tanggal</label>
        <input type="date" name="from" value="<?= e($from); ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:bg-slate-900">
    </div>
    <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Sampai Tanggal</label>
        <input type="date" name="to" value="<?= e($to); ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:bg-slate-900">
    </div>
    <div class="flex items-end gap-2 sm:col-span-2">
        <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-500">Filter</button>
        <a href="history_transaksi.php" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:bg-slate-700 dark:text-slate-300">Reset</a>
    </div>
</form>

<?php if ($message): ?>
    <div class="mt-5 rounded-xl border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
        ✅ <?= e($message); ?>
    </div>
<?php endif; ?>

<div class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-900 text-xs uppercase tracking-wider text-white dark:bg-slate-950">
            <tr>
                <th class="px-5 py-4">Kode Nota</th>
                <th class="px-5 py-4">Pelanggan</th>
                <th class="px-5 py-4">Layanan & Kecepatan</th>
                <th class="px-5 py-4">Total & Bayar</th>
                <th class="px-5 py-4">Status Cucian</th>
                <th class="px-5 py-4 text-center">Nota / Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <?php if (mysqli_num_rows($rows) === 0): ?>
                <tr><td colspan="6" class="p-5 text-center text-slate-500">Belum ada riwayat transaksi.</td></tr>
            <?php endif; ?>
            <?php while ($row = mysqli_fetch_assoc($rows)): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="px-5 py-4 font-mono font-bold text-cyan-600 dark:text-cyan-400">
                        <?= e($row['kode_transaksi']); ?>
                        <div class="text-[11px] text-slate-400 font-sans font-normal"><?= date('d/m/Y H:i', strtotime($row['tanggal_transaksi'])); ?></div>
                    </td>
                    <td class="px-5 py-4">
                        <span class="font-bold text-slate-900 dark:text-white"><?= e($row['nama_pelanggan'] ?? '-'); ?></span>
                        <?php if (!empty($row['id_pelanggan'])): ?>
                            <span class="ml-1 text-[10px] font-black text-cyan-600 bg-cyan-50 px-1.5 py-0.5 rounded dark:bg-cyan-950 dark:text-cyan-300"><?= format_pelanggan_id((int)$row['id_pelanggan']); ?></span>
                        <?php endif; ?>
                        <div class="text-xs text-slate-400">
                            <?php if ($wa = format_wa_phone($row['no_hp'])): ?>
                                <?php 
                                    $wa_msg = rawurlencode("Halo Kak {$row['nama_pelanggan']},\nUpdate status cucian RAVF Laundry Anda [{$row['kode_transaksi']}]: *{$row['status_cucian']}*.\nTotal: Rp " . number_format($row['total_harga'], 0, ',', '.') . " ({$row['status_pembayaran']}).\n\nTerima kasih!");
                                ?>
                                <a href="https://wa.me/<?= $wa; ?>?text=<?= $wa_msg; ?>" target="_blank" class="inline-flex items-center gap-1 font-bold text-emerald-600 hover:underline">
                                    💬 <?= e($row['no_hp']); ?>
                                </a>
                            <?php else: ?>
                                <?= e($row['no_hp'] ?? '-'); ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-xs space-y-1">
                        <div><strong class="text-slate-800 dark:text-slate-200"><?= e($row['tipe_layanan_pengiriman']); ?></strong></div>
                        <span class="rounded px-2 py-0.5 font-bold <?= $row['kecepatan_proses'] === 'Express' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'; ?>">
                            <?= e($row['kecepatan_proses']); ?>
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-xs font-bold">
                        <div class="text-slate-900 dark:text-white text-sm">Rp <?= number_format($row['total_harga'], 0, ',', '.'); ?></div>
                        <span class="rounded-full px-2 py-0.5 font-bold text-[10px] <?= $row['status_pembayaran'] === 'Lunas' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                            <?= e($row['status_pembayaran']); ?> (<?= e($row['metode_pembayaran']); ?>)
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs font-bold text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300">
                            <?= e($row['status_cucian']); ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 text-center">
                        <div class="inline-flex items-center gap-2">
                            <a href="nota.php?id=<?= (int)$row['id_transaksi']; ?>" target="_blank" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-bold hover:bg-slate-100 dark:bg-slate-700">
                                📄 Nota
                            </a>
                            <?php if (is_staff()): ?>
                                <form method="POST" class="inline-flex items-center gap-1">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id_transaksi" value="<?= (int)$row['id_transaksi']; ?>">
                                    <select name="status_cucian" class="rounded-lg border border-slate-200 px-2 py-1 text-xs dark:bg-slate-900">
                                        <?php foreach ($statuses as $st): ?>
                                            <option value="<?= e($st); ?>" <?= $st === $row['status_cucian'] ? 'selected' : ''; ?>><?= e($st); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="status_pembayaran" class="rounded-lg border border-slate-200 px-2 py-1 text-xs dark:bg-slate-900">
                                        <option value="Belum Lunas" <?= $row['status_pembayaran'] === 'Belum Lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                                        <option value="Lunas" <?= $row['status_pembayaran'] === 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                                    </select>
                                    <button class="rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-indigo-500">Save</button>
                                </form>
                                <?php if (is_admin()): ?>
                                    <form method="POST" onsubmit="return confirm('Hapus transaksi ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_transaksi" value="<?= (int)$row['id_transaksi']; ?>">
                                        <button class="rounded-lg bg-rose-600 px-2 py-1 text-xs font-bold text-white">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php app_end(); ?>
