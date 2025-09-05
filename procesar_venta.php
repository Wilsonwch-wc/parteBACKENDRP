<?php
require_once 'db.php';

// Establecer la zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Para APIs JSON, verificamos el token CSRF en las cabeceras HTTP
if (!function_exists('verify_csrf_token_api')) {
    function verify_csrf_token_api() {
        $headers = getallheaders();
        $token = isset($headers['X-CSRF-TOKEN']) ? $headers['X-CSRF-TOKEN'] : null;
        
        if (!$token || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            // Si el token CSRF no es válido, registrar el intento y responder con error
            error_log("Intento de CSRF en API detectado desde IP: " . $_SERVER['REMOTE_ADDR']);
            http_response_code(403);
            echo json_encode(['error' => 'Error de seguridad: token inválido']);
            exit;
        }
    }
}

// Verificar permisos
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar el token CSRF para API
// Nota: En entornos de producción, descomenta esta línea cuando la integración con el frontend esté lista
// verify_csrf_token_api();

// Configurar cabeceras y manejo de errores para mejor debugging
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = getDB();

// Obtener el usuario actual
$usuario_id = $_SESSION['user_id'];

// Obtener datos del carrito
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos']);
    exit;
}

// Verificar si es una venta offline
$esVentaOffline = isset($data['offline']) && $data['offline'] === true;
$fechaVenta = date('Y-m-d H:i:s'); // Usar formato actual por defecto

// Si es una venta offline con timestamp, convertir el formato ISO a MySQL
if ($esVentaOffline && isset($data['timestamp'])) {
    try {
        $dt = new DateTime($data['timestamp']);
        $fechaVenta = $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // Si hay error en el formato, seguir con la fecha actual
        error_log("Error parseando timestamp: " . $e->getMessage());
    }
}

try {
    // Iniciar transacción
    $conn->begin_transaction();

    // Calcular total de la venta
    $subtotal = array_reduce($data['items'], function($carry, $item) {
        return $carry + $item['total'];
    }, 0);
    
    // Calcular impuestos
    $totalIVA = 0;
    $totalIVA21 = 0;
    
    // Calcular recargo 3.5% si está marcado
    if (isset($data['incluirIVA']) && $data['incluirIVA']) {
        $totalIVA = round($subtotal * 0.035);
    }
    
    // Calcular IVA 21% si está marcado
    if (isset($data['incluirIVA21']) && $data['incluirIVA21']) {
        $totalIVA21 = round($subtotal * 0.21);
    }
    
    // Total final con impuestos
    $total = $subtotal + $totalIVA + $totalIVA21;
    
    // Añadir valor de envío si existe
    $valorEnvio = 0;
    if (isset($data['envio']) && is_numeric($data['envio'])) {
        $valorEnvio = floatval($data['envio']);
        $total += $valorEnvio;
    }

    // Insertar cabecera de venta con la fecha proporcionada por offline
    $sqlCabecera = "INSERT INTO ventas_cabecera (total, iva, iva21, metodo_pago, fecha_venta, costo_envio) VALUES (?, ?, ?, ?, ?, ?)";
    if ($conn->error) {
        throw new Exception("Error preparando SQL cabecera: " . $conn->error);
    }
    
    $stmtCabecera = $conn->prepare($sqlCabecera);
    if (!$stmtCabecera) {
        throw new Exception("Error preparando statement cabecera: " . $conn->error);
    }
    
    // Asegurarse de que IVA siempre tenga un valor (0 o 1)
    $iva = 0;
    if (isset($data['incluirIVA']) && $data['incluirIVA']) {
        $iva = 1;
    } else if (isset($data['items'][0]['iva'])) {
        $iva = (int)$data['items'][0]['iva'];
    }
    
    // Asegurarse de que IVA21 siempre tenga un valor (0 o 1)
    $iva21 = 0;
    if (isset($data['incluirIVA21']) && $data['incluirIVA21']) {
        $iva21 = 1;
    } else if (isset($data['items'][0]['iva21'])) {
        $iva21 = (int)$data['items'][0]['iva21'];
    }
    
    $metodoPago = $data['metodoPago'];
    
    // Usar la fecha de venta como parámetro
    $stmtCabecera->bind_param("diissd", $total, $iva, $iva21, $metodoPago, $fechaVenta, $valorEnvio);
    
    if (!$stmtCabecera->execute()) {
        throw new Exception("Error ejecutando cabecera: " . $stmtCabecera->error);
    }
    
    $transaccionId = $conn->insert_id;

    // Insertar detalles de venta
    $sqlDetalle = "INSERT INTO ventas (
        producto_id, 
        cantidad, 
        precio_unitario, 
        total, 
        iva, 
        precio_compra, 
        transaccion_id,
        categoria
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtDetalle = $conn->prepare($sqlDetalle);
    if (!$stmtDetalle) {
        throw new Exception("Error preparando statement detalle: " . $conn->error);
    }

    foreach ($data['items'] as $item) {
        // Obtener el precio_compra y categoría actual del producto
        $sqlProducto = "SELECT precio_compra, categoria FROM productos WHERE id = ?";
        $stmtProducto = $conn->prepare($sqlProducto);
        if (!$stmtProducto) {
            throw new Exception("Error preparando consulta producto: " . $conn->error);
        }
        
        $stmtProducto->bind_param("i", $item['id']);
        $stmtProducto->execute();
        $producto = $stmtProducto->get_result()->fetch_assoc();
        
        if (!$producto) {
            throw new Exception("No se encontró el producto con ID: {$item['id']}");
        }
        
        // Verificar stock
        $sqlStock = "SELECT stock FROM productos WHERE id = ? FOR UPDATE";
        $stmtStock = $conn->prepare($sqlStock);
        $stmtStock->bind_param("i", $item['id']);
        $stmtStock->execute();
        $result = $stmtStock->get_result();
        $stock = $result->fetch_assoc()['stock'];

        if ($stock < $item['cantidad']) {
            throw new Exception("Stock insuficiente para el producto ID: {$item['id']}");
        }

        // Asegurarse de que el IVA nunca sea NULL
        $itemIva = isset($item['iva']) ? $item['iva'] : 0;
        
        // Insertar detalle con el precio_compra y categoría
        $stmtDetalle->bind_param("iidddiss", 
            $item['id'],
            $item['cantidad'],
            $item['precio'],
            $item['total'],
            $itemIva, // Usamos el valor verificado
            $producto['precio_compra'],
            $transaccionId,
            $producto['categoria']
        );
        
        if (!$stmtDetalle->execute()) {
            throw new Exception("Error insertando detalle: " . $stmtDetalle->error);
        }

        // Actualizar stock
        $sqlUpdateStock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
        $stmtUpdateStock = $conn->prepare($sqlUpdateStock);
        $stmtUpdateStock->bind_param("ii", $item['cantidad'], $item['id']);
        
        if (!$stmtUpdateStock->execute()) {
            throw new Exception("Error actualizando stock: " . $stmtUpdateStock->error);
        }
    }

    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'transaccion_id' => $transaccionId,
        'message' => $esVentaOffline ? 'Venta offline sincronizada correctamente' : 'Venta procesada correctamente'
    ]);

} catch (Exception $e) {
    // Revertir cambios si hay error
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
    
    // Registrar error en log con más detalle
    error_log("Error procesando venta: " . $e->getMessage() . "\n" . 
              "Datos: " . json_encode($data));
}

$conn->close();
?>