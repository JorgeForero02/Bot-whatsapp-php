<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class RAGService
{
    private $openai;
    private $vectorSearch;
    private $logger;
    private $db;
    private $topK;
    private $threshold;

    public function __construct(
        OpenAIService $openai,
        VectorSearchService $vectorSearch,
        Logger $logger,
        $topK = 3,
        $threshold = 0.7,
        Database $db = null
    ) {
        $this->openai = $openai;
        $this->vectorSearch = $vectorSearch;
        $this->logger = $logger;
        $this->topK = $topK;
        $this->threshold = $threshold;
        $this->db = $db;
    }

    public function generateResponse($userMessage, $systemPrompt = null, $conversationHistory = [], $temperature = 0.7, $maxTokens = 500)
    {
        try {
            $this->logger->info('RAG: Processing query', ['message' => $userMessage]);

            $queryEmbedding = $this->getCachedOrCreateEmbedding($userMessage);
            
            $similarChunks = $this->vectorSearch->searchSimilar(
                $queryEmbedding,
                $this->topK,
                $this->threshold
            );

            if (empty($similarChunks)) {
                $this->logger->warning('RAG: No relevant context found');
                return [
                    'response' => null,
                    'context' => '',
                    'confidence' => 0.0,
                    'sources' => []
                ];
            }

            $contextParts = [];
            $sources = [];
            $maxScore = 0;

            foreach ($similarChunks as $chunk) {
                $contextParts[] = $chunk['chunk_text'];
                $sources[] = [
                    'document' => $chunk['original_name'],
                    'score' => $chunk['score']
                ];
                $maxScore = max($maxScore, $chunk['score']);
            }

            $context = implode("\n\n", $contextParts);
            
            $response = $this->openai->generateResponse($userMessage, $context, $systemPrompt, $temperature, $maxTokens, $conversationHistory);

            $this->logger->info('RAG: Response generated', [
                'confidence' => $maxScore,
                'sources_count' => count($sources)
            ]);

            return [
                'response' => $response,
                'context' => $context,
                'confidence' => $maxScore,
                'sources' => $sources
            ];

        } catch (\Exception $e) {
            $this->logger->error('RAG Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function indexDocument($documentId, $text, $chunkSize = 500, $overlap = 50)
    {
        try {
            $this->logger->info('RAG: Indexing document', ['document_id' => $documentId]);

            $chunks = \App\Utils\TextProcessor::chunkText($text, $chunkSize, $overlap);
            
            $embeddings = $this->openai->createBatchEmbeddings($chunks);

            $indexed = 0;
            foreach ($chunks as $index => $chunk) {
                if ($embeddings[$index] !== null) {
                    $this->vectorSearch->storeVector($documentId, $chunk, $index, $embeddings[$index]);
                    $indexed++;
                }
            }

            $this->logger->info('RAG: Document indexed', [
                'document_id' => $documentId,
                'chunks' => $indexed
            ]);

            return $indexed;

        } catch (\Exception $e) {
            $this->logger->error('RAG Indexing Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getCachedOrCreateEmbedding($userMessage)
    {
        $normalized = trim(mb_strtolower($userMessage));
        $queryHash = md5($normalized);

        if ($this->db) {
            try {
                $cached = $this->db->fetchOne(
                    'SELECT embedding FROM query_embedding_cache 
                     WHERE query_hash = :hash AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)',
                    [':hash' => $queryHash]
                );

                if ($cached && !empty($cached['embedding'])) {
                    $this->db->query(
                        'UPDATE query_embedding_cache SET last_used_at = NOW(), hit_count = hit_count + 1 WHERE query_hash = :hash',
                        [':hash' => $queryHash]
                    );
                    $this->logger->info('RAG: Embedding cache hit', ['hash' => $queryHash]);
                    return \App\Utils\VectorMath::unserializeVector($cached['embedding']);
                }
            } catch (\Exception $e) {
                $this->logger->warning('RAG: Cache lookup failed, proceeding without cache', ['error' => $e->getMessage()]);
            }
        }

        $embedding = $this->openai->createEmbedding($userMessage);

        if ($this->db) {
            try {
                $binaryEmbedding = \App\Utils\VectorMath::serializeVector($embedding);
                $this->db->query(
                    'INSERT INTO query_embedding_cache (query_hash, embedding, created_at, last_used_at, hit_count)
                     VALUES (:hash, :embedding, NOW(), NOW(), 0)
                     ON DUPLICATE KEY UPDATE embedding = :embedding2, created_at = NOW(), last_used_at = NOW()',
                    [':hash' => $queryHash, ':embedding' => $binaryEmbedding, ':embedding2' => $binaryEmbedding]
                );
                $this->cleanExpiredCache();
            } catch (\Exception $e) {
                $this->logger->warning('RAG: Cache store failed', ['error' => $e->getMessage()]);
            }
        }

        return $embedding;
    }

    private function cleanExpiredCache()
    {
        try {
            $this->db->query('DELETE FROM query_embedding_cache WHERE last_used_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        } catch (\Exception $e) {
            $this->logger->warning('RAG: Cache cleanup failed', ['error' => $e->getMessage()]);
        }
    }
}
