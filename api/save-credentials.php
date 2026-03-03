<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\EncryptionService;
use App\Services\CredentialService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['service'])) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['error' => 'Invalid input. "service" field required.']);
        exit;
    }

    $encryption = new EncryptionService();
    $credentialService = new CredentialService($db, $encryption);
    $service = $input['service'];

    switch ($service) {
        case 'whatsapp':
            $credentialService->saveWhatsAppCredentials([
                'phone_number_id' => $input['phone_number_id'] ?? null,
                'access_token'    => $input['access_token'] ?? null,
                'app_secret'      => $input['app_secret'] ?? null,
                'verify_token'    => $input['verify_token'] ?? null,
            ]);
            break;

        case 'openai':
            $credentialService->saveOpenAICredentials([
                'api_key'         => $input['api_key'] ?? null,
                'model'           => $input['model'] ?? null,
                'embedding_model' => $input['embedding_model'] ?? null,
            ]);
            break;

        case 'google':
            $credentialService->saveGoogleOAuthCredentials([
                'client_id'     => $input['client_id'] ?? null,
                'client_secret' => $input['client_secret'] ?? null,
                'access_token'  => $input['access_token'] ?? null,
                'refresh_token' => $input['refresh_token'] ?? null,
                'calendar_id'   => $input['calendar_id'] ?? null,
            ]);
            break;

        default:
            http_response_code(400);
            ob_clean();
            echo json_encode(['error' => 'Invalid service. Use: whatsapp, openai, or google']);
            exit;
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Credenciales guardadas exitosamente'
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Save Credentials Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar credenciales'
    ]);
}
