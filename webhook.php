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

function getCalendarService($logger, $db) {
    $calendarConfig = \App\Helpers\CalendarConfigHelper::loadFromDatabase($db);
    
    if (!$calendarConfig['enabled']) {
        return null;
    }
    
    $credentials = $calendarConfig['credentials'];
    
    if (empty($credentials['access_token'])) {
        return null;
    }
    
    return new \App\Services\GoogleCalendarService(
        $credentials['access_token'],
        $credentials['calendar_id'],
        $logger,
        $calendarConfig['timezone'],
        $credentials['refresh_token'],
        $credentials['client_id'],
        $credentials['client_secret']
    );
}

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
        
        $calendarConfig = \App\Helpers\CalendarConfigHelper::loadFromDatabase($db);
        $calendarAction = null;
        
        if ($calendarConfig['enabled']) {
            $calendarKeywords = $calendarConfig['keywords'];
            $messageLower = mb_strtolower($messageData['text']);
            
            foreach ($calendarKeywords as $action => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($messageLower, $keyword) !== false) {
                        $calendarAction = $action;
                        break 2;
                    }
                }
            }
        }

        $eventState = $conversation['event_creation_state'] ?? null;
        $eventAttempts = intval($conversation['event_creation_attempts'] ?? 0);
        
        if ($eventState === 'waiting_date') {
            $calendar = getCalendarService($logger, $db);
            if (!$calendar) {
                $response = "Lo siento, no puedo agendar citas en este momento. Por favor contáctanos directamente.";
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                $db->query(
                    'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                    [':id' => $conversation['id']]
                );
                http_response_code(200);
                echo json_encode(['status' => 'calendar_disabled']);
                exit;
            }
            
            $validDate = $calendar->validateDateFormat($messageData['text']);
            
            if ($validDate) {
                $eventData = json_decode($conversation['event_creation_data'], true);
                $eventData['date'] = $validDate;
                
                $response = "Perfecto. ¿A qué hora? (Ejemplo: 14:00 o 3pm)";
                
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
                    $response = "No entiendo esa fecha. Intenta con: 25/02/2026 o mañana";
                    
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
                    $calendar = getCalendarService($logger, $db);
                    $calendarConfig = \App\Helpers\CalendarConfigHelper::loadFromDatabase($db);
                    
                    if (!$calendar) {
                        throw new \Exception('Calendar service not available');
                    }
                    
                    $timezone = new \DateTimeZone($calendarConfig['timezone']);
                    $startDateTime = new \DateTime($eventData['date'] . ' ' . $validTime, $timezone);
                    $endDateTime = clone $startDateTime;
                    $endDateTime->modify('+' . $calendarConfig['default_duration_minutes'] . ' minutes');
                    
                    $businessValidation = $calendar->validateBusinessHours(
                        $eventData['date'],
                        $validTime,
                        $calendarConfig['business_hours']
                    );
                    
                    if (!$businessValidation['valid']) {
                        $response = $businessValidation['reason'];
                        $db->query(
                            'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                            [':id' => $conversation['id']]
                        );
                        $whatsapp->sendMessage($messageData['from'], $response);
                        $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                        http_response_code(200);
                        echo json_encode(['status' => 'outside_business_hours']);
                        exit;
                    }
                    
                    $overlapCheck = $calendar->checkEventOverlap(
                        $eventData['date'],
                        $validTime,
                        $endDateTime->format('H:i')
                    );
                    
                    if ($overlapCheck['overlap']) {
                        $response = "Lo siento, ya hay una cita agendada en ese horario.\n\n";
                        $response .= "Por favor elige otro horario.";
                        
                        $eventAttempts++;
                        $db->query(
                            'UPDATE conversations SET event_creation_attempts = :attempts WHERE id = :id',
                            [':attempts' => $eventAttempts, ':id' => $conversation['id']]
                        );
                        
                        $whatsapp->sendMessage($messageData['from'], $response);
                        $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                        http_response_code(200);
                        echo json_encode(['status' => 'event_overlap']);
                        exit;
                    }
                    
                    $eventsCount = $calendar->countEventsForDay($eventData['date']);
                    if ($eventsCount >= $calendarConfig['max_events_per_day']) {
                        $response = "Lo siento, ya se alcanzó el máximo de citas para ese día.\n\n";
                        $response .= "Por favor elige otro día.";
                        
                        $db->query(
                            'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                            [':id' => $conversation['id']]
                        );
                        
                        $whatsapp->sendMessage($messageData['from'], $response);
                        $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                        http_response_code(200);
                        echo json_encode(['status' => 'max_events_reached']);
                        exit;
                    }
                    
                    $confirmationDate = $startDateTime->format('d/m/Y');
                    $confirmationTime = $startDateTime->format('H:i');
                    
                    $event = $calendar->createEvent(
                        $eventData['title'],
                        'Creado desde WhatsApp por ' . $messageData['contact_name'],
                        $startDateTime->format(\DateTime::RFC3339),
                        $endDateTime->format(\DateTime::RFC3339)
                    );
                    
                    $response = "✓ Cita confirmada\n";
                    $response .= $confirmationDate . " a las " . $confirmationTime;
                    $response .= "\nNos vemos!";
                    
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
                    $response = "No entiendo esa hora. Intenta: 14:00 o 3pm";
                    
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
            $logger->info('Calendar request detected', ['action' => $calendarAction]);
            
            $calendar = getCalendarService($logger, $db);
            
            if (!$calendar) {
                $response = "Lo siento, no puedo gestionar citas en este momento. Por favor contáctanos para ayudarte.";
                
                $whatsapp->sendMessage($messageData['from'], $response);
                $conversationService->addMessage($conversation['id'], 'bot', $response, null, null, 1.0);
                
                http_response_code(200);
                echo json_encode(['status' => 'calendar_not_configured']);
                exit;
            }
            
            try {
                $response = '';
                $messageLower = mb_strtolower($messageData['text']);
                
                if ($calendarAction === 'list') {
                    $timezone = new \DateTimeZone($calendarConfig['timezone']);
                    
                    if (preg_match('/mañana/i', $messageLower)) {
                        $tomorrow = (new \DateTime('tomorrow', $timezone))->format('Y-m-d');
                        $allEvents = $calendar->getEventsForDay($tomorrow);
                        $contactName = $messageData['contact_name'];
                        $filteredItems = [];
                        
                        if (!empty($allEvents['items'])) {
                            foreach ($allEvents['items'] as $event) {
                                $summary = $event['summary'] ?? '';
                                if (stripos($summary, $contactName) !== false) {
                                    $filteredItems[] = $event;
                                }
                            }
                        }
                        
                        $events = ['items' => $filteredItems];
                        $response = "Citas para mañana:\n\n";
                    } elseif (preg_match('/hoy/i', $messageLower)) {
                        $allEvents = $calendar->getTodayEvents();
                        $contactName = $messageData['contact_name'];
                        $filteredItems = [];
                        
                        if (!empty($allEvents['items'])) {
                            foreach ($allEvents['items'] as $event) {
                                $summary = $event['summary'] ?? '';
                                if (stripos($summary, $contactName) !== false) {
                                    $filteredItems[] = $event;
                                }
                            }
                        }
                        
                        $events = ['items' => $filteredItems];
                        $response = "Citas para hoy:\n\n";
                    } elseif (preg_match('/próxima|siguiente/i', $messageLower)) {
                        $allEvents = $calendar->listUpcomingEvents(50);
                        $contactName = $messageData['contact_name'];
                        $nextEvent = null;
                        
                        if (!empty($allEvents['items'])) {
                            foreach ($allEvents['items'] as $event) {
                                $summary = $event['summary'] ?? '';
                                if (stripos($summary, $contactName) !== false) {
                                    $nextEvent = $event;
                                    break;
                                }
                            }
                        }
                        
                        if ($nextEvent) {
                            $start = new \DateTime($nextEvent['start']['dateTime'] ?? $nextEvent['start']['date']);
                            $response = "Tu próxima cita es:\n\n";
                            $response .= "📆 " . $start->format('d/m/Y H:i') . "\n";
                        } else {
                            $response = "No tienes próximas citas agendadas.";
                        }
                        $events = null;
                    } else {
                        $allEvents = $calendar->listUpcomingEvents(50);
                        $contactName = $messageData['contact_name'];
                        $filteredItems = [];
                        
                        if (!empty($allEvents['items'])) {
                            foreach ($allEvents['items'] as $event) {
                                $summary = $event['summary'] ?? '';
                                if (stripos($summary, $contactName) !== false) {
                                    $filteredItems[] = $event;
                                    if (count($filteredItems) >= 5) break;
                                }
                            }
                        }
                        
                        $events = ['items' => $filteredItems];
                        $response = "📅 ";
                    }
                    
                    if ($events !== null) {
                        $response .= $calendar->formatEventsForWhatsApp($events['items'] ?? []);
                    }
                } elseif ($calendarAction === 'availability') {
                    $timezone = new \DateTimeZone($calendarConfig['timezone']);
                    $today = (new \DateTime('now', $timezone))->format('Y-m-d');
                    $todayEvents = $calendar->getEventsForDay($today);
                    $count = count($todayEvents['items'] ?? []);
                    
                    if ($count === 0) {
                        $hours = $calendarConfig['business_hours'][strtolower(date('l'))];
                        if ($hours) {
                            $response = "Estoy disponible hoy de {$hours['start']} a {$hours['end']}.";
                        } else {
                            $response = "No atendemos hoy.";
                        }
                    } else {
                        $response = "Hoy tengo {$count} cita(s) agendada(s).";
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
            }
        }
        
        $cancelKeywords = ['cancelar', 'salir', 'no quiero', 'olvida', 'dejalo'];
        $requestingCancel = false;
        
        foreach ($cancelKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                $requestingCancel = true;
                break;
            }
        }
        
        if ($requestingCancel && ($eventState === 'waiting_date' || $eventState === 'waiting_time')) {
            $db->query(
                'UPDATE conversations SET event_creation_state = NULL, event_creation_attempts = 0, event_creation_data = NULL WHERE id = :id',
                [':id' => $conversation['id']]
            );
            $cancelMessage = 'Entendido, cancelé el proceso. ¿En qué más puedo ayudarte?';
            $whatsapp->sendMessage($messageData['from'], $cancelMessage);
            $conversationService->addMessage($conversation['id'], 'bot', $cancelMessage, null, null, 1.0);
            http_response_code(200);
            echo json_encode(['status' => 'flow_cancelled']);
            exit;
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
            $humanMessage = 'Enseguida te comunico con alguien de nuestro equipo 😊';
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
            http_response_code(200);
            echo json_encode(['status' => 'ai_disabled']);
            exit;
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
        
        $calendarConfig = \App\Helpers\CalendarConfigHelper::loadFromDatabase($db);
        if ($calendarConfig['enabled'] && file_exists(__DIR__ . '/prompts/calendar_prompt.txt')) {
            $calendarPrompt = file_get_contents(__DIR__ . '/prompts/calendar_prompt.txt');
            $systemPrompt .= "\n\n" . $calendarPrompt;
        }
        
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
