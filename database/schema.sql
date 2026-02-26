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
    file_hash VARCHAR(32),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_file_type (file_type),
    UNIQUE KEY idx_file_hash (file_hash)
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
    event_creation_state VARCHAR(50) DEFAULT NULL,
    event_creation_attempts INT DEFAULT 0,
    event_creation_data TEXT DEFAULT NULL,
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
    audio_url VARCHAR(512) NULL,
    media_type ENUM('text', 'audio', 'image', 'video', 'document') DEFAULT 'text',
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
    setting_type ENUM('text', 'boolean', 'json') DEFAULT 'text',
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('bot_name', 'WhatsApp Bot', 'text'),
('bot_greeting', 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?', 'text'),
('bot_fallback_message', 'Lo siento, no encontré información relevante. Un operador humano te atenderá pronto.', 'text'),
('human_handoff_enabled', 'true', 'boolean'),
('openai_status', 'active', 'text'),
('openai_last_error', '', 'text'),
('openai_error_timestamp', '', 'text'),
('system_prompt', 'Eres un asistente virtual inteligente y profesional especializado en atención al cliente.

CAPACIDADES PRINCIPALES:

1. Información General: Responde preguntas sobre servicios, productos y consultas generales usando tu base de conocimientos.

2. Base de Conocimientos RAG: Usa documentos cargados en el sistema para dar respuestas precisas y actualizadas.

TONO Y ESTILO:

Profesional pero cercano, respuestas concisas y directas, siempre confirma las acciones realizadas.', 'text');

-- Tabla de configuración de Google Calendar
CREATE TABLE IF NOT EXISTS calendar_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración inicial de Calendar
INSERT INTO calendar_settings (setting_key, setting_value) VALUES
('timezone', 'America/Bogota'),
('default_duration_minutes', '60'),
('max_events_per_day', '10'),
('min_advance_hours', '1'),
('business_hours_monday', '{"enabled":true,"start":"09:00","end":"18:00"}'),
('business_hours_tuesday', '{"enabled":true,"start":"09:00","end":"18:00"}'),
('business_hours_wednesday', '{"enabled":true,"start":"09:00","end":"18:00"}'),
('business_hours_thursday', '{"enabled":true,"start":"09:00","end":"18:00"}'),
('business_hours_friday', '{"enabled":true,"start":"09:00","end":"18:00"}'),
('business_hours_saturday', '{"enabled":true,"start":"10:00","end":"14:00"}'),
('business_hours_sunday', '{"enabled":false,"start":"09:00","end":"18:00"}'),
('reminder_email_enabled', 'true'),
('reminder_email_minutes', '1440'),
('reminder_popup_enabled', 'true'),
('reminder_popup_minutes', '30')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
