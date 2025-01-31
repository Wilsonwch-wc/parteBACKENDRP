<?php
require_once 'db.php';
checkPermission();

if (isset($_GET['pedido_id'])) {
    $pedidoId = $_GET['pedido_id'];
    $metodoPago = $_GET['metodo_pago'] ?? 'Desconocido';
    $conn = getDB();
    
    if ($conn->connect_error) {
        die("Error: Connection failed.");
    }
    
    // Obtener la fecha exacta de la venta
    $sql = "SELECT fecha_venta FROM ventas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pedidoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fechaVenta = $result->fetch_assoc()['fecha_venta'];
    
    // Buscar productos por la misma fecha exacta
    $sql = "SELECT v.*, p.nombre, p.codigo FROM ventas v 
            JOIN productos p ON v.producto_id = p.id 
            WHERE v.fecha_venta = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $fechaVenta);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $carrito = [];
    $fecha = '';
    while ($row = $result->fetch_assoc()) {
        $iva = $row['iva'] == 1 ? '21%' : '-';
        $carrito[] = [
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'cantidad' => $row['cantidad'],
            'precio' => $row['precio_unitario'],
            'iva' => $iva,
            'total' => $row['total']
        ];
        $fecha = date('d/m/Y H:i:s', strtotime($row['fecha_venta']));
    }
    
    $conn->close();
} else {
    $datos = json_decode(file_get_contents('php://input'), true);
    $carrito = $datos['items'];
    $metodoPago = $datos['metodoPago'];
    $fecha = date('d/m/Y H:i:s');

    // Verificar que los datos se recibieron correctamente
    if (!$carrito || !$metodoPago) {
        echo "Error: Datos incompletos.";
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta</title>
    <style>
        @page {
            margin: 0;
            size: 80mm 297mm;
        }
        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0;
            padding: 5mm;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        th, td {
            text-align: left;
            padding: 2px 0;
            font-size: 11px;
        }
        .total {
            text-align: left;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed #000;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 11px;
        }
        .item-row td {
            vertical-align: top;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 10px;">
        <strong>PUNTO CAPS</strong><br>
        <strong>CAMPANA 255, L. 30</strong><br>
        <strong>CATALOGO 1166445162 - 1166445164</strong><br>
        <strong><?php echo $fecha; ?></strong>
    </div>
    <table>
        <thead>
            <tr>
                <th><strong>Código</strong></th>
                <th><strong>Descrip.</strong></th>
                <th><strong>Un.</strong></th>
                <th><strong>Precio</strong></th>
                <th><strong>Iva</strong></th>
                <th><strong>Total</strong></th>
            </tr>
        </thead>
        <tbody>
        <?php
$totalItems = 0;
$totalGeneral = 0;
foreach ($carrito as $item) {
    $totalItems += $item['cantidad'];
    $totalGeneral += $item['total'];
    ?>
    <tr class="item-row">
        <!-- Descripción ocupa toda la línea -->
        <td colspan="6" style="font-weight: bold; text-align: left;">
            <strong><?php echo htmlspecialchars($item['codigo']) . " - " . htmlspecialchars($item['nombre']); ?></strong>
        </td>
    </tr>
    <!-- Segunda línea con los demás datos -->
    <tr>
        <td><?php echo ""; ?></td>
        <td><?php echo ""; ?></td>
        <td><strong><?php echo htmlspecialchars($item['cantidad']); ?></strong></td>
        <td><strong><?php echo number_format($item['precio']); ?></strong></td>
        <td><strong><?php echo htmlspecialchars($item['iva']); ?></strong></td>
        <td><strong><?php echo number_format($item['total']); ?></strong></td>
    </tr>
<?php
}
?>

        </tbody>
    </table>

    <div class="total">
        <div><strong># Pedido: <?php echo $pedidoId; ?></strong></div>
        <div><strong>Total de items: <?php echo $totalItems; ?></strong></div>
        <div><strong>Valor a pagar $ <?php echo number_format($totalGeneral, 2); ?></strong></div>
        <div><strong>FORMA PAGO: <?php echo htmlspecialchars($metodoPago); ?></strong></div>
    </div>

    <div class="no-print" style="margin-top:20px;text-align:center;">
        <button onclick="window.print()">Imprimir Ticket</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>