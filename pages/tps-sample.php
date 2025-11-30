<?php
/**
 * TPS Sample Management - Quick Count Mode
 * Manage which TPS to include in quick count sampling
 */

require_once '../config/config.php';
requireAdmin();

$pageTitle = 'Kelola TPS Sampel';
$conn = getConnection();
$settings = getSettings();

// Check if quick count mode
$isQuickCount = ($settings['jenis_hitung'] ?? 'real_count') === 'quick_count';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'random_select') {
        // Random select TPS
        $jumlah = intval($_POST['jumlah'] ?? 100);
        $perKecamatan = isset($_POST['per_kecamatan']);
        
        // Clear existing samples
        $conn->query("DELETE FROM tps_sample");
        
        if ($perKecamatan) {
            // Select proportionally from each kecamatan
            $kecList = $conn->query("SELECT k.id, COUNT(t.id) as total_tps 
                                     FROM kecamatan k 
                                     JOIN desa d ON d.id_kecamatan = k.id 
                                     JOIN tps t ON t.id_desa = d.id 
                                     WHERE t.is_active = 1 
                                     GROUP BY k.id");
            
            $totalTps = 0;
            $kecData = [];
            while ($row = $kecList->fetch_assoc()) {
                $totalTps += $row['total_tps'];
                $kecData[] = $row;
            }
            
            foreach ($kecData as $kec) {
                $proportion = $kec['total_tps'] / $totalTps;
                $kecSample = max(1, round($jumlah * $proportion));
                
                $sql = "INSERT INTO tps_sample (tps_id) 
                        SELECT t.id FROM tps t 
                        JOIN desa d ON t.id_desa = d.id 
                        WHERE d.id_kecamatan = ? AND t.is_active = 1 
                        ORDER BY RAND() 
                        LIMIT ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $kec['id'], $kecSample);
                $stmt->execute();
            }
        } else {
            // Random select from all TPS
            $sql = "INSERT INTO tps_sample (tps_id) 
                    SELECT id FROM tps WHERE is_active = 1 
                    ORDER BY RAND() 
                    LIMIT ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $jumlah);
            $stmt->execute();
        }
        
        // Update settings
        $count = $conn->query("SELECT COUNT(*) as total FROM tps_sample")->fetch_assoc()['total'];
        $conn->query("UPDATE settings SET jumlah_tps_sample = $count WHERE id = 1");
        
        setFlash('success', "Berhasil memilih $count TPS sampel secara acak!");
        header('Location: tps-sample.php');
        exit;
    }
    
    if ($action === 'clear_all') {
        $conn->query("DELETE FROM tps_sample");
        $conn->query("UPDATE settings SET jumlah_tps_sample = 0 WHERE id = 1");
        setFlash('success', 'Semua TPS sampel telah dihapus!');
        header('Location: tps-sample.php');
        exit;
    }
    
    if ($action === 'toggle_tps') {
        $tps_id = intval($_POST['tps_id'] ?? 0);
        $is_selected = intval($_POST['is_selected'] ?? 0);
        
        if ($is_selected) {
            $stmt = $conn->prepare("INSERT IGNORE INTO tps_sample (tps_id) VALUES (?)");
            $stmt->bind_param("i", $tps_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("DELETE FROM tps_sample WHERE tps_id = ?");
            $stmt->bind_param("i", $tps_id);
            $stmt->execute();
        }
        
        // Update count
        $count = $conn->query("SELECT COUNT(*) as total FROM tps_sample")->fetch_assoc()['total'];
        $conn->query("UPDATE settings SET jumlah_tps_sample = $count WHERE id = 1");
        
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }
}

// Get statistics
$totalTps = $conn->query("SELECT COUNT(*) as total FROM tps WHERE is_active = 1")->fetch_assoc()['total'];
$sampledTps = $conn->query("SELECT COUNT(*) as total FROM tps_sample")->fetch_assoc()['total'];
$percentage = $totalTps > 0 ? round(($sampledTps / $totalTps) * 100, 1) : 0;

// Get TPS list with sample status
$filter_kecamatan = isset($_GET['kecamatan']) ? intval($_GET['kecamatan']) : 0;
$filter_desa = isset($_GET['desa']) ? intval($_GET['desa']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT t.id, t.nomor_tps, d.nama as desa, k.nama as kecamatan,
        (SELECT 1 FROM tps_sample ts WHERE ts.tps_id = t.id) as is_sampled
        FROM tps t
        JOIN desa d ON t.id_desa = d.id
        JOIN kecamatan k ON d.id_kecamatan = k.id
        WHERE t.is_active = 1";

$params = [];
$types = "";

if ($filter_kecamatan) {
    $sql .= " AND k.id = ?";
    $params[] = $filter_kecamatan;
    $types .= "i";
}

if ($filter_desa) {
    $sql .= " AND d.id = ?";
    $params[] = $filter_desa;
    $types .= "i";
}

if ($filter_status === 'sampled') {
    $sql .= " AND EXISTS (SELECT 1 FROM tps_sample ts WHERE ts.tps_id = t.id)";
} elseif ($filter_status === 'not_sampled') {
    $sql .= " AND NOT EXISTS (SELECT 1 FROM tps_sample ts WHERE ts.tps_id = t.id)";
}

$sql .= " ORDER BY k.nama, d.nama, t.nomor_tps";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$tpsList = $result->fetch_all(MYSQLI_ASSOC);

// Get kecamatan list for filter
$kecamatanList = $conn->query("SELECT id, nama FROM kecamatan ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-pin-map me-2"></i>Kelola TPS Sampel</h1>
        <p>Pilih TPS yang akan digunakan untuk quick count</p>
    </div>
    <a href="settings.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Kembali ke Settings
    </a>
</div>

<?php if (!$isQuickCount): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Perhatian!</strong> Mode perhitungan saat ini adalah <strong>Real Count</strong>. 
    TPS sampel hanya digunakan pada mode <strong>Quick Count</strong>.
    <a href="settings.php" class="alert-link">Ubah ke Quick Count</a>
</div>
<?php endif; ?>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2">Total TPS</h6>
                <h2 class="card-title mb-0"><?= number_format($totalTps) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2">TPS Sampel</h6>
                <h2 class="card-title mb-0"><?= number_format($sampledTps) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2">Persentase Sampel</h6>
                <h2 class="card-title mb-0"><?= $percentage ?>%</h2>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-lightning me-2"></i>Aksi Cepat
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end" id="randomForm">
            <input type="hidden" name="action" value="random_select">
            <div class="col-md-3">
                <label class="form-label">Jumlah TPS Sampel</label>
                <input type="number" name="jumlah" class="form-control" value="100" min="1" max="<?= $totalTps ?>">
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="per_kecamatan" id="perKecamatan" checked>
                    <label class="form-check-label" for="perKecamatan">
                        Proporsional per kecamatan
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-shuffle me-2"></i>Pilih Acak
                </button>
            </div>
            <div class="col-md-2">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Hapus semua TPS sampel?')">
                        <i class="bi bi-trash me-2"></i>Hapus Semua
                    </button>
                </form>
            </div>
        </form>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-funnel me-2"></i>Filter
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Kecamatan</label>
                <select name="kecamatan" class="form-select" id="filterKecamatan">
                    <option value="">-- Semua Kecamatan --</option>
                    <?php foreach ($kecamatanList as $kec): ?>
                    <option value="<?= $kec['id'] ?>" <?= $filter_kecamatan == $kec['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kec['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Desa</label>
                <select name="desa" class="form-select" id="filterDesa">
                    <option value="">-- Semua Desa --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="sampled" <?= $filter_status === 'sampled' ? 'selected' : '' ?>>Sudah Dipilih</option>
                    <option value="not_sampled" <?= $filter_status === 'not_sampled' ? 'selected' : '' ?>>Belum Dipilih</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary me-2">
                    <i class="bi bi-search me-2"></i>Filter
                </button>
                <a href="tps-sample.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- TPS List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list me-2"></i>Daftar TPS (<?= number_format(count($tpsList)) ?> data)</span>
        <div>
            <button class="btn btn-sm btn-success" id="selectAllVisible">
                <i class="bi bi-check-all me-1"></i>Pilih Semua
            </button>
            <button class="btn btn-sm btn-outline-danger" id="unselectAllVisible">
                <i class="bi bi-x-lg me-1"></i>Hapus Semua
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="tpsTable">
                <thead>
                    <tr>
                        <th width="60">Sampel</th>
                        <th>Kecamatan</th>
                        <th>Desa</th>
                        <th>TPS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tpsList as $tps): ?>
                    <tr>
                        <td class="text-center">
                            <div class="form-check">
                                <input class="form-check-input tps-checkbox" type="checkbox" 
                                       data-tps-id="<?= $tps['id'] ?>"
                                       <?= $tps['is_sampled'] ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($tps['kecamatan']) ?></td>
                        <td><?= htmlspecialchars($tps['desa']) ?></td>
                        <td>TPS <?= $tps['nomor_tps'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$additionalJS = <<<JS
<script>
// Toggle individual TPS
$('.tps-checkbox').on('change', function() {
    const tpsId = $(this).data('tps-id');
    const isSelected = $(this).is(':checked') ? 1 : 0;
    
    $.post('tps-sample.php', {
        action: 'toggle_tps',
        tps_id: tpsId,
        is_selected: isSelected
    }, function(response) {
        if (response.success) {
            // Update count somewhere if needed
        }
    }, 'json');
});

// Select all visible
$('#selectAllVisible').on('click', function() {
    $('.tps-checkbox:not(:checked)').each(function() {
        $(this).prop('checked', true).trigger('change');
    });
});

// Unselect all visible
$('#unselectAllVisible').on('click', function() {
    $('.tps-checkbox:checked').each(function() {
        $(this).prop('checked', false).trigger('change');
    });
});

// Filter kecamatan -> desa
$('#filterKecamatan').on('change', function() {
    const idKec = $(this).val();
    const desaSelect = $('#filterDesa');
    
    desaSelect.html('<option value="">-- Semua Desa --</option>');
    
    if (idKec) {
        $.get('../api/get-desa.php', { id_kecamatan: idKec }, function(data) {
            data.forEach(function(item) {
                desaSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
            });
        });
    }
});

// Initialize DataTable
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#tpsTable').DataTable({
            pageLength: 50,
            order: [[1, 'asc'], [2, 'asc'], [3, 'asc']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            }
        });
    }
});
</script>
JS;

include '../includes/footer.php';
?>
