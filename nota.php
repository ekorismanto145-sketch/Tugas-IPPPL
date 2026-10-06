<?php
require_once 'auth.php';

if (!isset($_GET['id'])) { 
    die("ID Transaksi tidak ditemukan."); 
}

$id_transaksi = (int) $_GET['id'];
$query = mysqli_query($koneksi, "
    SELECT t.*, p.nama AS nama_pelanggan, p.no_hp, COALESCE(u.nama, 'Sistem / Mandiri Online') AS nama_kasir 
    FROM transaksi t
    JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN users u ON t.id_user = u.id_user
    WHERE t.id_transaksi = {$id_transaksi}
");

if (!$query || mysqli_num_rows($query) === 0) {
    die("Data transaksi tidak ditemukan.");
}

$data = mysqli_fetch_assoc($query);

$detail = mysqli_query($koneksi, "
    SELECT d.*, l.nama_layanan 
    FROM detail_transaksi d
    JOIN layanan_laundry l ON d.id_layanan = l.id_layanan
    WHERE d.id_transaksi = {$id_transaksi}
");

// Cek Otorisasi Cetak (Hanya Staff/Admin yang dapat mencetak langsung dari POS)
$can_print = is_staff();
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Resmi - <?= e($data['kode_transaksi']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Courier New', Courier, monospace; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 min-h-screen flex flex-col items-center justify-center">

    <!-- ACTION BUTTONS BAR (HANYA LAYAR) -->
    <div class="no-print w-full max-w-md mb-4 flex flex-col gap-2">
        <?php if ($can_print): ?>
            <button type="button" onclick="window.print()" class="w-full rounded-xl bg-slate-900 py-3 font-bold text-white shadow-lg hover:bg-slate-800 transition">
                🖨️ Cetak Nota (Kasir / Staf)
            </button>
            <?php 
                $wa_no = format_wa_phone($data['no_hp']); 
                $wa_text = rawurlencode("Halo Kak {$data['nama_pelanggan']},\nBerikut adalah Nota Transaksi RAVF Laundry Anda:\n\n*No Nota:* {$data['kode_transaksi']}\n*Status Cucian:* {$data['status_cucian']}\n*Total:* Rp " . number_format($data['total_harga'], 0, ',', '.') . "\n\nAnda dapat mengecek detail nota digital di sini:\n{$current_url}\n\nTerima kasih telah menggunakan jasa RAVF Laundry!");
            ?>
            <?php if ($wa_no): ?>
                <a href="https://wa.me/<?= $wa_no; ?>?text=<?= $wa_text; ?>" target="_blank" class="w-full text-center rounded-xl bg-emerald-600 py-2.5 font-bold text-white shadow hover:bg-emerald-500 transition text-sm flex items-center justify-center gap-2">
                    📱 Kirim Nota via WhatsApp Pelanggan
                </a>
            <?php endif; ?>
            <button type="button" onclick="copyShareLink()" class="w-full rounded-xl bg-indigo-600 py-2 font-bold text-white shadow hover:bg-indigo-500 transition text-sm">
                🔗 Bagikan Link Nota ke Pelanggan
            </button>
            <div id="copy-toast" class="hidden text-center text-xs font-bold text-emerald-600 bg-emerald-50 p-2 rounded-lg">
                ✓ Link Nota berhasil disalin!
            </div>
        <?php else: ?>
            <div class="flex gap-2">
                <a href="customer_dashboard.php" class="w-full rounded-xl bg-slate-900 py-3 font-bold text-white shadow-lg hover:bg-slate-800 transition text-center text-sm">
                    &larr; Kembali ke Dashboard
                </a>
                <button type="button" onclick="window.print()" class="w-full rounded-xl bg-cyan-600 py-3 font-bold text-white shadow-lg hover:bg-cyan-500 transition text-center text-sm">
                    🖨️ Cetak / Simpan PDF
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- STRUK / NOTA CONTAINER -->
    <div class="w-full max-w-md bg-white p-6 shadow-2xl rounded-2xl border border-slate-200">
        
        <!-- HEADER LAUNDRY -->
        <div class="text-center border-b border-dashed border-slate-300 pb-4">
            <h1 class="text-2xl font-black tracking-widest text-slate-900">RAVF LAUNDRY</h1>
            <p class="text-xs text-slate-500 mt-1">Clean, Fresh & Professional Laundry Service</p>
            <p class="text-xs text-slate-500">Jl. Raya Telang, Universitas Trunojoyo Madura · WA: 0812-3456-7890</p>
            <p class="text-[11px] text-slate-400 mt-1">Tanggal Cetak: <?= date('d/m/Y H:i'); ?></p>
        </div>

        <!-- METADATA TRANSAKSI -->
        <div class="py-4 border-b border-dashed border-slate-300 text-xs space-y-1 text-slate-700">
            <div class="flex justify-between"><span class="font-bold">No. Nota:</span><span class="font-mono font-bold text-slate-900"><?= e($data['kode_transaksi']); ?></span></div>
            <div class="flex justify-between"><span>Tanggal Order:</span><span><?= date('d/m/Y H:i', strtotime($data['tanggal_transaksi'])); ?></span></div>
            <div class="flex justify-between"><span>Kasir:</span><span><?= e($data['nama_kasir']); ?></span></div>
            <div class="flex justify-between"><span>Pelanggan:</span><span class="font-bold text-slate-900"><?= e($data['nama_pelanggan']); ?></span></div>
            <div class="flex justify-between"><span>No. HP:</span><span><?= e($data['no_hp'] ?: '-'); ?></span></div>
        </div>

        <!-- RINCIAN ITEM -->
        <div class="py-4 border-b border-dashed border-slate-300 text-xs">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-slate-400 border-b border-slate-100">
                        <th class="pb-2">Layanan</th>
                        <th class="pb-2 text-center">Jumlah</th>
                        <th class="pb-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php while ($item = mysqli_fetch_assoc($detail)): ?>
                        <tr>
                            <td class="py-2 font-bold text-slate-800"><?= e($item['nama_layanan']); ?></td>
                            <td class="py-2 text-center"><?= number_format($item['jumlah'], 1); ?> Kg</td>
                            <td class="py-2 text-right font-bold">Rp <?= number_format($item['subtotal'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- TOTAL & PEMBAYARAN -->
        <div class="py-4 border-b border-dashed border-slate-300 text-xs space-y-2">
            <div class="flex justify-between text-base font-black text-slate-900">
                <span>TOTAL HARGA</span>
                <span class="text-cyan-600">Rp <?= number_format($data['total_harga'], 0, ',', '.'); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Metode Bayar:</span>
                <span class="font-bold"><?= e($data['metode_pembayaran']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Status Pembayaran:</span>
                <span class="font-bold <?= $data['status_pembayaran'] === 'Lunas' ? 'text-emerald-600' : 'text-amber-600'; ?>">
                    <?= e($data['status_pembayaran']); ?>
                </span>
            </div>
            <div class="flex justify-between">
                <span>Status Cucian:</span>
                <span class="font-bold text-indigo-600"><?= e($data['status_cucian']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Est. Selesai:</span>
                <span class="font-bold text-slate-800">
                    <?= !empty($data['tanggal_selesai']) ? date('d F Y', strtotime($data['tanggal_selesai'])) : date('d F Y', strtotime($data['tanggal_transaksi'] . ' +3 days')); ?>
                </span>
            </div>
        </div>

        <!-- METODE PEMBAYARAN KHUSUS (QRIS / VIRTUAL ACCOUNT) -->
        <?php if ($data['metode_pembayaran'] === 'QRIS'): ?>
            <div class="py-4 border-b border-dashed border-slate-300 text-center space-y-3">
                <p class="text-xs font-black uppercase text-slate-800">📱 Pembayaran via QRIS Resmi</p>
                <div class="inline-block p-3 bg-white border-2 border-slate-900 rounded-2xl shadow-md">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode('RAVF-LAUNDRY-' . $data['kode_transaksi'] . '-' . (int)$data['total_harga']); ?>" alt="QRIS Code" class="w-40 h-40 mx-auto">
                </div>
                <p class="text-[11px] text-slate-600 font-semibold">Scan QRIS di atas menggunakan <strong>Gopay, OVO, DANA, ShopeePay, atau Mobile Banking</strong></p>
                <p class="text-xs font-black text-cyan-700">Nominal: Rp <?= number_format($data['total_harga'], 0, ',', '.'); ?></p>
            </div>
        <?php elseif ($data['metode_pembayaran'] === 'Transfer'): ?>
            <div class="py-4 border-b border-dashed border-slate-300 text-xs space-y-3">
                <p class="font-black text-slate-800 uppercase text-center">🏦 Virtual Account Bank Transfer</p>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-slate-900">BCA Virtual Account</p>
                            <p class="font-mono text-sm font-black text-indigo-700">88012 081234567890</p>
                        </div>
                        <button type="button" onclick="copyVA('88012081234567890')" class="px-2.5 py-1 bg-indigo-100 text-indigo-700 font-bold rounded-lg text-[10px] hover:bg-indigo-200">Salin</button>
                    </div>
                    <hr class="border-slate-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-slate-900">Mandiri Virtual Account</p>
                            <p class="font-mono text-sm font-black text-indigo-700">89022 081234567890</p>
                        </div>
                        <button type="button" onclick="copyVA('89022081234567890')" class="px-2.5 py-1 bg-indigo-100 text-indigo-700 font-bold rounded-lg text-[10px] hover:bg-indigo-200">Salin</button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 text-center">Pembayaran otomatis terverifikasi setelah Anda melakukan transfer.</p>
            </div>
        <?php endif; ?>

        <!-- FOOTER NOTA -->
        <div class="text-center pt-4 text-[11px] text-slate-500 space-y-1">
            <p class="font-bold text-slate-700">Terima kasih atas kepercayaan Anda!</p>
            <p>Syarat & Ketentuan: Pengambilan cucian wajib membawa nota ini / menunjukan salinan digital.</p>
        </div>
    </div>

    <script>
    function copyShareLink() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            const toast = document.getElementById('copy-toast');
            if (toast) {
                toast.classList.remove('hidden');
                setTimeout(() => toast.classList.add('hidden'), 3000);
            }
        });
    }
    function copyVA(num) {
        navigator.clipboard.writeText(num).then(() => {
            alert('Nomor Virtual Account ' + num + ' berhasil disalin!');
        });
    }
    </script>
</body>
</html>