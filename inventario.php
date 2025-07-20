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

// Determinar qué vista mostrar al inicio del script
$vista = $_GET['vista'] ?? 'lista';

// FUNCIÓN PARA GENERAR SKU ÚNICO (para inventario interno)
function generateUniqueSKU($pdo, $nombre_producto) {
    $base_sku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombre_producto), 0, 6));
    if (strlen($base_sku) < 3) {
        $base_sku = 'PROD';
    }
    $counter = 1;
    do {
        $sku = $base_sku . str_pad($counter, 3, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE sku = ?");
        $stmt->execute([$sku]);
        $exists = $stmt->fetchColumn() > 0;
        $counter++;
    } while ($exists && $counter < 1000);
    return $sku;
}

// PROCESAR AJAX PARA OBTENER SKUs POR IMPORTADOR (ORDENADO POR PRECIO)
if (isset($_POST['action']) && $_POST['action'] == 'get_skus_by_importador') {
    try {
        $importador_id = $_POST['importador_id'] ?? 0;

        if ($importador_id > 0) {
            $stmt = $pdo->prepare("
                SELECT id, sku_codigo, nombre_producto, descripcion, precio_importador, categoria, estado 
                FROM sku_importadores 
                WHERE importador_id = ? AND estado = 'disponible'
                ORDER BY precio_importador ASC
            ");
            $stmt->execute([$importador_id]);
            $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'skus' => $skus]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'skus' => []]);
        }
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA OBTENER DATOS DE SKU ESPECÍFICO (para inventario interno)
if (isset($_POST['action']) && $_POST['action'] == 'get_sku_data') {
    try {
        $sku_id = $_POST['sku_id'] ?? 0;

        if ($sku_id > 0) {
            $stmt = $pdo->prepare("
                SELECT si.*, i.nombre_importador 
                FROM sku_importadores si
                JOIN importadores i ON si.importador_id = i.id
                WHERE si.id = ?
            ");
            $stmt->execute([$sku_id]);
            $sku_data = $stmt->fetch(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'sku_data' => $sku_data]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'SKU no válido']);
        }
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Procesar verificación de SKU duplicado (para inventario interno)
if (isset($_POST['action']) && $_POST['action'] == 'check_sku') {
    try {
        $sku = $_POST['sku'] ?? '';
        $exclude_id = $_POST['exclude_id'] ?? 0;

        if (empty($sku)) {
            header('Content-Type: application/json');
            echo json_encode(['exists' => false]);
            exit;
        }

        if ($exclude_id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE sku = ? AND id != ?");
            $stmt->execute([$sku, $exclude_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE sku = ?");
            $stmt->execute([$sku]);
        }

        $exists = $stmt->fetchColumn() > 0;

        header('Content-Type: application/json');
        echo json_encode(['exists' => $exists]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA AGREGAR NUEVO SKU IMPORTADOR
if (isset($_POST['action']) && $_POST['action'] == 'add_sku_importador') {
    try {
        $importador_id = $_POST['importador_id'] ?? null;
        $sku_codigo = $_POST['sku_codigo'] ?? '';
        $nombre_producto = $_POST['nombre_producto'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $precio_importador = $_POST['precio_importador'] ?? 0;
        $categoria = $_POST['categoria'] ?? '';
        $estado = $_POST['estado'] ?? 'disponible';

        if (empty($importador_id) || empty($sku_codigo) || empty($nombre_producto)) {
            throw new Exception("Los campos Importador, SKU y Nombre de Producto son obligatorios.");
        }

        // Verificar si el SKU ya existe para este importador
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sku_importadores WHERE importador_id = ? AND sku_codigo = ?");
        $stmt_check->execute([$importador_id, $sku_codigo]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("Ya existe un SKU con este código para el importador seleccionado.");
        }

        $stmt = $pdo->prepare("INSERT INTO sku_importadores (
            importador_id, sku_codigo, nombre_producto, descripcion, precio_importador, categoria, estado, fecha_creacion, fecha_actualizacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

        $stmt->execute([
            $importador_id,
            $sku_codigo,
            $nombre_producto,
            $descripcion,
            $precio_importador,
            $categoria,
            $estado
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'SKU de importador agregado exitosamente.']);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Error de base de datos: " . $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA OBTENER DATOS DE UN SKU IMPORTADOR ESPECÍFICO (para edición)
if (isset($_POST['action']) && $_POST['action'] == 'get_sku_importador_data') {
    try {
        $sku_importador_id = $_POST['sku_importador_id'] ?? 0;

        if ($sku_importador_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM sku_importadores WHERE id = ?");
            $stmt->execute([$sku_importador_id]);
            $sku_importador_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sku_importador_data) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'sku_importador' => $sku_importador_data]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'SKU de importador no encontrado']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID de SKU de importador no válido']);
        }
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA ACTUALIZAR SKU IMPORTADOR
if (isset($_POST['action']) && $_POST['action'] == 'update_sku_importador') {
    try {
        $sku_importador_id = $_POST['sku_importador_id'] ?? null;
        $importador_id = $_POST['importador_id'] ?? null;
        $sku_codigo = $_POST['sku_codigo'] ?? '';
        $nombre_producto = $_POST['nombre_producto'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $precio_importador = $_POST['precio_importador'] ?? 0;
        $categoria = $_POST['categoria'] ?? '';
        $estado = $_POST['estado'] ?? 'disponible';

        if (empty($sku_importador_id) || empty($importador_id) || empty($sku_codigo) || empty($nombre_producto)) {
            throw new Exception("Los campos ID, Importador, SKU y Nombre de Producto son obligatorios.");
        }

        // Verificar si el SKU ya existe para este importador, excluyendo el SKU actual
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sku_importadores WHERE importador_id = ? AND sku_codigo = ? AND id != ?");
        $stmt_check->execute([$importador_id, $sku_codigo, $sku_importador_id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("Ya existe un SKU con este código para el importador seleccionado.");
        }

        $stmt = $pdo->prepare("UPDATE sku_importadores SET 
            importador_id = ?, sku_codigo = ?, nombre_producto = ?, descripcion = ?, 
            precio_importador = ?, categoria = ?, estado = ?, fecha_actualizacion = NOW() 
            WHERE id = ?");

        $stmt->execute([
            $importador_id,
            $sku_codigo,
            $nombre_producto,
            $descripcion,
            $precio_importador,
            $categoria,
            $estado,
            $sku_importador_id
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'SKU de importador actualizado exitosamente.']);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Error de base de datos: " . $e->getMessage()]);
        exit;
    }
}

// --- NUEVAS FUNCIONALIDADES PARA IMPORTADORES ---

// PROCESAR AJAX PARA AGREGAR NUEVO IMPORTADOR
if (isset($_POST['action']) && $_POST['action'] == 'add_importador') {
    try {
        $nombre_importador = $_POST['nombre_importador'] ?? '';
        $codigo_importador = $_POST['codigo_importador'] ?? '';
        $estado = $_POST['estado'] ?? 'activo';

        if (empty($nombre_importador) || empty($codigo_importador)) {
            throw new Exception("El nombre y el código del importador son obligatorios.");
        }

        // Verificar si el código de importador ya existe
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM importadores WHERE codigo_importador = ?");
        $stmt_check->execute([$codigo_importador]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("El código de importador ya existe. Por favor, use uno diferente.");
        }

        $stmt = $pdo->prepare("INSERT INTO importadores (
            nombre_importador, codigo_importador, estado, fecha_creacion, fecha_actualizacion
        ) VALUES (?, ?, ?, NOW(), NOW())");

        $stmt->execute([
            $nombre_importador,
            $codigo_importador,
            $estado
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Importador agregado exitosamente.']);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Error de base de datos: " . $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA OBTENER DATOS DE UN IMPORTADOR ESPECÍFICO (para edición)
if (isset($_POST['action']) && $_POST['action'] == 'get_importador_data') {
    try {
        $importador_id = $_POST['importador_id'] ?? 0;

        if ($importador_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM importadores WHERE id = ?");
            $stmt->execute([$importador_id]);
            $importador_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($importador_data) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'importador' => $importador_data]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Importador no encontrado']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID de importador no válido']);
        }
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA ACTUALIZAR IMPORTADOR
if (isset($_POST['action']) && $_POST['action'] == 'update_importador') {
    try {
        $importador_id = $_POST['importador_id'] ?? null;
        $nombre_importador = $_POST['nombre_importador'] ?? '';
        $codigo_importador = $_POST['codigo_importador'] ?? '';
        $estado = $_POST['estado'] ?? 'activo';

        if (empty($importador_id) || empty($nombre_importador) || empty($codigo_importador)) {
            throw new Exception("Los campos ID, Nombre y Código del importador son obligatorios.");
        }

        // Verificar si el código de importador ya existe, excluyendo el importador actual
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM importadores WHERE codigo_importador = ? AND id != ?");
        $stmt_check->execute([$codigo_importador, $importador_id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("El código de importador ya existe para otro importador. Por favor, use uno diferente.");
        }

        $stmt = $pdo->prepare("UPDATE importadores SET 
            nombre_importador = ?, codigo_importador = ?, estado = ?, fecha_actualizacion = NOW() 
            WHERE id = ?");

        $stmt->execute([
            $nombre_importador,
            $codigo_importador,
            $estado,
            $importador_id
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Importador actualizado exitosamente.']);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Error de base de datos: " . $e->getMessage()]);
        exit;
    }
}

// PROCESAR AJAX PARA ELIMINAR IMPORTADOR
if (isset($_POST['action']) && $_POST['action'] == 'delete_importador') {
    try {
        $importador_id = $_POST['importador_id'] ?? 0;

        if ($importador_id <= 0) {
            throw new Exception("ID de importador no válido.");
        }

        // Opcional: Verificar si hay SKUs o productos asociados antes de eliminar
        // $stmt_check_skus = $pdo->prepare("SELECT COUNT(*) FROM sku_importadores WHERE importador_id = ?");
        // $stmt_check_skus->execute([$importador_id]);
        // if ($stmt_check_skus->fetchColumn() > 0) {
        //     throw new Exception("No se puede eliminar el importador porque tiene SKUs asociados.");
        // }

        $stmt = $pdo->prepare("DELETE FROM importadores WHERE id = ?");
        $stmt->execute([$importador_id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Importador eliminado exitosamente.']);
        exit;
    } catch(Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Error de base de datos: " . $e->getMessage()]);
        exit;
    }
}

// --- FIN NUEVAS FUNCIONALIDADES PARA IMPORTADORES ---


// Procesar búsqueda
$search = $_GET['search'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

// Procesar subida de imagen
$imagen_nombre = null;
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
    $upload_dir = 'uploads/productos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($file_extension, $allowed_extensions)) {
        $imagen_nombre = uniqid() . '.' . $file_extension;
        move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $imagen_nombre);
    }
}

// Procesar eliminación de producto
if (isset($_POST['action']) && $_POST['action'] == 'delete_product') {
    try {
        $stmt = $pdo->prepare("SELECT imagen FROM inventario WHERE id = ?");
        $stmt->execute([$_POST['producto_id']]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = ?");
        $stmt->execute([$_POST['producto_id']]);

        if ($producto && $producto['imagen'] && file_exists('uploads/productos/' . $producto['imagen'])) {
            unlink('uploads/productos/' . $producto['imagen']);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Procesar obtención de datos para edición (para inventario interno)
if (isset($_POST['action']) && $_POST['action'] == 'get_product') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
        $stmt->execute([$_POST['producto_id']]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($producto) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'producto' => $producto]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        }
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Procesar actualización de producto (para inventario interno)
if (isset($_POST['action']) && $_POST['action'] == 'update_product') {
    try {
        $imagen_actual = $_POST['imagen_actual'] ?? '';

        if (isset($_FILES['imagen_edit']) && $_FILES['imagen_edit']['error'] == 0) {
            $upload_dir = 'uploads/productos/';
            $file_extension = strtolower(pathinfo($_FILES['imagen_edit']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_extensions)) {
                if ($imagen_actual && file_exists($upload_dir . $imagen_actual)) {
                    unlink($upload_dir . $imagen_actual);
                }
                $imagen_actual = uniqid() . '.' . $file_extension;
                move_uploaded_file($_FILES['imagen_edit']['tmp_name'], $upload_dir . $imagen_actual);
            }
        }

        // VERIFICAR SI EL SKU YA EXISTE (EXCLUYENDO EL PRODUCTO ACTUAL)
        $nuevo_sku = $_POST['sku'] ?? '';
        if (!empty($nuevo_sku)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE sku = ? AND id != ?");
            $stmt->execute([$nuevo_sku, $_POST['producto_id']]);
            if ($stmt->fetchColumn() > 0) {
                $nuevo_sku = generateUniqueSKU($pdo, $_POST['nombre_producto'] ?? 'PRODUCTO');
            }
        } else {
            $nuevo_sku = generateUniqueSKU($pdo, $_POST['nombre_producto'] ?? 'PRODUCTO');
        }

        $stmt = $pdo->prepare("UPDATE inventario SET 
            nombre_producto = ?, imagen = ?, rastrear_inventario = ?, color = ?,
            precio_venta = ?, costo = ?, categoria = ?, sku = ?, codigo_barras = ?,
            ruta_comprar = ?, ruta_manufactura = ?, responsable = ?, peso = ?,
            volumen = ?, notas_internas = ?, importador_id = ?, sku_importador_id = ?,
            fecha_actualizacion = NOW() WHERE id = ?");

        $stmt->execute([
            $_POST['nombre_producto'] ?? '',
            $imagen_actual,
            $_POST['rastrear_inventario'] ?? 'por_lotes',
            $_POST['color'] ?? '',
            $_POST['precio_venta'] ?? 0,
            $_POST['costo'] ?? 0,
            $_POST['categoria'] ?? '',
            $nuevo_sku,
            $_POST['codigo_barras'] ?? '',
            isset($_POST['ruta_comprar']) ? 1 : 0,
            isset($_POST['ruta_manufactura']) ? 1 : 0,
            $_POST['responsable'] ?? '',
            $_POST['peso'] ?? 0,
            $_POST['volumen'] ?? 0,
            $_POST['notas_internas'] ?? '',
            !empty($_POST['importador_id']) ? $_POST['importador_id'] : null,
            !empty($_POST['sku_importador_id']) ? $_POST['sku_importador_id'] : null,
            $_POST['producto_id']
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'new_sku' => $nuevo_sku]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Procesar formulario si se envía (solo para nuevos productos de inventario interno)
if ($_POST && !isset($_POST['action'])) {
    try {
        // Generar SKU único si no se proporciona uno
        $sku_final = $_POST['sku'] ?? '';
        if (empty($sku_final)) {
            $sku_final = generateUniqueSKU($pdo, $_POST['nombre_producto'] ?? 'PRODUCTO');
        } else {
            // Verificar si el SKU ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE sku = ?");
            $stmt->execute([$sku_final]);
            if ($stmt->fetchColumn() > 0) {
                $sku_final = generateUniqueSKU($pdo, $_POST['nombre_producto'] ?? 'PRODUCTO');
            }
        }

        $stmt = $pdo->prepare("INSERT INTO inventario (
            nombre_producto, imagen, rastrear_inventario, color, precio_venta, costo, 
            categoria, sku, codigo_barras, ruta_comprar, ruta_manufactura,
            responsable, peso, volumen, notas_internas, importador_id, sku_importador_id,
            fecha_creacion, fecha_actualizacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

        $stmt->execute([
            $_POST['nombre_producto'] ?? '',
            $imagen_nombre,
            $_POST['rastrear_inventario'] ?? 'por_lotes',
            $_POST['color'] ?? '',
            $_POST['precio_venta'] ?? 0,
            $_POST['costo'] ?? 0,
            $_POST['categoria'] ?? '',
            $sku_final,
            $_POST['codigo_barras'] ?? '',
            isset($_POST['ruta_comprar']) ? 1 : 0,
            isset($_POST['ruta_manufactura']) ? 1 : 0,
            $_POST['responsable'] ?? '',
            $_POST['peso'] ?? 0,
            $_POST['volumen'] ?? 0,
            $_POST['notas_internas'] ?? '',
            !empty($_POST['importador_id']) ? $_POST['importador_id'] : null,
            !empty($_POST['sku_importador_id']) ? $_POST['sku_importador_id'] : null
        ]);

        $mensaje = "Producto guardado exitosamente con SKU: " . $sku_final;
        header("Location: " . $_SERVER['PHP_SELF'] . "?vista=lista&success=1");
        exit;
    } catch(PDOException $e) {
        $error = "Error al guardar: " . $e->getMessage();
    }
}

// Obtener productos con filtros de búsqueda
try {
    $sql = "SELECT i.*, imp.nombre_importador, si.sku_codigo as sku_importador_codigo, si.nombre_producto as producto_importador
            FROM inventario i
            LEFT JOIN importadores imp ON i.importador_id = imp.id
            LEFT JOIN sku_importadores si ON i.sku_importador_id = si.id
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (i.nombre_producto LIKE ? OR i.sku LIKE ? OR i.codigo_barras LIKE ? OR si.sku_codigo LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($categoria_filtro)) {
        $sql .= " AND i.categoria = ?";
        $params[] = $categoria_filtro;
    }

    $sql .= " ORDER BY i.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $productos = [];
    $error = "Error al cargar productos: " . $e->getMessage();
}

// Obtener usuarios para el dropdown de responsables
try {
    $usuarios = $pdo->query("SELECT Id, CONCAT(Nombres, ' ', Apellidos) as nombre_completo FROM iniciosesion ORDER BY Nombres")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $usuarios = [];
}

// Obtener importadores para el dropdown (para formularios de producto y SKU)
try {
    $importadores = $pdo->query("SELECT id, nombre_importador, codigo_importador FROM importadores WHERE estado = 'activo' ORDER BY nombre_importador")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $importadores = [];
}

// Obtener TODOS los importadores para la vista 'importadores'
$all_importadores = [];
if ($vista == 'importadores') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM importadores ORDER BY nombre_importador ASC");
        $stmt->execute();
        $all_importadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_importadores = "Error al cargar importadores: " . $e->getMessage();
    }
}

// Obtener todos los SKU de importadores para la vista 'sku'
$all_sku_importadores = [];
if ($vista == 'sku') {
    try {
        $stmt = $pdo->prepare("
            SELECT si.*, i.nombre_importador, i.codigo_importador
            FROM sku_importadores si
            JOIN importadores i ON si.importador_id = i.id
            ORDER BY si.precio_importador ASC
        ");
        $stmt->execute();
        $all_sku_importadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_sku_importadores = "Error al cargar SKUs de importadores: " . $e->getMessage();
    }
}


// Categorías para el dropdown
$categorias_default = [
    'Accesorios',
    'Herramientas', 
    'Productos de Belleza',
    'Equipos',
    'Consumibles',
    'Mobiliario',
    'Tecnología'
];

// Obtener categorías únicas de la base de datos
try {
    $categorias_db = $pdo->query("SELECT DISTINCT categoria FROM inventario WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
    $categorias = array_unique(array_merge($categorias_default, $categorias_db));
    sort($categorias);
} catch(PDOException $e) {
    $categorias = $categorias_default;
}


// Mostrar mensaje de éxito si viene de redirección
if (isset($_GET['success'])) {
    $mensaje = "Producto guardado exitosamente";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Sistema ERP ELESS</title>
    <link rel="stylesheet" href="css/inventario2.css">
    <link rel="stylesheet" href="css/siderbarycabezal.css?v=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main">
    <?php include __DIR__ . '/header.php'; ?>

    <section class="content">
        <?php if ($vista == 'lista'): ?>
        <!-- VISTA DE CATÁLOGO CON CARDS COMPLETOS -->
        <div class="inventory-container">
            <div class="inventory-header">
                <div class="breadcrumb">
                    <a href="index.php">Dashboard</a> / <span>Inventario</span>
                </div>
                <h2><i class="fas fa-boxes"></i> Gestión de Inventario</h2>
            </div>

            <div class="header-tabs">
                <div class="tabs">
                    <button class="tab active">Catálogo de Productos</button>
                    <button class="tab" onclick="window.location.href='?vista=nuevo'">Nuevo Producto</button>
                    <button class="tab" onclick="window.location.href='?vista=sku'">SKU Importadores</button>
                    <button class="tab" onclick="window.location.href='?vista=importadores'">Importadores</button>
                </div>
            </div>

            <?php if (isset($mensaje)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- FORMULARIO DE BÚSQUEDA -->
            <div class="search-container">
                <form method="GET" class="search-form">
                    <input type="hidden" name="vista" value="lista">
                    
                    <div class="search-group">
                        <label for="search">Buscar productos</label>
                        <div class="search-input-container">
                            <i class="fas fa-search search-icon"></i>
                            <input 
                                type="text" 
                                id="search" 
                                name="search" 
                                class="search-input"
                                placeholder="Buscar por nombre, SKU o código de barras..."
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                        </div>
                    </div>
                    
                    <div class="category-group">
                        <label for="categoria">Filtrar por categoría</label>
                        <select name="categoria" id="categoria" class="filter-select">
                            <option value="">Todas las categorías</option>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo $categoria_filtro === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    
                    <a href="?vista=lista" class="clear-btn">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </form>
            </div>

            <!-- INFORMACIÓN DE RESULTADOS -->
            <div class="results-info">
                <div class="results-count">
                    <i class="fas fa-box"></i>
                    Mostrando <?php echo count($productos); ?> productos
                    <?php if (!empty($search) || !empty($categoria_filtro)): ?>
                        <?php 
                        $filtros = [];
                        if (!empty($search)) $filtros[] = "búsqueda: \"$search\"";
                        if (!empty($categoria_filtro)) $filtros[] = "categoría: \"$categoria_filtro\"";
                        ?>
                        (filtrado por <?php echo implode(', ', $filtros); ?>)
                    <?php endif; ?>
                </div>
                <button onclick="window.location.href='?vista=nuevo'" class="btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </button>
            </div>

            <!-- GRID DE PRODUCTOS -->
            <?php if (!empty($productos)): ?>
                <div class="products-grid">
                    <?php foreach ($productos as $producto): ?>
                    <div class="product-card" onclick="editProduct(<?php echo $producto['id']; ?>)">
                        <!-- Imagen del producto -->
                        <div class="product-image-container">
                            <?php if (!empty($producto['imagen']) && file_exists('uploads/productos/' . $producto['imagen'])): ?>
                                <img src="uploads/productos/<?php echo htmlspecialchars($producto['imagen']); ?>"
                                    alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>"
                                    class="product-card-image"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="no-image-placeholder" style="display: none;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php else: ?>
                                <div class="no-image-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Información del producto -->
                        <div class="product-info">
                            <div class="product-header">
                                <span class="product-code"><?php echo htmlspecialchars($producto['sku'] ?: 'ID-' . $producto['id']); ?></span>
                                <span class="stock-badge <?php echo ($producto['precio_venta'] > 0) ? 'stock-available' : 'stock-unavailable'; ?>">
                                    <?php echo ($producto['precio_venta'] > 0) ? 'Disponible' : 'Sin precio'; ?>
                                </span>
                            </div>
                            <h3 class="product-name" title="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                                <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                            </h3>
                            <div class="product-price">
                                S/ <?php echo number_format($producto['precio_venta'], 2); ?>
                            </div>
                            <?php if (!empty($producto['categoria'])): ?>
                                <span class="product-category">
                                    <?php echo htmlspecialchars($producto['categoria']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <!-- Información del importador si existe -->
                            <?php if (!empty($producto['nombre_importador'])): ?>
                                <div class="importador-info" style="margin-top: 8px; padding: 4px 8px; background: #e0f2fe; border-radius: 4px; font-size: 12px; color: #0277bd;">
                                    <i class="fas fa-shipping-fast"></i> <?php echo htmlspecialchars($producto['nombre_importador']); ?>
                                    <?php if (!empty($producto['sku_importador_codigo'])): ?>
                                        <br><strong>SKU:</strong> <?php echo htmlspecialchars($producto['sku_importador_codigo']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- BOTONES DE ACCIÓN -->
                            <div class="product-actions">
                                <button class="action-btn edit" onclick="event.stopPropagation(); editProduct(<?php echo $producto['id']; ?>)" title="Editar producto">
                                    <i class="fas fa-edit"></i> EDITAR
                                </button>
                                <button class="action-btn delete" onclick="event.stopPropagation(); deleteProduct(<?php echo $producto['id']; ?>)" title="Eliminar producto">
                                    <i class="fas fa-trash"></i> ELIMINAR
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No se encontraron productos</h3>
                    <p>
                        <?php if (!empty($search) || !empty($categoria_filtro)): ?>
                            No hay productos que coincidan con los filtros aplicados.
                        <?php else: ?>
                            No hay productos registrados en el sistema.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || !empty($categoria_filtro)): ?>
                        <a href="?vista=lista" class="btn-primary">
                            <i class="fas fa-times"></i> Limpiar filtros
                        </a>
                    <?php else: ?>
                        <button onclick="window.location.href='?vista=nuevo'" class="btn-primary">
                            <i class="fas fa-plus"></i> Agregar primer producto
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- MODAL DE EDICIÓN DE PRODUCTO (Inventario Interno) -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Editar Producto</h3>
                    <span class="close" onclick="closeEditModal()">&times;</span>
                </div>
                <form id="editForm" enctype="multipart/form-data">
                    <input type="hidden" id="edit_producto_id" name="producto_id">
                    <input type="hidden" id="edit_imagen_actual" name="imagen_actual">
                    <input type="hidden" name="action" value="update_product">
                    
                    <div class="modal-body">
                        <!-- Información básica del producto -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_nombre_producto">Nombre del Producto *</label>
                                <input type="text" id="edit_nombre_producto" name="nombre_producto" required>
                            </div>
                        </div>
                        
                        <!-- SKU de Importador -->
                        <div class="sku-importador-section">
                            <h4><i class="fas fa-shipping-fast"></i> SKU de Importador</h4>
                            
                            <div class="importador-dropdown">
                                <label for="edit_importador_id">Seleccionar Importador</label>
                                <select id="edit_importador_id" name="importador_id" class="importador-select" onchange="loadSKUsForEdit(this.value)">
                                    <option value="">Sin importador</option>
                                    <?php foreach($importadores as $imp): ?>
                                        <option value="<?php echo $imp['id']; ?>">
                                            <?php echo htmlspecialchars($imp['nombre_importador']); ?> (<?php echo htmlspecialchars($imp['codigo_importador']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="sku-list-container" id="edit_sku_list_container" style="display: none;">
                                <div class="sku-list" id="edit_sku_list">
                                    <!-- SKUs se cargarán aquí dinámicamente -->
                                </div>
                            </div>
                            
                            <div class="selected-sku-info" id="edit_selected_sku_info">
                                <h5><i class="fas fa-check-circle"></i> SKU Seleccionado</h5>
                                <div id="edit_sku_details"></div>
                            </div>
                            <input type="hidden" id="edit_sku_importador_id" name="sku_importador_id">
                        </div>
                        
                        <!-- Resto de campos del formulario -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_categoria">Categoría</label>
                                <select id="edit_categoria" name="categoria" class="filter-select">
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach($categorias as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_sku">SKU Interno</label>
                                <input type="text" id="edit_sku" name="sku" onblur="checkSKUDuplicate(this.value, document.getElementById('edit_producto_id').value)">
                                <div id="sku_warning" class="sku-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Este SKU ya existe. Se generará uno automáticamente al guardar.</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_precio_venta">Precio de venta</label>
                                <input type="number" id="edit_precio_venta" name="precio_venta" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="edit_costo">Costo</label>
                                <input type="number" id="edit_costo" name="costo" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($vista == 'sku'): ?>
        <!-- VISTA DE GESTIÓN SKU IMPORTADORES -->
        <div class="inventory-container">
            <div class="inventory-header">
                <div class="breadcrumb">
                    <a href="index.php">Dashboard</a> / <a href="?vista=lista">Inventario</a> / <span>SKU Importadores</span>
                </div>
                <h2><i class="fas fa-shipping-fast"></i> Gestión de SKU Importadores</h2>
            </div>

            <div class="header-tabs">
                <div class="tabs">
                    <button class="tab" onclick="window.location.href='?vista=lista'">Catálogo de Productos</button>
                    <button class="tab" onclick="window.location.href='?vista=nuevo'">Nuevo Producto</button>
                    <button class="tab active">SKU Importadores</button>
                    <button class="tab" onclick="window.location.href='?vista=importadores'">Importadores</button>
                </div>
            </div>

            <?php if (isset($mensaje_sku_importador)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($mensaje_sku_importador); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_sku_importador)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_sku_importador); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario para agregar nuevo SKU de Importador -->
            <div class="form-section active" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;">
                <h3><i class="fas fa-plus-square"></i> Agregar Nuevo SKU de Importador</h3>
                <form id="addSkuImportadorForm" class="inventory-form">
                    <input type="hidden" name="action" value="add_sku_importador">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_importador_id">Importador *</label>
                            <select id="add_importador_id" name="importador_id" required class="filter-select">
                                <option value="">Seleccionar Importador</option>
                                <?php foreach($importadores as $imp): ?>
                                    <option value="<?php echo $imp['id']; ?>">
                                        <?php echo htmlspecialchars($imp['nombre_importador']); ?> (<?php echo htmlspecialchars($imp['codigo_importador']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_sku_codigo">Código SKU del Importador *</label>
                            <input type="text" id="add_sku_codigo" name="sku_codigo" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_nombre_producto">Nombre del Producto (Importador) *</label>
                            <input type="text" id="add_nombre_producto" name="nombre_producto" required>
                        </div>
                        <div class="form-group">
                            <label for="add_precio_importador">Precio del Importador</label>
                            <input type="number" id="add_precio_importador" name="precio_importador" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_categoria">Categoría</label>
                            <select id="add_categoria" name="categoria" class="filter-select">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_estado">Estado</label>
                            <select id="add_estado" name="estado" class="filter-select">
                                <option value="disponible">Disponible</option>
                                <option value="agotado">Agotado</option>
                                <option value="descontinuado">Descontinuado</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="add_descripcion">Descripción</label>
                        <textarea id="add_descripcion" name="descripcion" placeholder="Descripción del producto del importador"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus"></i> Agregar SKU Importador
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de SKUs de Importadores existentes -->
            <div style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;">
                <h3><i class="fas fa-list"></i> SKUs de Importadores Existentes</h3>
                <?php if (!empty($all_sku_importadores)): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Importador</th>
                                    <th>SKU Código</th>
                                    <th>Nombre Producto</th>
                                    <th>Precio</th>
                                    <th>Categoría</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th> <!-- Nueva columna para acciones -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_sku_importadores as $sku_imp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sku_imp['nombre_importador']); ?></td>
                                    <td><?php echo htmlspecialchars($sku_imp['sku_codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($sku_imp['nombre_producto']); ?></td>
                                    <td>S/ <?php echo number_format($sku_imp['precio_importador'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($sku_imp['categoria']); ?></td>
                                    <td><?php echo htmlspecialchars($sku_imp['estado']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($sku_imp['fecha_creacion']))); ?></td>
                                    <td>
                                        <button class="action-btn edit" onclick="editSkuImportador(<?php echo $sku_imp['id']; ?>)" title="Editar SKU de importador">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Puedes añadir un botón de eliminar si lo necesitas -->
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <p>No hay SKUs de importadores registrados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL DE EDICIÓN DE SKU IMPORTADOR -->
        <div id="editSkuImportadorModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Editar SKU de Importador</h3>
                    <span class="close" onclick="closeEditSkuImportadorModal()">&times;</span>
                </div>
                <form id="editSkuImportadorForm">
                    <input type="hidden" id="edit_sku_importador_id_modal" name="sku_importador_id">
                    <input type="hidden" name="action" value="update_sku_importador">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_sku_imp_importador_id">Importador *</label>
                                <select id="edit_sku_imp_importador_id" name="importador_id" required class="filter-select">
                                    <option value="">Seleccionar Importador</option>
                                    <?php foreach($importadores as $imp): ?>
                                        <option value="<?php echo $imp['id']; ?>">
                                            <?php echo htmlspecialchars($imp['nombre_importador']); ?> (<?php echo htmlspecialchars($imp['codigo_importador']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_sku_imp_codigo">Código SKU del Importador *</label>
                                <input type="text" id="edit_sku_imp_codigo" name="sku_codigo" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_sku_imp_nombre_producto">Nombre del Producto (Importador) *</label>
                                <input type="text" id="edit_sku_imp_nombre_producto" name="nombre_producto" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_sku_imp_precio_importador">Precio del Importador</label>
                                <input type="number" id="edit_sku_imp_precio_importador" name="precio_importador" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_sku_imp_categoria">Categoría</label>
                                <select id="edit_sku_imp_categoria" name="categoria" class="filter-select">
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach($categorias as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_sku_imp_estado">Estado</label>
                                <select id="edit_sku_imp_estado" name="estado" class="filter-select">
                                    <option value="disponible">Disponible</option>
                                    <option value="agotado">Agotado</option>
                                    <option value="descontinuado">Descontinuado</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_sku_imp_descripcion">Descripción</label>
                            <textarea id="edit_sku_imp_descripcion" name="descripcion" placeholder="Descripción del producto del importador"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeEditSkuImportadorModal()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($vista == 'importadores'): ?>
        <!-- VISTA DE GESTIÓN DE IMPORTADORES -->
        <div class="inventory-container">
            <div class="inventory-header">
                <div class="breadcrumb">
                    <a href="index.php">Dashboard</a> / <a href="?vista=lista">Inventario</a> / <span>Importadores</span>
                </div>
                <h2><i class="fas fa-truck"></i> Gestión de Importadores</h2>
            </div>

            <div class="header-tabs">
                <div class="tabs">
                    <button class="tab" onclick="window.location.href='?vista=lista'">Catálogo de Productos</button>
                    <button class="tab" onclick="window.location.href='?vista=nuevo'">Nuevo Producto</button>
                    <button class="tab" onclick="window.location.href='?vista=sku'">SKU Importadores</button>
                    <button class="tab active">Importadores</button>
                </div>
            </div>

            <?php if (isset($mensaje_importador)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($mensaje_importador); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_importadores)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_importadores); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario para agregar nuevo Importador -->
            <div class="form-section active" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;">
                <h3><i class="fas fa-plus-square"></i> Agregar Nuevo Importador</h3>
                <form id="addImportadorForm" class="inventory-form">
                    <input type="hidden" name="action" value="add_importador">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_nombre_importador">Nombre del Importador *</label>
                            <input type="text" id="add_nombre_importador" name="nombre_importador" required>
                        </div>
                        <div class="form-group">
                            <label for="add_codigo_importador">Código del Importador *</label>
                            <input type="text" id="add_codigo_importador" name="codigo_importador" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_importador_estado">Estado</label>
                            <select id="add_importador_estado" name="estado" class="filter-select">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus"></i> Agregar Importador
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Importadores existentes -->
            <div style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;">
                <h3><i class="fas fa-list"></i> Importadores Existentes</h3>
                <?php if (!empty($all_importadores)): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Código</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_importadores as $importador): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($importador['id']); ?></td>
                                    <td><?php echo htmlspecialchars($importador['nombre_importador']); ?></td>
                                    <td><?php echo htmlspecialchars($importador['codigo_importador']); ?></td>
                                    <td><?php echo htmlspecialchars($importador['estado']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($importador['fecha_creacion']))); ?></td>
                                    <td>
                                        <button class="action-btn edit" onclick="editImportador(<?php echo $importador['id']; ?>)" title="Editar importador">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteImportador(<?php echo $importador['id']; ?>)" title="Eliminar importador">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <p>No hay importadores registrados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL DE EDICIÓN DE IMPORTADOR -->
        <div id="editImportadorModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Editar Importador</h3>
                    <span class="close" onclick="closeEditImportadorModal()">&times;</span>
                </div>
                <form id="editImportadorForm">
                    <input type="hidden" id="edit_importador_id_modal" name="importador_id">
                    <input type="hidden" name="action" value="update_importador">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_nombre_importador">Nombre del Importador *</label>
                                <input type="text" id="edit_nombre_importador" name="nombre_importador" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_codigo_importador">Código del Importador *</label>
                                <input type="text" id="edit_codigo_importador" name="codigo_importador" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_importador_estado">Estado</label>
                                <select id="edit_importador_estado" name="estado" class="filter-select">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeEditImportadorModal()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- VISTA DE FORMULARIO NUEVO PRODUCTO CON SKU IMPORTADORES -->
        <div class="inventory-container">
            <div class="inventory-header">
                <div class="breadcrumb">
                    <a href="index.php">Dashboard</a> / <a href="?vista=lista">Inventario</a> / <span>Nuevo Producto</span>
                </div>
                <h2><i class="fas fa-boxes"></i> Gestión de Inventario</h2>
            </div>

            <div class="header-tabs">
                <div class="tabs">
                    <button class="tab" onclick="window.location.href='?vista=lista'">Catálogo de Productos</button>
                    <button class="tab active">Nuevo Producto</button>
                    <button class="tab" onclick="window.location.href='?vista=sku'">SKU Importadores</button>
                    <button class="tab" onclick="window.location.href='?vista=importadores'">Importadores</button>
                </div>
            </div>

            <div class="product-header">
                <div class="product-info">
                    <h1 class="product-title"><i class="fas fa-plus-circle"></i> Nuevo Producto</h1>
                </div>
                <div class="product-image">
                    <div class="image-upload-area" onclick="document.getElementById('imagen').click()">
                        <img id="image-preview" style="display: none; width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">
                        <div class="image-placeholder" id="image-placeholder">
                            <i class="fas fa-camera"></i>
                            <span>Subir Imagen</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-tabs">
                <button class="form-tab active">Información general</button>
                <button class="form-tab">Inventario</button>
            </div>

            <?php if (isset($mensaje)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="inventory-form">
                <input type="file" id="imagen" name="imagen" accept="image/*" style="display: none;" onchange="previewImage(this)">
                
                <!-- SECCIÓN INFORMACIÓN GENERAL -->
                <div class="form-section active" id="general">
                    <h3><i class="fas fa-info-circle"></i> Información General</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre_producto">Nombre del Producto *</label>
                            <input type="text" id="nombre_producto" name="nombre_producto" required>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN SKU DE IMPORTADOR -->
                    <div class="sku-importador-section">
                        <h4><i class="fas fa-shipping-fast"></i> SKU de Importador</h4>
                        <p style="margin: 0 0 16px 0; opacity: 0.9;">Selecciona un importador y su SKU correspondiente para este producto.</p>
                        
                        <div class="importador-dropdown">
                            <label for="importador_id">Seleccionar Importador</label>
                            <select id="importador_id" name="importador_id" class="importador-select" onchange="loadSKUs(this.value)">
                                <option value="">Sin importador</option>
                                <?php foreach($importadores as $importador): ?>
                                    <option value="<?php echo $importador['id']; ?>">
                                        <?php echo htmlspecialchars($importador['nombre_importador']); ?> (<?php echo htmlspecialchars($importador['codigo_importador']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="sku-list-container" id="sku_list_container" style="display: none;">
                            <div class="sku-list" id="sku_list">
                                <!-- SKUs se cargarán aquí dinámicamente -->
                            </div>
                        </div>
                        
                        <div class="selected-sku-info" id="selected_sku_info">
                            <h5><i class="fas fa-check-circle"></i> SKU Seleccionado</h5>
                            <div id="sku_details"></div>
                        </div>
                        <input type="hidden" id="sku_importador_id" name="sku_importador_id">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" class="filter-select">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sku">SKU Interno</label>
                            <input type="text" id="sku" name="sku" placeholder="Se generará automáticamente si se deja vacío" onblur="checkNewSKUDuplicate(this.value)">
                            <div id="new_sku_warning" class="sku-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Este SKU ya existe. Se generará uno automáticamente al guardar.</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio_venta">Precio de venta</label>
                            <div class="input-with-unit">
                                <span class="currency">S/</span>
                                <input type="number" id="precio_venta" name="precio_venta" step="0.01" placeholder="0.00">
                                <span class="unit">por Unidades</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="costo">Costo</label>
                            <div class="input-with-unit">
                                <span class="currency">S/</span>
                                <input type="number" id="costo" name="costo" step="0.01" placeholder="0.00">
                                <span class="unit">por Unidades</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo_barras">Código de barras</label>
                            <input type="text" id="codigo_barras" name="codigo_barras">
                        </div>
                        <div class="form-group">
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color" placeholder="El usuario podrá elegir">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Producto
                        </button>
                        <button type="button" class="btn-secondary" onclick="window.location.href='?vista=lista'">
                            <i class="fas fa-arrow-left"></i> Volver a Catálogo
                        </button>
                    </div>
                </div>
                
                <!-- SECCIÓN INVENTARIO -->
                <div class="form-section" id="inventario">
                    <h3><i class="fas fa-warehouse"></i> Inventario</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rastrear_inventario">Rastrear Inventario</label>
                            <select id="rastrear_inventario" name="rastrear_inventario" class="filter-select">
                                <option value="por_lotes">Por lotes</option>
                                <option value="por_serie">Por número de serie único</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="responsable">Responsable</label>
                            <select id="responsable" name="responsable" class="filter-select">
                                <option value="">Seleccionar responsable</option>
                                <?php foreach($usuarios as $usuario): ?>
                                    <option value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>">
                                        <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Rutas</label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="ruta_comprar" value="1"> Comprar</label>
                                <label><input type="checkbox" name="ruta_manufactura" value="1"> Manufactura</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="peso">Peso (kg)</label>
                            <input type="number" id="peso" name="peso" step="0.01" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="volumen">Volumen (m³)</label>
                            <input type="number" id="volumen" name="volumen" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    
                    <h3><i class="fas fa-sticky-note"></i> Notas Internas</h3>
                    <div class="form-group">
                        <textarea id="notas_internas" name="notas_internas" placeholder="Esta nota es solo para fines internos."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Producto
                        </button>
                        <button type="button" class="btn-secondary" onclick="window.location.href='?vista=lista'">
                            <i class="fas fa-arrow-left"></i> Volver a Catálogo
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </section>
</div>
<script>
// VARIABLES GLOBALES
let selectedSKUId = null;
let selectedEditSKUId = null;

// FUNCIÓN PARA CARGAR SKUs POR IMPORTADOR (para el formulario de nuevo producto)
function loadSKUs(importadorId) {
    const skuListContainer = document.getElementById('sku_list_container');
    const skuList = document.getElementById('sku_list');
    const selectedSkuInfo = document.getElementById('selected_sku_info');

    if (!importadorId) {
        skuListContainer.style.display = 'none';
        selectedSkuInfo.classList.remove('show');
        selectedSKUId = null;
        document.getElementById('sku_importador_id').value = '';
        return;
    }

    // Mostrar loading
    skuList.innerHTML = '<div class="loading-skus"><i class="fas fa-spinner fa-spin"></i> Cargando SKUs...</div>';
    skuListContainer.style.display = 'block';

    const formData = new FormData();
    formData.append('action', 'get_skus_by_importador');
    formData.append('importador_id', importadorId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.skus.length > 0) {
                let skusHTML = '';
                data.skus.forEach(sku => {
                    skusHTML += `
                        <div class="sku-item" onclick="selectSKU(${sku.id}, '${sku.sku_codigo}', '${sku.nombre_producto}', ${sku.precio_importador}, '${sku.categoria}', '${sku.descripcion}')">
                            <div class="sku-item-header">
                                <span class="sku-code">${sku.sku_codigo}</span>
                                <span class="sku-price">S/ ${parseFloat(sku.precio_importador).toFixed(2)}</span>
                            </div>
                            <div class="sku-name">${sku.nombre_producto}</div>
                        </div>
                    `;
                });
                skuList.innerHTML = skusHTML;
            } else {
                skuList.innerHTML = '<div class="no-skus">No hay SKUs disponibles para este importador</div>';
            }
        } else {
            skuList.innerHTML = '<div class="no-skus">Error al cargar SKUs</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        skuList.innerHTML = '<div class="no-skus">Error de conexión</div>';
    });
}

// FUNCIÓN PARA SELECCIONAR SKU (para el formulario de nuevo producto)
function selectSKU(skuId, skuCodigo, nombreProducto, precio, categoria, descripcion) {
    // Remover selección anterior
    document.querySelectorAll('.sku-item').forEach(item => {
        item.classList.remove('selected');
    });

    // Seleccionar nuevo item
    event.target.closest('.sku-item').classList.add('selected');

    // Guardar ID seleccionado
    selectedSKUId = skuId;
    document.getElementById('sku_importador_id').value = skuId;

    // Mostrar información del SKU seleccionado
    const skuDetails = document.getElementById('sku_details');
    skuDetails.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div><strong>SKU:</strong> ${skuCodigo}</div>
            <div><strong>Precio:</strong> S/ ${parseFloat(precio).toFixed(2)}</div>
            <div><strong>Categoría:</strong> ${categoria}</div>
            <div><strong>Estado:</strong> Disponible</div>
        </div>
        <div><strong>Producto:</strong> ${nombreProducto}</div>
        ${descripcion ? `<div style="margin-top: 8px;"><strong>Descripción:</strong> ${descripcion}</div>` : ''}
    `;

    // Mostrar la sección de información
    document.getElementById('selected_sku_info').classList.add('show');

    // Auto-llenar algunos campos si están vacíos
    const nombreProductoInput = document.getElementById('nombre_producto');
    const categoriaSelect = document.getElementById('categoria');
    const costoInput = document.getElementById('costo');

    if (!nombreProductoInput.value) {
        nombreProductoInput.value = nombreProducto;
    }

    if (!categoriaSelect.value && categoria) {
        categoriaSelect.value = categoria;
    }

    if (!costoInput.value && precio > 0) {
        costoInput.value = precio;
    }

    console.log('SKU seleccionado:', skuId, skuCodigo);
}

// FUNCIÓN PARA CARGAR SKUs EN EDICIÓN (para el modal de edición de producto interno)
function loadSKUsForEdit(importadorId) {
    const skuListContainer = document.getElementById('edit_sku_list_container');
    const skuList = document.getElementById('edit_sku_list');
    const selectedSkuInfo = document.getElementById('edit_selected_sku_info');

    if (!importadorId) {
        skuListContainer.style.display = 'none';
        selectedSkuInfo.classList.remove('show');
        selectedEditSKUId = null;
        document.getElementById('edit_sku_importador_id').value = '';
        return;
    }

    // Mostrar loading
    skuList.innerHTML = '<div class="loading-skus"><i class="fas fa-spinner fa-spin"></i> Cargando SKUs...</div>';
    skuListContainer.style.display = 'block';

    const formData = new FormData();
    formData.append('action', 'get_skus_by_importador');
    formData.append('importador_id', importadorId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.skus.length > 0) {
                let skusHTML = '';
                data.skus.forEach(sku => {
                    skusHTML += `
                        <div class="sku-item" onclick="selectEditSKU(${sku.id}, '${sku.sku_codigo}', '${sku.nombre_producto}', ${sku.precio_importador}, '${sku.categoria}', '${sku.descripcion}')">
                            <div class="sku-item-header">
                                <span class="sku-code">${sku.sku_codigo}</span>
                                <span class="sku-price">S/ ${parseFloat(sku.precio_importador).toFixed(2)}</span>
                            </div>
                            <div class="sku-name">${sku.nombre_producto}</div>
                        </div>
                    `;
                });
                skuList.innerHTML = skusHTML;
            } else {
                skuList.innerHTML = '<div class="no-skus">No hay SKUs disponibles para este importador</div>';
            }
        } else {
            skuList.innerHTML = '<div class="no-skus">Error al cargar SKUs</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        skuList.innerHTML = '<div class="no-skus">Error de conexión</div>';
    });
}

// FUNCIÓN PARA SELECCIONAR SKU EN EDICIÓN (para el modal de edición de producto interno)
function selectEditSKU(skuId, skuCodigo, nombreProducto, precio, categoria, descripcion) {
    // Remover selección anterior
    document.querySelectorAll('#edit_sku_list .sku-item').forEach(item => {
        item.classList.remove('selected');
    });

    // Seleccionar nuevo item
    event.target.closest('.sku-item').classList.add('selected');

    // Guardar ID seleccionado
    selectedEditSKUId = skuId;
    document.getElementById('edit_sku_importador_id').value = skuId;

    // Mostrar información del SKU seleccionado
    const skuDetails = document.getElementById('edit_sku_details');
    skuDetails.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div><strong>SKU:</strong> ${skuCodigo}</div>
            <div><strong>Precio:</strong> S/ ${parseFloat(precio).toFixed(2)}</div>
            <div><strong>Categoría:</strong> ${categoria}</div>
            <div><strong>Estado:</strong> Disponible</div>
        </div>
        <div><strong>Producto:</strong> ${nombreProducto}</div>
        ${descripcion ? `<div style="margin-top: 8px;"><strong>Descripción:</strong> ${descripcion}</div>` : ''}
    `;

    // Mostrar la sección de información
    document.getElementById('edit_selected_sku_info').classList.add('show');

    console.log('SKU seleccionado para edición:', skuId, skuCodigo);
}

// BÚSQUEDA DESDE EL HEADER
function searchFromHeader() {
    const searchValue = document.getElementById('header-search').value;
    window.location.href = `?vista=lista&search=${encodeURIComponent(searchValue)}`;
}

// PREVISUALIZAR IMAGEN
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview');
            const placeholder = document.getElementById('image-placeholder');
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// FUNCIÓN PARA ELIMINAR PRODUCTO (inventario interno)
function deleteProduct(id) {
    console.log('🗑️ Intentando eliminar producto ID:', id);

    if (!confirm('¿Estás seguro de que deseas eliminar este producto? Esta acción no se puede deshacer.')) {
        return;
    }

    // Mostrar loading
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'delete-loading';
    loadingDiv.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-spinner fa-spin" style="color: #ef4444;"></i>
                <span>Eliminando producto...</span>
            </div>
        </div>
    `;
    document.body.appendChild(loadingDiv);

    const formData = new FormData();
    formData.append('action', 'delete_product');
    formData.append('producto_id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert('Producto eliminado exitosamente');
                window.location.reload();
            } else {
                alert('Error al eliminar el producto: ' + (data.error || 'Error desconocido'));
            }
        } catch (e) {
            alert('Error de respuesta del servidor al eliminar');
        }
    })
    .catch(error => {
        alert('Error de conexión al eliminar el producto');
    })
    .finally(() => {
        const loading = document.getElementById('delete-loading');
        if (loading) {
            document.body.removeChild(loading);
        }
    });
}

// FUNCIÓN PARA EDITAR PRODUCTO (inventario interno)
function editProduct(id) {
    console.log('🔧 Intentando editar producto ID:', id);

    const formData = new FormData();
    formData.append('action', 'get_product');
    formData.append('producto_id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.producto) {
            const producto = data.producto;

            // Llenar el formulario
            document.getElementById('edit_producto_id').value = producto.id || '';
            document.getElementById('edit_nombre_producto').value = producto.nombre_producto || '';
            document.getElementById('edit_categoria').value = producto.categoria || '';
            document.getElementById('edit_sku').value = producto.sku || '';
            document.getElementById('edit_precio_venta').value = producto.precio_venta || '';
            document.getElementById('edit_costo').value = producto.costo || '';
            
            // Cargar importador si existe
            if (producto.importador_id) {
                document.getElementById('edit_importador_id').value = producto.importador_id;
                loadSKUsForEdit(producto.importador_id);
                
                // Seleccionar SKU después de cargar
                if (producto.sku_importador_id) {
                    setTimeout(() => {
                        const skuItems = document.querySelectorAll('#edit_sku_list .sku-item');
                        skuItems.forEach(item => {
                            // This is a bit hacky, ideally pass sku.id to onclick directly
                            if (item.onclick.toString().includes(`selectEditSKU(${producto.sku_importador_id}`)) {
                                item.click();
                            }
                        });
                    }, 1000); // Give time for SKUs to load
                }
            } else {
                // Reset importador selection if no importador_id
                document.getElementById('edit_importador_id').value = '';
                document.getElementById('edit_sku_list_container').style.display = 'none';
                document.getElementById('edit_selected_sku_info').classList.remove('show');
            }

            // Mostrar modal
            document.getElementById('editModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            alert('Error al cargar los datos del producto');
        }
    })
    .catch(error => {
        alert('Error de conexión al cargar el producto');
    });
}

// FUNCIÓN PARA CERRAR MODAL DE EDICIÓN DE PRODUCTO (inventario interno)
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto';

    // Limpiar selecciones
    selectedEditSKUId = null;
    document.getElementById('edit_sku_importador_id').value = '';
    document.getElementById('edit_selected_sku_info').classList.remove('show');
    document.getElementById('edit_sku_list_container').style.display = 'none';
}

// VERIFICAR SKU DUPLICADO (para inventario interno)
function checkSKUDuplicate(sku, currentId) {
    const warning = document.getElementById('sku_warning');
    if (!warning || !sku || sku.trim() === '') {
        if (warning) warning.style.display = 'none';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'check_sku');
    formData.append('sku', sku);
    formData.append('exclude_id', currentId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            warning.style.display = 'flex';
        } else {
            warning.style.display = 'none';
        }
    })
    .catch(error => {
        warning.style.display = 'none';
    });
}

// VERIFICAR SKU DUPLICADO EN NUEVO PRODUCTO (para inventario interno)
function checkNewSKUDuplicate(sku) {
    const warning = document.getElementById('new_sku_warning');
    if (!warning || !sku || sku.trim() === '') {
        if (warning) warning.style.display = 'none';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'check_sku');
    formData.append('sku', sku);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            warning.style.display = 'flex';
        } else {
            warning.style.display = 'none';
        }
    })
    .catch(error => {
        warning.style.display = 'none';
    });
}

// FUNCIONES PARA EDICIÓN DE SKU IMPORTADOR
function editSkuImportador(id) {
    console.log('🔧 Intentando editar SKU de importador ID:', id);

    const formData = new FormData();
    formData.append('action', 'get_sku_importador_data');
    formData.append('sku_importador_id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.sku_importador) {
            const sku_imp = data.sku_importador;

            // Llenar el formulario del modal de edición de SKU importador
            document.getElementById('edit_sku_importador_id_modal').value = sku_imp.id || '';
            document.getElementById('edit_sku_imp_importador_id').value = sku_imp.importador_id || '';
            document.getElementById('edit_sku_imp_codigo').value = sku_imp.sku_codigo || '';
            document.getElementById('edit_sku_imp_nombre_producto').value = sku_imp.nombre_producto || '';
            document.getElementById('edit_sku_imp_descripcion').value = sku_imp.descripcion || '';
            document.getElementById('edit_sku_imp_precio_importador').value = sku_imp.precio_importador || '';
            document.getElementById('edit_sku_imp_categoria').value = sku_imp.categoria || '';
            document.getElementById('edit_sku_imp_estado').value = sku_imp.estado || 'disponible';

            // Mostrar modal
            document.getElementById('editSkuImportadorModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            alert('Error al cargar los datos del SKU de importador: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        alert('Error de conexión al cargar el SKU de importador');
        console.error('Fetch error:', error);
    });
}

function closeEditSkuImportadorModal() {
    document.getElementById('editSkuImportadorModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// --- NUEVAS FUNCIONES JAVASCRIPT PARA IMPORTADORES ---

function addImportador() {
    // This function is not directly called, form submission is handled by event listener
}

function editImportador(id) {
    console.log('🔧 Intentando editar importador ID:', id);

    const formData = new FormData();
    formData.append('action', 'get_importador_data');
    formData.append('importador_id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.importador) {
            const importador = data.importador;

            // Llenar el formulario del modal de edición de importador
            document.getElementById('edit_importador_id_modal').value = importador.id || '';
            document.getElementById('edit_nombre_importador').value = importador.nombre_importador || '';
            document.getElementById('edit_codigo_importador').value = importador.codigo_importador || '';
            document.getElementById('edit_importador_estado').value = importador.estado || 'activo';

            // Mostrar modal
            document.getElementById('editImportadorModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            alert('Error al cargar los datos del importador: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        alert('Error de conexión al cargar el importador');
        console.error('Fetch error:', error);
    });
}

function deleteImportador(id) {
    console.log('🗑️ Intentando eliminar importador ID:', id);

    if (!confirm('¿Estás seguro de que deseas eliminar este importador? Esta acción no se puede deshacer.')) {
        return;
    }

    // Mostrar loading
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'delete-importador-loading';
    loadingDiv.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-spinner fa-spin" style="color: #ef4444;"></i>
                <span>Eliminando importador...</span>
            </div>
        </div>
    `;
    document.body.appendChild(loadingDiv);

    const formData = new FormData();
    formData.append('action', 'delete_importador');
    formData.append('importador_id', id);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error al eliminar el importador: ' + (data.error || 'Error desconocido'));
            }
        } catch (e) {
            alert('Error de respuesta del servidor al eliminar el importador');
        }
    })
    .catch(error => {
        alert('Error de conexión al eliminar el importador');
    })
    .finally(() => {
        const loading = document.getElementById('delete-importador-loading');
        if (loading) {
            document.body.removeChild(loading);
        }
    });
}

function closeEditImportadorModal() {
    document.getElementById('editImportadorModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// --- FIN NUEVAS FUNCIONES JAVASCRIPT PARA IMPORTADORES ---


// MANEJAR ENVÍO DEL FORMULARIO DE EDICIÓN DE PRODUCTO (inventario interno)
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Producto actualizado exitosamente');
                    closeEditModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert('Error al actualizar el producto: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                alert('Error de conexión al actualizar el producto');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // MANEJAR ENVÍO DEL FORMULARIO DE AGREGAR SKU IMPORTADOR
    const addSkuImportadorForm = document.getElementById('addSkuImportadorForm');
    if (addSkuImportadorForm) {
        addSkuImportadorForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Clear form fields
                    addSkuImportadorForm.reset();
                    // Reload page to show new SKU in list
                    window.location.reload(); 
                } else {
                    alert('Error al agregar SKU de importador: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                alert('Error de conexión al agregar SKU de importador');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // MANEJAR ENVÍO DEL FORMULARIO DE EDICIÓN DE SKU IMPORTADOR
    const editSkuImportadorForm = document.getElementById('editSkuImportadorForm');
    if (editSkuImportadorForm) {
        editSkuImportadorForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeEditSkuImportadorModal();
                    setTimeout(() => {
                        window.location.reload(); 
                    }, 500);
                } else {
                    alert('Error al actualizar SKU de importador: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                alert('Error de conexión al actualizar SKU de importador');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // MANEJAR ENVÍO DEL FORMULARIO DE AGREGAR IMPORTADOR
    const addImportadorForm = document.getElementById('addImportadorForm');
    if (addImportadorForm) {
        addImportadorForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    addImportadorForm.reset();
                    window.location.reload(); 
                } else {
                    alert('Error al agregar importador: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                alert('Error de conexión al agregar importador');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // MANEJAR ENVÍO DEL FORMULARIO DE EDICIÓN DE IMPORTADOR
    const editImportadorForm = document.getElementById('editImportadorForm');
    if (editImportadorForm) {
        editImportadorForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeEditImportadorModal();
                    setTimeout(() => {
                        window.location.reload(); 
                    }, 500);
                } else {
                    alert('Error al actualizar importador: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                alert('Error de conexión al actualizar importador');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // CAMBIAR ENTRE PESTAÑAS DEL FORMULARIO
    <?php if ($vista == 'nuevo'): ?>
    document.querySelectorAll('.form-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            
            this.classList.add('active');
            
            const tabText = this.textContent.toLowerCase();
            if (tabText.includes('general')) {
                document.getElementById('general').classList.add('active');
            } else if (tabText.includes('inventario')) {
                document.getElementById('inventario').classList.add('active');
            }
        });
    });
    
    // GENERAR SKU AUTOMÁTICO
    const nombreInput = document.getElementById('nombre_producto');
    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            const skuInput = document.getElementById('sku');
            if (skuInput && skuInput.value.trim() === '') {
                const nombre = this.value.toUpperCase().replace(/[^A-Za-z0-9]/g, '').substring(0, 6);
                if (nombre.length > 0) {
                    const random = Math.floor(Math.random() * 1000);
                    const suggestedSku = nombre + random.toString().padStart(3, '0');
                    skuInput.placeholder = `Sugerido: ${suggestedSku}`;
                }
            }
        });
    }
    <?php endif; ?>
    
    // CERRAR MODAL AL HACER CLIC FUERA
    window.onclick = function(event) {
        const editModal = document.getElementById('editModal');
        const editSkuImportadorModal = document.getElementById('editSkuImportadorModal');
        const editImportadorModal = document.getElementById('editImportadorModal'); // New modal
        
        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == editSkuImportadorModal) {
            closeEditSkuImportadorModal();
        }
        if (event.target == editImportadorModal) { // New modal close
            closeEditImportadorModal();
        }
    }
    
    console.log('🚀 Sistema de inventario con SKU de importadores y gestión de importadores cargado correctamente');
});
</script>
<style>
/* Basic styles for the new SKU Importadores table */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.data-table th, .data-table td {
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
    text-align: left;
    font-size: 14px;
}

.data-table th {
    background-color: #f0f0f0;
    font-weight: bold;
    color: #333;
}

.data-table tbody tr:nth-child(even) {
    background-color: #fcfcfc;
}

.data-table tbody tr:hover {
    background-color: #f5f5f5;
}

/* Styles for form sections */
.form-section {
    margin-bottom: 20px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background-color: #fff;
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    font-size: 1.2em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h3 .fas {
    color: #007bff; /* Primary color for icons */
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea,
.form-group select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 1em;
    width: 100%;
    box-sizing: border-box; /* Include padding in width */
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-primary, .btn-secondary {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* Specific styles for SKU Importador section */
.sku-importador-section {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background-color: #f9f9f9;
}

.sku-importador-section h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    font-size: 1.1em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sku-importador-section h4 .fas {
    color: #28a745; /* Green for shipping icon */
}

.sku-list-container {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    margin-top: 15px;
    background-color: #fff;
}

.sku-list {
    padding: 10px;
}

.sku-item {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.sku-item:last-child {
    border-bottom: none;
}

.sku-item:hover {
    background-color: #f0f8ff;
}

.sku-item.selected {
    background-color: #e0f2fe;
    border-left: 4px solid #007bff;
    padding-left: 11px;
}

.sku-item-header {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    margin-bottom: 5px;
}

.sku-code {
    color: #007bff;
}

.sku-price {
    color: #28a745;
}

.sku-name {
    font-size: 0.9em;
    color: #555;
}

.selected-sku-info {
    margin-top: 20px;
    padding: 15px;
    border: 1px solid #cce5ff;
    border-radius: 8px;
    background-color: #e0f2fe;
    color: #004085;
    display: none; /* Hidden by default */
    opacity: 0;
    transition: opacity 0.3s ease;
}

.selected-sku-info.show {
    display: block;
    opacity: 1;
}

.selected-sku-info h5 {
    margin-top: 0;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #004085;
}

.selected-sku-info h5 .fas {
    color: #28a745;
}

.loading-skus, .no-skus {
    text-align: center;
    padding: 20px;
    color: #777;
}

.loading-skus .fa-spinner {
    margin-right: 10px;
    color: #007bff;
}

/* General inventory styles (from original CSS, ensure consistency) */
.inventory-container {
    padding: 20px;
    background-color: #f4f7f6;
    min-height: calc(100vh - 60px); /* Adjust based on header height */
}

.inventory-header {
    display: flex;
    flex-direction: column;
    margin-bottom: 20px;
}

.inventory-header h2 {
    font-size: 1.8em;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.breadcrumb {
    font-size: 0.9em;
    color: #777;
    margin-bottom: 10px;
}

.breadcrumb a {
    color: #007bff;
    text-decoration: none;
}

.breadcrumb span {
    font-weight: bold;
}

.header-tabs {
    margin-bottom: 20px;
}

.tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
}

.tab {
    padding: 10px 15px;
    border: none;
    background-color: transparent;
    cursor: pointer;
    font-size: 1em;
    color: #555;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
    font-weight: bold;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.alert.success {
    background-color: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.alert.error {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.search-container {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.search-group, .category-group {
    flex: 1;
    min-width: 200px;
}

.search-input-container {
    position: relative;
}

.search-input {
    padding-left: 35px !important;
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.filter-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background-color: white;
}

.search-btn, .clear-btn {
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1em;
    transition: background-color 0.3s ease;
}

.search-btn {
    background-color: #007bff;
    color: white;
}

.search-btn:hover {
    background-color: #0056b3;
}

.clear-btn {
    background-color: #f0f0f0;
    color: #555;
}

.clear-btn:hover {
    background-color: #e0e0e0;
}

.results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background-color: #e9ecef;
    border-radius: 8px;
}

.results-count {
    font-weight: 500;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.product-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.product-image-container {
    width: 100%;
    height: 180px;
    background-color: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #bbb;
    font-size: 1.2em;
}

.no-image-placeholder .fas {
    font-size: 3em;
    margin-bottom: 10px;
}

.no-image-placeholder span {
    font-size: 0.9em;
}

.product-info {
    padding: 15px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.product-code {
    font-size: 0.85em;
    color: #777;
    background-color: #eee;
    padding: 3px 8px;
    border-radius: 4px;
}

.stock-badge {
    font-size: 0.8em;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.stock-available {
    background-color: #d4edda;
    color: #155724;
}

.stock-unavailable {
    background-color: #f8d7da;
    color: #721c24;
}

.product-name {
    font-size: 1.1em;
    font-weight: bold;
    color: #333;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-price {
    font-size: 1.3em;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 10px;
}

.product-category {
    font-size: 0.85em;
    color: #555;
    background-color: #f0f0f0;
    padding: 3px 8px;
    border-radius: 4px;
    align-self: flex-start;
    margin-bottom: 10px;
}

.product-actions {
    display: flex;
    gap: 10px;
    margin-top: auto; /* Pushes buttons to the bottom */
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.action-btn {
    flex: 1;
    padding: 8px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: background-color 0.3s ease;
}

.action-btn.edit {
    background-color: #ffc107;
    color: #333;
}

.action-btn.edit:hover {
    background-color: #e0a800;
}

.action-btn.delete {
    background-color: #dc3545;
    color: white;
}

.action-btn.delete:hover {
    background-color: #c82333;
}

.no-results {
    text-align: center;
    padding: 50px 20px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-top: 20px;
}

.no-results .fas {
    font-size: 4em;
    color: #ccc;
    margin-bottom: 20px;
}

.no-results h3 {
    color: #555;
    margin-bottom: 10px;
}

.no-results p {
    color: #777;
    margin-bottom: 20px;
}

.modal {
    display: flex;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 800px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5em;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 .fas {
    color: #007bff;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
}

.modal-body {
    padding-bottom: 20px;
}

.modal-footer {
    border-top: 1px solid #eee;
    padding-top: 15px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.sku-warning {
    display: none; /* Hidden by default */
    color: #dc3545;
    font-size: 0.85em;
    margin-top: 5px;
    align-items: center;
    gap: 5px;
}

.sku-warning .fas {
    color: #dc3545;
}

/* Styles for the new product form image upload */
.product-image {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.image-upload-area {
    width: 150px;
    height: 150px;
    border: 2px dashed #ccc;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background-color: #f9f9f9;
    transition: border-color 0.3s ease;
}

.image-upload-area:hover {
    border-color: #007bff;
}

.image-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #999;
}

.image-placeholder .fas {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.image-placeholder span {
    font-size: 0.9em;
}

.input-with-unit {
    display: flex;
    align-items: center;
    border: 1px solid #ccc;
    border-radius: 5px;
    overflow: hidden;
}

.input-with-unit .currency,
.input-with-unit .unit {
    padding: 10px;
    background-color: #f0f0f0;
    color: #555;
    font-size: 1em;
    white-space: nowrap;
}

.input-with-unit input {
    border: none;
    flex-grow: 1;
    padding: 10px;
    font-size: 1em;
}

.input-with-unit input:focus {
    outline: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .search-form, .form-row {
        flex-direction: column;
        gap: 10px;
    }

    .search-group, .category-group, .form-group {
        width: 100%;
        min-width: unset;
    }

    .results-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }

    .modal-content {
        width: 95%;
        padding: 15px;
    }
}

</style>
</body>
</html>
