<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\FlowBuilderService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        http_response_code(405);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $nodeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$nodeId) {
        http_response_code(400);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'ID de nodo requerido']);
        exit;
    }

    $service = new FlowBuilderService($db, $logger);
    $service->deleteNode($nodeId);

    ob_clean();
    echo json_encode(['success' => true]);

} catch (\Exception $e) {
    $logger->error('delete-flow error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el nodo']);
}
