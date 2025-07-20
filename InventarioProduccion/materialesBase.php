<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Inicia la sesión para verificar la autenticación

// MANEJAR PETICIONES AJAX PRIMERO (antes de cualquier HTML)
// Este archivo está diseñado para ser un endpoint de API, no una página HTML.
// Por lo tanto, si se accede directamente sin una acción AJAX, no debería mostrar nada.
if (isset($_GET['action']) || isset($_POST['action'])) {
    // Verificar autenticación para solicitudes AJAX
    // if (!isset($_SESSION['nombres'])) { // Descomenta esto en producción para habilitar la autenticación
    //     header('Content-Type: application/json');
    //     echo json_encode(['success' => false, 'message' => 'Sesión expirada. Por favor, inicia sesión nuevamente.']);
    //     exit();
    // }
    header('Content-Type: application/json; charset=utf-8');
    // Configuración de la base de datos
    $host = 'localhost';
    $dbname = 'sistema-erp-eless'; // Asegúrate de que este sea el nombre correcto de tu DB
    $username = 'root';
    $password = ''; // Tu contraseña de MySQL (vacío si no tienes)

    // Función para conectar a la base de datos usando mysqli
    function conectarDB()
    {
        global $host, $username, $password, $dbname;
        $conexion = new mysqli($host, $username, $password, $dbname);
        if ($conexion->connect_error) {
            // En un entorno de producción, no expongas el error directamente.
            // Loguea el error y muestra un mensaje genérico.
            throw new Exception('Error de conexión a la base de datos: ' . $conexion->connect_error);
        }
        $conexion->set_charset("utf8");
        return $conexion;
    }

    // Directorio para guardar imágenes
    $uploadDir = 'uploads/';
    // Asegurarse de que el directorio de subidas exista
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Permisos 0777 son muy permisivos, considera 0755 en producción
    }

    // Función para guardar una imagen Base64 (reutilizada)
    function saveBase64Image($base64_string, $uploadDir)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
            $data = substr($base64_string, strpos($base64_string, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpeg', 'jpg', 'gif', 'png'])) {
                throw new Exception('Tipo de imagen no soportado: ' . $type);
            }
            $data = base64_decode($data);
            if ($data === false) {
                throw new Exception('Decodificación Base64 fallida.');
            }
        } else {
            throw new Exception('Formato Base64 inválido.');
        }
        $filename = uniqid() . '.' . $type;
        $filepath = $uploadDir . $filename;
        file_put_contents($filepath, $data);
        return $filepath; // Retorna la ruta relativa para guardar en la DB
    }

    // Función para formatear un material con los tipos de datos correctos
    function formatMaterial($material) {
        if (!$material) return null;

        // Crear un nuevo array para el material formateado
        $formattedMaterial = [];

        // Asignar y convertir campos numéricos
        $formattedMaterial['id'] = (int) ($material['id'] ?? 0);
        $formattedMaterial['quantity'] = (int) ($material['quantity'] ?? 0);
        $formattedMaterial['purchasePrice'] = (float) ($material['purchase_price'] ?? 0.0); // Usa purchase_price de la DB
        $formattedMaterial['unitPrice'] = (float) ($material['unit_price'] ?? 0.0); // Usa unit_price de la DB
        $formattedMaterial['igv'] = (float) ($material['igv'] ?? 0.0);
        $formattedMaterial['weightKg'] = (float) ($material['weight_kg'] ?? 0.0); // Usa weight_kg de la DB
        $formattedMaterial['volumeM3'] = (float) ($material['volume_m3'] ?? 0.0); // Usa volume_m3 de la DB

        // Asignar campos de texto, asegurando que sean cadenas y tengan valores predeterminados
        $formattedMaterial['name'] = (string) ($material['name'] ?? '');
        $formattedMaterial['sku'] = (string) ($material['sku'] ?? '');
        $formattedMaterial['barcode'] = (string) ($material['barcode'] ?? '');
        $formattedMaterial['responsible'] = (string) ($material['responsible'] ?? '');
        $formattedMaterial['supplier'] = (string) ($material['supplier'] ?? ''); // Nuevo campo: supplier
        $formattedMaterial['location'] = (string) ($material['location'] ?? '');
        $formattedMaterial['photo'] = (string) ($material['photo'] ?? 'placeholder.svg'); // CAMBIO AQUÍ: Ruta relativa

        return $formattedMaterial;
    }

    // Obtener la acción de la solicitud
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'obtener_materiales':
                obtenerMateriales();
                break;
            case 'obtener_material':
                obtenerMaterial();
                break;
            case 'crear_material':
                crearMaterial();
                break;
            case 'editar_material':
                editarMaterial();
                break;
            case 'eliminar_material':
                eliminarMaterial();
                break;
            default:
                throw new Exception('Acción no válida.');
        }
    } catch (Exception $e) {
        // Captura cualquier excepción y devuelve un error JSON
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit(); // Termina la ejecución después de manejar la solicitud AJAX
}
// Si el archivo se accede directamente sin una acción AJAX, no hace nada o redirige.
// En un archivo de API puro, simplemente se sale.
// Si este archivo también sirve una página HTML, la lógica de redirección iría aquí.
// Por ahora, asumimos que es solo un endpoint de API.
?>
<?php
// --- Funciones CRUD para Materiales ---
function obtenerMateriales(){
    $conexion = conectarDB();
    $searchTerm = isset($_GET['search']) ? '%' . $conexion->real_escape_string(strtolower($_GET['search'])) . '%' : '%';
    $sql = "SELECT * FROM materials WHERE LOWER(name) LIKE ? OR LOWER(sku) LIKE ? OR barcode LIKE ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = formatMaterial($row); // Formatear cada material
    }
    $stmt->close();
    $conexion->close();
    echo json_encode(['success' => true, 'data' => $materials]);
}

function obtenerMaterial(){
    $conexion = conectarDB();
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('ID de material no proporcionado.');
    }
    $sql = "SELECT * FROM materials WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    $stmt->close();
    $conexion->close();
    if ($material) {
        echo json_encode(['success' => true, 'data' => formatMaterial($material)]); // Formatear el material
    } else {
        throw new Exception('Material no encontrado.');
    }
}

function crearMaterial(){
    global $uploadDir;
    $conexion = conectarDB();
    $input = json_decode(file_get_contents('php://input'), true);

    // error_log("DEBUG: crearMaterial - Input data: " . print_r($input, true)); // Ya la tenías, asegúrate que no esté comentada
    error_log("DEBUG: crearMaterial - Supplier value: " . ($input['supplier'] ?? 'NOT SET')); // Añade esta línea

    $photoPath = 'placeholder.svg'; // CAMBIO AQUÍ: Ruta relativa
    if (isset($input['photo']) && str_starts_with($input['photo'], 'data:image')) {
        $photoPath = saveBase64Image($input['photo'], $uploadDir);
    }

    // Añadir 'supplier' a la consulta INSERT
    $sql = "INSERT INTO materials (photo, name, sku, quantity, purchase_price, unit_price, igv, barcode, responsible, supplier, weight_kg, volume_m3, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    // Actualizar la cadena bind_param: añadir 's' para supplier
    $stmt->bind_param(
        "sssiiddssdsds", // 13 parámetros: photo, name, sku, quantity, purchasePrice, unitPrice, igv, barcode, responsible, supplier, weightKg, volumeM3, location
        $photoPath,
        $input['name'],
        $input['sku'],
        $input['quantity'],
        $input['purchasePrice'],
        $input['unitPrice'],
        $input['igv'],
        $input['barcode'],
        $input['responsible'],
        $input['supplier'], // Añadir supplier aquí
        $input['weightKg'],
        $input['volumeM3'],
        $input['location']
    );
    if ($stmt->execute()) {
        $newId = $conexion->insert_id;
        $stmt->close();
        $conexion->close();
        // Devolver el material recién creado con el ID y los tipos correctos
        $input['id'] = $newId;
        $input['photo'] = $photoPath; // Asegurarse de que la ruta de la foto sea la correcta
        echo json_encode(['success' => true, 'message' => 'Material creado con éxito', 'id' => $newId, 'data' => formatMaterial($input)]);
    } else {
        $stmt->close();
        $conexion->close();
        throw new Exception('Error al crear material: ' . $stmt->error);
    }
}

function editarMaterial(){
    global $uploadDir;
    $conexion = conectarDB();
    $id = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);

    // --- ELIMINAR ESTAS LÍNEAS DE DEPURACIÓN DESPUÉS DE CONFIRMAR QUE FUNCIONA ---
    // error_log("DEBUG: editarMaterial - ID: " . $id);
    // error_log("DEBUG: editarMaterial - Input data: " . print_r($input, true));
    // --- FIN DE LÍNEAS DE DEPURACIÓN ---

    // error_log("DEBUG: editarMaterial - Input data: " . print_r($input, true)); // Ya la tenías, asegúrate que no esté comentada
    error_log("DEBUG: editarMaterial - Supplier value: " . ($input['supplier'] ?? 'NOT SET')); // Añade esta línea

    // Obtener la ruta de la foto actual para posible eliminación
    $stmt_photo = $conexion->prepare("SELECT photo FROM materials WHERE id = ?");
    $stmt_photo->bind_param("i", $id);
    $stmt_photo->execute();
    $result_photo = $stmt_photo->get_result();
    $currentPhoto = $result_photo->fetch_assoc()['photo'] ?? null;
    $stmt_photo->close();

    $photoPath = $currentPhoto; // Por defecto, mantener la foto existente
    if (isset($input['photo']) && str_starts_with($input['photo'], 'data:image')) {
        // Nueva imagen Base64 subida
        if ($currentPhoto && $currentPhoto !== 'placeholder.svg' && file_exists($currentPhoto)) { // CAMBIO AQUÍ: Ruta relativa
            unlink($currentPhoto); // Eliminar archivo antiguo
        }
        $photoPath = saveBase64Image($input['photo'], $uploadDir);
    } else if (isset($input['photo']) && empty($input['photo'])) {
        // La foto se vació explícitamente (ej. el usuario la eliminó del formulario)
        if ($currentPhoto && $currentPhoto !== 'placeholder.svg' && file_exists($currentPhoto)) { // CAMBIO AQUÍ: Ruta relativa
            unlink($currentPhoto); // Eliminar archivo antiguo
        }
        $photoPath = 'placeholder.svg'; // CAMBIO AQUÍ: Ruta relativa
    }
    // Si $input['photo'] no está seteado o es una URL existente, $photoPath permanece $currentPhoto

    // Añadir 'supplier' a la consulta UPDATE
    $sql = "UPDATE materials SET photo = ?, name = ?, sku = ?, quantity = ?, purchase_price = ?, unit_price = ?, igv = ?, barcode = ?, responsible = ?, supplier = ?, weight_kg = ?, volume_m3 = ?, location = ? WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    // CORRECCIÓN AQUÍ: Cambiado 's' por 'd' para volume_m3
    $stmt->bind_param(
        "sssiiddssdsdsi", // 14 parámetros: photo, name, sku, quantity, purchasePrice, unitPrice, igv, barcode, responsible, supplier, weightKg, volumeM3, location, id
        $photoPath,
        $input['name'],
        $input['sku'],
        $input['quantity'],
        $input['purchasePrice'],
        $input['unitPrice'],
        $input['igv'],
        $input['barcode'],
        $input['responsible'],
        $input['supplier'], // Añadir supplier aquí
        $input['weightKg'],
        $input['volumeM3'], // Ahora se vincula como double
        $input['location'],
        $id
    );
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        $conexion->close();
        if ($affectedRows > 0) {
            // Devolver el material actualizado con los tipos correctos
            $input['id'] = $id;
            $input['photo'] = $photoPath; // Asegurarse de que la ruta de la foto sea la correcta
            echo json_encode(['success' => true, 'message' => 'Material actualizado con éxito', 'data' => formatMaterial($input)]);
        } else {
            // Si affected_rows es 0, significa que no se hicieron cambios reales en la fila.
            // Esto no es un error desde la perspectiva del usuario, sino un mensaje informativo.
            echo json_encode(['success' => true, 'message' => 'No se detectaron cambios en el material.', 'data' => formatMaterial($input)]);
        }
    } else {
        $stmt->close();
        $conexion->close();
        throw new Exception('Error al actualizar material: ' . $stmt->error);
    }
}

function eliminarMaterial(){
    global $uploadDir;
    $conexion = conectarDB();
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('ID de material no proporcionado para eliminar.');
    }
    // Obtener la ruta de la foto para eliminar el archivo físico
    $stmt_photo = $conexion->prepare("SELECT photo FROM materials WHERE id = ?");
    $stmt_photo->bind_param("i", $id);
    $stmt_photo->execute();
    $result_photo = $stmt_photo->get_result();
    $photoToDelete = $result_photo->fetch_assoc()['photo'] ?? null;
    $stmt_photo->close();

    $sql = "DELETE FROM materials WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        $conexion->close();
        if ($affectedRows > 0) {
            // Eliminar el archivo de imagen asociado si existe y no es el placeholder
            if ($photoToDelete && $photoToDelete !== 'placeholder.svg' && file_exists($photoToDelete)) { // CAMBIO AQUÍ: Ruta relativa
                unlink($photoToDelete);
            }
            echo json_encode(['success' => true, 'message' => 'Material eliminado con éxito.']);
        } else {
            throw new Exception('Material no encontrado para eliminar.');
        }
    } else {
        $stmt->close();
        $conexion->close();
        throw new Exception('Error al eliminar material: ' . $stmt->error);
    }
}
?>
