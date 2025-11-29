<?php
/**
 * API - Get Kabupaten
 */

header('Content-Type: application/json');

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

$id_provinsi = isset($_GET['id_provinsi']) ? intval($_GET['id_provinsi']) : 0;

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, nama, tipe FROM kabupaten WHERE id_provinsi = ? AND is_active = 1 ORDER BY nama");
$stmt->bind_param("i", $id_provinsi);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'nama' => ($row['tipe'] == 'kota' ? 'Kota ' : 'Kab. ') . $row['nama']
    ];
}

echo json_encode($data);
