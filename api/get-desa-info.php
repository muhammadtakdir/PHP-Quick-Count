<?php
/**
 * API: Get Desa Info
 * Returns desa information including kecamatan
 */

header('Content-Type: application/json');

define('BASEPATH', true);
require_once '../config/database.php';

$id_desa = isset($_GET['id_desa']) ? intval($_GET['id_desa']) : 0;

if (!$id_desa) {
    echo json_encode(null);
    exit;
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, id_kecamatan, kode, nama as nama_desa FROM desa WHERE id = ?");
$stmt->bind_param("i", $id_desa);
$stmt->execute();
$result = $stmt->get_result();
$desa = $result->fetch_assoc();

echo json_encode($desa);
