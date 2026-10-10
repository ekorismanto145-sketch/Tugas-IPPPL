<?php
require_once 'app_layout.php';
require_admin();

// Auto-create tabel vouchers jika belum ada
@mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS vouchers (
    id_voucher INT AUTO_INCREMENT PRIMARY KEY,
    kode_voucher VARCHAR(50) NOT NULL UNIQUE,
    tipe_diskon ENUM('Nominal', 'Persen') NOT NULL DEFAULT 'Nominal',
    nilai_diskon DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_transaksi DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    kuota INT NOT NULL DEFAULT 100,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$success = '';
$error = '';

// Handling Form Submit (Tambah & Edit Voucher)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $kode = strtoupper(trim($_POST['kode_voucher'] ?? ''));
        $tipe = $_POST['tipe_diskon'] ?? 'Nominal';
        $nilai = (float)($_POST['nilai_diskon'] ?? 0);
        $min_tx = (float)($_POST['min_transaksi'] ?? 0);
        $kuota = (int)($_POST['kuota'] ?? 100);
        $status = $_POST['status'] ?? 'Aktif';

        if (empty($kode)) {
            $error = 'Kode voucher tidak boleh kosong.';
        } elseif ($nilai <= 0) {
            $error = 'Nilai diskon harus lebih dari 0.';
        } else {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO vouchers (kode_voucher, tipe_diskon, nilai_diskon, min_transaksi, kuota, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssddis', $kode, $tipe, $nilai, $min_tx, $kuota, $status);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Voucher <strong>{$kode}</strong> berhasil ditambahkan!";
            } else {
                $error = 'Gagal menambahkan voucher. Kode mungkin sudah digunakan.';
            }
        }
    } elseif ($action === 'toggle_status') {
        $id_voucher = (int)($_POST['id_voucher'] ?? 0);
        $new_status = $_POST['new_status'] === 'Aktif' ? 'Aktif' : 'Nonaktif';
        mysqli_query($koneksi, "UPDATE vouchers SET status = '{$new_status}' WHERE id_voucher = {$id_voucher}");
        $success = 'Status voucher berhasil diperbarui!';
    } elseif ($action === 'delete') {
        $id_voucher = (int)($_POST['id_voucher'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM vouchers WHERE id_voucher = {$id_voucher}");
        $success = 'Voucher berhasil dihapus!';
    }
}

$vouchers = mysqli_query($koneksi, "SELECT * FROM vouchers ORDER BY id_voucher DESC");

app_start('Kelola Voucher & Promo Diskon', 'vouchers');
?>

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-widest text-cyan-600">Modul Administrasi Promo</p>
            <h1 class="text-3xl font-black text-slate-900 dark:text-white">🎟️ Kelola Voucher & Promo</h1>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="rounded-xl border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
            ✅ <?= $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-xl border-l-4 border-rose-500 bg-rose-50 p-4 text-sm font-bold text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
            ⚠️ <?= e($error); ?>
        </div>
    <?php endif; ?>

    <!-- FORM TAMBAH VOUCHER -->
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
        <h2 class="text-lg font-black text-slate-900 dark:text-white mb-4">➕ Buat Voucher Diskon Baru</h2>
        <form method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <input type="hidden" name="action" value="create">
            
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Kode Voucher (Kapital)</label>
                <input type="text" name="kode_voucher" required placeholder="Contoh: HEMAT10" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold uppercase dark:bg-slate-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Tipe Diskon</label>
                <select name="tipe_diskon" required class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                    <option value="Nominal">Nominal Rupiah (Rp)</option>
                    <option value="Persen">Persentase (%)</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Nilai Diskon</label>
                <input type="number" step="0.01" name="nilai_diskon" required placeholder="Misal: 5000 atau 10" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Minimal Transaksi (Rp)</label>
                <input type="number" step="1000" name="min_transaksi" value="0" required class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Kuota Pemakaian</label>
                <input type="number" name="kuota" value="100" required class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                    <option value="Aktif">Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>

            <div class="sm:col-span-3 text-right">
                <button type="submit" class="rounded-xl bg-cyan-600 px-6 py-3 text-sm font-black text-white hover:bg-cyan-500 shadow-md">
                    Simpan Voucher
                </button>
            </div>
        </form>
    </div>

    <!-- TABEL DAFTAR VOUCHER -->
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800 overflow-x-auto">
        <h2 class="text-lg font-black text-slate-900 dark:text-white mb-4">📋 Daftar Voucher Aktif & Promo</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 uppercase text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                <tr>
                    <th class="p-3">Kode</th>
                    <th class="p-3">Diskon</th>
                    <th class="p-3">Min. Transaksi</th>
                    <th class="p-3">Kuota</th>
                    <th class="p-3">Status</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <?php while ($v = mysqli_fetch_assoc($vouchers)): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                        <td class="p-3 font-black text-cyan-600 dark:text-cyan-400"><?= e($v['kode_voucher']); ?></td>
                        <td class="p-3 font-bold">
                            <?= $v['tipe_diskon'] === 'Persen' ? e($v['nilai_diskon']) . '%' : 'Rp ' . number_format($v['nilai_diskon'], 0, ',', '.'); ?>
                        </td>
                        <td class="p-3 text-slate-600 dark:text-slate-300">
                            Rp <?= number_format($v['min_transaksi'], 0, ',', '.'); ?>
                        </td>
                        <td class="p-3 font-bold"><?= (int)$v['kuota']; ?>x</td>
                        <td class="p-3">
                            <span class="rounded-full px-3 py-1 text-xs font-black <?= $v['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'; ?>">
                                <?= e($v['status']); ?>
                            </span>
                        </td>
                        <td class="p-3 text-center space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id_voucher" value="<?= $v['id_voucher']; ?>">
                                <input type="hidden" name="new_status" value="<?= $v['status'] === 'Aktif' ? 'Nonaktif' : 'Aktif'; ?>">
                                <button type="submit" class="text-xs font-bold text-indigo-600 hover:underline">
                                    <?= $v['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                </button>
                            </form>
                            <span>·</span>
                            <form method="POST" class="inline" onsubmit="return confirm('Yakin hapus voucher ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_voucher" value="<?= $v['id_voucher']; ?>">
                                <button type="submit" class="text-xs font-bold text-rose-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php app_end(); ?>
