<?php include 'includes/header.php'; ?>

<head>
    <!-- ... otros headers ... -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
</head>

<div class="container mt-4">
    <h2>Historial de Ventas</h2>
    
    <?php
    require_once 'db.php';
    $conn = getDB();
    $result = $conn->query("SELECT SUM(total) as total FROM ventas");
    $total = $result->fetch_assoc()['total'] ?? 0;
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
                <h3 class="card-text">$ <?php echo number_format($total, 2); ?></h3>
            </div>
        </div>
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <button class="btn btn-danger" onclick="if(confirm('¿Estás seguro de eliminar todo el historial?')) window.location.href='eliminar_historial.php'">
            Eliminar Todo
        </button>
        <button class="btn btn-danger" onclick="mostrarModalEliminarPorFechas()">
            Eliminar por Fechas
        </button>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <form id="filtroForm" action="" method="GET" class="row g-3 mb-4">
        <div class="col-md-2">
            <select class="form-select" name="ano" onchange="this.form.submit()">
                <option value="">Año</option>
                <?php
                $anos = range(2020, date('Y'));
                foreach($anos as $ano) {
                    $selected = (isset($_GET['ano']) && $_GET['ano'] == $ano) ? 'selected' : '';
                    echo "<option value='$ano' $selected>$ano</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="mes" onchange="this.form.submit()">
                <option value="">Mes</option>
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
        <div class="col-md-2">
            <input type="text" class="form-control flatpickr" name="desde" 
                   value="<?php echo $_GET['desde'] ?? ''; ?>"
                   placeholder="Fecha desde">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control flatpickr" name="hasta"
                   value="<?php echo $_GET['hasta'] ?? ''; ?>"
                   placeholder="Fecha hasta">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" name="categoria" 
                   value="<?php echo $_GET['categoria'] ?? ''; ?>"
                   placeholder="Categoría">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" name="pedido" 
                   value="<?php echo $_GET['pedido'] ?? ''; ?>"
                   placeholder="Número de Pedido">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
        </div>
        <div class="col-md-1">
            <a href="historial.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <!-- Tabla de Ventas -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Pedido #</th>
                            <th>Productos</th>
                            <th>Cantidad</th>
                            <th>Total</th>
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
                            $where[] = "p.categoria LIKE ?";
                            $params[] = '%' . $_GET['categoria'] . '%';
                            $types .= "s";
                        }
                        if (!empty($_GET['pedido'])) {
                            $where[] = "v.id IN (
                                SELECT v2.id 
                                FROM ventas v2 
                                WHERE v2.fecha_venta = (
                                    SELECT fecha_venta 
                                    FROM ventas 
                                    WHERE id = ?
                                )
                            )";
                            $params[] = $_GET['pedido'];
                            $types .= "i";
                        }

                        $sql = "SELECT 
                                v.fecha_venta,
                                GROUP_CONCAT(v.id) as ids,
                                GROUP_CONCAT(p.nombre) as productos,
                                GROUP_CONCAT(p.codigo) as codigos,
                                GROUP_CONCAT(v.cantidad) as cantidades,
                                GROUP_CONCAT(v.precio_unitario) as precios,
                                GROUP_CONCAT(p.id) as producto_ids,
                                SUM(v.cantidad) as total_items,
                                SUM(v.total) as total_pedido,
                                MIN(v.id) as pedido_id
                            FROM ventas v 
                            JOIN productos p ON v.producto_id = p.id";
                        
                        if (!empty($where)) {
                            $sql .= " WHERE " . implode(" AND ", $where);
                        }
                        
                        $sql .= " GROUP BY DATE_FORMAT(v.fecha_venta, '%Y-%m-%d %H:%i:%s')
                                  ORDER BY v.fecha_venta DESC";
                        
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
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_venta'])); ?></td>
                                    <td><?php echo $row['pedido_id']; ?></td>
                                    <td>
                                        <a href="#" onclick="toggleDetalles('detalles_<?php echo $row['pedido_id']; ?>')">
                                            <?php echo count($productos) > 1 ? 
                                                  "Pedido múltiple (" . count($productos) . " productos)" : 
                                                  htmlspecialchars($productos[0]); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $row['total_items']; ?></td>
                                    <td>$<?php echo number_format($row['total_pedido'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="eliminarPedido(<?php echo $row['pedido_id']; ?>)">
                                            Deshacer venta
                                        </button>
                                        <button class="btn btn-sm btn-secondary" 
                                                onclick="imprimirTicket(<?php echo $row['pedido_id']; ?>)">
                                            Imprimir Ticket
                                        </button>
                                    </td>
                                </tr>
                                <tr id="detalles_<?php echo $row['pedido_id']; ?>" style="display:none;">
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
                                                                <td>$<?php echo number_format($precios[$i], 2); ?></td>
                                                                <td>$<?php echo number_format($cantidades[$i] * $precios[$i], 2); ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-warning" 
                                                                            onclick="devolverProducto(<?php echo $ids[$i]; ?>, <?php echo $producto_ids[$i]; ?>, <?php echo $cantidades[$i]; ?>, '<?php echo htmlspecialchars($productos[$i]); ?>')">
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
            <div class="text-muted">
                Mostrando <?php echo $result->num_rows; ?> de <?php echo $result->num_rows; ?> ventas
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

<!-- Modal para seleccionar método de pago -->
<div class="modal fade" id="modalMetodoPago" tabindex="-1" aria-labelledby="modalMetodoPagoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMetodoPagoLabel">Seleccione Método de Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="seleccionarMetodoPago('EFECTIVO')">Efectivo</button>
                    <button class="btn btn-primary" onclick="seleccionarMetodoPago('DÉBITO')">Débito</button>
                    <button class="btn btn-primary" onclick="seleccionarMetodoPago('TRANSFERENCIA')">Transferencia</button>
                    <button class="btn btn-primary" onclick="seleccionarMetodoPago('DEPOSITO')">Depósito</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let pedidoIdGlobal;

function toggleDetalles(id) {
    const detalles = document.getElementById(id);
    detalles.style.display = detalles.style.display === 'none' ? 'table-row' : 'none';
}

function eliminarPedido(pedidoId) {
    if (confirm('¿Estás seguro de deshacer toda la venta? Se devolverá el stock de todos los productos.')) {
        window.location.href = 'eliminar_venta.php?id=' + pedidoId;
    }
}

function devolverProducto(ventaId, productoId, cantidadActual, nombreProducto) {
    const cantidadDevolver = prompt(`¿Cuántas unidades de ${nombreProducto} desea devolver? (Máximo: ${cantidadActual})`);
    
    if (cantidadDevolver === null) return; // Usuario canceló
    
    const cantidad = parseInt(cantidadDevolver);
    if (isNaN(cantidad) || cantidad <= 0 || cantidad > cantidadActual) {
        alert('Por favor ingrese una cantidad válida');
        return;
    }
    
    if (confirm(`¿Confirma la devolución de ${cantidad} unidad(es) de ${nombreProducto}?`)) {
        window.location.href = `devolver_producto.php?venta_id=${ventaId}&producto_id=${productoId}&cantidad=${cantidad}`;
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

    if (confirm(`¿Está seguro de eliminar todas las ventas desde ${desde} hasta ${hasta}?`)) {
        window.location.href = `eliminar_por_fechas.php?desde=${desde}&hasta=${hasta}`;
    }
}

function imprimirTicket(pedidoId) {
    pedidoIdGlobal = pedidoId;
    const modal = new bootstrap.Modal(document.getElementById('modalMetodoPago'));
    modal.show();
}

function seleccionarMetodoPago(metodoPago) {
    fetch(`generar_ticket.php?pedido_id=${pedidoIdGlobal}&metodo_pago=${metodoPago}`)
        .then(response => response.text())
        .then(html => {
            if (html.includes("Error:")) {
                alert(html);
            } else {
                const ticketWindow = window.open('', 'Ticket de Venta', 'width=400,height=600');
                ticketWindow.document.write(html);
                ticketWindow.document.close();
            }
        });
}

flatpickr(".flatpickr", {
    locale: "es",
    dateFormat: "Y-m-d",
    altFormat: "d/m/Y",
    altInput: true,
    allowInput: true,
    theme: "material_blue"
});
</script>

<?php include 'includes/footer.php'; ?>