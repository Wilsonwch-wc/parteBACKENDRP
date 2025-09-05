<?php
/**
 * Router seguro para ocultar estructura de archivos
 * Maneja todas las rutas de forma centralizada
 */

require_once 'db.php';
require_once 'includes/security_headers.php';
require_once 'includes/secure_logger.php';

// Aplicar headers de seguridad
apply_security_headers();

// Obtener la ruta solicitada
$route = $_GET['route'] ?? '';
$route = trim($route, '/');

// Definir rutas permitidas y sus archivos correspondientes
$routes = [
    '' => 'index.php',
    'home' => 'index.php',
    'login' => 'login.php',
    'logout' => 'logout.php',
    'catalog' => 'menu_fotos.php',
    'admin' => 'admin.php',
    'manage' => 'administrar.php',
    'history' => 'historial.php',
    'stats' => 'estadisticas.php',
    'api/catalog' => 'api_catalogo.php',
    'api/ping' => 'ping.php'
];

// Rutas que requieren autenticación
$protected_routes = [
    'catalog', 'admin', 'manage', 'history', 'stats', 'api/catalog'
];

// Rutas que requieren rol de administrador
$admin_routes = [
    'admin', 'manage', 'stats'
];

// Función para verificar autenticación
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si es administrador
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Log del acceso
log_security('route_access', 'INFO', [
    'route' => $route,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'user_id' => $_SESSION['user_id'] ?? 'anonymous'
]);

// Verificar si la ruta existe
if (!array_key_exists($route, $routes)) {
    log_security('invalid_route_access', 'WARNING', [
        'route' => $route,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Página no encontrada</title></head><body><h1>404 - Página no encontrada</h1></body></html>';
    exit;
}

// Verificar autenticación para rutas protegidas
if (in_array($route, $protected_routes) && !isAuthenticated()) {
    log_security('unauthorized_access_attempt', 'WARNING', [
        'route' => $route,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    header('Location: login');
    exit;
}

// Verificar permisos de administrador
if (in_array($route, $admin_routes) && !isAdmin()) {
    log_security('admin_access_denied', 'WARNING', [
        'route' => $route,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Acceso denegado</title></head><body><h1>403 - Acceso denegado</h1></body></html>';
    exit;
}

// Obtener el archivo correspondiente
$file = $routes[$route];

// Verificar que el archivo existe
if (!file_exists($file)) {
    log_error('route_file_not_found', [
        'route' => $route,
        'file' => $file
    ]);
    
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error interno</title></head><body><h1>500 - Error interno del servidor</h1></body></html>';
    exit;
}

// Incluir el archivo solicitado
include $file;
?>