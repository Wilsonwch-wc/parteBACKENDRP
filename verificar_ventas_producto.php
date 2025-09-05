<?php
require_once 'db.php';
checkPermission();
header('Content-Type: application/json');

$id = $_GET['id'] ?? '';
$response = ['tiene_ventas' => false];

if (!empty($id)) {
    $conn = getDB();
    
    // Verificar si el producto tiene ventas
    $sql = "SELECT COUNT(*) as total FROM ventas WHERE producto_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $response['tiene_ventas'] = ($row['total'] > 0);
    $response['total_ventas'] = $row['total'];
    
    $conn->close();
}

echo json_encode($response);
?> 