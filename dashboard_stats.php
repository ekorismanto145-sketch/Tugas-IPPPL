<?php
require_once 'auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
if (!is_staff()) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses hanya untuk Admin dan Kasir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stats = [
    'menunggu' => 0,
    'proses' => 0,
    'selesai' => 0,
    'diambil' => 0,
    'total' => 0,
    'omset' => 0,
    'persentase' => 0,
    'aktif' => false,
];

$query = mysqli_query($koneksi, "SELECT status_cucian, COUNT(*) AS total, COALESCE(SUM(total_harga), 0) AS omset FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE() GROUP BY status_cucian");
while ($row = mysqli_fetch_assoc($query)) {
    $count = (int) $row['total'];
    $stats['total'] += $count;
    $stats['omset'] += (float) $row['omset'];
    if (in_array($row['status_cucian'], ['Booking Masuk', 'Penjemputan Kurir', 'Menunggu Timbang'])) $stats['menunggu'] += $count;
    if ($row['status_cucian'] === 'Dalam Proses') $stats['proses'] += $count;
    if ($row['status_cucian'] === 'Selesai') $stats['selesai'] += $count;
    if (in_array($row['status_cucian'], ['Siap Diambil/Diantar', 'Sudah Selesai & Diambil'])) $stats['diambil'] += $count;
}

$completed = $stats['selesai'] + $stats['diambil'];
$stats['persentase'] = $stats['total'] > 0 ? (int) round(($completed / $stats['total']) * 100) : 0;
$stats['aktif'] = ($stats['menunggu'] + $stats['proses']) > 0;
echo json_encode($stats, JSON_UNESCAPED_UNICODE);
