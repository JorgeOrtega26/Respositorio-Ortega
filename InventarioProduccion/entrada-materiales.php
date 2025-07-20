<?php
session_start();

function conectarDB(){
    $host = 'localhost';
    $dbname = 'sistema-erp-eless';
    $username = 'root';
    $password = '';

    $conexion = new mysqli($host, $username, $password, $dbname);
    if ($conexion->connect_error){
        die("error de conexion: " . $conexion->connect_error);
    }
    return $conexion;
}

$conexion = conectarDB();
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_POST) {
    try {
        $material_id = $_POST['material_id'];
        $cantidad = floatval($_POST['cantidad']);
        $precio_unitario = floatval($_POST['precio_unitario']);
        $motivo = $_POST['motivo'];
        $proveedor_id = !empty($_POST['proveedor_id']) ? $_POST['proveedor_id'] : null;
        $observaciones = $_POST['observaciones'];
        $documento_referencia = $_POST['documento_referencia'];
        $usuario = $_SESSION['nombres'] ?? 'Sistema';

        // Iniciar transacción
        $conexion->begin_transaction();

        // Insertar movimiento
        $stmt = $conexion->prepare("
            INSERT INTO movimientos_materiales 
            (material_id, tipo_movimiento, cantidad, precio_unitario, motivo, proveedor_id, usuario, observaciones, documento_referencia) 
            VALUES (?, 'entrada', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iddissss", $material_id, $cantidad, $precio_unitario, $motivo, $proveedor_id, $usuario, $observaciones, $documento_referencia);
        $stmt->execute();

        // Actualizar stock del material
        $stmt2 = $conexion->prepare("
            UPDATE materiales 
            SET stock_actual = stock_actual + ?, 
                precio_unitario = CASE WHEN ? > 0 THEN ? ELSE precio_unitario END,
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt2->bind_param("dddi", $cantidad, $precio_unitario, $precio_unitario, $material_id);
        $stmt2->execute();

        $conexion->commit();
        $mensaje = "Entrada de material registrada correctamente";
        $tipo_mensaje = "success";

    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "Error al registrar la entrada: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener materiales
$materiales = $conexion->query("SELECT * FROM materiales WHERE activo = 1 ORDER BY nombre");

// Obtener proveedores
$proveedores = $conexion->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de Materiales - ERP ELESS</title>
    <link rel="stylesheet" href="../css/siderbarycabezal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content {
            padding: 20px;
            background: #f8f9fa;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .btn {
            background: #0b9085;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #0a7a70;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .material-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        .material-info.show {
            display: block;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="main">
        <?php include '../header.php'; ?>
        
        <div class="content">
            <h1><i class="fas fa-plus-circle"></i> Entrada de Materiales</h1>
            
            <?php if ($mensaje): ?>
            <div class="alert <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" id="entradaForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="material_id">Material *</label>
                            <select name="material_id" id="material_id" required onchange="mostrarInfoMaterial()">
                                <option value="">Seleccionar material...</option>
                                <?php while ($material = $materiales->fetch_assoc()): ?>
                                <option value="<?php echo $material['id']; ?>" 
                                        data-stock="<?php echo $material['stock_actual']; ?>"
                                        data-unidad="<?php echo $material['unidad_medida']; ?>"
                                        data-precio="<?php echo $material['precio_unitario']; ?>">
                                    <?php echo htmlspecialchars($material['nombre']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="materialInfo" class="material-info">
                                <strong>Stock actual:</strong> <span id="stockActual">-</span> <span id="unidadMedida">-</span><br>
                                <strong>Precio unitario actual:</strong> $<span id="precioActual">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="proveedor_id">Proveedor</label>
                            <select name="proveedor_id" id="proveedor_id">
                                <option value="">Sin proveedor</option>
                                <?php 
                                $proveedores->data_seek(0); // Reset pointer
                                while ($proveedor = $proveedores->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $proveedor['id']; ?>">
                                    <?php echo htmlspecialchars($proveedor['nombre']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cantidad">Cantidad *</label>
                            <input type="number" name="cantidad" id="cantidad" step="0.01" min="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="precio_unitario">Precio Unitario</label>
                            <input type="number" name="precio_unitario" id="precio_unitario" step="0.01" min="0">
                            <small style="color: #666;">Dejar en blanco para mantener el precio actual</small>
                        </div>

                        <div class="form-group">
                            <label for="motivo">Motivo *</label>
                            <select name="motivo" id="motivo" required>
                                <option value="">Seleccionar motivo...</option>
                                <option value="Compra">Compra</option>
                                <option value="Devolución">Devolución</option>
                                <option value="Ajuste de inventario">Ajuste de inventario</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Producción interna">Producción interna</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="documento_referencia">Documento de Referencia</label>
                            <input type="text" name="documento_referencia" id="documento_referencia" placeholder="Ej: Factura #123, OC-456">
                        </div>

                        <div class="form-group full-width">
                            <label for="observaciones">Observaciones</label>
                            <textarea name="observaciones" id="observaciones" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="InventarioDeProduccion.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Registrar Entrada
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function mostrarInfoMaterial() {
            const select = document.getElementById('material_id');
            const option = select.options[select.selectedIndex];
            const info = document.getElementById('materialInfo');
            
            if (option.value) {
                document.getElementById('stockActual').textContent = parseFloat(option.dataset.stock).toFixed(2);
                document.getElementById('unidadMedida').textContent = option.dataset.unidad;
                document.getElementById('precioActual').textContent = parseFloat(option.dataset.precio).toFixed(2);
                document.getElementById('precio_unitario').value = option.dataset.precio;
                info.classList.add('show');
            } else {
                info.classList.remove('show');
            }
        }

        // Validación del formulario
        document.getElementById('entradaForm').addEventListener('submit', function(e) {
            const cantidad = parseFloat(document.getElementById('cantidad').value);
            
            if (cantidad <= 0) {
                e.preventDefault();
                alert('La cantidad debe ser mayor a 0');
                return false;
            }
        });
    </script>
</body>
</html>
