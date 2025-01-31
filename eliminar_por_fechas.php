<?php
require_once 'db.php';
checkPermission('admin');

if (isset($_GET['desde']) && isset($_GET['hasta'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener todas las ventas dentro del rango de fechas
        $stmt = $conn->prepare("
            SELECT id, producto_id, cantidad 
            FROM ventas 
            WHERE DATE(fecha_venta) BETWEEN ? AND ?");
        $stmt->bind_param("ss", $_GET['desde'], $_GET['hasta']);
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
        header("Location: historial.php?success=Ventas eliminadas correctamente");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: historial.php?error=No se pudieron eliminar las ventas");
    }

    $conn->close();
} else {
    header("Location: historial.php?error=Fechas no válidas");
}
?>
