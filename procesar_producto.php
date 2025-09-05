<?php
require_once 'db.php';
require_once 'includes/image_helpers.php';
require_once 'includes/file_validator.php';
require_once 'includes/rate_limiter.php';
require_once 'helpers/categorias_temporadas.php';

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
            header("Location: subir_foto.php?error=Error de seguridad: token inválido");
            exit;
        }
    }
}

// Verificar permisos
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar rate limiting
$rateLimiter = new RateLimiter();
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'], 'upload')) {
    header("Location: subir_foto.php?error=Demasiadas solicitudes. Intente más tarde.");
    exit();
}

// Verificar el token CSRF
verify_csrf_token();

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

// Validar y sanitizar los datos
$codigo = trim($_POST['codigo']);
$nombre = trim($_POST['nombre']);
$categoria_seleccionada = trim($_POST['categoria'] ?? '');
$nueva_categoria = trim($_POST['nueva_categoria'] ?? '');
$temporada = trim($_POST['temporada'] ?? '');
$colores = trim($_POST['colores']);
$stock = (int)$_POST['stock'];
$precio = (float)$_POST['precio'];
$precio_compra = (float)$_POST['precio_compra'];

// Procesar categoría
$categoria = '';
$categoria_id = null;

if ($categoria_seleccionada === '__NUEVA__' && !empty($nueva_categoria)) {
    // Crear nueva categoría
    $resultado = crearNuevaCategoria($nueva_categoria);
    if ($resultado['success']) {
        $categoria = $nueva_categoria;
        $categoria_id = $resultado['id'];
    } else {
        header("Location: subir_foto.php?error=" . urlencode($resultado['error']));
        exit();
    }
} elseif (!empty($categoria_seleccionada) && $categoria_seleccionada !== '__NUEVA__') {
    // Usar categoría seleccionada
    $categoria = $categoria_seleccionada;
    
    // Obtener el ID de la categoría si existe en la tabla categorias
    $conn_temp = getDB();
    if ($conn_temp) {
        $stmt = $conn_temp->prepare("SELECT id FROM categorias WHERE nombre = ?");
        $stmt->bind_param("s", $categoria);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $categoria_id = $row['id'];
        }
        $stmt->close();
    }
} else {
    header("Location: subir_foto.php?error=Debe seleccionar una categoría o crear una nueva");
    exit();
}

// Validar temporada si se proporciona
if (!empty($temporada) && !esTemporadaValida($temporada)) {
    header("Location: subir_foto.php?error=La temporada seleccionada no es válida");
    exit();
}

// Validar que los campos requeridos no estén vacíos
if (empty($codigo) || empty($nombre) || empty($categoria)) {
    header("Location: subir_foto.php?error=Todos los campos marcados con * son obligatorios");
    exit();
}

// Si colores está vacío, establecer como NULL o cadena vacía
$colores = empty($colores) ? '' : $colores;

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
$sql = "INSERT INTO productos (codigo, nombre, categoria, categoria_id, colores, stock, precio, precio_compra, temporada, estado, fecha_creacion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssissdds", 
    $codigo,
    $nombre,
    $categoria,
    $categoria_id,
    $colores,
    $stock,
    $precio,
    $precio_compra,
    $temporada
);

if ($stmt->execute()) {
    $producto_id = $conn->insert_id;
    
    // Procesar imágenes con validación segura
    if (isset($_FILES['imagenes'])) {
        $fileValidator = new FileValidator();
        $uploadErrors = [];
        $uploadedImages = [];
        
        try {
            $targetDir = $fileValidator->ensureUploadDirectory();
        } catch (Exception $e) {
            error_log("Error creando directorio: " . $e->getMessage());
            header("Location: subir_foto.php?error=Error en el sistema de archivos");
            exit();
        }
        
        // Validar cada archivo
        foreach($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                // Crear array de archivo individual para validación
                $file = [
                    'name' => $_FILES['imagenes']['name'][$key],
                    'type' => $_FILES['imagenes']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['imagenes']['error'][$key],
                    'size' => $_FILES['imagenes']['size'][$key]
                ];
                
                // Validar archivo
                $validation = $fileValidator->validateFile($file);
                
                if (!$validation['valid']) {
                    $uploadErrors[] = "Archivo {$file['name']}: " . implode(', ', $validation['errors']);
                    continue;
                }
                
                // Generar nombre seguro
                $secureFileName = $fileValidator->generateSecureFileName($file['name']);
                $targetFilePath = $targetDir . $secureFileName;
                
                // Mover archivo
                if (move_uploaded_file($tmp_name, $targetFilePath)) {
                    // Optimizar la imagen después de subirla
                    $resultado = optimizarImagen($targetFilePath);
                    
                    // Usar la ruta de WebP si la conversión fue exitosa
                    $rutaImagen = $resultado && isset($resultado['convertido']) && $resultado['convertido'] 
                        ? $resultado['rutaWebp'] 
                        : $targetFilePath;
                    
                    $uploadedImages[] = $rutaImagen;
                } else {
                    $uploadErrors[] = "Error al mover archivo: {$file['name']}";
                }
            }
        }
        
        // Si hay errores, eliminar archivos subidos y mostrar errores
        if (!empty($uploadErrors)) {
            // Limpiar archivos subidos
            foreach ($uploadedImages as $imagePath) {
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $errorMsg = implode('; ', $uploadErrors);
            header("Location: subir_foto.php?error=" . urlencode($errorMsg));
            exit();
        }
        
        // Insertar imágenes válidas en la base de datos
        foreach ($uploadedImages as $rutaImagen) {
            $sql = "INSERT INTO imagenes_producto (producto_id, ruta_imagen) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $producto_id, $rutaImagen);
            $stmt->execute();
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
?>
