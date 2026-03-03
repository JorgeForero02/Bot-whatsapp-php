-- Migration: Create credential tables for Part B
-- Run this AFTER schema.sql has been imported

-- Table for WhatsApp and OpenAI credentials
CREATE TABLE IF NOT EXISTS bot_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    whatsapp_phone_number_id VARCHAR(255) DEFAULT '',
    whatsapp_business_account_id VARCHAR(255) DEFAULT '',
    whatsapp_access_token TEXT DEFAULT NULL,
    whatsapp_app_secret TEXT DEFAULT NULL,
    whatsapp_verify_token VARCHAR(255) DEFAULT '',
    openai_api_key TEXT DEFAULT NULL,
    openai_model VARCHAR(100) DEFAULT 'gpt-3.5-turbo',
    openai_embedding_model VARCHAR(100) DEFAULT 'text-embedding-ada-002',
    openai_temperature DECIMAL(3,2) DEFAULT 0.70,
    openai_max_tokens INT DEFAULT 500,
    openai_timeout INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default row so there's always a record to UPDATE
INSERT INTO bot_credentials (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = 1;

-- Table for Google OAuth credentials
CREATE TABLE IF NOT EXISTS google_oauth_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) DEFAULT '',
    client_secret TEXT DEFAULT NULL,
    redirect_uri VARCHAR(512) DEFAULT '',
    access_token TEXT DEFAULT NULL,
    refresh_token TEXT DEFAULT NULL,
    token_expires_at TIMESTAMP NULL,
    calendar_id VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default row
INSERT INTO google_oauth_credentials (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = 1;

-- Expand settings table with new keys (if not already present)
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('system_prompt', 'Eres un asistente virtual inteligente y profesional especializado en atención al cliente.

CAPACIDADES PRINCIPALES:

1. Información General: Responde preguntas sobre servicios, productos y consultas generales usando tu base de conocimientos.

2. Base de Conocimientos RAG: Usa documentos cargados en el sistema para dar respuestas precisas y actualizadas.

TONO Y ESTILO:

Profesional pero cercano, respuestas concisas y directas, siempre confirma las acciones realizadas.', 'text'),
('context_messages_count', '5', 'text'),
('bot_name', 'WhatsApp Bot', 'text'),
('business_name', 'Mi Negocio', 'text'),
('timezone', 'America/Bogota', 'text'),
('welcome_message', 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?', 'text'),
('fallback_message', 'Lo siento, no encontré información relevante. Un operador humano te atenderá pronto.', 'text'),
('calendar_enabled', 'true', 'boolean')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
