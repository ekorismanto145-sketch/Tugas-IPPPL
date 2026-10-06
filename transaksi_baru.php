<?php
require_once 'app_layout.php';

$error = '';
$today = date('Y-m-d');
$is_customer = is_user();

// Ambil Status Express Overload
$q_express_status = mysqli_query($koneksi, "SELECT val_value FROM pengaturans WHERE key_name = 'express_status' LIMIT 1");
$express_status = mysqli_fetch_assoc($q_express_status)['val_value'] ?? 'Available';

// Cek Kuota Express Hari Ini (jika >= 10 transaksi express maka overload)
$q_express_count = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE kecepatan_proses = 'Express' AND DATE(tanggal_transaksi) = CURDATE()");
$total_express_today = (int) (mysqli_fetch_assoc($q_express_count)['total'] ?? 0);

$is_express_overload = ($express_status === 'Overload') || ($total_express_today >= 10);

$pelanggan_id = 0;
$poin_pelanggan = 0;
$nama_pelanggan_user = '';

if ($is_customer) {
    $user_id = (int) $_SESSION['user_id'];
    $q_cust = mysqli_query($koneksi, "SELECT id_pelanggan, nama, poin_bonus FROM pelanggan WHERE id_pelanggan = {$user_id} LIMIT 1");
    if ($data_cust = mysqli_fetch_assoc($q_cust)) {
        $pelanggan_id = (int) $data_cust['id_pelanggan'];
        $poin_pelanggan = (int) $data_cust['poin_bonus'];
        $nama_pelanggan_user = $data_cust['nama'];
    }
}

// Handling Submit Transaction / Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = 'RAVF-' . date('YmdHis');
    $id_pelanggan = $is_customer ? $pelanggan_id : (int) ($_POST['id_pelanggan'] ?? 0);
    $id_user = $is_customer ? null : (int) $_SESSION['user_id'];

    $layanan_id = (int) ($_POST['id_layanan'] ?? 0);
    $tipe_pengiriman = $_POST['tipe_layanan_pengiriman'] ?? 'Drop Point Outlet';
    $kecepatan = $_POST['kecepatan_proses'] ?? 'Regular';

    // Cek Guard jika Express Overload tapi tetap dipaksa submit
    if ($kecepatan === 'Express' && $is_express_overload) {
        $error = 'Mohon maaf, layanan Express hari ini sedang OVERLOAD. Silakan pilih layanan Regular.';
    } elseif ($id_pelanggan <= 0) {
        $error = 'Silakan pilih pelanggan yang valid.';
    } elseif ($layanan_id <= 0) {
        $error = 'Silakan pilih jenis layanan laundry.';
    } else {
        // Ambil info layanan
        $q_lay = mysqli_query($koneksi, "SELECT harga_per_kg FROM layanan_laundry WHERE id_layanan = {$layanan_id} LIMIT 1");
        $harga_per_kg = (float) (mysqli_fetch_assoc($q_lay)['harga_per_kg'] ?? 0);

        // Biaya Express & Pengiriman (Jarak)
        $biaya_express_val = 0;
        $jarak_km = 0.0;
        $biaya_antar_jemput = 0.0;
        $tgl_pickup = null;
        $alamat_pickup = null;

        if ($tipe_pengiriman === 'Antar-Jemput Kurir') {
            $jarak_km = (float) ($_POST['jarak_km'] ?? 1.0);
            $tgl_pickup = $_POST['tanggal_pickup'] ?? null;
            $alamat_pickup = trim($_POST['alamat_pickup'] ?? '');
            
            // Rumus Ongkir: <= 3km = Rp 5.000, extra 1.000 / km
            if ($jarak_km <= 3.0) {
                $biaya_antar_jemput = 5000.0;
            } else {
                $biaya_antar_jemput = 5000.0 + (($jarak_km - 3.0) * 1000.0);
            }
        }

        $berat = (float) ($_POST['berat'] ?? 0.0);
        
        // Kalkulasi Tambahan Express (+Rp 2.000 per Kg jika ada timbangan, atau flat 2.000)
        if ($kecepatan === 'Express') {
            $biaya_express_val = $berat > 0 ? ($berat * 2000.0) : 2000.0;
        }

        // REDEEM POIN (10 Poin = Gratis 1 Kg)
        $use_poin = isset($_POST['use_poin']) && (int)$_POST['use_poin'] === 1;
        $poin_ditukar = 0;
        $diskon_poin = 0.0;

        // Ambil poin terkini dari DB
        $q_p_check = mysqli_query($koneksi, "SELECT poin_bonus FROM pelanggan WHERE id_pelanggan = {$id_pelanggan} LIMIT 1");
        $poin_db = (int) (mysqli_fetch_assoc($q_p_check)['poin_bonus'] ?? 0);

        if ($use_poin && $poin_db >= 10) {
            $poin_ditukar = 10;
            $diskon_poin = $harga_per_kg; // Potong harga 1 Kg
        }

        // Subtotal Poin & Ongkir
        $subtotal_cucian = ($berat * $harga_per_kg);
        $total_harga = ($subtotal_cucian + $biaya_express_val + $biaya_antar_jemput) - $diskon_poin;
        if ($total_harga < 0) $total_harga = 0;

        $metode = $_POST['metode_pembayaran'] ?? 'Cash Outlet';
        $bayar = $_POST['status_pembayaran'] ?? 'Belum Lunas';
        $catatan = trim($_POST['catatan'] ?? '');
        $status_cucian = $is_customer ? 'Booking Masuk' : 'Menunggu Timbang';

        // Insert Transaksi
        $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO transaksi (kode_transaksi, id_pelanggan, id_user, tipe_layanan_pengiriman, kecepatan_proses, tanggal_pickup, alamat_pickup, jarak_km, biaya_antar_jemput, biaya_express, berat_cucian, poin_ditukar, diskon_poin, total_harga, metode_pembayaran, status_pembayaran, status_cucian, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $user_param = $id_user > 0 ? $id_user : null;
        mysqli_stmt_bind_param($stmt_ins, 'siissssddddiddssss', 
            $kode, $id_pelanggan, $user_param, $tipe_pengiriman, $kecepatan, 
            $tgl_pickup, $alamat_pickup, $jarak_km, $biaya_antar_jemput, $biaya_express_val, 
            $berat, $poin_ditukar, $diskon_poin, $total_harga, $metode, $bayar, $status_cucian, $catatan
        );

        if (mysqli_stmt_execute($stmt_ins)) {
            $transaction_id = mysqli_insert_id($koneksi);

            // Insert Detail Transaksi (Layanan yang dipilih)
            $jumlah_item = $berat > 0 ? $berat : 1.0;
            $dt_stmt = mysqli_prepare($koneksi, "INSERT INTO detail_transaksi (id_transaksi, id_layanan, jumlah, subtotal) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($dt_stmt, 'iidd', $transaction_id, $layanan_id, $jumlah_item, $subtotal_cucian);
            mysqli_stmt_execute($dt_stmt);

            // Jika Poin Ditukar, potong saldo poin pelanggan & buat log
            if ($poin_ditukar > 0) {
                mysqli_query($koneksi, "UPDATE pelanggan SET poin_bonus = poin_bonus - {$poin_ditukar} WHERE id_pelanggan = {$id_pelanggan}");
                $log_poin = mysqli_prepare($koneksi, "INSERT INTO riwayat_poin (id_pelanggan, id_transaksi, tipe, jumlah_poin, keterangan) VALUES (?, ?, 'Keluar', ?, 'Redeem Diskon Gratis 1 Kg')");
                mysqli_stmt_bind_param($log_poin, 'iii', $id_pelanggan, $transaction_id, $poin_ditukar);
                mysqli_stmt_execute($log_poin);
            }

            header('Location: nota.php?id=' . $transaction_id);
            exit;
        } else {
            $error = 'Gagal memproses transaksi. Silakan periksa kembali.';
        }
    }
}

// Master Data Pelanggan & Layanan
$pelanggan_list = mysqli_query($koneksi, "
    SELECT p.id_pelanggan, p.nama, p.no_hp, p.poin_bonus,
           t.id_transaksi AS booking_id_transaksi,
           t.kecepatan_proses AS booking_kecepatan,
           t.tipe_layanan_pengiriman AS booking_pengiriman,
           t.jarak_km AS booking_jarak,
           t.alamat_pickup AS booking_alamat,
           t.poin_ditukar AS booking_poin_ditukar,
           t.metode_pembayaran AS booking_metode,
           t.berat_cucian AS booking_berat,
           dt.id_layanan AS booking_id_layanan
    FROM pelanggan p
    LEFT JOIN (
        SELECT t1.* 
        FROM transaksi t1
        INNER JOIN (
            SELECT id_pelanggan, MAX(id_transaksi) AS max_id 
            FROM transaksi 
            WHERE status_cucian IN ('Booking Masuk', 'Menunggu Timbang')
            GROUP BY id_pelanggan
        ) t2 ON t1.id_transaksi = t2.max_id
    ) t ON p.id_pelanggan = t.id_pelanggan
    LEFT JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
    ORDER BY p.nama ASC
");
$layanan_list = mysqli_query($koneksi, "SELECT id_layanan, nama_layanan, harga_per_kg, estimasi_hari, deskripsi FROM layanan_laundry ORDER BY harga_per_kg ASC");

$pelanggan_arr = [];
while ($p = mysqli_fetch_assoc($pelanggan_list)) {
    $pelanggan_arr[] = $p;
}

$layanan_arr = [];
while ($l = mysqli_fetch_assoc($layanan_list)) {
    $layanan_arr[] = $l;
}

app_start($is_customer ? 'Booking Layanan Laundry' : 'Input Transaksi Kasir POS', 'new');
?>

<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-widest text-cyan-600"><?= $is_customer ? 'Portal Booking Pelanggan' : 'Kasir POS System'; ?></p>
            <h1 class="mt-1 text-3xl font-black text-slate-900 dark:text-white"><?= $is_customer ? 'Booking Layanan Laundry' : 'Kasir & Input Timbangan'; ?></h1>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mt-5 rounded-xl border-l-4 border-rose-500 bg-rose-50 p-4 text-sm font-bold text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
            ⚠️ <?= e($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="mt-6 space-y-6">
        
        <!-- SEKSI 1: PELANGGAN / USER -->
        <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
            <h2 class="text-base font-black text-slate-900 dark:text-white mb-3">👤 Data Pelanggan</h2>
            
            <?php if ($is_customer): ?>
                <div class="rounded-xl bg-slate-100 p-4 dark:bg-slate-700 flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500">Pemesan (Akun Anda):</p>
                        <p class="text-lg font-black text-slate-900 dark:text-white"><?= e($nama_pelanggan_user); ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-500">Saldo Poin Member</span>
                        <p class="text-xl font-black text-cyan-600 dark:text-cyan-400">⭐ <?= $poin_pelanggan; ?> Poin</p>
                    </div>
                </div>
            <?php else: ?>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Pilih Nama Pelanggan</label>
                    <select id="select-pelanggan" name="id_pelanggan" required onchange="updateMemberInfo()" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($pelanggan_arr as $p): ?>
                            <?php 
                                $has_booking = !empty($p['booking_id_transaksi']); 
                                $booking_info = htmlspecialchars(json_encode([
                                    'has_booking' => $has_booking,
                                    'id_layanan' => (int)($p['booking_id_layanan'] ?? 0),
                                    'kecepatan' => $p['booking_kecepatan'] ?? 'Regular',
                                    'pengiriman' => $p['booking_pengiriman'] ?? 'Drop Point Outlet',
                                    'jarak' => (float)($p['booking_jarak'] ?? 0),
                                    'alamat' => $p['booking_alamat'] ?? '',
                                    'berat' => (float)($p['booking_berat'] ?? 0),
                                    'poin_ditukar' => (int)($p['booking_poin_ditukar'] ?? 0),
                                    'metode' => $p['booking_metode'] ?? 'Cash Outlet'
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <option value="<?= (int)$p['id_pelanggan']; ?>" data-poin="<?= (int)$p['poin_bonus']; ?>" data-booking='<?= $booking_info; ?>'>
                                [<?= format_pelanggan_id((int)$p['id_pelanggan']); ?>] <?= e($p['nama']); ?> (HP: <?= e($p['no_hp'] ?: '-'); ?>) - ⭐ <?= (int)$p['poin_bonus']; ?> Poin <?= $has_booking ? ' [📌 Memiliki Booking Online]' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <!-- SEKSI 2: PAKET LAYANAN & KECEPATAN PROSES -->
        <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800 space-y-4">
            <h2 class="text-base font-black text-slate-900 dark:text-white">🧺 Pilih Jenis Layanan & Kecepatan</h2>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Jenis Layanan Laundry</label>
                <select id="select-layanan" name="id_layanan" required onchange="hitungSemua()" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                    <?php foreach ($layanan_arr as $l): ?>
                        <option value="<?= (int)$l['id_layanan']; ?>" data-harga="<?= (float)$l['harga_per_kg']; ?>">
                            <?= e($l['nama_layanan']); ?> — Rp <?= number_format($l['harga_per_kg'], 0, ',', '.'); ?> / Kg (Est. <?= (int)$l['estimasi_hari']; ?> Hari)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- KECEPATAN PROSES (REGULAR VS EXPRESS OVERLOAD GUARD) -->
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Kecepatan Proses Pengerjaan</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700">
                        <input type="radio" name="kecepatan_proses" value="Regular" checked onchange="hitungSemua()" class="h-4 w-4 text-cyan-600">
                        <div>
                            <p class="text-xs font-black text-slate-900 dark:text-white">🚀 Regular (Biasa)</p>
                            <p class="text-[11px] text-slate-500">Proses 2 - 3 Hari · Tanpa Biaya Tambahan</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer <?= $is_express_overload ? 'opacity-50 bg-slate-100 cursor-not-allowed' : 'hover:bg-slate-50 dark:hover:bg-slate-700'; ?>">
                        <input type="radio" name="kecepatan_proses" value="Express" <?= $is_express_overload ? 'disabled' : ''; ?> onchange="hitungSemua()" class="h-4 w-4 text-cyan-600">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-xs font-black text-slate-900 dark:text-white">⚡ Express Kilat</p>
                                <?php if ($is_express_overload): ?>
                                    <span class="rounded bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white">OVERLOAD / PENUH</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-[11px] text-slate-500">Proses 12 Jam - 1 Hari · +Rp 2.000 / Kg</p>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">
                    <?= $is_customer ? 'Estimasi / Perkiraan Berat Cucian (Kg)' : 'Hasil Timbangan Kasir (Kg)'; ?>
                </label>
                <div class="relative flex items-center">
                    <input type="number" step="0.1" min="0.1" id="input-berat" name="berat" value="1.0" oninput="hitungSemua()" class="w-full rounded-xl border border-slate-200 p-3 pr-16 text-base font-bold dark:bg-slate-900" placeholder="1.0">
                    <div class="absolute right-3 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        Kg
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="text-[11px] text-slate-400">Pilih Cepat:</span>
                    <button type="button" onclick="setBerat(1)" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 dark:bg-slate-800 dark:text-slate-300">1 Kg</button>
                    <button type="button" onclick="setBerat(2)" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 dark:bg-slate-800 dark:text-slate-300">2 Kg</button>
                    <button type="button" onclick="setBerat(3)" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 dark:bg-slate-800 dark:text-slate-300">3 Kg</button>
                    <button type="button" onclick="setBerat(5)" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 dark:bg-slate-800 dark:text-slate-300">5 Kg</button>
                    <button type="button" onclick="setBerat(10)" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 dark:bg-slate-800 dark:text-slate-300">10 Kg</button>
                </div>
                <?php if ($is_customer): ?>
                    <p class="mt-1.5 text-[11px] text-slate-500">💡 Masukkan perkiraan berat cucian Anda. Berat akhir & total biaya akan dikonfirmasi/ditimbang ulang secara pasti oleh outlet/kurir.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- SEKSI 3: ANTAR JEMPUT & DROPOFF -->
        <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800 space-y-4">
            <h2 class="text-base font-black text-slate-900 dark:text-white">🚗 Pilihan Pengiriman & Antar-Jemput</h2>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Tipe Pengiriman Cucian</label>
                <select id="select-pengiriman" name="tipe_layanan_pengiriman" onchange="toggleAntarJemput(); hitungSemua();" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                    <option value="Drop Point Outlet">🏢 Drop Point Outlet (Antar Langsung ke Toko - Rp 0)</option>
                    <option value="Antar-Jemput Kurir">🚗 Antar-Jemput Kurir (Dijemput Kurir ke Rumah)</option>
                </select>
            </div>

            <div id="container-antar-jemput" class="hidden space-y-4 border-t border-dashed pt-4 dark:border-slate-700">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">📍 Cari & Pin Lokasi di Peta</label>
                    <div class="flex gap-2 mb-2">
                        <input type="text" id="map-search-input" placeholder="Ketik nama jalan / lokasi / area..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:bg-slate-900">
                        <button type="button" onclick="searchLocationOnMap()" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-500 whitespace-nowrap">
                            🔍 Cari Lokasi
                        </button>
                    </div>

                    <!-- WIDGET MAP LEAFLET -->
                    <div id="map" class="h-64 w-full rounded-xl border border-slate-300 shadow-inner z-10"></div>
                    <p class="text-[11px] text-slate-500 mt-1">💡 Klik/geser marker pada peta di atas untuk menentukan titik penjemputan presisi.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Estimasi Jarak dari Outlet (Km)</label>
                        <input type="number" step="0.1" min="0.1" id="input-jarak" name="jarak_km" value="2.0" oninput="hitungSemua()" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                        <p class="text-[10px] text-slate-400 mt-1">≤3 km = Rp 5.000, >3 km = +Rp 1.000/km</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Jadwal Tanggal & Jam Pickup</label>
                        <input type="datetime-local" name="tanggal_pickup" value="<?= date('Y-m-d\TH:i'); ?>" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-semibold dark:bg-slate-900">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">🏡 Alamat Lengkap & Detail Patokan</label>
                    <textarea id="alamat_pickup" name="alamat_pickup" rows="3" class="w-full rounded-xl border border-slate-200 p-3 text-sm dark:bg-slate-900" placeholder="Alamat otomatis terisi dari peta. Tambahkan detail seperti: No. Rumah, Blok, Warna Pagar, atau Patokan..."></textarea>
                </div>
            </div>
        </div>

        <!-- SEKSI 4: REDEEM POIN (10 POIN = GRATIS 1 KG) -->
        <?php $can_claim_poin = $is_customer ? ($poin_pelanggan >= 10) : false; ?>
        <div class="rounded-2xl bg-cyan-50 border border-cyan-200 p-6 dark:bg-cyan-950/40 dark:border-cyan-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white">🎁 Klaim Poin Diskon Member</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">Setiap 10 Poin dapat ditukar dengan <strong>Gratis 1 Kg Cucian</strong>!</p>
                    <p id="text-warning-poin" class="text-[11px] font-bold text-rose-600 dark:text-rose-400 mt-1 <?= ($is_customer && $poin_pelanggan < 10) ? '' : 'hidden'; ?>">
                        ⚠️ Poin tidak mencukupi (Minimal 10 poin). Saldo poin saat ini: <?= $poin_pelanggan; ?> Poin.
                    </p>
                </div>
                <div class="text-right">
                    <label id="label-checkbox-poin" class="inline-flex items-center gap-2 font-bold text-sm <?= ($is_customer && !$can_claim_poin) ? 'opacity-50 cursor-not-allowed text-slate-400' : 'cursor-pointer text-cyan-700 dark:text-cyan-300'; ?>">
                        <input type="checkbox" id="check-use-poin" name="use_poin" value="1" <?= ($is_customer && !$can_claim_poin) ? 'disabled' : ''; ?> onchange="hitungSemua()" class="h-5 w-5 rounded text-cyan-600">
                        Tukarkan 10 Poin
                    </label>
                </div>
            </div>
        </div>

        <!-- SEKSI 5: RINCIAN BIAYA DETAIL & PEMBAYARAN -->
        <div id="seksi-pembayaran" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800 space-y-4">
            <h2 class="text-base font-black text-slate-900 dark:text-white">💳 Pembayaran & Rincian Biaya</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Metode Pembayaran</label>
                    <select name="metode_pembayaran" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                        <option value="Cash Outlet">💵 Cash di Outlet / Toko</option>
                        <option value="Cash Kurir (COD)">🚗 Cash ke Kurir saat Pickup (COD)</option>
                        <option value="QRIS">📱 QRIS / E-Wallet</option>
                        <option value="Transfer">🏦 Transfer Bank</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Status Bayar</label>
                    <select name="status_pembayaran" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-bold dark:bg-slate-900">
                        <option value="Belum Lunas">Belum Lunas (Bayar Nanti)</option>
                        <option value="Lunas">Lunas</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Catatan Pesanan (Opsional)</label>
                <textarea name="catatan" rows="2" class="w-full rounded-xl border border-slate-200 p-3 text-sm dark:bg-slate-900" placeholder="Pakaian dipisah, dll..."></textarea>
            </div>

            <!-- TABEL RINCIAN BIAYA (COST BREAKDOWN) -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50 space-y-2 text-xs">
                <h3 class="font-black text-slate-900 dark:text-white border-b pb-2 text-xs uppercase tracking-wider">📋 Rincian Perhitungan Biaya</h3>
                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                    <span id="detail-layanan-nama">Layanan Laundry:</span>
                    <span id="detail-layanan-harga" class="font-bold text-slate-900 dark:text-white">Rp 0</span>
                </div>
                <div id="row-detail-express" class="flex justify-between text-amber-600 dark:text-amber-400 hidden">
                    <span>Biaya Kecepatan Express (+Rp 2.000/kg):</span>
                    <span id="detail-express-harga" class="font-bold">Rp 0</span>
                </div>
                <div id="row-detail-ongkir" class="flex justify-between text-indigo-600 dark:text-indigo-400 hidden">
                    <span id="detail-ongkir-label">Biaya Antar-Jemput Kurir:</span>
                    <span id="detail-ongkir-harga" class="font-bold">Rp 0</span>
                </div>
                <div id="row-detail-diskon" class="flex justify-between text-emerald-600 dark:text-emerald-400 hidden font-bold">
                    <span>Diskon Poin Member (Gratis 1 Kg):</span>
                    <span id="detail-diskon-harga">-Rp 0</span>
                </div>
            </div>

            <!-- SUMMARY TOTAL FINAL -->
            <div class="rounded-xl bg-slate-900 p-4 text-white flex justify-between items-center mt-4">
                <div>
                    <span class="text-xs uppercase font-bold tracking-wider text-slate-400 block">Total Pembayaran:</span>
                    <span id="text-diskon-poin" class="text-xs text-emerald-400 font-bold hidden">🎉 Diskon Poin Berhasil Dipasang!</span>
                </div>
                <span id="display-total" class="text-2xl font-black text-cyan-400">Rp 0</span>
            </div>
        </div>

        <button type="submit" class="w-full rounded-2xl bg-cyan-500 py-4 text-base font-black text-slate-950 shadow-xl hover:bg-cyan-400 transition">
            🚀 Process Booking & Transaksi &rarr;
        </button>
    </form>
</div>

<script>
let map = null;
let marker = null;
// Koordinat Outlet: Universitas Trunojoyo Madura (UTM), Bangkalan, Jawa Timur
const OUTLET_LAT = -7.1276; 
const OUTLET_LNG = 112.7246;

function initMap() {
    if (map) return;
    
    // Inisialisasi peta berpusat di Universitas Trunojoyo Madura
    map = L.map('map').setView([OUTLET_LAT, OUTLET_LNG], 15);

    // Load tile layer OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Buat Marker Drag & Drop di UTM
    marker = L.marker([OUTLET_LAT, OUTLET_LNG], { draggable: true }).addTo(map);
    marker.bindPopup("<b>Outlet RAVF Laundry</b><br>Universitas Trunojoyo Madura").openPopup();

    // Event ketika marker digeser (dragend) atau peta diklik
    marker.on('dragend', function (e) {
        const coord = marker.getLatLng();
        onLocationSelected(coord.lat, coord.lng);
    });

    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        onLocationSelected(e.latlng.lat, e.latlng.lng);
    });
}

function onLocationSelected(lat, lng) {
    // Hitung estimasi jarak garis lurus dari outlet UTM (menggunakan rumus Haversine)
    const distanceKm = calculateHaversineDistance(OUTLET_LAT, OUTLET_LNG, lat, lng);
    const inputJarak = document.getElementById('input-jarak');
    if (inputJarak) {
        inputJarak.value = distanceKm.toFixed(1);
        hitungSemua();
    }

    // Reverse Geocoding menggunakan OpenStreetMap Nominatim API untuk mendapatkan nama jalan/alamat otomatis
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.display_name) {
                const textAlamat = document.getElementById('alamat_pickup');
                if (textAlamat) {
                    textAlamat.value = data.display_name + "\n\nPatokan / Detail Tambahan: ";
                }
            }
        })
        .catch(err => console.error("Reverse geocoding error:", err));
}

function searchLocationOnMap() {
    const query = document.getElementById('map-search-input').value;
    if (!query) return;

    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(results => {
            if (results && results.length > 0) {
                const firstResult = results[0];
                const lat = parseFloat(firstResult.lat);
                const lon = parseFloat(firstResult.lon);

                map.setView([lat, lon], 16);
                marker.setLatLng([lat, lon]);
                onLocationSelected(lat, lon);
            } else {
                alert("Lokasi tidak ditemukan. Coba ketik nama daerah atau jalan lain.");
            }
        })
        .catch(err => alert("Gagal mencari lokasi. Cek koneksi internet."));
}

function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius bumi dalam Km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function toggleAntarJemput() {
    const tipe = document.getElementById('select-pengiriman').value;
    const container = document.getElementById('container-antar-jemput');
    if (tipe === 'Antar-Jemput Kurir') {
        container.classList.remove('hidden');
        setTimeout(() => {
            initMap();
            map.invalidateSize();
        }, 200);
    } else {
        container.classList.add('hidden');
    }
}

function updateMemberInfo() {
    const selPelanggan = document.getElementById('select-pelanggan');
    if (!selPelanggan) return;

    const optSelected = selPelanggan.options[selPelanggan.selectedIndex];
    const poin = parseInt(optSelected?.dataset.poin || 0);

    const checkPoin = document.getElementById('check-use-poin');
    const labelPoin = document.getElementById('label-checkbox-poin');
    const textWarning = document.getElementById('text-warning-poin');

    // 1. Validasi Poin Promo Diskon
    if (poin < 10) {
        if (checkPoin) {
            checkPoin.checked = false;
            checkPoin.disabled = true;
        }
        if (labelPoin) {
            labelPoin.classList.add('opacity-50', 'cursor-not-allowed', 'text-slate-400');
            labelPoin.classList.remove('cursor-pointer', 'text-cyan-700', 'dark:text-cyan-300');
        }
        if (textWarning) {
            textWarning.textContent = `⚠️ Poin tidak mencukupi (Minimal 10 poin). Saldo poin saat ini: ${poin} Poin.`;
            textWarning.classList.remove('hidden');
        }
    } else {
        if (checkPoin) {
            checkPoin.disabled = false;
        }
        if (labelPoin) {
            labelPoin.classList.remove('opacity-50', 'cursor-not-allowed', 'text-slate-400');
            labelPoin.classList.add('cursor-pointer', 'text-cyan-700', 'dark:text-cyan-300');
        }
        if (textWarning) {
            textWarning.classList.add('hidden');
        }
    }

    // 2. Auto Sync Data Booking Online jika Pelanggan Memiliki Booking Aktif
    if (optSelected && optSelected.dataset.booking) {
        try {
            const booking = JSON.parse(optSelected.dataset.booking);
            if (booking.has_booking) {
                // Layanan
                if (booking.id_layanan > 0) {
                    const selLayanan = document.getElementById('select-layanan');
                    if (selLayanan) selLayanan.value = booking.id_layanan;
                }

                // Kecepatan Proses
                const radioKecepatan = document.querySelector(`input[name="kecepatan_proses"][value="${booking.kecepatan}"]`);
                if (radioKecepatan && !radioKecepatan.disabled) {
                    radioKecepatan.checked = true;
                }

                // Tipe Pengiriman
                const selPengiriman = document.getElementById('select-pengiriman');
                if (selPengiriman) {
                    selPengiriman.value = booking.pengiriman;
                    toggleAntarJemput();
                }

                // Jarak & Alamat Pickup
                if (booking.pengiriman === 'Antar-Jemput Kurir') {
                    const inputJarak = document.getElementById('input-jarak');
                    if (inputJarak && booking.jarak > 0) inputJarak.value = booking.jarak;

                    const textAlamat = document.getElementById('alamat_pickup');
                    if (textAlamat && booking.alamat) textAlamat.value = booking.alamat;
                }

                // Berat Cucian Estimasi Pelanggan
                if (booking.berat > 0) {
                    const inputBerat = document.getElementById('input-berat');
                    if (inputBerat) inputBerat.value = booking.berat.toFixed(1);
                }

                // Poin Promo
                if (checkPoin && !checkPoin.disabled) {
                    checkPoin.checked = booking.poin_ditukar > 0;
                }

                // Metode Pembayaran
                const selMetode = document.querySelector('select[name="metode_pembayaran"]');
                if (selMetode && booking.metode) {
                    selMetode.value = booking.metode;
                }
            }
        } catch (e) {
            console.error("Gagal parse data booking pelanggan:", e);
        }
    }

    if (optSelected && optSelected.value !== "") {
        const target = document.getElementById('seksi-pembayaran');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    hitungSemua();
}

function formatRupiah(val) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

function setBerat(val) {
    const input = document.getElementById('input-berat');
    if (input) {
        input.value = parseFloat(val).toFixed(1);
        hitungSemua();
    }
}

function hitungSemua() {
    const selLayanan = document.getElementById('select-layanan');
    const namaLayanan = selLayanan.options[selLayanan.selectedIndex]?.text.split('—')[0].trim() || 'Layanan Laundry';
    const hargaPerKg = parseFloat(selLayanan.options[selLayanan.selectedIndex]?.dataset.harga || 0);
    
    const isExpress = document.querySelector('input[name="kecepatan_proses"]:checked')?.value === 'Express';
    const inputBerat = document.getElementById('input-berat');
    const berat = parseFloat(inputBerat ? inputBerat.value : 0) || 0;
    
    const tipePengiriman = document.getElementById('select-pengiriman').value;
    const jarak = parseFloat(document.getElementById('input-jarak')?.value || 0);
    
    // 1. Biaya Utama Cucian
    let subtotalCucian = berat > 0 ? (berat * hargaPerKg) : hargaPerKg; // default 1 kg preview jika berat 0
    const textLayananHarga = berat > 0 
        ? `${berat} kg × ${formatRupiah(hargaPerKg)} = ${formatRupiah(subtotalCucian)}`
        : `${formatRupiah(hargaPerKg)} / kg (Menunggu Timbangan Kasir)`;
    
    document.getElementById('detail-layanan-nama').textContent = `Layanan (${namaLayanan}):`;
    document.getElementById('detail-layanan-harga').textContent = textLayananHarga;

    // 2. Biaya Express
    let expressFee = 0;
    const rowExpress = document.getElementById('row-detail-express');
    if (isExpress) {
        expressFee = berat > 0 ? (berat * 2000) : 2000;
        document.getElementById('detail-express-harga').textContent = formatRupiah(expressFee);
        rowExpress.classList.remove('hidden');
    } else {
        rowExpress.classList.add('hidden');
    }
    
    // 3. Biaya Antar-Jemput
    let ongkir = 0;
    const rowOngkir = document.getElementById('row-detail-ongkir');
    if (tipePengiriman === 'Antar-Jemput Kurir') {
        if (jarak <= 3.0) {
            ongkir = 5000;
        } else {
            ongkir = 5000 + ((jarak - 3.0) * 1000);
        }
        document.getElementById('detail-ongkir-label').textContent = `Antar-Jemput Kurir (${jarak.toFixed(1)} km):`;
        document.getElementById('detail-ongkir-harga').textContent = formatRupiah(ongkir);
        rowOngkir.classList.remove('hidden');
    } else {
        rowOngkir.classList.add('hidden');
    }

    let totalHarga = (berat > 0 ? (berat * hargaPerKg) : 0) + expressFee + ongkir;
    
    // 4. Redeem Poin Check
    const checkPoin = document.getElementById('check-use-poin');
    const textDiskon = document.getElementById('text-diskon-poin');
    const rowDiskon = document.getElementById('row-detail-diskon');

    // Jika checkbox disabled (misal poin under 10), pastikan uncheck
    if (checkPoin && checkPoin.disabled) {
        checkPoin.checked = false;
    }

    if (checkPoin && checkPoin.checked) {
        totalHarga -= hargaPerKg; // Diskon Gratis 1 Kg
        document.getElementById('detail-diskon-harga').textContent = `-${formatRupiah(hargaPerKg)}`;
        rowDiskon.classList.remove('hidden');
        if (textDiskon) textDiskon.classList.remove('hidden');
    } else {
        rowDiskon.classList.add('hidden');
        if (textDiskon) textDiskon.classList.add('hidden');
    }

    if (totalHarga < 0) totalHarga = 0;

    document.getElementById('display-total').textContent = formatRupiah(totalHarga);
}

// Jalankan kalkulasi & map saat load pertama
document.addEventListener("DOMContentLoaded", function() {
    toggleAntarJemput();
    hitungSemua();
});
</script>
<?php app_end(); ?>
