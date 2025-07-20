<?php
session_start();

if (!isset($_SESSION['nombres'], $_SESSION['apellidos'])) {
    header('Location: inicio-Sesion.php');
    exit;
}

// Configuración de la base de datos
$host = 'localhost';
$dbname_mysql = 'sistema-erp-eless';
$dbname_pgsql = 'sistema-erp-eless';

// 🔵 CONEXIÓN A MySQL
try {
    $pdo_mysql = new PDO("mysql:host=$host;dbname=$dbname_mysql;charset=utf8mb4", 'root', '');
    $pdo_mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión MySQL: " . $e->getMessage());
}

// 🟣 CONEXIÓN A PostgreSQL
try {
    $pdo_pgsql = new PDO("pgsql:host=$host;port=5432;dbname=$dbname_pgsql", 'postgres', 'root');
    $pdo_pgsql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión PostgreSQL: " . $e->getMessage());
}

// Función para generar número de solicitud
function generateSolicitudNumber($pdo_pgsql) {
    $year = date('Y');
    $prefix = "SOL-$year-";

    $stmt = $pdo_pgsql->prepare("SELECT MAX(CAST(SUBSTRING(numero_solicitud, LENGTH(?) + 1) AS INTEGER)) as max_num FROM solicitudes_cotizacion WHERE numero_solicitud LIKE ?");
    $stmt->execute([$prefix, $prefix . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextNumber = ($result['max_num'] ?? 0) + 1;
    return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

// Procesar acciones AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_productos':
            try {
                $search = $_POST['search'] ?? '';
                $sql = "SELECT id, nombre_producto, sku, precio_venta, costo, categoria FROM inventario WHERE 1=1";
                $params = [];

                if (!empty($search)) {
                    $sql .= " AND (nombre_producto LIKE ? OR sku LIKE ?)";
                    $searchParam = "%$search%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                }

                $sql .= " ORDER BY nombre_producto LIMIT 50";

                $stmt = $pdo_mysql->prepare($sql);
                $stmt->execute($params);
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'productos' => $productos]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'save_solicitud':
            try {
                $pdo_pgsql->beginTransaction();

                $numero_solicitud = generateSolicitudNumber($pdo_pgsql);

                $stmt = $pdo_pgsql->prepare("INSERT INTO solicitudes_cotizacion (
                    numero_solicitud, proveedor_nombre, proveedor_nif, proveedor_email, 
                    proveedor_referencia, fecha_limite_orden, entrega_esperada, entregar_a,
                    subtotal, total, terminos_condiciones, usuario_creador
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $numero_solicitud,
                    $_POST['proveedor_nombre'] ?? '',
                    $_POST['proveedor_nif'] ?? '',
                    $_POST['proveedor_email'] ?? '',
                    $_POST['proveedor_referencia'] ?? '',
                    $_POST['fecha_limite_orden'] ?? null,
                    $_POST['entrega_esperada'] ?? '',
                    $_POST['entregar_a'] ?? '',
                    $_POST['subtotal'] ?? 0,
                    $_POST['total'] ?? 0,
                    $_POST['terminos_condiciones'] ?? '',
                    $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']
                ]);

                $solicitud_id = $pdo_pgsql->lastInsertId();

                if (isset($_POST['productos']) && is_array($_POST['productos'])) {
                    $stmt_producto = $pdo_pgsql->prepare("INSERT INTO solicitud_productos (
                        solicitud_id, producto_id, producto_nombre, cantidad, unidad, 
                        precio_unitario, impuestos, importe
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($_POST['productos'] as $producto) {
                        $stmt_producto->execute([
                            $solicitud_id,
                            $producto['id'] ?? null,
                            $producto['nombre'],
                            $producto['cantidad'],
                            $producto['unidad'] ?? 'Unidades',
                            $producto['precio_unitario'] ?? 0,
                            $producto['impuestos'] ?? 0,
                            $producto['importe'] ?? 0
                        ]);
                    }
                }

                $pdo_pgsql->commit();
                echo json_encode(['success' => true, 'numero_solicitud' => $numero_solicitud]);
            } catch(PDOException $e) {
                $pdo_pgsql->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'update_estado':
            try {
                $stmt = $pdo_pgsql->prepare("UPDATE solicitudes_cotizacion SET estado = ? WHERE id = ?");
                $stmt->execute([$_POST['estado'], $_POST['solicitud_id']]);
                echo json_encode(['success' => true]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'delete_solicitud':
            try {
                $stmt = $pdo_pgsql->prepare("DELETE FROM solicitudes_cotizacion WHERE id = ?");
                $stmt->execute([$_POST['solicitud_id']]);
                echo json_encode(['success' => true]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'get_solicitud':
            try {
                $stmt = $pdo_pgsql->prepare("SELECT * FROM solicitudes_cotizacion WHERE id = ?");
                $stmt->execute([$_POST['solicitud_id']]);
                $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt_productos = $pdo_pgsql->prepare("SELECT * FROM solicitud_productos WHERE solicitud_id = ?");
                $stmt_productos->execute([$_POST['solicitud_id']]);
                $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'solicitud' => $solicitud, 'productos' => $productos]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Consultas de solicitudes (modo no-AJAX)
$search = $_GET['search'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$vista = $_GET['vista'] ?? 'solicitudes';
$solicitud_id = $_GET['solicitud_id'] ?? null;

try {
    $sql = "SELECT * FROM solicitudes_cotizacion WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (numero_solicitud LIKE ? OR proveedor_nombre LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($estado_filtro)) {
        $sql .= " AND estado = ?";
        $params[] = $estado_filtro;
    }

    $sql .= " ORDER BY fecha_creacion DESC";

    $stmt = $pdo_pgsql->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $solicitudes = [];
    $error = "Error al cargar solicitudes: " . $e->getMessage();
}

// Si estamos editando
$solicitud_actual = null;
$productos_actuales = [];
if ($vista == 'editar' && $solicitud_id) {
    try {
        $stmt = $pdo_pgsql->prepare("SELECT * FROM solicitudes_cotizacion WHERE id = ?");
        $stmt->execute([$solicitud_id]);
        $solicitud_actual = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($solicitud_actual) {
            $stmt_productos = $pdo_pgsql->prepare("SELECT * FROM solicitud_productos WHERE solicitud_id = ?");
            $stmt_productos->execute([$solicitud_id]);
            $productos_actuales = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        $solicitud_actual = null;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Cotización - Sistema ERP ELESS</title>
    <link rel="stylesheet" href="css/siderbarycabezal.css?v=1.0">
      <link rel="stylesheet" href="css/compras2.css?v=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    

</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <?php include __DIR__ . '/header.php'; ?>
            
        <section class="content">
            <?php if ($vista == 'solicitudes'): ?>
            <!-- VISTA DE SOLICITUDES -->
            <div class="solicitudes-container">
                <div class="solicitudes-header">
                    <h1><i class="fas fa-file-invoice"></i> Solicitudes de Cotización</h1>
                    <div class="solicitudes-info">
                        <span>1-<?php echo count($solicitudes); ?> / <?php echo count($solicitudes); ?></span>
                        <div class="solicitudes-controls">
                            <a href="?vista=nueva" class="nuevo-btn"><i class="fas fa-plus"></i> Nueva Solicitud</a>
                            <a href="inventario.php"><i class="fas fa-box"></i> Productos</a>
                        </div>
                    </div>
                </div>
                
                <!-- FILTROS DE BÚSQUEDA -->
                <div style="padding: 2rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <form method="GET" style="background: white; padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <input type="hidden" name="vista" value="solicitudes">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="search">Buscar solicitudes</label>
                                <input 
                                    type="text" 
                                    id="search" 
                                    name="search" 
                                    placeholder="Buscar por número o proveedor..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="estado">Estado</label>
                                <select name="estado" id="estado">
                                    <option value="">Todos los estados</option>
                                    <option value="nueva" <?php echo $estado_filtro === 'nueva' ? 'selected' : ''; ?>>Nueva</option>
                                    <option value="enviada" <?php echo $estado_filtro === 'enviada' ? 'selected' : ''; ?>>Enviada</option>
                                    <option value="confirmada" <?php echo $estado_filtro === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                    <option value="orden_compra" <?php echo $estado_filtro === 'orden_compra' ? 'selected' : ''; ?>>Orden de Compra</option>
                                    <option value="cancelada" <?php echo $estado_filtro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <button type="submit" class="btn-form primary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                            <a href="?vista=solicitudes" class="btn-form secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- GRID DE SOLICITUDES -->
                <?php if (!empty($solicitudes)): ?>
                    <div class="solicitudes-grid">
                        <?php foreach ($solicitudes as $solicitud): ?>
                        <div class="solicitud-card">
                            <div class="solicitud-header-card">
                                <div class="solicitud-menu" onclick="toggleDropdown(<?php echo $solicitud['id']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                    <div class="dropdown-menu" id="dropdown-<?php echo $solicitud['id']; ?>">
                                        <button class="dropdown-item" onclick="editarSolicitud(<?php echo $solicitud['id']; ?>)">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button class="dropdown-item" onclick="verSolicitud(<?php echo $solicitud['id']; ?>)">
                                            <i class="fas fa-eye"></i> Ver Detalles
                                        </button>
                                        <?php if ($solicitud['estado'] === 'nueva'): ?>
                                            <button class="dropdown-item" onclick="enviarSolicitud(<?php echo $solicitud['id']; ?>)">
                                                <i class="fas fa-paper-plane"></i> Enviar
                                            </button>
                                        <?php endif; ?>
                                        <button class="dropdown-item delete" onclick="eliminarSolicitud(<?php echo $solicitud['id']; ?>)">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="solicitud-info">
                                    <h3><?php echo htmlspecialchars($solicitud['numero_solicitud']); ?></h3>
                                    <div class="proveedor"><?php echo htmlspecialchars($solicitud['proveedor_nombre']); ?></div>
                                </div>
                            </div>
                            
                            <div style="padding: 1.5rem;">
                                <div class="solicitud-status">
                                    <span class="status-badge <?php echo $solicitud['estado']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $solicitud['estado'])); ?>
                                    </span>
                                </div>
                                
                                <div style="margin: 1rem 0; font-size: 0.875rem; color: #6b7280;">
                                    <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-dollar-sign"></i>
                                        <strong>Total: S/ <?php echo number_format($solicitud['total'], 2); ?></strong>
                                    </p>
                                    <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-calendar"></i>
                                        Límite: <?php echo $solicitud['fecha_limite_orden'] ? date('d/m/Y', strtotime($solicitud['fecha_limite_orden'])) : 'No definida'; ?>
                                    </p>
                                    <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-clock"></i>
                                        Creado: <?php echo date('d/m/Y', strtotime($solicitud['fecha_creacion'])); ?>
                                    </p>
                                    <?php if ($solicitud['entrega_esperada']): ?>
                                        <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-truck"></i>
                                            Entrega: <?php echo htmlspecialchars($solicitud['entrega_esperada']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="solicitud-actions">
                                <a href="?vista=editar&solicitud_id=<?php echo $solicitud['id']; ?>" class="btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if ($solicitud['estado'] === 'nueva'): ?>
                                    <button class="btn-sm btn-success" onclick="enviarSolicitud(<?php echo $solicitud['id']; ?>)">
                                        <i class="fas fa-paper-plane"></i> Enviar
                                    </button>
                                <?php endif; ?>
                                <button class="btn-sm btn-delete" onclick="eliminarSolicitud(<?php echo $solicitud['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <i class="fas fa-file-invoice" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3>No se encontraron solicitudes</h3>
                        <p>No hay solicitudes de cotización registradas.</p>
                        <a href="?vista=nueva" class="btn-form primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Crear primera solicitud
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php elseif ($vista == 'nueva' || ($vista == 'editar' && $solicitud_actual)): ?>
            <!-- VISTA DE FORMULARIO DE SOLICITUD -->
            <div class="solicitud-form-container">
                <div class="form-header">
                    <div class="form-title">
                        <i class="fas fa-<?php echo $solicitud_actual ? 'edit' : 'plus-circle'; ?>" style="color: #6366f1;"></i>
                        <h1><?php echo $solicitud_actual ? 'Editar Solicitud' : 'Nueva Solicitud de Cotización'; ?></h1>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?vista=solicitudes" class="btn-form secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="form-tabs">
                    <div class="form-tab active" onclick="showTab('general')">
                        <i class="fas fa-info-circle"></i> Información General
                    </div>
                    <div class="form-tab" onclick="showTab('productos')">
                        <i class="fas fa-box"></i> Productos
                    </div>
                    <div class="form-tab" onclick="showTab('terminos')">
                        <i class="fas fa-file-contract"></i> Términos
                    </div>
                </div>
                
                <div class="form-content">
                    <form id="solicitudForm">
                        <?php if ($solicitud_actual): ?>
                            <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_actual['id']; ?>">
                        <?php endif; ?>
                        
                        <!-- SECCIÓN GENERAL -->
                        <div class="form-section active" id="general">
                            <div class="section-title">Información del Proveedor</div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="proveedor_nombre">Proveedor *</label>
                                    <input type="text" id="proveedor_nombre" name="proveedor_nombre" required 
                                           placeholder="Nombre del proveedor"
                                           value="<?php echo htmlspecialchars($solicitud_actual['proveedor_nombre'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="proveedor_nif">NIF/RUC</label>
                                    <input type="text" id="proveedor_nif" name="proveedor_nif" 
                                           value="<?php echo htmlspecialchars($solicitud_actual['proveedor_nif'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="proveedor_email">Email del proveedor</label>
                                    <input type="email" id="proveedor_email" name="proveedor_email" 
                                           value="<?php echo htmlspecialchars($solicitud_actual['proveedor_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="proveedor_referencia">Referencia del proveedor</label>
                                    <input type="text" id="proveedor_referencia" name="proveedor_referencia" 
                                           value="<?php echo htmlspecialchars($solicitud_actual['proveedor_referencia'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="subsection">
                                <h4>Fechas y Entrega</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="fecha_limite_orden">Fecha límite de la orden</label>
                                        <input type="date" id="fecha_limite_orden" name="fecha_limite_orden" 
                                               value="<?php echo $solicitud_actual['fecha_limite_orden'] ?? ''; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="entrega_esperada">Entrega esperada</label>
                                        <input type="text" id="entrega_esperada" name="entrega_esperada" 
                                               placeholder="Fecha o período esperado"
                                               value="<?php echo htmlspecialchars($solicitud_actual['entrega_esperada'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="entregar_a">Entregar a</label>
                                        <input type="text" id="entregar_a" name="entregar_a" 
                                               placeholder="Dirección de entrega"
                                               value="<?php echo htmlspecialchars($solicitud_actual['entregar_a'] ?? ''); ?>">
                                    </div>
                                    <div></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN PRODUCTOS -->
                        <div class="form-section" id="productos">
                            <div class="section-title">Productos Solicitados</div>
                            
                            <table class="productos-table" id="productosTable">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Unidad</th>
                                        <th>Precio unitario</th>
                                        <th>Impuestos</th>
                                        <th>Importe</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="productosBody">
                                    <!-- Los productos se agregarán dinámicamente -->
                                </tbody>
                            </table>
                            
                            <div class="add-product-links">
                                <a href="#" class="add-link" onclick="agregarProducto()">
                                    <i class="fas fa-plus"></i> Agregar un producto
                                </a>
                                <a href="inventario.php" class="add-link">
                                    <i class="fas fa-external-link-alt"></i> Ver catálogo
                                </a>
                            </div>
                            
                            <!-- TOTALES -->
                            <div class="totales-section">
                                <div class="total-row">
                                    <span>Subtotal:</span>
                                    <span id="subtotal">S/ 0.00</span>
                                </div>
                                <div class="total-row total-final">
                                    <span>Total:</span>
                                    <span id="total">S/ 0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECCIÓN TÉRMINOS -->
                        <div class="form-section" id="terminos">
                            <div class="section-title">Términos y Condiciones</div>
                            
                            <div class="form-row single">
                                <div class="form-group">
                                    <label for="terminos_condiciones">Defina sus términos y condiciones</label>
                                    <textarea id="terminos_condiciones" name="terminos_condiciones" 
                                              placeholder="Escriba aquí los términos y condiciones de la solicitud..."
                                              style="min-height: 150px;"><?php echo htmlspecialchars($solicitud_actual['terminos_condiciones'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-form primary" onclick="guardarSolicitud()">
                        <i class="fas fa-save"></i> Guardar Solicitud
                    </button>
                    <a href="?vista=solicitudes" class="btn-form secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php if ($solicitud_actual): ?>
                        <button type="button" class="btn-form" onclick="alert('Función próximamente')" style="background: #6366f1; color: white;">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- MODAL PARA AGREGAR PRODUCTO -->
    <div id="productoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Agregar Producto</h3>
                <button class="modal-close" onclick="cerrarModalProducto()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="buscar_producto">Buscar producto</label>
                    <div class="producto-search">
                        <input type="text" id="buscar_producto" placeholder="Escriba para buscar productos..." 
                               onkeyup="buscarProductos(this.value)">
                        <div id="productosDropdown" class="productos-dropdown"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="producto_cantidad">Cantidad *</label>
                        <input type="number" id="producto_cantidad" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="producto_unidad">Unidad</label>
                        <select id="producto_unidad">
                            <option value="Unidades">Unidades</option>
                            <option value="Kg">Kilogramos</option>
                            <option value="Lt">Litros</option>
                            <option value="Mt">Metros</option>
                            <option value="Cajas">Cajas</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="producto_precio">Precio unitario</label>
                        <input type="number" id="producto_precio" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label for="producto_impuestos">Impuestos (%)</label>
                        <input type="number" id="producto_impuestos" step="0.01" min="0" value="18">
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn-form primary" onclick="confirmarProducto()">
                        <i class="fas fa-check"></i> Agregar
                    </button>
                    <button type="button" class="btn-form secondary" onclick="cerrarModalProducto()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // VARIABLES GLOBALES
        let productos = [];
        let productoSeleccionado = null;
        let dropdownAbierto = null;
        let timeoutBusqueda = null;

        // Cargar productos existentes si estamos editando
        <?php if (!empty($productos_actuales)): ?>
        productos = <?php echo json_encode(array_map(function($p) {
            return [
                'id' => $p['producto_id'],
                'nombre' => $p['producto_nombre'],
                'cantidad' => floatval($p['cantidad']),
                'unidad' => $p['unidad'],
                'precio_unitario' => floatval($p['precio_unitario']),
                'impuestos' => floatval($p['impuestos']),
                'importe' => floatval($p['importe'])
            ];
        }, $productos_actuales)); ?>;
        
        // Actualizar tabla y totales al cargar
        document.addEventListener('DOMContentLoaded', function() {
            actualizarTablaProductos();
            actualizarTotales();
        });
        <?php endif; ?>

        // FUNCIONES PARA PRODUCTOS
        function buscarProductos(search) {
            const dropdown = document.getElementById('productosDropdown');
            
            // Limpiar timeout anterior
            if (timeoutBusqueda) {
                clearTimeout(timeoutBusqueda);
            }
    
            if (search.length < 2) {
                ocultarDropdown();
                return;
            }
            
            // Debounce la búsqueda
            timeoutBusqueda = setTimeout(() => {
                dropdown.innerHTML = '<div class="producto-option">Buscando productos...</div>';
                mostrarDropdown();
                
                const formData = new FormData();
                formData.append('action', 'get_productos');
                formData.append('search', search);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarProductosDropdown(data.productos);
                    } else {
                        dropdown.innerHTML = '<div class="producto-option">Error: ' + (data.error || 'Error desconocido') + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error en fetch:', error);
                    dropdown.innerHTML = '<div class="producto-option">Error de conexión</div>';
                });
            }, 300);
        }

        function mostrarDropdown() {
            const dropdown = document.getElementById('productosDropdown');
            dropdown.style.display = 'block';
            dropdown.classList.add('show');
        }

        function ocultarDropdown() {
            const dropdown = document.getElementById('productosDropdown');
            dropdown.style.display = 'none';
            dropdown.classList.remove('show');
            dropdown.innerHTML = '';
        }

        function mostrarProductosDropdown(productos) {
            const dropdown = document.getElementById('productosDropdown');
            dropdown.innerHTML = '';
            
            if (productos.length === 0) {
                dropdown.innerHTML = '<div class="producto-option">No se encontraron productos</div>';
            } else {
                productos.forEach(producto => {
                    const option = document.createElement('div');
                    option.className = 'producto-option';
                    option.innerHTML = `
                        <div class="product-name">${producto.nombre_producto}</div>
                        <div class="product-details">
                            SKU: ${producto.sku || 'N/A'} | 
                            Precio: S/ ${parseFloat(producto.precio_venta || 0).toFixed(2)} | 
                            Categoría: ${producto.categoria || 'N/A'}
                        </div>
                    `;
                    
                    // Usar addEventListener en lugar de onclick
                    option.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        seleccionarProducto(producto);
                    });
                    
                    dropdown.appendChild(option);
                });
            }
            
            mostrarDropdown();
        }

        function seleccionarProducto(producto) {
            console.log('Seleccionando producto:', producto);
            
            productoSeleccionado = producto;
            document.getElementById('buscar_producto').value = producto.nombre_producto;
            document.getElementById('producto_precio').value = producto.precio_venta || 0;
            
            // FORZAR el cierre del dropdown
            ocultarDropdown();
            
            // Enfocar en el campo cantidad
            setTimeout(() => {
                document.getElementById('producto_cantidad').focus();
            }, 100);
        }

        function agregarProducto() {
            document.getElementById('productoModal').style.display = 'flex';
            
            // Limpiar formulario completamente
            document.getElementById('buscar_producto').value = '';
            document.getElementById('producto_cantidad').value = '';
            document.getElementById('producto_precio').value = '';
            document.getElementById('producto_impuestos').value = '18';
            document.getElementById('producto_unidad').value = 'Unidades';
            
            // Limpiar estado
            productoSeleccionado = null;
            ocultarDropdown();
            
            // Enfocar en el campo de búsqueda
            setTimeout(() => {
                document.getElementById('buscar_producto').focus();
            }, 100);
        }

        function cerrarModalProducto() {
            document.getElementById('productoModal').style.display = 'none';
            ocultarDropdown();
        }

        function confirmarProducto() {
            const nombre = document.getElementById('buscar_producto').value.trim();
            const cantidad = parseFloat(document.getElementById('producto_cantidad').value);
            const unidad = document.getElementById('producto_unidad').value;
            const precio = parseFloat(document.getElementById('producto_precio').value) || 0;
            const impuestosPorcentaje = parseFloat(document.getElementById('producto_impuestos').value) || 0;

            if (!nombre || !cantidad || cantidad <= 0) {
                alert('Por favor complete los campos obligatorios correctamente');
                return;
            }

            const subtotal = cantidad * precio;
            const impuestos = subtotal * (impuestosPorcentaje / 100);
            const importe = subtotal + impuestos;

            const producto = {
                id: productoSeleccionado ? productoSeleccionado.id : null,
                nombre: nombre,
                cantidad: cantidad,
                unidad: unidad,
                precio_unitario: precio,
                impuestos: impuestos,
                importe: importe
            };

            productos.push(producto);
            actualizarTablaProductos();
            actualizarTotales();
            cerrarModalProducto();
            
            console.log('Producto agregado:', producto);
        }

        function actualizarTablaProductos() {
            const tbody = document.getElementById('productosBody');
            tbody.innerHTML = '';

            productos.forEach((producto, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${producto.nombre}</td>
                    <td>${producto.cantidad}</td>
                    <td>${producto.unidad}</td>
                    <td>S/ ${producto.precio_unitario.toFixed(2)}</td>
                    <td>S/ ${producto.impuestos.toFixed(2)}</td>
                    <td>S/ ${producto.importe.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn-sm btn-delete" onclick="eliminarProducto(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function eliminarProducto(index) {
            if (confirm('¿Eliminar este producto?')) {
                productos.splice(index, 1);
                actualizarTablaProductos();
                actualizarTotales();
            }
        }

        function actualizarTotales() {
            const subtotal = productos.reduce((sum, p) => sum + (p.cantidad * p.precio_unitario), 0);
            const total = productos.reduce((sum, p) => sum + p.importe, 0);

            document.getElementById('subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
            document.getElementById('total').textContent = `S/ ${total.toFixed(2)}`;
        }

        // FUNCIONES PARA SOLICITUDES
        function guardarSolicitud() {
            const form = document.getElementById('solicitudForm');
            const formData = new FormData();
            
            // Determinar si es crear o editar
            const solicitudId = form.querySelector('input[name="solicitud_id"]');
            const action = solicitudId && solicitudId.value ? 'update_solicitud' : 'save_solicitud';
            formData.append('action', action);
            
            // Obtener todos los campos del formulario
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.name && input.name !== 'action') {
                    formData.append(input.name, input.value || '');
                }
            });
            
            const subtotal = productos.reduce((sum, p) => sum + (p.cantidad * p.precio_unitario), 0);
            const total = productos.reduce((sum, p) => sum + p.importe, 0);
            formData.append('subtotal', subtotal);
            formData.append('total', total);
            formData.append('productos', JSON.stringify(productos));
            
            console.log('💾 Guardando solicitud...');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.numero_solicitud ? 
                        `Solicitud creada exitosamente: ${data.numero_solicitud}` : 
                        'Solicitud actualizada exitosamente'
                    );
                    window.location.href = '?vista=solicitudes';
                } else {
                    alert('Error al guardar la solicitud: ' + (data.error || 'Error desconocido'));
                    console.error('Error details:', data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al guardar la solicitud');
            });
        }

        function editarSolicitud(id) {
            window.location.href = `?vista=editar&solicitud_id=${id}`;
            cerrarDropdowns();
        }

        function enviarSolicitud(id) {
            if (!confirm('¿Enviar esta solicitud de cotización?')) return;
            
            const formData = new FormData();
            formData.append('action', 'update_estado');
            formData.append('solicitud_id', id);
            formData.append('estado', 'enviada');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Solicitud enviada exitosamente');
                    window.location.reload();
                } else {
                    alert('Error al enviar: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        function eliminarSolicitud(id) {
            if (!confirm('¿Está seguro de eliminar esta solicitud?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_solicitud');
            formData.append('solicitud_id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Solicitud eliminada exitosamente');
                    window.location.reload();
                } else {
                    alert('Error al eliminar: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        function verSolicitud(id) {
            alert('Función de ver detalles en desarrollo');
            cerrarDropdowns();
        }

        // FUNCIONES PARA FORMULARIO
        function showTab(tabName) {
            // Ocultar todas las secciones
            const sections = document.querySelectorAll('.form-section');
            sections.forEach(section => section.classList.remove('active'));
            
            // Remover clase active de todas las pestañas
            const tabs = document.querySelectorAll('.form-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar la sección seleccionada
            document.getElementById(tabName).classList.add('active');
            
            // Activar la pestaña seleccionada
            event.target.classList.add('active');
        }

        // FUNCIONES PARA DROPDOWNS
        function toggleDropdown(solicitudId) {
            const dropdown = document.getElementById(`dropdown-${solicitudId}`);
            
            cerrarDropdowns();
            
            if (dropdown) {
                dropdown.classList.toggle('show');
                dropdownAbierto = dropdown.classList.contains('show') ? solicitudId : null;
            }
        }

        function cerrarDropdowns() {
            document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            dropdownAbierto = null;
        }

        // EVENT LISTENERS
        document.addEventListener('click', function(event) {
            // Cerrar dropdowns de solicitudes
            if (!event.target.closest('.solicitud-menu')) {
                cerrarDropdowns();
            }
            
            // Cerrar dropdown de productos si se hace click fuera
            if (!event.target.closest('.producto-search')) {
                ocultarDropdown();
            }
        });

        // Manejar teclas especiales
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalProducto();
                cerrarDropdowns();
                ocultarDropdown();
            }
        });

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('productoModal');
            if (event.target == modal) {
                cerrarModalProducto();
            }
        }

        // INICIALIZACIÓN
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Sistema de solicitudes de cotización cargado');
            
            const totalSolicitudes = <?php echo count($solicitudes); ?>;
            console.log('📋 Total de solicitudes cargadas:', totalSolicitudes);
            
            // Agregar event listener al campo de búsqueda
            const buscarInput = document.getElementById('buscar_producto');
            if (buscarInput) {
                buscarInput.addEventListener('input', function(e) {
                    buscarProductos(e.target.value);
                });
                
                buscarInput.addEventListener('focus', function() {
                    if (this.value.length >= 2) {
                        buscarProductos(this.value);
                    }
                });
                
                buscarInput.addEventListener('blur', function() {
                    // Delay para permitir que el click en el dropdown funcione
                    setTimeout(() => {
                        ocultarDropdown();
                    }, 200);
                });
            }
        });

        // Debug: Verificar que la tabla inventario existe
        function verificarInventario() {
            const formData = new FormData();
            formData.append('action', 'get_productos');
            formData.append('search', '');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Verificación de inventario:', data);
                if (data.success) {
                    console.log('✅ Inventario cargado correctamente:', data.productos.length, 'productos encontrados');
                } else {
                    console.error('❌ Error al cargar inventario:', data.error);
                }
            })
            .catch(error => {
                console.error('❌ Error de conexión:', error);
            });
        }

        // Ejecutar verificación al cargar la página
        document.addEventListener('DOMContentLoaded', verificarInventario);
</script>
</body>
</html>
