<?php
/**
 * Desa Management - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

// Viewer tidak bisa edit master data
$isViewer = hasRole(['viewer']);

$pageTitle = 'Master Desa/Kelurahan';
$conn = getConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete (hanya admin/operator)
if ($action == 'delete' && $id > 0 && !$isViewer) {
    $stmt = $conn->prepare("DELETE FROM desa WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Data desa/kelurahan berhasil dihapus!');
    } else {
        setFlash('error', 'Gagal menghapus data desa/kelurahan!');
    }
    header('Location: desa.php');
    exit;
}

// Save (Add/Edit) - hanya admin/operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isViewer) {
    $id = intval($_POST['id']);
    $id_kecamatan = intval($_POST['id_kecamatan']);
    $kode = sanitize($_POST['kode']);
    $nama = sanitize($_POST['nama']);
    $tipe = sanitize($_POST['tipe']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasi: kode harus diisi
    if (empty(trim($kode))) {
        setFlash('error', 'Kode wilayah wajib diisi!');
        header('Location: desa.php');
        exit;
    }
    
    // Validasi: cek duplikat nama (tanpa spasi, case-insensitive)
    $duplicate = checkDuplicateDesa($id_kecamatan, $nama, $id);
    if ($duplicate) {
        setFlash('error', 'Nama desa/kelurahan sudah ada: "' . htmlspecialchars($duplicate['nama']) . '". Tidak boleh ada nama yang mirip (abaikan spasi dan huruf besar/kecil).');
        header('Location: desa.php');
        exit;
    }
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE desa SET id_kecamatan = ?, kode = ?, nama = ?, tipe = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("isssii", $id_kecamatan, $kode, $nama, $tipe, $is_active, $id);
        $message = 'Data desa/kelurahan berhasil diupdate!';
    } else {
        $stmt = $conn->prepare("INSERT INTO desa (id_kecamatan, kode, nama, tipe, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $id_kecamatan, $kode, $nama, $tipe, $is_active);
        $message = 'Data desa/kelurahan berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data desa/kelurahan!');
    }
    header('Location: desa.php');
    exit;
}

// Get filters - use settings as default
$filterProvinsi = isset($_GET['provinsi']) ? intval($_GET['provinsi']) : ($settings['id_provinsi_aktif'] ?? 0);
$filterKabupaten = isset($_GET['kabupaten']) ? intval($_GET['kabupaten']) : ($settings['id_kabupaten_aktif'] ?? 0);
$filterKecamatan = isset($_GET['kecamatan']) ? intval($_GET['kecamatan']) : 0;

// Force filter by settings if configured
if (!empty($settings['id_provinsi_aktif'])) {
    $filterProvinsi = $settings['id_provinsi_aktif'];
}
if (!empty($settings['id_kabupaten_aktif'])) {
    $filterKabupaten = $settings['id_kabupaten_aktif'];
}

// Get dropdowns
$provinsiList = getProvinsi(false);
$kabupatenFilter = $filterProvinsi > 0 ? getKabupaten($filterProvinsi, false) : [];
$kecamatanFilter = $filterKabupaten > 0 ? getKecamatan($filterKabupaten, false) : [];

// Get all data with limit for performance
$sql = "SELECT d.*, kec.nama as nama_kecamatan, k.nama as nama_kabupaten, p.nama as nama_provinsi,
        (SELECT COUNT(*) FROM tps WHERE id_desa = d.id) as jml_tps
        FROM desa d
        JOIN kecamatan kec ON d.id_kecamatan = kec.id
        JOIN kabupaten k ON kec.id_kabupaten = k.id
        JOIN provinsi p ON k.id_provinsi = p.id
        WHERE 1=1";

// Always filter by configured kabupaten if set
if (!empty($settings['id_kabupaten_aktif'])) {
    $sql .= " AND kec.id_kabupaten = " . intval($settings['id_kabupaten_aktif']);
    if ($filterKecamatan > 0) {
        $sql .= " AND d.id_kecamatan = " . $filterKecamatan;
    }
} elseif (!empty($settings['id_provinsi_aktif'])) {
    $sql .= " AND k.id_provinsi = " . intval($settings['id_provinsi_aktif']);
    if ($filterKabupaten > 0) {
        $sql .= " AND kec.id_kabupaten = " . $filterKabupaten;
    }
    if ($filterKecamatan > 0) {
        $sql .= " AND d.id_kecamatan = " . $filterKecamatan;
    }
} else {
    if ($filterKecamatan > 0) {
        $sql .= " AND d.id_kecamatan = " . $filterKecamatan;
    } elseif ($filterKabupaten > 0) {
        $sql .= " AND kec.id_kabupaten = " . $filterKabupaten;
    } elseif ($filterProvinsi > 0) {
        $sql .= " AND k.id_provinsi = " . $filterProvinsi;
    }
}
$sql .= " ORDER BY p.nama, k.nama, kec.nama, d.nama LIMIT 500";
$result = $conn->query($sql);
$desaList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Master Desa/Kelurahan</h1>
        <p>Kelola data desa dan kelurahan
        <?php if (!empty($settings['id_kabupaten_aktif'])): 
            $kabInfo = $conn->query("SELECT k.nama, k.tipe, p.nama as prov FROM kabupaten k JOIN provinsi p ON k.id_provinsi = p.id WHERE k.id = " . intval($settings['id_kabupaten_aktif']))->fetch_assoc();
        ?>
        - <strong><?= ($kabInfo['tipe'] == 'kota' ? 'Kota ' : 'Kab. ') . htmlspecialchars($kabInfo['nama']) ?>, <?= htmlspecialchars($kabInfo['prov']) ?></strong>
        <?php endif; ?>
        </p>
    </div>
    <?php if (!$isViewer): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="bi bi-plus-lg me-2"></i>Tambah Desa/Kelurahan
    </button>
    <?php endif; ?>
</div>

<!-- Filter - Only show kecamatan filter if kabupaten is locked -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label">Filter:</label>
            </div>
            <?php if (empty($settings['id_provinsi_aktif'])): ?>
            <div class="col-md-3">
                <select name="provinsi" id="filterProvinsi" class="form-select" onchange="loadFilterKabupaten()">
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
                <select name="kabupaten" id="filterKabupaten" class="form-select" onchange="loadFilterKecamatan()">
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
                <select name="kecamatan" id="filterKecamatan" class="form-select">
                    <option value="">-- Semua Kecamatan --</option>
                    <?php foreach ($kecamatanFilter as $kec): ?>
                    <option value="<?= $kec['id'] ?>" <?= $filterKecamatan == $kec['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kec['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-2"></i>
            Menampilkan maksimal 500 data. Gunakan filter untuk mempersempit hasil.
        </div>
        <table class="table table-hover" id="dataTable">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th width="80">Kode</th>
                    <th>Nama</th>
                    <th>Kecamatan</th>
                    <th>Kabupaten</th>
                    <th width="80">Tipe</th>
                    <th width="70">TPS</th>
                    <th width="70">Status</th>
                    <th width="100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($desaList as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><small><?= htmlspecialchars($row['kode']) ?></small></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kecamatan']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kabupaten']) ?></td>
                    <td>
                        <span class="badge bg-<?= $row['tipe'] == 'kelurahan' ? 'primary' : 'success' ?>">
                            <?= ucfirst($row['tipe']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-info"><?= $row['jml_tps'] ?></span>
                    </td>
                    <td>
                        <?= $row['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?>
                    </td>
                    <td>
                        <?php if (!$isViewer): ?>
                        <button class="btn btn-sm btn-warning" onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('desa.php?action=delete&id=<?= $row['id'] ?>', '<?= addslashes($row['nama']) ?>')">
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
            <form action="desa.php" method="POST">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Desa/Kelurahan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <input type="hidden" name="id_provinsi" value="<?= $settings['id_provinsi_aktif'] ?>">
                            <?php endif; ?>
                            <select id="formProvinsi" class="form-select" required onchange="loadFormKabupaten()" <?= $settings['id_provinsi_aktif'] ? 'disabled' : '' ?>>
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kabupaten <span class="text-danger">*</span></label>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <input type="hidden" name="id_kabupaten" value="<?= $settings['id_kabupaten_aktif'] ?>">
                            <?php endif; ?>
                            <select id="formKabupaten" class="form-select" required onchange="loadFormKecamatan()" <?= $settings['id_kabupaten_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_kabupaten_aktif']): ?>
                                <option value="">-- Pilih --</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <small class="text-muted">🔒</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                            <select name="id_kecamatan" id="formKecamatan" class="form-select" required>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" name="kode" id="formKode" class="form-control" maxlength="15">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe <span class="text-danger">*</span></label>
                            <select name="tipe" id="formTipe" class="form-select" required>
                                <option value="desa">Desa</option>
                                <option value="kelurahan">Kelurahan</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="formNama" class="form-control" required>
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

function loadFilterKabupaten() {
    const provId = $('#filterProvinsi').val();
    $('#filterKabupaten').html('<option value="">-- Kabupaten/Kota --</option>');
    $('#filterKecamatan').html('<option value="">-- Kecamatan --</option>');
    
    if (provId) {
        $.get('../api/get-kabupaten.php', { id_provinsi: provId }, function(data) {
            data.forEach(item => $('#filterKabupaten').append('<option value="' + item.id + '">' + item.nama + '</option>'));
        });
    }
}

function loadFilterKecamatan() {
    const kabId = $('#filterKabupaten').val();
    $('#filterKecamatan').html('<option value="">-- Kecamatan --</option>');
    
    if (kabId) {
        $.get('../api/get-kecamatan.php', { id_kabupaten: kabId }, function(data) {
            data.forEach(item => $('#filterKecamatan').append('<option value="' + item.id + '">' + item.nama + '</option>'));
        });
    }
}

function loadFormKabupaten(selectedId) {
    const provId = $('#formProvinsi').val();
    
    if (!kabLocked) {
        $('#formKabupaten').html('<option value="">-- Pilih --</option>');
    } else {
        $('#formKabupaten').html('');
    }
    $('#formKecamatan').html('<option value="">-- Pilih --</option>');
    
    if (provId) {
        $.get('../api/get-kabupaten.php', { id_provinsi: provId }, function(data) {
            data.forEach(item => {
                const sel = (item.id == selectedId || item.id == defaultKab) ? 'selected' : '';
                $('#formKabupaten').append('<option value="' + item.id + '" ' + sel + '>' + item.nama + '</option>');
            });
            // Auto load kecamatan if kabupaten is locked
            if (kabLocked && defaultKab) {
                loadFormKecamatan();
            }
        });
    }
}

function loadFormKecamatan(selectedId) {
    const kabId = $('#formKabupaten').val();
    $('#formKecamatan').html('<option value="">-- Pilih --</option>');
    
    if (kabId) {
        $.get('../api/get-kecamatan.php', { id_kabupaten: kabId }, function(data) {
            data.forEach(item => {
                const sel = item.id == selectedId ? 'selected' : '';
                $('#formKecamatan').append('<option value="' + item.id + '" ' + sel + '>' + item.nama + '</option>');
            });
        });
    }
}

function editData(data) {
    $('#modalTitle').text('Edit Desa/Kelurahan');
    $('#formId').val(data.id);
    $('#formKode').val(data.kode);
    $('#formTipe').val(data.tipe);
    $('#formNama').val(data.nama);
    $('#formActive').prop('checked', data.is_active == 1);
    
    // Get kecamatan detail to get kabupaten and provinsi
    $.get('../api/get-kecamatan-detail.php', { id: data.id_kecamatan }, function(kec) {
        $.get('../api/get-kabupaten-detail.php', { id: kec.id_kabupaten }, function(kab) {
            if (!provLocked) {
                $('#formProvinsi').val(kab.id_provinsi);
            }
            loadFormKabupaten(kec.id_kabupaten);
            setTimeout(() => loadFormKecamatan(data.id_kecamatan), 300);
        });
    });
    
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Tambah Desa/Kelurahan');
    $('form')[0].reset();
    
    if (provLocked) {
        $('#formProvinsi').val(defaultProv);
        loadFormKabupaten(defaultKab);
    } else {
        $('#formKabupaten').html('<option value="">-- Pilih --</option>');
    }
    $('#formKecamatan').html('<option value="">-- Pilih --</option>');
});

// Initialize on page load
$(document).ready(function() {
    if (defaultProv) {
        loadFormKabupaten(defaultKab);
    }
});
</script>
JS;

include '../includes/footer.php';
?>
