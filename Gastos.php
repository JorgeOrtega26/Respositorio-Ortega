<?php
session_start();

// MANEJAR PETICIONES AJAX PRIMERO (antes de cualquier HTML)
if (isset($_GET['action']) || isset($_POST['action'])) {
  // Verificar autenticación para AJAX
  if (!isset($_SESSION['nombres'])) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
      exit();
  }

  header('Content-Type: application/json; charset=utf-8');

  // Configuración de la base de datos
  $host = 'localhost';
  $dbname = 'sistema-erp-eless';
  $username = 'root';
  $password = '';

  // Función para conectar a la base de datos
  function conectarDB()
  {
      global $host, $username, $password, $dbname;
      $conexion = new mysqli($host, $username, $password, $dbname);
      if ($conexion->connect_error) {
          throw new Exception('Error de conexión: ' . $conexion->connect_error);
      }
      $conexion->set_charset("utf8");
      return $conexion;
  }

  $action = $_GET['action'] ?? $_POST['action'] ?? '';

  try {
      switch ($action) {
          case 'obtener_gastos':
              obtenerGastos();
              break;
          case 'obtener_gasto':
              obtenerGasto();
              break;
          case 'crear_gasto':
              crearGasto();
              break;
          case 'editar_gasto':
              editarGasto();
              break;
          case 'eliminar_gasto':
              eliminarGasto();
              break;
          case 'actualizar_estado':
              actualizarEstadoGasto();
              break;
          default:
              throw new Exception('Acción no válida');
      }
  } catch (Exception $e) {
      echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  }
  exit();
}

// Verificar autenticación para la página HTML
if (!isset($_SESSION['nombres'])) {
  header("Location: inicio-Sesion.php");
  exit();
}

// Función para obtener todos los gastos
function obtenerGastos()
{
  // Debug: Log para verificar que la función se ejecuta
  error_log("🔧 obtenerGastos() ejecutándose...");

  if (!isset($_SESSION['nombres'])) {
      error_log("❌ Sesión no válida en obtenerGastos");
      throw new Exception('Sesión no válida');
  }

  error_log("✅ Sesión válida: " . $_SESSION['nombres']);

  try {
      $conexion = conectarDB();
      error_log("✅ Conexión DB exitosa");

      $empleado = $_SESSION['nombres'];

      $sql = "SELECT id, descripcion, categoria, fecha_gasto, total, metodo_pago, 
                 pagado_por, COALESCE(NULLIF(estado, ''), 'borrador') AS estado, 
                 archivo_recibo, fecha_creacion, empleado, notas
          FROM gastos 
          ORDER BY fecha_creacion DESC";

      $stmt = $conexion->prepare($sql);
      if (!$stmt) {
          error_log("❌ Error preparando consulta: " . $conexion->error);
          throw new Exception('Error al preparar la consulta: ' . $conexion->error);
      }

      $stmt->execute();
      $result = $stmt->get_result();

      $gastos = [];
      while ($row = $result->fetch_assoc()) {
          $row['total'] = floatval($row['total']);
          $gastos[] = $row;
      }

      error_log("✅ Gastos encontrados: " . count($gastos));

      $response = [
          'success' => true,
          'gastos' => $gastos,
          'total_encontrados' => count($gastos),
          'usuario' => $empleado,
          'debug' => 'Función ejecutada correctamente'
      ];

      error_log("✅ Enviando respuesta JSON: " . json_encode($response));
      echo json_encode($response);

      $conexion->close();
  } catch (Exception $e) {
      error_log("❌ Error en obtenerGastos: " . $e->getMessage());
      throw $e;
  }
}

// Función para obtener un gasto específico
function obtenerGasto()
{
  if (!isset($_GET['id']) || empty($_GET['id'])) {
      throw new Exception('ID de gasto requerido');
  }

  $conexion = conectarDB();
  $empleado = $_SESSION['nombres'];
  $id = intval($_GET['id']);

  $sql = "SELECT id, descripcion, categoria, fecha_gasto, total, metodo_pago, 
                 pagado_por, estado, archivo_recibo, notas, fecha_creacion 
          FROM gastos 
          WHERE id = ?";

  $stmt = $conexion->prepare($sql);
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
      $gasto = $result->fetch_assoc();
      echo json_encode(['success' => true, 'gasto' => $gasto]);
  } else {
      throw new Exception('Gasto no encontrado');
  }

  $conexion->close();
}

// Función para crear un nuevo gasto
function crearGasto()
{
  ob_start();

  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
      throw new Exception('Método no permitido');
  }

  $conexion = conectarDB();

  // Validar y obtener datos del formulario
  $descripcion = trim($_POST['descripcion'] ?? '');
  $categoria = trim($_POST['categoria'] ?? '');
  $fecha = trim($_POST['fecha'] ?? '');
  $total = floatval($_POST['total'] ?? 0);
  $metodo_pago = trim($_POST['metodo_pago'] ?? '');
  $pagado_por = trim($_POST['pagado_por'] ?? '');
  $notas = trim($_POST['notas'] ?? '');
  $empleado = $_SESSION['nombres'];

  // Validaciones básicas
  if (empty($descripcion)) throw new Exception('La descripción es requerida');
  if (empty($categoria)) throw new Exception('La categoría es requerida');
  if (empty($fecha)) throw new Exception('La fecha es requerida');
  if ($total <= 0) throw new Exception('El total debe ser mayor a 0');
  if (empty($metodo_pago)) throw new Exception('El método de pago es requerido');
  if (empty($pagado_por)) throw new Exception('Debe especificar quién pagó');

  // Manejar archivo adjunto
  $archivo_recibo = null;
  if (isset($_FILES['recibo']) && $_FILES['recibo']['error'] === UPLOAD_ERR_OK) {
      $upload_dir = 'uploads/recibos';

      if (!file_exists($upload_dir)) {
          if (!mkdir($upload_dir, 0755, true)) {
              throw new Exception('No se pudo crear el directorio de uploads');
          }
      }

      $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
      $file_type = $_FILES['recibo']['type'];

      if (!in_array($file_type, $allowed_types)) {
          throw new Exception('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF) y PDF');
      }

      if ($_FILES['recibo']['size'] > 5 * 1024 * 1024) {
          throw new Exception('El archivo es demasiado grande. Máximo 5MB');
      }

      $file_extension = pathinfo($_FILES['recibo']['name'], PATHINFO_EXTENSION);
      $archivo_recibo = uniqid('recibo_') . '.' . strtolower($file_extension);
      $upload_path = $upload_dir . '/' . $archivo_recibo;

      if (!move_uploaded_file($_FILES['recibo']['tmp_name'], $upload_path)) {
          throw new Exception('Error al subir el archivo');
      }
  }

  $sql = "INSERT INTO gastos (descripcion, categoria, fecha_gasto, total, metodo_pago, pagado_por, notas, empleado, archivo_recibo, estado, fecha_creacion) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'borrador', NOW())";

  $stmt = $conexion->prepare($sql);
  if (!$stmt) {
      throw new Exception('Error al preparar la consulta: ' . $conexion->error);
  }

  $stmt->bind_param(
      "sssdsssss",
      $descripcion,
      $categoria,
      $fecha,
      $total,
      $metodo_pago,
      $pagado_por,
      $notas,
      $empleado,
      $archivo_recibo
  );

  if ($stmt->execute()) {
      $gasto_id = $conexion->insert_id;
      ob_clean();

      echo json_encode([
          'success' => true,
          'message' => 'Gasto guardado exitosamente',
          'data' => [
              'id' => $gasto_id,
              'descripcion' => $descripcion,
              'categoria' => $categoria,
              'fecha' => $fecha,
              'total' => $total,
              'metodo_pago' => $metodo_pago,
              'pagado_por' => $pagado_por,
              'archivo' => $archivo_recibo
          ]
      ]);
  } else {
      throw new Exception('Error al guardar en la base de datos: ' . $stmt->error);
  }

  $stmt->close();
  $conexion->close();
}

// Función para editar un gasto
function editarGasto()
{
  ob_start();

  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
      throw new Exception('Método no permitido');
  }

  if (!isset($_POST['id']) || empty($_POST['id'])) {
      throw new Exception('ID de gasto requerido');
  }

  $conexion = conectarDB();

  // Validar y obtener datos del formulario
  $id = intval($_POST['id']);
  $descripcion = trim($_POST['descripcion'] ?? '');
  $categoria = trim($_POST['categoria'] ?? '');
  $fecha = trim($_POST['fecha'] ?? '');
  $total = floatval($_POST['total'] ?? 0);
  $metodo_pago = trim($_POST['metodo_pago'] ?? '');
  $pagado_por = trim($_POST['pagado_por'] ?? '');
  $notas = trim($_POST['notas'] ?? '');
  $empleado = $_SESSION['nombres'];

  // Validaciones básicas
  if (empty($descripcion)) throw new Exception('La descripción es requerida');
  if (empty($categoria)) throw new Exception('La categoría es requerida');
  if (empty($fecha)) throw new Exception('La fecha es requerida');
  if ($total <= 0) throw new Exception('El total debe ser mayor a 0');
  if (empty($metodo_pago)) throw new Exception('El método de pago es requerido');
  if (empty($pagado_por)) throw new Exception('Debe especificar quién pagó');

  // Verificar que el gasto pertenece al usuario
  $sql_verificar = "SELECT id, archivo_recibo FROM gastos WHERE id = ?";
  $stmt_verificar = $conexion->prepare($sql_verificar);
  $stmt_verificar->bind_param("i", $id);
  $stmt_verificar->execute();
  $result_verificar = $stmt_verificar->get_result();

  if ($result_verificar->num_rows === 0) {
      throw new Exception('Gasto no encontrado o no tienes permisos para editarlo');
  }

  $gasto_actual = $result_verificar->fetch_assoc();
  $archivo_recibo_actual = $gasto_actual['archivo_recibo'];

  // Manejar archivo adjunto
  $archivo_recibo = $archivo_recibo_actual;

  if (isset($_FILES['recibo']) && $_FILES['recibo']['error'] === UPLOAD_ERR_OK) {
      $upload_dir = 'uploads/recibos/';

      if (!file_exists($upload_dir)) {
          if (!mkdir($upload_dir, 0755, true)) {
              throw new Exception('No se pudo crear el directorio de uploads');
          }
      }

      $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
      $file_type = $_FILES['recibo']['type'];

      if (!in_array($file_type, $allowed_types)) {
          throw new Exception('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF) y PDF');
      }

      if ($_FILES['recibo']['size'] > 5 * 1024 * 1024) {
          throw new Exception('El archivo es demasiado grande. Máximo 5MB');
      }

      // Eliminar archivo anterior si existe
      if ($archivo_recibo && file_exists('./uploads/recibos/' . $archivo_recibo)) {
          unlink($upload_dir . $archivo_recibo_actual);
      }

      $file_extension = pathinfo($_FILES['recibo']['name'], PATHINFO_EXTENSION);
      $archivo_recibo = uniqid('recibo_') . '.' . strtolower($file_extension);
      $upload_path = $upload_dir . $archivo_recibo;

      if (!move_uploaded_file($_FILES['recibo']['tmp_name'], $upload_path)) {
          throw new Exception('Error al subir el archivo');
      }
  }

  // Actualizar gasto en la base de datos
  $sql = "UPDATE gastos SET 
              descripcion = ?, 
              categoria = ?, 
              fecha_gasto = ?, 
              total = ?, 
              metodo_pago = ?, 
              pagado_por = ?, 
              notas = ?, 
              archivo_recibo = ?
          WHERE id = ?";

  $stmt = $conexion->prepare($sql);
  if (!$stmt) {
      throw new Exception('Error al preparar la consulta: ' . $conexion->error);
  }

  $stmt->bind_param(
      "sssdssssi",
      $descripcion,
      $categoria,
      $fecha,
      $total,
      $metodo_pago,
      $pagado_por,
      $notas,
      $archivo_recibo,
      $id
  );

  if ($stmt->execute()) {
      ob_clean();

      echo json_encode([
          'success' => true,
          'message' => 'Gasto actualizado exitosamente',
          'data' => [
              'id' => $id,
              'descripcion' => $descripcion,
              'categoria' => $categoria,
              'fecha' => $fecha,
              'total' => $total,
              'metodo_pago' => $metodo_pago,
              'pagado_por' => $pagado_por,
              'archivo' => $archivo_recibo
          ]
      ]);
  } else {
      throw new Exception('Error al actualizar en la base de datos: ' . $stmt->error);
  }

  $stmt->close();
  $conexion->close();
}

// Función para eliminar un gasto
function eliminarGasto()
{
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
      throw new Exception('Método no permitido');
  }

  $input = json_decode(file_get_contents('php://input'), true);

  if (!isset($input['id']) || empty($input['id'])) {
      throw new Exception('ID de gasto requerido');
  }

  $conexion = conectarDB();
  $empleado = $_SESSION['nombres'];
  $id = intval($input['id']);

  // Primero obtener información del archivo para eliminarlo
  $sql_archivo = "SELECT archivo_recibo FROM gastos WHERE id = ?";
  $stmt_archivo = $conexion->prepare($sql_archivo);
  $stmt_archivo->bind_param("i", $id);
  $stmt_archivo->execute();
  $result_archivo = $stmt_archivo->get_result();

  if ($result_archivo->num_rows === 0) {
      throw new Exception('Gasto no encontrado o no tienes permisos para eliminarlo');
  }

  $gasto = $result_archivo->fetch_assoc();
  $archivo_recibo = $gasto['archivo_recibo'];

  // Eliminar el gasto de la base de datos
  $sql = "DELETE FROM gastos WHERE id = ?";
  $stmt = $conexion->prepare($sql);
  $stmt->bind_param("i", $id);

  if ($stmt->execute()) {
      // Si se eliminó correctamente, eliminar también el archivo
      if ($archivo_recibo && file_exists('uploads/recibos/' . $archivo_recibo)) {
          unlink('uploads/recibos/' . $archivo_recibo);
      }

      echo json_encode([
          'success' => true,
          'message' => 'Gasto eliminado exitosamente'
      ]);
  } else {
      throw new Exception('Error al eliminar el gasto');
  }

  $conexion->close();
}

// Función para actualizar estado de un gasto
function actualizarEstadoGasto()
{

  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
      throw new Exception('Método no permitido');
  }

  $input = json_decode(file_get_contents('php://input'), true);

  if (!isset($input['id']) || !isset($input['estado'])) {
      throw new Exception('ID y estado requeridos');
  }

  $conexion = conectarDB();
  $empleado = $_SESSION['nombres'];
  $id = intval($input['id']);
  $estado = trim($input['estado']);



  // Validar estados permitidos
  $estados_permitidos = ['aprobado', 'desaprobado', 'borrador'];
  if (!in_array($estado, $estados_permitidos)) {
      throw new Exception('Estado no válido');
  }



  // Verificar que el gasto pertenece al usuario
  $sql_verificar = "SELECT id, pagado_por FROM gastos WHERE id = ?";
  $stmt_verificar = $conexion->prepare($sql_verificar);
  $stmt_verificar->bind_param("i", $id);
  $stmt_verificar->execute();
  $result_verificar = $stmt_verificar->get_result();

  if ($result_verificar->num_rows === 0) {
      throw new Exception('Gasto no encontrado o no tienes permisos');
  }

  $gasto = $result_verificar->fetch_assoc();

  // Actualizar estado
  $sql = "UPDATE gastos SET estado = ? WHERE id = ?";
  $stmt = $conexion->prepare($sql);
  $stmt->bind_param("si", $estado, $id);

  if ($stmt->execute()) {
      echo json_encode([
          'success' => true,
          'message' => 'Estado actualizado exitosamente',
          'data' => [
              'id' => $id,
              'estado' => $estado,
              'pagado_por' => $gasto['pagado_por']
          ]
      ]);
  } else {
      throw new Exception('Error al actualizar el estado');
  }

  $conexion->close();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gastos - ELESS</title>
  <link rel="stylesheet" href="./css/siderbarycabezal.css">
  <link rel="stylesheet" href="./css/estiloG.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
  <?php include 'sidebar.php'; ?>
  <div class="main">
      <?php include 'header.php'; ?>

      <section class="content">
          <!-- Header del módulo de gastos -->
          <div class="gastos-header">
              <div class="gastos-nav">
                  <div class="gastos-logo">
                      <i class="fas fa-receipt"></i>
                      <span>Gastos</span>
                  </div>

                  <nav class="gastos-navigation">
                      <button class="nav-item active" id="misGastosTab">Mi gastos</button>
                      <button class="nav-item" id="analisisGastosTab">
                          <i class="fas fa-chart-bar"></i>
                          Análisis de gastos
                      </button>
                  </nav>
              </div>
          </div>

          <!-- Toolbar -->
          <div class="gastos-toolbar">
              <div class="toolbar-left">
                  <button class="btn btn-primary">
                      <i class="fas fa-upload"></i>
                      Subir
                  </button>
                  <button class="btn btn-secondary" id="nuevoGastoBtn">
                      <i class="fas fa-plus"></i>
                      Nuevo
                  </button>
                  <span class="active-tab">Mi gastos</span>
              </div>

              <div class="toolbar-right">
                  <button class="btn btn-secondary" id="actualizarBtn">
                      <i class="fas fa-sync-alt"></i>
                      Actualizar
                  </button>
              </div>
          </div>

          <!-- Tarjetas de resumen -->
          <div class="gastos-summary">
              <div class="summary-card">
                  <div class="card-content">
                      <div class="card-info">
                          <h3 id="totalPorEnviar">S/ 0,00</h3>
                          <p>Total de gastos</p>
                      </div>
                      <i class="fas fa-chevron-right"></i>
                  </div>
              </div>

              <div class="summary-card">
                  <div class="card-content">
                      <div class="card-info">
                          <h3 id="totalAprobacion">S/ 0,00</h3>
                          <p>En espera de aprobación</p>
                      </div>
                      <i class="fas fa-chevron-right"></i>
                  </div>
              </div>

              <div class="summary-card">
                  <div class="card-content">
                      <div class="card-info">
                          <h3 id="totalReembolso">S/ 0,00</h3>
                          <p>Gastos por reembolsar</p>
                      </div>
                      <i class="fas fa-chevron-right"></i>
                  </div>
              </div>
          </div>

          <!-- Tabla de gastos -->
          <div class="gastos-table">
              <div class="table-header">
                  <div class="table-row">
                      <div class="table-cell">Empleado</div>
                      <div class="table-cell">Descripción</div>
                      <div class="table-cell">Fecha del</div>
                      <div class="table-cell">Categoría</div>
                      <div class="table-cell">Método de Pago</div>
                      <div class="table-cell">Pagado por</div>
                      <div class="table-cell">Total</div>
                      <div class="table-cell">Estado</div>
                      <div class="table-cell">Acciones</div>
                  </div>
              </div>

              <!-- Contenedor para gastos -->
              <div id="gastosContainer">
                  <!-- Loading state -->
                  <div class="loading-state" id="loadingState">
                      <i class="fas fa-spinner fa-spin"></i>
                      <p>Cargando gastos...</p>
                  </div>

                  <!-- Estado vacío (se mostrará si no hay gastos) -->
                  <div class="empty-state" id="emptyState" style="display: none;">
                      <div class="phone-illustration">
                          <div class="phone">
                              <div class="phone-screen">
                                  <i class="fas fa-receipt"></i>
                              </div>
                          </div>
                      </div>
                      <h3>Suba o arrastre un recibo de gastos</h3>
                      <div class="empty-actions">
                          <a href="#" class="sample-link">Or use un recibo de muestra</a>
                          <p class="tip-text">
                              Consejo: Intente enviar los recibos por correo electrónico
                              <strong>expense@eless.com</strong>
                          </p>
                      </div>
                  </div>

                  <!-- Lista de gastos (se llenará dinámicamente) -->
                  <div id="gastosLista"></div>
              </div>
          </div>

          <!-- Sección de Análisis de Gastos -->
          <div class="analisis-gastos-section" id="analisisGastosSection" style="display: none;">
              <div class="analisis-header">
                  <h3><i class="fas fa-chart-bar"></i> Análisis de Gastos</h3>
                  <div class="chart-controls">
                      <button class="chart-btn active" data-chart="bar">
                          <i class="fas fa-chart-bar"></i>
                      </button>
                      <button class="chart-btn" data-chart="line">
                          <i class="fas fa-chart-line"></i>
                      </button>
                      <button class="chart-btn" data-chart="pie">
                          <i class="fas fa-chart-pie"></i>
                      </button>
                  </div>
              </div>

              <div class="charts-container">
                  <div class="chart-wrapper">
                      <canvas id="gastosChart" width="400" height="200"></canvas>
                  </div>

                  <div class="chart-stats">
                      <div class="stat-card">
                          <h4>Gasto Promedio</h4>
                          <span id="gastoPromedio">S/ 0.00</span>
                      </div>
                      <div class="stat-card">
                          <h4>Categoría Principal</h4>
                          <span id="categoriaPrincipal">-</span>
                      </div>
                      <div class="stat-card">
                          <h4>Total del Mes</h4>
                          <span id="totalMes">S/ 0.00</span>
                      </div>
                  </div>
              </div>
          </div>
      </section>
  </div>

  <!-- MODAL NUEVO GASTO -->
  <div class="modal-overlay" id="nuevoGastoModal">
      <div class="modal-container">
          <div class="modal-header">
              <div class="modal-tabs">
                  <button class="tab-btn active">Nuevo</button>
                  <span class="tab-title">Mis gastos</span>
                  <span class="tab-subtitle"> <i class="fas fa-cog"></i> <i class="fas fa-upload"></i> <i
                          class="fas fa-times" id="cerrarModal"></i></span>
              </div>
          </div>

          <div class="modal-content">
              <form id="gastoForm" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="crear_gasto">

                  <!-- Botones de acción -->
                  <div class="action-buttons">
                      <button type="button" class="btn btn-primary">Adjuntar recibo</button>
                  </div>

                  <!-- Formulario -->
                  <div class="form-grid">
                      <div class="form-group full-width">
                          <label for="descripcion">Descripción</label>
                          <textarea id="descripcion" name="descripcion" placeholder="por ejemplo, comida con cliente"
                              rows="3"></textarea>
                      </div>

                      <div class="form-group">
                          <label for="categoria">Tipo de gasto</label>
                          <select id="categoria" name="categoria">
                              <option value="">Seleccionar categoría</option>
                              <option value="alimentacion">Alimentación</option>
                              <option value="transporte">Transporte</option>
                              <option value="hospedaje">Hospedaje</option>
                              <option value="materiales">Materiales de oficina</option>
                              <option value="otros">Otros</option>
                          </select>
                      </div>

                      <div class="form-group">
                          <label for="fecha">Fecha del gasto</label>
                          <input type="date" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>">
                      </div>

                      <div class="form-group">
                          <label for="total">Total</label>
                          <input type="number" id="total" name="total" placeholder="S/ 0,00" step="0.01">
                      </div>

                      <div class="form-group">
                          <label for="metodo_pago">Método de pago</label>
                          <select id="metodo_pago" name="metodo_pago" required>
                              <option value="">Seleccionar método</option>
                              <option value="efectivo">Efectivo</option>
                              <option value="tarjeta_debito">Tarjeta de débito</option>
                              <option value="tarjeta_credito">Tarjeta de crédito</option>
                              <option value="transferencia">Transferencia bancaria</option>
                              <option value="yape">Yape</option>
                              <option value="plin">Plin</option>
                              <option value="tunki">Tunki</option>
                              <option value="otros">Otros</option>
                          </select>
                      </div>

                      <div class="form-group">
                          <span>Empleado</span>
                          <div class="empleado-info">
                              <div class="empleado-avatar">MH</div>
                              <span><?php echo $_SESSION['nombres']; ?></span>
                          </div>
                      </div>

                      <div class="form-group">
                          <span>Pagado por</span>
                          <div class="radio-group">
                              <label class="radio-option">
                                  <input type="radio" name="pagado_por" value="empleado" checked>
                                  <span class="radio-custom"></span>
                                  Empleado (a reembolsar)
                              </label>
                              <label class="radio-option">
                                  <input type="radio" name="pagado_por" value="empresa">
                                  <span class="radio-custom"></span>
                                  Empresa
                              </label>
                          </div>
                      </div>

                      <div class="form-group full-width">
                          <label for="notas">Notas...</label>
                          <textarea id="notas" name="notas" rows="3"></textarea>
                      </div>

                      <div class="form-group full-width">
                          <label for="recibo">Adjuntar recibo</label>
                          <div class="file-upload">
                              <input type="file" id="recibo" name="recibo" accept="image/*,.pdf">
                              <div class="file-upload-area">
                                  <i class="fas fa-cloud-upload-alt"></i>
                                  <p>Arrastra un archivo aquí o haz clic para seleccionar</p>
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- Botones del formulario -->
                  <div class="form-actions">
                      <button type="button" class="btn btn-secondary" id="cancelarBtn">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Guardar Gasto</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

  <!-- MODAL DE ÉXITO PERSONALIZADO -->
  <div class="success-modal-overlay" id="successModal">
      <div class="success-modal">
          <div class="success-icon">
              <i class="fas fa-check"></i>
          </div>
          <h3 class="success-title" id="successTitle">¡Gasto creado exitosamente!</h3>
          <p class="success-message" id="successMessage">El gasto ha sido guardado correctamente</p>
      </div>
  </div>

  <!-- Scripts -->
  <script>
      // Configuración global para las peticiones AJAX
      const API_BASE = './Gastos.php';

      // Actualizar las URLs en el JavaScript para usar el mismo archivo
      window.gastosConfig = {
          obtenerGastos: `${API_BASE}?action=obtener_gastos`,
          obtenerGasto: `${API_BASE}?action=obtener_gasto`,
          crearGasto: `${API_BASE}`,
          editarGasto: `${API_BASE}`,
          eliminarGasto: `${API_BASE}?action=eliminar_gasto`,
          actualizarEstado: `${API_BASE}?action=actualizar_estado`
      };

      // Debug inicial
      console.log('🔧 Página cargada, verificando elementos...');
      console.log('📊 Chart.js disponible:', typeof Chart !== 'undefined');

      // Verificar elementos críticos
      const elementos = {
          'nuevoGastoBtn': document.getElementById('nuevoGastoBtn'),
          'gastosContainer': document.getElementById('gastosContainer'),
          'loadingState': document.getElementById('loadingState'),
          'gastosLista': document.getElementById('gastosLista'),
          'totalPorEnviar': document.getElementById('totalPorEnviar'),
          'analisisGastosSection': document.getElementById('analisisGastosSection')
      };

      console.log('🔍 Elementos encontrados:');
      let elementosEncontrados = 0;
      for (const [nombre, elemento] of Object.entries(elementos)) {
          if (elemento) {
              console.log(`✅ ${nombre}:`, elemento);
              elementosEncontrados++;
          } else {
              console.log(`❌ ${nombre}: NO ENCONTRADO`);
          }
      }
  </script>

  <!-- Cargar el script principal -->
  <script src="./js/gastosJ.js"></script>
</body>

</html>
