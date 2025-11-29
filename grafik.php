<?php
/**
 * Grafik & Rekap - Quick Count System
 */

require_once 'config/config.php';
requireLogin();

$pageTitle = 'Grafik & Rekap';
$conn = getConnection();
$settings = getSettings();

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

// Prepare chart data
$chartLabels = json_encode(array_map(function($c) { return $c['nama_calon']; }, $rekap));
$chartData = json_encode(array_column($rekap, 'total_suara'));
$chartColors = json_encode(array_column($rekap, 'warna'));

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1>Grafik & Rekap Suara</h1>
        <p>
            <?php if (!empty($breadcrumb)): ?>
            <?= implode(' &raquo; ', $breadcrumb) ?>
            <?php else: ?>
            Seluruh Wilayah
            <?php endif; ?>
        </p>
    </div>
    <div>
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer me-2"></i>Cetak
        </button>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center" id="filterForm">
            <div class="col-md-3">
                <select name="provinsi" id="filterProvinsi" class="form-select form-select-sm">
                    <option value="">-- Semua Provinsi --</option>
                    <?php foreach ($provinsiList as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="kabupaten" id="filterKabupaten" class="form-select form-select-sm">
                    <option value="">-- Semua Kabupaten --</option>
                    <?php foreach ($kabupatenList as $kab): ?>
                    <option value="<?= $kab['id'] ?>" <?= $filterKabupaten == $kab['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kab['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="kecamatan" id="filterKecamatan" class="form-select form-select-sm">
                    <option value="">-- Semua Kec --</option>
                    <?php foreach ($kecamatanList as $kec): ?>
                    <option value="<?= $kec['id'] ?>" <?= $filterKecamatan == $kec['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kec['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="desa" id="filterDesa" class="form-select form-select-sm">
                    <option value="">-- Semua Desa --</option>
                    <?php foreach ($desaList as $ds): ?>
                    <option value="<?= $ds['id'] ?>" <?= $filterDesa == $ds['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ds['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card primary h-100">
            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            <div class="stat-value"><?= formatNumber($stats['total_tps']) ?></div>
            <div class="stat-label">Total TPS</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card success h-100">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?= formatNumber($stats['tps_masuk']) ?></div>
            <div class="stat-label">TPS Masuk (<?= $tpsProgress ?>%)</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card warning h-100">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= formatNumber($stats['total_dpt']) ?></div>
            <div class="stat-label">Total DPT</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card info h-100">
            <div class="stat-icon"><i class="bi bi-card-checklist"></i></div>
            <div class="stat-value"><?= formatNumber($totalSuara) ?></div>
            <div class="stat-label">Total Suara Masuk</div>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Progress Input TPS</span>
            <span><?= $stats['tps_masuk'] ?> / <?= $stats['total_tps'] ?> TPS (<?= $tpsProgress ?>%)</span>
        </div>
        <div class="progress" style="height: 25px;">
            <div class="progress-bar progress-bar-striped bg-success" 
                 style="width: <?= $tpsProgress ?>%"><?= $tpsProgress ?>%</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Pie Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Diagram Perolehan Suara
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
                <i class="bi bi-bar-chart me-2"></i>Perbandingan Suara
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detail Rekap Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Tabel Rekap Perolehan Suara</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="60" class="text-center">No</th>
                        <th>Pasangan Calon</th>
                        <th width="120" class="text-end">Jumlah Suara</th>
                        <th width="100" class="text-center">Persentase</th>
                        <th width="200">Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxSuara = max(array_column($rekap, 'total_suara') ?: [1]);
                    foreach ($rekap as $calon): 
                        $percentage = $totalSuara > 0 ? calculatePercentage($calon['total_suara'], $totalSuara) : 0;
                        $barWidth = $maxSuara > 0 ? ($calon['total_suara'] / $maxSuara) * 100 : 0;
                    ?>
                    <tr>
                        <td class="text-center">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                 style="width: 35px; height: 35px; background: <?= $calon['warna'] ?>; color: white; font-weight: bold;">
                                <?= $calon['nomor_urut'] ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($calon['nama_calon']) ?></strong>
                            <?php if ($calon['nama_wakil']): ?>
                            <br><small class="text-muted">&amp; <?= htmlspecialchars($calon['nama_wakil']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold" style="color: <?= $calon['warna'] ?>">
                            <?= formatNumber($calon['total_suara']) ?>
                        </td>
                        <td class="text-center">
                            <span class="badge" style="background: <?= $calon['warna'] ?>; font-size: 1rem;">
                                <?= $percentage ?>%
                            </span>
                        </td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" 
                                     style="width: <?= $barWidth ?>%; background: <?= $calon['warna'] ?>">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($rekap)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mb-0 mt-2">Belum ada data</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($rekap)): ?>
                <tfoot class="table-secondary">
                    <tr>
                        <th colspan="2" class="text-end">TOTAL SUARA SAH:</th>
                        <th class="text-end"><?= formatNumber($totalSuara) ?></th>
                        <th class="text-center">100%</th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Rekap per Kecamatan (if kabupaten filter is active) -->
<?php if ($filterKabupaten && !$filterKecamatan): 
    // Get list of candidates - use rekap from kabupaten level
    $calonList = $rekap;
?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-task me-2"></i>Rekap Per Kecamatan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="rekapKecTable">
                <thead class="table-light">
                    <tr>
                        <th>Kecamatan</th>
                        <th class="text-center">TPS</th>
                        <th class="text-center">Masuk</th>
                        <?php foreach ($calonList as $calon): ?>
                        <th class="text-center" style="color: <?= $calon['warna'] ?>">
                            No. <?= $calon['nomor_urut'] ?>
                        </th>
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
                        
                        // Create indexed array by calon id for consistent column mapping
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

<!-- Rekap per Desa (if kecamatan filter is active) -->
<?php if ($filterKecamatan && !$filterDesa): 
    $calonList = $rekap;
?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-task me-2"></i>Rekap Per Desa/Kelurahan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="rekapDesaTable">
                <thead class="table-light">
                    <tr>
                        <th>Desa/Kelurahan</th>
                        <th class="text-center">TPS</th>
                        <th class="text-center">Masuk</th>
                        <?php foreach ($calonList as $calon): ?>
                        <th class="text-center" style="color: <?= $calon['warna'] ?>">
                            No. <?= $calon['nomor_urut'] ?>
                        </th>
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

<!-- Rekap per TPS (if desa filter is active) -->
<?php if ($filterDesa): 
    $calonList = $rekap;
    $conn = getConnection();
    
    // Get TPS list with suara data
    $sqlTps = "SELECT t.*, 
               (SELECT COUNT(*) FROM suara WHERE id_tps = t.id) as sudah_input
               FROM tps t 
               WHERE t.id_desa = ? AND t.is_active = 1
               ORDER BY t.nomor_tps";
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
            <table class="table table-hover table-sm mb-0" id="rekapTpsTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" width="80">TPS</th>
                        <th class="text-center" width="80">DPT</th>
                        <th class="text-center" width="80">Status</th>
                        <?php foreach ($calonList as $calon): ?>
                        <th class="text-center" style="color: <?= $calon['warna'] ?>">
                            No. <?= $calon['nomor_urut'] ?>
                        </th>
                        <?php endforeach; ?>
                        <th class="text-center" width="80">Tdk Sah</th>
                        <th class="text-center" width="100">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($tpsListRekap as $tps):
                        // Get suara for this TPS
                        $sqlSuara = "SELECT s.*, c.nomor_urut 
                                     FROM suara s 
                                     JOIN calon c ON s.id_calon = c.id 
                                     WHERE s.id_tps = ?";
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
                            <span class="badge bg-success"><i class="bi bi-check"></i></span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-x"></i></span>
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
                <?php if (!empty($tpsListRekap)): 
                    // Calculate totals
                    $totalDpt = array_sum(array_column($tpsListRekap, 'dpt'));
                    $totalMasuk = count(array_filter($tpsListRekap, fn($t) => $t['sudah_input'] > 0));
                ?>
                <tfoot class="table-secondary">
                    <tr>
                        <th class="text-center">Total</th>
                        <th class="text-center"><?= formatNumber($totalDpt) ?></th>
                        <th class="text-center"><?= $totalMasuk ?>/<?= count($tpsListRekap) ?></th>
                        <?php foreach ($calonList as $calon): ?>
                        <th class="text-center" style="color: <?= $calon['warna'] ?>"><?= formatNumber($rekap[array_search($calon['id'], array_column($rekap, 'id'))]['total_suara'] ?? 0) ?></th>
                        <?php endforeach; ?>
                        <th class="text-center">-</th>
                        <th class="text-center"><?= formatNumber($totalSuara) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$additionalJS = <<<JS
<script>
// Filter cascading
$('#filterProvinsi').change(function() {
    loadCascade('api/get-kabupaten.php', { id_provinsi: $(this).val() }, '#filterKabupaten');
    $('#filterKecamatan, #filterDesa').html('<option value="">-- Semua --</option>');
});

$('#filterKabupaten').change(function() {
    loadCascade('api/get-kecamatan.php', { id_kabupaten: $(this).val() }, '#filterKecamatan');
    $('#filterDesa').html('<option value="">-- Semua Desa --</option>');
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
const chartLabels = {$chartLabels};
const chartData = {$chartData};
const chartColors = {$chartColors};

// Pie Chart
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: chartLabels,
        datasets: [{
            data: chartData,
            backgroundColor: chartColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, usePointStyle: true }
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
            borderWidth: 1
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
                ticks: {
                    callback: function(value) { return formatNumber(value); }
                }
            }
        }
    }
});

$('#rekapKecTable').DataTable({
    paging: false,
    searching: false,
    info: false,
    order: [[0, 'asc']]
});

$('#rekapDesaTable').DataTable({
    paging: false,
    searching: false,
    info: false,
    order: [[0, 'asc']]
});

$('#rekapTpsTable').DataTable({
    paging: false,
    searching: false,
    info: false,
    order: [[0, 'asc']]
});
</script>
JS;

include 'includes/footer.php';
?>
