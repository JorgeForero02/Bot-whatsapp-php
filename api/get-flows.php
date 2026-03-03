<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\FlowBuilderService;

try {
    $service = new FlowBuilderService($db, $logger);
    $nodes   = $service->getFlowTree();

    ob_clean();
    echo json_encode(['success' => true, 'nodes' => $nodes]);

} catch (\Exception $e) {
    $logger->error('get-flows error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al obtener flujos']);
}
