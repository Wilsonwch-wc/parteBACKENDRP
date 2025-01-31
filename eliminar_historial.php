<?php
require_once 'db.php';
checkPermission('admin');

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // Obtener todas las ventas para restaurar stock
    $result = $conn->query("SELECT producto_id, cantidad FROM ventas");
    while ($venta = $result->fetch_assoc()) {
        $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param("ii", $venta['cantidad'], $venta['producto_id']);
        $stmt->execute();
    }
    
    // Eliminar todas las ventas
    $conn->query("DELETE FROM ventas");
    
    $conn->commit();
    header("Location: historial.php?success=1");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: historial.php?error=No se pudo eliminar el historial");
}

$conn->close(); 