<?php
/**
 * API - Get Desa
 */

header('Content-Type: application/json');

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

$id_kecamatan = isset($_GET['id_kecamatan']) ? intval($_GET['id_kecamatan']) : 0;

$conn = getConnection();
$stmt = $conn->prepare("SELECT id, nama, tipe FROM desa WHERE id_kecamatan = ? AND is_active = 1 ORDER BY nama");
$stmt->bind_param("i", $id_kecamatan);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'nama' => ($row['tipe'] == 'kelurahan' ? 'Kel. ' : 'Desa ') . $row['nama']
    ];
}

echo json_encode($data);
