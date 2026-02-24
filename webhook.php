<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\WhatsAppService;
use App\Services\OpenAIService;
use App\Services\VectorSearchService;
use App\Services\RAGService;
use App\Services\ConversationService;

header('Content-Type: application/json');

try {
    $config = Config::load(__DIR__ . '/config/config.php');
    $logger = new Logger(__DIR__ . '/logs');
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';
        $verifyToken = Config::get('whatsapp.verify_token');
        
        if ($mode === 'subscribe' && $token === $verifyToken) {
            echo $challenge;
            http_response_code(200);
            exit;
        }
        
        http_response_code(403);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);
        
        $logger->info('Webhook received', ['payload' => $payload]);
        
        $db = Database::getInstance(Config::get('database'));
        
        $whatsapp = new WhatsAppService(
            Config::get('whatsapp.access_token'),
            Config::get('whatsapp.phone_number_id'),
            Config::get('whatsapp.api_version'),
            $logger
        );

        $openai = new OpenAIService(
            Config::get('openai.api_key'),
            Config::get('openai.model'),
            Config::get('openai.embedding_model'),
            $logger
        );
        
        $messageData = $whatsapp->parseWebhookPayload($payload);
        
        if (!$messageData) {
            http_response_code(200);
            echo json_encode(['status' => 'ignored']);
            exit;
        }

        if ($messageData['type'] === 'audio' && isset($messageData['audio_id'])) {
            try {
                $logger->info('Audio message received', ['audio_id' => $messageData['audio_id']]);
                
                $audioContent = $whatsapp->downloadMedia($messageData['audio_id']);
                
                $contactName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $messageData['contact_name']);
                $phoneNumber = $messageData['from'];
                $conversationFolder = $contactName . '_' . $phoneNumber;
                
                $audioDir = __DIR__ . '/uploads/audios/' . $conversationFolder;
                if (!file_exists($audioDir)) {
                    mkdir($audioDir, 0755, true);
                }
                
                $audioFileName = uniqid('audio_') . '_' . time() . '.ogg';
                $audioPath = $audioDir . '/' . $audioFileName;
                file_put_contents($audioPath, $audioContent);
                
                $messageData['audio_url'] = '/uploads/audios/' . $conversationFolder . '/' . $audioFileName;
                
                $transcription = $openai->transcribeAudio($audioContent, 'audio.ogg');
                
                $messageData['text'] = '[Audio] ' . $transcription;
                $logger->info('Audio saved and transcribed', [
                    'text' => $transcription, 
                    'folder' => $conversationFolder,
                    'file' => $audioFileName
                ]);
            } catch (\Exception $e) {
                $logger->error('Audio processing error: ' . $e->getMessage());
                $whatsapp->sendMessage($messageData['from'], 'Lo siento, no pude procesar el audio. Por favor, envía un mensaje de texto.');
                http_response_code(200);
                echo json_encode(['status' => 'audio_error', 'error' => $e->getMessage()]);
                exit;
            }
        }

        if (empty($messageData['text'])) {
            http_response_code(200);
            echo json_encode(['status' => 'no_text']);
            exit;
        }
        
        $conversationService = new ConversationService($db);
        
        $conversation = $conversationService->getOrCreateConversation(
            $messageData['from'],
            $messageData['contact_name']
        );
        
        if ($messageData['message_id']) {
            $existingMessage = $db->fetchOne(
                'SELECT id FROM messages WHERE message_id = :message_id',
                [':message_id' => $messageData['message_id']]
            );
            
            if ($existingMessage) {
                $logger->info('Message already processed, skipping', [
                    'message_id' => $messageData['message_id']
                ]);
                http_response_code(200);
                echo json_encode(['status' => 'already_processed']);
                exit;
            }
        }
        
        $conversationService->addMessage(
            $conversation['id'],
            'user',
            $messageData['text'],
            $messageData['message_id'],
            null,
            null,
            $messageData['audio_url'] ?? null,
            $messageData['type']
        );
        
        $db->query(
            'UPDATE conversations SET last_message_at = NOW() WHERE id = :id',
            [':id' => $conversation['id']]
        );
        
        if ($messageData['message_id']) {
            $whatsapp->markAsRead($messageData['message_id']);
        }
        
        $calendarKeywords = [
            'list' => ['eventos', 'agendado', 'calendario', 'próximos eventos', 'qué tengo', 'mis eventos'],
            'availability' => ['disponible', 'disponibilidad', 'tienes tiempo', 'estás libre', 'tiempo libre'],
            'create' => [
                'agendar', 'programar', 'reservar', 'apartar', 'crear',
                'agenda', 'agendo', 'programa', 'programo', 'reserva', 'reservo', 'aparta', 'aparto',
                'crear evento', 'crear cita', 'apartar cita', 'agendar cita', 'hacer cita', 
                'poner cita', 'guardar cita', 'cita para', 'evento para', 'quiero agendar',
                'necesito agendar', 'quisiera agendar'
            ]
        ];
        
        $messageLower = mb_strtolower($messageData['text']);
        $calendarAction = null;
        
        foreach ($calendarKeywords as $action => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($messageLower, $keyword) !== false) {
                    $calendarAction = $action;
                    break 2;
                }
            }
        }

        $eventState = $conversation['event_creation_state'] ?? null;
        $eventAttempts = intval($conversation['event_creation_attempts'] ?? 0);
        
        if ($eventState === 'waiting_date') {
            $validDate = (new \App\Services\GoogleCalendarService(
                Config::get('google_calendar.access_token'),
                Config::get('google_calendar.calendar_id'),
                $logger,
                Config::get('google_calendar.refresh_token'),
                Config::get('google_calendar.client_id'),
                Config::get('google_calendar.client_secret')
            ))->validateDateFormat($messageData['text']);
            
            if ($validDate) {
                $eventData = json_decode($conversation['event_creation_data'], true);
                $eventData['date'] = $validDate;
                
                $response = "Perfecto. Ahora, ¿a qué hora?\n\n";
                $response .= "Ejemplos: 14:00, 3pm, 15:30";
                
                $db->query(
                    'UPDATE conversations SET event_creation_state = :state, event_creation_attempts = 0, event_creation_data = :data WHERE id = :id',
                    [
                        ':state' => 'waiting_time',
                        ':data' => json_encode($eventData),
                        ':id' => $conversation['id']
                    ]
                );
                
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                
                http_response_code(200);
                echo json_encode(['status' => 'event_flow_waiting_time']);
                exit;
            } else {
                $eventAttempts++;
                
                if ($eventAttempts >= 2) {
                    $response = "Lo siento, no he podido entender el formato de la fecha después de varios intentos.\n\n";
                    $response .= "¿Hay algo más en lo que pueda ayudarte?";
                    
                    $db->query(
                        'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                } else {
                    $response = "No he podido entender esa fecha. Por favor, usa uno de estos formatos:\n\n";
                    $response .= "• DD/MM/AAAA (ejemplo: 25/02/2026)\n";
                    $response .= "• Texto (ejemplo: 24 de febrero del 2026)";
                    
                    $db->query(
                        'UPDATE conversations SET event_creation_attempts = :attempts WHERE id = :id',
                        [
                            ':attempts' => $eventAttempts,
                            ':id' => $conversation['id']
                        ]
                    );
                }
                
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                
                http_response_code(200);
                echo json_encode(['status' => 'event_flow_invalid_date']);
                exit;
            }
        }
        
        if ($eventState === 'waiting_time') {
            $timePattern = '/(\d{1,2}):?(\d{2})?\s*(am|pm)?/i';
            $validTime = null;
            
            if (preg_match($timePattern, $messageData['text'], $matches)) {
                $hour = intval($matches[1]);
                $minute = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : 0;
                $ampm = isset($matches[3]) ? strtolower($matches[3]) : null;
                
                if ($ampm === 'pm' && $hour < 12) {
                    $hour += 12;
                } elseif ($ampm === 'am' && $hour === 12) {
                    $hour = 0;
                }
                
                if ($hour >= 0 && $hour < 24 && $minute >= 0 && $minute < 60) {
                    $validTime = sprintf('%02d:%02d', $hour, $minute);
                }
            }
            
            if ($validTime) {
                $eventData = json_decode($conversation['event_creation_data'], true);
                $eventData['time'] = $validTime;
                
                try {
                    $calendar = new \App\Services\GoogleCalendarService(
                        Config::get('google_calendar.access_token'),
                        Config::get('google_calendar.calendar_id'),
                        $logger,
                        Config::get('google_calendar.refresh_token'),
                        Config::get('google_calendar.client_id'),
                        Config::get('google_calendar.client_secret')
                    );
                    
                    $startDateTime = new \DateTime($eventData['date'] . ' ' . $validTime);
                    $endDateTime = clone $startDateTime;
                    $endDateTime->modify('+1 hour');
                    
                    $event = $calendar->createEvent(
                        $eventData['title'],
                        'Creado desde WhatsApp por ' . $messageData['contact_name'],
                        $startDateTime->format(\DateTime::RFC3339),
                        $endDateTime->format(\DateTime::RFC3339)
                    );
                    
                    $response = "Evento creado exitosamente\n\n";
                    $response .= $eventData['title'] . "\n";
                    $response .= $startDateTime->format('d/m/Y') . "\n";
                    $response .= $startDateTime->format('H:i') . " - " . $endDateTime->format('H:i');
                    
                    $db->query(
                        'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                } catch (\Exception $e) {
                    $logger->error('Error creating event: ' . $e->getMessage());
                    $response = "Error al crear el evento. Por favor intenta de nuevo más tarde.";
                    
                    $db->query(
                        'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                }
                
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                
                http_response_code(200);
                echo json_encode(['status' => 'event_created']);
                exit;
            } else {
                $eventAttempts++;
                
                if ($eventAttempts >= 2) {
                    $response = "Lo siento, no he podido entender la hora después de varios intentos.\n\n";
                    $response .= "¿Hay algo más en lo que pueda ayudarte?";
                    
                    $db->query(
                        'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                } else {
                    $response = "No he podido entender esa hora. Por favor, usa un formato como:\n\n";
                    $response .= "• 14:00\n";
                    $response .= "• 3pm\n";
                    $response .= "• 15:30";
                    
                    $db->query(
                        'UPDATE conversations SET event_creation_attempts = :attempts WHERE id = :id',
                        [
                            ':attempts' => $eventAttempts,
                            ':id' => $conversation['id']
                        ]
                    );
                }
                
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                
                http_response_code(200);
                echo json_encode(['status' => 'event_flow_invalid_time']);
                exit;
            }
        }
        
        if ($calendarAction) {
            $logger->info('Calendar request detected', [
                'action' => $calendarAction,
                'has_token' => !empty(Config::get('google_calendar.access_token'))
            ]);
            
            $accessToken = Config::get('google_calendar.access_token');
            
            if (empty($accessToken)) {
                $response = "⚠️ La funcionalidad de calendario no está configurada.\n\n";
                $response .= "Por favor contacta al administrador para habilitar esta función.";
                
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                
                http_response_code(200);
                echo json_encode(['status' => 'calendar_not_configured']);
                exit;
            }
            
            try {
                $calendar = new \App\Services\GoogleCalendarService(
                    $accessToken,
                    Config::get('google_calendar.calendar_id'),
                    $logger,
                    Config::get('google_calendar.refresh_token'),
                    Config::get('google_calendar.client_id'),
                    Config::get('google_calendar.client_secret')
                );
                
                $response = '';
                
                if ($calendarAction === 'list') {
                    $events = $calendar->listUpcomingEvents(5);
                    $response = $calendar->formatEventsForWhatsApp($events);
                } elseif ($calendarAction === 'availability') {
                    // Simple availability check for today
                    $date = date('Y-m-d');
                    $isAvailable = $calendar->checkAvailability($date, 9, 18);
                    
                    if ($isAvailable) {
                        $response = "Estoy disponible hoy entre 9 AM y 6 PM.";
                    } else {
                        $response = "Hoy tengo eventos agendados.";
                    }
                } elseif ($calendarAction === 'create') {
                    $eventState = $conversation['event_creation_state'] ?? null;
                    
                    if (!$eventState || $eventState === 'initial') {
                        $response = "Para agendar tu cita, por favor proporciona la fecha.\n\n";
                        $response .= "Formatos válidos:\n";
                        $response .= "• DD/MM/AAAA (ejemplo: 25/02/2026)\n";
                        $response .= "• Texto (ejemplo: 24 de febrero del 2026)";
                        
                        $eventTitle = 'Cita - ' . $messageData['contact_name'];
                        
                        $db->query(
                            'UPDATE conversations SET event_creation_state = :state, event_creation_attempts = 0, event_creation_data = :data WHERE id = :id',
                            [
                                ':state' => 'waiting_date',
                                ':data' => json_encode(['title' => $eventTitle]),
                                ':id' => $conversation['id']
                            ]
                        );
                    }
                }
                
                $whatsapp->sendMessage($messageData['from'], $response);
                
                $conversationService->addMessage(
                    $conversation['id'],
                    'bot',
                    $response,
                    null,
                    null,
                    1.0
                );
                
                $logger->info('Calendar request handled', ['action' => $calendarAction]);
                
                http_response_code(200);
                echo json_encode(['status' => 'calendar_handled']);
                exit;
                
            } catch (\Exception $e) {
                $logger->error('Calendar error: ' . $e->getMessage());
                // Continue with normal flow if calendar fails
            }
        }
        
        $humanKeywords = ['hablar con humano', 'hablar con una persona', 'hablar con operador', 
                          'quiero un humano', 'atención humana', 'operador', 'agente humano',
                          'hablar con alguien', 'persona real', 'representante'];
        
        $requestingHuman = false;
        
        foreach ($humanKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                $requestingHuman = true;
                break;
            }
        }
        
        if ($requestingHuman) {
            $humanMessage = 'Entendido. Te conectaré con un operador humano. Por favor espera un momento.';
            $whatsapp->sendMessage($messageData['from'], $humanMessage);
            
            $conversationService->addMessage(
                $conversation['id'],
                'bot',
                $humanMessage,
                null,
                null,
                1.0
            );
            
            $conversationService->updateConversationStatus($conversation['id'], 'pending_human');
            $db->query(
                'UPDATE conversations SET ai_enabled = 0 WHERE id = :id',
                [':id' => $conversation['id']]
            );
            
            $logger->info('User requested human intervention', [
                'conversation_id' => $conversation['id']
            ]);
            
            http_response_code(200);
            echo json_encode(['status' => 'human_requested']);
            exit;
        }
        
        $aiEnabled = $conversation['ai_enabled'] ?? true;
        
        if ($conversation['status'] === 'pending_human' || !$aiEnabled) {
            $logger->info('Conversation pending human intervention or AI disabled', [
                'conversation_id' => $conversation['id'],
                'ai_enabled' => $aiEnabled
            ]);
            if ($conversation['ai_disabled']) {
                http_response_code(200);
                echo json_encode(['status' => 'ai_disabled']);
                exit;
            }
        }
        
        $vectorSearch = new VectorSearchService(
            $db,
            Config::get('rag.similarity_method')
        );
        
        $systemPromptRow = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'system_prompt'",
            []
        );
        $systemPrompt = $systemPromptRow['setting_value'] ?? 'Eres un asistente virtual útil y amigable.';
        
        $contextCountRow = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'context_messages_count'",
            []
        );
        $contextMessagesCount = isset($contextCountRow['setting_value']) ? intval($contextCountRow['setting_value']) : 5;
        
        $conversationHistory = [];
        if ($contextMessagesCount > 0) {
            $historyMessages = $db->fetchAll(
                "SELECT sender_type, message_text FROM messages 
                 WHERE conversation_id = :conversation_id 
                 ORDER BY created_at DESC 
                 LIMIT " . intval($contextMessagesCount),
                [':conversation_id' => $conversation['id']]
            );
            
            if ($historyMessages && is_array($historyMessages)) {
                // Renombrar sender_type a sender para compatibilidad con OpenAI
                $conversationHistory = array_map(function($msg) {
                    return [
                        'sender' => $msg['sender_type'],
                        'message_text' => $msg['message_text']
                    ];
                }, array_reverse($historyMessages));
            }
        }
        
        $rag = new RAGService(
            $openai,
            $vectorSearch,
            $logger,
            3,
            0.7
        );
        
        try {
            $result = $rag->generateResponse($messageData['text'], $systemPrompt, $conversationHistory);
            
            if ($result['response'] && $result['confidence'] >= Config::get('rag.similarity_threshold')) {
                $whatsapp->sendMessage($conversation['phone_number'], $result['response']);
                
                $conversationService->addMessage(
                    $conversation['id'],
                    'bot',
                    $result['response'],
                    null,
                    $result['context'],
                    $result['confidence']
                );
                
                $db->query(
                    'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                    [':id' => $conversation['id']]
                );
                
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'confidence' => $result['confidence'],
                    'sources' => count($result['sources'])
                ]);
                exit;
            }
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'INSUFFICIENT_FUNDS') !== false) {
                $db->query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES ('openai_status', 'insufficient_funds') 
                     ON DUPLICATE KEY UPDATE setting_value = 'insufficient_funds'",
                    []
                );
                $db->query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES ('openai_error_timestamp', NOW()) 
                     ON DUPLICATE KEY UPDATE setting_value = NOW()",
                    []
                );
            }
            $logger->error('RAG Error: ' . $e->getMessage());
        }
        
        try {
            $response = $openai->generateResponse($messageData['text'], '', $systemPrompt, 0.7, 500, $conversationHistory);
            
            if ($response) {
                $whatsapp->sendMessage($conversation['phone_number'], $response);
                
                $conversationService->addMessage(
                    $conversation['id'],
                    'bot',
                    $response
                );
                
                $db->query(
                    'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                    [':id' => $conversation['id']]
                );
                
                http_response_code(200);
                echo json_encode(['status' => 'processed', 'type' => 'openai']);
                exit;
            }
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'INSUFFICIENT_FUNDS') !== false) {
                $db->query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES ('openai_status', 'insufficient_funds') 
                     ON DUPLICATE KEY UPDATE setting_value = 'insufficient_funds'",
                    []
                );
                $db->query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES ('openai_error_timestamp', NOW()) 
                     ON DUPLICATE KEY UPDATE setting_value = NOW()",
                    []
                );
            }
            $logger->error('OpenAI Fallback Error: ' . $e->getMessage());
        }
        
        $fallbackMessage = 'Lo siento, no encontré información relevante sobre tu consulta. Un operador te atenderá pronto.';
        $whatsapp->sendMessage($messageData['from'], $fallbackMessage);
        
        $conversationService->addMessage(
            $conversation['id'],
            'bot',
            $fallbackMessage,
            null,
            null,
            0.0
        );
        
        http_response_code(200);
        echo json_encode(['status' => 'fallback']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    
} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Webhook Error: ' . $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
