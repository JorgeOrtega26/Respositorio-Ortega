<?php
// Este bloque PHP se ejecuta primero.
// Si hay una acción solicitada por JavaScript, se procesa y se sale del script.
// Si no hay acción, el script continúa para renderizar el HTML.

session_start(); // Start session for authentication

// --- Configuración de la base de datos (AJUSTA ESTOS VALORES) ---
$servername = "localhost";
$username = "root"; // <--- ¡CONFIRMA ESTE USUARIO!
$password = "";     // <--- ¡CONFIRMA ESTA CONTRASEÑA! (A menudo vacía en XAMPP/WAMP por defecto)
$dbname = "sistema-erp-eless"; // <--- ¡CONFIRMA ESTE NOMBRE DE BASE DE DATOS!

// Función para conectar a la base de datos
function conectarDB()
{
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}

// Obtener la acción solicitada (puede ser GET o POST)
$action = $_REQUEST['action'] ?? ''; // Usa $_REQUEST para obtener de GET o POST

// Si se ha solicitado una acción de API, la manejamos y salimos
if (!empty($action)) {
    header('Content-Type: application/json'); // Aseguramos que la respuesta sea JSON

    // Verificar autenticación para AJAX requests
    // Comentar la redirección de autenticación para depuración
    // if (!isset($_SESSION['nombres'])) {
    //     echo json_encode(["success" => false, "error" => "Sesión expirada. Por favor, inicie sesión nuevamente."]);
    //     exit();
    // }

    try {
        $conn = conectarDB(); // Establish connection for API actions
        switch ($action) {
            case 'get_folders':
                handleGetFolders($conn);
                break;
            case 'create_folder':
                handleCreateFolder($conn);
                break;
            case 'edit_folder':
                handleEditFolder($conn);
                break;
            case 'delete_folder':
                handleDeleteFolder($conn);
                break;
            case 'get_files':
                handleGetFiles($conn);
                break;
            case 'upload_file':
                handleUploadFile($conn);
                break;
            case 'edit_file':
                handleEditFile($conn);
                break;
            case 'delete_file':
                handleDeleteFile($conn);
                break;
            case 'get_file_content': // Nuevo endpoint para obtener contenido de archivo
                handleGetFileContent($conn);
                break;
            case 'get_file_content': // Nuevo endpoint para obtener contenido de archivo
                handleGetFileContent($conn);
                break;
            case 'get_trash_files': // NUEVO: Obtener archivos de la papelera
                handleGetTrashFiles($conn);
                break;
            case 'restore_file': // NUEVO: Restaurar archivo
                handleRestoreFile($conn);
                break;
            case 'permanent_delete_file': // NUEVO: Eliminar archivo permanentemente
                handlePermanentDeleteFile($conn);
                break;
            default:
                echo json_encode(["success" => false, "error" => "Acción no válida."]);
                break;
        }
        $conn->close(); // Cierra la conexión antes de salir
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit(); // ¡IMPORTANTE! Detiene la ejecución del script para no enviar el HTML
}

// --- Funciones para manejar cada acción (estas funciones solo se llaman si $action está presente) ---

function handleGetFolders($conn)
{
    $sql = "SELECT id, name, parent_id FROM folders";
    $result = $conn->query($sql);

    $folders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (empty($row['parent_id'])) {
                $row['parent_id'] = null;
            }
            $folders[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $folders]);
}

function handleCreateFolder($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? uniqid('folder_'); // Genera un ID único si no se proporciona
    $name = $input['name'] ?? '';
    $parentId = $input['parentId'] ?? null;

    if (empty($name)) {
        echo json_encode(["success" => false, "error" => "El nombre de la carpeta no puede estar vacío."]);
        return;
    }

    // Prepara la consulta para evitar inyección SQL
    $stmt = $conn->prepare("INSERT INTO folders (id, name, parent_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $id, $name, $parentId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Carpeta creada exitosamente.", "folder" => ["id" => $id, "name" => $name, "parentId" => $parentId]]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al crear carpeta: " . $stmt->error]);
    }
    $stmt->close();
}

function handleEditFolder($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $newName = $input['name'] ?? '';

    if (empty($id) || empty($newName)) {
        echo json_encode(["success" => false, "error" => "ID y nuevo nombre de carpeta son requeridos."]);
        return;
    }

    $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ?");
    $stmt->bind_param("ss", $newName, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Carpeta actualizada exitosamente."]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al actualizar carpeta: " . $stmt->error]);
    }
    $stmt->close();
}

function handleDeleteFolder($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';

    if (empty($id)) {
        echo json_encode(["success" => false, "error" => "ID de carpeta requerido."]);
        return;
    }

    // Recursively delete files and subfolders
    function deleteFolderContents($conn, $folderId)
    {
        // Delete files in this folder
        $stmt_files = $conn->prepare("SELECT type, content FROM files WHERE folder_id = ?");
        $stmt_files->bind_param("s", $folderId);
        $stmt_files->execute();
        $result_files = $stmt_files->get_result();
        while ($row = $result_files->fetch_assoc()) {
            if ($row['type'] !== 'txt') { // Only delete physical file if it's not a text file
                $filePath = 'public/uploads/documents/' . $row['content'];
                if (file_exists($filePath) && !is_dir($filePath)) {
                    unlink($filePath);
                }
            }
        }
        $stmt_files->close();

        $stmt_delete_files = $conn->prepare("DELETE FROM files WHERE folder_id = ?");
        $stmt_delete_files->bind_param("s", $folderId);
        $stmt_delete_files->execute();
        $stmt_delete_files->close();

        // Get subfolders and recursively delete their contents
        $stmt_subfolders = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?");
        $stmt_subfolders->bind_param("s", $folderId);
        $stmt_subfolders->execute();
        $result_subfolders = $stmt_subfolders->get_result();
        while ($row = $result_subfolders->fetch_assoc()) {
            deleteFolderContents($conn, $row['id']);
        }
        $stmt_subfolders->close();

        // Finally, delete the folder itself
        $stmt_delete_folder = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt_delete_folder->bind_param("s", $folderId);
        $stmt_delete_folder->execute();
        $stmt_delete_folder->close();
    }

    try {
        $conn->begin_transaction();
        deleteFolderContents($conn, $id);
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Carpeta y su contenido eliminados exitosamente."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["success" => false, "error" => "Error al eliminar carpeta: " . $e->getMessage()]);
    }
}

//muestra archivos activos
function handleGetFiles($conn)
{
    // Solo selecciona archivos que NO están marcados como eliminados
    $sql = "SELECT id, name, type, size, uploaded_at, folder_id, content FROM files WHERE is_deleted = FALSE";
    $result = $conn->query($sql);

    $files = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $files]);
}

function handleUploadFile($conn)
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["success" => false, "error" => "Método no permitido."]);
        return;
    }

    $name = trim($_POST['name'] ?? '');
    $folderId = trim($_POST['folderId'] ?? '');
    $file = $_FILES['file'] ?? null;

    if (empty($name) || empty($folderId) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "error" => "Datos incompletos o error de subida de archivo."]);
        return;
    }

    $upload_dir = 'public/uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(["success" => false, "error" => "Tipo de archivo no permitido."]);
        return;
    }

    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        echo json_encode(["success" => false, "error" => "El archivo es demasiado grande (máx. 10MB)."]);
        return;
    }

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('file_') . '.' . strtolower($file_extension);
    $upload_path = $upload_dir . $unique_filename;

    $id = uniqid('file_');
    $type = strtolower($file_extension);
    $size = round($file['size'] / (1024 * 1024), 2) . ' MB';
    $uploaded_at = date('Y-m-d');
    $content_to_store = null; // Default to null

    // If it's a text file, read its content directly and don't save physical file
    if ($type === 'txt') {
        $content_to_store = file_get_contents($file['tmp_name']);
    } else {
        // For other file types, move the uploaded file and store its unique filename
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo json_encode(["success" => false, "error" => "Error al mover el archivo subido."]);
            return;
        }
        $content_to_store = $unique_filename;
    }

    //Esto garantiza que todos los archivos nuevos no estén en la papelera por defecto.
    $stmt = $conn->prepare("INSERT INTO files (id, name, type, size, uploaded_at, folder_id, content, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, FALSE)");
    $stmt->bind_param("sssssss", $id, $name, $type, $size, $uploaded_at, $folderId, $content_to_store);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Archivo subido exitosamente.", "file" => ["id" => $id, "name" => $name, "type" => $type, "size" => $size, "uploaded_at" => $uploaded_at, "folder_id" => $folderId, "content" => $content_to_store]]);
    } else {
        // If DB insert fails and it's a non-text file, delete the uploaded file
        if ($type !== 'txt' && file_exists($upload_path)) {
            unlink($upload_path);
        }
        echo json_encode(["success" => false, "error" => "Error al guardar archivo en la base de datos: " . $stmt->error]);
    }
    $stmt->close();
}

function handleEditFile($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $newName = $input['name'] ?? '';
    $newContent = $input['content'] ?? null; // For text files

    if (empty($id) || empty($newName)) {
        echo json_encode(["success" => false, "error" => "ID y nuevo nombre de archivo son requeridos."]);
        return;
    }

    // Check file type to determine if content needs to be updated
    $stmt_type = $conn->prepare("SELECT type FROM files WHERE id = ?");
    $stmt_type->bind_param("s", $id);
    $stmt_type->execute();
    $result_type = $stmt_type->get_result();
    $file_data = $result_type->fetch_assoc();
    $stmt_type->close();

    if (!$file_data) {
        echo json_encode(["success" => false, "error" => "Archivo no encontrado."]);
        return;
    }

    $file_type = $file_data['type'];

    if ($file_type === 'txt' && $newContent !== null) {
        $stmt = $conn->prepare("UPDATE files SET name = ?, content = ? WHERE id = ?");
        $stmt->bind_param("sss", $newName, $newContent, $id);
    } else {
        $stmt = $conn->prepare("UPDATE files SET name = ? WHERE id = ?");
        $stmt->bind_param("ss", $newName, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Archivo actualizado exitosamente."]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al actualizar archivo: " . $stmt->error]);
    }
    $stmt->close();
}

function handleDeleteFile($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';

    if (empty($id)) {
        echo json_encode(["success" => false, "error" => "ID de archivo requerido."]);
        return;
    }
    // Realiza un "soft delete" actualizando la columna is_deleted
    $stmt = $conn->prepare("UPDATE files SET is_deleted = TRUE WHERE id = ?");
    $stmt->bind_param("s", $id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Archivo enviado a la papelera exitosamente."]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al enviar archivo a la papelera: " . $stmt->error]);
    }
    $stmt->close();
}

function handleGetFileContent($conn)
{
    $fileId = $_GET['id'] ?? '';

    if (empty($fileId)) {
        echo json_encode(["success" => false, "error" => "ID de archivo requerido."]);
        return;
    }

    $stmt = $conn->prepare("SELECT content, type FROM files WHERE id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    $stmt->close();

    if ($file) {
        if ($file['type'] === 'txt') {
            echo json_encode(["success" => true, "content" => $file['content']]);
        } else {
            // For non-text files, return the path to the file
            echo json_encode(["success" => true, "content_url" => 'public/uploads/documents/' . $file['content']]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Archivo no encontrado."]);
    }
}

// --- NUEVAS FUNCIONES PARA LA PAPELERA ---

// Función para obtener archivos de la papelera (is_deleted = TRUE)
function handleGetTrashFiles($conn)
{
    $sql = "SELECT id, name, type, size, uploaded_at, folder_id, content FROM files WHERE is_deleted = TRUE";
    $result = $conn->query($sql);

    $files = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $files]);
}

// Función para restaurar un archivo de la papelera (is_deleted = FALSE)
function handleRestoreFile($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';

    if (empty($id)) {
        echo json_encode(["success" => false, "error" => "ID de archivo requerido para restaurar."]);
        return;
    }

    $stmt = $conn->prepare("UPDATE files SET is_deleted = FALSE WHERE id = ?");
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Archivo restaurado exitosamente."]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al restaurar archivo: " . $stmt->error]);
    }
    $stmt->close();
}

// Función para eliminar un archivo permanentemente (hard delete)
function handlePermanentDeleteFile($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';

    if (empty($id)) {
        echo json_encode(["success" => false, "error" => "ID de archivo requerido para eliminación permanente."]);
        return;
    }

    // Obtener la ruta del archivo físico antes de eliminar el registro de la DB
    $stmt_select = $conn->prepare("SELECT type, content FROM files WHERE id = ?");
    $stmt_select->bind_param("s", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $file_data = $result->fetch_assoc();
    $stmt_select->close();

    if (!$file_data) {
        echo json_encode(["success" => false, "error" => "Archivo no encontrado para eliminación permanente."]);
        return;
    }

    $file_type = $file_data['type'];
    $file_content_or_path = $file_data['content'];

    // Eliminar el registro de la base de datos
    $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
    $stmt_delete->bind_param("s", $id);

    if ($stmt_delete->execute()) {
        // Si no es un archivo de texto, elimina el archivo físico del disco
        if ($file_type !== 'txt' && !empty($file_content_or_path)) {
            $filePath = 'public/uploads/documents/' . $file_content_or_path;
            if (file_exists($filePath) && !is_dir($filePath)) {
                unlink($filePath); // Elimina el archivo físico
            }
        }
        echo json_encode(["success" => true, "message" => "Archivo eliminado permanentemente."]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al eliminar archivo permanentemente: " . $stmt_delete->error]);
    }
    $stmt_delete->close();
}




// Verificar autenticación para la página HTML
// Comentar la redirección de autenticación para depuración
// if (!isset($_SESSION['nombres'])) {
//   header("Location: inicio-Sesion.php"); // Redirect to login page if not authenticated
//   exit();
// }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/siderbarycabezal.css">
    <link rel="stylesheet" href="./css/docustyle.css">
    <title>Documentos</title>
</head>

<body>
    <?php include 'sidebar.php' ?>
    <div class="main">
        <?php include 'header.php' ?>
        <!-- Contenido del Módulo de Documentos -->
        <div id="documents-module-container" class="documents-module-container">
            <!-- Left Sidebar for Folder Navigation -->
            <aside class="documents-sidebar">
                <h2 class="mb-4 text-lg font-semibold">Carpetas</h2>
                <div class="scroll-area">
                    <ul>
                        <!-- Folder tree will be rendered here by JavaScript -->
                    </ul>
                    <!-- "Otros" section will be rendered here by JavaScript -->
                </div>
            </aside>
            <!-- Main Content Area -->
            <main class="documents-main-content">
                <!-- Top Bar -->
                <header class="documents-header">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">Documentos</h1>
                    </div>
                    <div class="actions-group">
                        <div class="dropdown-menu-container">
                            <button class="action-button">
                                <span class="h-4 w-4"><?php echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus"><path d="M12 5v14"/><path d="M5 12h14"/></svg>'; ?></span>
                                <span>Nuevo</span>
                            </button>
                            <div class="dropdown-menu-content hidden">
                                <button class="dropdown-menu-item" data-action="new-folder">
                                    <span class="mr-2 h-4 w-4"><?php echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 8 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>'; ?></span> Nueva Carpeta
                                </button>
                                <button class="dropdown-menu-item" data-action="new-principal-folder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder">
                                        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 8 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                                    </svg>
                                    Nueva Carpeta Principal
                                </button>
                                <button class="dropdown-menu-item" data-action="upload-file">
                                    <span class="mr-2 h-4 w-4"><?php echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>'; ?></span> Subir Archivo
                                </button>
                            </div>
                        </div>
                        <div class="separator vertical mx-2 h-6"></div>
                        <div class="toggle-group" role="group" aria-label="Cambiar vista">
                            <button class="toggle-group-item" value="kanban" aria-pressed="true">
                                <span class="h-5 w-5"><?php echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-grid"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>'; ?></span>
                            </button>
                            <button class="toggle-group-item" value="list" aria-pressed="false">
                                <span class="h-5 w-5"><?php echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>'; ?></span>
                            </button>
                        </div>
                        <div class="separator vertical mx-2 h-6"></div>

                    </div>
                </header>
                <!-- Content Area (Folders and Files) -->
                <div class="documents-content-area">
                    <!-- Content will be rendered here by JavaScript -->
                </div>
            </main>
            <!-- Create Folder Dialog -->
            <div id="create-folder-dialog" class="dialog-overlay hidden">
                <div class="dialog-content">
                    <div class="dialog-header">
                        <h3 class="dialog-title">Crear Nueva Carpeta</h3>
                        <p class="dialog-description">Introduce el nombre de la nueva carpeta.</p>
                    </div>
                    <div class="dialog-body">
                        <div class="dialog-form-group">
                            <label for="folderName">Nombre</label>
                            <input id="folderName" type="text" class="col-span-3" />
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button class="button outline">Cancelar</button>
                        <button class="button default">Crear</button>
                    </div>
                </div>
            </div>
            <!-- Upload File Dialog -->
            <div id="upload-file-dialog" class="dialog-overlay hidden">
                <div class="dialog-content">
                    <div class="dialog-header">
                        <h3 class="dialog-title">Subir Archivo</h3>
                        <p class="dialog-description">Selecciona un archivo para subir a la carpeta actual.</p>
                    </div>
                    <div class="dialog-body">
                        <div class="dialog-form-group">
                            <label for="uploadFileName">Nombre del Archivo</label>
                            <input id="uploadFileName" type="text" class="col-span-3" placeholder="Nombre del archivo (opcional)" />
                        </div>
                        <div class="dialog-form-group">
                            <label for="file">Archivo</label>
                            <input id="file" type="file" class="col-span-3" />
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button class="button outline">Cancelar</button>
                        <button class="button default" disabled>Subir</button>
                    </div>
                </div>
            </div>
            <!-- Generic Edit Dialog (for folders and files) -->
            <div id="edit-dialog" class="dialog-overlay hidden">
                <div class="dialog-content">
                    <div class="dialog-header">
                        <h3 class="dialog-title">Editar Elemento</h3>
                        <p class="dialog-description">Cambia el nombre del elemento.</p>
                    </div>
                    <div class="dialog-body">
                        <div class="dialog-form-group">
                            <label for="editName">Nombre</label>
                            <input id="editName" type="text" class="col-span-3" />
                        </div>
                        <div class="dialog-form-group hidden" id="file-content-group">
                            <label for="editContent">Contenido</label>
                            <textarea id="editContent" class="col-span-3" rows="10"></textarea>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button class="button outline">Cancelar</button>
                        <button class="button default">Guardar Cambios</button>
                    </div>
                </div>
            </div>
            <!-- Generic Delete Dialog (for folders and files) -->
            <div id="delete-dialog" class="dialog-overlay hidden">
                <div class="dialog-content">
                    <div class="dialog-header">
                        <h3 class="dialog-title">Eliminar Elemento</h3>
                        <p class="dialog-description">¿Estás seguro de que quieres eliminar este elemento? Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="dialog-footer">
                        <button class="button outline">Cancelar</button>
                        <button class="button destructive">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- NEW: File Preview Dialog -->
    <div id="file-preview-dialog" class="dialog-overlay hidden">
        <div class="dialog-content">
            <div class="dialog-header">
                <h3 class="dialog-title">Vista Previa del Archivo</h3>
                <button class="icon-button file-preview-close-button" aria-label="Cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
            <div class="dialog-body">
                <div id="file-preview-content" class="text-center py-4">
                    <!-- File preview content will be loaded here by JavaScript -->
                </div>
                <p class="dialog-description text-sm text-gray-500 dark:text-gray-400 mt-4" id="file-preview-info"></p>
            </div>
            <div class="dialog-footer">
                <button class="button outline file-preview-close-button">Cerrar</button>
            </div>
        </div>
    </div>
    <div id="trash-dialog" class="dialog-overlay hidden">
    <div class="dialog-content">
        <div class="dialog-header">
            <h3 class="dialog-title">Papelera</h3>
            <button class="icon-button trash-close-button" aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="dialog-body">
            <div id="trash-content" class="text-center py-4">
                <!-- Trash content will be loaded here by JavaScript -->
            </div>
        </div>
        <div class="dialog-footer">
            <button class="button outline trash-close-button">Cerrar</button>
        </div>
    </div>
</div>
    <!-- Nuevo JavaScript para el módulo de documentos -->
    <script>
        // Asegúrate de que esta URL coincida con la ruta de tu archivo PHP
        const API_BASE_URL = './Documento.php';
        const UPLOADS_BASE_URL = './public/uploads/documents/';
    </script>
    <script src="./js/documentos.js"></script>
</body>

</html>