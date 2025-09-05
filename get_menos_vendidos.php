<?php
require_once 'db.php';
header('Content-Type: application/json');

$conn = getDB();

// Parámetros de paginación
$registrosPorPagina = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Primero, obtener el total de registros para la paginación
$sqlCount = "SELECT COUNT(*) as total FROM (
    SELECT 
        p.id
    FROM productos p
    LEFT JOIN ventas v ON p.id = v.producto_id
    GROUP BY p.id, p.codigo, p.nombre
    HAVING COALESCE(SUM(v.cantidad), 0) <= 5
) AS t";

$resultCount = $conn->query($sqlCount);
$totalRegistros = $resultCount->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Obtener productos menos vendidos o sin ventas con paginación
$sql = "SELECT 
            p.id,
            p.codigo,
            p.nombre,
            p.precio_compra,
            COALESCE(SUM(v.cantidad), 0) as total_vendido,
            COALESCE(SUM(v.precio_unitario * v.cantidad), 0) as total_ventas,
            p.stock,
            DATEDIFF(CURRENT_DATE, p.fecha_creacion) as dias_en_inventario
        FROM productos p
        LEFT JOIN ventas v ON p.id = v.producto_id
        GROUP BY p.id, p.codigo, p.nombre
        HAVING total_vendido <= 5
        ORDER BY total_vendido ASC, dias_en_inventario DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

$productos = [];

while ($row = $result->fetch_assoc()) {
    // Calcular métricas adicionales
    $dias = max(1, $row['dias_en_inventario']);
    $ventas_por_dia = $row['total_vendido'] / $dias;
    $dinero_estancado = $row['stock'] * $row['precio_compra'];
    
    $productos[] = [
        'codigo' => $row['codigo'],
        'nombre' => $row['nombre'],
        'vendido' => (int)$row['total_vendido'],
        'total_ventas' => number_format($row['total_ventas'], 0),
        'stock_actual' => (int)$row['stock'],
        'dias_inventario' => $dias,
        'ventas_por_dia' => round($ventas_por_dia, 3),
        'dinero_estancado' => number_format($dinero_estancado, 0)
    ];
}

// Retornar datos y metadatos de paginación
$response = [
    'productos' => $productos,
    'paginacion' => [
        'total' => $totalRegistros,
        'por_pagina' => $registrosPorPagina,
        'pagina_actual' => $paginaActual,
        'total_paginas' => $totalPaginas
    ]
];

echo json_encode($response);
$conn->close();
?> 