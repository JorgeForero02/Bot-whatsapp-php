<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Core\Config;
use App\Services\ConversationService;
use App\Services\DocumentService;
use App\Services\VectorSearchService;

try {
    $conversationService = new ConversationService($db);
    $documentService = new DocumentService(
        $db,
        Config::get('uploads.path'),
        Config::get('uploads.allowed_types'),
        Config::get('uploads.max_size')
    );
    $vectorSearch = new VectorSearchService($db, Config::get('rag.similarity_method'));

    $stats = [
        'conversations' => $conversationService->getConversationStats(),
        'documents' => $documentService->getDocumentStats(),
        'vectors' => $vectorSearch->countVectors()
    ];

    ob_clean();
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (\Throwable $e) {
    $logger->error('Get Stats Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas'
    ]);
}
