-- Onboarding wizard progress tracking
CREATE TABLE IF NOT EXISTS onboarding_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    step_name VARCHAR(100) NOT NULL UNIQUE,
    step_order INT NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    is_skipped BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert the 7 onboarding steps in order
INSERT INTO onboarding_progress (step_name, step_order) VALUES
('whatsapp_credentials', 1),
('openai_credentials',   2),
('bot_personality',      3),
('calendar_setup',       4),
('flow_builder',         5),
('test_connection',      6),
('go_live',              7)
ON DUPLICATE KEY UPDATE step_order = VALUES(step_order);
