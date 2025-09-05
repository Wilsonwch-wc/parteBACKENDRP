<?php
require_once 'db.php';
require_once 'includes/security_headers.php';
require_once 'includes/secure_logger.php';

// Aplicar headers de seguridad
apply_security_headers();

checkPermission();

// Log acceso al sistema
log_security('system_access', 'INFO', [
    'user_id' => $_SESSION['user_id'] ?? 'unknown',
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Hacer el redirect antes de cualquier salida HTML
if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'usuario' || true) {
    header("Location: catalog");
    exit;
}

// Eliminar todo el HTML ya que nunca se mostrará debido al redirect
?>