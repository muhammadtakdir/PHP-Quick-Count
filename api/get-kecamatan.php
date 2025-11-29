<?php
/**
 * API - Get Kecamatan
 */

header('Content-Type: application/json');

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

$id_kabupaten = isset($_GET['id_kabupaten']) ? intval($_GET['id_kabupaten']) : 0;

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, nama FROM kecamatan WHERE id_kabupaten = ? AND is_active = 1 ORDER BY nama");
$stmt->bind_param("i", $id_kabupaten);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'nama' => $row['nama']
    ];
}

echo json_encode($data);
