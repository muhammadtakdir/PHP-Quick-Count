<?php
/**
 * Login Process
 * Quick Count System
 */

session_start();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Get form data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validate input
if (empty($username) || empty($password)) {
    header('Location: ../login.php?error=empty');
    exit;
}

// Include config
define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

// Get user from database
$conn = getConnection();
$stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, role, foto, is_active FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verify user and password
if (!$user || !password_verify($password, $user['password'])) {
    header('Location: ../login.php?error=invalid');
    exit;
}

// Check if user is active
if (!$user['is_active']) {
    header('Location: ../login.php?error=inactive');
    exit;
}

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_name'] = $user['nama_lengkap'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_foto'] = $user['foto'];

// Update last login
$stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();

// Set remember me cookie
if ($remember) {
    $token = bin2hex(random_bytes(32));
    setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
    
    // Store token in database (you should create a remember_tokens table for this)
    // For simplicity, we'll skip this step
}

// Redirect to dashboard
header('Location: ../index.php');
exit;
