<?php
require_once 'db.php';
checkPermission();

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

$registrosPorPagina = 10; // Aumentado de 12 a 15 productos por página
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Obtener parámetros de búsqueda y filtrado
$query = isset($_GET['busqueda']) ? $_GET['busqueda'] : (isset($_GET['q']) ? $_GET['q'] : '');
$busqueda = "%$query%";

// Nuevo parámetro para filtrar por categoría
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// Construcción dinámica de la consulta SQL base
$whereClause = "p.estado = 1";

// Añadir condición de búsqueda si hay un término
if (!empty($query)) {
    $whereClause .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.categoria LIKE ?)";
}

// Añadir condición de categoría si se ha seleccionado una
if (!empty($categoria)) {
    $whereClause .= " AND p.categoria = ?";
}

// Primero, obtener el total de registros para la búsqueda
$sqlCount = "SELECT COUNT(DISTINCT p.id) as total 
             FROM productos p 
             WHERE $whereClause";

$stmtCount = $conn->prepare($sqlCount);

// Binding dinámico de parámetros
if (!empty($query) && !empty($categoria)) {
    $stmtCount->bind_param("ssss", $busqueda, $busqueda, $busqueda, $categoria);
} elseif (!empty($query)) {
    $stmtCount->bind_param("sss", $busqueda, $busqueda, $busqueda);
} elseif (!empty($categoria)) {
    $stmtCount->bind_param("s", $categoria);
}

$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta principal con paginación y límite de imágenes
$sql = "SELECT p.*, 
        GROUP_CONCAT(i.ruta_imagen ORDER BY i.id) as imagenes 
        FROM productos p 
        LEFT JOIN imagenes_producto i ON p.id = i.producto_id 
        WHERE $whereClause
        GROUP BY p.id 
        ORDER BY p.fecha_creacion DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

// Binding dinámico de parámetros para la consulta principal
if (!empty($query) && !empty($categoria)) {
    $stmt->bind_param("ssssii", $busqueda, $busqueda, $busqueda, $categoria, $registrosPorPagina, $offset);
} elseif (!empty($query)) {
    $stmt->bind_param("sssii", $busqueda, $busqueda, $busqueda, $registrosPorPagina, $offset);
} elseif (!empty($categoria)) {
    $stmt->bind_param("sii", $categoria, $registrosPorPagina, $offset);
} else {
    $stmt->bind_param("ii", $registrosPorPagina, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

function mostrarImagen($rutaOriginal) {
    $rutaWebP = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $rutaOriginal);
    return '<picture>
              <source srcset="' . htmlspecialchars($rutaWebP) . '" type="image/webp">
              <img src="' . htmlspecialchars($rutaOriginal) . '" class="card-img-top producto-imagen" alt="Imagen del producto">
            </picture>';
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $imagenes = explode(',', $row['imagenes']);
        ?>
        <div class="col">
            <div class="card h-100" data-producto-id="<?php echo $row['id']; ?>">
                <small class="codigo-producto">Código: <?php echo htmlspecialchars($row['codigo']); ?></small>
                <div id="carousel<?php echo $row['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php if (!empty($imagenes[0])): ?>
                            <?php foreach($imagenes as $index => $imagen): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo $imagen; ?>" 
                                         class="producto-imagen" 
                                         alt="<?php echo $row['nombre']; ?>"
                                         onclick='agregarAlCarrito({
                                             "id": <?php echo $row["id"]; ?>,
                                             "codigo": "<?php echo addslashes($row["codigo"]); ?>",
                                             "nombre": "<?php echo addslashes($row["nombre"]); ?>",
                                             "precio": <?php echo intval($row["precio"]); ?>,
                                             "stock": <?php echo intval($row["stock"]); ?>
                                         })'>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="carousel-item active">
                                <img src="assets/img/no-image.png" 
                                     class="producto-imagen" 
                                     alt="Sin imagen disponible"
                                     onclick='agregarAlCarrito({
                                         "id": <?php echo $row["id"]; ?>,
                                         "codigo": "<?php echo addslashes($row["codigo"]); ?>",
                                         "nombre": "<?php echo addslashes($row["nombre"]); ?>",
                                         "precio": <?php echo intval($row["precio"]); ?>,
                                         "stock": <?php echo intval($row["stock"]); ?>
                                     })'>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($imagenes) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="precio">$<?php echo number_format($row['precio'], 0, ',', '.'); ?></div>
                    <h5 class="producto-nombre"><?php echo htmlspecialchars($row['nombre']); ?></h5>
                    <div class="producto-detalles">
                        <span class="categoria"><?php echo htmlspecialchars($row['categoria']); ?></span>
                        <span class="stock-badge <?php echo $row['stock'] > 0 ? 'disponible' : 'agotado'; ?>">
                            <?php echo $row['stock'] > 0 ? $row['stock'] . ' unidades' : 'Sin stock'; ?>
                        </span>
                    </div>
                    <?php if ($row['stock'] > 0): ?>
                        <button class="btn-agregar" 
                                onclick='agregarAlCarrito({
                                    "id": <?php echo $row["id"]; ?>,
                                    "codigo": "<?php echo addslashes($row["codigo"]); ?>",
                                    "nombre": "<?php echo addslashes($row["nombre"]); ?>",
                                    "precio": <?php echo intval($row["precio"]); ?>,
                                    "stock": <?php echo intval($row["stock"]); ?>
                                })'>
                            Agregar
                        </button>
                    <?php else: ?>
                        <button class="btn-agotado" disabled>Agotado</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // Mostrar paginación
    if ($totalPaginas > 1) {
        ?>
        <div class="col-12">
            <nav aria-label="Navegación de páginas" class="pagination-container-static">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($paginaActual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="#" onclick="buscarPagina(<?php echo $paginaActual - 1; ?>); return false;">
                                <i class="fas fa-chevron-left fa-xs"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $rango = 1; // Reducido de 2 a 1 para mostrar menos números
                    for ($i = max(1, $paginaActual - $rango); $i <= min($totalPaginas, $paginaActual + $rango); $i++): 
                    ?>
                        <li class="page-item <?php echo $i == $paginaActual ? 'active' : ''; ?>">
                            <a class="page-link" href="#" onclick="buscarPagina(<?php echo $i; ?>); return false;"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($paginaActual < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="#" onclick="buscarPagina(<?php echo $paginaActual + 1; ?>); return false;">
                                <i class="fas fa-chevron-right fa-xs"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>

        <style>
        .pagination-container-static {
            margin: 2.5rem 0;
            padding: 1rem 0;
        }

        .pagination {
            gap: 8px;
        }

        .page-link {
            border: none;
            color: #666;
            width: 45px;
            height: 45px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.2s ease;
            background-color: transparent;
        }

        .page-link:hover {
            background-color: #f0f0f0;
            color: #333;
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background-color: #2c3e50;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(44, 62, 80, 0.2);
        }

        .page-item:first-child .page-link,
        .page-item:last-child .page-link {
            background-color: transparent;
            color: #666;
            font-size: 1rem;
            width: 40px;
            height: 40px;
        }

        .page-item:first-child .page-link:hover,
        .page-item:last-child .page-link:hover {
            background-color: #f0f0f0;
            color: #333;
            transform: translateY(-2px);
        }

        @media (max-width: 576px) {
            .pagination-container-static {
                margin: 2rem 0;
            }

            .page-link {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .page-item:first-child .page-link,
            .page-item:last-child .page-link {
                width: 35px;
                height: 35px;
            }
        }
        </style>
        <?php
    }
} else {
    echo '<div class="col-12"><div class="alert alert-info">No se encontraron productos.</div></div>';
}

$stmt->close();
$conn->close();
?>

<style>
    /* Eliminar todos los bordes en elementos de formulario */
    input, select, textarea, button, .form-control, .form-select {
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
    }
    
    /* Asegurar que no haya bordes en estados hover/focus */
    input:hover, input:focus, select:hover, select:focus, 
    textarea:hover, textarea:focus, button:hover, button:focus,
    .form-control:hover, .form-control:focus, 
    .form-select:hover, .form-select:focus {
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
    }
    
    /* Estilos específicos para el buscador */
    #buscador {
        border: none !important;
        box-shadow: none !important;
        background-color: var(--input-bg) !important;
    }
    
    /* Estilos para los botones y controles */
    .pagination .page-link {
        border: none !important;
    }
    
    /* Estilos minimalistas */
    .codigo-producto {
        padding: 5px 8px;
        color: #666;
        font-size: 0.75rem;
    }

    .producto-imagen {
        width: 100%;
        height: 150px; /* Reducido de 300px a 150px */
        object-fit: cover;
        cursor: pointer;
    }

    .card-body {
        padding: 0.75rem;
    }

    .precio {
        font-size: 1.2rem; /* Reducido de 1.5rem */
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 0.25rem; /* Reducido de 0.5rem */
    }

    .producto-nombre {
        font-size: 0.9rem; /* Reducido de 1rem */
        margin-bottom: 0.25rem; /* Reducido de 0.5rem */
        color: #34495e;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .producto-detalles {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem; /* Reducido de 1rem */
        font-size: 0.8rem; /* Reducido de 0.9rem */
    }

    .categoria {
        color: #7f8c8d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 50%;
    }

    .stock-badge {
        padding: 2px 6px; /* Reducido de 4px 8px */
        border-radius: 4px;
        font-size: 0.7rem; /* Reducido de 0.8rem */
    }

    .stock-badge.disponible {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .stock-badge.agotado {
        background: #ffebee;
        color: #c62828;
    }

    .btn-agregar, .btn-agotado {
        width: 100%;
        padding: 8px;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .btn-agregar {
        background: #2c3e50;
        color: white;
    }

    .btn-agregar:hover {
        background: #34495e;
        transform: translateY(-1px);
    }

    .btn-agotado {
        background: #ecf0f1;
        color: #95a5a6;
        cursor: not-allowed;
    }

    /* Carousel más sutil */
    .carousel-control-prev, .carousel-control-next {
        opacity: 0.7;
        width: 10%;
    }

    .carousel-control-prev-icon, .carousel-control-next-icon {
        background-color: rgba(0,0,0,0.3);
        border-radius: 50%;
        padding: 12px;
    }
</style>
