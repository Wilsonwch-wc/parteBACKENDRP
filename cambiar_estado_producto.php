<?php
require_once 'db.php';
checkPermission('admin');

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

if (isset($_GET['id']) && isset($_GET['estado'])) {
    // Sanitizar y validar entradas
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $estado = filter_var($_GET['estado'], FILTER_VALIDATE_INT);
    
    if ($id === false || $estado === false) {
        header("Location: administrar.php?error=Datos inválidos");
        exit();
    }

    $sql = "UPDATE productos SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $estado, $id);
    
    if ($stmt->execute()) {
        header("Location: administrar.php?success=1");
    } else {
        header("Location: administrar.php?error=1");
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: administrar.php?error=Parámetros faltantes");
}
exit();
?>