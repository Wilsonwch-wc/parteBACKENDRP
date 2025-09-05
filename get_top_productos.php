<?php
require_once 'db.php';
header('Content-Type: application/json');

$conn = getDB();

$desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

// Obtener el total de productos diferentes vendidos en el período
$sqlTotal = "SELECT COUNT(DISTINCT p.id) as total 
             FROM ventas v 
             JOIN productos p ON v.producto_id = p.id
             WHERE DATE(v.fecha_venta) BETWEEN ? AND ?";

$stmt = $conn->prepare($sqlTotal);
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();
$totalProductos = $stmt->get_result()->fetch_assoc()['total'];

// Determinar el límite
$limite = min(10, $totalProductos);

$sql = "SELECT 
            p.nombre,
            SUM(v.cantidad) as total_cantidad,
            SUM(v.precio_unitario * v.cantidad) as total_ventas,
            ROUND((SUM(v.cantidad) * 100.0) / (
                SELECT SUM(cantidad) 
                FROM ventas
                WHERE DATE(fecha_venta) BETWEEN ? AND ?
            ), 1) as porcentaje_total
        FROM ventas v
        JOIN productos p ON v.producto_id = p.id
        WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
        GROUP BY p.id, p.nombre
        ORDER BY total_cantidad DESC
        LIMIT ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $desde, $hasta, $desde, $hasta, $limite);
$stmt->execute();
$result = $stmt->get_result();

$nombres = [];
$cantidades = [];
$montos = [];
$porcentajes = [];

while ($row = $result->fetch_assoc()) {
    $nombres[] = strlen($row['nombre']) > 25 ? 
                substr($row['nombre'], 0, 25) . '...' : 
                $row['nombre'];
    $cantidades[] = (int)$row['total_cantidad'];
    $montos[] = floatval(round($row['total_ventas'], 0));
    $porcentajes[] = $row['porcentaje_total'];
}

echo json_encode([
    'nombres' => $nombres,
    'cantidades' => $cantidades,
    'montos' => $montos,
    'porcentajes' => $porcentajes,
    'total_productos' => $totalProductos
]);

$conn->close();
?> 