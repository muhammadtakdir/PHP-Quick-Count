-- ============================================
-- Quick Count Database Schema
-- Simplified & Modern Structure
-- Mendukung: Pilpres, Pilgub, Pilbup/Pilwalkot
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- Drop existing tables if any
DROP TABLE IF EXISTS `suara`;
DROP TABLE IF EXISTS `calon`;
DROP TABLE IF EXISTS `tps`;
DROP TABLE IF EXISTS `desa`;
DROP TABLE IF EXISTS `kecamatan`;
DROP TABLE IF EXISTS `kabupaten`;
DROP TABLE IF EXISTS `provinsi`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- ============================================
-- Tabel Settings - Konfigurasi Sistem
-- ============================================
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pemilihan` varchar(255) NOT NULL DEFAULT 'Quick Count Pemilu',
  `jenis_pemilihan` enum('pilpres','pilgub','pilbup','pilwalkot') NOT NULL DEFAULT 'pilbup',
  `tingkat_wilayah` enum('nasional','provinsi','kabupaten') NOT NULL DEFAULT 'kabupaten',
  `id_provinsi_aktif` int(11) DEFAULT NULL COMMENT 'NULL = semua provinsi',
  `id_kabupaten_aktif` int(11) DEFAULT NULL COMMENT 'NULL = semua kabupaten',
  `tahun_pemilihan` year(4) NOT NULL DEFAULT 2024,
  `logo` varchar(255) DEFAULT 'logo.png',
  `warna_tema` varchar(7) DEFAULT '#0d6efd',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default settings
INSERT INTO `settings` (`nama_pemilihan`, `jenis_pemilihan`, `tingkat_wilayah`, `tahun_pemilihan`) VALUES 
('Quick Count Pemilukada 2024', 'pilbup', 'kabupaten', 2024);

-- ============================================
-- Tabel Users - Pengguna Sistem
-- ============================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `role` enum('admin','operator','viewer') NOT NULL DEFAULT 'operator',
  `foto` varchar(255) DEFAULT 'default.png',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user (password: admin123)
INSERT INTO `users` (`username`, `password`, `nama_lengkap`, `role`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- ============================================
-- Tabel Provinsi
-- ============================================
CREATE TABLE `provinsi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel Kabupaten/Kota
-- ============================================
CREATE TABLE `kabupaten` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_provinsi` int(11) NOT NULL,
  `kode` varchar(10) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `tipe` enum('kabupaten','kota') NOT NULL DEFAULT 'kabupaten',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_provinsi` (`id_provinsi`),
  KEY `idx_kode` (`kode`),
  FOREIGN KEY (`id_provinsi`) REFERENCES `provinsi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel Kecamatan
-- ============================================
CREATE TABLE `kecamatan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kabupaten` int(11) NOT NULL,
  `kode` varchar(10) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kabupaten` (`id_kabupaten`),
  KEY `idx_kode` (`kode`),
  FOREIGN KEY (`id_kabupaten`) REFERENCES `kabupaten`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel Desa/Kelurahan
-- ============================================
CREATE TABLE `desa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kecamatan` int(11) NOT NULL,
  `kode` varchar(10) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `tipe` enum('desa','kelurahan') NOT NULL DEFAULT 'desa',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kecamatan` (`id_kecamatan`),
  KEY `idx_kode` (`kode`),
  FOREIGN KEY (`id_kecamatan`) REFERENCES `kecamatan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel TPS
-- ============================================
CREATE TABLE `tps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_desa` int(11) NOT NULL,
  `nomor_tps` int(11) NOT NULL,
  `dpt` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah DPT',
  `alamat` text DEFAULT NULL,
  `koordinat_lat` decimal(10,8) DEFAULT NULL,
  `koordinat_lng` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tps` (`id_desa`, `nomor_tps`),
  KEY `idx_desa` (`id_desa`),
  FOREIGN KEY (`id_desa`) REFERENCES `desa`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel Calon
-- ============================================
CREATE TABLE `calon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_urut` int(11) NOT NULL,
  `nama_calon` varchar(100) NOT NULL,
  `nama_wakil` varchar(100) DEFAULT NULL,
  `foto_calon` varchar(255) DEFAULT 'default_calon.png',
  `foto_wakil` varchar(255) DEFAULT 'default_wakil.png',
  `partai` varchar(255) DEFAULT NULL COMMENT 'Partai pengusung',
  `visi` text DEFAULT NULL,
  `misi` text DEFAULT NULL,
  `warna` varchar(7) DEFAULT '#6c757d' COMMENT 'Warna untuk grafik',
  `jenis_pemilihan` enum('pilpres','pilgub','pilbup','pilwalkot') NOT NULL DEFAULT 'pilbup',
  `id_provinsi` int(11) DEFAULT NULL COMMENT 'NULL untuk pilpres',
  `id_kabupaten` int(11) DEFAULT NULL COMMENT 'NULL untuk pilpres/pilgub',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jenis` (`jenis_pemilihan`),
  KEY `idx_provinsi` (`id_provinsi`),
  KEY `idx_kabupaten` (`id_kabupaten`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel Suara - Hasil Quick Count per TPS
-- ============================================
CREATE TABLE `suara` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tps` int(11) NOT NULL,
  `id_calon` int(11) NOT NULL,
  `jumlah_suara` int(11) NOT NULL DEFAULT 0,
  `suara_sah` int(11) NOT NULL DEFAULT 0,
  `suara_tidak_sah` int(11) NOT NULL DEFAULT 0,
  `foto_c1` varchar(255) DEFAULT NULL COMMENT 'Foto formulir C1',
  `catatan` text DEFAULT NULL,
  `input_by` int(11) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_suara` (`id_tps`, `id_calon`),
  KEY `idx_tps` (`id_tps`),
  KEY `idx_calon` (`id_calon`),
  FOREIGN KEY (`id_tps`) REFERENCES `tps`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_calon`) REFERENCES `calon`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`input_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Sample Data - Provinsi
-- ============================================
INSERT INTO `provinsi` (`kode`, `nama`) VALUES
('73', 'Sulawesi Selatan'),
('72', 'Sulawesi Tengah'),
('74', 'Sulawesi Tenggara'),
('31', 'DKI Jakarta'),
('32', 'Jawa Barat'),
('33', 'Jawa Tengah'),
('34', 'DI Yogyakarta'),
('35', 'Jawa Timur');

-- ============================================
-- Sample Data - Kabupaten (Sulawesi Selatan)
-- ============================================
INSERT INTO `kabupaten` (`id_provinsi`, `kode`, `nama`, `tipe`) VALUES
(1, '7311', 'Bone', 'kabupaten'),
(1, '7301', 'Bantaeng', 'kabupaten'),
(1, '7302', 'Barru', 'kabupaten'),
(1, '7303', 'Bulukumba', 'kabupaten'),
(1, '7304', 'Enrekang', 'kabupaten'),
(1, '7305', 'Gowa', 'kabupaten'),
(1, '7371', 'Makassar', 'kota'),
(1, '7372', 'Parepare', 'kota');

-- ============================================
-- Sample Data - Kecamatan (Bone)
-- ============================================
INSERT INTO `kecamatan` (`id_kabupaten`, `kode`, `nama`) VALUES
(1, '731101', 'Ajangale'),
(1, '731102', 'Amali'),
(1, '731103', 'Awangpone'),
(1, '731104', 'Barebbo'),
(1, '731105', 'Bengo'),
(1, '731106', 'Bontocani'),
(1, '731107', 'Cenrana'),
(1, '731108', 'Cina'),
(1, '731109', 'Dua Boccoe'),
(1, '731110', 'Kajuara'),
(1, '731111', 'Kahu'),
(1, '731112', 'Lamuru'),
(1, '731113', 'Lappariaja'),
(1, '731114', 'Libureng'),
(1, '731115', 'Mare'),
(1, '731116', 'Palakka'),
(1, '731117', 'Patimpeng'),
(1, '731118', 'Ponre'),
(1, '731119', 'Salomekko'),
(1, '731120', 'Sibulue'),
(1, '731121', 'Tanete Riattang'),
(1, '731122', 'Tanete Riattang Barat'),
(1, '731123', 'Tanete Riattang Timur'),
(1, '731124', 'Tellu Limpoe'),
(1, '731125', 'Tellu Siattinge'),
(1, '731126', 'Tonra'),
(1, '731127', 'Ulaweng');

-- ============================================
-- Sample Data - Desa (Kecamatan Tanete Riattang)
-- ============================================
INSERT INTO `desa` (`id_kecamatan`, `kode`, `nama`, `tipe`) VALUES
(21, '7311210001', 'Watampone', 'kelurahan'),
(21, '7311210002', 'Masumpu', 'kelurahan'),
(21, '7311210003', 'Macanang', 'kelurahan'),
(21, '7311210004', 'Manurunge', 'kelurahan'),
(21, '7311210005', 'Bukaka', 'kelurahan'),
(21, '7311210006', 'Ta', 'kelurahan'),
(21, '7311210007', 'Biru', 'kelurahan'),
(21, '7311210008', 'Jeppee', 'kelurahan');

-- ============================================
-- Sample Data - TPS
-- ============================================
INSERT INTO `tps` (`id_desa`, `nomor_tps`, `dpt`) VALUES
(1, 1, 250),
(1, 2, 275),
(1, 3, 300),
(1, 4, 280),
(1, 5, 265),
(2, 1, 230),
(2, 2, 245),
(2, 3, 260);

-- ============================================
-- Sample Data - Calon Bupati
-- ============================================
INSERT INTO `calon` (`nomor_urut`, `nama_calon`, `nama_wakil`, `partai`, `warna`, `jenis_pemilihan`, `id_provinsi`, `id_kabupaten`) VALUES
(1, 'H. Ahmad Syarifuddin', 'Ir. Muhammad Yusuf', 'Golkar, PDI-P', '#ffc107', 'pilbup', 1, 1),
(2, 'Dr. Andi Fahsar', 'Drs. H. Ambo Dalle', 'Gerindra, PKS', '#dc3545', 'pilbup', 1, 1),
(3, 'H. Andi Mudzakkar', 'Ir. H. Sudirman', 'Nasdem, PKB', '#198754', 'pilbup', 1, 1);

-- ============================================
-- Views untuk kemudahan query
-- ============================================

-- View rekap suara per TPS
CREATE OR REPLACE VIEW `v_rekap_tps` AS
SELECT 
    t.id as id_tps,
    t.nomor_tps,
    t.dpt,
    d.id as id_desa,
    d.nama as nama_desa,
    kec.id as id_kecamatan,
    kec.nama as nama_kecamatan,
    kab.id as id_kabupaten,
    kab.nama as nama_kabupaten,
    p.id as id_provinsi,
    p.nama as nama_provinsi,
    COALESCE(SUM(s.jumlah_suara), 0) as total_suara
FROM tps t
JOIN desa d ON t.id_desa = d.id
JOIN kecamatan kec ON d.id_kecamatan = kec.id
JOIN kabupaten kab ON kec.id_kabupaten = kab.id
JOIN provinsi p ON kab.id_provinsi = p.id
LEFT JOIN suara s ON t.id = s.id_tps
GROUP BY t.id;

-- View rekap suara per calon
CREATE OR REPLACE VIEW `v_rekap_calon` AS
SELECT 
    c.id as id_calon,
    c.nomor_urut,
    c.nama_calon,
    c.nama_wakil,
    c.foto_calon,
    c.foto_wakil,
    c.warna,
    c.jenis_pemilihan,
    COALESCE(SUM(s.jumlah_suara), 0) as total_suara
FROM calon c
LEFT JOIN suara s ON c.id = s.id_calon
WHERE c.is_active = 1
GROUP BY c.id;
