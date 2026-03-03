-- Flow nodes for Classic Bot mode
CREATE TABLE IF NOT EXISTS flow_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    trigger_keywords JSON NOT NULL DEFAULT ('[]'),
    message_text TEXT NOT NULL,
    next_node_id INT NULL,
    is_root BOOLEAN DEFAULT FALSE,
    requires_calendar BOOLEAN DEFAULT FALSE,
    position_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (next_node_id) REFERENCES flow_nodes(id) ON DELETE SET NULL,
    INDEX idx_is_root (is_root),
    INDEX idx_is_active (is_active),
    INDEX idx_position (position_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Options for each flow node (branching)
CREATE TABLE IF NOT EXISTS flow_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_id INT NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    option_keywords JSON NOT NULL DEFAULT ('[]'),
    next_node_id INT NULL,
    position_order INT DEFAULT 0,
    FOREIGN KEY (node_id) REFERENCES flow_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (next_node_id) REFERENCES flow_nodes(id) ON DELETE SET NULL,
    INDEX idx_node (node_id),
    INDEX idx_position (position_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session state for Classic Bot (mirrors calendar_flow_state pattern)
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
