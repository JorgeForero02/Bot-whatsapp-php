ALTER TABLE settings ADD COLUMN IF NOT EXISTS setting_type ENUM('text', 'boolean', 'json') DEFAULT 'text' AFTER setting_key;

INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('openai_status', 'active', 'text'),
('openai_last_error', '', 'text'),
('openai_error_timestamp', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
