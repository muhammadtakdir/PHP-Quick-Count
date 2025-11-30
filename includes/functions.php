<?php
/**
 * Helper Functions
 * Quick Count System
 */

/**
 * Get filtered data based on settings configuration
 * Fungsi ini menentukan scope data berdasarkan jenis_pemilihan dan tingkat_wilayah
 * 
 * @param array $settings Settings aplikasi
 * @return array Filter yang harus diterapkan
 */
function getConfigFilter($settings = null) {
    if (!$settings) {
        $settings = getSettings();
    }
    
    $filter = [
        'jenis_pemilihan' => $settings['jenis_pemilihan'] ?? 'pilbup',
        'tingkat_wilayah' => $settings['tingkat_wilayah'] ?? 'kabupaten',
        'id_provinsi' => null,
        'id_kabupaten' => null,
        'id_desa' => null,
        'scope' => 'all' // all, provinsi, kabupaten, desa
    ];
    
    $jenis = $filter['jenis_pemilihan'];
    $tingkat = $filter['tingkat_wilayah'];
    
    // Tentukan scope berdasarkan jenis pemilihan dan tingkat wilayah
    switch ($jenis) {
        case 'pilgub':
            // Pilgub: provinsi aktif adalah scope utama
            $filter['id_provinsi'] = $settings['id_provinsi_aktif'] ?? null;
            $filter['scope'] = 'provinsi';
            break;
            
        case 'pilbup':
        case 'pilwalkot':
            // Pilbup/Pilwalkot: kabupaten aktif adalah scope utama
            $filter['id_provinsi'] = $settings['id_provinsi_aktif'] ?? null;
            $filter['id_kabupaten'] = $settings['id_kabupaten_aktif'] ?? null;
            $filter['scope'] = 'kabupaten';
            break;
            
        case 'pilkades':
            // Pilkades: desa aktif adalah scope utama
            $filter['id_provinsi'] = $settings['id_provinsi_aktif'] ?? null;
            $filter['id_kabupaten'] = $settings['id_kabupaten_aktif'] ?? null;
            $filter['id_desa'] = $settings['id_desa_aktif'] ?? null;
            $filter['scope'] = 'desa';
            break;
            
        case 'pilpres':
            // Pilpres: nasional, tidak ada filter wilayah
            $filter['scope'] = 'all';
            break;
    }
    
    return $filter;
}

/**
 * Get provinsi list berdasarkan konfigurasi
 * Jika sudah dikonfigurasi, hanya tampilkan provinsi yang aktif
 */
function getProvinsiFiltered($settings = null, $active_only = true) {
    if (!$settings) {
        $settings = getSettings();
    }
    
    $conn = getConnection();
    $sql = "SELECT * FROM provinsi WHERE 1=1";
    
    // Jika provinsi aktif sudah dikonfigurasi, hanya tampilkan itu
    if (!empty($settings['id_provinsi_aktif'])) {
        $sql .= " AND id = " . intval($settings['id_provinsi_aktif']);
    }
    
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    
    $sql .= " ORDER BY nama ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get kabupaten list berdasarkan konfigurasi
 */
function getKabupatenFiltered($settings = null, $id_provinsi = null, $active_only = true) {
    if (!$settings) {
        $settings = getSettings();
    }
    
    $conn = getConnection();
    $sql = "SELECT * FROM kabupaten WHERE 1=1";
    
    // Jika kabupaten aktif sudah dikonfigurasi, hanya tampilkan itu
    if (!empty($settings['id_kabupaten_aktif'])) {
        $sql .= " AND id = " . intval($settings['id_kabupaten_aktif']);
    } else {
        // Filter by provinsi
        if ($id_provinsi) {
            $sql .= " AND id_provinsi = " . intval($id_provinsi);
        } elseif (!empty($settings['id_provinsi_aktif'])) {
            $sql .= " AND id_provinsi = " . intval($settings['id_provinsi_aktif']);
        }
    }
    
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    
    $sql .= " ORDER BY nama ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check if current config is for specific jenis pemilihan
 */
function isPilgub($settings = null) {
    if (!$settings) $settings = getSettings();
    return ($settings['jenis_pemilihan'] ?? '') === 'pilgub';
}

function isPilbup($settings = null) {
    if (!$settings) $settings = getSettings();
    return in_array($settings['jenis_pemilihan'] ?? '', ['pilbup', 'pilwalkot']);
}

function isPilkades($settings = null) {
    if (!$settings) $settings = getSettings();
    return ($settings['jenis_pemilihan'] ?? '') === 'pilkades';
}

/**
 * Get nama wilayah aktif untuk display
 */
function getActiveWilayahName($settings = null) {
    if (!$settings) $settings = getSettings();
    $conn = getConnection();
    
    $names = [];
    
    if (!empty($settings['id_provinsi_aktif'])) {
        $result = $conn->query("SELECT nama FROM provinsi WHERE id = " . intval($settings['id_provinsi_aktif']));
        if ($row = $result->fetch_assoc()) {
            $names['provinsi'] = $row['nama'];
        }
    }
    
    if (!empty($settings['id_kabupaten_aktif'])) {
        $result = $conn->query("SELECT nama, tipe FROM kabupaten WHERE id = " . intval($settings['id_kabupaten_aktif']));
        if ($row = $result->fetch_assoc()) {
            $prefix = ($row['tipe'] == 'kota') ? 'Kota ' : 'Kabupaten ';
            $names['kabupaten'] = $prefix . $row['nama'];
        }
    }
    
    if (!empty($settings['id_desa_aktif'])) {
        $result = $conn->query("SELECT nama, tipe FROM desa WHERE id = " . intval($settings['id_desa_aktif']));
        if ($row = $result->fetch_assoc()) {
            $prefix = ($row['tipe'] == 'kelurahan') ? 'Kelurahan ' : 'Desa ';
            $names['desa'] = $prefix . $row['nama'];
        }
    }
    
    return $names;
}

/**
 * Cek apakah filter dropdown harus dikunci
 */
function isFilterLocked($level, $settings = null) {
    if (!$settings) $settings = getSettings();
    
    switch ($level) {
        case 'provinsi':
            return !empty($settings['id_provinsi_aktif']);
        case 'kabupaten':
            return !empty($settings['id_kabupaten_aktif']);
        case 'desa':
            return !empty($settings['id_desa_aktif']);
    }
    return false;
}

/**
 * Get locked filter value
 */
function getLockedFilterValue($level, $settings = null) {
    if (!$settings) $settings = getSettings();
    
    switch ($level) {
        case 'provinsi':
            return $settings['id_provinsi_aktif'] ?? null;
        case 'kabupaten':
            return $settings['id_kabupaten_aktif'] ?? null;
        case 'desa':
            return $settings['id_desa_aktif'] ?? null;
    }
    return null;
}

/**
 * Get all provinces
 */
function getProvinsi($active_only = true) {
    $conn = getConnection();
    $sql = "SELECT * FROM provinsi";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY nama ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get kabupaten by provinsi
 */
function getKabupaten($id_provinsi = null, $active_only = true) {
    $conn = getConnection();
    $sql = "SELECT * FROM kabupaten WHERE 1=1";
    if ($id_provinsi) {
        $sql .= " AND id_provinsi = " . intval($id_provinsi);
    }
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY nama ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get kecamatan by kabupaten
 */
function getKecamatan($id_kabupaten = null, $active_only = true) {
    $conn = getConnection();
    $sql = "SELECT * FROM kecamatan WHERE 1=1";
    if ($id_kabupaten) {
        $sql .= " AND id_kabupaten = " . intval($id_kabupaten);
    }
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY nama ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get desa by kecamatan
 */
function getDesa($id_kecamatan = null, $active_only = true) {
    $conn = getConnection();
    $sql = "SELECT * FROM desa WHERE 1=1";
    if ($id_kecamatan) {
        $sql .= " AND id_kecamatan = " . intval($id_kecamatan);
    }
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY nama ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get TPS by desa
 */
function getTPS($id_desa = null, $active_only = true) {
    $conn = getConnection();
    $sql = "SELECT * FROM tps WHERE 1=1";
    if ($id_desa) {
        $sql .= " AND id_desa = " . intval($id_desa);
    }
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY nomor_tps ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get calon by jenis pemilihan
 */
function getCalon($jenis = null, $id_provinsi = null, $id_kabupaten = null) {
    $conn = getConnection();
    $sql = "SELECT * FROM calon WHERE is_active = 1";
    
    if ($jenis) {
        $sql .= " AND jenis_pemilihan = '" . $conn->real_escape_string($jenis) . "'";
    }
    if ($id_provinsi) {
        $sql .= " AND (id_provinsi = " . intval($id_provinsi) . " OR id_provinsi IS NULL)";
    }
    if ($id_kabupaten) {
        $sql .= " AND (id_kabupaten = " . intval($id_kabupaten) . " OR id_kabupaten IS NULL)";
    }
    
    $sql .= " ORDER BY nomor_urut ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get total suara per calon
 */
function getTotalSuaraCalon($id_calon) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(jumlah_suara), 0) as total FROM suara WHERE id_calon = ?");
    $stmt->bind_param("i", $id_calon);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'];
}

/**
 * Get rekap suara per calon dengan filter wilayah
 */
function getRekapSuara($filter = []) {
    $conn = getConnection();
    
    $sql = "SELECT 
                c.id, c.nomor_urut, c.nama_calon, c.nama_wakil, 
                c.foto_calon, c.foto_wakil, c.warna,
                COALESCE(SUM(s.jumlah_suara), 0) as total_suara
            FROM calon c
            LEFT JOIN suara s ON c.id = s.id_calon";
    
    // Join tables for filtering suara by wilayah
    if (!empty($filter['id_desa']) || !empty($filter['id_kecamatan']) || 
        !empty($filter['id_kabupaten']) || !empty($filter['id_provinsi'])) {
        $sql .= " LEFT JOIN tps t ON s.id_tps = t.id
                  LEFT JOIN desa d ON t.id_desa = d.id
                  LEFT JOIN kecamatan kec ON d.id_kecamatan = kec.id
                  LEFT JOIN kabupaten kab ON kec.id_kabupaten = kab.id";
    }
    
    $sql .= " WHERE c.is_active = 1";
    
    // Filter calon berdasarkan jenis pemilihan
    if (!empty($filter['jenis_pemilihan'])) {
        $sql .= " AND c.jenis_pemilihan = '" . $conn->real_escape_string($filter['jenis_pemilihan']) . "'";
        
        // Filter calon juga berdasarkan wilayah sesuai jenis pemilihan
        $jenis = $filter['jenis_pemilihan'];
        if ($jenis === 'pilgub' && !empty($filter['id_provinsi'])) {
            $sql .= " AND c.id_provinsi = " . intval($filter['id_provinsi']);
        } elseif (in_array($jenis, ['pilbup', 'pilwalkot']) && !empty($filter['id_kabupaten'])) {
            $sql .= " AND c.id_kabupaten = " . intval($filter['id_kabupaten']);
        } elseif ($jenis === 'pilkades' && !empty($filter['id_desa'])) {
            $sql .= " AND c.id_desa = " . intval($filter['id_desa']);
        }
    }
    
    // Filter suara berdasarkan wilayah TPS (untuk drill-down)
    if (!empty($filter['id_provinsi']) && empty($filter['id_kabupaten'])) {
        $sql .= " AND (kab.id_provinsi = " . intval($filter['id_provinsi']) . " OR s.id IS NULL)";
    }
    if (!empty($filter['id_kabupaten']) && empty($filter['id_kecamatan'])) {
        $sql .= " AND (kec.id_kabupaten = " . intval($filter['id_kabupaten']) . " OR s.id IS NULL)";
    }
    if (!empty($filter['id_kecamatan']) && empty($filter['id_desa'])) {
        $sql .= " AND (d.id_kecamatan = " . intval($filter['id_kecamatan']) . " OR s.id IS NULL)";
    }
    if (!empty($filter['id_desa'])) {
        $sql .= " AND (t.id_desa = " . intval($filter['id_desa']) . " OR s.id IS NULL)";
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.nomor_urut ASC";
    
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get statistik TPS
 * @param array $filter Filter wilayah
 * @param bool $sampleOnly Hanya hitung TPS sampel (untuk quick count)
 */
function getStatistikTPS($filter = [], $sampleOnly = false) {
    $conn = getConnection();
    
    // Build subquery untuk TPS yang sudah masuk data suara (berdasarkan jenis pemilihan)
    $suaraJoin = "LEFT JOIN (
        SELECT DISTINCT s.id_tps 
        FROM suara s 
        JOIN calon c ON s.id_calon = c.id 
        WHERE 1=1";
    
    if (!empty($filter['jenis_pemilihan'])) {
        $suaraJoin .= " AND c.jenis_pemilihan = '" . $conn->real_escape_string($filter['jenis_pemilihan']) . "'";
    }
    
    $suaraJoin .= ") sm ON t.id = sm.id_tps";
    
    $sql = "SELECT 
                COUNT(DISTINCT t.id) as total_tps,
                COUNT(DISTINCT CASE WHEN sm.id_tps IS NOT NULL THEN t.id END) as tps_masuk,
                COALESCE(SUM(DISTINCT t.dpt), 0) as total_dpt
            FROM tps t
            LEFT JOIN desa d ON t.id_desa = d.id
            LEFT JOIN kecamatan kec ON d.id_kecamatan = kec.id
            LEFT JOIN kabupaten kab ON kec.id_kabupaten = kab.id
            $suaraJoin";
    
    if ($sampleOnly) {
        $sql .= " INNER JOIN tps_sample ts ON t.id = ts.tps_id AND ts.is_selected = 1";
    }
    
    $sql .= " WHERE t.is_active = 1";
    
    if (!empty($filter['id_provinsi'])) {
        $sql .= " AND kab.id_provinsi = " . intval($filter['id_provinsi']);
    }
    if (!empty($filter['id_kabupaten'])) {
        $sql .= " AND kec.id_kabupaten = " . intval($filter['id_kabupaten']);
    }
    if (!empty($filter['id_kecamatan'])) {
        $sql .= " AND d.id_kecamatan = " . intval($filter['id_kecamatan']);
    }
    if (!empty($filter['id_desa'])) {
        $sql .= " AND t.id_desa = " . intval($filter['id_desa']);
    }
    
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

/**
 * Get rekap suara dengan dukungan quick count (hanya TPS sampel)
 * @param array $filter Filter wilayah dan jenis pemilihan
 */
function getRekapSuaraQuickCount($filter = []) {
    $conn = getConnection();
    
    $sql = "SELECT 
                c.id, c.nomor_urut, c.nama_calon, c.nama_wakil, 
                c.foto_calon, c.foto_wakil, c.warna,
                COALESCE(SUM(s.jumlah_suara), 0) as total_suara
            FROM calon c
            LEFT JOIN suara s ON c.id = s.id_calon
            LEFT JOIN tps t ON s.id_tps = t.id
            LEFT JOIN tps_sample ts ON t.id = ts.tps_id AND ts.is_selected = 1
            LEFT JOIN desa d ON t.id_desa = d.id
            LEFT JOIN kecamatan kec ON d.id_kecamatan = kec.id
            LEFT JOIN kabupaten kab ON kec.id_kabupaten = kab.id
            WHERE c.is_active = 1";
    
    // Hanya hitung suara dari TPS sampel
    $sql .= " AND (s.id IS NULL OR ts.tps_id IS NOT NULL)";
    
    if (!empty($filter['jenis_pemilihan'])) {
        $sql .= " AND c.jenis_pemilihan = '" . $conn->real_escape_string($filter['jenis_pemilihan']) . "'";
    }
    if (!empty($filter['id_provinsi'])) {
        $sql .= " AND (kab.id_provinsi = " . intval($filter['id_provinsi']) . " OR s.id IS NULL)";
    }
    if (!empty($filter['id_kabupaten'])) {
        $sql .= " AND (kec.id_kabupaten = " . intval($filter['id_kabupaten']) . " OR s.id IS NULL)";
    }
    if (!empty($filter['id_kecamatan'])) {
        $sql .= " AND (d.id_kecamatan = " . intval($filter['id_kecamatan']) . " OR s.id IS NULL)";
    }
    if (!empty($filter['id_desa'])) {
        $sql .= " AND (t.id_desa = " . intval($filter['id_desa']) . " OR s.id IS NULL)";
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.nomor_urut ASC";
    
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Cek apakah user boleh melihat detail level tertentu
 * @param string $level Level yang ingin dilihat: kecamatan, desa, tps
 * @param array $settings Settings aplikasi
 * @param bool $isAdmin Apakah user adalah admin
 */
function canViewDetailLevel($level, $settings, $isAdmin = false) {
    // Admin selalu bisa lihat semua
    if ($isAdmin) return true;
    
    // Jika real count dengan full detail, semua boleh dilihat
    if ($settings['jenis_hitung'] === 'real_count' && $settings['public_detail_level'] === 'full') {
        return true;
    }
    
    // Quick count atau minimal detail
    if ($settings['public_detail_level'] === 'minimal') {
        $jenis = $settings['jenis_pemilihan'];
        
        // Pilkades - tidak ada drill down
        if ($jenis === 'pilkades') {
            return false;
        }
        
        // Pilgub - hanya sampai kabupaten
        if ($jenis === 'pilgub') {
            return in_array($level, ['kabupaten']);
        }
        
        // Pilbup/Pilwalkot - sampai kecamatan
        if (in_array($jenis, ['pilbup', 'pilwalkot'])) {
            return in_array($level, ['kecamatan']);
        }
        
        // Pilpres - sampai provinsi
        if ($jenis === 'pilpres') {
            return in_array($level, ['provinsi', 'kabupaten']);
        }
    }
    
    return true;
}

/**
 * Get jumlah TPS sampel yang sudah masuk
 */
function getSampleProgress() {
    $conn = getConnection();
    $result = $conn->query("
        SELECT 
            COUNT(DISTINCT ts.tps_id) as total_sample,
            COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN ts.tps_id END) as sample_masuk
        FROM tps_sample ts
        LEFT JOIN suara s ON ts.tps_id = s.id_tps
    ");
    return $result->fetch_assoc();
}

/**
 * Get rekap per kecamatan untuk tampilan publik
 */
function getRekapPerKecamatan($id_kabupaten, $sampleOnly = false) {
    $conn = getConnection();
    
    $sql = "SELECT 
                kec.id, kec.nama,
                COUNT(DISTINCT t.id) as total_tps,
                COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN t.id END) as tps_masuk,
                c.id as calon_id, c.nomor_urut, c.nama_calon, c.warna,
                COALESCE(SUM(s.jumlah_suara), 0) as total_suara
            FROM kecamatan kec
            LEFT JOIN desa d ON d.id_kecamatan = kec.id
            LEFT JOIN tps t ON t.id_desa = d.id AND t.is_active = 1";
    
    if ($sampleOnly) {
        $sql .= " INNER JOIN tps_sample ts ON t.id = ts.tps_id";
    }
    
    $sql .= " LEFT JOIN suara s ON s.id_tps = t.id
              LEFT JOIN calon c ON c.id = s.id_calon
              WHERE kec.id_kabupaten = " . intval($id_kabupaten) . " AND kec.is_active = 1
              GROUP BY kec.id, c.id
              ORDER BY kec.nama, c.nomor_urut";
    
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    
    // Reorganize by kecamatan
    $rekap = [];
    foreach ($data as $row) {
        $kecId = $row['id'];
        if (!isset($rekap[$kecId])) {
            $rekap[$kecId] = [
                'id' => $row['id'],
                'nama' => $row['nama'],
                'total_tps' => $row['total_tps'],
                'tps_masuk' => $row['tps_masuk'],
                'calon' => []
            ];
        }
        if ($row['calon_id']) {
            $rekap[$kecId]['calon'][] = [
                'id' => $row['calon_id'],
                'nomor_urut' => $row['nomor_urut'],
                'nama_calon' => $row['nama_calon'],
                'warna' => $row['warna'],
                'total_suara' => $row['total_suara']
            ];
        }
    }
    
    return array_values($rekap);
}

/**
 * Get rekap per kabupaten untuk pilgub
 */
function getRekapPerKabupaten($id_provinsi, $sampleOnly = false) {
    $conn = getConnection();
    
    $sql = "SELECT 
                kab.id, kab.nama, kab.tipe,
                COUNT(DISTINCT t.id) as total_tps,
                COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN t.id END) as tps_masuk,
                c.id as calon_id, c.nomor_urut, c.nama_calon, c.warna,
                COALESCE(SUM(s.jumlah_suara), 0) as total_suara
            FROM kabupaten kab
            LEFT JOIN kecamatan kec ON kec.id_kabupaten = kab.id
            LEFT JOIN desa d ON d.id_kecamatan = kec.id
            LEFT JOIN tps t ON t.id_desa = d.id AND t.is_active = 1";
    
    if ($sampleOnly) {
        $sql .= " INNER JOIN tps_sample ts ON t.id = ts.tps_id";
    }
    
    $sql .= " LEFT JOIN suara s ON s.id_tps = t.id
              LEFT JOIN calon c ON c.id = s.id_calon
              WHERE kab.id_provinsi = " . intval($id_provinsi) . " AND kab.is_active = 1
              GROUP BY kab.id, c.id
              ORDER BY kab.nama, c.nomor_urut";
    
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    
    // Reorganize by kabupaten
    $rekap = [];
    foreach ($data as $row) {
        $kabId = $row['id'];
        if (!isset($rekap[$kabId])) {
            $rekap[$kabId] = [
                'id' => $row['id'],
                'nama' => $row['nama'],
                'tipe' => $row['tipe'],
                'total_tps' => $row['total_tps'],
                'tps_masuk' => $row['tps_masuk'],
                'calon' => []
            ];
        }
        if ($row['calon_id']) {
            $rekap[$kabId]['calon'][] = [
                'id' => $row['calon_id'],
                'nomor_urut' => $row['nomor_urut'],
                'nama_calon' => $row['nama_calon'],
                'warna' => $row['warna'],
                'total_suara' => $row['total_suara']
            ];
        }
    }
    
    return array_values($rekap);
}

/**
 * Upload image with compression
 */
function uploadImage($file, $destination, $max_width = 800) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
    }
    
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar (max 5MB)'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $destination . $filename;
    
    // Get image info
    $image_info = getimagesize($file['tmp_name']);
    $width = $image_info[0];
    $height = $image_info[1];
    
    // Resize if needed
    if ($width > $max_width) {
        $ratio = $max_width / $width;
        $new_width = $max_width;
        $new_height = $height * $ratio;
        
        // Create image resource
        switch ($file['type']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $source = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($file['tmp_name']);
                break;
        }
        
        $thumb = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($file['type'] == 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // Save
        switch ($file['type']) {
            case 'image/jpeg':
                imagejpeg($thumb, $filepath, 85);
                break;
            case 'image/png':
                imagepng($thumb, $filepath, 8);
                break;
            case 'image/gif':
                imagegif($thumb, $filepath);
                break;
            case 'image/webp':
                imagewebp($thumb, $filepath, 85);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($thumb);
    } else {
        move_uploaded_file($file['tmp_name'], $filepath);
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

/**
 * Delete file
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Generate random color
 */
function generateRandomColor() {
    $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', 
               '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];
    return $colors[array_rand($colors)];
}

/**
 * Get breadcrumb location name
 */
function getLocationBreadcrumb($id_desa = null, $id_kecamatan = null, $id_kabupaten = null, $id_provinsi = null) {
    $conn = getConnection();
    $breadcrumb = [];
    
    if ($id_provinsi) {
        $result = $conn->query("SELECT nama FROM provinsi WHERE id = " . intval($id_provinsi));
        if ($row = $result->fetch_assoc()) {
            $breadcrumb['provinsi'] = $row['nama'];
        }
    }
    
    if ($id_kabupaten) {
        $result = $conn->query("SELECT nama, tipe FROM kabupaten WHERE id = " . intval($id_kabupaten));
        if ($row = $result->fetch_assoc()) {
            $breadcrumb['kabupaten'] = ($row['tipe'] == 'kota' ? 'Kota ' : 'Kab. ') . $row['nama'];
        }
    }
    
    if ($id_kecamatan) {
        $result = $conn->query("SELECT nama FROM kecamatan WHERE id = " . intval($id_kecamatan));
        if ($row = $result->fetch_assoc()) {
            $breadcrumb['kecamatan'] = 'Kec. ' . $row['nama'];
        }
    }
    
    if ($id_desa) {
        $result = $conn->query("SELECT nama, tipe FROM desa WHERE id = " . intval($id_desa));
        if ($row = $result->fetch_assoc()) {
            $breadcrumb['desa'] = ($row['tipe'] == 'kelurahan' ? 'Kel. ' : 'Desa ') . $row['nama'];
        }
    }
    
    return $breadcrumb;
}

/**
 * Normalize string untuk perbandingan (hapus spasi, lowercase)
 */
function normalizeString($str) {
    // Hapus semua spasi dan ubah ke lowercase
    return strtolower(preg_replace('/\s+/', '', $str));
}

/**
 * Cek duplikat nama kabupaten dalam provinsi yang sama
 * @param int $id_provinsi ID provinsi
 * @param string $nama Nama kabupaten
 * @param int $exclude_id ID yang dikecualikan (untuk update)
 * @return array|false Data yang duplikat atau false jika tidak ada
 */
function checkDuplicateKabupaten($id_provinsi, $nama, $exclude_id = 0) {
    $conn = getConnection();
    $normalizedNama = normalizeString($nama);
    
    $sql = "SELECT id, nama FROM kabupaten WHERE id_provinsi = ?";
    if ($exclude_id > 0) {
        $sql .= " AND id != " . intval($exclude_id);
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_provinsi);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (normalizeString($row['nama']) === $normalizedNama) {
            return $row; // Duplikat ditemukan
        }
    }
    return false;
}

/**
 * Cek duplikat nama kecamatan dalam kabupaten yang sama
 * @param int $id_kabupaten ID kabupaten
 * @param string $nama Nama kecamatan
 * @param int $exclude_id ID yang dikecualikan (untuk update)
 * @return array|false Data yang duplikat atau false jika tidak ada
 */
function checkDuplicateKecamatan($id_kabupaten, $nama, $exclude_id = 0) {
    $conn = getConnection();
    $normalizedNama = normalizeString($nama);
    
    $sql = "SELECT id, nama FROM kecamatan WHERE id_kabupaten = ?";
    if ($exclude_id > 0) {
        $sql .= " AND id != " . intval($exclude_id);
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_kabupaten);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (normalizeString($row['nama']) === $normalizedNama) {
            return $row; // Duplikat ditemukan
        }
    }
    return false;
}

/**
 * Cek duplikat nama desa dalam kecamatan yang sama
 * @param int $id_kecamatan ID kecamatan
 * @param string $nama Nama desa
 * @param int $exclude_id ID yang dikecualikan (untuk update)
 * @return array|false Data yang duplikat atau false jika tidak ada
 */
function checkDuplicateDesa($id_kecamatan, $nama, $exclude_id = 0) {
    $conn = getConnection();
    $normalizedNama = normalizeString($nama);
    
    $sql = "SELECT id, nama FROM desa WHERE id_kecamatan = ?";
    if ($exclude_id > 0) {
        $sql .= " AND id != " . intval($exclude_id);
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_kecamatan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (normalizeString($row['nama']) === $normalizedNama) {
            return $row; // Duplikat ditemukan
        }
    }
    return false;
}
