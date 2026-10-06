<?php
require_once 'app_layout.php';
require_admin(); // Strict Admin Security Guard

$rekap_mode = $_GET['rekap'] ?? 'mingguan'; // 'mingguan', 'bulanan', 'tahunan'
$today = date('Y-m-d');

// Penentuan Range Tanggal berdasarkan Rekapitulasi
if ($rekap_mode === 'mingguan') {
    $from = date('Y-m-d', strtotime('-6 days'));
    $to = $today;
    $title_rekap = "Rekapitulasi Mingguan (7 Hari Terakhir)";
} elseif ($rekap_mode === 'bulanan') {
    $from = date('Y-m-01');
    $to = date('Y-m-t');
    $title_rekap = "Rekapitulasi Bulanan (" . date('F Y') . ")";
} elseif ($rekap_mode === 'tahunan') {
    $from = date('Y-01-01');
    $to = date('Y-12-31');
    $title_rekap = "Rekapitulasi Tahunan (Tahun " . date('Y') . ")";
} else {
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $title_rekap = "Laporan Custom (" . date('d/m/Y', strtotime($from)) . " - " . date('d/m/Y', strtotime($to)) . ")";
}

// Summary Query
$stmt = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total_transaksi, COALESCE(SUM(total_harga), 0) AS omset, COALESCE(SUM(CASE WHEN status_pembayaran = 'Lunas' THEN total_harga ELSE 0 END), 0) AS lunas, COALESCE(SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN total_harga ELSE 0 END), 0) AS piutang FROM transaksi WHERE DATE(tanggal_transaksi) BETWEEN ? AND ?");
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Rincian Harian
$daily_stmt = mysqli_prepare($koneksi, "SELECT DATE(tanggal_transaksi) AS hari, COUNT(*) AS jumlah, COALESCE(SUM(total_harga), 0) AS total FROM transaksi WHERE DATE(tanggal_transaksi) BETWEEN ? AND ? GROUP BY DATE(tanggal_transaksi) ORDER BY hari ASC");
mysqli_stmt_bind_param($daily_stmt, 'ss', $from, $to);
mysqli_stmt_execute($daily_stmt);
$daily = mysqli_stmt_get_result($daily_stmt);
$rows = [];
while ($item = mysqli_fetch_assoc($daily)) {
    $rows[] = $item;
}

$max = 1;
foreach ($rows as $item) {
    $max = max($max, (float) $item['total']);
}

app_start('Laporan Keuangan Rekapitulasi', 'report');
?>

<style>
@media print {
    .no-print, #app-sidebar, #sidebar-overlay, header { display: none !important; }
    main { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
    .print-header { display: block !important; }
    .surface { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
}
.print-header { display: none; }
</style>

<!-- HEADER PRINT KHUSUS LAPORAN PDF -->
<div class="print-header mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black uppercase tracking-wider text-slate-900">RAVF LAUNDRY ECOSMART</h1>
            <p class="text-xs text-slate-600">Jl. Laundry Super Clean No. 123, Indonesia · Telp/WA: 0812-3456-7890</p>
        </div>
        <div class="text-right">
            <h2 class="text-base font-bold text-slate-800">LAPORAN REKAPITULASI KEUANGAN</h2>
            <p class="text-xs text-slate-500">Tanggal Cetak: <?= date('d F Y H:i:s'); ?></p>
        </div>
    </div>
</div>

<div class="no-print flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-xs font-black uppercase tracking-widest text-cyan-600">Admin Dashboard</p>
        <h1 class="mt-1 text-3xl font-black text-slate-900 dark:text-white">Laporan Keuangan Rekapitulasi</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pilih rekapitulasi waktu untuk memantau omset, export Excel, dan cetak laporan PDF.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="export_laporan_excel.php?from=<?= urlencode($from); ?>&to=<?= urlencode($to); ?>" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-lg transition hover:bg-emerald-500 flex items-center gap-2">
            📊 Export Excel (.csv)
        </a>
        <button type="button" onclick="window.print()" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white shadow-lg transition hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950">
            🖨️ Cetak / Simpan PDF
        </button>
    </div>
</div>

<!-- TOMBOL PILIHAN REKAPITULASI -->
<div class="no-print mt-6 flex flex-wrap gap-3">
    <a href="laporan.php?rekap=mingguan" class="rounded-xl px-4 py-2.5 text-sm font-bold transition <?= $rekap_mode === 'mingguan' ? 'bg-cyan-500 text-slate-950 font-black shadow-md' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200 dark:bg-slate-800 dark:text-slate-300' ?>">
        📅 Rekap Mingguan
    </a>
    <a href="laporan.php?rekap=bulanan" class="rounded-xl px-4 py-2.5 text-sm font-bold transition <?= $rekap_mode === 'bulanan' ? 'bg-cyan-500 text-slate-950 font-black shadow-md' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200 dark:bg-slate-800 dark:text-slate-300' ?>">
        🗓️ Rekap Bulanan
    </a>
    <a href="laporan.php?rekap=tahunan" class="rounded-xl px-4 py-2.5 text-sm font-bold transition <?= $rekap_mode === 'tahunan' ? 'bg-cyan-500 text-slate-950 font-black shadow-md' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200 dark:bg-slate-800 dark:text-slate-300' ?>">
        📊 Rekap Tahunan
    </a>
</div>

<!-- RINGKASAN OMSET -->
<section class="mt-6 grid gap-4 sm:grid-cols-4">
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Total Transaksi</p>
        <strong class="mt-2 block text-3xl font-black text-slate-900 dark:text-white"><?= number_format($summary['total_transaksi']); ?></strong>
    </article>
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Total Omset</p>
        <strong class="mt-2 block text-3xl font-black text-cyan-600 dark:text-cyan-400">Rp <?= number_format($summary['omset'], 0, ',', '.'); ?></strong>
    </article>
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Terbayar Lunas</p>
        <strong class="mt-2 block text-3xl font-black text-emerald-600 dark:text-emerald-400">Rp <?= number_format($summary['lunas'], 0, ',', '.'); ?></strong>
    </article>
    <article class="surface rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-800">
        <p class="muted text-xs uppercase font-bold text-slate-500">Piutang Belum Lunas</p>
        <strong class="mt-2 block text-3xl font-black text-amber-600 dark:text-amber-400">Rp <?= number_format($summary['piutang'], 0, ',', '.'); ?></strong>
    </article>
</section>

<!-- GRAFIK & TABLE RINCIAN -->
<section class="surface mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:bg-slate-800">
    <h2 class="text-lg font-black text-slate-900 dark:text-white"><?= e($title_rekap); ?></h2>
    
    <!-- GRAFIK BAR Sederhana (Hanya tampil di Layar) -->
    <div class="no-print mt-8 flex h-64 items-end gap-3 border-b border-l border-slate-200 px-3 pt-4 dark:border-slate-700">
        <?php if (!$rows): ?>
            <div class="flex h-full w-full items-center justify-center text-sm text-slate-400">Belum ada transaksi pada periode ini.</div>
        <?php else: ?>
            <?php foreach ($rows as $item): 
                $height = max(6, round(((float) $item['total'] / $max) * 100)); 
            ?>
                <div class="group flex h-full flex-1 flex-col items-center justify-end gap-2">
                    <span class="invisible text-xs font-bold group-hover:visible dark:text-slate-300">Rp <?= number_format($item['total'], 0, ',', '.'); ?></span>
                    <div class="w-full max-w-12 rounded-t-lg bg-cyan-500 transition-all hover:bg-cyan-400" style="height:<?= $height; ?>%"></div>
                    <span class="muted text-xs"><?= date('d/m', strtotime($item['hari'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RINCIAN TABEL -->
    <div class="mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wider text-white dark:bg-slate-950">
                <tr>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Jumlah Transaksi</th>
                    <th class="px-4 py-3 text-right">Omset Harian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <?php if (!$rows): ?>
                    <tr><td colspan="3" class="p-4 text-center text-slate-500">Tidak ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $item): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-white"><?= date('d F Y', strtotime($item['hari'])); ?></td>
                        <td class="px-4 py-3"><?= (int) $item['jumlah']; ?> Pesanan</td>
                        <td class="px-4 py-3 text-right font-bold text-cyan-600 dark:text-cyan-400">Rp <?= number_format($item['total'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php app_end(); ?>
