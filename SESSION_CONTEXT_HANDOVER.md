# 📋 Handover Context & Summary - RAVF Laundry System

**Tanggal Sesi:** 29 September 2026  
**Proyek:** RAVF Laundry System (PHP Native + MySQL + Tailwind CSS + Leaflet.js)  
**Database Name:** `laundry_ecosmart`  

---

## 📑 Rangkuman Penyesuaian Utama

Sistem telah di-refactor secara menyeluruh dari struktur legacy menjadi sistem modern dengan 3 role terproteksi (*Admin*, *Staff*, *User*) dan skema basis data baru yang bersih.

---

## 🗄️ 1. Skema Database Baru (`laundry_ecosmart`)

Database terdiri dari **8 Tabel Utama yang Terstruktur**:

1. **`users`**: Mengelola otentikasi internal (`id_user`, `nama`, `username`, `password`, `role` (`Admin`/`Staff`), `no_hp`, `status`).
2. **`pelanggan`**: Mengelola member pelanggan (`id_pelanggan`, `nama`, `username`, `password`, `no_hp`, `alamat`, `poin_bonus`).
3. **`layanan_laundry`**: Master tarif layanan:
   - *Cuci Basah* (Rp 4.000 / Kg)
   - *Cuci Kering* (Rp 5.000 / Kg)
   - *Cuci Kering Lipat* (Rp 7.000 / Kg)
   - *Cuci Setrika* (Rp 10.000 / Kg)
4. **`transaksi`**:
   - `tipe_layanan_pengiriman`: `Drop Point Outlet` / `Antar-Jemput Kurir`
   - `kecepatan_proses`: `Regular` (2-3 hari) / `Express` (12 jam-1 hari, +Rp 2.000/kg)
   - `jarak_km`, `alamat_pickup`, `tanggal_pickup`, `biaya_antar_jemput`, `biaya_express`
   - `poin_ditukar`, `diskon_poin`, `total_harga`
   - `status_pembayaran` (`Belum Lunas`, `Lunas`), `metode_pembayaran` (`Cash Outlet`, `Cash Kurir (COD)`, `QRIS`, `Transfer`, `Debit`)
   - `status_cucian` (`Booking Masuk`, `Penjemputan Kurir`, `Menunggu Timbang`, `Dalam Proses`, `Selesai`, `Siap Diambil/Diantar`, `Sudah Selesai & Diambil`)
5. **`detail_transaksi`**: Rincian item per transaksi.
6. **`riwayat_poin`**: Log audit pergerakan poin (`tipe`: `Masuk` / `Keluar`).
7. **`rating_laundry`**: Penampung ulasan & rating bintang (1-5) dari pelanggan.
8. **`pengaturans`**: Pengaturan batas kuota Express per hari (`max_express_per_day`, `express_status`).

---

## 🎁 2. Aturan Loyalty Poin Member

- **Pengumpulan Poin (*Collect*)**: Setiap transaksi kelipatan **Rp 5.000** yang berstatus **Lunas** otomatis memberikan **+1 Poin Bonus**.
- **Penukaran Poin (*Redeem*)**: **Every 10 Poin = Gratis 1 Kg Cucian**. Saat checkout, jika saldo poin $\ge 10$, pengguna/kasir bisa mencentang *"Tukarkan 10 Poin"* untuk memotong seharga 1 Kg layanan.

---

## 🚗 3. Layanan Antar-Jemput & Peta Interactive

- **Rumus Ongkir**:
  $$\text{Biaya} = \begin{cases} 0 & \text{Drop Point} \\ 5000 & \text{Antar-Jemput } \le 3\text{ km} \\ 5000 + (\text{Jarak} - 3) \times 1000 & \text{Antar-Jemput } > 3\text{ km} \end{cases}$$
- **Peta Interactive (Leaflet.js & OpenStreetMap)**:
  - Tersedia **Search Bar** untuk mencari lokasi/jalan.
  - Marker peta dapat di-click / drag & drop.
  - Alamat jalan otomatis terisi via *Reverse Geocoding (Nominatim API)*.
  - Tersedia **Kolom Alamat Detail & Patokan** untuk rincian seperti blok/nomor rumah.

---

## 🚨 4. Guard Express Overload Control

- Apabila jumlah pesanan `Express` pada hari berjalan sudah $\ge 10$ transaksi (atau setting `express_status = 'Overload'`), maka di form booking/kasir opsi **Express** otomatis di-disabled dengan indikator **OVERLOAD / PENUH**.

---

## 🛡️ 5. Hak Akses & Keamanan URL Guard

- **Middleware `auth.php` & `app_layout.php`**:
  - `require_admin()`: Mengunci halaman `laporan.php` & `kelola_karyawan.php` khusus `Admin`.
  - `require_staff()`: Mengunci halaman operasional kasir untuk `Staff` & `Admin`.
  - `require_user()`: Mengunci portal pelanggan `customer_dashboard.php`.
  - Whitelist Pelanggan: `customer_dashboard.php`, `transaksi_baru.php` (Booking), `history_transaksi.php` (Riwayat Cucian), dan `profile.php`.

---

## 🔑 6. Akun Pengujian System Default

- **Admin Utama**: Username: `admin` | Password: `password`
- **Staf Kasir**: Username: `kasir1` | Password: `password`
- **Member Pelanggan**: Registrasi mandiri via `register.php`.

---

## 📁 File Utama Proyek

- `koneksi.php`: Koneksi database PDO/mysqli terpusat.
- `auth.php`: Otorisasi & guard keamanan URL.
- `app_layout.php`: Master template header, sidebar, dark mode & CDN (Leaflet).
- `login.php`: Authentikasi login multi-tabel (`users` & `pelanggan`).
- `register.php`: Form pendaftaran member pelanggan.
- `customer_dashboard.php`: Dashboard portal pelanggan (poin, status, form ulasan).
- `dashboard.php`: Dashboard operasional kasir & admin.
- `transaksi_baru.php`: Booking online & kasir POS dengan Peta Interactive.
- `history_transaksi.php`: Daftar transaksi, status cucian, & pembagian poin.
- `laporan.php`: Rekapitulasi keuangan Mingguan/Bulanan/Tahunan + Header PDF.
- `kelola_karyawan.php`: Kelola Staf & Pelanggan (Tab dual table).
- `nota.php`: Cetak nota resmi & tombol bagikan link.
- `database.sql`: Skrip master reset database.
