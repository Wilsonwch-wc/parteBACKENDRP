<?php 
// Establecer la zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

include 'includes/header.php'; ?>

<head>
    <!-- ... otros headers ... -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <style>
        .pagination {
            margin-bottom: 0;
        }
        .pagination .page-link {
            padding: 0.375rem 0.75rem;
        }
        .pagination .active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        /* Estilo para el indicador de atajo de teclado */
        .keyboard-shortcut {
            font-size: 0.7rem;
            opacity: 0.8;
            background-color: rgba(0,0,0,0.1);
            padding: 2px 4px;
            border-radius: 3px;
            vertical-align: middle;
            font-weight: normal;
        }
        
        @media (max-width: 991.98px) {
            .card-header .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.9rem;
            }
            
            #filtrosCollapse {
                border-top: 1px solid rgba(0,0,0,.125);
            }
            
            #filtrosCollapse .form-label {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>

<div class="container mt-4">
    <?php
    require_once 'db.php';
    $conn = getDB();
    $result = $conn->query("SELECT SUM(total) as total FROM ventas_cabecera");
    $total = $result->fetch_assoc()['total'] ?? 0;

    $registrosPorPagina = 10;
    $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $offset = ($paginaActual - 1) * $registrosPorPagina;

    $sqlCount = "SELECT COUNT(DISTINCT DATE_FORMAT(v.fecha_venta, '%Y-%m-%d %H:%i:%s')) as total FROM ventas v JOIN productos p ON v.producto_id = p.id";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute();
    $totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
    ?>
    
    <!-- Mensaje de error -->
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Error al cargar el historial de ventas: <?php echo $_GET['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Total y Botón Eliminar -->
    <div class="d-flex gap-3 mb-4">
        <div class="card bg-success text-white" style="min-width: 200px;">
            <div class="card-body">
                <h6 class="card-title mb-0">Total Ventas</h6>
                <h3 class="card-text">$ <?php echo number_format($total, 0); ?></h3>
            </div>
        </div>
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <button class="btn btn-danger" onclick="confirmarEliminarTodo()">
            Eliminar Historial
        </button>
        <button class="btn btn-danger" onclick="mostrarModalEliminarPorFechas()">
            Eliminar por Fechas el historial
        </button>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Filtros</h5>
            <button class="btn btn-primary d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>
        <div class="collapse d-lg-block" id="filtrosCollapse">
            <div class="card-body">
                <form id="filtroForm" action="" method="GET" class="row g-3">
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Año</label>
                        <select class="form-select" name="ano" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php
                            $anos = range(2020, date('Y'));
                            foreach($anos as $ano) {
                                $selected = (isset($_GET['ano']) && $_GET['ano'] == $ano) ? 'selected' : '';
                                echo "<option value='$ano' $selected>$ano</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Mes</label>
                        <select class="form-select" name="mes" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php
                            $meses = [
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                            ];
                            foreach($meses as $num => $nombre) {
                                $selected = (isset($_GET['mes']) && $_GET['mes'] == $num) ? 'selected' : '';
                                echo "<option value='$num' $selected>$nombre</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Desde</label>
                        <input type="text" class="form-control flatpickr" name="desde" 
                               value="<?php echo $_GET['desde'] ?? ''; ?>"
                               placeholder="Fecha desde">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Hasta</label>
                        <input type="text" class="form-control flatpickr" name="hasta"
                               value="<?php echo $_GET['hasta'] ?? ''; ?>"
                               placeholder="Fecha hasta">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Buscar por</label>
                        <input type="text" class="form-control" name="categoria" 
                               value="<?php echo $_GET['categoria'] ?? ''; ?>"
                               placeholder="Código, Producto o Categoría">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">N° Transacción</label>
                        <input type="text" class="form-control" name="pedido" 
                               value="<?php echo $_GET['pedido'] ?? ''; ?>"
                               placeholder="Número">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Envío</label>
                        <select class="form-select" name="filtro_envio" onchange="this.form.submit()">
                            <option value="todos" <?php echo (!isset($_GET['filtro_envio']) || $_GET['filtro_envio'] == 'todos') ? 'selected' : ''; ?>>Todos los pedidos</option>
                            <option value="con_envio" <?php echo (isset($_GET['filtro_envio']) && $_GET['filtro_envio'] == 'con_envio') ? 'selected' : ''; ?>>Con envío</option>
                            <option value="sin_envio" <?php echo (isset($_GET['filtro_envio']) && $_GET['filtro_envio'] == 'sin_envio') ? 'selected' : ''; ?>>Sin envío</option>
                        </select>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary me-2" title="Atajo: Enter">
                            <i class="fas fa-search"></i> Buscar
                            <small class="keyboard-shortcut ms-1">⏎ Enter</small>
                        </button>
                        <a href="history" class="btn btn-outline-secondary" title="Atajo: Ctrl + L">
                            <i class="fas fa-times"></i> Limpiar
                            <small class="keyboard-shortcut ms-1">Ctrl + L</small>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabla de Ventas -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>N° Pedido</th>
                            <th>Productos</th>
                            <th>Cantidad</th>
                            <th>Total</th>
                            <th>Método de Pago / Envío</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Construir la consulta con filtros
                        $where = [];
                        $params = [];
                        $types = "";

                        if (!empty($_GET['ano'])) {
                            $where[] = "YEAR(v.fecha_venta) = ?";
                            $params[] = $_GET['ano'];
                            $types .= "i";
                        }
                        if (!empty($_GET['mes'])) {
                            $where[] = "MONTH(v.fecha_venta) = ?";
                            $params[] = $_GET['mes'];
                            $types .= "i";
                        }
                        if (!empty($_GET['desde'])) {
                            $where[] = "DATE(v.fecha_venta) >= ?";
                            $params[] = $_GET['desde'];
                            $types .= "s";
                        }
                        if (!empty($_GET['hasta'])) {
                            $where[] = "DATE(v.fecha_venta) <= ?";
                            $params[] = $_GET['hasta'];
                            $types .= "s";
                        }
                        if (!empty($_GET['categoria'])) {
                            $where[] = "(p.categoria LIKE ? OR p.codigo LIKE ? OR p.nombre LIKE ?)";
                            $busqueda = '%' . $_GET['categoria'] . '%';
                            $params[] = $busqueda;
                            $params[] = $busqueda;
                            $params[] = $busqueda;
                            $types .= "sss";
                        }
                        if (!empty($_GET['pedido'])) {
                            $where[] = "v.transaccion_id = ?";
                            $params[] = $_GET['pedido'];
                            $types .= "i";
                        }
                        if (isset($_GET['filtro_envio'])) {
                            switch($_GET['filtro_envio']) {
                                case 'con_envio':
                                    $where[] = "vc.costo_envio > 0";
                                    break;
                                case 'sin_envio':
                                    $where[] = "vc.costo_envio = 0 OR vc.costo_envio IS NULL";
                                    break;
                                // 'todos' no necesita condición
                            }
                        }

                        $sql = "SELECT 
                                v.transaccion_id,
                                MIN(v.fecha_venta) as fecha_venta,
                                GROUP_CONCAT(v.id) as ids,
                                GROUP_CONCAT(p.nombre) as productos,
                                GROUP_CONCAT(p.codigo) as codigos,
                                GROUP_CONCAT(v.cantidad) as cantidades,
                                GROUP_CONCAT(v.precio_unitario) as precios,
                                GROUP_CONCAT(p.id) as producto_ids,
                                GROUP_CONCAT(p.categoria) as categorias,
                                SUM(v.cantidad) as total_items,
                                vc.total as total_pedido,
                                vc.metodo_pago,
                                vc.costo_envio,
                                vc.iva,
                                vc.iva21
                            FROM ventas v 
                            JOIN productos p ON v.producto_id = p.id
                            JOIN ventas_cabecera vc ON v.transaccion_id = vc.id";
                        
                        if (!empty($where)) {
                            $sql .= " WHERE " . implode(" AND ", $where);
                        }
                        
                        $sql .= " GROUP BY v.transaccion_id
                                  ORDER BY fecha_venta DESC
                                  LIMIT ? OFFSET ?";
                        
                        $types .= "ii";
                        $params[] = $registrosPorPagina;
                        $params[] = $offset;

                        $stmt = $conn->prepare($sql);
                        if (!empty($params)) {
                            $stmt->bind_param($types, ...$params);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $productos = explode(',', $row['productos']);
                                $codigos = explode(',', $row['codigos']);
                                $cantidades = explode(',', $row['cantidades']);
                                $precios = explode(',', $row['precios']);
                                $ids = explode(',', $row['ids']);
                                $producto_ids = explode(',', $row['producto_ids']);
                                $categorias = explode(',', $row['categorias']);
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_venta'])); ?></td>
                                    <td>Transacción #<?php echo $row['transaccion_id']; ?></td>
                                    <td>
                                        <a href="#" onclick="toggleDetalles('detalles_<?php echo $row['transaccion_id']; ?>')">
                                            <?php echo count($productos) > 1 ? 
                                                  "Pedido múltiple (" . count($productos) . " productos)" : 
                                                  htmlspecialchars($productos[0]); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $row['total_items']; ?></td>
                                    <td>$<?php echo number_format($row['total_pedido'], 0); ?></td>
                                    <td>
                                        <?php echo $row['metodo_pago']; ?>
                                        <?php if ($row['costo_envio'] > 0): ?>
                                            <br><small class="text-success">Con envío ($<?php echo number_format($row['costo_envio'], 0); ?>)</small>
                                        <?php else: ?>
                                            <br><small class="text-muted">Sin envío</small>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['iva'] == 1): ?>
                                            <br><small class="text-primary">Recargo 3.5%</small>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['iva21'] == 1): ?>
                                            <br><small class="text-warning">IVA 21%</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="confirmarDeshacerVenta(<?php echo $row['transaccion_id']; ?>)">
                                            Deshacer venta
                                        </button>
                                        <button class="btn btn-sm btn-secondary" 
                                                onclick="imprimirTicket(<?php echo $row['transaccion_id']; ?>)">
                                            Imprimir Ticket
                                        </button>
                                    </td>
                                </tr>
                                <tr id="detalles_<?php echo $row['transaccion_id']; ?>" style="display:none;">
                                    <td colspan="6">
                                        <div class="card">
                                            <div class="card-body">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Código</th>
                                                            <th>Producto</th>
                                                            <th>Cantidad</th>
                                                            <th>Precio Unit.</th>
                                                            <th>Subtotal</th>
                                                            <th>Categoría</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        for($i = 0; $i < count($productos); $i++) {
                                                            ?>
                                                            <tr>
                                                            <td><?php echo htmlspecialchars($codigos[$i]); ?></td>
                                                                <td><?php echo htmlspecialchars($productos[$i]); ?></td>
                                                                <td><?php echo $cantidades[$i]; ?></td>
                                                                <td>$<?php echo number_format($precios[$i], 0); ?></td>
                                                                <td>$<?php echo number_format($cantidades[$i] * $precios[$i], 0); ?></td>
                                                                <td><?php echo htmlspecialchars($categorias[$i]); ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-warning" 
                                                                            onclick="confirmarDevolucion(<?php echo $ids[$i]; ?>, '<?php echo htmlspecialchars($productos[$i]); ?>', <?php echo $producto_ids[$i]; ?>, <?php echo $cantidades[$i]; ?>)">
                                                                        Devolver
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center">No hay ventas registradas</td></tr>';
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Mostrando <?php echo ($offset + 1); ?>-<?php echo min($offset + $registrosPorPagina, $totalRegistros); ?> de <?php echo $totalRegistros; ?> ventas
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
        </div>
    </div>
</div>

<!-- Modal para eliminar por fechas -->
<div class="modal fade" id="modalEliminarPorFechas" tabindex="-1" aria-labelledby="modalEliminarPorFechasLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEliminarPorFechasLabel">Eliminar Ventas por Fechas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEliminarPorFechas">
                    <div class="mb-3">
                        <label for="fechaDesde" class="form-label">Fecha Desde</label>
                        <input type="text" class="form-control flatpickr" id="fechaDesde" name="desde" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="mb-3">
                        <label for="fechaHasta" class="form-label">Fecha Hasta</label>
                        <input type="text" class="form-control flatpickr" id="fechaHasta" name="hasta" placeholder="YYYY-MM-DD">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="eliminarPorFechas()">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
let pedidoIdGlobal;

function toggleDetalles(id) {
    const detalles = document.getElementById(id);
    const transaccionId = id.replace('detalles_', '');
    
    if (detalles.style.display === 'none') {
        detalles.style.display = 'table-row';
        
        // Actualizar la URL con el parámetro mostrar_detalles
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('mostrar_detalles', transaccionId);
        
        // Actualizar la URL sin recargar la página
        const newUrl = window.location.pathname + '?' + urlParams.toString();
        history.pushState({}, '', newUrl);
    } else {
        detalles.style.display = 'none';
        
        // Eliminar el parámetro mostrar_detalles de la URL
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.delete('mostrar_detalles');
        
        // Actualizar la URL sin recargar la página
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        history.pushState({}, '', newUrl);
    }
}

function confirmarDeshacerVenta(ventaId) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: '¿Realmente deseas deshacer toda la venta? Se devolverá el stock de todos los productos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, deshacer venta',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `deshacer_venta.php?venta_id=${ventaId}`;
        }
    });
}

function confirmarDevolucion(ventaId, nombreProducto, productoId, cantidad = 1) {
    // Obtener todos los parámetros actuales de la URL
    const urlParams = new URLSearchParams(window.location.search);
    let parametrosURL = '';
    
    // Encontrar el ID de transacción al que pertenece esta venta
    const detalleAbierto = document.getElementById('detalles_' + urlParams.get('mostrar_detalles'));
    let transaccionId = urlParams.get('mostrar_detalles');
    
    // Si no hay un detalle abierto en la URL, buscar el elemento padre para obtener la transacción
    if (!transaccionId) {
        // Buscar el elemento padre para obtener la transacción
        const filaDetalle = document.querySelector(`button[onclick*="confirmarDevolucion(${ventaId},"]`).closest('tr').closest('tbody').closest('tr');
        if (filaDetalle && filaDetalle.id) {
            transaccionId = filaDetalle.id.replace('detalles_', '');
        }
    }
    
    // Asegurar que mostrar_detalles esté entre los parámetros si tenemos un ID de transacción
    if (transaccionId) {
        urlParams.set('mostrar_detalles', transaccionId);
    }
    
    // Agregar cada parámetro a la URL de redirección
    urlParams.forEach((value, key) => {
        if (parametrosURL === '') {
            parametrosURL = `&${key}=${value}`;
        } else {
            parametrosURL += `&${key}=${value}`;
        }
    });
    
    if (cantidad === 1) {
        Swal.fire({
            title: 'Confirmar devolución',
            text: `¿Confirma la devolución de ${nombreProducto}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `devolver_producto.php?venta_id=${ventaId}&producto_id=${productoId}&cantidad=1${parametrosURL}`;
            }
        });
    } else {
        Swal.fire({
            title: 'Cantidad a devolver',
            text: `¿Cuántas unidades de ${nombreProducto} desea devolver?`,
            input: 'number',
            inputAttributes: {
                min: 1,
                max: cantidad,
                step: 1
            },
            inputValue: 1,
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value || value < 1 || value > cantidad) {
                    return 'Por favor ingrese una cantidad válida';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const cantidadDevolver = result.value;
                Swal.fire({
                    title: 'Confirmar devolución',
                    text: `¿Confirma la devolución de ${cantidadDevolver} unidad(es) de ${nombreProducto}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar'
                }).then((confirm) => {
                    if (confirm.isConfirmed) {
                        window.location.href = `devolver_producto.php?venta_id=${ventaId}&producto_id=${productoId}&cantidad=${cantidadDevolver}${parametrosURL}`;
                    }
                });
            }
        });
    }
}

function mostrarModalEliminarPorFechas() {
    const modal = new bootstrap.Modal(document.getElementById('modalEliminarPorFechas'));
    modal.show();
}

function eliminarPorFechas() {
    const desde = document.getElementById('fechaDesde').value;
    const hasta = document.getElementById('fechaHasta').value;

    if (!desde || !hasta) {
        alert('Por favor, seleccione ambas fechas.');
        return;
    }

    if (confirm(`¿Está seguro de eliminar todas las ventas desde ${desde} hasta ${hasta}? Esta acción no se puede deshacer.`)) {
        window.location.href = `eliminar_por_fechas.php?desde=${desde}&hasta=${hasta}`;
    }
}

function imprimirTicket(transaccionId) {
    const ticketWindow = window.open(`generar_ticket.php?transaccion_id=${transaccionId}`, 'Ticket de Venta', 'width=400,height=600');
}

flatpickr(".flatpickr", {
    locale: "es",
    dateFormat: "Y-m-d",
    altFormat: "d/m/Y",
    altInput: true,
    allowInput: true,
    theme: "material_blue",
    onChange: function(selectedDates, dateStr, instance) {
        instance.element.form.submit();
    }
});

// Inicializar flatpickr para las fechas del modal
flatpickr("#fechaDesde, #fechaHasta", {
    locale: "es",
    dateFormat: "Y-m-d",
    altFormat: "d/m/Y",
    altInput: true,
    allowInput: true,
    theme: "material_blue"
});

// Función para actualizar la tabla de historial
function actualizarTablaHistorial() {
    // Obtener todos los parámetros actuales de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const paginaActual = urlParams.get('pagina') || 1;
    
    // Construir la URL con todos los filtros actuales
    let url = `buscar_historial.php?`;
    urlParams.forEach((value, key) => {
        url += `${key}=${value}&`;
    });
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.querySelector('.table-responsive').innerHTML = html;
        })
        .catch(error => console.error('Error:', error));
}

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
                    actualizarTablaHistorial();
                }
            })
            .catch(error => console.log('Error en la actualización:', error));
    }, 2000);
}

// Iniciar el sistema de actualización cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    iniciarActualizacionTiempoReal();
    
    // Inicializar flatpickr para los campos de fecha
    flatpickr(".flatpickr", {
        locale: "es",
        dateFormat: "Y-m-d",
        allowInput: true
    });
    
    // Manejar el envío del formulario con Enter
    const camposBusqueda = document.querySelectorAll('#filtroForm input, #filtroForm select');
    camposBusqueda.forEach(campo => {
        campo.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filtroForm').submit();
            }
        });
    });
    
    // Manejar el atajo Ctrl + L para limpiar filtros
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'l') {
            e.preventDefault(); // Prevenir la acción por defecto (seleccionar la barra de direcciones del navegador)
            window.location.href = 'historial.php';
        }
    });
    
    // Abrir detalles si hay uno especificado en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const mostrarDetalles = urlParams.get('mostrar_detalles');
    if (mostrarDetalles) {
        const detalles = document.getElementById('detalles_' + mostrarDetalles);
        if (detalles) {
            detalles.style.display = 'table-row';
        }
    }
    
    // Si hay un mensaje de éxito o error relacionado con devoluciones, mostrar Sweet Alert
    const successMsg = urlParams.get('success');
    const errorMsg = urlParams.get('error');
    
    if (successMsg) {
        Swal.fire({
            title: 'Éxito',
            text: successMsg,
            icon: 'success',
            confirmButtonColor: '#28a745'
        });
    } else if (errorMsg) {
        Swal.fire({
            title: 'Error',
            text: errorMsg,
            icon: 'error',
            confirmButtonColor: '#dc3545'
        });
    }
});

// Función para eliminar todo el historial con SweetAlert
function confirmarEliminarTodo() {
    Swal.fire({
        title: '¿Estás seguro?',
        text: '¿Realmente deseas eliminar todo el historial de ventas? Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar todo',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'eliminar_historial.php';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>