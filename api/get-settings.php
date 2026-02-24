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
    $settings = $db->fetchAll(
        "SELECT setting_key, setting_value FROM settings",
        []
    );

    $response = [
        'systemPrompt' => 'Eres un asistente virtual útil y amigable.',
        'welcomeMessage' => 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?',
        'errorMessage' => 'Lo siento, no encontré información relevante. Un operador te atenderá pronto.',
        'confidenceThreshold' => 0.7,
        'maxResults' => 5,
        'chunkSize' => 1000,
        'autoReply' => true,
        'openaiModel' => 'gpt-4',
        'temperature' => 0.7,
        'timeout' => 30,
        'contextMessagesCount' => 5
    ];

    foreach ($settings as $setting) {
        switch ($setting['setting_key']) {
            case 'system_prompt':
                $response['systemPrompt'] = $setting['setting_value'];
                break;
            case 'bot_greeting':
                $response['welcomeMessage'] = $setting['setting_value'];
                break;
            case 'bot_fallback_message':
                $response['errorMessage'] = $setting['setting_value'];
                break;
            case 'confidence_threshold':
                $response['confidenceThreshold'] = floatval($setting['setting_value']);
                break;
            case 'max_results':
                $response['maxResults'] = intval($setting['setting_value']);
                break;
            case 'chunk_size':
                $response['chunkSize'] = intval($setting['setting_value']);
                break;
            case 'auto_reply':
                $response['autoReply'] = $setting['setting_value'] === 'true';
                break;
            case 'openai_model':
                $response['openaiModel'] = $setting['setting_value'];
                break;
            case 'temperature':
                $response['temperature'] = floatval($setting['setting_value']);
                break;
            case 'timeout':
                $response['timeout'] = intval($setting['setting_value']);
                break;
            case 'context_messages_count':
                $response['contextMessagesCount'] = intval($setting['setting_value']);
                break;
        }
    }

    echo json_encode([
        'success' => true,
        'settings' => $response
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Get Settings Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
