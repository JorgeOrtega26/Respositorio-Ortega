<?php
session_start();

// Conexión
$conexion = new mysqli("localhost","root","","sistema-erp-eless");
if ($conexion->connect_error) {
  die("Error de conexión: ".$conexion->connect_error);
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $correo    = $conexion->real_escape_string($_POST['correo']);
  $contrasena= $conexion->real_escape_string($_POST['contrasena']);

  $sql  = "SELECT * FROM iniciosesion WHERE Correo='$correo' AND Contraseña='$contrasena'";
  $res  = $conexion->query($sql);

  if ($res->num_rows === 1) {
    $u = $res->fetch_assoc();
    // Guardamos en sesión los datos que luego necesitemos
    $_SESSION['nombres']   = $u['Nombres'];
    $_SESSION['apellidos'] = $u['Apellidos'];
    $_SESSION['foto']      = 'img/usuarios/'.$u['Foto']; // asumiendo que el campo Foto existe

    // Redirigimos **al dashboard**, que aquí llamamos Inicio.php
    header("Location: pruebas.php");
    exit();
  }
  $mensaje = "❌ Correo o contraseña incorrectos";
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - ELESS</title>
  <link rel="stylesheet" href="./css/estilos.css" />
  <script src="https://kit.fontawesome.com/XXXXXXXXXX.js" crossorigin="anonymous"></script>
</head>
<body>
  
  <div class="login-container">
    <div class="card login-card">
      <h2>Iniciar Sesión</h2>
      <form method="post" action="">
        <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input type="email" name="correo" placeholder="Correo electrónico" required />
        </div>
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="contrasena" placeholder="Contraseña" required />
        </div>
        <a href="#" class="forgot">¿Olvidaste tu contraseña?</a>
        <button type="submit" class="btn btn-login">Iniciar Sesión</button>
        <?php if ($mensaje): ?>
          <p style="color:red; font-weight:bold; margin-top:10px;"><?php echo $mensaje; ?></p>
        <?php endif; ?>
      </form>
    </div>

    <div class="card welcome-card">
      <h2>¡Bienvenido!</h2>
      <p>Accede a todas las funciones de ELESS ingresando tus datos.</p>
      <a href="https://eless.com.pe" class="btn btn-register">Visita la Web</a>
    </div>
  </div>
</body>
</html>