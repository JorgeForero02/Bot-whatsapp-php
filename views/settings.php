<?php
$pageTitle = 'Configuración - WhatsApp Bot';
$currentPage = 'settings';

ob_start();
?>

<div class="page-header">
    <h1 class="page-title">Configuración del Bot</h1>
    <p class="page-subtitle">Personaliza el comportamiento y parámetros del bot de WhatsApp</p>
</div>

<div class="settings-grid">
    <div class="settings-main">
        <div class="card">
          <div class="card-header">
            <span class="card-title">Configuración RAG</span>
          </div>
          <div class="card-body">
            
            <div class="form-stack">
                <div class="form-group">
                    <label class="form-label">Umbral de Confianza Mínimo</label>
                    <input type="range" id="confidence-threshold" min="0" max="1" step="0.05" value="0.7" class="range-input">
                    <div class="range-labels">
                        <span>0%</span>
                        <span id="confidence-value" class="range-value">70%</span>
                        <span>100%</span>
                    </div>
                    <p class="form-hint">Define el nivel mínimo de confianza para que el bot responda automáticamente.</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Número Máximo de Resultados de Búsqueda</label>
                    <select id="max-results" class="form-select">
                        <option value="3">3 resultados</option>
                        <option value="5" selected>5 resultados</option>
                        <option value="7">7 resultados</option>
                        <option value="10">10 resultados</option>
                    </select>
                    <p class="form-hint">Cantidad de fragmentos recuperados de la base de conocimiento.</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Tamaño de Chunk (Fragmentos de Texto)</label>
                    <select id="chunk-size" class="form-select">
                        <option value="500">500 palabras</option>
                        <option value="1000" selected>1000 palabras</option>
                        <option value="1500">1500 palabras</option>
                        <option value="2000">2000 palabras</option>
                    </select>
                    <p class="form-hint">Tamaño de los fragmentos de documentos. Chunks pequeños son más precisos, grandes dan más contexto.</p>
                </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Prompt del Sistema</span></div>
          <div class="card-body">
            <div class="form-group">
                <label class="form-label">Instrucciones del Sistema para el Bot</label>
                <textarea id="system-prompt" rows="6" class="form-textarea" style="font-family:monospace;" placeholder="Eres un asistente virtual..."></textarea>
                <p class="form-hint">Define la personalidad, tono y forma de responder del bot. Los cambios aplican inmediatamente.</p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Respuestas Automáticas</span></div>
          <div class="card-body">
            <div class="form-stack">
                <div class="toggle-row">
                    <div>
                        <p class="form-label" style="margin-bottom:0.125rem;">Activar Respuestas Automáticas</p>
                        <p class="form-hint" style="margin-top:0;">El bot responde automáticamente cuando la confianza supera el umbral.</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" id="auto-reply" checked>
                        <span class="toggle-thumb"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="welcome-message">Mensaje de Bienvenida</label>
                    <textarea id="welcome-message" rows="3" class="form-textarea" placeholder="Hola, soy el asistente virtual. ¿En qué puedo ayudarte?"></textarea>
                    <p class="form-hint">Se envía cuando un usuario inicia una nueva conversación.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="error-message">Mensaje de Error (Baja Confianza)</label>
                    <textarea id="error-message" rows="3" class="form-textarea" placeholder="Lo siento, no tengo suficiente información. Un agente te atenderá pronto."></textarea>
                    <p class="form-hint">Se envía cuando el bot no puede responder con suficiente confianza.</p>
                </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Módulo de Calendario</span></div>
          <div class="card-body">
            <div class="toggle-row">
                <div>
                    <p class="form-label" style="margin-bottom:0.125rem;">Activar Google Calendar</p>
                    <p class="form-hint" style="margin-top:0;">Los usuarios pueden agendar, listar y consultar disponibilidad desde WhatsApp.</p>
                </div>
                <label class="toggle">
                    <input type="checkbox" id="calendar-enabled" checked>
                    <span class="toggle-thumb"></span>
                </label>
            </div>
            <div id="calendar-status-info" style="margin-top:0.75rem;font-size:0.8125rem;"></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Modo de Operación del Bot</span></div>
          <div class="card-body">
            <div class="bot-mode-grid">
                <label class="bot-mode-option">
                    <input type="radio" name="bot-mode" id="bot-mode-ai" value="ai" class="sr-only">
                    <div class="bot-mode-card">
                        <div>
                            <p class="form-label" style="margin-bottom:0.125rem;">Modo IA</p>
                            <p class="form-hint" style="margin-top:0;">OpenAI + RAG + Calendar. Respuestas inteligentes.</p>
                        </div>
                    </div>
                </label>
                <label class="bot-mode-option">
                    <input type="radio" name="bot-mode" id="bot-mode-classic" value="classic" class="sr-only">
                    <div class="bot-mode-card">
                        <div>
                            <p class="form-label" style="margin-bottom:0.125rem;">Modo Clásico</p>
                            <p class="form-hint" style="margin-top:0;">Flujos por palabras clave. Sin OpenAI.</p>
                        </div>
                    </div>
                </label>
            </div>
            <div id="classic-mode-link" class="hidden" style="margin-top:0.75rem;">
                <a href="<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>/flow-builder"
                   style="display:inline-flex;align-items:center;gap:0.375rem;font-size:0.875rem;color:var(--color-secondary);text-decoration:none;font-weight:500;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    Ir al Constructor de Flujos
                </a>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Configuración Avanzada</span></div>
          <div class="card-body">
            <div class="form-stack">
                <div class="form-group">
                    <label class="form-label" for="openai-model">Modelo de OpenAI</label>
                    <select id="openai-model" class="form-select">
                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo (Rápido y económico)</option>
                        <option value="gpt-4" selected>GPT-4 (Más preciso)</option>
                        <option value="gpt-4-turbo">GPT-4 Turbo (Equilibrado)</option>
                    </select>
                    <p class="form-hint">Modelo de IA usado para generar respuestas.</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Temperatura de Respuesta</label>
                    <input type="range" id="temperature" min="0" max="1" step="0.1" value="0.7" class="range-input">
                    <div class="range-labels">
                        <span>Precisa</span>
                        <span id="temperature-value" class="range-value">0.7</span>
                        <span>Creativa</span>
                    </div>
                    <p class="form-hint">Controla la creatividad. 0 = muy precisa, 1 = más creativa.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="timeout">Timeout (segundos)</label>
                        <input type="number" id="timeout" min="5" max="60" value="30" class="form-input">
                        <p class="form-hint">Tiempo máximo de espera para OpenAI.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="context-messages-count">Mensajes de Contexto</label>
                        <input type="number" id="context-messages-count" min="0" max="20" value="5" class="form-input">
                        <p class="form-hint">Mensajes anteriores visibles al bot. 0 desactiva.</p>
                    </div>
                </div>
            </div>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.75rem;flex-wrap:wrap;">
            <button onclick="resetSettings()" class="btn btn-secondary btn-md">Restablecer</button>
            <button onclick="saveSettings()" class="btn btn-primary btn-md">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Guardar Configuración
            </button>
        </div>
    </div>

    <div class="settings-sidebar">
        <div class="card" style="position:sticky;top:calc(var(--topbar-height) + 1rem);">
          <div class="card-header"><span class="card-title">Información</span></div>
          <div class="card-body">
            <div class="form-stack">
                <p class="form-hint">Los cambios se aplican inmediatamente a todas las nuevas conversaciones.</p>
                <p class="form-hint">Un umbral de confianza muy bajo puede causar respuestas incorrectas.</p>
                <p class="form-hint">La configuración se guarda en la base de datos.</p>
            </div>
          </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$scripts = '';
$extraScripts = '<script src="' . (defined('BASE_PATH') ? BASE_PATH : '') . '/assets/js/settings.js"></script>';

require __DIR__ . '/layout.php';
?>
