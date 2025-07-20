document.addEventListener("DOMContentLoaded", () => {
  console.log("DOM Content Loaded - documentos.js is running.")

  // Asegúrate de que estas URLs coincidan con la ruta de tu archivo PHP
  // Estas constantes se definen en el HTML del PHP, pero las repetimos aquí para claridad en el JS.
  const API_BASE_URL = "./Documento.php"
  const UPLOADS_BASE_URL = "./public/uploads/documents/"

  // --- Icon SVGs (Lucide React equivalents) ---
  const icons = {
    folder: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 8 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>`,
    file: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>`,
    fileText: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`,
    fileSpreadsheet: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-spreadsheet"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/><path d="M8 9h2"/><path d="M14 9h2"/></svg>`,
    filePdf: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`, // Using file-text for PDF as a placeholder
    fileImage: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-image"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><circle cx="10" cy="14" r="2"/><path d="m20 15-1.5-1.5a2 2 0 0 0-2.2-.3L11 19"/></svg>`,
    building2: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building-2"><path d="M6 22V7M18 22V7M2 22h20M12 7V2h3a1 1 0 1 1 0 2H12v3M12 7H9a1 1 0 0 0-1 1v1h8V8a1 1 0 0 0-1-1Z"/></svg>`, // Nuevo icono de empresa
    fileQuestion: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-question"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M9 18h1"/><path d="M9 12v2"/><path d="M10 12a2 2 0 0 1 4 0v2a2 2 0 0 1-4 0"/></svg>`,
    plus: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus"><path d="M12 5v14"/><path d="M5 12h14"/></svg>`,
    layoutGrid: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-grid"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>`,
    list: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>`,
    info: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`,
    settings: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.78 1.28a2 2 0 0 0 .73 2.73l.15.08a2 2 0 0 1 1 1.74v.17a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l-.78-1.28a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>`,
    clock: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
    ellipsisVertical: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis-vertical"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>`,
    pencil: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>`,
    trash2: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>`,
    chevronRight: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>`,
    hardDrive: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hard-drive"><path d="M22 12H2"/><path d="M5.45 12.91 2 16v-4.91l3.45-.91"/><path d="M18.55 12.91 22 16v-4.91l-3.45-.91"/><path d="M2 16h20"/><path d="M2 19h20"/><path d="M12 12v7"/></svg>`,
    users: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
    fileEye: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-eye"><path d="M4 19V5a2 2 0 0 1 2-2h10l4 4v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M10 12.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0Z"/><path d="M10 12c0-2.5 1.5-4 4-4s4 1.5 4 4-1.5 4-4 4-4-1.5-4-4Z"/></svg>`,
    x: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>`,
    save: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>`,
  }

  const openFolderStates = new Set(["empresa"]) // Inicializa con 'empresa' abierta por defecto
  let currentFolderCreationMode = "subfolder" // 'subfolder' o 'principal'

  // --- Data (Ahora se obtendrá de la base de datos) ---
  let folders = []
  let files = []

  // --- State Variables ---
  let currentFolderId = "mi-unidad" // Default to 'Mi Unidad' as per screenshot
  let viewMode = "kanban" // 'kanban' or 'list'
  let selectedFileForUpload = null
  let viewingFileId = null // New state to track which file is being viewed/edited

  // Dialogs (still used for create/edit/delete)
  const documentsModuleContainer = document.getElementById("documents-module-container")
  if (!documentsModuleContainer) {
    console.error("Error: #documents-module-container not found! JavaScript will not function correctly.")
    return // Stop execution if the main container isn't found
  }
  console.log("#documents-module-container found:", documentsModuleContainer)

  const sidebarFoldersList = documentsModuleContainer.querySelector(".documents-sidebar .scroll-area ul")
  if (!sidebarFoldersList) {
    console.error("Error: .documents-sidebar .scroll-area ul not found! Sidebar content will not render.")
  } else {
    console.log(".documents-sidebar .scroll-area ul found:", sidebarFoldersList)
  }

  const currentFolderTitle = documentsModuleContainer.querySelector(".documents-header h1")
  if (!currentFolderTitle) {
    console.error("Error: .documents-header h1 not found! Folder title will not update.")
  }

  const newButton = documentsModuleContainer.querySelector(".documents-header .action-button")
  if (!newButton) {
    console.error("Error: .documents-header .action-button (Nuevo button) not found!")
  }

  const newButtonDropdownContent = newButton ? newButton.nextElementSibling : null // The dropdown menu for "Nuevo"
  if (!newButtonDropdownContent) {
    console.warn("Warning: New button dropdown content not found.")
  }

  const viewModeToggleGroup = documentsModuleContainer.querySelector(".toggle-group")
  if (!viewModeToggleGroup) {
    console.error("Error: .toggle-group not found! View mode toggles will not work.")
  }

  const kanbanViewToggle = viewModeToggleGroup ? viewModeToggleGroup.querySelector('[value="kanban"]') : null
  const listViewToggle = viewModeToggleGroup ? viewModeToggleGroup.querySelector('[value="list"]') : null
  if (!kanbanViewToggle || !listViewToggle) {
    console.error("Error: Kanban or List view toggles not found!")
  }

  const contentArea = documentsModuleContainer.querySelector(".documents-content-area")
  if (!contentArea) {
    console.error("Error: .documents-content-area not found! Main content will not render.")
  }

  // Dialogs (still used for create/edit/delete)
  const createFolderDialog = documentsModuleContainer.querySelector("#create-folder-dialog")
  const createFolderInput = createFolderDialog ? createFolderDialog.querySelector("#folderName") : null
  const createFolderCancelBtn = createFolderDialog
    ? createFolderDialog.querySelector(".dialog-footer .button.outline")
    : null
  const createFolderConfirmBtn = createFolderDialog
    ? createFolderDialog.querySelector(".dialog-footer .button.default")
    : null
  if (!createFolderDialog || !createFolderInput || !createFolderCancelBtn || !createFolderConfirmBtn) {
    console.error("Error: Create Folder Dialog elements not found!")
  }

  const uploadFileDialog = documentsModuleContainer.querySelector("#upload-file-dialog")
  const uploadFileNameInput = uploadFileDialog ? uploadFileDialog.querySelector("#uploadFileName") : null
  const uploadFileInput = uploadFileDialog ? uploadFileDialog.querySelector("#file") : null
  const uploadFileCancelBtn = uploadFileDialog ? uploadFileDialog.querySelector(".dialog-footer .button.outline") : null
  const uploadFileConfirmBtn = uploadFileDialog
    ? uploadFileDialog.querySelector(".dialog-footer .button.default")
    : null
  if (!uploadFileDialog || !uploadFileInput || !uploadFileCancelBtn || !uploadFileConfirmBtn) {
    console.error("Error: Upload File Dialog elements not found!")
  }

  const editDialog = documentsModuleContainer.querySelector("#edit-dialog")
  const editInput = editDialog ? editDialog.querySelector("#editName") : null
  const editContentTextarea = editDialog ? editDialog.querySelector("#editContent") : null
  const fileContentGroup = editDialog ? editDialog.querySelector("#file-content-group") : null
  const editTitle = editDialog ? editDialog.querySelector(".dialog-title") : null
  const editDescription = editDialog ? editDialog.querySelector(".dialog-description") : null
  const editConfirmBtn = editDialog ? editDialog.querySelector(".dialog-footer .button.default") : null
  if (!editDialog || !editInput || !editTitle || !editDescription || !editConfirmBtn) {
    console.error("Error: Edit Dialog elements not found!")
  }

  const deleteDialog = documentsModuleContainer.querySelector("#delete-dialog")
  const deleteDescription = deleteDialog ? deleteDialog.querySelector(".dialog-description") : null
  const deleteConfirmBtn = deleteDialog ? deleteDialog.querySelector(".dialog-footer .button.destructive") : null
  if (!deleteDialog || !deleteDescription || !deleteConfirmBtn) {
    console.error("Error: Delete Dialog elements not found!")
  }

  // NEW: File Preview Dialog elements
  const filePreviewDialog = documentsModuleContainer.querySelector("#file-preview-dialog")
  const filePreviewContent = filePreviewDialog ? filePreviewDialog.querySelector("#file-preview-content") : null
  const filePreviewInfo = filePreviewDialog ? filePreviewDialog.querySelector("#file-preview-info") : null
  const filePreviewCloseButtons = filePreviewDialog
    ? filePreviewDialog.querySelectorAll(".file-preview-close-button")
    : []

  if (!filePreviewDialog || !filePreviewContent || !filePreviewInfo || filePreviewCloseButtons.length === 0) {
    console.error("Error: File Preview Dialog elements not found!")
  }

  // --- Helper Functions ---
  async function fetchData() {

    try {
      const foldersRes = await fetch(`${API_BASE_URL}?action=get_folders`)
      const foldersData = await foldersRes.json()

      if (foldersData.success) {
        folders = foldersData.data
      } else {
        console.error("Error fetching folders:", foldersData.error)
        alert("Error al cargar carpetas: " + foldersData.error)
      }

      let filesEndpoint = `${API_BASE_URL}?action=get_files`
      if (currentFolderId === "papelera") {
        filesEndpoint = `${API_BASE_URL}?action=get_trash_files` // Nuevo endpoint para la papelera
      }

      const filesRes = await fetch(filesEndpoint)
      const filesData = await filesRes.json()

      if (filesData.success) {
        files = filesData.data
      } else {
        console.error("Error fetching files:", filesData.error)
        alert("Error al cargar archivos: " + filesData.error)
      }
    } catch (error) {
      console.error("Network error fetching data:", error)
      alert("Error de red al cargar datos: " + error.message)
    }

  }
  async function updateDisplay() {
    console.log("updateDisplay called. currentFolderId:", currentFolderId, "viewingFileId:", viewingFileId)

    await fetchData() // Fetch latest data before updating display

    if (viewingFileId) {
      // If a file is being viewed, render the file viewer
      renderFileViewer(viewingFileId)
      return
    }

    let displayedFolders = []
    let displayedFiles = []
    const specialTopLevelFolders = ["todos", "mi-unidad", "compartido", "reciente", "papelera", "empresa"]

    if (currentFolderId === "todos") {
      // For "Todos", show all folders (excluding the special top-level ones themselves as cards)
      // and all files.
      displayedFolders = folders.filter((f) => !specialTopLevelFolders.includes(f.id))
      displayedFiles = files // All files
    } else {
      // For any other folder, show its direct children folders and files
      displayedFolders = folders.filter((f) => f.parent_id === currentFolderId)
      displayedFiles = files.filter((f) => f.folder_id === currentFolderId)
    }

    const currentFolder = folders.find((f) => f.id === currentFolderId)
    if (currentFolderTitle) {
      currentFolderTitle.textContent = currentFolder ? currentFolder.name : "Documentos"
    } else {
      console.warn("currentFolderTitle element not found when trying to update text.")
    }

    if (contentArea) {
      contentArea.innerHTML = "" // Clear current content

      if (displayedFolders.length === 0 && displayedFiles.length === 0) {
        contentArea.innerHTML = `<div class="empty-state"><p>Esta carpeta está vacía.</p></div>`
        console.log("Folder is empty.")
        return
      }

      let contentHtml = ""

      if (displayedFolders.length > 0) {
        contentHtml += `<h2 class="mb-4 text-lg font-semibold">Carpetas</h2>`
        contentHtml += `<div class="${viewMode === "kanban" ? "grid-view" : "list-view"}">`
        displayedFolders.forEach((folder) => {
          contentHtml += renderFolderCard(folder, viewMode)
        })
        contentHtml += `</div>`
        if (displayedFiles.length > 0) {
          contentHtml += `<div class="separator my-6"></div>`
        }
      }

      if (displayedFiles.length > 0) {
        contentHtml += `<h2 class="mb-4 text-lg font-semibold">Archivos</h2>`
        contentHtml += `<div class="${viewMode === "kanban" ? "grid-view" : "list-view"}">`
        displayedFiles.forEach((file) => {
          contentHtml += renderFileCard(file, viewMode)
        })
        contentHtml += `</div>`
      }

      contentArea.innerHTML = contentHtml
      attachContentAreaEventListeners() // Re-attach event listeners after re-rendering
      console.log("Content area updated.")
    } else {
      console.error("Content area element not found, cannot update display.")
    }
  }

  async function updateSidebar() {
    console.log("updateSidebar called.")
    await fetchData() // Ensure folders data is fresh

    if (sidebarFoldersList) {
      sidebarFoldersList.innerHTML = renderFolderTree(folders, null)

      const existingOtherSectionH3 = sidebarFoldersList.parentElement.querySelector(".documents-sidebar > h3")
      if (existingOtherSectionH3) {
        existingOtherSectionH3.nextElementSibling.remove()
        existingOtherSectionH3.remove()
      }
      console.log("Sidebar updated.")
    } else {
      console.error("Sidebar folders list element not found, cannot update sidebar.")
    }
  }

  function renderFolderTree(foldersToRender, parentId, level = 0) {
    const children = foldersToRender.filter((f) => f.parent_id === parentId)
    if (children.length === 0) return ""

    let html = `<ul class="space-y-1">`
    children.forEach((folder) => {
      const hasChildren = foldersToRender.some((f) => f.parent_id === folder.id)
      const isActive = currentFolderId === folder.id ? "active" : ""
      const paddingLeft = `${16 + level * 16}px`

      let isOpen = openFolderStates.has(folder.id)
      // Si no está explícitamente abierta, verifica si es un ancestro de la carpeta actual
      if (!isOpen) {
        let tempId = currentFolderId
        while (tempId) {
          const current = foldersToRender.find((f) => f.id === tempId)
          if (!current) break
          if (current.parent_id === folder.id) {
            isOpen = true
            break
          }
          tempId = current.parent_id
        }
      }

      html += `
                <li>
                    <div class="collapsible-container">
                        <button class="folder-button ${isActive} ${isOpen && hasChildren ? "collapsible-open" : ""}" data-folder-id="${folder.id}" style="padding-left: ${paddingLeft};">
                            <span class="icon-blue">${getSidebarIcon(folder.id, icons.folder)}</span>
                            <span>${folder.name}</span>
                            ${hasChildren ? `<span class="chevron-icon">${icons.chevronRight}</span>` : ""}
                        </button>
                        <div class="collapsible-content" style="display: ${isOpen ? "block" : "none"};">
                            ${renderFolderTree(foldersToRender, folder.id, level + 1)}
                        </div>
                    </div>
                </li>
            `
    })
    html += `</ul>`
    return html
  }

  function getSidebarIcon(folderId, defaultIcon) {
    switch (folderId) {
      case "empresa":
        return icons.building2
      case "mi-unidad":
        return icons.hardDrive
      case "compartido":
        return icons.users
      case "reciente":
        return icons.clock
      case "papelera":
        return icons.trash2
      default:
        return defaultIcon
    }
  }

  function renderFolderCard(folder, mode) {
    if (mode === "list") {
      return `
                <div class="document-card list-view-item" data-id="${folder.id}" data-type="folder">
                    <button class="folder-navigate-button flex items-center gap-2 p-0 h-auto" data-id="${folder.id}">
                        <span class="icon-blue h-5 w-5">${icons.folder}</span>
                        <span class="font-medium">${folder.name}</span>
                    </button>
                    <div class="dropdown-menu-container">
                        <button class="dropdown-menu-trigger-button" aria-label="Acciones de carpeta">${icons.ellipsisVertical}</button>
                        <div class="dropdown-menu-content hidden">
                            <button class="dropdown-menu-item edit-action" data-id="${folder.id}" data-name="${folder.name}" data-type="folder">${icons.pencil} Editar</button>
                            <button class="dropdown-menu-item delete-action text-red-600" data-id="${folder.id}" data-type="folder">${icons.trash2} Eliminar</button>
                        </div>
                    </div>
                </div>
            `
    }
    return `
            <div class="document-card kanban-view-item" data-id="${folder.id}" data-type="folder">
                <div class="document-card-header">
                    <span class="icon-blue">${icons.folder}</span>
                    <div class="dropdown-menu-container">
                        <button class="dropdown-menu-trigger-button" aria-label="Acciones de carpeta">${icons.ellipsisVertical}</button>
                        <div class="dropdown-menu-content hidden">
                            <button class="dropdown-menu-item edit-action" data-id="${folder.id}" data-name="${folder.name}" data-type="folder">${icons.pencil} Editar</button>
                            <button class="dropdown-menu-item delete-action text-red-600" data-id="${folder.id}" data-type="folder">${icons.trash2} Eliminar</button>
                        </div>
                    </div>
                </div>
                <div class="document-card-content folder-navigate-button" data-id="${folder.id}">
                    <h3 class="document-card-title">${folder.name}</h3>
                </div>
            </div>
        `
  }

  function getFileIcon(fileType) {
    const type = fileType.toLowerCase()
    if (type === "txt") return icons.fileText
    if (["xls", "xlsx", "csv"].includes(type)) return icons.fileSpreadsheet
    if (type === "pdf") return icons.filePdf
    if (["jpg", "jpeg", "png", "gif", "bmp", "svg"].includes(type)) return icons.fileImage
    // Add more specific icons for other types if needed
    return icons.file // Default file icon
  }

  function renderFileCard(file, mode) {
    let dropdownActions = ""
    if (currentFolderId === "papelera") {
      // Acciones para archivos en la papelera
      dropdownActions = `
                  <button class="dropdown-menu-item restore-action" data-id="${file.id}" data-type="file">${icons.rotateCcw} Restaurar</button>
                  <button class="dropdown-menu-item permanent-delete-action text-red-600" data-id="${file.id}" data-type="file">${icons.trash2} Eliminar Permanentemente</button>
              `
    } else {
      // Acciones para archivos en carpetas normales
      dropdownActions = `
                  <button class="dropdown-menu-item edit-action" data-id="${file.id}" data-name="${file.name}" data-type="file" data-file-type="${file.type}">${icons.pencil} Editar</button>
                  <button class="dropdown-menu-item delete-action text-red-600" data-id="${file.id}" data-type="file">${icons.trash2} Enviar a Papelera</button>
              `
    }
    // Removed 'view-action' from dropdown, now direct click on card content
    if (mode === "list") {
      return `
                <div class="document-card list-view-item" data-id="${file.id}" data-type="file">
                    <button class="file-open-button flex items-center gap-2 p-0 h-auto" data-id="${file.id}">
                        ${getFileIcon(file.type)}
                        <div class="flex flex-col">
                            <span class="font-medium">${file.name}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">${file.size} &bull; ${file.uploaded_at}</span>
                        </div>
                    </button>
                    <div class="dropdown-menu-container">
                        <button class="dropdown-menu-trigger-button" aria-label="Acciones de archivo">${icons.ellipsisVertical}</button>
                        <div class="dropdown-menu-content hidden">
                            <button class="dropdown-menu-item edit-action" data-id="${file.id}" data-name="${file.name}" data-type="file" data-file-type="${file.type}">${icons.pencil} Editar</button>
                            <button class="dropdown-menu-item delete-action text-red-600" data-id="${file.id}" data-type="file">${icons.trash2} Eliminar</button>
                        </div>
                    </div>
                </div>
            `
    }
    return `
            <div class="document-card kanban-view-item" data-id="${file.id}" data-type="file">
                <div class="document-card-header">
                    ${getFileIcon(file.type)}
                    <div class="dropdown-menu-container">
                        <button class="dropdown-menu-trigger-button" aria-label="Acciones de archivo">${icons.ellipsisVertical}</button>
                        <div class="dropdown-menu-content hidden">
                            <button class="dropdown-menu-item edit-action" data-id="${file.id}" data-name="${file.name}" data-type="file" data-file-type="${file.type}">${icons.pencil} Editar</button>
                            <button class="dropdown-menu-item delete-action text-red-600" data-id="${file.id}" data-type="file">${icons.trash2} Eliminar</button>
                        </div>
                    </div>
                </div>
                <div class="document-card-content file-open-button" data-id="${file.id}">
                    <h3 class="document-card-title">${file.name}</h3>
                    <p class="document-card-info">${file.size} &bull; ${file.uploaded_at}</p>
                </div>
            </div>
        `
  }

  async function renderFileViewer(fileId) {
    const file = files.find((f) => f.id === fileId)
    if (!file) {
      console.error("File not found for viewing:", fileId)
      viewingFileId = null // Reset viewing state
      updateDisplay() // Go back to folder view
      return
    }

    // Open the file preview dialog
    if (filePreviewDialog) {
      openDialog(filePreviewDialog)
    } else {
      console.error("File preview dialog not found.")
      return
    }

    // Update dialog title and info
    const dialogTitle = filePreviewDialog.querySelector(".dialog-title")
    if (dialogTitle) dialogTitle.textContent = file.name
    if (filePreviewInfo)
      filePreviewInfo.textContent = `Tamaño: ${file.size} • Subido: ${file.uploaded_at} • Tipo: ${file.type.toUpperCase()}`

    // Clear previous content
    if (filePreviewContent) filePreviewContent.innerHTML = ""

    if (file.type.toLowerCase() === "txt") {
      try {
        const response = await fetch(`${API_BASE_URL}?action=get_file_content&id=${file.id}`)
        const data = await response.json()
        if (data.success) {
          if (filePreviewContent) {
            filePreviewContent.innerHTML = `<textarea id="file-editor-textarea" class="file-editor-textarea" rows="20">${data.content}</textarea>`
            // Add save button for text files in preview dialog
            const saveButtonHtml = `<button class="button default file-viewer-save-button">${icons.save} Guardar Cambios</button>`
            const dialogFooter = filePreviewDialog.querySelector(".dialog-footer")
            if (dialogFooter && !dialogFooter.querySelector(".file-viewer-save-button")) {
              dialogFooter.insertAdjacentHTML("afterbegin", saveButtonHtml)
            }
            attachFileViewerEventListeners(file.id) // Re-attach listeners for save button
          }
        } else {
          if (filePreviewContent)
            filePreviewContent.innerHTML = `<p class="text-center text-red-600 dark:text-red-400">Error al cargar contenido: ${data.error}</p>`
        }
      } catch (error) {
        if (filePreviewContent)
          filePreviewContent.innerHTML = `<p class="text-center text-red-600 dark:text-red-400">Error de red al cargar contenido: ${error.message}</p>`
      }
    } else if (["jpg", "jpeg", "png", "gif"].includes(file.type.toLowerCase())) {
      if (filePreviewContent)
        filePreviewContent.innerHTML = `<img src="${UPLOADS_BASE_URL}${file.content}" alt="${file.name}" class="max-w-full h-auto mx-auto" />`
    } else if (file.type.toLowerCase() === "pdf") {
      if (filePreviewContent)
        filePreviewContent.innerHTML = `<iframe src="${UPLOADS_BASE_URL}${file.content}" width="100%" height="500px" style="border:none;"></iframe>`
    } else {
      if (filePreviewContent)
        filePreviewContent.innerHTML = `<p class="text-center text-gray-600 dark:text-gray-300">No hay vista previa disponible para este tipo de archivo.</p><p class="text-center text-gray-500 dark:text-gray-400 text-sm mt-2">Contenido almacenado: ${file.content}</p>`
    }

    // Attach close button listeners for the preview dialog
    filePreviewCloseButtons.forEach((button) => {
      button.onclick = () => {
        closeDialog(filePreviewDialog)
        // Remove save button if it was added for text files
        const saveBtn = filePreviewDialog.querySelector(".file-viewer-save-button")
        if (saveBtn) saveBtn.remove()
        viewingFileId = null // Clear viewing state
        updateDisplay() // Go back to folder view
        updateSidebar() // Ensure sidebar active state is correct
      }
    })
  }

  function attachFileViewerEventListeners(fileId) {
    const saveButton = filePreviewDialog.querySelector(".file-viewer-save-button")
    const editorTextarea = filePreviewDialog.querySelector("#file-editor-textarea")
    if (saveButton && editorTextarea) {
      saveButton.onclick = () => {
        handleSaveFileContent(fileId, editorTextarea.value)
      }
    }
  }

  async function handleSaveFileContent(fileId, newContent) {
    try {
      const response = await fetch(`${API_BASE_URL}?action=edit_file`, {
        method: "POST", // Or PUT if your PHP supports it
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: fileId, name: files.find((f) => f.id === fileId).name, content: newContent }),
      })
      const data = await response.json()
      if (data.success) {
        alert("Cambios guardados exitosamente.")
        await fetchData() // Refresh data
        renderFileViewer(fileId) // Re-render viewer with updated content
      } else {
        alert("Error al guardar cambios: " + data.error)
      }
    } catch (error) {
      alert("Error de red al guardar cambios: " + error.message)
    }
  }

  // --- Event Handlers ---
  async function handleConfirmCreateFolder() {
    const newFolderName = createFolderInput ? createFolderInput.value.trim() : ""
    if (newFolderName) {
      let parentIdForNewFolder = null // Por defecto, es una carpeta principal

      if (currentFolderCreationMode === "subfolder") {
        // Si el modo es 'subcarpeta', la nueva carpeta se crea dentro de la carpeta actual
        parentIdForNewFolder = currentFolderId
      }
      // Si currentFolderCreationMode es 'principal', parentIdForNewFolder permanece null

      try {
        const response = await fetch(`${API_BASE_URL}?action=create_folder`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ name: newFolderName, parentId: parentIdForNewFolder }),
        })
        const data = await response.json()
        if (data.success) {
          if (createFolderInput) createFolderInput.value = ""
          closeDialog(createFolderDialog)
          await updateSidebar()
          await updateDisplay()
          alert("Carpeta creada exitosamente.")
        } else {
          alert("Error al crear carpeta: " + data.error)
        }
      } catch (error) {
        alert("Error de red al crear carpeta: " + error.message)
      }
    } else {
      console.warn("New folder name is empty.")
    }
  }

  async function handleUploadFile() {
    if (selectedFileForUpload) {
      const formData = new FormData()
      formData.append("file", selectedFileForUpload)
      formData.append("name", uploadFileNameInput.value.trim() || selectedFileForUpload.name)
      formData.append("folderId", currentFolderId)

      try {
        const response = await fetch(`${API_BASE_URL}?action=upload_file`, {
          method: "POST",
          body: formData,
        })
        const data = await response.json()
        if (data.success) {
          selectedFileForUpload = null
          if (uploadFileInput) uploadFileInput.value = "" // Clear file input
          if (uploadFileNameInput) uploadFileNameInput.value = "" // Clear name input
          closeDialog(uploadFileDialog)
          await updateDisplay()
          alert("Archivo subido exitosamente.")
        } else {
          alert("Error al subir archivo: " + data.error)
        }
      } catch (error) {
        alert("Error de red al subir archivo: " + error.message)
      }
    } else {
      console.warn("No file selected for upload.")
    }
  }

  async function handleEditFolder(id, newName) {
    try {
      const response = await fetch(`${API_BASE_URL}?action=edit_folder`, {
        method: "POST", // Or PUT
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, name: newName }),
      })
      const data = await response.json()
      if (data.success) {
        await updateSidebar()
        await updateDisplay()
        alert("Carpeta actualizada exitosamente.")
      } else {
        alert("Error al actualizar carpeta: " + data.error)
      }
    } catch (error) {
      alert("Error de red al actualizar carpeta: " + error.message)
    }
  }

  async function handleDeleteFolder(id) {
    try {
      const response = await fetch(`${API_BASE_URL}?action=delete_folder`, {
        method: "POST", // Or DELETE
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id }),
      })
      const data = await response.json()
      if (data.success) {
        // After deletion, check if currentFolderId was deleted or is a child of a deleted folder
        const deletedFolderIds = data.deleted_ids || [id] // PHP might return all deleted IDs
        if (deletedFolderIds.includes(currentFolderId) || !folders.some((f) => f.id === currentFolderId)) {
          // If the current folder or its ancestor was deleted, navigate to 'todos' or 'mi-unidad'
          currentFolderId = "mi-unidad" // Or a more robust default
        }
        await updateSidebar()
        await updateDisplay()
        alert("Carpeta eliminada exitosamente.")
      } else {
        alert("Error al eliminar carpeta: " + data.error)
      }
    } catch (error) {
      alert("Error de red al eliminar carpeta: " + error.message)
    }
  }

  async function handleEditFile(id, newName, newContent = null) {
    try {
      const bodyData = { id: id, name: newName }
      if (newContent !== null) {
        // Only send content if it's a text file and content was edited
        bodyData.content = newContent
      }

      const response = await fetch(`${API_BASE_URL}?action=edit_file`, {
        method: "POST", // Or PUT
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(bodyData),
      })
      const data = await response.json()
      if (data.success) {
        await updateDisplay()
        alert("Archivo actualizado exitosamente.")
      } else {
        alert("Error al actualizar archivo: " + data.error)
      }
    } catch (error) {
      alert("Error de red al actualizar archivo: " + error.message)
    }
  }

  async function handleDeleteFile(id) {
    try {
      const response = await fetch(`${API_BASE_URL}?action=delete_file`, {
        method: "POST", // Or DELETE
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id }),
      })
      const data = await response.json()
      if (data.success) {
        await updateDisplay()
        alert("Archivo eliminado exitosamente.")
      } else {
        alert("Error al eliminar archivo: " + data.error)
      }
    } catch (error) {
      alert("Error de red al eliminar archivo: " + error.message)
    }
  }

  // --- Attach Event Listeners ---
  if (sidebarFoldersList) {
    sidebarFoldersList.addEventListener("click", (event) => {
      const button = event.target.closest(".folder-button")
      if (button) {
        const folderId = button.dataset.folderId
        if (!folderId) return

        const clickedOnChevron = event.target.closest(".chevron-icon")
        const hasChildren = folders.some((f) => f.parent_id === folderId)

        if (clickedOnChevron && hasChildren) {
          // Toggle collapsible state in our state management
          if (openFolderStates.has(folderId)) {
            openFolderStates.delete(folderId)
          } else {
            openFolderStates.add(folderId)
          }
          updateSidebar() // Re-render sidebar to reflect new open/closed state
        } else {
          // Clicked on folder name/icon, or folder has no children
          if (currentFolderId !== folderId) {
            currentFolderId = folderId
            viewingFileId = null // Clear any file being viewed
            updateDisplay()
            updateSidebar() // Re-render sidebar to update active state and ensure correct collapsible states
          }
        }
      }
    })
  } else {
    console.error("Cannot attach sidebar event listener: sidebarFoldersList is null.")
  }

  function attachContentAreaEventListeners() {
    if (!contentArea) {
      console.error("Content area not found, cannot attach content event listeners.")
      return
    }

    // Folder navigation (from content area cards)
    contentArea.querySelectorAll(".folder-navigate-button").forEach((button) => {
      button.onclick = (event) => {
        if (event.target.closest(".dropdown-menu-trigger-button")) {
          return
        }
        const folderId = button.dataset.id
        if (folderId) {
          currentFolderId = folderId
          viewingFileId = null // Clear any file being viewed
          updateDisplay()
          updateSidebar()
        }
      }
    })

    // File open action (direct click on file card/item)
    contentArea.querySelectorAll(".file-open-button").forEach((element) => {
      element.onclick = (event) => {
        event.stopPropagation() // Prevent card click from triggering if it's a dropdown item
        const fileId = element.dataset.id
        if (fileId) {
          viewingFileId = fileId // Set file to be viewed
          renderFileViewer(fileId) // Open the file viewer dialog
        }
      }
    })

    // Dropdown menu toggling
    contentArea.querySelectorAll(".dropdown-menu-trigger-button").forEach((button) => {
      button.onclick = (event) => {
        event.stopPropagation()
        const dropdownContent = button.nextElementSibling
        if (dropdownContent && dropdownContent.classList.contains("dropdown-menu-content")) {
          dropdownContent.classList.toggle("hidden")
        }
        document.querySelectorAll(".dropdown-menu-content:not(.hidden)").forEach((openDropdown) => {
          if (openDropdown !== dropdownContent) {
            openDropdown.classList.add("hidden")
          }
        })
      }
    })

    document.onclick = (event) => {
      if (!event.target.closest(".dropdown-menu-container") && !event.target.closest(".action-button")) {
        document.querySelectorAll(".dropdown-menu-content:not(.hidden)").forEach((openDropdown) => {
          openDropdown.classList.add("hidden")
        })
      }
    }

    // Edit actions
    contentArea.querySelectorAll(".edit-action").forEach((button) => {
      button.onclick = (event) => {
        event.stopPropagation()
        const id = button.dataset.id
        const name = button.dataset.name
        const type = button.dataset.type // 'folder' or 'file'
        const fileType = button.dataset.fileType // e.g., 'txt', 'pdf'

        if (editTitle) editTitle.textContent = `Editar ${type === "folder" ? "Carpeta" : "Archivo"}`
        if (editDescription)
          editDescription.textContent = `Cambia el nombre de ${type === "folder" ? "la carpeta" : "el archivo"}.`
        if (editInput) {
          editInput.value = name
          editInput.dataset.editId = id
          editInput.dataset.editType = type
          editInput.dataset.fileType = fileType // Store file type for later use
        }

        // Show/hide content textarea based on file type
        if (type === "file" && fileType === "txt") {
          fileContentGroup.classList.remove("hidden")
          const fileToEdit = files.find((f) => f.id === id)
          if (fileToEdit && editContentTextarea) {
            editContentTextarea.value = fileToEdit.content
          }
        } else {
          fileContentGroup.classList.add("hidden")
          if (editContentTextarea) editContentTextarea.value = ""
        }

        if (editConfirmBtn) {
          editConfirmBtn.onclick = () => {
            const newName = editInput ? editInput.value.trim() : ""
            const newContent =
              type === "file" && fileType === "txt" ? (editContentTextarea ? editContentTextarea.value : null) : null

            if (newName) {
              if (type === "folder") {
                handleEditFolder(id, newName)
              } else {
                handleEditFile(id, newName, newContent)
              }
            }
            closeDialog(editDialog)
          }
        }
        openDialog(editDialog)
      }
    })

    // Delete actions
    contentArea.querySelectorAll(".delete-action").forEach((button) => {
      button.onclick = (event) => {
        event.stopPropagation()
        const id = button.dataset.id
        const type = button.dataset.type
        const nameElement = button.closest(".document-card").querySelector(".document-card-title, .font-medium")
        const name = nameElement ? nameElement.textContent : "este elemento"

        if (deleteDescription)
          deleteDescription.innerHTML = `¿Estás seguro de que quieres eliminar ${type === "folder" ? "la carpeta" : "el archivo"} "<strong>${name}</strong>"? Esta acción no se puede deshacer.`

        if (deleteConfirmBtn) {
          deleteConfirmBtn.onclick = () => {
            if (type === "folder") {
              handleDeleteFolder(id)
            } else {
              handleDeleteFile(id)
            }
            closeDialog(deleteDialog)
          }
        }
        openDialog(deleteDialog)
      }
    })
  }

  // Initial setup
  updateSidebar()
  updateDisplay()

  // --- Global Event Listeners ---
  if (newButton) {
    newButton.onclick = () => {
      if (newButtonDropdownContent) newButtonDropdownContent.classList.toggle("hidden")
    }
  }

  const newFolderAction = documentsModuleContainer.querySelector('.dropdown-menu-item[data-action="new-folder"]')
  if (newFolderAction) {
    newFolderAction.onclick = () => {
      if (newButtonDropdownContent) newButtonDropdownContent.classList.add("hidden")
      currentFolderCreationMode = "subfolder" // Establece el modo a subcarpeta
      openDialog(createFolderDialog)
      if (createFolderInput) createFolderInput.focus()
    }
  }

  const newPrincipalFolderAction = documentsModuleContainer.querySelector(
    '.dropdown-menu-item[data-action="new-principal-folder"]',
  )
  if (newPrincipalFolderAction) {
    newPrincipalFolderAction.onclick = () => {
      if (newButtonDropdownContent) newButtonDropdownContent.classList.add("hidden")
      currentFolderCreationMode = "principal" // Establece el modo a principal
      openDialog(createFolderDialog)
      if (createFolderInput) createFolderInput.focus()
    }
  }

  const uploadFileAction = documentsModuleContainer.querySelector('.dropdown-menu-item[data-action="upload-file"]')
  if (uploadFileAction) {
    uploadFileAction.onclick = () => {
      if (newButtonDropdownContent) newButtonDropdownContent.classList.add("hidden")
      openDialog(uploadFileDialog)
    }
  }

  if (createFolderCancelBtn) createFolderCancelBtn.onclick = () => closeDialog(createFolderDialog)
  if (createFolderConfirmBtn) createFolderConfirmBtn.onclick = handleConfirmCreateFolder

  if (uploadFileCancelBtn) uploadFileCancelBtn.onclick = () => closeDialog(uploadFileDialog)
  if (uploadFileConfirmBtn) uploadFileConfirmBtn.onclick = handleUploadFile
  if (uploadFileInput) {
    uploadFileInput.onchange = (event) => {
      selectedFileForUpload = event.target.files ? event.target.files[0] : null
      if (uploadFileConfirmBtn) uploadFileConfirmBtn.disabled = !selectedFileForUpload
      if (uploadFileNameInput && selectedFileForUpload) {
        uploadFileNameInput.value = selectedFileForUpload.name // Pre-fill name input
      }
    }
  }

  if (kanbanViewToggle) {
    kanbanViewToggle.onclick = () => {
      viewMode = "kanban"
      if (kanbanViewToggle) kanbanViewToggle.setAttribute("aria-pressed", "true")
      if (listViewToggle) listViewToggle.setAttribute("aria-pressed", "false")
      updateDisplay()
    }
  }

  if (listViewToggle) {
    listViewToggle.onclick = () => {
      viewMode = "list"
      if (listViewToggle) listViewToggle.setAttribute("aria-pressed", "true")
      if (kanbanViewToggle) kanbanViewToggle.setAttribute("aria-pressed", "false")
      updateDisplay()
    }
  }

  documentsModuleContainer.querySelectorAll(".dialog-overlay .button.outline").forEach((button) => {
    button.onclick = (event) => {
      closeDialog(event.target.closest(".dialog-overlay"))
    }
  })

  // Declare closeDialog and openDialog functions
  function closeDialog(dialog) {
    if (dialog) dialog.classList.add("hidden")
  }

  function openDialog(dialog) {
    if (dialog) dialog.classList.remove("hidden")
  }
})
