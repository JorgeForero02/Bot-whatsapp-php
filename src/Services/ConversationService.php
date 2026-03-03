<?php

namespace App\Services;

use App\Core\Database;

class ConversationService
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getOrCreateConversation($phoneNumber, $contactName = null)
    {
        $conversation = $this->db->fetchOne(
            'SELECT * FROM conversations WHERE phone_number = :phone',
            [':phone' => $phoneNumber]
        );

        if ($conversation) {
            $this->db->update(
                'conversations',
                ['last_message_at' => date('Y-m-d H:i:s')],
                'id = :id',
                [':id' => $conversation['id']]
            );
            return $conversation;
        }

        $id = $this->db->insert('conversations', [
            'phone_number' => $phoneNumber,
            'contact_name' => $contactName,
            'status' => 'active',
            'ai_enabled' => 1
        ]);

        return $this->db->fetchOne(
            'SELECT * FROM conversations WHERE id = :id',
            [':id' => $id]
        );
    }

    public function addMessage($conversationId, $senderType, $messageText, $messageId = null, $contextUsed = null, $confidenceScore = null, $audioUrl = null, $mediaType = 'text')
    {
        $this->db->query(
            'INSERT INTO messages (conversation_id, message_id, sender_type, message_text, audio_url, media_type, context_used, confidence_score) 
             VALUES (:conversation_id, :message_id, :sender_type, :message_text, :audio_url, :media_type, :context_used, :confidence_score)',
            [
                ':conversation_id' => $conversationId,
                ':message_id' => $messageId,
                ':sender_type' => $senderType,
                ':message_text' => $messageText,
                ':audio_url' => $audioUrl,
                ':media_type' => $mediaType,
                ':context_used' => $contextUsed,
                ':confidence_score' => $confidenceScore
            ]
        );
        
        return $this->db->lastInsertId();
    }

    public function getConversationHistory($conversationId, $limit = 50)
    {
        $limit = (int) $limit;
        return $this->db->fetchAll(
            "SELECT * FROM messages WHERE conversation_id = :id ORDER BY created_at DESC LIMIT {$limit}",
            [':id' => $conversationId]
        );
    }

    public function getAllConversations($status = null, $limit = 100)
    {
        $limit = (int) $limit;
        if ($status) {
            return $this->db->fetchAll(
                "SELECT * FROM conversations WHERE status = :status ORDER BY last_message_at DESC LIMIT {$limit}",
                [':status' => $status]
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM conversations ORDER BY last_message_at DESC LIMIT {$limit}",
            []
        );
    }

    public function updateConversationStatus($conversationId, $status)
    {
        return $this->db->update(
            'conversations',
            ['status' => $status],
            'id = :id',
            [':id' => $conversationId]
        );
    }

    public function getConversationStats()
    {
        $stats = [];
        
        $stats['total'] = $this->db->fetchOne('SELECT COUNT(*) as count FROM conversations')['count'] ?? 0;
        $stats['active'] = $this->db->fetchOne("SELECT COUNT(*) as count FROM conversations WHERE status = 'active'")['count'] ?? 0;
        $stats['pending_human'] = $this->db->fetchOne("SELECT COUNT(*) as count FROM conversations WHERE status = 'pending_human'")['count'] ?? 0;
        $stats['total_messages'] = $this->db->fetchOne('SELECT COUNT(*) as count FROM messages')['count'] ?? 0;
        
        return $stats;
    }
}
