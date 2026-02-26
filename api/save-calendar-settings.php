<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Core\Logger;
use App\Helpers\TimeValidator;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $config = Config::load(__DIR__ . '/../config/config.php');
    $db = Database::getInstance(Config::get('database'));
    $logger = new Logger(__DIR__ . '/../logs');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    $errors = [];
    
    if (isset($data['default_duration_minutes'])) {
        $duration = (int)$data['default_duration_minutes'];
        if ($duration <= 0) {
            $errors[] = 'La duración debe ser mayor a 0 minutos';
        }
    }
    
    if (isset($data['max_events_per_day'])) {
        $maxEvents = (int)$data['max_events_per_day'];
        if ($maxEvents <= 0) {
            $errors[] = 'El máximo de eventos debe ser mayor a 0';
        }
    }
    
    if (isset($data['min_advance_hours'])) {
        $minAdvance = (int)$data['min_advance_hours'];
        if ($minAdvance < 0) {
            $errors[] = 'La anticipación mínima no puede ser negativa';
        }
    }
    
    $atLeastOneDayOpen = false;
    if (isset($data['business_hours'])) {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            if (isset($data['business_hours'][$day])) {
                $dayData = $data['business_hours'][$day];
                
                if ($dayData['enabled']) {
                    $atLeastOneDayOpen = true;
                    
                    if (!TimeValidator::validateFormat($dayData['start'])) {
                        $errors[] = ucfirst($day) . ': formato de hora inicio inválido (debe ser HH:MM)';
                    }
                    
                    if (!TimeValidator::validateFormat($dayData['end'])) {
                        $errors[] = ucfirst($day) . ': formato de hora fin inválido (debe ser HH:MM)';
                    }
                    
                    if (empty($errors) && !TimeValidator::isValidTimeRange($dayData['start'], $dayData['end'])) {
                        $errors[] = ucfirst($day) . ': la hora de fin debe ser posterior a la hora de inicio';
                    }
                }
            }
        }
        
        if (!$atLeastOneDayOpen) {
            $errors[] = 'Debe haber al menos un día de la semana abierto';
        }
    }
    
    $validTimezones = \DateTimeZone::listIdentifiers();
    if (isset($data['timezone']) && !in_array($data['timezone'], $validTimezones)) {
        $errors[] = 'Zona horaria inválida';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['error' => implode(', ', $errors)]);
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        if (isset($data['timezone'])) {
            $db->query(
                "INSERT INTO calendar_settings (setting_key, setting_value) VALUES (:key, :value) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [':key' => 'timezone', ':value' => $data['timezone']]
            );
        }
        
        if (isset($data['default_duration_minutes'])) {
            $db->query(
                "INSERT INTO calendar_settings (setting_key, setting_value) VALUES (:key, :value) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [':key' => 'default_duration_minutes', ':value' => (string)$data['default_duration_minutes']]
            );
        }
        
        if (isset($data['max_events_per_day'])) {
            $db->query(
                "INSERT INTO calendar_settings (setting_key, setting_value) VALUES (:key, :value) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [':key' => 'max_events_per_day', ':value' => (string)$data['max_events_per_day']]
            );
        }
        
        if (isset($data['min_advance_hours'])) {
            $db->query(
                "INSERT INTO calendar_settings (setting_key, setting_value) VALUES (:key, :value) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [':key' => 'min_advance_hours', ':value' => (string)$data['min_advance_hours']]
            );
        }
        
        if (isset($data['business_hours'])) {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($days as $day) {
                if (isset($data['business_hours'][$day])) {
                    $db->query(
                        "INSERT INTO calendar_settings (setting_key, setting_value) VALUES (:key, :value) 
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                        [
                            ':key' => 'business_hours_' . $day,
                            ':value' => json_encode($data['business_hours'][$day])
                        ]
                    );
                }
            }
        }
        
        $db->commit();
        
        $logger->info('Calendar settings updated', ['data' => $data]);
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente']);
        
    } catch (\Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    ob_clean();
    echo json_encode(['error' => 'Error al guardar configuración: ' . $e->getMessage()]);
}
