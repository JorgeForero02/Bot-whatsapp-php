<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\OpenAIService;
use App\Services\EncryptionService;
use App\Services\CredentialService;
use App\Utils\VectorMath;

$config = Config::load(__DIR__ . '/../config/config.php');
$logger = new Logger(__DIR__ . '/../logs');
$db = Database::getInstance(Config::get('database'));

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
} catch (\Exception $e) {
    $openai = new OpenAIService(
        Config::get('openai.api_key'),
        Config::get('openai.model'),
        Config::get('openai.embedding_model'),
        $logger
    );
}

$logger->info('Embedding worker started');

while (true) {
    try {
        $pendingChunks = $db->fetchAll(
            'SELECT v.id, v.chunk_text, v.document_id 
             FROM vectors v 
             WHERE v.embedding IS NULL OR v.embedding = ""
             LIMIT 10'
        );
        
        if (empty($pendingChunks)) {
            $logger->info('No pending chunks, sleeping...');
            sleep(10);
            continue;
        }
        
        $processedDocuments = [];
        
        foreach ($pendingChunks as $chunk) {
            try {
                $logger->info('Processing chunk', ['chunk_id' => $chunk['id']]);
                
                $embedding = $openai->createEmbedding($chunk['chunk_text']);
                $embeddingBinary = VectorMath::serializeVector($embedding);
                
                $db->query(
                    'UPDATE vectors SET embedding = :embedding WHERE id = :id',
                    [
                        ':embedding' => $embeddingBinary,
                        ':id' => $chunk['id']
                    ]
                );
                
                $logger->info('Chunk processed successfully', ['chunk_id' => $chunk['id']]);
                
                $processedDocuments[$chunk['document_id']] = true;
                
                sleep(1);
                
            } catch (\Exception $e) {
                $logger->error('Error processing chunk', [
                    'chunk_id' => $chunk['id'],
                    'error' => $e->getMessage()
                ]);
                
                if (strpos($e->getMessage(), 'INSUFFICIENT_FUNDS') !== false) {
                    $logger->error('Insufficient funds detected, stopping worker');
                    exit(1);
                }
                
                sleep(5);
            }
        }
        
        foreach (array_keys($processedDocuments) as $documentId) {
            try {
                $count = $db->fetchOne(
                    'SELECT COUNT(*) as total FROM vectors WHERE document_id = :id AND embedding IS NOT NULL',
                    [':id' => $documentId]
                );
                
                $db->query(
                    'UPDATE documents SET chunk_count = :count WHERE id = :id',
                    [':count' => $count['total'], ':id' => $documentId]
                );
            } catch (\Exception $e) {
                $logger->error('Error updating chunk_count for document ' . $documentId . ': ' . $e->getMessage());
            }
        }
        
    } catch (\Exception $e) {
        $logger->error('Worker error: ' . $e->getMessage());
        sleep(10);
    }
}
