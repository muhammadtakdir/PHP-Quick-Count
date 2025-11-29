<?php
/**
 * Calon Management - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

// Viewer tidak bisa edit master data
$isViewer = hasRole(['viewer']);

$pageTitle = 'Master Calon';
$conn = getConnection();
$settings = getSettings();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete (hanya admin/operator)
if ($action == 'delete' && $id > 0 && !$isViewer) {
    // Get old photos
    $stmt = $conn->prepare("SELECT foto_calon, foto_wakil FROM calon WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $oldData = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("DELETE FROM calon WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        // Delete photos
        if ($oldData['foto_calon'] && $oldData['foto_calon'] != 'default_calon.png') {
            deleteFile(FOTO_CALON_PATH . $oldData['foto_calon']);
        }
        if ($oldData['foto_wakil'] && $oldData['foto_wakil'] != 'default_wakil.png') {
            deleteFile(FOTO_CALON_PATH . $oldData['foto_wakil']);
        }
        setFlash('success', 'Data calon berhasil dihapus!');
    } else {
        setFlash('error', 'Gagal menghapus data calon!');
    }
    header('Location: calon.php');
    exit;
}

// Save (Add/Edit) - hanya admin/operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isViewer) {
    $id = intval($_POST['id']);
    $nomor_urut = intval($_POST['nomor_urut']);
    $nama_calon = sanitize($_POST['nama_calon']);
    $nama_wakil = sanitize($_POST['nama_wakil'] ?? '');
    $partai = sanitize($_POST['partai'] ?? '');
    $visi = sanitize($_POST['visi'] ?? '');
    $misi = sanitize($_POST['misi'] ?? '');
    $warna = sanitize($_POST['warna']);
    $jenis_pemilihan = sanitize($_POST['jenis_pemilihan']);
    $id_provinsi = !empty($_POST['id_provinsi']) ? intval($_POST['id_provinsi']) : null;
    $id_kabupaten = !empty($_POST['id_kabupaten']) ? intval($_POST['id_kabupaten']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Handle foto calon
    $foto_calon = null;
    if (!empty($_FILES['foto_calon']['name'])) {
        $result = uploadImage($_FILES['foto_calon'], FOTO_CALON_PATH, 400);
        if ($result['success']) {
            $foto_calon = $result['filename'];
        }
    }
    
    // Handle foto wakil
    $foto_wakil = null;
    if (!empty($_FILES['foto_wakil']['name'])) {
        $result = uploadImage($_FILES['foto_wakil'], FOTO_CALON_PATH, 400);
        if ($result['success']) {
            $foto_wakil = $result['filename'];
        }
    }
    
    if ($id > 0) {
        // Update
        $sql = "UPDATE calon SET nomor_urut = ?, nama_calon = ?, nama_wakil = ?, 
                partai = ?, visi = ?, misi = ?, warna = ?, jenis_pemilihan = ?,
                id_provinsi = ?, id_kabupaten = ?, is_active = ?";
        $params = [$nomor_urut, $nama_calon, $nama_wakil, $partai, $visi, $misi, 
                   $warna, $jenis_pemilihan, $id_provinsi, $id_kabupaten, $is_active];
        $types = "isssssssiii";
        
        if ($foto_calon) {
            $sql .= ", foto_calon = ?";
            $params[] = $foto_calon;
            $types .= "s";
        }
        if ($foto_wakil) {
            $sql .= ", foto_wakil = ?";
            $params[] = $foto_wakil;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $message = 'Data calon berhasil diupdate!';
    } else {
        // Insert
        $foto_calon = $foto_calon ?? 'default_calon.png';
        $foto_wakil = $foto_wakil ?? 'default_wakil.png';
        
        $stmt = $conn->prepare("INSERT INTO calon (nomor_urut, nama_calon, nama_wakil, partai, visi, misi, 
                               warna, jenis_pemilihan, id_provinsi, id_kabupaten, foto_calon, foto_wakil, is_active) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssiissi", $nomor_urut, $nama_calon, $nama_wakil, $partai, $visi, $misi,
                         $warna, $jenis_pemilihan, $id_provinsi, $id_kabupaten, $foto_calon, $foto_wakil, $is_active);
        $message = 'Data calon berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data calon! ' . $conn->error);
    }
    header('Location: calon.php');
    exit;
}

// Get filters
$filterJenis = isset($_GET['jenis']) ? sanitize($_GET['jenis']) : '';
$filterProvinsi = isset($_GET['provinsi']) ? intval($_GET['provinsi']) : 0;

// Get dropdowns
$provinsiList = getProvinsi(false);

// Get calon data
$sql = "SELECT c.*, p.nama as nama_provinsi, k.nama as nama_kabupaten,
        (SELECT COALESCE(SUM(jumlah_suara), 0) FROM suara WHERE id_calon = c.id) as total_suara
        FROM calon c
        LEFT JOIN provinsi p ON c.id_provinsi = p.id
        LEFT JOIN kabupaten k ON c.id_kabupaten = k.id
        WHERE 1=1";
if ($filterJenis) {
    $sql .= " AND c.jenis_pemilihan = '" . $conn->real_escape_string($filterJenis) . "'";
}
if ($filterProvinsi > 0) {
    $sql .= " AND c.id_provinsi = " . $filterProvinsi;
}
$sql .= " ORDER BY c.jenis_pemilihan, c.nomor_urut";
$result = $conn->query($sql);
$calonList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1>Master Calon</h1>
        <p>Kelola data calon dan wakil</p>
    </div>
    <?php if (!$isViewer): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="bi bi-plus-lg me-2"></i>Tambah Calon
    </button>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label">Filter:</label>
            </div>
            <div class="col-md-3">
                <select name="jenis" class="form-select">
                    <option value="">-- Semua Jenis Pemilihan --</option>
                    <option value="pilpres" <?= $filterJenis == 'pilpres' ? 'selected' : '' ?>>Pilpres</option>
                    <option value="pilgub" <?= $filterJenis == 'pilgub' ? 'selected' : '' ?>>Pilgub</option>
                    <option value="pilbup" <?= $filterJenis == 'pilbup' ? 'selected' : '' ?>>Pilbup</option>
                    <option value="pilwalkot" <?= $filterJenis == 'pilwalkot' ? 'selected' : '' ?>>Pilwalkot</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="provinsi" class="form-select">
                    <option value="">-- Semua Provinsi --</option>
                    <?php foreach ($provinsiList as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= $filterProvinsi == $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nama']) ?>
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
        <div class="row">
            <?php foreach ($calonList as $calon): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header text-center py-3" style="background: <?= $calon['warna'] ?>20; border-bottom: 3px solid <?= $calon['warna'] ?>">
                        <span class="badge" style="background: <?= $calon['warna'] ?>; font-size: 1.2rem;">
                            No. Urut <?= $calon['nomor_urut'] ?>
                        </span>
                        <br>
                        <small class="text-muted"><?= strtoupper($calon['jenis_pemilihan']) ?></small>
                    </div>
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center gap-3 mb-3">
                            <div>
                                <img src="../uploads/calon/<?= $calon['foto_calon'] ?>" 
                                     class="rounded-circle border shadow-sm" 
                                     style="width: 80px; height: 80px; object-fit: cover;"
                                     onerror="this.src='../assets/img/default_calon.png'">
                                <div class="mt-1"><small class="text-muted">Calon</small></div>
                            </div>
                            <?php if ($calon['nama_wakil']): ?>
                            <div>
                                <img src="../uploads/calon/<?= $calon['foto_wakil'] ?>" 
                                     class="rounded-circle border shadow-sm" 
                                     style="width: 80px; height: 80px; object-fit: cover;"
                                     onerror="this.src='../assets/img/default_wakil.png'">
                                <div class="mt-1"><small class="text-muted">Wakil</small></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title mb-1"><?= htmlspecialchars($calon['nama_calon']) ?></h5>
                        <?php if ($calon['nama_wakil']): ?>
                        <p class="text-muted mb-2">&amp; <?= htmlspecialchars($calon['nama_wakil']) ?></p>
                        <?php endif; ?>
                        <?php if ($calon['partai']): ?>
                        <p class="small text-muted mb-2">
                            <i class="bi bi-flag me-1"></i><?= htmlspecialchars($calon['partai']) ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <div>
                                <span class="badge bg-<?= $calon['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $calon['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <strong style="color: <?= $calon['warna'] ?>"><?= formatNumber($calon['total_suara']) ?></strong>
                                <small class="text-muted d-block">suara</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <?php if (!$isViewer): ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-warning flex-grow-1" 
                                    onclick="editData(<?= htmlspecialchars(json_encode($calon)) ?>)">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="confirmDelete('calon.php?action=delete&id=<?= $calon['id'] ?>', '<?= addslashes($calon['nama_calon']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">Hanya lihat</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($calonList)): ?>
            <div class="col-12">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Belum ada data calon</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="calon.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Calon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Pemilihan <span class="text-danger">*</span></label>
                            <select name="jenis_pemilihan" id="formJenis" class="form-select" required onchange="toggleWilayah()" <?= !empty($settings['jenis_pemilihan']) ? 'disabled' : '' ?>>
                                <option value="pilpres" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilpres' ? 'selected' : '' ?>>Pilpres</option>
                                <option value="pilgub" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilgub' ? 'selected' : '' ?>>Pilgub</option>
                                <option value="pilbup" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilbup' ? 'selected' : '' ?>>Pilbup</option>
                                <option value="pilwalkot" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilwalkot' ? 'selected' : '' ?>>Pilwalkot</option>
                            </select>
                            <?php if (!empty($settings['jenis_pemilihan'])): ?>
                            <input type="hidden" name="jenis_pemilihan" value="<?= $settings['jenis_pemilihan'] ?>">
                            <small class="text-muted"><i class="bi bi-lock"></i> Sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">No. Urut <span class="text-danger">*</span></label>
                            <input type="number" name="nomor_urut" id="formNomor" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Warna</label>
                            <input type="color" name="warna" id="formWarna" class="form-control form-control-color w-100" value="#6c757d">
                        </div>
                    </div>
                    
                    <div class="row" id="wilayahRow">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Provinsi</label>
                            <select name="id_provinsi" id="formProvinsi" class="form-select" onchange="loadFormKabupaten()" <?= !empty($settings['id_provinsi_aktif']) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Provinsi --</option>
                                <?php foreach ($provinsiList as $prov): ?>
                                <option value="<?= $prov['id'] ?>" <?= ($settings['id_provinsi_aktif'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($settings['id_provinsi_aktif'])): ?>
                            <input type="hidden" name="id_provinsi" value="<?= $settings['id_provinsi_aktif'] ?>">
                            <small class="text-muted"><i class="bi bi-lock"></i> Sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3" id="kabupatenCol">
                            <label class="form-label">Kabupaten/Kota</label>
                            <select name="id_kabupaten" id="formKabupaten" class="form-select" <?= !empty($settings['id_kabupaten_aktif']) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Kabupaten --</option>
                            </select>
                            <?php if (!empty($settings['id_kabupaten_aktif'])): ?>
                            <input type="hidden" name="id_kabupaten" value="<?= $settings['id_kabupaten_aktif'] ?>">
                            <small class="text-muted"><i class="bi bi-lock"></i> Sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Calon <span class="text-danger">*</span></label>
                            <input type="text" name="nama_calon" id="formNamaCalon" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Wakil</label>
                            <input type="text" name="nama_wakil" id="formNamaWakil" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto Calon</label>
                            <input type="file" name="foto_calon" class="form-control" accept="image/*">
                            <div id="previewCalon" class="mt-2"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto Wakil</label>
                            <input type="file" name="foto_wakil" class="form-control" accept="image/*">
                            <div id="previewWakil" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Partai Pengusung</label>
                        <input type="text" name="partai" id="formPartai" class="form-control" placeholder="Contoh: Golkar, PDI-P, Gerindra">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Visi</label>
                            <textarea name="visi" id="formVisi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Misi</label>
                            <textarea name="misi" id="formMisi" class="form-control" rows="3"></textarea>
                        </div>
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
$defaultJenis = $settings['jenis_pemilihan'] ?? '';
$provLocked = !empty($defaultProvId) ? 'true' : 'false';
$kabLocked = !empty($defaultKabId) ? 'true' : 'false';
$jenisLocked = !empty($defaultJenis) ? 'true' : 'false';

$additionalJS = <<<JS
<script>
var defaultProv = '{$defaultProvId}';
var defaultKab = '{$defaultKabId}';
var defaultJenis = '{$defaultJenis}';
var provLocked = {$provLocked};
var kabLocked = {$kabLocked};
var jenisLocked = {$jenisLocked};

function toggleWilayah() {
    var jenis = $('#formJenis').val();
    // Untuk pilpres, sembunyikan wilayah karena tidak perlu
    // Tapi jika sudah dikunci, tetap tampilkan
    if (jenis === 'pilpres' && !provLocked) {
        $('#wilayahRow').hide();
    } else if (jenis === 'pilgub') {
        $('#wilayahRow').show();
        $('#kabupatenCol').hide();
    } else {
        $('#wilayahRow').show();
        $('#kabupatenCol').show();
    }
}

function loadFormKabupaten(selectedId) {
    var provId = $('#formProvinsi').val();
    
    if (provId) {
        $.get('../api/get-kabupaten.php', { id_provinsi: provId }, function(data) {
            if (!kabLocked) {
                $('#formKabupaten').html('<option value="">-- Pilih Kabupaten --</option>');
            } else {
                $('#formKabupaten').html('');
            }
            data.forEach(function(item) {
                var sel = (item.id == selectedId || item.id == defaultKab) ? 'selected' : '';
                $('#formKabupaten').append('<option value="' + item.id + '" ' + sel + '>' + item.nama + '</option>');
            });
        });
    }
}

function editData(data) {
    $('#modalTitle').text('Edit Calon');
    $('#formId').val(data.id);
    if (!jenisLocked) {
        $('#formJenis').val(data.jenis_pemilihan);
    }
    $('#formNomor').val(data.nomor_urut);
    $('#formWarna').val(data.warna);
    $('#formNamaCalon').val(data.nama_calon);
    $('#formNamaWakil').val(data.nama_wakil);
    $('#formPartai').val(data.partai);
    $('#formVisi').val(data.visi);
    $('#formMisi').val(data.misi);
    $('#formActive').prop('checked', data.is_active == 1);
    
    toggleWilayah();
    
    if (data.id_provinsi && !provLocked) {
        $('#formProvinsi').val(data.id_provinsi);
    }
    if (data.id_kabupaten) {
        loadFormKabupaten(data.id_kabupaten);
    }
    
    // Show current photos
    if (data.foto_calon && data.foto_calon !== 'default_calon.png') {
        $('#previewCalon').html('<img src="../uploads/calon/' + data.foto_calon + '" class="img-thumbnail" style="max-height:80px">');
    }
    if (data.foto_wakil && data.foto_wakil !== 'default_wakil.png') {
        $('#previewWakil').html('<img src="../uploads/calon/' + data.foto_wakil + '" class="img-thumbnail" style="max-height:80px">');
    }
    
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Tambah Calon');
    $(this).find('form')[0].reset();
    
    // Reset to default values
    if (jenisLocked) {
        $('#formJenis').val(defaultJenis);
    }
    if (provLocked) {
        $('#formProvinsi').val(defaultProv);
    }
    
    $('#previewCalon, #previewWakil').html('');
    toggleWilayah();
    
    // Reload kabupaten if provinsi is locked
    if (defaultProv) {
        loadFormKabupaten(defaultKab);
    }
});

// Initialize on page load
$(document).ready(function() {
    toggleWilayah();
    
    // Load kabupaten based on settings
    if (defaultProv) {
        loadFormKabupaten(defaultKab);
    }
});
</script>
JS;

include '../includes/footer.php';
?>
