-- Add bot_mode setting to settings table
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('bot_mode', 'ai', 'text')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
