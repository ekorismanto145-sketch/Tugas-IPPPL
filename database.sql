-- RESET TOTAL DATABASE LAUNDRY ECOSMART
DROP DATABASE IF EXISTS laundry_ecosmart;
CREATE DATABASE laundry_ecosmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_ecosmart;

-- 1. TABEL USERS (ADMIN & STAFF KASIR/KURIR)
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
    no_hp VARCHAR(20) NULL,
    status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    foto_profil VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. TABEL PELANGGAN (MEMBER & PENGGUNA)
CREATE TABLE pelanggan (
    id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NULL,
    password VARCHAR(255) NULL,
    no_hp VARCHAR(20) NULL UNIQUE,
    alamat VARCHAR(255) NULL,
    poin_bonus INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABEL LAYANAN LAUNDRY
CREATE TABLE layanan_laundry (
    id_layanan INT AUTO_INCREMENT PRIMARY KEY,
    nama_layanan VARCHAR(100) NOT NULL,
    kategori VARCHAR(50) DEFAULT 'Kiloan',
    satuan VARCHAR(20) DEFAULT 'Kg',
    harga_per_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    estimasi_hari INT NOT NULL DEFAULT 2,
    deskripsi VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TABEL PENGATURAN KONTROL OVERLOAD & KUOTA (SETTINGS)
CREATE TABLE pengaturans (
    id_setting INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(50) NOT NULL UNIQUE,
    val_value VARCHAR(255) NOT NULL,
    keterangan VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. TABEL TRANSAKSI UTAMA
CREATE TABLE transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) NOT NULL UNIQUE,
    id_pelanggan INT NOT NULL,
    id_user INT NULL, -- Staf/Kasir yang memproses
    tanggal_transaksi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- PENGIRIMAN & PENJEMPUTAN
    tipe_layanan_pengiriman ENUM('Drop Point Outlet', 'Antar-Jemput Kurir') NOT NULL DEFAULT 'Drop Point Outlet',
    kecepatan_proses ENUM('Regular', 'Express') NOT NULL DEFAULT 'Regular',
    tanggal_pickup DATETIME NULL,
    alamat_pickup TEXT NULL,
    jarak_km DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    biaya_antar_jemput DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    biaya_express DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    
    -- TIMBANGAN, REDEEM POIN & TOTAL HARGA
    berat_cucian DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    poin_ditukar INT NOT NULL DEFAULT 0,
    diskon_poin DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_harga DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    
    -- METODE PEMBAYARAN FLEXIBLE
    metode_pembayaran ENUM('Cash Outlet', 'Cash Kurir (COD)', 'QRIS', 'Transfer', 'Debit') NOT NULL DEFAULT 'Cash Outlet',
    status_pembayaran ENUM('Belum Lunas', 'Lunas') NOT NULL DEFAULT 'Belum Lunas',
    waktu_pembayaran DATETIME NULL,
    
    -- STATUS OPERASIONAL
    status_cucian ENUM(
        'Booking Masuk', 
        'Penjemputan Kurir', 
        'Menunggu Timbang', 
        'Dalam Proses', 
        'Selesai', 
        'Siap Diambil/Diantar', 
        'Sudah Selesai & Diambil'
    ) NOT NULL DEFAULT 'Menunggu Timbang',
    
    tanggal_selesai DATE NULL,
    catatan TEXT NULL,
    
    CONSTRAINT fk_transaksi_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE,
    CONSTRAINT fk_transaksi_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. TABEL DETAIL TRANSAKSI
CREATE TABLE detail_transaksi (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_layanan INT NOT NULL,
    jumlah DECIMAL(5,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detail_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
    CONSTRAINT fk_detail_layanan FOREIGN KEY (id_layanan) REFERENCES layanan_laundry(id_layanan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. TABEL LOG POIN
CREATE TABLE riwayat_poin (
    id_poin INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT NOT NULL,
    id_transaksi INT NULL,
    tipe ENUM('Masuk', 'Keluar') NOT NULL,
    jumlah_poin INT NOT NULL,
    keterangan VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_poin_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE,
    CONSTRAINT fk_poin_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. TABEL ULASAN / RATING
CREATE TABLE rating_laundry (
    id_rating INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT NULL,
    id_transaksi INT NULL,
    nama_reviewer VARCHAR(100) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    komentar VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rating_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE SET NULL,
    CONSTRAINT fk_rating_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DATA SEEDER DEFAULT (AKUN SYSTEM & JENIS LAYANAN)
INSERT INTO users (nama, username, password, role) VALUES 
('Administrator Utama', 'admin', '$2y$10$cqVnfBkypZVaGGkRFVv0vOIB2ZypNU46pOdzmKTusW1lGksIpb08S', 'Admin'),
('Staf Kasir 1', 'kasir1', '$2y$10$cqVnfBkypZVaGGkRFVv0vOIB2ZypNU46pOdzmKTusW1lGksIpb08S', 'Staff');

INSERT INTO layanan_laundry (nama_layanan, harga_per_kg, estimasi_hari, deskripsi) VALUES 
('Cuci Basah', 4000.00, 2, 'Cuci bersih basah tanpa pengeringan & setrika'),
('Cuci Kering', 5000.00, 2, 'Cuci bersih dan pengeringan mesin'),
('Cuci Kering Lipat', 7000.00, 2, 'Cuci bersih, kering, dan dilipat rapi'),
('Cuci Setrika', 10000.00, 2, 'Layanan komplit cuci bersih, kering, dan setrika rapi');

INSERT INTO pengaturans (key_name, val_value, keterangan) VALUES
('max_express_per_day', '10', 'Batas maksimal pesanan Express per hari sebelum dianggap Overload'),
('express_status', 'Available', 'Status ketersediaan Express (Available / Overload)');
