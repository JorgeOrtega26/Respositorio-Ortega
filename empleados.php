<?php
session_start();
if (!isset($_SESSION['nombres'], $_SESSION['apellidos'])) {
    header('Location: inicio-Sesion.php');
    exit;
}

// ========================================
// 🔄 CONEXIÓN A ASTRA DB via API Flask
// ========================================
// Configura la URL base de tu API Flask
$api_base_url = "http://localhost:5000";

// Función para hacer POST (insertar/actualizar)
function api_post($endpoint, $data) {
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents("http://localhost:5000/$endpoint", false, $context);
    if ($result === FALSE) {
        return ['success' => false, 'error' => 'Error de conexión con la API'];
    }
    return json_decode($result, true);
}

// Función para hacer GET (consultar)
function api_get($endpoint) {
    $options = [
        'http' => [
            'method' => 'GET'
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents("http://localhost:5000/$endpoint", false, $context);
    if ($result === FALSE) {
        return ['success' => false, 'error' => 'Error de conexión con la API'];
    }
    return json_decode($result, true);
}

// Función para hacer PUT (actualizar)
function api_put($endpoint, $data) {
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'PUT',
            'content' => json_encode($data),
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents("http://localhost:5000/$endpoint", false, $context);
    if ($result === FALSE) {
        return ['success' => false, 'error' => 'Error de conexión con la API'];
    }
    return json_decode($result, true);
}

// Función para hacer DELETE (eliminar)
function api_delete($endpoint) {
    $options = [
        'http' => [
            'method' => 'DELETE'
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents("http://localhost:5000/$endpoint", false, $context);
    if ($result === FALSE) {
        return ['success' => false, 'error' => 'Error de conexión con la API'];
    }
    return json_decode($result, true);
}

// PROCESAR ACCIONES AJAX
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'obtener_empleados':
            try {
                $response = api_get('empleados');
                if ($response && isset($response['success']) && $response['success']) {
                    echo json_encode(['success' => true, 'empleados' => $response['data'] ?? []]);
                } else {
                    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Error al obtener empleados']);
                }
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'crear_empleado':
            try {
                $data = [
                    'nombre' => $_POST['nombre'] ?? '',
                    'apellidos' => $_POST['apellidos'] ?? '',
                    'email_trabajo' => $_POST['email_trabajo'] ?? '',
                    'telefono_laboral' => $_POST['telefono_laboral'] ?? '',
                    'departamento' => $_POST['departamento'] ?? '',
                    'puesto' => $_POST['puesto'] ?? '',
                    'genero' => $_POST['genero'] ?? '',
                    'estado' => $_POST['estado'] ?? 'activo'
                ];
                
                $response = api_post('empleados', $data);
                if ($response && isset($response['success']) && $response['success']) {
                    echo json_encode(['success' => true, 'message' => 'Empleado creado exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Error al crear empleado']);
                }
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'editar_empleado':
            try {
                $empleado_nombre = $_POST['empleado_nombre'] ?? '';
                if (empty($empleado_nombre)) {
                    echo json_encode(['success' => false, 'error' => 'Nombre de empleado requerido']);
                    exit;
                }
                
                $data = [
                    'apellidos' => $_POST['apellidos'] ?? '',
                    'email_trabajo' => $_POST['email_trabajo'] ?? '',
                    'telefono_laboral' => $_POST['telefono_laboral'] ?? '',
                    'departamento' => $_POST['departamento'] ?? '',
                    'puesto' => $_POST['puesto'] ?? '',
                    'genero' => $_POST['genero'] ?? '',
                    'estado' => $_POST['estado'] ?? 'activo'
                ];
                
                $response = api_put("empleados/" . urlencode($empleado_nombre), $data);
                if ($response && isset($response['success']) && $response['success']) {
                    echo json_encode(['success' => true, 'message' => 'Empleado actualizado exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Error al actualizar empleado']);
                }
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'obtener_empleado':
            try {
                $empleado_nombre = $_POST['empleado_nombre'] ?? '';
                if (empty($empleado_nombre)) {
                    echo json_encode(['success' => false, 'error' => 'Nombre de empleado requerido']);
                    exit;
                }
                
                $response = api_get("empleados/" . urlencode($empleado_nombre));
                if ($response && isset($response['success']) && $response['success']) {
                    echo json_encode(['success' => true, 'empleado' => $response['data'] ?? null]);
                } else {
                    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Error al obtener empleado']);
                }
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'eliminar_empleado':
            try {
                $empleado_nombre = $_POST['empleado_nombre'] ?? '';
                if (empty($empleado_nombre)) {
                    echo json_encode(['success' => false, 'error' => 'Nombre de empleado requerido']);
                    exit;
                }
                
                $response = api_delete("empleados/" . urlencode($empleado_nombre));
                if ($response && isset($response['success']) && $response['success']) {
                    echo json_encode(['success' => true, 'message' => 'Empleado eliminado exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Error al eliminar empleado']);
                }
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'buscar_empleados':
            try {
                $params = [];
                if (!empty($_POST['search'])) {
                    $params['search'] = $_POST['search'];
                }
                if (!empty($_POST['departamento'])) {
                    $params['departamento'] = $_POST['departamento'];
                }
                if (!empty($_POST['estado'])) {
                    $params['estado'] = $_POST['estado'];
                }
                
                $query_string = http_build_query($params);
                $endpoint = 'empleados/buscar' . ($query_string ? '?' . $query_string : '');
                
                $response = api_get($endpoint);
                if ($response && isset($response['success']) && $response['success']) {
                    echo json_encode(['success' => true, 'empleados' => $response['data'] ?? []]);
                } else {
                    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'Error al buscar empleados']);
                }
            } catch(Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Determinar qué vista mostrar
$vista = $_GET['vista'] ?? 'empleados';
$view_mode = $_GET['view'] ?? 'cards';
$empleado_nombre = $_GET['empleado_nombre'] ?? null;

// Obtener empleados
$empleados = [];
try {
    $response = api_get('empleados');
    if ($response && isset($response['success']) && $response['success']) {
        $empleados = $response['data'] ?? [];
    } else {
        error_log("Error al cargar empleados: " . ($response['error'] ?? 'Error desconocido'));
    }
} catch(Exception $e) {
    error_log("Error al cargar empleados: " . $e->getMessage());
}

// Obtener información del empleado si estamos en vista de edición o perfil
$empleado_actual = null;
if (($vista == 'editar' || $vista == 'perfil') && $empleado_nombre) {
    try {
        $response = api_get("empleados/" . urlencode($empleado_nombre));
        if ($response && isset($response['success']) && $response['success']) {
            $empleado_actual = $response['data'] ?? null;
        } else {
            error_log("Error al cargar empleado: " . ($response['error'] ?? 'Error desconocido'));
        }
    } catch(Exception $e) {
        error_log("Error al cargar empleado: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Sistema ERP ELESS</title>
    <link rel="stylesheet" href="css/siderbarycabezal.css?v=1.0">
    <link rel="stylesheet" href="css/punto-venta.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ESTILOS ESPECÍFICOS PARA EMPLEADOS */
        .empleados-container {
            background: white;
            min-height: 100vh;
            padding: 0 2rem;
        }
        
        .empleados-header {
            background: #f8fafc;
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .empleados-header h1 {
            margin: 0;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .empleados-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #6b7280;
        }
        
        .empleados-controls {
            display: flex;
            gap: 0.5rem;
        }
        
        .empleados-controls button {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .empleados-controls button:hover {
            background: #f3f4f6;
        }
        
        .empleados-controls button.active {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
        }
        
        .empleados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            padding: 2rem 0;
        }
        
        .empleado-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .empleado-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .empleado-header-card {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 1.5rem;
            position: relative;
        }
        
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .empleado-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .empleado-info .role {
            opacity: 0.9;
            font-size: 0.875rem;
        }
        
        .empleado-status {
            margin: 1rem 0;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.activo { background: #d1fae5; color: #065f46; }
        .status-badge.inactivo { background: #fee2e2; color: #991b1b; }
        .status-badge.vacaciones { background: #fef3c7; color: #92400e; }
        .status-badge.licencia { background: #e0e7ff; color: #3730a3; }
        
        .empleado-actions {
            padding: 1.5rem;
            border-top: 1px solid #f3f4f6;
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #6366f1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5b21b6;
            color: white;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
        }
        
        .btn-edit:hover {
            background: #d97706;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        /* ESTILOS PARA FORMULARIO SIMPLIFICADO */
        .form-container {
            background: white;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .form-header {
            background: #f8fafc;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .form-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-title h1 {
            font-size: 1.5rem;
            color: #1f2937;
            margin: 0;
        }
        
        .form-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-grid.single {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn-form {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-form.primary {
            background: #10b981;
            color: white;
        }
        
        .btn-form.primary:hover {
            background: #059669;
            color: white;
        }
        
        .btn-form.secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-form.secondary:hover {
            background: #4b5563;
            color: white;
        }
        
        /* ESTILOS PARA VISTA DE PERFIL */
        .profile-container {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .profile-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .profile-header .avatar-wrapper {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background-color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .profile-header .avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-header .info {
            flex-grow: 1;
        }
        
        .profile-header .info h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.75rem;
            color: #1f2937;
        }
        
        .profile-header .info p {
            margin: 0.25rem 0;
            color: #6b7280;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-header .info .status-text {
            font-size: 0.85rem;
            color: #10b981;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.5rem;
        }
        
        .profile-header .info .status-text.inactivo { color: #ef4444; }
        .profile-header .info .status-text.vacaciones { color: #f59e0b; }
        .profile-header .info .status-text.licencia { color: #6366f1; }
        
        .profile-section-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .profile-section-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .profile-data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 2rem;
        }
        
        .profile-data-item {
            display: flex;
            flex-direction: column;
        }
        
        .profile-data-item label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .profile-data-item p {
            margin: 0;
            color: #4b5563;
            font-size: 0.95rem;
        }
        
        .profile-data-item p.no-data {
            color: #9ca3af;
            font-style: italic;
        }
        
        .profile-data-item.full-width {
            grid-column: span 2;
        }
        
        /* ALERTA DE CONEXIÓN */
        .connection-alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .empleados-grid {
                grid-template-columns: 1fr;
                padding: 1rem 0;
            }
            
            .empleados-container {
                padding: 0 1rem;
            }
            
            .form-container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .profile-container {
                padding: 1rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .profile-data-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-data-item.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main">
        <?php include __DIR__ . '/header.php'; ?>
        
        <section class="content">
            <?php if (empty($empleados)): ?>
            <div class="connection-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <span>⚠️ No se pudo conectar con la API Flask. Verifica que el servidor esté ejecutándose en http://localhost:5000</span>
            </div>
            <?php endif; ?>
            
            <?php if ($vista == 'empleados'): ?>
            <!-- VISTA DE EMPLEADOS -->
            <div class="empleados-container">
                <div class="empleados-header">
                    <h1><i class="fas fa-users"></i> Empleados (Cassandra)</h1>
                    <div class="empleados-info">
                        <span>1-<?php echo count($empleados); ?> / <?php echo count($empleados); ?></span>
                        <div class="empleados-controls">
                            <button onclick="window.location.href='?vista=nuevo'"><i class="fas fa-plus"></i> Nuevo Empleado</button>
                            <button onclick="window.location.href='?vista=empleados&view=cards'" class="<?php echo $view_mode == 'cards' ? 'active' : ''; ?>">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="empleados-grid">
                    <?php foreach ($empleados as $empleado):
                        $initials = strtoupper(substr($empleado['nombre'] ?? '', 0, 1) . substr($empleado['apellidos'] ?? '', 0, 1));
                        $avatarColor = '#6366f1';
                    ?>
                    <div class="empleado-card" onclick="verPerfilEmpleado('<?php echo htmlspecialchars($empleado['nombre'] ?? ''); ?>')">
                        <div class="empleado-header-card" style="background: linear-gradient(135deg, <?php echo $avatarColor; ?>, <?php echo $avatarColor; ?>aa);">
                            <div class="employee-avatar" style="background-color: rgba(255,255,255,0.2);">
                                <?php echo $initials; ?>
                            </div>
                            
                            <div class="empleado-info">
                                <h3><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?></h3>
                                <div class="role"><?php echo htmlspecialchars($empleado['puesto'] ?: 'Sin puesto asignado'); ?></div>
                            </div>
                        </div>
                        
                        <div style="padding: 1.5rem;">
                            <div class="empleado-status">
                                <span class="status-badge <?php echo $empleado['estado'] ?: 'activo'; ?>">
                                    <?php echo ucfirst($empleado['estado'] ?: 'activo'); ?>
                                </span>
                            </div>
                            
                            <div style="margin: 1rem 0; font-size: 0.875rem; color: #6b7280;">
                                <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($empleado['email_trabajo'] ?? ''); ?>
                                </p>
                                <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($empleado['telefono_laboral'] ?: 'No especificado'); ?>
                                </p>
                                <p style="margin: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-building"></i>
                                    <?php echo htmlspecialchars($empleado['departamento'] ?: 'Sin departamento'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="empleado-actions">
                            <button class="btn-sm btn-primary" onclick="event.stopPropagation(); editarEmpleado('<?php echo htmlspecialchars($empleado['nombre'] ?? ''); ?>')">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn-sm btn-delete" onclick="event.stopPropagation(); eliminarEmpleado('<?php echo htmlspecialchars($empleado['nombre'] ?? ''); ?>')">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php elseif ($vista == 'nuevo'): ?>
            <!-- VISTA DE NUEVO EMPLEADO -->
            <div class="form-container">
                <div class="form-header">
                    <div class="form-title">
                        <i class="fas fa-user-plus" style="color: #6366f1;"></i>
                        <h1>Nuevo Empleado</h1>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?vista=empleados" class="btn-form secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="form-card">
                    <form id="empleadoForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required>
                            </div>
                            <div class="form-group">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="email_trabajo">Correo de trabajo *</label>
                                <input type="email" id="email_trabajo" name="email_trabajo" required>
                            </div>
                            <div class="form-group">
                                <label for="telefono_laboral">Teléfono laboral</label>
                                <input type="text" id="telefono_laboral" name="telefono_laboral">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="departamento">Departamento</label>
                                <input type="text" id="departamento" name="departamento" placeholder="Ej: Ventas, Marketing, IT">
                            </div>
                            <div class="form-group">
                                <label for="puesto">Puesto de trabajo</label>
                                <input type="text" id="puesto" name="puesto">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="genero">Género</label>
                                <select id="genero" name="genero">
                                    <option value="">Seleccionar</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Femenino">Femenino</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="estado">Estado del empleado</label>
                                <select id="estado" name="estado">
                                    <option value="activo" selected>Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                    <option value="vacaciones">Vacaciones</option>
                                    <option value="licencia">Licencia</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-form primary" onclick="crearEmpleado()">
                                <i class="fas fa-save"></i> Crear Empleado
                            </button>
                            <a href="?vista=empleados" class="btn-form secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($vista == 'editar' && $empleado_actual): ?>
            <!-- VISTA DE FORMULARIO DE EDICIÓN -->
            <div class="form-container">
                <div class="form-header">
                    <div class="form-title">
                        <i class="fas fa-edit" style="color: #6366f1;"></i>
                        <h1>Editar Empleado</h1>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?vista=empleados" class="btn-form secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="form-card">
                    <form id="empleadoForm">
                        <input type="hidden" name="empleado_nombre" value="<?php echo htmlspecialchars($empleado_actual['nombre'] ?? ''); ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required readonly
                                       value="<?php echo htmlspecialchars($empleado_actual['nombre'] ?? ''); ?>"
                                       style="background-color: #f3f4f6; cursor: not-allowed;">
                                <small style="color: #6b7280; font-size: 0.75rem;">El nombre no se puede modificar (es la clave primaria)</small>
                            </div>
                            <div class="form-group">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required
                                       value="<?php echo htmlspecialchars($empleado_actual['apellidos'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="email_trabajo">Correo de trabajo *</label>
                                <input type="email" id="email_trabajo" name="email_trabajo" required
                                       value="<?php echo htmlspecialchars($empleado_actual['email_trabajo'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="telefono_laboral">Teléfono laboral</label>
                                <input type="text" id="telefono_laboral" name="telefono_laboral"
                                       value="<?php echo htmlspecialchars($empleado_actual['telefono_laboral'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="departamento">Departamento</label>
                                <input type="text" id="departamento" name="departamento"
                                       value="<?php echo htmlspecialchars($empleado_actual['departamento'] ?? ''); ?>"
                                       placeholder="Ej: Ventas, Marketing, IT">
                            </div>
                            <div class="form-group">
                                <label for="puesto">Puesto de trabajo</label>
                                <input type="text" id="puesto" name="puesto"
                                       value="<?php echo htmlspecialchars($empleado_actual['puesto'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="genero">Género</label>
                                <select id="genero" name="genero">
                                    <option value="">Seleccionar</option>
                                    <option value="Masculino" <?php echo ($empleado_actual['genero'] ?? '') == 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="Femenino" <?php echo ($empleado_actual['genero'] ?? '') == 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                                    <option value="Otro" <?php echo ($empleado_actual['genero'] ?? '') == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="estado">Estado del empleado</label>
                                <select id="estado" name="estado">
                                    <option value="activo" <?php echo ($empleado_actual['estado'] ?? 'activo') == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactivo" <?php echo ($empleado_actual['estado'] ?? '') == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="vacaciones" <?php echo ($empleado_actual['estado'] ?? '') == 'vacaciones' ? 'selected' : ''; ?>>Vacaciones</option>
                                    <option value="licencia" <?php echo ($empleado_actual['estado'] ?? '') == 'licencia' ? 'selected' : ''; ?>>Licencia</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-form primary" onclick="guardarEmpleado()">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            <a href="?vista=empleados" class="btn-form secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($vista == 'perfil' && $empleado_actual): ?>
            <!-- VISTA DE PERFIL DE EMPLEADO -->
            <div class="profile-container">
                <div class="form-header" style="background: white; margin-bottom: 1.5rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                    <div class="form-title">
                        <i class="fas fa-eye" style="color: #6366f1;"></i>
                        <h1>Perfil del Empleado</h1>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?vista=empleados" class="btn-form secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <a href="?vista=editar&empleado_nombre=<?php echo urlencode($empleado_actual['nombre'] ?? ''); ?>" class="btn-form primary">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                    </div>
                </div>
                
                <div class="profile-header">
                    <div class="avatar-wrapper" style="background-color: #6366f1;">
                        <?php 
                        $initials = strtoupper(substr($empleado_actual['nombre'] ?? '', 0, 1) . substr($empleado_actual['apellidos'] ?? '', 0, 1));
                        echo $initials;
                        ?>
                    </div>
                    <div class="info">
                        <h2><?php echo htmlspecialchars($empleado_actual['nombre'] . ' ' . $empleado_actual['apellidos']); ?></h2>
                        <p><?php echo htmlspecialchars($empleado_actual['puesto'] ?: 'Sin puesto asignado'); ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($empleado_actual['email_trabajo'] ?: 'No especificado'); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($empleado_actual['telefono_laboral'] ?: 'No especificado'); ?></p>
                        <p class="status-text <?php echo htmlspecialchars($empleado_actual['estado'] ?: 'activo'); ?>">
                            <i class="fas fa-circle" style="font-size: 0.6em;"></i>
                            Empleado <?php echo htmlspecialchars(ucfirst($empleado_actual['estado'] ?: 'activo')); ?>
                        </p>
                    </div>
                </div>
                
                <div class="profile-section-card">
                    <h3>Información Personal</h3>
                    <div class="profile-data-grid">
                        <div class="profile-data-item">
                            <label>Nombre</label>
                            <p><?php echo htmlspecialchars($empleado_actual['nombre'] ?? 'No especificado'); ?></p>
                        </div>
                        <div class="profile-data-item">
                            <label>Apellidos</label>
                            <p><?php echo htmlspecialchars($empleado_actual['apellidos'] ?? 'No especificado'); ?></p>
                        </div>
                        <div class="profile-data-item">
                            <label>Género</label>
                            <p><?php echo htmlspecialchars($empleado_actual['genero'] ?: 'No especificado'); ?></p>
                        </div>
                        <div class="profile-data-item">
                            <label>Estado</label>
                            <p>
                                <span class="status-badge <?php echo $empleado_actual['estado'] ?: 'activo'; ?>">
                                    <?php echo ucfirst($empleado_actual['estado'] ?: 'activo'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="profile-section-card">
                    <h3>Información Laboral</h3>
                    <div class="profile-data-grid">
                        <div class="profile-data-item">
                            <label>Departamento</label>
                            <p><?php echo htmlspecialchars($empleado_actual['departamento'] ?: 'No especificado'); ?></p>
                        </div>
                        <div class="profile-data-item">
                            <label>Puesto</label>
                            <p><?php echo htmlspecialchars($empleado_actual['puesto'] ?: 'No especificado'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="profile-section-card">
                    <h3>Información de Contacto</h3>
                    <div class="profile-data-grid">
                        <div class="profile-data-item">
                            <label>Correo de trabajo</label>
                            <p><?php echo htmlspecialchars($empleado_actual['email_trabajo'] ?: 'No especificado'); ?></p>
                        </div>
                        <div class="profile-data-item">
                            <label>Teléfono laboral</label>
                            <p><?php echo htmlspecialchars($empleado_actual['telefono_laboral'] ?: 'No especificado'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
    // FUNCIONES PARA EMPLEADOS
    function editarEmpleado(empleadoNombre) {
        window.location.href = `?vista=editar&empleado_nombre=${encodeURIComponent(empleadoNombre)}`;
    }
    
    function verPerfilEmpleado(empleadoNombre) {
        window.location.href = `?vista=perfil&empleado_nombre=${encodeURIComponent(empleadoNombre)}`;
    }
    
    function eliminarEmpleado(empleadoNombre) {
        if (!confirm('¿Está seguro de que desea eliminar este empleado? Esta acción no se puede deshacer.')) {
            return;
        }
        
        console.log('🗑️ Eliminando empleado:', empleadoNombre);
        
        const formData = new FormData();
        formData.append('action', 'eliminar_empleado');
        formData.append('empleado_nombre', empleadoNombre);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Empleado eliminado exitosamente');
                location.reload();
            } else {
                alert('Error al eliminar el empleado: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
    }
    
    function crearEmpleado() {
        const form = document.getElementById('empleadoForm');
        const formData = new FormData();
        
        formData.append('action', 'crear_empleado');
        
        // Obtener todos los campos del formulario
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name && input.name !== 'action') {
                formData.append(input.name, input.value || '');
            }
        });
        
        console.log('💾 Creando empleado...');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Empleado creado exitosamente');
                window.location.href = '?vista=empleados';
            } else {
                alert('Error al crear el empleado: ' + (data.error || 'Error desconocido'));
                console.error('Error details:', data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al crear el empleado');
        });
    }
    
    function guardarEmpleado() {
        const form = document.getElementById('empleadoForm');
        const formData = new FormData();
        
        formData.append('action', 'editar_empleado');
        
        // Obtener todos los campos del formulario
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name && input.name !== 'action') {
                formData.append(input.name, input.value || '');
            }
        });
        
        console.log('💾 Guardando empleado...');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Empleado actualizado exitosamente');
                window.location.href = '?vista=empleados';
            } else {
                alert('Error al guardar el empleado: ' + (data.error || 'Error desconocido'));
                console.error('Error details:', data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al guardar el empleado');
        });
    }
    
    // INICIALIZACIÓN
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Sistema de gestión de empleados cargado');
        console.log('🔗 Conectado a API Flask en: http://localhost:5000');
        
        const totalEmpleados = <?php echo count($empleados); ?>;
        console.log('👥 Total de empleados cargados:', totalEmpleados);
    });
    </script>
</body>
</html>
