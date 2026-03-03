<?php
$pageTitle = 'Constructor de Flujos - WhatsApp Bot';
$currentPage = 'flow-builder';

ob_start();
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Constructor de Flujos</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Define los flujos conversacionales del modo Bot Clásico</p>
    </div>
    <div class="flex space-x-3">
        <button onclick="exportFlow()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-all flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            <span>Exportar JSON</span>
        </button>
        <label class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-all flex items-center space-x-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/></svg>
            <span>Importar JSON</span>
            <input type="file" accept=".json" class="hidden" onchange="importFlow(event)">
        </label>
        <button onclick="openNodeModal()" class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Nuevo Nodo</span>
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Node list -->
    <div class="lg:col-span-2 space-y-4">
        <div id="nodes-container">
            <div class="text-center py-12 text-gray-400">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-4"></div>
                <p>Cargando flujos...</p>
            </div>
        </div>
    </div>

    <!-- Simulator panel -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 sticky top-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Simulador de Conversación
            </h3>
            <div id="sim-chat" class="h-72 overflow-y-auto bg-gray-50 dark:bg-gray-900 rounded-lg p-3 space-y-2 mb-3 text-sm">
                <p class="text-gray-400 text-center text-xs pt-8">Escribe un mensaje para simular</p>
            </div>
            <div class="flex space-x-2">
                <input id="sim-input" type="text" placeholder="Escribe un mensaje..." class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" onkeydown="if(event.key==='Enter') sendSimMessage()">
                <button onclick="sendSimMessage()" class="px-3 py-2 bg-primary hover:bg-secondary text-white rounded-lg transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </div>
            <button onclick="resetSimulator()" class="mt-2 w-full text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all">↺ Reiniciar conversación</button>
        </div>
    </div>
</div>

<!-- Node Modal -->
<div id="node-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 id="modal-title" class="text-xl font-bold text-gray-900 dark:text-gray-100">Nuevo Nodo</h2>
            <button onclick="closeNodeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <input type="hidden" id="node-id">

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del nodo</label>
                <input type="text" id="node-name" placeholder="Ej: Bienvenida, Menú Principal..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Palabras que activan este nodo</label>
                <div id="keywords-tags" class="flex flex-wrap gap-2 p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 min-h-[44px]"></div>
                <input type="text" id="keywords-input" placeholder="Escribe una palabra y presiona Enter..." class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" onkeydown="handleKeywordInput(event)">
                <p class="text-xs text-gray-400 mt-1">Presiona Enter o coma para agregar</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mensaje de respuesta</label>
                <textarea id="node-message" rows="4" placeholder="Escribe el mensaje que el bot enviará..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                <div id="message-preview" class="mt-2 hidden">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Vista previa WhatsApp:</p>
                    <div class="bg-green-100 dark:bg-green-900 rounded-lg px-3 py-2 text-sm text-gray-800 dark:text-gray-200 inline-block max-w-xs whitespace-pre-wrap"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Nodo raíz</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Activado sin contexto previo</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="node-is-root" class="sr-only peer" onchange="onIsRootChange()">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary dark:bg-gray-700"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Activa calendario</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Delega al flujo de citas</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="node-requires-calendar" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600 dark:bg-gray-700"></div>
                    </label>
                </div>
            </div>

            <div id="match-any-input-row" class="hidden">
                <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Activar con cualquier mensaje</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Se dispara con cualquier mensaje cuando no hay sesión activa</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="node-match-any-input" class="sr-only peer" onchange="onMatchAnyInputChange()">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500 dark:bg-gray-700"></div>
                    </label>
                </div>
                <div id="match-any-notice" class="hidden mt-2 px-3 py-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg text-xs text-yellow-700 dark:text-yellow-300">
                    Las palabras clave son ignoradas cuando este modo está activo.
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nodo siguiente (automático)</label>
                <select id="node-next" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">— Ninguno —</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">Si no hay opciones que coincidan, el bot irá aquí automáticamente</p>
            </div>

            <!-- Options section -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Opciones del menú</label>
                    <button onclick="addOption()" type="button" class="text-xs px-3 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-all">+ Agregar opción</button>
                </div>
                <div id="options-container" class="space-y-3"></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Orden</label>
                    <input type="number" id="node-order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg mt-5">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Activo</p>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="node-is-active" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent dark:bg-gray-700"></div>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 dark:border-gray-700">
            <button onclick="closeNodeModal()" class="px-5 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">Cancelar</button>
            <button onclick="saveNode()" class="px-5 py-2 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all">Guardar Nodo</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$scripts = '';
$extraScripts = '<script src="' . (defined('BASE_PATH') ? BASE_PATH : '') . '/assets/js/flow-builder.js"></script>';

require __DIR__ . '/layout.php';
?>
