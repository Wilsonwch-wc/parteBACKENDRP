<?php
// Establecer la zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once 'db.php';
checkPermission();

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

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

// Agregar filtro de envío a las condiciones WHERE
if (isset($_GET['filtro_envio'])) {
    switch($_GET['filtro_envio']) {
        case 'con_envio':
            $where[] = "vc.costo_envio > 0";
            break;
        case 'sin_envio':
            $where[] = "vc.costo_envio = 0";
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
?>

<!-- Agregar filtro de envío -->
<div class="mb-3">
    <select class="form-select" name="filtro_envio" onchange="this.form.submit()">
        <option value="todos" <?php echo (!isset($_GET['filtro_envio']) || $_GET['filtro_envio'] == 'todos') ? 'selected' : ''; ?>>Todos los pedidos</option>
        <option value="con_envio" <?php echo (isset($_GET['filtro_envio']) && $_GET['filtro_envio'] == 'con_envio') ? 'selected' : ''; ?>>Con envío</option>
        <option value="sin_envio" <?php echo (isset($_GET['filtro_envio']) && $_GET['filtro_envio'] == 'sin_envio') ? 'selected' : ''; ?>>Sin envío</option>
    </select>
</div>

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
                                onclick="eliminarPedido(<?php echo $row['transaccion_id']; ?>)">
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
            echo '<tr><td colspan="7" class="text-center">No hay ventas registradas</td></tr>';
        }
        ?>
    </tbody>
</table> 