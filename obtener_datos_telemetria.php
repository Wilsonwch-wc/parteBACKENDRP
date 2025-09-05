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
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    // Validar fechas
    if (!strtotime($desde) || !strtotime($hasta)) {
        throw new Exception('Fechas inválidas');
    }

    // Ajustar la consulta según la vista
    switch($vista) {
        case 'dia':
            $groupBy = 'DATE(fecha_venta)';
            $orderBy = 'fecha_venta';
            break;
        case 'semana':
            $groupBy = 'YEARWEEK(fecha_venta)';
            $orderBy = 'periodo';
            break;
        case 'mes':
        default:
            $groupBy = 'DATE_FORMAT(fecha_venta, "%Y-%m")';
            $orderBy = 'periodo';
            break;
    }

    $sql = "SELECT 
                $groupBy as periodo,
                SUM(v.cantidad * v.precio_compra) as gastos,
                SUM(v.total - (v.cantidad * v.precio_compra)) as ganancias
            FROM ventas v
            WHERE fecha_venta BETWEEN ? AND ?
            GROUP BY periodo
            ORDER BY $orderBy";

    // Debug
    error_log("SQL Query: " . $sql);
    error_log("Params: desde = $desde, hasta = $hasta");

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $desde, $hasta);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception($conn->error);
    }

    $labels = [];
    $gastos = [];
    $ganancias = [];

    while ($row = $result->fetch_assoc()) {
        error_log("Row data: " . print_r($row, true));
        // Formatear la etiqueta según la vista
        switch($vista) {
            case 'dia':
                $labels[] = date('d/m/Y', strtotime($row['periodo']));
                break;
            case 'semana':
                $year = substr($row['periodo'], 0, 4);
                $week = substr($row['periodo'], 4);
                $labels[] = "Semana $week, $year";
                break;
            case 'mes':
            default:
                $labels[] = date('M Y', strtotime($row['periodo'] . '-01'));
                break;
        }
        $gastos[] = floatval(round($row['gastos'], 2));
        $ganancias[] = floatval(round($row['ganancias'], 2));
    }

    // Si no hay datos, proporcionar datos vacíos
    if (empty($labels)) {
        $labels = [date('d/m/Y')];
        $gastos = [0];
        $ganancias = [0];
    }

    // Formatear valores con formato ARS (punto como separador de miles)
    $gastos_formateados = array_map(function($valor) {
        return floatval(round($valor, 0));
    }, $gastos);
    
    $ganancias_formateadas = array_map(function($valor) {
        return floatval(round($valor, 0));
    }, $ganancias);

    $response = [
        'labels' => $labels,
        'gastos' => $gastos_formateados,
        'ganancias' => $ganancias_formateadas
    ];

    error_log("Response: " . print_r($response, true));
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Error en telemetría: " . $e->getMessage());
    echo json_encode(['error' => 'Error en el servidor']);
}

$conn->close();
?> 