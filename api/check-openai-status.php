<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

try {
    $openaiStatus = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'openai_status'",
        []
    );
    
    $status = $openaiStatus['setting_value'] ?? 'active';
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'status' => $status,
        'can_enable_ai' => $status !== 'insufficient_funds'
    ]);

} catch (\Throwable $e) {
    if (isset($logger)) {
        $logger->error('Check OpenAI Status Error: ' . $e->getMessage());
    }
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar estado de OpenAI'
    ]);
}
