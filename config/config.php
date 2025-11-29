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

// Error reporting (disable in production)
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
