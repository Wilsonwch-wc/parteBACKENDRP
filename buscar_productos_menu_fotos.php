<?php
require_once 'db.php';
$conn = getDB();

if (!$conn) {
    die("Error de conexión con la base de datos");
}

$query = $_GET['q'] ?? '';

$sql = "SELECT p.*, GROUP_CONCAT(i.ruta_imagen) as imagenes 
        FROM productos p 
        LEFT JOIN imagenes_producto i ON p.id = i.producto_id 
        WHERE p.nombre LIKE ? OR p.codigo LIKE ? OR p.categoria LIKE ? 
        GROUP BY p.id 
        ORDER BY p.nombre";

$stmt = $conn->prepare($sql);
$searchTerm = '%' . $query . '%';
$stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $imagenes = explode(',', $row['imagenes']);
    ?>
    <div class="col">
        <div class="card h-100">
            <div class="card-header">
                <small class="text-muted">Código: <?php echo htmlspecialchars($row['codigo']); ?></small>
            </div>
            <!-- Carrusel de imágenes -->
            <div id="carousel<?php echo $row['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php
                    if (!empty($imagenes[0])) {
                        foreach($imagenes as $index => $imagen) {
                            ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo $imagen; ?>" class="d-block w-100 img-fluid" 
                                     style="height: 300px; object-fit: cover; cursor: pointer;" 
                                     alt="<?php echo $row['nombre']; ?>"
                                     onclick='agregarAlCarrito({
                                         "id": <?php echo $row["id"]; ?>,
                                         "codigo": "<?php echo addslashes($row["codigo"]); ?>",
                                         "nombre": "<?php echo addslashes($row["nombre"]); ?>",
                                         "precio": <?php echo floatval($row["precio"]); ?>,
                                         "stock": <?php echo intval($row["stock"]); ?>
                                     })'>
                            </div>
                            <?php
                        }
                    } else {
                        // Imagen por defecto si no hay imágenes
                        ?>
                        <div class="carousel-item active">
                            <img src="https://via.placeholder.com/400x300" class="d-block w-100 img-fluid" 
                                 style="height: 300px; object-fit: cover;" alt="Sin imagen">
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <?php if (count($imagenes) > 1) { ?>
                    <button class="carousel-control-prev" type="button" 
                            data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" 
                            data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                <?php } ?>
            </div>
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($row['nombre']); ?></h5>
                <p class="card-text">
                    <strong>Categoría:</strong> <?php echo htmlspecialchars($row['categoria']); ?><br>
                    <strong>Colores:</strong> <?php echo htmlspecialchars($row['colores']); ?><br>
                    <strong>Stock:</strong> <span class="badge bg-<?php echo $row['stock'] > 0 ? 'success' : 'danger'; ?>">
                        <?php echo htmlspecialchars($row['stock']); ?> unidades
                    </span><br>
                    <strong>Precio:</strong> $<?php echo number_format($row['precio'], 2); ?>
                </p>
                <?php if ($row['stock'] > 0) : ?>
                <button class="btn btn-primary w-100" 
                        onclick='agregarAlCarrito({
                            "id": <?php echo $row["id"]; ?>,
                            "codigo": "<?php echo addslashes($row["codigo"]); ?>",
                            "nombre": "<?php echo addslashes($row["nombre"]); ?>",
                            "precio": <?php echo floatval($row["precio"]); ?>,
                            "stock": <?php echo intval($row["stock"]); ?>
                        })'>
                    Agregar al Carrito
                </button>
                <?php else: ?>
                <button class="btn btn-secondary w-100" disabled>Sin Stock</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

$conn->close();
?>
