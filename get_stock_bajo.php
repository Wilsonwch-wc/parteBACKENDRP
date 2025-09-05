<?php
require_once 'db.php';
header('Content-Type: application/json');

$conn = getDB();

// Parámetros de paginación
$registrosPorPagina = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Primero, obtener el total de registros para la paginación
$sqlCount = "SELECT COUNT(*) as total FROM productos WHERE stock < 50";
$resultCount = $conn->query($sqlCount);
$totalRegistros = $resultCount->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta principal con paginación
$sql = "SELECT * FROM productos WHERE stock < 50 ORDER BY stock ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

$productos = [];

while ($row = $result->fetch_assoc()) {
    $productos[] = [
        'id' => $row['id'],
        'codigo' => $row['codigo'],
        'nombre' => $row['nombre'],
        'stock' => (int)$row['stock'],
        'categoria' => $row['categoria'],
        'precio' => number_format($row['precio'], 0),
        'precio_compra' => number_format($row['precio_compra'], 0)
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