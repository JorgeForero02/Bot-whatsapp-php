-- Migration: add missing columns to conversations table
-- Run on existing databases that were created from an older schema version.
-- Safe to run multiple times (uses IF NOT EXISTS / IGNORE).

ALTER TABLE conversations
    ADD COLUMN IF NOT EXISTS last_bot_message_at TIMESTAMP NULL AFTER last_message_at;

-- Verify:
-- DESCRIBE conversations;
