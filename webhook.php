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

        // Process audio messages
        if ($messageData['type'] === 'audio' && isset($messageData['audio_id'])) {
            try {
                $logger->info('Audio message received', ['audio_id' => $messageData['audio_id']]);
                
                // Download audio from WhatsApp
                $audioContent = $whatsapp->downloadMedia($messageData['audio_id']);
                
                // Create conversation-specific folder
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
                
                // Transcribe using Whisper
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

        // Ensure we have text to process
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
        
        // Check for Google Calendar requests
        $calendarKeywords = [
            'list' => ['eventos', 'agenda', 'agendado', 'calendario', 'próximos eventos'],
            'availability' => ['disponible', 'disponibilidad', 'tienes tiempo', 'estás libre'],
            'create' => ['agendar', 'crear evento', 'crear cita', 'programar', 'reservar']
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
        
        // Handle calendar requests
        if ($calendarAction && Config::get('google_calendar.access_token')) {
            try {
                $calendar = new \App\Services\GoogleCalendarService(
                    Config::get('google_calendar.access_token'),
                    Config::get('google_calendar.calendar_id'),
                    $logger
                );
                
                $response = '';
                
                if ($calendarAction === 'list') {
                    $events = $calendar->listUpcomingEvents(5);
                    $response = $calendar->formatEventsForWhatsApp($events);
                } elseif ($calendarAction === 'availability') {
                    // Simple availability check for today
                    $date = date('Y-m-d');
                    $availability = $calendar->checkAvailability($date, 9, 18);
                    
                    if ($availability['available']) {
                        $response = "✅ Estoy disponible hoy entre 9 AM y 6 PM.";
                    } else {
                        $response = "⏰ Hoy tengo " . count($availability['busy_times']) . " evento(s) agendado(s).";
                    }
                } elseif ($calendarAction === 'create') {
                    // Extract date/time using basic parsing (can be improved with NLP)
                    $response = "📅 Para agendar una cita, por favor proporciona:\n\n";
                    $response .= "• Título de la cita\n";
                    $response .= "• Fecha (DD/MM/YYYY)\n";
                    $response .= "• Hora (HH:MM)\n";
                    $response .= "• Duración (en horas)\n\n";
                    $response .= "Ejemplo: 'Agendar: Reunión con cliente - 25/02/2026 - 14:00 - 1 hora'";
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
