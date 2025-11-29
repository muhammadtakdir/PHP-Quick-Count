<?php
/**
 * CRUD Provinsi - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

// Viewer tidak bisa edit master data
$isViewer = hasRole(['viewer']);

$pageTitle = 'Master Provinsi';
$conn = getConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete (hanya admin/operator)
if ($action == 'delete' && $id > 0 && !$isViewer) {
    $stmt = $conn->prepare("DELETE FROM provinsi WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash('success', 'Data provinsi berhasil dihapus!');
    } else {
        setFlash('error', 'Gagal menghapus data provinsi!');
    }
    header('Location: provinsi.php');
    exit;
}

// Save (Add/Edit) - hanya admin/operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isViewer) {
    $id = intval($_POST['id']);
    $kode = sanitize($_POST['kode']);
    $nama = sanitize($_POST['nama']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE provinsi SET kode = ?, nama = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssii", $kode, $nama, $is_active, $id);
        $message = 'Data provinsi berhasil diupdate!';
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO provinsi (kode, nama, is_active) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $kode, $nama, $is_active);
        $message = 'Data provinsi berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data provinsi!');
    }
    header('Location: provinsi.php');
    exit;
}

// Get data for edit
$editData = null;
if ($action == 'edit' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM provinsi WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// Get all data
$result = $conn->query("SELECT p.*, 
                        (SELECT COUNT(*) FROM kabupaten WHERE id_provinsi = p.id) as jml_kabupaten
                        FROM provinsi p ORDER BY p.nama");
$provinsiList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Master Provinsi</h1>
        <p>Kelola data provinsi</p>
    </div>
    <?php if (!$isViewer): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="bi bi-plus-lg me-2"></i>Tambah Provinsi
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="dataTable">
            <thead>
                <tr>
                    <th width="60">No</th>
                    <th width="100">Kode</th>
                    <th>Nama Provinsi</th>
                    <th width="120">Jml Kabupaten</th>
                    <th width="100">Status</th>
                    <th width="120">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($provinsiList as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['kode']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-info"><?= $row['jml_kabupaten'] ?></span>
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
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('provinsi.php?action=delete&id=<?= $row['id'] ?>', '<?= addslashes($row['nama']) ?>')">
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
            <form action="provinsi.php" method="POST">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Provinsi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Provinsi</label>
                        <input type="text" name="kode" id="formKode" class="form-control" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Provinsi <span class="text-danger">*</span></label>
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
    $('#modalTitle').text('Edit Provinsi');
    $('#formId').val(data.id);
    $('#formKode').val(data.kode);
    $('#formNama').val(data.nama);
    $('#formActive').prop('checked', data.is_active == 1);
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Tambah Provinsi');
    $('#formId').val('');
    $('#formKode').val('');
    $('#formNama').val('');
    $('#formActive').prop('checked', true);
});
</script>
JS;

include '../includes/footer.php';
?>
