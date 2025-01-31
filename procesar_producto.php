<?php
require_once 'db.php';
checkPermission('admin');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = getDB();
    if (!$conn) {
        die("Error de conexión con la base de datos");
    }
    
    // Obtener datos del formulario
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $categoria = $_POST['categoria'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $colores = $_POST['colores'];
    $precio_compra = $_POST['precio_compra']; // Obtener el nuevo dato
    
    // Verificar si el código ya existe
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['total'] > 0) {
        $params = http_build_query([
            'error' => "El código '$codigo' ya está en uso. Por favor, elija otro código.",
            'codigo' => $codigo,
            'nombre' => $nombre,
            'categoria' => $categoria,
            'precio_compra' => $precio_compra,
            'precio' => $precio,
            'stock' => $stock,
            'colores' => $colores,
             // Retornar también este dato al formulario
        ]);
        header("Location: subir_foto.php?" . $params);
        exit();
    }
    
    // Insertar producto en la base de datos
    $sql = "INSERT INTO productos (codigo, nombre, categoria, precio_compra, precio, stock, colores) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdisd", $codigo, $nombre, $categoria, $precio_compra, $precio, $stock, $colores);
    
    if ($stmt->execute()) {
        $producto_id = $conn->insert_id;
        
        // Procesar imágenes
        if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['tmp_name'][0])) {
            $targetDir = "uploads/";
            
            // Crear el directorio si no existe
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            foreach($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
                $fileName = $_FILES['imagenes']['name'][$key];
                // Sanitizar el nombre del archivo
                $fileName = preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                $targetFilePath = $targetDir . time() . '_' . $fileName;
                
                if (move_uploaded_file($tmp_name, $targetFilePath)) {
                    // Guardar referencia de la imagen en la base de datos
                    $sql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $producto_id, $targetFilePath);
                    $stmt->execute();
                }
            }
        } else {
            // Agregar imagen por defecto si no se subieron imágenes
            $defaultImagePath = "./sinfoto.jpg";
            $sql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $producto_id, $defaultImagePath);
            $stmt->execute();
        }
        
        header("Location: subir_foto.php?success=Producto subido correctamente");
    } else {
        header("Location: subir_foto.php?error=Error al subir el producto");
    }
    
    $conn->close();
}
?>
