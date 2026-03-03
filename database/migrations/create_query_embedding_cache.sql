-- Migration: Add query embedding cache and is_active to documents
-- Run this on existing databases. New installs use schema.sql directly.

-- Add is_active column to documents if not exists
ALTER TABLE documents ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER file_hash;
ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_is_active (is_active);

-- Create query embedding cache table
CREATE TABLE IF NOT EXISTS query_embedding_cache (
    query_hash VARCHAR(32) NOT NULL PRIMARY KEY,
    embedding MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    hit_count INT DEFAULT 0,
    INDEX idx_last_used (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
