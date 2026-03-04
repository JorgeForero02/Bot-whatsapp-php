<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

try {
    $settings = $db->fetchAll(
        "SELECT setting_key, setting_value FROM settings",
        []
    );

    $response = [
        'systemPrompt' => 'Eres un asistente virtual útil y amigable.',
        'welcomeMessage' => 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?',
        'errorMessage' => 'Lo siento, no encontré información relevante. Un operador te atenderá pronto.',
        'contextMessagesCount' => 5,
        'calendarEnabled' => false,
        'botMode' => 'ai',
        'botName' => 'WhatsApp Bot'
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
            case 'context_messages_count':
                $response['contextMessagesCount'] = intval($setting['setting_value']);
                break;
            case 'calendar_enabled':
                $response['calendarEnabled'] = ($setting['setting_value'] === 'true' || $setting['setting_value'] === '1');
                break;
            case 'bot_mode':
                $response['botMode'] = $setting['setting_value'];
                break;
            case 'bot_name':
                $response['botName'] = $setting['setting_value'];
                break;
        }
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'settings' => $response
    ]);

} catch (\Throwable $e) {
    if (isset($logger)) {
        $logger->error('Get Settings Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener configuración'
    ]);
}
