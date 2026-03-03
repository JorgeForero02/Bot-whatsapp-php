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
            } catch (\Exception $e) {
                $result = [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Error de conexión con WhatsApp API'
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
            } catch (\Exception $e) {
                $statusMsg = 'Error de conexión con OpenAI';
                if (strpos($e->getMessage(), '401') !== false) {
                    $statusMsg = 'API Key inválida';
                } elseif (strpos($e->getMessage(), 'INSUFFICIENT_FUNDS') !== false || strpos($e->getMessage(), '429') !== false) {
                    $statusMsg = 'Sin fondos o límite de rate excedido';
                }
                $result = [
                    'success' => false,
                    'status' => 'error',
                    'message' => $statusMsg
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
            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 10]);
            try {
                $response = $client->get(
                    'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($creds['calendar_id']),
                    ['headers' => ['Authorization' => 'Bearer ' . $creds['access_token']]]
                );
                $data = json_decode($response->getBody(), true);
                $result = [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Conexión exitosa. Calendario: ' . ($data['summary'] ?? $creds['calendar_id'])
                ];
            } catch (\Exception $e) {
                $statusMsg = 'Error de conexión con Google Calendar';
                if (strpos($e->getMessage(), '401') !== false) {
                    $statusMsg = 'Token expirado o inválido. Reconecta con Google.';
                }
                $result = [
                    'success' => false,
                    'status' => 'error',
                    'message' => $statusMsg
                ];
                $logger->error('Google Calendar test connection error: ' . $e->getMessage());
            }
            break;
    }

    ob_clean();
    echo json_encode($result);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Test Connection Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al probar conexión'
    ]);
}
