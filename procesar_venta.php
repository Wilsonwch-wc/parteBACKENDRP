<?php
require_once 'db.php';
checkPermission();
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = getDB();
    if (!$conn) {
        die(json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos']));
    }

    $carrito = json_decode(file_get_contents('php://input'), true);
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        foreach ($carrito as $item) {
            // Verificar stock disponible
            $stmt = $conn->prepare("SELECT stock, precio_compra FROM productos WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $item['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $producto = $result->fetch_assoc();
            
            if ($producto['stock'] >= $item['cantidad']) {
                // Actualizar stock
                $stmt = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $item['cantidad'], $item['id']);
                $stmt->execute();
                
                // Registrar venta
                $iva = isset($item['iva']) && $item['iva'] == 1 ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO ventas (producto_id, cantidad, precio_compra, precio_unitario, total, iva) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiddid", $item['id'], $item['cantidad'], $producto['precio_compra'], $item['precio'], $item['total'], $iva);
                $stmt->execute();
            } else {
                throw new Exception("Stock insuficiente para " . $item['nombre']);
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    $conn->close();
}
?>