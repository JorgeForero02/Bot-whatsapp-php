<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\EncryptionService;
use App\Services\CredentialService;

try {
    $encryption = new EncryptionService();
    $credentialService = new CredentialService($db, $encryption);

    $whatsapp = $credentialService->getWhatsAppCredentials();
    $openai = $credentialService->getOpenAICredentials();
    $google = $credentialService->getGoogleOAuthCredentials();

    $mask = function($value) {
        return !empty($value) ? '••••••••' : '';
    };

    ob_clean();
    echo json_encode([
        'success' => true,
        'whatsapp' => [
            'phone_number_id' => $whatsapp['phone_number_id'],
            'access_token'    => $mask($whatsapp['access_token']),
            'app_secret'      => $mask($whatsapp['app_secret']),
            'verify_token'    => $whatsapp['verify_token'],
            'has_credentials' => $credentialService->hasWhatsAppCredentials(),
        ],
        'openai' => [
            'api_key'         => $mask($openai['api_key']),
            'model'           => $openai['model'],
            'embedding_model' => $openai['embedding_model'],
            'has_credentials' => $credentialService->hasOpenAICredentials(),
        ],
        'google' => [
            'client_id'       => $google['client_id'],
            'client_secret'   => $mask($google['client_secret']),
            'access_token'    => $mask($google['access_token']),
            'refresh_token'   => $mask($google['refresh_token']),
            'calendar_id'     => $google['calendar_id'],
            'has_credentials' => $credentialService->hasGoogleOAuthCredentials(),
        ]
        // Removed: business_account_id, app_secret, temperature, max_tokens, timeout, redirect_uri
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Get Credentials Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener credenciales'
    ]);
}
