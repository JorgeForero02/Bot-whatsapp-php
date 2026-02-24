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
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            
            if ($response->getStatusCode() === 429 || 
                (isset($body['error']['code']) && $body['error']['code'] === 'insufficient_quota')) {
                $this->logger->error('OpenAI Insufficient Funds: ' . ($body['error']['message'] ?? 'Quota exceeded'));
                throw new \RuntimeException('INSUFFICIENT_FUNDS');
            }
            
            $this->logger->error('OpenAI Embedding Error: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI Embedding Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateResponse($prompt, $context = '', $systemPrompt = null, $temperature = 0.7, $maxTokens = 500, $conversationHistory = [])
    {
        try {
            $systemMessage = $systemPrompt ?? 'Eres un asistente virtual útil y amigable. Responde de manera clara y concisa basándote en el contexto proporcionado.';
            
            $messages = [
                ['role' => 'system', 'content' => $systemMessage]
            ];
            
            if (!empty($context)) {
                $messages[] = [
                    'role' => 'system',
                    'content' => "Contexto relevante:\n" . $context
                ];
            }
            
            if (!empty($conversationHistory)) {
                foreach ($conversationHistory as $historyMsg) {
                    $role = $historyMsg['sender'] === 'bot' ? 'assistant' : 'user';
                    $messages[] = [
                        'role' => $role,
                        'content' => $historyMsg['message_text']
                    ];
                }
            }
            
            $messages[] = ['role' => 'user', 'content' => $prompt];

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
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            
            if ($response->getStatusCode() === 429 || 
                (isset($body['error']['code']) && $body['error']['code'] === 'insufficient_quota')) {
                $this->logger->error('OpenAI Insufficient Funds: ' . ($body['error']['message'] ?? 'Quota exceeded'));
                throw new \RuntimeException('INSUFFICIENT_FUNDS');
            }
            
            $this->logger->error('OpenAI Generation Error: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function transcribeAudio($audioContent, $filename = 'audio.ogg')
    {
        try {
            // Save audio to temp file
            $tempFile = sys_get_temp_dir() . '/' . uniqid() . '_' . $filename;
            file_put_contents($tempFile, $audioContent);

            $response = $this->client->post('audio/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey
                ],
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($tempFile, 'r'),
                        'filename' => $filename
                    ],
                    [
                        'name' => 'model',
                        'contents' => 'whisper-1'
                    ],
                    [
                        'name' => 'language',
                        'contents' => 'es'
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            // Delete temp file
            unlink($tempFile);

            if (isset($data['text'])) {
                $this->logger->info('Whisper: Audio transcribed', [
                    'text_length' => strlen($data['text'])
                ]);
                return $data['text'];
            }

            throw new \RuntimeException('Invalid transcription response');
        } catch (\Exception $e) {
            $this->logger->error('Whisper Transcription Error: ' . $e->getMessage());
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
