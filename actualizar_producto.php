<?php
require_once 'db.php';
require_once 'includes/image_helpers.php';
require_once 'cache.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Función para verificar token CSRF si no existe
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token() {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            // Si el token CSRF no es válido, registrar el intento y redirigir
            error_log("Intento de CSRF detectado desde IP: " . $_SERVER['REMOTE_ADDR']);
            header("Location: administrar.php?error=Error de seguridad: token inválido");
            exit;
        }
    }
}

// Verificar permisos
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=No tienes permisos para acceder a esta sección");
    exit;
}

// Verificar el token CSRF
verify_csrf_token();

header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
    $conn = getDB();
    if (!$conn) {
        $response['error'] = "Error de conexión con la base de datos";
        echo json_encode($response);
        exit();
    }

    $id = $_POST['id'];
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $categoria = $_POST['categoria'];
    $colores = $_POST['colores'];
    $stock = $_POST['stock'];
    $precio = $_POST['precio'];
    $precio_compra = $_POST['precio_compra'];
    $estado = $_POST['estado'];

    $sql = "UPDATE productos SET 
            codigo = ?, 
            nombre = ?, 
            categoria = ?, 
            colores = ?, 
            stock = ?, 
            precio = ?,
            precio_compra = ?,
            estado = ?
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiidii", 
        $codigo, 
        $nombre, 
        $categoria, 
        $colores, 
        $stock, 
        $precio,
        $precio_compra,
        $estado,
        $id
    );
    
    if ($stmt->execute()) {
           // Procesar nuevas imágenes si existen
           if (!empty($_FILES['nuevas_imagenes']['name'][0])) {
            for ($i = 0; $i < count($_FILES['nuevas_imagenes']['name']); $i++) {
                if ($_FILES['nuevas_imagenes']['error'][$i] == 0 && 
                    $_FILES['nuevas_imagenes']['size'][$i] > 0 &&
                    substr($_FILES['nuevas_imagenes']['type'][$i], 0, 6) == 'image/') {
                    
                    $imageType = $_FILES['nuevas_imagenes']['type'][$i];
                    $fileExtension = pathinfo($_FILES['nuevas_imagenes']['name'][$i], PATHINFO_EXTENSION);
                    
                    $uploadDir = 'uploads/productos/';
                    if (!file_exists($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true)) {
                            throw new Exception("No se pudo crear el directorio para las imágenes");
                        }
                    }
                    
                    $fileName = 'producto_' . $id . '_' . uniqid() . '.' . $fileExtension;
                    $targetFilePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['nuevas_imagenes']['tmp_name'][$i], $targetFilePath)) {
                        // Registra mensajes antes de la optimización
                        error_log("Preparando para optimizar imagen: " . $targetFilePath);
                        
                        try {
                            // Llamar a la función optimizarImagen y guardar la nueva ruta (que será WebP)
                            $resultado = optimizarImagen($targetFilePath);
                            
                            // Verificar resultado y extraer la ruta optimizada
                            $rutaOptimizada = $resultado && is_array($resultado) && isset($resultado['rutaWebp']) 
                                ? $resultado['rutaWebp'] 
                                : $targetFilePath;
                                
                            error_log("Imagen optimizada correctamente. Ruta original: $targetFilePath, Ruta WebP: $rutaOptimizada");
                            
                            // Usar la ruta optimizada (WebP) para guardar en la base de datos
                            $insertImageSql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen, fecha_creacion) 
                                            VALUES (?, ?, NOW())";
                            $stmtImage = $conn->prepare($insertImageSql);
                            if (!$stmtImage) {
                                throw new Exception("Error al preparar inserción de imagen: " . $conn->error);
                            }
                            
                            $stmtImage->bind_param("is", $id, $rutaOptimizada);
                            if (!$stmtImage->execute()) {
                                throw new Exception("Error al guardar imagen en BD: " . $stmtImage->error);
                            }
                            
                            $stmtImage->close();
                        } catch (Exception $imgEx) {
                            error_log("Error al optimizar imagen: " . $imgEx->getMessage());
                            throw new Exception("Error al procesar la imagen " . ($i+1) . ": " . $imgEx->getMessage());
                        }
                    } else {
                        throw new Exception("Error al subir la imagen " . ($i+1));
                    }
                }
            }
        }
        
        // Consultar los datos actualizados del producto
        $query = "SELECT id, codigo, nombre, categoria, colores, stock, precio, precio_compra, estado,
                 (SELECT GROUP_CONCAT(i.ruta_imagen ORDER BY i.id) 
                 FROM imagenes_producto i 
                 WHERE i.producto_id = ?) as imagenes
                 FROM productos WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $producto = $result->fetch_assoc();
        
        $response['success'] = true;
        $response['message'] = "Producto actualizado correctamente";
        $response['producto'] = $producto;
    } else {
        $response['error'] = "Error al actualizar el producto: " . $conn->error;
    }
}

echo json_encode($response);
exit();
?>