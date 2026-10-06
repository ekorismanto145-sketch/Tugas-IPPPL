<?php
require_once 'auth.php';
require_admin();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = mysqli_prepare($koneksi, "
    SELECT t.kode_transaksi, t.tanggal_transaksi, p.id_pelanggan, p.nama AS nama_pelanggan, p.no_hp,
           t.tipe_layanan_pengiriman, t.kecepatan_proses, t.berat_cucian, t.diskon_poin, t.total_harga,
           t.metode_pembayaran, t.status_pembayaran, t.status_cucian
    FROM transaksi t
    JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE DATE(t.tanggal_transaksi) BETWEEN ? AND ?
    ORDER BY t.id_transaksi DESC
");
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$filename = "Laporan_Keuangan_RAVF_Laundry_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM untuk Excel kompatibilitas UTF-8
fputs($output, "\xEF\xBB\xBF");

// Header Kolom CSV
fputcsv($output, [
    'No. Nota', 
    'Tanggal Transaksi', 
    'ID Pelanggan', 
    'Nama Pelanggan', 
    'No. HP', 
    'Tipe Pengiriman', 
    'Kecepatan', 
    'Berat (Kg)', 
    'Diskon Poin (Rp)', 
    'Total Harga (Rp)', 
    'Metode Bayar', 
    'Status Bayar', 
    'Status Cucian'
]);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['kode_transaksi'],
        $row['tanggal_transaksi'],
        format_pelanggan_id((int)$row['id_pelanggan']),
        $row['nama_pelanggan'],
        $row['no_hp'] ?: '-',
        $row['tipe_layanan_pengiriman'],
        $row['kecepatan_proses'],
        $row['berat_cucian'],
        $row['diskon_poin'],
        $row['total_harga'],
        $row['metode_pembayaran'],
        $row['status_pembayaran'],
        $row['status_cucian']
    ]);
}

fclose($output);
exit;
