<?php
/**
 * Dashboard - Quick Count System
 */

require_once 'config/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$settings = getSettings();

// Get statistics
$stats = getStatistikTPS([
    'id_provinsi' => $settings['id_provinsi_aktif'],
    'id_kabupaten' => $settings['id_kabupaten_aktif']
]);

// Get rekap suara
$filter = ['jenis_pemilihan' => $settings['jenis_pemilihan']];
if ($settings['id_provinsi_aktif']) $filter['id_provinsi'] = $settings['id_provinsi_aktif'];
if ($settings['id_kabupaten_aktif']) $filter['id_kabupaten'] = $settings['id_kabupaten_aktif'];
$rekap = getRekapSuara($filter);

// Calculate totals
$totalSuara = array_sum(array_column($rekap, 'total_suara'));
$tpsProgress = $stats['total_tps'] > 0 ? round(($stats['tps_masuk'] / $stats['total_tps']) * 100, 1) : 0;

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Dashboard</h1>
        <p>Selamat datang di <?= htmlspecialchars($settings['nama_pemilihan']) ?></p>
    </div>
    <div>
        <span class="badge bg-primary fs-6">
            <?= ucfirst(str_replace(['pilpres', 'pilgub', 'pilbup', 'pilwalkot'], 
                ['Pilpres', 'Pilgub', 'Pilbup', 'Pilwalkot'], $settings['jenis_pemilihan'])) ?>
        </span>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="stat-card primary">
            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            <div class="stat-value"><?= formatNumber($stats['total_tps']) ?></div>
            <div class="stat-label">Total TPS</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="stat-card success">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?= formatNumber($stats['tps_masuk']) ?></div>
            <div class="stat-label">TPS Masuk (<?= $tpsProgress ?>%)</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="stat-card warning">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= formatNumber($stats['total_dpt']) ?></div>
            <div class="stat-label">Total DPT</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="stat-card info">
            <div class="stat-icon"><i class="bi bi-card-checklist"></i></div>
            <div class="stat-value"><?= formatNumber($totalSuara) ?></div>
            <div class="stat-label">Total Suara Masuk</div>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Progress Input TPS</span>
            <span class="text-muted"><?= $stats['tps_masuk'] ?> / <?= $stats['total_tps'] ?> TPS</span>
        </div>
        <div class="progress" style="height: 25px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: <?= $tpsProgress ?>%"
                 aria-valuenow="<?= $tpsProgress ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                <?= $tpsProgress ?>%
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Chart -->
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pie-chart me-2"></i>Perolehan Suara</span>
                <a href="grafik.php" class="btn btn-sm btn-outline-primary">Detail</a>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="suaraChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kandidat List -->
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-person-badge me-2"></i>Daftar Calon
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($rekap as $calon): 
                        $percentage = $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0;
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; background: <?= $calon['warna'] ?>; color: white; font-weight: bold; font-size: 20px;">
                                    <?= $calon['nomor_urut'] ?>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1"><?= htmlspecialchars($calon['nama_calon']) ?></h6>
                                <?php if ($calon['nama_wakil']): ?>
                                <small class="text-muted"><?= htmlspecialchars($calon['nama_wakil']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0" style="color: <?= $calon['warna'] ?>"><?= formatNumber($calon['total_suara']) ?></h5>
                                <small class="text-muted"><?= $percentage ?>%</small>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar" style="width: <?= $percentage ?>%; background: <?= $calon['warna'] ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($rekap)): ?>
                    <div class="list-group-item text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mb-0 mt-2">Belum ada data calon</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Aksi Cepat
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="input-suara.php" class="btn btn-primary w-100 py-3">
                            <i class="bi bi-pencil-square fs-4 d-block mb-2"></i>
                            Input Suara
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="grafik.php" class="btn btn-success w-100 py-3">
                            <i class="bi bi-bar-chart fs-4 d-block mb-2"></i>
                            Lihat Grafik
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="pages/calon.php" class="btn btn-warning w-100 py-3">
                            <i class="bi bi-people fs-4 d-block mb-2"></i>
                            Kelola Calon
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="pages/tps.php" class="btn btn-info w-100 py-3">
                            <i class="bi bi-box fs-4 d-block mb-2"></i>
                            Kelola TPS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Prepare chart data for JavaScript
$chartLabels = array_map(function($c) { 
    return addslashes($c['nama_calon']); 
}, $rekap);

$chartData = array_column($rekap, 'total_suara');

$chartColors = array_map(function($c) { 
    return $c['warna']; 
}, $rekap);

$additionalJS = '<script>
// Prepare chart data
const chartLabels = ' . json_encode($chartLabels) . ';
const chartData = ' . json_encode(array_map('intval', $chartData)) . ';
const chartColors = ' . json_encode($chartColors) . ';

// Create chart
const ctx = document.getElementById("suaraChart").getContext("2d");
new Chart(ctx, {
    type: "doughnut",
    data: {
        labels: chartLabels,
        datasets: [{
            data: chartData,
            backgroundColor: chartColors,
            borderWidth: 2,
            borderColor: "#fff"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom",
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.raw;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                        return context.label + ": " + value.toLocaleString("id-ID") + " (" + percentage + "%)";
                    }
                }
            }
        }
    }
});
</script>';

include 'includes/footer.php';
?>
