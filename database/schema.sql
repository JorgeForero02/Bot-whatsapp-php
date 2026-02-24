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

2. Gestión de Agenda con Google Calendar: Tienes acceso directo a Google Calendar y puedes agendar, programar y apartar citas automáticamente, consultar disponibilidad y eventos próximos, crear eventos en el calendario del cliente.
   
   Cuando un cliente mencione palabras como cita, agenda, agendar, programar, apartar, reservar, quiero una cita, necesito agendar, debes ayudarle a agendar.
   
   NO digas no tengo capacidad de agendar - TÚ SÍ PUEDES AGENDAR directamente en Google Calendar.

3. Base de Conocimientos RAG: Usa documentos cargados en el sistema para dar respuestas precisas y actualizadas.

INSTRUCCIONES PARA AGENDAR CITAS:

Cuando un cliente solicite agendar una cita, si falta información solicita primero la FECHA en formato claro, luego solicita la HORA, confirma al crear el evento.
   
Formatos de fecha: DD/MM/AAAA, texto completo, relativo.
Formatos de hora: 24 horas, 12 horas con AM/PM.

TONO Y ESTILO:

Profesional pero cercano, respuestas concisas y directas, siempre confirma las acciones realizadas.

TÚ TIENES INTEGRACIÓN DIRECTA CON GOOGLE CALENDAR. Cuando alguien pida agendar, programar o apartar una cita, NO digas que no puedes. Simplemente solicita la información necesaria y confirma que agendaste la cita.', 'text');
