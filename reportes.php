<?php
session_start();
if (!isset($_SESSION['nombres'], $_SESSION['apellidos'])) {
    header('Location: inicio-Sesion.php');
    exit;
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sistema-erp-eless';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar solicitudes AJAX
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'actualizar_reportes':
            try {
                $fecha_desde = $_POST['fecha_desde'] ?? date('Y-m-d');
                $fecha_hasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
                
                // Intentar llamar al procedimiento almacenado si existe
                try {
                    $stmt = $pdo->prepare("CALL ActualizarReportesConsolidados(?, ?)");
                    $stmt->execute([$fecha_desde, $fecha_hasta]);
                } catch(PDOException $e) {
                    // Si el procedimiento no existe, continuar sin error
                }
                
                echo json_encode(['success' => true, 'message' => 'Reportes actualizados correctamente']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'obtener_datos_grafico':
            try {
                $fecha_desde = $_POST['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
                $fecha_hasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
                
                $stmt = $pdo->prepare("
                    SELECT DATE(fecha_venta) as fecha, SUM(total) as total_ventas 
                    FROM ventas 
                    WHERE DATE(fecha_venta) BETWEEN ? AND ? 
                    GROUP BY DATE(fecha_venta)
                    ORDER BY fecha ASC
                ");
                $stmt->execute([$fecha_desde, $fecha_hasta]);
                $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'datos' => $datos]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Obtener fechas del filtro
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Obtener datos consolidados
try {
    // Intentar obtener de tabla de reportes consolidados si existe
    $stmt = $pdo->prepare("
        SELECT * FROM reportes_consolidados 
        WHERE fecha_reporte BETWEEN ? AND ? 
        ORDER BY fecha_reporte DESC 
        LIMIT 1
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $reporte_consolidado = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $reporte_consolidado = false;
}

// Si no hay datos consolidados, calcular directamente
if (!$reporte_consolidado) {
    // Obtener productos registrados desde la tabla inventario
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventario");
        $productos_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch(PDOException $e) {
        $productos_count = 0;
    }
    
    // Obtener compras del período
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total FROM compras WHERE DATE(fecha_compra) BETWEEN ? AND ?");
        $stmt->execute([$fecha_desde, $fecha_hasta]);
        $total_compras = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch(PDOException $e) {
        $total_compras = 0;
    }
    
    // Obtener gastos fijos
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM gastos_fijos WHERE fecha_registro BETWEEN ? AND ?");
        $stmt->execute([$fecha_desde, $fecha_hasta]);
        $gastos_fijos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch(PDOException $e) {
        $gastos_fijos = 0;
    }
    
    // Obtener ventas del período
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(total), 0) as total_ventas,
                COALESCE(SUM(igv), 0) as total_igv
            FROM ventas 
            WHERE DATE(fecha_venta) BETWEEN ? AND ?
        ");
        $stmt->execute([$fecha_desde, $fecha_hasta]);
        $ventas_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $ventas_data = ['total_ventas' => 0, 'total_igv' => 0];
    }
    
  try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(v.total), 0) AS total_con_igv,
            COALESCE(SUM(v.total) * 0.18 / 1.18, 0) AS igv_total,
            COALESCE(SUM(v.total) - SUM(v.total) * 0.18 / 1.18, 0) AS ganancia_bruta
        FROM ventas v
        WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $ganancia_bruta = $result['ganancia_bruta'];
} catch(PDOException $e) {
    $ganancia_bruta = 0;
}
    // Ventas del día actual
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total FROM ventas WHERE DATE(fecha_venta) = ?");
        $stmt->execute([date('Y-m-d')]);
        $ventas_dia = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch(PDOException $e) {
        $ventas_dia = 0;
    }
    
    $reporte_consolidado = [
        'productos_registrados' => $productos_count,
        'total_compras' => $total_compras,
        'gastos_fijos' => $gastos_fijos,
        'total_ventas_con_igv' => $ventas_data['total_ventas'],
        'total_ganancia_bruta' => $ganancia_bruta,
        'igv_total_ganancia' => $ventas_data['total_igv'],
        'ventas_del_dia' => $ventas_dia
    ];
}

// Obtener productos más vendidos
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(i.sku, i.codigo_barras, CONCAT('PROD-', i.id)) as codigo,
            COALESCE(i.nombre_producto, 'Producto sin nombre') as producto,
            SUM(vd.cantidad) as cantidad,
            SUM(vd.subtotal) as ventas
        FROM venta_detalles vd
        INNER JOIN ventas v ON vd.venta_id = v.id
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
        GROUP BY vd.producto_id, i.id, i.sku, i.codigo_barras, i.nombre_producto
        HAVING SUM(vd.cantidad) > 0
        ORDER BY SUM(vd.cantidad) DESC
        LIMIT 10
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $productos_mas_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $productos_mas_vendidos = [];
}

// CORREGIDO: Obtener TODOS los vendedores (responsables de cajas + vendedores que han hecho ventas)
try {
    // 1. Obtener todos los responsables de cajas registradoras
    $todos_vendedores = [];
    
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT 
                TRIM(responsable) as vendedor_nombre,
                responsable_id
            FROM cajas_registradoras 
            WHERE responsable IS NOT NULL 
            AND TRIM(responsable) != ''
            ORDER BY responsable
        ");
        $responsables_cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($responsables_cajas as $responsable) {
            $todos_vendedores[$responsable['vendedor_nombre']] = [
                'nombre' => $responsable['vendedor_nombre'],
                'tipo' => 'responsable_caja',
                'id' => $responsable['responsable_id']
            ];
        }
    } catch(PDOException $e) {
        // Si no existe la tabla cajas_registradoras, continuar
    }
    
    // 2. Obtener todos los vendedores que han hecho ventas
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT 
                TRIM(COALESCE(usuario_nombre, 'Sin Asignar')) as vendedor_nombre,
                usuario_id
            FROM ventas 
            WHERE usuario_nombre IS NOT NULL 
            AND TRIM(usuario_nombre) != ''
            ORDER BY usuario_nombre
        ");
        $vendedores_ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($vendedores_ventas as $vendedor) {
            if (!isset($todos_vendedores[$vendedor['vendedor_nombre']])) {
                $todos_vendedores[$vendedor['vendedor_nombre']] = [
                    'nombre' => $vendedor['vendedor_nombre'],
                    'tipo' => 'vendedor_ventas',
                    'id' => $vendedor['usuario_id']
                ];
            }
        }
    } catch(PDOException $e) {
        // Error al obtener vendedores de ventas
    }
    
    // 3. Obtener todos los usuarios de iniciosesion (tabla de usuarios del sistema)
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT 
                CONCAT(TRIM(nombres), ' ', TRIM(apellidos)) as vendedor_nombre,
                id
            FROM iniciosesion 
            WHERE nombres IS NOT NULL 
            AND TRIM(nombres) != ''
            ORDER BY nombres, apellidos
        ");
        $usuarios_sistema = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($usuarios_sistema as $usuario) {
            $nombre_completo = trim($usuario['vendedor_nombre']);
            if (!isset($todos_vendedores[$nombre_completo]) && $nombre_completo != '') {
                $todos_vendedores[$nombre_completo] = [
                    'nombre' => $nombre_completo,
                    'tipo' => 'usuario_sistema',
                    'id' => $usuario['id']
                ];
            }
        }
    } catch(PDOException $e) {
        // Error al obtener usuarios del sistema
    }
    
    // 4. Agregar "Sin Asignar" si hay ventas sin vendedor
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM ventas 
            WHERE usuario_nombre IS NULL 
            OR TRIM(usuario_nombre) = '' 
            OR usuario_nombre = 'Sin Asignar'
        ");
        $ventas_sin_vendedor = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($ventas_sin_vendedor > 0) {
            $todos_vendedores['Sin Asignar'] = [
                'nombre' => 'Sin Asignar',
                'tipo' => 'sin_asignar',
                'id' => null
            ];
        }
    } catch(PDOException $e) {
        // Error al verificar ventas sin vendedor
    }
    
    // 5. Calcular ventas para cada vendedor en el período específico
    $ventas_por_vendedor = [];
    
    foreach ($todos_vendedores as $vendedor_data) {
        $vendedor_nombre = $vendedor_data['nombre'];
        
        if ($vendedor_nombre === 'Sin Asignar') {
            // Para "Sin Asignar", buscar ventas sin vendedor o con vendedor vacío
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(v.id) as ventas,
                    COALESCE(SUM(v.total), 0) as total
                FROM ventas v
                WHERE (
                    v.usuario_nombre IS NULL 
                    OR TRIM(v.usuario_nombre) = '' 
                    OR v.usuario_nombre = 'Sin Asignar'
                )
                AND DATE(v.fecha_venta) BETWEEN ? AND ?
            ");
            $stmt->execute([$fecha_desde, $fecha_hasta]);
        } else {
            // Para vendedores específicos, buscar por nombre exacto
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(v.id) as ventas,
                    COALESCE(SUM(v.total), 0) as total
                FROM ventas v
                WHERE TRIM(v.usuario_nombre) = ?
                AND DATE(v.fecha_venta) BETWEEN ? AND ?
            ");
            $stmt->execute([$vendedor_nombre, $fecha_desde, $fecha_hasta]);
        }
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            $ventas_por_vendedor[] = [
                'vendedora' => $vendedor_nombre,
                'ventas' => (int)$resultado['ventas'],
                'total' => (float)$resultado['total'],
                'tipo' => $vendedor_data['tipo']
            ];
        }
    }
    
    // 6. Calcular porcentajes
    $total_general = array_sum(array_column($ventas_por_vendedor, 'total'));
    
    foreach ($ventas_por_vendedor as &$vendedor) {
        $vendedor['porcentaje'] = $total_general > 0 ? 
            round(($vendedor['total'] * 100.0) / $total_general, 2) : 0;
    }
    
    // 7. Ordenar por total de ventas (descendente) y luego alfabéticamente
    usort($ventas_por_vendedor, function($a, $b) {
        if ($b['total'] == $a['total']) {
            return strcmp($a['vendedora'], $b['vendedora']);
        }
        return $b['total'] <=> $a['total'];
    });
    
} catch(PDOException $e) {
    $ventas_por_vendedor = [];
}

// Obtener artículo más vendido
$articulo_mas_vendido = '';
if (!empty($productos_mas_vendidos)) {
    $articulo_mas_vendido = $productos_mas_vendidos[0]['producto'];
}

// Obtener datos para el gráfico
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(fecha_venta) as fecha,
            SUM(total) as total_ventas
        FROM ventas 
        WHERE DATE(fecha_venta) BETWEEN ? AND ?
        GROUP BY DATE(fecha_venta)
        ORDER BY DATE(fecha_venta) ASC
    ");
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $datos_grafico = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $datos_grafico = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema ERP ELESS</title>
    <link rel="stylesheet" href="css/siderbarycabezal.css">
    <link rel="stylesheet" href="css/reportes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <?php include __DIR__ . '/header.php'; ?>
        
        <section class="content">
            <div class="reportes-container">
                <!-- Header con filtros -->
                <div class="reportes-header">
                    <h1 class="reportes-title">Reportes</h1>
                    <div class="filtros-fecha">
                        <label for="fecha_desde">Fecha Desde:</label>
                        <input type="date" id="fecha_desde" value="<?php echo $fecha_desde; ?>">
                        
                        <label for="fecha_hasta">Fecha Hasta:</label>
                        <input type="date" id="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                        
                        <button class="btn-actualizar" onclick="actualizarReportes()">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                </div>

                <!-- Métricas principales -->
                <div class="metricas-grid">
                    <div class="metrica-card productos-registrados">
                        <div class="metrica-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="metrica-numero"><?php echo number_format($reporte_consolidado['productos_registrados']); ?></div>
                        <div class="metrica-label">Productos Registrados</div>
                    </div>
                    
                    <div class="metrica-card total-compras">
                        <div class="metrica-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="metrica-numero">S/ <?php echo number_format($reporte_consolidado['total_compras'], 2); ?></div>
                        <div class="metrica-label">Total Compras</div>
                    </div>
                    
                    <div class="metrica-card gastos-fijos">
                        <div class="metrica-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="metrica-numero">S/ <?php echo number_format($reporte_consolidado['gastos_fijos'], 2); ?></div>
                        <div class="metrica-label">Gastos Fijos</div>
                    </div>
                    
                    <div class="metrica-card total-ventas">
                        <div class="metrica-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="metrica-numero">S/ <?php echo number_format($reporte_consolidado['total_ventas_con_igv'], 2); ?></div>
                        <div class="metrica-label">Total Ventas con IGV</div>
                    </div>
                    
                    <div class="metrica-card ganancia-bruta">
                        <div class="metrica-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="metrica-numero">S/ <?php echo number_format($reporte_consolidado['total_ganancia_bruta'], 2); ?></div>
                        <div class="metrica-label">Total Ganancia Bruta</div>
                    </div>
                    
                    <div class="metrica-card igv-total">
                        <div class="metrica-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="metrica-numero">S/ <?php echo number_format($reporte_consolidado['igv_total_ganancia'], 2); ?></div>
                        <div class="metrica-label">IGV de Total Ganancia Bruta</div>
                    </div>
                    
                    <div class="metrica-card ventas-dia">
                        <div class="metrica-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="metrica-numero">S/ <?php echo number_format($reporte_consolidado['ventas_del_dia'], 2); ?></div>
                        <div class="metrica-label">Ventas del Día <?php echo date('d/m/Y'); ?></div>
                    </div>
                </div>

                <!-- Gráfico de ventas -->
                <div class="grafico-container">
                    <h2 class="grafico-title">ELESS - Ventas x Día</h2>
                    <canvas id="ventasChart" class="chart-canvas"></canvas>
                </div>

                <!-- Tablas de datos -->
                <div class="tablas-container">
                    <!-- Productos más vendidos -->
                    <div class="tabla-card">
                        <h3 class="tabla-title">Los 10 Productos Más Vendidos</h3>
                        <table class="tabla-productos">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Ventas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($productos_mas_vendidos)): ?>
                                    <?php foreach ($productos_mas_vendidos as $producto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['codigo'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($producto['producto']); ?></td>
                                        <td><?php echo number_format($producto['cantidad'], 0); ?></td>
                                        <td>S/ <?php echo number_format($producto['ventas'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #666;">No hay datos disponibles para el período seleccionado</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ventas por vendedor -->
                    <div class="tabla-card">
                        <h3 class="tabla-title">Ventas por Vendedor</h3>
                        <table class="tabla-vendedores">
                            <thead>
                                <tr>
                                    <th>Vendedor</th>
                                    <th># Ventas</th>
                                    <th>Total (S/)</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($ventas_por_vendedor)): ?>
                                    <?php foreach ($ventas_por_vendedor as $vendedor): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($vendedor['vendedora']); ?>
                                            <?php if (isset($vendedor['tipo'])): ?>
                                                <?php if ($vendedor['tipo'] == 'responsable_caja'): ?>
                                                    <small style="color: #10b981; font-size: 10px;">📦 Caja</small>
                                                <?php elseif ($vendedor['tipo'] == 'usuario_sistema'): ?>
                                                    <small style="color: #6366f1; font-size: 10px;">👤 Sistema</small>
                                                <?php elseif ($vendedor['tipo'] == 'vendedor_ventas'): ?>
                                                    <small style="color: #f59e0b; font-size: 10px;">💰 Ventas</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($vendedor['ventas']); ?></td>
                                        <td>S/ <?php echo number_format($vendedor['total'], 2); ?></td>
                                        <td><?php echo number_format($vendedor['porcentaje'], 1); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #666;">No hay vendedores registrados</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Artículo más vendido -->
                    <div class="tabla-card">
                        <div class="articulo-mas-vendido">
                            <h3>ELESS<br>Artículo Más Vendido</h3>
                            <div class="articulo-nombre">
                                <?php echo $articulo_mas_vendido ? htmlspecialchars($articulo_mas_vendido) : 'No hay datos disponibles'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Variables globales
        let ventasChart = null;

        // Función para actualizar reportes
        function actualizarReportes() {
            const fechaDesde = document.getElementById('fecha_desde').value;
            const fechaHasta = document.getElementById('fecha_hasta').value;
            
            if (!fechaDesde || !fechaHasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }
            
            if (fechaDesde > fechaHasta) {
                alert('La fecha desde no puede ser mayor a la fecha hasta');
                return;
            }
            
            // Mostrar loading
            document.querySelector('.reportes-container').classList.add('loading');
            
            // Actualizar URL y recargar página
            const url = new URL(window.location);
            url.searchParams.set('fecha_desde', fechaDesde);
            url.searchParams.set('fecha_hasta', fechaHasta);
            window.location.href = url.toString();
        }

        // Función para crear el gráfico
        function crearGraficoVentas() {
            const ctx = document.getElementById('ventasChart').getContext('2d');
            
            const datosGrafico = <?php echo json_encode($datos_grafico); ?>;
            
            const labels = datosGrafico.map(item => {
                const fecha = new Date(item.fecha);
                return fecha.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });
            });
            
            const datos = datosGrafico.map(item => parseFloat(item.total_ventas));
            
            if (ventasChart) {
                ventasChart.destroy();
            }
            
            ventasChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Ventas',
                        data: datos,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'S/ ' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    elements: {
                        point: {
                            hoverBackgroundColor: '#764ba2'
                        }
                    }
                }
            });
        }

        // Inicializar cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            crearGraficoVentas();
            
            // Remover loading state
            setTimeout(() => {
                document.querySelector('.reportes-container').classList.remove('loading');
            }, 500);
        });

        // Actualizar gráfico cuando cambia el tamaño de ventana
        window.addEventListener('resize', function() {
            if (ventasChart) {
                ventasChart.resize();
            }
        });
    </script>
</body>
</html>
