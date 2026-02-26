<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;

header('Content-Type: application/json');

try {
    $config = Config::load(__DIR__ . '/../config/config.php');
    $db = Database::getInstance(Config::get('database'));
    
    $settings = $db->fetchAll(
        "SELECT setting_key, setting_value FROM calendar_settings",
        []
    );
    
    $response = [
        'timezone' => 'America/Bogota',
        'default_duration_minutes' => 60,
        'max_events_per_day' => 10,
        'min_advance_hours' => 1,
        'business_hours' => [
            'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            'saturday' => ['enabled' => true, 'start' => '10:00', 'end' => '14:00'],
            'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00']
        ]
    ];
    
    foreach ($settings as $setting) {
        $key = $setting['setting_key'];
        $value = $setting['setting_value'];
        
        switch ($key) {
            case 'timezone':
                $response['timezone'] = $value;
                break;
            
            case 'default_duration_minutes':
                $response['default_duration_minutes'] = (int)$value;
                break;
            
            case 'max_events_per_day':
                $response['max_events_per_day'] = (int)$value;
                break;
            
            case 'min_advance_hours':
                $response['min_advance_hours'] = (int)$value;
                break;
            
            default:
                if (preg_match('/^business_hours_(\w+)$/', $key, $matches)) {
                    $day = $matches[1];
                    $dayData = json_decode($value, true);
                    if ($dayData) {
                        $response['business_hours'][$day] = $dayData;
                    }
                }
                break;
        }
    }
    
    ob_clean();
    echo json_encode($response);
    
} catch (\Exception $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
