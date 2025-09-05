<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['tipo']) || !isset($data['producto_id']) || !isset($data['timestamp'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$conn = getDB();
$sql = "INSERT INTO cambios_stock (producto_id, tipo, timestamp) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $data['producto_id'], $data['tipo'], $data['timestamp']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al registrar el cambio']);
}

$conn->close(); 