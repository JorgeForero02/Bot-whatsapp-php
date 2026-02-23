<?php

header('Content-Type: application/json');

use App\Core\Config;
use App\Services\DocumentService;

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new \InvalidArgumentException('Document ID required');
    }

    $documentService = new DocumentService(
        $db,
        Config::get('uploads.path'),
        Config::get('uploads.allowed_types'),
        Config::get('uploads.max_size')
    );

    $documentService->deleteDocument($id);

    echo json_encode([
        'success' => true,
        'message' => 'Document deleted successfully'
    ]);

} catch (\Exception $e) {
    $logger->error('Delete Document Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
