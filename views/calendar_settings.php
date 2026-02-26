<?php
$pageTitle = 'Configuración de Calendar - WhatsApp Bot';
$currentPage = 'calendar-settings';

ob_start();
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Configuración de Google Calendar</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Administra los horarios de atención y preferencias de agendamiento de citas</p>
</div>

<div id="alert-container" class="mb-4"></div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <form id="calendar-settings-form">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Horarios de Atención
            </h2>
            
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                    <div class="font-medium text-gray-700 dark:text-gray-300">Día</div>
                    <div class="text-center font-medium text-gray-700 dark:text-gray-300">Abierto</div>
                    <div class="font-medium text-gray-700 dark:text-gray-300">Hora Inicio</div>
                    <div class="font-medium text-gray-700 dark:text-gray-300">Hora Fin</div>
                </div>

                <?php
                $days = [
                    'monday' => 'Lunes',
                    'tuesday' => 'Martes',
                    'wednesday' => 'Miércoles',
                    'thursday' => 'Jueves',
                    'friday' => 'Viernes',
                    'saturday' => 'Sábado',
                    'sunday' => 'Domingo'
                ];
                
                foreach ($days as $dayKey => $dayLabel):
                ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                    <label class="text-gray-700 dark:text-gray-300 font-medium"><?php echo $dayLabel; ?></label>
                    
                    <div class="flex justify-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   id="<?php echo $dayKey; ?>-enabled" 
                                   name="business_hours[<?php echo $dayKey; ?>][enabled]"
                                   class="sr-only peer"
                                   onchange="toggleDayInputs('<?php echo $dayKey; ?>')">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 dark:peer-focus:ring-primary/40 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                        </label>
                    </div>
                    
                    <input type="time" 
                           id="<?php echo $dayKey; ?>-start" 
                           name="business_hours[<?php echo $dayKey; ?>][start]"
                           class="day-time-input px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                           disabled>
                    
                    <input type="time" 
                           id="<?php echo $dayKey; ?>-end" 
                           name="business_hours[<?php echo $dayKey; ?>][end]"
                           class="day-time-input px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                           disabled>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Configuración de Citas
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Zona Horaria
                    </label>
                    <select id="timezone" name="timezone" 
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="America/Bogota">América/Bogotá (GMT-5)</option>
                        <option value="America/Mexico_City">América/Ciudad de México (GMT-6)</option>
                        <option value="America/New_York">América/Nueva York (GMT-5)</option>
                        <option value="America/Los_Angeles">América/Los Ángeles (GMT-8)</option>
                        <option value="America/Chicago">América/Chicago (GMT-6)</option>
                        <option value="America/Lima">América/Lima (GMT-5)</option>
                        <option value="America/Buenos_Aires">América/Buenos Aires (GMT-3)</option>
                        <option value="America/Santiago">América/Santiago (GMT-3)</option>
                        <option value="Europe/Madrid">Europa/Madrid (GMT+1)</option>
                        <option value="Europe/London">Europa/Londres (GMT+0)</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Zona horaria del negocio para las citas
                    </p>
                </div>

                <div>
                    <label for="default_duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Duración Predeterminada (minutos)
                    </label>
                    <input type="number" id="default_duration" name="default_duration_minutes" min="1" step="1"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Tiempo por defecto para cada cita
                    </p>
                </div>

                <div>
                    <label for="max_events" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Máximo de Citas por Día
                    </label>
                    <input type="number" id="max_events" name="max_events_per_day" min="1" step="1"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Límite diario de agendamientos
                    </p>
                </div>

                <div>
                    <label for="min_advance" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Anticipación Mínima (horas)
                    </label>
                    <input type="number" id="min_advance" name="min_advance_hours" min="0" step="1"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Tiempo mínimo requerido para agendar
                    </p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="loadSettings()" 
                    class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                Cancelar
            </button>
            <button type="submit" 
                    class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg font-medium transition-all flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Guardar Configuración</span>
            </button>
        </div>
        </form>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 sticky top-6">
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-gray-100">Información</h3>
            <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>Los cambios en la configuración se aplicarán inmediatamente al bot de WhatsApp.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p>Al menos un día de la semana debe estar abierto para permitir agendamientos.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>La hora de fin debe ser posterior a la hora de inicio en cada día.</p>
                </div>
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <p>La configuración se guarda en la base de datos y persistirá entre sesiones.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>

function toggleDayInputs(day) {
    const enabled = document.getElementById(day + '-enabled').checked;
    document.getElementById(day + '-start').disabled = !enabled;
    document.getElementById(day + '-end').disabled = !enabled;
}

function showNotification(message, type = 'success') {
    const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    const icon = type === 'success' 
        ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>'
        : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
    
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                ${icon}
            </svg>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

async function loadSettings() {
    try {
        const response = await fetch(BASE_PATH + '/api/get-calendar-settings');
        const data = await response.json();
        
        if (data.error) {
            showNotification(data.error, 'error');
            return;
        }
        
        document.getElementById('timezone').value = data.timezone;
        document.getElementById('default_duration').value = data.default_duration_minutes;
        document.getElementById('max_events').value = data.max_events_per_day;
        document.getElementById('min_advance').value = data.min_advance_hours;
        
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        days.forEach(day => {
            const dayData = data.business_hours[day];
            document.getElementById(day + '-enabled').checked = dayData.enabled;
            document.getElementById(day + '-start').value = dayData.start;
            document.getElementById(day + '-end').value = dayData.end;
            toggleDayInputs(day);
        });
        
    } catch (error) {
        showNotification('Error al cargar configuración: ' + error.message, 'error');
    }
}

const calendarForm = document.getElementById('calendar-settings-form');
if (calendarForm) {
    calendarForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
    
    const data = {
        timezone: formData.get('timezone'),
        default_duration_minutes: parseInt(formData.get('default_duration_minutes')),
        max_events_per_day: parseInt(formData.get('max_events_per_day')),
        min_advance_hours: parseInt(formData.get('min_advance_hours')),
        business_hours: {}
    };
    
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    days.forEach(day => {
        data.business_hours[day] = {
            enabled: document.getElementById(day + '-enabled').checked,
            start: document.getElementById(day + '-start').value,
            end: document.getElementById(day + '-end').value
        };
    });
    
    try {
        console.log('Enviando datos:', data);
        
        const response = await fetch(BASE_PATH + '/api/save-calendar-settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Response data:', result);
        
        if (response.ok && result.success) {
            showNotification(result.message, 'success');
        } else {
            showNotification(result.error || 'Error al guardar configuración', 'error');
        }
    } catch (error) {
        console.error('Error completo:', error);
        showNotification('Error de conexión: ' + error.message, 'error');
    }
    });
}

loadSettings();

<?php
$scripts = ob_get_clean();

require __DIR__ . '/layout.php';
?>
