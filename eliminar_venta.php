<?php
require_once 'db.php';
checkPermission();

if (isset($_GET['id'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    $conn->begin_transaction();
    
    try {
        // Obtener todas las ventas de la transacción
        $stmt = $conn->prepare("SELECT producto_id, cantidad FROM ventas WHERE transaccion_id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Restaurar stock de todos los productos
        while ($venta = $result->fetch_assoc()) {
            $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("ii", $venta['cantidad'], $venta['producto_id']);
            $stmt->execute();
        }
        
        // Eliminar todas las ventas de la transacción
        $stmt = $conn->prepare("DELETE FROM ventas WHERE transaccion_id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        
        // Eliminar la cabecera de la venta
        $stmt = $conn->prepare("DELETE FROM ventas_cabecera WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        
        $conn->commit();
        header("Location: historial.php?success=Venta eliminada correctamente");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: historial.php?error=" . urlencode($e->getMessage()));
    }
    
    $conn->close();
} 