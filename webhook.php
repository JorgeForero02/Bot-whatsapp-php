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
use App\Services\EncryptionService;
use App\Services\CredentialService;
use App\Services\CalendarIntentService;
use App\Services\ClassicBotService;
use App\Handlers\CalendarFlowHandler;
use App\Handlers\ClassicCalendarFlowHandler;

header('Content-Type: application/json');

function handleInsufficientFunds($db, $e) {
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
        return true;
    }
    return false;
}

function getCalendarService($logger, $db, $credentialService = null, $calendarConfig = null) {
    if ($calendarConfig === null) {
        $calendarConfig = \App\Helpers\CalendarConfigHelper::loadFromDatabase($db);
    }
    
    if (!$calendarConfig['enabled']) {
        return null;
    }
    
    if ($credentialService) {
        $credentials = $credentialService->getGoogleOAuthCredentials();
    } else {
        $credentials = $calendarConfig['credentials'];
    }
    
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
        $credentials['client_secret'],
        $db
    );
}

try {
    $config = Config::load(__DIR__ . '/config/config.php');
    date_default_timezone_set(Config::get('app.timezone') ?: 'America/Bogota');
    $logger = new Logger(__DIR__ . '/logs');
    $db = Database::getInstance(Config::get('database'));
    
    $credentialService = null;
    try {
        $encryption = new EncryptionService();
        $credentialService = new CredentialService($db, $encryption);
    } catch (\Exception $e) {
        $logger->warning('CredentialService not available, using config fallback: ' . $e->getMessage());
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';
        
        if ($credentialService && $credentialService->hasWhatsAppCredentials()) {
            $waCreds = $credentialService->getWhatsAppCredentials();
            $verifyToken = $waCreds['verify_token'];
        } else {
            $verifyToken = Config::get('whatsapp.verify_token');
        }
        
        if ($mode === 'subscribe' && $token === $verifyToken) {
            echo $challenge;
            http_response_code(200);
            exit;
        }
        
        http_response_code(403);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        
        if ($credentialService && $credentialService->hasWhatsAppCredentials()) {
            $waCreds = $credentialService->getWhatsAppCredentials();
            $appSecret = $waCreds['app_secret'];
        } else {
            $appSecret = Config::get('whatsapp.app_secret');
        }
        if ($appSecret) {
            $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
            if (!hash_equals($expected, $signature)) {
                http_response_code(401);
                exit('Unauthorized');
            }
        } else {
            $logger->warning('Webhook signature validation SKIPPED - no app_secret configured');
        }
        
        $payload = json_decode($rawBody, true);
        
        if ($credentialService && $credentialService->hasWhatsAppCredentials()) {
            $waCreds = $waCreds ?? $credentialService->getWhatsAppCredentials();
            $whatsapp = new WhatsAppService(
                $waCreds['access_token'],
                $waCreds['phone_number_id'],
                Config::get('whatsapp.api_version'),
                $logger
            );
        } else {
            $whatsapp = new WhatsAppService(
                Config::get('whatsapp.access_token'),
                Config::get('whatsapp.phone_number_id'),
                Config::get('whatsapp.api_version'),
                $logger
            );
        }

        $openaiTemperature = 0.7;
        $openaiMaxTokens = 500;

        if ($credentialService && $credentialService->hasOpenAICredentials()) {
            $oaiCreds = $credentialService->getOpenAICredentials();
            $openai = new OpenAIService(
                $oaiCreds['api_key'],
                $oaiCreds['model'],
                $oaiCreds['embedding_model'],
                $logger
            );
            $openaiTemperature = $oaiCreds['temperature'] ?? 0.7;
            $openaiMaxTokens = $oaiCreds['max_tokens'] ?? 500;
        } else {
            $openai = new OpenAIService(
                Config::get('openai.api_key'),
                Config::get('openai.model'),
                Config::get('openai.embedding_model'),
                $logger
            );
        }
        
        $messageData = $whatsapp->parseWebhookPayload($payload);
        
        if (!$messageData) {
            http_response_code(200);
            echo json_encode(['status' => 'ignored']);
            exit;
        }

        $logger->info('Webhook received', [
            'type' => $messageData['type'],
            'from_hash' => substr(hash('sha256', $messageData['from'] ?? ''), 0, 12),
            'timestamp' => $messageData['timestamp'] ?? time()
        ]);

        $botModeRow = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'bot_mode'",
            []
        );
        $botMode = $botModeRow['setting_value'] ?? 'ai';

        $unsupportedTypes = ['image', 'document', 'location', 'video', 'sticker', 'contacts'];
        if (in_array($messageData['type'], $unsupportedTypes)) {
            $conversationService = new ConversationService($db);
            $conversation = $conversationService->getOrCreateConversation(
                $messageData['from'],
                $messageData['contact_name']
            );
            $unsupportedMsg = "Lo siento, por el momento solo puedo procesar mensajes de *texto*. Por favor, envíame tu consulta en un mensaje de texto.";
            $whatsapp->sendMessage($messageData['from'], $unsupportedMsg);
            $conversationService->addMessage($conversation['id'], 'bot', $unsupportedMsg, null, null, 1.0);
            $db->query(
                'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                [':id' => $conversation['id']]
            );
            http_response_code(200);
            echo json_encode(['status' => 'unsupported_media_type']);
            exit;
        }

        if ($messageData['type'] === 'audio' && isset($messageData['audio_id'])) {
            if ($botMode === 'classic') {
                $conversationService = new ConversationService($db);
                $conversation = $conversationService->getOrCreateConversation(
                    $messageData['from'],
                    $messageData['contact_name']
                );
                $audioUnsupportedMsg = "Lo siento, en este modo solo puedo procesar mensajes de *texto*. Por favor, escribe tu consulta.";
                $whatsapp->sendMessage($messageData['from'], $audioUnsupportedMsg);
                $conversationService->addMessage($conversation['id'], 'bot', $audioUnsupportedMsg, null, null, 1.0);
                $db->query(
                    'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                    [':id' => $conversation['id']]
                );
                $logger->info('Audio rejected in classic mode', ['from' => $messageData['from']]);
                http_response_code(200);
                echo json_encode(['status' => 'audio_not_supported_classic']);
                exit;
            }
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
                echo json_encode(['status' => 'audio_error']);
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
        
        $humanKeywords = ['hablar con humano', 'hablar con una persona', 'hablar con operador', 
                          'quiero un humano', 'atención humana', 'operador', 'agente humano',
                          'hablar con alguien', 'persona real', 'representante'];
        
        $messageLower = mb_strtolower($messageData['text']);
        $isRequestingHuman = false;
        
        foreach ($humanKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                $isRequestingHuman = true;
                break;
            }
        }
        
        if ($isRequestingHuman) {
            $humanMessage = 'Enseguida te comunico con alguien de nuestro equipo.';
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
        
        $isAiEnabled = $conversation['ai_enabled'] ?? true;
        
        if ($conversation['status'] === 'pending_human' || !$isAiEnabled) {
            $logger->info('Conversation pending human intervention or AI disabled', [
                'conversation_id' => $conversation['id'],
                'ai_enabled' => $isAiEnabled
            ]);
            http_response_code(200);
            echo json_encode(['status' => 'ai_disabled']);
            exit;
        }
        
        $calendarConfig = \App\Helpers\CalendarConfigHelper::loadFromDatabase($db);

        if ($botMode === 'classic') {
            try {
                $calendarService    = getCalendarService($logger, $db, $credentialService, $calendarConfig);
                $classicCalHandler  = null;
                $timezone           = $calendarConfig['timezone'] ?? 'America/Bogota';

                if ($calendarService) {
                    $classicCalHandler = new ClassicCalendarFlowHandler($db, $logger, $calendarService, $timezone);

                    if ($classicCalHandler->hasActiveSession($messageData['from'])) {
                        $calResult = $classicCalHandler->handleStep(
                            $messageData['text'],
                            $messageData['from'],
                            $messageData['contact_name']
                        );

                        if (empty($calResult['exited'])) {
                            $whatsapp->sendMessage($messageData['from'], $calResult['response']);
                            $conversationService->addMessage(
                                $conversation['id'], 'bot', $calResult['response'], null, null, 1.0
                            );
                            $db->query(
                                'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                                [':id' => $conversation['id']]
                            );
                            http_response_code(200);
                            echo json_encode(['status' => 'classic_calendar_step']);
                            exit;
                        }

                        $db->query(
                            "DELETE FROM classic_flow_sessions WHERE user_phone = :phone",
                            [':phone' => $messageData['from']]
                        );
                        $exitBot    = new ClassicBotService($db, $logger);
                        $exitResult = $exitBot->processMessage('menu', $messageData['from']);
                        $exitMsg    = !empty($exitResult['response'])
                            ? $exitResult['response']
                            : "Escribe el número de tu opción o escríbeme lo que necesitas.";
                        $whatsapp->sendMessage($messageData['from'], $exitMsg);
                        $conversationService->addMessage(
                            $conversation['id'], 'bot', $exitMsg, null, null, 1.0
                        );
                        $db->query(
                            'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                            [':id' => $conversation['id']]
                        );
                        http_response_code(200);
                        echo json_encode(['status' => 'classic_calendar_exited']);
                        exit;
                    }
                }

                $classicBot    = new ClassicBotService($db, $logger);
                $classicResult = $classicBot->processMessage($messageData['text'], $messageData['from']);

                if ($classicResult['type'] === 'response' || $classicResult['type'] === 'fallback' || $classicResult['type'] === 'farewell') {
                    $whatsapp->sendMessage($messageData['from'], $classicResult['response']);
                    $conversationService->addMessage(
                        $conversation['id'], 'bot', $classicResult['response'], null, null, 1.0
                    );
                    $db->query(
                        'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                    http_response_code(200);
                    echo json_encode(['status' => 'classic_' . $classicResult['type']]);
                    exit;
                }

                if ($classicResult['type'] === 'calendar') {
                    $intent = $classicResult['calendar_intent'] ?? 'schedule';

                    if ($classicCalHandler) {
                        $calResult = $classicCalHandler->start($intent, $messageData['from'], $messageData['contact_name']);
                        $msg = $calResult['response'];
                    } else {
                        $msg = $classicResult['response'];
                    }

                    $whatsapp->sendMessage($messageData['from'], $msg);
                    $conversationService->addMessage($conversation['id'], 'bot', $msg, null, null, 1.0);
                    $db->query(
                        'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                    http_response_code(200);
                    echo json_encode(['status' => 'classic_calendar']);
                    exit;
                }

            } catch (\Throwable $e) {
                $logger->error('Classic bot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $classicErrMsg = 'Lo siento, ocurrió un error procesando tu mensaje. Por favor intenta de nuevo.';
                try {
                    $whatsapp->sendMessage($messageData['from'], $classicErrMsg);
                    $conversationService->addMessage($conversation['id'], 'bot', $classicErrMsg, null, null, 0.0);
                    $db->query(
                        'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                        [':id' => $conversation['id']]
                    );
                } catch (\Throwable $ignored) {}
                http_response_code(200);
                echo json_encode(['status' => 'classic_error']);
                exit;
            }

        }

        $systemPromptRow = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'system_prompt'",
            []
        );
        $systemPrompt = $systemPromptRow['setting_value'] ?? 'Eres un asistente virtual útil y amigable.';
        
        $calendarEnabledRow = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'calendar_enabled'",
            []
        );
        $isCalendarEnabled = $calendarConfig['enabled'] 
            && (!$calendarEnabledRow || $calendarEnabledRow['setting_value'] === 'true' || $calendarEnabledRow['setting_value'] === '1');
        
        if ($isCalendarEnabled && file_exists(__DIR__ . '/prompts/calendar_prompt.txt')) {
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
        
        $cachedIntentResponse = null;
        
        if ($isCalendarEnabled) {
            $calendarService = getCalendarService($logger, $db, $credentialService, $calendarConfig);
            
            if ($calendarService) {
                $flowHandler = new CalendarFlowHandler(
                    $db, $logger, $calendarService, $openai, $calendarConfig, $conversationService
                );
                
                $flowState = $flowHandler->getFlowState($messageData['from']);
                
                if ($flowState) {
                    $flowResult = $flowHandler->handleActiveFlow(
                        $flowState, $messageData['text'], $conversation, $messageData
                    );
                    
                    if ($flowResult['handled']) {
                        $whatsapp->sendMessage($messageData['from'], $flowResult['response']);
                        $conversationService->addMessage(
                            $conversation['id'], 'bot', $flowResult['response'], null, null, 1.0
                        );
                        $db->query(
                            'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                            [':id' => $conversation['id']]
                        );
                        http_response_code(200);
                        echo json_encode(['status' => $flowResult['status']]);
                        exit;
                    }
                }
                
                $intentService = new CalendarIntentService($openai, $logger);
                $intent = $intentService->detectIntent(
                    $messageData['text'], $conversationHistory, $systemPrompt
                );
                
                $logger->info('Calendar intent detection', [
                    'intent' => $intent['intent'],
                    'confidence' => $intent['confidence']
                ]);
                
                if ($intent['intent'] !== 'none') {
                    $flowResult = $flowHandler->startFlow(
                        $intent['intent'], $intent['extracted_data'], $conversation, $messageData
                    );
                    
                    if ($flowResult['handled']) {
                        $whatsapp->sendMessage($messageData['from'], $flowResult['response']);
                        $conversationService->addMessage(
                            $conversation['id'], 'bot', $flowResult['response'], null, null, 1.0
                        );
                        $db->query(
                            'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                            [':id' => $conversation['id']]
                        );
                        http_response_code(200);
                        echo json_encode(['status' => $flowResult['status']]);
                        exit;
                    }
                }
                
                $cachedIntentResponse = $intent['original_response'];
            }
        }

        $vectorSearch = new VectorSearchService(
            $db,
            Config::get('rag.similarity_method')
        );
        
        $rag = new RAGService(
            $openai,
            $vectorSearch,
            $logger,
            3,
            0.7,
            $db
        );
        
        $ragLowConfidenceResponse = null;

        try {
            $result = $rag->generateResponse($messageData['text'], $systemPrompt, $conversationHistory, $openaiTemperature, $openaiMaxTokens);
            
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

            if ($result['response']) {
                $ragLowConfidenceResponse = $result['response'];
            }
        } catch (\Exception $e) {
            handleInsufficientFunds($db, $e);
            $logger->error('RAG Error: ' . $e->getMessage());
        }
        
        if ($cachedIntentResponse) {
            $whatsapp->sendMessage($conversation['phone_number'], $cachedIntentResponse);
            
            $conversationService->addMessage(
                $conversation['id'],
                'bot',
                $cachedIntentResponse
            );
            
            $db->query(
                'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                [':id' => $conversation['id']]
            );
            
            http_response_code(200);
            echo json_encode(['status' => 'processed', 'type' => 'intent_reuse']);
            exit;
        }
        
        $fallbackResponse = $ragLowConfidenceResponse;

        if (!$fallbackResponse) {
            try {
                $fallbackResponse = $openai->generateResponse($messageData['text'], '', $systemPrompt, $openaiTemperature, $openaiMaxTokens, $conversationHistory);
            } catch (\Exception $e) {
                handleInsufficientFunds($db, $e);
                $logger->error('OpenAI Fallback Error: ' . $e->getMessage());
            }
        }
        
        if ($fallbackResponse) {
            $whatsapp->sendMessage($conversation['phone_number'], $fallbackResponse);
            
            $conversationService->addMessage(
                $conversation['id'],
                'bot',
                $fallbackResponse
            );
            
            $db->query(
                'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                [':id' => $conversation['id']]
            );
            
            http_response_code(200);
            echo json_encode(['status' => 'processed', 'type' => $ragLowConfidenceResponse ? 'rag_low_confidence' : 'openai']);
            exit;
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
