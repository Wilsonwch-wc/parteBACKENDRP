<?php
require_once 'db.php';

// Verificar permisos
if (!isLoggedIn() || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin')) {
    header("Location: menu_fotos.php"); // Redirigir a menú de fotos si no tiene permiso
    exit();
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Agregar Nuevo Producto</h2>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <script>
        setTimeout(function() {
            document.querySelector('.alert-success').style.display = 'none';
        }, 2000); // Ocultar después de 5 segundos
    </script>
    <?php endif; ?>
    
    <form action="procesar_producto.php" method="POST" enctype="multipart/form-data" class="mt-4">
        <!-- Información Básica -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información Básica</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="codigo" class="form-label">Código *</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required 
                               oninvalid="this.setCustomValidity('Debe ingresar el código del producto')"
                               oninput="this.setCustomValidity('')"
                               placeholder="Ingrese el código del producto"
                               value="<?php echo isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Descripción del Producto *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               placeholder="Ej: Gorra Nike Sportswear Heritage86" required
                               maxlength="38"
                               oninvalid="this.setCustomValidity('La descripción no debe exceder los 38 caracteres')"
                               oninput="this.setCustomValidity('')"
                               value="<?php echo isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="categoria" class="form-label">Categoría *</label>
                        <input type="text" class="form-control" id="categoria" name="categoria"
                               placeholder="Ej: GORRA PLANA, GORRA CURVA, SOMBRERO, etc." required
                               value="<?php echo isset($_GET['categoria']) ? htmlspecialchars($_GET['categoria']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventario y Precio -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Inventario y Precio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="precio_compra" class="form-label">Precio de Compra ($) *</label>
                        <input type="number" class="form-control" id="precio_compra" name="precio_compra" 
                               step="0.01" min="0" required
                               onwheel="return false;" 
                               placeholder="Ingrese el precio de compra"
                               value="<?php echo isset($_GET['precio_compra']) ? htmlspecialchars($_GET['precio_compra']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="precio" class="form-label">Precio ($) *</label>
                        <input type="number" class="form-control" id="precio" name="precio" 
                               step="0.01" min="0" required
                               onwheel="return false;" 
                               placeholder="Ingrese el precio"
                               value="<?php echo isset($_GET['precio']) ? htmlspecialchars($_GET['precio']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="stock" class="form-label">Stock Inicial *</label>
                        <input type="number" class="form-control" id="stock" name="stock" 
                               min="0" required
                               onwheel="return false;" 
                               placeholder="Ingrese el stock inicial"
                               value="<?php echo isset($_GET['stock']) ? htmlspecialchars($_GET['stock']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Variantes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Variantes Disponibles</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="colores" class="form-label">Colores Disponibles (separados por coma)</label>
                    <input type="text" class="form-control" id="colores" name="colores"
                           placeholder="Ej: NEGRO, ROJO, CAMUFLADO"
                           value="<?php echo isset($_GET['colores']) ? htmlspecialchars($_GET['colores']) : ''; ?>">
                    <div class="form-text">Ingrese los colores separados por comas</div>
                </div>
            </div>
        </div>

        <!-- Imágenes del Producto -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Imágenes del Producto</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="imagenes" class="form-label">Seleccionar múltiples imágenes </label>
                    <input type="file" class="form-control" id="imagenes" name="imagenes[]" 
                           multiple accept="image/*" >
                </div>
                
                <!-- Preview de imágenes -->
                <div id="imagePreview" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <!-- Las imágenes se mostrarán aquí -->
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#imagePreview" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#imagePreview" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="text-end mb-4">
            <button type="submit" class="btn btn-primary">Guardar Producto</button>
        </div>
    </form>
</div>

<script>
// Preview de imágenes
document.getElementById('imagenes').addEventListener('change', function(event) {
    const preview = document.querySelector('.carousel-inner');
    preview.innerHTML = ''; // Limpiar preview existente
    
    const files = event.target.files;
    
    for(let i = 0; i < files.length; i++) {
        const file = files[i];
        if (!file.type.startsWith('image/')) continue;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'carousel-item' + (i === 0 ? ' active' : '');
            div.innerHTML = `
                <img src="${e.target.result}" class="d-block w-100" style="height: 400px; object-fit: contain;">
            `;
            preview.appendChild(div);
        }
        reader.readAsDataURL(file);
    }
});
</script>

<style>
    /* Deshabilitar las flechas del input number - versión compatible */
    /* Firefox */
    input[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }
    
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        appearance: none;
        margin: 0;
    }
    
    /* Para IE */
    input[type=number]::-ms-clear,
    input[type=number]::-ms-reveal {
        display: none;
    }
</style>

<?php include 'includes/footer.php'; ?>