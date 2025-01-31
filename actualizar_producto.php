<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'db.php';
    $conn = getDB();
    
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }

    $id = $_POST['id'];
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $categoria = $_POST['categoria'];
    $stock = $_POST['stock'];
    $precio = $_POST['precio'];
    $precio_compra = $_POST['precio_compra']; // Obtener el nuevo dato
    $colores = $_POST['colores'];
    $estado = $_POST['estado']; // Obtener el nuevo dato

    $sql = "UPDATE productos SET 
            codigo = ?, 
            nombre = ?, 
            categoria = ?, 
            stock = ?, 
            precio = ?, 
            precio_compra = ?, 
            colores = ?, 
            estado = ? 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssddisii", $codigo, $nombre, $categoria, $stock, $precio, $precio_compra, $colores, $estado, $id);
    
    if ($stmt->execute()) {
        // Procesar nuevas imágenes si se han subido
        if (isset($_FILES['nuevas_imagenes']) && !empty($_FILES['nuevas_imagenes']['name'][0])) {
            $targetDir = "uploads/";
            
            foreach($_FILES['nuevas_imagenes']['tmp_name'] as $key => $tmp_name) {
                $fileName = $_FILES['nuevas_imagenes']['name'][$key];
                $fileName = preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                $targetFilePath = $targetDir . time() . '_' . $fileName;
                
                if (move_uploaded_file($tmp_name, $targetFilePath)) {
                    $sql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $id, $targetFilePath);
                    $stmt->execute();
                }
            }
        }
        
        header("Location: administrar.php?success=1");
    } else {
        header("Location: administrar.php?error=1");
    }

    $conn->close();
}
?>