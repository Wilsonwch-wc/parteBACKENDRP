<?php
require_once 'db.php';
checkPermission();
include 'includes/header.php';

if ($_SESSION['role'] == 'admin') {
    header("Location: admin.php");
} elseif ($_SESSION['role'] == 'usuario') {
    header("Location: menu_fotos.php");
} else {
    header("Location: menu_fotos.php");
}
exit;
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
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Sistema de Ventas</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="menu_fotos.php">Menú Fotos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="historial.php">Historial</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="estadisticas.php">Estadísticas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subir_foto.php">Subir Foto</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="administrar.php">Administrar</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Aquí irá el contenido específico de cada página -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>