<?php
// header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// asumimos $_SESSION['nombres'], $_SESSION['apellidos'] definidas
$nomArr       = explode(' ', trim($_SESSION['nombres']));
$apeArr       = explode(' ', trim($_SESSION['apellidos']));
$primerNombre = $nomArr[0] ?? '';
$primerApellido = $apeArr[0] ?? '';
$base = 'img/usuarios/';
$slug = strtolower($primerNombre . '-' . $primerApellido) . '.jpg';
$ruta = $base . $slug;
$foto = file_exists(__DIR__ . '/' . $ruta) ? $ruta : $base . 'default.png';
$nombreMostrar = trim($primerNombre . ' ' . $primerApellido);
?>
<header class="page-header">
  <button class="menu-toggle"><i class="fas fa-bars"></i></button>
  <div class="header-actions">
    <button><i class="fas fa-bell"></i><span>2</span></button>
    <button><i class="fas fa-envelope"></i><span>5</span></button>
    <div class="user-info">
      <img src="<?php echo htmlspecialchars($foto); ?>" alt="Usuario">
      <span><?php echo htmlspecialchars($nombreMostrar); ?></span>
    </div>
  </div>
</header>