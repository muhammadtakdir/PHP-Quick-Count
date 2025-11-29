<?php
/**
 * CRUD Kabupaten - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

// Viewer tidak bisa edit master data
$isViewer = hasRole(['viewer']);

$pageTitle = 'Master Kabupaten/Kota';
$conn = getConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete (hanya admin/operator)
if ($action == 'delete' && $id > 0 && !$isViewer) {
    $stmt = $conn->prepare("DELETE FROM kabupaten WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Data kabupaten/kota berhasil dihapus!');
    } else {
        setFlash('error', 'Gagal menghapus data kabupaten/kota!');
    }
    header('Location: kabupaten.php');
    exit;
}

// Save (Add/Edit) - hanya admin/operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isViewer) {
    $id = intval($_POST['id']);
    $id_provinsi = intval($_POST['id_provinsi']);
    $kode = sanitize($_POST['kode']);
    $nama = sanitize($_POST['nama']);
    $tipe = sanitize($_POST['tipe']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasi: kode harus diisi
    if (empty(trim($kode))) {
        setFlash('error', 'Kode wilayah wajib diisi!');
        header('Location: kabupaten.php');
        exit;
    }
    
    // Validasi: cek duplikat nama (tanpa spasi, case-insensitive)
    $duplicate = checkDuplicateKabupaten($id_provinsi, $nama, $id);
    if ($duplicate) {
        setFlash('error', 'Nama kabupaten/kota sudah ada: "' . htmlspecialchars($duplicate['nama']) . '". Tidak boleh ada nama yang mirip (abaikan spasi dan huruf besar/kecil).');
        header('Location: kabupaten.php');
        exit;
    }
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE kabupaten SET id_provinsi = ?, kode = ?, nama = ?, tipe = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("isssii", $id_provinsi, $kode, $nama, $tipe, $is_active, $id);
        $message = 'Data kabupaten/kota berhasil diupdate!';
    } else {
        $stmt = $conn->prepare("INSERT INTO kabupaten (id_provinsi, kode, nama, tipe, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $id_provinsi, $kode, $nama, $tipe, $is_active);
        $message = 'Data kabupaten/kota berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data kabupaten/kota!');
    }
    header('Location: kabupaten.php');
    exit;
}

// Get provinsi list for dropdown
$provinsiList = getProvinsi(false);

// Get filter
$filterProvinsi = isset($_GET['provinsi']) ? intval($_GET['provinsi']) : 0;

// Get all data
$sql = "SELECT k.*, p.nama as nama_provinsi,
        (SELECT COUNT(*) FROM kecamatan WHERE id_kabupaten = k.id) as jml_kecamatan
        FROM kabupaten k
        JOIN provinsi p ON k.id_provinsi = p.id";
if ($filterProvinsi > 0) {
    $sql .= " WHERE k.id_provinsi = " . $filterProvinsi;
}
$sql .= " ORDER BY p.nama, k.nama";
$result = $conn->query($sql);
$kabupatenList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Master Kabupaten/Kota</h1>
        <p>Kelola data kabupaten dan kota</p>
    </div>
    <?php if (!$isViewer): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="bi bi-plus-lg me-2"></i>Tambah Kabupaten/Kota
    </button>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label">Filter Provinsi:</label>
            </div>
            <div class="col-auto">
                <select name="provinsi" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Provinsi --</option>
                    <?php foreach ($provinsiList as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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
                    <th width="80">Kode</th>
                    <th>Nama</th>
                    <th>Provinsi</th>
                    <th width="80">Tipe</th>
                    <th width="100">Jml Kec.</th>
                    <th width="80">Status</th>
                    <th width="100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kabupatenList as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['kode']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_provinsi']) ?></td>
                    <td>
                        <span class="badge bg-<?= $row['tipe'] == 'kota' ? 'primary' : 'success' ?>">
                            <?= ucfirst($row['tipe']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-info"><?= $row['jml_kecamatan'] ?></span>
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
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('kabupaten.php?action=delete&id=<?= $row['id'] ?>', '<?= addslashes($row['nama']) ?>')">
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
            <form action="kabupaten.php" method="POST">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Kabupaten/Kota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                        <select name="id_provinsi" id="formProvinsi" class="form-select" required>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsiList as $prov): ?>
                            <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" name="kode" id="formKode" class="form-control" maxlength="10">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe <span class="text-danger">*</span></label>
                            <select name="tipe" id="formTipe" class="form-select" required>
                                <option value="kabupaten">Kabupaten</option>
                                <option value="kota">Kota</option>
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
$additionalJS = <<<JS
<script>
$('#dataTable').DataTable();

function editData(data) {
    $('#modalTitle').text('Edit Kabupaten/Kota');
    $('#formId').val(data.id);
    $('#formProvinsi').val(data.id_provinsi);
    $('#formKode').val(data.kode);
    $('#formTipe').val(data.tipe);
    $('#formNama').val(data.nama);
    $('#formActive').prop('checked', data.is_active == 1);
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Tambah Kabupaten/Kota');
    $('#formId').val('');
    $('#formProvinsi').val('');
    $('#formKode').val('');
    $('#formTipe').val('kabupaten');
    $('#formNama').val('');
    $('#formActive').prop('checked', true);
});
</script>
JS;

include '../includes/footer.php';
?>
