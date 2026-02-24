<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\ConversationService;
use App\Services\DocumentService;
use App\Services\VectorSearchService;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

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

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (\Exception $e) {
    $logger->error('Get Stats Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
