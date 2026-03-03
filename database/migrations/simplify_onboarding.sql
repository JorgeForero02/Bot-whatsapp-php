-- Migration: simplify_credentials
-- Elimina las columnas que no corresponden a ninguna variable de entorno del sistema.
-- Ejecutar SOLO en bases de datos existentes. Las instalaciones nuevas usan schema.sql directamente.

ALTER TABLE bot_credentials
    DROP COLUMN IF EXISTS whatsapp_business_account_id,
    DROP COLUMN IF EXISTS openai_temperature,
    DROP COLUMN IF EXISTS openai_max_tokens,
    DROP COLUMN IF EXISTS openai_timeout;

ALTER TABLE google_oauth_credentials
    DROP COLUMN IF EXISTS redirect_uri;

-- Verificacion final:
-- DESCRIBE bot_credentials;
-- DESCRIBE google_oauth_credentials;
