<?php
/**
 * Halaman Publik - Quick Count System
 * Melihat hasil quick count tanpa login
 * Mendukung jenis_hitung: quick_count (sample TPS) dan real_count (semua TPS)
 * OTOMATIS mengambil settings per kabupaten/provinsi
 * 
 * Logic:
 * - Pilih Provinsi saja → tampilkan data Pilgub
 * - Pilih Provinsi + Kabupaten → tampilkan data Pilbup/Pilwalkot
 */

require_once 'config/config.php';

$globalSettings = getSettings();
$conn = getConnection();

// Cek mode publik - apakah dibatasi ke satu daerah atau bisa semua
$publikMode = $globalSettings['publik_mode'] ?? 'semua_daerah';
$idKabupatenPublik = $globalSettings['id_kabupaten_publik'] ?? null;

// Get filter parameters dari URL
$filterProvinsi = isset($_GET['provinsi']) && $_GET['provinsi'] !== '' ? intval($_GET['provinsi']) : 0;
$filterKabupaten = isset($_GET['kabupaten']) && $_GET['kabupaten'] !== '' ? intval($_GET['kabupaten']) : 0;
$filterKecamatan = isset($_GET['kecamatan']) && $_GET['kecamatan'] !== '' ? intval($_GET['kecamatan']) : 0;
$filterDesa = isset($_GET['desa']) && $_GET['desa'] !== '' ? intval($_GET['desa']) : 0;

// Jika mode satu_daerah dan ada id_kabupaten_publik, paksa ke kabupaten tersebut
if ($publikMode === 'satu_daerah' && $idKabupatenPublik) {
    $filterKabupaten = $idKabupatenPublik;
}

// Flag untuk menentukan apakah data ditampilkan atau tidak
// Data ditampilkan jika minimal ada provinsi yang dipilih
$showData = ($filterProvinsi > 0);

// Tentukan jenis pemilihan berdasarkan filter
// - Jika hanya provinsi dipilih → pilgub
// - Jika provinsi + kabupaten dipilih → pilbup/pilwalkot
$filterMode = 'none'; // none, pilgub, pilbup
if ($filterProvinsi > 0 && $filterKabupaten == 0) {
    $filterMode = 'pilgub';
} elseif ($filterProvinsi > 0 && $filterKabupaten > 0) {
    $filterMode = 'pilbup'; // atau pilwalkot, akan dicek dari settings
}

// =============================================
// Default values - akan di-override oleh settings per-daerah
// =============================================
$jenisHitung = 'real_count';
$publicDetailLevel = 'full';
$isSampleOnly = false;
$tahunPemilihan = $globalSettings['tahun_pemilihan'] ?? date('Y');
$jumlahTpsSample = 0;
$jenisPemilihan = 'pilbup';
$namaPemilihan = 'Quick Count System';
$rekap = [];
$stats = ['total_tps' => 0, 'tps_masuk' => 0, 'total_dpt' => 0];
$breadcrumb = [];
$totalSuara = 0;
$tpsProgress = 0;
$allowedDrillDown = [];
$canViewKecamatan = true;
$canViewDesa = true;
$canViewTPS = true;
$showAbsoluteNumbers = true;
$chartLabels = '[]';
$chartData = '[]';
$chartColors = '[]';
$jenisPemilihanLabel = [
    'pilpres' => 'Pemilihan Presiden',
    'pilgub' => 'Pemilihan Gubernur', 
    'pilbup' => 'Pemilihan Bupati',
    'pilwalkot' => 'Pemilihan Walikota',
    'pilkades' => 'Pemilihan Kepala Desa'
];
$jenisHitungLabel = 'Real Count';
$noDataMessage = '';

// Jika ada filter dipilih, ambil data
if ($showData) {

if ($filterMode === 'pilgub') {
    // =============================================
    // MODE PILGUB - Hanya provinsi dipilih
    // Mengambil settings dari settings_kabupaten berdasarkan id_provinsi
    // =============================================
    $jenisPemilihan = 'pilgub';
    
    // Ambil info provinsi
    $stmt = $conn->prepare("SELECT nama FROM provinsi WHERE id = ?");
    $stmt->bind_param("i", $filterProvinsi);
    $stmt->execute();
    $provInfo = $stmt->get_result()->fetch_assoc();
    $namaProvinsi = $provInfo['nama'] ?? 'Unknown';
    
    // Ambil settings khusus untuk pilgub provinsi ini
    $stmt = $conn->prepare("SELECT * FROM settings_kabupaten WHERE id_provinsi = ? AND jenis_pemilihan = 'pilgub' LIMIT 1");
    $stmt->bind_param("i", $filterProvinsi);
    $stmt->execute();
    $settingsProv = $stmt->get_result()->fetch_assoc();
    
    // Override dengan settings provinsi jika ada
    if ($settingsProv) {
        $jenisHitung = $settingsProv['jenis_hitung'] ?? 'real_count';
        $publicDetailLevel = $settingsProv['public_detail_level'] ?? 'full';
        $jumlahTpsSample = $settingsProv['jumlah_tps_sample'] ?? 0;
        $isSampleOnly = ($jenisHitung === 'quick_count');
    }
    
    // Cek apakah ada data calon pilgub untuk provinsi ini
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM calon WHERE jenis_pemilihan = 'pilgub' AND id_provinsi = ?");
    $stmt->bind_param("i", $filterProvinsi);
    $stmt->execute();
    $calonCount = $stmt->get_result()->fetch_assoc()['cnt'];
    
    if ($calonCount == 0) {
        $noDataMessage = "Data Pemilihan Gubernur untuk Provinsi <strong>{$namaProvinsi}</strong> belum tersedia.";
        $showData = false;
    } else {
        $namaPemilihan = "Pemilihan Gubernur " . $namaProvinsi . " " . $tahunPemilihan;
        
        // Build filter untuk query
        $filter = [
            'jenis_pemilihan' => 'pilgub',
            'id_provinsi' => $filterProvinsi
        ];
        if ($filterKecamatan) $filter['id_kecamatan'] = $filterKecamatan;
        if ($filterDesa) $filter['id_desa'] = $filterDesa;
        
        // Get rekap data
        $rekap = getRekapSuara($filter);
        
        // Get statistics
        $stats = getStatistikTPS($filter, false);
        
        // Untuk Quick Count: gunakan jumlah_tps_sample dari settings provinsi
        if ($isSampleOnly && $jumlahTpsSample > 0) {
            $stats['total_tps'] = $jumlahTpsSample;
        }
        
        $breadcrumb = [$namaProvinsi];
    }
    
} else {
    // =============================================
    // MODE PILBUP/PILWALKOT - Provinsi + Kabupaten dipilih
    // Mengambil settings dari settings_kabupaten berdasarkan id_kabupaten
    // =============================================
    
    // Ambil nama kabupaten
    $stmt = $conn->prepare("SELECT k.nama as nama_kabupaten, p.nama as nama_provinsi, p.id as id_provinsi 
                            FROM kabupaten k JOIN provinsi p ON k.id_provinsi = p.id WHERE k.id = ?");
    $stmt->bind_param("i", $filterKabupaten);
    $stmt->execute();
    $kabInfo = $stmt->get_result()->fetch_assoc();
    $namaKabupaten = $kabInfo['nama_kabupaten'] ?? 'Unknown';
    $namaProvinsi = $kabInfo['nama_provinsi'] ?? 'Unknown';
    
    // Tentukan jenis pemilihan berdasarkan nama kabupaten (kota atau bukan)
    $isKota = (stripos($namaKabupaten, 'kota') === 0);
    $jenisPemilihan = $isKota ? 'pilwalkot' : 'pilbup';
    
    // Ambil settings khusus untuk kabupaten ini
    $stmt = $conn->prepare("SELECT * FROM settings_kabupaten WHERE id_kabupaten = ? LIMIT 1");
    $stmt->bind_param("i", $filterKabupaten);
    $stmt->execute();
    $settingsKab = $stmt->get_result()->fetch_assoc();
    
    // Override dengan settings kabupaten jika ada
    if ($settingsKab) {
        $jenisHitung = $settingsKab['jenis_hitung'] ?? 'real_count';
        $publicDetailLevel = $settingsKab['public_detail_level'] ?? 'full';
        $jumlahTpsSample = $settingsKab['jumlah_tps_sample'] ?? 0;
        $isSampleOnly = ($jenisHitung === 'quick_count');
    }
    
    // Cek apakah ada data calon untuk kabupaten ini
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM calon WHERE jenis_pemilihan IN ('pilbup', 'pilwalkot') AND id_kabupaten = ?");
    $stmt->bind_param("i", $filterKabupaten);
    $stmt->execute();
    $calonCount = $stmt->get_result()->fetch_assoc()['cnt'];
    
    $jenisLabel = ($jenisPemilihan === 'pilwalkot') ? 'Walikota' : 'Bupati';
    
    if ($calonCount == 0) {
        $noDataMessage = "Data Pemilihan {$jenisLabel} untuk <strong>{$namaKabupaten}</strong> belum tersedia.";
        $showData = false;
    } else {
        // Generate nama pemilihan otomatis
        $namaPemilihan = "Pemilihan " . $jenisLabel . " " . $namaKabupaten . " " . $tahunPemilihan;
    }
    
} // End of filterMode check (pilgub vs pilbup)

} // End of first if ($showData) - checking for filter existence

// Jika masih showData (ada data), lanjutkan proses
if ($showData) {

// Gunakan warna tema dari global settings
$settings = $globalSettings;
$settings['jenis_hitung'] = $jenisHitung;
$settings['jenis_pemilihan'] = $jenisPemilihan;
$settings['public_detail_level'] = $publicDetailLevel;
$settings['nama_pemilihan'] = $namaPemilihan;

// Tentukan batasan drill-down berdasarkan jenis pemilihan dan jenis hitung
function getAllowedDrillDown($jenisPemilihan, $jenisHitung) {
    // Quick Count - batasi drill-down
    if ($jenisHitung === 'quick_count') {
        switch ($jenisPemilihan) {
            case 'pilkades': return ['desa']; // Pilkades hanya sampai desa
            case 'pilgub': return ['kabupaten']; // Pilgub hanya sampai kabupaten
            case 'pilbup':
            case 'pilwalkot': return ['kecamatan']; // Pilbup/Pilwalkot hanya sampai kecamatan
            case 'pilpres': return ['provinsi', 'kabupaten'];
            default: return ['kabupaten'];
        }
    }
    
    // Real Count - semua level diizinkan
    switch ($jenisPemilihan) {
        case 'pilkades': return ['desa', 'tps'];
        case 'pilgub': return ['kabupaten', 'kecamatan', 'desa', 'tps'];
        case 'pilbup':
        case 'pilwalkot': return ['kabupaten', 'kecamatan', 'desa', 'tps'];
        case 'pilpres': return ['provinsi', 'kabupaten', 'kecamatan', 'desa', 'tps'];
        default: return ['kabupaten', 'kecamatan', 'desa', 'tps'];
    }
}

$allowedDrillDown = getAllowedDrillDown($jenisPemilihan, $jenisHitung);

// Untuk Quick Count, tentukan level maksimal yang bisa dilihat
$maxDrillLevel = 'tps'; // default untuk real_count
if ($jenisHitung === 'quick_count') {
    // Ambil level tertinggi dari allowedDrillDown
    $levels = ['kabupaten', 'kecamatan', 'desa', 'tps'];
    foreach (array_reverse($levels) as $level) {
        if (in_array($level, $allowedDrillDown)) {
            $maxDrillLevel = $level;
            break;
        }
    }
}

// Validasi filter berdasarkan batasan drill-down
// Untuk quick count: hanya bisa filter sampai level yang diizinkan
$canViewKecamatan = ($jenisHitung === 'real_count') || in_array('kecamatan', $allowedDrillDown);
$canViewDesa = ($jenisHitung === 'real_count') || in_array('desa', $allowedDrillDown) || in_array('tps', $allowedDrillDown);
$canViewTPS = ($jenisHitung === 'real_count') || in_array('tps', $allowedDrillDown);

// Untuk Quick Count pilbup/pilwalkot:
// - Filter kecamatan TIDAK ditampilkan (karena level max adalah kecamatan, tidak ada drill-down lebih)
// - Rekap kecamatan ditampilkan tapi tanpa link
if ($jenisHitung === 'quick_count') {
    if ($maxDrillLevel === 'kabupaten') {
        // Pilgub quick count: tidak bisa filter/drill ke kecamatan, desa, tps
        $canViewKecamatan = false;
        $canViewDesa = false;
        $canViewTPS = false;
    } elseif ($maxDrillLevel === 'kecamatan') {
        // Pilbup/pilwalkot quick count: tidak bisa filter/drill ke desa, tps
        // Tapi tetap tampilkan rekap per kecamatan
        $canViewKecamatan = false; // Tidak ada filter kecamatan karena sudah sampai level max
        $canViewDesa = false;
        $canViewTPS = false;
    } elseif ($maxDrillLevel === 'desa') {
        // Pilkades quick count: tidak bisa drill ke tps
        $canViewTPS = false;
    }
}

// Reset filter jika tidak diizinkan (untuk quick count)
if ($jenisHitung === 'quick_count') {
    if ($maxDrillLevel === 'kabupaten') {
        $filterKecamatan = 0;
        $filterDesa = 0;
    } elseif ($maxDrillLevel === 'kecamatan') {
        $filterDesa = 0;
    }
}

// Untuk mode pilbup/pilwalkot, query rekap dan stats di sini
// Mode pilgub sudah diquery di atas
if ($filterMode === 'pilbup') {
    // Build filter array
    $filter = ['jenis_pemilihan' => $jenisPemilihan];
    if ($filterProvinsi) $filter['id_provinsi'] = $filterProvinsi;
    if ($filterKabupaten) $filter['id_kabupaten'] = $filterKabupaten;
    if ($filterKecamatan) $filter['id_kecamatan'] = $filterKecamatan;
    if ($filterDesa) $filter['id_desa'] = $filterDesa;
    
    // Get rekap data
    $rekap = getRekapSuara($filter);
    
    // Get statistics
    $stats = getStatistikTPS($filter, false);
    
    // Untuk Quick Count: gunakan jumlah_tps_sample dari settings kabupaten
    if ($isSampleOnly && $jumlahTpsSample > 0) {
        $stats['total_tps'] = $jumlahTpsSample;
    }
    
    $breadcrumb = getLocationBreadcrumb($filterDesa, $filterKecamatan, $filterKabupaten, $filterProvinsi);
}

// Calculate totals
$totalSuara = array_sum(array_column($rekap, 'total_suara'));

// Hitung progress - untuk quick count, cap di 100%
$tpsProgress = $stats['total_tps'] > 0 ? round(($stats['tps_masuk'] / $stats['total_tps']) * 100, 2) : 0;
if ($isSampleOnly && $tpsProgress > 100) {
    $tpsProgress = 100; // Cap di 100% untuk quick count
}

// Jenis pemilihan label
$jenisPemilihanLabel = [
    'pilpres' => 'Pemilihan Presiden',
    'pilgub' => 'Pemilihan Gubernur', 
    'pilbup' => 'Pemilihan Bupati',
    'pilwalkot' => 'Pemilihan Walikota',
    'pilkades' => 'Pemilihan Kepala Desa'
];

// Label jenis perhitungan
$jenisHitungLabel = $jenisHitung === 'quick_count' ? 'Quick Count (Sampling)' : 'Real Count';

// Tentukan apakah boleh tampilkan jumlah suara (angka absolut) atau hanya persentase
// Quick Count: hanya persentase, Real Count: tampilkan semua detail
$showAbsoluteNumbers = ($jenisHitung === 'real_count');

// Prepare chart data
$chartLabels = json_encode(array_map(function($c) { return $c['nama_calon']; }, $rekap));
$chartData = json_encode(array_column($rekap, 'total_suara'));
$chartColors = json_encode(array_column($rekap, 'warna'));

// Prepare percentage data for quick count charts
$chartPercentages = [];
foreach ($rekap as $c) {
    $chartPercentages[] = $totalSuara > 0 ? round(($c['total_suara'] / $totalSuara) * 100, 2) : 0;
}
$chartPercentagesJson = json_encode($chartPercentages);

} else {
    // Jika tidak showData, gunakan globalSettings dengan nama default
    $settings = $globalSettings;
    $settings['nama_pemilihan'] = 'Quick Count System';
    $settings['jenis_hitung'] = 'real_count';
    $settings['jenis_pemilihan'] = 'pilbup';
} // End of if ($showData)

// Get dropdowns - selalu tersedia untuk filter
$provinsiList = getProvinsi();
$kabupatenList = $filterProvinsi ? getKabupaten($filterProvinsi) : [];
$kecamatanList = ($filterKabupaten && $canViewKecamatan) ? getKecamatan($filterKabupaten) : [];
$desaList = ($filterKecamatan && $canViewDesa) ? getDesa($filterKecamatan) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Quick Count - <?= htmlspecialchars($settings['nama_pemilihan']) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?= $settings['warna_tema'] ?? '#4f46e5' ?>;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .header-section {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .main-content {
            padding-bottom: 50px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }
        
        .stat-card.primary .icon { background: #e0e7ff; color: #4f46e5; }
        .stat-card.success .icon { background: #d1fae5; color: #059669; }
        .stat-card.warning .icon { background: #fef3c7; color: #d97706; }
        .stat-card.info .icon { background: #cffafe; color: #0891b2; }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-card .label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .candidate-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: transform 0.2s ease;
        }
        
        .candidate-card:hover {
            transform: translateX(5px);
        }
        
        .candidate-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        
        .progress {
            height: 12px;
            border-radius: 10px;
            background: #e5e7eb;
        }
        
        .progress-main {
            height: 30px;
            font-size: 1rem;
        }
        
        .filter-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
        }
        
        .footer-section {
            background: rgba(0,0,0,0.2);
            color: white;
            padding: 20px 0;
            text-align: center;
        }
        
        .breadcrumb-location {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 10px;
            color: white;
            display: inline-block;
        }
        
        .live-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .table th {
            background: #f8fafc;
            font-weight: 600;
        }
        
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-section no-print">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="logo-container">
                        <div class="logo-icon">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= htmlspecialchars($settings['nama_pemilihan']) ?></h4>
                            <small class="text-muted">
                                <?= $jenisPemilihanLabel[$jenisPemilihan] ?? 'Quick Count' ?>
                                <span class="badge <?= $jenisHitung === 'quick_count' ? 'bg-warning text-dark' : 'bg-info' ?> ms-2">
                                    <i class="bi bi-<?= $jenisHitung === 'quick_count' ? 'lightning' : 'database' ?> me-1"></i><?= $jenisHitungLabel ?>
                                </span>
                                <span class="badge bg-success ms-1 live-badge">
                                    <i class="bi bi-broadcast me-1"></i>LIVE
                                </span>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Cetak
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container main-content">
        <!-- Breadcrumb Location -->
        <?php if (!empty($breadcrumb) && $showData): ?>
        <div class="text-center mb-4">
            <div class="breadcrumb-location">
                <i class="bi bi-geo-alt me-2"></i><?= implode(' &raquo; ', $breadcrumb) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filter - Selalu tampil -->
        <div class="card filter-card mb-4 no-print">
            <div class="card-body py-3">
                <form class="row g-2 align-items-center" id="filterForm">
                    <div class="col-md-3">
                        <select name="provinsi" id="filterProvinsi" class="form-select">
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsiList as $prov): ?>
                            <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="kabupaten" id="filterKabupaten" class="form-select">
                            <option value="">-- Pilih Kabupaten --</option>
                            <?php foreach ($kabupatenList as $kab): ?>
                            <option value="<?= $kab['id'] ?>" <?= $filterKabupaten == $kab['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kab['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($showData && $canViewKecamatan): ?>
                    <div class="col-md-2">
                        <select name="kecamatan" id="filterKecamatan" class="form-select">
                            <option value="">-- Semua Kecamatan --</option>
                            <?php foreach ($kecamatanList as $kec): ?>
                            <option value="<?= $kec['id'] ?>" <?= $filterKecamatan == $kec['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kec['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($showData && $canViewDesa): ?>
                    <div class="col-md-2">
                        <select name="desa" id="filterDesa" class="form-select">
                            <option value="">-- Semua Desa --</option>
                            <?php foreach ($desaList as $ds): ?>
                            <option value="<?= $ds['id'] ?>" <?= $filterDesa == $ds['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ds['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($showData): ?>
        <!-- Statistics -->
        <?php if ($isSampleOnly): ?>
        <div class="alert alert-warning mb-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Mode Quick Count:</strong> Data ditampilkan berdasarkan estimasi sampling. 
            Hasil ini bukan hasil final resmi.
            <br><small class="text-muted">
                Detail hanya tersedia sampai level <?= $maxDrillLevel === 'kabupaten' ? 'Kabupaten' : ($maxDrillLevel === 'kecamatan' ? 'Kecamatan' : 'Desa') ?>.
            </small>
        </div>
        <?php endif; ?>
        
        <?php if ($showAbsoluteNumbers): ?>
        <!-- Real Count: Tampilkan statistik lengkap -->
        <div class="row mb-4">
            <div class="col-6 col-lg-3 mb-3">
                <div class="stat-card primary">
                    <div class="icon"><i class="bi bi-box-seam"></i></div>
                    <div class="value"><?= formatNumber($stats['total_tps']) ?></div>
                    <div class="label">Total TPS</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="stat-card success">
                    <div class="icon"><i class="bi bi-check-circle"></i></div>
                    <div class="value"><?= formatNumber($stats['tps_masuk']) ?></div>
                    <div class="label">TPS Masuk</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="stat-card warning">
                    <div class="icon"><i class="bi bi-people"></i></div>
                    <div class="value"><?= formatNumber($stats['total_dpt']) ?></div>
                    <div class="label">Total DPT</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="stat-card info">
                    <div class="icon"><i class="bi bi-card-checklist"></i></div>
                    <div class="value"><?= formatNumber($totalSuara) ?></div>
                    <div class="label">Total Suara</div>
                </div>
            </div>
        </div>
        
        <!-- Progress Bar - Real Count -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">
                        <i class="bi bi-hourglass-split me-2"></i>Progress Input Data
                    </span>
                    <span class="badge bg-primary fs-6"><?= $tpsProgress ?>%</span>
                </div>
                <div class="progress progress-main">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         style="width: <?= $tpsProgress ?>%">
                        <?= $tpsProgress ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Quick Count: Hanya tampilkan progress persentase -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">
                        <i class="bi bi-lightning me-2"></i>Estimasi Quick Count
                    </span>
                    <span class="badge bg-warning text-dark fs-6"><?= $tpsProgress ?>% TPS Sample Masuk</span>
                </div>
                <div class="progress progress-main">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" 
                         style="width: <?= $tpsProgress ?>%">
                        <?= $tpsProgress ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$showAbsoluteNumbers): ?>
        <!-- QUICK COUNT: Tampilan khusus dengan foto calon dan persentase besar -->
        <div class="row justify-content-center mb-4">
            <?php 
            foreach ($rekap as $calon): 
                $percentage = $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0;
                $colSize = count($rekap) <= 3 ? 4 : 3;
            ?>
            <div class="col-md-<?= $colSize ?> col-6 mb-3">
                <div class="card text-center h-100 shadow-sm" style="border-top: 4px solid <?= $calon['warna'] ?>">
                    <div class="card-body p-3">
                        <!-- Nomor Urut -->
                        <div class="mb-2">
                            <span class="badge rounded-pill fs-6 px-3 py-2" style="background: <?= $calon['warna'] ?>">
                                No. <?= $calon['nomor_urut'] ?>
                            </span>
                        </div>
                        
                        <!-- Foto Pasangan Calon (1 foto untuk semua pasangan) -->
                        <div class="mb-3">
                            <?php if (!empty($calon['foto_calon'])): ?>
                            <img src="uploads/calon/<?= $calon['foto_calon'] ?>" 
                                 class="rounded shadow-sm" 
                                 style="width: 100px; height: 100px; object-fit: cover; border: 3px solid <?= $calon['warna'] ?>;"
                                 alt="Paslon <?= $calon['nomor_urut'] ?>">
                            <?php else: ?>
                            <div class="rounded shadow-sm d-inline-flex align-items-center justify-content-center" 
                                 style="width: 100px; height: 100px; background: <?= $calon['warna'] ?>20; border: 3px solid <?= $calon['warna'] ?>;">
                                <i class="bi bi-people fs-1" style="color: <?= $calon['warna'] ?>"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Persentase Besar -->
                        <h2 class="fw-bold mb-2" style="color: <?= $calon['warna'] ?>; font-size: 2.5rem;">
                            <?= $percentage ?>%
                        </h2>
                        
                        <!-- Nama Calon -->
                        <h6 class="mb-0 text-truncate" title="<?= htmlspecialchars($calon['nama_calon']) ?>">
                            <?= htmlspecialchars($calon['nama_calon']) ?>
                        </h6>
                        <?php if (!empty($calon['nama_wakil'])): ?>
                        <small class="text-muted text-truncate d-block" title="<?= htmlspecialchars($calon['nama_wakil']) ?>">
                            &amp; <?= htmlspecialchars($calon['nama_wakil']) ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Grafik untuk Quick Count -->
        <div class="row mb-4">
            <!-- Pie Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-pie-chart me-2"></i>Diagram Persentase Suara
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bar Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-bar-chart me-2"></i>Grafik Perbandingan Suara
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="barChartQC"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daftar Perolehan Suara Calon (Quick Count) -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-list-ol me-2"></i>Daftar Perolehan Suara
                <span class="badge bg-warning text-dark float-end">Estimasi Quick Count</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px" class="text-center">No</th>
                                <th>Pasangan Calon</th>
                                <th class="text-center" style="width: 150px">Persentase</th>
                                <th style="width: 40%">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $maxPercentage = max(array_map(function($c) use ($totalSuara) { 
                                return $totalSuara > 0 ? ($c['total_suara'] / $totalSuara) * 100 : 0; 
                            }, $rekap) ?: [1]);
                            
                            foreach ($rekap as $calon): 
                                $percentage = $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0;
                                $barWidth = $maxPercentage > 0 ? ($percentage / $maxPercentage) * 100 : 0;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge rounded-pill px-3 py-2" style="background: <?= $calon['warna'] ?>; font-size: 1rem;">
                                        <?= $calon['nomor_urut'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($calon['foto_calon'])): ?>
                                        <img src="uploads/calon/<?= $calon['foto_calon'] ?>" 
                                             class="rounded me-3" 
                                             style="width: 50px; height: 50px; object-fit: cover; border: 2px solid <?= $calon['warna'] ?>;">
                                        <?php else: ?>
                                        <div class="rounded me-3 d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px; background: <?= $calon['warna'] ?>20; border: 2px solid <?= $calon['warna'] ?>;">
                                            <i class="bi bi-person" style="color: <?= $calon['warna'] ?>"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($calon['nama_calon']) ?></strong>
                                            <?php if (!empty($calon['nama_wakil'])): ?>
                                            <br><small class="text-muted">&amp; <?= htmlspecialchars($calon['nama_wakil']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <h4 class="mb-0" style="color: <?= $calon['warna'] ?>"><?= $percentage ?>%</h4>
                                </td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar" 
                                             style="width: <?= $barWidth ?>%; background: <?= $calon['warna'] ?>; font-size: 0.9rem;"
                                             role="progressbar">
                                            <?= $percentage ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- REAL COUNT: Tampilan dengan grafik dan detail lengkap -->
        <div class="row">
            <!-- Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-pie-chart me-2"></i>Diagram Perolehan Suara
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 350px;">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Candidates List -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-people me-2"></i>Perolehan Suara Calon
                    </div>
                    <div class="card-body p-3">
                        <?php 
                        $maxSuara = max(array_column($rekap, 'total_suara') ?: [1]);
                        foreach ($rekap as $calon): 
                            $percentage = $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0;
                            $barWidth = $maxSuara > 0 ? ($calon['total_suara'] / $maxSuara) * 100 : 0;
                        ?>
                        <div class="candidate-card" style="border-color: <?= $calon['warna'] ?>">
                            <div class="d-flex align-items-center">
                                <div class="candidate-number me-3" style="background: <?= $calon['warna'] ?>">
                                    <?= $calon['nomor_urut'] ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($calon['nama_calon']) ?></h5>
                                    <?php if ($calon['nama_wakil']): ?>
                                    <small class="text-muted">&amp; <?= htmlspecialchars($calon['nama_wakil']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <h4 class="mb-0" style="color: <?= $calon['warna'] ?>"><?= formatNumber($calon['total_suara']) ?></h4>
                                    <?php endif; ?>
                                    <span class="badge fs-6" style="background: <?= $calon['warna'] ?>"><?= $percentage ?>%</span>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <div class="progress-bar" style="width: <?= $barWidth ?>%; background: <?= $calon['warna'] ?>"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($rekap)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-3">Belum ada data calon</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bar Chart with Candidate Photos (Real Count Only) -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Grafik Perbandingan Suara
            </div>
            <div class="card-body">
                <!-- Foto Pasangan Calon di atas grafik -->
                <div class="row justify-content-center mb-3">
                    <?php foreach ($rekap as $calon): 
                        $percentage = $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0;
                        $colSize = count($rekap) <= 3 ? 4 : 3;
                    ?>
                    <div class="col text-center" style="max-width: <?= 100/count($rekap) ?>%">
                        <!-- Foto Pasangan -->
                        <?php if (!empty($calon['foto_calon'])): ?>
                        <img src="uploads/calon/<?= $calon['foto_calon'] ?>" 
                             class="rounded shadow-sm mb-2" 
                             style="width: 80px; height: 80px; object-fit: cover; border: 3px solid <?= $calon['warna'] ?>;"
                             alt="Paslon <?= $calon['nomor_urut'] ?>">
                        <?php else: ?>
                        <div class="rounded shadow-sm mb-2 d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; background: <?= $calon['warna'] ?>20; border: 3px solid <?= $calon['warna'] ?>;">
                            <span class="fw-bold fs-4" style="color: <?= $calon['warna'] ?>"><?= $calon['nomor_urut'] ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="fw-bold" style="color: <?= $calon['warna'] ?>; font-size: 1.1rem;">
                            <?= $percentage ?>%
                        </div>
                        <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;">
                            <?= htmlspecialchars($calon['nama_calon']) ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Bar Chart -->
                <div style="position: relative; height: 250px;">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; // End of Real Count section ?>
        
        <!-- Rekap per Kabupaten (untuk Pilgub) -->
        <?php 
        // Tampilkan rekap kabupaten jika mode pilgub (hanya provinsi dipilih, tanpa kabupaten)
        $showKabupatenRekap = ($filterMode === 'pilgub' && $filterProvinsi && !$filterKabupaten);
        if ($showKabupatenRekap): 
            $calonList = $rekap;
            $kabupatenListRekap = getKabupaten($filterProvinsi);
        ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-building me-2"></i>Rekap Per Kabupaten/Kota
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="rekapKabupatenTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%">Kabupaten/Kota</th>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center" style="width: 10%">TPS Masuk</th>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">
                                    <small>No. <?= $calon['nomor_urut'] ?></small><br>
                                    <small class="text-muted"><?= htmlspecialchars(explode(' - ', $calon['nama_calon'])[0]) ?></small>
                                </th>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">Total</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grandTotalPerCalon = [];
                            foreach ($calonList as $c) { $grandTotalPerCalon[$c['id']] = 0; }
                            $grandTotalSuara = 0;
                            $grandTotalTpsMasuk = 0;
                            $grandTotalTps = 0;
                            
                            foreach ($kabupatenListRekap as $kab):
                                $kabFilter = [
                                    'jenis_pemilihan' => 'pilgub',
                                    'id_provinsi' => $filterProvinsi,
                                    'id_kabupaten' => $kab['id']
                                ];
                                $kabRekap = getRekapSuara($kabFilter);
                                $kabStats = getStatistikTPS($kabFilter, false);
                                $kabTotal = array_sum(array_column($kabRekap, 'total_suara'));
                                
                                // Hitung grand total
                                $grandTotalSuara += $kabTotal;
                                $grandTotalTpsMasuk += $kabStats['tps_masuk'];
                                $grandTotalTps += $kabStats['total_tps'];
                                
                                $kabSuaraByCalon = [];
                                foreach ($kabRekap as $kr) {
                                    $kabSuaraByCalon[$kr['id']] = $kr['total_suara'];
                                    $grandTotalPerCalon[$kr['id']] += $kr['total_suara'];
                                }
                                
                                // Hitung persentase per kabupaten
                                $kabPercentages = [];
                                foreach ($kabRekap as $kr) {
                                    $kabPercentages[$kr['id']] = $kabTotal > 0 ? calculatePercentage($kr['total_suara'], $kabTotal) : 0;
                                }
                                
                                // Tentukan pemenang di kabupaten ini
                                $maxSuara = max(array_values($kabSuaraByCalon) ?: [0]);
                                $winnerId = array_search($maxSuara, $kabSuaraByCalon);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($kab['nama']) ?></strong>
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <br><small class="text-muted">TPS: <?= $kabStats['tps_masuk'] ?>/<?= $kabStats['total_tps'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center">
                                    <?php 
                                    $progressPct = $kabStats['total_tps'] > 0 ? round(($kabStats['tps_masuk'] / $kabStats['total_tps']) * 100) : 0;
                                    $progressClass = $progressPct >= 100 ? 'bg-success' : ($progressPct >= 50 ? 'bg-info' : 'bg-warning');
                                    ?>
                                    <span class="badge <?= $progressClass ?>"><?= $kabStats['tps_masuk'] ?>/<?= $kabStats['total_tps'] ?></span>
                                    <br><small class="text-muted"><?= $progressPct ?>%</small>
                                </td>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center <?= ($winnerId === $calon['id'] && $kabTotal > 0) ? 'table-success' : '' ?>">
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $kabPercentages[$calon['id']] ?? 0 ?>%</strong>
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <br><span class="text-muted"><?= formatNumber($kabSuaraByCalon[$calon['id']] ?? 0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center fw-bold"><?= formatNumber($kabTotal) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr class="fw-bold">
                                <td>
                                    <strong>TOTAL PROVINSI</strong>
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <br><small>TPS: <?= $grandTotalTpsMasuk ?>/<?= $grandTotalTps ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center">
                                    <?php 
                                    $grandProgressPct = $grandTotalTps > 0 ? round(($grandTotalTpsMasuk / $grandTotalTps) * 100) : 0;
                                    ?>
                                    <span class="badge bg-primary"><?= $grandTotalTpsMasuk ?>/<?= $grandTotalTps ?></span>
                                    <br><small><?= $grandProgressPct ?>%</small>
                                </td>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $grandTotalSuara > 0 ? calculatePercentage($grandTotalPerCalon[$calon['id']], $grandTotalSuara) : 0 ?>%</strong>
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <br><span class="fw-bold"><?= formatNumber($grandTotalPerCalon[$calon['id']]) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center fw-bold"><?= formatNumber($grandTotalSuara) ?></td>
                                <?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; // End of Rekap per Kabupaten ?>
        
        <!-- Rekap per Kecamatan -->
        <?php 
        // Tampilkan rekap kecamatan jika:
        // - Real count: semua level
        // - Quick count pilbup/pilwalkot: tampilkan tapi tanpa link drill-down
        $showKecamatanRekap = $filterKabupaten && !$filterKecamatan && $jenisPemilihan !== 'pilkades';
        if ($showKecamatanRekap): 
            $calonList = $rekap;
        ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-task me-2"></i>Rekap Per Kecamatan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="rekapTable">
                        <thead>
                            <tr>
                                <th>Kecamatan</th>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">TPS Masuk</th>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">No. <?= $calon['nomor_urut'] ?></th>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">Total</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $kecList = getKecamatan($filterKabupaten);
                            foreach ($kecList as $kec):
                                $kecFilter = array_merge($filter, ['id_kecamatan' => $kec['id']]);
                                // Selalu gunakan getRekapSuara - untuk quick count hanya tampilan yang berbeda
                                $kecRekap = getRekapSuara($kecFilter);
                                $kecStats = getStatistikTPS($kecFilter, false);
                                $kecTotal = array_sum(array_column($kecRekap, 'total_suara'));
                                
                                $kecSuaraByCalon = [];
                                foreach ($kecRekap as $kr) {
                                    $kecSuaraByCalon[$kr['id']] = $kr['total_suara'];
                                }
                                
                                // Hitung persentase per kecamatan
                                $kecPercentages = [];
                                foreach ($kecRekap as $kr) {
                                    $kecPercentages[$kr['id']] = $kecTotal > 0 ? calculatePercentage($kr['total_suara'], $kecTotal) : 0;
                                }
                            ?>
                            <tr>
                                <td>
                                    <?php if ($canViewDesa): ?>
                                    <a href="?provinsi=<?= $filterProvinsi ?>&kabupaten=<?= $filterKabupaten ?>&kecamatan=<?= $kec['id'] ?>" class="fw-bold text-decoration-none">
                                        <?= htmlspecialchars($kec['nama']) ?>
                                    </a>
                                    <?php else: ?>
                                    <strong><?= htmlspecialchars($kec['nama']) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <br><small class="text-muted">TPS Masuk: <?= $kecStats['tps_masuk'] ?> dari <?= $kecStats['total_tps'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center">
                                    <span class="badge bg-success"><?= $kecStats['tps_masuk'] ?>/<?= $kecStats['total_tps'] ?></span>
                                </td>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $kecPercentages[$calon['id']] ?? 0 ?>%</strong>
                                    <br><span class="text-muted"><?= formatNumber($kecSuaraByCalon[$calon['id']] ?? 0) ?></span>
                                    <?php else: ?>
                                    <?= $kecPercentages[$calon['id']] ?? 0 ?>%
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center fw-bold"><?= formatNumber($kecTotal) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($showAbsoluteNumbers): ?>
                        <tfoot class="table-secondary">
                            <tr>
                                <td class="fw-bold">Total Suara</td>
                                <td></td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0 ?>%</strong>
                                    <br><span class="fw-bold"><?= formatNumber($calon['total_suara']) ?></span>
                                </td>
                                <?php endforeach; ?>
                                <td class="text-center fw-bold"><?= formatNumber($totalSuara) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rekap per Desa -->
        <?php 
        // Rekap desa hanya untuk real count atau jika maxDrillLevel >= desa
        $showDesaRekap = $filterKecamatan && !$filterDesa && $canViewDesa && ($jenisHitung === 'real_count' || $maxDrillLevel === 'desa' || $maxDrillLevel === 'tps');
        if ($showDesaRekap): 
            $calonList = $rekap;
        ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-task me-2"></i>Rekap Per Desa/Kelurahan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="rekapTable">
                        <thead>
                            <tr>
                                <th>Desa/Kelurahan</th>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">TPS Masuk</th>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">No. <?= $calon['nomor_urut'] ?></th>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">Total</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $desaListRekap = getDesa($filterKecamatan);
                            foreach ($desaListRekap as $desa):
                                $desaFilter = array_merge($filter, ['id_desa' => $desa['id']]);
                                // Selalu gunakan getRekapSuara - untuk quick count hanya tampilan yang berbeda
                                $desaRekap = getRekapSuara($desaFilter);
                                $desaStats = getStatistikTPS($desaFilter, false);
                                $desaTotal = array_sum(array_column($desaRekap, 'total_suara'));
                                
                                $desaSuaraByCalon = [];
                                foreach ($desaRekap as $dr) {
                                    $desaSuaraByCalon[$dr['id']] = $dr['total_suara'];
                                }
                                
                                // Hitung persentase per desa
                                $desaPercentages = [];
                                foreach ($desaRekap as $dr) {
                                    $desaPercentages[$dr['id']] = $desaTotal > 0 ? calculatePercentage($dr['total_suara'], $desaTotal) : 0;
                                }
                            ?>
                            <tr>
                                <td>
                                    <?php if ($canViewTPS): ?>
                                    <a href="?provinsi=<?= $filterProvinsi ?>&kabupaten=<?= $filterKabupaten ?>&kecamatan=<?= $filterKecamatan ?>&desa=<?= $desa['id'] ?>" class="fw-bold text-decoration-none">
                                        <?= ($desa['tipe'] == 'kelurahan' ? 'Kel. ' : '') . htmlspecialchars($desa['nama']) ?>
                                    </a>
                                    <?php else: ?>
                                    <strong><?= ($desa['tipe'] == 'kelurahan' ? 'Kel. ' : '') . htmlspecialchars($desa['nama']) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <br><small class="text-muted">TPS Masuk: <?= $desaStats['tps_masuk'] ?> dari <?= $desaStats['total_tps'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center">
                                    <span class="badge bg-success"><?= $desaStats['tps_masuk'] ?>/<?= $desaStats['total_tps'] ?></span>
                                </td>
                                <?php endif; ?>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $desaPercentages[$calon['id']] ?? 0 ?>%</strong>
                                    <br><span class="text-muted"><?= formatNumber($desaSuaraByCalon[$calon['id']] ?? 0) ?></span>
                                    <?php else: ?>
                                    <?= $desaPercentages[$calon['id']] ?? 0 ?>%
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center fw-bold"><?= formatNumber($desaTotal) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($showAbsoluteNumbers): ?>
                        <tfoot class="table-secondary">
                            <tr>
                                <td class="fw-bold">Total Suara</td>
                                <td></td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0 ?>%</strong>
                                    <br><span class="fw-bold"><?= formatNumber($calon['total_suara']) ?></span>
                                </td>
                                <?php endforeach; ?>
                                <td class="text-center fw-bold"><?= formatNumber($totalSuara) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rekap per TPS - Hanya untuk Real Count -->
        <?php 
        $showTPSRekap = $filterDesa && $canViewTPS && ($jenisHitung === 'real_count');
        if ($showTPSRekap): 
            $calonList = $rekap;
            
            // Untuk quick_count, hanya tampilkan TPS sample
            $tpsCondition = "";
            if ($isSampleOnly) {
                $tpsCondition = " AND t.id IN (SELECT tps_id FROM tps_sample WHERE is_selected = 1)";
            }
            
            $sqlTps = "SELECT t.*, (SELECT COUNT(*) FROM suara WHERE id_tps = t.id) as sudah_input
                       FROM tps t WHERE t.id_desa = ? AND t.is_active = 1 $tpsCondition ORDER BY t.nomor_tps";
            $stmtTps = $conn->prepare($sqlTps);
            $stmtTps->bind_param("i", $filterDesa);
            $stmtTps->execute();
            $tpsListRekap = $stmtTps->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-box me-2"></i>Rekap Per TPS<?= $isSampleOnly ? ' (Sample)' : '' ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="rekapTable">
                        <thead>
                            <tr>
                                <th class="text-center">TPS</th>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">DPT</th>
                                <?php endif; ?>
                                <th class="text-center">Status</th>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">No. <?= $calon['nomor_urut'] ?></th>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <th class="text-center">Tdk Sah</th>
                                <th class="text-center">Total</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($tpsListRekap as $tps):
                                $sqlSuara = "SELECT s.*, c.nomor_urut FROM suara s JOIN calon c ON s.id_calon = c.id WHERE s.id_tps = ?";
                                $stmtSuara = $conn->prepare($sqlSuara);
                                $stmtSuara->bind_param("i", $tps['id']);
                                $stmtSuara->execute();
                                $suaraList = $stmtSuara->get_result()->fetch_all(MYSQLI_ASSOC);
                                
                                $tpsSuaraByCalon = [];
                                $suaraTidakSah = 0;
                                $tpsTotal = 0;
                                foreach ($suaraList as $s) {
                                    $tpsSuaraByCalon[$s['id_calon']] = $s['jumlah_suara'];
                                    $suaraTidakSah = $s['suara_tidak_sah'];
                                    $tpsTotal += $s['jumlah_suara'];
                                }
                                
                                // Hitung persentase per TPS
                                $tpsPercentages = [];
                                foreach ($suaraList as $s) {
                                    $tpsPercentages[$s['id_calon']] = $tpsTotal > 0 ? calculatePercentage($s['jumlah_suara'], $tpsTotal) : 0;
                                }
                            ?>
                            <tr>
                                <td class="text-center fw-bold">TPS <?= $tps['nomor_tps'] ?></td>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center"><?= formatNumber($tps['dpt']) ?></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if ($tps['sudah_input'] > 0): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Masuk</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-x"></i> Belum</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <?php if ($showAbsoluteNumbers): ?>
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $tpsPercentages[$calon['id']] ?? 0 ?>%</strong>
                                    <br><span class="text-muted"><?= formatNumber($tpsSuaraByCalon[$calon['id']] ?? 0) ?></span>
                                    <?php else: ?>
                                    <?= $tpsPercentages[$calon['id']] ?? 0 ?>%
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($showAbsoluteNumbers): ?>
                                <td class="text-center text-danger"><?= formatNumber($suaraTidakSah) ?></td>
                                <td class="text-center fw-bold"><?= formatNumber($tpsTotal) ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($showAbsoluteNumbers): ?>
                        <tfoot class="table-secondary">
                            <tr>
                                <td class="fw-bold">Total Suara</td>
                                <td></td>
                                <td></td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center">
                                    <strong style="color: <?= $calon['warna'] ?>"><?= $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0 ?>%</strong>
                                    <br><span class="fw-bold"><?= formatNumber($calon['total_suara']) ?></span>
                                </td>
                                <?php endforeach; ?>
                                <td></td>
                                <td class="text-center fw-bold"><?= formatNumber($totalSuara) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Last Update -->
        <div class="text-center mt-4">
            <small class="text-white">
                <i class="bi bi-clock me-1"></i>
                Data terakhir diperbarui: <?= date('d M Y H:i:s') ?> WIB
            </small>
        </div>
        
        <?php else: ?>
        <!-- Empty State - Pilih filter terlebih dahulu atau Data tidak tersedia -->
        <div class="text-center py-5">
            <div class="card shadow-sm mx-auto" style="max-width: 600px;">
                <div class="card-body py-5">
                    <?php if (!empty($noDataMessage)): ?>
                    <!-- Data tidak tersedia untuk filter yang dipilih -->
                    <i class="bi bi-exclamation-circle text-warning" style="font-size: 5rem;"></i>
                    <h3 class="mt-4 text-warning">Data Tidak Tersedia</h3>
                    <p class="text-muted mb-4">
                        <?= $noDataMessage ?>
                    </p>
                    <a href="publik.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                    <?php elseif ($filterProvinsi == 0): ?>
                    <!-- Belum pilih filter sama sekali -->
                    <i class="bi bi-funnel text-muted" style="font-size: 5rem;"></i>
                    <h3 class="mt-4 text-muted">Silakan Pilih Daerah</h3>
                    <p class="text-muted mb-4">
                        Pilih <strong>Provinsi</strong> untuk melihat hasil Pilgub, atau pilih 
                        <strong>Provinsi + Kabupaten/Kota</strong> untuk melihat hasil Pilbup/Pilwalkot
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <span class="badge bg-info fs-6 px-3 py-2">
                            <i class="bi bi-globe me-1"></i>Provinsi saja = Pilgub
                        </span>
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-building me-1"></i>+ Kabupaten = Pilbup/Pilwalkot
                        </span>
                    </div>
                    <?php else: ?>
                    <!-- Filter sudah dipilih tapi tidak ada data -->
                    <i class="bi bi-search text-muted" style="font-size: 5rem;"></i>
                    <h3 class="mt-4 text-muted">Data Tidak Ditemukan</h3>
                    <p class="text-muted mb-4">
                        Tidak ada data untuk filter yang dipilih. Silakan coba filter lain.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <div class="footer-section">
        <div class="container">
            <p class="mb-0">
                <i class="bi bi-shield-check me-2"></i>
                <?= htmlspecialchars($settings['nama_pemilihan']) ?> - Quick Count System
            </p>
            <small>Data bersifat sementara dan bukan hasil resmi</small>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <script>
        // Format number
        function formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        }
        
        // Permission flags from PHP
        const canViewKecamatan = <?= $canViewKecamatan ? 'true' : 'false' ?>;
        const canViewDesa = <?= $canViewDesa ? 'true' : 'false' ?>;
        
        // Filter cascading
        $('#filterProvinsi').change(function() {
            loadCascade('api/get-kabupaten.php', { id_provinsi: $(this).val() }, '#filterKabupaten');
            if (canViewKecamatan) {
                $('#filterKecamatan').html('<option value="">-- Semua --</option>');
            }
            if (canViewDesa) {
                $('#filterDesa').html('<option value="">-- Semua --</option>');
            }
        });
        
        $('#filterKabupaten').change(function() {
            if (canViewKecamatan) {
                loadCascade('api/get-kecamatan.php', { id_kabupaten: $(this).val() }, '#filterKecamatan');
            }
            if (canViewDesa) {
                $('#filterDesa').html('<option value="">-- Semua --</option>');
            }
        });
        
        $('#filterKecamatan').change(function() {
            if (canViewDesa) {
                loadCascade('api/get-desa.php', { id_kecamatan: $(this).val() }, '#filterDesa');
            }
        });
        
        function loadCascade(url, params, target) {
            $(target).html('<option value="">-- Semua --</option>');
            if (Object.values(params)[0]) {
                $.get(url, params, function(data) {
                    data.forEach(item => $(target).append('<option value="' + item.id + '">' + item.nama + '</option>'));
                });
            }
        }
        
        // Charts
        const chartLabels = <?= $chartLabels ?? '[]' ?>;
        const chartData = <?= $chartData ?? '[]' ?>;
        const chartColors = <?= $chartColors ?? '[]' ?>;
        const chartPercentages = <?= $chartPercentagesJson ?? '[]' ?>;
        const isQuickCount = <?= $showAbsoluteNumbers ? 'false' : 'true' ?>;
        
        // Pie Chart
        if (chartData.length > 0) {
            new Chart(document.getElementById('pieChart'), {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: isQuickCount ? chartPercentages : chartData,
                        backgroundColor: chartColors,
                        borderWidth: 3,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20, usePointStyle: true, font: { size: 12 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    if (isQuickCount) {
                                        return ctx.label + ': ' + ctx.raw.toFixed(2) + '%';
                                    } else {
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(2) : 0;
                                        return ctx.label + ': ' + formatNumber(ctx.raw) + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                }
            });
            
            // Bar Chart (only for Real Count)
            const barChartEl = document.getElementById('barChart');
            if (barChartEl) {
                new Chart(barChartEl, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Jumlah Suara',
                            data: chartData,
                            backgroundColor: chartColors,
                            borderColor: chartColors,
                            borderWidth: 1,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: function(value) { return formatNumber(value); } }
                            }
                        }
                    }
                });
            }
            
            // Bar Chart for Quick Count (percentage)
            const barChartQCEl = document.getElementById('barChartQC');
            if (barChartQCEl) {
                new Chart(barChartQCEl, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Persentase Suara',
                            data: chartPercentages,
                            backgroundColor: chartColors,
                            borderColor: chartColors,
                            borderWidth: 1,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y', // Horizontal bar chart
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return ctx.raw.toFixed(2) + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                ticks: { 
                                    callback: function(value) { return value + '%'; }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // DataTable
        $('#rekapTable').DataTable({
            paging: false,
            searching: false,
            info: false,
            order: [[0, 'asc']],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' }
        });
        
        // Auto refresh every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
