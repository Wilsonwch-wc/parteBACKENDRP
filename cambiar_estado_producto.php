<?php
require_once 'db.php';
$conn = getDB();

if (!$conn) {
    die("Error de conexión con la base de datos");
}

checkPermission('admin');

if (isset($_GET['id']) && isset($_GET['estado'])) {
    $id = $_GET['id'];
    $estado = $_GET['estado'];

    $sql = "UPDATE productos SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $estado, $id);
    
    if ($stmt->execute()) {
        header("Location: administrar.php?success=1");
    } else {
        header("Location: administrar.php?error=1");
    }

    $conn->close();
}
?>