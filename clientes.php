<?php
// clientes.php

// 0) Mostrar errores en desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1) Autoload de Composer (incluye MongoDB)
require __DIR__ . '/vendor/autoload.php';


//Clase principal

use MongoDB\Client as MongoClient;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Regex  ;

// 2) Conecta a MongoDB
try {
    $mongoClient     = new MongoClient("mongodb://localhost:27017");
    $mongoDb         = $mongoClient->selectDatabase('sistema-erp-eless');
    $mongoCollection = $mongoDb->selectCollection('Clientes');
} catch (Throwable $e) {
    die("Error conectando a MongoDB: " . $e->getMessage());
}

// 3) (Opcional) Conexión a MySQL
$host     = 'localhost';
$dbname   = 'sistema-erp-eless';
$username = 'root';
$password = '';
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Error de conexión MySQL: " . $e->getMessage());
}

// 4) Endpoints AJAX para MongoDB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {

            // 4.1) Buscar clientes
            case 'buscar_mongo':
                $q = trim($_POST['search'] ?? '');
                $filter = [];
                if ($q !== '') {
                    $regex = new MongoDB\BSON\Regex($q, 'i');
                    $filter = ['$or'=>[
                        ['nombre'            => $regex],
                        ['apellidos'         => $regex],
                        ['nombre_empresa'    => $regex],
                        ['email'             => $regex],
                        ['documento_numero'  => $regex],
                    ]];
                }
                $cursor = $mongoCollection->find($filter, ['sort'=>['creado_en'=>-1],'limit'=>200]);
                $out = [];
                foreach ($cursor as $doc) {
                    $arr = $doc->getArrayCopy();
                    $arr['_id'] = (string)$doc['_id'];
                    // Fecha a string
                    foreach (['fecha_creacion','fecha_actualizacion','fecha_registro'] as $f) {
                        if (!empty($doc[$f]) && $doc[$f] instanceof UTCDateTime) {
                            $arr[$f.'_fmt'] = date('d/m/Y H:i', $doc[$f]->toDateTime()->getTimestamp());
                        } else {
                            $arr[$f.'_fmt'] = '';
                        }
                    }
                    $out[] = $arr;
                }
                echo json_encode(['success'=>true,'clientes'=>$out]);
                break;

            // 4.2) Crear cliente
            case 'crear_mongo':
                // Todos los campos que quieres guardar
                $doc = [
                    'tipo'                        => $_POST['tipo']                        ?? '',
                    'nombre'                      => $_POST['nombre']                      ?? '',
                    'apellidos'                   => $_POST['apellidos']                   ?? '',
                    'nombre_empresa'              => $_POST['nombre_empresa']              ?? '',
                    'email'                       => $_POST['email']                       ?? '',
                    'telefono'                    => $_POST['telefono']                    ?? '',
                    'celular'                     => $_POST['celular']                     ?? '',
                    'direccion'                   => $_POST['direccion']                   ?? '',
                    'ciudad'                      => $_POST['ciudad']                      ?? '',
                    'estado'                      => $_POST['estado']                      ?? '',
                    'codigo_postal'               => $_POST['codigo_postal']               ?? '',
                    'pais'                        => $_POST['pais']                        ?? '',
                    'documento_tipo'              => $_POST['documento_tipo']              ?? '',
                    'documento_numero'            => $_POST['documento_numero']            ?? '',
                    'puesto_trabajo'              => $_POST['puesto_trabajo']              ?? '',
                    'sitio_web'                   => $_POST['sitio_web']                   ?? '',
                    'idioma'                      => $_POST['idioma']                      ?? '',
                    'etiquetas'                   => $_POST['etiquetas']                   ?? '',
                    'balance'                     => isset($_POST['balance']) 
                                                      ? (float)$_POST['balance'] : 0.0,
                    'activo'                      => isset($_POST['activo']) 
                                                      ? (bool)$_POST['activo'] : true,
                    'fecha_creacion'              => new UTCDateTime(),
                    'fecha_actualizacion'         => new UTCDateTime(),
                    'tipo_cliente'                => $_POST['tipo_cliente']                ?? '',
                    'industria'                   => $_POST['industria']                   ?? '',
                    'notas'                       => $_POST['notas']                       ?? '',
                    'contacto_principal_nombre'   => $_POST['contacto_principal_nombre']   ?? '',
                    'contacto_principal_email'    => $_POST['contacto_principal_email']    ?? '',
                    'contacto_principal_telefono' => $_POST['contacto_principal_telefono'] ?? '',
                    'color_avatar'                => $_POST['color_avatar']                ?? '#6366f1',
                    'estado_registro'             => $_POST['estado_registro']             ?? '',
                    'fecha_registro'              => isset($_POST['fecha_registro']) 
                                                      ? new UTCDateTime(strtotime($_POST['fecha_registro'])*1000)
                                                      : new UTCDateTime(),
                    'tipo_identificacion'         => $_POST['tipo_identificacion']         ?? '',
                    'numero_identificacion'       => $_POST['numero_identificacion']       ?? '',
                    'calle1'                      => $_POST['calle1']                      ?? '',
                    'calle2'                      => $_POST['calle2']                      ?? '',
                    'distrito'                    => $_POST['distrito']                    ?? '',
                    'estado_provincia'            => $_POST['estado_provincia']            ?? '',
                ];
                $res = $mongoCollection->insertOne($doc);
                echo json_encode([
                    'success'     => true,
                    'insertedId'  => (string)$res->getInsertedId()
                ]);
                break;

            // 4.3) Obtener un cliente
            case 'obtener_mongo':
                $id = $_POST['cliente_id'] ?? '';
                if (!preg_match('/^[0-9a-f]{24}$/i',$id)) {
                    throw new Exception("ID inválido");
                }
                $doc = $mongoCollection->findOne(['_id'=>new ObjectId($id)]);
                if (!$doc) {
                    echo json_encode(['success'=>false,'error'=>'No encontrado']);
                } else {
                    $arr = $doc->getArrayCopy();
                    $arr['_id'] = $id;
                    // formatea fechas
                    foreach (['fecha_creacion','fecha_actualizacion','fecha_registro'] as $f) {
                        if (!empty($doc[$f]) && $doc[$f] instanceof UTCDateTime) {
                            $arr[$f.'_fmt'] = date('Y-m-d H:i:s',$doc[$f]->toDateTime()->getTimestamp());
                        } else {
                            $arr[$f.'_fmt']='';
                        }
                    }
                    echo json_encode(['success'=>true,'cliente'=>$arr]);
                }
                break;

            // 4.4) Editar cliente
            case 'editar_mongo':
                $id = $_POST['cliente_id'] ?? '';
                if (!preg_match('/^[0-9a-f]{24}$/i',$id)) {
                    throw new Exception("ID inválido");
                }
                $update = [];
                // sólo actualizo los campos enviados
                foreach ([
                    'tipo','nombre','apellidos','nombre_empresa','email','telefono','celular',
                    'direccion','ciudad','estado','codigo_postal','pais','documento_tipo',
                    'documento_numero','puesto_trabajo','sitio_web','idioma','etiquetas',
                    'balance','activo','tipo_cliente','industria','notas',
                    'contacto_principal_nombre','contacto_principal_email','contacto_principal_telefono',
                    'color_avatar','estado_registro','tipo_identificacion','numero_identificacion',
                    'calle1','calle2','distrito','estado_provincia'
                ] as $fld) {
                    if (isset($_POST[$fld])) {
                        // casting especial
                        if ($fld==='balance') {
                            $update[$fld] = (float)$_POST[$fld];
                        } elseif ($fld==='activo') {
                            $update[$fld] = (bool)$_POST[$fld];
                        } else {
                            $update[$fld] = $_POST[$fld];
                        }
                    }
                }
                $update['fecha_actualizacion'] = new UTCDateTime();
                // si pasaron fecha_registro, la convierto
                if (!empty($_POST['fecha_registro'])) {
                    $ts = strtotime($_POST['fecha_registro']);
                    if ($ts) {
                        $update['fecha_registro'] = new UTCDateTime($ts*1000);
                    }
                }
                $mongoCollection->updateOne(
                    ['_id'=>new ObjectId($id)],
                    ['$set'=>$update]
                );
                echo json_encode(['success'=>true]);
                break;

            // 4.5) Cambiar estado
            case 'cambiar_estado_mongo':
                $id     = $_POST['cliente_id'] ?? '';
                $estado = $_POST['estado']      ?? '';
                if (!preg_match('/^[0-9a-f]{24}$/i',$id)) {
                    throw new Exception("ID inválido");
                }
                $mongoCollection->updateOne(
                    ['_id'=>new ObjectId($id)],
                    ['$set'=>['estado'=>$estado,'fecha_actualizacion'=>new UTCDateTime()]]
                );
                echo json_encode(['success'=>true]);
                break;

            // 4.6) Eliminar cliente
            case 'eliminar_mongo':
                $id = $_POST['cliente_id'] ?? '';
                if (!preg_match('/^[0-9a-f]{24}$/i',$id)) {
                    throw new Exception("ID inválido");
                }
                $mongoCollection->deleteOne(['_id'=>new ObjectId($id)]);
                echo json_encode(['success'=>true]);
                break;

            default:
                throw new Exception("Acción inválida");
        }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// 5) Control de vista
$vista      = $_GET['vista']      ?? 'clientes';
$view_mode  = $_GET['view']       ?? 'cards';
$cliente_id = $_GET['cliente_id'] ?? null;

// 6) Carga todos los clientes desde Mongo
$cursor   = $mongoCollection->find([], ['sort'=>['fecha_creacion'=>-1]]);
$clientes = [];
foreach ($cursor as $doc) {
    $c = $doc->getArrayCopy();
    $c['_id'] = (string)$doc['_id'];
    // crea formatos de fecha
    foreach (['fecha_creacion','fecha_actualizacion','fecha_registro'] as $f) {
        if (!empty($doc[$f]) && $doc[$f] instanceof UTCDateTime) {
            $c[$f.'_fmt'] = date('d/m/Y',$doc[$f]->toDateTime()->getTimestamp());
        } else {
            $c[$f.'_fmt'] = '';
        }
    }
    $clientes[] = $c;
}

// 7) Para tu perfil/editar MySQL (opcional)
$cliente_actual = null;
if (in_array($vista,['editar','perfil'],true) && $cliente_id) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id=?");
    $stmt->execute([$cliente_id]);
    $cliente_actual = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Clientes - Sistema ERP ELESS</title>
  <link rel="stylesheet" href="css/siderbarycabezal.css?v=1.0">
  <link rel="stylesheet" href="css/punto-venta.css">
  <link rel="stylesheet" href="css/clientes.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/header.php'; ?>

    <section class="content">
      <?php if ($vista==='clientes'): ?>
        <div class="clientes-container">
          <div class="clientes-header">
            <h1><i class="fas fa-handshake"></i> Clientes (MongoDB)</h1>
            <div class="clientes-info">
              <span>1-<?php echo count($clientes)?> / <?php echo count($clientes)?></span>
              <div class="clientes-controls">
                <button onclick="openCrearModal()">
                  <i class="fas fa-plus"></i> Nuevo Cliente
                </button>
              </div>
            </div>
          </div>

          <?php if ($view_mode==='cards'): ?>
          <div class="clientes-grid">
            <?php foreach($clientes as $c):
              $ini = strtoupper(mb_substr($c['nombre'],0,1,'UTF-8').
                                mb_substr($c['apellidos'],0,1,'UTF-8'));
              $bg  = $c['color_avatar'] ?? '#6366f1';
              $loc = trim(($c['ciudad']??'').', '.($c['pais']??''),', ');
            ?>
              <div class="cliente-card" onclick="verCliente('<?php echo $c['_id']?>')">
                <div class="cliente-header-card"
                     style="background: linear-gradient(135deg,<?=$bg?>,<?=$bg?>aa);">
                  <div class="client-avatar"><?=$ini?></div>
                  <div class="cliente-info">
                    <h3><?php echo htmlspecialchars($c['nombre'].' '.$c['apellidos'])?></h3>
                    <div class="company"><?php echo htmlspecialchars($c['nombre_empresa']?:'Individual')?></div>
                  </div>
                </div>
                <div class="cliente-card-body">
                  <div class="cliente-status">
                    <span class="status-badge <?php echo htmlspecialchars($c['estado']?:'activo')?>">
                      <?php echo ucfirst(htmlspecialchars($c['estado']?:'activo'))?>
                    </span>
                  </div>
                  <div class="cliente-contact-info">
                    <p><i class="fas fa-envelope"></i>
                      <?php echo htmlspecialchars($c['email']?:'—')?></p>
                    <p><i class="fas fa-phone"></i>
                      <?php echo htmlspecialchars($c['telefono']?:'—')?></p>
                    <p><i class="fas fa-map-marker-alt"></i>
                      <?php echo $loc?:'—'?></p>
                    <p><i class="fas fa-calendar-alt"></i>
                      Registrado: <?php echo $c['fecha_creacion_fmt']?></p>
                  </div>
                </div>
                <div class="cliente-actions">
                  <button class="btn-sm btn-primary"
                          onclick="event.stopPropagation(); editarCliente('<?php echo $c['_id']?>')">
                    <i class="fas fa-edit"></i> Editar
                  </button>
                  <?php if(($c['estado']??'')==='activo'): ?>
                    <button class="btn-sm btn-edit"
                            onclick="event.stopPropagation(); cambiarEstado('<?php echo $c['_id']?>','inactivo')">
                      <i class="fas fa-user-slash"></i> Desactivar
                    </button>
                  <?php else: ?>
                    <button class="btn-sm btn-success"
                            onclick="event.stopPropagation(); cambiarEstado('<?php echo $c['_id']?>','activo')">
                      <i class="fas fa-user-check"></i> Activar
                    </button>
                  <?php endif;?>
                  <button class="btn-sm btn-delete"
                          onclick="event.stopPropagation(); eliminarCliente('<?php echo $c['_id']?>')">
                    <i class="fas fa-trash"></i> Eliminar
                  </button>
                </div>
              </div>
            <?php endforeach;?>
          </div>
          <?php else: ?>
            <!-- rows view aquí si la usas -->
          <?php endif;?>
        </div>

        <!-- Modal de creación -->
        <div id="modalCrear" class="modal" style="display:none">
          <div class="modal-content">
            <div class="modal-header">
              <h3><i class="fas fa-plus"></i> Nuevo Cliente</h3>
              <button class="modal-close" onclick="closeCrearModal()">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form id="formCrearMongo" style="padding:1rem">
              <input type="hidden" name="action" value="crear_mongo">
              <!-- todos los campos -->
              <?php
              $campos = [
                ['label'=>'Tipo','name'=>'tipo','type'=>'text'],
                ['label'=>'Nombre*','name'=>'nombre','type'=>'text','required'=>true],
                ['label'=>'Apellidos*','name'=>'apellidos','type'=>'text','required'=>true],
                ['label'=>'Empresa','name'=>'nombre_empresa','type'=>'text'],
                ['label'=>'Email','name'=>'email','type'=>'email'],
                ['label'=>'Teléfono','name'=>'telefono','type'=>'text'],
                ['label'=>'Celular','name'=>'celular','type'=>'text'],
                ['label'=>'Dirección','name'=>'direccion','type'=>'text'],
                ['label'=>'Ciudad','name'=>'ciudad','type'=>'text'],
                ['label'=>'Estado','name'=>'estado','type'=>'text'],
                ['label'=>'Código Postal','name'=>'codigo_postal','type'=>'text'],
                ['label'=>'País','name'=>'pais','type'=>'text','value'=>'Peru'],
                ['label'=>'Tipo Documento','name'=>'documento_tipo','type'=>'text'],
                ['label'=>'Número Documento','name'=>'documento_numero','type'=>'text'],
                ['label'=>'Puesto Trabajo','name'=>'puesto_trabajo','type'=>'text'],
                ['label'=>'Sitio Web','name'=>'sitio_web','type'=>'url'],
                ['label'=>'Idioma','name'=>'idioma','type'=>'text','value'=>'Español'],
                ['label'=>'Etiquetas','name'=>'etiquetas','type'=>'text'],
                ['label'=>'Balance','name'=>'balance','type'=>'number','step'=>'0.01','value'=>'0.00'],
                ['label'=>'Activo','name'=>'activo','type'=>'checkbox','checked'=>true],
                ['label'=>'Tipo Cliente','name'=>'tipo_cliente','type'=>'text','value'=>'Individual'],
                ['label'=>'Industria','name'=>'industria','type'=>'text'],
                ['label'=>'Notas','name'=>'notas','type'=>'textarea'],
                ['label'=>'Contacto Principal Nombre','name'=>'contacto_principal_nombre','type'=>'text'],
                ['label'=>'Contacto Principal Email','name'=>'contacto_principal_email','type'=>'email'],
                ['label'=>'Contacto Principal Teléfono','name'=>'contacto_principal_telefono','type'=>'text'],
                ['label'=>'Color Avatar','name'=>'color_avatar','type'=>'color','value'=>'#6366f1'],
                ['label'=>'Estado Registro','name'=>'estado_registro','type'=>'text','value'=>'activo'],
                ['label'=>'Fecha Registro','name'=>'fecha_registro','type'=>'datetime-local'],
                ['label'=>'Tipo Identificación','name'=>'tipo_identificacion','type'=>'text'],
                ['label'=>'Número Identificación','name'=>'numero_identificacion','type'=>'text'],
                ['label'=>'Calle 1','name'=>'calle1','type'=>'text'],
                ['label'=>'Calle 2','name'=>'calle2','type'=>'text'],
                ['label'=>'Distrito','name'=>'distrito','type'=>'text'],
                ['label'=>'Estado/Provincia','name'=>'estado_provincia','type'=>'text'],
              ];
              foreach ($campos as $fld) {
                echo '<div class="form-group">';
                echo '<label>'.htmlspecialchars($fld['label']).'</label>';
                if ($fld['type']==='textarea') {
                  echo '<textarea name="'.$fld['name'].'"'.(isset($fld['required'])?' required':'').'>'.htmlspecialchars($fld['value']??'').'</textarea>';
                } else {
                  echo '<input '
                       .'type="'.$fld['type'].'" '
                       .'name="'.$fld['name'].'" '
                       .(isset($fld['step'])?' step="'.$fld['step'].'"':'')
                       .(isset($fld['value'])?' value="'.htmlspecialchars($fld['value']).'"':'')
                       .(isset($fld['required'])?' required':'')
                       .(isset($fld['checked'])?' checked':'')
                       .' >';
                }
                echo '</div>';
              }
              ?>
              <div style="text-align:right;margin-top:1rem;">
                <button type="button" class="btn-form secondary"
                        onclick="closeCrearModal()">Cancelar</button>
                <button type="submit" class="btn-form primary">Guardar</button>
              </div>
            </form>
          </div>
        </div>

      <?php elseif(in_array($vista,['nuevo','editar'],true)): ?>
        <!-- formulario MySQL aquí (si lo necesitas) -->
      <?php elseif($vista==='perfil'): ?>
        <!-- perfil MySQL aquí -->
      <?php endif; ?>
    </section>
  </div>

 <script src="js/clientes.js"></script>
</body>
</html>