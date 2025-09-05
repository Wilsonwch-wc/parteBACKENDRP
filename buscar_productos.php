<?php
require_once 'db.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Función para formatear números en pesos argentinos
if (!function_exists('formato_pesos_arg')) {
    function formato_pesos_arg($numero, $decimales = 0) {
        return number_format($numero, $decimales, ',', '.');
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

// Generar token CSRF para la sesión actual
generate_csrf_token();

// Definir función h si no existe
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

$conn = getDB();

$registrosPorPagina = 10; // Aumentar registros por página
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

$query = $_GET['q'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$busqueda = "%$query%";

// Construir la consulta dinámica según los filtros
$whereClause = '';
$params = [];
$tipos = '';

if (!empty($query) && !empty($categoria)) {
    // Búsqueda por texto y categoría
    $whereClause = "(p.nombre LIKE ? OR p.codigo LIKE ?) AND p.categoria = ?";
    $params = [$busqueda, $busqueda, $categoria];
    $tipos = "sss";
} elseif (!empty($query)) {
    // Solo búsqueda por texto
    $whereClause = "(p.nombre LIKE ? OR p.codigo LIKE ? OR p.categoria LIKE ?)";
    $params = [$busqueda, $busqueda, $busqueda];
    $tipos = "sss";
} elseif (!empty($categoria)) {
    // Solo filtro por categoría
    $whereClause = "p.categoria = ?";
    $params = [$categoria];
    $tipos = "s";
} else {
    // Sin filtros, mostrar todos los productos
    $whereClause = "1=1"; // Siempre verdadero
}

// Consulta para contar el total de registros
$sqlCount = "SELECT COUNT(DISTINCT p.id) as total FROM productos p WHERE $whereClause";
$stmtCount = $conn->prepare($sqlCount);

if (!empty($params)) {
    $stmtCount->bind_param($tipos, ...$params);
}

$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$rowCount = $resultCount->fetch_assoc();
$totalRegistros = $rowCount['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta principal con LIMIT para paginación y GROUP_CONCAT para imágenes
$sql = "SELECT p.*, GROUP_CONCAT(i.ruta_imagen ORDER BY i.id) as imagenes 
        FROM productos p 
        LEFT JOIN imagenes_producto i ON p.id = i.producto_id 
        WHERE $whereClause 
        GROUP BY p.id 
        ORDER BY p.nombre ASC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);

// Añadir parámetros de paginación
$params[] = $offset;
$params[] = $registrosPorPagina;
$tipos .= "ii";

$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Construir la respuesta HTML para la vista de productos
if ($result->num_rows > 0) {
    // Agregar meta tag para CSRF
    echo '<meta name="csrf-token" content="' . $_SESSION['csrf_token'] . '">';
    
    // mostrar resultado
    echo '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xxl-5 g-3 mb-4" id="productosGrid">';

    while($row = $result->fetch_assoc()) {
        // Manejar caso en que no haya imágenes
        $imagenes = [];
        if (!empty($row['imagenes'])) {
            $imagenes = explode(',', $row['imagenes']);
        }
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
                        if (count($imagenes) > 0) {
                            foreach ($imagenes as $index => $imagen) {
                                ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo h($imagen); ?>" class="d-block w-100" 
                                         style="height: 240px; object-fit: cover;" 
                                         alt="<?php echo h($row['nombre']); ?>">
                                </div>
                                <?php
                            }
                        } else {
                            // Mostrar imagen por defecto si no hay imágenes
                            ?>
                            <div class="carousel-item active">
                                <img src="assets/img/no-image.png" class="d-block w-100" 
                                     style="height: 240px; object-fit: cover;" 
                                     alt="Sin imagen">
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php if (count($imagenes) > 1): ?>
                        <button class="carousel-control-prev" type="button" 
                                data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" 
                                data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body py-2">
                    <h5 class="card-title fs-6"><?php echo h($row['nombre']); ?></h5>
                    <p class="card-text small mb-2">
                        <strong>Categoría:</strong> <span class="categoria-producto"><?php echo h($row['categoria']); ?></span><br>
                        <strong>Stock:</strong> 
                        <span class="badge bg-<?php echo $row['stock'] < 5 ? ($row['stock'] == 0 ? 'danger' : 'warning') : 'success'; ?>">
                            <span class="stock-producto"><?php echo $row['stock']; ?></span> unidades
                        </span><br>
                        <strong>Precio:</strong> $<span class="precio-producto"><?php echo formato_pesos_arg($row['precio']); ?></span><br>
                        <strong>Precio de Compra:</strong> $<?php echo formato_pesos_arg($row['precio_compra']); ?><br>
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
                    <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $row['id']; ?>, '<?php echo addslashes(h($row['nombre'])); ?>')">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
} else {
    echo '<div class="col-12 text-center py-5">';
    echo '<p class="text-muted">No se encontraron productos que coincidan con la búsqueda.</p>';
    echo '</div>';
}

// Paginación
if ($totalPaginas > 1):
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted">
            Mostrando <?php echo ($offset + 1); ?>-<?php echo min($offset + $registrosPorPagina, $totalRegistros); ?> de <?php echo $totalRegistros; ?> productos
        </div>
        
        <!-- Paginación -->
        <nav aria-label="Navegación de páginas">
            <ul class="pagination mb-0">
                <?php if ($paginaActual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="buscarPagina(<?php echo $paginaActual - 1; ?>); return false;">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $rangoInicio = max(1, $paginaActual - 2);
                $rangoFin = min($totalPaginas, $paginaActual + 2);
                
                if ($rangoInicio > 1) {
                    echo '<li class="page-item"><a class="page-link" href="#" onclick="buscarPagina(1); return false;">1</a></li>';
                    if ($rangoInicio > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $rangoInicio; $i <= $rangoFin; $i++) {
                    echo '<li class="page-item ';
                    if ($i == $paginaActual) echo 'active';
                    echo '"><a class="page-link" href="#" onclick="buscarPagina(' . $i . '); return false;">' . $i . '</a></li>';
                }

                if ($rangoFin < $totalPaginas) {
                    if ($rangoFin < $totalPaginas - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="#" onclick="buscarPagina(' . $totalPaginas . '); return false;">' . $totalPaginas . '</a></li>';
                }
                ?>

                <?php if ($paginaActual < $totalPaginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="buscarPagina(<?php echo $paginaActual + 1; ?>); return false;">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php
endif;

$conn->close();
?>

<script>
// Función para formatear números en formato argentino
const formatoPesosArg = (numero, decimales = 0) => {
    return numero.toLocaleString('es-AR', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    });
};

function cambiarEstadoProducto(id, nuevoEstado, event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Guardar la posición actual de desplazamiento
    const scrollPosition = window.scrollY;
    
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
            const estadoSpan = btn.closest('.card').querySelector('.estado-texto');
            
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
            text: 'Error de comunicación con el servidor',
            icon: 'error'
        });
    });
    
    return false;
}

// Variables para gestionar la posición de scroll
let currentScrollPosition = 0;

// Función para abrir el modal de sumar stock
function sumarStock(id, nombre) {
    // Guardar la posición actual de desplazamiento
    currentScrollPosition = window.scrollY;
    
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

// Función para procesar el formulario de sumar stock
function procesarSumarStock() {
    // Guardar la posición actual de desplazamiento
    const scrollPosition = window.scrollY;
    
    const productoIdActual = document.getElementById('productoIdStock').value;
    const cantidad = parseInt(document.getElementById('cantidadStock').value);
    
    if (isNaN(cantidad) || cantidad <= 0) {
        Swal.fire({
            title: 'Error',
            text: 'Por favor ingresa una cantidad válida mayor a cero',
            icon: 'error'
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
                window.scrollTo(0, scrollPosition);
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

// También agregar la función actualizarFilaProducto para manejar edición en buscar_productos.php
function actualizarFilaProducto(producto) {
    // Buscar la tarjeta del producto
    const productCard = document.querySelector(`.card[data-id="${producto.id}"]`);
    if (!productCard) {
        return; // Si no encontramos la tarjeta, no hacemos nada
    }
    
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
}

// Función para editar productos redirigiendo a administrar.php
function editarProducto(producto) {
    // Guardar el producto en sessionStorage para recuperarlo en administrar.php
    sessionStorage.setItem('productoEditar', JSON.stringify(producto));
    
    // Redirigir a administrar.php con un parámetro para abrir el modal
    window.location.href = 'administrar.php?editar=' + producto.id;
}
</script>
