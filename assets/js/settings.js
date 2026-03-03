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
        const response = await fetch(BASE_PATH + '/api/get-settings.php');
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
            if (s.calendarEnabled !== undefined) {
                document.getElementById('calendar-enabled').checked = s.calendarEnabled;
                updateCalendarStatusInfo(s.calendarEnabled);
            }
            if (s.botMode) {
                const radio = document.querySelector(`input[name="bot-mode"][value="${s.botMode}"]`);
                if (radio) radio.checked = true;
                updateClassicModeLink(s.botMode);
            }
            
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
        contextMessagesCount: parseInt(document.getElementById('context-messages-count').value),
        calendarEnabled: document.getElementById('calendar-enabled').checked,
        botMode: document.querySelector('input[name="bot-mode"]:checked')?.value || 'ai'
    };
    
    try {
        const saveRes = await fetch(BASE_PATH + '/api/save-settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(settings)
        });
        
        const data = await saveRes.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error al guardar');
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        showToast('Error al guardar la configuración: ' + error.message, 'error');
        return;
    }
    
    showToast('Configuración guardada correctamente', 'success');
}

function resetSettings() {
    showConfirmModal('¿Restablecer la configuración a los valores por defecto?', {
        title: 'Restablecer configuración',
        confirmText: 'Restablecer',
        cancelText: 'Cancelar',
        isDanger: false,
        onConfirm: async () => {
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
            document.getElementById('calendar-enabled').checked = true;

            confidenceSlider.dispatchEvent(new Event('input'));
            temperatureSlider.dispatchEvent(new Event('input'));

            await saveSettings();
            showToast('Configuración restablecida a valores por defecto', 'info');
        },
    });
}

function updateCalendarStatusInfo(enabled) {
    const info = document.getElementById('calendar-status-info');
    if (enabled) {
        info.innerHTML = '<span class="text-green-600 dark:text-green-400">\u2705 El m\u00f3dulo de calendario est\u00e1 activo. Los usuarios pueden agendar citas por WhatsApp.</span>';
    } else {
        info.innerHTML = '<span class="text-yellow-600 dark:text-yellow-400">\u26a0\ufe0f El m\u00f3dulo de calendario est\u00e1 desactivado. Los flujos de agendamiento activos ser\u00e1n reseteados.</span>';
    }
}

function updateClassicModeLink(mode) {
    const link = document.getElementById('classic-mode-link');
    if (link) link.classList.toggle('hidden', mode !== 'classic');
}

document.getElementById('calendar-enabled').addEventListener('change', function() {
    updateCalendarStatusInfo(this.checked);
});

document.querySelectorAll('input[name="bot-mode"]').forEach(radio => {
    radio.addEventListener('change', e => updateClassicModeLink(e.target.value));
});

loadSettings();
