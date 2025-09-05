<?php
require_once 'db.php';
require_once 'includes/image_helpers.php';
checkPermission();

$id = $_GET['id'] ?? '';
$redirect = $_GET['redirect'] ?? '';
$q = $_GET['q'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$confirmar_ventas = isset($_GET['confirmar_ventas']) ? ($_GET['confirmar_ventas'] === 'true') : false;

$conn = getDB();

// Verificar si el producto tiene ventas
$sql = "SELECT COUNT(*) as total FROM ventas WHERE producto_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$tiene_ventas = $row['total'] > 0;

if ($tiene_ventas && !$confirmar_ventas) {
    // El producto tiene ventas, pero no se ha confirmado la eliminación de ventas
    header("Location: administrar.php?error=tiene_ventas&redirect=" . $redirect . "&q=" . urlencode($q) . "&categoria=" . urlencode($categoria));
    exit;
}

// Iniciar transacción para asegurar que todas las operaciones se completen o fallen juntas
$conn->begin_transaction();

try {
    // Si hay ventas y se confirmó la eliminación, eliminar primero las ventas asociadas
    if ($tiene_ventas && $confirmar_ventas) {
        $sql = "DELETE FROM ventas WHERE producto_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    // Eliminar imágenes físicas
    $sql = "SELECT ruta_imagen FROM imagenes_producto WHERE producto_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rutaImagen = $row['ruta_imagen'];
        
        // Eliminar la imagen si existe
        if (file_exists($rutaImagen)) {
            unlink($rutaImagen);
        }
        
        // Si es un archivo WebP, buscar versiones originales
        $extension = pathinfo($rutaImagen, PATHINFO_EXTENSION);
        if (strtolower($extension) === 'webp') {
            // Intentar eliminar posibles versiones originales (jpg, png, jpeg)
            $rutaBase = pathinfo($rutaImagen, PATHINFO_DIRNAME) . '/' . pathinfo($rutaImagen, PATHINFO_FILENAME);
            $extensionesPosibles = ['.jpg', '.jpeg', '.png'];
            
            foreach ($extensionesPosibles as $ext) {
                $rutaPosible = $rutaBase . $ext;
                if (file_exists($rutaPosible)) {
                    unlink($rutaPosible);
                }
            }
        } else {
            // Si no es WebP, buscar la versión WebP
            $rutaWebP = pathinfo($rutaImagen, PATHINFO_DIRNAME) . '/' . pathinfo($rutaImagen, PATHINFO_FILENAME) . '.webp';
            if (file_exists($rutaWebP)) {
                unlink($rutaWebP);
            }
        }
    }

    // Eliminar registros de imágenes
    $sql = "DELETE FROM imagenes_producto WHERE producto_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Eliminar el producto
    $sql = "DELETE FROM productos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Confirmar todas las operaciones
    $conn->commit();
    
    header("Location: administrar.php?success=eliminado&redirect=" . $redirect . "&q=" . urlencode($q) . "&categoria=" . urlencode($categoria));
} catch (Exception $e) {
    // Revertir los cambios si algo falla
    $conn->rollback();
    header("Location: administrar.php?error=error_eliminar&redirect=" . $redirect . "&q=" . urlencode($q) . "&categoria=" . urlencode($categoria));
}

$conn->close();
?>