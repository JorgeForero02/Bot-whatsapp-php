<?php

header('Content-Type: application/json');

use App\Services\ConversationService;

try {
    $conversationService = new ConversationService($db);

    $status = $_GET['status'] ?? null;
    $conversations = $conversationService->getAllConversations($status);

    foreach ($conversations as &$conversation) {
        $messages = $conversationService->getConversationHistory($conversation['id'], 10);
        $conversation['recent_messages'] = array_reverse($messages);
    }

    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);

} catch (\Exception $e) {
    $logger->error('Get Conversations Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
