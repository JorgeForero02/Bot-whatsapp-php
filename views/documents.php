<?php
$pageTitle = 'Documentos - WhatsApp Bot';
$currentPage = 'documents';

ob_start();
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Documentos</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Gestiona la base de conocimiento del bot subiendo y organizando documentos</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Subir Nuevo Documento
            </h2>
            
            <form id="upload-form" enctype="multipart/form-data">
                <div id="drop-zone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center hover:border-primary dark:hover:border-primary transition-colors cursor-pointer bg-gray-50 dark:bg-gray-900">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <p class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Arrastra archivos aquí o haz clic para seleccionar</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Formatos soportados: PDF, DOCX, TXT (Máx. 10MB)</p>
                    <input type="file" id="document" name="document" accept=".pdf,.docx,.txt" required class="hidden">
                    <button type="button" onclick="document.getElementById('document').click()" class="px-6 py-2 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all">
                        Seleccionar Archivo
                    </button>
                </div>
                
                <div id="file-preview" class="hidden mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100" id="file-name"></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400" id="file-size"></p>
                            </div>
                        </div>
                        <button type="button" onclick="clearFile()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" id="upload-btn" class="hidden mt-4 w-full px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all flex items-center justify-center space-x-2">
                    <span>Procesar Documento</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </button>
            </form>
            
            <div id="upload-status" class="mt-4"></div>
        </div>
    </div>
    
    <div class="lg:col-span-1">
        <div class="bg-primary dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-white dark:text-gray-100">
            <h3 class="text-lg font-bold mb-4">Información</h3>
            <div class="space-y-3 text-sm">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>Los documentos se procesan automáticamente y se dividen en chunks para la búsqueda vectorial.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>Formatos soportados: PDF para manuales, DOCX para políticas, TXT para FAQs.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>El procesamiento puede tardar unos segundos dependiendo del tamaño del archivo.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
            <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Documentos Indexados
        </h2>
        <button onclick="loadDocuments()" class="text-primary hover:text-secondary transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
        </button>
    </div>
    
    <div id="documents-container" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary mb-4"></div>
        <p class="text-gray-600 dark:text-gray-400">Cargando documentos...</p>
    </div>
</div>

<!-- Modal para ver contenido del documento -->
<div id="document-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span id="modal-document-name">Documento</span>
            </h3>
            <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div id="modal-document-content" class="flex-1 overflow-y-auto p-6 bg-gray-50 dark:bg-gray-900">
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary mb-4"></div>
                <p class="text-gray-600 dark:text-gray-400">Cargando contenido...</p>
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button onclick="closeDocumentModal()" class="w-full px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all">
                Cerrar
            </button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>

const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('document');
const filePreview = document.getElementById('file-preview');
const uploadBtn = document.getElementById('upload-btn');

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-primary', 'bg-blue-50');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-primary', 'bg-blue-50');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-blue-50');
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        displayFilePreview(e.dataTransfer.files[0]);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        displayFilePreview(e.target.files[0]);
    }
});

function displayFilePreview(file) {
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatBytes(file.size);
    filePreview.classList.remove('hidden');
    uploadBtn.classList.remove('hidden');
}

function clearFile() {
    fileInput.value = '';
    filePreview.classList.add('hidden');
    uploadBtn.classList.add('hidden');
}

document.getElementById('upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData();
    
    if (!fileInput.files[0]) {
        return;
    }
    
    formData.append('document', fileInput.files[0]);
    
    const statusDiv = document.getElementById('upload-status');
    const uploadButton = uploadBtn;
    
    uploadButton.disabled = true;
    uploadButton.innerHTML = '<span class="inline-block animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></span><span>Procesando...</span>';
    
    statusDiv.innerHTML = `
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800">
            <div class="flex items-center">
                <svg class="animate-spin h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Procesando documento y generando embeddings...</span>
            </div>
        </div>
    `;
    
    try {
        const response = await fetch('/api/upload.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            statusDiv.innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-800">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="font-medium">Documento procesado correctamente</p>
                            <p class="text-sm mt-1">${data.document.chunks} chunks indexados en la base de conocimiento</p>
                        </div>
                    </div>
                </div>
            `;
            clearFile();
            setTimeout(() => {
                statusDiv.innerHTML = '';
                loadDocuments();
            }, 4000);
        } else {
            throw new Error(data.error);
        }
        
    } catch (error) {
        statusDiv.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800">
                <div class="flex items-center">
                    <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="font-medium">Error al procesar documento</p>
                        <p class="text-sm mt-1">${error.message}</p>
                    </div>
                </div>
            </div>
        `;
    } finally {
        uploadButton.disabled = false;
        uploadButton.innerHTML = '<span>Procesar Documento</span><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    }
});

async function loadDocuments() {
    try {
        const response = await fetch('/api/get-documents.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error loading documents');
        }
        
        const container = document.getElementById('documents-container');
        
        if (data.documents.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-lg font-medium text-gray-500">No hay documentos indexados</p>
                    <p class="text-sm text-gray-400 mt-2">Sube tu primer documento para comenzar</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
        
        data.documents.forEach(doc => {
            const typeColors = {
                'pdf': 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                'docx': 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                'txt': 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
            };
            const typeColor = typeColors[doc.file_type] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
            
            const typeIcons = {
                'pdf': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>',
                'docx': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
                'txt': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'
            };
            const typeIcon = typeIcons[doc.file_type] || typeIcons['txt'];
            
            html += `
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start space-x-3 mb-3">
                        <div class="flex-shrink-0">
                            <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                ${typeIcon}
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 truncate" title="${doc.original_name}">${doc.original_name}</h4>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${typeColor}">
                                    ${doc.file_type.toUpperCase()}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">${formatBytes(doc.file_size)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 mb-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Chunks indexados</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">${doc.chunk_count}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Fecha de subida</span>
                            <span class="text-gray-500 dark:text-gray-400">${new Date(doc.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="viewDocument(${doc.id}, '${doc.original_name}')" class="px-3 py-2 bg-primary dark:bg-primary/80 hover:bg-secondary dark:hover:bg-secondary text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <span>Ver</span>
                        </button>
                        <button onclick="deleteDocument(${doc.id}, '${doc.original_name}')" class="px-3 py-2 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Eliminar</span>
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        document.getElementById('documents-container').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800 text-center">
                <svg class="w-12 h-12 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <p class="font-medium">Error al cargar documentos</p>
                <p class="text-sm mt-1">${error.message}</p>
            </div>
        `;
    }
}

async function deleteDocument(id, name) {
    if (!confirm(`¿Estás seguro de que quieres eliminar "${name}"?\n\nEsta acción eliminará el documento y todos sus vectores asociados.`)) {
        return;
    }
    
    const container = document.getElementById('documents-container');
    const originalHTML = container.innerHTML;
    
    container.innerHTML = `
        <div class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-500 mb-4"></div>
            <p class="text-gray-600">Eliminando documento...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`/api/delete-document.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadDocuments();
        } else {
            throw new Error(data.error);
        }
        
    } catch (error) {
        container.innerHTML = originalHTML;
        alert('Error al eliminar documento: ' + error.message);
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

async function viewDocument(id, name) {
    const modal = document.getElementById('document-modal');
    const modalName = document.getElementById('modal-document-name');
    const modalContent = document.getElementById('modal-document-content');
    
    modalName.textContent = name;
    modal.classList.remove('hidden');
    
    modalContent.innerHTML = `
        <div class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary mb-4"></div>
            <p class="text-gray-600 dark:text-gray-400">Cargando contenido del documento...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`/api/get-document-content.php?id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error al cargar el contenido');
        }
        
        const chunks = data.chunks || [];
        
        if (chunks.length === 0) {
            modalContent.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-20 h-20 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-lg font-medium text-gray-500 dark:text-gray-400">No hay contenido disponible</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="space-y-4">';
        chunks.forEach((chunk, index) => {
            html += `
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Fragmento ${index + 1} de ${chunks.length}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">${chunk.chunk_text.length} caracteres</span>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">${escapeHtml(chunk.chunk_text)}</p>
                </div>
            `;
        });
        html += '</div>';
        
        modalContent.innerHTML = html;
        
    } catch (error) {
        modalContent.innerHTML = `
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-800 dark:text-red-400 text-center">
                <svg class="w-12 h-12 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <p class="font-medium">Error al cargar el contenido</p>
                <p class="text-sm mt-1">${error.message}</p>
            </div>
        `;
    }
}

function closeDocumentModal() {
    document.getElementById('document-modal').classList.add('hidden');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

loadDocuments();

<?php
$scripts = ob_get_clean();

require __DIR__ . '/layout.php';
?>
