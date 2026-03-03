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
            'verify' => false // Deshabilitado para desarrollo local
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

    public function generateResponse($prompt, $context = '', $systemPrompt = null, $temperature = 0.7, $maxTokens = 500, $conversationHistory = [], $modelOverride = null)
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
                    'model' => $modelOverride ?? $this->model,
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

    public function generateResponseWithTools($prompt, $context = '', $systemPrompt = null, array $tools = [], $temperature = 0.7, $maxTokens = 500, $conversationHistory = [])
    {
        try {
            $systemMessage = $systemPrompt ?? 'Eres un asistente virtual útil y amigable.';
            
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

            $requestBody = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ];

            if (!empty($tools)) {
                $requestBody['tools'] = $tools;
                $requestBody['tool_choice'] = 'auto';
            }

            $response = $this->client->post('chat/completions', [
                'json' => $requestBody
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['choices'][0]['message'])) {
                return $data['choices'][0]['message'];
            }

            throw new \RuntimeException('Invalid chat response with tools');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            
            if ($response->getStatusCode() === 429 || 
                (isset($body['error']['code']) && $body['error']['code'] === 'insufficient_quota')) {
                $this->logger->error('OpenAI Insufficient Funds: ' . ($body['error']['message'] ?? 'Quota exceeded'));
                throw new \RuntimeException('INSUFFICIENT_FUNDS');
            }
            
            $this->logger->error('OpenAI Tools Error: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI Tools Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getCalendarTools()
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'schedule_appointment',
                    'description' => 'El usuario quiere agendar, reservar, programar o sacar una cita, turno, reunión, consulta o cualquier tipo de evento. Usar cuando el usuario expresa intención de reservar tiempo, aunque no use palabras exactas. Ejemplos: "quiero una cita", "puedo ir el martes", "necesito un turno", "están disponibles el viernes", "me gustaría una consulta"',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'date_preference' => [
                                'type' => 'string',
                                'description' => 'Fecha o referencia temporal mencionada. Relativa (mañana, el viernes, la próxima semana) o absoluta (el 15 de marzo)'
                            ],
                            'time_preference' => [
                                'type' => 'string',
                                'description' => 'Hora o rango preferido si fue mencionado (a las 3, en la mañana, por la tarde)'
                            ],
                            'service_type' => [
                                'type' => 'string',
                                'description' => 'Tipo de servicio o motivo si fue mencionado'
                            ],
                            'is_confirmed' => [
                                'type' => 'boolean',
                                'description' => 'true solo si el usuario ya confirmó explícitamente fecha y hora específicas'
                            ]
                        ],
                        'required' => ['is_confirmed']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_availability',
                    'description' => 'El usuario pregunta por disponibilidad sin necesariamente querer agendar. Ejemplos: "qué días tienen", "cuándo están libres", "tienen espacio esta semana"',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'date_range' => [
                                'type' => 'string',
                                'description' => 'Rango de fechas consultado'
                            ]
                        ],
                        'required' => ['date_range']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_appointments',
                    'description' => 'El usuario quiere consultar, ver o saber sus citas, eventos o reservas próximas. Ejemplos: "quiero saber mis próximas citas", "tengo citas pendientes?", "cuándo es mi cita", "qué tengo agendado", "ver mi agenda", "mis reservas", "tengo algo agendado?", "cuáles son mis próximos eventos"',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                        'required' => []
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancel_appointment',
                    'description' => 'El usuario quiere cancelar, anular o reprogramar una cita existente',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Motivo de cancelación si fue mencionado'
                            ]
                        ],
                        'required' => []
                    ]
                ]
            ]
        ];
    }

    public function transcribeAudio($audioContent, $filename = 'audio.ogg')
    {
        try {
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
