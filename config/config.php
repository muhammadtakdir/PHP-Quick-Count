<?php
/**
 * Main Configuration File
 * Quick Count System
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define base path
define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Application settings
define('APP_NAME', 'Quick Count');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/bone/');

// Directory paths
define('UPLOAD_PATH', BASEPATH . 'uploads/');
define('FOTO_CALON_PATH', UPLOAD_PATH . 'calon/');
define('FOTO_C1_PATH', UPLOAD_PATH . 'c1/');
define('FOTO_USER_PATH', UPLOAD_PATH . 'users/');

// Create upload directories if not exist
$directories = [UPLOAD_PATH, FOTO_CALON_PATH, FOTO_C1_PATH, FOTO_USER_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Include database config
require_once BASEPATH . 'config/database.php';

// Include helper functions
require_once BASEPATH . 'includes/functions.php';

// Error reporting
// PENTING: Set ke 0 untuk production!
// error_reporting(0);
// ini_set('display_errors', 0);
// Untuk development:
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Makassar');

/**
 * Get application settings from database
 */
function getSettings() {
    static $settings = null;
    
    if ($settings === null) {
        $conn = getConnection();
        $result = $conn->query("SELECT * FROM settings WHERE is_active = 1 LIMIT 1");
        $settings = $result->fetch_assoc();
    }
    
    return $settings;
}

/**
 * Get settings per kabupaten
 * @param int $idKabupaten ID Kabupaten
 * @return array|null Settings untuk kabupaten tersebut
 */
function getSettingsKabupaten($idKabupaten) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT sk.*, k.nama as nama_kabupaten, p.nama as nama_provinsi
        FROM settings_kabupaten sk
        JOIN kabupaten k ON sk.id_kabupaten = k.id
        JOIN provinsi p ON k.id_provinsi = p.id
        WHERE sk.id_kabupaten = ? AND sk.is_active = 1
    ");
    $stmt->bind_param("i", $idKabupaten);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get all kabupaten settings
 * @return array List of all kabupaten with their settings
 */
function getAllKabupatenSettings() {
    $conn = getConnection();
    $query = "
        SELECT k.id, k.nama as nama_kabupaten, p.nama as nama_provinsi,
               sk.nama_pemilihan, sk.jenis_pemilihan, sk.jenis_hitung, 
               sk.jumlah_tps_sample, sk.public_detail_level, sk.tahun_pemilihan, sk.is_active as setting_active,
               (SELECT COUNT(*) FROM kecamatan WHERE id_kabupaten = k.id) as jumlah_kecamatan,
               (SELECT COUNT(*) FROM desa d JOIN kecamatan kc ON d.id_kecamatan = kc.id WHERE kc.id_kabupaten = k.id) as jumlah_desa,
               (SELECT COUNT(*) FROM tps t JOIN desa d ON t.id_desa = d.id JOIN kecamatan kc ON d.id_kecamatan = kc.id WHERE kc.id_kabupaten = k.id) as jumlah_tps
        FROM kabupaten k
        JOIN provinsi p ON k.id_provinsi = p.id
        LEFT JOIN settings_kabupaten sk ON k.id = sk.id_kabupaten
        ORDER BY p.nama, k.nama
    ";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . 'login.php');
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if (!hasRole(['admin'])) {
        header('Location: ' . APP_URL . 'index.php?error=unauthorized');
        exit;
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF Token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format number Indonesian style
 */
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

/**
 * Calculate percentage
 */
function calculatePercentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100, 2);
}

/**
 * Generate nama pemilihan otomatis berdasarkan jenis pemilihan dan wilayah
 * @param string $jenisPemilihan Jenis pemilihan (pilpres, pilgub, pilbup, pilwalkot, pilkades)
 * @param int|null $idProvinsi ID Provinsi
 * @param int|null $idKabupaten ID Kabupaten
 * @param int|null $idDesa ID Desa (untuk pilkades)
 * @param int|null $tahun Tahun pemilihan
 * @return string Nama pemilihan lengkap
 */
function generateNamaPemilihan($jenisPemilihan, $idProvinsi = null, $idKabupaten = null, $idDesa = null, $tahun = null) {
    $conn = getConnection();
    $tahun = $tahun ?? date('Y');
    
    // Label jenis pemilihan
    $jenisPemilihanLabel = [
        'pilpres' => 'PEMILIHAN PRESIDEN',
        'pilgub' => 'PILKADA PROVINSI',
        'pilbup' => 'PILKADA KABUPATEN',
        'pilwalkot' => 'PILKADA KOTA',
        'pilkades' => 'PILKADES'
    ];
    
    $label = $jenisPemilihanLabel[$jenisPemilihan] ?? 'PEMILIHAN';
    $namaWilayah = '';
    
    switch ($jenisPemilihan) {
        case 'pilpres':
            $namaWilayah = 'INDONESIA';
            break;
            
        case 'pilgub':
            if ($idProvinsi) {
                $stmt = $conn->prepare("SELECT nama FROM provinsi WHERE id = ?");
                $stmt->bind_param("i", $idProvinsi);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $namaWilayah = strtoupper($result['nama'] ?? '');
            }
            break;
            
        case 'pilbup':
        case 'pilwalkot':
            if ($idKabupaten) {
                $stmt = $conn->prepare("SELECT nama FROM kabupaten WHERE id = ?");
                $stmt->bind_param("i", $idKabupaten);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $namaWilayah = strtoupper($result['nama'] ?? '');
            }
            break;
            
        case 'pilkades':
            if ($idDesa) {
                $stmt = $conn->prepare("
                    SELECT d.nama as nama_desa, kc.nama as nama_kecamatan, k.nama as nama_kabupaten
                    FROM desa d
                    JOIN kecamatan kc ON d.id_kecamatan = kc.id
                    JOIN kabupaten k ON kc.id_kabupaten = k.id
                    WHERE d.id = ?
                ");
                $stmt->bind_param("i", $idDesa);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if ($result) {
                    $namaWilayah = strtoupper($result['nama_desa']) . ' KEC. ' . strtoupper($result['nama_kecamatan']);
                }
            }
            break;
    }
    
    return trim("$label $namaWilayah $tahun");
}

/**
 * Get nama pemilihan from settings (otomatis generate jika kosong)
 * @param array|null $settings Settings array (optional, jika tidak diberikan akan di-fetch)
 * @return string Nama pemilihan
 */
function getNamaPemilihan($settings = null) {
    if ($settings === null) {
        $settings = getSettings();
    }
    
    // Generate otomatis berdasarkan jenis pemilihan dan wilayah aktif
    return generateNamaPemilihan(
        $settings['jenis_pemilihan'] ?? 'pilbup',
        $settings['id_provinsi_aktif'] ?? null,
        $settings['id_kabupaten_aktif'] ?? null,
        $settings['id_desa_aktif'] ?? null,
        $settings['tahun_pemilihan'] ?? date('Y')
    );
}

/**
 * Get nama pemilihan for specific kabupaten
 * @param int $idKabupaten ID Kabupaten
 * @param array|null $settingsKab Settings kabupaten (optional)
 * @return string Nama pemilihan
 */
function getNamaPemilihanKabupaten($idKabupaten, $settingsKab = null) {
    if ($settingsKab === null) {
        $settingsKab = getSettingsKabupaten($idKabupaten);
    }
    
    $jenisPemilihan = $settingsKab['jenis_pemilihan'] ?? 'pilbup';
    $tahun = $settingsKab['tahun_pemilihan'] ?? date('Y');
    
    // Untuk pilkades, cari desa aktif (jika ada)
    $idDesa = null;
    if ($jenisPemilihan === 'pilkades') {
        // Ambil dari settings global untuk desa aktif
        $globalSettings = getSettings();
        $idDesa = $globalSettings['id_desa_aktif'] ?? null;
    }
    
    // Ambil id provinsi dari kabupaten
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id_provinsi FROM kabupaten WHERE id = ?");
    $stmt->bind_param("i", $idKabupaten);
    $stmt->execute();
    $kab = $stmt->get_result()->fetch_assoc();
    $idProvinsi = $kab['id_provinsi'] ?? null;
    
    return generateNamaPemilihan($jenisPemilihan, $idProvinsi, $idKabupaten, $idDesa, $tahun);
}
