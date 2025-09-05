<?php
require_once 'db.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar permisos
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Función para generar token CSRF si no existe
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Generar token CSRF
generate_csrf_token();

// Verificar si se proporcionó un ID de venta
if (!isset($_GET['venta_id']) || empty($_GET['venta_id'])) {
    header("Location: historial.php?error=ID de venta no proporcionado");
    exit;
}

$transaccion_id = (int)$_GET['venta_id'];
$conn = getDB();

if (!$conn) {
    header("Location: historial.php?error=Error de conexión con la base de datos");
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Obtener los detalles de la venta para devolver el stock
    $stmt = $conn->prepare("SELECT p.id, v.cantidad, p.nombre 
                           FROM ventas v 
                           JOIN productos p ON v.producto_id = p.id 
                           WHERE v.transaccion_id = ?");
    $stmt->bind_param("i", $transaccion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontraron productos para esta venta");
    }
    
    // Devolver el stock de cada producto
    while ($row = $result->fetch_assoc()) {
        $producto_id = $row['id'];
        $cantidad = $row['cantidad'];
        
        // Actualizar el stock del producto
        $update = $conn->prepare("UPDATE productos SET stock = stock + ?, fecha_modificacion = CURRENT_TIMESTAMP WHERE id = ?");
        $update->bind_param("ii", $cantidad, $producto_id);
        
        if (!$update->execute()) {
            throw new Exception("Error al actualizar el stock del producto: " . $row['nombre']);
        }
    }
    
    // Eliminar los registros de ventas
    $delete_ventas = $conn->prepare("DELETE FROM ventas WHERE transaccion_id = ?");
    $delete_ventas->bind_param("i", $transaccion_id);
    
    if (!$delete_ventas->execute()) {
        throw new Exception("Error al eliminar detalles de la venta");
    }
    
    // Eliminar el registro de la cabecera de venta
    $delete_cabecera = $conn->prepare("DELETE FROM ventas_cabecera WHERE id = ?");
    $delete_cabecera->bind_param("i", $transaccion_id);
    
    if (!$delete_cabecera->execute()) {
        throw new Exception("Error al eliminar la venta");
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir con mensaje de éxito
    header("Location: historial.php?success=Venta deshecha correctamente");
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    // Redirigir con mensaje de error
    header("Location: historial.php?error=" . urlencode($e->getMessage()));
    exit;
}

$conn->close();
?> 