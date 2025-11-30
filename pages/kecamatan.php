<?php
/**
 * CRUD Kecamatan - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

// Load settings
$settings = getSettings();

// Viewer tidak bisa edit master data
$isViewer = hasRole(['viewer']);

$pageTitle = 'Master Kecamatan';
$conn = getConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete (hanya admin/operator)
if ($action == 'delete' && $id > 0 && !$isViewer) {
    $stmt = $conn->prepare("DELETE FROM kecamatan WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Data kecamatan berhasil dihapus!');
    } else {
        setFlash('error', 'Gagal menghapus data kecamatan!');
    }
    header('Location: kecamatan.php');
    exit;
}

// Save (Add/Edit) - hanya admin/operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isViewer) {
    $id = intval($_POST['id']);
    $id_kabupaten = intval($_POST['id_kabupaten']);
    $kode = sanitize($_POST['kode']);
    $nama = sanitize($_POST['nama']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasi: kode harus diisi
    if (empty(trim($kode))) {
        setFlash('error', 'Kode wilayah wajib diisi!');
        header('Location: kecamatan.php');
        exit;
    }
    
    // Validasi: cek duplikat nama (tanpa spasi, case-insensitive)
    $duplicate = checkDuplicateKecamatan($id_kabupaten, $nama, $id);
    if ($duplicate) {
        setFlash('error', 'Nama kecamatan sudah ada: "' . htmlspecialchars($duplicate['nama']) . '". Tidak boleh ada nama yang mirip (abaikan spasi dan huruf besar/kecil).');
        header('Location: kecamatan.php');
        exit;
    }
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE kecamatan SET id_kabupaten = ?, kode = ?, nama = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("issii", $id_kabupaten, $kode, $nama, $is_active, $id);
        $message = 'Data kecamatan berhasil diupdate!';
    } else {
        $stmt = $conn->prepare("INSERT INTO kecamatan (id_kabupaten, kode, nama, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $id_kabupaten, $kode, $nama, $is_active);
        $message = 'Data kecamatan berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data kecamatan!');
    }
    header('Location: kecamatan.php');
    exit;
}

// Get dropdowns - filter by settings
$provinsiList = getProvinsi(false);

// Force filter by settings if configured
$filterProvinsi = isset($_GET['provinsi']) ? intval($_GET['provinsi']) : ($settings['id_provinsi_aktif'] ?? 0);
$filterKabupaten = isset($_GET['kabupaten']) ? intval($_GET['kabupaten']) : ($settings['id_kabupaten_aktif'] ?? 0);

// If settings lock provinsi/kabupaten, force the filter
if (!empty($settings['id_provinsi_aktif'])) {
    $filterProvinsi = $settings['id_provinsi_aktif'];
}
if (!empty($settings['id_kabupaten_aktif'])) {
    $filterKabupaten = $settings['id_kabupaten_aktif'];
}

// Get kabupaten for filter
$kabupatenFilter = [];
if ($filterProvinsi > 0) {
    $kabupatenFilter = getKabupaten($filterProvinsi, false);
}

// Get all data - filtered by settings
$sql = "SELECT kec.*, k.nama as nama_kabupaten, k.tipe as tipe_kab, p.nama as nama_provinsi,
        (SELECT COUNT(*) FROM desa WHERE id_kecamatan = kec.id) as jml_desa
        FROM kecamatan kec
        JOIN kabupaten k ON kec.id_kabupaten = k.id
        JOIN provinsi p ON k.id_provinsi = p.id
        WHERE 1=1";

// Always filter by configured kabupaten if set
if (!empty($settings['id_kabupaten_aktif'])) {
    $sql .= " AND kec.id_kabupaten = " . intval($settings['id_kabupaten_aktif']);
} elseif (!empty($settings['id_provinsi_aktif'])) {
    $sql .= " AND k.id_provinsi = " . intval($settings['id_provinsi_aktif']);
} elseif ($filterKabupaten > 0) {
    $sql .= " AND kec.id_kabupaten = " . $filterKabupaten;
} elseif ($filterProvinsi > 0) {
    $sql .= " AND k.id_provinsi = " . $filterProvinsi;
}
$sql .= " ORDER BY p.nama, k.nama, kec.nama";
$result = $conn->query($sql);
$kecamatanList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Master Kecamatan</h1>
        <p>Kelola data kecamatan
        <?php if (!empty($settings['id_kabupaten_aktif'])): 
            $kabInfo = $conn->query("SELECT k.nama, k.tipe, p.nama as prov FROM kabupaten k JOIN provinsi p ON k.id_provinsi = p.id WHERE k.id = " . intval($settings['id_kabupaten_aktif']))->fetch_assoc();
        ?>
        - <strong><?= ($kabInfo['tipe'] == 'kota' ? 'Kota ' : 'Kab. ') . htmlspecialchars($kabInfo['nama']) ?>, <?= htmlspecialchars($kabInfo['prov']) ?></strong>
        <?php endif; ?>
        </p>
    </div>
    <?php if (!$isViewer): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="bi bi-plus-lg me-2"></i>Tambah Kecamatan
    </button>
    <?php endif; ?>
</div>

<!-- Filter - Hidden if settings locked -->
<?php if (empty($settings['id_kabupaten_aktif'])): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label">Filter:</label>
            </div>
            <?php if (empty($settings['id_provinsi_aktif'])): ?>
            <div class="col-md-3">
                <select name="provinsi" id="filterProvinsi" class="form-select" onchange="loadKabupatenFilter()">
                    <option value="">-- Semua Provinsi --</option>
                    <?php foreach ($provinsiList as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <select name="kabupaten" id="filterKabupaten" class="form-select">
                    <option value="">-- Semua Kabupaten/Kota --</option>
                    <?php foreach ($kabupatenFilter as $kab): ?>
                    <option value="<?= $kab['id'] ?>" <?= $filterKabupaten == $kab['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kab['nama']) ?>
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
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="dataTable">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th width="80">Kode</th>
                    <th>Nama Kecamatan</th>
                    <th>Kabupaten/Kota</th>
                    <th>Provinsi</th>
                    <th width="80">Jml Desa</th>
                    <th width="70">Status</th>
                    <th width="100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kecamatanList as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['kode']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= ($row['tipe_kab'] == 'kota' ? 'Kota ' : 'Kab. ') . htmlspecialchars($row['nama_kabupaten']) ?></td>
                    <td><?= htmlspecialchars($row['nama_provinsi']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-info"><?= $row['jml_desa'] ?></span>
                    </td>
                    <td>
                        <?php if ($row['is_active']): ?>
                        <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$isViewer): ?>
                        <button class="btn btn-sm btn-warning" onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('kecamatan.php?action=delete&id=<?= $row['id'] ?>', '<?= addslashes($row['nama']) ?>')">
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
            <form action="kecamatan.php" method="POST">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Kecamatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <input type="hidden" name="id_provinsi" value="<?= $settings['id_provinsi_aktif'] ?>">
                            <?php endif; ?>
                            <select id="formProvinsi" class="form-select" required onchange="loadKabupaten()" <?= $settings['id_provinsi_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_provinsi_aktif']): ?>
                                <option value="">-- Pilih Provinsi --</option>
                                <?php endif; ?>
                                <?php foreach ($provinsiList as $prov): ?>
                                <option value="<?= $prov['id'] ?>" <?= ($settings['id_provinsi_aktif'] == $prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($settings['id_provinsi_aktif']): ?>
                            <small class="text-muted">🔒 Sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kabupaten/Kota <span class="text-danger">*</span></label>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <input type="hidden" name="id_kabupaten" value="<?= $settings['id_kabupaten_aktif'] ?>">
                            <?php endif; ?>
                            <select <?= $settings['id_kabupaten_aktif'] ? '' : 'name="id_kabupaten"' ?> id="formKabupaten" class="form-select" required <?= $settings['id_kabupaten_aktif'] ? 'disabled' : '' ?>>
                                <?php if (!$settings['id_kabupaten_aktif']): ?>
                                <option value="">-- Pilih Kabupaten --</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($settings['id_kabupaten_aktif']): ?>
                            <small class="text-muted">🔒 Sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="kode" id="formKode" class="form-control" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kecamatan <span class="text-danger">*</span></label>
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

$('#dataTable').DataTable();

function loadKabupatenFilter() {
    const provId = $('#filterProvinsi').val();
    const kabSelect = $('#filterKabupaten');
    kabSelect.html('<option value="">-- Semua Kabupaten/Kota --</option>');
    
    if (provId) {
        $.get('../api/get-kabupaten.php', { id_provinsi: provId }, function(data) {
            data.forEach(function(item) {
                kabSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
            });
        });
    }
}

function loadKabupaten(selectedId) {
    const provId = $('#formProvinsi').val();
    const kabSelect = $('#formKabupaten');
    
    if (!kabLocked) {
        kabSelect.html('<option value="">-- Pilih Kabupaten --</option>');
    } else {
        kabSelect.html('');
    }
    
    if (provId) {
        $.get('../api/get-kabupaten.php', { id_provinsi: provId }, function(data) {
            data.forEach(function(item) {
                const selected = (item.id == selectedId || item.id == defaultKab) ? 'selected' : '';
                kabSelect.append('<option value="' + item.id + '" ' + selected + '>' + item.nama + '</option>');
            });
        });
    }
}

function editData(data) {
    $('#modalTitle').text('Edit Kecamatan');
    $('#formId').val(data.id);
    $('#formKode').val(data.kode);
    $('#formNama').val(data.nama);
    $('#formActive').prop('checked', data.is_active == 1);
    
    // Get provinsi from kabupaten
    $.get('../api/get-kabupaten-detail.php', { id: data.id_kabupaten }, function(kab) {
        if (!provLocked) {
            $('#formProvinsi').val(kab.id_provinsi);
        }
        loadKabupaten(data.id_kabupaten);
    });
    
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Tambah Kecamatan');
    $('#formId').val('');
    
    if (!provLocked) {
        $('#formProvinsi').val('');
        $('#formKabupaten').html('<option value="">-- Pilih Kabupaten --</option>');
    } else {
        $('#formProvinsi').val(defaultProv);
        loadKabupaten(defaultKab);
    }
    
    $('#formKode').val('');
    $('#formNama').val('');
    $('#formActive').prop('checked', true);
});

// Initialize on page load
$(document).ready(function() {
    if (defaultProv) {
        loadKabupaten(defaultKab);
    }
});
</script>
JS;

include '../includes/footer.php';
?>
