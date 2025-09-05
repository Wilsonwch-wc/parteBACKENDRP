<?php
require_once 'db.php';
require_once 'helpers/categorias_temporadas.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar si el usuario tiene permisos
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'user') {
    header("Location: index.php?error=No tienes permisos para acceder a esta página");
    exit;
}

// Definir la función h() si no existe
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Definir la función csrf_field() si no existe
if (!function_exists('csrf_field')) {
    function csrf_field() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }
}

$conn = getDB();

// Obtener categorías para el formulario
$sqlCategorias = "SELECT DISTINCT categoria FROM productos WHERE categoria != '' ORDER BY categoria";
$resultCategorias = $conn->query($sqlCategorias);
$categorias = [];

if ($resultCategorias && $resultCategorias->num_rows > 0) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $categorias[] = $row['categoria'];
    }
}

// Incluir header para obtener estilos y menú
include 'includes/header.php';
?>

<div class="container mt-4">
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo h($_GET['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo h($_GET['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <script>
        setTimeout(function() {
            document.querySelector('.alert-success').style.display = 'none';
        }, 2000); // Ocultar después de 5 segundos
    </script>
    <?php endif; ?>
    
    <form action="procesar_producto.php" method="POST" enctype="multipart/form-data" class="mt-4">
        <?php echo csrf_field(); ?>
        <!-- Información Básica -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información Básica</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="codigo" class="form-label">Código *</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required 
                               pattern="[A-Za-z0-9-]+" maxlength="20"
                               oninvalid="this.setCustomValidity('Por favor ingrese un código válido (letras, números y guiones)')"
                               oninput="this.value = this.value.toUpperCase(); this.setCustomValidity('')"
                               placeholder="Ingrese el código del producto"
                               value="<?php echo isset($_GET['codigo']) ? h($_GET['codigo']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               maxlength="38"
                               oninvalid="this.setCustomValidity('La descripción no debe exceder los 38 caracteres')"
                               oninput="this.setCustomValidity('')"
                               value="<?php echo isset($_GET['nombre']) ? h($_GET['nombre']) : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="categoria" class="form-label">Categoría *</label>
                        <?php echo generarSelectCategoriasConNueva('categoria', isset($_GET['categoria']) ? $_GET['categoria'] : null, [
                            'id' => 'categoria',
                            'class' => 'form-control',
                            'required' => 'required',
                            'onchange' => 'toggleNuevaCategoria()'
                        ]); ?>
                        
                        <!-- Input para nueva categoría (oculto inicialmente) -->
                        <div id="nuevaCategoriaContainer" class="mt-2" style="display: none;">
                            <input type="text" class="form-control" id="nuevaCategoria" name="nueva_categoria" 
                                   placeholder="Nombre de la nueva categoría"
                                   maxlength="100">
                            <small class="form-text text-muted">Ingrese el nombre de la nueva categoría</small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="temporada" class="form-label">Temporada</label>
                        <?php echo generarSelectTemporadas('temporada', isset($_GET['temporada']) ? $_GET['temporada'] : null, true, [
                            'id' => 'temporada',
                            'class' => 'form-control'
                        ]); ?>
                        <small class="form-text text-muted">Seleccione la temporada del producto (opcional)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenedor de fila para Inventario/Precio y Variantes -->
        <div class="row mb-4">
            <!-- Inventario y Precio -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Inventario y Precio</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="precio_compra" class="form-label">Precio de Compra ($) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control precio-input" id="precio_compra" name="precio_compra" 
                                           inputmode="numeric" required
                                           placeholder="Ingrese el precio de compra"
                                           value="<?php echo isset($_GET['precio_compra']) ? h($_GET['precio_compra']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="precio" class="form-label">Precio de Venta ($) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control precio-input" id="precio" name="precio" 
                                           inputmode="numeric" required
                                           placeholder="Ingrese el precio"
                                           value="<?php echo isset($_GET['precio']) ? h($_GET['precio']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stock" class="form-label">Stock Inicial *</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       min="0" required
                                       onwheel="return false;" 
                                       placeholder="Ingrese el stock inicial"
                                       value="<?php echo isset($_GET['stock']) ? h($_GET['stock']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variantes -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Variantes Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="colores" class="form-label">Colores Disponibles</label>
                            <input type="text" class="form-control" id="colores" name="colores"
                                   placeholder="Ej: NEGRO, ROJO, CAMUFLADO"
                                   value="<?php echo isset($_GET['colores']) ? h($_GET['colores']) : ''; ?>">
                            <div class="form-text">Ingrese los colores separados por comas</div>
                        </div>
                    </div>
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
                    <label class="form-label">Agregar imágenes</label>
                    <div class="d-grid gap-2">
                        <!-- Botón para tomar foto -->
                        <button type="button" class="btn btn-primary" id="btnTakePhoto">
                            <i class="fas fa-camera me-2"></i>Tomar Foto
                        </button>
                        <!-- Botón para seleccionar de galería -->
                        <button type="button" class="btn btn-secondary" id="btnSelectFiles">
                            <i class="fas fa-images me-2"></i>Seleccionar de Galería
                        </button>
                    </div>
                    <!-- Input file oculto -->
                    <input type="file" class="form-control d-none" id="imagenes" name="imagenes[]" 
                           multiple accept="image/*">
                </div>
                
                <!-- Preview de imágenes -->
                <div id="imagePreviewContainer" class="row g-2 mb-3"></div>
            </div>
        </div>

        <!-- Agregar un indicador de estado en el formulario -->
        <div class="connection-status-form mb-3">
            <span id="onlineIndicatorForm" class="badge bg-success">
                <i class="fas fa-wifi"></i> En línea (Los productos se subirán directamente)
            </span>
            <span id="offlineIndicatorForm" class="badge bg-warning d-none">
                <i class="fas fa-exclamation-triangle"></i> Sin conexión (Los productos se guardarán localmente)
            </span>
        </div>

        <div class="text-end mb-4">
            <button type="submit" class="btn btn-primary">Guardar Producto</button>
        </div>
    </form>
</div>

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

.preview-container {
    position: relative;
    margin-bottom: 10px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.preview-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}

.remove-image {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 0, 0, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: all 0.2s ease;
}

.remove-image:hover {
    background: rgba(255, 0, 0, 1);
    transform: scale(1.1);
}

.btn-take-photo, .btn-select-files {
    width: 100%;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
}

.atajo-teclado {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    opacity: 0.8;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.atajo-teclado kbd {
    background-color: #eee;
    border-radius: 3px;
    border: 1px solid #b4b4b4;
    box-shadow: 0 1px 1px rgba(0,0,0,.2);
    color: #333;
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    padding: 2px 4px;
    white-space: nowrap;
}

/* Loader para carga de imágenes */
.loader-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}

.loader-container.active {
    opacity: 1;
    visibility: visible;
}

.loader {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 8px solid #f3f3f3;
    border-top: 8px solid #0d6efd;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Mensaje no invasivo sobre los atajos de teclado -->
<div class="atajo-teclado">
    Atajos: <kbd>Enter</kbd> siguiente campo | <kbd>Ctrl</kbd>+<kbd>Enter</kbd> guardar
</div>

<!-- Elemento de carga para subida de archivos -->
<div class="loader-container" id="loaderContainer">
    <div class="loader"></div>
</div>

<script>
let imageFiles = new DataTransfer();

// Añadir evento para saltar al siguiente campo con Enter
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input:not([type="hidden"]):not([type="file"]), select, textarea');
    const formulario = document.querySelector('form');
    
    inputs.forEach((input, index) => {
        input.addEventListener('keydown', function(e) {
            // Si se presiona Enter sin Ctrl
            if (e.key === 'Enter' && !e.ctrlKey) {
                e.preventDefault(); // Evitar envío del formulario
                
                // Buscar el siguiente input en el formulario
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            }
            // Si se presiona Ctrl + Enter
            else if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                formulario.submit(); // Enviar el formulario
            }
        });
    });
    
    // Agregar evento a nivel de documento para Ctrl + Enter
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            formulario.submit(); // Enviar el formulario
        }
    });
    
    // Ocultar el mensaje de atajo después de unos segundos
    setTimeout(function() {
        const mensaje = document.querySelector('.atajo-teclado');
        mensaje.style.opacity = '0';
        mensaje.style.transition = 'opacity 0.5s ease';
        
        // Quitar del DOM después de la transición
        setTimeout(() => mensaje.style.display = 'none', 500);
    }, 7000);
});

// Manejador para el botón de tomar foto
document.getElementById('btnTakePhoto').addEventListener('click', function() {
    const input = document.getElementById('imagenes');
    input.removeAttribute('multiple'); // Desactivar múltiple para la cámara
    input.setAttribute('capture', 'environment');
    input.click();
});

// Manejador para el botón de seleccionar archivos
document.getElementById('btnSelectFiles').addEventListener('click', function() {
    const input = document.getElementById('imagenes');
    input.setAttribute('multiple', ''); // Activar múltiple para galería
    input.removeAttribute('capture'); // Quitar capture para permitir selección
    input.click();
});

document.getElementById('imagenes').addEventListener('change', async function(e) {
    const files = Array.from(e.target.files);

    // Clear the DataTransfer object to avoid duplicates
    imageFiles = new DataTransfer();

    if (files.length > 0) {
        // Mostrar loader con mensaje de procesamiento
        document.getElementById('loaderContainer').classList.add('active');
        updateLoaderMessage('Preparando imágenes...');

        // Agregar nuevos archivos al DataTransfer
        files.forEach(file => {
            // Verificar si es una imagen
            if (!file.type.startsWith('image/')) {
                alert('Por favor, seleccione solo archivos de imagen');
                return;
            }
            
           // Verificar tamaño (máximo 20MB por imagen)
            if (file.size > 20 * 1024 * 1024) {
                alert('El archivo ' + file.name + ' es demasiado grande. El tamaño máximo es 20MB.');
                return;
            }
            
            imageFiles.items.add(file);
        });
        
        // Actualizar el input con todos los archivos
        this.files = imageFiles.files;
        
        // Mostrar previews
        updateImagePreviews();
    }
});

function updateImagePreviews() {
    const container = document.getElementById('imagePreviewContainer');
    container.innerHTML = '';
    
    let filesProcessed = 0;
    const totalFiles = imageFiles.files.length;
    
    if (totalFiles === 0) {
        // Si no hay archivos, ocultar el loader
        document.getElementById('loaderContainer').classList.remove('active');
        return;
    }
    
    Array.from(imageFiles.files).forEach((file, index) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4';
        
        const previewContainer = document.createElement('div');
        previewContainer.className = 'preview-container';
        
        const img = document.createElement('img');
        img.className = 'preview-image';
        img.src = URL.createObjectURL(file);
        
        // Cuando la imagen termine de cargar
        img.onload = function() {
            filesProcessed++;
            // Ocultar el loader cuando se han procesado todos los archivos
            if (filesProcessed === totalFiles) {
                document.getElementById('loaderContainer').classList.remove('active');
            }
        };
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-image';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = (e) => {
            e.preventDefault();
            removeImage(index);
        };
        
        previewContainer.appendChild(img);
        previewContainer.appendChild(removeBtn);
        col.appendChild(previewContainer);
        container.appendChild(col);
    });
}

function removeImage(index) {
    const newFiles = new DataTransfer();
    
    Array.from(imageFiles.files)
        .filter((_, i) => i !== index)
        .forEach(file => newFiles.items.add(file));
    
    imageFiles = newFiles;
    document.getElementById('imagenes').files = imageFiles.files;
    updateImagePreviews();
}
// Función para pre-optimizar una imagen en el cliente
async function optimizeImageClient(file) {
    return new Promise((resolve, reject) => {
        // Mostrar mensaje de estado en el loader
        updateLoaderMessage(`Optimizando ${file.name}...`);
        
        // Crear elementos para procesar la imagen
        const img = new Image();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        img.onload = function() {
            // Calcular nuevas dimensiones manteniendo la proporción
            let width = img.width;
            let height = img.height;
            const maxDimension = 1200; // Tamaño máximo recomendado para móviles/web
            
            if (width > height && width > maxDimension) {
                height = Math.round(height * (maxDimension / width));
                width = maxDimension;
            } else if (height > maxDimension) {
                width = Math.round(width * (maxDimension / height));
                height = maxDimension;
            }
            
            // Configurar canvas con nuevas dimensiones
            canvas.width = width;
            canvas.height = height;
            
            // Dibujar imagen redimensionada
            ctx.drawImage(img, 0, 0, width, height);
            
            // Convertir a archivo con calidad reducida (0.7 = 70% de calidad)
            canvas.toBlob(function(blob) {
                if (!blob) {
                    console.error('Error al optimizar la imagen');
                    reject(file); // Devolver imagen original en caso de error
                    return;
                }
                
                // Crear un nuevo archivo con el mismo nombre
                const optimizedFile = new File([blob], file.name, {
                    type: 'image/jpeg',
                    lastModified: Date.now()
                });
                
                // Log para comparación de tamaños
                console.log(`Optimización: ${file.name} - Original: ${(file.size/1024/1024).toFixed(2)}MB, Optimizado: ${(optimizedFile.size/1024/1024).toFixed(2)}MB`);
                resolve(optimizedFile);
            }, 'image/jpeg', 0.7); // Formato JPEG con 70% de calidad
        };
        
        img.onerror = function() {
            console.error('Error al cargar la imagen para optimizar');
            reject(file); // Devolver imagen original en caso de error
        };
        
        // Cargar la imagen desde el archivo
        img.src = URL.createObjectURL(file);
    });
}
// Función para actualizar el mensaje en el loader
function updateLoaderMessage(message) {
    const loaderMessage = document.getElementById('loaderMessage');
    if (loaderMessage) {
        loaderMessage.textContent = message;
    }
}

// Modificar el evento change para optimizar imágenes
document.getElementById('imagenes').addEventListener('change', async function(e) {
    const files = Array.from(e.target.files);
    
    if (files.length > 0) {
        // Mostrar loader con mensaje de procesamiento
        document.getElementById('loaderContainer').classList.add('active');
        updateLoaderMessage('Preparando imágenes...');
        
        // Limpiar el DataTransfer object
        imageFiles = new DataTransfer();
        
        // Procesar cada archivo de forma asíncrona
        const optimizationPromises = files.map(async (file, index) => {
            // Verificar si es una imagen
            if (!file.type.startsWith('image/')) {
                mostrarMensaje('Por favor, seleccione solo archivos de imagen', 'danger');
                return null;
            }
            
            // Verificar tamaño (máximo 20MB por imagen)
            if (file.size > 20 * 1024 * 1024) {
                mostrarMensaje(`El archivo ${file.name} es demasiado grande. El tamaño máximo es 20MB.`, 'danger');
                return null;
            }
            
            try {
                // Optimizar la imagen antes de añadirla
                updateLoaderMessage(`Procesando imagen ${index+1} de ${files.length}...`);
                const optimizedFile = await optimizeImageClient(file);
                return optimizedFile;
            } catch (error) {
                console.error('Error al optimizar:', error);
                return file; // Usar archivo original si falla la optimización
            }
        });
        
        // Esperar a que todas las optimizaciones se completen
        const optimizedFiles = await Promise.all(optimizationPromises);
        
        // Filtrar nulos y agregar archivos optimizados
        optimizedFiles.filter(file => file !== null).forEach(file => {
            imageFiles.items.add(file);
        });
        
        // Actualizar el input con todos los archivos procesados
        this.files = imageFiles.files;
        
        // Actualizar previsualizaciones
        updateLoaderMessage('Generando vistas previas...');
        await updateImagePreviews();
        
        // Ocultar loader
        document.getElementById('loaderContainer').classList.remove('active');
    }
});

// Actualizar la función updateImagePreviews para hacerla más eficiente
async function updateImagePreviews() {
    const container = document.getElementById('imagePreviewContainer');
    container.innerHTML = '';
    
    const files = Array.from(imageFiles.files);
    const totalFiles = files.length;
    
    if (totalFiles === 0) {
        return;
    }
    
    // Usar Promise.all para procesar todas las previsualizaciones en paralelo
    const previewPromises = files.map((file, index) => {
        return new Promise(resolve => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4';
            
            const previewContainer = document.createElement('div');
            previewContainer.className = 'preview-container';
            
            const img = document.createElement('img');
            img.className = 'preview-image';
            img.loading = 'lazy'; // Añadir lazy loading
            
            // Crear URL para la imagen
            const objectUrl = URL.createObjectURL(file);
            img.src = objectUrl;
            
            // Liberar URL cuando la imagen se cargue
            img.onload = () => {
                URL.revokeObjectURL(objectUrl);
                updateLoaderMessage(`Cargando imagen ${index+1} de ${totalFiles}...`);
                resolve();
            };
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-image';
            removeBtn.innerHTML = '×';
            removeBtn.onclick = (e) => {
                e.preventDefault();
                removeImage(index);
            };
            
            // Añadir información del tamaño de la imagen
            const infoLabel = document.createElement('div');
            infoLabel.className = 'image-info';
            infoLabel.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
            
            previewContainer.appendChild(img);
            previewContainer.appendChild(removeBtn);
            previewContainer.appendChild(infoLabel);
            col.appendChild(previewContainer);
            container.appendChild(col);
        });
    });
    
    // Esperar a que todas las previsualizaciones se completen
    await Promise.all(previewPromises);
}

// Función para formatear números con punto como separador de miles
function formatoARS(numero) {
    // Eliminar cualquier carácter que no sea un dígito (excepto puntos y comas)
    let numero_limpio = numero.replace(/[^\d,.]/g, '');
    
    // Eliminar puntos y reemplazar comas por puntos para trabajar sólo con números
    numero_limpio = numero_limpio.replace(/\./g, '').replace(/,/g, '.');
    
    // Convertir a número para hacer operaciones
    let num = parseFloat(numero_limpio);
    if (isNaN(num)) return '';
    
    // Formatear con puntos como separadores de miles
    return Math.round(num).toLocaleString('es-AR', {
        maximumFractionDigits: 0,
        useGrouping: true
    });
}

// Aplicar formato a todos los campos de precio
document.querySelectorAll('.precio-input').forEach(input => {
    // Formatear valor inicial si existe
    if (input.value) {
        input.value = formatoARS(input.value);
    }
    
    // Evento para formatear mientras se escribe
    input.addEventListener('input', function(e) {
        // Guardar posición del cursor
        const start = this.selectionStart;
        const longAntes = this.value.length;
        
        // Formatear
        this.value = formatoARS(this.value);
        
        // Ajustar la posición del cursor después del formateo
        const longDespues = this.value.length;
        const diferencia = longDespues - longAntes;
        this.setSelectionRange(start + diferencia, start + diferencia);
    });
    
    // Aplicar formato también al hacer foco para asegurar formato correcto
    input.addEventListener('focus', function() {
        if (this.value) {
            this.value = formatoARS(this.value);
        }
    });
    
    // Crear un campo oculto para enviar el valor sin formato al servidor
    input.addEventListener('blur', function() {
        // Guardar el valor real sin formato en un atributo
        let valorReal = this.value.replace(/\./g, '');
        this.setAttribute('data-valor-real', valorReal);
    });
});

// Modificar el envío del formulario para usar los valores sin formato
document.querySelector('form').addEventListener('submit', function(e) {
    // Reemplazar los valores formateados por los valores reales antes de enviar
    document.querySelectorAll('.precio-input').forEach(input => {
        input.value = input.getAttribute('data-valor-real') || input.value.replace(/\./g, '');
    });
    
    // Mostrar el loader al enviar el formulario
    document.getElementById('loaderContainer').classList.add('active');
});

// Agregar script para actualizar el estado del formulario
function updateFormConnectionStatus() {
    const onlineIndicator = document.getElementById('onlineIndicatorForm');
    const offlineIndicator = document.getElementById('offlineIndicatorForm');
    
    if (!onlineIndicator || !offlineIndicator) return;
    
    if (typeof offlineManager !== 'undefined' && !offlineManager.isOnline) {
        onlineIndicator.classList.add('d-none');
        offlineIndicator.classList.remove('d-none');
    } else if (typeof offlineProductManager !== 'undefined' && !offlineProductManager.isOnline) {
        onlineIndicator.classList.add('d-none');
        offlineIndicator.classList.remove('d-none');
    } else if (!navigator.onLine) {
        onlineIndicator.classList.add('d-none');
        offlineIndicator.classList.remove('d-none');
    } else {
        onlineIndicator.classList.remove('d-none');
        offlineIndicator.classList.add('d-none');
    }
}

// Actualizar al cargar la página
document.addEventListener('DOMContentLoaded', updateFormConnectionStatus);

// Actualizar cuando cambie la conexión
window.addEventListener('online', updateFormConnectionStatus);
window.addEventListener('offline', updateFormConnectionStatus);

// Función para mostrar/ocultar el input de nueva categoría
function toggleNuevaCategoria() {
    const categoriaSelect = document.getElementById('categoria');
    const nuevaCategoriaContainer = document.getElementById('nuevaCategoriaContainer');
    const nuevaCategoriaInput = document.getElementById('nuevaCategoria');
    
    if (categoriaSelect.value === '__NUEVA__') {
        nuevaCategoriaContainer.style.display = 'block';
        nuevaCategoriaInput.required = true;
        nuevaCategoriaInput.focus();
    } else {
        nuevaCategoriaContainer.style.display = 'none';
        nuevaCategoriaInput.required = false;
        nuevaCategoriaInput.value = '';
    }
}

// Inicializar y validar formulario
document.addEventListener('DOMContentLoaded', function() {
    updateFormConnectionStatus();
    
    const form = document.getElementById('productoForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const categoriaSelect = document.getElementById('categoria');
            const nuevaCategoriaInput = document.getElementById('nuevaCategoria');
            
            // Validar que se haya seleccionado una categoría o ingresado una nueva
            if (!categoriaSelect.value || (categoriaSelect.value === '__NUEVA__' && !nuevaCategoriaInput.value.trim())) {
                e.preventDefault();
                alert('Por favor seleccione una categoría o ingrese una nueva');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<!-- Al final del archivo, antes de cerrar body -->
<script src="js/offline-product-manager.js"></script>

<!-- Agregar botón de debug para productos offline (solo para admins) -->
<?php if ($_SESSION['role'] == 'admin'): ?>
<div id="debug-product-panel" style="position: fixed; bottom: 10px; left: 10px; z-index: 9999; opacity: 0.8;">
    <button id="debug-product-button" class="btn btn-sm btn-secondary" onclick="mostrarDebugProductosOffline()">
        <i class="fas fa-box"></i> Debug Productos
    </button>
</div>

<script>
function mostrarDebugProductosOffline() {
    // Obtener productos pendientes
    const pendingProducts = localStorage.getItem('offlineProducts');
    let mensaje = 'No hay productos offline pendientes';
    
    if (pendingProducts) {
        try {
            const productos = JSON.parse(pendingProducts);
            mensaje = `Hay ${productos.length} productos pendientes:\n\n` + 
                     JSON.stringify(productos.map(p => ({
                         codigo: p.codigo,
                         nombre: p.nombre,
                         categoria: p.categoria,
                         precio: p.precio,
                         imagenes: p.images ? p.images.length : 0
                     })), null, 2);
        } catch (e) {
            mensaje = 'Error al parsear productos: ' + e;
        }
    }
    
    // Mostrar detalles
    Swal.fire({
        title: 'Debug Productos Offline',
        html: `<pre style="text-align: left; max-height: 400px; overflow: auto;">${mensaje}</pre>`,
        width: '80%',
        confirmButtonText: 'Cerrar'
    });
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>