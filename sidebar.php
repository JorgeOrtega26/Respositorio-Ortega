<?php
// sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>.modal-overlay {
  position: fixed;
  top: 0; left: 0;
  width: 100vw; height: 100vh;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
}
.modal-box {
  background: #fff;
  padding: 24px;
  border-radius: 8px;
  max-width: 360px;
  width: 90%;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}
.modal-box .modal-message {
  margin-bottom: 16px;
  font-size: 16px;
}
.modal-box .modal-actions button {
  margin: 0 8px;
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}
.btn-danger {
  background: #ef4444;
  color: #fff;
}
.btn-secondary {
  background: #6b7280;
  color: #fff;
}
.hidden {
  display: none !important;
}
.brand-logo{
  margin-left: 60px;
}
</style>
<aside class="sidebar">
  <div class="brand">
    <img src="img/logo/eless_black.png" alt="ELESS Logo" class="brand-logo">
    <button class="close-sidebar"><i class="fas fa-times"></i></button>
  </div>
  <div class="search-sidebar">
    <i class="fas fa-search"></i>
    <input type="text" placeholder="Buscar..." />
  </div>
  <nav>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <ul class="modules">
      <li><a href="punto-venta.php"><img src="img/iconos/puntodeventa.png" class="nav-icon"> Punto de Venta</a></li>
      <li><a href="contabilidad.php"><img src="img/iconos/contabilidad.png" class="nav-icon"> Contabilidad</a></li>
      <li><a href="compras.php"><img src="img/iconos/compras.png" class="nav-icon"> Compras</a></li>
      <li><a href="codigos-de-barra.php"><img src="img/iconos/codigodebarras.png" class="nav-icon"> Códigos de Barra</a></li>
      <li><a href="inventario.php"><img src="img/iconos/inventario.png" class="nav-icon"> Inventario</a></li>
      <li><a href="Documento.php"><img src="img/iconos/documentos.png" class="nav-icon"> Documentos</a></li>
      <li><a href="sitio-web.php"><img src="img/iconos/sitio web.png" class="nav-icon"> Sitio web</a></li>
      <li><a href="fabricacion.php"><img src="img/iconos/fabricacion.png" class="nav-icon"> Fabricación</a></li>
      <li><a href="nomina.php"><img src="img/iconos/nomina.png" class="nav-icon"> Nómina</a></li>
      <li><a href="evaluaciones.php"><img src="img/iconos/evaluacion.png" class="nav-icon"> Evaluaciones</a></li>
      <li><a href="gastos.php"><img src="img/iconos/gastos.png" class="nav-icon"> Gastos</a></li>
      <li><a href="calendarios.php"><img src="img/iconos/calendario.png" class="nav-icon"> Calendarios</a></li>
      <li><a href="reportes.php"><img src="img/iconos/resumenfinanciero.png" class="nav-icon">Reportes</a></li>
      <li><a href="clientes.php"><img src="img/iconos/contactos.png" class="nav-icon"> Clientes</a></li>
      <li><a href="empleados.php"><img src="img/iconos/Empleados.png" class="nav-icon"> Empleados</a></li>
      <li><a href="proveedores.php"><img src="img/iconos/proveedores.png" class="nav-icon">Proveedores</a></li>
    </ul>
  </nav>

   <!-- BOTÓN CERRAR SESIÓN -->
  <div class="sidebar-footer">
    <a href="#" class="logout-button">
      <i class="fas fa-sign-out-alt"></i> Cerrar sesión
    </a>
  </div>
</aside>

<!-- MODAL DE CONFIRMACIÓN -->
<div id="logoutModal" class="modal-overlay hidden">
  <div class="modal-box">
    <p class="modal-message">¿Estás seguro de cerrar sesión?</p>
    <div class="modal-actions">
      <button id="confirmLogout" class="btn btn-danger">Estoy seguro</button>
      <button id="cancelLogout"  class="btn btn-secondary">Cancelar</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.sidebar');
  document.querySelector('.menu-toggle')
          .addEventListener('click', () => sidebar.classList.toggle('open'));
  document.querySelector('.close-sidebar')
          .addEventListener('click', () => sidebar.classList.remove('open'));

  // Manejo del modal
  const logoutBtn      = document.querySelector('.logout-button');
  const logoutModal    = document.getElementById('logoutModal');
  const confirmLogout  = document.getElementById('confirmLogout');
  const cancelLogout   = document.getElementById('cancelLogout');

  logoutBtn.addEventListener('click', e => {
    e.preventDefault();
    logoutModal.classList.remove('hidden');
  });

  cancelLogout.addEventListener('click', () => {
    logoutModal.classList.add('hidden');
  });

  confirmLogout.addEventListener('click', () => {
    window.location.href = 'inicio-Sesion.php';
  });
});
</script>
