<?php
// punto-venta.php - VERSIÓN CORREGIDA
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

// PROCESAR ACCIONES AJAX
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
        switch ($_POST['action']) {
        case 'obtener_responsables':
            try {
                $stmt = $pdo->query("SELECT id, nombres, apellidos, correo FROM iniciosesion ORDER BY nombres");
                $responsables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'responsables' => $responsables]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'crear_caja':
            try {
                $stmt = $pdo->prepare("INSERT INTO cajas_registradoras (nombre_caja, responsable, responsable_id, estado, activa, fecha_creacion) VALUES (?, ?, ?, 'cerrada', 1, NOW())");
                $stmt->execute([
                    $_POST['nombre_caja'] ?? '',
                    $_POST['responsable_nombre'] ?? '',
                    $_POST['responsable_id'] ?? null
                ]);
                echo json_encode(['success' => true, 'message' => 'Caja creada exitosamente']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'editar_caja':
            try {
                $stmt = $pdo->prepare("UPDATE cajas_registradoras SET nombre_caja = ?, responsable = ?, responsable_id = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['nombre_caja'] ?? '',
                    $_POST['responsable_nombre'] ?? '',
                    $_POST['responsable_id'] ?? null,
                    $_POST['caja_id'] ?? 0
                ]);
                echo json_encode(['success' => true, 'message' => 'Caja actualizada exitosamente']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'eliminar_caja':
            try {
                $stmt = $pdo->prepare("SELECT id FROM cajas_registradoras WHERE id = ?");
                $stmt->execute([$_POST['caja_id'] ?? 0]);
                                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("DELETE FROM cajas_registradoras WHERE id = ? LIMIT 1");
                    $result = $stmt->execute([$_POST['caja_id'] ?? 0]);
                                        if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Caja eliminada exitosamente']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la caja']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'La caja no existe']);
                }
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'obtener_caja':
            try {
                $stmt = $pdo->prepare("SELECT * FROM cajas_registradoras WHERE id = ?");
                $stmt->execute([$_POST['caja_id'] ?? 0]);
                $caja = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'caja' => $caja]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'abrir_caja':
            try {
                $stmt = $pdo->prepare("UPDATE cajas_registradoras SET estado = 'abierta', fecha_apertura = NOW() WHERE id = ?");
                $stmt->execute([$_POST['caja_id'] ?? 0]);
                echo json_encode(['success' => true, 'message' => 'Caja abierta exitosamente']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'cerrar_caja':
            try {
                $stmt = $pdo->prepare("UPDATE cajas_registradoras SET estado = 'cerrada', fecha_cierre = NOW() WHERE id = ?");
                $stmt->execute([$_POST['caja_id'] ?? 0]);
                echo json_encode(['success' => true, 'message' => 'Caja cerrada exitosamente']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'crear_nueva_orden':
            try {
                // Verificar si existe la tabla ordenes_venta, si no, usar un contador simple
                $stmt = $pdo->query("SHOW TABLES LIKE 'ordenes_venta'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->query("SELECT MAX(id) as max_orden FROM ordenes_venta");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $nuevo_numero = ($result['max_orden'] ?? 200) + 1;
                                        $stmt = $pdo->prepare("INSERT INTO ordenes_venta (numero_orden, caja_id, estado, fecha_creacion) VALUES (?, ?, 'activa', NOW())");$stmt->execute([$nuevo_numero, $_POST['caja_id'] ?? 0]);
                } else {
                    // Si no existe la tabla, generar número basado en timestamp
                    $nuevo_numero = 200 + (int)(time() % 1000);
                }
                                echo json_encode([
                    'success' => true,
                    'numero_orden' => $nuevo_numero,
                    'message' => 'Nueva orden creada exitosamente'
                ]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'crear_cliente':
            try {
                $stmt = $pdo->prepare("INSERT INTO clientes (tipo, nombre, apellidos, nombre_empresa, email, telefono, celular, direccion, ciudad, estado, codigo_postal, pais, documento_tipo, documento_numero, puesto_trabajo, sitio_web, idioma, etiquetas, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                                $stmt->execute([
                    $_POST['tipo'] ?? 'persona',
                    $_POST['nombre'] ?? '',
                    $_POST['apellidos'] ?? '',
                    $_POST['nombre_empresa'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['telefono'] ?? '',
                    $_POST['celular'] ?? '',
                    $_POST['direccion'] ?? '',
                    $_POST['ciudad'] ?? '',
                    $_POST['estado'] ?? '',
                    $_POST['codigo_postal'] ?? '',
                    $_POST['pais'] ?? 'Perú',
                    $_POST['documento_tipo'] ?? 'DNI',
                    $_POST['documento_numero'] ?? '',
                    $_POST['puesto_trabajo'] ?? '',
                    $_POST['sitio_web'] ?? '',
                    $_POST['idioma'] ?? 'Español',
                    $_POST['etiquetas'] ?? ''
                ]);
                                $clienteId = $pdo->lastInsertId();
                                $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
                $stmt->execute([$clienteId]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                                echo json_encode(['success' => true, 'message' => 'Cliente creado exitosamente', 'cliente' => $cliente]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'buscar_clientes':
            try {
                $search = $_POST['search'] ?? '';
                $sql = "SELECT * FROM clientes WHERE activo = 1";
                $params = [];
                                if (!empty($search)) {
                    $sql .= " AND (nombre LIKE ? OR apellidos LIKE ? OR email LIKE ? OR telefono LIKE ? OR documento_numero LIKE ?)";
                    $searchParam = "%$search%";
                    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
                }
                                $sql .= " ORDER BY nombre LIMIT 50";
                                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                echo json_encode(['success' => true, 'clientes' => $clientes]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
                    case 'agregar_producto_carrito':
            try {
                $stmt = $pdo->prepare("SELECT id, nombre_producto, imagen, precio_venta, categoria, sku FROM inventario WHERE id = ?");
                $stmt->execute([$_POST['producto_id'] ?? 0]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($producto) {
                    echo json_encode(['success' => true, 'producto' => $producto]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
                }
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        case 'agregar_producto_personalizado':
            try {
                $stmt = $pdo->prepare("SELECT id, nombre_producto, imagen, precio_venta, categoria, sku FROM inventario WHERE id = ?");
                $stmt->execute([$_POST['producto_id'] ?? 0]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($producto) {
                    $productoPersonalizado = [
                        'id' => $producto['id'],
                        'nombre_producto' => $producto['nombre_producto'],
                        'imagen' => $producto['imagen'],
                        'categoria' => $producto['categoria'],
                        'sku' => $producto['sku'],
                        'precio_venta' => floatval($_POST['precio_personalizado'] ?? $producto['precio_venta']),
                        'cantidad_personalizada' => floatval($_POST['cantidad_personalizada'] ?? 1),
                        'precio_original' => $producto['precio_venta']
                    ];
                                        echo json_encode(['success' => true, 'producto' => $productoPersonalizado]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
                }
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        case 'procesar_venta_completa':
            try {
                $pdo->beginTransaction();
                                // Datos de la venta
                $orden_numero = $_POST['numero_orden'] ?? 0;
                $caja_id = $_POST['caja_id'] ?? 0;
                $cliente_id = $_POST['cliente_id'] ?? null;
                $cliente_nombre = $_POST['cliente_nombre'] ?? 'Consumidor Final';
                $subtotal = floatval($_POST['subtotal'] ?? 0);
                $igv = floatval($_POST['igv'] ?? 0);
                $total = floatval($_POST['total'] ?? 0);
                $metodo_pago = $_POST['metodo_pago'] ?? '';
                $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
                $vuelto = floatval($_POST['vuelto'] ?? 0);
                $productos = json_decode($_POST['productos'] ?? '[]', true);
                                // VALIDAR CLIENTE_ID - Esta es la parte importante
                if ($cliente_id !== null && !empty($cliente_id)) {
                    // Verificar que el cliente existe
                    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND activo = 1");
                    $stmt->execute([$cliente_id]);
                    if (!$stmt->fetch()) {
                        // Si el cliente no existe, establecer como NULL
                        $cliente_id = null;
                        $cliente_nombre = 'Consumidor Final';
                    }
                } else {
                    // Si no se proporciona cliente_id o está vacío, establecer como NULL
                    $cliente_id = null;
                    $cliente_nombre = 'Consumidor Final';
                }

                // --- INICIO DE LA CORRECCIÓN ---
                // Obtener el responsable de la caja actual
                $stmt_responsable = $pdo->prepare("SELECT responsable_id, responsable FROM cajas_registradoras WHERE id = ?");
                $stmt_responsable->execute([$caja_id]);
                $caja_responsable_info = $stmt_responsable->fetch(PDO::FETCH_ASSOC);

                $usuario_id_venta = $caja_responsable_info['responsable_id'] ?? null;
                $usuario_nombre_venta = $caja_responsable_info['responsable'] ?? 'Desconocido';
                // --- FIN DE LA CORRECCIÓN ---

                // Verificar estructura de tabla ventas
                $stmt = $pdo->query("DESCRIBE ventas");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                // Construir query dinámicamente basado en columnas existentes
                $ventaData = [
                    'cliente_id' => $cliente_id,  // Ahora será NULL o un ID válido
                    'total' => $total,
                    'fecha_venta' => date('Y-m-d H:i:s'),
                    'estado' => 'completada'
                ];
                                // Agregar campos opcionales si existen
                if (in_array('caja_id', $columns)) {
                    $ventaData['caja_id'] = $caja_id;
                }
                if (in_array('cliente_nombre', $columns)) {
                    $ventaData['cliente_nombre'] = $cliente_nombre;
                }
                if (in_array('subtotal', $columns)) {
                    $ventaData['subtotal'] = $subtotal;
                }
                if (in_array('igv', $columns)) {
                    $ventaData['igv'] = $igv;
                }
                if (in_array('metodo_pago', $columns)) {
                    $ventaData['metodo_pago'] = $metodo_pago;
                }
                if (in_array('monto_pagado', $columns)) {
                    $ventaData['monto_pagado'] = $monto_pagado;
                }
                if (in_array('vuelto', $columns)) {
                    $ventaData['vuelto'] = $vuelto;
                }
                // --- CAMBIO AQUÍ: Usar la información del responsable de la caja ---
                if (in_array('usuario_id', $columns)) {
                    $ventaData['usuario_id'] = $usuario_id_venta;
                }
                if (in_array('usuario_nombre', $columns)) {
                    $ventaData['usuario_nombre'] = $usuario_nombre_venta;
                }
                // --- FIN DEL CAMBIO ---
                if (in_array('numero_venta', $columns)) {
                    $ventaData['numero_venta'] = 'V-' . str_pad($orden_numero, 6, '0', STR_PAD_LEFT);
                }
                                // Construir query INSERT dinámicamente
                $campos = implode(', ', array_keys($ventaData));
                $placeholders = ':' . implode(', :', array_keys($ventaData));
                                $stmt = $pdo->prepare("INSERT INTO ventas ($campos) VALUES ($placeholders)");
                $stmt->execute($ventaData);
                                $venta_id = $pdo->lastInsertId();
                                // Insertar detalles de venta
                foreach ($productos as $producto) {
                    // Verificar estructura de tabla venta_detalles
                    $stmt = $pdo->query("DESCRIBE venta_detalles");
                    $detailColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                        // *** CORRECCIÓN PRINCIPAL: Usar 'costo' en lugar de 'precio_compra' ***
                    $stmt = $pdo->prepare("SELECT costo FROM inventario WHERE id = ?");
                    $stmt->execute([$producto['id']]);
                    $info_producto = $stmt->fetch(PDO::FETCH_ASSOC);
                                        // Usar la columna 'costo' y manejar valores nulos
                    $costo_unitario = floatval($info_producto['costo'] ?? 0);
                    $ganancia_unitaria = $producto['precio'] - $costo_unitario;
                    $ganancia_total = $ganancia_unitaria * $producto['cantidad'];
                                        // Construir datos del detalle
                    $detalleData = [
                        'venta_id' => $venta_id,
                        'producto_id' => $producto['id'],
                        'cantidad' => $producto['cantidad'],
                        'precio_unitario' => $producto['precio'],
                        'subtotal' => $producto['precio'] * $producto['cantidad']
                    ];
                                        // Agregar campos opcionales si existen
                    if (in_array('producto_nombre', $detailColumns)) {
                        $detalleData['producto_nombre'] = $producto['nombre'];
                    }
                    if (in_array('producto_sku', $detailColumns)) {
                        $detalleData['producto_sku'] = $producto['sku'] ?? '';
                    }
                    if (in_array('categoria', $detailColumns)) {
                        $detalleData['categoria'] = $producto['categoria'] ?? '';
                    }
                    if (in_array('precio_original', $detailColumns)) {
                        $detalleData['precio_original'] = $producto['precio_original'] ?? $producto['precio'];
                    }
                    if (in_array('costo_unitario', $detailColumns)) {
                        $detalleData['costo_unitario'] = $costo_unitario;
                    }
                    if (in_array('ganancia_unitaria', $detailColumns)) {
                        $detalleData['ganancia_unitaria'] = $ganancia_unitaria;
                    }
                    if (in_array('ganancia_total', $detailColumns)) {
                        $detalleData['ganancia_total'] = $ganancia_total;
                    }
                    if (in_array('fecha_venta', $detailColumns)) {
                        $detalleData['fecha_venta'] = date('Y-m-d H:i:s');
                    }
                    if (in_array('descuento', $detailColumns)) {
                        $detalleData['descuento'] = 0;
                    }
                                        // Construir query INSERT para detalles
                    $camposDetalle = implode(', ', array_keys($detalleData));
                    $placeholdersDetalle = ':' . implode(', :', array_keys($detalleData));
                                        $stmt = $pdo->prepare("INSERT INTO venta_detalles ($camposDetalle) VALUES ($placeholdersDetalle)");
                    $stmt->execute($detalleData);
                }
                                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Venta procesada exitosamente', 'venta_id' => $venta_id]);
                            } catch(PDOException $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Resto del código PHP permanece igual...
$vista = $_GET['vista'] ?? 'cajas';
$caja_id = $_GET['caja_id'] ?? null;
$view_mode = $_GET['view'] ?? 'cards';
$producto_id = $_GET['producto_id'] ?? null;

// Obtener cajas registradoras
try {
    $cajas = $pdo->query("SELECT * FROM cajas_registradoras ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $cajas = [];
    error_log("Error al cargar cajas: " . $e->getMessage());
}

// Obtener productos por categoría si estamos en vista de venta
$productos_por_categoria = [];
if ($vista == 'venta' && $caja_id) {
    try {
        $stmt = $pdo->query("SELECT id, nombre_producto, imagen, precio_venta, categoria, sku, codigo_barras FROM inventario ORDER BY categoria, nombre_producto");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($productos as $producto) {
            $categoria = !empty($producto['categoria']) ? $producto['categoria'] : 'Sin Categoría';
            if (!isset($productos_por_categoria[$categoria])) {
                $productos_por_categoria[$categoria] = [];
            }
            $productos_por_categoria[$categoria][] = $producto;
        }
            } catch(PDOException $e) {
        error_log("❌ Error al cargar productos: " . $e->getMessage());
        $productos_por_categoria = [];
    }
}

// Obtener clientes
try {
    $clientes = $pdo->query("SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $clientes = [];
}

// Obtener información de la caja si estamos en vista de venta
$caja_actual = null;
if ($vista == 'venta' && $caja_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM cajas_registradoras WHERE id = ?");
        $stmt->execute([$caja_id]);
        $caja_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $caja_actual = null;
    }
}

// Obtener responsables para el dropdown
try {
    $responsables = $pdo->query("SELECT id, nombres, apellidos, correo FROM iniciosesion ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $responsables = [];
}
?>
<!DOCTYPE html><html lang="es"><head>    <meta charset="UTF-8">    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Punto de Venta - Sistema ERP ELESS</title>    <link rel="stylesheet" href="css/siderbarycabezal.css?v=1.0">    <link rel="stylesheet" href="css/punto-venta.css">    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">        <style>        /* ===== estilos carritos ===== */        /* ===== ESTILOS PARA LA INTERFAZ DE SELECCIÓN DE PRODUCTO EN EL CARRITO ===== */.product-selection-in-cart {  background: white;  border-radius: 10px;  margin: 12px;  border: 2px solid #17a2b8;  overflow: hidden;  min-height: 550px;  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);}.product-header-cart {  padding: 18px;  border-bottom: 2px solid #e2e8f0;  background: #f8f9fa;}.product-info-cart {  display: flex;  justify-content: space-between;  align-items: flex-start;}.product-details-cart {  flex: 1;}.product-code-cart {  color: #17a2b8;  font-weight: bold;  font-size: 15px;}.product-name-cart {  font-size: 15px;  margin: 3px 0;  font-weight: 500;}.product-quantity-cart {  font-size: 13px;  color: #666;  margin-top: 6px;}.product-price-cart {  font-size: 20px;  font-weight: bold;  text-align: right;  color: #2d3436;}.country-flag-cart {  background: #dc3545;  color: white;  padding: 2px 5px;  border-radius: 3px;  font-size: 10px;  margin-left: 5px;}.totals-section-cart {  padding: 16px 18px;  border-top: 2px solid #ddd;  background: #f1f3f4;}.total-row-cart {  display: flex;  justify-content: space-between;  margin-bottom: 6px;  font-size: 15px;}.total-row-cart.final {  font-size: 18px;  font-weight: bold;  border-top: 2px solid #ddd;  padding-top: 10px;  margin-top: 10px;  color: #2d3436;}.input-controls-cart {  padding: 18px;}.control-buttons-cart {  display: grid;  grid-template-columns: 1fr 1fr 1fr;  gap: 10px;  margin-bottom: 16px;}.control-btn-cart {  padding: 14px;  border: 2px solid #ddd;  background: white;  border-radius: 6px;  cursor: pointer;  font-size: 14px;  font-weight: 600;  transition: all 0.2s;}.control-btn-cart.active {  background: #17a2b8;  color: white;  border-color: #17a2b8;  transform: translateY(-1px);  box-shadow: 0 3px 6px rgba(23, 162, 184, 0.3);}.control-btn-cart:hover:not(.active) {  background: #f8f9fa;  border-color: #17a2b8;}.current-input-cart {  background: #e9ecef;  padding: 14px;  border-radius: 6px;  text-align: center;  font-family: "Courier New", monospace;  font-size: 18px;  font-weight: bold;  margin-bottom: 16px;  min-height: 24px;  border: 2px solid #ced4da;  color: #495057;}.number-pad-cart {  display: grid;  grid-template-columns: repeat(3, 1fr);  gap: 10px;  margin-bottom: 16px;}.number-btn-cart {  padding: 16px;  border: 2px solid #ddd;  background: white;  border-radius: 6px;  cursor: pointer;  font-size: 17px;  font-weight: bold;  transition: all 0.2s;  color: #495057;}.number-btn-cart:hover {  background: #f8f9fa;  border-color: #17a2b8;  transform: translateY(-1px);  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);}.number-btn-cart.special {  background: #fff3cd;  border-color: #ffeaa7;}.number-btn-cart.special:hover {  background: #ffeaa7;}.number-btn-cart.delete {  background: #f8d7da;  border-color: #f5c6cb;  color: #721c24;}.number-btn-cart.delete:hover {  background: #f5c6cb;}.next-button-cart {  background: linear-gradient(135deg, #6f42c1, #5a2d91);  color: white;  border: none;  padding: 16px;  width: 100%;  border-radius: 6px;  font-size: 16px;  font-weight: bold;  cursor: pointer;  transition: all 0.3s;  position: relative;  box-shadow: 0 3px 10px rgba(111, 66, 193, 0.3);}.next-button-cart:hover {  background: linear-gradient(135deg, #5a2d91, #4a1f7a);  transform: translateY(-1px);  box-shadow: 0 5px 14px rgba(111, 66, 193, 0.4);}.next-button-cart:disabled {  background: #6c757d;  cursor: not-allowed;  transform: none;  box-shadow: none;}.next-button-cart .loading-spinner {  display: none;  width: 16px;  height: 16px;  border: 2px solid #ffffff;  border-top: 2px solid transparent;  border-radius: 50%;  animation: spin 1s linear infinite;  margin-right: 6px;}.next-button-cart.loading .loading-spinner {  display: inline-block;}@keyframes spin {  0% {    transform: rotate(0deg);  }  100% {    transform: rotate(360deg);  }}.success-animation {  position: fixed;  top: 50%;  left: 50%;  transform: translate(-50%, -50%);  background: linear-gradient(135deg, #28a745, #20c997);  color: white;  padding: 20px 30px;  border-radius: 10px;  font-size: 18px;  font-weight: bold;  z-index: 9999;  opacity: 0;  animation: successPulse 2.2s ease-in-out;  box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);}@keyframes successPulse {  0% {    opacity: 0;    transform: translate(-50%, -50%) scale(0.8);  }  20% {    opacity: 1;    transform: translate(-50%, -50%) scale(1.1);  }  80% {    opacity: 1;    transform: translate(-50%, -50%) scale(1);  }  100% {    opacity: 0;    transform: translate(-50%, -50%) scale(0.9);  }}/* Ocultar elementos cuando está en modo selección */.carrito-panel.selection-mode .cliente-seleccionado,.carrito-panel.selection-mode .carrito-placeholder,.carrito-panel.selection-mode .carrito-items {  display: none !important;}/* Animación para botones */.control-btn-cart,.number-btn-cart {  user-select: none;}.control-btn-cart:active,.number-btn-cart:active {  transform: translateY(0px);}/* ===== ESTILOS PARA MODALES DE PAGO ===== */.modal-pago {  position: fixed;  top: 0;  left: 0;  width: 100%;  height: 100%;  background: rgba(0, 0, 0, 0.8);  display: flex;  justify-content: center;  align-items: center;  z-index: 10000;}.modal-pago-content {  background: white;  border-radius: 12px;  width: 90%;  max-width: 500px;  max-height: 90vh;  overflow-y: auto;  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);}.modal-pago-header {  padding: 20px;  border-bottom: 1px solid #e5e7eb;  text-align: center;}.orden-tab-pago {  display: inline-block;  background: #f3f4f6;  border: 2px solid #d1d5db;  border-radius: 8px;  padding: 8px 16px;  font-weight: bold;  font-size: 18px;  color: #374151;}.modal-pago-body {  padding: 20px;}.metodos-pago {  display: flex;  flex-direction: column;  gap: 12px;}.metodo-pago-item {  display: flex;  align-items: center;  padding: 16px 20px;  background:rgb(255, 255, 255);  border: 2px solid #e5e7eb;  border-radius: 8px;  cursor: pointer;  transition: all 0.2s;  font-size: 16px;  font-weight: 500;}.metodo-pago-item:hover {  background: #f3f4f6;  border-color: #6366f1;  transform: translateY(-1px);}.metodo-pago-item.selected {  background: #eef2ff;  border-color: #6366f1;  color: #6366f1;}.metodo-pago-item i {  margin-right: 12px;  font-size: 20px;  width: 24px;  text-align: center;}/* Teclado de pago */.teclado-pago {  margin-top: 20px;}.cliente-factura-tabs {  display: flex;  gap: 8px;  margin-bottom: 20px;}.tab-pago {  flex: 1;  padding: 12px;  border: 2px solid #e5e7eb;  background: white;  border-radius: 6px;  cursor: pointer;  font-weight: 500;  display: flex;  align-items: center;  justify-content: center;  gap: 8px;}.tab-pago.active {  background: #10b981;  color: white;  border-color: #10b981;}.display-monto {  background: #f3f4f6;  padding: 20px;  text-align: center;  border-radius: 8px;  margin-bottom: 20px;  font-size: 24px;  font-weight: bold;  font-family: "Courier New", monospace;  border: 2px solid #d1d5db;  min-height: 60px;  display: flex;  align-items: center;  justify-content: center;}.teclado-numerico {  display: grid;  grid-template-columns: repeat(4, 1fr);  gap: 10px;  margin-bottom: 20px;}.tecla-num,.tecla-especial {  padding: 16px;  border: 2px solid #d1d5db;  background: white;  border-radius: 6px;  cursor: pointer;  font-size: 18px;  font-weight: bold;  transition: all 0.2s;}.tecla-num:hover,.tecla-especial:hover {  background: #f3f4f6;  transform: translateY(-1px);}.tecla-especial.verde {  background: #dcfce7;  border-color: #16a34a;  color: #16a34a;}.tecla-especial.amarillo {  background: #fef3c7;  border-color: #d97706;  color: #d97706;}.tecla-especial.rojo {  background: #fee2e2;  border-color: #dc2626;  color: #dc2626;}.botones-accion-pago {  display: flex;  gap: 12px;}.btn-regresar {  flex: 1;  padding: 16px;  background: #f3f4f6;  border: 2px solid #d1d5db;  border-radius: 6px;  cursor: pointer;  font-size: 16px;  font-weight: bold;  color: #374151;}.btn-validar {  flex: 1;  padding: 16px;  background: #7c3aed;  border: 2px solid #7c3aed;  border-radius: 6px;  cursor: pointer;  font-size: 16px;  font-weight: bold;  color: white;}.btn-validar:hover {  background: #6d28d9;  border-color: #6d28d9;}/* ===== MODAL DE PAGO EXITOSO ===== */.modal-pago-exitoso {  position: fixed;  top: 0;  left: 0;  width: 100%;  height: 100%;  background: rgba(0, 0, 0, 0.9);  display: flex;  justify-content: center;  align-items: center;  z-index: 10001;}.modal-exitoso-content {  display: flex;  width: 95%;  max-width: 1200px;  height: 90vh;  background: white;  border-radius: 12px;  overflow: hidden;  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);  position: relative;}.pago-exitoso-info {  flex: 1;  padding: 40px;  display: flex;  flex-direction: column;  justify-content: center;  align-items: center;  gap: 30px;}.mensaje-exito {  text-align: center;}.mensaje-exito i {  font-size: 60px;  color: #10b981;  margin-bottom: 20px;}.mensaje-exito h2 {  font-size: 28px;  color: #10b981;  margin-bottom: 10px;}.mensaje-exito h3 {  font-size: 32px;  font-weight: bold;  color: #1f2937;}.btn-imprimir-recibo {  width: 100%;  padding: 16px 24px;  background: #6b7280;  color: white;  border: none;  border-radius: 8px;  font-size: 16px;  font-weight: bold;  cursor: pointer;  display: flex;  align-items: center;  justify-content: center;  gap: 10px;}.envio-email {  display: flex;  width: 100%;  gap: 10px;}.envio-email input {  flex: 1;  padding: 12px 16px;  border: 2px solid #d1d5db;  border-radius: 6px;  font-size: 14px;}.btn-enviar-email {  padding: 12px 16px;  background: #8b5cf6;  color: white;  border: none;  border-radius: 6px;  cursor: pointer;}.recibo-preview {  flex: 1;  background: #f9fafb;  padding: 30px;  overflow-y: auto;  border-left: 1px solid #e5e7eb;}.recibo-header {  text-align: center;  margin-bottom: 30px;}.logo-recibo {  font-size: 24px;  font-weight: bold;  margin-bottom: 10px;}.recibo-header h2 {  font-size: 48px;  font-weight: bold;  margin: 20px 0;  color: #1f2937;}.recibo-items {  margin-bottom: 30px;}.recibo-item {  display: flex;  justify-content: space-between;  align-items: center;  padding: 10px 0;  border-bottom: 1px solid #e5e7eb;}.item-detalle h4 {  font-weight: bold;  margin-bottom: 4px;}.item-detalle p {  color: #6b7280;  font-size: 14px;}.linea-separadora {  border-top: 2px dashed #d1d5db;  margin: 15px 0;}.total-row {  display: flex;  justify-content: space-between;  margin-bottom: 8px;  font-size: 16px;}.total-row.final {  font-size: 20px;  font-weight: bold;  padding-top: 10px;}.efectivo-row {  display: flex;  justify-content: space-between;  margin-bottom: 6px;  color: #6b7280;  font-size: 14px;}.recibo-footer {  text-align: center;  margin-top: 30px;  color: #6b7280;  font-size: 12px;}.btn-nueva-orden {  position: absolute;  bottom: 0;  left: 0;  right: 0;  padding: 20px;  background: #7c3aed;  color: white;  border: none;  font-size: 18px;  font-weight: bold;  cursor: pointer;}.btn-nueva-orden:hover {  background: #6d28d9;}/* ===== RESPONSIVE DESIGN ===== *//* Tablets (768px - 1024px) */@media screen and (max-width: 1024px) and (min-width: 769px) {  .product-selection-in-cart {    margin: 8px;    min-height: 500px;  }  .product-header-cart {    padding: 16px;  }  .product-info-cart {    flex-direction: column;    gap: 10px;  }  .product-price-cart {    text-align: left;    font-size: 18px;  }  .input-controls-cart {    padding: 16px;  }  .control-buttons-cart {    gap: 8px;    margin-bottom: 14px;  }  .control-btn-cart {    padding: 12px;    font-size: 13px;  }  .number-pad-cart {    gap: 8px;    margin-bottom: 14px;  }  .number-btn-cart {    padding: 14px;    font-size: 16px;  }  .modal-pago-content {    width: 85%;    max-width: 600px;  }  .teclado-numerico {    gap: 8px;  }  .tecla-num,  .tecla-especial {    padding: 14px;    font-size: 16px;  }  .modal-exitoso-content {    width: 90%;    height: 85vh;  }  .pago-exitoso-info {    padding: 30px;    gap: 25px;  }  .mensaje-exito i {    font-size: 50px;  }  .mensaje-exito h2 {    font-size: 24px;  }  .mensaje-exito h3 {    font-size: 28px;  }  .recibo-preview {    padding: 25px;  }  .recibo-header h2 {    font-size: 40px;  }}/* Mobile Devices (320px - 768px) */@media screen and (max-width: 768px) {  .product-selection-in-cart {    margin: 4px;    min-height: auto;    border-radius: 8px;  }  .product-header-cart {    padding: 12px;  }  .product-info-cart {    flex-direction: column;    gap: 8px;  }  .product-code-cart {    font-size: 13px;  }  .product-name-cart {    font-size: 14px;  }  .product-quantity-cart {    font-size: 12px;  }  .product-price-cart {    text-align: left;    font-size: 16px;    margin-top: 5px;  }  .totals-section-cart {    padding: 12px;  }  .total-row-cart {    font-size: 14px;    margin-bottom: 4px;  }  .total-row-cart.final {    font-size: 16px;    padding-top: 8px;    margin-top: 8px;  }  .input-controls-cart {    padding: 12px;  }  .control-buttons-cart {    gap: 6px;    margin-bottom: 12px;  }  .control-btn-cart {    padding: 10px 8px;    font-size: 12px;  }  .current-input-cart {    padding: 12px;    font-size: 16px;    margin-bottom: 12px;  }  .number-pad-cart {    gap: 6px;    margin-bottom: 12px;  }  .number-btn-cart {    padding: 12px 8px;    font-size: 15px;  }  .next-button-cart {    padding: 14px;    font-size: 15px;  }  /* Modal de Pago - Mobile */  .modal-pago-content {    width: 95%;    max-width: none;    margin: 10px;    max-height: 95vh;  }  .modal-pago-header {    padding: 15px;  }  .orden-tab-pago {    padding: 6px 12px;    font-size: 16px;  }  .modal-pago-body {    padding: 15px;  }  .metodos-pago {    gap: 10px;  }  .metodo-pago-item {    padding: 12px 15px;    font-size: 15px;  }  .metodo-pago-item i {    font-size: 18px;    margin-right: 10px;  }  .cliente-factura-tabs {    gap: 6px;    margin-bottom: 15px;  }  .tab-pago {    padding: 10px 8px;    font-size: 13px;    gap: 6px;  }  .display-monto {    padding: 15px;    font-size: 20px;    margin-bottom: 15px;    min-height: 50px;  }  .teclado-numerico {    gap: 8px;    margin-bottom: 15px;  }  .tecla-num,  .tecla-especial {    padding: 12px 8px;    font-size: 16px;  }  .botones-accion-pago {    gap: 10px;    flex-direction: column;  }  .btn-regresar,  .btn-validar {    padding: 14px;    font-size: 15px;  }  /* Modal Pago Exitoso - Mobile */  .modal-exitoso-content {    flex-direction: column;    width: 95%;    height: 95vh;    margin: 10px;  }  .pago-exitoso-info {    padding: 20px 15px;    gap: 20px;    flex: none;    min-height: 40%;  }  .mensaje-exito i {    font-size: 40px;    margin-bottom: 15px;  }  .mensaje-exito h2 {    font-size: 20px;    margin-bottom: 8px;  }  .mensaje-exito h3 {    font-size: 24px;  }  .btn-imprimir-recibo {    padding: 12px 16px;    font-size: 14px;  }  .envio-email {    flex-direction: column;    gap: 8px;  }  .envio-email input {    padding: 10px 12px;    font-size: 14px;  }  .btn-enviar-email {    padding: 10px 12px;    align-self: stretch;  }  .recibo-preview {    border-left: none;    border-top: 1px solid #e5e7eb;    padding: 15px;    flex: 1;    overflow-y: auto;  }  .recibo-header {    margin-bottom: 20px;  }  .logo-recibo {    font-size: 20px;    margin-bottom: 8px;  }  .recibo-header h2 {    font-size: 32px;    margin: 15px 0;  }  .recibo-items {    margin-bottom: 20px;  }  .recibo-item {    flex-direction: column;    align-items: flex-start;    padding: 8px 0;    gap: 4px;  }  .item-detalle h4 {    font-size: 14px;    margin-bottom: 2px;  }  .item-detalle p {    font-size: 12px;  }  .item-precio {    font-weight: bold;    color: #1f2937;  }  .total-row {    font-size: 14px;    margin-bottom: 6px;  }  .total-row.final {    font-size: 18px;    padding-top: 8px;  }  .efectivo-row {    font-size: 13px;    margin-bottom: 4px;  }  .recibo-footer {    margin-top: 20px;    font-size: 11px;  }  .btn-nueva-orden {    padding: 15px;    font-size: 16px;    position: relative;    margin-top: 10px;  }  /* Success Animation - Mobile */  .success-animation {    padding: 15px 20px;    font-size: 16px;    border-radius: 8px;    max-width: 90%;    text-align: center;  }}/* Small Mobile Devices (320px - 480px) */@media screen and (max-width: 480px) {  .product-selection-in-cart {    margin: 2px;  }  .product-header-cart {    padding: 10px;  }  .product-code-cart {    font-size: 12px;  }  .product-name-cart {    font-size: 13px;  }  .product-price-cart {    font-size: 15px;  }  .totals-section-cart {    padding: 10px;  }  .input-controls-cart {    padding: 10px;  }  .control-btn-cart {    padding: 8px 6px;    font-size: 11px;  }  .number-btn-cart {    padding: 10px 6px;    font-size: 14px;  }  .current-input-cart {    padding: 10px;    font-size: 15px;  }  .next-button-cart {    padding: 12px;    font-size: 14px;  }  .modal-pago-content {    margin: 5px;  }  .modal-pago-header,  .modal-pago-body {    padding: 12px;  }  .metodo-pago-item {    padding: 10px 12px;    font-size: 14px;  }  .display-monto {    font-size: 18px;    padding: 12px;  }  .tecla-num,  .tecla-especial {    padding: 10px 6px;    font-size: 15px;  }  .modal-exitoso-content {    margin: 5px;  }  .pago-exitoso-info {    padding: 15px 10px;  }  .mensaje-exito i {    font-size: 35px;  }  .mensaje-exito h2 {    font-size: 18px;  }  .mensaje-exito h3 {    font-size: 22px;  }  .recibo-preview {    padding: 12px;  }  .recibo-header h2 {    font-size: 28px;  }}/* Landscape Mobile Orientation */@media screen and (max-width: 768px) and (orientation: landscape) {  .modal-exitoso-content {    flex-direction: row;    height: 90vh;  }  .pago-exitoso-info {    flex: 0.6;    padding: 15px;    gap: 15px;  }  .mensaje-exito i {    font-size: 35px;    margin-bottom: 10px;  }  .mensaje-exito h2 {    font-size: 18px;    margin-bottom: 5px;  }  .mensaje-exito h3 {    font-size: 20px;  }  .recibo-preview {    flex: 1.4;    border-left: 1px solid #e5e7eb;    border-top: none;  }  .btn-nueva-orden {    position: absolute;    bottom: 0;    left: 0;    right: 0;  }}/* High DPI Displays */@media screen and (-webkit-min-device-pixel-ratio: 2), screen and (min-resolution: 192dpi) {  .number-btn-cart,  .control-btn-cart,  .tecla-num,  .tecla-especial {    border-width: 1px;  }  .product-selection-in-cart,  .modal-pago-content,  .modal-exitoso-content {    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);  }}    </style></head><body>    <?php include 'sidebar.php'; ?>        <div class="main">    <?php include __DIR__ . '/header.php'; ?>                <section class="content">            <?php if ($vista == 'cajas'): ?>            <!-- VISTA DE CAJAS REGISTRADORAS -->            <div class="pos-container">                <div class="pos-header">                    <h1><i class="fas fa-cash-register"></i> Punto de venta</h1>
                    <div class="pos-info">
                        <span>1-<?php echo count($cajas); ?> / <?php echo count($cajas); ?></span>
                        <div class="pos-controls">
                            <button onclick="crearCaja()"><i class="fas fa-plus"></i> Nueva Caja</button>
                            <button onclick="window.location.href='?vista=cajas&view=cards'" class="<?php echo $view_mode == 'cards' ? 'active' : ''; ?>">
                                <i class="fas fa-th"></i>
                            </button>
                            <button onclick="window.location.href='?vista=cajas&view=rows'" class="<?php echo $view_mode == 'rows' ? 'active' : ''; ?>">
                                <i class="fas fa-bars"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php if ($view_mode == 'cards'): ?>
                <div class="cajas-grid">
                    <?php foreach ($cajas as $caja): ?>
                    <div class="caja-card">
                        <div class="caja-header">
                            <h3><?php echo htmlspecialchars($caja['nombre_caja']); ?></h3>
                            <div class="caja-menu" onclick="toggleDropdown(<?php echo $caja['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                                <div class="dropdown-menu" id="dropdown-<?php echo $caja['id']; ?>">
                                    <button class="dropdown-item" onclick="editarCaja(<?php echo $caja['id']; ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="dropdown-item delete" onclick="eliminarCaja(<?php echo $caja['id']; ?>)">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                                                <div class="caja-status">
                            <?php if ($caja['estado'] == 'cerrada'): ?>
                                <span class="status-badge closed">Por cerrar</span>
                            <?php elseif ($caja['estado'] == 'abierta'): ?>
                                <span class="status-badge open">Abierta</span>
                            <?php else: ?>
                                <span class="status-badge in-use">En uso</span>
                            <?php endif; ?>
                        </div>
                        <div class="caja-actions">
                            <?php if ($caja['estado'] == 'cerrada'): ?>
                                <button class="btn-primary" onclick="abrirCaja(<?php echo $caja['id']; ?>)">
                                    Abrir caja registradora
                                </button>
                            <?php else: ?>
                                <button class="btn-success" onclick="window.location.href='?vista=venta&caja_id=<?php echo $caja['id']; ?>'">
                                    Seguir vendiendo
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($caja['estado'] != 'cerrada'): ?>
                        <div class="caja-info">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                                <?php else: ?>
                <table class="cajas-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cajas as $caja): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($caja['nombre_caja']); ?></strong></td>
                            <td><?php echo htmlspecialchars($caja['responsable']); ?></td>
                            <td>
                                <?php if ($caja['estado'] == 'cerrada'): ?>
                                    <span class="status-badge closed">Por cerrar</span>
                                <?php elseif ($caja['estado'] == 'abierta'): ?>
                                    <span class="status-badge open">Abierta</span>
                                <?php else: ?>
                                    <span class="status-badge in-use">En uso</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo isset($caja['fecha_creacion']) ? date('d/m/Y', strtotime($caja['fecha_creacion'])) : 'N/A'; ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($caja['estado'] == 'cerrada'): ?>
                                        <button class="btn-sm btn-primary" onclick="abrirCaja(<?php echo $caja['id']; ?>)">
                                            Abrir
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-sm btn-success" onclick="window.location.href='?vista=venta&caja_id=<?php echo $caja['id']; ?>'">
                                            Vender
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-sm btn-edit" onclick="editarCaja(<?php echo $caja['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-sm btn-delete" onclick="eliminarCaja(<?php echo $caja['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php elseif ($vista == 'venta' && $caja_actual): ?>
            <!-- VISTA DE PUNTO DE VENTA -->
            <div class="venta-container">
                <div class="ordenes-tabs" id="ordenes-tabs">
                    <div class="orden-tab active" data-orden="201">
                        201
                        <button class="close-btn" onclick="cerrarOrden(201)" title="Cerrar orden">×</button>
                    </div>
                    <button class="nueva-orden-btn" onclick="crearNuevaOrden()" title="Nueva orden">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                                <div class="venta-header">
                    <div class="venta-info">
                        <button class="btn-back" onclick="cerrarCajaYVolver()">
                            <i class="fas fa-times"></i>
                        </button>
                        <span class="venta-numero" id="numero-orden-actual">201</span>
                    </div>
                    <div class="venta-actions">
                        <button class="btn-crear" onclick="mostrarModalCliente()">Crear</button>
                        <div class="venta-controls">
                            <button><i class="fas fa-th"></i></button>
                            <button><i class="fas fa-user"></i> <?php echo htmlspecialchars($caja_actual['responsable']); ?></button>
                            <button class="btn-cerrar-caja" onclick="cerrarCaja(<?php echo $caja_actual['id']; ?>)">
                                <i class="fas fa-lock"></i> Cerrar Caja
                            </button>
                            <button><i class="fas fa-bars"></i></button>
                        </div>
                    </div>
                </div>
                <div class="venta-layout">
                    <!-- Panel izquierdo - Carrito / Selección de Producto -->
                    <div class="carrito-panel" id="carrito-panel">
                        <div class="carrito-content">
                            <!-- CLIENTE SELECCIONADO -->
                            <div class="cliente-seleccionado" id="cliente-seleccionado">
                                <h4><i class="fas fa-user-check"></i> Cliente Seleccionado</h4>
                                <p id="cliente-nombre-seleccionado">-</p>
                                <p id="cliente-info-seleccionado">-</p>
                                <button class="btn-cambiar" onclick="cambiarCliente()">Cambiar Cliente</button>
                            </div>
                                                        <!-- INTERFAZ DE SELECCIÓN DE PRODUCTO (SE MUESTRA CUANDO HAY PRODUCTO SELECCIONADO) -->
                            <div class="product-selection-in-cart" id="product-selection-in-cart" style="display: none;">
                                <!-- Header del Producto -->
                                <div class="product-header-cart">
                                    <div class="product-info-cart">
                                        <div class="product-details-cart">
                                            <div>
                                                <span class="product-code-cart" id="product-code-display">[SKU]</span>
                                                <span class="product-name-cart" id="product-name-display">Nombre del Producto</span>
                                                <span class="country-flag-cart">🇺🇸</span>
                                            </div>
                                            <div class="product-quantity-cart">
                                                <span id="current-quantity-display">1.00</span> Unidades
                                                 x S/ <span id="unit-price-display">0.00</span> / Unidades
                                            </div>
                                        </div>
                                        <div class="product-price-cart">
                                            S/ <span id="total-price-display">0.00</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Sección de Totales -->
                                <div class="totals-section-cart">
                                    <div class="total-row-cart">
                                        <span>Impuestos</span>
                                        <span>S/ <span id="taxes-display">0.00</span></span>
                                    </div>
                                    <div class="total-row-cart final">
                                        <span>Total</span>
                                        <span>S/ <span id="final-total-display">0.00</span></span>
                                    </div>
                                </div>
                                <!-- Controles de Entrada -->
                                <div class="input-controls-cart">
                                    <!-- Botones de Control -->
                                    <div class="control-buttons-cart">
                                        <button class="control-btn-cart active" id="cant-btn-cart" onclick="setInputModeCart('quantity')">Cant.</button>
                                        <button class="control-btn-cart" id="percent-btn-cart" onclick="setInputModeCart('percent')">%</button>
                                        <button class="control-btn-cart" id="price-btn-cart" onclick="setInputModeCart('price')">Precio</button>
                                    </div>
                                    <!-- Display de entrada actual -->
                                    <div class="current-input-cart" id="current-input-cart"></div>
                                    <!-- Teclado Numérico -->
                                    <div class="number-pad-cart">
                                        <button class="number-btn-cart" onclick="addNumberCart('1')">1</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('2')">2</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('3')">3</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('4')">4</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('5')">5</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('6')">6</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('7')">7</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('8')">8</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('9')">9</button>
                                        <button class="number-btn-cart special" onclick="toggleSignCart()">+/-</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('0')">0</button>
                                        <button class="number-btn-cart" onclick="addNumberCart('.')">.</button>
                                    </div>
                                    <!-- Botón de Borrar -->
                                    <button class="number-btn-cart delete" onclick="deleteLastCharCart()" style="width: 100%; margin-bottom: 12px;">⌫</button>
                                    <!-- Botón Próximo -->
                                    <button class="next-button-cart" onclick="agregarProductoAlCarritoYCerrar()" id="next-button-cart">
                                        <span class="loading-spinner"></span>
                                        <span class="button-text">Pago</span>
                                    </button>
                                </div>
                            </div>
                                                        <div class="carrito-placeholder" id="carrito-placeholder">
                                <i class="fas fa-shopping-cart"></i>
                                <p>Comience a agregar productos</p>
                            </div>
                                                        <div class="carrito-items" id="carrito-items">
                                <!-- Los productos se agregarán aquí dinámicamente -->
                            </div>
                        </div>
                                                <div class="cliente-tabs">
                            <button class="tab active" onclick="abrirModalSeleccionCliente()" id="tab-consumidor">Consumidor Final</button>
                        </div>
                    </div>
                    <!-- Panel derecho - Productos -->
                    <div class="productos-panel">
                        <div class="categorias-tabs">
                            <?php
                            $colores_categoria = [
                                'BAR' => '#d8a7ca',
                                'Barberia' => '#9bb3d8',
                                'Marcas' => '#7dd3c0',
                                'Salón' => '#f5a3a3',
                                'andis' => '#c8a2c8',
                                'ester' => '#e8e8e8',
                                'Salón / Accesorios' => '#f5a3a3',
                                'Herramientas' => '#9bb3d8',
                                'Productos de Belleza' => '#7dd3c0',
                                'Equipos' => '#4caf50',
                                'Consumibles' => '#ff9800',
                                'Mobiliario' => '#795548',
                                'Tecnología' => '#607d8b',
                                'Sin Categoría' => '#9e9e9e'
                            ];
                            foreach ($productos_por_categoria as $categoria => $productos):
                                 $color = $colores_categoria[$categoria] ?? '#607d8b';
                            ?>
                            <button class="categoria-tab" style="background-color: <?php echo $color; ?>" onclick="mostrarCategoria('<?php echo htmlspecialchars($categoria); ?>')">
                                <?php echo htmlspecialchars($categoria); ?> (<?php echo count($productos); ?>)
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="productos-grid">
                            <?php foreach ($productos_por_categoria as $categoria => $productos): ?>
                            <div class="categoria-productos" id="categoria-<?php echo htmlspecialchars($categoria); ?>">
                                <?php foreach ($productos as $producto): ?>
                                <div class="producto-card" onclick="seleccionarProductoParaEdicion(<?php echo $producto['id']; ?>)" data-producto-id="<?php echo $producto['id']; ?>">
                                    <div class="producto-imagen">
                                        <?php if (!empty($producto['imagen']) && file_exists('uploads/productos/' . $producto['imagen'])): ?>
                                            <img src="uploads/productos/<?php echo htmlspecialchars($producto['imagen']); ?>"
                                                 alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="producto-info">
                                        <h4><?php echo htmlspecialchars($producto['nombre_producto']); ?></h4>
                                        <p class="producto-precio">S/<?php echo number_format($producto['precio_venta'], 2); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </div>
    <!-- Modal para crear/editar caja -->
    <div id="modalCaja" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalCajaTitulo"><i class="fas fa-cash-register"></i> Nueva Caja Registradora</h3>
                <button class="modal-close" onclick="cerrarModalCaja()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
                        <div class="modal-body">
                <form id="formCaja">
                    <input type="hidden" id="caja_id" name="caja_id">
                    <input type="hidden" id="caja_action" name="action" value="crear_caja">
                                        <div class="form-row">
                        <div class="form-group">
                            <label for="nombre_caja">Nombre de la Caja *</label>
                            <input type="text" id="nombre_caja" name="nombre_caja" required placeholder="Ej: Caja Principal">
                        </div>
                        <div class="form-group">
                            <label for="responsable_select">Responsable *</label>
                            <select id="responsable_select" name="responsable_id" required onchange="actualizarResponsable()">
                                <option value="">Seleccionar responsable...</option>
                                <?php foreach ($responsables as $responsable): ?>
                                <option value="<?php echo $responsable['id']; ?>"
                                         data-nombre="<?php echo htmlspecialchars($responsable['nombres'] . ' ' . $responsable['apellidos']); ?>">
                                    <?php echo htmlspecialchars($responsable['nombres'] . ' ' . $responsable['apellidos']); ?>
                                     (<?php echo htmlspecialchars($responsable['correo']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="responsable_nombre" name="responsable_nombre">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button type="button" class="btn-secondary" onclick="cerrarModalCaja()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal para crear cliente -->
    <div id="modalCliente" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Nuevo Cliente</h3>
                <button class="modal-close" onclick="cerrarModalCliente()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
                        <div class="modal-body">
                <form id="formCliente">
                    <input type="hidden" name="action" value="crear_cliente">
                                        <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Cliente</label>
                            <select name="tipo" id="tipo_cliente" onchange="toggleTipoCliente()">
                                <option value="persona">Persona</option>
                                <option value="empresa">Empresa</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" required placeholder="Nombre del cliente">
                        </div>
                        <div class="form-group" id="apellidos_group">
                            <label for="apellidos">Apellidos</label>
                            <input type="text" id="apellidos" name="apellidos" placeholder="Apellidos del cliente">
                        </div>
                    </div>
                    <div class="form-row" id="empresa_group" style="display: none;">
                        <div class="form-group">
                            <label for="nombre_empresa">Nombre de la Empresa</label>
                            <input type="text" id="nombre_empresa" name="nombre_empresa" placeholder="Razón social">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" placeholder="999 999 999">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="documento_numero">Documento</label>
                            <input type="text" id="documento_numero" name="documento_numero" placeholder="DNI / RUC">
                        </div>
                        <div class="form-group">
                            <label for="direccion">Dirección</label>
                            <input type="text" id="direccion" name="direccion" placeholder="Dirección completa">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cliente
                        </button>
                        <button type="button" class="btn-secondary" onclick="cerrarModalCliente()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal para seleccionar cliente -->
    <div id="modalSeleccionCliente" class="modal-clientes" style="display: none;">
        <div class="modal-clientes-content">
            <div class="modal-clientes-header">
                <h3><i class="fas fa-users"></i> Seleccionar Cliente</h3>
                <button class="modal-close" onclick="cerrarModalSeleccionCliente()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
                        <div class="modal-clientes-search">
                <div style="position: relative;">
                    <i class="fas fa-search search-icon-clientes"></i>
                    <input type="text" class="search-input-clientes" placeholder="Buscar clientes..." id="search-clientes-modal" onkeyup="buscarClientesModal(this.value)">
                </div>
            </div>
                        <div class="modal-clientes-body">
                <div class="clientes-grid" id="clientes-grid">
                    <!-- Los clientes se cargarán aquí dinámicamente -->
                </div>
            </div>
        </div>
    </div>
    <!-- Modal para selección de método de pago -->
    <div id="modalMetodoPago" class="modal-pago" style="display: none;">
        <div class="modal-pago-content">
            <div class="modal-pago-header">
                <div class="orden-tab-pago">
                    <span id="numero-orden-pago">202</span>
                </div>
            </div>
                        <div class="modal-pago-body">
                <div class="metodos-pago">
                    <div class="metodo-pago-item" onclick="seleccionarMetodoPago('efectivo')" data-metodo="efectivo">
                        <i class="fas fa-money-bill-wave"></i>
                        <span id="efectivo-caja-nombre">Efectivo</span>
                    </div>
                                        <div class="metodo-pago-item" onclick="seleccionarMetodoPago('yape')" data-metodo="yape">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Yape</span>
                    </div>
                                        <div class="metodo-pago-item" onclick="seleccionarMetodoPago('plin')" data-metodo="plin">
                        <i class="fas fa-credit-card"></i>
                        <span>Plin</span>
                    </div>
                                        <div class="metodo-pago-item" onclick="seleccionarMetodoPago('transferencia')" data-metodo="transferencia">
                        <i class="fas fa-university"></i>
                        <span>Transferencia Bancaria</span>
                    </div>
                                        <div class="metodo-pago-item" onclick="seleccionarMetodoPago('tarjeta')" data-metodo="tarjeta">
                        <i class="fas fa-credit-card"></i>
                        <span>Tarjeta</span>
                    </div>
                                        <div class="metodo-pago-item" onclick="seleccionarMetodoPago('cuenta_cliente')" data-metodo="cuenta_cliente">
                        <i class="fas fa-user-circle"></i>
                        <span>Cuenta de cliente</span>
                    </div>
                </div>
                                <!-- Teclado numérico (se muestra después de seleccionar método) -->
                <div class="teclado-pago" id="teclado-pago" style="display: none;">
                    <div class="cliente-factura-tabs">
                        <button class="tab-pago active" id="tab-consumidor-pago">
                            <i class="fas fa-user"></i> Consumidor Final
                        </button>
                        <button class="tab-pago" id="tab-factura-pago">
                            <i class="fas fa-file-invoice"></i> Factura
                            <input type="checkbox" style="margin-left: 8px;">
                        </button>
                    </div>
                                        <div class="display-monto">
                        <span id="monto-display">0</span>
                    </div>
                                        <div class="teclado-numerico">
                        <button class="tecla-num" onclick="agregarNumero('1')">1</button>
                        <button class="tecla-num" onclick="agregarNumero('2')">2</button>
                        <button class="tecla-num" onclick="agregarNumero('3')">3</button>
                        <button class="tecla-especial verde" onclick="agregarMonto(10)">+10</button>
                                                <button class="tecla-num" onclick="agregarNumero('4')">4</button>
                        <button class="tecla-num" onclick="agregarNumero('5')">5</button>
                        <button class="tecla-num" onclick="agregarNumero('6')">6</button>
                        <button class="tecla-especial verde" onclick="agregarMonto(20)">+20</button>
                                                <button class="tecla-num" onclick="agregarNumero('7')">7</button>
                        <button class="tecla-num" onclick="agregarNumero('8')">8</button>
                        <button class="tecla-num" onclick="agregarNumero('9')">9</button>
                        <button class="tecla-especial verde" onclick="agregarMonto(50)">+50</button>
                                                <button class="tecla-especial amarillo" onclick="cambiarSigno()">+/-</button>
                        <button class="tecla-num" onclick="agregarNumero('0')">0</button>
                        <button class="tecla-especial" onclick="agregarNumero('.')">.</button>
                        <button class="tecla-especial rojo" onclick="borrarUltimo()">⌫</button>
                    </div>
                                        <div class="botones-accion-pago">
                        <button class="btn-regresar" onclick="regresarMetodos()">Regresar</button>
                        <button class="btn-validar" onclick="validarPago()">Validar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de pago exitoso -->
    <div id="modalPagoExitoso" class="modal-pago-exitoso" style="display: none;">
        <div class="modal-exitoso-content">
            <div class="pago-exitoso-info">
                <div class="mensaje-exito">
                    <i class="fas fa-check-circle"></i>
                    <h2>Pago exitoso</h2>
                    <h3 id="monto-final-exitoso">S/ 0.00</h3>
                </div>
                                <button class="btn-imprimir-recibo">
                    <i class="fas fa-print"></i> Imprimir recibo completo
                </button>
                                <div class="envio-email">
                    <input type="email" placeholder="e.g. john.doe@mail.com" id="email-recibo">
                    <button class="btn-enviar-email">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
                        <div class="recibo-preview">
                <div class="recibo-header">
                    <div class="logo-recibo">
                        <i class="fas fa-store"></i> Your logo
                    </div>
                    <p>Inventory Solution ERL</p>
                    <p>Atendido por Administrador</p>
                    <h2 id="numero-orden-recibo">202</h2>
                </div>
                                <div class="recibo-items" id="recibo-items-detalle">
                    <!-- Los items se llenarán dinámicamente -->
                </div>
                                <div class="recibo-totales">
                    <div class="linea-separadora"></div>
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span id="subtotal-recibo">S/ 0.00</span>
                    </div>
                    <div class="total-row">
                        <span>IGV</span>
                        <span id="igv-recibo">S/ 0.00</span>
                    </div>
                    <div class="linea-separadora"></div>
                    <div class="total-row final">
                        <span>TOTAL</span>
                        <span id="total-recibo">S/ 0.00</span>
                    </div>
                    <div class="efectivo-row">
                        <span class="efectivo-label">Efectivo</span>
                        <span id="efectivo-recibo">S/ 0.00</span>
                    </div>
                    <div class="efectivo-row">
                        <span class="vuelto-label">Vuelto</span>
                        <span id="vuelto-recibo">S/ 0.00</span>
                    </div>
                </div>
                                <div class="recibo-footer">
                    <p>Con la tecnología de Odoo</p>
                    <p>Orden 00002-025-0002</p>
                    <p id="fecha-recibo">10/07/2025 06:36:29</p>
                </div>
            </div>
                        <button class="btn-nueva-orden" onclick="crearNuevaOrdenDespuesPago()">
                Nueva orden
            </button>
        </div>
    </div>
    <script>
    // ===== VARIABLES GLOBALES =====
    let carritosOrdenes = {};
    let clientesOrdenes = {};
    let dropdownAbierto = null;
    let ordenesActivas = [{ numero: 201, activa: true }];
    let ordenActual = 201;
    let siguienteNumeroOrden = 202;
    // Variables para la interfaz de selección de producto en el carrito
    let currentInputCart = '';
    let inputModeCart = 'quantity';
    let currentQuantityCart = 1.00;
    let unitPriceCart = 0;
    let cajaIdActual = <?php echo $caja_id ?? 'null'; ?>;
    let productoSeleccionadoCart = null;
    // Variables para el sistema de pago
    let metodoPagoSeleccionado = null;
    let montoActualPago = "";
    let totalVentaActual = 0;
    // Información de la caja actual
    let cajaActualInfo = <?php echo $caja_actual ? json_encode($caja_actual) : 'null'; ?>;
    // ===== FUNCIONES PARA GESTIÓN DE ESTADO POR ORDEN =====
    function inicializarOrden(numeroOrden) {
        console.log('🔧 Inicializando orden:', numeroOrden);
                carritosOrdenes[numeroOrden] = [];
        clientesOrdenes[numeroOrden] = null;
                const carritoKey = `carrito_orden_${numeroOrden}`;
        const clienteKey = `cliente_orden_${numeroOrden}`;
                localStorage.setItem(carritoKey, JSON.stringify([]));
        localStorage.setItem(clienteKey, JSON.stringify(null));
                console.log('✅ Orden inicializada completamente vacía:', numeroOrden);
    }
    function cargarEstadoOrden(numeroOrden) {
        console.log('📥 Cargando estado de orden:', numeroOrden);
                const carritoKey = `carrito_orden_${numeroOrden}`;
        const clienteKey = `cliente_orden_${numeroOrden}`;
                const carritoGuardado = JSON.parse(localStorage.getItem(carritoKey) || '[]');
        carritosOrdenes[numeroOrden] = carritoGuardado;
                const clienteGuardado = JSON.parse(localStorage.getItem(clienteKey) || 'null');
        clientesOrdenes[numeroOrden] = clienteGuardado;
                console.log('📦 Estado cargado para orden', numeroOrden, '- Carrito:', carritoGuardado.length, 'items - Cliente:', clienteGuardado ? clienteGuardado.nombre : 'ninguno');
    }
    function guardarEstadoOrden(numeroOrden) {
        console.log('💾 Guardando estado de orden:', numeroOrden);
                const carritoKey = `carrito_orden_${numeroOrden}`;
        const clienteKey = `cliente_orden_${numeroOrden}`;
                const carritoActual = carritosOrdenes[numeroOrden] || [];
        const clienteActual = clientesOrdenes[numeroOrden] || null;
                localStorage.setItem(carritoKey, JSON.stringify(carritoActual));
        localStorage.setItem(clienteKey, JSON.stringify(clienteActual));
                console.log('✅ Estado guardado para orden', numeroOrden, '- Carrito:', carritoActual.length, 'items');
    }
    // Inicializar orden 201 al cargar
    if (!localStorage.getItem('carrito_orden_201')) {
        inicializarOrden(201);
    } else {
        cargarEstadoOrden(201);
    }
    // ===== FUNCIONES PARA LA INTERFAZ DE SELECCIÓN DE PRODUCTO EN EL CARRITO =====
    function seleccionarProductoParaEdicion(productoId) {
        console.log('🛒 Seleccionando producto para edición ID:', productoId, 'en orden:', ordenActual);
                guardarEstadoOrden(ordenActual);
                const formData = new FormData();
        formData.append('action', 'agregar_producto_carrito');
        formData.append('producto_id', productoId);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.producto) {
                mostrarInterfazSeleccionEnCarrito(data.producto);
            } else {
                alert('Error al cargar producto: ' + (data.error || 'Producto no encontrado'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
    }
    function mostrarInterfazSeleccionEnCarrito(producto) {
        productoSeleccionadoCart = producto;
        currentQuantityCart = 1.00;
        unitPriceCart = parseFloat(producto.precio_venta);
                document.getElementById('product-code-display').textContent = `[${producto.sku || 'SKU'}]`;
        document.getElementById('product-name-display').textContent = producto.nombre_producto;
                document.getElementById('carrito-panel').classList.add('selection-mode');
        document.getElementById('product-selection-in-cart').style.display = 'block';
                currentInputCart = '';
        inputModeCart = 'quantity';
        setInputModeCart('quantity');
                updateTotalsCart();
                console.log('✅ Interfaz de selección mostrada para:', producto.nombre_producto, 'en orden:', ordenActual);
    }
    function cerrarInterfazSeleccionEnCarrito() {
        document.getElementById('carrito-panel').classList.remove('selection-mode');
        document.getElementById('product-selection-in-cart').style.display = 'none';
        productoSeleccionadoCart = null;
        currentInputCart = '';
        updateInputDisplayCart();
                actualizarCarrito();
                console.log('❌ Interfaz de selección cerrada');
    }
    function setInputModeCart(mode) {
        inputModeCart = mode;
                document.querySelectorAll('.control-btn-cart').forEach(btn => btn.classList.remove('active'));
                if (mode === 'quantity') {
            document.getElementById('cant-btn-cart').classList.add('active');
        } else if (mode === 'percent') {
            document.getElementById('percent-btn-cart').classList.add('active');
        } else if (mode === 'price') {
            document.getElementById('price-btn-cart').classList.add('active');
        }
                clearInputCart();
    }
    function addNumberCart(num) {
        if (num === '0' && currentInputCart === '') return;
        currentInputCart += num;
        updateInputDisplayCart();
    }
    function toggleSignCart() {
        if (currentInputCart.startsWith('-')) {
            currentInputCart = currentInputCart.substring(1);
        } else if (currentInputCart !== '') {
            currentInputCart = '-' + currentInputCart;
        }
        updateInputDisplayCart();
    }
    function deleteLastCharCart() {
        currentInputCart = currentInputCart.slice(0, -1);
        updateInputDisplayCart();
    }
    function clearInputCart() {
        currentInputCart = '';
        updateInputDisplayCart();
    }
    function updateInputDisplayCart() {
        document.getElementById('current-input-cart').textContent = currentInputCart || '';
                if (currentInputCart && !isNaN(parseFloat(currentInputCart))) {
            applyCurrentInputCart();
        }
    }
    function applyCurrentInputCart() {
        const value = parseFloat(currentInputCart);
        if (isNaN(value)) return;
        switch (inputModeCart) {
            case 'quantity':
                currentQuantityCart = Math.max(0.01, value);
                break;
            case 'percent':
                if (productoSeleccionadoCart) {
                    const precioOriginal = parseFloat(productoSeleccionadoCart.precio_venta);
                    const porcentaje = value / 100;
                    unitPriceCart = precioOriginal * (1 + porcentaje);
                }
                break;
            case 'price':
                unitPriceCart = Math.max(0, value);
                break;
        }
                updateTotalsCart();
    }
    function updateTotalsCart() {
        const subtotal = currentQuantityCart * unitPriceCart;
        const taxes = subtotal * 0.18;
        const total = subtotal + taxes;
        document.getElementById('current-quantity-display').textContent = currentQuantityCart.toFixed(2);
        document.getElementById('unit-price-display').textContent = unitPriceCart.toFixed(2);
        document.getElementById('total-price-display').textContent = subtotal.toFixed(2);
        document.getElementById('taxes-display').textContent = taxes.toFixed(2);
        document.getElementById('final-total-display').textContent = total.toFixed(2);
    }
    function agregarProductoAlCarritoYCerrar() {
        if (!productoSeleccionadoCart) {
            alert('Error: No hay producto seleccionado');
            return;
        }
        const button = document.getElementById('next-button-cart');
        button.disabled = true;
        button.classList.add('loading');
        button.querySelector('.button-text').textContent = 'Agregando...';
        console.log('🛒 Agregando producto personalizado al carrito de orden:', ordenActual, {
            productoId: productoSeleccionadoCart.id,
            cantidad: currentQuantityCart,
            precio: unitPriceCart
        });
        const formData = new FormData();
        formData.append('action', 'agregar_producto_personalizado');
        formData.append('producto_id', productoSeleccionadoCart.id);
        formData.append('cantidad_personalizada', currentQuantityCart);
        formData.append('precio_personalizado', unitPriceCart);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.producto) {
                mostrarAnimacionExito();
                                let carritoActual = carritosOrdenes[ordenActual] || [];
                                const itemExistente = carritoActual.find(item => item.id === data.producto.id);
                                if (itemExistente) {
                    itemExistente.cantidad += data.producto.cantidad_personalizada;
                    if (itemExistente.precio !== data.producto.precio_venta) {
                        itemExistente.precio = data.producto.precio_venta;
                        itemExistente.precio_modificado = true;
                    }
                } else {
                    carritoActual.push({
                        id: data.producto.id,
                        nombre: data.producto.nombre_producto,
                        precio: data.producto.precio_venta,
                        cantidad: data.producto.cantidad_personalizada,
                        imagen: data.producto.imagen,
                        precio_original: data.producto.precio_original,
                        precio_modificado: data.producto.precio_venta !== data.producto.precio_original,
                        sku: data.producto.sku,
                        categoria: data.producto.categoria
                    });
                }
                                carritosOrdenes[ordenActual] = carritoActual;
                guardarEstadoOrden(ordenActual);
                                console.log('✅ Producto agregado exitosamente al carrito de orden', ordenActual, '- Total items:', carritoActual.length);
                                setTimeout(() => {
                    cerrarInterfazSeleccionEnCarrito();
                    resetearBotonCart();
                }, 1500);
                            } else {
                alert('Error al agregar producto: ' + (data.error || 'Error desconocido'));
                resetearBotonCart();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al agregar producto');
            resetearBotonCart();
        });
    }
    function mostrarAnimacionExito() {
        const animacion = document.createElement('div');
        animacion.className = 'success-animation';
        animacion.innerHTML = '<i class="fas fa-check-circle"></i> ¡Producto agregado!';
        document.body.appendChild(animacion);
                setTimeout(() => {
            if (document.body.contains(animacion)) {
                document.body.removeChild(animacion);
            }
        }, 2200);
    }
    function resetearBotonCart() {
        const button = document.getElementById('next-button-cart');
        button.disabled = false;
        button.classList.remove('loading');
        button.querySelector('.button-text').textContent = 'Pago';
    }
    // ===== FUNCIONES PARA CAJAS REGISTRADORAS =====
    function actualizarResponsable() {
        const select = document.getElementById('responsable_select');
        const hiddenInput = document.getElementById('responsable_nombre');
        const selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.dataset.nombre) {
            hiddenInput.value = selectedOption.dataset.nombre;
        } else {
            hiddenInput.value = '';
        }
    }
    function crearCaja() {
        document.getElementById('modalCajaTitulo').innerHTML = '<i class="fas fa-cash-register"></i> Nueva Caja Registradora';
        document.getElementById('caja_action').value = 'crear_caja';
        document.getElementById('caja_id').value = '';
        document.getElementById('nombre_caja').value = '';
        document.getElementById('responsable_select').value = '';
        document.getElementById('responsable_nombre').value = '';
        document.getElementById('modalCaja').style.display = 'flex';
    }
    function editarCaja(cajaId) {
        console.log('🔧 Editando caja ID:', cajaId);
                const formData = new FormData();
        formData.append('action', 'obtener_caja');
        formData.append('caja_id', cajaId);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.caja) {
                document.getElementById('modalCajaTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar Caja Registradora';
                document.getElementById('caja_action').value = 'editar_caja';
                document.getElementById('caja_id').value = data.caja.id;
                document.getElementById('nombre_caja').value = data.caja.nombre_caja;
                                if (data.caja.responsable_id) {
                    document.getElementById('responsable_select').value = data.caja.responsable_id;
                    document.getElementById('responsable_nombre').value = data.caja.responsable;
                }
                                document.getElementById('modalCaja').style.display = 'flex';
            } else {
                alert('Error al cargar los datos de la caja');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
                cerrarDropdowns();
    }
    function eliminarCaja(cajaId) {
        if (!confirm('¿Está seguro de que desea eliminar esta caja registradora?')) {
            return;
        }
                console.log('🗑️ Eliminando caja ID:', cajaId);
                const formData = new FormData();
        formData.append('action', 'eliminar_caja');
        formData.append('caja_id', cajaId);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('📡 Respuesta del servidor:', data);
            if (data.success) {
                alert('Caja eliminada exitosamente');
                location.reload();
            } else {
                alert('Error al eliminar la caja: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
                cerrarDropdowns();
    }
    function abrirCaja(cajaId) {
        if (!confirm('¿Desea abrir la caja registradora?')) {
            return;
        }
                console.log('🔓 Abriendo caja ID:', cajaId);
                const formData = new FormData();
        formData.append('action', 'abrir_caja');
        formData.append('caja_id', cajaId);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Caja abierta exitosamente');
                window.location.href = `?vista=venta&caja_id=${cajaId}`;
            } else {
                alert('Error al abrir la caja: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
    }
    function cerrarCaja(cajaId) {
        if (!confirm('¿Desea cerrar la caja registradora? Esto finalizará todas las ventas activas.')) {
            return;
        }
                console.log('🔒 Cerrando caja ID:', cajaId);
                const formData = new FormData();
        formData.append('action', 'cerrar_caja');
        formData.append('caja_id', cajaId);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Caja cerrada exitosamente');
                window.location.href = '?vista=cajas';
            } else {
                alert('Error al cerrar la caja: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
    }
    function cerrarCajaYVolver() {
        const cajaId = <?php echo $caja_id ?? 'null'; ?>;
        if (cajaId) {
            cerrarCaja(cajaId);
        } else {
            window.location.href = '?vista=cajas';
        }
    }
    // ===== FUNCIONES PARA ÓRDENES MÚLTIPLES =====
    function crearNuevaOrden() {
        const cajaId = <?php echo $caja_id ?? 'null'; ?>;
                console.log('🆕 Creando nueva orden...');
                guardarEstadoOrden(ordenActual);
                const formData = new FormData();
        formData.append('action', 'crear_nueva_orden');
        formData.append('caja_id', cajaId);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ordenesActivas.push({
                    numero: data.numero_orden,
                    activa: false
                });
                                inicializarOrden(data.numero_orden);
                                actualizarTabsOrdenes();
                cambiarOrden(data.numero_orden);
                                console.log('✅ Nueva orden creada:', data.numero_orden, 'con estado completamente vacío');
            } else {
                alert('Error al crear nueva orden: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
    }
    function actualizarTabsOrdenes() {
        const container = document.getElementById('ordenes-tabs');
        if (!container) return;
                let html = '';
                ordenesActivas.forEach(orden => {
            const activeClass = orden.numero === ordenActual ? 'active' : '';
            html += `
                <div class="orden-tab ${activeClass}" data-orden="${orden.numero}" onclick="cambiarOrden(${orden.numero})">
                    ${orden.numero}
                    <button class="close-btn" onclick="cerrarOrden(${orden.numero})" title="Cerrar orden">×</button>
                </div>
            `;
        });
                html += `
            <button class="nueva-orden-btn" onclick="crearNuevaOrden()" title="Nueva orden">
                <i class="fas fa-plus"></i>
            </button>
        `;
                container.innerHTML = html;
    }
    function cambiarOrden(numeroOrden) {
        console.log('🔄 Cambiando de orden', ordenActual, 'a orden:', numeroOrden);
                cerrarInterfazSeleccionEnCarrito();
                if (ordenActual !== numeroOrden) {
            console.log('💾 Guardando estado de orden actual:', ordenActual);
            guardarEstadoOrden(ordenActual);
        }
                const ordenAnterior = ordenActual;
        ordenActual = numeroOrden;
                if (!carritosOrdenes.hasOwnProperty(numeroOrden) || !clientesOrdenes.hasOwnProperty(numeroOrden)) {
            console.log('⚠️ Orden no existe en memoria, inicializando...');
            inicializarOrden(numeroOrden);
        }
                console.log('📥 Cargando estado de nueva orden:', numeroOrden);
        cargarEstadoOrden(numeroOrden);
                document.getElementById('numero-orden-actual').textContent = numeroOrden;
                document.querySelectorAll('.orden-tab').forEach(tab => {
            tab.classList.remove('active');
        });
                const tabActivo = document.querySelector(`[data-orden="${numeroOrden}"]`);
        if (tabActivo) {
            tabActivo.classList.add('active');
        }
                actualizarCarrito();
        mostrarClienteSeleccionado();
                const clienteActual = obtenerClienteActual();
        const tabConsumidor = document.getElementById('tab-consumidor');
        if (tabConsumidor) {
            if (clienteActual) {
                const nombreCorto = clienteActual.nombre.length > 15 ?
                     clienteActual.nombre.substring(0, 15) + '...' :
                     clienteActual.nombre;
                tabConsumidor.textContent = nombreCorto;
            } else {
                tabConsumidor.textContent = 'Consumidor Final';
            }
        }
                console.log('✅ Orden cambiada exitosamente a', numeroOrden, '- Carrito:', obtenerCarritoActual().length, 'items - Cliente:', obtenerClienteActual() ? obtenerClienteActual().nombre : 'ninguno');
    }
    function cerrarOrden(numeroOrden) {
        event.stopPropagation();
                if (ordenesActivas.length <= 1) {
            alert('No se puede cerrar la última orden activa');
            return;
        }
                if (!confirm(`¿Cerrar la orden ${numeroOrden}?`)) {
            return;
        }
                console.log('❌ Cerrando orden:', numeroOrden);
                if (numeroOrden === ordenActual) {
            cerrarInterfazSeleccionEnCarrito();
        }
                ordenesActivas = ordenesActivas.filter(orden => orden.numero !== numeroOrden);
                delete carritosOrdenes[numeroOrden];
        delete clientesOrdenes[numeroOrden];
                localStorage.removeItem(`carrito_orden_${numeroOrden}`);
        localStorage.removeItem(`cliente_orden_${numeroOrden}`);
                if (numeroOrden === ordenActual && ordenesActivas.length > 0) {
            cambiarOrden(ordenesActivas[0].numero);
        }
                actualizarTabsOrdenes();
                console.log('✅ Orden cerrada y datos limpiados:', numeroOrden);
    }
    function toggleDropdown(cajaId) {
        const dropdown = document.getElementById(`dropdown-${cajaId}`);
                cerrarDropdowns();
                if (dropdown) {
            dropdown.classList.toggle('show');
            dropdownAbierto = dropdown.classList.contains('show') ? cajaId : null;
        }
    }
    function cerrarDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
        dropdownAbierto = null;
    }
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.caja-menu')) {
            cerrarDropdowns();
        }
    });
    function cerrarModalCaja() {
        document.getElementById('modalCaja').style.display = 'none';
    }
    document.getElementById('formCaja').addEventListener('submit', function(e) {
        e.preventDefault();
                const formData = new FormData(this);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                cerrarModalCaja();
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
    });
    // ===== FUNCIONES PARA PUNTO DE VENTA =====
    function mostrarCategoria(categoria) {
        console.log('📂 Mostrando categoría:', categoria);
                document.querySelectorAll('.categoria-productos').forEach(cat => {
            cat.classList.remove('active');
        });
                const categoriaElement = document.getElementById(`categoria-${categoria}`);
        if (categoriaElement) {
            categoriaElement.classList.add('active');
        }
                document.querySelectorAll('.categoria-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');
    }
    function obtenerCarritoActual() {
        if (!carritosOrdenes[ordenActual]) {
            carritosOrdenes[ordenActual] = [];
        }
        return carritosOrdenes[ordenActual];
    }
    function obtenerClienteActual() {
        return clientesOrdenes[ordenActual] || null;
    }
    function actualizarCarrito() {
        const carrito = obtenerCarritoActual();
        const placeholder = document.getElementById('carrito-placeholder');
        const items = document.getElementById('carrito-items');
                if (carrito.length === 0) {
            placeholder.style.display = 'flex';
            items.style.display = 'none';
            return;
        }
                placeholder.style.display = 'none';
        items.style.display = 'block';
                let html = '';
        let total = 0;
                carrito.forEach((item, index) => {
            const subtotal = item.precio * item.cantidad;
            total += subtotal;
                        const precioModificado = item.precio_modificado ? ' <span style="color: #f59e0b; font-size: 10px;">●</span>' : '';
                        html += `
                <div class="carrito-item">
                    <div class="item-info">
                        <h4>${item.nombre}${precioModificado}</h4>
                        <p>S/${item.precio.toFixed(2)} c/u</p>
                    </div>
                    <div class="item-controls">
                        <button class="qty-btn" onclick="cambiarCantidad(${index}, -1)">-</button>
                        <input type="number" class="qty-input" value="${item.cantidad}" onchange="actualizarCantidad(${index}, this.value)" min="1">
                        <button class="qty-btn" onclick="cambiarCantidad(${index}, 1)">+</button>
                        <button class="qty-btn" onclick="eliminarDelCarrito(${index})" style="color: #ef4444; margin-left: 8px;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
                html += `
            <div class="carrito-total" style="padding: 16px; border-top: 2px solid #e5e7eb; margin-top: 16px; background: #f8fafc; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <strong style="font-size: 18px; color: #1f2937;">Total: S/${total.toFixed(2)}</strong>
                </div>
                <button class="btn-primary" onclick="procesarVenta()" style="width: 100%; margin-bottom: 8px;">
                    <i class="fas fa-credit-card"></i> Procesar Venta
                </button>
                <button class="btn-secondary" onclick="limpiarCarrito()" style="width: 100%;">
                    <i class="fas fa-trash"></i> Limpiar Carrito
                </button>
            </div>
        `;
                items.innerHTML = html;
    }
    function cambiarCantidad(index, cambio) {
        const carrito = obtenerCarritoActual();
        if (carrito[index]) {
            carrito[index].cantidad += cambio;
            if (carrito[index].cantidad <= 0) {
                carrito.splice(index, 1);
            }
            carritosOrdenes[ordenActual] = carrito;
            guardarEstadoOrden(ordenActual);
            actualizarCarrito();
        }
    }
    function actualizarCantidad(index, nuevaCantidad) {
        const cantidad = parseInt(nuevaCantidad);
        const carrito = obtenerCarritoActual();
        if (cantidad > 0 && carrito[index]) {
            carrito[index].cantidad = cantidad;
            carritosOrdenes[ordenActual] = carrito;
            guardarEstadoOrden(ordenActual);
            actualizarCarrito();
        }
    }
    function eliminarDelCarrito(index) {
        if (confirm('¿Eliminar este producto del carrito?')) {
            const carrito = obtenerCarritoActual();
            carrito.splice(index, 1);
            carritosOrdenes[ordenActual] = carrito;
            guardarEstadoOrden(ordenActual);
            actualizarCarrito();
        }
    }
    function limpiarCarrito() {
        if (confirm('¿Limpiar todo el carrito?')) {
            carritosOrdenes[ordenActual] = [];
            guardarEstadoOrden(ordenActual);
            actualizarCarrito();
        }
    }
    // ===== FUNCIÓN PRINCIPAL PARA PROCESAR VENTA (CON SISTEMA DE PAGO) =====
    function procesarVenta() {
        const carrito = obtenerCarritoActual();
        if (carrito.length === 0) {
            alert('El carrito está vacío');
            return;
        }
        totalVentaActual = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        document.getElementById('numero-orden-pago').textContent = ordenActual;
        document.getElementById('modalMetodoPago').style.display = 'flex';
        metodoPagoSeleccionado = null;
        montoActualPago = totalVentaActual.toFixed(2);
        document.getElementById('monto-display').textContent = montoActualPago;
        document.getElementById('teclado-pago').style.display = 'none';
        document.querySelectorAll('.metodo-pago-item').forEach(item => {
            item.classList.remove('selected');
        });
    }
    // ===== FUNCIONES DEL SISTEMA DE PAGO =====
    function seleccionarMetodoPago(metodo) {
        metodoPagoSeleccionado = metodo;
        document.querySelectorAll('.metodo-pago-item').forEach(item => {
            item.classList.remove('selected');
        });
        document.querySelector(`[data-metodo="${metodo}"]`).classList.add('selected');
        setTimeout(() => {
            document.getElementById('teclado-pago').style.display = 'block';
            document.getElementById('monto-display').textContent = totalVentaActual.toFixed(2);
            montoActualPago = totalVentaActual.toFixed(2);
        }, 300);
    }
    function agregarNumero(num) {
        if (montoActualPago === '0' || montoActualPago === totalVentaActual.toFixed(2)) {
            montoActualPago = num;
        } else {
            montoActualPago += num;
        }
        actualizarDisplayMonto();
    }
    function agregarMonto(cantidad) {
        const montoActual = parseFloat(montoActualPago) || 0;
        montoActualPago = (montoActual + cantidad).toFixed(2);
        actualizarDisplayMonto();
    }
    function cambiarSigno() {
        if (montoActualPago.startsWith('-')) {
            montoActualPago = montoActualPago.substring(1);
        } else if (montoActualPago !== '0') {
            montoActualPago = '-' + montoActualPago;
        }
        actualizarDisplayMonto();
    }
    function borrarUltimo() {
        if (montoActualPago.length > 1) {
            montoActualPago = montoActualPago.slice(0, -1);
        } else {
            montoActualPago = '0';
        }
        actualizarDisplayMonto();
    }
    function actualizarDisplayMonto() {
        document.getElementById('monto-display').textContent = montoActualPago;
    }
    function regresarMetodos() {
        document.getElementById('teclado-pago').style.display = 'none';
        metodoPagoSeleccionado = null;
        document.querySelectorAll('.metodo-pago-item').forEach(item => {
            item.classList.remove('selected');
        });
    }
    function validarPago() {
        if (!metodoPagoSeleccionado) {
            alert('Seleccione un método de pago');
            return;
        }
        const montoPagado = parseFloat(montoActualPago) || 0;
        if (montoPagado < totalVentaActual) {
            if (!confirm(`El monto ingresado (S/ ${montoPagado.toFixed(2)}) es menor al total (S/ ${totalVentaActual.toFixed(2)}). ¿Continuar?`)) {
                return;
            }
        }
        // Procesar venta en la base de datos
        procesarVentaEnBaseDatos(montoPagado);
    }
    function procesarVentaEnBaseDatos(montoPagado) {
        const carrito = obtenerCarritoActual();
        const cliente = obtenerClienteActual();
                const subtotal = totalVentaActual / 1.18;
        const igv = totalVentaActual - subtotal;
        const vuelto = Math.max(0, montoPagado - totalVentaActual);
        const formData = new FormData();
        formData.append('action', 'procesar_venta_completa');
        formData.append('numero_orden', ordenActual);
        formData.append('caja_id', cajaIdActual);
        formData.append('cliente_id', cliente ? cliente.id : null);
        formData.append('cliente_nombre', cliente ? cliente.nombre : null);
        formData.append('subtotal', subtotal.toFixed(2));
        formData.append('igv', igv.toFixed(2));
        formData.append('total', totalVentaActual.toFixed(2));
        formData.append('metodo_pago', metodoPagoSeleccionado);
        formData.append('monto_pagado', montoPagado.toFixed(2));
        formData.append('vuelto', vuelto.toFixed(2));
        formData.append('productos', JSON.stringify(carrito));
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Venta procesada exitosamente en la base de datos');
                document.getElementById('modalMetodoPago').style.display = 'none';
                mostrarPagoExitoso(montoPagado);
            } else {
                alert('Error al procesar la venta: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al procesar la venta');
        });
    }
    function mostrarPagoExitoso(montoPagado) {
        const carrito = obtenerCarritoActual();
        const cliente = obtenerClienteActual();
        const nombreCaja = cajaActualInfo ? cajaActualInfo.nombre_caja : 'Efectivo';
        const subtotal = totalVentaActual / 1.18;
        const igv = totalVentaActual - subtotal;
        const vuelto = Math.max(0, montoPagado - totalVentaActual);
        document.getElementById('monto-final-exitoso').textContent = `S/ ${totalVentaActual.toFixed(2)}`;
        document.getElementById('numero-orden-recibo').textContent = ordenActual;
        let itemsHtml = '';
        carrito.forEach(item => {
            const subtotalItem = item.precio * item.cantidad;
            itemsHtml += `
                <div class="recibo-item">
                    <div class="item-detalle">
                        <h4>${item.nombre}</h4>
                        <p>${item.cantidad.toFixed(2)} Unidades x S/ ${item.precio.toFixed(2)} / Unidades</p>
                    </div>
                    <div class="item-precio">
                        S/ ${subtotalItem.toFixed(2)}
                    </div>
                </div>
            `;
        });
        document.getElementById('recibo-items-detalle').innerHTML = itemsHtml;
        document.getElementById('subtotal-recibo').textContent = `S/ ${subtotal.toFixed(2)}`;
        document.getElementById('igv-recibo').textContent = `S/ ${igv.toFixed(2)}`;
        document.getElementById('total-recibo').textContent = `S/ ${totalVentaActual.toFixed(2)}`;
        document.getElementById('efectivo-recibo').textContent = `S/ ${montoPagado.toFixed(2)}`;
        document.getElementById('vuelto-recibo').textContent = `S/ ${vuelto.toFixed(2)}`;
        const efectivoRows = document.querySelectorAll('.efectivo-row .efectivo-label, .efectivo-row .vuelto-label');
        efectivoRows.forEach(span => {
            if (span.textContent.includes('Efectivo')) {
                span.textContent = nombreCaja;
            }
        });
        const ahora = new Date();
        const fechaFormateada = ahora.toLocaleDateString('es-PE') + ' ' + ahora.toLocaleTimeString('es-PE');
        document.getElementById('fecha-recibo').textContent = fechaFormateada;
        document.getElementById('modalPagoExitoso').style.display = 'flex';
    }
    function crearNuevaOrdenDespuesPago() {
        carritosOrdenes[ordenActual] = [];
        clientesOrdenes[ordenActual] = null;
        guardarEstadoOrden(ordenActual);
        document.getElementById('modalPagoExitoso').style.display = 'none';
        actualizarCarrito();
        mostrarClienteSeleccionado();
        document.getElementById('tab-consumidor').textContent = 'Consumidor Final';
        setTimeout(() => {
            crearNuevaOrden();
        }, 500);
    }
    // ===== FUNCIONES PARA CLIENTES =====
    function abrirModalSeleccionCliente() {
        document.getElementById('modalSeleccionCliente').style.display = 'flex';
        cargarTodosLosClientes();
    }
    function cerrarModalSeleccionCliente() {
        document.getElementById('modalSeleccionCliente').style.display = 'none';
    }
    function cargarTodosLosClientes() {
        const formData = new FormData();
        formData.append('action', 'buscar_clientes');
        formData.append('search', '');
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarClientesEnModal(data.clientes);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    function buscarClientesModal(termino) {
        const formData = new FormData();
        formData.append('action', 'buscar_clientes');
        formData.append('search', termino);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarClientesEnModal(data.clientes);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    function mostrarClientesEnModal(clientes) {
        const grid = document.getElementById('clientes-grid');
                if (clientes.length === 0) {
            grid.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 40px;">No se encontraron clientes</p>';
            return;
        }
                let html = '';
        clientes.forEach(cliente => {
            const nombreCompleto = cliente.tipo === 'empresa' ? cliente.nombre_empresa : `${cliente.nombre} ${cliente.apellidos || ''}`.trim();
            const info = cliente.email || cliente.telefono || cliente.documento_numero || '';
                        html += `
                <div class="cliente-card" onclick="seleccionarClienteDesdeModal(${cliente.id}, '${nombreCompleto.replace(/'/g, "\\'")}', '${info.replace(/'/g, "\\'")}')">
                    <h4>${nombreCompleto}</h4>
                    ${info ? `<p>${info}</p>` : '<p>Sin información adicional</p>'}
                </div>
            `;
        });
                grid.innerHTML = html;
    }
    function seleccionarClienteDesdeModal(clienteId, nombreCliente, infoCliente) {
        const clienteData = { id: clienteId, nombre: nombreCliente, info: infoCliente };
        clientesOrdenes[ordenActual] = clienteData;
                guardarEstadoOrden(ordenActual);
                mostrarClienteSeleccionado();
                const tabConsumidor = document.getElementById('tab-consumidor');
        tabConsumidor.textContent = nombreCliente.length > 15 ? nombreCliente.substring(0, 15) + '...' : nombreCliente;
                cerrarModalSeleccionCliente();
                console.log('👤 Cliente seleccionado para orden', ordenActual, ':', clienteData);
    }
    function mostrarClienteSeleccionado() {
        const clienteSeleccionado = obtenerClienteActual();
        const clienteDiv = document.getElementById('cliente-seleccionado');
        const nombreDiv = document.getElementById('cliente-nombre-seleccionado');
        const infoDiv = document.getElementById('cliente-info-seleccionado');
                if (clienteSeleccionado) {
            nombreDiv.textContent = clienteSeleccionado.nombre;
            infoDiv.textContent = clienteSeleccionado.info || 'Sin información adicional';
            clienteDiv.classList.add('show');
        } else {
            clienteDiv.classList.remove('show');
        }
    }
    function cambiarCliente() {
        clientesOrdenes[ordenActual] = null;
        guardarEstadoOrden(ordenActual);
                document.getElementById('tab-consumidor').textContent = 'Consumidor Final';
        mostrarClienteSeleccionado();
        abrirModalSeleccionCliente();
    }
    function mostrarModalCliente() {
        document.getElementById('modalCliente').style.display = 'flex';
    }
    function cerrarModalCliente() {
        document.getElementById('modalCliente').style.display = 'none';
    }
    function toggleTipoCliente() {
        const tipo = document.getElementById('tipo_cliente').value;
        const empresaGroup = document.getElementById('empresa_group');
        const apellidosGroup = document.getElementById('apellidos_group');
                if (tipo === 'empresa') {
            empresaGroup.style.display = 'block';
            apellidosGroup.style.display = 'none';
        } else {
            empresaGroup.style.display = 'none';
            apellidosGroup.style.display = 'block';
        }
    }
    document.getElementById('formCliente').addEventListener('submit', function(e) {
        e.preventDefault();
                const formData = new FormData(this);
                fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cliente creado exitosamente');
                cerrarModalCliente();
                                if (data.cliente) {
                    const nombreCompleto = data.cliente.tipo === 'empresa' ?
                         data.cliente.nombre_empresa :
                         `${data.cliente.nombre} ${data.cliente.apellidos || ''}`.trim();
                    const info = data.cliente.email || data.cliente.telefono || data.cliente.documento_numero || '';
                                        seleccionarClienteDesdeModal(data.cliente.id, nombreCompleto, info);
                }
                                this.reset();
            } else {
                alert('Error: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al crear cliente');
        });
    });
    // ===== INICIALIZACIÓN =====
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Sistema de punto de venta cargado');
                // Actualizar nombre del método de pago efectivo
        if (cajaActualInfo && cajaActualInfo.nombre_caja) {
            const efectivoSpan = document.getElementById('efectivo-caja-nombre');
            if (efectivoSpan) {
                efectivoSpan.textContent = `Efectivo ${cajaActualInfo.nombre_caja}`;
            }
        }
                <?php if ($vista == 'venta'): ?>
        const totalProductos = <?php echo array_sum(array_map('count', $productos_por_categoria)); ?>;
        console.log('📦 Total de productos cargados:', totalProductos);
                if (totalProductos === 0) {
            console.warn('⚠️ No se encontraron productos en la base de datos');
            alert('No se encontraron productos. Verifique la conexión a la base de datos.');
        }
                cargarEstadoOrden(ordenActual);
        actualizarCarrito();
        mostrarClienteSeleccionado();
                <?php if (!empty($productos_por_categoria)): ?>
        const categorias = <?php echo json_encode(array_keys($productos_por_categoria)); ?>;
        console.log('📂 Categorías disponibles:', categorias);
                if (categorias.length > 0) {
            setTimeout(() => {
                mostrarCategoria(categorias[0]);
            }, 100);
        }
        <?php endif; ?>
                actualizarTabsOrdenes();
        <?php endif; ?>
                document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalCaja();
                cerrarModalCliente();
                cerrarModalSeleccionCliente();
                cerrarDropdowns();
                cerrarInterfazSeleccionEnCarrito();
                document.getElementById('modalMetodoPago').style.display = 'none';
                document.getElementById('modalPagoExitoso').style.display = 'none';
            }
        });
                window.onclick = function(event) {
            const modalCaja = document.getElementById('modalCaja');
            const modalCliente = document.getElementById('modalCliente');
            const modalSeleccion = document.getElementById('modalSeleccionCliente');
            const modalPago = document.getElementById('modalMetodoPago');
            const modalExitoso = document.getElementById('modalPagoExitoso');
                        if (event.target == modalCaja) {
                cerrarModalCaja();
            }
            if (event.target ==modalCliente) {
                cerrarModalCliente();
            }
            if (event.target == modalSeleccion) {
                cerrarModalSeleccionCliente();
            }
            if (event.target == modalPago) {
                modalPago.style.display = 'none';
            }
            if (event.target == modalExitoso) {
                modalExitoso.style.display = 'none';
            }
        }
    });
    </script></body></html>
