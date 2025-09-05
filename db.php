<?php
// includes/db.php
// Cargar variables de entorno
require_once __DIR__ . '/includes/env_loader.php';
require_once __DIR__ . '/includes/secure_session.php';

// Inicializar sesión segura
secure_session_start();

// Configuración de la base de datos desde variables de entorno
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', 'root'));
define('DB_NAME', env('DB_NAME', 'tiendarp'));

// Función para obtener la conexión
function getDB() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        $conn->set_charset("utf8");
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Función para verificar permisos
function checkPermission($requiredRole = 'user') {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    if ($requiredRole === 'admin' && !(isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
        header('Location: index.php?error=No tienes permisos para acceder a esta sección');
        exit();
    }
}

// Función para limpiar datos de entrada
function sanitize($data) {
    $conn = getDB();
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return $conn->real_escape_string(trim($item));
        }, $data);
    }
    return $conn->real_escape_string(trim($data));
}
?>