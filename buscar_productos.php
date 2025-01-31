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
                                <img src="<?php echo $imagen; ?>" class="d-block w-100" 
                                     style="height: 300px; object-fit: cover;" alt="<?php echo $row['nombre']; ?>">
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="carousel-item active">
                            <img src="https://via.placeholder.com/400x300" class="d-block w-100" 
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
                    <strong>Stock:</strong> 
                    <span class="badge bg-<?php echo $row['stock'] < 5 ? ($row['stock'] == 0 ? 'danger' : 'warning') : 'success'; ?>">
                        <?php echo $row['stock']; ?> unidades
                    </span><br>
                    <strong>Precio:</strong> $<?php echo number_format($row['precio'], 2); ?>
                </p>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" 
                            onclick="editarProducto(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                        Editar
                    </button>
                    <button class="btn btn-danger" 
                            onclick="eliminarProducto(<?php echo $row['id']; ?>)">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

$conn->close();
?>
