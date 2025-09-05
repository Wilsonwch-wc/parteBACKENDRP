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
    <h2>Telemetría de Ventas</h2>

    <!-- Filtros de fecha -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Desde</label>
                    <input type="text" class="form-control flatpickr" id="fecha_desde">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hasta</label>
                    <input type="text" class="form-control flatpickr" id="fecha_hasta">
                </div>
                <div class="col-md-4">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" onclick="cambiarVista('mes')">Mes</button>
                        <button type="button" class="btn btn-outline-primary" onclick="cambiarVista('semana')">Semana</button>
                        <button type="button" class="btn btn-outline-primary" onclick="cambiarVista('dia')">Día</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modificar la estructura para crear una fila con los dos gráficos -->
    <div class="row mb-4">
        <!-- Gráfico de Ganancias vs Gastos -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ganancias vs Gastos</h5>
                </div>
                <div class="card-body">
                    <div style="height: 400px;">
                        <canvas id="gananciasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos en la misma fila -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Ventas por Categoría</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" id="fecha_desde_categoria" placeholder="Desde">
                            <input type="text" class="form-control form-control-sm" id="fecha_hasta_categoria" placeholder="Hasta">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="ventasCategoriaChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Top Productos Más Vendidos</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" id="fecha_desde_productos" placeholder="Desde">
                            <input type="text" class="form-control form-control-sm" id="fecha_hasta_productos" placeholder="Hasta">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="topProductosChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let vistaActual = 'mes';
let gananciasChartInstance = null;

function cambiarVista(vista) {
    vistaActual = vista;
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    actualizarGrafico();
}

function actualizarGrafico() {
    const desde = document.getElementById('fecha_desde').value;
    const hasta = document.getElementById('fecha_hasta').value;
        
    fetch(`obtener_datos_telemetria.php?vista=${vistaActual}&desde=${desde}&hasta=${hasta}`)
        .then(response => response.json())
        .then(data => {
          
            
            if (gananciasChartInstance) {
                gananciasChartInstance.destroy();
            }
            
            const ctx = document.getElementById('gananciasChart').getContext('2d');
            gananciasChartInstance = new Chart(ctx, {
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
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Gastos y Ganancias por Período'
                        },
                        legend: {
                            position: 'top',
                            align: 'center'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y || 0;
                                    return `${label}: $${value.toLocaleString('es-AR', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error al cargar los datos:', error);
        });
}

// Inicializar flatpickr y valores por defecto
const fechaDesde = new Date();
fechaDesde.setDate(1); // Establecer al primer día del mes
const fechaHasta = new Date();
// Último día del mes actual
const ultimoDia = new Date(fechaHasta.getFullYear(), fechaHasta.getMonth() + 1, 0).getDate();
fechaHasta.setDate(ultimoDia);

const fpDesde = flatpickr("#fecha_desde", {
    locale: "es",
    dateFormat: "Y-m-d",
    defaultDate: fechaDesde,
    onChange: function() {
        actualizarGrafico();
    }
});

const fpHasta = flatpickr("#fecha_hasta", {
    locale: "es",
    dateFormat: "Y-m-d",
    defaultDate: fechaHasta,
    onChange: function() {
        actualizarGrafico();
    }
});

// Cargar gráfico inicial
document.addEventListener('DOMContentLoaded', function() {
    // Establecer los valores iniciales
    document.getElementById('fecha_desde').value = fpDesde.formatDate(fechaDesde, "Y-m-d");
    document.getElementById('fecha_hasta').value = fpHasta.formatDate(fechaHasta, "Y-m-d");
    actualizarGrafico();
    initVentasCategoriaChart();
    initTopProductosChart();
});

function initVentasCategoriaChart() {
    const ctx = document.getElementById('ventasCategoriaChart').getContext('2d');
    let chartInstance = null;
    
    function actualizarGraficoCategoria() {
        const desde = document.getElementById('fecha_desde_categoria').value;
        const hasta = document.getElementById('fecha_hasta_categoria').value;
        
    
        
        fetch(`get_ventas_categoria.php?desde=${desde}&hasta=${hasta}`)
            .then(response => response.json())
            .then(data => {
            
                
                if (chartInstance) {
                    chartInstance.destroy();
                }
                
                if (data.categorias.length === 0) {
                    // Si no hay datos, mostrar mensaje
                    const ctx = document.getElementById('ventasCategoriaChart');
                    ctx.height = 100;
                    const context = ctx.getContext('2d');
                    context.fillStyle = '#666';
                    context.textAlign = 'center';
                    context.fillText('No hay datos para el período seleccionado', ctx.width/2, ctx.height/2);
                    return;
                }
                
                chartInstance = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.categorias,
                        datasets: [{
                            data: data.ventas,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(255, 206, 86, 0.8)',
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(153, 102, 255, 0.8)',
                                'rgba(255, 159, 64, 0.8)'
                            ],
                            borderColor: 'white',
                            borderWidth: 2,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value * 100) / total).toFixed(1) : 0;
                                        return `${label}: $${value.toLocaleString('es-AR', {minimumFractionDigits: 0, maximumFractionDigits: 0})} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error al cargar los datos:', error);
            });
    }
    
    // Inicializar flatpickr para las fechas
    flatpickr("#fecha_desde_categoria", {
        locale: "es",
        dateFormat: "Y-m-d",
        defaultDate: fechaDesde,
        onChange: function() {
            actualizarGraficoCategoria();
        }
    });

    flatpickr("#fecha_hasta_categoria", {
        locale: "es",
        dateFormat: "Y-m-d",
        defaultDate: fechaHasta,
        onChange: function() {
            actualizarGraficoCategoria();
        }
    });
    
    // Cargar gráfico inicial
    actualizarGraficoCategoria();
}

function initTopProductosChart() {
    const ctx = document.getElementById('topProductosChart').getContext('2d');
    let chartInstance = null;
    
    function actualizarGraficoProductos() {
        const desde = document.getElementById('fecha_desde_productos').value;
        const hasta = document.getElementById('fecha_hasta_productos').value;
        
        fetch(`get_top_productos.php?desde=${desde}&hasta=${hasta}`)
            .then(response => response.json())
            .then(data => {
                if (chartInstance) {
                    chartInstance.destroy();
                }
                
                chartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.nombres,
                        datasets: [{
                            label: 'Unidades Vendidas',
                            data: data.cantidades,
                            backgroundColor: 'rgba(54, 162, 235, 0.8)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            borderRadius: 5,
                            barThickness: 20,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const cantidad = context.parsed.x;
                                        const index = context.dataIndex;
                                        const monto = parseFloat(data.montos[index]);
                                        const porcentaje = data.porcentajes[index];
                                        return [
                                            `Cantidad: ${cantidad} unidades`,
                                            `Monto: $${monto.toLocaleString('es-AR', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`,
                                            `Porcentaje: ${porcentaje}% del total`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            });
    }
    
    // Inicializar flatpickr para las fechas
    flatpickr("#fecha_desde_productos", {
        locale: "es",
        dateFormat: "Y-m-d",
        defaultDate: fechaDesde,
        onChange: function() {
            actualizarGraficoProductos();
        }
    });

    flatpickr("#fecha_hasta_productos", {
        locale: "es",
        dateFormat: "Y-m-d",
        defaultDate: fechaHasta,
        onChange: function() {
            actualizarGraficoProductos();
        }
    });
    
    // Cargar gráfico inicial
    actualizarGraficoProductos();
}
</script>

<?php include 'includes/footer.php'; ?> 