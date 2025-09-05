<?php
require_once 'db.php';
require_once 'helpers/categorias_temporadas.php';
checkPermission();

// Obtener categorías de la nueva tabla
$categorias = obtenerCategoriasNuevas();

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h3>🧪 Test del Sistema de Categorías</h3>
        </div>
        <div class="card-body">
            <h5>Categorías Disponibles:</h5>
            <?php if (!empty($categorias)): ?>
                <ul class="list-group mb-3">
                    <?php foreach ($categorias as $categoria): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo h($categoria['nombre']); ?></strong>
                                <br><small class="text-muted"><?php echo h($categoria['descripcion']); ?></small>
                            </div>
                            <span class="badge bg-primary">ID: <?php echo $categoria['id']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="alert alert-warning">No hay categorías disponibles en la tabla categorias.</div>
            <?php endif; ?>
            
            <h5>Test de Select de Categorías:</h5>
            <?php echo generarSelectCategoriasConNueva('test_categoria', null, [
                'class' => 'form-control mb-3',
                'onchange' => 'console.log("Seleccionado:", this.value)'
            ]); ?>
            
            <h5>Test de Select de Temporadas:</h5>
            <?php echo generarSelectTemporadas('test_temporada', null, true, [
                'class' => 'form-control mb-3'
            ]); ?>
            
            <div class="mt-3">
                <a href="subir_foto.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Probar Formulario de Producto
                </a>
                <a href="administrar.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Ver Productos
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
