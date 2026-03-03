<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\ConversationService;

try {
    $conversationService = new ConversationService($db);

    $status = $_GET['status'] ?? null;
    $conversations = $conversationService->getAllConversations($status);

    ob_clean();
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);

} catch (\Exception $e) {
    $logger->error('Get Conversations Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener conversaciones'
    ]);
}
