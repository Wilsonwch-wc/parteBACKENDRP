<?php
require_once dirname(__DIR__) . '/db.php';
checkPermission();

// Obtener IP del servidor
$server_ip = $_SERVER['SERVER_ADDR'];
if ($server_ip == '::1' || $server_ip == '127.0.0.1') {
    $server_ip = gethostbyname(gethostname());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
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
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="subir_foto.php">Subir Foto</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
                <span class="navbar-text text-white">
                    IP: <?php echo $server_ip; ?>
                </span>
            </div>
        </div>
    </nav>
</body>
</html>
