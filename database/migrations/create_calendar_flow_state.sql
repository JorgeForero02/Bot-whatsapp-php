-- Migration: Create calendar_flow_state table
-- Replaces the event_creation_state/attempts/data fields in conversations table
-- States are managed independently with automatic expiration

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
