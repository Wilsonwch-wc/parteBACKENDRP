<?php
require_once 'db.php';
checkPermission('admin');
header('Content-Type: application/json');

$conn = getDB();
if (!$conn) {
    die(json_encode(['error' => 'Error de conexión con la base de datos']));
}

$vista = $_GET['vista'] ?? 'mes';
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 year'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$format = '';
$groupBy = '';
$dateFormat = '';

switch($vista) {
    case 'dia':
        $format = '%Y-%m-%d';
        $groupBy = 'DATE(fecha_venta)';
        $dateFormat = 'd/m/Y';
        break;
    case 'mes':
        $format = '%Y-%m';
        $groupBy = 'DATE_FORMAT(fecha_venta, "%Y-%m")';
        $dateFormat = 'M Y';
        break;
    case 'año':
        $format = '%Y';
        $groupBy = 'YEAR(fecha_venta)';
        $dateFormat = 'Y';
        break;
}

$sql = "SELECT 
            DATE_FORMAT(fecha_venta, '$format') as periodo,
            COUNT(*) as total_ventas,
            SUM(total) as total_ingresos
        FROM ventas 
        WHERE fecha_venta BETWEEN ? AND ?
        GROUP BY $groupBy
        ORDER BY periodo ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$ventas = [];
$ingresos = [];

while($row = $result->fetch_assoc()) {
    $fecha = $vista === 'mes' ? 
        date($dateFormat, strtotime($row['periodo'] . '-01')) : 
        date($dateFormat, strtotime($row['periodo']));
        
    $labels[] = $fecha;
    $ventas[] = intval($row['total_ventas']);
    $ingresos[] = round(floatval($row['total_ingresos']), 2);
}

$conn->close();

echo json_encode([
    'labels' => $labels,
    'ventas' => $ventas,
    'ingresos' => $ingresos
]);
?> 