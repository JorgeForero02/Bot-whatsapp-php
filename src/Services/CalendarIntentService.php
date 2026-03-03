<?php

namespace App\Services;

use App\Core\Logger;

class CalendarIntentService
{
    private $openai;
    private $logger;

    public function __construct(OpenAIService $openai, Logger $logger)
    {
        $this->openai = $openai;
        $this->logger = $logger;
    }

    public function detectIntent(string $message, array $conversationHistory, string $systemPrompt): array
    {
        try {
            $tools = $this->openai->getCalendarTools();

            $response = $this->openai->generateResponseWithTools(
                $message,
                '',
                $systemPrompt,
                $tools,
                0.7,
                500,
                $conversationHistory
            );

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('CalendarIntentService: Error detecting intent', [
                'error' => $e->getMessage()
            ]);

            return [
                'intent' => 'none',
                'extracted_data' => [],
                'confidence' => 'low',
                'original_response' => null
            ];
        }
    }

    private function parseResponse(array $message): array
    {
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {
            $toolCall = $message['tool_calls'][0];
            $functionName = $toolCall['function']['name'] ?? '';
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];

            $this->logger->info('CalendarIntentService: Tool invoked', [
                'function' => $functionName,
                'arguments' => $arguments
            ]);

            switch ($functionName) {
                case 'schedule_appointment':
                    return [
                        'intent' => 'schedule',
                        'extracted_data' => [
                            'date_preference' => $arguments['date_preference'] ?? '',
                            'time_preference' => $arguments['time_preference'] ?? null,
                            'service_type' => $arguments['service_type'] ?? null,
                            'is_confirmed' => $arguments['is_confirmed'] ?? false
                        ],
                        'confidence' => !empty($arguments['date_preference']) ? 'high' : 'low',
                        'original_response' => $message['content'] ?? null
                    ];

                case 'check_availability':
                    return [
                        'intent' => 'check_availability',
                        'extracted_data' => [
                            'date_range' => $arguments['date_range'] ?? ''
                        ],
                        'confidence' => !empty($arguments['date_range']) ? 'high' : 'low',
                        'original_response' => $message['content'] ?? null
                    ];

                case 'list_appointments':
                    return [
                        'intent' => 'list',
                        'extracted_data' => [],
                        'confidence' => 'high',
                        'original_response' => $message['content'] ?? null
                    ];

                case 'cancel_appointment':
                    return [
                        'intent' => 'cancel',
                        'extracted_data' => [
                            'reason' => $arguments['reason'] ?? null
                        ],
                        'confidence' => 'high',
                        'original_response' => $message['content'] ?? null
                    ];

                default:
                    $this->logger->warning('CalendarIntentService: Unknown tool', [
                        'function' => $functionName
                    ]);
                    break;
            }
        }

        return [
            'intent' => 'none',
            'extracted_data' => [],
            'confidence' => 'low',
            'original_response' => $message['content'] ?? null
        ];
    }
}
