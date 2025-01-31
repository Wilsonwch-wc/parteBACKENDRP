<?php
require_once 'db.php';
checkPermission();

if (isset($_GET['venta_id']) && isset($_GET['producto_id']) && isset($_GET['cantidad'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Obtener información actual de la venta
        $stmt = $conn->prepare("SELECT cantidad, precio_unitario FROM ventas WHERE id = ?");
        $stmt->bind_param("i", $_GET['venta_id']);
        $stmt->execute();
        $venta = $stmt->get_result()->fetch_assoc();
        
        if (!$venta) {
            throw new Exception("Venta no encontrada");
        }
        
        $cantidadDevolver = $_GET['cantidad'];
        $cantidadRestante = $venta['cantidad'] - $cantidadDevolver;
        
        if ($cantidadRestante < 0) {
            throw new Exception("Cantidad a devolver excede la cantidad vendida");
        }
        
        if ($cantidadRestante == 0) {
            // Si se devuelven todas las unidades, eliminar la venta
            $stmt = $conn->prepare("DELETE FROM ventas WHERE id = ?");
            $stmt->bind_param("i", $_GET['venta_id']);
        } else {
            // Si es devolución parcial, actualizar la cantidad y el total
            $nuevoTotal = $cantidadRestante * $venta['precio_unitario'];
            $stmt = $conn->prepare("UPDATE ventas SET cantidad = ?, total = ? WHERE id = ?");
            $stmt->bind_param("idi", $cantidadRestante, $nuevoTotal, $_GET['venta_id']);
        }
        $stmt->execute();
        
        // Restaurar el stock del producto
        $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param("ii", $cantidadDevolver, $_GET['producto_id']);
        $stmt->execute();
        
        $conn->commit();
        header("Location: historial.php?success=Devolución procesada correctamente");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: historial.php?error=" . urlencode($e->getMessage()));
    }
    
    $conn->close();
}
?> 