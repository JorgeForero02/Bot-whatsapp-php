<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\OpenAIService;

$config = Config::load(__DIR__ . '/../config/config.php');
$logger = new Logger(__DIR__ . '/../logs');
$db = Database::getInstance(Config::get('database'));

$openai = new OpenAIService(
    Config::get('openai.api_key'),
    Config::get('openai.model'),
    Config::get('openai.embedding_model'),
    $logger
);

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
        
        foreach ($pendingChunks as $chunk) {
            try {
                $logger->info('Processing chunk', ['chunk_id' => $chunk['id']]);
                
                $embedding = $openai->createEmbedding($chunk['chunk_text']);
                $embeddingJson = json_encode($embedding);
                
                $db->query(
                    'UPDATE vectors SET embedding = :embedding WHERE id = :id',
                    [
                        ':embedding' => $embeddingJson,
                        ':id' => $chunk['id']
                    ]
                );
                
                $logger->info('Chunk processed successfully', ['chunk_id' => $chunk['id']]);
                
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
        
    } catch (\Exception $e) {
        $logger->error('Worker error: ' . $e->getMessage());
        sleep(10);
    }
}
