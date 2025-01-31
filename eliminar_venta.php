<?php
require_once 'db.php';
checkPermission();

if (isset($_GET['id'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Obtener todas las ventas del mismo pedido (mismo timestamp)
        $stmt = $conn->prepare("
            SELECT v2.id, v2.producto_id, v2.cantidad 
            FROM ventas v1 
            JOIN ventas v2 ON DATE_FORMAT(v1.fecha_venta, '%Y-%m-%d %H:%i:%s') = 
                            DATE_FORMAT(v2.fecha_venta, '%Y-%m-%d %H:%i:%s')
            WHERE v1.id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($venta = $result->fetch_assoc()) {
            // Restaurar el stock de cada producto
            $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("ii", $venta['cantidad'], $venta['producto_id']);
            $stmt->execute();
            
            // Eliminar cada venta
            $stmt = $conn->prepare("DELETE FROM ventas WHERE id = ?");
            $stmt->bind_param("i", $venta['id']);
            $stmt->execute();
        }
        
        $conn->commit();
        header("Location: historial.php?success=Venta deshecha correctamente");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: historial.php?error=No se pudo deshacer la venta");
    }
    
    $conn->close();
} 