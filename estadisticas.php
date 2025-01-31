<?php
require_once 'db.php';
checkPermission('admin');
include 'includes/header.php';

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}
?>

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<div class="container mt-4">
    <h2>Estadísticas</h2>

    <?php
    if ($conn->connect_error) {
        ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Error al cargar estadísticas: <?php echo $conn->connect_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    }

    // Función para obtener estadísticas
    function getStats($conn, $dateCondition) {
        $sql = "SELECT COUNT(*) as total_ventas, COALESCE(SUM(total), 0) as ingreso_total 
                FROM ventas 
                WHERE " . $dateCondition;
        $result = $conn->query($sql);
        return $result->fetch_assoc();
    }

    // Estadísticas de hoy
    $hoy = getStats($conn, "DATE(fecha_venta) = CURDATE()");
    
    // Estadísticas de la semana
    $semana = getStats($conn, "YEARWEEK(fecha_venta) = YEARWEEK(CURDATE())");
    
    // Estadísticas del mes
    $mes = getStats($conn, "YEAR(fecha_venta) = YEAR(CURDATE()) AND MONTH(fecha_venta) = MONTH(CURDATE())");
    ?>

    <div class="row mt-4">
        <!-- Ventas de Hoy -->
        <div class="col-md-4 mb-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-header">
                    <h5 class="mb-0">Ventas de Hoy</h5>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $hoy['total_ventas']; ?> ventas</h3>
                    <p class="card-text">
                        Ingreso: $<?php echo number_format($hoy['ingreso_total'], 2); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Ventas de la Semana -->
        <div class="col-md-4 mb-4">
            <div class="card bg-success text-white h-100">
                <div class="card-header">
                    <h5 class="mb-0">Ventas de la Semana</h5>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $semana['total_ventas']; ?> ventas</h3>
                    <p class="card-text">
                        Ingreso: $<?php echo number_format($semana['ingreso_total'], 2); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Ventas del Mes -->
        <div class="col-md-4 mb-4">
            <div class="card bg-info text-white h-100">
                <div class="card-header">
                    <h5 class="mb-0">Ventas del Mes</h5>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $mes['total_ventas']; ?> ventas</h3>
                    <p class="card-text">
                        Ingreso: $<?php echo number_format($mes['ingreso_total'], 2); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos y estadísticas adicionales -->
    <div class="row mt-4">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Ventas e Ingresos</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary " onclick="cambiarVista('dia')">Por Día</button>
                            <button type="button" class="btn btn-outline-primary active" onclick="cambiarVista('mes')">Por Mes</button>
                            <button type="button" class="btn btn-outline-primary" onclick="cambiarVista('año')">Por Año</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Desde</label>
                                <input type="text" class="form-control flatpickr" id="fecha_desde" 
                                       value="<?php echo date('Y-m-d', strtotime('-1 year')); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hasta</label>
                                <input type="text" class="form-control flatpickr" id="fecha_hasta" 
                                       value="<?php echo date('Y-m-d', strtotime('+2 day')); ?>">
                            </div>
                        </div>
                    </div>
                    <div style="position: relative; height: 60vh; min-height: 400px;">
                        <canvas id="ventasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nuevo gráfico de Gastos y Ganancias -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Análisis de Gastos y Ganancias</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="cambiarVistaGanancias('dia')">Por Día</button>
                    <button type="button" class="btn btn-outline-primary active" onclick="cambiarVistaGanancias('mes')">Por Mes</button>
                    <button type="button" class="btn btn-outline-primary" onclick="cambiarVistaGanancias('año')">Por Año</button>
                </div>
            </div>
            <div style="height: 400px;">
                <canvas id="gananciasChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Productos con Stock Bajo -->
    <div class="row mt-4">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Productos con Stock Bajo</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Stock Actual</th>
                                    <th>Precio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Consideramos stock bajo menos de 5 unidades
                                $sql = "SELECT nombre, stock, precio 
                                       FROM productos 
                                       WHERE stock < 51 
                                       ORDER BY stock ASC";
                                $result = $conn->query($sql);
                                
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $row['stock'] == 0 ? 'danger' : 'warning'; ?>">
                                                    <?php echo $row['stock']; ?> unidades
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($row['precio'], 2); ?></td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="3" class="text-center text-success">
                                            No hay productos con stock bajo
                                          </td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let vistaActual = 'mes';
let chartInstance = null;

function cambiarVista(vista) {
    vistaActual = vista;
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    actualizarGrafico();
}

function actualizarGrafico() {
    const desde = document.getElementById('fecha_desde').value;
    const hasta = document.getElementById('fecha_hasta').value;
    
    fetch(`obtener_datos_grafico.php?vista=${vistaActual}&desde=${desde}&hasta=${hasta}`)
        .then(response => response.json())
        .then(data => {
            if (chartInstance) {
                chartInstance.destroy();
            }
            
            const ctx = document.getElementById('ventasChart').getContext('2d');
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Cantidad de Ventas',
                            data: data.ventas,
                            backgroundColor: 'rgba(76, 175, 80, 0.6)',
                            borderColor: '#4CAF50',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Ingresos ($)',
                            data: data.ingresos,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgb(54, 162, 235)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: 'Cantidad de Ventas e Ingresos por Período'
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Período'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: {
                                borderDash: [2, 2]
                            },
                            title: {
                                display: true,
                                text: 'Cantidad de Ventas'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            title: {
                                display: true,
                                text: 'Ingresos ($)'
                            }
                        }
                    }
                }
            });
        });
}

// Inicializar Flatpickr
flatpickr(".flatpickr", {
    locale: "es",
    dateFormat: "Y-m-d",
    altFormat: "d/m/Y",
    altInput: true,
    allowInput: true,
    theme: "material_blue",
    onChange: function(selectedDates, dateStr) {
        actualizarGrafico();
    }
});

// Inicializar gráfico al cargar la página
document.addEventListener('DOMContentLoaded', actualizarGrafico);

let vistaActualGanancias = 'mes';
let chartGanancias = null;

function cambiarVistaGanancias(vista) {
    vistaActualGanancias = vista;
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    actualizarGraficoGanancias();
}

function actualizarGraficoGanancias() {
    const desde = document.getElementById('fecha_desde').value;
    const hasta = document.getElementById('fecha_hasta').value;
    
    fetch(`obtener_datos_ganancias.php?vista=${vistaActualGanancias}&desde=${desde}&hasta=${hasta}`)
        .then(response => response.json())
        .then(data => {
            if (chartGanancias) {
                chartGanancias.destroy();
            }
            
            const ctx = document.getElementById('gananciasChart').getContext('2d');
            chartGanancias = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Gastos',
                            data: data.gastos,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            borderColor: 'rgb(255, 99, 132)',
                            borderWidth: 1
                        },
                        {
                            label: 'Ganancias',
                            data: data.ganancias,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgb(75, 192, 192)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: {
                                borderDash: [2, 2]
                            }
                        }
                    }
                }
            });
        });
}

// Inicializar gráfico de ganancias al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    actualizarGrafico();
    actualizarGraficoGanancias();
});
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>