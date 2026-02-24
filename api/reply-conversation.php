<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\ConversationService;
use App\Services\WhatsAppService;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

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

    $whatsapp = new WhatsAppService(
        Config::get('whatsapp.access_token'),
        Config::get('whatsapp.phone_number_id'),
        Config::get('whatsapp.api_version'),
        $logger
    );

    $whatsapp->sendMessage($result['phone_number'], $message);

    $conversationService->addMessage($id, 'human', $message);

    $conversationService->updateConversationStatus($id, 'active');
    
    $db->query(
        'UPDATE conversations SET last_message_at = NOW() WHERE id = :id',
        [':id' => $id]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Reply sent successfully'
    ]);

} catch (\Exception $e) {
    $logger->error('Reply Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
