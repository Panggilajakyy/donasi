-- -------------------------------------------------------------
-- SKEMA DATABASE: DIMZZ DONASI
-- Siap diimpor di phpMyAdmin atau MySQL Server
-- -------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- --------------------------------------------------------
-- 1. Tabel users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin Default (Password: admin123)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `role`) VALUES
('Administrator Dimzz', 'admin@dimzz.id', '081234567890', 'admin123', 'admin')
ON DUPLICATE KEY UPDATE `password`=VALUES(`password`), `role`='admin';

-- --------------------------------------------------------
-- 2. Tabel kategori_donasi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategori_donasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon_svg` TEXT DEFAULT NULL,
  `warna_badge` VARCHAR(30) DEFAULT '#20b2aa',
  `halaman_url` VARCHAR(150) DEFAULT NULL,
  `urutan` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `kategori_donasi` (`id`, `nama_kategori`, `slug`, `icon_svg`, `warna_badge`, `halaman_url`, `urutan`, `is_active`) VALUES
(1, 'Yatim & Dhuafa', 'yatim', '<svg fill=\"none\" stroke=\"white\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/></svg>', '#ef4444', 'donasiyatim.php', 1, 1),
(2, 'Pendidikan', 'pendidikan', '<svg fill=\"none\" stroke=\"white\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M4 19.5A2.5 2.5 0 0 1 6.5 17H20\"/><path d=\"M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z\"/></svg>', '#10b981', 'donasipendidikan.php', 2, 1),
(3, 'Pemberdayaan', 'pemberdayaan', '<svg fill=\"none\" stroke=\"white\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><line x1=\"12\" y1=\"20\" x2=\"12\" y2=\"10\"/><line x1=\"18\" y1=\"20\" x2=\"18\" y2=\"4\"/><line x1=\"6\" y1=\"20\" x2=\"6\" y2=\"16\"/></svg>', '#20b2aa', 'donasipemberdayaan.php', 3, 1),
(4, 'Kesehatan', 'kesehatan', '<svg fill=\"none\" stroke=\"white\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M22 12h-4l-3 9L9 3l-3 9H2\"/></svg>', '#06b6d4', 'donasikesehatan.php', 4, 1),
(5, 'Darurat Bencana', 'darurat', '<svg fill=\"none\" stroke=\"white\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><polygon points=\"13 2 3 14 12 14 11 22 21 10 12 10 13 2\"/></svg>', '#f59e0b', 'donasidarurat.php', 5, 1)
ON DUPLICATE KEY UPDATE `icon_svg`=VALUES(`icon_svg`), `nama_kategori`=VALUES(`nama_kategori`);

-- --------------------------------------------------------
-- 3. Tabel program_donasi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `program_donasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori_id` INT DEFAULT NULL,
  `judul_program` VARCHAR(255) NOT NULL,
  `judul_banner` VARCHAR(150) DEFAULT NULL,
  `deskripsi_singkat` TEXT DEFAULT NULL,
  `deskripsi` LONGTEXT DEFAULT NULL,
  `gambar_banner` VARCHAR(255) DEFAULT NULL,
  `gambar_thumbnail` VARCHAR(255) DEFAULT NULL,
  `target_donasi` DECIMAL(15,2) DEFAULT 0.00,
  `donasi_terkumpul` DECIMAL(15,2) DEFAULT 0.00,
  `jumlah_donatur` INT DEFAULT 0,
  `sisa_hari` INT DEFAULT 30,
  `progress_percent` INT DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `urutan` INT DEFAULT 0,
  `status` ENUM('aktif', 'selesai', 'draft') DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`kategori_id`) REFERENCES `kategori_donasi`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `program_donasi` (`id`, `kategori_id`, `judul_program`, `judul_banner`, `deskripsi_singkat`, `gambar_banner`, `target_donasi`, `donasi_terkumpul`, `jumlah_donatur`, `sisa_hari`, `progress_percent`, `is_featured`, `urutan`, `status`) VALUES
(1, 1, 'Sempurnakan Ramadhan dengan Kebaikan untuk Palestina', 'Ramadhan Palestina', 'Salurkan paket pangan sahur & berbuka serta bantuan medis bagi saudara-saudara kita di Palestina.', 'donasiyatim.png', 300000000.00, 201000000.00, 1820, 21, 67, 1, 1, 'aktif'),
(2, 5, 'Bantu Korban Banjir Pekalongan Bangkit Kembali', 'Banjir Pekalongan', 'Ribuan warga terdampak banjir di Pekalongan memerlukan bantuan pangan, air bersih, dan logistik evakuasi darurat.', 'donasi.jpeg', 1200000000.00, 84220155.00, 3362, 14, 42, 1, 2, 'aktif'),
(3, 2, 'Beasiswa Pendidikan Anak Yatim & Dhuafa 2026', 'Beasiswa Pendidikan', 'Wujudkan mimpi anak-anak dhuafa berprestasi untuk terus mengenyam bangku sekolah hingga tuntas.', 'donasipendidikan.png', 600000000.00, 534000000.00, 4100, 8, 89, 1, 3, 'aktif'),
(4, 3, 'Penyediaan Sumur & Pipanisasi Air Bersih Desa Terpencil NTT', 'Air Bersih NTT', 'Alirkan sumber air bersih ke pemukiman warga desa pelosok NTT yang dilanda kekeringan ekstrem.', 'donasipemberdayaan.png', 250000000.00, 132000000.00, 980, 35, 54, 1, 4, 'aktif'),
(5, 4, 'Layanan Pengobatan Gratis & Ambulans untuk Kaum Dhuafa', 'Kesehatan Dhuafa', 'Bantuan biaya operasi, obat-obatan, dan operasional ambulans gratis bagi keluarga prasejahtera.', 'donasikesehatan.png', 150000000.00, 47000000.00, 412, 45, 31, 1, 5, 'aktif')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- --------------------------------------------------------
-- 4. Tabel donations (Transaksi Donasi)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `program_id` INT DEFAULT NULL,
  `campaign_id` VARCHAR(50) DEFAULT NULL,
  `invoice_no` VARCHAR(60) NOT NULL UNIQUE,
  `campaign_name` VARCHAR(255) DEFAULT NULL,
  `donator_name` VARCHAR(150) NOT NULL,
  `donator_phone` VARCHAR(30) NOT NULL,
  `donator_email` VARCHAR(150) DEFAULT NULL,
  `sapaan` VARCHAR(30) DEFAULT 'Bapak/Ibu',
  `is_anonymous` TINYINT(1) DEFAULT 0,
  `campaign_img` VARCHAR(255) DEFAULT 'donasi.jpeg',
  `nominal_donation` DECIMAL(15,2) NOT NULL,
  `admin_fee` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'expired', 'failed') DEFAULT 'pending',
  `payment_expiry` DATETIME DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `doa_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`program_id`) REFERENCES `program_donasi`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Tabel doa_comments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `doa_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `program_id` INT DEFAULT NULL,
  `campaign_id` VARCHAR(50) DEFAULT NULL,
  `donator_name` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `aamiin_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`program_id`) REFERENCES `program_donasi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Tabel penyaluran_dana (LPJ Transparansi)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penyaluran_dana` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `program_id` INT NOT NULL,
  `campaign_name` VARCHAR(255) NOT NULL,
  `nominal_disalurkan` DECIMAL(15,2) NOT NULL,
  `tanggal_penyaluran` DATE NOT NULL,
  `lokasi_penerima` VARCHAR(255) NOT NULL,
  `jumlah_penerima` VARCHAR(100) DEFAULT NULL,
  `deskripsi` TEXT NOT NULL,
  `foto_bukti` VARCHAR(255) DEFAULT 'bantuan.png',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Tabel pengumuman
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengumuman` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(200) NOT NULL,
  `isi` TEXT NOT NULL,
  `tipe` ENUM('hadist', 'pengumuman', 'quote') DEFAULT 'pengumuman',
  `sumber` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Tabel payment_methods
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `method_code` VARCHAR(50) NOT NULL UNIQUE,
  `method_name` VARCHAR(100) NOT NULL,
  `admin_fee` DECIMAL(10,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_methods` (`method_code`, `method_name`, `admin_fee`, `is_active`) VALUES
('qris', 'QRIS Instan', 0.00, 1),
('bca', 'BCA Virtual Account', 0.00, 1),
('bri', 'BRI Virtual Account', 0.00, 1),
('mandiri', 'Mandiri Virtual Account', 0.00, 1),
('gopay', 'GoPay', 0.00, 1),
('ovo', 'OVO', 0.00, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
