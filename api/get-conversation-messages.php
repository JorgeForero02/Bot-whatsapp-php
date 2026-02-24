<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\ConversationService;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

try {
    $conversationId = $_GET['id'] ?? null;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    if (!$conversationId) {
        throw new \InvalidArgumentException('Conversation ID is required');
    }

    $conversationService = new ConversationService($db);
    
    $messages = $db->fetchAll(
        "SELECT id, sender_type, message_text, audio_url, media_type, context_used, confidence_score, created_at 
         FROM messages 
         WHERE conversation_id = :conversation_id 
         ORDER BY created_at DESC 
         LIMIT :limit OFFSET :offset",
        [
            ':conversation_id' => $conversationId,
            ':limit' => $limit,
            ':offset' => $offset
        ]
    );

    $messages = array_reverse($messages);

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'has_more' => count($messages) === $limit
    ]);

} catch (\Exception $e) {
    $logger->error('Get Conversation Messages Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
