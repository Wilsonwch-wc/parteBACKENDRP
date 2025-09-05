<?php
require_once 'db.php';
require_once 'includes/security_headers.php';
require_once 'includes/secure_logger.php';

// Aplicar headers de seguridad
apply_security_headers();

checkPermission('admin');

// Log acceso de administrador
log_security('admin_access', 'INFO', [
    'user_id' => $_SESSION['user_id'] ?? 'unknown',
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

include 'includes/header.php';
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="admin-header bg-primary text-white p-4 rounded mb-4">
                <h1><i class="fas fa-user-shield me-2"></i>Panel de Administración</h1>
                <p class="mb-0">¡Bienvenido, administrador! Gestiona todos los aspectos del sistema desde aquí.</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Gestión de Categorías -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-tags fa-3x text-primary"></i>
                    </div>
                    <h5 class="card-title">Gestión de Categorías</h5>
                    <p class="card-text">Administra las categorías de productos del sistema. Crear, editar y eliminar categorías.</p>
                    <div class="d-grid gap-2">
                        <a href="gestionar_categorias.php" class="btn btn-primary">
                            <i class="fas fa-cog me-1"></i>Gestionar Categorías
                        </a>
                        <a href="../fron/admin_categorias.html" class="btn btn-outline-primary" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i>Panel Frontend
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API de Catálogo -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-code fa-3x text-success"></i>
                    </div>
                    <h5 class="card-title">API de Catálogo</h5>
                    <p class="card-text">Accede y gestiona el API del catálogo de productos y categorías.</p>
                    <div class="d-grid gap-2">
                        <a href="api_catalogo.php" class="btn btn-success" target="_blank">
                            <i class="fas fa-database me-1"></i>API Principal
                        </a>
                        <a href="api_categorias_admin.php" class="btn btn-outline-success" target="_blank">
                            <i class="fas fa-tools me-1"></i>API Categorías Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gestión de Productos -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-box fa-3x text-warning"></i>
                    </div>
                    <h5 class="card-title">Gestión de Productos</h5>
                    <p class="card-text">Administra el inventario de productos, precios y stock del sistema.</p>
                    <div class="d-grid gap-2">
                        <a href="productos.php" class="btn btn-warning">
                            <i class="fas fa-boxes me-1"></i>Ver Productos
                        </a>
                        <a href="agregar_producto.php" class="btn btn-outline-warning">
                            <i class="fas fa-plus me-1"></i>Agregar Producto
                        </a>
                        <a href="gestionar_categorias.php" class="btn btn-outline-warning">
                            <i class="fas fa-tags me-1"></i>Gestionar Categorías
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Subir Fotos -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-camera fa-3x text-info"></i>
                    </div>
                    <h5 class="card-title">Gestión de Imágenes</h5>
                    <p class="card-text">Sube y gestiona las imágenes de productos del catálogo.</p>
                    <div class="d-grid gap-2">
                        <a href="subir_foto.php" class="btn btn-info">
                            <i class="fas fa-upload me-1"></i>Subir Fotos
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Telemetría -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-chart-line fa-3x text-secondary"></i>
                    </div>
                    <h5 class="card-title">Telemetría</h5>
                    <p class="card-text">Monitorea el rendimiento y estadísticas del sistema.</p>
                    <div class="d-grid gap-2">
                        <a href="telemetria.php" class="btn btn-secondary">
                            <i class="fas fa-analytics me-1"></i>Ver Telemetría
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración del Sistema -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-cogs fa-3x text-dark"></i>
                    </div>
                    <h5 class="card-title">Configuración</h5>
                    <p class="card-text">Ajusta la configuración general del sistema y parámetros.</p>
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-dark" onclick="alert('Próximamente disponible')">
                            <i class="fas fa-wrench me-1"></i>Configuración
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Información del Sistema -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrador'); ?></p>
                            <p><strong>Rol:</strong> <?php echo htmlspecialchars($_SESSION['role'] ?? 'admin'); ?></p>
                            <p><strong>Última conexión:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>IP:</strong> <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></p>
                            <p><strong>Navegador:</strong> <?php echo htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 50)); ?>...</p>
                            <p><strong>Servidor:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_NAME']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}
.card {
    transition: transform 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-5px);
}
.fa-3x {
    margin-bottom: 1rem;
}
</style>
<?php include 'includes/footer.php'; ?>
