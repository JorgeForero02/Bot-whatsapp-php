<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Core\Config;
use App\Services\EncryptionService;
use App\Services\CredentialService;

try {
    $service = $_GET['service'] ?? '';
    
    if (!in_array($service, ['whatsapp', 'openai', 'google'])) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['error' => 'Invalid service. Use: whatsapp, openai, or google']);
        exit;
    }

    $encryption = new EncryptionService();
    $credentialService = new CredentialService($db, $encryption);

    $result = ['success' => false, 'status' => 'not_configured', 'message' => ''];

    switch ($service) {
        case 'whatsapp':
            if (!$credentialService->hasWhatsAppCredentials()) {
                $result['message'] = 'Credenciales de WhatsApp no configuradas';
                break;
            }
            $creds = $credentialService->getWhatsAppCredentials();
            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 10]);
            try {
                $response = $client->get(
                    'https://graph.facebook.com/' . Config::get('whatsapp.api_version') . '/' . $creds['phone_number_id'],
                    ['headers' => ['Authorization' => 'Bearer ' . $creds['access_token']]]
                );
                $data = json_decode($response->getBody(), true);
                $result = [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Conexión exitosa. Phone Number ID: ' . ($data['id'] ?? $creds['phone_number_id'])
                ];
            } catch (\Throwable $e) {
                $result = [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Error de conexión con WhatsApp API: ' . $e->getMessage()
                ];
                $logger->error('WhatsApp test connection error: ' . $e->getMessage());
            }
            break;

        case 'openai':
            if (!$credentialService->hasOpenAICredentials()) {
                $result['message'] = 'Credenciales de OpenAI no configuradas';
                break;
            }
            $creds = $credentialService->getOpenAICredentials();
            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 10]);
            try {
                $response = $client->get('https://api.openai.com/v1/models', [
                    'headers' => ['Authorization' => 'Bearer ' . $creds['api_key']]
                ]);
                $result = [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Conexión exitosa con OpenAI. Modelo configurado: ' . $creds['model']
                ];
            } catch (\Throwable $e) {
                $statusMsg = 'Error de conexión con OpenAI';
                if (strpos($e->getMessage(), '401') !== false) {
                    $statusMsg = 'API Key inválida';
                } elseif (strpos($e->getMessage(), 'INSUFFICIENT_FUNDS') !== false || strpos($e->getMessage(), '429') !== false) {
                    $statusMsg = 'Sin fondos o límite de rate excedido';
                }
                $result = [
                    'success' => false,
                    'status' => 'error',
                    'message' => $statusMsg . ' — ' . $e->getMessage()
                ];
                $logger->error('OpenAI test connection error: ' . $e->getMessage());
            }
            break;

        case 'google':
            if (!$credentialService->hasGoogleOAuthCredentials()) {
                $result['message'] = 'Credenciales de Google Calendar no configuradas';
                break;
            }
            $creds = $credentialService->getGoogleOAuthCredentials();
            try {
                $calService = new \App\Services\GoogleCalendarService(
                    $creds['access_token'],
                    $creds['calendar_id'] ?: 'primary',
                    $logger,
                    'America/Bogota',
                    $creds['refresh_token'],
                    $creds['client_id'],
                    $creds['client_secret'],
                    $db
                );
                $events = $calService->listUpcomingEvents(1);
                $result = [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Conexión exitosa con Google Calendar.'
                ];
            } catch (\Throwable $e) {
                $statusMsg = 'Error de conexión con Google Calendar';
                if (strpos($e->getMessage(), '401') !== false) {
                    $statusMsg = 'Token expirado o inválido. Verifica tus credenciales de Google.';
                } elseif (strpos($e->getMessage(), '403') !== false) {
                    $statusMsg = 'Acceso denegado. Verifica los permisos del calendario.';
                } elseif (strpos($e->getMessage(), '404') !== false) {
                    $statusMsg = 'Calendario no encontrado. Verifica el Calendar ID.';
                }
                $result = [
                    'success' => false,
                    'status' => 'error',
                    'message' => $statusMsg . ' — ' . $e->getMessage()
                ];
                $logger->error('Google Calendar test connection error: ' . $e->getMessage());
            }
            break;
    }

    ob_clean();
    echo json_encode($result);

} catch (\Throwable $e) {
    if (isset($logger)) {
        $logger->error('Test Connection Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
