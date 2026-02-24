<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\DocumentService;
use App\Services\OpenAIService;
use App\Services\VectorSearchService;
use App\Services\RAGService;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

try {
    if (!isset($_FILES['document'])) {
        throw new \RuntimeException('No file uploaded');
    }

    $documentService = new DocumentService(
        $db,
        Config::get('uploads.path'),
        Config::get('uploads.allowed_types'),
        Config::get('uploads.max_size')
    );

    $document = $documentService->uploadDocument($_FILES['document']);

    $openai = new OpenAIService(
        Config::get('openai.api_key'),
        Config::get('openai.model'),
        Config::get('openai.embedding_model'),
        $logger
    );

    $vectorSearch = new VectorSearchService($db, Config::get('rag.similarity_method'));

    $rag = new RAGService(
        $openai,
        $vectorSearch,
        $logger,
        Config::get('rag.top_k_results'),
        Config::get('rag.similarity_threshold')
    );

    $chunksIndexed = $rag->indexDocument(
        $document['id'],
        $document['text'],
        Config::get('rag.chunk_size'),
        Config::get('rag.chunk_overlap')
    );

    $documentService->updateChunkCount($document['id'], $chunksIndexed);

    echo json_encode([
        'success' => true,
        'document' => [
            'id' => $document['id'],
            'filename' => $document['original_name'],
            'chunks' => $chunksIndexed
        ]
    ]);

} catch (\Exception $e) {
    $logger->error('Upload Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
