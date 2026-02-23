<?php

require_once __DIR__ . '/../vendor/autoload.php';

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
    $config = Config::load(__DIR__ . '/../config/config.php');
    $logger = new Logger(__DIR__ . '/../logs');
    
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
        
        $messageData = $whatsapp->parseWebhookPayload($payload);
        
        if (!$messageData) {
            http_response_code(200);
            echo json_encode(['status' => 'ignored']);
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
            $messageData['message_id']
        );
        
        if ($messageData['message_id']) {
            $whatsapp->markAsRead($messageData['message_id']);
        }
        
        $humanKeywords = ['hablar con humano', 'hablar con una persona', 'hablar con operador', 
                          'quiero un humano', 'atención humana', 'operador', 'agente humano',
                          'hablar con alguien', 'persona real', 'representante'];
        
        $messageLower = mb_strtolower($messageData['text']);
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
            http_response_code(200);
            echo json_encode(['status' => 'ai_disabled']);
            exit;
        }
        
        $openai = new OpenAIService(
            Config::get('openai.api_key'),
            Config::get('openai.model'),
            Config::get('openai.embedding_model'),
            $logger
        );
        
        $vectorSearch = new VectorSearchService(
            $db,
            Config::get('rag.similarity_method')
        );
        
        $rag = new RAGService(
            $openai,
            $vectorSearch,
            $logger,
            Config::get('rag.top_k_results'),
            Config::get('rag.similarity_threshold')
        );
        
        $result = $rag->generateResponse($messageData['text']);
        
        if (!$result['response'] || $result['confidence'] < Config::get('rag.similarity_threshold')) {
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
            
            $conversationService->updateConversationStatus($conversation['id'], 'pending_human');
            
            $db->query(
                'UPDATE conversations SET last_bot_message_at = NOW() WHERE id = :id',
                [':id' => $conversation['id']]
            );
            
            http_response_code(200);
            echo json_encode(['status' => 'fallback', 'confidence' => $result['confidence']]);
            exit;
        }
        
        $whatsapp->sendMessage($messageData['from'], $result['response']);
        
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
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    
} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Webhook Error: ' . $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
