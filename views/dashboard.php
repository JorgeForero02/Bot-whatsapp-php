<?php
$pageTitle = 'Dashboard - WhatsApp Bot';
$currentPage = 'dashboard';

ob_start();
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Vista general del sistema y estadísticas en tiempo real</p>
</div>

<div id="stats-container" class="text-center py-12">
    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
    <p class="mt-4 text-gray-600 dark:text-gray-400">Cargando estadísticas...</p>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>

async function loadStats() {
    try {
        const response = await fetch('/api/get-stats.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error loading stats');
        }
        
        const stats = data.stats;
        
        const html = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Conversaciones</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">${stats.conversations.total}</p>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900 rounded-full p-3">
                            <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Conversaciones Activas</p>
                            <p class="text-3xl font-bold text-accent">${stats.conversations.active}</p>
                        </div>
                        <div class="bg-green-100 dark:bg-green-900 rounded-full p-3">
                            <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Pendientes Humano</p>
                            <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">${stats.conversations.pending_human}</p>
                        </div>
                        <div class="bg-orange-100 dark:bg-orange-900 rounded-full p-3">
                            <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Documentos Indexados</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">${stats.documents.total}</p>
                        </div>
                        <div class="bg-purple-100 dark:bg-purple-900 rounded-full p-3">
                            <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Vectores en Base</p>
                            <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">${stats.vectors}</p>
                        </div>
                        <div class="bg-indigo-100 dark:bg-indigo-900 rounded-full p-3">
                            <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Mensajes</p>
                            <p class="text-3xl font-bold text-teal-600 dark:text-teal-400">${stats.conversations.total_messages}</p>
                        </div>
                        <div class="bg-teal-100 dark:bg-teal-900 rounded-full p-3">
                            <svg class="w-8 h-8 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Estado del Sistema
                    </h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">Estado del Bot</span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                <span class="w-2 h-2 mr-2 rounded-full bg-green-500 animate-pulse"></span>
                                Activo
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">Base de Conocimiento</span>
                            <span class="text-gray-900 dark:text-gray-100 font-semibold">${stats.documents.total} documentos</span>
                        </div>
                        <div class="flex items-center justify-between py-3">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">Tamaño Total</span>
                            <span class="text-gray-900 dark:text-gray-100 font-semibold">${formatBytes(stats.documents.total_size)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-primary dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 text-white dark:text-gray-100">
                    <h2 class="text-xl font-bold mb-4">Acciones Rápidas</h2>
                    <div class="space-y-3">
                        <a href="/conversations" class="block bg-white dark:bg-gray-700 bg-opacity-20 dark:bg-opacity-100 hover:bg-opacity-30 dark:hover:bg-gray-600 rounded-lg p-4 transition-all">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <span class="font-medium">Ver Conversaciones</span>
                            </div>
                        </a>
                        <a href="/documents" class="block bg-white dark:bg-gray-700 bg-opacity-20 dark:bg-opacity-100 hover:bg-opacity-30 dark:hover:bg-gray-600 rounded-lg p-4 transition-all">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <span class="font-medium">Subir Documentos</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('stats-container').innerHTML = html;
        
    } catch (error) {
        document.getElementById('stats-container').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Error al cargar estadísticas: ${error.message}</span>
                </div>
            </div>
        `;
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

loadStats();
setInterval(loadStats, 30000);

<?php
$scripts = ob_get_clean();

require __DIR__ . '/layout.php';
?>
