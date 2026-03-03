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
    ]
];

return $defaults;
