<?php
/**
 * API - Get TPS
 */

header('Content-Type: application/json');

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

$id_desa = isset($_GET['id_desa']) ? intval($_GET['id_desa']) : 0;

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, nomor_tps, dpt FROM tps WHERE id_desa = ? AND is_active = 1 ORDER BY nomor_tps");
$stmt->bind_param("i", $id_desa);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'nama' => 'TPS ' . $row['nomor_tps'],
        'dpt' => $row['dpt']
    ];
}

echo json_encode($data);
