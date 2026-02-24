-- Agregar columna para guardar URL de audios de WhatsApp
USE whatsapp_rag_bot;

ALTER TABLE messages 
ADD COLUMN audio_url VARCHAR(512) NULL AFTER message_text,
ADD COLUMN media_type ENUM('text', 'audio', 'image', 'video', 'document') DEFAULT 'text' AFTER audio_url;
