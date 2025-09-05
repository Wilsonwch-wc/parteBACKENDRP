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

    <!-- Productos con Stock Bajo -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Productos con Stock Bajo (Menos de 50 unidades)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Stock Actual</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                        </tr>
                    </thead>
                    <tbody id="stockBajoBody">
                        <!-- Se llenará dinámicamente -->
                    </tbody>
                </table>
                
                <!-- Paginación para Stock Bajo -->
                <div id="paginacionStockBajoContainer" class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted" id="paginacionStockBajoInfo">
                        <!-- Información de paginación se llenará dinámicamente -->
                    </div>
                    
                    <nav aria-label="Navegación de productos con stock bajo">
                        <ul class="pagination mb-0" id="paginacionStockBajo">
                            <!-- Paginación se llenará dinámicamente -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Resto de las estadísticas -->
    <div class="row">
        <!-- Ventas Totales -->
        <div class="col-md-4 mb-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Ingresos Totales</h5>
                    <?php
                    $sql = "SELECT SUM(total) as total FROM ventas_cabecera";
                    $result = $conn->query($sql);
                    $total = $result->fetch_assoc()['total'] ?? 0;
                    ?>
                    <h3 class="card-text">$<?php echo number_format($total, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>

        <!-- Productos Vendidos -->
        <div class="col-md-4 mb-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Productos Vendidos</h5>
                    <?php
                    $sql = "SELECT SUM(cantidad) as total FROM ventas";
                    $result = $conn->query($sql);
                    $total = $result->fetch_assoc()['total'] ?? 0;
                    ?>
                    <h3 class="card-text"><?php echo number_format($total, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>

        <!-- Productos en Stock -->
        <div class="col-md-4 mb-4">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Productos en Stock</h5>
                    <?php
                    $sql = "SELECT SUM(stock) as total FROM productos";
                    $result = $conn->query($sql);
                    $total = $result->fetch_assoc()['total'] ?? 0;
                    ?>
                    <h3 class="card-text"><?php echo number_format($total, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Productos Más Vendidos -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Productos Más Vendidos (Top 10)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad Vendida</th>
                            <th>Total Ventas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.codigo, p.nombre, SUM(v.cantidad) as cantidad_vendida, 
                                SUM(v.total) as total_ventas 
                                FROM ventas v 
                                JOIN productos p ON v.producto_id = p.id 
                                GROUP BY v.producto_id 
                                ORDER BY cantidad_vendida DESC 
                                LIMIT 10";
                        $result = $conn->query($sql);
                        
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$row['codigo']}</td>
                                    <td>{$row['nombre']}</td>
                                    <td>{$row['cantidad_vendida']}</td>
                                    <td>$" . number_format($row['total_ventas'], 0, ',', '.') . "</td>
                                </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Productos Menos Vendidos -->
    <div class="card mt-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Productos Menos Vendidos (5 o menos ventas)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Vendidos</th>
                            <th>Stock</th>
                            <th>Días en Inventario</th>
                            <th>Ventas/Día</th>
                            <th>Capital Estancado</th>
                        </tr>
                    </thead>
                    <tbody id="menosVendidosBody">
                        <!-- Se llenará dinámicamente -->
                    </tbody>
                </table>
                
                <!-- Paginación -->
                <div id="paginacionContainer" class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted" id="paginacionInfo">
                        <!-- Información de paginación se llenará dinámicamente -->
                    </div>
                    
                    <nav aria-label="Navegación de productos menos vendidos">
                        <ul class="pagination mb-0" id="paginacionMenosVendidos">
                            <!-- Paginación se llenará dinámicamente -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let paginaActualStockBajo = 1;
let paginaActualMenosVendidos = 1;
const registrosPorPagina = 10;

function cargarProductosStockBajo(pagina = 1) {
    paginaActualStockBajo = pagina;
    
    // Mostrar indicador de carga
    document.getElementById('stockBajoBody').innerHTML = `
        <tr>
            <td colspan="5" class="text-center py-3">
                <div class="spinner-border text-warning" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </td>
        </tr>
    `;
    
    // Obtener posición actual de scroll si es necesario
    const scrollPosition = window.scrollY;
    
    fetch(`get_stock_bajo.php?pagina=${pagina}&limit=${registrosPorPagina}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('stockBajoBody');
            tbody.innerHTML = '';
            
            if (data.productos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-3">
                            No hay productos con stock bajo
                        </td>
                    </tr>
                `;
                document.getElementById('paginacionStockBajoContainer').style.display = 'none';
                return;
            }
            
            data.productos.forEach(producto => {
                const stockClass = producto.stock == 0 ? 'table-danger' : 'table-warning';
                const tr = document.createElement('tr');
                tr.className = stockClass;
                tr.innerHTML = `
                    <td>${producto.codigo}</td>
                    <td>${producto.nombre}</td>
                    <td>${producto.stock}</td>
                    <td>${producto.categoria}</td>
                    <td>$${producto.precio}</td>
                `;
                tbody.appendChild(tr);
            });
            
            // Actualizar información de paginación
            document.getElementById('paginacionStockBajoInfo').textContent = 
                `Mostrando ${(data.paginacion.pagina_actual - 1) * data.paginacion.por_pagina + 1}-` +
                `${Math.min(data.paginacion.pagina_actual * data.paginacion.por_pagina, data.paginacion.total)} ` +
                `de ${data.paginacion.total} productos`;
            
            // Generar controles de paginación
            generarPaginacionStockBajo(data.paginacion);
            
            // Mostrar contenedor de paginación
            document.getElementById('paginacionStockBajoContainer').style.display = 'flex';
            
            // Mantener posición de scroll si no es la primera carga
            if (pagina > 1) {
                window.scrollTo(0, scrollPosition);
            }
        })
        .catch(error => {
            console.error('Error al cargar productos con stock bajo:', error);
            document.getElementById('stockBajoBody').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-3 text-danger">
                        Error al cargar datos. Intente nuevamente.
                    </td>
                </tr>
            `;
        });
}

function generarPaginacionStockBajo(paginacion) {
    const ulPaginacion = document.getElementById('paginacionStockBajo');
    ulPaginacion.innerHTML = '';
    
    // Botón anterior
    if (paginacion.pagina_actual > 1) {
        const liAnterior = document.createElement('li');
        liAnterior.className = 'page-item';
        liAnterior.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosStockBajo(${paginacion.pagina_actual - 1}); return false;">
                <span aria-hidden="true">&laquo;</span>
            </a>
        `;
        ulPaginacion.appendChild(liAnterior);
    }
    
    // Páginas
    const rangoInicio = Math.max(1, paginacion.pagina_actual - 2);
    const rangoFin = Math.min(paginacion.total_paginas, paginacion.pagina_actual + 2);
    
    // Primera página y ellipsis
    if (rangoInicio > 1) {
        const liPrimera = document.createElement('li');
        liPrimera.className = 'page-item';
        liPrimera.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosStockBajo(1); return false;">1</a>
        `;
        ulPaginacion.appendChild(liPrimera);
        
        if (rangoInicio > 2) {
            const liEllipsis = document.createElement('li');
            liEllipsis.className = 'page-item disabled';
            liEllipsis.innerHTML = '<span class="page-link">...</span>';
            ulPaginacion.appendChild(liEllipsis);
        }
    }
    
    // Páginas del rango
    for (let i = rangoInicio; i <= rangoFin; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === paginacion.pagina_actual ? 'active' : ''}`;
        li.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosStockBajo(${i}); return false;">${i}</a>
        `;
        ulPaginacion.appendChild(li);
    }
    
    // Última página y ellipsis
    if (rangoFin < paginacion.total_paginas) {
        if (rangoFin < paginacion.total_paginas - 1) {
            const liEllipsis = document.createElement('li');
            liEllipsis.className = 'page-item disabled';
            liEllipsis.innerHTML = '<span class="page-link">...</span>';
            ulPaginacion.appendChild(liEllipsis);
        }
        
        const liUltima = document.createElement('li');
        liUltima.className = 'page-item';
        liUltima.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosStockBajo(${paginacion.total_paginas}); return false;">${paginacion.total_paginas}</a>
        `;
        ulPaginacion.appendChild(liUltima);
    }
    
    // Botón siguiente
    if (paginacion.pagina_actual < paginacion.total_paginas) {
        const liSiguiente = document.createElement('li');
        liSiguiente.className = 'page-item';
        liSiguiente.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosStockBajo(${paginacion.pagina_actual + 1}); return false;">
                <span aria-hidden="true">&raquo;</span>
            </a>
        `;
        ulPaginacion.appendChild(liSiguiente);
    }
}

function cargarProductosMenosVendidos(pagina = 1) {
    paginaActualMenosVendidos = pagina;
    
    // Mostrar indicador de carga
    document.getElementById('menosVendidosBody').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </td>
        </tr>
    `;
    
    // Obtener posición actual de scroll si es necesario
    const scrollPosition = window.scrollY;
    
    fetch(`get_menos_vendidos.php?pagina=${pagina}&limit=${registrosPorPagina}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('menosVendidosBody');
            tbody.innerHTML = '';
            
            if (data.productos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-3">
                            No se encontraron productos con 5 o menos ventas
                        </td>
                    </tr>
                `;
                document.getElementById('paginacionContainer').style.display = 'none';
                return;
            }
            
            data.productos.forEach(producto => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${producto.codigo}</td>
                    <td>${producto.nombre}</td>
                    <td class="text-danger">${producto.vendido}</td>
                    <td>${producto.stock_actual}</td>
                    <td>${producto.dias_inventario}</td>
                    <td>${producto.ventas_por_dia}</td>
                    <td class="text-danger">$${producto.dinero_estancado}</td>
                </tr>`;
                tbody.appendChild(tr);
            });
            
            // Actualizar información de paginación
            document.getElementById('paginacionInfo').textContent = 
                `Mostrando ${(data.paginacion.pagina_actual - 1) * data.paginacion.por_pagina + 1}-` +
                `${Math.min(data.paginacion.pagina_actual * data.paginacion.por_pagina, data.paginacion.total)} ` +
                `de ${data.paginacion.total} productos`;
            
            // Generar controles de paginación
            generarPaginacion(data.paginacion);
            
            // Mostrar contenedor de paginación
            document.getElementById('paginacionContainer').style.display = 'flex';
            
            // Mantener posición de scroll si no es la primera carga
            if (pagina > 1) {
                window.scrollTo(0, scrollPosition);
            }
        })
        .catch(error => {
            console.error('Error al cargar productos menos vendidos:', error);
            document.getElementById('menosVendidosBody').innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-3 text-danger">
                        Error al cargar datos. Intente nuevamente.
                    </td>
                </tr>
            `;
        });
}

function generarPaginacion(paginacion) {
    const ulPaginacion = document.getElementById('paginacionMenosVendidos');
    ulPaginacion.innerHTML = '';
    
    // Botón anterior
    if (paginacion.pagina_actual > 1) {
        const liAnterior = document.createElement('li');
        liAnterior.className = 'page-item';
        liAnterior.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosMenosVendidos(${paginacion.pagina_actual - 1}); return false;">
                <span aria-hidden="true">&laquo;</span>
            </a>
        `;
        ulPaginacion.appendChild(liAnterior);
    }
    
    // Páginas
    const rangoInicio = Math.max(1, paginacion.pagina_actual - 2);
    const rangoFin = Math.min(paginacion.total_paginas, paginacion.pagina_actual + 2);
    
    // Primera página y ellipsis
    if (rangoInicio > 1) {
        const liPrimera = document.createElement('li');
        liPrimera.className = 'page-item';
        liPrimera.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosMenosVendidos(1); return false;">1</a>
        `;
        ulPaginacion.appendChild(liPrimera);
        
        if (rangoInicio > 2) {
            const liEllipsis = document.createElement('li');
            liEllipsis.className = 'page-item disabled';
            liEllipsis.innerHTML = '<span class="page-link">...</span>';
            ulPaginacion.appendChild(liEllipsis);
        }
    }
    
    // Páginas del rango
    for (let i = rangoInicio; i <= rangoFin; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === paginacion.pagina_actual ? 'active' : ''}`;
        li.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosMenosVendidos(${i}); return false;">${i}</a>
        `;
        ulPaginacion.appendChild(li);
    }
    
    // Última página y ellipsis
    if (rangoFin < paginacion.total_paginas) {
        if (rangoFin < paginacion.total_paginas - 1) {
            const liEllipsis = document.createElement('li');
            liEllipsis.className = 'page-item disabled';
            liEllipsis.innerHTML = '<span class="page-link">...</span>';
            ulPaginacion.appendChild(liEllipsis);
        }
        
        const liUltima = document.createElement('li');
        liUltima.className = 'page-item';
        liUltima.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosMenosVendidos(${paginacion.total_paginas}); return false;">${paginacion.total_paginas}</a>
        `;
        ulPaginacion.appendChild(liUltima);
    }
    
    // Botón siguiente
    if (paginacion.pagina_actual < paginacion.total_paginas) {
        const liSiguiente = document.createElement('li');
        liSiguiente.className = 'page-item';
        liSiguiente.innerHTML = `
            <a class="page-link" href="#" onclick="cargarProductosMenosVendidos(${paginacion.pagina_actual + 1}); return false;">
                <span aria-hidden="true">&raquo;</span>
            </a>
        `;
        ulPaginacion.appendChild(liSiguiente);
    }
}

// Cargar datos cuando la página esté lista
document.addEventListener('DOMContentLoaded', function() {
    cargarProductosStockBajo();
    cargarProductosMenosVendidos();
});
</script>

<style>
/* Estilos para la tabla de menos vendidos */
#menosVendidosBody tr:hover {
    background-color: #fff3f3;
}

[data-theme="dark"] #menosVendidosBody tr:hover {
    background-color: #3a2a2a;
}

/* Estilos para la tabla de stock bajo */
#stockBajoBody tr:hover {
    background-color: #fff9e6;
}

[data-theme="dark"] #stockBajoBody tr:hover {
    background-color: #3a3522;
}

.table td {
    vertical-align: middle;
}

.text-danger {
    font-weight: 500;
}

/* Estilos para la paginación de productos menos vendidos */
#paginacionMenosVendidos .page-link {
    color: #dc3545;
    border-color: #dc3545;
}

#paginacionMenosVendidos .page-item.active .page-link {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

[data-theme="dark"] #paginacionMenosVendidos .page-link {
    background-color: #343a40;
    color: #dc3545;
    border-color: #495057;
}

[data-theme="dark"] #paginacionMenosVendidos .page-item.active .page-link {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

/* Estilos para la paginación de productos con stock bajo */
#paginacionStockBajo .page-link {
    color: #ffc107;
    border-color: #ffc107;
}

#paginacionStockBajo .page-item.active .page-link {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

[data-theme="dark"] #paginacionStockBajo .page-link {
    background-color: #343a40;
    color: #ffc107;
    border-color: #495057;
}

[data-theme="dark"] #paginacionStockBajo .page-item.active .page-link {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}
</style>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>