<?php
$pageTitle = 'Configuración - WhatsApp Bot';
$currentPage = 'settings';

ob_start();
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Configuración del Bot</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Personaliza el comportamiento y parámetros del bot de WhatsApp</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Configuración RAG
            </h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Umbral de Confianza Mínimo
                    </label>
                    <input type="range" id="confidence-threshold" min="0" max="1" step="0.05" value="0.7" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <span>0%</span>
                        <span id="confidence-value" class="font-semibold text-primary">70%</span>
                        <span>100%</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Define el nivel mínimo de confianza para que el bot responda automáticamente. Si la respuesta tiene menos confianza, se marcará como "Pendiente de revisión humana".
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Número Máximo de Resultados de Búsqueda
                    </label>
                    <select id="max-results" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="3">3 resultados</option>
                        <option value="5" selected>5 resultados</option>
                        <option value="7">7 resultados</option>
                        <option value="10">10 resultados</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Cantidad de fragmentos de documentos que se recuperarán de la base de conocimiento para generar la respuesta. Más resultados = respuestas más completas pero más lentas.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Tamaño de Chunk (Fragmentos de Texto)
                    </label>
                    <select id="chunk-size" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="500">500 caracteres</option>
                        <option value="1000" selected>1000 caracteres</option>
                        <option value="1500">1500 caracteres</option>
                        <option value="2000">2000 caracteres</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Tamaño de los fragmentos en los que se dividen los documentos. Chunks más pequeños son más precisos, chunks más grandes dan más contexto.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                Prompt del Sistema
            </h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Instrucciones del Sistema para el Bot
                    </label>
                    <textarea id="system-prompt" rows="6" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm" placeholder="Eres un asistente virtual..."></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Este es el prompt del sistema que define cómo se comporta el bot. Define su personalidad, tono y forma de responder. Los cambios se aplican inmediatamente a todas las nuevas respuestas.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
                Respuestas Automáticas
            </h2>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Activar Respuestas Automáticas</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Permite que el bot responda automáticamente a los mensajes cuando la confianza supere el umbral configurado.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="checkbox" id="auto-reply" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 dark:peer-focus:ring-primary/40 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Mensaje de Bienvenida
                    </label>
                    <textarea id="welcome-message" rows="3" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Hola, soy el asistente virtual. ¿En qué puedo ayudarte?"></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Mensaje que se envía automáticamente cuando un usuario inicia una nueva conversación.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Mensaje de Error (Baja Confianza)
                    </label>
                    <textarea id="error-message" rows="3" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Lo siento, no tengo suficiente información para responder. Un agente te atenderá pronto."></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Mensaje que se envía cuando el bot no puede responder con suficiente confianza.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Configuración Avanzada
            </h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Modelo de OpenAI
                    </label>
                    <select id="openai-model" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo (Rápido y económico)</option>
                        <option value="gpt-4" selected>GPT-4 (Más preciso)</option>
                        <option value="gpt-4-turbo">GPT-4 Turbo (Equilibrado)</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Modelo de IA usado para generar respuestas. GPT-4 es más preciso pero más costoso.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Temperatura de Respuesta
                    </label>
                    <input type="range" id="temperature" min="0" max="1" step="0.1" value="0.7" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <span>Precisa</span>
                        <span id="temperature-value" class="font-semibold text-primary">0.7</span>
                        <span>Creativa</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Controla la creatividad de las respuestas. 0 = Muy precisa y consistente, 1 = Más creativa y variada.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Timeout de Respuesta (segundos)
                    </label>
                    <input type="number" id="timeout" min="5" max="60" value="30" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Tiempo máximo de espera para obtener una respuesta de OpenAI antes de dar timeout.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Mensajes de Contexto
                    </label>
                    <input type="number" id="context-messages-count" min="0" max="20" value="5" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Cantidad de mensajes anteriores de la conversación que el bot verá como contexto. 0 para desactivar.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            <button onclick="resetSettings()" class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                Restablecer
            </button>
            <button onclick="saveSettings()" class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Guardar Configuración</span>
            </button>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 sticky top-6">
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-gray-100">Información</h3>
            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>Los cambios en la configuración se aplicarán inmediatamente a todas las nuevas conversaciones.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p>Un umbral de confianza muy bajo puede causar respuestas incorrectas, mientras que uno muy alto puede requerir más intervención humana.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <p>La configuración se guarda localmente y persistirá entre sesiones.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>

const confidenceSlider = document.getElementById('confidence-threshold');
const confidenceValue = document.getElementById('confidence-value');
const temperatureSlider = document.getElementById('temperature');
const temperatureValue = document.getElementById('temperature-value');

confidenceSlider?.addEventListener('input', function() {
    const value = Math.round(this.value * 100);
    confidenceValue.textContent = value + '%';
});

temperatureSlider?.addEventListener('input', function() {
    temperatureValue.textContent = this.value;
});

async function loadSettings() {
    try {
        const response = await fetch('/api/get-settings.php');
        const data = await response.json();
        
        if (data.success && data.settings) {
            const s = data.settings;
            
            if (s.systemPrompt) document.getElementById('system-prompt').value = s.systemPrompt;
            if (s.welcomeMessage) document.getElementById('welcome-message').value = s.welcomeMessage;
            if (s.errorMessage) document.getElementById('error-message').value = s.errorMessage;
            if (s.confidenceThreshold !== undefined) confidenceSlider.value = s.confidenceThreshold;
            if (s.maxResults) document.getElementById('max-results').value = s.maxResults;
            if (s.chunkSize) document.getElementById('chunk-size').value = s.chunkSize;
            if (s.autoReply !== undefined) document.getElementById('auto-reply').checked = s.autoReply;
            if (s.openaiModel) document.getElementById('openai-model').value = s.openaiModel;
            if (s.temperature !== undefined) temperatureSlider.value = s.temperature;
            if (s.timeout) document.getElementById('timeout').value = s.timeout;
            if (s.contextMessagesCount !== undefined) document.getElementById('context-messages-count').value = s.contextMessagesCount;
            
            confidenceSlider.dispatchEvent(new Event('input'));
            temperatureSlider.dispatchEvent(new Event('input'));
        }
    } catch (error) {
        console.error('Error loading settings:', error);
    }
}

async function saveSettings() {
    const settings = {
        confidenceThreshold: parseFloat(confidenceSlider.value),
        maxResults: parseInt(document.getElementById('max-results').value),
        chunkSize: parseInt(document.getElementById('chunk-size').value),
        systemPrompt: document.getElementById('system-prompt').value,
        autoReply: document.getElementById('auto-reply').checked,
        welcomeMessage: document.getElementById('welcome-message').value,
        errorMessage: document.getElementById('error-message').value,
        openaiModel: document.getElementById('openai-model').value,
        temperature: parseFloat(temperatureSlider.value),
        timeout: parseInt(document.getElementById('timeout').value),
        contextMessagesCount: parseInt(document.getElementById('context-messages-count').value)
    };
    
    try {
        const response = await fetch('/api/save-settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(settings)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error al guardar');
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        alert('Error al guardar la configuración en la base de datos: ' + error.message);
        return;
    }
    
    const notification = document.createElement('div');
    notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in';
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span>Configuración guardada correctamente</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

async function resetSettings() {
    if (!confirm('¿Estás seguro de que quieres restablecer la configuración a los valores por defecto?')) {
        return;
    }
    
    confidenceSlider.value = 0.7;
    document.getElementById('max-results').value = '5';
    document.getElementById('chunk-size').value = '1000';
    document.getElementById('system-prompt').value = '';
    document.getElementById('auto-reply').checked = true;
    document.getElementById('welcome-message').value = '';
    document.getElementById('error-message').value = '';
    document.getElementById('openai-model').value = 'gpt-4';
    temperatureSlider.value = 0.7;
    document.getElementById('timeout').value = '30';
    document.getElementById('context-messages-count').value = '5';
    
    confidenceSlider.dispatchEvent(new Event('input'));
    temperatureSlider.dispatchEvent(new Event('input'));
    
    await saveSettings();
    
    const notification = document.createElement('div');
    notification.className = 'fixed top-20 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span>Configuración restablecida</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

loadSettings();

<?php
$scripts = ob_get_clean();

require __DIR__ . '/layout.php';
?>
