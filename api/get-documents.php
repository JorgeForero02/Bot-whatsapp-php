<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Core\Config;
use App\Services\DocumentService;

try {
    $documentService = new DocumentService(
        $db,
        Config::get('uploads.path'),
        Config::get('uploads.allowed_types'),
        Config::get('uploads.max_size')
    );

    $documents = $documentService->getAllDocuments();

    ob_clean();
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);

} catch (\Throwable $e) {
    $logger->error('Get Documents Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener documentos'
    ]);
}
