<?php
require_once 'db.php';
checkPermission('admin');

if (isset($_GET['id'])) {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    $id = intval($_GET['id']);
    
    // Marcar el producto como inactivo
    $sql = "UPDATE productos SET activo = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: administrar.php?success=1");
    } else {
        header("Location: administrar.php?error=1");
    }
    
    $stmt->close();
    $conn->close();
} else {
    header("Location: administrar.php");
}
?>