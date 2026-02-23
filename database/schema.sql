CREATE DATABASE IF NOT EXISTS whatsapp_rag_bot DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE whatsapp_rag_bot;

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    content_text LONGTEXT NOT NULL,
    chunk_count INT DEFAULT 0,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vectors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    chunk_text TEXT NOT NULL,
    chunk_index INT NOT NULL,
    embedding BLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_document (document_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(50) NOT NULL,
    contact_name VARCHAR(255),
    status ENUM('active', 'closed', 'pending_human') DEFAULT 'active',
    ai_enabled BOOLEAN DEFAULT TRUE,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_bot_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_phone (phone_number),
    INDEX idx_status (status),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    message_id VARCHAR(255),
    sender_type ENUM('user', 'bot', 'human') NOT NULL,
    message_text TEXT NOT NULL,
    context_used TEXT,
    confidence_score FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id),
    INDEX idx_created (created_at),
    INDEX idx_sender (sender_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('bot_name', 'WhatsApp Bot'),
('bot_greeting', 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?'),
('bot_fallback_message', 'Lo siento, no encontré información relevante. Un operador humano te atenderá pronto.'),
('human_handoff_enabled', 'true'),
('system_prompt', 'Eres un asistente virtual útil y amigable. Responde de manera clara, concisa y profesional. Usa el contexto proporcionado para dar respuestas precisas. Si no tienes suficiente información, indícalo claramente.');
