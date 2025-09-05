<?php
require_once 'db.php';
require_once 'includes/rate_limiter.php';
require_once 'includes/security_headers.php';
require_once 'includes/secure_logger.php';

// Configuración de errores segura
ini_set('display_errors', env('DISPLAY_ERRORS', 0));
ini_set('display_startup_errors', 0);
error_reporting(env('DEBUG_MODE', false) ? E_ALL : E_ERROR);

// Configuración CORS restrictiva desde variables de entorno (ANTES de otros headers)
$allowed_origins = explode(',', env('ALLOWED_ORIGINS', 'https://puntocaps.shop,https://www.puntocaps.shop,https://punto-caps-landing.vercel.app'));
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Manejar preflight requests PRIMERO
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Validar origen para preflight
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // Cache preflight por 24 horas
    }
    http_response_code(204);
    exit();
}

// Validar origen para peticiones normales
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // Log intento de acceso no autorizado
    log_security('cors_violation', 'WARNING', [
        'origin' => $origin,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    if (!empty($origin)) {
        http_response_code(403);
        echo json_encode(['error' => 'Origen no autorizado']);
        exit();
    }
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Aplicar headers de seguridad DESPUÉS de CORS
apply_security_headers();

// Rate limiting para API
$rate_limiter = new RateLimiter();
if (!$rate_limiter->checkApiLimit($_SERVER['REMOTE_ADDR'])) {
    log_security('rate_limit_exceeded', 'WARNING', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'endpoint' => 'api_catalogo',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    http_response_code(429);
    echo json_encode(['error' => 'Demasiadas peticiones. Intenta más tarde.']);
    exit();
}

// Log de la solicitud
error_log("Solicitud recibida desde: " . ($origin ?? 'No origin'));

/**
 * Función para obtener productos activos con sus imágenes
 * @param int $categoria_id ID de la categoría para filtrar (opcional)
 * @param string $busqueda Término de búsqueda (opcional)
 * @param int $page Número de página
 * @param int $limit Cantidad de productos por página
 * @param int $product_id ID del producto para filtrar (opcional)
 * @return string JSON con los productos
 */
function obtenerProductosActivos($categoria_id = null, $busqueda = null, $page = 1, $limit = 12, $product_id = null) {
    try {
        $conn = getDB();
        if (!$conn) {
            throw new Exception("Error de conexión a la base de datos");
        }
        
        // Calcular offset para paginación
        $offset = ($page - 1) * $limit;
        
        // Construir consulta SQL base - incluir productos activos e inactivos
        $sql = "SELECT 
                    p.id,
                    p.codigo,
                    p.nombre,
                    p.categoria,
                    p.precio,
                    p.stock,
                    p.colores,
                    p.estado,
                    p.temporada
                FROM productos p
                WHERE p.stock > 0";
        
        $params = [];
        $types = "";
        
        // Agregar filtro por categoría si se especifica
        if ($categoria_id !== null) {
            // Primero intentar buscar por nombre en tabla categorias para obtener el ID
            $conn_temp = getDB();
            $stmt_cat = $conn_temp->prepare("SELECT id FROM categorias WHERE nombre = ?");
            $stmt_cat->bind_param("s", $categoria_id);
            $stmt_cat->execute();
            $result_cat = $stmt_cat->get_result();
            
            if ($result_cat && $row_cat = $result_cat->fetch_assoc()) {
                // Si encontramos el ID en la tabla categorias, usar categoria_id
                $sql .= " AND p.categoria_id = ?";
                $params[] = $row_cat['id'];
                $types .= "i";
            } else {
                // Si no, buscar por categoria legacy (texto libre)
                $sql .= " AND p.categoria = ?";
                $params[] = $categoria_id;
                $types .= "s";
            }
            $stmt_cat->close();
        }
        
        // Agregar filtro por id si se especifica
        if ($product_id !== null) {
            $sql .= " AND p.id = ?";
            $params[] = $product_id;
            $types .= "i";
        }
        
        // Agregar filtro por búsqueda si se especifica (solo si no se buscó por id)
        if ($busqueda !== null && $product_id === null) {
            $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.categoria LIKE ?)";
            $busqueda_param = "%$busqueda%";
            $params[] = $busqueda_param;
            $params[] = $busqueda_param;
            $params[] = $busqueda_param;
            $types .= "sss";
        }
        
        // Contar total de resultados para metadata de paginación (solo si no es búsqueda por id)
        $count_sql = str_replace("SELECT 
                    p.id,
                    p.codigo,
                    p.nombre,
                    p.categoria,
                    p.precio,
                    p.stock,
                    p.colores,
                    p.estado,
                    p.temporada", "SELECT COUNT(*) as total", $sql);
        
        if ($product_id === null) {
            $count_stmt = $conn->prepare($count_sql);
            if ($count_stmt && count($params) > 0) {
                $count_stmt->bind_param($types, ...$params);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $total_productos = $count_result->fetch_assoc()['total'];
                $count_stmt->close();
            } else if ($count_stmt) {
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $total_productos = $count_result->fetch_assoc()['total'];
                $count_stmt->close();
            } else {
                $total_productos = 0;
            }
        } else {
            $total_productos = 1; // búsqueda por id -> 1 o 0 resultados
        }
        
        // Completar la consulta principal con ordenamiento y paginación
        if ($product_id === null) {
            $sql .= " ORDER BY p.nombre ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
        }
        
        // Preparar y ejecutar la consulta
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conn->error);
        }
        
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            // Devolver valores tal cual para mantener caracteres como < y >
            
            // Obtener imágenes del producto
            $sql_imagenes = "SELECT ruta_imagen 
                            FROM imagenes_producto 
                            WHERE producto_id = ?";
            
            $stmt_img = $conn->prepare($sql_imagenes);
            if (!$stmt_img) {
                throw new Exception("Error preparando consulta de imágenes: " . $conn->error);
            }
            
            $stmt_img->bind_param("i", $row['id']);
            $stmt_img->execute();
            $result_imagenes = $stmt_img->get_result();
            
            $imagenes = [];
            
            // Solo cargar imágenes si el producto está activo (estado = 1)
            if ($row['estado'] == 1) {
                while ($img = $result_imagenes->fetch_assoc()) {
                    $ruta_imagen = $img['ruta_imagen'];
                    
                    // Formatear URL completa para las imágenes
                    if (!preg_match("/^https?:\/\//i", $ruta_imagen)) {
                        if (strpos($ruta_imagen, 'uploads/productos/') === false) {
                            $ruta_imagen = 'uploads/productos/' . basename($ruta_imagen);
                        }
                        // Construir URL completa con el dominio del sistema
                        $ruta_imagen = 'https://antero.cluishdev.uno/' . ltrim($ruta_imagen, '/');
                    }
                    
                    $imagenes[] = $ruta_imagen;
                }
            }
            // Si el producto está inactivo (estado = 0), las imágenes quedan como array vacío
            
            $stmt_img->close();
            
            // Crear estructura JSON con nombres de campos modificados
            $producto = [
                'id' => (string)$row['id'],
                'name' => $row['nombre'],
                'code' => $row['codigo'],
                'category' => $row['categoria'],
                'price' => (float)$row['precio'],
                'images' => $imagenes,
                'showImages' => count($imagenes) > 0 && $row['estado'] == 1, // true solo si tiene imágenes Y está activo
                'season' => $row['temporada'], // Campo temporada de la base de datos
                'active' => $row['estado'] == 1 // Nuevo campo para indicar si está activo
            ];
            
            $productos[] = $producto;
        }
        
        $stmt->close();
        $conn->close();
        
        // Crear respuesta con metadata de paginación
        $response = [
            'productos' => $productos,
            'metadata' => [
                'total' => (int)$total_productos,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => $product_id === null ? ceil($total_productos / $limit) : 1
            ]
        ];
        
        // Configurar cache para mejorar rendimiento
        $cache_time = 86400; // 24 horas
        header('Cache-Control: public, max-age=' . $cache_time);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_time) . ' GMT');
        
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        error_log("Error en obtenerProductosActivos: " . $e->getMessage());
        http_response_code(500);
        return json_encode(['error' => 'Error interno del servidor']);
    }
}

/**
 * Obtener categorías disponibles
 */
function obtenerCategorias() {
    try {
        $conn = getDB();
        if (!$conn) {
            throw new Exception("Error de conexión a la base de datos");
        }
        
        // Usar la nueva tabla categorias en lugar de productos
        $sql = "SELECT id, nombre, descripcion FROM categorias WHERE activo = 1 ORDER BY nombre ASC";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Error en la consulta: " . $conn->error);
        }
        
        $categorias = [];
        while ($row = $result->fetch_assoc()) {
            // Estructura más completa con id, nombre y descripción
            $categorias[] = [
                'id' => (int)$row['id'],
                'name' => $row['nombre'],
                'description' => $row['descripcion']
            ];
        }
        
        $conn->close();
        return json_encode(['categorias' => $categorias], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Error en obtenerCategorias: " . $e->getMessage());
        http_response_code(500);
        return json_encode(['error' => 'Error interno del servidor']);
    }
}

// Manejar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Validar y sanitizar parámetros
    $endpoint = isset($_GET['endpoint']) ? filter_var($_GET['endpoint'], FILTER_SANITIZE_STRING) : 'productos';
    $categoria = isset($_GET['categoria']) ? filter_var($_GET['categoria'], FILTER_SANITIZE_STRING) : null;
    $busqueda = isset($_GET['busqueda']) ? filter_var($_GET['busqueda'], FILTER_SANITIZE_STRING) : null;
    
    switch ($endpoint) {
        case 'categorias':
            echo obtenerCategorias();
            break;
            
        case 'productos':
        default:
   
            $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) : 1;
            $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT, ['options' => ['default' => 12, 'min_range' => 1, 'max_range' => 50]]) : 12;
            
            $product_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
            echo obtenerProductosActivos($categoria, $busqueda, $page, $limit, $product_id);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}