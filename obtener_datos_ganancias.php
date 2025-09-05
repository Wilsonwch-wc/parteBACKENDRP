<?php
error_reporting(0); // Desactivar reporte de errores
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

$conn = getDB();
if (!$conn) {
    die(json_encode(['error' => 'Error de conexión']));
}

try {
    $vista = $_GET['vista'] ?? 'mes';
    $desde = $_GET['desde'] ?? date('Y-m-01'); // Primer día del mes actual
    $hasta = $_GET['hasta'] ?? date('Y-m-d');  // Hoy

    $sql = "SELECT 
                DATE(fecha) as fecha,
                SUM(total) as ingresos,
                SUM(cantidad * precio_compra) as gastos,
                SUM(total - (cantidad * precio_compra)) as ganancias
            FROM ventas v
            JOIN productos p ON v.producto_id = p.id
            WHERE fecha BETWEEN ? AND ?
            GROUP BY DATE(fecha)
            ORDER BY fecha";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $desde, $hasta);
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $gastos = [];
    $ganancias = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = date('d/m/Y', strtotime($row['fecha']));
        $gastos[] = floatval(round($row['gastos'], 2));
        $ganancias[] = floatval(round($row['ganancias'], 2));
    }

    // Si no hay datos, proporcionar datos vacíos
    if (empty($labels)) {
        $labels = [date('d/m/Y')];
        $gastos = [0];
        $ganancias = [0];
    }

    echo json_encode([
        'labels' => $labels,
        'gastos' => $gastos,
        'ganancias' => $ganancias
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error en el servidor']);
}

$conn->close();
?> 