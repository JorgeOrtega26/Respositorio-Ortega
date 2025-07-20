<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Materiales</title>
    <link rel="stylesheet" href="../css/siderbarycabezal.css">
    <link rel="stylesheet" href="../css/estiloIn.css">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include '../header.php'; ?>

        <main class="main-content">
            <h1 class="page-title">Inventario de Materiales</h1>

            <!-- Tabs for Inventory and Materials, styled to match the new dashboard look -->
            <div class="content-tabs">
                <button class="tab-button active">Inventario</button>
                <button class="tab-button">Materiales</button>
            </div>

            <div class="content-actions">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Filtrar materiales por nombre, SKU o código de barras..." class="search-input">
                </div>
                <div class="right-actions">
                    <button class="button outline" id="toggleViewButton">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-grid">
                            <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                            <path d="M3 9h18" />
                            <path d="M3 15h18" />
                            <path d="M9 3v18" />
                            <path d="M15 3v18" />
                        </svg>
                        <span id="toggleViewText">Vista Tarjetas</span>
                    </button>
                    <button class="button primary" id="createNewMaterialButton">Crear Nuevo Material</button>
                </div>
            </div>

            <div class="table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>SKU</th>
                            <th>Cantidad</th>
                            <th>Precio Compra</th>
                            <th>Precio Unidad</th>
                            <th>IGV</th>
                            <th>Código Barras</th>
                            <th>Responsable</th>
                            <th>Peso (kg)</th>
                            <th>Volumen (m³)</th>
                            <th>Ubicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="materialsTableBody">
                        <!-- Data will be loaded here by JavaScript -->
                    </tbody>
                </table>
            </div>

            <div id="cardGridContainer" class="card-grid-container hidden">
                <!-- Las tarjetas de materiales se cargarán aquí por JavaScript -->
            </div>
        </main>
    </div>

    <div id="newMaterialModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title-group">
                    <label for="productName" class="modal-label-product">Producto:</label>
                    <input type="text" id="productName" name="name" placeholder="Por ejemplo, Tabla" class="modal-input-product">
                </div>
                <button class="modal-close-button" id="closeModalButton">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
            <p class="modal-description">Introduce los detalles para un nuevo material.</p>

            <form id="newMaterialForm" class="modal-form" data-material-id="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" id="sku" name="sku" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="quantity" class="form-label">Cantidad</label>
                        <input type="number" id="quantity" name="quantity" value="0" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="purchasePrice" class="form-label">Precio Compra</label>
                        <input type="number" id="purchasePrice" name="purchasePrice" value="0" step="0.01" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="unitPrice" class="form-label">Precio por Unidad</label>
                        <input type="number" id="unitPrice" name="unitPrice" value="0" step="0.01" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="weight" class="form-label">Peso (kg)</label>
                        <input type="number" id="weight" name="weightKg" value="0" step="0.001" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="volume" class="form-label">Volumen (m³)</label>
                        <input type="number" id="volume" name="volumeM3" value="0" step="0.000001" class="form-input">
                    </div>
                </div>

                <div class="form-right-column">
                    <div class="image-upload-area">
                        <input type="file" id="materialPhotoInput" accept="image/*" class="hidden-file-input">
                        <label for="materialPhotoInput" class="image-placeholder">
                            <img id="materialPhotoPreview" src="/placeholder.svg?height=120&width=120" alt="Previsualización de la imagen" class="material-photo-preview">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image upload-icon">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                                <circle cx="9" cy="9" r="2" />
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                            </svg>
                        </label>
                    </div>
                    <div class="form-group igv-group">
                        <label for="igv" class="form-label">IGV (18%)</label>
                        <input type="text" id="igv" name="igv" value="S/ 0.00" readonly class="form-input igv-input">
                        <span class="igv-note">(= S/ <span id="igvExcludedAmount">0.00</span> impuestos excluidos)</span>
                    </div>
                    <div class="form-group">
                        <label for="barcode" class="form-label">Código de Barras</label>
                        <div class="barcode-input-group">
                            <input type="text" id="barcode" name="barcode" class="form-input">
                            <button type="button" id="printBarcodeButton" class="button outline icon-button">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-printer">
                                    <polyline points="6 9 6 2 18 2 18 9" />
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                    <rect width="12" height="8" x="6" y="14" />
                                </svg>
                                <span class="sr-only">Imprimir Código de Barras</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="responsible" class="form-label">Responsable</label>
                        <select id="responsible" name="responsible" class="form-select">
                            <option value="">Selecciona un empleado</option>
                            <option value="Juan Pérez">Juan Pérez</option>
                            <option value="María García">María García</option>
                            <option value="Carlos López">Carlos López</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="supplier" class="form-label">Proveedor</label>
                        <select name="supplier" id="supplier" class="form-select">
                            <option value="">Seleccionar un Proveedor</option>
                            <option value="Proveedor A">Proveedor A</option>
                            <option value="Proveedor B">Proveedor B</option>
                            <option value="Proveedor C">Proveedor C</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location" class="form-label">Ubicación</label>
                        <select id="location" name="location" class="form-select">
                            <option value="">Selecciona una ubicación</option>
                            <option value="Almacén Principal - Estante A1">Almacén Principal - Estante A1</option>
                            <option value="Almacén Secundario - Sección B">Almacén Secundario - Sección B</option>
                            <option value="Almacén Principal - Sección C">Almacén Principal - Sección C</option>
                            <option value="Almacén Secundario - Estante D2">Almacén Secundario - Estante D2</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="button primary save-material-button">Guardar Material</button>
            </form>
        </div>
    </div>

    <script src="../js/iventarioF.js"></script>
</body>

</html>