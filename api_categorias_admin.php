<?php
require_once 'db.php';
require_once 'includes/secure_session.php';
require_once 'includes/security_headers.php';
require_once 'includes/rate_limiter.php';

// Configurar headers de seguridad
SecurityHeaders::apply();

// Configurar CORS restrictivo
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar que el usuario esté logueado y sea admin
try {
    checkPermission('admin');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado', 'message' => 'Solo administradores pueden gestionar categorías']);
    exit();
}

// Rate limiting
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIP, 'api_categorias', 60, 100)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $conn = getDB();
    
    switch ($method) {
        case 'GET':
            handleGetCategorias($conn);
            break;
            
        case 'POST':
            handleCreateCategoria($conn, $input);
            break;
            
        case 'PUT':
            handleUpdateCategoria($conn, $input);
            break;
            
        case 'DELETE':
            handleDeleteCategoria($conn, $input);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en API Categorías Admin: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}

function handleGetCategorias($conn) {
    $sql = "SELECT id, nombre, descripcion, activo, fecha_creacion, fecha_actualizacion FROM categorias ORDER BY nombre";
    $result = $conn->query($sql);
    
    $categorias = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Contar productos asociados
            $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?");
            $stmt_count->bind_param("i", $row['id']);
            $stmt_count->execute();
            $count_result = $stmt_count->get_result();
            $row['productos_count'] = $count_result->fetch_assoc()['count'];
            $stmt_count->close();
            
            $categorias[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $categorias,
        'total' => count($categorias)
    ]);
}

function handleCreateCategoria($conn, $input) {
    // Validar datos de entrada
    if (empty($input['nombre'])) {
        http_response_code(400);
        echo json_encode(['error' => 'El nombre de la categoría es obligatorio']);
        return;
    }
    
    $nombre = trim($input['nombre']);
    $descripcion = trim($input['descripcion'] ?? '');
    
    // Verificar que no exista una categoría con el mismo nombre
    $stmt_check = $conn->prepare("SELECT id FROM categorias WHERE nombre = ?");
    $stmt_check->bind_param("s", $nombre);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        http_response_code(409);
        echo json_encode(['error' => 'Ya existe una categoría con ese nombre']);
        return;
    }
    $stmt_check->close();
    
    // Crear la categoría
    $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
    $stmt->bind_param("ss", $nombre, $descripcion);
    
    if ($stmt->execute()) {
        $categoria_id = $conn->insert_id;
        
        // Obtener la categoría creada
        $stmt_get = $conn->prepare("SELECT id, nombre, descripcion, activo, fecha_creacion FROM categorias WHERE id = ?");
        $stmt_get->bind_param("i", $categoria_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $categoria = $result_get->fetch_assoc();
        $categoria['productos_count'] = 0;
        $stmt_get->close();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Categoría creada exitosamente',
            'data' => $categoria
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear la categoría']);
    }
    
    $stmt->close();
}

function handleUpdateCategoria($conn, $input) {
    // Validar datos de entrada
    if (empty($input['id']) || empty($input['nombre'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID y nombre son obligatorios']);
        return;
    }
    
    $id = intval($input['id']);
    $nombre = trim($input['nombre']);
    $descripcion = trim($input['descripcion'] ?? '');
    $activo = isset($input['activo']) ? intval($input['activo']) : 1;
    
    // Verificar que la categoría existe
    $stmt_check = $conn->prepare("SELECT id FROM categorias WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        http_response_code(404);
        echo json_encode(['error' => 'Categoría no encontrada']);
        return;
    }
    $stmt_check->close();
    
    // Verificar que no exista otra categoría con el mismo nombre
    $stmt_check_name = $conn->prepare("SELECT id FROM categorias WHERE nombre = ? AND id != ?");
    $stmt_check_name->bind_param("si", $nombre, $id);
    $stmt_check_name->execute();
    $result_check_name = $stmt_check_name->get_result();
    
    if ($result_check_name->num_rows > 0) {
        $stmt_check_name->close();
        http_response_code(409);
        echo json_encode(['error' => 'Ya existe otra categoría con ese nombre']);
        return;
    }
    $stmt_check_name->close();
    
    // Actualizar la categoría
    $stmt = $conn->prepare("UPDATE categorias SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
    $stmt->bind_param("ssii", $nombre, $descripcion, $activo, $id);
    
    if ($stmt->execute()) {
        // Obtener la categoría actualizada
        $stmt_get = $conn->prepare("SELECT id, nombre, descripcion, activo, fecha_creacion, fecha_actualizacion FROM categorias WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $categoria = $result_get->fetch_assoc();
        
        // Contar productos asociados
        $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?");
        $stmt_count->bind_param("i", $id);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $categoria['productos_count'] = $count_result->fetch_assoc()['count'];
        $stmt_count->close();
        $stmt_get->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente',
            'data' => $categoria
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar la categoría']);
    }
    
    $stmt->close();
}

function handleDeleteCategoria($conn, $input) {
    // Validar datos de entrada
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID es obligatorio']);
        return;
    }
    
    $id = intval($input['id']);
    
    // Verificar que la categoría existe
    $stmt_check = $conn->prepare("SELECT nombre FROM categorias WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        http_response_code(404);
        echo json_encode(['error' => 'Categoría no encontrada']);
        return;
    }
    
    $categoria_data = $result_check->fetch_assoc();
    $stmt_check->close();
    
    // Verificar si la categoría está siendo usada
    $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?");
    $stmt_count->bind_param("i", $id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $count = $result_count->fetch_assoc()['count'];
    $stmt_count->close();
    
    if ($count > 0) {
        http_response_code(409);
        echo json_encode([
            'error' => 'No se puede eliminar la categoría',
            'message' => "La categoría '{$categoria_data['nombre']}' tiene {$count} producto(s) asociado(s)"
        ]);
        return;
    }
    
    // Eliminar la categoría
    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => "Categoría '{$categoria_data['nombre']}' eliminada exitosamente"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar la categoría']);
    }
    
    $stmt->close();
}
?>