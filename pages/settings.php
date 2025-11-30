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
    $jenis_pemilihan = sanitize($_POST['jenis_pemilihan']);
    $jenis_hitung = sanitize($_POST['jenis_hitung']);
    $tingkat_wilayah = sanitize($_POST['tingkat_wilayah']);
    $tahun_pemilihan = intval($_POST['tahun_pemilihan']);
    $id_provinsi_aktif = !empty($_POST['id_provinsi_aktif']) ? intval($_POST['id_provinsi_aktif']) : null;
    $id_kabupaten_aktif = !empty($_POST['id_kabupaten_aktif']) ? intval($_POST['id_kabupaten_aktif']) : null;
    $id_desa_aktif = !empty($_POST['id_desa_aktif']) ? intval($_POST['id_desa_aktif']) : null;
    $jumlah_tps_sample = !empty($_POST['jumlah_tps_sample']) ? intval($_POST['jumlah_tps_sample']) : 0;
    $public_detail_level = sanitize($_POST['public_detail_level'] ?? 'full');
    $izinkan_edit_provinsi = isset($_POST['izinkan_edit_provinsi']) ? 1 : 0;
    $izinkan_edit_kabupaten = isset($_POST['izinkan_edit_kabupaten']) ? 1 : 0;
    $warna_tema = sanitize($_POST['warna_tema']);
    
    // Generate nama pemilihan otomatis
    $nama_pemilihan = generateNamaPemilihan($jenis_pemilihan, $id_provinsi_aktif, $id_kabupaten_aktif, $id_desa_aktif, $tahun_pemilihan);
    
    // Auto set public_detail_level based on jenis_hitung
    if ($jenis_hitung === 'quick_count') {
        $public_detail_level = 'minimal';
    }
    
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
            jenis_hitung = ?,
            tingkat_wilayah = ?,
            tahun_pemilihan = ?,
            id_provinsi_aktif = ?,
            id_kabupaten_aktif = ?,
            id_desa_aktif = ?,
            jumlah_tps_sample = ?,
            public_detail_level = ?,
            izinkan_edit_provinsi = ?,
            izinkan_edit_kabupaten = ?,
            warna_tema = ?";
    
    $params = [$nama_pemilihan, $jenis_pemilihan, $jenis_hitung, $tingkat_wilayah, $tahun_pemilihan, 
               $id_provinsi_aktif, $id_kabupaten_aktif, $id_desa_aktif, $jumlah_tps_sample, 
               $public_detail_level, $izinkan_edit_provinsi, $izinkan_edit_kabupaten, $warna_tema];
    $types = "ssssiiiiissis"; // 13 params: s,s,s,s,i,i,i,i,i,s,i,i,s
    
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tahun Pemilihan <span class="text-danger">*</span></label>
                            <input type="number" name="tahun_pemilihan" class="form-control" id="tahunPemilihan"
                                   value="<?= $settings['tahun_pemilihan'] ?? date('Y') ?>" 
                                   min="2020" max="2050" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label"><i class="bi bi-magic me-1"></i>Preview Nama Pemilihan (Otomatis)</label>
                            <div class="form-control bg-light" id="previewNamaPemilihan" style="font-weight: bold;">
                                <?= htmlspecialchars(getNamaPemilihan($settings)) ?>
                            </div>
                            <small class="text-muted">Nama pemilihan akan otomatis terbentuk dari jenis pemilihan, wilayah, dan tahun.</small>
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
                                <option value="pilkades" <?= ($settings['jenis_pemilihan'] ?? '') == 'pilkades' ? 'selected' : '' ?>>
                                    Pemilihan Kepala Desa (Pilkades)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tingkat Wilayah <span class="text-danger">*</span></label>
                            <select name="tingkat_wilayah" class="form-select" id="tingkatWilayah" required>
                                <option value="nasional" <?= ($settings['tingkat_wilayah'] ?? '') == 'nasional' ? 'selected' : '' ?>>
                                    Nasional (Seluruh Indonesia)
                                </option>
                                <option value="provinsi" <?= ($settings['tingkat_wilayah'] ?? '') == 'provinsi' ? 'selected' : '' ?>>
                                    Tingkat Provinsi
                                </option>
                                <option value="kabupaten" <?= ($settings['tingkat_wilayah'] ?? '') == 'kabupaten' ? 'selected' : '' ?>>
                                    Tingkat Kabupaten/Kota
                                </option>
                                <option value="desa" <?= ($settings['tingkat_wilayah'] ?? '') == 'desa' ? 'selected' : '' ?>>
                                    Tingkat Desa/Kelurahan
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Jenis Perhitungan -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-calculator me-2"></i>Jenis Perhitungan Suara
                    <span class="badge bg-secondary float-end">PENGATURAN DEFAULT</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Catatan:</strong> Pengaturan ini adalah <strong>default</strong>. Setiap kabupaten/provinsi memiliki pengaturan masing-masing 
                        yang dapat diubah di <a href="settings-daerah.php" class="alert-link">Pengaturan Per Daerah</a>.
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Metode Perhitungan <span class="text-danger">*</span></label>
                            <select name="jenis_hitung" class="form-select" id="jenisHitung" required>
                                <option value="real_count" <?= ($settings['jenis_hitung'] ?? 'real_count') == 'real_count' ? 'selected' : '' ?>>
                                    Real Count - Hitung Semua TPS
                                </option>
                                <option value="quick_count" <?= ($settings['jenis_hitung'] ?? '') == 'quick_count' ? 'selected' : '' ?>>
                                    Quick Count - Hitung TPS Sampel
                                </option>
                            </select>
                            <small class="text-muted">
                                <strong>Real Count:</strong> Menghitung suara dari semua TPS, 100% tercapai jika semua TPS sudah masuk.<br>
                                <strong>Quick Count:</strong> Menghitung suara dari TPS sampel terpilih, 100% tercapai jika semua TPS sampel sudah masuk.
                            </small>
                        </div>
                        <div class="col-md-6 mb-3" id="tpsSampleSection" style="display: none;">
                            <label class="form-label">Jumlah TPS Sampel</label>
                            <input type="number" name="jumlah_tps_sample" class="form-control" id="jumlahTpsSample"
                                   value="<?= $settings['jumlah_tps_sample'] ?? 0 ?>" min="0">
                            <small class="text-muted">
                                Jumlah TPS yang akan dijadikan sampel. Set 0 untuk memilih manual.
                                <a href="tps-sample.php" class="ms-2"><i class="bi bi-gear"></i> Kelola TPS Sampel</a>
                            </small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">Level Detail Tampilan Publik <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-3 mb-2 <?= ($settings['public_detail_level'] ?? 'full') == 'minimal' ? 'border-primary bg-light' : '' ?>">
                                        <input class="form-check-input" type="radio" name="public_detail_level" 
                                               id="levelMinimal" value="minimal" 
                                               <?= ($settings['public_detail_level'] ?? '') == 'minimal' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="levelMinimal">
                                            <strong><i class="bi bi-eye-slash me-1"></i>Minimal (Quick Count)</strong><br>
                                            <small class="text-muted">
                                                - Hanya tampilkan persentase suara<br>
                                                - Detail terbatas (Pilbup/Pilwalkot: sampai Kecamatan, Pilgub: sampai Kabupaten)<br>
                                                - Jumlah suara disembunyikan di publik
                                            </small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-3 mb-2 <?= ($settings['public_detail_level'] ?? 'full') == 'full' ? 'border-primary bg-light' : '' ?>">
                                        <input class="form-check-input" type="radio" name="public_detail_level" 
                                               id="levelFull" value="full"
                                               <?= ($settings['public_detail_level'] ?? 'full') == 'full' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="levelFull">
                                            <strong><i class="bi bi-eye me-1"></i>Lengkap (Real Count)</strong><br>
                                            <small class="text-muted">
                                                - Tampilkan persentase dan jumlah suara<br>
                                                - Detail lengkap sampai tingkat TPS<br>
                                                - Semua data terbuka untuk publik
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            </div>
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
                    <div class="alert alert-warning" id="alertFilterInfo">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Penting!</strong> Pengaturan wilayah aktif akan memfilter SEMUA data yang ditampilkan di dashboard, 
                        halaman publik, dan seluruh menu aplikasi. Pastikan memilih wilayah yang sesuai.
                    </div>
                    <div class="alert alert-info mb-3" id="filterGuidance">
                        <i class="bi bi-lightbulb me-2"></i>
                        <span id="filterGuidanceText">Pilih wilayah sesuai jenis pemilihan.</span>
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
                    
                    <!-- Desa Aktif (untuk Pilkades) -->
                    <div class="row" id="desaSection" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kecamatan</label>
                            <select class="form-select select2" id="kecamatanAktif">
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Desa/Kelurahan Aktif <span class="text-danger">*</span></label>
                            <select name="id_desa_aktif" class="form-select select2" id="desaAktif">
                                <option value="">-- Pilih Desa/Kelurahan --</option>
                            </select>
                            <small class="text-muted">Wajib dipilih untuk Pilkades</small>
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
$selectedDesa = $settings['id_desa_aktif'] ?? '';
$currentJenisPemilihan = $settings['jenis_pemilihan'] ?? 'pilbup';
$currentJenisHitung = $settings['jenis_hitung'] ?? 'real_count';
$additionalJS = <<<JS
<script>
// Label jenis pemilihan
const jenisPemilihanLabel = {
    'pilpres': 'PEMILIHAN PRESIDEN',
    'pilgub': 'PILKADA PROVINSI',
    'pilbup': 'PILKADA KABUPATEN',
    'pilwalkot': 'PILKADA KOTA',
    'pilkades': 'PILKADES'
};

// Mapping jenis pemilihan ke tingkat wilayah yang sesuai
const jenisTingkatMapping = {
    'pilpres': 'nasional',
    'pilgub': 'provinsi',
    'pilbup': 'kabupaten',
    'pilwalkot': 'kabupaten',
    'pilkades': 'desa'
};

// Guidance text per jenis pemilihan
const filterGuidanceText = {
    'pilpres': 'Pilpres menampilkan data nasional. Filter provinsi/kabupaten opsional.',
    'pilgub': '<strong>Wajib pilih Provinsi</strong> yang akan digunakan untuk Pilgub. Semua data akan difilter berdasarkan provinsi terpilih.',
    'pilbup': '<strong>Wajib pilih Provinsi dan Kabupaten</strong>. Semua data akan difilter berdasarkan kabupaten terpilih.',
    'pilwalkot': '<strong>Wajib pilih Provinsi dan Kota</strong>. Semua data akan difilter berdasarkan kota terpilih.',
    'pilkades': '<strong>Wajib pilih sampai tingkat Desa</strong>. Quick count dilakukan di satu desa saja.'
};

// Update preview nama pemilihan
function updatePreviewNamaPemilihan() {
    const jenisPemilihan = $('#jenisPemilihan').val();
    const tahun = $('#tahunPemilihan').val();
    const label = jenisPemilihanLabel[jenisPemilihan] || 'PEMILIHAN';
    let namaWilayah = '';
    
    switch(jenisPemilihan) {
        case 'pilpres':
            namaWilayah = 'INDONESIA';
            break;
        case 'pilgub':
            namaWilayah = $('#provinsiAktif option:selected').text().toUpperCase();
            if (namaWilayah.includes('SEMUA') || namaWilayah.includes('--')) namaWilayah = '[PILIH PROVINSI]';
            break;
        case 'pilbup':
        case 'pilwalkot':
            namaWilayah = $('#kabupatenAktif option:selected').text().toUpperCase();
            if (namaWilayah.includes('SEMUA') || namaWilayah.includes('--')) namaWilayah = '[PILIH KABUPATEN/KOTA]';
            break;
        case 'pilkades':
            const namaDesa = $('#desaAktif option:selected').text().toUpperCase();
            const namaKec = $('#kecamatanAktif option:selected').text().toUpperCase();
            if (namaDesa && !namaDesa.includes('--') && !namaDesa.includes('PILIH')) {
                namaWilayah = namaDesa + ' KEC. ' + namaKec;
            } else {
                namaWilayah = '[PILIH DESA]';
            }
            break;
    }
    
    $('#previewNamaPemilihan').text(label + ' ' + namaWilayah + ' ' + tahun);
}

// Toggle TPS Sample section
function toggleTpsSampleSection() {
    const jenisHitung = $('#jenisHitung').val();
    if (jenisHitung === 'quick_count') {
        $('#tpsSampleSection').show();
        // Auto select minimal for quick count
        $('#levelMinimal').prop('checked', true);
    } else {
        $('#tpsSampleSection').hide();
    }
}

// Toggle Desa section for Pilkades
function toggleDesaSection() {
    const jenisPemilihan = $('#jenisPemilihan').val();
    
    // Update filter guidance
    $('#filterGuidanceText').html(filterGuidanceText[jenisPemilihan] || 'Pilih wilayah sesuai jenis pemilihan.');
    
    // Auto-set tingkat wilayah
    const tingkatWilayah = jenisTingkatMapping[jenisPemilihan] || 'kabupaten';
    $('#tingkatWilayah').val(tingkatWilayah);
    
    // Toggle visibility based on jenis pemilihan
    if (jenisPemilihan === 'pilkades') {
        $('#desaSection').show();
        $('#tingkatWilayah').prop('disabled', true);
    } else {
        $('#desaSection').hide();
        $('#tingkatWilayah').prop('disabled', jenisPemilihan !== 'pilpres');
    }
    
    // Toggle field requirements visual cues
    toggleFieldRequirements(jenisPemilihan);
    
    updatePreviewNamaPemilihan();
}

// Toggle field requirements visual
function toggleFieldRequirements(jenisPemilihan) {
    // Reset all labels
    $('label[for="provinsiAktif"]').html('Provinsi Aktif');
    $('label[for="kabupatenAktif"]').html('Kabupaten/Kota Aktif');
    
    switch(jenisPemilihan) {
        case 'pilgub':
            $('label[for="provinsiAktif"]').html('Provinsi Aktif <span class="text-danger">*</span>');
            break;
        case 'pilbup':
        case 'pilwalkot':
            $('label[for="provinsiAktif"]').html('Provinsi Aktif <span class="text-danger">*</span>');
            $('label[for="kabupatenAktif"]').html('Kabupaten/Kota Aktif <span class="text-danger">*</span>');
            break;
        case 'pilkades':
            $('label[for="provinsiAktif"]').html('Provinsi Aktif <span class="text-danger">*</span>');
            $('label[for="kabupatenAktif"]').html('Kabupaten/Kota Aktif <span class="text-danger">*</span>');
            break;
    }
}

// Load kabupaten based on provinsi
$('#provinsiAktif').on('change', function() {
    const idProvinsi = $(this).val();
    const kabSelect = $('#kabupatenAktif');
    
    kabSelect.html('<option value="">-- Semua Kabupaten/Kota --</option>');
    $('#kecamatanAktif').html('<option value="">-- Pilih Kecamatan --</option>');
    $('#desaAktif').html('<option value="">-- Pilih Desa/Kelurahan --</option>');
    
    if (idProvinsi) {
        $.get('../api/get-kabupaten.php', { id_provinsi: idProvinsi }, function(data) {
            data.forEach(function(item) {
                kabSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
            });
            updatePreviewNamaPemilihan();
        });
    }
    updatePreviewNamaPemilihan();
});

// Load kecamatan based on kabupaten
$('#kabupatenAktif').on('change', function() {
    const idKab = $(this).val();
    const kecSelect = $('#kecamatanAktif');
    
    kecSelect.html('<option value="">-- Pilih Kecamatan --</option>');
    $('#desaAktif').html('<option value="">-- Pilih Desa/Kelurahan --</option>');
    
    if (idKab) {
        $.get('../api/get-kecamatan.php', { id_kabupaten: idKab }, function(data) {
            data.forEach(function(item) {
                kecSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
            });
        });
    }
    updatePreviewNamaPemilihan();
});

// Load desa based on kecamatan
$('#kecamatanAktif').on('change', function() {
    const idKec = $(this).val();
    const desaSelect = $('#desaAktif');
    
    desaSelect.html('<option value="">-- Pilih Desa/Kelurahan --</option>');
    
    if (idKec) {
        $.get('../api/get-desa.php', { id_kecamatan: idKec }, function(data) {
            data.forEach(function(item) {
                desaSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
            });
        });
    }
    updatePreviewNamaPemilihan();
});

// Desa change
$('#desaAktif').on('change', updatePreviewNamaPemilihan);

// Tahun change
$('#tahunPemilihan').on('change input', updatePreviewNamaPemilihan);

// Event handlers
$('#jenisHitung').on('change', toggleTpsSampleSection);
$('#jenisPemilihan').on('change', toggleDesaSection);

// Load initial kabupaten
$(document).ready(function() {
    const idProvinsi = $('#provinsiAktif').val();
    const selectedKab = '{$selectedKab}';
    const selectedDesa = '{$selectedDesa}';
    const jenisPemilihan = '{$currentJenisPemilihan}';
    
    // Initial toggle
    toggleTpsSampleSection();
    toggleDesaSection();
    
    if (idProvinsi) {
        $.get('../api/get-kabupaten.php', { id_provinsi: idProvinsi }, function(data) {
            const kabSelect = $('#kabupatenAktif');
            data.forEach(function(item) {
                const selected = item.id == selectedKab ? 'selected' : '';
                kabSelect.append('<option value="' + item.id + '" ' + selected + '>' + item.nama + '</option>');
            });
            updatePreviewNamaPemilihan();
            
            // If pilkades, load kecamatan and desa
            if (jenisPemilihan === 'pilkades' && selectedKab) {
                $.get('../api/get-kecamatan.php', { id_kabupaten: selectedKab }, function(kecData) {
                    const kecSelect = $('#kecamatanAktif');
                    kecData.forEach(function(item) {
                        kecSelect.append('<option value="' + item.id + '">' + item.nama + '</option>');
                    });
                    
                    // Load desa for selected desa
                    if (selectedDesa) {
                        // Get kecamatan of the selected desa first
                        $.get('../api/get-desa-info.php', { id_desa: selectedDesa }, function(desaInfo) {
                            if (desaInfo && desaInfo.id_kecamatan) {
                                $('#kecamatanAktif').val(desaInfo.id_kecamatan).trigger('change');
                                setTimeout(function() {
                                    $('#desaAktif').val(selectedDesa);
                                    updatePreviewNamaPemilihan();
                                }, 500);
                            }
                        });
                    }
                });
            }
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
