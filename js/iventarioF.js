document.addEventListener("DOMContentLoaded", () => {
  // Selectors for main content elements
  const searchInput = document.getElementById("searchInput")
  const materialsTableBody = document.getElementById("materialsTableBody")
  const toggleViewButton = document.getElementById("toggleViewButton")
  const toggleViewText = document.getElementById("toggleViewText")
  const cardGridContainer = document.getElementById("cardGridContainer")
  const tableContainer = document.querySelector(".table-container")
  let currentView = "table"

  // Selectors for modal elements
  const createNewMaterialButton = document.getElementById("createNewMaterialButton")
  const newMaterialModal = document.getElementById("newMaterialModal")
  const closeModalButton = document.getElementById("closeModalButton")
  const newMaterialForm = document.getElementById("newMaterialForm")
  const productNameInput = document.getElementById("productName")
  const skuInput = document.getElementById("sku")
  const quantityInput = document.getElementById("quantity")
  const purchasePriceInput = document.getElementById("purchasePrice")
  const unitPriceInput = document.getElementById("unitPrice")
  const weightInput = document.getElementById("weight")
  const volumeInput = document.getElementById("volume")
  const barcodeInput = document.getElementById("barcode")
  const responsibleSelect = document.getElementById("responsible")
  const supplierSelect = document.getElementById("supplier") // Nuevo selector para el proveedor
  const locationSelect = document.getElementById("location")
  const igvInput = document.getElementById("igv")
  const igvExcludedAmountSpan = document.getElementById("igvExcludedAmount")
  const materialPhotoInput = document.getElementById("materialPhotoInput")
  const materialPhotoPreview = (document = document.getElementById("materialPhotoPreview"))
  const printBarcodeButton = document.getElementById("printBarcodeButton") // Selector para el botón de imprimir

  let currentMaterialPhotoBase64 = ""

  // Function to generate a simple SKU
  function generateSKU() {
    const timestamp = Date.now().toString().slice(-6) // Last 6 digits of timestamp
    const randomPart = Math.random().toString(36).substring(2, 6).toUpperCase() // 4 random chars
    return `SKU-${timestamp}-${randomPart}`
  }

  // Function to generate and display barcode
  function generateBarcode(text) {
    const JsBarcode = window.JsBarcode // Declare the variable here
    if (text && typeof JsBarcode !== "undefined") {
      try {
        // Create a temporary SVG element to render the barcode
        const svgElement = document.createElementNS("http://www.w3.org/2000/svg", "svg")
        JsBarcode(svgElement, text, {
          format: "CODE128", // Puedes cambiar el formato si lo necesitas (ej. EAN13, UPC)
          displayValue: true,
          width: 2,
          height: 100,
          margin: 0,
        })
        // No necesitamos el SVG string aquí, solo para la impresión
        barcodeInput.value = text // Asegura que el input tenga el valor del código de barras
      } catch (e) {
        console.error("JsBarcode error during generation:", e)
        barcodeInput.value = "Error al generar código"
      }
    } else if (typeof JsBarcode === "undefined") {
      console.warn("JsBarcode library not loaded. Barcode generation skipped.")
      barcodeInput.value = text // Still set the text even if barcode can't be rendered
    } else {
      barcodeInput.value = ""
    }
  }

  // Function to print the barcode
  function printBarcode() {
    const barcodeValue = barcodeInput.value
    const JsBarcode = window.JsBarcode // Declare the variable here
    if (!barcodeValue || typeof JsBarcode === "undefined") {
      alert("No hay código de barras para imprimir o la librería no está cargada.")
      return
    }

    try {
      const svgElement = document.createElementNS("http://www.w3.org/2000/svg", "svg")
      JsBarcode(svgElement, barcodeValue, {
        format: "CODE128",
        displayValue: true,
        width: 2,
        height: 100,
        margin: 10,
      })
      const svgString = new XMLSerializer().serializeToString(svgElement)

      const printWindow = window.open("", "_blank")
      printWindow.document.write(`
        <html>
        <head>
            <title>Imprimir Código de Barras</title>
            <style>
                body { font-family: sans-serif; text-align: center; margin: 20px; }
                svg { max-width: 100%; height: auto; }
                .barcode-label { margin-top: 10px; font-size: 1.2em; font-weight: bold; }
                @media print {
                    body { margin: 0; }
                    svg { page-break-after: avoid; }
                }
            </style>
        </head>
        <body>
            <h1>${productNameInput.value || "Material"}</h1>
            <div class="barcode-container">${svgString}</div>
            <div class="barcode-label">${barcodeValue}</div>
            <p>SKU: ${skuInput.value}</p>
            <p>Cantidad: ${quantityInput.value}</p>
            <p>Precio: S/ ${unitPriceInput.value}</p>
            <script>
                window.onload = function() {
                    window.print();
                    window.onafterprint = function() {
                        window.close();
                    };
                };
            </script>
        </body>
        </html>
      `)
      printWindow.document.close()
    } catch (e) {
      console.error("Error al imprimir el código de barras:", e)
      alert("No se pudo imprimir el código de barras. Asegúrate de que el valor sea válido.")
    }
  }

  // Function to show the modal
  function showModal(material = null) {
    newMaterialForm.reset() // Clear form fields
    productNameInput.value = "" // Clear product name explicitly
    materialPhotoPreview.src = "placeholder.svg?height=120&width=120" // Reset image preview
    currentMaterialPhotoBase64 = "" // Clear Base64 string
    newMaterialForm.dataset.materialId = "" // Clear material ID

    if (material) {
      // Populate form for editing
      productNameInput.value = material.name
      skuInput.value = material.sku
      quantityInput.value = material.quantity
      purchasePriceInput.value = material.purchasePrice
      unitPriceInput.value = material.unitPrice
      weightInput.value = material.weightKg
      volumeInput.value = material.volumeM3
      barcodeInput.value = material.barcode
      responsibleSelect.value = material.responsible
      supplierSelect.value = material.supplier // Set supplier value
      locationSelect.value = material.location
      newMaterialForm.dataset.materialId = material.id // Set material ID for editing

      if (material.photo && material.photo !== "placeholder.svg") {
        materialPhotoPreview.src = material.photo
      }
      updateIgvCalculation()
      // Generate barcode if editing and barcode exists
      if (material.barcode) {
        generateBarcode(material.barcode)
      }
    } else {
      // For creating new material
      const newSKU = generateSKU()
      skuInput.value = newSKU // Set generated SKU
      generateBarcode(newSKU) // Generate barcode for new SKU
      updateIgvCalculation() // Initial calculation for new form
    }
    newMaterialModal.classList.add("show")
  }

  // Function to hide the modal
  function hideModal() {
    newMaterialModal.classList.remove("show")
    newMaterialForm.reset() // Reset form fields when closing
    productNameInput.value = "" // Clear product name explicitly
    materialPhotoPreview.src = "placeholder.svg?height=120&width=120" // Reset image preview
    currentMaterialPhotoBase64 = "" // Clear Base64 string
    newMaterialForm.dataset.materialId = "" // Clear material ID
    updateIgvCalculation() // Reset IGV display
  }

  // Event listener for "Crear Nuevo Material" button
  createNewMaterialButton.addEventListener("click", () => showModal())

  toggleViewButton.addEventListener("click", () => {
    if (currentView === "table") {
      currentView = "cards"
      tableContainer.classList.add("hidden")
      cardGridContainer.classList.remove("hidden")
      toggleViewText.textContent = "Vista Tabla"
      toggleViewButton.querySelector("svg").outerHTML =
        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-table"><path d="M12 2v20"/><path d="M17 2v20"/><path d="M7 2v20"/><path d="M2 12h20"/><path d="M2 17h20"/><path d="M2 7h20"/></svg>`
    } else {
      currentView = "table"
      cardGridContainer.classList.add("hidden")
      tableContainer.classList.remove("hidden")
      toggleViewText.textContent = "Vista Tarjetas"
      toggleViewButton.querySelector("svg").outerHTML =
        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-grid"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>`
    }
    fetchAndDisplayMaterials(searchInput.value) // Re-render with current search term
  })

  // Event listener for close button (X)
  closeModalButton.addEventListener("click", hideModal)

  // Event listener for clicking outside the modal content to close it
  newMaterialModal.addEventListener("click", (event) => {
    if (event.target === newMaterialModal) {
      hideModal()
    }
  })

  // Event listener for SKU input to generate barcode
  skuInput.addEventListener("input", (event) => {
    generateBarcode(event.target.value)
  })

  // Event listener for print barcode button
  printBarcodeButton.addEventListener("click", printBarcode)

  // Function to calculate and update IGV
  function updateIgvCalculation() {
    const purchasePrice = Number.parseFloat(purchasePriceInput.value) || 0
    const unitPrice = Number.parseFloat(unitPriceInput.value) || 0
    const totalBasePrice = purchasePrice + unitPrice
    const igvRate = 0.18 // 18%
    const igvAmount = totalBasePrice * igvRate

    igvInput.value = `S/ ${igvAmount.toFixed(2)}`
    igvExcludedAmountSpan.textContent = totalBasePrice.toFixed(2)
  }

  // Event listeners for price inputs to update IGV
  purchasePriceInput.addEventListener("input", updateIgvCalculation)
  unitPriceInput.addEventListener("input", updateIgvCalculation)

  // Handle image file input
  materialPhotoInput.addEventListener("change", (event) => {
    const file = event.target.files[0]
    if (file) {
      const reader = new FileReader()
      reader.onload = (e) => {
        materialPhotoPreview.src = e.target.result
        currentMaterialPhotoBase64 = e.target.result // Store Base64 string
      }
      reader.readAsDataURL(file)
    } else {
      materialPhotoPreview.src = "placeholder.svg?height=120&width=120" // CAMBIO AQUÍ: Ruta relativa
      currentMaterialPhotoBase64 = ""
    }
  })

  // Handle form submission (Create/Edit)
  newMaterialForm.addEventListener("submit", async (event) => {
    event.preventDefault()

    const materialId = newMaterialForm.dataset.materialId
    const method = "POST" // Siempre POST para PHP con 'action' en GET
    const url = materialId
      ? `../InventarioProduccion/materialesBase.php?action=editar_material&id=${materialId}`
      : `../InventarioProduccion/materialesBase.php?action=crear_material`

    const materialData = {
      id: materialId || undefined, // Include ID for PUT requests
      name: productNameInput.value,
      sku: skuInput.value,
      quantity: Number.parseInt(quantityInput.value),
      purchasePrice: Number.parseFloat(purchasePriceInput.value),
      unitPrice: Number.parseFloat(unitPriceInput.value),
      igv: Number.parseFloat(igvInput.value.replace("S/ ", "")), // Parse IGV from display
      barcode: barcodeInput.value,
      responsible: responsibleSelect.value,
      supplier: supplierSelect.value, // Incluir el valor del proveedor
      weightKg: Number.parseFloat(weightInput.value),
      volumeM3: Number.parseFloat(volumeInput.value),
      location: locationSelect.value,
      photo: currentMaterialPhotoBase64 || materialPhotoPreview.src, // Send Base64 if new, else current URL
    }

    // --- ELIMINAR ESTA LÍNEA DE DEPURACIÓN DESPUÉS DE CONFIRMAR QUE FUNCIONA ---
    // console.log("DEBUG: materialData being sent:", materialData)
    // --- FIN DE LÍNEAS DE DEPURACIÓN ---

    try {
      const response = await fetch(url, {
        method: method,
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(materialData),
      })

      if (!response.ok) {
        const errorText = await response.text()
        throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`)
      }

      const result = await response.json()
      console.log("Success:", result)
      // Verifica si la API devuelve 'success: true'
      if (result.success) {
        alert(`Material ${materialId ? "actualizado" : "guardado"} con éxito!`)
        hideModal()
        fetchAndDisplayMaterials(searchInput.value) // Refresh the table/cards
      } else {
        // Muestra el mensaje de error de la API si 'success' es false
        alert(`Hubo un error al ${materialId ? "actualizar" : "guardar"} el material: ${result.message}`)
      }
    } catch (error) {
      console.error("Error saving material:", error)
      alert(`Hubo un error al ${materialId ? "actualizar" : "guardar"} el material: ${error.message}`)
    }
  })

  // Function to fetch and display materials
  async function fetchAndDisplayMaterials(searchTerm = "") {
    try {
      const response = await fetch(
        `../InventarioProduccion/materialesBase.php?action=obtener_materiales&search=${encodeURIComponent(searchTerm)}`,
      )
      if (!response.ok) {
        const errorText = await response.text()
        throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`)
      }
      const result = await response.json() // Parse the JSON response

      // Check if the API returned success: true
      if (result.success) {
        if (currentView === "table") {
          renderTable(result.data) // Use result.data for materials
        } else {
          renderCards(result.data) // Use result.data for materials
        }
      } else {
        // Handle API error message
        materialsTableBody.innerHTML = `<tr><td colspan="13" style="text-align: center; padding: 20px;">Error al cargar los materiales: ${result.message}</td></tr>`
        cardGridContainer.innerHTML = `<p style="text-align: center; padding: 20px;">Error al cargar los materiales: ${result.message}</p>`
      }
    } catch (error) {
      console.error("Error fetching materials:", error)
      materialsTableBody.innerHTML = `<tr><td colspan="13" style="text-align: center; padding: 20px;">Error al cargar los materiales. Por favor, asegúrate de que tu servidor y 'materialesBase.php' estén funcionando correctamente. Detalle: ${error.message}</td></tr>`
      cardGridContainer.innerHTML = `<p style="text-align: center; padding: 20px;">Error al cargar los materiales. Por favor, asegúrate de que tu servidor y 'materialesBase.php' estén funcionando correctamente. Detalle: ${error.message}</p>`
    }
  }

  // Function to render the table rows
  function renderTable(materials) {
    materialsTableBody.innerHTML = ""
    if (materials.length === 0) {
      materialsTableBody.innerHTML = `<tr><td colspan="13" style="text-align: center; padding: 20px;">No se encontraron materiales.</td></tr>`
      return
    }

    materials.forEach((material) => {
      const row = document.createElement("tr")
      row.innerHTML = `
              <td class="photo-cell">
                  <img src="${material.photo || "placeholder.svg?height=40&width=40"}" alt="${material.name}">
              </td>
              <td class="font-medium">${material.name}</td>
              <td>${material.sku}</td>
              <td>${material.quantity}</td>
              <td>S/ ${material.purchasePrice.toFixed(2)}</td>
              <td>S/ ${material.unitPrice.toFixed(2)}</td>
              <td>S/ ${material.igv.toFixed(2)}</td>
              <td>${material.barcode}</td>
              <td>${material.responsible}</td>
              <td>${material.weightKg.toFixed(3)}</td>
              <td>${material.volumeM3.toFixed(6)}</td>
              <td>${material.location}</td>
              <td>
                  <div class="action-buttons">
                      <button class="button outline edit" data-id="${material.id}">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                          <span class="sr-only">Editar</span>
                      </button>
                      <button class="button delete" data-id="${material.id}">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                          <span class="sr-only">Eliminar</span>
                      </button>
                  </div>
              </td>
          `
      materialsTableBody.appendChild(row)
    })

    // Add event listeners for action buttons
    materialsTableBody.querySelectorAll(".button.edit").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const materialId = event.currentTarget.dataset.id
        // Fetch material data to populate modal
        try {
          const response = await fetch(
            `../InventarioProduccion/materialesBase.php?action=obtener_material&id=${materialId}`,
          )
          const result = await response.json()
          if (result.success) {
            showModal(result.data)
          } else {
            alert(`No se pudo cargar el material para editar: ${result.message}`)
          }
        } catch (error) {
          console.error("Error fetching material for edit:", error)
          alert("No se pudo cargar el material para editar.")
        }
      })
    })

    materialsTableBody.querySelectorAll(".button.delete").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const materialId = event.currentTarget.dataset.id
        if (confirm(`¿Estás seguro de que quieres eliminar el material con ID: ${materialId}?`)) {
          try {
            const response = await fetch(
              `../InventarioProduccion/materialesBase.php?action=eliminar_material&id=${materialId}`,
              {
                method: "GET",
              },
            )
            if (!response.ok) {
              const errorText = await response.text()
              throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`)
            }
            const result = await response.json()
            if (result.success) {
              alert("Material eliminado con éxito!")
              fetchAndDisplayMaterials(searchInput.value) // Refresh the table
            } else {
              alert(`Hubo un error al eliminar el material: ${result.message}`)
            }
          } catch (error) {
            console.error("Error deleting material:", error)
            alert(`Hubo un error al eliminar el material: ${error.message}`)
          }
        }
      })
    })
  }

  function renderCards(materials) {
    cardGridContainer.innerHTML = ""
    if (materials.length === 0) {
      cardGridContainer.innerHTML = `<p style="text-align: center; padding: 20px;">No se encontraron materiales.</p>`
      return
    }

    materials.forEach((material) => {
      const card = document.createElement("div")
      card.classList.add("material-card")
      card.innerHTML = `
            <button class="card-favorite-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span class="sr-only">Marcar como favorito</span>
            </button>
            <div class="card-header">
                <div class="card-image-placeholder">
                    <img src="${material.photo || "placeholder.svg?height=64&width=64"}" alt="${material.name}">
                </div>
                <div class="card-details">
                    <h3 class="card-name">${material.name}</h3>
                    <p class="card-sku">[${material.sku}]</p>
                </div>
            </div>
            <p class="card-price">Precio: S/ ${material.unitPrice.toFixed(2)}</p>
            <p class="card-quantity">A la mano: ${material.quantity} Unidades</p>
            <div class="card-actions">
                <button class="button outline edit" data-id="${material.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                    <span class="sr-only">Editar</span>
                </button>
                <button class="button delete" data-id="${material.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                    <span class="sr-only">Eliminar</span>
                </button>
            </div>
        `
      cardGridContainer.appendChild(card)
    })

    // Add event listeners for action buttons on cards
    cardGridContainer.querySelectorAll(".button.edit").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const materialId = event.currentTarget.dataset.id
        try {
          const response = await fetch(
            `../InventarioProduccion/materialesBase.php?action=obtener_material&id=${materialId}`,
          )
          const result = await response.json()
          if (result.success) {
            showModal(result.data)
          } else {
            alert(`No se pudo cargar el material para editar: ${result.message}`)
          }
        } catch (error) {
          console.error("Error fetching material for edit:", error)
          alert("No se pudo cargar el material para editar.")
        }
      })
    })

    cardGridContainer.querySelectorAll(".button.delete").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const materialId = event.currentTarget.dataset.id
        if (confirm(`¿Estás seguro de que quieres eliminar el material con ID: ${materialId}?`)) {
          try {
            const response = await fetch(
              `../InventarioProduccion/materialesBase.php?action=eliminar_material&id=${materialId}`,
              {
                method: "GET",
              },
            )
            if (!response.ok) {
              const errorText = await response.text()
              throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`)
            }
            const result = await response.json()
            if (result.success) {
              alert("Material eliminado con éxito!")
              fetchAndDisplayMaterials(searchInput.value) // Refresh the cards
            } else {
              alert(`Hubo un error al eliminar el material: ${result.message}`)
            }
          } catch (error) {
            console.error("Error deleting material:", error)
            alert(`Hubo un error al eliminar el material: ${error.message}`)
          }
        }
      })
    })
  }

  // Event listener for search input
  searchInput.addEventListener("input", (event) => {
    fetchAndDisplayMaterials(event.target.value)
  })

  // Initial load of materials
  fetchAndDisplayMaterials()
})
