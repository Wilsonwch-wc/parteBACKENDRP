<?php
require_once 'db.php';
checkPermission();
include 'includes/header.php';

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

?>

<div class="container mt-4">
    <div class="row">
        <!-- Carrito de compras - Ahora primero en móvil -->
        <div class="col-md-5 order-1 order-md-2 mb-4">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Lista de Compras</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 15%">Código</th>
                                    <th style="width: 35%">Producto</th>
                                    <th style="width: 15%">Cant.</th>
                                    <th style="width: 15%">P.U.</th>
                                    <th style="width: 15%">Total</th>
                                    <th style="width: 5%"></th>
                                </tr>
                            </thead>
                            <tbody id="carritoItems">
                                <!-- Los items se agregarán aquí dinámicamente -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">Total Items:</td>
                                    <td id="totalItems">0</td>
                                    <td>Total:</td>
                                    <td colspan="2" id="totalPrecio">$0.00</td>
                                </tr>
                                <tr>
                                    <td colspan="4">Total con IVA (21%):</td>
                                    <td colspan="2" id="totalConIVA">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="incluirIVA" onchange="actualizarCarritoUI()">
                        <label class="form-check-label" for="incluirIVA">
                            Incluir IVA (21%)
                        </label>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" onclick="procesarVenta()">
                            Procesar Venta
                        </button>
                        <button class="btn btn-danger" onclick="limpiarCarrito()">
                            Limpiar Lista
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buscador -->
        <div class="col-12 order-2 order-md-1 mb-4">
            <input type="text" id="buscador" class="form-control" placeholder="Buscar por nombre, código, etc.">
        </div>
        
        <!-- Lista de productos -->
        <div class="col-md-7 order-3 order-md-1">
            <h2 class="mb-4">Catálogo de Productos</h2>
            <div class="row row-cols-1 row-cols-md-3 g-4" id="productosGrid">
                <?php
                // Obtener productos con sus imágenes
                $sql = "SELECT p.*, GROUP_CONCAT(i.ruta_imagen) as imagenes 
                FROM productos p 
                LEFT JOIN imagenes_producto i ON p.id = i.producto_id 
                WHERE p.estado = 1 
                GROUP BY p.id 
                ORDER BY p.fecha_creacion DESC";
                        
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
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
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Anterior</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" 
                                                data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Siguiente</span>
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
                } else {
                    echo '<div class="col-12"><div class="alert alert-info">No hay productos disponibles.</div></div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
let carrito = [];

function agregarAlCarrito(producto) {
    console.log('Agregando producto:', producto);
    const itemExistente = carrito.find(item => item.id === producto.id);
    
    if (itemExistente) {
        if (itemExistente.cantidad < producto.stock) {
            itemExistente.cantidad++;
            itemExistente.total = itemExistente.cantidad * itemExistente.precio;
        } else {
            alert('Stock insuficiente');
            return;
        }
    } else {
        carrito.push({
            ...producto,
            cantidad: 1,
            total: producto.precio
        });
    }
    
    actualizarCarritoUI();
}

function actualizarCarritoUI() {
    const tbody = document.getElementById('carritoItems');
    tbody.innerHTML = '';
    
    let totalItems = 0;
    let totalPrecio = 0;
    
    carrito.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.codigo}</td>
            <td>${item.nombre}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${item.id}, -1)">-</button>
                    <input type="number" class="form-control form-control-sm" style="width: 50px;" value="${item.cantidad}" min="1" max="${item.stock}" onchange="cambiarCantidadEspecifica(${item.id}, this.value)">
                    <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${item.id}, 1)">+</button>
                </div>
            </td>
            <td>$${item.precio.toFixed(2)}</td>
            <td>$${item.total.toFixed(2)}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="eliminarItem(${item.id})">×</button>
            </td>
        `;
        tbody.appendChild(tr);
        
        totalItems += item.cantidad;
        totalPrecio += item.total;
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalPrecio').textContent = `$${totalPrecio.toFixed(2)}`;
    
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const totalConIVA = incluirIVA ? totalPrecio * 1.21 : totalPrecio;
    document.getElementById('totalConIVA').textContent = `$${totalConIVA.toFixed(2)}`;
}

function cambiarCantidad(id, delta) {
    const item = carrito.find(item => item.id === id);
    if (item) {
        const nuevaCantidad = item.cantidad + delta;
        if (nuevaCantidad > 0 && nuevaCantidad <= item.stock) {
            item.cantidad = nuevaCantidad;
            item.total = item.cantidad * item.precio;
            actualizarCarritoUI();
        }
    }
}

function cambiarCantidadEspecifica(id, cantidad) {
    const item = carrito.find(item => item.id === id);
    if (item) {
        const nuevaCantidad = parseInt(cantidad);
        if (nuevaCantidad > 0 && nuevaCantidad <= item.stock) {
            item.cantidad = nuevaCantidad;
            item.total = item.cantidad * item.precio;
            actualizarCarritoUI();
        } else {
            alert(`Stock insuficiente. Solo hay ${item.stock} unidades disponibles.`);
            document.querySelector(`input[type="number"][value="${item.cantidad}"]`).value = item.cantidad;
        }
    }
}

function eliminarItem(id) {
    carrito = carrito.filter(item => item.id !== id);
    actualizarCarritoUI();
}

function limpiarCarrito() {
    carrito = [];
    actualizarCarritoUI();
}

function procesarVenta() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const carritoConIVA = carrito.map(item => ({
        ...item,
        total: incluirIVA ? item.total * 1.21 : item.total,
        iva: incluirIVA ? 1 : 0
    }));
    
    console.log("Carrito con IVA:", carritoConIVA); // Log para verificar el valor del IVA

    fetch('procesar_venta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(carritoConIVA)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Venta procesada correctamente');
            limpiarCarrito();
        } else {
            alert('Error al procesar la venta: ' + data.error);
        }
    });
}

function imprimirTicket() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    // Mostrar modal para seleccionar método de pago
    const metodoPago = prompt('Seleccione método de pago:\n1 - Efectivo\n2 - Débito\n3 - Transferencia\n4 - Deposito');
    
    if (!metodoPago || !['1','2','3','4'].includes(metodoPago)) {
        alert('Debe seleccionar un método de pago válido');
        return;
    }
    
    // Convertir método de pago a texto
    const metodoPagoTexto = {
        '1': 'EFECTIVO',
        '2': 'DÉBITO',
        '3': 'TRANSFERENCIA',
        '4': 'DEPOSITO'
    }[metodoPago];
    
    // Agregar método de pago al carrito
    const incluirIVA = document.getElementById('incluirIVA').checked;
    const carritoConIVA = carrito.map(item => ({
        ...item,
        total: incluirIVA ? item.total * 1.21 : item.total,
        iva: incluirIVA ? '21%' : '-'
    }));
    
    const datosVenta = {
        items: carritoConIVA,
        metodoPago: metodoPagoTexto
    };
    
    fetch('generar_ticket.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datosVenta)
    }).then(response => response.text())
    .then(html => {
        if (html.includes("Error:")) {
            alert(html);
        } else {
            const ticketWindow = window.open('', 'Ticket de Venta', 'width=400,height=600');
            ticketWindow.document.write(html);
            ticketWindow.document.close();
        }
    });
}

// Buscador con AJAX
document.getElementById('buscador').addEventListener('input', function() {
    const query = this.value;
    fetch(`buscar_productos_menu_fotos.php?q=${encodeURIComponent(query)}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('productosGrid').innerHTML = html;
        });
});
</script>

<?php include 'includes/footer.php'; ?>