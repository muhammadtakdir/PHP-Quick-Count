<?php
/**
 * Input Suara - Quick Count System
 */

require_once 'config/config.php';
requireLogin();

// Viewer tidak bisa input suara
if (hasRole(['viewer'])) {
    setFlash('error', 'Anda tidak memiliki akses untuk input suara!');
    header('Location: ' . APP_URL . 'grafik.php');
    exit;
}

$pageTitle = 'Input Suara';
$conn = getConnection();
$settings = getSettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tps = intval($_POST['id_tps']);
    $suara = $_POST['suara'] ?? [];
    $suara_tidak_sah = intval($_POST['suara_tidak_sah'] ?? 0);
    $catatan = sanitize($_POST['catatan'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    // Handle foto C1
    $foto_c1 = null;
    if (!empty($_FILES['foto_c1']['name'])) {
        $result = uploadImage($_FILES['foto_c1'], FOTO_C1_PATH, 1200);
        if ($result['success']) {
            $foto_c1 = $result['filename'];
        }
    }
    
    $success = true;
    $conn->begin_transaction();
    
    try {
        // Delete existing suara for this TPS
        $stmt = $conn->prepare("DELETE FROM suara WHERE id_tps = ?");
        $stmt->bind_param("i", $id_tps);
        $stmt->execute();
        
        // Insert new suara
        foreach ($suara as $id_calon => $jumlah) {
            $jumlah = intval($jumlah);
            $stmt = $conn->prepare("INSERT INTO suara (id_tps, id_calon, jumlah_suara, suara_tidak_sah, foto_c1, catatan, input_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiissi", $id_tps, $id_calon, $jumlah, $suara_tidak_sah, $foto_c1, $catatan, $user_id);
            $stmt->execute();
        }
        
        $conn->commit();
        setFlash('success', 'Data suara berhasil disimpan!');
    } catch (Exception $e) {
        $conn->rollback();
        setFlash('error', 'Gagal menyimpan data suara!');
    }
    
    header('Location: input-suara.php');
    exit;
}

// Get dropdowns based on settings
$provinsiList = getProvinsi();
$calonList = getCalon($settings['jenis_pemilihan'], $settings['id_provinsi_aktif'], $settings['id_kabupaten_aktif']);

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Input Suara</h1>
    <p>Masukkan data perolehan suara per TPS</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil-square me-2"></i>Form Input Suara
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data" id="formSuara">
                    <!-- Pilih Lokasi TPS -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                            <select id="provinsi" class="form-select" required <?= !empty($settings['id_provinsi_aktif']) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Provinsi --</option>
                                <?php foreach ($provinsiList as $prov): ?>
                                <option value="<?= $prov['id'] ?>" 
                                    <?= ($settings['id_provinsi_aktif'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($settings['id_provinsi_aktif'])): ?>
                            <input type="hidden" name="provinsi_val" value="<?= $settings['id_provinsi_aktif'] ?>">
                            <small class="text-muted"><i class="bi bi-lock"></i> Dikunci sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kabupaten/Kota <span class="text-danger">*</span></label>
                            <select id="kabupaten" class="form-select" required <?= !empty($settings['id_kabupaten_aktif']) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Kabupaten --</option>
                            </select>
                            <?php if (!empty($settings['id_kabupaten_aktif'])): ?>
                            <input type="hidden" name="kabupaten_val" value="<?= $settings['id_kabupaten_aktif'] ?>">
                            <small class="text-muted"><i class="bi bi-lock"></i> Dikunci sesuai konfigurasi</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                            <select id="kecamatan" class="form-select" required>
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Desa/Kelurahan <span class="text-danger">*</span></label>
                            <select id="desa" class="form-select" required>
                                <option value="">-- Pilih Desa --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">TPS <span class="text-danger">*</span></label>
                            <select name="id_tps" id="tps" class="form-select" required>
                                <option value="">-- Pilih TPS --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah DPT</label>
                            <input type="text" id="dpt" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Input Suara per Calon -->
                    <h5 class="mb-3"><i class="bi bi-people me-2"></i>Perolehan Suara</h5>
                    
                    <?php if (empty($calonList)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Belum ada data calon. Silakan tambahkan calon terlebih dahulu.
                    </div>
                    <?php else: ?>
                    <div class="row" id="suaraInputs">
                        <?php foreach ($calonList as $calon): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100" style="border-left: 4px solid <?= $calon['warna'] ?>">
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 45px; height: 45px; background: <?= $calon['warna'] ?>; color: white; font-weight: bold;">
                                                <?= $calon['nomor_urut'] ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 mx-3">
                                            <strong><?= htmlspecialchars($calon['nama_calon']) ?></strong>
                                            <?php if ($calon['nama_wakil']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($calon['nama_wakil']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <input type="number" name="suara[<?= $calon['id'] ?>]" 
                                                   class="form-control text-center suara-input" 
                                                   style="width: 100px" min="0" value="0" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Suara Tidak Sah</label>
                            <input type="number" name="suara_tidak_sah" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Suara Sah</label>
                            <input type="text" id="totalSuara" class="form-control fw-bold" readonly>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <!-- Upload Foto C1 -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto Formulir C1</label>
                            <input type="file" name="foto_c1" class="form-control" accept="image/*">
                            <small class="text-muted">Upload foto formulir C1 sebagai bukti</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg" <?= empty($calonList) ? 'disabled' : '' ?>>
                            <i class="bi bi-check-lg me-2"></i>Simpan Suara
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Informasi
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Jenis Pemilihan:</strong></p>
                <p class="text-primary mb-3">
                    <?= ucfirst(str_replace(['pilpres', 'pilgub', 'pilbup', 'pilwalkot'], 
                        ['Pemilihan Presiden', 'Pemilihan Gubernur', 'Pemilihan Bupati', 'Pemilihan Walikota'], 
                        $settings['jenis_pemilihan'])) ?>
                </p>
                <p class="mb-2"><strong>Jumlah Calon:</strong></p>
                <p class="mb-3"><?= count($calonList) ?> pasangan calon</p>
                
                <div class="alert alert-info mb-0">
                    <i class="bi bi-lightbulb me-2"></i>
                    <small>Pastikan data suara yang diinput sesuai dengan formulir C1 hasil pemungutan suara di TPS.</small>
                </div>
            </div>
        </div>
        
        <!-- TPS Status -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-box me-2"></i>Status TPS Terpilih
            </div>
            <div class="card-body" id="tpsInfo">
                <p class="text-muted text-center mb-0">Pilih TPS untuk melihat status</p>
            </div>
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
$(document).ready(function() {
    // Configuration flags
    const defaultProv = '{$defaultProvId}';
    const defaultKab = '{$defaultKabId}';
    const provLocked = {$provLocked};
    const kabLocked = {$kabLocked};

    // Helper functions for loading cascading data
    function loadKabupaten(provId, selectedId, callback) {
        if (!provLocked) {
            $('#kabupaten').html('<option value="">-- Memuat... --</option>');
        }
        $('#kecamatan, #desa, #tps').html('<option value="">-- Pilih --</option>');
        
        if (!provId) {
            $('#kabupaten').html('<option value="">-- Pilih Kabupaten --</option>');
            return;
        }
        
        $.get('api/get-kabupaten.php', { id_provinsi: provId }, function(data) {
            $('#kabupaten').html('<option value="">-- Pilih Kabupaten --</option>');
            data.forEach(function(item) {
                var selected = item.id == selectedId ? 'selected' : '';
                $('#kabupaten').append('<option value="' + item.id + '" ' + selected + '>' + item.nama + '</option>');
            });
            if (callback) callback();
        });
    }

    function loadKecamatan(kabId, selectedId, callback) {
        $('#kecamatan').html('<option value="">-- Memuat... --</option>');
        $('#desa, #tps').html('<option value="">-- Pilih --</option>');
        
        if (!kabId) {
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>');
            return;
        }
        
        $.get('api/get-kecamatan.php', { id_kabupaten: kabId }, function(data) {
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>');
            data.forEach(function(item) {
                var selected = item.id == selectedId ? 'selected' : '';
                $('#kecamatan').append('<option value="' + item.id + '" ' + selected + '>' + item.nama + '</option>');
            });
            if (callback) callback();
        });
    }

    function loadDesa(kecId, selectedId, callback) {
        $('#desa').html('<option value="">-- Memuat... --</option>');
        $('#tps').html('<option value="">-- Pilih TPS --</option>');
        
        if (!kecId) {
            $('#desa').html('<option value="">-- Pilih Desa --</option>');
            return;
        }
        
        $.get('api/get-desa.php', { id_kecamatan: kecId }, function(data) {
            $('#desa').html('<option value="">-- Pilih Desa --</option>');
            data.forEach(function(item) {
                var selected = item.id == selectedId ? 'selected' : '';
                $('#desa').append('<option value="' + item.id + '" ' + selected + '>' + item.nama + '</option>');
            });
            if (callback) callback();
        });
    }

    function loadTPS(desaId, selectedId, callback) {
        $('#tps').html('<option value="">-- Memuat... --</option>');
        
        if (!desaId) {
            $('#tps').html('<option value="">-- Pilih TPS --</option>');
            return;
        }
        
        $.get('api/get-tps.php', { id_desa: desaId }, function(data) {
            $('#tps').html('<option value="">-- Pilih TPS --</option>');
            data.forEach(function(item) {
                var selected = item.id == selectedId ? 'selected' : '';
                $('#tps').append('<option value="' + item.id + '" data-dpt="' + item.dpt + '" ' + selected + '>' + item.nama + '</option>');
            });
            if (callback) callback();
        });
    }

    function calculateTotal() {
        var total = 0;
        $('.suara-input').each(function() {
            total += parseInt($(this).val()) || 0;
        });
        $('#totalSuara').val(total.toLocaleString('id-ID'));
    }

    // Cascading dropdown events
    $('#provinsi').on('change', function() {
        if (!provLocked) {
            loadKabupaten($(this).val());
            $('#dpt').val('');
        }
    });

    $('#kabupaten').on('change', function() {
        if (!kabLocked) {
            loadKecamatan($(this).val());
        }
    });

    $('#kecamatan').on('change', function() {
        var kecId = $(this).val();
        console.log('Kecamatan changed:', kecId);
        loadDesa(kecId);
    });

    $('#desa').on('change', function() {
        var desaId = $(this).val();
        console.log('Desa changed:', desaId);
        loadTPS(desaId);
    });

    $('#tps').on('change', function() {
        var selected = $(this).find(':selected');
        var dpt = selected.data('dpt') || 0;
        var id = $(this).val();
        
        $('#dpt').val(dpt ? dpt.toLocaleString('id-ID') : '');
        
        if (id) {
            $.get('api/get-suara.php', { id_tps: id }, function(data) {
                if (data.suara && data.suara.length > 0) {
                    data.suara.forEach(function(s) {
                        $('input[name="suara[' + s.id_calon + ']"]').val(s.jumlah_suara);
                    });
                    $('input[name="suara_tidak_sah"]').val(data.suara[0].suara_tidak_sah || 0);
                    
                    $('#tpsInfo').html(
                        '<div class="alert alert-success mb-0">' +
                        '<i class="bi bi-check-circle me-2"></i>' +
                        '<strong>Sudah Diinput</strong><br>' +
                        '<small>Data akan diupdate jika disimpan ulang</small>' +
                        '</div>'
                    );
                } else {
                    $('.suara-input').val(0);
                    $('input[name="suara_tidak_sah"]').val(0);
                    
                    $('#tpsInfo').html(
                        '<div class="alert alert-warning mb-0">' +
                        '<i class="bi bi-exclamation-circle me-2"></i>' +
                        '<strong>Belum Diinput</strong><br>' +
                        '<small>Silakan masukkan data suara</small>' +
                        '</div>'
                    );
                }
                calculateTotal();
            });
        } else {
            $('#tpsInfo').html('<p class="text-muted text-center mb-0">Pilih TPS untuk melihat status</p>');
        }
    });

    // Calculate total on input
    $('.suara-input').on('input', calculateTotal);

    // Load initial data based on settings
    if (provLocked && kabLocked) {
        loadKabupaten(defaultProv, defaultKab, function() {
            loadKecamatan(defaultKab);
        });
    } else if (provLocked && !kabLocked) {
        loadKabupaten(defaultProv);
    }
    
    calculateTotal();

    // Form validation
    $('#formSuara').on('submit', function(e) {
        var tps = $('#tps').val();
        if (!tps) {
            e.preventDefault();
            Swal.fire('Error', 'Silakan pilih TPS terlebih dahulu!', 'error');
            return false;
        }
        return true;
    });
});
</script>
JS;

include 'includes/footer.php';
?>
