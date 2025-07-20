document.addEventListener('DOMContentLoaded', () => {
  // Formulario de creación
  const formCrear = document.getElementById('formCrearMongo');
  if (formCrear) {
    formCrear.addEventListener('submit', function(e) {
      e.preventDefault();
      crearCliente();
    });
  }
});

// 1) Abrir/Cerrar modal de Nuevo Cliente
function openCrearModal() {
  document.getElementById('modalCrear').style.display = 'flex';
}
function closeCrearModal() {
  document.getElementById('modalCrear').style.display = 'none';
}

// 2) Crear cliente en MongoDB
function crearCliente() {
  const form = document.getElementById('formCrearMongo');
  const data = new FormData(form);
  data.set('action', 'crear_mongo');

  fetch('clientes.php', {
    method: 'POST',
    body: data
  })
  .then(res => res.json())
  .then(json => {
    if (json.success) {
      closeCrearModal();
      window.location.reload();
    } else {
      alert('Error al crear cliente:\n' + json.error);
    }
  })
  .catch(err => alert('Error de red:\n' + err));
}

// 3) Redirigir a edición (usas tu vista PHP de MySQL o podrías hacer un modal similar)
function editarCliente(id) {
  // si quieres que abra tu vista editar de PHP:
  window.location.href = `clientes.php?vista=editar&cliente_id=${encodeURIComponent(id)}`;
}

// 4) Cambiar sólo el estado
function cambiarEstado(id, nuevoEstado) {
  if (!confirm(`¿Estás seguro de cambiar el estado a "${nuevoEstado}"?`)) return;
  const data = new FormData();
  data.append('action', 'cambiar_estado_mongo');
  data.append('cliente_id', id);
  data.append('estado', nuevoEstado);

  fetch('clientes.php', {
    method: 'POST',
    body: data
  })
  .then(res => res.json())
  .then(json => {
    if (json.success) {
      window.location.reload();
    } else {
      alert('Error al cambiar estado:\n' + json.error);
    }
  })
  .catch(err => alert('Error de red:\n' + err));
}

// 5) Eliminar cliente
function eliminarCliente(id) {
  if (!confirm('¿Estás seguro de eliminar este cliente? Esta operación NO se puede deshacer.')) return;
  const data = new FormData();
  data.append('action', 'eliminar_mongo');
  data.append('cliente_id', id);

  fetch('clientes.php', {
    method: 'POST',
    body: data
  })
  .then(res => res.json())
  .then(json => {
    if (json.success) {
      window.location.reload();
    } else {
      alert('Error al eliminar:\n' + json.error);
    }
  })
  .catch(err => alert('Error de red:\n' + err));
}