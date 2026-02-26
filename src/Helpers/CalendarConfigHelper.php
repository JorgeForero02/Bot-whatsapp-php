<?php

namespace App\Helpers;

use App\Core\Database;

class CalendarConfigHelper
{
    public static function loadFromDatabase($db)
    {
        $defaults = require __DIR__ . '/../../config/calendar.php';
        
        try {
            $settings = $db->fetchAll("SELECT setting_key, setting_value FROM calendar_settings", []);
            
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                
                switch ($key) {
                    case 'timezone':
                        $defaults['timezone'] = $value;
                        break;
                    
                    case 'default_duration_minutes':
                        $defaults['default_duration_minutes'] = (int)$value;
                        break;
                    
                    case 'max_events_per_day':
                        $defaults['max_events_per_day'] = (int)$value;
                        break;
                    
                    case 'min_advance_hours':
                        $defaults['min_advance_hours'] = (int)$value;
                        break;
                    
                    default:
                        if (preg_match('/^business_hours_(\w+)$/', $key, $matches)) {
                            $day = $matches[1];
                            $dayData = json_decode($value, true);
                            if ($dayData && isset($dayData['enabled'])) {
                                $defaults['business_hours'][$day] = $dayData['enabled'] 
                                    ? ['start' => $dayData['start'], 'end' => $dayData['end']]
                                    : null;
                            }
                        }
                        break;
                }
            }
        } catch (\Exception $e) {
        }
        
        return $defaults;
    }
}
