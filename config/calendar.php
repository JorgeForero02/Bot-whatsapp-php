<?php

$defaults = [
    'enabled' => getenv('CALENDAR_ENABLED') !== 'false',
    
    'credentials' => [
        'access_token' => getenv('GOOGLE_CALENDAR_ACCESS_TOKEN'),
        'refresh_token' => getenv('GOOGLE_CALENDAR_REFRESH_TOKEN'),
        'client_id' => getenv('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'calendar_id' => getenv('GOOGLE_CALENDAR_ID') ?: 'primary'
    ],
    
    'timezone' => 'America/Bogota',
    
    'default_duration_minutes' => 60,
    
    'business_hours' => [
        'monday' => ['start' => '09:00', 'end' => '18:00'],
        'tuesday' => ['start' => '09:00', 'end' => '18:00'],
        'wednesday' => ['start' => '09:00', 'end' => '18:00'],
        'thursday' => ['start' => '09:00', 'end' => '18:00'],
        'friday' => ['start' => '09:00', 'end' => '18:00'],
        'saturday' => ['start' => '10:00', 'end' => '14:00'],
        'sunday' => null
    ],
    
    'max_events_per_day' => 10,
    
    'min_advance_hours' => 1,
    
    'reminders' => [
        'email' => [
            'enabled' => true,
            'minutes_before' => 24 * 60
        ],
        'popup' => [
            'enabled' => true,
            'minutes_before' => 30
        ]
    ],
    
    'keywords' => [
        'list' => [
            'eventos', 'agendado', 'calendario', 'próximos eventos', 'qué tengo', 
            'mis eventos', 'mis citas', 'tengo citas', 'citas tengo', 'agenda del día',
            'agenda de hoy', 'agenda de mañana', 'próxima cita', 'siguiente cita',
            'cita próxima', 'eventos de', 'citas de'
        ],
        'availability' => [
            'disponible', 'disponibilidad', 'tienes tiempo', 'estás libre', 
            'tiempo libre', 'espacios libres', 'horarios disponibles', 'puedes atender',
            'hay espacio', 'hay disponibilidad'
        ],
        'create' => [
            'agendar', 'programar', 'reservar', 'apartar', 'crear',
            'agenda', 'agendo', 'programa', 'programo', 'reserva', 'reservo', 
            'aparta', 'aparto', 'crear evento', 'crear cita', 'apartar cita', 
            'agendar cita', 'hacer cita', 'poner cita', 'guardar cita', 
            'cita para', 'evento para', 'quiero agendar', 'necesito agendar', 
            'quisiera agendar', 'solicito cita', 'pedir cita'
        ]
    ]
];

return $defaults;
