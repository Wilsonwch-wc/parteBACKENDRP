<?php
require_once 'db.php';

// Funciones de seguridad
// Función h() para escapar HTML y prevenir XSS
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Función para generar token CSRF
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Función para incluir campo CSRF en formularios
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
    }
}

// Función para verificar token CSRF
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

// Función para registrar intentos de login
if (!function_exists('record_login_attempt')) {
    function record_login_attempt($username) {
        $conn = getDB();
        $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())");
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// Función para verificar intentos de login excesivos
if (!function_exists('check_login_attempts')) {
    function check_login_attempts($username) {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                               WHERE username = ? AND ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        // Si hay más de 5 intentos en los últimos 15 minutos
        if ($row['attempts'] >= 5) {
            return true; // Bloquear el login
        }
        return false; // Permitir el login
    }
}

// Verificar si el usuario está autenticado
// No hacemos verificación en la página de login para evitar bucles de redirección
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page != 'login.php' && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Agregar al inicio del archivo, después de los requires
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión</title>
    <link rel="icon" type="image/png" href="includes/logo3.png">
    
    <!-- Cargar CSS directamente en lugar de usar preload -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            /* Variables para el tema claro (default) */
            --bg-color: #f8f9fa;
            --text-color: #212529;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --input-bg: #ffffff;
            --navbar-bg: #343a40;
            --navbar-color: #ffffff;
            --search-bar-bg: #ffffff;
            --search-text: #212529;
            --search-placeholder: #6c757d;
            --price-color: #212529;
            --icon-color: #212529;
        }

        [data-theme="dark"] {
            /* Variables para el tema oscuro */
            --bg-color: #212529;
            --text-color: #f8f9fa;
            --card-bg: #343a40;
            --border-color: #495057;
            --primary-color: #0d6efd;
            --secondary-color: #adb5bd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --input-bg: #2b3035;
            --navbar-bg: #212529;
            --navbar-color: #ffffff;
            --search-bar-bg: #2b3035;
            --search-text: #f8f9fa;
            --search-placeholder: #adb5bd;
            --price-color: #f8f9fa;
            --icon-color: #f8f9fa;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .card {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .form-control, .form-select {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--border-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar {
            background-color: var(--navbar-bg) !important;
            color: var(--navbar-color);
        }

        /* Estilos para el botón de tema */
        .theme-toggle {
            position: relative;
            width: 60px;
            height: 30px;
            margin: 0 15px;
            cursor: pointer;
        }

        .theme-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .theme-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }

        .theme-slider:before {
            position: absolute;
            content: "☀️";
            display: flex;
            align-items: center;
            justify-content: center;
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .theme-slider {
            background-color: #2196F3;
        }

        input:checked + .theme-slider:before {
            transform: translateX(30px);
            content: "🌙";
        }

        /* Keyboard shortcut indicator */
        .kbd-shortcut {
            display: inline-block;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            padding: 0 5px;
            font-size: 0.8em;
            font-weight: bold;
            color: var(--secondary-color);
            background-color: var(--card-bg);
            box-shadow: 0 1px 1px rgba(0,0,0,0.2);
            margin-left: 5px;
        }

        /* Smooth transitions */
        .btn, .card, .form-control, .form-select, .nav-link {
            transition: all 0.2s ease !important;
        }

        /* Estilos para el modo oscuro */
        [data-theme="dark"] .form-control::placeholder {
            color: #adb5bd;
        }

        [data-theme="dark"] .table {
            color: var(--text-color);
        }

        [data-theme="dark"] .card-header {
            border-color: var(--border-color);
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .theme-toggle {
                margin: 0 8px;
            }
        }

        /* Mantener los estilos existentes */
        /* Estilos para móviles */
        @media (max-width: 991px) {
            .navbar-collapse {
                background-color: #1a1a1a;
                margin-top: 0.5rem;
            }
            
            .navbar-nav {
                padding: 0.5rem 0;
            }
            
            .nav-link {
                padding: 0.7rem 1rem;
                border-left: 3px solid transparent;
                font-size: 0.95rem;
            }
            
            .nav-link i {
                width: 20px;
                margin-right: 0.75rem;
                opacity: 0.7;
            }
            
            .nav-link:hover, 
            .nav-link:focus {
                background-color: #222;
                color: white;
                border-left-color: #0d6efd;
            }
            
            .nav-link.active {
                background-color: #222 !important;
                color: #0d6efd !important;
                border-left-color: #0d6efd;
            }
            
            /* Separador más sutil */
            .nav-divider {
                height: 1px;
                background-color: rgba(255,255,255,0.1);
                margin: 0.3rem 1rem;
            }
            
            /* Botón de refrescar más integrado */
            .btn-outline-light {
                background: transparent;
                border: 1px solid rgba(255,255,255,0.2);
                margin: 0.5rem 1rem;
            }
            
            .btn-outline-light:hover {
                background: #222;
                border-color: rgba(255,255,255,0.3);
            }
        }

        /* Estilos para desktop */
        @media (min-width: 992px) {
            .nav-link.active {
                color: #0d6efd !important;
            }
            
            .nav-link:hover {
                color: white;
            }
        }

        /* Búsqueda en modo oscuro */
        [data-theme="dark"] input[type="search"],
        [data-theme="dark"] input[type="text"] {
            background-color: var(--search-bar-bg);
            color: var(--search-text);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] input[type="search"]::placeholder,
        [data-theme="dark"] input[type="text"]::placeholder {
            color: var(--search-placeholder);
        }
        
        [data-theme="dark"] input[type="search"]:focus,
        [data-theme="dark"] input[type="text"]:focus {
            background-color: #2c3338;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        /* Precios en modo oscuro */
        [data-theme="dark"] .price {
            color: var(--price-color);
            font-weight: bold;
        }
        
        /* Iconos en modo oscuro */
        [data-theme="dark"] .fas,
        [data-theme="dark"] .far,
        [data-theme="dark"] .fa {
            color: var(--icon-color);
        }
        
        /* Ajuste para el botón en la barra de búsqueda */
        .input-group .btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Mejoras generales del tema */
        #themeToggle:focus + .theme-slider {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .theme-toggle {
            margin-right: 1rem;
        }

        /* Eliminar todos los bordes de elementos de formulario */
        input[type="search"],
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        .form-control,
        .form-select,
        textarea,
        select {
            border: none !important;
            box-shadow: none !important;
            background-color: var(--input-bg) !important;
            color: var(--text-color) !important;
        }
        
        /* Bordes para el modo claro */
        [data-theme="light"] input[type="search"],
        [data-theme="light"] input[type="text"],
        [data-theme="light"] input[type="email"],
        [data-theme="light"] input[type="password"],
        [data-theme="light"] input[type="number"],
        [data-theme="light"] .form-control,
        [data-theme="light"] .form-select,
        [data-theme="light"] textarea,
        [data-theme="light"] select {
            background-color: #f8f9fa !important;
        }
        
        /* Bordes para el modo oscuro */
        [data-theme="dark"] input[type="search"],
        [data-theme="dark"] input[type="text"],
        [data-theme="dark"] input[type="email"],
        [data-theme="dark"] input[type="password"],
        [data-theme="dark"] input[type="number"],
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select,
        [data-theme="dark"] textarea,
        [data-theme="dark"] select {
            background-color: #2b3035 !important;
        }
        
        /* Eliminar bordes y sombras en focus */
        input:focus,
        textarea:focus,
        select:focus,
        .form-control:focus,
        .form-select:focus,
        button:focus {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        
        /* Asegurarse que los grupos de input tampoco tengan bordes */
        .input-group,
        .input-group-text {
            border: none !important;
            box-shadow: none !important;
        }
        
        /* Ajustar buscador específicamente */
        .form-control-search,
        input[type="search"] {
            border-radius: 50px !important;
            padding-left: 15px !important;
            background-color: var(--input-bg) !important;
            border: none !important;
        }

        /* Corregir contorno blanco entrecortado del selector de categorías */
        select, .form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            outline: none !important;
            border: none !important;
            box-shadow: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 16px 12px !important;
        }
        
        [data-theme="dark"] select, 
        [data-theme="dark"] .form-select {
            background-color: #2b3035 !important;
            color: #f8f9fa !important;
        }
        
        [data-theme="light"] select, 
        [data-theme="light"] .form-select {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
        
        /* Eliminar todos los contornos en focus */
        *:focus {
            outline: none !important;
            box-shadow: none !important;
            border: none !important;
        }
        
        /* Estilos para botones en modo oscuro */
        [data-theme="dark"] .btn-primary {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: white !important;
        }
        
        [data-theme="dark"] .btn-success {
            background-color: #198754 !important;
            border-color: #198754 !important;
            color: white !important;
        }
        
        [data-theme="dark"] .btn-danger {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        [data-theme="dark"] .btn-warning {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #212529 !important;
        }
        
        [data-theme="dark"] .btn-info {
            background-color: #0dcaf0 !important;
            border-color: #0dcaf0 !important;
            color: #212529 !important;
        }
        
        [data-theme="dark"] .btn-secondary {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: white !important;
        }
        
        [data-theme="dark"] .btn-outline-light {
            color: #f8f9fa !important;
            border-color: #f8f9fa !important;
        }
        
        [data-theme="dark"] .btn-outline-light:hover {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
        
        /* Ajustes para reducir espacio en contenedores de búsqueda */
        .search-filters-wrapper {
            padding: 10px !important;
        }
        
        .search-filters-wrapper .row.mb-3 {
            margin-bottom: 0 !important;
        }
        
        /* Ajustes para inputs y selects en tema oscuro */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .filter-select,
        [data-theme="dark"] select {
            color: #f8f9fa !important;
        }
        
        /* Ajuste para los placeholders en tema oscuro */
        [data-theme="dark"] input::placeholder {
            color: #adb5bd !important;
        }
        
        /* Estilos para modales en tema oscuro */
        [data-theme="dark"] .modal-content {
            background-color: #343a40 !important;
            color: #f8f9fa !important;
            border-color: #495057 !important;
        }
        
        /* Estilos para alertas en modo oscuro */
        [data-theme="dark"] .alert {
            color: #212529 !important;
        }
        
        [data-theme="dark"] .alert-success {
            background-color: #28a745 !important;
            border-color: #238c3a !important;
            color: #fff !important;
        }
        
        [data-theme="dark"] .alert-danger {
            background-color: #dc3545 !important;
            border-color: #bd2d3b !important;
            color: #fff !important;
        }
        
        [data-theme="dark"] .alert-warning {
            background-color: #ffc107 !important;
            border-color: #d9a406 !important;
            color: #212529 !important;
        }
        
        [data-theme="dark"] .alert-info {
            background-color: #17a2b8 !important;
            border-color: #148a9c !important;
            color: #fff !important;
        }
        
        [data-theme="dark"] .alert .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            border-color: #495057 !important;
        }
        
        [data-theme="dark"] .modal-title {
            color: #f8f9fa !important;
        }
        
        [data-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        [data-theme="dark"] input,
        [data-theme="dark"] select,
        [data-theme="dark"] textarea {
            background-color: #2b3035 !important;
            color: #f8f9fa !important;
            border-color: #495057 !important;
        }
        
        [data-theme="dark"] .form-control:disabled,
        [data-theme="dark"] .form-control[readonly] {
            background-color: #212529 !important;
            color: #adb5bd !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="home">Sistema de Ventas</a>
            <button class="navbar-toggler" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                    style="transition: none;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav" style="transition: height 0.2s ease;">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'menu_fotos.php' ? 'active' : ''; ?>" href="catalog">
                            <i class="fas fa-cash-register"></i> Punto de Venta
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'historial.php' ? 'active' : ''; ?>" href="history">
                            <i class="fas fa-history"></i> Historial
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <div class="nav-divider d-lg-none"></div>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'administrar.php' ? 'active' : ''; ?>" href="manage">
                                <i class="fas fa-cog"></i> Administrar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'estadisticas.php' ? 'active' : ''; ?>" href="stats">
                                <i class="fas fa-chart-bar"></i> Estadísticas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'telemetria.php' ? 'active' : ''; ?>" href="telemetria.php">
                                <i class="fas fa-chart-line"></i> Gráficos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'subir_foto.php' ? 'active' : ''; ?>" href="subir_foto.php">
                                <i class="fas fa-upload"></i> Subir productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'gestionar_categorias.php' ? 'active' : ''; ?>" href="gestionar_categorias.php">
                                <i class="fas fa-tags"></i> Gestionar Categorías
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] == 'user'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'subir_foto.php' ? 'active' : ''; ?>" href="subir_foto.php">
                                <i class="fas fa-upload"></i> Subir productos
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <label class="theme-toggle" title="Cambiar tema">
                        <input type="checkbox" id="themeToggle">
                        <span class="theme-slider"></span>
                    </label>
                    <button id="refreshBtn" class="btn btn-outline-light me-2">
                        <i class="fas fa-sync-alt"></i> Refrescar
                    </button>
                    <!-- Indicador de estado de conexión -->
                    <div class="connection-status me-3">
                        <span id="onlineIndicator" class="badge bg-success">
                            <i class="fas fa-wifi"></i> En línea
                        </span>
                        <span id="offlineIndicator" class="badge bg-danger d-none">
                            <i class="fas fa-exclamation-triangle"></i> Sin conexión
                        </span>
                    </div>
                    <div class="nav-item">
                        <span class="navbar-text text-white me-3" title="<?php 
                            if ($_SESSION['role'] == 'admin') {
                                echo 'Dueño';
                            } elseif ($_SESSION['role'] == 'user') {
                                echo 'Gerente';
                            } else {
                                echo 'Vendedor';
                            }
                        ?>">
                            <i class="fas fa-user-tag"></i> <?php 
                            if ($_SESSION['role'] == 'admin') {
                                echo 'Dueño';
                            } elseif ($_SESSION['role'] == 'user') {
                                echo 'Gerente';
                            } else {
                                echo 'Vendedor';
                            }
                        ?>
                        </span>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link text-danger" href="logout" title="Cerrar Sesión">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <script>
        // Función para establecer el tema
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            document.getElementById('themeToggle').checked = (theme === 'dark');
        }

        // Comprobar el tema preferido del usuario
        const savedTheme = localStorage.getItem('theme') || 'light';
        setTheme(savedTheme);

        // Listener para el toggle de tema
        document.getElementById('themeToggle').addEventListener('change', function(e) {
            if(e.target.checked) {
                setTheme('dark');
            } else {
                setTheme('light');
            }
        });

        // Agregar funcionalidad al botón de refrescar
        document.getElementById('refreshBtn').addEventListener('click', function() {
            location.reload();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
