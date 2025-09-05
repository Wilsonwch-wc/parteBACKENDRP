<?php
require_once 'db.php';
require_once 'includes/header.php';
require_once 'helpers/categorias_temporadas.php';

// Verificar que el usuario esté logueado y sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Usar la función del helper para obtener categorías

// Procesar acciones CRUD
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (!empty($nombre)) {
                $conn = getDB();
                $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
                $stmt->bind_param("ss", $nombre, $descripcion);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría creada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al crear la categoría: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
                $stmt->close();
            } else {
                $mensaje = "El nombre de la categoría es obligatorio";
                $tipo_mensaje = "warning";
            }
            break;
            
        case 'editar':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if ($id > 0 && !empty($nombre)) {
                $conn = getDB();
                $stmt = $conn->prepare("UPDATE categorias SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
                $stmt->bind_param("ssii", $nombre, $descripcion, $activo, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría actualizada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar la categoría: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
                $stmt->close();
            } else {
                $mensaje = "Datos inválidos para editar la categoría";
                $tipo_mensaje = "warning";
            }
            break;
            
        case 'eliminar':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id > 0) {
                $conn = getDB();
                
                // Verificar si la categoría está siendo usada
                $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?");
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $count = $result_check->fetch_assoc()['count'];
                $stmt_check->close();
                
                if ($count > 0) {
                    $mensaje = "No se puede eliminar la categoría porque tiene productos asociados";
                    $tipo_mensaje = "warning";
                } else {
                    $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Categoría eliminada exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al eliminar la categoría: " . $conn->error;
                        $tipo_mensaje = "danger";
                    }
                    $stmt->close();
                }
            }
            break;
    }
}

// Obtener todas las categorías con todos los campos
$conn = getDB();
$sql = "SELECT id, nombre, descripcion, activo, fecha_creacion FROM categorias ORDER BY nombre";
$result = $conn->query($sql);

$categorias = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}
?>

<style>
    .badge-activo {
        background-color: #28a745;
        color: white;
        padding: 0.25em 0.6em;
        border-radius: 0.25rem;
    }
    .badge-inactivo {
        background-color: #dc3545;
        color: white;
        padding: 0.25em 0.6em;
        border-radius: 0.25rem;
    }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-tags me-2"></i>Gestión de Categorías</h2>
            <p class="text-muted">Administra las categorías de productos del sistema</p>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulario para crear nueva categoría -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nueva Categoría</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <?php echo csrf_field(); ?>
                            
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i>Crear Categoría
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de categorías existentes -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Categorías Existentes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categorias)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No hay categorías registradas</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Estado</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <tr>
                                                <td><?php echo $categoria['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($categoria['nombre']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($categoria['descripcion'] ?? 'Sin descripción'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $categoria['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                        <?php echo $categoria['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($categoria['fecha_creacion'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                                            onclick="editarCategoria(<?php echo $categoria['id']; ?>, '<?php echo addslashes($categoria['nombre']); ?>', '<?php echo addslashes($categoria['descripcion'] ?? ''); ?>', <?php echo $categoria['activo'] ? 'true' : 'false'; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="eliminarCategoria(<?php echo $categoria['id']; ?>, '<?php echo htmlspecialchars($categoria['nombre']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar categoría -->
    <div class="modal fade" id="modalEditarCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditarCategoria">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="editId">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="editNombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="editNombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDescripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="editDescripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="editActivo" name="activo">
                                <label class="form-check-label" for="editActivo">
                                    Categoría activa
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar eliminación -->
    <div class="modal fade" id="modalEliminarCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar la categoría <strong id="nombreEliminar"></strong>?</p>
                    <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
    function editarCategoria(id, nombre, descripcion, activo) {
        document.getElementById('editId').value = id;
        document.getElementById('editNombre').value = nombre;
        document.getElementById('editDescripcion').value = descripcion || '';
        document.getElementById('editActivo').checked = activo;
        
        new bootstrap.Modal(document.getElementById('modalEditarCategoria')).show();
    }
    
    function eliminarCategoria(id, nombre) {
        document.getElementById('eliminarId').value = id;
        document.getElementById('nombreEliminar').textContent = nombre;
        
        new bootstrap.Modal(document.getElementById('modalEliminarCategoria')).show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>