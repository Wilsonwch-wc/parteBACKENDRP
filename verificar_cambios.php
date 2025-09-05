<?php
require_once 'db.php';
header('Content-Type: application/json');

// Obtener el timestamp del cliente
$clientTimestamp = isset($_GET['timestamp']) ? (int)$_GET['timestamp'] : 0;

// Obtener el último cambio de la base de datos
$conn = getDB();
$sql = "SELECT MAX(UNIX_TIMESTAMP(fecha_modificacion)) as ultimo_cambio FROM productos";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

$ultimoCambio = (int)$row['ultimo_cambio'] * 1000; // Convertir a milisegundos

if ($ultimoCambio > $clientTimestamp) {
    echo json_encode([
        'hayCambios' => true,
        'timestamp' => $ultimoCambio
    ]);
} else {
    echo json_encode([
        'hayCambios' => false,
        'timestamp' => $clientTimestamp
    ]);
}

$conn->close(); 