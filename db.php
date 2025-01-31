<?php
// includes/db.php
session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tiendaropa');

// Función para obtener la conexión
function getDB() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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

// Función para verificar si el usuario es admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Función para verificar permisos
function checkPermission($requiredRole = 'user') {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    if ($requiredRole === 'admin' && !isAdmin()) {
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