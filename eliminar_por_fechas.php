<?php
require_once 'db.php';
checkPermission('admin');

if (isset($_GET['desde']) && isset($_GET['hasta'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    $conn->begin_transaction();
    
    try {
        // Convertir las fechas al formato correcto
        $desde = date('Y-m-d 00:00:00', strtotime($_GET['desde']));
        $hasta = date('Y-m-d 23:59:59', strtotime($_GET['hasta']));
        
        // Obtener los IDs de las transacciones en el rango de fechas
        $stmt = $conn->prepare("SELECT id FROM ventas_cabecera WHERE fecha_venta BETWEEN ? AND ?");
        $stmt->bind_param("ss", $desde, $hasta);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $contadorEliminados = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Eliminar las ventas
            $stmt4 = $conn->prepare("DELETE FROM ventas WHERE transaccion_id = ?");
            $stmt4->bind_param("i", $row['id']);
            $stmt4->execute();
            
            // Eliminar la cabecera
            $stmt5 = $conn->prepare("DELETE FROM ventas_cabecera WHERE id = ?");
            $stmt5->bind_param("i", $row['id']);
            $stmt5->execute();
            
            $contadorEliminados++;
        }
        
        $conn->commit();
        $mensaje = $contadorEliminados > 0 ? 
                  "Se eliminaron $contadorEliminados transacciones correctamente" : 
                  "No se encontraron ventas en el rango de fechas seleccionado";
        header("Location: historial.php?success=" . urlencode($mensaje));
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: historial.php?error=" . urlencode($e->getMessage()));
    }
    
    $conn->close();
} else {
    header("Location: historial.php?error=Debe seleccionar ambas fechas");
}
?>
