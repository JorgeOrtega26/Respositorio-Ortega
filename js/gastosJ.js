document.addEventListener("DOMContentLoaded", () => {
  // Referencias a elementos
  const nuevoGastoBtn = document.getElementById("nuevoGastoBtn")
  const nuevoGastoModal = document.getElementById("nuevoGastoModal")
  const cerrarModal = document.getElementById("cerrarModal")
  const cancelarBtn = document.getElementById("cancelarBtn")
  const gastoForm = document.getElementById("gastoForm")
  const fileUploadArea = document.querySelector(".file-upload-area")
  const fileInput = document.getElementById("recibo")
  const adjuntarReciboBtn = document.querySelector(".action-buttons .btn-primary")
  const actualizarBtn = document.getElementById("actualizarBtn")
  const subirBtn = document.querySelector(".btn-primary")

  // Variables de control
  let editandoGasto = false
  let gastoEditandoId = null
  let filtroActual = "todos" // nuevo: controla qué gastos mostrar
  let gastosOriginales = [] // nuevo: almacena todos los gastos

  // ========== INICIALIZACIÓN ==========
  cargarGastos()
  configurarFiltrosTarjetas()
  configurarDropdownReportes() // nuevo

  // ========== CONFIGURAR DROPDOWN DE REPORTES ==========
  function configurarDropdownReportes() {
    // Asegurar que el enlace de análisis funcione
    const analisisLink = document.querySelector('a[href="analisis_gastos.php"]')
    if (analisisLink) {
      analisisLink.addEventListener("click", (e) => {
        console.log("🔗 Navegando a análisis de gastos...")
        // Permitir navegación normal
      })
    }

    // Configurar hover del dropdown
    const dropdown = document.querySelector(".dropdown")
    const dropdownMenu = document.querySelector(".dropdown-menu")

    if (dropdown && dropdownMenu) {
      dropdown.addEventListener("mouseenter", () => {
        dropdownMenu.style.opacity = "1"
        dropdownMenu.style.visibility = "visible"
        dropdownMenu.style.transform = "translateY(0)"
      })

      dropdown.addEventListener("mouseleave", () => {
        dropdownMenu.style.opacity = "0"
        dropdownMenu.style.visibility = "hidden"
        dropdownMenu.style.transform = "translateY(-10px)"
      })
    }
  }

  // ========== CONFIGURAR FILTROS DE TARJETAS ==========
  function configurarFiltrosTarjetas() {
    // Tarjeta "En espera de aprobación"
    const tarjetaAprobacion = document.getElementById("totalAprobacion")?.closest(".summary-card, .card, .stat-card")
    if (tarjetaAprobacion) {
      tarjetaAprobacion.style.cursor = "pointer"
      tarjetaAprobacion.addEventListener("click", () => {
        filtroActual = "aprobacion"
        actualizarTituloSeccion("En espera de aprobación")
        filtrarYMostrarGastos()
      })
    }

    // Tarjeta "Gastos reembolsados"
    const tarjetaReembolso = document.getElementById("totalReembolso")?.closest(".summary-card, .card, .stat-card")
    if (tarjetaReembolso) {
      tarjetaReembolso.style.cursor = "pointer"
      tarjetaReembolso.addEventListener("click", () => {
        filtroActual = "reembolsados"
        actualizarTituloSeccion("Gastos reembolsados")
        filtrarYMostrarGastos()
      })
    }

    // Tarjeta "Total de gastos" - mostrar todos
    const tarjetaTotal = document.getElementById("totalPorEnviar")?.closest(".summary-card, .card, .stat-card")
    if (tarjetaTotal) {
      tarjetaTotal.style.cursor = "pointer"
      tarjetaTotal.addEventListener("click", () => {
        filtroActual = "todos"
        actualizarTituloSeccion("Mis gastos")
        filtrarYMostrarGastos()
      })
    }
  }

  // ========== ACTUALIZAR TÍTULO DE SECCIÓN ==========
  function actualizarTituloSeccion(titulo) {
    const tituloElemento = document.querySelector("h1, .page-title, .section-title")
    if (tituloElemento) {
      tituloElemento.textContent = titulo
    }

    // También actualizar el título en el área de gastos si existe
    const tituloGastos = document.querySelector(".gastos-header h2, .gastos-title")
    if (tituloGastos) {
      tituloGastos.textContent = titulo
    }
  }

  // ========== FILTRAR Y MOSTRAR GASTOS ==========
  function filtrarYMostrarGastos() {
    let gastosFiltrados = []

    switch (filtroActual) {
      case "aprobacion":
        // Gastos en borrador (en espera de aprobación)
        gastosFiltrados = gastosOriginales.filter((gasto) => (gasto.estado || "borrador") === "borrador")
        break

      case "reembolsados":
        // Gastos aprobados pagados por empleado
        gastosFiltrados = gastosOriginales.filter(
          (gasto) => (gasto.estado || "borrador") === "aprobado" && gasto.pagado_por === "empleado",
        )
        break

      case "todos":
      default:
        // Todos los gastos
        gastosFiltrados = gastosOriginales
        break
    }

    mostrarGastos(gastosFiltrados)
    mostrarEstadisticasFiltro(gastosFiltrados)
  }

  // ========== MOSTRAR ESTADÍSTICAS DEL FILTRO ==========
  function mostrarEstadisticasFiltro(gastosFiltrados) {
    const totalFiltrado = gastosFiltrados.reduce((sum, gasto) => sum + Number.parseFloat(gasto.total || 0), 0)

    const infoFiltro = document.querySelector(".filtro-info") || crearElementoInfoFiltro()

    // Vaciar mensaje y eliminar el botón
    infoFiltro.innerHTML = `
    <div class="filtro-actual">
      <span></span>
    </div>
  `
  }

  // ========== CREAR ELEMENTO INFO FILTRO ==========
  function crearElementoInfoFiltro() {
    const gastosLista = document.getElementById("gastosLista")
    if (!gastosLista) return null

    const infoFiltro = document.createElement("div")
    infoFiltro.className = "filtro-info"
    gastosLista.parentNode.insertBefore(infoFiltro, gastosLista)
    return infoFiltro
  }

  // ========== LIMPIAR FILTRO ==========
  window.limpiarFiltro = () => {
    filtroActual = "todos"
    actualizarTituloSeccion("Mis gastos")
    filtrarYMostrarGastos()
  }

  // ========== FUNCIONALIDAD BOTÓN "NUEVO" DEL MODAL ==========
  function configurarBotonNuevo() {
    const botonNuevoModal = document.querySelector(".tab-btn.active")

    if (botonNuevoModal) {
      botonNuevoModal.addEventListener("click", (e) => {
        e.preventDefault()
        console.log('🆕 Click en botón "Nuevo" - Preparando formulario nuevo')

        // Limpiar formulario para nuevo gasto
        if (gastoForm) gastoForm.reset()
        restaurarBotonAdjuntar()
        editandoGasto = false
        gastoEditandoId = null

        // Cambiar texto del botón submit
        const submitBtn = document.querySelector(".form-actions .btn-primary")
        if (submitBtn) {
          submitBtn.textContent = "Guardar Gasto"
        }

        // Asegurar que el título sea correcto
        const modalTitle = document.querySelector(".tab-title")
        if (modalTitle) {
          modalTitle.textContent = "Mis gastos"
        }
      })
      console.log('✅ Evento agregado al botón "Nuevo"')
    }
  }

  // Ocultar iconos del modal (basado en el HTML real)
  function ocultarIconosModal() {
    const iconos = document.querySelectorAll(`
      .tab-subtitle .fa-cog,
      .tab-subtitle .fa-upload
    `)

    iconos.forEach((icono) => {
      if (icono) {
        icono.style.display = "none"
      }
    })
  }

  // ========== FUNCIONALIDAD BOTÓN SUBIR ==========
  if (subirBtn) {
    subirBtn.addEventListener("click", () => {
      const inputFile = document.createElement("input")
      inputFile.type = "file"
      inputFile.accept = "image/*,.pdf"
      inputFile.style.display = "none"

      inputFile.addEventListener("change", (e) => {
        if (e.target.files.length > 0) {
          const file = e.target.files[0]
          abrirModalConArchivo(file)
        }
      })

      document.body.appendChild(inputFile)
      inputFile.click()
      document.body.removeChild(inputFile)
    })
  }

  // ========== MODAL FUNCTIONALITY ==========
  if (nuevoGastoBtn) {
    nuevoGastoBtn.addEventListener("click", () => {
      abrirModal()
    })
  }

  function abrirModal(gasto = null) {
    editandoGasto = gasto !== null
    gastoEditandoId = gasto ? gasto.id : null

    const modalTitle = document.querySelector(".tab-title")
    if (modalTitle) {
      modalTitle.textContent = editandoGasto ? "Editar gasto" : "Mis gastos"
    }

    const submitBtn = document.querySelector(".form-actions .btn-primary")
    if (submitBtn) {
      submitBtn.textContent = editandoGasto ? "Actualizar Gasto" : "Guardar Gasto"
    }

    if (editandoGasto && gasto) {
      llenarFormulario(gasto)
    }

    nuevoGastoModal.classList.add("active")

    // Agregar clase 'editing' al modal container cuando se está editando
    const modalContainer = document.querySelector(".modal-container")
    if (modalContainer) {
      if (editandoGasto) {
        modalContainer.classList.add("editing")
      } else {
        modalContainer.classList.remove("editing")
      }
    }

    document.body.style.overflow = "hidden"
    setTimeout(() => {
      ocultarIconosModal()
      configurarBotonNuevo()
    }, 50)
  }

  function abrirModalConArchivo(file) {
    abrirModal()
    const dataTransfer = new DataTransfer()
    dataTransfer.items.add(file)
    fileInput.files = dataTransfer.files
    mostrarVistaPrevia(file)
    actualizarBotonAdjuntar(file)
  }

  function llenarFormulario(gasto) {
    document.getElementById("descripcion").value = gasto.descripcion || ""
    document.getElementById("categoria").value = gasto.categoria || ""
    document.getElementById("fecha").value = gasto.fecha_gasto || ""
    document.getElementById("total").value = gasto.total || ""
    document.getElementById("metodo_pago").value = gasto.metodo_pago || ""
    document.getElementById("notas").value = gasto.notas || ""

    const pagadoPorRadios = document.querySelectorAll('input[name="pagado_por"]')
    pagadoPorRadios.forEach((radio) => {
      radio.checked = radio.value === gasto.pagado_por
    })

    if (gasto.archivo_recibo) {
      mostrarArchivoExistente(gasto.archivo_recibo)
    }
  }

  function mostrarArchivoExistente(nombreArchivo) {
    if (fileUploadArea) {
      fileUploadArea.innerHTML = `
        <div class="file-preview">
          <i class="fas fa-file" style="font-size: 48px; color: #4a90e2;"></i>
          <p><strong>${nombreArchivo}</strong></p>
          <p>Archivo actual</p>
          <button type="button" class="btn btn-secondary btn-sm" onclick="cambiarArchivo()">
            <i class="fas fa-exchange-alt"></i> Cambiar archivo
          </button>
        </div>
      `
    }
    if (adjuntarReciboBtn) {
      adjuntarReciboBtn.innerHTML = `<i class="fas fa-check"></i> Archivo actual`
      adjuntarReciboBtn.style.background = "#10b981"
    }
  }

  function cerrarModalGasto() {
    if (nuevoGastoModal) {
      nuevoGastoModal.classList.remove("active")

      // Remover clase 'editing' al cerrar
      const modalContainer = document.querySelector(".modal-container")
      if (modalContainer) {
        modalContainer.classList.remove("editing")
      }

      document.body.style.overflow = "auto"
    }
    if (gastoForm) gastoForm.reset()
    restaurarBotonAdjuntar()

    editandoGasto = false
    gastoEditandoId = null

    const modalTitle = document.querySelector(".tab-title")
    if (modalTitle) {
      modalTitle.textContent = "Mis gastos"
    }
  }

  // Event listeners para cerrar modal
  if (cerrarModal) cerrarModal.addEventListener("click", cerrarModalGasto)
  if (cancelarBtn) cancelarBtn.addEventListener("click", cerrarModalGasto)

  if (nuevoGastoModal) {
    nuevoGastoModal.addEventListener("click", (e) => {
      if (e.target === nuevoGastoModal) {
        cerrarModalGasto()
      }
    })
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && nuevoGastoModal && nuevoGastoModal.classList.contains("active")) {
      cerrarModalGasto()
    }
  })

  // ========== ACTUALIZAR GASTOS ==========
  if (actualizarBtn) {
    actualizarBtn.addEventListener("click", () => {
      cargarGastos()
    })
  }

  // ========== FORM SUBMISSION ==========
  if (gastoForm) {
    gastoForm.addEventListener("submit", async (e) => {
      e.preventDefault()

      const formData = new FormData(gastoForm)

      if (editandoGasto && gastoEditandoId) {
        formData.append("id", gastoEditandoId)
        formData.append("accion", "editar")
      }

      try {
        const endpoint = editandoGasto ? "Gastos.php?action=editar_gasto" : "Gastos.php?action=crear_gasto"
        const response = await fetch(endpoint, {
          method: "POST",
          body: formData,
        })

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }

        const result = await response.json()

        if (result.success) {
          const titulo = editandoGasto ? "¡Gasto actualizado exitosamente!" : "¡Gasto creado exitosamente!";
          const mensaje = editandoGasto ? "Los cambios han sido guardados correctamente" : "El gasto ha sido registrado en el sistema";

          mostrarModalExito(titulo, mensaje);
          cerrarModalGasto();
          cargarGastos();
        } else {
          alert("❌ Error al " + (editandoGasto ? "actualizar" : "guardar") + ": " + result.message)
        }
      } catch (error) {
        console.error("💥 Error:", error)
        alert("❌ Error al procesar el gasto: " + error.message)
      }
    })
  }

  // ========== CARGAR Y MOSTRAR GASTOS ==========
  async function cargarGastos() {
    const loadingState = document.getElementById("loadingState")
    const emptyState = document.getElementById("emptyState")
    const gastosLista = document.getElementById("gastosLista")

    if (loadingState) loadingState.style.display = "flex"
    if (emptyState) emptyState.style.display = "none"
    if (gastosLista) gastosLista.innerHTML = ""

    try {
      const response = await fetch("Gastos.php?action=obtener_gastos")
      const result = await response.json()

      if (result.success) {
        gastosOriginales = result.gastos // Guardar todos los gastos

        if (loadingState) loadingState.style.display = "none"

        if (gastosOriginales.length === 0) {
          if (emptyState) emptyState.style.display = "flex"
        } else {
          filtrarYMostrarGastos() // Usar filtro en lugar de mostrar todos
          actualizarResumen(gastosOriginales)
        }
      } else {
        throw new Error(result.message)
      }
    } catch (error) {
      console.error("Error al cargar gastos:", error)
      if (loadingState) loadingState.style.display = "none"
      if (gastosLista) {
        gastosLista.innerHTML = `
          <div class="error-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Error al cargar los gastos: ${error.message}</p>
            <button onclick="cargarGastos()" class="btn btn-primary">Reintentar</button>
          </div>
        `
      }
    }
  }

  function mostrarGastos(gastos) {
    const gastosLista = document.getElementById("gastosLista")
    if (!gastosLista) return

    if (gastos.length === 0) {
      gastosLista.innerHTML = `
        <div class="empty-filtered-state">
          <i class="fas fa-filter"></i>
          <p>No hay gastos para mostrar con el filtro actual</p>
        </div>
      `
      return
    }

    gastosLista.innerHTML = gastos
      .map(
        (gasto) => `
      <div class="gasto-row">
        <div class="gasto-cell">
          <div class="empleado-info">
            <div class="empleado-avatar">
              ${(gasto.empleado || "Usuario").charAt(0).toUpperCase()}
            </div>
            <span>${gasto.empleado || "Usuario"}</span>
          </div>
        </div>
        <div class="gasto-cell">
          <strong>${gasto.descripcion || "Sin descripción"}</strong>
        </div>
        <div class="gasto-cell">
          ${formatearFecha(gasto.fecha_gasto)}
        </div>
        <div class="gasto-cell">
          <span class="categoria-badge categoria-${gasto.categoria || "otros"}">
            ${gasto.categoria || "Sin categoría"}
          </span>
        </div>
        <div class="gasto-cell">
          <span class="metodo-pago">
            ${formatearMetodoPago(gasto.metodo_pago)}
          </span>
        </div>
        <div class="gasto-cell">
          <span class="pagado-por ${gasto.pagado_por || "empleado"}">
            ${gasto.pagado_por === "empleado" ? "Empleado" : "Empresa"}
          </span>
        </div>
        <div class="gasto-cell">
          <strong class="total">S/ ${Number.parseFloat(gasto.total || 0).toFixed(2)}</strong>
        </div>
        <div class="gasto-cell">
          ${crearEstadoBadge(gasto)}
        </div>
        <div class="gasto-cell">
          <div class="acciones">
            ${gasto.archivo_recibo
            ? `<button onclick="verRecibo('${gasto.archivo_recibo}')" class="btn-accion" title="Ver recibo">
                     <i class="fas fa-eye"></i>
                   </button>`
            : ""
          }
            <button onclick="editarGasto(${gasto.id})" class="btn-accion" title="Editar">
              <i class="fas fa-edit"></i>
            </button>
            <button onclick="eliminarGasto(${gasto.id})" class="btn-accion btn-danger" title="Eliminar">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
    `,
      )
      .join("")
  }

  // ========== NUEVA FUNCIÓN PARA CREAR ESTADO CON DROPDOWN ==========
  function crearEstadoBadge(gasto) {
    const estado = gasto.estado || "borrador"
    let selectedBorrador = ""
    let selectedAprobado = ""
    let selectedDesaprobado = ""

    if (estado === "borrador") {
      return `
        <div class="estado-dropdown">
         <select onchange="cambiarEstado(${gasto.id}, this.value)" class="estado-select">
  <option value="borrador" ${estado === "borrador" ? "selected" : ""}>Borrador</option>
  <option value="aprobado" ${estado === "aprobado" ? "selected" : ""}>Aprobar gasto</option>
  <option value="desaprobado" ${estado === "desaprobado" ? "selected" : ""}>Desaprobar gasto</option>
</select>
        </div>
      `
    } else if (estado === "aprobado") {
      return `
        <span class="estado-badge estado-aprobado">
          Aprobado
        </span>
      `
    } else if (estado === "desaprobado") {
      return `
        <span class="estado-badge estado-desaprobado">
          Desaprobado
        </span>
      `
    } else {
      // Fallback for any other unexpected status
      return `
        <span class="estado-badge estado-otros">
          ${estado.charAt(0).toUpperCase() + estado.slice(1)}
        </span>
      `
    }
  }

  // ========== FUNCIÓN PARA CAMBIAR ESTADO ==========
  window.cambiarEstado = async (id, nuevoEstado) => {
    console.log(`[DEBUG] Intentando cambiar estado para gasto ID: ${id} a: ${nuevoEstado}`) // Nuevo log
    if (nuevoEstado === "borrador") return // No hacer nada si selecciona borrador

    try {
      const response = await fetch("Gastos.php?action=actualizar_estado", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id: id,
          estado: nuevoEstado,
        }),
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const result = await response.json()
      console.log("[DEBUG] Respuesta del servidor al cambiar estado:", result) // Nuevo log

      if (result.success) {
        const mensaje = nuevoEstado === "aprobado" ? "✅ Gasto aprobado exitosamente" : "❌ Gasto desaprobado"
        mostrarModalExito(mensaje, "")
        cargarGastos() // Recargar la lista
      } else {
        mostrarModalExito("❌ Error al actualizar estado", result.message)
      }
    } catch (error) {
      console.error("Error al cambiar estado:", error)
      mostrarModalExito("❌ Error al cambiar el estado del gasto", error.message)
    }
  }

  function actualizarResumen(gastos) {
    let totalGeneral = 0
    let totalAprobacion = 0
    let totalReembolso = 0

    gastos.forEach((gasto) => {
      const total = Number.parseFloat(gasto.total)
      const estado = gasto.estado || "borrador"
      const pagadoPor = gasto.pagado_por

      // Total general: todos los gastos
      if (estado === "aprobado") {
        totalGeneral += total
      }
      // En espera de aprobación: gastos en borrador
      if (estado === "borrador") {
        totalAprobacion += total
      }


      // Gastos reembolsados: gastos aprobados pagados por empleado
      if (estado === "aprobado" && pagadoPor === "empleado") {
        totalReembolso += total
      }
    })

    // Actualizar las tarjetas
    const totalPorEnviarEl = document.getElementById("totalPorEnviar")
    const totalAprobacionEl = document.getElementById("totalAprobacion")
    const totalReembolsoEl = document.getElementById("totalReembolso")

    if (totalPorEnviarEl) totalPorEnviarEl.textContent = `S/ ${totalGeneral.toFixed(2)}`
    if (totalAprobacionEl) totalAprobacionEl.textContent = `S/ ${totalAprobacion.toFixed(2)}`
    if (totalReembolsoEl) totalReembolsoEl.textContent = `S/ ${totalReembolso.toFixed(2)}`
  }

  // ========== HELPER FUNCTIONS ==========
  function formatearFecha(fecha) {
    const date = new Date(fecha)
    return date.toLocaleDateString("es-PE", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    })
  }

  function formatearMetodoPago(metodo) {
    const metodos = {
      efectivo: "Efectivo",
      tarjeta_debito: "T. Débito",
      tarjeta_credito: "T. Crédito",
      yape: `<img src="./Gastos/yape.png" alt="Yape" style="height: 20px; vertical-align: middle; margin-right: 5px;">Yape`,
      plin: `<img src="./Gastos/plin.png" alt="Plin" style="height: 20px; vertical-align: middle; margin-right: 5px;">Plin`,
      tunki: `<img src="./Gastos/tunki.png" alt="Tunki" style="height: 20px; vertical-align: middle; margin-right: 5px;">Tunki`,
      otros: "Otros",
    }
    return metodos[metodo] || metodo
  }

  // ========== FUNCIONES GLOBALES ==========
  window.verRecibo = (archivo) => {
    window.open(`./uploads/recibos/${archivo}`, "_blank")
  }

  window.editarGasto = async (id) => {
    try {
      const response = await fetch(`Gastos.php?action=obtener_gasto&id=${id}`)
      const result = await response.json()

      if (result.success) {
        abrirModal(result.gasto)
      } else {
        alert("Error al cargar el gasto: " + result.message)
      }
    } catch (error) {
      console.error("Error al cargar gasto:", error)
      alert("Error al cargar el gasto")
    }
  }

  window.eliminarGasto = async (id) => {
    if (confirm("¿Estás seguro de que quieres eliminar este gasto?")) {
      try {
        const response = await fetch("Gastos.php?action=eliminar_gasto", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ id: id }),
        })

        const result = await response.json()

        if (result.success) {
          alert("✅ Gasto eliminado exitosamente")
          cargarGastos()
        } else {
          alert("❌ Error al eliminar: " + result.message)
        }
      } catch (error) {
        console.error("Error al eliminar gasto:", error)
        alert("❌ Error al eliminar el gasto")
      }
    }
  }

  window.cargarGastos = cargarGastos
  window.cambiarArchivo = () => {
    if (fileInput) fileInput.click()
  }

  // ========== FILE UPLOAD FUNCTIONALITY ==========
  if (adjuntarReciboBtn && fileInput) {
    adjuntarReciboBtn.addEventListener("click", (e) => {
      e.preventDefault()
      fileInput.click()
    })
  }

  if (fileInput) {
    fileInput.addEventListener("change", (e) => {
      if (e.target.files.length > 0) {
        const file = e.target.files[0]
        mostrarVistaPrevia(file)
        actualizarBotonAdjuntar(file)
      }
    })
  }

  if (fileUploadArea) {
    fileUploadArea.addEventListener("dragover", (e) => {
      e.preventDefault()
      fileUploadArea.style.borderColor = "#4A90E2"
      fileUploadArea.style.background = "#f0faff"
    })

    fileUploadArea.addEventListener("dragleave", (e) => {
      e.preventDefault()
      fileUploadArea.style.borderColor = "#d1d5db"
      fileUploadArea.style.background = "transparent"
    })

    fileUploadArea.addEventListener("drop", (e) => {
      e.preventDefault()
      fileUploadArea.style.borderColor = "#d1d5db"
      fileUploadArea.style.background = "transparent"

      const files = e.dataTransfer.files
      if (files.length > 0 && fileInput) {
        fileInput.files = files
        mostrarVistaPrevia(files[0])
        actualizarBotonAdjuntar(files[0])
      }
    })
  }

  function mostrarVistaPrevia(file) {
    if (!fileUploadArea) return

    const reader = new FileReader()
    reader.onload = (e) => {
      fileUploadArea.innerHTML = `
        <div class="file-preview">
          ${file.type.startsWith("image/")
          ? `<img src="${e.target.result}" alt="Vista previa" style="max-width: 200px; max-height: 200px; border-radius: 8px;">`
          : `<i class="fas fa-file-pdf" style="font-size: 48px; color: #ef4444;"></i>`
        }
          <p><strong>${file.name}</strong></p>
          <p>${(file.size / 1024 / 1024).toFixed(2)} MB</p>
          <button type="button" class="btn btn-secondary btn-sm" onclick="eliminarArchivo()">
            <i class="fas fa-trash"></i> Eliminar
          </button>
        </div>
      `
    }
    reader.readAsDataURL(file)
  }

  function actualizarBotonAdjuntar(file) {
    if (adjuntarReciboBtn) {
      adjuntarReciboBtn.innerHTML = `<i class="fas fa-check"></i> Recibo adjuntado`
      adjuntarReciboBtn.style.background = "#10b981"
    }
  }

  function restaurarBotonAdjuntar() {
    if (adjuntarReciboBtn) {
      adjuntarReciboBtn.innerHTML = `<i class="fas fa-paperclip"></i> Adjuntar recibo`
      adjuntarReciboBtn.style.background = "#7c3aed"
    }
    if (fileUploadArea) {
      fileUploadArea.innerHTML = `
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Arrastra un archivo aquí o haz clic para seleccionar</p>
      `
    }
  }

  window.eliminarArchivo = () => {
    if (fileInput) fileInput.value = ""
    restaurarBotonAdjuntar()
  }

  console.log("✅ Sistema de gastos inicializado correctamente")


  // ========== ANÁLISIS DE GASTOS - AGREGAR AL FINAL DE gastosJ.js ==========

  // Variables para los gráficos
  let chartInstance = null;
  let gastosParaAnalisis = [];

  // ========== CONFIGURAR TAB DE ANÁLISIS ==========
  function configurarAnalisisTab() {
    const analisisTab = document.getElementById('analisisGastosTab');
    const misGastosTab = document.getElementById('misGastosTab');
    const analisisSection = document.getElementById('analisisGastosSection');
    const gastosTable = document.querySelector('.gastos-table');
    const gastosSummary = document.querySelector('.gastos-summary');

    if (analisisTab && analisisSection) {
      analisisTab.addEventListener('click', () => {
        console.log("🔄 Cambiando a vista de análisis...");

        // Cambiar tabs activos
        document.querySelectorAll('.nav-item').forEach(tab => tab.classList.remove('active'));
        analisisTab.classList.add('active');

        // Mostrar análisis, ocultar tabla y resumen
        analisisSection.style.display = 'block';
        if (gastosTable) gastosTable.style.display = 'none';
        if (gastosSummary) gastosSummary.style.display = 'none';

        // Generar análisis con datos actuales
        generarAnalisisCompleto();
      });
    }

    if (misGastosTab) {
      misGastosTab.addEventListener('click', () => {
        console.log("🔄 Cambiando a vista de gastos...");

        // Cambiar tabs activos
        document.querySelectorAll('.nav-item').forEach(tab => tab.classList.remove('active'));
        misGastosTab.classList.add('active');

        // Mostrar tabla y resumen, ocultar análisis
        if (analisisSection) analisisSection.style.display = 'none';
        if (gastosTable) gastosTable.style.display = 'block';
        if (gastosSummary) gastosSummary.style.display = 'grid';
      });
    }
  }

  // ========== FILTRAR SOLO GASTOS APROBADOS ==========
  function filtrarGastosAprobados(gastos) {
    return gastos.filter(gasto => (gasto.estado || 'borrador') === 'aprobado');
  }

  // ========== AGRUPAR POR CATEGORÍA ==========
  function agruparPorCategoria(gastos) {
    const agrupados = {};

    gastos.forEach(gasto => {
      const categoria = gasto.categoria || 'otros';
      if (!agrupados[categoria]) {
        agrupados[categoria] = {
          nombre: categoria,
          cantidad: 0,
          total: 0,
          gastos: []
        };
      }

      agrupados[categoria].cantidad++;
      agrupados[categoria].total += parseFloat(gasto.total || 0);
      agrupados[categoria].gastos.push(gasto);
    });

    return agrupados;
  }

  // ========== GENERAR ANÁLISIS COMPLETO ==========
  async function generarAnalisisCompleto() {
    console.log("🔄 Generando análisis completo...");

    // Usar los datos que ya tienes cargados
    const gastosAprobados = filtrarGastosAprobados(gastosOriginales);
    const datosAgrupados = agruparPorCategoria(gastosAprobados);

    console.log("📊 Gastos aprobados:", gastosAprobados.length);
    console.log("📊 Categorías encontradas:", Object.keys(datosAgrupados));

    // Actualizar estadísticas
    actualizarEstadisticasAnalisis(gastosAprobados, datosAgrupados);

    // Generar gráfico
    generarGraficoAnalisis(datosAgrupados);
  }

  // ========== ACTUALIZAR ESTADÍSTICAS ==========
  function actualizarEstadisticasAnalisis(gastos, agrupados) {
    const totalAprobado = gastos.reduce((sum, gasto) => sum + parseFloat(gasto.total || 0), 0);
    const cantidadGastos = gastos.length;
    const promedioGasto = cantidadGastos > 0 ? totalAprobado / cantidadGastos : 0;

    // Encontrar categoría principal
    let categoriaPrincipal = '-';
    let maxTotal = 0;
    Object.keys(agrupados).forEach(categoria => {
      if (agrupados[categoria].total > maxTotal) {
        maxTotal = agrupados[categoria].total;
        categoriaPrincipal = categoria.charAt(0).toUpperCase() + categoria.slice(1);
      }
    });

    // Actualizar elementos del DOM
    const gastoPromedio = document.getElementById('gastoPromedio');
    const categoriaPrincipalEl = document.getElementById('categoriaPrincipal');
    const totalMes = document.getElementById('totalMes');

    if (gastoPromedio) gastoPromedio.textContent = `S/ ${promedioGasto.toFixed(2)}`;
    if (categoriaPrincipalEl) categoriaPrincipalEl.textContent = categoriaPrincipal;
    if (totalMes) totalMes.textContent = `S/ ${totalAprobado.toFixed(2)}`;
  }

  // ========== GENERAR GRÁFICO ==========
  function generarGraficoAnalisis(datosAgrupados) {
    const canvas = document.getElementById('gastosChart');
    if (!canvas) {
      console.error("❌ Canvas 'gastosChart' no encontrado");
      return;
    }

    // Destruir gráfico anterior si existe
    if (chartInstance) {
      chartInstance.destroy();
    }

    const ctx = canvas.getContext('2d');
    const categorias = Object.keys(datosAgrupados);
    const totales = categorias.map(cat => datosAgrupados[cat].total);

    // Colores para las categorías
    const colores = {
      'alimentacion': '#10B981',
      'transporte': '#3B82F6',
      'hospedaje': '#F59E0B',
      'materiales': '#8B5CF6',
      'otros': '#6B7280'
    };

    const backgroundColors = categorias.map(cat => colores[cat] || '#6B7280');

    try {
      chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: categorias.map(cat => cat.charAt(0).toUpperCase() + cat.slice(1)),
          datasets: [{
            label: 'Gastos Aprobados por Categoría (S/)',
            data: totales,
            backgroundColor: backgroundColors,
            borderColor: backgroundColors.map(color => color + '80'),
            borderWidth: 1,
            borderRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            title: {
              display: true,
              text: 'Gastos Aprobados por Categoría',
              font: {
                size: 16,
                weight: 'bold'
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function (value) {
                  return 'S/ ' + value.toFixed(2);
                }
              }
            }
          }
        }
      });

      console.log("✅ Gráfico creado exitosamente");
    } catch (error) {
      console.error("❌ Error al crear gráfico:", error);
    }
  }

  // ========== CONFIGURAR CONTROLES DE GRÁFICO ==========
  function configurarControlesGrafico() {
    const chartBtns = document.querySelectorAll('.chart-btn');

    chartBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        // Remover active de todos los botones
        chartBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const chartType = this.dataset.chart;
        console.log("🔄 Cambiando tipo de gráfico a:", chartType);

        // Regenerar gráfico con nuevo tipo
        const gastosAprobados = filtrarGastosAprobados(gastosOriginales);
        const datosAgrupados = agruparPorCategoria(gastosAprobados);

        generarGraficoConTipo(datosAgrupados, chartType);
      });
    });
  }

  // ========== GENERAR GRÁFICO CON TIPO ESPECÍFICO ==========
  function generarGraficoConTipo(datosAgrupados, tipo) {
    const canvas = document.getElementById('gastosChart');
    if (!canvas) return;

    // Destruir gráfico anterior
    if (chartInstance) {
      chartInstance.destroy();
    }

    const ctx = canvas.getContext('2d');
    const categorias = Object.keys(datosAgrupados);
    const totales = categorias.map(cat => datosAgrupados[cat].total);

    const colores = {
      'alimentacion': '#10B981',
      'transporte': '#3B82F6',
      'hospedaje': '#F59E0B',
      'materiales': '#8B5CF6',
      'otros': '#6B7280'
    };

    const backgroundColors = categorias.map(cat => colores[cat] || '#6B7280');

    let chartConfig = {
      type: tipo === 'pie' ? 'doughnut' : tipo,
      data: {
        labels: categorias.map(cat => cat.charAt(0).toUpperCase() + cat.slice(1)),
        datasets: [{
          label: 'Gastos Aprobados (S/)',
          data: totales,
          backgroundColor: backgroundColors,
          borderColor: tipo === 'pie' ? '#ffffff' : backgroundColors.map(color => color + '80'),
          borderWidth: tipo === 'pie' ? 2 : 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: tipo === 'pie',
            position: 'bottom'
          }
        }
      }
    };

    // Configuraciones específicas por tipo
    if (tipo === 'bar' || tipo === 'line') {
      chartConfig.options.scales = {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function (value) {
              return 'S/ ' + value.toFixed(2);
            }
          }
        }
      };
    }

    try {
      chartInstance = new Chart(ctx, chartConfig);
      console.log(`✅ Gráfico ${tipo} creado exitosamente`);
    } catch (error) {
      console.error(`❌ Error al crear gráfico ${tipo}:`, error);
    }
  }

  // ========== INICIALIZAR ANÁLISIS (agregar a la inicialización existente) ==========
  // Agregar estas líneas después de tu inicialización actual
  configurarAnalisisTab();
  configurarControlesGrafico();

  console.log("✅ Análisis de gastos inicializado correctamente");

  // ========== MODAL DE ÉXITO PERSONALIZADO ==========
  function mostrarModalExito(titulo = "¡Gasto creado exitosamente!", mensaje = "El gasto ha sido guardado correctamente") {
    const modal = document.getElementById('successModal');
    const titleEl = document.getElementById('successTitle');
    const messageEl = document.getElementById('successMessage');

    if (modal && titleEl && messageEl) {
      // Actualizar contenido
      titleEl.textContent = titulo;
      messageEl.textContent = mensaje;

      // Mostrar modal
      modal.classList.add('show');
      modal.classList.remove('hide');

      // Auto-cerrar después de 3 segundos
      setTimeout(() => {
        cerrarModalExito();
      }, 3000);

      console.log('✅ Modal de éxito mostrado');
    }
  }

  function cerrarModalExito() {
    const modal = document.getElementById('successModal');

    if (modal) {
      modal.classList.add('hide');
      modal.classList.remove('show');

      // Remover clases después de la animación
      setTimeout(() => {
        modal.classList.remove('hide');
      }, 300);

      console.log('🔒 Modal de éxito cerrado');
    }
  }

  // Cerrar modal al hacer clic fuera de él
  document.addEventListener('click', (e) => {
    const modal = document.getElementById('successModal');
    if (modal && e.target === modal) {
      cerrarModalExito();
    }
  });

  // Cerrar modal con tecla Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      cerrarModalExito();
    }
  });

})
