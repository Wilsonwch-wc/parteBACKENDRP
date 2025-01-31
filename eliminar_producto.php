<?php
require_once 'db.php';
checkPermission('admin');

if (isset($_GET['id'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    // Verificar si hay ventas asociadas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas WHERE producto_id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $ventas = $result->fetch_assoc()['total'];
    
    if ($ventas > 0) {
        header("Location: administrar.php?error=No se puede eliminar un producto con ventas asociadas");
    } else {
        // Eliminar imágenes asociadas
        $stmt = $conn->prepare("SELECT ruta_imagen FROM imagenes_producto WHERE producto_id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (file_exists($row['ruta_imagen'])) {
                unlink($row['ruta_imagen']);
            }
        }
        
        // Eliminar registros de imágenes
        $stmt = $conn->prepare("DELETE FROM imagenes_producto WHERE producto_id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        
        // Eliminar producto
        $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
        
        if ($stmt->execute()) {
            header("Location: administrar.php?success=1");
        } else {
            header("Location: administrar.php?error=1");
        }
    }
    
    $conn->close();
}
?>