<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Core\Config;
use App\Services\ConversationService;
use App\Services\WhatsAppService;
use App\Services\EncryptionService;
use App\Services\CredentialService;

try {
    $id = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? null;

    if (!$id || !$message) {
        throw new \InvalidArgumentException('Conversation ID and message required');
    }

    $conversationService = new ConversationService($db);
    
    $result = $db->fetchOne('SELECT * FROM conversations WHERE id = :id', [':id' => $id]);
    
    if (!$result) {
        throw new \RuntimeException('Conversation not found');
    }

    try {
        $encryption = new EncryptionService();
        $credentialService = new CredentialService($db, $encryption);
        if ($credentialService->hasWhatsAppCredentials()) {
            $waCreds = $credentialService->getWhatsAppCredentials();
            $whatsapp = new WhatsAppService(
                $waCreds['access_token'],
                $waCreds['phone_number_id'],
                Config::get('whatsapp.api_version'),
                $logger
            );
        } else {
            throw new \Exception('No DB credentials');
        }
    } catch (\Exception $credEx) {
        $whatsapp = new WhatsAppService(
            Config::get('whatsapp.access_token'),
            Config::get('whatsapp.phone_number_id'),
            Config::get('whatsapp.api_version'),
            $logger
        );
    }

    $whatsapp->sendMessage($result['phone_number'], $message);

    $conversationService->addMessage($id, 'human', $message);

    $conversationService->updateConversationStatus($id, 'active');
    
    $db->query(
        'UPDATE conversations SET last_message_at = NOW() WHERE id = :id',
        [':id' => $id]
    );

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Reply sent successfully'
    ]);

} catch (\Exception $e) {
    $logger->error('Reply Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al enviar respuesta'
    ]);
}
