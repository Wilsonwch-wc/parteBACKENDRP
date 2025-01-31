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
        // Restaurar el stock del producto
        $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param("ii", $_GET['cantidad'], $_GET['producto_id']);
        $stmt->execute();
        
        // Eliminar la venta específica
        $stmt = $conn->prepare("DELETE FROM ventas WHERE id = ?");
        $stmt->bind_param("i", $_GET['venta_id']);
        $stmt->execute();
        
        $conn->commit();
        header("Location: historial.php?success=1");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: historial.php?error=No se pudo eliminar el producto del pedido");
    }
    
    $conn->close();
}
?>