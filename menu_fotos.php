<?php
require_once 'db.php';
require_once 'cache.php';
require_once 'includes/security_headers.php';
require_once 'includes/secure_logger.php';

// Aplicar headers de seguridad
apply_security_headers();

checkPermission();

// Log acceso al catálogo
log_app('catalog_access', 'INFO', [
    'user_id' => $_SESSION['user_id'] ?? 'unknown',
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Agregar headers para optimización
header("Cache-Control: public, max-age=31536000");

include 'includes/header.php';

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

$cache = new Cache();

// Configuración de paginación
$registrosPorPagina = 15; // Aumentado de 12 a 15 productos por página
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Consulta para contar total de registros
$cache_key = 'total_productos_activos';
$totalRegistros = $cache->get($cache_key);

if ($totalRegistros === false) {
    $sqlCount = "SELECT COUNT(*) as total FROM productos WHERE estado = 1";
    $resultCount = $conn->query($sqlCount);
    $totalRegistros = $resultCount->fetch_assoc()['total'];
    $cache->set($cache_key, $totalRegistros);
}

$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta principal con caché
$cache_key = "productos_pagina_{$paginaActual}";
$productos = $cache->get($cache_key);

if ($productos === false) {
    $sql = "SELECT p.*, 
            (SELECT ruta_imagen 
             FROM imagenes_producto i 
             WHERE i.producto_id = p.id 
             ORDER BY i.id 
             LIMIT 1) as imagen_principal 
            FROM productos p 
            WHERE p.estado = 1 
            ORDER BY p.fecha_creacion DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $registrosPorPagina, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $cache->set($cache_key, $productos);
}

?>

<!-- Buscador encima de todo -->
<div class="container-fluid container-xxl mt-4">
    <!-- Contenedor de búsqueda y filtros dividido en dos secciones -->
    <div class="row mb-3">
        <!-- Sección de buscador - Ocupa más espacio en pantallas grandes -->
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
        
        <!-- Sección de filtros - Ocupa menos espacio en pantallas grandes -->
        <div class="col-md-4">
            <div class="filters-container d-flex">
                <select id="filtroCategoria" class="form-select filter-select">
                    <option value="">📑 Todas las categorías</option>
                    <?php
                    // Obtener categorías únicas de la base de datos
                    $sqlCategorias = "SELECT DISTINCT categoria FROM productos WHERE estado = 1 ORDER BY categoria";
                    $resultCategorias = $conn->query($sqlCategorias);
                    while($rowCategoria = $resultCategorias->fetch_assoc()) {
                        echo '<option value="' . htmlspecialchars($rowCategoria['categoria']) . '">' . 
                             '🏷️ ' . htmlspecialchars($rowCategoria['categoria']) . 
                             '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Catálogo de productos - ahora más ancho (col-md-8) -->
        <div class="col-md-8 order-2 order-md-1">
            <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 g-2" id="productosGrid">
                <?php include 'buscar_productos_menu_fotos.php'; ?>
            </div>
        </div>
        
        <!-- Carrito de compras - ahora más estrecho (col-md-4) -->
        <div class="col-md-4 order-1 order-md-2" id="carritoContainer">
            <!-- Botón para mostrar carrito en móvil -->
            <button class="btn btn-primary d-lg-none btn-carrito" type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#carritoCollapse"
                    aria-expanded="false">
                <i class="fas fa-shopping-cart"></i>
                <span class="badge bg-light text-dark" id="totalItemsBadge">0</span>
            </button>

            <!-- Overlay para cerrar al tocar fuera -->
            <div class="carrito-overlay d-lg-none"></div>

            <!-- Contenedor del carrito con collapse para móvil -->
            <div class="collapse d-lg-block" id="carritoCollapse">
                <div class="card sticky-top">
                <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Lista de Compras</h5>
                            <button type="button" 
                                    class="btn-close-carrito d-lg-none" 
                                    onclick="cerrarCarrito()"
                                    aria-label="Cerrar">
                                <i class="fas fa-times fa-lg"></i>
                            </button>
                        </div>
                </div>
                <div class="card-body">
                    <div class="carrito-items" id="carritoItems">
                        <!-- Los items se agregarán aquí dinámicamente -->
                    </div>
                    <div class="carrito-resumen">
                        <div class="resumen-item">
                            <span><i class="fas fa-box"></i> Total Items:</span>
                            <span id="totalItems">0</span>
                        </div>
                        <div class="resumen-item">
                            <span><i class="fas fa-receipt"></i> Subtotal:</span>
                            <span id="totalPrecio">$0</span>
                        </div>
                        <div class="resumen-item total">
                            <span class="iva-text"><i class="fas fa-calculator"></i> Total :</span>
                            <span id="totalConIVA">$0</span>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="incluirIVA" onchange="actualizarCarritoUI()">
                        <label class="form-check-label" for="incluirIVA">
                            <i class="fas fa-percentage"></i> Incluir Recargo (3.5%)
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="incluirIVA21" onchange="actualizarCarritoUI()">
                        <label class="form-check-label" for="incluirIVA21">
                            <i class="fas fa-percentage"></i> Incluir IVA (21%)
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="incluirEnvio" onchange="toggleCampoEnvio()">
                        <label class="form-check-label" for="incluirEnvio">
                            <i class="fas fa-truck"></i> Incluir Cadete
                        </label>
                        <div id="campoEnvioContainer" style="display: none; margin-top: 8px;">
                            <input type="text" class="form-control" id="valorEnvio" 
                                   placeholder="Valor del envío" 
                                   value="5000"
                                   oninput="formatearNumero(this)"
                                   onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                   onchange="actualizarCarritoUI()">
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-procesar-venta" onclick="procesarVenta()" title="Atajo: Enter">
                            <i class="fas fa-check-circle"></i>
                            Procesar Venta
                            <small class="keyboard-shortcut">⏎ Enter</small>
                        </button>
                        <button class="btn btn-limpiar-lista" onclick="limpiarCarrito()" title="Atajo: Ctrl + L">
                            <i class="fas fa-trash-alt"></i>
                            Limpiar Lista
                            <small class="keyboard-shortcut">Ctrl + L</small>
                        </button>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button id="btnVolverArriba" class="btn btn-primary btn-floating" onclick="volverArriba()">
    <i class="fas fa-arrow-up"></i>
</button>

<style>
/* Base styles */
:root {
    --navbar-height: 60px;
    --carrito-width: 340px;
    --primary-color: #007bff;
    --hover-color: #0056b3;
    --bg-light: #f8f9fa;
    --bg-dark: #1a1a1a;
    --text-light: #212529;
    --text-dark: #ffffff;
    --border-light: rgba(0,0,0,0.1);
    --border-dark: rgba(255,255,255,0.1);
    --card-bg-light: #ffffff;
    --card-bg-dark: #2d2d2d;
    --input-bg-light: #ffffff;
    --input-bg-dark: #2d2d2d;
    --shadow-light: rgba(0,0,0,0.1);
    --shadow-dark: rgba(0,0,0,0.3);
}

/* Modo oscuro */
[data-theme="dark"] {
    background-color: var(--bg-dark);
    color: var(--text-dark);
}

[data-theme="dark"] .card {
    background-color: var(--card-bg-dark);
    border-color: var(--border-dark);
}

[data-theme="dark"] .search-container input {
    background-color: var(--input-bg-dark);
    border-color: var(--border-dark);
    color: var(--text-dark);
}

[data-theme="dark"] .search-container input::placeholder {
    color: rgba(255,255,255,0.6);
}

[data-theme="dark"] .search-icon {
    color: rgba(255,255,255,0.6);
}

[data-theme="dark"] .filter-select {
    background-color: var(--input-bg-dark);
    border-color: var(--border-dark);
    color: var(--text-dark);
}

[data-theme="dark"] .catalog-title {
    color: var(--text-dark);
}

[data-theme="dark"] .carrito-item {
    background-color: var(--card-bg-dark);
    border-color: var(--border-dark);
}

[data-theme="dark"] .item-codigo {
    color: rgba(255,255,255,0.6);
}

[data-theme="dark"] .carrito-resumen {
    background-color: rgba(255,255,255,0.05);
}

[data-theme="dark"] .resumen-item {
    color: rgba(255,255,255,0.8);
}

[data-theme="dark"] .form-check-label {
    color: rgba(255,255,255,0.8);
}

/* Mejoras en el buscador */
.search-container {
    position: relative;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: var(--input-bg-light);
    box-shadow: 0 2px 8px var(--shadow-light);
}

.search-container input {
    padding: 14px 20px;
    padding-right: 45px;
    border-radius: 12px;
    border: 1px solid var(--border-light);
    font-size: 1rem;
    width: 100%;
    transition: all 0.3s ease;
    background-color: transparent;
}

.search-container:focus-within {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px var(--shadow-light);
}

.search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 1.1rem;
    pointer-events: none;
}

/* Mejoras en el filtro de categorías */
.filters-container {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px var(--shadow-light);
    height: 100%;
    background: var(--input-bg-light);
}

.filter-select {
    height: 100%;
    padding: 14px 20px;
    border: none;
    background-color: transparent;
    font-size: 1rem;
    cursor: pointer;
    width: 100%;
    color: var(--text-light);
}

/* Ajustes para el modo oscuro en el modal de método de pago */
[data-theme="dark"] #metodoPagoModal .modal-content {
    background-color: var(--card-bg-dark);
    border-color: var(--border-dark);
}

[data-theme="dark"] #metodoPagoModal .modal-title {
    color: var(--text-dark);
}

[data-theme="dark"] .metodo-pago-btn {
    background-color: var(--input-bg-dark);
    border-color: var(--border-dark);
    color: var(--text-dark);
}

[data-theme="dark"] .metodo-pago-btn:hover {
    background-color: rgba(255,255,255,0.1);
}

/* Mejoras en las tarjetas de productos */
[data-theme="dark"] .producto-card {
    background-color: var(--card-bg-dark);
    border-color: var(--border-dark);
}

[data-theme="dark"] .producto-nombre {
    color: var(--text-dark);
}

[data-theme="dark"] .precio {
    color: var(--text-dark);
}

[data-theme="dark"] .categoria {
    color: rgba(255,255,255,0.6);
}

/* Ajustes para el scroll en modo oscuro */
[data-theme="dark"] ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

[data-theme="dark"] ::-webkit-scrollbar-track {
    background: var(--bg-dark);
}

[data-theme="dark"] ::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
}

[data-theme="dark"] ::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}

/* Estilos para ambas columnas */
@media (min-width: 768px) {
    /* Contenedor principal */
    .container-xxl {
        max-width: 100%;
        padding-left: 30px;
        padding-right: 30px;
    }
    
    /* Ajustes para el buscador */
    .col-12.mb-3 {
        padding: 0 15px;
        margin-bottom: 20px !important;
    }
    
    /* Mantener el carrito visible en pantallas medianas y grandes */
    #carritoCollapse {
        display: block !important;
        height: auto !important;
        visibility: visible !important;
    }
    
    /* Ajuste para el área de productos */
    .order-md-1, .order-md-2 {
        margin-bottom: 30px;
    }
    
    /* Mejora visual del carrito */
    .carrito-items {
        padding: 10px 5px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    /* Ajuste de tamaño del catálogo y carrito */
    .col-md-8 {
        padding-right: 25px;
    }
    
    .col-md-4 {
        padding-left: 15px;
    }
}

/* Estilos para móvil */
@media (max-width: 767.98px) {
    /* Reordenar para que el catálogo aparezca primero en móvil */
    .order-1 {
        order: 2;
    }
    
    .order-2 {
        order: 1;
    }
    
    /* Carrito flotante en móvil */
    .btn-carrito {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1030;
        border-radius: 50%;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    #carritoCollapse {
        position: fixed;
        top: var(--navbar-height);
        right: 0;
        bottom: 0;
        width: 100%;
        max-width: 100%;
        z-index: 1050;
        transform: translateX(100%);
        transition: transform 0.3s ease-out;
        padding: 0;
        margin: 0;
        border-radius: 0;
        box-shadow: -2px 0 5px rgba(0,0,0,0.1);
    }
    
    #carritoCollapse.show {
        transform: translateX(0);
    }

    .carrito-overlay {
        position: fixed;
        top: var(--navbar-height);
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
        display: none;
    }
    
    .carrito-overlay.show {
        display: block;
    }
}

/* Estilos para PC - Ajustes para la proporción 70/30 */
@media (min-width: 992px) {
    .container-xxl {
        padding-left: 40px;
        padding-right: 40px;
    }
    
    /* Ajustes específicos para el catálogo */
    .col-md-8 {
        flex: 0 0 70%;
        max-width: 70%;
        padding-right: 30px;
    }
    
    /* Ajustes específicos para el carrito */
    .col-md-4 {
        flex: 0 0 30%;
        max-width: 30%;
        padding-left: 15px;
    }
}

/* Estilos compartidos para todos los tamaños */
.catalog-title {
    font-size: 1.6rem;
    margin-top: 0;
    margin-bottom: 15px;
    color: #212529;
    font-weight: 600;
}

.card {
    border: 1px solid rgba(0,0,0,0.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-radius: 8px;
    margin-bottom: 15px;
}

.card-header {
    border-radius: 8px 8px 0 0;
    padding: 12px 16px;
}

/* Mantener estilos existentes del carrito */
.carrito-item {
    background: white;
    border-radius: 8px;
    padding: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 10px;
    transition: all 0.2s ease;
}

.carrito-item:hover {
    transform: translateX(5px);
    background-color: #f8f9fa;
}

/* Ajustes del buscador */
.search-container {
    position: relative;
    border-radius: 30px;
    transition: all 0.3s ease;
}

.search-container:focus-within {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.search-container input {
    padding: 12px 20px;
    padding-right: 40px;
    border-radius: 30px;
    border: 1px solid #ced4da;
    font-size: 0.95rem;
    width: 100%;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background-color: #f8f9fa;
}

.search-container input:focus {
    border-color: #adb5bd;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    outline: none;
    background-color: #fff;
}

.search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

/* Configuración para tarjetas de productos más pequeñas */
.g-2 {
    --bs-gutter-y: 0.5rem;
    --bs-gutter-x: 0.5rem;
}

/* Ajustes específicos para las tarjetas de productos */
.producto-card {
    margin-bottom: 0.5rem !important;
    transition: all 0.2s;
}

.producto-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.producto-card .card-body {
    padding: 0.75rem !important;
}

.producto-imagen {
    height: 150px !important; /* Altura reducida para las imágenes */
    object-fit: cover;
    transition: all 0.3s;
}

.producto-card .carousel:hover .producto-imagen {
    filter: brightness(1.05);
}

.producto-card h5 {
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.producto-card .precio {
    font-size: 1.1rem;
    font-weight: 600;
}

.producto-card .categoria,
.producto-card .codigo {
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.producto-card .stock-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
}

.producto-card .btn-agregar {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s;
    width: 100%;
    margin-top: 0.5rem;
    background-color: #2c3e50;
    color: white;
    border: none;
}

.producto-card .btn-agregar:hover {
    background-color: #1a252f;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.producto-card .btn-agotado {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    width: 100%;
    margin-top: 0.5rem;
    background-color: #e9ecef;
    color: #6c757d;
    border: none;
    cursor: not-allowed;
}

/* Mejorar controles del carrusel */
.carousel-control-prev, .carousel-control-next {
    opacity: 0.7;
    width: 15%;
}

.carousel-control-prev-icon, .carousel-control-next-icon {
    width: 1.5rem;
    height: 1.5rem;
}

/* Responsive para pantallas muy pequeñas */
@media (max-width: 375px) {
    .row-cols-2 > * {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Comportamiento responsivo del grid por tamaños */
@media (min-width: 576px) {
    .row-cols-sm-3 > * {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (min-width: 992px) {
    .row-cols-lg-4 > * {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

@media (min-width: 1200px) {
    .row-cols-xl-5 > * {
        flex: 0 0 20%;
        max-width: 20%;
    }
}

/* Estilos para PC - Mover el carrito más a la derecha */
@media (min-width: 992px) {
    body {
        overflow-x: hidden; /* Prevenir scroll horizontal */
    }
    
    .container-xxl {
        max-width: 100%;
        padding-left: 40px;
        padding-right: 40px;
        width: 100%;
    }
    
    /* Ajuste para que el buscador ocupe el ancho correcto */
    .col-12.col-lg-8 {
        padding-right: 20px;
        padding-left: 40px;
        max-width: calc(100% - 400px);
        margin-bottom: 0 !important;
    }
    
    /* Cambiar distribución en pantalla completa */
    .row {
        margin-right: 0;
        margin-left: 0;
        display: flex;
        flex-wrap: wrap;
        width: 100%;
    }
    
    /* Ajuste del carrito solo en modo PC */
    .col-md-5.col-lg-4 {
        padding-left: 20px;
        margin-left: auto;
        width: 400px;
        flex: 0 0 400px;
        padding-right: 40px;
    }
    
    /* Ajuste para el área de productos */
    .col-md-7.col-lg-8 {
        padding-right: 20px;
        padding-left: 40px;
        flex: 1;
        max-width: calc(100% - 400px);
        padding-top: 0;
    }
    
    /* Ajustes adicionales para el carrito */
    #carritoContainer {
        position: relative;
    }
    
    #carritoContainer .card {
        border: 1px solid rgba(0,0,0,0.1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-radius: 8px;
        width: 100%;
    }

    /* Asegurarse que el carrito siempre esté visible en PC */
    #carritoCollapse {
        display: block !important;
        height: auto !important;
        visibility: visible !important;
        transform: none !important;
        position: static !important;
        width: auto !important;
        max-width: none !important;
        box-shadow: none !important;
        background: transparent !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;
        z-index: 1 !important;
    }
    
    /* Ocultar elementos para móvil */
    .d-lg-none, 
    .carrito-overlay,
    .btn-close-carrito {
        display: none !important;
    }
    
    /* Estilo del encabezado del carrito en PC */
    #carritoContainer .card-header {
        border-radius: 8px 8px 0 0;
        padding: 12px 16px;
    }
    
    /* Mejora visual del carrito */
    .carrito-items {
        padding: 10px 5px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .carrito-item {
        transition: all 0.2s ease;
    }
    
    .carrito-item:hover {
        transform: translateX(5px);
        background-color: #f8f9fa;
    }
    
    /* Ajustar el grid de productos */
    #productosGrid {
        margin-right: 0;
    }
    
    /* Reducir el espacio superior del catálogo */
    .col-md-7.col-lg-8 h2 {
        margin-top: 0;
        margin-bottom: 12px;
    }
}

/* Estilos para móvil y tablet unificados */
@media (max-width: 991.98px) {
    /* Carrito flotante */
    .btn-carrito {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1030;
        border-radius: 50%;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    #carritoCollapse {
        position: fixed;
        top: var(--navbar-height);
        right: 0;
        bottom: 0;
        width: 100%;
        max-width: 400px;
        z-index: 1050;
        transform: translateX(100%);
        transition: transform 0.3s ease-out;
        padding: 0;
        margin: 0;
        border-radius: 0;
    }

    .carrito-overlay {
        position: fixed;
        top: var(--navbar-height);
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
        display: none;
    }

    /* Ajustes del header y contenido del carrito */
    #carritoCollapse .card-header {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #0d6efd;
        padding: 15px 75px 15px 20px;
        min-height: 60px;
        display: flex;
        align-items: center;
    }

    #carritoCollapse .card-body {
        padding: 1rem;
        height: calc(100vh - var(--navbar-height) - 60px);
        overflow-y: auto;
    }

    /* Botón volver arriba */
    #btnVolverArriba {
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 1030;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    /* Reducir espacios en dispositivos móviles */
    .col-12.col-lg-8 {
        margin-bottom: 10px;
    }
    
    .col-md-7.col-lg-8 h2 {
        margin-top: 0;
        margin-bottom: 12px;
        font-size: 1.5rem;
    }
}

/* Estilos adicionales para el carrito */
#carritoCollapse {
    background: white;
    max-height: 100vh;
    overflow-y: auto;
}

/* Ajustes para móvil y tablet */
@media (max-width: 991.98px) {
    #carritoCollapse.show {
        transform: translateX(0);
    }

    #carritoCollapse .card {
        border: none;
        border-radius: 0;
        height: 100%;
    }

    /* X del carrito */
    .btn-close-carrito {
        position: absolute;
        right: 15px;
        top: 15px;
        color: white;
        font-size: 24px;
        background: none;
        border: none;
        padding: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.9;
        transition: opacity 0.2s;
        z-index: 1061;
    }

    .btn-close-carrito:hover {
        opacity: 1;
    }

    .btn-close-carrito i {
        font-size: 24px;
    }

    /* Ajuste del header del carrito */
    #carritoCollapse .card-header h5 {
        margin: 0;
        font-size: 1.1rem;
    }
}

/* Estilos para la lista de compras */
.carrito-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.item-principal {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.item-codigo {
    color: #666;
    font-size: 0.85rem;
}

.item-nombre {
    flex: 1;
    font-weight: 500;
}

.btn-eliminar {
    background: none;
    border: none;
    color: #dc3545;
    padding: 0.25rem;
    cursor: pointer;
}

.item-detalles {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cantidad-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cantidad-control button {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.cantidad-control input {
    width: 40px;
    text-align: center;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 0.25rem;
}

.precio-info {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.precio-unitario {
    font-size: 0.85rem;
    color: #666;
}

.precio-total {
    font-weight: 500;
    color: #2c3e50;
}

.carrito-resumen {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.resumen-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    color: #666;
    font-size: 0.95rem;
}

.resumen-item i {
    margin-right: 8px;
    width: 16px;
    color: #2c3e50;
}

.resumen-item.total {
    border-top: 1px solid #dee2e6;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    font-weight: 500;
    color: #2c3e50;
}

.form-check-label {
    color: #666;
    font-size: 0.9rem;
}

.form-check-label i {
    color: #2c3e50;
    margin-right: 5px;
}

.form-check-input:checked {
    background-color: #2c3e50;
    border-color: #2c3e50;
}

/* Ajustes específicos para móviles pequeños */
@media (max-width: 576px) {
    .input-cantidad {
        width: 60px !important;
    }
}

/* Ajustes específicos para pantallas pequeñas */
@media (max-width: 360px) {
    #btnVolverArriba {
        width: 40px;
        height: 40px;
        bottom: 15px;
        left: 15px;
    }

    .btn-carrito {
        width: 48px;
        height: 48px;
        bottom: 15px;
        right: 15px;
    }

    .btn-close-carrito {
        width: 35px;
        height: 35px;
        font-size: 20px;
    }
}

/* Estilos para el modal */
.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
}

.modal-content {
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: none;
    padding: 1.5rem 1.5rem 0.5rem;
}

.modal-body {
    padding: 1rem 1.5rem 1.5rem;
}

.btn-lg {
    padding: 1rem;
    font-size: 1.1rem;
    border-radius: 10px;
}

.btn-outline-primary:hover {
    transform: translateY(-2px);
    transition: transform 0.2s;
}

.bi {
    font-size: 1.2rem;
}

/* Estilos adicionales */
.filters-container {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    height: 100%;
}

.filter-select {
    flex-grow: 1;
    border: none;
    box-shadow: none;
    font-size: 0.95rem;
    border-radius: 8px;
}

.filter-button {
    white-space: nowrap;
    transition: all 0.3s ease;
}

.filter-button:hover {
    background-color: var(--primary-color);
    color: white;
}

/* Adaptación para móviles */
@media (max-width: 768px) {
    .filter-button {
        padding: 0.375rem 0.5rem;
    }
    
    .filter-button i {
        margin-right: 0 !important;
    }
    
    .filter-button span {
        display: none;
    }
}

/* Estilos mejorados para los botones del carrito */
.btn-procesar-venta {
    background: linear-gradient(45deg, #28a745, #20c997);
    border: none;
    color: white;
    padding: 12px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-procesar-venta:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.btn-limpiar-lista {
    background: linear-gradient(45deg, #dc3545, #e74c3c);
    border: none;
    color: white;
    padding: 12px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-limpiar-lista:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

/* Estilos para el modal de método de pago */
#metodoPagoModal .modal-dialog {
    max-width: 400px;
}

#metodoPagoModal .modal-content {
    background: #f8f9fa;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

#metodoPagoModal .modal-header {
    padding: 1.5rem 1.5rem 0.5rem;
}

#metodoPagoModal .modal-title {
    font-size: 1.25rem;
    color: #2c3e50;
}

#metodoPagoModal .btn-close {
    background-size: 0.8rem;
    opacity: 0.5;
}

#metodoPagoModal .modal-body {
    padding: 1rem 1.5rem 1.5rem;
}

.metodo-pago-btn {
    background: white;
    border: 1px solid #e9ecef;
    padding: 1rem;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    color: #495057;
}

.metodo-pago-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-color: #dee2e6;
    background: white;
}

.metodo-pago-btn i {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
}

.metodo-pago-btn span {
    font-size: 0.9rem;
    font-weight: 500;
}

/* Colores específicos para cada método */
.metodo-pago-btn:nth-child(1) i { color: #2ecc71; }
.metodo-pago-btn:nth-child(2) i { color: #3498db; }
.metodo-pago-btn:nth-child(3) i { color: #9b59b6; }
.metodo-pago-btn:nth-child(4) i { color: #e67e22; }

/* Estilos para los atajos de teclado */
.keyboard-shortcut {
    font-size: 0.75rem;
    opacity: 0.7;
    background: rgba(255, 255, 255, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 8px;
    font-family: monospace;
}

/* Mejoras en los botones */
.btn-procesar-venta, .btn-limpiar-lista {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-procesar-venta:hover, .btn-limpiar-lista:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

/* Modo oscuro para los botones */
[data-theme="dark"] .btn-procesar-venta {
    background: linear-gradient(45deg, #2ecc71, #27ae60);
}

[data-theme="dark"] .btn-limpiar-lista {
    background: linear-gradient(45deg, #e74c3c, #c0392b);
}

[data-theme="dark"] .keyboard-shortcut {
    background: rgba(255, 255, 255, 0.15);
}

/* Estilos generales */
body {
    min-height: 100vh;
    padding-bottom: 60px;
}

.catalog-title {
    margin-bottom: 1rem;
    font-weight: 600;
    color: var(--text-color);
}

/* Estilos de las tarjetas de producto */
.card {
    height: 100%;
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

[data-theme="dark"] .card:hover {
    background-color: var(--card-bg);
    box-shadow: 0 5px 15px rgba(255,255,255,0.1);
}

/* Estilos para la lista de compras */
#carritoContainer {
    position: relative;
}

.carrito-item {
    margin-bottom: 10px;
    transition: all 0.2s ease;
}

[data-theme="dark"] .carrito-item:hover {
    background-color: var(--card-bg) !important;
}

/* Lista de compras en móvil */
@media (max-width: 767px) {
    /* ... existing code ... */
}

/* Ajustes para modo claro/oscuro */
[data-theme="light"] .card {
    border-color: rgba(0,0,0,0.125);
}

[data-theme="dark"] .card {
    border-color: var(--border-color);
}

[data-theme="dark"] .list-group-item {
    background-color: var(--card-bg);
    border-color: var(--border-color);
    color: var(--text-color);
}

[data-theme="dark"] .list-group-item-action:hover {
    background-color: rgba(255,255,255,0.05);
}

[data-theme="dark"] .card-header {
    border-bottom-color: var(--border-color);
}

/* Corregir contrastes en botones para ambos temas */
[data-theme="dark"] .btn-light {
    background-color: #495057;
    border-color: #495057;
    color: #e9ecef;
}

[data-theme="dark"] .btn-light:hover {
    background-color: #545b62;
    border-color: #545b62;
    color: #e9ecef;
}

[data-theme="dark"] .badge.bg-light.text-dark {
    background-color: #343a40 !important;
    color: #f8f9fa !important;
}

/* Mejorar visibilidad de números y totales */
#totalItems, #subtotal, #totalConIVA {
    transition: all 0.3s ease;
}

[data-theme="dark"] #totalItems strong,
[data-theme="dark"] #subtotal strong,
[data-theme="dark"] #totalConIVA strong {
    color: #fff;
}

/* Mejoras específicas para el modo oscuro */
[data-theme="dark"] .form-control {
    background-color: var(--input-bg);
    color: #f8f9fa;
    border-color: #495057;
}

[data-theme="dark"] .form-control:focus {
    background-color: #2c3338;
    color: #ffffff;
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

[data-theme="dark"] .form-control::placeholder {
    color: #adb5bd;
}

/* Mejorar contraste en la lista de compras */
[data-theme="dark"] .card-body {
    color: var(--text-color);
}

[data-theme="dark"] #listaCompras .carrito-item {
    background-color: var(--card-bg);
    border-color: var(--border-color);
}

[data-theme="dark"] #listaCompras .carrito-item:hover {
    background-color: #3a4147 !important;
}

/* Mejorar visibilidad de precios en la lista */
[data-theme="dark"] .precio-individual,
[data-theme="dark"] .precio-total,
[data-theme="dark"] #totalItems,
[data-theme="dark"] #subtotal,
[data-theme="dark"] #totalConIVA {
    color: #e9ecef;
}

/* Iconos en lista de compras */
[data-theme="dark"] i.fas, 
[data-theme="dark"] i.far, 
[data-theme="dark"] i.fa {
    color: #e9ecef;
}

/* Mejoras para el checkbox del IVA */
[data-theme="dark"] .form-check-label {
    color: #e9ecef;
}

[data-theme="dark"] .form-check-input {
    background-color: #343a40;
    border-color: #495057;
}

[data-theme="dark"] .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

/* Botón de búsqueda en modo oscuro */
[data-theme="dark"] .input-group .btn {
    background-color: #495057;
    border-color: #495057;
    color: #f8f9fa;
}

[data-theme="dark"] .input-group .btn:hover {
    background-color: #5a6268;
    border-color: #5a6268;
}

/* Mejorar visibilidad de totales con iconos */
[data-theme="dark"] #totalItems,
[data-theme="dark"] #subtotal,
[data-theme="dark"] #totalConIVA {
    color: #f8f9fa !important;
}

/* Resaltar texto de los precios */
[data-theme="dark"] .precio-unitario, 
[data-theme="dark"] .precio-total {
    font-weight: 500;
    color: #f8f9fa;
}

/* Mejora en los modales para modo oscuro */
[data-theme="dark"] .modal-content {
    background-color: var(--card-bg);
    color: var(--text-color);
    border-color: var(--border-color);
}

[data-theme="dark"] .modal-header {
    border-bottom-color: var(--border-color);
}

[data-theme="dark"] .modal-footer {
    border-top-color: var(--border-color);
}

/* Consistencia en los bordes de las tarjetas */
[data-theme="dark"] .card {
    border: 1px solid var(--border-color);
}

[data-theme="dark"] .card-header,
[data-theme="dark"] .card-footer {
    background-color: rgba(0, 0, 0, 0.2);
    border-color: var(--border-color);
}

/* Estilos específicos para los precios */
.precio-unitario, .precio-total {
    font-weight: 500;
    transition: color 0.3s ease;
}

[data-theme="light"] .precio-unitario, 
[data-theme="light"] .precio-total {
    color: #212529;
}

[data-theme="dark"] .precio-unitario, 
[data-theme="dark"] .precio-total {
    color: #f8f9fa;
}

/* Totales en el carrito */
#totalItems, #subtotal, #totalConIVA {
    padding: 8px 0;
    transition: color 0.3s ease;
}

[data-theme="light"] #totalItems strong,
[data-theme="light"] #subtotal strong,
[data-theme="light"] #totalConIVA strong {
    color: #212529;
}

[data-theme="dark"] #totalItems strong,
[data-theme="dark"] #subtotal strong,
[data-theme="dark"] #totalConIVA strong {
    color: #ffffff;
}

/* Estilos para el campo de búsqueda */
#buscador {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

[data-theme="dark"] #buscador {
    background-color: var(--input-bg);
    color: #f8f9fa;
    border-color: #495057;
}

/* Ajustes para las tarjetas de producto */
[data-theme="dark"] .product-card {
    background-color: var(--card-bg);
    border-color: var(--border-color);
}

[data-theme="dark"] .product-card:hover {
    border-color: #0d6efd;
}

[data-theme="dark"] .product-card .card-title,
[data-theme="dark"] .product-card .card-text {
    color: var(--text-color);
}

/* Botón cerrar en modo oscuro */
[data-theme="dark"] .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Iconos dentro de la lista de compras */
.carrito-item i.fas,
.carrito-item i.far,
.carrito-item i.fa {
    transition: color 0.3s ease;
}

[data-theme="dark"] .carrito-item i.fas, 
[data-theme="dark"] .carrito-item i.far, 
[data-theme="dark"] .carrito-item i.fa {
    color: #f8f9fa;
}

/* Iconos junto a los totales */
#totalItems i, #subtotal i, #totalConIVA i {
    transition: color 0.3s ease;
}

[data-theme="dark"] #totalItems i,
[data-theme="dark"] #subtotal i,
[data-theme="dark"] #totalConIVA i {
    color: #f8f9fa;
}

/* Botones de cantidad */
[data-theme="dark"] .btn-quantity {
    background-color: #495057;
    border-color: #495057;
    color: #f8f9fa;
}

[data-theme="dark"] .btn-quantity:hover {
    background-color: #5a6268;
    border-color: #5a6268;
}

/* Selector de categorías */
[data-theme="dark"] .form-select {
    background-color: var(--input-bg);
    color: #f8f9fa;
    border-color: #495057;
}

[data-theme="dark"] .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Ajustes para los precios destacados */
.carrito-item .precio-total {
    font-weight: 600;
}

/* Corrección específica para el texto "Total con IVA (21%)" */
[data-theme="dark"] #totalConIVA {
    color: #f8f9fa !important;
}

[data-theme="dark"] #totalConIVA * {
    color: #f8f9fa !important;
}

/* Corregir también el elemento que contiene el texto del IVA */
[data-theme="dark"] .form-check-label {
    color: #f8f9fa !important;
}

/* Regla adicional para asegurar que todos los textos en el área de totales son visibles */
[data-theme="dark"] .totals-container p,
[data-theme="dark"] .totals-container div,
[data-theme="dark"] .totals-container span {
    color: #f8f9fa !important;
}

/* Corrección específica para el texto "Total con IVA (21%)" */
[data-theme="dark"] .iva-label {
    color: #f8f9fa !important;
}

[data-theme="dark"] #totalConIVA {
    color: #f8f9fa !important;
}

[data-theme="dark"] #totalConIVA strong {
    color: #f8f9fa !important;
}

/* Corrección específica para el texto "Total con IVA (21%)" */
[data-theme="dark"] .iva-text {
    color: #f8f9fa !important;
}

[data-theme="dark"] .iva-text i {
    color: #f8f9fa !important;
}

/* Asegurar que todos los textos en el resumen de compra son visibles */
[data-theme="dark"] .resumen-item,
[data-theme="dark"] .resumen-item span,
[data-theme="dark"] .resumen-item i {
    color: #f8f9fa !important;
}

[data-theme="dark"] .total {
    color: #f8f9fa !important;
}

/* Aplicar estos estilos con !important para asegurar que tengan prioridad */
[data-theme="dark"] #totalConIVA {
    color: #f8f9fa !important;
}

/* Garantizar visibilidad en modo oscuro para todos los textos */ 
[data-theme="dark"] body {
    color: #f8f9fa;
}

/* Estilos globales para el modo oscuro */
[data-theme="dark"] .card-body,
[data-theme="dark"] .card-header,
[data-theme="dark"] .card-footer,
[data-theme="dark"] .card-title,
[data-theme="dark"] .card-text,
[data-theme="dark"] p,
[data-theme="dark"] span,
[data-theme="dark"] div,
[data-theme="dark"] label,
[data-theme="dark"] h1, 
[data-theme="dark"] h2, 
[data-theme="dark"] h3, 
[data-theme="dark"] h4, 
[data-theme="dark"] h5, 
[data-theme="dark"] h6 {
    color: #f8f9fa;
}

/* Forzar colores específicos para elementos del resumen */
[data-theme="dark"] .resumen-compra * {
    color: #f8f9fa !important;
}

[data-theme="dark"] .resumen-item {
    color: #f8f9fa !important;
}

[data-theme="dark"] .resumen-item * {
    color: #f8f9fa !important;
}

/* Para elementos con iconos FontAwesome */
[data-theme="dark"] .fas,
[data-theme="dark"] .far,
[data-theme="dark"] .fa {
    color: #f8f9fa !important;
}

/* Eliminar todos los bordes en elementos de la interfaz */
.card,
.btn,
.input-group,
.form-control,
.form-select,
.list-group,
.list-group-item,
.dropdown-menu,
.dropdown-item,
input[type="search"],
input[type="text"],
button,
select {
    border: none !important;
    box-shadow: none !important;
}

/* Redondear buscador y eliminar bordes */
.input-group .form-control {
    border-radius: 50px 0 0 50px !important;
    border: none !important;
    background-color: var(--input-bg) !important;
}

.input-group .btn {
    border-radius: 0 50px 50px 0 !important;
    border: none !important;
    background-color: var(--input-bg) !important;
}

/* Asegurar que el buscador no tenga bordes */
#buscador {
    border: none !important;
    box-shadow: none !important;
    background-color: var(--input-bg) !important;
}

/* Eliminar bordes en las tarjetas de productos */
.card {
    border: none !important;
    background-color: var(--card-bg) !important;
}

/* Eliminar bordes en campos de formulario */
input, select, textarea, button {
    border: none !important;
    box-shadow: none !important;
}

/* Asegurarse que no haya bordes en hover */
*:hover, *:focus, *:active {
    border-color: transparent !important;
    box-shadow: none !important;
}



/* Asegurar que el checkbox del IVA sea visible */
#incluirIVA {
    opacity: 1 !important;
    position: static !important;
    width: auto !important;
    height: auto !important;
    margin-right: 8px !important;
    appearance: checkbox !important;
    -webkit-appearance: checkbox !important;
    -moz-appearance: checkbox !important;
    pointer-events: auto !important;
    visibility: visible !important;
    display: inline-block !important;
}

/* Arreglar el estilo para la etiqueta del checkbox */
.form-check-label {
    display: inline-block !important;
    vertical-align: middle !important;
    user-select: none !important;
    cursor: pointer !important;
}

/* Estilos específicos para el contenedor del checkbox */
.form-check {
    display: flex !important;
    align-items: center !important;
    margin-bottom: 1rem !important;
}

/* Eliminar TODOS los contornos y bordes en elementos de formulario */
select, .form-select, input, button, .form-control, .btn {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

select:focus, .form-select:focus, input:focus, button:focus, .form-control:focus, .btn:focus {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

/* Aplicar estilos específicos para la caja de búsqueda */
.form-control, .form-select {
    border: none !important;
    background-color: var(--input-bg) !important;
    color: var(--text-color) !important;
}

[data-theme="dark"] .form-control, 
[data-theme="dark"] .form-select,
[data-theme="dark"] #buscador {
    background-color: #2b3035 !important;
    color: #f8f9fa !important;
}

[data-theme="light"] .form-control, 
[data-theme="light"] .form-select,
[data-theme="light"] #buscador {
    background-color: #f8f9fa !important;
    color: #212529 !important;
}

/* Estilos específicos para el buscador principal */
.search-container {
    border-radius: 50px !important;
    overflow: hidden !important;
    background-color: var(--input-bg) !important;
}

.search-container #buscador {
    height: 45px !important;
    border: none !important;
    box-shadow: none !important;
    background-color: transparent !important;
    padding-left: 40px !important;
    font-size: 1rem !important;
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



/* Forzar colores específicos según tema */
[data-theme="dark"] .search-container,
[data-theme="dark"] #buscador,
[data-theme="dark"] #filtroCategoria {
    background-color: #2b3035 !important;
    color: #f8f9fa !important;
}

[data-theme="light"] .search-container,
[data-theme="light"] #buscador,
[data-theme="light"] #filtroCategoria {
    background-color: #f8f9fa !important;
    color: #212529 !important;
}

/* Reset completo de todos los elementos de formulario */
.form-control, .form-select, input, select, textarea, button,
.form-control:focus, .form-select:focus, input:focus, select:focus, textarea:focus, button:focus,
.form-control:hover, .form-select:hover, input:hover, select:hover, textarea:hover, button:hover {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}

/* Corregir el problema del checkbox */
.form-check-input[type="checkbox"] {
    opacity: 1 !important;
    visibility: visible !important;
    position: static !important;
    margin-right: 8px !important;
    width: 16px !important;
    height: 16px !important;
    cursor: pointer !important;
    display: inline-block !important;
    appearance: checkbox !important;
    -webkit-appearance: checkbox !important;
    -moz-appearance: checkbox !important;
}

/* Restaurar la estructura original del contenedor del checkbox */
.form-check {
    display: flex !important;
    align-items: center !important;
    margin-bottom: 1rem !important;
}

/* Asegurar que los contenedores checkbox de Bootstrap se muestren correctamente */
input[type="checkbox"].form-check-input {
    display: inline-block !important;
    margin-top: 0 !important;
    margin-right: 8px !important;
    position: relative !important;
}

/* Estilo para el checkbox solo en el modo oscuro */
[data-theme="dark"] .form-check-input[type="checkbox"] {
    background-color: #343a40 !important;
    border: 1px solid #6c757d !important;
}

[data-theme="dark"] .form-check-input[type="checkbox"]:checked {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
}

/* Modo claro para el checkbox */
[data-theme="light"] .form-check-input[type="checkbox"] {
    background-color: #fff !important;
    border: 1px solid #dee2e6 !important;
}

/* Estilos para los indicadores de conexión */
.connection-status {
    display: flex;
    align-items: center;
}

.connection-status .badge {
    display: flex;
    align-items: center;
    padding: 6px 10px;
    font-size: 0.85rem;
    border-radius: 12px;
}

.connection-status i {
    margin-right: 5px;
}

/* Animación para el indicador offline */
@keyframes pulseWarning {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

#offlineIndicator {
    animation: pulseWarning 2s infinite;
}



/* Estilos para los detalles de impuestos */
.impuestos-desglose {
    font-size: 0.8em;
    color: #666;
    margin-top: 5px;
    text-align: right;
}
.impuesto-detalle {
    margin-top: 2px;
}
</style>

<script>
let carrito = [];

function agregarAlCarrito(producto) {
    if (producto.stock <= 0) {
        alert('Producto sin stock disponible');
        return;
    }
    
    const itemExistente = carrito.find(item => item.id === producto.id);
    
    if (itemExistente) {
        if (itemExistente.cantidad < producto.stock) {
            itemExistente.cantidad++;
            itemExistente.total = itemExistente.cantidad * itemExistente.precio;
            mostrarNotificacion(`Se agregó otra unidad de ${producto.nombre}`);
        } else {
            alert('Stock insuficiente');
            return;
        }
    } else {
        carrito.push({
            ...producto,
            cantidad: 1,
            total: producto.precio
        });
        mostrarNotificacion(`${producto.nombre} agregado al carrito`);
    }
    
    actualizarCarritoUI();
}

function actualizarCarritoUI() {
    const tbody = document.getElementById('carritoItems');
    tbody.innerHTML = '';
    
    let totalItems = 0;
    let totalPrecio = 0;
    
    carrito.forEach(item => {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'carrito-item';
        itemDiv.innerHTML = `
            <div class="item-info">
                <div class="item-principal">
                    <span class="item-codigo">${item.codigo}</span>
                    <span class="item-nombre">${item.nombre}</span>
                    <button class="btn-eliminar" onclick="eliminarItem(${item.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="item-detalles">
                    <div class="cantidad-control">
                        <button onclick="cambiarCantidad(${item.id}, -1)">-</button>
                        <input type="number" value="${item.cantidad}" min="1" max="${item.stock}" 
                               onchange="cambiarCantidadEspecifica(${item.id}, this.value)">
                        <button onclick="cambiarCantidad(${item.id}, 1)">+</button>
                    </div>
                    <div class="precio-info">
                        <span class="precio-unitario">$${item.precio.toLocaleString('es-AR')}</span>
                        <span class="precio-total">$${item.total.toLocaleString('es-AR')}</span>
                    </div>
                </div>
            </div>
        `;
        tbody.appendChild(itemDiv);
        
        totalItems += item.cantidad;
        totalPrecio += item.total;
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalPrecio').textContent = `$${totalPrecio.toLocaleString('es-AR')}`;
    
    // Calcular total con recargos e impuestos
    let totalFinal = totalPrecio;
    let detalleImpuestos = '';
    
    // Aplicar recargo del 3.5% si está marcado
    if (document.getElementById('incluirIVA').checked) {
        const recargoTransaccion = Math.round(totalPrecio * 0.035);
        totalFinal += recargoTransaccion;
        detalleImpuestos += `<div class="impuesto-detalle">Recargo 3.5%: $${recargoTransaccion.toLocaleString('es-AR')}</div>`;
    }
    
    // Aplicar IVA 21% si está marcado
    if (document.getElementById('incluirIVA21').checked) {
        const iva21 = Math.round(totalPrecio * 0.21);
        totalFinal += iva21;
        detalleImpuestos += `<div class="impuesto-detalle">IVA 21%: $${iva21.toLocaleString('es-AR')}</div>`;
    }
    
    // Aplicar envío si está marcado
    if (document.getElementById('incluirEnvio').checked) {
        const valorEnvio = obtenerValorEnvio();
        if (valorEnvio > 0) {
            totalFinal += valorEnvio;
            detalleImpuestos += `<div class="impuesto-detalle">Envío: $${valorEnvio.toLocaleString('es-AR')}</div>`;
        }
    }
    
    // Mostrar el total con impuestos y detalle
    if (document.getElementById('incluirIVA').checked || document.getElementById('incluirIVA21').checked || 
        (document.getElementById('incluirEnvio').checked && obtenerValorEnvio() > 0)) {
        document.getElementById('totalConIVA').innerHTML = `$${totalFinal.toLocaleString('es-AR')}${detalleImpuestos ? '<div class="impuestos-desglose">' + detalleImpuestos + '</div>' : ''}`;
        document.getElementById('totalConIVA').style.display = 'block';
    } else {
        document.getElementById('totalConIVA').style.display = 'none';
    }

    actualizarContadorCarrito();
}

function cambiarCantidad(id, delta) {
    const item = carrito.find(item => item.id === id);
    if (item) {
        const nuevaCantidad = item.cantidad + delta;
        if (nuevaCantidad > 0 && nuevaCantidad <= item.stock) {
            item.cantidad = nuevaCantidad;
            item.total = item.cantidad * item.precio;
            actualizarCarritoUI();
        }
    }
}

function cambiarCantidadEspecifica(id, cantidad) {
    const item = carrito.find(item => item.id === id);
    if (item) {
        const nuevaCantidad = parseInt(cantidad);
        if (nuevaCantidad > 0 && nuevaCantidad <= item.stock) {
            item.cantidad = nuevaCantidad;
            item.total = item.cantidad * item.precio;
            actualizarCarritoUI();
        } else {
            alert(`Stock insuficiente. Solo hay ${item.stock} unidades disponibles.`);
            document.querySelector(`input[type="number"][value="${item.cantidad}"]`).value = item.cantidad;
        }
    }
}

function eliminarItem(id) {
    carrito = carrito.filter(item => item.id !== id);
    actualizarCarritoUI();
}

function limpiarCarrito() {
    carrito = [];
    actualizarCarritoUI();
    // Restaurar checkboxes a su estado inicial
    document.getElementById('incluirIVA').checked = false;
    document.getElementById('incluirIVA21').checked = false;
    document.getElementById('incluirEnvio').checked = false;
    document.getElementById('campoEnvioContainer').style.display = 'none';
    if (document.getElementById('valorEnvio')) {
        document.getElementById('valorEnvio').value = '5000';
        formatearNumero(document.getElementById('valorEnvio'));
    }
}

function procesarVenta() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    // Mostrar efecto visual (si existe la función)
    if (typeof showKeyPressEffect === 'function') {
        showKeyPressEffect('.btn-procesar-venta');
    }
    
    // Obtener los valores de los checkbox de impuestos y envío
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const incluirIVA21 = document.getElementById('incluirIVA21').checked;
    const incluirEnvio = document.getElementById('incluirEnvio').checked;
    const valorEnvio = incluirEnvio ? obtenerValorEnvio() : 0;
    
    // Usar el gestor offline para verificar la conexión
    offlineManager.procesarVenta(carrito, incluirIVA, incluirIVA21)
        .then(result => {
            if (!result.success) return;
            
            if (result.online) {
                // En modo online, mostrar el modal de método de pago directamente
                const modal = new bootstrap.Modal(document.getElementById('metodoPagoModal'));
                modal.show();
            } else {
                // En modo offline, mostrar selector simplificado
                Swal.fire({
                    title: 'Venta sin conexión',
                    text: 'Seleccione el método de pago para esta venta offline',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Efectivo',
                    cancelButtonText: 'Cancelar',
                    showDenyButton: true,
                    denyButtonText: 'Otro método'
                }).then((result) => {
                    if (result.isConfirmed) {
                        procesarVentaOffline('EFECTIVO', valorEnvio);
                    } else if (result.isDenied) {
                        mostrarSelectorMetodoPagoOffline(valorEnvio);
                    }
                });
            }
        });
}

// Función para procesar venta offline
function procesarVentaOffline(metodoPago, envio) {
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const incluirIVA21 = document.getElementById('incluirIVA21').checked;
    const ventaId = offlineManager.saveOfflineVenta(carrito, metodoPago, incluirIVA, incluirIVA21, envio);
    
    // Limpiar carrito
    carrito = [];
    actualizarCarritoUI();
    
    // Restaurar checkboxes a su estado inicial
    document.getElementById('incluirIVA').checked = false;
    document.getElementById('incluirIVA21').checked = false;
    document.getElementById('incluirEnvio').checked = false;
    document.getElementById('campoEnvioContainer').style.display = 'none';
    if (document.getElementById('valorEnvio')) {
        document.getElementById('valorEnvio').value = '5000';
        formatearNumero(document.getElementById('valorEnvio'));
    }
    
    // Mostrar mensaje de éxito
    mostrarMensaje('¡Venta guardada localmente! Se sincronizará cuando vuelva la conexión.', 'success');
}

// Función para mostrar selector de método de pago offline
function mostrarSelectorMetodoPagoOffline(valorEnvio) {
    Swal.fire({
        title: 'Seleccione método de pago',
        input: 'select',
        inputOptions: {
            'DÉBITO': 'Débito',
            'TRANSFERENCIA': 'Transferencia',
            'DEPOSITO': 'Depósito'
        },
        inputPlaceholder: 'Seleccione un método',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            return new Promise((resolve) => {
                if (value) {
                    resolve();
                } else {
                    resolve('Necesita seleccionar un método de pago');
                }
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            procesarVentaOffline(result.value, valorEnvio);
        }
    });
}

// Función para seleccionar método de pago (modo online)
function seleccionarMetodoPago(metodoPago) {
    // Ocultar modal de métodos de pago
    const modal = bootstrap.Modal.getInstance(document.getElementById('metodoPagoModal'));
    modal.hide();
    
    // Obtener los valores de los checkbox
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const incluirIVA21 = document.getElementById('incluirIVA21').checked;
    const incluirEnvio = document.getElementById('incluirEnvio').checked;
    const valorEnvio = incluirEnvio ? obtenerValorEnvio() : 0;
    
    // Procesar la venta directamente
    procesarVentaConDatos(metodoPago, valorEnvio);
}

// Función para procesar venta con los datos completos
function procesarVentaConDatos(metodoPago, envio) {
    // Mostrar efecto visual
    if (typeof showKeyPressEffect === 'function') {
        showKeyPressEffect('.btn-procesar-venta');
    }
    
    // Preparar datos de venta
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const incluirIVA21 = document.getElementById('incluirIVA21').checked;
    
    // Procesar artículos manteniendo los valores originales sin impuestos
    const itemsVenta = carrito.map(item => {
        return {
            ...item,
            iva: incluirIVA ? 1 : 0,
            iva21: incluirIVA21 ? 1 : 0,
            // Mantener el precio y total originales sin impuestos
            precio: item.precio,
            total: item.total
        };
    });
    
    // Enviar datos al servidor
    fetch('procesar_venta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
            items: itemsVenta,
            metodoPago: metodoPago,
            incluirIVA: incluirIVA,
            incluirIVA21: incluirIVA21,
            envio: envio
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Vaciar carrito
            carrito = [];
            actualizarCarritoUI();
            
            // Restaurar checkboxes a su estado inicial
            document.getElementById('incluirIVA').checked = false;
            document.getElementById('incluirIVA21').checked = false;
            document.getElementById('incluirEnvio').checked = false;
            document.getElementById('campoEnvioContainer').style.display = 'none';
            if (document.getElementById('valorEnvio')) {
                document.getElementById('valorEnvio').value = '5000';
                formatearNumero(document.getElementById('valorEnvio'));
            }
            
            // Abrir ticket directamente en nueva ventana
            window.open(`generar_ticket.php?transaccion_id=${data.transaccion_id}`, 'TicketVenta', 
                'width=400,height=600,resizable=yes,scrollbars=yes');
            
            // Mostrar mensaje de venta exitosa en la parte superior
            mostrarMensaje(`¡Venta completada con éxito! ID: ${data.transaccion_id}`, 'success');
        } else {
            // Mostrar error
            Swal.fire({
                title: 'Error',
                text: data.error || 'No se pudo procesar la venta',
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor. La venta se guardará en modo offline.',
            icon: 'warning',
            confirmButtonText: 'Entendido'
        }).then(() => {
            // Si falla la conexión, guardar como offline
            procesarVentaOffline(metodoPago, envio);
        });
    });
}

function mostrarMensajeClickeable(mensaje, tipo, url) {
    // Crear o obtener el contenedor de mensajes
    let mensajesContainer = document.getElementById('mensajesContainer');
    if (!mensajesContainer) {
        mensajesContainer = document.createElement('div');
        mensajesContainer.id = 'mensajesContainer';
        mensajesContainer.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: auto;
            min-width: 300px;
            max-width: 80%;
        `;
        document.body.appendChild(mensajesContainer);
    }

    // Determinar si estamos en modo oscuro
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo} alert-dismissible fade show cursor-pointer`;
    
    alertDiv.style.cssText = `
        cursor: pointer;
        margin-bottom: 10px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 8px;
        ${isDarkMode ? `
            background-color: ${tipo === 'success' ? '#28a745' : 
                               tipo === 'danger' ? '#dc3545' : 
                               tipo === 'warning' ? '#ffc107' : 
                               tipo === 'info' ? '#17a2b8' : '#343a40'};
            color: ${tipo === 'warning' ? '#212529' : '#ffffff'};
            border-color: ${tipo === 'success' ? '#238c3a' : 
                           tipo === 'danger' ? '#bd2d3b' : 
                           tipo === 'warning' ? '#d9a406' : 
                           tipo === 'info' ? '#148a9c' : '#495057'};
        ` : ''}
    `;
    
    alertDiv.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Si estamos en modo oscuro, aplicar estilo al botón de cierre
    if (isDarkMode) {
        setTimeout(() => {
            const btnClose = alertDiv.querySelector('.btn-close');
            if (btnClose) {
                btnClose.style.filter = 'invert(1) grayscale(100%) brightness(200%)';
            }
        }, 0);
    }
    
    alertDiv.onclick = function(e) {
        if (!e.target.classList.contains('btn-close')) {
            window.location.href = url;
        }
    };
    
    mensajesContainer.appendChild(alertDiv);

    // Remover después de 5 segundos
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

function buscarPagina(pagina) {
    const query = document.getElementById('buscador').value;
    const categoria = document.getElementById('filtroCategoria').value;
    
    fetch(`buscar_productos_menu_fotos.php?busqueda=${encodeURIComponent(query)}&categoria=${encodeURIComponent(categoria)}&pagina=${pagina}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('productosGrid').innerHTML = html;
            window.scrollTo(0, 0);
        });
}

// Unificar la búsqueda usando debounce
document.getElementById('buscador').addEventListener('input', 
    debounce(function() {
        buscarPagina(1);
    }, 300)
);

window.onscroll = function() {
    mostrarOcultarBoton();
};

function mostrarOcultarBoton() {
    const btn = document.getElementById("btnVolverArriba");
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

// Agregar esta función para las notificaciones
function mostrarNotificacion(mensaje) {
    // Crear el toast
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.textContent = mensaje;
    
    // Determinar si estamos en modo oscuro
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    // Elegir colores según el modo
    const bgColor = isDarkMode ? '#28a745' : '#28a745';
    const textColor = '#ffffff';
    const shadowColor = isDarkMode ? 'rgba(0,0,0,0.5)' : 'rgba(0,0,0,0.2)';
    
    // Agregar estilos al toast
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: ${bgColor};
        color: ${textColor};
        padding: 15px 25px;
        border-radius: 5px;
        box-shadow: 0 2px 5px ${shadowColor};
        z-index: 9999;
        animation: slideInTop 0.3s ease-out, fadeOut 0.5s ease-out 2s forwards;
        font-weight: bold;
        text-align: center;
        min-width: 250px;
    `;
    
    // Crear estilos para las animaciones si no existen
    if (!document.getElementById('toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slideInTop {
                from { transform: translate(-50%, -20px); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Agregar el toast al body
    document.body.appendChild(toast);
    
    // Remover el toast después de 2.5 segundos
    setTimeout(() => {
        toast.remove();
    }, 2500);
}

document.addEventListener('DOMContentLoaded', function() {
    const searchContainer = document.querySelector('.search-container');
    const searchInput = document.getElementById('buscador');
    
    // Usar media query en CSS en lugar de verificación de ancho
    searchInput.addEventListener('focus', function(e) {
        e.preventDefault();
        searchContainer.classList.add('is-typing');
        setTimeout(() => {
            window.scrollTo({
                top: window.pageYOffset
            });
        }, 0);
    });

    // Cerrar carrito al hacer clic fuera en móviles
    if (window.innerWidth <= 768) {
        document.addEventListener('click', function(e) {
            const carritoCollapse = document.getElementById('carritoCollapse');
            const carritoBtn = document.querySelector('[data-bs-target="#carritoCollapse"]');
            
            if (!carritoCollapse.contains(e.target) && !carritoBtn.contains(e.target)) {
                const bsCollapse = new bootstrap.Collapse(carritoCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });
    }

    const carritoCollapse = document.getElementById('carritoCollapse');
    const overlay = document.querySelector('.carrito-overlay');
    const btnCarrito = document.querySelector('[data-bs-target="#carritoCollapse"]');

    // Mostrar/ocultar overlay
    carritoCollapse.addEventListener('show.bs.collapse', function () {
        overlay.classList.add('show');
    });

    carritoCollapse.addEventListener('hide.bs.collapse', function () {
        overlay.classList.remove('show');
    });

    // Cerrar al tocar el overlay
    overlay.addEventListener('click', function() {
        bootstrap.Collapse.getInstance(carritoCollapse).hide();
    });

    // Evitar que el carrito se cierre al hacer clic dentro
    carritoCollapse.addEventListener('click', function(e) {
        if (!e.target.classList.contains('btn-close') && 
            !e.target.classList.contains('cerrar-carrito')) {
            e.stopPropagation();
        }
    });

    // Inicializar búsqueda
    const buscador = document.getElementById('buscador');
    const filtroCategoria = document.getElementById('filtroCategoria');
    
    function buscarProductos() {
        const busqueda = buscador.value.trim();
        const categoria = filtroCategoria.value;
        
        document.getElementById('productosGrid').innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        
        fetch('buscar_productos_menu_fotos.php?busqueda=' + encodeURIComponent(busqueda) + '&categoria=' + encodeURIComponent(categoria))
        .then(response => response.text())
        .then(data => {
            document.getElementById('productosGrid').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('productosGrid').innerHTML = '<div class="col-12 text-center py-5">Error al cargar productos. Intente nuevamente.</div>';
        });
    }
    
    buscador.addEventListener('input', debounce(buscarProductos, 500));
    filtroCategoria.addEventListener('change', buscarProductos);

    // Ajustar los elementos según el tema cuando cambie
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('change', function() {
            // Pequeña pausa para permitir que el tema cambie
            setTimeout(actualizarCarritoUI, 100);
        });
    }
});

// Actualizar el contador del carrito
function actualizarContadorCarrito() {
    const totalItems = carrito.reduce((sum, item) => sum + item.cantidad, 0);
    document.getElementById('totalItemsBadge').textContent = totalItems;
}

// Agregar función debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function verificarImagen(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(true);
        img.onerror = () => resolve(false);
        img.src = url;
    });
}

async function mostrarImagenProducto(url, nombre) {
    const existe = await verificarImagen(url);
    if (existe) {
        return `<img src="${url}" class="d-block w-100" style="height: 300px; object-fit: cover;" alt="${nombre}">`;
    } else {
        return `
            <div class="d-flex align-items-center justify-content-center bg-light" style="height: 300px;">
                <span class="text-secondary">
                    <i class="fas fa-image me-2"></i>Sin imagen disponible
                </span>
            </div>
        `;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Lazy loading de imágenes
    var lazyImages = [].slice.call(document.querySelectorAll("img.lazy"));

    if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.remove("lazy");
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });

        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    }
});

// Función para iniciar el sistema de actualización en tiempo real
function iniciarActualizacionTiempoReal() {
    let ultimoTimestamp = Date.now();
    
    // Verificar cambios cada 2 segundos
    setInterval(() => {
        fetch(`verificar_cambios.php?timestamp=${ultimoTimestamp}`)
            .then(response => response.json())
            .then(data => {
                if (data.hayCambios) {
                    // Actualizar el timestamp
                    ultimoTimestamp = data.timestamp;
                    
                    // Actualizar la vista
                    const query = document.getElementById('buscador').value;
                    return fetch(`buscar_productos_menu_fotos.php?q=${encodeURIComponent(query)}&pagina=1`);
                }
            })
            .then(response => response && response.text())
            .then(html => {
                if (html) {
                    document.getElementById('productosGrid').innerHTML = html;
                }
            })
            .catch(error => console.log('Error en la actualización:', error));
    }, 10000);
}

// Iniciar el sistema de actualización cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    iniciarActualizacionTiempoReal();
});

document.addEventListener('DOMContentLoaded', function() {
    // Referencias a elementos DOM
    const buscador = document.getElementById('buscador');
    const filtroCategoria = document.getElementById('filtroCategoria');
    
    // Función para buscar productos
    function buscarProductos() {
        const busqueda = buscador.value.trim();
        const categoria = filtroCategoria.value;
        
        // Mostrar indicador de carga
        document.getElementById('productosGrid').innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        
        // Realizar petición AJAX
        fetch('buscar_productos_menu_fotos.php?busqueda=' + encodeURIComponent(busqueda) + '&categoria=' + encodeURIComponent(categoria))
        .then(response => response.text())
        .then(data => {
            document.getElementById('productosGrid').innerHTML = data;
        })
        .catch(error => {
            console.error('Error en la búsqueda:', error);
            document.getElementById('productosGrid').innerHTML = '<div class="col-12 text-center py-5">Error al cargar productos. Intente nuevamente.</div>';
        });
    }
    
    // Event listeners
    buscador.addEventListener('input', function() {
        // Retraso para evitar muchas peticiones mientras el usuario escribe
        clearTimeout(buscador.timeoutId);
        buscador.timeoutId = setTimeout(buscarProductos, 500);
    });
    
    filtroCategoria.addEventListener('change', buscarProductos);
});

// Añadir manejadores de atajos de teclado
document.addEventListener('keydown', function(e) {
    // Procesar venta con Enter
    if (e.key === 'Enter' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
        const activeElement = document.activeElement;
        // Solo procesar si no estamos en un input o área de texto
        if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault();
            procesarVenta();
        }
    }
    
    // Limpiar carrito con Ctrl + L
    if (e.key === 'l' && e.ctrlKey && !e.altKey && !e.shiftKey) {
        e.preventDefault();
        limpiarCarrito();
    }
});

// Añadir efecto visual cuando se presiona una tecla
function showKeyPressEffect(buttonClass) {
    const button = document.querySelector(buttonClass);
    button.style.transform = 'scale(0.95)';
    setTimeout(() => {
        button.style.transform = '';
    }, 100);
}

// Modificar las funciones existentes para incluir el efecto visual
const originalProcesarVenta = procesarVenta;
procesarVenta = function() {
    showKeyPressEffect('.btn-procesar-venta');
    originalProcesarVenta();
};

const originalLimpiarCarrito = limpiarCarrito;
limpiarCarrito = function() {
    showKeyPressEffect('.btn-limpiar-lista');
    originalLimpiarCarrito();
};

// Sistema de ventas offline
let offlineVentas = [];
let isOnline = navigator.onLine;

// Al cargar el documento
document.addEventListener('DOMContentLoaded', function() {
    // Cargar ventas pendientes del almacenamiento local
    const pendingVentas = localStorage.getItem('offlineVentas');
    if (pendingVentas) {
        offlineVentas = JSON.parse(pendingVentas);
        
        // Si hay ventas pendientes y estamos online, intentar sincronizar
        if (offlineVentas.length > 0 && navigator.onLine) {
            mostrarMensaje(`Hay ${offlineVentas.length} ventas pendientes de sincronizar. Sincronizando...`, 'info');
            sincronizarVentasPendientes();
        } else if (offlineVentas.length > 0) {
            mostrarMensaje(`Hay ${offlineVentas.length} ventas pendientes para sincronizar cuando vuelva la conexión.`, 'warning');
        }
    }
    
    // Configurar indicadores de conexión
    actualizarIndicadorConexion();
    
    // Detectar cambios en la conexión
    window.addEventListener('online', manejarCambioConexion);
    window.addEventListener('offline', manejarCambioConexion);
});

// Función para actualizar el indicador de conexión
function actualizarIndicadorConexion() {
    const onlineIndicator = document.getElementById('onlineIndicator');
    const offlineIndicator = document.getElementById('offlineIndicator');
    
    if (navigator.onLine) {
        onlineIndicator.classList.remove('d-none');
        offlineIndicator.classList.add('d-none');
    } else {
        onlineIndicator.classList.add('d-none');
        offlineIndicator.classList.remove('d-none');
    }
    
    isOnline = navigator.onLine;
}

// Manejar cambios en la conexión
function manejarCambioConexion() {
    actualizarIndicadorConexion();
    
    if (navigator.onLine) {
        mostrarMensaje('Conexión restablecida', 'success');
        
        // Si hay ventas pendientes, intentar sincronizar
        if (offlineVentas.length > 0) {
            mostrarMensaje(`Sincronizando ${offlineVentas.length} ventas pendientes...`, 'info');
            sincronizarVentasPendientes();
        }
    } else {
        mostrarMensaje('Modo sin conexión activado. Las ventas se guardarán localmente.', 'warning');
    }
}

// Modificar la función procesarVenta para manejar modo offline
function procesarVenta() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    // Continuar normal si hay conexión
    if (navigator.onLine) {
        // Mostrar modal para seleccionar método de pago
        const modal = new bootstrap.Modal(document.getElementById('metodoPagoModal'));
        modal.show();
    } else {
        // En modo offline, usar un modal simplificado
        Swal.fire({
            title: 'Venta sin conexión',
            text: 'Seleccione el método de pago para esta venta offline',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Efectivo',
            cancelButtonText: 'Cancelar',
            showDenyButton: true,
            denyButtonText: 'Otro método'
        }).then((result) => {
            if (result.isConfirmed) {
                guardarVentaOffline('EFECTIVO');
            } else if (result.isDenied) {
                mostrarSelectorMetodoPagoOffline();
            }
        });
    }
}

// Mostrar selector de método de pago en modo offline
function mostrarSelectorMetodoPagoOffline() {
    Swal.fire({
        title: 'Seleccione método de pago',
        input: 'select',
        inputOptions: {
            'DÉBITO': 'Débito',
            'TRANSFERENCIA': 'Transferencia',
            'DEPOSITO': 'Depósito'
        },
        inputPlaceholder: 'Seleccione un método',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            return new Promise((resolve) => {
                if (value) {
                    resolve();
                } else {
                    resolve('Necesita seleccionar un método de pago');
                }
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            guardarVentaOffline(result.value);
        }
    });
}

// Guardar venta en modo offline
function guardarVentaOffline(metodoPago) {
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const carritoConIVA = carrito.map(item => ({
        ...item,
        total: incluirIVA ? item.total * 1.035 : item.total,
        iva: incluirIVA ? 1 : 0
    }));
    
    // Generar ID temporal para la venta offline
    const offlineId = 'offline_' + Date.now();
    
    // Crear objeto de venta offline
    const ventaOffline = {
        id: offlineId,
        items: carritoConIVA,
        metodoPago: metodoPago,
        timestamp: new Date().toISOString(),
        total: carritoConIVA.reduce((sum, item) => sum + item.total, 0)
    };
    
    // Guardar en el array de ventas offline
    offlineVentas.push(ventaOffline);
    
    // Guardar en localStorage
    localStorage.setItem('offlineVentas', JSON.stringify(offlineVentas));
    
    // Mostrar mensaje de éxito
    Swal.fire({
        title: '¡Venta guardada localmente!',
        text: `Se guardó la venta (${offlineId}) en modo sin conexión. Se sincronizará automáticamente cuando vuelva la conexión a internet.`,
        icon: 'success',
        confirmButtonText: 'Entendido'
    });
    
    // Limpiar carrito
    carrito = [];
    actualizarCarritoUI();
    
    // Mostrar badge con número de ventas pendientes
    actualizarContadorVentasPendientes();
}

// Actualizar contador de ventas pendientes
function actualizarContadorVentasPendientes() {
    const offlineIndicator = document.getElementById('offlineIndicator');
    if (offlineVentas.length > 0) {
        offlineIndicator.textContent = `Sin conexión (${offlineVentas.length})`;
    } else {
        offlineIndicator.textContent = 'Sin conexión';
    }
}

// Sincronizar ventas pendientes cuando vuelva la conexión
function sincronizarVentasPendientes() {
    if (offlineVentas.length === 0) return;
    
    // Copia de las ventas para procesar
    const ventasPorSincronizar = [...offlineVentas];
    let ventasSincronizadas = 0;
    let ventasConError = 0;
    
    // Función para procesar una venta
    function procesarSiguienteVenta() {
        if (ventasPorSincronizar.length === 0) {
            // Todas las ventas procesadas
            const mensaje = `Sincronización completada: ${ventasSincronizadas} ventas sincronizadas` + 
                           (ventasConError > 0 ? `, ${ventasConError} con errores.` : '.');
            
            if (ventasConError === 0) {
                // Si no hay errores, limpiar el almacenamiento
                offlineVentas = [];
                localStorage.setItem('offlineVentas', JSON.stringify(offlineVentas));
                actualizarContadorVentasPendientes();
                mostrarMensaje(mensaje, 'success');
            } else {
                mostrarMensaje(mensaje, 'warning');
            }
            return;
        }
        
        // Obtener la siguiente venta para procesar
        const venta = ventasPorSincronizar.shift();
        
        // Enviar al servidor
        fetch('procesar_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                items: venta.items,
                metodoPago: venta.metodoPago,
                offline: true,
                timestamp: venta.timestamp
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Venta sincronizada con éxito
                ventasSincronizadas++;
                
                // Eliminar de la lista de ventas pendientes
                offlineVentas = offlineVentas.filter(v => v.id !== venta.id);
                localStorage.setItem('offlineVentas', JSON.stringify(offlineVentas));
            } else {
                // Error al sincronizar
                ventasConError++;
                console.error('Error al sincronizar venta:', data.error);
            }
            
            // Procesar la siguiente venta
            procesarSiguienteVenta();
        })
        .catch(error => {
            // Error de red
            ventasConError++;
            console.error('Error de red al sincronizar:', error);
            
            // Procesar la siguiente venta
            procesarSiguienteVenta();
        });
    }
    
    // Iniciar el proceso
    procesarSiguienteVenta();
}

// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo) {
    // Crear o obtener el contenedor de mensajes
    let mensajesContainer = document.getElementById('mensajesContainer');
    if (!mensajesContainer) {
        mensajesContainer = document.createElement('div');
        mensajesContainer.id = 'mensajesContainer';
        mensajesContainer.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: auto;
            min-width: 300px;
            max-width: 80%;
        `;
        document.body.appendChild(mensajesContainer);
    }

    // Determinar si estamos en modo oscuro
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
    
    alertDiv.style.cssText = `
        margin-bottom: 10px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 8px;
        ${isDarkMode ? `
            background-color: ${tipo === 'success' ? '#28a745' : 
                               tipo === 'danger' ? '#dc3545' : 
                               tipo === 'warning' ? '#ffc107' : 
                               tipo === 'info' ? '#17a2b8' : '#343a40'};
            color: ${tipo === 'warning' ? '#212529' : '#ffffff'};
            border-color: ${tipo === 'success' ? '#238c3a' : 
                           tipo === 'danger' ? '#bd2d3b' : 
                           tipo === 'warning' ? '#d9a406' : 
                           tipo === 'info' ? '#148a9c' : '#495057'};
        ` : ''}
    `;
    
    alertDiv.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Si estamos en modo oscuro, aplicar estilo al botón de cierre
    if (isDarkMode) {
        setTimeout(() => {
            const btnClose = alertDiv.querySelector('.btn-close');
            if (btnClose) {
                btnClose.style.filter = 'invert(1) grayscale(100%) brightness(200%)';
            }
        }, 0);
    }
    
    mensajesContainer.appendChild(alertDiv);

    // Remover después de 5 segundos
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

// Definir una función separada si es necesario
function inicializarAplicacion() {
    // Mover el código de inicialización aquí
}

// Buscar y corregir cualquier manejador de eventos que pueda causar el problema
document.addEventListener('DOMContentLoaded', function(e) {
    // Asegurarse de que e se use correctamente o no pasarlo si no es necesario
    // Por ejemplo:
    inicializarAplicacion();
});

function cerrarCarrito() {
    const carritoCollapse = document.getElementById('carritoCollapse');
    const bsCollapse = bootstrap.Collapse.getInstance(carritoCollapse) || new bootstrap.Collapse(carritoCollapse);
    bsCollapse.hide();
    
    // También ocultar el overlay si existe
    const overlay = document.querySelector('.carrito-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}


// Función para formatear números con separadores de miles
function formatearNumero(input) {
    // Obtener el valor sin puntos
    let num = input.value.replace(/\./g, '');
    
    // Eliminar caracteres no numéricos
    num = num.replace(/\D/g, '');
    
    // Convertir a número y formatear con puntos
    if (num) {
        num = parseInt(num).toLocaleString('es-AR');
    }
    
    // Actualizar el valor del input
    input.value = num;
}

// Función para obtener el valor numérico del input de envío
function obtenerValorEnvio() {
    const input = document.getElementById('valorEnvio');
    // Eliminar los puntos y convertir a número
    return parseInt(input.value.replace(/\./g, '')) || 0;
}

function toggleCampoEnvio() {
    const campoEnvioContainer = document.getElementById('campoEnvioContainer');
    const valorEnvio = document.getElementById('valorEnvio');
    
    if (campoEnvioContainer.style.display === 'none') {
        campoEnvioContainer.style.display = 'block';
        // Formatear el valor por defecto
        if (valorEnvio.value) {
            formatearNumero(valorEnvio);
        }
        actualizarCarritoUI();
    } else {
        campoEnvioContainer.style.display = 'none';
        actualizarCarritoUI();
    }
}
</script>

<!-- Modificar el modal de método de pago -->
<div class="modal fade" id="metodoPagoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Método de Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="row g-3">
                    <div class="col-6">
                        <button type="button" class="metodo-pago-btn w-100" onclick="seleccionarMetodoPago('EFECTIVO')">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Efectivo</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="metodo-pago-btn w-100" onclick="seleccionarMetodoPago('DÉBITO')">
                            <i class="fas fa-credit-card"></i>
                            <span>Débito</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="metodo-pago-btn w-100" onclick="seleccionarMetodoPago('TRANSFERENCIA')">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Transferencia</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="metodo-pago-btn w-100" onclick="seleccionarMetodoPago('DEPOSITO')">
                            <i class="fas fa-piggy-bank"></i>
                            <span>Depósito</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Script para manejar correctamente el foco en los modales -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.addEventListener('hide.bs.modal', function (event) {
            if (document.activeElement) {
                document.activeElement.blur();
            }
        });
    });
</script>