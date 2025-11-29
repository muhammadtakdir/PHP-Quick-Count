-- =====================================================
-- Quick Count System - Sample Data
-- =====================================================
-- Import ini setelah quickcount_structure.sql
-- =====================================================

-- Default Admin User (password: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `telepon`, `role`, `foto`, `is_active`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@quickcount.local', '08123456789', 'admin', 'default.png', 1);

-- Default Settings
INSERT INTO `settings` (`id`, `nama_pemilihan`, `jenis_pemilihan`, `tingkat_wilayah`, `tahun_pemilihan`, `logo`, `warna_tema`, `is_active`) VALUES
(1, 'Quick Count Pemilu', 'pilbup', 'kabupaten', 2024, 'logo.png', '#4f46e5', 1);

-- Sample Provinsi
INSERT INTO `provinsi` (`id`, `kode`, `nama`, `is_active`) VALUES
(1, '73', 'Sulawesi Selatan', 1),
(2, '31', 'DKI Jakarta', 1),
(3, '32', 'Jawa Barat', 1);

-- Sample Kabupaten
INSERT INTO `kabupaten` (`id`, `id_provinsi`, `kode`, `nama`, `tipe`, `is_active`) VALUES
(1, 1, '7311', 'Bone', 'kabupaten', 1),
(2, 1, '7312', 'Sinjai', 'kabupaten', 1),
(3, 1, '7371', 'Makassar', 'kota', 1);

-- Sample Kecamatan (Bone)
INSERT INTO `kecamatan` (`id`, `id_kabupaten`, `kode`, `nama`, `is_active`) VALUES
(1, 1, '731101', 'Tanete Riattang', 1),
(2, 1, '731102', 'Tanete Riattang Barat', 1),
(3, 1, '731103', 'Tanete Riattang Timur', 1);

-- Sample Desa (Kec. Tanete Riattang)
INSERT INTO `desa` (`id`, `id_kecamatan`, `kode`, `nama`, `tipe`, `is_active`) VALUES
(1, 1, '7311011001', 'Watampone', 'kelurahan', 1),
(2, 1, '7311011002', 'Bukaka', 'kelurahan', 1),
(3, 1, '7311011003', 'Macanang', 'kelurahan', 1);

-- Sample TPS
INSERT INTO `tps` (`id`, `id_desa`, `nomor_tps`, `dpt`, `alamat`, `is_active`) VALUES
(1, 1, 1, 250, 'Jl. Merdeka No. 1', 1),
(2, 1, 2, 280, 'Jl. Merdeka No. 2', 1),
(3, 1, 3, 300, 'Jl. Ahmad Yani', 1),
(4, 2, 1, 275, 'Jl. Bukaka Raya', 1),
(5, 2, 2, 260, 'Jl. Bukaka Baru', 1);

-- Sample Calon (Pilbup)
INSERT INTO `calon` (`id`, `jenis_pemilihan`, `id_provinsi`, `id_kabupaten`, `nomor_urut`, `nama_calon`, `nama_wakil`, `partai`, `warna`, `is_active`) VALUES
(1, 'pilbup', 1, 1, 1, 'Calon A', 'Wakil A', 'Partai Satu', '#e74c3c', 1),
(2, 'pilbup', 1, 1, 2, 'Calon B', 'Wakil B', 'Partai Dua', '#3498db', 1),
(3, 'pilbup', 1, 1, 3, 'Calon C', 'Wakil C', 'Partai Tiga', '#27ae60', 1);
