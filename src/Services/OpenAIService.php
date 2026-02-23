<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Core\Logger;

class OpenAIService
{
    private $client;
    private $apiKey;
    private $model;
    private $embeddingModel;
    private $logger;

    public function __construct($apiKey, $model, $embeddingModel, Logger $logger)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->embeddingModel = $embeddingModel;
        $this->logger = $logger;
        
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30,
            'verify' => false
        ]);
    }

    public function createEmbedding($text)
    {
        try {
            $response = $this->client->post('embeddings', [
                'json' => [
                    'model' => $this->embeddingModel,
                    'input' => $text
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['data'][0]['embedding'])) {
                return $data['data'][0]['embedding'];
            }

            throw new \RuntimeException('Invalid embedding response');
        } catch (\Exception $e) {
            $this->logger->error('OpenAI Embedding Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateResponse($prompt, $context = '', $temperature = 0.7, $maxTokens = 500)
    {
        try {
            $systemMessage = 'Eres un asistente virtual útil y amigable. Responde de manera clara y concisa basándote en el contexto proporcionado.';
            
            if (!empty($context)) {
                $systemMessage .= "\n\nContexto relevante:\n" . $context;
            }

            $messages = [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $prompt]
            ];

            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            }

            throw new \RuntimeException('Invalid chat response');
        } catch (\Exception $e) {
            $this->logger->error('OpenAI Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createBatchEmbeddings(array $texts)
    {
        $embeddings = [];
        
        foreach ($texts as $index => $text) {
            try {
                $embeddings[$index] = $this->createEmbedding($text);
            } catch (\Exception $e) {
                $this->logger->error('Batch embedding error for index ' . $index . ': ' . $e->getMessage());
                $embeddings[$index] = null;
            }
        }

        return $embeddings;
    }
}
