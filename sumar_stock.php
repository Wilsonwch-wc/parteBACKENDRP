<?php
require_once 'db.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar permisos
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para acceder a esta función']);
    exit;
}

// Función para verificar token CSRF si no existe
if (!function_exists('verify_csrf_token_api')) {
    function verify_csrf_token_api() {
        // Verificar si la petición es GET (no necesita token)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }
        
        $headers = getallheaders();
        $token = isset($headers['X-CSRF-TOKEN']) ? $headers['X-CSRF-TOKEN'] : null;
        
        // Si no hay token en las cabeceras, intentar obtenerlo de POST
        if (!$token && isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        
        if (!$token || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            // Si el token CSRF no es válido, registrar el intento y responder con error
            error_log("Intento de CSRF en API detectado desde IP: " . $_SERVER['REMOTE_ADDR']);
            return false;
        }
        
        return true;
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    // Consulta para obtener el stock actual
    $conn = getDB();
    $stmt = $conn->prepare("SELECT stock FROM productos WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'stock' => $row['stock']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    }
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar el token CSRF
    if (!verify_csrf_token_api()) {
        echo json_encode(['success' => false, 'error' => 'Error de seguridad: token inválido']);
        exit;
    }
    
    if (!isset($_POST['id']) || !isset($_POST['cantidad'])) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit;
    }

    $conn = getDB();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión']);
        exit;
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Actualizar el stock y la fecha de modificación
        $stmt = $conn->prepare("UPDATE productos SET stock = stock + ?, fecha_modificacion = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("ii", $_POST['cantidad'], $_POST['id']);
        
        if ($stmt->execute()) {
            // Obtener el stock actualizado
            $stmt = $conn->prepare("SELECT stock FROM productos WHERE id = ?");
            $stmt->bind_param("i", $_POST['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            // Confirmar transacción
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'nuevoStock' => $row['stock']
            ]);
        } else {
            throw new Exception("Error al actualizar el stock");
        }
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    $conn->close();
}
?> 