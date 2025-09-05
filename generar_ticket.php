<?php
require_once 'db.php';

// Establecer la zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (isset($_GET['transaccion_id'])) {
    $transaccionId = $_GET['transaccion_id'];
    $conn = getDB();

    if ($conn->connect_error) {
        die("Error: Connection failed.");
    }

    $sql = "SELECT v.*, p.nombre, p.codigo, vc.metodo_pago, vc.costo_envio, vc.iva, vc.iva21 
            FROM ventas v 
            JOIN productos p ON v.producto_id = p.id 
            JOIN ventas_cabecera vc ON v.transaccion_id = vc.id
            WHERE v.transaccion_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $transaccionId);
    $stmt->execute();
    $result = $stmt->get_result();

    $carrito = [];
    $metodoPago = '';
    $costoEnvio = 0;
    $incluirIVA = false;
    $incluirIVA21 = false;

    while ($row = $result->fetch_assoc()) {
        $carrito[] = [
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'cantidad' => $row['cantidad'],
            'precio' => $row['precio_unitario'],
            'total' => $row['total']
        ];
        $metodoPago = $row['metodo_pago'];
        $costoEnvio = $row['costo_envio'] ?? 0;
        $incluirIVA = $row['iva'] == 1;
        $incluirIVA21 = $row['iva21'] == 1;
    }
    
    $conn->close();
} else if (isset($_POST['ticketData'])) {
    $datos = json_decode($_POST['ticketData'], true);
    if (!$datos) {
        echo "Error: Datos incompletos.";
        exit;
    }
    $carrito = $datos['items'];
    $metodoPago = $datos['metodoPago'];
    $costoEnvio = isset($datos['envio']) ? floatval($datos['envio']) : 0;
    $incluirIVA = isset($datos['incluirIVA']) ? $datos['incluirIVA'] : false;
    $incluirIVA21 = isset($datos['incluirIVA21']) ? $datos['incluirIVA21'] : false;
} else {
    $datos = json_decode(file_get_contents('php://input'), true);
    if (!$datos) {
        echo "Error: Datos incompletos.";
        exit;
    }
    $carrito = $datos['items'];
    $metodoPago = $datos['metodoPago'];
    $costoEnvio = isset($datos['envio']) ? floatval($datos['envio']) : 0;
    $incluirIVA = isset($datos['incluirIVA']) ? $datos['incluirIVA'] : false;
    $incluirIVA21 = isset($datos['incluirIVA21']) ? $datos['incluirIVA21'] : false;
}

// Siempre usar la fecha y hora actual del servidor con zona horaria de Argentina
$fecha = date('d/m/Y H:i:s');
$transaccionId = isset($_GET['transaccion_id']) ? $_GET['transaccion_id'] : 'OFFLINE';

$totalItems = 0;
$totalGeneral = 0;
$ivaTotal = 0;
$iva21Total = 0;

foreach ($carrito as $item) {
    $totalItems += $item['cantidad'];
    $totalGeneral += $item['total'];
}

// Guardar el total sin impuestos para el cálculo correcto
$subTotal = $totalGeneral;

// Calcular recargo e IVA si están marcados
if ($incluirIVA) {
    $ivaTotal = $subTotal * 0.035;
}

if ($incluirIVA21) {
    $iva21Total = $subTotal * 0.21;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page {
            margin: 0;
            size: 80mm 297mm;
        }
        body {
            font-family: 'Space Mono', monospace;
            width: 80mm;
            margin: 0;
            padding: 5mm;
            font-size: 11px;
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
            margin-top: 20px;
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
        .header {
            text-align: center;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: bold;
        }
        .item-row {
            font-size: 10px;
            line-height: 1.4;
        }
        .total {
            font-size: 13px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 10px; font-size: 13px;">
        <strong>PUNTO CAPS</strong><br>
        <strong>CAMPANA 255 FLORES L.30</strong><br>
        <strong>CATALOGO 1166445162 - 1166445164</strong><br>
        <strong><?php echo $fecha; ?></strong>
    </div>
    
    <div style="border-top: 1px dashed #000; margin: 5px 0;"></div>
    
    <table>
        <thead>
            <tr>
                <th><strong>Código</strong></th>
                <th><strong>Un.</strong></th>
                <th><strong>Descrip.</strong></th>
                <th><strong>Precio</strong></th>
                <th><strong>Total</strong></th>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ($carrito as $item) {
            ?>
            <tr class="item-row">
                <td colspan="5" style="font-weight: bold; text-align: left;">
                    <strong><?php echo htmlspecialchars($item['codigo']) . " - " . htmlspecialchars($item['nombre']); ?></strong>
                </td>
            </tr>
            <tr>
                <td><?php echo ""; ?></td>
                <td><strong><?php echo htmlspecialchars($item['cantidad']); ?></strong></td>
                <td><strong>$<?php echo number_format($item['precio'], 0); ?></strong></td>
                <td><strong>$<?php echo number_format($item['precio'] * $item['cantidad'], 0); ?></strong></td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>

    <div class="total">
        <div>Orden de Compra #<?php echo $transaccionId; ?></div>
        <div>Total de item: <?php echo $totalItems; ?></div>
        <div>Forma de Pago: <?php echo htmlspecialchars($metodoPago); ?></div>
        <?php if ($incluirIVA): ?>
        <div>transacción 3.5%: $<?php echo number_format($ivaTotal, 0); ?></div>
        <?php endif; ?>
        <?php if ($incluirIVA21): ?>
        <div>IVA 21%: $<?php echo number_format($iva21Total, 0); ?></div>
        <?php endif; ?>
        <?php if ($costoEnvio > 0): ?>
        <div>Cadete: $ <?php echo number_format($costoEnvio, 0); ?></div>
        <?php endif; ?>
        <div><strong>Valor total $ <?php echo number_format($subTotal + ($incluirIVA ? $ivaTotal : 0) + ($incluirIVA21 ? $iva21Total : 0) + $costoEnvio, 0); ?></strong></div>
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
