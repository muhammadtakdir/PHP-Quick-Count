<?php
/**
 * Helper Functions
 * Quick Count System
 */

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
    
    // Join tables for filtering
    if (!empty($filter['id_desa']) || !empty($filter['id_kecamatan']) || 
        !empty($filter['id_kabupaten']) || !empty($filter['id_provinsi'])) {
        $sql .= " LEFT JOIN tps t ON s.id_tps = t.id
                  LEFT JOIN desa d ON t.id_desa = d.id
                  LEFT JOIN kecamatan kec ON d.id_kecamatan = kec.id
                  LEFT JOIN kabupaten kab ON kec.id_kabupaten = kab.id";
    }
    
    $sql .= " WHERE c.is_active = 1";
    
    if (!empty($filter['jenis_pemilihan'])) {
        $sql .= " AND c.jenis_pemilihan = '" . $conn->real_escape_string($filter['jenis_pemilihan']) . "'";
    }
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
    
    $sql .= " GROUP BY c.id ORDER BY c.nomor_urut ASC";
    
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get statistik TPS
 */
function getStatistikTPS($filter = []) {
    $conn = getConnection();
    
    $sql = "SELECT 
                COUNT(DISTINCT t.id) as total_tps,
                COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN t.id END) as tps_masuk,
                COALESCE(SUM(t.dpt), 0) as total_dpt
            FROM tps t
            LEFT JOIN desa d ON t.id_desa = d.id
            LEFT JOIN kecamatan kec ON d.id_kecamatan = kec.id
            LEFT JOIN kabupaten kab ON kec.id_kabupaten = kab.id
            LEFT JOIN suara s ON t.id = s.id_tps
            WHERE t.is_active = 1";
    
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
