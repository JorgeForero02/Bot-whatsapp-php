<?php

header('Content-Type: application/json');

use App\Core\Database;

try {
    $id = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    $aiEnabled = $input['ai_enabled'] ?? null;

    if (!$id || $aiEnabled === null) {
        throw new \InvalidArgumentException('Conversation ID and ai_enabled state required');
    }

    $db->query(
        'UPDATE conversations SET ai_enabled = :ai_enabled WHERE id = :id',
        [
            ':ai_enabled' => $aiEnabled ? 1 : 0,
            ':id' => $id
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'AI state updated successfully'
    ]);

} catch (\Exception $e) {
    $logger->error('Toggle AI Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
