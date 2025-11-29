<?php
/**
 * API - Get Suara by TPS
 */

header('Content-Type: application/json');

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'config/database.php';

$id_tps = isset($_GET['id_tps']) ? intval($_GET['id_tps']) : 0;

$conn = getConnection();
$stmt = $conn->prepare("SELECT s.*, c.nama_calon 
                        FROM suara s 
                        JOIN calon c ON s.id_calon = c.id 
                        WHERE s.id_tps = ?");
$stmt->bind_param("i", $id_tps);
$stmt->execute();
$result = $stmt->get_result();

$data = [
    'suara' => $result->fetch_all(MYSQLI_ASSOC),
    'id_tps' => $id_tps
];

echo json_encode($data);
