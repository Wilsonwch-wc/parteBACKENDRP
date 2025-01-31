<?php
require_once 'db.php';
checkPermission('admin');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    $producto_id = $_POST['producto_id'];
    $ruta_imagen = $_POST['ruta_imagen'];
    
    // Eliminar el archivo físico
    if (file_exists($ruta_imagen)) {
        unlink($ruta_imagen);
    }
    
    // Eliminar el registro de la base de datos
    $stmt = $conn->prepare("DELETE FROM imagenes_producto WHERE producto_id = ? AND ruta_imagen = ?");
    $stmt->bind_param("is", $producto_id, $ruta_imagen);
    
    $response = ['success' => $stmt->execute()];
    
    $conn->close();
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?> 