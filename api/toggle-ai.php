<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

try {
    $id = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    $aiEnabled = $input['ai_enabled'] ?? null;

    if (!$id || $aiEnabled === null) {
        throw new \InvalidArgumentException('Conversation ID and ai_enabled state required');
    }

    if ($aiEnabled) {
        $openaiStatus = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'openai_status'",
            []
        );
        
        if ($openaiStatus && $openaiStatus['setting_value'] === 'insufficient_funds') {
            http_response_code(402);
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'INSUFFICIENT_FUNDS',
                'message' => 'No se puede activar la IA. Fondos insuficientes en OpenAI.'
            ]);
            exit;
        }
    }

    $db->query(
        'UPDATE conversations SET ai_enabled = :ai_enabled WHERE id = :id',
        [
            ':ai_enabled' => $aiEnabled ? 1 : 0,
            ':id' => $id
        ]
    );

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'AI state updated successfully'
    ]);

} catch (\Throwable $e) {
    $logger->error('Toggle AI Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al cambiar estado de IA'
    ]);
}
