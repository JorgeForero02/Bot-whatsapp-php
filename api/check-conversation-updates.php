<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

try {
    $lastCheck = $_GET['last_check'] ?? null;

    if (!$lastCheck) {
        throw new \InvalidArgumentException('last_check timestamp is required');
    }

    $updatedConversations = $db->fetchAll(
        'SELECT id, last_message_at, status FROM conversations 
         WHERE last_message_at > :last_check1 OR updated_at > :last_check2
         ORDER BY last_message_at DESC',
        [':last_check1' => $lastCheck, ':last_check2' => $lastCheck]
    );

    ob_clean();
    echo json_encode([
        'success' => true,
        'has_updates' => count($updatedConversations) > 0,
        'updated_conversations' => $updatedConversations
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Check Conversation Updates Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar actualizaciones'
    ]);
}
