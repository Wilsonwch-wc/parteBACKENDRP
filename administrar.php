<?php
require_once 'db.php';
checkPermission('admin');
include 'includes/header.php';

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

?>

<div class="container mt-4">
    <h2>Administrar Productos</h2>

    <?php
    // Mensaje de éxito o error
    if (isset($_GET['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                Producto actualizado correctamente
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }
    if (isset($_GET['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error al actualizar el producto
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }
    ?>

    <div class="card">
        <div class="card-body">
            <!-- Buscador -->
            <div class="mb-4">
                <input type="text" id="buscador" class="form-control" placeholder="Buscar por nombre, código, etc.">
            </div>
            
            <!-- Agregar vista en cuadrícula similar a menu_fotos -->
            <div class="row row-cols-1 row-cols-md-3 g-4 mb-4" id="productosGrid">
                <?php
               $sql = "SELECT p.*, GROUP_CONCAT(i.ruta_imagen) as imagenes 
               FROM productos p 
               LEFT JOIN imagenes_producto i ON p.id = i.producto_id  
               GROUP BY p.id 
               ORDER BY p.nombre";
                $result = $conn->query($sql);

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
                                    <strong>Precio:</strong> $<?php echo number_format($row['precio']); ?><br>
                                    <strong>Precio de Compra:</strong> $<?php echo number_format($row['precio_compra']); ?><br>
                                    <strong>Estado:</strong> <?php echo $row['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                </p>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" 
                                            onclick="editarProducto(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        Editar
                                    </button>
                                    <button class="btn <?php echo $row['estado'] == 1 ? 'btn-warning' : 'btn-success'; ?>" 
                                            onclick="cambiarEstadoProducto(<?php echo $row['id']; ?>, <?php echo $row['estado'] == 1 ? 0 : 1; ?>)">
                                        <?php echo $row['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edición -->
<div class="modal fade" id="editarModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="actualizar_producto.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="hidden" name="id" id="edit_id">
                            
                            <div class="mb-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" name="codigo" id="edit_codigo" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Descripción del Producto</label>
                                <input type="text" class="form-control" name="nombre" id="edit_nombre" required maxlength="37"
                                       oninvalid="this.setCustomValidity('La descripción no debe exceder los 38 caracteres')"
                                       oninput="this.setCustomValidity('')">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <input type="text" class="form-control" name="categoria" id="edit_categoria" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Stock</label>
                                <input type="number" class="form-control" name="stock" id="edit_stock" required min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Precio</label>
                                <input type="number" class="form-control" name="precio" id="edit_precio" required min="0" step="0.01">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Precio de Compra</label>
                                <input type="number" class="form-control" name="precio_compra" id="edit_precio_compra" required min="0" step="0.01">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Colores (separados por coma)</label>
                                <input type="text" class="form-control" name="colores" id="edit_colores" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Imágenes Actuales</label>
                                <div id="imagenesActuales" class="mb-2">
                                    <!-- Las imágenes actuales se mostrarán aquí -->
                                </div>
                                <label class="form-label">Agregar Nuevas Imágenes (Opcional)</label>
                                <input type="file" class="form-control" name="nuevas_imagenes[]" 
                                       multiple accept="image/*">
                                <small class="text-muted">Puede seleccionar múltiples imágenes</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarProducto(producto) {
    document.getElementById('edit_id').value = producto.id;
    document.getElementById('edit_codigo').value = producto.codigo;
    document.getElementById('edit_nombre').value = producto.nombre;
    document.getElementById('edit_categoria').value = producto.categoria;
    document.getElementById('edit_stock').value = producto.stock;
    document.getElementById('edit_precio').value = producto.precio;
    document.getElementById('edit_precio_compra').value = producto.precio_compra;
    document.getElementById('edit_colores').value = producto.colores;
    
    // Mostrar imágenes actuales
    const imagenesDiv = document.getElementById('imagenesActuales');
    imagenesDiv.innerHTML = '';
    
    if (producto.imagenes) {
        const imagenes = producto.imagenes.split(',');
        imagenes.forEach((imagen, index) => {
            const imgContainer = document.createElement('div');
            imgContainer.className = 'position-relative d-inline-block me-2 mb-2';
            imgContainer.innerHTML = `
                <img src="${imagen}" alt="Imagen ${index + 1}" 
                     style="height: 100px; width: 100px; object-fit: cover;">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0"
                        onclick="eliminarImagen(${producto.id}, '${imagen}')">×</button>
            `;
            imagenesDiv.appendChild(imgContainer);
        });
    }
    
    new bootstrap.Modal(document.getElementById('editarModal')).show();
}

function cambiarEstadoProducto(id, nuevoEstado) {
    if (confirm('¿Estás seguro de que quieres cambiar el estado de este producto?')) {
        window.location.href = 'cambiar_estado_producto.php?id=' + id + '&estado=' + nuevoEstado;
    }
}

function eliminarImagen(productoId, rutaImagen) {
    if (confirm('¿Estás seguro de que quieres eliminar esta imagen?')) {
        fetch('eliminar_imagen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `producto_id=${productoId}&ruta_imagen=${encodeURIComponent(rutaImagen)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar la imagen');
            }
        });
    }
}

document.getElementById('buscador').addEventListener('input', function() {
    const query = this.value;
    fetch(`buscar_productos.php?q=${encodeURIComponent(query)}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('productosGrid').innerHTML = html;
        });
});
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>