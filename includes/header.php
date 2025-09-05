<?php
require_once dirname(__DIR__) . '/db.php';
checkPermission();

// Obtener IP del servidor
function getWiFiIP() {
    $command = PHP_OS === 'WINNT' ? 'ipconfig' : 'ifconfig';
    exec($command, $output);
    
    foreach ($output as $line) {
        // Para Windows, buscar adaptadores WiFi
        if (PHP_OS === 'WINNT') {
            if (strpos($line, 'Adaptador de LAN inalámbrica Wi-Fi') !== false || 
                strpos($line, 'Wireless LAN adapter Wi-Fi') !== false) {
                $wifiFound = true;
            }
            if (isset($wifiFound) && strpos($line, 'IPv4') !== false) {
                return trim(explode(":", $line)[1]);
            }
        } else {
            // Para Linux/Unix, buscar wlan0 o similar
            if (strpos($line, 'wlan0') !== false && strpos($line, 'inet ') !== false) {
                preg_match('/inet (\d+\.\d+\.\d+\.\d+)/', $line, $matches);
                return $matches[1];
            }
        }
    }
    return 'No se encontró IP WiFi';
}

$server_ip = getWiFiIP();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .navbar {
            background-color: #1a1a1a;
            padding: 1rem;
        }
        .navbar-brand {
            color: white;
            font-size: 1.5rem;
        }
        .nav-link {
            color: #808080;
            margin-left: 1rem;
        }
        .nav-link:hover {
            color: white;
        }
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .carousel-control-prev,
        .carousel-control-next {
            background-color: rgba(0, 0, 0, 0.3);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            margin: 0 10px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sistema de Ventas</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="menu_fotos.php">Menú Fotos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="historial.php">Historial</a>
                    </li>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="administrar.php">Administrar</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="estadisticas.php">Estadísticas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="subir_foto.php">Subir Foto</a>
                        </li>
                    <?php elseif ($_SESSION['role'] == 'user'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="subir_foto.php">Subir Foto</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-outline-light me-3" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refrescar
                        </button>
                    </li>
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            IP: <span id="ip-address"><?php echo $server_ip; ?></span>/php
                            <button class="btn btn-link text-white p-0 ms-2" onclick="toggleIP()" style="text-decoration: none;">
                                <i id="eye-icon" class="fas fa-eye"></i>
                            </button>
                        </span>
                    </li>
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            Rol: <?php echo htmlspecialchars($_SESSION['role']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <script>
        function toggleIP() {
            const ipElement = document.getElementById('ip-address');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (ipElement.style.filter === 'blur(4px)') {
                ipElement.style.filter = 'none';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
                localStorage.setItem('ipHidden', 'false');
            } else {
                ipElement.style.filter = 'blur(4px)';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                localStorage.setItem('ipHidden', 'true');
            }
        }

        // Verificar el estado guardado al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const ipElement = document.getElementById('ip-address');
            const eyeIcon = document.getElementById('eye-icon');
            const isHidden = localStorage.getItem('ipHidden') === 'true';
            
            if (isHidden) {
                ipElement.style.filter = 'blur(4px)';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    </script>
</body>
</html>
