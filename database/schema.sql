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
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_file_type (file_type),
    INDEX idx_is_active (is_active),
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

Profesional pero cercano, respuestas concisas y directas, siempre confirma las acciones realizadas.', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS calendar_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('bot_mode', 'ai', 'text')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS flow_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    trigger_keywords JSON NOT NULL,
    message_text TEXT NOT NULL,
    next_node_id INT NULL,
    is_root BOOLEAN DEFAULT FALSE,
    requires_calendar BOOLEAN DEFAULT FALSE,
    match_any_input TINYINT(1) NOT NULL DEFAULT 0,
    position_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (next_node_id) REFERENCES flow_nodes(id) ON DELETE SET NULL,
    INDEX idx_is_root (is_root),
    INDEX idx_is_active (is_active),
    INDEX idx_position (position_order),
    INDEX idx_match_any (match_any_input)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flow_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_id INT NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    option_keywords JSON NOT NULL,
    next_node_id INT NULL,
    position_order INT DEFAULT 0,
    FOREIGN KEY (node_id) REFERENCES flow_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (next_node_id) REFERENCES flow_nodes(id) ON DELETE SET NULL,
    INDEX idx_node (node_id),
    INDEX idx_position (position_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classic_flow_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_phone VARCHAR(50) NOT NULL,
    current_node_id INT NULL,
    attempts INT DEFAULT 0,
    expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_phone (user_phone),
    FOREIGN KEY (current_node_id) REFERENCES flow_nodes(id) ON DELETE SET NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classic_calendar_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_phone VARCHAR(50) NOT NULL UNIQUE,
    step VARCHAR(50) NOT NULL,
    data JSON,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    step_name VARCHAR(100) NOT NULL UNIQUE,
    step_order INT NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    is_skipped BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO onboarding_progress (step_name, step_order) VALUES
('whatsapp_credentials', 1),
('openai_credentials',   2),
('bot_personality',      3),
('calendar_setup',       4),
('flow_builder',         5),
('test_connection',      6),
('go_live',              7)
ON DUPLICATE KEY UPDATE step_order = VALUES(step_order);

CREATE TABLE IF NOT EXISTS bot_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    whatsapp_phone_number_id VARCHAR(255) DEFAULT '',
    whatsapp_access_token TEXT DEFAULT NULL,
    whatsapp_app_secret TEXT DEFAULT NULL,
    whatsapp_verify_token VARCHAR(255) DEFAULT '',
    openai_api_key TEXT DEFAULT NULL,
    openai_model VARCHAR(100) DEFAULT 'gpt-3.5-turbo',
    openai_embedding_model VARCHAR(100) DEFAULT 'text-embedding-ada-002',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bot_credentials (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = 1;

CREATE TABLE IF NOT EXISTS google_oauth_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) DEFAULT '',
    client_secret TEXT DEFAULT NULL,
    access_token TEXT DEFAULT NULL,
    refresh_token TEXT DEFAULT NULL,
    token_expires_at TIMESTAMP NULL,
    calendar_id VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO google_oauth_credentials (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = 1;

INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('context_messages_count', '5', 'text'),
('business_name', 'Mi Negocio', 'text'),
('timezone', 'America/Bogota', 'text'),
('welcome_message', 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?', 'text'),
('fallback_message', 'Lo siento, no encontré información relevante. Un operador humano te atenderá pronto.', 'text'),
('calendar_enabled', 'false', 'boolean'),
('confidence_threshold', '0.7', 'text'),
('max_results', '5', 'text'),
('chunk_size', '1000', 'text'),
('auto_reply', 'true', 'boolean'),
('temperature', '0.7', 'text'),
('timeout', '30', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS calendar_flow_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_phone VARCHAR(50) NOT NULL,
    conversation_id INT NOT NULL,
    current_step VARCHAR(50) NOT NULL,
    extracted_date VARCHAR(20) DEFAULT NULL,
    extracted_time VARCHAR(10) DEFAULT NULL,
    extracted_service VARCHAR(255) DEFAULT NULL,
    event_title VARCHAR(255) DEFAULT NULL,
    cancel_events_json TEXT DEFAULT NULL,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_phone (user_phone),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS query_embedding_cache (
    query_hash VARCHAR(32) NOT NULL PRIMARY KEY,
    embedding MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    hit_count INT DEFAULT 0,
    INDEX idx_last_used (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
