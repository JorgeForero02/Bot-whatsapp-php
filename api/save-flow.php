<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\FlowBuilderService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }

    $service = new FlowBuilderService($db, $logger);

    if (!empty($input['_import'])) {
        if (empty($input['json'])) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Campo "json" requerido para importación']);
            exit;
        }
        $summary = $service->importFromJson($input['json']);
        ob_clean();
        echo json_encode(['success' => true, 'imported_nodes' => $summary['imported_nodes']]);
        exit;
    }

    $nodeId = $service->saveNode($input);
    ob_clean();
    echo json_encode(['success' => true, 'node_id' => $nodeId]);

} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    $logger->error('save-flow error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al guardar el nodo']);
}
