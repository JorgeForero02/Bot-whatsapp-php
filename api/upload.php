<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Core\Config;
use App\Services\DocumentService;
use App\Services\OpenAIService;
use App\Services\EncryptionService;
use App\Services\CredentialService;
use App\Services\VectorSearchService;
use App\Services\RAGService;

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

    try {
        $encryption = new EncryptionService();
        $credentialService = new CredentialService($db, $encryption);
        if ($credentialService->hasOpenAICredentials()) {
            $oaiCreds = $credentialService->getOpenAICredentials();
            $openai = new OpenAIService(
                $oaiCreds['api_key'],
                $oaiCreds['model'],
                $oaiCreds['embedding_model'],
                $logger
            );
        } else {
            throw new \Exception('No DB credentials');
        }
    } catch (\Exception $credEx) {
        $openai = new OpenAIService(
            Config::get('openai.api_key'),
            Config::get('openai.model'),
            Config::get('openai.embedding_model'),
            $logger
        );
    }

    $vectorSearch = new VectorSearchService($db, Config::get('rag.similarity_method'));

    $rag = new RAGService(
        $openai,
        $vectorSearch,
        $logger,
        Config::get('rag.top_k_results'),
        Config::get('rag.similarity_threshold'),
        $db
    );

    $chunksIndexed = $rag->indexDocument(
        $document['id'],
        $document['text'],
        Config::get('rag.chunk_size'),
        Config::get('rag.chunk_overlap')
    );

    $documentService->updateChunkCount($document['id'], $chunksIndexed);

    ob_clean();
    echo json_encode([
        'success' => true,
        'document' => [
            'id' => $document['id'],
            'filename' => $document['original_name'],
            'chunks' => $chunksIndexed
        ]
    ]);

} catch (\Throwable $e) {
    $logger->error('Upload Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al subir documento'
    ]);
}
