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

// Función para formatear números en pesos argentinos
if (!function_exists('formato_pesos_arg')) {
    function formato_pesos_arg($numero, $decimales = 0) {
        return number_format($numero, $decimales, ',', '.');
    }
}

// Función para verificar token CSRF si no existe
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token() {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            // Si el token CSRF no es válido, registrar el intento y redirigir
            error_log("Intento de CSRF detectado desde IP: " . $_SERVER['REMOTE_ADDR']);
            header("Location: index.php?error=Error de seguridad: token inválido");
            exit;
        }
    }
}

// Función para generar token CSRF si no existe
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Generar token CSRF si no existe
generate_csrf_token();

// Agregar este endpoint al inicio del archivo
if (isset($_POST['action']) && $_POST['action'] == 'cambiarEstado') {
    $conn = getDB();
    
    // Verificar el token CSRF
    verify_csrf_token();
    
    $id = $_POST['id'];
    $estado = $_POST['estado'];
    
    $sql = "UPDATE productos SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $estado, $id);
    
    $response = ['success' => false];
    if ($stmt->execute()) {
        $response = ['success' => true];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Incluir header después de procesar la actualización
include 'includes/header.php';

// Obtener las categorías desde la base de datos para el filtro
$conn = getDB();
$sqlCategorias = "SELECT DISTINCT categoria FROM productos WHERE categoria != '' ORDER BY categoria";
$resultCategorias = $conn->query($sqlCategorias);
$categorias = [];

if ($resultCategorias && $resultCategorias->num_rows > 0) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $categorias[] = $row['categoria'];
    }
}

// Agregar estilos personalizados para búsqueda y tarjetas
?>
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
<style>
    /* Estilos para el buscador y filtros */
    .search-container {
        position: relative;
        background-color: var(--input-bg);
        border-radius: 6px;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
        margin-bottom: 0;
    }
    
    .search-container:focus-within {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-1px);
    }
    
    .search-container input {
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 12px;
        padding: 12px 40px 12px 16px;
        font-size: 0.95rem;
        width: 100%;
        transition: all 0.3s ease;
        background-color: transparent;
    }
    
    .search-container input:focus {
        outline: none;
        border-color: #0d6efd;
    }
    
    .search-icon {
        color: #6c757d;
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        transition: color 0.3s ease;
    }
    
    .search-container:focus-within .search-icon {
        color: #0d6efd;
    }
    
    .filters-container {
        height: 100%;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    
    .filters-container:focus-within {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-1px);
    }
    
    .filter-select {
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 0.95rem;
        width: 100%;
        height: 100%;
        cursor: pointer;
        background-color: transparent;
        transition: all 0.3s ease;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #0d6efd;
    }
    
    /* Responsive para dispositivos pequeños */
    @media (max-width: 576px) {
        .search-container input,
        .filter-select {
            padding: 10px 35px 10px 12px;
            font-size: 0.9rem;
        }
        
        .search-icon {
            right: 12px;
        }
        
        .search-container,
        .filters-container {
            border-radius: 8px;
        }
    }
    
    /* Ajustes para tablets */
    @media (min-width: 768px) and (max-width: 991px) {
        .search-container input,
        .filter-select {
            padding: 11px 40px 11px 14px;
        }
    }
    
    /* Contenedor principal de búsqueda y filtros */
    .search-filters-wrapper {
        background-color: var(--card-bg);
        border-radius: 8px;
        padding: 8px !important;
        margin-bottom: 15px;
        border: 1px solid var(--border-color);
    }
    
    /* Ajustes para el contenedor de búsqueda y filtros */
    .row.mb-3 {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Ajusta el espacio entre las columnas */
    .search-filters-wrapper .col-md-8 {
        padding-right: 5px;
    }
    
    .search-filters-wrapper .col-md-4 {
        padding-left: 5px;
    }
    
    @media (max-width: 767px) {
        .search-filters-wrapper .col-md-8,
        .search-filters-wrapper .col-md-4 {
            padding-left: 5px;
            padding-right: 5px;
        }
        
        .search-filters-wrapper .col-md-8 {
            margin-bottom: 5px !important;
        }
    }
    
    /* Estilos para el buscador y filtros */
    .search-container {
        position: relative;
        background-color: var(--input-bg);
        border-radius: 6px;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
    }
    
    .search-container:focus-within {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,0.15);
    }
    
    .search-container input {
        border: none;
        border-radius: 6px;
        padding: 10px 35px 10px 12px;
        font-size: 0.95rem;
        width: 100%;
        transition: all 0.2s ease;
        background-color: transparent;
        color: var(--text-color);
    }
    
    .search-container input:focus {
        outline: none;
    }
    
    .filters-container {
        height: 100%;
        background-color: var(--input-bg);
        border-radius: 6px;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
    }
    
    .filters-container:focus-within {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,0.15);
    }
    
    .filter-select {
        border: none;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 0.95rem;
        width: 100%;
        height: 100%;
        cursor: pointer;
        background-color: transparent;
        transition: all 0.2s ease;
        color: var(--text-color);
    }
    
    /* Estilos para botones en modo oscuro */
    [data-theme="dark"] .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }
    
    [data-theme="dark"] .btn-success {
        background-color: #198754;
        border-color: #198754;
        color: white;
    }
    
    [data-theme="dark"] .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    
    [data-theme="dark"] .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
    }
    
    [data-theme="dark"] .btn-info {
        background-color: #0dcaf0;
        border-color: #0dcaf0;
        color: #212529;
    }
    
    [data-theme="dark"] .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }
    
    [data-theme="dark"] .btn-outline-primary {
        color: #0d6efd;
        border-color: #0d6efd;
    }
    
    [data-theme="dark"] .btn-outline-primary:hover {
        background-color: #0d6efd;
        color: white;
    }
    
    [data-theme="dark"] .btn-outline-secondary {
        color: #6c757d;
        border-color: #6c757d;
    }
    
    [data-theme="dark"] .btn-outline-secondary:hover {
        background-color: #6c757d;
        color: white;
    }
    
    /* Reducir el espacio en el contenedor de búsqueda */
    .search-filters-wrapper {
        padding: 10px;
    }
    
    .search-filters-wrapper .row.mb-3 {
        margin-bottom: 0 !important;
    }
    
    /* Estilos para las tarjetas de productos */
    .card {
        transition: all 0.2s ease;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .card:hover {
        box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
        transform: translateY(-2px);
    }
    
    .card-header {
        background-color: rgba(0,0,0,0.02);
        border-bottom: 1px solid rgba(0,0,0,0.08);
    }
    
    .card-footer {
        background-color: rgba(0,0,0,0.02);
        border-top: 1px solid rgba(0,0,0,0.08);
    }
    
    .carousel-control-next, 
    .carousel-control-prev {
        opacity: 0.7;
        width: 10%;
    }
    
    /* Layout responsivo mejorado */
    @media (min-width: 1400px) {
        .container-xxl {
            max-width: 100%;
            padding-left: 30px;
            padding-right: 30px;
        }
    }
    
    @media (max-width: 1399px) {
        .container-xxl {
            max-width: 100%;
            padding-left: 20px;
            padding-right: 20px;
        }
    }
    
    /* Responsive para dispositivos pequeños */
    @media (max-width: 576px) {
        .container-xxl {
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .card-title {
            font-size: 1rem;
        }
        
        .card-text {
            font-size: 0.875rem;
        }
        
        .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }
    }
    
    /* Mejoras de accesibilidad y usabilidad */
    .form-control:focus,
    .form-select:focus,
    .btn:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    /* Estilo para el estado del producto */
    .estado-texto {
        font-weight: bold;
    }
    
    /* Estilos para botones flotantes */
    .btn-floating {
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: none;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        text-align: center;
        padding: 0;
        z-index: 1000;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    
    .btn-floating:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
    }
    
    /* Estilos para gestión de stock */
    .input-group {
        width: 200px;
        margin: 0 auto;
    }
    
    #cantidadStock {
        text-align: center;
    }
    
    #cantidadStock::-webkit-outer-spin-button,
    #cantidadStock::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .stock-actual {
        font-size: 1.1rem;
        color: #0d6efd;
    }
    
    .producto-info {
        font-size: 1.1rem;
    }
    
    /* Estilo para el texto de ayuda */
    .form-text {
        text-align: center;
        margin-top: 0.5rem;
    }
    
    /* Ajustes para tablets */
    @media (min-width: 768px) and (max-width: 991px) {
        .row-cols-md-3 > * {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }
    
    /* Mejorar la apariencia de las tarjetas */
    .card {
        border: 1px solid rgba(0,0,0,0.08);
    }
    
    .card:hover {
        border-color: rgba(0,0,0,0.12);
    }
    
    /* Hacer que el botón de filtro sea más visible */
    .filters-container {
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-radius: 4px;
    }
    
    /* Ajustes para el contenedor principal */
    .container-xxl {
        width: 100%;
        margin-right: auto;
        margin-left: auto;
    }
    
    /* Ajustes para el grid de productos */
    .row-cols-1 {
        margin-right: -0.5rem;
        margin-left: -0.5rem;
    }
    
    .row-cols-1 > * {
        padding-right: 0.5rem;
        padding-left: 0.5rem;
    }
    
    /* Optimización para pantallas grandes */
    @media (min-width: 1600px) {
        .row-cols-xxl-5 > * {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }
    
    /* Eliminar contornos blancos en modo oscuro */
    [data-theme="dark"] input,
    [data-theme="dark"] select,
    [data-theme="dark"] textarea,
    [data-theme="dark"] button,
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        background-color: #2b3035 !important;
        color: #f8f9fa !important;
    }
    
    /* Corrección para el selector de categorías */
    [data-theme="dark"] select, 
    [data-theme="dark"] .form-select {
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 16px 12px !important;
    }
    
    /* Estilos para el buscador */
    [data-theme="dark"] .form-control-search,
    [data-theme="dark"] input[type="search"] {
        border-radius: 50px !important;
        padding-left: 15px !important;
        background-color: #2b3035 !important;
        border: none !important;
    }
    
    /* Eliminar efectos hover celestes */
    [data-theme="dark"] .btn:hover,
    [data-theme="dark"] .card:hover,
    [data-theme="dark"] .list-group-item:hover,
    [data-theme="dark"] .nav-link:hover,
    [data-theme="dark"] .dropdown-item:hover,
    [data-theme="dark"] .form-control:focus,
    [data-theme="dark"] .form-select:focus {
        background-color: transparent !important;
        box-shadow: none !important;
        background-image: none !important;
    }
    
    /* Eliminar todos los contornos en focus */
    *:focus {
        outline: none !important;
        box-shadow: none !important;
        border: none !important;
    }
    
    /* Estilos para checkbox */
    [data-theme="dark"] .form-check-input[type="checkbox"] {
        background-color: #343a40 !important;
        border: 1px solid #6c757d !important;
    }
    
    [data-theme="dark"] .form-check-input[type="checkbox"]:checked {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
    }
    
    /* Estilos para tarjetas y elementos de lista */
    [data-theme="dark"] .card,
    [data-theme="dark"] .list-group-item {
        border: none !important;
        background-color: var(--card-bg) !important;
    }
    
    /* Redondeo del buscador */
    .search-container {
        border-radius: 50px !important;
        overflow: hidden !important;
        background-color: var(--input-bg) !important;
    }
    
    /* Remover bordes de botones */
    button, .btn {
        border: none !important;
    }
    
    /* Estilos específicos para el buscador principal */
    #buscador {
        height: 45px !important;
        border: none !important;
        box-shadow: none !important;
        background-color: transparent !important;
        padding-left: 40px !important;
        font-size: 1rem !important;
    }
    
    .search-container {
        border-radius: 50px !important;
        overflow: hidden !important;
        background-color: var(--input-bg) !important;
        border: none !important;
    }
    
    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-color) !important;
        opacity: 0.7;
        z-index: 10;
    }
    
    /* Estilos para el modo oscuro específicos para el buscador */
    [data-theme="dark"] .search-container {
        background-color: #2b3035 !important;
    }
    
    [data-theme="dark"] #buscador {
        background-color: transparent !important;
        color: #f8f9fa !important;
    }
    
    [data-theme="dark"] .search-icon {
        color: #f8f9fa !important;
    }
    
    /* Eliminar todos los efectos al pasar el cursor */
    *:hover {
        border-color: transparent !important;
    }
    
    /* Estilos específicos para botones en modo oscuro */
    [data-theme="dark"] .btn-primary,
    [data-theme="dark"] .btn-success,
    [data-theme="dark"] .btn-danger,
    [data-theme="dark"] .btn-warning,
    [data-theme="dark"] .btn-info {
        border: none !important;
    }
    
    /* Eliminar contornos en todos los elementos del formulario */
    input, select, textarea, button, .form-control, .form-select {
        box-shadow: none !important;
        outline: none !important;
    }
    
    /* Eliminar transiciones */
    .form-control, .form-select, button, .btn {
        transition: none !important;
    }
    
    /* Loader para carga de imágenes */
    .loader-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }
    
    .loader-container.active {
        opacity: 1;
        visibility: visible;
    }
    
    .loader {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 8px solid #f3f3f3;
        border-top: 8px solid #0d6efd;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Mejoras para el loader */
    .loader-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }

    .loader-container.active {
        opacity: 1;
        visibility: visible;
    }

    .loader {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 6px solid #f3f3f3;
        border-top: 6px solid #0d6efd;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }

    .loader-message {
        color: white;
        font-size: 16px;
        text-align: center;
        margin-top: 15px;
        max-width: 80%;
        background: rgba(0,0,0,0.6);
        padding: 10px 20px;
        border-radius: 20px;
        font-weight: 500;
    }

    /* Mejoras para previsualizaciones */
    .image-info {
        position: absolute;
        bottom: 8px;
        left: 8px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .preview-image {
        transition: filter 0.3s ease-out;
        min-height: 150px;
        background-color: #f0f0f0;
    }
</style>
<?php
$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

// Consulta para obtener productos
$registrosPorPagina = 10; // Reducido de 9 a 6 productos por página
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Modificar la consulta principal para incluir paginación
$sql = "SELECT p.*, 
        (SELECT GROUP_CONCAT(i.ruta_imagen ORDER BY i.id) 
         FROM imagenes_producto i 
         WHERE i.producto_id = p.id) as imagenes,
        (SELECT COUNT(*) 
         FROM imagenes_producto i 
         WHERE i.producto_id = p.id) as total_imagenes 
        FROM productos p 
        ORDER BY p.fecha_creacion DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid container-xxl px-4">

    <!-- Botón Volver Arriba -->
    <button id="btnVolverArriba" class="btn btn-primary btn-floating" style="display: none;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <?php
    // Mostrar mensajes de éxito o error desde la sesión
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $_SESSION['success'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . $_SESSION['error'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['error']);
    }
    ?>

    <?php if (isset($_GET['error']) && $_GET['error'] == 'tiene_ventas'): ?>
        <div class="alert alert-warning">
            El producto tiene historial de ventas asociado. Si desea eliminarlo, debe confirmar que también se eliminarán todas sus ventas del historial.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] == 'error_eliminar'): ?>
        <div class="alert alert-danger">
            Ocurrió un error al intentar eliminar el producto. Por favor, inténtelo nuevamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] == 'eliminado'): ?>
        <div class="alert alert-success">
            El producto ha sido eliminado exitosamente.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Contenedor de búsqueda y filtros mejorado -->
            <div class="search-filters-wrapper">
                <div class="row mb-3">
                    <!-- Sección de buscador -->
                    <div class="col-md-8 mb-2 mb-md-0">
                        <div class="search-container position-relative">
                            <input type="text" 
                                   id="buscador" 
                                   class="form-control" 
                                   placeholder="Buscar por nombre, código o categoría..."
                                   autocomplete="off">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                    
                    <!-- Sección de filtros -->
                    <div class="col-md-4">
                        <div class="filters-container">
                            <select id="filtroCategoria" class="filter-select">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo h($categoria); ?>"><?php echo h($categoria); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contenedor específico para el contenido actualizable -->
            <div id="contenedorProductos">
                <?php
                // Consulta para contar total de registros
                $sqlCount = "SELECT COUNT(*) as total FROM productos";
                $resultCount = $conn->query($sqlCount);
                $totalRegistros = $resultCount->fetch_assoc()['total'];
                $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

                // Modificar la consulta principal para incluir paginación
                $sql = "SELECT p.*, 
                        (SELECT GROUP_CONCAT(i.ruta_imagen ORDER BY i.id) 
                         FROM imagenes_producto i 
                         WHERE i.producto_id = p.id) as imagenes,
                        (SELECT COUNT(*) 
                         FROM imagenes_producto i 
                         WHERE i.producto_id = p.id) as total_imagenes 
                        FROM productos p 
                        ORDER BY p.fecha_creacion DESC 
                        LIMIT ? OFFSET ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $registrosPorPagina, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                ?>
                
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xxl-5 g-3 mb-4" id="productosGrid">
                    <?php
                    while($row = $result->fetch_assoc()) {
                        $imagenes = $row['imagenes'] ? explode(',', $row['imagenes']) : [];
                        ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm" data-id="<?php echo $row['id']; ?>">
                                <div class="card-header py-2">
                                    <small class="text-muted">Código: <span class="codigo-producto"><?php echo h($row['codigo']); ?></span></small>
                                </div>
                                <!-- Carrusel de imágenes -->
                                <div id="carousel<?php echo $row['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner">
                                        <?php
                                        if (!empty($row['imagenes'])) {
                                            $imagenes = explode(',', $row['imagenes']);
                                            foreach($imagenes as $index => $imagen) {
                                                ?>
                                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                                    <img src="<?php echo h($imagen); ?>" 
                                                         class="d-block w-100" 
                                                         style="height: 240px; object-fit: cover;" 
                                                         alt="<?php echo h($row['nombre']); ?>">
                                                </div>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <div class="carousel-item active">
                                                <div class="d-flex align-items-center justify-content-center bg-light" 
                                                     style="height: 240px;">
                                                    <span class="text-secondary">Sin imagen disponible</span>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($row['total_imagenes']) && $row['total_imagenes'] > 1) { ?>
                                        <button class="carousel-control-prev" type="button" 
                                                data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon"></span>
                                        </button>
                                        <button class="carousel-control-next" type="button" 
                                                data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="next">
                                            <span class="carousel-control-next-icon"></span>
                                        </button>
                                    <?php } ?>
                                </div>
                                <div class="card-body py-2">
                                    <h5 class="card-title fs-6"><?php echo h($row['nombre']); ?></h5>
                                    <p class="card-text small mb-2">
                                        <strong>Categoría:</strong> <span class="categoria-producto"><?php echo h($row['categoria']); ?></span><br>
                                        <strong>Precio:</strong> $<span class="precio-producto"><?php echo formato_pesos_arg($row['precio']); ?></span><br>
                                        <strong>Stock:</strong> <span class="stock-producto"><?php echo $row['stock']; ?></span><br>
                                        <strong>Estado:</strong> <span class="estado-texto"><?php echo $row['estado'] == 1 ? 'Activo' : 'Inactivo'; ?></span>
                                    </p>
                                    <div class="d-grid gap-1">
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="editarProducto(<?php echo h(json_encode($row)); ?>)">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="sumarStock(<?php echo $row['id']; ?>, '<?php echo h($row['nombre']); ?>')">
                                            <i class="fas fa-plus"></i> Sumar Stock
                                        </button>
                                        <button class="btn btn-sm <?php echo $row['estado'] == 1 ? 'btn-warning' : 'btn-success'; ?>" 
                                                onclick="return cambiarEstadoProducto(<?php echo $row['id']; ?>, <?php echo $row['estado'] == 1 ? 0 : 1; ?>, event)">
                                            <?php echo $row['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-footer d-flex justify-content-between align-items-center py-2">
                                    <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nombre']); ?>')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <?php if ($totalPaginas > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        Mostrando <?php echo ($offset + 1); ?>-<?php echo min($offset + $registrosPorPagina, $totalRegistros); ?> de <?php echo $totalRegistros; ?> productos
                    </div>
                    
                    <!-- Paginación -->
                    <nav aria-label="Navegación de páginas">
                        <ul class="pagination mb-0">
                            <?php if ($paginaActual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php 
                                        $_GET['pagina'] = $paginaActual - 1;
                                        echo http_build_query($_GET);
                                    ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $rangoInicio = max(1, $paginaActual - 2);
                            $rangoFin = min($totalPaginas, $paginaActual + 2);
                            
                            if ($rangoInicio > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?';
                                $_GET['pagina'] = 1;
                                echo http_build_query($_GET);
                                echo '">1</a></li>';
                                if ($rangoInicio > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $rangoInicio; $i <= $rangoFin; $i++) {
                                echo '<li class="page-item ';
                                if ($i == $paginaActual) echo 'active';
                                echo '"><a class="page-link" href="?';
                                $_GET['pagina'] = $i;
                                echo http_build_query($_GET);
                                echo '">' . $i . '</a></li>';
                            }

                            if ($rangoFin < $totalPaginas) {
                                if ($rangoFin < $totalPaginas - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?';
                                $_GET['pagina'] = $totalPaginas;
                                echo http_build_query($_GET);
                                echo '">' . $totalPaginas . '</a></li>';
                            }
                            ?>

                            <?php if ($paginaActual < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php 
                                        $_GET['pagina'] = $paginaActual + 1;
                                        echo http_build_query($_GET);
                                    ?>" aria-label="Siguiente">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edición -->
<div class="modal fade" id="editarModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditar" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="hidden" name="id" id="editId">
                            <input type="hidden" name="estado" id="editEstado">
                            
                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" name="codigo" id="editCodigo" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Descripción del Producto</label>
                                <input type="text" class="form-control" name="nombre" id="editNombre" required maxlength="37"
                                       oninvalid="this.setCustomValidity('La descripción no debe exceder los 38 caracteres')"
                                       oninput="this.setCustomValidity('')">
                            </div>
                            
                            <div class="mb-3">
                                <label for="editCategoria" class="form-label">Categoría</label>
                                <input type="text" class="form-control" name="categoria" id="editCategoria" list="categorias-list" required>
                                <datalist id="categorias-list">
                                    <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo h($categoria); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Stock</label>
                                <input type="number" class="form-control" name="stock" id="editStock" required min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Precio</label>
                                <input type="number" class="form-control" name="precio" id="editPrecio" required min="0" step="1" onchange="this.value = Math.floor(this.value)">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Precio de Compra</label>
                                <input type="number" class="form-control" name="precio_compra" id="editPrecioCompra" required min="0" step="1" onchange="this.value = Math.floor(this.value)">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Colores (separados por coma)</label>
                                <input type="text" class="form-control" name="colores" id="editColores" >
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Imágenes Actuales</label>
                                <div id="imagenesActuales" class="mb-2">
                                    <!-- Las imágenes actuales se mostrarán aquí -->
                                </div>
                                <label class="form-label">Agregar Nuevas Imágenes</label>
                                <div class="d-grid gap-2">
                                    <!-- Botón para tomar foto -->
                                    <button type="button" class="btn btn-primary" id="btnTakePhoto">
                                        <i class="fas fa-camera me-2"></i>Tomar Foto
                                    </button>
                                    <!-- Botón para seleccionar de galería -->
                                    <button type="button" class="btn btn-secondary" id="btnSelectFiles">
                                        <i class="fas fa-images me-2"></i>Seleccionar de Galería
                                    </button>
                                </div>
                                <input type="file" class="form-control d-none" name="nuevas_imagenes[]" 
                                       id="nuevasImagenes"
                                       multiple accept="image/*">
                                <div id="previewNuevasImagenes" class="mt-3 d-flex flex-wrap gap-2">
                                    <!-- Aquí se mostrarán las previsualizaciones -->
                                </div>
                                <small class="text-muted">Puede seleccionar múltiples imágenes</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" name="actualizar" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Agregar este modal antes del cierre del body -->
<!-- Modal para Sumar Stock -->
<div class="modal fade" id="sumarStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modificar Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="producto-info mb-3">
                    <strong>Producto:</strong>
                    <span id="productoNombre" class="ms-2"></span>
                </div>
                <div class="stock-actual mb-3">
                    <strong>Stock Actual:</strong>
                    <span id="stockActual" class="ms-2"></span>
                </div>
                <div class="form-group">
                    <label for="cantidadStock" class="form-label">Cantidad a Modificar:</label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" onclick="ajustarCantidad(-1)">-</button>
                        <input type="number" class="form-control text-center" id="cantidadStock" 
                               placeholder="Ingrese la cantidad">
                        <button class="btn btn-outline-secondary" type="button" onclick="ajustarCantidad(1)">+</button>
                    </div>
                    <small class="form-text text-muted">Use valores negativos para restar stock</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarSumarStock()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Agregar esta función para mantener la búsqueda
function mantenerBusqueda() {
    const query = document.getElementById('buscador').value;
    const categoria = document.getElementById('filtroCategoria').value;
    
    if (query) {
        localStorage.setItem('ultimaBusqueda', query);
    }
    
    if (categoria) {
        localStorage.setItem('ultimaCategoria', categoria);
    }
}

// Agregar al inicio del script
let imageFiles = new DataTransfer();

document.getElementById('nuevasImagenes').addEventListener('change', function(e) {
    console.log('Evento change activado para nuevasImagenes');
    console.log('Archivos seleccionados:', e.target.files);
    
    // Mostrar loader si hay archivos seleccionados
    if (e.target.files && e.target.files.length > 0) {
        document.getElementById('loaderContainer').classList.add('active');
    }
    
    const previewContainer = document.getElementById('previewNuevasImagenes');
    
    // Agregar nuevos archivos al DataTransfer
    Array.from(e.target.files).forEach(file => {
        // Verificar si es una imagen
        if (!file.type.startsWith('image/')) {
            mostrarMensaje('Por favor, seleccione solo archivos de imagen', 'danger');
            return;
        }
        
        // Verificar tamaño (máximo 10MB por imagen)
        if (file.size > 10 * 1024 * 1024) {
            mostrarMensaje(`El archivo ${file.name} es demasiado grande. El tamaño máximo es 10MB.`, 'danger');
            return;
        }
        
        imageFiles.items.add(file);
    });
    
    // Actualizar el input con todos los archivos
    this.files = imageFiles.files;
    
    // Actualizar previsualizaciones
    actualizarPrevisualizaciones();
});

function actualizarPrevisualizaciones() {
    const previewContainer = document.getElementById('previewNuevasImagenes');
    previewContainer.innerHTML = '';
    
    let filesProcessed = 0;
    const totalFiles = imageFiles.files.length;
    
    if (totalFiles === 0) {
        // Si no hay archivos, ocultar el loader
        document.getElementById('loaderContainer').classList.remove('active');
        return;
    }
    
    Array.from(imageFiles.files).forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const previewDiv = document.createElement('div');
            previewDiv.className = 'position-relative d-inline-block me-2 mb-2';
            previewDiv.innerHTML = `
                <img src="${e.target.result}" 
                     alt="Preview ${index + 1}" 
                     style="height: 100px; width: 100px; object-fit: cover; border-radius: 4px;">
                <button type="button" 
                        class="btn btn-danger btn-sm position-absolute top-0 end-0"
                        onclick="eliminarPreview(${index})">×</button>
            `;
            previewContainer.appendChild(previewDiv);
            
            filesProcessed++;
            
            // Ocultar el loader cuando se han procesado todos los archivos
            if (filesProcessed === totalFiles) {
                document.getElementById('loaderContainer').classList.remove('active');
            }
        };
        
        reader.readAsDataURL(file);
    });
}

function eliminarPreview(index) {
    const newFiles = new DataTransfer();
    
    Array.from(imageFiles.files)
        .filter((_, i) => i !== index)
        .forEach(file => newFiles.items.add(file));
    
    imageFiles = newFiles;
    document.getElementById('nuevasImagenes').files = imageFiles.files;
    actualizarPrevisualizaciones();
}

function editarProducto(producto) {
    mantenerBusqueda();
    // Resetear imageFiles para nuevas imágenes
    imageFiles = new DataTransfer();
    document.getElementById('nuevasImagenes').value = '';
    document.getElementById('previewNuevasImagenes').innerHTML = '';
    
    document.getElementById('editId').value = producto.id;
    document.getElementById('editCodigo').value = producto.codigo;
    document.getElementById('editNombre').value = producto.nombre;
    document.getElementById('editCategoria').value = producto.categoria;
    document.getElementById('editColores').value = producto.colores || '';
    document.getElementById('editStock').value = producto.stock;
    document.getElementById('editPrecio').value = Math.floor(producto.precio);
    document.getElementById('editPrecioCompra').value = Math.floor(producto.precio_compra);
    document.getElementById('editEstado').value = producto.estado;
    
    // Mostrar imágenes actuales
    const imagenesDiv = document.getElementById('imagenesActuales');
    imagenesDiv.innerHTML = '';
    
    if (producto.imagenes) {
        const imagenes = producto.imagenes.split(',');
        imagenes.forEach((imagen, index) => {
            if (imagen) { // Verificar que la imagen no sea vacía
                const imgContainer = document.createElement('div');
                imgContainer.className = 'position-relative d-inline-block me-2 mb-2';
                imgContainer.innerHTML = `
                    <img src="${imagen}" alt="Imagen ${index+1}" style="width: 80px; height: 80px; object-fit: cover;" class="border rounded">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" 
                            style="font-size: 0.7rem; padding: 0.1rem 0.4rem;" 
                            onclick="eliminarImagen(${producto.id}, '${imagen}', this)">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                imagenesDiv.appendChild(imgContainer);
            }
        });
    } else {
        imagenesDiv.innerHTML = '<p class="text-muted mb-0">No hay imágenes asociadas a este producto.</p>';
    }
    
    // Configurar manejadores de eventos para los botones de imágenes
    const btnTakePhoto = document.getElementById('btnTakePhoto');
    btnTakePhoto.onclick = function() {
        const input = document.getElementById('nuevasImagenes');
        input.removeAttribute('multiple');
        input.setAttribute('capture', 'environment');
        input.click();
    };
    
    const btnSelectFiles = document.getElementById('btnSelectFiles');
    btnSelectFiles.onclick = function() {
        const input = document.getElementById('nuevasImagenes');
        input.setAttribute('multiple', '');
        input.removeAttribute('capture');
        input.click();
    };
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('editarModal'));
    modal.show();
}

function cambiarEstadoProducto(id, nuevoEstado, event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Guardar la posición actual de desplazamiento
    const scrollPosition = window.scrollY;
    mantenerBusqueda();
    
    // Obtener el token CSRF (añadido como meta tag en la página)
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    fetch('administrar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=cambiarEstado&id=${id}&estado=${nuevoEstado}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const btn = event.target;
            const cardBody = btn.closest('.card-body');
            const estadoSpan = cardBody.querySelector('.estado-texto');
            
            if (nuevoEstado == 1) {
                btn.textContent = 'Desactivar';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-warning');
                estadoSpan.textContent = 'Activo';
                btn.setAttribute('onclick', `cambiarEstadoProducto(${id}, 0, event)`);
            } else {
                btn.textContent = 'Activar';
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-success');
                estadoSpan.textContent = 'Inactivo';
                btn.setAttribute('onclick', `cambiarEstadoProducto(${id}, 1, event)`);
            }
            
            // Mostrar notificación de éxito
            Swal.fire({
                title: 'Estado actualizado',
                text: 'El estado del producto ha sido actualizado correctamente',
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            
            // Restaurar la posición de desplazamiento
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 100);
        } else {
            // Mostrar error
            Swal.fire({
                title: 'Error',
                text: 'Hubo un problema al actualizar el estado del producto',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'Hubo un problema al comunicarse con el servidor',
            icon: 'error'
        });
    });
    
    return false;
}

function eliminarImagen(productoId, rutaImagen, boton) {
    if (confirm('¿Estás seguro de que quieres eliminar esta imagen?')) {
        // Obtener el token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch('eliminar_imagen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken
            },
            body: `producto_id=${productoId}&ruta_imagen=${encodeURIComponent(rutaImagen)}&csrf_token=${csrfToken}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Eliminar solo el contenedor de la imagen específica
                boton.closest('.position-relative').remove();
                
                // Mostrar notificación de éxito
                Swal.fire({
                    title: 'Imagen eliminada',
                    text: 'La imagen ha sido eliminada correctamente',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } else {
                // Mostrar error
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo eliminar la imagen',
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Hubo un problema al comunicarse con el servidor',
                icon: 'error'
            });
        });
    }
}

// Modificar el evento del buscador
const buscador = document.getElementById('buscador');
buscador.addEventListener('input', 
    function() {
        const query = this.value;
        fetch(`buscar_productos.php?q=${encodeURIComponent(query)}&pagina=1`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('contenedorProductos').innerHTML = html;
            })
            .catch(error => {
                console.error('Error en la búsqueda:', error);
                mostrarNotificacion('Error al realizar la búsqueda', 'error');
            });
    }
);

// Funciones de scroll
function mostrarOcultarBoton() {
    const btn = document.getElementById("btnVolverArriba");
    if (!btn) return; // Evitar error si el botón no existe
    
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        btn.style.display = "block";
    } else {
        btn.style.display = "none";
    }
}

function volverArriba() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Agregar listener de scroll
window.addEventListener('scroll', mostrarOcultarBoton);

// Agregar listener al botón
document.addEventListener('DOMContentLoaded', function() {
    const btnVolverArriba = document.getElementById('btnVolverArriba');
    if (btnVolverArriba) {
        btnVolverArriba.addEventListener('click', volverArriba);
    }
});

// Función para manejar la paginación
function buscarPagina(pagina) {
    // Mantener la posición actual
    buscarProductos(pagina, true);
}

// Función para buscar productos
function buscarProductos(pagina = 1, mantenerPosicion = false) {
    const query = document.getElementById('buscador').value;
    const categoria = document.getElementById('filtroCategoria').value;
    
    // Guardar la posición actual de desplazamiento si se requiere
    let scrollPosition = 0;
    if (mantenerPosicion) {
        scrollPosition = window.scrollY;
    }
    
    // Mostrar indicador de carga
    document.getElementById('contenedorProductos').innerHTML = `
        <div class="d-flex justify-content-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;
    
    fetch(`buscar_productos.php?q=${encodeURIComponent(query)}&categoria=${encodeURIComponent(categoria)}&pagina=${pagina}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenedorProductos').innerHTML = html;
            
            // Restaurar la posición de desplazamiento si es necesario
            if (mantenerPosicion) {
                setTimeout(() => {
                    window.scrollTo(0, scrollPosition);
                }, 100);
            }
        })
        .catch(error => {
            document.getElementById('contenedorProductos').innerHTML = `
                <div class="alert alert-danger">
                    Error al cargar los productos. Por favor, intente nuevamente.
                </div>
            `;
            console.error('Error en la búsqueda:', error);
        });
}

// Agregar evento para limpiar el valor al recargar
window.addEventListener('beforeunload', function() {
    const buscador = document.getElementById('buscador');
    const filtroCategoria = document.getElementById('filtroCategoria');
    
    // Solo eliminamos si no hay una búsqueda ni un filtro seleccionado
    if (!buscador.value.trim() && !filtroCategoria.value) {
        localStorage.removeItem('ultimaBusqueda');
        localStorage.removeItem('ultimaCategoria');
    }
});

// Modificar el evento submit del formulario
document.getElementById('formEditar').addEventListener('submit', function(e) {
    e.preventDefault();
    console.log('Formulario enviado');
    
    // Guardar la posición actual de desplazamiento
    const scrollPosition = window.scrollY;
    
    const formData = new FormData(this);
    
    // Asegurarnos de que el campo 'actualizar' esté incluido
    if (!formData.has('actualizar')) {
        formData.append('actualizar', '1');
    }
    
    // Debug de datos
    console.log('Enviando datos:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('editarModal'));
    
    // Mostrar el loader antes de iniciar la petición
    document.getElementById('loaderContainer').classList.add('active');
    
    fetch('actualizar_producto.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta recibida', response);
        return response.json();
    })
    .then(data => {
        // Ocultar el loader al recibir la respuesta
        document.getElementById('loaderContainer').classList.remove('active');
        
        console.log('Datos recibidos', data);
        if (data.success) {
            // Cerrar el modal
            modal.hide();
            
            // Resetear imageFiles para futuras operaciones
            imageFiles = new DataTransfer();
            
            // Mostrar mensaje de éxito
            Swal.fire({
                title: 'Producto actualizado',
                text: 'El producto ha sido actualizado correctamente',
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            
            // Actualizar la vista preservando elementos
            actualizarFilaProducto(data.producto);
            
            // Restaurar la posición de desplazamiento
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 100);
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error || 'Error al actualizar el producto',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        // Ocultar el loader en caso de error
        document.getElementById('loaderContainer').classList.remove('active');
        
        console.error('Error completo:', error);
        mostrarMensaje('Error al procesar la solicitud', 'danger');
    });
});

// Mantener solo la versión mejorada
function mostrarMensaje(mensaje, tipo) {
    // Crear el contenedor de notificaciones si no existe
    let notificacionesContainer = document.getElementById('notificacionesContainer');
    if (!notificacionesContainer) {
        notificacionesContainer = document.createElement('div');
        notificacionesContainer.id = 'notificacionesContainer';
        document.body.appendChild(notificacionesContainer);
    }

    // Crear la notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    // Agregar icono según el tipo
    const icono = tipo === 'success' ? '✓' : tipo === 'danger' ? '✕' : 'ℹ';
    
    notificacion.innerHTML = `
        <div class="notificacion-icono">${icono}</div>
        <div class="notificacion-mensaje">${mensaje}</div>
    `;
    
    // Agregar al contenedor
    notificacionesContainer.appendChild(notificacion);
    
    // Animar entrada
    setTimeout(() => notificacion.classList.add('show'), 10);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        notificacion.classList.remove('show');
        setTimeout(() => notificacion.remove(), 300);
    }, 3000);
}

// Mantener solo los estilos mejorados
const notificacionesStyle = document.createElement('style');
notificacionesStyle.textContent = `
    #notificacionesContainer {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .notificacion {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(120%);
        transition: transform 0.3s ease;
        min-width: 300px;
        max-width: 400px;
    }

    .notificacion.show {
        transform: translateX(0);
    }

    .notificacion-icono {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        margin-right: 12px;
        font-size: 14px;
    }

    .notificacion.success {
        border-left: 4px solid #28a745;
    }

    .notificacion.danger {
        border-left: 4px solid #dc3545;
    }

    .notificacion.success .notificacion-icono {
        background: #28a745;
        color: white;
    }

    .notificacion.danger .notificacion-icono {
        background: #dc3545;
        color: white;
    }

    .notificacion-mensaje {
        color: #333;
        font-size: 14px;
        line-height: 1.4;
    }

    @media (max-width: 576px) {
        #notificacionesContainer {
            left: 20px;
            right: 20px;
        }

        .notificacion {
            min-width: 0;
            width: 100%;
        }
    }
`;
document.head.appendChild(notificacionesStyle);

// Variables para el modal de suma de stock
let productoIdActual = null;
let stockActual = 0;
let currentScrollPosition = 0;

// Función para abrir el modal de sumar stock
function sumarStock(id, nombre) {
    // Guardar la posición actual de desplazamiento
    currentScrollPosition = window.scrollY;
    mantenerBusqueda();
    
    productoIdActual = id;
    document.getElementById('productoNombre').textContent = nombre;
    document.getElementById('cantidadStock').value = "1"; // Valor por defecto
    
    // Obtener el stock actual
    fetch(`sumar_stock.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                stockActual = data.stock;
                document.getElementById('stockActual').textContent = stockActual;
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.error || 'No se pudo obtener el stock actual',
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Hubo un problema al comunicarse con el servidor',
                icon: 'error'
            });
        });
    
    const modal = new bootstrap.Modal(document.getElementById('sumarStockModal'));
    modal.show();
}

// Función para ajustar la cantidad del stock
function ajustarCantidad(delta) {
    const input = document.getElementById('cantidadStock');
    let valor = parseInt(input.value || 0);
    valor += delta;
    input.value = valor;
}

// Función para confirmar y procesar la suma de stock
function confirmarSumarStock() {
    if (!productoIdActual) return;
    
    const cantidad = parseInt(document.getElementById('cantidadStock').value || 0);
    if (cantidad === 0) {
        Swal.fire({
            title: 'Advertencia',
            text: 'Debe ingresar una cantidad diferente de cero',
            icon: 'warning'
        });
        return;
    }
    
    // Obtener el token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Enviar los datos al servidor
    fetch('sumar_stock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': csrfToken
        },
        body: `id=${productoIdActual}&cantidad=${cantidad}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('sumarStockModal')).hide();
            
            // Mostrar mensaje de éxito
            Swal.fire({
                title: 'Stock actualizado',
                text: `El stock se ha actualizado correctamente a ${data.nuevoStock} unidades`,
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            
            // Actualizar el stock en la interfaz sin recargar toda la página
            actualizarStockProducto(productoIdActual, data.nuevoStock);
            
            // Restaurar la posición de desplazamiento
            setTimeout(() => {
                window.scrollTo(0, currentScrollPosition);
            }, 100);
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error || 'No se pudo actualizar el stock',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'Hubo un problema al comunicarse con el servidor',
            icon: 'error'
        });
    });
}

// Función para actualizar sólo el stock de un producto en la interfaz
function actualizarStockProducto(productoId, nuevoStock) {
    // Buscar la tarjeta del producto
    const productCard = document.querySelector(`.card[data-id="${productoId}"]`);
    if (!productCard) return;
    
    // Actualizar el stock visible
    const stockElement = productCard.querySelector('.stock-producto');
    if (stockElement) {
        stockElement.textContent = nuevoStock;
    }
}

function confirmarEliminar(id, nombre) {
    mantenerBusqueda();
    
    // Primero verificar si el producto tiene ventas
    fetch(`verificar_ventas_producto.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.tiene_ventas) {
                // Producto con ventas: mostrar confirmación especial
                Swal.fire({
                    title: '¡Atención! Producto con historial de ventas',
                    html: `El producto "${nombre}" tiene ventas registradas en el historial.<br><br>
                          <strong>¿Qué desea hacer?</strong><br>
                          - Si elimina el producto, también se eliminarán TODAS sus ventas del historial.<br>
                          - Esta acción NO puede deshacerse.`,
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    denyButtonColor: '#ffc107',
                    confirmButtonText: 'Sí, eliminar todo',
                    denyButtonText: 'Cancelar',
                    cancelButtonText: 'Volver'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Eliminar producto y sus ventas
                        const query = document.getElementById('buscador').value;
                        const categoria = document.getElementById('filtroCategoria').value;
                        window.location.href = `eliminar_producto.php?id=${id}&confirmar_ventas=true&redirect=buscar&q=${encodeURIComponent(query)}&categoria=${encodeURIComponent(categoria)}`;
                    }
                });
            } else {
                // Producto sin ventas: confirmación normal
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `¿Realmente deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const query = document.getElementById('buscador').value;
                        const categoria = document.getElementById('filtroCategoria').value;
                        window.location.href = `eliminar_producto.php?id=${id}&redirect=buscar&q=${encodeURIComponent(query)}&categoria=${encodeURIComponent(categoria)}`;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // En caso de error, mostrar confirmación básica
            Swal.fire({
                title: '¿Estás seguro?',
                text: `¿Realmente deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const query = document.getElementById('buscador').value;
                    const categoria = document.getElementById('filtroCategoria').value;
                    window.location.href = `eliminar_producto.php?id=${id}&redirect=buscar&q=${encodeURIComponent(query)}&categoria=${encodeURIComponent(categoria)}`;
                }
            });
        });
}

// Asegurarse que todos los formularios incluyan el token CSRF
document.addEventListener('DOMContentLoaded', function() {
    // Obtener el token CSRF del meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Agregar encabezado X-CSRF-TOKEN a todas las peticiones fetch
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        // Si es una petición POST, asegurarse de incluir el token CSRF
        if (options.method === 'POST' && options.headers) {
            if (options.headers['Content-Type'] === 'application/x-www-form-urlencoded') {
                // Si ya existe un body, asegurarse de incluir el token CSRF
                if (options.body && !options.body.includes('csrf_token')) {
                    options.body += `&csrf_token=${csrfToken}`;
                }
            }
        }
        return originalFetch(url, options);
    };
    
    // Asegurarse que el formulario de edición incluya el token CSRF
    document.getElementById('formEditar').addEventListener('submit', function(e) {
        const csrfInput = this.querySelector('input[name="csrf_token"]');
        if (!csrfInput) {
            e.preventDefault();
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = csrfToken;
            this.appendChild(input);
            this.submit();
        }
    });
    
    // Añadir event listener para el cambio del filtro de categoría
    document.getElementById('filtroCategoria').addEventListener('change', function() {
        mantenerBusqueda();
        buscarProductos(1);
    });
    
    // Cargar el valor guardado del filtro de categoría (si existe)
    const categoriaGuardada = localStorage.getItem('ultimaCategoria');
    if (categoriaGuardada) {
        const filtroCategoria = document.getElementById('filtroCategoria');
        // Verificar si existe la opción
        const options = Array.from(filtroCategoria.options);
        const existe = options.some(option => option.value === categoriaGuardada);
        
        if (existe) {
            filtroCategoria.value = categoriaGuardada;
            // Ejecutar búsqueda con el filtro guardado
            buscarProductos(1);
        } else {
            // Si la categoría ya no existe, limpiar el localStorage
            localStorage.removeItem('ultimaCategoria');
        }
    }
    
    // Limpiar imageFiles cuando se cierra el modal de edición
    document.getElementById('editarModal').addEventListener('hidden.bs.modal', function() {
        imageFiles = new DataTransfer();
        document.getElementById('nuevasImagenes').value = '';
        document.getElementById('previewNuevasImagenes').innerHTML = '';
    });
});

// Agregar esta nueva función para actualizar fila de producto sin recargar
function actualizarFilaProducto(producto) {
    // Buscar la tarjeta del producto
    const productCard = document.querySelector(`.card[data-id="${producto.id}"]`);
    if (!productCard) {
        // Si no encontramos la tarjeta, actualizamos toda la página
        buscarProductos(1, true);
        return;
    }
    
    // Función para formatear números en formato argentino
    const formatoPesosArg = (numero, decimales = 0) => {
        return numero.toLocaleString('es-AR', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    };
    
    // Actualizar datos en la tarjeta
    productCard.querySelector('.card-title').textContent = producto.nombre;
    
    // Actualizar categoría
    const categoriaElement = productCard.querySelector('.categoria-producto');
    if (categoriaElement) {
        categoriaElement.textContent = producto.categoria;
    }
    
    // Actualizar precio
    const precioElement = productCard.querySelector('.precio-producto');
    if (precioElement) {
        precioElement.textContent = formatoPesosArg(parseInt(producto.precio));
    }
    
    // Actualizar stock
    const stockElement = productCard.querySelector('.stock-producto');
    if (stockElement) {
        stockElement.textContent = producto.stock;
    }
    
    // Actualizar código
    const codigoElement = productCard.querySelector('.codigo-producto');
    if (codigoElement) {
        codigoElement.textContent = producto.codigo;
    }
}

// Definir Web Worker para optimización de imágenes en segundo plano
const imageOptimizerWorkerCode = `
    self.onmessage = async function(e) {
        const { file, id, maxDimension, quality } = e.data;
        
        try {
            // Convertir el archivo a un ArrayBuffer para procesarlo
            const arrayBuffer = await file.arrayBuffer();
            const bitmap = await createImageBitmap(new Blob([arrayBuffer]));
            
            // Calcular nuevas dimensiones
            let width = bitmap.width;
            let height = bitmap.height;
            
            if (width > height && width > maxDimension) {
                height = Math.round(height * (maxDimension / width));
                width = maxDimension;
            } else if (height > maxDimension) {
                width = Math.round(width * (maxDimension / height));
                height = maxDimension;
            }
            
            // Crear canvas offscreen para procesamiento
            const canvas = new OffscreenCanvas(width, height);
            const ctx = canvas.getContext('2d');
            ctx.drawImage(bitmap, 0, 0, width, height);
            
            // Liberar bitmap para ahorrar memoria
            bitmap.close();
            
            // Obtener el blob comprimido
            const blob = await canvas.convertToBlob({
                type: 'image/jpeg',
                quality: quality
            });
            
            // Enviar resultado de vuelta al hilo principal
            self.postMessage({
                id: id,
                status: 'success',
                result: blob,
                originalSize: arrayBuffer.byteLength,
                optimizedSize: blob.size,
                fileName: file.name
            });
        } catch (error) {
            self.postMessage({
                id: id,
                status: 'error',
                error: error.message,
                fileName: file.name
            });
        }
    };
`;

// Crear Blob URL para el worker
const workerBlob = new Blob([imageOptimizerWorkerCode], { type: 'application/javascript' });
const workerUrl = URL.createObjectURL(workerBlob);

// Pool de workers para optimización paralela
class ImageOptimizerPool {
    constructor(poolSize = 4) {
        this.workers = [];
        this.queue = [];
        this.activeJobs = 0;
        this.maxJobs = poolSize;
        this.callbacks = new Map();
        
        // Inicializar pool de workers
        for (let i = 0; i < poolSize; i++) {
            const worker = new Worker(workerUrl);
            worker.onmessage = this.handleWorkerMessage.bind(this);
            this.workers.push({
                worker: worker,
                busy: false
            });
        }
    }
    
    handleWorkerMessage(e) {
        const { id, status, result, originalSize, optimizedSize, fileName, error } = e.data;
        const callback = this.callbacks.get(id);
        
        if (callback) {
            if (status === 'success') {
                // Crear archivo optimizado con el blob recibido
                const optimizedFile = new File([result], fileName, {
                    type: 'image/jpeg',
                    lastModified: Date.now()
                });
                
                callback.resolve(optimizedFile);
                console.log(`Optimización: ${fileName} - Original: ${(originalSize/1024/1024).toFixed(2)}MB, Optimizado: ${(optimizedSize/1024/1024).toFixed(2)}MB [${Math.round((optimizedSize/originalSize)*100)}%]`);
            } else {
                callback.reject(new Error(error || 'Error desconocido en optimización'));
            }
            
            this.callbacks.delete(id);
        }
        
        // Marcar worker como disponible
        const workerIndex = this.workers.findIndex(w => w.worker === e.target);
        if (workerIndex !== -1) {
            this.workers[workerIndex].busy = false;
            this.activeJobs--;
            
            // Procesar siguiente trabajo en cola
            this.processQueue();
        }
    }
    
    processQueue() {
        // Mientras haya trabajos en cola y workers disponibles
        while (this.queue.length > 0 && this.activeJobs < this.maxJobs) {
            const availableWorker = this.workers.find(w => !w.busy);
            if (!availableWorker) break;
            
            const job = this.queue.shift();
            availableWorker.busy = true;
            this.activeJobs++;
            
            // Enviar trabajo al worker
            availableWorker.worker.postMessage({
                file: job.file,
                id: job.id,
                maxDimension: job.maxDimension || 1200,
                quality: job.quality || 0.7
            });
        }
    }
    
    optimizeImage(file, options = {}) {
        return new Promise((resolve, reject) => {
            const jobId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Guardar callbacks para este trabajo
            this.callbacks.set(jobId, { resolve, reject });
            
            // Añadir trabajo a la cola
            this.queue.push({
                id: jobId,
                file: file,
                maxDimension: options.maxDimension || 1200,
                quality: options.quality || 0.7
            });
            
            // Intentar procesar la cola
            this.processQueue();
        });
    }
    
    terminate() {
        // Limpiar todos los workers al finalizar
        this.workers.forEach(w => w.worker.terminate());
        URL.revokeObjectURL(workerUrl);
    }
}

// Instanciar pool de optimización
const imageOptimizerPool = new ImageOptimizerPool(
    navigator.hardwareConcurrency ? Math.min(navigator.hardwareConcurrency, 4) : 2
);

// Función mejorada para optimización de imágenes
async function optimizeImageClient(file, progressCallback) {
    try {
        // Si el archivo ya es pequeño (<500KB), evitar optimizaciones innecesarias
        if (file.size < 500 * 1024) {
            if (progressCallback) progressCallback(100);
            return file;
        }
        
        if (progressCallback) progressCallback(30);
        
        // Usar el pool de optimización para procesar la imagen
        const optimizedFile = await imageOptimizerPool.optimizeImage(file, {
            // Ajustar calidad según el tamaño original para equilibrar calidad/tamaño
            quality: file.size > 5 * 1024 * 1024 ? 0.6 : 0.7
        });
        
        if (progressCallback) progressCallback(100);
        return optimizedFile;
    } catch (error) {
        console.error(`Error optimizando ${file.name}:`, error);
        if (progressCallback) progressCallback(100, true);
        return file; // Devolver original en caso de error
    }
}

// Mejorar el evento change del input de imágenes en el modal
document.getElementById('editImagenes').addEventListener('change', async function(e) {
    const files = Array.from(e.target.files);
    
    if (files.length > 0) {
        // Mostrar loader
        const loader = document.getElementById('editLoader');
        loader.style.display = 'block';
        
        // Mostrar mensaje de optimización
        updateLoaderMessage(`Preparando ${files.length} ${files.length === 1 ? 'imagen' : 'imágenes'}...`);
        
        // Limpiar el DataTransfer object
        editImageFiles = new DataTransfer();
        
        // Procesar lotes de archivos para mejor rendimiento
        const BATCH_SIZE = 3; // Procesar de 3 en 3 para equilibrar velocidad y rendimiento
        
        for (let i = 0; i < files.length; i += BATCH_SIZE) {
            const batch = files.slice(i, i + BATCH_SIZE);
            
            // Procesar el lote actual
            const optimizationPromises = batch.map(async (file, batchIndex) => {
                const index = i + batchIndex;
                
                // Verificar tipo de archivo
                if (!file.type.startsWith('image/')) {
                    mostrarMensaje('Por favor, seleccione solo archivos de imagen', 'danger');
                    return null;
                }
                
                // Verificar tamaño
                if (file.size > 20 * 1024 * 1024) {
                    mostrarMensaje(`El archivo ${file.name} es demasiado grande. El tamaño máximo es 20MB.`, 'danger');
                    return null;
                }
                
                try {
                    // Actualizar mensaje con progreso de optimización
                    updateLoaderMessage(`Optimizando imagen ${index+1} de ${files.length}...`);
                    
                    // Optimizar imagen con seguimiento de progreso
                    const optimizedFile = await optimizeImageClient(file, (progress, error) => {
                        if (!error) {
                            updateLoaderMessage(`Optimizando imagen ${index+1} de ${files.length}: ${progress}%`);
                        }
                    });
                    
                    return optimizedFile;
                } catch (error) {
                    console.error(`Error al optimizar ${file.name}:`, error);
                    return file; // Usar archivo original si falla
                }
            });
            
            // Esperar a que se complete el lote actual
            const optimizedBatch = await Promise.all(optimizationPromises);
            
            // Agregar archivos optimizados al DataTransfer
            optimizedBatch.filter(file => file !== null).forEach(file => {
                editImageFiles.items.add(file);
            });
            
            // Actualizar el indicador de progreso
            const processedCount = Math.min((i + BATCH_SIZE), files.length);
            updateLoaderMessage(`Procesadas ${processedCount} de ${files.length} imágenes...`);
        }
        
        // Actualizar el input con los archivos procesados
        this.files = editImageFiles.files;
        
        // Actualizar previsualizaciones
        updateLoaderMessage(`Generando previsualizaciones...`);
        await updateEditImagePreviews();
        
        // Ocultar loader
        loader.style.display = 'none';
    }
});

// Función mejorada para actualizar previsualizaciones
async function updateEditImagePreviews() {
    const container = document.getElementById('editImagePreviewContainer');
    container.innerHTML = '';
    
    const files = Array.from(editImageFiles.files);
    const totalFiles = files.length;
    
    if (totalFiles === 0) {
        return;
    }
    
    // Crear un observador de intersección para lazy loading avanzado
    const lazyImageObserver = 'IntersectionObserver' in window ? 
        new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const lazyImage = entry.target;
                    const dataSrc = lazyImage.getAttribute('data-src');
                    
                    if (dataSrc) {
                        // Carga progresiva con blurhash/placeholder
                        lazyImage.style.filter = 'blur(5px)';
                        lazyImage.src = dataSrc;
                        
                        lazyImage.onload = () => {
                            // Desvanecimiento suave del blur al cargar
                            lazyImage.style.transition = 'filter 0.3s ease-out';
                            lazyImage.style.filter = 'blur(0)';
                            
                            // Liberar recursos
                            URL.revokeObjectURL(dataSrc);
                            lazyImage.removeAttribute('data-src');
                            
                            // Dejar de observar
                            observer.unobserve(lazyImage);
                        };
                    }
                }
            });
        }, {
            rootMargin: '200px 0px', // Precarga antes de que sea visible
            threshold: 0.01
        }) : null;
    
    // Usar DocumentFragment para minimizar reflows
    const fragment = document.createDocumentFragment();
    
    // Generar thumbnails de baja resolución para vista previa instantánea
    const thumbnailPromises = files.map(async (file, index) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4';
        
        const previewContainer = document.createElement('div');
        previewContainer.className = 'preview-container';
        
        // Crear imagen con placeholder y lazy loading
        const img = document.createElement('img');
        img.className = 'preview-image';
        img.loading = 'lazy';
        
        // Placeholder gris mientras carga
        img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
        
        // Crear URL para lazy loading
        const objectUrl = URL.createObjectURL(file);
        img.setAttribute('data-src', objectUrl);
        
        // Observar para lazy loading si el navegador lo soporta
        if (lazyImageObserver) {
            lazyImageObserver.observe(img);
        } else {
            // Fallback para navegadores sin IntersectionObserver
            img.src = objectUrl;
            img.onload = () => URL.revokeObjectURL(objectUrl);
        }
        
        // Botón de eliminar
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-image';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = (e) => {
            e.preventDefault();
            removeEditImage(index);
        };
        
        // Etiqueta de información con tamaño
        const infoLabel = document.createElement('div');
        infoLabel.className = 'image-info';
        infoLabel.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
        
        // Agregar elementos al contenedor
        previewContainer.appendChild(img);
        previewContainer.appendChild(removeBtn);
        previewContainer.appendChild(infoLabel);
        col.appendChild(previewContainer);
        fragment.appendChild(col);
        
        return new Promise(resolve => {
            // Resolver inmediatamente - el loading real se hace con IntersectionObserver
            resolve();
        });
    });
    
    // Esperar a que se generen todos los thumbnails
    await Promise.all(thumbnailPromises);
    
    // Añadir todo al DOM de una sola vez
    container.appendChild(fragment);
}

// Función para actualizar mensajes en el loader
function updateLoaderMessage(message) {
    // Buscar o crear el elemento para el mensaje
    let loaderMessage = document.getElementById('loaderMessage');
    
    if (!loaderMessage) {
        loaderMessage = document.createElement('div');
        loaderMessage.id = 'loaderMessage';
        loaderMessage.className = 'loader-message';
        
        // Insertar después del elemento loader
        const loader = document.querySelector('#loaderContainer .loader');
        if (loader && loader.parentNode) {
            loader.parentNode.insertBefore(loaderMessage, loader.nextSibling);
        }
    }
    
    loaderMessage.textContent = message;
}
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<!-- Elemento de carga para subida de archivos -->
<div class="loader-container" id="loaderContainer">
    <div class="loader"></div>
</div>