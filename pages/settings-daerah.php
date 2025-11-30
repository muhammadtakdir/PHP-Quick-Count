<?php
/**
 * Halaman Pengaturan Per Kabupaten
 * Admin dapat mengatur jenis perhitungan untuk masing-masing kabupaten
 */

require_once '../config/config.php';
requireAdmin();

$conn = getConnection();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug POST data - simpan ke session untuk ditampilkan
    $_SESSION['debug_post'] = $_POST;
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid! Silakan refresh halaman dan coba lagi.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_kabupaten') {
            $idKabupaten = intval($_POST['id_kabupaten']);
            $jenisPemilihan = $_POST['jenis_pemilihan'];
            $jenisHitung = $_POST['jenis_hitung'];
            $jumlahTpsSample = intval($_POST['jumlah_tps_sample']);
            $publicDetailLevel = $_POST['public_detail_level'];
            $tahunPemilihan = intval($_POST['tahun_pemilihan']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Generate nama pemilihan otomatis
            $namaPemilihan = generateNamaPemilihan($jenisPemilihan, null, $idKabupaten, null, $tahunPemilihan);
            
            // Cek apakah sudah ada settings untuk kabupaten ini
            $check = $conn->prepare("SELECT id FROM settings_kabupaten WHERE id_kabupaten = ?");
            $check->bind_param("i", $idKabupaten);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            
            if ($exists) {
                // Update
                $stmt = $conn->prepare("UPDATE settings_kabupaten SET 
                    nama_pemilihan = ?, jenis_pemilihan = ?, jenis_hitung = ?,
                    jumlah_tps_sample = ?, public_detail_level = ?, tahun_pemilihan = ?, is_active = ?, updated_at = NOW()
                    WHERE id_kabupaten = ?");
                $stmt->bind_param("sssissii", $namaPemilihan, $jenisPemilihan, $jenisHitung, 
                    $jumlahTpsSample, $publicDetailLevel, $tahunPemilihan, $isActive, $idKabupaten);
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO settings_kabupaten 
                    (id_kabupaten, nama_pemilihan, jenis_pemilihan, jenis_hitung, jumlah_tps_sample, public_detail_level, tahun_pemilihan, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssisii", $idKabupaten, $namaPemilihan, $jenisPemilihan, $jenisHitung, 
                    $jumlahTpsSample, $publicDetailLevel, $tahunPemilihan, $isActive);
            }
            
            if ($stmt->execute()) {
                $message = "Pengaturan {$namaPemilihan} berhasil disimpan! (Jenis: {$jenisHitung})";
                $messageType = 'success';
            } else {
                $message = 'Gagal menyimpan pengaturan: ' . $conn->error;
                $messageType = 'danger';
            }
        }
        
        // Handler untuk update settings Pilgub (per provinsi)
        if ($action === 'update_provinsi') {
            $idProvinsi = intval($_POST['id_provinsi']);
            $jenisHitung = $_POST['jenis_hitung'];
            $jumlahTpsSample = intval($_POST['jumlah_tps_sample']);
            $publicDetailLevel = $_POST['public_detail_level'];
            $tahunPemilihan = intval($_POST['tahun_pemilihan']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Get nama provinsi
            $stmt = $conn->prepare("SELECT nama FROM provinsi WHERE id = ?");
            $stmt->bind_param("i", $idProvinsi);
            $stmt->execute();
            $provInfo = $stmt->get_result()->fetch_assoc();
            $namaProvinsi = $provInfo['nama'] ?? 'Unknown';
            $namaPemilihan = "PILGUB " . strtoupper($namaProvinsi) . " " . $tahunPemilihan;
            
            // Cek apakah sudah ada settings untuk provinsi ini
            $check = $conn->prepare("SELECT id FROM settings_kabupaten WHERE id_provinsi = ? AND jenis_pemilihan = 'pilgub'");
            $check->bind_param("i", $idProvinsi);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            
            if ($exists) {
                // Update
                $stmt = $conn->prepare("UPDATE settings_kabupaten SET 
                    nama_pemilihan = ?, jenis_hitung = ?,
                    jumlah_tps_sample = ?, public_detail_level = ?, tahun_pemilihan = ?, is_active = ?
                    WHERE id_provinsi = ? AND jenis_pemilihan = 'pilgub'");
                $stmt->bind_param("ssissii", $namaPemilihan, $jenisHitung, 
                    $jumlahTpsSample, $publicDetailLevel, $tahunPemilihan, $isActive, $idProvinsi);
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO settings_kabupaten 
                    (id_kabupaten, id_provinsi, nama_pemilihan, jenis_pemilihan, jenis_hitung, jumlah_tps_sample, public_detail_level, tahun_pemilihan, is_active)
                    VALUES (NULL, ?, ?, 'pilgub', ?, ?, ?, ?, ?)");
                $stmt->bind_param("issiisi", $idProvinsi, $namaPemilihan, $jenisHitung, 
                    $jumlahTpsSample, $publicDetailLevel, $tahunPemilihan, $isActive);
            }
            
            if ($stmt->execute()) {
                $message = 'Pengaturan Pilgub ' . $namaProvinsi . ' berhasil disimpan!';
                $messageType = 'success';
            } else {
                $message = 'Gagal menyimpan pengaturan: ' . $conn->error;
                $messageType = 'danger';
            }
        }
        
        if ($action === 'update_global') {
            $publikMode = $_POST['publik_mode'];
            $idKabupatenPublik = $_POST['id_kabupaten_publik'] ?: null;
            
            $stmt = $conn->prepare("UPDATE settings SET publik_mode = ?, id_kabupaten_publik = ? WHERE id = 1");
            $stmt->bind_param("si", $publikMode, $idKabupatenPublik);
            
            if ($stmt->execute()) {
                $message = 'Pengaturan akses publik berhasil disimpan!';
                $messageType = 'success';
            } else {
                $message = 'Gagal menyimpan pengaturan: ' . $conn->error;
                $messageType = 'danger';
            }
        }
    }
}

// Get all kabupaten with settings
$allKabupaten = getAllKabupatenSettings();
$globalSettings = getSettings();

// Get all provinsi with pilgub settings
$allProvinsiPilgub = $conn->query("
    SELECT p.id, p.nama as nama_provinsi,
           sk.nama_pemilihan, sk.jenis_hitung, sk.jumlah_tps_sample, 
           sk.public_detail_level, sk.tahun_pemilihan, sk.is_active as setting_active,
           (SELECT COUNT(*) FROM calon c WHERE c.jenis_pemilihan = 'pilgub' AND c.id_provinsi = p.id) as jumlah_calon
    FROM provinsi p
    LEFT JOIN settings_kabupaten sk ON p.id = sk.id_provinsi AND sk.jenis_pemilihan = 'pilgub'
    WHERE EXISTS (SELECT 1 FROM calon c WHERE c.jenis_pemilihan = 'pilgub' AND c.id_provinsi = p.id)
    ORDER BY p.nama
")->fetch_all(MYSQLI_ASSOC);

// Page title
$pageTitle = 'Pengaturan Per Daerah';

include '../includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="bi bi-sliders me-2"></i><?= $pageTitle ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active"><?= $pageTitle ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['debug_post']) && !empty($_SESSION['debug_post'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <strong>Debug POST Data:</strong><br>
                <small><pre><?= htmlspecialchars(print_r($_SESSION['debug_post'], true)) ?></pre></small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['debug_post']); endif; ?>
            
            <!-- Pengaturan Akses Publik Global -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-globe me-2"></i>Pengaturan Akses Publik
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="update_global">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Mode Tampilan Publik</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="publik_mode" value="semua_daerah" 
                                            id="modeSemua" <?= ($globalSettings['publik_mode'] ?? 'semua_daerah') === 'semua_daerah' ? 'checked' : '' ?>
                                            onchange="toggleKabupatenSelect()">
                                        <label class="form-check-label" for="modeSemua">
                                            <i class="bi bi-grid me-1"></i>Semua Daerah
                                            <small class="text-muted d-block">Publik dapat melihat data semua kabupaten/kota</small>
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="radio" name="publik_mode" value="satu_daerah" 
                                            id="modeSatu" <?= ($globalSettings['publik_mode'] ?? '') === 'satu_daerah' ? 'checked' : '' ?>
                                            onchange="toggleKabupatenSelect()">
                                        <label class="form-check-label" for="modeSatu">
                                            <i class="bi bi-pin-map me-1"></i>Satu Daerah Saja
                                            <small class="text-muted d-block">Publik hanya dapat melihat data satu kabupaten/kota yang dipilih</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3" id="kabupatenPublikContainer" style="<?= ($globalSettings['publik_mode'] ?? 'semua_daerah') === 'semua_daerah' ? 'display:none' : '' ?>">
                                    <label class="form-label fw-bold">Kabupaten yang Ditampilkan</label>
                                    <select name="id_kabupaten_publik" class="form-select">
                                        <option value="">-- Pilih Kabupaten --</option>
                                        <?php foreach ($allKabupaten as $kab): ?>
                                        <option value="<?= $kab['id'] ?>" <?= ($globalSettings['id_kabupaten_publik'] ?? '') == $kab['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kab['nama_kabupaten']) ?> - <?= htmlspecialchars($kab['nama_provinsi'] ?? '') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Pengaturan Akses
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Daftar Provinsi dengan Pilgub -->
            <?php if (!empty($allProvinsiPilgub)): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-globe2 me-2"></i>Pengaturan Pilgub Per Provinsi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabelProvinsi">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Provinsi</th>
                                    <th>Jenis Hitung</th>
                                    <th>TPS Sample</th>
                                    <th>Detail Level</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($allProvinsiPilgub as $prov): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($prov['nama_provinsi']) ?></strong>
                                        <br><small class="text-muted"><?= $prov['nama_pemilihan'] ?? 'Belum diatur' ?></small>
                                    </td>
                                    <td>
                                        <?php if (($prov['jenis_hitung'] ?? '') === 'quick_count'): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-lightning me-1"></i>Quick Count
                                        </span>
                                        <?php elseif (($prov['jenis_hitung'] ?? '') === 'real_count'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-database me-1"></i>Real Count
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Belum diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $prov['jumlah_tps_sample'] ?? 0 ?></td>
                                    <td>
                                        <?php if (($prov['public_detail_level'] ?? '') === 'full'): ?>
                                        <span class="badge bg-info">Full</span>
                                        <?php elseif (($prov['public_detail_level'] ?? '') === 'minimal'): ?>
                                        <span class="badge bg-secondary">Minimal</span>
                                        <?php else: ?>
                                        <span class="badge bg-light text-dark">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($prov['setting_active'] ?? 0) == 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" 
                                            onclick="editProvinsi(<?= htmlspecialchars(json_encode($prov)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="../publik.php?provinsi=<?= $prov['id'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Daftar Kabupaten dan Pengaturannya -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-building me-2"></i>Pengaturan Per Kabupaten/Kota
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabelKabupaten">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Kabupaten/Kota</th>
                                    <th>Provinsi</th>
                                    <th>Jenis Pemilihan</th>
                                    <th>Jenis Hitung</th>
                                    <th>TPS Sample</th>
                                    <th>Detail Level</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($allKabupaten as $kab): 
                                    // Generate nama pemilihan otomatis untuk preview
                                    $previewNama = getNamaPemilihanKabupaten($kab['id']);
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($kab['nama_kabupaten']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($previewNama) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($kab['nama_provinsi'] ?? '-') ?></td>
                                    <td>
                                        <?php 
                                        $jenisPemilihanLabels = [
                                            'pilpres' => 'Pilpres',
                                            'pilgub' => 'Pilgub',
                                            'pilbup' => 'Pilbup',
                                            'pilwalkot' => 'Pilwalkot',
                                            'pilkades' => 'Pilkades'
                                        ];
                                        echo $jenisPemilihanLabels[$kab['jenis_pemilihan'] ?? ''] ?? '-';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (($kab['jenis_hitung'] ?? '') === 'quick_count'): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-lightning me-1"></i>Quick Count
                                        </span>
                                        <?php elseif (($kab['jenis_hitung'] ?? '') === 'real_count'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-database me-1"></i>Real Count
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Belum diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $kab['jumlah_tps_sample'] ?? 0 ?></td>
                                    <td>
                                        <?php if (($kab['public_detail_level'] ?? '') === 'full'): ?>
                                        <span class="badge bg-info">Full</span>
                                        <?php elseif (($kab['public_detail_level'] ?? '') === 'minimal'): ?>
                                        <span class="badge bg-secondary">Minimal</span>
                                        <?php else: ?>
                                        <span class="badge bg-light text-dark">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($kab['setting_active'] ?? 0) == 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="editKabupaten(<?= htmlspecialchars(json_encode($kab)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="../publik.php?kabupaten=<?= $kab['id'] ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Modal Edit Kabupaten -->
<div class="modal fade" id="modalEditKabupaten" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="update_kabupaten">
                <input type="hidden" name="id_kabupaten" id="edit_id_kabupaten">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i>Edit Pengaturan: <span id="edit_nama_kabupaten"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Preview Nama Pemilihan -->
                    <div class="alert alert-info mb-3">
                        <strong><i class="bi bi-magic me-2"></i>Preview Nama Pemilihan:</strong>
                        <div id="preview_nama_pemilihan" class="fs-5 mt-1">-</div>
                        <small class="text-muted">Nama pemilihan akan otomatis terbentuk dari jenis pemilihan dan wilayah</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jenis Pemilihan</label>
                                <select name="jenis_pemilihan" id="edit_jenis_pemilihan" class="form-select" required onchange="updatePreviewNama()">
                                    <option value="pilpres">Pemilihan Presiden</option>
                                    <option value="pilgub">Pemilihan Gubernur</option>
                                    <option value="pilbup">Pemilihan Bupati</option>
                                    <option value="pilwalkot">Pemilihan Walikota</option>
                                    <option value="pilkades">Pemilihan Kepala Desa</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tahun Pemilihan</label>
                                <input type="number" name="tahun_pemilihan" id="edit_tahun_pemilihan" class="form-control" min="2020" max="2030" required onchange="updatePreviewNama()">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jenis Perhitungan</label>
                                <select name="jenis_hitung" id="edit_jenis_hitung" class="form-select" required onchange="toggleSampleField()">
                                    <option value="real_count">Real Count (Semua TPS)</option>
                                    <option value="quick_count">Quick Count (Sample TPS)</option>
                                </select>
                                <small class="text-muted">
                                    Quick Count: hanya hitung TPS sample<br>
                                    Real Count: hitung semua TPS
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3" id="sampleContainer">
                                <label class="form-label">Jumlah TPS Sample</label>
                                <input type="number" name="jumlah_tps_sample" id="edit_jumlah_tps_sample" class="form-control" min="0">
                                <small class="text-muted">Hanya untuk Quick Count</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Detail Level Publik</label>
                                <select name="public_detail_level" id="edit_public_detail_level" class="form-select">
                                    <option value="full">Full - Tampilkan semua detail</option>
                                    <option value="minimal">Minimal - Hanya persentase</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 d-flex align-items-center h-100 pt-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" checked>
                                    <label class="form-check-label" for="edit_is_active">Aktifkan Pengaturan</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Provinsi (Pilgub) -->
<div class="modal fade" id="modalEditProvinsi" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="update_provinsi">
                <input type="hidden" name="id_provinsi" id="edit_prov_id_provinsi">
                
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i>Edit Pilgub: <span id="edit_prov_nama_provinsi"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tahun Pemilihan</label>
                        <input type="number" name="tahun_pemilihan" id="edit_prov_tahun_pemilihan" class="form-control" min="2020" max="2030" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jenis Perhitungan</label>
                        <select name="jenis_hitung" id="edit_prov_jenis_hitung" class="form-select" required onchange="toggleProvSampleField()">
                            <option value="real_count">Real Count (Semua TPS)</option>
                            <option value="quick_count">Quick Count (Sample TPS)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="provSampleContainer">
                        <label class="form-label">Jumlah TPS Sample</label>
                        <input type="number" name="jumlah_tps_sample" id="edit_prov_jumlah_tps_sample" class="form-control" min="0">
                        <small class="text-muted">Hanya untuk Quick Count</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Detail Level Publik</label>
                        <select name="public_detail_level" id="edit_prov_public_detail_level" class="form-select">
                            <option value="full">Full - Tampilkan semua detail</option>
                            <option value="minimal">Minimal - Hanya persentase</option>
                        </select>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_prov_is_active" checked>
                        <label class="form-check-label" for="edit_prov_is_active">Aktifkan Pengaturan</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-save me-1"></i>Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Label jenis pemilihan
const jenisPemilihanLabels = {
    'pilpres': 'PEMILIHAN PRESIDEN',
    'pilgub': 'PILKADA PROVINSI',
    'pilbup': 'PILKADA KABUPATEN',
    'pilwalkot': 'PILKADA KOTA',
    'pilkades': 'PILKADES'
};

let currentKabupatenName = '';

function toggleKabupatenSelect() {
    const modeSatu = document.getElementById('modeSatu').checked;
    const container = document.getElementById('kabupatenPublikContainer');
    container.style.display = modeSatu ? 'block' : 'none';
}

function toggleSampleField() {
    const jenisHitung = document.getElementById('edit_jenis_hitung').value;
    const container = document.getElementById('sampleContainer');
    container.style.display = (jenisHitung === 'quick_count') ? 'block' : 'none';
}

function updatePreviewNama() {
    const jenisPemilihan = document.getElementById('edit_jenis_pemilihan').value;
    const tahun = document.getElementById('edit_tahun_pemilihan').value;
    const label = jenisPemilihanLabels[jenisPemilihan] || 'PEMILIHAN';
    const namaWilayah = currentKabupatenName.toUpperCase();
    
    document.getElementById('preview_nama_pemilihan').textContent = label + ' ' + namaWilayah + ' ' + tahun;
}

function editKabupaten(data) {
    currentKabupatenName = data.nama_kabupaten;
    
    document.getElementById('edit_id_kabupaten').value = data.id;
    document.getElementById('edit_nama_kabupaten').textContent = data.nama_kabupaten;
    document.getElementById('edit_jenis_pemilihan').value = data.jenis_pemilihan || 'pilbup';
    document.getElementById('edit_jenis_hitung').value = data.jenis_hitung || 'real_count';
    document.getElementById('edit_jumlah_tps_sample').value = data.jumlah_tps_sample || 0;
    document.getElementById('edit_public_detail_level').value = data.public_detail_level || 'full';
    document.getElementById('edit_tahun_pemilihan').value = data.tahun_pemilihan || new Date().getFullYear();
    document.getElementById('edit_is_active').checked = data.setting_active == 1;
    
    toggleSampleField();
    updatePreviewNama();
    
    new bootstrap.Modal(document.getElementById('modalEditKabupaten')).show();
}

// Functions untuk Edit Provinsi (Pilgub)
function toggleProvSampleField() {
    const jenisHitung = document.getElementById('edit_prov_jenis_hitung').value;
    const container = document.getElementById('provSampleContainer');
    container.style.display = (jenisHitung === 'quick_count') ? 'block' : 'none';
}

function editProvinsi(data) {
    document.getElementById('edit_prov_id_provinsi').value = data.id;
    document.getElementById('edit_prov_nama_provinsi').textContent = data.nama_provinsi;
    document.getElementById('edit_prov_jenis_hitung').value = data.jenis_hitung || 'real_count';
    document.getElementById('edit_prov_jumlah_tps_sample').value = data.jumlah_tps_sample || 0;
    document.getElementById('edit_prov_public_detail_level').value = data.public_detail_level || 'full';
    document.getElementById('edit_prov_tahun_pemilihan').value = data.tahun_pemilihan || new Date().getFullYear();
    document.getElementById('edit_prov_is_active').checked = data.setting_active == 1;
    
    toggleProvSampleField();
    
    new bootstrap.Modal(document.getElementById('modalEditProvinsi')).show();
}

// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#tabelKabupaten').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            },
            order: [[1, 'asc']]
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
