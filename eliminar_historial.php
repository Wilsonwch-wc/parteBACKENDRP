<?php
require_once 'db.php';
checkPermission('admin');

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

$conn->begin_transaction();

try {
    // Eliminar todos los registros de ventas
    $stmt = $conn->prepare("DELETE FROM ventas");
    $stmt->execute();
    
    // Eliminar todos los registros de ventas_cabecera
    $stmt = $conn->prepare("DELETE FROM ventas_cabecera");
    $stmt->execute();
    
    $conn->commit();
    header("Location: historial.php?success=Historial eliminado correctamente");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: historial.php?error=" . urlencode($e->getMessage()));
}

$conn->close(); 