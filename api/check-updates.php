<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

try {
    $lastCheck = $_GET['last_check'] ?? null;
    $conversationId = $_GET['conversation_id'] ?? null;

    $serverTime = date('Y-m-d H:i:s');

    if (!$lastCheck) {
        ob_clean();
        echo json_encode([
            'success'    => true,
            'has_update' => false,
            'has_updates' => false,
            'server_time' => $serverTime
        ]);
        exit;
    }

    if ($conversationId) {
        $newMsg = $db->fetchOne(
            'SELECT id FROM messages WHERE conversation_id = :conv_id AND created_at > :last_check LIMIT 1',
            [':conv_id' => $conversationId, ':last_check' => $lastCheck]
        );

        $hasUpdate = !empty($newMsg);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'has_update' => $hasUpdate,
            'conversation_id' => $conversationId,
            'server_time' => $serverTime
        ]);
    } else {
        $updatedConversations = $db->fetchAll(
            'SELECT DISTINCT conversation_id as id FROM messages WHERE created_at > :last_check',
            [':last_check' => $lastCheck]
        );

        ob_clean();
        echo json_encode([
            'success' => true,
            'has_updates' => count($updatedConversations) > 0,
            'updated_conversations' => array_column($updatedConversations, 'id'),
            'server_time' => $serverTime
        ]);
    }

} catch (\Throwable $e) {
    $logger->error('Check Updates Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar actualizaciones'
    ]);
}
