<?php
/**
 * Settings Page - Quick Count System
 */

require_once '../config/config.php';
requireAdmin();

$pageTitle = 'Konfigurasi Sistem';
$conn = getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pemilihan = sanitize($_POST['nama_pemilihan']);
    $jenis_pemilihan = sanitize($_POST['jenis_pemilihan']);
    $tingkat_wilayah = sanitize($_POST['tingkat_wilayah']);
    $tahun_pemilihan = intval($_POST['tahun_pemilihan']);
    $id_provinsi_aktif = !empty($_POST['id_provinsi_aktif']) ? intval($_POST['id_provinsi_aktif']) : null;
    $id_kabupaten_aktif = !empty($_POST['id_kabupaten_aktif']) ? intval($_POST['id_kabupaten_aktif']) : null;
    $izinkan_edit_provinsi = isset($_POST['izinkan_edit_provinsi']) ? 1 : 0;
    $izinkan_edit_kabupaten = isset($_POST['izinkan_edit_kabupaten']) ? 1 : 0;
    $warna_tema = sanitize($_POST['warna_tema']);
    
    // Handle logo upload
    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $result = uploadImage($_FILES['logo'], UPLOAD_PATH, 200);
        if ($result['success']) {
            $logo = $result['filename'];
        }
    }
    
    // Update settings
    $sql = "UPDATE settings SET 
            nama_pemilihan = ?, 
            jenis_pemilihan = ?, 
            tingkat_wilayah = ?,
            tahun_pemilihan = ?,
            id_provinsi_aktif = ?,
            id_kabupaten_aktif = ?,
            izinkan_edit_provinsi = ?,
            izinkan_edit_kabupaten = ?,
            warna_tema = ?";
    
    $params = [$nama_pemilihan, $jenis_pemilihan, $tingkat_wilayah, $tahun_pemilihan, 
               $id_provinsi_aktif, $id_kabupaten_aktif, $izinkan_edit_provinsi, $izinkan_edit_kabupaten, $warna_tema];
    $types = "sssiisiis";
    
    if ($logo) {
        $sql .= ", logo = ?";
        $params[] = $logo;
        $types .= "s";
    }
    
    $sql .= " WHERE id = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        setFlash('success', 'Konfigurasi berhasil disimpan!');
    } else {
        setFlash('error', 'Gagal menyimpan konfigurasi!');
    }
    
    header('Location: settings.php');
    exit;
}

// Get current settings
$settings = getSettings();
$provinsiList = getProvinsi();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Konfigurasi Sistem</h1>
    <p>Atur pengaturan dasar aplikasi quick count</p>
</div>

<form action="" method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-lg-8">
            <!-- Pengaturan Umum -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-gear me-2"></i>Pengaturan Umum
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nama Pemilihan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_pemilihan" class="form-control" 
                                   value="<?= htmlspecialchars($settings['nama_pemilihan'] ?? '') ?>" required>
                            <small class="text-muted">Contoh: Quick Count Pilkada Bone 2024</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tahun Pemilihan <span class="text-danger">*</span></label>
                            <input type="number" name="tahun_pemilihan" class="form-control" 
                                   value="<?= $settings['tahun_pemilihan'] ?? date('Y') ?>" 
                                   min="2020" max="2050" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Pemilihan <span class="text-danger">*</span></label>
                            <select name="jenis_pemilihan" class="form-select" id="jenisPemilihan" required>
                                <option value="pilpres" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilpres' ? 'selected' : '' ?>>
                                    Pemilihan Presiden (Pilpres)
                                </option>
                                <option value="pilgub" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilgub' ? 'selected' : '' ?>>
                                    Pemilihan Gubernur (Pilgub)
                                </option>
                                <option value="pilbup" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilbup' ? 'selected' : '' ?>>
                                    Pemilihan Bupati (Pilbup)
                                </option>
                                <option value="pilwalkot" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilwalkot' ? 'selected' : '' ?>>
                                    Pemilihan Walikota (Pilwalkot)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tingkat Wilayah <span class="text-danger">*</span></label>
                            <select name="tingkat_wilayah" class="form-select" required>
                                <option value="nasional" <?= ($settings['tingkat_wilayah'] ?? '') == 'nasional' ? 'selected' : '' ?>>
                                    Nasional (Seluruh Indonesia)
                                </option>
                                <option value="provinsi" <?= ($settings['tingkat_wilayah'] ?? '') == 'provinsi' ? 'selected' : '' ?>>
                                    Tingkat Provinsi
                                </option>
                                <option value="kabupaten" <?= ($settings['tingkat_wilayah'] ?? '') == 'kabupaten' ? 'selected' : '' ?>>
                                    Tingkat Kabupaten/Kota
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Wilayah -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-geo-alt me-2"></i>Filter Wilayah Aktif
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Kosongkan jika ingin menampilkan data seluruh wilayah. 
                        Pilih provinsi/kabupaten untuk memfilter data yang ditampilkan.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Provinsi Aktif</label>
                            <select name="id_provinsi_aktif" class="form-select select2" id="provinsiAktif">
                                <option value="">-- Semua Provinsi --</option>
                                <?php foreach ($provinsiList as $prov): ?>
                                <option value="<?= $prov['id'] ?>" <?= ($settings['id_provinsi_aktif'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kabupaten/Kota Aktif</label>
                            <select name="id_kabupaten_aktif" class="form-select select2" id="kabupatenAktif">
                                <option value="">-- Semua Kabupaten/Kota --</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Izinkan Edit Master Data -->
                    <hr class="my-3">
                    <h6 class="text-muted mb-3"><i class="bi bi-unlock me-2"></i>Izinkan Edit Master Data</h6>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Jika provinsi/kabupaten sudah dikonfigurasi, menu Master Data tersebut akan disembunyikan. 
                        Aktifkan opsi di bawah untuk tetap bisa mengedit master data.
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="izinkan_edit_provinsi" 
                                       id="izinkanEditProvinsi" value="1" 
                                       <?= ($settings['izinkan_edit_provinsi'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="izinkanEditProvinsi">
                                    <strong>Izinkan Edit Provinsi</strong><br>
                                    <small class="text-muted">Menu Provinsi tetap tampil meski sudah dikonfigurasi</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="izinkan_edit_kabupaten" 
                                       id="izinkanEditKabupaten" value="1"
                                       <?= ($settings['izinkan_edit_kabupaten'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="izinkanEditKabupaten">
                                    <strong>Izinkan Edit Kabupaten/Kota</strong><br>
                                    <small class="text-muted">Menu Kabupaten/Kota tetap tampil meski sudah dikonfigurasi</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Tampilan -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-palette me-2"></i>Tampilan
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <?php if (!empty($settings['logo'])): ?>
                        <div class="mt-2">
                            <img src="../uploads/<?= htmlspecialchars($settings['logo']) ?>" 
                                 alt="Logo" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Warna Tema</label>
                        <div class="input-group">
                            <input type="color" name="warna_tema" class="form-control form-control-color" 
                                   value="<?= $settings['warna_tema'] ?? '#4f46e5' ?>" style="width: 60px;">
                            <input type="text" class="form-control" 
                                   value="<?= $settings['warna_tema'] ?? '#4f46e5' ?>" 
                                   id="warnaText" readonly>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>Informasi
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>Versi Aplikasi</td>
                            <td class="text-end"><strong><?= APP_VERSION ?></strong></td>
                        </tr>
                        <tr>
                            <td>Terakhir Update</td>
                            <td class="text-end">
                                <strong><?= date('d/m/Y H:i', strtotime($settings['updated_at'] ?? 'now')) ?></strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-2"></i>Simpan Pengaturan
                </button>
            </div>
        </div>
    </div>
</form>

<?php
$selectedKab = $settings['id_kabupaten_aktif'] ?? '';
$additionalJS = <<<JS
<script>
// Load kabupaten based on provinsi
$('#provinsiAktif').on('change', function() {
    const idProvinsi = $(this).val();
    const kabSelect = $('#kabupatenAktif');
    
    kabSelect.html('<option value="">-- Semua Kabupaten/Kota --</option>');
    
    if (idProvinsi) {
        $.get('../api/get-kabupaten.php', { id_provinsi: idProvinsi }, function(data) {
            data.forEach(function(item) {
                kabSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
            });
        });
    }
});

// Load initial kabupaten
$(document).ready(function() {
    const idProvinsi = $('#provinsiAktif').val();
    const selectedKab = '{$selectedKab}';
    
    if (idProvinsi) {
        $.get('../api/get-kabupaten.php', { id_provinsi: idProvinsi }, function(data) {
            const kabSelect = $('#kabupatenAktif');
            data.forEach(function(item) {
                const selected = item.id == selectedKab ? 'selected' : '';
                kabSelect.append('<option value="' + item.id + '" ' + selected + '>' + item.nama + '</option>');
            });
        });
    }
});

// Update color text
$('input[name="warna_tema"]').on('input', function() {
    $('#warnaText').val($(this).val());
});
</script>
JS;

include '../includes/footer.php';
?>
