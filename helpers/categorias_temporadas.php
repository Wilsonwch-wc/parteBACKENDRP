<?php
/**
 * FUNCIONES HELPER PARA CATEGORÍAS Y TEMPORADAS
 * 
 * Este archivo contiene funciones útiles para trabajar con el nuevo sistema 
 * de categorías y temporadas implementado en el sistema.
 * 
 * @author Sistema de Gestión de Ropa
 * @version 1.0
 * @date 2025-01-21
 */

require_once 'db.php';

/**
 * Obtener todas las categorías activas desde la tabla categorias
 * 
 * @return array Lista de categorías con id, nombre y descripción
 */
function obtenerCategoriasNuevas() {
    $conn = getDB();
    if (!$conn) {
        return [];
    }
    
    $sql = "SELECT id, nombre, descripcion FROM categorias WHERE activo = 1 ORDER BY nombre";
    $result = $conn->query($sql);
    
    $categorias = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row;
        }
    }
    
    return $categorias;
}

/**
 * Obtener categorías de la tabla productos (para compatibilidad hacia atrás)
 * 
 * @return array Lista de categorías únicas desde la tabla productos
 */
function obtenerCategoriasLegacy() {
    $conn = getDB();
    if (!$conn) {
        return [];
    }
    
    $sql = "SELECT DISTINCT categoria FROM productos WHERE categoria != '' AND categoria IS NOT NULL ORDER BY categoria";
    $result = $conn->query($sql);
    
    $categorias = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row['categoria'];
        }
    }
    
    return $categorias;
}

/**
 * Obtener todas las categorías (combinando nueva tabla y legacy)
 * 
 * @return array Lista combinada de categorías
 */
function obtenerTodasLasCategorias() {
    // Primero intentar obtener de la nueva tabla
    $categoriasNuevas = obtenerCategoriasNuevas();
    
    if (!empty($categoriasNuevas)) {
        return array_map(function($cat) {
            return $cat['nombre'];
        }, $categoriasNuevas);
    }
    
    // Si no hay categorías nuevas, usar las legacy
    return obtenerCategoriasLegacy();
}

/**
 * Obtener las opciones de temporada disponibles
 * 
 * @return array Array con las opciones de temporada
 */
function obtenerTemporadas() {
    return [
        'verano' => 'Verano',
        'invierno' => 'Invierno'
    ];
}

/**
 * Validar que una temporada sea válida
 * 
 * @param string $temporada Temporada a validar
 * @return bool True si es válida, false si no
 */
function esTemporadaValida($temporada) {
    $temporadasValidas = ['verano', 'invierno'];
    return in_array(strtolower($temporada), $temporadasValidas);
}

/**
 * Obtener productos filtrados por categoría y/o temporada
 * 
 * @param string|null $categoria Categoría a filtrar (opcional)
 * @param string|null $temporada Temporada a filtrar (opcional)
 * @param int $limit Límite de resultados (default: 50)
 * @param int $offset Offset para paginación (default: 0)
 * @return array Array con productos filtrados
 */
function obtenerProductosFiltrados($categoria = null, $temporada = null, $limit = 50, $offset = 0) {
    $conn = getDB();
    if (!$conn) {
        return [];
    }
    
    $sql = "SELECT * FROM productos WHERE estado = 1";
    $params = [];
    $types = "";
    
    if ($categoria !== null && $categoria !== '') {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
        $types .= "s";
    }
    
    if ($temporada !== null && $temporada !== '' && esTemporadaValida($temporada)) {
        $sql .= " AND temporada = ?";
        $params[] = strtolower($temporada);
        $types .= "s";
    }
    
    $sql .= " ORDER BY nombre LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if ($stmt && !empty($params)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
        
        return $productos;
    }
    
    return [];
}

/**
 * Contar productos por categoría y temporada (para estadísticas)
 * 
 * @return array Estadísticas de productos por categoría y temporada
 */
function obtenerEstadisticasCategoriasTemporadas() {
    $conn = getDB();
    if (!$conn) {
        return [];
    }
    
    $sql = "SELECT 
                categoria,
                temporada,
                COUNT(*) as total_productos,
                SUM(stock) as total_stock,
                AVG(precio) as precio_promedio
            FROM productos 
            WHERE estado = 1 
            GROUP BY categoria, temporada
            ORDER BY categoria, temporada";
    
    $result = $conn->query($sql);
    $estadisticas = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $estadisticas[] = [
                'categoria' => $row['categoria'],
                'temporada' => $row['temporada'] ?? 'Sin definir',
                'total_productos' => (int)$row['total_productos'],
                'total_stock' => (int)$row['total_stock'],
                'precio_promedio' => round((float)$row['precio_promedio'], 2)
            ];
        }
    }
    
    return $estadisticas;
}

/**
 * Migrar categorías de texto libre a la tabla categorias
 * 
 * Esta función ayuda a migrar las categorías existentes en el campo 'categoria' 
 * de la tabla productos hacia la nueva tabla 'categorias'
 * 
 * @return array Resultado de la migración
 */
function migrarCategoriasLegacy() {
    $conn = getDB();
    if (!$conn) {
        return ['success' => false, 'error' => 'No hay conexión a la base de datos'];
    }
    
    try {
        // Obtener categorías únicas de productos
        $categoriasLegacy = obtenerCategoriasLegacy();
        
        if (empty($categoriasLegacy)) {
            return ['success' => true, 'message' => 'No hay categorías legacy para migrar'];
        }
        
        $migradas = 0;
        $duplicadas = 0;
        
        foreach ($categoriasLegacy as $categoria) {
            $stmt = $conn->prepare("INSERT IGNORE INTO categorias (nombre, descripcion) VALUES (?, ?)");
            $descripcion = "Migrada automáticamente desde sistema legacy";
            $stmt->bind_param("ss", $categoria, $descripcion);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $migradas++;
            } else {
                $duplicadas++;
            }
        }
        
        return [
            'success' => true,
            'migradas' => $migradas,
            'duplicadas' => $duplicadas,
            'total_procesadas' => count($categoriasLegacy)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Generar select HTML para categorías
 * 
 * @param string $name Nombre del campo select
 * @param string|null $selected Valor seleccionado
 * @param bool $incluirTodas Si incluir opción "Todas las categorías"
 * @param array $attributes Atributos adicionales para el select
 * @return string HTML del select
 */
function generarSelectCategorias($name, $selected = null, $incluirTodas = false, $attributes = []) {
    $categorias = obtenerTodasLasCategorias();
    
    $attrs = '';
    foreach ($attributes as $attr => $value) {
        $attrs .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
    }
    
    $html = '<select name="' . htmlspecialchars($name) . '"' . $attrs . '>';
    
    if ($incluirTodas) {
        $html .= '<option value="">Todas las categorías</option>';
    }
    
    foreach ($categorias as $categoria) {
        $selectedAttr = ($selected === $categoria) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($categoria) . '"' . $selectedAttr . '>';
        $html .= htmlspecialchars($categoria) . '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Generar select HTML para temporadas
 * 
 * @param string $name Nombre del campo select
 * @param string|null $selected Valor seleccionado
 * @param bool $incluirTodas Si incluir opción "Todas las temporadas"
 * @param array $attributes Atributos adicionales para el select
 * @return string HTML del select
 */
function generarSelectTemporadas($name, $selected = null, $incluirTodas = false, $attributes = []) {
    $temporadas = obtenerTemporadas();
    
    $attrs = '';
    foreach ($attributes as $attr => $value) {
        $attrs .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
    }
    
    $html = '<select name="' . htmlspecialchars($name) . '"' . $attrs . '>';
    
    if ($incluirTodas) {
        $html .= '<option value="">Todas las temporadas</option>';
    }
    
    foreach ($temporadas as $value => $label) {
        $selectedAttr = ($selected === $value) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($value) . '"' . $selectedAttr . '>';
        $html .= htmlspecialchars($label) . '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Crear una nueva categoría en la tabla categorias
 * 
 * @param string $nombre Nombre de la categoría
 * @param string $descripcion Descripción de la categoría (opcional)
 * @return array Resultado de la operación
 */
function crearNuevaCategoria($nombre, $descripcion = '') {
    $conn = getDB();
    if (!$conn) {
        return ['success' => false, 'error' => 'No hay conexión a la base de datos'];
    }
    
    try {
        // Validar que el nombre no esté vacío
        $nombre = trim($nombre);
        if (empty($nombre)) {
            return ['success' => false, 'error' => 'El nombre de la categoría no puede estar vacío'];
        }
        
        // Verificar si la categoría ya existe
        $stmt = $conn->prepare("SELECT id FROM categorias WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'error' => 'La categoría ya existe'];
        }
        
        // Crear la nueva categoría
        $descripcion = empty($descripcion) ? "Categoría creada por el usuario" : $descripcion;
        $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $descripcion);
        
        if ($stmt->execute()) {
            return [
                'success' => true, 
                'id' => $conn->insert_id,
                'mensaje' => 'Categoría creada exitosamente'
            ];
        } else {
            return ['success' => false, 'error' => 'Error al crear la categoría'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Generar select HTML para categorías con opción de crear nueva
 * 
 * @param string $name Nombre del campo select
 * @param string|null $selected Valor seleccionado
 * @param array $attributes Atributos adicionales para el select
 * @return string HTML del select
 */
function generarSelectCategoriasConNueva($name, $selected = null, $attributes = []) {
    $categorias = obtenerCategoriasNuevas();
    
    $attrs = '';
    foreach ($attributes as $attr => $value) {
        $attrs .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
    }
    
    $html = '<select name="' . htmlspecialchars($name) . '"' . $attrs . '>';
    $html .= '<option value="">Seleccionar categoría...</option>';
    
    foreach ($categorias as $categoria) {
        $selectedAttr = ($selected === $categoria['nombre']) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($categoria['nombre']) . '"' . $selectedAttr . '>';
        $html .= htmlspecialchars($categoria['nombre']) . '</option>';
    }
    
    $html .= '<option value="__NUEVA__">+ Crear nueva categoría...</option>';
    $html .= '</select>';
    
    return $html;
}
?>
