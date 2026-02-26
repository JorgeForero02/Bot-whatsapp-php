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
