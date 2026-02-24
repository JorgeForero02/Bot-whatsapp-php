<?php

namespace App\Services;

use App\Core\Logger;

class RAGService
{
    private $openai;
    private $vectorSearch;
    private $logger;
    private $topK;
    private $threshold;

    public function __construct(
        OpenAIService $openai,
        VectorSearchService $vectorSearch,
        Logger $logger,
        $topK = 3,
        $threshold = 0.7
    ) {
        $this->openai = $openai;
        $this->vectorSearch = $vectorSearch;
        $this->logger = $logger;
        $this->topK = $topK;
        $this->threshold = $threshold;
    }

    public function generateResponse($userMessage, $systemPrompt = null, $conversationHistory = [])
    {
        try {
            $this->logger->info('RAG: Processing query', ['message' => $userMessage]);

            $queryEmbedding = $this->openai->createEmbedding($userMessage);
            
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
            
            $response = $this->openai->generateResponse($userMessage, $context, $systemPrompt, 0.7, 500, $conversationHistory);

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
}
