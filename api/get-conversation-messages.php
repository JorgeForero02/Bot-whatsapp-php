<?php

header('Content-Type: application/json');

use App\Services\ConversationService;

try {
    $conversationId = $_GET['id'] ?? null;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    if (!$conversationId) {
        throw new \InvalidArgumentException('Conversation ID is required');
    }

    $conversationService = new ConversationService($db);
    
    // Obtener mensajes con offset para paginación
    $messages = $db->fetchAll(
        'SELECT * FROM messages 
         WHERE conversation_id = :conversation_id 
         ORDER BY created_at DESC 
         LIMIT :limit OFFSET :offset',
        [
            ':conversation_id' => $conversationId,
            ':limit' => $limit,
            ':offset' => $offset
        ]
    );

    // Invertir para mostrar en orden cronológico
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
