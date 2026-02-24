<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new \Exception('Invalid JSON input');
    }

    $settingsMap = [
        'systemPrompt' => 'system_prompt',
        'welcomeMessage' => 'bot_greeting',
        'errorMessage' => 'bot_fallback_message',
        'confidenceThreshold' => 'confidence_threshold',
        'maxResults' => 'max_results',
        'chunkSize' => 'chunk_size',
        'autoReply' => 'auto_reply',
        'openaiModel' => 'openai_model',
        'temperature' => 'temperature',
        'timeout' => 'timeout',
        'contextMessagesCount' => 'context_messages_count'
    ];

    foreach ($settingsMap as $jsonKey => $dbKey) {
        if (isset($input[$jsonKey])) {
            $value = $input[$jsonKey];
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [':key' => $dbKey, ':value' => (string)$value]
            );
        }
    }

    $logger->info('Settings saved', ['user' => 'admin']);

    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada correctamente'
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Save Settings Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
