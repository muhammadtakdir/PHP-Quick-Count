<?php
/**
 * API - Get Kecamatan Detail
 */

header('Content-Type: application/json');

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM kecamatan WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_assoc());
