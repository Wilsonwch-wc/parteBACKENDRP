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
        // Obtener información actual de la venta y su transaccion_id
        $stmt = $conn->prepare("SELECT v.cantidad, v.precio_unitario, v.transaccion_id, 
                              (SELECT COUNT(*) FROM ventas WHERE transaccion_id = v.transaccion_id) as total_items 
                              FROM ventas v WHERE v.id = ?");
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
            $stmt->execute();
            
            // Si era el último item de la transacción, eliminar la cabecera
            if ($venta['total_items'] <= 1) {
                $stmt = $conn->prepare("DELETE FROM ventas_cabecera WHERE id = ?");
                $stmt->bind_param("i", $venta['transaccion_id']);
                $stmt->execute();
            }
        } else {
            // Si es devolución parcial, actualizar la cantidad y el total
            $nuevoTotal = $cantidadRestante * $venta['precio_unitario'];
            $stmt = $conn->prepare("UPDATE ventas SET cantidad = ?, total = ? WHERE id = ?");
            $stmt->bind_param("idi", $cantidadRestante, $nuevoTotal, $_GET['venta_id']);
            $stmt->execute();
            
            // Actualizar el total en la cabecera
            $stmt = $conn->prepare("UPDATE ventas_cabecera 
                                  SET total = (SELECT SUM(total) FROM ventas WHERE transaccion_id = ?) 
                                  WHERE id = ?");
            $stmt->bind_param("ii", $venta['transaccion_id'], $venta['transaccion_id']);
            $stmt->execute();
        }
        
        // Restaurar el stock del producto
        $stmt = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param("ii", $cantidadDevolver, $_GET['producto_id']);
        $stmt->execute();
        
        $conn->commit();
        
        // Preservar todos los parámetros de la URL anterior
        $parametros = array();
        foreach ($_GET as $key => $value) {
            if ($key != 'venta_id' && $key != 'producto_id' && $key != 'cantidad') {
                $parametros[$key] = $value;
            }
        }
        
        // Añadir mensaje de éxito
        $parametros['success'] = 'Devolución procesada correctamente';
        
        // Reconstruir la URL con los parámetros preservados
        $url = 'historial.php?' . http_build_query($parametros);
        header("Location: " . $url);
    } catch (Exception $e) {
        $conn->rollback();
        
        // Preservar todos los parámetros de la URL anterior
        $parametros = array();
        foreach ($_GET as $key => $value) {
            if ($key != 'venta_id' && $key != 'producto_id' && $key != 'cantidad') {
                $parametros[$key] = $value;
            }
        }
        
        // Añadir mensaje de error
        $parametros['error'] = $e->getMessage();
        
        // Reconstruir la URL con los parámetros preservados
        $url = 'historial.php?' . http_build_query($parametros);
        header("Location: " . $url);
    }
    
    $conn->close();
}
?> 