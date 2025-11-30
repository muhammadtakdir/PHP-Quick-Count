<?php
/**
 * Header Template
 * Quick Count System
 */

$settings = getSettings();
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= getNamaPemilihan($settings) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?= $settings['warna_tema'] ?? '#4f46e5' ?>;
            --sidebar-width: 260px;
            --header-height: 60px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header .logo {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
            color: white;
        }
        
        .sidebar-header h5 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 16px;
        }
        
        .sidebar-header small {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
        }
        
        .sidebar-menu {
            padding: 15px 0;
        }
        
        .menu-label {
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 20px 8px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }
        
        .sidebar-menu a.active {
            background: rgba(79, 70, 229, 0.2);
            color: white;
            border-left-color: var(--primary-color);
        }
        
        .sidebar-menu a i {
            width: 20px;
            margin-right: 12px;
            font-size: 18px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Header */
        .main-header {
            background: white;
            height: var(--header-height);
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
        }
        
        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 14px;
        }
        
        .breadcrumb-nav a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .user-dropdown img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        
        .user-dropdown .user-info {
            text-align: right;
        }
        
        .user-dropdown .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
        }
        
        .user-dropdown .user-role {
            font-size: 12px;
            color: #64748b;
        }
        
        /* Content Area */
        .content-wrapper {
            padding: 25px;
        }
        
        .page-header {
            margin-bottom: 25px;
        }
        
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        
        .page-header p {
            color: #64748b;
            margin: 5px 0 0;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 15px 20px;
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Stat Cards */
        .stat-card {
            padding: 20px;
            border-radius: 12px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card.primary { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
        .stat-card.success { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .stat-card.warning { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
        .stat-card.danger { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .stat-card.info { background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); }
        
        .stat-card .stat-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 60px;
            opacity: 0.2;
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #4338ca;
            border-color: #4338ca;
        }
        
        /* Tables */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: #64748b;
            border-bottom-width: 1px;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            border-color: #e5e7eb;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="bi bi-bar-chart-fill"></i>
            </div>
            <h5><?= getNamaPemilihan($settings) ?></h5>
            <small>Tahun <?= $settings['tahun_pemilihan'] ?? date('Y') ?></small>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="<?= APP_URL ?>index.php" class="<?= $currentPage == 'index' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <?php if (!hasRole(['viewer'])): ?>
            <a href="<?= APP_URL ?>input-suara.php" class="<?= $currentPage == 'input-suara' ? 'active' : '' ?>">
                <i class="bi bi-pencil-square"></i>
                <span>Input Suara</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>grafik.php" class="<?= $currentPage == 'grafik' ? 'active' : '' ?>">
                <i class="bi bi-pie-chart-fill"></i>
                <span>Grafik & Rekap</span>
            </a>
            
            <div class="menu-label">Master Data</div>
            <?php if (empty($settings['id_provinsi_aktif']) || !empty($settings['izinkan_edit_provinsi'])): ?>
            <a href="<?= APP_URL ?>pages/provinsi.php" class="<?= $currentPage == 'provinsi' ? 'active' : '' ?>">
                <i class="bi bi-geo-alt"></i>
                <span>Provinsi</span>
            </a>
            <?php endif; ?>
            <?php if (empty($settings['id_kabupaten_aktif']) || !empty($settings['izinkan_edit_kabupaten'])): ?>
            <a href="<?= APP_URL ?>pages/kabupaten.php" class="<?= $currentPage == 'kabupaten' ? 'active' : '' ?>">
                <i class="bi bi-building"></i>
                <span>Kabupaten/Kota</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>pages/kecamatan.php" class="<?= $currentPage == 'kecamatan' ? 'active' : '' ?>">
                <i class="bi bi-buildings"></i>
                <span>Kecamatan</span>
            </a>
            <a href="<?= APP_URL ?>pages/desa.php" class="<?= $currentPage == 'desa' ? 'active' : '' ?>">
                <i class="bi bi-house"></i>
                <span>Desa/Kelurahan</span>
            </a>
            <a href="<?= APP_URL ?>pages/tps.php" class="<?= $currentPage == 'tps' ? 'active' : '' ?>">
                <i class="bi bi-box"></i>
                <span>TPS</span>
            </a>
            <a href="<?= APP_URL ?>pages/calon.php" class="<?= $currentPage == 'calon' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Calon</span>
            </a>
            
            <?php if (hasRole(['admin'])): ?>
            <div class="menu-label">Pengaturan</div>
            <a href="<?= APP_URL ?>pages/settings.php" class="<?= $currentPage == 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Konfigurasi</span>
            </a>
            <a href="<?= APP_URL ?>pages/settings-daerah.php" class="<?= $currentPage == 'settings-daerah' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i>
                <span>Pengaturan Daerah</span>
            </a>
            <?php if (($settings['jenis_hitung'] ?? 'real_count') === 'quick_count'): ?>
            <a href="<?= APP_URL ?>pages/tps-sample.php" class="<?= $currentPage == 'tps-sample' ? 'active' : '' ?>">
                <i class="bi bi-pin-map"></i>
                <span>TPS Sampel</span>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>pages/users.php" class="<?= $currentPage == 'users' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Pengguna</span>
            </a>
            <?php endif; ?>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <nav class="breadcrumb-nav">
                    <a href="<?= APP_URL ?>index.php"><i class="bi bi-house"></i></a>
                    <span>/</span>
                    <span><?= $pageTitle ?? 'Dashboard' ?></span>
                </nav>
            </div>
            
            <div class="header-right">
                <div class="dropdown">
                    <div class="user-dropdown" data-bs-toggle="dropdown">
                        <div class="user-info d-none d-md-block">
                            <div class="user-name"><?= $_SESSION['user_name'] ?? 'User' ?></div>
                            <div class="user-role"><?= ucfirst($_SESSION['user_role'] ?? 'Guest') ?></div>
                        </div>
                        <img src="<?= APP_URL ?>uploads/users/<?= $_SESSION['user_foto'] ?? 'default.png' ?>" alt="User">
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>pages/profile.php">
                            <i class="bi bi-person me-2"></i>Profil Saya
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>auth/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content-wrapper">
