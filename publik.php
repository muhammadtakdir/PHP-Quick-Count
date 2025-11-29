<?php
/**
 * Halaman Publik - Quick Count System
 * Melihat hasil quick count tanpa login
 */

require_once 'config/config.php';

$settings = getSettings();
$conn = getConnection();

// Get filter parameters
$filterProvinsi = isset($_GET['provinsi']) ? intval($_GET['provinsi']) : ($settings['id_provinsi_aktif'] ?? 0);
$filterKabupaten = isset($_GET['kabupaten']) ? intval($_GET['kabupaten']) : ($settings['id_kabupaten_aktif'] ?? 0);
$filterKecamatan = isset($_GET['kecamatan']) ? intval($_GET['kecamatan']) : 0;
$filterDesa = isset($_GET['desa']) ? intval($_GET['desa']) : 0;

// Build filter array
$filter = ['jenis_pemilihan' => $settings['jenis_pemilihan']];
if ($filterProvinsi) $filter['id_provinsi'] = $filterProvinsi;
if ($filterKabupaten) $filter['id_kabupaten'] = $filterKabupaten;
if ($filterKecamatan) $filter['id_kecamatan'] = $filterKecamatan;
if ($filterDesa) $filter['id_desa'] = $filterDesa;

// Get rekap data
$rekap = getRekapSuara($filter);
$stats = getStatistikTPS($filter);
$breadcrumb = getLocationBreadcrumb($filterDesa, $filterKecamatan, $filterKabupaten, $filterProvinsi);

// Calculate totals
$totalSuara = array_sum(array_column($rekap, 'total_suara'));
$tpsProgress = $stats['total_tps'] > 0 ? round(($stats['tps_masuk'] / $stats['total_tps']) * 100, 2) : 0;

// Get dropdowns
$provinsiList = getProvinsi();
$kabupatenList = $filterProvinsi ? getKabupaten($filterProvinsi) : [];
$kecamatanList = $filterKabupaten ? getKecamatan($filterKabupaten) : [];
$desaList = $filterKecamatan ? getDesa($filterKecamatan) : [];

// Jenis pemilihan label
$jenisPemilihanLabel = [
    'pilpres' => 'Pemilihan Presiden',
    'pilgub' => 'Pemilihan Gubernur', 
    'pilbup' => 'Pemilihan Bupati',
    'pilwalkot' => 'Pemilihan Walikota'
];

// Prepare chart data
$chartLabels = json_encode(array_map(function($c) { return $c['nama_calon']; }, $rekap));
$chartData = json_encode(array_column($rekap, 'total_suara'));
$chartColors = json_encode(array_column($rekap, 'warna'));
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
                                <?= $jenisPemilihanLabel[$settings['jenis_pemilihan']] ?? 'Quick Count' ?>
                                <span class="badge bg-success ms-2 live-badge">
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
        <?php if (!empty($breadcrumb)): ?>
        <div class="text-center mb-4">
            <div class="breadcrumb-location">
                <i class="bi bi-geo-alt me-2"></i><?= implode(' &raquo; ', $breadcrumb) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filter -->
        <div class="card filter-card mb-4 no-print">
            <div class="card-body py-3">
                <form class="row g-2 align-items-center" id="filterForm">
                    <div class="col-md-3">
                        <select name="provinsi" id="filterProvinsi" class="form-select">
                            <option value="">-- Semua Provinsi --</option>
                            <?php foreach ($provinsiList as $prov): ?>
                            <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="kabupaten" id="filterKabupaten" class="form-select">
                            <option value="">-- Semua Kabupaten --</option>
                            <?php foreach ($kabupatenList as $kab): ?>
                            <option value="<?= $kab['id'] ?>" <?= $filterKabupaten == $kab['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kab['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistics -->
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
        
        <!-- Progress Bar -->
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
                        <?= $stats['tps_masuk'] ?> / <?= $stats['total_tps'] ?> TPS
                    </div>
                </div>
            </div>
        </div>
        
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
                                    <h4 class="mb-0" style="color: <?= $calon['warna'] ?>"><?= formatNumber($calon['total_suara']) ?></h4>
                                    <span class="badge" style="background: <?= $calon['warna'] ?>"><?= $percentage ?>%</span>
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
        
        <!-- Bar Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Grafik Perbandingan Suara
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Rekap per Kecamatan -->
        <?php if ($filterKabupaten && !$filterKecamatan): 
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
                                <th class="text-center">TPS</th>
                                <th class="text-center">Masuk</th>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">No. <?= $calon['nomor_urut'] ?></th>
                                <?php endforeach; ?>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $kecList = getKecamatan($filterKabupaten);
                            foreach ($kecList as $kec):
                                $kecFilter = array_merge($filter, ['id_kecamatan' => $kec['id']]);
                                $kecRekap = getRekapSuara($kecFilter);
                                $kecStats = getStatistikTPS($kecFilter);
                                $kecTotal = array_sum(array_column($kecRekap, 'total_suara'));
                                
                                $kecSuaraByCalon = [];
                                foreach ($kecRekap as $kr) {
                                    $kecSuaraByCalon[$kr['id']] = $kr['total_suara'];
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="?provinsi=<?= $filterProvinsi ?>&kabupaten=<?= $filterKabupaten ?>&kecamatan=<?= $kec['id'] ?>">
                                        <?= htmlspecialchars($kec['nama']) ?>
                                    </a>
                                </td>
                                <td class="text-center"><?= $kecStats['total_tps'] ?></td>
                                <td class="text-center"><?= $kecStats['tps_masuk'] ?></td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center"><?= formatNumber($kecSuaraByCalon[$calon['id']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="text-center fw-bold"><?= formatNumber($kecTotal) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rekap per Desa -->
        <?php if ($filterKecamatan && !$filterDesa): 
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
                                <th class="text-center">TPS</th>
                                <th class="text-center">Masuk</th>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">No. <?= $calon['nomor_urut'] ?></th>
                                <?php endforeach; ?>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $desaListRekap = getDesa($filterKecamatan);
                            foreach ($desaListRekap as $desa):
                                $desaFilter = array_merge($filter, ['id_desa' => $desa['id']]);
                                $desaRekap = getRekapSuara($desaFilter);
                                $desaStats = getStatistikTPS($desaFilter);
                                $desaTotal = array_sum(array_column($desaRekap, 'total_suara'));
                                
                                $desaSuaraByCalon = [];
                                foreach ($desaRekap as $dr) {
                                    $desaSuaraByCalon[$dr['id']] = $dr['total_suara'];
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="?provinsi=<?= $filterProvinsi ?>&kabupaten=<?= $filterKabupaten ?>&kecamatan=<?= $filterKecamatan ?>&desa=<?= $desa['id'] ?>">
                                        <?= ($desa['tipe'] == 'kelurahan' ? 'Kel. ' : '') . htmlspecialchars($desa['nama']) ?>
                                    </a>
                                </td>
                                <td class="text-center"><?= $desaStats['total_tps'] ?></td>
                                <td class="text-center"><?= $desaStats['tps_masuk'] ?></td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center"><?= formatNumber($desaSuaraByCalon[$calon['id']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="text-center fw-bold"><?= formatNumber($desaTotal) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rekap per TPS -->
        <?php if ($filterDesa): 
            $calonList = $rekap;
            
            $sqlTps = "SELECT t.*, (SELECT COUNT(*) FROM suara WHERE id_tps = t.id) as sudah_input
                       FROM tps t WHERE t.id_desa = ? AND t.is_active = 1 ORDER BY t.nomor_tps";
            $stmtTps = $conn->prepare($sqlTps);
            $stmtTps->bind_param("i", $filterDesa);
            $stmtTps->execute();
            $tpsListRekap = $stmtTps->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-box me-2"></i>Rekap Per TPS
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="rekapTable">
                        <thead>
                            <tr>
                                <th class="text-center">TPS</th>
                                <th class="text-center">DPT</th>
                                <th class="text-center">Status</th>
                                <?php foreach ($calonList as $calon): ?>
                                <th class="text-center" style="color: <?= $calon['warna'] ?>">No. <?= $calon['nomor_urut'] ?></th>
                                <?php endforeach; ?>
                                <th class="text-center">Tdk Sah</th>
                                <th class="text-center">Total</th>
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
                            ?>
                            <tr>
                                <td class="text-center fw-bold">TPS <?= $tps['nomor_tps'] ?></td>
                                <td class="text-center"><?= formatNumber($tps['dpt']) ?></td>
                                <td class="text-center">
                                    <?php if ($tps['sudah_input'] > 0): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Masuk</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-x"></i> Belum</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($calonList as $calon): ?>
                                <td class="text-center"><?= formatNumber($tpsSuaraByCalon[$calon['id']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="text-center text-danger"><?= formatNumber($suaraTidakSah) ?></td>
                                <td class="text-center fw-bold"><?= formatNumber($tpsTotal) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
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
        
        // Filter cascading
        $('#filterProvinsi').change(function() {
            loadCascade('api/get-kabupaten.php', { id_provinsi: $(this).val() }, '#filterKabupaten');
            $('#filterKecamatan, #filterDesa').html('<option value="">-- Semua --</option>');
        });
        
        $('#filterKabupaten').change(function() {
            loadCascade('api/get-kecamatan.php', { id_kabupaten: $(this).val() }, '#filterKecamatan');
            $('#filterDesa').html('<option value="">-- Semua --</option>');
        });
        
        $('#filterKecamatan').change(function() {
            loadCascade('api/get-desa.php', { id_kecamatan: $(this).val() }, '#filterDesa');
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
        const chartLabels = <?= $chartLabels ?>;
        const chartData = <?= $chartData ?>;
        const chartColors = <?= $chartColors ?>;
        
        // Pie Chart
        if (chartData.length > 0) {
            new Chart(document.getElementById('pieChart'), {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
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
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(2) : 0;
                                    return ctx.label + ': ' + formatNumber(ctx.raw) + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Bar Chart
            new Chart(document.getElementById('barChart'), {
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
