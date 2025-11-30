<?php
/**
 * CRUD TPS - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

// Viewer tidak bisa edit master data
$isViewer = hasRole(['viewer']);

$pageTitle = 'Master TPS';
$conn = getConnection();
$settings = getSettings();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete (hanya admin/operator)
if ($action == 'delete' && $id > 0 && !$isViewer) {
    $stmt = $conn->prepare("DELETE FROM tps WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Data TPS berhasil dihapus!');
    } else {
        setFlash('error', 'Gagal menghapus data TPS!');
    }
    header('Location: tps.php');
    exit;
}

// Save (Add/Edit) - hanya admin/operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_add']) && !$isViewer) {
    $id = intval($_POST['id']);
    $id_desa = intval($_POST['id_desa']);
    $nomor_tps = intval($_POST['nomor_tps']);
    $dpt = intval($_POST['dpt']);
    $alamat = sanitize($_POST['alamat'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tps SET id_desa = ?, nomor_tps = ?, dpt = ?, alamat = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("iiisii", $id_desa, $nomor_tps, $dpt, $alamat, $is_active, $id);
        $message = 'Data TPS berhasil diupdate!';
    } else {
        $stmt = $conn->prepare("INSERT INTO tps (id_desa, nomor_tps, dpt, alamat, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisi", $id_desa, $nomor_tps, $dpt, $alamat, $is_active);
        $message = 'Data TPS berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data TPS! ' . $conn->error);
    }
    header('Location: tps.php');
    exit;
}

// Bulk add TPS (hanya admin/operator)
if (isset($_POST['bulk_add']) && !$isViewer) {
    $id_desa = intval($_POST['bulk_desa']);
    $jumlah = intval($_POST['jumlah_tps']);
    $dpt_default = intval($_POST['dpt_default']);
    
    $success = 0;
    for ($i = 1; $i <= $jumlah; $i++) {
        $stmt = $conn->prepare("INSERT IGNORE INTO tps (id_desa, nomor_tps, dpt) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $id_desa, $i, $dpt_default);
        if ($stmt->execute()) $success++;
    }
    
    setFlash('success', "Berhasil menambahkan $success TPS!");
    header('Location: tps.php');
    exit;
}

// Get filters - use settings as default
$filterDesa = isset($_GET['desa']) ? intval($_GET['desa']) : 0;
$filterKecamatan = isset($_GET['kecamatan']) ? intval($_GET['kecamatan']) : 0;
$filterKabupaten = isset($_GET['kabupaten']) ? intval($_GET['kabupaten']) : ($settings['id_kabupaten_aktif'] ?? 0);
$filterProvinsi = isset($_GET['provinsi']) ? intval($_GET['provinsi']) : ($settings['id_provinsi_aktif'] ?? 0);

// Force filter by settings if configured
if (!empty($settings['id_provinsi_aktif'])) {
    $filterProvinsi = $settings['id_provinsi_aktif'];
}
if (!empty($settings['id_kabupaten_aktif'])) {
    $filterKabupaten = $settings['id_kabupaten_aktif'];
}

// Get dropdowns
$provinsiList = getProvinsi(false);

// Jika provinsi sudah dikunci di settings, ambil kabupaten dari provinsi tersebut
if (!empty($settings['id_provinsi_aktif'])) {
    $kabupatenFilter = getKabupaten($settings['id_provinsi_aktif'], false);
} else {
    $kabupatenFilter = $filterProvinsi > 0 ? getKabupaten($filterProvinsi, false) : [];
}

// Jika kabupaten sudah dikunci di settings, ambil kecamatan dari kabupaten tersebut
if (!empty($settings['id_kabupaten_aktif'])) {
    $kecamatanFilter = getKecamatan($settings['id_kabupaten_aktif'], false);
} else {
    $kecamatanFilter = $filterKabupaten > 0 ? getKecamatan($filterKabupaten, false) : [];
}

// Ambil desa berdasarkan kecamatan yang dipilih
$desaFilter = $filterKecamatan > 0 ? getDesa($filterKecamatan, false) : [];

// Get TPS data
$sql = "SELECT t.*, d.nama as nama_desa, d.tipe as tipe_desa,
        kec.nama as nama_kecamatan, k.nama as nama_kabupaten,
        (SELECT COUNT(*) FROM suara WHERE id_tps = t.id) as sudah_input
        FROM tps t
        JOIN desa d ON t.id_desa = d.id
        JOIN kecamatan kec ON d.id_kecamatan = kec.id
        JOIN kabupaten k ON kec.id_kabupaten = k.id
        WHERE 1=1";

// Always filter by configured kabupaten if set
if (!empty($settings['id_kabupaten_aktif'])) {
    $sql .= " AND kec.id_kabupaten = " . intval($settings['id_kabupaten_aktif']);
    if ($filterKecamatan > 0) {
        $sql .= " AND d.id_kecamatan = " . $filterKecamatan;
    }
    if ($filterDesa > 0) {
        $sql .= " AND t.id_desa = " . $filterDesa;
    }
} elseif (!empty($settings['id_provinsi_aktif'])) {
    $sql .= " AND k.id_provinsi = " . intval($settings['id_provinsi_aktif']);
    if ($filterKabupaten > 0) {
        $sql .= " AND kec.id_kabupaten = " . $filterKabupaten;
    }
    if ($filterKecamatan > 0) {
        $sql .= " AND d.id_kecamatan = " . $filterKecamatan;
    }
    if ($filterDesa > 0) {
        $sql .= " AND t.id_desa = " . $filterDesa;
    }
} else {
    if ($filterDesa > 0) {
        $sql .= " AND t.id_desa = " . $filterDesa;
    } elseif ($filterKecamatan > 0) {
        $sql .= " AND d.id_kecamatan = " . $filterKecamatan;
    } elseif ($filterKabupaten > 0) {
        $sql .= " AND kec.id_kabupaten = " . $filterKabupaten;
    } elseif ($filterProvinsi > 0) {
        $sql .= " AND k.id_provinsi = " . $filterProvinsi;
    }
}
$sql .= " ORDER BY k.nama, kec.nama, d.nama, t.nomor_tps";
$result = $conn->query($sql);
$tpsList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1>Master TPS</h1>
        <p>Kelola data Tempat Pemungutan Suara
        <?php if (!empty($settings['id_kabupaten_aktif'])): 
            $kabInfo = $conn->query("SELECT k.nama, k.tipe, p.nama as prov FROM kabupaten k JOIN provinsi p ON k.id_provinsi = p.id WHERE k.id = " . intval($settings['id_kabupaten_aktif']))->fetch_assoc();
        ?>
        - <strong><?= ($kabInfo['tipe'] == 'kota' ? 'Kota ' : 'Kab. ') . htmlspecialchars($kabInfo['nama']) ?>, <?= htmlspecialchars($kabInfo['prov']) ?></strong>
        <?php endif; ?>
        </p>
    </div>
    <?php if (!$isViewer): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkModal">
            <i class="bi bi-plus-circle me-2"></i>Tambah Banyak
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
            <i class="bi bi-plus-lg me-2"></i>Tambah TPS
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center" id="filterForm">
            <?php if (empty($settings['id_provinsi_aktif'])): ?>
            <div class="col-md-3">
                <select name="provinsi" id="filterProvinsi" class="form-select form-select-sm">
                    <option value="">-- Provinsi --</option>
                    <?php foreach ($provinsiList as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (empty($settings['id_kabupaten_aktif'])): ?>
            <div class="col-md-3">
                <select name="kabupaten" id="filterKabupaten" class="form-select form-select-sm">
                    <option value="">-- Kabupaten/Kota --</option>
                    <?php foreach ($kabupatenFilter as $kab): ?>
                    <option value="<?= $kab['id'] ?>" <?= $filterKabupaten == $kab['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kab['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <select name="kecamatan" id="filterKecamatan" class="form-select form-select-sm">
                    <option value="">-- Semua Kecamatan --</option>
                    <?php foreach ($kecamatanFilter as $kec): ?>
                    <option value="<?= $kec['id'] ?>" <?= $filterKecamatan == $kec['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kec['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="desa" id="filterDesa" class="form-select form-select-sm">
                    <option value="">-- Semua Desa/Kel --</option>
                    <?php foreach ($desaFilter as $ds): ?>
                    <option value="<?= $ds['id'] ?>" <?= $filterDesa == $ds['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ds['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="dataTable">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th width="70">TPS</th>
                    <th>Desa/Kelurahan</th>
                    <th>Kecamatan</th>
                    <th>Kabupaten</th>
                    <th width="80">DPT</th>
                    <th width="80">Input</th>
                    <th width="70">Status</th>
                    <th width="100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tpsList as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong>TPS <?= $row['nomor_tps'] ?></strong></td>
                    <td><?= ($row['tipe_desa'] == 'kelurahan' ? 'Kel. ' : '') . htmlspecialchars($row['nama_desa']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kecamatan']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kabupaten']) ?></td>
                    <td class="text-end"><?= formatNumber($row['dpt']) ?></td>
                    <td class="text-center">
                        <?php if ($row['sudah_input'] > 0): ?>
                        <span class="badge bg-success"><i class="bi bi-check"></i> Ya</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $row['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?>
                    </td>
                    <td>
                        <?php if (!$isViewer): ?>
                        <button class="btn btn-sm btn-warning" onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('tps.php?action=delete&id=<?= $row['id'] ?>', 'TPS <?= $row['nomor_tps'] ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="tps.php" method="POST">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah TPS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Provinsi</label>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <input type="hidden" name="id_provinsi" value="<?= $settings['id_provinsi_aktif'] ?>">
                            <?php endif; ?>
                            <select id="formProvinsi" class="form-select" onchange="loadFormKabupaten()" <?= $settings['id_provinsi_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_provinsi_aktif']): ?>
                                <option value="">-- Pilih --</option>
                                <?php endif; ?>
                                <?php foreach ($provinsiList as $prov): ?>
                                <option value="<?= $prov['id'] ?>" <?= ($settings['id_provinsi_aktif'] == $prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <small class="text-muted">🔒</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kabupaten</label>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <input type="hidden" name="id_kabupaten" value="<?= $settings['id_kabupaten_aktif'] ?>">
                            <?php endif; ?>
                            <select id="formKabupaten" class="form-select" onchange="loadFormKecamatan()" <?= $settings['id_kabupaten_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_kabupaten_aktif']): ?>
                                <option value="">-- Pilih --</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <small class="text-muted">🔒</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Kecamatan</label>
                            <select id="formKecamatan" class="form-select" onchange="loadFormDesa()">
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Desa/Kelurahan <span class="text-danger">*</span></label>
                            <select name="id_desa" id="formDesa" class="form-select" required>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nomor TPS <span class="text-danger">*</span></label>
                            <input type="number" name="nomor_tps" id="formNomorTps" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah DPT <span class="text-danger">*</span></label>
                            <input type="number" name="dpt" id="formDpt" class="form-control" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" id="formAlamat" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="formActive" class="form-check-input" checked>
                        <label class="form-check-label" for="formActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Bulk Add -->
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="tps.php" method="POST">
                <input type="hidden" name="bulk_add" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah TPS Sekaligus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Fitur ini akan membuat TPS dengan nomor urut otomatis (1, 2, 3, dst)
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Provinsi</label>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <input type="hidden" name="bulk_provinsi" value="<?= $settings['id_provinsi_aktif'] ?>">
                            <?php endif; ?>
                            <select id="bulkProvinsi" class="form-select" onchange="loadBulkKabupaten()" <?= $settings['id_provinsi_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_provinsi_aktif']): ?>
                                <option value="">-- Pilih --</option>
                                <?php endif; ?>
                                <?php foreach ($provinsiList as $prov): ?>
                                <option value="<?= $prov['id'] ?>" <?= ($settings['id_provinsi_aktif'] == $prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <small class="text-muted">🔒</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kabupaten</label>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <input type="hidden" name="bulk_kabupaten" value="<?= $settings['id_kabupaten_aktif'] ?>">
                            <?php endif; ?>
                            <select id="bulkKabupaten" class="form-select" onchange="loadBulkKecamatan()" <?= $settings['id_kabupaten_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_kabupaten_aktif']): ?>
                                <option value="">-- Pilih --</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <small class="text-muted">🔒</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Kecamatan</label>
                            <select id="bulkKecamatan" class="form-select" onchange="loadBulkDesa()">
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Desa/Kelurahan <span class="text-danger">*</span></label>
                            <select name="bulk_desa" id="bulkDesa" class="form-select" required>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah TPS <span class="text-danger">*</span></label>
                            <input type="number" name="jumlah_tps" class="form-control" min="1" max="100" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DPT Default</label>
                            <input type="number" name="dpt_default" class="form-control" min="0" value="200">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Tambah TPS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$defaultProvId = $settings['id_provinsi_aktif'] ?? '';
$defaultKabId = $settings['id_kabupaten_aktif'] ?? '';
$provLocked = !empty($defaultProvId) ? 'true' : 'false';
$kabLocked = !empty($defaultKabId) ? 'true' : 'false';

$additionalJS = <<<JS
<script>
var defaultProv = '{$defaultProvId}';
var defaultKab = '{$defaultKabId}';
var provLocked = {$provLocked};
var kabLocked = {$kabLocked};

$('#dataTable').DataTable({ pageLength: 25 });

// Filter cascading
$('#filterProvinsi').change(function() {
    loadCascade('../api/get-kabupaten.php', { id_provinsi: $(this).val() }, '#filterKabupaten', '-- Kabupaten/Kota --');
    $('#filterKecamatan').html('<option value="">-- Semua Kecamatan --</option>');
    $('#filterDesa').html('<option value="">-- Semua Desa/Kel --</option>');
});

$('#filterKabupaten').change(function() {
    loadCascade('../api/get-kecamatan.php', { id_kabupaten: $(this).val() }, '#filterKecamatan', '-- Semua Kecamatan --');
    $('#filterDesa').html('<option value="">-- Semua Desa/Kel --</option>');
});

$('#filterKecamatan').change(function() {
    loadCascade('../api/get-desa.php', { id_kecamatan: $(this).val() }, '#filterDesa', '-- Semua Desa/Kel --');
});

// Form cascading
function loadFormKabupaten(sel) { 
    var placeholder = kabLocked ? '' : '-- Pilih --';
    loadCascade('../api/get-kabupaten.php', { id_provinsi: $('#formProvinsi').val() }, '#formKabupaten', placeholder, sel || defaultKab, function() {
        if (kabLocked || defaultKab) {
            loadFormKecamatan();
        }
    }); 
}
function loadFormKecamatan(sel) { loadCascade('../api/get-kecamatan.php', { id_kabupaten: $('#formKabupaten').val() }, '#formKecamatan', '-- Pilih --', sel); }
function loadFormDesa(sel) { loadCascade('../api/get-desa.php', { id_kecamatan: $('#formKecamatan').val() }, '#formDesa', '-- Pilih --', sel); }

// Bulk cascading
function loadBulkKabupaten(sel) { 
    var placeholder = kabLocked ? '' : '-- Pilih --';
    loadCascade('../api/get-kabupaten.php', { id_provinsi: $('#bulkProvinsi').val() }, '#bulkKabupaten', placeholder, sel || defaultKab, function() {
        if (kabLocked || defaultKab) {
            loadBulkKecamatan();
        }
    }); 
}
function loadBulkKecamatan(sel) { loadCascade('../api/get-kecamatan.php', { id_kabupaten: $('#bulkKabupaten').val() }, '#bulkKecamatan', '-- Pilih --', sel); }
function loadBulkDesa(sel) { loadCascade('../api/get-desa.php', { id_kecamatan: $('#bulkKecamatan').val() }, '#bulkDesa', '-- Pilih --', sel); }

function loadCascade(url, params, target, placeholder, selectedId, callback) {
    if (placeholder) {
        $(target).html('<option value="">' + placeholder + '</option>');
    } else {
        $(target).html('');
    }
    if (Object.values(params)[0]) {
        $.get(url, params, function(data) {
            data.forEach(item => {
                const sel = item.id == selectedId ? 'selected' : '';
                $(target).append('<option value="' + item.id + '" ' + sel + '>' + item.nama + '</option>');
            });
            if (callback) callback();
        });
    }
}

function editData(data) {
    $('#modalTitle').text('Edit TPS');
    $('#formId').val(data.id);
    $('#formNomorTps').val(data.nomor_tps);
    $('#formDpt').val(data.dpt);
    $('#formAlamat').val(data.alamat);
    $('#formActive').prop('checked', data.is_active == 1);
    
    // Load cascading selects
    $.get('../api/get-desa-detail.php', { id: data.id_desa }, function(desa) {
        $.get('../api/get-kecamatan-detail.php', { id: desa.id_kecamatan }, function(kec) {
            $.get('../api/get-kabupaten-detail.php', { id: kec.id_kabupaten }, function(kab) {
                if (!provLocked) {
                    $('#formProvinsi').val(kab.id_provinsi);
                }
                loadFormKabupaten(kec.id_kabupaten);
                setTimeout(() => { loadFormKecamatan(desa.id_kecamatan); }, 200);
                setTimeout(() => { loadFormDesa(data.id_desa); }, 400);
            });
        });
    });
    
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $(this).find('form')[0].reset();
    $('#modalTitle').text('Tambah TPS');
    
    if (provLocked) {
        $('#formProvinsi').val(defaultProv);
        loadFormKabupaten(defaultKab);
    } else {
        $('#formKabupaten').html('<option value="">-- Pilih --</option>');
    }
    $('#formKecamatan, #formDesa').html('<option value="">-- Pilih --</option>');
});

$('#bulkModal').on('hidden.bs.modal', function() {
    $(this).find('form')[0].reset();
    
    if (provLocked) {
        $('#bulkProvinsi').val(defaultProv);
        loadBulkKabupaten(defaultKab);
    } else {
        $('#bulkKabupaten').html('<option value="">-- Pilih --</option>');
    }
    $('#bulkKecamatan, #bulkDesa').html('<option value="">-- Pilih --</option>');
});

// Initialize on page load
$(document).ready(function() {
    if (defaultProv) {
        loadFormKabupaten(defaultKab);
        loadBulkKabupaten(defaultKab);
    }
});
</script>
JS;

include '../includes/footer.php';
?>
