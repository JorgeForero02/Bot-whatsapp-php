<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\ClassicBotService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['message'])) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Campo "message" requerido']);
        exit;
    }

    $simulationPhone = 'SIM_' . md5(session_id() ?: 'preview');

    if (!empty($input['reset'])) {
        $db->query(
            "DELETE FROM classic_flow_sessions WHERE user_phone = :phone",
            [':phone' => $simulationPhone]
        );
        ob_clean();
        echo json_encode(['success' => true, 'reset' => true]);
        exit;
    }

    $service = new ClassicBotService($db, $logger);
    $result  = $service->processMessage($input['message'], $simulationPhone);

    ob_clean();
    echo json_encode([
        'success'  => true,
        'type'     => $result['type'],
        'response' => $result['response'],
    ]);

} catch (\Exception $e) {
    $logger->error('simulate-flow error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error en simulación']);
}
