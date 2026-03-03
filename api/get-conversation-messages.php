<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\ConversationService;

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
         LIMIT {$limit} OFFSET {$offset}",
        [':conversation_id' => $conversationId]
    );

    $messages = array_reverse($messages);

    ob_clean();
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'has_more' => count($messages) === $limit
    ]);

} catch (\Exception $e) {
    $logger->error('Get Conversation Messages Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener mensajes'
    ]);
}
