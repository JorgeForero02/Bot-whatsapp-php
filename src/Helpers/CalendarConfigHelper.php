<?php

namespace App\Helpers;

use App\Core\Database;
use App\Core\Logger;

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
                    
                    case 'reminder_email_enabled':
                        $defaults['reminders']['email']['enabled'] = ($value === '1' || $value === 'true');
                        break;
                    
                    case 'reminder_email_minutes':
                        $defaults['reminders']['email']['minutes_before'] = (int)$value;
                        break;
                    
                    case 'reminder_popup_enabled':
                        $defaults['reminders']['popup']['enabled'] = ($value === '1' || $value === 'true');
                        break;
                    
                    case 'reminder_popup_minutes':
                        $defaults['reminders']['popup']['minutes_before'] = (int)$value;
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
            $logger = new Logger(__DIR__ . '/../../logs');
            $logger->error('CalendarConfigHelper: Failed to load settings from database', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $defaults;
    }
}
