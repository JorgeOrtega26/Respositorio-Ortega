<?php
// prueba.php
session_start();
if (!isset($_SESSION['nombres'], $_SESSION['apellidos'])) {
  header('Location: inicio-Sesion.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Prueba Sidebar + Header</title>
  <link rel="stylesheet" href="css/siderbarycabezal.css?v=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* reset y layout básico */
    * { box-sizing:border-box; margin:0; padding:0; }
    html, body { height:100%; }
    body { display:flex; font-family:sans-serif; }
    .main { flex:1; display:flex; flex-direction:column; }
    .content { flex:1; padding:20px; overflow:auto; }
  </style>
</head>
<body>

  <?php include __DIR__ . '/sidebar.php'; ?>


  <div class="main">
    <!-- 2a) header fijo -->
    <?php include __DIR__ . '/header.php'; ?>

    <!-- 2b) contenido cambiante -->
    <div class="content">
      <h1>¡Hola, <?php echo htmlspecialchars(explode(' ', trim($_SESSION['nombres']))[0]); ?>!</h1>
      <p>Este es un fragmento breve de contenido bajo tu sidebar y tu cabezal fijos.</p>
    </div>
  </div>

</body>
</html>