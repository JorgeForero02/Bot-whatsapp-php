<?php

header('Content-Type: application/json');

use App\Core\Database;

try {
    $lastCheck = $_GET['last_check'] ?? null;
    $conversationId = $_GET['conversation_id'] ?? null;

    if (!$lastCheck) {
        throw new \InvalidArgumentException('last_check timestamp is required');
    }

    // Verificar si hay actualizaciones para una conversación específica
    if ($conversationId) {
        $conversation = $db->fetchOne(
            'SELECT id, last_bot_message_at FROM conversations WHERE id = :id',
            [':id' => $conversationId]
        );
        
        $hasUpdate = false;
        if ($conversation && $conversation['last_bot_message_at']) {
            $lastBotMessage = strtotime($conversation['last_bot_message_at']);
            $lastCheckTime = strtotime($lastCheck);
            $hasUpdate = $lastBotMessage > $lastCheckTime;
        }
        
        echo json_encode([
            'success' => true,
            'has_update' => $hasUpdate,
            'conversation_id' => $conversationId
        ]);
    } else {
        // Verificar si hay actualizaciones en cualquier conversación
        $updatedConversations = $db->fetchAll(
            'SELECT id FROM conversations WHERE last_bot_message_at > :last_check',
            [':last_check' => $lastCheck]
        );
        
        echo json_encode([
            'success' => true,
            'has_updates' => count($updatedConversations) > 0,
            'updated_conversations' => array_column($updatedConversations, 'id')
        ]);
    }

} catch (\Exception $e) {
    $logger->error('Check Updates Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
