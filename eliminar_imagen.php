<?php
require_once 'db.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar permisos de admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?error=No tienes permisos para acceder a esta sección");
    exit;
}

// Función para verificar token CSRF si no existe
if (!function_exists('verify_csrf_token_api')) {
    function verify_csrf_token_api() {
        $headers = getallheaders();
        $token = isset($headers['X-CSRF-TOKEN']) ? $headers['X-CSRF-TOKEN'] : null;
        
        // Si no hay token en las cabeceras, intentar obtenerlo de POST
        if (!$token && isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        
        if (!$token || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            // Si el token CSRF no es válido, registrar el intento y responder con error
            error_log("Intento de CSRF en API detectado desde IP: " . $_SERVER['REMOTE_ADDR']);
            http_response_code(403);
            echo json_encode(['error' => 'Error de seguridad: token inválido']);
            exit;
        }
    }
}

// Verificar el token CSRF para API
verify_csrf_token_api();

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
    
    // Si es un archivo WebP, buscar versiones originales
    $extension = pathinfo($ruta_imagen, PATHINFO_EXTENSION);
    if (strtolower($extension) === 'webp') {
        // Intentar eliminar posibles versiones originales (jpg, png, jpeg)
        $rutaBase = pathinfo($ruta_imagen, PATHINFO_DIRNAME) . '/' . pathinfo($ruta_imagen, PATHINFO_FILENAME);
        $extensionesPosibles = ['.jpg', '.jpeg', '.png'];
        
        foreach ($extensionesPosibles as $ext) {
            $rutaPosible = $rutaBase . $ext;
            if (file_exists($rutaPosible)) {
                unlink($rutaPosible);
            }
        }
    } else {
        // Si no es WebP, buscar la versión WebP
        $rutaWebP = pathinfo($ruta_imagen, PATHINFO_DIRNAME) . '/' . pathinfo($ruta_imagen, PATHINFO_FILENAME) . '.webp';
        if (file_exists($rutaWebP)) {
            unlink($rutaWebP);
        }
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