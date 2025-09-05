<?php
require_once 'db.php';
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desactivar la salida de errores en la respuesta

try {
    $conn = getDB();

    $desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
    $hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

    // Validar fechas
    if (!strtotime($desde) || !strtotime($hasta)) {
        throw new Exception('Fechas inválidas');
    }

    // Modificar la consulta para usar fecha_venta
    $sql = "SELECT 
                COALESCE(p.categoria, 'Sin Categoría') as categoria,
                COUNT(DISTINCT v.id) as cantidad_ventas,
                COALESCE(SUM(v.precio_unitario * v.cantidad), 0) as total_ventas
            FROM ventas v
            JOIN productos p ON v.producto_id = p.id
            WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
            GROUP BY p.categoria
            HAVING total_ventas > 0
            ORDER BY total_ventas DESC";

    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Error preparando la consulta');
    }

    $stmt->bind_param("ss", $desde, $hasta);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando la consulta');
    }

    $result = $stmt->get_result();
    $categorias = [];
    $ventas = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row['categoria'];
            $ventas[] = round($row['total_ventas'], 0);
        }
    } else {
        $categorias[] = 'Sin ventas';
        $ventas[] = 0;
    }

    // Asegurar que la respuesta sea JSON válido
    $response = [
        'categorias' => $categorias,
        'ventas' => $ventas,
        'desde' => $desde,
        'hasta' => $hasta,
        'status' => 'success'
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en get_ventas_categoria.php: " . $e->getMessage());
    
    // Devolver un JSON con el error
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener los datos',
        'debug' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 