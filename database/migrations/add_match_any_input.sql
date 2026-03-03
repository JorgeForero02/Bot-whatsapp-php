-- Migration: add match_any_input to flow_nodes
-- Run this on existing databases that already have the flow_nodes table.
-- Safe to run multiple times only if your MySQL version supports IF NOT EXISTS for ADD COLUMN (MySQL 8.0+).
-- For MySQL 5.7 / MariaDB: run manually only if the column does not already exist.

ALTER TABLE flow_nodes
    ADD COLUMN match_any_input TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_calendar;

ALTER TABLE flow_nodes
    ADD INDEX idx_match_any (match_any_input);
