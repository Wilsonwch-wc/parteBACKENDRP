<?php
require_once 'db.php';
require_once 'includes/image_helpers.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar permisos
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Responder en formato JSON
header('Content-Type: application/json');

// Configurar manejo de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos']);
    exit;
}

try {
    // Validar y sanitizar los datos
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $categoria = trim($_POST['categoria']);
    $colores = isset($_POST['colores']) ? trim($_POST['colores']) : '';
    $stock = (int)$_POST['stock'];
    $precio = (float)$_POST['precio'];
    $precio_compra = (float)$_POST['precio_compra'];

    // Validar que los campos requeridos no estén vacíos
    if (empty($codigo) || empty($nombre) || empty($categoria)) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
        exit();
    }

    // Verificar si el código ya existe
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['total'] > 0) {
        echo json_encode(['success' => false, 'error' => "El código '$codigo' ya está en uso"]);
        exit();
    }

    // Iniciar transacción
    $conn->begin_transaction();

    // Insertar producto en la base de datos
    $sql = "INSERT INTO productos (codigo, nombre, categoria, colores, stock, precio, precio_compra, estado, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssddd", 
        $codigo,
        $nombre,
        $categoria,
        $colores,
        $stock,
        $precio,
        $precio_compra
    );

    if ($stmt->execute()) {
        $producto_id = $conn->insert_id;
        
        // Procesar imágenes
        $imagesSaved = false;
        
        if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['tmp_name'][0])) {
            $targetDir = "uploads/";
            
            // Crear el directorio si no existe
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            foreach($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name)) {
                    $fileName = uniqid() . '_' . $_FILES['imagenes']['name'][$key];
                    $targetFilePath = $targetDir . $fileName;
                    
                    if (move_uploaded_file($tmp_name, $targetFilePath)) {
                        // Optimizar la imagen después de subirla
                        optimizarImagen($targetFilePath);
                        
                        $sql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen) VALUES (?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("is", $producto_id, $targetFilePath);
                        $stmt->execute();
                        $imagesSaved = true;
                    }
                }
            }
        }
        
        // Agregar imagen por defecto si no se subieron imágenes
        if (!$imagesSaved) {
            $defaultImagePath = "sinfoto.jpg";
            $sql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $producto_id, $defaultImagePath);
            $stmt->execute();
        }
        
        // Confirmar transacción
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Producto sincronizado correctamente', 
            'product_id' => $producto_id
        ]);
    } else {
        throw new Exception("Error al insertar el producto: " . $stmt->error);
    }
} catch (Exception $e) {
    // Revertir cambios si hay error
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
    
    // Registrar error en log
    error_log("Error procesando producto offline: " . $e->getMessage());
}

$conn->close();
?> 